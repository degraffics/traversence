=== FILE: f25/files/website/api/stg-csp.php ===
<?php
// website/api/stg-csp.php — staging's Content Security Policy reports (decisions/0165 §1): what a page on staging tried to
// load and the policy blocked, kept in the held ledger (kind csp) so a third-party script that would track or report out
// shows in Held. Staging only; on live it's not there (404). Nothing is sent anywhere.
// f25: only CSP-report content types, only reports about this host's own pages, a global per-minute cap, and every
// stored value reduced to inert URL characters (no < > " ' ` or whitespace) before it reaches the ledger.
declare(strict_types=1);

require_once __DIR__ . '/../../app/lib/Staging.php';
if (!Staging::here() || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(404); exit; }
require_once __DIR__ . '/../../app/lib/CspReport.php';
if (!CspReport::typeOk((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''))) { http_response_code(415); exit; }
$j = json_decode((string) file_get_contents('php://input', false, null, 0, 20000), true);
$r = is_array($j) ? (array) ($j['csp-report'] ?? $j) : [];
$doc = (string) ($r['document-uri'] ?? $r['documentURL'] ?? '');
if (!CspReport::hostOk($doc, (string) ($_SERVER['HTTP_HOST'] ?? ''), (string) ($_SERVER['HTTP_ORIGIN'] ?? ''))) { http_response_code(204); exit; }
$blocked = CspReport::cleanUri((string) ($r['blocked-uri'] ?? $r['blockedURL'] ?? ''), 190);
$page = CspReport::cleanPath((string) parse_url($doc, PHP_URL_PATH), 200);
$directive = CspReport::cleanDirective((string) ($r['violated-directive'] ?? $r['effectiveDirective'] ?? ''), 40);
if ($blocked !== '' && CspReport::allow(CspReport::rateFile(), time())) {
    require_once __DIR__ . '/../../app/lib/StagingGate.php';
    StagingGate::record('csp', StagingGate::serviceOf($blocked), $blocked, 'Blocked on ' . $page . ' (' . $directive . ')', '', 'not allowed to load on staging (its Content Security Policy)');
}
http_response_code(204);
=== END FILE ===
=== FILE: f25/files/app/lib/CspReport.php ===
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
=== END FILE ===
=== FILE: f25/tests/csp-report-tests.php ===
<?php
// tools/staging-tests/csp-report-tests.php — f25: CspReport input checks. Run: php csp-report-tests.php
declare(strict_types=1);

$candidates = [
    __DIR__ . '/../files/app/lib/CspReport.php',  // from the release folder
    __DIR__ . '/../../app/lib/CspReport.php',     // from tools/staging-tests/ in the repo
];
$lib = null;
foreach ($candidates as $c) {
    if (is_file($c)) { $lib = $c; break; }
}
if ($lib === null) { echo "FAIL could not find CspReport.php\n"; exit(1); }
require_once $lib;

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
    if (!$ok) { $fails++; }
}
function inert(string $s): bool
{
    return preg_match('/[<>"\'`\\\\\s\x00-\x1F\x7F]/', $s) !== 1;
}

// content type
check('type csp-report', CspReport::typeOk('application/csp-report'));
check('type json with charset', CspReport::typeOk('application/json; charset=utf-8'));
check('type form rejected', !CspReport::typeOk('application/x-www-form-urlencoded'));
check('type text rejected', !CspReport::typeOk('text/plain'));
check('type empty rejected', !CspReport::typeOk(''));

// host
check('host same', CspReport::hostOk('https://stg.example.com/a', 'stg.example.com', ''));
check('host same with port', CspReport::hostOk('https://stg.example.com:8443/a', 'stg.example.com:8443', ''));
check('host other doc rejected', !CspReport::hostOk('https://evil.test/a', 'stg.example.com', ''));
check('host other origin rejected', !CspReport::hostOk('https://stg.example.com/a', 'stg.example.com', 'https://evil.test'));
check('host null origin ok', CspReport::hostOk('https://stg.example.com/a', 'stg.example.com', 'null'));
check('host missing doc rejected', !CspReport::hostOk('', 'stg.example.com', ''));
check('host missing HTTP_HOST rejected', !CspReport::hostOk('https://stg.example.com/a', '', ''));

// blocked-uri cleaning
$x = CspReport::cleanUri('"><img src=x onerror=alert(1)>', 190);
check('xss payload made inert', inert($x) && $x !== '');
check('script tag made inert', inert(CspReport::cleanUri('<script>alert(1)</script>', 190)));
check('javascript scheme labelled', CspReport::cleanUri('javascript:alert(1)', 190) === 'other-scheme(javascript)');
check('JaVaScRiPt scheme labelled', CspReport::cleanUri('JaVaScRiPt:alert(1)', 190) === 'other-scheme(javascript)');
check('data url reduced', CspReport::cleanUri('data:text/html,<b>x</b>', 190) === 'data');
check('keyword inline kept', CspReport::cleanUri('inline', 190) === 'inline');
check('https url unchanged', CspReport::cleanUri('https://cdn.example.com/a.js?x=1&y=2', 190) === 'https://cdn.example.com/a.js?x=1&y=2');
check('length capped', strlen(CspReport::cleanUri('https://a.test/' . str_repeat('"', 500), 190)) <= 190);
check('empty stays empty', CspReport::cleanUri("  \t", 190) === '');

// page + directive
check('path inert', inert(CspReport::cleanPath('/a/"><svg onload=1>', 200)));
check('path normal kept', CspReport::cleanPath('/shop/item-1', 200) === '/shop/item-1');
check('directive cleaned', CspReport::cleanDirective('script-src-elem"><b>', 40) === 'script-src-elemb');

// rate cap
$f = tempnam(sys_get_temp_dir(), 'csp-rate-test');
$t = 1_700_000_000;
$a = CspReport::allow($f, $t, 3) && CspReport::allow($f, $t + 1, 3) && CspReport::allow($f, $t + 2, 3);
check('rate allows up to limit', $a);
check('rate blocks over limit', !CspReport::allow($f, $t + 3, 3));
check('rate resets next minute', CspReport::allow($f, $t + 60, 3));
@unlink($f);
check('rate fails closed on bad path', !CspReport::allow(sys_get_temp_dir() . '/no-such-dir-f25/x/y', $t, 3));

echo $fails === 0 ? "ALL PASS\n" : "FAILURES: $fails\n";
exit($fails === 0 ? 0 : 1);
=== END FILE ===
=== FILE: f25/faq/faq-entries.md ===
no faq change (staging-only endpoint; visitors never see it, and on live it returns 404)
=== END FILE ===
=== FILE: f25/release.json ===
{
  "name": "f25-stg-csp-harden",
  "gate": "staging only; lane review + tests pass before merge",
  "what": "stg-csp.php (staging CSP report sink) now accepts only application/csp-report or application/json bodies (else 415), only reports whose document-uri host and Origin (if sent) match this host (else silent 204), allows at most 30 ledger writes per minute across all senders (file-locked counter, fails closed), and reduces blocked-uri, page path and directive to inert URL characters before StagingGate::record, so a stored value can no longer carry HTML/script into the Held page. Also blocks javascript:/other non-web schemes and trims data:/blob: payloads to their keyword.",
  "files": [
    "files/website/api/stg-csp.php",
    "files/app/lib/CspReport.php",
    "tests/csp-report-tests.php",
    "faq/faq-entries.md",
    "release.json"
  ],
  "decisions": [
    "Content types accepted: application/csp-report and application/json only. application/reports+json (Reporting API / report-to) is rejected with 415; the old parser already recorded nothing from that format, so no reports are lost.",
    "Host check rejects with a silent 204, not an error, so a probing sender learns nothing.",
    "Rate cap is global (not per IP): 30 writes/minute, counter in sys_get_temp_dir()/stg-csp-rate. If the counter file can't be opened the report is dropped (fail closed).",
    "Values are sanitised at input because the Held page file was not attached; output escaping on the Held page is still required (see verify).",
    "Non-web schemes are stored as 'other-scheme(<name>)'; data:/blob: are stored as just 'data'/'blob'."
  ],
  "tests": [
    "tests/csp-report-tests.php (copy to tools/staging-tests/; run: php csp-report-tests.php; expect ALL PASS)"
  ],
  "risks": [
    "DRAFT: nothing here has been run, tested or zip-checked; the lane session must do all of that.",
    "The host check can be forged by anyone who sets the headers and body to match. It filters casual cross-site noise; it is not authentication. The rate cap and sanitising are the real protections.",
    "Behind a proxy that rewrites Host, HTTP_HOST may not match the page host, and every real report would be dropped silently.",
    "30/minute may drop real reports during a burst, for example a page with many blocked resources.",
    "Existing Held rows written before this fix are not sanitised. No data migration is included."
  ],
  "verify": [
    "PERSON DECISION: rate cap value (30/min, global) and whether rejecting application/reports+json is acceptable. If staging's policy uses report-to, a parser for the reports+json array format should be added.",
    "PERSON DECISION: whether to clean up already-stored Held rows of kind csp. Not done here; it would need an UPDATE/DELETE with a data_reason.",
    "Held page file not shown: confirm it escapes every StagingGate ledger field with htmlspecialchars(..., ENT_QUOTES) and never puts a stored value raw into href/src. If it does, fix it in a follow-up release; this release does not cover it.",
    "app/lib/StagingGate.php not shown: confirm record() signature and that serviceOf() copes with the new 'other-scheme(...)' / 'data' / 'blob' values.",
    "app/lib/Staging.php not shown: confirm Staging::here() is unchanged and still makes the endpoint 404 on live.",
    "Confirm the live path is website/api/stg-csp.php with app/lib/ two levels up, as the require_once lines assume. The attached file name was sync-storage/api/stg-csp.php.",
    "Confirm sys_get_temp_dir() is writable on staging and not shared with another site that could exhaust the counter.",
    "Manual on staging: POST a csp-report with Content-Type text/plain → 415; with a document-uri on another host → 204 and no Held row; a real browser violation → a Held row appears; a payload of '\"><img src=x onerror=alert(1)>' → Held shows inert text; more than 30 posts in a minute → the extra ones produce no rows; live URL → 404."
  ],
  "regions": false
}
=== END FILE ===