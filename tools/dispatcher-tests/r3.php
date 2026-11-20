<?php
// decisions/0176 release 3, Fix it routing (§5, A5): each thread carries its cause, and the cause → landing map gives one
// spot (Thread::FIX). A refused key lands on the card's Its key (#svc-<k>-key, Replace ready); a used-up quota or a cap on
// its Caps and budget (#svc-<k>-caps; the community budget on Costs); a refusal on its own issue (#issue-<id>); a site
// that blocked W0 on its line among the sites that blocked us, with Reactivate (#held-<host>). Their own trouble (5xx, no
// answer, a 429) has no Fix it: "The system is handling it", Check it now, Pause it and a greyed Use backup. Fix it comes
// first, opening in the one side panel (data-peek-frame), on the board, in Pulse's message and as the first step of the
// case; a push or an email opens the thread's Pulse row with &fix=1. Setting a cap looks at its budget again on the spot.
// Run: mariadb < ../release-b-tests/start-schema.sql && php r3.php <root>/app
declare(strict_types=1);
$app = $argv[1]; if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(is_string($got) ? $got : json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 600) : '') . "\n"; } };
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require_once $app . '/admin/sections.php'; require_once $app . '/admin/operations-console.php'; require_once $app . '/admin/control-console.php';
require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/Thread.php'; require_once $app . '/lib/ServiceHealth.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS service_issues (id INT AUTO_INCREMENT PRIMARY KEY, service VARCHAR(40), host VARCHAR(190), caller VARCHAR(40), kind VARCHAR(20), state VARCHAR(12), hits INT, first_at DATETIME, last_at DATETIME, evidence TEXT, hold_until DATETIME NULL, probe_url TEXT, probe_every_min INT, probe_agent VARCHAR(40), next_probe_at DATETIME NULL, last_probe TEXT NULL, resolved_at DATETIME NULL, resolved_by INT NULL, resolved_how VARCHAR(255) NULL)");
$pdo->exec('DELETE FROM service_issues'); $pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);
$soon = gmdate('Y-m-d H:i:s', time() + 1800);
$pdo->exec("INSERT INTO service_issues (id, service, host, caller, kind, state, hits, first_at, last_at, evidence, hold_until) VALUES
  (40, 'anthropic', 'api.anthropic.com', 'site', 'auth', 'open', 1, '2026-11-20 10:00:00', '2026-11-20 10:00:00', '{\"latest\":{\"status\":401,\"error\":\"invalid x-api-key\"}}', NULL),
  (41, 'tavily', 'api.tavily.com', 'crawler', 'quota', 'open', 1, '2026-11-20 10:00:00', '2026-11-20 10:00:00', '{\"latest\":{\"status\":402}}', NULL),
  (42, 'nps', 'developer.nps.gov', 'crawler', 'ban', 'open', 1, '2026-11-20 10:00:00', '2026-11-20 10:00:00', '{\"latest\":{\"status\":403}}', NULL),
  (43, 'commoncrawl', 'index.commoncrawl.org', 'crawler', 'server', 'open', 2, '2026-11-20 10:00:00', '2026-11-20 10:05:00', '{\"latest\":{\"status\":502}}', NULL),
  (44, 'wikidata', 'query.wikidata.org', 'site', 'rate', 'open', 2, '2026-11-20 10:00:00', '2026-11-20 10:05:00', '{\"latest\":{\"status\":429}}', '$soon')");
$row = fn (int $id) => $pdo->query('SELECT * FROM service_issues WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);

// the map
$t('the map names every cause in 0176 §5\'s table, theirs with no Fix it', array_keys(Thread::FIX) === ['key', 'quota', 'blocked', 'backup', 'shutdown', 'theirs'] && Thread::FIX['theirs'] === null);
$t('a spot that exists draws Fix it as a link into the one side panel', str_contains(Thread::fixButton(['cause' => 'key', 'url' => '/admin/config/register#svc-x-key']), 'href="/admin/config/register#svc-x-key" data-peek-frame="The card&#039;s Its key, with Replace ready" data-fix="key"'));
$t('…and on its own page (an anchor) lands there without a panel', !str_contains(Thread::fixButton(['cause' => 'key', 'url' => '#svc-x-key']), 'data-peek-frame'));
$sb = Thread::fixButton(Thread::fixOf(['fix' => ['cause' => 'backup', 'url' => '/admin/config/control/wf-W1#cb-steps']]));
$t('a spot still to come: a greyed Fix it with its reason shown, not only on hover', str_contains($sb, 'disabled title="Naming a backup') && str_contains($sb, '<small class="tv-fix-soon">Naming a backup'), $sb);
$t('their trouble, or an unknown cause, has no Fix it', Thread::fixOf(['fix' => ['cause' => 'theirs', 'url' => '']]) === null && Thread::fixOf(['fix' => ['cause' => 'nope', 'url' => '/x']]) === null && Thread::fixOf([]) === null);
$th = Thread::html(['title' => 'A', 'level' => 'trip', 'fix' => ['cause' => 'key', 'url' => '/k'], 'buttons' => [['Acknowledge', 'ack', '1']]], 'pulse', null, '/e');
$t('Fix it is first among a thread\'s buttons', strpos($th, '>Fix it</a>') !== false && strpos($th, '>Fix it</a>') < strpos($th, '>Acknowledge</button>'), $th);
$t('…and a closed thread has none', !str_contains(Thread::html(['title' => 'A', 'level' => 'closed', 'fix' => ['cause' => 'key', 'url' => '/k']], 'pulse', null, '/e'), 'Fix it'));

// an outside service's issue: its cause from its kind
$f = []; foreach ([40, 41, 42, 43, 44] as $i) $f[$i] = ServiceHealth::facts($pdo, $row($i));
$t('a refused key: the card\'s Its key', $f[40]['fix'] === ['cause' => 'key', 'url' => '/admin/config/register#svc-anthropic-key'], $f[40]['fix']);
$t('a used-up quota: the card\'s Caps and budget', $f[41]['fix'] === ['cause' => 'quota', 'url' => '/admin/config/register#svc-tavily-caps'], $f[41]['fix']);
$t('a refusal: the issue itself, where Check it now is once they\'ve lifted it', $f[42]['fix'] === ['cause' => 'blocked', 'url' => '/admin/config/register#issue-42'], $f[42]['fix']);
$t('their server, or a 429: no Fix it, "The system is handling it"', $f[43]['fix'] === null && $f[44]['fix'] === null && !empty($f[43]['theirs']) && str_starts_with($f[43]['doing'], 'The system is handling it: ') && str_starts_with($f[44]['doing'], 'The system is handling it: '), [$f[43]['doing'], $f[44]['fix']]);
$t('…a person\'s kind says what it means, without that line', !str_starts_with($f[40]['doing'], 'The system is handling it'));

// the card
$c40 = tv_ops_issues($pdo, 'anthropic', 'x', '');
$t('the card: Fix it first, landing on Its key on the same card (no panel)', str_contains($c40, '<a class="ak-b go tv-fix" href="#svc-anthropic-key" data-fix="key"') && strpos($c40, '>Fix it</a>') < strpos($c40, '>Check it now<'), mb_substr($c40, (int) strpos($c40, 'tv-th-acts'), 400));
$c42 = tv_ops_issues($pdo, 'nps', 'x', '');
$t('…a refusal\'s spot is the issue itself, so no Fix it there (its Check it now is right there)', !str_contains($c42, 'tv-fix') && str_contains($c42, 'data-ops-cb="svccheck" data-ops-id="42"'));
$c43 = tv_ops_issues($pdo, 'commoncrawl', 'x', '');
$t('their server: no Fix it; Check it now, Pause it (this card\'s Pause) and a greyed Use backup with its reason', !str_contains($c43, 'tv-fix"') && str_contains($c43, 'data-ops-cb="svccheck" data-ops-id="43"') && str_contains($c43, '<a class="ak-b" href="#stage-commoncrawl">Pause it</a>') && str_contains($c43, '<button type="button" class="ak-b" disabled title="Use backup as a button is to come') && str_contains($c43, '<small class="tv-fix-soon">Use backup'), mb_substr($c43, (int) strpos($c43, 'tv-th-acts'), 600));
$c44 = tv_ops_issues($pdo, 'wikidata', 'x', '');
$t('a 429: still no Check it now (it waits its turn), no Fix it', !str_contains($c44, 'svccheck') && !str_contains($c44, 'tv-fix"'));
$reg = tv_admin_mod_register($pdo);
$t('the register: every card\'s Its key carries its anchor', str_contains($reg, '<dt id="svc-anthropic-key">Its key</dt>') && str_contains($reg, '<dt id="svc-tavily-key">Its key</dt>'));
$t('…Caps and paces carries svc-<k>-caps where a card has caps', str_contains($reg, '<h3 class="os-h" id="svc-tavily-caps">Caps and paces</h3>'));
$noCaps = array_values(array_filter(array_keys(OutsideServices::COMPANIES), fn ($k) => empty(Operations::CAPS[$k]) && str_contains($reg, 'id="svc-' . $k . '-key"')));
$t('…and This month carries it where a card has none', $noCaps && str_contains($reg, '<dt id="svc-' . $noCaps[0] . '-caps">This month</dt>'), $noCaps);
$t('…each anchor once', substr_count($reg, 'id="svc-tavily-caps"') === 1 && substr_count($reg, 'id="svc-anthropic-key"') === 1);
$js = (string) file_get_contents(dirname($app) . '/website/js/admin-outside.js');
$t('landing on Its key opens its card and readies Replace (never a greyed one)', str_contains($js, "r.closest('.os-r')") && str_contains($js, "spot.nextElementSibling") && str_contains($js, "inp.focus({ preventScroll: true })") && str_contains($js, "querySelector('button[disabled]')"));

// alarms
$a40 = ControlAlarms::raise($pdo, 'svc:issue:40', 'svc:anthropic', 'trip', 'held', 'api.anthropic.com: Our key was refused', 'Nothing calls it.', 'Fix the key.', '', [], true, false);
$a43 = ControlAlarms::raise($pdo, 'svc:issue:43', 'svc:commoncrawl', 'warning', 'retry', 'index.commoncrawl.org: Its server failed', 'Calls wait and try again.', '', '', ['Check it now', 'svccheck', '43'], true, false);
$aCap = ControlAlarms::raise($pdo, 'ops:cap:tavily', 'svc:tavily', 'trip', 'limit', 'Tavily is out of budget', 'Work waits.', 'Raise the cap.', '100%', [['Its register entry', 'peek', '/admin/config/register#svc-tavily']], false);
$aCom = ControlAlarms::raise($pdo, 'ops:cap:community', 'svc:community', 'warning', 'limit', 'The community budget at 75%', '', '', '', [], false);
$aW0 = ControlAlarms::raise($pdo, 'ops:w0:held:Example.org', 'wf:W0', 'warning', 'check', 'W0 Indexer: example.org blocked us', '', '', '', [['Reactivate', 'ops', 'ops_w0_reactivate:example.org']], false, false);
$aCr = ControlAlarms::raise($pdo, 'task:failed:second_look', 'wf:W2', 'trip', 'crash', 'W2: its task second_look failed', 'Its work goes back.', 'Read why.', '', [], true, false);
$by = fn (int $id) => ControlAlarms::byId($pdo, $id);
$t('an alarm on an issue takes the issue\'s cause', ControlAlarms::fixOf($pdo, $by($a40)) === ['cause' => 'key', 'url' => '/admin/config/register#svc-anthropic-key'] && ControlAlarms::fixOf($pdo, $by($a43))['cause'] === 'theirs');
$t('a cap: the card\'s Caps and budget; the community budget: Costs', ControlAlarms::fixOf($pdo, $by($aCap)) === ['cause' => 'quota', 'url' => '/admin/config/register#svc-tavily-caps'] && ControlAlarms::fixOf($pdo, $by($aCom)) === ['cause' => 'quota', 'url' => '/admin/config/register#costs']);
$t('a site that blocked W0: its line among the sites that blocked us', ControlAlarms::fixOf($pdo, $by($aW0)) === ['cause' => 'blocked', 'url' => '/admin/insights/index#held-example.org'], ControlAlarms::fixOf($pdo, $by($aW0)));
$t('a crash has no mapped cause (its row keeps its component button)', ControlAlarms::fixOf($pdo, $by($aCr)) === null);
$t('their trouble on an alarm: no Fix it, "The system is handling it"', ControlAlarms::facts($pdo, $by($a43))['fix'] === null && str_starts_with(ControlAlarms::facts($pdo, $by($a43))['doing'], 'The system is handling it: '));
$ix = (string) file_get_contents($app . '/admin/index-console.php');
$t('Insights → Index: each blocked site\'s row carries held-<host>, beside its Reactivate', str_contains($ix, '<tr id="\' . $e(ControlAlarms::heldAnchor((string) $r[\'host\'])) . \'">') && ControlAlarms::heldAnchor('Sub_Domain.Example.org') === 'held-sub-domain.example.org');

// the board
$names = []; $pid = fn (string $p) => 'cb-p-' . str_replace(':', '-', $p);
$btn = fn (string $act, string $label, bool $ok, string $why, string $id = '', array $attr = []) => '<button data-cb-act="' . $act . '" data-cb-id="' . htmlspecialchars($id) . '"' . ($ok ? '' : ' disabled') . '>' . htmlspecialchars($label) . '</button>';
$rowOf = function (int $id) use ($pdo, $names, $pid, $btn): string { $a = ControlAlarms::byId($pdo, $id); $a['fix'] = json_decode((string) $a['fix'], true) ?: []; $h = tv_cb_alarms([$a], ['trip' => 1, 'warning' => 0, 'notice' => 0], $names, $pid, true, '', $btn); return $h; };
$b40 = $rowOf($a40);
$t('the board: Fix it first, into the side panel, in place of "Open its card" (the same page)', str_contains($b40, '<a class="ak-b cb-xs go cb-main tv-fix" href="/admin/config/register#svc-anthropic-key" data-peek-frame=') && !str_contains($b40, '>Open its card</a>'), mb_substr($b40, (int) strpos($b40, 'cb-acts'), 500));
$bW0 = $rowOf($aW0);
$t('…a site that blocked W0: Fix it to its line, and its process page kept beside it (another page)', str_contains($bW0, 'href="/admin/insights/index#held-example.org" data-peek-frame') && str_contains($bW0, '>Open W0</a>'), mb_substr($bW0, (int) strpos($bW0, 'cb-acts'), 600));
$bCr = $rowOf($aCr);
$t('…a crash keeps its component button and has no Fix it', !str_contains($bCr, 'tv-fix') && str_contains($bCr, '>Open W2</a>'));
$b43 = $rowOf($a43);
$t('…their trouble: no Fix it, its Check it now kept', !str_contains($b43, 'tv-fix') && str_contains($b43, '>Check it now</button>'));

// Pulse
$s = fn (string $t, string $h, ?array $act = null, bool $auto = false, string $ext = '') => ['t' => $t, 'h' => $h, 'act' => $act];
$ps = ControlAlarms::pulseSteps($pdo, $aCap, $s);
$t('Pulse: Fix it is the case\'s first step, opening in its panel, saying what happens there', $ps[0]['t'] === 'Fix it' && $ps[0]['act'] === ['tool', '/admin/config/register#svc-tavily-caps'] && str_contains($ps[0]['h'], 'Set looks at its budget again at once'), $ps[0]);
$t('…an alarm without a cause keeps its steps as they were', ControlAlarms::pulseSteps($pdo, $aCr, $s)[0]['t'] !== 'Fix it');
$pb = ControlAlarms::pulseBody($pdo, ['thing' => 'alarm:' . $a40], 1)['html'];
$t('…its message in Pulse: Fix it first, then Acknowledge', strpos($pb, '>Fix it</a>') !== false && strpos($pb, '>Fix it</a>') < strpos($pb, 'Acknowledge'), $pb);
$ss = ServiceHealth::steps($row(40));
$t('…an issue\'s own case (svc:): Fix it first, to its key', $ss[0]['t'] === 'Fix it' && $ss[0]['act'] === ['tool', '/admin/config/register#svc-anthropic-key']);
$t('…their trouble\'s case starts with what it said, as before', ServiceHealth::steps($row(43))[0]['t'] === 'Read what it said');
$pu = (string) file_get_contents($app . '/ui/pulse-ui.php');
$t('…the case opened with &fix=1 opens its panel on Fix it; the step\'s button reads Fix it', str_contains($pu, "hf = /[#&]fix=1\\b/.test(location.hash)") && str_contains($pu, "showTool(true, gb.dataset.toolUrl)") && str_contains($pu, "st.t === 'Fix it' ? 'Fix it' : 'Open'"));

// a push and an email
$t('a push or an email opens the thread\'s Pulse row, with the panel on its fix', ControlAlarms::pulseUrl($pdo, $by($a40)) === '/admin/dashboard/stream#case=alarm:' . $a40 . '&fix=1' && ControlAlarms::pulseUrl($pdo, $by($aCr)) === '/admin/dashboard/stream#case=alarm:' . $aCr);
$ca = (string) file_get_contents($app . '/lib/ControlAlarms.php');
$t('…the trip\'s email and its Escalation Notice use it; Recovered keeps the board\'s line', str_contains($ca, "'url' => self::pulseUrl(\$pdo, \$a), 'from'") && str_contains($ca, "\$kind === 'alarm.recovered' ? '/admin/config/control#cb-alarm-' . (int) \$a['id'] : self::pulseUrl(\$pdo, \$a)"));

// after acting
$oc = (string) file_get_contents($app . '/admin/operations-console.php');
$t('setting a cap looks at its budget again on the spot: Fixed, or what it still reads and the old value', str_contains($oc, 'Operations::alarms($pdo); $still = ') && str_contains($oc, "' Checked now: Fixed, its budget is under its warning level.'") && str_contains($oc, "' Put it back to ' . Operations::num(\$old)"));
$t('a key\'s Replace runs Check at once, with Put the old one back (0173 release 2, kept)', str_contains($oc, "case 'key_set':") && str_contains($oc, "' Put the old one back undoes it.'"));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
