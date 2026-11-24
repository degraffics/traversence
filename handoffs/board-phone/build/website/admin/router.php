<?php
// admin/router.php — the admin (decisions/0109): /admin/<module>/<section>[/<id>]. Checks who may open it, draws the
// frame (the menu of the 11 modules, the section dropdown, the tools at hand) and the section's widgets
// (app/admin/widgets.php). ?panel=1 draws only the section, for a drawer opened over other work (A3a).
// admin/.htaccess sends the clean addresses here; admin/index.php is /admin/dashboard.
declare(strict_types=1);

require_once __DIR__ . '/../../app/ui/admin-hub.php';
require_once __DIR__ . '/../../app/admin/sections.php';
require_once __DIR__ . '/../../app/ui/app-shell.php';   // tv_icon()
require_once __DIR__ . '/../../app/ui/section-rails.php';   // tv_admin_rail()

$pdo = Database::connection();
[$me] = tv_admin_require($pdo);
if (Workflow::ready($pdo)) Workflow::seed($pdo);

$path = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
$parts = $tvRoute ?? array_slice(explode('/', $path), 1);   // admin/<module>/<section>/<id>
$mk = (string) ($parts[0] ?? '') ?: 'dashboard';
$sk = (string) ($parts[1] ?? '');
$id = (string) ($parts[2] ?? '');
$panel = !empty($_GET['panel']);
if ($panel) $_GET['embed'] = '1';

// a module that's part of another now (Workspaces::MERGED: Pulse in the Dashboard): its pages open there
require_once __DIR__ . '/../../app/lib/Workspaces.php';
if (isset(Workspaces::MERGED[$mk])) {
    [$into, $map] = Workspaces::MERGED[$mk];
    $q = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
    header('Location: /admin/' . $into . '/' . ($map[$sk] ?? array_values($map)[0]) . ($id !== '' ? '/' . rawurlencode($id) : '') . ($q !== '' ? '?' . $q : ''), true, 302); exit;
}
// one control board in the menu (decisions/0175's note, Jason 2026-11-20: "yes. fold into 2"; release 2b part A): the board's
// own tables and Scheduled tasks are its tabs, Automation is its Start-up stage, Pre-flight its Pre-flight stage. The old
// address lands on the tab (?tab=), with no fragment of its own, so the browser keeps the link's (#job-<key>, #pf-<line>).
// Drawn on its own for the board's tab (?panel=1) or in the side panel (?embed=1), it isn't redirected; it's left out of
// the sections menu.
const TV_ADMIN_FOLDED = ['config/alarms' => 'alarms', 'config/log' => 'log', 'config/ledger' => 'ledger', 'config/upkeep' => 'upkeep',
    'config/scheduler' => 'scheduler', 'config/preflight' => 'preflight', 'config/automation' => 'startup'];
if (isset(TV_ADMIN_FOLDED[$mk . '/' . $sk]) && !$panel && empty($_GET['embed'])) {
    $q = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
    header('Location: /admin/config/control?tab=' . TV_ADMIN_FOLDED[$mk . '/' . $sk] . ($q !== '' ? '&' . $q : ''), true, 302); exit;
}
// Costs is folded into Modules, the Service register, and the Workflow board into Workflows (decisions/0175's note, release
// 2b parts B and C): each address opens its place there (#costs, #crawler), its query kept; drawn on its own (?panel=1, ?embed=1) it isn't redirected; it's left out of the menu
const TV_ADMIN_FOLDED_INTO = ['config/costs' => 'config/register#costs', 'config/board' => 'config/workflows#crawler'];   // the Workflow board: the crawler's part of Workflows (part C)
if (isset(TV_ADMIN_FOLDED_INTO[$mk . '/' . $sk]) && !$panel && empty($_GET['embed'])) {
    $q = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY); [$to, $frag] = explode('#', TV_ADMIN_FOLDED_INTO[$mk . '/' . $sk], 2) + [1 => ''];
    header('Location: /admin/' . $to . ($q !== '' ? '?' . $q : '') . ($frag !== '' ? '#' . $frag : ''), true, 302); exit;
}
// a section that's part of another now (decisions/0173 §1: Outside services is in the Service register): its address opens
// there, with its query; the browser keeps the #svc-<key> through the redirect, so a link lands on the same card
const TV_ADMIN_MOVED = ['config/services' => 'config/register'];
if (isset(TV_ADMIN_MOVED[$mk . '/' . $sk])) {
    $q = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
    header('Location: /admin/' . TV_ADMIN_MOVED[$mk . '/' . $sk] . ($id !== '' ? '/' . rawurlencode($id) : '') . ($q !== '' ? '?' . $q : ''), true, 302); exit;
}
$m = Modules::module($mk);
if (!$m) { http_response_code(404); tv_admin_start('dashboard', 'Not part of the admin'); echo '<div class="hb-card tv-bleed" style="max-width:32rem;margin-top:1rem"><p>There\'s no admin module here. <a class="underline text-[#92400E]" href="/admin/dashboard">Go to the Dashboard</a>.</p></div>'; tv_admin_end(); exit; }
if ($sk === '' || !isset($m['sections'][$sk])) {
    $first = '';
    foreach ($m['sections'] as $k => $s) if ($s['status'] !== 'coming' && Modules::canSection($mk, $k)[0]) { $first = $k; break; }
    if ($first === '') $first = (string) array_key_first($m['sections']);
    header('Location: ' . Modules::url($mk, $first) . ($panel ? '?panel=1' : ''), true, 302); exit;
}
$s = $m['sections'][$sk];
[$ok, $why] = Modules::canSection($mk, $sk);

// the section dropdown (like Shortcuts): every section, coming ones greyed with their tag
$dd = '<details class="ak-dd"><summary>' . tv_icon($s['icon'] ?? 'compass', 'ak-svg') . '<span>' . tv_w_e($s['label']) . '</span></summary><nav class="ak-ddl" aria-label="Sections">';
$part = '';
foreach ($m['sections'] as $k => $x) {
    if (isset(TV_ADMIN_FOLDED[$mk . '/' . $k])) continue;   // it lives in the board now (its tab): not a menu entry of its own
    if (isset(TV_ADMIN_FOLDED_INTO[$mk . '/' . $k])) continue;   // folded into another section
    $off = $x['status'] === 'coming';
    if (($x['part'] ?? '') !== '' && $x['part'] !== $part) { $part = (string) $x['part']; $dd .= '<b style="display:block;font-size:.66rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#8A7355;padding:.55rem .7rem .2rem">' . tv_w_e($part) . '</b>'; }   // a group in the module (decisions/0135 §1)
    $dd .= '<a href="' . tv_w_e(Modules::url($mk, $k)) . '"' . ($k === $sk ? ' class="on" aria-current="page"' : ($off ? ' class="off"' : '')) . '>' . tv_icon($x['icon'] ?? 'compass', 'ak-svg')
        . '<span>' . tv_w_e($x['label']) . '</span>' . ($off ? '<i>Coming</i>' : ($x['status'] === 'partial' ? '<i>In part</i>' : '')) . '</a>';
}
$dd .= '</nav></details>';

// the tools at hand: other modules' sections, opened in a drawer over this one
$hand = '';
foreach ((array) ($m['at_hand'] ?? []) as $h) {
    [$hm, $hs] = explode('/', $h) + ['', ''];
    $hmod = Modules::module($hm);
    if (!$hmod || !isset($hmod['sections'][$hs]) || $hmod['sections'][$hs]['status'] === 'coming') continue;
    [$hok, $hwhy] = Modules::canSection($hm, $hs);
    $lab = $hmod['sections'][$hs]['label'] . ' (' . $hmod['name'] . ')';
    $hand .= '<button type="button" class="ak-ib"' . ($hok ? ' data-ak-hand="' . tv_w_e(Modules::url($hm, $hs)) . '" data-ak-title="' . tv_w_e($hmod['sections'][$hs]['label']) . '"' : ' disabled') . ' title="' . tv_w_e($hok ? $lab : $lab . ': ' . $hwhy) . '" aria-label="' . tv_w_e($lab) . '">' . tv_icon($hmod['sections'][$hs]['icon'] ?? 'compass', 'ak-svg') . '</button>';
}

// an item with a page of its own (a client order's builder): /admin/backoffice/orders/9 shows that page inside the
// module, and /new starts one; the page keeps working in its frame (form posts reload it there)
$item = '';
if ($id !== '' && !empty($s['item']) && (ctype_digit($id) || $id === 'new')) {
    [$iu, $it] = $s['item'];
    $item = $id === 'new' ? preg_replace('/([?&])id=\{id\}/', '$1new=1', $iu) : str_replace('{id}', $id, $iu);
    $itemTitle = $id === 'new' ? 'New' : str_replace('{id}', $id, $it);
}
// the item's own page, drawn here as a whole page (no frame inside the module): it has its own module header, menu and
// address (/admin/backoffice/orders/9), and posts back to it
if ($item !== '') {
    parse_str((string) parse_url($item, PHP_URL_QUERY), $iq);
    $iPage = __DIR__ . '/' . basename((string) parse_url($item, PHP_URL_PATH));
    (function () use ($iq, $iPage) { $tvItemRoute = true; foreach ($iq as $k => $v) $_GET[$k] = $v; require $iPage; })();
    exit;
}
// opening an item from its address (/admin/backoffice/orders/922): the first list of the section opens it
$open = '';
if ($id !== '' && $item === '') foreach ((array) ($s['widgets'] ?? []) as $w) if (in_array($w[0], ['table', 'pipeline'], true)) { $open = $w[1] . '|' . $id; break; }
$lead = false;
foreach ((array) ($s['widgets'] ?? []) as $w) if (in_array($w[0], ['table', 'pipeline'], true) && (Modules::tool($w[1])['open'] ?? '') === 'lead') $lead = true;

$title = ($item !== '' ? $itemTitle . ' · ' : '') . $s['label'] . ' · ' . $m['name'];
if ($panel) {
    tv_page_start($title, ['no_crumbs' => true]);
} else {
    tv_page_start($title, ['wide' => true, 'no_crumbs' => true, 'description' => 'Traversence admin: ' . $title . '.', 'rail' => tv_admin_rail($mk), 'rail_label' => 'Admin', 'active' => $mk]);
}
require __DIR__ . '/../../app/admin/kit-css.php';
if (!$panel) {
    // the command header (Jason's design directive, 2026-11-18; it replaced the sage bar of 2026-11-12): one row under the
    // site's header. Back, the module, the sections dropdown and the page's own filter (#ak-cmdf) on the left; on the
    // right (#ak-cmdr) the board's light, the tools at hand, what the page puts there (the vault's time left) and Shortcuts
    $arrow = '<svg class="ak-svg" viewBox="0 0 24 24" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>';
    // the board's light (decisions/0171 §7, release 3a): open trips and warnings, for whoever can open the board; it opens
    // the alarm list
    if (!function_exists('tv_admin_board_light')) { function tv_admin_board_light(?PDO $pdo): string {
        try {
            require_once __DIR__ . '/../../app/lib/ControlBoard.php'; require_once __DIR__ . '/../../app/lib/ControlAlarms.php';
            if (!$pdo || !ControlBoard::can()[0]) return '';
            $c = ControlAlarms::counts($pdo); $n = $c['trip'] + $c['warning'];
            // a trip started again and waiting for its run is amber, as its card (task-trip-3): red only for one still holding
            $w = (int) ($c['waiting'] ?? 0); $c['trip'] -= $w;
            // red only for a trip, amber for warnings and waiting trips (decisions/0171 §8's note), from the palette's tokens with their values as fallbacks
            $tone = $c['trip'] ? ['color-mix(in srgb,var(--tv-fail,#a62b2b) 8%,#fff)', 'var(--tv-fail,#a62b2b)'] : ($c['warning'] || $w ? ['color-mix(in srgb,var(--tv-hold,#8f5600) 11%,#fff)', 'var(--tv-hold,#8f5600)'] : ['#E8F3EC', '#15803D']);
            $say = $n ? implode(', ', array_filter([$c['trip'] ? $c['trip'] . ' trip' . ($c['trip'] === 1 ? '' : 's') : '', $w ? $w . ' waiting for ' . ($w === 1 ? 'its run' : 'their run') : '', $c['warning'] ? $c['warning'] . ' warning' . ($c['warning'] === 1 ? '' : 's') : ''])) : 'No alarm open';
            return '<a class="ak-light" href="/admin/config/control#cb-alarms" title="System Control Board: ' . tv_w_e($say) . '" aria-label="System Control Board: ' . tv_w_e($say) . '" style="display:inline-flex;align-items:center;gap:.3rem;padding:.15rem .5rem;border-radius:999px;font:700 .8rem system-ui;text-decoration:none;background:' . $tone[0] . ';color:' . $tone[1] . '">'
                . '<span aria-hidden="true" style="width:.55rem;height:.55rem;border-radius:50%;background:currentColor' . ($c['trip'] ? ';box-shadow:0 0 6px currentColor' : '') . '"></span>' . ($n ? $n : '') . '</a>';
        } catch (Throwable $x) { return ''; }
    } }
    echo '<header class="ak-head ak-cmd"><a class="ak-back" href="' . tv_w_e($item !== '' ? Modules::url($mk, $sk) : '/admin/') . '" data-ak-back title="Back" aria-label="Back">' . $arrow . '</a>'
        . '<span class="ak-mod">' . tv_w_e($m['num'] . '. ' . $m['name']) . ':</span><h1 class="ak-sr">' . tv_w_e($item !== '' ? $itemTitle : $s['label']) . '</h1>' . $dd
        . ($item !== '' ? '<span class="ak-item">' . tv_w_e($itemTitle) . '</span>' : '')
        . '<div class="ak-cmdf" id="ak-cmdf"></div>'   // the page's own filter, moved up here from its list ([data-ak-cmd], admin-kit.js)
        . '<div class="ak-cmdr" id="ak-cmdr">' . ($mk === 'dashboard' ? '' : tv_admin_board_light($pdo ?? null)) . ($hand !== '' ? '<div class="ak-hand" aria-label="At hand"><span>At hand</span>' . $hand . '</div>' : '') . tv_admin_bar() . '</div></header>';
}
// the command bar sits right under the site's header: whatever space the shell leaves above it (its padding, the page
// body's margin, a notice), it's measured once the page is drawn and closed (Jason, 2026-11-12: "no margin above")
if (!$panel) echo '<script' . (function_exists('tv_nonce') ? tv_nonce() : '') . '>(function () { var h = document.querySelector("header.ak-cmd"), tb = document.getElementById("top-bar"); if (!h || !tb) return;'
    . ' function fit() { if (window.scrollY > 2) return; h.style.removeProperty("margin-top"); var b = tb.getBoundingClientRect().bottom;'
    . ' [document.getElementById("tv-notice"), document.querySelector("body > .tv-as")].forEach(function (x) { if (x && x.offsetHeight) b = Math.max(b, x.getBoundingClientRect().bottom); });'
    . ' document.documentElement.style.setProperty("--ak-top", Math.round(b) + "px");'   // it sticks right under what's pinned above it, measured (2026-11-18)
    . ' var g = h.getBoundingClientRect().top - b;'
    . ' if (g > 1) h.style.setProperty("margin-top", ((parseFloat(getComputedStyle(h).marginTop) || 0) - g) + "px", "important"); }'
    . ' fit(); window.addEventListener("load", fit); window.addEventListener("resize", fit); })();</script>';
// the back arrow beside the title (Jason, 2026-10-30): to the admin page you came from (the site map, a list), else up
if (!$panel) echo '<script>(function () { var b = document.querySelector("[data-ak-back]"); if (!b) return; try { var r = document.referrer ? new URL(document.referrer) : null;
  if (r && r.origin === location.origin && r.pathname.indexOf("/admin") === 0 && r.pathname !== location.pathname) { if (!/[?&]embed=/.test(r.search)) b.href = r.pathname + r.search; b.addEventListener("click", function (e) { if (history.length > 1) { e.preventDefault(); history.back(); } }); } } catch (x) {} })();</script>';
echo '<div class="ak-body" data-ak-section="' . tv_w_e($mk . '/' . $sk) . '"' . ($open !== '' ? ' data-ak-open="' . tv_w_e($open) . '"' : '') . '>';
if (!$ok) echo '<div class="ak-card ak-off tv-bleed"><p>' . tv_w_e($why) . '. Ask an owner if you need it.</p></div>';
else echo $item !== '' ? tv_w_item($item, Modules::url($mk, $sk)) : tv_w_section($pdo, $m, $sk);
echo '</div>';
if ($lead) { require_once __DIR__ . '/../../app/ui/lead-panel.php'; echo tv_lead_panel(); }
echo '<script src="/js/admin-kit.js?v=' . (int) @filemtime(__DIR__ . '/../js/admin-kit.js') . '"></script>';
tv_admin_end();
