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
