<?php
// event/?id=… — one event a business posted (decisions/0134 §5, its minimum before Jan 1: the note of 2026-11-27, option
// (a)), built to the content-page standard (decisions/0058 §20–21): the hero, the header (its kind searches, the title,
// the place, the Link button), the engage bar (views, likes, Share and its QR: the event's GO Code), comments (public,
// 18+, reportable), views counted. Free events; tickets only through the host's own Event tickets item on the Marketplace
// (Get tickets). Showtimes are information only: no ticket link, no door note (the note). A cancelled event says so; an
// event that's over, or gone, never leaves a dead end (the host's listing, its other events, search). Taking part (P2,
// 0134's note #1594): the businesses the host invited that accepted, by name, each with its own offer and GO Code, and the
// evening as a route (0174: Add to a trip, Send to Maps), the venue last.
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8'); // bootstrap defaults to JSON
require_once __DIR__ . '/../../app/ui/page-shell.php';
require_once __DIR__ . '/../../app/lib/Events.php';
require_once __DIR__ . '/../../app/ui/explore-hero.php';
require_once __DIR__ . '/../../app/ui/engage-ui.php';
require_once __DIR__ . '/../../app/ui/maps-send.php';

$pdo = Database::connection();
$id = (int) ($_GET['id'] ?? 0);
$ev = $id ? Events::publicGet($pdo, $id) : null;
if (!$ev) {
    http_response_code(404);
    tv_page_start('Event not found', ['no_crumbs' => true]);
    echo '<div class="max-w-xl mx-auto py-10 px-4"><p data-tv-status="info" data-tv-label="Not here:" data-tv-keep>This event isn&rsquo;t listed: it may have been taken down by its host, or the link is incomplete.</p>'
        . '<p class="mt-4"><a class="underline font-semibold" href="/directory/">Get Local</a> · <a class="underline font-semibold" href="/contact.php">Ask our team</a> (support@traversence.com)</p></div>';
    tv_page_end(); exit;
}
$title = (string) $ev['title'];
$off = $ev['status'] === 'cancelled'; $over = Events::over($ev);
$kind = Events::KINDS[$ev['kind']] ?? 'Event';
// where: the host listing's town
$town = ''; $townQ = '';
try { $st = $pdo->prepare('SELECT TRIM(z.city) AS city, TRIM(z.state) AS state FROM entities e LEFT JOIN zip_coordinates z ON TRIM(z.zip) = TRIM(e.primary_zip) WHERE e.id = ? LIMIT 1'); $st->execute([(int) $ev['entity_id']]);
    $w = $st->fetch(PDO::FETCH_ASSOC) ?: []; if (!empty($w['city'])) { $town = ucwords(strtolower((string) $w['city'])) . ', ' . $w['state']; $townQ = ' near ' . $town; } } catch (Throwable) {}
$ticket = $off || $over ? null : Events::ticketOf($pdo, $ev);
$slides = [];
if ($ev['gallery_item_id']) { require_once __DIR__ . '/../../app/lib/Gallery.php'; $gi = Gallery::item($pdo, (int) $ev['gallery_item_id']); if ($gi) $slides[] = ['image' => Gallery::url($gi, 'full'), 'caption' => '']; }
$desc = mb_strimwidth(($off ? 'Cancelled. ' : '') . Events::when($ev) . ' at ' . $ev['host_name'] . ($town !== '' ? ', ' . $town : '') . '. ' . (string) $ev['about'], 0, 180, '…');
$url = 'https://traversence.com/event/?id=' . $id;
$head = '<meta property="og:type" content="website"><meta property="og:title" content="' . tv_e($title) . '"><meta property="og:description" content="' . tv_e($desc) . '"><meta property="og:url" content="' . tv_e($url) . '"><meta name="twitter:card" content="summary">';
tv_page_start($title, ['no_crumbs' => true, 'description' => $desc, 'head' => $head]);
tv_count_item('event:' . $id);
echo tv_explore_hero($pdo, ['name' => $title, 'heading' => '', 'edit' => false, 'empty' => '', 'photos_of' => $title, 'slides' => $slides,
    'scope' => $ev['micro_cluster_id'] ? 'cluster:' . (int) $ev['micro_cluster_id'] : '',
    'stock' => $slides ? null : ['subject' => strtolower($kind) . ' event', 'place' => $ev['micro_cluster_id'] ? 'cluster:' . (int) $ev['micro_cluster_id'] : '', 'owner' => 'event:' . $id, 'representative' => true, 'fallback' => 'landscape']]);
?>
<div class="evp tv-bleed mt-3" id="evp" data-link-host>
  <div class="evp-top">
    <button type="button" class="evp-pill" data-q="<?= tv_e(strtolower($kind) . ' events' . $townQ) ?>"><?= tv_e($kind) ?></button>
    <?php if ($ev['age'] !== 'all'): ?><span class="evp-pill evp-age"><?= tv_e(Events::AGES[$ev['age']] ?? '') ?></span><?php endif; ?>
    <span class="evp-link" data-link="place:ev:<?= $id ?>" data-link-label="<?= tv_e($title) ?>"></span>
  </div>
  <h1 class="evp-h"><?= tv_e($title) ?></h1>
  <?php if ($off): $tok = Events::buyerOrder($pdo, $ev, (int) (Auth::currentUserId() ?? 0)); ?><p data-tv-status="bad" data-tv-label="Cancelled:" data-tv-keep>Its host has cancelled this event.<?= ' <a class="underline" href="/listing/view.php?id=' . (int) $ev['entity_id'] . '">See what else is on at ' . tv_e($ev['host_name']) . '</a>.' ?></p>
  <?php if ($ev['ticket_item_id']): /* ticket holders: their order, and our team; no refund terms promised (the Design reviewer, 2026-11-27) */ ?><p data-tv-status="info" data-tv-label="Have tickets?" data-tv-keep><?= $tok !== '' ? '<a class="underline font-semibold" href="/market/order.php?t=' . tv_e($tok) . '">Open your order</a> to see where it stands and message ' . tv_e($ev['host_name']) . '.' : 'Your order is linked in the email you got when you bought them; from there you can message ' . tv_e($ev['host_name']) . '.' ?> Or <a class="underline font-semibold" href="/contact.php">ask our team</a> (support@traversence.com).</p><?php endif; ?>
  <?php elseif ($over): ?><p data-tv-status="info" data-tv-label="This has happened:" data-tv-keep>It was on <?= tv_e(Events::when($ev)) ?>. <a class="underline" href="/listing/view.php?id=<?= (int) $ev['entity_id'] ?>">See what&rsquo;s coming up at <?= tv_e($ev['host_name']) ?></a>.</p><?php endif; ?>
  <dl class="evp-facts">
    <div><dt>When</dt><dd><?= tv_e(Events::when($ev)) ?></dd></div>
    <div><dt>Where</dt><dd><a class="evp-name" href="/listing/view.php?id=<?= (int) $ev['entity_id'] ?>"><?= tv_e($ev['host_name']) ?></a><?= $town !== '' ? ', ' . tv_e($town) : '' ?> <span class="evp-host">· the host</span></dd></div>
    <div><dt>Cost</dt><dd><?= $ev['ticket_item_id'] ? ($off ? 'Tickets: sales stopped' : 'Tickets') : 'Free' ?></dd></div>
  </dl>
  <?php if ($ticket): ?><p class="evp-buy"><a class="evp-go" href="<?= tv_e($ticket['url']) ?>"><?= tv_icon('cart') ?>Get tickets</a> <span><?= tv_e($ticket['title']) ?>, sold by <?= tv_e($ev['host_name']) ?> on the Marketplace.</span></p>
  <?php elseif ($ev['ticket_item_id'] && !$off && !$over): ?><p data-tv-status="info" data-tv-label="Tickets:" data-tv-keep>None on sale right now. <a class="underline" href="/listing/view.php?id=<?= (int) $ev['entity_id'] ?>#contact">Ask <?= tv_e($ev['host_name']) ?></a>.</p><?php endif; ?>
  <?= tv_engage_bar($pdo, 'event', (string) $id, ['title' => $title]) ?>
</div>
<?php if (trim((string) $ev['about']) !== ''): ?><section class="evp-card tv-bleed mt-3"><h2 class="evp-h2">About</h2><p class="evp-about"><?= nl2br(tv_e((string) $ev['about'])) ?></p></section><?php endif; ?>
<?php if (trim((string) $ev['showtimes']) !== ''): /* information only: no ticket link, no door note (the note of 2026-11-27) */ ?><section class="evp-card tv-bleed mt-3"><h2 class="evp-h2">Showtimes</h2><p class="evp-about"><?= nl2br(tv_e((string) $ev['showtimes'])) ?></p><p class="evp-meta">As listed by <?= tv_e($ev['host_name']) ?>. Times are local (<?= tv_e((new DateTime('now', new DateTimeZone((string) ($ev['zone'] ?: 'America/Phoenix'))))->format('T')) ?>).</p></section><?php endif; ?>
<?php $viewer = Auth::currentUser(); $parts = Events::publicParticipants($pdo, $ev, $viewer ? ['id' => (int) $viewer['id']] : null); if ($parts):
  $ACT = ['call' => 'phone', 'directions' => 'route', 'website' => 'globe', 'contact' => 'calendar'];
  $hostRow = []; try { $q = $pdo->prepare('SELECT latitude, longitude FROM entities WHERE id = ?'); $q->execute([(int) $ev['entity_id']]); $hostRow = $q->fetch(PDO::FETCH_ASSOC) ?: []; } catch (Throwable) {}
  require_once __DIR__ . '/../../app/lib/ListingActions.php';
  $hostActs = ListingActions::actions($pdo, (int) $ev['entity_id'], null);
  $stops = array_values(array_filter(array_column($parts, 'stop')));
  if (isset($hostActs['directions']) && ($hostRow['latitude'] ?? null) !== null) $stops[] = ['lat' => $hostRow['latitude'], 'lon' => $hostRow['longitude'], 'name' => (string) $ev['host_name']];
  $route = tv_maps_attrs($stops, $title . ': the evening'); ?>
<section class="evp-parts mt-4" id="evp-parts"><h2 class="evp-h2">Taking part</h2>
  <p class="evp-meta" style="margin:0 0 .6rem">Businesses <?= tv_e($ev['host_name']) ?> invited, in order of their names. Each offer is the business&rsquo;s own: ask it about the details.</p>
  <div class="evp-pg">
  <?php foreach ($parts as $pt): $nm = tv_e($pt['name']); ?>
    <article class="evp-pc tv-bleed">
      <div class="evp-pt"><a class="evp-name" href="/listing/view.php?id=<?= $pt['id'] ?>"><?= $nm ?></a>
        <span class="evp-ics"><?php foreach ($ACT as $k => $icon): if (!isset($pt['acts'][$k])) continue; $a = $pt['acts'][$k]; $ext = preg_match('#^https?://#', $a['href']); ?><a class="evp-ic" href="<?= tv_e($a['href']) ?>"<?= $ext ? ' target="_blank" rel="noopener"' : '' ?> title="<?= tv_e($a['title'] . ': ' . $pt['name']) ?>" aria-label="<?= tv_e($a['title'] . ': ' . $pt['name']) ?>" data-out="<?= tv_e($a['out']) ?>" data-e="<?= $pt['id'] ?>"><?= tv_icon($icon) ?></a><?php endforeach; ?><button type="button" class="evp-ic" data-trip-add="<?= tv_e(json_encode(['kind' => 'listing', 'ref' => (string) $pt['id'], 'name' => $pt['name']], JSON_UNESCAPED_UNICODE)) ?>" aria-haspopup="dialog" title="Add <?= $nm ?> to a trip" aria-label="Add <?= $nm ?> to a trip"><?= tv_icon('plus') ?></button></span>
        <span class="evp-plink" data-link="business:<?= $pt['id'] ?>" data-link-label="<?= $nm ?>"></span></div>
      <?php if ($pt['town'] !== ''): ?><p class="evp-meta" style="margin:.15rem 0 0"><?= tv_e($pt['town']) ?></p><?php endif; ?>
      <?php if ($pt['offer'] !== '' || $pt['code']): ?><p class="evp-offer"><b>Their offer:</b> <?= $pt['offer'] !== '' ? tv_e($pt['offer']) : '' ?><?= $pt['code'] ? ($pt['offer'] !== '' ? ' · ' : '') . 'code <code>' . tv_e($pt['code']['code']) . '</code>' . ($pt['code']['ends'] ? ' until ' . tv_e(date('M j', strtotime((string) $pt['code']['ends']))) : '') : '' ?></p><?php endif; ?>
      <?php if ($pt['go'] !== ''): ?><p class="evp-go-c"><?= tv_icon('qr') ?> GO Code <a href="<?= tv_e($pt['go']) ?>"><?= tv_e(preg_replace('#^https?://#', '', $pt['go'])) ?></a></p><?php endif; ?>
    </article>
  <?php endforeach; ?>
  </div>
  <?php if ($route !== ''): ?><p class="evp-route"><a class="evp-rt" <?= $route ?> aria-haspopup="dialog"><?= tv_icon('route') ?>Send the evening to Maps</a> <span><?= count($stops) ?> stops, ending at <?= tv_e($ev['host_name']) ?>. Places without Directions are left out.</span></p><?= tv_maps_script() ?><?php endif; ?>
</section>
<script src="/js/tv-trips.js?v=<?= (int) @filemtime(__DIR__ . '/../js/tv-trips.js') ?>" defer></script>
<?php endif; ?>
<?php $more = array_values(array_filter(Events::forEntity($pdo, (int) $ev['entity_id'], 4), fn ($r) => (int) $r['id'] !== $id)); if ($more): ?>
<section class="evp-more mt-4"><h2 class="evp-h2">More at <?= tv_e($ev['host_name']) ?></h2><?= Events::html($more, false) ?></section><?php endif; /* a heading and the event cards, as the listing's Coming up here: one 3px edge on phones */ ?>
<?= tv_engage_comments($pdo, 'event', (string) $id) ?>
<style>
  .evp,.evp-card{background:var(--tv-card-a,#FFFBF2);border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.9rem;padding:.9rem 1rem}
  .evp{position:relative}
  .evp-top{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center;padding-right:2.5rem}
  .evp-link{position:absolute;top:.7rem;right:.8rem}
  .evp-pill{border:1px solid var(--tv-line-strong,#CBB98D);background:var(--tv-sec,#F7EFDC);color:var(--tv-link,#92400E);border-radius:999px;padding:.12rem .6rem;font-size:.75rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;cursor:pointer}
  .evp-age{cursor:default;color:var(--tv-meta,#5C4632)}
  .evp-h{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:clamp(1.5rem,3.4vw,2.1rem);line-height:1.15;color:var(--tv-ink,#3E2B1F);margin:.4rem 0 .5rem;overflow-wrap:anywhere}
  .evp-facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(12rem,100%),1fr));gap:.5rem 1rem;margin:.4rem 0 .6rem}
  .evp-facts dt{font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--tv-meta,#8A7355)}
  .evp-facts dd{margin:.1rem 0 0;font-size:.95rem;color:var(--tv-ink,#3E2B1F)}.evp-facts a{color:var(--tv-link,#92400E);text-decoration:underline}
  .evp-name{color:#92400E;text-decoration:underline;font-weight:700}.evp-host{font-size:.8rem;color:var(--tv-meta,#8A7355)}
  .evp-buy{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem .8rem;margin:.2rem 0 .7rem}.evp-buy span{font-size:.85rem;color:var(--tv-meta,#5C4632)}
  .evp-go{display:inline-flex;align-items:center;gap:.4rem;background:var(--tv-btn,#92400E);color:var(--tv-btn-text,#FFFBF2);border-radius:.7rem;padding:.6rem 1.1rem;font-weight:700;text-decoration:none}
  .evp-go:hover{background:var(--tv-btn-hover,#78350F)}.evp-go .ta-svg{width:16px;height:16px}
  .evp-h2{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:1.15rem;color:var(--tv-ink,#3E2B1F);margin:0 0 .4rem}
  .evp-about{font-size:.95rem;line-height:1.6;color:var(--tv-ink,#3E2B1F);margin:0;overflow-wrap:anywhere}
  .evp-meta{font-size:.8rem;color:var(--tv-meta,#8A7355);margin:.4rem 0 0}
  /* Taking part: card anatomy (decisions/0058 §11): the name the link top left, Link top right, round icons on the name's line (under it on phones) */
  .evp-pg{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(17rem,100%),1fr));gap:.6rem}
  .evp-parts>.evp-h2,.evp-parts>p{padding:0 .25rem}   /* a heading over the cards, never a card around them (the Design reviewer, E2's More at) */
  .evp-pc{position:relative;background:var(--tv-card-a,#FFFBF2);border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.9rem;padding:.7rem .8rem}
  .evp-pt{display:flex;flex-wrap:wrap;align-items:center;gap:.35rem .5rem;padding-right:2.4rem}
  .evp-plink{position:absolute;top:.55rem;right:.6rem}
  .evp-ics{display:inline-flex;flex-wrap:wrap;gap:.3rem}
  .evp-ic{width:30px;height:30px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;background:var(--tv-card-a,#FFFBF2);border:1px solid var(--tv-line-strong,#CBB98D);color:var(--tv-icon,#B45309);cursor:pointer;padding:0}
  .evp-ic .ta-svg{width:15px;height:15px}.evp-ic:hover{border-color:var(--tv-icon,#B45309)}
  .evp-offer{font-size:.9rem;color:var(--tv-ink,#3E2B1F);margin:.4rem 0 0;overflow-wrap:anywhere}.evp-offer code{font-weight:700;background:var(--tv-sec,#F7EFDC);border-radius:.3rem;padding:0 .3rem}
  .evp-go-c{display:flex;align-items:center;gap:.3rem;font-size:.82rem;color:var(--tv-meta,#5C4632);margin:.35rem 0 0}.evp-go-c a{color:var(--tv-link,#92400E);text-decoration:underline}.evp-go-c .ta-svg{width:14px;height:14px;color:var(--tv-icon,#B45309)}
  .evp-route{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem .8rem;margin:.7rem 0 0}.evp-route span{font-size:.82rem;color:var(--tv-meta,#5C4632)}
  .evp-rt{display:inline-flex;align-items:center;gap:.4rem;border:1px solid var(--tv-btn,#92400E);color:var(--tv-link,#92400E);border-radius:.7rem;padding:.5rem .9rem;font-weight:700;text-decoration:none;background:var(--tv-card-a,#FFFBF2)}.evp-rt .ta-svg{width:16px;height:16px}
  @media (max-width:767px){.evp,.evp-card{border-radius:.5rem;padding:.8rem .75rem}.evp-pg{gap:3px}.evp-pc{border-radius:.5rem}.evp-ics{flex-basis:100%}}
</style>
<script src="/js/link-button.js?v=<?= (int) @filemtime(__DIR__ . '/../js/link-button.js') ?>" defer></script>
<script<?= tv_nonce() ?>>document.getElementById('evp').addEventListener('click', function (e) { var b = e.target.closest('[data-q]'); if (b && window.TvSearch) TvSearch.open(b.getAttribute('data-q')); });</script>
<?php tv_page_end();
