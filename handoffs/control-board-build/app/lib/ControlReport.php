<?php
// app/lib/ControlReport.php — the System Control Board's reports (decisions/0171 §11, release 5b): for the whole system,
// one system or one process, over a day, a week or a window. Built on demand from what is already kept (the historian's
// state rows, the ledger, crawler run items, site job runs, the alarms, the logbook, the service register's usage): no new
// table, nothing stored, no AI and no outside call, so a report costs nothing to make.
//   - Each section: uptime; what ran; records in, stored and refused; slow calls, retries and crashes; spend against caps;
//     alarms raised, acknowledged and cleared, and how long each was open; shut-downs, maintenance and switches; the trend.
//   - The page on the board (?report=<scope>&range=day|week), Download as CSV (one table per section, the page's columns)
//     and the page as a file (self-contained HTML: inline styles, no scripts, no images).
//   - A summary of three to five lines for the daily report's System status and the health report.
// Nothing private leaves in a report: "who" is a staff member's name or "the system" (as the logbook), never a member;
// no readings or evidence (they can hold addresses), and every line is scrubbed of email addresses, phone numbers and IPs.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ControlRegister.php';

final class ControlReport
{
    /** Ranges: key => [seconds, words, the historian's trend range]. */
    public const RANGES = ['day' => [86400, 'the last 24 hours', '24h'], 'week' => [604800, 'the last 7 days', '7d']];

    /** The scopes a report can cover: '' (the whole system), 'sys:<key>', or a process id. key => name. */
    public static function scopes(PDO $pdo): array
    {
        $out = ['' => 'The whole system'];
        foreach (ControlRegister::SYSTEMS as $k => $n) $out['sys:' . $k] = $n;
        try { foreach (ControlRegister::processes($pdo) as $pid => $p) $out[$pid] = (string) $p[1]; } catch (Throwable $x) {}
        return $out;
    }

    /** The processes a scope covers: pid => [system, name]. */
    public static function procsOf(PDO $pdo, string $scope): array
    {
        try { $all = ControlRegister::processes($pdo); } catch (Throwable $x) { return []; }
        if ($scope === '') return $all;
        if (str_starts_with($scope, 'sys:')) return array_filter($all, fn ($p) => $p[0] === substr($scope, 4));
        return isset($all[$scope]) ? [$scope => $all[$scope]] : [];
    }

    /** Email addresses, phone numbers and IP addresses out of a line that leaves in a report. */
    public static function clean(string $s): string
    {
        $s = (string) preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $s);
        $s = (string) preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[ip]', $s);
        $s = (string) preg_replace('/(?<![\d.])(\+?1[\s.-]?)?\(?\d{3}\)?[\s.-]\d{3}[\s.-]\d{4}(?![\d.])/', '[phone]', $s);
        return trim($s);
    }

    /** Seconds as words: "2 h 5 min", "40 s". */
    public static function dur(int $s): string
    {
        if ($s < 60) return $s . ' s';
        $h = intdiv($s, 3600); $m = intdiv($s % 3600, 60);
        if ($h >= 48) return intdiv($h, 24) . ' d ' . ($h % 24) . ' h';
        return trim(($h ? $h . ' h ' : '') . ($m || !$h ? $m . ' min' : ''));
    }

    /**
     * The report: ['scope', 'name', 'range', 'label', 'from', 'to', 'sections' => key => [title, cols, rows, note], 'summary'].
     * A cell is a string, a number, or ['t' => unix] for a moment (shown in the reader's zone, UTC in the CSV and file).
     */
    public static function build(PDO $pdo, string $scope = '', string $range = 'day', ?int $now = null): array
    {
        $now = $now ?? time();
        [$span, $words, $tr] = self::RANGES[$range] ?? self::RANGES['day'];
        $range = isset(self::RANGES[$range]) ? $range : 'day';
        $from = $now - $span; $to = $now;
        $scopes = self::scopes($pdo);
        $scope = isset($scopes[$scope]) ? $scope : '';
        $procs = self::procsOf($pdo, $scope);
        $sec = [];

        // uptime, from the historian's state rows
        $rows = []; $up = 0; $due = 0;
        try {
            require_once __DIR__ . '/ControlHistorian.php';
            $d = ControlHistorian::durations($pdo, array_keys($procs), $from, $to);
            foreach ($procs as $pid => [$sys, $name]) {
                if (!isset($d[$pid])) continue;
                $x = $d[$pid]; $u = ControlHistorian::uptime($x); $up += $x['up']; $due += $x['due'];
                $rows[] = [$name, ControlRegister::SYSTEMS[$sys] ?? $sys, $u === null ? 'not due to be on' : $u . '%', self::dur($x['up']), self::dur($x['down']), self::dur($x['maint']), self::dur($x['off'])];
            }
        } catch (Throwable $x) {}
        usort($rows, fn ($a, $b) => strcmp((string) $a[2], (string) $b[2]));
        $sec['uptime'] = ['Uptime', ['Process', 'System', 'Uptime', 'Up', 'Down', 'Maintenance', 'Off'], $rows, $rows ? '' : 'No history yet for this window: readings started with release 4b.'];
        $uptime = $due ? round(100 * $up / $due, 1) : null;

        // what ran, and records in, stored and refused: each workflow's calls (the ledger), its crawler tasks' outcomes, its site jobs
        $rows = []; $tot = ['received' => 0, 'stored' => 0, 'refused' => 0, 'errors' => 0, 'calls' => 0]; $claimed = [];
        try {
            require_once __DIR__ . '/ControlBoard.php';
            $w = [$now - $from, $now - $to];
            foreach ($procs as $pid => [, $name]) {
                if (!str_starts_with($pid, 'wf:')) continue;
                $of = ControlRegister::of($pdo, substr($pid, 3));
                $c = ControlBoard::calls($pdo, (array) $of['endpoints'], $w);
                $o = ControlBoard::outcomes($pdo, (array) $of['tasks'], $w);
                [$g, $b] = ControlBoard::jobRuns($pdo, (array) $of['jobs'], $w);
                foreach ($tot as $k => $_) $tot[$k] += (int) ($c[$k] ?? 0);
                if (!$c['calls'] && !array_sum($o) && !$g && !$b) continue;
                $rows[] = [$name, $c['calls'], $c['received'], $c['stored'], $c['updated'], $c['review'], $c['refused'], $c['errors'], array_sum($o), $g, $b];
                foreach ((array) $of['endpoints'] as $e) $claimed[$e] = true;
            }
            // every call in the ledger counts in the totals, whichever workflow claims its endpoint (the control board's build,
            // D6: "Records: 0 in" for a week while the ledger was writing, because calls were counted only through a workflow's
            // task names); calls no workflow claims get their own row, never dropped
            if ($scope === '') {
                $all = ControlBoard::callsByEndpoint($pdo, $w);
                $tot = ['received' => 0, 'stored' => 0, 'refused' => 0, 'errors' => 0, 'calls' => 0];
                $loose = ['calls' => 0, 'received' => 0, 'stored' => 0, 'updated' => 0, 'review' => 0, 'refused' => 0, 'errors' => 0]; $looseEps = [];
                foreach ($all as $e => $c) {
                    foreach ($tot as $k => $_) $tot[$k] += (int) ($c[$k] ?? 0);
                    if (isset($claimed[$e])) continue;
                    foreach ($loose as $k => $_) $loose[$k] += (int) ($c[$k] ?? 0); $looseEps[] = '/api/' . $e;
                }
                if ($loose['calls'] > 0) $rows[] = ['Not tied to a workflow: ' . implode(', ', array_slice($looseEps, 0, 4)) . (count($looseEps) > 4 ? ' and ' . (count($looseEps) - 4) . ' more' : ''), $loose['calls'], $loose['received'], $loose['stored'], $loose['updated'], $loose['review'], $loose['refused'], $loose['errors'], 0, 0, 0];
            }
        } catch (Throwable $x) { error_log('control report, what ran: ' . $x->getMessage()); }
        $sec['ran'] = ['What ran, and records in, stored and refused', ['Workflow', 'Calls', 'Received', 'Stored', 'Updated', 'For review', 'Refused', 'Errors', 'Task runs', 'Site jobs ok', 'Site jobs failed'], $rows, $rows ? '' : 'Nothing ran in this window.'];

        // alarms raised, acknowledged and cleared, and how long each was open (titles only: a reading can hold an address)
        $rows = []; $kinds = ['slow' => 0, 'retry' => 0, 'crash' => 0]; $trips = 0; $openNow = 0;
        try {
            require_once __DIR__ . '/ControlAlarms.php'; ControlAlarms::ensure($pdo);
            $f = gmdate('Y-m-d H:i:s', $from); $t = gmdate('Y-m-d H:i:s', $to);
            $st = $pdo->prepare('SELECT proc, level, kind, title, n_times, first_at, ack_name, ack_at, cleared_at, cleared_how FROM control_alarms WHERE first_at < ? AND (cleared_at IS NULL OR cleared_at >= ?) ORDER BY first_at DESC LIMIT 300');
            $st->execute([$t, $f]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
                if ($procs !== [] && $scope !== '' && !isset($procs[(string) $a['proc']])) continue;
                $raised = (int) strtotime($a['first_at'] . ' UTC'); $cl = $a['cleared_at'] ? (int) strtotime($a['cleared_at'] . ' UTC') : 0;
                if ($raised >= $from && isset($kinds[$a['kind']])) $kinds[$a['kind']]++;
                if ($a['level'] === 'trip' && $raised >= $from) $trips++;
                if (!$cl) $openNow++;
                $rows[] = [['t' => $raised], ControlAlarms::LEVELS[$a['level']] ?? $a['level'], $procs[(string) $a['proc']][1] ?? (string) $a['proc'], self::clean((string) $a['title']), (int) $a['n_times'],
                    $a['ack_at'] ? self::clean((string) $a['ack_name']) : '', $cl ? ['t' => $cl] : 'open', self::dur(($cl ?: $to) - $raised), $cl ? self::clean((string) $a['cleared_how']) : ''];
            }
        } catch (Throwable $x) {}
        $sec['alarms'] = ['Alarms raised, acknowledged and cleared', ['Raised', 'Level', 'Process', 'Alarm', 'Times', 'Acknowledged by', 'Cleared', 'Open for', 'How it cleared'], $rows, $rows ? '' : 'No alarm was open in this window.'];
        $sec['faults'] = ['Slow calls, retries and crashes', ['What', 'Raised in this window'], [['Slow', $kinds['slow']], ['Retrying (an outside server backing off)', $kinds['retry']], ['Crashes', $kinds['crash']], ['Server errors answering the workers', $tot['errors']]], ''];

        // shut-downs, maintenance, switches and controls, from the logbook
        $rows = []; $stops = 0;
        try {
            require_once __DIR__ . '/ControlLog.php';
            foreach (ControlLog::feed($pdo, $scope, $to + 1, 200) as [$at, , $k, , $what, $who, $why]) {
                if ($at < $from || !in_array($k, ['stop', 'start', 'maint', 'switch', 'control', 'session', 'resolve'], true)) continue;
                if ($k === 'stop') $stops++;
                $rows[] = [['t' => $at], ControlLog::KINDS[$k] ?? $k, self::clean($what), self::clean($who), self::clean($why)];
            }
        } catch (Throwable $x) {}
        $sec['changes'] = ['Shut-downs, maintenance and switches', ['When', 'Kind', 'What', 'Who', 'Why'], $rows, $rows ? '' : 'Nothing was switched, shut down or taken out in this window.'];

        // spend against caps (month to date, or today for a daily cap), from the service register
        $rows = []; $top = null;
        try {
            require_once __DIR__ . '/Operations.php';
            foreach (Operations::usage($pdo) as $svc => $list) {
                if ($scope !== '' && !isset($procs['svc:' . $svc]) && !(str_starts_with($scope, 'wf:') && in_array($svc, (array) (ControlRegister::of($pdo, substr($scope, 3))['services'] ?? []), true))) continue;
                foreach ($list as $u) {
                    if ((float) $u['cap'] <= 0 && (float) $u['used'] <= 0) continue;
                    $rows[] = [$procs['svc:' . $svc][1] ?? $svc, self::clean((string) $u['what']), (string) $u['used'] . ' ' . ($u['unit'] ?? ''), (float) $u['cap'] > 0 ? (string) $u['cap'] . ' ' . ($u['unit'] ?? '') : 'no cap', (float) $u['cap'] > 0 ? (int) $u['pct'] . '%' : ''];
                    if ((float) $u['cap'] > 0 && ($top === null || (int) $u['pct'] > $top[1])) $top = [self::clean((string) $u['what']), (int) $u['pct']];
                }
            }
        } catch (Throwable $x) {}
        $sec['spend'] = ['Spend against caps (as of now)', ['Service', 'What', 'Used', 'Cap', 'Of its cap'], $rows, $rows ? 'Month to date, or today for a daily cap.' : 'No metered use for this scope.'];

        // the trend, in words, from the historian (the board draws its bars)
        $trend = '';
        try {
            require_once __DIR__ . '/ControlHistorian.php';
            $tid = $scope === '' ? '' : $scope;
            if ($tid !== '') { $tt = ControlHistorian::trend($pdo, $tid, $tr, $now); $trend = !$tt['history'] ? 'No history yet: readings start now.' : 'Over ' . $words . ': uptime ' . ($tt['uptime'] === null ? 'not counted (not due to be on)' : $tt['uptime'] . '%') . '.'; }
        } catch (Throwable $x) {}
        $sec['trend'] = ['The trend', ['Window', 'Uptime', 'Trips raised', 'Alarms open now', 'Records stored', 'Records refused'], [[ucfirst($words), $uptime === null ? 'not counted' : $uptime . '%', $trips, $openNow, $tot['stored'], $tot['refused']]], $trend];

        $name = $scopes[$scope];
        $sum = [];
        $sum[] = $name . ', ' . $words . ': uptime ' . ($uptime === null ? 'not counted yet' : $uptime . '%') . '.';
        $sum[] = $trips . ' trip' . ($trips === 1 ? '' : 's') . ' raised; ' . $openNow . ' alarm' . ($openNow === 1 ? '' : 's') . ' open now.';
        $sum[] = 'Records: ' . number_format($tot['received']) . ' in, ' . number_format($tot['stored']) . ' stored, ' . number_format($tot['refused']) . ' refused' . ($tot['errors'] ? '; ' . $tot['errors'] . ' server error' . ($tot['errors'] === 1 ? '' : 's') : '') . '.';
        if ($top) $sum[] = 'Nearest a cap: ' . $top[0] . ' at ' . $top[1] . '%.';
        if ($stops) $sum[] = $stops . ' shut-down' . ($stops === 1 ? '' : 's') . ' in the logbook.';
        return ['scope' => $scope, 'name' => $name, 'range' => $range, 'label' => $words, 'from' => $from, 'to' => $to, 'sections' => $sec, 'summary' => array_slice($sum, 0, 5)];
    }

    /** A cell as plain text: a moment in UTC (ISO for the CSV, words for the file). */
    private static function text($c, bool $iso): string
    {
        if (is_array($c) && isset($c['t'])) return $iso ? gmdate('Y-m-d\TH:i:s\Z', (int) $c['t']) : gmdate('M j, g:i A', (int) $c['t']) . ' UTC';
        return (string) $c;
    }

    /** Download as CSV: each section a heading line, then its columns and rows (the page's columns). */
    public static function csv(array $r): string
    {
        $f = fopen('php://temp', 'w+');
        fputcsv($f, ['Traversence System Control Board report', $r['name'], ucfirst($r['label']), gmdate('Y-m-d\TH:i:s\Z', $r['from']), gmdate('Y-m-d\TH:i:s\Z', $r['to'])]);
        foreach ($r['summary'] as $l) fputcsv($f, ['Summary', $l]);
        foreach ($r['sections'] as [$title, $cols, $rows, $note]) {
            fputcsv($f, []); fputcsv($f, [$title]); fputcsv($f, $cols);
            foreach ($rows as $row) fputcsv($f, array_map(fn ($c) => self::text($c, true), $row));
            if ($note !== '') fputcsv($f, [$note]);
        }
        rewind($f); $s = (string) stream_get_contents($f); fclose($f);
        return $s;
    }

    /** The page as a file: one self-contained HTML (inline styles, no scripts, no images, no links back in). */
    public static function file(array $r): string
    {
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $th = 'text-align:left;padding:6px 8px;border-bottom:1px solid #CBB98D;font-size:12px;color:#5C4632';
        $td = 'padding:6px 8px;border-bottom:1px solid #EEE3CA;font-size:13px;vertical-align:top';
        $h = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e($r['name'] . ': ' . $r['label']) . '</title></head>'
           . '<body style="margin:0;background:#F6EFDF;color:#3E2B1F;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif"><div style="max-width:960px;margin:0 auto;padding:14px 10px">'
           . '<p style="margin:0;font-size:13px;color:#8A7355">Traversence · System Control Board report · ' . $e(gmdate('M j, g:i A', $r['from'])) . ' to ' . $e(gmdate('M j, g:i A', $r['to'])) . ' UTC</p>'
           . '<h1 style="margin:4px 0 10px;font-size:20px">' . $e($r['name']) . ': ' . $e($r['label']) . '</h1><ul style="margin:0 0 12px;padding-left:18px">';
        foreach ($r['summary'] as $l) $h .= '<li style="margin:2px 0">' . $e($l) . '</li>';
        $h .= '</ul>';
        foreach ($r['sections'] as [$title, $cols, $rows, $note]) {
            $h .= '<h2 style="margin:16px 0 6px;font-size:16px">' . $e($title) . '</h2>';
            if ($rows) {
                $h .= '<div style="overflow-x:auto"><table style="border-collapse:collapse;width:100%;background:#FFFBF2"><thead><tr>';
                foreach ($cols as $c) $h .= '<th style="' . $th . '">' . $e($c) . '</th>';
                $h .= '</tr></thead><tbody>';
                foreach ($rows as $row) { $h .= '<tr>'; foreach ($row as $c) $h .= '<td style="' . $td . '">' . $e(self::text($c, false)) . '</td>'; $h .= '</tr>'; }
                $h .= '</tbody></table></div>';
            }
            if ($note !== '') $h .= '<p style="margin:6px 0;font-size:13px;color:#5C4632">' . $e($note) . '</p>';
        }
        return $h . '<p style="margin:16px 0 0;font-size:12px;color:#8A7355">Made from what the site keeps; nothing private is in it. Times are UTC.</p></div></body></html>';
    }

    /** The short summary for the daily report and the health report: the whole system over the last day (or week). */
    public static function summary(PDO $pdo, string $range = 'day'): array
    {
        try { return self::build($pdo, '', $range)['summary']; } catch (Throwable $x) { return []; }
    }
}
