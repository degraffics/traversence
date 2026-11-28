<?php
// /listing/api/leave.php — Leave a message on an unclaimed listing (decisions/0172 §3.3, ListingMessages). Works signed
// out; POSTs need X-CSRF-Token. A message is accepted only with a way to answer its sender: a member account, a
// confirmed email (a link first; nothing goes until it's pressed), or a phone with the guest's say-so.
//   GET  ?challenge=ENTITY_ID       -> {route, before, button, nonce, question, signed_in, csrf, mine: [the member's own here]}
//   POST {do:'leave', entity_id, body, [guest: nonce, answer, website, name, answer_by: email|phone, email, phone, phone_ok]}
//                                   -> {state, said, code (a guest's, shown once)}
//   POST {do:'check', code}         -> {thread}              where it stands, its words (the code travels in the body)
//   POST {do:'reply', code, body} / {do:'reply', id, body} -> {thread} / {mine}   writing again, once it's been answered
//   POST {do:'withdraw', code} / {do:'withdraw', id}       -> {}                   any time before it's delivered
//   POST {do:'resend', code}        -> {}                    a new confirm link to the guest's email
//   GET  ?mine=1                    -> {items}               a member's left messages (their Pulse lists the same)
// Bearer-code actions (check / reply / withdraw / resend with a code) are throttled by LeaveThrottle (finding f20):
// failed codes per IP, 'check' reads per IP, and confirm-link resends per code and per IP. Over a limit -> 429 rate_limited.
declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/lib/ListingMessages.php';
require_once __DIR__ . '/../../../app/lib/LeaveThrottle.php';

header('Cache-Control: no-store');
$pdo = Database::connection();
if (!ListingMessages::ready($pdo)) Response::error('Leaving a message isn\'t switched on yet. Call them instead.', 503, 'not_configured');
$uid = Auth::currentUserId();

$code = '';
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$throttle = null;
$codeAction = false;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!empty($_GET['mine'])) {
            if ($uid === null) Response::error('Sign in to see your messages.', 401, 'unauthenticated');
            Response::json(['ok' => true, 'items' => ListingMessages::forMember($pdo, $uid)]);
        }
        $e = (int) ($_GET['challenge'] ?? 0);
        $route = ListingMessages::routeFor($pdo, $e);
        [$before, $button] = ListingMessages::words($route);
        Response::json(['ok' => true, 'route' => $route, 'before' => $before, 'button' => $button, 'signed_in' => $uid !== null,
                        'csrf' => $_SESSION['csrf_token'] ?? null, 'mine' => $uid !== null ? ListingMessages::forMember($pdo, $uid, $e) : []]
                       + ($uid === null ? GuestInquiries::challenge() : []));
    }
    Response::requireMethod('POST');
    Response::requireCsrf();
    $b = Response::jsonBody();
    $do = (string) ($b['do'] ?? 'leave');
    $code = trim((string) ($b['code'] ?? ''));
    $id = (int) ($b['id'] ?? 0);

    $codeAction = $code !== '' && in_array($do, ['check', 'reply', 'withdraw', 'resend'], true);
    if ($codeAction) {
        $throttle = new LeaveThrottle($pdo);
        $why = $throttle->admit($do, $code, $ip);
        if ($why !== null) {
            header('Retry-After: ' . LeaveThrottle::retryAfter($why));
            if ($why === 'resend') {
                Response::error('A confirm link was sent recently. Check your inbox (and spam folder), then wait a few minutes before asking again.', 429, 'rate_limited');
            }
            Response::error('Too many tries with a reply code. Please wait a few minutes and try again.', 429, 'rate_limited');
        }
    }

    switch ($do) {
        case 'leave':
            Response::json(['ok' => true] + ListingMessages::leave($pdo, (int) ($b['entity_id'] ?? 0), $uid, $b));
        case 'check':
            $thread = GuestInquiries::guestView($pdo, $code);
            if (empty($thread) && $throttle !== null) $throttle->failed($code, $ip);
            Response::json(['ok' => true, 'thread' => $thread]);
        case 'reply':
            if ($code !== '') { GuestInquiries::guestReply($pdo, $code, (string) ($b['body'] ?? '')); Response::json(['ok' => true, 'thread' => GuestInquiries::guestView($pdo, $code)]); }
            if ($uid === null) Response::error('Sign in, or use your reply code.', 401, 'unauthenticated');
            ListingMessages::memberFollowUp($pdo, $uid, $id, (string) ($b['body'] ?? ''));
            Response::json(['ok' => true, 'mine' => ListingMessages::forMember($pdo, $uid, (int) ($b['entity_id'] ?? 0))]);
        case 'withdraw':
            if ($code === '' && $uid === null) Response::error('Sign in, or use your reply code.', 401, 'unauthenticated');
            ListingMessages::withdraw($pdo, $uid, $id, $code);
            Response::json(['ok' => true]);
        case 'resend':
            ListingMessages::resendConfirm($pdo, $code);
            Response::json(['ok' => true]);
    }
    Response::error('Unknown request.', 400, 'bad_request');
} catch (InvalidArgumentException $x) {
    if ($codeAction && $throttle !== null) $throttle->failed($code, $ip);
    Response::error($x->getMessage(), 422, 'not_allowed');
}
