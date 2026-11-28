<?php
// claim.php: claim a listing, or add a business that isn't listed (decisions/0070).
//
//   /claim.php?listing=123            claim a listing (or see your claim on it)
//   /claim.php?listing=123&dispute=1  question the owner of a claimed listing
//   /claim.php?listing=123&mode=contribute  help complete a listing (anyone)
//   /claim.php?new=1                  add a business that isn't listed yet
//
// Step 1, an email code to your account: the listing is "Managed by owner". Step 2, text the claim's code to the
// Verify number from the phone on the listing: ✓ Verified (or an admin contacts you within 48 hours). Other ways: an
// email at the business's own domain, a document, or an appointment. A second request is held, never just refused.
require_once __DIR__ . '/../app/ui/page-shell.php';
require_once __DIR__ . '/../app/lib/Database.php';
require_once __DIR__ . '/../app/lib/Consent.php';

$pdo = Database::connection();
$me = class_exists('Auth') ? Auth::currentUser() : null;
if (!class_exists('Auth')) { require_once __DIR__ . '/../app/bootstrap.php'; header('Content-Type: text/html; charset=utf-8'); $me = Auth::currentUser(); }
require_once __DIR__ . '/../app/lib/Claims.php';
require_once __DIR__ . '/../app/lib/VerifyRequests.php';
require_once __DIR__ . '/../app/lib/AddBusiness.php';
require_once __DIR__ . '/../app/lib/ListingCategories.php';
require_once __DIR__ . '/../app/ui/category-fields.php';
require_once __DIR__ . '/../app/lib/crawler/IntakeStager.php';
$ready = Claims::ready($pdo);

$id = (int) ($_GET['listing'] ?? 0);
$adding = !empty($_GET['new']) || $id === 0;
$contribute = !$adding && ($_GET['mode'] ?? '') === 'contribute';
$disputing = !$adding && !empty($_GET['dispute']);
$biz = $id > 0 ? Claims::listing($pdo, $id) : null;
require_once __DIR__ . '/../app/lib/PlaceScope.php';
$notBiz = $biz ? PlaceScope::notABusiness($pdo, (int) $biz['id']) : '';   // a drop box: nothing to claim, but always a way to help (never a dead end)
if ($notBiz !== '' && !$adding) $contribute = true;
// who might claim it (Jason, 2026-11-19; decisions/0070 note): a business, a public place, a community group, a place on a nation's land
$kind = $biz ? Claims::kind($pdo, (int) $biz['id']) : 'business';
$kw = Claims::KIND_WORDS[$kind];
$agency = $biz && in_array($kind, ['public', 'nation'], true) ? Claims::agency($pdo, (int) $biz['id']) : null;
$uid = $me ? (int) $me['id'] : 0;
$holding = $biz && $ready ? Claims::holding($pdo, $id) : null;
$mine = $biz && $uid && $ready ? Claims::mine($pdo, $uid, $id) : null;
$myActive = $mine && in_array($mine['status'], ['managed', 'verified', 'on_hold'], true) ? $mine : null;
$missing = $uid && $ready ? Claims::missing($pdo, $uid) : [];
$near = trim(mb_substr(preg_replace('/[^\p{L}\p{N} ,.\'()-]/u', '', (string) ($_GET['near'] ?? '')), 0, 80));
$dir = tv_route(['directory/index.php' => '/directory/']) ?: '/';
$csrf = (string) ($_SESSION['csrf_token'] ?? '');
$here = $_SERVER['REQUEST_URI'] ?? '/claim.php';
$pf = [];   // from an order we prepared for this business (its thank-you page: "Put HK Parts on Traversence")
if ($adding && !empty($_GET['order'])) {
    require_once __DIR__ . '/../app/lib/ClientOrders.php';
    $po = ClientOrders::byToken($pdo, (string) $_GET['order']);
    if ($po && $po['status'] !== 'draft') $pf = ['name' => (string) $po['client_company'], 'phone' => (string) $po['client_phone'], 'email' => (string) $po['bill_email'],
        'address' => (string) $po['bill_street'], 'town' => (string) $po['bill_city'], 'state' => (string) $po['bill_state']];
}

// what this visit shows
$view = 'claim';
if ($adding) $view = 'add';
elseif ($contribute) $view = 'contribute';
elseif (!$biz) $view = 'missing';
elseif ($myActive) $view = $myActive['status'] === 'on_hold' ? 'held' : 'mine';
elseif ($disputing) $view = 'dispute';
elseif ($holding || $biz['status'] !== 'unclaimed') $view = 'taken';
if ($view === 'claim' && $kind === 'nation') $view = 'nation';   // the nation's own tools decide (0054, 0066): a request our team checks with the nation

$pageTitle = ['add' => 'Add your business', 'contribute' => 'Help complete this listing', 'held' => 'Claim Request On Hold',
              'mine' => 'Your claim', 'dispute' => 'Question this listing\'s owner', 'taken' => $kw['title'], 'nation' => $kw['title']][$view] ?? $kw['title'];
tv_page_start($pageTitle, ['no_crumbs' => true, 'back' => $biz ? '/listing/view.php?id=' . (int) $biz['id'] : $dir,
    'description' => 'Add or claim your business on Traversence: confirm by email, then one tap to get verified.']);
$e = 'tv_e';
$claim = $myActive;
$cOut = function (?array $c) use ($biz, $pdo, $kind): array {
    if (!$c) return [];
    return ['id' => (int) $c['id'], 'status' => $c['status'], 'text_code' => $c['text_code'], 'verify_number' => Claims::verifyNumber(),
            'listed_phone' => Claims::fmtPhone((string) $c['listed_phone']), 'claimant_phone' => Claims::fmtPhone((string) $c['claimant_phone']),
            'role' => $c['role_note'], 'due_at' => $c['due_at'], 'route' => $c['route'], 'has_document' => $c['doc_path'] !== '', 'plan' => $c['plan'],
            'domain' => implode(' or @', Claims::domains($pdo, (int) $biz['id'], $biz['website'] ?? '')), 'kind' => $kind, 'name' => $biz['name'] ?? '', 'request' => VerifyRequests::latest($pdo, (int) $c['id'])];
};
?>
<style>
  .cl{max-width:42rem}
  .cl-card{background:var(--tv-sec,#F7EFDC);border:1px solid rgba(203,185,141,.7);border-radius:1rem;padding:1rem 1.1rem;margin-top:.8rem}
  .cl-card h2{font-family:var(--tv-font-head,Georgia,serif);font-weight:700;font-size:1.15rem;color:var(--tv-ink,#3E2B1F)}
  .cl-s{font-size:.8rem;color:var(--tv-meta,#5C4632)}.cl-m{font-size:.75rem;color:var(--tv-meta,#8A7355)}
  .cl-in{width:100%;background:var(--tv-card-a,#FFFBF2);border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.6rem;padding:.5rem .7rem;font-size:.9rem}
  .cl-lb{display:block;font-size:.75rem;font-weight:600;margin:.6rem 0 .2rem}
  .cl-b{display:inline-flex;align-items:center;gap:.35rem;font-size:.85rem;font-weight:600;border:1px solid var(--tv-line-strong,#CBB98D);background:var(--tv-card-a,#FFFBF2);border-radius:.7rem;padding:.45rem .9rem;cursor:pointer;color:var(--tv-ink,#3E2B1F)}
  .cl-b.go{background:#B8863B;border-color:#B8863B;color:var(--tv-ink,#2A1B12)}.cl-b:disabled{opacity:.55;cursor:default}
  .cl-chk{display:flex;gap:.5rem;align-items:flex-start;font-size:.85rem;margin-top:.6rem}
  .cl-code{font-family:ui-monospace,monospace;font-size:1.4rem;font-weight:700;letter-spacing:.06em;background:var(--tv-umber,#3E2B1F);color:#F7EFDC;border-radius:.6rem;padding:.25rem .7rem;display:inline-block}
  .cl-steps{counter-reset:s;list-style:none;padding:0;margin:.6rem 0 0}
  .cl-steps li{counter-increment:s;position:relative;padding-left:1.9rem;margin:.55rem 0;font-size:.9rem}
  .cl-steps li::before{content:counter(s);position:absolute;left:0;top:0;width:1.4rem;height:1.4rem;border-radius:50%;background:var(--tv-umber,#3E2B1F);color:#F7EFDC;font-size:.75rem;display:flex;align-items:center;justify-content:center}
  .cl-steps li.done::before{content:'✓';background:var(--tv-sage,#8FBCA8);color:var(--tv-ink,#2A1B12)}
  .cl-msg{font-size:.85rem;margin-top:.6rem}.cl-msg.bad{color:var(--tv-crimson,#991B1B)}.cl-msg.ok{color:#166534}
  .cl-pill{display:inline-block;font-size:.72rem;font-weight:700;border-radius:999px;padding:.15rem .6rem;background:var(--tv-sec,#E3D3AC);color:var(--tv-ink,#3E2B1F)}
  .cl-pill.v{background:var(--tv-sage,#8FBCA8)}.cl-pill.h{background:#F2C98A}
  details.cl-more{margin-top:.6rem}details.cl-more summary{cursor:pointer;font-weight:600;font-size:.85rem;color:var(--tv-link,#92400E)}
  .cl-plan{display:flex;flex-direction:column;gap:.45rem;margin-top:.6rem}
  .cl-tier{border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.7rem;background:var(--tv-card-a,#FFFBF2)}.cl-tier.on{border-color:var(--tv-sage,#8FBCA8);box-shadow:0 0 0 2px rgba(143,188,168,.45)}
  .cl-tier-h{display:flex;align-items:center;gap:.5rem;padding:.55rem .6rem .55rem .75rem}
  .cl-tier-h label{flex:1;display:flex;gap:.55rem;align-items:flex-start;font-size:.92rem;cursor:pointer;min-width:0}.cl-tier-h input{margin-top:.25rem;accent-color:#5E8F79}
  .cl-price{color:var(--tv-link,#92400E);font-weight:700}
  .cl-tog{flex:none;width:30px;height:30px;border-radius:50%;border:1px solid var(--tv-line-strong,#CBB98D);background:var(--tv-sec,#F7EFDC);color:var(--tv-icon,#B45309);font-size:.85rem;cursor:pointer}
  .cl-tog[aria-expanded=true]{background:var(--tv-sage,#8FBCA8);border-color:var(--tv-sage,#8FBCA8);color:#1F3B30;box-shadow:0 0 6px rgba(143,188,168,.7)}
  .cl-tier-f{margin:0;padding:.1rem 1rem .7rem 2.4rem;list-style:disc;font-size:.85rem;color:var(--tv-ink,#3E2B1F)}.cl-tier-f li{margin-top:.2rem}
  @media (max-width:767px){.cl-card{border-radius:.5rem}}
</style>
<div class="cl" id="claim" data-csrf="<?= $e($csrf) ?>" data-entity="<?= $biz ? (int) $biz['id'] : 0 ?>"
     data-claim='<?= $e(json_encode($cOut($claim), JSON_UNESCAPED_UNICODE)) ?>' data-missing="<?= $e(implode(',', $missing)) ?>">
  <h1 class="font-serif font-bold text-3xl"><?= $e($pageTitle) ?></h1>
  <?php if (!empty($_GET['added']) && $myActive): ?>
  <div class="tv-bleed cl-card" style="border-color:var(--tv-sage,#8FBCA8)"><p class="cl-s"><b>Your business is live on Traversence.</b> <a class="underline text-[color:var(--tv-link)]" href="/listing/view.php?id=<?= (int) $biz['id'] ?>">See it</a>, add photos and more from your business dashboard, and send a verification request below for the ✓.</p></div>
  <?php endif; ?>
  <?php if ($biz): ?>
    <p class="cl-s mt-1"><span class="font-semibold"><?= $e($biz['name']) ?></span><?= $biz['city'] ? ', ' . $e($biz['city']) . ($biz['state'] ? ', ' . $e($biz['state']) : '') : '' ?>
      · <a class="underline text-[color:var(--tv-link)]" href="/listing/view.php?id=<?= (int) $biz['id'] ?>">See the listing</a></p>
  <?php endif; ?>

<?php if (!$ready): ?>
  <div class="tv-bleed cl-card"><p class="cl-s">Claiming isn't switched on yet. Please check back soon.</p></div>

<?php elseif ($view === 'missing'): ?>
  <div class="tv-bleed cl-card"><p class="cl-s">That listing wasn't found. You can <a class="underline" href="/claim.php?new=1">add your business</a> instead.</p></div>

<?php elseif ($view === 'add'): ?>
  <?php $kinds = AddBusiness::KINDS; $miss = $me ? Claims::missing($pdo, $uid) : []; ?>
  <p class="cl-s mt-1">Your business on Traversence in a few minutes, website or not. It goes live as soon as you confirm a code we email you, and you manage it from your phone<?= $near !== '' ? '. Adding near <b>' . $e($near) . '</b>' : '' ?>.</p>
  <?php if ($me && in_array('email', $miss, true)): ?>
  <div class="tv-bleed cl-card" id="cl-email"><h2>First, confirm your email</h2>
    <p class="cl-s mt-1">We sent a link to <b><?= $e($me['email']) ?></b> when you joined. Open it, then come back here; what you type below is kept.</p>
    <div class="mt-2"><button class="cl-b" type="button" data-resend>Send the link again</button></div><p class="cl-msg" role="status"></p></div>
  <?php endif; ?>
  <form class="tv-bleed cl-card" id="cl-new" data-signed="<?= $me ? '1' : '0' ?>" data-next="<?= $e($here) ?>" autocomplete="on">
    <h2>Your business</h2>
    <label class="cl-lb" for="n-name">Business name</label><input class="cl-in" id="n-name" name="name" required maxlength="120" value="<?= $e($pf['name'] ?? '') ?>">
    <p class="cl-m mt-1" data-name-tip hidden>Tip: businesses do best with the name on their sign. Put what you do in the categories (+) and What you offer below: that's where people's searches find you.</p>
    <?php $catsOn = ListingCategories::ready($pdo); ?>
    <div<?= $catsOn ? ' data-cat-more data-max="' . ListingCategories::MAX . '"' : '' ?>>
    <label class="cl-lb" for="n-kind">What it is</label>
    <div class="tv-cat-line"><select class="cl-in" id="n-kind" name="kind"><option value="">Choose…</option>
      <?php foreach ($kinds as $k => [$label]): ?><option value="<?= $e($k) ?>"><?= $e($label) ?></option><?php endforeach; ?></select><?= $catsOn ? tv_cat_add_button() : '' ?></div>
    <?php if ($catsOn): ?><?= tv_cat_more_rows(IntakeStager::leafCategories($pdo), [], 'cl-in') ?>
    <p class="cl-m mt-1">Tap + to pick the exact kind of business (Movie theater, Bakery…) and up to <?= ListingCategories::MAX ?> in all: people find you under each one. The first exact one becomes your main category.</p><?php endif; ?>
    </div>
    <div style="display:grid;grid-template-columns:1fr 5rem;gap:.5rem">
      <div><label class="cl-lb" for="n-town">Town (or nearest)</label><input class="cl-in" id="n-town" name="town" required maxlength="80" value="<?= $e($near !== '' ? $near : ($pf['town'] ?? '')) ?>"></div>
      <div><label class="cl-lb" for="n-state">State</label><input class="cl-in" id="n-state" name="state" maxlength="2" value="<?= $e(($pf['state'] ?? '') !== '' ? strtoupper($pf['state']) : 'AZ') ?>" style="text-transform:uppercase"></div>
    </div>
    <label class="cl-lb" for="n-phone">Business phone</label><input class="cl-in" id="n-phone" name="phone" required inputmode="tel" maxlength="20" placeholder="A cell is fine" value="<?= $e($pf['phone'] ?? '') ?>">
    <label class="cl-lb" for="n-email">Business email</label><input class="cl-in" id="n-email" name="email" type="email" required maxlength="160" autocomplete="email" value="<?= $e((string) ($me['email'] ?? ($pf['email'] ?? ''))) ?>">
    <p class="cl-m mt-1">Where we can reach the business. Yours is filled in: change it if the business has its own. It isn't shown on your listing.</p>
    <label class="cl-lb" for="n-addr">Street address</label><input class="cl-in" id="n-addr" name="address" maxlength="160" placeholder="Or use the pin below if there isn't one" value="<?= $e($pf['address'] ?? '') ?>">
    <div class="mt-2 flex gap-2 flex-wrap items-center"><button class="cl-b" type="button" data-here><?= tv_icon('pin', 'tv-ei') ?> Use where I am now</button><span class="cl-m" data-pin></span></div>
    <input type="hidden" name="point">
    <details class="cl-more"><summary>Facebook, website, hours, what you offer (optional)</summary>
      <label class="cl-lb" for="n-fb">Facebook page</label><input class="cl-in" id="n-fb" name="facebook" maxlength="300" inputmode="url" placeholder="facebook.com/yourbusiness">
      <label class="cl-lb" for="n-web">Website</label><input class="cl-in" id="n-web" name="website" maxlength="300" inputmode="url">
      <label class="cl-lb" for="n-hours">Hours</label><input class="cl-in" id="n-hours" name="hours" maxlength="300" placeholder="Mon–Fri 8–5, Sat 9–1">
      <label class="cl-lb" for="n-offers">What you offer</label><textarea class="cl-in" id="n-offers" name="offers" rows="3" maxlength="1000"></textarea>
      <p class="cl-m mt-2">Photos: add them from your listing as soon as it's live.</p>
    </details>
    <?php if ($me && in_array('phone', $miss, true)): ?>
    <label class="cl-lb" for="n-me">Your phone</label><input class="cl-in" id="n-me" name="account_phone" required inputmode="tel" maxlength="20">
    <p class="cl-m mt-1">Private: saved to your account, and only our staff see it, so we can reach you about your listing.</p>
    <?php endif; if ($me && in_array('verification', $miss, true)): ?>
    <p class="cl-chk" data-tv-status="info" data-tv-label="First:">Confirm your email: open the link we sent you, or send it again from <a class="underline" href="/login.php">Sign in</a>.</p>
    <?php endif; if ($me && in_array('age', $miss, true)): ?>
    <label class="cl-chk"><input type="checkbox" name="age" required> <span>I'm 18 or older.</span></label>
    <?php endif; ?>
    <?php if ($me): ?>
    <div class="mt-3"><button class="cl-b go" type="submit">Email me a code</button></div>
    <?php else: ?>
    <p class="cl-s mt-3">You'll need a Traversence account, with your email and phone. What you've typed is kept while you sign in.</p>
    <div class="mt-2 flex gap-2 flex-wrap"><button class="cl-b go" type="submit" data-to="/login.php">Sign in to continue</button><button class="cl-b" type="submit" data-to="/register.php">Create an account</button></div>
    <?php endif; ?>
    <div data-dups hidden class="mt-3"></div>
    <div data-code hidden class="mt-3">
      <label class="cl-lb" for="n-code">The 6-digit code from your email</label>
      <input class="cl-in" id="n-code" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code">
      <div class="mt-2"><button class="cl-b go" type="button" data-golive>Confirm and go live</button></div>
    </div>
    <p class="cl-msg" role="status"></p>
  </form>
  <details class="tv-bleed cl-card cl-more" style="margin-top:.8rem"><summary>Not your business? Tell us about it instead</summary>
  <form id="cl-add" data-kind="missing" class="mt-2">
    <p class="cl-s">We check it and add it, and email you when it's on the site.</p>
    <label class="cl-lb" for="a-name">Business name</label><input class="cl-in" id="a-name" name="name" required maxlength="160">
    <label class="cl-lb" for="a-what">What it is</label><input class="cl-in" id="a-what" name="what" maxlength="160" placeholder="Hardware store, diner, outfitter…">
    <label class="cl-lb" for="a-addr">Street address and town</label><input class="cl-in" id="a-addr" name="address" maxlength="160" value="<?= $e($near) ?>">
    <label class="cl-lb" for="a-phone">Phone</label><input class="cl-in" id="a-phone" name="phone" inputmode="tel" maxlength="20">
    <label class="cl-lb" for="a-web">Website or Facebook page</label><input class="cl-in" id="a-web" name="website" maxlength="160" placeholder="optional">
    <label class="cl-lb" for="a-note">Anything else</label><textarea class="cl-in" id="a-note" name="note" rows="2" maxlength="600"></textarea>
    <input type="text" name="hp" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
    <div class="mt-3"><button class="cl-b" type="submit">Send</button></div>
    <p class="cl-msg" role="status"></p>
  </form></details>

<?php elseif ($view === 'contribute'): ?>
  <?php if ($notBiz !== ''): ?><p data-tv-status="info" data-tv-label="Nothing to claim:"><?= $e($notBiz) ?> You can still tell us what's wrong or missing (its pickup times, a moved box).</p><?php endif; ?>
  <p class="cl-s mt-1">Share what you know, such as a phone number, hours or what they do. Anyone can suggest details<?= $notBiz !== '' ? '; our team checks them' : '; the owner confirms them when they claim the listing' ?>.</p>
  <form class="tv-bleed cl-card" id="cl-add" data-kind="fix">
    <label class="cl-lb" for="a-phone">Phone</label><input class="cl-in" id="a-phone" name="phone" inputmode="tel" maxlength="20">
    <label class="cl-lb" for="a-web">Website</label><input class="cl-in" id="a-web" name="website" maxlength="160" placeholder="optional">
    <label class="cl-lb" for="a-hours">Hours</label><input class="cl-in" id="a-hours" name="hours" maxlength="160" placeholder="optional">
    <label class="cl-lb" for="a-note">Anything else</label><textarea class="cl-in" id="a-note" name="note" rows="2" maxlength="600"></textarea>
    <input type="text" name="hp" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
    <div class="mt-3"><button class="cl-b go" type="submit">Send</button></div>
    <p class="cl-msg" role="status"></p>
  </form>

<?php elseif (!$me): ?>
  <div class="tv-bleed cl-card">
    <h2>Sign in to claim</h2>
    <p class="cl-s mt-1">Claiming takes a few minutes: an email code, then a quick text. You'll need a Traversence account (18 or older).</p>
    <div class="flex gap-2 flex-wrap mt-3">
      <a class="cl-b go" href="/login.php?next=<?= $e(urlencode($here)) ?>">Sign in</a>
      <a class="cl-b" href="/register.php?next=<?= $e(urlencode($here)) ?>">Create an account</a>
    </div>
  </div>

<?php elseif ($view === 'claim'): ?>
  <ol class="tv-bleed cl-steps cl-card" aria-label="How claiming works">
    <li><b>Confirm by email.</b> We send a code to <?= $e($me['email']) ?>. You can then edit your description, hours, services and photos.</li>
    <li><b>Get verified.</b> One tap sends us a verification request (or text your claim's code). Once we confirm, your name, phone, address and website open up, and the listing shows ✓ Verified.</li>
    <li><b><?= $kind === 'business' ? 'Choose a plan.' : 'Your plan.' ?></b> <?= $kind === 'business' ? 'Free, or a paid plan (free trial for now).' : ($kind === 'care' ? 'Free. Plans for health care listings are coming; until then, everything a claimed listing gets on Free is yours.' : 'Free. Plans for agencies and nonprofits are coming; until then, everything a claimed listing gets on Free is yours.') ?></li>
  </ol>
  <?php if ($kind === 'public'): // who runs it: its Nexus (decisions/0054), never typed in as its name ?>
  <div class="tv-bleed cl-card"><h2>Who runs it</h2>
    <?php if ($agency): ?><p class="cl-s mt-1">Run by <b><?= $e($agency['chain']) ?></b>. Your claim covers this place. Not the right agency? Say which one below; our team checks it.</p>
    <?php else: ?><p class="cl-s mt-1">We don't know yet which agency runs it. Say which one below (optional): our team checks it and links it.</p><?php endif; ?>
    <p class="cl-chk mt-2" data-tv-status="info" data-tv-label="Coming:">Claiming for the whole agency, every place it runs, in one go. For now, claim each place you look after.</p></div>
  <?php endif; ?>
  <form class="tv-bleed cl-card" id="cl-start">
    <h2>Step 1: email code</h2>
    <label class="cl-lb" for="c-role">Your role with <?= $e($kw['yours']) ?></label>
    <select class="cl-in" id="c-role" name="role"><?php foreach (Claims::KIND_ROLES[$kind] as $r): ?><option><?= $e($r) ?></option><?php endforeach; ?></select>
    <?php if ($kind === 'public'): ?><label class="cl-lb" for="c-agency"><?= $agency ? 'A different agency? (optional)' : 'Which agency runs it? (optional)' ?></label>
    <input class="cl-in" id="c-agency" name="agency" maxlength="120" data-spell placeholder="For example, Arizona State Parks &amp; Trails"><?php endif; ?>
    <label class="cl-lb" for="c-phone">The phone you'll text from</label>
    <input class="cl-in" id="c-phone" name="phone" inputmode="tel" maxlength="20" placeholder="Your cell is fine" value="<?= $e(Claims::fmtPhone(Claims::phone10($biz['phone'] ?? ''))) ?>">
    <?php if (in_array('email', $missing, true)): ?>
    <p class="cl-s mt-2" id="cl-email"><b>First, confirm your email.</b> Open the link we sent to <?= $e($me['email']) ?> when you joined, then come back.
      <button class="cl-b mt-1" type="button" data-resend>Send the link again</button></p>
    <?php endif; if (in_array('phone', $missing, true)): ?>
    <label class="cl-lb" for="c-me">Your phone (private: saved to your account, only our staff see it)</label>
    <input class="cl-in" id="c-me" name="account_phone" required inputmode="tel" maxlength="20">
    <?php endif; if (in_array('verification', $missing, true)): ?>
    <p class="cl-chk" data-tv-status="info" data-tv-label="First:">Confirm your email: open the link we sent you, or send it again from <a class="underline" href="/login.php">Sign in</a>.</p>
    <?php endif; if (in_array('age', $missing, true)): ?>
    <label class="cl-chk"><input type="checkbox" name="age" required> <span>I'm 18 or older.</span></label>
    <?php endif; ?>
    <div class="mt-3"><button class="cl-b go" type="submit">Email me a code</button></div>
    <div id="cl-code" hidden>
      <label class="cl-lb" for="c-code">The 6-digit code from your email</label>
      <input class="cl-in" id="c-code" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code">
      <div class="mt-3"><button class="cl-b go" type="button" id="cl-confirm">Confirm</button></div>
    </div>
    <p class="cl-msg" role="status"></p>
  </form>

<?php elseif ($view === 'taken' || $view === 'held' || $view === 'nation'): ?>
  <div class="tv-bleed cl-card">
    <span class="cl-pill h"><?= $view === 'nation' ? 'A request for our team' : 'Claim Request On Hold' ?></span>
    <?php if ($view === 'nation'): ?>
    <p class="cl-s mt-2">This place is on a nation's land, and the nation decides who speaks for it. Until the nation's own tools are ready, your request comes to our team, who check with the nation before anything changes. Leave a way to reach you and your role.</p>
    <?php else: ?>
    <p class="cl-s mt-2"><?= $view === 'held' ? 'Your request is saved.' : 'Another claim for this listing is in progress.' ?>
      Only one claim goes ahead at a time, so your request is on hold, not turned down. An admin has been told and will be in touch. Leave a way to reach you, and anything that shows <?= $e($kw['yours']) ?> runs it.</p>
    <?php endif; ?>
    <?php $hd = $myActive ? Claims::detailsOf($myActive) : []; ?>
    <form id="cl-hold">
      <label class="cl-lb" for="h-role">Your role with <?= $e($kw['yours']) ?></label>
      <input class="cl-in" id="h-role" name="role" maxlength="80" value="<?= $e($hd['role'] ?? ($myActive['role_note'] ?? '')) ?>" placeholder="Owner, manager…">
      <label class="cl-lb" for="h-phone">Phone</label>
      <input class="cl-in" id="h-phone" name="phone" inputmode="tel" maxlength="20" value="<?= $e(Claims::fmtPhone((string) ($hd['phone'] ?? ''))) ?>">
      <label class="cl-lb" for="h-note">Note for the admin</label>
      <textarea class="cl-in" id="h-note" name="note" rows="3" maxlength="1000"><?= $e($hd['note'] ?? '') ?></textarea>
      <?php if (in_array('verification', $missing, true)): ?>
      <p class="cl-chk" data-tv-status="info" data-tv-label="First:">Confirm your email: open the link we sent you, or send it again from <a class="underline" href="/login.php">Sign in</a>.</p>
      <?php endif; if (in_array('age', $missing, true)): ?>
      <label class="cl-chk"><input type="checkbox" name="age" required> <span>I'm 18 or older.</span></label>
      <?php endif; ?>
      <div class="mt-3 flex gap-2 flex-wrap"><button class="cl-b go" type="submit"><?= $view === 'held' ? 'Update my request' : 'Save my request' ?></button>
        <?php if ($view === 'held'): ?><button class="cl-b" type="button" data-withdraw>Withdraw it</button><?php endif; ?></div>
      <p class="cl-msg" role="status"></p>
    </form>
    <?php if ($view === 'held' && $myActive): ?>
    <details class="cl-more"><summary>Add a document</summary>
      <form class="cl-doc mt-2"><p class="cl-m">A business license, utility bill, lease, photos of the location or back office, or a brochure. PDF, JPG or PNG, up to 25 MB. Only admins see it, and it's deleted as soon as your claim is decided.</p>
        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required class="mt-2 text-sm"> <button class="cl-b mt-2" type="submit">Send</button><p class="cl-msg" role="status"></p></form>
    </details>
    <?php endif; ?>
    <?php if ($holding && $holding['status'] === 'verified'): ?>
      <p class="cl-m mt-3">This listing has a verified owner. If you believe that's wrong, you can <a class="underline" href="/claim.php?listing=<?= (int) $biz['id'] ?>&dispute=1">question it</a>.</p>
    <?php endif; ?>
  </div>

<?php elseif ($view === 'dispute'): ?>
  <?php if (!$holding): ?>
  <div class="tv-bleed cl-card"><p class="cl-s">This listing has no owner yet, so there's nothing to question. You can <a class="underline" href="/claim.php?listing=<?= (int) $biz['id'] ?>">claim it</a>.</p></div>
  <?php else: ?>
  <form class="tv-bleed cl-card" id="cl-dispute">
    <p class="cl-s">If you own or run this business and someone else holds the listing, tell us. The owner is told and has a chance to answer; an admin decides. Nothing changes on the listing until then.</p>
    <label class="cl-lb" for="d-reason">Why you believe the listing is yours</label>
    <textarea class="cl-in" id="d-reason" name="reason" rows="4" maxlength="4000" required></textarea>
    <label class="cl-lb" for="d-file">Proof (optional): a license, bill, lease or photos</label>
    <input type="file" id="d-file" name="file" accept=".pdf,.jpg,.jpeg,.png" class="text-sm">
    <?php if (in_array('verification', $missing, true)): ?>
    <p class="cl-chk" data-tv-status="info" data-tv-label="First:">Confirm your email: open the link we sent you, or send it again from <a class="underline" href="/login.php">Sign in</a>.</p>
    <?php endif; if (in_array('age', $missing, true)): ?>
    <label class="cl-chk"><input type="checkbox" name="age" value="1" required> <span>I'm 18 or older.</span></label>
    <?php endif; ?>
    <div class="mt-3"><button class="cl-b go" type="submit">Send to an admin</button></div>
    <p class="cl-msg" role="status"></p>
  </form>
  <?php endif; ?>

<?php endif; ?>

  <!-- Step 2 and after: drawn by js/claim.js once a claim is Managed (or shown straight away for your own claim) -->
  <div id="cl-mine" <?= $view === 'mine' ? '' : 'hidden' ?>></div>
</div>
<script src="/js/cat-more.js?v=<?= (int) @filemtime(__DIR__ . '/js/cat-more.js') ?>"></script>
<script src="/js/claim.js?v=<?= (int) @filemtime(__DIR__ . '/js/claim.js') ?>" defer></script>
<?php tv_page_end();
