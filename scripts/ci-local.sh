#!/usr/bin/env bash
# Local CI (CONTRIBUTING.md → "CI is local"): the CI workflow is disabled, so every PR runs this and pastes the
# summary into its body. Same steps as .github/workflows/run-tests.yml (kept for reference): PHP 8.2 and 8.3, each with
# composer install, Pest (composer test), Pint --test and PHPStan (composer analyse).
#   scripts/ci-local.sh                    both PHP versions
#   PHP_VERSIONS=8.2 scripts/ci-local.sh
# Finds php8.2 / php8.3 on PATH, or Homebrew's php@8.x.
set -uo pipefail
cd "$(dirname "$0")/.."

PHP_VERSIONS=${PHP_VERSIONS:-"8.2 8.3"}
RESULTS=(); FAILED=0
step() { local name=$1; shift; printf '\n== %s\n' "$name"; if "$@"; then RESULTS+=("pass  $name"); else RESULTS+=("FAIL  $name"); FAILED=1; fi; }

php_bin() {
  local v=$1 p
  for p in "$(command -v "php$v" 2>/dev/null)" "$(brew --prefix 2>/dev/null)/opt/php@$v/bin/php"; do
    [ -n "$p" ] && [ -x "$p" ] && "$p" -r 'exit(PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION === $argv[1] ? 0 : 1);' "$v" && { echo "$p"; return; }
  done
  return 1
}

COMPOSER=$(command -v composer)
SHIM=$(mktemp -d); trap 'rm -rf "$SHIM"' EXIT
BASE_PATH=$PATH
for v in $PHP_VERSIONS; do
  PHP=$(php_bin "$v") || { RESULTS+=("FAIL  PHP $v not found"); FAILED=1; continue; }
  # vendor/bin scripts start with "#!/usr/bin/env php": put this version first on PATH so they don't run on the default PHP.
  ln -sf "$PHP" "$SHIM/php"; export PATH="$SHIM:$BASE_PATH"
  step "PHP $v active" sh -c "php -r 'exit(PHP_MAJOR_VERSION.\".\".PHP_MINOR_VERSION === \"$v\" ? 0 : 1);'"
  step "PHP $v composer install" "$PHP" "$COMPOSER" install --no-interaction --prefer-dist --no-progress
  step "PHP $v pest"             "$PHP" "$COMPOSER" test
  step "PHP $v pint"             "$PHP" vendor/bin/pint --test
  step "PHP $v phpstan"          "$PHP" "$COMPOSER" analyse
done

printf '\n== Summary (%s, %s)\n' "$(git rev-parse --short HEAD)" "$(date '+%Y-%m-%d %H:%M')"
printf '%s\n' "${RESULTS[@]}"
exit $FAILED
