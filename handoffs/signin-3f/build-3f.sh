#!/bin/sh
# Release 3f, sign-in (handoffs/signin-3f.md): replays src/3f.patch on the live mirror (default /home/user/traverse-3way-sync)
# into out/, adds release.json, and zips it as signin-3f.zip. A hunk that no longer applies stops the build: re-apply that
# change by hand on the newest live copy (builder playbook §3).
set -e
M=${1:-/home/user/traverse-3way-sync}; S=$(cd "$(dirname "$0")" && pwd); O=$S/out
rm -rf "$O" && mkdir -p "$O"
for f in $(grep '^+++ b/' "$S/src/3f.patch" | sed 's#^+++ b/##'); do
  live=$(echo "$f" | sed 's#^website/#sync-storage/#; s#^app/#sync-app/#'); mkdir -p "$O/$(dirname "$f")"
  if [ -f "$M/$live" ]; then cp "$M/$live" "$O/$f"; fi
done
(cd "$O" && patch -p1 --no-backup-if-mismatch -s < "$S/src/3f.patch")
cp "$S/src/release.json" "$O/release.json"
rm -f "$S/signin-3f.zip" && (cd "$O" && zip -q -r "$S/signin-3f.zip" .)
echo "built $S/signin-3f.zip from $(git -C "$M" log --oneline -1)"
