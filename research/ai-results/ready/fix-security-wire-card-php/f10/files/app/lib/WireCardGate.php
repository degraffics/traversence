<?php
// app/lib/WireCardGate.php — ties the wire-card picture to the browser that entered the emailed code (finding f10).
// The code-entry step calls WireCardGate::mark($token) right after the code checks out. website/api/wire-card.php
// calls WireCardGate::allowed($token). Only a hash of the order token is kept, and only in the server-side session.
// Each marker lasts ClientOrders::WIRE_MINUTES (15 if that constant is missing), and ClientOrders::wireLeft()
// still applies on top.
declare(strict_types=1);

final class WireCardGate
{
    public const KEY = 'wire_card_ok';
    public const MAX = 20;          // markers kept per session; the oldest are dropped first
    public const FALLBACK_MINUTES = 15;

    /** Call at code verification, before any output. Returns false if the session could not be used. */
    public static function mark(string $token): bool
    {
        if ($token === '' || !self::open(true)) {
            return false;
        }
        // A new session id at the moment access is granted blocks session fixation. The data is kept.
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        self::markIn($_SESSION, $token, time(), self::minutes());
        return true;
    }

    /** For the wire-card endpoint. Never creates a session, and closes it (releasing the lock) once read. */
    public static function allowed(string $token): bool
    {
        if ($token === '' || !self::open(false)) {
            return false;
        }
        $ok = self::allowedIn($_SESSION, $token, time(), self::minutes());
        session_write_close();
        return $ok;
    }

    public static function minutes(): int
    {
        return defined('ClientOrders::WIRE_MINUTES')
            ? max(1, (int) constant('ClientOrders::WIRE_MINUTES'))
            : self::FALLBACK_MINUTES;
    }

    /** Pure: records the marker inside a session-like array. */
    public static function markIn(array &$s, string $token, int $now, int $minutes): void
    {
        $map = is_array($s[self::KEY] ?? null) ? $s[self::KEY] : [];
        $map = self::prune($map, $now, $minutes);
        unset($map[self::key($token)]);
        $map[self::key($token)] = $now;
        while (count($map) > self::MAX) {
            array_shift($map);
        }
        $s[self::KEY] = $map;
    }

    /** Pure: true only if this token was marked in this session within the window. */
    public static function allowedIn(array $s, string $token, int $now, int $minutes): bool
    {
        if ($token === '') {
            return false;
        }
        $map = is_array($s[self::KEY] ?? null) ? $s[self::KEY] : [];
        $at = $map[self::key($token)] ?? null;
        return is_int($at) && $at <= $now && $now < $at + $minutes * 60;
    }

    private static function prune(array $map, int $now, int $minutes): array
    {
        return array_filter($map, static fn($at) => is_int($at) && $at <= $now && $now < $at + $minutes * 60);
    }

    private static function key(string $token): string
    {
        return hash('sha256', $token);
    }

    private static function open(bool $create): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        if (session_status() === PHP_SESSION_DISABLED || headers_sent()) {
            return false;
        }
        // Visitors without a session cookie (link-only, probers) never get a session created here.
        if (!$create && (string) ($_COOKIE[session_name()] ?? '') === '') {
            return false;
        }
        ini_set('session.use_strict_mode', '1');
        session_cache_limiter('');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        return session_start();
    }
}
