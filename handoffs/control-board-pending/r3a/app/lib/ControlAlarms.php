<?php
// app/lib/ControlAlarms.php — the System Control Board's annunciator (decisions/0171 §5, §7; release 3a). One alarm list
// for the whole system, by level: a trip (stopped, crashed, can't run what's switched on), a warning (slow, retrying,
// near a limit, a wrong count) or a notice. An alarm is raised the moment its reading goes wrong (the ledger, a crawler
// run's report, a service failing; silence by the 5-minute watch), and the same problem again updates the open alarm,
// never a second. It clears by itself when its reading is good again, saying how. Acknowledging stops the repeat email;
// the alarm stays open until its reading is good. A trip is emailed to the Founder at once through the one dispatcher
// (Notify::send, kind alarm.trip, decisions/0172); a warning still unacknowledged after 24 hours, as alarm.warning.
// Ids, words and readings only: nothing about a person.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class ControlAlarms
{
    public const LEVELS = ['trip' => 'Trip', 'warning' => 'Warning', 'notice' => 'Notice'];
    /** What kind of problem (0171 §5's table). */
    public const KINDS = ['slow' => 'Slow', 'retry' => 'Retrying', 'crash' => 'Crashed', 'held' => 'Held or out', 'limit' => 'Near a limit',
                          'count' => 'Wrong count', 'silent' => 'Gone quiet', 'check' => 'Its check'];
    private const RANK = ['notice' => 0, 'warning' => 1, 'trip' => 2];
    private static bool $ready = false;

    public static function ensure(PDO $pdo): void
    {
        if (self::$ready) return;
        $pdo->exec("CREATE TABLE IF NOT EXISTS control_alarms (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            akey VARCHAR(120) NOT NULL, proc VARCHAR(60) NOT NULL DEFAULT '', level VARCHAR(10) NOT NULL, kind VARCHAR(12) NOT NULL,
            title VARCHAR(200) NOT NULL, means VARCHAR(400) NOT NULL DEFAULT '', todo VARCHAR(400) NOT NULL DEFAULT '', reading VARCHAR(255) NOT NULL DEFAULT '',
            fix VARCHAR(400) NOT NULL DEFAULT '', n_times INT UNSIGNED NOT NULL DEFAULT 1, first_at DATETIME NOT NULL, last_at DATETIME NOT NULL,
            ack_uid INT UNSIGNED NULL, ack_name VARCHAR(80) NULL, ack_at DATETIME NULL, cleared_at DATETIME NULL, cleared_how VARCHAR(255) NULL,
            mailed_at DATETIME NULL, KEY open_key (akey, cleared_at), KEY proc (proc), KEY cleared (cleared_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ready = true;
    }

    /** The open alarm with this key, or null. */
    public static function openOf(PDO $pdo, string $key): ?array
    {
        self::ensure($pdo);
        $st = $pdo->prepare('SELECT * FROM control_alarms WHERE akey = ? AND cleared_at IS NULL ORDER BY id DESC LIMIT 1'); $st->execute([$key]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Raises an alarm, or updates the open one with the same key (its count, last seen, words, and a higher level if it
     * got worse). $fix: [label, action, id] a button the board and Pulse press in place ('' none). Returns its id. Never
     * throws: a failing alarm must not break what raised it.
     */
    public static function raise(PDO $pdo, string $key, string $proc, string $level, string $kind, string $title, string $means = '', string $todo = '', string $reading = '', array $fix = []): int
    {
        try {
            self::ensure($pdo);
            $level = isset(self::LEVELS[$level]) ? $level : 'warning'; $kind = isset(self::KINDS[$kind]) ? $kind : 'check';
            $cut = fn (string $s, int $n) => mb_substr(trim($s), 0, $n);
            $fx = $fix ? json_encode(array_slice($fix, 0, 3), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
            $o = self::openOf($pdo, $key);
            if ($o) {
                $lv = self::RANK[$level] > self::RANK[(string) $o['level']] ? $level : (string) $o['level'];
                $pdo->prepare('UPDATE control_alarms SET n_times = n_times + 1, last_at = UTC_TIMESTAMP(), level = ?, title = ?, means = ?, todo = ?, reading = ?, fix = ? WHERE id = ?')
                    ->execute([$lv, $cut($title, 200), $cut($means, 400), $cut($todo, 400), $cut($reading, 255), $cut($fx, 400), (int) $o['id']]);
                if ($lv === 'trip' && $o['level'] !== 'trip') self::mail($pdo, (int) $o['id']);   // it got worse: a trip now
                return (int) $o['id'];
            }
            $pdo->prepare('INSERT INTO control_alarms (akey, proc, level, kind, title, means, todo, reading, fix, first_at, last_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())')
                ->execute([$cut($key, 120), $cut($proc, 60), $level, $kind, $cut($title, 200), $cut($means, 400), $cut($todo, 400), $cut($reading, 255), $cut($fx, 400)]);
            $id = (int) $pdo->lastInsertId();
            if ($level === 'trip') self::mail($pdo, $id);
            return $id;
        } catch (Throwable $x) { error_log('control alarm ' . $key . ': ' . $x->getMessage()); return 0; }
    }

    /** Clears the open alarm with this key, saying how. Returns whether one was open. Never throws. */
    public static function clear(PDO $pdo, string $key, string $how): bool
    {
        try {
            self::ensure($pdo);
            $st = $pdo->prepare('UPDATE control_alarms SET cleared_at = UTC_TIMESTAMP(), cleared_how = ? WHERE akey = ? AND cleared_at IS NULL');
            $st->execute([mb_substr($how, 0, 255), $key]);
            return $st->rowCount() > 0;
        } catch (Throwable $x) { error_log('control alarm clear ' . $key . ': ' . $x->getMessage()); return false; }
    }

    /** Clears every open alarm whose key starts with $prefix except those in $keep (the readings still bad). */
    public static function clearOthers(PDO $pdo, string $prefix, array $keep, string $how): int
    {
        $n = 0;
        try {
            self::ensure($pdo);
            $st = $pdo->prepare("SELECT akey FROM control_alarms WHERE cleared_at IS NULL AND akey LIKE ?"); $st->execute([addcslashes($prefix, '%_') . '%']);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $k) if (!in_array($k, $keep, true) && self::clear($pdo, (string) $k, $how)) $n++;
        } catch (Throwable $x) {}
        return $n;
    }

    /** Acknowledged by a person: stops the repeat email; it stays open until its reading is good. */
    public static function ack(PDO $pdo, int $id, int $uid, string $who): bool
    {
        self::ensure($pdo);
        $st = $pdo->prepare('UPDATE control_alarms SET ack_uid = ?, ack_name = ?, ack_at = UTC_TIMESTAMP() WHERE id = ? AND ack_at IS NULL');
        $st->execute([$uid ?: null, mb_substr($who, 0, 80) ?: null, $id]);
        return $st->rowCount() > 0;
    }

    /**
     * The list: open alarms, worst first, then the trips that cleared before anyone saw them (kept 24 hours, 0171 §7).
     * Each row as stored, with 'fix' decoded.
     */
    public static function list(PDO $pdo, int $n = 100): array
    {
        try {
            self::ensure($pdo);
            $rows = $pdo->query("SELECT * FROM control_alarms WHERE cleared_at IS NULL
                                 OR (level = 'trip' AND ack_at IS NULL AND cleared_at >= UTC_TIMESTAMP() - INTERVAL 24 HOUR)
                                 ORDER BY cleared_at IS NOT NULL, FIELD(level, 'trip', 'warning', 'notice'), last_at DESC LIMIT " . max(1, min(500, $n)))->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) $r['fix'] = $r['fix'] !== '' ? (json_decode((string) $r['fix'], true) ?: []) : [];
            return $rows;
        } catch (Throwable $x) { return []; }
    }

    /** For the light: open trips and warnings, and the trips that cleared unseen. */
    public static function counts(PDO $pdo): array
    {
        $z = ['trip' => 0, 'warning' => 0, 'notice' => 0, 'unseen' => 0];
        try {
            self::ensure($pdo);
            foreach ($pdo->query('SELECT level, COUNT(*) FROM control_alarms WHERE cleared_at IS NULL GROUP BY level')->fetchAll(PDO::FETCH_KEY_PAIR) as $l => $c) if (isset($z[$l])) $z[$l] = (int) $c;
            $z['unseen'] = (int) $pdo->query("SELECT COUNT(*) FROM control_alarms WHERE level = 'trip' AND ack_at IS NULL AND cleared_at >= UTC_TIMESTAMP() - INTERVAL 24 HOUR")->fetchColumn();
        } catch (Throwable $x) {}
        return $z;
    }

    /** A process's open alarms (for its row on the board). */
    public static function ofProc(PDO $pdo, string $proc): array
    {
        try { self::ensure($pdo); $st = $pdo->prepare('SELECT * FROM control_alarms WHERE proc = ? AND cleared_at IS NULL ORDER BY FIELD(level, \'trip\', \'warning\', \'notice\'), last_at DESC'); $st->execute([$proc]); return $st->fetchAll(PDO::FETCH_ASSOC); }
        catch (Throwable $x) { return []; }
    }

    /**
     * The email (0171 §7, through 0172's one dispatcher): a trip at once; a warning unacknowledged 24 hours. Sent only
     * when the dispatcher knows the kind (Notify::KINDS has alarm.trip / alarm.warning); until then nothing is sent and
     * the alarm still stands on the board. Marks it mailed so it goes once.
     */
    private static function mail(PDO $pdo, int $id): void
    {
        try {
            $st = $pdo->prepare('SELECT * FROM control_alarms WHERE id = ?'); $st->execute([$id]); $a = $st->fetch(PDO::FETCH_ASSOC);
            if (!$a || $a['mailed_at'] !== null) return;
            $kind = $a['level'] === 'trip' ? 'alarm.trip' : 'alarm.warning';
            if (!is_file(__DIR__ . '/Notify.php')) return;
            require_once __DIR__ . '/Notify.php';
            if (!class_exists('Notify') || !defined('Notify::KINDS') || !isset(Notify::KINDS[$kind]) || !method_exists('Notify', 'send')) return;
            $words = (string) $a['title'] . "\n\n" . ($a['means'] !== '' ? 'What it means: ' . $a['means'] . "\n" : '') . ($a['todo'] !== '' ? 'What to do: ' . $a['todo'] . "\n" : '')
                   . ($a['reading'] !== '' ? 'Reading: ' . $a['reading'] . "\n" : '') . "\nOpen the board: https://traversence.com/admin/config/control#cb-alarms";
            Notify::send($pdo, $kind, 'founder', 'alarm:' . $id, $words);
            $pdo->prepare('UPDATE control_alarms SET mailed_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
        } catch (Throwable $x) { error_log('control alarm mail ' . $id . ': ' . $x->getMessage()); }
    }

    /**
     * The watch (0171 §5, every 5 minutes: control_tick): what doesn't call in. W0 Indexer silent while it's switched on, a
     * crawler run past its time plus 5 minutes, a site job whose last run stopped, a job run that never finished; and the
     * warnings due their 24-hour email. Each raised, or cleared when its reading is good. Returns what it did, in words.
     */
    public static function tick(PDO $pdo): string
    {
        self::ensure($pdo);
        $did = [];
        require_once __DIR__ . '/Automation.php';
        $masterOn = Automation::master($pdo) === null;
        // W0 Indexer: its check-in (every minute or so while it runs)
        try {
            require_once __DIR__ . '/crawler/Frontier.php';
            $beat = (int) (Frontier::lastBeat($pdo)['at'] ?? 0); $on = $masterOn && Automation::wfOff($pdo, 'W0') === null;
            if ($on && $beat && time() - $beat > 15 * 60) { self::raise($pdo, 'silent:w0', 'wk:w0-indexer', 'trip', 'silent', 'W0 Indexer has gone quiet', 'It hasn\'t checked in for ' . (int) floor((time() - $beat) / 60) . ' minutes while it\'s switched on: no addresses are being logged.', 'Open its Railway service: if it crashed or its plan ran out, redeploy it; then press Check.', 'last check-in ' . gmdate('M j H:i', $beat) . ' UTC', ['Check', 'check', 'wk:w0-indexer']); $did[] = 'W0 quiet'; }
            elseif ($beat && time() - $beat <= 15 * 60 && self::clear($pdo, 'silent:w0', 'It checked in at ' . gmdate('H:i', $beat) . ' UTC.')) $did[] = 'W0 back';
            elseif (!$on) self::clear($pdo, 'silent:w0', 'It was switched off.');
        } catch (Throwable $x) {}
        // a crawler run that never reported: its time (RUN_LIMIT, 30 minutes) plus 5
        try {
            $late = $pdo->query("SELECT id, started_at FROM crawl_runs WHERE status = 'running' AND started_at < NOW() - INTERVAL 35 MINUTE ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($late) { self::raise($pdo, 'silent:run', 'wk:crawler', 'trip', 'silent', 'A crawler run never reported (#' . (int) $late['id'] . ')', 'It started over 35 minutes ago and hasn\'t said how it ended: it was stopped, or it crashed on Railway.', 'Open the crawler on Railway and read the run\'s last log lines; the site marks it abandoned.', 'started ' . (string) $late['started_at'], ['Check', 'check', 'wk:crawler']); $did[] = 'run quiet'; }
            elseif (self::clear($pdo, 'silent:run', 'No run is overdue (' . gmdate('H:i') . ' UTC).')) $did[] = 'run cleared';
        } catch (Throwable $x) {}
        // site jobs: the last run of each, stopped or never finished
        try {
            $t = Database::t('job_runs'); $bad = []; $stuck = [];
            foreach ($pdo->query("SELECT r.job, r.ok, r.note, r.started_at, r.finished_at FROM $t r JOIN (SELECT job, MAX(id) id FROM $t WHERE started_at > NOW() - INTERVAL 2 DAY GROUP BY job) m ON m.id = r.id")->fetchAll(PDO::FETCH_ASSOC) as $j) {
                $job = (string) $j['job'];
                if ($j['ok'] !== null && (int) $j['ok'] === 0) { $bad[] = 'job:' . $job; self::raise($pdo, 'job:' . $job, 'job:' . $job, 'trip', 'crash', 'The job "' . $job . '" stopped', 'Its last run ended with a problem; what it does waits until it runs well again.', 'Read its note in System Configuration → Scheduled tasks and fix the cause; it clears by itself when it next runs well.', mb_substr((string) $j['note'], 0, 255)); }
                elseif ($j['finished_at'] === null && strtotime((string) $j['started_at']) < time() - 7200) { $stuck[] = 'job:' . $job . ':stuck'; self::raise($pdo, 'job:' . $job . ':stuck', 'job:' . $job, 'warning', 'slow', 'The job "' . $job . '" never finished', 'Its run started over 2 hours ago and never said it ended: it was killed, or it hangs.', 'Look at the server\'s error log for it; the next run starts as usual.', 'started ' . (string) $j['started_at']); }
            }
            $n = self::clearOthers($pdo, 'job:', array_merge($bad, $stuck), 'Its last run ended well (' . gmdate('H:i') . ' UTC).');
            if ($bad) $did[] = count($bad) . ' job(s) stopped'; if ($n) $did[] = $n . ' job alarm(s) cleared';
        } catch (Throwable $x) {}
        $m = self::mailStale($pdo); if ($m) $did[] = $m . ' warning(s) emailed';
        return $did ? implode('; ', $did) . '.' : 'Nothing new.';
    }

    /** Warnings open and unacknowledged 24 hours: emailed once (the watch calls this). */
    public static function mailStale(PDO $pdo): int
    {
        $n = 0;
        try {
            self::ensure($pdo);
            foreach ($pdo->query("SELECT id FROM control_alarms WHERE level = 'warning' AND cleared_at IS NULL AND ack_at IS NULL AND mailed_at IS NULL AND first_at <= UTC_TIMESTAMP() - INTERVAL 24 HOUR")->fetchAll(PDO::FETCH_COLUMN) as $id) { self::mail($pdo, (int) $id); $n++; }
        } catch (Throwable $x) {}
        return $n;
    }
}
