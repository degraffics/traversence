<?php
// app/lib/ControlIncidents.php — Incident Reports (the state model's P12, 02 §2.6, §4 group F; decisions/0175's note of
// 2026-11-21, Jason: "Warning id and trip id with explanations that I can reference"; "Trips only"). Every trip opens one,
// IR-####, or joins an open one: the same signature (its component's host and kind) within the correlation window
// (02 §0a O1: 15 minutes). It keeps its members (the trips, with their warning ids W-#### and trip ids T-####), its
// timeline, its radius (Held / On a backup / Unaffected), its evidence and its root cause, "Unconfirmed" until a person
// writes it. It closes when its last member closes. The outside heartbeat's records (raised while the site was down) are
// imported with source 'heartbeat' and numbered then (ruling 6). Kept 365 days (Q19). Never throws to a caller.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

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

    /** Records kept KEEP_DAYS (Q19), trimmed in small batches. */
    public static function trim(PDO $pdo): void
    {
        try { self::ensure($pdo); $pdo->exec('DELETE FROM control_incidents WHERE closed_at IS NOT NULL AND closed_at < UTC_TIMESTAMP() - INTERVAL ' . self::KEEP_DAYS . ' DAY LIMIT 500'); } catch (Throwable $x) {}
    }
}
