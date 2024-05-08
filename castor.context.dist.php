<?php

use Castor\Attribute\AsContext;
use Castor\Context;

use function Castor\load_dot_env;

#[AsContext(name: 'dev', default: true)]
function dev_context(): Context
{
    $env = load_dot_env();

    return new Context(
        data: [
            'in_builder' => isset($_SERVER['IN_BUILDER']) && '1' === $_SERVER['IN_BUILDER'],
        ] + COMMON_CONTEXT + $env,
        environment: $_ENV + $_SERVER + [
            'SYMFONY_DEPRECATIONS_HELPER' => 'disabled',
        ],
        tty: true,
        timeout: null
    );
}

#[AsContext(name: 'prod', default: false)]
function prod_context(): Context
{
    $env = load_dot_env();

    return new Context(
        data: [
            'in_builder' => isset($_SERVER['IN_BUILDER']) && '1' === $_SERVER['IN_BUILDER'],
            'app_env' => 'prod',
            'user' => 'www-data',
        ] + COMMON_CONTEXT + $env,
        environment: $_ENV + $_SERVER,
        timeout: null
    );
}

#[AsContext(name: 'backup', default: false)]
function backup_context(): Context
{
    $prod = prod_context();

    return $prod
        ->withTty(false)
        ->withPty(false)
    ;
}
