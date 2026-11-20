<?php
/**
 * app/lib/Wdqs.php — the one way the site asks Wikidata's query service (query.wikidata.org), within its limits (Jason,
 * 2026-11-20, approved; the board's issue #26, a 429 since Nov 18). The service limits each caller by query time a minute,
 * so: one query at a time across the site (a database lock, skipped when busy, never queued), the wait it asks for kept
 * and honoured by every caller (Retry-After on a 429 or 503), and each answer kept 7 days so a page doesn't ask again.
 * It changes how we ask, not what we read. Its callers: PlaceFinder::get, Dossier::get.
 *   Wdqs::is($url)                          whether a URL is the query service
 *   Wdqs::ask($url, fn () => [code, body, retryAfter])   the cached body, else the call within the rules; null when not asked or failed
 */
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/PageCache.php';

final class Wdqs
{
    public const HOST = 'query.wikidata.org';
    public const KEEP = 7 * 86400;          // an answer, kept 7 days
    private const WAIT = 'wdqs:wait-until'; // the time Wikidata asked us to wait until (unix seconds)
    private const LOCK = 'tv_wdqs';
    private const LOCK_SECONDS = 5;         // how long a caller waits for the one query in flight before skipping

    public static function is(string $url): bool
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST)) === self::HOST;
    }

    /** Why the service mustn't be asked now ('' when it may). */
    public static function waiting(PDO $pdo): string
    {
        $u = (int) (PageCache::get($pdo, self::WAIT) ?: 0);
        return $u > time() ? 'Wikidata asked us to wait until ' . gmdate('H:i', $u) . ' UTC.' : '';
    }

    /** Seconds to wait from a Retry-After value (seconds, or an HTTP date), else from the status; 60 s to an hour. */
    public static function waitFor(int $code, string $retryAfter): int
    {
        $r = trim($retryAfter);
        $s = ctype_digit($r) ? (int) $r : (($t = strtotime($r)) ? $t - time() : 0);
        if ($s <= 0) $s = $code === 429 ? 60 : 30;
        return max(30, min(3600, $s));
    }

    /**
     * The answer to a query URL: kept, else asked within the rules. $call does the request and returns
     * [int code, ?string body, string retryAfter]. Returns the body (200 only), or null when not asked or it failed.
     */
    public static function ask(string $url, callable $call): ?string
    {
        try { $pdo = Database::connection(); } catch (Throwable $e) { $pdo = null; }
        if (!$pdo) { [$c, $b] = $call(); return $c === 200 && is_string($b) ? $b : null; }   // no database: as before
        $key = 'wdqs:' . sha1($url);
        $hit = PageCache::get($pdo, $key);
        if (is_string($hit)) return $hit;
        if (self::waiting($pdo) !== '') return null;   // honour the wait Wikidata asked for
        $got = false;
        try { $got = (int) $pdo->query("SELECT GET_LOCK('" . self::LOCK . "', " . self::LOCK_SECONDS . ')')->fetchColumn() === 1; } catch (Throwable $e) { $got = true; }   // no locks: ask anyway
        if (!$got) return null;   // another query is in flight: skip, never pile up
        try {
            $hit = PageCache::get($pdo, $key);   // answered while we waited for the lock
            if (is_string($hit)) return $hit;
            [$code, $body, $after] = $call();
            if ($code === 429 || $code === 503) { PageCache::put($pdo, self::WAIT, 3600, time() + self::waitFor($code, (string) $after)); return null; }
            if ($code !== 200 || !is_string($body)) return null;
            PageCache::put($pdo, $key, self::KEEP, $body);
            return $body;
        } finally {
            try { $pdo->query("SELECT RELEASE_LOCK('" . self::LOCK . "')"); } catch (Throwable $e) {}
        }
    }
}
