<?php
// includes/engage-ui.php — the standard engagement on content pages (decisions/0058 §20): the Engage bar under the
// page's header (views, likes, comments, Share, Report) and the Comments section at the end, with one level of replies
// (decisions/0172 §2.3). js/engage.js runs both.
//   echo tv_engage_bar($pdo, 'exp', '12', ['title' => 'Paddle Lyman Lake', 'report' => 'experience:12']);
//   echo tv_engage_comments($pdo, 'exp', '12');
declare(strict_types=1);

require_once __DIR__ . '/../lib/Engage.php';

function tv_engage_assets(): string
{
    static $done = false;
    if ($done) return '';
    $done = true;
    return <<<'H'
<style>
  .eg-bar{display:flex;flex-wrap:wrap;align-items:center;gap:.35rem;margin:.6rem 0 .2rem}
  .eg-bar .eg-n{display:inline-flex;align-items:center;gap:.3rem;font-size:.8rem;font-weight:700;color:var(--tv-meta);margin-right:.2rem;font-variant-numeric:tabular-nums}.eg-n svg{width:16px;height:16px}
  .eg-b{display:inline-flex;align-items:center;gap:.3rem;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);border-radius:999px;padding:.28rem .7rem;font:inherit;font-size:.8rem;font-weight:600;cursor:pointer;text-decoration:none;line-height:1.2}
  .eg-b:hover{border-color:var(--tv-icon,#B45309)}.eg-b svg{width:15px;height:15px;color:var(--tv-icon,#B45309)}
  .eg-b[aria-pressed="true"]{background:var(--tv-sage);border-color:var(--tv-sage);color:var(--tv-umber);box-shadow:0 0 0 2px rgba(143,188,168,.3)}.eg-b[aria-pressed="true"] svg{color:var(--tv-umber)}
  .eg-b.r{margin-left:auto;color:var(--tv-meta);font-weight:500}
  .eg-sec{margin:1.4rem 0 .5rem}.eg-sec h2{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-size:1.2rem;color:var(--tv-ink);margin:0 0 .5rem}
  .eg-cm{background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:.7rem;padding:.55rem .75rem;margin-bottom:.4rem}
  .eg-cm b{font-size:.86rem;color:var(--tv-ink)}.eg-cm small{color:var(--tv-meta);font-size:.75rem;margin-left:.4rem}.eg-cm p{margin:.25rem 0 0;font-size:.9rem;color:var(--tv-ink);white-space:pre-line}
  .eg-re{margin-left:1.1rem;border-left:3px solid var(--tv-line-strong)}.eg-team{display:inline-block;margin-left:.35rem;font-size:.72rem;font-weight:700;color:var(--tv-link);background:var(--tv-card-a);border-radius:999px;padding:.05rem .45rem}
  .eg-rf{margin:.2rem 0 .5rem 1.1rem}.eg-rf .eg-in{min-height:3rem}
  .eg-cm button{float:right;border:0;background:none;color:var(--tv-meta);font-size:.75rem;cursor:pointer;text-decoration:underline;margin-left:.4rem}
  .eg-in{width:100%;box-sizing:border-box;border:1px solid var(--tv-line-strong);border-radius:.6rem;padding:.5rem .6rem;font:inherit;font-size:.9rem;background:var(--tv-field);min-height:4.5rem}
  .eg-box{margin-top:.4rem}.eg-fake{display:block;text-align:left;color:var(--tv-meta);cursor:text;min-height:2.8rem!important}
  .eg-grow{min-height:2.8rem;transition:min-height .15s}.eg-grow:focus,.eg-grow:not(:placeholder-shown){min-height:6rem}
  .eg-starters{display:flex;flex-wrap:wrap;gap:.35rem;margin:.45rem 0 .1rem}.eg-st{border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);border-radius:999px;padding:.25rem .7rem;font:500 .82rem var(--tv-font-ui);cursor:pointer}
  .eg-st:hover{border-color:var(--tv-link);color:var(--tv-link)}.eg-row{margin:.4rem 0 0}
  .eg-note{font-size:.8rem;color:var(--tv-meta);margin:.3rem 0}.eg-note a{color:var(--tv-link)}
  /* ⋯ on the bar (Jason, 2026-11-19: "Maybe a ... tool kit for things?"): the page's tools in words; what can't be used here is greyed with why */
  .eg-mw{position:relative;display:inline-flex}.eg-more{font:700 .95rem/1 system-ui,sans-serif;letter-spacing:.05em;padding:.28rem .6rem!important}
  .eg-ml{position:absolute;right:0;top:calc(100% + .35rem);z-index:60;min-width:14.5rem;display:flex;flex-direction:column;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.7rem;box-shadow:var(--tv-shadow-lg,0 12px 30px -12px rgba(0,0,0,.35));padding:.35rem .3rem .3rem;text-align:left;text-shadow:none}
  .eg-ml[hidden]{display:none}.eg-hid{visibility:hidden!important}   /* the page's Link icon, while ⋯ is open (Jason, 2026-11-20) */.eg-ml.up{top:auto;bottom:calc(100% + .35rem)}
  .eg-ml a,.eg-ml button[role=menuitem],.eg-mo{display:flex;align-items:flex-start;gap:.5rem;width:100%;box-sizing:border-box;padding:.45rem .55rem;border:0;background:none;border-radius:.4rem;font:500 .9rem var(--tv-font-ui);color:var(--tv-ink);text-decoration:none;cursor:pointer;text-align:left;position:static}
  .eg-ml a:hover,.eg-ml button[role=menuitem]:hover{background:var(--tv-field)}.eg-ml svg{width:16px;height:16px;flex:none;margin-top:.1rem;color:var(--tv-icon,#B45309)}
  .eg-mo{color:var(--tv-meta);cursor:default}.eg-mo span{display:flex;flex-direction:column}.eg-mo small{font-size:.76rem;line-height:1.3}.eg-mo svg{color:var(--tv-meta)}
  .eg-mx{align-self:flex-end;width:26px;height:26px;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);font-size:1rem;line-height:1;cursor:pointer;padding:0;position:static}
  .eg-ml .tv-save-wrap{display:block}.eg-ml .tv-save{display:flex!important;align-items:center;gap:.5rem;width:100%;padding:.45rem .55rem!important;border:0!important;background:none!important;border-radius:.4rem;font:500 .9rem var(--tv-font-ui)!important;color:var(--tv-ink)!important;cursor:pointer;box-shadow:none!important}
  .eg-ml .tv-save:hover{background:var(--tv-field)!important}.eg-ml .tv-save svg{width:16px;height:16px;color:var(--tv-icon,#B45309)}.eg-ml .tv-save span{display:inline!important}.eg-ml .tv-save::after{content:none!important}.eg-ml .tv-save-note{font-size:.78rem;color:var(--tv-meta);padding:0 .55rem .35rem}
  .eg-trip{position:fixed;z-index:3100;left:50%;top:50%;transform:translate(-50%,-50%);width:min(26rem,calc(100vw - 6px));max-height:calc(100vh - 2rem);overflow:auto;box-sizing:border-box;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.9rem;box-shadow:var(--tv-shadow-lg,0 12px 30px -12px rgba(0,0,0,.45));padding:.8rem .9rem;color:var(--tv-ink);text-align:left}
  .eg-trip h3{margin:0 2rem .5rem 0;font:700 1.02rem var(--tv-font-head,ui-serif,Georgia,serif)}.eg-trip p{margin:.3rem 0;font-size:.86rem;color:var(--tv-meta)}
  .eg-trip ul{list-style:none;margin:.3rem 0 .6rem;padding:0;display:flex;flex-direction:column;gap:.3rem}.eg-trip li a{display:block;border:1px solid var(--tv-line);border-radius:.55rem;padding:.45rem .6rem;color:var(--tv-ink);text-decoration:none;background:var(--tv-field)}
  .eg-trip li a b{display:block;color:var(--tv-link,#92400E)}.eg-trip li a small{color:var(--tv-meta)}.eg-trip .eg-plan{display:inline-flex;border-radius:999px;background:var(--tv-btn,#92400E);color:var(--tv-btn-text,#E4DCC5);padding:.45rem .9rem;font-weight:700;text-decoration:none}
  .eg-trip .eg-tx{position:absolute;top:.55rem;right:.55rem;width:30px;height:30px;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);font-size:1.1rem;line-height:1;cursor:pointer}
  .eg-rep{background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.7rem;padding:.6rem .75rem;margin:.5rem 0}.eg-rep select,.eg-rep input{font:inherit;font-size:.85rem;border:1px solid var(--tv-line-strong);border-radius:.45rem;padding:.3rem .4rem;margin:.2rem 0;max-width:100%}
</style>
<script src="/js/engage.js" defer></script>
H;
}

/** The bar under a page's header. $o: title (for Share), likes (default true), comments (default true), report ("experience:12"). */
function tv_engage_bar(PDO $pdo, string $kind, string $ref, array $o = []): string
{
    $uid = Auth::currentUserId();
    $views = Engage::views($pdo, $kind, $ref);
    $ic = fn (string $p) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
    $h = tv_engage_assets() . '<div class="eg-bar" data-eg-kind="' . tv_e($kind) . '" data-eg-ref="' . tv_e($ref) . '" data-eg-title="' . tv_e((string) ($o['title'] ?? '')) . '">';
    // views always show, 0 included (Jason, 2026-11-19: "the social numbers should always be present"; 0058 §20 note)
    $h .= '<span class="eg-n" title="' . number_format($views) . ' ' . ($views === 1 ? 'visit' : 'visits') . ' saw this page" aria-label="' . number_format($views) . ' views">'
        . $ic('<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>') . '<span>' . number_format($views) . '</span></span>';
    if (in_array($kind, ['lm', 'rec', 'place'], true) && is_file(__DIR__ . '/../lib/Trips.php')) {   // added to trips this month (decisions/0174 §1; Jason, 2026-11-20)
        require_once __DIR__ . '/../lib/Trips.php';
        $tn = Trips::addedThisMonth($pdo, $kind, $ref);
        if ($tn > 0 || Trips::SHOW_ZERO_TRIPS) { $tw = 'Added to ' . number_format($tn) . ' trip' . ($tn === 1 ? '' : 's') . ' this month';
            $h .= '<span class="eg-n" data-eg-trips title="' . $tw . '" aria-label="' . $tw . '">' . $ic('<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>') . '<span>' . number_format($tn) . '</span></span>'; }
    }
    if ($o['likes'] ?? true) {
        if ($uid) $on = Engage::liked($pdo, $kind, $ref, $uid);
        else { require_once __DIR__ . '/../lib/Guest.php'; $on = !empty(Guest::liked()[$kind . ':' . $ref]); }   // this visit's own like (decisions/0009)
        $n = Engage::likes($pdo, $kind, $ref);
        $h .= '<button type="button" class="eg-b" data-eg-like aria-pressed="' . ($on ? 'true' : 'false') . '" title="Like">' . $ic('<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/>') . '<span>' . $n . '</span></button>';
    }
    if ($o['comments'] ?? true) $h .= '<a class="eg-b" href="#comments" title="Comments">' . $ic('<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>') . '<span>' . Engage::commentCount($pdo, $kind, $ref) . '</span></a>';
    $fold = !empty($o['fold']) && !empty($o['tools']);   // on a hero: the corner keeps the numbers and ⋯ (Jason, 2026-11-19)
    if (!$fold) $h .= '<button type="button" class="eg-b" data-eg-share title="Share">' . $ic('<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.6" y1="13.5" x2="15.4" y2="17.5"/><line x1="15.4" y1="6.5" x2="8.6" y2="10.5"/>') . '<span>Share</span></button>';
    // the page's QR code, on its trvgo.us address (decisions/0094; js/short-link.js)
    if (!$fold) $h .= '<button type="button" class="eg-b" data-qr title="QR code" aria-label="QR code for this page">' . $ic('<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h.01M17 20h4v-3"/>') . '</button>';
    $first = '';
    if ($fold) $first = '<button type="button" role="menuitem" data-eg-share-m>' . $ic('<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.6" y1="13.5" x2="15.4" y2="17.5"/><line x1="15.4" y1="6.5" x2="8.6" y2="10.5"/>') . 'Share</button>'
        . (($o['qr'] ?? true) ? '<button type="button" role="menuitem" data-eg-qr-m>' . $ic('<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h.01M17 20h4v-3"/>') . 'QR code</button>' : '')
        . (string) ($o['first'] ?? '');
    if (!empty($o['tools'])) {
        $tt = (array) $o['tools'];
        if (in_array($kind, ['listing', 'lm', 'rec', 'place'], true)) {   // Add to a trip (decisions/0174 §2): the rules say whether, never hidden
            require_once __DIR__ . '/../lib/Trips.php';
            if (!empty($o['fold']) && !isset($tt['link']) && (string) ($o['title'] ?? '') !== '') $tt['link'] = (string) $o['title'];   // Link to (the hero's ⋯)
            if (!array_key_exists('claim', $tt)) $tt['claim'] = ['', $kind === 'listing' ? 'Already claimed by its business.' : 'Only a business\'s or an organization\'s listing can be claimed.'];
            $tt['add'] = [$kind, $ref, (string) ($o['title'] ?? ''), Trips::ready($pdo) ? Trips::addable($pdo, $kind, $ref) : 'Coming with the next site update.'];
            $h .= tv_trips_script();
        }
        $h .= tv_engage_tools($tt, (string) ($o['report'] ?? ''), $ic, $first);   // ⋯ (Report moves into it)
    }   // ⋯ (Report moves into it)
    elseif (!empty($o['report'])) $h .= '<button type="button" class="eg-b r" data-eg-report="' . tv_e((string) $o['report']) . '">Report</button>';
    return $h . '</div>';
}

/**
 * ⋯ on the bar (Jason, 2026-11-19; decisions/0058 §20 note): the page's own tools in words. Each one the page has runs the
 * page's own button (one way to do a thing); one it can't offer here is greyed with why, never hidden. $t: map (the page's
 * See on map button, '#id', or ''), dir (the page's directions URL, or ['', why]), trip (['key' => place key] or ['lat', 'lon']),
 * add ([kind, ref, name, why]: Add to a trip, decisions/0174 §2; why '' when it can go in one), link (the page's name: Link to,
 * which runs the page's own Link button), claim (a URL, or ['', why]). Order (Jason, 2026-11-20: "should start with Link
 * to, then Claim, Add to Trip, then the rest"): Link to, Claim, Add to a trip, then Share, QR and the page's tools.
 * contact (a .vcf URL: businesses), suggest (data-tv-suggest's JSON fields).
 */
function tv_engage_tools(array $t, string $report, callable $ic, string $first = ''): string
{
    $off = fn (string $svg, string $label, string $why) => '<span class="eg-mo" role="menuitem" aria-disabled="true">' . $ic($svg) . '<span>' . $label . '<small>' . tv_e($why) . '</small></span></span>';
    $MAP = '<polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><path d="M8 2v16M16 6v16"/>'; $DIR = '<polygon points="3 11 22 2 13 21 11 13 3 11"/>';
    $TRIP = '<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>'; $ADD = '<path d="M12 5v14M5 12h14"/>'; $VCF = '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>';
    $EDIT = '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>'; $FLAG = '<path d="M4 22V4"/><path d="M4 4h13l-2 4 2 4H4"/>';
    $m = '<button type="button" class="eg-mx" aria-label="Close" title="Close">&times;</button>';
    $LINK = '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>';
    $CLAIM = '<path d="M3 9l1.5-5h15L21 9"/><path d="M4 9v11h16V9"/><path d="M9 20v-6h6v6"/>';
    if (!empty($t['link'])) $m .= '<button type="button" role="menuitem" data-eg-linkto>' . $ic($LINK) . 'Link to ' . tv_e((string) $t['link']) . '</button>';
    if (array_key_exists('claim', $t)) { $c = $t['claim'];
        $m .= is_string($c) && $c !== '' ? '<a role="menuitem" href="' . tv_e($c) . '">' . $ic($CLAIM) . 'Claim</a>' : $off($CLAIM, 'Claim', is_array($c) ? (string) ($c[1] ?? '') : 'Only a business\'s or an organization\'s listing can be claimed.'); }
    $add = $t['add'] ?? null;
    $m .= is_array($add) && ($add[3] ?? '') === '' ? '<button type="button" role="menuitem" data-trip-add="' . tv_e(json_encode(['kind' => $add[0], 'ref' => (string) $add[1], 'name' => (string) $add[2]], JSON_UNESCAPED_UNICODE)) . '" aria-haspopup="dialog">' . $ic($ADD) . 'Add to a trip</button>'
        : $off($ADD, 'Add to a trip', is_array($add) ? (string) $add[3] : 'This page isn\'t a place you can visit.');
    $m .= $first . '<!--eg-more-->';
    $map = $t['map'] ?? '';
    if (array_key_exists('map', $t)) $m .= is_array($map) && isset($map['lat'], $map['lon']) ? '<button type="button" role="menuitem" data-eg-map="' . tv_e(json_encode($map)) . '">' . $ic($MAP) . 'See on the map</button>'
        : (is_string($map) && $map !== '' ? '<button type="button" role="menuitem" data-eg-click="' . tv_e($map) . '">' . $ic($MAP) . 'See on the map</button>' : $off($MAP, 'See on the map', 'This page has no point on the map.'));
    if (array_key_exists('dir', $t)) { $d = $t['dir'];
        $m .= is_string($d) && $d !== '' ? '<a role="menuitem" href="' . tv_e($d) . '" target="_blank" rel="noopener"' . (!empty($t['out']) ? ' data-out="directions" data-e="' . (int) $t['out'] . '"' : '') . '>' . $ic($DIR) . 'Directions</a>'
            : $off($DIR, 'Directions', is_array($d) ? (string) ($d[1] ?? 'No street address to drive to.') : 'No street address to drive to.'); }
    $m .= !empty($t['trip']) ? '<button type="button" role="menuitem" data-eg-trip="' . tv_e(json_encode((array) $t['trip'])) . '">' . $ic($TRIP) . 'Plan a trip here</button>' : $off($TRIP, 'Plan a trip here', 'This page isn\'t tied to a place.');
    if (!empty($t['contact'])) $m .= '<a role="menuitem" href="' . tv_e((string) $t['contact']) . '">' . $ic($VCF) . 'Save contact</a>';
    if (!empty($t['suggest'])) $m .= '<button type="button" role="menuitem" data-tv-suggest="' . tv_e(json_encode((array) $t['suggest'])) . '">' . $ic($EDIT) . 'Suggest an edit</button>';
    if ($report !== '') $m .= '<button type="button" role="menuitem" data-eg-report="' . tv_e($report) . '">' . $ic($FLAG) . 'Report</button>';
    return '<span class="eg-mw"><button type="button" class="eg-b eg-more" aria-haspopup="menu" aria-expanded="false" title="More: the map, directions, a trip" aria-label="More tools">&#8943;</button>'
        . '<span class="eg-ml" role="menu" hidden>' . $m . '</span></span>';
}

/** The trips script, once per page (js/tv-trips.js: the picker, the guest's trip on the device, moving it in at sign-in). */
function tv_trips_script(): string
{
    static $done = false;
    if ($done) return '';
    $done = true;
    return '<script src="/js/tv-trips.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/tv-trips.js') . '" defer></script>';
}

/** One comment's card: Remove (the writer, staff), Report, Reply (a comment, not a reply); a team member named with the business. */
function tv_engage_comment_card(array $c, ?int $uid, bool $tidy, bool $canReply): string
{
    $mine = $uid && (int) $c['user_id'] === $uid;
    return '<div class="eg-cm' . (!empty($c['parent_id']) ? ' eg-re' : '') . '" data-c="' . (int) $c['id'] . '">' . ($mine || $tidy ? '<button type="button" data-eg-del="' . (int) $c['id'] . '">Remove</button>' : '')
        . ($uid && !$mine ? '<button type="button" data-eg-rep="' . (int) $c['id'] . '">Report</button>' : '')
        . ($canReply && empty($c['parent_id']) ? '<button type="button" data-eg-reply="' . (int) $c['id'] . '">Reply</button>' : '')
        . '<b>' . tv_e((string) $c['name']) . '</b>' . (!empty($c['team']) ? '<span class="eg-team">' . tv_e((string) $c['team']) . '</span>' : '')
        . '<small>' . tv_e(date('M j, Y', strtotime((string) $c['created_at']))) . '</small><p>' . tv_e((string) $c['body']) . '</p></div>';
}

/** The Comments section: public comments with one level of replies, the form (signed in, 18 or older), Remove for the writer and staff, Report, Reply. */
function tv_engage_comments(PDO $pdo, string $kind, string $ref, array $o = []): string
{
    $title = trim((string) ($o['title'] ?? '')); $starters = array_values(array_filter(array_map('strval', (array) ($o['starters'] ?? []))));
    $uid = Auth::currentUserId();
    $tidy = Engage::canTidy($pdo, $uid);
    $list = Engage::comments($pdo, $kind, $ref);
    $canReply = $uid !== null && Engage::repliesReady($pdo);
    $kids = [];
    foreach ($list as $c) if (!empty($c['parent_id'])) $kids[(int) $c['parent_id']][] = $c;
    $h = tv_engage_assets() . '<section class="eg-sec" id="comments" data-eg-kind="' . tv_e($kind) . '" data-eg-ref="' . tv_e($ref) . '"><h2>Comments</h2><div data-eg-list>';
    if (!$list) $h .= '<p class="eg-note" data-eg-none>No comments yet: be the first.</p>';
    foreach ($list as $c) {
        if (!empty($c['parent_id'])) continue;   // drawn under its comment
        $h .= '<div class="eg-th" data-th="' . (int) $c['id'] . '">' . tv_engage_comment_card($c, $uid, $tidy, $canReply);
        foreach ($kids[(int) $c['id']] ?? [] as $r) $h .= tv_engage_comment_card($r, $uid, $tidy, $canReply);
        $h .= '</div>';
    }
    $h .= '</div>';
    // the box that invites a comment, open right here (Jason approved, 2026-11-19): it grows when tapped; a guest's tap
    // asks them to sign in (js/tv-join.js), then back to the comments; starters under it; "Comments are public" as a note
    $ask = $title !== '' ? 'Been to ' . $title . '? Share a tip…' : 'Been here? Share a tip…';
    $st = '';
    if ($starters) { $st = '<p class="eg-starters" aria-label="Start with">'; foreach ($starters as $w) $st .= '<button type="button" class="eg-st" data-eg-start="' . tv_e($w) . '">' . tv_e($w) . '</button>'; $st .= '</p>'; }
    if (!$uid) {
        $need = ' data-needs-account="Comments come back to you: replies, edits and removing them. Create a free account (it takes a minute) to comment." data-next="' . tv_e((string) ($_SERVER['REQUEST_URI'] ?? '/') . '#comments') . '"';
        $h .= '<div class="eg-box"><button type="button" class="eg-in eg-fake"' . $need . '>' . tv_e($ask) . '</button>'
            . ($starters ? str_replace('class="eg-st"', 'class="eg-st"' . $need, $st) : '')
            . '<p class="eg-note">Comments are public. You need to be 18 or older.</p></div>';
    } else {
        require_once __DIR__ . '/../lib/AgeGate.php';
        $h .= '<form data-eg-form class="eg-box"><textarea class="eg-in eg-grow" maxlength="2000" rows="1" placeholder="' . tv_e($ask) . '" aria-label="' . tv_e($ask) . '" required></textarea>' . $st
            . (!AgeGate::confirmed($pdo, $uid) ? '<label class="eg-note"><input type="checkbox" data-eg-age required> I am 18 or older</label>' : '')
            . '<p class="eg-row"><button class="eg-b" type="submit">Post comment</button> <span class="eg-note" data-eg-msg></span></p><p class="eg-note">Comments are public.</p></form>';
    }
    return $h . '</section>';
}
