#!/usr/bin/env bash
# Baut das Release-Paket dist/easywetter-<version>.zip
# (identisch mit dem Download auf der Seite "Selbst installieren").
# Das Paket kann im Admin unter "System & Updates" hochgeladen oder bei GitHub als Release-Asset angehängt werden.
set -euo pipefail
cd "$(dirname "$0")/.."
if command -v php >/dev/null 2>&1; then
  php bin/package.php
else
  docker compose exec -T app php bin/package.php
fi
