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
