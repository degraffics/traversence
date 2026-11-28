<?php
// Tests TagCardCache::forget() against a stub PageCache. This is not the real one, because PageCache.php was not shown.
// Run with: php f09/tests/tag-card-cache-forget-tests.php
declare(strict_types=1);

$fail = 0;
function check(string $name, bool $ok): void { global $fail; echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL; if (!$ok) $fail++; }

if (!extension_loaded('pdo_sqlite')) { echo "SKIP: pdo_sqlite not loaded\n"; exit(0); }
$pdo = new PDO('sqlite::memory:');

// No PageCache class defined yet, so forget() must return false and not throw.
require_once __DIR__ . '/../files/app/lib/TagCardCache.php';
ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
check('without PageCache::forget returns false', TagCardCache::forget($pdo, 'entity:12') === false);

// Stub PageCache that records which keys were forgotten.
final class PageCache
{
    public static array $forgotten = [];
    public static function forget(PDO $pdo, string $key): void { self::$forgotten[] = $key; }
}

check('forget valid ref returns true', TagCardCache::forget($pdo, 'entity:12') === true);
check('forget clears tagcard:entity:12', in_array('tagcard:entity:12', PageCache::$forgotten, true));
check('forgetThing landmark', TagCardCache::forgetThing($pdo, 'landmark', 7) === true && in_array('tagcard:landmark:7', PageCache::$forgotten, true));
check('forget trims ref', TagCardCache::forget($pdo, ' town:3 ') === true && in_array('tagcard:town:3', PageCache::$forgotten, true));

$before = count(PageCache::$forgotten);
check('bad ref rejected', TagCardCache::forget($pdo, 'entity:abc') === false);
check('bad ref injection rejected', TagCardCache::forget($pdo, "entity:1' OR 1=1") === false);
check('bad ref does not touch cache', count(PageCache::$forgotten) === $before);

echo $fail === 0 ? "ALL PASS\n" : "$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
