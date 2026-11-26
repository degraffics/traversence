<?php
// app/lib/Releases.php — planned, phased releases (decisions/0145 §5, §6; site-audit/phase-5-releases.md). A release is a
// zip of files at their real paths (app/…, website/…, trvgo/…, as every delivery), uploaded on System Configuration →
// Releases or dropped on the site manager on staging (decisions/0159 §5), which approve its plan (from its release.json)
// and put it on staging at once. From there:
//   planned   the zip is in, its files listed; the plan is read (its release.json, if any: title, why, proposals, switch)
//   approved  the plan is approved (title, why, switch, how it advances)
//   staged    its files are on staging.trvgo.us, its migrations ran there, and the checks ran: they passed, or say what failed
//   vetted    signed off after staging passed (since decisions/0159 §5, by Publish or Schedule itself, once someone has
//             opened it on staging: that look is the review and Publish is the sign-off)
//   scheduled waiting for its date and time
//   live      its files are on the live site, the previous copy of each kept; step 1 staff and test accounts, 2 a share of
//             members, 3 everyone (its switch: Switches). Each step advances on schedule (only when its checks are clean,
//             inside the release window) or on Publish next step. A failed check pauses it and tells whoever runs it.
//   off       Turn off: its switch off for everyone, at once (the files stay)
//   rolled_back  Roll back: the previous copy of every file put back, its new files removed. Migrations only ever add,
//             so no data is touched; what they made is recorded as left by the release (SchemaUpkeep), and proposed for
//             removal, by a later release, if it isn't sent again within 30 days (decisions/0162)
// Since decisions/0165 (and its correction, Jason 2026-11-15): Claude's builds from git, approved in chat, go live by
// themselves once their checks on staging pass (or by their own Publish after a review). Publish staging is for what's
// made on staging: its data merged into live (StagingPublish) with any zip uploaded there, in one run: their migrations,
// the data, their files, the checks (publishPart()). Releases stack on staging ("replaced by #57" where a later one changes
// a file again). The urgent fix keeps its own path: one release straight to live with a reason.
// Its migrations run as the first step of Publish, before any file goes live, each with a copy of the tables it touches
// kept first (DbCopies); one that fails stops the publish there, with the error in its record (decisions/0162). A
// migration that changes rows needs a "data_reason" in its release.json; one that drops needs the leftover named under
// "removes".
// An urgent fix can be published at once with a reason; it's checked on live straight after and recorded like any other.
// Its files and the previous copies are kept in app/storage/releases/<id>/ (never served, never synced). Once the plan is
// approved, the release's zip and its rollback zip can be downloaded, for an upload by FTP instead.
// Each change is written to git (deliveries/releases/, DocsRepo with GITHUB_DOCS_TOKEN) and shows as a Pulse case.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Releases
{
    /** The four permissions (decisions/0145 §5): Founder only until a role is given one (System Configuration → Permissions). */
    public const PERMS = [
        'releases.publish'  => 'Releases: upload, approve, stage, sign off, publish and step on',
        'releases.schedule' => 'Releases: schedule a release',
        'releases.turn_off' => 'Releases: turn a release off (its switch, for everyone)',
        'releases.rollback' => 'Releases: roll a release back (its files)',
    ];
    public const STATES = [
        'planned' => 'Planned', 'approved' => 'Plan approved', 'staged' => 'On staging', 'vetted' => 'Vetted', 'scheduled' => 'Scheduled',
        'live' => 'Live', 'off' => 'Turned off', 'rolled_back' => 'Rolled back', 'withdrawn' => 'Withdrawn',
    ];
    public const STEPS = [1 => 'Staff and test accounts', 2 => 'A share of members', 3 => 'Everyone'];
    public const DEFAULTS = ['share_pct' => 25, 'members_after_h' => 24, 'everyone_after_h' => 72, 'days' => '1,2,3,4,5', 'from_h' => 6, 'to_h' => 18, 'tz' => 'America/Phoenix'];
    private const TOPS = ['app', 'website', 'trvgo'];
    private const NEVER = ['bootstrap-super-admin.php', 'error_log'];
    private const NEVER_DIRS = ['_notes', '_SUPPORT', '_pending', '_concepts', '_gsdata_'];
    private const MAX_FILES = 400;
    private const CHECK_USER = 'tv-releases';

    private static bool $made = false;
    /** While the staging bar acts (website/api/release-bar.php): the permissions its signed token carries, from when it was given. */
    private static ?array $grant = null;

    public static function ensure(PDO $pdo): void
    {
        if (self::$made) return;
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_releases (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(160) NOT NULL DEFAULT '', why TEXT NULL, proposals VARCHAR(255) NOT NULL DEFAULT '',
            switch_key VARCHAR(64) NOT NULL DEFAULT '', advance VARCHAR(10) NOT NULL DEFAULT 'schedule',
            state VARCHAR(20) NOT NULL DEFAULT 'planned', step TINYINT NOT NULL DEFAULT 0, step_at DATETIME NULL, paused TINYINT NOT NULL DEFAULT 0,
            schedule_at DATETIME NULL, urgent TEXT NULL, zip_name VARCHAR(190) NOT NULL DEFAULT '',
            files MEDIUMTEXT NULL, checks MEDIUMTEXT NULL, checks_ok TINYINT NULL, log MEDIUMTEXT NULL, tz VARCHAR(64) NOT NULL DEFAULT '',
            plan_by INT NULL, plan_at DATETIME NULL, staged_at DATETIME NULL, vetted_by INT NULL, vetted_at DATETIME NULL, live_at DATETIME NULL,
            created_by INT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            KEY state (state)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_release_settings (k VARCHAR(40) NOT NULL PRIMARY KEY, v TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$made = true;
    }

    // ---- who may -------------------------------------------------------------------------------------------------------

    /** [allowed, why not] for one of the four permissions. */
    public static function can(string $perm): array
    {
        if (!isset(self::PERMS[$perm])) return [false, 'Not a release permission.'];
        if (self::$grant !== null) return in_array($perm, self::$grant, true) ? [true, ''] : [false, 'Your role can\'t do this (System Configuration → Permissions).'];
        if (PHP_SAPI === 'cli') return [true, ''];   // the scheduled job acts on what a person already approved
        require_once __DIR__ . '/../admin/Access.php';
        [$ok, $why] = Access::can($perm, ['min' => 'founder', 'depts' => []]);
        return [$ok, $ok ? '' : (($why ?: 'Your role can\'t do this') . '. It\'s given in System Configuration → Permissions.')];
    }

    private static function need(string $perm): void
    {
        [$ok, $why] = self::can($perm);
        if (!$ok) throw new RuntimeException($why);
    }

    // ---- settings: the rollout defaults and the release window ---------------------------------------------------------

    public static function settings(PDO $pdo): array
    {
        self::ensure($pdo);
        $out = self::DEFAULTS;
        try { foreach ($pdo->query('SELECT k, v FROM site_release_settings')->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) if (array_key_exists($k, $out)) $out[$k] = is_int(self::DEFAULTS[$k]) ? (int) $v : (string) $v; } catch (Throwable) {}
        return $out;
    }

    public static function saveSettings(PDO $pdo, array $in): string
    {
        self::need('releases.schedule');
        $s = self::settings($pdo);
        $s['share_pct'] = max(1, min(99, (int) ($in['share_pct'] ?? $s['share_pct'])));
        $s['members_after_h'] = max(1, min(720, (int) ($in['members_after_h'] ?? $s['members_after_h'])));
        $s['everyone_after_h'] = max(1, min(720, (int) ($in['everyone_after_h'] ?? $s['everyone_after_h'])));
        $days = array_values(array_unique(array_filter(array_map('intval', (array) ($in['days'] ?? explode(',', $s['days']))), fn ($d) => $d >= 0 && $d <= 6)));
        if (!$days) throw new RuntimeException('Choose at least one day releases may go live on schedule.');
        sort($days); $s['days'] = implode(',', $days);
        $s['from_h'] = max(0, min(23, (int) ($in['from_h'] ?? $s['from_h'])));
        $s['to_h'] = max(1, min(24, (int) ($in['to_h'] ?? $s['to_h'])));
        if ($s['to_h'] <= $s['from_h']) throw new RuntimeException('The window has to end after it starts.');
        $st = $pdo->prepare('REPLACE INTO site_release_settings (k, v) VALUES (?, ?)');
        foreach (['share_pct', 'members_after_h', 'everyone_after_h', 'days', 'from_h', 'to_h'] as $k) $st->execute([$k, (string) $s[$k]]);
        return 'Saved: members after ' . $s['members_after_h'] . ' h (' . $s['share_pct'] . '%), everyone after ' . $s['everyone_after_h'] . ' h more, inside the window.';
    }

    /** Is it inside the release window now (Arizona time by default)? */
    public static function inWindow(PDO $pdo, ?int $at = null): bool
    {
        $s = self::settings($pdo);
        $d = (new DateTimeImmutable('@' . ($at ?? time())))->setTimezone(new DateTimeZone($s['tz']));
        $h = (int) $d->format('G');
        return in_array((int) $d->format('w'), array_map('intval', explode(',', $s['days'])), true) && $h >= $s['from_h'] && $h < $s['to_h'];
    }

    // ---- where things are ---------------------------------------------------------------------------------------------

    private static function liveRoot(): string { return dirname(__DIR__, 2); }

    public static function dir(int $id): string { return dirname(__DIR__) . '/storage/releases/' . $id; }

    /** Staging's site root (it holds website/, app/, trvgo/ as live does), or null when staging isn't set up. */
    private static function stagingRoot(PDO $pdo): ?string
    {
        require_once __DIR__ . '/StagingSite.php';
        if (!StagingSite::isSetUp($pdo)) return null;
        $s = (array) StagingSite::get($pdo, 'setup');
        $L = StagingSite::layout((string) $s['base']);
        return is_dir($L['root']) ? $L['root'] : null;
    }

    // ---- the record ----------------------------------------------------------------------------------------------------

    public static function get(PDO $pdo, int $id): ?array
    {
        self::ensure($pdo);
        $st = $pdo->prepare('SELECT * FROM site_releases WHERE id = ?'); $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? self::decode($r) : null;
    }

    /** The newest releases first. */
    public static function all(PDO $pdo, int $limit = 30): array
    {
        self::ensure($pdo);
        return array_map([self::class, 'decode'], $pdo->query('SELECT * FROM site_releases ORDER BY id DESC LIMIT ' . max(1, $limit))->fetchAll(PDO::FETCH_ASSOC));
    }

    private static function decode(array $r): array
    {
        foreach (['files', 'checks', 'log'] as $k) $r[$k] = json_decode((string) ($r[$k] ?? ''), true) ?: [];
        foreach (['id', 'step', 'paused', 'plan_by', 'vetted_by', 'created_by'] as $k) $r[$k] = (int) ($r[$k] ?? 0);
        $r['checks_ok'] = $r['checks_ok'] === null ? null : (bool) (int) $r['checks_ok'];
        return $r;
    }

    private static function save(PDO $pdo, array $r): void
    {
        $cols = ['title', 'why', 'proposals', 'switch_key', 'advance', 'state', 'step', 'step_at', 'paused', 'schedule_at', 'urgent', 'checks_ok', 'tz',
                 'plan_by', 'plan_at', 'staged_at', 'vetted_by', 'vetted_at', 'live_at'];
        $set = []; $vals = [];
        foreach ($cols as $c) { $set[] = "$c = ?"; $vals[] = $r[$c] === null ? null : (is_bool($r[$c]) ? (int) $r[$c] : $r[$c]); }
        foreach (['files', 'checks', 'log'] as $c) { $set[] = "$c = ?"; $vals[] = json_encode($r[$c], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
        $vals[] = $r['id'];
        $pdo->prepare('UPDATE site_releases SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = ?')->execute($vals);
    }

    /** A build that came from git (a Claude build, released through the inbox), not a person's upload. */
    public static function fromGitBuild(array $r): bool
    {
        foreach ((array) ($r['log'] ?? []) as $l) if (str_starts_with((string) ($l['msg'] ?? ''), 'Came from git:')) return true;
        return false;
    }

    private static function note(array &$r, string $msg, int $by): void
    {
        $r['log'][] = ['at' => gmdate('c'), 'by' => $by, 'msg' => $msg];
        if (count($r['log']) > 200) $r['log'] = array_slice($r['log'], -200);
    }

    /** Where it stands, in a line: [text, tone ok|info|bad]. */
    public static function standing(array $r): array
    {
        $st = $r['state'];
        if ($st === 'live' && $r['paused']) return ['Paused at step ' . $r['step'] . ' of 3 (' . self::STEPS[$r['step']] . '): ' . self::lastWord($r), 'bad'];
        if ($st === 'staged' && $r['checks_ok'] && str_starts_with(self::lastWord($r), 'On staging and its checks passed; the advocates look again'))   // the before-and-after check (Piece 3)
            return ['On staging and its checks passed: the advocates are looking again (the before-and-after check). It goes on by itself once they pass it.', 'info'];
        if ($st === 'vetted' && $r['paused']) return ['Didn\'t go live: ' . self::lastWord($r), 'bad'];   // its migrations failed at publish
        return match ($st) {
            'planned' => str_starts_with(self::lastWord($r), 'Waiting for its before look') ? ['Waiting for its before look: the advocates look at staging as it is, then it goes on staging (the before-and-after check).', 'info'] : ['Planned: ' . count($r['files']) . ' files. Approve the plan to go on.', 'info'],
            'approved' => ['Plan approved: put it on staging to check it there.', 'info'],
            'staged' => $r['checks_ok'] ? ['On staging and its checks passed: look at it there, then Publish or Schedule.', 'ok'] : (self::fromGitRelease($r) ? ['On staging, and a check failed: see the checks. A build from git withdraws itself within 10 minutes, and its session sends a fixed one; nothing to do here.', 'bad'] : ['On staging, and a check failed: see the checks. Upload a fixed zip as a new release, or put it on staging again.', 'bad']),
            'vetted' => ['Vetted: ready to publish now or on a schedule.', 'ok'],
            'scheduled' => ['Scheduled for ' . self::when((string) $r['schedule_at'], (string) $r['tz']) . '.', 'info'],
            'live' => $r['step'] >= 3 ? ['Live for everyone' . ($r['switch_key'] === '' ? ' (no switch: all at once)' : '') . '.', 'ok']
                : ['Live at step ' . $r['step'] . ' of 3: ' . self::STEPS[$r['step']] . ($r['advance'] === 'schedule' ? '; the next step comes on schedule.' : '; the next step waits for Publish next step.'), 'ok'],
            'off' => ['Turned off: its switch is off for everyone. Its files are still live; Roll back takes them out.', 'bad'],
            'rolled_back' => ['Rolled back: the previous copy of every file is live again.', 'info'],
            'withdrawn' => [str_starts_with(self::lastWord($r), 'Withdrawn by itself') ? 'Withdrawn by itself: a check on staging failed (its checks, below). Nothing on the live site changed; a fixed build follows.'
                : (str_starts_with(self::lastWord($r), 'Replaced by') ? (string) strtok(self::lastWord($r), ',') . '.' : 'Withdrawn before it went live.'), 'info'],
            default => [$st, 'info'],
        };
    }

    /** The last line of a release's record (its standing's own words; the tests read it). */
    public static function lastWordFor(array $r): string { return self::lastWord($r); }

    private static function lastWord(array $r): string
    {
        $l = end($r['log']);
        return $l ? (string) $l['msg'] : '';
    }

    private static function when(string $utc, string $tz): string
    {
        if ($utc === '') return '';
        try { $z = new DateTimeZone($tz !== '' ? $tz : 'America/Phoenix'); } catch (Throwable) { $z = new DateTimeZone('America/Phoenix'); }
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($z)->format('M j, g:i A T');
    }

    // ---- 1. the upload -------------------------------------------------------------------------------------------------

    /** A path a release may carry: [ok, why not]. */
    public static function pathOk(string $p): array
    {
        if (!preg_match('#^(app|website|trvgo)/[A-Za-z0-9._\-/]+$#', $p) || str_contains($p, '..') || str_contains($p, '//')) return [false, 'not at a real path under app/, website/ or trvgo/'];
        foreach (explode('/', $p) as $seg) {
            if ($seg === '' || $seg[0] === '.') return [false, 'a dotfile (.htaccess, .env): those are delivered by hand, never in a release'];
            if (in_array($seg, self::NEVER_DIRS, true)) return [false, 'a notes folder, never on the server'];
        }
        if (in_array(basename($p), self::NEVER, true)) return [false, 'never delivered'];
        if (str_starts_with($p, 'app/storage/')) return [false, 'app/storage is the site\'s own, never delivered'];
        return [true, ''];
    }

    /** A zip uploaded on the Releases page (the release_zip upload target). Returns what the page shows. */
    public static function fromUpload(PDO $pdo, int $uid, string $zipPath, string $zipName, string $tz = ''): array
    {
        self::need('releases.publish');
        self::ensure($pdo);
        if (!class_exists('ZipArchive')) throw new RuntimeException('The server\'s PHP can\'t open zips (its zip extension is off). Ask Bluehost to turn it on, or send releases by FTP for now.');
        $z = new ZipArchive();
        if ($z->open($zipPath) !== true) throw new RuntimeException('That isn\'t a zip the server can open. Make it again and upload it.');
        $files = []; $refused = []; $plan = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $n = (string) $z->getNameIndex($i);
            if ($n === '' || str_ends_with($n, '/') || str_starts_with($n, '__MACOSX/')) continue;
            if ($n === 'release.json') { $plan = json_decode((string) $z->getFromIndex($i), true) ?: []; continue; }
            if (!in_array(strtok($n, '/'), self::TOPS, true)) { $refused[] = $n . ' (not under app/, website/ or trvgo/)'; continue; }
            [$ok, $why] = self::pathOk($n);
            if (!$ok) { $refused[] = $n . ' (' . $why . ')'; continue; }
            $files[$n] = $i;
        }
        if ($refused) { $z->close(); throw new RuntimeException('Not taken: ' . implode('; ', array_slice($refused, 0, 6)) . (count($refused) > 6 ? ' and ' . (count($refused) - 6) . ' more' : '') . '. Take them out of the zip and upload it again.'); }
        if (!$files) { $z->close(); throw new RuntimeException('The zip has no files at app/…, website/… or trvgo/….'); }
        if (count($files) > self::MAX_FILES) { $z->close(); throw new RuntimeException('That\'s ' . count($files) . ' files; a release carries at most ' . self::MAX_FILES . '. Split it into smaller releases.'); }
        $title = trim((string) ($plan['title'] ?? '')) ?: preg_replace('/\.zip$/i', '', $zipName);
        $props = $plan['proposals'] ?? '';
        $props = is_array($props) ? implode(', ', array_map('strval', $props)) : (string) $props;
        // "none", "off" or "no" in release.json mean no switch (#108, #109, 2026-11-17: stored as a switch, they stranded the
        // release at Planned); a switch the site doesn't know is checked at its approval, which says why on its card
        $sw = strtolower(trim((string) ($plan['switch'] ?? '')));
        $sw = in_array($sw, ['none', 'off', 'no', 'null', '-'], true) || !preg_match('/^[a-z0-9_]{2,64}$/', $sw) ? '' : $sw;
        $pdo->prepare('INSERT INTO site_releases (title, why, proposals, switch_key, zip_name, tz, state, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, \'planned\', ?, NOW(), NOW())')
            ->execute([mb_substr($title, 0, 160), mb_substr(trim((string) ($plan['why'] ?? '')), 0, 4000), mb_substr($props, 0, 255), $sw, mb_substr($zipName, 0, 190), $tz, $uid ?: null]);
        $id = (int) $pdo->lastInsertId();
        $dir = self::dir($id);
        if (!is_dir($dir . '/new') && !@mkdir($dir . '/new', 0750, true)) throw new RuntimeException('Couldn\'t make the release\'s folder in app/storage.');
        @file_put_contents(dirname($dir) . '/.htaccess', "Require all denied\n");
        $list = [];
        foreach ($files as $n => $i) {
            $data = $z->getFromIndex($i);
            if ($data === false) { $z->close(); throw new RuntimeException('Couldn\'t read ' . $n . ' from the zip.'); }
            $to = $dir . '/new/' . $n;
            if (!is_dir(dirname($to))) @mkdir(dirname($to), 0750, true);
            file_put_contents($to, $data);
            $list[$n] = ['sha' => hash('sha256', $data), 'size' => strlen($data), 'live_was' => self::liveSha($n)];
        }
        $z->close();
        @copy($zipPath, $dir . '/release.zip');
        $r = self::get($pdo, $id);
        $r['files'] = $list;
        $same = array_filter($list, fn ($f) => $f['live_was'] === $f['sha']);
        self::note($r, 'Uploaded ' . $zipName . ': ' . count($list) . ' files' . ($same ? ' (' . count($same) . ' already the same as live)' : '') . '.', $uid);
        self::save($pdo, $r);
        self::record($pdo, $r);
        return ['id' => $id, 'message' => 'Release #' . $id . ' is in: ' . count($list) . ' files. Read its plan and approve it to go on.'];
    }

    private static function liveSha(string $p): ?string
    {
        $f = self::liveRoot() . '/' . $p;
        return is_file($f) ? hash_file('sha256', $f) : null;
    }

    private static function migrations(array $r): array
    {
        return array_values(array_filter(array_keys($r['files']), fn ($p) => preg_match('#^app/migrations/[^/]+\.sql$#', $p)));
    }

    /** The release's release.json, read again from its kept zip ([] if there's none). */
    private static function plan(array $r): array
    {
        $f = self::dir((int) $r['id']) . '/release.zip';
        if (!class_exists('ZipArchive') || !is_file($f)) return [];
        $z = new ZipArchive();
        if ($z->open($f) !== true) return [];
        $p = json_decode((string) $z->getFromName('release.json'), true); $z->close();
        return is_array($p) ? $p : [];
    }

    /**
     * Its migrations' own checks (decisions/0145 §6, 0162): add-only, except a drop of a recorded leftover the release
     * names under "removes"; one that changes rows has a "data_reason". Returns [items, first failure or ''].
     */
    private static function migrationChecks(array $r): array
    {
        $migs = self::migrations($r);
        if (!$migs) return [[], ''];
        require_once __DIR__ . '/DbCopies.php';
        require_once __DIR__ . '/SchemaUpkeep.php';
        $dir = self::dir((int) $r['id']) . '/new/';
        $plan = self::plan($r);
        $removes = array_map(fn ($x) => strtolower(trim((string) $x)), (array) ($plan['removes'] ?? []));
        $reason = trim((string) ($plan['data_reason'] ?? ''));
        $known = SchemaUpkeep::removable();
        $mBad = []; $gone = []; $data = [];
        foreach ($migs as $p) {
            $sql = (string) file_get_contents($dir . $p);
            if (preg_match('/\b(DROP\s+(TABLE|COLUMN|INDEX|KEY|DATABASE|VIEW|TRIGGER)|RENAME\s+(TABLE|COLUMN|TO)|TRUNCATE|DELIMITER|CREATE\s+(DEFINER\S*\s+)?PROCEDURE|MODIFY\s+(COLUMN\s+)?\w+|CHANGE\s+(COLUMN\s+)?\w+)\b/i', $sql, $m)) {
                $d = SchemaUpkeep::drops($sql);
                $unasked = array_diff($d['drops'], $removes); $unknown = array_diff($d['drops'], $known);
                if (preg_match('/\b(DELIMITER|CREATE\s+(DEFINER\S*\s+)?PROCEDURE)\b/i', $sql, $mm)) $mBad[] = basename($p) . ' (' . strtoupper($mm[1]) . ')';
                elseif ($d['other'] || !$d['drops']) $mBad[] = basename($p) . ' (' . ($d['other'] ? implode(', ', $d['other']) : strtoupper(preg_replace('/\s+/', ' ', $m[1]))) . ')';
                elseif ($unasked) $mBad[] = basename($p) . ' (drops ' . implode(', ', $unasked) . ', not named under "removes" in release.json)';
                elseif ($unknown) $mBad[] = basename($p) . ' (' . implode(', ', $unknown) . ': not a recorded leftover, so not removed)';
                else $gone = array_merge($gone, $d['drops']);
            }
            if (DbCopies::changesData($sql)) $data[] = basename($p);
        }
        $items = [['Migrations only add', !$mBad, $mBad ? 'Not add-only: ' . implode(', ', $mBad) . '. A removal comes in a later release, of what a rolled-back release left (decisions/0145 §6, 0162).'
            : count($migs) . ' checked' . ($gone ? '; removes recorded leftovers named in release.json: ' . implode(', ', $gone) . ' (each table copied first)' : '')]];
        if ($data) $items[] = ['Data changes have a reason', mb_strlen($reason) >= 10, mb_strlen($reason) >= 10
            ? implode(', ', $data) . ' change rows: ' . mb_substr($reason, 0, 300) . ' (a copy of every table they touch is kept first)'
            : implode(', ', $data) . ' change rows (UPDATE, DELETE or a drop), and release.json has no "data_reason": say why in a sentence and send it again.'];
        $first = '';
        foreach ($items as $i) if ($i[1] === false) { $first = $i[0] . ': ' . $i[2]; break; }
        return [$items, $first];
    }

    /**
     * Publish's first step when a release carries migrations (decisions/0162): its migration files go into the live
     * app/migrations and the site's runner runs them now (unthrottled; each copies what it touches first). Those that
     * didn't run are taken out again, so the hourly run never runs them without the release's code. Returns
     * [ok, the paths that ran, what went wrong].
     */
    private static function runMigrationsFirst(PDO $pdo, array $r): array
    {
        require_once __DIR__ . '/DbUpdates.php';
        $dir = self::dir((int) $r['id']) . '/new/'; $root = self::liveRoot(); $placed = []; $ran = [];
        foreach (self::migrations($r) as $p) {
            $sql = (string) file_get_contents($dir . $p);
            if (DbUpdates::ranAlready($pdo, $p, $sql)) { $ran[] = $p; continue; }   // ran on an earlier try of this publish
            $live = $root . '/' . $p;
            if (!is_dir(dirname($live))) @mkdir(dirname($live), 0755, true);
            if (!@copy($dir . $p, $live)) { foreach ($placed as $q) @unlink($root . '/' . $q); return [false, $ran, 'couldn\'t put ' . basename($p) . ' in app/migrations']; }
            $placed[] = $p;
        }
        if (!$placed) return [true, $ran, ''];
        DbUpdates::retry($pdo, $placed);
        $res = DbUpdates::autoRun($pdo, false, 'publish of release #' . $r['id']);
        foreach ($placed as $p) if (in_array(basename($p), $res['ran'], true)) $ran[] = $p;
        $left = array_values(array_diff($placed, $ran));
        if (!$left) return [true, $ran, ''];
        foreach ($left as $p) @unlink($root . '/' . $p);
        $why = $res['failed'] ? implode('; ', array_map(fn ($k, $v) => $k . ' failed: ' . $v, array_keys($res['failed']), $res['failed']))
            : 'an earlier migration waiting on the server failed, so the ones after it wait (see Database updates)';
        return [false, $ran, $why];
    }

    // ---- the actions -----------------------------------------------------------------------------------------------------

    /** One action on a release, from the page or a Pulse case. Returns the line to show. */
    public static function act(PDO $pdo, int $id, string $what, array $a, int $uid): string
    {
        $r = self::get($pdo, $id);
        if (!$r) throw new RuntimeException('That release isn\'t here.');
        $tz = preg_match('#^[A-Za-z_]+(/[A-Za-z0-9_+-]+)*$#', (string) ($a['tz'] ?? '')) ? (string) $a['tz'] : '';
        switch ($what) {
            case 'approve': return self::approve($pdo, $r, $a, $uid);
            case 'stage': return self::stage($pdo, $r, $uid);
            case 'signoff': return self::signOff($pdo, $r, $uid);
            case 'publish': if (!self::fromGitRelease($r)) throw new RuntimeException('A zip uploaded on staging goes live with staging\'s own work: Publish ▾ → Preview and publish on the site manager, or System Configuration → Staging. An urgent fix goes straight to live with a reason.');
                return self::publish($pdo, $r, $uid, '');
            case 'urgent': return self::publish($pdo, $r, $uid, trim((string) ($a['reason'] ?? '')));
            case 'schedule': if ($r['state'] !== 'scheduled' && !self::fromGitRelease($r)) throw new RuntimeException('A zip uploaded on staging is scheduled with staging\'s own work: Publish ▾ → Schedule on the site manager.');
                return self::schedule($pdo, $r, (string) ($a['at'] ?? ''), $tz ?: (string) $r['tz'], $uid);
            case 'unschedule': return self::unschedule($pdo, $r, $uid);
            case 'next': return self::nextStep($pdo, $r, $uid, false);
            case 'pause': return self::pause($pdo, $r, $uid, true, 'Paused by hand.');
            case 'resume': return self::pause($pdo, $r, $uid, false, 'Carried on.');
            case 'off': return self::turnOff($pdo, $r, $uid);
            case 'back': return self::rollBack($pdo, $r, $uid);
            case 'withdraw': return self::withdraw($pdo, $r, $uid);
            case 'review': return self::reviewPart($pdo, $r, $a, $uid);
            case 'note': return self::addNote($pdo, $r, (string) ($a['text'] ?? ''), (string) ($a['page'] ?? ''), $uid, (string) ($a['name'] ?? ''));
        }
        throw new RuntimeException('That can\'t be done to a release.');
    }

    /** A note on the release (from the staging bar, while looking): what was seen, on which page. Kept in its record. */
    private static function addNote(PDO $pdo, array $r, string $text, string $page, int $uid, string $name): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') throw new RuntimeException('Write what you saw.');
        $page = preg_match('#^/[^\s]{0,300}$#', $page) ? $page : '';
        self::note($r, 'Note' . ($name !== '' ? ' from ' . $name : '') . ($page !== '' ? ' on ' . $page : '') . ': ' . mb_substr($text, 0, 1000), $uid);
        self::save($pdo, $r); self::record($pdo, $r);
        return 'Noted on release #' . $r['id'] . '.';
    }

    // ---- the staging bar: from the live admin to staging and back ---------------------------------------------------------

    /** A signed value: base64url(json) . '.' . base64url(hmac). */
    public static function sign(array $data, string $key): string
    {
        $j = json_encode($data, JSON_UNESCAPED_SLASHES);
        return rtrim(strtr(base64_encode($j), '+/', '-_'), '=') . '.' . rtrim(strtr(base64_encode(hash_hmac('sha256', $j, $key, true)), '+/', '-_'), '=');
    }

    /** The data of a signed value, if its signature is right and it hasn't run out ('exp'); else null. */
    public static function verify(string $tok, string $key): ?array
    {
        if ($key === '' || !preg_match('/^([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/', $tok, $m)) return null;
        $j = base64_decode(strtr($m[1], '-_', '+/'), true);
        $sig = base64_decode(strtr($m[2], '-_', '+/'), true);
        if ($j === false || $sig === false || !hash_equals(hash_hmac('sha256', $j, $key, true), $sig)) return null;
        $d = json_decode($j, true);
        return is_array($d) && (int) ($d['exp'] ?? 0) >= time() ? $d : null;
    }

    /**
     * Open on staging: the address that takes this staff member onto staging.trvgo.us, past its viewing password, signed in
     * as themselves ('me': staging's copy of their own account and staff login, the default), or as one of the audit's test
     * accounts ('m:<key>' a test member, 's:<key>' a test staff login), or signed out (''), with the staging bar. Good for 10 minutes, once. The bar carries what this person may do with releases.
     */
    public static function enterUrl(PDO $pdo, int $id, string $as, string $to, int $uid, string $name, int $sid = 0): string
    {
        require_once __DIR__ . '/StagingSite.php';
        $k = StagingSite::barKeys($pdo);
        if (!$k) throw new RuntimeException('Staging isn\'t set up: System Configuration → Staging → Set up staging.');
        if (StagingSite::running($pdo)) throw new RuntimeException('Staging is being refreshed: try again once it\'s done (you get an email).');
        if ($id) { $r = self::get($pdo, $id); if (!$r) throw new RuntimeException('That release isn\'t here.'); }
        if (!preg_match('/^(me|[ms]:[a-z0-9-]{2,40})?$/', $as)) $as = 'me';
        if (!preg_match('#^/[A-Za-z0-9/_.\-?=&%]{0,300}$#', $to)) $to = '/';
        $perms = [];
        foreach (array_keys(self::PERMS) as $p) if (self::can($p)[0]) $perms[] = $p;
        if ($id && in_array($r['state'], ['staged', 'vetted', 'scheduled'], true)) {   // the look a sign-off needs
            self::note($r, 'Opened on staging' . ($name !== '' ? ' by ' . $name : '') . '.', $uid);
            self::save($pdo, $r);
        }
        $tok = self::sign(['rel' => $id, 'uid' => $uid, 'sid' => $sid, 'name' => mb_substr($name, 0, 60), 'perms' => $perms, 'as' => $as, 'to' => $to, 'n' => bin2hex(random_bytes(8)), 'exp' => time() + 600], $k[0]);
        return 'https://staging.trvgo.us/stg-enter.php?t=' . rawurlencode($tok);
    }

    /** Acts for the staging bar with the permissions its token carries (website/api/release-bar.php). */
    public static function actFromBar(PDO $pdo, array $bar, int $id, string $what, array $a): string
    {
        if (!in_array($what, ['note', 'signoff', 'schedule', 'unschedule', 'publish', 'stage', 'withdraw', 'next', 'off', 'back', 'review'], true)) throw new RuntimeException('That isn\'t done from the staging bar: open the release on the live admin.');
        self::$grant = array_values(array_filter((array) ($bar['perms'] ?? []), fn ($p) => isset(self::PERMS[$p])));
        try { return self::act($pdo, $id, $what, $a + ['name' => (string) ($bar['name'] ?? '')], (int) ($bar['uid'] ?? 0)); }
        finally { self::$grant = null; }
    }

    /**
     * What the site manager shows (decisions/0159 §5): its Releases ▾ (on staging now, waiting, live and at which step),
     * the release chosen (or the newest on staging), its line, pages, checks and notes, and its Publish ▾ (Publish now,
     * Schedule, the next step; Turn off and Roll back once it's live), each only where the bar's permissions allow.
     */
    public static function barState(PDO $pdo, int $id, array $perms): array
    {
        self::ensure($pdo);
        $can = fn ($p) => in_array($p, $perms, true);
        $group = fn (string $st) => in_array($st, ['staged', 'vetted'], true) ? 'staging' : (in_array($st, ['planned', 'approved', 'scheduled'], true) ? 'waiting' : 'live');
        $list = [];
        foreach ($pdo->query("SELECT id, title, state, step, switch_key FROM site_releases WHERE state IN ('planned', 'approved', 'staged', 'vetted', 'scheduled', 'live', 'off') ORDER BY id DESC LIMIT 25")->fetchAll(PDO::FETCH_ASSOC) as $x)
            $list[] = ['id' => (int) $x['id'], 'title' => (string) $x['title'], 'state' => self::STATES[$x['state']] ?? $x['state'], 'group' => $group((string) $x['state']),
                       'step' => $x['state'] === 'live' && (string) $x['switch_key'] !== '' ? 'step ' . (int) $x['step'] . ' of 3' : ''];
        $r = $id ? self::get($pdo, $id) : null;
        if (!$r) foreach ($list as $x) if ($x['group'] === 'staging') { $r = self::get($pdo, $x['id']); break; }   // the newest on staging
        if (!$r && $list) $r = self::get($pdo, $list[0]['id']);   // else the newest of all (Jason, 2026-11-13: the bar still showed #18 after #27)
        $seen = [];
        foreach (['job', 'hand'] as $w) { $st = $pdo->prepare('SELECT v FROM site_release_settings WHERE k = ?'); $st->execute(['git_seen_' . $w]); $seen[$w] = json_decode((string) $st->fetchColumn(), true) ?: null; }
        $base = ['releases' => $list, 'can_upload' => $can('releases.publish'), 'git_seen' => $seen, 'now' => time()];
        // what's checked out (decisions/0161 §10): the files Claude sessions are writing a release for, shown in Releases ▾
        try { if (is_file(__DIR__ . '/Checkouts.php')) { require_once __DIR__ . '/Checkouts.php'; $base['checkouts'] = Checkouts::forBar(); } } catch (Throwable) {}
        if (!$r) return $base + ['release' => null];
        [$line, $tone] = self::standing($r);
        $notes = array_values(array_map(fn ($l) => ['at' => $l['at'], 'msg' => $l['msg']], array_filter($r['log'], fn ($l) => str_starts_with((string) $l['msg'], 'Note'))));
        $st = $r['state']; $pub = [];   // Publish ▾: [action, label, needs a yes]
        if ($st === 'approved' && $can('releases.publish')) $pub[] = ['stage', 'Put it on staging', 0];
        if (in_array($st, ['staged', 'vetted', 'scheduled'], true) && ($st !== 'staged' || $r['checks_ok']) && self::fromGitRelease($r)) {   // Claude's builds: their own Publish (an upload goes with Publish staging)
            if ($can('releases.publish')) $pub[] = ['publish', 'Publish now', 1];
            if ($can('releases.schedule')) $pub[] = ['schedule', $st === 'scheduled' ? 'Change the time' : 'Schedule', 0];
        }
        if ($st === 'scheduled' && $can('releases.schedule')) $pub[] = ['unschedule', 'Take off the schedule', 0];
        if ($st === 'staged' && $can('releases.publish')) $pub[] = ['stage', $r['checks_ok'] ? 'Put it on staging again' : 'Try staging again', 0];
        // Withdraw from the bar too (Jason, 2026-11-15: #65 failed its checks and could only be withdrawn on the live admin)
        if (in_array($st, ['staged', 'vetted', 'scheduled', 'approved', 'planned'], true) && $can('releases.publish')) $pub[] = ['withdraw', 'Withdraw (it won\'t go live)', 2];
        if ($st === 'live' && $r['step'] < 3 && $r['switch_key'] !== '' && $can('releases.publish')) $pub[] = ['next', $r['step'] === 1 ? 'Next step: a share of members' : 'Next step: everyone', 1];
        if ($st === 'live' && $r['switch_key'] !== '' && $can('releases.turn_off')) $pub[] = ['off', 'Turn off', 2];
        if (in_array($st, ['live', 'off'], true) && $can('releases.rollback')) $pub[] = ['back', 'Roll back', 2];
        if (in_array($st, ['planned', 'approved', 'staged', 'vetted', 'scheduled'], true) && $can('releases.publish')) $pub[] = ['withdraw', 'Withdraw', 2];
        return $base + ['release' => [
            'id' => (int) $r['id'], 'title' => (string) $r['title'], 'state' => $st, 'state_name' => self::STATES[$st] ?? $st, 'line' => $line, 'tone' => $tone,
            'group' => $group($st), 'step' => (int) $r['step'], 'switch' => (string) $r['switch_key'], 'pages' => self::pagesOf($r), 'files' => array_keys($r['files']), 'why' => (string) $r['why'],
            'checks' => $r['checks'] ? array_map(fn ($i) => ['name' => $i[0], 'ok' => $i[1], 'detail' => $i[2]], $r['checks']['items']) : [],
            'notes' => array_slice(array_reverse($notes), 0, 20), 'acts' => $pub, 'admin' => 'https://traversence.com/admin/config/releases#rel-' . (int) $r['id'],
            'stale' => in_array($st, ['staged', 'vetted'], true) && self::refreshedSince($pdo, $r),
            'review' => ($rv = self::review($pdo, (int) $r['id'])) === null ? null : array_map(fn ($k, $d) => [$k, $d[0], $d[1], $rv['parts'][$k]['ok'] ?? null, (string) ($rv['parts'][$k]['by'] ?? ''), (string) ($rv['parts'][$k]['note'] ?? '')], array_keys(self::REVIEW_PARTS), self::REVIEW_PARTS),
            'reviewed' => self::reviewed($pdo, (int) $r['id']),
            'replaced' => self::replacedIn($pdo, $r),
            'changes' => self::changes($r),
            'from_git' => self::fromGitRelease($r),
        ]];
    }

    private static function approve(PDO $pdo, array $r, array $a, int $uid): string
    {
        self::need('releases.publish');
        if (!in_array($r['state'], ['planned', 'approved'], true)) throw new RuntimeException('Its plan was approved already.');
        $title = trim((string) ($a['title'] ?? $r['title']));
        if ($title === '') throw new RuntimeException('Give the release a title.');
        $sw = trim((string) ($a['switch'] ?? $r['switch_key']));
        if ($sw !== '' && !preg_match('/^[a-z0-9_]{2,64}$/', $sw)) throw new RuntimeException('A switch\'s key is lower case letters, numbers and _.');
        if ($sw !== '' && !self::switchKnown($r, $sw)) throw new RuntimeException('There\'s no switch "' . $sw . '" on the live site or in this release\'s Switches.php. Leave it empty for a release without one (it goes to everyone at once).');
        $r['title'] = mb_substr($title, 0, 160);
        $r['why'] = mb_substr(trim((string) ($a['why'] ?? $r['why'])), 0, 4000);
        $r['proposals'] = mb_substr(trim((string) ($a['proposals'] ?? $r['proposals'])), 0, 255);
        $r['switch_key'] = $sw;
        $r['advance'] = ($a['advance'] ?? $r['advance']) === 'publish' ? 'publish' : 'schedule';
        $r['state'] = 'approved'; $r['plan_by'] = $uid; $r['plan_at'] = gmdate('Y-m-d H:i:s');
        self::note($r, 'Plan approved' . ($sw !== '' ? ' (switch ' . $sw . ')' : ' (no switch: everyone at once when published)') . ', advancing ' . ($r['advance'] === 'schedule' ? 'on schedule' : 'on Publish next step') . '.', $uid);
        self::save($pdo, $r); self::record($pdo, $r);
        return 'Plan approved. Next: put it on staging.';
    }

    /** Is the switch on the live site already, or added by this release's own Switches.php? */
    private static function switchKnown(array $r, string $key): bool
    {
        require_once __DIR__ . '/Switches.php';
        if (isset(Switches::DEFS[$key])) return true;
        $mine = self::dir((int) $r['id']) . '/new/app/lib/Switches.php';
        return is_file($mine) && str_contains((string) file_get_contents($mine), "'" . $key . "' =>");
    }

    // ---- 2. staging ----------------------------------------------------------------------------------------------------

    private static function stage(PDO $pdo, array $r, int $uid): string
    {
        self::need('releases.publish');
        if (!in_array($r['state'], ['approved', 'staged'], true)) throw new RuntimeException($r['state'] === 'planned' ? 'Approve the plan first.' : 'It\'s past staging already.');
        $root = self::stagingRoot($pdo);
        if (!$root) throw new RuntimeException('Staging isn\'t set up: System Configuration → Staging → Set up staging.');
        require_once __DIR__ . '/StagingSite.php';
        if (StagingSite::running($pdo)) throw new RuntimeException('Staging is being refreshed: put it on staging once that\'s done (you get an email).');
        @set_time_limit(300);
        $n = 0;
        foreach ($r['files'] as $p => $f) {
            $to = $root . '/' . $p;
            if (!is_dir(dirname($to)) && !@mkdir(dirname($to), 0755, true)) throw new RuntimeException('Couldn\'t make staging\'s folder for ' . $p . '.');
            if (!@copy(self::dir((int) $r['id']) . '/new/' . $p, $to)) throw new RuntimeException('Couldn\'t copy ' . $p . ' to staging.');
            $n++;
        }
        if (function_exists('opcache_reset')) @opcache_reset();
        $r['staged_at'] = gmdate('Y-m-d H:i:s');
        $c = self::checks($pdo, $r, 'staging');
        $r['checks'] = $c; $r['checks_ok'] = $c['ok']; $r['state'] = 'staged';
        self::note($r, 'Put on staging (' . $n . ' files); its checks ' . ($c['ok'] ? 'passed.' : 'found a problem: ' . $c['first_fail'] . '.'), $uid);
        self::save($pdo, $r); self::record($pdo, $r);
        return $c['ok'] ? 'On staging, and its checks passed. Look at it on staging.trvgo.us, then Publish or Schedule.' : 'On staging, but a check failed: ' . $c['first_fail'] . '.';
    }

    private static function signOff(PDO $pdo, array $r, int $uid): string
    {
        self::need('releases.publish');
        if ($r['state'] !== 'staged') throw new RuntimeException('Put it on staging first.');
        if (!$r['checks_ok']) throw new RuntimeException('Its checks on staging didn\'t pass, so it can\'t be signed off.');
        if (self::refreshedSince($pdo, $r)) throw new RuntimeException('Staging was refreshed from live since it went there, so it isn\'t on staging any more: put it on staging again.');
        if (!self::lookedAt($r)) throw new RuntimeException('Look at it on staging first (Open on staging): a release is signed off only after someone has looked at it there (Jason, 2026-11-13).');
        $r['state'] = 'vetted'; $r['vetted_by'] = $uid; $r['vetted_at'] = gmdate('Y-m-d H:i:s');
        self::note($r, 'Signed off after staging.', $uid);
        self::save($pdo, $r); self::record($pdo, $r);
        return 'Signed off: Publish and Schedule are open now.';
    }

    /**
     * Publish (or Schedule) on a release that's on staging is its sign-off (decisions/0159 §5: the separate Sign it off
     * goes): the same conditions as signOff (its checks passed, staging not refreshed since, someone looked at it there),
     * then vetted, in place, for the action to carry on.
     */
    private static function signedByPublish(PDO $pdo, array &$r, int $uid, string $by): void
    {
        if (!$r['checks_ok']) throw new RuntimeException('Its checks on staging didn\'t pass: put it on staging again, or upload a fixed zip.');
        if (self::refreshedSince($pdo, $r)) throw new RuntimeException('Staging was refreshed from live since it went there, so it isn\'t on staging any more: put it on staging again.');
        if (!self::lookedAt($r)) throw new RuntimeException('Look at it on staging first (Open on staging): a release goes live only after someone has looked at it there (Jason, 2026-11-13).');
        $r['state'] = 'vetted'; $r['vetted_by'] = $uid; $r['vetted_at'] = gmdate('Y-m-d H:i:s');
        self::note($r, 'Signed off by ' . $by . ', after a look on staging.', $uid);
    }

    /**
     * Upload, straight onto staging (decisions/0159 §5): a release just uploaded gets its plan approved as its release.json
     * gives it and goes onto staging, where its checks run; no separate Approve the plan or Put it on staging. When it
     * can't go on yet (staging refreshing, not set up), it waits approved and says why. Returns the line to show.
     */
    public static function straightOn(PDO $pdo, int $id, int $uid): string
    {
        $r = self::get($pdo, $id);
        if (!$r) throw new RuntimeException('That release isn\'t here.');
        // a build that can't go on says why on its card (and in its record for git), never sits silently (#108, #109)
        $why = function (string $msg) use ($pdo, $id, $uid): string { $x = self::get($pdo, $id); $lg = (array) ($x['log'] ?? []); if ($x && (string) ($lg ? ($lg[count($lg) - 1]['msg'] ?? '') : '') !== $msg) { self::note($x, $msg, $uid); self::save($pdo, $x); try { self::record($pdo, $x); } catch (Throwable $e) {} } return $msg; };
        try { self::approve($pdo, $r, [], $uid); }
        catch (RuntimeException $e) { return $why('Release #' . $id . ' is in, but its plan needs a look: ' . $e->getMessage() . ' Approve it on System Configuration → Releases.'); }
        try { return 'Release #' . $id . ': ' . self::stage($pdo, (array) self::get($pdo, $id), $uid); }
        catch (RuntimeException $e) { return $why('Release #' . $id . ' is in and approved, but not on staging yet: ' . $e->getMessage() . ' The site tries again every 10 minutes.'); }
    }

    /**
     * Upload from the site manager on staging: the zip the bar's upload kept in staging's own inbox (target release_zip_bar,
     * app/storage/release-inbox/ on staging, which this live server can read: the same hosting account) becomes a release
     * and goes straight onto staging, as the person the bar belongs to with the permissions it carries.
     */
    public static function fromStagingInbox(PDO $pdo, array $bar, string $inbox, string $name, string $tz): array
    {
        if (!preg_match('/^[a-f0-9]{32}\.zip$/', $inbox)) throw new RuntimeException('That upload isn\'t here: upload the zip again.');
        $root = self::stagingRoot($pdo);
        $f = $root ? $root . '/app/storage/release-inbox/' . $inbox : '';
        if ($f === '' || !is_file($f)) throw new RuntimeException('That upload isn\'t on staging any more: upload the zip again.');
        $name = preg_match('/^[A-Za-z0-9 ._()-]{1,120}\.zip$/', $name) ? $name : 'release.zip';
        self::$grant = array_values(array_filter((array) ($bar['perms'] ?? []), fn ($p) => isset(self::PERMS[$p])));
        try {
            $up = self::fromUpload($pdo, (int) ($bar['uid'] ?? 0), $f, $name, $tz);
            $msg = self::straightOn($pdo, (int) $up['id'], (int) ($bar['uid'] ?? 0));
        } finally { self::$grant = null; @unlink($f); }
        return ['id' => (int) $up['id'], 'message' => $msg];
    }

    /**
     * Builds from git (decisions/0159 §5, Phase 6): each release zip Claude merged into releases/inbox on
     * degraffics/traversence's main is fetched (its bytes checked against git's id), made a release and put straight onto
     * staging, then taken out of the inbox (the history keeps it). Once its checks there pass it goes live by itself
     * (autoPublish), unless its release.json sends it for review: then it waits for the review and its own Publish.
     * Publish staging (StagingPublish) is for what's made on staging: its data and zips uploaded there (decisions/0165
     * correction, Jason 2026-11-15). Run by releases_tick every 10 minutes, and by Check for
     * builds on the site manager. A zip taken before (its git id remembered) is only taken out again. Returns a line each.
     */
    public static function fromGit(PDO $pdo, int $uid = 0, string $by = ''): array
    {
        self::ensure($pdo);
        // one taker at a time (the 10-minute job, Check for builds, the ping from git), so a build is never made twice
        $got = 1; try { $got = (int) $pdo->query("SELECT GET_LOCK('tv_rel_git', 0)")->fetchColumn(); } catch (Throwable $x) { $got = 1; }
        if ($got !== 1) return ['Another check is taking builds from git right now: look again in a minute.'];
        try { return self::fromGitLocked($pdo, $uid, $by); }
        finally { try { $pdo->query("SELECT RELEASE_LOCK('tv_rel_git')"); } catch (Throwable $x) {} }
    }

    private static function fromGitLocked(PDO $pdo, int $uid, string $by): array
    {
        require_once __DIR__ . '/DocsRepo.php';
        $list = DocsRepo::inbox();
        // when the site last looked, and who (the 10-minute job or Check for builds): shown on the site manager, so a job
        // that isn't running shows (2026-11-13: two builds waited for Check for builds)
        try { $pdo->prepare('REPLACE INTO site_release_settings (k, v) VALUES (?, ?)')->execute([$uid ? 'git_seen_hand' : ($by === 'ping' ? 'git_seen_ping' : 'git_seen_job'), json_encode(['at' => time(), 'found' => $list === null ? -1 : count($list)])]); } catch (Throwable $e) {}
        if ($list === null) return !$uid ? [] : (DocsRepo::canSave() ? ['GitHub didn\'t answer: try again in a minute (the site also looks every 10 minutes).'] : ['No GITHUB_DOCS_TOKEN in .env: builds can\'t come from git.']);   // the job stays quiet
        $out = [];
        foreach (array_slice($list, 0, 5) as $f) {
            $gk = 'g:' . substr($f['sha'], 0, 38);   // the settings key holds 40 characters
            $st = $pdo->prepare('SELECT v FROM site_release_settings WHERE k = ?'); $st->execute([$gk]);
            $had = (int) $st->fetchColumn();
            if ($had) { DocsRepo::takeFromInbox($f['path'], $f['sha'], 'Release #' . $had . ': taken from the inbox (it was already in)'); $out[] = $f['name'] . ': already release #' . $had . '.'; continue; }
            $data = DocsRepo::blob($f['sha']);
            if ($data === null) { $out[] = $f['name'] . ': couldn\'t fetch it whole from GitHub; tried again next time.'; continue; }
            $tmp = tempnam(sys_get_temp_dir(), 'tvrel');
            file_put_contents($tmp, $data);
            $id = 0;
            try {
                $up = self::fromUpload($pdo, $uid, $tmp, $f['name'], '');
                $id = (int) $up['id'];
                try { $pdo->prepare('REPLACE INTO site_release_settings (k, v) VALUES (?, ?)')->execute([$gk, (string) $id]); } catch (Throwable $e) { error_log('release from git, remembering ' . $gk . ': ' . $e->getMessage()); }
                $r = self::get($pdo, $id);
                if ($r) { self::note($r, 'Came from git: ' . $f['path'] . ' (git ' . substr($f['sha'], 0, 10) . ').', $uid); self::save($pdo, $r); }
                // a fixed build withdraws the one it replaces before it's staged, so its files and migrations are clear
                $out = array_merge($out, self::replaceEarlier($pdo, $id, (array) (self::planOf($data)['replaces'] ?? [])));
                // the before-and-after check (Piece 3, P3-2): while it's on, the release waits for its before look first, and
                // BeforeAfter::advance() puts it on staging and decides it (checkedByAdvocates); off, it goes on as always
                $kind = (string) (self::planOf($data)['kind'] ?? '');   // rule 2's low-risk kind it says it is (ReleaseKind proves it at publish)
                if ($kind !== '') { try { $pdo->prepare('REPLACE INTO site_release_settings (k, v) VALUES (?, ?)')->execute(['kind:' . $id, mb_substr($kind, 0, 20)]); } catch (Throwable $e) {} }
                require_once __DIR__ . '/BeforeAfter.php';
                if (($ba = BeforeAfter::begin($pdo, $id, $f['name'], $f['sha'], self::planHold($data))) !== '') { $out[] = $ba; continue; }
                $out[] = self::straightOn($pdo, $id, $uid);
                // it stays on staging (decisions/0165 §2: nothing goes live by itself); "review": true also asks for the review
                // approved in chat before it was sent: it goes live by itself once its checks on staging pass, unless its
                // release.json sends it for review (decisions/0165 correction, Jason 2026-11-15)
                $out[] = self::autoPublish($pdo, $id, $uid, self::planHold($data));
                if (($w = self::selfWithdraw($pdo, (array) self::get($pdo, $id))) !== '') $out[] = $w;   // failed on staging: off by itself
            } catch (Throwable $e) {
                $out[] = $f['name'] . ': ' . ($id ? 'release #' . $id . ' made, then stopped: ' : 'not taken: ') . $e->getMessage();
            } finally {
                @unlink($tmp);
                // once it's a release, it leaves the inbox whatever happened next, so it's never made twice
                if ($id) { $t = DocsRepo::takeFromInbox($f['path'], $f['sha'], 'Release #' . $id . ': ' . $f['name'] . ' taken from the inbox onto staging'); if (empty($t['ok'])) $out[] = 'Release #' . $id . ' stays in the inbox for now (' . ($t['error'] ?? '') . '); it won\'t be made again.'; }
            }
        }
        return array_values(array_filter($out, fn ($l) => $l !== ''));
    }

    /** Does a release zip's release.json send it for review ("review": true; "hold" as before)? */
    private static function planHold(string $zipData): bool
    {
        if (!class_exists('ZipArchive')) return true;   // can't read it: wait, to be safe
        $tmp = tempnam(sys_get_temp_dir(), 'tvhold'); file_put_contents($tmp, $zipData);
        $z = new ZipArchive(); $hold = false;
        if ($z->open($tmp) === true) { $p = json_decode((string) $z->getFromName('release.json'), true); $hold = is_array($p) && (!empty($p['review']) || !empty($p['hold'])); $z->close(); }
        @unlink($tmp);
        return $hold;
    }

    /**
     * A build from git goes live by itself once its checks on staging pass (Jason, 2026-11-13: "I would have already
     * approved it in claude before it got pushed"; kept by decisions/0165's correction, 2026-11-15: "The publish feature
     * is for me to push the changes I make from within staging"). Held ones, failed checks and a
     * refresh in the way leave it on staging, saying why. Returns the line to show.
     */
    private static function autoPublish(PDO $pdo, int $id, int $uid, bool $hold): string
    {
        $r = self::get($pdo, $id);
        if (!$r) return '';
        if ($hold) return self::sendForReview($pdo, $r, $uid);
        if ($r['state'] === 'staged' && $r['checks_ok'] && ($why = self::rule2($pdo, $r)) !== '') {   // 0179: rule 1 and rule 2, when the site keeps them
            $r = (array) self::get($pdo, $id); self::note($r, 'Waits for the daily batch: ' . $why, $uid); self::save($pdo, $r);
            return self::sendForReview($pdo, (array) self::get($pdo, $id), $uid);
        }
        if ($r['state'] !== 'staged' || !$r['checks_ok']) return 'Release #' . $id . ' stays on staging: ' . (self::standing($r)[0]) . '';
        $r['state'] = 'vetted'; $r['vetted_by'] = $uid ?: null; $r['vetted_at'] = gmdate('Y-m-d H:i:s');
        self::note($r, 'Approved in chat before it was sent through git, and its checks on staging passed: publishing by itself.', $uid);
        self::save($pdo, $r);
        try { return 'Release #' . $id . ': ' . self::publish($pdo, $r, $uid, ''); }
        catch (RuntimeException $e) { return 'Release #' . $id . ' passed on staging but didn\'t go live: ' . $e->getMessage() . ' Publish it from the site manager.'; }
    }

    // ---- review (Jason, 2026-11-13): a build sent for review waits on staging until UI, UX and Functional are approved ---

    public const REVIEW_PARTS = ['ui' => ['UI', 'How it looks: layout, type, colours, icons, the phone layout'],
        'ux' => ['UX', 'How it works for a person: the steps, the words, where things are'],
        'fn' => ['Functional', 'It does what it should: buttons, saving, results, nothing broken']];

    /** The review of a release: null when it wasn't sent for review; else ['parts' => [key => ['ok' => bool|null, 'by', 'at', 'note']]]. */
    public static function review(PDO $pdo, int $id): ?array
    {
        self::ensure($pdo);
        $st = $pdo->prepare('SELECT v FROM site_release_settings WHERE k = ?'); $st->execute(['rv:' . $id]);
        $v = $st->fetchColumn();
        return $v === false ? null : (json_decode((string) $v, true) ?: ['parts' => []]);
    }

    private static function saveReview(PDO $pdo, int $id, array $rv): void
    {
        $pdo->prepare('REPLACE INTO site_release_settings (k, v) VALUES (?, ?)')->execute(['rv:' . $id, json_encode($rv, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    /**
     * What a release changes, as short items: [[what, where]]. From release.json's "changes" ([{"what", "where"}] or
     * strings); when it has none, its "why" split into its numbered parts (Jason, 2026-11-15: the review email was one
     * unreadable paragraph on a phone).
     */
    public static function changes(array $r): array
    {
        $out = [];
        foreach ((array) (self::plan($r)['changes'] ?? []) as $c) {
            if (is_string($c) && trim($c) !== '') $out[] = [mb_substr(trim($c), 0, 300), ''];
            elseif (is_array($c) && trim((string) ($c['what'] ?? '')) !== '') $out[] = [mb_substr(trim((string) $c['what']), 0, 300), mb_substr(trim((string) ($c['where'] ?? '')), 0, 200)];
        }
        if ($out) return array_slice($out, 0, 30);
        $why = trim((string) $r['why']);
        if ($why === '') return [];
        $parts = preg_split('/\s*(?:\n\s*|(?<=[.!?])\s+)(?=\(?\d{1,2}[.)]\s)/', $why) ?: [$why];
        foreach ($parts as $p) { $p = trim((string) preg_replace('/^\(?\d{1,2}[.)]\s*/', '', trim($p))); if ($p !== '') $out[] = [mb_strlen($p) > 300 ? mb_substr($p, 0, 297) . '…' : $p, '']; }
        return array_slice($out, 0, 30);
    }

    /** Are all three parts approved (or no review asked)? */
    public static function reviewed(PDO $pdo, int $id): bool
    {
        $rv = self::review($pdo, $id);
        if ($rv === null) return true;
        foreach (array_keys(self::REVIEW_PARTS) as $k) if (($rv['parts'][$k]['ok'] ?? null) !== true) return false;
        return true;
    }

    /**
     * Sent for review: it waits on staging, and the Founder is told (an email, a system one, and the Pulse case) with the
     * way onto staging. There, the site manager's Review takes UI, UX and Functional, each Approve or Needs changes with a
     * note; Publish opens when all three are approved.
     */
    private static function sendForReview(PDO $pdo, array $r, int $uid): string
    {
        $id = (int) $r['id'];
        self::saveReview($pdo, $id, ['parts' => [], 'asked' => gmdate('c')]);
        self::note($r, 'Sent for review on staging: UI, UX and Functional, then Publish.', $uid);
        self::save($pdo, $r); self::record($pdo, $r);
        $link = 'https://traversence.com/admin/api/config/releases?action=enter&id=' . $id . '&as=me&to=%2F';
        try {
            $to = [];
            foreach ($pdo->query("SELECT s.email, u.email AS uemail FROM " . Database::t('staff_accounts') . " s LEFT JOIN users u ON u.id = s.user_id WHERE s.role = 'owner' AND s.status = 'active'")->fetchAll(PDO::FETCH_ASSOC) as $o)
                foreach ([(string) $o['uemail'], (string) $o['email']] as $e) if (filter_var($e, FILTER_VALIDATE_EMAIL) && !str_ends_with($e, '.invalid')) { $to[strtolower($e)] = $e; break; }
            if ($to) {
                require_once __DIR__ . '/Mailer.php';
                require_once __DIR__ . '/DailyReport.php';
                $subject = 'Review release #' . $id . ' on staging: ' . $r['title'];
                $changes = self::changes($r);
                $steps = ['Use it on staging (the button signs you in as you).', 'In the site manager at the top: Review ▸ UI, UX and Functional, each Approve or Needs changes with a note.', 'Then Publish ▾ → Publish now (it opens once all three are approved).'];
                $h = fn ($x) => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');
                $li = '';
                foreach ($changes as [$w, $where]) $li .= '<div style="padding:8px 0;border-top:1px solid #EEE3CA;font-size:14px">' . $h($w) . ($where !== '' ? '<div style="font-size:12px;color:#8A7355;margin-top:2px">' . $h($where) . '</div>' : '') . '</div>';
                $ol = ''; foreach ($steps as $i => $st) $ol .= '<div style="padding:6px 0;border-top:1px solid #EEE3CA;font-size:14px"><b style="color:#92400E">' . ($i + 1) . '.</b> ' . $h($st) . '</div>';
                $body = '<p style="margin:0 6px 10px;font-size:15px">Release #' . $id . ' is on staging, its checks passed, and it waits for your review.</p>'
                      . '<p style="margin:0 6px 12px">' . DailyReport::button('Open it on staging', $link) . '</p>'
                      . ($li !== '' ? DailyReport::box('What changes', (string) count($changes), $li) : '')
                      . DailyReport::box('What to do', '', $ol);
                $foot = 'The button stopped working? Open System Configuration → Releases → Open on staging (<a href="https://traversence.com/admin/config/releases" style="color:#92400E">Releases</a>), or write to <a href="mailto:support@traversence.com" style="color:#92400E">support@traversence.com</a>. This email changes nothing by itself.';
                $html = DailyReport::frame($subject, 'Release #' . $id . ' · waiting for your review', (string) $r['title'], $body, $foot);
                $text = 'Release #' . $id . ' is on staging, its checks passed, and it waits for your review.' . "\n\nOpen it on staging: " . $link
                      . ($changes ? "\n\nWhat changes (" . count($changes) . "):\n" . implode("\n", array_map(fn ($c) => '• ' . $c[0] . ($c[1] !== '' ? ' (' . $c[1] . ')' : ''), $changes)) : '')
                      . "\n\nWhat to do:\n" . implode("\n", array_map(fn ($i, $st) => ($i + 1) . '. ' . $st, array_keys($steps), $steps))
                      . "\n\nThe link stopped working? Open System Configuration → Releases → Open on staging, or write to support@traversence.com.";
                foreach ($to as $e) Mailer::send($e, $subject, $text, null, '', $html);
            }
        } catch (Throwable $e) { error_log('release review email: ' . $e->getMessage()); }
        return 'Release #' . $id . ' is on staging, waiting for your review (UI, UX, Functional).';
    }

    /** One part of a review, from the site manager: approve, or needs changes with a note (kept with the release, in git). */
    private static function reviewPart(PDO $pdo, array $r, array $a, int $uid): string
    {
        self::need('releases.publish');
        $id = (int) $r['id'];
        $rv = self::review($pdo, $id);
        if ($rv === null) throw new RuntimeException('This release wasn\'t sent for review: Publish it when you\'re ready.');
        if (!in_array($r['state'], ['staged', 'vetted'], true)) throw new RuntimeException('It\'s past review: ' . self::standing($r)[0]);
        $k = (string) ($a['part'] ?? '');
        if (!isset(self::REVIEW_PARTS[$k])) throw new RuntimeException('Choose UI, UX or Functional.');
        $ok = !empty($a['ok']);
        $note = trim(mb_substr((string) ($a['text'] ?? ''), 0, 2000));
        if (!$ok && $note === '') throw new RuntimeException('Say what needs changing, so it can be fixed.');
        $who = trim((string) ($a['name'] ?? ''));
        if ($who === '' && $uid > 0) {   // given on the Releases page: the signed-in staff member's name
            try { $q = $pdo->prepare('SELECT COALESCE(NULLIF(s.name, \'\'), u.display_name) FROM ' . Database::t('staff_accounts') . ' s JOIN users u ON u.id = s.user_id WHERE s.user_id = ? LIMIT 1'); $q->execute([$uid]); $who = trim((string) $q->fetchColumn()); } catch (Throwable $x) { $who = ''; }
        }
        $rv['parts'][$k] = ['ok' => $ok, 'by' => $who, 'at' => gmdate('c'), 'note' => $note, 'page' => mb_substr((string) ($a['page'] ?? ''), 0, 200)];
        self::saveReview($pdo, $id, $rv);
        $name = self::REVIEW_PARTS[$k][0];
        self::note($r, 'Review ' . $name . ': ' . ($ok ? 'approved' : 'needs changes') . ($who !== '' ? ' (' . $who . ')' : '') . ($note !== '' ? ': ' . $note : '') . ($rv['parts'][$k]['page'] !== '' ? ' [on ' . $rv['parts'][$k]['page'] . ']' : '') . '.', $uid);
        self::save($pdo, $r); self::record($pdo, $r);
        if (!$ok) return $name . ': needs changes, noted with the release. The build gets fixed and sent again.';
        return self::reviewed($pdo, $id) ? 'All three approved: Publish is open.' : $name . ' approved.';
    }

    /** Runs $f as the staging bar's person, with the permissions its signed token carries (StagingApi from the site manager). */
    public static function asBar(array $bar, callable $f)
    {
        self::$grant = array_values(array_filter((array) ($bar['perms'] ?? []), fn ($p) => isset(self::PERMS[$p])));
        try { return $f(); } finally { self::$grant = null; }
    }

    /**
     * Check for builds on the live Releases page (Jason, 2026-11-15: a fix waited in git with no way to fetch it from the
     * live admin): takes Claude's builds from git now. A build not sent for review then goes live by itself once its
     * checks on staging pass, as from the 10-minute job.
     */
    public static function pullNow(PDO $pdo, int $uid): string
    {
        self::need('releases.publish');
        $l = self::fromGit($pdo, max(1, $uid));
        return $l ? implode(' ', $l) : 'Nothing waiting in git: every build there is already a release.';
    }

    /** When the site last looked in git for builds, by whom, and what it found: [[who, unix time, found]], newest first. */
    public static function lastLooked(PDO $pdo): array
    {
        $out = [];
        try {
            self::ensure($pdo);
            $names = ['git_seen_job' => 'the 10-minute job', 'git_seen_ping' => 'the ping from git', 'git_seen_hand' => 'Check for builds'];
            $st = $pdo->query("SELECT k, v FROM site_release_settings WHERE k IN ('git_seen_job', 'git_seen_ping', 'git_seen_hand')");
            foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) { $j = json_decode((string) $v, true) ?: []; $out[] = [$names[$k], (int) ($j['at'] ?? 0), (int) ($j['found'] ?? 0)]; }
        } catch (Throwable $x) {}
        usort($out, fn ($a, $b) => $b[1] <=> $a[1]);
        return $out;
    }

    /** Check for builds, from the site manager: fromGit now, as the bar's person with its permissions. */
    public static function pullFromBar(PDO $pdo, array $bar): string
    {
        self::$grant = array_values(array_filter((array) ($bar['perms'] ?? []), fn ($p) => isset(self::PERMS[$p])));
        try { self::need('releases.publish'); $l = self::fromGit($pdo, (int) ($bar['uid'] ?? 0)); }
        finally { self::$grant = null; }
        return $l ? implode(' ', $l) : 'No new builds in git: nothing is waiting.';
    }

    /** The staging bar showed this release to someone on staging: that's a look, for its sign-off (once each time it's staged). */
    public static function sawOnStaging(PDO $pdo, int $id, int $uid, string $name): void
    {
        $r = self::get($pdo, $id);
        if (!$r || !in_array($r['state'], ['staged', 'vetted', 'scheduled'], true) || self::lookedAt($r)) return;
        self::note($r, 'Opened on staging' . ($name !== '' ? ' by ' . $name : '') . ' (the staging bar).', $uid);
        self::save($pdo, $r);
    }

    /** Has someone opened it on staging since it was last put there (Open on staging, or the staging bar on it)? */
    private static function lookedAt(array $r): bool
    {
        $since = strtotime((string) $r['staged_at'] . ' UTC');
        foreach ($r['log'] as $l) if (str_starts_with((string) $l['msg'], 'Opened on staging') && strtotime((string) $l['at']) >= $since) return true;
        return false;
    }

    /** Was staging refreshed from live after this release went there (wiping it)? */
    private static function refreshedSince(PDO $pdo, array $r): bool
    {
        require_once __DIR__ . '/StagingSite.php';
        $last = StagingSite::get($pdo, 'last');
        return $last && !empty($last['at']) && !empty($r['staged_at']) && strtotime((string) $last['at']) > strtotime((string) $r['staged_at'] . ' UTC');
    }

    // ---- releases stack on staging (decisions/0165 §2) -------------------------------------------------------------

    /**
     * The releases on staging (its checks ran there), oldest first. $forPublish: only those Publish staging takes, the zips
     * uploaded on staging (the site manager's Upload, System Configuration → Releases); Claude's builds from git go live
     * on their own, or by their own Publish after a review (decisions/0165 correction, Jason 2026-11-15).
     */
    public static function onStaging(PDO $pdo, bool $forPublish = true): array
    {
        self::ensure($pdo);
        $out = [];
        foreach ($pdo->query("SELECT id FROM site_releases WHERE state IN ('staged', 'vetted') ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $id) if ($r = self::get($pdo, (int) $id)) if (!$forPublish || !self::fromGitRelease($r)) $out[] = $r;
        return $out;
    }

    /** Did it come from git (one of Claude's builds, approved in chat)? */
    public static function fromGitRelease(array $r): bool
    {
        foreach ((array) $r['log'] as $l) if (str_starts_with((string) ($l['msg'] ?? ''), 'Came from git')) return true;
        return false;
    }

    /** A release's files a later release on staging changes again: [path => the later release's id] ("replaced by #57"). */
    public static function replacedIn(PDO $pdo, array $r): array
    {
        $out = [];
        if (!in_array($r['state'], ['staged', 'vetted'], true)) return $out;
        foreach (self::onStaging($pdo, false) as $x) if ((int) $x['id'] > (int) $r['id']) foreach (array_keys($x['files']) as $p) if (isset($r['files'][$p])) $out[$p] = (int) $x['id'];
        return $out;
    }

    /**
     * One part of Publish for a release on staging, run by StagingPublish in order across every release on staging:
     * 'migrations' (its own, first; 0162), 'files' (each file's live copy kept, then renamed into place; it's live), or
     * 'checks' (on live, then step 1 of its switch; returns '' when they pass, else the failure). Throws on a failure that
     * stops the publish. Its review (when asked) and its checks on staging must have passed; the look on staging is the
     * whole of staging's (Publish is the sign-off).
     */
    public static function publishPart(PDO $pdo, int $id, int $uid, string $part, string $pub): string
    {
        self::need('releases.publish');
        $r = self::get($pdo, $id);
        if (!$r) throw new RuntimeException('Release #' . $id . ' isn\'t here.');
        if ($part === 'migrations') {
            if (!in_array($r['state'], ['staged', 'vetted'], true)) return '';
            if (!$r['checks_ok']) throw new RuntimeException('Release #' . $id . '\'s checks on staging didn\'t pass.');
            if (!self::reviewed($pdo, $id)) throw new RuntimeException('Release #' . $id . ' waits for its review (UI, UX and Functional).');
            if (!self::migrations($r)) return '';
            [, $mFail] = self::migrationChecks($r);
            if ($mFail !== '') throw new RuntimeException('Release #' . $id . ': ' . $mFail);
            [$ok, $ran, $why] = self::runMigrationsFirst($pdo, $r);
            self::note($r, 'Publish of staging (' . $pub . '): its migrations ' . ($ok ? 'ran first' : 'stopped: ' . $why) . ($ran ? ' (' . implode(', ', array_map('basename', $ran)) . ')' : '') . '.', $uid);
            self::save($pdo, $r); self::record($pdo, $r);
            if (!$ok) throw new RuntimeException('Release #' . $id . '\'s migrations stopped: ' . $why . '. No file went live.');
            return '';
        }
        if ($part === 'files') {
            if ($r['state'] === 'live') return '';   // carried on after a stop: already done
            if (!in_array($r['state'], ['staged', 'vetted'], true)) return '';
            $r['state'] = 'vetted'; $r['vetted_by'] = $uid ?: null; $r['vetted_at'] = gmdate('Y-m-d H:i:s');
            $also = [];   // what the releases before it in this publish wrote
            foreach ($pdo->query("SELECT id FROM site_releases WHERE state = 'live' AND id < " . (int) $id . " ORDER BY id DESC LIMIT 40")->fetchAll(PDO::FETCH_COLUMN) as $o) {
                $x = self::get($pdo, (int) $o);
                if (!$x || !array_filter($x['log'], fn ($l) => str_contains((string) $l['msg'], '(' . $pub . ')'))) continue;
                foreach ($x['files'] as $fp => $ff) $also[$fp][] = (string) $ff['sha'];
            }
            self::goLive($pdo, $r, $uid, '', 'Published with all of staging (' . $pub . ')', [], $also);
            self::save($pdo, $r); self::record($pdo, $r);
            return '';
        }
        if ($part === 'checks') {
            if ($r['state'] !== 'live') return '';
            $c = self::checks($pdo, $r, 'live');
            $r['checks'] = $c; $r['checks_ok'] = $c['ok'];
            if (!$c['ok']) { $r['paused'] = 1; self::note($r, 'A check on live failed: ' . $c['first_fail'] . '. Paused: Turn off or Roll back is your call.', 0); self::alert($pdo, $r, $c['first_fail']); }
            self::save($pdo, $r); self::record($pdo, $r);
            return $c['ok'] ? '' : 'Release #' . $id . ': ' . $c['first_fail'];
        }
        throw new RuntimeException('Not a part of Publish.');
    }

    // ---- the checks ----------------------------------------------------------------------------------------------------

    /**
     * The release's checks, on staging (before) or live (after each step): every PHP file parses; migrations only add; on
     * staging its migrations run there; its pages and the home page answer without a server error; no new fatal errors
     * in the site's error logs while they were asked. ['ok', 'where', 'at', 'items' => [[name, ok true|false|null, detail]], 'first_fail'].
     */
    public static function checks(PDO $pdo, array $r, string $where): array
    {
        $items = [];
        $dir = self::dir((int) $r['id']) . '/new/';
        // PHP that doesn't parse
        $bad = [];
        foreach (array_keys($r['files']) as $p) {
            if (!str_ends_with($p, '.php')) continue;
            try { token_get_all((string) file_get_contents($dir . $p), TOKEN_PARSE); } catch (Throwable $e) { $bad[] = $p . ' line ' . $e->getLine() . ': ' . $e->getMessage(); }
        }
        $php = count(array_filter(array_keys($r['files']), fn ($p) => str_ends_with($p, '.php')));
        $items[] = ['PHP files parse', !$bad, $bad ? implode('; ', array_slice($bad, 0, 3)) : $php . ' checked'];
        // migrations: add-only (or a recorded leftover's removal), a reason for a data change, nothing the runner can't run.
        // On staging only: on live they ran as Publish's first step (decisions/0162).
        $migs = self::migrations($r); $mFail = '';
        if ($where === 'staging' && $migs) { [$mItems, $mFail] = self::migrationChecks($r); $items = array_merge($items, $mItems); }
        if ($where === 'staging' && $migs && $mFail === '') {
            [$ok, $detail] = self::runStagingMigrations($pdo);
            $items[] = ['Migrations ran on staging', $ok, $detail];
        }
        // pages answer, and the error logs stay quiet while they're asked
        $base = $where === 'staging' ? 'https://staging.trvgo.us' : 'https://traversence.com';
        $root = $where === 'staging' ? self::stagingRoot($pdo) : self::liveRoot();
        $auth = $where === 'staging' ? self::checkLogin($pdo) : null;
        $urls = self::pagesOf($r);
        $logs = $root ? self::errorLogs($root, $r) : [];
        $sizes = []; foreach ($logs as $f) $sizes[$f] = (int) @filesize($f);
        $fails = []; $silent = 0;
        foreach ($urls as $u) {
            [$code, $body] = self::fetch($base . $u, $auth);
            if ($code === 0) { $silent++; continue; }
            if ($code >= 500 || preg_match('/<b>(Fatal|Parse) error<\/b>|PHP (Fatal|Parse) error|Fatal error: Uncaught/i', $body)) $fails[] = $u . ' (' . $code . ')';
        }
        if ($silent === count($urls)) $items[] = ['Pages answer', null, 'The server couldn\'t reach ' . parse_url($base, PHP_URL_HOST) . ' to ask (not counted as a failure; the error logs below still are)'];
        else $items[] = ['Pages answer', !$fails && !$silent, $fails || $silent ? 'Server errors: ' . implode(', ', array_slice(array_merge($fails, $silent ? [$silent . ' gave no answer'] : []), 0, 5)) : count($urls) . ' pages answered on ' . parse_url($base, PHP_URL_HOST)];
        clearstatcache();
        $fatal = []; $warn = 0;
        foreach ($sizes as $f => $was) {
            $now = (int) @filesize($f);
            if ($now <= $was) continue;
            $h = @fopen($f, 'rb'); if (!$h) continue;
            fseek($h, $was); $new = (string) fread($h, min(200000, $now - $was)); fclose($h);
            foreach (explode("\n", $new) as $l) {
                if (preg_match('/PHP (Fatal|Parse) error/i', $l)) $fatal[] = trim(mb_substr(preg_replace('/^\[[^\]]+\]\s*/', '', $l), 0, 220));
                elseif (preg_match('/PHP (Warning|Deprecated)/i', $l)) $warn++;
            }
        }
        $items[] = ['No new fatal errors in the logs', !$fatal, $fatal ? implode(' · ', array_slice(array_unique($fatal), 0, 3)) : ($warn ? $warn . ' warning' . ($warn === 1 ? '' : 's') . ' (not blocking)' : count($sizes) . ' logs read')];
        $first = '';
        foreach ($items as $i) if ($i[1] === false) { $first = $i[0] . ': ' . $i[2]; break; }
        return ['ok' => $first === '', 'where' => $where, 'at' => gmdate('c'), 'items' => $items, 'first_fail' => $first];
    }

    /** The pages a release touches, as addresses (its website/*.php that aren't endpoints), with the home and FAQ pages. */
    private static function pagesOf(array $r): array
    {
        $out = ['/', '/faq.php'];
        foreach (array_keys($r['files']) as $p) {
            if (!preg_match('#^website/(.+\.php)$#', $p, $m)) continue;
            if (preg_match('#(^|/)(api|includes|_[a-z]+)/#', $m[1])) continue;
            $out[] = '/' . preg_replace('#(^|/)index\.php$#', '$1', $m[1]);
        }
        return array_slice(array_values(array_unique($out)), 0, 25);
    }

    private static function errorLogs(string $root, array $r): array
    {
        $dirs = ['website', 'website/admin', 'website/api'];
        foreach (array_keys($r['files']) as $p) if (str_starts_with($p, 'website/')) $dirs[] = dirname($p);
        $out = [];
        foreach (array_unique($dirs) as $d) { $f = $root . '/' . $d . '/error_log'; $out[] = $f; }
        return $out;
    }

    private static function fetch(string $url, ?array $auth): array
    {
        require_once __DIR__ . '/StagingGate.php'; if (($w = StagingGate::hold($url, 'a release check')) !== null) return [0, $w];   // staging itself always goes; live's pages from staging don't
        $ch = curl_init($url);
        $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 8,
              CURLOPT_USERAGENT => 'Traversence release check', CURLOPT_HTTPHEADER => ['X-Tv-Check: release']];
        if ($auth) { $o[CURLOPT_USERPWD] = $auth[0] . ':' . $auth[1]; $o[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC; }
        curl_setopt_array($ch, $o);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, is_string($body) ? $body : ''];
    }

    /**
     * The checks' own sign-in on staging: a second line in its viewing-password file, its password kept here on the live
     * server only (never shown). Put back if a new viewing password rewrote the file.
     */
    public static function checkLogin(PDO $pdo): ?array   // public for Check the way in (StagingSite::checkWayIn)
    {
        require_once __DIR__ . '/StagingSite.php';
        $s = (array) StagingSite::get($pdo, 'setup');
        if (empty($s['base'])) return null;
        $f = StagingSite::layout((string) $s['base'])['htpasswd'];
        $st = $pdo->prepare("SELECT v FROM site_release_settings WHERE k = 'check_pw'"); $st->execute();
        $pw = (string) $st->fetchColumn();
        if ($pw === '') { $pw = bin2hex(random_bytes(18)); $pdo->prepare("REPLACE INTO site_release_settings (k, v) VALUES ('check_pw', ?)")->execute([$pw]); }
        $lines = is_file($f) ? (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [];
        $have = null;
        foreach ($lines as $l) if (str_starts_with($l, self::CHECK_USER . ':')) $have = substr($l, strlen(self::CHECK_USER) + 1);
        if ($have === null || !password_verify($pw, $have)) {
            $lines = array_values(array_filter($lines, fn ($l) => !str_starts_with($l, self::CHECK_USER . ':')));
            $lines[] = self::CHECK_USER . ':' . password_hash($pw, PASSWORD_BCRYPT);
            $tmp = $f . '.new-' . bin2hex(random_bytes(4));
            if (@file_put_contents($tmp, implode("\n", $lines) . "\n") !== false) { @chmod($tmp, 0644); @rename($tmp, $f); }
        }
        return [self::CHECK_USER, $pw];
    }

    /** Asks staging to run the migrations waiting in its app/migrations (website/api/staging-updates.php, staging only). */
    private static function runStagingMigrations(PDO $pdo): array
    {
        require_once __DIR__ . '/StagingSite.php';
        $s = (array) StagingSite::get($pdo, 'setup');
        $env = StagingSite::readEnvFile(StagingSite::layout((string) $s['base'])['env']);
        $tok = (string) ($env['HEALTH_TOKEN'] ?? '');
        if ($tok === '') return [false, 'Staging has no HEALTH_TOKEN in its .env: press Set up staging again.'];
        $auth = self::checkLogin($pdo);
        require_once __DIR__ . '/StagingGate.php'; if (($w = StagingGate::hold('https://staging.trvgo.us/api/staging-updates.php', 'staging\'s migrations')) !== null) return [false, $w];   // a call to staging itself: always goes
        $ch = curl_init('https://staging.trvgo.us/api/staging-updates.php');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => '', CURLOPT_TIMEOUT => 240,
            CURLOPT_HTTPHEADER => ['X-Health-Token: ' . $tok], CURLOPT_USERPWD => $auth[0] . ':' . $auth[1], CURLOPT_HTTPAUTH => CURLAUTH_BASIC]);
        $body = (string) curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $j = json_decode($body, true);
        if ($code !== 200 || !is_array($j)) return [false, 'Staging didn\'t run them (HTTP ' . ($code ?: 'no answer') . ').'];
        $failed = (array) ($j['failed'] ?? []);
        if ($failed) return [false, implode('; ', array_map(fn ($k, $v) => $k . ': ' . $v, array_keys($failed), $failed))];
        $ran = (array) ($j['ran'] ?? []);
        return [true, $ran ? 'Ran ' . implode(', ', $ran) : 'Nothing waiting (run already)'];
    }

    // ---- 3. live ---------------------------------------------------------------------------------------------------------

    private static function publish(PDO $pdo, array $r, int $uid, string $urgent): string
    {
        self::need('releases.publish');
        if ($urgent !== '') {
            if (mb_strlen($urgent) < 10) throw new RuntimeException('Say why it can\'t wait (a sentence): it\'s recorded with the release.');
            if (!in_array($r['state'], ['planned', 'approved', 'staged', 'vetted', 'scheduled'], true)) throw new RuntimeException('It\'s gone out already.');
        } elseif (!self::reviewed($pdo, (int) $r['id'])) {
            throw new RuntimeException('Its review isn\'t finished: UI, UX and Functional each need Approve (site manager → Review).');
        } elseif ($r['state'] === 'staged') {
            self::signedByPublish($pdo, $r, $uid, 'Publish');   // the look on staging was the review; Publish is the sign-off (decisions/0159 §5)
        } elseif (!in_array($r['state'], ['vetted', 'scheduled'], true)) {
            throw new RuntimeException('Publish opens once it\'s on staging, its checks passed and someone has looked at it there.');
        }
        @set_time_limit(300);
        $dir = self::dir((int) $r['id']); $root = self::liveRoot();
        // a live file changed since the release was built (another upload, another release): never written over unseen
        $moved = [];
        foreach ($r['files'] as $p => $f) { $now = self::liveSha($p); if ($now !== $f['live_was'] && $now !== $f['sha']) $moved[] = $p; }
        if ($moved && $urgent === '') throw new RuntimeException('Changed on the live site since this release was built: ' . implode(', ', array_slice($moved, 0, 5)) . (count($moved) > 5 ? ' and ' . (count($moved) - 5) . ' more' : '') . '. Nothing was published: rebuild the release on the live copy (upload it again as a new release).');
        if ($moved) self::note($r, 'Published over files changed on live since it was built (urgent): ' . implode(', ', array_slice($moved, 0, 5)) . '.', $uid);
        // its migrations first, before any file goes live (Jason, 2026-11-14; decisions/0162): they ran on staging, each
        // copies what it touches first, and a failure stops the publish here with nothing else changed
        $migs = self::migrations($r); $ranNow = [];
        if ($migs) {
            [, $mFail] = self::migrationChecks($r);
            if ($mFail !== '') throw new RuntimeException('Not published: ' . $mFail);
            [$ok, $ranNow, $why] = self::runMigrationsFirst($pdo, $r);
            if (!$ok) {
                $r['paused'] = 1;
                if ($r['state'] === 'scheduled') { $r['state'] = 'vetted'; $r['schedule_at'] = null; }   // not tried again every 10 minutes
                if ($r['state'] === 'staged') $r['state'] = 'vetted';
                $msg = 'Not published: its migrations run first, and ' . $why . '.' . ($ranNow ? ' Ran before it stopped: ' . implode(', ', array_map('basename', $ranNow)) . ' (a copy of what ' . (count($ranNow) === 1 ? 'it' : 'they') . ' touched is on System Configuration → Database updates).' : '')
                     . ' No file went live. Send a fixed release, or Publish again once the cause is fixed: it tries them again.';
                self::note($r, $msg, $uid); self::save($pdo, $r); self::record($pdo, $r);
                throw new RuntimeException($msg);
            }
        }
        $n = self::goLive($pdo, $r, $uid, $urgent, '', $ranNow);
        $c = self::checks($pdo, $r, 'live');
        $r['checks'] = $c; $r['checks_ok'] = $c['ok'];
        if (!$c['ok']) { $r['paused'] = 1; self::note($r, 'A check on live failed: ' . $c['first_fail'] . '. Paused: Turn off or Roll back is your call.', 0); self::alert($pdo, $r, $c['first_fail']); }
        self::save($pdo, $r); self::record($pdo, $r);
        return $c['ok'] ? 'Published: ' . $n . ' files are live' . ($r['step'] === 1 ? ', on for staff and test accounts.' : '.') . ' Its checks on live passed.'
                        : 'Published, but a check on live failed (' . $c['first_fail'] . '): it\'s paused. Turn it off or roll it back if it needs it.';
    }

    /** The files of a release live (each previous copy kept first, renamed into place), its state live, its switch's step 1. */
    private static function goLive(PDO $pdo, array &$r, int $uid, string $urgent, string $how, array $ranNow = [], array $also = []): int
    {
        @set_time_limit(300);
        $dir = self::dir((int) $r['id']); $root = self::liveRoot();
        $moved = array_values(array_filter(self::movedOnLive($r), fn ($p) => !in_array(self::liveSha($p), (array) ($also[$p] ?? []), true)));   // an earlier release of the same publish wrote it: expected (releases stack)
        if ($moved && $urgent === '' && $how !== '') throw new RuntimeException('Release #' . $r['id'] . ': changed on the live site since it was built: ' . implode(', ', array_slice($moved, 0, 5)) . '. Its files weren\'t published: rebuild it on the live copy.');
        // the previous copy of every file first, so a rollback always has it
        foreach ($r['files'] as $p => &$f) {
            if (str_starts_with($p, 'app/migrations/')) continue;   // ran above (and cleared, as every run file is)
            $live = $root . '/' . $p;
            $f['existed'] = is_file($live);
            if ($f['existed']) {
                $old = $dir . '/old/' . $p;
                if (!is_dir(dirname($old))) @mkdir(dirname($old), 0750, true);
                if (!@copy($live, $old)) throw new RuntimeException('Couldn\'t keep the previous copy of ' . $p . ', so nothing was published.');
                $f['old_sha'] = hash_file('sha256', $old);
            }
        }
        unset($f);
        $done = [];
        foreach ($r['files'] as $p => $f) {
            if (str_starts_with($p, 'app/migrations/')) continue;
            $live = $root . '/' . $p;
            if (!is_dir(dirname($live)) && !@mkdir(dirname($live), 0755, true)) { self::putBack($r, $done); throw new RuntimeException('Couldn\'t make the folder for ' . $p . '; the files already copied were put back.'); }
            $tmp = $live . '.rel-' . bin2hex(random_bytes(3));
            if (!@copy($dir . '/new/' . $p, $tmp) || !@rename($tmp, $live)) { @unlink($tmp); self::putBack($r, $done); throw new RuntimeException('Couldn\'t write ' . $p . '; the files already copied were put back.'); }
            $done[] = $p;
        }
        if (function_exists('opcache_reset')) @opcache_reset();
        $wasDue = $r['state'] === 'scheduled';
        $migs = self::migrations($r);   // the job publishing it at its time, not a build going live by itself
        $r['state'] = 'live'; $r['live_at'] = gmdate('Y-m-d H:i:s'); $r['step_at'] = $r['live_at']; $r['paused'] = 0; $r['schedule_at'] = null;
        if ($urgent !== '') $r['urgent'] = $urgent;
        if ($r['switch_key'] !== '') { self::switchTo($pdo, $r['switch_key'], 'test', 0, $uid, 'Release #' . $r['id'] . ': step 1'); $r['step'] = 1; }
        else $r['step'] = 3;
        if ($migs) { try { require_once __DIR__ . '/SchemaUpkeep.php'; $up = SchemaUpkeep::takenUp($r, $migs, $dir . '/new/'); if ($up) self::note($r, 'Takes up what a rolled-back release left: ' . implode(', ', $up) . '.', $uid); } catch (Throwable $x) { error_log('release leftovers taken up: ' . $x->getMessage()); } }
        if ($how !== '') self::note($r, $how . ': ' . count($done) . ' files' . ($r['step'] === 1 ? '; step 1, staff and test accounts.' : '; everyone at once (no switch).'), $uid);
        else self::note($r, ($urgent !== '' ? 'Published at once (urgent: ' . $urgent . ')' : ($uid || !$wasDue ? 'Published' : 'Published on schedule')) . ': ' . count($done) . ' files'
            . ($migs ? ', after its ' . count($migs) . ' migration' . (count($migs) === 1 ? '' : 's') . ' ran first (' . implode(', ', array_map('basename', $ranNow)) . '; a copy of what each touched is kept 14 days)' : '')
            . ($r['step'] === 1 ? '; step 1, staff and test accounts.' : '; everyone at once (no switch).'), $uid);
        return count($done);
    }

    /** The files a release changes that changed on live since it was built (another release, an upload): never written over unseen. */
    public static function movedOnLive(array $r): array
    {
        $moved = [];
        foreach ($r['files'] as $p => $f) { if (str_starts_with($p, 'app/migrations/')) continue; $now = self::liveSha($p); if ($now !== $f['live_was'] && $now !== $f['sha']) $moved[] = $p; }
        return $moved;
    }

    /** After a failed write: the files already copied go back to their previous copy (or away, if they were new). */
    private static function putBack(array $r, array $paths): void
    {
        $dir = self::dir((int) $r['id']); $root = self::liveRoot();
        foreach ($paths as $p) {
            if (!empty($r['files'][$p]['existed'])) @copy($dir . '/old/' . $p, $root . '/' . $p); else @unlink($root . '/' . $p);
        }
    }

    /** Sets a switch (one this release may have just added, so the class loaded on this request may not know it yet). */
    private static function switchTo(PDO $pdo, string $key, string $state, int $pct, int $by, string $note, bool $killed = false): void
    {
        require_once __DIR__ . '/Switches.php';
        if (isset(Switches::DEFS[$key])) { Switches::set($pdo, $key, $state, $pct, $killed, $note, $by); return; }
        $pdo->prepare('INSERT INTO feature_switches (switch_key, state, share_pct, killed, note, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW())
                       ON DUPLICATE KEY UPDATE state = VALUES(state), share_pct = VALUES(share_pct), killed = VALUES(killed), note = VALUES(note), updated_by = VALUES(updated_by), updated_at = NOW()')
            ->execute([$key, $state, max(0, min(100, $pct)), $killed ? 1 : 0, mb_substr($note, 0, 255), $by ?: null]);
    }

    private static function schedule(PDO $pdo, array $r, string $at, string $tz, int $uid): string
    {
        self::need('releases.schedule');
        if (!self::reviewed($pdo, (int) $r['id'])) throw new RuntimeException('Its review isn\'t finished: UI, UX and Functional each need Approve (site manager → Review).');
        if ($r['state'] === 'staged') self::signedByPublish($pdo, $r, $uid, 'Schedule');
        elseif (!in_array($r['state'], ['vetted', 'scheduled'], true)) throw new RuntimeException('Schedule opens once it\'s on staging, its checks passed and someone has looked at it there.');
        try { $z = new DateTimeZone($tz !== '' ? $tz : 'America/Phoenix'); $d = new DateTimeImmutable($at, $z); } catch (Throwable) { throw new RuntimeException('Choose a date and time.'); }
        if ($d->getTimestamp() < time() + 60) throw new RuntimeException('Choose a time in the future (or Publish now).');
        $r['schedule_at'] = $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); $r['tz'] = $z->getName(); $r['state'] = 'scheduled';
        self::note($r, 'Scheduled for ' . self::when($r['schedule_at'], $r['tz']) . '.', $uid);
        self::save($pdo, $r); self::record($pdo, $r);
        return 'Scheduled for ' . self::when($r['schedule_at'], $r['tz']) . '.';
    }

    private static function unschedule(PDO $pdo, array $r, int $uid): string
    {
        self::need('releases.schedule');
        if ($r['state'] !== 'scheduled') throw new RuntimeException('It isn\'t scheduled.');
        $r['state'] = 'vetted'; $r['schedule_at'] = null;
        self::note($r, 'Schedule taken off.', $uid);
        self::save($pdo, $r); self::record($pdo, $r);
        return 'Taken off the schedule: it\'s ready to publish or schedule again.';
    }

    private static function nextStep(PDO $pdo, array $r, int $uid, bool $auto): string
    {
        self::need('releases.publish');
        if ($r['state'] !== 'live' || $r['step'] >= 3) throw new RuntimeException($r['state'] === 'live' ? 'It\'s live for everyone already.' : 'It isn\'t live.');
        if ($r['paused'] && $auto) return 'Paused: waiting for you.';
        $c = self::checks($pdo, $r, 'live');
        $r['checks'] = $c; $r['checks_ok'] = $c['ok'];
        if (!$c['ok']) {
            $r['paused'] = 1;
            self::note($r, 'A check failed at step ' . $r['step'] . ' (' . $c['first_fail'] . '): paused before the next step. Turn off or Roll back is your call.', $uid);
            self::save($pdo, $r); self::record($pdo, $r);
            self::alert($pdo, $r, $c['first_fail']);
            return 'Not moved on: a check failed (' . $c['first_fail'] . '). It\'s paused.';
        }
        $s = self::settings($pdo);
        $r['step']++; $r['step_at'] = gmdate('Y-m-d H:i:s'); $r['paused'] = 0;
        if ($r['step'] === 2) self::switchTo($pdo, $r['switch_key'], 'share', (int) $s['share_pct'], $uid, 'Release #' . $r['id'] . ': step 2');
        else self::switchTo($pdo, $r['switch_key'], 'on', 100, $uid, 'Release #' . $r['id'] . ': step 3, everyone');
        self::note($r, ($auto ? 'On schedule: ' : '') . 'step ' . $r['step'] . ', ' . ($r['step'] === 2 ? $s['share_pct'] . '% of members (and staff and test accounts).' : 'everyone.'), $uid);
        self::save($pdo, $r); self::record($pdo, $r);
        return $r['step'] === 2 ? 'Step 2: on for ' . $s['share_pct'] . '% of members as well.' : 'Step 3: on for everyone.';
    }

    private static function pause(PDO $pdo, array $r, int $uid, bool $on, string $why): string
    {
        self::need('releases.publish');
        if ($r['state'] !== 'live') throw new RuntimeException('Only a release that\'s live can be paused.');
        $r['paused'] = $on ? 1 : 0;
        if (!$on) $r['step_at'] = gmdate('Y-m-d H:i:s');   // its clock starts again
        self::note($r, $why, $uid);
        self::save($pdo, $r); self::record($pdo, $r);
        return $on ? 'Paused: it stays at step ' . $r['step'] . ' until you carry on.' : 'Carrying on from step ' . $r['step'] . '.';
    }

    private static function turnOff(PDO $pdo, array $r, int $uid): string
    {
        self::need('releases.turn_off');
        if (!in_array($r['state'], ['live', 'off'], true)) throw new RuntimeException('It isn\'t live.');
        if ($r['switch_key'] === '') throw new RuntimeException('It has no switch, so it can\'t be turned off: Roll back takes its files out.');
        self::switchTo($pdo, $r['switch_key'], 'off', 0, $uid, 'Release #' . $r['id'] . ': turned off', true);
        $r['state'] = 'off'; $r['paused'] = 1;
        self::note($r, 'Turned off for everyone (switch ' . $r['switch_key'] . ').', $uid);
        self::save($pdo, $r); self::record($pdo, $r);
        return 'Turned off for everyone. Its files are still there; Roll back takes them out.';
    }

    private static function rollBack(PDO $pdo, array $r, int $uid): string
    {
        self::need('releases.rollback');
        if (!in_array($r['state'], ['live', 'off'], true)) throw new RuntimeException('Only a release that went live can be rolled back.');
        $dir = self::dir((int) $r['id']); $root = self::liveRoot();
        $back = 0; $gone = 0; $changed = [];
        foreach ($r['files'] as $p => $f) {
            if (preg_match('#^app/migrations/#', $p)) continue;   // migrations only add, and run once: nothing to undo
            $live = $root . '/' . $p;
            if (is_file($live) && hash_file('sha256', $live) !== $f['sha']) $changed[] = $p;   // changed again since: still put back, but said
            if (!empty($f['existed'])) { if (@copy($dir . '/old/' . $p, $live)) $back++; }
            elseif (is_file($live) && @unlink($live)) $gone++;
        }
        if (function_exists('opcache_reset')) @opcache_reset();
        if ($r['switch_key'] !== '') self::switchTo($pdo, $r['switch_key'], 'off', 0, $uid, 'Release #' . $r['id'] . ': rolled back');
        $r['state'] = 'rolled_back'; $r['paused'] = 0;
        // what its migrations made stays (they only add), recorded as left by this release (decisions/0162)
        $migs = self::migrations($r); $left = [];
        if ($migs) { try { require_once __DIR__ . '/SchemaUpkeep.php'; $left = SchemaUpkeep::recordLeftovers($pdo, $r, $migs, $dir . '/new/'); } catch (Throwable $x) { error_log('release leftovers: ' . $x->getMessage()); } }
        $migNote = !$migs ? '' : ($left ? ' Its migrations stay (they only add); what they made is recorded as left by release #' . $r['id'] . ': ' . implode(', ', array_slice($left, 0, 6)) . (count($left) > 6 ? ' and ' . (count($left) - 6) . ' more' : '') . '. If it isn\'t sent again within ' . SchemaUpkeep::DUE_DAYS . ' days, removing them is proposed (Database updates lists them).'
            : ' Its migrations stay (they only add); nothing they made is left to track.') . ' A copy of what they touched before they ran is on Database updates (14 days).';
        self::note($r, 'Rolled back: ' . $back . ' file' . ($back === 1 ? '' : 's') . ' put back to their previous copy, ' . $gone . ' new file' . ($gone === 1 ? '' : 's') . ' removed' . ($changed ? '; ' . count($changed) . ' had changed again since (' . implode(', ', array_slice($changed, 0, 3)) . ')' : '') . '.' . $migNote, $uid);
        $c = self::checks($pdo, $r, 'live');
        $r['checks'] = $c; $r['checks_ok'] = $c['ok'];
        self::save($pdo, $r); self::record($pdo, $r);
        return 'Rolled back: ' . $back . ' file' . ($back === 1 ? '' : 's') . ' put back, ' . $gone . ' removed.' . ($c['ok'] ? ' The checks on live passed.' : ' A check on live still fails: ' . $c['first_fail'] . '.');
    }

    private static function withdraw(PDO $pdo, array $r, int $uid): string
    {
        self::need('releases.publish');
        return self::withdrawAs($pdo, $r, $uid, 'Withdrawn before it went live');
    }

    /**
     * Withdraws a release that isn't live, for a person or for the site itself (Jason, 2026-11-18: "having to require me to
     * remove bad releases is not helpful. Since you create them, you need a way to deal with issues"). Staging is put back
     * as it was before the release: its migrations, which never ran on live, leave staging's folder (a failed one would
     * otherwise hold every migration after it on staging); each of its other files gets live's copy back, or is removed
     * when it was new, unless a later release on staging carries it too. $why opens the note in its record.
     */
    private static function withdrawAs(PDO $pdo, array $r, int $uid, string $why): string
    {
        if (!in_array($r['state'], ['planned', 'approved', 'staged', 'vetted', 'scheduled'], true)) throw new RuntimeException('It went live: roll it back instead.');
        $put = self::unstage($pdo, $r);
        $r['state'] = 'withdrawn'; $r['schedule_at'] = null;
        self::note($r, $why . ($put !== '' ? ' ' . $put : '') . '.', $uid);
        self::save($pdo, $r); self::record($pdo, $r);
        return 'Withdrawn.' . ($put !== '' ? ' ' . $put : '');
    }

    /** Staging put back as it was before a release that's being withdrawn: what was done, in a few words ('' when nothing). */
    private static function unstage(PDO $pdo, array $r): string
    {
        if (empty($r['staged_at'])) return '';
        $root = self::stagingRoot($pdo);
        if (!$root) return '';
        $later = self::replacedIn($pdo, $r);
        $back = 0; $gone = 0; $kept = 0;
        foreach (array_keys((array) $r['files']) as $p) {
            if (isset($later[$p])) { $kept++; continue; }   // a later release on staging carries it: its copy stays
            $to = $root . '/' . $p; $live = self::liveRoot() . '/' . $p;
            if (str_starts_with($p, 'app/migrations/')) { if (is_file($to) && @unlink($to)) $gone++; continue; }
            if (is_file($live)) { if (@copy($live, $to)) $back++; }
            elseif (is_file($to) && @unlink($to)) $gone++;
        }
        if (function_exists('opcache_reset')) @opcache_reset();
        $w = [];
        if ($back) $w[] = $back . ' file' . ($back === 1 ? '' : 's') . ' back to live\'s copy on staging';
        if ($gone) $w[] = $gone . ' taken off staging (its migrations, and files that were new)';
        if ($kept) $w[] = $kept . ' kept for a later release on staging';
        return $w ? '(' . implode('; ', $w) . ')' : '';
    }

    /**
     * A build from git that fails its checks on staging withdraws itself (2026-11-18): nothing on the live site changed, its
     * record in git says why, so the session that built it sends a fixed build. A zip a person uploaded stays for them.
     * Returns the line to show, or '' when it doesn't apply.
     */
    /**
     * Rule 1 and rule 2 kept by the site (decisions/0179; Piece 3, P3-3), only while the switch rule2_autopublish is on: a
     * release from git may go live by itself when its zip is in a daily batch the Founder approved (today's or yesterday's,
     * UTC), or when it's a low-risk kind ReleaseKind proves and the advocates passed it. '' when it may; else why it waits.
     * Off (as shipped): '' always, as before.
     */
    private static function rule2(PDO $pdo, array $r): string
    {
        require_once __DIR__ . '/Switches.php';
        $s = Switches::all($pdo)['rule2_autopublish'] ?? null;
        if (!$s || $s['killed'] || $s['state'] !== 'on') return '';
        $zip = (string) $r['zip_name'];
        try {
            require_once __DIR__ . '/GaugeTasks.php';
            $ap = GaugeTasks::approvals($pdo);
            foreach ([gmdate('Y-m-d'), gmdate('Y-m-d', time() - 86400)] as $day) {
                $v = (string) ($ap['area:batch-' . $day]['batch']['value'] ?? '');
                if ($zip !== '' && in_array($zip, array_map('trim', explode(',', $v)), true)) return '';
            }
        } catch (Throwable $e) {}
        $kind = ''; try { $st = $pdo->prepare('SELECT v FROM site_release_settings WHERE k = ?'); $st->execute(['kind:' . (int) $r['id']]); $kind = (string) $st->fetchColumn(); } catch (Throwable $e) {}
        require_once __DIR__ . '/ReleaseKind.php'; require_once __DIR__ . '/BeforeAfter.php';
        $files = [];
        foreach (array_keys($r['files']) as $p) { $new = @file_get_contents(self::dir((int) $r['id']) . '/new/' . $p); $old = is_file(self::liveRoot() . '/' . $p) ? @file_get_contents(self::liveRoot() . '/' . $p) : null; $files[$p] = [(string) $new, $old === false ? null : $old]; }
        // Jason, 2026-11-25 (0179's note "Tier 2 publishes on its own once Piece 3 is live"): Tier 1 and Tier 2 both go live by
        // themselves once staging's checks and the advocates pass; only Tier 3 stops, and comes to the Founder as a Decision Brief.
        $t3 = ReleaseKind::tier3(array_keys($files), $files);
        if ($t3 !== '') { self::briefFor($pdo, $r, $t3); return 'it touches a Tier 3 area (' . $t3 . '): it waits for the Founder, as a Decision Brief in the Work queue.'; }
        if ($kind === 'data_fix' || ReleaseKind::insertsRows($files)) return 'it changes data (a data fix): data fixes stay in the daily batch for now (Jason, 2026-11-25: DB3).';
        $k = ReleaseKind::prove($kind, $files);
        $ba = null; foreach ((array) ($r['checks']['items'] ?? []) as $i) if (($i[0] ?? '') === BeforeAfter::ITEM) $ba = $i[1];
        if ($ba !== true) return 'it\'s ' . ($k['ok'] ? ReleaseKind::KINDS[$kind] . ' (Tier 1)' : 'Tier 2') . ', but the advocates haven\'t passed it (the before-and-after check).';
        $r2 = (array) self::get($pdo, (int) $r['id']);
        self::note($r2, $k['ok'] ? 'Published alone (low-risk: ' . $kind . '): ' . $k['why'] : 'Published alone (Tier 2): it touches no Tier 3 area, and staging\'s checks and the advocates passed it.', 0);
        self::save($pdo, $r2);
        return '';
    }

    /** A Tier 3 release's Decision Brief (0179 rule 3): one Resolution ticket in the Work queue, once per release, for its brief to be written and the Founder to answer. */
    private static function briefFor(PDO $pdo, array $r, string $area): void
    {
        $k = 'brief:' . (int) $r['id'];
        try { $st = $pdo->prepare('SELECT v FROM site_release_settings WHERE k = ?'); $st->execute([$k]); if ($st->fetchColumn() !== false) return; } catch (Throwable $e) { return; }
        try {
            require_once __DIR__ . '/GaugeTasks.php';
            $id = GaugeTasks::add($pdo, 'area:backlog', mb_substr('Release #' . (int) $r['id'] . ' (' . ($r['title'] ?: $r['zip_name']) . ') touches ' . $area . ': your decision', 0, 250),
                'It passed staging but touches a Tier 3 area (' . $area . '), so it never goes live by itself (decisions/0179 rule 3). It stays on staging until you answer.',
                'Releases: release #' . (int) $r['id'] . ' on staging', 'the system', 0, '', 'resolution');
            $pdo->prepare('REPLACE INTO site_release_settings (k, v) VALUES (?, ?)')->execute([$k, (string) $id]);
            $r2 = (array) self::get($pdo, (int) $r['id']); self::note($r2, 'Tier 3 (' . $area . '): waiting for the Founder, Decision Brief #' . $id . ' in the Work queue.', 0); self::save($pdo, $r2);
        } catch (Throwable $e) { error_log('release #' . (int) $r['id'] . ' brief: ' . $e->getMessage()); }
    }

    /** A line in a release's record from the before-and-after check (BeforeAfter, Piece 3), as its other steps write theirs. */
    public static function noteFromCheck(PDO $pdo, int $id, string $msg): void
    {
        $r = self::get($pdo, $id); if (!$r) return;
        self::note($r, mb_substr($msg, 0, 400), 0); self::save($pdo, $r);
    }

    /**
     * The advocates' verdict on a release on staging (Piece 3, P3-2; decisions/0179's note "Piece 3's decisions"): it joins
     * the release's checks as "Before and after (advocates)". A pass goes on as a release that passed always has (it
     * publishes by itself, or waits for review when $hold: its release.json asks, or the day's minutes are spent, DB4); a
     * block withdraws it as a failed check does, and puts the failure in the Work queue (DB2); an incomplete look (the
     * accounts couldn't sign in) never publishes alone: it waits for review. Returns the line for the run.
     */
    public static function checkedByAdvocates(PDO $pdo, int $id, array $v, bool $hold): string
    {
        $r = self::get($pdo, $id);
        if (!$r || $r['state'] !== 'staged') return 'Release #' . $id . ': its before-and-after verdict came, but it isn\'t on staging any more.';
        require_once __DIR__ . '/BeforeAfter.php';
        $ok = $v['verdict'] === 'pass' ? true : ($v['verdict'] === 'block' ? false : null);
        $detail = $v['verdict'] === 'pass' ? $v['views'] . ' views by the advocates, before and after: nothing worse'
            : ($v['verdict'] === 'block' ? $v['blocks'] . ' problem' . ($v['blocks'] === 1 ? '' : 's') . ': ' . $v['first'] : 'Couldn\'t be judged (the test accounts couldn\'t sign in): it waits for a person');
        $items = array_values(array_filter((array) ($r['checks']['items'] ?? []), fn ($i) => ($i[0] ?? '') !== BeforeAfter::ITEM));
        $items[] = [BeforeAfter::ITEM, $ok, $detail . ($v['run'] !== '' ? ' · ' . $v['run'] : '')];
        $first = ''; foreach ($items as $i) if ($i[1] === false) { $first = $i[0] . ': ' . $i[2]; break; }
        $r['checks'] = ['ok' => $first === '', 'where' => 'staging', 'at' => gmdate('c'), 'items' => $items, 'first_fail' => $first];
        $r['checks_ok'] = $first === '';
        self::note($r, 'The before-and-after check: ' . ($ok === true ? 'passed.' : ($ok === false ? 'blocked it: ' . $v['first'] : 'couldn\'t judge it; it waits for a person.')), 0);
        self::save($pdo, $r);
        if ($ok === false) {
            $w = self::selfWithdraw($pdo, (array) self::get($pdo, $id));
            require_once __DIR__ . '/GaugeTasks.php';
            $t = GaugeTasks::auditOpen($pdo, 'ba:' . $id, 'Release #' . $id . ' was stopped by the before-and-after check: ' . mb_substr($v['first'], 0, 200),
                'An advocate\'s view was worse after it (decisions/0179 rule 2\'s four rules): ' . $v['first'] . '. It withdrew itself; nothing on the live site changed. Fix it and send it again.' . ($v['run'] !== '' ? ' The report: ' . $v['run'] : ''), 2, 'system · before-and-after check');
            return ($w !== '' ? $w : 'Release #' . $id . ': stopped by the before-and-after check.') . ' Task #' . $t . ' in the Work queue.';
        }
        return self::autoPublish($pdo, $id, 0, $hold || $ok === null);
    }

    public static function selfWithdraw(PDO $pdo, array $r): string
    {
        if ($r['state'] !== 'staged' || $r['checks_ok'] !== false || !self::fromGitRelease($r)) return '';   // checked and failed only, never not checked yet
        $first = (string) ($r['checks']['first_fail'] ?? 'a check failed');
        return 'Release #' . $r['id'] . ': ' . self::withdrawAs($pdo, $r, 0, 'Withdrawn by itself: its checks on staging failed (' . mb_substr($first, 0, 400) . '). Nothing on the live site changed; the session that built it sends a fixed build');
    }

    /** A new build that names earlier ones it replaces ("replaces" in its release.json: zip names or release numbers). */
    private static function replaceEarlier(PDO $pdo, int $id, array $names): array
    {
        $out = [];
        foreach (array_slice($names, 0, 10) as $n) {
            $n = trim((string) $n); if ($n === '') continue;
            $st = is_numeric(ltrim($n, '#'))
                ? $pdo->prepare("SELECT id FROM site_releases WHERE id = ? AND id <> ? AND state IN ('planned', 'approved', 'staged', 'vetted', 'scheduled')")
                : $pdo->prepare("SELECT id FROM site_releases WHERE (zip_name = ? OR zip_name = CONCAT(?, '.zip')) AND id <> ? AND state IN ('planned', 'approved', 'staged', 'vetted', 'scheduled')");
            $st->execute(is_numeric(ltrim($n, '#')) ? [(int) ltrim($n, '#'), $id] : [$n, $n, $id]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $old) {
                $o = self::get($pdo, (int) $old);
                if ($o) { self::withdrawAs($pdo, $o, 0, 'Replaced by release #' . $id . ', a fixed build, and withdrawn by itself'); $out[] = 'Release #' . $old . ' withdrawn: replaced by #' . $id . '.'; }
            }
        }
        return $out;
    }

    /** What a zip's release.json says ([] when it can't be read). */
    private static function planOf(string $zipData): array
    {
        if (!class_exists('ZipArchive')) return [];
        $tmp = tempnam(sys_get_temp_dir(), 'tvplan'); file_put_contents($tmp, $zipData);
        $z = new ZipArchive(); $p = [];
        if ($z->open($tmp) === true) { $j = json_decode((string) $z->getFromName('release.json'), true); $p = is_array($j) ? $j : []; $z->close(); }
        @unlink($tmp);
        return $p;
    }

    /** A failed check: an email to whoever approved the plan (and the Pulse case turns red). A system email: no cost. */
    private static function alert(PDO $pdo, array $r, string $what): void
    {
        try {
            $uid = (int) ($r['plan_by'] ?: $r['created_by']);
            if (!$uid) return;
            $st = $pdo->prepare('SELECT email FROM users WHERE id = ?'); $st->execute([$uid]);
            $to = (string) $st->fetchColumn();
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;
            require_once __DIR__ . '/Mailer.php';
            Mailer::send($to, 'Release #' . $r['id'] . ' is paused: a check failed', 'Release #' . $r['id'] . ' (' . $r['title'] . ') is paused at step ' . $r['step'] . ' of 3: ' . $what
                . "\n\nNothing was undone. Open System Configuration → Releases to carry on, turn it off or roll it back: https://traversence.com/admin/config/releases"
                . "\n\nSomething not right? Write to support@traversence.com.");
        } catch (Throwable $e) { error_log('release alert: ' . $e->getMessage()); }
    }

    // ---- the scheduled job -----------------------------------------------------------------------------------------------

    /** Publishes what's due and moves live releases on when their time comes (releases_tick). Returns lines for its log. */
    public static function tick(PDO $pdo): array
    {
        self::ensure($pdo);
        $out = [];
        // a build from git left at Planned or Plan approved (its zip here, nothing wrong with it now) goes on by itself (#106,
        // 2026-11-17: a rebuilt zip sat at Plan approved until a person pressed Put it on staging); a person's own upload waits for them
        foreach ($pdo->query("SELECT id FROM site_releases WHERE state IN ('planned', 'approved') AND updated_at < NOW() - INTERVAL 10 MINUTE ORDER BY id LIMIT 5")->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $r = self::get($pdo, (int) $id);
            if (!$r || !self::fromGitBuild($r)) continue;
            try { $out[] = self::straightOn($pdo, (int) $id, 0); } catch (Throwable $e) { error_log('releases tick, staging #' . $id . ': ' . $e->getMessage()); }
        }
        foreach ($pdo->query("SELECT id FROM site_releases WHERE state = 'staged' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $id) {
            try { $w = self::selfWithdraw($pdo, (array) self::get($pdo, (int) $id)); if ($w !== '') $out[] = $w; }
            catch (Throwable $e) { error_log('releases tick, withdrawing #' . $id . ': ' . $e->getMessage()); }
        }
        foreach ($pdo->query("SELECT id FROM site_releases WHERE state IN ('scheduled', 'live') ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $r = self::get($pdo, (int) $id);
            try {
                if ($r['state'] === 'scheduled' && strtotime((string) $r['schedule_at'] . ' UTC') <= time()) { $out[] = '#' . $id . ': ' . self::publish($pdo, $r, 0, ''); continue; }
                if ($r['state'] !== 'live' || $r['paused'] || $r['step'] >= 3 || $r['advance'] !== 'schedule' || $r['switch_key'] === '') continue;
                $s = self::settings($pdo);
                $after = $r['step'] === 1 ? $s['members_after_h'] : $s['everyone_after_h'];
                if (strtotime((string) $r['step_at'] . ' UTC') + $after * 3600 > time() || !self::inWindow($pdo)) continue;
                $out[] = '#' . $id . ': ' . self::nextStep($pdo, $r, 0, true);
            } catch (Throwable $e) { $out[] = '#' . $id . ': ' . $e->getMessage(); error_log('releases tick #' . $id . ': ' . $e->getMessage()); }
        }
        return $out;
    }

    // ---- the zips, for FTP ---------------------------------------------------------------------------------------------

    /** The release's own zip, or its rollback zip (the previous copies, and a list of its new files to delete): [path, name]. */
    public static function zip(PDO $pdo, int $id, string $which): array
    {
        self::need('releases.publish');
        $r = self::get($pdo, $id);
        if (!$r) throw new RuntimeException('That release isn\'t here.');
        if ($r['state'] === 'planned') throw new RuntimeException('The zips are ready once the plan is approved.');
        $dir = self::dir($id);
        if ($which === 'release') {
            if (!is_file($dir . '/release.zip')) throw new RuntimeException('Its zip isn\'t kept any more.');
            return [$dir . '/release.zip', 'release-' . $id . '.zip'];
        }
        if (!class_exists('ZipArchive')) throw new RuntimeException('The server\'s PHP can\'t make zips.');
        $f = $dir . '/rollback.zip';
        $z = new ZipArchive();
        if ($z->open($f, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Couldn\'t make the rollback zip.');
        $new = []; $root = self::liveRoot();
        foreach ($r['files'] as $p => $x) {
            if (preg_match('#^app/migrations/#', $p)) continue;
            $existed = array_key_exists('existed', $x) ? (bool) $x['existed'] : $x['live_was'] !== null;
            if (!$existed) { $new[] = $p; continue; }
            $src = is_file($dir . '/old/' . $p) ? $dir . '/old/' . $p : ($r['state'] === 'live' || $r['state'] === 'off' ? null : $root . '/' . $p);   // not live yet: today's live copy is the previous one
            if ($src && is_file($src)) $z->addFile($src, $p);
        }
        $z->addFromString('ROLLBACK.txt', "Rollback for release #$id (" . $r['title'] . ").\nUpload these files over the live ones. Then delete these files, which the release added:\n" . ($new ? implode("\n", $new) : '(none)') . "\nMigrations aren't undone: they only added.\n");
        $z->close();
        return [$f, 'rollback-' . $id . '.zip'];
    }

    // ---- the record in git -------------------------------------------------------------------------------------------------

    /** Writes the release to deliveries/releases/ in the docs repo (best effort: the page says when it couldn't). */
    private static function record(PDO $pdo, array $r): void
    {
        try {
            require_once __DIR__ . '/DocsRepo.php';
            if (!DocsRepo::canSave()) return;
            $slug = gmdate('Y-m-d', strtotime((string) $r['created_at'] . ' UTC')) . '-' . $r['id'] . '-' . (substr(trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $r['title'])), '-'), 0, 40) ?: 'release');
            $doc = ['id' => $r['id'], 'title' => $r['title'], 'why' => $r['why'], 'proposals' => $r['proposals'], 'zip' => $r['zip_name'], 'switch' => $r['switch_key'],
                    'advance' => $r['advance'], 'state' => $r['state'], 'step' => $r['step'], 'paused' => (bool) $r['paused'], 'urgent' => $r['urgent'],
                    'schedule_at' => $r['schedule_at'], 'plan_at' => $r['plan_at'], 'staged_at' => $r['staged_at'], 'vetted_at' => $r['vetted_at'], 'live_at' => $r['live_at'],
                    'files' => $r['files'], 'migrations' => self::migrations($r), 'checks' => $r['checks'], 'log' => $r['log']];
            $res = DocsRepo::saveRecord('deliveries/releases/' . $slug . '.json', json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", 'Release #' . $r['id'] . ': ' . self::STATES[$r['state']]);
            if (empty($res['ok'])) error_log('release record #' . $r['id'] . ': ' . ($res['error'] ?? ''));
        } catch (Throwable $e) { error_log('release record: ' . $e->getMessage()); }
    }

    // ---- Pulse -------------------------------------------------------------------------------------------------------------

    /** The releases waiting on someone or paused: [key, severity, title, why, when, action word]. */
    public static function pulseItems(PDO $pdo): array
    {
        self::ensure($pdo);
        $out = [];
        foreach ($pdo->query("SELECT * FROM site_releases WHERE state IN ('planned', 'approved', 'staged', 'vetted', 'scheduled', 'live', 'off') ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $r = self::decode($row);
            if ($r['state'] === 'live' && $r['step'] >= 3 && !$r['paused']) continue;   // done: nothing to do
            if ($r['state'] === 'live' && !$r['paused'] && $r['advance'] === 'schedule') continue;   // moving on by itself
            if ($r['state'] === 'scheduled') continue;
            [$line, $tone] = self::standing($r);
            $sev = $tone === 'bad' ? 'alert' : 'update';
            $out[] = ['release:' . $r['id'], $sev, 'Release #' . $r['id'] . ': ' . $r['title'], $line, (string) $r['updated_at'], self::nextWord($r)];
        }
        return $out;
    }

    private static function nextWord(array $r): string
    {
        if ($r['state'] === 'live' && $r['paused']) return 'Decide';
        return match ($r['state']) { 'planned' => 'Approve the plan', 'approved' => 'Put it on staging', 'staged' => $r['checks_ok'] ? 'Look, then Publish' : 'See the checks', 'vetted' => 'Publish', 'live' => 'Publish next step', 'off' => 'Roll back', default => 'Open' };
    }

    /** The Pulse case's steps: its next move as a button (decisions: a step doable from the case is a button). */
    public static function pulseSteps(PDO $pdo, int $id, callable $s): array
    {
        $r = self::get($pdo, $id);
        $page = '/admin/config/releases#rel-' . $id;
        if (!$r) return [$s('Open Releases', 'The release isn\'t here any more.', ['tool', '/admin/config/releases'])];
        $ch = self::changes($r);
        $read = $s('Read the release', ($ch ? "What changes:\n" . implode("\n", array_map(fn ($c) => '• ' . $c[0] . ($c[1] !== '' ? ' (' . $c[1] . ')' : ''), array_slice($ch, 0, 12))) . "\n\n" : '') . 'Its files, plan and checks are on System Configuration → Releases.', ['tool', $page]);
        $pubStg = fn (string $t) => $s($t, 'An upload on staging goes live with staging\'s own work (decisions/0165): preview it, choose any conflict, then Publish, on System Configuration → Staging or the site manager.', ['tool', '/admin/config/staging#against']);
        if ($r['state'] === 'live' && $r['paused']) return [$read,
            $s('Carry on', 'If the failed check doesn\'t matter, carry on from this step.', ['do', 'rel_resume', 'Carry on']),
            $s('Turn it off', 'Its switch off for everyone at once; the files stay.', ['do', 'rel_off', 'Turn off']),
            $s('Roll it back', 'The previous copy of every file goes back.', ['do', 'rel_back', 'Roll back'])];
        if ($r['state'] === 'staged' && !self::reviewed($pdo, $id))
            return [$read, $s('Review it on staging', 'Use it there, then in the site manager at the top: Review ▸ UI, UX and Functional, each Approve or Needs changes. Publish opens when all three are approved.')
                + ['ext' => ['staging (signed in as you)', 'https://traversence.com/admin/api/config/releases?action=enter&id=' . $id . '&as=me&to=%2F']]];
        return match ($r['state']) {
            'planned' => [$read, $s('Approve the plan', 'Check its title, switch and how it advances on the page first (it can be changed there).', ['do', 'rel_approve', 'Approve the plan'])],
            'approved' => [$read, $s('Put it on staging', 'Copies its files to staging.trvgo.us, runs its migrations there and its checks.', ['do', 'rel_stage', 'Put it on staging'])],
            'staged' => $r['checks_ok'] ? [$read, self::fromGitRelease($r) ? $s('Look at it on staging, then publish it', 'Open staging (its site manager has Publish ▾); Publish here works once someone has looked at it there.', ['do', 'rel_publish', 'Publish']) : $pubStg('Look at it on staging, then publish staging')]
                                        : [$read, $s('Fix what failed', 'Upload a fixed zip as a new release, or put this one on staging again.', ['do', 'rel_stage', 'Put it on staging again'])],
            'vetted' => [$read, self::fromGitRelease($r) ? $s('Publish it', 'Or schedule it on the page.', ['do', 'rel_publish', 'Publish']) : $pubStg('Publish staging')],
            'live' => [$read, $s('Publish the next step', 'It waits for you (it advances on Publish next step).', ['do', 'rel_next', 'Publish next step'])],
            'off' => [$read, $s('Roll it back', 'Its files are still live behind the switch.', ['do', 'rel_back', 'Roll back'])],
            default => [$read],
        };
    }
}
