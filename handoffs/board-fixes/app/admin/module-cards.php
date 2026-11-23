<?php
// app/admin/module-cards.php — the four-zone module card (handoffs/control-board-plan/step3/03-cards.md §1–§3, §8, §9, §13;
// 02-state-model.md §0 PL, §3, §6, §8; 04-vault-portal-links.md §5–§6). One renderer, tv_module_card(), for every module
// on the Service register (the Modules page) and for the parts the hosts run (D18: hosts and endpoints are parts with a
// role; the video worker has its heartbeat, read by the engine). The zones, always in this order (03 §1):
//   1 Identity and role: the name, its role (Host · Source · Service, service-defs.php `role`), criticality (its duty,
//     ControlRegister::dutyMap), the state as "<switch> · <run-state>" (02 §3b's precedence), the compact stage badge with
//     "Next allowed" (03 §2), the setup capture notice (03 §13), the spend chip (03 §3) and its Incident Radius (02 §6).
//   2 Capabilities and feeds: what uses it (a Service or Source), or one row per part it hosts (a Host).
//   3 Sensors and key readiness: its check, its keys (named, never shown), and on Railway the keys it keeps for others.
//   4 Actions and Fix it, two rows that never mix (02 PL; 04 §6): "On our system" (the board's actions, Fix it first)
//     and "At <Provider>" (four slots, Billing · Keys · Console · Sign in, each a link with its source tag or the words
//     "No <type> page registered for <Provider> yet"). While a fault is open on it or a part, zone 4 moves up under
//     zone 1 with the thread (03 §1, the lift's §(f)).
// Words come from the engine's state (P9): ControlAlarms::facts / ServiceHealth::facts / ControlEngine::status. Nothing here
// writes anything: every button posts to the board's own endpoint (/admin/api/config/control) or the register's forms
// (tv_ops_form in operations-console.php), each with its guard. Each section carries data-copy for Copy by section
// (website/js/tv-copy.js). PHP 8.0.
declare(strict_types=1);

/**
 * The workers' radius (they have no service-defs.php entry: their manifest is ControlRegister::WORKERS), as the spec wrote
 * it: Held · On a backup · Unaffected. A module's overrides are its manifest's incident_radius_rules (02 §8).
 */
const TV_MC_WORKER_RADIUS = [
    'wk:video-worker' => [['New video copies (they queue)'], ['Each video plays its original'], ['Everything else, including the crawler and W0 on the same host']],   // 03 §23.1
    'wk:crawler' => [['All gathering the crawler runs'], [], ['W0 Indexer, the video worker, Bluehost\'s pages, search, sign-in, payments, databases and jobs, GitHub, everything stored']],   // 03 §5 c2
    'wk:w0-indexer' => [['W0'], [], ['The crawler, the video worker, Bluehost, GitHub']],   // 03 §5 c3
];

/** What each Railway worker holds when it (or Railway) is down (03 §4 "Railway's host tile"; §23.1). */
const TV_MC_WORKER_HELD = ['w0-indexer' => 'W0 Indexer (W0)', 'video-worker' => 'New video copies (they queue)'];

/** What each worker feeds, for its row (03 §1 Zone 2's table). The crawler's are read from Automation::WORKFLOWS. */
const TV_MC_WORKER_FEEDS = ['w0-indexer' => 'W0', 'video-worker' => 'Posted videos (720p and 1080p copies)'];

/** Bluehost's hosted parts, each with its own duty (03 §14.2 Zone 2, the fix for g3 D6). proc => its name on the card. */
const TV_MC_BLUEHOST_PARTS = ['svc:bluehost' => 'The site (its pages)', 'site:env' => 'The site\'s settings (.env)', 'site:db' => 'The databases', 'site:dbup' => 'Database updates', 'site:sched' => 'The scheduler (cPanel cron)'];

/** The four portal slots in their fixed order (03 §1; 04 §6) and the words for each type ("No <type> page registered…"). */
const TV_MC_SLOTS = ['billing' => ['Billing', 'billing'], 'keys' => ['Keys', 'API keys'], 'console' => ['Console', 'console'], 'login' => ['Sign in', 'sign-in']];

/** Jason's eight stages (02 §3a): number => name. */
const TV_MC_STAGES = [1 => 'Selection and configuration', 2 => 'Integration (pre-flight)', 3 => 'Activation', 4 => 'Assigned to workflows', 5 => 'Operational', 6 => 'Maintenance', 7 => 'Deactivation', 8 => 'Removal'];

/** The register's manifest for one module: service-defs.php's entry (the overlay's role and answers), or a worker part. */
function tv_mc_manifest(string $key): array
{
    static $defs = null;
    if ($defs === null) { $defs = []; try { $d = require __DIR__ . '/../lib/service-defs.php'; if (is_array($d)) $defs = $d; } catch (Throwable $x) { error_log('module card defs: ' . $x->getMessage()); } }
    if (str_starts_with($key, 'wk:')) {
        require_once __DIR__ . '/../lib/ControlRegister.php';
        $w = ControlRegister::WORKERS[substr($key, 3)] ?? null;
        if (!$w) return [];
        // 03 §23.1: "Service, hosted on Railway · not critical"; its slots are Railway's (02 §8)
        return ['key' => $key, 'name' => (string) $w[0], 'role' => 'service', 'hosted_by' => 'railway', 'purpose' => (string) $w[1],
                'links' => (array) (($defs['railway']['company'][3] ?? [])), 'provider' => 'Railway', 'keys' => [], 'pricing' => 'Railway\'s compute, counted on Railway\'s card', 'worker' => substr($key, 3)];
    }
    $d = $defs[$key] ?? null;
    if (!is_array($d) || !isset($d['company'])) return [];
    $c = (array) $d['company'];
    return ['key' => $key, 'name' => (string) $c[0], 'role' => (string) ($d['role'] ?? ''), 'hosted_by' => '', 'purpose' => (string) ($c[1] ?? ''), 'pricing' => (string) ($c[2] ?? ''),
            'links' => (array) ($c[3] ?? []), 'provider' => (string) $c[0], 'keys' => (array) ($d['keys'] ?? []), 'stage' => (string) ($d['stage'] ?? ''),
            'radius_rules' => (array) ($d['incident_radius_rules'] ?? []), 'portal_links' => (array) ($d['portal_links'] ?? [])];
}

/**
 * The four portal slots from the manifest's company links, mapped by their labels (02 §8: Billing, Usage, Limits → billing;
 * Keys, Key, Credentials, API settings → keys; Dashboard, Console → console; Account → login). "Rate limits" and "The sync"
 * are not slots (03 §1, 04 §5). Bluehost's Console is built from CPANEL_HOST ("Site settings", 03 §14.2, 14.6a §3).
 * type => [['label', 'url', 'tag'], …] (several where the provider has several, the first is the slot's link).
 */
function tv_mc_slots(array $man): array
{
    $out = ['billing' => [], 'keys' => [], 'console' => [], 'login' => []];
    $map = ['billing' => '/\b(billing|usage|limits)\b/i', 'keys' => '/\b(keys?|credentials|api settings)\b/i', 'console' => '/\b(dashboard|console)\b/i', 'login' => '/\baccount\b/i'];
    require_once __DIR__ . '/../lib/OutsideServices.php';
    foreach ($man['links'] as $l) {
        [$lab, $url] = array_pad((array) $l, 2, '');
        if (preg_match('/^(rate limits|the sync)$/i', trim((string) $lab))) continue;
        if (($man['key'] ?? '') === 'bluehost' && preg_match('/cpanel/i', (string) $lab)) continue;   // folded into Sign in · Account (14.6a §3, §6)
        foreach ($map as $type => $re) if (preg_match($re, (string) $lab)) {
            $u = method_exists('OutsideServices', 'link') ? OutsideServices::link((string) $url) : (string) $url;
            if ($u !== '' && !str_starts_with($u, '{')) $out[$type][] = [(string) $lab, $u, 'documented'];
            break;
        }
    }
    // a portal page documented outside the company links, by its release (02 §8 portal_links; Fly's Tokens, release #97, 03 §25.1)
    foreach ((array) ($man['portal_links'] ?? []) as $type => $pl) if (isset($out[$type]) && is_array($pl) && (string) ($pl[1] ?? '') !== '')
        array_unshift($out[$type], [(string) $pl[0], (string) $pl[1], 'documented:' . (int) ($pl[2] ?? 212)]);
    if (($man['key'] ?? '') === 'bluehost') {
        $h = trim((string) (getenv('CPANEL_HOST') ?: ''));
        if ($h !== '' && preg_match('/^[a-z0-9.-]+$/i', $h)) $out['console'] = [['cPanel', 'https://' . $h . ':2083', 'site']];
    }
    return $out;
}

/** The company's links that are no portal slot (02 §8's mapping) and no row of their own: [[label, url], …]. The sync is
 *  GitHub's sync row (03 §12.1); Bluehost's cPanel folds into Sign in · Account (03 14.6a §6). */
function tv_mc_pages(array $man): array
{
    $out = []; $slot = '/\b(billing|usage|limits|keys?|credentials|api settings|dashboard|console|account)\b/i';
    foreach ($man['links'] as $l) {
        [$lab, $url] = array_pad((array) $l, 2, ''); $lab = (string) $lab;
        if (strcasecmp(trim($lab), 'The sync') === 0 || (($man['key'] ?? '') === 'bluehost' && stripos($lab, 'cpanel') !== false)) continue;
        if (preg_match($slot, $lab) && !preg_match('/^rate limits$/i', trim($lab))) continue;
        $u = method_exists('OutsideServices', 'link') ? OutsideServices::link((string) $url) : (string) $url;
        if ($u !== '' && !str_starts_with($u, '{')) $out[] = [$lab, $u];
    }
    return $out;
}

/** The release a documented link came with: 'documented:<n>', else #212 (the release that carried service-defs.php, 03 §1). */
function tv_mc_rel(string $tag): int { return preg_match('/^documented:(\d+)$/', $tag, $m) ? (int) $m[1] : 212; }

/** A source tag (04 rule 3, locked by Jason): Documented · release #212 (service-defs.php's release), or Site settings. */
function tv_mc_tag(string $tag, string $provider): string
{
    if ($tag === 'site') return '<span class="mc-tag" title="Built from a setting in the site\'s .env">Site settings</span>';
    return '<span class="mc-tag" title="Written by a Claude session from ' . tv_w_e($provider) . '\'s own pages">Documented · release #' . tv_mc_rel($tag) . '</span>';
}

/** Jason's stage for a 0173 stage (02 §3a's table): [n, name, words]. keyin waits for Activate: 3 once its pre-flight passed, else 2. */
function tv_mc_stage(string $sg, string $pf, bool $maint): array
{
    if ($maint) return [6, TV_MC_STAGES[6]];
    switch ($sg) {
        case 'proposed': case 'added': return [1, TV_MC_STAGES[1]];
        case 'keyin': return $pf === 'ok' ? [3, TV_MC_STAGES[3]] : [2, TV_MC_STAGES[2]];
        case 'retired': return [8, TV_MC_STAGES[8] . ' (Retired: its code remains)'];
        case 'removed': return [8, TV_MC_STAGES[8] . ' (Removed)'];
        default: return [5, TV_MC_STAGES[5]];
    }
}

/** A part's open alarms (its own process, or the component the engine put it on: P2). */
function tv_mc_alarms(PDO $pdo, array $procs): array
{
    $procs = array_values(array_unique(array_filter($procs, 'strlen'))); if (!$procs) return [];
    try {
        require_once __DIR__ . '/../lib/ControlAlarms.php'; ControlAlarms::ensure($pdo);
        $in = implode(',', array_fill(0, count($procs), '?'));
        $hasComp = ControlAlarms::engineOn($pdo);
        $st = $pdo->prepare('SELECT * FROM control_alarms WHERE cleared_at IS NULL AND (proc IN (' . $in . ')' . ($hasComp ? ' OR component IN (' . $in . ')' : '') . ") ORDER BY FIELD(level, 'trip', 'warning', 'notice'), last_at DESC");
        $st->execute($hasComp ? array_merge($procs, $procs) : $procs);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) $r['fix'] = (string) ($r['fix'] ?? '') !== '' ? (json_decode((string) $r['fix'], true) ?: []) : [];
        return $rows;
    } catch (Throwable $x) { error_log('module card alarms: ' . $x->getMessage()); return []; }
}

/**
 * A part's run-state now (02 §3b, the first that applies wins): [light, first words, rest]. light: ok · hold · bad · off ·
 * maint · grey · none. $a: its open alarms; $issue: its own open service issue row or null.
 */
function tv_mc_state(PDO $pdo, string $proc, array $a, ?array $issue, ?string $off, ?array $maint, bool $retired, ?array $pfRow, string $waitOn = ''): array
{
    $sw = $off !== null ? 'Off' : 'On';
    if ($retired) return ['none', 'Retired', 'Kept as a record: nothing calls it; Bring it back undoes it.'];
    if ($maint) return ['maint', 'In maintenance', 'Back by ' . tv_mc_t((string) ($maint['until_at'] ?? ''))];
    if ($off !== null) return ['off', 'Off', $off];   // a person's Off is never a failure (R109)
    $trip = null; $warn = null;
    foreach ($a as $x) { if ($x['level'] === 'trip' && !empty($x['tripped_at'])) { $trip = $trip ?? $x; } elseif (in_array($x['level'], ['trip', 'warning'], true)) { $warn = $warn ?? $x; } }
    require_once __DIR__ . '/../lib/ControlAlarms.php'; require_once __DIR__ . '/../lib/ControlEngine.php';
    if ($trip) {
        $held = ControlAlarms::stoppedWords($trip);
        $words = ControlEngine::status($trip, $held);
        if ($words === '') $words = $held !== '' ? 'Paused by the system (' . $held . ')' : 'Down: the system is still trying';
        $seen = $trip['ack_at'] !== null ? ' (seen by ' . (string) ($trip['ack_name'] ?? 'staff') . ')' : '';   // the light stays red after Acknowledge (03 §4, g4 F1)
        return ['bad', $sw . ' · ' . (str_starts_with($words, 'Paused by the system') || str_starts_with($words, 'Down') || str_starts_with($words, 'Tripped') ? '' : 'Paused by the system: ') . $words, '1 trip' . $seen];
    }
    if ($issue && !in_array((string) ($issue['kind'] ?? ''), ['timeout', 'server', 'rate', 'malformed'], true)) return ['bad', $sw . ' · Paused by the system: calls to ' . (string) $issue['host'] . ' wait for a person', 'Issue #' . (int) $issue['id']];   // a stop kind holds at once (row 29)
    if ($waitOn !== '') return ['hold', $sw . ' · Paused by the system: waiting on ' . $waitOn, ''];
    if ($warn || $issue) {
        $words = $warn ? ControlEngine::status($warn, '') : '';
        return ['hold', $sw . ' · Warning (the system is trying)', $words !== '' ? $words : ($warn ? (string) $warn['title'] : 'Issue #' . (int) $issue['id'] . ' at ' . (string) $issue['host'])];
    }
    if (!$pfRow) return ['grey', $sw . ' · Not checked yet', ''];
    if (($pfRow['s'] ?? '') === 'bad') return ['hold', $sw . ' · Warning: its last check failed', (string) ($pfRow['said'] ?? '')];
    return ['ok', $sw . ' · Working', ''];
}

/** A moment, labelled with its zone (CLAUDE.md "Times where they happened"): UTC 'Y-m-d H:i:s' or a unix time. */
function tv_mc_t($at): string
{
    $ts = is_int($at) ? $at : (int) strtotime((string) $at . ' UTC');
    if ($ts <= 0) return 'not read yet';
    return '<time datetime="' . gmdate('Y-m-d\TH:i:s\Z', $ts) . '" data-tv-time data-tz="UTC">' . gmdate('M j, H:i', $ts) . ' UTC</time>';
}

/** The light: never colour alone, a dot and its word (03 §1 Zone 1). */
function tv_mc_light(string $light, string $words): string
{
    require_once __DIR__ . '/../lib/Thread.php';   // the engine's words carry their moments as {t:…}: drawn as a labelled <time>
    return '<span class="mc-light mc-l-' . tv_w_e($light) . '"><i aria-hidden="true"></i>' . Thread::times(tv_w_e($words)) . '</span>';
}

/** A board action button (posts {action, id} to /admin/api/config/control, busy while it works, the answer beside it). */
function tv_mc_btn(string $act, string $id, string $label, bool $can = true, string $why = '', string $confirm = '', bool $danger = false, string $cls = 'ak-b'): string
{
    $e = 'tv_w_e';
    return '<button type="button" class="' . $e($cls) . '" data-mc-act="' . $e($act) . '" data-mc-id="' . $e($id) . '"'
        . ($confirm !== '' ? ' data-confirm="' . $e($confirm) . '" data-yes="' . $e($label) . '"' . ($danger ? ' data-danger' : '') : '')
        . (!$can ? ' disabled title="' . $e($why) . '"' : '') . '>' . $e($label) . '</button>' . (!$can && $why !== '' ? '<small class="mc-why">' . $e($why) . '</small>' : '');
}

/** Where a fault's provider page is (02 group G, rows 128–133): key → keys; quota or budget → billing; host or their trouble → console; else login. */
function tv_mc_route(string $cause, bool $host): string
{
    if ($cause === 'key') return 'keys';
    if ($cause === 'quota') return 'billing';
    if ($host || in_array($cause, ['theirs', 'blocked', 'host'], true)) return 'console';
    return 'login';
}

/**
 * The card. $key: a service-defs.php key ('railway', 'tavily'…) or a worker part ('wk:video-worker'). $ctx (all optional,
 * as the register already holds them): 'r' (Operations::register's row), 'stage' ([0173 stage, words]), 'csrf', 'tz',
 * 'can', 'why' (the register's permission), 'pf' (Preflight::results), 'nested' (drawn inside another card).
 */
function tv_module_card(PDO $pdo, string $key, array $ctx = []): string
{
    $e = 'tv_w_e';
    $man = tv_mc_manifest($key);
    if (!$man) return '<p data-tv-status="bad" data-tv-label="No such module:">' . $e($key) . ' isn\'t in the register.</p>';
    foreach (['Automation', 'Operations', 'ServiceStages', 'ControlRegister', 'ControlAlarms', 'ControlEngine', 'Preflight', 'ServiceHealth', 'Thread', 'ControlMaintenance', 'Thresholds', 'OutsideServices', 'SystemStart'] as $lib)
        if (is_file(__DIR__ . '/../lib/' . $lib . '.php')) require_once __DIR__ . '/../lib/' . $lib . '.php';
    $isWk = isset($man['worker']); $name = $man['name']; $prov = $man['provider']; $role = $man['role'];
    $proc = $isWk ? $key : 'svc:' . $key;
    $csrf = (string) ($ctx['csrf'] ?? ($_SESSION['csrf_token'] ?? '')); $tz = (string) ($ctx['tz'] ?? '');
    $can = (bool) ($ctx['can'] ?? true); $why = (string) ($ctx['why'] ?? '');
    static $pfOnce = null;   // one read of the pre-flight's results per page, however many cards it draws
    $pf = $ctx['pf'] ?? null; if (!is_array($pf)) { if ($pfOnce === null) { try { $pfOnce = Preflight::results($pdo); } catch (Throwable $x) { $pfOnce = []; } } $pf = $pfOnce; }
    $r = $ctx['r'] ?? null;
    if (!$isWk && !is_array($r)) { try { $r = Operations::register($pdo)[$key] ?? null; } catch (Throwable $x) { $r = null; } }
    $stage = $ctx['stage'] ?? null;
    if (!$isWk && !is_array($stage)) { try { $stage = ServiceStages::all($pdo)[$key] ?? ['active', '']; } catch (Throwable $x) { $stage = ['active', '']; } }
    if ($isWk) $stage = ['active', ''];
    [$sg, $sgw] = array_pad((array) $stage, 2, '');
    [$canAu, $whyAu] = Access::can('config.automation', ['min' => 'executive', 'depts' => []]);
    [$canPf, $whyPf] = method_exists('Preflight', 'can') ? Preflight::can() : [true, ''];
    $line = $isWk ? (string) (ControlRegister::WORKERS[$man['worker']][3] ?? '') : 'svc:' . $key;
    $pfRow = $line !== '' ? ($pf[$line] ?? null) : null;
    $off = $isWk ? null : Automation::serviceOff($pdo, $key);
    $maint = null; try { $maint = ControlMaintenance::outFor($pdo, $proc); } catch (Throwable $x) {}
    $retired = in_array($sg, ['retired', 'removed'], true);
    $issues = []; if (!$isWk) { try { $issues = array_values(array_filter(ServiceHealth::openAll($pdo), fn ($i) => (string) $i['service'] === $key)); } catch (Throwable $x) {} }
    // its parts (a Host's): procs whose faults move zone 4 up (03 §1)
    $parts = tv_mc_parts($pdo, $key, $man);
    $alarms = tv_mc_alarms($pdo, array_merge([$proc], array_keys($parts)));
    $own = array_values(array_filter($alarms, fn ($a) => $a['proc'] === $proc || ($a['component'] ?? '') === $proc));
    $hostTrip = '';
    if ($isWk) foreach (tv_mc_alarms($pdo, ['svc:' . $man['hosted_by']]) as $ha) if ($ha['level'] === 'trip' && !empty($ha['tripped_at'])) { $hostTrip = $prov; break; }
    [$light, $first, $rest] = tv_mc_state($pdo, $proc, $own, $issues[0] ?? null, $off, $maint, $retired, $pfRow, $hostTrip);
    if ($isWk && $light === 'grey') {   // a worker's check is its heartbeat or its last run (the engine's, DB reads only: 02 §2.7)
        $ck = ControlEngine::check($pdo, $key);
        if ($ck['pass'] === true) { $light = 'ok'; $first = 'On · Working'; $rest = (string) $ck['proof']; }
        elseif ($ck['pass'] === false && !str_starts_with((string) $ck['proof'], 'Its check failed to run')) { $light = 'hold'; $first = 'On · Warning: no heartbeat in its window'; $rest = (string) $ck['proof']; }
        elseif ($ck['pass'] === false) $rest = 'Its heartbeat couldn\'t be read yet.';   // a reading that couldn't be taken is "not checked yet", never a fault (02 §3b; P4)
    }
    // a Host's light is its worst part's (02 §3b roll-up, R9): "On · Paused by the system: the crawler (a trip)" (03 §5 c2)
    if (!$isWk && in_array($light, ['ok', 'grey', 'hold'], true)) foreach ($alarms as $pa) {
        if ($pa['proc'] === $proc || ($pa['component'] ?? '') === $proc || !isset($parts[$pa['proc']]) && !isset($parts[(string) ($pa['component'] ?? '')])) continue;
        $pn = (string) ($parts[$pa['proc']]['name'] ?? $parts[(string) ($pa['component'] ?? '')]['name'] ?? $pa['proc']);
        if ($pa['level'] === 'trip' && !empty($pa['tripped_at'])) { $light = 'bad'; $first = 'On · Paused by the system: ' . $pn . ' (a trip)'; $rest = '1 trip' . ($pa['ack_at'] !== null ? ' (seen by ' . (string) ($pa['ack_name'] ?? 'staff') . ')' : ''); break; }
        if ($light !== 'hold') { $light = 'hold'; $first = 'On · Warning: ' . $pn; $rest = ''; }
    }
    $faultOpen = (bool) ($alarms || $issues);
    $dutyMap = ControlRegister::dutyMap($pdo);
    $duties = []; foreach (array_merge([$proc], $key === 'bluehost' ? array_keys(TV_MC_BLUEHOST_PARTS) : []) as $p) if (isset($dutyMap[$p])) $duties[$dutyMap[$p]] = true;
    $slots = tv_mc_slots($man);
    // which account it signs in as (02 §10; Jason, 2026-11-23): an email is no secret, so it shows; its password stays in the Vault
    $signin = null; if (!str_starts_with($key, 'wk:')) { require_once __DIR__ . '/../lib/SignIns.php'; $signin = SignIns::get($pdo, $key); }
    $tripA = null; foreach ($alarms as $x) if ($x['level'] === 'trip' && !empty($x['tripped_at'])) { $tripA = $x; break; }

    // ---- zone 1: identity and role ----
    $roleWord = ['host' => 'Host', 'source' => 'Source', 'service' => 'Service'][$role] ?? 'Role not set';
    $crit = $duties ? 'Critical: ' . implode(', ', array_keys($duties)) : ($role === 'host' ? 'Not critical by itself' : 'Not critical: no duty stops without it');   // P8; 03 §1, §26
    [$sn, $sname] = tv_mc_stage($sg, (string) ($pfRow['s'] ?? ''), (bool) $maint);
    // zone 4 first, so "Next allowed" lists the actions it really offers (03 §2)
    [$z4, $allowed] = tv_mc_zone4($pdo, $key, $man, $proc, $slots, $alarms, $issues, $r, $sg, $off, $line, $pf, $csrf, $can, $why, $canAu, (string) $whyAu, $canPf, (string) $whyPf);
    $z1 = '<section class="mc-z mc-z1" data-copy="' . $e($name . ': identity and role') . '"><h3 class="mc-zh">Identity and role</h3>'
        . '<p class="mc-id"><b class="mc-name">' . $e($name) . '</b> <span class="mc-role">' . $e($roleWord) . '</span>'
        . ($man['hosted_by'] !== '' ? ' <span class="mc-sub">hosted on ' . $e($prov) . '</span>' : '') . ' <span class="mc-crit">' . $e($crit) . '</span></p>'
        . (!str_starts_with($key, 'wk:') ? '<div class="mc-signin">' . ($signin && $signin['email'] !== '' ? 'Signs in as: <b>' . $e($signin['email']) . '</b>' . ($signin['project'] !== '' ? ' · project <code>' . $e($signin['project']) . '</code>' : '')
            : 'Signs in as: <span class="mc-sub">not recorded</span> <a class="underline" href="#mc-' . $e(str_replace(':', '-', $key)) . '-setup">Record it</a>')
            . ($signin && SignIns::canUndo($signin) && function_exists('tv_ops_form') ? ' ' . tv_ops_form($csrf, 'svc_signin_undo', 'Undo', ['ref' => $key], (($signin['prev']['email'] ?? '') !== '' ? 'Put back the sign-in ' . $signin['prev']['email'] . ' (instead of ' . $signin['email'] . ')?' : 'Forget the recorded sign-in (' . $signin['email'] . ')?'), '', $can, $why, 'ak-b cb-xs') : '') . '</div>' : '')
        . '<p class="mc-state">' . tv_mc_light($light, $first) . ($rest !== '' ? ' <span class="mc-rest">' . Thread::times($e($rest)) . '</span>' : '') . '</p>'
        . '<p class="mc-stage"><b>Stage ' . $sn . ' of 8 · ' . $e($sname) . '</b>' . ($sgw !== '' ? ' <span class="mc-sub">' . $e($sgw) . '</span>' : '')
        . '<br><span class="mc-sub">Next allowed: ' . $e($allowed ? implode(' · ', $allowed) : 'nothing from here') . '</span></p>'
        . tv_mc_setup($man, $slots, $signin, $csrf, $can, $why, $key)
        . tv_mc_spend($pdo, $key, $man, $r, $slots)
        . tv_mc_radius_html($pdo, $key, $man, $parts)
        . '</section>';

    // ---- zone 2: capabilities and feeds (for a Host: the parts it hosts) ----
    $z2 = '<section class="mc-z mc-z2" data-copy="' . $e($name . ': capabilities and feeds') . '"><h3 class="mc-zh">' . ($role === 'host' ? 'What it hosts and feeds' : 'Capabilities and feeds') . '</h3>'
        . '<p class="mc-sub">' . $e($man['purpose']) . '</p>';
    if (is_array($r)) {
        $z2 .= '<dl class="ak-note os-dl"><dt>Used by</dt><dd>' . ($r['workflows'] ? $e(implode(', ', array_map(fn ($wf) => $wf . ' ' . (Automation::WORKFLOWS[$wf][0] ?? ''), $r['workflows']))) : 'No workflow: people\'s own presses and business operations')
            . ($r['tasks'] ? '<br><small>Tasks: ' . $e(implode(', ', $r['tasks'])) . '</small>' : '') . ($r['jobs'] ? '<br><small>Jobs: ' . $e(implode(', ', $r['jobs'])) . '</small>' : '') . '</dd>'
            . ((string) ($r['produced'] ?? '') !== '' ? '<dt>Produced</dt><dd>' . $e($r['produced']) . '</dd>' : '') . '</dl>';
    }
    // the company's other pages, not portal slots (03 §24.1 "pages, linked on their zone 2 lines, not slots"; §26 "any other
    // link is 'Its pages'"; Rate limits on its own line, 03 §1): moved here from the register's "On <company>" list
    $other = tv_mc_pages($man);
    if ($other) $z2 .= '<p class="mc-row"><b>Its pages</b> ' . implode(' ', array_map(fn ($x) => '<a class="os-go" href="' . $e($x[1]) . '" target="_blank" rel="noopener">' . $e($x[0]) . ' ↗</a>', $other)) . '</p>';
    if ($key === 'github') foreach ($man['links'] as [$lab, $url]) if (strcasecmp((string) $lab, 'The sync') === 0) {   // 03 §12.1: the sync's runs on its row, not a slot
        $w = []; try { $w = Operations::watched($pdo)['sync'] ?? []; } catch (Throwable $x) {}
        $z2 .= '<div class="mc-part"><p><b>The sync</b> <span class="mc-sub">sync.yml, 06:00 and 18:00 UTC or on demand → the live mirror and OneDrive</span></p>'
            . '<p class="mc-sub">Last look: ' . ($w ? $e((string) ($w['what'] ?? '')) . ' (' . tv_mc_t((string) ($w['at'] ?? '')) . ')' : 'not read yet') . ' · <a class="os-go" href="' . $e((string) $url) . '" target="_blank" rel="noopener">Its runs ↗</a></p></div>';
    }
    foreach ($parts as $pp => $pt) $z2 .= tv_mc_part_row($pdo, $pp, $pt, $alarms, $pf, $canPf, (string) $whyPf, $faultOpen);
    if ($isWk && $man['worker'] === 'video-worker') $z2 .= '<p class="mc-sub">Its endpoints: <code>/api/video/jobs.php</code>, <code>/api/video/results.php</code> (03 §23.1). Its storage: the file store, originals and copies side by side, on the AWS card.</p>';
    $z2 .= '</section>';

    // ---- zone 3: sensors and key readiness ----
    $z3 = '<section class="mc-z mc-z3" data-copy="' . $e($name . ': sensors and keys') . '"><h3 class="mc-zh">Sensors and key readiness</h3>';
    $z3 .= '<p class="mc-row"><b>Its check</b> ' . ($line === '' ? '<span class="mc-sub">' . ($isWk ? 'No pre-flight line: Check now runs its own check (its heartbeat, read from its asks for work).' : 'No check of its own yet.') . '</span>'
        : ($pfRow ? tv_mc_light(($pfRow['s'] ?? '') === 'ok' ? 'ok' : (($pfRow['s'] ?? '') === 'bad' ? 'bad' : 'grey'), Preflight::markOf($line, (string) ($pfRow['s'] ?? ''))) . ' <span class="mc-sub">' . $e((string) ($pfRow['said'] ?? '')) . ' · ' . tv_mc_t((int) ($pfRow['at'] ?? 0)) . '</span>'
                  : tv_mc_light('grey', 'Not checked yet'))) . '</p>';
    if ($isWk) { $ck = ControlEngine::check($pdo, $key); $z3 .= '<p class="mc-row"><b>' . ($man['worker'] === 'crawler' ? 'Runs' : 'Heartbeat') . '</b> <span class="mc-sub">' . $e($ck['proof'] !== '' && !str_starts_with((string) $ck['proof'], 'Its check failed to run') ? (string) $ck['proof'] : 'not read yet') . '</span></p>'; }
    if (!$isWk) {
        $keys = is_array($r) ? (array) ($r['keys'] ?? []) : Operations::keys($key);
        $z3 .= '<dl class="ak-note os-dl"><dt id="svc-' . $e($key) . '-key">Its key</dt><dd>' . ($keys ? (function_exists('tv_ops_keys') ? tv_ops_keys($key, $keys, $csrf) : $e(implode(', ', array_column($keys, 0)))) : 'None needed') . '</dd></dl>';
    }
    if ($key === 'railway') {   // 03 §1 Zone 3: kept on Railway for other modules; each belongs to its module; presence alone is Not checked yet (Q4)
        $rw = Operations::workerKeys(); $rows = '';
        foreach (Operations::KEYS as $svc => $list) foreach ($list as [$kn, $where]) if (str_starts_with((string) $where, 'Railway')) {
            $owner = OutsideServices::COMPANIES[$svc][0] ?? $svc; $has = $rw[$kn] ?? null;
            $rows .= '<li><code>' . $e($kn) . '</code> <span class="mc-sub">' . $e($owner) . '\'s key, kept on Railway (the crawler service → Variables)</span> '
                . ($has === false ? tv_mc_light('bad', $owner . ': ' . $kn . ' isn\'t on Railway') : tv_mc_light('grey', 'Not checked yet')) . '</li>';
        }
        if ($rows !== '') $z3 .= '<p class="mc-row"><b>Kept on Railway for other modules</b> <span class="mc-sub">(each belongs to its module; its state never colours Railway)</span></p><ul class="mc-list">' . $rows . '</ul>';
    }
    $z3 .= '</section>';

    $h = '<div class="mc-card tv-bleed' . ($faultOpen ? ' mc-fault' : '') . '" id="mc-' . $e(str_replace(':', '-', $key)) . '" data-mc="' . $e($key) . '" data-copy="' . $e($name . '\'s card') . '">'
        . $z1 . ($faultOpen ? $z4 . $z2 . $z3 : $z2 . $z3 . $z4) . tv_mc_details($pdo, $key, $sg) . '</div>';
    return tv_mc_css() . $h . tv_mc_js($csrf);
}

/** The parts a Host runs (02 §2.2): proc => ['name', 'role', 'what', 'line'] (a worker, a site part, our endpoints). */
function tv_mc_parts(PDO $pdo, string $key, array $man): array
{
    $out = [];
    if ($key === 'railway') foreach (ControlRegister::WORKERS as $wk => $w) {
        $feeds = TV_MC_WORKER_FEEDS[$wk] ?? '';
        if ($wk === 'crawler') { $wfs = []; foreach (Automation::WORKFLOWS as $wf => $d) if ($wf !== 'W0' && str_contains((string) ($d[2] ?? ''), 'crawler')) $wfs[] = $wf; $feeds = 'Gathering: ' . implode(', ', $wfs); }
        $out['wk:' . $wk] = ['name' => (string) $w[0], 'role' => 'Worker', 'what' => (string) $w[1], 'feeds' => $feeds, 'line' => (string) $w[3], 'card' => true];
    }
    if ($key === 'bluehost') {
        foreach (TV_MC_BLUEHOST_PARTS as $p => $n) $out[$p] = ['name' => $n, 'role' => 'Site part', 'what' => '', 'feeds' => '', 'line' => $p, 'card' => false];
        foreach (ControlRegister::ENDPOINTS as $ep => [$what, $for]) {
            $tasks = array_values(array_filter((array) $for, fn ($f) => !str_starts_with((string) $f, '@')));
            $out['ep:' . $ep] = ['name' => '/api/' . $ep . '.php', 'role' => 'Endpoint (Core Services)', 'what' => (string) $what, 'feeds' => $tasks ? 'Serves: ' . implode(', ', $tasks) : 'The workers\' own calls', 'line' => '', 'card' => false];
        }
    }
    return $out;
}

/** One hosted part's row in zone 2: its role, what it carries, its state, Check now (03 §1 Zone 2). */
function tv_mc_part_row(PDO $pdo, string $pp, array $pt, array $alarms, array $pf, bool $canPf, string $whyPf, bool $faultOpen): string
{
    $e = 'tv_w_e';
    $mine = array_values(array_filter($alarms, fn ($a) => $a['proc'] === $pp || ($a['component'] ?? '') === $pp));
    $pfRow = $pt['line'] !== '' ? ($pf[$pt['line']] ?? null) : null;
    [$light, $first, $rest] = tv_mc_state($pdo, $pp, $mine, null, null, null, false, $pfRow);
    if (str_starts_with($pp, 'wk:') && $light === 'grey') { $ck = ControlEngine::check($pdo, $pp); if ($ck['pass'] !== null && !str_starts_with((string) $ck['proof'], 'Its check failed to run')) { $light = $ck['pass'] ? 'ok' : 'hold'; $first = $ck['pass'] ? 'On · Working' : 'On · Warning: no heartbeat in its window'; $rest = (string) $ck['proof']; } }
    $duty = ControlRegister::dutyMap($pdo)[$pp] ?? '';
    // a worker or an endpoint of ours: the engine's own check (the board's check routes wk: and ep: to ControlEngine::check, 02 §2.7)
    $hasLine = (bool) preg_match('/^(wk|ep):/', $pp) || ($pt['line'] !== '' && (isset($pf[$pt['line']]) || isset(Preflight::lines($pdo)[$pt['line']])));
    $h = '<div class="mc-part' . ($mine ? ' mc-tint-' . ($light === 'bad' ? 'bad' : 'hold') : '') . '" id="mc-part-' . $e(preg_replace('/[^a-z0-9-]/i', '-', $pp)) . '">'
        . '<p><b>' . $e($pt['name']) . '</b> <span class="mc-role mc-role-s">' . $e($pt['role']) . '</span>' . ($duty !== '' ? ' <span class="mc-crit">Critical: ' . $e($duty) . '</span>' : '') . '</p>'
        . ($pt['what'] !== '' ? '<p class="mc-sub">' . $e($pt['what']) . '</p>' : '') . ($pt['feeds'] !== '' ? '<p class="mc-sub">Feeds: ' . $e($pt['feeds']) . '</p>' : '')
        . '<p class="mc-state">' . tv_mc_light($light, $first) . ($rest !== '' ? ' <span class="mc-rest">' . Thread::times($e($rest)) . '</span>' : '') . '</p>';
    if ($mine && $faultOpen) $h .= '<p class="mc-sub">Its actions are in the thread above ↑</p>';   // 03 §1
    else $h .= '<p>' . tv_mc_btn('check', $pp, 'Check now', $hasLine && $canPf, !$hasLine ? 'It has no check of its own yet.' : $whyPf) . '</p>';
    if (!empty($pt['card'])) $h .= '<details class="mc-fold"><summary>Its card ▸</summary>' . tv_module_card($pdo, $pp, ['nested' => true]) . '</details>';
    return $h . '</div>';
}

/** The setup capture notice (03 §13: today's modules went live before the rule: grey words, never an alarm, a hold or a Pulse item). */
function tv_mc_setup(array $man, array $slots, ?array $signin = null, string $csrf = '', bool $can = false, string $canWhy = '', string $key = ''): string
{
    $e = 'tv_w_e';
    $gaps = []; $why = [];
    $signed = $signin && ($signin['email'] ?? '') !== '';
    foreach (TV_MC_SLOTS as $t => [$lab]) if (!$slots[$t]) { if ($t === 'login') { if (!$signed) $gaps[] = 'Sign in not recorded'; } else $why[] = $lab; }
    if ($why) $gaps[] = implode(', ', $why) . ': why not recorded';
    $keys = ''; foreach ($man['keys'] as $k) { [$kn, $kw] = array_pad((array) $k, 2, ''); $keys .= '<li><code>' . $e($kn) . '</code>: ' . $e($kw) . '</li>'; }
    $sl = ''; foreach (TV_MC_SLOTS as $t => [$lab]) $sl .= '<li>' . $e($lab) . ': ' . ($slots[$t] ? $e($lab . ' · ' . $slots[$t][0][0]) . ' · ' . ($slots[$t][0][2] === 'site' ? 'Site settings' : 'Documented · release #' . tv_mc_rel((string) $slots[$t][0][2])) : 'Not registered · no why yet') . '</li>';
    // the account it signs in as: a step finished here, never a dead end (Record, a one-field form; Undo for a day)
    $rec = '';
    if ($key !== '' && !str_starts_with($key, 'wk:') && function_exists('tv_ops_form')) {
        require_once __DIR__ . '/../lib/SignIns.php';
        $proj = false; foreach ((array) ($man['links'] ?? []) as $l) { $h = strtolower((string) parse_url((string) ($l[1] ?? ''), PHP_URL_HOST)); if (isset(SignIns::HINTS[$h]) && SignIns::HINTS[$h][1] !== '') $proj = true; }
        $rec = '<p><b>The account it signs in as</b></p><p class="mc-sub">' . ($signed ? 'Signs in as ' . $e((string) $signin['email']) . ($signin['project'] !== '' ? ', project ' . $e((string) $signin['project']) : '') . ' · recorded' . ((string) $signin['by'] !== '' ? ' by ' . $e((string) $signin['by']) : '') . ', <time datetime="' . $e(str_replace(' ', 'T', (string) $signin['at']) . ':00Z') . '" data-tv-time>' . $e((string) $signin['at']) . ' UTC</time>.' : 'Not recorded: its portal links open in whichever account the browser is signed in to.')
            . ' An email isn\'t a secret; its password stays in the Vault.</p>'
            . tv_ops_form($csrf, 'svc_signin', $signed ? 'Change' : 'Record', ['ref' => $key], '', '<input class="ak-in" type="email" name="email" required maxlength="190" autocomplete="off" spellcheck="false" placeholder="The account\'s sign-in email" value="' . $e($signed ? (string) $signin['email'] : '') . '" aria-label="The account\'s sign-in email">'
                . ($proj ? '<input class="ak-in" name="project" maxlength="30" autocomplete="off" spellcheck="false" placeholder="Project id (optional)" value="' . $e($signin['project'] ?? '') . '" aria-label="Its project id (optional)">' : ''), $can, $canWhy)
;
    }
    return '<details class="mc-setup" id="mc-' . $e(str_replace(':', '-', $key)) . '-setup"' . ($signed || $key === '' || str_starts_with($key, 'wk:') ? '' : ' open') . '><summary class="mc-sub">' . ($gaps ? 'Setup capture: ' . $e(implode(' · ', $gaps)) : 'Setup capture: complete') . ' ▸</summary>'
        . $rec
        . '<p class="mc-sub">Names only, never values. Credentials live only in the Vault.</p>'
        . '<p><b>Where its keys live</b></p>' . ($keys !== '' ? '<ul class="mc-list">' . $keys . '</ul>' : '<p class="mc-sub">No keys of its own.</p>')
        . '<p><b>Its slots</b></p><ul class="mc-list">' . $sl . '</ul>'
        . '<p class="mc-sub">Recording a why is to come: the manifest\'s setup_capture field (02 §8) isn\'t built yet, so a one-line why can\'t be saved here. A slot is filled by publishing it from your Vault.</p></details>';
}

/**
 * The spend chip (03 §3): "<plan> · $<spend> of $<cap> (<pct>%)", pct = round(spend ÷ cap × 100) from the two numbers
 * printed beside it. The band reached (50, 75, 95) decides Warning, 100 a trip (rows 39–40). Known price and This month
 * fold under it (03 §9).
 */
function tv_mc_spend(PDO $pdo, string $key, array $man, ?array $r, array $slots): string
{
    $e = 'tv_w_e';
    $plan = trim((string) preg_split('/(?<=[a-z0-9)])[.;](\s|$)/i', $man['pricing'], 2)[0]);
    $best = null; $txt = [];
    foreach ((array) ($r['use'] ?? []) as $u) {
        $used = round((float) $u['used'], 2); $cap = round((float) $u['cap'], 2);
        $pct = $cap > 0 ? (int) round($used / $cap * 100) : null;
        $txt[] = $u['money'] ? '$' . number_format($used, 2) . ($cap > 0 ? ' of $' . number_format($cap, 2) . ' (' . $pct . '%)' : '') : number_format($used) . ($cap > 0 ? ' of ' . number_format($cap) . ' (' . $pct . '%)' : '') . ' ' . (string) $u['unit'];
        if ($pct !== null && ($best === null || $pct > $best)) $best = $pct;
    }
    $tone = $best === null ? 'grey' : ($best >= 100 ? 'bad' : ($best >= 50 ? 'hold' : 'ok'));
    $bill = $slots['billing'][0] ?? null;
    $h = '<p class="mc-spend mc-l-' . $tone . '"><b>' . $e($plan !== '' ? $plan : 'No plan recorded') . '</b> · ' . ($txt ? $e(implode(' · ', $txt)) : 'Nothing counted yet')
        . ' · ' . ($bill ? '<a class="os-go" href="' . $e($bill[1]) . '" target="_blank" rel="noopener">Billing' . (strcasecmp($bill[0], 'Billing') ? ' · ' . $e($bill[0]) : '') . ' ↗</a>' : 'Billing: No billing page registered for ' . $e($man['provider']) . ' yet.') . '</p>';
    if (is_array($r)) {
        $caps = (array) ($r['caps'] ?? []);
        $h .= '<details class="mc-fold"><summary>Its numbers ▸</summary><dl class="ak-note os-dl"><dt>Known price</dt><dd>' . $e((string) $r['price']) . '</dd>'
            . '<dt' . ($caps ? '' : ' id="svc-' . $e($key) . '-caps"') . '>This month</dt><dd>' . ($r['use'] ? implode('<br>', array_map(fn ($u) => $e($u['what']) . ': ' . ($u['money'] ? '$' . number_format((float) $u['used'], 2) . ($u['cap'] > 0 ? ' of $' . number_format((float) $u['cap'], 2) : '') : number_format((float) $u['used']) . ($u['cap'] > 0 ? ' of ' . number_format((float) $u['cap']) : '') . ' ' . $u['unit']), $r['use'])) . '<br><small>' . $e((string) $r['provider']) . '</small>' : 'Nothing counted yet') . '</dd></dl></details>';
    }
    return $h;
}

/**
 * Its Incident Radius if it fails (02 §6): Held · On a backup · Unaffected, each list one tap away. A Host: what it hosts;
 * a Source or Service: its calls, and each task that uses it either on its next working source or waiting; Unaffected:
 * every other workflow. The manifest's incident_radius_rules override a list (02 §8); the workers' are TV_MC_WORKER_RADIUS.
 */
function tv_mc_radius(PDO $pdo, string $key, array $man, array $parts): array
{
    if (isset(TV_MC_WORKER_RADIUS[$key])) return TV_MC_WORKER_RADIUS[$key];
    [$held, $back, $un] = tv_mc_radius_computed($pdo, $key, $man, $parts);
    // the manifest's rare overrides (02 §8 incident_radius_rules): a list it names stands as written
    $rr = (array) ($man['radius_rules'] ?? []);
    return [array_key_exists('held', $rr) ? (array) $rr['held'] : $held, array_key_exists('backup', $rr) ? (array) $rr['backup'] : $back, array_key_exists('unaffected', $rr) ? (array) $rr['unaffected'] : $un];
}

/** The radius computed from the register (02 §6): a Host's workers; a Source's or Service's calls and the tasks that use it. */
function tv_mc_radius_computed(PDO $pdo, string $key, array $man, array $parts): array
{
    $held = []; $back = []; $un = [];
    if ($key === 'railway') {
        foreach ($parts as $pp => $pt) $held[] = TV_MC_WORKER_HELD[substr($pp, 3)] ?? ($pt['name'] . ': all gathering (' . preg_replace('/^Gathering: /', '', $pt['feeds']) . ')');
        return [$held, $back, ['Everything already stored']];
    }
    $held[] = 'Calls to ' . $man['name'] . ' (' . rtrim($man['purpose'], '.') . ')';
    $tasks = (array) (OutsideServices::CONTROLS[$key]['tasks'] ?? []); $tw = ControlRegister::taskWorkflows($pdo); $touched = [];
    $res = []; try { $res = Preflight::results($pdo); } catch (Throwable $x) {}
    foreach ($tasks as $tk) {
        $wf = (string) ($tw[$tk] ?? ''); if ($wf === '') continue; $touched[$wf] = true;
        $b = ControlEngine::backupFor($pdo, $key, $wf, $res);
        if ($b !== '') $back[] = $tk . ' (' . $wf . ') on ' . (OutsideServices::COMPANIES[$b][0] ?? $b); else $held[] = $tk . ' (' . $wf . ')';
    }
    $duty = ControlRegister::dutyMap($pdo)['svc:' . $key] ?? '';
    if ($duty !== '') $held[] = ControlRegister::DUTIES[$duty][0] . ': ' . ControlRegister::DUTIES[$duty][1];   // 02 §6 step 4
    foreach (Automation::WORKFLOWS as $wf => $d) if (!isset($touched[$wf])) $un[] = $wf . ' ' . $d[0];
    $un[] = 'Everything already stored';
    return [$held, $back, $un];
}

function tv_mc_radius_html(PDO $pdo, string $key, array $man, array $parts): string
{
    $e = 'tv_w_e';
    [$held, $back, $un] = tv_mc_radius($pdo, $key, $man, $parts);
    $list = fn (string $t, array $xs, string $c) => '<details class="mc-rl mc-rl-' . $c . '"><summary>' . $e($t) . ' (' . count($xs) . ') ▸</summary>' . ($xs ? '<ul class="mc-list">' . implode('', array_map(fn ($x) => '<li>' . $e($x) . '</li>', $xs)) . '</ul>' : '<p class="mc-sub">none</p>') . '</details>';
    return '<details class="mc-fold mc-radius" data-copy="' . $e($man['name'] . ': its Incident Radius') . '"><summary>If ' . $e($man['name']) . ' itself is down: its Incident Radius ▸</summary><div class="mc-rad">'
        . $list('Held', $held, 'held') . $list('On a backup', $back, 'back') . $list('Unaffected', $un, 'un') . '</div></details>';
}

/**
 * Zone 4: the thread of each open fault, then two rows that never mix (02 PL; 04 §6): On our system (Fix it first, only for
 * a cause a person can fix, R117; their trouble gets no Fix it, only Check it now and Pause it, R118) and At <Provider>
 * (the four slots). Returns [html, the labels "Next allowed" lists].
 */
function tv_mc_zone4(PDO $pdo, string $key, array $man, string $proc, array $slots, array $alarms, array $issues, ?array $r, string $sg, ?string $off, string $line, array $pf, string $csrf, bool $can, string $why, bool $canAu, string $whyAu, bool $canPf, string $whyPf): array
{
    require_once __DIR__ . '/../lib/SignIns.php'; $si = str_starts_with($key, 'wk:') ? null : SignIns::get($pdo, $key);   // its portal links open in that account
    $e = 'tv_w_e'; $name = $man['name']; $prov = $man['provider']; $isWk = isset($man['worker']);
    $isHost = $man['role'] === 'host' || $isWk;
    $threads = ''; $ours = []; $allowed = []; $fixType = ''; $fixHtml = ''; $theirs = false;
    // each open issue (ServiceHealth::facts) and each open alarm that isn't an issue's (ControlAlarms::facts): its thread's words
    $issueAlarm = [];
    foreach ($issues as $i) {
        $f = ServiceHealth::facts($pdo, $i); if (!empty($f['alarm'])) $issueAlarm[(int) $f['alarm']['id']] = true;
        $threads .= '<div class="ops-issue mc-th" id="issue-' . (int) $i['id'] . '">' . Thread::html($f + ['pill_class' => 'tv-th-pill tv-th-' . ($f['level'] === 'trip' ? 'trip' : 'warning')], 'card', '') . '</div>';
        $fx = Thread::fixOf($f);
        if (!empty($f['theirs'])) $theirs = true;
        if ($fx && $fixHtml === '') { if (str_starts_with($fx['url'], '/admin/config/register#')) $fx['url'] = substr($fx['url'], 22); $fixHtml = Thread::fixButton($fx, 'ak-b go'); $fixType = tv_mc_route($fx['cause'], false); }
        if (!empty($f['trip']) && !empty($f['alarm'])) { $ours[] = tv_mc_btn('resolve', (string) (int) $f['alarm']['id'], 'Check and start again', $canPf, $whyPf, '', false, 'ak-b go'); $allowed[] = 'Check and start again'; }
        elseif ((string) $i['kind'] !== 'rate') { $ours[] = tv_mc_btn('svccheck', (string) (int) $i['id'], 'Check it now', $canPf, $whyPf); $allowed[] = 'Check it now'; }   // a 429 waits its turn (row 130)
        $ours[] = '<a class="ak-b" href="/admin/pulse/stream#case=' . rawurlencode(!empty($f['alarm']) ? 'alarm:' . (int) $f['alarm']['id'] : 'svc:' . (int) $i['id']) . '" data-peek-frame>Its case in Pulse</a>';
    }
    foreach ($alarms as $a) {
        if (isset($issueAlarm[(int) $a['id']])) continue;
        $f = ControlAlarms::facts($pdo, $a); $trip = $a['level'] === 'trip' && !empty($a['tripped_at']);
        $ir = (int) ($a['incident_id'] ?? 0);
        $threads .= '<div class="mc-th" id="mc-alarm-' . (int) $a['id'] . '">' . Thread::html($f + ['pill_class' => 'tv-th-pill tv-th-' . ($trip ? 'trip' : 'warning')], 'card', '')
            . ($ir ? '<p class="mc-sub">Its Incident Report: IR-' . sprintf('%04d', $ir) . '</p>' : '') . '</div>';
        $fx = Thread::fixOf($f);
        if (!empty($f['theirs'])) $theirs = true;
        $partHost = $isHost || preg_match('/^(wk|ep|site):/', (string) $a['proc']);
        if ($fx && $fixHtml === '') { if (str_starts_with($fx['url'], '/admin/config/register#')) $fx['url'] = substr($fx['url'], 22); $fixHtml = Thread::fixButton($fx, 'ak-b go'); $fixType = tv_mc_route($fx['cause'], false); }
        elseif (!$fx && empty($f['theirs']) && $partHost && $fixType === '') $fixType = 'console';   // a host or worker fault: Fix it lands on its console slot (03 §1's table; row 131)
        if ($trip) {
            $ours[] = tv_mc_btn('resolve', (string) (int) $a['id'], 'Check and start again', $canPf, $whyPf, '', false, 'ak-b go'); $allowed[] = 'Check and start again';
            $ours[] = $a['ack_at'] === null ? tv_mc_btn('ack', (string) (int) $a['id'], 'Acknowledge') : tv_mc_btn('unack', (string) (int) $a['id'], 'Undo Acknowledge');   // a trip only, never a warning (R80)
            if ($a['ack_at'] === null) $allowed[] = 'Acknowledge';
        }
    }
    // a host fault's Fix it lands on its slot on this card (03 §1: "Fix it names its slot and never borrows another")
    if ($fixHtml === '' && $fixType !== '') $fixHtml = '<a class="ak-b go tv-fix" href="#mc-' . $e(str_replace(':', '-', $key)) . '-slot-' . $fixType . '" data-fix="' . $e($fixType) . '">Fix it</a> ';
    if ($fixHtml !== '') array_unshift($ours, $fixHtml);
    // the board's actions with their guards
    $hasLine = $isWk || ($line !== '' && (isset($pf[$line]) || isset(Preflight::lines($pdo)[$line])));   // a worker: the engine's check
    $ours[] = tv_mc_btn('check', $proc, 'Check now', $hasLine && $canPf, !$hasLine ? 'It has no check of its own yet.' : $whyPf); if ($hasLine && $canPf) $allowed[] = 'Check now';
    if (!$isWk) {
        $np = Automation::NOT_PAUSABLE[$key] ?? null;
        $st = '<div class="os-stage" id="stage-' . $e($key) . '">';   // the register's stage buttons, kept with their anchor (0173 §2)
        if ($np !== null) $st .= '<small class="mc-why">Not paused from here: its calls are business operations (' . $e($np) . ').</small>';
        elseif ($sg === 'retired') { $st .= tv_ops_form($csrf, 'svc_bringback', 'Bring it back', ['ref' => $key], 'Bring ' . $name . ' back? It stays paused until it\'s activated, through its check.', '', $canAu, $whyAu); if ($canAu) $allowed[] = 'Bring it back'; }
        elseif ($off === null) { $st .= tv_ops_form($csrf, 'svc_pause', 'Pause', ['ref' => $key], '', '<input class="ak-in" name="why" maxlength="200" required placeholder="Why pause it" data-spell>', $canAu, $whyAu); if ($canAu) $allowed[] = 'Pause'; }
        else { $st .= tv_ops_form($csrf, 'svc_activate', 'Activate', ['ref' => $key], 'Activate ' . $name . '? The switch-on check runs first; a red line keeps it paused, with the reason.', '', $canAu && $sg !== 'added', $sg === 'added' ? 'It needs its key first.' : $whyAu, 'ak-b go'); if ($canAu && $sg !== 'added') $allowed[] = 'Activate'; }
        if ($np === null && $sg !== 'retired') { $st .= '<details class="os-ed os-retire"><summary>Retire it…</summary>' . str_replace(' data-yes="Retire"', ' data-yes="Retire" data-danger', tv_ops_form($csrf, 'svc_retire', 'Retire', ['ref' => $key], 'Retire ' . $name . '? Nothing of ours calls it; its record and data are kept, and Bring it back undoes it.',
            '<input class="ak-in" name="why" maxlength="250" required placeholder="Why it\'s retired" data-spell><input class="ak-in" name="replaced" maxlength="250" placeholder="What replaced it (optional)" data-spell>', $canAu, $whyAu)) . '</details>'; if ($canAu) $allowed[] = 'Retire (stage 8)'; }
        $ours[] = $st . '</div>';
    }
    $ours[] = tv_mc_btn('maintout', $proc, 'Take out', ControlMaintenance::canTake($proc), 'It can\'t be taken out from here: a task without its own switch is taken out with its workflow (02 §5.3).');   // rows 103–104; g3 M5
    if ($key === 'railway') { [$okS, $whyS] = SystemStart::can(); $ours[] = tv_mc_btn('pauseall', '', 'Pause everything', $okS, (string) $whyS, 'Pause everything? Every workflow stops, and the crawler\'s schedule with it, until Start the system.', true); if ($okS) $allowed[] = 'Pause everything'; }   // row 111; 03 §10 answer 9
    // the caps (rows 39: Raise the cap / Lower the cap): the register's Set and Reset, kept with their anchors
    $caps = '';
    if (is_array($r) && $r['caps']) {
        $caps = '<h3 class="os-h" id="svc-' . $e($key) . '-caps">Caps and paces</h3><table class="ak-aa mc-caps"><thead><tr><th>Setting</th><th>Now</th><th>Set it</th><th>History</th></tr></thead><tbody>';
        foreach ($r['caps'] as $c) {
            $hist = Thresholds::history($pdo, $c['gate'], 6);
            $caps .= '<tr id="cap-' . $e($c['gate']) . '"><td><b>' . $e($c['label']) . '</b><br><small>' . $e($c['where']) . '</small></td><td>' . $e(Operations::num($c['value']) . ' ' . $c['unit']) . '<br><small>' . $e($c['how']) . '</small></td><td>'
                . tv_ops_form($csrf, 'cap_set', 'Set', ['ref' => $c['gate']], '', '<input class="ak-in" style="width:7rem" type="text" inputmode="decimal" name="value" value="' . $e((string) $c['value']) . '" aria-label="' . $e($c['label']) . '">', $can, $why)
                . ' ' . tv_ops_form($csrf, 'cap_reset', 'Reset', ['ref' => $c['gate']], 'Back to its default (' . Operations::num(Thresholds::GATES[$c['gate']][2]) . ')?', '', $can, $why) . '</td><td><small>'
                . ($hist ? implode('<br>', array_map(fn ($x) => $e(($x['old_v'] ?? '–') . ' → ' . $x['new_v'] . ', ' . $x['at'] . ($x['note'] !== '' ? ' (' . $x['note'] . ')' : '')), $hist)) : 'No changes yet') . '</small></td></tr>';
        }
        $caps .= '</tbody></table>';
    }
    // At <Provider>: four slots in the fixed order, two per row at 390 (03 §1, §8)
    $owner = (Access::who()[0] ?? '') === 'founder';
    $sl = '';
    foreach (TV_MC_SLOTS as $t => [$lab, $word]) {
        $fix = $t === $fixType;
        $sl .= '<div class="mc-slot' . ($slots[$t] ? '' : ' mc-none') . ($fix ? ' mc-slot-fix' : '') . '" id="mc-' . $e(str_replace(':', '-', $key)) . '-slot-' . $t . '">' . ($fix ? '<b class="mc-fixhere">Fix it lands here</b>' : '');
        if ($slots[$t]) {
            [$pl, $u, $tag] = $slots[$t][0];
            [$u, $hinted] = SignIns::link((string) $u, $si);   // opened in the account it signs in as, where the provider takes a hint
            $sl .= '<p class="mc-ty">' . $e($lab) . (strcasecmp($pl, $lab) ? ' <small>· ' . $e($pl) . '</small>' : '') . '</p><a class="ak-b" href="' . $e($u) . '" target="_blank" rel="noopener">Open ' . $e($prov) . ' ' . $e($word) . ' ↗</a> ' . tv_mc_tag($tag, $prov)
                . ($si && $si['email'] !== '' ? '<small class="mc-as">' . ($hinted ? 'opens as' : 'sign in as') . '<span class="mc-asv" title="' . $e((string) $si['email']) . '">' . $e((string) $si['email']) . '</span></small>' : '');
            foreach (array_slice($slots[$t], 1) as [$pl2, $u2]) { [$u2] = SignIns::link((string) $u2, $si); $sl .= ' <a class="os-go" href="' . $e($u2) . '" target="_blank" rel="noopener">' . $e($pl2) . ' ↗</a>'; }
        } else {
            $sl .= '<p class="mc-ty">' . $e($lab) . '</p><p class="mc-sub">No ' . $e($word) . ' page registered for ' . $e($prov) . ' yet.</p>'
                . ($owner ? '<a class="ak-b" href="/admin/config/vault">Publish from your Vault</a>' : '<button type="button" class="ak-b" disabled title="Owner only">Publish from your Vault</button>') . ' <small class="mc-why">owner only</small>';
        }
        $sl .= '</div>';
    }
    $fixLine = $fixType !== '' ? '<p class="mc-sub">Fix it: ' . ($slots[$fixType] ? $e($prov . '\'s ' . TV_MC_SLOTS[$fixType][1] . ' page') : $e('No ' . TV_MC_SLOTS[$fixType][1] . ' page registered for ' . $prov . ' yet')) . '.</p>' : '';
    $h = '<section class="mc-z mc-z4" data-copy="' . $e($name . ': actions and Fix it') . '"><h3 class="mc-zh">Actions and Fix it</h3>' . $threads
        . '<div class="mc-act mc-ours" data-row="ours"><p class="mc-lbl">On our system</p><div class="mc-btns">' . implode(' ', array_filter($ours, 'strlen')) . '</div></div>'
        . '<div class="mc-act mc-prov" data-row="provider"><p class="mc-lbl">At ' . $e($prov) . '</p><div class="mc-slots">' . $sl . '</div>' . $fixLine
        . '<p class="mc-sub">After a page at ' . $e($prov) . ': Billing → Check now · Console → Check now (Check and start again on a trip) · Keys → Replace key, then its Check. Opening a page there changes nothing here.</p></div>'
        . $caps . '</section>';
    return [$h, array_values(array_unique($allowed))];
}

/** Details (03 §9: terms, contact, settings and its stage history; the terms and contact stay on the card below). */
function tv_mc_details(PDO $pdo, string $key, string $sg): string
{
    $e = 'tv_w_e'; $h = '';
    try { if (!str_starts_with($key, 'wk:') && ($hs = ServiceStages::history($pdo, $key))) {
        $h = '<details class="mc-fold" data-copy="Its stages"><summary>Its stages ▸</summary><ul class="os-hist">';
        foreach ($hs as $x) $h .= '<li><b>' . $e(ServiceStages::STAGES[$x['stage']][0] ?? $x['stage']) . '</b> ' . tv_mc_t((string) $x['at']) . ((string) $x['by_name'] !== '' ? ', ' . $e((string) $x['by_name']) : '') . ((string) $x['why'] !== '' ? ': ' . $e((string) $x['why']) : '') . '</li>';
        $h .= '</ul></details>';
    } } catch (Throwable $x) {}
    return $h;
}

function tv_mc_css(): string
{
    static $done = false; if ($done) return ''; $done = true;
    return '<style>'
        . '#tv .mc-signin,.mc-signin{margin:.2rem 0 .35rem;font-size:.9rem;overflow-wrap:anywhere}.mc-signin .ak-b{margin-left:.35rem}.mc-as{display:block;margin-top:.2rem;color:var(--tv-meta,#6b5d49);min-width:0}.mc-asv{display:block;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
        . '.mc-card{border:1px solid var(--tv-line-strong);border-radius:.6rem;background:var(--tv-card-a,var(--tv-card));padding:.6rem .75rem;margin:.5rem 0;min-width:0;overflow-wrap:break-word}.mc-card code,.mc-card a,.mc-card time{overflow-wrap:anywhere}'
        . '.mc-card *{min-width:0}.mc-z{border-top:1px solid var(--tv-line);padding:.45rem 0}.mc-z:first-child{border-top:0}'
        . '.mc-zh{font-size:.72rem;letter-spacing:.06em;text-transform:uppercase;color:var(--tv-meta);margin:0 0 .3rem}'
        . '.mc-name{font-family:var(--tv-font-display,serif);color:#92400E;text-decoration:underline;font-size:1.1rem}'
        . '.mc-role{display:inline-block;border-radius:999px;padding:0 .5rem;font-size:.75rem;background:var(--tv-umber);color:var(--tv-on-umber)}.mc-role-s{background:transparent;color:var(--tv-ink);border:1px solid var(--tv-line-strong)}'
        . '.mc-crit,.mc-sub,.mc-why{color:var(--tv-meta);font-size:.82rem}.mc-why{display:block}'
        . '.mc-light{display:inline-flex;gap:.35rem;align-items:center;font-weight:600}.mc-light i{width:.6rem;height:.6rem;border-radius:50%;background:var(--tv-meta);flex:none}'
        . '.mc-l-ok i{background:var(--tv-sage)}.mc-l-hold i{background:var(--tv-hold)}.mc-l-bad i{background:var(--tv-fail)}.mc-l-maint i{background:var(--tv-river)}.mc-l-off i,.mc-l-grey i{background:var(--tv-taupe)}.mc-l-none i{display:none}'
        . '.mc-spend{font-size:.85rem;border-left:3px solid var(--tv-taupe);padding-left:.4rem}.mc-spend.mc-l-hold{border-color:var(--tv-hold)}.mc-spend.mc-l-bad{border-color:var(--tv-fail)}.mc-spend.mc-l-ok{border-color:var(--tv-sage)}'
        . '.mc-rad{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.4rem}.mc-list{margin:.2rem 0 .2rem 1.1rem;padding:0;font-size:.85rem}'
        . '.mc-part{border:1px solid var(--tv-line);border-radius:.5rem;padding:.4rem .55rem;margin:.35rem 0}.mc-tint-hold{background:var(--tv-hold-bg)}.mc-tint-bad{background:var(--tv-fail-bg)}'
        . '.mc-part p,.mc-z p{margin:.15rem 0}.mc-act{margin:.45rem 0}.mc-lbl{font-weight:600;font-size:.82rem;margin:0 0 .25rem}.mc-btns{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center}'
        . '.mc-btns .os-stage{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center}'
        . '.mc-slots{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.4rem}.mc-slot{border:1px solid var(--tv-line);border-radius:.5rem;padding:.4rem}.mc-none{opacity:.85}'
        . '.mc-slot-fix{outline:2px solid var(--tv-copper);outline-offset:1px}.mc-fixhere{display:block;color:var(--tv-copper);font-size:.78rem}.mc-ty{font-weight:600;margin:0 0 .2rem}'
        . '.mc-tag{display:inline-block;font-size:.72rem;border:1px solid var(--tv-line-strong);border-radius:999px;padding:0 .4rem;color:var(--tv-meta)}'
        . '.mc-fold>summary,.mc-setup>summary,.mc-rl>summary{cursor:pointer}.mc-card .ak-b{max-width:100%;white-space:normal;overflow-wrap:break-word;text-align:left}.mc-card .os-stage form{max-width:100%;flex-wrap:wrap}.mc-card .ak-in{max-width:100%}'
        . '@media (max-width:767px){.mc-card{margin:3px 0;border-radius:.5rem;padding:.5rem}.mc-rad{grid-template-columns:1fr}.mc-slots{grid-template-columns:repeat(2,minmax(0,1fr))}.mc-btns{flex-direction:row}.mc-card .ak-aa td>b,.mc-card .ak-aa td>small{display:block}.mc-card .ak-aa td{flex-direction:column;align-items:flex-start}}'
        . '</style>';
}

/** The board's actions pressed on a card: busy while it works, the answer beside the button (TvAsk), a yes first where asked. */
function tv_mc_js(string $csrf): string
{
    static $done = false; if ($done) return ''; $done = true;
    return '<script>(function(){document.addEventListener("click",function(e){var b=e.target.closest("[data-mc-act]");if(!b||b.disabled)return;'
        . 'if(b.hasAttribute("data-confirm")&&window.TvAsk&&!TvAsk.gate(b,b.getAttribute("data-confirm"),{yes:b.getAttribute("data-yes")||"Yes",danger:b.hasAttribute("data-danger")}))return;'
        . 'var tz="";try{tz=Intl.DateTimeFormat().resolvedOptions().timeZone||""}catch(x){}'
        . 'if(window.TvAsk)TvAsk.busy(b,true);var body={action:b.getAttribute("data-mc-act"),id:b.getAttribute("data-mc-id"),tz:tz};'
        . 'fetch("/admin/api/config/control",{method:"POST",credentials:"include",headers:{"Content-Type":"application/json","X-CSRF-Token":' . json_encode($csrf) . '},body:JSON.stringify(window.tvSafe?tvSafe(body):body)})'
        . '.then(function(r){return r.json().catch(function(){return{ok:false,error:{message:"The server didn’t answer clearly. Try again; still stuck? Write to support@traversence.com."}};});})'
        . '.then(function(j){if(window.TvAsk){TvAsk.busy(b,false);TvAsk.say(j.ok?(j.message||"Done."):((j.error&&j.error.message)||"That didn’t work."),{anchor:b,bad:!j.ok||!!j.bad});}if(j.ok&&!j.bad)setTimeout(function(){location.reload();},2200);})'
        . '.catch(function(){if(window.TvAsk){TvAsk.busy(b,false);TvAsk.say("That didn’t reach the site. Try again.",{anchor:b,bad:true});}});});})();</script>';
}
