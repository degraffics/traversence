<?php
// app/lib/HostBackups.php — what our host (Bluehost) already backs up, read from its cPanel through the API
// (CPanel::uapi(); CLAUDE.md "cPanel through its API"), for the Bluehost card's "The databases" line in Modules.
// The launch plan's B.2, step 1 (the CEO's brief, 2026-11-27; decisions/0181 gate 4; 0163's planned nightly backups):
// read first, so the nightly backup we add keeps what the host doesn't, and nothing twice.
// Read-only: two cPanel reads, at most once a day (the answer is kept in search_index_state, 'host:backups'), never
// from staging (CPanel's own StagingGate hold answers there, and the line says so). Nothing here changes the account.
declare(strict_types=1);

final class HostBackups
{
    public const KEY = 'host:backups';
    public const EVERY = 86400;   // seconds: one read a day (the brief: "a check that refreshes daily")

    /** The kept reading, read again when it's a day old: ['at', 'ok', 'backups' => [...], 'latest', 'count', 'jetbackup', 'why']. */
    public static function state(PDO $pdo, bool $fresh = false): array
    {
        require_once __DIR__ . '/SearchIndex.php';
        $was = null;
        try { $was = json_decode(SearchIndex::state($pdo, self::KEY), true); } catch (Throwable $x) {}
        if (!$fresh && is_array($was) && (time() - (int) ($was['at'] ?? 0)) < self::EVERY) return $was;
        $now = self::read();
        try { SearchIndex::setState($pdo, self::KEY, json_encode($now, JSON_UNESCAPED_SLASHES)); } catch (Throwable $x) { error_log('host backups: ' . $x->getMessage()); }
        return $now;
    }

    /** The two reads. Each failure is kept in its own words; one failing doesn't hide the other. */
    public static function read(): array
    {
        require_once __DIR__ . '/CPanel.php';
        $out = ['at' => time(), 'ok' => false, 'backups' => [], 'latest' => '', 'count' => 0, 'jetbackup' => null, 'why' => ''];
        if (!CPanel::ready()) { $out['why'] = 'cPanel isn\'t connected (CPANEL_HOST, CPANEL_USER and CPANEL_TOKEN in .env).'; return $out; }
        // cPanel's own Backup module: the account's backups the host keeps (UAPI Backup::list_backups)
        try {
            $list = CPanel::uapi('Backup', 'list_backups');
            $out['ok'] = true;
            $out['backups'] = self::dates(is_array($list) ? $list : []);
            $out['count'] = count($out['backups']);
            $out['latest'] = $out['backups'] ? max($out['backups']) : '';
        } catch (Throwable $x) { $out['why'] = 'cPanel\'s Backup module: ' . mb_substr($x->getMessage(), 0, 200); }
        // JetBackup (Bluehost's backup tool on many plans): is its feature on for the account? (UAPI Features::list_features)
        try {
            $f = CPanel::uapi('Features', 'list_features');
            $on = [];
            foreach ((array) $f as $k => $v) {
                $name = is_array($v) ? (string) ($v['name'] ?? $v['feature'] ?? $k) : (string) $k;
                $enabled = is_array($v) ? !empty($v['enabled'] ?? $v['value'] ?? true) : (bool) $v;
                if ($enabled && stripos($name, 'jetbackup') !== false) $on[] = $name;
            }
            $out['jetbackup'] = $on;
        } catch (Throwable $x) { $out['jetbackup'] = null; if ($out['why'] === '') $out['why'] = 'cPanel\'s feature list: ' . mb_substr($x->getMessage(), 0, 200); }
        return $out;
    }

    /** The backups' dates (Y-m-d), newest last: from date strings, or rows carrying a date or a time. */
    public static function dates(array $list): array
    {
        $d = [];
        foreach ($list as $k => $row) {
            $v = is_array($row) ? ($row['date'] ?? $row['backupdate'] ?? $row['time'] ?? $row['mtime'] ?? $row['name'] ?? '') : $row;
            if (is_int($v) || (is_string($v) && ctype_digit($v) && strlen($v) >= 9)) { $d[] = gmdate('Y-m-d', (int) $v); continue; }
            if (is_string($v) && preg_match('/(\d{4})-?(\d{2})-?(\d{2})/', $v, $m)) $d[] = $m[1] . '-' . $m[2] . '-' . $m[3];
            elseif (is_string($k) && preg_match('/(\d{4})-?(\d{2})-?(\d{2})/', $k, $m)) $d[] = $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        $d = array_values(array_unique($d)); sort($d);
        return $d;
    }

    /** How often they run, from the dates when they say it plainly ("about daily", "about weekly"), else ''. */
    public static function cadence(array $dates): string
    {
        if (count($dates) < 3) return '';
        $gaps = [];
        for ($i = 1; $i < count($dates); $i++) $gaps[] = (strtotime($dates[$i]) - strtotime($dates[$i - 1])) / 86400;
        sort($gaps); $mid = $gaps[intdiv(count($gaps), 2)];
        if ($mid >= 0.5 && $mid <= 1.5) return 'about daily';
        if ($mid >= 6 && $mid <= 8) return 'about weekly';
        if ($mid >= 27 && $mid <= 32) return 'about monthly';
        return '';
    }

    /** The line's parts for the card: [state, sentence, read at (unix), next read (unix)]. State: found, none, unread, staging. */
    public static function words(array $s, bool $staging = false): array
    {
        $at = (int) ($s['at'] ?? time()); $next = $at + self::EVERY;
        if ($staging) return ['staging', 'Not read on staging; cPanel is read on live only.', 0, 0];
        $md = fn (string $d) => date('M j', (int) strtotime($d));
        $jb = $s['jetbackup'] ?? null;
        $jbw = $jb ? ' JetBackup is on for the account.' : ($jb === [] ? ' JetBackup isn\'t on for the account.' : '');
        if (empty($s['ok'])) return ['unread', 'Bluehost\'s backups couldn\'t be read: ' . rtrim((string) ($s['why'] ?: 'no answer'), '. ') . '.', $at, $next];
        if ((int) ($s['count'] ?? 0) === 0) return ['none', 'cPanel lists no backups for this account.' . $jbw, $at, $next];
        $b = (array) $s['backups']; $how = self::cadence($b);
        return ['found', 'Bluehost keeps ' . (int) $s['count'] . ', from ' . $md($b[0]) . ' to ' . $md((string) $s['latest']) . ($how !== '' ? ', ' . $how . ', going by their dates' : '') . '.' . $jbw, $at, $next];
    }
}
