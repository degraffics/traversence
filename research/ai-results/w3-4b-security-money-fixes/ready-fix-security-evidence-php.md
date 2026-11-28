=== FILE: f19/files/listing/api/evidence.php ===
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
=== END FILE ===
=== FILE: f19/tests/evidence-freeze-test.php ===
<?php
// tools/listing-tests/ style. Static checks on listing/api/evidence.php for f19. DRAFT: this has not been run.
// Usage: php tests/evidence-freeze-test.php [path/to/listing/api/evidence.php]
declare(strict_types=1);

$path = $argv[1] ?? __DIR__ . '/../files/listing/api/evidence.php';
$src = @file_get_contents($path);
$fails = 0;
function check(string $name, bool $ok): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . ": $name\n"; if (!$ok) $fails++; }

check('file readable', $src !== false);
$src = (string) $src;

$posFrozen  = strpos($src, 'evidence_claim_frozen($pdo, $id)');
$posProt    = strpos($src, 'in_array($fact, EVIDENCE_PROTECTED_FACTS, true)');
$posUse     = strpos($src, 'Identity::ownerUse(');
$posDismiss = strpos($src, "elseif (\$act === 'dismiss')");

check('frozen check present in use branch', $posFrozen !== false);
check('protected-fact check present', $posProt !== false);
check('frozen check runs before ownerUse', $posFrozen !== false && $posUse !== false && $posFrozen < $posUse);
check('protected check runs before ownerUse', $posProt !== false && $posUse !== false && $posProt < $posUse);
check('dismiss is not gated (comes after ownerUse)', $posDismiss !== false && $posDismiss > $posUse);
check('frozen helper fails closed when Claims is missing', (bool) preg_match("/method_exists\('Claims', 'frozen'\)\) return true;/", $src));
check('frozen helper fails closed when the check throws', (bool) preg_match('/catch \(Throwable \$e\) \{\s*return true;/', $src));
check('frozen refusal uses 409 claim_frozen', str_contains($src, "409, 'claim_frozen'"));
check('protected refusal uses 409 needs_admin', str_contains($src, "409, 'needs_admin'"));
check('unknown fact_id is refused before use', str_contains($src, "if (\$fact === null) Response::error("));
check('php -l clean (syntax)', (function () use ($path): bool {
    $out = []; $rc = 1; @exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $rc); return $rc === 0;
})());

echo $fails === 0 ? "ALL PASS\n" : "$fails FAIL\n";
exit($fails === 0 ? 0 : 1);
=== END FILE ===
=== FILE: f19/faq/faq-entries.md ===
### Why can't I use a suggestion for my listing?

There are two reasons this can happen.

- **Your listing is in a dispute.** You cannot make changes while the dispute is being reviewed. This includes using suggestions we found about your business. You can still dismiss suggestions. When the review ends, you can use suggestions again.
- **The detail needs an admin to approve it.** Some details need an admin to check them before they change. These are your business name, website, phone, email and address. If you try to use a suggestion for one of these details, the listing does not change. Sending these suggestions to an admin from this screen is **coming**.
=== END FILE ===
=== FILE: f19/release.json ===
{
  "name": "f19-evidence-use-freeze-and-protected-facts",
  "gate": "lane review: verify Claims::frozen signature and the admin queue before shipping",
  "what": "listing/api/evidence.php: the 'use' action is now refused with 409 claim_frozen while a dispute freezes the claim. The freeze check fails closed if Claims::frozen is missing or throws. 'use' is also refused with 409 needs_admin for protected facts (name, website, phone, email, address). Unknown fact_ids are refused before Identity::ownerUse runs. 'dismiss' is unchanged because it does not change the listing.",
  "files": ["files/listing/api/evidence.php"],
  "decisions": [
    "Freeze check fails closed. If Claims or Claims::frozen cannot be found or errors, 'use' is refused and 'dismiss' still works.",
    "Protected facts are blocked rather than queued. The admin-queue API was not shown, so this release does not invent one. The owner is told the detail was not changed, and the release makes no claim that it was queued.",
    "The protected-fact list is a conservative default: name, website, phone, email, address.",
    "The fact key is found through Identity::offers(), which documents a 'fact' field per offer. This adds one extra offers() call on 'use'."
  ],
  "tests": ["tests/evidence-freeze-test.php"],
  "risks": [
    "Nothing here has been run, tested or zip-checked. This is a DRAFT only.",
    "If Claims::frozen does not exist or takes different arguments, every 'use' will be refused (fail closed). That blocks the feature but is safe.",
    "If Identity::ownerUse already queues protected facts for admin, this release blocks a path that worked correctly.",
    "The test is a static source check plus php -l. It does not exercise the endpoint against a database."
  ],
  "verify": [
    "app/lib/Claims.php: file not shown. Confirm Claims::frozen exists, confirm its signature is (PDO $pdo, int $entityId), and confirm it returns true while a dispute freeze is active. Also confirm the path app/lib/Claims.php and whether bootstrap autoloads it.",
    "app/lib/Identity.php: file not shown. Check whether ownerUse already respects the freeze or queues protected fields. If it queues them, remove the needs_admin block and rely on ownerUse. If it does not, wire this block to the real admin-queue call.",
    "PERSON DECISION: which facts are protected and need admin approval. The default is name, website, phone, email and address. Also confirm these key names match the 'fact' values Identity::offers returns.",
    "PERSON DECISION: whether a blocked protected fact should be queued automatically once a queue API is confirmed. The FAQ marks this as 'coming'.",
    "Confirm Response::error exits, so no code runs after a refusal inside the try block.",
    "Confirm the front end shows the 409 messages for claim_frozen and needs_admin."
  ],
  "regions": false
}
=== END FILE ===