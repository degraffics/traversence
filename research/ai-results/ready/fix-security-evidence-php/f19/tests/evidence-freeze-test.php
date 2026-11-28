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
