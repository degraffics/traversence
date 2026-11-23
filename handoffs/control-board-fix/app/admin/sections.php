<?php
// app/admin/sections.php — sections drawn by a module widget (the 'html' widgets in app/admin/manifests.php): the tools
// that already work, wrapped (the Dashboard, Pulse, the card scanner, promotions, territories), and the new ones (the
// funnel, the workflow builder, the permissions matrix).
declare(strict_types=1);
require_once __DIR__ . '/crawler-console.php';   // Insights → Crawler, the crawler console (decisions/0160 §4)
require_once __DIR__ . '/index-console.php';   // Insights → The index (W0), the indexer's frontier (decisions/0164 §1.4)
require_once __DIR__ . '/automation-console.php';
require_once __DIR__ . '/control-console.php';   // System Configuration → System Control Board (decisions/0171)
require_once __DIR__ . '/preflight-console.php';   // System Configuration → Pre-flight (decisions/0163 §10's note of 2026-11-16)   // System Configuration → Automation, the switches (decisions/0163 §9)
require_once __DIR__ . '/cleanup-console.php';   // Insights → Data cleanup (decisions/0163 §8 stage 1b)
require_once __DIR__ . '/operations-console.php';   // System Configuration → Service register, Costs, Workflow board (decisions/0163 §1–§5)
require_once __DIR__ . '/comms-console.php';   // System Configuration → Communications (decisions/0167, 0172 §4.3)
require_once __DIR__ . '/inbox-console.php';   // Dashboard → My inbox and Your communications (decisions/0172 §4.7, §4.4)

require_once __DIR__ . '/widgets.php';

function tv_admin_mod_today(PDO $pdo): string
{
    require_once __DIR__ . '/../ui/admin-dashboard.php';
    require_once __DIR__ . '/../ui/pulse-ui.php';
    $me = Auth::currentUser();
    $ctx = Roles::current($pdo, (int) $me['id']);
    return tv_pulse_board($pdo, Access::staff(), (int) $me['id'], '', tv_admin_dashboard($pdo, $ctx, (int) $me['id']));
}

/** Dashboard → Quick actions: the everyday tools, each opened over the Dashboard (decisions/0109 A3a). */
function tv_admin_mod_quick(PDO $pdo): string
{
    $h = '<div class="ak-wfs">';
    foreach ([['Scan a card', 'camera', '/admin/growth/cards'], ['New client order', 'cart', '/admin/client-order.php?new=1'], ['Find a listing', 'search', '/admin/local/listings'],
              ['Add a listing', 'store', '/admin/listing-add.php'], ['Calls today', 'contact', '/admin/growth/calls'], ['Review', 'inbox', '/admin/local/review'],
              ['Claims', 'shield', '/admin/local/claims'], ['GO Codes', 'qr', '/admin/market/codes'], ['Reports', 'shield', '/admin/social/reports'], ['Settings', 'settings', '/admin/config/settings']] as [$l, $i, $u]) {
        $sec = preg_match('#^/admin/([a-z]+)/([a-z0-9_-]+)$#', $u, $x) ? Modules::canSection($x[1], $x[2]) : [true, ''];
        $h .= $sec[0] ? '<a class="ak-wf tv-bleed" href="' . tv_w_e($u) . '" data-ak-page><span class="ak-rh">' . tv_icon($i, 'ak-svg') . '<b class="ak-nm">' . tv_w_e($l) . '</b></span></a>'
                      : '<span class="ak-wf tv-bleed ak-off" title="' . tv_w_e($sec[1]) . '"><span class="ak-rh">' . tv_icon($i, 'ak-svg') . '<b>' . tv_w_e($l) . '</b></span><small>' . tv_w_e($sec[1]) . '</small></span>';
    }
    return $h . '</div><p class="ak-note">Each opens over the Dashboard; Back closes it.</p>';
}

function tv_admin_mod_stream(PDO $pdo): string
{
    require_once __DIR__ . '/../ui/pulse-ui.php';
    return tv_pulse_board($pdo, Access::staff(), (int) Auth::currentUser()['id'], 'stream', '');
}

function tv_admin_mod_stream_settings(PDO $pdo): string
{
    require_once __DIR__ . '/../ui/pulse-ui.php';
    return tv_pulse_settings($pdo, Access::staff());
}

function tv_admin_mod_cards(PDO $pdo): string
{
    require_once __DIR__ . '/../ui/card-scanner.php';
    require_once __DIR__ . '/../lib/crawler/IntakeStager.php';
    require_once __DIR__ . '/../lib/CardScan.php';
    if (!CardScan::ready($pdo)) return '<div class="ak-card">Run <code>app/migrations/2026-12-20_card_scanner.sql</code> first.</div>';
    $opts = implode('', array_map(fn ($c) => '<option value="' . (int) $c['id'] . '">' . tv_w_e((string) ($c['label'] ?? $c['name'] ?? '')) . '</option>', IntakeStager::leafCategories($pdo)));
    return tv_card_scanner($pdo, $opts);
}

function tv_admin_mod_promos(PDO $pdo): string
{
    require_once __DIR__ . '/../ui/promotions-admin.php';
    require_once __DIR__ . '/../ui/admin-crawler.php';
    $fl = tv_crawler_flash();
    return ($fl ? '<p class="ak-flash' . (($fl['type'] ?? '') === 'error' ? ' bad' : '') . '" role="status" data-tv-flash="' . tv_w_e((string) ($fl['type'] ?? '')) . '">' . tv_w_e((string) ($fl['text'] ?? '')) . '</p>' : '')
        . tv_w_table($pdo, 'growth/promotions', true)
        . '<h2 class="ak-h">Make and issue</h2>' . tv_promotions_panel($pdo, Promotions::canMake(Access::staff()), (string) ($_SESSION['csrf_token'] ?? ''));
}

function tv_admin_mod_territories(PDO $pdo): string
{
    require_once __DIR__ . '/../ui/lead-panel.php';
    return tv_lead_territories();
}

function tv_admin_mod_funnel(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/Sales.php';
    $F = Sales::funnel($pdo);
    $rows = [['Not contacted', 'new'], ['Contacted', 'contacted'], ['Met in person', 'met'], ['Interested', 'interested'], ['Meeting set', 'meeting'], ['Claim invite sent', 'invited'], ['Quote sent', 'quote'], ['Won', 'won'],
             ['Claimed', 'claimed'], ['Verified', 'verified'], ['Paying', 'paid'], ['Not now', 'not_now'], ['Not interested', 'no'], ['Lost', 'lost']];
    $max = max(1, ...array_map(fn ($r) => (int) ($F[$r[1]] ?? 0), $rows));
    $h = '<div class="ak-metrics">';
    foreach ([['Contacts this week', $F['week']['contacts'] ?? 0], ['Invites this week', $F['week']['invites'] ?? 0], ['Claims this week', $F['week']['claims'] ?? 0], ['Follow-ups due', $F['due'] ?? 0]] as [$l, $v])
        $h .= '<div class="ak-metric tv-bleed"><b>' . (int) $v . '</b><span>' . tv_w_e($l) . '</span></div>';
    $h .= '</div><div class="ak-card tv-bleed"><h2 class="ak-h" style="margin-top:0">From first call to paying</h2>';
    foreach ($rows as [$l, $k]) {
        $n = (int) ($F[$k] ?? 0);
        $h .= '<div class="ak-bar"><span>' . tv_w_e($l) . '</span><i style="width:' . max(1, (int) round(log(1 + $n) / log(1 + $max) * 100)) . '%"></i><b>' . number_format($n) . '</b></div>';
    }
    return $h . '<p class="ak-note">Bars are scaled to show the small stages beside the large ones.</p></div>';
}

/** System Configuration → Workflows: every workflow, and the builder (Managers and up; decisions/0109 A4). */
function tv_admin_mod_workflows(PDO $pdo): string
{
    if (!Workflow::ready($pdo)) return '<div class="ak-card">Run <code>app/migrations/2026-12-24_admin_engine.sql</code> first.</div>';
    [$can, $why] = Access::can('config.workflows.edit', ['min' => 'manager', 'depts' => []]);
    $list = [];
    foreach (Workflow::all($pdo) as $w) {
        $d = $w['version'] ? Workflow::def($pdo, $w['wf_key']) : (json_decode((string) $w['draft'], true) ?: ['stages' => []]);
        $counts = [];
        foreach ($pdo->query('SELECT stage, COUNT(*) n FROM ' . Database::t('wf_items') . ' WHERE wf_key = ' . $pdo->quote($w['wf_key']) . ' GROUP BY stage') as $r) $counts[$r['stage']] = (int) $r['n'];
        $list[] = ['key' => $w['wf_key'], 'name' => $w['name'], 'module' => $w['module'], 'builtin' => (bool) $w['builtin'], 'version' => (int) $w['version'], 'draft' => $w['draft'] !== null,
                   'stages' => array_map(fn ($s) => $s['label'], $d['stages']), 'items' => array_sum($counts)];
    }
    $cfg = ['can' => $can, 'why' => $why, 'workflows' => $list,
        'checks' => array_map(fn ($c) => $c[0], Workflow::checks()),
        'effects' => array_map(fn ($e) => ['label' => $e[0], 'params' => $e[1]], Workflow::effects()),
        'levels' => Access::LEVEL_NAMES, 'depts' => Access::DEPT_NAMES,
        'modules' => array_map(fn ($m) => $m[0] . '. ' . $m[1], Workspaces::ALL),
        'widgets' => ['Fields' => 'Details', 'Files' => 'Files and proofs', 'Notes' => 'Notes', 'Timeline' => 'History', 'Related' => 'Related items']];
    return '<section class="ak-builder" data-ak-builder="' . tv_w_e(json_encode($cfg, JSON_UNESCAPED_UNICODE)) . '"><div class="ak-load">Loading workflows…</div></section>'
        . '<script src="/js/admin-builder.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/admin-builder.js') . '" defer></script>';
}

/** System Configuration → Permissions: each tool's and each move's rule (the lowest level, and departments). */
function tv_admin_mod_permissions(PDO $pdo): string
{
    [$can, $why] = Access::can('config.permissions.edit', ['min' => 'director', 'depts' => []]);
    $rows = [];
    foreach (Modules::tools() as $k => $t) {
        $mod = strtok($k, '/');
        $rows[] = [$k . '.view', 'See ' . strtolower($t['title']), $t['view'] ?? ['min' => 'staff', 'depts' => Modules::depts($mod)], $mod];
        if (isset($t['create'])) $rows[] = [$k . '.create', $t['create']['label'], $t['create']['rule'] ?? ['min' => 'staff', 'depts' => []], $mod];
    }
    if (Workflow::ready($pdo)) foreach (Workflow::all($pdo) as $w) {
        if (!$w['version']) continue;
        $d = Workflow::def($pdo, $w['wf_key']);
        foreach ($d['moves'] as $m) if (empty($m['auto'])) $rows[] = [$w['wf_key'] . '.move.' . $m['key'], $w['name'] . ': ' . ($m['label'] ?? $m['key']), ['min' => $m['who']['min'] ?? 'staff', 'depts' => $m['who']['depts'] ?? []], $w['module']];
        foreach ($d['any'] as $a) $rows[] = [$w['wf_key'] . '.act.' . $a['key'], $w['name'] . ': ' . $a['label'], ['min' => $a['who']['min'] ?? 'staff', 'depts' => $a['who']['depts'] ?? []], $w['module']];
    }
    $rows[] = ['config.workflows.edit', 'Build and publish workflows', ['min' => 'manager', 'depts' => []], 'config'];
    $rows[] = ['config.permissions.edit', 'Change these permissions', ['min' => 'director', 'depts' => []], 'config'];
    $rows[] = ['pulse.support_mail', 'Open the support inbox', ['min' => 'manager', 'depts' => ['svc']], 'pulse'];
    $rows[] = ['config.switches', 'Turn features on and off (Switches)', ['min' => 'executive', 'depts' => []], 'config'];
    $rows[] = ['config.automation', 'Pause and resume automation: the master switch, workflows, tasks and services', ['min' => 'executive', 'depts' => []], 'config'];
    $rows[] = ['config.operations', 'Set the service register\'s caps and paces, watching, and each workflow\'s settings (saved as versions)', ['min' => 'executive', 'depts' => []], 'config'];
    require_once __DIR__ . '/../lib/DataCleanup.php';
    foreach (DataCleanup::PERMS as $k => $label) $rows[] = [$k, $label, $k === 'insights.cleanup_undo' ? DataCleanup::RULE_UNDO : DataCleanup::RULE_RUN, 'insights'];   // Insights → Data cleanup
    require_once __DIR__ . '/../lib/Releases.php';
    foreach (Releases::PERMS as $k => $label) $rows[] = [$k, $label, ['min' => 'founder', 'depts' => []], 'config'];   // decisions/0145 §5: Founder only until a role is given one
    require_once __DIR__ . '/../lib/Blocks.php';
    foreach (Blocks::PERMS as $k => $label) $rows[] = [$k, $label, ['min' => 'founder', 'depts' => []], 'config'];   // decisions/0159 §6: the editor, Founder only until a role is given one
    $h = '<p class="ak-note">Who may do what, wherever the tool is opened. The Founder and Executives may do everything. An action someone may not take shows greyed out with the reason.' . ($can ? '' : ' <b>' . tv_w_e($why) . ' to change these.</b>') . '</p>';
    $h .= '<div class="ak-card tv-bleed ak-perm" data-ak-perm="' . ($can ? '1' : '0') . '">';
    $last = '';
    foreach ($rows as [$action, $label, $def, $mod]) {
        $r = Access::rule($action, $def);
        if ($mod !== $last) { $h .= '<h3 class="ak-h">' . tv_w_e(isset(Workspaces::ALL[$mod]) ? Workspaces::ALL[$mod][0] . '. ' . Workspaces::ALL[$mod][1] : $mod) . '</h3>'; $last = $mod; }
        $h .= '<form class="ak-prow" data-action="' . tv_w_e($action) . '"><span>' . tv_w_e($label) . '</span><select name="min"' . ($can ? '' : ' disabled') . '>';
        foreach (Access::LEVEL_NAMES as $k => $l) $h .= '<option value="' . $k . '"' . ($r['min'] === $k ? ' selected' : '') . '>' . tv_w_e(ucfirst(preg_replace('/^(a|an|the) /', '', $l))) . ' and up</option>';
        $h .= '</select><span class="ak-depts">';
        foreach (Access::DEPT_NAMES as $k => $l) $h .= '<label><input type="checkbox" name="d" value="' . $k . '"' . (in_array($k, $r['depts'], true) ? ' checked' : '') . ($can ? '' : ' disabled') . '> ' . tv_w_e($l) . '</label>';
        $h .= '</span></form>';
    }
    return $h . '</div><p class="ak-note">No department ticked: any department. Changes save as you make them.</p>';
}

/** System Configuration → Data separation: the check of the four databases and their logins (decisions/0110). */
function tv_admin_mod_datacheck(PDO $pdo): string
{
    [$can, $why] = Access::can('config.datacheck.run', ['min' => 'director', 'depts' => ['eng']]);
    $csrf = (string) ($_SESSION['csrf_token'] ?? '');
    $h = '<p class="ak-note">Each kind of data lives in its own database with its own login: public (main), admin, businesses (listings) and members (users). This checks that each login reaches only its own, and that this server lets one business be walled from another inside the listings database. It makes small test objects named tvchk_* and removes them.</p>';
    $h .= '<form method="post" class="ak-acts"><input type="hidden" name="csrf" value="' . tv_w_e($csrf) . '"><input type="hidden" name="datacheck" value="1">'
        . '<button class="ak-b go"' . ($can ? '' : ' disabled title="' . tv_w_e($why) . '"') . '>Run the check</button>' . ($can ? '' : '<span class="ak-why">' . tv_w_e($why) . '</span>') . '</form>';
    $h .= tv_admin_datamove($pdo, $can, $why, $csrf);
    if (!$can || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['datacheck'])) return $h;
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) return $h . '<p class="ak-flash bad">Your session expired. Reload the page and run it again.</p>';
    require_once __DIR__ . '/DataCheck.php';
    $rows = DataCheck::run();
    $fails = count(array_filter($rows, fn ($r) => $r[2] === false));
    $h .= '<p class="ak-flash' . ($fails ? ' bad' : '') . '" role="status">' . ($fails ? $fails . ' to fix. Each says what.' : 'Everything is as it should be.') . '</p><div>';
    $groups = [];
    foreach ($rows as $r) $groups[$r[0]][] = $r;
    foreach ($groups as $g => $rs) {
        $bad = count(array_filter($rs, fn ($r) => $r[2] === false));
        $body = '';
        foreach ($rs as [, $what, $ok, $detail]) $body .= '<div class="ak-res"><b class="' . ($ok === true ? 'ok' : ($ok === false ? 'bad' : '')) . '">' . ($ok === true ? '✓' : ($ok === false ? '✗' : '–')) . '</b><span>' . tv_w_e($what) . '<small>' . tv_w_e($detail) . '</small></span></div>';
        $h .= tv_admin_fold($g, $bad ? $bad . ' to fix' : 'All ' . count($rs) . ' as they should be', $bad ? 'bad' : 'ok', '', $body, $bad > 0);
    }
    return $h . '</div>';
}

/** Data separation → moving the admin's tables into the admin database, a group at a time, with Undo (0110 phase B). */
function tv_admin_datamove(PDO $pdo, bool $can, string $why, string $csrf): string
{
    require_once __DIR__ . '/DataMove.php';
    $msg = '';
    try { $back = DataMove::home($pdo, true); if ($back) $msg = '<div class="ak-flash" role="status" data-tv-flash="ok"><b>The identity record is back in main</b> (it is the listings\' own record now).<br>' . implode('<br>', array_map('tv_w_e', $back)) . '</div>'; } catch (Throwable $e) { $msg = '<p class="ak-flash bad" role="status" data-tv-flash="error">The identity record couldn\'t come back to main yet: ' . tv_w_e($e->getMessage()) . '</p>'; }
    if ($can && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['datamove']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $g = (int) ($_POST['group'] ?? 0);
        try {
            $lines = $_POST['datamove'] === 'undo' ? DataMove::undo($pdo, $g) : DataMove::move($pdo, $g);
            $msg = '<div class="ak-flash" role="status"><b>' . ($_POST['datamove'] === 'undo' ? 'Put back.' : 'Moved.') . '</b><br>' . implode('<br>', array_map('tv_w_e', $lines)) . '</div>';
        } catch (Throwable $e) { $msg = '<p class="ak-flash bad" role="status">' . tv_w_e($e->getMessage()) . '</p>'; }
    }
    $h = '<h2 class="ak-h">Move the admin\'s data</h2><p class="ak-note">Each group is copied into the admin database and checked row by row; the old tables stay in main as <code>_moved_…</code> backups. <b>Export the main database in phpMyAdmin before each move.</b> Undo puts a group back, with anything written since.</p>' . $msg . '<div>';
    foreach (DataMove::status($pdo) as $g => $st) {
        $all = $st['present'] > 0 && $st['moved'] === $st['present'];
        $form = fn (string $do, string $label, string $ask, bool $danger) => '<form method="post"><input type="hidden" name="csrf" value="' . tv_w_e($csrf) . '"><input type="hidden" name="group" value="' . $g . '"><input type="hidden" name="datamove" value="' . $do . '">'
            . '<button class="ak-b' . ($do === 'move' ? ' go' : ' no') . '" data-confirm="' . tv_w_e($ask) . '" data-yes="' . tv_w_e($label) . '"' . ($danger ? ' data-danger' : '') . ($can ? '' : ' disabled title="' . tv_w_e($why) . '"') . '>' . tv_w_e($label) . '</button></form>';
        $btn = !$st['ready'] ? '<span class="ak-tag">Next builds</span>'
            : ($all ? $form('undo', 'Undo', 'Put this group back in the main database?', true)
               : $form('move', 'Move', 'Did you export the main database in phpMyAdmin first? Move this group now?', false) . ($st['moved'] ? $form('undo', 'Undo', 'Put this group back in the main database?', true) : ''));
        $tables = implode('', array_map(fn ($t, $r) => '<li>' . tv_w_e($t) . ' <small>' . ($r[0] === 'none' ? 'not here' : number_format((int) $r[1]) . ' rows') . '</small></li>', array_keys($st['tables']), $st['tables']));
        $h .= tv_admin_fold($g . '. ' . $st['name'], $all ? 'In the admin database' : ($st['moved'] ? $st['moved'] . ' of ' . $st['present'] . ' moved' : 'In main'), $all ? 'ok' : '', $btn, '<ul class="ak-tbls">' . $tables . '</ul>', false);
    }
    return $h . '</div>';
}

/** A collapsible row: its title and status on the left, its actions flush right, its body underneath (opened by tapping). */
function tv_admin_fold(string $title, string $status, string $tone, string $actions, string $body, bool $open): string
{
    static $n = 0; $n++;
    return '<div class="ak-fold tv-bleed"><div class="ak-foldh"><button type="button" class="ak-foldt" aria-expanded="' . ($open ? 'true' : 'false') . '" aria-controls="akf' . $n . '"><span class="ak-chev" aria-hidden="true">▸</span><span><b>' . tv_w_e($title) . '</b><small class="' . $tone . '">' . tv_w_e($status) . '</small></span></button>'
        . ($actions !== '' ? '<div class="ak-folda">' . $actions . '</div>' : '') . '</div><div class="ak-foldb" id="akf' . $n . '"' . ($open ? '' : ' hidden') . '>' . $body . '</div></div>'
        . ($n === 1 ? '<script>document.addEventListener("click",function(e){var t=e.target.closest(".ak-foldt");if(!t)return;var b=document.getElementById(t.getAttribute("aria-controls")),o=t.getAttribute("aria-expanded")!=="true";t.setAttribute("aria-expanded",o?"true":"false");if(b)b.hidden=!o;});</script>' : '');
}

/** Back-Office → Taxes & shipping: where our tax comes from, and a check of what an address would pay. */
function tv_admin_mod_taxship(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/Commerce.php';
    [$can, $why] = Access::can('backoffice/taxes.edit', ['min' => 'manager', 'depts' => ['fin']]);
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $msg = '';
    if ($can && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['tax_source']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? '')) && isset(Commerce::TAX_SOURCES[$_POST['tax_source']])) {
        Commerce::setSetting($pdo, 'tax_source', (string) $_POST['tax_source']); $msg = '<p class="ak-flash" role="status">Saved.</p>';
    }
    $src = Commerce::taxSource($pdo);
    $h = '<div class="ak-card tv-bleed"><h2 class="ak-h" style="margin-top:0">Where our sales tax comes from</h2>' . $msg
        . '<form method="post" class="ak-ask" style="background:none;border:0;padding:0"><input type="hidden" name="csrf" value="' . tv_w_e($csrf) . '"><label>Tax on our invoices and checkouts<select class="ak-in" name="tax_source"' . ($can ? '' : ' disabled') . '>';
    foreach (Commerce::TAX_SOURCES as $k => $v) $h .= '<option value="' . $k . '"' . ($k === $src ? ' selected' : '') . '>' . tv_w_e($v) . '</option>';
    $h .= '</select></label><div class="ak-acts"><button class="ak-b go"' . ($can ? '' : ' disabled title="' . tv_w_e($why) . '"') . '>Save</button>' . ($can ? '' : '<span class="ak-why">' . tv_w_e($why) . '</span>') . '</div></form>'
        . '<p class="ak-note">QuickBooks works out the tax on our invoices today. The rates below are used when "Our rates" is chosen, for orders paid outside QuickBooks, and for the businesses\' own checkouts once they open.</p></div>';

    // the check: an address and an order, and what they'd pay
    $g = fn ($k, $d = '') => trim((string) ($_GET[$k] ?? $d));
    $h .= '<div class="ak-card tv-bleed"><h2 class="ak-h" style="margin-top:0">Check an address</h2><form method="get" class="ak-ask" style="background:none;border:0;padding:0">'
        . '<label>State<input class="ak-in" name="ts" value="' . tv_w_e($g('ts', 'AZ')) . '" maxlength="2"></label><label>City<input class="ak-in" name="tc" value="' . tv_w_e($g('tc')) . '"></label>'
        . '<label>ZIP<input class="ak-in" name="tz" value="' . tv_w_e($g('tz')) . '" inputmode="numeric"></label><label>Order total before tax ($)<input class="ak-in" name="tt" value="' . tv_w_e($g('tt', '100')) . '" inputmode="decimal"></label>'
        . '<label>Weight (pounds)<input class="ak-in" name="tw" value="' . tv_w_e($g('tw', '1')) . '" inputmode="decimal"></label><div class="ak-acts"><button class="ak-b go">Check</button></div></form>';
    if (isset($_GET['tz']) || isset($_GET['tc'])) {
        $sub = (int) round((float) $g('tt') * 100);
        $ships = Commerce::shipOptions($pdo, $sub, (float) $g('tw'), $g('ts'), $g('tz'));
        $first = $ships[0]['cents'] ?? 0;
        $tax = Commerce::taxFor($pdo, $g('ts'), $g('tz'), $g('tc'), $sub, $first);
        $m = fn ($c) => '$' . number_format($c / 100, 2);
        $h .= '<div class="ak-sec">Shipping and pickup they\'d be offered</div>' . ($ships ? '' : '<p class="ak-note">None: no option reaches this address.</p>');
        foreach ($ships as $s) $h .= '<div class="ak-res amt"><b class="ok">' . ($s['cents'] ? $m($s['cents']) : 'Free') . '</b><span>' . tv_w_e($s['name']) . ($s['why'] ? '<small>' . tv_w_e($s['why']) . '</small>' : '') . '</span></div>';
        $h .= '<div class="ak-sec">Sales tax (with the cheapest option)</div>';
        foreach ($tax['lines'] as [$n, $mp, $c]) $h .= '<div class="ak-res amt"><b>' . $m($c) . '</b><span>' . tv_w_e($n) . '<small>' . Commerce::pct($mp) . '</small></span></div>';
        $h .= '<p class="ak-note"><b>' . Commerce::pct($tax['rate_milli']) . ' · ' . $m($tax['tax']) . ' tax</b>' . ($tax['lines'] ? '' : ': no rate set for this place') . '. Total ' . $m($sub + $first + $tax['tax']) . '.</p>';
    }
    return $h . '</div>';
}

/** Back-Office → Price lists: what one customer pays for a product and quantity. */
function tv_admin_mod_pricetest(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/PriceLists.php'; require_once __DIR__ . '/../lib/Products.php';
    $prods = Products::ready($pdo) ? Products::all($pdo) : [];
    $g = fn ($k, $d = '') => trim((string) ($_GET[$k] ?? $d));
    $h = '<div class="ak-card tv-bleed"><h2 class="ak-h" style="margin-top:0">What would a customer pay?</h2><form method="get" class="ak-ask" style="background:none;border:0;padding:0">'
        . '<label>Customer\'s email<input class="ak-in" name="pe" value="' . tv_w_e($g('pe')) . '" type="email"></label><label>Product<select class="ak-in" name="pp">';
    foreach ($prods as $p) $h .= '<option value="' . (int) $p['id'] . '"' . ((int) $g('pp') === (int) $p['id'] ? ' selected' : '') . '>' . tv_w_e($p['name']) . '</option>';
    $h .= '</select></label><label>Quantity<input class="ak-in" name="pq" value="' . tv_w_e($g('pq', '100')) . '" inputmode="numeric"></label><div class="ak-acts"><button class="ak-b go">Check</button></div></form>';
    if (isset($_GET['pp']) && ($p = Products::get($pdo, (int) $g('pp')))) {
        $qty = max(1, (int) $g('pq'));
        $cat = Products::priceFor($p, $qty)['total'];
        $r = PriceLists::priceFor($pdo, (int) $p['id'], $qty, $cat, $g('pe'));
        $m = fn ($c) => '$' . number_format($c / 100, 2);
        $each = fn ($c) => PriceLists::money($c / 100 / $qty) . ' each';
        $h .= '<div class="ak-res amt"><b>' . $m($cat) . '</b><span>Catalog: ' . number_format($qty) . '<small>' . $each($cat) . '</small></span></div>'
            . '<div class="ak-res amt"><b class="ok">' . $m($r['total']) . '</b><span>' . ($r['list'] ? tv_w_e($r['list']) . ': ' . tv_w_e($r['why']) : 'No price list applies') . '<small>' . $each($r['total']) . '</small></span></div>';
    }
    return $h . '<p class="ak-note">A customer gets a list by its tag (tag them in Sales &amp; Growth → Customers). Where several lists apply, the lowest price wins.</p></div>';
}

/**
 * Back-Office → Orders: sandbox (test) orders, folded away until wanted. They're numbered on their own (S-1, S-2…),
 * stay out of the lists and money above, and can be deleted (decisions/0101, addendum 2026-10-28).
 */
function tv_admin_mod_sandbox(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/ClientOrders.php';
    if (!Orders::hasCol($pdo, 'qb_env')) return '';
    $rows = $pdo->query("SELECT * FROM orders WHERE qb_env = 'sandbox' ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
    $csrf = tv_w_e((string) ($_SESSION['csrf_token'] ?? ''));
    $fl = $_SESSION['tc_flash'] ?? null; $say = '';   // back from a delete: its message, here
    if (is_array($fl) && ($fl['type'] ?? '') === 'ok' && str_starts_with((string) ($fl['text'] ?? ''), 'Sandbox order')) {
        unset($_SESSION['tc_flash']);
        $say = '<p role="status" style="margin:.5rem 0;padding:.45rem .7rem;border-radius:.5rem;background:' . (($fl['type'] ?? '') === 'error' ? '#FEE2E2' : '#DCFCE7') . '">' . tv_w_e((string) $fl['text']) . '</p>';
    }
    $h = '<details class="ak-card tv-bleed" id="sandbox"' . ($say !== '' ? ' open' : '') . '><summary style="cursor:pointer;font:700 1.15rem var(--tv-font-head,Georgia,serif);color:#3E2B1F">Sandbox orders <span class="ak-note" style="font:400 .9rem system-ui,sans-serif">(' . count($rows) . ', tests: no real money, their own numbers S-1, S-2…)</span></summary>' . $say;
    if (!$rows) return $h . '<p class="ak-note">None. Make one from an order\'s <b>Duplicate ▾ → Test copy</b> while E-Commerce → Payments is on Sandbox.</p></details>';
    $h .= '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:.92rem;margin-top:.5rem"><tr style="text-align:left;font:700 .72rem system-ui,sans-serif;letter-spacing:.06em;text-transform:uppercase;color:#8A7355"><th style="padding:.3rem">Order</th><th style="padding:.3rem">Client</th><th style="padding:.3rem">Status</th><th style="padding:.3rem">Total</th><th style="padding:.3rem">Made</th><th></th></tr>';
    foreach ($rows as $o) {
        $url = (int) ($o['created_by'] ?? 0) > 0 ? '/admin/backoffice/orders/' . (int) $o['id'] : '/admin/order.php?id=' . (int) $o['id'];
        $h .= '<tr style="border-top:1px solid #F1E8D4"><td style="padding:.35rem .3rem"><a class="hb-nm" style="color:#92400E;text-decoration:underline;font-weight:700" href="' . $url . '">Order #' . tv_w_e(Orders::no($o)) . '</a></td>'
            . '<td style="padding:.35rem .3rem">' . tv_w_e($o['client_company'] ?: $o['bill_name']) . '</td><td style="padding:.35rem .3rem">' . tv_w_e(ucfirst(str_replace('_', ' ', (string) $o['status']))) . '</td>'
            . '<td style="padding:.35rem .3rem">' . Orders::usd((int) $o['total_cents']) . '</td><td style="padding:.35rem .3rem">' . tv_w_e(date('M j', strtotime((string) $o['created_at']))) . '</td>'
            . '<td style="padding:.35rem .3rem;text-align:right;white-space:nowrap">'
            . ((int) ($o['created_by'] ?? 0) > 0 && $o['status'] !== 'void' ? '<form method="post" action="/admin/backoffice/orders/' . (int) $o['id'] . '" target="_blank" style="margin:0 .3rem 0 0;display:inline"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="do" value="test_open"><button class="ak-b" title="The client\'s page in a new tab: proofs, approve, pay with a test card. Nothing is emailed.">Open as the client</button></form>' : '')
            . '<form method="post" action="/admin/backoffice/orders/' . (int) $o['id'] . '" style="margin:0;display:inline"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="do" value="test_delete">'
            . '<button class="ak-b" data-confirm="Delete sandbox order #' . tv_w_e(Orders::no($o)) . ' and everything on it?" data-yes="Delete" data-danger style="color:#991B1B">Delete</button></form></td></tr>';
    }
    $h .= '<script>if (location.hash === \'#sandbox\') document.getElementById(\'sandbox\').open = true;</script>';   // back from a delete: still open
    return $h . '</table></div><p class="ak-note">Deleting takes the test order, its items, proofs, files and history off for good. Anything made in the QuickBooks sandbox stays there.</p></details>';
}

/** System Configuration → Scheduled tasks: our scripts' cron jobs in cPanel (through its API), and how each last ran. */
/** System Configuration → AI use (Jason, 2026-10-30: "not waste LLM spending"): each AI feature on or off, its model, runs a
 *  day, its own monthly budget and web searches, with what it has used; all under the month's overall cap. */
/**
 * A feature's provider (decisions/0164 §0.8, §2.3; 0163 §11): Claude, or Gemini's free tier for a feature that reads public
 * pages only, a director's call after the sample comparison. Gemini is greyed out with the reason for every other feature.
 */
function tv_admin_ai_provider(PDO $pdo, string $f, string $dis): string
{
    require_once __DIR__ . '/../lib/Gemini.php';
    $cur = AiClient::providerOf($pdo, $f);
    $no = AiClient::geminiRefused($f) ?? (!in_array('gemini', AiClient::ROUTES[$f] ?? [], true) ? 'This feature has no Gemini route: it stays on Claude.' : null);
    $g = '<option value="gemini"' . ($cur === 'gemini' ? ' selected' : '') . ($no !== null ? ' disabled' : '') . '>Gemini (free tier, public pages only)' . ($no !== null ? ': not for this one' : '') . '</option>';
    $models = ''; foreach (Gemini::MODELS as $m => $label) $models .= '<option value="' . tv_w_e($m) . '">' . tv_w_e($label) . '</option>';
    return '<label title="' . tv_w_e($no ?? 'Moved to Gemini after the sample comparison (a page read by both, side by side); kept with who and when.') . '">Provider <select class="ak-in" name="provider"' . $dis . '><option value="claude"' . ($cur === 'claude' ? ' selected' : '') . '>Claude</option>' . $g . '</select>'
        . '<small>' . tv_w_e($no ?? 'Gemini: no fallback to Claude when its day is used; the work waits.') . '</small></label>'
        . ($no === null ? '<label>Gemini model <select class="ak-in" name="gemini_model"' . $dis . '>' . $models . '</select></label>' : '');
}

function tv_admin_mod_ai(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/AiClient.php';
    [$can, $why] = Access::can('config.ai.edit', ['min' => 'director', 'depts' => ['eng', 'ops']]);
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $msg = '';
    if ($can && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['ai_feature']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try { AiClient::setFeature($pdo, (string) $_POST['ai_feature'], $_POST, (int) (Auth::currentUserId() ?? 0)); $msg = '<p class="ak-flash" role="status" data-tv-flash="ok">' . tv_w_e((AiClient::FEATURES[$_POST['ai_feature']] ?? AiClient::SWITCHES[$_POST['ai_feature']] ?? ['It'])[0]) . ': saved.</p>'; }
        catch (Throwable $e) { $msg = '<p class="ak-flash bad" role="status" data-tv-flash="error">' . tv_w_e($e->getMessage()) . '</p>'; }
    }
    $month = AiClient::monthUsd($pdo); $cap = AiClient::capUsd(); $on = AiClient::on('claude');
    $h = '<p class="ak-note">What the site spends on AI, feature by feature. Switch a feature off, give it a cheaper model, or hold it to so many runs a day or dollars a month. All of it stops for the month when the overall cap is reached.' . ($can ? '' : ' <b>' . tv_w_e($why) . '</b> to change them.') . '</p>' . $msg;
    $h .= '<p data-tv-status="' . (!$on ? 'bad' : ($month >= $cap ? 'bad' : ($month >= $cap * .8 ? 'info' : 'ok'))) . '" data-tv-label="This month:">$' . number_format($month, 2) . ' of the $' . number_format($cap, 2) . ' cap' . (!$on ? '. Claude isn\'t connected (ANTHROPIC_API_KEY in .env).' : ($month >= $cap ? ': every AI feature is stopped until the 1st.' : '.')) . ' The cap is in the <a class="underline" href="/admin/config/register#svc-anthropic">Service register</a> (it was AI_MONTHLY_CAP_USD in .env).</p><div>';
    foreach (AiClient::SWITCHES as $f => [$name, $what]) {   // switches that spend nothing themselves, each with its lowest confidence score
        $c = AiClient::feature($pdo, $f); $dis = $can ? '' : ' disabled title="' . tv_w_e($why) . '"';
        $body = '<p class="ak-note" style="margin:.2rem 0 .5rem">' . tv_w_e($what) . '</p><form method="post" class="ak-aif"><input type="hidden" name="csrf" value="' . tv_w_e($csrf) . '"><input type="hidden" name="ai_feature" value="' . tv_w_e($f) . '">'
            . '<label><input type="checkbox" name="enabled" value="1"' . ($c['enabled'] ? ' checked' : '') . $dis . '> On</label>'
            . '<label>Lowest score <input class="ak-in" type="number" min="0" max="100" name="min_score" value="' . (int) $c['per_day'] . '"' . $dis . '><small>0 to 100; higher is stricter</small></label><button class="ak-b go"' . $dis . '>Save</button></form>';
        $h .= tv_admin_fold($name, $c['enabled'] ? 'On · at a score of ' . (int) $c['per_day'] . ' or more' : 'Off: a person does it', $c['enabled'] ? 'ok' : '', '', $body, false);
    }
    foreach (AiClient::FEATURES as $f => [$name, $what, , , $defSearch]) {
        $c = AiClient::feature($pdo, $f); $u = AiClient::used($pdo, $f); [$ok, $no] = AiClient::allow($pdo, $f); $nc = AiClient::NOT_CLAUDE[$f] ?? null;
        require_once __DIR__ . '/../lib/Automation.php'; $pz = !$ok && Automation::feature($pdo, $f) !== null;   // paused (System Configuration → Automation): a status, never bad
        $status = ($c['enabled'] ? 'On' : 'Off') . ' · ' . ($nc ? ($f === 'scan_read' ? 'AWS Textract' : 'Tavily') : explode(' ', AiClient::MODELS[$c['model']] ?? $c['model'])[0]) . ' · ' . $u['today'] . ($c['per_day'] ? ' of ' . $c['per_day'] : '') . ' today · $' . number_format($u['usd'], 2) . ($c['month_usd'] > 0 ? ' of $' . number_format($c['month_usd'], 2) : '') . ' this month';
        $dis = $can ? '' : ' disabled title="' . tv_w_e($why) . '"';
        $opts = ''; foreach (AiClient::MODELS as $m => $label) $opts .= '<option value="' . $m . '"' . ($m === $c['model'] ? ' selected' : '') . '>' . tv_w_e($label) . '</option>';
        $body = '<p class="ak-note" style="margin:.2rem 0 .5rem">' . tv_w_e($what) . '</p>' . (!$ok && $c['enabled'] ? '<p data-tv-status="info" data-tv-label="' . ($pz ? 'Paused:' : 'Not running now:') . '">' . tv_w_e($no) . '</p>' : '')
            . '<form method="post" class="ak-aif"><input type="hidden" name="csrf" value="' . tv_w_e($csrf) . '"><input type="hidden" name="ai_feature" value="' . tv_w_e($f) . '">'
            . '<label><input type="checkbox" name="enabled" value="1"' . ($c['enabled'] ? ' checked' : '') . $dis . '> On</label>'
            . ($nc ? '<input type="hidden" name="model" value="' . tv_w_e($c['model']) . '">' : '<label>Model <select class="ak-in" name="model"' . $dis . '>' . $opts . '</select></label>')   // Tavily, Textract: no model (decisions/0157)
            . ($nc ? '' : tv_admin_ai_provider($pdo, $f, $dis))
            . '<label>' . tv_w_e($nc[0] ?? 'Runs a day') . ' <input class="ak-in" type="number" min="0" max="10000" name="per_day" value="' . $c['per_day'] . '"' . $dis . '><small>0 = no daily limit</small></label>'
            . '<label>Monthly budget $ <input class="ak-in" type="number" min="0" step="0.5" name="month_usd" value="' . ($c['month_usd'] > 0 ? $c['month_usd'] : '') . '" placeholder="0"' . $dis . '><small>0 = only the overall cap</small></label>'
            . ($defSearch > 0 ? '<label>' . tv_w_e($nc[1][0] ?? 'Web searches a run') . ' <input class="ak-in" type="number" min="0" max="20" name="searches" value="' . $c['searches'] . '"' . $dis . '><small>' . tv_w_e($nc[1][1] ?? 'each about 1¢') . '</small></label>' : '<input type="hidden" name="searches" value="0">')
            . '<button class="ak-b go"' . $dis . '>Save</button></form>';
        $h .= '<div id="ai-' . tv_w_e($f) . '">' . tv_admin_fold($name, $status . ($pz ? ' · paused' : ''), !$c['enabled'] ? '' : ($ok ? 'ok' : ($pz ? 'info' : 'bad')), '', $body, false) . '</div>';   // #ai-<feature>: an alarm's route opens it here
    }
    // the sample comparison (decisions/0164 §2.3, 0163 §11): before a director moves page_read to Gemini, a sample read by both
    require_once __DIR__ . '/../lib/GeminiCompare.php';
    if ($can && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['ai_compare']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try { $me = Auth::currentUser(); GeminiCompare::run($pdo, (int) (Auth::currentUserId() ?? 0), (string) ($me['display_name'] ?? 'staff')); $h .= '<p class="ak-flash" role="status" data-tv-flash="ok">The comparison ran: its pages are below.</p>'; }
        catch (Throwable $e) { $h .= '<p class="ak-flash bad" role="status" data-tv-flash="error">' . tv_w_e($e->getMessage()) . '</p>'; }
    }
    $cmp = GeminiCompare::last($pdo);
    $cb = '<p class="ak-note">A sample of up to ' . GeminiCompare::PAGES . ' public pages from the source register, cleaned of private details and held names, read by Claude (Haiku, inside the AI cap) and by Gemini\'s free tier, side by side. It decides nothing: moving "Read a public page into facts" to Gemini is its Provider choice above, your call once you\'ve seen this.</p>'
        . '<form method="post"><input type="hidden" name="csrf" value="' . tv_w_e($csrf) . '"><input type="hidden" name="ai_compare" value="1"><button class="ak-b go"' . ($can ? '' : ' disabled title="' . tv_w_e($why) . '"') . ' data-confirm="Read up to ' . GeminiCompare::PAGES . ' public pages with both? Claude\'s side is counted against the AI cap." data-yes="Compare">Compare Claude and Gemini</button></form>';
    if ($cmp) {
        $cb .= '<p class="ak-note">Last run <time datetime="' . tv_w_e((string) $cmp['at']) . '" data-tv-time>' . tv_w_e((string) $cmp['at']) . '</time> by ' . tv_w_e((string) $cmp['by']) . '.</p>';
        foreach ((array) $cmp['pages'] as $p) {
            $list = fn (array $xs) => $xs ? '<ul class="ak-note">' . implode('', array_map(fn ($x) => '<li>' . tv_w_e(implode(' · ', array_filter(array_map('strval', array_intersect_key((array) $x, array_flip(['name', 'street', 'city', 'phone', 'website'])))))) . '</li>', array_slice($xs, 0, 12))) . '</ul>' : '<p class="ak-note">Nothing.</p>';
            $cb .= '<div class="ak-card"><p><b>' . tv_w_e((string) $p['url']) . '</b> · agree ' . (int) $p['agree'] . '%' . ($p['removed'] ? ' · removed first: ' . tv_w_e(implode(', ', array_map(fn ($k, $n) => $n . ' ' . $k, array_keys((array) $p['removed']), (array) $p['removed']))) : '') . '</p>'
                . ($p['why'] !== '' ? '<p data-tv-status="info" data-tv-label="Note:">' . tv_w_e((string) $p['why']) . '</p>' : '')
                . '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr));gap:.6rem"><div><b>Claude</b>' . $list((array) $p['claude']) . '</div><div><b>Gemini</b>' . $list((array) $p['gemini']) . '</div></div></div>';
        }
    }
    $h .= tv_admin_fold('Compare Claude and Gemini (the sample comparison)', $cmp ? 'Last run ' . substr((string) $cmp['at'], 0, 10) : 'Not run yet', '', '', $cb, false);
    $other = [];   // what else spent this month (card scans, brochures, menus), under the overall cap only
    foreach (AiClient::monthBy($pdo) as $prov => $row) foreach ((array) ($row[2] ?? []) as $kind => $n) if (!isset(AiClient::FEATURES[$kind])) $other[] = $kind . ' (' . (int) $n . ')';
    if ($other) $h .= '<p class="ak-note">Also this month, under the overall cap only: ' . tv_w_e(implode(', ', $other)) . '.</p>';
    return $h . '</div><style>.ak-aif{display:flex;flex-wrap:wrap;gap:.6rem 1rem;align-items:flex-end}.ak-aif label{display:grid;gap:.2rem;font-size:.82rem;color:#5C4632}.ak-aif label small{font-size:.7rem;color:#8A7355}.ak-aif .ak-in{max-width:12rem}</style>';
}

/** System Configuration → Vault (decisions/0156): logins only their owner's browser can open; the page is app/ui/vault.php. */
function tv_admin_mod_vault(PDO $pdo): string
{
    require_once __DIR__ . '/../ui/vault.php';
    return tv_vault_page($pdo);
}

/** System Configuration → Settings, in the new admin (decisions/0152): what's about the site itself; the old admin/settings.php
 *  is archived. Outside companies and their keys are in the Service register (decisions/0173). */
function tv_admin_mod_settings(PDO $pdo): string
{
    require_once __DIR__ . '/../ui/outside-services.php';
    return tv_site_settings($pdo);
}

/** System Configuration → Jurisdictions (decisions/0133): the law of each place, layered federal, state or territory, and
 *  tribal nation. Not reviewed, or not opened: the strictest rule applies there. */
function tv_admin_mod_jurisdictions(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/Jurisdictions.php';
    $e = 'tv_w_e';
    if (!Jurisdictions::ready($pdo)) return '<p data-tv-status="info" data-tv-label="Not ready:">The database update for the jurisdiction register hasn\'t run yet: it runs within the hour, or open Database updates.</p>';
    [$can, $why] = Access::can('config.jurisdictions.edit', ['min' => 'director', 'depts' => ['eng', 'ops']]);
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $msg = ''; $uid = (int) (Auth::currentUserId() ?? 0);
    if ($can && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['jur']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $code = (string) ($_POST['code'] ?? '');
        try {
            switch ((string) $_POST['jur']) {
                case 'open': Jurisdictions::open($pdo, $code, (string) ($_POST['level'] ?? 'state'), (string) ($_POST['name'] ?? '')); $done = 'Opened. Add its laws, then review it.'; break;
                case 'law': Jurisdictions::addLaw($pdo, $code, $_POST, $uid); $done = 'Law added. The record goes back to draft until it is reviewed again.'; break;
                case 'unlaw': Jurisdictions::removeLaw($pdo, (int) ($_POST['law'] ?? 0)); $done = 'Law removed. The record goes back to draft.'; break;
                case 'review': Jurisdictions::review($pdo, $code, $uid, (string) ($_POST['attorney'] ?? ''), (string) ($_POST['signed_at'] ?? '')); $done = 'Marked reviewed.'; break;
                default: $done = '';
            }
            if ($done !== '') $msg = '<p class="ak-flash" role="status" data-tv-flash="ok">' . $e($done) . '</p>';
        } catch (Throwable $x) { $msg = '<p class="ak-flash bad" role="status" data-tv-flash="error">' . $e($x->getMessage()) . '</p>'; }
    }
    $dis = $can ? '' : ' disabled title="' . $e($why) . '"';
    $hid = fn (string $do, string $code) => '<input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="jur" value="' . $do . '"><input type="hidden" name="code" value="' . $e($code) . '">';
    $h = '<p class="ak-note">The law of the place decides what we show: the federal baseline everywhere, then each state or territory, then the tribal nation whose land it is (its rules outrank ours there). '
       . 'Where a record is missing or not yet reviewed, the strictest rule applies: the story of a protected place is held back entirely. A state\'s or territory\'s record is reviewed with an attorney\'s sign-off before its region opens: until then its guides don\'t go live and the crawler doesn\'t gather its history (Arizona and New Mexico were open before the register and stay open while theirs are reviewed). Where a record lists a law limiting reading or copying websites, official websites there are kept as pointers unless their rules are set in Sources. Changing a record sends it back to draft.' . ($can ? '' : ' <b>' . $e($why) . '</b>') . '</p>' . $msg;
    $closed = [];
    foreach (Jurisdictions::register($pdo) as [$code, $level, $name, $r]) {
        if (!$r) { $closed[] = [$code, $level, $name]; continue; }
        $j = Jurisdictions::get($pdo, $code);
        $status = ($j['status'] === 'reviewed' ? 'Reviewed ' . date('M j, Y', strtotime((string) $j['reviewed_at'])) . ($j['signed_by'] !== '' ? ' · signed off by ' . $j['signed_by'] : '') : 'Draft: the strictest rule applies') . ' · ' . count($j['laws']) . ' law' . (count($j['laws']) === 1 ? '' : 's');
        if (in_array($level, ['state', 'territory'], true)) $status .= ' · ' . (Jurisdictions::regionOpen($pdo, substr($code, 3)) ? (in_array(substr($code, 3), Jurisdictions::OPENED, true) && $j['status'] !== 'reviewed' ? 'open (before the register; its review is pending)' : 'open') : 'closed until reviewed');
        $body = $j['notes'] ? '<p class="ak-note">' . $e((string) $j['notes']) . '</p>' : '';
        foreach ($j['laws'] as $l) {
            $body .= '<div class="ak-law"><p><b>' . $e($l['name']) . '</b>' . ($l['citation'] !== '' ? ', ' . ($l['url'] !== '' ? '<a class="underline" href="' . $e($l['url']) . '" target="_blank" rel="noopener">' . $e($l['citation']) . '</a>' : $e($l['citation'])) : '') . '</p>'
                . '<p class="ak-note">' . $e(Jurisdictions::EFFECTS[$l['effect']] ?? $l['effect']) . ($l['protects'] !== '' ? '. Protects: ' . $e($l['protects']) : '') . ($l['requires_us'] !== '' ? ' Asks of us: ' . $e($l['requires_us']) : '') . '</p>'
                . '<form method="post">' . $hid('unlaw', $code) . '<input type="hidden" name="law" value="' . (int) $l['id'] . '"><button class="ak-b no"' . $dis . ' data-confirm="Remove this law from the record?" data-yes="Remove" data-danger>Remove</button></form></div>';
        }
        $eff = ''; foreach (Jurisdictions::EFFECTS as $k => $v) $eff .= '<option value="' . $k . '">' . $e($v) . '</option>';
        $body .= tv_admin_fold('Add a law', '', '', '', '<form method="post" class="ak-jf">' . $hid('law', $code)
            . '<label>Name<input class="ak-in" name="name" required></label><label>Citation<input class="ak-in" name="citation" placeholder="A.R.S. 41-844"></label>'
            . '<label>What it does to what we show<select class="ak-in" name="effect">' . $eff . '</select></label><label>Link<input class="ak-in" name="url" placeholder="https://"></label>'
            . '<label class="w">What it protects<input class="ak-in" name="protects"></label><label class="w">What it asks of us<input class="ak-in" name="requires_us"></label>'
            . '<button class="ak-b go"' . $dis . '>Add</button></form>', false);
        if ($j['status'] !== 'reviewed') $body .= '<form method="post" class="ak-jf">' . $hid('review', $code)
            . ($level !== 'nation' ? '<label>Attorney who signed off<input class="ak-in" name="attorney" value="' . $e((string) $j['signed_by']) . '"></label><label>Signed on<input class="ak-in" type="date" name="signed_at"></label>' : '')
            . '<button class="ak-b go"' . $dis . ' data-confirm="Mark ' . $e($name) . '\'s record reviewed? Its rules then apply as written." data-yes="Mark reviewed">Mark reviewed</button></form>';
        $h .= tv_admin_fold($name, $status, $j['status'] === 'reviewed' ? 'ok' : 'bad', '', $body, false);
    }
    $list = '';
    foreach ($closed as [$code, $level, $name]) $list .= '<form method="post" class="ak-jo">' . $hid('open', $code) . '<input type="hidden" name="level" value="' . $e($level) . '"><input type="hidden" name="name" value="' . $e($name) . '"><span>' . $e($name) . '</span><button class="ak-b"' . $dis . '>Open</button></form>';
    if ($list !== '') $h .= tv_admin_fold('Not opened yet', count($closed) . ' states, territories and nations: the strictest rule applies there', '', '', '<div class="ak-jl">' . $list . '</div>', false);
    return '<div>' . $h . '</div><style>.ak-law{border-top:1px solid rgba(203,185,141,.5);padding:.5rem 0}.ak-law p{margin:.1rem 0}.ak-law form{margin-top:.3rem}'
        . '.ak-jf{display:grid;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));gap:.5rem .8rem;align-items:end;margin:.5rem 0}.ak-jf label{display:grid;gap:.15rem;font-size:.8rem;color:#5C4632}.ak-jf .ak-in{width:100%}.ak-jf .w{grid-column:1/-1}'
        . '.ak-jl{display:grid;grid-template-columns:repeat(auto-fill,minmax(14rem,1fr));gap:.3rem .8rem}.ak-jo{display:flex;justify-content:space-between;align-items:center;gap:.5rem;font-size:.85rem}</style>';
}

/** Insights → The Seeker (decisions/0133 Phase 4): the heritage catalog by words, place and kind. */
function tv_admin_mod_seeker(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/Heritage.php';
    require_once __DIR__ . '/../lib/Guides.php';
    $e = 'tv_w_e';
    if (!Heritage::ready($pdo)) return '<p data-tv-status="info" data-tv-label="Not ready:">The database update for the heritage catalog hasn\'t run yet: it runs within the hour, or open Database updates.</p>';
    $q = trim((string) ($_GET['q'] ?? '')); $place = (string) ($_GET['place'] ?? ''); $kind = (string) ($_GET['kind'] ?? '');
    [$can, $why] = Access::can('insights.seeker.story', ['min' => 'manager', 'depts' => ['con', 'ops']]);
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $msg = '';
    if ($can && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['story_of']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try { $to = Heritage::storyOnly($pdo, (int) (Auth::currentUserId() ?? 0), (int) $_POST['story_of'], (string) ($_POST['evidence'] ?? ''), (string) ($_POST['story'] ?? ''));
              $msg = '<p class="ak-flash" role="status" data-tv-flash="ok">' . $e('Its story is in the catalog for ' . (Guides::place($pdo, $to)['name'] ?? $to) . ', told without its place. The landmark stays held, with no page and no pin.') . '</p>'; }
        catch (Throwable $x) { $msg = '<p class="ak-flash bad" role="status" data-tv-flash="error">' . $e($x->getMessage()) . '</p>'; }
    }
    [$n, $vet, $places, $loc] = Heritage::counts($pdo);
    $h = '<p class="ak-note">Search the heritage catalog: the facts drawn from each place\'s sources (the Library of Congress\'s records, the crawler\'s dossier, the research brief) about turning points, people, trades, materials, water, land, hardships and lore, each with where it can be stood on today. '
       . '<b>Vetted</b> facts (a primary record or two independent sources) are what guides write from; the rest wait for another source. A protected place is <b>story only</b> (never the way in) or <b>restricted</b> (kept, never shown) under the jurisdiction register.</p>'
       . '<p data-tv-status="info" data-tv-label="The catalog:">' . number_format($n) . ' facts, ' . number_format($vet) . ' vetted, across ' . $places . ' place' . ($places === 1 ? '' : 's') . ', from ' . number_format($loc) . ' Library of Congress records.</p>';
    $po = '<option value="">Every place</option>';
    foreach ($pdo->query('SELECT DISTINCT place_key FROM heritage_facts ORDER BY place_key')->fetchAll(PDO::FETCH_COLUMN) as $k) { $p = Guides::place($pdo, (string) $k); $po .= '<option value="' . $e($k) . '"' . ($k === $place ? ' selected' : '') . '>' . $e($p['name'] ?? $k) . '</option>'; }
    $ko = '<option value="">Every kind</option>'; foreach (Heritage::KINDS as $k => $v) $ko .= '<option value="' . $k . '"' . ($k === $kind ? ' selected' : '') . '>' . $e($v) . '</option>';
    $held = $pdo->query("SELECT gnis_id, name, near_town, state FROM landmarks WHERE status = 'waiting' AND nation = '' ORDER BY name LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
    if ($held) {
        $ho = ''; foreach ($held as $l) $ho .= '<option value="' . (int) $l['gnis_id'] . '">' . $e($l['name'] . ($l['near_town'] !== '' ? ', near ' . $l['near_town'] : '') . ', ' . $l['state']) . '</option>';
        $dis = $can ? '' : ' disabled title="' . $e($why) . '"';
        $h .= tv_admin_fold('Tell a held place\'s story', count($held) . ' held places not on a nation\'s land', '', '', '<p class="ak-note">When a public source presents a sacred, burial or archaeological place to visitors (a state park\'s rock-art tour, a museum), its story may be told, never its place: it goes in the catalog as story only, with the courtesy line, and the landmark stays held with no page and no pin. Only where the law of the place is reviewed.</p>'
            . '<form method="post" class="ak-jf"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><label>Held place<select class="ak-in" name="story_of">' . $ho . '</select></label>'
            . '<label>The public source that presents it<input class="ak-in" name="evidence" placeholder="https://"></label><label class="w">Its story, in our words, with no directions<input class="ak-in" name="story" maxlength="1200"></label>'
            . '<button class="ak-b go"' . $dis . ' data-confirm="Tell its story, never its place?" data-yes="Tell its story">Tell its story</button></form>', false);
    }
    $h .= $msg . '<form method="get" class="ak-sk"><input class="ak-in" name="q" value="' . $e($q) . '" placeholder="water stone masonry" aria-label="Words"><select class="ak-in" name="place" aria-label="Place">' . $po . '</select><select class="ak-in" name="kind" aria-label="Kind">' . $ko . '</select><button class="ak-b go">Seek</button></form>';
    $rows = Heritage::seek($pdo, $q, $place, $kind);
    if (!$rows) return '<div>' . $h . '<p class="ak-note">' . ($n ? 'Nothing matches.' : 'Nothing cataloged yet: the heritage job reads each guide\'s place in turn.') . '</p></div><style>.ak-jf{display:grid;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));gap:.5rem .8rem;align-items:end;margin:.5rem 0}.ak-jf label{display:grid;gap:.15rem;font-size:.8rem;color:#5C4632}.ak-jf .ak-in{width:100%}.ak-jf .w{grid-column:1/-1}.ak-sk{display:flex;flex-wrap:wrap;gap:.4rem;margin:.6rem 0}.ak-sk .ak-in{flex:1 1 10rem;min-width:0}</style>';
    $h .= '<div class="ak-skl">';
    foreach ($rows as $f) {
        $p = Guides::place($pdo, (string) $f['place_key']);
        $src = ''; foreach ((array) (json_decode((string) $f['sources'], true) ?: []) as $s) if ($s['url'] !== '') $src .= ' <a class="underline" href="' . $e($s['url']) . '" target="_blank" rel="noopener nofollow">' . $e($s['host'] === 'loc.gov' ? 'Library of Congress' : (string) parse_url((string) $s['url'], PHP_URL_HOST)) . '</a>'; else $src .= ' ' . $e($s['host']);
        $yr = $f['year_from'] ? ' · ' . $f['year_from'] . ($f['year_to'] && $f['year_to'] !== $f['year_from'] ? '-' . $f['year_to'] : '') : '';
        $h .= '<div class="hb-card tv-bleed ak-skf"><p class="ak-skm">' . $e(Heritage::KINDS[$f['kind']] ?? $f['kind']) . ' · ' . $e($p['name'] ?? $f['place_key']) . $yr . '</p><p><b>' . $e($f['title']) . '</b></p><p>' . $e($f['fact']) . '</p>'
            . ($f['footprint'] !== '' && $f['tier'] === 'public' ? '<p class="ak-note">Stand here today: ' . $e($f['footprint']) . '</p>' : '')
            . '<p class="ak-note">' . ($f['status'] === 'cataloged' ? '<b>Vetted</b>' : 'Waiting for another source') . ' · ' . $e(Heritage::TIERS[$f['tier']] ?? $f['tier']) . ' · from:' . $src . '</p></div>';
    }
    return '<div>' . $h . '</div></div><style>.ak-sk{display:flex;flex-wrap:wrap;gap:.4rem;margin:.6rem 0}.ak-sk .ak-in{flex:1 1 10rem;min-width:0}.ak-skl{display:grid;gap:3px}.ak-skf p{margin:.15rem 0}.ak-skm{font-size:.75rem;color:#8A7355;text-transform:uppercase;letter-spacing:.04em}</style>';
}

/** Insights → Topic index (decisions/0133): our encyclopedia of the state of knowledge, and AI assist written from it. */
function tv_admin_mod_topics(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/Topics.php';
    require_once __DIR__ . '/../lib/Guides.php';
    $e = 'tv_w_e';
    if (!Topics::ready($pdo)) return '<p data-tv-status="info" data-tv-label="Not ready:">The database update for the Topic index hasn\'t run yet: it runs within the hour, or open Database updates.</p>';
    [$can, $why] = Access::can('insights.topics.assist', ['min' => 'staff', 'depts' => ['ops', 'con', 'eng']]);
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $out = '';
    $open = (string) ($_GET['topic'] ?? '');
    if ($can && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['assist']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $open = (string) $_POST['assist'];
        try {
            $r = Topics::assist($pdo, $open, (string) ($_POST['ask'] ?? ''));
            $out = ($r['thin'] ? '<p data-tv-status="info" data-tv-label="Thin topic:">There isn\'t much vetted yet, so this says only what we know.' . ($r['queued'] ? ' It\'s queued for research, the most asked-for first.' : ' It\'s already queued for research.') . '</p>' : '')
                 . '<div class="ak-ta">' . nl2br($e($r['text'])) . '</div><p class="ak-note">Written from ' . count($r['used']) . ' vetted fact' . (count($r['used']) === 1 ? '' : 's') . ' in the index' . ($r['used'] ? ' (' . implode(', ', array_map(fn ($i) => '#' . $i, $r['used'])) . ')' : '') . '.</p>';
        } catch (Throwable $x) { $out = '<p class="ak-flash bad" role="status" data-tv-flash="error">' . $e($x->getMessage()) . '</p>'; }
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    $rows = Topics::list($pdo, $q);
    $h = '<p class="ak-note">Every topic the research touches, with what we know about it: its vetted facts and their sources, related topics, and its state of knowledge (how well covered, how fresh, what isn\'t confirmed yet). '
       . 'The system writes each summary from the vetted facts and keeps it current. AI assist writes from a topic\'s record, not from memory or a fresh web search; a thin topic is queued for research, the most asked-for first. Restricted facts never leave the catalog; story-only ones are told without their place.' . ($can ? '' : ' <b>' . $e($why) . '</b>') . '</p>'
       . '<form method="get" class="ak-sk"><input class="ak-in" name="q" value="' . $e($q) . '" placeholder="Find a topic" aria-label="Find a topic"><button class="ak-b go">Find</button></form>';
    if (!$rows) return '<div>' . $h . '<p class="ak-note">' . ($q !== '' ? 'No topic by that name.' : 'No topics yet: they come with the heritage catalog, as each place is read.') . '</p></div>';
    foreach ($rows as $row) {
        $isOpen = $open !== '' && ($open === (string) $row['slug'] || $open === (string) $row['id']);
        $status = (Topics::KINDS[$row['kind']] ?? $row['kind']) . ' · ' . (int) $row['vetted'] . ' vetted' . ((int) $row['asks'] ? ' · asked ' . (int) $row['asks'] . ' times' : '') . ($row['queued_at'] ? ' · queued for research' : '');
        $body = '';
        if ($isOpen || (int) $row['vetted'] > 0 || $row['summary']) {
            $t = Topics::record($pdo, (string) $row['id']);
            $st = $t['state'];
            $body .= ($t['summary'] ? '<p>' . $e((string) $t['summary']) . '</p>' : '<p class="ak-note">No summary yet: it\'s written once two facts are vetted.</p>')
                . '<p data-tv-status="' . ($st['vetted'] >= Topics::THIN ? 'ok' : 'info') . '" data-tv-label="State of knowledge:">' . $e(ucfirst($st['coverage'])) . ': ' . $st['vetted'] . ' vetted fact' . ($st['vetted'] === 1 ? '' : 's') . ' from ' . $st['sources'] . ' source' . ($st['sources'] === 1 ? '' : 's')
                . ($st['fresh'] !== '' ? ', newest ' . $e($st['fresh']) : '') . ($st['research_at'] !== '' ? '; researched ' . $e($st['research_at']) : '') . ($st['open'] ? '. Not confirmed yet: ' . $e(implode('; ', $st['open'])) : '') . '.</p>';
            if (count($t['names']) > 1) $body .= '<p class="ak-note">Also called: ' . $e(implode(', ', array_diff($t['names'], [$t['name']]))) . '</p>';
            if ($t['places']) $body .= '<p class="ak-note">Places: ' . $e(implode(', ', array_map(fn ($k) => (string) (Guides::place($pdo, (string) $k)['name'] ?? $k), $t['places']))) . '</p>';
            foreach ($t['facts'] as $f) $body .= '<p class="ak-tf"><b>' . $e($f['title']) . '</b>' . ($f['year_from'] ? ' (' . (int) $f['year_from'] . ')' : '') . ': ' . $e($f['fact']) . ($f['tier'] === 'story' ? ' <small>story only</small>' : '') . '</p>';
            if ($t['related']) $body .= '<p class="ak-note">Related: ' . implode(', ', array_map(fn ($r) => '<a class="underline" href="?topic=' . $e((string) $r['slug']) . '">' . $e((string) $r['name']) . '</a>', $t['related'])) . '</p>';
        }
        $body .= ($isOpen ? $out : '') . '<form method="post" class="ak-sk"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="assist" value="' . $e((string) $row['slug']) . '">'
            . '<input class="ak-in" name="ask" required placeholder="Write a paragraph for a guide about this" aria-label="What should AI assist write?"><button class="ak-b go"' . ($can ? '' : ' disabled title="' . $e($why) . '"') . '>AI assist</button></form>';
        $h .= tv_admin_fold((string) $row['name'], $status, (int) $row['vetted'] >= Topics::THIN ? 'ok' : '', '', $body, $isOpen);
    }
    return '<div>' . $h . '</div><style>.ak-sk{display:flex;flex-wrap:wrap;gap:.4rem;margin:.6rem 0}.ak-sk .ak-in{flex:1 1 12rem;min-width:0}.ak-tf{margin:.2rem 0;font-size:.9rem}.ak-tf small{color:#8A7355}.ak-ta{background:#FFFBF2;border:1px solid #EADFC4;border-radius:.6rem;padding:.7rem .8rem;margin:.4rem 0;line-height:1.55}</style>';
}

function tv_admin_mod_scheduler(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/Scheduler.php';
    require_once __DIR__ . '/job-line.php';   // each task's page, and the line its page shows (Jason, 2026-11-17)
    [$can, $why] = Access::can('config.scheduler.edit', ['min' => 'director', 'depts' => ['eng', 'ops']]);
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $msg = '';
    if ($can && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['sched']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $job = (string) ($_POST['job'] ?? ''); $preset = (string) ($_POST['preset'] ?? '');
        try {
            match ((string) $_POST['sched']) { 'add' => Scheduler::add($job, $preset), 'change' => Scheduler::change($job, $preset), 'remove' => Scheduler::remove($job), default => null };
            $msg = '<p class="ak-flash" role="status">' . tv_w_e(Scheduler::JOBS[$job][0] ?? 'The job') . ': ' . ['add' => 'set up in cPanel.', 'change' => 'its time is changed.', 'remove' => 'removed from cPanel.'][$_POST['sched']] . '</p>';
        } catch (Throwable $e) { $msg = '<p class="ak-flash bad" role="status">' . tv_w_e($e->getMessage()) . '</p>'; }
    }
    $lines = null; $cpErr = '';
    if (!CPanel::ready()) $cpErr = 'cPanel isn\'t connected: add CPANEL_HOST, CPANEL_USER and CPANEL_TOKEN to .env (the same token makes staff mailboxes). Until then, set the jobs in cPanel → Cron Jobs with the lines below.';
    else { try {
        $new = Scheduler::sync($pdo, true);   // new jobs set themselves up through the API: nothing to do by hand
        if ($new && $msg === '') $msg = '<p class="ak-flash" role="status">Set up in cPanel just now: ' . tv_w_e(implode(', ', array_map(fn ($j) => Scheduler::JOBS[$j][0], $new))) . '.</p>';
        $lines = Scheduler::cronLines();
        tv_job_remember($lines);   // the pages' "Runs:" lines read this, so they never wait on cPanel
    } catch (Throwable $e) { $cpErr = $e->getMessage(); } }
    $runs = Scheduler::lastRuns($pdo);
    $ago = function (?string $t): string {
        if (!$t) return 'never'; $s = max(0, time() - strtotime($t));
        return $s < 90 ? 'just now' : ($s < 5400 ? round($s / 60) . ' minutes ago' : ($s < 172800 ? round($s / 3600) . ' hours ago' : round($s / 86400) . ' days ago'));
    };
    $sel = function (string $cur): string { $h = '<select class="ak-in" name="preset" aria-label="When it runs">'; foreach (Scheduler::PRESETS as $k => $p) $h .= '<option value="' . $k . '"' . ($k === $cur ? ' selected' : '') . '>' . tv_w_e($p[0]) . '</option>'; return $h . '</select>'; };
    $form = fn (string $do, string $job, string $inner, string $label, string $ask = '', bool $danger = false) => '<form method="post" class="ak-schf"><input type="hidden" name="csrf" value="' . tv_w_e($csrf) . '"><input type="hidden" name="sched" value="' . $do . '"><input type="hidden" name="job" value="' . tv_w_e($job) . '">'
        . $inner . '<button class="ak-b' . ($danger ? ' no' : ' go') . '"' . ($ask !== '' ? ' data-confirm="' . tv_w_e($ask) . '" data-yes="' . tv_w_e($label) . '"' . ($danger ? ' data-danger' : '') : '') . ($can ? '' : ' disabled title="' . tv_w_e($why) . '"') . '>' . tv_w_e($label) . '</button></form>';

    $h = '<p class="ak-note">The jobs that keep the site running on time. Each is a cron job in cPanel, set from here through cPanel\'s API, on the server\'s clock. Each records its own runs, so you can see when it last ran and whether it worked.' . ($can ? '' : ' <b>' . tv_w_e($why) . '</b> to change them.') . '</p>' . $msg;
    if ($cpErr !== '') $h .= '<p class="ak-flash bad" role="status">' . tv_w_e($cpErr) . '</p>';
    $h .= '<div>';
    foreach (Scheduler::JOBS as $job => [$name, $what, $preset, $lateMin]) {
        $l = $lines !== null ? Scheduler::lineFor($lines, $job) : null;
        $r = $runs[$job] ?? null;
        $late = $r && strtotime((string) $r['started_at']) < time() - $lateMin * 60 && !in_array($job, array_merge(Scheduler::QUIET, Scheduler::RETIRED), true);   // a quiet job's silence isn't lateness
        $failed = $r && $r['ok'] !== null && !(int) $r['ok'];
        $when = $l ? Scheduler::words($l) : ($lines !== null ? 'Not set up in cPanel' : 'cPanel not reached');
        $status = $when . ' · last ran ' . $ago($r['started_at'] ?? null) . ($failed ? ', and stopped' : '');
        $tone = $failed || ($lines !== null && !$l) ? 'bad' : ($late ? 'bad' : ($r ? 'ok' : ''));
        $acts = '';
        if ($lines !== null) $acts = $l ? $form('change', $job, $sel(Scheduler::presetOf($l)), 'Change') . $form('remove', $job, '', 'Remove', 'Stop ' . $name . ' running on its own?', true)
                                        : $form('add', $job, $sel($preset), 'Set up');
        $acts = tv_admin_job_open($job) . $acts;   // "Open <page>": the page this task serves (none for one without a page)
        $cmd = $l['command'] ?? Scheduler::command($job, $lines ?? []);
        $p = Scheduler::PRESETS[$l ? (Scheduler::presetOf($l) ?: $preset) : $preset];
        $body = '<p class="ak-note" style="margin:.2rem 0 .5rem">' . tv_w_e($what) . '</p>'
            . '<div class="ak-res"><b class="' . ($r ? ($failed ? 'bad' : 'ok') : '') . '">' . ($r ? ($failed ? '✗' : '✓') : '–') . '</b><span>Last run: ' . tv_w_e($r ? date('M j, g:i a', strtotime((string) $r['started_at'])) : 'none recorded yet')
            . '<small>' . tv_w_e($r ? ($r['finished_at'] ? ($r['note'] !== '' ? $r['note'] : 'Finished.') : 'Still running, or it stopped without saying.') . ((int) ($r['fails_today'] ?? 0) ? ' · ' . (int) $r['fails_today'] . ' stopped in the last day' : '') : 'It shows here after its first run.') . '</small></span></div>'
            . ($late ? '<div class="ak-res"><b class="bad">!</b><span>Late<small>It should have run since. Check that the cron job is set up, and its line.</small></span></div>' : '')
            . '<div class="ak-res"><b>⌘</b><span>' . ($l ? 'The line in cPanel' : 'The line for cPanel → Cron Jobs') . '<small><code class="ak-code">' . tv_w_e(($l ? implode(' ', [$l['minute'], $l['hour'], $l['day'], $l['month'], $l['weekday']]) : implode(' ', array_slice($p, 1))) . ' ' . $cmd) . '</code></small></span></div>';
        $h .= '<div id="job-' . tv_w_e($job) . '">' . tv_admin_fold($name, $status, $tone, $acts, $body, $tone === 'bad') . '</div>';   // #job-<key>: the admin search opens it here
    }
    $h .= '<script>(function(){var t=location.hash&&document.getElementById(location.hash.slice(1));if(!t)return;var b=t.querySelector(\'.ak-foldt[aria-expanded="false"]\');if(b)b.click();t.style.boxShadow="0 0 0 3px #8FBCA8";t.style.borderRadius=".5rem";setTimeout(function(){t.scrollIntoView({block:"start"});},50);})();</script>';
    if ($lines) {
        $other = array_filter($lines, fn ($l) => !array_filter(array_keys(Scheduler::JOBS), fn ($j) => str_contains($l['command'], 'app/scripts/' . $j . '.php')));
        if ($other) {
            $body = '';
            foreach ($other as $l) $body .= '<div class="ak-res"><b>⌘</b><span>' . tv_w_e(Scheduler::words($l)) . '<small><code class="ak-code">' . tv_w_e($l['command']) . '</code></small></span></div>';
            $h .= tv_admin_fold('Other cron jobs in cPanel', count($other) . ' set up outside this page (shown, not changed here)', '', '', $body, false);
        }
    }
    return $h . '</div>';
}

/** Back-Office → Plans: which business tools each plan opens (business-portal-architecture.md §7.4). A tool its plan
 *  doesn't include shows greyed in the business portal with "Included in …: see plans". Prices stay in Billing for now. */
/**
 * Market 360° → Marketplace rules (decisions/0135): the categories and the fields each asks for, what can't be sold, the
 * words that hold a post for a person to look first, the cap on a member's posts, how long posts are offered for and the
 * "Still available?" check. Changed here by a manager in Safety or Content; read by Market::setting().
 */
function tv_admin_mod_market_rules(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/Market.php';
    if (!Market::ready($pdo)) return '<div class="ak-card tv-bleed"><p data-tv-status="info" data-tv-label="Coming:">The Marketplace\'s database update (app/migrations/2027-01-12_marketplace.sql) runs by itself within the hour. Its rules show here then.</p></div>';
    [$can, $why] = Access::can('market/rules.edit', ['min' => 'manager', 'depts' => ['ops', 'con']]);
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $msg = '';
    if ($can && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['mkr']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try {
            $n = Market::saveSettings($pdo, (array) $_POST['mkr'], (int) (Auth::currentUser()['id'] ?? 0));
            $msg = '<p data-tv-flash="ok">' . ($n ? $n . ' changed. Posts follow them from now on.' : 'Nothing changed.') . '</p>';
        } catch (InvalidArgumentException $x) { $msg = '<p data-tv-flash="error">' . tv_w_e($x->getMessage()) . '</p>'; }
    }
    $h = '<div class="ak-card tv-bleed"><h2 class="ak-h" style="margin-top:0">Marketplace rules</h2><p class="ak-note">What people can post, and how long it stays up. A member has '
        . (int) Market::setting($pdo, 'person_cap') . ' posts out at a time (commercial.md §1.1). Businesses\' catalogs follow their plan\'s cap when they join in step 3.</p>' . $msg
        . '<form method="post" class="mkr"><input type="hidden" name="csrf" value="' . tv_w_e($csrf) . '">';
    $nums = ''; $lists = '';
    foreach (Market::SETTINGS as $k => [$label, $def, $kind, $help]) {
        $v = (string) Market::setting($pdo, $k);
        $dis = $can ? '' : ' disabled title="' . tv_w_e($why) . '"';
        if ($kind === 'number') $nums .= '<label>' . tv_w_e($label) . '<input class="ak-in" type="number" min="1" max="365" name="mkr[' . $k . ']" value="' . tv_w_e($v) . '"' . $dis . '><small>' . tv_w_e($help) . '</small></label>';
        else $lists .= '<label>' . tv_w_e($label) . '<textarea class="ak-in" rows="' . ($k === 'categories' ? 12 : 8) . '" name="mkr[' . $k . ']"' . $dis . '>' . tv_w_e($v) . '</textarea><small>' . tv_w_e($help) . '</small></label>';
    }
    $h .= '<div class="mkr-g">' . $nums . '</div><div class="mkr-g l">' . $lists . '</div>'
        . '<div class="ak-acts" style="margin-top:.6rem"><button class="ak-b go"' . ($can ? '' : ' disabled title="' . tv_w_e($why) . '"') . '>Save</button>' . ($can ? '' : '<span class="ak-why">' . tv_w_e($why) . '</span>') . '</div></form></div>'
        . '<style>.mkr-g{display:grid;gap:.7rem;grid-template-columns:repeat(auto-fill,minmax(15rem,1fr));margin-top:.6rem}.mkr-g.l{grid-template-columns:repeat(auto-fill,minmax(18rem,1fr))}'
        . '.mkr label{display:grid;gap:.25rem;font-size:.84rem;font-weight:700;color:#5C4632}.mkr small{font-weight:400;color:#8A7355;font-size:.76rem}.mkr textarea{font-family:inherit;font-size:.86rem}</style>';
    return $h;
}

function tv_admin_mod_planfeatures(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/Plan.php';
    [$can, $why] = Access::can('backoffice/plans.features', ['min' => 'director', 'depts' => ['fin']]);
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $msg = '';
    if ($can && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['pf']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $uid = (int) (Auth::currentUser()['id'] ?? 0); $n = 0;
        $now = Plan::starts($pdo);
        foreach ((array) $_POST['pf'] as $f => $t) if (isset(Plan::FEATURES[$f]) && ($now[$f] ?? '') !== $t) { Plan::setStart($pdo, (string) $f, (string) $t, $uid); $n++; }
        $msg = '<p class="ak-flash" role="status">' . ($n ? $n . ' changed. Businesses see it on their next page.' : 'Nothing changed.') . '</p>';
    }
    $starts = Plan::starts($pdo);
    $h = '<div class="ak-card tv-bleed"><h2 class="ak-h" style="margin-top:0">Plan features</h2><p class="ak-note">Which tools each plan opens in the business portal. A plan includes everything from the plan it starts at, up. Prices and allowances are set in code for now (Billing).</p>' . $msg
        . '<form method="post"><input type="hidden" name="csrf" value="' . tv_w_e($csrf) . '"><table class="bz-pf">';
    foreach (Plan::FEATURES as $f => [$label, $default]) {
        $h .= '<tr><td>' . tv_w_e($label) . ($starts[$f] !== $default ? ' <small>(was ' . tv_w_e(Plan::TIERS[$default]) . ')</small>' : '') . '</td><td><select class="ak-in" name="pf[' . tv_w_e($f) . ']"' . ($can ? '' : ' disabled') . ' aria-label="' . tv_w_e($label) . ': starts at">';
        foreach (Plan::TIERS + ['off' => 'Not offered'] as $t => $tl) $h .= '<option value="' . $t . '"' . ($starts[$f] === $t ? ' selected' : '') . '>' . tv_w_e($t === 'off' ? $tl : 'From ' . $tl) . '</option>';
        $h .= '</select></td></tr>';
    }
    $h .= '</table><div class="ak-acts" style="margin-top:.6rem"><button class="ak-b go"' . ($can ? '' : ' disabled title="' . tv_w_e($why) . '"') . '>Save</button>' . ($can ? '' : '<span class="ak-why">' . tv_w_e($why) . '</span>') . '</div></form></div>'
        . '<style>.bz-pf{width:100%;border-collapse:collapse;font-size:.9rem}.bz-pf td{padding:.35rem .3rem;border-top:1px solid #EADFC4;vertical-align:middle}.bz-pf td:last-child{width:13rem}.bz-pf small{color:#8A7355}'
        . '@media (max-width:767px){.bz-pf tr{display:grid;gap:.25rem;padding:.35rem 0;border-top:1px solid #EADFC4}.bz-pf td{border:0;padding:0}.bz-pf td:last-child{width:auto}}</style>';
    return $h;
}

/**
 * Back-Office → Vendors (decisions/0101, addendum 2026-10-28): the vendors we work with, each with where their orders go,
 * a contact, our account number with them and notes. Added and edited here (or a new one typed on a work order); an
 * item's Vendor tab picks one, and its work order goes to that email.
 */
function tv_admin_mod_vendor_list(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/Vendors.php'; require_once __DIR__ . '/../lib/WorkOrders.php';
    if (!Vendors::ready($pdo)) return '<div class="ak-card tv-bleed"><p>Run <code>app/migrations/2027-01-01_work_orders.sql</code> in phpMyAdmin first.</p></div>';
    $e = 'tv_w_e'; $csrf = (string) ($_SESSION['csrf_token'] ?? '');
    $form = function (array $v) use ($e): string {
        $f = fn ($k, $lab, $type = 'text', $ph = '') => '<label>' . $lab . '<input class="ak-in" name="' . $k . '" type="' . $type . '" value="' . $e((string) ($v[$k] ?? '')) . '"' . ($ph !== '' ? ' placeholder="' . $e($ph) . '"' : '') . '></label>';
        return '<form class="vd-f" data-vd="' . (int) ($v['id'] ?? 0) . '" novalidate>'
            . $f('name', 'Name', 'text', '4OVER') . (Vendors::hasCode(Database::connection()) ? '<label>Work order code (1-4 letters: ' . date('Ymd') . '-<b>' . $e(Vendors::codeFor($v ?: null)) . '</b>0001)<input class="ak-in" name="code" maxlength="4" value="' . $e((string) ($v['code'] ?? '')) . '" placeholder="' . $e(!empty($v['name']) ? Vendors::codeFor(['name' => $v['name']]) : 'CP') . '" style="text-transform:uppercase"></label>' : '') . $f('email', 'Orders email (work orders go here)', 'email', 'orders@vendor.com')
            . $f('contact', 'Contact person') . $f('phone', 'Phone', 'tel') . $f('website', 'Website', 'text', 'vendor.com') . $f('account_no', 'Our account number with them')
            . '<label class="vd-wide">Notes (turnaround, how they ship, who to call)<textarea class="ak-in" name="notes" rows="2">' . $e((string) ($v['notes'] ?? '')) . '</textarea></label>'
            . (Vendors::hasPref(Database::connection()) ? '<label>Files they take<select class="ak-in" name="file_pref">' . implode('', array_map(fn ($k, $lab) => '<option value="' . $k . '"' . ((string) ($v['file_pref'] ?? '') === $k ? ' selected' : '') . '>' . $e($lab) . '</option>', array_keys(WorkOrders::FILE_PREF), WorkOrders::FILE_PREF)) . '</select></label>' : '')
            . (!empty($v['id']) ? '<label class="vd-chk"><input type="checkbox" name="active" value="1"' . (!isset($v['active']) || (int) $v['active'] ? ' checked' : '') . '> On the list (untick to retire it; its past work orders stay)</label>' : '')
            . '<div class="vd-wide"><button class="ak-b go">' . (!empty($v['id']) ? 'Save' : 'Add the vendor') . '</button></div></form>';
    };
    $h = '<style>.vd-f{display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:.5rem;margin:.5rem 0}.vd-f label{display:grid;gap:.15rem;font-size:.86rem;color:#5C4632}.vd-wide{grid-column:1/-1}'
        . '.vd-chk{display:flex!important;gap:.4rem;align-items:center;grid-column:1/-1}.vd-chk input{width:auto}.vd-row{border-top:1px solid #EADFC4;padding:.55rem 0}.vd-row>summary{cursor:pointer;display:flex;flex-wrap:wrap;gap:.2rem .8rem;align-items:baseline}'
        . '.vd-row>summary b{color:#92400E;text-decoration:underline}.vd-row small{color:#8A7355}.vd-off{opacity:.6}</style>';
    // their own link to all their work orders (decisions/0150): 30 days; sending a new one ends the old
    $linkOn = Vendors::linkReady($pdo);
    $link = function (array $v) use ($e, $pdo, $linkOn): string {
        if (!$linkOn || !(int) $v['active']) return '';
        $ends = Vendors::linkEnds($pdo, $v); $live = $ends !== null && strtotime($ends) > time();
        $what = $live ? 'Their link to all their work orders works until ' . date('F j', strtotime($ends)) . '.' : 'Their link to all their work orders ' . ($ends ? 'expired ' . date('F j', strtotime($ends)) . '.' : 'hasn\'t been sent yet (it goes with their next work order).');
        $why = $v['email'] ? '' : 'Add their orders email first.';
        return '<p class="vd-link"><span>' . $e($what) . '</span> <button type="button" class="ak-b" data-vd-link="' . (int) $v['id'] . '"' . ($why !== '' ? ' disabled title="' . $e($why) . '"' : '')
            . ' data-ask="Email ' . $e($v['name']) . ' a new link?' . ($live ? ' The one they have stops working.' : '') . '">' . ($live ? 'Email a new link' : 'Email their link') . '</button></p>';
    };
    $h .= '<style>.vd-link{display:flex;flex-wrap:wrap;gap:.3rem .6rem;align-items:center;margin:.4rem 0 0;font-size:.88rem;color:#5C4632}</style>';
    $h .= '<div class="ak-card tv-bleed"><p style="margin:0 0 .3rem;font-size:.92rem">Who makes our printed work. A work order goes to a vendor\'s orders email from orders@trvgo.us, with the files to download. Pick an item\'s vendor in its Vendor tab on the order.</p>';
    foreach (Vendors::all($pdo) as $v) {
        $h .= '<details class="vd-row' . ((int) $v['active'] ? '' : ' vd-off') . '" id="vendor-' . (int) $v['id'] . '"><summary><b>' . $e($v['name']) . '</b><small>' . $e(implode(' · ', array_filter([$v['email'] ?: 'no orders email yet', $v['contact'], $v['phone'], $v['account_no'] !== '' ? 'account ' . $v['account_no'] : '', ['setup' => 'takes production setups', 'print' => 'takes 1-up print files'][(string) ($v['file_pref'] ?? '')] ?? '', (int) $v['active'] ? '' : 'retired']))) . '</small></summary>' . $link($v) . $form($v) . '</details>';
    }
    $h .= '<details class="vd-row" id="vendor-new"' . (Vendors::all($pdo) ? '' : ' open') . '><summary><b>+ Add a vendor</b></summary>' . $form([]) . '</details></div>';
    $h .= '<script>(function () {
      document.addEventListener("click", function (ev) {
        var lb = ev.target.closest("button[data-vd-link]"); if (!lb || lb.disabled) return;
        if (!TvAsk.gate(lb, lb.getAttribute("data-ask"), { yes: "Email it" })) return;
        TvAsk.busy(lb, true);
        var lbody = { do: "link", id: +lb.getAttribute("data-vd-link") };
        fetch("/admin/api/vendors.php", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json", "X-CSRF-Token": ' . json_encode($csrf) . ' }, body: JSON.stringify(window.tvSafe ? tvSafe(lbody) : lbody) })
          .then(function (r) { return r.json(); }).then(function (r) {
            TvAsk.busy(lb, false);
            if (!r || r.ok === false) { TvAsk.say((r && r.error && r.error.message) || "That didn\'t go.", { anchor: lb, bad: true }); return; }
            TvUpload.reloadHere("Link emailed to " + r.to + ". It works until " + r.until + ".", document.getElementById("vendor-" + lbody.id));
          }, function () { TvAsk.busy(lb, false); TvAsk.say("That didn\'t reach us. Check your connection and try again.", { anchor: lb, bad: true }); });
      });
      document.addEventListener("submit", function (ev) {
        var f = ev.target.closest("form[data-vd]"); if (!f) return; ev.preventDefault();
        var b = {}; ["name", "code", "email", "contact", "phone", "website", "account_no", "notes", "file_pref"].forEach(function (k) { if (f.elements[k]) b[k] = f.elements[k].value; });
        b.do = "save"; b.id = +f.getAttribute("data-vd"); if (f.elements.active) b.active = f.elements.active.checked ? 1 : 0;
        var btn = f.querySelector("button"); btn.disabled = true;
        fetch("/admin/api/vendors.php", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json", "X-CSRF-Token": ' . json_encode($csrf) . ' }, body: JSON.stringify(window.tvSafe ? tvSafe(b) : b) })
          .then(function (r) { return r.json(); }).then(function (r) {
            btn.disabled = false;
            if (!r || r.ok === false) { TvAsk.say((r && r.error && r.error.message) || "That didn\'t save.", { anchor: btn }); return; }
            TvUpload.reloadHere(b.id ? b.name + " saved." : b.name + " added to the list.", document.getElementById("vendor-" + (b.id || "new")));
          }, function () { btn.disabled = false; TvAsk.say("That didn\'t reach us. Check your connection and try again.", { anchor: btn }); });
      });
    })();</script>';
    return $h;
}

/**
 * Back-Office → Cost list (Jason, 2026-10-29): paper at the best price on the market, binding and other materials, each
 * with its package price, how many in it, any discount, where and when it was priced; its cost a sheet or each is what
 * an order item's Cost tab works its COGS out from.
 */
function tv_admin_mod_cost_list(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/Costing.php';
    if (!Costing::ready($pdo)) return '<div class="ak-card tv-bleed"><p>Run <code>app/migrations/2027-01-08_costing.sql</code> in phpMyAdmin first.</p></div>';
    $e = 'tv_w_e'; $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $items = Costing::items($pdo);
    $form = function (array $c) use ($e): string {
        $f = fn ($k, $lab, $ph = '', $mode = '') => '<label>' . $lab . '<input class="ak-in" name="' . $k . '" value="' . $e((string) ($c[$k] ?? '')) . '"' . ($ph !== '' ? ' placeholder="' . $e($ph) . '"' : '') . ($mode !== '' ? ' inputmode="' . $mode . '"' : '') . '></label>';
        return '<form class="vd-f" data-cl="' . (int) ($c['id'] ?? 0) . '" novalidate>'
            . '<label>Kind<select class="ak-in" name="kind">' . implode('', array_map(fn ($k, $v) => '<option value="' . $k . '"' . (($c['kind'] ?? 'paper') === $k ? ' selected' : '') . '>' . $e($v) . '</option>', array_keys(Costing::KINDS), Costing::KINDS)) . '</select></label>'
            . $f('name', 'Name', 'Blazer Gloss Text 100#') . $f('code', 'Code', 'BGT10018') . $f('size', 'Sheet size', '11x17')
            . '<label>Package price<input class="ak-in" name="pkg" inputmode="decimal" value="' . (isset($c['pkg_cents']) ? number_format((int) $c['pkg_cents'] / 100, 2, '.', '') : '') . '" placeholder="47.69"></label>'
            . $f('per_pkg', 'How many in a package', '500', 'numeric') . $f('discount', 'Discount %', '0', 'decimal')
            . $f('source', 'Where it\'s from', 'Paperworks') . $f('url', 'Link', 'paperworks.com/…')
            . '<label>Priced on<input class="ak-in" type="date" name="priced_on" value="' . $e((string) ($c['priced_on'] ?? date('Y-m-d'))) . '"></label>'
            . '<label class="vd-wide">Note<input class="ak-in" name="note" value="' . $e((string) ($c['note'] ?? '')) . '" placeholder="Cart discount on 44 packages"></label>'
            . (!empty($c['id']) ? '<label class="vd-chk"><input type="checkbox" name="active" value="1"' . ((int) ($c['active'] ?? 1) ? ' checked' : '') . '> On the list (untick to retire it; costings that use it keep its price until they\'re saved again)</label>' : '')
            . '<div class="vd-wide"><button class="ak-b go">' . (!empty($c['id']) ? 'Save' : 'Add it') . '</button></div></form>';
    };
    $h = '<style>.vd-f{display:grid;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));gap:.5rem;margin:.5rem 0}.vd-f label{display:grid;gap:.15rem;font-size:.86rem;color:#5C4632}.vd-wide{grid-column:1/-1}'
        . '.vd-chk{display:flex!important;gap:.4rem;align-items:center;grid-column:1/-1}.vd-chk input{width:auto}.vd-row{border-top:1px solid #EADFC4;padding:.55rem 0}.vd-row>summary{cursor:pointer;display:flex;flex-wrap:wrap;gap:.2rem .8rem;align-items:baseline}'
        . '.vd-row>summary b{color:#92400E;text-decoration:underline}.vd-row small{color:#8A7355}.vd-off{opacity:.6}.cl-u{font-weight:700;color:#1F3A2E}</style>';
    $h .= '<div class="ak-card tv-bleed"><h3 style="margin:0 0 .2rem;font-size:1.05rem">Materials and services</h3><p style="margin:0 0 .3rem;font-size:.92rem">What our products are made of: paper at the best price on the market, binding, boxes, and services we buy or do. An order item\'s Cost tab picks from here (and a cost typed there that isn\'t here joins the list, marked "Added from Order #…"). The cost a sheet or each is the package price ÷ how many, less the discount.</p>';
    foreach (Costing::KINDS as $k => $kl) {
        $mine = array_filter($items, fn ($c) => $c['kind'] === $k); if (!$mine) continue;
        $h .= '<h3 style="margin:.6rem 0 0;font-size:1rem">' . $e($kl) . '</h3>';
        foreach ($mine as $c) {
            $h .= '<details class="vd-row' . ((int) $c['active'] ? '' : ' vd-off') . '" id="cost-' . (int) $c['id'] . '"><summary><b>' . $e($c['name'] . ($c['size'] !== '' ? ' ' . $c['size'] : '')) . '</b><span class="cl-u">$' . rtrim(rtrim(number_format($c['unit'], 5), '0'), '.') . ($k === 'paper' ? ' a sheet' : ' each') . '</span>'
                . '<small>' . $e(implode(' · ', array_filter([$c['code'], '$' . number_format((int) $c['pkg_cents'] / 100, 2) . ' for ' . number_format((int) $c['per_pkg']), (float) $c['discount'] ? rtrim(rtrim(number_format((float) $c['discount'], 3), '0'), '.') . '% off' : '', $c['source'], $c['priced_on'] ? 'priced ' . date('M j, Y', strtotime((string) $c['priced_on'])) : '']))) . '</small>'
                . ($c['url'] !== '' ? ' <a href="' . $e($c['url']) . '" target="_blank" rel="noopener" style="font-size:.84rem">Open the source</a>' : '') . '</summary>' . $form($c) . '</details>';
        }
    }
    $h .= '<details class="vd-row" id="cost-new"' . ($items ? '' : ' open') . '><summary><b>+ Add to the list</b></summary>' . $form([]) . '</details></div>';
    $h .= '<script>(function () {
      document.addEventListener("submit", function (ev) {
        var f = ev.target.closest("form[data-cl]"); if (!f) return; ev.preventDefault();
        var b = {}; ["kind", "name", "code", "size", "pkg", "per_pkg", "discount", "source", "url", "priced_on", "note"].forEach(function (k) { if (f.elements[k]) b[k] = f.elements[k].value; });
        b.do = "save"; b.id = +f.getAttribute("data-cl"); if (f.elements.active) b.active = f.elements.active.checked ? 1 : 0;
        var btn = f.querySelector("button"); btn.disabled = true;
        fetch("/admin/api/costs.php", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json", "X-CSRF-Token": ' . json_encode($csrf) . ' }, body: JSON.stringify(window.tvSafe ? tvSafe(b) : b) })
          .then(function (r) { return r.json(); }).then(function (r) {
            btn.disabled = false;
            if (!r || r.ok === false) { TvAsk.say((r && r.error && r.error.message) || "That didn\'t save.", { anchor: btn, bad: true }); return; }
            TvUpload.reloadHere(b.id ? b.name + " saved." : b.name + " added to the list.", document.getElementById("cost-" + (b.id || "new")));
          }, function () { btn.disabled = false; TvAsk.say("That didn\'t reach us. Check your connection and try again.", { anchor: btn, bad: true }); });
      });
    })();</script>';
    return $h;
}

/**
 * Back-Office → QuickBooks items (decisions/0128): who does what with the money (processor, bookkeeper, order/CRM;
 * tax and history authority), and QuickBooks' items mapped to our products before the books record anything: tag one
 * to a product, make a product from it, mark it a charge (shipping, fees), or leave it. Reading writes nothing in
 * QuickBooks; every tag can be undone.
 */
function tv_admin_mod_qb_items(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/Books.php'; require_once __DIR__ . '/../lib/Products.php';
    [$can, $why] = Access::can('backoffice.qb_items.edit', ['min' => 'manager', 'depts' => ['fin', 'ops']]);
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $uid = (int) (Auth::currentUser()['id'] ?? 0); $e = 'tv_w_e';
    $msg = ''; $flash = fn (string $t, bool $bad = false) => '<p data-tv-flash="' . ($bad ? 'error' : 'ok') . '">' . tv_w_e($t) . '</p>';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['qbi']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try {
            if (!$can) throw new RuntimeException($why);
            switch ((string) $_POST['qbi']) {
                case 'roles': Books::setRoles($pdo, (array) ($_POST['role'] ?? []), $uid); $msg = $flash('Saved: who does what with the money.'); break;
                case 'read': $n = Books::readItems($pdo, $uid); $msg = $flash('Read ' . $n . ' item' . ($n === 1 ? '' : 's') . ' from QuickBooks (' . Books::env() . '). Nothing was changed there.'); break;
                case 'accept': $n = Books::acceptSuggestions($pdo, $uid); $msg = $flash($n ? $n . ' suggestion' . ($n === 1 ? '' : 's') . ' taken: matches tagged, charges marked. Products are only made when you say.' : 'No matches or charges to take.'); break;
                case 'decide': $msg = $flash(Books::decide($pdo, (string) ($_POST['ref'] ?? ''), (string) ($_POST['what'] ?? ''), (int) ($_POST['product'] ?? 0), $uid)); break;
                case 'undo': SystemTags::undo($pdo, (int) ($_POST['tag'] ?? 0), $uid); $msg = $flash('Undone.'); break;
            }
        } catch (Throwable $x) { $msg = $flash($x instanceof RuntimeException ? $x->getMessage() : 'That didn\'t work: try again.', true); if (!($x instanceof RuntimeException)) error_log('qb items: ' . $x->getMessage()); }
    }
    $post = fn (string $do, string $inner, string $label, string $cls = 'go', string $ask = '') => '<form method="post" style="display:inline-flex;flex-wrap:wrap;gap:.35rem;align-items:center;margin:0"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="qbi" value="' . $do . '">'
        . $inner . '<button class="ak-b ' . $cls . '"' . ($ask !== '' ? ' data-confirm="' . $e($ask) . '" data-yes="' . $e($label) . '"' : '') . ($can ? '' : ' disabled title="' . $e($why) . '"') . '>' . $e($label) . '</button></form>';

    // who does what
    $h = '<style>.qbi-roles{display:grid;grid-template-columns:repeat(auto-fit,minmax(15rem,1fr));gap:.6rem;margin:.4rem 0}.qbi-roles label{display:grid;gap:.15rem;font-size:.86rem;color:#5C4632}.qbi-roles small{color:#8A7355}'
        . '.qbi-d{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;margin-top:.4rem}.qbi-d select{max-width:100%}.qbi-sug{font-size:.86rem;color:#5C4632;margin:.2rem 0}</style>';
    $h .= $msg . '<div class="ak-card tv-bleed"><h3 style="margin:0 0 .2rem">Who does what with the money</h3><p class="ak-note" style="margin:0">Each set on its own. Every order keeps the setup it was made under, so changing one later never rewrites old orders.</p>'
        . '<form method="post"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="qbi" value="roles"><div class="qbi-roles">';
    foreach (Books::ROLES as $k => [$label, $what, $choices]) {
        $cur = Books::role($pdo, $k); $fixed = in_array($k, ['tax', 'history'], true) && Books::role($pdo, 'books') === 'trv';
        if ($fixed) $what = 'Traversence keeps the books, so it\'s the authority.';
        $h .= '<label>' . $e($label) . '<select class="ak-in" name="role[' . $k . ']"' . ($can && !$fixed ? '' : ' disabled') . '>' . implode('', array_map(fn ($v, $l) => '<option value="' . $v . '"' . ($v === $cur ? ' selected' : '') . '>' . $e($l) . '</option>', array_keys($choices), $choices)) . '</select><small>' . $e($what) . '</small></label>';
    }
    $h .= '</div><button class="ak-b go"' . ($can ? '' : ' disabled title="' . $e($why) . '"') . '>Save</button></form>';
    $h .= '<h4 style="margin:.7rem 0 .2rem">What each needs to work</h4>';
    foreach (Books::requirements($pdo) as [$what, $ok, $text, $link])
        $h .= '<p data-tv-status="' . ($ok ? 'ok' : 'bad') . '" data-tv-label="' . $e($what . ':') . '">' . $e($text) . ($link ? ' <a href="' . $e($link[1]) . '">' . $e($link[0]) . '</a>' : '') . '</p>';
    $h .= '</div>';

    // mapping
    $env = Books::env(); $items = Books::items($pdo); $pr = Books::progress($pdo);
    $products = []; foreach (Products::all($pdo) as $p) $products[(int) $p['id']] = (string) $p['name'];
    $h .= '<div class="ak-card tv-bleed"><h3 style="margin:0 0 .2rem">QuickBooks items and our products</h3>'
        . '<p class="ak-note" style="margin:0 0 .4rem">Map each QuickBooks item before the books record orders: tag it to one of our products (a general item like "Printing" may stand for several), make a product from it, mark it a charge (shipping, shrink wrap, fees: never a product or a reorder item), or leave it. Mapping never changes a past order, and nothing is written to QuickBooks. QuickBooks is in <b>' . $e(ucfirst($env)) . '</b>; its items are mapped separately.</p>';
    $h .= $items
        ? '<p data-tv-status="' . ($pr['open'] ? 'info' : 'ok') . '" data-tv-label="' . ($pr['open'] ? 'Mapping:' : 'All mapped:') . '">' . $pr['decided'] . ' of ' . $pr['items'] . ' items decided' . ($pr['open'] ? ', ' . $pr['open'] . ' active ones still open' : '') . '. ' . ($pr['untagged'] ? $pr['untagged'] . ' of our products have no QuickBooks item yet: each gets one the first time it\'s invoiced, made once and remembered.' : 'Every active product has its QuickBooks item.') . '</p>'
        : '<p data-tv-status="info" data-tv-label="Start here:">Read the items from QuickBooks to map them.</p>';
    $sugN = count(array_filter($items, fn ($r) => $r['state'] === 'open' && $r['suggest'] && $r['suggest'][0] !== 'new'));
    $h .= '<div style="display:flex;flex-wrap:wrap;gap:.4rem;margin:.4rem 0">' . $post('read', '', $items ? 'Read the items again' : 'Read the items from QuickBooks', $items ? '' : 'go')
        . ($sugN ? $post('accept', '', 'Take the ' . $sugN . ' suggested match' . ($sugN === 1 ? '' : 'es'), 'go', 'Tag the suggested matches and mark the suggested charges? Each can be undone.') : '') . '</div>';
    $opts = function (int $sel) use ($products, $e): string { $o = '<option value="0">Choose a product…</option>'; foreach ($products as $id => $n) $o .= '<option value="' . $id . '"' . ($id === $sel ? ' selected' : '') . '>' . $e($n) . '</option>'; return $o; };
    $stateWord = ['linked' => 'Tagged', 'charge' => 'A charge', 'leave' => 'Left as it is', 'open' => 'Not decided'];
    foreach ($items as $r) {
        $pn = array_map(fn ($id) => $products[$id] ?? ('#' . $id), $r['products']);
        $status = $stateWord[$r['state']] . ($pn ? ': ' . implode(', ', $pn) : '') . ($r['price'] !== null ? ' · $' . rtrim(rtrim(number_format((float) $r['price'], 4), '0'), '.') : '') . ((int) $r['active'] ? '' : ' · inactive in QuickBooks');
        $tone = $r['state'] === 'open' ? ((int) $r['active'] ? 'bad' : '') : 'ok';
        $sug = $r['suggest'] ? '<p class="qbi-sug">Suggested: <b>' . $e($r['suggest'][0] === 'product' ? 'tag it to ' . ($products[$r['suggest'][1]] ?? '') : ($r['suggest'][0] === 'charge' ? 'a charge' : 'make a product from it')) . '</b> (' . $e(end($r['suggest'])) . ')</p>' : '';
        $sel = $r['suggest'] && $r['suggest'][0] === 'product' ? (int) $r['suggest'][1] : 0;
        $what = '<select class="ak-in" name="what" aria-label="What to do"><option value="link"' . ($r['suggest'] && $r['suggest'][0] === 'product' ? ' selected' : '') . '>Tag it to our product</option><option value="make"' . ($r['suggest'] && $r['suggest'][0] === 'new' ? ' selected' : '') . '>Make a product from it</option><option value="charge"' . ($r['suggest'] && $r['suggest'][0] === 'charge' ? ' selected' : '') . '>It\'s a charge</option><option value="leave">Leave it</option>' . ($r['state'] !== 'open' ? '<option value="clear">Undecided again</option>' : '') . '</select>';
        $body = ($r['description'] !== '' ? '<p class="ak-note" style="margin:.1rem 0">' . $e($r['description']) . '</p>' : '') . $sug
            . '<div class="qbi-d">' . $post('decide', '<input type="hidden" name="ref" value="' . $e($r['ref']) . '">' . $what . '<select class="ak-in" name="product" aria-label="Our product">' . $opts($sel) . '</select>', 'Save') . '</div>';
        $h .= tv_admin_fold((string) $r['name'], $status, $tone, '', $body, false);
    }
    $rec = SystemTags::recent($pdo, 'qbo.item', $env, 15);
    if ($rec) {
        $body = '';
        foreach ($rec as $t) $body .= '<div class="ak-res"><b class="' . ($t['removed_at'] ? '' : 'ok') . '">' . ($t['removed_at'] ? '–' : '✓') . '</b><span>' . $e(($t['ref_name'] ?: 'Item ' . $t['ref']) . ($t['removed_at'] ? ' taken off ' : ' tagged to ') . ($products[(int) $t['thing_id']] ?? '#' . $t['thing_id']))
            . '<small>' . $e(date('M j, g:i a', strtotime((string) ($t['removed_at'] ?: $t['set_at']))) . ($t['note'] !== '' ? ' · ' . $t['note'] : '')) . '</small></span>' . $post('undo', '<input type="hidden" name="tag" value="' . (int) $t['id'] . '">', 'Undo', '') . '</div>';
        $h .= tv_admin_fold('Recent tag changes', count($rec) . ' latest, each can be undone', '', '', $body, false);
    }
    return $h . '</div>';
}

/**
 * Back-Office → Orders: Pull an invoice from QuickBooks (decisions/0128, step 2). Its number shows a preview first (the
 * client, each line as it'll come in, what's paid); items not mapped yet are decided here, once each, and remembered.
 * Then the order is built from the invoice and the site processes it from there.
 */
function tv_admin_mod_qb_pull(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/QbPull.php';
    [$can, $why] = Access::can('backoffice.orders.pull', ['min' => 'manager', 'depts' => ['fin', 'ops']]);
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $uid = (int) (Auth::currentUser()['id'] ?? 0); $e = 'tv_w_e'; $usd = fn ($c) => Orders::usd((int) $c);
    $no = trim((string) ($_POST['qbp_no'] ?? $_GET['qbpull'] ?? '')); $msg = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['qbp'] ?? '') === 'pull' && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try {
            if (!$can) throw new RuntimeException($why);
            $oid = QbPull::pull($pdo, $no, ['email' => (string) ($_POST['email'] ?? ''), 'client' => (string) ($_POST['client'] ?? 'auto'), 'item' => (array) ($_POST['item'] ?? []), 'pick' => (array) ($_POST['pick'] ?? [])], $uid);
            $o = Orders::get($pdo, $oid);
            return '<div class="ak-card tv-bleed" id="qbpull"><p data-tv-status="ok" data-tv-label="Pulled:">Invoice ' . $e($no) . ' is now order #' . $e(Orders::no($o)) . '. Opening it…</p><a class="ak-b go" href="/admin/backoffice/orders/' . $oid . '">Open order #' . $e(Orders::no($o)) . '</a></div>'
                . '<script>setTimeout(function () { location.href = "/admin/backoffice/orders/' . $oid . '"; }, 900);</script>';
        } catch (Throwable $x) { $msg = '<p data-tv-flash="error">' . $e($x instanceof RuntimeException ? $x->getMessage() : 'That didn\'t work: try again.') . '</p>'; if (!($x instanceof RuntimeException)) error_log('qb pull: ' . $x->getMessage()); }
    }
    $h = '<style>.qbp-f{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center}.qbp-l{border-top:1px solid #EADFC4;padding:.45rem 0;display:grid;grid-template-columns:1fr auto;gap:.2rem .6rem}.qbp-l small{color:#8A7355;display:block}.qbp-l .amt{text-align:right;font-weight:700}'
        . '.qbp-dec{display:grid;gap:.3rem;margin:.4rem 0}.qbp-dec label{display:grid;gap:.15rem;font-size:.86rem;color:#5C4632}.qbp-pill{display:inline-block;font-size:.72rem;padding:.05rem .45rem;border-radius:999px;background:#F3EAD6;color:#5C4632;margin-left:.3rem}</style>';
    $h .= '<div class="ak-card tv-bleed" id="qbpull"><h3 style="margin:0 0 .2rem">Pull an invoice from QuickBooks</h3><p class="ak-note" style="margin:0 0 .4rem">An order that started in QuickBooks: its number brings it in as an order here, with its client, lines, tax and payments. You see it first; nothing changes in QuickBooks.</p>'
        . $msg . '<form method="get" action="#qbpull" class="qbp-f"><input class="ak-in" name="qbpull" value="' . $e($no) . '" placeholder="Invoice number, e.g. 112906" inputmode="numeric" style="max-width:14rem"><button class="ak-b">Show it</button></form>';
    if ($no === '') return $h . '</div>';
    try { $pv = QbPull::preview($pdo, $no); } catch (Throwable $x) { return $h . '<p data-tv-status="bad" data-tv-label="Not found:">' . $e($x instanceof RuntimeException ? $x->getMessage() : 'QuickBooks didn\'t answer: try again.') . '</p></div>'; }
    $inv = $pv['inv'];
    if ($pv['existing']) return $h . '<p data-tv-status="info" data-tv-label="Already here:">Invoice ' . $e($inv['no']) . ' is order #' . $e(Orders::no($pv['existing'])) . '. <a href="/admin/backoffice/orders/' . (int) $pv['existing']['id'] . '">Open it</a></p></div>';
    if ($pv['refers']) return $h . '<p data-tv-status="info" data-tv-label="Merge instead:">Order #' . $e(Orders::no($pv['refers'][0])) . ' already refers to invoice ' . $e($inv['no']) . ': merge the two there (the order takes the invoice\'s lines, tax and payments). Pulling it as a second order would count it twice.</p><a class="ak-b go" href="/admin/backoffice/orders/' . (int) $pv['refers'][0]['id'] . '?qbmerge=' . rawurlencode($inv['no']) . '#qbmerge">Merge it into order #' . $e(Orders::no($pv['refers'][0])) . '</a></div>';
    $h .= '<div style="margin:.6rem 0"><b>' . $e($inv['company'] ?: $inv['person']) . '</b>' . ($inv['company'] && $inv['person'] !== $inv['company'] ? ' · ' . $e($inv['person']) : '')
        . '<br><small class="ak-note">Invoice ' . $e($inv['no']) . ($inv['date'] ? ' · ' . $e(date('M j, Y', strtotime($inv['date']))) : '') . ($inv['due'] ? ' · due ' . $e(date('M j', strtotime($inv['due']))) : '') . ' · ' . $e($inv['email'] ?: 'no email in QuickBooks') . '</small></div>';
    $h .= '<p data-tv-status="' . ($inv['balance'] <= 0 ? 'ok' : 'info') . '" data-tv-label="' . ($inv['balance'] <= 0 ? 'Paid:' : 'Open:') . '">' . $usd($inv['total']) . ' with ' . $usd($inv['tax']) . ' tax' . ($inv['balance'] <= 0 ? ', paid in full in QuickBooks: recorded here as paid, nothing posted back.' : ($inv['paid'] ? ', ' . $usd($inv['paid']) . ' paid, ' . $usd($inv['balance']) . ' left: a payment in QuickBooks later marks it paid here on its own.' : ', not paid yet: a payment in QuickBooks marks it paid here on its own.')) . '</p>';
    $products = []; foreach (Products::all($pdo) as $p) $products[(int) $p['id']] = (string) $p['name'];
    $form = '<form method="post" action="#qbpull"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="qbp" value="pull"><input type="hidden" name="qbp_no" value="' . $e($inv['no']) . '">';
    // the lines
    $form .= '<h4 style="margin:.6rem 0 .1rem">Lines</h4>';
    $open = [];
    foreach ($pv['lines'] as $l) {
        $kind = $l['kind'] === 'product' ? 'Product: ' . ($products[$l['product']] ?? '') : ($l['kind'] === 'charge' ? 'Charge (never a reorder item)' : 'Item not mapped yet: decide below');
        if ($l['kind'] === 'open') $open[$l['item'][0]] = $l['item'][1];
        $pick = count($l['products']) > 1 ? '<label style="display:grid;font-size:.82rem;color:#5C4632;margin-top:.2rem">Which product<select class="ak-in" name="pick[' . $l['i'] . ']">' . implode('', array_map(fn ($pid) => '<option value="' . $pid . '"' . ($pid === $l['product'] ? ' selected' : '') . '>' . $e($products[$pid] ?? '#' . $pid) . '</option>', $l['products'])) . '</select></label>' : '';
        $form .= '<div class="qbp-l"><div><b>' . $e($l['head']) . '</b>' . ($l['qty'] > 1 ? ' · ' . $e(number_format($l['qty'])) : '') . '<small>' . $e($kind) . ' · QuickBooks item "' . $e($l['item'][1]) . '"' . ($l['taxable'] ? ' · taxed' : '') . '</small>' . $pick . '</div><div class="amt">' . $usd($l['amount']) . '</div></div>';
    }
    if ($open) {
        $form .= '<h4 style="margin:.6rem 0 .1rem">Items to map first</h4><p class="ak-note" style="margin:0">Decided once for each item, however many lines use it, and remembered for every later invoice (Back-Office → QuickBooks items).</p><div class="qbp-dec">';
        foreach ($open as $ref => $name) {
            $o2 = '<option value="">Choose…</option><option value="make">Make a product from it</option><option value="charge">It\'s a charge (shipping, fees)</option>';
            foreach ($products as $pid => $pn) $o2 .= '<option value="p:' . $pid . '"' . (mb_strtolower($pn) === mb_strtolower($name) ? ' selected' : '') . '>Tag it to ' . $e($pn) . '</option>';
            $form .= '<label>"' . $e((string) $name) . '"<select class="ak-in" name="item[' . $e((string) $ref) . ']" required>' . $o2 . '</select></label>';
        }
        $form .= '</div>';
    }
    // the client
    $form .= '<h4 style="margin:.6rem 0 .1rem">Client</h4>';
    if ($pv['client']['tagged']) $form .= '<p class="ak-note" style="margin:0">' . $e($pv['client']['tagged']['name']) . ' (its QuickBooks customer is tagged to it).</p>';
    else {
        $form .= '<select class="ak-in" name="client"><option value="auto">Match it as other new orders are (a clear match, or a private listing for the business)</option>';
        foreach ($pv['client']['found'] as $f) $form .= '<option value="' . (int) $f['id'] . '">' . $e($f['name'] . ($f['city'] ? ', ' . $f['city'] : '') . ' (' . $f['why'] . ')') . '</option>';
        $form .= '<option value="person">The person alone (no business)</option></select>';
    }
    if ($inv['email'] === '') $form .= '<label style="display:grid;gap:.15rem;margin-top:.4rem;font-size:.86rem;color:#5C4632">The client\'s email (QuickBooks has none)<input class="ak-in" type="email" name="email" required></label>';
    $form .= '<div style="margin-top:.6rem"><button class="ak-b go" data-confirm="Make order from invoice ' . $e($inv['no']) . '?' . ($open ? ' The items you mapped are remembered.' : '') . '" data-yes="Make the order"' . ($can ? '' : ' disabled title="' . $e($why) . '"') . '>Make the order</button></div></form>';
    return $h . $form . '</div>';
}

/**
 * System Configuration → Database updates (Jason, 2026-10-28: a file used once is deleted once it's been used). Each
 * file in app/migrations is checked against the database: run (what it makes is all there), not run yet, or can't tell
 * (it only changes data). Run files are cleared from the server; the sync then deletes them from OneDrive too.
 * Since 2026-11-14 (decisions/0162): the copies kept before each migration ran, each with Restore, and what rolled-back
 * releases left in the database.
 */
function tv_admin_mod_dbupdates(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/DbUpdates.php';
    require_once __DIR__ . '/../lib/DbCopies.php';
    require_once __DIR__ . '/../lib/SchemaUpkeep.php';
    [$can, $why] = Access::can('config.dbupdates', ['min' => 'executive', 'depts' => []]);
    [$canRestore, $whyRestore] = Access::can('config.dbupdates.restore', ['min' => 'executive', 'depts' => []]);
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $e = 'tv_w_e'; $msg = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && in_array($_POST['dbu'] ?? '', ['restore', 'keep'], true) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try {
            $uid = (int) (Auth::currentUserId() ?? 0);
            if ($_POST['dbu'] === 'restore') {
                if (!$canRestore) throw new RuntimeException($whyRestore);
                $msg = '<p data-tv-flash="ok">' . $e(DbCopies::restore($pdo, (string) ($_POST['copy'] ?? ''), $uid)) . '</p>';
            } else {
                if (!$can) throw new RuntimeException($why);
                SchemaUpkeep::keep((string) ($_POST['left'] ?? ''), $uid);
                $msg = '<p data-tv-flash="ok">Kept: it isn\'t proposed for removal again.</p>';
            }
        } catch (Throwable $x) { $msg = '<p data-tv-flash="error">' . $e($x instanceof RuntimeException ? $x->getMessage() : 'That didn\'t work: try again, or write to support@traversence.com.') . '</p>'; if (!($x instanceof RuntimeException)) error_log('db updates: ' . $x->getMessage()); }
        unset($_POST['dbu']);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['dbu']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try {
            if (!$can) throw new RuntimeException($why);
            $say = $_POST['dbu'] === 'said';
            $files = $say ? [(string) ($_POST['file'] ?? '')] : array_column(array_filter(DbUpdates::all($pdo), fn ($r) => $r['state'] === 'run'), 'file');
            $n = DbUpdates::clear($pdo, $files, $say);
            $msg = '<p data-tv-flash="ok">' . ($n ? 'Cleared ' . $n . ' file' . ($n === 1 ? '' : 's') . ' from the server. The sync deletes ' . ($n === 1 ? 'it' : 'them') . ' from OneDrive on its next run (to the recycle bin, with a copy kept in its history).' : 'Nothing to clear.') . '</p>';
        } catch (Throwable $x) { $msg = '<p data-tv-flash="error">' . $e($x instanceof RuntimeException ? $x->getMessage() : 'That didn\'t work: try again.') . '</p>'; if (!($x instanceof RuntimeException)) error_log('db updates: ' . $x->getMessage()); }
    }
    // the site runs what's waiting itself when this page opens (and within the hour from the scheduled jobs)
    $auto = $can ? DbUpdates::autoRun($pdo, false, 'Database updates opened') : ['ran' => [], 'failed' => []];
    if ($auto['ran']) $msg .= '<p data-tv-flash="ok">Ran and cleared ' . count($auto['ran']) . ' file' . (count($auto['ran']) === 1 ? '' : 's') . ': ' . $e(implode(', ', $auto['ran'])) . '.</p>';
    foreach ($auto['failed'] as $f => $err) if (str_contains((string) $err, 'copy first')) $msg .= '<p data-tv-status="bad" data-tv-label="Waiting for its copy:">' . $e($f . ': ' . $err) . '</p>';
    $all = DbUpdates::all($pdo); $by = ['notrun' => [], 'partial' => [], 'unknown' => [], 'run' => []];
    foreach ($all as $r) $by[$r['state']][] = $r;
    $btn = fn (string $do, string $inner, string $label, string $ask, bool $danger = true) => '<form method="post" style="display:inline;margin:0"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="dbu" value="' . $do . '">' . $inner
        . '<button class="ak-b' . ($danger ? '' : ' go') . '" data-confirm="' . $e($ask) . '" data-yes="' . $e($label) . '"' . ($danger ? ' data-danger' : '') . ($can ? '' : ' disabled title="' . $e($why) . '"') . '>' . $e($label) . '</button></form>';
    $h = '<div class="ak-card tv-bleed"><h3 style="margin:0 0 .2rem">Database updates</h3><p class="ak-note" style="margin:0 0 .4rem">The files in <code>app/migrations</code> change the database. Nothing to do by hand: a release\'s migrations run as the first step of its Publish, before its files go live (one that fails stops the publish, with what went wrong in the release\'s record). Any still waiting run within the hour (or when this page opens), oldest first, and each is cleared once it has run; one that fails stays here with what went wrong, and the ones after it wait. Just before each one runs, a copy of the tables it touches is kept for ' . DbCopies::KEEP_DAYS . ' days (below, with Restore).</p>' . $msg;
    foreach (DbUpdates::log($pdo) as $f => $l) if (!(int) $l['ok'] && is_file(DbUpdates::dir() . '/' . $f)) $h .= '<p data-tv-status="bad" data-tv-label="Didn\'t run:">' . $e($f) . ': ' . $e((string) $l['error']) . ' It\'s tried again when the file is replaced.</p>';
    $h .= $by['notrun'] ? '<p data-tv-status="bad" data-tv-label="Not run yet:">' . count($by['notrun']) . ' file' . (count($by['notrun']) === 1 ? '' : 's') . ' still to run: the features ' . (count($by['notrun']) === 1 ? 'it adds don\'t' : 'they add don\'t') . ' work until ' . (count($by['notrun']) === 1 ? 'it is' : 'they are') . '. The site runs them within the hour.</p>'
                        : '<p data-tv-status="ok" data-tv-label="All run:">Every file that makes tables or columns has been run.</p>';
    $h .= $by['run'] ? '<div style="display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;margin:.4rem 0">' . $btn('run', '', 'Clear the ' . count($by['run']) . ' run file' . (count($by['run']) === 1 ? '' : 's'), 'Delete the ' . count($by['run']) . ' files that have been run from the server? The sync takes them off OneDrive too (recycle bin) and keeps their history.')
        . '<small class="ak-note">' . count($by['run']) . ' run and safe to clear.</small></div>' : '';
    $rows = function (array $list, bool $say) use ($e, $btn): string {
        $o = '';
        foreach ($list as $r) $o .= '<div class="ak-res"><span>' . $e($r['file']) . '<small>' . ($r['of'] ? $r['have'] . ' of ' . $r['of'] . ' there' . ($r['missing'] ? ': missing ' . $e(implode(', ', array_slice($r['missing'], 0, 4))) . (count($r['missing']) > 4 ? '…' : '') : '') : 'only changes data: nothing to look for') . '</small></span>'
            . ($say ? $btn('said', '<input type="hidden" name="file" value="' . $e($r['file']) . '">', 'It\'s been run: clear it', 'Clear ' . $r['file'] . '? Only if you know it\'s been run.') : '') . '</div>';
        return $o;
    };
    if ($by['notrun']) $h .= tv_admin_fold('Not run yet', count($by['notrun']) . ' to run in phpMyAdmin', 'bad', '', $rows($by['notrun'], false), true);
    if ($by['partial']) $h .= tv_admin_fold('Partly there', count($by['partial']) . ': a later file may have changed what it made', '', '', $rows($by['partial'], true), false);
    if ($by['unknown']) $h .= tv_admin_fold('Can\'t tell', count($by['unknown']) . ' only change data: clear one when you know it\'s been run', '', '', $rows($by['unknown'], true), false);
    if ($by['run']) $h .= tv_admin_fold('Run', count($by['run']) . ' files', 'ok', '', $rows($by['run'], false), false);
    // the copies kept before each migration ran (decisions/0162), each with Restore
    $time = fn (string $iso) => '<time datetime="' . $e($iso) . '" data-tv-time>' . $e(gmdate('M j, g:i a', (int) strtotime($iso))) . ' UTC</time>';
    $size = fn (int $b) => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
    $copies = DbCopies::all(); $cb = '';
    foreach ($copies as $c) {
        $tables = implode(', ', array_map(fn ($t) => $t['db'] . '.' . $t['table'] . ' (' . number_format((int) $t['rows']) . ' rows, ' . $size((int) $t['bytes']) . ')', (array) $c['tables']));
        $skips = implode('; ', array_map(fn ($k, $v) => $k . ': ' . $v, array_keys((array) $c['skipped']), (array) $c['skipped']));
        $last = $c['restored'] ? end($c['restored']) : null;
        $cb .= '<div class="ak-res"><span>' . $e((string) $c['file']) . '<small>' . $time((string) $c['at']) . ' · ' . $e((string) $c['context']) . ($c['changes_data'] ? ' · changes data' : '') . '</small>'
            . ($tables !== '' ? '<small>Copied: ' . $e($tables) . '</small>' : '')
            . ($skips !== '' ? '<small data-tv-status="info" data-tv-label="Not copied:">' . $e($skips) . '</small>' : '')
            . ($last ? '<small data-tv-status="' . ($last['failed'] ? 'bad' : 'ok') . '" data-tv-label="Restored:">' . $time((string) $last['at']) . ': ' . $e(implode('; ', array_merge((array) $last['done'], (array) $last['failed']))) . '</small>' : '') . '</span>'
            . ($c['tables'] ? '<form method="post" style="display:inline;margin:0"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="dbu" value="restore"><input type="hidden" name="copy" value="' . $e((string) $c['id']) . '">'
              . '<button class="ak-b" data-confirm="' . $e('Put back the rows of ' . implode(', ', array_map(fn ($t) => $t['table'], (array) $c['tables'])) . ' as they were before ' . $c['file'] . ' ran? Everything written to ' . (count($c['tables']) === 1 ? 'that table' : 'those tables') . ' since is replaced (a copy of how ' . (count($c['tables']) === 1 ? 'it is' : 'they are') . ' now is kept first). Columns added since stay, with their defaults.') . '" data-yes="Restore" data-danger'
              . ($canRestore ? '' : ' disabled title="' . $e($whyRestore) . '"') . '>Restore</button></form>' : '') . '</div>';
    }
    $h .= tv_admin_fold('Copies before migrations', $copies ? count($copies) . ' kept (' . DbCopies::KEEP_DAYS . ' days)' : 'none yet', '', '', $cb !== '' ? $cb : '<p class="ak-note">A copy is kept here each time a migration runs that touches tables already there.</p>', false);
    // what rolled-back releases left (decisions/0162): proposed for removal after SchemaUpkeep::DUE_DAYS
    $left = SchemaUpkeep::leftovers($pdo);
    if ($left) {
        $lb = '';
        foreach ($left as $k => $it) {
            $lb .= '<div class="ak-res"><span>' . $e($k) . '<small>left by release #' . (int) $it['release'] . ' (' . $e((string) $it['title']) . '), from ' . $e((string) $it['file']) . ', since ' . $time((string) $it['at'])
                . (!empty($it['kept_at']) ? ' · kept' : ($it['due'] ? ' · due for removal: removal comes as a release (copied first, then dropped), once the Founder approves' : ' · removal is proposed after ' . SchemaUpkeep::DUE_DAYS . ' days unless the release is sent again')) . '</small></span>'
                . (empty($it['kept_at']) ? '<form method="post" style="display:inline;margin:0"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="dbu" value="keep"><input type="hidden" name="left" value="' . $e($k) . '"><button class="ak-b" data-confirm="' . $e('Keep ' . $k . ' where it is? It isn\'t proposed for removal again.') . '" data-yes="Keep it"' . ($can ? '' : ' disabled title="' . $e($why) . '"') . '>Keep it</button></form>' : '') . '</div>';
        }
        $due = count(array_filter($left, fn ($i) => $i['due']));
        $h .= tv_admin_fold('Left by rolled-back releases', count($left) . ($due ? ', ' . $due . ' due for removal' : ''), $due ? 'bad' : '', '', $lb, $due > 0);
    }
    // what no live code names (SchemaUpkeep's monthly check): the list Pulse's case opens on (it showed nothing here before,
    // 2026-11-18), each with its database, table, column or rows and size, read only; removal is a release, copied first
    $u = SchemaUpkeep::unused(); $keptU = SchemaUpkeep::keptUnused();
    $all = array_merge($u['tables'], $u['columns']);
    if ($all) {
        // what it means for the person reading it (Jason, 2026-11-18: "not sure what these means to me as admin, i dont see how
        // to act on it"): nothing to press here; Claude sorts them and brings one list to approve
        $ub = '<p data-tv-status="info" data-tv-label="Nothing to do here:">These are parts of the database that no page or job uses any more, most likely left over from older versions of the site. They take a little space and do no harm. Claude checks each one and brings you one list to approve: which are safe to remove, which to keep and why, and any it isn\'t sure of. Nothing is removed without your OK, and a copy is kept first.</p>'
            . '<p class="ak-note">The monthly check (last ' . ($u['at'] !== '' ? $time((string) $u['at']) : 'not yet') . ') read ' . number_format((int) $u['files']) . ' live files; none of them names these. A name built in code (a table name made from parts) may still use one: each is checked before removal, which comes as a release (copied first, then dropped) once the Founder approves. Keep it is in the Pulse case.</p>';
        foreach ($all as $x) {
            $k = (string) $x['key']; $kept = isset($keptU[$k]);
            $what = isset($x['column']) ? 'Column ' . $x['table'] . '.' . $x['column'] : 'Table ' . $x['table'] . ' · ' . number_format((int) $x['rows']) . ' rows, ' . number_format(((int) $x['bytes']) / 1048576, 2) . ' MB';
            $ub .= '<div class="ak-res"><span>' . $e($what) . '<small>in ' . $e((string) $x['db']) . ($kept ? ' · kept (' . $time((string) $keptU[$k]['at']) . ')' : '') . '</small></span>'
                . '</div>';   // read only (the session manager, 2026-11-18): keeping or removing one is decided on the lists sent to the Founder
        }
        $open = count(array_filter($all, fn ($x) => !isset($keptU[$x['key']])));
        $h .= '<div id="unused"></div>' . tv_admin_fold('Unused tables and columns (for Claude to sort)', count($u['tables']) . (count($u['tables']) === 1 ? ' table, ' : ' tables, ') . count($u['columns']) . (count($u['columns']) === 1 ? ' column' : ' columns') . ($open < count($all) ? ' (' . (count($all) - $open) . ' kept)' : ''), $open ? 'info' : 'ok', '', $ub, true);
    }
    $done = array_filter(DbUpdates::log($pdo), fn ($l) => (int) $l['ok']);
    if ($done) $h .= tv_admin_fold('Run by the site', count($done) . ' recently', 'ok', '', implode('', array_map(fn ($f, $l) => '<div class="ak-res"><span>' . $e($f) . '<small>ran ' . $e(date('M j, g:i a', strtotime((string) $l['ran_at']))) . '</small></span></div>', array_keys(array_slice($done, 0, 20)), array_slice($done, 0, 20))), false);
    return $h . '</div>';
}

/** Social → Takedowns (decisions/0133): what people asked us to take down, hidden within 24 hours while we look; restore or keep it down. */
function tv_admin_mod_takedowns(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/Takedowns.php';
    $e = 'tv_w_e';
    if (!Takedowns::ready($pdo)) return '<p data-tv-status="info" data-tv-label="Not ready:">The database update for takedowns hasn\'t run yet: it runs within the hour, or open Database updates.</p>';
    [$can, $why] = Access::can('social.takedowns.decide', ['min' => 'manager', 'depts' => ['ops', 'con']]);
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $msg = ''; $openId = (int) ($_POST['td'] ?? $_GET['td'] ?? 0);
    if ($can && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['td_do']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try {
            $uid = (int) (Auth::currentUserId() ?? 0); $note = (string) ($_POST['note'] ?? '');
            match ((string) $_POST['td_do']) { 'hide' => Takedowns::hide($pdo, $openId), 'restore' => Takedowns::restore($pdo, $openId, $uid, $note), 'uphold' => Takedowns::uphold($pdo, $openId, $uid, $note), default => null };
            $msg = '<p data-tv-flash="ok">' . ['hide' => 'It\'s hidden now.', 'restore' => 'It\'s back on the site; whoever asked and left an email is told.', 'uphold' => 'It stays down; whoever asked and left an email is told.'][$_POST['td_do']] . '</p>';
        } catch (Throwable $x) { $msg = '<p data-tv-flash="error">' . $e($x->getMessage()) . '</p>'; }
    }
    $rows = Takedowns::queue($pdo);
    $h = '<p class="ak-note">Anyone can ask us to take down something we publish (a guide\'s section, a heritage fact, a topic, a landmark). A protected reason (a way in to a sacred, burial or archaeological place, a nation asking, someone\'s private information, a safety risk) hides it at once; anything else is hidden when 24 hours pass without a decision. '
       . '<b>Restore</b> brings it back (nothing wrong, or it\'s been fixed); <b>Keep it down</b> makes a fact restricted, keeps a landmark hidden and keeps a guide\'s section out until it\'s rewritten and restored. Whoever asked and left an email hears what we decided.' . ($can ? '' : ' <b>' . $e($why) . '</b>') . '</p>' . $msg;
    if (!$rows) return '<div>' . $h . '<p data-tv-status="ok" data-tv-label="All clear:">No takedown requests waiting.</p></div>';
    $label = ['open' => 'Asked', 'hidden' => 'Hidden while we look', 'restored' => 'Restored', 'upheld' => 'Kept down'];
    foreach ($rows as $r) {
        $live = in_array($r['status'], ['open', 'hidden'], true);
        $status = $label[$r['status']] . ' · ' . (Takedowns::REASONS[$r['reason']][0] ?? $r['reason']) . ' · ' . date('M j, g:i a', strtotime((string) $r['created_at']))
                . ($r['status'] === 'open' ? ' · hidden by ' . date('M j, g:i a', strtotime((string) $r['hide_by'])) : '');
        $body = '<p class="ak-note">' . $e(Takedowns::KINDS[$r['kind']] ?? $r['kind']) . ($r['url'] !== '' ? ' · <a class="underline" href="' . $e((string) $r['url']) . '" data-peek-frame>see it</a>' : '') . ($r['email'] !== '' ? ' · ' . $e((string) $r['email']) : '') . '</p>'
              . ($r['note'] ? '<p style="white-space:pre-line">' . $e((string) $r['note']) . '</p>' : '')
              . ($r['decision_note'] !== '' ? '<p class="ak-note">Our note: ' . $e((string) $r['decision_note']) . '</p>' : '');
        if ($live) {
            $dis = $can ? '' : ' disabled title="' . $e($why) . '"';
            $body .= '<form method="post" class="ak-sk"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="td" value="' . (int) $r['id'] . '">'
                   . '<input class="ak-in" name="note" maxlength="500" placeholder="A note for whoever asked (optional)" aria-label="A note for whoever asked">'
                   . ($r['status'] === 'open' ? '<button class="ak-b" name="td_do" value="hide"' . $dis . '>Hide now</button>' : '')
                   . '<button class="ak-b" name="td_do" value="restore" data-confirm="Bring it back on the site?" data-yes="Restore"' . $dis . '>Restore</button>'
                   . '<button class="ak-b go" name="td_do" value="uphold"' . $dis . '>Keep it down</button></form>';
        }
        $h .= tv_admin_fold((string) $r['title'], $status, $live ? ($r['status'] === 'open' ? 'bad' : '') : 'ok', '', $body, $openId === (int) $r['id']);
    }
    return '<div>' . $h . '</div><style>.ak-sk{display:flex;flex-wrap:wrap;gap:.4rem;margin:.6rem 0}.ak-sk .ak-in{flex:1 1 14rem;min-width:0}</style>';
}

/** Insights → Quarterly audit (decisions/0133): a random sample of published guides each quarter, read against the protected-information standard. */
function tv_admin_mod_audit(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/GuideAudits.php';
    require_once __DIR__ . '/../lib/Guides.php';
    $e = 'tv_w_e';
    if (!GuideAudits::ready($pdo)) return '<p data-tv-status="info" data-tv-label="Not ready:">The database update for the audit hasn\'t run yet: it runs within the hour, or open Database updates.</p>';
    [$can, $why] = Access::can('insights.audit.mark', ['min' => 'manager', 'depts' => ['con', 'ops']]);
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $msg = ''; $openId = (int) ($_POST['au'] ?? 0);
    if ($can && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['au_do']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try { GuideAudits::mark($pdo, $openId, (string) $_POST['au_do'], (int) (Auth::currentUserId() ?? 0), (string) ($_POST['note'] ?? '')); $msg = '<p data-tv-flash="ok">Marked ' . $e(mb_strtolower(GuideAudits::STATUSES[$_POST['au_do']])) . '.</p>'; }
        catch (Throwable $x) { $msg = '<p data-tv-flash="error">' . $e($x->getMessage()) . '</p>'; }
    }
    if ($can && isset($_POST['au_draw']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) { $n = GuideAudits::tick($pdo); $msg = '<p data-tv-flash="' . ($n ? 'ok' : 'error') . '">' . ($n ? $n . ' guides drawn.' : 'This quarter\'s sample is drawn already, or no guide is live.') . '</p>'; }
    $q = GuideAudits::quarter();
    $rows = GuideAudits::list($pdo, $q);
    $left = count(array_filter($rows, fn ($r) => $r['status'] === 'open'));
    $h = '<p class="ak-note">Each quarter a tenth of the published guides (at least ' . GuideAudits::MIN . ', at most ' . GuideAudits::MAX . ') are drawn at random. Read each against the standard: no way in to a sacred, burial or archaeological place (only an access point the managing authority offers), story-only places told without where they are, no private people, the nation\'s rules kept. '
       . 'The pre-check lists what to look at first. Mark it passed, fixed (after editing it), or take it down.' . ($can ? '' : ' <b>' . $e($why) . '</b>') . '</p>' . $msg;
    if (!$rows) return '<div>' . $h . '<p data-tv-status="info" data-tv-label="' . $e($q) . ':">The sample for this quarter is drawn by the takedowns job\'s next run.</p>'
        . ($can ? '<form method="post"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><button class="ak-b go" name="au_draw" value="1">Draw it now</button></form>' : '') . '</div>';
    $h .= '<p data-tv-status="' . ($left ? 'info' : 'ok') . '" data-tv-label="' . $e($q) . ':">' . ($left ? $left . ' of ' . count($rows) . ' still to read.' : 'All ' . count($rows) . ' read.') . '</p>';
    foreach ($rows as $r) {
        $p = Guides::place($pdo, (string) $r['place_key']);
        $flags = (array) (json_decode((string) $r['flags'], true) ?: []);
        $bad = count(array_filter($flags, fn ($f) => ($f[0] ?? '') === 'bad'));
        $status = (GuideAudits::STATUSES[$r['status']] ?? $r['status']) . ($r['status'] === 'open' ? ($bad ? ' · ' . $bad . ' to look at first' : ' · pre-check clear') : ' · ' . date('M j', strtotime((string) $r['checked_at'])));
        $body = '<p class="ak-note">' . ($p ? '<a class="underline" href="' . $e((string) $p['url']) . '" data-peek-frame>Read the guide</a> · ' : '') . '<a class="underline" href="/admin/explore/place_guides/' . Guides::num((string) $r['place_key']) . '">Edit it</a></p>';
        foreach ($flags as [$tone, $line]) $body .= '<p data-tv-status="' . ($tone === 'bad' ? 'bad' : 'info') . '" data-tv-label="' . ($tone === 'bad' ? 'Look:' : 'Note:') . '">' . $e((string) $line) . '</p>';
        if (!$flags) $body .= '<p data-tv-status="ok" data-tv-label="Pre-check:">Nothing flagged: read it through.</p>';
        if ($r['note'] !== '') $body .= '<p class="ak-note">Note: ' . $e((string) $r['note']) . '</p>';
        if ($r['status'] === 'open') {
            $dis = $can ? '' : ' disabled title="' . $e($why) . '"';
            $body .= '<form method="post" class="ak-sk"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="au" value="' . (int) $r['id'] . '">'
                   . '<input class="ak-in" name="note" maxlength="500" placeholder="What you checked or fixed" aria-label="Note">'
                   . '<button class="ak-b go" name="au_do" value="pass"' . $dis . '>Passed</button><button class="ak-b" name="au_do" value="fixed"' . $dis . '>Fixed</button>'
                   . '<button class="ak-b" name="au_do" value="unpublished" data-confirm="Take this guide down until someone publishes it again?" data-yes="Take it down" data-danger' . $dis . '>Take it down</button></form>';
        }
        $h .= tv_admin_fold($p ? (string) $p['name'] : (string) $r['place_key'], $status, $r['status'] === 'open' ? ($bad ? 'bad' : '') : 'ok', '', $body, $openId === (int) $r['id']);
    }
    return '<div>' . $h . '</div><style>.ak-sk{display:flex;flex-wrap:wrap;gap:.4rem;margin:.6rem 0}.ak-sk .ak-in{flex:1 1 14rem;min-width:0}</style>';
}

/** Insights → AI Assist: what members' asks cost us, live, where they come from (the source, for routing), free against
 *  credits, the follow-ups the crawler's research sent, and the subjects we didn't know yet. Counts and money only, never
 *  what anyone asked. */
function tv_admin_mod_aiassist(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/AiAssist.php';
    require_once __DIR__ . '/../lib/Thresholds.php';
    $e = 'tv_w_e';
    if (!AiAssist::ready($pdo)) return '<p data-tv-status="info" data-tv-label="Not ready:">The database update for AI Assist asks hasn\'t run yet: it runs within the hour, or open Database updates.</p>';
    $months = [date('Y-m'), date('Y-m', strtotime('first day of last month')), date('Y-m', strtotime('first day of -2 months'))];
    $m = in_array($_GET['m'] ?? '', $months, true) ? (string) $_GET['m'] : $months[0];
    $r = AiAssist::report($pdo, $m);
    $usd = function (float $v): string { return '$' . number_format($v, $v > 0 && $v < 0.01 ? 4 : 2); };   // always in dollars (Jason, 2026-11-23: "Cents vs dollars")
    $h = '<p class="ak-note">What members\' AI Assist asks cost us, as they happen (each ask\'s cost is what Claude charged for it, a follow-up added to its ask). '
       . 'Where each ask came from is its source, so we can see which places lead members to ask and route them. Members with a paid plan get '
       . (int) Thresholds::get($pdo, 'ai_free_asks') . ' free asks a day (Thresholds &amp; gates), then credits. Counts and money only: what anyone asked stays theirs.</p>';
    $h .= '<form method="get" class="ak-sk"><label class="ak-note">Month <select class="ak-in" name="m" onchange="this.form.submit()">';
    foreach ($months as $x) $h .= '<option value="' . $x . '"' . ($x === $m ? ' selected' : '') . '>' . date('F Y', strtotime($x . '-01')) . '</option>';
    $h .= '</select></label></form>';
    $h .= '<div class="ak-metrics">';
    foreach ([['Asks', number_format($r['asks'])], ['Members', number_format($r['members'])], ['Cost', $usd($r['usd'])], ['Per ask', $r['asks'] ? $usd($r['per_ask']) : '–'],
              ['Free asks cost', $usd($r['free_usd'])], ['Waiting on research', number_format($r['waiting'])], ['Follow-ups sent', number_format($r['sent'])]] as [$l, $v])
        $h .= '<div class="ak-metric tv-bleed"><b>' . $e($v) . '</b><span>' . $e($l) . '</span></div>';
    $h .= '</div>';
    if ($r['asks']) {
        $tone = $r['per_ask'] > 0.03 ? 'bad' : ($r['per_ask'] > 0.02 ? 'info' : 'ok');
        $h .= '<p data-tv-status="' . $tone . '" data-tv-label="Live cost:">' . $usd($r['per_ask']) . ' an ask on average, against the 1 to 2 cents we planned on'
            . ($tone === 'ok' ? '.' : ': look at the model for AI Assist in System Configuration → AI use, or the free asks in Thresholds &amp; gates.') . '</p>';
    } else {
        $h .= '<p data-tv-status="info" data-tv-label="' . $e(date('F', strtotime($m . '-01'))) . ':">No asks yet this month.</p>';
    }
    if ($r['by']) {
        $h .= '<div class="ak-aa"><table><thead><tr><th>Source</th><th>Asks</th><th>Members</th><th>Free</th><th>Credits</th><th>Not known yet</th><th>Followed up</th><th>Cost</th></tr></thead><tbody>';
        foreach ($r['by'] as $src => $b) {
            $h .= '<tr><td>' . $e(AiAssist::SOURCES[$src] ?? $src) . '</td><td>' . (int) $b['n'] . '</td><td>' . (int) $b['members'] . '</td><td>' . (int) $b['free'] . '</td><td>' . (int) $b['credit'] . '</td><td>' . (int) $b['unknown'] . '</td><td>' . (int) $b['fu'] . '</td><td>' . $usd((int) $b['c'] / 1e6) . '</td></tr>';
        }
        $h .= '</tbody></table></div>';
    }
    if ($r['unknown']) {
        $body = '<p class="ak-note">Subjects members asked about that our record didn\'t cover yet. Each went to the research queue; the member hears back when it learns something (within ' . AiAssist::FOLLOW_DAYS . ' days).</p><ul class="ak-note">';
        foreach ($r['unknown'] as $u) $body .= '<li>' . $e((string) $u['name']) . ' (' . (int) $u['n'] . ')</li>';
        $h .= tv_admin_fold('Asked about, not known yet', count($r['unknown']) . ' subject' . (count($r['unknown']) === 1 ? '' : 's'), 'info', '', $body . '</ul><p class="ak-note"><a class="underline" href="/admin/insights/topics">Topic index</a></p>', false);
    }
    return '<div>' . $h . '</div><style>.ak-aa{overflow-x:auto;margin:.6rem 0}.ak-aa table{width:100%;border-collapse:collapse;font-size:.85rem}.ak-aa th,.ak-aa td{padding:.35rem .5rem;border-bottom:1px solid #E8DCC6;text-align:right;white-space:nowrap}.ak-aa th:first-child,.ak-aa td:first-child{text-align:left;white-space:normal}.ak-sk{display:flex;flex-wrap:wrap;gap:.4rem;margin:.6rem 0}</style>';
}

/** Let's Explore → Live events (decisions/0133): what the events finder found, waiting ones first. Show one a person was
 *  asked to look at, or take one down (it stays down). Shaped in System Configuration → Content templates → Live events. */
function tv_admin_mod_events(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/LiveEvents.php';
    require_once __DIR__ . '/../lib/Guides.php';
    $e = 'tv_w_e';
    if (!LiveEvents::ready($pdo)) return '<p data-tv-status="info" data-tv-label="Not ready:">The database update for live events hasn\'t run yet: it runs within the hour, or open Database updates.</p>';
    [$can, $why] = Access::can('explore.events.edit', ['min' => 'staff', 'depts' => ['con', 'ops']]);
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $msg = '';
    if ($can && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['ev_to']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try { LiveEvents::setStatus($pdo, (int) ($_POST['ev'] ?? 0), (string) $_POST['ev_to'], (int) (Auth::currentUserId() ?? 0)); $msg = '<p data-tv-flash="ok">' . ($_POST['ev_to'] === 'removed' ? 'Taken down: it stays down.' : 'It shows now.') . '</p>'; }
        catch (Throwable $x) { $msg = '<p data-tv-flash="error">' . $e($x->getMessage()) . '</p>'; }
    }
    LiveEvents::expire($pdo);
    $tpl = LiveEvents::template($pdo); $tid = 0; try { $tid = (int) ((ContentTemplates::get($pdo, 'live_events') ?? [])['id'] ?? 0); } catch (Throwable $x) {}
    $h = '<p class="ak-note">What\'s coming up in each place with a live guide, found on its official event pages first and on the web for the rest. Each shows in the guide\'s Events section and on its venue\'s listing until the day after it ends. '
       . 'One whose name may mark a protected place waits here for a person. Take down anything that\'s wrong or shouldn\'t be shown; it stays down.' . ($can ? '' : ' <b>' . $e($why) . '</b>') . '</p>' . $msg;
    $h .= '<p data-tv-status="' . ($tpl['status'] === 'active' ? 'ok' : 'info') . '" data-tv-label="The finder:">' . ($tpl['status'] === 'active' ? 'Running: one place a run, each place every ' . (int) ($tpl['runs']['refresh_days'] ?? 7) . ' days, looking ' . (int) ($tpl['runs']['horizon_days'] ?? 120) . ' days ahead.' : 'Not running yet (the template is ' . $e(mb_strtolower(ContentTemplates::STATUSES[$tpl['status']] ?? $tpl['status'])) . '): shape it, preview and try it, then set it running.')
       . ($tid ? ' <a class="underline" href="/admin/config/content/' . $tid . '">The Live events template</a>' : '') . '</p>';
    $rows = LiveEvents::list($pdo);
    if (!$rows) return '<div>' . $h . '<p class="ak-note">No events yet.</p></div>';
    $dis = $can ? '' : ' disabled title="' . $e($why) . '"';
    foreach ($rows as $r) {
        $p = Guides::place($pdo, (string) $r['place_key']);
        $body = '<p class="ak-note">' . $e(LiveEvents::when($r)) . ((string) $r['venue_name'] !== '' ? ' · ' . ($r['venue_entity_id'] ? '<a class="underline" href="/listing/view.php?id=' . (int) $r['venue_entity_id'] . '" data-peek-frame>' . $e($r['venue_name']) . '</a>' : $e($r['venue_name'])) : '') . ((string) $r['price_text'] !== '' ? ' · ' . $e($r['price_text']) : '') . '</p>'
              . ((string) $r['summary'] !== '' ? '<p class="ak-note">' . $e($r['summary']) . '</p>' : '')
              . '<p class="ak-note"><a class="underline" href="' . $e($r['source_url']) . '" target="_blank" rel="noopener nofollow">The page that announces it</a>' . ($p ? ' · <a class="underline" href="' . $e((string) $p['url']) . '" data-peek-frame>' . $e($p['name']) . '\'s guide</a>' : '') . '</p>'
              . ($r['why'] !== '' ? '<p data-tv-status="info" data-tv-label="Why it waits:">' . $e($r['why']) . '</p>' : '')
              . '<form method="post" class="ak-sk"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="ev" value="' . (int) $r['id'] . '">'
              . ($r['status'] === 'held' ? '<button class="ak-b go" name="ev_to" value="live"' . $dis . '>Show it</button>' : '')
              . '<button class="ak-b" name="ev_to" value="removed" data-confirm="Take this event down? It stays down." data-yes="Take it down" data-danger' . $dis . '>Take it down</button></form>';
        $h .= tv_admin_fold((string) $r['title'], ($p ? $p['name'] . ' · ' : '') . date('M j', strtotime((string) $r['starts_on'])) . ' · ' . (LiveEvents::STATUSES[$r['status']] ?? $r['status']), $r['status'] === 'held' ? 'bad' : 'ok', '', $body, false);
    }
    return '<div>' . $h . '</div><style>.ak-sk{display:flex;flex-wrap:wrap;gap:.4rem;margin:.6rem 0}</style>';
}

/** System Configuration → Content templates (the AI Content Crawler's templates, decisions/0133): each kind of content the
 *  crawler and Claude make, shaped before anything is spent. Each opens its own page (/admin/config/content/<id>). */
function tv_admin_mod_content(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/ContentTemplates.php';
    $e = 'tv_w_e';
    if (!ContentTemplates::ready($pdo)) return '<p data-tv-status="info" data-tv-label="Not ready:">The database update for templates hasn\'t run yet: it runs within the hour, or open Database updates.</p>';
    ContentTemplates::seed($pdo);
    require_once __DIR__ . '/../lib/LegalTexts.php';
    try { LegalTexts::seed($pdo); } catch (Throwable $x) { error_log('legal seed: ' . $x->getMessage()); }
    $h = '<p class="ak-note">Each kind of content the crawler and Claude make has a template: what it applies to and when it runs, what it gathers (free sources first), what it writes and in what voice, how it\'s checked, how long it lasts and what it costs. '
       . 'Open one to shape it: Preview shows exactly what Claude would be sent and an estimate, for nothing; Try it writes one place without touching its guide. The exposure standard is the same in every template and changes only by a decision.</p>';
    $h .= '<div class="ak-aa"><table><thead><tr><th>Template</th><th>Makes</th><th>Status</th><th>Version</th><th>Saved</th></tr></thead><tbody>';
    foreach (ContentTemplates::all($pdo) as $t) {
        if (str_starts_with((string) $t['slug'], 'legal_')) continue;   // the legal texts have their own list, below
        $runs = ContentTemplates::RUNS[$t['slug']] ?? '';
        $h .= '<tr><td><a class="underline" style="color:#92400E" href="/admin/config/content/' . (int) $t['id'] . '">' . $e((string) $t['name']) . '</a><br><small style="color:#8A7355">' . $e($runs !== '' ? 'Run by ' . $runs : 'A copy being shaped: nothing runs it') . '</small></td>'
            . '<td>' . $e(ContentTemplates::KINDS[$t['kind']] ?? $t['kind']) . '</td><td>' . $e(ContentTemplates::STATUSES[$t['status']] ?? $t['status']) . '</td><td>' . (int) $t['version'] . '</td><td>' . $e(date('M j, Y', strtotime((string) $t['updated_at']))) . '</td></tr>';
    }
    $h .= '</tbody></table></div>';
    // the legal texts (decisions/0142 §1): Terms, Privacy, Seller Agreement, store policies, the checkout line
    $h .= '<h3 class="ak-h" style="margin-top:1.2rem">Legal texts</h3><p class="ak-note">Every legal text is a template: edit its sections, and each save keeps a version. Publishing gives it a version label and the date it takes effect; until then the site keeps today\'s wording. Drafts are for the attorney\'s review.</p>'
        . '<div class="ak-aa"><table><thead><tr><th>Text</th><th>Who accepts it</th><th>Published</th><th>Version</th><th>Saved</th></tr></thead><tbody>';
    foreach (LegalTexts::all($pdo) as $doc => $t) {
        $l = LegalTexts::live($pdo, $doc);
        $h .= '<tr><td><a class="underline" style="color:#92400E" href="/admin/config/content/' . (int) $t['id'] . '">' . $e(LegalTexts::DOCS[$doc][0]) . '</a></td><td><small>' . $e(LegalTexts::DOCS[$doc][2]) . '</small></td>'
            . '<td>' . ($l ? $e($l['label'] . ' · ' . date('M j, Y', strtotime((string) $l['effective']))) : 'Not yet') . '</td><td>' . (int) $t['version'] . '</td><td>' . $e(date('M j, Y', strtotime((string) $t['updated_at']))) . '</td></tr>';
    }
    $h .= '</tbody></table></div>';
    return '<div>' . $h . '</div><style>.ak-aa{overflow-x:auto;margin:.6rem 0}.ak-aa table{width:100%;border-collapse:collapse;font-size:.88rem}.ak-aa th,.ak-aa td{padding:.4rem .5rem;border-bottom:1px solid #E8DCC6;text-align:left;vertical-align:top}</style>';
}

/** System Configuration → Thresholds & gates (decisions/0133): one table, each value named by what it gates. */
function tv_admin_mod_thresholds(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/Thresholds.php';
    $e = 'tv_w_e';
    [$can, $why] = Access::can('config.thresholds.edit', ['min' => 'director', 'depts' => ['eng', 'ops']]);
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $msg = ''; $hit = (string) ($_POST['gate'] ?? '');
    if ($can && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $hit !== '' && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try {
            $uid = (int) (Auth::currentUserId() ?? 0);
            if (!empty($_POST['reset'])) { Thresholds::reset($pdo, $hit, $uid); $msg = '<p data-tv-flash="ok">' . $e(Thresholds::GATES[$hit][0]) . ': back to its default.</p>'; }
            else { $v = Thresholds::set($pdo, $hit, (string) ($_POST['value'] ?? ''), $uid); $msg = '<p data-tv-flash="ok">' . $e(Thresholds::GATES[$hit][0]) . ': now ' . $e((string) $v) . '.</p>'; }
        } catch (Throwable $x) { $msg = '<p data-tv-flash="error">' . $e($x->getMessage()) . '</p>'; }
    }
    $h = '<p class="ak-note">Every value that decides something on its own: what publishes by itself, what goes to a person, what a member may do. Numbers can be tuned here (a director); standards change only by a decision and are shown so the whole picture is in one place. '
       . 'The web search and Places allowances and the AI budget are in Services &amp; usage and AI use.' . ($can ? '' : ' <b>' . $e($why) . '</b>') . '</p>' . $msg;
    // the critical parts, each by the duty it carries (0171 §5's note, Jason 2026-11-19): a part's first failure trips at once
    require_once __DIR__ . '/../lib/ControlRegister.php';
    if ($can && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['cduty_proc']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try { $who = (string) (Auth::currentUser()['name'] ?? 'staff'); ControlRegister::setDuty($pdo, (string) $_POST['cduty_proc'], (string) ($_POST['cduty'] ?? ''), $who); $msg .= '<p data-tv-flash="ok">Saved: ' . $e((string) $_POST['cduty_proc']) . (($_POST['cduty'] ?? '') === '' ? ' is not critical now.' : ' carries ' . $e(ControlRegister::DUTIES[(string) $_POST['cduty']][0]) . '.') . '</p>'; }
        catch (Throwable $x) { $msg .= '<p data-tv-flash="error">' . $e($x->getMessage()) . '</p>'; }
        $h .= $msg; $msg = '';
    }
    $procs = ControlRegister::processes($pdo); $dm = ControlRegister::dutyMap($pdo);
    $rows = ''; uksort($procs, fn ($a, $b) => [!isset($dm[$a]), $a] <=> [!isset($dm[$b]), $b]);
    foreach ($procs as $pid => [$sys, $pn]) {
        $cur = (string) ($dm[$pid] ?? ''); $opts = '<option value="">Not critical</option>';
        foreach (ControlRegister::DUTIES as $dk => [$dn]) $opts .= '<option value="' . $dk . '"' . ($dk === $cur ? ' selected' : '') . '>' . $e($dn) . '</option>';
        $rows .= '<li class="ak-cd' . ($cur !== '' ? ' on' : '') . '"><span><b>' . $e($pn) . '</b> <small>' . $e($pid) . (((ControlRegister::CRITICAL[$pid] ?? '') !== $cur) ? ' · changed from its default' : '') . '</small></span>'
            . ($can ? '<form method="post" class="ak-sk"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="cduty_proc" value="' . $e($pid) . '"><select class="ak-in" name="cduty" aria-label="' . $e($pn) . ': its duty">' . $opts . '</select><button class="ak-b go">Save</button></form>'
                    : '<span class="ak-gv">' . $e($cur !== '' ? ControlRegister::DUTIES[$cur][0] : 'Not critical') . '</span>') . '</li>';
    }
    $dl = ''; foreach (ControlRegister::DUTIES as [$dn, $dw]) $dl .= '<li><b>' . $e($dn) . ':</b> ' . $e($dw) . '</li>';
    $h .= '<details class="ak-gate tv-bleed" id="critical-parts" style="margin-top:.6rem"><summary><b>Critical parts, by the duty each carries</b> <small>' . count($dm) . ' critical</small></summary>'
        . '<p class="ak-note">"Critical is that our site can perform its duties" (Jason, 2026-11-19). A critical part\'s first failure is a trip at once (Pulse, email, push) while the system keeps trying: if it fixes it, you get a Recovered notice with nothing to press; if its tries run out, an Escalation Notice, and only Resolve clears it. Any other part is a warning on the board while the system tries, and a trip only when it can\'t fix it.</p><ul class="ak-note">' . $dl . '</ul>'
        . '<ul class="ak-cds">' . $rows . '</ul></details>';
    $h .= '<div class="ak-gates">';
    foreach (Thresholds::GATES as $k => [$label, $kind, $def, $min, $max, $step, $unit, $note]) {
        $now = Thresholds::get($pdo, $k);
        $st = $kind === 'number' ? Thresholds::stored($pdo, $k) : null;
        $changed = $kind === 'number' ? $st !== null : ($kind === 'switch' && (string) $now !== (string) $def);
        $h .= '<div class="ak-gate tv-bleed" id="gate-' . $e($k) . '"><div class="ak-gh"><b>' . $e($label) . '</b>'   // #gate-<key>: an alarm's link lands on its setting
            . ($kind === 'rule' ? '<span class="ak-gr">Standard</span>' : '<span class="ak-gv">' . $e((string) $now) . ($unit !== '' ? ' <small>' . $e($unit) . '</small>' : '') . '</span>') . '</div>'
            . ($kind === 'rule' ? '<p class="ak-gt">' . $e((string) $def) . '</p>' : '')
            . '<p class="ak-note">' . $e($note) . ($changed ? ' Default ' . $e((string) $def) . ($st ? '; changed ' . $e((string) $st['at']) : '') . '.' : '') . '</p>';
        if ($kind !== 'rule' && $can) {
            $h .= '<form method="post" class="ak-sk"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="gate" value="' . $e($k) . '">'
                . '<input class="ak-in" name="value" type="number" inputmode="decimal" min="' . $e((string) $min) . '" max="' . $e((string) $max) . '" step="' . $e((string) $step) . '" value="' . $e((string) $now) . '" aria-label="' . $e($label) . '">'
                . '<button class="ak-b go">Save</button>' . ($changed ? '<button class="ak-b" name="reset" value="1" data-confirm="Back to its default (' . $e((string) $def) . ')?" data-yes="Reset">Reset</button>' : '') . '</form>';
        }
        $h .= '</div>';
    }
    return '<div>' . $h . '</div></div><style>.ak-gates{display:grid;gap:.5rem;margin-top:.6rem}.ak-gate{background:#FFFBF2;border:1px solid #EADFC4;border-radius:.6rem;padding:.65rem .75rem}'
        . '.ak-gh{display:flex;justify-content:space-between;gap:.6rem;align-items:baseline}.ak-gv{font-weight:700;color:#92400E;white-space:nowrap}.ak-gv small{font-weight:400;color:#8A7355}.ak-gr{font-size:.75rem;color:#5C4632;background:#F7EFDC;border-radius:999px;padding:.05rem .5rem}'
        . '.ak-gt{margin:.3rem 0 0}.ak-sk{display:flex;flex-wrap:wrap;gap:.4rem;margin:.5rem 0 0}.ak-sk .ak-in{flex:0 1 8rem;min-width:0}'
        . '.ak-cds{list-style:none;margin:.5rem 0 0;padding:0;display:grid;gap:2px}.ak-cd{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:.3rem .6rem;padding:.3rem .5rem;border-radius:.4rem}.ak-cd.on{background:#FBEFD8}.ak-cd small{color:#8A7355}.ak-cd .ak-sk{margin:0}'
        . '@media (max-width:767px){.ak-gates{gap:3px}.ak-gate{border-radius:.5rem}}</style>';
}

/** Get Local → Categories (decisions/0024): the weighted classifier's corrections to listings already live, and a tester. */
function tv_admin_mod_categories(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/CategoryClassifier.php';
    $e = 'tv_w_e';
    [$can, $why] = Access::can('local.categories.decide', ['min' => 'staff', 'depts' => ['ops', 'con', 'sales']]);
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $msg = '';
    $post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''));
    if ($can && $post && isset($_POST['cc_do'])) {
        try { CategoryClassifier::decide($pdo, (int) ($_POST['cc'] ?? 0), (string) $_POST['cc_do'], (int) (Auth::currentUserId() ?? 0)); $msg = '<p data-tv-flash="ok">' . ['take' => 'Category changed.', 'keep' => 'Kept as it was: it won\'t be suggested again.', 'undo' => 'Put back as it was.'][$_POST['cc_do']] . '</p>'; }
        catch (Throwable $x) { $msg = '<p data-tv-flash="error">' . $e($x->getMessage()) . '</p>'; }
    }
    [$auto, $review] = CategoryClassifier::lines($pdo);
    $h = '<p class="ak-note">The classifier reads a business\'s name, what it calls itself on its own site, what it offers and its description, and gives each category a confidence. '
       . 'For a new find, at ' . $auto . '% or more the category is set; under ' . $review . '% a person picks; between, the readiness rules decide. "Other" is never set by itself. '
       . 'For listings already live it changes a category only when it is sure (' . $auto . '%+) of another industry and the category the listing has barely fits; a better fit it isn\'t sure enough of comes here for you. It never touches a category an owner or our team set. '
       . 'The lines are in System Configuration → Thresholds &amp; gates.' . ($can ? '' : ' <b>' . $e($why) . '</b>') . '</p>' . $msg;
    // the tester
    $tName = trim((string) ($_POST['t_name'] ?? '')); $tDesc = trim((string) ($_POST['t_desc'] ?? ''));
    $h .= '<form method="post" class="ak-sk"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input class="ak-in" name="t_name" value="' . $e($tName) . '" placeholder="Try it: a business name" aria-label="Business name">'
        . '<input class="ak-in" name="t_desc" value="' . $e($tDesc) . '" placeholder="What it says about itself (optional)" aria-label="Description"><button class="ak-b go">See its category</button></form>';
    if ($post && $tName !== '') {
        $r = CategoryClassifier::classify(['name' => $tName, 'what' => ['description' => $tDesc, 'offerings' => [], 'types' => []]], CategoryClassifier::categories($pdo), 3);
        if (!$r) $h .= '<p data-tv-status="info" data-tv-label="No fit:">No category matches those words: a person would pick.</p>';
        foreach ($r as $i => $x) {
            $verdict = $i === 0 ? (CategoryClassifier::sure($pdo, $x) ? 'set by itself' : ($x['confidence'] >= $review ? 'the readiness rules decide' : 'a person picks')) : 'also fits';
            $h .= '<p data-tv-status="' . ($i === 0 && $x['confidence'] >= $auto ? 'ok' : 'info') . '" data-tv-label="' . $e($x['confidence'] . '%:') . '"><b>' . $e($x['name']) . '</b> (' . $e($x['group']) . ') · ' . $e($verdict) . '<br><small>' . $e(implode('; ', $x['why'])) . '</small></p>';
        }
    }
    if (!CategoryClassifier::ready($pdo)) return '<div>' . $h . '<p data-tv-status="info" data-tv-label="Not ready:">The database update for category corrections hasn\'t run yet: it runs within the hour, or open Database updates.</p></div>';
    $rows = fn (string $where, int $n) => $pdo->query("SELECT ch.*, e.name AS listing, o.name AS old_name, og.name AS old_group, c.name AS new_name, g.name AS new_group FROM category_changes ch JOIN entities e ON e.id = ch.entity_id
        LEFT JOIN categories o ON o.id = ch.old_category_id LEFT JOIN categories og ON og.id = o.parent_id JOIN categories c ON c.id = ch.new_category_id LEFT JOIN categories g ON g.id = c.parent_id WHERE $where ORDER BY ch.created_at DESC LIMIT $n")->fetchAll(PDO::FETCH_ASSOC);
    $card = function (array $r, array $acts) use ($e, $csrf, $can, $why): string {
        $b = '';
        foreach ($acts as [$do, $label, $go]) $b .= '<button class="ak-b' . ($go ? ' go' : '') . '" name="cc_do" value="' . $do . '"' . ($can ? '' : ' disabled title="' . $e($why) . '"') . '>' . $e($label) . '</button>';
        return '<div class="ak-cc tv-bleed"><div><a class="underline" href="/listing/view.php?id=' . (int) $r['entity_id'] . '" data-peek-frame><b>' . $e((string) $r['listing']) . '</b></a>'
            . '<p class="ak-ccw">' . $e((string) ($r['old_name'] ?? 'no category')) . ' <small>(' . $e((string) ($r['old_group'] ?? '')) . ')</small> → <b>' . $e((string) $r['new_name']) . '</b> <small>(' . $e((string) $r['new_group']) . ', ' . (int) $r['confidence'] . '%)</small></p>'
            . '<small class="ak-note">' . $e((string) $r['why']) . '</small></div>'
            . ($b !== '' ? '<form method="post" class="ak-ccb"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="cc" value="' . (int) $r['id'] . '">' . $b . '</form>' : '') . '</div>';
    };
    $sug = $rows("ch.status = 'suggested'", 200);
    $done = $rows("ch.status IN ('applied', 'taken')", 60);
    $h .= '<h3 class="ak-cch">For a person (' . count($sug) . ')</h3>';
    $h .= $sug ? implode('', array_map(fn ($r) => $card($r, [['take', 'Apply', true], ['keep', 'Keep it as it is', false]]), $sug)) : '<p data-tv-status="ok" data-tv-label="All clear:">Nothing waiting: the classifier checks listings as the listings job runs.</p>';
    $h .= '<h3 class="ak-cch">Changed (' . count($done) . ' most recent)</h3>';
    $h .= $done ? implode('', array_map(fn ($r) => $card($r, [['undo', 'Undo', false]]), $done)) : '<p class="ak-note">No changes yet.</p>';
    return '<div>' . $h . '</div><style>.ak-sk{display:flex;flex-wrap:wrap;gap:.4rem;margin:.6rem 0}.ak-sk .ak-in{flex:1 1 14rem;min-width:0}.ak-cch{font-weight:700;margin:1rem 0 .35rem}'
        . '.ak-cc{display:flex;flex-wrap:wrap;gap:.5rem 1rem;justify-content:space-between;align-items:center;background:#FFFBF2;border:1px solid #EADFC4;border-radius:.6rem;padding:.6rem .75rem;margin-top:3px}'
        . '.ak-cc>div{flex:1 1 20rem;min-width:0}.ak-ccw{margin:.15rem 0}.ak-ccw small{color:#8A7355}.ak-ccb{display:flex;gap:.35rem;flex-wrap:wrap}@media (max-width:767px){.ak-cc{border-radius:.5rem}}</style>';
}

/**
 * System Configuration → Stock library (decisions/0143): photos no one on the site made, for cards and heroes until
 * their owners add their own. Search a source and add a photo with its tags; choose the home page's doors; see where
 * stock may and may never go. The library itself is the list under this (the config/stock tool): retag or retire there.
 */
function tv_admin_mod_stock(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/StockLibrary.php';
    require_once __DIR__ . '/../ui/stock-ui.php';
    $e = 'tv_w_e';
    if (!StockLibrary::ready($pdo)) return '<p data-tv-status="info" data-tv-label="Not ready:">The database update for the stock library hasn\'t run yet: it runs within the hour, or open Database updates.</p>';
    StockLibrary::syncSlots($pdo);
    [$can, $why] = Access::can('config/stock.edit', ['min' => 'staff', 'depts' => ['con', 'ops', 'eng']]);
    $dis = $can ? '' : ' disabled title="' . $e($why) . '"';
    $api = '/admin/api/config/stock';
    $h = '<p class="ak-note">Photos <b>no one on Traversence made</b>, so cards and heroes look good until their owners add their own. They only ever go in <b>illustrative</b> spots, always labelled "Stock photo" with their credit, '
       . 'and a real photo takes over by itself. Hand-picked only: add one at a time. Only licences that allow commercial use and changes are offered; nothing that may show a sacred, burial or protected site.' . ($can ? '' : ' <b>' . $e($why) . '</b>') . '</p>';
    // the sources
    $opts = ''; $states = [];
    foreach (StockLibrary::sources() as [$k, $name, $miss]) {
        $opts .= '<option value="' . $e($k) . '"' . ($miss !== '' ? ' disabled' : '') . '>' . $e($name . ($miss !== '' ? ' (not set up)' : '')) . '</option>';
        $states[] = $miss === '' ? $name . ': ready' : $name . ': ' . $miss;
    }
    $h .= '<p data-tv-status="ok" data-tv-label="Sources:">' . $e(implode(' · ', $states)) . '. Pixabay\'s searches are kept 24 hours; every photo added is copied as WebP into our own storage.</p>';
    // search and add
    $h .= '<div data-stock-admin="' . $e($api) . '"' . ($can ? '' : ' data-stk-no="' . $e($why) . '"') . ' class="stk"><h3 class="ak-h">Find photos</h3>'
        . '<form class="stk-f" data-stk-search role="search"><select class="ak-in" name="source" aria-label="Source">' . $opts . '</select>'
        . '<input class="ak-in" type="search" name="q" required minlength="2" placeholder="What it shows: desert sunset, main street, farmers market…" aria-label="What it shows"><button class="ak-b go">Search</button></form>'
        . '<div class="stk-res" data-stk-results></div><button type="button" class="ak-b" data-stk-more hidden>More</button>';
    require_once __DIR__ . '/../lib/Guides.php';
    $h .= '<datalist id="stk-places">';
    try { foreach (Guides::tree($pdo) as [$k, $name, $tier]) if ($tier <= 5) $h .= '<option value="' . $e($name . ' (' . (Guides::TIERS[strtok($k, ':')][1] ?? 'Place') . ')') . '" data-key="' . $e($k) . '"></option>'; }
    catch (Throwable $x) { error_log('stock places: ' . $x->getMessage()); }
    $h .= '</datalist></div>';
    if (!$can) $h = str_replace('<button class="ak-b go">Search</button>', '<button class="ak-b go">Search</button><span class="ak-note"> (adding needs ' . $e($why) . ')</span>', $h);
    // the home page's doors
    $h .= '<h3 class="ak-h" style="margin-top:1.2rem">The home page\'s doors</h3><p class="ak-note">Each door picks a photo from the library by its subject, or shows the one you choose.</p><div class="stk-doors">';
    foreach (StockLibrary::DOORS as $k => [$label, $subject]) {
        $ch = StockLibrary::chosen($pdo, 'home_door', $k);
        $now = $ch ?: StockLibrary::pick($pdo, $subject, '', ['seed' => 'home_door:' . $k]);
        $h .= '<div class="stk-door tv-bleed"><span class="stk-dim"' . ($now ? ' style="background-image:url(\'' . $e($now['card']) . '\')"' : '') . '>' . ($now ? tv_stock_tag($now) : '<em>Nothing fits "' . $e($subject) . '" yet</em>') . '</span>'
            . '<b>' . $e($label) . '</b><small>' . ($ch ? 'Chosen' : 'Automatic: "' . $e($subject) . '"') . '</small><span class="stk-acts">'
            . '<button type="button" class="ak-b" data-stock-pick data-slot="home_door" data-owner="' . $e($k) . '" data-q="' . $e($subject) . '" data-post="' . $e($api) . '" data-title="' . $e('A photo for the ' . $label . ' door') . '"' . $dis . '>Choose</button>'
            . ($ch ? '<button type="button" class="ak-b" data-stock-clear data-slot="home_door" data-owner="' . $e($k) . '" data-post="' . $e($api) . '"' . $dis . '>Automatic</button>' : '') . '</span></div>';
    }
    $h .= '</div>';
    // where it may and may never go
    $ill = ''; $auth = '';
    foreach (StockLibrary::SLOTS as [$kind, $label]) { if ($kind === 'illustrative') $ill .= '<li>' . $e($label) . '</li>'; else $auth .= '<li>' . $e($label) . '</li>'; }
    $h .= '<h3 class="ak-h" style="margin-top:1.2rem">Where stock goes</h3><div class="stk-where"><div><p data-tv-status="ok" data-tv-label="Illustrative:">May show stock, labelled, until a real photo is added.</p><ul>' . $ill . '</ul></div>'
        . '<div><p data-tv-status="bad" data-tv-label="Authentic:">Never: these say "this is the real thing", and their pages don\'t offer stock at all.</p><ul>' . $auth . '</ul></div></div>';
    $later = ''; foreach (StockLibrary::LATER as [$name, $needs]) $later .= '<li><b>' . $e($name) . '</b>: ' . $e($needs) . '</li>';
    $h .= '<details class="stk-later"><summary>Sources for later (recorded, not built)</summary><ul>' . $later . '</ul><p class="ak-note">Each is one more adapter in app/lib/stock/ and one line in StockLibrary::SOURCES.</p></details>';
    $h .= '<script src="/js/tv-stock.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/tv-stock.js') . '" defer></script>'
        . '<style>.stk-f{display:flex;flex-wrap:wrap;gap:.4rem;margin:.5rem 0}.stk-f select{flex:0 1 13rem}.stk-f input{flex:1 1 14rem;min-width:0}'
        . '.stk-res{display:grid;grid-template-columns:repeat(auto-fill,minmax(15rem,1fr));gap:.6rem;margin:.6rem 0}'
        . '.stk-r{display:flex;flex-direction:column;border:1px solid #E8DCC6;border-radius:.7rem;overflow:hidden;background:#FFFBF2}.stk-r.off{opacity:.7}'
        . '.stk-r img{width:100%;aspect-ratio:16/10;object-fit:cover;background:#E3D3AC;display:block}.stk-t{display:grid;gap:.25rem;padding:.5rem .6rem .6rem;font-size:.85rem;overflow-wrap:anywhere}'
        . '.stk-t small{color:#5C4632}.stk-t a{color:#92400E;text-decoration:underline}.stk-why{color:#991B1B}.stk-addf{display:grid;gap:.4rem;margin-top:.4rem}.stk-addf label{display:grid;gap:.15rem;font-size:.82rem}'
        . '.stk-sc{border:0;padding:0;margin:0;display:grid;gap:.2rem;font-size:.82rem}'
        . '.stk-doors{display:grid;grid-template-columns:repeat(auto-fill,minmax(13rem,1fr));gap:.6rem}.stk-door{display:grid;gap:.25rem;border:1px solid #E8DCC6;border-radius:.7rem;padding:.5rem;background:#FFFBF2}'
        . '.stk-dim{position:relative;display:flex;align-items:center;justify-content:center;aspect-ratio:16/9;border-radius:.5rem;background:#E3D3AC center/cover no-repeat;color:#8A7355;font-size:.8rem;text-align:center;padding:.4rem}'
        . '.stk-acts{display:flex;gap:.3rem;flex-wrap:wrap}.stk-where{display:grid;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr));gap:.8rem}.stk-where ul{margin:.3rem 0 0 1.1rem;font-size:.88rem}'
        . '.stk-later{margin-top:1rem}.stk-later summary{cursor:pointer}.stk-later ul{font-size:.88rem;margin:.4rem 0 0 1.1rem}'
        . '@media (max-width:767px){.stk-res,.stk-doors{gap:3px}.stk-r,.stk-door{border-radius:.5rem}}</style>';
    return '<div>' . $h . '</div>';
}

/**
 * Pulse → Support inbox (Jason, 2026-11-08): opens the support mailbox's webmail already signed in, through cPanel's
 * API (Session::create_webmail_session_for_mail_user), so no mailbox password is typed or kept. The Founder, executives and
 * Service managers by default, or whoever System Configuration → Permissions gives "pulse.support_mail". Each opening is logged with who.
 * A full inbox inside the admin is in future-considerations.md.
 */
function tv_admin_mod_support_mail(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/CPanel.php';
    [$can, $why] = Access::can('pulse.support_mail', ['min' => 'manager', 'depts' => ['svc']]);
    $box = trim((string) (getenv('SUPPORT_EMAIL') ?: 'support@traversence.com'));
    [$local, $domain] = array_pad(explode('@', $box, 2), 2, '');
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $e = 'tv_w_e'; $msg = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['mail'] ?? '') === 'open' && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try {
            if (!$can) throw new RuntimeException($why);
            if (!CPanel::ready()) throw new RuntimeException('cPanel isn\'t connected: CPANEL_HOST, CPANEL_USER and CPANEL_TOKEN go in .env.');
            $d = (array) CPanel::uapi('Session', 'create_webmail_session_for_mail_user', ['login' => $local, 'domain' => $domain]);
            $session = (string) ($d['session'] ?? ''); $token = '/' . ltrim((string) ($d['token'] ?? ''), '/');
            if ($session === '' || $token === '/') throw new RuntimeException('cPanel didn\'t give a webmail session. Open webmail by hand for now.');
            $host = preg_replace('#^https?://|[:/].*$#', '', trim((string) getenv('CPANEL_HOST')));
            $me = Auth::currentUser();
            error_log('support inbox opened by staff user ' . (int) ($me['id'] ?? 0) . ' (' . $box . ')');
            // straight on to webmail: the session goes in a form post, never in an address that's kept in history
            return '<form id="sm-go" method="post" action="' . $e('https://' . $host . ':2096' . $token . '/login/') . '"><input type="hidden" name="session" value="' . $e($session) . '">'
                . '<p data-tv-status="info" data-tv-label="Opening:">the ' . $e($box) . ' inbox…</p><button class="ak-b go">Continue to the inbox</button></form>'
                . '<script>document.getElementById("sm-go").submit();</script>';
        } catch (Throwable $x) {
            $msg = '<p data-tv-flash="error">' . $e($x instanceof RuntimeException ? $x->getMessage() : 'That didn\'t work: try again.') . '</p>';
            if (!($x instanceof RuntimeException)) error_log('support inbox: ' . $x->getMessage());
        }
    }
    return $msg . '<div class="ak-card tv-bleed"><h3 class="ak-h" style="margin-top:0">' . $e($box) . '</h3>'
        . '<p class="ak-note">Read and answer the support mailbox in its webmail, already signed in: no password to type, and none kept here. It opens in a new tab.</p>'
        . '<form method="post" target="_blank" style="margin:.6rem 0 0"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="mail" value="open">'
        . '<button class="ak-b go"' . ($can ? '' : ' disabled title="' . $e($why) . '"') . '>Open the support inbox</button></form>'
        . (CPanel::ready() ? '' : '<p data-tv-status="bad" data-tv-label="Not connected:">cPanel\'s API token isn\'t in .env, so this can\'t sign in for you yet.</p>')
        . '<p class="ak-note">Who may open it: System Configuration → Permissions, "pulse.support_mail" (the Founder, executives, and managers in Service by default).</p></div>';
}

/**
 * System Configuration → Switches (decisions/0145 §6): each feature switch, who it's on for, and the kill switch. A
 * change takes effect at once, with no upload. Executives by default ("config.switches" in Permissions).
 */
function tv_admin_mod_switches(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/Switches.php';
    if (!Switches::ready($pdo)) return '<div class="ak-card">Switches are added by a database update that runs within the hour (or open Database updates).</div>';
    [$can, $why] = Access::can('config.switches', ['min' => 'executive', 'depts' => []]);
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $e = 'tv_w_e'; $msg = ''; $me = Access::staff();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['sw']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try {
            if (!$can) throw new RuntimeException($why);
            if ($_POST['sw'] === 'killall' || $_POST['sw'] === 'unkillall') { $n = Switches::killAll($pdo, $_POST['sw'] === 'killall', (int) ($me['user_id'] ?? 0)); $msg = '<p data-tv-flash="ok">' . ($_POST['sw'] === 'killall' ? 'Every switch is off for everyone (' . $n . ').' : 'Every switch is back to its setting.') . '</p>'; }
            else { Switches::set($pdo, (string) $_POST['sw'], (string) ($_POST['state'] ?? 'off'), (int) ($_POST['pct'] ?? 0), !empty($_POST['killed']), (string) ($_POST['note'] ?? ''), (int) ($me['user_id'] ?? 0)); $msg = '<p data-tv-flash="ok">Saved: it takes effect at once.</p>'; }
        } catch (Throwable $x) { $msg = '<p data-tv-flash="error">' . $e($x instanceof RuntimeException ? $x->getMessage() : 'That didn\'t work: try again.') . '</p>'; }
    }
    $off = $can ? '' : ' disabled title="' . $e($why) . '"';
    $h = $msg . '<p class="ak-note">Each new feature checks its switch, so it can be turned on or off here with no upload: first for staff and test accounts, then a share of members, then everyone. The kill switch turns it off for everyone at once.</p>';
    foreach (Switches::all($pdo) as $k => $s) {
        $h .= '<form method="post" class="ak-card tv-bleed" id="sw-' . $e($k) . '" style="margin-top:.5rem"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="sw" value="' . $e($k) . '">'
            . '<h3 class="ak-h" style="margin-top:0">' . $e($s['name']) . ' <small class="ak-note">' . $e($k) . '</small></h3><p class="ak-note">' . $e($s['about']) . '</p>'
            . ($s['killed'] ? '<p data-tv-status="bad" data-tv-label="Killed:">Off for everyone until the kill switch is lifted.</p>' : '')
            . '<label>On for <select name="state"' . $off . '>' . implode('', array_map(fn ($sk, $sl) => '<option value="' . $sk . '"' . ($s['state'] === $sk ? ' selected' : '') . '>' . $e($sl) . '</option>', array_keys(Switches::STATES), Switches::STATES)) . '</select></label> '
            . '<label>Share <input type="number" name="pct" min="0" max="100" value="' . (int) $s['share_pct'] . '" style="width:4.5rem"' . $off . '> %</label> '
            . '<label><input type="checkbox" name="killed" value="1"' . ($s['killed'] ? ' checked' : '') . $off . '> Kill switch</label> '
            . '<input type="text" name="note" value="' . $e($s['note']) . '" placeholder="Why (for the log)" data-spell style="width:min(18rem,100%)"' . $off . '> '
            . '<button class="ak-b go"' . $off . '>Save ' . $e($s['name']) . '</button>'
            . ($s['updated_at'] ? '<p class="ak-note">Last changed ' . $e(date('M j, g:i a', strtotime((string) $s['updated_at']))) . '.</p>' : '') . '</form>';
    }
    $h .= '<script>(function(){var t=location.hash&&document.getElementById(location.hash.slice(1));if(!t)return;var b=t.querySelector(\'.ak-foldt[aria-expanded="false"]\');if(b)b.click();t.style.boxShadow="0 0 0 3px #8FBCA8";t.style.borderRadius=".5rem";setTimeout(function(){t.scrollIntoView({block:"start"});},50);})();</script>';   // #sw-<key>: the admin search opens it here
    $h .= '<form method="post" style="margin-top:.8rem"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="sw" value="killall"><button class="ak-b" data-confirm="Turn every switch off for everyone?" data-yes="Turn them all off" data-danger' . $off . '>Kill all switches</button></form>'
        . '<form method="post" style="margin-top:.4rem"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="sw" value="unkillall"><button class="ak-b"' . $off . '>Lift every kill switch</button></form>';
    return $h;
}

/**
 * System Configuration → Test accounts (decisions/0145 §3, site-audit/test-accounts.md): one Founder-only button makes
 * every account the site's checks sign in as, and gives them new sign-ins. The sign-ins are shown once, here, as one
 * block for the GitHub secret CHECK_ACCOUNTS; they're never kept readable on the server.
 */
function tv_admin_mod_testaccounts(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/TestAccounts.php';
    if (!TestAccounts::ready($pdo)) return '<div class="ak-card">Test accounts are added by a database update that runs within the hour (or open Database updates).</div>';
    $me = Access::staff();
    $founder = $me && ($me['role'] ?? '') === 'owner';
    $why = 'Only the Founder can make test accounts or replace their sign-ins.';
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $e = 'tv_w_e'; $msg = ''; $block = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['ta'] ?? '') === 'make' && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try {
            if (!$founder) throw new RuntimeException($why);
            $r = TestAccounts::makeAll($pdo, (int) $me['id'], (int) $me['user_id']);
            $block = $r['block'];
            $msg = '<p data-tv-flash="ok">' . ($r['made'] ? 'Made: ' . $e(implode(', ', $r['made'])) . '. ' : '') . 'Every test account has a new sign-in.</p>';
        } catch (Throwable $x) { $msg = '<p data-tv-flash="error">' . $e($x instanceof RuntimeException && !($x instanceof PDOException) ? $x->getMessage() : 'That didn\'t work: try again.') . '</p>'; if (!($x instanceof RuntimeException) || $x instanceof PDOException) error_log('test accounts: ' . $x->getMessage()); }
    }
    $made = TestAccounts::made($pdo);
    $h = $msg;
    if ($block !== '') {
        $h .= '<div class="ak-card tv-bleed" style="border-color:#8FBCA8"><h3 class="ak-h" style="margin-top:0">The sign-ins, shown once</h3>'
            . '<p class="ak-note">Copy this into GitHub: <b>degraffics/traversence</b> → Settings → Secrets and variables → Actions → <b>CHECK_ACCOUNTS</b> (New repository secret, or Update). It isn\'t kept here: leave this page and it\'s gone. Lost or exposed? Press the button again for new ones.</p>'
            . '<details><summary class="ak-b">Show the sign-ins</summary><textarea id="ta-block" readonly rows="14" style="width:100%;font:12px ui-monospace,monospace;margin-top:.5rem" spellcheck="false">' . $e($block) . '</textarea>'
            . '<button type="button" class="ak-b go" onclick="var t=document.getElementById(\'ta-block\');navigator.clipboard.writeText(t.value).then(function(){window.TvAsk&&TvAsk.say(\'Copied: now paste it into the GitHub secret.\',{anchor:t})})">Copy</button></details></div>';
    }
    $rows = '';
    foreach (TestAccounts::MEMBERS as $k => [$name, $covers]) $rows .= '<tr><td>' . $e($name) . '</td><td><code>check+' . $e($k) . '@</code></td><td>' . $e($covers) . '</td><td>' . (isset($made['user|' . $k]) ? 'Made' : 'Not yet') . '</td></tr>';
    foreach (TestAccounts::STAFF as $k => [$name, $lv, $dep]) $rows .= '<tr><td>' . $e($name) . '</td><td>Staff login</td><td>' . $e(ucfirst($lv) . ($dep !== '' ? ' · ' . $dep : '')) . '</td><td>' . (isset($made['staff|' . $k]) ? 'Made' : 'Not yet') . '</td></tr>';
    $rows .= '<tr><td>' . $e(TestAccounts::LISTING) . '</td><td>Hidden listing</td><td>The business team above</td><td>' . (isset($made['listing|' . TestAccounts::LISTING]) ? 'Made' : 'Not yet') . '</td></tr>';
    $h .= '<p class="ak-note">The accounts the site\'s checks sign in as (site-audit/test-accounts.md). Test accounts are left out of counts, likes, emails, people search and billing (they pay in sandbox only), and the live site sends them nothing. The steward\'s group, the test client and the test vendor come with the next build.</p>'
        . '<form method="post" style="margin:.6rem 0"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="ta" value="make">'
        . '<button class="ak-b go" data-confirm="' . ($made ? 'Give every test account a new sign-in? The old ones stop working.' : 'Make the test accounts now?') . '" data-yes="' . ($made ? 'Replace sign-ins' : 'Create test accounts') . '"' . ($founder ? '' : ' disabled title="' . $e($why) . '"') . '>' . ($made ? 'Replace sign-ins' : 'Create test accounts') . '</button></form>'
        . '<div class="ak-card tv-bleed" style="overflow-x:auto"><table class="ak-tbl"><thead><tr><th>Account</th><th>Sign-in</th><th>Covers</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    return $h;
}


/**
 * System Configuration → Staging (decisions/0145 Phase 3, site-audit/phase-3-staging.md): the staging copy of the site at
 * staging.trvgo.us, where changes are tried before they reach anyone. Where it stands, and two Founder-only buttons:
 * Set up staging (makes it through cPanel's API, once; safe to press again) and Refresh staging (a fresh copy of live, with
 * people's details replaced; also weekly, by itself). Both answer through /admin/api/config/staging (js/admin-staging.js).
 * On staging itself: the email it held instead of sending.
 */
function tv_admin_mod_staging(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/StagingSite.php';
    $e = 'tv_w_e';
    $at = fn (?string $iso, string $tz = '') => $iso ? '<time datetime="' . $e(date('c', (int) strtotime($iso))) . '" data-tv-time' . ($tz !== '' ? ' data-tz="' . $e($tz) . '"' : '') . '>' . $e(date('M j, g:i a', (int) strtotime($iso))) . '</time>' : 'never';
    $row = fn (string $mark, string $tone, string $what, string $detail) => '<div class="ak-res"><b class="' . $tone . '">' . $mark . '</b><span>' . $what . '<small>' . $detail . '</small></span></div>';
    $js = '<script src="/js/tv-time.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/tv-time.js') . '" defer></script>';

    if (Staging::here()) {   // the staging copy: what it held
        require_once __DIR__ . '/../lib/StagingGate.php';
        $h = '<p data-tv-status="info" data-tv-label="Staging:">This is the staging copy (staging.trvgo.us), the live site in safe mode (decisions/0165): it acts like live, and nothing here reaches the real world. It\'s managed from the live site\'s System Configuration → Staging and the site manager at the top of each page.</p>';
        $hl = '';
        foreach (StagingGate::held($pdo, 50) as $x) if ($x['kind'] !== 'mail') $hl .= '<li>' . $e(ucfirst((string) $x['kind'])) . ': ' . $e((string) ($x['subject'] ?: $x['target'])) . ' <small class="ak-note">' . $e((string) $x['service']) . ' · ' . $e((string) $x['why']) . ' · ' . $at((string) $x['created_at']) . '</small></li>';
        $h .= '<h3 class="ak-h">Held here: calls and scripts</h3><p class="ak-note">What staging didn\'t let out (paid services, bulk pulls, anything the page\'s policy blocked), newest first. A service can be allowed for a test on the live site\'s Staging page; it\'s counted against live\'s caps.</p>'
           . ($hl !== '' ? '<ul class="ak-note">' . $hl . '</ul>' : '<p class="ak-note">Nothing held yet.</p>');
        $h .= '<h3 class="ak-h">Email held here</h3><p class="ak-note">Staging sends no email. Each message the site would have sent is kept here instead, newest first (the last 50); Send to me (in the site manager\'s Held) sends one to you, marked as from staging.</p>';
        $list = '';
        foreach (Staging::heldMail($pdo, 50) as $m)
            $list .= '<details class="ak-card tv-bleed" style="margin-top:.4rem"><summary><b>' . $e((string) $m['subject']) . '</b> <small class="ak-note">to ' . $e((string) $m['to_addr']) . ' · ' . $at((string) $m['created_at']) . ' · ' . $e((string) $m['via']) . '</small></summary>'
                   . '<pre style="white-space:pre-wrap;word-break:break-word;font:13px/1.45 ui-monospace,monospace;margin:.5rem 0 0">' . $e((string) $m['body_text']) . '</pre></details>';
        return $h . ($list !== '' ? $list : '<p class="ak-note">Nothing held yet.</p>') . $js;
    }

    [$can, $why] = StagingSite::canManage();
    $s = StagingSite::status($pdo);
    $setup = $s['setup']; $last = $s['last']; $run = $s['running'];
    if (!$setup) $state = ['info', 'Not set up:', 'staging.trvgo.us isn\'t made yet. Set up staging makes it, then copies the live site there.'];
    elseif (!empty($setup['missing'])) $state = ['bad', 'Needs setting up again:', 'staging\'s .env isn\'t in its folder. Press Set up staging: it fills in what\'s missing.'];
    elseif ($run) $state = ['info', 'Refreshing:', 'a fresh copy is going in. Staging is closed until it finishes.'];
    elseif ($last && empty($last['ok'])) $state = ['bad', 'Closed:', (string) ($last['message'] ?? 'the last refresh didn\'t finish. Press Refresh staging.')];
    elseif (!$last) $state = ['info', 'Set up:', 'staging.trvgo.us is made but hasn\'t been filled yet. Press Refresh staging.'];
    else $state = ['ok', 'Ready:', 'staging.trvgo.us is a copy of the live site from ' . date('M j', (int) strtotime((string) $last['at'])) . ', with people\'s details replaced by test values.'];
    $h = '<p class="ak-note">A copy of the whole site at <b>staging.trvgo.us</b>, with its own files and databases, where a change is tried on real data shapes and every kind of account before it reaches anyone. It sends no email, takes no real payment, isn\'t indexed by search engines and asks for its viewing password.</p>'
       . '<p data-tv-status="' . $state[0] . '" data-tv-label="' . $e($state[1]) . '">' . $e($state[2]) . '</p>';

    $facts = '';
    $facts .= $row('⌂', $setup ? 'ok' : '', 'Address', $setup ? '<a href="https://staging.trvgo.us/" target="_blank" rel="noopener">staging.trvgo.us</a> (it asks for its viewing password, which is in the CHECK_ACCOUNTS block); the trvgo.us router\'s copy at staging.trvgo.us/go/' : 'Not made yet');
    if ($setup) {
        $facts .= $row('▤', empty($setup['missing']) ? 'ok' : 'bad', 'Set up', (!empty($setup['at']) ? $at((string) $setup['at'], (string) ($setup['tz'] ?? '')) : 'not finished') . ' · folder ' . $e((string) ($setup['docroot'] ?? '–')) . ' · databases ' . $e(implode(', ', (array) ($setup['dbs'] ?? []))));
        if ($last) {
            $n = count((array) ($last['stripped'] ?? []));
            $facts .= $row(empty($last['ok']) ? '✗' : '✓', empty($last['ok']) ? 'bad' : 'ok', 'Last refresh', $at((string) $last['at'], (string) ($last['tz'] ?? ''))
                . (!empty($last['ok']) ? ' · ' . number_format((int) $last['tables']) . ' tables, ' . number_format((int) $last['rows']) . ' rows, ' . (int) ($last['files']['files'] ?? 0) . ' files · personal details replaced in ' . $n . ' tables' : ' · ' . $e((string) ($last['message'] ?? ''))));
            if ($n) {
                $list = '';
                foreach ((array) $last['stripped'] as $t => $cols) $list .= '<li><code>' . $e((string) $t) . '</code>: ' . $e(implode(', ', array_unique((array) $cols))) . '</li>';
                $facts .= '<details style="margin:.2rem 0 .4rem 1.6rem"><summary class="ak-note">What was replaced, by table</summary><ul class="ak-note" style="margin:.3rem 0 0">' . $list . '</ul></details>';
            }
        } else $facts .= $row('–', '', 'Last refresh', 'Not yet');
        $facts .= $row('↻', '', 'No weekly refresh', 'Since decisions/0165 staging keeps its work: it catches up from live instead (below). Start staging over is the button, when it\'s wanted.');
    }
    $facts .= $row('✓', 'ok', 'Safe by default there', 'Every outside effect held, sandboxed or paced (the held ledger, Held in the site manager; decisions/0165 §1) · a Content Security Policy on every page · payments refuse while a live key is in its .env · email held and readable on its own Staging page · QuickBooks, Stripe and Square in their sandboxes · SES records only · the crawler and paid AI off unless their switch is on (Switches → Staging) · not indexed (noindex, robots.txt) · behind its viewing password · a "Staging" mark on every page · files it stores kept apart, never changing a live file · Turnstile\'s test keys · email sign-in only.');
    $facts .= $row('↻', '', 'Releases', 'Every build goes onto staging first and its checks run there. Claude\'s builds, approved in chat, then go live by themselves (or after your review, by their own Publish); a zip uploaded on staging goes live with staging\'s own work, by Publish staging (below). System Configuration → <a href="/admin/config/releases">Releases</a> → Open staging takes you there past the viewing password, signed in as you, with the staging bar.' . (($pt = StagingSite::passTest($pdo)) !== '' ? ' Its pass rule: ' . $e($pt) . '.' : ''));
    $h .= '<div class="ak-card tv-bleed">' . $facts . '</div>';
    if ($setup && empty($setup['missing'])) { try { $h .= tv_admin_staging_mirror($pdo, $can, $why); } catch (Throwable $x) { error_log('staging panel: ' . $x->getMessage()); $h .= '<p data-tv-status="bad" data-tv-label="Staging against live:">couldn\'t be read (' . $e($x->getMessage()) . '). Press Set up staging again, or ask our team at support@traversence.com.</p>'; } }

    $off = $can ? '' : ' disabled title="' . $e($why) . '"';
    $h .= '<div class="ak-card tv-bleed" id="stg" data-csrf="' . $e((string) ($_SESSION['csrf_token'] ?? '')) . '"' . ($run ? ' data-stg-running="1"' : '') . '>'
        . '<p class="ak-note" style="margin-top:0">' . ($setup ? 'Set up staging again checks each part and fills in what\'s missing. It also gives every test account a new sign-in (shown once, for CHECK_ACCOUNTS), then refreshes staging.' : 'Set up staging makes staging.trvgo.us through cPanel (its folder, databases and logins, its own .env with passwords made on the server, its viewing password and DNS record), gives every test account a new sign-in, shows them once for the GitHub secret CHECK_ACCOUNTS, then copies the live site there.') . '</p>'
        . '<button type="button" class="ak-b go" data-stg="setup"' . $off . '>Set up staging</button> '
        . (function () use ($pdo, $setup, $run, $off, $can, $why, $e): string {   // Start staging over (decisions/0165 §3): a button only, saying what staging would lose, Publish offered first
            if (!$setup || !empty($setup['missing'])) return '<button type="button" class="ak-b" data-stg="refresh" disabled title="' . $e($can ? 'Set up staging first.' : $why) . '">Start staging over</button> ';
            if ($run) return '<button type="button" class="ak-b" data-stg="refresh"' . $off . '>Carry on starting over</button> ';
            $lo = ['changes' => 0, 'held' => 0, 'releases' => 0]; try { $lo = StagingSite::startOverLosses($pdo); } catch (Throwable) {}
            $ask = 'Start staging over? It copies live over staging and loses ' . $lo['changes'] . ' change' . ($lo['changes'] === 1 ? '' : 's') . ' made there, ' . $lo['held'] . ' held item' . ($lo['held'] === 1 ? '' : 's') . ' and the ' . $lo['releases'] . ' release' . ($lo['releases'] === 1 ? '' : 's') . ' waiting there (they\'d need putting on again). Publish first to keep them.';
            return '<button type="button" class="ak-b" data-stg="refresh" data-confirm="' . $e($ask) . '" data-yes="Start over" data-danger' . $off . '>Start staging over</button> ';
        })()
        // Open staging: there past its viewing password, signed in as you, with the staging bar (decisions/0145 Phase 5).
        // As one of the audit's test accounts: on Releases, where the choice of account is.
        . (function () use ($pdo, $setup, $run, $e): string {
            require_once __DIR__ . '/../lib/Switches.php';
            $why = !$setup || !empty($setup['missing']) ? 'Set up staging first.' : ($run ? 'Staging is being refreshed: open it once that\'s done (you get an email).' : (!Switches::on($pdo, 'releases') ? 'It comes with the Releases page (Switches → Releases page).' : ''));
            return $why === '' ? '<a class="ak-b" href="/admin/api/config/releases?action=enter&amp;id=0&amp;as=me&amp;to=%2F">Open staging</a>'
                               : '<span class="ak-b" aria-disabled="true" title="' . $e($why) . '" style="opacity:.55">Open staging</span>';
        })()
        // Check the way in (decisions/0159 step 1): when Open staging meets a password prompt, which layer asked
        . ' <button type="button" class="ak-b" data-stg="wayin"' . (($wOff = $setup && empty($setup['missing']) ? $off : ' disabled title="' . $e($can ? 'Set up staging first.' : $why) . '"') !== '' ? $wOff : ' title="Asks staging for a page several ways and says which part answered: when Open staging asks for a password, this finds where"') . '>Check the way in</button>'
        . ($can ? '' : '<p class="ak-note">' . $e($why) . '</p>')
        . '<div data-stg-bar role="progressbar" aria-label="Staging refresh" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" hidden style="margin-top:.75rem;height:10px;border-radius:5px;background:#F3E8D6;box-shadow:inset 0 0 0 1px #92400E33;overflow:hidden"><span style="display:block;height:100%;width:0;background:#8FBCA8;box-shadow:0 0 6px #8FBCA8;transition:width .4s"></span></div>'
        . '<p class="ak-note" data-stg-now hidden></p>'
        . '<p class="ak-note">A refresh carries on by itself on the server (Scheduled tasks → Staging refresh, carried on): you can leave this page, and we email you when it\'s done.</p>'
        . '<div data-stg-out></div></div>';
    return $h . $js . '<script src="/js/admin-staging.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/admin-staging.js') . '" defer></script>';
}

/**
 * Staging against live (decisions/0165 §2–§5), on System Configuration → Staging: the site manager's line, the two
 * switches (Keep staging caught up, Staging runs its own jobs; both shipped off), what's held, the conflicts, the preview
 * and Publish, the run, To send now and Roll back, staging's answer per service, the outside calls that skip the gate.
 */
function tv_admin_staging_mirror(PDO $pdo, bool $can, string $why): string
{
    require_once __DIR__ . '/../lib/StagingApi.php';
    $e = 'tv_w_e';
    $t = fn ($iso) => $iso ? '<time datetime="' . $e(date('c', is_int($iso) ? $iso : (int) strtotime((string) $iso))) . '" data-tv-time>' . $e(date('M j, g:i a', is_int($iso) ? $iso : (int) strtotime((string) $iso))) . '</time>' : 'not yet';
    $b = fn (string $act, string $label, array $args = [], string $confirm = '', bool $danger = false, bool $go = false, string $perm = '') => '<button type="button" class="ak-b' . ($go ? ' go' : '') . '" data-stg2="' . $e($act) . '"'
        . ($args ? ' data-args="' . $e((string) json_encode($args)) . '"' : '') . ($confirm !== '' ? ' data-confirm="' . $e($confirm) . '" data-yes="' . $e($label) . '"' . ($danger ? ' data-danger' : '') : '')
        . ($perm !== '' && !Releases::can($perm)[0] ? ' disabled title="' . $e(Releases::can($perm)[1]) . '"' : '') . '>' . $e($label) . '</button> ';
    $s = StagingApi::summary($pdo);
    $h = '<h3 class="ak-h" id="against">Staging against live</h3><p data-tv-status="' . ($s['conflicts'] ? 'bad' : 'info') . '" data-tv-label="Now:">' . $e(StagingApi::line($s)) . ($s['caught_up'] ? ' · caught up ' : '') . '</p>';
    if ($s['caught_up']) $h = substr($h, 0, -4) . $t((int) $s['caught_up']) . '</p>';
    $fo = $can ? '' : ' disabled title="' . $e($why) . '"';
    // the two switches
    $h .= '<div class="ak-card tv-bleed"><p class="ak-note" style="margin-top:0"><b>Keep staging caught up</b> ' . ($s['mirror'] ? '(on)' : '(off)') . ': live\'s changes come into staging within a minute (live notes each change in a small outbox beside it; a job sends it), staging\'s own work is kept, and a row both changed waits on the conflicts list. A full check runs nightly. Turning it on starts staging over once, so both sides match first. Until it\'s on, Publish carries releases\' code and migrations only.</p>'
        . ($s['mirror'] ? '<button type="button" class="ak-b" data-stg2="mirror" data-args="{&quot;on&quot;:0}" data-confirm="Stop keeping staging caught up? Live\'s outbox comes off at once; staging keeps what it has." data-yes="Stop"' . $fo . '>Stop keeping caught up</button>'
                        : '<button type="button" class="ak-b go" data-stg2="mirror" data-args="{&quot;on&quot;:1}" data-confirm="Keep staging caught up? Staging starts over once (a fresh copy of live, a few minutes; anything done on staging since its last copy is lost), then keeps caught up." data-yes="Start"' . $fo . '>Keep staging caught up</button>')
        . '<p class="ak-note"><b>Staging runs its own jobs</b> ' . ($s['jobs'] ? '(on)' : '(off)') . ': staging\'s copy of each scheduled job, on staging\'s data under the gate (jobs that only talk to the outside world, like email setup and domains, never run there).</p>'
        . '<button type="button" class="ak-b" data-stg2="jobs" data-args="{&quot;on&quot;:' . ($s['jobs'] ? 0 : 1) . '}"' . $fo . '>' . ($s['jobs'] ? 'Stop staging\'s jobs' : 'Run staging\'s jobs') . '</button>';
    if ($s['live_keys']) $h .= '<p data-tv-status="bad" data-tv-label="Payments are off on staging:">a live payment key is in its .env (' . $e(implode(', ', $s['live_keys'])) . '). Press Set up staging again to write its .env without it.</p>';
    if ($s['ungated'] !== null) $h .= '<p data-tv-status="' . ($s['ungated'] ? 'bad' : 'ok') . '" data-tv-label="Outside calls that skip the gate:">' . ($s['ungated'] ? (int) $s['ungated'] . ' in staging\'s code (the nightly scan; a new one is refused by the zip check). Staging\'s .env holds no key for the paid ones, so they fail there rather than leave.' : 'none in staging\'s code (the nightly scan).') . '</p>'
        . ($s['ungated'] ? '<details><summary class="ak-note">Where they are</summary><ul class="ak-note">' . implode('', array_map(fn ($x) => '<li><code>' . $e((string) $x[0]) . ':' . (int) $x[1] . '</code> ' . $e((string) $x[2]) . ' (' . $e((string) $x[3]) . ')</li>', $s['ungated_list'])) . '</ul></details>' : '');
    $h .= '</div>';
    // releases on staging
    $rl = '';
    foreach ($s['releases'] as $r) $rl .= '<li>' . ($r['ok'] ? '✓' : '✗') . ' #' . (int) $r['id'] . ' ' . $e($r['title']) . ($r['replaced'] ? ' <small class="ak-note">(' . count($r['replaced']) . ' of its files replaced by a later one)</small>' : '') . '</li>';
    $h .= '<div class="ak-card tv-bleed"><h4 style="margin-top:0">Uploads waiting on staging (' . count($s['releases']) . ')</h4>' . ($rl !== '' ? '<ul class="ak-note">' . $rl . '</ul>' : '<p class="ak-note">None: zips uploaded on staging wait here for Publish staging. Claude\'s builds go live on their own (Releases).</p>') . '</div>';
    // conflicts
    $cf = StagingApi::conflictsView();
    $ch = '';
    foreach ($cf as $x) {
        $rows = '';
        foreach ((array) $x['clash'] as $f => $v) $rows .= '<tr><td>' . $e($f === '*' ? '(the whole row)' : (string) $f) . '</td><td>' . $e((string) ($v[0] ?? '—')) . '</td><td>' . $e((string) ($v[1] ?? '—')) . '</td><td>' . $e((string) ($v[2] ?? '—')) . '</td></tr>';
        $a = ['realm' => $x['realm'], 'tbl' => $x['tbl'], 'k' => $x['k']];
        $ch .= '<div style="border-top:1px solid #E8DCC2;padding:.4rem 0"><b>' . $e($x['kind'] . ' · ' . $x['tbl'] . ' #' . str_replace(chr(31), ' · ', (string) $x['k'])) . '</b> <small class="ak-note">' . $e((string) $x['why']) . ' · found ' . $t((string) $x['found_at']) . '</small>'
            . '<table class="ak-tbl"><tr><th>Field</th><th>Base</th><th>Staging</th><th>Live</th></tr>' . $rows . '</table>'
            . $b('settle', 'Keep live', $a + ['choice' => 'live'], '', false, false, 'releases.publish') . $b('settle', 'Use staging', $a + ['choice' => 'staging'], '', false, true, 'releases.publish') . '</div>';
    }
    $h .= '<div class="ak-card tv-bleed"><h4 style="margin-top:0">Conflicts (' . count($cf) . ')</h4>' . ($ch !== '' ? $ch . $b('keeplive', 'Keep live for the rest', [], 'Keep live\'s for every conflict left?', false, false, 'releases.publish') : '<p class="ak-note">None: nothing both sides changed two ways.</p>') . '</div>';
    // the preview and Publish
    $pv = $s['preview']; $run = $s['run'];
    $ph = '<div class="ak-card tv-bleed"><h4 style="margin-top:0">Publish staging</h4><p class="ak-note">What you made and changed on staging, all at once (Jason, choice 1): the migrations of any zip uploaded there, then staging\'s data merged into live, then those zips\' files, then the checks. Claude\'s builds don\'t wait for this: they go live on their own. Live stays open; a row live changes meanwhile is a late conflict, left as live has it. A failure while writing is undone at once, last write first. Staging\'s views, counts, test data, money and stand-ins for people\'s details are never carried; held effects are never sent by Publish.</p>';
    if ($run) $ph .= '<p data-tv-status="' . (in_array($run['state'], ['undone', 'stopped'], true) ? 'bad' : ($run['state'] === 'done' ? 'ok' : 'info')) . '" data-tv-label="' . $e(['running' => 'Publishing:', 'undoing' => 'Undoing:', 'done' => 'Published:', 'undone' => 'Stopped and undone:', 'stopped' => 'Stopped:', 'rolled_back' => 'Rolled back:'][$run['state']] ?? 'Publish:') . '">'
        . $e($run['message'] !== '' ? $run['message'] : 'step ' . ($run['pos'] + 1) . ' of ' . $run['of'] . ', ' . $run['step']) . '</p>'
        . ($run['log'] ? '<details><summary class="ak-note">Its steps</summary><ul class="ak-note">' . implode('', array_map(fn ($l) => '<li>' . $e((string) $l) . '</li>', $run['log'])) . '</ul></details>' : '')
        . ($run['left'] ? '<details open><summary class="ak-note">Left as live has them (changed on live since)</summary><ul class="ak-note">' . implode('', array_map(fn ($l) => '<li>' . $e((string) $l) . '</li>', $run['left'])) . '</ul></details>' : '');
    if ($pv) {
        $rows = '';
        foreach ((array) $pv['counts'] as $k => $c) $rows .= '<tr><td>' . $e((string) $k) . '</td><td>' . (int) ($c['added'] ?? 0) . '</td><td>' . (int) ($c['changed'] ?? 0) . '</td><td>' . (int) ($c['removed'] ?? 0) . '</td></tr>';
        $nc = ''; foreach ((array) $pv['not_carried'] as $k => $n) $nc .= '<li>' . $e((string) $k) . ': ' . (int) $n . '</li>';
        $ph .= '<p class="ak-note">The preview, worked out ' . $t((string) $pv['at']) . ': ' . $e(StagingApi::previewLine($pv)) . '</p>'
            . ($rows !== '' ? '<table class="ak-tbl"><tr><th>What</th><th>Added</th><th>Changed</th><th>Removed</th></tr>' . $rows . '</table>' : '')
            . ($nc !== '' ? '<details><summary class="ak-note">Not carried, and why</summary><ul class="ak-note">' . $nc . '</ul></details>' : '')
            . ($pv['unknown'] ? '<p data-tv-status="info" data-tv-label="Record numbers not in the register:">' . $e(implode(', ', array_slice($pv['unknown'], 0, 12))) . (count($pv['unknown']) > 12 ? ' and ' . (count($pv['unknown']) - 12) . ' more' : '') . '. A publish that would move a number one of them holds stops and names it.</p>' : '');
    }
    $busy = $run && in_array($run['state'], ['running', 'undoing'], true);
    $fresh = $pv && strtotime((string) $pv['at']) > time() - 15 * 60 && !$pv['blocking'];
    $ph .= $b('preview', $pv ? 'Preview again' : 'Preview', [], '', false, false, 'releases.publish');
    if (!$busy && $fresh) $ph .= $b('publish', 'Publish', [], 'Publish staging to live?', false, true, 'releases.publish');
    if ($run && $run['state'] === 'stopped') $ph .= $b('carryon', 'Carry on', [], '', false, true, 'releases.publish');
    if ($busy) $ph .= $b('carry', 'Carry on now', [], '', false, false, 'releases.publish');
    if ($s['last'] && strtotime((string) $s['last']['until']) > time() && (!$run || $run['state'] === 'done')) $ph .= $b('rollback', 'Roll back the last publish', [], 'Roll back the whole publish of ' . date('M j', (int) strtotime((string) $s['last']['at'])) . '? Rows live changed since stay as live has them.', true, false, 'releases.rollback');
    $ph .= '<p class="ak-note">Or schedule it, inside the release window (the preview is done again at the time, and it stops if a new conflict appears)' . ($s['scheduled'] ? ': scheduled for ' . $t((string) $s['scheduled']['at']) : '') . '.</p>'
        . '<input type="datetime-local" data-stg-when aria-label="When staging goes live"> ' . $b('schedule', 'Schedule', [], '', false, false, 'releases.schedule') . ($s['scheduled'] ? $b('unschedule', 'Take off the schedule', [], '', false, false, 'releases.schedule') : '');
    $h .= $ph . '</div>';
    // To send now
    $ts = StagingPublish::state($pdo, 'to_send');
    $items = $ts ? (array) $ts['items'] : [];
    if ($items) {
        $li = '';
        foreach (array_slice($items, 0, 50) as $x) $li .= '<li>' . $e((string) $x['subject']) . ' <small class="ak-note">to ' . $e((string) $x['target']) . ' · ' . $t((string) $x['created_at']) . '</small> '
            . $b('sendnow', 'Send', ['id' => (int) $x['id'], 'send' => 1], 'Send it to ' . $x['target'] . ' from the live site?', false, true, 'releases.publish') . $b('sendnow', 'Drop', ['id' => (int) $x['id'], 'send' => 0], '', false, false, 'releases.publish') . '</li>';
        $h .= '<div class="ak-card tv-bleed"><h4 style="margin-top:0">To send now (' . count($items) . ')</h4><p class="ak-note">Held on staging and never sent by Publish: each can go now from the live site to its real recipient, or be dropped.</p><ul>' . $li . '</ul></div>';
    }
    // held
    $hl = '';
    foreach (StagingApi::heldView(30) as $x) $hl .= '<li>' . $e(ucfirst((string) $x['kind'])) . ': ' . $e((string) ($x['subject'] ?: $x['target'])) . ' <small class="ak-note">' . ($x['kind'] === 'mail' ? 'to ' . $e((string) $x['target']) . ' · ' : '') . $e((string) $x['why']) . ' · ' . $t((string) $x['created_at']) . ($x['replayed_at'] ? ' · done: ' . $e((string) $x['replayed_to']) : '') . '</small>'
        . ($x['kind'] === 'mail' && !$x['replayed_at'] ? ' ' . $b('sendme', 'Send to me', ['id' => (int) $x['id']]) : '') . '</li>';
    $h .= '<div class="ak-card tv-bleed"><h4 style="margin-top:0">Held on staging (' . (int) $s['held'] . ')</h4><p class="ak-note">What staging didn\'t let out, newest first. Send to me sends a held email to you, marked as from staging, never to its real recipient.</p>' . ($hl !== '' ? '<ul>' . $hl . '</ul>' : '<p class="ak-note">Nothing held yet.</p>') . '</div>';
    // each service's answer on staging
    require_once __DIR__ . '/../lib/OutsideServices.php';
    $ans = '';
    foreach (OutsideServices::COMPANIES as $k => $c) $ans .= '<tr><td>' . $e((string) $c[0]) . '</td><td>' . $e(StagingGate::WORDS[StagingGate::answer((string) $k)]) . '</td></tr>';
    $h .= '<details class="ak-card tv-bleed"><summary><b>Each outside service on staging</b> <small class="ak-note">(the service register\'s staging column)</small></summary><table class="ak-tbl"><tr><th>Service</th><th>On staging</th></tr>' . $ans . '</table></details>';
    return '<div id="stg2" data-csrf="' . $e((string) ($_SESSION['csrf_token'] ?? '')) . '">' . $h . '</div>';
}

/**
 * System Configuration → Decisions (Jason, 2026-11-12: "can we access the adr within the system and edit them to git. this
 * could be an adr manager?"): the decision records of degraffics/traversence, listed, read, edited with a preview, a note
 * added, a status changed, a new one started at the next number. Saves go straight to main (Jason: "Straight to main"),
 * through app/lib/DocsRepo.php with GITHUB_DOCS_TOKEN; with no key everything reads and the page says how to add it.
 * Owners and executives save; other staff who open System Configuration read.
 */
function tv_admin_mod_decisions(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/DocsRepo.php';
    [$can, $why] = Access::can('config.decisions', ['min' => 'executive', 'depts' => []]);
    if ($can && !DocsRepo::canSave()) { $can = false; $why = 'Saving needs the GitHub key: GITHUB_DOCS_TOKEN in .env.'; }
    $e = 'tv_w_e'; $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $me = Access::staff();
    $who = trim((string) ($me['name'] ?? '')) ?: 'staff';
    $base = '/admin/config/decisions';
    $num = preg_match('/^\d{4}$/', (string) ($_GET['d'] ?? '')) ? (string) $_GET['d'] : '';
    $mode = in_array($_GET['do'] ?? '', ['edit', 'note', 'status'], true) ? (string) $_GET['do'] : '';
    $new = !empty($_GET['new']) || ($_GET['new'] ?? '') === 'config/decisions';
    $off = $can ? '' : ' disabled title="' . $e($why) . '"';
    $msg = ''; $keep = null; $preview = ''; $saved = '';
    $hide = '<style>.ak-list[data-ak-table*="decisions"]{display:none}.dm-doc{line-height:1.6;color:#3E2B1F}.dm-doc .dm-h{font-family:var(--tv-font-head,ui-serif,Georgia,serif);color:#3E2B1F;margin:1.1rem 0 .4rem}'
        . '.dm-doc h2.dm-h{font-size:1.35rem}.dm-doc h3.dm-h{font-size:1.1rem}.dm-doc p,.dm-doc li{font-size:.95rem}.dm-doc code{background:#F3EAD3;border-radius:.25rem;padding:0 .25rem;font-size:.85em}'
        . '.dm-doc pre{background:#2B1D14;color:#FFF8EA;padding:.7rem;border-radius:.5rem;overflow:auto}.dm-doc pre code{background:none;color:inherit}.dm-doc blockquote{border-left:3px solid #CBB98D;margin:.5rem 0;padding:.1rem .8rem;color:#5C4632}'
        . '.dm-doc a{color:#92400E}.dm-tw{overflow-x:auto}.dm-ta{width:100%;box-sizing:border-box;font:13px/1.5 ui-monospace,Menlo,monospace;border:1px solid #CBB98D;border-radius:.5rem;padding:.6rem;background:#FFFBF2;color:#2B1D14}'
        . '.dm-row{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin:.6rem 0}.dm-in{border:1px solid #CBB98D;border-radius:.5rem;padding:.45rem .6rem;background:#FFFBF2;font:inherit;width:min(32rem,100%)}'
        . '.dm-pv{border:1px dashed #B8863B;border-radius:.5rem;padding:.4rem 1rem;margin:.6rem 0;background:#FFFBF2}</style>';

    // ---- saving ------------------------------------------------------------------------------------------------------
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['dm']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $act = (string) $_POST['dm']; $isPreview = !empty($_POST['preview']);
        try {
            if ($act === 'new') {
                $title = trim((string) ($_POST['title'] ?? ''));
                $n = DocsRepo::nextNumber();
                $text = '# ADR ' . $n . ': ' . $title . "\n\n**Status:** Proposed (" . $who . ', ' . date('Y-m-d') . ", in the admin)\n\n## Context\n\n" . trim((string) ($_POST['context'] ?? ''))
                    . "\n\n## Decision\n\n" . trim((string) ($_POST['decision'] ?? '')) . "\n\n## Consequences\n\n" . trim((string) ($_POST['consequences'] ?? '')) . "\n";
                if ($isPreview) { $preview = DocsRepo::html($text); $keep = $_POST; }
                else {
                    if (!$can) throw new RuntimeException($why);
                    if ($title === '' || trim((string) ($_POST['decision'] ?? '')) === '') throw new RuntimeException('A new decision needs its title and what was decided.');
                    $file = 'decisions/' . $n . '-' . DocsRepo::slug($title) . '.md';
                    $r = DocsRepo::save($file, $text, 'Decision ' . $n . ': ' . $title . ' (new, in the admin by ' . $who . ')');
                    if (!$r['ok']) { $keep = $_POST; throw new RuntimeException($r['error']); }
                    DocsRepo::noteAdded($n, $title, $file);
                    // the README's count of decisions follows ("numbered `0001`–`0156`")
                    $rd = DocsRepo::raw('README.md', true);
                    if ($rd !== null && preg_match('/`0001`–`(\d{4})`/u', $rd, $mm) && (int) $mm[1] < (int) $n) DocsRepo::save('README.md', str_replace($mm[0], '`0001`–`' . $n . '`', $rd), 'README: decisions numbered to ' . $n, $rd);
                    tv_admin_dm_log($pdo, 'decision_new', $n . ' ' . $title);
                    $saved = $r['url']; $num = $n; $new = false; $mode = '';
                }
            } else {
                $d = DocsRepo::one((string) ($_POST['num'] ?? ''));
                if (!$d || $d['text'] === null) throw new RuntimeException('That decision couldn\'t be read from GitHub. Try again in a minute.');
                $startedFrom = (string) base64_decode((string) ($_POST['base'] ?? ''), true);
                $text = (string) $d['text']; $what = '';
                if ($act === 'edit') { $text = str_replace("\r\n", "\n", (string) ($_POST['text'] ?? '')); $what = trim((string) ($_POST['why'] ?? '')) ?: 'edited'; }
                elseif ($act === 'note') {
                    $note = trim((string) ($_POST['note'] ?? ''));
                    if ($note === '') throw new RuntimeException('Write the note first.');
                    $text = rtrim($startedFrom !== '' ? $startedFrom : $text) . "\n\n**Note (" . date('Y-m-d') . ', ' . $who . '):** ' . $note . "\n";
                    $what = 'a note';
                } elseif ($act === 'status') {
                    $st = (string) ($_POST['status'] ?? ''); $by = preg_match('/^\d{1,4}$/', (string) ($_POST['by'] ?? '')) ? sprintf('%04d', (int) $_POST['by']) : '';
                    $label = ['proposed' => 'Proposed', 'accepted' => 'Accepted', 'replaced' => 'Replaced' . ($by !== '' ? ' by `decisions/' . $by . '`' : ''), 'rejected' => 'Rejected', 'parked' => 'Parked'][$st] ?? '';
                    if ($label === '') throw new RuntimeException('Choose a status.');
                    $line = '**Status:** ' . $label . ' (' . $who . ', ' . date('Y-m-d') . ')';
                    $src = $startedFrom !== '' ? $startedFrom : $text;
                    $text = preg_match('/^\*\*Status:\*\*.*$/m', $src) ? (string) preg_replace('/^\*\*Status:\*\*.*$/m', str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $src, 1) : (string) preg_replace('/^(# .*\n)/', "$1\n" . str_replace(['\\', '$'], ['\\\\', '\\$'], $line) . "\n", $src, 1);
                    $what = 'status: ' . strtolower($label);
                }
                if ($isPreview) { $preview = DocsRepo::html($text); $keep = $_POST + ['_text' => $text]; }
                else {
                    if (!$can) throw new RuntimeException($why);
                    $r = DocsRepo::save($d['file'], $text, 'Decision ' . $d['num'] . ': ' . $what . ' (in the admin by ' . $who . ')', $startedFrom !== '' ? $startedFrom : null);
                    if (!$r['ok']) { $keep = $_POST; throw new RuntimeException($r['error']); }
                    tv_admin_dm_log($pdo, 'decision_' . $act, $d['num'] . ' ' . $what);
                    $saved = $r['url']; $num = $d['num']; $mode = '';
                }
            }
        } catch (Throwable $x) { $msg = '<p data-tv-flash="error">' . $e($x instanceof RuntimeException ? $x->getMessage() : 'That didn\'t work: try again.') . '</p>'; if (!($x instanceof RuntimeException)) error_log('decisions: ' . $x->getMessage()); }
    }
    if ($saved !== '') $msg = '<p data-tv-flash="ok">Saved to main on GitHub (<a href="' . $e($saved) . '" target="_blank" rel="noopener">the commit</a>). The register, decisions/index.md, catches up at the next site audit.</p>';
    $key = DocsRepo::canSave() ? '' : '<p data-tv-status="info" data-tv-label="Reading only:">Decisions are read here from GitHub. To save from here, Jason makes a fine-grained GitHub token for <b>' . $e(DocsRepo::REPO) . '</b> with <b>Contents: read and write</b> (github.com → Settings → Developer settings → Fine-grained tokens) and puts it in <code>.env</code> as <code>GITHUB_DOCS_TOKEN</code> (cPanel File Manager). Until then, <a href="https://github.com/' . $e(DocsRepo::REPO) . '/tree/main/decisions" target="_blank" rel="noopener">edit on GitHub</a>.</p>';
    $form = fn (string $act, string $inner) => '<form method="post" class="ak-card tv-bleed"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="dm" value="' . $act . '">' . $inner . '</form>';

    // ---- a new decision ------------------------------------------------------------------------------------------------
    if ($new) {
        $k = $keep ?? []; $n = DocsRepo::nextNumber();
        $ta = fn (string $name, string $label, string $hint, int $rows) => '<label class="ak-note" style="display:block;margin-top:.6rem"><b>' . $label . '</b> · ' . $hint . '<textarea class="dm-ta" name="' . $name . '" rows="' . $rows . '" data-spell>' . $e((string) ($k[$name] ?? '')) . '</textarea></label>';
        return $hide . $msg . $key . '<p class="ak-row"><a class="ak-b" href="' . $base . '">‹ All decisions</a></p>'
            . ($preview !== '' ? '<div class="dm-pv dm-doc"><p class="ak-note">Preview: not saved yet.</p>' . $preview . '</div>' : '')
            . $form('new', '<h3 class="ak-h" style="margin-top:0">New decision · ' . $e($n) . '</h3><p class="ak-note">It starts as Proposed, with your name and today\'s date, and is saved to main as <code>decisions/' . $e($n) . '-….md</code>. The README\'s count follows.</p>'
                . '<label class="ak-note" style="display:block"><b>Title</b><br><input class="dm-in" name="title" required maxlength="140" data-spell value="' . $e((string) ($k['title'] ?? '')) . '"></label>'
                . $ta('context', 'Context', 'what asked for it, in the words used', 5) . $ta('decision', 'Decision', 'what was decided', 7) . $ta('consequences', 'Consequences', 'what follows from it, and what\'s left', 4)
                . '<div class="dm-row"><button class="ak-b" name="preview" value="1">Preview</button><button class="ak-b go"' . $off . '>Save decision ' . $e($n) . ' to main</button></div>');
    }

    if ($num === '') return $msg . $key . ($can || !DocsRepo::canSave() ? '' : '<p class="ak-note">' . $e($why) . '</p>')
        . '<p class="ak-note">The decision records of <a href="https://github.com/' . $e(DocsRepo::REPO) . '/tree/main/decisions" target="_blank" rel="noopener">' . $e(DocsRepo::REPO) . '</a>: open one to read it, edit it with a preview, add a note or change its status. Saves go straight to main.</p>'
        . '<p class="dm-row"><a class="ak-b go" href="' . $base . '?new=1"' . ($can ? '' : ' aria-disabled="true" title="' . $e($why) . '" onclick="return false"') . '>+ New decision</a></p>';

    // ---- one decision --------------------------------------------------------------------------------------------------
    $d = DocsRepo::one($num);
    if (!$d) return $hide . '<div class="ak-card">There\'s no decision ' . $e($num) . '. <a href="' . $base . '">See them all</a>.</div>';
    if ($d['text'] === null) return $hide . '<div class="ak-card">Decision ' . $e($num) . ' couldn\'t be read from GitHub just now. Try again in a minute, or <a href="' . $e(DocsRepo::webUrl($d['file'])) . '" target="_blank" rel="noopener">open it on GitHub</a>.</div>';
    $text = (string) $d['text']; $b64 = base64_encode($text); $k = $keep ?? [];
    $hdr = '<div class="dm-row"><a class="ak-b" href="' . $base . '">‹ All decisions</a>'
        . '<a class="ak-b' . ($mode === 'edit' ? ' go' : '') . '" href="' . $base . '?d=' . $num . '&do=edit">Edit</a>'
        . '<a class="ak-b' . ($mode === 'note' ? ' go' : '') . '" href="' . $base . '?d=' . $num . '&do=note">Add a note</a>'
        . '<a class="ak-b' . ($mode === 'status' ? ' go' : '') . '" href="' . $base . '?d=' . $num . '&do=status">Status</a>'
        . '<a class="ak-b" href="' . $e(DocsRepo::webUrl($d['file'])) . '" target="_blank" rel="noopener">On GitHub</a>'
        . '<a class="ak-b" href="' . $e(DocsRepo::historyUrl($d['file'])) . '" target="_blank" rel="noopener">History</a></div>'
        . '<p class="ak-note">' . $e($d['num']) . ' · ' . $e($d['status'] !== '' ? ucfirst($d['status']) : 'Status in the record') . ($d['replaced_by'] !== '' ? ' · replaced by <a href="' . $base . '?d=' . $e($d['replaced_by']) . '">' . $e($d['replaced_by']) . '</a>' : '') . ' · ' . count(DocsRepo::notes($text)) . ' notes · read from main, kept 10 minutes</p>';
    $pv = $preview !== '' ? '<div class="dm-pv dm-doc"><p class="ak-note">Preview: not saved yet.</p>' . $preview . '</div>' : '';
    $hb = '<input type="hidden" name="num" value="' . $e($num) . '"><input type="hidden" name="base" value="' . $e((string) ($k['base'] ?? $b64)) . '">';
    $tool = '';
    if ($mode === 'edit') {
        $tool = $form('edit', $hb . '<h3 class="ak-h" style="margin-top:0">Edit ' . $e($num) . '</h3><p class="ak-note">The whole record, in Markdown. Preview shows it as it will read; Save commits it to main. If someone changes it on GitHub meanwhile, nothing is overwritten: you\'re told.</p>'
            . '<textarea class="dm-ta" name="text" rows="24" spellcheck="true">' . $e((string) ($k['text'] ?? $text)) . '</textarea>'
            . '<div class="dm-row"><input class="dm-in" name="why" maxlength="120" placeholder="What changed, for the history (optional)" data-spell value="' . $e((string) ($k['why'] ?? '')) . '"><button class="ak-b" name="preview" value="1">Preview</button><button class="ak-b go"' . $off . '>Save ' . $e($num) . ' to main</button></div>');
    } elseif ($mode === 'note') {
        $tool = $form('note', $hb . '<h3 class="ak-h" style="margin-top:0">Add a note to ' . $e($num) . '</h3><p class="ak-note">Added at the end as <b>Note (' . date('Y-m-d') . ', ' . $e($who) . '):</b> … Say what changed and why, in the words used.</p>'
            . '<textarea class="dm-ta" name="note" rows="6" data-spell>' . $e((string) ($k['note'] ?? '')) . '</textarea>'
            . '<div class="dm-row"><button class="ak-b" name="preview" value="1">Preview</button><button class="ak-b go"' . $off . '>Save the note to main</button></div>');
    } elseif ($mode === 'status') {
        $opts = ['proposed' => ['Proposed', 'Written down, not yet agreed'], 'accepted' => ['Accepted', 'Agreed: build to it'], 'replaced' => ['Replaced', 'A newer decision takes its place (name it)'], 'parked' => ['Parked', 'Kept, not being built now'], 'rejected' => ['Rejected', 'Decided against']];
        $cur = (string) ($k['status'] ?? '');
        $sel = implode('', array_map(fn ($sk, $sv) => '<option value="' . $sk . '" title="' . $e($sv[1]) . '"' . ($cur === $sk ? ' selected' : '') . '>' . $e($sv[0] . ': ' . $sv[1]) . '</option>', array_keys($opts), $opts));
        $tool = $form('status', $hb . '<h3 class="ak-h" style="margin-top:0">Status of ' . $e($num) . '</h3><p class="ak-note">Rewrites the record\'s <b>Status:</b> line with your name and today\'s date.</p>'
            . '<div class="dm-row"><select class="dm-in" name="status" style="width:auto">' . $sel . '</select><label class="ak-note">Replaced by <input class="dm-in" name="by" inputmode="numeric" maxlength="4" style="width:5rem" placeholder="0157" value="' . $e((string) ($k['by'] ?? '')) . '"></label></div>'
            . '<div class="dm-row"><button class="ak-b" name="preview" value="1">Preview</button><button class="ak-b go"' . $off . '>Save the status to main</button></div>');
    }
    return $hide . $msg . $key . $hdr . $tool . $pv . '<article class="ak-card tv-bleed dm-doc">' . DocsRepo::html($text) . '</article>';
}

/** A line in the action log for a save to the docs repo. */
function tv_admin_dm_log(PDO $pdo, string $action, string $detail): void
{
    try {
        $me = Access::staff();
        $pdo->prepare('INSERT INTO ' . Database::t('audit_log') . " (actor_user, actor_staff, route, action, detail) VALUES (?, ?, 'config/decisions', ?, ?)")
            ->execute([Auth::currentUserId(), $me['id'] ?? null, $action, mb_substr($detail, 0, 500)]);
    } catch (Throwable) {}
}

/**
 * System Configuration → Releases (decisions/0145 §5, Phase 5; 0159 §5): every change goes out the same way. Upload a release's
 * zip and it goes straight onto staging (its plan from its release.json, its checks run there); look at it there, then
 * publish or schedule it (that's the sign-off), here or from the site manager on staging; it goes to staff and
 * test accounts, a share of members, then everyone, with Turn off and Roll back always at hand. app/lib/Releases.php.
 */
function tv_admin_mod_releases(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/Releases.php';
    require_once __DIR__ . '/../lib/Switches.php';
    require_once __DIR__ . '/../lib/Staging.php';
    $e = 'tv_w_e';
    // a passed, failed or not-yet mark: the brand's icon in a small round box, green, red or grey (as the Pre-flight's)
    $mark = function (string $s, string $title) use ($e): string {
        $st = ['ok' => 'background:#DCFCE7;color:#15803D', 'bad' => 'background:#FEE2E2;color:#B91C1C', 'na' => 'background:#F1EDE6;color:#8a7a68'][$s];
        $ic = function_exists('tv_icon') ? tv_icon($s === 'ok' ? 'check' : ($s === 'bad' ? 'x' : 'dash'), 'rel-svg') : ($s === 'ok' ? '✓' : ($s === 'bad' ? '✗' : '–'));
        return '<span class="rel-m" role="img" aria-label="' . $e($title) . '" title="' . $e($title) . '" style="display:inline-flex;width:1.35rem;height:1.35rem;border-radius:.3rem;align-items:center;justify-content:center;flex:0 0 auto;align-self:flex-start;margin-top:.05rem;' . $st . '">' . $ic . '</span>';
    };
    if (Staging::here()) return '<p data-tv-status="info" data-tv-label="Staging:">Releases are run from the live site\'s System Configuration → Releases; this copy is where they\'re tried first.</p>';
    if (!Switches::on($pdo, 'releases')) return '<p data-tv-status="info" data-tv-label="Not on for you yet:">The Releases page is behind its switch (Switches → Releases page), on for staff and test accounts first.</p>';
    Releases::ensure($pdo);
    $at = fn (?string $utc, string $tz = '') => $utc ? '<time datetime="' . $e(gmdate('c', (int) strtotime($utc . ' UTC'))) . '" data-tv-time' . ($tz !== '' ? ' data-tz="' . $e($tz) . '"' : '') . '>' . $e(gmdate('M j, g:i a', (int) strtotime($utc . ' UTC'))) . ' UTC</time>' : '';
    $row = fn (string $what, string $detail) => '<div class="ak-res"><span>' . $what . '<small>' . $detail . '</small></span></div>';
    $perm = [];
    foreach (array_keys(Releases::PERMS) as $p) $perm[$p] = Releases::can($p);
    $btn = function (string $what, int $id, string $label, string $p, string $cls = '', array $attr = []) use ($e, $perm): string {
        [$ok, $why] = $perm[$p];
        $a = ''; foreach ($attr as $k => $v) $a .= ' ' . $k . '="' . $e($v) . '"';
        return '<button type="button" class="ak-b' . ($cls !== '' ? ' ' . $cls : '') . '" data-rel="' . $e($what) . '" data-id="' . $id . '"' . ($ok ? $a : ' disabled title="' . $e($why) . '"') . '>' . $e($label) . '</button> ';
    };
    $set = Releases::settings($pdo);
    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    // Open on staging: the test account to look as (decisions/0145 Phase 5, the staging bar)
    require_once __DIR__ . '/../lib/TestAccounts.php';
    $asOpts = '<option value="me" selected>You (your own account)</option><option value="">Signed out (a visitor)</option><optgroup label="The audit\'s test members">';
    foreach (TestAccounts::MEMBERS as $k => [$nm]) $asOpts .= '<option value="m:' . $e($k) . '">' . $e($nm) . '</option>';
    $asOpts .= '</optgroup><optgroup label="The audit\'s test staff logins">';
    foreach (TestAccounts::STAFF as $k => $x) $asOpts .= '<option value="s:' . $e($k) . '">' . $e((string) $x[0]) . '</option>';
    $asOpts .= '</optgroup>';
    $enter = function (int $id, string $label) use ($e, $asOpts, $perm): string {
        [$ok, $why] = $perm['releases.publish'];
        return '<span style="display:inline-flex;gap:.3rem;align-items:center;flex-wrap:wrap"><select class="ak-in" data-rel-as="' . $id . '" aria-label="Look as"' . ($ok ? '' : ' disabled') . '>' . $asOpts . '</select>'
            . '<button type="button" class="ak-b" data-rel-enter="' . $id . '"' . ($ok ? '' : ' disabled title="' . $e($why) . '"') . '>' . $e($label) . '</button></span> ';
    };
    $on = array_map('intval', explode(',', $set['days']));

    $h = '<p class="ak-note">Every change goes out the same way: uploaded here or on the site manager at the top of staging, it goes straight onto <b>staging.trvgo.us</b> and its checks run there; once someone has looked at it there, <b>Publish</b> (now or on a schedule) is the sign-off, in steps (staff and test accounts, then ' . (int) $set['share_pct'] . '% of members, then everyone), with <b>Turn off</b> and <b>Roll back</b> always at hand. Each release is written to git (deliveries/releases/) and shows in Pulse while it waits on you.</p>';

    // the upload
    [$okUp, $whyUp] = $perm['releases.publish'];
    $h .= '<div class="ak-card tv-bleed" id="rel" data-csrf="' . $e((string) ($_SESSION['csrf_token'] ?? '')) . '">'
        . '<h3 class="ak-h" style="margin-top:0">Upload a release</h3>'
        . '<p class="ak-note">A zip of the files at their real paths (app/…, website/…, trvgo/…), the same as every delivery; migrations in app/migrations/. A <code>release.json</code> at its top can give its title, why, proposals and switch. Nothing changes on the live site until you publish it.</p>'
        . '<label class="ak-b go" style="display:inline-block;cursor:pointer"' . ($okUp ? '' : ' aria-disabled="true" title="' . $e($whyUp) . '"') . '>Choose the zip<input type="file" accept=".zip,application/zip" data-art="upload" data-rel-upload hidden' . ($okUp ? '' : ' disabled') . '></label>'
        . '<p class="ak-note" data-rel-up-note hidden></p>'
        // Check for builds (Jason, 2026-11-15: a fix waited in git with no way to fetch it from here): Claude's builds from git
        // now, the same as the 10-minute job and the ping git sends when a build lands; and when each last looked
        . '<div style="margin-top:.6rem;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">' . $btn('pull', 0, 'Check for builds', 'releases.publish')
        . '<span class="ak-note" style="margin:0">' . (function () use ($pdo, $e) {
              $l = Releases::lastLooked($pdo);
              if (!$l) return 'Claude\'s builds come from git: the site looks every 10 minutes, and at once when git pings it.';
              $p = []; foreach ($l as [$who, $at, $found]) if ($at > 0) $p[] = $e($who) . ' looked <time datetime="' . gmdate('c', $at) . '" data-tv-time>' . gmdate('M j, H:i', $at) . ' UTC</time>' . ($found < 0 ? ' (GitHub didn\'t answer)' : '');
              $late = $l[0][1] > 0 && time() - $l[0][1] > 25 * 60;
              return implode(' · ', $p) . ($late ? ' <span data-tv-status="bad" data-tv-label="Late:">nothing has looked for 25 minutes; Check for builds takes them now.</span>' : '');
          })() . '</span></div>'
        . '<p class="ak-note" style="margin-top:.6rem">Look at staging with the staging bar (the releases there, notes, sign off, schedule, publish), signed in as you (or as one of the audit\'s test accounts, to see it as they would): ' . $enter(0, 'Open staging') . '</p></div>';

    // each release
    $list = Releases::all($pdo, 30);
    if (!$list) $h .= '<p class="ak-note">No releases yet.</p>';
    // laid out as the admin's tables (Jason, 2026-11-18: "formatted like our standard table format"; "a release opens on toggle
    // under the title"): search and one Shown filter above, a header row, one row per release in columns, each opening under
    // its title; on a phone each row stacks
    if ($list) $h .= '<div class="rel-tools" style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;margin-top:.8rem">'
        . '<input type="search" class="ak-q" data-rel-q placeholder="Search releases" aria-label="Search releases">'
        . '<select class="ak-in" data-rel-show aria-label="Shown" style="flex:0 0 auto;width:auto;max-width:100%"><option value="">Every release</option><option value="waiting">Waiting (planned to scheduled)</option><option value="live">Live</option><option value="failed">A check failed</option><option value="withdrawn">Withdrawn</option><option value="rolled">Rolled back or off</option></select></div>'
        . '<div class="rel-row rel-head" aria-hidden="true"><span></span><span></span><span>Release</span><span>Where it stands</span><span>When</span><span>Files</span></div>'
        . '<p class="ak-note" data-rel-none hidden>Nothing matches. <button type="button" class="ak-b" data-rel-clear>Clear</button></p>';
    foreach ($list as $r) {
        $id = (int) $r['id'];
        [$line, $tone] = Releases::standing($r);
        // folded to one line (Jason, 2026-11-17: "the releases should be collapsed"): #N · title, where it stands, when; open
        // by default only while it needs him (on staging, vetted, scheduled, planned); admin-releases.js opens #rel-N links
        // and remembers each one opened or closed until its state changes
        $wait = in_array($r['state'], ['planned', 'approved', 'staged', 'vetted', 'scheduled'], true);
        $lastAt = $r['log'] ? (string) (end($r['log'])['at'] ?? '') : '';
        if ($r['state'] === 'scheduled' && !empty($r['schedule_at'])) { $wt = (int) strtotime($r['schedule_at'] . ' UTC'); $wl = 'goes live '; $wz = (string) $r['tz']; }
        else { $wt = $lastAt !== '' ? (int) strtotime($lastAt) : (int) strtotime((string) ($r['live_at'] ?: ($r['staged_at'] ?: $r['created_at'])) . ' UTC'); $wl = ''; $wz = ''; }
        $when = $wt > 0 ? $wl . '<time datetime="' . $e(gmdate('c', $wt)) . '" data-tv-time' . ($wz !== '' ? ' data-tz="' . $e($wz) . '"' : '') . '>' . $e(gmdate('M j, g:i a', $wt)) . ' UTC</time>' : '';
        $col = $tone === 'bad' ? '#B42318' : ($tone === 'ok' ? '#2F7D5B' : '#5C4632');
        // passed or failed at a glance (Jason, 2026-11-18: "green check box or red x at the beginning of the title"): green
        // when its checks passed or it's live and well; red when a check failed, it withdrew itself, or it's paused; grey
        // when it hasn't been checked yet or a person withdrew it
        $failed = $r['checks_ok'] === false || ($r['state'] === 'withdrawn' && str_starts_with((string) (end($r['log'])['msg'] ?? ''), 'Withdrawn by itself')) || !empty($r['paused']);
        $passed = !$failed && ($r['checks_ok'] === true || $r['state'] === 'live');
        $show = $failed ? 'failed' : (in_array($r['state'], ['planned', 'approved', 'staged', 'vetted', 'scheduled'], true) ? 'waiting' : (in_array($r['state'], ['rolled_back', 'off'], true) ? 'rolled' : $r['state']));
        $h .= '<details class="ak-card tv-bleed rel-fold" id="rel-' . $id . '" data-rel-fold="' . $id . '" data-rel-state="' . $e((string) $r['state']) . '" data-rel-kind="' . $e($show . ($r['state'] === 'withdrawn' ? ' withdrawn' : '')) . '" data-rel-text="' . $e(mb_strtolower('#' . $id . ' ' . $r['title'] . ' ' . $r['zip_name'])) . '" style="margin-top:.3rem"' . ($wait ? ' open' : '') . '>'
            . '<summary class="rel-line rel-row" style="cursor:pointer;list-style:none">'
            . '<span class="rel-caret" aria-hidden="true" style="display:inline-block;transition:transform .15s">▾</span>'
            . $mark($failed ? 'bad' : ($passed ? 'ok' : 'na'), $failed ? 'A check failed' : ($passed ? 'Its checks passed' : 'Not checked yet'))
            . '<h3 class="ak-h rel-title" style="margin:0;font-size:1.05rem;min-width:0;overflow-wrap:anywhere">#' . $id . ' · ' . $e((string) $r['title']) . '</h3>'
            . '<span class="rel-c" style="font-size:13px;font-weight:600;color:' . $col . '"><span class="rel-k">Where it stands </span>' . $e(Releases::STATES[$r['state']] ?? $r['state']) . '</span>'
            . '<small class="ak-note rel-c" style="margin:0"><span class="rel-k">When </span>' . $when . '</small>'
            . '<small class="ak-note rel-c" style="margin:0"><span class="rel-k">Files </span>' . count((array) $r['files']) . '</small>'
            . '</summary><div class="rel-body" style="margin-top:.5rem">'
            // its notice only when it says more than the mark and the word beside the title (Jason, 2026-11-18: "once we have a
            // green check why keep the green notification?"): a live release that's well, everyone on, has none
            . (($tone === 'ok' && $r['state'] === 'live' && empty($r['paused'])) ? '' : '<p data-tv-status="' . ($tone === 'bad' ? 'bad' : ($tone === 'ok' ? 'ok' : 'info')) . '" data-tv-label="' . $e(Releases::STATES[$r['state']] ?? $r['state']) . ':">' . $e($line) . '</p>');
        // where it is in its steps
        if (in_array($r['state'], ['live', 'off', 'rolled_back'], true) && $r['switch_key'] !== '') {
            $h .= '<div role="img" aria-label="Step ' . (int) $r['step'] . ' of 3" style="display:flex;gap:4px;margin:.4rem 0">';
            foreach (Releases::STEPS as $n => $name) {
                $done = $r['state'] === 'live' && $r['step'] >= $n;
                $h .= '<div style="flex:1;text-align:center;font-size:12px;padding:.3rem .2rem;border-radius:.4rem;' . ($done ? 'background:#8FBCA8;color:#1F2E27;box-shadow:0 0 6px #8FBCA8' : 'background:#F3E8D6;color:#5C4632') . '">' . $n . '. ' . $e($name) . ($n === 2 ? ' (' . (int) $set['share_pct'] . '%)' : '') . '</div>';
            }
            $h .= '</div>';
        }
        // the plan
        $files = $r['files']; $new = 0; $chg = 0; $same = 0; $fl = '';
        foreach ($files as $p => $f) {
            $k = $f['live_was'] === null ? 'new' : ($f['live_was'] === $f['sha'] ? 'same as live' : 'changed');
            if ($k === 'new') $new++; elseif ($k === 'changed') $chg++; else $same++;
            $fl .= '<li><code>' . $e((string) $p) . '</code> <small class="ak-note">' . $k . ' · ' . number_format((int) $f['size']) . ' bytes</small></li>';
        }
        $migs = array_values(array_filter(array_keys($files), fn ($p) => str_starts_with((string) $p, 'app/migrations/')));
        $facts = $row('Files', count($files) . ' (' . $chg . ' changed, ' . $new . ' new' . ($same ? ', ' . $same . ' same as live' : '') . ') from ' . $e((string) $r['zip_name']) . ' <details style="display:inline"><summary class="ak-note" style="display:inline;cursor:pointer">list</summary><ul class="ak-note">' . $fl . '</ul></details>');
        if ($migs) $facts .= $row('Migrations', $e(implode(', ', array_map('basename', $migs))) . ' (on staging first; on live they run themselves within the hour)');
        $facts .= $row('Switch', $r['switch_key'] !== '' ? '<code>' . $e((string) $r['switch_key']) . '</code>, advancing ' . ($r['advance'] === 'schedule' ? 'on schedule (' . (int) $set['members_after_h'] . ' h clean to members, ' . (int) $set['everyone_after_h'] . ' h more to everyone, inside the window)' : 'on Publish next step') : 'None: it goes to everyone at once when published (fixes)');
        if ((string) $r['proposals'] !== '') $facts .= $row('Proposals', $e((string) $r['proposals']));
        if ((string) $r['why'] !== '') $facts .= $row('Why', nl2br($e((string) $r['why'])));
        if ((string) ($r['urgent'] ?? '') !== '') $facts .= $row('Published at once because', $e((string) $r['urgent']));
        $h .= $facts;
        // its checks, as a log (Jason, 2026-11-18: "once the notice has posted it can be removed/changed to a logged entry saving
        // space and condensing under a log toggle"): one line saying how they went; each check a line inside, open only while a
        // failed check waits on a person (a zip a person uploaded: a build from git withdraws itself)
        if ($r['checks']) {
            $c = $r['checks']; $items = (array) $c['items'];
            $passed = count(array_filter($items, fn ($x) => $x[1] === true)); $failed = array_values(array_filter($items, fn ($x) => $x[1] === false));
            $waits = $r['state'] === 'staged' && $failed;
            $cl = '';
            foreach ($items as [$name, $ok, $detail]) $cl .= '<li style="margin:.25rem 0;display:flex;gap:.4rem;align-items:flex-start;overflow-wrap:anywhere">' . $mark($ok === false ? 'bad' : ($ok ? 'ok' : 'na'), $ok === false ? 'Failed' : ($ok ? 'Passed' : 'Not run')) . '<span style="min-width:0">' . $e($name) . ': ' . $e((string) $detail) . '</span></li>';
            $h .= '<details class="rel-checks" style="margin-top:.5rem"' . ($waits ? ' open' : '') . '><summary class="ak-note" style="cursor:pointer">Checks on ' . $e($c['where'] === 'staging' ? 'staging' : 'live') . ' · <time datetime="' . $e((string) $c['at']) . '" data-tv-time>' . $e(gmdate('M j, g:i a', (int) strtotime((string) $c['at']))) . ' UTC</time> · '
                . ($failed ? '<b style="color:#B42318">' . $passed . ' of ' . count($items) . ' passed; failed: ' . $e(implode(', ', array_map(fn ($x) => (string) $x[0], $failed))) . '</b>' : 'all ' . count($items) . ' passed')
                . '</summary><ul class="ak-note" style="list-style:none;padding-left:.2rem;margin:.3rem 0">' . $cl . '</ul></details>';
        }
        // what can be done now
        $acts = '';
        switch ($r['state']) {
            case 'planned':
            case 'approved':
                if ($r['state'] === 'planned') {
                    $acts .= '<div class="ak-form" data-rel-plan="' . $id . '" style="display:grid;gap:.4rem;margin:.5rem 0">'
                        . '<label>Title <input class="ak-in" name="title" maxlength="160" data-spell value="' . $e((string) $r['title']) . '"></label>'
                        . '<label>Why (what it changes, for whom) <textarea class="ak-in" name="why" rows="3" maxlength="4000">' . $e((string) $r['why']) . '</textarea></label>'
                        . '<label>Proposals it carries <input class="ak-in" name="proposals" maxlength="255" placeholder="P7, P8" value="' . $e((string) $r['proposals']) . '"></label>'
                        . '<label>Its switch <input class="ak-in" name="switch" maxlength="64" spellcheck="false" placeholder="none: everyone at once" value="' . $e((string) $r['switch_key']) . '"></label>'
                        . '<label>Advances <select class="ak-in" name="advance"><option value="schedule"' . ($r['advance'] === 'schedule' ? ' selected' : '') . '>On schedule (when its checks are clean)</option><option value="publish"' . ($r['advance'] === 'publish' ? ' selected' : '') . '>On Publish next step</option></select></label></div>'
                        . $btn('approve', $id, 'Approve the plan', 'releases.publish', 'go');
                } else $acts .= $btn('stage', $id, 'Put it on staging', 'releases.publish', 'go');
                $acts .= $btn('urgent', $id, 'Publish now (urgent)', 'releases.publish', '', ['data-danger' => '1']) . $btn('withdraw', $id, 'Withdraw', 'releases.publish', '', ['data-confirm' => 'Withdraw this release? It won\'t go live.', 'data-yes' => 'Withdraw']);
                break;
            case 'staged':
                if (!$r['checks_ok']) {
                    $acts .= $btn('stage', $id, 'Try staging again', 'releases.publish') . $btn('urgent', $id, 'Publish now (urgent)', 'releases.publish', '', ['data-danger' => '1']) . $btn('withdraw', $id, 'Withdraw', 'releases.publish', '', ['data-confirm' => 'Withdraw this release? It won\'t go live.', 'data-yes' => 'Withdraw']);
                    break;
                }
                $acts .= $btn('stage', $id, 'Put it on staging again', 'releases.publish');
                // its review here too (Jason, 2026-11-15: the staging bar wasn't there, so the review couldn't be given): UI, UX
                // and Functional, each Approve or Needs changes with a note; Publish opens when all three are approved
                if (($rv = Releases::review($pdo, $id)) !== null) {
                    $acts .= '<div class="ak-form" style="display:grid;gap:.45rem;margin:.5rem 0"><b>Review</b>';
                    foreach (Releases::REVIEW_PARTS as $pk => [$pn, $pw]) {
                        $pp = $rv['parts'][$pk] ?? []; $st = ($pp['ok'] ?? null) === true ? '✓ Approved' . (!empty($pp['by']) ? ' by ' . $pp['by'] : '') : (($pp['ok'] ?? null) === false ? '✗ Needs changes: ' . (string) ($pp['note'] ?? '') : 'Waiting for you');
                        // an approved part is one line with Edit (Jason, 2026-11-15); Edit opens it again
                        $ok = ($pp['ok'] ?? null) === true;
                        $acts .= ($ok ? '<details><summary style="cursor:pointer"><b>' . $e($pn) . '</b> <span style="color:#2F7D5B">' . $e($st) . '</span> · <u>Edit</u></summary>' : '<div><span><b>' . $e($pn) . '</b> · ' . $e($pw) . ' · <i>' . $e($st) . '</i></span>')
                            . '<div style="display:flex;gap:.35rem;flex-wrap:wrap;align-items:center;margin-top:.25rem">'
                            . '<input class="ak-in" data-spell data-rel-note="' . $id . ':' . $pk . '" placeholder="What needs changing (for Needs changes)" aria-label="What needs changing in ' . $e($pn) . '" style="flex:1 1 14rem">'
                            . $btn('review', $id, 'Approve', 'releases.publish', 'go', ['data-part' => $pk, 'data-ok' => '1']) . $btn('review', $id, 'Needs changes', 'releases.publish', '', ['data-part' => $pk, 'data-ok' => '0']) . '</div>' . ($ok ? '</details>' : '</div>');
                    }
                    $acts .= '</div>';
                }
                // its checks passed: Publish and Schedule now, each the sign-off once someone has looked at it on staging (decisions/0159 §5)
            case 'vetted':
            case 'scheduled':
                if (!Releases::fromGitRelease($r)) {   // a zip uploaded on staging goes live with staging's own work (decisions/0165 correction, Jason 2026-11-15)
                    $acts .= '<a class="ak-b go" href="/admin/config/staging#against">Publish with staging\'s work</a> ' . $btn('urgent', $id, 'Publish now (urgent)', 'releases.publish', '', ['data-danger' => '1']);
                    $acts .= $btn('withdraw', $id, 'Withdraw', 'releases.publish', '', ['data-confirm' => 'Withdraw this release? It won\'t go live.', 'data-yes' => 'Withdraw']);
                    break;
                }
                $acts .= $btn('publish', $id, 'Publish', 'releases.publish', 'go', ['data-confirm' => 'Publish it to the live site now?' . ($r['switch_key'] !== '' ? ' It goes to staff and test accounts first.' : ' It has no switch: everyone gets it at once.'), 'data-yes' => 'Publish']);
                [$okS, $whyS] = $perm['releases.schedule'];
                $acts .= '<span style="display:inline-flex;gap:.3rem;align-items:center;flex-wrap:wrap"><input class="ak-in" type="datetime-local" data-rel-when="' . $id . '" aria-label="When it goes live"' . ($okS ? '' : ' disabled title="' . $e($whyS) . '"') . '>' . $btn('schedule', $id, $r['state'] === 'scheduled' ? 'Change the time' : 'Schedule', 'releases.schedule') . '</span> ';
                if ($r['state'] === 'scheduled') $acts .= $btn('unschedule', $id, 'Take off the schedule', 'releases.schedule');
                $acts .= $btn('withdraw', $id, 'Withdraw', 'releases.publish', '', ['data-confirm' => 'Withdraw this release? It won\'t go live.', 'data-yes' => 'Withdraw']);
                break;
            case 'live':
                if ($r['step'] < 3 && $r['switch_key'] !== '') $acts .= $btn('next', $id, 'Publish next step', 'releases.publish', 'go', ['data-confirm' => $r['step'] === 1 ? 'Turn it on for ' . (int) $set['share_pct'] . '% of members as well?' : 'Turn it on for everyone?', 'data-yes' => 'Publish next step']);
                if ($r['step'] < 3) $acts .= $r['paused'] ? $btn('resume', $id, 'Carry on', 'releases.publish') : $btn('pause', $id, 'Pause', 'releases.publish');
                if ($r['switch_key'] !== '') $acts .= $btn('off', $id, 'Turn off', 'releases.turn_off', '', ['data-confirm' => 'Turn it off for everyone, now?', 'data-yes' => 'Turn off', 'data-danger' => '1']);
                $acts .= $btn('back', $id, 'Roll back', 'releases.rollback', '', ['data-confirm' => 'Put the previous copy of every file back on the live site, now?', 'data-yes' => 'Roll back', 'data-danger' => '1']);
                break;
            case 'off':
                $acts .= $btn('back', $id, 'Roll back', 'releases.rollback', '', ['data-confirm' => 'Put the previous copy of every file back on the live site, now?', 'data-yes' => 'Roll back', 'data-danger' => '1']);
                break;
        }
        if (in_array($r['state'], ['staged', 'vetted', 'scheduled'], true)) $acts = $enter($id, 'Open on staging') . $acts;
        if ($acts !== '') $h .= '<div style="margin-top:.5rem;display:flex;gap:.3rem;flex-wrap:wrap;align-items:center">' . $acts . '</div>';
        if ($r['state'] !== 'planned') {
            [$okZ, $whyZ] = $perm['releases.publish'];
            $z = fn (string $w, string $l) => $okZ ? '<a class="ak-b" href="/admin/api/config/releases?action=download&amp;id=' . $id . '&amp;which=' . $w . '">' . $l . '</a> ' : '<span class="ak-b" aria-disabled="true" title="' . $e($whyZ) . '">' . $l . '</span> ';
            $h .= '<p class="ak-note" style="margin-top:.5rem">For an upload by FTP instead: ' . $z('release', 'Download the zip') . $z('rollback', 'Download the rollback zip') . '</p>';
        }
        if ($r['log']) {
            $lg = '';
            foreach (array_reverse($r['log']) as $l) $lg .= '<li><time datetime="' . $e((string) $l['at']) . '" data-tv-time>' . $e(gmdate('M j, g:i a', (int) strtotime((string) $l['at']))) . ' UTC</time> · ' . $e((string) $l['msg']) . '</li>';
            $h .= '<details style="margin-top:.4rem"><summary class="ak-note">What happened</summary><ul class="ak-note">' . $lg . '</ul></details>';
        }
        $h .= '</div></details>';
    }
    if ($list) $h .= '<style>details.rel-fold>summary::-webkit-details-marker{display:none}details.rel-fold:not([open])>summary .rel-caret{transform:rotate(-90deg)}'
        . '.rel-row{display:grid;grid-template-columns:.9rem 1.35rem minmax(0,1fr) 8.5rem 11rem 3rem;gap:.2rem .6rem;align-items:start}'
        . '.rel-head{padding:.5rem 1rem 0;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;color:#8a7a68}.rel-k{display:none}'
        . '@media (max-width:767px){.rel-row{grid-template-columns:.9rem 1.35rem minmax(0,1fr)}.rel-head{display:none}.rel-c{grid-column:3}.rel-k{display:inline;font-weight:400;color:#8a7a68}}</style>';

    // the rollout defaults and the window
    [$okSet, $whySet] = $perm['releases.schedule'];
    $dis = $okSet ? '' : ' disabled title="' . $e($whySet) . '"';
    $dd = ''; foreach ($days as $i => $d) $dd .= '<label style="margin-right:.5rem"><input type="checkbox" name="days" value="' . $i . '"' . (in_array($i, $on, true) ? ' checked' : '') . $dis . '> ' . $d . '</label>';
    $h .= '<details class="ak-card tv-bleed" style="margin-top:.8rem"><summary class="ak-h" style="cursor:pointer">Rollout defaults and the release window</summary>'
        . '<div class="ak-form" data-rel-settings style="display:grid;gap:.4rem;margin-top:.5rem">'
        . '<label>Share of members at step 2 (%) <input class="ak-in" type="number" min="1" max="99" name="share_pct" value="' . (int) $set['share_pct'] . '"' . $dis . '></label>'
        . '<label>Hours clean before members <input class="ak-in" type="number" min="1" max="720" name="members_after_h" value="' . (int) $set['members_after_h'] . '"' . $dis . '></label>'
        . '<label>Hours clean before everyone <input class="ak-in" type="number" min="1" max="720" name="everyone_after_h" value="' . (int) $set['everyone_after_h'] . '"' . $dis . '></label>'
        . '<div>Days a release may go on, on schedule: ' . $dd . '</div>'
        . '<label>From (hour, Arizona time) <input class="ak-in" type="number" min="0" max="23" name="from_h" value="' . (int) $set['from_h'] . '"' . $dis . '></label>'
        . '<label>Until (hour) <input class="ak-in" type="number" min="1" max="24" name="to_h" value="' . (int) $set['to_h'] . '"' . $dis . '></label>'
        . '<p class="ak-note">A scheduled release goes live at the time you give it; the window is for the steps that advance by themselves. Publish, Publish next step, Turn off and Roll back work any time.</p>'
        . '<div><button type="button" class="ak-b go" data-rel="settings" data-id="0"' . $dis . '>Save the defaults</button></div></div></details>';

    return $h . '<style>.rel-m .rel-svg{width:.9rem;height:.9rem;fill:none;stroke:currentColor;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}</style><script src="/js/tv-time.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/tv-time.js') . '" defer></script>'
        . '<script src="/js/admin-releases.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/admin-releases.js') . '" defer></script>';
}
