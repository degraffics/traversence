#!/bin/sh
# The phone menu (handoffs/phone-menu.md): replays src/pm.patch on the live mirror (default /home/user/traverse-3way-sync)
# into out/, adds release.json, and zips it as phone-menu.zip. A hunk that no longer applies stops the build: re-apply that
# change by hand on the newest live copy (builder playbook §3).
set -e
M=${1:-/home/user/traverse-3way-sync}; S=$(cd "$(dirname "$0")" && pwd); O=$S/out
rm -rf "$O" && mkdir -p "$O"
for f in $(grep '^+++ b/' "$S/src/pm.patch" | sed 's#^+++ b/##'); do
  live=$(echo "$f" | sed 's#^website/#sync-storage/#; s#^app/#sync-app/#'); mkdir -p "$O/$(dirname "$f")"
  if [ -f "$M/$live" ]; then cp "$M/$live" "$O/$f"; fi
done
(cd "$O" && patch -p1 --no-backup-if-mismatch -s < "$S/src/pm.patch")
cp "$S/src/release.json" "$O/release.json"
rm -f "$S/phone-menu.zip" && (cd "$O" && zip -q -r "$S/phone-menu.zip" .)
echo "built $S/phone-menu.zip from $(git -C "$M" log --oneline -1)"
