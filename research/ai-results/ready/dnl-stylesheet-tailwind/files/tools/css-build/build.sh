#!/bin/sh
# Builds css/site.css from the site's files with the tailwindcss CLI.
# Run this in the lane session, never on Bluehost (no node there).
# css/site.css is committed and goes in the deploy zip.
#
# Version: v3 is the line the Play CDN (cdn.tailwindcss.com) serves.
# LANE: confirm the exact version against the live tag before trusting 3.4.17.
# If the live tag loads plugins (?plugins=forms,typography,...), use this instead:
#   npx --yes -p tailwindcss@3.4.17 -p @tailwindcss/forms tailwindcss ...
set -eu
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"
npx --yes tailwindcss@3.4.17 \
  -c tools/css-build/tailwind.config.cjs \
  -i tools/css-build/input.css \
  -o css/site.css \
  --minify
if [ -f tools/css-tests/stylesheet-test.php ]; then
  php tools/css-tests/stylesheet-test.php
else
  echo "FAIL tools/css-tests/stylesheet-test.php not installed"
  exit 1
fi
