<?php
// app/lib/StagingPublish.php — Publish is a merge: staging into live, balanced, nothing lost (decisions/0165 §4, §5).
// Runs on the live site (the site manager asks; the server's job, staging_publish_tick, carries it on a step at a time,
// so the page can close). One ordered run:
//   1 check       the preview is fresh (15 minutes), no conflict open, every release on staging passed (and its review),
//                 room on disk
//   2 migrations  every release's, oldest first (0162: add-only, copied first; a failure stops here, nothing else changed)
//   3 data        per database, per table (parents before children), 250 rows a batch: each batch's journal entries are
//                 written first (pub_journal: the row before, write-ahead), then the batch in one transaction, each row
//                 written only if live's still matches what the preview saw (else a late conflict, left as live has it)
//   4 files       every release's files (each kept first, renamed into place), then staging's files in the store, 100 a step
//   5 checks      on live, then switches step 1 where a release has one
//   6 in step     staging matches live again: staging's new rows take their live numbers, the base taken again
// A failure while writing undoes what this publish wrote at once, from the journal, read backward (last write first),
// each only where live still holds what the publish wrote; an interrupted undo resumes where it stopped. Roll back (14
// days) is the same undo, asked for. Live stays open throughout (Jason, choice 2). Staging's views, counts, logs, money,
// test data, stripped details, sessions and switches are never carried (§4); held effects are never sent by Publish:
// they're offered after, on To send now.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/StagingSync.php';

final class StagingPublish
{
    public const FRESH_MIN = 15;
    public const BATCH = 250;
    public const FILES = 100;
    public const KEEP_DAYS = 14;

    /** What a table's rows are, in the preview's counts. */
    public static function kindOf(string $t): string
    {
        foreach ([
            'Pages' => '/^(site_pages|site_block_|content_template|hero_media|consent_wordings|policies)/',
            'Listings' => '/^(entities|entity_|listing_|categor|storefronts|business_domains|short_codes|identity_)/',
            'Places' => '/^(places|place_|landmarks|experience|hubs|geo_hubs|hub_|micro_clusters|cluster_|nations|nation_|jurisdiction|heritage_|live_event|stories)/',
            'Guides and journeys' => '/^(resource_|how_guides|guide_|journey|suggested_journeys|topic|situation)/',
            'Community' => '/^(community_|member_post|content_comments|content_tags|content_reports|takedowns|journey_comments)/',
            'Settings and switches' => '/(_settings|_switches)$|^(search_terms|search_meanings|search_senses|source_rules|sources|crawler_sources|reference_sources)$/',
            'Workflows' => '/^(wf_|workflow_|crawl_workflows|crawl_capture_tasks)/',
            'People and staff' => '/^(users|member_profiles|user_|notice_prefs|people_|staff_|admin_|role_|contributors|listing_claims|listing_invites)/',
        ] as $k => $p) if (preg_match($p, $t)) return $k;
        return 'Other records';
    }

    // ---- the state of the publish (live's record of staging) -----------------------------------------------------------

    public static function state(PDO $pdo, string $k): ?array { require_once __DIR__ . '/StagingSite.php'; return StagingSite::get($pdo, $k); }
    private static function put(PDO $pdo, string $k, ?array $v): void { require_once __DIR__ . '/StagingSite.php'; StagingSite::putState($pdo, $k, $v); }

    /** Who made a change: work data or test data (§3). $test = [user ids, staff ids]. */
    public static function isTest(array $ch, array $test): bool
    {
        if (!empty($ch['test'])) return true;
        $a = (string) ($ch['actor'] ?? '');
        if (preg_match('/^u(\d+)/', $a, $m) && in_array((int) $m[1], $test[0], true)) return true;
        if (preg_match('/s(\d+)$/', $a, $m) && in_array((int) $m[1], $test[1], true)) return true;
        return false;
    }

    // ---- the preview ---------------------------------------------------------------------------------------------------

    /**
     * Works out what Publish would do (and keeps it as the plan, stg_plan in each staging database): per row, the merge
     * (StagingMerge) with any choice made on a conflict and any row marked test or work; what isn't carried and why; the
     * releases; held effects; the time it should take. Catch-up runs first for the rows staging changed, so the preview
     * sees live as it is now.
     */
    public static function preview(PDO $pdo, int $uid = 0): array
    {
        require_once __DIR__ . '/StagingSite.php';
        require_once __DIR__ . '/Releases.php';
        if (($hold = StagingSite::copyHold($pdo)) !== '') throw new RuntimeException($hold);
        $id = bin2hex(random_bytes(6));
        $test = StagingSync::testIds();
        $sum = ['id' => $id, 'at' => gmdate('c'), 'by' => $uid, 'counts' => [], 'conflicts' => 0, 'late' => 0, 'not_carried' => [], 'unknown' => [], 'rows' => 0, 'clash' => [],
                'releases' => [], 'blocking' => [], 'files' => 0, 'held' => 0, 'renumber' => 0, 'tables' => []];
        foreach (StagingSync::pairs() as $realm => [$c, $l, $s]) {
            StagingSync::ensure($realm);
            self::ensurePlan($realm);
            $c->exec('DELETE FROM ' . StagingSync::q($s, 'stg_plan') . ' WHERE pub <> ' . $c->quote($id) . ' AND made_at < NOW() - INTERVAL 1 DAY');
            $c->prepare('DELETE FROM ' . StagingSync::q($s, 'stg_plan') . ' WHERE pub = ?')->execute([$id]);
            $seq = 0;
            foreach (self::orderTables($realm, StagingSync::tables($realm, true)) as $t) {
                $cl = StagingTables::classOf($t);
                $keys = self::candidates($realm, $t);
                if (!$keys) continue;
                if ($cl !== 'records') { $sum['not_carried'][$cl === 'money' ? 'Money (everything on staging is sandbox)' : 'Not carried'] = ($sum['not_carried'][$cl === 'money' ? 'Money (everything on staging is sandbox)' : 'Not carried'] ?? 0) + count($keys); continue; }
                $skip = StagingSync::skipCols($realm, $t);
                $m = StagingSync::meta($realm, $t);
                $ins = $c->prepare('INSERT INTO ' . StagingSync::q($s, 'stg_plan') . ' (pub, realm, seq, tbl, k, do_what, live_h, fields, test, why, made_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                foreach ($keys as $k) {
                    try { StagingSync::catchUpKey($realm, $t, $k); } catch (Throwable $e) { error_log('preview catch-up ' . $t . ' ' . $k . ': ' . $e->getMessage()); }
                    $ch = StagingSync::change($realm, $t, $k);
                    $stg = StagingSync::row($realm, 'staging', $t, $k);
                    $live = StagingSync::row($realm, 'live', $t, $k);
                    if ($ch === null && $stg === null && $live === null) continue;
                    $base = $ch['before'] ?? ($ch === null && $live !== null ? $live : null);
                    if ($ch !== null && $ch['op'] === 'i') $base = null;
                    $mg = StagingMerge::row($base, $stg, $live, $skip);
                    if ($mg['do'] === StagingMerge::CONFLICT) {
                        $choice = self::choiceFor($realm, $t, $k);
                        if ($choice !== null) { try { $mg = StagingMerge::settle($mg, $choice, $stg, $live); } catch (Throwable) {} }
                    }
                    $isTest = $ch !== null && self::isTest($ch, $test);
                    if (($ch['mark'] ?? '') === 'work') $isTest = false;
                    if (($ch['mark'] ?? '') === 'test') $isTest = true;
                    if ($mg['left']) $sum['not_carried']['Stand-ins for people\'s details (stripped on staging)'] = ($sum['not_carried']['Stand-ins for people\'s details (stripped on staging)'] ?? 0) + 1;
                    if ($isTest && $mg['do'] !== StagingMerge::KEEP) { $sum['not_carried']['Test data (made by a test account)'] = ($sum['not_carried']['Test data (made by a test account)'] ?? 0) + 1; $mg['do'] = 'test'; }
                    if ($mg['do'] === StagingMerge::CONFLICT) $sum['conflicts']++;
                    if ($mg['do'] === StagingMerge::INSERT) {
                        $sum['renumber']++;
                        $clash = self::uniqueClash($realm, $t, $stg ?? []);
                        if ($clash !== '') { $mg['do'] = StagingMerge::CONFLICT; $mg['why'] = 'Both sides added a row with the same ' . $clash . '.'; $sum['conflicts']++; $sum['clash'][] = $t . ' ' . $clash; }
                    }
                    if (in_array($mg['do'], [StagingMerge::UPDATE, StagingMerge::INSERT, StagingMerge::DELETE], true)) {
                        $kind = self::kindOf($t); $w = $mg['do'] === StagingMerge::INSERT ? 'added' : ($mg['do'] === StagingMerge::DELETE ? 'removed' : 'changed');
                        $sum['counts'][$kind][$w] = ($sum['counts'][$kind][$w] ?? 0) + 1;
                        $sum['rows']++;
                        $sum['tables'][$realm . '.' . $t] = ($sum['tables'][$realm . '.' . $t] ?? 0) + 1;
                    }
                    $ins->execute([$id, $realm, ++$seq, $t, $k, $mg['do'], StagingSync::hashOf($realm, 'live', $t, $k), json_encode(self::enc($mg['fields'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $isTest ? 1 : 0, mb_substr((string) $mg['why'], 0, 250)]);
                    unset($m);
                }
            }
            // tables with no class: what differs from live there isn't carried, and is said
            $st = $c->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'"); $st->execute([$s]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $t) {
                if (StagingTables::named((string) $t) || str_starts_with((string) $t, 'stg_') || $t === 'pub_journal') continue;
                $sum['not_carried']['Tables with no class yet (they follow live): ' . $t] = 1;
            }
        }
        // the unknown references in Records tables
        foreach (StagingSync::refs()['unknown'] as [$realm, $t, $col]) if (StagingTables::carried($t)) $sum['unknown'][] = $t . '.' . $col;
        // releases on staging and whether each may go
        foreach (Releases::onStaging($pdo) as $r) {
            $ok = (bool) $r['checks_ok'] && Releases::reviewed($pdo, (int) $r['id']);
            $sum['releases'][] = ['id' => (int) $r['id'], 'title' => (string) $r['title'], 'ok' => $ok, 'files' => count($r['files'])];
            $sum['files'] += count($r['files']);
            if (!(bool) $r['checks_ok']) $sum['blocking'][] = 'Release #' . $r['id'] . '\'s checks on staging didn\'t pass: ' . (string) ($r['checks']['first_fail'] ?? '');
            elseif (!Releases::reviewed($pdo, (int) $r['id'])) $sum['blocking'][] = 'Release #' . $r['id'] . ' waits for its review (UI, UX and Functional, in the site manager).';
        }
        $sum['store_files'] = self::storeFiles($pdo, true);
        $sum['held'] = count(self::heldFor($pdo));
        if ($sum['conflicts']) $sum['blocking'][] = $sum['conflicts'] . ' conflict' . ($sum['conflicts'] === 1 ? '' : 's') . ' to choose (Conflicts): Publish opens when none is open.';
        $sum['minutes'] = max(1, (int) ceil(($sum['rows'] / self::BATCH) * 0.2 + $sum['files'] / 300 + $sum['store_files'] / self::FILES * 0.5 + count($sum['releases']) * 0.5));
        self::put($pdo, 'preview', $sum);
        return $sum;
    }

    private static function enc(array $row): array
    {
        foreach ($row as $k => $v) if (is_string($v) && !mb_check_encoding($v, 'UTF-8')) $row[$k] = ['b64' => base64_encode($v)];
        return $row;
    }

    private static function dec(array $row): array
    {
        foreach ($row as $k => $v) if (is_array($v) && isset($v['b64'])) $row[$k] = base64_decode((string) $v['b64']);
        return $row;
    }

    public static function ensurePlan(string $realm): void
    {
        [$c, , $s] = StagingSync::pairs()[$realm];
        $c->exec('CREATE TABLE IF NOT EXISTS ' . StagingSync::q($s, 'stg_plan') . ' (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, pub VARCHAR(16) NOT NULL, realm VARCHAR(12) NOT NULL, seq INT NOT NULL, tbl VARCHAR(64) NOT NULL, k VARCHAR(191) NOT NULL,
            do_what VARCHAR(10) NOT NULL, live_h CHAR(32) NULL, fields LONGTEXT NULL, test TINYINT NOT NULL DEFAULT 0, why VARCHAR(255) NOT NULL DEFAULT \'\', state VARCHAR(10) NOT NULL DEFAULT \'\', made_at DATETIME NOT NULL, KEY idx_stg_plan (pub, seq)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    /** The rows staging changed in a table: its change records, and rows the base has that staging no longer does (a delete the database cascaded). */
    public static function candidates(string $realm, string $t): array
    {
        [$c, , $s] = StagingSync::pairs()[$realm];
        $m = StagingSync::meta($realm, $t);
        $st = $c->prepare('SELECT k FROM ' . StagingSync::q($s, 'stg_changes') . ' WHERE tbl = ?'); $st->execute([$t]);
        $keys = array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
        try {
            $sql = 'SELECT b.k FROM ' . StagingSync::q($s, 'stg_base') . ' b LEFT JOIN ' . StagingSync::q($s, $t) . ' x ON b.k = ' . StagingSync::keySql($m['pk'], 'x') . ' WHERE b.tbl = ' . $c->quote($t) . ' AND b.sh <> \'\' AND x.`' . str_replace('`', '', $m['pk'][0]) . '` IS NULL';
            foreach ($c->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $k) $keys[] = (string) $k;
        } catch (Throwable $e) { error_log('preview candidates ' . $t . ': ' . $e->getMessage()); }
        $keys = array_values(array_unique($keys));
        if ($m['int']) sort($keys, SORT_NUMERIC);
        return $keys;
    }

    /** Tables in the order their rows can be written: one a row points to before the row (by the register's column references). */
    public static function orderTables(string $realm, array $tables): array
    {
        $deps = array_fill_keys($tables, []);
        foreach (StagingSync::refs()['refs'] as [$r, $t, $col, $place, $points]) if ($r === $realm && $place === 'col' && isset($deps[$t]) && isset($deps[$points]) && $points !== $t) $deps[$t][$points] = true;
        $out = []; $seen = [];
        $visit = function (string $t, array $path) use (&$visit, &$out, &$seen, $deps): void {
            if (isset($seen[$t]) || isset($path[$t])) return;   // a loop: the later fix-up step settles it
            $path[$t] = true;
            foreach (array_keys($deps[$t]) as $d) $visit($d, $path);
            $seen[$t] = true; $out[] = $t;
        };
        foreach ($tables as $t) $visit($t, []);
        return $out;
    }

    /** A unique key (not the primary) on live that a row added on staging would clash with: its name, or ''. */
    private static function uniqueClash(string $realm, string $t, array $row): string
    {
        [$c, $l] = StagingSync::pairs()[$realm];
        $st = $c->prepare("SELECT INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND NON_UNIQUE = 0 AND INDEX_NAME <> 'PRIMARY' ORDER BY INDEX_NAME, SEQ_IN_INDEX");
        $st->execute([$l, $t]);
        $idx = [];
        foreach ($st->fetchAll(PDO::FETCH_NUM) as [$i, $col]) $idx[(string) $i][] = (string) $col;
        foreach ($idx as $name => $cols) {
            $w = []; $p = [];
            foreach ($cols as $col) { if (!array_key_exists($col, $row) || $row[$col] === null) continue 2; $w[] = '`' . $col . '` = ?'; $p[] = $row[$col]; }
            $q = $c->prepare('SELECT 1 FROM ' . StagingSync::q($l, $t) . ' WHERE ' . implode(' AND ', $w) . ' LIMIT 1'); $q->execute($p);
            if ($q->fetchColumn()) return implode(', ', $cols);
        }
        return '';
    }

    private static function choiceFor(string $realm, string $t, string $k)
    {
        [$c, , $s] = StagingSync::pairs()[$realm];
        try { $st = $c->prepare('SELECT choice FROM ' . StagingSync::q($s, 'stg_conflicts') . ' WHERE tbl = ? AND k = ? AND settled_at IS NOT NULL'); $st->execute([$t, $k]); $v = $st->fetchColumn(); } catch (Throwable) { return null; }
        return $v === false || $v === null ? null : json_decode((string) $v, true);
    }

    /** Marks one carried row as test (left out) or a test row as work (carried), from the preview. */
    public static function mark(string $realm, string $t, string $k, string $as): void
    {
        if (!in_array($as, ['test', 'work', ''], true)) throw new InvalidArgumentException('Mark it test or work.');
        [$c, , $s] = StagingSync::pairs()[$realm];
        $c->prepare('UPDATE ' . StagingSync::q($s, 'stg_changes') . ' SET mark = ? WHERE tbl = ? AND k = ?')->execute([$as, $t, $k]);
    }

    /** The plan's rows of a preview, for the drawer (a page of them). */
    public static function planRows(string $pub, int $limit = 200, string $only = ''): array
    {
        $out = [];
        foreach (StagingSync::pairs() as $realm => [$c, , $s]) {
            try {
                $st = $c->prepare('SELECT realm, tbl, k, do_what, test, why, state FROM ' . StagingSync::q($s, 'stg_plan') . ' WHERE pub = ?' . ($only !== '' ? ' AND do_what = ' . $c->quote($only) : '') . ' ORDER BY seq LIMIT ' . (int) $limit);
                $st->execute([$pub]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[] = $r + ['kind' => self::kindOf((string) $r['tbl'])];
            } catch (Throwable) {}
        }
        return $out;
    }

    // ---- Publish ---------------------------------------------------------------------------------------------------------

    /** Starts a publish from the latest preview (or carries on the one running). Returns the run. */
    public static function start(PDO $pdo, int $uid, string $tz = ''): array
    {
        require_once __DIR__ . '/Releases.php';
        [$ok, $why] = Releases::can('releases.publish');
        if (!$ok) throw new RuntimeException($why);
        $run = self::state($pdo, 'publish');
        if ($run && in_array($run['state'] ?? '', ['running', 'undoing'], true)) return $run;
        $pv = self::state($pdo, 'preview');
        if (!$pv) throw new RuntimeException('Work out the preview first (Publish ▾ → Preview).');
        require_once __DIR__ . '/StagingSite.php';
        if (($hold = StagingSite::copyHold($pdo)) !== '') throw new RuntimeException($hold);
        $run = ['id' => (string) $pv['id'], 'state' => 'running', 'started_at' => gmdate('c'), 'tz' => $tz, 'by' => $uid, 'pos' => 0, 'seq' => 0, 'log' => [],
                'steps' => ['check', 'migrations', 'data', 'fixup', 'files', 'store', 'checks', 'instep'], 'cursor' => [], 'map' => [], 'late' => [], 'releases' => array_column($pv['releases'], 'id'), 'done_releases' => []];
        self::put($pdo, 'publish', $run);
        return $run;
    }

    /** Works on the publish for up to $seconds, a step (or a batch of one) at a time. Returns the run. */
    public static function carry(PDO $pdo, int $seconds = 50): array
    {
        $run = self::state($pdo, 'publish');
        if (!$run || !in_array($run['state'] ?? '', ['running', 'undoing'], true)) return $run ?? [];
        if (!self::lock($pdo)) return $run + ['busy' => true];
        $t0 = time();
        try {
            while (time() - $t0 < $seconds) {
                if ($run['state'] === 'undoing') { $run = self::undoStep($pdo, $run, 200); if ($run['state'] !== 'undoing') break; continue; }
                if ($run['pos'] >= count($run['steps'])) { $run = self::finish($pdo, $run); break; }
                $step = $run['steps'][$run['pos']];
                $doneStep = self::step($pdo, $run, $step);
                if ($doneStep) { $run['log'][] = gmdate('H:i:s') . ' ' . self::stepName($step) . ': done.'; $run['pos']++; $run['cursor'] = []; }
                self::put($pdo, 'publish', $run);
            }
        } catch (Throwable $e) {
            $stepName = self::stepName($run['steps'][$run['pos']] ?? 'finish');
            error_log('staging publish (' . $stepName . '): ' . $e->getMessage());
            $run['log'][] = gmdate('H:i:s') . ' Stopped at ' . $stepName . ': ' . $e->getMessage();
            $run['stopped_at'] = $stepName; $run['why'] = $e->getMessage();
            if (in_array($run['steps'][$run['pos']] ?? '', ['check', 'migrations'], true)) { $run['state'] = 'stopped'; $run['message'] = 'Publish stopped at ' . $stepName . ': ' . $e->getMessage() . ' Nothing of staging\'s data or files went live.'; }
            else { $run['state'] = 'undoing'; $run['message'] = 'Publish stopped at ' . $stepName . ' (' . $e->getMessage() . '): what it wrote is being undone, last write first.'; }
            self::put($pdo, 'publish', $run);
            if ($run['state'] === 'stopped') self::tell($pdo, $run);
        } finally { self::unlock($pdo); }
        return $run;
    }

    public static function stepName(string $s): string
    {
        return ['check' => 'the checks before', 'migrations' => 'migrations', 'data' => 'data', 'fixup' => 'links between new rows', 'files' => 'releases\' files', 'store' => 'staging\'s files in the store',
                'checks' => 'checks on live', 'instep' => 'staging back in step', 'finish' => 'finishing'][$s] ?? $s;
    }

    /** One piece of a step. True when the step is done. */
    private static function step(PDO $pdo, array &$run, string $step): bool
    {
        require_once __DIR__ . '/Releases.php';
        switch ($step) {
            case 'check':
                $pv = (array) self::state($pdo, 'preview');
                if (($pv['id'] ?? '') !== $run['id']) throw new RuntimeException('A newer preview was worked out: Preview again, then Publish.');
                if (strtotime((string) $pv['at']) < time() - self::FRESH_MIN * 60) throw new RuntimeException('The preview is over ' . self::FRESH_MIN . ' minutes old: Preview again (it catches up first), then Publish.');
                if (!empty($pv['blocking'])) throw new RuntimeException(implode(' ', $pv['blocking']));
                if (StagingSync::conflicts(1)) throw new RuntimeException('A conflict came up since the preview: choose it, Preview again, then Publish.');
                $free = @disk_free_space(dirname(__DIR__));
                if ($free !== false && $free < 200 * 1024 * 1024) throw new RuntimeException('Under 200 MB free on the server: make room first.');
                if (!empty($pv['unknown']) && !empty($pv['renumber'])) StagingSync::stopOnUnknown(self::newIdsSoFar($run['id']));
                return true;
            case 'migrations':
                foreach ($run['releases'] as $id) { Releases::publishPart($pdo, (int) $id, (int) $run['by'], 'migrations', $run['id']); }
                return true;
            case 'data':
                return self::dataBatch($pdo, $run);
            case 'fixup':
                return self::fixup($pdo, $run);
            case 'files':
                foreach ($run['releases'] as $id) if (!in_array($id, $run['done_releases'], true)) {
                    self::journal($run, 'main', 'release', (string) $id, 'release', null, null, '', 1);
                    Releases::publishPart($pdo, (int) $id, (int) $run['by'], 'files', $run['id']);
                    $run['done_releases'][] = $id;
                    return false;   // one release a piece
                }
                return true;
            case 'store':
                return self::storeStep($pdo, $run);
            case 'checks':
                $bad = [];
                foreach ($run['releases'] as $id) { $msg = Releases::publishPart($pdo, (int) $id, (int) $run['by'], 'checks', $run['id']); if ($msg !== '') $bad[] = $msg; }
                if ($bad) $run['log'][] = 'A check on live failed: ' . implode(' ', $bad) . ' Turn off or Roll back is your call.';
                $run['checks_failed'] = $bad;
                return true;
            case 'instep':
                return self::inStep($pdo, $run);
        }
        return true;
    }

    /** The journal's next number across every database (undo reads it backward). */
    private static function journal(array &$run, string $realm, string $t, string $k, string $op, ?array $before, ?string $afterH, string $newK, int $done): int
    {
        $p = StagingSync::pairs();
        if (!isset($p[$realm])) $realm = (string) array_key_first($p);
        [$c, , $s] = $p[$realm];
        StagingSync::ensure($realm);
        $seq = ++$run['seq'];
        $c->prepare('INSERT INTO ' . StagingSync::q($s, 'pub_journal') . ' (pub, seq, tbl, k, op, before_row, after_h, new_k, done, at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
          ->execute([$run['id'], $seq, $t, $k, $op, $before === null ? null : json_encode(self::enc($before), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $afterH, $newK, $done]);
        return (int) $c->lastInsertId();
    }

    /** One batch of the data step: the next 250 planned rows of the current database. */
    private static function dataBatch(PDO $pdo, array &$run): bool
    {
        $realms = array_keys(StagingSync::pairs());
        $ri = (int) ($run['cursor']['realm'] ?? 0);
        if ($ri >= count($realms)) return true;
        $realm = $realms[$ri];
        [$c, $l, $s] = StagingSync::pairs()[$realm];
        self::ensurePlan($realm);
        $after = (int) ($run['cursor']['seq'] ?? 0);
        $st = $c->prepare("SELECT * FROM " . StagingSync::q($s, 'stg_plan') . " WHERE pub = ? AND seq > ? AND do_what IN ('update', 'insert', 'delete') AND state = '' ORDER BY seq LIMIT " . self::BATCH);
        $st->execute([$run['id'], $after]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) { $run['cursor'] = ['realm' => $ri + 1, 'seq' => 0]; return $ri + 1 >= count($realms); }
        // 1. write-ahead: every entry of the batch in the journal first, with the row as live has it now
        $entries = [];
        foreach ($rows as $r) {
            $before = StagingSync::row($realm, 'live', (string) $r['tbl'], (string) $r['k']);
            $jid = self::journal($run, $realm, (string) $r['tbl'], (string) $r['k'], (string) $r['do_what'], $before, null, '', 0);
            $entries[] = [$r, $jid, $before];
        }
        self::put($pdo, 'publish', $run);
        // 2. the batch, in one transaction, by this database's own login, each row only if live's still matches the preview
        $map = $run['map'];
        $c->exec('SET @stg_pub = 1');
        $c->beginTransaction();
        try {
            foreach ($entries as [$r, $jid, $before]) {
                $t = (string) $r['tbl']; $k = (string) $r['k']; $m = StagingSync::meta($realm, $t);
                $now = StagingSync::hashOf($realm, 'live', $t, $k);
                if ((string) $now !== (string) $r['live_h']) {   // changed on live since the preview: a late conflict, left as live has it
                    $run['late'][] = [$realm, $t, $k];
                    $c->prepare('UPDATE ' . StagingSync::q($s, 'pub_journal') . ' SET note = ? WHERE id = ?')->execute(['late: changed on live since the preview', $jid]);
                    $c->prepare('UPDATE ' . StagingSync::q($s, 'stg_plan') . ' SET state = ? WHERE id = ?')->execute(['late', $r['id']]);
                    continue;
                }
                $f = self::dec((array) json_decode((string) $r['fields'], true));
                [$f] = StagingIds::row($f, self::colRefs($realm, $t), self::jsonRefs($realm, $t), self::textCols($realm, $t), self::flatMap($map));
                $newK = '';
                if ($r['do_what'] === 'delete') {
                    [$w, $p] = self::where($m['pk'], $k);
                    $c->prepare('DELETE FROM ' . StagingSync::q($l, $t) . ' WHERE ' . $w)->execute($p);
                } elseif ($r['do_what'] === 'update') {
                    unset($f[$m['pk'][0]]);
                    foreach ($m['gen'] as $g) unset($f[$g]);
                    if ($f) {
                        [$w, $p] = self::where($m['pk'], $k);
                        $set = implode(', ', array_map(fn ($x) => '`' . str_replace('`', '', $x) . '` = ?', array_keys($f)));
                        $c->prepare('UPDATE ' . StagingSync::q($l, $t) . ' SET ' . $set . ' WHERE ' . $w)->execute(array_merge(array_values($f), $p));
                    }
                } else {   // insert: a row made on staging takes its number from live
                    foreach ($m['gen'] as $g) unset($f[$g]);
                    if ($m['auto']) unset($f[$m['pk'][0]]);
                    $cols = array_keys($f);
                    $c->prepare('INSERT INTO ' . StagingSync::q($l, $t) . ' (' . implode(', ', array_map(fn ($x) => '`' . str_replace('`', '', $x) . '`', $cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($f));
                    $newK = $m['auto'] ? (string) $c->lastInsertId() : $k;
                    if ($m['auto']) $map[$realm][$t][(int) $k] = (int) $newK;
                }
                $after = $r['do_what'] === 'delete' ? null : StagingSync::hashOf($realm, 'live', $t, $newK !== '' ? $newK : $k);
                $c->prepare('UPDATE ' . StagingSync::q($s, 'pub_journal') . ' SET done = 1, after_h = ?, new_k = ? WHERE id = ?')->execute([$after, $newK, $jid]);
                $c->prepare('UPDATE ' . StagingSync::q($s, 'stg_plan') . ' SET state = ? WHERE id = ?')->execute(['done', $r['id']]);
            }
            $c->commit();
        } catch (Throwable $e) {
            if ($c->inTransaction()) $c->rollBack();
            $c->exec('SET @stg_pub = NULL');
            throw $e;
        }
        $c->exec('SET @stg_pub = NULL');
        $run['map'] = $map;
        $run['cursor'] = ['realm' => $ri, 'seq' => (int) end($rows)['seq']];
        return false;
    }

    /** Links between new rows that pointed forward (a row written before the one it points to): rewritten now with the whole map. */
    private static function fixup(PDO $pdo, array &$run): bool
    {
        $flat = self::flatMap($run['map']);
        if (!$flat) return true;
        foreach (StagingSync::pairs() as $realm => [$c, $l, $s]) {
            $st = $c->prepare("SELECT tbl, new_k FROM " . StagingSync::q($s, 'pub_journal') . " WHERE pub = ? AND op = 'insert' AND done = 1 AND undone = 0"); $st->execute([$run['id']]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $j) {
                $t = (string) $j['tbl']; $k = (string) $j['new_k'];
                $row = StagingSync::row($realm, 'live', $t, $k);
                if (!$row) continue;
                [$nr, $n] = StagingIds::row($row, self::colRefs($realm, $t), self::jsonRefs($realm, $t), self::textCols($realm, $t), $flat);
                if (!$n) continue;
                $diff = array_filter($nr, fn ($v, $c2) => !StagingMerge::eq($v, $row[$c2] ?? null), ARRAY_FILTER_USE_BOTH);
                if (!$diff) continue;
                $m = StagingSync::meta($realm, $t);
                $jid = self::journal($run, $realm, $t, $k, 'update', $row, null, '', 0);
                [$w, $p] = self::where($m['pk'], $k);
                $c->exec('SET @stg_pub = 1');
                $c->prepare('UPDATE ' . StagingSync::q($l, $t) . ' SET ' . implode(', ', array_map(fn ($x) => '`' . $x . '` = ?', array_keys($diff))) . ' WHERE ' . $w)->execute(array_merge(array_values($diff), $p));
                $c->exec('SET @stg_pub = NULL');
                $c->prepare('UPDATE ' . StagingSync::q($s, 'pub_journal') . ' SET done = 1, after_h = ? WHERE id = ?')->execute([StagingSync::hashOf($realm, 'live', $t, $k), $jid]);
            }
        }
        return true;
    }

    /** [table => [staging id => live id]] across realms. */
    private static function flatMap(array $map): array
    {
        $out = [];
        foreach ($map as $tables) foreach ($tables as $t => $ids) foreach ($ids as $a => $b) $out[$t][(int) $a] = (int) $b;
        return $out;
    }

    private static function newIdsSoFar(string $pub): array
    {
        $map = [];
        foreach (StagingSync::pairs() as [$c, , $s]) {
            try { $st = $c->prepare("SELECT tbl, k FROM " . StagingSync::q($s, 'stg_plan') . " WHERE pub = ? AND do_what = 'insert'"); $st->execute([$pub]); foreach ($st->fetchAll(PDO::FETCH_NUM) as [$t, $k]) $map[$t][(int) $k] = 0; } catch (Throwable) {}
        }
        return $map;
    }

    private static function colRefs(string $realm, string $t): array
    {
        $o = [];
        foreach (StagingSync::refs()['refs'] as [$r, $tt, $col, $place, $points]) if ($r === $realm && $tt === $t && $place === 'col') $o[$col] = $points;
        return $o;
    }

    private static function jsonRefs(string $realm, string $t): array
    {
        $o = [];
        foreach (StagingSync::refs()['refs'] as [$r, $tt, $col, $place, $points, $path]) if ($r === $realm && $tt === $t && $place === 'json') $o[$col][$path] = $points;
        return $o;
    }

    private static function textCols(string $realm, string $t): array
    {
        $o = [];
        foreach (StagingSync::refs()['refs'] as [$r, $tt, $col, $place]) if ($r === $realm && $tt === $t && $place === 'kind') $o[] = $col;
        return $o;
    }

    private static function where(array $pk, string $k): array
    {
        $vals = count($pk) === 1 ? [$k] : explode(chr(31), $k);
        return [implode(' AND ', array_map(fn ($x) => '`' . str_replace('`', '', $x) . '` = ?', $pk)), $vals];
    }

    // ---- staging's files in the store ---------------------------------------------------------------------------------

    /** Staging's own files (staging/<key>), for the preview (a count) or the step (the keys). */
    public static function storeFiles(PDO $pdo, bool $count = false)
    {
        $p = StagingSync::pairs(); if (!isset($p['main'])) return $count ? 0 : [];
        [$c, , $s] = $p['main'];
        try {
            if ($count) return (int) $c->query('SELECT COUNT(*) FROM ' . StagingSync::q($s, 'staging_s3'))->fetchColumn();
            return array_map('strval', $c->query('SELECT k FROM ' . StagingSync::q($s, 'staging_s3') . ' ORDER BY k')->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable) { return $count ? 0 : []; }
    }

    private static function storeStep(PDO $pdo, array &$run): bool
    {
        $keys = self::storeFiles($pdo);
        $from = (int) ($run['cursor']['file'] ?? 0);
        $part = array_slice($keys, $from, self::FILES);
        if (!$part) return true;
        require_once __DIR__ . '/S3.php';
        if (!S3::on()) { $run['log'][] = 'The file store isn\'t set up here: staging\'s ' . count($keys) . ' files stay on staging.'; return true; }
        $flat = self::flatMap($run['map']);
        foreach ($part as $k) {
            $to = StagingIds::storeKey($k, $flat);
            $kept = '';
            if (S3::size($to) > 0) { $kept = 'published/' . $run['id'] . '/' . $to; if (!S3::copy($to, $kept)) throw new RuntimeException('Couldn\'t keep live\'s copy of ' . $to . '.'); }
            self::journal($run, 'main', 'store', $to, 'store', ['kept' => $kept], null, '', 0);
            if (!S3::copy('staging/' . $k, $to)) throw new RuntimeException('Couldn\'t copy staging\'s ' . $k . ' into the store.');
            [$c, , $s] = StagingSync::pairs()['main'];
            $c->prepare("UPDATE " . StagingSync::q($s, 'pub_journal') . " SET done = 1 WHERE pub = ? AND seq = ?")->execute([$run['id'], $run['seq']]);
        }
        $run['cursor'] = ['file' => $from + count($part)];
        return $from + count($part) >= count($keys);
    }

    // ---- staging back in step --------------------------------------------------------------------------------------

    /** Staging's new rows take their live numbers (staging gives way), then every carried row's base is taken again. */
    private static function inStep(PDO $pdo, array &$run): bool
    {
        foreach ($run['map'] as $realm => $tables) foreach ($tables as $t => $ids) foreach ($ids as $old => $new) {
            if ((int) $old === (int) $new) continue;
            // a row of staging's own already using that number moves out of the way first
            if (StagingSync::row($realm, 'staging', $t, (string) $new) !== null && !isset($ids[(int) $new])) StagingSync::renumber($realm, $t, (int) $new, 'publish');
            StagingSync::renumber($realm, $t, (int) $old, 'publish', (int) $new);
        }
        foreach (StagingSync::pairs() as $realm => [$c, , $s]) {
            // what went live, and what was the same on both: in step now. Test data, late conflicts and what wasn't carried stay
            // as staging's own changes (shown again in the next preview)
            $st = $c->prepare("SELECT DISTINCT tbl, k FROM " . StagingSync::q($s, 'stg_plan') . " WHERE pub = ? AND (state = 'done' OR (do_what = 'keep' AND test = 0 AND state = ''))"); $st->execute([$run['id']]);
            foreach ($st->fetchAll(PDO::FETCH_NUM) as [$t, $k]) {
                $nk = isset($run['map'][$realm][$t][(int) $k]) ? (string) $run['map'][$realm][$t][(int) $k] : (string) $k;
                StagingSync::baseRow($realm, (string) $t, $nk);
            }
            $c->exec('DELETE FROM ' . StagingSync::q($s, 'stg_conflicts') . ' WHERE settled_at IS NOT NULL');
        }
        StagingSync::clearCaches();
        return true;
    }

    private static function finish(PDO $pdo, array $run): array
    {
        $run['state'] = 'done'; $run['finished_at'] = gmdate('c');
        $n = 0; foreach (self::planCounts($run['id']) as $x) $n += $x;
        $run['message'] = 'Published: ' . count($run['releases']) . ' release' . (count($run['releases']) === 1 ? '' : 's') . ', ' . $n . ' change' . ($n === 1 ? '' : 's') . ', ' . count($run['late']) . ' late conflict' . (count($run['late']) === 1 ? '' : 's') . '. '
            . (!empty($run['checks_failed']) ? 'A check on live failed: Turn off or Roll back is your call.' : 'Checks on live passed.');
        $run['log'][] = gmdate('H:i:s') . ' ' . $run['message'];
        self::put($pdo, 'publish', $run);
        self::put($pdo, 'last_publish', ['id' => $run['id'], 'at' => $run['finished_at'], 'until' => gmdate('c', time() + self::KEEP_DAYS * 86400), 'message' => $run['message'], 'releases' => $run['releases']]);
        self::put($pdo, 'to_send', ['pub' => $run['id'], 'items' => self::heldFor($pdo)]);
        self::tell($pdo, $run);
        return $run;
    }

    private static function planCounts(string $pub): array
    {
        $o = [];
        foreach (StagingSync::pairs() as [$c, , $s]) {
            try { $st = $c->prepare("SELECT do_what, COUNT(*) FROM " . StagingSync::q($s, 'stg_plan') . " WHERE pub = ? AND state = 'done' GROUP BY do_what"); $st->execute([$pub]); foreach ($st->fetchAll(PDO::FETCH_NUM) as [$d, $n]) $o[$d] = ($o[$d] ?? 0) + (int) $n; } catch (Throwable) {}
        }
        return $o;
    }

    // ---- undo, backward -------------------------------------------------------------------------------------------------

    /**
     * Undoes a publish from its journal, last write first, a piece at a time (an interrupted undo resumes: each entry is
     * marked as it's undone). Each write is put back only where live still holds what the publish wrote; the rest are
     * listed for Jason. Releases' files go back by their own Roll back; migrations stay (0162).
     */
    public static function undoStep(PDO $pdo, array $run, int $max = 200): array
    {
        require_once __DIR__ . '/Releases.php';
        $all = [];
        foreach (StagingSync::pairs() as $realm => [$c, , $s]) {
            StagingSync::ensure($realm);
            $st = $c->prepare('SELECT * FROM ' . StagingSync::q($s, 'pub_journal') . ' WHERE pub = ? AND done = 1 AND undone = 0 ORDER BY seq DESC LIMIT ' . (int) $max);
            $st->execute([$run['id']]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $j) $all[] = ['realm' => $realm] + $j;
        }
        usort($all, fn ($a, $b) => (int) $b['seq'] <=> (int) $a['seq']);
        $all = array_slice($all, 0, $max);
        foreach ($all as $j) {
            $realm = (string) $j['realm']; [$c, $l, $s] = StagingSync::pairs()[$realm];
            $note = self::undoOne($pdo, $realm, $j, $run);
            $c->prepare('UPDATE ' . StagingSync::q($s, 'pub_journal') . ' SET undone = 1, note = ? WHERE id = ?')->execute([mb_substr($note, 0, 250), $j['id']]);
            if (str_starts_with($note, 'left')) $run['left'][] = $j['tbl'] . ' ' . $j['k'] . ': ' . $note;
        }
        if (count($all) < $max) {
            $rolled = !empty($run['rollback']);
            $run['state'] = $rolled ? 'rolled_back' : 'undone';
            $run['finished_at'] = gmdate('c');
            $left = count($run['left'] ?? []);
            $run['message'] = ($rolled ? 'Rolled back' : 'Publish stopped at ' . ($run['stopped_at'] ?? 'a step') . ' and what it wrote was undone') . ': '
                . ($left ? $left . ' row' . ($left === 1 ? '' : 's') . ' changed on live since, left as live has them and listed below.' : 'live is as it was before.') . ' Migrations stay (they only add).'
                . ($rolled ? '' : ' Fix the cause, Preview again and Publish, or ask our team (support@traversence.com).');
            $run['log'][] = gmdate('H:i:s') . ' ' . $run['message'];
            self::put($pdo, 'publish', $run);
            self::tell($pdo, $run);
        } else self::put($pdo, 'publish', $run);
        return $run;
    }

    /** One journal entry put back. Returns a note: 'undone …' or 'left: …'. */
    private static function undoOne(PDO $pdo, string $realm, array $j, array $run): string
    {
        [$c, $l] = StagingSync::pairs()[$realm];
        $t = (string) $j['tbl']; $op = (string) $j['op'];
        if ($op === 'release') {
            try { Releases::act($pdo, (int) $j['k'], 'back', [], (int) $run['by']); return 'undone: release #' . $j['k'] . ' rolled back'; }
            catch (Throwable $e) { return 'left: release #' . $j['k'] . ' couldn\'t be rolled back (' . $e->getMessage() . ')'; }
        }
        if ($op === 'store') {
            require_once __DIR__ . '/S3.php';
            $b = (array) json_decode((string) $j['before_row'], true);
            if (!empty($b['kept'])) return S3::copy((string) $b['kept'], (string) $j['k']) ? 'undone: live\'s file put back' : 'left: couldn\'t put live\'s file back';
            S3::delete((string) $j['k']); return 'undone: the file removed';
        }
        $m = StagingSync::meta($realm, $t);
        if (!$m) return 'left: the table isn\'t there';
        $k = $op === 'insert' ? (string) $j['new_k'] : (string) $j['k'];
        $now = StagingSync::hashOf($realm, 'live', $t, $k);
        $before = $j['before_row'] !== null ? self::dec((array) json_decode((string) $j['before_row'], true)) : null;
        [$w, $p] = self::where($m['pk'], $k);
        $c->exec('SET @stg_pub = 1');
        try {
            if ($op === 'insert') {
                if ($now === null) return 'undone: already gone';
                if ($now !== (string) $j['after_h']) return 'left: changed on live since the publish';
                $c->prepare('DELETE FROM ' . StagingSync::q($l, $t) . ' WHERE ' . $w)->execute($p);
                return 'undone: the added row removed';
            }
            if ($op === 'update') {
                if ($before === null) return 'left: no copy of the row before';
                if ($now !== (string) $j['after_h']) return $now === null ? 'left: removed on live since' : 'left: changed on live since the publish';
                $f = $before; foreach ($m['gen'] as $g) unset($f[$g]);
                $c->prepare('UPDATE ' . StagingSync::q($l, $t) . ' SET ' . implode(', ', array_map(fn ($x) => '`' . str_replace('`', '', $x) . '` = ?', array_keys($f))) . ' WHERE ' . $w)->execute(array_merge(array_values($f), $p));
                return 'undone: the row put back';
            }
            if ($op === 'delete') {
                if ($before === null) return 'left: no copy of the row before';
                if ($now !== null) return 'left: the number is in use on live again';
                $f = $before; foreach ($m['gen'] as $g) unset($f[$g]);
                $c->prepare('INSERT INTO ' . StagingSync::q($l, $t) . ' (' . implode(', ', array_map(fn ($x) => '`' . str_replace('`', '', $x) . '`', array_keys($f))) . ') VALUES (' . implode(', ', array_fill(0, count($f), '?')) . ')')->execute(array_values($f));
                return 'undone: the removed row put back';
            }
        } finally { $c->exec('SET @stg_pub = NULL'); }
        return 'left: nothing to undo';
    }

    /** Roll back the whole publish (14 days): files back, added rows removed, changed and removed rows put back (each only if live hasn't changed it since). */
    public static function rollBack(PDO $pdo, int $uid): array
    {
        require_once __DIR__ . '/Releases.php';
        [$ok, $why] = Releases::can('releases.rollback');
        if (!$ok) throw new RuntimeException($why);
        $run = self::state($pdo, 'publish');
        if (!$run || ($run['state'] ?? '') !== 'done') throw new RuntimeException('There\'s no finished publish to roll back.');
        if (strtotime((string) $run['finished_at']) < time() - self::KEEP_DAYS * 86400) throw new RuntimeException('That publish is over ' . self::KEEP_DAYS . ' days old: its copies are gone. Ask our team (support@traversence.com).');
        $run['state'] = 'undoing'; $run['rollback'] = true; $run['rollback_by'] = $uid; $run['left'] = [];
        $run['log'][] = gmdate('H:i:s') . ' Roll back asked.';
        self::put($pdo, 'publish', $run);
        return $run;
    }

    /** Carry on a publish that stopped (the server restarted, a time limit): from the step it was on. */
    public static function carryOn(PDO $pdo): array
    {
        $run = self::state($pdo, 'publish');
        if (!$run || !in_array($run['state'] ?? '', ['stopped', 'running'], true)) throw new RuntimeException('No stopped publish to carry on.');
        if ($run['state'] === 'stopped') { $run['state'] = 'running'; $run['log'][] = gmdate('H:i:s') . ' Carried on.'; self::put($pdo, 'publish', $run); }
        return $run;
    }

    // ---- held effects and To send now -------------------------------------------------------------------------------

    /** What staging held (the ledger): never sent by Publish; offered after, on To send now. */
    public static function heldFor(PDO $pdo): array
    {
        $p = StagingSync::pairs(); if (!isset($p['main'])) return [];
        [$c, , $s] = $p['main'];
        try { return $c->query("SELECT id, kind, service, target, subject, ref, created_at FROM " . StagingSync::q($s, 'staging_held') . " WHERE replayed_at IS NULL AND kind = 'mail' ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable) { return []; }
    }

    /** To send now: one held email, sent on live to its real recipient (Jason's choice, one at a time), or dropped. */
    public static function sendNow(PDO $pdo, int $heldId, bool $send, int $uid): string
    {
        $p = StagingSync::pairs(); [$c, , $s] = $p['main'];
        $st = $c->prepare('SELECT * FROM ' . StagingSync::q($s, 'staging_held') . ' WHERE id = ?'); $st->execute([$heldId]);
        $h = $st->fetch(PDO::FETCH_ASSOC);
        if (!$h) throw new RuntimeException('That held item isn\'t there any more.');
        $c->prepare('UPDATE ' . StagingSync::q($s, 'staging_held') . ' SET replayed_at = NOW(), replayed_to = ? WHERE id = ?')->execute([$send ? 'live:' . $uid : 'dropped:' . $uid, $heldId]);
        if (!$send) return 'Dropped: it won\'t be sent.';
        if ($h['kind'] !== 'mail') return 'Only emails can be sent from here: this one is marked done.';
        require_once __DIR__ . '/Mailer.php';
        Mailer::send((string) $h['target'], (string) $h['subject'], (string) $h['body_text']);
        return 'Sent to ' . $h['target'] . ' from the live site.';
    }

    // ---- the run's lock and its email -----------------------------------------------------------------------------

    private static function lock(PDO $pdo): bool { try { return (int) $pdo->query("SELECT GET_LOCK('tv_staging_publish', 0)")->fetchColumn() === 1; } catch (Throwable) { return true; } }
    private static function unlock(PDO $pdo): void { try { $pdo->query("SELECT RELEASE_LOCK('tv_staging_publish')"); } catch (Throwable) {} }

    /** An email to whoever started it (a system email), with the time in their zone. */
    private static function tell(PDO $pdo, array $run): void
    {
        try {
            $uid = (int) ($run['by'] ?? 0);
            if (!$uid || PHP_SAPI === 'cli' && getenv('TV_TEST')) return;
            $st = $pdo->prepare('SELECT email FROM users WHERE id = ?'); $st->execute([$uid]);
            $to = (string) $st->fetchColumn();
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;
            $tz = 'UTC'; try { if ((string) ($run['tz'] ?? '') !== '') { new DateTimeZone((string) $run['tz']); $tz = (string) $run['tz']; } } catch (Throwable) {}
            $when = (new DateTimeImmutable('now', new DateTimeZone($tz)))->format('M j, g:i A T');
            require_once __DIR__ . '/Mailer.php';
            Mailer::send($to, ($run['state'] === 'done' ? 'Staging is published' : 'Publish of staging stopped'), (string) ($run['message'] ?? '') . "\n\n" . $when
                . "\n\nSee it in System Configuration → Staging: https://traversence.com/admin/config/staging (Carry on, Roll back and To send now are there).\n\nSomething not right? Write to support@traversence.com.");
        } catch (Throwable $e) { error_log('staging publish email: ' . $e->getMessage()); }
    }
}
