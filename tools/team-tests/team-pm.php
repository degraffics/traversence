<?php
// tools/team-tests/team-pm.php — the Project Manager (role 8) in TeamRunner: its steps, ceilings, reasoning order,
// prompt, charter and the code-only rules (sequenceFrom, idleDays). No database, no outside call, no money (nothing here
// reaches QuickBooksPay or any connector). Run: php tools/team-tests/team-pm.php [repo root]
declare(strict_types=1);

$root = $argv[1] ?? (getenv('TRAV_ROOT') ?: dirname(__DIR__, 2));
require_once rtrim($root, '/') . '/app/lib/TeamRunner.php';

$fail = 0;
function check(string $name, bool $ok): void { global $fail; echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . "\n"; if (!$ok) $fail++; }

// steps and ceilings
$pm = array_keys(array_filter(TeamRunner::STEPS, fn ($s) => $s[0] === 'project-manager'));
check('the Project Manager has gate, sequence and stall', $pm === ['gate', 'sequence', 'stall']);
foreach ($pm as $s) {
    check("$s needs a team.* Access action", str_starts_with(TeamRunner::STEPS[$s][2], 'team.'));
    check("$s has a mode at every ceiling", array_keys(TeamRunner::STEP_AT[$s]) === array_keys(TeamRunner::CEILINGS));
    check("$s costs \$0", (TeamRunner::STEP_COST_MICROS[$s] ?? -1) === 0);
}
check('sequence only proposes, at every ceiling', array_unique(array_values(TeamRunner::STEP_AT['sequence'])) === ['propose']);
check('stall proposes at Planning', TeamRunner::STEP_AT['stall']['planning'] === 'propose');
check('stall acts from Building on', TeamRunner::STEP_AT['stall']['building'] === 'act' && TeamRunner::STEP_AT['stall']['confirmation'] === 'act');
check('every step in STEPS has a STEP_AT row', array_diff(array_keys(TeamRunner::STEPS), array_keys(TeamRunner::STEP_AT)) === []);

// the reasoning order: code, then API, then session (0180's note to §6)
check('stall order is code, api, session', TeamRunner::STEP_AI['stall'] === ['code', 'api', 'session']);
check('code settles: code', TeamRunner::route('stall', true) === 'code');
check('API off until its counter is live', TeamRunner::AGENTS_API_LIVE === false);
check('code can\'t settle, API off: session', TeamRunner::route('stall', false) === 'session');
check('sequence never guesses past code', TeamRunner::route('sequence', false) === 'none');
check('an unlisted step is code only', TeamRunner::route('handoff', false) === 'none');

// the rules the tree's approval covers
$rd = TeamRunner::ruleData();
check('ruleData carries step_ai', isset($rd['step_ai']['stall']));
check('ruleData carries agents_api and stall_days', array_key_exists('agents_api', $rd) && $rd['stall_days'] === 3);
check('ruleData carries the new steps', isset($rd['steps']['sequence'], $rd['steps']['stall']));
check('fingerprint is stable', TeamRunner::fingerprint() === TeamRunner::fingerprint());

// sequence: code settles it, or nothing
check('precedent of a ruling is Priority 1', (TeamRunner::sequenceFrom(null, 'resolution', 7, [])[0] ?? 0) === 1);
check('goal priority carries', (TeamRunner::sequenceFrom(2, 'work', 7, [])[0] ?? 0) === 2);
check('waiting tickets: most urgent', (TeamRunner::sequenceFrom(null, '', 0, [3, 1, 0])[0] ?? 0) === 1);
check('nothing settles: null (no guess)', TeamRunner::sequenceFrom(null, '', 0, []) === null);
check('children without priorities: null', TeamRunner::sequenceFrom(null, '', 0, [0, 0]) === null);
check('a goal without a priority, no children: null', TeamRunner::sequenceFrom(null, 'work', 5, []) === null);

// idle days
$now = strtotime('2026-11-30 13:00:00 UTC');
check('idle 3 days', TeamRunner::idleDays('2026-11-27 12:00:00', $now) === 3);
check('idle under a day', TeamRunner::idleDays('2026-11-30 01:00:00', $now) === 0);
check('no date: 0', TeamRunner::idleDays('', $now) === 0);
check('future date: 0', TeamRunner::idleDays('2026-12-05 00:00:00', $now) === 0);

// the prompt: lean, filled, private-free by construction
$ask = TeamRunner::prompt('stall', ['id' => 42, 'what' => 'Fix the stray Recovered email', 'stage' => 'Building', 'days' => 4, 'priority' => 1, 'lane' => 'IT Manager', 'missing' => '', 'child' => 51, 'adrs' => '0180']);
check('prompt names the ticket', str_contains($ask, 'Task #42'));
check('prompt leaves no {placeholder}', !preg_match('/\{[a-z_]+\}/', $ask));
check('an empty value reads none', str_contains($ask, 'still needs: none'));
check('prompt is lean (< 1200 chars)', mb_strlen($ask) < 1200);
check('an unknown prompt is empty', TeamRunner::prompt('nope', []) === '');

// the charter (0180 §6's four groundings)
$c = TeamRunner::charter('project-manager');
check('charter is role 8 in the project-manager lane', ($c['number'] ?? 0) === 8 && ($c['lane'] ?? '') === 'project-manager');
check('charter works Work tickets only', ($c['tickets'] ?? []) === ['work']);
foreach (['follows', 'job', 'knowledge', 'escalation', 'never'] as $k) check("charter has $k", !empty($c[$k]));
check('charter steps match STEPS', ($c['steps'] ?? []) === $pm);
check('charter shows the stall order', ($c['ai']['stall'] ?? []) === ['code', 'api', 'session']);
check('no charter for an unknown role', TeamRunner::charter('nobody') === []);
check('the PM lane is one of the seven', in_array('project-manager', TeamRoster::LANES, true));

echo $fail === 0 ? "ALL PASS\n" : "$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
