<?php
// Server-side limit for "send the confirmation link again" (finding f15). The session limit in
// resend-confirmation.php can be bypassed by dropping the cookie, so this limit is kept in the
// database instead. It is keyed per email address and per client IP.
//
// Every request is recorded first and then counted. Recording before counting means two
// concurrent requests cannot both slip under the limit. Rejected attempts are recorded too, so
// someone who keeps hammering stays blocked until the rolling window clears.
//
// Keys are stored only as sha256 hashes. Rows older than KEEP seconds are pruned now and then.
declare(strict_types=1);

final class ResendLimiter
{
    public const WINDOW    = 3600;  // rolling window, seconds
    public const PER_EMAIL = 3;     // sends allowed per address per window (matches the old per-session limit)
    public const PER_IP    = 10;    // sends allowed per client IP (IPv6: per /64) per window
    public const KEEP      = 86400; // rows older than this are pruned

    public function __construct(private PDO $db) {}

    /**
     * Records this attempt and checks it.
     * Returns null when the send may go ahead.
     * Returns 'email' or 'ip' when that limit is exceeded. 'email' wins when both are exceeded.
     */
    public function hit(string $email, string $ip, ?int $now = null): ?string
    {
        $now ??= time();
        $keys = ['email' => [self::PER_EMAIL, self::hashKey('email', strtolower(trim($email)))]];
        $ipKey = self::normalizeIp($ip);
        if ($ipKey !== '') $keys['ip'] = [self::PER_IP, self::hashKey('ip', $ipKey)];

        $ins = $this->db->prepare('INSERT INTO resend_confirmation_attempts (kind, key_hash, created_at) VALUES (?, ?, ?)');
        foreach ($keys as $kind => [, $hash]) $ins->execute([$kind, $hash, $now]);

        $cnt = $this->db->prepare('SELECT COUNT(*) FROM resend_confirmation_attempts WHERE kind = ? AND key_hash = ? AND created_at > ?');
        $over = null;
        foreach ($keys as $kind => [$limit, $hash]) {
            $cnt->execute([$kind, $hash, $now - self::WINDOW]);
            $n = (int) $cnt->fetchColumn();
            $cnt->closeCursor();
            if ($n > $limit && $over === null) $over = $kind;
        }

        if (random_int(1, 50) === 1) {
            try { $this->prune($now); } catch (Throwable $e) { error_log('resend limiter prune: ' . $e->getMessage()); }
        }
        return $over;
    }

    /**
     * Deletes attempt rows older than KEEP seconds and returns how many were removed.
     * data_reason: these rows exist only for rate limiting and are no use after the window.
     * Pruning keeps the table small and stops hashed emails/IPs from being kept longer than needed.
     */
    public function prune(?int $now = null): int
    {
        $now ??= time();
        $st = $this->db->prepare('DELETE FROM resend_confirmation_attempts WHERE created_at < ?');
        $st->execute([$now - self::KEEP]);
        return $st->rowCount();
    }

    public static function hashKey(string $kind, string $value): string
    {
        return hash('sha256', 'tv-resend|' . $kind . '|' . $value);
    }

    /** IPv4 is used as-is. IPv6 is cut to its /64 so one host can't rotate addresses. Invalid or empty input gives ''. */
    public static function normalizeIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') return '';
        $bin = @inet_pton($ip);
        if ($bin === false) return '';
        if (strlen($bin) === 4) return inet_ntop($bin);
        return bin2hex(substr($bin, 0, 8)) . '::/64';
    }
}
