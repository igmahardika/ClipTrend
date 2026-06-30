#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
export PHP_CLI_SERVER_WORKERS=6
exec php -c "$ROOT/php-local.ini" artisan serve "$@"
