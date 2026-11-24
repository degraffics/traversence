<?php
// app/lib/ControlLedger.php — the System Control Board's sensors (decisions/0171 §4, release 2a; Jason, 2026-11-18:
// "neither of them, especially START, does not include endpoints or a way to see what data is being captured and or
// stored"). Every call a worker makes to the site (/api/crawl/*, /api/video/*) leaves one row in control_ledger: what it
// handed out, received, stored, updated, sent to Review and refused (refusals counted by reason), its answer code, how
// long it took and the crawler run it belonged to. The ids of the records it touched go to control_records (at most
// control_records_per_call; beyond that, the count), so a run opens to what it brought in.
//
// How: the endpoints' shared _auth.php calls begin() once the worker is signed in. It keeps the answer in a buffer, and
// after the endpoint has answered (a shutdown function, before the buffer is sent) the row is written from the request,
// the answer and what the libraries marked with touched() on the way (IntakeStager::stage: a Review item or a listing;
// CrawlerIngest::upsertMetadataKey: a listing's field). No endpoint changes; a new one is measured by itself. It never
// changes the answer and never stops the call: a failure here is logged and forgotten.
// Ids and counts only, never a person's name, email, phone or a page's text. Kept control_ledger_days and
// control_records_days (Thresholds), trimmed in small batches. Release 2b draws it on the board.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class ControlLedger
{
    /** Request keys that aren't records (the worker's log, its own checks, the run report's items). */
    private const NOT_RECORDS = ['log', 'checked', 'items', 'calls', 'issues', 'keys', 'holds', 'probes', 'errors', 'plan'];
    /** Answer keys => what they count. */
    private const COUNTS = [
        'stored' => ['stored', 'added', 'kept', 'inserted', 'imported', 'auto_imported', 'created', 'saved', 'loaded'],
        'review' => ['staged', 'to_review', 'review'],
        'updated' => ['updated', 'filled', 'applied', 'refreshed', 'changed', 'merged'],
        'refused' => ['refused', 'already_known', 'skipped', 'rejected', 'dropped', 'junk', 'held', 'duplicates'],
    ];

    private static ?array $on = null;      // the call being measured: [pdo, endpoint, t0, method]
    private static array $touched = [];    // [kind, id, what]
    private static int $over = 0;          // touched beyond the cap: counted only
    private static array $at = [];         // kind:id => its line in $touched

    /** Starts measuring this call (from _auth.php once the worker is signed in). */
    public static function begin(PDO $pdo, string $endpoint): void
    {
        if (self::$on !== null) return;
        self::$on = [$pdo, mb_substr($endpoint, 0, 40), microtime(true), (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')];
        ob_start();
        register_shutdown_function([self::class, 'finish']);
    }

    /** A record this call touched: kind (listing, review, …), its id, what happened (stored, updated: phone, to Review). */
    public static function touched(string $kind, int $id, string $what): void
    {
        if (self::$on === null || $id <= 0) return;
        // one line per record: a listing whose fields were written one by one is one "updated: phone, website"; stored
        // (new) outranks updated, and a field kept because a person entered it stays named
        $k = $kind . ':' . $id;
        if (isset(self::$at[$k])) {
            $i = self::$at[$k]; $was = self::$touched[$i][2];
            if (str_starts_with($what, 'refused') && !str_starts_with($was, 'refused')) $what = $was . '; ' . substr($what, 9);   // a field kept on a record otherwise written
            elseif (str_starts_with($what, 'stored') || str_starts_with($was, 'stored')) $what = 'stored';
            elseif (str_starts_with($what, 'updated: ') && str_starts_with($was, 'updated: ')) $what = $was . (str_contains($was, substr($what, 9)) ? '' : ', ' . substr($what, 9));
            self::$touched[$i][2] = mb_substr($what, 0, 60);
            return;
        }
        if (count(self::$touched) >= 2000) { self::$over++; return; }   // a hard ceiling; the setting trims further when written
        self::$at[$k] = count(self::$touched);
        self::$touched[] = [mb_substr($kind, 0, 20), $id, mb_substr($what, 0, 60)];
    }

    /** Is a call being measured (for libraries that would do extra work only then)? */
    public static function active(): bool { return self::$on !== null; }

    private static function isList(array $a): bool
    {
        $i = 0;
        foreach ($a as $k => $_) { if ($k !== $i++) return false; }
        return true;
    }

    /** Items in a JSON object's top-level lists of records. */
    private static function listed($j): int
    {
        if (!is_array($j)) return 0;
        $n = 0;
        foreach ($j as $k => $v) {
            if (!is_string($k) || in_array($k, self::NOT_RECORDS, true) || !is_array($v) || !$v || !self::isList($v)) continue;
            if (is_array($v[0]) || is_string($v[0])) $n += count($v);
        }
        return $n;
    }

    /** The answer's counts: [stored, review, updated, refused, reasons]. */
    public static function counts($answer): array
    {
        $out = ['stored' => 0, 'review' => 0, 'updated' => 0, 'refused' => 0];
        $reasons = [];
        if (!is_array($answer)) return $out + ['reasons' => $reasons];
        // a batch's receipt (W0's /api/crawl/index answers {ok, receipt: {received, stored, rejected: [...], ...}}): its stored, and
        // its rejected as refused; its junk, skipped and new are inside stored (stored = received - rejected), never added again.
        // Read top level only, it counted 0 of every 250 (the W0 alarm, 2026-11-24, seen 7,646 times)
        if (is_array($answer['receipt'] ?? null) && isset($answer['receipt']['received'])) {
            $rc = $answer['receipt'];
            $out['stored'] = max(0, (int) ($rc['stored'] ?? 0));
            $rej = $rc['rejected'] ?? [];
            $out['refused'] = is_array($rej) ? count($rej) : max(0, (int) $rej);
            if ($out['refused'] > 0) {
                foreach ((array) $rej as $r) { $why = is_array($r) ? (string) ($r[1] ?? 'rejected') : 'rejected'; $why = strtolower(str_replace('_', ' ', $why)); $reasons[$why] = ($reasons[$why] ?? 0) + 1; }
            }
            return $out + ['reasons' => $reasons, 'receipt' => true];
        }
        foreach (self::COUNTS as $col => $keys) foreach ($keys as $k) {
            if (!array_key_exists($k, $answer)) continue;
            $v = $answer[$k];
            $n = is_int($v) ? $v : (is_array($v) ? count($v) : (is_numeric($v) ? (int) $v : 0));
            if ($n <= 0) continue;
            $out[$col] += $n;
            if ($col === 'refused') $reasons[str_replace('_', ' ', $k)] = $n;
        }
        return $out + ['reasons' => $reasons];
    }

    /** After the endpoint has answered: its row, and the records it touched. */
    /**
     * The alarms read at the call (decisions/0171 §5, release 3a): a server error is a trip on the process the endpoint
     * serves, cleared by its next good answer; more outcomes than records received is a wrong count (a warning).
     */
    private static function alarms(PDO $pdo, string $ep, int $code, int $received, array $c, array $reasons, int $ms = 0): void
    {
        try {
            require_once __DIR__ . '/ControlAlarms.php'; require_once __DIR__ . '/ControlRegister.php';
            $proc = ControlAlarms::procOfEndpoint($pdo, $ep);
            $at = gmdate('H:i') . ' UTC';
            // the call itself, opened in the Ledger table (routes, 2026-11-19): never the list of every call
            $callOf = function (string $where) use ($pdo, $ep): array { try { $st = $pdo->prepare('SELECT MAX(id) FROM control_ledger WHERE endpoint = ? AND ' . $where); $st->execute([$ep]); $id = (int) $st->fetchColumn(); } catch (Throwable $x) { $id = 0; }
                return $id ? ['The call (' . '#' . $id . ')', 'peek', '/admin/config/ledger#open=config/ledger:' . $id] : ['Its calls', 'peek', '/admin/config/ledger?q=' . rawurlencode($ep)]; };
            // a refusal before our code (ModSecurity's 406, the bot check's 409): our endpoint refusing the worker, held at once for a
            // person (02 row 23); a later good answer proves it's let through again
            if (in_array($code, [406, 409], true)) { require_once __DIR__ . '/crawler/CrawlService.php'; CrawlService::blocked($pdo, $ep, $code, implode('; ', $reasons)); }
            elseif ($code >= 200 && $code < 300) ControlAlarms::clear($pdo, 'ep:' . $ep . ':blocked', 'It answered ' . $code . ' at ' . $at . ': the worker is let through again.');
            // its title in plain words (02 §4.8 rows 21, 22): "/api/<ep> keeps failing"
            if ($code >= 500) ControlAlarms::raise($pdo, 'ep:' . $ep . ':5xx', $proc, 'trip', 'crash', '/api/' . $ep . ' keeps failing',
                'The worker\'s call failed on our side; what it sent wasn\'t kept and it will send it again.', 'Open the failing call (beside this): its reason and the records it carried. Fix the cause on the site; the worker sends it again, and its next good answer clears this. Check tests it now.', mb_substr(implode('; ', $reasons) ?: 'answered ' . $code, 0, 255), [$callOf('code >= 500'), ['Check', 'check', $proc]]);
            else ControlAlarms::clear($pdo, 'ep:' . $ep . ':5xx', 'It answered ' . $code . ' at ' . $at . '.');
            $sorted = (int) $c['stored'] + (int) $c['updated'] + (int) $c['review'] + (int) $c['refused'];
            if ($received > 0 && $sorted > $received) ControlAlarms::raise($pdo, 'ep:' . $ep . ':count', $proc, 'warning', 'count', '/api/' . $ep . ' counted more than it received',
                'Its record of what it stored, updated, sent to Review or refused doesn\'t add up, so its numbers on the board can\'t be trusted.', 'Open the call (beside this): it shows what it received and how each was counted; the counting in its endpoint needs a fix.', 'received ' . $received . ', counted ' . $sorted, [$callOf('n_received > 0')]);
            // fewer accounted for than received is as wrong as more (02 row 75; g2 R55: only "more" was read): records unaccounted for.
            // Only a call that answered (2xx): a refusal or a server error accounts for nothing by design
            elseif ($received > 0 && $sorted < $received && $code >= 200 && $code < 300) ControlAlarms::raise($pdo, 'ep:' . $ep . ':count', $proc, 'warning', 'count', '/api/' . $ep . ': received ' . $received . ', accounted for ' . $sorted,
                'Its numbers can\'t be trusted until this is fixed: ' . ($received - $sorted) . ' record(s) it received weren\'t stored, updated, sent to Review or refused.', 'Open the call (beside this): it shows what it received and how each was counted; the counting in its endpoint needs a fix.', 'received ' . $received . ', counted ' . $sorted, [$callOf('n_received > 0')]);
            // a receipt read right closes the old misreading in true words: W0 never lost what it sent (the Design reviewer, 2026-11-24)
            elseif ($received > 0) ControlAlarms::clear($pdo, 'ep:' . $ep . ':count', !empty($c['receipt'])
                ? 'Cleared: our ledger misread ' . ($ep === 'crawl/index' ? 'W0' : '/api/' . $ep) . '\'s receipt (fixed in loads-reply). Its counts add up as of ' . $at . '.'
                : 'Its counts added up at ' . $at . '.');
            // slow (0171 §5, release 3c): longer than its usual time (the middle of its last 50 good calls, 20 at least) times
            // control_slow_factor, and over a second; a warning (never a trip, 4e). A call that carries records is judged per
            // record (release 5a, 2026-11-19: W1's results endpoint took 3.3 s for a big batch, not a slow one)
            if ($code < 500 && $ms > 1000) {
                $per = $received > 0;
                $st = $pdo->prepare($per ? 'SELECT ms / n_received FROM control_ledger WHERE endpoint = ? AND code < 500 AND n_received > 0 ORDER BY id DESC LIMIT 1, 50'
                    : 'SELECT ms FROM control_ledger WHERE endpoint = ? AND code < 500 ORDER BY id DESC LIMIT 1, 50'); $st->execute([$ep]);
                $past = array_map('floatval', $st->fetchAll(PDO::FETCH_COLUMN));
                // fewer than 20 calls of history: judged against its declared speed gate (02 row 19; g1 row 9: nothing was said)
                if (count($past) < 20 && !$per) {
                    require_once __DIR__ . '/ControlEngine.php'; $gate = ControlEngine::gateMs($pdo, $ep);
                    if ($ms > $gate) ControlAlarms::raise($pdo, 'ep:' . $ep . ':slow', $proc, 'warning', 'slow', '/api/' . $ep . ' is slow',
                        'It took ' . number_format($ms / 1000, 1) . ' s against its limit of ' . number_format($gate / 1000, 1) . ' s (too few calls yet for a usual time): the worker waits, so its runs do less. Nothing is stopped; this clears by itself when it answers in time.', 'Open the slow call (beside this): its time and the records it carried; a slow query on the site is the usual cause.', number_format($ms) . ' ms (limit ' . number_format($gate) . ' ms)', [$callOf('code < 500 AND ms >= ' . (int) $ms)]);
                    else ControlAlarms::clear($pdo, 'ep:' . $ep . ':slow', 'It answered within its limit at ' . $at . '.');
                }
                if (count($past) >= 20) {
                    sort($past); $usual = $past[intdiv(count($past), 2)]; $now = $per ? $ms / $received : (float) $ms;
                    require_once __DIR__ . '/Thresholds.php'; $f = (float) (Thresholds::get($pdo, 'control_slow_factor') ?? 3);
                    $unit = fn (float $v) => $per ? number_format($v, $v < 10 ? 1 : 0) . ' ms a record' : number_format($v / 1000, 1) . ' s';
                    if ($now > max($per ? 0 : 1000, $usual * $f)) ControlAlarms::raise($pdo, 'ep:' . $ep . ':slow', $proc, 'warning', 'slow', '/api/' . $ep . ' is slow',
                        'It took ' . $unit($now) . ', against its usual ' . $unit($usual) . ($per ? ' (' . number_format($received) . ' records in ' . number_format($ms / 1000, 1) . ' s)' : '') . ': the worker waits, so its runs do less. Nothing is stopped; this clears by itself when it answers at its usual speed.', 'Open the slow call (beside this): its time and the records it carried; a slow query on the site is the usual cause.', number_format($ms) . ' ms' . ($per ? ' for ' . number_format($received) . ' records' : '') . ' (usual ' . $unit($usual) . ')', [$callOf('code < 500 AND ms >= ' . (int) $ms)]);
                    else ControlAlarms::clear($pdo, 'ep:' . $ep . ':slow', 'It answered at its usual speed at ' . $at . '.');
                }
            } elseif ($code < 500) ControlAlarms::clear($pdo, 'ep:' . $ep . ':slow', 'It answered at its usual speed at ' . $at . '.');
        } catch (Throwable $x) { error_log('ControlLedger alarms: ' . $x->getMessage()); }
    }

    public static function finish(): void
    {
        if (self::$on === null) return;
        [$pdo, $ep, $t0, $method] = self::$on;
        self::$on = null;
        try {
            $ms = (int) round((microtime(true) - $t0) * 1000);
            $code = (int) (http_response_code() ?: 200);
            $answer = json_decode((string) ob_get_contents(), true);
            $request = $method === 'GET' ? null : json_decode((string) file_get_contents('php://input'), true);
            $c = self::counts($answer);
            // what the libraries marked is the record of what happened; the answer's counts fill what they didn't mark
            $by = ['stored' => 0, 'review' => 0, 'updated' => 0, 'refused' => 0];
            foreach (self::$touched as [, , $what]) { $w = strtok($what, ':'); if (isset($by[$w])) $by[$w]++; }
            if ($by['stored'] + $by['review'] + $by['updated'] > 0) foreach (['stored', 'review', 'updated'] as $col) $c[$col] = $by[$col];
            if ($by['refused'] > 0) { $c['refused'] += $by['refused']; $c['reasons']['kept what a person entered'] = $by['refused']; }
            $received = self::listed($request);
            $sent = $method === 'GET' || $received === 0 ? self::listed($answer) : 0;
            $reasons = [];
            foreach ($c['reasons'] as $k => $n) $reasons[] = $k . ': ' . $n;
            if ($code >= 400 && is_array($answer) && isset($answer['error'])) $reasons[] = 'answered ' . $code . ': ' . (is_string($answer['error']) ? $answer['error'] : json_encode($answer['error']));
            // what an endpoint set aside with its error (Verify::lease, 2026-11-18): kept, so a hidden failure shows on the board
            if (is_array($answer) && is_array($answer['set_aside'] ?? null)) foreach (array_slice($answer['set_aside'], 0, 5) as $sa) $reasons[] = 'set aside #' . (int) ($sa['row_id'] ?? 0) . ': ' . (string) ($sa['error'] ?? '');
            $runId = null;
            if (str_starts_with($ep, 'crawl/')) {
                try { $r = $pdo->query("SELECT id FROM crawl_runs WHERE status = 'running' AND started_at >= NOW() - INTERVAL 40 MINUTE ORDER BY id DESC LIMIT 1")->fetchColumn(); $runId = $r !== false ? (int) $r : null; } catch (Throwable $x) {}
            }
            $cap = 500;
            try { require_once __DIR__ . '/Thresholds.php'; $cap = max(0, (int) Thresholds::get($pdo, 'control_records_per_call')); } catch (Throwable $x) {}
            $keep = array_slice(self::$touched, 0, $cap);
            $pdo->prepare('INSERT INTO control_ledger (at, endpoint, method, code, ms, run_id, n_sent, n_received, n_stored, n_updated, n_review, n_refused, reasons, records) VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$ep, mb_substr($method, 0, 6), $code, $ms, $runId, $sent, $received, $c['stored'], $c['updated'], $c['review'], $c['refused'], $reasons ? mb_substr(implode('; ', $reasons), 0, 500) : null, count(self::$touched) + self::$over]);
            $id = (int) $pdo->lastInsertId();
            self::alarms($pdo, $ep, $code, $received, $c, $reasons, $ms);
            if ($keep) {
                $rows = []; $args = [];
                foreach ($keep as [$kind, $ref, $what]) { $rows[] = '(?, UTC_TIMESTAMP(), ?, ?, ?)'; array_push($args, $id, $kind, $ref, $what); }
                $pdo->prepare('INSERT INTO control_records (ledger_id, at, kind, ref_id, what) VALUES ' . implode(', ', $rows))->execute($args);
            }
            if (random_int(1, 200) === 1) self::trim($pdo);
        } catch (Throwable $x) {
            if (!str_contains($x->getMessage(), "doesn't exist")) error_log('ControlLedger: ' . $x->getMessage());   // before its database update has run: nothing to record yet
        } finally {
            self::$touched = []; self::$over = 0; self::$at = [];
        }
    }

    /** Rows past their days, in small batches. */
    public static function trim(PDO $pdo): void
    {
        require_once __DIR__ . '/Thresholds.php';
        $d1 = max(1, (int) Thresholds::get($pdo, 'control_ledger_days'));
        $d2 = max(1, (int) Thresholds::get($pdo, 'control_records_days'));
        $pdo->exec('DELETE FROM control_records WHERE at < UTC_TIMESTAMP() - INTERVAL ' . $d2 . ' DAY LIMIT 5000');
        $pdo->exec('DELETE FROM control_ledger WHERE at < UTC_TIMESTAMP() - INTERVAL ' . $d1 . ' DAY LIMIT 5000');
    }

    /** A crawler run's calls and the records they touched (for the board and the crawler console, release 2b). */
    public static function forRun(PDO $pdo, int $runId): array
    {
        $st = $pdo->prepare('SELECT * FROM control_ledger WHERE run_id = ? ORDER BY id'); $st->execute([$runId]);
        $calls = $st->fetchAll(PDO::FETCH_ASSOC);
        $recs = [];
        if ($calls) {
            $ids = implode(',', array_map(fn ($c) => (int) $c['id'], $calls));
            $recs = $pdo->query('SELECT ledger_id, kind, ref_id, what FROM control_records WHERE ledger_id IN (' . $ids . ') ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        }
        return ['calls' => $calls, 'records' => $recs];
    }

    /** What a record's line says it was: stored, updated, review or refused (its first word). */
    public static function whatKind(string $what): string
    {
        $w = strtolower((string) strtok($what, ':'));
        return in_array($w, ['stored', 'updated', 'review', 'refused'], true) ? $w : (str_starts_with($w, 'sent to review') ? 'review' : 'other');
    }

    /** How many records each crawler run touched: [run_id => n]. */
    public static function runCounts(PDO $pdo, array $runIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $runIds)));
        if (!$ids) return [];
        try { return array_map('intval', $pdo->query('SELECT l.run_id, COUNT(*) FROM control_records r JOIN control_ledger l ON l.id = r.ledger_id WHERE l.run_id IN (' . implode(',', $ids) . ') GROUP BY l.run_id')->fetchAll(PDO::FETCH_KEY_PAIR)); }
        catch (Throwable $x) { return []; }
    }

    /**
     * The records a workflow's calls touched between two UTC times ("W1 today"), newest first, at most $n: each with its
     * run, the endpoint and when. The workflow is read from the endpoint (the register: a task's endpoint is its workflow's).
     */
    public static function records(PDO $pdo, string $wf, string $from, string $to, int $n = 1000): array
    {
        require_once __DIR__ . '/ControlAlarms.php';
        $st = $pdo->prepare('SELECT r.kind, r.ref_id, r.what, r.at, l.run_id, l.endpoint FROM control_records r JOIN control_ledger l ON l.id = r.ledger_id
                             WHERE r.at >= ? AND r.at < ? ORDER BY r.id DESC LIMIT ' . max(1, min(5000, $n * 3)));
        $st->execute([$from, $to]);
        $of = []; $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ep = (string) $r['endpoint'];
            $of[$ep] = $of[$ep] ?? ControlAlarms::procOfEndpoint($pdo, $ep);
            if ($wf !== '' && $of[$ep] !== 'wf:' . $wf) continue;
            $out[] = $r; if (count($out) >= $n) break;
        }
        return $out;
    }
}
