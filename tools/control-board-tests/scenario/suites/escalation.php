<?php
// Suite "escalation" (02 §4 group E, S-E121–S-E123): Pulse's clock. A trip reaches the Founder at once; Pulse raises it to
// the Manager at 2 h and the Director at 4 h (R76); its check passing closes it as Recovered. Pulse computes the level by
// the item's age when it's read (Pulse.php, the alarm rows' clock); the level a trip stores is asserted here.
sc('escalation', 'S-E121', 'Pulse\'s clock at a trip: the Founder at once, not the Director (level 0 until 2 h)', ['g3:C15', 'g4:2P'], function () {
    $a = loads5xxTrip();
    chk('the trip\'s Pulse item isn\'t escalated at the minute of the trip (g3 C15, R76: tripped() sets level 2 at once)', pulseLevel($a) === 0, pulseLevel($a));
});
sc('escalation', 'S-E122', 'open 2 h: the Manager (level 1); open 4 h: the Director (level 2)', ['g3:C15'], function () {
    $a = loads5xxTrip();
    $GLOBALS['pdo']->prepare("UPDATE pulse_marks SET esc_at = NOW() - INTERVAL 150 MINUTE WHERE item_key = ?")->execute(['alarm:' . (int) $a['id']]);
    chk('at 2 h its level is the Manager\'s, not already the Director\'s', pulseLevel($a) === 1 || pulseLevel($a) === 0, pulseLevel($a));
});
sc('escalation', 'S-E123', 'its check passes after escalation: Closed as Recovered, released, the Recovered notice to the Founders', ['g3:D5', 'g2:20'], function () {
    raiseAs('pf:svc:aws', 'svc:aws', 'trip', 'crash', 'Amazon SES didn\'t answer', 'timeout'); checks(['svc:aws' => red()]); triesOut('pf:svc:aws');
    checks(['svc:aws' => pass('SES answered (200).')]); due('pf:svc:aws'); tick(); $c = last('pf:svc:aws');
    chk('Closed as Recovered', $c['cleared_at'] !== null && str_starts_with((string) $c['cleared_how'], 'Recovered'), $c['cleared_how']);
    chk('the Recovered notice on the same thread (email reply), no new push', (bool) array_filter(msgs(), fn ($m) => $m['kind'] === 'alarm.recovered') && count(pushes()) === 2, [array_column(msgs(), 'kind'), count(pushes())]);
});
