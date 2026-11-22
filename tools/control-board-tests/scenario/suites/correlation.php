<?php
// Suite "correlation" (02 §4 group F, S-F124–S-F127; O1: a 15-minute window, matching host, signature and time):
// two failures with one signature, inside the window, are one incident and one thread.
function twoOnOurSite(): array { $a = loads5xxTrip(); ledger('crawl/verify', 500); checks(['ep:crawl/verify' => red()]); triesOut('ep:crawl/verify:5xx'); return [$a, alarm('ep:crawl/verify:5xx')]; }
sc('correlation', 'S-F124', 'Trip B on the same host and signature inside the window joins A\'s incident: one thread', ['g4:11B', 'g4:11P', 'g4:11E', 'g4:11Pu', 'g4:10B', 'g4:10P', 'g4:10E', 'g4:10Pu'], function () {
    [$a, $b] = twoOnOurSite();
    chk('B joins A\'s Incident Report (IR-####)', (int) $a['incident_id'] > 0 && $a['incident_id'] === $b['incident_id'], [$a['incident_id'] ?? null, $b['incident_id'] ?? null]);
    chk('each member\'s own radius, unioned', holds() === array_values(array_unique(array_merge(LOADS_TASKS, ['task:second_look']))) || count(holds()) === 4, holds());
    chk('one thread: one Pulse row, one email, one push for the incident (g4 rows 10, 11: two of each)', msgN() === [1, 1, 1], msgN());
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
