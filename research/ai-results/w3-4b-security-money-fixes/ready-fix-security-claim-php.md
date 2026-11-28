# Release drafts for f04, f17 and f18 (listing/api/claim.php)

There are three release folders below, highest risk first. All three change the same live file, so each later release contains the earlier fixes:

- **f04** is the live file plus the ownership guard.
- **f17** contains f04 and adds its own fix.
- **f18** contains f04 and f17 and adds its own fix.

Ship them in order: f04, then f17, then f18. Nothing here has been run or verified.

=== FILE: f04/files/listing/api/claim.php ===
<?php
// /listing/api/claim.php: claiming a listing (decisions/0070). POST, signed in, X-CSRF-Token.
// JSON body {action, ...}; the document upload is multipart (action=document, claim_id, file).
//   start      {entity_id, verify?, age?}     email code to the account, or {held: true} if another claim holds it
//   confirm    {entity_id, code, role?, phone?}  → Managed by owner; returns the claim (text code, Verify number)
//   hold       {entity_id, phone?, role?, note?} "Claim Request On Hold"
//   details    {claim_id, phone, role}
//   domain_start {claim_id, email} / domain_confirm {claim_id, code}
//   document   (multipart) claim_id, file
//   appointment {claim_id, email, phone, address, when, how}
//   locations  {claim_id} → the other locations we found; add_locations {claim_id, ids[]}
//   activate   {claim_id, plan, agreed}   (a paid plan's trial needs agreed: the checkout's Terms box)
//   withdraw   {claim_id}
//   dispute    (JSON or multipart) entity_id, reason, file?
//   answer     {dispute_id, answer}
//   add_start  {name, kind, town, state?, phone, address | point, facebook?, website?, hours?, offers?, not_mine?}
//              → {duplicates: […]} to ask "Is this yours?", or {sent: true} (email code)       (decisions/0071)
//   add_confirm {code} → the new listing, live as Managed by owner; returns the claim
//   verify_request {claim_id} → emails the admins a one-tap Verify link
//   Every action that needs it accepts account_phone (saved to the account) with verify/age.
//   Every action that takes a claim_id is checked against the signed-in account before it runs (f04):
//   a claim that doesn't exist and a claim that isn't yours get the same 404.
declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/middleware/require_auth.php'; // sets $GLOBALS['__current_user'], 401s if not authenticated
require_once __DIR__ . '/../../../app/lib/Claims.php';
require_once __DIR__ . '/../../../app/lib/MemberVerification.php';
require_once __DIR__ . '/../../../app/lib/AgeGate.php';
require_once __DIR__ . '/../../../app/lib/Consent.php';
require_once __DIR__ . '/../../../app/lib/AddBusiness.php';
require_once __DIR__ . '/../../../app/lib/VerifyRequests.php';

Response::requireMethod('POST');
Response::requireCsrf();

$user = $GLOBALS['__current_user'];
$uid = (int) $user['id'];
$body = Response::jsonBody() ?: $_POST;
$action = (string) ($body['action'] ?? '');
$pdo = Database::connection();
if (!Claims::ready($pdo)) Response::error('Claiming isn\'t switched on yet (run app/migrations/2026-11-10_listing_claims.sql).', 503, 'not_ready');

$e = (int) ($body['entity_id'] ?? 0);
$cid = (int) ($body['claim_id'] ?? 0);
// The actions that act on, or return, an existing claim by claim_id: only the claim's own account may use them (f04).
$ownedActions = ['details', 'domain_start', 'domain_confirm', 'appointment', 'locations', 'add_locations', 'activate', 'withdraw', 'verify_request'];
$out = function (?array $c) use ($pdo): array {
    if (!$c) return [];
    $l = Claims::listing($pdo, (int) $c['entity_id']);
    return [
        'id' => (int) $c['id'], 'entity_id' => (int) $c['entity_id'], 'status' => $c['status'], 'text_code' => $c['text_code'],
        'verify_number' => Claims::verifyNumber(), 'listed_phone' => Claims::fmtPhone((string) $c['listed_phone']),
        'claimant_phone' => Claims::fmtPhone((string) $c['claimant_phone']), 'role' => $c['role_note'], 'due_at' => $c['due_at'],
        'route' => $c['route'], 'has_document' => $c['doc_path'] !== '', 'plan' => $c['plan'], 'domain' => implode(' or @', Claims::domains($pdo, (int) $c['entity_id'], $l['website'] ?? '')), 'kind' => Claims::kind($pdo, (int) $c['entity_id']),
        'name' => $l['name'] ?? '', 'request' => VerifyRequests::latest($pdo, (int) $c['id']),
    ];
};
// what's missing before someone can claim: the page asks for it in place
$needs = function (array $missing): never {
    $msg = match ($missing[0]) {
        'email' => 'Confirm your email first: open the link we emailed you when you joined (or send a new one from the page).',
        'phone' => 'Add your phone number first. It stays private: only our staff see it.',
        'age' => 'Adding or claiming a business is for members 18 or older: confirm your age first.',
        default => 'To add or claim a business, confirm your email first: open the link we sent you, or send it again from Sign in.',
    };
    Response::json(['ok' => false, 'needs' => $missing, 'verification' => Consent::prompt('verification'),
                    'error' => ['code' => 'needs_' . $missing[0], 'message' => $msg]], 428);
};
$accept = function () use ($pdo, $uid, $body): void {
    if (!empty($body['verify'])) MemberVerification::accept($pdo, $uid, 'claim');
    if (!empty($body['age'])) AgeGate::confirm($pdo, $uid);
    if (!empty($body['account_phone']) && Claims::phone10(AddBusiness::accountPhone($pdo, $uid)) === '') AddBusiness::setAccountPhone($pdo, $uid, (string) $body['account_phone']);
};

try {
    // Ownership guard (f04): runs before any claim_id action touches or returns the claim (text code, phones).
    if (in_array($action, $ownedActions, true)) {
        $owned = $cid > 0 ? Claims::get($pdo, $cid) : null;
        if (!$owned || (int) $owned['user_id'] !== $uid) Response::error('Claim not found.', 404, 'not_found');
    }
    switch ($action) {
        case 'start':
            $accept();
            if (($m = Claims::missing($pdo, $uid))) $needs($m);
            $r = Claims::start($pdo, $user, $e);
            Response::success($r['held'] ? ['held' => true, 'message' => 'Another claim for this listing is in progress.']
                                         : ['held' => false, 'message' => 'We sent a code to ' . $user['email'] . '.']);
        case 'confirm':
            try {
                $c = Claims::confirm($pdo, $user, $e, (string) ($body['code'] ?? ''), (string) ($body['role'] ?? ''), (string) ($body['phone'] ?? ''));
            } catch (RuntimeException $x) {
                if ($x->getMessage() !== 'held') throw $x;
                Response::success(['held' => true, 'message' => 'Another claim for this listing went ahead first.']);
            }
            if (trim((string) ($body['agency'] ?? '')) !== '') Claims::agencyNote($pdo, $uid, (int) $c['id'], (string) $body['agency']);   // who runs it, for the team to check (0054)
            Response::success(['held' => false, 'claim' => $out($c)]);
        case 'hold':
            $accept();
            if (($m = Claims::missing($pdo, $uid))) $needs($m);
            $c = Claims::hold($pdo, $user, $e, $body);
            if ($c && trim((string) ($body['agency'] ?? '')) !== '') Claims::agencyNote($pdo, $uid, (int) $c['id'], (string) $body['agency']);
            Response::success(['claim' => $out($c), 'message' => 'Claim Request On Hold: saved. An admin will be in touch.']);
        case 'details':
            Claims::details($pdo, $uid, $cid, (string) ($body['phone'] ?? ''), (string) ($body['role'] ?? ''));
            Response::success(['claim' => $out(Claims::get($pdo, $cid))]);
        case 'domain_start':
            Claims::domainStart($pdo, $uid, $cid, (string) ($body['email'] ?? ''));
            Response::success(['message' => 'We sent a code to that address.']);
        case 'domain_confirm':
            Claims::domainConfirm($pdo, $uid, $cid, (string) ($body['code'] ?? ''));
            Response::success(['claim' => $out(Claims::get($pdo, $cid)), 'message' => 'Confirmed: your listing is verified.']);
        case 'appointment':
            Claims::appointment($pdo, $uid, $cid, $body);
            Response::success(['claim' => $out(Claims::get($pdo, $cid)), 'message' => 'Requested. An admin will confirm the time with you.']);
        case 'locations':
            $c = Claims::get($pdo, $cid);
            if (!$c || (int) $c['user_id'] !== $uid) Response::error('Claim not found.', 404, 'not_found');
            Response::success(['locations' => array_map(fn ($r) => ['id' => (int) $r['id'], 'name' => $r['name'], 'city' => $r['city'] ?? '', 'address' => $r['address'] ?? ''],
                                                        Claims::siblings($pdo, (int) $c['entity_id']))]);
        case 'add_locations':
            $added = Claims::addLocations($pdo, $uid, $cid, is_array($body['ids'] ?? null) ? $body['ids'] : []);
            Response::success(['added' => count($added)]);
        case 'activate':
            Claims::activate($pdo, $uid, $cid, (string) ($body['plan'] ?? ''), !empty($body['agreed']));
            Response::success(['claim' => $out(Claims::get($pdo, $cid))]);
        case 'withdraw':
            Claims::withdraw($pdo, $uid, $cid);
            Response::success(['message' => 'Your claim is withdrawn.']);
        case 'dispute':
            $accept();
            if (($m = Claims::missing($pdo, $uid))) $needs($m);
            $id = Claims::fileDispute($pdo, $user, $e, (string) ($body['reason'] ?? ''));   // its proof follows through the uploader (target claim_doc)
            Response::success(['dispute_id' => $id, 'claim_id' => (int) $pdo->query('SELECT claim_id FROM disputes WHERE id = ' . (int) $id)->fetchColumn(), 'message' => 'Filed. The owner is told, and an admin will look at it.']);
        case 'resend_email':   // the email confirmation link again (adding or claiming needs a confirmed email)
            if (!empty($user['email_verified'])) Response::success(['message' => 'Your email is already confirmed.']);
            if (($_SESSION['tv_resend_at'] ?? 0) > time() - 120) Response::error('We just sent one. Check your inbox (and spam), or try again in two minutes.', 429, 'slow_down');
            $_SESSION['tv_resend_at'] = time();
            $tok = Auth::issueEmailConfirmation($uid, 'registration', 1440);
            Mailer::send((string) $user['email'], 'Confirm your Traversence email', "Please confirm your email address by visiting:\n"
                . rtrim((string) (getenv('APP_URL') ?: ''), '/') . '/verify-email.php?token=' . urlencode($tok) . "\n\nThe link works for 24 hours. If you didn't ask for this, you can ignore it.");
            Response::success(['message' => 'Sent to ' . $user['email'] . '. Open the link, then come back here.']);
        case 'add_start':
            $accept();
            if (($m = Claims::missing($pdo, $uid))) $needs($m);
            $r = AddBusiness::start($pdo, $user, $body, !empty($body['not_mine']));
            Response::success($r['sent'] ? ['sent' => true, 'message' => 'We sent a code to ' . $user['email'] . '.'] : ['sent' => false, 'duplicates' => $r['duplicates']]);
        case 'add_confirm':
            $c = AddBusiness::confirm($pdo, $user, (string) ($body['code'] ?? ''));
            Response::success(['claim' => $out($c), 'url' => '/claim.php?listing=' . (int) $c['entity_id'], 'listing_url' => '/listing/view.php?id=' . (int) $c['entity_id']]);
        case 'verify_request':
            if (!VerifyRequests::ready($pdo)) Response::error('Verification requests aren\'t switched on yet (run app/migrations/2026-11-12_adding_a_business.sql).', 503, 'not_ready');
            VerifyRequests::send($pdo, $uid, $cid);
            Response::success(['message' => 'Sent. We\'ll email you as soon as it\'s verified.']);
        case 'answer':
            Claims::answerDispute($pdo, $uid, (int) ($body['dispute_id'] ?? 0), (string) ($body['answer'] ?? ''));
            Response::success(['message' => 'Your answer is with the admin.']);
    }
} catch (DomainException $x) {
    $needs(explode(',', $x->getMessage()));
} catch (InvalidArgumentException $x) {
    Response::error($x->getMessage(), 422, 'invalid');
} catch (Throwable $x) {
    error_log('listing/api/claim.php ' . $action . ': ' . $x->getMessage());
    Response::error(str_contains($x->getMessage(), 'SMTP') ? 'We couldn\'t send the email just now. Please try again.' : 'Something went wrong. Please try again.', 500, 'server_error');
}
Response::error('Unknown action.', 422, 'invalid');
=== END FILE ===

=== FILE: f04/tests/claim-ownership-test.php ===
<?php
// tools/claim-tests/claim-ownership-test.php (f04): every claim_id action in listing/api/claim.php is owner-checked.
// Plain PHP, prints PASS/FAIL/SKIP. Static checks always run. The HTTP check runs only when the CLAIM_* env vars are set,
// and only against staging: it calls the read-only 'locations' action and never 'activate' (no money moves) or any writing action.
//   CLAIM_PHP     path to claim.php (optional; otherwise looked up next to this test)
//   CLAIM_URL     full URL of /listing/api/claim.php on staging
//   CLAIM_A_COOKIE / CLAIM_A_CSRF   session cookie header value + CSRF token of member A (owns CLAIM_A_ID)
//   CLAIM_B_COOKIE / CLAIM_B_CSRF   the same for member B (a different, ordinary member)
//   CLAIM_A_ID    a claim id owned by member A
declare(strict_types=1);

final class T {
    public static int $fail = 0;
    public static function check(string $name, bool $ok, string $why = ''): void {
        echo ($ok ? 'PASS ' : 'FAIL ') . $name . (!$ok && $why !== '' ? ' (' . $why . ')' : '') . "\n";
        if (!$ok) self::$fail++;
    }
}

$path = (string) (getenv('CLAIM_PHP') ?: '');
foreach ([__DIR__ . '/../files/listing/api/claim.php', __DIR__ . '/../../listing/api/claim.php', __DIR__ . '/../../public_html/listing/api/claim.php'] as $p) {
    if ($path === '' && is_file($p)) $path = $p;
}
if ($path === '' || !is_file($path)) { echo "FAIL claim.php not found (set CLAIM_PHP)\n"; exit(1); }
$src = (string) file_get_contents($path);

// 1. syntax
if (function_exists('exec')) {
    $o = []; $rc = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $o, $rc);
    T::check('php -l claim.php', $rc === 0, implode(' ', $o));
} else {
    echo "SKIP php -l (exec disabled)\n";
}

// 2. the owned-actions list and the guard before the switch
$owned = [];
if (preg_match('/\$ownedActions = \[([^\]]*)\];/', $src, $m)) {
    preg_match_all("/'([a-z_]+)'/", $m[1], $mm);
    $owned = $mm[1];
}
T::check('$ownedActions list present', $owned !== []);
$switchAt = strpos($src, 'switch ($action) {');
$guardAt = strpos($src, 'if (in_array($action, $ownedActions, true)) {');
T::check('switch found', $switchAt !== false);
T::check('guard found before the switch', $guardAt !== false && $switchAt !== false && $guardAt < $switchAt);
T::check('guard compares the claim owner with the signed-in user', str_contains($src, "(int) \$owned['user_id'] !== \$uid"));
T::check('guard answers missing and not-yours alike with 404', str_contains($src, "Response::error('Claim not found.', 404, 'not_found');"));

// 3. every case that uses $cid is in the owned list; every owned action is a real case
$end = strpos($src, '} catch (DomainException');
$sw = $switchAt !== false && $end !== false ? substr($src, $switchAt, $end - $switchAt) : '';
$parts = preg_split("/\n\s*case '([a-z_]+)':/", $sw, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
$cases = [];
for ($i = 1; $i + 1 < count($parts); $i += 2) $cases[$parts[$i]] = $parts[$i + 1];
T::check('cases parsed', count($cases) >= 15, 'got ' . count($cases));
foreach ($cases as $name => $code) {
    if (str_contains($code, '$cid')) T::check("case '$name' uses claim_id and is owner-guarded", in_array($name, $owned, true));
}
foreach ($owned as $name) T::check("owned action '$name' is a real case", isset($cases[$name]));

// 4. optional live check against staging (read-only action only)
$url = (string) (getenv('CLAIM_URL') ?: '');
$env = ['CLAIM_A_COOKIE', 'CLAIM_A_CSRF', 'CLAIM_B_COOKIE', 'CLAIM_B_CSRF', 'CLAIM_A_ID'];
$have = $url !== '' && !in_array(false, array_map(fn ($k) => getenv($k) !== false && getenv($k) !== '', $env), true);
if (!$have || !function_exists('curl_init')) {
    echo "SKIP HTTP check (set CLAIM_URL and " . implode(', ', $env) . "; needs ext-curl)\n";
} else {
    $post = function (string $cookie, string $csrf, array $b) use ($url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'X-CSRF-Token: ' . $csrf, 'Cookie: ' . $cookie],
            CURLOPT_POSTFIELDS => json_encode($b),
        ]);
        $res = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$code, $res];
    };
    $id = (int) getenv('CLAIM_A_ID');
    [$codeB, $resB] = $post((string) getenv('CLAIM_B_COOKIE'), (string) getenv('CLAIM_B_CSRF'), ['action' => 'locations', 'claim_id' => $id]);
    T::check('member B gets 404 on member A\'s claim', $codeB === 404, "HTTP $codeB");
    T::check('member B sees no locations or text code', !str_contains($resB, '"locations"') && !str_contains($resB, 'text_code'));
    [$codeA] = $post((string) getenv('CLAIM_A_COOKIE'), (string) getenv('CLAIM_A_CSRF'), ['action' => 'locations', 'claim_id' => $id]);
    T::check('member A still gets their own claim\'s locations', $codeA === 200, "HTTP $codeA");
    [$code0] = $post((string) getenv('CLAIM_B_COOKIE'), (string) getenv('CLAIM_B_CSRF'), ['action' => 'locations', 'claim_id' => 0]);
    T::check('claim_id 0 gets 404', $code0 === 404, "HTTP $code0");
}

echo T::$fail === 0 ? "ALL PASS\n" : T::$fail . " FAILED\n";
exit(T::$fail === 0 ? 0 : 1);
=== END FILE ===

=== FILE: f04/faq/faq-entries.md ===
no faq change
=== END FILE ===

=== FILE: f04/release.json ===
{
  "name": "f04-claim-ownership-guard",
  "gate": "high: ship first; f17 and f18 are built on top of this file",
  "what": "listing/api/claim.php: one ownership guard runs before the switch for every action that takes a claim_id (details, domain_start, domain_confirm, appointment, locations, add_locations, activate, withdraw, verify_request). The guard loads the claim with Claims::get and returns 404 'Claim not found.' unless claim.user_id is the signed-in user. This happens before any library call and before $out() returns text_code and phones. A missing claim and someone else's claim get the same 404, so claim ids can't be probed. The existing check inside 'locations' is kept.",
  "files": ["files/listing/api/claim.php"],
  "decisions": [
    "Safest default: no admin or staff bypass on this member endpoint. Admins acting on another member's claim would now get 404. A person must confirm admin tools don't post here.",
    "Missing and not-yours both return 404 not_found (same as the existing 'locations' check), so claim ids can't be enumerated.",
    "Guard placed at the endpoint whatever app/lib/Claims.php does (defense in depth). The finding's severity depends on the library, which is not shown."
  ],
  "tests": ["tests/claim-ownership-test.php (static: lint, guard before switch, every $cid case is guarded; optional staging HTTP: member B gets 404 on member A's claim via read-only 'locations')"],
  "risks": [
    "NOTHING HERE HAS BEEN RUN OR VERIFIED: not linted, not tested, not deployed. The lane session must run php -l, the test and a zip check.",
    "Any legitimate flow where a claim is used by an account other than claim.user_id (co-owner, transferred claim, staff-on-behalf) will now get 404.",
    "Adds one Claims::get read per claim_id request.",
    "Not covered by this endpoint guard: 'answer' (dispute_id) and the multipart 'document' upload, which has no case here and goes through the uploader."
  ],
  "verify": [
    "Live path: the file's header says /listing/api/claim.php, but its requires reach ../../../app. It may sit under a web-root folder (e.g. public_html/listing/api/claim.php). Place it at the real live path.",
    "app/lib/Claims.php: file not shown. Confirm Claims::get returns user_id (the existing 'locations' case already relies on it). Check whether details/domainStart/domainConfirm/appointment/addLocations/activate/withdraw already check ownership; this sets f04's true severity.",
    "app/lib/VerifyRequests.php: file not shown. Confirm VerifyRequests::send checks ownership too.",
    "Claims::answerDispute: file not shown. Confirm it only lets the disputed claim's owner answer.",
    "The document uploader (target claim_doc): file not shown. Confirm it checks the claim belongs to the uploader.",
    "DECISION NEEDED: should admins or co-owners act on others' claims through this endpoint? Default here: no.",
    "Confirm the claim page's JS handles a 404 not_found on these actions (it already must for 'locations')."
  ],
  "regions": false
}
=== END FILE ===

=== FILE: f17/files/listing/api/claim.php ===
<?php
// /listing/api/claim.php: claiming a listing (decisions/0070). POST, signed in, X-CSRF-Token.
// JSON body {action, ...}; the document upload is multipart (action=document, claim_id, file).
//   start      {entity_id, verify?, age?}     email code to the account, or {held: true} if another claim holds it
//   confirm    {entity_id, code, role?, phone?}  → Managed by owner; returns the claim (text code, Verify number)
//   hold       {entity_id, phone?, role?, note?} "Claim Request On Hold"
//   details    {claim_id, phone, role}
//   domain_start {claim_id, email} / domain_confirm {claim_id, code}
//   document   (multipart) claim_id, file
//   appointment {claim_id, email, phone, address, when, how}
//   locations  {claim_id} → the other locations we found; add_locations {claim_id, ids[]}
//   activate   {claim_id, plan, agreed}   (a paid plan's trial needs agreed: the checkout's Terms box)
//   withdraw   {claim_id}
//   dispute    (JSON or multipart) entity_id, reason, file?  → {dispute_id} only: never the other party's claim_id (f17)
//   answer     {dispute_id, answer}
//   add_start  {name, kind, town, state?, phone, address | point, facebook?, website?, hours?, offers?, not_mine?}
//              → {duplicates: […]} to ask "Is this yours?", or {sent: true} (email code)       (decisions/0071)
//   add_confirm {code} → the new listing, live as Managed by owner; returns the claim
//   verify_request {claim_id} → emails the admins a one-tap Verify link
//   Every action that needs it accepts account_phone (saved to the account) with verify/age.
//   Every action that takes a claim_id is checked against the signed-in account before it runs (f04):
//   a claim that doesn't exist and a claim that isn't yours get the same 404.
declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/middleware/require_auth.php'; // sets $GLOBALS['__current_user'], 401s if not authenticated
require_once __DIR__ . '/../../../app/lib/Claims.php';
require_once __DIR__ . '/../../../app/lib/MemberVerification.php';
require_once __DIR__ . '/../../../app/lib/AgeGate.php';
require_once __DIR__ . '/../../../app/lib/Consent.php';
require_once __DIR__ . '/../../../app/lib/AddBusiness.php';
require_once __DIR__ . '/../../../app/lib/VerifyRequests.php';

Response::requireMethod('POST');
Response::requireCsrf();

$user = $GLOBALS['__current_user'];
$uid = (int) $user['id'];
$body = Response::jsonBody() ?: $_POST;
$action = (string) ($body['action'] ?? '');
$pdo = Database::connection();
if (!Claims::ready($pdo)) Response::error('Claiming isn\'t switched on yet (run app/migrations/2026-11-10_listing_claims.sql).', 503, 'not_ready');

$e = (int) ($body['entity_id'] ?? 0);
$cid = (int) ($body['claim_id'] ?? 0);
// The actions that act on, or return, an existing claim by claim_id: only the claim's own account may use them (f04).
$ownedActions = ['details', 'domain_start', 'domain_confirm', 'appointment', 'locations', 'add_locations', 'activate', 'withdraw', 'verify_request'];
$out = function (?array $c) use ($pdo): array {
    if (!$c) return [];
    $l = Claims::listing($pdo, (int) $c['entity_id']);
    return [
        'id' => (int) $c['id'], 'entity_id' => (int) $c['entity_id'], 'status' => $c['status'], 'text_code' => $c['text_code'],
        'verify_number' => Claims::verifyNumber(), 'listed_phone' => Claims::fmtPhone((string) $c['listed_phone']),
        'claimant_phone' => Claims::fmtPhone((string) $c['claimant_phone']), 'role' => $c['role_note'], 'due_at' => $c['due_at'],
        'route' => $c['route'], 'has_document' => $c['doc_path'] !== '', 'plan' => $c['plan'], 'domain' => implode(' or @', Claims::domains($pdo, (int) $c['entity_id'], $l['website'] ?? '')), 'kind' => Claims::kind($pdo, (int) $c['entity_id']),
        'name' => $l['name'] ?? '', 'request' => VerifyRequests::latest($pdo, (int) $c['id']),
    ];
};
// what's missing before someone can claim: the page asks for it in place
$needs = function (array $missing): never {
    $msg = match ($missing[0]) {
        'email' => 'Confirm your email first: open the link we emailed you when you joined (or send a new one from the page).',
        'phone' => 'Add your phone number first. It stays private: only our staff see it.',
        'age' => 'Adding or claiming a business is for members 18 or older: confirm your age first.',
        default => 'To add or claim a business, confirm your email first: open the link we sent you, or send it again from Sign in.',
    };
    Response::json(['ok' => false, 'needs' => $missing, 'verification' => Consent::prompt('verification'),
                    'error' => ['code' => 'needs_' . $missing[0], 'message' => $msg]], 428);
};
$accept = function () use ($pdo, $uid, $body): void {
    if (!empty($body['verify'])) MemberVerification::accept($pdo, $uid, 'claim');
    if (!empty($body['age'])) AgeGate::confirm($pdo, $uid);
    if (!empty($body['account_phone']) && Claims::phone10(AddBusiness::accountPhone($pdo, $uid)) === '') AddBusiness::setAccountPhone($pdo, $uid, (string) $body['account_phone']);
};

try {
    // Ownership guard (f04): runs before any claim_id action touches or returns the claim (text code, phones).
    if (in_array($action, $ownedActions, true)) {
        $owned = $cid > 0 ? Claims::get($pdo, $cid) : null;
        if (!$owned || (int) $owned['user_id'] !== $uid) Response::error('Claim not found.', 404, 'not_found');
    }
    switch ($action) {
        case 'start':
            $accept();
            if (($m = Claims::missing($pdo, $uid))) $needs($m);
            $r = Claims::start($pdo, $user, $e);
            Response::success($r['held'] ? ['held' => true, 'message' => 'Another claim for this listing is in progress.']
                                         : ['held' => false, 'message' => 'We sent a code to ' . $user['email'] . '.']);
        case 'confirm':
            try {
                $c = Claims::confirm($pdo, $user, $e, (string) ($body['code'] ?? ''), (string) ($body['role'] ?? ''), (string) ($body['phone'] ?? ''));
            } catch (RuntimeException $x) {
                if ($x->getMessage() !== 'held') throw $x;
                Response::success(['held' => true, 'message' => 'Another claim for this listing went ahead first.']);
            }
            if (trim((string) ($body['agency'] ?? '')) !== '') Claims::agencyNote($pdo, $uid, (int) $c['id'], (string) $body['agency']);   // who runs it, for the team to check (0054)
            Response::success(['held' => false, 'claim' => $out($c)]);
        case 'hold':
            $accept();
            if (($m = Claims::missing($pdo, $uid))) $needs($m);
            $c = Claims::hold($pdo, $user, $e, $body);
            if ($c && trim((string) ($body['agency'] ?? '')) !== '') Claims::agencyNote($pdo, $uid, (int) $c['id'], (string) $body['agency']);
            Response::success(['claim' => $out($c), 'message' => 'Claim Request On Hold: saved. An admin will be in touch.']);
        case 'details':
            Claims::details($pdo, $uid, $cid, (string) ($body['phone'] ?? ''), (string) ($body['role'] ?? ''));
            Response::success(['claim' => $out(Claims::get($pdo, $cid))]);
        case 'domain_start':
            Claims::domainStart($pdo, $uid, $cid, (string) ($body['email'] ?? ''));
            Response::success(['message' => 'We sent a code to that address.']);
        case 'domain_confirm':
            Claims::domainConfirm($pdo, $uid, $cid, (string) ($body['code'] ?? ''));
            Response::success(['claim' => $out(Claims::get($pdo, $cid)), 'message' => 'Confirmed: your listing is verified.']);
        case 'appointment':
            Claims::appointment($pdo, $uid, $cid, $body);
            Response::success(['claim' => $out(Claims::get($pdo, $cid)), 'message' => 'Requested. An admin will confirm the time with you.']);
        case 'locations':
            $c = Claims::get($pdo, $cid);
            if (!$c || (int) $c['user_id'] !== $uid) Response::error('Claim not found.', 404, 'not_found');
            Response::success(['locations' => array_map(fn ($r) => ['id' => (int) $r['id'], 'name' => $r['name'], 'city' => $r['city'] ?? '', 'address' => $r['address'] ?? ''],
                                                        Claims::siblings($pdo, (int) $c['entity_id']))]);
        case 'add_locations':
            $added = Claims::addLocations($pdo, $uid, $cid, is_array($body['ids'] ?? null) ? $body['ids'] : []);
            Response::success(['added' => count($added)]);
        case 'activate':
            Claims::activate($pdo, $uid, $cid, (string) ($body['plan'] ?? ''), !empty($body['agreed']));
            Response::success(['claim' => $out(Claims::get($pdo, $cid))]);
        case 'withdraw':
            Claims::withdraw($pdo, $uid, $cid);
            Response::success(['message' => 'Your claim is withdrawn.']);
        case 'dispute':
            $accept();
            if (($m = Claims::missing($pdo, $uid))) $needs($m);
            $id = Claims::fileDispute($pdo, $user, $e, (string) ($body['reason'] ?? ''));   // its proof follows through the uploader (target claim_doc)
            // Only the dispute's own id goes back: the disputed claim belongs to someone else (f17).
            Response::success(['dispute_id' => $id, 'message' => 'Filed. The owner is told, and an admin will look at it.']);
        case 'resend_email':   // the email confirmation link again (adding or claiming needs a confirmed email)
            if (!empty($user['email_verified'])) Response::success(['message' => 'Your email is already confirmed.']);
            if (($_SESSION['tv_resend_at'] ?? 0) > time() - 120) Response::error('We just sent one. Check your inbox (and spam), or try again in two minutes.', 429, 'slow_down');
            $_SESSION['tv_resend_at'] = time();
            $tok = Auth::issueEmailConfirmation($uid, 'registration', 1440);
            Mailer::send((string) $user['email'], 'Confirm your Traversence email', "Please confirm your email address by visiting:\n"
                . rtrim((string) (getenv('APP_URL') ?: ''), '/') . '/verify-email.php?token=' . urlencode($tok) . "\n\nThe link works for 24 hours. If you didn't ask for this, you can ignore it.");
            Response::success(['message' => 'Sent to ' . $user['email'] . '. Open the link, then come back here.']);
        case 'add_start':
            $accept();
            if (($m = Claims::missing($pdo, $uid))) $needs($m);
            $r = AddBusiness::start($pdo, $user, $body, !empty($body['not_mine']));
            Response::success($r['sent'] ? ['sent' => true, 'message' => 'We sent a code to ' . $user['email'] . '.'] : ['sent' => false, 'duplicates' => $r['duplicates']]);
        case 'add_confirm':
            $c = AddBusiness::confirm($pdo, $user, (string) ($body['code'] ?? ''));
            Response::success(['claim' => $out($c), 'url' => '/claim.php?listing=' . (int) $c['entity_id'], 'listing_url' => '/listing/view.php?id=' . (int) $c['entity_id']]);
        case 'verify_request':
            if (!VerifyRequests::ready($pdo)) Response::error('Verification requests aren\'t switched on yet (run app/migrations/2026-11-12_adding_a_business.sql).', 503, 'not_ready');
            VerifyRequests::send($pdo, $uid, $cid);
            Response::success(['message' => 'Sent. We\'ll email you as soon as it\'s verified.']);
        case 'answer':
            Claims::answerDispute($pdo, $uid, (int) ($body['dispute_id'] ?? 0), (string) ($body['answer'] ?? ''));
            Response::success(['message' => 'Your answer is with the admin.']);
    }
} catch (DomainException $x) {
    $needs(explode(',', $x->getMessage()));
} catch (InvalidArgumentException $x) {
    Response::error($x->getMessage(), 422, 'invalid');
} catch (Throwable $x) {
    error_log('listing/api/claim.php ' . $action . ': ' . $x->getMessage());
    Response::error(str_contains($x->getMessage(), 'SMTP') ? 'We couldn\'t send the email just now. Please try again.' : 'Something went wrong. Please try again.', 500, 'server_error');
}
Response::error('Unknown action.', 422, 'invalid');
=== END FILE ===

=== FILE: f17/tests/claim-dispute-response-test.php ===
<?php
// tools/claim-tests/claim-dispute-response-test.php (f17): the 'dispute' action returns only dispute_id, never the
// disputed (other party's) claim_id. Plain PHP, prints PASS/FAIL/SKIP. Static only: filing a real dispute writes data and
// emails the owner, so the live check is a manual step in release.json 'verify'. No money moves.
//   CLAIM_PHP  path to claim.php (optional; otherwise looked up next to this test)
declare(strict_types=1);

final class T {
    public static int $fail = 0;
    public static function check(string $name, bool $ok, string $why = ''): void {
        echo ($ok ? 'PASS ' : 'FAIL ') . $name . (!$ok && $why !== '' ? ' (' . $why . ')' : '') . "\n";
        if (!$ok) self::$fail++;
    }
}

$path = (string) (getenv('CLAIM_PHP') ?: '');
foreach ([__DIR__ . '/../files/listing/api/claim.php', __DIR__ . '/../../listing/api/claim.php', __DIR__ . '/../../public_html/listing/api/claim.php'] as $p) {
    if ($path === '' && is_file($p)) $path = $p;
}
if ($path === '' || !is_file($path)) { echo "FAIL claim.php not found (set CLAIM_PHP)\n"; exit(1); }
$src = (string) file_get_contents($path);

if (function_exists('exec')) {
    $o = []; $rc = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $o, $rc);
    T::check('php -l claim.php', $rc === 0, implode(' ', $o));
} else {
    echo "SKIP php -l (exec disabled)\n";
}

$switchAt = strpos($src, 'switch ($action) {');
$end = strpos($src, '} catch (DomainException');
$sw = $switchAt !== false && $end !== false ? substr($src, $switchAt, $end - $switchAt) : '';
$parts = preg_split("/\n\s*case '([a-z_]+)':/", $sw, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
$cases = [];
for ($i = 1; $i + 1 < count($parts); $i += 2) $cases[$parts[$i]] = $parts[$i + 1];
$d = $cases['dispute'] ?? '';

T::check("case 'dispute' found", $d !== '');
T::check('dispute response returns dispute_id', str_contains($d, "'dispute_id' => \$id"));
T::check('dispute response has no claim_id key', !str_contains($d, "'claim_id'"));
T::check('dispute case no longer reads disputes.claim_id', !str_contains($d, '->query(') && !str_contains($d, 'SELECT claim_id'));
T::check('no raw SQL query left in the endpoint', !str_contains($src, '$pdo->query('));
// f04 is carried in this file: make sure it wasn't lost
T::check('f04 ownership guard still present before the switch',
    ($g = strpos($src, 'if (in_array($action, $ownedActions, true)) {')) !== false && $switchAt !== false && $g < $switchAt);

echo T::$fail === 0 ? "ALL PASS\n" : T::$fail . " FAILED\n";
exit(T::$fail === 0 ? 0 : 1);
=== END FILE ===

=== FILE: f17/faq/faq-entries.md ===
no faq change
=== END FILE ===

=== FILE: f17/release.json ===
{
  "name": "f17-dispute-returns-dispute-id-only",
  "gate": "med: ship after f04 (this file contains f04's guard); f18 is built on top of this file",
  "what": "listing/api/claim.php 'dispute' action: the response is now {dispute_id, message}. It no longer includes the disputed claim's claim_id, which belongs to the other party. The raw $pdo->query('SELECT claim_id FROM disputes ...') used to fetch it is removed. Everything else matches f04.",
  "files": ["files/listing/api/claim.php"],
  "decisions": [
    "Safest default: drop claim_id from the response entirely; nothing replaces it on the client.",
    "Proof for a dispute should be keyed by dispute_id server-side. If the uploader currently needs the client to send the other party's claim_id, that is a separate bug to fix in the uploader. claim_id is not handed back."
  ],
  "tests": ["tests/claim-dispute-response-test.php (static: lint, dispute case returns dispute_id only, no SELECT claim_id, f04 guard still present)"],
  "risks": [
    "NOTHING HERE HAS BEEN RUN OR VERIFIED: not linted, not tested, not deployed. The lane session must run php -l, the test and a zip check.",
    "The dispute proof upload may break: the code comment says proof 'follows through the uploader (target claim_doc)'. If the page's JS sends the returned claim_id to the uploader, the proof step stops working until it uses dispute_id.",
    "This file carries f04. Shipping f17 without f04 also ships f04's guard; shipping f04 after f17 would undo f17."
  ],
  "verify": [
    "Claim page JS and the dispute form: files not shown. Search for where the dispute response's claim_id is read, and switch it to dispute_id or remove it.",
    "The uploader handling target claim_doc for disputes: file not shown. Confirm it attaches proof by dispute_id and checks the dispute was filed by the uploader. If it needs claim_id, a person must decide how to move it to dispute_id before shipping.",
    "Manual check on staging: file one dispute with a test account against a test listing. Confirm the JSON has dispute_id and no claim_id, and that attaching proof still works.",
    "Live path: header says /listing/api/claim.php; requires reach ../../../app. Place it at the real live path (it may be under a web-root folder)."
  ],
  "regions": false
}
=== END FILE ===

=== FILE: f18/files/listing/api/claim.php ===
<?php
// /listing/api/claim.php: claiming a listing (decisions/0070). POST, signed in, X-CSRF-Token.
// JSON body {action, ...}; the document upload is multipart (action=document, claim_id, file).
//   start      {entity_id, verify?, age?}     email code to the account, or {held: true} if another claim holds it
//   confirm    {entity_id, code, role?, phone?}  → Managed by owner; returns the claim (text code, Verify number)
//   hold       {entity_id, phone?, role?, note?} "Claim Request On Hold"
//   details    {claim_id, phone, role}
//   domain_start {claim_id, email} / domain_confirm {claim_id, code}
//   document   (multipart) claim_id, file
//   appointment {claim_id, email, phone, address, when, how}
//   locations  {claim_id} → the other locations we found (street address only once the claim is confirmed, f18);
//              add_locations {claim_id, ids[]}
//   activate   {claim_id, plan, agreed}   (a paid plan's trial needs agreed: the checkout's Terms box)
//   withdraw   {claim_id}
//   dispute    (JSON or multipart) entity_id, reason, file?  → {dispute_id} only: never the other party's claim_id (f17)
//   answer     {dispute_id, answer}
//   add_start  {name, kind, town, state?, phone, address | point, facebook?, website?, hours?, offers?, not_mine?}
//              → {duplicates: […]} to ask "Is this yours?", or {sent: true} (email code)       (decisions/0071)
//   add_confirm {code} → the new listing, live as Managed by owner; returns the claim
//   verify_request {claim_id} → emails the admins a one-tap Verify link
//   Every action that needs it accepts account_phone (saved to the account) with verify/age.
//   Every action that takes a claim_id is checked against the signed-in account before it runs (f04):
//   a claim that doesn't exist and a claim that isn't yours get the same 404.
declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/middleware/require_auth.php'; // sets $GLOBALS['__current_user'], 401s if not authenticated
require_once __DIR__ . '/../../../app/lib/Claims.php';
require_once __DIR__ . '/../../../app/lib/MemberVerification.php';
require_once __DIR__ . '/../../../app/lib/AgeGate.php';
require_once __DIR__ . '/../../../app/lib/Consent.php';
require_once __DIR__ . '/../../../app/lib/AddBusiness.php';
require_once __DIR__ . '/../../../app/lib/VerifyRequests.php';

Response::requireMethod('POST');
Response::requireCsrf();

$user = $GLOBALS['__current_user'];
$uid = (int) $user['id'];
$body = Response::jsonBody() ?: $_POST;
$action = (string) ($body['action'] ?? '');
$pdo = Database::connection();
if (!Claims::ready($pdo)) Response::error('Claiming isn\'t switched on yet (run app/migrations/2026-11-10_listing_claims.sql).', 503, 'not_ready');

$e = (int) ($body['entity_id'] ?? 0);
$cid = (int) ($body['claim_id'] ?? 0);
// The actions that act on, or return, an existing claim by claim_id: only the claim's own account may use them (f04).
$ownedActions = ['details', 'domain_start', 'domain_confirm', 'appointment', 'locations', 'add_locations', 'activate', 'withdraw', 'verify_request'];
// Claim statuses at which 'locations' may include the other locations' street addresses (f18).
// DECISION PENDING: fill in the confirmed/verified status values used by app/lib/Claims.php (exact strings).
// Until then it stays empty, so addresses are left out at every status; name and town are still shown.
$addressStatuses = [];
$out = function (?array $c) use ($pdo): array {
    if (!$c) return [];
    $l = Claims::listing($pdo, (int) $c['entity_id']);
    return [
        'id' => (int) $c['id'], 'entity_id' => (int) $c['entity_id'], 'status' => $c['status'], 'text_code' => $c['text_code'],
        'verify_number' => Claims::verifyNumber(), 'listed_phone' => Claims::fmtPhone((string) $c['listed_phone']),
        'claimant_phone' => Claims::fmtPhone((string) $c['claimant_phone']), 'role' => $c['role_note'], 'due_at' => $c['due_at'],
        'route' => $c['route'], 'has_document' => $c['doc_path'] !== '', 'plan' => $c['plan'], 'domain' => implode(' or @', Claims::domains($pdo, (int) $c['entity_id'], $l['website'] ?? '')), 'kind' => Claims::kind($pdo, (int) $c['entity_id']),
        'name' => $l['name'] ?? '', 'request' => VerifyRequests::latest($pdo, (int) $c['id']),
    ];
};
// what's missing before someone can claim: the page asks for it in place
$needs = function (array $missing): never {
    $msg = match ($missing[0]) {
        'email' => 'Confirm your email first: open the link we emailed you when you joined (or send a new one from the page).',
        'phone' => 'Add your phone number first. It stays private: only our staff see it.',
        'age' => 'Adding or claiming a business is for members 18 or older: confirm your age first.',
        default => 'To add or claim a business, confirm your email first: open the link we sent you, or send it again from Sign in.',
    };
    Response::json(['ok' => false, 'needs' => $missing, 'verification' => Consent::prompt('verification'),
                    'error' => ['code' => 'needs_' . $missing[0], 'message' => $msg]], 428);
};
$accept = function () use ($pdo, $uid, $body): void {
    if (!empty($body['verify'])) MemberVerification::accept($pdo, $uid, 'claim');
    if (!empty($body['age'])) AgeGate::confirm($pdo, $uid);
    if (!empty($body['account_phone']) && Claims::phone10(AddBusiness::accountPhone($pdo, $uid)) === '') AddBusiness::setAccountPhone($pdo, $uid, (string) $body['account_phone']);
};

try {
    // Ownership guard (f04): runs before any claim_id action touches or returns the claim (text code, phones).
    if (in_array($action, $ownedActions, true)) {
        $owned = $cid > 0 ? Claims::get($pdo, $cid) : null;
        if (!$owned || (int) $owned['user_id'] !== $uid) Response::error('Claim not found.', 404, 'not_found');
    }
    switch ($action) {
        case 'start':
            $accept();
            if (($m = Claims::missing($pdo, $uid))) $needs($m);
            $r = Claims::start($pdo, $user, $e);
            Response::success($r['held'] ? ['held' => true, 'message' => 'Another claim for this listing is in progress.']
                                         : ['held' => false, 'message' => 'We sent a code to ' . $user['email'] . '.']);
        case 'confirm':
            try {
                $c = Claims::confirm($pdo, $user, $e, (string) ($body['code'] ?? ''), (string) ($body['role'] ?? ''), (string) ($body['phone'] ?? ''));
            } catch (RuntimeException $x) {
                if ($x->getMessage() !== 'held') throw $x;
                Response::success(['held' => true, 'message' => 'Another claim for this listing went ahead first.']);
            }
            if (trim((string) ($body['agency'] ?? '')) !== '') Claims::agencyNote($pdo, $uid, (int) $c['id'], (string) $body['agency']);   // who runs it, for the team to check (0054)
            Response::success(['held' => false, 'claim' => $out($c)]);
        case 'hold':
            $accept();
            if (($m = Claims::missing($pdo, $uid))) $needs($m);
            $c = Claims::hold($pdo, $user, $e, $body);
            if ($c && trim((string) ($body['agency'] ?? '')) !== '') Claims::agencyNote($pdo, $uid, (int) $c['id'], (string) $body['agency']);
            Response::success(['claim' => $out($c), 'message' => 'Claim Request On Hold: saved. An admin will be in touch.']);
        case 'details':
            Claims::details($pdo, $uid, $cid, (string) ($body['phone'] ?? ''), (string) ($body['role'] ?? ''));
            Response::success(['claim' => $out(Claims::get($pdo, $cid))]);
        case 'domain_start':
            Claims::domainStart($pdo, $uid, $cid, (string) ($body['email'] ?? ''));
            Response::success(['message' => 'We sent a code to that address.']);
        case 'domain_confirm':
            Claims::domainConfirm($pdo, $uid, $cid, (string) ($body['code'] ?? ''));
            Response::success(['claim' => $out(Claims::get($pdo, $cid)), 'message' => 'Confirmed: your listing is verified.']);
        case 'appointment':
            Claims::appointment($pdo, $uid, $cid, $body);
            Response::success(['claim' => $out(Claims::get($pdo, $cid)), 'message' => 'Requested. An admin will confirm the time with you.']);
        case 'locations':
            $c = Claims::get($pdo, $cid);
            if (!$c || (int) $c['user_id'] !== $uid) Response::error('Claim not found.', 404, 'not_found');
            // Street addresses only for a confirmed claim (f18); before that, name and town are enough to pick locations.
            $showAddress = in_array((string) $c['status'], $addressStatuses, true);
            Response::success(['locations' => array_map(fn ($r) => ['id' => (int) $r['id'], 'name' => $r['name'], 'city' => $r['city'] ?? '', 'address' => $showAddress ? ($r['address'] ?? '') : ''],
                                                        Claims::siblings($pdo, (int) $c['entity_id'])),
                               'addresses_shown' => $showAddress]);
        case 'add_locations':
            $added = Claims::addLocations($pdo, $uid, $cid, is_array($body['ids'] ?? null) ? $body['ids'] : []);
            Response::success(['added' => count($added)]);
        case 'activate':
            Claims::activate($pdo, $uid, $cid, (string) ($body['plan'] ?? ''), !empty($body['agreed']));
            Response::success(['claim' => $out(Claims::get($pdo, $cid))]);
        case 'withdraw':
            Claims::withdraw($pdo, $uid, $cid);
            Response::success(['message' => 'Your claim is withdrawn.']);
        case 'dispute':
            $accept();
            if (($m = Claims::missing($pdo, $uid))) $needs($m);
            $id = Claims::fileDispute($pdo, $user, $e, (string) ($body['reason'] ?? ''));   // its proof follows through the uploader (target claim_doc)
            // Only the dispute's own id goes back: the disputed claim belongs to someone else (f17).
            Response::success(['dispute_id' => $id, 'message' => 'Filed. The owner is told, and an admin will look at it.']);
        case 'resend_email':   // the email confirmation link again (adding or claiming needs a confirmed email)
            if (!empty($user['email_verified'])) Response::success(['message' => 'Your email is already confirmed.']);
            if (($_SESSION['tv_resend_at'] ?? 0) > time() - 120) Response::error('We just sent one. Check your inbox (and spam), or try again in two minutes.', 429, 'slow_down');
            $_SESSION['tv_resend_at'] = time();
            $tok = Auth::issueEmailConfirmation($uid, 'registration', 1440);
            Mailer::send((string) $user['email'], 'Confirm your Traversence email', "Please confirm your email address by visiting:\n"
                . rtrim((string) (getenv('APP_URL') ?: ''), '/') . '/verify-email.php?token=' . urlencode($tok) . "\n\nThe link works for 24 hours. If you didn't ask for this, you can ignore it.");
            Response::success(['message' => 'Sent to ' . $user['email'] . '. Open the link, then come back here.']);
        case 'add_start':
            $accept();
            if (($m = Claims::missing($pdo, $uid))) $needs($m);
            $r = AddBusiness::start($pdo, $user, $body, !empty($body['not_mine']));
            Response::success($r['sent'] ? ['sent' => true, 'message' => 'We sent a code to ' . $user['email'] . '.'] : ['sent' => false, 'duplicates' => $r['duplicates']]);
        case 'add_confirm':
            $c = AddBusiness::confirm($pdo, $user, (string) ($body['code'] ?? ''));
            Response::success(['claim' => $out($c), 'url' => '/claim.php?listing=' . (int) $c['entity_id'], 'listing_url' => '/listing/view.php?id=' . (int) $c['entity_id']]);
        case 'verify_request':
            if (!VerifyRequests::ready($pdo)) Response::error('Verification requests aren\'t switched on yet (run app/migrations/2026-11-12_adding_a_business.sql).', 503, 'not_ready');
            VerifyRequests::send($pdo, $uid, $cid);
            Response::success(['message' => 'Sent. We\'ll email you as soon as it\'s verified.']);
        case 'answer':
            Claims::answerDispute($pdo, $uid, (int) ($body['dispute_id'] ?? 0), (string) ($body['answer'] ?? ''));
            Response::success(['message' => 'Your answer is with the admin.']);
    }
} catch (DomainException $x) {
    $needs(explode(',', $x->getMessage()));
} catch (InvalidArgumentException $x) {
    Response::error($x->getMessage(), 422, 'invalid');
} catch (Throwable $x) {
    error_log('listing/api/claim.php ' . $action . ': ' . $x->getMessage());
    Response::error(str_contains($x->getMessage(), 'SMTP') ? 'We couldn\'t send the email just now. Please try again.' : 'Something went wrong. Please try again.', 500, 'server_error');
}
Response::error('Unknown action.', 422, 'invalid');
=== END FILE ===

=== FILE: f18/tests/claim-sibling-address-test.php ===
<?php
// tools/claim-tests/claim-sibling-address-test.php (f18): 'locations' only returns street addresses for claims whose
// status is in $addressStatuses (empty by default = never). Plain PHP, prints PASS/FAIL/SKIP.
// Static checks always run. The HTTP check runs only with the env vars below, only on staging, and only calls the
// read-only 'locations' action (no writes, no 'activate', no money moves).
//   CLAIM_PHP     path to claim.php (optional; otherwise looked up next to this test)
//   CLAIM_URL     full URL of /listing/api/claim.php on staging
//   CLAIM_A_COOKIE / CLAIM_A_CSRF / CLAIM_A_ID   a member and one of their own claims (ideally not yet confirmed)
declare(strict_types=1);

final class T {
    public static int $fail = 0;
    public static function check(string $name, bool $ok, string $why = ''): void {
        echo ($ok ? 'PASS ' : 'FAIL ') . $name . (!$ok && $why !== '' ? ' (' . $why . ')' : '') . "\n";
        if (!$ok) self::$fail++;
    }
}

$path = (string) (getenv('CLAIM_PHP') ?: '');
foreach ([__DIR__ . '/../files/listing/api/claim.php', __DIR__ . '/../../listing/api/claim.php', __DIR__ . '/../../public_html/listing/api/claim.php'] as $p) {
    if ($path === '' && is_file($p)) $path = $p;
}
if ($path === '' || !is_file($path)) { echo "FAIL claim.php not found (set CLAIM_PHP)\n"; exit(1); }
$src = (string) file_get_contents($path);

if (function_exists('exec')) {
    $o = []; $rc = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $o, $rc);
    T::check('php -l claim.php', $rc === 0, implode(' ', $o));
} else {
    echo "SKIP php -l (exec disabled)\n";
}

$switchAt = strpos($src, 'switch ($action) {');
$end = strpos($src, '} catch (DomainException');
$sw = $switchAt !== false && $end !== false ? substr($src, $switchAt, $end - $switchAt) : '';
$parts = preg_split("/\n\s*case '([a-z_]+)':/", $sw, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
$cases = [];
for ($i = 1; $i + 1 < count($parts); $i += 2) $cases[$parts[$i]] = $parts[$i + 1];
$loc = $cases['locations'] ?? '';

T::check('$addressStatuses defined', preg_match('/\$addressStatuses = \[[^\]]*\];/', $src) === 1);
T::check("case 'locations' found", $loc !== '');
T::check('locations decides $showAddress from the claim status', str_contains($loc, "\$showAddress = in_array((string) \$c['status'], \$addressStatuses, true);"));
T::check('address is gated on $showAddress', str_contains($loc, "'address' => \$showAddress ? (\$r['address'] ?? '') : ''"));
T::check('no ungated address left', !str_contains($loc, "'address' => \$r['address'] ?? ''"));
T::check('locations keeps its own owner check', str_contains($loc, "(int) \$c['user_id'] !== \$uid"));
T::check('f04 ownership guard still present before the switch',
    ($g = strpos($src, 'if (in_array($action, $ownedActions, true)) {')) !== false && $switchAt !== false && $g < $switchAt);
T::check('f17 dispute response still has no claim_id', isset($cases['dispute']) && !str_contains($cases['dispute'], "'claim_id'"));

// the gating expression itself, run on sample rows (mirrors the line above)
$rows = [['id' => 1, 'name' => 'A', 'city' => 'X', 'address' => '1 Main St'], ['id' => 2, 'name' => 'B', 'city' => 'Y']];
foreach ([[[], 'pending', false], [['confirmed'], 'pending', false], [['confirmed'], 'confirmed', true]] as [$statuses, $status, $want]) {
    $showAddress = in_array($status, $statuses, true);
    $got = array_map(fn ($r) => $showAddress ? ($r['address'] ?? '') : '', $rows);
    $leak = $got[0] !== '';
    T::check('sample: status ' . $status . ' with allowlist [' . implode(',', $statuses) . '] ' . ($want ? 'shows' : 'hides') . ' address', $leak === $want);
}

$url = (string) (getenv('CLAIM_URL') ?: '');
$env = ['CLAIM_A_COOKIE', 'CLAIM_A_CSRF', 'CLAIM_A_ID'];
$have = $url !== '' && !in_array(false, array_map(fn ($k) => getenv($k) !== false && getenv($k) !== '', $env), true);
if (!$have || !function_exists('curl_init')) {
    echo "SKIP HTTP check (set CLAIM_URL and " . implode(', ', $env) . "; needs ext-curl)\n";
} else {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'X-CSRF-Token: ' . getenv('CLAIM_A_CSRF'), 'Cookie: ' . getenv('CLAIM_A_COOKIE')],
        CURLOPT_POSTFIELDS => json_encode(['action' => 'locations', 'claim_id' => (int) getenv('CLAIM_A_ID')]),
    ]);
    $res = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    T::check('owner gets 200 on locations', $code === 200, "HTTP $code");
    $find = function ($v, string $key) use (&$find) {
        if (!is_array($v)) return null;
        if (array_key_exists($key, $v)) return $v[$key];
        foreach ($v as $x) { $f = $find($x, $key); if ($f !== null) return $f; }
        return null;
    };
    $json = json_decode($res, true);
    $shown = $find($json, 'addresses_shown');
    $locs = $find($json, 'locations');
    T::check('response says whether addresses are shown', is_bool($shown));
    if ($shown === false && is_array($locs)) {
        T::check('no address in any location while hidden', array_filter($locs, fn ($l) => ($l['address'] ?? '') !== '') === []);
    }
}

echo T::$fail === 0 ? "ALL PASS\n" : T::$fail . " FAILED\n";
exit(T::$fail === 0 ? 0 : 1);
=== END FILE ===

=== FILE: f18/faq/faq-entries.md ===
### Why can't I see the street addresses of my other locations yet?

When you claim a listing, we show you other locations that may belong to the same business, so you can add them to your claim. Until your claim is confirmed, we show each location's name and town but not its street address. This keeps addresses private while a claim is still being checked. Once your claim is confirmed, the full addresses appear in the same list.
=== END FILE ===

=== FILE: f18/release.json ===
{
  "name": "f18-sibling-addresses-need-confirmed-claim",
  "gate": "med: ship after f04 and f17 (this file contains both)",
  "what": "listing/api/claim.php 'locations' action: each sibling location's street address is only returned when the claim's status is in a new $addressStatuses allowlist. Otherwise 'address' is '' (key kept so the page doesn't break). Name, town and id are still returned so locations can still be picked. The response also says addresses_shown: true|false. The allowlist ships EMPTY, so no addresses go out at any status until a person fills in the confirmed status values.",
  "files": ["files/listing/api/claim.php"],
  "decisions": [
    "DECISION NEEDED: which claim statuses count as confirmed? The exact status strings live in app/lib/Claims.php, which is not shown. Safest default: $addressStatuses = [], so addresses are omitted at every status.",
    "The address key is kept as '' rather than removed, so existing page code that reads it keeps working.",
    "add_locations is unchanged: a member can still add siblings by id at any status (out of scope for f18)."
  ],
  "tests": ["tests/claim-sibling-address-test.php (static: lint, gating present, no ungated address, f04/f17 still present, sample-row check of the gating; optional staging HTTP: owner's 'locations' has no addresses while addresses_shown is false)"],
  "risks": [
    "NOTHING HERE HAS BEEN RUN OR VERIFIED: not linted, not tested, not deployed. The lane session must run php -l, the test and a zip check.",
    "With the empty default, confirmed owners also stop seeing sibling addresses until the allowlist is filled in. Members with same-named locations in one town may find them harder to tell apart.",
    "If the allowlist is filled with strings that don't exactly match Claims.php statuses, it fails closed (no addresses). That is safe but looks broken.",
    "This file carries f04 and f17. Shipping an earlier release's file after this one would undo f18."
  ],
  "verify": [
    "app/lib/Claims.php: file not shown. Read the claim status values. Ask the owner of decisions/0070 which ones mean confirmed (e.g. the status after 'confirm' → Managed by owner, or after domain/document/admin verification), and put the exact strings in $addressStatuses.",
    "Claims::siblings: file not shown. Confirm it doesn't return other private fields; this release only filters 'address'.",
    "Claim page JS: file not shown. Confirm it shows sensibly when address is '' (e.g. falls back to name and town). Optionally show the FAQ text when addresses_shown is false.",
    "FAQ entry: place it where claim FAQs live; wording assumes the allowlist will be filled in.",
    "Live path: header says /listing/api/claim.php; requires reach ../../../app. Place it at the real live path (it may be under a web-root folder)."
  ],
  "regions": false
}
=== END FILE ===