<?php

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Symfony\Component\Process\Process;

use function Castor\capture;
use function Castor\context;
use function Castor\import;
use function Castor\io;
use function Castor\run;
use function Castor\variable;

// CONTEXTS

const COMMON_CONTEXT = [
    'app_env' => 'dev',
    'app_name' => 'trailodyssey',
    'dir_backup' => __DIR__ . '/../backup',
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

#[AsTask(namespace: 'prod', description: 'Backup the app')]
function backup(): void
{
    if (is_builder()) {
        io()->warning('Can not backup in builder');

        return;
    }

    $dir = variable('dir_backup');

    $dateString = date_string();
    $revision = capture(['git', 'rev-parse', 'HEAD']);
    $filename = "{$dateString}_$revision";

    backup_database($dir, $filename);

    io()->success('Backup success');
}

#[AsTask(namespace: 'prod', description: 'Backup database')]
function backup_database(#[AsArgument] string $directory, #[AsArgument] string $filename = 'database'): void
{
    assert_not_in_builder();
    io()->text('Backing-up database ...');
    $databasePassword = variable('MYSQL_ROOT_PASSWORD');
    $appName = variable('app_name');
    docker_exec(
        "mysqldump -P 3306 -u root -p$databasePassword $appName | gzip -9 > $directory/$filename.sql.gz",
        service: "$appName-mysql",
        user: 'root'
    );
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

    run_in_builder("bin/console doctrine:database:drop -n --force --if-exists --env=$env");
    run_in_builder("bin/console doctrine:database:create -n --if-not-exists --env=$env");
    run_in_builder("bin/console doctrine:migration:migrate -n --allow-no-migration --env=$env");
    io()->warning('There are no fixtures for now.');
    // run_in_builder("bin/console doctrine:fixtures:load -n --append --env=$env");
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

    run($docker, tty: true, pty: true, allowFailure: true);
}

#[AsTask(namespace: 'dev', description: 'Give Symfony version as a test command')]
function version(): void
{
    io()->info(is_prod() ? 'prod' : 'dev');
    io()->info(is_builder() ? 'builder' : 'not builder');
    run_in_builder('bin/console --version');
}

#[AsTask(namespace: 'dev', description: 'Run tests', aliases: ['test', 'tests'])]
function tests(#[AsArgument] array $params = []): void
{
    load_fixture('test');
    run_in_builder('bin/phpunit ' . implode(' ', $params));
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
        return run($runCommand, allowFailure: $allowFailure);
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

    return run($docker, allowFailure: $allowFailure);
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

    return run($command, allowFailure: $allowFailure);
}
