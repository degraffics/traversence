<?php
// The four-zone module card (handoffs/control-board-plan/step3/03-cards.md §1–§3, §8, §9, §13; 02 §3b, §6, PL;
// app/admin/module-cards.php): a working service, a held one (a refused key: Fix it), a provider's 5xx (no Fix it), a host
// down with its radius (the crawler on Railway), the video worker, Bluehost's parts and endpoints, GitHub's sync; the
// zones in order, the two rows of zone 4 never mixing, words from state, the stage badge and the spend chip, and every
// control the register's block had, kept. Run: mariadb < ../release-b-tests/start-schema.sql (with the ledger migration,
// control_ledger, as run-all.sh loads it) && php module-cards.php <root>/app
declare(strict_types=1);
$app = $argv[1]; if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(is_string($got) ? $got : json_encode($got), 0, 400) : '') . "\n"; } };
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require_once $app . '/admin/sections.php'; require_once $app . '/admin/operations-console.php'; require_once $app . '/admin/module-cards.php';
require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ServiceStages.php';
putenv('CPANEL_HOST=box1234.bluehost.com');

$pdo->exec("CREATE TABLE IF NOT EXISTS service_issues (id INT AUTO_INCREMENT PRIMARY KEY, service VARCHAR(40), host VARCHAR(190), caller VARCHAR(40), kind VARCHAR(20), state VARCHAR(12), hits INT, first_at DATETIME, last_at DATETIME, evidence TEXT, hold_until DATETIME NULL, probe_url TEXT, probe_every_min INT, probe_agent VARCHAR(40), next_probe_at DATETIME NULL, resolved_at DATETIME NULL, resolved_by INT NULL, resolved_how VARCHAR(255) NULL, last_probe TEXT NULL)");
$pdo->exec('DELETE FROM service_issues'); $pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);
$pdo->exec("CREATE TABLE IF NOT EXISTS service_stages (id INT AUTO_INCREMENT PRIMARY KEY, service VARCHAR(40) NOT NULL, stage VARCHAR(12) NOT NULL, why VARCHAR(255) NULL, replaced_by VARCHAR(255) NULL, by_uid INT NULL, by_name VARCHAR(80) NULL, at DATETIME NOT NULL, KEY (service, id))");

$zones = function (string $h): array { preg_match_all('/class="mc-z (mc-z\d)"/', $h, $m); return $m[1]; };
$row = function (string $h, string $which): string { $i = strpos($h, 'data-row="' . $which . '"'); if ($i === false) return ''; $j = strpos($h, 'data-row="provider"', $i + 10); $k = strpos($h, '</section>', $i); return substr($h, $i, ($which === 'ours' && $j !== false ? $j : $k) - $i); };
$card = fn (string $k, array $c = []) => tv_module_card($pdo, $k, $c + ['csrf' => 'x', 'pf' => []]);

// 1. a working service: the zones in order, the badge, the slots, the two rows, Copy hooks
$h = $card('usgs');
$t('a working service: its four zones in order (1 identity, 2 feeds, 3 sensors, 4 actions)', $zones($h) === ['mc-z1', 'mc-z2', 'mc-z3', 'mc-z4'], $zones($h));
$t('…its role and criticality in zone 1 (P8: no duty, "Not critical")', str_contains($h, 'class="mc-role">Source</span>') && str_contains($h, 'Not critical: no duty stops without it'));
$t('…the compact stage badge, never eight boxes (03 §2)', str_contains($h, 'Stage 5 of 8 · Operational') && str_contains($h, 'Next allowed: ') && substr_count($h, 'Stage ') === 1);
$t('…a part never checked reads "Not checked yet", grey (02 §3b, Q4)', str_contains($h, 'mc-l-grey') && str_contains($h, 'On · Not checked yet'));
$t('…no fault: no Fix it, and zone 4 sits last', !str_contains($h, 'tv-fix') && strpos($h, 'mc-z4') > strpos($h, 'mc-z3'));
preg_match_all('/id="mc-usgs-slot-(\w+)"/', $h, $sl);
$t('…four portal slots in the fixed order Billing · Keys · Console · Sign in', $sl[1] === ['billing', 'keys', 'console', 'login'], $sl[1]);
$t('…an empty slot says so in words, with Publish from your Vault (owner only)', str_contains($h, 'No API keys page registered for USGS') || (bool) preg_match('/No (billing|API keys|console|sign-in) page registered for [^<]+ yet\./', $h));
$t('…every section carries data-copy (Copy by section)', substr_count($h, 'class="mc-z ') === substr_count($h, '" data-copy="') - 2 || preg_match_all('/<section class="mc-z mc-z\d" data-copy="/', $h) === 4);
$t('…kept: Pause with its why, Retire it… folded, its stage anchor', str_contains($h, 'id="stage-usgs"') && str_contains($h, 'value="svc_pause"') && str_contains($h, 'placeholder="Why pause it"') && str_contains($h, '<summary>Retire it…</summary>') && str_contains($h, 'data-yes="Retire" data-danger'));
$t('…kept: Its key with its anchor, Used by, Known price, This month (with the caps anchor where it has no caps)', str_contains($h, '<dt id="svc-usgs-key">Its key</dt>') && str_contains($h, '<dt>Used by</dt>') && str_contains($h, '<dt>Known price</dt>') && str_contains($h, '<dt id="svc-usgs-caps">This month</dt>'));
$t('…Take out greyed with the reason (a module can\'t be taken out today, g3 M5)', (bool) preg_match('/data-mc-act="maintout"[^>]*disabled title="It can&#039;t be taken out from here/', $h));
$t('…phone layout: edge to edge class, slots two per row and the radius stacked under 768px', str_contains($h, 'mc-card tv-bleed') && str_contains($h, '.mc-slots{grid-template-columns:repeat(2,minmax(0,1fr))}') && str_contains($h, '.mc-rad{grid-template-columns:1fr}') && str_contains($h, 'overflow-wrap:anywhere'));

// 2. a held one: a refused key, Fix it to Its key, the Keys slot named (row 129), zone 4 moved up
$pdo->exec("INSERT INTO service_issues (id, service, host, caller, kind, state, hits, first_at, last_at, evidence) VALUES (40, 'anthropic', 'api.anthropic.com', 'site', 'auth', 'open', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), '{\"latest\":{\"status\":401,\"error\":\"invalid x-api-key\"}}')");
$h = $card('anthropic');
$z = $zones($h);
$t('a refused key: zone 4 moves up under zone 1, the thread first (03 §1, the lift\'s §f)', $z === ['mc-z1', 'mc-z4', 'mc-z2', 'mc-z3'], $z);
$t('…its state from the engine\'s words: Paused by the system, red, the issue named', str_contains($h, 'mc-l-bad') && str_contains($h, 'Paused by the system: calls to api.anthropic.com wait for a person'));
$ours = $row($h, 'ours'); $prov = $row($h, 'provider');
$t('…Fix it first on our system, landing on Its key on this card (0176 §5; row 129)', (bool) preg_match('/^data-row="ours"><p class="mc-lbl">On our system<\/p><div class="mc-btns"><a class="ak-b go tv-fix" href="#svc-anthropic-key"/', $ours), mb_substr($ours, 0, 200));
$t('…and the Keys slot outlined "Fix it lands here", the line naming it', str_contains($h, 'class="mc-slot mc-slot-fix" id="mc-anthropic-slot-keys"><b class="mc-fixhere">Fix it lands here</b>') && str_contains($h, 'Fix it: Anthropic (Claude)&#039;s API keys page.'));
$t('…its link labelled by type with the provider\'s label where it differs, and its source tag (04 §5)', str_contains($h, 'Billing <small>· Usage</small>') && str_contains($h, 'Documented · release #212') && str_contains($h, 'Written by a Claude session from Anthropic (Claude)'));
$t('…Check it now beside it (a passing check closes it), and its case in Pulse', str_contains($ours, 'data-mc-act="svccheck" data-mc-id="40"') && str_contains($ours, 'href="/admin/pulse/stream#case=svc%3A40"'));
$t('the two rows never mix: no outside link on our row, no board action among the slots', !str_contains($ours, 'target="_blank"') && $prov !== '' && !str_contains($prov, 'data-mc-act') && !str_contains($prov, 'value="svc_'));
$t('…never Mark resolved or Use backup (03 §9: dropped)', !str_contains($h, 'Mark resolved') && !str_contains($h, 'svcresolve') && !str_contains($h, 'Use backup'));

// 3. a provider's 5xx: their trouble, no Fix it, only Check it now and Pause it (R118)
$pdo->exec("INSERT INTO service_issues (id, service, host, caller, kind, state, hits, first_at, last_at, evidence) VALUES (41, 'tavily', 'api.tavily.com', 'crawler', 'server', 'open', 2, UTC_TIMESTAMP(), UTC_TIMESTAMP(), '{\"latest\":{\"status\":503,\"body\":\"Service Unavailable\"}}')");
$h = $card('tavily'); $ours = $row($h, 'ours');
$t('a provider\'s 5xx: no Fix it and no slot outlined', !str_contains($h, 'tv-fix') && !str_contains($h, 'mc-slot-fix'));
$t('…Check it now and Pause on our system', str_contains($ours, 'data-mc-act="svccheck" data-mc-id="41"') && str_contains($ours, 'value="svc_pause"'));
$t('…a warning while the system tries, amber, never "Working"', str_contains($h, 'mc-l-hold') && str_contains($h, 'Warning (the system is trying)') && !str_contains($h, 'On · Working'));
$t('…the thread says the system is handling it', str_contains($h, 'The system is handling it'));
$t('…Caps and paces kept with its anchor, Set and Reset (rows 39)', str_contains($h, 'id="svc-tavily-caps">Caps and paces</h3>') && str_contains($h, 'value="cap_set"') && str_contains($h, 'value="cap_reset"'));

// 4. a host down with its radius: the crawler's trip on Railway
$pdo->exec("INSERT INTO control_alarms (akey, proc, level, kind, title, first_at, last_at, tripped_at, stopped, component) VALUES ('run:failed', 'wk:crawler', 'trip', 'held', 'The crawler is down', UTC_TIMESTAMP() - INTERVAL 40 MINUTE, UTC_TIMESTAMP(), UTC_TIMESTAMP() - INTERVAL 5 MINUTE, '[[\"pause\",\"master:all\",\"All gathering\"]]', 'wk:crawler')");
$aid = (int) $pdo->lastInsertId();
$h = $card('railway'); $ours = $row($h, 'ours');
$t('Railway: a Host, not critical by itself (Jason)', str_contains($h, 'class="mc-role">Host</span>') && str_contains($h, 'Not critical by itself'));
$t('…its three workers as parts with their role (D18)', substr_count($h, 'mc-role mc-role-s">Worker</span>') === 3 && str_contains($h, '<b>The video worker</b>') && str_contains($h, '<b>W0 Indexer</b>'));
$t('…the crawler\'s trip moves zone 4 up, its row tinted and pointing to the thread', $zones($h)[1] === 'mc-z4' && str_contains($h, 'mc-part mc-tint-bad') && str_contains($h, 'Its actions are in the thread above ↑'));
$t('…Check and start again and Acknowledge on the trip (rows 93, 96)', str_contains($ours, 'data-mc-act="resolve" data-mc-id="' . $aid . '"') && str_contains($ours, 'data-mc-act="ack" data-mc-id="' . $aid . '"'));
$t('…Fix it lands on Railway\'s console slot on the card (03 §1\'s table)', str_contains($ours, 'href="#mc-railway-slot-console" data-fix="console">Fix it</a>') && str_contains($h, 'id="mc-railway-slot-console"><b class="mc-fixhere">Fix it lands here</b>') && str_contains($h, 'Fix it: Railway&#039;s console page.'));
$t('…its words from the engine: Paused by the system (All gathering)', str_contains($h, 'Paused by the system (All gathering)'));
$t('…its radius: Held the crawler, W0 and new video copies; Unaffected Bluehost and GitHub', str_contains($h, 'If Railway itself is down: its Incident Radius') && str_contains($h, 'Held (3)') && str_contains($h, 'New video copies (they queue)') && str_contains($h, 'W0 Indexer (W0)') && str_contains($h, 'Bluehost: pages, search, sign-in, payments, the databases'));
$t('…Pause everything, asking first (row 111)', (bool) preg_match('/data-mc-act="pauseall"[^>]*data-confirm="Pause everything\?/', $ours));
$t('…the keys it keeps for other modules: Not checked yet, never colouring Railway', str_contains($h, 'Kept on Railway for other modules') && str_contains($h, '<code>TAVILY_API_KEY</code>') && str_contains($h, 'its state never colours Railway'));
$t('…spend chip: its plan, Billing · Usage linked', str_contains($h, '<b>Hobby plan: $5 a month including $5 of usage</b>') && str_contains($h, 'Billing · Usage ↗'));
$t('…setup capture as grey words: Sign in not recorded · Keys: why not recorded (03 §13.1)', str_contains($h, 'Setup capture: Sign in not recorded · Keys: why not recorded'));
ControlAlarms::ack($pdo, $aid, 1, 'Jason');
$h = $card('railway');
$t('after Acknowledge the light stays red: "1 trip (seen by Jason)" (03 §4, g4 F1)', str_contains($h, 'mc-l-bad') && str_contains($h, '1 trip (seen by Jason)') && str_contains($h, 'data-mc-act="unack"'));

// 5. the video worker: a Service hosted on Railway, its heartbeat, its radius
$h = $card('wk:video-worker');
$t('the video worker with no reading yet: Not checked yet, never a fault', str_contains($h, 'On · Not checked yet') || str_contains($h, 'On · Warning: no heartbeat'), mb_substr(strip_tags($h), 0, 300));
$pdo->exec("INSERT INTO control_ledger (at, endpoint, method, code, ms) VALUES (UTC_TIMESTAMP() - INTERVAL 3 MINUTE, 'video/jobs', 'POST', 200, 120)");
$h = $card('wk:video-worker');
$t('the video worker: a Service hosted on Railway, not critical', str_contains($h, 'class="mc-role">Service</span>') && str_contains($h, 'hosted on Railway') && str_contains($h, 'Not critical'));
$t('…its heartbeat read from its asks for work (the engine\'s check)', str_contains($h, '<b>Heartbeat</b>') && str_contains($h, 'The video worker last asked for work at') && str_contains($h, 'On · Working'), mb_substr(strip_tags($h), 0, 400));
$t('…its radius: Held new copies; On a backup each video plays its original (03 §23.1)', str_contains($h, 'New video copies (they queue)') && str_contains($h, 'Each video plays its original'));
$t('…while Railway\'s crawler trips, the video worker isn\'t held by it (only its host\'s own trip would)', !str_contains($h, 'waiting on Railway'));
$t('…Railway\'s slots (02 §8), and no Pause of its own', str_contains($h, 'Open Railway console ↗') && !str_contains($h, 'value="svc_pause"'));

// 6. Bluehost: its parts each with a duty, our endpoints as Core Services parts, Console from Site settings
$h = $card('bluehost');
$t('Bluehost: Critical: page, database, procedures (03 §14.2)', str_contains($h, 'Critical: page, database, procedures'));
$t('…its parts with their duties (the fix for g3 D6)', str_contains($h, '<b>The databases</b>') && str_contains($h, '<b>The scheduler (cPanel cron)</b>') && str_contains($h, 'Critical: database'));
$t('…our endpoints for the workers as parts with their role, /api/crawl/loads.php among them', str_contains($h, '<b>/api/crawl/loads.php</b> <span class="mc-role mc-role-s">Endpoint (Core Services)</span>') && str_contains($h, 'Serves: overture_bulk, foursquare_bulk, geofabrik_bulk'));
$t('…Console · cPanel from CPANEL_HOST, tagged Site settings; Sign in · Account documented (14.6a §3)', str_contains($h, 'https://box1234.bluehost.com:2083') && str_contains($h, '>Site settings</span>') && str_contains($h, 'Sign in <small>· Account</small>'));
$t('…the documented cPanel link folded into Sign in, not drawn (14.6a §6)', !str_contains($h, 'hosting/app#/cpanel'));
$t('…Bluehost can\'t be paused (business operations), and says why', str_contains($h, 'Not paused from here: its calls are business operations'));
$t('…its radius from the spec (14.4)', str_contains($h, 'Railway&#039;s workers keep running but can&#039;t report'));

// 7. GitHub: the sync is a row, not a slot
$h = $card('github');
$t('GitHub: the sync on its row with Its runs, never a portal slot (04 §5)', str_contains($h, '<b>The sync</b>') && str_contains($h, '>Its runs ↗</a>') && !preg_match('/mc-slot[^"]*" id="mc-github-slot-console"><p class="mc-ty">Console <small>· The sync/', $h));
$t('…Critical: procedures', str_contains($h, 'Critical: procedures'));

// 8. the spend chip: the percentage from the two numbers printed (03 §3), the band decides the tone
$r = Operations::register($pdo)['anthropic']; $r['use'] = [['what' => 'Claude this month', 'used' => 3.85, 'cap' => 5.0, 'money' => true, 'unit' => 'dollars', 'pct' => 0]];
$h = $card('anthropic', ['r' => $r]);
$t('the spend chip: $3.85 of $5.00 (77%), amber past its 75% band', str_contains($h, '$3.85 of $5.00 (77%)') && str_contains($h, 'class="mc-spend mc-l-hold"'));

// 9. retired: Bring it back kept, stage 8
ServiceStages::retire($pdo, 'usgs', 'Testing the card', '', 1, 'Tester'); Automation::forget();
$h = $card('usgs', ['stage' => ['retired', 'Testing the card']]);
$t('a retired module: Stage 8 of 8, Bring it back (its way back), no Pause', str_contains($h, 'Stage 8 of 8 · Removal (Retired: its code remains)') && str_contains($h, 'value="svc_bringback"') && !str_contains($h, 'value="svc_pause"'));
$t('…its stages history kept (Details)', str_contains($h, 'Its stages ▸') && str_contains($h, 'Testing the card'));

// 10. the register draws every service as a card, and the old anchors stay
$reg = tv_admin_mod_register($pdo);
preg_match_all('/class="mc-card tv-bleed[^"]*" id="mc-[^"]+" data-mc="([a-z0-9_]+)"/', $reg, $mm);
$t('the register: a four-zone card for every service (and each Railway worker\'s own card inside Railway\'s row)', count($mm[1]) === count(OutsideServices::COMPANIES) && substr_count($reg, 'data-mc="wk:') === 3, count($mm[1]));
$t('…the anchors other pages land on, once each (#svc-<k>-key, -caps, #stage-<k>, #issue-<n>)', substr_count($reg, 'id="svc-anthropic-key"') === 1 && substr_count($reg, 'id="svc-tavily-caps"') === 1 && substr_count($reg, 'id="stage-tavily"') === 1 && substr_count($reg, 'id="issue-40"') === 1);
$t('…the keys\' controls kept (Add its key / Replace, Check)', substr_count($reg, 'class="os-key"') >= 15 && str_contains($reg, 'value="key_check"'));
$t('…the CSS and the script drawn at most once a page', substr_count($reg, '.mc-card{') <= 1 && substr_count($reg, 'closest("[data-mc-act]")') <= 1);
$t('no key value anywhere: names only', !preg_match('/testtoken|vtoken/', $reg));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
