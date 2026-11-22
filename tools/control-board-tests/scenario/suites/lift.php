<?php
// Suite "lift" (02 §4 group A, S-A1–S-A15): the W3 lift of 2026-11-21 under the designed model. The starting state's causes
// (step3/lift: Common Crawl's index answering 504, our /api/crawl/loads not replying in time for the bulk loads, two W3
// switches left off by a pre-0175 trip) are caused through the real callers; each press of group A follows.
const CC_INDEX = 'index.commoncrawl.org';
const CC_DATA = 'data.commoncrawl.org';
/** Common Crawl's index answering 504 to the crawler (issue #35's cause), as ServiceHealth records it. Returns its alarm key. */
function ccIndexDown(): string { $id = svcFail(CC_INDEX, 'server', 'crawler', ['status' => 504, 'body' => 'Gateway Time-out', 'what' => 'a CDX lookup']); return 'svc:issue:' . $id; }
/** ...and the engine's three tries of its check failing: the trip. */
function ccIndexTrip(): string { $k = ccIndexDown(); checks(['svc:commoncrawl' => red('index.commoncrawl.org answered 504 Gateway Time-out.')]); triesOut($k); return $k; }
/** The workflows and tasks that must carry on when only the index is held (02 row 2: Unaffected: W3 and every workflow). */
function noWorkflowHeld(): bool { foreach (holds() as $h) if (str_starts_with($h, 'wf:') || str_starts_with($h, 'task:') || $h === 'master:all') return false; return true; }

sc('lift', 'S-A1', 'a 504 at Common Crawl\'s index: a warning, its first try at the next tick, nothing sent', ['g1:13', 'g2:16', 'g2:17', 'g4:1B', 'g4:1P', 'g4:1E', 'g4:1Pu'], function () {
    $s0 = snap(); $k = ccIndexDown(); $a = alarm($k);
    chk('state: a Warning (P3: tries first)', $a && $a['level'] === 'warning', $a['level'] ?? null);
    chk('its component is Common Crawl, classified by the engine (P2)', ($a['component'] ?? '') === 'svc:commoncrawl', $a['component'] ?? null);
    chk('its first try is due about 5 min after the fault (ruling 2)', $a && $a['next_try_at'] !== null && abs(strtotime($a['next_try_at'] . ' UTC') - strtotime($a['first_at'] . ' UTC') - 300) < 5, [$a['first_at'] ?? null, $a['next_try_at'] ?? null]);
    chk('hold: nothing held while it tries', holds() === [] && hostHolds() === [], [holds(), hostHolds()]);
    chk('no switch value changed (CS-1)', snap() === $s0);
    $f = facts($a);
    chk('words: trying again by itself, n of 3, its next time; nothing held', str_contains($f['status'], 'trying again') && str_contains($f['status'], 'of 3') && str_contains($f['status'], 'Nothing is held'), $f['status']);
    chk('messages: a warning goes to the board only (no Pulse row, email or push)', quiet(), [count(msgs()), count(mails()), count(pushes())]);
});

sc('lift', 'S-A2', 'the index\'s three tries fail: a trip holding only calls to the index, W3 and every workflow unaffected', ['g1:13', 'g2:16', 'g2:17', 'g2:22', 'g4:9cB', 'g4:9cP', 'g4:9cE', 'g4:9cPu', 'g4:2B', 'g4:2P', 'g4:2E', 'g4:2Pu'], function () {
    $s0 = snap(); $k = ccIndexTrip(); $a = alarm($k);
    chk('state: a Trip after 3 tries (try_n 3)', $a && $a['level'] === 'trip' && (int) $a['try_n'] === 3 && $a['tripped_at'] !== null, [$a['level'] ?? null, $a['try_n'] ?? null]);
    chk('guard: each try called Common Crawl\'s check (svc:commoncrawl), 3 times', count(array_filter(called(), fn ($c) => $c === 'svc:commoncrawl')) === 3, called());
    chk('hold: calls to the index host held', in_array(CC_INDEX, hostHolds(), true), hostHolds());
    chk('hold: W3 and every workflow and task unaffected (02 row 2; workflows-page §8)', noWorkflowHeld(), holds());
    chk('no switch value changed (CS-1)', snap() === $s0);
    chk('an Incident Report opened at the trip (P12)', (int) ($a['incident_id'] ?? 0) > 0);
    $f = facts($a);
    chk('words: "Paused by the system (<what\'s held>)" (ruling 5), never "held until an admin resolves it"', str_starts_with($f['status'], 'Paused by the system (') && !str_contains($f['status'] . $f['doing'] . ($a['means'] ?? ''), 'until an admin resolves'), [$f['status'], $a['means'] ?? '']);
    chk('messages: one thread: one Pulse row, one email, one push', count(msgs()) === 1 && count(mails()) === 1 && count(pushes()) === 1, [count(msgs()), count(mails()), count(pushes())]);
    chk('…its subject "SYSTEM ALARM NOTICE: <part>: Paused by the system"', str_starts_with((string) (msgs()[0]['title'] ?? ''), 'SYSTEM ALARM NOTICE: ') && str_ends_with((string) (msgs()[0]['title'] ?? ''), ': Paused by the system'), msgs()[0]['title'] ?? null);
});

sc('lift', 'S-A3', 'W3\'s line is a roll-up: no alarm on W3, its row "Working"', ['g1:20', 'g3:C2'], function () {
    $s0 = snap(); ccIndexTrip();
    $pl = Preflight::lines($GLOBALS['pdo']);
    $line = $pl['wf:W3'] ?? ['wf', 'W3 Sources', true];
    Preflight::alarm($GLOBALS['pdo'], 'wf:W3', $line, ['s' => 'bad', 'said' => 'Common Crawl (W3): held by issue', 'extra' => ['parts' => [['Common Crawl', 'bad']]]]);
    chk('no alarm of W3\'s own (a roll-up isn\'t a fault, 02 §2.2)', alarm('pf:wf:W3') === null && !array_filter(ControlAlarms::ofProc($GLOBALS['pdo'], 'wf:W3'), fn ($x) => $x['level'] !== 'notice'), ControlAlarms::ofProc($GLOBALS['pdo'], 'wf:W3'));
    chk('hold: W3 not held whole', !in_array('wf:W3', holds(), true), holds());
    chk('no switch value changed', snap() === $s0);
    // the row's words "Working. Its backup source … is paused (IR-…); nothing in W3 uses it." are the Workflows page's: views: pending
});

sc('lift', 'S-A4', 'after the trip, the ladder\'s check fails: the trip\'s status updated in place, nothing re-sent', ['g2:22', 'g1:13'], function () {
    $k = ccIndexTrip(); $n0 = [count(msgs()), count(mails()), count(pushes())];
    $a0 = alarm($k);
    chk('a trip keeps checks after its tries (§7: the ladder): its next check is set', $a0 && $a0['next_try_at'] !== null, $a0['next_try_at'] ?? null);
    $GLOBALS['called'] = [];
    due($k); tick(); $a = alarm($k);
    chk('guard: the after-trip check called Common Crawl', in_array('svc:commoncrawl', called(), true), called());
    chk('state: still a Trip, its reading the check\'s answer', $a && $a['level'] === 'trip' && str_contains((string) $a['reading'], '504'), [$a['level'] ?? null, $a['reading'] ?? null]);
    chk('hold unchanged', in_array(CC_INDEX, hostHolds(), true));
    chk('messages: nothing new sent (in place)', [count(msgs()), count(mails()), count(pushes())] === $n0, [count(msgs()), count(mails()), count(pushes())]);
    $f = facts($a);
    chk('words: its status names its last check and the next one', str_contains($f['status'], 'next check at') || str_contains($f['status'], 'next at'), $f['status']);
});

sc('lift', 'S-A5', 'Check and start again on the index: its check called, it closes, only the index\'s hold lifts, every channel updated', ['g2:23', 'g2:1', 'g4:7B', 'g4:7P', 'g4:7E', 'g4:7Pu', 'g1:14'], function () {
    $k = ccIndexTrip(); $dk = 'svc:issue:' . svcFail(CC_DATA, 'server', 'crawler', ['status' => 500]);
    $a = alarm($k); $GLOBALS['called'] = [];
    checks(['svc:commoncrawl' => pass('the index answered a real query (a lookup for https://example.org/, 1 record, 412 ms).')]);
    [$ok, $w] = resolveA($a);
    chk('guard: Check and start again called Common Crawl\'s check first (P4; g2 F1: no 5 ms close)', in_array('svc:commoncrawl', called(), true), called());
    chk('state: closed', $ok && alarm($k) === null, [$ok, $w]);
    chk('hold: the index\'s calls are released', !in_array(CC_INDEX, hostHolds(), true), hostHolds());
    chk('no other fault closes unless its own component was checked (row 6): the data host\'s warning stays', alarm($dk) !== null, last($dk)['cleared_how'] ?? null);
    $m = msgs();
    chk('messages: the same thread updated: a close reaches Pulse and the email (P10)', count(array_filter($m, fn ($x) => $x['kind'] === 'alarm.recovered')) === 1 && count(mails()) === 2, [array_column($m, 'kind'), count(mails())]);
    chk('…its subject "…: Recovered" or "…: Fixed by Jason"', (bool) array_filter($m, fn ($x) => $x['kind'] === 'alarm.recovered' && preg_match('/: (Recovered|Fixed by Jason)$/', (string) $x['title'])), array_column($m, 'title'));
    chk('…no new push (a close is a Notice: replaced silently)', count(pushes()) === 1, count(pushes()));
});

sc('lift', 'S-A5b', 'Check and start again with the index still red: stays a trip, saying why, nothing changes', ['g2:23', 'g1:15'], function () {
    $k = ccIndexTrip(); $a = alarm($k); $n0 = count(mails());
    checks(['svc:commoncrawl' => red('index.commoncrawl.org answered 504 Gateway Time-out.')]);
    [$ok, $w] = resolveA($a); $a2 = alarm($k);
    chk('state: still a Trip', !$ok && $a2 && $a2['level'] === 'trip', [$ok, $w]);
    chk('words: "its check is still red" with the answer', str_contains($w, 'still red') && str_contains($w, '504'), $w);
    chk('hold unchanged', in_array(CC_INDEX, hostHolds(), true), hostHolds());
    chk('messages: nothing sent', count(mails()) === $n0);
});

sc('lift', 'S-A6', 'a check closes only the component it checked: Common Crawl\'s data host is its own part', ['g2:17', 'g2:29'], function () {
    $ki = ccIndexDown(); $kd = 'svc:issue:' . svcFail(CC_DATA, 'server', 'crawler', ['status' => 500]);
    $ai = alarm($ki); $ad = alarm($kd);
    chk('the index and the data host are separate components (P2: the part that failed)', ($ai['component'] ?? '') !== ($ad['component'] ?? ''), [$ai['component'] ?? null, $ad['component'] ?? null]);
    checks(['svc:commoncrawl' => pass()]); due($ki, 5); tick();
    chk('the index\'s passing try leaves the data host\'s warning open until its own check', alarm($kd) !== null, last($kd)['cleared_how'] ?? null);
});

sc('lift', 'S-A7', 'a switch a closed trip left off: CS-3\'s three cases (a person\'s setting, Start\'s, none)', ['g3:M1', 'g1:17'], function () {
    $pdo = $GLOBALS['pdo'];
    $off = function (string $t) use ($pdo) { $pdo->prepare("UPDATE crawl_capture_tasks SET enabled = 0, off_why = 'A trip on the Control Board: W3 (the Control Board (a trip))' WHERE task = ?")->execute([$t]);
        $pdo->prepare("INSERT INTO automation_log (at, kind, ref, paused, why, by_uid, by_name) VALUES (UTC_TIMESTAMP(), 'task', ?, 1, 'A trip on the Control Board: W3', NULL, 'the Control Board (a trip)')")->execute([$t]); };
    $pdo->exec("INSERT INTO automation_log (at, kind, ref, paused, why, by_uid, by_name) VALUES (UTC_TIMESTAMP() - INTERVAL 2 DAY, 'task', 'heritage', 0, 'On', 7, 'Jason')"); $off('heritage');   // a person set it on
    $pdo->exec("INSERT INTO automation_log (at, kind, ref, paused, why, by_uid, by_name) VALUES (UTC_TIMESTAMP() - INTERVAL 2 DAY, 'task', 'source', 0, 'Start the system: on', 7, 'Start the system')"); $off('source');   // Start's setting only
    $off('commoncrawl');   // nobody ever set it
    Automation::putBackTripSwitches($pdo);
    $en = fn (string $t) => (int) $pdo->query("SELECT enabled FROM crawl_capture_tasks WHERE task = " . $pdo->quote($t))->fetchColumn();
    chk('a person\'s own setting is put back (CS-3 "Keep for a person")', $en('heritage') === 1, $en('heritage'));
    chk('Start the system\'s setting isn\'t a person\'s: it stays off (ruling 4)', $en('source') === 0, $en('source'));
    chk('nobody\'s: stays Off by the system (left by a closed trip); nothing switched on (NG)', $en('commoncrawl') === 0, $en('commoncrawl'));
    // its words ("Off by the system (left by a closed trip): left off by W3's trip of …") are Paused now's: views: pending
});

sc('lift', 'S-A8', 'a person\'s close names the holds actually lifted, never "Started again: W3" while nothing of W3 was held', ['g1:20', 'g3:C2'], function () {
    $k = ccIndexTrip(); checks(['svc:commoncrawl' => pass()]);
    [$ok, $w] = resolveA(alarm($k));
    chk('closed', $ok, $w);
    chk('words: never "Started again: W3" (only what its hold held)', !str_contains($w, 'Started again: W3'), $w);
});

sc('lift', 'S-A9', 'Switch on a switch left off by a trip: the switch-on check first', ['g3:C9'], function () {
    notHere('Switch on (CS-4) is a view action on the task\'s row and Paused now (the views being rebuilt); its switch-on check is Preflight::run on live lines, which calls outside services. Re-run with the views: pending');
});

sc('lift', 'S-A10', 'any close updates the trip\'s thread on every channel; the bell\'s count drops', ['g4:7P', 'g4:7E', 'g4:6B', 'g4:6P', 'g4:6E', 'g4:6Pu'], function () {
    $k = ccIndexTrip(); checks(['svc:commoncrawl' => pass()]); due($k); tick();
    $c = last($k);
    chk('the system\'s passing check closes it as Recovered', $c && $c['cleared_at'] !== null && str_starts_with((string) $c['cleared_how'], 'Recovered'), $c['cleared_how'] ?? null);
    $m = msgs();
    chk('one message per alarm: the trip and its close are one thread (thing alarm:<id>)', count(array_unique(array_column($m, 'thing'))) === 1, array_column($m, 'thing'));
    chk('the close reached Pulse and the email (a reply), no push', count(array_filter($m, fn ($x) => $x['kind'] === 'alarm.recovered')) === 1 && count(mails()) === 2 && count(pushes()) === 1, [array_column($m, 'kind'), count(mails()), count(pushes())]);
    chk('the bell\'s count drops: no unread trip left for the founder', Notify::unreadForBell($GLOBALS['pdo'], 7) <= 1, Notify::unreadForBell($GLOBALS['pdo'], 7));
});

sc('lift', 'S-A11', 'overture_bulk times out reading our reply: our endpoint is slow, a warning; W1 and its tasks unaffected', ['g1:2', 'g1:8', 'g4:11B'], function () {
    $s0 = snap();
    report(['overture_bulk' => ['failed', "TimeoutError('The read operation timed out') posting 500 rows to /api/crawl/loads", []], 'coverage' => ['done', '']]);
    $a = alarm('task:failed:overture_bulk');
    chk('the fault is classified as our endpoint\'s (P2, Q3, IR-0001)', $a && $a['component'] === 'ep:crawl/loads', $a['component'] ?? null);
    chk('state: a Warning (slow), never a trip', $a && $a['level'] === 'warning', $a['level'] ?? null);
    chk('hold: nothing; W1, its tasks and all gathering unaffected', holds() === [], holds());
    chk('no switch changed', snap() === $s0);
    chk('messages: none (a warning)', quiet(), [count(msgs()), count(mails()), count(pushes())]);
    chk('words: never "the workflow\'s other tasks carry on" (D4)', !str_contains(facts($a)['doing'] . ($a['means'] ?? ''), 'carry on'), facts($a)['doing']);
});

sc('lift', 'S-A12', 'foursquare_bulk the same, 11 min apart: the same alarm at the same component, count 2, no second message', ['g4:11B', 'g4:11P', 'g4:11E', 'g4:11Pu', 'g1:2'], function () {
    report(['foursquare_bulk' => ['failed', "TimeoutError('The read operation timed out')", []]]);
    report(['overture_bulk' => ['failed', "TimeoutError('The read operation timed out')", []]]);
    $open = $GLOBALS['pdo']->query("SELECT akey, component, level, n_times FROM control_alarms WHERE cleared_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);
    chk('one alarm for one component and signature (ep:crawl/loads), seen 2 times', count($open) === 1 && (int) $open[0]['n_times'] === 2, $open);
    chk('no trip, no second W1 pause', !in_array('wf:W1', holds(), true) && !array_filter($open, fn ($x) => $x['level'] === 'trip'), [holds(), $open]);
    chk('no message', quiet());
});

sc('lift', 'S-A13', 'the engine\'s check of /api/crawl/loads passes: Cleared', ['g1:24', 'g1:2'], function () {
    report(['overture_bulk' => ['failed', "TimeoutError('The read operation timed out')", []]]);
    $k = 'task:failed:overture_bulk';
    checks(['ep:crawl/loads' => pass('/api/crawl/loads answered 200 in 400 ms.')]); due($k, 5); tick();
    chk('guard: its check called the endpoint', in_array('ep:crawl/loads', called(), true), called());
    chk('state: Cleared, its proof named', alarm($k) === null && str_contains((string) (last($k)['cleared_how'] ?? ''), 'answered 200'), last($k)['cleared_how'] ?? null);
});

sc('lift', 'S-A14', 'the next night\'s window: the bulk loads run (nothing holds them)', ['g1:2', 'g4:2B'], function () {
    report(['overture_bulk' => ['failed', "TimeoutError('The read operation timed out')", []], 'foursquare_bulk' => ['failed', "TimeoutError('The read operation timed out')", []]]);
    chk('neither bulk task nor W1 is held, so their next window runs', !array_intersect(['task:overture_bulk', 'task:foursquare_bulk', 'wf:W1', 'master:all'], holds()), holds());
    report(['overture_bulk' => ['done', ''], 'foursquare_bulk' => ['done', '']]);
    chk('their run doing its work clears what was open', alarm('task:failed:overture_bulk') === null && alarm('task:failed:foursquare_bulk') === null);
});

sc('lift', 'S-A15', 'Acknowledge a trip: Seen, the light stays red (one count of open trips, seen or not)', ['g4:3B', 'g4:3P', 'g4:3E', 'g4:3Pu'], function () {
    $k = ccIndexTrip(); $a = alarm($k); $n0 = count(mails());
    ControlAlarms::ack($GLOBALS['pdo'], (int) $a['id'], 7, 'Jason');
    $c = ControlAlarms::counts($GLOBALS['pdo']);
    chk('state: still a Trip, Seen by Jason', ($x = alarm($k)) && $x['level'] === 'trip' && $x['ack_name'] === 'Jason', alarm($k));
    chk('the open-trip count still counts it (g4 F1; D5)', (int) ($c['trip'] ?? $c['trips'] ?? 0) === 1, $c);
    chk('hold unchanged', in_array(CC_INDEX, hostHolds(), true));
    chk('messages: nothing sent for a mark', count(mails()) === $n0);
    chk('words: its status says it was seen', str_contains(facts(alarm($k))['status'], 'Acknowledged by Jason') || str_contains(facts(alarm($k))['status'], 'seen by Jason'), facts(alarm($k))['status']);
});
