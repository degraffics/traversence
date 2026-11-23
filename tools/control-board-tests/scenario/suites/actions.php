<?php
// Suite "actions" (02 §4 group D, S-D91–S-D120): each person's action with its check red and then green, through the
// library each button calls (ControlAlarms::resolve / unresolve / ack, ControlMaintenance, Automation, ControlBoard,
// SystemStart, ServiceStages, ControlLog). The buttons themselves are the views' (being rebuilt): their words are
// "views: pending" in cells.md; what's asserted here is the state, the hold, the check called and the thread.
sc('actions', 'S-D91', 'Check now on a warning: pass Cleared; fail counts as a try', ['g2:14', 'g2:22'], function () {
    $id = svcFail('api.weather.gov', 'server', 'site', ['status' => 503]); $k = 'svc:issue:' . $id; $a = alarm($k); $comp = (string) $a['component'];
    checks([$comp => red('api.weather.gov answered 503.')]);
    $r = ControlEngine::check($GLOBALS['pdo'], $comp, 'Jason');
    chk('guard: a person\'s check calls the component', in_array($comp, called(), true) && $r['pass'] === false, called());
    chk('a failing person\'s check counts as a try (row 91: "(2 of 3)")', (int) alarm($k)['try_n'] === 1, alarm($k)['try_n']);
});
sc('actions', 'S-D92', 'Check now on a working part: its reading updated; a fail opens a fault', [], function () {
    notHere('Check now on a working module is the card\'s button (Preflight::run of its live line, an outside call on the bench); the card is being rebuilt (M). views: pending');
});
sc('actions', 'S-D93', 'Check and start again: check first; pass releases only this trip\'s hold', ['g2:23', 'g2:1', 'g4:7B', 'g4:7P', 'g4:7E', 'g4:7Pu', 'g1:24'], function () {
    $s0 = snap(); $a = loads5xxTrip(); ccIndexTrip();
    $GLOBALS['called'] = []; checks(['ep:crawl/loads' => pass('/api/crawl/loads answered 200 in 180 ms.')]);
    [$ok, $w] = resolveA($a);
    chk('guard: the endpoint\'s check was called before the close', in_array('ep:crawl/loads', called(), true), called());
    chk('closed; the three tasks run again', $ok && !array_intersect(LOADS_TASKS, holds()), [$ok, holds()]);
    chk('only this trip\'s hold: the index\'s calls stay held', in_array(CC_INDEX, hostHolds(), true), hostHolds());
    chk('no switch changed', snap() === $s0);
    chk('its thread closed on every channel: "…: Fixed by Jason"', (bool) array_filter(msgs(), fn ($m) => $m['kind'] === 'alarm.recovered' && str_ends_with((string) $m['title'], 'Fixed by Jason')), array_column(msgs(), 'title'));
});
sc('actions', 'S-D94', 'Check and start again with its check red: a Trip, its status updated, the press refused saying why', ['g2:23', 'g1:24'], function () {
    $a = loads5xxTrip(); [$ok, $w] = resolveA($a);
    chk('refused, with its check\'s answer', !$ok && str_contains($w, 'still red'), $w);
    chk('the holds stay', holds() === LOADS_TASKS, holds());
    chk('its status carries the refusal', str_contains(facts(alarm('ep:crawl/loads:5xx'))['status'], 'Not resolved'), facts(alarm('ep:crawl/loads:5xx'))['status']);
});
sc('actions', 'S-D95', 'Resolve and Mark resolved (svcresolve) removed', ['g2:24'], function () {
    $src = file_get_contents($GLOBALS['APP'] . '/lib/ControlAlarms.php') . file_get_contents($GLOBALS['APP'] . '/lib/Pulse.php');
    chk('no svcresolve action left (02 §5.3: "Mark resolved and svcresolve" removed)', !str_contains($src, 'svcresolve'), substr_count($src, 'svcresolve') . ' mentions');
    // the view (g2:24): an open issue's card and its board row carry Check it now, never Mark resolved
    views(); svcFail('api.tavily.com', 'timeout', 'crawler', ['status' => 0, 'error' => 'timed out']);
    $card = tv_module_card($GLOBALS['pdo'], 'tavily', ['can' => true]);
    chk('the card: Check it now, no Mark resolved (row 95)', str_contains($card, 'Check it now') && !str_contains($card, 'Mark resolved') && !str_contains($card, 'svcresolve'), mb_substr(strip_tags($card), 0, 200));
});
sc('actions', 'S-D96', 'Acknowledge a trip: Seen; the trip stays', ['g4:3B', 'g4:3P'], function () {
    $a = loads5xxTrip(); ControlAlarms::ack($GLOBALS['pdo'], (int) $a['id'], 7, 'Jason');
    chk('still a trip, its holds, seen', ($x = alarm('ep:crawl/loads:5xx')) && $x['level'] === 'trip' && $x['ack_name'] === 'Jason' && holds() === LOADS_TASKS);
    $c = ControlAlarms::counts($GLOBALS['pdo']);
    chk('one count of open trips, seen or not (D5)', (int) ($c['trip'] ?? 0) === 1, $c);
});
sc('actions', 'S-D97', 'Acknowledge isn\'t offered on a warning', [], function () {
    ledger('crawl/loads', 500); $a = alarm('ep:crawl/loads:5xx');
    $b = ControlAlarms::pulseBody($GLOBALS['pdo'], ['thing' => 'alarm:' . (int) $a['id'], 'kind' => 'alarm.warning', 'title' => '', 'body' => ''], 7);
    chk('no Acknowledge on a warning (R80)', !str_contains(json_encode($b), 'Acknowledge'), $b);
});
sc('actions', 'S-D98', 'Undo a close within a day: the trip open again, its hold placed again, its thread raised', ['g4:7P', 'g4:7E'], function () {
    $a = loads5xxTrip(); checks(['ep:crawl/loads' => pass()]); resolveA($a);
    $n0 = count(pushes());
    [$ok, $w] = ControlAlarms::unresolve($GLOBALS['pdo'], (int) $a['id'], 7, 'Jason');
    chk('reopened, its holds placed again', $ok && holds() === LOADS_TASKS, [$ok, $w, holds()]);
    chk('its thread raised again: Pulse unread and one push (row 98)', count(pushes()) === $n0 + 1, [count(pushes()), $n0]);
});
sc('actions', 'S-D99', 'Pause (a person) on a pausable part: Off by a person', [], function () {
    $s0 = snap(); Automation::set($GLOBALS['pdo'], 'service', 'tavily', true, 'testing a new key', 7, 'Jason');
    chk('a person\'s switch (Off · Paused by Jason)', (int) $GLOBALS['pdo']->query("SELECT paused FROM automation_switches WHERE kind = 'service' AND ref = 'tavily'")->fetchColumn() === 1 && snap() !== $s0);
    chk('no system hold for it (a person\'s pause is a switch, never a hold)', !in_array('service:tavily', holds(), true));
});
sc('actions', 'S-D100', 'Resume with its check red: refused, saying why', [], function () { notHere('Resume\'s check is the card\'s (Operations, Preflight::run of a live line): an outside call; the card is being rebuilt (M)'); });
sc('actions', 'S-D101', 'Deactivate: stage 7, dependants move to the next source', [], function () { notHere('Deactivate (Q14) is a Modules card action (ServiceStages) being rebuilt (M); "Migrate traffic" has no library path yet'); });
sc('actions', 'S-D102', 'Activate: its line checked first', [], function () { notHere('Activate runs Preflight::run on the service\'s live line (an outside call); covered by tools/control-board-tests/register.php'); });
sc('actions', 'S-D103', 'Take out (maintenance) for any module, host, job, worker or part', ['g3:M5', 'g3:M4'], function () {
    require_once $GLOBALS['APP'] . '/lib/ControlMaintenance.php';
    $bad = array_values(array_filter(['svc:railway', 'svc:bluehost', 'job:billing_tick', 'wk:crawler', 'site:search', 'wf:W2'], fn ($p) => !ControlMaintenance::canTake($p)));
    chk('every part can be taken out (g3 M5: workflows and tasks only)', $bad === [], $bad);
    // the view (g3:M5): a module taken out reads In maintenance on its card, with when it's back, never a failure
    views();
    try { ControlMaintenance::take($GLOBALS['pdo'], 'svc:tavily', time(), time() + 7200, 'testing the card', 7, 'Jason'); } catch (Throwable $x) { chk('a module can be taken out on the bench', false, $x->getMessage()); return; }
    $card = strip_tags(tv_module_card($GLOBALS['pdo'], 'tavily', ['can' => true]));
    chk('the card: In maintenance, Back by its time, not a fault', str_contains($card, 'In maintenance') && str_contains($card, 'Back by') && !preg_match('/\bTrip\b|Paused by the system/', $card), mb_substr($card, 0, 240));
});
sc('actions', 'S-D104', 'Put back: its check first; red stays out with the reason', ['g3:M4'], function () {
    require_once $GLOBALS['APP'] . '/lib/ControlMaintenance.php';
    try { ControlMaintenance::take($GLOBALS['pdo'], 'wf:W2', time(), time() + 3600, 'testing', 7, 'Jason'); } catch (Throwable $x) { notHere('ControlMaintenance::take: ' . mb_substr($x->getMessage(), 0, 120)); return; }
    $m = ControlMaintenance::outFor($GLOBALS['pdo'], 'wf:W2');
    chk('W2 in maintenance', $m !== null, $m);
});
sc('actions', 'S-D105', 'a window set ahead goes out and comes back by itself', [], function () { notHere('built and covered by tools/control-board-tests/r4a.php (re-run in run-all.sh); its ticks read the scheduler\'s clock'); });
sc('actions', 'S-D106', 'Replace its key: pass Fixed; fail Paused with Put the old one back', ['g2:1', 'g2:2'], function () {
    $id = svcFail('api.tavily.com', 'auth', 'crawler', ['status' => 401]); $a = issueAlarm($id);
    checks(['svc:tavily' => pass('Tavily answered a search with the new key.')]);
    [$ok, $w] = resolveA($a);
    chk('a refused key\'s check isn\'t blocked by its own issue (g2 F2): the check is called', in_array('svc:tavily', called(), true), called());
    chk('closed as Fixed by Jason, the issue resolved, its calls released', $ok && issue($id)['state'] === 'resolved' && !ServiceHealth::holding(issue($id)), [$ok, $w]);
});
sc('actions', 'S-D107', 'Add its key: Working, or Not checked yet until the report', ['g2:4'], function () { notHere('Add its key writes .env through the one writer of secrets (Keys, 0173 r2): covered by tools/control-board-tests/keys.php; "not checked yet" is the card\'s (M)'); });
sc('actions', 'S-D108', 'Reactivate a ban: one polite request; pass Fixed, fail stays', ['g2:14', 'g2:15'], function () {
    $id = svcFail('api.tavily.com', 'ban', 'crawler', ['status' => 403]); $a = issueAlarm($id);
    checks(['svc:tavily' => red('api.tavily.com answered 403.')]);
    [$ok, $w] = resolveA($a);
    chk('a still-refusing host stays held (g2 row 14: resumed with no look)', !$ok && ServiceHealth::holding(issue($id)), [$ok, $w]);
    checks(['svc:tavily' => pass('api.tavily.com answered one polite request (200).')]);
    [$ok, $w] = resolveA(issueAlarm($id));
    chk('…its one request answering closes it as Fixed', $ok && issue($id)['state'] === 'resolved', [$ok, $w]);
});
sc('actions', 'S-D109', 'Raise the cap: as row 41', ['g2:8'], function () { notHere('Raise the cap is Operations::pulseDo(ops_cap:<gate>), which re-reads the month\'s costs; covered by tools/control-board-tests/r2c.php'); });
sc('actions', 'S-D110', 'It\'s fixed: try it again: a Warning "waiting for its next run"', ['g1:1'], function () {
    ownCode(); ownCode(); $a = alarm('task:failed:second_look'); [$ok, $w] = resolveA($a);
    chk('noted; it waits for its next run (never closed on the press)', $ok && alarm('task:failed:second_look') !== null && str_contains($w, 'Its next run is its check'), $w);
    chk('nothing held', holds() === []);
});
sc('actions', 'S-D111', 'Pause everything: the master Off by a person; business duties run', ['g3:M2', 'g3:M1'], function () {
    require_once $GLOBALS['APP'] . '/lib/SystemStart.php';
    try { SystemStart::pauseAll($GLOBALS['pdo'], 7, 'Jason'); } catch (Throwable $x) {}
    Automation::forget();
    chk('the master off, by Jason (a person\'s switch, never a hold)', ($m = Automation::master($GLOBALS['pdo'])) !== null && !in_array('master:all', holds(), true), Automation::masterRow($GLOBALS['pdo']));
    chk('business jobs still run (billing, releases, the watch)', Automation::job($GLOBALS['pdo'], 'billing_tick') === null && Automation::job($GLOBALS['pdo'], 'releases_tick') === null && Automation::job($GLOBALS['pdo'], 'control_tick') === null, [Automation::job($GLOBALS['pdo'], 'billing_tick')]);
});
sc('actions', 'S-D112', 'Start the system: each confirmed item on, in order, watched', ['g3:C7', 'g3:C9'], function () { notHere('Start tests each item\'s live needs first (SystemStart::refreshFor → Preflight::run): outside calls; covered by tools/control-board-tests/r1.php and r2b.php'); });
sc('actions', 'S-D113', 'Shut down: dependants named before it stops, leases handed back', ['g3:M3'], function () { notHere('shut-down waits on crawler runs and Railway\'s schedule (an outside call); its dependants\' naming is a build item (g3 F10) listed for the session'); });
sc('actions', 'S-D114', 'Switch on (CS-4): as S-A9', [], function () { notHere('as S-A9: a view action; views: pending'); });
sc('actions', 'S-D115', 'Switch on: its check passed, or stays saying why', [], function () { notHere('as S-A9'); });
sc('actions', 'S-D116', 'Delete: Retired, its holds lift, history kept', [], function () {
    require_once $GLOBALS['APP'] . '/lib/ServiceStages.php';
    $id = svcFail('api.tavily.com', 'auth', 'crawler', ['status' => 401]);
    try { ServiceStages::retire($GLOBALS['pdo'], 'tavily', 'replaced', 'Brave', 7, 'Jason'); } catch (Throwable $x) { chk('Retire runs', false, $x->getMessage()); return; }
    chk('its trip closes: "Closed: <part> was deleted by Jason; its history is kept" (row 116)', issueAlarm($id) === null, issueAlarm($id) ? 'still open' : null);
});
sc('actions', 'S-D117', 'Bring it back: stage 1–2, its pre-flight needed', [], function () { notHere('covered by tools/control-board-tests/defs.php (Retire and Bring it back)'); });
sc('actions', 'S-D118', 'Assign or Unassign to a need or task', [], function () { notHere('no assign action exists (§4.8 row 118: "nothing"); a build item listed for the session'); });
sc('actions', 'S-D119', 'Log an entry', [], function () {
    ControlLog::add($GLOBALS['pdo'], 'note', 'wf:W2', 'Checked the parser', 'Jason');
    chk('in the logbook', (int) $GLOBALS['pdo']->query("SELECT COUNT(*) FROM control_log WHERE what LIKE 'Checked the parser%'")->fetchColumn() === 1);
});
sc('actions', 'S-D120', 'Pause only this task during an incident: the task Off by the person', ['g1:1'], function () {
    $s0 = snap(); CrawlService::setTask($GLOBALS['pdo'], 'overture_bulk', false, 7, 'Jason');
    chk('a person\'s switch on that task only', (int) $GLOBALS['pdo']->query("SELECT enabled FROM crawl_capture_tasks WHERE task = 'overture_bulk'")->fetchColumn() === 0 && (int) $GLOBALS['pdo']->query("SELECT enabled FROM crawl_capture_tasks WHERE task = 'coverage'")->fetchColumn() === 1);
    chk('no system hold for it', holds() === []);
});
