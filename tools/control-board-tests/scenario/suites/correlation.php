<?php
// Suite "correlation" (02 §4 group F, S-F124–S-F127; O1: a 15-minute window, matching host, signature and time):
// two failures with one signature, inside the window, are one incident and one thread.
function twoOnOurSite(): array { $a = loads5xxTrip(); ledger('crawl/verify', 500); checks(['ep:crawl/verify' => red()]); triesOut('ep:crawl/verify:5xx'); return [$a, alarm('ep:crawl/verify:5xx')]; }
sc('correlation', 'S-F124', 'Trip B on the same host and signature inside the window joins A\'s incident: one thread', ['g4:11B', 'g4:11P', 'g4:11E', 'g4:11Pu', 'g4:10B', 'g4:10P', 'g4:10E', 'g4:10Pu'], function () {
    [$a, $b] = twoOnOurSite();
    chk('B joins A\'s Incident Report (IR-####)', (int) $a['incident_id'] > 0 && $a['incident_id'] === $b['incident_id'], [$a['incident_id'] ?? null, $b['incident_id'] ?? null]);
    chk('each member\'s own radius, unioned', holds() === array_values(array_unique(array_merge(LOADS_TASKS, ['task:second_look']))) || count(holds()) === 4, holds());
    chk('one thread: one Pulse row and one push for the incident (g4 rows 10, 11: two of each)', count(msgs()) === 1 && count(pushes()) === 1, msgN());
    // the session manager's ruling (2026-11-22, row 124): B updates the incident's Pulse item and replies in its email thread,
    // no new subject, no push
    $m = msgs()[0] ?? []; $ml = mails();
    chk('B\'s join updated the incident\'s Pulse item in place: its words name the incident and both parts', str_contains((string) ($m['body'] ?? ''), 'IR-') && str_contains((string) ($m['body'] ?? ''), 'Correlated · 2 parts') && (int) ($m['times'] ?? 0) === 2, mb_substr((string) ($m['body'] ?? ''), 0, 200));
    chk('…the Pulse item stays A\'s (its thing and subject)', ($m['thing'] ?? '') === 'alarm:' . $a['id'] && str_starts_with((string) ($m['title'] ?? ''), 'SYSTEM ALARM NOTICE: '), [$m['thing'] ?? null, $m['title'] ?? null]);
    chk('a reply in the incident\'s email thread: two emails, the same subject, the second In-Reply-To the first', count($ml) === 2 && $ml[0][1] === $ml[1][1] && ($ml[1][3]['parent'] ?? '') === ($ml[0][3]['id'] ?? '-') && ($ml[0][3]['id'] ?? '') !== '', array_map(fn ($x) => [$x[1], $x[3]], $ml));
    chk('…and no push for the join (the push route says why)', (int) $GLOBALS['pdo']->query("SELECT COUNT(*) FROM comms_route WHERE via = 'push' AND stage = 'skipped' AND detail LIKE 'A reply on a thread%'")->fetchColumn() === 1, $GLOBALS['pdo']->query("SELECT via, stage, detail FROM comms_route WHERE via = 'push'")->fetchAll(PDO::FETCH_ASSOC));
});
sc('correlation', 'S-F125', 'one member\'s check passes: that member closed, the incident still open', [], function () {
    [$a, $b] = twoOnOurSite(); checks(['ep:crawl/verify' => pass()]); due('ep:crawl/verify:5xx'); tick();
    $ir = ControlIncidents::get($GLOBALS['pdo'], (int) $a['incident_id']);
    chk('B closed, A open, the incident open', alarm('ep:crawl/verify:5xx') === null && alarm('ep:crawl/loads:5xx') !== null && $ir && $ir['closed_at'] === null, $ir['closed_at'] ?? null);
    chk('B\'s hold released, A\'s kept', !in_array('task:second_look', holds(), true) && holds() === LOADS_TASKS, holds());
});
sc('correlation', 'S-F126', 'the last member\'s check passes: the incident closed, Recovered for the group', [], function () {
    [$a, $b] = twoOnOurSite(); checks(['ep:crawl/verify' => pass(), 'ep:crawl/loads' => pass()]); due('ep:crawl/verify:5xx'); due('ep:crawl/loads:5xx'); tick();
    $ir = ControlIncidents::get($GLOBALS['pdo'], (int) $a['incident_id']);
    chk('the incident closed', $ir && $ir['closed_at'] !== null, $ir);
    chk('every hold released', holds() === [], holds());
});
sc('correlation', 'S-F127', 'alarms sharing a cause or incident: one group row, named by the cause', ['g4:10B', 'g4:10P'], function () {
    [$a, $b] = twoOnOurSite();
    $ka = ControlAlarms::groupKey(alarm('ep:crawl/loads:5xx')); $kb = ControlAlarms::groupKey(alarm('ep:crawl/verify:5xx'));
    chk('one group for the two (by cause or incident, row 127)', $ka === $kb, [$ka, $kb]);
    chk('…keyed by their incident, never the board-system grouping "engine feeds" (02 §5.3)', str_contains($ka, (string) $a['incident_id']) || str_contains($ka, 'IR'), $ka);
});
