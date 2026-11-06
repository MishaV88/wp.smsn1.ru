#!/usr/bin/env bash
# Собирает устанавливаемый архив плагина в dist/wp-smsn1-ru.zip
set -euo pipefail
cd "$(dirname "$0")/.."

SLUG="wp-smsn1-ru"
FILES=("wp-smsn1-ru.php" "includes" "templates" "assets" "readme.txt" "LICENSE")

rm -rf dist
mkdir -p "dist/$SLUG"
for f in "${FILES[@]}"; do
  cp -R "$f" "dist/$SLUG/"
done

(cd dist && zip -qr "$SLUG.zip" "$SLUG" && rm -rf "$SLUG")
echo "Собрано: dist/$SLUG.zip"
