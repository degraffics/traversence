<?php
// app/lib/CspReport.php — input checks for website/api/stg-csp.php (f25). Pure functions plus one small file-locked
// per-minute counter. Nothing here talks to the network or the database.
declare(strict_types=1);

final class CspReport
{
    /** Content types browsers use for report-uri CSP reports. */
    public const TYPES = ['application/csp-report', 'application/json'];
    /** Non-URL values browsers put in blocked-uri. */
    public const KEYWORDS = ['inline', 'eval', 'self', 'wasm-eval', 'trusted-types-policy', 'trusted-types-sink', 'data', 'blob', 'about'];
    /** Schemes kept as-is; anything else (javascript:, vbscript:, …) is reduced to a label. */
    public const SCHEMES = ['http', 'https', 'ws', 'wss'];
    /** Ledger writes allowed per minute, across all senders. */
    public const RATE_PER_MINUTE = 30;

    public static function typeOk(string $contentType): bool
    {
        $t = strtolower(trim(explode(';', $contentType, 2)[0]));
        return in_array($t, self::TYPES, true);
    }

    /** The report must be about a page on this host; if the browser sent an Origin, it must be this host too. */
    public static function hostOk(string $documentUri, string $httpHost, string $origin): bool
    {
        $want = self::bareHost($httpHost);
        if ($want === '') {
            return false;
        }
        $origin = trim($origin);
        if ($origin !== '' && $origin !== 'null') {
            if (self::bareHost((string) parse_url($origin, PHP_URL_HOST)) !== $want) {
                return false;
            }
        }
        $doc = (string) parse_url($documentUri, PHP_URL_HOST);
        return $doc !== '' && self::bareHost($doc) === $want;
    }

    /** blocked-uri reduced to inert characters: no < > " ' ` backslash, whitespace or control bytes; no script schemes. */
    public static function cleanUri(string $s, int $max): string
    {
        $s = (string) preg_replace('/[\x00-\x20\x7F]/', '', $s);
        if ($s === '') {
            return '';
        }
        $low = strtolower($s);
        if (in_array($low, self::KEYWORDS, true)) {
            return $low;
        }
        if (preg_match('/^([A-Za-z][A-Za-z0-9+.\-]*):/', $s, $m) === 1) {
            $scheme = strtolower($m[1]);
            if ($scheme === 'data' || $scheme === 'blob') {
                return $scheme;
            }
            if (!in_array($scheme, self::SCHEMES, true)) {
                return 'other-scheme(' . substr(self::encode($scheme), 0, 20) . ')';
            }
        }
        return substr(self::encode($s), 0, $max);
    }

    public static function cleanPath(string $s, int $max): string
    {
        $s = (string) preg_replace('/[\x00-\x20\x7F]/', '', $s);
        return substr(self::encode($s), 0, $max);
    }

    public static function cleanDirective(string $s, int $max): string
    {
        return substr((string) preg_replace('/[^a-z0-9\-]/', '', strtolower($s)), 0, $max);
    }

    public static function rateFile(): string
    {
        return rtrim(sys_get_temp_dir(), '/\\') . '/stg-csp-rate';
    }

    /** True if one more ledger write fits in this minute. Fails closed (false) if the counter file can't be used. */
    public static function allow(string $file, int $now, int $limit = self::RATE_PER_MINUTE): bool
    {
        $fh = @fopen($file, 'c+');
        if ($fh === false) {
            return false;
        }
        try {
            if (!flock($fh, LOCK_EX)) {
                return false;
            }
            $parts = explode(' ', trim((string) stream_get_contents($fh)));
            $min = (int) ($parts[0] ?? 0);
            $n = (int) ($parts[1] ?? 0);
            $cur = intdiv($now, 60);
            if ($min !== $cur) {
                $min = $cur;
                $n = 0;
            }
            if ($n >= $limit) {
                return false;
            }
            $n++;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, $min . ' ' . $n);
            fflush($fh);
            return true;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    private static function encode(string $s): string
    {
        return (string) preg_replace_callback(
            '/[^A-Za-z0-9\-._~:\/?#\[\]@!$&()*+,;=%]/',
            static fn(array $m): string => rawurlencode($m[0]),
            $s
        );
    }

    private static function bareHost(string $h): string
    {
        $h = strtolower(trim($h));
        if ($h === '') {
            return '';
        }
        if ($h[0] === '[') {
            $end = strpos($h, ']');
            return $end === false ? '' : substr($h, 0, $end + 1);
        }
        return explode(':', $h, 2)[0];
    }
}
