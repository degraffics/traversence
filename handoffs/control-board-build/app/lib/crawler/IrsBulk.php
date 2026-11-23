<?php

declare(strict_types=1);

require_once __DIR__ . '/NpiBulk.php';

/**
 * api/lib/crawler/IrsBulk.php — our copy of the IRS list of tax-exempt organizations (decisions/0048).
 *
 * The IRS publishes every exempt organization by state (Exempt Organizations Business Master File: name,
 * street, city, ZIP, activity code). Once a month the worker sends the pilot states here. It is used ONLY to
 * confirm listings waiting for review: a similar name at the same ZIP and street number is a government
 * reference. It is never shown and never creates a listing: many small nonprofits are registered at a
 * volunteer's home, and a registration is not proof a place is open.
 */
final class IrsBulk
{
    private const EVERY_DAYS = 30;
    private const RETRY_HOURS = 6;

    public static function ready(PDO $pdo): bool
    {
        try { $pdo->query('SELECT 1 FROM ' . Database::t('irs_loads') . ' LIMIT 1'); $pdo->query('SELECT 1 FROM ' . Database::t('irs_orgs') . ' LIMIT 1'); return true; } catch (PDOException) { return false; }
    }

    public static function status(PDO $pdo): array
    {
        $last = $pdo->query('SELECT file, finished_at FROM ' . Database::t('irs_loads') . ' WHERE finished_at IS NOT NULL ORDER BY finished_at DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null;
        $running = $pdo->query('SELECT started_at FROM ' . Database::t('irs_loads') . ' WHERE finished_at IS NULL ORDER BY id DESC LIMIT 1')->fetchColumn();
        $due = (!$last || strtotime((string) $last['finished_at']) < time() - self::EVERY_DAYS * 86400)
            && (!$running || strtotime((string) $running) < time() - self::RETRY_HOURS * 3600);
        return ['due' => $due, 'states' => NpiBulk::STATES, 'last_finished' => $last['finished_at'] ?? null,
                'records' => (int) $pdo->query('SELECT COUNT(*) FROM ' . Database::t('irs_orgs') . '')->fetchColumn()];
    }

    public static function start(PDO $pdo, string $file): int
    {
        $pdo->prepare('INSERT INTO ' . Database::t('irs_loads') . ' (file, started_at) VALUES (?, ?)')->execute([mb_substr($file, 0, 40), date('Y-m-d H:i:s')]);
        return (int) $pdo->lastInsertId();
    }

    /** The columns each row of the worker's batch carries (api/crawl/irs.php); one missing means the file changed shape. */
    public const COLUMNS = ['ein', 'name', 'street', 'city', 'state', 'zip', 'ntee'];

    /** The load's alarm (the one engine): a stop on IRS's load only, which waits for a person (trying again won't change the file). */
    private static function hold(PDO $pdo, string $key, string $title, string $means, string $reading): void
    {
        try { require_once __DIR__ . '/../ControlAlarms.php'; ControlAlarms::raise($pdo, $key, 'svc:irs', 'trip', 'held', $title, $means, 'Open its files on IRS\'s card to see what changed; the worker\'s reader needs the fix. The next month\'s load runs as usual once it reads the file.', mb_substr($reading, 0, 255), [['IRS\'s card', 'peek', '/admin/config/register#svc-irs'], ['Check', 'check', 'svc:irs']], true, false, 'svc:irs'); } catch (Throwable $x) { error_log('irs hold: ' . $x->getMessage()); }
    }

    public static function batch(PDO $pdo, string $file, array $rows): int
    {
        // the shape, before any write (02 row 38b): a row without an expected column means the IRS's file changed shape
        $first = $rows ? (array) reset($rows) : [];
        $missing = $first ? array_values(array_diff(self::COLUMNS, array_keys($first))) : [];
        if ($missing) {
            self::hold($pdo, 'load:irs:shape', 'IRS\'s file changed shape (' . $file . ')', 'Nothing of this batch was written: its rows have no ' . implode(', ', $missing) . '. Last month\'s copy is kept and used.', 'missing: ' . implode(', ', $missing));
            throw new DomainException('The IRS file changed shape: its rows have no ' . implode(', ', $missing) . '. Nothing was written.');
        }
        $n = 0;
        $up = $pdo->prepare('REPLACE INTO ' . Database::t('irs_orgs') . ' (ein, name, street, city, state, zip5, ntee, file, loaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach (array_slice($rows, 0, 2000) as $r) {
            $ein = preg_replace('/\D/', '', (string) ($r['ein'] ?? ''));
            $state = strtoupper(substr((string) ($r['state'] ?? ''), 0, 2));
            $name = trim((string) ($r['name'] ?? ''));
            if (strlen($ein) !== 9 || $name === '' || !in_array($state, NpiBulk::STATES, true)) continue;
            $up->execute([$ein, mb_substr($name, 0, 200), mb_substr(trim((string) ($r['street'] ?? '')), 0, 255), mb_substr(trim((string) ($r['city'] ?? '')), 0, 80),
                          $state, substr(preg_replace('/\D/', '', (string) ($r['zip'] ?? '')), 0, 5), substr(strtoupper(trim((string) ($r['ntee'] ?? ''))), 0, 5),
                          mb_substr($file, 0, 40), date('Y-m-d H:i:s')]);
            $n++;
        }
        return $n;
    }

    /** The load finished: records it and removes organizations that weren't in this month's files. */
    public static function finish(PDO $pdo, int $loadId, string $file, int $seen): int
    {
        // no delete below the floor (02 row 38b; Thresholds irs_floor_pct, 90% of last month's rows): one state's file loading
        // while the other's rows were all refused deleted half the copy; now last month's is kept and the load waits
        $prev = (int) $pdo->query('SELECT COUNT(*) FROM ' . Database::t('irs_orgs') . ' WHERE file <> ' . $pdo->quote($file))->fetchColumn();   // last month's copy, still here
        $have = (int) $pdo->query('SELECT COUNT(*) FROM ' . Database::t('irs_orgs') . ' WHERE file = ' . $pdo->quote($file))->fetchColumn();
        require_once __DIR__ . '/../Thresholds.php';
        $floor = max(0, min(100, (int) (Thresholds::get($pdo, 'irs_floor_pct') ?? 90)));
        if ($prev > 0 && $have * 100 < $prev * $floor) {
            $pdo->prepare('UPDATE ' . Database::t('irs_loads') . ' SET finished_at = ?, rows_seen = ?, removed = 0 WHERE id = ?')->execute([date('Y-m-d H:i:s'), $seen, $loadId]);
            self::hold($pdo, 'load:irs:short', 'IRS\'s load came short (' . $file . ')', 'This month\'s files brought ' . number_format($have) . ' organizations against ' . number_format($prev) . ' last month (under ' . $floor . '%): nothing was deleted, and last month\'s copy is kept and used.', number_format($have) . ' of ' . number_format($prev));
            return 0;
        }
        try { require_once __DIR__ . '/../ControlAlarms.php'; ControlAlarms::clear($pdo, 'load:irs:short', 'This month\'s load came whole (' . number_format($have) . ' organizations).'); ControlAlarms::clear($pdo, 'load:irs:shape', 'This month\'s files read in their usual shape.'); } catch (Throwable $x) {}
        $del = $pdo->prepare('DELETE FROM ' . Database::t('irs_orgs') . ' WHERE file <> ?');
        $del->execute([$file]);
        $removed = $del->rowCount();
        $pdo->prepare('UPDATE ' . Database::t('irs_loads') . ' SET finished_at = ?, rows_seen = ?, removed = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $seen, $removed, $loadId]);
        return $removed;
    }

    /** A government reference for a listing: a similar name at the same ZIP and street number. */
    public static function matches(PDO $pdo, array $c): array
    {
        if (!self::ready($pdo)) return [];
        $zip = (string) ($c['where']['zip'] ?? '');
        $house = preg_match('/^\s*(\d+)/', (string) ($c['where']['address'] ?? ''), $m) ? $m[1] : '';
        if ($zip === '' || $house === '') return [];
        $st = $pdo->prepare('SELECT ein, name, street FROM ' . Database::t('irs_orgs') . ' WHERE zip5 = ? LIMIT 2000');
        $st->execute([$zip]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (!preg_match('/^\s*' . $house . '\b/', (string) $r['street']) || !NpiBulk::sameName((string) ($c['name'] ?? ''), (string) $r['name'])) continue;
            return [['url' => 'https://apps.irs.gov/app/eos/details/?ein=' . $r['ein'], 'kind' => 'government', 'evidence' => 'address',
                     'label' => 'IRS exempt organization: ' . ucwords(strtolower((string) $r['name']))]];
        }
        return [];
    }
}
