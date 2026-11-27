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
        $body .= "\nOr reply to this email.";   // the button opens the batch (one link, the Founder's template; 0172 note)
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
            . "\n\nOr reply to this email with A, B or your own words.";   // the button opens its page
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

    // ---- the Founder's words, clean and on brand (Jason, 2026-11-27: "The push is clutter with code vs a clean on brand
    // message"; the standard is the CMO's (7) and Editorial's (12)). A body writes a link as [its name](address); a bare
    // address is named by where it goes. Links not ours (linkOf) keep only their name, never an address that leads off.

    private const BARE = '#https?://[^\s<>"\'()\[\]]+#';

    /** A body as pieces: ['t', text] or ['l', name, address ('' when it isn't ours)]. */
    public static function pieces(string $body): array
    {
        $out = []; $at = 0;
        if (preg_match_all('#\[([^\]\n]{1,120})\]\(([^)\s]{1,500})\)|https?://[^\s<>"\'()\[\]]+#', $body, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) return [['t', $body]];
        foreach ($mm as $x) {
            [$all, $pos] = $x[0];
            if (($x[1][1] ?? -1) >= 0) { $name = trim($x[1][0]); $url = $x[2][0]; }
            else { $all = rtrim($all, '.,;:!?'); $url = $all; $name = self::nameOf($url); }
            $own = ($x[1][1] ?? -1) >= 0 ? $name : '';   // the name the words gave it, before a phrase takes over
            $before = $pos > $at ? substr($body, $at, $pos - $at) : '';
            // a phrase before a link ("Approve it on the Work queue: …", "The comparison is ready: […](…)") is the link itself,
            // so a line says one thing and calls once (the Design reviewer, 2026-11-27)
            if (preg_match('/(^|[\n.!?]\s*)([^\n:.!?]{3,80}):\s*$/u', $before, $ph, PREG_OFFSET_CAPTURE) === 1) {
                $name = trim($ph[2][0]); $before = substr($before, 0, $ph[2][1]);
            }
            if ($before !== '') $out[] = ['t', $before];
            $out[] = ['l', $name, self::linkOf($url), $own];
            $at = $pos + strlen($all);
        }
        if ($at < strlen($body)) $out[] = ['t', substr($body, $at)];
        return $out;
    }

    /** A bare address's name, by where it goes (a suggestion for the CMO and Editorial to settle in the Design review). */
    public static function nameOf(string $url): string
    {
        $h = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($h === 'traversence.com' || $h === 'www.traversence.com') return 'Open it on Traversence';
        if ($h === 'staging.trvgo.us') return 'Open it on staging';
        if ($h === 'github.com') return 'Open it on GitHub';
        return $h !== '' ? $h : 'a link';
    }

    /** The text part: each of our links as "its name: its address" (mail apps without HTML need the address). */
    public static function textOf(string $body, string $except = ''): string
    {
        $t = ''; $after = false;   // after an address, its line ends there: a mail app would take a full stop into the link
        foreach (self::pieces($body) as $p) {
            if ($p[0] === 't') { $x = $p[1]; if ($after && preg_match('/^[.,;:!?]+[ \t]*/', $x, $q) === 1) { $x = substr($x, strlen($q[0])); $x = ($x !== '' && $x[0] !== "\n" ? "\n" : '') . $x; } $t .= $x; $after = false; continue; }
            $to = $p[2] !== '' && !self::same($p[2], $except);   // the button's own place: words only, the button carries it
            $t .= $to ? $p[1] . ': ' . (str_starts_with($p[2], '/') ? 'https://traversence.com' . $p[2] : $p[2]) : $p[1];
            $after = $to;
        }
        return $t;
    }

    /** The body without a first line that only repeats the title ("<title>: what each costs…" → "What each costs…"). */
    public static function untitled(string $body, string $title): string
    {
        $title = trim($title); $b = ltrim($body);
        if ($title === '' || mb_stripos($b, $title) !== 0) return $body;
        // only a title that ends where the words pause (":", ".", a dash or the line's end), never one that starts a sentence
        if (preg_match('/^\s*([:.\-]|\x{2014}|\n|$)/u', mb_substr($b, mb_strlen($title))) !== 1) return $body;
        $rest = ltrim(mb_substr($b, mb_strlen($title)), " \t:.,;-");
        if ($rest === '' || $rest[0] === "\n") return ltrim($rest);
        return mb_strtoupper(mb_substr($rest, 0, 1)) . mb_substr($rest, 1);
    }

    /** Where Open it goes, by name: "Open it on the Work queue", "Open it on GitHub", … (the Design reviewer, 2026-11-27:
     *  the button names its place). */
    public static function openLabel(string $link): string
    {
        if (str_contains($link, '/admin/config/control?tab=development')) return 'Open it on the Work queue';
        return self::nameOf(str_starts_with($link, '/') ? 'https://traversence.com' . $link : $link);
    }

    /** A push's one plain sentence: links as their names, no address and no `code`, at most 140 characters, and never the
     *  title again. */
    public static function pushLine(string $body, string $title = ''): string
    {
        $body = self::untitled($body, $title);
        $t = '';
        foreach (self::pieces($body) as $p) $t .= $p[1];   // a link is its name
        $t = (string) preg_replace('/`[^`\n]*`/u', '', $t);
        $t = (string) preg_replace(self::BARE, '', $t);
        $t = trim((string) preg_replace('/\s+/u', ' ', $t));
        if (preg_match('/^(.+?[.!?])(\s|$)/u', $t, $x) === 1) $t = $x[1];
        $t = (string) preg_replace('/\s+([.,;:!?])/u', '$1', $t);
        if (mb_strlen($t) > 140) { $cut = mb_substr($t, 0, 139); $sp = mb_strrpos($cut, ' '); $t = rtrim($sp > 80 ? mb_substr($cut, 0, $sp) : $cut, ' ,;:') . '…'; }
        return $t;
    }

    /** The body in HTML: paragraphs, our links by their names, `code` as code. */
    public static function htmlOf(string $body, string $font = '', string $except = ''): string
    {
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
        $h = '';
        foreach (self::pieces($body) as $p) {
            if ($p[0] === 't') { $h .= (string) preg_replace('/`([^`\n]*)`/u', '<code>$1</code>', $e($p[1])); continue; }
            $h .= $p[2] !== '' && !self::same($p[2], $except) ? '<a href="' . $e(str_starts_with($p[2], '/') ? 'https://traversence.com' . $p[2] : $p[2]) . '" style="color:#92400E;text-decoration:underline">' . $e($p[1]) . '</a>' : $e($p[1]);
        }
        $out = '';
        foreach (preg_split('/\n{2,}/', trim($h)) as $para) if (trim($para) !== '') $out .= '<p style="margin:0 0 12px;' . $font . 'font-size:15px;line-height:22px;color:#3E2B1F">' . nl2br($para, false) . '</p>';
        return $out;
    }

    /** Notify's email for a founder.* kind: [text, html]. Its title once, the body without it, Open it naming its place,
     *  and the Founder's footer in both parts (the Design reviewer, 2026-11-27). */
    public const FOOTER = 'You get this email because you\'re the Founder.';
    public const CHOICES = 'https://traversence.com/admin/dashboard/comms';

    public static function emailParts(string $title, string $body, string $link, string $text = '', string $thing = '', string $from = ''): array
    {
        $body = self::untitled($body, $title);
        $open = $thing !== '' ? self::buttonOf($thing, $link, $body) : self::openLabel($link);
        $text = trim($title) . "\n" . ($from === 'ceo' ? "From the CEO\n" : '') . "\n" . (trim($body) !== '' ? trim(self::textOf($body, $link)) . "\n\n" : '') . $open . ': ' . $link . "\n\n"
              . "-- \n" . self::FOOTER . ' Change what reaches you: ' . self::CHOICES . "\nTraversence";
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
        $font = 'font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;';
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#EFE3C8" style="background:#EFE3C8"><tr><td align="center" style="padding:24px 12px">'
              . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#FFFBF2" style="width:100%;max-width:560px;background:#FFFBF2;border:1px solid #CBB98D;border-radius:12px">'
              . '<tr><td bgcolor="#3E2B1F" style="padding:14px 28px;border-radius:12px 12px 0 0;font-family:Georgia,serif;font-size:20px;font-weight:700;color:#F7EFDC">Traversence</td></tr>'
              . '<tr><td style="padding:24px 28px 10px;' . $font . 'font-size:18px;font-weight:700;line-height:24px;color:#3E2B1F">' . $e($title) . '</td></tr>'
              . ($from === 'ceo' ? '<tr><td style="padding:0 28px 10px;' . $font . 'font-size:13px;line-height:18px;color:#8A7355">From the CEO</td></tr>' : '')
              . '<tr><td style="padding:0 28px 6px">' . self::htmlOf($body, $font, $link) . '</td></tr>'
              . '<tr><td style="padding:6px 28px 24px"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td bgcolor="#92400E" style="border-radius:8px;padding:12px 26px">'
              . '<a href="' . $e($link) . '" style="display:inline-block;' . $font . 'font-size:16px;font-weight:700;color:#FFFBF2;text-decoration:none"><span style="color:#FFFBF2;text-decoration:none">' . $e($open) . '</span></a></td></tr></table></td></tr>'
              . '<tr><td style="padding:12px 28px;border-top:1px solid #EADFC4;' . $font . 'font-size:12px;line-height:18px;color:#8A7355">' . $e(self::FOOTER) . ' '
              . '<a href="' . self::CHOICES . '" style="color:#8A7355;text-decoration:underline">Change what reaches you</a></td></tr>'
              . '</table></td></tr></table>';
        return [$text, $html];
    }

    /** Pulse's body for a founder.* kind (Notify::BODIES): the words with their links named, and Open it. */
    public static function pulseBody(PDO $pdo, array $m, int $viewer): array
    {
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
        $html = '';
        foreach (preg_split('/\n{2,}/', trim(self::untitled((string) $m['body'], (string) ($m['title'] ?? '')))) as $para) if (trim($para) !== '') {
            $h = ''; foreach (self::pieces($para) as $p) $h .= $p[0] === 't' ? (string) preg_replace('/`([^`\n]*)`/u', '<code>$1</code>', $e($p[1])) : ($p[2] !== '' && !self::same($p[2], (string) ($m['url'] ?? '')) ? '<a href="' . $e($p[2]) . '">' . $e($p[1]) . '</a>' : $e($p[1]));
            $html .= '<p>' . nl2br($h, false) . '</p>';
        }
        if ((string) ($m['url'] ?? '') !== '') $html .= '<p class="tv-msg-acts"><a class="tv-btn" href="' . $e($m['url']) . '">' . $e(self::buttonOf((string) ($m['thing'] ?? ''), (string) $m['url'], (string) $m['body'])) . '</a></p>';
        return ['html' => $html, 'state' => ''];
    }

    /** Who a message is from, by its thing ("message:<id>"): "ceo" when batch.json's message says so, else '' (the 0172
     *  note, 2026-11-27: "From the CEO" under the title for the CEO's messages only). */
    public static function fromOf(PDO $pdo, string $thing): string
    {
        if (!str_starts_with($thing, 'message:')) return '';
        try {
            require_once __DIR__ . '/ControlGauges.php';
            foreach ((array) (ControlGauges::config($pdo, true)['messages'] ?? []) as $m)
                if (is_array($m) && 'message:' . ($m['id'] ?? '') === $thing) return ($m['from'] ?? '') === 'ceo' ? 'ceo' : '';
        } catch (Throwable $x) {}
        return '';
    }

    /** Whether a link in the words goes where the button goes (one link to a place: the button). */
    public static function same(string $a, string $b): bool
    {
        $f = fn ($u) => str_starts_with($u, '/') ? 'https://traversence.com' . $u : $u;
        return $a !== '' && $f($a) === $f($b);
    }

    /** The one button, named for what it opens (the Founder's template, the 0172 note; Design, 2026-11-27): today's batch,
     *  the decision, or the message's own link by its name in the words, else by where it goes. */
    public static function buttonOf(string $thing, string $link, string $body = ''): string
    {
        if (str_starts_with($thing, 'batch:')) return substr($thing, 6) === gmdate('Y-m-d') ? 'Open today\'s batch' : 'Open the batch';
        if (str_starts_with($thing, 'decision:')) return 'Open the decision';
        foreach (self::pieces($body) as $p) if ($p[0] === 'l' && $p[2] !== '' && self::same($p[2], $link) && ($p[3] ?? '') !== '') return 'Open ' . lcfirst((string) preg_replace('/^Open\s+/i', '', $p[3]));
        return self::openLabel($link);
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
