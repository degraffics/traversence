<?php
// app/lib/ControlIncidents.php — Incident Reports (the state model's P12, 02 §2.6, §4 group F; decisions/0175's note of
// 2026-11-21, Jason: "Warning id and trip id with explanations that I can reference"; "Trips only"). Every trip opens one,
// IR-####, or joins an open one: the same signature (its component's host and kind) within the correlation window
// (02 §0a O1: 15 minutes). It keeps its members (the trips, with their warning ids W-#### and trip ids T-####), its
// timeline, its radius (Held / On a backup / Unaffected), its evidence and its root cause, "Unconfirmed" until a person
// writes it. It closes when its last member closes. The outside heartbeat's records (raised while the site was down) are
// imported with source 'heartbeat' and numbered then (ruling 6). Kept 365 days (Q19). Never throws to a caller.
declare(strict_types=1);


final class ControlIncidents
{
    public const WINDOW_MIN = 15;
    public const KEEP_DAYS = 365;

    private static bool $ready = false;

    public static function ensure(PDO $pdo): void
    {
        if (self::$ready) return;
        $pdo->exec("CREATE TABLE IF NOT EXISTS control_incidents (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            opened_at DATETIME NOT NULL, closed_at DATETIME NULL, host VARCHAR(120) NOT NULL DEFAULT '', signature VARCHAR(160) NOT NULL DEFAULT '',
            title VARCHAR(200) NOT NULL DEFAULT '', radius TEXT NULL, evidence TEXT NULL, timeline MEDIUMTEXT NULL,
            root_cause VARCHAR(600) NULL, root_cause_state VARCHAR(12) NOT NULL DEFAULT 'unconfirmed', root_cause_by VARCHAR(80) NULL,
            source VARCHAR(10) NOT NULL DEFAULT 'board', KEY open_sig (signature, closed_at), KEY opened (opened_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ready = true;
    }

    /** IR-0007 from 7. */
    public static function number(int $id): string { return 'IR-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT); }
    /** T-0123 / W-0123 from an alarm's id: the trip and the warning it grew from share the alarm's number. */
    public static function tripId(int $alarmId): string { return 'T-' . str_pad((string) $alarmId, 4, '0', STR_PAD_LEFT); }
    public static function warningId(int $alarmId): string { return 'W-' . str_pad((string) $alarmId, 4, '0', STR_PAD_LEFT); }

    /** The signature that correlates trips: the component's host (or the component) and the alarm's kind. */
    public static function signature(PDO $pdo, array $a): array
    {
        $comp = (string) ($a['component'] ?? '');
        $host = $comp;
        if (preg_match('/^svc:issue:(\d+)$/', (string) $a['akey'], $m)) { try { $st = $pdo->prepare('SELECT host FROM service_issues WHERE id = ?'); $st->execute([(int) $m[1]]); $h = (string) $st->fetchColumn(); if ($h !== '') $host = $h; } catch (Throwable $x) {} }
        elseif (str_starts_with($comp, 'ep:') || str_starts_with($comp, 'site:')) $host = 'our site';
        return [$host, mb_substr($host . '|' . (string) $a['kind'], 0, 160)];
    }

    /** A trip opens its incident, or joins an open one with its signature inside the window. Returns the incident id. */
    public static function forTrip(PDO $pdo, int $alarmId): int
    {
        try {
            self::ensure($pdo);
            $st = $pdo->prepare('SELECT * FROM control_alarms WHERE id = ?'); $st->execute([$alarmId]); $a = $st->fetch(PDO::FETCH_ASSOC);
            if (!$a) return 0;
            if (!empty($a['incident_id'])) return (int) $a['incident_id'];
            [$host, $sig] = self::signature($pdo, $a);
            $st = $pdo->prepare('SELECT id FROM control_incidents WHERE signature = ? AND closed_at IS NULL AND opened_at >= UTC_TIMESTAMP() - INTERVAL ' . self::WINDOW_MIN . ' MINUTE ORDER BY id DESC LIMIT 1');
            $st->execute([$sig]); $id = (int) $st->fetchColumn();
            if ($id === 0) {
                $pdo->prepare('INSERT INTO control_incidents (opened_at, host, signature, title, evidence) VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?)')
                    ->execute([mb_substr($host, 0, 120), $sig, mb_substr((string) $a['title'], 0, 200), mb_substr((string) $a['reading'], 0, 2000)]);
                $id = (int) $pdo->lastInsertId();
                self::note($pdo, $id, 'Opened at ' . self::tripId($alarmId) . ' (' . self::warningId($alarmId) . '): ' . (string) $a['title']);
            } else self::note($pdo, $id, 'Joined (same host and kind within ' . self::WINDOW_MIN . ' min): ' . self::tripId($alarmId) . ': ' . (string) $a['title']);
            $pdo->prepare('UPDATE control_alarms SET incident_id = ? WHERE id = ?')->execute([$id, $alarmId]);
            return $id;
        } catch (Throwable $x) { error_log('control incident: ' . $x->getMessage()); return 0; }
    }

    /** A line on an incident's timeline (UTC). */
    public static function note(PDO $pdo, int $id, string $what): void
    {
        if ($id <= 0) return;
        try {
            self::ensure($pdo);
            $st = $pdo->prepare('SELECT timeline FROM control_incidents WHERE id = ?'); $st->execute([$id]);
            $t = json_decode((string) $st->fetchColumn(), true); $t = is_array($t) ? $t : [];
            $t[] = [gmdate('Y-m-d H:i:s'), mb_substr($what, 0, 400)];
            $pdo->prepare('UPDATE control_incidents SET timeline = ? WHERE id = ?')->execute([json_encode(array_slice($t, -300), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $id]);
        } catch (Throwable $x) {}
    }

    /** What the incident holds, recorded at its trip and as members join: [held words]. */
    public static function radius(PDO $pdo, int $id, array $held): void
    {
        if ($id <= 0) return;
        try {
            $st = $pdo->prepare('SELECT radius FROM control_incidents WHERE id = ?'); $st->execute([$id]);
            $r = json_decode((string) $st->fetchColumn(), true); $r = is_array($r) ? $r : ['held' => [], 'on_backup' => []];
            foreach ($held as $h) if (!in_array($h, $r['held'], true)) $r['held'][] = $h;
            $pdo->prepare('UPDATE control_incidents SET radius = ? WHERE id = ?')->execute([json_encode($r, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $id]);
        } catch (Throwable $x) {}
    }

    /** A member closed: the incident closes with its last member. */
    public static function memberClosed(PDO $pdo, int $alarmId, string $how): void
    {
        try {
            $st = $pdo->prepare('SELECT incident_id FROM control_alarms WHERE id = ?'); $st->execute([$alarmId]); $id = (int) $st->fetchColumn();
            if ($id <= 0) return;
            self::note($pdo, $id, self::tripId($alarmId) . ' closed: ' . $how);
            $st = $pdo->prepare('SELECT COUNT(*) FROM control_alarms WHERE incident_id = ? AND cleared_at IS NULL'); $st->execute([$id]);
            if ((int) $st->fetchColumn() === 0) { $pdo->prepare('UPDATE control_incidents SET closed_at = UTC_TIMESTAMP() WHERE id = ? AND closed_at IS NULL')->execute([$id]); self::note($pdo, $id, 'Closed: its last trip closed.'); }
        } catch (Throwable $x) {}
    }

    /** A person writes the root cause (it becomes Confirmed). */
    public static function setCause(PDO $pdo, int $id, string $cause, string $who): bool
    {
        $cause = trim($cause); if ($cause === '' || $id <= 0) return false;
        self::ensure($pdo);
        $st = $pdo->prepare("UPDATE control_incidents SET root_cause = ?, root_cause_state = 'confirmed', root_cause_by = ? WHERE id = ?");
        $st->execute([mb_substr($cause, 0, 600), mb_substr($who, 0, 80), $id]);
        self::note($pdo, $id, 'Root cause written by ' . $who . ': ' . mb_substr($cause, 0, 300));
        return $st->rowCount() > 0;
    }

    public static function get(PDO $pdo, int $id): ?array
    {
        self::ensure($pdo);
        $st = $pdo->prepare('SELECT * FROM control_incidents WHERE id = ?'); $st->execute([$id]); $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return null;
        $m = $pdo->prepare('SELECT id, akey, title, level, first_at, tripped_at, cleared_at, cleared_how FROM control_alarms WHERE incident_id = ? ORDER BY id'); $m->execute([$id]);
        $r['members'] = $m->fetchAll(PDO::FETCH_ASSOC);
        $r['timeline'] = json_decode((string) $r['timeline'], true) ?: [];
        $r['radius'] = json_decode((string) $r['radius'], true) ?: ['held' => [], 'on_backup' => []];
        return $r;
    }

    /** The newest incidents for the board's list. */
    public static function list(PDO $pdo, int $n = 50): array
    {
        try { self::ensure($pdo); return $pdo->query('SELECT * FROM control_incidents ORDER BY id DESC LIMIT ' . max(1, min(500, $n)))->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $x) { return []; }
    }

    /** where the outside heartbeat keeps its records (.github/workflows/heartbeat.yml, tools/heartbeat/heartbeat.py) */
    public const HEARTBEAT_SOURCE = 'https://raw.githubusercontent.com/degraffics/traversence/main/site-audit/heartbeat/records.json';
    /** the heartbeat's parts, in the words of the state model's rows 53–55 */
    public const HEARTBEAT_PARTS = ['page' => 'Page load', 'database' => 'The databases', 'cron' => 'The site\'s scheduled jobs'];

    /**
     * When the site is back, the outside heartbeat's records (raised while the site was down) are imported and numbered
     * then (02 §0a ruling 6): each record becomes a trip row (T-####) with its real times, closed as Recovered once the
     * heartbeat saw the site answer again, and its Incident Report (IR-####, source 'heartbeat'). Nothing is sent: the
     * heartbeat's own sender already emailed the trip and its Recovered (no push while the host was gone). Each record is
     * imported once (its alarm key holds the record's id). $body is the file's text (tests); otherwise it's read from
     * HEARTBEAT_SOURCE, at most every 15 minutes. Returns how many were imported.
     */
    public static function importHeartbeat(PDO $pdo, ?string $body = null): int
    {
        try {
            if ($body === null) {
                require_once __DIR__ . '/PageCache.php';
                require_once __DIR__ . '/StagingGate.php';
                if (StagingGate::hold(self::HEARTBEAT_SOURCE, 'the outside heartbeat\'s records') !== null) return 0;   // every outside call asks first (R13)
                $body = PageCache::remember($pdo, 'control:heartbeat', 900, static function () {
                    $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true, 'header' => "User-Agent: Traversence-board\r\n"]]);
                    $b = @file_get_contents(self::HEARTBEAT_SOURCE, false, $ctx);
                    return is_string($b) ? $b : '';
                });
            }
            $d = json_decode((string) $body, true);
            if (!is_array($d) || !is_array($d['records'] ?? null)) return 0;
            self::ensure($pdo);
            require_once __DIR__ . '/ControlAlarms.php';
            ControlAlarms::ensure($pdo);
            $n = 0;
            foreach ($d['records'] as $r) {
                $hid = preg_replace('/[^A-Za-z0-9-]/', '', (string) ($r['id'] ?? ''));
                $part = (string) ($r['part'] ?? '');
                $open = strtotime((string) ($r['opened_at'] ?? '')) ?: 0;
                if ($hid === '' || !isset(self::HEARTBEAT_PARTS[$part]) || $open === 0) continue;
                // a record still open on the heartbeat's side waits: the heartbeat closes it when it sees the site answer
                $close = !empty($r['closed_at']) ? (strtotime((string) $r['closed_at']) ?: 0) : 0;
                if ($close === 0) continue;
                $akey = mb_substr('hb:' . $part . ':' . $hid, 0, 120);
                $st = $pdo->prepare('SELECT id FROM control_alarms WHERE akey = ? LIMIT 1'); $st->execute([$akey]);
                if ($st->fetchColumn()) continue;
                $name = self::HEARTBEAT_PARTS[$part];
                $min = max(1, (int) round(($close - $open) / 60));
                $title = $name . ': raised while the site was down, by the outside heartbeat';
                $how = 'Recovered: ' . $name . ' was down ' . $min . ' min; the site answers again (the outside heartbeat\'s check).';
                $o = gmdate('Y-m-d H:i:s', $open); $c = gmdate('Y-m-d H:i:s', $close);
                $pdo->prepare("INSERT INTO control_alarms (akey, proc, level, kind, title, means, reading, first_at, last_at, tripped_at, cleared_at, cleared_how, component)
                    VALUES (?, ?, 'trip', 'down', ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$akey, 'site:' . $part, mb_substr($title, 0, 200), mb_substr((string) ($r['what'] ?? ''), 0, 400),
                        mb_substr(count((array) ($r['checks'] ?? [])) . ' checks from outside', 0, 255), $o, $c, $o, $c, mb_substr($how, 0, 255), 'site:' . $part]);
                $aid = (int) $pdo->lastInsertId();
                $tl = [];
                foreach (array_slice((array) ($r['checks'] ?? []), 0, 40) as $ck) $tl[] = ['at' => (string) ($ck['at'] ?? ''), 'what' => mb_substr((string) ($ck['url'] ?? '') . ' answered ' . (string) ($ck['status'] ?? '—'), 0, 200)];
                $tl[] = ['at' => gmdate('c', $close), 'what' => $how];
                $pdo->prepare("INSERT INTO control_incidents (opened_at, closed_at, host, signature, title, evidence, timeline, source) VALUES (?, ?, 'our site', ?, ?, ?, ?, 'heartbeat')")
                    ->execute([$o, $c, mb_substr('our site|down|' . $hid, 0, 160), mb_substr($title, 0, 200), mb_substr((string) ($r['what'] ?? ''), 0, 2000), json_encode($tl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
                $pdo->prepare('UPDATE control_alarms SET incident_id = ? WHERE id = ?')->execute([(int) $pdo->lastInsertId(), $aid]);
                $n++;
            }
            return $n;
        } catch (Throwable $x) { return 0; }
    }

    /** Records kept KEEP_DAYS (Q19), trimmed in small batches. */
    public static function trim(PDO $pdo): void
    {
        try { self::ensure($pdo); $pdo->exec('DELETE FROM control_incidents WHERE closed_at IS NOT NULL AND closed_at < UTC_TIMESTAMP() - INTERVAL ' . self::KEEP_DAYS . ' DAY LIMIT 500'); } catch (Throwable $x) {}
    }
}
