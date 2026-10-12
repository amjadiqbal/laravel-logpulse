# Contributing

## CI is local

GitHub workflows don't run checks for this repo (Amjad, 2026-10-10: failed runs flood his email). The `CI`
workflow is disabled and its file (`.github/workflows/run-tests.yml`) stays only as a reference for what the local
run covers. Don't re-enable or trigger it.

Every PR runs the same checks on the developer's machine and pastes the summary block into its description:

```bash
scripts/ci-local.sh                    # composer install, Pest, Pint --test and PHPStan on PHP 8.2 and 8.3
PHP_VERSIONS=8.2 scripts/ci-local.sh   # one PHP version only
```

It finds `php8.2`/`php8.3` on PATH or Homebrew's `php@8.x` and ends with one `pass`/`FAIL` line per step.
`composer.json` pins `config.platform.php` to 8.2.0, the lowest PHP the dev tooling installs on, so the lock stays
installable on both versions.
