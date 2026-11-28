<?php

declare(strict_types=1);

/**
 * Brute-force limiter for the sign-in endpoint (finding f03).
 *
 * It keeps a sliding window of FAILED sign-ins, counted two ways:
 *   - per client IP (IPv6 grouped by /64)
 *   - per account (the typed email, trimmed and lower-cased)
 *
 * Each key is stored as a small JSON list of timestamps in its own file under
 * one directory, using flock. The account key is a hash of whatever email was
 * typed, so the code never looks up the account. A 429 therefore says nothing
 * about whether the account exists.
 *
 * If storage fails, the limiter fails OPEN (it logs and allows the request)
 * so a broken disk cannot lock everyone out. See release.json decisions.
 */
final class LoginThrottle
{
    public const IP_LIMIT = 30;          // failed sign-ins per IP per window
    public const ACCOUNT_LIMIT = 10;     // failed sign-ins per account per window
    public const WINDOW_SECONDS = 900;   // 15 minutes

    private string $dir;

    public function __construct(
        ?string $dir = null,
        private int $ipLimit = self::IP_LIMIT,
        private int $accountLimit = self::ACCOUNT_LIMIT,
        private int $window = self::WINDOW_SECONDS
    ) {
        $this->dir = rtrim($dir ?? dirname(__DIR__, 2) . '/storage/login-throttle', '/');
    }

    /** Client IP from REMOTE_ADDR only. Forwarded headers are not trusted. IPv6 is grouped to its /64. */
    public static function clientIp(array $server): string
    {
        $ip = trim((string) ($server['REMOTE_ADDR'] ?? ''));
        if ($ip === '') {
            return 'unknown';
        }
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return 'unknown';
        }
        if (strlen($bin) === 16) {
            // An IPv4-mapped address (::ffff:a.b.c.d) is keyed on its IPv4 part.
            if (substr($bin, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
                return (string) inet_ntop(substr($bin, 12, 4));
            }
            return bin2hex(substr($bin, 0, 8)) . '::/64';
        }
        return $ip;
    }

    public static function normaliseEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function ipKey(string $ip): string
    {
        return 'i_' . hash('sha256', $ip);
    }

    public static function accountKey(string $email): string
    {
        return 'a_' . hash('sha256', self::normaliseEmail($email));
    }

    /** Returns the seconds the caller must wait, or 0 when a new attempt is allowed. */
    public function retryAfter(string $ip, string $email, ?int $now = null): int
    {
        $now ??= time();
        $wait = $this->waitFor(self::ipKey($ip), $this->ipLimit, $now);
        if (self::normaliseEmail($email) !== '') {
            $wait = max($wait, $this->waitFor(self::accountKey($email), $this->accountLimit, $now));
        }
        return $wait;
    }

    public function recordFailure(string $ip, string $email, ?int $now = null): void
    {
        $now ??= time();
        $this->append(self::ipKey($ip), $this->ipLimit, $now);
        if (self::normaliseEmail($email) !== '') {
            $this->append(self::accountKey($email), $this->accountLimit, $now);
        }
        if (random_int(1, 100) === 1) {
            $this->gc(time());
        }
    }

    /** After a successful sign-in, this clears the account counter. The IP counter is kept. */
    public function clearAccount(string $email): void
    {
        if (self::normaliseEmail($email) === '') {
            return;
        }
        $path = $this->path(self::accountKey($email));
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** Removes counter files that have not changed within one window. */
    public function gc(int $now): void
    {
        if (!is_dir($this->dir)) {
            return;
        }
        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $now - $this->window) {
                @unlink($file);
            }
        }
    }

    private function waitFor(string $key, int $limit, int $now): int
    {
        $times = $this->prune($this->read($key), $now);
        $count = count($times);
        if ($count < $limit) {
            return 0;
        }
        $oldestCounted = $times[$count - $limit];
        return max(1, $oldestCounted + $this->window - $now);
    }

    /** @return int[] sorted timestamps that are still inside the window */
    private function prune(array $times, int $now): array
    {
        $cut = $now - $this->window;
        $out = [];
        foreach ($times as $t) {
            if (is_int($t) && $t > $cut) {
                $out[] = $t;
            }
        }
        sort($out);
        return $out;
    }

    private function decode(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function read(string $key): array
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return [];
        }
        $fh = @fopen($path, 'r');
        if ($fh === false) {
            error_log('LoginThrottle: cannot read ' . $path);
            return [];
        }
        try {
            if (!flock($fh, LOCK_SH)) {
                return [];
            }
            $raw = stream_get_contents($fh);
            flock($fh, LOCK_UN);
            return $this->decode($raw === false ? '' : $raw);
        } finally {
            fclose($fh);
        }
    }

    private function append(string $key, int $limit, int $now): void
    {
        if (!$this->ensureDir()) {
            return;
        }
        $path = $this->path($key);
        $fh = @fopen($path, 'c+');
        if ($fh === false) {
            error_log('LoginThrottle: cannot write ' . $path);
            return;
        }
        try {
            if (!flock($fh, LOCK_EX)) {
                return;
            }
            $raw = stream_get_contents($fh);
            $times = $this->prune($this->decode($raw === false ? '' : $raw), $now);
            $times[] = $now;
            $times = array_slice($times, -max(1, $limit));
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, (string) json_encode(array_values($times)));
            fflush($fh);
            flock($fh, LOCK_UN);
        } finally {
            fclose($fh);
        }
    }

    private function ensureDir(): bool
    {
        if (is_dir($this->dir)) {
            return true;
        }
        if (@mkdir($this->dir, 0700, true) || is_dir($this->dir)) {
            return true;
        }
        error_log('LoginThrottle: cannot create ' . $this->dir . ' (limiter is failing open)');
        return false;
    }

    private function path(string $key): string
    {
        return $this->dir . '/' . $key . '.json';
    }
}
