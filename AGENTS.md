# Agent Instructions

## Base Rules

- Use existing classes, methods, components
- Use the same patterns; new features are usually variations of existing ones
- Refactor often
- Do not be too verbose
- Do not add comments everywhere; prefer good naming
- Find the root cause of bugs, do not try workarounds
- Do not implement defensive code by default


## Environment

Commands run via Docker through Castor. Prefix host commands with `castor builder`.

Example: `bin/console clear:cache` → `castor builder bin/console clear:cache`


## PHP and Symfony

- PHP 8.4 syntax
- Never use `empty` except when it is the only option
- Use positional and named arguments instead of default values
- Use latest Symfony version
- Prefer attributes to config files
- Do not remove debug code unless asked: `$this->client->enableProfiler()`, `dump`, `dd`
- Create repository methods and do not use the `findOneBy` and `findBy` shortcuts
