#!/bin/bash
# The rendered sweep (the control board's build, step 4; playbook §3: "Press every new button once in a rendered test"):
# every Playwright test of the board and the dispatcher on one bench, each on a fresh lv_main with its seed, at 1280 and
# 390 as each test does.
#   rendered-all.sh <root>   <root> holds app/ and website/ (the mirror with the build over it), its .env at <root>/../..
#                            (run-all.sh writes it). Never live: each test draws its page with PHP and routes its calls.
# Tests print "  ok  <name>" and "FAIL  <name>"; the exit code is non-zero on any FAIL.
set -u
HERE="$(cd "$(dirname "$0")" && pwd)"
TOOLS="$(dirname "$HERE")"
R="$(cd "$1" && pwd)"
ONLY="${2:-}"   # a name pattern: only those tests (rendered-all.sh <root> 'board-views|board-tabs'), each with its full output
SQL="mariadb -utv -ptv -h127.0.0.1"
WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT
cat > "$WORK/ledger.sql" <<'E'
CREATE TABLE IF NOT EXISTS control_ledger (id INT AUTO_INCREMENT PRIMARY KEY, at DATETIME NOT NULL, endpoint VARCHAR(80) NOT NULL,
  method VARCHAR(6), code INT, ms INT, run_id INT NULL, n_sent INT DEFAULT 0, n_received INT DEFAULT 0, n_stored INT DEFAULT 0,
  n_updated INT DEFAULT 0, n_review INT DEFAULT 0, n_refused INT DEFAULT 0, reasons VARCHAR(500) NULL, records INT DEFAULT 0,
  KEY (endpoint, id), KEY (run_id), KEY (at));
CREATE TABLE IF NOT EXISTS control_records (id INT AUTO_INCREMENT PRIMARY KEY, ledger_id INT NOT NULL, at DATETIME NOT NULL,
  kind VARCHAR(20), ref_id INT, what VARCHAR(255), KEY (ledger_id), KEY (kind, ref_id), KEY (at));
E
fresh() { $SQL -e 'DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main'; $SQL < "$TOOLS/release-b-tests/start-schema.sql"; $SQL lv_main < "$WORK/ledger.sql"; }
D="$TOOLS/dispatcher-tests"
# name | setup (run in its folder, after fresh) | the test
TESTS=(
"board-menu|:|node board-menu.cjs $R"
"board-phone|php board-phone-seed.php $R/app|node board-phone.cjs $R"
"board-rendered|php board-rendered-seed.php $R/app|node board-rendered.cjs $R"
"board-tabs|php board-phone-seed.php $R/app|node board-tabs.cjs $R"
"board-views|php board-views-seed.php $R/app|node board-views.cjs $R"
"board-panels|php board-panels-seed.php $R/app|node board-panels.cjs $R"
"board-opening|php board-panels-seed.php $R/app|node board-opening.cjs $R"
"gauge-panel|php board-panels-seed.php $R/app|node gauge-panel.cjs $R"
"work-queue|:|node work-queue.cjs $R"
"task-trip|:|node task-trip.cjs $R"
"google-signin|:|node google-signin.cjs $R"
"board-live|php board-live-seed.php $R/app $TOOLS/../site-audit/results/alarms.json|node board-live.cjs $R"
"lines|php lines.php $R/app|node lines.cjs $R"
"module-cards|php module-cards-page.php $R/app seed|node module-cards.cjs $R"
"one-panel|:|node one-panel.cjs $R/website"
"one-place|php board-phone-seed.php $R/app|node one-place.cjs $R"
"peek-anchor|:|node peek-anchor.cjs $R"
"workflows-page|php workflows-page.php $R|node workflows-page.cjs $R"
"board-phone-header|:|node board-phone-header.cjs $R"
"dispatcher/r2|cd $D && php r2.php $R/app|node $D/r2.cjs $R"
"dispatcher/r3|cd $D && php r3.php $R/app|node $D/r3.cjs $R"
"dispatcher/r4|cd $D && php r4.php $R/app|node $D/r4.cjs $R"
)
printf '%-22s %6s %6s  %s\n' TEST PASS FAIL NOTE
TP=0; TF=0; ALL="$WORK/fails"; : > "$ALL"
for t in "${TESTS[@]}"; do
    IFS='|' read -r name setup test <<<"$t"
    if [ -n "$ONLY" ] && ! [[ "$name" =~ ^($ONLY)$ ]]; then continue; fi
    fresh
    (cd "$HERE" && eval "$setup") > "$WORK/seed" 2>&1
    (cd "$HERE" && eval "timeout 600 $test") > "$WORK/out" 2>&1; rc=$?
    p=$(grep -c '^  ok  ' "$WORK/out"); n=$(grep -c '^FAIL  ' "$WORK/out"); note=""
    [ "$p$n" = "00" ] && { note="did not run (exit $rc): $(tail -1 "$WORK/out" | cut -c1-90)"; n=1; }
    # a test that stopped part way (an exception after some checks) is a failure, never a pass with fewer checks (2026-11-23)
    [ "$rc" != 0 ] && [ "$n" = 0 ] && { note="stopped part way (exit $rc): $(grep -m1 -E 'Error|locator|waiting for' "$WORK/out" | cut -c1-110)"; n=1; }
    grep -q '^FAIL' "$WORK/seed" && note="seed: $(grep -m1 '^FAIL' "$WORK/seed" | cut -c1-80)"
    grep '^FAIL  ' "$WORK/out" | sed "s|^|$name: |" >> "$ALL"
    [ -n "$note" ] && echo "$name: $note" >> "$ALL"
    printf '%-22s %6s %6s  %s\n' "$name" "$p" "$n" "$note"
    [ -n "$ONLY" ] && { tail -15 "$WORK/out" | cut -c1-300; }
    TP=$((TP + p)); TF=$((TF + n))
done
printf '\n%-22s %6s %6s\n' TOTAL "$TP" "$TF"
[ -s "$ALL" ] && { echo; echo 'FAIL lines:'; cut -c1-400 "$ALL"; }
[ "$TF" = 0 ]
