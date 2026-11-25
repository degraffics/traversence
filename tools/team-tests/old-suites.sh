#!/bin/bash
# The Work queue's own rendered suites (work-queue, task-scope, task-adrs) run over Piece 2's build: each copied beside the
# original with its seed also running tickets-migrate.php (live runs a release's migrations first at Publish, 0162), then
# removed. Usage: bash old-suites.sh <root>
R=$(cd "$1" && pwd); D=$(cd "$(dirname "$0")/../control-board-tests" && pwd); M=$(cd "$(dirname "$0")" && pwd)/tickets-migrate.php
for s in work-queue task-scope task-adrs; do
  c="$D/_p2_$s.cjs"
  python3 - "$D/$s.cjs" "$c" "$M" <<'PY'
import sys; src, dst, m = sys.argv[1:4]; s = open(src).read()
s = s.replace('CREATE DATABASE lv_main\\"', 'CREATE DATABASE lv_main CHARACTER SET utf8mb4\\"')
a = "']).toString().trim()"; i = s.index(a)
s = s[:i] + "; php " + m + " ' + root + '/app >/dev/null" + s[i:]
open(dst, 'w').write(s)
PY
  echo "== $s"; node "$c" "$R" 2>&1 | grep -E "^FAIL|passed"; rm -f "$c"
done
