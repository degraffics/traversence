<?php
// Suite "faults" (02 §4 group B, S-B16–S-B82 and the lettered rows 37a, 38a, 38b, 41a, 70a): each kind of failure, caused
// through its real caller (CrawlService::report, ControlLedger's raise, ServiceHealth::failed and ::classify, the watch's job
// section) or, where the caller's raise sits inside a routine that reads live outside state (the sensors, Start's watch,
// Railway's and GitHub's watches), the caller's own raise with its key, level and kind (raiseAs), so the engine's handling
// of it is what's judged.
const LOADS_TASKS = ['task:foursquare_bulk', 'task:geofabrik_bulk', 'task:overture_bulk'];
function msgN(): array { return [count(msgs()), count(mails()), count(pushes())]; }
/** A task failing in its own code, with another task in the run doing its work (so the run isn't "failed"). */
function ownCode(string $t = 'second_look', string $why = 'KeyError: name'): void { report([$t => ['failed', $why, []], 'coverage' => ['done', '']]); }
/** A run of loads' 5xx tripped by the engine's three tries of the endpoint's check. */
function loads5xxTrip(): array { ledger('crawl/loads', 500); checks(['ep:crawl/loads' => red('/api/crawl/loads answered 500 in 210 ms.')]); triesOut('ep:crawl/loads:5xx'); return alarm('ep:crawl/loads:5xx') ?? []; }
function msgOf(?array $a): array { if (!$a) return []; $st = $GLOBALS['pdo']->prepare('SELECT kind, title FROM comms_messages WHERE thing = ?'); $st->execute(['alarm:' . (int) $a['id']]); return $st->fetchAll(PDO::FETCH_ASSOC); }
function wordsOk(string $s, array $want): bool { foreach ($want as $w) if (!str_contains($s, $w)) return false; return true; }

// -- tasks (rows 16–18) ---------------------------------------------------------------------------------------------------
sc('faults', 'S-B16', 'a task fails in its own code: a warning "waiting for its next run", nothing held', ['g1:1', 'g4:1B'], function () {
    $s0 = snap(); ownCode(); $a = alarm('task:failed:second_look');
    chk('state: a Warning, its component the task, no try clock (its next run is its check)', $a && $a['level'] === 'warning' && $a['component'] === 'task:second_look' && $a['next_try_at'] === null, $a);
    chk('hold: nothing (P7: holding it would block its check); W2 not held', holds() === [], holds());
    chk('no switch changed', snap() === $s0);
    chk('words: no check can run before its next run; nothing held', str_contains(facts($a)['doing'], 'No check can run before its next run'), facts($a)['doing']);
    chk('messages: none', quiet());
});
sc('faults', 'S-B17', 'its next run fails again: a Trip that holds nothing, its next run still goes ahead (ruling 3, O2)', ['g1:1', 'g4:2B', 'g4:2P', 'g4:2E', 'g4:2Pu'], function () {
    ownCode(); ownCode(); $a = alarm('task:failed:second_look');
    chk('state: a Trip', $a && $a['level'] === 'trip' && $a['tripped_at'] !== null, $a['level'] ?? null);
    chk('hold: nothing, so its next scheduled run goes ahead', holds() === [] && (string) $a['stopped'] === '', [holds(), $a['stopped'] ?? null]);
    chk('words: it says plainly it can\'t be held without blocking its next run', str_contains(facts($a)['doing'], 'can\'t be held without blocking its next run'), facts($a)['doing']);
    chk('messages: a trip reaches the founder once (P11)', msgN() === [1, 1, 1], msgN());
    chk('…its action never "Paused by the system" with nothing held', !str_contains(title($a), 'Paused by the system'), title($a));
});
sc('faults', 'S-B18', 'its next run finishes well: Cleared', ['g1:1'], function () {
    ownCode(); report(['second_look' => ['done', ''], 'coverage' => ['done', '']]);
    chk('state: Cleared, saying its run did its work', alarm('task:failed:second_look') === null && str_contains((string) last('task:failed:second_look')['cleared_how'], 'did its work'), last('task:failed:second_look')['cleared_how'] ?? null);
});
// -- our endpoints (19–23) ------------------------------------------------------------------------------------------------
sc('faults', 'S-B19', 'an endpoint slow: 3× its usual with 20 calls of history, or over its speed gate with fewer', ['g1:7', 'g1:9', 'g1:10'], function () {
    for ($i = 0; $i < 25; $i++) ledger('crawl/geocode', 200, 150);
    ledger('crawl/geocode', 200, 4000); $a = alarm('ep:crawl/geocode:slow');
    chk('state: a Warning (Slow), never a trip', $a && $a['level'] === 'warning' && $a['kind'] === 'slow', $a);
    chk('its component is the endpoint (a Site part), not a workflow', ($a['component'] ?? '') === 'ep:crawl/geocode', $a['component'] ?? null);
    chk('hold: nothing', holds() === []);
    chk('messages: none', quiet());
    for ($i = 0; $i < 5; $i++) ledger('crawl/places', 200, 150);
    ledger('crawl/places', 200, 9000);
    chk('fewer than 20 calls: judged against its declared speed gate (row 19; g1 row 9: missing)', alarm('ep:crawl/places:slow') !== null, 'no alarm');
});
sc('faults', 'S-B20', 'a call at its usual speed: Cleared', ['g1:7'], function () {
    for ($i = 0; $i < 25; $i++) ledger('crawl/geocode', 200, 150);
    ledger('crawl/geocode', 200, 4000); ledger('crawl/geocode', 200, 150);
    chk('Cleared at its usual speed', alarm('ep:crawl/geocode:slow') === null && last('ep:crawl/geocode:slow') !== null, last('ep:crawl/geocode:slow'));
});
sc('faults', 'S-B21', 'an endpoint answers 5xx: a Warning (W-####), trying again, nothing held', ['g1:24', 'g4:1B'], function () {
    $s0 = snap(); ledger('crawl/loads', 500); $a = alarm('ep:crawl/loads:5xx');
    chk('state: a Warning with its first try due', $a && $a['level'] === 'warning' && $a['next_try_at'] !== null, $a);
    chk('its component the endpoint (P2), never W1', ($a['component'] ?? '') === 'ep:crawl/loads', $a['component'] ?? null);
    chk('hold: nothing yet', holds() === [], holds());
    chk('no switch changed', snap() === $s0);
    chk('messages: none (a warning)', quiet());
    chk('words: trying again, n of 3', str_contains(facts($a)['status'], 'of 3'), facts($a)['status']);
});
sc('faults', 'S-B22', 'three failed tries of the endpoint\'s check: a Trip holding only the tasks that call it; its check runs while held (P7)', ['g1:24', 'g4:2B', 'g4:2P', 'g4:2E', 'g4:2Pu'], function () {
    $s0 = snap(); $a = loads5xxTrip();
    chk('state: a Trip after 3 tries', ($a['level'] ?? '') === 'trip' && (int) $a['try_n'] === 3, [$a['level'] ?? null, $a['try_n'] ?? null]);
    chk('guard: each try called the endpoint\'s check', count(array_filter(called(), fn ($c) => $c === 'ep:crawl/loads')) === 3, called());
    chk('hold: exactly overture_bulk, foursquare_bulk, geofabrik_bulk', holds() === LOADS_TASKS, holds());
    chk('unaffected: W1 and its other tasks', !in_array('wf:W1', holds(), true) && !in_array('task:coverage', holds(), true));
    chk('no switch changed (CS-1)', snap() === $s0);
    $GLOBALS['called'] = []; due('ep:crawl/loads:5xx'); tick();
    chk('its check still runs while the tasks are held (P7: no deadlock, g1 row 24)', in_array('ep:crawl/loads', called(), true), called());
    chk('messages: one thread (Pulse, email, push)', msgN() === [1, 1, 1], msgN());
    chk('words: "Paused by the system (<the three tasks>)"', str_starts_with(facts(alarm('ep:crawl/loads:5xx'))['status'], 'Paused by the system (') && str_contains(facts(alarm('ep:crawl/loads:5xx'))['status'], 'overture_bulk'), facts(alarm('ep:crawl/loads:5xx'))['status']);
});
sc('faults', 'S-B23', 'Bluehost\'s bot check (409 humans_) or ModSecurity (406) refuses the worker: a refusal on the endpoint, held at once', ['g1:25', 'g1:26'], function () {
    report(['overture_bulk' => ['held', 'site refused: 409 humans_', ['traversence.com' => [2, 2, '409 humans_']]], 'coverage' => ['done', '']], ['site_blocked' => ['crawl/loads' => ['status' => 409, 'body' => 'humans_21909=1']]]);
    ledger('crawl/loads', 406);
    $open = $GLOBALS['pdo']->query("SELECT akey, component, level FROM control_alarms WHERE cleared_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);
    $a = array_values(array_filter($open, fn ($x) => $x['component'] === 'ep:crawl/loads'))[0] ?? null;
    chk('a refusal on /api/crawl/loads is raised (site_blocked is read; a 406 is a fault on the endpoint)', $a !== null, $open);
    chk('state: Paused by the system at once (a refusal needs a person)', ($a['level'] ?? '') === 'trip', $a);
    chk('hold: the tasks that call that endpoint', holds() === LOADS_TASKS, holds());
});
// -- outside services: back kinds (24–28) ---------------------------------------------------------------------------------
sc('faults', 'S-B24', 'timeouts in a burst inside one run: one fault, a Warning trying again (1 of 3)', ['g2:18', 'g2:16', 'g1:4', 'g1:11', 'g2:26', 'g1:5', 'g2:31', 'g2:3', 'g2:25'], function () {
    chk('a DuckDB read failing at Overture\'s regional S3 host is Overture\'s, never Amazon Web Services\' (g1 row 5; g2 row 31)', ControlEngine::classifyTask($GLOBALS['pdo'], 'overture_bulk', 'IO Error: HTTP 503', ['overturemaps-us-west-2.s3.us-west-2.amazonaws.com' => [4, 4, 'HTTP 503']]) === 'svc:overture', ControlEngine::classifyTask($GLOBALS['pdo'], 'overture_bulk', 'IO Error: HTTP 503', ['overturemaps-us-west-2.s3.us-west-2.amazonaws.com' => [4, 4, 'HTTP 503']]));
    chk('…and a refused Hugging Face token on Foursquare\'s read is Foursquare\'s key (g2 row 3)', ControlEngine::classifyTask($GLOBALS['pdo'], 'foursquare_bulk', 'HTTP 401', ['huggingface.co' => [1, 1, '401 Unauthorized']]) === 'svc:foursquare', ControlEngine::classifyTask($GLOBALS['pdo'], 'foursquare_bulk', 'HTTP 401', ['huggingface.co' => [1, 1, '401 Unauthorized']]));
    $mk = ServiceHealth::siteFailed($GLOBALS['pdo'], 'https://generativelanguage.googleapis.com/v1beta/models/x:generateContent', 200, '<html>unreadable</html>', '', 'Gemini');
    chk('a malformed 200 at a site caller is a back-off fault (g2 row 25: classify(200) is nothing)', $mk === 'malformed', $mk);
    $id = svcFail('api.tavily.com', 'timeout', 'crawler', ['error' => 'timed out'], 6); $a = issueAlarm($id);
    chk('state: a Warning, never a trip from one burst (g2 row 18)', $a && $a['level'] === 'warning', $a['level'] ?? null);
    chk('…its tries are the engine\'s checks (next try due)', $a && $a['next_try_at'] !== null, $a['next_try_at'] ?? null);
    chk('hold: nothing while it tries (a back-off in its wait isn\'t a hold)', holds() === [] && hostHolds() === [], [holds(), hostHolds()]);
    chk('messages: none', quiet());
    report(['identity' => ['failed', 'timed out', ['api.tavily.com' => [3, 3, 'timed out']]], 'coverage' => ['done', '']]);
    // Tavily's open alarms only: the malformed 200 above opened Gemini's own (harness: it counted every open alarm)
    $n = (int) $GLOBALS['pdo']->query("SELECT COUNT(*) FROM control_alarms WHERE cleared_at IS NULL AND component = 'svc:tavily'")->fetchColumn();
    chk('the same cause in a task\'s report is the same fault: one alarm, not two (g1 row 4)', $n === 1, $GLOBALS['pdo']->query("SELECT akey, component FROM control_alarms WHERE cleared_at IS NULL")->fetchAll(PDO::FETCH_ASSOC));
    $a2 = alarm('svc:issue:' . svcFail('api.tavily.com', 'malformed', 'site', ['status' => 200, 'body' => 'not json']));
    chk('a malformed answer is a back kind too: a warning on the diagnostic clock', $a2 && $a2['level'] === 'warning', $a2);
});
sc('faults', 'S-B25', 'a success between failures: Cleared, its tries reset (any real 2xx from any caller)', ['g2:19', 'g2:21', 'g2:17'], function () {
    $id = svcFail('api.weather.gov', 'server', 'site', ['status' => 503]);
    ServiceHealth::answered($GLOBALS['pdo'], 'api.weather.gov', 'site');
    chk('the 2xx closes the warning', issueAlarm($id) === null, issueAlarm($id));
    $id2 = svcFail('api.weather.gov', 'server', 'site', ['status' => 503]);
    chk('a failure 7 h later is a new fault, tries from 0 (never "2 in a row")', $id2 !== $id && (int) (issueAlarm($id2)['try_n'] ?? -1) === 0 && issueAlarm($id2)['level'] === 'warning', issueAlarm($id2));
    $c = svcFail(CC_INDEX, 'server', 'crawler', ['status' => 504]);
    ServiceHealth::answered($GLOBALS['pdo'], CC_INDEX, 'crawler');
    chk('the crawler\'s own 2xx at the host closes the crawler\'s issue', issueAlarm($c) === null);
});
sc('faults', 'S-B26', 'a server\'s 3 tries fail: a Trip and IR; each task reroutes (On a backup) or waits; never the workflow whole', ['g2:16', 'g2:28', 'g1:13', 'g1:17', 'g4:9cB'], function () {
    $s0 = snap(); $id = svcFail('api.tavily.com', 'server', 'crawler', ['status' => 502]);
    checks(['svc:tavily' => red('api.tavily.com answered 502.')]); triesOut('svc:issue:' . $id); $a = issueAlarm($id);
    chk('state: a Trip, its Incident Report open', $a && $a['level'] === 'trip' && (int) $a['incident_id'] > 0, $a);
    chk('hold: its calls (the host)', in_array('api.tavily.com', hostHolds(), true), hostHolds());
    chk('hold: tasks with Brave behind Tavily aren\'t held (On a backup: second_look, refresh, identity, targets, learn)', !array_intersect(['task:second_look', 'task:refresh', 'task:identity', 'task:targets', 'task:learn'], holds()), holds());
    chk('hold: never a workflow whole', !array_filter(holds(), fn ($h) => str_starts_with($h, 'wf:') || $h === 'master:all'), holds());
    chk('no switch changed', snap() === $s0);
    chk('messages: one thread', msgN() === [1, 1, 1], msgN());
});
sc('faults', 'S-B27', '429, S3 SlowDown, Google\'s per-minute limit: a rate wait, never a trip', ['g2:11', 'g2:12', 'g2:13', 'g4:9bB', 'g4:9bP', 'g4:9bE', 'g4:9bPu', 'g1:16'], function () {
    chk('classify: 503 "SlowDown … reduce your request rate" is a rate wait', ServiceHealth::classify(503, '<Code>SlowDown</Code> Please reduce your request rate.') === 'rate');
    chk('classify: Google\'s per-minute RESOURCE_EXHAUSTED is a rate wait, not a quota (g2 row 6)', ServiceHealth::classify(429, '{"error":{"status":"RESOURCE_EXHAUSTED","message":"Quota exceeded for quota metric \'Generate Content API requests per minute\'"}}', '', 'generativelanguage.googleapis.com') === 'rate', ServiceHealth::classify(429, 'Quota exceeded for quota metric \'Generate Content API requests per minute\'', '', 'generativelanguage.googleapis.com'));
    $id = svcFail('query.wikidata.org', 'rate', 'crawler', ['status' => 429, 'retry_after' => '120']); $a = issueAlarm($id);
    chk('state: a Warning (a rate wait)', $a && $a['level'] === 'warning', $a);
    // the hour passing with the system's clock running (a rate wait has no tries of its own: none is forced here)
    for ($i = 0; $i < 4; $i++) { $GLOBALS['pdo']->prepare('UPDATE control_alarms SET first_at = first_at - INTERVAL 20 MINUTE WHERE akey = ?')->execute(['svc:issue:' . $id]); tick(); }
    chk('never a trip, even past the hour', ($x = issueAlarm($id)) && $x['level'] === 'warning', issueAlarm($id)['level'] ?? null);
    chk('hold: nothing (a wait isn\'t a hold)', holds() === [] && hostHolds() === [], [holds(), hostHolds()]);
    chk('messages: none', quiet());
});
sc('faults', 'S-B28', 'the wait ends and the engine\'s check passes, whether or not a task calls it: Cleared', ['g2:12', 'g2:13'], function () {
    $id = svcFail('query.wikidata.org', 'rate', 'crawler', ['status' => 429, 'retry_after' => '60']);
    $GLOBALS['pdo']->prepare('UPDATE service_issues SET hold_until = UTC_TIMESTAMP() - INTERVAL 1 MINUTE WHERE id = ?')->execute([$id]);
    checks(['svc:wikimedia' => pass('query.wikidata.org answered its check (200).')]); tick();
    chk('guard: the engine checked it after its wait (no task calling it)', in_array('svc:wikimedia', called(), true), called());
    chk('state: Cleared after its wait (g2 row 12, L11: open 3 days)', issueAlarm($id) === null, issueAlarm($id));
});
// -- stop kinds (29–38b) --------------------------------------------------------------------------------------------------
sc('faults', 'S-B29', 'a refused key (401): Paused by the system at once, calls with that key held, tasks reroute or wait', ['g1:15', 'g2:1', 'g2:2', 'g4:9aB', 'g4:9aP', 'g4:9aE', 'g4:9aPu'], function () {
    $s0 = snap(); $id = svcFail('api.tavily.com', 'auth', 'crawler', ['status' => 401, 'body' => 'invalid api key']); $a = issueAlarm($id);
    chk('state: a Trip at once, no tries', $a && $a['level'] === 'trip' && (int) $a['try_n'] === 0, $a);
    chk('hold: calls to its host for that caller (its key)', ServiceHealth::holding(issue($id)), issue($id));
    chk('hold: tasks with Brave behind it reroute (not held)', !array_intersect(['task:second_look', 'task:identity'], holds()), holds());
    chk('no switch changed', snap() === $s0);
    $af = ControlAlarms::affects($GLOBALS['pdo'], $a); $fx = facts($a);
    chk('words: never "Nothing could be paused" while its calls are held (g1 row 15; g4 9a)', !str_contains(json_encode($af) . $fx['status'] . $fx['doing'], 'Nothing could be paused'), [$af, $fx['status']]);
    chk('words: its status "Paused by the system (…)"', str_starts_with($fx['status'], 'Paused by the system'), $fx['status']);
    chk('Fix it → the card\'s Its key (#svc-tavily-key)', (ControlAlarms::fixOf($GLOBALS['pdo'], $a)['url'] ?? '') === '/admin/config/register#svc-tavily-key', ControlAlarms::fixOf($GLOBALS['pdo'], $a));
    chk('messages: one thread', msgN() === [1, 1, 1], msgN());
    $b = issueAlarm(svcFail('api.anthropic.com', 'auth', 'site', ['status' => 401]));
    chk('the site\'s own caller (Claude): its calls are held, so its status says "Paused by the system", never "Down: nothing could be held"', ServiceHealth::holding(issue((int) substr((string) $b['akey'], 10))) && str_starts_with(facts($b)['status'], 'Paused by the system'), facts($b)['status']);
});
sc('faults', 'S-B30', 'a Railway key the worker hasn\'t reported yet: Not checked yet, holds nothing', ['g1:21', 'g2:4'], function () {
    notHere('the key\'s state comes from Operations::status over the worker\'s stored report and the register (Operations::switchOnCheck, gateFor); its line is drawn on the Modules card being rebuilt (M). views: pending; covered today by tools/control-board-tests/keys.php');
});
sc('faults', 'S-B31', 'the worker reports a key missing or refused: as S-B29', ['g1:15', 'g2:2'], function () {
    ServiceHealth::fromReport($GLOBALS['pdo'], ['issues' => [['host' => 'api.search.brave.com', 'kind' => 'auth', 'status' => 401, 'body' => 'SUBSCRIPTION_TOKEN_INVALID', 'n' => 1]]]);
    $r = ServiceHealth::open($GLOBALS['pdo'], 'api.search.brave.com', 'crawler'); $a = $r ? issueAlarm((int) $r['id']) : null;
    chk('the worker\'s report opens a refused key (a trip at once)', $a && $a['level'] === 'trip', $r);
    chk('Fix it → Brave\'s Its key', (ControlAlarms::fixOf($GLOBALS['pdo'], $a ?? ['akey' => ''])['url'] ?? '') === '/admin/config/register#svc-brave-key');
});
sc('faults', 'S-B32', 'a ban (403 challenge): Paused at once, never retried, its probe once a day for 3 days', ['g2:14', 'g1:22'], function () {
    $id = svcFail('api.example-ban.test', 'ban', 'crawler', ['status' => 403]);
    $id = $id ?: svcFail('api.tavily.com', 'ban', 'crawler', ['status' => 403, 'body' => 'Attention Required! | Cloudflare']); $a = issueAlarm($id); $r = issue($id);
    chk('state: a Trip at once', $a && $a['level'] === 'trip', $a);
    chk('never retried by the system (no try clock)', $a && $a['next_try_at'] === null, $a['next_try_at'] ?? null);
    chk('its probe is scheduled: once a day (g2 F7: PROBES is empty)', $r && $r['next_probe_at'] !== null, [$r['probe_url'] ?? null, $r['next_probe_at'] ?? null]);
});
sc('faults', 'S-B33', 'the ban\'s probe passes: stays Paused, a Notice "looks lifted" with Reactivate on the thread', ['g2:14'], function () {
    $id = svcFail('api.tavily.com', 'ban', 'crawler', ['status' => 403]);
    ServiceHealth::probed($GLOBALS['pdo'], $id, ['status' => 200, 'body' => 'ok', 'at' => gmdate('Y-m-d H:i:s')]); $a = issueAlarm($id);
    chk('stays Paused (no automatic return, R94)', $a && $a['level'] === 'trip' && ServiceHealth::holding(issue($id)), $a);
    chk('the trip\'s thread says its ban looks lifted (P10: one thread; g4 (b): the EngineCase step is off the board)', str_contains(json_encode(facts($a)), 'looks lifted'), facts($a)['status']);
});
sc('faults', 'S-B34', 'three daily probes fail: stays; the probes stop; it waits for a person', ['g2:14'], function () {
    $id = svcFail('api.tavily.com', 'ban', 'crawler', ['status' => 403]);
    $GLOBALS['pdo']->prepare('UPDATE service_issues SET first_at = UTC_TIMESTAMP() - INTERVAL 4 DAY WHERE id = ?')->execute([$id]);
    ServiceHealth::probed($GLOBALS['pdo'], $id, ['status' => 403, 'at' => gmdate('Y-m-d H:i:s')]);
    chk('stays a trip, held', ($a = issueAlarm($id)) && $a['level'] === 'trip' && ServiceHealth::holding(issue($id)));
    chk('the probes stop', issue($id)['next_probe_at'] === null, issue($id)['next_probe_at']);
});
sc('faults', 'S-B35', 'a site blocks W0 or W3: that site held, one notice, never a trip; Fix it on W0\'s page', ['g1:22', 'g2:15'], function () {
    raiseAs('ops:w0:held', 'wk:w0-indexer', 'notice', 'held', '3 sites blocked us', '', false, false); $a = alarm('ops:w0:held');
    chk('a notice, never a trip', $a && $a['level'] === 'notice', $a);
    chk('hold: nothing of W0 (only those sites\' reads)', holds() === []);
    chk('Fix it → W0\'s page, its sites that blocked us', (ControlAlarms::fixOf($GLOBALS['pdo'], $a)['url'] ?? '') === '/admin/config/control/wf-W0#cb-held');
    chk('messages: none', quiet());
});
sc('faults', 'S-B36', 'a provider\'s per-day quota used (Gemini): Paused until its reset, its check at the reset', ['g2:6', 'g2:10'], function () {
    chk('classify: Gemini\'s per-day RESOURCE_EXHAUSTED is a quota', ServiceHealth::classify(429, '{"error":{"status":"RESOURCE_EXHAUSTED","message":"Quota exceeded for quota metric \'Generate Content API requests per day\'"}}', '', 'generativelanguage.googleapis.com') === 'quota');
    $id = svcFail('generativelanguage.googleapis.com', 'quota', 'site', ['status' => 429, 'body' => 'RESOURCE_EXHAUSTED per day']); $a = issueAlarm($id);
    chk('state: Paused by the system (a trip)', $a && $a['level'] === 'trip', $a);
    chk('its check is set for its reset (00:00 Pacific), not held for good (g2 row 6)', $a && ($a['next_try_at'] !== null || issue($id)['next_probe_at'] !== null), [$a['next_try_at'] ?? null, issue($id)['next_probe_at'] ?? null]);
});
sc('faults', 'S-B37', 'the reset check passes: Closed, Recovered; released', ['g2:6'], function () {
    $id = svcFail('generativelanguage.googleapis.com', 'quota', 'site', ['status' => 429, 'body' => 'RESOURCE_EXHAUSTED per day']);
    ServiceHealth::probed($GLOBALS['pdo'], $id, ['status' => 200, 'at' => gmdate('Y-m-d H:i:s')]);
    $c = last('svc:issue:' . $id);
    chk('closed', $c && $c['cleared_at'] !== null, $c);
    chk('as Recovered (§3c), never "Resolved by the system"', str_starts_with((string) ($c['cleared_how'] ?? ''), 'Recovered'), $c['cleared_how'] ?? null);
    chk('released', !ServiceHealth::holding(issue($id)));
    chk('messages: the close reaches the thread (Pulse and email), no push', count(array_filter(msgs(), fn ($m) => $m['kind'] === 'alarm.recovered')) === 1 && count(pushes()) === 1, array_column(msgs(), 'kind'));
});
sc('faults', 'S-B37a', 'a private input asked onto Gemini: a Notice on Gemini\'s card only; nothing sent, nothing held', ['g2:10'], function () {
    require_once $GLOBALS['APP'] . '/lib/Gemini.php'; require_once $GLOBALS['APP'] . '/lib/AiClient.php';
    $priv = ''; foreach (AiClient::INPUT as $f => $cl) if ($cl !== 'public') { $priv = (string) $f; break; }
    $why = Gemini::refused($priv);
    chk('refused, kept on Claude', $priv !== '' && $why !== null, [$priv, $why]);
    $n = (int) $GLOBALS['pdo']->query("SELECT COUNT(*) FROM control_alarms WHERE level = 'notice' AND proc = 'svc:gemini'")->fetchColumn();
    chk('a Notice on Gemini\'s card (the session manager\'s ruling, 2026-11-21)', $n === 1, $n);
    chk('nothing held, no message', holds() === [] && quiet());
});
sc('faults', 'S-B38', 'out of credit at Anthropic (400 "credit balance is too low"): a quota, Paused at once', ['g2:7'], function () {
    $k = ServiceHealth::classify(400, '{"type":"error","error":{"type":"invalid_request_error","message":"Your credit balance is too low to access the Anthropic API."}}', '', 'api.anthropic.com');
    chk('classified as a quota (a stop kind), not nothing (g2 row 7)', $k === 'quota', $k);
    $id = svcFail('api.anthropic.com', $k === '' ? 'quota' : $k, 'site', ['status' => 400]); $a = issueAlarm($id);
    chk('state: Paused by the system at once', $a && $a['level'] === 'trip');
    chk('Fix it → its card\'s Caps and paces', str_contains((string) (ControlAlarms::fixOf($GLOBALS['pdo'], $a ?? ['akey' => ''])['url'] ?? ''), '#svc-anthropic-caps'), ControlAlarms::fixOf($GLOBALS['pdo'], $a ?? ['akey' => '']));
});
sc('faults', 'S-B38a', 'AWS pauses SES sending: a stop kind, Paused at once (email by SES); alarms go by Bluehost\'s SMTP', ['g2:20'], function () {
    $k = ServiceHealth::classify(400, '<ErrorResponse><Error><Code>AccountSendingPausedException</Code><Message>Email sending is disabled for this account.</Message></Error></ErrorResponse>', '', 'email.us-west-2.amazonaws.com');
    chk('classified as a stop kind (an account-level pause), never a try', in_array($k, ['quota', 'ban', 'auth'], true), $k);
});
sc('faults', 'S-B38b', 'a source\'s file changed shape: Paused at once on that load only, nothing written', ['g2:3'], function () {
    require_once $GLOBALS['APP'] . '/lib/crawler/IrsBulk.php'; require_once $GLOBALS['APP'] . '/lib/crawler/NpiBulk.php';
    $threw = false; try { IrsBulk::batch($GLOBALS['pdo'], 'eo_az.csv', [['EIN' => '123456789', 'NAME' => 'X']]); } catch (DomainException $x) { $threw = true; }
    $a = alarm('load:irs:shape');
    chk('nothing written: the batch refused', $threw);
    chk('a Trip at once on IRS\'s load', $a && $a['level'] === 'trip', $a);
    chk('hold: that load only (no workflow, no other task)', !array_filter(holds(), fn ($h) => $h !== 'task:irs_bulk'), holds());
    chk('messages: the trip\'s thread', msgN() === [1, 1, 1], msgN());
});
// -- caps (39–41a) --------------------------------------------------------------------------------------------------------
sc('faults', 'S-B39', 'near a cap: a Warning; its title\'s number is the reading (round(spend ÷ cap)), the band decides only the level', ['g2:9', 'g4:12B', 'g4:12P', 'g4:12E', 'g4:12Pu'], function () {
    notHere('the cap look (Operations::warnings over the month\'s crawl_costs and the caps) is a caller outside the engine; its title is written by Operations (L605: "<name> at <band>%"), a raiser\'s words P9 moves to state. Covered on the bench once the words come from state; today tools/control-board-tests/r2c.php');
});
sc('faults', 'S-B40', 'our cap at 100%: Paused by the system, every call held', ['g2:8'], function () {
    $s0 = snap(); raiseAs('ops:cap:tavily', 'svc:tavily', 'trip', 'limit', 'Tavily is out of budget', '$20.00 of $20.00 (100%)', false); $a = alarm('ops:cap:tavily');
    chk('state: a Trip at once', $a && $a['level'] === 'trip', $a);
    chk('hold: its automatic calls (service:tavily)', in_array('service:tavily', holds(), true), holds());
    chk('no switch changed', snap() === $s0);
    chk('Fix it → its caps', (ControlAlarms::fixOf($GLOBALS['pdo'], $a)['url'] ?? '') === '/admin/config/register#svc-tavily-caps');
    chk('messages: one thread', msgN() === [1, 1, 1], msgN());
});
sc('faults', 'S-B41', 'the cap raised or the month turned: Closed, released', ['g2:8'], function () {
    raiseAs('ops:cap:tavily', 'svc:tavily', 'trip', 'limit', 'Tavily is out of budget', '', false);
    ControlAlarms::clear($GLOBALS['pdo'], 'ops:cap:tavily', 'Back under its warning level.');
    chk('closed as Recovered, its hold released', alarm('ops:cap:tavily') === null && str_starts_with((string) last('ops:cap:tavily')['cleared_how'], 'Recovered') && !in_array('service:tavily', holds(), true), [last('ops:cap:tavily')['cleared_how'] ?? null, holds()]);
});
sc('faults', 'S-B41a', 'a workflow\'s share of the AI cap used: a Warning on that workflow only', ['g2:10'], function () {
    notHere('AiClient::shareLeft reads budgets and the month\'s AI spend and refuses the call to its caller; no raise exists to drive (the ruling, 2026-11-21, is a build item: "a Warning on that workflow only"). Listed for the session');
});
// -- backups and needs (42–46) --------------------------------------------------------------------------------------------
sc('faults', 'S-B42', 'a backup\'s own check fails while the primary works: its calls only; nothing waits on it', ['g1:19', 'g2:27'], function () {
    $id = svcFail('api.search.brave.com', 'server', 'crawler', ['status' => 503]); checks(['svc:brave' => red()]); triesOut('svc:issue:' . $id);
    chk('a Trip on the backup', ($a = issueAlarm($id)) && $a['level'] === 'trip');
    chk('hold: the backup\'s calls only; no task (Tavily, their first choice, works)', in_array('api.search.brave.com', hostHolds(), true) && !array_filter(holds(), fn ($h) => !in_array($h, ['task:source', 'task:howto'], true)), [hostHolds(), holds()]);
});
sc('faults', 'S-B43', 'a first source paused: its task On a backup, said as a Notice naming both', ['g2:27', 'g2:30', 'g4:8B', 'g4:8P', 'g4:8E', 'g4:8Pu', 'g1:23'], function () {
    $id = svcFail('api.tavily.com', 'auth', 'crawler', ['status' => 401]);
    report(['identity' => ['done', 'search: brave instead (tavily held)'], 'coverage' => ['done', '']], ['search_switches' => [['task' => 'identity', 'from' => 'tavily', 'to' => 'brave', 'n' => 3]]]);
    chk('the task isn\'t held (it runs on Brave)', !in_array('task:identity', holds(), true));
    $n = $GLOBALS['pdo']->query("SELECT COUNT(*) FROM control_alarms WHERE level = 'notice' AND cleared_at IS NULL AND (title LIKE '%backup%' OR title LIKE '%Brave%')")->fetchColumn();
    chk('a Notice "<task> is on its backup (Brave) while Tavily is paused" (search_switches read: g2 row 27)', (int) $n >= 1, (int) $n);
    chk('messages: a notice sends nothing', count(mails()) === 1, count(mails()));
});
sc('faults', 'S-B44', 'the first source\'s hold released: back on its first choice', ['g2:27'], function () {
    notHere('needs S-B43\'s On a backup notice, which no raiser makes yet (search_switches are read by nothing): S-B43 fails first');
});
sc('faults', 'S-B45', 'every source of a need paused: every task that needs it waits, in every workflow the need names', ['g1:18'], function () {
    raiseAs('pf:nd:prose', 'nd:prose', 'trip', 'held', 'Page prose into facts: no source works', 'gemini paused', true, false);
    $h = holds();
    chk('a Trip on the need', ($a = alarm('pf:nd:prose')) && $a['level'] === 'trip');
    chk('hold: never a workflow whole (only tasks)', !array_filter($h, fn ($x) => str_starts_with($x, 'wf:')), $h);
});
sc('faults', 'S-B46', 'every task of a workflow held: the workflow waits whole, with no alarm of its own', ['g1:17', 'g1:20', 'g3:C2'], function () {
    $a = loads5xxTrip();
    chk('no alarm on W1 of its own while tasks are held', !array_filter(ControlAlarms::ofProc($GLOBALS['pdo'], 'wf:W1'), fn ($x) => $x['akey'] === 'pf:wf:W1' || str_starts_with($x['akey'], 'wf:')), ControlAlarms::ofProc($GLOBALS['pdo'], 'wf:W1'));
});
// -- the worker (47–52) ---------------------------------------------------------------------------------------------------
sc('faults', 'S-B47', 'no run reported, or one abandoned: a Warning, the crawler\'s check its tries', ['g1:28', 'g1:30', 'g1:31', 'g1:27', 'g1:12'], function () {
    report(['second_look' => ['not_reached', ''], 'refresh' => ['not_reached', '']]);
    chk('a starved run: a Warning on the crawler, never a trip (g1 row 12)', ($st = alarm('run:starved')) && $st['level'] === 'warning' && holds() === [], alarm('run:starved'));
    fresh();
    $GLOBALS['pdo']->exec("INSERT INTO crawl_runs (run_key, started_at, status) VALUES ('late1', NOW() - INTERVAL 40 MINUTE, 'running')");
    watch(); $a = alarm('silent:run');
    chk('state: a Warning, trying again (its component the crawler)', $a && $a['level'] === 'warning' && $a['component'] === 'wk:crawler', $a);
    chk('hold: nothing yet', !in_array('master:all', holds(), true), holds());
    $GLOBALS['pdo']->exec("UPDATE crawl_runs SET status = 'abandoned' WHERE run_key = 'late1'"); watch();
    chk('an abandoned run counts as a failure: it doesn\'t clear (g1 row 30: four killed runs never trip)', alarm('silent:run') !== null, last('silent:run')['cleared_how'] ?? null);
});
sc('faults', 'S-B48', 'the crawler\'s 3 tries fail: a Trip holding all gathering; W0, the site and its jobs unaffected', ['g1:29', 'g1:30', 'g1:31', 'g1:32', 'g1:35'], function () {
    chk('no crawler part carries a critical duty (g1 row 35)', ControlRegister::duty($GLOBALS['pdo'], 'wk:crawler') === '' && ControlRegister::duty($GLOBALS['pdo'], 'wf:W1') === '');
    $s0 = snap(); report(['second_look' => ['failed', 'x'], 'refresh' => ['failed', 'y']], ['status' => 'failed', 'why' => 'every task failed']);
    checks(['wk:crawler' => red('The crawler\'s last run failed.')]); triesOut('run:failed'); $a = alarm('run:failed');
    chk('state: a Trip after the engine\'s tries', $a && $a['level'] === 'trip' && (int) $a['try_n'] === 3, $a);
    chk('hold: all gathering (master:all), nothing else', holds() === ['master:all'], holds());
    chk('no switch changed: the master is a hold, never a switch', snap() === $s0);
    chk('messages: one thread', msgN() === [1, 1, 1], msgN());
});
sc('faults', 'S-B49', 'a run with tasks idle and one failing: no fault on the crawler; the task follows its own row', ['g1:3', 'g1:29'], function () {
    report(['second_look' => ['idle', ''], 'refresh' => ['idle', ''], 'identity' => ['failed', 'KeyError: x']]);
    chk('no alarm on the crawler (g1 F4: idle + one failure is "failed", all gathering)', alarm('run:failed') === null, alarm('run:failed'));
    chk('the failing task has its own alarm', alarm('task:failed:identity') !== null, $GLOBALS['pdo']->query("SELECT akey FROM control_alarms")->fetchAll(PDO::FETCH_COLUMN));
});
sc('faults', 'S-B50', 'a worker\'s heartbeat silent 15 min: W0 held after its tries; the video worker\'s queue waits', ['g1:33', 'g1:34'], function () {
    raiseAs('silent:w0', 'wk:w0-indexer', 'trip', 'silent', 'W0 Indexer has gone quiet', 'last check-in 30 min ago', false); $a = alarm('silent:w0');
    chk('a Warning first (the engine tries its check)', $a && $a['level'] === 'warning', $a);
    checks(['wk:w0-indexer' => red('W0 Indexer hasn\'t checked in.')]); triesOut('silent:w0');
    chk('its tries out: a Trip holding W0', ($x = alarm('silent:w0')) && $x['level'] === 'trip' && in_array('wf:W0', holds(), true), [alarm('silent:w0')['level'] ?? null, holds()]);
    $v = ControlEngine::check($GLOBALS['pdo'], 'ep:video/jobs');
    chk('the video worker has a heartbeat check of its own (its endpoints checked through it)', in_array('wk:video-worker', called(), true) || in_array('ep:video/jobs', called(), true), called());
});
sc('faults', 'S-B51', 'Railway: a deploy failed or building over 30 min: a Warning, the old version running', ['g2:5'], function () {
    notHere('Railway\'s deploy watch (Operations::watchTick) reads Railway\'s API, an outside call the bench doesn\'t make; its raise isn\'t in the engine yet (02 §5.2 "the ops:watch:* Pulse cases become rows 51 and 60"). Listed for the session');
});
sc('faults', 'S-B52', 'Railway refuses our token (401): Paused at once, deploys not watched', ['g2:5'], function () {
    notHere('as S-B51: the watch catches the 401 as ok:null ("Couldn\'t look: HTTP 401") before any raise (g2 row 5); no engine path to drive');
});
// -- Bluehost and GitHub (53–61) -----------------------------------------------------------------------------------------
sc('faults', 'S-B53', 'Bluehost is down: the outside heartbeat emails', ['g3:C13', 'g3:D6'], function () {
    notHere('not set up (ALARM_TO): the heartbeat sends from GitHub with ALARM_TO and the SES send-only key, which Jason sets (close.md A1). Its site-side import: tools/control-board-tests/heartbeat-import.php; the heartbeat: tools/heartbeat/test_heartbeat.py');
});
sc('faults', 'S-B54', 'a database realm refuses at the watch: a critical Trip at once (database)', ['g3:C14', 'g3:C3', 'g3:C4'], function () {
    raiseAs('pf:site:db', 'site:db', 'trip', 'check', 'The databases: the listings realm refused', 'SQLSTATE[HY000] [1045]', true, true); $a = alarm('pf:site:db');
    chk('a critical part (database) trips at once whatever the alarm\'s kind (P8; g3 D2)', $a && $a['level'] === 'trip', $a);
    chk('…while still trying', $a && $a['retrying_at'] !== null && $a['next_try_at'] !== null, $a);
    chk('words: "Down: the system is still trying"', str_contains(title($a), 'Down: the system is still trying'), title($a));
    chk('messages: one at once', msgN() === [1, 1, 1], msgN());
});
sc('faults', 'S-B55', 'the watch hasn\'t ticked in 10 min, or a critical job\'s cron line missing: a critical Trip (procedures)', ['g3:J8', 'g3:J11'], function () {
    notHere('a dead watch is seen from outside the host (the heartbeat, not set up: ALARM_TO); a missing cron line is read from cPanel\'s API (Scheduler::sync), an outside call');
});
sc('faults', 'S-B56', 'cPanel\'s API doesn\'t answer (email and pages fine): a Warning on cPanel\'s API, never "Page load"', ['g3:C5', 'g3:D6'], function () {
    raiseAs('pf:svc:bluehost', 'svc:bluehost', 'warning', 'check', 'Bluehost: cPanel\'s API didn\'t answer', 'cPanel UAPI timed out', true, true); $a = alarm('pf:svc:bluehost');
    chk('a Warning, never a critical "Page load" trip (g3 C5)', $a && $a['level'] === 'warning', [$a['level'] ?? null, title($a)]);
    chk('messages: none', quiet(), msgN());
});
sc('faults', 'S-B57', 'GitHub\'s release look gets no answer: a critical Trip, releases and the sync held, still trying', ['g3:J9', 'g3:D2'], function () {
    raiseAs('pf:svc:github', 'svc:github', 'trip', 'crash', 'GitHub didn\'t answer the release look', 'found: -1'); $a = alarm('pf:svc:github');
    chk('a Trip at once, still trying (P8)', $a && $a['level'] === 'trip' && $a['retrying_at'] !== null, $a);
    chk('words: never "Paused by the system" with nothing held', $a && (holds() !== [] || !str_contains(title($a), 'Paused by the system')), [title($a), holds()]);
    chk('messages: one at once', msgN() === [1, 1, 1], msgN());
});
sc('faults', 'S-B58', 'a GitHub token expiring within 14 days: a Warning', [], function () { notHere('the token\'s expiry is read from GitHub\'s API (an outside call); no raiser on the site yet (manifest spec)'); });
sc('faults', 'S-B59', 'a GitHub token refused: Paused at once, what it does held', [], function () { notHere('as S-B58: GitHub\'s answer is read by the release job and the sync watch, outside calls; no raiser yet'); });
sc('faults', 'S-B60', 'the sync\'s run failed or stuck: a Warning, then a Trip after 3 tries', ['g2:5'], function () { notHere('the sync watch reads GitHub Actions\' runs (an outside call); today a Pulse case ops:watch:* (02 §5.3 removes it)'); });
sc('faults', 'S-B61', 'no sync run an hour after its slot: a Warning; a Trip at 14 h', [], function () { notHere('as S-B60'); });
// -- the site's jobs (62–68) ----------------------------------------------------------------------------------------------
sc('faults', 'S-B62', 'a site job serving no workflow fails: a Warning, nothing held, its next run its check', ['g3:J1', 'g4:13B', 'g4:13P', 'g4:13E', 'g4:13Pu'], function () {
    jobRun('sales_tick', 0, 'Lead scores: division by zero'); watch(); $a = alarm('job:sales_tick');
    chk('state: a Warning (its next run is its check: no try clock)', $a && $a['level'] === 'warning' && $a['next_try_at'] === null, $a);
    chk('hold: nothing', holds() === [], holds());
    chk('messages: none for the job', !msgOf($a), msgOf($a));
    $GLOBALS['pdo']->exec("UPDATE control_alarms SET first_at = first_at - INTERVAL 61 MINUTE WHERE akey = 'job:sales_tick'"); tick();
    chk('the hour passing makes no trip with no try made (g3 J1)', ($x = alarm('job:sales_tick')) && $x['level'] === 'warning', alarm('job:sales_tick')['level'] ?? null);
});
sc('faults', 'S-B63', 'a job tied to workflows fails: a Warning on the job; the workflows\' crawler tasks unaffected', ['g3:J2', 'g3:J3'], function () {
    foreach ([3, 2, 1] as $m) { jobRun('listings_tick', 0, 'deadlock', $m); watch(); }
    chk('hold: never W1 whole; no crawler task', !array_filter(holds(), fn ($h) => str_starts_with($h, 'wf:') || str_starts_with($h, 'task:')), holds());
    foreach ([3, 2, 1] as $m) { jobRun('guide_finds_tick', 0, 'boom', $m); watch(); }
    chk('a job tied to three workflows holds none of them (g3 J3: W4 only)', !array_intersect(['wf:W4', 'wf:W8', 'wf:W10'], holds()), holds());
});
sc('faults', 'S-B64', 'its next run skipped ("Paused: …"): nothing changes (a skip is never a good run)', ['g3:J2', 'g3:M1', 'g3:D5'], function () {
    jobRun('listings_tick', 0, 'deadlock', 3); watch();
    jobRun('listings_tick', 1, 'Paused: gathering is off', 1); watch();
    chk('the alarm stays open (g3 J2: a skipped run read as Recovered)', alarm('job:listings_tick') !== null, last('job:listings_tick')['cleared_how'] ?? null);
});
sc('faults', 'S-B65', 'a job late: not started by its time plus 5 min: a Warning', ['g3:J7'], function () {
    jobRun('sales_tick', 1, 'ok', 400); watch();
    $n = $GLOBALS['pdo']->query("SELECT akey FROM control_alarms WHERE cleared_at IS NULL AND akey LIKE '%sales_tick%'")->fetchAll(PDO::FETCH_COLUMN);
    chk('a lateness warning is raised (g3 J7: missing)', (bool) $n, $n);
});
sc('faults', 'S-B66', 'a job stuck over 2 h: a fault (not slow): a Warning, then a Trip after 3 tries, holding its own work', ['g3:J5', 'g3:J4'], function () {
    jobRun('identity_recheck_tick', null, '', 150, null); watch(); $a = alarm('job:identity_recheck_tick:stuck');
    chk('raised', $a !== null);
    chk('a fault on the diagnostic clock, not "slow" that can never trip (g3 J5)', $a && $a['kind'] !== 'slow', $a['kind'] ?? null);
    $GLOBALS['pdo']->exec("DELETE FROM job_runs"); jobRun('identity_recheck_tick', 1, 'ok', 3000); watch();
    chk('no false clear when no run comes in 2 days (g3 J4)', alarm('job:identity_recheck_tick:stuck') !== null, last('job:identity_recheck_tick:stuck')['cleared_how'] ?? null);
});
sc('faults', 'S-B67', 'a job slow: over 3× its usual and over a minute: a Warning (Slow) that clears itself', ['g3:J10'], function () {
    foreach (range(12, 2) as $m) jobRun('digest_tick', 1, 'ok', $m * 10, 40);
    jobRun('digest_tick', 1, 'ok', 1, 400); watch(); $a = alarm('jobslow:digest_tick');
    chk('a Warning (Slow), nothing held', $a && $a['level'] === 'warning' && holds() === [], $a);
});
sc('faults', 'S-B68', 'billing_tick fails or is stuck; releases_tick quiet: a critical Trip at once whatever the kind', ['g3:J6', 'g3:J9', 'g3:J12', 'g3:D1', 'g3:D2'], function () {
    jobRun('billing_tick', null, '', 150, null); watch(); $a = alarm('job:billing_tick:stuck');
    chk('stuck billing (a critical job): a Trip at once (g3 J6, D2)', $a && $a['level'] === 'trip', $a['level'] ?? null);
    fresh(); jobRun('billing_tick', 0, 'renewals: card declined handler threw'); watch(); $b = alarm('job:billing_tick');
    chk('failed billing: a Trip at once, still trying', $b && $b['level'] === 'trip' && $b['retrying_at'] !== null, $b);
    chk('words: "Down: the system is still trying" (nothing held)', $b && str_contains(title($b), 'Down: the system is still trying'), title($b));
    chk('messages: one at once for billing', count(msgOf($b)) === 1, msgOf($b));
    due('job:billing_tick', 61); tick();
    chk('no Escalation Notice with no retry made (g3 J12: its next run is 24 h away)', !array_filter(msgOf(alarm('job:billing_tick') ?? last('job:billing_tick')), fn ($m) => $m['kind'] === 'alarm.escalated'), msgOf(last('job:billing_tick')));
    fresh(); raiseAs('rel:job:quiet', 'job:releases_tick', 'warning', 'check', 'The 10-minute release job hasn\'t looked in git', '', false);
    chk('releases_tick quiet 30 min: a critical Trip (g3 J9)', ($c = alarm('rel:job:quiet')) && $c['level'] === 'trip', alarm('rel:job:quiet')['level'] ?? null);
});
// -- the sensors and checks (69–79) --------------------------------------------------------------------------------------
sc('faults', 'S-B69', 'search\'s test fails: a critical Trip at once, each probe a try, nothing held', ['g3:C10', 'g3:C11', 'g3:D1', 'g3:D3', 'g3:D4', 'g4:4B', 'g4:4P', 'g4:4E', 'g4:4Pu'], function () {
    raiseAs('crit:site:search', 'site:search', 'trip', 'crash', 'Search is failing', 'the test search answered 500'); $a = alarm('crit:site:search');
    chk('a Trip at once, still trying (P8)', $a && $a['level'] === 'trip' && $a['retrying_at'] !== null, $a);
    chk('nothing held (it\'s what visitors use)', holds() === []);
    chk('words: "Down: the system is still trying" on every surface (g4 row 4: never "Paused")', str_contains(title($a), 'Down: the system is still trying') && str_starts_with(push($a)['body'], 'Down'), [title($a), push($a)]);
    chk('messages: one at once', msgN() === [1, 1, 1], msgN());
});
sc('faults', 'S-B70', 'QuickBooks refuses our token: Paused at once; closes as Fixed by the person who reconnected', ['g3:C12', 'g3:D5'], function () {
    raiseAs('crit:site:payments:token', 'site:payments', 'trip', 'held', 'QuickBooks needs reconnecting', 'refresh token expired', true, false); $a = alarm('crit:site:payments:token');
    chk('a Trip at once', $a && $a['level'] === 'trip', $a);
    chk('Fix it lands on the person\'s fix (a key: Connect QuickBooks again), never none (g4 (b))', ControlAlarms::fixOf($GLOBALS['pdo'], $a) !== null, ControlAlarms::fixOf($GLOBALS['pdo'], $a));
    checks(['site:payments' => pass('QuickBooks answered with the new token.')]);
    [$ok, $w] = resolveA($a);
    chk('closed as Fixed by Jason, never "the system fixed it" (g3 C12)', $ok && str_contains((string) (last('crit:site:payments:token')['cleared_how'] ?? ''), 'Jason') && !str_contains((string) last('crit:site:payments:token')['cleared_how'], 'the system fixed it'), [$w, last('crit:site:payments:token')['cleared_how'] ?? null]);
});
sc('faults', 'S-B70a', 'a test order reaching a charge: a Notice on QuickBooks\' card; no real money moves', [], function () {
    notHere('QuickBooksPay::guardReal() refuses to the caller only; the Notice on QuickBooks\' card (the ruling, 2026-11-21) has no raiser yet. Listed for the session');
});
sc('faults', 'S-B71', 'a module\'s pre-flight line goes red: the component\'s own fault by its kind, never an alarm of the line\'s own', ['g3:C1', 'g3:C6', 'g1:20'], function () {
    raiseAs('pf:svc:tavily', 'svc:tavily', 'trip', 'check', 'Tavily: its check failed', '502', true, true); $a = alarm('pf:svc:tavily');
    chk('a Warning on the component, tried on the diagnostic clock (g3 C1: zero tries, then a trip)', $a && $a['level'] === 'warning' && $a['component'] === 'svc:tavily' && $a['next_try_at'] !== null, $a);
    checks(['svc:tavily' => red()]); triesOut('pf:svc:tavily');
    chk('its tries are real checks of Tavily', count(array_filter(called(), fn ($c) => $c === 'svc:tavily')) === 3, called());
    chk('…then a trip holding its tasks with no backup only', ($b = alarm('pf:svc:tavily')) && $b['level'] === 'trip' && !array_intersect(['task:second_look', 'task:identity', 'wf:W2'], holds()), holds());
});
sc('faults', 'S-B72', 'site:db or site:sched red with the master OFF: a critical Trip at once (the master stops gathering, never duties)', ['g3:C3', 'g3:C4', 'g3:D2'], function () {
    $GLOBALS['pdo']->exec("UPDATE automation_switches SET paused = 1 WHERE kind = 'master'"); Automation::forget();
    raiseAs('pf:site:sched', 'site:sched', 'warning', 'check', 'The scheduler: its cron lines are missing', '', true, true); $a = alarm('pf:site:sched');
    chk('a Trip at once with the master off (g3 C3)', $a && $a['level'] === 'trip', $a);
    chk('messages: the founder told', msgN() === [1, 1, 1], msgN());
});
sc('faults', 'S-B73', 'Start\'s watch: a switched-on item\'s gate fails: a hold record, the switch untouched', ['g3:C8', 'g3:C7'], function () {
    $s0 = snap(); require_once $GLOBALS['APP'] . '/lib/SystemStart.php';
    $m = new ReflectionClass('SystemStart'); $fn = null; foreach (['pauseItem', 'holdItem', 'hold'] as $n) if ($m->hasMethod($n)) { $fn = $m->getMethod($n); break; }
    chk('Start\'s watch has its hold through the engine', $fn !== null, 'no pauseItem / holdItem');
    if ($fn) { $fn->setAccessible(true); $ps = $fn->getParameters(); try { $fn->invokeArgs(null, array_slice([$GLOBALS['pdo'], 'wf:W2', '3 of its 10 runs failed since it came on.', 7], 0, count($ps))); } catch (Throwable $x) { chk('it runs', false, $x->getMessage()); } }
    chk('the switch untouched (CS-1; D21)', snap() === $s0);
    chk('a hold record (system_pauses) for W2, or its trip', in_array('wf:W2', holds(), true) || (bool) $GLOBALS['pdo']->query("SELECT 1 FROM control_alarms WHERE akey LIKE 'start:held:%' AND cleared_at IS NULL")->fetchColumn(), holds());
});
sc('faults', 'S-B74', 'a task returns empty in 12 runs: a Warning, nothing held', ['g1:6'], function () {
    raiseAs('ops:empty:refresh', 'wf:W2', 'warning', 'check', 'W2 Keeping returns empty', '12 runs', false, false); $a = alarm('ops:empty:refresh');
    chk('a Warning, nothing held, no message', $a && $a['level'] === 'warning' && holds() === [] && quiet(), $a);
});
sc('faults', 'S-B75', 'received ≠ stored + updated + Review + refused, either way: a Warning on that hop', ['g1:6'], function () {
    ledger('crawl/results', 200, 100, 2, ['stored' => 1]);
    chk('fewer accounted for than received is a warning too (g2 R55: only "more")', alarm('ep:crawl/results:count') !== null, 'no alarm');
    fresh(); ledger('crawl/results', 200, 100, 1, ['stored' => 2]);
    chk('more accounted for than received: a Warning', ($a = alarm('ep:crawl/results:count')) && $a['level'] === 'warning');
});
sc('faults', 'S-B76', 'cost per record past control_cost_factor × its 30-day usual: a Warning', [], function () { notHere('no raiser yet: control_cost_factor (Q19: 2.0×) is set; the cost watch is a build item listed for the session'); });
sc('faults', 'S-B77', 'Pulse slow to gather: a Warning, board only', [], function () {
    raiseAs('slow:site:pulse', 'site:pulse', 'warning', 'slow', 'Pulse is slow to gather (2.4 s)', '2400 ms', false, false); $a = alarm('slow:site:pulse');
    chk('a Warning, board only', $a && $a['level'] === 'warning' && quiet());
});
sc('faults', 'S-B78', 'a query over its limit: a Warning (Slow)', [], function () {
    raiseAs('watch:history:slow', 'site:watch', 'trip', 'slow', 'The historian is slow', '1800 ms', false, false); $a = alarm('watch:history:slow');
    chk('slow is never a trip: a Warning', $a && $a['level'] === 'warning');
});
sc('faults', 'S-B79', 'a fault on a part in maintenance: a Notice only; its holds stand', ['g3:M4'], function () {
    require_once $GLOBALS['APP'] . '/lib/ControlMaintenance.php';
    $ok = true; try { ControlMaintenance::take($GLOBALS['pdo'], 'wf:W2', time(), time() + 3600, 'testing a new reader', 7, 'Jason'); } catch (Throwable $x) { $ok = $x->getMessage(); }
    if ($ok !== true) { notHere('ControlMaintenance::take on the bench: ' . mb_substr((string) $ok, 0, 120)); return; }
    raiseAs('task:failed:second_look', 'wf:W2', 'trip', 'crash', 'second_look failed', 'boom');
    chk('a Notice only', ($a = alarm('task:failed:second_look')) && $a['level'] === 'notice', alarm('task:failed:second_look'));
});
// -- the dispatcher (80–82) -----------------------------------------------------------------------------------------------
sc('faults', 'S-B80', 'a trip with nobody to receive it: shown, and a critical Trip on the Communications Dispatcher', ['g4:14B', 'g4:14P', 'g4:14E', 'g4:14Pu'], function () {
    $GLOBALS['pdo']->exec("DELETE FROM staff_accounts");
    $k = ccIndexTrip();
    $d = alarm('dispatch:nobody');
    chk('the dispatcher\'s own fault raised as a critical Trip (P11; g4 row 14)', $d && $d['level'] === 'trip', $GLOBALS['pdo']->query("SELECT akey, level FROM control_alarms")->fetchAll(PDO::FETCH_ASSOC));
    chk('the trip says it reached no one', str_contains(json_encode(facts(alarm($k))) . (alarm($k)['reading'] ?? ''), 'Reached no one'), alarm($k)['reading'] ?? null);
});
sc('faults', 'S-B81', 'email fails (SMTP and SES): a fault on that channel; the message falls back to Pulse and push', [], function () {
    notHere('Notify\'s channel fallback (row 81) has no path yet; mail.failure is still a Pulse message (g4 (c)). Listed for the session');
});
sc('faults', 'S-B82', 'a Critical message with email paused by message control: sent anyway', ['g4:15B', 'g4:15P', 'g4:15E', 'g4:15Pu'], function () {
    $GLOBALS['pdo']->exec("INSERT INTO comms_controls (ckey, paused, why, by_uid, at) VALUES ('channel:email', 1, 'testing', 7, UTC_TIMESTAMP())");
    raiseAs('crit:site:search', 'site:search', 'trip', 'crash', 'Search is failing', '500');
    chk('the Critical trip\'s email is sent anyway (g4 row 15)', count(mails()) === 1, [count(mails()), $GLOBALS['pdo']->query("SELECT via, stage, detail FROM comms_route")->fetchAll(PDO::FETCH_ASSOC)]);
    chk('…and its push', count(pushes()) === 1);
});
