<?php
// app/lib/StagingGate.php — staging's answer at the site's gates (decisions/0165 §1). Staging acts like live and can't
// touch the real world: every outside effect is held, sandboxed, or allowed for a test, and what's held goes in one
// ledger (staging_held, which takes in staging_mail). On the live site every question here answers "go" at once (no
// database, no file read): nothing changes how live behaves.
//
// The gates it answers in (no new interceptor: our calls are curl, so the answer goes where the site already asks):
//   ServiceHealth::allowed() per host, AiClient's keys, Staging::allow() for the switches, Staging::holdMail() in Mailer and
//   SesMail, QuickBooksPay::testHere()/guardReal() for money, and hold() in each outside helper (TravelLegs, the stock
//   sources). A new outside call that skips the gate is refused by the zip check (R13); staging's own copy of the code is
//   scanned for them (scan(), at each release on staging and nightly) and any found shows in the safe-mode bar.
// A service with no answer here is held.
declare(strict_types=1);

final class StagingGate
{
    /**
     * Each service's answer on staging, by its key in OutsideServices::COMPANIES (the service register's "On staging"
     * column): hold (never leaves; a switch allows it for a test), sandbox (only to the sandbox's own hosts), read (free
     * public data: allowed for reading, paced inside live's limits, bulk held), allow (our own store and the test keys).
     */
    // generated from app/lib/service-defs.php (decisions/0173 §4): edit that file, then run tools/page-audit/service-defs.php --write
    public const ANSWERS = [
        'cloudflare' => 'allow',
        'aws' => 'allow',
        'anthropic' => 'hold',
        'google' => 'hold',
        'tavily' => 'hold',
        'railway' => 'hold',
        'bluehost' => 'hold',
        'quickbooks' => 'sandbox',
        'stripe' => 'sandbox',
        'square' => 'sandbox',
        'namecom' => 'hold',
        'fly' => 'hold',
        'github' => 'hold',
        'microsoft' => 'hold',
        'apple' => 'hold',
        'meta' => 'hold',
        'census' => 'read',
        'osm' => 'read',
        'overpass' => 'hold',
        'wikimedia' => 'read',
        'gemini' => 'hold',
        'supabase' => 'hold',
        'overture' => 'hold',
        'foursquare' => 'hold',
        'geofabrik' => 'hold',
        'azcc' => 'read',
        'azdhs' => 'read',
        'nmsos' => 'read',
        'azlicences' => 'hold',
        'heigit' => 'hold',
        'nws' => 'read',
        'az511' => 'read',
        'ridb' => 'read',
        'nps' => 'read',
        'usgs' => 'read',
        'cms' => 'read',
        'irs' => 'read',
        'cpsc' => 'read',
        'loc' => 'read',
        'commoncrawl' => 'hold',
        'brave' => 'hold',
        'dbip' => 'hold',
        'pixabay' => 'hold',
        'tailwind' => 'allow',
        'vimeo' => 'allow',
        'onepassword' => 'hold',
    ];
    public const WORDS = ['hold' => 'Held', 'sandbox' => 'Sandbox only', 'read' => 'Read, paced inside live\'s limits', 'allow' => 'Allowed'];
    /** Sandbox hosts (the only ones a sandbox service may reach from staging). */
    public const SANDBOX_HOSTS = ['sandbox.api.intuit.com', 'sandbox-quickbooks.api.intuit.com', 'oauth.platform.intuit.com', 'appcenter.intuit.com', 'developer.api.intuit.com',
        'connect.squareupsandbox.com', 'sandbox.web.squarecdn.com', 'pci-connect.squareupsandbox.com', 'api.stripe.com', 'js.stripe.com', 'm.stripe.network'];
    /** Free services: at most this many calls a minute from staging, per host (live shares the address; this keeps staging small beside it). */
    public const PACE_PER_MIN = 6;
    /** Live payment keys that stop payments on staging while they're in its .env. */
    /** AWS hosts that aren't the file store: Textract (paid per page), SES and SNS (send, and change our account). Held on staging. */
    public const AWS_HELD = '/^(textract|email|sns)\.[a-z0-9-]+\.amazonaws\.com$/';
    public const AWS_NAMES = ['textract' => 'Textract', 'email' => 'SES', 'sns' => 'SNS'];
    /** The start of every held call's message (Jason, 2026-11-17). */
    public const TEST_MODE = 'TEST MODE: ACTIVE (STAGE REPLIES ONLY)';
    /** The payment settings the code reads (MarketPay), each judged on staging by its value (liveKeys). */
    public const PAY_NAMES = ['STRIPE_SECRET_KEY', 'STRIPE_PUBLISHABLE_KEY', 'STRIPE_TEST_SECRET_KEY', 'STRIPE_TEST_PUBLISHABLE_KEY',
                              'SQUARE_APP_ID', 'SQUARE_APP_SECRET', 'SQUARE_SANDBOX_APP_ID', 'SQUARE_SANDBOX_APP_SECRET'];
    public const LIVE_PAY_KEYS = ['QB_CLIENT_ID', 'QB_CLIENT_SECRET', 'STRIPE_LIVE_SECRET_KEY', 'STRIPE_LIVE_PUBLISHABLE_KEY', 'SQUARE_LIVE_ACCESS_TOKEN', 'SQUARE_LIVE_APP_ID'];

    private static ?bool $here = null;
    private static array $pace = [];

    /** Is this the staging copy? Public (2026-11-19): CommsSetup, Mailboxes and SesInbound ask it before writing .env,
     *  mailbox passwords or our AWS account. While it was private their calls threw, were caught as "not staging", and
     *  the guard never held (decisions/0165). */
    public static function here(): bool
    {
        if (self::$here !== null) return self::$here;
        require_once __DIR__ . '/Staging.php';
        return self::$here = Staging::here();
    }

    /** For tests: act as staging (true), live (false), or ask again (null). */
    public static function pretend(?bool $staging): void { self::$here = $staging; self::$pace = []; }

    /** A service's answer on staging ('hold' when it has none). */
    public static function answer(string $svc): string { return self::ANSWERS[$svc] ?? 'hold'; }

    /** The service a URL or host belongs to ('' when none). */
    public static function serviceOf(string $urlOrHost): string
    {
        $host = str_contains($urlOrHost, '/') ? (string) parse_url($urlOrHost, PHP_URL_HOST) : $urlOrHost;
        require_once __DIR__ . '/OutsideServices.php';
        return OutsideServices::serviceOfHost(strtolower($host));
    }

    /**
     * The gate: null when the call may go now, else why not (and the call is recorded as held in the ledger). On live,
     * always null. $what: a line for the ledger ("travel times for Kayenta"); $ref: the record it's for ("listing:12").
     * $bulk: a bulk pull (held for free services too).
     */
    public static function hold(string $url, string $what = '', string $ref = '', bool $bulk = false): ?string
    {
        if (!self::here()) return null;
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $svc = self::serviceOf($host);
        if ($svc === '' && ($host === 'staging.trvgo.us' || $host === 'localhost' || $host === '127.0.0.1')) return null;   // staging itself
        $a = self::answer($svc);
        if ($svc === 'aws' && preg_match(self::AWS_HELD, $host)) $a = 'hold';   // Textract, SES and SNS spend or change our account: only the store is allowed
        if (self::allowedForTest($svc)) { self::record('call', $svc, $host, $what, $ref, 'allowed for a test'); return null; }
        $why = null; $how = '';
        if ($a === 'allow') return null;
        $who = $svc === 'aws' && preg_match(self::AWS_HELD, $host, $m) ? 'Amazon ' . (self::AWS_NAMES[$m[1]] ?? $m[1]) : ($svc !== '' ? self::name($svc) : ($host !== '' ? $host : 'That service'));
        if ($a === 'sandbox') { $why = in_array($host, self::SANDBOX_HOSTS, true) ? null : 'only the sandbox may be reached from staging'; $how = 'sandbox only on staging'; }
        elseif ($a === 'read') {
            if ($bulk) { $why = 'bulk pulls of free services are held on staging (live shares its address)'; $how = 'bulk pulls are held on staging'; }
            else {
                $min = (int) floor(time() / 60);
                $n = self::paceCount($host, $min);
                $why = $n >= self::PACE_PER_MIN ? 'paced: staging may ask ' . $host . ' ' . self::PACE_PER_MIN . ' times a minute' : null;
                $how = 'paced: staging may ask it ' . self::PACE_PER_MIN . ' times a minute';
            }
        } else { $why = $who . ' is held on staging'; $how = 'held on staging'; }
        if ($why === null) return null;
        self::record('call', $svc, $host, $what !== '' ? $what : (string) parse_url($url, PHP_URL_PATH), $ref, $why);
        return self::said($who . ' wasn\'t called (' . $how . ')');
    }

    /** What a person on staging is told when a call is held (Jason, 2026-11-17): "TEST MODE: ACTIVE (STAGE REPLIES ONLY) · … Nothing was sent." */
    public static function said(string $why): string { return self::TEST_MODE . ' · ' . $why . '. Nothing was sent.'; }

    /** A key from .env, for a service's call: on staging, '' unless its switch allows it for a test (then live's own key). */
    public static function key(string $name, string $svc): string
    {
        $v = trim((string) (getenv($name) ?: ''));
        if (!self::here()) return $v;
        if (!self::allowedForTest($svc)) return '';
        if ($v !== '') return $v;
        $live = self::liveEnv();
        return trim((string) ($live[$name] ?? ''));
    }

    private static function name(string $svc): string
    {
        require_once __DIR__ . '/OutsideServices.php';
        return (string) (OutsideServices::COMPANIES[$svc][0] ?? $svc);
    }

    // ---- the ledger: staging_held ---------------------------------------------------------------------------------------

    public static function ensure(PDO $pdo): void
    {
        static $done = false;
        if ($done) return;
        $pdo->exec('CREATE TABLE IF NOT EXISTS staging_held (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(12) NOT NULL, service VARCHAR(32) NOT NULL DEFAULT \'\', target VARCHAR(190) NOT NULL DEFAULT \'\',
            subject VARCHAR(255) NOT NULL DEFAULT \'\', body_text MEDIUMTEXT NULL, body_html MEDIUMTEXT NULL, via VARCHAR(12) NOT NULL DEFAULT \'\', ref VARCHAR(64) NOT NULL DEFAULT \'\', why VARCHAR(255) NOT NULL DEFAULT \'\',
            actor VARCHAR(40) NOT NULL DEFAULT \'\', tz VARCHAR(64) NOT NULL DEFAULT \'\', created_at DATETIME NOT NULL, replayed_at DATETIME NULL, replayed_to VARCHAR(190) NULL,
            KEY idx_staging_held (kind, id), KEY idx_staging_held_ref (ref)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS staging_allow (svc VARCHAR(32) NOT NULL PRIMARY KEY, until_at DATETIME NOT NULL, by_user INT NULL, at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS staging_pace (host VARCHAR(120) NOT NULL, minute INT UNSIGNED NOT NULL, n INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (host, minute)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        // email held before this build (staging_mail) moves into the ledger once
        try {
            if ((int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staging_mail'")->fetchColumn()
                && !(int) $pdo->query("SELECT COUNT(*) FROM staging_held WHERE via = 'old'")->fetchColumn())
                $pdo->exec("INSERT INTO staging_held (kind, service, target, subject, body_text, body_html, via, why, created_at) SELECT 'mail', 'email', to_addr, subject, body_text, body_html, 'old', 'email is held on staging', created_at FROM staging_mail");
        } catch (Throwable $e) { error_log('staging held (old mail): ' . $e->getMessage()); }
        $done = true;
    }

    /** One held effect in the ledger (never throws: the effect stays held even when it couldn't be noted). */
    public static function record(string $kind, string $svc, string $target, string $what, string $ref, string $why, string $text = '', string $html = '', string $via = ''): void
    {
        try {
            require_once __DIR__ . '/Database.php';
            $pdo = Database::connection();
            self::ensure($pdo);
            $pdo->prepare('INSERT INTO staging_held (kind, service, target, subject, body_text, body_html, via, ref, why, actor, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, IFNULL(@stg_actor, \'\'), NOW())')
                ->execute([$kind, mb_substr($svc, 0, 32), mb_substr($target, 0, 190), mb_substr($what, 0, 255), $text, $html, mb_substr($via, 0, 12), mb_substr($ref, 0, 64), mb_substr($why, 0, 255)]);
            if (PHP_SAPI !== 'cli' && !headers_sent()) header('X-Tv-Held: ' . rawurlencode(mb_substr($kind . ': ' . ($svc ?: $target), 0, 80)));   // the page's notice says what was held
        } catch (Throwable $e) { error_log('staging held: ' . $e->getMessage()); }
    }

    /** An email held (Mailer, SesMail): kept to read, never sent. */
    public static function mail(string $to, string $subject, string $text, string $html, string $via): void
    {
        $ref = preg_match('/\b(order|listing|entity|guide|post|journey|place)\s*#?\s*(\d+)/i', $subject, $m) ? strtolower($m[1]) . ':' . $m[2] : '';
        self::record('mail', 'email', $to, $subject, $ref, 'email is held on staging', $text, $html, $via);
    }

    /** The ledger, newest first (one kind, or all). */
    public static function held(PDO $pdo, int $limit = 50, string $kind = ''): array
    {
        self::ensure($pdo);
        $st = $pdo->prepare('SELECT id, kind, service, target, subject, ref, why, via, actor, created_at, replayed_at, replayed_to FROM staging_held' . ($kind !== '' ? ' WHERE kind = ?' : '') . ' ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)));
        $st->execute($kind !== '' ? [$kind] : []);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function heldOne(PDO $pdo, int $id): ?array
    {
        self::ensure($pdo);
        $st = $pdo->prepare('SELECT * FROM staging_held WHERE id = ?'); $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public static function counts(PDO $pdo): array
    {
        self::ensure($pdo);
        return array_map('intval', $pdo->query('SELECT kind, COUNT(*) FROM staging_held WHERE replayed_at IS NULL GROUP BY kind')->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    // ---- allowed for a test (a switch per service) ----------------------------------------------------------------------

    public static function allowedForTest(string $svc): bool
    {
        if (!self::here() || $svc === '') return false;
        if ($svc === 'anthropic' || $svc === 'gemini') { require_once __DIR__ . '/Staging.php'; if (Staging::allow('ai')) return true; }
        try {
            require_once __DIR__ . '/Database.php';
            $pdo = Database::connection(); self::ensure($pdo);
            $st = $pdo->prepare('SELECT 1 FROM staging_allow WHERE svc = ? AND until_at > NOW()'); $st->execute([$svc]);
            return (bool) $st->fetchColumn();
        } catch (Throwable) { return false; }
    }

    /** Allow one service on staging for a test (for an hour by default), or stop allowing it. Counted against live's caps (0163): it's the same money. */
    public static function allowFor(PDO $pdo, string $svc, int $minutes, int $uid): string
    {
        if (!isset(self::ANSWERS[$svc]) && $svc !== '') throw new InvalidArgumentException('That service isn\'t in the register.');
        self::ensure($pdo);
        if ($minutes <= 0) { $pdo->prepare('DELETE FROM staging_allow WHERE svc = ?')->execute([$svc]); return self::name($svc) . ' is held on staging again.'; }
        $minutes = min(24 * 60, $minutes);
        $pdo->prepare('REPLACE INTO staging_allow (svc, until_at, by_user, at) VALUES (?, NOW() + INTERVAL ' . (int) $minutes . ' MINUTE, ?, NOW())')->execute([$svc, $uid]);
        return self::name($svc) . ' is allowed on staging for ' . ($minutes >= 60 ? round($minutes / 60, 1) . ' h' : $minutes . ' min') . ', counted against live\'s caps.';
    }

    public static function allowList(PDO $pdo): array
    {
        self::ensure($pdo);
        return $pdo->query('SELECT svc, until_at, by_user FROM staging_allow WHERE until_at > NOW()')->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function paceCount(string $host, int $min): int
    {
        $k = $host . '|' . $min;
        try {
            require_once __DIR__ . '/Database.php';
            $pdo = Database::connection(); self::ensure($pdo);
            $pdo->prepare('INSERT INTO staging_pace (host, minute, n) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE n = n + 1')->execute([$host, $min]);
            $st = $pdo->prepare('SELECT n FROM staging_pace WHERE host = ? AND minute = ?'); $st->execute([$host, $min]);
            if (random_int(1, 200) === 1) $pdo->prepare('DELETE FROM staging_pace WHERE minute < ?')->execute([$min - 60]);
            return (int) $st->fetchColumn() - 1;
        } catch (Throwable) { self::$pace[$k] = (self::$pace[$k] ?? 0) + 1; return self::$pace[$k] - 1; }
    }

    // ---- payments refuse with a live key ----------------------------------------------------------------------------------

    /** On staging: the live payment keys present in its .env (payments stay off while there's one). On live: []. */
    public static function liveKeys(?array $env = null): array
    {
        if ($env === null && !self::here()) return [];
        $out = [];
        $get = fn (string $k): string => $env !== null ? trim((string) ($env[$k] ?? '')) : trim((string) (getenv($k) ?: ''));
        foreach (self::LIVE_PAY_KEYS as $k) if ($get($k) !== '') $out[] = $k;
        // every payment name the code reads (MarketPay: the plain names, and the _TEST_ / _SANDBOX_ ones a test account
        // uses), judged by its value, so a live key is caught under whatever name it was put (2026-11-18: the list above
        // named STRIPE_LIVE_*, which nothing reads)
        foreach (self::PAY_NAMES as $k) {
            $v = $get($k); if ($v === '') continue;
            $live = str_starts_with($k, 'STRIPE_') ? (bool) preg_match('/^(sk|pk|rk)_live_/', $v)
                  : (str_starts_with($k, 'SQUARE_') && str_contains($k, '_APP_') ? !str_starts_with($v, 'sandbox-') : false);
            if ($live) $out[] = $k . ' (a live key)';
        }
        $qe = strtolower($env !== null ? (string) ($env['QB_ENV'] ?? '') : (string) (getenv('QB_ENV') ?: ''));
        if ($qe === 'production') $out[] = 'QB_ENV=production';
        return $out;
    }

    // ---- the Content Security Policy (staging only) -------------------------------------------------------------------

    /**
     * What pages may load on staging: our own files and the store, the libraries and map tiles our pages use (cdnjs,
     * Tailwind, OpenStreetMap and the National Map's tiles, Wikimedia's photos), Turnstile, and the payment sandboxes'
     * own fields. Everything else is blocked and reported into Held (/api/stg-csp.php). It sits beside a card page's own
     * policy (app/ui/csp.php), which stays the stricter one there; both hold.
     */
    public static function csp(): string
    {
        $store = '';
        $b = trim((string) (getenv('S3_PUBLIC_BASE') ?: ''));
        if ($b !== '' && preg_match('#^(https://[^/]+)#', $b, $m)) $store = ' ' . $m[1];
        $s3 = ' https://*.amazonaws.com https://*.r2.cloudflarestorage.com https://*.cloudfront.net' . $store;
        $pay = ' https://js.stripe.com https://web.squarecdn.com https://sandbox.web.squarecdn.com';
        return "default-src 'self'; "
             . "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.tailwindcss.com https://challenges.cloudflare.com" . $pay . '; '
             . "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://web.squarecdn.com https://sandbox.web.squarecdn.com; "
             . "img-src 'self' data: blob: https://tile.openstreetmap.org https://*.tile.openstreetmap.org https://basemap.nationalmap.gov https://upload.wikimedia.org https://commons.wikimedia.org https://cdnjs.cloudflare.com https://*.stripe.com" . $s3 . '; '
             . "font-src 'self' data: https://*.squarecdn.com https://*.cloudfront.net; media-src 'self' blob:" . $s3 . '; '
             . "connect-src 'self' https://cdnjs.cloudflare.com https://sandbox.api.intuit.com https://api.stripe.com https://m.stripe.network https://pci-connect.squareupsandbox.com https://connect.squareupsandbox.com https://*.squarecdn.com" . $s3 . '; '
             . "worker-src 'self' blob: https://cdnjs.cloudflare.com; "
             . "frame-src 'self' https://challenges.cloudflare.com https://js.stripe.com https://checkout.stripe.com https://*.squarecdn.com https://connect.squareupsandbox.com https://www.youtube-nocookie.com https://player.vimeo.com; "
             . "object-src 'none'; base-uri 'self'; form-action 'self' https://traversence.com; frame-ancestors 'self' https://traversence.com; report-uri /api/stg-csp.php";
    }

    // ---- outside calls that skip the gate -------------------------------------------------------------------------------

    /**
     * Staging's own copy of the code, scanned for outside calls that skip the gates (curl, http file reads, sockets) in a
     * function with no gate call (StagingGate::, Staging::, ServiceHealth::allowed or ::held, Automation::) and no "@gate" note saying
     * why it needn't (live-only, a call to staging itself). Returns [[file, line, function, what]]. The safe-mode bar shows
     * the count; the zip check (R13) refuses a new one.
     */
    public static function scan(string $root): array
    {
        $out = [];
        foreach (['app/lib', 'app/scripts', 'app/admin', 'website/api', 'website/admin', 'trvgo'] as $d) {
            if (!is_dir($root . '/' . $d)) continue;
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $d, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (!str_ends_with((string) $f, '.php') || str_contains((string) $f, '/vendor/')) continue;
                foreach (self::scanText((string) file_get_contents((string) $f)) as $hit) $out[] = array_merge([substr((string) $f, strlen($root) + 1)], $hit);
            }
        }
        return $out;
    }

    /** The ungated outside calls in one file's text: [[line, function, what]]. */
    public static function scanText(string $src): array
    {
        $out = [];
        $lines = explode("\n", $src);
        $fn = ''; $fnLine = 0; $depth = 0; $fnDepth = -1; $body = []; $calls = [];
        $flush = function () use (&$out, &$fn, &$body, &$calls) {
            if (!$calls) return;
            $txt = implode("\n", $body);
            if (preg_match('/StagingGate::|Staging::(here|allow|holdMail)|ServiceHealth::(allowed|held)|Automation::(gathering|hostOff|job)|@gate\b/', $txt)) { $calls = []; return; }
            foreach ($calls as [$ln, $what]) $out[] = [$ln, $fn ?: '(top of file)', $what];
            $calls = [];
        };
        foreach ($lines as $i => $l) {
            $t = ltrim($l);
            if (preg_match('/^(?:(?:public|private|protected|static|final|abstract)\s+)*function\s+(\w+)/', $t, $m) && $fnDepth < 0) { $flush(); $fn = $m[1]; $fnLine = $i + 1; $fnDepth = $depth; $body = []; }
            if ($fnDepth >= 0 || $fn === '') $body[] = $l;
            if (!preg_match('#^\s*(//|\*|/\*)#', $l) && preg_match('/\b(curl_init|curl_multi_init|fsockopen|stream_socket_client)\s*\(|file_get_contents\(\s*[\'"]https?:/', $l, $m)) $calls[] = [$i + 1, ($m[1] ?? '') !== '' ? $m[1] : 'file_get_contents(http…)'];
            $depth += substr_count($l, '{') - substr_count($l, '}');
            if ($fnDepth >= 0 && $depth <= $fnDepth && str_contains($l, '}')) { $flush(); $fn = ''; $fnDepth = -1; $body = []; }
        }
        $flush();
        return $out;
    }

    // ---- live's .env, read from staging (only to lend a key for an allowed test) -------------------------------------

    private static function liveEnv(): array
    {
        require_once __DIR__ . '/Database.php';
        $home = dirname(Database::envPath());
        foreach ([dirname($home) . '/.env', dirname($home, 2) . '/.env'] as $f) {
            if (!is_readable($f) || realpath($f) === realpath(Database::envPath())) continue;
            $out = [];
            foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) { $l = trim($l); if ($l === '' || $l[0] === '#' || !str_contains($l, '=')) continue; [$k, $v] = explode('=', $l, 2); $out[trim($k)] = trim(trim($v), '"\''); }
            if (!isset($out['STAGING'])) return $out;
        }
        return [];
    }
}
