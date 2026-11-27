<?php
// business/api.php — the business portal's one endpoint (decisions/0110 wall 1, 0115): /business/api/<listing>/<module>.
// The same actions as the admin's (list, get, act, create) on the same tool, after the same checks as the page: your
// role on this listing (seeing needs view, changing needs edit), then its plan. Then the business realm opens (the
// listings login, that business only) and the tool runs there. A refusal by the walls is a security event.
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/business/portal.php';
require_once __DIR__ . '/../../app/lib/BusinessData.php';

header('Cache-Control: no-store');
$me = Auth::currentUser();
if (!$me) Response::error('Sign in first.', 401, 'unauthenticated');
$uid = (int) $me['id'];
$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if (!preg_match('#/business/api/(\d+)/([a-z]+)$#', $path, $x)) Response::error('Not a business tool.', 404);
[, $id, $mk] = $x; $id = (int) $id;
$main = Database::connection();
$ctx = tv_biz_context($main, $uid, $id);
if (!$ctx) { error_log('SECURITY business api: user ' . $uid . ' called business ' . $id . ' without a role on it'); Response::error('This isn\'t one of your businesses.', 403, 'forbidden'); }
$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$in = $post ? Response::jsonBody() : $_GET;
if ($post) Response::requireCsrf();
$act = (string) ($in['action'] ?? 'list');
if ($mk === 'settings' && $post && in_array($act, ['team_add', 'team_role', 'team_remove', 'invite_cancel', 'invite_send'], true)) {   // the team (decisions/0118): Team checks the role
    try {
        if ($act === 'team_add') Response::success(['message' => Team::invite($main, $uid, $id, (string) ($in['email'] ?? ''), (string) ($in['role'] ?? ''), $ctx['name'], (string) ($in['name'] ?? ''))]);
        if ($act === 'team_role') { Team::setRole($main, $uid, $id, (int) ($in['user'] ?? 0), (string) ($in['role'] ?? '')); Response::success(['message' => 'Role changed.']); }
        if ($act === 'team_remove') { Team::remove($main, $uid, $id, (int) ($in['user'] ?? 0)); Response::success(['message' => (int) ($in['user'] ?? 0) === $uid ? 'You\'ve left the team.' : 'Taken off the team.']); }
        if ($act === 'invite_send') { require_once __DIR__ . '/../../app/lib/Clients.php'; $iv = Clients::invite($main, (int) ($in['invite'] ?? 0)); if (!$iv || (int) $iv['listing_id'] !== $id) Response::error('That invitation isn\'t here.'); Clients::sendInvite($main, $uid, (int) $iv['id'], false); Response::success(['message' => 'Sent to ' . $iv['email'] . '.']); }
        Team::cancelInvite($main, $uid, $id, (int) ($in['invite'] ?? 0)); Response::success(['message' => 'Invitation cancelled.']);
    } catch (RuntimeException $x) { Response::error($x->getMessage()); }
}
if ($mk === 'settings' && $post && in_array($act, ['transfer_ask', 'transfer_cancel'], true)) {   // handing over ownership (decisions/0103 §2, 0120)
    try {
        if ($act === 'transfer_ask') Response::success(['message' => Team::requestTransfer($main, $uid, $id, (string) ($in['who'] ?? ''), $ctx['name'])]);
        Team::cancelTransfer($main, $uid, $id); Response::success(['message' => 'Cancelled. You\'re still the Owner.']);
    } catch (RuntimeException $x) { Response::error($x->getMessage()); }
}
if ($mk === 'settings' && $post && $act === 'quote_defaults') {   // the business's usual quote terms (decisions/0119)
    [$qok, $qwhy] = tv_biz_can($main, $ctx, 'quotes', 'edit');
    if (!$qok) Response::error($qwhy . '.', 403, 'forbidden');
    try { $bp = BusinessData::open($id); require_once __DIR__ . '/../../app/lib/BizQuotes.php'; BizQuotes::saveDefaults($bp, $in); Response::success(['message' => 'Saved. New quotes start with these.']); }
    catch (InvalidArgumentException $x) { Response::error($x->getMessage()); }
}
if ($mk === 'settings' && $post && $act === 'white_glove') {   // the owner turns White Glove access off or on (decisions/0117)
    if ($ctx['role'] !== 'owner') Response::error('Only the owner turns White Glove access off or on.', 403, 'forbidden');
    require_once __DIR__ . '/../../app/lib/SupportAccess.php';
    SupportAccess::ownerSet($main, $id, !empty($in['on']), $uid);
    Response::success(['message' => !empty($in['on']) ? 'White Glove access is on.' : 'White Glove access is off.']);
}
if ($mk === 'checkout' && $post) {   // E-commerce setup (decisions/0135 step 4): its own actions
    require_once __DIR__ . '/../../app/business/checkout.php';
    try { Response::success(['message' => tv_biz_checkout_act($main, $ctx, $uid, $act, $in)]); }
    catch (InvalidArgumentException | RuntimeException $x) { Response::error($x->getMessage(), 422, 'not_allowed'); }
}
if ($mk === 'storefront' && $post) {   // Storefront & pages (decisions/0139 step 5a): its own actions
    require_once __DIR__ . '/../../app/business/storefront.php';
    try { $r = tv_biz_storefront_act($main, $ctx, $uid, $act, $in); Response::success(is_array($r) ? $r : ['message' => $r]); }   // a domain search answers with its list
    catch (InvalidArgumentException | RuntimeException $x) { Response::error($x->getMessage(), 422, 'not_allowed'); }
}
if ($mk === 'trust' && $post) {   // Trust score (decisions/0141 §3): ask Stripe or Square again how the account stands
    require_once __DIR__ . '/../../app/business/trust.php';
    try { Response::success(['message' => tv_biz_trust_act($main, $ctx, $uid, $act, $in)]); }
    catch (InvalidArgumentException | RuntimeException $x) { Response::error($x->getMessage(), 422, 'not_allowed'); }
}
if ($mk === 'policies' && $post) {   // Store policies (decisions/0142): save and publish them
    require_once __DIR__ . '/../../app/business/policies.php';
    try { Response::success(['message' => tv_biz_policies_act($main, $ctx, $uid, $act, $in)]); }
    catch (InvalidArgumentException | RuntimeException $x) { Response::error($x->getMessage(), 422, 'not_allowed'); }
}
if ($mk === 'events' && $post) {   // Events (decisions/0134's minimum): save one, publish, cancel, delete and their ways back
    require_once __DIR__ . '/../../app/business/events.php';
    if (($in['code'] ?? '') !== '' && ($in['code'] ?? null) !== null) { try { BusinessData::open($id); Scope::setPeople($ctx['people']); } catch (Throwable $x) { error_log('business events api: ' . $x->getMessage()); Response::error('Your discount codes can\'t be reached right now. Try again in a minute, or save the offer without a code.', 503, 'realm'); } }   // a chosen code is checked in the business's own data (0110)
    try { $r = tv_biz_events_act($main, $ctx, $uid, $act, $in); Response::success(is_array($r) ? $r : ['message' => $r]); }   // finding a business to invite answers with its list
    catch (InvalidArgumentException | RuntimeException $x) { Response::error($x->getMessage(), 422, 'not_allowed'); }
}
if ($mk === 'marketplace' && $post) {   // publishing to the Marketplace (decisions/0135 step 3): its own actions
    require_once __DIR__ . '/../../app/business/marketplace.php';
    try { $r = tv_biz_marketplace_act($main, $ctx, $uid, $act, $in); Response::success(is_array($r) ? $r : ['message' => $r]); }   // a restricted item answers with its question
    catch (InvalidArgumentException | RuntimeException $x) { Response::error($x->getMessage(), 422, 'not_allowed'); }
}
[$ok, $why] = tv_biz_can($main, $ctx, $mk, in_array($act, ['act', 'create'], true) ? 'edit' : 'view');
if (!$ok) Response::error(rtrim($why, '.') . '.', 403, 'forbidden');
$tool = (require __DIR__ . '/../../app/admin/tools.php')[tv_biz_modules()[$mk]['tool'] ?? ''] ?? null;
if (!$tool) Response::error('Not a business tool.', 404);

try {
    if ($ctx['role'] === 'white_glove' && in_array($act, ['act', 'create'], true))   // our agent's every change is in the action log (0102 §5)
        Audit::log($main, ['user' => $uid, 'listing' => $id, 'route' => 'white_glove', 'action' => $mk . '.' . ($act === 'act' ? (string) ($in['act'] ?? '') : 'create'), 'detail' => $act === 'act' ? 'item ' . (int) ($in['id'] ?? 0) : '']);
    $pdo = BusinessData::open($id);
    Scope::setPeople($ctx['people']);
    $detail = function (int $iid) use ($tool, $pdo) {
        $d = ($tool['get'])($pdo, $iid);
        if (!$d) throw new InvalidArgumentException('That isn\'t here.');
        $d = (array) $d + ['subtitle' => '', 'fields' => [], 'links' => []];
        $d['fields'] = array_values(array_filter($d['fields'], fn ($f) => trim((string) $f[1]) !== ''));
        $d['id'] = $iid;
        return $d;
    };
    if ($act === 'list') {
        // the lists' standard, as the admin's (2026-11-12): so many a page (10, 25, 50, 100), any column sorts, the pages
        $per = in_array((int) ($in['per'] ?? 10), [10, 25, 50, 100], true) ? (int) $in['per'] : 10;
        $in['per'] = $per;
        [$rows, $total, $counts] = ($tool['list'])($pdo, $in, $uid);
        $sort = (string) ($in['sort'] ?? '');
        if ($sort !== '' && $rows && array_key_exists($sort, $rows[0])) {
            $d = ($in['dir'] ?? '') === 'desc' ? -1 : 1;
            usort($rows, fn ($x, $y) => $d * strnatcasecmp((string) ($x[$sort] ?? ''), (string) ($y[$sort] ?? '')));
        }
        $page = max(0, (int) ($in['page'] ?? 0));
        Response::success(['rows' => array_slice($rows, $page * $per, $per), 'total' => count($rows), 'counts' => (object) $counts, 'per' => $per,
                           'sortable' => array_map(fn ($c) => (string) $c[0], (array) ($tool['columns'] ?? []))]);
    }
    if ($act === 'get') Response::success(['item' => $detail((int) ($in['id'] ?? 0))]);
    if (!$post) Response::error('Send it as a POST.', 405);
    if ($act === 'act') {
        $iid = (int) ($in['id'] ?? 0);
        $msg = ($tool['act'])($pdo, $iid, (string) ($in['act'] ?? ''), (array) ($in['input'] ?? []), $uid);
        if (is_array($msg) && isset($msg['draft'])) Response::success(['draft' => (string) $msg['draft'], 'message' => (string) ($msg['message'] ?? '')]);   // written words for a box, not saved
        if (is_array($msg) && !empty($msg['ask'])) Response::success(['ask' => $msg['ask']]);   // a question first (a licensed item: is it, and its conditions); the page sends the answer
        if (is_array($msg) && !empty($msg['gone'])) Response::success(['gone' => true, 'message' => (string) ($msg['message'] ?? 'Deleted.')]);   // the record is gone: the drawer closes
        Response::success(['item' => $detail($iid), 'message' => is_string($msg) ? $msg : 'Done.']);
    }
    if ($act === 'create' && !empty($tool['create']['do'])) {
        $vals = (array) ($in['input'] ?? []);
        foreach ((is_callable($tool['create']['fields'] ?? null) ? ($tool['create']['fields'])($pdo) : (array) ($tool['create']['fields'] ?? [])) as $f) if (($f[4] ?? '') !== 'optional' && trim((string) ($vals[$f[0]] ?? '')) === '') Response::error('Fill in ' . strtolower($f[1]) . '.');
        Response::success(($tool['create']['do'])($pdo, $vals, $uid));
    }
    Response::error('Unknown action.');
} catch (InvalidArgumentException | RuntimeException $e) {
    Response::error($e->getMessage());
} catch (Throwable $e) {
    if (BusinessData::refused($e)) { error_log('SECURITY business api: wall refused user ' . $uid . ' on business ' . $id . ': ' . $e->getMessage()); Response::error('That isn\'t this business\'s.', 403, 'forbidden'); }
    error_log('business api ' . $path . ': ' . $e->getMessage());
    Response::error('Something went wrong. Try again.', 500);
}
