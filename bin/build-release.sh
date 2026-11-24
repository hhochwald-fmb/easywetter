#!/usr/bin/env bash
# Baut die Downloads (Arduino-ZIP, MicroPython) und ein Release-Paket dist/easywetter-<version>.zip
# Das Paket kann im Admin-Bereich unter "System & Updates" hochgeladen oder bei GitHub als Release-Asset angehängt werden.
set -euo pipefail
cd "$(dirname "$0")/.."
VERSION=$(tr -d '[:space:]' < VERSION)

echo "→ Downloads bauen"
rm -f public/downloads/EasyWetter-Arduino.zip
(cd libraries/arduino && zip -qr ../../public/downloads/EasyWetter-Arduino.zip EasyWetter -x '*.DS_Store')
cp libraries/micropython/easywetter.py public/downloads/easywetter.py
cp libraries/micropython/main.py public/downloads/main.py
cp libraries/micropython/main_dht22.py public/downloads/main_dht22.py

echo "→ Release-Paket $VERSION bauen"
rm -rf dist && mkdir -p dist/easywetter
rsync -a ./ dist/easywetter/ \
  --exclude '.git' --exclude 'dist' --exclude '.DS_Store' \
  --exclude 'config/config.php' --exclude 'storage/logs/*' --exclude 'storage/tmp/*' --exclude 'storage/backups'
touch dist/easywetter/storage/logs/.gitkeep dist/easywetter/storage/tmp/.gitkeep
(cd dist && zip -qr "easywetter-$VERSION.zip" easywetter && rm -rf easywetter)
echo "✓ dist/easywetter-$VERSION.zip"
