<?php
// app/ui/outside-services.php — System Configuration → Outside services and → Settings, in the new admin (decisions/0152).
// Outside services: one card per company (app/lib/OutsideServices.php), with what we use it for, each part checked, this
// month's use and cost, its pricing and fees, its .env names (never values) and its links; the month's total on top.
// Settings: what's about the site itself (the settings changed here, publishing, privacy, the site, the server).
// Both replace admin/settings.php (archived) and the Services & usage page of decisions/0133, keeping all they showed.
declare(strict_types=1);

require_once __DIR__ . '/../lib/OutsideServices.php';
require_once __DIR__ . '/../lib/Services.php';
require_once __DIR__ . '/../admin/sections.php';

function tv_os_money(float $d): string { return '$' . number_format($d, $d > 0 && $d < 1 ? 3 : 2); }
function tv_os_size(int $b): string { return $b >= 1073741824 ? number_format($b / 1073741824, 2) . ' GB' : number_format($b / 1048576, 1) . ' MB'; }

/** A company's flat plan this month: [name, plan, usd or null when not set, note]. */
function tv_os_plan(string $key): ?array
{
    if ($key === '') return null;
    if (isset(Services::PLANS[$key])) {
        [$name, $plan, $usd, $env, $note] = Services::PLANS[$key];
        $v = getenv($env); $usd = $v !== false && $v !== '' && is_numeric($v) ? (float) $v : (float) $usd;
        return [$name, $plan, $usd > 0 ? $usd : null, $note, $env];
    }
    if ($key === 'bluehost') { $v = getenv('BLUEHOST_USD_MONTH'); return ['Bluehost', 'Hosting', $v !== false && $v !== '' && is_numeric($v) && (float) $v > 0 ? (float) $v : null, 'The web hosting plan.', 'BLUEHOST_USD_MONTH']; }
    return null;
}

function tv_outside_services(PDO $pdo, array $with = []): string
{
    // drawn inside the Service register (decisions/0173 §1): $with['blocks'] is each service's register parts (stage, status,
    // use, key, caps, issue), shown first on its card; $with['stages'] its stage, in the Status column and the filter
    $blocks = (array) ($with['blocks'] ?? []); $stages = (array) ($with['stages'] ?? []);
    $e = 'tv_w_e';
    require_once __DIR__ . '/../admin/Access.php';
    [$canNotes, $whyNotes] = Access::can('config.services.notes', ['min' => 'executive', 'depts' => []]);   // owners and executives
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $msg = ''; $saved = '';
    if ($canNotes && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['svc_notes']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try { OutsideServices::saveNotes($pdo, (string) $_POST['svc_notes'], (array) ($_POST['note'] ?? [])); $saved = (string) $_POST['svc_notes'];
              $msg = '<p data-tv-flash="ok">' . $e(OutsideServices::COMPANIES[$saved][0] ?? 'The service') . ': how to sign in is saved.</p>'; }
        catch (Throwable $x) { $msg = '<p data-tv-flash="error">' . $e($x instanceof InvalidArgumentException ? $x->getMessage() : 'That didn\'t save. Try again.') . '</p>'; $saved = (string) $_POST['svc_notes']; }
    }
    // a request we're waiting on from the company (OutsideServices::requests): each a Pulse case until it's answered
    if ($canNotes && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['svc_request']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $saved = (string) $_POST['svc_request']; $me = Access::staff();
        try { $rid = OutsideServices::addRequest($pdo, $saved, (string) ($_POST['what'] ?? ''), (string) ($_POST['ticket'] ?? ''), (string) ($_POST['link'] ?? ''), (int) ($me['user_id'] ?? 0));
              $msg = '<p data-tv-flash="ok">' . $e(OutsideServices::COMPANIES[$saved][0] ?? 'The service') . ': request noted. It\'s a Pulse case until you record their answer.</p>'; }
        catch (Throwable $x) { $msg = '<p data-tv-flash="error">' . $e($x instanceof InvalidArgumentException ? $x->getMessage() : 'That didn\'t save. Try again.') . '</p>'; }
    }
    // a service's servers (ServiceHealth::ROLES; Jason, 2026-11-14: "server lists become settings, not code"): add one, pause
    // one, resume it. The pre-use check holds a server with an issue; pausing is a person's choice, shown with who and why.
    // a task the card says is switched off, switched on from the card (Jason, 2026-11-19: "Not sure what it need to do?");
    // the Automation page's own switch, its permission and its log (Automation::setTask)
    [$canTask, $whyTask] = Access::can('config.automation', ['min' => 'executive', 'depts' => []]);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['svc_task_on']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $saved = (string) ($_POST['svc'] ?? '');
        try {
            if (!$canTask) throw new RuntimeException($whyTask);
            $t = (string) $_POST['svc_task_on'];
            if (!in_array($t, OutsideServices::CONTROLS[$saved]['tasks'] ?? [], true)) throw new RuntimeException('That task isn\'t this service\'s.');
            require_once __DIR__ . '/../lib/Automation.php';
            $me = Access::staff(); $uid = (int) ($me['user_id'] ?? 0);
            Automation::setTask($pdo, $t, true, '', $uid, trim((string) ($me['name'] ?? '')));
            Automation::forget();
            $msg = '<p data-tv-flash="ok">Switched on: the crawler takes it from its next run. Its switch is on its workflow\'s page on the System Control Board, linked under What it serves.</p>';
        } catch (Throwable $x) { $msg = '<p data-tv-flash="error">' . $e($x instanceof RuntimeException ? $x->getMessage() : 'That didn\'t work: try again.') . '</p>'; if (!($x instanceof RuntimeException)) error_log('register task_on: ' . $x->getMessage()); }
    }
    require_once __DIR__ . '/../lib/ServiceHealth.php';
    [$canServers, $whyServers] = Access::can('config.services.servers', ['min' => 'executive', 'depts' => []]);
    if ($canServers && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['svc_server']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $saved = (string) ($_POST['svc'] ?? ''); $me = Access::staff(); $uid = (int) ($me['user_id'] ?? 0);
        try {
            $act = (string) $_POST['svc_server'];
            if ($act === 'add') { ServiceHealth::addServer($pdo, $saved, (string) ($_POST['role'] ?? ''), (string) ($_POST['url'] ?? ''), $uid); $msg = '<p data-tv-flash="ok">Added: it\'s asked after the servers above it, inside the same checks.</p>'; }
            elseif ($act === 'pause' || $act === 'resume') { ServiceHealth::pauseServer($pdo, (int) ($_POST['id'] ?? 0), $act === 'pause', $uid, (string) ($_POST['why'] ?? '')); $msg = '<p data-tv-flash="ok">' . ($act === 'pause' ? 'Paused: it isn\'t asked until it\'s resumed.' : 'Resumed: it\'s asked again, inside the same checks.') . '</p>'; }
        } catch (Throwable $x) { $msg = '<p data-tv-flash="error">' . $e($x instanceof InvalidArgumentException ? $x->getMessage() : 'That didn\'t save. Try again.') . '</p>'; }
    }
    $use = []; try { foreach (Services::all($pdo) as $s) $use[$s['key']] = $s; } catch (Throwable) {}
    $cards = []; $plansUsd = 0.0; $usageUsd = 0.0; $unpriced = [];
    // the table (Jason, 2026-11-17: "treat it like the table view with search and filters sorting"): each company's status is
    // its pre-flight line's (one list, one status: Preflight 'svc:<key>'), its card opens in place under its row
    $pf = []; try { require_once __DIR__ . '/../lib/Preflight.php'; $pf = Preflight::results($pdo); [$canTest, $whyTest] = Preflight::can(); } catch (Throwable $x) { $canTest = false; $whyTest = 'The pre-flight isn\'t ready'; }
    foreach (OutsideServices::COMPANIES as $k => [$name, $what, $pricing, $links, $usageKeys, $planKey]) {
        $cUsd = 0.0;   // this company's month so far: its plan and its use
        $parts = []; try { $parts = OutsideServices::parts($pdo, $k); } catch (Throwable $x) { $parts = [['Check', 'Couldn\'t check: ' . $x->getMessage(), 'bad', '', '']]; }
        $bad = array_filter($parts, fn ($p) => $p[2] === 'bad');
        $b = '<p class="os-what">' . $e($what) . '</p>';
        // sign in, first and on one line (Jason, 2026-11-11: "I really need login and passwords with where to sign in links"; "I dont
        // like how much space the how to sign in is taking up"): the company's sign-in page, the login in 1Password (its private
        // link: the password stays in 1Password, decisions/0155), who to sign in as and the second step; ✎ opens the fields.
        $login = $k === 'aws' && trim((string) (getenv('AWS_CONSOLE_URL') ?: '')) !== '' ? trim((string) getenv('AWS_CONSOLE_URL')) : (OutsideServices::LOGIN[$k] ?? '');   // Amazon: the account's own sign-in link when set
        $nt = $canNotes ? OutsideServices::notes($pdo, $k) : []; $vault = OutsideServices::vaultLink((string) ($nt['vault'] ?? ''));
        $bits = array_values(array_filter([(string) ($nt['account'] ?? ''), (string) ($nt['how'] ?? ''), (string) ($nt['step'] ?? ''), ($nt['who'] ?? '') !== '' ? 'held by ' . $nt['who'] : '']));
        $b .= '<div class="os-sign">' . ($login !== '' ? '<a class="ak-b go" href="' . $e($login) . '" target="_blank" rel="noopener">Sign in ↗</a>' : '')
            . ($canNotes ? ($vault !== '' ? '<a class="ak-b" href="' . $e($vault) . '" target="_blank" rel="noopener" title="Opens this login in your 1Password: the password stays there">1Password ↗</a>' : '')
                . '<details class="os-ed"' . ($saved === $k ? ' open' : '') . '><summary title="Edit how to sign in" aria-label="Edit how to sign in">✎</summary><form method="post" class="os-notes"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="svc_notes" value="' . $e($k) . '">'
                . implode('', array_map(fn ($f, $fd) => '<label><span>' . $e($fd[0]) . '</span><input class="ak-in" name="note[' . $f . ']" value="' . $e((string) ($nt[$f] ?? '')) . '" maxlength="200" placeholder="' . $e($fd[1]) . '" spellcheck="false" autocomplete="off"></label>', array_keys(OutsideServices::NOTE_FIELDS), OutsideServices::NOTE_FIELDS))
                . '<p class="os-note">The password stays in 1Password: paste the login\'s private link, never the password. A password, key or code typed here is refused.</p><button class="ak-b go">Save</button></form></details>'
                . '<span class="os-signt">' . ($bits ? $e(implode(' · ', $bits)) : '<span class="os-not">' . (!empty($nt['known']) ? '' : 'No sign-in note yet') . '</span>') . (!empty($nt['known']) ? ' <span class="os-not">(from the decisions: ✎ to confirm)</span>' : '') . '</span>'
              : '<span class="os-signt os-not">Sign-in notes: ' . $e($whyNotes) . '</span>')
            . '</div>';
        // each part, checked
        foreach ($parts as $p) {
            $tone = $p[2] === 'ok' ? 'ok' : ($p[2] === 'bad' ? 'bad' : 'info');
            $b .= '<div class="os-part"><p data-tv-status="' . $tone . '" data-tv-label="' . $e($p[0]) . ':">' . $e($p[1]) . '</p>'
                . (($p[4] ?? '') !== '' || ($p[3] ?? '') !== '' ? '<small>' . $e((string) ($p[4] ?? '')) . (($p[3] ?? '') !== '' ? ' <code>' . $e($p[3]) . '</code>' : '') . '</small>' : '') . '</div>';
            if (($p[5] ?? '') === 'storage') {
                [$n, $bytes, $vn, $vb] = OutsideServices::stored($pdo);
                $b .= '<p class="os-line">Stored: <b>' . number_format($n) . ' photos and videos, ' . tv_os_size($bytes) . '</b>' . ($vn ? ' (' . number_format($vn) . ' videos, ' . tv_os_size($vb) . ')' : '') . '. Business files, proofs and order files are counted in Cloudflare\'s own numbers (Metrics on the bucket). Views and plays aren\'t counted by the site.</p>';
                // the files from before object storage: moved off the server a batch at a time (decisions/0123)
                $b .= '<div class="os-line" id="tv-s3move"><p data-t>Checking for files still on the server…</p><button type="button" class="ak-b go" style="display:none">Move files</button></div>';
            }
        }
        // its servers, in the order they're asked, each with its state (in use, held and why, backing off, paused)
        foreach (ServiceHealth::ROLES[$k] ?? [] as $role => [$rl, $rpath]) {
            $b .= '<h3 class="os-h">' . $e($rl) . '</h3>';
            $retiredRole = false;
            foreach (ServiceHealth::servers($pdo, $k, $role, true) as $sv) {
                if (!empty($sv['retired'])) {   // Overpass retired (ServiceHealth::OSM_RETIRED): its reason and the forum, nothing to pause, resume or add
                    $retiredRole = true;
                    $b .= '<div class="os-part"><p data-tv-status="info" data-tv-label="' . $e($sv['host']) . ': retired.">' . $e((string) $sv['paused_why']) . ' <a class="os-go" href="' . $e(ServiceHealth::OSM_RETIRED_URL) . '" target="_blank" rel="noopener">The forum\'s answer</a></p></div>';
                    continue;
                }
                [$tone, $says, $case] = ServiceHealth::serverState($pdo, $sv);
                $b .= '<div class="os-part"><p data-tv-status="' . $tone . '" data-tv-label="' . $e($sv['host']) . ':">' . $e($says)
                    . ($case !== '' ? ' <a class="os-go" href="/admin/pulse/stream#case=' . $e(rawurlencode($case)) . '">Its case</a>' : '') . '</p>';
                if ((int) ($sv['id'] ?? 0)) {
                    $p = (int) $sv['paused'];
                    $b .= '<form method="post" class="os-notes"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="svc" value="' . $e($k) . '"><input type="hidden" name="id" value="' . (int) $sv['id'] . '">'
                        . '<input type="hidden" name="svc_server" value="' . ($p ? 'resume' : 'pause') . '">'
                        . (!$p ? '<input class="ak-in" name="why" maxlength="200" placeholder="Why pause it (optional)" data-spell>' : '')
                        . '<button class="ak-b"' . ($canServers ? (!$p ? ' data-confirm="Pause ' . $e($sv['host']) . '? It isn\'t asked until it\'s resumed." data-yes="Pause it"' : '') : ' disabled title="' . $e($whyServers) . '"') . '>' . ($p ? 'Resume' : 'Pause') . '</button></form>';
                }
                $b .= '</div>';
            }
            if ($retiredRole) continue;   // a retired list takes no new server
            $b .= '<form method="post" class="os-notes"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="svc" value="' . $e($k) . '"><input type="hidden" name="role" value="' . $e($role) . '"><input type="hidden" name="svc_server" value="add">'
                . '<label><span>Add a server</span><input class="ak-in" name="url" type="url" maxlength="255" placeholder="https://… (the server) …' . $e($rpath) . '" spellcheck="false" autocomplete="off" required></label>'
                . '<button class="ak-b go"' . ($canServers ? '' : ' disabled title="' . $e($whyServers) . '"') . '>Add</button></form>'
                . '<p class="os-note">Asked in this order. A server with an issue is held by itself and comes back when it\'s resolved (or its probe answers); nobody needs to take it off the list. The day\'s budget and pace are in Thresholds &amp; gates.' . ($canServers ? '' : ' ' . $e($whyServers)) . '</p>';
        }
        // is it working, from the site's and the crawler's own logs (Jason, 2026-11-13: "everything pertaining to that service")
        $work = []; try { $work = OutsideServices::working($pdo, $k); } catch (Throwable $x) {}
        if ($work) {
            $b .= '<h3 class="os-h">Is it working</h3>';
            foreach ($work as $wrow) {
                [$wl, $ws, $wt, $wu] = $wrow; $wa = $wrow[4] ?? null;   // [4]: its fix, a button here (['task_on', task, label])
                $keep = ($wrow[5] ?? '') === 'keep';   // [5]: an issue's line: no × (it closes only when its check passes, decisions/0176 §3), and its link lands on the issue on this card
                if ($keep && str_starts_with($wu, '/admin/config/register#')) $wu = substr($wu, strlen('/admin/config/register'));
                if ($wt === 'bad') $bad[] = $wl;
                $fix = '';
                if (is_array($wa) && $wa[0] === 'task_on') $fix = ' <form method="post" class="os-fix" style="display:inline"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="svc" value="' . $e($k) . '"><input type="hidden" name="svc_task_on" value="' . $e((string) $wa[1]) . '">'
                    . '<button class="ak-b go"' . ($canTask ? ' data-confirm="Switch on ' . $e($wl) . '? The crawler takes it from its next run." data-yes="Switch on"' : ' disabled title="' . $e($whyTask) . '"') . '>' . $e((string) $wa[2]) . '</button></form>';
                $b .= '<div class="os-part"><p data-tv-status="' . ($wt === 'ok' ? 'ok' : ($wt === 'bad' ? 'bad' : 'info')) . '"' . ($keep ? ' data-tv-keep' : '') . ' data-tv-label="' . $e($wl) . ':">' . $e($ws) . $fix
                    . ($wu !== '' ? ' <a class="os-go" href="' . $e($wu) . '"' . (str_starts_with($wu, '#') ? '' : (str_starts_with($wu, '/') ? ' data-peek-frame' : ' target="_blank" rel="noopener"')) . '>' . (str_starts_with($wu, '#issue-') ? 'Its issue' : ($fix !== '' || str_contains($wu, '/admin/config/control/wf-') ? 'Its switch' : (str_starts_with($wu, '/') ? 'Open' : 'Read it ↗'))) . '</a>' : '') . '</p></div>';   // a step it serves opens its switch on its workflow's page, in the side panel (0175 §6)
            }
        }
        // what's set in another section: shown here as it stands, changed there ("linked [where it] is controlled by another section")
        $ctl = []; try { $ctl = OutsideServices::controlled($pdo, $k); } catch (Throwable $x) {}
        if ($ctl) {
            $b .= '<h3 class="os-h">Set in other sections</h3>';
            foreach ($ctl as [$cl, $cs, $cu]) $b .= '<p class="os-line"><a class="os-go" href="' . $e($cu) . '">' . $e($cl) . '</a>: ' . $e($cs) . '</p>';
        }
        // what we call: its addresses (the admin search finds the card by them; the site audit's R12 checks the code against them)
        if ($hs = OutsideServices::HOSTS[$k] ?? []) $b .= '<p class="os-note">Called at: ' . implode(', ', array_map(fn ($x) => '<code>' . $e($x) . '</code>', $hs)) . '</p>';
        // what it costs
        $cost = '';
        if ($pl = tv_os_plan($planKey)) {
            if ($pl[2] !== null) { $plansUsd += $pl[2]; $cUsd += $pl[2]; $cost .= '<p class="os-line">Plan: <b>' . $e($pl[1]) . ', ' . tv_os_money($pl[2]) . ' a month</b>. ' . $e($pl[3]) . '</p>'; }
            else { $unpriced[] = $name; $cost .= '<p data-tv-status="info" data-tv-label="Plan price not set:">Set it in Settings (' . $e($pl[0]) . ' plan, a month) so it counts in the monthly total. <code>' . $e($pl[4]) . '</code></p>'; }
        }
        foreach ($usageKeys as $uk) if (isset($use[$uk])) {
            $s = $use[$uk]; $usageUsd += (float) $s['cost']; $cUsd += (float) $s['cost'];
            $line = $s['money'] ? tv_os_money((float) $s['used']) . ' of ' . tv_os_money((float) $s['cap']) : number_format((float) $s['used']) . ($s['cap'] ? ' of ' . number_format((float) $s['cap']) : '') . ' ' . $s['unit'];
            $cost .= '<p data-tv-status="' . $s['tone'] . '" data-tv-label="' . ($s['pct'] >= 100 ? 'Stopped for the month:' : ($s['pct'] >= 80 ? 'Getting close:' : $e($s['name']) . ' this month:')) . '">' . $e($line)
                . ($s['cap'] ? ' (' . $s['pct'] . '%)' : '') . ' · ' . number_format((float) $s['today'], $s['money'] ? 2 : 0) . ' today · at this pace ' . $e(Services::fmt($s, $s['projected_units'])) . ' by the month\'s end'
                . (!$s['money'] && $s['rate'] > 0 ? ', about ' . tv_os_money((float) $s['projected']) . ' (' . tv_os_money((float) $s['cost']) . ' so far)' : '') . '.</p><p class="os-note">' . $e($s['note']) . '</p>';
        }
        $free = str_starts_with($pricing, 'Free ($0)');
        if ($k === 'namecom') $b .= tv_os_namecom_prices();   // what our account pays per ending, beside Name.com's public price (Jason, 2026-11-17)
        $b .= '<h3 class="os-h">What it costs</h3>' . ($cost !== '' ? $cost : ($free ? '<p class="os-line"><b>$0</b>: free, within its usage rules below.</p>' : '<p class="os-line">No use counted by the site; see its own billing.</p>')) . '<p class="os-note">' . $e($pricing) . '</p>';
        // its settings changed here (the rest stay in .env: the names under each part above)
        if ($mine = OutsideServices::settingsOf($k)) $b .= '<h3 class="os-h">Its settings</h3>' . tv_os_env_rows($mine);
        // where it's set up in our admin, then the company's own pages
        if ($in = OutsideServices::linked($k)) {
            $b .= '<h3 class="os-h">In our admin</h3><div class="os-links">';
            foreach ($in as [$lt, $lu]) $b .= '<a class="ak-b" href="' . $e($lu) . '">' . $e($lt) . '</a>';
            $b .= '</div><h3 class="os-h">On ' . $e($name) . '</h3>';
        }
        $b .= '<div class="os-links">';
        foreach ($links as [$lt, $lu]) { $u = OutsideServices::link($lu); $ext = !str_starts_with($u, '/');
            $b .= '<a class="ak-b" href="' . $e($u) . '"' . ($ext ? ' target="_blank" rel="noopener"' : '') . '>' . $e($lt) . ($ext ? ' ↗' : '') . '</a>'; }
        $b .= '</div>';
        // its terms and where to reach it or its community when something's wrong
        if ($ct = OutsideServices::CONTACT[$k] ?? []) {
            $b .= '<h3 class="os-h">Terms and who to ask</h3><div class="os-links">';
            foreach ($ct as [$lt, $lu]) $b .= '<a class="ak-b" href="' . $e($lu) . '" target="_blank" rel="noopener">' . $e($lt) . ' ↗</a>';
            $b .= '</div>';
        }
        $on = OutsideServices::inUse($parts);
        $status = $bad ? count($bad) . ' to look at' : ($on ? 'In use' : 'Not set up');
        // paused by a person (System Configuration → Automation): a status like a hold, never an issue
        require_once __DIR__ . '/../lib/Automation.php';
        if (($pz = Automation::serviceOff($pdo, $k)) !== null) { $status = 'Paused · ' . $status; if (!$stages) $b = '<p data-tv-status="info" data-tv-label="Paused:">' . $e($pz) . ' Nothing of ours calls it until it\'s resumed in <a class="underline" href="/admin/config/automation#au-svc-' . $e($k) . '">Automation</a>.</p>' . $b; }
        $b .= tv_os_requests($pdo, $k, $csrf, (bool) $canNotes, (string) $whyNotes);   // what we're waiting on from them
        // its test: the pre-flight's line for it, run from here too (one status). An active service's is its diagnostics, never
        // "pre-flight" (0175's note, 2026-11-20: "once something is active it is not longer preflight, it is diagnostics"); a
        // service a person paused, or not in use yet, still has its pre-flight before it goes active again
        $active = $on && Automation::serviceOff($pdo, $k) === null;
        $t = $pf['svc:' . $k] ?? null; $ts = (string) ($t['s'] ?? '');
        $mark = $ts === 'ok' ? 'ok' : ($ts === 'bad' ? 'bad' : ($ts === 'na' ? 'na' : ($bad ? 'bad' : ($on ? 'ok' : 'na'))));   // not tested yet: what its parts say
        if (isset($blocks[$k])) $b = $blocks[$k] . $b;   // the register's parts first: the stage and the issue are what the card is opened for
        $b = '<div class="os-test"><button type="button" class="ak-b go" data-os-test="' . $e($k) . '"' . ($canTest ? '' : ' disabled title="' . $e((string) $whyTest) . '"') . '>Test</button>'
            . '<span data-os-said>' . ($t ? $e(Preflight::markOf('svc:' . $k, $ts)) . ': ' . $e((string) $t['said']) : 'Not tested yet: Test runs its ' . ($active ? 'diagnostics' : 'pre-flight check') . ' (real, free, read only).') . '</span>'
            . ' <a class="os-go" href="/admin/config/preflight#pf-svc-' . $e($k) . '">' . ($active ? 'Diagnostics' : 'Pre-flight') . '</a></div>' . $b;
        $need = $bad || $ts === 'bad';
        $kinds = OutsideServices::KINDS[$k] ?? [];
        $sg = (string) ($stages[$k][0] ?? '');
        $q = mb_strtolower($name . ' ' . $what . ' ' . OutsideServices::searchText($k) . ' ' . implode(' ', OutsideServices::settingsOf($k)) . ' ' . implode(' ', array_map(fn ($p) => (string) ($p[3] ?? ''), $parts)) . ' ' . implode(' ', array_map(fn ($x) => OutsideServices::KIND_NAMES[$x] ?? $x, $kinds)));
        $open = $saved === $k || ($k === 'namecom' && isset($_GET['nc_prices']));   // open again after saving it; #svc-<key> opens it too
        $row = '<div class="os-card os-r" id="svc-' . $e($k) . '" data-k="' . $e($k) . '" data-name="' . $e(mb_strtolower($name)) . '" data-cost="' . round($cUsd, 4) . '" data-need="' . ($need ? 1 : 0) . '" data-on="' . ($on ? 1 : 0) . '" data-kinds="' . $e(implode(' ', $kinds)) . '" data-stage="' . $e($sg) . '" data-q="' . $e($q) . '">'
            . '<button type="button" class="os-rh" aria-expanded="' . ($open ? 'true' : 'false') . '">'
            . '<span class="os-m os-m-' . $mark . '" title="' . $e(Preflight::MARKS[$mark] ?? '') . '">' . (function_exists('tv_icon') ? tv_icon(['ok' => 'check', 'bad' => 'x', 'na' => 'dash'][$mark], 'os-svg') : '') . '</span>'
            . '<span class="os-rn"><b>' . $e($name) . '</b><small>' . $e($what) . '</small></span>'
            . '<span class="os-rs">' . $e($sg === 'retired' ? 'Retired' : ($sg !== '' ? ServiceStages::STAGES[$sg][0] . ' · ' : '') . ($need ? 'Needs a look' : ($on ? 'In use' : 'Not in use')) . ($sg === '' && str_starts_with($status, 'Paused') ? ' · Paused' : '')) . '</span>'   // its stage, then the filter's own words
            . '<span class="os-rc">' . ($cUsd > 0 ? tv_os_money($cUsd) : ($free ? '$0' : '—')) . '</span>'
            . '<span class="os-rt" data-os-at>' . ($t ? '<time datetime="' . gmdate('c', (int) $t['at']) . '" data-tv-time>' . gmdate('M j, g:i A', (int) $t['at']) . ' UTC</time>' : 'not tested') . '</span>'
            . '<span class="os-rx" aria-hidden="true">▾</span></button>'
            . '<div class="os-rb"' . ($open ? '' : ' hidden') . '>' . $b . '</div></div>';
        $cards[] = [$need ? 0 : ($on ? 1 : 2), $name, $row];
    }
    usort($cards, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);   // by status (the default sort): what needs a look, then what's in use, then the rest; by name
    $total = $plansUsd + $usageUsd;
    $h = $msg . '<p data-tv-status="info" data-tv-label="This month so far:">Plans ' . tv_os_money($plansUsd) . ' + use ' . tv_os_money($usageUsd) . ' = <b>' . tv_os_money($total) . '</b>'
       . ($unpriced ? ' (not counted yet: ' . $e(implode(', ', $unpriced)) . '\'s plan price, set in Settings)' : '') . '. Card fees on our own orders show in QuickBooks; businesses\' fees on the Marketplace are theirs.</p>'
       . '<p class="ak-note">Every outside service the site and the crawler depend on, keyed or free, one card each: what it\'s for, whether it\'s working (from the site\'s and the crawler\'s own logs), what it costs, what\'s set in other sections (each linked), its terms and who to ask. Ctrl K finds a card by its name, company or address. The counts are the site\'s own; each company\'s bill is exact (its Billing link). An email goes to the admins at 80% and 100% of a cap, and a report on the 1st.</p>';
    // the table's bar: search (names, uses, key names), one filter (status or kind), and the sortable heads
    $opt = fn (string $v, string $l) => '<button type="button" data-fv="' . $e($v) . '">' . $e($l) . '<i data-n="' . $e($v) . '"></i></button>';
    $h .= '<div class="os-t" id="os-table"><div class="ak-bar"><input class="ak-q" type="search" placeholder="Search names, uses and key names" aria-label="Search outside services" data-os-q>'
        . '<span class="ak-fw"><button type="button" class="ak-fb" aria-expanded="false" data-os-fb><svg class="ak-svg" viewBox="0 0 24 24"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg><span data-os-fl>Everything</span> <b data-os-fn></b></button><span class="ak-fl" hidden>'
        . $opt('', 'Everything') . $opt('need', 'Needs a look') . $opt('on', 'In use') . $opt('off', 'Not in use');
    if ($stages) foreach (['active', 'paused', 'keyin', 'added', 'retired'] as $sk) $h .= $opt('stage:' . $sk, ServiceStages::STAGES[$sk][0]);
    foreach (OutsideServices::KIND_NAMES as $kk => $kl) $h .= $opt('kind:' . $kk, $kl);
    $h .= '</span></span></div>'
        . '<div class="os-th" role="row"><span></span><button type="button" data-os-sort="name">Company</button><button type="button" data-os-sort="status" class="on">Status</button><button type="button" data-os-sort="cost">This month</button><span>Last checked</span><span></span></div>'
        . '<div class="os-rows">';
    foreach ($cards as $c) $h .= $c[2];
    $h .= '</div><p class="ak-note" data-os-none hidden>Nothing matches: clear the search or choose Everything.</p></div>';
    // across companies: how independent we are, and last month's report (kept from Services & usage, decisions/0133)
    try {
        $i = Services::independence($pdo);
        $h .= '<p data-tv-status="info" data-tv-label="Independence this month:">' . ($i['share'] !== null ? 'Our own crawler answered ' . $i['share'] . '% of Find it (' . $i['find_free'] . ' of ' . ($i['find_free'] + $i['find_paid']) . ').' : 'No Find it answers yet this month.')
            . ' ' . $i['listings'] . ' listing' . ($i['listings'] === 1 ? '' : 's') . ' published from the crawler with ' . $i['paid'] . ' paid lookup' . ($i['paid'] === 1 ? '' : 's') . ($i['per_listing'] !== null ? ' (' . $i['per_listing'] . ' each; lower is more independent)' : '') . '.</p>';
        $last = date('Y-m', strtotime('first day of last month'));
        $h .= tv_admin_fold('Report for ' . date('F Y', strtotime($last . '-01')), 'What the email on the 1st says', '', '', '<pre style="white-space:pre-wrap;font:inherit;font-size:.85rem;margin:0">' . $e(Services::report($pdo, $last)) . '</pre>', false);
    } catch (Throwable) {}
    // #svc-<key> (the admin search, the pre-flight, a link from another section): js/admin-outside.js opens that row and brings it into view
    $h .= '<script src="/js/admin-outside.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/admin-outside.js') . '" defer></script>';
    return '<div class="os" data-csrf="' . $e($csrf) . '">' . $h . '</div>' . tv_os_css() . tv_os_move_js() . tv_os_env_js();
}

/** System Configuration → Settings: what's about the site itself. Outside companies are in Outside services. */
function tv_site_settings(PDO $pdo): string
{
    $e = 'tv_w_e';
    $env = fn (string $k): string => trim((string) (getenv($k) ?: ''));
    $on = fn (string $k, string $d = 'on'): bool => strtolower($env($k) ?: $d) !== 'off';
    require_once __DIR__ . '/../lib/EnvSettings.php';
    require_once __DIR__ . '/../admin/Access.php';
    // changed here, written into .env (decisions/0125); keys, passwords and tokens stay in .env by hand
    $h = '<p class="ak-note">How the site itself is set up. Each outside company (storage, email, payments, AI, sign-in) and its keys are in the <a href="/admin/config/register">Service register</a>. '
       . 'Settings below are written into .env for you, and the file before each change is kept; a service\'s key is added on its own card in the Service register (the Founder; never shown), and the site\'s own secrets stay in .env.</p>';
    // the senders' settings (address, name, reply-to, server, login) moved to their cards in Communications (decisions/0167 §1)
    require_once __DIR__ . '/../lib/CommsSetup.php';
    $mailKeys = ['GMAIL_ASSESSMENT_USD'];
    foreach (array_keys(CommsSetup::SENDERS) as $sk) $mailKeys = array_merge($mailKeys, array_values(CommsSetup::fields($sk)));
    $rest = array_values(array_diff(array_keys(EnvSettings::EDITABLE), OutsideServices::onCards(), $mailKeys));   // a company's are on its card
    $h .= '<p class="ak-note">The site\'s email (each sender\'s address, name, reply-to, server and mailbox) is set up in <a href="/admin/config/communications">Communications</a>.</p>';
    // in groups, the way they're used (Jason, 2026-11-11: "easy to find"); anything new lands in Other until it's grouped
    $groups = ['The site\'s email' => ['BUSINESS_POSTAL_ADDRESS'],
               'Orders and payments' => ['CLIENT_PAY_WAYS', 'WIRE_VERIFY_PHONE'],
               'Legal identity (Terms and policies)' => ['LEGAL_NAME', 'LEGAL_ADDRESS', 'LEGAL_EMAIL', 'LEGAL_PHONE', 'LEGAL_STATE', 'LEGAL_COUNTY'],
               'Claims' => ['CLAIM_VERIFY_NUMBER']];
    $grouped = array_merge(...array_values($groups));
    if ($other = array_values(array_diff($rest, $grouped))) $groups['Other'] = $other;
    foreach ($groups as $g => $ks) { $ks = array_values(array_intersect($ks, $rest)); if ($ks) $h .= tv_admin_fold($g, count($ks) . ' setting' . (count($ks) === 1 ? '' : 's') . ', changed here', '', '', tv_os_env_rows($ks), false); }
    require_once __DIR__ . '/../lib/crawler/AutoImport.php';
    $sess = dirname(__DIR__) . '/storage/sessions';
    $G = [
        'Publishing' => [
            ['Auto-publish', AutoImport::enabled() ? 'On' : 'Off', AutoImport::enabled() ? 'ok' : 'bad', 'AUTO_IMPORT', 'Listings that pass every check publish on their own (decisions/0076). Off: everything waits in Review.'],
            ['Crawler', $on('CRAWL_QUEUE') ? 'Running' : 'Paused', $on('CRAWL_QUEUE') ? 'ok' : 'bad', 'CRAWL_QUEUE', 'Off pauses every crawl job.'],
        ],
        'Privacy' => [
            ['Usage analytics', $env('ANALYTICS_SRC') !== '' ? ($env('ANALYTICS_NAME') ?: 'Set up') . ' (' . (parse_url($env('ANALYTICS_SRC'), PHP_URL_HOST) ?: $env('ANALYTICS_SRC')) . ')' : 'None set up', $env('ANALYTICS_SRC') !== '' ? 'ok' : 'info', 'ANALYTICS_SRC, ANALYTICS_NAME', 'Loads only for people who say yes (decisions/0077). With none set up, nobody is asked.'],
            ['Videos from elsewhere', 'Wait for a yes', 'ok', 'built in', 'YouTube and Vimeo load only after Play, or with Videos allowed.'],
            ['Staying signed in', 'Until sign-out or the browser closes; 30 days unused', 'info', 'built in', 'Sessions are kept in app/storage/sessions' . (is_dir($sess) && is_writable($sess) ? '.' : ', which isn\'t writable yet: people may be signed out sooner.')],
            ['Privacy keys', $env('TAG_HASH_KEY') !== '' && $env('GUEST_HASH_KEY') !== '' ? 'Set' : 'Not all set', $env('TAG_HASH_KEY') !== '' && $env('GUEST_HASH_KEY') !== '' ? 'ok' : 'bad', 'TAG_HASH_KEY, GUEST_HASH_KEY', 'Turn counts into codes no one can trace back.'],
        ],
        'The site' => [
            ['Site address', $env('APP_URL') ?: 'Not set', 'info', 'APP_URL', 'Used in emails and shared links.'],
            ['Default place', $env('DEFAULT_GEO_HUB_SLUG') ?: 'Not set', 'info', 'DEFAULT_GEO_HUB_SLUG', 'Where the site opens with no place chosen.'],
            ['Two-step sign-in for staff', strtolower($env('TV_TWO_STEP')) === 'off' ? 'Off (TV_TWO_STEP=off)' : 'On', strtolower($env('TV_TWO_STEP')) === 'off' ? 'bad' : 'ok', 'TV_TWO_STEP', 'Staff enter a code from their authenticator app.'],
            ['Crawler key', $env('CRAWLER_API_TOKEN') !== '' ? 'Set' : 'Not set', $env('CRAWLER_API_TOKEN') !== '' ? 'ok' : 'bad', 'CRAWLER_API_TOKEN', 'Lets the crawler worker report in.'],
            ['Staff mailboxes', $env('STAFF_EMAIL_DOMAIN') ?: 'trvgo.com', 'info', 'STAFF_EMAIL_DOMAIN', 'Made from Staff logins through cPanel (Bluehost in the Service register).'],
        ],
        'Server' => [
            ['PHP', PHP_VERSION, 'info', 'the host', ''],
            ['Database', (function () use ($pdo) { try { return 'MySQL ' . $pdo->query('SELECT VERSION()')->fetchColumn(); } catch (Throwable) { return 'Not reachable'; } })(), 'info', 'DB_HOST, DB_DATABASE', 'Updates run themselves: Database updates.'],
            ['Maintenance notice', 'Coming', 'info', '', 'A notice across the top of every page while work is being done.'],
        ],
    ];
    foreach ($G as $g => $rs) {
        $b = '';
        foreach ($rs as [$lab, $val, $tone, $from, $what]) $b .= '<div class="os-part"><p data-tv-status="' . $tone . '" data-tv-label="' . $e($lab) . ':">' . $e($val) . '</p><small>' . $e($what) . ($from !== '' ? ' <code>' . $e($from) . '</code>' : '') . '</small></div>';
        $bad = count(array_filter($rs, fn ($r) => $r[2] === 'bad'));
        $h .= tv_admin_fold($g, $bad ? $bad . ' to look at' : count($rs) . ' settings', $bad ? 'bad' : '', '', $b, (bool) $bad);
    }
    $h .= '<div class="os-links"><a class="ak-b" href="/admin/config/register">Service register</a><a class="ak-b" href="/admin/config/dbupdates">Database updates</a><a class="ak-b" href="/admin/config/scheduler">Scheduled tasks</a><a class="ak-b" href="/admin/config/ai">AI use</a></div>';
    return '<div class="os">' . $h . '</div>' . tv_os_css() . tv_os_env_js();
}

/** Settings changed here (EnvSettings, decisions/0125), each with Edit: written into .env, the file before kept. */
function tv_os_env_rows(array $keys): string
{
    if (!$keys) return '';
    require_once __DIR__ . '/../lib/EnvSettings.php';
    require_once __DIR__ . '/../admin/Access.php';
    [$ok, $why] = Access::can('config.env', ['min' => 'executive', 'depts' => []]);
    $e = 'tv_w_e'; $h = '';
    foreach ($keys as $k) {
        if (!isset(EnvSettings::EDITABLE[$k])) continue;
        [$lab, $what] = EnvSettings::EDITABLE[$k]; $v = EnvSettings::shown($k);   // a choice's name ("Test"), not its code
        $h .= '<div class="os-env"><div class="os-envh"><b>' . $e($lab) . '</b><span data-v>' . ($v !== '' ? $e($v) : '<span class="os-not">Not set</span>') . '</span>'
            . '<button type="button" class="ak-b" data-env="' . $e($k) . '" data-label="' . $e($lab) . '"' . EnvSettings::editAttrs($k) . ($ok ? '' : ' disabled title="' . $e($why) . '" aria-disabled="true"') . '>Edit</button></div>'
            . '<small>' . $e($what) . ' <code>' . $e($k) . '</code>' . ($ok ? '' : ' · ' . $e($why)) . '</small></div>';
    }
    return '<div class="os-envs">' . $h . '</div>';
}

/** A card's requests we're waiting on (each a Pulse case until answered), and Add a request. */
function tv_os_requests(PDO $pdo, string $k, string $csrf, bool $can, string $why): string
{
    $e = 'tv_w_e';
    $open = array_filter(OutsideServices::requests($pdo), fn ($r) => $r['svc'] === $k);
    $h = '<h3 class="os-h">Waiting on them</h3>';
    if (OutsideServices::$reqError !== '') $h .= '<p data-tv-status="bad" data-tv-label="Can\'t keep requests:">' . $e(OutsideServices::$reqError) . '. Pulse has an alert for it.</p>';
    foreach ($open as $id => $r) $h .= '<p data-tv-status="info" data-tv-label="' . $e($r['ticket'] !== '' ? 'Ticket #' . $r['ticket'] . ':' : 'Asked:') . '">' . $e($r['what']) . ' Asked <time datetime="' . $e(date('c', strtotime((string) $r['sent']))) . '" data-tv-time>' . $e(date('M j', strtotime((string) $r['sent']))) . '</time>.'
        . ($r['link'] !== '' ? ' <a class="os-go" href="' . $e($r['link']) . '" target="_blank" rel="noopener">Their support ↗</a>' : '') . ' Its Pulse case: <a class="os-go" href="/admin/dashboard/stream">record their answer there</a>.</p>';
    $who = OutsideServices::COMPANIES[$k][0] ?? 'them';
    if (!$open) $h .= '<p class="os-note">Nothing waiting on ' . $e($who) . '. Sent them a question or a support ticket and waiting for their answer? Note it below: Pulse keeps it as a case, reminds you after 5 days, and closes it when you record their answer.</p>';
    if (!$can) return $h . '<p class="os-note">Adding one needs ' . $e($why) . '.</p>';
    return $h . '<details class="os-req"><summary class="ak-b">Note a request we sent ' . $e($who) . '</summary><form method="post" class="os-notes"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="svc_request" value="' . $e($k) . '">'
        . '<label><span>What you asked</span><input class="ak-in" name="what" maxlength="120" required data-spell placeholder="Reseller pricing for our account"></label>'
        . '<label><span>Ticket number</span><input class="ak-in" name="ticket" maxlength="30" spellcheck="false" placeholder="3113488"></label>'
        . '<label><span>Link</span><input class="ak-in" name="link" type="url" maxlength="90" spellcheck="false" placeholder="https://…"></label>'
        . '<button class="ak-b">Keep it in Pulse</button></form></details>';
}

/** Name.com's card: the price our account pays per ending we sell, beside Name.com's retail and list prices, and a verdict. */
function tv_os_namecom_prices(): string
{
    require_once __DIR__ . '/../lib/Domains.php';
    $e = 'tv_w_e'; $m = fn (int $c) => $c > 0 ? '$' . number_format($c / 100, 2) : '—';
    [$rows, $at, $err] = Domains::priceList(isset($_GET['nc_prices']));
    $h = '<h3 class="os-h">Prices we pay (1 year, from Name.com)</h3>';
    if ($err !== '') return $h . '<p data-tv-status="bad" data-tv-label="Not available:">' . $e($err) . '</p>';
    $retail = 0; $cheaper = 0; $sold = 0;
    $t = '<div class="os-pt"><table><thead><tr><th>Ending</th><th>We pay to register</th><th>Public price</th><th>We pay to renew</th><th>Public renewal</th></tr></thead><tbody>';
    foreach ($rows as [$tld, $reg, $regR, $regL, $ren, $renR, $renL]) {
        if ($reg <= 0) { $t .= '<tr><td>.' . $e($tld) . '</td><td colspan="4">Not sold by Name.com</td></tr>'; continue; }
        $sold++; $off = $renR > 0 && $ren < $renR; $off ? $cheaper++ : $retail++;
        $t .= '<tr><td>.' . $e($tld) . '</td><td><b>' . $m($reg) . '</b></td><td>' . $m($regR) . ($regL > $regR ? ' <s>' . $m($regL) . '</s>' : '') . '</td><td><b>' . $m($ren) . '</b>' . ($off ? ' <em>' . (int) round(100 - $ren * 100 / $renR) . '% off</em>' : '') . '</td><td>' . $m($renR) . ($renL > $renR ? ' <s>' . $m($renL) . '</s>' : '') . '</td></tr>';
    }
    $t .= '</tbody></table></div>';
    $v = $sold && $cheaper === 0
        ? '<p data-tv-status="bad" data-tv-label="On retail:">Our account pays Name.com\'s public price on every ending we sell, so ours can\'t compete with reseller-priced sites. Ask Name.com about reseller pricing: <a class="os-go" href="https://www.name.com/support/api" target="_blank" rel="noopener">https://www.name.com/support/api</a></p>'
        : ($cheaper ? '<p data-tv-status="ok" data-tv-label="Discounted:">Our account pays less than Name.com\'s public renewal on ' . $cheaper . ' of ' . $sold . ' endings.</p>' : '');
    return $h . $v . $t . '<p class="os-note">Name.com\'s price list, kept 12 hours' . ($at !== '' ? ': as of <time datetime="' . $e(date('c', strtotime($at))) . '" data-tv-time>' . $e(date('M j, g:i A', strtotime($at))) . ' UTC</time>' : '') . '. <a class="os-go" href="?nc_prices=1#svc-namecom">Ask Name.com again</a>. Ours add DOMAIN_MARKUP_CENTS a year on top.</p>';
}

function tv_os_css(): string
{
    static $done = false; if ($done) return ''; $done = true;
    return '<style>'
         // the table (2026-11-17): one row per company; the card opens under it, in place
         . '.os .os-key{margin:.15rem 0 .35rem}.os .os-ks{font-weight:600;color:#6b5a48}.os .os-ks.ok{color:#3F6B57}.os .os-ks.bad{color:#B45309}.os .os-keyf{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center;margin:.25rem 0}.os .os-keyf .ak-in{max-width:16rem;min-width:0}.os .os-retire{flex-basis:100%}.os .os-retire>summary{cursor:pointer;color:#92400E;font-size:.85rem;list-style:none}.os .os-retire>summary::-webkit-details-marker{display:none}.os .os-retire form{margin-top:.35rem}.os .os-stage{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;margin:.2rem 0 .5rem}.os .os-stage p{margin:0}.os .os-dl{display:grid;grid-template-columns:max-content 1fr;gap:.2rem .7rem;margin:.4rem 0}.os .os-hist{margin:.2rem 0;padding-left:1.1rem;font-size:.85rem}'
         . '.os .os-th,.os .os-rh{display:grid;grid-template-columns:1.6rem minmax(0,1fr) 12rem 6rem 9.5rem 1rem;align-items:center;gap:.6rem}'
         . '.os .os-th{padding:.2rem .6rem;font:700 .72rem system-ui,sans-serif;letter-spacing:.06em;text-transform:uppercase;color:#8A7355}.os .os-th button{all:unset;cursor:pointer}.os .os-th button.on{color:#92400E;text-decoration:underline}'
         . '.os .os-rows{display:grid;grid-template-columns:minmax(0,1fr);gap:2px}.os .os-r{min-width:0;background:#fff;border:1px solid #EADFC4;border-radius:.5rem}'
         . '.os .os-rh{all:unset;box-sizing:border-box;width:100%;display:grid;grid-template-columns:1.6rem minmax(0,1fr) 12rem 6rem 9.5rem 1rem;align-items:center;gap:.6rem;padding:.3rem .6rem;cursor:pointer;min-height:2.2rem}'
         . '.os .os-rn{min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.os .os-rn b{font-weight:600;color:#2B1D14}.os .os-rn small{margin-left:.5rem;color:#8A7355;font-size:.8rem}'
         . '.os .os-rs,.os .os-rt{font-size:.8rem;color:#6b5a48;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.os .os-rc{font-size:.85rem;text-align:right;white-space:nowrap}.os .os-rx{color:#92400E}'
         . '.os .os-r[open-row] .os-rx{transform:rotate(180deg)}.os .os-rb{padding:.4rem .8rem .8rem 2.8rem;border-top:1px solid #F1E8D6}'
         . '.os .os-m{display:inline-flex;width:1.3rem;height:1.3rem;border-radius:50%;align-items:center;justify-content:center}.os .os-svg{width:.85rem;height:.85rem}'
         . '.os .os-m-ok{background:#DCFCE7;color:#15803D}.os .os-m-bad{background:#FEE2E2;color:#B91C1C}.os .os-m-na{background:#F1EDE6;color:#8a7a68}'
         . '.os .os-test{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem .6rem;margin:.1rem 0 .6rem;font-size:.85rem;color:#5C4632}'
         . '@media (max-width:767px){.os .os-th{display:none}.os .os-rh{grid-template-columns:1.6rem minmax(0,1fr) auto 1rem}.os .os-rs,.os .os-rt{display:none}.os .os-rn small{display:none}.os .os-rb{padding-left:.6rem}.os .os-req>summary.ak-b{white-space:normal;max-width:100%;text-align:left}}'
         . '.os .os-what{margin:.1rem 0 .6rem;font-weight:600;color:#3E2B1F}.os .os-part{margin:.35rem 0 .55rem}.os .os-part p{margin:0}.os .os-part small,.os .os-note{display:block;font-size:.78rem;color:#8A7355;margin-top:.15rem}'
         . '.os .os-h{font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:#8A7355;margin:.9rem 0 .35rem}.os .os-line{margin:.35rem 0;font-size:.9rem}.os .os-links{display:flex;flex-wrap:wrap;gap:.4rem;margin:.8rem 0 .2rem}.os .os-links .ak-b{text-decoration:none}'
         . '.os .os-sign{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem .5rem;margin:.1rem 0 .6rem}.os .os-sign .ak-b{text-decoration:none}.os .os-signt{font-size:.85rem;color:#5C4632;min-width:0}'
         . '.os .os-ed{display:inline-block}.os .os-ed>summary{list-style:none;cursor:pointer;width:28px;height:28px;border-radius:999px;border:1px solid #CBB98D;display:inline-flex;align-items:center;justify-content:center;color:#92400E;font-size:.85rem}.os .os-ed>summary::-webkit-details-marker{display:none}.os .os-ed[open]{flex-basis:100%;order:9}.os .os-ed .os-notes{margin-top:.5rem}'
         . '.os .os-notes{display:grid;gap:.45rem;max-width:40rem}.os .os-notes label{display:grid;grid-template-columns:8rem 1fr;gap:.5rem;align-items:center;font-size:.85rem}.os .os-notes label span{font-weight:700}.os .os-notes .ak-b{justify-self:start}@media (max-width:560px){.os .os-notes label{grid-template-columns:1fr}}'
         . '.os .os-env{padding:.5rem 0;border-top:1px solid #EEE3CA}.os .os-env:first-child{border-top:0}.os .os-envh{display:flex;flex-wrap:wrap;align-items:center;gap:.3rem .6rem}.os .os-envh b{min-width:12rem}.os .os-envh [data-v]{flex:1;min-width:8rem;word-break:break-all}'
         . '.os .os-req{margin:.3rem 0}.os .os-req>summary{list-style:none;cursor:pointer;display:inline-flex}.os .os-req>summary::-webkit-details-marker{display:none}.os .os-req .os-notes{margin-top:.5rem}'
         . '.os .os-pt{overflow-x:auto;margin:.3rem 0}.os .os-pt table{border-collapse:collapse;font-size:.85rem;min-width:100%}.os .os-pt th,.os .os-pt td{text-align:left;padding:.3rem .5rem;border-bottom:1px solid #EEE3CA;white-space:nowrap}.os .os-pt th{font-weight:700;color:#5C4632;font-size:.78rem}.os .os-pt s{color:#8A7355}.os .os-pt em{font-style:normal;color:#3F7A5E;font-weight:700;font-size:.78rem}'
         . '.os .os-env small{display:block;font-size:.78rem;color:#8A7355;margin-top:.2rem}.os .os-not{color:#8A7355;font-weight:400}.os .os-go{color:#92400E;text-decoration:underline;font-weight:600}.os code{font-size:.75rem;word-break:break-all}</style>';
}

/** Settings → Change here: one setting at a time, written into .env (decisions/0125); choices and the next step come
 *  from js/tv-env-edit.js (shared with Settings). */
function tv_os_env_js(): string
{
    return '<script src="/js/tv-env-edit.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/tv-env-edit.js') . '"></script>'
         . '<script>document.querySelectorAll(\'.os\').forEach(function (r) { TvEnvEdit.bind(r); });</script>';
}

/** Storage: the files from before object storage, moved off the server a batch per request (decisions/0123). */
function tv_os_move_js(): string
{
    return <<<'JS'
<script>
(function () {
  var box = document.getElementById('tv-s3move'); if (!box) return;
  var t = box.querySelector('[data-t]'), btn = box.querySelector('button');
  function mb(b) { return b >= 1073741824 ? (b / 1073741824).toFixed(2) + ' GB' : (b / 1048576).toFixed(1) + ' MB'; }
  function left(r) { if (!r.left) { t.textContent = 'Every file is in storage: none left on the server.'; btn.style.display = 'none'; return; }
    t.textContent = r.left + ' file' + (r.left === 1 ? '' : 's') + ' (' + mb(r.left_bytes) + ') from before still to move into their owners\' folders in storage. Nothing changes for visitors while they move. The scheduled task Files to S3 does this too.';
    btn.textContent = 'Move ' + r.left + ' file' + (r.left === 1 ? '' : 's'); btn.style.display = ''; }
  fetch('/admin/api/move-files.php', { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (r) { if (r.ok) left(r); else t.textContent = (r.error && r.error.message) || 'Couldn\'t check.'; }).catch(function () { t.textContent = 'Couldn\'t check.'; });
  btn.addEventListener('click', async function () {
    if (!(await TvAsk.confirm('Move them now? Keep this page open until it says done; it goes a batch at a time.', { anchor: btn, yes: 'Move them' }))) return;
    TvAsk.busy(btn, true);
    var csrf = (await (await fetch('/api/auth/session.php', { credentials: 'include' })).json()).csrf_token, total = 0, bytes = 0, r;
    try {
      do {
        r = await (await fetch('/admin/api/move-files.php', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: '{}' })).json();
        if (!r.ok) throw r.error || {};
        total += r.moved; bytes += r.bytes;
        t.textContent = 'Moved ' + total + ' (' + mb(bytes) + ')… ' + r.left + ' to go.';
      } while (r.moved + (r.missing || 0) > 0 && r.left > r.stuck);
      if (r.left) { left(r); TvAsk.say('Moved ' + total + '. ' + r.left + ' wouldn\'t go yet (they still work from the server); try again later.', { anchor: btn }); }
      else { t.textContent = 'Done: moved ' + total + ' file' + (total === 1 ? '' : 's') + ' (' + mb(bytes) + '). Every file is in storage.'; btn.style.display = 'none'; }
    } catch (e) { t.textContent = (e && e.message) || 'The move stopped. Try again: what moved stays moved.'; }
    TvAsk.busy(btn, false);
  });
})();
</script>
JS;
}
