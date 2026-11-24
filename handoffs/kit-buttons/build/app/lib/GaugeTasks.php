<?php
// app/lib/GaugeTasks.php — what a person does from a gauge's panel (the gauge panel, approved by Jason 2026-11-23;
// handoffs/gauge-panel.md; CLAUDE.md "Every action is a task"). One table, each site's own (staging class: own):
//   - fix: Request a fix on one item (an issue, a thing not built, a check that doesn't reach it). With no direct action, the
//     item is sent to a build: the request is kept here, named for its gauge ("the Discovery build"), shown on the row as
//     "Requested: the … build has it · who · when" with Undo, reported by health.php so the daily fetch writes build-requests.json and the
//     session manager queues it under the owning session (its ruling on Q1, 2026-11-23). It clears by itself once the
//     item's check passes: the item no longer stands where it was sent from (Jason: "once fixed it should clear the issue").
//   - scope / target: Jason's own decisions on a gauge (gauge-lifecycle §3, §8 decided 2's note): Approve the scope moves
//     a development gauge out of Planning; Approve the target sets a runtime gauge's target to the one proposed. Each read by
//     ControlGauges over gauges.json at once, and reported by health.php so gauges.py records it (the ruling on Q3).
// Every press, and every Undo, is in the board's logbook too (ControlLog). Who is a staff member's name, never a member's.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ControlLog.php';

final class GaugeTasks
{
    public const KINDS = ['fix', 'scope', 'target'];
    /** An Undo is offered for a day; after that the row's line stands (Undo stays in the logbook's history only). */
    public const UNDO_HOURS = 24;
    private static bool $ready = false;

    public static function ensure(PDO $pdo): void
    {
        if (self::$ready) return;
        $pdo->exec("CREATE TABLE IF NOT EXISTS control_gauge_tasks (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, at DATETIME NOT NULL,
            tz VARCHAR(64) NOT NULL DEFAULT '', gauge VARCHAR(80) NOT NULL, item VARCHAR(255) NOT NULL DEFAULT '', kind VARCHAR(10) NOT NULL,
            what VARCHAR(400) NOT NULL DEFAULT '', bucket VARCHAR(10) NOT NULL DEFAULT '', value VARCHAR(255) NOT NULL DEFAULT '',
            who VARCHAR(80) NOT NULL DEFAULT '', uid INT NOT NULL DEFAULT 0, undone_at DATETIME NULL, cleared_at DATETIME NULL,
            KEY gauge_item (gauge, item), KEY kind_at (kind, at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ready = true;
    }

    private static function gaugeOk(string $g): bool { return (bool) preg_match('/^(dev|rt):[A-Za-z0-9_-]{1,60}$/', $g); }

    /**
     * Request a fix on one item: the request kept, and logged. $bucket: where the item stood (fail|build|check), so it clears when
     * it no longer stands there. $build: the gauge's name for the line ("Discovery"). Returns the new row's id.
     */
    public static function fix(PDO $pdo, string $gauge, string $item, string $bucket, string $what, string $build, string $who, int $uid, string $tz = ''): int
    {
        self::ensure($pdo);
        if (!self::gaugeOk($gauge)) throw new RuntimeException('Which gauge?');
        $item = trim($item); if ($item === '') throw new RuntimeException('Which item?');
        if (!in_array($bucket, ['fail', 'build', 'check'], true)) throw new RuntimeException('That item has nothing to fix.');
        if ($who === '') throw new RuntimeException('Only a staff member can send a fix.');
        $open = self::openFor($pdo, $gauge, $item);
        if ($open) return (int) $open['id'];   // pressed twice: the one request stands
        $pdo->prepare('INSERT INTO control_gauge_tasks (at, tz, gauge, item, kind, what, bucket, value, who, uid) VALUES (UTC_TIMESTAMP(), ?, ?, ?, \'fix\', ?, ?, ?, ?, ?)')
            ->execute([self::tz($tz), $gauge, mb_substr($item, 0, 255), mb_substr(trim($what), 0, 400), $bucket, mb_substr($build, 0, 255), mb_substr($who, 0, 80), $uid]);
        $id = (int) $pdo->lastInsertId();
        ControlLog::add($pdo, 'control', '', 'Fix requested: ' . $item . ': ' . trim($what) . ', for the ' . $build . ' build', $who, '', $tz);
        return $id;
    }

    /** Approve the scope (dev) or the target (rt, with its value): Jason's decision, kept and logged. Returns the row's id. */
    public static function approve(PDO $pdo, string $gauge, string $kind, string $value, string $title, string $who, int $uid, string $tz = ''): int
    {
        self::ensure($pdo);
        if (!self::gaugeOk($gauge) || !in_array($kind, ['scope', 'target'], true)) throw new RuntimeException('Which gauge?');
        if ($kind === 'scope' && !str_starts_with($gauge, 'dev:')) throw new RuntimeException('A scope is approved on a development gauge.');
        if ($kind === 'target' && (!str_starts_with($gauge, 'rt:') || trim($value) === '')) throw new RuntimeException('Which target?');
        if ($who === '') throw new RuntimeException('Only a staff member can approve.');
        $have = self::approvals($pdo)[$gauge][$kind] ?? null;
        if ($have) return (int) $have['id'];
        $pdo->prepare('INSERT INTO control_gauge_tasks (at, tz, gauge, item, kind, what, value, who, uid) VALUES (UTC_TIMESTAMP(), ?, ?, \'\', ?, ?, ?, ?, ?)')
            ->execute([self::tz($tz), $gauge, $kind, mb_substr($title, 0, 400), mb_substr(trim($value), 0, 255), mb_substr($who, 0, 80), $uid]);
        $id = (int) $pdo->lastInsertId();
        ControlLog::add($pdo, 'control', '', ($kind === 'scope' ? 'Approved the scope of ' : 'Approved the target of ') . $title . ($kind === 'target' ? ': ' . trim($value) : ''), $who, '', $tz);
        return $id;
    }

    /** Undo (an open request at any age, an approval within a day): the row is marked undone (kept for the record), and logged. */
    public static function undo(PDO $pdo, int $id, string $who, string $tz = ''): string
    {
        self::ensure($pdo);
        $st = $pdo->prepare('SELECT * FROM control_gauge_tasks WHERE id = ?'); $st->execute([$id]); $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r || $r['undone_at'] !== null) throw new RuntimeException('It was already undone, or it isn\'t here any more.');
        if ($r['cleared_at'] !== null) throw new RuntimeException('It has already cleared: its check passed, so there\'s nothing to undo.');
        // an open request can be withdrawn at any age (nothing was done yet; past a day it's waiting, never stuck); an approval for a day
        if ($r['kind'] !== 'fix' && strtotime($r['at'] . ' UTC') < time() - self::UNDO_HOURS * 3600) throw new RuntimeException('Undo is offered for a day; this one is older. It\'s in the logbook.');
        $pdo->prepare('UPDATE control_gauge_tasks SET undone_at = UTC_TIMESTAMP() WHERE id = ? AND undone_at IS NULL')->execute([$id]);
        $what = $r['kind'] === 'fix' ? 'the fix for ' . $r['item'] : ($r['kind'] === 'scope' ? 'the scope approval of ' . $r['what'] : 'the target approval of ' . $r['what']);
        ControlLog::add($pdo, 'control', '', 'Undid ' . $what, $who, '', $tz);
        return $r['kind'] === 'fix' ? 'Undone: the fix request is withdrawn.' : 'Undone: the approval is taken back.';
    }

    /** The open fix request for one item, or null. */
    public static function openFor(PDO $pdo, string $gauge, string $item): ?array
    {
        self::ensure($pdo);
        $st = $pdo->prepare("SELECT * FROM control_gauge_tasks WHERE gauge = ? AND item = ? AND kind = 'fix' AND undone_at IS NULL AND cleared_at IS NULL ORDER BY id DESC LIMIT 1");
        $st->execute([$gauge, mb_substr($item, 0, 255)]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * One gauge's fix rows, by item: open ones, and those cleared in the last day (the row says "Cleared: its check passed").
     * $now: item name => where it stands now (fail|build|check|ok). An open request whose item no longer stands where it was
     * sent from is cleared here, at once (Jason: "once fixed it should clear the issue"), and logged.
     */
    public static function forGauge(PDO $pdo, string $gauge, array $now = []): array
    {
        self::ensure($pdo);
        $st = $pdo->prepare("SELECT * FROM control_gauge_tasks WHERE gauge = ? AND kind = 'fix' AND undone_at IS NULL AND (cleared_at IS NULL OR cleared_at > UTC_TIMESTAMP() - INTERVAL 1 DAY) ORDER BY id");
        $st->execute([$gauge]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($r['cleared_at'] === null && isset($now[$r['item']]) && $now[$r['item']] !== $r['bucket']) {
                $pdo->prepare('UPDATE control_gauge_tasks SET cleared_at = UTC_TIMESTAMP() WHERE id = ? AND cleared_at IS NULL')->execute([(int) $r['id']]);
                $r['cleared_at'] = gmdate('Y-m-d H:i:s');
                ControlLog::add($pdo, 'clear', '', 'Cleared: ' . $r['item'] . ' (its check passed)', 'the system');
            }
            $out[$r['item']] = $r;
        }
        return $out;
    }

    /** Approvals in force: gauge => kind => [id, at, who, value, tz]. Never throws (a board that can't read them shows the file's). */
    public static function approvals(PDO $pdo): array
    {
        try {
            self::ensure($pdo);
            $out = [];
            foreach ($pdo->query("SELECT id, at, tz, gauge, kind, value, who FROM control_gauge_tasks WHERE kind IN ('scope','target') AND undone_at IS NULL ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r)
                $out[$r['gauge']][$r['kind']] = ['id' => (int) $r['id'], 'at' => (string) $r['at'], 'tz' => (string) $r['tz'], 'who' => (string) $r['who'], 'value' => (string) $r['value']];
            return $out;
        } catch (Throwable $x) { error_log('gauge tasks: ' . $x->getMessage()); return []; }
    }

    /** For health.php: open fix requests (the build queue) and approvals in force. Items, words and times: no person's name (0145 §1). */
    public static function report(PDO $pdo): array
    {
        try {
            self::ensure($pdo);
            $req = [];
            foreach ($pdo->query("SELECT id, at, gauge, item, what, bucket, value FROM control_gauge_tasks WHERE kind = 'fix' AND undone_at IS NULL AND cleared_at IS NULL ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r)
                $req[] = ['id' => (int) $r['id'], 'at_utc' => str_replace(' ', 'T', (string) $r['at']) . 'Z', 'gauge' => $r['gauge'], 'build' => $r['value'], 'item' => $r['item'], 'what' => $r['what'], 'was' => $r['bucket']];
            $ap = [];
            foreach (self::approvals($pdo) as $g => $ks) foreach ($ks as $k => $a) $ap[] = ['gauge' => $g, 'kind' => $k, 'value' => $a['value'], 'at_utc' => str_replace(' ', 'T', $a['at']) . 'Z'];
            return ['requests' => $req, 'approvals' => $ap];
        } catch (Throwable $x) { return ['requests' => [], 'approvals' => [], 'error' => $x->getMessage()]; }
    }

    private static function tz(string $tz): string { return preg_match('~^[A-Za-z_]+(?:/[A-Za-z0-9_+\-]+){0,2}$~', $tz) ? $tz : ''; }
}
