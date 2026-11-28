# Workflow-shape audit: draft

**Where it stands.** The documents say this item has not been built. Nothing in it is live.

- `handoffs/control-board-2026-11-24.md`, "Next", item 4: *"The workflow-shape audit (`research/workflow-shapes-2026-11-23.md`, read-only; live read through the test accounts; report to the session manager)."*
- `handoffs/control-board-2026-11-25.md` moved everything outstanding into the Work queue (task-seed-3.zip, PR #1368, *"merged, live once synced: check its rows on live"*). This item should be one of those tasks.

The title says "a report to Jason first", and the hand-off calls the audit read-only. So the only work left is to read and report. No site behaviour should change until Jason has the report. This draft therefore patches no live file. It adds one read-only tool that gathers the facts for the report, plus a test that proves the tool writes nothing.

`research/workflow-shapes-2026-11-23.md` is **file not shown**. I don't know which "shapes" it defines, so the tool gathers facts and makes no judgments.

## 1. Plan

1. **No change to any live `app/` or `website/` file.** The item is "read-only" and "a report to Jason first" (hand-off 2026-11-24, Next item 4). CLAUDE.md #1334–#1336 says never invent what's set, and a gap is offered as suggestions.
2. **Add `tools/workflow-shape-audit/read.php`.** It reads, per workflow, what the code already declares:
   - name, start-up position, switch, tasks, jobs, endpoints and needs with their sources;
   - the last 24 hours of calls, outcomes and job runs;
   - its "what's wrong now", using ControlBoard's own readers (`calls`, `outcomes`, `jobRuns`, `latest`).
   
   It also lists the endpoints no process claims. This follows 0171 §3: *"Anything no process claims is listed as unclaimed"*, and *"What is already declared is read, not copied."*
3. **Add a test, `tools/control-board-tests/workflow-shapes.php`.** It checks:
   - the read covers every workflow in `Automation::WORKFLOWS`;
   - every workflow has the right shape;
   - there is no email address in the output (0171 §4: *"Ids and counts only"*);
   - the counts in the tables it touches are the same before and after (read-only).
4. **No faq.md entry.** Nothing changes for a visitor. faq.md is shared, and only one release carrying it can wait in the inbox (hand-off 2026-11-20).
5. **The report itself** goes to the session manager, then to Jason. The lane session runs the tool through the test accounts and writes it. Sources: hand-off 2026-11-24, and the item's own title.

## 2. Diffs against the attached live files

**None.** No attached file (`ControlBoard.php`, `ControlAlarms.php`, `ControlGauges.php`, `GaugeTasks.php`) needs a change for a read-only audit.

The two new files are under `tools/`. Both are given in full in §3.

Files I would have looked at but are **not shown**:
- `research/workflow-shapes-2026-11-23.md`
- `ControlRegister.php`
- `Automation.php`
- `SystemStart.php`
- `Preflight.php`
- `live-read.yml`
- `tools/control-board-tests/run-all.sh`
- an existing test's bootstrap

## 3. New files (full contents)

### `tools/workflow-shape-audit/read.php`

The tool sits outside `tools/control-board-tests/` so that `run-all.sh` doesn't run it as a test. That script is not shown; the lane session should confirm how it picks files.

```php
<?php
// tools/workflow-shape-audit/read.php — the workflow-shape audit's read (DRAFT). handoffs/control-board-2026-11-24.md, Next 4:
// "The workflow-shape audit (research/workflow-shapes-2026-11-23.md, read-only; live read through the test accounts; report
// to the session manager)". It reads what the code already declares (decisions/0171 §3: "What is already declared is read,
// not copied"): Automation::WORKFLOWS, SystemStart::ORDER and TASKS, ControlRegister, Preflight::NEEDS; and the last 24 hours
// through ControlBoard's own readers (calls, outcomes, jobRuns, latest). It writes nothing and judges nothing: the report's
// words are the session's, against the research file. Ids, words and counts only (0171 §4): nothing about a person.
// Use: php tools/workflow-shape-audit/read.php <bench root>   (prints JSON)
declare(strict_types=1);

/** The facts per workflow, the endpoints no process claims, and Start's tasks. Never throws for one workflow's failure. */
function tv_workflow_shapes(PDO $pdo, string $app): array
{
    require_once $app . '/lib/Automation.php';
    require_once $app . '/lib/ControlRegister.php';
    require_once $app . '/lib/ControlBoard.php';
    require_once $app . '/lib/ControlAlarms.php';
    require_once $app . '/lib/SystemStart.php';
    require_once $app . '/lib/Preflight.php';

    $order = array_values((array) SystemStart::ORDER);
    $taskWf = [];
    try { $taskWf = (array) ControlRegister::taskWorkflows($pdo); } catch (Throwable $x) {}
    $out = ['read_at' => gmdate('Y-m-d\TH:i:s\Z'), 'workflows' => [], 'unclaimed_endpoints' => [], 'start_tasks' => [], 'errors' => []];

    foreach (Automation::WORKFLOWS as $wf => $d) {
        $wf = (string) $wf;
        $p = ['endpoints' => [], 'tasks' => [], 'jobs' => [], 'needs' => []];
        try { $p = (array) ControlRegister::of($pdo, $wf) + $p; } catch (Throwable $x) { $out['errors'][] = $wf . ': ' . $x->getMessage(); }
        $tasks = array_values(array_map('strval', (array) $p['tasks']));
        $jobs = array_values(array_map('strval', (array) $p['jobs']));
        // the endpoints as ControlBoard::process reads a workflow's (crawl/service and crawl/usage are every run's, not its own)
        $eps = array_values(array_filter(array_map('strval', (array) $p['endpoints']), fn ($e) => !in_array($e, ['crawl/service', 'crawl/usage'], true)));
        $needs = [];
        foreach ((array) $p['needs'] as $nk) {
            $n = Preflight::NEEDS[$nk] ?? null;
            $needs[(string) $nk] = $n
                ? ['name' => (string) $n[1], 'sources' => array_map(fn ($s) => (string) $s[0] . ':' . (string) $s[1], (array) $n[3])]
                : ['name' => 'not in Preflight::NEEDS', 'sources' => []];
        }
        $off = null;
        try { $off = Automation::wfOff($pdo, $wf); } catch (Throwable $x) { $out['errors'][] = $wf . ' switch: ' . $x->getMessage(); }
        $now = ControlBoard::latest($pdo, $eps, $jobs, $tasks);
        $pos = array_search($wf, $order, true);
        // a task this workflow lists that the register files under another workflow: a fact for the report, not a verdict
        $elsewhere = [];
        foreach ($tasks as $t) if (isset($taskWf[$t]) && (string) $taskWf[$t] !== $wf) $elsewhere[$t] = (string) $taskWf[$t];
        $out['workflows'][$wf] = [
            'name' => (string) ($d[0] ?? ''),
            'start_order' => $pos === false ? null : $pos + 1,
            'switch' => $off === null ? 'on' : 'off: ' . (string) $off,
            'tasks' => $tasks, 'jobs' => $jobs, 'endpoints' => $eps, 'needs' => $needs,
            'tasks_filed_elsewhere' => $elsewhere,
            'calls_24h' => ControlBoard::calls($pdo, $eps),
            'outcomes_24h' => ControlBoard::outcomes($pdo, $tasks),
            'job_runs_24h' => array_combine(['good', 'stopped'], ControlBoard::jobRuns($pdo, $jobs)),
            'now' => ['bad' => array_values($now['bad']), 'some' => array_values($now['some'])],
        ];
    }

    // 0171 §3: "Anything no process claims is listed as unclaimed": procOfEndpoint answers 'ep:<endpoint>' for those
    foreach (array_keys((array) ControlRegister::ENDPOINTS) as $ep) {
        if (str_starts_with(ControlAlarms::procOfEndpoint($pdo, (string) $ep), 'ep:')) $out['unclaimed_endpoints'][] = (string) $ep;
    }
    foreach ((array) SystemStart::TASKS as $t => $td) {
        $out['start_tasks'][(string) $t] = ['name' => (string) ($td[0] ?? ''), 'jobs' => array_values((array) ($td[3] ?? [])), 'crawler_tasks' => array_values((array) ($td[4] ?? []))];
    }
    return $out;
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $root = rtrim((string) ($argv[1] ?? ''), '/');
    if ($root === '' || !is_file($root . '/app/lib/Database.php')) { fwrite(STDERR, "Usage: php read.php <bench root with app/lib>\n"); exit(2); }
    require_once $root . '/app/lib/Database.php';
    $pdo = Database::connection();
    echo json_encode(tv_workflow_shapes($pdo, $root . '/app'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
}
```

### `tools/control-board-tests/workflow-shapes.php`

```php
<?php
// tools/control-board-tests/workflow-shapes.php — the workflow-shape audit's read (tools/workflow-shape-audit/read.php):
// it covers every workflow, each in the same shape, holds no email address, and writes nothing (DRAFT).
// Use: php tools/control-board-tests/workflow-shapes.php <bench root>
declare(strict_types=1);

$root = rtrim((string) ($argv[1] ?? ''), '/');
if ($root === '' || !is_file($root . '/app/lib/Database.php')) { echo "FAIL: give the bench root (with app/lib)\n"; exit(1); }
require_once $root . '/app/lib/Database.php';
require_once __DIR__ . '/../workflow-shape-audit/read.php';
$pdo = Database::connection();

$fails = 0;
$ok = function (bool $c, string $what) use (&$fails) { echo ($c ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$c) $fails++; };

// what the read must never change: row counts before and after (null: the table isn't on this bench)
$count = function (string $t) use ($pdo): ?int { try { return (int) $pdo->query('SELECT COUNT(*) FROM ' . $t)->fetchColumn(); } catch (Throwable $x) { return null; } };
$tables = ['control_alarms', 'control_log', 'control_ledger', 'control_records', 'automation_log', 'search_index_state'];
try { $tables[] = Database::t('job_runs'); } catch (Throwable $x) {}
$before = []; foreach ($tables as $t) $before[$t] = $count($t);

$r = tv_workflow_shapes($pdo, $root . '/app');

require_once $root . '/app/lib/Automation.php';
foreach (array_keys(Automation::WORKFLOWS) as $wf) {
    $w = $r['workflows'][(string) $wf] ?? null;
    $ok(is_array($w), (string) $wf . ' is in the read');
    if (!is_array($w)) continue;
    $ok(is_array($w['tasks']) && is_array($w['jobs']) && is_array($w['endpoints']) && is_array($w['needs']), (string) $wf . ' has tasks, jobs, endpoints and needs as lists');
    $ok(!in_array('crawl/service', $w['endpoints'], true) && !in_array('crawl/usage', $w['endpoints'], true), (string) $wf . ' leaves out every run\'s own endpoints, as the board does');
    $ok(isset($w['calls_24h']['calls'], $w['job_runs_24h']['good'], $w['job_runs_24h']['stopped']), (string) $wf . ' has its 24-hour counts');
}
$ok(count($r['workflows']) === count(Automation::WORKFLOWS), 'no workflow added or left out');
$ok(is_array($r['unclaimed_endpoints']), 'the unclaimed endpoints are a list');
$ok(is_array($r['start_tasks']) && $r['start_tasks'] !== [], 'Start\'s tasks are read');

$json = json_encode($r, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$ok(is_string($json), 'the read encodes as JSON');
$ok(is_string($json) && !preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $json), 'no email address in the read (0171 §4: ids and counts only)');

foreach ($tables as $t) $ok($count($t) === $before[$t], 'read-only: ' . $t . ' rows unchanged (' . var_export($before[$t], true) . ')');

echo $fails ? "FAILED: $fails\n" : "ALL PASS\n";
exit($fails ? 1 : 0);
```

## 4. faq.md entries

**None.** The audit is read-only and internal, so a visitor sees nothing new and there is nothing to call "coming." Two more reasons to leave faq.md alone:
- faq.md is shared: only one release carrying it can wait in the inbox (hand-off 2026-11-20).
- faq.md is **file not shown**.

If Jason approves changes after reading the report, their FAQ entries belong to that release.

## 5. Risks, and what the lane session must verify

**Before running anything**
- **Read the research file.** `research/workflow-shapes-2026-11-23.md` is not shown. The report must use its terms and criteria. The tool only gathers facts. Nothing in this draft names a "shape."
- **Check that the Work queue task exists.** task-seed-3 (PR #1368) was to be "live once synced: check its rows on live" (hand-off 2026-11-25). Confirm the audit's task is there and use it, so a second task isn't added.

**Check the tool against code not shown**
- **Signatures inferred from callers.** I took these from how `ControlBoard.php` and `ControlAlarms.php` call them; check each against the live files:
  - `ControlRegister::of()` returns `endpoints`, `tasks`, `jobs`, `needs`;
  - `ControlRegister::ENDPOINTS` and `taskWorkflows()`;
  - `SystemStart::ORDER`, and `TASKS[$t]` as `[name, …, jobs, crawler tasks]`;
  - `Preflight::NEEDS[$k]` as `[group, name, …, sources, …]`;
  - `Database::connection()`.
- **Possible hidden writes.** `Automation::wfOff`, `ControlRegister::of` and `taskWorkflows` may run `CREATE TABLE IF NOT EXISTS` or cache something. Their files are not shown. The test's row counts catch inserts, not DDL. If any of them writes, read that value another way or drop it.
- **Bootstrap.** The test takes the bench root as `argv[1]`, the way `run-all.sh <root>` is used. Its bootstrap may not match the existing tests (none is shown). Align it, and confirm `run-all.sh` won't pick up `tools/workflow-shape-audit/`.

**Running it**
- **Live read.** Run the tool only through `live-read.yml` with the test accounts, then delete the run's log, as the `ir0001` and `w0` choices are handled (hand-off 2026-11-24). That file is not shown, so adding a choice for this audit is the lane session's call.
- **Known noise on the bench.** `run-all.sh` has 7 known live failures (hand-off 2026-11-24). Don't read those as this test's failures.

**Content and scope**
- **Errors in the output.** `calls_24h.last_error.said` carries the ledger's `reasons` text. The email check covers addresses only. Read a sample before it goes into the report.
- **The report goes first; any fix waits.** The report goes to the session manager, then to Jason. Its pop-up opens with "Decision (nothing changes on the site yet)…" (CLAUDE.md #1341). Nothing is zipped as a site release from this draft.
- **Related findings.** The open ledger-counting warnings (`/api/crawl/places`, `/api/crawl/identity`, `/api/crawl/refresh`; hand-off 2026-11-24, open notes) may show up in `now.some`. They are tracked as their own task, so name them in the report but don't fix them here.

None of this has been run or tested. It is a draft for the lane session.