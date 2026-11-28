<?php

declare(strict_types=1);

/**
 * Small file-backed sliding-window rate limiter (finding f14).
 *
 * - Keys are stored only as sha256 hashes, so no plaintext emails or IPs
 *   are written to disk.
 * - Blocked attempts are NOT recorded. A sender cannot extend their own
 *   lockout by retrying, and the window frees up on schedule.
 * - It fails OPEN: if storage is unavailable, the request is allowed and an
 *   error is logged. Broken storage then never stops legitimate password
 *   resets (see release.json decisions).
 *
 * Storage dir: RATE_LIMIT_DIR from the environment. Otherwise
 * <sys temp dir>/traversence-ratelimit.
 */
final class RateLimiter
{
    private static ?string $dirOverride = null;

    /** Test hook: force a storage directory (null = back to env/default). */
    public static function setDirectory(?string $dir): void
    {
        self::$dirOverride = $dir;
    }

    public static function directory(): string
    {
        if (self::$dirOverride !== null) {
            return rtrim(self::$dirOverride, '/');
        }
        $env = getenv('RATE_LIMIT_DIR');
        if (is_string($env) && $env !== '') {
            return rtrim($env, '/');
        }
        return rtrim(sys_get_temp_dir(), '/') . '/traversence-ratelimit';
    }

    /**
     * Try to record one attempt for ($bucket, $key).
     *
     * Returns true when the attempt is within $max per $windowSeconds and has
     * been recorded. Returns false when the limit is already reached; nothing
     * is recorded in that case.
     */
    public static function hit(string $bucket, string $key, int $max, int $windowSeconds, ?int $now = null): bool
    {
        if ($max < 1 || $windowSeconds < 1) {
            throw new InvalidArgumentException('RateLimiter: max and windowSeconds must be >= 1.');
        }
        $now = $now ?? time();

        $dir = self::directory();
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            error_log('RateLimiter: cannot create storage dir ' . $dir . ' — failing open.');
            return true;
        }

        $file = self::fileFor($bucket, $key);
        $fh = @fopen($file, 'c+');
        if ($fh === false) {
            error_log('RateLimiter: cannot open ' . $file . ' — failing open.');
            return true;
        }

        try {
            if (!flock($fh, LOCK_EX)) {
                error_log('RateLimiter: cannot lock ' . $file . ' — failing open.');
                return true;
            }

            $raw = stream_get_contents($fh);
            $stamps = [];
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $t) {
                        if (is_int($t) && $t > $now - $windowSeconds && $t <= $now) {
                            $stamps[] = $t;
                        }
                    }
                }
            }

            $allowed = count($stamps) < $max;
            if ($allowed) {
                $stamps[] = $now;
            }

            // Write back the pruned list, plus the new stamp if allowed.
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, (string) json_encode(array_values($stamps)));
            fflush($fh);
            flock($fh, LOCK_UN);

            return $allowed;
        } finally {
            fclose($fh);
        }

        // Opportunistic GC is done by the caller via maybeGc().
    }

    /** Remove limiter files not touched for $maxAgeSeconds. Returns count removed. */
    public static function gc(int $maxAgeSeconds = 86400, ?int $now = null): int
    {
        $now = $now ?? time();
        $dir = self::directory();
        if (!is_dir($dir)) {
            return 0;
        }
        $removed = 0;
        $files = glob($dir . '/*.json');
        if ($files === false) {
            return 0;
        }
        foreach ($files as $f) {
            $mtime = @filemtime($f);
            if ($mtime !== false && $mtime < $now - $maxAgeSeconds) {
                if (@unlink($f)) {
                    $removed++;
                }
            }
        }
        return $removed;
    }

    /** Run gc() on roughly 1 in $oneIn calls. */
    public static function maybeGc(int $oneIn = 100): void
    {
        if ($oneIn > 0 && random_int(1, $oneIn) === 1) {
            self::gc();
        }
    }

    /** Path of the storage file for ($bucket, $key). Public for tests. */
    public static function fileFor(string $bucket, string $key): string
    {
        $safeBucket = preg_replace('/[^A-Za-z0-9_-]/', '', $bucket);
        return self::directory() . '/' . $safeBucket . '-' . hash('sha256', $bucket . "\0" . $key) . '.json';
    }
}
