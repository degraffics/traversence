<?php
// LeaveThrottle — rate limits for bearer-code actions on /listing/api/leave.php (finding f20).
// Stores only sha256 hashes of IPs and codes, never the raw values. Table: listing_leave_throttle (migration f20).
// If the table is missing or the DB errors: 'resend' is refused (fail closed; it sends mail), every other action
// is allowed (fail open) and a line is written to the error log once per request.
declare(strict_types=1);

final class LeaveThrottle
{
    public const TABLE = 'listing_leave_throttle';

    // Failed codes (unknown / rejected) per IP.
    public const FAIL_MAX = 10;
    public const FAIL_WINDOW = 900;          // 15 minutes
    // 'check' reads per IP (a check returns contact details, even for a valid code).
    public const CHECK_MAX = 30;
    public const CHECK_WINDOW = 3600;        // 1 hour
    // Confirm-link resends.
    public const RESEND_GAP = 600;           // at most 1 per code per 10 minutes
    public const RESEND_DAY_MAX = 3;         // at most 3 per code per 24 hours
    public const RESEND_IP_MAX = 10;         // at most 10 per IP per hour
    public const RESEND_IP_WINDOW = 3600;
    public const KEEP_SECONDS = 172800;      // rows older than 2 days are pruned

    private PDO $pdo;
    private Closure $now;
    private ?bool $ready = null;
    private bool $logged = false;
    private int $pruneOdds;

    public function __construct(PDO $pdo, ?Closure $now = null, int $pruneOdds = 50)
    {
        $this->pdo = $pdo;
        $this->now = $now ?? static function (): int { return time(); };
        $this->pruneOdds = max(0, $pruneOdds);
    }

    public static function key(string $kind, string $value): string
    {
        return hash('sha256', $kind . '|' . $value);
    }

    public static function retryAfter(string $why): int
    {
        return $why === 'resend' ? self::RESEND_GAP : ($why === 'checks' ? self::CHECK_WINDOW : self::FAIL_WINDOW);
    }

    public function ready(): bool
    {
        if ($this->ready === null) {
            try {
                $this->pdo->query('SELECT 1 FROM ' . self::TABLE . ' LIMIT 1');
                $this->ready = true;
            } catch (Throwable) {
                $this->ready = false;
            }
        }
        return $this->ready;
    }

    /**
     * Decide whether a bearer-code action may go ahead, and record it if so.
     * Returns null when allowed, else the reason: 'tries' | 'checks' | 'resend'.
     */
    public function admit(string $do, string $code, string $ip): ?string
    {
        if ($code === '') return null;
        if (!$this->ready()) {
            $this->logOnce('LeaveThrottle: table ' . self::TABLE . ' missing; resend refused, other code actions not throttled');
            return $do === 'resend' ? 'resend' : null;
        }
        try {
            $ipKey = self::key('ip', $ip);
            if ($this->over('fail_ip', $ipKey, self::FAIL_MAX, self::FAIL_WINDOW)) return 'tries';
            if ($do === 'check') {
                if ($this->over('check_ip', $ipKey, self::CHECK_MAX, self::CHECK_WINDOW)) return 'checks';
                $this->hit('check_ip', $ipKey);
            }
            if ($do === 'resend') {
                $codeKey = self::key('code', $code);
                if ($this->over('resend_code', $codeKey, 1, self::RESEND_GAP)
                    || $this->over('resend_code', $codeKey, self::RESEND_DAY_MAX, 86400)
                    || $this->over('resend_ip', $ipKey, self::RESEND_IP_MAX, self::RESEND_IP_WINDOW)) {
                    return 'resend';
                }
                $this->hit('resend_code', $codeKey);
                $this->hit('resend_ip', $ipKey);
            }
            return null;
        } catch (Throwable $x) {
            $this->logOnce('LeaveThrottle: ' . $x->getMessage());
            return $do === 'resend' ? 'resend' : null;
        }
    }

    /** Record a failed (unknown or rejected) code from this IP. */
    public function failed(string $code, string $ip): void
    {
        if ($code === '' || !$this->ready()) return;
        try {
            $this->hit('fail_ip', self::key('ip', $ip));
        } catch (Throwable $x) {
            $this->logOnce('LeaveThrottle: ' . $x->getMessage());
        }
    }

    public function count(string $bucket, string $subject, int $window): int
    {
        $since = gmdate('Y-m-d H:i:s', ($this->now)() - $window);
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE bucket = ? AND subject = ? AND created_at > ?');
        $st->execute([$bucket, $subject, $since]);
        return (int) $st->fetchColumn();
    }

    public function over(string $bucket, string $subject, int $max, int $window): bool
    {
        return $this->count($bucket, $subject, $window) >= $max;
    }

    public function hit(string $bucket, string $subject): void
    {
        $st = $this->pdo->prepare('INSERT INTO ' . self::TABLE . ' (bucket, subject, created_at) VALUES (?, ?, ?)');
        $st->execute([$bucket, $subject, gmdate('Y-m-d H:i:s', ($this->now)())]);
        if ($this->pruneOdds > 0 && random_int(1, $this->pruneOdds) === 1) $this->prune();
    }

    /** data_reason: throttle rows are only counters (hashed IP/code); rows older than KEEP_SECONDS can no longer affect any limit. */
    public function prune(): int
    {
        $before = gmdate('Y-m-d H:i:s', ($this->now)() - self::KEEP_SECONDS);
        $st = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE created_at < ?');
        $st->execute([$before]);
        return $st->rowCount();
    }

    private function logOnce(string $msg): void
    {
        if ($this->logged) return;
        $this->logged = true;
        error_log($msg);
    }
}
