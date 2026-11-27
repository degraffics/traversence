<?php
// market/item.php?id= — a business's item on the Marketplace (decisions/0135 step 3): its published version (MarketBiz),
// never the walled catalog. The photos, the price, "From <business> in <town>", and the buttons the business chose for
// it within its plan: Message (its inbox), Make an offer (when it takes offers), Request a quote (Quotes & invoices),
// Buy now (coming with checkout). The business on its own card with the Link button, and more from its Shop.
// A business is reviewed in its listing's comments (0058), so there are none here. Views, Share, QR and Report.
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8'); // bootstrap defaults to JSON
require_once __DIR__ . '/../../app/ui/page-shell.php';
require_once __DIR__ . '/../../app/lib/MarketBiz.php';
require_once __DIR__ . '/../../app/lib/MarketOffers.php';
require_once __DIR__ . '/../../app/lib/MarketCheckout.php';
require_once __DIR__ . '/../../app/lib/MarketRules.php';
require_once __DIR__ . '/../../app/ui/market-ui.php';

$pdo = Database::connection();
$uid = Auth::currentUserId();
$it = MarketBiz::ready($pdo) ? MarketBiz::get($pdo, (int) ($_GET['id'] ?? 0)) : null;
if (!$it || !MarketBiz::canSee($pdo, $it, $uid)) {
    http_response_code(404);
    tv_page_start('Not on the Marketplace', ['no_crumbs' => true]);
    echo '<div class="text-center py-10"><h1 class="font-serif font-bold text-2xl mb-2">This item isn&rsquo;t up</h1><p class="text-sm">The business may have taken it off. <a class="underline" href="/market/">See what&rsquo;s there now</a></p></div>';
    tv_page_end();
    exit;
}
$id = (int) $it['id']; $e = (int) $it['entity_id'];
$biz = MarketBiz::entity($pdo, $e);
$live = $it['status'] === 'published';
$team = $uid && MarketBiz::onTeam($pdo, $uid, $e);
$photos = MarketBiz::photos($pdo, $e, (int) $it['product_id'], true);
$buttons = $live ? MarketBiz::buttonsFor($pdo, $it) : [];
// step 4: Buy now and Add to cart, once its checkout is live; how many are left when it tracks stock
$canCheck = MarketCheckout::ready($pdo);
[$buyOk, $buyWhy] = $canCheck && in_array('buy', $buttons, true) ? MarketCheckout::buyable($pdo, $it) : [false, ''];
$left = $canCheck ? MarketCheckout::available($pdo, $it) : null;
if (in_array('buy', $buttons, true)) $buttons = array_values(array_unique(array_merge(['buy'], $buttons)));   // buying first
$offers = $uid && in_array('offer', $buttons, true) ? MarketOffers::forItem($pdo, $id, $uid) : [];
$openOffer = (bool) array_filter($offers, fn ($o) => in_array($o['status'], MarketOffers::OPEN, true));
if ($offers) $buttons = array_values(array_unique(array_merge(['offer'], $buttons)));   // their offer first, where they left it
$more = array_values(array_filter(MarketBiz::browse($pdo, [], 7, $e), fn ($c) => $c['id'] !== $id));
$notice = !$live ? ['info', (MarketBiz::STATUSES[$it['status']] ?? $it['status']) . ':', $it['status'] === 'removed' ? (string) $it['staff_note'] : 'Only the business\'s team and ours can see this page.'] : null;

tv_page_start($it['title'], ['no_crumbs' => true, 'description' => mb_strimwidth(MarketBiz::price($it) . ' · ' . ($biz['name'] ?? '') . ' · ' . $it['place_label'] . ' · ' . $it['about'], 0, 180, '…')]);
if ($live) tv_count_item('market:i' . $id);
echo tv_market_css();
?>
<style>
  .mv-kindtag{display:inline-block;vertical-align:middle;margin-left:.4rem;font:700 .68rem system-ui,sans-serif;letter-spacing:.05em;text-transform:uppercase;background:#1F5C43;color:var(--tv-on-umber,#FFFBF2);border-radius:.35rem;padding:.15rem .45rem;text-decoration:none}
  .mv-facts{display:grid;grid-template-columns:auto 1fr;gap:.3rem .8rem;margin:.3rem 0 0;font-size:.92rem}.mv-facts dt{font-weight:700;color:var(--tv-meta,#5C4632)}.mv-facts dd{margin:0}
  @media (max-width:520px){.mv-facts{grid-template-columns:1fr}.mv-facts dd{margin-bottom:.35rem}}
  .mv{max-width:64rem;display:grid;gap:1.2rem;grid-template-columns:minmax(0,3fr) minmax(0,2fr);align-items:start}
  .mv-gal{background:var(--tv-umber,#2A1B12);border-radius:.9rem;overflow:hidden}.mv-main{aspect-ratio:4/3;display:flex;align-items:center;justify-content:center}
  .mv-main img{max-width:100%;max-height:100%;object-fit:contain;display:block}.mv-main .ta-svg{width:56px;height:56px;color:#B8863B;opacity:.6}
  .mv-strip{display:flex;gap:4px;padding:4px;overflow-x:auto;background:var(--tv-void,#1E140C)}.mv-strip button{flex:0 0 auto;width:62px;height:62px;border:2px solid transparent;border-radius:.4rem;padding:0;overflow:hidden;cursor:pointer;background:var(--tv-umber,#3E2B1F)}
  .mv-strip button[aria-current="true"]{border-color:var(--tv-sage,#8FBCA8)}.mv-strip img{width:100%;height:100%;object-fit:cover;display:block}
  .mv-side{display:grid;gap:.8rem}
  .mv-kind{display:inline-block;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--tv-link,#92400E);text-decoration:none;border:1px solid var(--tv-line-strong,#CBB98D);border-radius:999px;padding:.12rem .55rem;background:var(--tv-card-a,#FFFBF2)}
  .mv h1{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:clamp(1.4rem,3.4vw,1.9rem);line-height:1.15;color:var(--tv-ink,#3E2B1F);margin:.35rem 0 .15rem;overflow-wrap:anywhere}
  .mv-price{font-size:1.35rem;font-weight:700;color:var(--tv-ink,#3E2B1F)}.mv-where{font-size:.86rem;color:var(--tv-meta,#5C4632);margin-top:.2rem}.mv-where a{color:var(--tv-link,#92400E);text-decoration:underline}
  .mv-made{display:inline-flex;align-items:center;gap:.3rem;margin-top:.45rem;font-size:.8rem;font-weight:700;color:var(--tv-link,#92400E);text-decoration:none;border:1px solid var(--tv-line-strong,#CBB98D);border-radius:999px;padding:.15rem .6rem .15rem .45rem;background:var(--tv-sec,#F7EFDC)}.mv-made .ta-svg{width:14px;height:14px;color:var(--tv-icon,#B45309)}.mv-made:hover{border-color:#B45309}
  .mv-card{background:var(--tv-card-a,#FFFBF2);border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.9rem;padding:.85rem .95rem}.mv-card h2{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-size:1.08rem;color:var(--tv-ink,#3E2B1F);margin:0 0 .45rem}
  .mv-about{font-size:.95rem;line-height:1.6;color:var(--tv-ink,#3E2B1F);white-space:pre-line;overflow-wrap:anywhere}
  .mv-f textarea,.mv-f input{border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.6rem;padding:.45rem .55rem;font:inherit;font-size:.92rem;background:var(--tv-card-a,#fff);color:var(--tv-ink,#3E2B1F);box-sizing:border-box}
  .mv-f textarea{width:100%}.mv-f .row{display:flex;gap:.5rem;align-items:center;margin-top:.45rem;flex-wrap:wrap}
  .mv-b{border:1px solid var(--tv-line-strong,#CBB98D);background:var(--tv-card-a,#FFFBF2);color:var(--tv-ink,#3E2B1F);border-radius:999px;padding:.4rem .9rem;font-size:.86rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}
  .mv-b.go{background:var(--tv-umber,#3E2B1F);border-color:var(--tv-umber,#3E2B1F);color:var(--tv-on-umber,#FFFBF2)}.mv-b[disabled]{opacity:.55;cursor:not-allowed}
  .mv-tabs{display:flex;gap:.35rem;flex-wrap:wrap;margin-bottom:.5rem}.mv-tabs button{border:1px solid var(--tv-line-strong,#CBB98D);background:var(--tv-card-a,#FFFBF2);border-radius:999px;padding:.3rem .8rem;font-weight:600;font-size:.84rem;cursor:pointer;color:var(--tv-ink,#3E2B1F)}
  .mv-tabs button[aria-selected="true"]{background:var(--tv-umber,#3E2B1F);color:var(--tv-on-umber,#FFFBF2);border-color:var(--tv-umber,#3E2B1F)}
  .mv-biz{position:relative;padding-right:3rem}.mv-biz a.nm{color:var(--tv-link,#92400E);text-decoration:underline;font-weight:700;font-size:1rem}.mv-biz small{display:block;color:var(--tv-meta,#8A7355);font-size:.8rem;margin-top:.15rem}
  .mv-safe{font-size:.8rem;color:var(--tv-meta,#5C4632);line-height:1.5}.mv-more{grid-column:1/-1}.mv-more h2{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-size:1.15rem;color:var(--tv-ink,#3E2B1F);margin:.4rem 0 .5rem}.mv-full{grid-column:1/-1}
  @media (max-width:767px){.mv{grid-template-columns:minmax(0,1fr);gap:.7rem}.mv-gal{border-radius:.5rem}.mv-main{aspect-ratio:1/1}}
</style>
<div class="mv">
  <?php if ($notice): ?><p class="mv-full" data-tv-status="<?= $notice[0] ?>" data-tv-label="<?= tv_e($notice[1]) ?>"><?= tv_e($notice[2]) ?></p><?php endif; ?>
  <section class="mv-gal tv-bleed" aria-label="Photos">
    <div class="mv-main" id="mv-main"><?php if ($photos): ?><img src="<?= tv_e($photos[0]['full']) ?>" alt="<?= tv_e($photos[0]['alt'] ?: $it['title']) ?>"><?php else: echo tv_icon('store'); endif; ?></div>
    <?php if (count($photos) > 1): ?><div class="mv-strip"><?php foreach ($photos as $i => $ph): ?><button type="button" data-full="<?= tv_e($ph['full']) ?>" aria-current="<?= $i ? 'false' : 'true' ?>" aria-label="Photo <?= $i + 1 ?> of <?= count($photos) ?>"><img src="<?= tv_e($ph['card']) ?>" alt="" loading="lazy"></button><?php endforeach; ?></div><?php endif; ?>
  </section>
  <div class="mv-side">
    <div>
      <a class="mv-kind" href="/market/?category=<?= rawurlencode((string) $it['category']) ?>"><?= tv_e($it['category'] ?: 'Item') ?></a>
      <h1><?= tv_e($it['title']) ?></h1>
      <?php require_once __DIR__ . '/../../app/lib/ItemKinds.php'; $tvKind = ItemKinds::key((string) $it['kind']); $tvKd = ItemKinds::data($tvKind, $it['kind_data'] ?? null); ?>
      <div class="mv-price"><?= tv_e(MarketBiz::price($it) . (MarketBiz::price($it) !== 'Ask' ? ItemKinds::per($tvKind, $tvKd) : '')) ?> <a class="mv-kindtag" href="/market/?kind=<?= tv_e($tvKind) ?>"><?= tv_e(ItemKinds::name($tvKind)) ?></a></div>
      <div class="mv-where">From <a href="/listing/view.php?id=<?= $e ?>"><?= tv_e($biz['name'] ?? 'a business') ?></a> in <a href="/market/?place=<?= rawurlencode((string) $it['place_key']) ?>"><?= tv_e($it['place_label']) ?></a></div>
      <?php if ($tvMade = MarketBiz::madeIn($pdo, $it)): /* our own label, and only this (0135's note, 2026-11-27): it opens the regional section */ ?><a class="mv-made" href="/market/?section=regional" title="In Experiences &amp; Regional Goods"><?= tv_icon('pin') ?><?= tv_e($tvMade) ?></a><?php endif; ?>
    </div>
    <?php if ($live) { require_once __DIR__ . '/../../app/ui/engage-ui.php'; echo tv_engage_bar($pdo, 'mkt', 'i' . $id, ['title' => $it['title'], 'likes' => false, 'comments' => false, 'report' => $uid && !$team ? 'market_item:' . $id : '']); } ?>
    <?php if ($team): ?><a class="mv-b go" href="/business/<?= $e ?>/marketplace#p<?= (int) $it['product_id'] ?>">Manage it in your business portal</a><?php endif; ?>

    <?php if (trim((string) $it['about']) !== ''): ?><section class="mv-card tv-bleed"><h2>Details</h2><div class="mv-about"><?= tv_e($it['about']) ?></div></section><?php endif; ?>
    <?php $tvFacts = ItemKinds::facts($tvKind, $tvKd, (string) ($biz['address'] ?? '')); if ($tvFacts): /* the kind's own details (decisions/0141 §5) */ ?><section class="mv-card tv-bleed"><h2><?= tv_e(ItemKinds::name($tvKind)) ?></h2><dl class="mv-facts"><?php foreach ($tvFacts as [$fl, $fv]): ?><dt><?= tv_e($fl) ?></dt><dd><?= nl2br(tv_e($fv)) ?></dd><?php endforeach; ?></dl></section><?php endif; ?>

    <?php if ($live && !$team && $buttons): ?>
    <section class="mv-card mv-f tv-bleed" id="mv-ask">
      <?php if (count($buttons) > 1): ?><div class="mv-tabs" role="tablist"><?php foreach ($buttons as $i => $b): ?><button type="button" role="tab" data-tab="<?= $b ?>" aria-selected="<?= $i ? 'false' : 'true' ?>"><?= tv_e(MarketBiz::label($b, $it)) ?></button><?php endforeach; ?></div><?php else: ?><h2><?= tv_e(MarketBiz::label($buttons[0], $it)) ?></h2><?php endif; ?>
      <?php if (!$uid): ?>
        <p class="text-sm"><a class="mv-b go" href="/login.php?next=<?= rawurlencode('/market/item.php?id=' . $id . '#mv-ask') ?>">Sign in to <?= tv_e(lcfirst(MarketBiz::label($buttons[0], $it))) ?></a></p>
      <?php else: foreach ($buttons as $i => $b): ?>
        <div data-pane="<?= $b ?>"<?= $i ? ' hidden' : '' ?>>
          <?php if ($b === 'message'): ?>
            <textarea id="mv-msg" rows="3" maxlength="2000" aria-label="Your message">Hi, is this available?</textarea>
            <div class="row"><button type="button" class="mv-b go" data-send="message">Send</button><span class="mv-safe">Goes to <?= tv_e($biz['name'] ?? 'the business') ?>&rsquo;s inbox; their team answers.</span></div>
          <?php elseif ($b === 'quote'): ?>
            <?php $tvQk = ItemKinds::key((string) $it['kind']); /* asked in the item's own terms (decisions/0144) */ ?>
            <?php if ($tvQk !== 'service'): ?><div class="row" style="margin-top:0"><label class="mv-safe">How many <input type="number" id="mv-qty" min="1" step="1" value="<?= (int) $it['price_qty'] ?: 1 ?>" style="width:6rem"></label></div><?php else: ?><input type="hidden" id="mv-qty" value="1"><?php endif; ?>
            <textarea id="mv-qnote" rows="3" maxlength="2000" placeholder="<?= tv_e(['service' => 'What you need done, where, and when suits you', 'madetoorder' => 'What you\'d like: size, wording, colors, when you need it'][$tvQk] ?? 'Anything they should know: sizes, dates, delivery') ?>" aria-label="What you need" style="margin-top:.45rem" data-spell></textarea>
            <div class="row"><button type="button" class="mv-b go" data-send="quote"><?= tv_e(MarketBiz::label('quote', $it)) ?></button><span class="mv-safe"><?= tv_e($tvQk === 'service' ? 'They reply with a price and a time' : 'They send you a quote to approve' . ($tvQk === 'madetoorder' ? ', with any deposit and a proof to sign off' : '')) ?>; you pay them the way they offer.</span></div>
          <?php elseif ($b === 'buy'): ?>
            <?php if ($buyOk): ?>
              <?php $tvBuy = ItemKinds::buy($tvKind, $tvKd); if ($tvBuy === 'dates'): /* a stay or a rental: its dates, then checkout (decisions/0141 §5) */ ?>
              <form class="row" style="margin-top:0" method="get" action="/market/cart.php"><input type="hidden" name="item" value="<?= $id ?>">
                <label class="mv-safe"><?= $tvKind === 'booking' ? 'Check in' : 'Pick up' ?> <input type="date" name="from" required min="<?= date('Y-m-d') ?>"></label>
                <label class="mv-safe"><?= $tvKind === 'booking' ? 'Check out' : 'Bring back' ?> <input type="date" name="to" required min="<?= date('Y-m-d') ?>"></label>
                <?php if ($tvKind === 'booking'): ?><label class="mv-safe">Guests <input type="number" name="guests" min="1" max="<?= (int) ($tvKd['max_guests'] ?? 2) ?>" value="1" style="width:4.5rem"></label><?php endif; ?>
                <button type="submit" class="mv-b go"><?= $tvKind === 'booking' ? 'Check dates and book' : 'Check dates and rent' ?></button></form>
              <?php elseif ($tvBuy === 'subscribe'): ?>
              <div class="row" style="margin-top:0"><button type="button" class="mv-b go" data-send="buy">Subscribe</button></div><input type="hidden" id="mv-bqty" value="1">
              <?php else: ?>
              <div class="row" style="margin-top:0"><label class="mv-safe"><?= $tvKind === 'ticket' ? 'Tickets' : 'How many' ?> <input type="number" id="mv-bqty" min="1" max="<?= $left !== null ? (int) $left : 999 ?>" step="1" value="1" style="width:5rem"></label>
                <?php if ($left !== null): ?><span class="mv-safe"><?= (int) $left ?> left</span><?php endif; ?></div>
              <div class="row"><button type="button" class="mv-b go" data-send="buy"><?= tv_e(MarketBiz::label('buy', $it)) ?></button><button type="button" class="mv-b" data-send="cart">Add to cart</button></div>
              <?php endif; ?>
              <p class="mv-safe" style="margin:.4rem 0 0">You pay <?= tv_e($biz['name'] ?? 'the business') ?> directly<?= MarketCheckout::cardOk($pdo, $e)[0] && MarketRules::cardOk($it) ? ' by card through their own account, or another way they offer' : ', the way they offer' ?>. Traversence never holds your money.</p>
            <?php else: ?><p data-tv-status="info" data-tv-label="<?= $left === 0 ? 'Sold out:' : 'Not right now:' ?>"><?= tv_e($buyWhy) ?></p><?php endif; ?>
          <?php elseif ($b === 'offer'): ?>
            <?php foreach ($offers as $o) echo tv_market_offer($o); ?>
            <?php if (!$openOffer): ?><div class="row" style="margin-top:0"><label class="mv-safe">$ <input type="number" id="mv-amt" min="0.01" step="0.01" value="<?= $it['price_cents'] !== null ? tv_e(rtrim(rtrim(number_format((int) $it['price_cents'] / 100, 2, '.', ''), '0'), '.')) : '' ?>" style="width:7rem" aria-label="Your offer in dollars"></label><button type="button" class="mv-b go" data-send="offer">Send offer</button></div>
            <p class="mv-safe" style="margin:.4rem 0 0">They can accept, counter or decline. No payment passes through Traversence: you pay the business the way it offers.</p><?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </section>
    <?php endif; ?>

    <section class="mv-card mv-biz tv-bleed" data-link-host><h2>Sold by</h2>
      <?php if (!$team): ?><span data-link="business:<?= $e ?>" data-link-label="<?= tv_e($biz['name'] ?? '') ?>"></span><?php endif; ?>
      <a class="nm" href="/listing/view.php?id=<?= $e ?>"><?= tv_e($biz['name'] ?? 'A business') ?></a>
      <small><?= tv_e($it['place_label']) ?> · <a class="underline" href="/listing/view.php?id=<?= $e ?>#shop">Their shop</a> · reviews are in their listing&rsquo;s comments</small>
      <?php   // the INFORM Consumers Act (decisions/0142): a high-volume seller's details past $20,000 a year, and a way to report, online and by phone
      require_once __DIR__ . '/../../app/lib/Inform.php'; $tvDis = []; try { $tvDis = Inform::disclosure($pdo, $e); } catch (Throwable) {}
      if ($tvDis): ?><p class="mv-safe" style="margin:.5rem 0 0"><b>Seller:</b> <?= tv_e($tvDis['name']) ?> · <?= tv_e($tvDis['address']) ?> · <?= tv_e($tvDis['phone']) ?> · <?= tv_e($tvDis['email']) ?></p><?php endif;
      $tvRp = trim((string) getenv('LEGAL_PHONE')); ?>
      <p class="mv-safe" style="margin:.4rem 0 0">Something wrong with this item or seller? <?= $uid && !$team ? 'Use <b>Report</b> above' : 'Report it from this page' ?><?= $tvRp !== '' ? ', or call Traversence at <a class="underline" href="tel:' . tv_e(preg_replace('/[^0-9+]/', '', $tvRp)) . '">' . tv_e($tvRp) . '</a>' : '' ?>.</p>
    </section>
    <?php require_once __DIR__ . '/../../app/lib/MarketRules.php'; if (($tvNotice = MarketRules::notice($it)) !== ''): /* the rulebook's notice (decisions/0142 §4) */ ?><p data-tv-status="info" data-tv-label="Please note:"><?= tv_e($tvNotice) ?></p><?php endif; ?>
    <?php require_once __DIR__ . '/../../app/lib/StorePolicies.php'; echo StorePolicies::css() . StorePolicies::box($pdo, $e, null, false, ($biz['name'] ?? 'The business') . '\'s store policies'); /* decisions/0142 §5.3 */ ?>
    <p class="mv-safe"><?php if ($buyOk): ?>Traversence never holds your money, and no one from Traversence will ever ask you to pay them. <b>Buy now</b> pays <?= tv_e($biz['name'] ?? 'the business') ?> itself, through its own account; or pay the way it offers on its quote or invoice.<?php else: ?>No payment passes through Traversence and no one from Traversence will ever ask you to pay through the site. Pay the business the way it offers on its quote or invoice.<?php endif; ?></p>
  </div>
  <?php if ($more): ?>
  <section class="mv-more"><h2>More from <?= tv_e($biz['name'] ?? 'them') ?></h2>
    <div class="mk-grid"><?php foreach (array_slice($more, 0, 6) as $c) echo tv_market_card($c, false, false); ?></div>
  </section>
  <?php endif; ?>
</div>
<?= tv_market_js() ?>
<script src="/js/link-button.js" defer></script>
<script>
(function () {
  var main = document.getElementById('mv-main');
  document.querySelectorAll('.mv-strip button').forEach(function (b) { b.addEventListener('click', function () { var img = main.querySelector('img'); if (img) img.src = b.getAttribute('data-full');
    document.querySelectorAll('.mv-strip button').forEach(function (x) { x.setAttribute('aria-current', x === b ? 'true' : 'false'); }); }); });
  document.querySelectorAll('[data-tab]').forEach(function (t) { t.addEventListener('click', function () {
    document.querySelectorAll('[data-tab]').forEach(function (x) { x.setAttribute('aria-selected', x === t ? 'true' : 'false'); });
    document.querySelectorAll('[data-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== t.getAttribute('data-tab'); }); }); });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-send]'); if (!b) return;
    var what = b.getAttribute('data-send'), body = { id: <?= $id ?> };
    if (what === 'message') { body.action = 'item_message'; body.body = document.getElementById('mv-msg').value; }
    if (what === 'quote') { body.action = 'item_quote'; body.qty = document.getElementById('mv-qty').value; body.body = document.getElementById('mv-qnote').value; }
    if (what === 'buy') { location.href = '/market/cart.php?item=<?= $id ?>&qty=' + Math.max(1, +document.getElementById('mv-bqty').value || 1); return; }
    if (what === 'cart') {
      b.disabled = true;
      fetch('/api/market-pay.php', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': <?= json_encode((string) ($_SESSION['csrf_token'] ?? '')) ?> }, body: JSON.stringify({ action: 'add', item: <?= $id ?>, qty: Math.max(1, +document.getElementById('mv-bqty').value || 1) }) })
        .then(function (r) { if (r.status === 401) { location.href = '/login.php?next=' + encodeURIComponent(location.pathname + location.search); throw new Error('Sign in first.'); } return r.json(); })
        .then(function (d) { if (!d.ok) throw new Error((d.error && d.error.message) || 'That didn\'t work.'); TvAsk.say(d.message + ' ', { anchor: b }); var a = document.createElement('a'); a.href = '/market/cart.php'; a.className = 'mv-b'; a.textContent = 'Your cart (' + d.count + ')'; b.insertAdjacentElement('afterend', a); })
        .catch(function (x) { TvAsk.say(x.message, { anchor: b, bad: true }); }).then(function () { b.disabled = false; });
      return;
    }
    if (what === 'offer') { body.action = 'item_offer'; body.amount = document.getElementById('mv-amt').value; if (!body.amount) { TvAsk.say('Enter your offer in dollars.', { anchor: b, bad: true }); return; } }
    b.disabled = true;
    tvMarketPost(body).then(function (d) { TvAsk.say(d.message, { anchor: b }); if (what === 'offer') setTimeout(function () { location.reload(); }, 1200); else b.disabled = false; })
      .catch(function (x) { TvAsk.say(x.message, { anchor: b, bad: true }); b.disabled = false; });
  });
})();
</script>
<?php tv_page_end();
