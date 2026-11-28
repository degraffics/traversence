=== FILE: files/app/lib/Claims.php.region-1.php ===
// ANCHOR: replaces     // ------------------------------------------------------------------ who might claim it (Jason, 2026-11-19; decisions/0070 note)
// through     }
// (that is, the closing brace of kind(): the line straight after         return $memo[$e] = $k;)
// Live Claims.php is over 60 KB, so only this region and region-2 are given. agency(), domains() and everything after are unchanged.
// What changes here:
//   - care in KIND_GROUPS, KIND_ROLES and KIND_WORDS;
//   - the kind() docblock.
// kind()'s body is unchanged: the map lookup covers care, and only public goes on to the nation check.
    // ------------------------------------------------------------------ who might claim it (Jason, 2026-11-19; decisions/0070 note)

    /** The category groups (decisions/0073) whose listings are claimed by an agency, an organization or the people who run it, not an owner (care: decisions/0070 note, 2026-11-27/28). */
    public const KIND_GROUPS = ['Government & Public Services' => 'public', 'Non-Profit & Community' => 'community', 'Religious & Faith-Based' => 'community', 'Health & Medical' => 'care'];
    /** The roles offered on the claim form, by kind. */
    public const KIND_ROLES = [
        'business'  => ['Owner', 'Co-owner or partner', 'Manager', 'Employee', 'Other'],
        'public'    => ['Site or park manager', 'Agency staff', 'Communications or public information', 'Other'],
        'community' => ['Director, pastor or leader', 'Staff', 'Board member', 'Volunteer', 'Other'],
        'nation'    => ['Nation staff', 'Tribal enterprise staff', 'Other'],
        'care'      => ['Administrator or practice manager', 'Staff', 'Communications', 'Other'],   // decisions/0070 note, 2026-11-27/28
    ];
    /** The words, by kind: the short button, its tip (%s: the name), the claim page's title, and "yours" in its sentences. */
    public const KIND_WORDS = [
        'business'  => ['button' => 'Claim it', 'tip' => 'Own or manage %s? Claim it to add photos, hours and more.', 'title' => 'Claim your business', 'yours' => 'your business'],
        'public'    => ['button' => 'Staff? Claim it', 'tip' => 'Work for the agency that runs %s? Claim it to keep its hours, photos and notices current.', 'title' => 'Claim this place for your agency', 'yours' => 'your agency'],
        'community' => ['button' => 'Run it? Claim it', 'tip' => 'Run %s? Claim it to add photos, hours and more.', 'title' => 'Claim your organization', 'yours' => 'your organization'],
        'nation'    => ['button' => 'Nation staff? Claim it', 'tip' => 'Work for the nation that runs %s? Ask to claim it: our team checks with the nation first.', 'title' => 'A place on a nation\'s land', 'yours' => 'the nation'],
        // tip and title: decisions/0070 note, 2026-11-27/28. button and yours are drafts awaiting Design review (not in the note).
        'care'      => ['button' => 'Work here? Claim it', 'tip' => 'Work at %s? Claim it to keep its hours and services current.', 'title' => 'Claim this health care listing', 'yours' => 'your organization'],
    ];

    /** business | public | community | care | nation (a public place on a nation's land: the nation's own tools decide, 0054, 0066; care: Health & Medical, decisions/0070 note 2026-11-27/28). */
    public static function kind(PDO $pdo, int $e): string
    {
        static $memo = [];
        if (isset($memo[$e])) return $memo[$e];
        $k = 'business';
        try {
            $st = $pdo->prepare('SELECT COALESCE(g.name, c.name) AS grp, e.latitude, e.longitude FROM entities e LEFT JOIN categories c ON c.id = e.category_id LEFT JOIN categories g ON g.id = c.parent_id WHERE e.id = ?');
            $st->execute([$e]); $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $k = self::KIND_GROUPS[(string) ($r['grp'] ?? '')] ?? 'business';
            if ($k === 'public' && isset($r['latitude'], $r['longitude']) && is_file(__DIR__ . '/Nations.php')) {
                require_once __DIR__ . '/Nations.php';
                if (Nations::at($pdo, (float) $r['latitude'], (float) $r['longitude']) !== null) $k = 'nation';
            }
        } catch (Throwable $x) { error_log('Claims::kind: ' . $x->getMessage()); }
        return $memo[$e] = $k;
    }
=== END FILE ===

=== FILE: files/app/lib/Claims.php.region-2.php ===
// ANCHOR: replaces     public static function activate(PDO $pdo, int $uid, int $claimId, string $plan, bool $agreed = false): void
// through     }
// (that is, the closing brace of activate(): the line straight after
//         if ($plan !== 'free') { try { require_once __DIR__ . '/Roles.php'; Roles::log($pdo, $uid, null, 'billing', 'trial terms agreed', 'listing ' . $e . ': ' . $plan . ', 30 days, ' . ($_SERVER['REMOTE_ADDR'] ?? '')); } catch (Throwable) {} })
// The docblock above activate() is unchanged.
// Changes against draft 17b: the care guard now runs before the Terms check. A paid choice on a health care listing
// therefore gets the true reason (plans are coming), never "agree to the Terms". $e moves up with it.
    public static function activate(PDO $pdo, int $uid, int $claimId, string $plan, bool $agreed = false): void
    {
        require_once __DIR__ . '/Billing.php';
        $c = self::ownClaim($pdo, $uid, $claimId);
        if (!isset(self::PLANS[$plan])) throw new InvalidArgumentException('Pick a plan.');
        $e = (int) $c['entity_id'];
        // health care stays on Free until a decision on its own plan (decisions/0070 note, 2026-11-27/28): checked first,
        // so a paid choice never reaches the trial's Terms or Billing
        if ($plan !== 'free' && self::kind($pdo, $e) === 'care') throw new InvalidArgumentException('Health care listings are on Free for now: plans for them are coming.');
        // a paid plan's trial starts only after its checkout: the plan, the dates, what happens at the end, and the
        // Terms agreed (Jason, 2026-10-24: "no terms or confirmation upon clicking activate")
        if ($plan !== 'free' && !$agreed) throw new InvalidArgumentException('Review the trial and agree to the Terms first.');
        $cur = Billing::current($pdo, 'listing', $e);
        if ($cur && $cur['status'] !== 'trial') throw new InvalidArgumentException('This listing has a paid plan: change it on its Plans page.');
        Database::transaction(function (PDO $pdo) use ($c, $plan, $e, $cur) {
            if ($cur) Billing::endNow($pdo, (int) $cur['id'], 'another plan chosen');
            if ($plan !== 'free') Billing::trial($pdo, 0, 'listing', $e, $plan, 30);
            else Billing::syncTier($pdo, 'listing', $e);
            $pdo->prepare('UPDATE listing_claims SET plan = ?, activated_at = NOW() WHERE id = ?')->execute([$plan, $c['id']]);
        });
        if ($plan !== 'free') { try { require_once __DIR__ . '/Roles.php'; Roles::log($pdo, $uid, null, 'billing', 'trial terms agreed', 'listing ' . $e . ': ' . $plan . ', 30 days, ' . ($_SERVER['REMOTE_ADDR'] ?? '')); } catch (Throwable) {} }
    }
=== END FILE ===

=== FILE: files/website/claim.php ===
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
=== END FILE ===

=== FILE: tests/claim-kind-care.php ===
<?php
// tools/listing-tests/claim-kind-care.php: health care's claim kind (decisions/0070 note, 2026-11-27/28).
//   php tools/listing-tests/claim-kind-care.php
// Prints PASS/FAIL (or SKIP) per check and exits 1 on any FAIL.
// The kind(), domains() and activate() checks run on an in-memory SQLite fixture (SKIP without pdo_sqlite), never
// the live database. The activate() checks stop at the guards, before Billing: no trial is written and no money
// moves (QuickBooksPay is never reached).
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/lib/Claims.php';   // match claim-kinds.php's bootstrap if it sets env first (file not shown)

$fail = 0; $n = 0;
$check = function (string $what, bool $ok) use (&$fail, &$n): void {
    $n++;
    echo ($ok ? 'PASS' : 'FAIL') . "  $what\n";
    if (!$ok) $fail++;
};

// The map, as the note sets it
$check('Health & Medical maps to care', (Claims::KIND_GROUPS['Health & Medical'] ?? null) === 'care');
$check('existing groups unchanged', (Claims::KIND_GROUPS['Government & Public Services'] ?? null) === 'public'
    && (Claims::KIND_GROUPS['Non-Profit & Community'] ?? null) === 'community'
    && (Claims::KIND_GROUPS['Religious & Faith-Based'] ?? null) === 'community');
$check('care roles as the note sets them', (Claims::KIND_ROLES['care'] ?? null) === ['Administrator or practice manager', 'Staff', 'Communications', 'Other']);
$check('care title', (Claims::KIND_WORDS['care']['title'] ?? null) === 'Claim this health care listing');
$check('care tip', (Claims::KIND_WORDS['care']['tip'] ?? null) === 'Work at %s? Claim it to keep its hours and services current.');

// Every kind the page can reach has roles and all four words, and its tip takes the name once
$kinds = array_unique(array_merge(['business', 'nation'], array_values(Claims::KIND_GROUPS)));
$whole = true;
foreach ($kinds as $k) {
    $w = Claims::KIND_WORDS[$k] ?? [];
    if (empty(Claims::KIND_ROLES[$k]) || !isset($w['button'], $w['tip'], $w['title'], $w['yours']) || substr_count($w['tip'], '%s') !== 1) {
        $whole = false; echo "      missing or bad: $k\n";
    }
}
$check('every kind has roles and words (no undefined index on claim.php)', $whole);

// kind(), domains() and the activate() guard against a fixture
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP  fixture checks: pdo_sqlite not available\n";
} else {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, parent_id INTEGER)');
    $pdo->exec('CREATE TABLE entities (id INTEGER PRIMARY KEY, category_id INTEGER, latitude REAL, longitude REAL)');
    $pdo->exec('CREATE TABLE listing_claims (id INTEGER PRIMARY KEY, entity_id INTEGER, user_id INTEGER, status TEXT)');
    $pdo->exec("INSERT INTO categories VALUES (1, 'Health & Medical', NULL), (2, 'Dentist', 1), (3, 'Food & Drink', NULL), (4, 'Bakery', 3)");
    $pdo->exec('INSERT INTO entities VALUES (9001, 2, NULL, NULL), (9002, 1, NULL, NULL), (9003, 4, NULL, NULL)');
    $pdo->exec("INSERT INTO listing_claims VALUES (501, 9001, 77, 'managed'), (503, 9003, 77, 'managed')");

    $check('a dentist (child of Health & Medical) is care', Claims::kind($pdo, 9001) === 'care');
    $check('a listing filed on the group itself is care', Claims::kind($pdo, 9002) === 'care');
    $check('a bakery stays business', Claims::kind($pdo, 9003) === 'business');
    $check('care proof: the listing\'s own domain only', Claims::domains($pdo, 9001, 'https://www.example-clinic.com/hours') === ['example-clinic.com']);
    $check('care with no website: no domain route', Claims::domains($pdo, 9001, '') === []);

    if (!is_file($root . '/app/lib/Billing.php')) {
        echo "SKIP  activate() guard: app/lib/Billing.php not found\n";
    } else {
        $msg = function (callable $f): string { try { $f(); return '(no error)'; } catch (Throwable $x) { return $x->getMessage(); } };
        $care = 'Health care listings are on Free for now: plans for them are coming.';
        $check('activate(): a paid plan on care is refused, Terms agreed', $msg(fn () => Claims::activate($pdo, 77, 501, 'core', true)) === $care);
        $check('activate(): a paid plan on care is refused with the care reason, not the Terms', $msg(fn () => Claims::activate($pdo, 77, 501, 'basic', false)) === $care);
        $check('activate(): business still asks for the Terms first', $msg(fn () => Claims::activate($pdo, 77, 503, 'core', false)) === 'Review the trial and agree to the Terms first.');
    }
}

// The page (source check; the path is the lane's to confirm)
$claimPage = '';
foreach ([$root . '/website/claim.php', $root . '/claim.php'] as $p) if (is_file($p)) { $claimPage = (string) file_get_contents($p); break; }
if ($claimPage === '') echo "SKIP  claim.php not found at website/claim.php or claim.php\n";
else $check('claim.php step 3 names health care\'s plans as coming', str_contains($claimPage, 'Plans for health care listings are coming'));

echo "\n" . ($n - $fail) . "/$n passed\n";
exit($fail ? 1 : 0);
=== END FILE ===

=== FILE: faq/faq-entries.md ===
**Can I claim a clinic, pharmacy or other health care listing?**
Yes, if you work there. Open the listing and claim it. The claim page is titled "Claim this health care listing" and asks your role:
- Administrator or practice manager;
- Staff;
- Communications;
- Other.

Once you've confirmed the code we email to your account, you can edit the listing's description, hours, services and photos. Its name, phone, address and website open up once your claim is verified.

**How do I get a health care listing verified?**
1. Confirm the code we email to your account.
2. Get the ✓ Verified mark. The quickest way is a code sent to an email address at the practice's own website domain. This is offered when the listing has a website on file. You can also:
   - send us a verification request, or text your claim's code;
   - send a document;
   - ask for a call or visit.

**Can I list myself as an individual doctor or provider?**
No. Traversence lists places, not individual people. Claim the clinic or practice where you work instead.

**Are there paid plans for health care listings?**
Not yet. Coming: plans of their own for health care listings. Until then, a claimed health care listing is on Free and gets everything a claimed listing gets on Free.
=== END FILE ===

=== FILE: release.json ===
{
  "name": "care-claim-kind",
  "gate": "Gate 3 (hours and services kept current by the people who run them). Frontend & Shop, after Monday's four and search release 1. Design review first. Tests at 1280 and 390.",
  "what": "Health & Medical listings take their own claim kind, 'care' (decisions/0070 note, 2026-11-27/28). It brings their own roles, tip and title, proof by the listing's own domain first, and Free only (refused server-side in Claims::activate). claim.php step 3 says plans for health care listings are coming. DRAFT from job 17b, reviewed against the live files: not run, not applied, not tested.",
  "files": [
    "files/app/lib/Claims.php.region-1.php",
    "files/app/lib/Claims.php.region-2.php",
    "files/website/claim.php",
    "tests/claim-kind-care.php",
    "faq/faq-entries.md"
  ],
  "decisions": [
    "decisions/0070 note 2026-11-27/28 (health care's claim kind)",
    "decisions/0070 note 2026-11-19 (claim kinds, KIND_GROUPS, agency/organization proof rule, Free for non-business kinds)",
    "decisions/0073 (category groups)",
    "decisions/0164 (people aren't places: individual providers stay out)",
    "CLAUDE.md 'Never a dead end' (staff see 'Open in the editor'; not built here, see verify)"
  ],
  "tests": [
    "tests/claim-kind-care.php -> tools/listing-tests/claim-kind-care.php (plain PHP, PASS/FAIL/SKIP, exit 1 on FAIL; SQLite fixture, never the live DB; activate() checks stop before Billing)"
  ],
  "risks": [
    "Nothing in this release has been run, applied, linted or tested. It is a draft for the lane session to verify, test and zip-check.",
    "Change from draft 17b: activate()'s care guard runs before the Terms check, so a paid choice gets the true reason, not 'agree to the Terms'. $e is assigned earlier and the later duplicate is removed.",
    "Change from draft 17b: unified diffs are replaced by whole files and regions. Claims.php is over 60 KB, so it ships as two regions (constants plus kind(), and activate()). claim.php ships whole.",
    "Change from draft 17b: the FAQ no longer names the button 'Work here? Claim it'. The listing aside (listing/view.php) is not changed in this release, and the button words await Design review, so visitors would not see that label yet.",
    "Incomplete against the note: listing/view.php is not shown, so neither required change there is drafted: (a) the aside takes its words from KIND_WORDS instead of the hard-coded 'Are you the owner?' (view.php:564); (b) staff see 'Open in the editor', never Claim. Until these land, a health care listing's aside still reads 'Are you the owner?'.",
    "KIND_WORDS['care'] 'button' ('Work here? Claim it') and 'yours' ('your organization') are drafts, not from the note. They need Design review.",
    "Only new paid choices are blocked. A Health & Medical listing claimed before this release counted as 'business' and may already hold a paid-plan trial, which is left untouched. The lane should count these and decide with Jason. No data change is made here.",
    "The server-side Free guard covers 'care' only. 'public' and 'community' still rely on the page (js/claim.js) to hide paid plans, as before. That gap exists already and is not widened here.",
    "Any other route to a paid plan (the listing's Plans page, Billing) is not shown and not guarded here.",
    "kind() reads only the main category (e.category_id) and one parent level. A three-level category tree, or a health listing whose main category sits elsewhere, falls back to 'business'. This is existing behaviour.",
    "Requiring Claims.php in the test also loads Database, StepUpAuth, Mailer and TrustScore. If those need env or config at load time, the test must use claim-kinds.php's bootstrap.",
    "listing/api/claim.php (live) declares a closure with return type 'never', which is PHP 8.1, not 8.0. This is outside this release but worth checking against the server's PHP version.",
    "No migration and no UPDATE or DELETE of data in this release."
  ],
  "verify": [
    "'Health & Medical' matches the top-level categories.name exactly (ampersand, not '&amp;' or 'and'), and is a top-level group in decisions/0073 (file not shown).",
    "The repo path of claim.php: the attachment was sync-storage/claim.php. Its '../app/' requires put it beside app/, and website/claim.php is assumed. Fix files/ and the test's probe if that's wrong.",
    "listing/view.php: file not shown. Draft and verify there: the aside from Claims::KIND_WORDS[Claims::kind($pdo, $id)] (button and tip, %s the name), and staff seeing 'Open in the editor' instead of Claim.",
    "js/claim.js: file not shown. Check that the plan step offers no paid plan for kind 'care' (it gets 'kind' from $cOut and the API), and how it shows the 422 refusal if one slips through. Check the proof ordering puts the domain route first for care, as the 0070 rule requires for agencies and organizations.",
    "tools/listing-tests/claim-kinds.php: file not shown. Match its bootstrap, or fold these checks in and update the '11 checks' count in 0070's note.",
    "claim-kinds.cjs: file not shown. Add browser checks at 1280 and 390: the Health & Medical aside (once view.php lands), the claim page title and roles, step 3's Free line, no paid plan offered, and staff seeing 'Open in the editor'.",
    "app/lib/Billing.php: file not shown. The test requires it through activate(); the guards throw before any Billing call.",
    "faq.md: file not shown. Match its heading style when placing faq/faq-entries.md.",
    "Region anchors: each region's last line is the method's closing '    }', identified by the line before it, as written in each ANCHOR comment.",
    "Lint both regions after splicing (php -l app/lib/Claims.php) and php -l website/claim.php on PHP 8.0."
  ],
  "regions": true
}
=== END FILE ===