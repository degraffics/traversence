<?php
// app/lib/PulseViews.php — a person's saved views in Pulse (design/table-standard.md, Jason's note of 2026-11-20, item 5,
// and its answers: kept "With my account"). A view is a name and the mix of Pulse's filters it stands for: the list
// (Inbox, Pinned, Follow up, Archived, a folder), what to show (a view of the stream, or the bell), the senders, the levels
// and Group by. Owner-only: every read and write names the person. Pulse's other choices stay on the device, as before.
declare(strict_types=1);

final class PulseViews
{
    public const MAX = 30;              // views a person can keep
    public const KEYS = ['list', 'show', 'from', 'sev', 'group'];
    private const GROUPS = ['', 'from', 'src', 'sev'];

    public static function ready(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok === null) { try { $pdo->query('SELECT 1 FROM pulse_views LIMIT 0'); $ok = true; } catch (Throwable $e) { $ok = false; } }
        return $ok;
    }

    /** This person's views, by name: [{id, name, filters}]. */
    public static function of(PDO $pdo, int $uid): array
    {
        if (!self::ready($pdo)) return [];
        $st = $pdo->prepare('SELECT id, name, filters FROM pulse_views WHERE user_id = ? ORDER BY name');
        $st->execute([$uid]);
        return array_map([self::class, 'shape'], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    private static function shape(array $r): array
    {
        $f = json_decode((string) $r['filters'], true);
        return ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'filters' => self::clean(is_array($f) ? $f : [])];
    }

    /** Only the filters Pulse knows, each in its own shape; anything else is dropped. */
    public static function clean(array $f): array
    {
        $str = function ($v, int $max): string { $v = is_scalar($v) ? trim((string) $v) : ''; return mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $v) ?? '', 0, $max); };
        $list = function ($v) use ($str): array {
            $out = [];
            foreach (is_array($v) ? $v : [] as $x) { $x = $str($x, 120); if ($x !== '' && !in_array($x, $out, true)) $out[] = $x; if (count($out) >= 100) break; }
            return $out;
        };
        $g = $str($f['group'] ?? '', 8);
        return ['list' => $str($f['list'] ?? '', 130), 'show' => $str($f['show'] ?? '', 30), 'from' => $list($f['from'] ?? []),
            'sev' => $list($f['sev'] ?? []), 'group' => in_array($g, self::GROUPS, true) ? $g : ''];
    }

    private static function name(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') throw new InvalidArgumentException('Give the view a name, like "Needs me now".');
        if (mb_strlen($name) > 60) throw new InvalidArgumentException('Keep the name to 60 letters or fewer.');
        return $name;
    }

    /** Saves a view; the same name again replaces its filters. Returns [view, replaced]. */
    public static function save(PDO $pdo, int $uid, string $name, array $filters): array
    {
        if (!self::ready($pdo)) throw new InvalidArgumentException('Saved views come with the next site update.');
        $name = self::name($name);
        $json = json_encode(self::clean($filters), JSON_UNESCAPED_UNICODE);
        if ($json === false || strlen($json) > 8000) throw new InvalidArgumentException('That mix of filters is too long to save. Tick fewer senders.');
        $st = $pdo->prepare('SELECT id FROM pulse_views WHERE user_id = ? AND name = ?'); $st->execute([$uid, $name]);
        $id = (int) ($st->fetchColumn() ?: 0);
        $replaced = $id > 0;
        if ($replaced) {
            $pdo->prepare('UPDATE pulse_views SET filters = ? WHERE id = ? AND user_id = ?')->execute([$json, $id, $uid]);
        } else {
            $st = $pdo->prepare('SELECT COUNT(*) FROM pulse_views WHERE user_id = ?'); $st->execute([$uid]);
            if ((int) $st->fetchColumn() >= self::MAX) throw new InvalidArgumentException('You have ' . self::MAX . ' saved views. Remove one to save another.');
            $pdo->prepare('INSERT INTO pulse_views (user_id, name, filters, created_at) VALUES (?, ?, ?, ?)')->execute([$uid, $name, $json, date('Y-m-d H:i:s')]);
            $id = (int) $pdo->lastInsertId();
        }
        return [self::one($pdo, $uid, $id), $replaced];
    }

    public static function one(PDO $pdo, int $uid, int $id): ?array
    {
        $st = $pdo->prepare('SELECT id, name, filters FROM pulse_views WHERE id = ? AND user_id = ?'); $st->execute([$id, $uid]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? self::shape($r) : null;
    }

    /** Removes one of this person's views; returns it, so Undo can save it again. */
    public static function delete(PDO $pdo, int $uid, int $id): array
    {
        $v = self::ready($pdo) ? self::one($pdo, $uid, $id) : null;
        if ($v === null) throw new InvalidArgumentException('That view is already gone.');
        $pdo->prepare('DELETE FROM pulse_views WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
        return $v;
    }
}
