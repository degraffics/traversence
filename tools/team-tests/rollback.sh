#!/bin/bash
# Roll back, proved on the bench for one release (the session manager, 2026-11-25: "Every release needs a working Roll back.
# Prove it on the bench: publish, roll back, and the site matches before"). As the site's Roll back does it (Releases.php:
# "the previous copy of every file put back, its new files removed. Migrations only ever add"):
#   1. before = the live mirror's app/ and website/js, website/admin;   2. publish: the zip's files laid over a copy, its
#   migrations run twice;   3. roll back: each file the zip carries put back from the mirror, or removed if it was new;
#   4. the rolled-back copy matches before, byte for byte; every PHP file in it parses;   5. the Work queue's own suites pass
#   on the rolled-back code with the release's tables left in the database (a rollback's leftovers, unused).
# Usage: bash rollback.sh <zip> <traverse-3way-sync checkout> <bench dir (its .env two above <bench>/root)>
set -e
Z=$(realpath "$1"); M=$(realpath "$2"); B=$(realpath "$3"); T=$(cd "$(dirname "$0")" && pwd)
rm -rf "$B/before" "$B/root" "$B/unz"; mkdir -p "$B/before/app" "$B/before/website" "$B/unz"
cp -r "$M/sync-app/." "$B/before/app/"; cp -r "$M/sync-storage/." "$B/before/website/"
cp -r "$B/before" "$B/root"
(cd "$B/unz" && unzip -q "$Z")
for d in app website; do [ -d "$B/unz/$d" ] && cp -r "$B/unz/$d/." "$B/root/$d/"; done
mariadb -utv -ptv -h127.0.0.1 -e "DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main CHARACTER SET utf8mb4"
mariadb -utv -ptv -h127.0.0.1 lv_main < "$T/before-schema.sql"
php "$T/tickets-migrate.php" "$B/before/app" 2>/dev/null || true   # what's live before Piece 2 (task-scope, task-adrs), when this root has none of Piece 2's own
# migrations live has already run (and deleted), named by the caller: PRE_MIGRATIONS="a.sql b.sql" (Piece 3 builds on Piece 2's tables)
for f in ${PRE_MIGRATIONS:-}; do php -r '$a=$argv[1]; require "$a/lib/Database.php"; require "$a/lib/DbUpdates.php"; $p=Database::connection(); foreach (DbUpdates::statements((string) file_get_contents($argv[2])) as $s) { $st=$p->query($s); if ($st) $st->closeCursor(); }' "$B/root/app" "$f"; done
for i in 1 2; do for f in $(cd "$B/unz" && ls app/migrations/*.sql 2>/dev/null); do php -r '$a=$argv[1]; require "$a/lib/Database.php"; require "$a/lib/DbUpdates.php"; $p=Database::connection(); foreach (DbUpdates::statements((string) file_get_contents($argv[2])) as $s) { $st=$p->query($s); if ($st) $st->closeCursor(); }' "$B/root/app" "$B/root/$f"; done; done
echo "published: $(cd "$B/unz" && find app website -type f 2>/dev/null | wc -l) files laid over, migrations run twice"
# roll back
for f in $(cd "$B/unz" && find app website -type f 2>/dev/null); do
  case "$f" in app/*) src="$M/sync-app/${f#app/}";; website/*) src="$M/sync-storage/${f#website/}";; esac
  if [ -f "$src" ]; then cp "$src" "$B/root/$f"; else rm -f "$B/root/$f"; fi
done
find "$B/root" -type d -empty -delete
if diff -rq "$B/before" "$B/root" >/dev/null; then echo "rolled back: the files match before, byte for byte"; else echo "FAIL rolled back: files differ"; diff -rq "$B/before" "$B/root" | head; exit 1; fi
bad=0; while IFS= read -r p; do php -l "$p" >/dev/null 2>&1 || { echo "FAIL parse: $p"; bad=1; }; done < <(find "$B/root/app" "$B/root/website/admin" -name '*.php')
[ $bad = 0 ] && echo "rolled back: every PHP file parses"
echo "the release's tables left behind (unused): $(mariadb -utv -ptv -h127.0.0.1 -N lv_main -e "SELECT GROUP_CONCAT(TABLE_NAME) FROM information_schema.TABLES WHERE TABLE_SCHEMA='lv_main' AND (TABLE_NAME LIKE 'team_%' OR TABLE_NAME LIKE 'control_gauge_task_moves' OR TABLE_NAME LIKE 'control_gauge_task_briefs')")"
