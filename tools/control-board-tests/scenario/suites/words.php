<?php
// Suite "words" (02 §4.9's words suite; §4.8's New column; P9 words from state; ruling 5; R130 the notice's shape): across
// the scenarios, the thread's words at the engine, Thread and Notify level, compared with §4.8 row by row: the title (plain
// names, never a raw id, host or caller), the status (Paused by the system (<what's held>) only when held; Down: the system
// is still trying; Trying again (n of 3)), the email subject "SYSTEM ALARM NOTICE: <title>: <action>", the push title
// "SYSTEM ALARM: <title>" with its body starting with the action, the logbook's trip line, and numbers that agree (BL).
// The board's and the card's own drawing of them (control-console.php, module-cards.php) is "views: pending" in cells.md.
const RAW = '/\b(wf|svc|job|task|ep|wk|site|pf):[a-z0-9_\/]+|\(the (crawler|site), on (Railway|Bluehost)\)/i';
function plain(string $s): bool { return !preg_match(RAW, $s); }

sc('words', 'W-1', '§4.8 rows 1, 24: a warning: a plain title; "Trying again (1 of 3): <answer> at <time>. Next check <time>."', ['g4:1B'], function () {
    $a = alarm(ccIndexDown()); $f = facts($a);
    chk('the title is plain words (no host or caller: R102)', plain($f['title']), $f['title']);
    chk('the status "Trying again (1 of 3)…Next check…"', str_contains($f['status'], 'Trying again (1 of 3)') && str_contains($f['status'], 'Next check'), $f['status']);
    chk('"What it affects" claims no hold on a warning (g4 row 1: "Holds: wf:W2")', !preg_match('/Holds?:|Paused/', json_encode($f['affects'])), $f['affects']);
});
sc('words', 'W-2', '§4.8 rows 2, 26, 85: a trip: "SYSTEM ALARM NOTICE: Common Crawl\'s index: Paused by the system"; Pu "SYSTEM ALARM: Common Crawl\'s index"', ['g4:2E', 'g4:2Pu', 'g4:9cE', 'g4:9cPu'], function () {
    $a = alarm(ccIndexTrip()); $m = msgs()[0] ?? []; $p = pushes()[0] ?? [];
    chk('E subject names the component plainly: "Common Crawl\'s index"', ($m['title'] ?? '') === 'SYSTEM ALARM NOTICE: Common Crawl\'s index: Paused by the system', $m['title'] ?? null);
    chk('Pu title "SYSTEM ALARM: Common Crawl\'s index", its body starting with the action', ($p['title'] ?? '') === 'SYSTEM ALARM: Common Crawl\'s index' && str_starts_with((string) ($p['body'] ?? ''), 'Paused by the system'), $p);
    chk('the status and what the system is doing agree (never "held until an admin resolves it" beside "closes as Recovered")', !str_contains(json_encode(facts($a)) . ($m['body'] ?? ''), 'until an admin resolves'), mb_substr((string) ($m['body'] ?? ''), 0, 300));
});
sc('words', 'W-11', '§4.8 rows 11, 16: our endpoint slow: "/api/crawl/loads is slow"; a task\'s own error: "<task> failed in its own code (run #N)"', ['g4:11B', 'g4:2B'], function () {
    report(['overture_bulk' => ['failed', "TimeoutError('The read operation timed out')", []], 'coverage' => ['done', '']]); $a = alarm('task:failed:overture_bulk');
    chk('the endpoint\'s title, not the task\'s (P2)', ($a['title'] ?? '') === '/api/crawl/loads is slow' || str_starts_with((string) ($a['title'] ?? ''), '/api/crawl/loads'), $a['title'] ?? null);
    fresh(); ownCode(); $b = alarm('task:failed:second_look');
    chk('a task\'s own error: "second_look failed in its own code (run #N)"', (bool) preg_match('/^second_look failed in its own code \(run #\d+\)/', (string) ($b['title'] ?? '')), $b['title'] ?? null);
});
sc('words', 'W-21', '§4.8 rows 21, 22: "/api/crawl/loads keeps failing"; trip "Paused by the system (a trip): 3 tasks wait on it."; E "SYSTEM ALARM NOTICE: /api/crawl/loads: Paused by the system"', ['g4:2E', 'g4:2Pu', 'g4:2P'], function () {
    $a = loads5xxTrip(); $m = msgs()[0] ?? []; $p = pushes()[0] ?? [];
    chk('E subject names the endpoint, never its first workflow (g1 F2: filed under W1)', ($m['title'] ?? '') === 'SYSTEM ALARM NOTICE: /api/crawl/loads: Paused by the system', $m['title'] ?? null);
    chk('Pu body "Paused by the system: 3 tasks wait on it." (the count from the holds)', str_contains((string) ($p['body'] ?? ''), '3 tasks wait on it'), $p['body'] ?? null);
});
sc('words', 'W-27', '§4.8 rows 27, 28 and g4 9b: a SlowDown reported by the worker as a server error reads "asked us to slow down" (P2: the worker\'s kind is a hint)', ['g4:9bB', 'g4:9bP', 'g4:9bE', 'g4:9bPu', 'g2:13'], function () {
    ServiceHealth::fromReport($GLOBALS['pdo'], ['issues' => [['host' => CC_DATA, 'kind' => 'server', 'status' => 503, 'body' => '<Error><Code>SlowDown</Code><Message>Please reduce your request rate.</Message></Error>', 'n' => 1]]]);
    $r = ServiceHealth::open($GLOBALS['pdo'], CC_DATA, 'crawler');
    chk('the engine classifies it a rate wait, not "Its server failed" (L12)', $r && $r['kind'] === 'rate', $r['kind'] ?? null);
});
sc('words', 'W-29', '§4.8 rows 29, 31: "Claude (AI) refused our key" · "Paused by the system: calls with ANTHROPIC_API_KEY wait for a new key."', ['g4:9aB', 'g4:9aP', 'g4:9aE', 'g4:9aPu'], function () {
    $a = issueAlarm(svcFail('api.anthropic.com', 'auth', 'site', ['status' => 401])); $f = facts($a); $m = msgs()[0] ?? [];
    chk('the title plain: no host, no caller', plain($f['title']) && !str_contains($f['title'], 'api.anthropic.com'), $f['title']);
    chk('the status names the key whose calls wait', str_contains($f['status'], 'ANTHROPIC_API_KEY'), $f['status']);
    chk('never "Nothing could be paused" on any surface', !str_contains(json_encode($f) . ($m['body'] ?? ''), 'Nothing could be paused'), mb_substr((string) ($m['body'] ?? ''), 0, 200));
});
sc('words', 'W-47', '§4.8 rows 47, 48: "The crawler is down" · trip "Paused by the system: all gathering waits."', [], function () {
    report(['second_look' => ['failed', 'x'], 'refresh' => ['failed', 'y']], ['status' => 'failed']); checks(['wk:crawler' => red()]); triesOut('run:failed'); $a = alarm('run:failed');
    chk('E subject "SYSTEM ALARM NOTICE: The crawler is down: Paused by the system"', (msgs()[0]['title'] ?? '') === 'SYSTEM ALARM NOTICE: The crawler is down: Paused by the system', msgs()[0]['title'] ?? null);
    chk('the status names all gathering', str_contains(facts($a)['status'], 'All gathering') || str_contains(facts($a)['status'], 'all gathering'), facts($a)['status']);
});
sc('words', 'W-62', '§4.8 rows 62, 63 and g4 row 13: a job\'s plain name, never "job:digest"; never "Paused" with nothing paused', ['g4:13P', 'g4:13E', 'g4:13Pu', 'g4:13B'], function () {
    jobRun('digest_tick', 0, 'boom', 3); watch(); jobRun('digest_tick', 0, 'boom', 1); watch(); $a = alarm('job:digest_tick');
    $t = title($a);
    chk('the notice names the job plainly', $a && plain($t), $t);
    chk('its action isn\'t "Paused by the system" with nothing held', $a && !str_ends_with($t, 'Paused by the system'), $t);
});
sc('words', 'W-69', '§4.8 rows 69, 87: a critical part: "Down: the system is still trying" on B, P, E and Pu', ['g4:4B', 'g4:4P', 'g4:4E', 'g4:4Pu'], function () {
    raiseAs('crit:site:search', 'site:search', 'trip', 'crash', 'Search is failing', '500'); $a = alarm('crit:site:search');
    $m = msgs()[0] ?? []; $p = pushes()[0] ?? [];
    chk('B status', str_starts_with(facts($a)['status'], 'Down: the system is still trying'), facts($a)['status']);
    chk('P/E "SYSTEM ALARM NOTICE: Search: Down: the system is still trying"', ($m['title'] ?? '') === 'SYSTEM ALARM NOTICE: Search: Down: the system is still trying', $m['title'] ?? null);
    chk('Pu "SYSTEM ALARM: Search" / "Down…: the system is still trying"', ($p['title'] ?? '') === 'SYSTEM ALARM: Search' && str_starts_with((string) $p['body'], 'Down'), $p);
});
sc('words', 'W-90', '§4.8 rows 90, 123: "Recovered: <part> was down <n> min, the system fixed it." on B, P and E; Pu replaced silently', ['g4:6B', 'g4:6P', 'g4:6E', 'g4:6Pu'], function () {
    raiseAs('crit:site:search', 'site:search', 'trip', 'crash', 'Search is failing', '500'); ControlAlarms::clear($GLOBALS['pdo'], 'crit:site:search', 'Its test search answered.');
    $c = last('crit:site:search'); $r = array_values(array_filter(msgs(), fn ($m) => $m['kind'] === 'alarm.recovered'))[0] ?? [];
    chk('B: closed "Recovered: Search was down <n> min, the system fixed it."', (bool) preg_match('/^Recovered: Search was down \d+ min, the system fixed it/', (string) $c['cleared_how']), $c['cleared_how']);
    chk('P/E: "SYSTEM ALARM NOTICE: Search: Recovered"', ($r['title'] ?? '') === 'SYSTEM ALARM NOTICE: Search: Recovered', $r['title'] ?? null);
    chk('Pu: no new push', count(pushes()) === 1, count(pushes()));
});
sc('words', 'W-93', '§4.8 rows 5, 10, 93: a person\'s close: "…: Fixed by <name>" on P and E', ['g4:7P', 'g4:7E', 'g4:7B', 'g4:7Pu'], function () {
    $a = loads5xxTrip(); checks(['ep:crawl/loads' => pass()]); resolveA($a);
    $r = array_values(array_filter(msgs(), fn ($m) => $m['kind'] === 'alarm.recovered'))[0] ?? [];
    chk('P/E subject ends "Fixed by Jason"', str_ends_with((string) ($r['title'] ?? ''), ': Fixed by Jason'), $r['title'] ?? null);
    chk('B: its close names who and the check\'s proof', str_contains((string) last('ep:crawl/loads:5xx')['cleared_how'], 'Jason') && str_contains((string) last('ep:crawl/loads:5xx')['cleared_how'], 'Its check passed'), last('ep:crawl/loads:5xx')['cleared_how']);
});
sc('words', 'W-16', 'g4 row 16: the logbook keeps the moment it tripped (Warning → Trip → Cleared)', ['g4:16'], function () {
    ownCode(); ownCode(); report(['second_look' => ['done', ''], 'coverage' => ['done', '']]);
    $lines = $GLOBALS['pdo']->query("SELECT what FROM control_log WHERE proc = 'wf:W2' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    chk('a Trip line between the Warning and the Clear', (bool) array_filter($lines, fn ($l) => str_starts_with((string) $l, 'Trip')), $lines);
});
sc('words', 'W-BL', 'BL: a number shown is computed from the same readings everywhere (the try count in the status and on the row)', [], function () {
    ledger('crawl/verify', 500); checks(['ep:crawl/verify' => red()]); due('ep:crawl/verify:5xx', 5); tick(); $a = alarm('ep:crawl/verify:5xx');
    chk('status "(1 of 3" equals try_n', str_contains(facts($a)['status'], '(' . (int) $a['try_n'] . ' of 3'), [facts($a)['status'], $a['try_n']]);
});
sc('words', 'W-12', 'g4 row 12 and §4.8 row 39: a cap\'s title says its reading, not the band', ['g4:12B'], function () {
    notHere('the cap title is written by Operations (L605) from crawl_costs and the caps, outside the engine; views: pending. Listed for the session (P9)');
});
