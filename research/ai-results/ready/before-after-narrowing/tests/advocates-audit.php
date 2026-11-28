<?php
// tests/advocates-audit.php (tools/piece3-tests/ style): the advocates' own audit's parts that need no database: its result
// file read field by field, its once-a-day ask, its keys, and its Scheduler::JOBS entry. TV_APP: the app folder.
declare(strict_types=1);

$app = getenv('TV_APP') ?: dirname(__DIR__, 2) . '/app';
require_once $app . '/lib/Database.php';
require_once $app . '/lib/Scheduler.php';
require_once $app . '/lib/AdvocatesAudit.php';

$fails = 0;
function ok(string $name, bool $cond): void { global $fails; echo ($cond ? 'PASS' : 'FAIL') . ': ' . $name . "\n"; if (!$cond) $fails++; }

$run = 'https://github.com/degraffics/traversence/actions/runs/123';
$file = json_encode(['run' => $run, 'at' => '2026-11-28T04:30:00Z', 'seconds' => 300, 'views' => 40, 'findings' => [
    ['who' => 'guest-visitor', 'page' => '/faq.php', 'size' => 'phone', 'rule' => 'layout', 'what' => 'Sideways scroll at 390', 'lane' => 'editorial'],
    ['who' => 'member', 'page' => '/user/x.php', 'size' => 'desk', 'rule' => 'friction', 'what' => 'check+member@traversence.com saw it'],
    ['who' => 'BAD', 'page' => '/x', 'rule' => 'layout'],
    ['who' => 'member', 'page' => 'http://evil', 'rule' => 'layout'],
    ['who' => 'member', 'page' => '/x', 'rule' => 'publish'],
]]);
$p = AdvocatesAudit::parse($file);
ok('a good file reads', is_array($p) && $p['run'] === $run && $p['seconds'] === 300.0);
ok('only valid findings kept', is_array($p) && count($p['findings']) === 2);
ok('an address is never kept', is_array($p) && !str_contains($p['findings'][1]['what'], '@'));
ok('a rule outside its list is dropped', is_array($p) && !in_array('publish', array_column($p['findings'], 'rule'), true));
ok('a run not from our repository is refused', AdvocatesAudit::parse(json_encode(['run' => 'https://github.com/other/repo/actions/runs/1'])) === null);
ok('nothing there: null', AdvocatesAudit::parse(null) === null && AdvocatesAudit::parse('not json') === null);

ok('not due before its hour', !AdvocatesAudit::due(AdvocatesAudit::HOUR_UTC - 1, '2026-11-27', '2026-11-28'));
ok('due at its hour', AdvocatesAudit::due(AdvocatesAudit::HOUR_UTC, '2026-11-27', '2026-11-28'));
ok('once a day', !AdvocatesAudit::due(23, '2026-11-28', '2026-11-28'));

$f = $p['findings'][0];
ok('a key is stable', AdvocatesAudit::key($f) === AdvocatesAudit::key($f));
ok('a key fits its column', strlen(AdvocatesAudit::key($f)) === 44);
ok('another size is another finding', AdvocatesAudit::key($f) !== AdvocatesAudit::key(['size' => 'desk'] + $f));

ok('JOBS has advocates_tick', isset(Scheduler::JOBS['advocates_tick']));
ok('its preset exists', isset(Scheduler::PRESETS[Scheduler::JOBS['advocates_tick'][2]]));
ok('its late is at least its schedule', (int) Scheduler::JOBS['advocates_tick'][3] >= 60);
ok('it sets itself up (AUTO)', in_array('advocates_tick', Scheduler::AUTO, true));
ok('never copied to staging', in_array('advocates_tick', Scheduler::NOT_ON_STAGING, true));
ok('its script is in the release', is_file($app . '/scripts/advocates_tick.php'));

echo $fails === 0 ? "ALL PASS\n" : $fails . " FAILED\n";
exit($fails === 0 ? 0 : 1);
