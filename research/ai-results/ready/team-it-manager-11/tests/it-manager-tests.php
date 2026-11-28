<?php
// tools/team-tests/it-manager-tests.php — the IT Manager's agent (decisions/0180 row 11). Plain PHP, no database, no network, no money.
// Run: php tools/team-tests/it-manager-tests.php   (TRV_ROOT=<repo root> if it isn't two folders up)
declare(strict_types=1);

$root = getenv('TRV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/TeamRunner.php';
require_once $root . '/app/lib/ItManager.php';

$fails = 0;
function t(string $name, bool $ok): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . '  ' . $name . "\n"; if (!$ok) $fails++; }
function hasCheck(array $p, string $who): bool { foreach ($p['checks'] as $c) if (str_starts_with((string) $c[1], $who)) return true; return false; }

// the step: the IT Manager's, $0, propose only, in the tree's rules
t('triage step belongs to it-manager', (TeamRunner::STEPS['triage'][0] ?? '') === 'it-manager');
t('triage needs team.triage, manager in eng by default', (TeamRunner::STEPS['triage'][2] ?? '') === 'team.triage' && (TeamRunner::STEPS['triage'][3] ?? []) === ['min' => 'manager', 'depts' => ['eng']]);
t('triage never acts, at any ceiling', array_unique(array_values(TeamRunner::STEP_AT['triage'] ?? ['act'])) === ['propose'] && count(TeamRunner::STEP_AT['triage']) === count(TeamRunner::CEILINGS));
t('triage costs $0', (TeamRunner::STEP_COST_MICROS['triage'] ?? -1) === 0);
t('triage is in the decision tree\'s rule data (the fingerprint covers it)', isset(TeamRunner::ruleData()['steps']['triage']));
t('the other four steps are unchanged', TeamRunner::STEPS['gate'][0] === 'project-manager' && TeamRunner::STEPS['handoff'][0] === 'system-architect' && TeamRunner::STEPS['brief'][0] === 'translator' && TeamRunner::STEPS['audit'][0] === 'ops-reconciliation');

// the lane (live TeamRoster, unchanged)
t('lane: Releases is the IT Manager\'s', TeamRoster::laneOf(['lib:Releases']) === 'it-manager');
t('lane: /admin/config is the IT Manager\'s', TeamRoster::laneOf(['path:/admin/config/switches']) === 'it-manager');
t('lane: billing_tick is Accounting\'s, not IT\'s', TeamRoster::laneOf(['job:billing_tick']) === 'accounting-manager');

// AI access order: code, then API, then session
t('AI order is code, api, session', ItManager::AI_ORDER === ['code', 'api', 'session']);
t('settled goes to code', ItManager::aiRoute('settled')[0] === 'code');
t('bounded goes to session while the agents\' API counter isn\'t live', ItManager::agentsApiLive() === false && ItManager::aiRoute('bounded')[0] === 'session');
t('reasoning goes to session', ItManager::aiRoute('reasoning')[0] === 'session');
t('nations is never the API rung', ItManager::aiRoute('bounded', 'nations')[0] !== 'api');

// the plan (pure)
$p = ItManager::plan(['lib:DbUpdates'], 'fix the db updates log', []);
t('data territory: Tier 5 until R3, Schema & Migration Guards check, Tier 4 step', $p['tier'] === 5 && $p['territory'] === 'data' && hasCheck($p, 'Schema & Migration Guards (24)') && hasCheck($p, 'Tier 4 review'));
t('data territory attaches 0180 and 0165', in_array('0180', $p['adrs'], true) && in_array('0165', $p['adrs'], true));
$p = ItManager::plan(['lib:Landmarks'], 'landmark photo job', []);
t('nations: Privacy & 0066 Guards check and 0066 attached', $p['territory'] === 'nations' && hasCheck($p, 'Privacy & 0066 Guards (25)') && in_array('0066', $p['adrs'], true));
$p = ItManager::plan(['job:crawl_tick'], 'the crawler batch stalls', []);
t('crawler: no territory, Tier 2, IT Manager verifies the batch', $p['territory'] === '' && $p['tier'] === 2 && hasCheck($p, 'IT Manager (11)'));
$p = ItManager::plan([], 'typo in a label on Switches', []);
t('a typo: Tier 1, settled in code, staging check only', $p['tier'] === 1 && $p['route'][0] === 'code' && count($p['checks']) === 1);
$p = ItManager::plan(['lib:Billing'], 'billing code', []);
t('money: hand-off note to Accounting, hard money line', $p['territory'] === 'money' && (bool) array_filter($p['notes'], fn ($n) => str_contains($n, 'Accounting Manager')));
$p = ItManager::plan(['lib:Scheduler'], 'scheduler', ['website/index.php']);
t('fence: a public page is noted', (bool) array_filter($p['notes'], fn ($n) => str_contains($n, 'waits for the Founder')));

// the packet: lean, and no ticket words on nations
$t = ['id' => 7, 'what' => 'Sacred-site-name near a town'];
$p = ItManager::plan(['lib:HeldNames'], '', []);
$pk = ItManager::packet($t, ['lib:HeldNames'], $p);
t('nations packet leaves the ticket\'s words out', !str_contains($pk, 'Sacred-site-name') && str_contains($pk, 'Prior Ruling Check'));
t('packet is lean (≤ 1500 characters)', mb_strlen($pk) <= 1500);

// the charter
t('charter has its four groundings (0180 §6)', isset(ItManager::CHARTER['adrs'], ItManager::CHARTER['job'], ItManager::CHARTER['knowledge'], ItManager::CHARTER['escalation']));
t('charter never answers a Founder ruling', in_array('Answers a Founder ruling', ItManager::CHARTER['never'], true));

echo $fails === 0 ? "ALL PASS\n" : $fails . " FAIL\n";
exit($fails === 0 ? 0 : 1);
