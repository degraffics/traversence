<?php
// app/lib/SuggestionRemoveGuard.php — f05: who may turn a removed suggestion into a staff block.
// A GET on /api/search.php?removed= is only ever counted (no one's id). A block needs:
//   a POST, carrying this session's CSRF token (header X-CSRF-Token or body field csrf_token),
//   not marked cross-site by the browser (Sec-Fetch-Site), and a person who can use the Workbench ($canTeach).
// Fails closed: no session, no token, or a wrong token means nothing is recorded.
// SESSION_KEY is the safest guess at the site's token; the lane confirms it (release.json verify).

declare(strict_types=1);

final class SuggestionRemoveGuard
{
    public const SESSION_KEY = 'csrf_token';
    public const BODY_KEY = 'csrf_token';
    public const HEADER = 'HTTP_X_CSRF_TOKEN';
    public const MAX_BODY = 65536;

    /** The POST body: form fields, or a small JSON object. Anything else is empty. */
    public static function body(array $post, string $contentType, ?string $raw = null): array
    {
        if ($post) return $post;
        if (stripos($contentType, 'application/json') === false) return [];
        if ($raw === null) {
            $read = @file_get_contents('php://input', false, null, 0, self::MAX_BODY + 1);
            $raw = is_string($read) ? $read : '';
        }
        if ($raw === '' || strlen($raw) > self::MAX_BODY) return [];
        $j = json_decode($raw, true);
        return is_array($j) ? $j : [];
    }

    /** A string field, or null when missing or not a string. */
    public static function str(array $a, string $key): ?string
    {
        return isset($a[$key]) && is_string($a[$key]) ? $a[$key] : null;
    }

    /** The token this session was given, or null (no session, or none issued). */
    public static function sessionToken(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return null;
        $t = $_SESSION[self::SESSION_KEY] ?? null;
        return is_string($t) && $t !== '' ? $t : null;
    }

    /** The token the request carries: the header first, then the body field. */
    public static function supplied(array $server, array $body): ?string
    {
        $h = $server[self::HEADER] ?? null;
        if (is_string($h) && $h !== '') return $h;
        return self::str($body, self::BODY_KEY);
    }

    public static function tokenOk(?string $expected, ?string $supplied): bool
    {
        if ($expected === null || $supplied === null || $expected === '' || $supplied === '') return false;
        return hash_equals($expected, $supplied);
    }

    /** The browser says another site sent this request. */
    public static function crossSite(array $server): bool
    {
        return strtolower(trim((string) ($server['HTTP_SEC_FETCH_SITE'] ?? ''))) === 'cross-site';
    }

    public static function requestOk(array $server, array $body, ?string $expected): bool
    {
        if (self::crossSite($server)) return false;
        return self::tokenOk($expected, self::supplied($server, $body));
    }

    /** Only a checked POST from someone who can teach search records who blocked it. */
    public static function mayBlock(bool $isPost, bool $requestOk, bool $canTeach): bool
    {
        return $isPost && $requestOk && $canTeach;
    }
}
