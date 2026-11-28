<?php
// listing/api/evidence.php — what we found about a claimed business, offered to its owner (decisions/0062, 2026-10-04).
// On a claimed listing the owner decides: evidence never changes it on its own. What its own website, official
// registries and other matching pages say that differs from the listing is offered here, with where it was found, to
// use or dismiss. Owner only.
//   GET  ?id=8753 -> {offers: [{fact_id, fact, label, value, now, sources: [{kind, site, url, label}]}], csrf}
//   POST {id, fact_id, action: use|dismiss} (X-CSRF-Token) -> {offers}
// f19: 'use' is refused while the claim is frozen by a dispute. 'use' is also refused for protected facts until an
// admin approves them. Both checks fail closed. 'dismiss' never changes the listing, so it is not gated.

declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/lib/Identity.php';

// f19: protected facts cannot be applied by the owner directly. This list is the safest default and needs a
// person's decision (see release.json verify).
const EVIDENCE_PROTECTED_FACTS = ['name', 'website', 'phone', 'email', 'address'];

// f19: true when the claim on this listing is frozen by a dispute. Fails closed: if Claims or Claims::frozen is
// missing, or the check throws, the listing is treated as frozen.
function evidence_claim_frozen(PDO $pdo, int $id): bool
{
    $claimsFile = __DIR__ . '/../../../app/lib/Claims.php';
    if (!class_exists('Claims') && is_file($claimsFile)) require_once $claimsFile;
    if (!class_exists('Claims') || !method_exists('Claims', 'frozen')) return true;
    try {
        return (bool) Claims::frozen($pdo, $id);
    } catch (Throwable $e) {
        return true;
    }
}

header('Cache-Control: no-store');
$me = Auth::currentUser();
if ($me === null) Response::error('Sign in first.', 401, 'unauthenticated');
$pdo = Database::connection();
$body = $_SERVER['REQUEST_METHOD'] === 'POST' ? Response::jsonBody() : $_GET;
$id = (int) ($body['id'] ?? 0);
$st = $pdo->prepare("SELECT owner_user_id FROM entities WHERE id = ? AND entity_type = 'merchant'");
$st->execute([$id]);
if ((int) $st->fetchColumn() !== (int) $me['id'] || $id <= 0) Response::error('Only the owner of this listing can see this.', 403, 'forbidden');
if (!Identity::ready($pdo)) Response::json(['ok' => true, 'offers' => [], 'csrf' => (string) ($_SESSION['csrf_token'] ?? '')]);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Response::requireCsrf();
        $fid = (int) ($body['fact_id'] ?? 0);
        $act = (string) ($body['action'] ?? '');
        if ($act === 'use') {
            if (evidence_claim_frozen($pdo, $id)) {
                Response::error('This listing is under review because of a dispute. You cannot make changes until the review ends.', 409, 'claim_frozen');
            }
            $fact = null;
            foreach (Identity::offers($pdo, $id) as $offer) {
                if ((int) ($offer['fact_id'] ?? 0) === $fid) { $fact = (string) ($offer['fact'] ?? ''); break; }
            }
            if ($fact === null) Response::error('That suggestion is no longer available.', 422, 'bad_request');
            if (in_array($fact, EVIDENCE_PROTECTED_FACTS, true)) {
                Response::error('An admin needs to approve changes to this detail. It has not been changed.', 409, 'needs_admin');
            }
            Identity::ownerUse($pdo, $id, $fid, (int) $me['id']);
        }
        elseif ($act === 'dismiss') Identity::ownerDismiss($pdo, $id, $fid, (int) $me['id']);
        else Response::error('Use or dismiss.', 422, 'bad_request');
    }
    Response::json(['ok' => true, 'offers' => Identity::offers($pdo, $id), 'csrf' => (string) ($_SESSION['csrf_token'] ?? '')]);
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), 422, 'bad_request');
}
