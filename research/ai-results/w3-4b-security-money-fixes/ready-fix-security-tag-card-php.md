=== FILE: f09/files/api/tag-card.php ===
<?php
// /api/tag-card.php?ref=entity:12 — what a highlighted name in a piece of content opens as its preview card (decisions/0132
// §2a): the name, what it is, where, a line about it and its page. Public facts only; nothing for a hidden, held or
// unknown thing. Cached briefly on the server (TagCardCache::TTL) and privately in the browser only, so a hide or hold
// stops showing within minutes even before TagCardCache::forget() is wired into the hide/hold actions (f09).
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/ContentTags.php';
require_once __DIR__ . '/../../app/lib/PageCache.php';
require_once __DIR__ . '/../../app/lib/TagCardCache.php';

$ref = trim((string) ($_GET['ref'] ?? ''));
if (!preg_match('/^(entity:\d+|landmark:\d+|rec:[A-Za-z0-9_-]{1,40}|(hub|geo_hub|cluster|town|sub):\d+)$/', $ref)) Response::error('Unknown.', 404, 'not_found');
try {
    $pdo = Database::connection();
    $c = PageCache::remember($pdo, TagCardCache::key($ref), TagCardCache::TTL, fn () => ContentTags::card($pdo, $ref) ?? ['none' => 1]);
    if (!is_array($c) || !empty($c['none'])) Response::error('Unknown.', 404, 'not_found');
    header('Cache-Control: private, max-age=' . TagCardCache::BROWSER_MAX_AGE);
    Response::json(['ok' => true, 'card' => $c]);
} catch (Throwable $e) {
    error_log('tag-card: ' . $e->getMessage());
    Response::error('Unavailable.', 503, 'unavailable');
}
=== END FILE ===

=== FILE: f09/files/app/lib/TagCardCache.php ===
<?php
// TagCardCache: one place for the /api/tag-card.php cache key and lifetimes, plus forget() for the hide/hold actions
// (f09). A hidden or held thing must stop appearing in preview cards right away.
//
// forget() only works if PageCache has a static forget(PDO $pdo, string $key) method. PageCache.php was not shown
// when this was drafted. If that method does not exist, forget() logs and returns false. Cards then fall back to
// expiring within TTL + BROWSER_MAX_AGE seconds.
declare(strict_types=1);

final class TagCardCache
{
    /** Server-side cache lifetime in seconds. Was 3600. */
    public const TTL = 300;

    /** Browser-only cache lifetime in seconds, sent as Cache-Control: private. Was public, 600. */
    public const BROWSER_MAX_AGE = 60;

    /** Same pattern tag-card.php accepts. */
    public const REF_PATTERN = '/^(entity:\d+|landmark:\d+|rec:[A-Za-z0-9_-]{1,40}|(hub|geo_hub|cluster|town|sub):\d+)$/';

    public static function key(string $ref): string
    {
        return 'tagcard:' . $ref;
    }

    /**
     * Drop the cached card for one ref, for example 'entity:12'. Call this right after something is hidden, held,
     * unhidden or released.
     * Returns true only if the cache entry was actually cleared.
     */
    public static function forget(\PDO $pdo, string $ref): bool
    {
        $ref = trim($ref);
        if (!preg_match(self::REF_PATTERN, $ref)) {
            return false;
        }
        if (!class_exists('PageCache', false) || !method_exists('PageCache', 'forget')) {
            error_log('TagCardCache: PageCache::forget not available; ' . $ref . ' expires within ' . (self::TTL + self::BROWSER_MAX_AGE) . 's');
            return false;
        }
        try {
            \PageCache::forget($pdo, self::key($ref));
            return true;
        } catch (\Throwable $e) {
            error_log('TagCardCache: forget failed for ' . $ref . ': ' . $e->getMessage());
            return false;
        }
    }

    /** Convenience for a hide/hold action that knows the kind and id, for example ('entity', 12). */
    public static function forgetThing(\PDO $pdo, string $kind, string|int $id): bool
    {
        return self::forget($pdo, $kind . ':' . $id);
    }
}
=== END FILE ===

=== FILE: f09/tests/tag-card-cache-static-tests.php ===
<?php
// tools/tag-card-tests style. Plain PHP and does not need bootstrap or a database.
// Checks the drafted tag-card.php no longer allows public caching or a 1-hour server cache.
// Run with: php f09/tests/tag-card-cache-static-tests.php
declare(strict_types=1);

$fail = 0;
function check(string $name, bool $ok): void { global $fail; echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL; if (!$ok) $fail++; }

$api = @file_get_contents(__DIR__ . '/../files/api/tag-card.php');
check('tag-card.php readable', is_string($api));
$api = (string) $api;

check('no public Cache-Control', stripos($api, 'Cache-Control: public') === false);
check('sends private Cache-Control', stripos($api, "Cache-Control: private") !== false);
check('no 3600s server TTL literal', strpos($api, '3600') === false);
check('uses TagCardCache::key', strpos($api, 'TagCardCache::key($ref)') !== false);
check('uses TagCardCache::TTL', strpos($api, 'TagCardCache::TTL') !== false);
check('requires TagCardCache.php', strpos($api, "/../../app/lib/TagCardCache.php'") !== false);

require_once __DIR__ . '/../files/app/lib/TagCardCache.php';
check('TTL at most 300s', TagCardCache::TTL <= 300);
check('browser max-age at most 60s', TagCardCache::BROWSER_MAX_AGE <= 60);
check('key format unchanged', TagCardCache::key('entity:12') === 'tagcard:entity:12');
preg_match("#preg_match\('(/\^.*?\\$/)'#", $api, $m);
check('ref pattern matches tag-card.php', isset($m[1]) && $m[1] === TagCardCache::REF_PATTERN);

echo $fail === 0 ? "ALL PASS\n" : "$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
=== END FILE ===

=== FILE: f09/tests/tag-card-cache-forget-tests.php ===
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
=== END FILE ===

=== FILE: f09/faq/faq-entries.md ===
no faq change
=== END FILE ===

=== FILE: f09/release.json ===
{
  "name": "f09-tag-card-stale-hidden",
  "gate": "med",
  "what": "Hidden or held things could keep appearing in tag preview cards for up to about 70 minutes: a 3600s server cache plus 600s public HTTP caching. This draft cuts the server cache to 300s and switches the browser header to 'private, max-age=60', so shared caches and CDNs no longer store cards. It also adds TagCardCache::forget() so hide/hold actions can clear a card immediately. Until forget() is wired in, the worst case is about 6 minutes.",
  "files": [
    "files/api/tag-card.php",
    "files/app/lib/TagCardCache.php"
  ],
  "decisions": [
    "Safest default: server TTL is 300s, down from 3600s, and the header is 'Cache-Control: private, max-age=60', down from 'public, max-age=600'. Someone must decide whether a 5-minute server TTL is acceptable for load once forget() is wired in, and whether to raise it back toward 3600.",
    "Negative results ('none') are still cached for the TTL. An unhidden or released thing may stay 404 for up to 5 minutes unless forget() is also called on unhide/release.",
    "The cache key format 'tagcard:<ref>' is unchanged, so existing entries expire naturally. No migration is needed."
  ],
  "tests": [
    "tests/tag-card-cache-static-tests.php",
    "tests/tag-card-cache-forget-tests.php"
  ],
  "risks": [
    "Nothing here has been run, tested or verified. This is a draft for the lane session.",
    "The shorter TTL means more ContentTags::card() queries. Load impact is unmeasured.",
    "forget() relies on PageCache::forget(PDO, string), which was not seen. If it doesn't exist, forget() returns false and logs. It does not break anything, but there is no immediate clearing.",
    "The forget test uses a stub PageCache, not the real class.",
    "Ref strings must match exactly. A thing referenced under more than one ref (for example a rec:<slug> alias) needs each ref forgotten."
  ],
  "verify": [
    "file not shown: app/lib/PageCache.php. Confirm a forget/delete method exists with signature forget(PDO $pdo, string $key). If it is named differently, adjust TagCardCache::forget, or add PageCache::forget in a follow-up.",
    "file not shown: app/lib/ContentTags.php. Confirm card() already returns null for hidden, held and unknown things, as the tag-card.php header comment says.",
    "file not shown: the hide/hold/unhide/release actions (admin or moderation code). Person's decision and wiring needed: call TagCardCache::forgetThing($pdo, $kind, $id) after each state change, for every ref kind that can point at the thing. That step is what makes clearing immediate.",
    "Confirm the live path of tag-card.php relative to the repo root. It is drafted as files/api/tag-card.php, and its require paths ('/../../app/...') imply app/ sits two levels above the file. Place TagCardCache.php in that same app/lib/.",
    "Check that no CDN or proxy rule overrides Cache-Control for /api/tag-card.php, and purge any CDN copies once on deploy.",
    "Run both test scripts with PHP 8.0 and zip-check the release folder."
  ],
  "regions": false
}
=== END FILE ===