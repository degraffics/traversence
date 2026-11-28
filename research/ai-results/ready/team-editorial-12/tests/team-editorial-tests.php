<?php
// tests/team-editorial-tests.php — role 12, the Editorial Director (tools/team-tests/ style). Plain PHP; it prints PASS or
// FAIL for each check and exits 1 on any FAIL. No database, no network, and no money moves: it exercises only pure
// functions and constants. The DB paths (applies, proposal, accept) are left to staging.
// Run: php tests/team-editorial-tests.php   (it finds app/lib in ../files/app/lib, ../app/lib, or $TRV_LIB)
declare(strict_types=1);

$dirs = array_filter([getenv('TRV_LIB') ?: '', __DIR__ . '/../files/app/lib', __DIR__ . '/../app/lib', __DIR__ . '/../../app/lib']);
$lib = ''; foreach ($dirs as $d) if (is_file($d . '/EditorialDirector.php')) { $lib = $d; break; }
if ($lib === '') { echo "FAIL: can't find app/lib/EditorialDirector.php\n"; exit(1); }
// TeamRunner and its neighbours load the live libraries; this release ships only TeamRunner and EditorialDirector, so the
// others come from the live tree beside it when the release folder has none.
set_include_path($lib . PATH_SEPARATOR . get_include_path());
try { require_once $lib . '/EditorialDirector.php'; if (!class_exists('TeamRunner')) require_once $lib . '/TeamRunner.php'; }
catch (Throwable $x) { echo 'FAIL: loading: ' . $x->getMessage() . "\n"; exit(1); }

$fails = 0;
function check(string $name, bool $ok): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . "\n"; if (!$ok) $fails++; }

// the step, wired the way the other four are
check('STEPS has editorial for editorial-director', (TeamRunner::STEPS['editorial'][0] ?? '') === 'editorial-director');
check('its Access action is team.editorial', (TeamRunner::STEPS['editorial'][2] ?? '') === EditorialDirector::ACTION);
check('its default rule matches EditorialDirector', (TeamRunner::STEPS['editorial'][3] ?? null) === EditorialDirector::ACTION_DEFAULT);
check('it only proposes, at every ceiling', array_values(array_unique(TeamRunner::STEP_AT['editorial'] ?? [])) === ['propose'] && count(TeamRunner::STEP_AT['editorial']) === count(TeamRunner::CEILINGS));
check('it costs $0', (TeamRunner::STEP_COST_MICROS['editorial'] ?? -1) === 0);
$ok = true; foreach (array_keys(TeamRunner::STEPS) as $s) if (!isset(TeamRunner::STEP_AT[$s], TeamRunner::STEP_COST_MICROS[$s])) $ok = false;
check('every step has its ceilings and its cost', $ok);
check('the rule data carries the new step (the fingerprint changes)', isset(TeamRunner::ruleData()['steps']['editorial']));
check('the step name fits team_runs.step (20)', strlen(EditorialDirector::STEP) <= 20);

// the lane: its own pages route to it
check('/faq.php is the Editorial lane', TeamRoster::laneOf(['path:/faq.php']) === 'editorial-director');
check('an order page is not', TeamRoster::laneOf(['path:/market/cart.php']) !== 'editorial-director');

// the code checks
$pub = ['id' => 1, 'what' => 'Clearer label on the guide page', 'why' => 'Visitors miss it', 'goal' => '', 'in_scope' => '', 'done_when' => '', 'build_brief' => ''];
$c = EditorialDirector::checks($pub, ['path:/guide/view.php']);
check('a public page change gets the FAQ step', isset($c['faq']));
check('... the 390px step', isset($c['phone']));
check('... the one-clear-next-action step', isset($c['simplicity']));
check('... the words step (a label)', isset($c['voice']));
check('... the person\'s-words step (a guide)', isset($c['person']));
check('... no territory step', !isset($c['territory']));
$done = $pub; $done['done_when'] = 'The FAQ entry is up; checked at 390px; one clear next action; read against brand.md; nothing a person entered changes';
$c2 = EditorialDirector::checks($done, ['path:/guide/view.php']);
check('Done when that covers them proposes none of them again', !isset($c2['faq']) && !isset($c2['phone']) && !isset($c2['simplicity']) && !isset($c2['voice']));
$admin = ['id' => 2, 'what' => 'Tidy the cron runner', 'why' => 'Noise', 'goal' => '', 'in_scope' => '', 'done_when' => '', 'build_brief' => ''];
check('a backend-only change with no screen words gets no checks', EditorialDirector::checks($admin, ['path:/admin/config/tasks']) === []);
$money = EditorialDirector::checks($pub, ['path:/market/cart.php']);
check('a money page gets its Tier 4 step', isset($money['territory']) && str_contains($money['territory'][0], 'Money'));
$land = EditorialDirector::checks(['what' => 'Landmark heading wording'] + $pub, ['path:/place/landmark.php']);
check('a landmark page gets the held-names step', isset($land['held']));
check('... and its territory (nations)', isset($land['territory']) && str_contains($land['territory'][0], '0066'));

// AI access order: code, then the API, then a session
check('the API is closed until its counter is live', EditorialDirector::apiReady()[0] === false);
check('no words to write: code settles it', EditorialDirector::route(false)[0] === 'code');
check('words to write: a session (the API closed)', EditorialDirector::route(true)[0] === 'session');
check('needsWords on a copy ticket', EditorialDirector::needsWords($pub));
check('needsWords not twice', !EditorialDirector::needsWords($pub, [['do_what' => 'Session ask (Editorial Director, 12): …']]));
check('the access order is code, api, session', array_keys(EditorialDirector::ACCESS) === ['code', 'api', 'session']);

// the prompt: lean, cited, capped
$ask = EditorialDirector::ask(['id' => 7] + $pub, ['path:/guide/view.php'], '', ['decisions/0057']);
check('the ask names the ticket', str_contains($ask, 'task #7'));
check('the ask cites its ADR', str_contains($ask, 'decisions/0057'));
check('the ask asks for a citation back', str_contains($ask, 'ADR section'));
check('the ask keeps 0066 and people\'s words', str_contains($ask, '0066') && str_contains($ask, 'person'));
$long = EditorialDirector::ask(['id' => 8, 'what' => str_repeat('x', 3000)] + $pub, array_fill(0, 50, 'path:/guide/a-long-page.php'), 'nations', array_fill(0, 40, 'decisions/0000'));
check('the ask is capped at ASK_MAX', mb_strlen($long) <= EditorialDirector::ASK_MAX);

// the charter: 0180 §6's four things
check('the charter has its four parts', array_keys(EditorialDirector::CHARTER) === ['adrs', 'job', 'knowledge', 'escalation']);
check('its ticket types: Work, at Planning and Building only', EditorialDirector::TICKETS === ['work' => ['planning', 'building']]);

// its seen mark: so the same proposal is never made twice
check('seen carries the mark', str_contains(EditorialDirector::seen(['hash' => 'abc123def456']), '[ed:abc123def456]'));
check('seen fits team_runs.seen (400)', mb_strlen(EditorialDirector::seen(['hash' => str_repeat('a', 12)])) <= 400);

echo $fails ? "\n" . $fails . " FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
