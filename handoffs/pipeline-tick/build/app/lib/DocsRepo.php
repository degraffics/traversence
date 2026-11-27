<?php
// app/lib/DocsRepo.php — the decision records (ADRs) in the docs repo, degraffics/traversence on GitHub, read and edited from
// the admin (Jason, 2026-11-12: "can we access the adr within the system and edit them to git. this could be an adr
// manager?"; saving: "Straight to main"). System Configuration → Decisions.
//
// Reading needs no key: the files come from raw.githubusercontent.com (public repo), kept 10 minutes in app/storage/docs/.
// Saving commits straight to main through GitHub's contents API with GITHUB_DOCS_TOKEN (.env, set by Jason in File Manager:
// a fine-grained token for degraffics/traversence, Contents read and write). No token: everything reads, nothing saves, and
// the page says so. GitHub's API costs nothing (CLAUDE.md "No income, no expense"). A save names the person in the commit
// message and the action log; the commit itself is the token's owner's.
declare(strict_types=1);

final class DocsRepo
{
    public const REPO = 'degraffics/traversence';
    public const BRANCH = 'main';
    private const TTL = 600;   // seconds a file read is kept

    public static function token(): string { return trim((string) (getenv('GITHUB_DOCS_TOKEN') ?: '')); }
    public static function canSave(): bool { return self::token() !== ''; }
    public static function webUrl(string $path): string { return 'https://github.com/' . self::REPO . '/blob/' . self::BRANCH . '/' . $path; }
    public static function historyUrl(string $path): string { return 'https://github.com/' . self::REPO . '/commits/' . self::BRANCH . '/' . $path; }

    private static function dir(): string
    {
        $d = __DIR__ . '/../storage/docs';
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }

    /** One request to GitHub: [status, body]. */
    /** Tests only (cli with TV_TEST): answers GitHub in place of the network, fn(method, url, json): [code, body]. */
    public static $fake = null;

    private static function http(string $method, string $url, ?array $json = null, bool $auth = false): array
    {
        if (self::$fake !== null && PHP_SAPI === 'cli' && getenv('TV_TEST')) return (self::$fake)($method, $url, $json);
        $h = ['User-Agent: traversence-admin', 'Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
        if ($auth && self::token() !== '') $h[] = 'Authorization: Bearer ' . self::token();
        $body = $json !== null ? json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        if ($body !== '') $h[] = 'Content-Type: application/json';
        require_once __DIR__ . '/StagingGate.php'; if (($w = StagingGate::hold($url, 'GitHub docs ' . $method)) !== null) return [0, $w];   // staging never reads or writes git (decisions/0165)
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => $h, CURLOPT_FOLLOWLOCATION => true] + ($body !== '' ? [CURLOPT_POSTFIELDS => $body] : []));
        $res = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return [$code, is_string($res) ? $res : ''];
    }

    /** A file of the repo as it is on main (kept 10 minutes; $fresh reads it again). null: not there or GitHub didn't answer. */
    public static function raw(string $path, bool $fresh = false): ?string
    {
        if (!preg_match('#^[A-Za-z0-9._/\-]+$#', $path) || str_contains($path, '..')) return null;
        $f = self::dir() . '/' . md5($path) . '.txt';
        if (!$fresh && is_file($f) && time() - (int) filemtime($f) < self::TTL) return (string) file_get_contents($f);
        [$code, $body] = self::http('GET', 'https://raw.githubusercontent.com/' . self::REPO . '/' . self::BRANCH . '/' . $path);
        if ($code === 200) { @file_put_contents($f, $body, LOCK_EX); return $body; }
        return is_file($f) ? (string) file_get_contents($f) : null;   // GitHub away: the last copy read
    }

    /**
     * A file of the repo as it is on main this moment, read through GitHub's API (the contents endpoint, with the key when
     * there is one), never kept and never from raw.githubusercontent.com's cache. null: not on main (404). GitHub away or
     * refusing: raw()'s fresh read instead, so nothing reads worse than before. (2026-11-27: #317 and #318 each waited an
     * hour on staging's way with their before verdict on main; the raw reads from :00 on came back empty until the late re-ask.)
     */
    public static function now(string $path): ?string
    {
        if (!preg_match('#^[A-Za-z0-9._/\-]+$#', $path) || str_contains($path, '..')) return null;
        [$code, $body] = self::http('GET', 'https://api.github.com/repos/' . self::REPO . '/contents/' . $path . '?ref=' . self::BRANCH, null, true);
        if ($code === 200) {
            $j = json_decode($body, true);
            if (is_array($j) && ($j['encoding'] ?? '') === 'base64' && isset($j['content'])) { $raw = base64_decode((string) $j['content'], true); if ($raw !== false) return $raw; }
        }
        if ($code === 404) return null;
        return self::raw($path, true);
    }

    /** Forget the kept copies of these files (after a save), so the next read is GitHub's. */
    public static function forget(string ...$paths): void { foreach ($paths as $p) @unlink(self::dir() . '/' . md5($p) . '.txt'); }

    /**
     * Every decision, from the register (decisions/index.md, rebuilt by the site audit twice a day), newest records the
     * repo has added since from the bundled list: [num => [num, title, file, status, replaced_by, notes]].
     */
    public static function all(): array
    {
        $out = [];
        $idx = self::raw('decisions/index.md');
        if ($idx !== null && preg_match_all('/^\|\s*(\d{4})\s*\|\s*\[(.+?)\]\((\d{4}-[^)]+\.md)\)\s*\|\s*([^|]*)\|\s*([^|]*)\|\s*([^|]*)\|/m', $idx, $m, PREG_SET_ORDER)) {
            foreach ($m as $r) $out[$r[1]] = ['num' => $r[1], 'title' => trim($r[2]), 'file' => 'decisions/' . $r[3], 'status' => trim($r[4]), 'replaced_by' => trim($r[5]), 'notes' => (int) trim($r[6])];
        }
        $f = __DIR__ . '/../data/decisions-index.php';
        if (is_file($f)) foreach ((array) require $f as $d) {
            if (($d[4] ?? '') === 'note' || isset($out[$d[0]])) continue;   // a note: found by the search, not a record of its own
            $out[$d[0]] = ['num' => $d[0], 'title' => $d[1], 'file' => $d[3], 'status' => '', 'replaced_by' => '', 'notes' => 0];
        }
        foreach (self::added() as $num => $d) if (!isset($out[$num])) $out[$num] = $d;
        ksort($out, SORT_STRING);
        return $out;
    }

    /** Decisions made here since the register was last rebuilt (kept until it lists them). */
    private static function added(): array
    {
        $f = self::dir() . '/added.json';
        return is_file($f) ? (array) json_decode((string) file_get_contents($f), true) : [];
    }

    public static function one(string $num): ?array
    {
        $all = self::all();
        $d = $all[$num] ?? null;
        if (!$d) return null;
        $d['text'] = self::raw($d['file']);
        return $d;
    }

    /** The number the next decision takes. */
    public static function nextNumber(): string
    {
        $all = self::all();
        return sprintf('%04d', $all ? (int) max(array_keys($all)) + 1 : 1);
    }

    /**
     * Saves a file straight to main (Jason, 2026-11-12). $base is the text the person started from: when main changed since,
     * nothing is saved and the error says so, so no one's edit is overwritten. ['ok' => true, 'url' => the commit] or
     * ['ok' => false, 'error' => what to do].
     */
    public static function save(string $path, string $text, string $message, ?string $base = null): array
    {
        if (!self::canSave()) return ['ok' => false, 'error' => 'Saving needs the GitHub key (GITHUB_DOCS_TOKEN in .env). Until it is there, decisions read here and are edited on GitHub.'];
        if (!preg_match('#^decisions/[0-9]{4}-[a-z0-9\-]+\.md$|^README\.md$#', $path)) return ['ok' => false, 'error' => 'Only decision records are saved from here.'];
        $api = 'https://api.github.com/repos/' . self::REPO . '/contents/' . $path;
        [$code, $body] = self::http('GET', $api . '?ref=' . self::BRANCH, null, true);
        $sha = null; $now = null;
        if ($code === 200) { $j = json_decode($body, true); $sha = (string) ($j['sha'] ?? ''); $now = base64_decode((string) ($j['content'] ?? '')); }
        elseif ($code !== 404) return ['ok' => false, 'error' => self::why($code, $body)];
        if ($base !== null && $now !== null && self::norm($now) !== self::norm($base)) {
            self::forget($path);
            return ['ok' => false, 'error' => 'Someone changed this decision on GitHub since you opened it. Your words are still in the box: copy them, open the decision again for the new version, and add them in.', 'changed' => true];
        }
        if ($base === null && $sha !== null && $path !== 'README.md') return ['ok' => false, 'error' => 'A decision with that number is already there. Open it instead, or start again for the next number.'];
        $in = ['message' => $message, 'content' => base64_encode(str_replace("\r\n", "\n", $text)), 'branch' => self::BRANCH];
        if ($sha) $in['sha'] = $sha;
        [$code, $body] = self::http('PUT', $api, $in, true);
        if ($code !== 200 && $code !== 201) return ['ok' => false, 'error' => self::why($code, $body)];
        self::forget($path, 'decisions/index.md');
        $j = json_decode($body, true);
        return ['ok' => true, 'url' => (string) ($j['commit']['html_url'] ?? self::historyUrl($path))];
    }

    /**
     * A release's record (decisions/0145 §5.9): deliveries/releases/<date>-<id>-<slug>.json, written over each time the
     * release moves (the history is git's). ['ok' => true, 'url'] or ['ok' => false, 'error'].
     */
    public static function saveRecord(string $path, string $text, string $message): array
    {
        if (!self::canSave()) return ['ok' => false, 'error' => 'No GITHUB_DOCS_TOKEN: the record stays on the server.'];
        if (!preg_match('#^deliveries/releases/[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]+-[a-z0-9\-]+\.json$#', $path)) return ['ok' => false, 'error' => 'Not a release record.'];
        $api = 'https://api.github.com/repos/' . self::REPO . '/contents/' . $path;
        // A release's steps can write its record a second apart (Release #301, 2026-11-26: "On staging" at 10:01:48, then
        // "Live"): the read can still give the file's last sha, and GitHub refuses the write as stale (409, or 422). Read it
        // again and write again, up to three times; any other answer stops at once.
        for ($try = 1; ; $try++) {
            [$code, $body] = self::http('GET', $api . '?ref=' . self::BRANCH . ($try > 1 ? '&t=' . time() . $try : ''), null, true);
            $sha = null;
            if ($code === 200) { $j = json_decode($body, true); $sha = (string) ($j['sha'] ?? ''); if (self::norm(base64_decode((string) ($j['content'] ?? ''))) === self::norm($text)) return ['ok' => true, 'url' => self::historyUrl($path)]; }
            elseif ($code !== 404) return ['ok' => false, 'error' => self::recordWhy($code, $body)];
            $in = ['message' => $message, 'content' => base64_encode(str_replace("\r\n", "\n", $text)), 'branch' => self::BRANCH];
            if ($sha) $in['sha'] = $sha;
            [$code, $body] = self::http('PUT', $api, $in, true);
            if ($code === 200 || $code === 201) { $j = json_decode($body, true); return ['ok' => true, 'url' => (string) ($j['commit']['html_url'] ?? self::historyUrl($path)), 'tries' => $try]; }
            if (($code !== 409 && $code !== 422) || $try >= 3) return ['ok' => false, 'error' => self::recordWhy($code, $body), 'tries' => $try];
            if (!(PHP_SAPI === 'cli' && getenv('TV_TEST'))) usleep(700000 * $try);
        }
    }

    /** Why a release record didn't save, in words for the Releases page (why() speaks of decisions). */
    private static function recordWhy(int $code, string $body): string
    {
        if ($code === 409 || $code === 422) return 'GitHub kept saying the record had changed meanwhile (three tries).';
        return self::why($code, $body);
    }

    // ---- the releases inbox (decisions/0159 §5, Phase 6): Claude's finished builds, as release zips in git ----------------

    public const INBOX = 'releases/inbox';

    /** The zips waiting in releases/inbox on main: [['path', 'name', 'sha' (git's blob id), 'size']]. null: GitHub didn't answer. */
    /** The inbox listing's last HTTP answer (0 when none was asked). */
    public static int $inboxCode = 0;

    public static function inbox(): ?array
    {
        if (!self::canSave()) return null;
        [$code, $body] = self::http('GET', 'https://api.github.com/repos/' . self::REPO . '/contents/' . self::INBOX . '?ref=' . self::BRANCH, null, true);
        self::$inboxCode = (int) $code;   // what GitHub answered, for the look's record (Releases::fromGit)
        if ($code === 404) return [];
        if ($code !== 200) return null;
        $out = [];
        foreach ((array) json_decode($body, true) as $f)
            if (($f['type'] ?? '') === 'file' && preg_match('/^[A-Za-z0-9._-]{1,120}\.zip$/', (string) ($f['name'] ?? '')) && preg_match('/^[a-f0-9]{40}$/', (string) ($f['sha'] ?? '')))
                $out[] = ['path' => self::INBOX . '/' . $f['name'], 'name' => (string) $f['name'], 'sha' => (string) $f['sha'], 'size' => (int) ($f['size'] ?? 0)];
        return $out;
    }

    /** One file of the repo by its git blob id, its bytes checked against that id. null: not fetched, or not what git says. */
    public static function blob(string $sha): ?string
    {
        if (!self::canSave() || !preg_match('/^[a-f0-9]{40}$/', $sha)) return null;
        [$code, $body] = self::http('GET', 'https://api.github.com/repos/' . self::REPO . '/git/blobs/' . $sha, null, true);
        if ($code !== 200) return null;
        $j = json_decode($body, true);
        $data = ($j['encoding'] ?? '') === 'base64' ? base64_decode(str_replace(["\n", "\r"], '', (string) ($j['content'] ?? '')), true) : null;
        if (!is_string($data) || sha1('blob ' . strlen($data) . "\0" . $data) !== $sha) return null;   // exactly the bytes in git
        return $data;
    }

    /** Takes a zip out of the inbox once it's a release (the history keeps it). ['ok'] or ['ok' => false, 'error']. */
    public static function takeFromInbox(string $path, string $sha, string $message): array
    {
        if (!self::canSave()) return ['ok' => false, 'error' => 'No GITHUB_DOCS_TOKEN.'];
        if (!preg_match('#^releases/inbox/[A-Za-z0-9._-]{1,120}\.zip$#', $path) || !preg_match('/^[a-f0-9]{40}$/', $sha)) return ['ok' => false, 'error' => 'Not an inbox zip.'];
        [$code, $body] = self::http('DELETE', 'https://api.github.com/repos/' . self::REPO . '/contents/' . $path, ['message' => $message, 'sha' => $sha, 'branch' => self::BRANCH], true);
        return $code === 200 || $code === 404 ? ['ok' => true] : ['ok' => false, 'error' => self::why($code, $body)];
    }

    /** Kept for the list until the register (rebuilt twice a day) names it. */
    public static function noteAdded(string $num, string $title, string $file): void
    {
        $a = self::added(); $a[$num] = ['num' => $num, 'title' => $title, 'file' => $file, 'status' => 'proposed', 'replaced_by' => '', 'notes' => 0];
        @file_put_contents(self::dir() . '/added.json', json_encode($a, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private static function norm(string $s): string { return rtrim(str_replace("\r\n", "\n", $s)); }

    private static function why(int $code, string $body): string
    {
        $m = (string) (json_decode($body, true)['message'] ?? '');
        if ($code === 401) return 'GitHub turned the key down (GITHUB_DOCS_TOKEN): it may have expired. Make a new one and put it in .env.';
        if ($code === 403 || $code === 404) return 'The GitHub key can\'t write to ' . self::REPO . ': give it Contents read and write on that repository.' . ($m !== '' ? ' (GitHub: ' . $m . ')' : '');
        if ($code === 409 || $code === 422) return 'GitHub says the file changed meanwhile: open the decision again and redo the change.';
        if ($code === 0) return 'GitHub didn\'t answer. Try again in a minute.';
        return 'GitHub answered ' . $code . ($m !== '' ? ': ' . $m : '') . '.';
    }

    /** A slug for a new decision's file name from its title. */
    public static function slug(string $title): string
    {
        $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-');
        return substr($s, 0, 60) ?: 'decision';
    }

    /** The notes added to a decision ("**Note (2026-11-12 …):** …"): [[date and heading, text]], oldest first. */
    public static function notes(string $text): array
    {
        $out = [];
        if (preg_match_all('/^\*\*(Note|Update|Amendment)\b([^*]*)\*\*:?\s*(.*)$/mi', $text, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($m as $r) {
                $start = $r[0][1];
                $rest = substr($text, $start);
                $para = preg_split('/\n\s*\n/', $rest, 2)[0];
                $head = trim($r[1][0] . $r[2][0]);
                $out[] = [rtrim($head, ': '), trim((string) preg_replace('/^\*\*[^*]*\*\*:?\s*/', '', $para))];
            }
        }
        return $out;
    }

    // ---- Markdown, enough for the decision records: headings, paragraphs, lists, tables, quotes, code, bold, links ----

    public static function html(string $md): string
    {
        $e = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $inline = function (string $s) use ($e): string {
            $s = $e($s);
            $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s);
            $s = preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', $s);
            $s = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/', '<i>$1</i>', $s);
            $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
                $u = html_entity_decode($m[2], ENT_QUOTES);
                if (preg_match('/^(\d{4})-[^\/]*\.md(#.*)?$/', $u, $d)) $u = '/admin/config/decisions?d=' . $d[1];   // another decision: opens here
                elseif (!preg_match('#^(https?://|/|\#)#', $u)) $u = 'https://github.com/' . self::REPO . '/blob/' . self::BRANCH . '/' . ltrim(preg_replace('#^\.\./#', '', $u), '/');
                $ext = str_starts_with($u, 'http');
                return '<a href="' . htmlspecialchars($u, ENT_QUOTES) . '"' . ($ext ? ' target="_blank" rel="noopener"' : '') . '>' . $m[1] . '</a>';
            }, $s);
            return (string) $s;
        };
        $lines = explode("\n", str_replace("\r\n", "\n", $md));
        $h = ''; $para = []; $list = null; $code = null; $tbl = [];
        $flushP = function () use (&$para, &$h, $inline) { if ($para) { $h .= '<p>' . $inline(implode(' ', $para)) . '</p>'; $para = []; } };
        $flushL = function () use (&$list, &$h) { if ($list !== null) { $h .= '</li></' . $list . '>'; $list = null; } };
        $flushT = function () use (&$tbl, &$h, $inline) {
            if (!$tbl) return;
            $rows = array_values(array_filter($tbl, fn ($r) => !preg_match('/^\|?\s*:?-{2,}/', $r)));
            $cell = fn ($r) => array_map('trim', explode('|', trim(trim($r), '|')));
            $h .= '<div class="dm-tw"><table class="ak-tbl"><thead><tr>' . implode('', array_map(fn ($c) => '<th>' . $inline($c) . '</th>', $cell(array_shift($rows)))) . '</tr></thead><tbody>';
            foreach ($rows as $r) $h .= '<tr>' . implode('', array_map(fn ($c) => '<td>' . $inline($c) . '</td>', $cell($r))) . '</tr>';
            $h .= '</tbody></table></div>'; $tbl = [];
        };
        foreach ($lines as $ln) {
            if ($code !== null) { if (preg_match('/^```/', $ln)) { $h .= '<pre><code>' . $e(implode("\n", $code)) . '</code></pre>'; $code = null; } else $code[] = $ln; continue; }
            if (preg_match('/^```/', $ln)) { $flushP(); $flushL(); $flushT(); $code = []; continue; }
            if (preg_match('/^\s*\|/', $ln)) { $flushP(); $flushL(); $tbl[] = $ln; continue; }
            $flushT();
            if (trim($ln) === '') { $flushP(); $flushL(); continue; }
            if (preg_match('/^(#{1,4})\s+(.*)$/', $ln, $m)) { $flushP(); $flushL(); $n = min(4, strlen($m[1]) + 1); $h .= '<h' . $n . ' class="dm-h">' . $inline($m[2]) . '</h' . $n . '>'; continue; }
            if (preg_match('/^(\s*)([-*]|\d+\.)\s+(.*)$/', $ln, $m)) {
                $flushP(); $tag = ctype_digit($m[2][0]) ? 'ol' : 'ul';
                if ($list !== $tag) { $flushL(); $h .= '<' . $tag . '><li>'; $list = $tag; } else $h .= '</li><li>';
                $h .= $inline($m[3]); continue;
            }
            if (preg_match('/^>\s?(.*)$/', $ln, $m)) { $flushP(); $flushL(); $h .= '<blockquote>' . $inline($m[1]) . '</blockquote>'; continue; }
            if ($list !== null && preg_match('/^\s{2,}\S/', $ln)) { $h .= ' ' . $inline(trim($ln)); continue; }
            $para[] = trim($ln);
        }
        if ($code !== null) $h .= '<pre><code>' . $e(implode("\n", $code)) . '</code></pre>';
        $flushP(); $flushL(); $flushT();
        return $h;
    }
}
