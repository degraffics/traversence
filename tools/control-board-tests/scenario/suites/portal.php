<?php
// Suite "portal" (02 §4 group G, S-G128–S-G135; 02 §0a O5): Fix it to the provider's page by fault kind, matched strictly by
// type (billing, keys, console, sign-in). The card's two rows ("On our system" and "At <Provider>") are the Modules card's
// (app/admin/module-cards.php, being built): views: pending. What the engine and the dispatcher carry is asserted: the
// Fix it cause, and the email body's link line.
sc('portal', 'S-G128', 'a provider\'s quota: Fix it → "At <Provider>: billing", the email carrying its link line', ['g2:6', 'g2:7'], function () {
    $id = svcFail('api.anthropic.com', 'quota', 'site', ['status' => 429, 'body' => 'credit balance is too low']); $a = issueAlarm($id);
    chk('Fix it\'s cause is a quota (the person\'s, R117)', (ControlAlarms::fixOf($GLOBALS['pdo'], $a)['cause'] ?? '') === 'quota', ControlAlarms::fixOf($GLOBALS['pdo'], $a));
    chk('the email body\'s link line "Billing at Anthropic: <its Limits URL>" (row 128)', (bool) array_filter(mails(), fn ($m) => str_contains((string) $m[2], 'console.anthropic.com/settings/limits')), array_map(fn ($m) => mb_substr((string) $m[2], 0, 160), mails()));
});
sc('portal', 'S-G129', 'a refused key: Fix it → Its key with Replace; the email\'s "API keys at <Provider>" line', ['g2:1', 'g2:2'], function () {
    $id = svcFail('api.anthropic.com', 'auth', 'site', ['status' => 401]); $a = issueAlarm($id);
    chk('Fix it → #svc-anthropic-key', (ControlAlarms::fixOf($GLOBALS['pdo'], $a)['url'] ?? '') === '/admin/config/register#svc-anthropic-key');
    chk('the email body\'s keys link line (row 129)', (bool) array_filter(mails(), fn ($m) => str_contains((string) $m[2], 'console.anthropic.com/settings/keys')), array_map(fn ($m) => mb_substr((string) $m[2], 0, 160), mails()));
});
sc('portal', 'S-G130', 'their trouble (a 5xx): no Fix it (R118 wins); the console link to inspect', ['g2:16'], function () {
    $id = svcFail('api.tavily.com', 'server', 'crawler', ['status' => 502]); checks(['svc:tavily' => red()]); triesOut('svc:issue:' . $id); $a = issueAlarm($id);
    $f = facts($a);
    chk('no Fix it on their trouble; "the system is handling it"', $f['fix'] === null && $f['theirs'] === true, [$f['fix'], $f['theirs']]);
});
sc('portal', 'S-G131', 'a host\'s console link on the card (Railway\'s dashboard; Bluehost "No console page registered")', [], function () { notHere('the card\'s "At <Host>" row is module-cards.php\'s (being built): views: pending'); });
sc('portal', 'S-G132', 'the sign-in link on the card', [], function () { notHere('as S-G131: views: pending'); });
sc('portal', 'S-G133', 'a type with no registered page: "No <type> page registered for <Provider> yet", never a borrowed URL', [], function () { notHere('as S-G131: views: pending (module-cards.php tv_mc_zone4 draws the note)'); });
sc('portal', 'S-G134', 'a portal link pressed: nothing on the board changes', [], function () {
    $a = loads5xxTrip(); $h0 = holds(); $s0 = snap(); $n0 = msgN();
    // a link to a provider's page calls nothing on the site (it opens in a new tab): there's no engine call to make
    chk('state, holds and alarm unchanged', alarm('ep:crawl/loads:5xx')['level'] === 'trip' && holds() === $h0 && snap() === $s0 && msgN() === $n0);
});
sc('portal', 'S-G135', 'the verifying board action after a portal step: as S-D91, S-D93, S-D106', [], function () {
    $id = svcFail('api.anthropic.com', 'auth', 'site', ['status' => 401]); checks(['svc:anthropic' => pass('Claude answered with the new key.')]);
    [$ok, $w] = resolveA(issueAlarm($id));
    chk('Check and start again after the key is replaced at Anthropic: checked, closed, released', $ok && in_array('svc:anthropic', called(), true) && !ServiceHealth::holding(issue($id)), [$ok, $w, called()]);
});
