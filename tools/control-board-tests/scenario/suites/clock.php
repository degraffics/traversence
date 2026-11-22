<?php
// Suite "clock" (02 §4 group C, S-C83–S-C90): the diagnostic clock, controlled: tries at about 5, 15 and 35 minutes inside
// the hour (ruling 2), the hour, the after-trip ladder; a scheduled run is never a try (P3); no trip on the clock alone (P4).
sc('clock', 'S-C83', 'a try fails inside the hour: a Warning, n+1 of 3, the next at 15 then 35 min', ['g1:1', 'g1:13', 'g2:16', 'g3:J1', 'g4:1B'], function () {
    ledger('crawl/verify', 500); checks(['ep:crawl/verify' => red('/api/crawl/verify answered 500.')]);
    $k = 'ep:crawl/verify:5xx'; $a0 = alarm($k);
    due($k, 5); tick(); $a = alarm($k);
    chk('try 1 at 5 min fails: a Warning, try_n 1, its next at first + 15 min', $a && $a['level'] === 'warning' && (int) $a['try_n'] === 1 && abs(strtotime($a['next_try_at'] . ' UTC') - strtotime($a['first_at'] . ' UTC') - 900) < 5, $a);
    due($k, 10); tick(); $a = alarm($k);
    chk('try 2 at 15 min fails: try_n 2, its next at first + 35 min', $a && (int) $a['try_n'] === 2 && abs(strtotime($a['next_try_at'] . ' UTC') - strtotime($a['first_at'] . ' UTC') - 2100) < 5, $a);
    chk('guard: each try was the endpoint\'s own check', count(array_filter(called(), fn ($c) => $c === 'ep:crawl/verify')) === 2, called());
    chk('words: its reading the try\'s answer, its status n of 3', str_contains((string) $a['reading'], 'Try 2') && str_contains(facts($a)['status'], '2 of 3'), [$a['reading'], facts($a)['status']]);
    chk('a repeat report between tries isn\'t a try (P3)', (function () use ($k) { ledger('crawl/verify', 500); ledger('crawl/verify', 500); return (int) alarm($k)['try_n'] === 2; })(), alarm($k)['try_n']);
    chk('messages: none while it tries', quiet());
});
sc('clock', 'S-C84', 'a try passes: Cleared, its proof named', ['g1:14', 'g2:21'], function () {
    ledger('crawl/verify', 500); checks(['ep:crawl/verify' => pass('/api/crawl/verify answered 200 in 140 ms.')]); due('ep:crawl/verify:5xx', 5); tick();
    $c = last('ep:crawl/verify:5xx');
    chk('Cleared, saying which try and its proof', $c['cleared_at'] !== null && str_contains((string) $c['cleared_how'], 'Its check passed') && str_contains((string) $c['cleared_how'], 'answered 200'), $c['cleared_how']);
    chk('messages: none (a warning\'s close)', quiet());
});
sc('clock', 'S-C85', 'the third try fails, or the hour ends after a failed try: a Trip with its radius', ['g1:2', 'g3:C1', 'g3:C4', 'g3:J12'], function () {
    ledger('crawl/verify', 500); checks(['ep:crawl/verify' => red()]); due('ep:crawl/verify:5xx', 5); tick();
    due('ep:crawl/verify:5xx', 60); tick(); $a = alarm('ep:crawl/verify:5xx');
    chk('after a failed try with the hour out: a Trip', $a && $a['level'] === 'trip', $a['level'] ?? null);
    chk('its radius: only second_look (the task that calls /api/crawl/verify)', holds() === ['task:second_look'], holds());
});
sc('clock', 'S-C86', 'the hour ends with no try made (the check couldn\'t run): never a Trip on the clock alone', ['g1:2', 'g3:J1', 'g3:C1', 'g3:D3', 'g3:J12'], function () {
    ledger('crawl/verify', 500);
    $GLOBALS['checks']['ep:crawl/verify'] = ['pass' => null, 'proof' => 'its host is down: no check could run'];
    $GLOBALS['pdo']->exec("UPDATE control_alarms SET first_at = first_at - INTERVAL 70 MINUTE, next_try_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND"); tick();
    $a = alarm('ep:crawl/verify:5xx');
    chk('no trip with no try made (P4)', $a && $a['level'] === 'warning', $a['level'] ?? null);
    chk('…it says why and keeps a next check (it never stops trying)', $a && ($a['next_try_at'] !== null) && str_contains(facts($a)['status'] . $a['reading'], 'No check could run'), [$a['next_try_at'] ?? null, facts($a)['status'] ?? null]);
});
sc('clock', 'S-C87', 'a critical part\'s first failure: a Trip at once, still trying; nothing held', ['g3:D1', 'g3:C10', 'g2:20', 'g4:4B', 'g4:4P', 'g4:4E', 'g4:4Pu'], function () {
    raiseAs('pf:svc:aws', 'svc:aws', 'trip', 'crash', 'Amazon SES didn\'t answer', 'timeout'); $a = alarm('pf:svc:aws');
    chk('a Trip at once, still trying, its next try set', $a && $a['level'] === 'trip' && $a['retrying_at'] !== null && $a['next_try_at'] !== null, $a);
    chk('nothing held', holds() === []);
    chk('words: "Down: the system is still trying, n of 3, next at …"', str_contains(facts($a)['status'], 'Down: the system is still trying') && str_contains(title($a), 'Down: the system is still trying'), [facts($a)['status'], title($a)]);
    chk('messages: one at once', msgN() === [1, 1, 1], msgN());
});
sc('clock', 'S-C88', 'its tries run out: Escalated, the Escalation Notice on the same thread, one push', ['g3:D4', 'g4:5B', 'g4:5P', 'g4:5E', 'g4:5Pu', 'g3:C4'], function () {
    raiseAs('pf:svc:aws', 'svc:aws', 'trip', 'crash', 'Amazon SES didn\'t answer', 'timeout'); checks(['svc:aws' => red('SES didn\'t answer.')]); triesOut('pf:svc:aws');
    $a = alarm('pf:svc:aws'); $m = msgs();
    chk('Escalated after three real tries (try_n 3)', $a && $a['retrying_at'] === null && (int) $a['try_n'] === 3, $a);
    chk('the Escalation Notice: the same thread raised (one thing), an email reply and one more push', count(array_unique(array_column($m, 'thing'))) === 1 && count(array_filter($m, fn ($x) => $x['kind'] === 'alarm.escalated')) === 1 && count(pushes()) === 2 && count(mails()) === 2, [array_column($m, 'kind'), count(pushes()), count(mails())]);
    chk('its words: "Retries unsuccessful, now admin required"', (bool) array_filter($m, fn ($x) => str_ends_with((string) $x['title'], 'Retries unsuccessful, now admin required')), array_column($m, 'title'));
    chk('no "(N of these are waiting.)" in an in-place thread\'s email (g4 F8)', !array_filter(mails(), fn ($x) => str_contains((string) $x[2], 'of these are waiting')), array_map(fn ($x) => mb_substr((string) $x[2], 0, 120), mails()));
});
sc('clock', 'S-C89', 'its after-trip checks keep failing: still a Trip, its status updated in place', ['g2:22'], function () {
    $id = svcFail('api.weather.gov', 'server', 'site', ['status' => 503]); $k = 'svc:issue:' . $id; checks(['svc:nws' => red('api.weather.gov answered 503.'), 'svc:weather' => red()]);
    $comp = (string) alarm($k)['component']; checks([$comp => red('api.weather.gov answered 503.')]); triesOut($k);
    $n0 = msgN(); due($k); tick(); due($k); tick(); $a = alarm($k);
    chk('still a Trip with a next check (the ladder)', $a && $a['level'] === 'trip' && $a['next_try_at'] !== null, $a);
    chk('nothing re-sent', msgN() === $n0, [msgN(), $n0]);
    chk('guard: the after-trip checks called it', count(array_filter(called(), fn ($c) => $c === $comp)) >= 5, called());
});
sc('clock', 'S-C90', 'an after-trip check passes: Closed as Recovered; dependants back on their first choice', ['g2:20', 'g2:21', 'g2:22', 'g3:D5', 'g4:6B', 'g4:6P', 'g4:6E', 'g4:6Pu'], function () {
    $id = svcFail('api.tavily.com', 'server', 'crawler', ['status' => 502]); $k = 'svc:issue:' . $id; checks(['svc:tavily' => red()]); triesOut($k);
    checks(['svc:tavily' => pass('api.tavily.com answered a search (200).')]); due($k); tick(); $c = last($k);
    chk('Closed', $c['cleared_at'] !== null, $c);
    chk('as Recovered, the system credited only where it fixed it (g2 row 20: "Resolved by the system")', str_starts_with((string) $c['cleared_how'], 'Recovered'), $c['cleared_how']);
    chk('released: its host\'s calls and its tasks', !in_array('api.tavily.com', hostHolds(), true) && !array_filter(holds(), fn ($h) => str_starts_with($h, 'task:')), [hostHolds(), holds()]);
    chk('the close reached the thread: Pulse and an email reply, no push', count(array_filter(msgs(), fn ($m) => $m['kind'] === 'alarm.recovered')) === 1 && count(mails()) === 2 && count(pushes()) === 1, [array_column(msgs(), 'kind'), count(mails()), count(pushes())]);
    chk('…its subject "…: Recovered"', (bool) array_filter(msgs(), fn ($m) => str_ends_with((string) $m['title'], ': Recovered')), array_column(msgs(), 'title'));
});
