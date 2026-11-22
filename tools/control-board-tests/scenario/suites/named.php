<?php
// The named case, end to end (step3/workflows-page.md §8, from 03 §28.1; handoffs/control-board-build.md §5): Common Crawl's
// index down holds only W3's per-site copy. Its six steps: the fault; what it holds; the system tries (a scheduled W3 run
// is not a try); its card (their trouble: no Fix it, R118); Recovered; the switches (nothing to restore).
sc('named', 'NAMED', 'Common Crawl\'s index down holds only W3\'s per-site copy (workflows-page §8, six steps)', ['g1:13', 'g2:17', 'g2:22', 'g2:29', 'g1:20', 'g3:C2'], function () {
    $s0 = snap();
    // 1. the fault: index.commoncrawl.org answers 504 (issue #35's cause)
    $k = ccIndexDown(); $a = alarm($k);
    chk('1. the fault: a warning on Common Crawl\'s index, trying again (1 of 3)', $a && $a['level'] === 'warning' && $a['next_try_at'] !== null, $a);
    // 3. the system tries; a scheduled W3 run between its tries isn't a try, and its bulk reads at the data host carry on
    checks(['svc:commoncrawl' => red('index.commoncrawl.org answered 504 Gateway Time-out.')]);
    due($k, 5); tick();
    report(['commoncrawl' => ['done', 'bulk reads from data.commoncrawl.org', [CC_DATA => [40, 0, '']]], 'source' => ['done', '']]);
    chk('3. a scheduled W3 run is not a try (try_n stays 1)', (int) alarm($k)['try_n'] === 1, alarm($k)['try_n']);
    chk('3. …nor does the data host\'s success close the index\'s fault (a check closes only its own component)', alarm($k) !== null, last($k)['cleared_how'] ?? null);
    due($k, 10); tick(); due($k, 20); tick(); $a = alarm($k);
    chk('3. its third failed try: a Trip', $a && $a['level'] === 'trip', $a['level'] ?? null);
    // 2. what it holds: calls to the index host only; W3's bulk task (data host), W3 and W2 carry on
    chk('2. held: calls to index.commoncrawl.org (the per-site copy\'s fallback, and W2\'s re-read fallback)', in_array(CC_INDEX, hostHolds(), true), hostHolds());
    chk('2. W3\'s bulk reads (commoncrawl, at the data host) carry on: not held', !in_array('task:commoncrawl', holds(), true), holds());
    chk('2. W3 and W2 not held whole; nothing else held', !array_intersect(['wf:W3', 'wf:W2', 'master:all'], holds()), holds());
    // 4. its card: their trouble, no Fix it; Check it now and Pause it
    $f = facts($a);
    chk('4. no Fix it (a 504 is their trouble; R118 wins)', $f['fix'] === null && $f['theirs'] === true, [$f['fix'], $f['theirs']]);
    // 5. Recovered: its check passes, the pause lifts, the thread closes as Recovered
    checks(['svc:commoncrawl' => pass('the index answered a real query (a lookup for https://example.org/, 1 record, 412 ms).')]);
    due($k); tick(); $c = last($k);
    chk('5. closed as "Recovered: Common Crawl\'s index was down <n> min, the system fixed it."', $c['cleared_at'] !== null && (bool) preg_match('/^Recovered: .*was down \d+ min, the system fixed it/', (string) $c['cleared_how']), $c['cleared_how']);
    chk('5. the hold lifted', !in_array(CC_INDEX, hostHolds(), true) && holds() === [], [hostHolds(), holds()]);
    chk('5. a notice with nothing to press: the thread closed (Pulse, email), no new push', count(array_filter(msgs(), fn ($m) => $m['kind'] === 'alarm.recovered')) === 1 && count(pushes()) === 1, [array_column(msgs(), 'kind'), count(pushes())]);
    // 6. the switches: a trip's pause sits beside them, so nothing was written and nothing needs restoring
    chk('6. no switch was written at any step (CS-1)', snap() === $s0);
});
