<?php

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

use function Castor\context;
use function Castor\import;
use function Castor\io;
use function Castor\run;
use function Castor\variable;

// CONTEXTS

const COMMON_CONTEXT = [
    'app_env' => 'dev',
    'app_name' => 'trailodyssey',
];

if (file_exists(__DIR__ . '/castor.context.php')) {
    import(__DIR__ . '/castor.context.php');
} else {
    import(__DIR__ . '/castor.context.dist.php');
}

// COMMANDS

#[AsTask(namespace: 'infra', description: 'Start the stack', aliases: ['up'])]
function up(
    #[AsOption(description: 'Ask for a rebuild')]
    bool $build = false,
    #[AsOption(description: 'Does not start a Traefik router')]
    bool $noRouter = false,
): void {
    $command = [];
    if (!$noRouter) {
        $command[] = '-f';
        $command[] = 'docker-traefik-dev.yml';
    }
    $command[] = 'up';
    if ($build) {
        $command[] = '--build';
    }
    $command[] = '-d';
    $command[] = '--wait';
    docker_compose($command);
}

#[AsTask(namespace: 'infra', description: 'Build the stack')]
function build(#[AsOption] bool $noCache = false): void
{
    $command = ['build'];
    if ($noCache) {
        $command[] = '--no-cache';
    }
    docker_compose($command);
}

#[AsTask(namespace: 'infra', description: 'Force rebuild the stack')]
function rebuild(): void
{
    build(noCache: true);
}

#[AsTask(namespace: 'infra', description: 'Stop the stack', aliases: ['down'])]
function down(): void
{
    docker_compose(['down']);
}

#[AsTask(namespace: 'infra', description: 'Install the app')]
function install(): void
{
    if (is_prod()) {
        run_in_builder('composer install --no-dev --optimize-autoloader');
    } else {
        run_in_builder('composer install');
    }
    run_in_builder('yarn install');
    if (is_prod()) {
        run_in_builder('yarn run build');
    } else {
        run_in_builder('yarn dev');
    }
    migrate();
}

#[AsTask(namespace: 'dev', description: 'Make migration')]
function make_migration(): void
{
    assert_not_in_prod();
    run_in_builder('bin/console doctrine:migrations:diff --formatted --allow-empty-diff'); // --from-empty-schema
}

#[AsTask(namespace: 'app', description: 'Migrate database')]
function migrate(): void
{
    run_in_builder('bin/console doctrine:database:create -n --if-not-exists');
    run_in_builder('bin/console doctrine:migrations:migrate -n --allow-no-migration');
}

#[AsTask(namespace: 'app', description: 'Install cron scripts')]
function install_cron(): void
{
    run_in_builder("bash -c 'cat ./config/crontab | crontab -'");
}

#[AsTask(namespace: 'app', description: 'Start background scripts', aliases: ['bg-start', 'start-bg'])]
function background_scripts_start(#[AsOption] int $limit = 1): void
{
    if (is_prod()) {
        run_in_builder('supervisord -c ./config/supervisord.conf', user: 'root');
        io()->success('Background scripts started');
    } else {
        run_in_builder("bin/console messenger:consume async -vvv --memory-limit=512M --time-limit=3600 --limit=$limit");
    }
}

#[AsTask(namespace: 'app', description: 'Stop background scripts', aliases: ['bg-stop', 'stop-bg'])]
function background_scripts_stop(): void
{
    if (is_prod()) {
        run_in_builder('supervisorctl -c ./config/supervisord.conf stop all', allowFailure: true, user: 'root');
        run_in_builder('supervisorctl -c ./config/supervisord.conf shutdown', allowFailure: true, user: 'root');
    }

    run_in_builder('bin/console messenger:stop-workers', allowFailure: true);
    io()->success('Background scripts stopped');
}

#[AsTask(namespace: 'app', description: 'Log background scripts', aliases: ['bg-log', 'log-bg'])]
function background_scripts_log(): void
{
    if (is_prod()) {
        run_in_builder('tail -f var/log/messenger-consume_00-std* var/log/supervisord.log', allowFailure: true, user: 'root');
    } else {
        io()->warning('This command does not do any operation in dev');
    }
}

#[AsTask(namespace: 'app', description: 'Clear cache', aliases: ['cc'])]
function clear_cache(#[AsArgument] string $env): void
{
    run_in_builder("bin/console cache:clear --env=$env");
    run_in_builder("bin/console cache:warmup --env=$env");
}

#[AsTask(namespace: 'dev', description: 'Load fixture [drop database]')]
function load_fixture(#[AsArgument] string $env): void
{
    assert_not_in_prod();
    io()->warning('There are no fixtures for now.');

    // run_in_builder("bin/console doctrine:database:drop -n --force --if-exists --env=$env");
    // run_in_builder("bin/console doctrine:database:create -n --if-not-exists --env=$env");
    // run_in_builder("bin/console doctrine:migration:migrate -n --allow-no-migration --env=$env");
    // run_in_builder("bin/console doctrine:fixtures:load -n --append --env=$env");
}

#[AsTask(namespace: 'backup', description: 'Make a backup of the database')]
function db_dump(#[AsOption] ?string $clean = null): void
{
    assert_is_in_builder();

    $directory = '/backup';
    $name = $directory . '/database-' . date_string();
    $POSTGRES_HOST = 'postgres';
    $POSTGRES_PASSWORD = $_SERVER['POSTGRES_PASSWORD'] ?? throw new Exception('Missing POSTGRES_PASSWORD env.');
    $POSTGRES_USER = $_SERVER['POSTGRES_USER'] ?? throw new Exception('Missing POSTGRES_USER env.');
    $POSTGRES_DB = $_SERVER['POSTGRES_DB'] ?? throw new Exception('Missing POSTGRES_DB env.');
    $POSTGRES_PORT = $_SERVER['POSTGRES_PORT'] ?? throw new Exception('Missing POSTGRES_PORT env.');
    run_in_builder("PGPASSWORD=$POSTGRES_PASSWORD pg_dump -h $POSTGRES_HOST -U $POSTGRES_USER -p $POSTGRES_PORT -f $name.sql $POSTGRES_DB");

    if ($clean) {
        $finder = new Finder();
        $finder->files()
            ->in($directory)
            ->date("< $clean ago");

        foreach ($finder as $file) {
            unlink($file->getPathname());
            io()->info($file->getPathname() . ' deleted');
        }
    }

    io()->success('Backup saved');
}

#[AsTask(namespace: 'backup', description: 'Restore a backup of the database')]
function db_restore(#[AsArgument] string $filePath): void
{
    assert_is_in_builder();

    $POSTGRES_HOST = 'postgres';
    $POSTGRES_PASSWORD = $_SERVER['POSTGRES_PASSWORD'] ?? throw new Exception('Missing POSTGRES_PASSWORD env.');
    $POSTGRES_USER = $_SERVER['POSTGRES_USER'] ?? throw new Exception('Missing POSTGRES_USER env.');
    $POSTGRES_DB = $_SERVER['POSTGRES_DB'] ?? throw new Exception('Missing POSTGRES_DB env.');
    $POSTGRES_PORT = $_SERVER['POSTGRES_PORT'] ?? throw new Exception('Missing POSTGRES_PORT env.');
    run_in_builder('bin/console doctrine:database:drop --force');
    run_in_builder('bin/console doctrine:database:create');
    run_in_builder("PGPASSWORD=$POSTGRES_PASSWORD psql -h $POSTGRES_HOST -U $POSTGRES_USER -p $POSTGRES_PORT $POSTGRES_DB -f $filePath");
    io()->success('Backup restored');
}

#[AsTask(namespace: 'deploy', description: 'Pre Deploy Script')]
function pre(): void
{
    assert_not_in_dev();
    assert_is_in_builder();

    background_scripts_stop();

    db_dump();

    io()->success('Pre Deploy success');
}

#[AsTask(namespace: 'deploy', description: 'Post Deploy Script')]
function post(): void
{
    assert_not_in_dev();
    assert_is_in_builder();

    install();
    migrate();

    background_scripts_start();
    install_cron();

    io()->success('Post Deploy success');
}

#[AsTask(description: 'Boot a builder')]
function builder(#[AsOption] ?string $user = null): void
{
    if (is_builder()) {
        io()->warning('Already in the builder');

        return;
    }

    $user ??= variable('user', '');
    if ($user) {
        $user = "--user=$user";
    }

    $builderService = variable('app_name') . '-php-fpm';
    $docker = "docker exec -it $user $builderService bash";

    io()->text("=> Will run via $docker");

    run($docker, context: context()->withAllowFailure()->withTty()->withPty());
}

#[AsTask(namespace: 'dev', description: 'Give Symfony version as a test command')]
function version(): void
{
    io()->info(is_prod() ? 'prod' : 'dev');
    io()->info(is_builder() ? 'builder' : 'not builder');
    run_in_builder('bin/console --version');
}

#[AsTask(namespace: 'dev', description: 'Run tests', aliases: ['test', 'tests'], ignoreValidationErrors: true)]
function tests(#[AsRawTokens] array $params = []): void
{
    load_fixture('test');
    run_in_builder('vendor/bin/phpunit ' . implode(' ', $params));
}

#[AsTask(namespace: 'dev', description: 'Run PHPStan', aliases: ['phpstan'])]
function phpstan(): void
{
    run_in_builder('vendor/bin/phpstan analyse --memory-limit 512M');
}

#[AsTask(namespace: 'dev', description: 'Run PHP-Coding-Style', aliases: ['phpcs'])]
function phpcs(): void
{
    run_in_builder('vendor/bin/php-cs-fixer fix');
    // run_in_builder('vendor/bin/twig-cs-fixer lint --config=.twig-cs-fixer.php --fix ./templates');
}

#[AsTask(namespace: 'dev', description: 'Run eslint fix', aliases: ['eslint'])]
function js_eslint(): void
{
    run_in_builder('yarn run eslint');
}

#[AsTask(namespace: 'dev', description: 'Run scripts that validates code before a git commit')]
function pre_commit(): void
{
    phpcs();
    phpstan();
    js_eslint();
    tests();
}

#[AsTask(namespace: 'proxy', description: 'composer command called in the builder', aliases: ['composer'])]
function composer(#[AsArgument] array $params = []): void
{
    run_in_builder('composer ' . implode(' ', $params));
}

#[AsTask(namespace: 'proxy', description: 'console command called in the builder', aliases: ['bin/console', 'console'])]
function console(#[AsArgument] array $params = []): void
{
    run_in_builder('bin/console ' . implode(' ', $params));
}

#[AsTask(namespace: 'proxy', description: 'yarn command called in the builder', aliases: ['yarn'])]
function yarn(#[AsArgument] array $params = []): void
{
    run_in_builder('yarn ' . implode(' ', $params));
}

#[AsTask(namespace: 'dev', aliases: ['tunnel'])]
function tunnel(#[AsOption] string $domain): void
{
    assert_not_in_prod();
    assert_not_in_builder();
    $appName = variable('app_name');

    run("ngrok http --host-header=$appName.test 443 --domain=$domain");
}

// HELPERS

function is_prod(): bool
{
    return 'prod' === variable('app_env');
}

function is_builder(): bool
{
    return variable('in_builder');
}

function date_string(): string
{
    return (new DateTime())->format('Ymd-His');
}

function run_in_builder(string $runCommand, bool $allowFailure = false, ?string $user = null): Process
{
    if (is_builder()) {
        return run($runCommand, context: context()->withAllowFailure($allowFailure));
    }

    return docker_exec($runCommand, allowFailure: $allowFailure, user: $user);
}

function docker_exec(string $runCommand, bool $allowFailure = false, ?string $service = null, ?string $user = null): Process
{
    $user ??= variable('user', '');
    if ($user) {
        $user = "--user=$user";
    }

    $service = $service ?: variable('app_name') . '-php-fpm';
    $context = context();
    $it = $context->tty || $context->pty ? '-it' : '';
    $docker = "docker exec $it $user $service $runCommand";

    io()->text("=> Will run: $docker");

    return run($docker, context: context()->withAllowFailure($allowFailure));
}

function assert_is_in_builder(): void
{
    if (!is_builder()) {
        throw new Exception('Can not run this command out of the builder');
    }
}

function assert_not_in_builder(): void
{
    if (is_builder()) {
        throw new Exception('Can not run this command in builder');
    }
}

function assert_not_in_prod(): void
{
    if (is_prod()) {
        throw new Exception('Can not run this command in production');
    }
}

function assert_not_in_dev(): void
{
    if (!is_prod()) {
        throw new Exception('Can not run this command in dev');
    }
}

/**
 * @param array<string> $subCommand
 */
function docker_compose(array $subCommand, bool $allowFailure = false): Process
{
    $command = [
        'docker',
        'compose',
        '-p', variable('app_name'),
        '-f',
        'docker-compose.yml',
    ];

    if (!is_prod()) {
        $command[] = '-f';
        $command[] = 'docker-compose-dev.yml';
    }

    $command = array_merge($command, $subCommand);
    $fullCommand = implode(' ', $command);

    io()->text("=> Will run: $fullCommand");

    return run($command, context: context()->withAllowFailure($allowFailure));
}
