#!/bin/bash
# Runs the control board's and the dispatcher's PHP tests on a test bench (local MariaDB, PHP CLI), never live.
#   run-all.sh [<root>]   <root> holds app/ and website/ (the mirror with the build over it); its .env sits four folders
#                         above app/lib, i.e. <root>/../.. . Without <root>, bench.sh builds one and prints it.
# Each test gets a fresh lv_main loaded as its "// Run:" line says (start-schema.sql, r2a-schema.sql, an empty database,
# the release 2a ledger), then `php <test> <root>/app` (or <root>, or <root>/website, as its Run line names).
# A PHP server on 127.0.0.1:8765 serves <root>/website for the endpoint tests; APP_URL points there, so no test reaches
# the live site. The .cjs files are Playwright rendered tests and aren't run here.
# Tests print "  ok  <name>" and "FAIL  <name>"; the exit code is non-zero on any FAIL not listed in KNOWN below.
set -u
HERE="$(cd "$(dirname "$0")" && pwd)"
TOOLS="$(dirname "$HERE")"
SCRATCH=/tmp/claude-0/-home-user-traversence/5ce0796b-d233-5642-bf86-81be058dd358/scratchpad
R="${1:-$(bash "$SCRATCH/bench.sh" | tail -1)}"
R="$(cd "$R" && pwd)"
ENV="$(cd "$R/../.." && pwd)/.env"
SQL="mariadb -utv -ptv -h127.0.0.1"
WORK="$(mktemp -d)"; trap 'kill $SRV 2>/dev/null; rm -rf "$WORK"' EXIT

# Known failures, one pattern a line: r1's endpoint register sees the bench's own zz_test endpoint (as on live);
# register.php's two assertions older than live code outside this build: operations-console.php's "Its key" now
# carries an id (<dt id="svc-…-key">), and the generated app/data/decisions-index.php quotes config/services.
KNOWN='zz_test.php
kept from the register: Used by, Its key
no page links to /admin/config/services'

# The .env the app reads (Database.php: DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_HOST). No admin, listings or users
# realm: with DB_ADMIN_DATABASE set, AdminTables would make tables in it while the tests read lv_main.
cat > "$ENV" <<E
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lv_main
DB_USERNAME=tv
DB_PASSWORD=tv
CRAWLER_API_TOKEN=testtoken
VIDEO_WORKER_TOKEN=vtoken
APP_URL=http://127.0.0.1:8765
E

# The test-only worker endpoint r2a calls (never shipped).
cp "$HERE/zz_test.php" "$R/website/api/crawl/zz_test.php"

# Tables whose migrations ran on the server and were deleted after: made again from the code that writes them.
# Release 2a's ledger (2026-11-18_control_ledger.sql), from ControlLedger::finish's columns:
LEDGER="$WORK/ledger.sql"
cat > "$LEDGER" <<'E'
CREATE TABLE IF NOT EXISTS control_ledger (id INT AUTO_INCREMENT PRIMARY KEY, at DATETIME NOT NULL, endpoint VARCHAR(80) NOT NULL,
  method VARCHAR(6), code INT, ms INT, run_id INT NULL, n_sent INT DEFAULT 0, n_received INT DEFAULT 0, n_stored INT DEFAULT 0,
  n_updated INT DEFAULT 0, n_review INT DEFAULT 0, n_refused INT DEFAULT 0, reasons VARCHAR(500) NULL, records INT DEFAULT 0,
  KEY (endpoint, id), KEY (run_id), KEY (at));
CREATE TABLE IF NOT EXISTS control_records (id INT AUTO_INCREMENT PRIMARY KEY, ledger_id INT NOT NULL, at DATETIME NOT NULL,
  kind VARCHAR(20), ref_id INT, what VARCHAR(255), KEY (ledger_id), KEY (kind, ref_id), KEY (at));
E
# 0173 release 1's 2026-11-19_service_stages.sql (register.php reads it from app/migrations and runs it twice), from
# ServiceStages::log's columns; Overpass's retirement recorded once. Put in the bench root only, for register.php.
STAGES_SQL="$R/app/migrations/2026-11-19_service_stages.sql"
cat > "$WORK/stages.sql" <<'E'
CREATE TABLE IF NOT EXISTS service_stages (id INT AUTO_INCREMENT PRIMARY KEY, service VARCHAR(40) NOT NULL, stage VARCHAR(12) NOT NULL,
  why VARCHAR(255) NULL, replaced_by VARCHAR(255) NULL, by_uid INT NULL, by_name VARCHAR(80) NULL, at DATETIME NOT NULL, KEY (service, id));
INSERT INTO service_stages (service, stage, why, replaced_by, by_name, at)
  SELECT 'overpass', 'retired', 'Its community asked that its public servers not be used as a back end for apps and websites (release #204).',
         'our own copy from Geofabrik', 'Jason', '2026-11-19 00:00:00' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM service_stages WHERE service = 'overpass' AND stage = 'retired')
E

(cd "$R/website" && exec php -S 127.0.0.1:8765 >"$WORK/server.log" 2>&1) & SRV=$!
sleep 1

fresh() {   # $1: start | r2a | empty
    $SQL -e 'DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main'
    case "$1" in
        start) $SQL < "$TOOLS/release-b-tests/start-schema.sql" ;;
        r2a)   $SQL < "$HERE/r2a-schema.sql" ;;
    esac
}

printf '%-34s %6s %6s  %s\n' TEST PASS FAIL NOTE
TP=0; TF=0; BAD=0; ALLFAIL="$WORK/fails"; : > "$ALLFAIL"
for f in "$HERE"/*.php "$TOOLS"/dispatcher-tests/*.php; do
    head="$(sed -n '1,14p' "$f")"
    grep -q '^// .*Run:' <<<"$head" || continue                  # fixtures (pages, seeds, zz_test) have no Run line
    name="$(basename "$(dirname "$f")")/$(basename "$f")"
    if   grep -q 'r2a-schema.sql' <<<"$head"; then schema=r2a
    elif grep -q 'start-schema.sql' <<<"$head"; then schema=start
    else schema=empty; fi
    fresh $schema
    grep -q "ledger migration\|control_ledger" <<<"$head" && $SQL lv_main < "$LEDGER"
    arg="$R/app"
    grep -q "php $(basename "$f") <root>/website" <<<"$head" && arg="$R/website"
    grep -Eq "php $(basename "$f") <root>( |$)" <<<"$head" && arg="$R"
    out="$WORK/out"
    [ "$(basename "$f")" = register.php ] && mkdir -p "$(dirname "$STAGES_SQL")" && cp "$WORK/stages.sql" "$STAGES_SQL"
    (cd "$(dirname "$f")" && timeout 300 php "$f" "$arg") > "$out" 2>&1; rc=$?
    rm -f "$STAGES_SQL"
    p=$(grep -c '^  ok  ' "$out"); n=$(grep -c '^FAIL  ' "$out")
    note=""
    [ "$p$n" = "00" ] && { note="did not run (exit $rc): $(grep -v '^\s*$' "$out" | tail -1 | cut -c1-90)"; BAD=1; }
    printf '%-34s %6d %6d  %s\n' "$name" "$p" "$n" "$note"
    grep '^FAIL  ' "$out" | sed "s|^|$name: |" >> "$ALLFAIL"
    TP=$((TP + p)); TF=$((TF + n))
done
echo
printf '%-34s %6d %6d\n' TOTAL "$TP" "$TF"
if [ -s "$ALLFAIL" ]; then echo; echo 'FAIL lines:'; cat "$ALLFAIL"; fi
grep -vF -- "$KNOWN" "$ALLFAIL" | grep -q . && BAD=1
exit $BAD
