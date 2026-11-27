<?php
// app/lib/FounderMail.php — the site emails the Founder itself (Jason, 2026-11-25: "If I have to allow emails in claude that's
// a problem. Can you use the system for emailing?"). Through the one dispatcher (Notify, decisions/0172 and 0176: Pulse first,
// the system's own email, free), to the Founder alone (CommsSetup::founders: the active staff logins at the founder level),
// three things, each once:
//   - the daily batch waiting for its OK (ControlGauges::latestBatch: today's, or the latest not yet approved; decisions/0179 rule 1; P2-5): its lines and the link;
//   - a Founder ruling (Tier 5, decisions/0180) reaching "Waiting for you" (GaugeTasks::sendBrief): the brief, on its task's thread;
//   - the session manager's messages, from releases/batch.json's "messages" (carried by gauges.py into gauges.json), each
//     by its id; its "Open it" goes to its own link, else its Work queue ticket, else the Work queue (urlOf).
// What was sent is kept in founder_mail_sent (key, when), so nothing goes twice. Never from staging: the job doesn't run
// there, and Notify's email there is held by StagingGate anyway. Run by founder_mail_tick every 10 minutes.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class FounderMail
{
    public const DEV = 'https://traversence.com/admin/config/control?tab=development';

    public static function ensure(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS founder_mail_sent (k VARCHAR(120) NOT NULL PRIMARY KEY, at DATETIME NOT NULL, users VARCHAR(120) NOT NULL DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public static function sent(PDO $pdo, string $k): bool
    {
        try { $st = $pdo->prepare('SELECT 1 FROM founder_mail_sent WHERE k = ?'); $st->execute([$k]); return (bool) $st->fetchColumn(); } catch (Throwable $x) { return false; }
    }

    /** The Founder's user ids, never anyone else (a test login is never the Founder, TestAccounts). */
    public static function founders(PDO $pdo): array
    {
        require_once __DIR__ . '/CommsSetup.php';
        return array_values(array_unique(array_filter(array_map('intval', CommsSetup::founders($pdo)), fn ($i) => $i > 0)));
    }

    /** Sends one thing once: true when it went to the Founder now (false when it went before, or there's no Founder). */
    private static function once(PDO $pdo, string $k, string $kind, array $about, array $words): bool
    {
        self::ensure($pdo);
        if (self::sent($pdo, $k)) return false;
        require_once __DIR__ . '/Staging.php'; if (Staging::here()) return false;   // staging never mails the Founder
        $to = self::founders($pdo); if (!$to) return false;
        require_once __DIR__ . '/Notify.php';
        $n = Notify::send($pdo, $kind, ['staff' => $to], $about + ['thing' => $k], $words);
        if ($n < 1) return false;
        $pdo->prepare('INSERT IGNORE INTO founder_mail_sent (k, at, users) VALUES (?, UTC_TIMESTAMP(), ?)')->execute([mb_substr($k, 0, 120), implode(',', $to)]);
        return true;
    }

    /** The batch waiting (ControlGauges::latestBatch: today's, or the latest not yet approved), once per date. */
    public static function batch(PDO $pdo, array $cfg): string
    {
        require_once __DIR__ . '/ControlGauges.php';
        $b = ControlGauges::latestBatch($pdo, $cfg);   // today's, or the latest while it waits for its OK (the same rule as the strip)
        if (!$b || empty($b['lines'])) return '';
        $lines = (array) $b['lines'];
        $body = ($b['date'] === gmdate('Y-m-d') ? "Today's batch: " : 'The batch of ' . date('M j', (int) strtotime($b['date'])) . ': ') . count($lines) . ' release' . (count($lines) === 1 ? '' : 's') . " waiting for your OK.\n\n";
        foreach ($lines as $l) $body .= '- ' . $l['release'] . ': ' . $l['what'] . ($l['why'] !== '' ? "\n  Why: " . $l['why'] : '') . ($l['checks'] !== '' ? "\n  Checks on staging: " . $l['checks'] : '') . ($l['way_back'] !== '' ? "\n  Way back: " . $l['way_back'] : '') . "\n";
        $body .= "\nApprove it on the Work queue: " . self::DEV . "\nOr reply to this email.";
        return self::once($pdo, 'batch:' . $b['date'], 'founder.batch', ['thread' => 'batch:' . $b['date'], 'url' => '/admin/config/control?tab=development'],
            ['title' => ($b['date'] === gmdate('Y-m-d') ? "Today's batch (" : 'The batch (') . date('M j', (int) strtotime($b['date'])) . '): ' . count($lines) . ' release' . (count($lines) === 1 ? '' : 's') . ' for your OK', 'body' => $body])
            ? 'Emailed the Founder the batch of ' . $b['date'] . '.' : '';
    }

    /** A Founder ruling (Tier 5) now waiting for the Founder (GaugeTasks::sendBrief), once per ticket, on its thread task:<id>. */
    public static function decision(PDO $pdo, int $id): string
    {
        require_once __DIR__ . '/GaugeTasks.php';
        $t = GaugeTasks::task($pdo, $id); $b = GaugeTasks::brief($pdo, $id);
        if (!$t || ($t['ticket'] ?? '') !== 'resolution' || ($t['stage'] ?? '') !== 'waiting' || !$b) return '';
        $f = fn ($k) => trim((string) ($b[$k] ?? ''));
        $body = 'A Founder ruling (Tier 5) waits for you: ' . $t['what'] . "\n\nWhy it needs you: " . $f('why_you')
            . "\n\nOption A: " . $f('a_text') . "\n  For: " . $f('a_pros') . "\n  Against: " . $f('a_cons')
            . "\n\nOption B: " . $f('b_text') . "\n  For: " . $f('b_pros') . "\n  Against: " . $f('b_cons')
            . ($f('recommend') !== '' ? "\n\nRecommended: Option " . strtoupper($f('recommend')) : '')
            . ($f('delay_impact') !== '' ? "\n\nIf it waits: " . $f('delay_impact') : '') . ($f('cost') !== '' ? "\nCost: " . $f('cost') : '')
            . "\n\nAnswer on its page: " . self::DEV . '#wq-' . $id . "\nOr reply to this email with A, B or your own words.";
        return self::once($pdo, 'decision:' . $id, 'founder.decision', ['thread' => 'task:' . $id, 'url' => '/admin/config/control?tab=development#wq-' . $id],
            ['title' => 'Founder ruling for you: ' . mb_substr((string) $t['what'], 0, 150), 'body' => $body]) ? 'Emailed the Founder task #' . $id . '\'s decision.' : '';
    }

    /** A message's own link, when it's one of ours: a /path on our site, or https on traversence.com, staging.trvgo.us or
     *  github.com/degraffics/ (a file in our repos). Anything else is dropped, never sent. */
    public static function linkOf($l): string
    {
        $l = trim((string) $l);
        // every match is read as === 1, so a pattern that fails to compile drops the link, never passes it
        if ($l === '' || strlen($l) > 500 || preg_match('/[\s<>"\\\\]/', $l) !== 0) return '';
        if (preg_match('#^/(?!/)#', $l) === 1) return $l;
        return preg_match('#^https://(traversence\.com|staging\.trvgo\.us)(/|$)#', $l) === 1
            || preg_match('#^https://github\.com/degraffics/[A-Za-z0-9._-]+(/|$)#', $l) === 1 ? $l : '';
    }

    /** Where a message's "Open it" goes (the session manager, 2026-11-27, option (a)): its own link; else its Work queue
     *  ticket (task), as a decision's email does; else the Work queue. */
    public static function urlOf(array $m): string
    {
        $l = self::linkOf($m['link'] ?? '');
        if ($l !== '') return $l;
        $task = (int) ($m['task'] ?? 0);
        return '/admin/config/control?tab=development' . ($task > 0 ? '#wq-' . $task : '');
    }

    /** The session manager's messages (gauges.json's "messages"), each id once. */
    public static function messages(PDO $pdo, array $cfg): array
    {
        $out = [];
        foreach (array_slice((array) ($cfg['messages'] ?? []), 0, 20) as $m) {
            if (!is_array($m) || !preg_match('/^[A-Za-z0-9._:-]{1,80}$/', (string) ($m['id'] ?? '')) || trim((string) ($m['subject'] ?? '')) === '') continue;
            if (self::once($pdo, 'message:' . $m['id'], 'founder.message', ['thread' => 'message:' . $m['id'], 'url' => self::urlOf($m)],
                ['title' => mb_substr(trim((string) $m['subject']), 0, 200), 'body' => mb_substr(trim((string) ($m['body'] ?? '')), 0, 2000)])) $out[] = 'Emailed the Founder message ' . $m['id'] . '.';
        }
        return $out;
    }

    /** The job: today's batch and the session manager's messages (decisions are sent as they're reached, by sendBrief). */
    public static function tick(PDO $pdo): array
    {
        require_once __DIR__ . '/Staging.php'; if (Staging::here()) return [];
        require_once __DIR__ . '/ControlGauges.php';
        $cfg = ControlGauges::config($pdo, true);
        return array_values(array_filter(array_merge([self::batch($pdo, $cfg)], self::messages($pdo, $cfg))));
    }
}
