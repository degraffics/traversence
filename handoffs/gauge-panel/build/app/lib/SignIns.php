<?php
// app/lib/SignIns.php — which account each outside service signs in as (02 §10, the Setup & Access Capture rule; 04, the
// Vault's portal links; Jason, 2026-11-23: Google's card didn't say which Google account it uses, so its Billing link was a
// guess). An account's email is no secret, so it shows on the card; its password stays in the Vault. Kept in the site's
// state table (SearchIndex), one row per service, with who set it, when, and the value before it, so a change has Undo
// for a day. The portal links take the account where the provider accepts a hint (Google: authuser, and project on its
// console's pages), so they open in that account; elsewhere the card names the account beside the link.
declare(strict_types=1);

final class SignIns
{
    /** Hosts whose pages open in a given account from a parameter: host => [account param, project param or '']. */
    public const HINTS = [
        'console.cloud.google.com' => ['authuser', 'project'],
        'console.developers.google.com' => ['authuser', 'project'],
        'myaccount.google.com' => ['authuser', ''],
        'search.google.com' => ['authuser', ''],
    ];
    /** Pages on a hinted host that aren't a project's (Billing is the billing account's): no project parameter there. */
    private const NO_PROJECT = ['#^/billing#'];

    private static function key(string $svc): string { return 'signin:' . preg_replace('/[^a-z0-9_-]/', '', strtolower($svc)); }

    /** The account a service signs in as: ['email', 'project', 'by', 'at' (UTC), 'prev' => [email, project] | null] or null. */
    public static function get(PDO $pdo, string $svc): ?array
    {
        try { require_once __DIR__ . '/SearchIndex.php'; $v = json_decode(SearchIndex::state($pdo, self::key($svc)), true); }
        catch (Throwable $x) { return null; }
        return is_array($v) && (($v['email'] ?? '') !== '' || isset($v['prev'])) ? $v + ['email' => '', 'project' => '', 'by' => '', 'at' => '', 'prev' => null] : null;
    }

    /** Records it (an email; a project id for a service whose pages take one). Returns what the card says. */
    public static function set(PDO $pdo, string $svc, string $email, string $project, string $who): string
    {
        $email = strtolower(trim($email)); $project = strtolower(trim($project));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('That isn\'t an email address: type the account\'s sign-in email, e.g. name@example.com.');
        if ($project !== '' && !preg_match('/^[a-z][a-z0-9-]{4,29}$/', $project)) throw new InvalidArgumentException('That isn\'t a project id: it\'s the short id in the console\'s address (project=…), 6 to 30 letters, digits and dashes.');
        $was = self::get($pdo, $svc);
        $prev = $was && $was['email'] !== '' ? ['email' => (string) $was['email'], 'project' => (string) $was['project']] : ['email' => '', 'project' => ''];
        if ($prev['email'] === $email && $prev['project'] === $project) return 'Nothing changed: it already signs in as ' . $email . '.';
        self::write($pdo, $svc, ['email' => $email, 'project' => $project, 'by' => $who, 'at' => gmdate('Y-m-d H:i'), 'prev' => $prev]);
        return 'Recorded: it signs in as ' . $email . ($project !== '' ? ', project ' . $project : '') . '. Its links open in that account.';   // a record is edited, never undone (Jason, 2026-11-23; CLAUDE.md #1315): its card offers Edit, the value before it as History
    }

    /** Puts back the value before the last change (a day at most). No card offers it since 2026-11-23 (a record is edited, never
     * undone: Jason, CLAUDE.md #1315); kept until its removal is agreed (the zip check's --drops). */
    public static function undo(PDO $pdo, string $svc, string $who): string
    {
        $v = self::get($pdo, $svc);
        if (!$v || !is_array($v['prev'])) throw new RuntimeException('There\'s no change to undo.');
        if ($v['at'] !== '' && strtotime($v['at'] . ' UTC') < time() - 86400) throw new RuntimeException('A change can be undone for a day; this one is older. Record the account again instead.');
        self::write($pdo, $svc, ['email' => (string) $v['prev']['email'], 'project' => (string) $v['prev']['project'], 'by' => $who, 'at' => gmdate('Y-m-d H:i'), 'prev' => null]);
        return $v['prev']['email'] !== '' ? 'Undone: it signs in as ' . $v['prev']['email'] . ' again.' : 'Undone: its sign-in isn\'t recorded again.';
    }

    private static function write(PDO $pdo, string $svc, array $v): void
    {
        require_once __DIR__ . '/SearchIndex.php';
        SearchIndex::setState($pdo, self::key($svc), (string) json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** Can the undo still be pressed (a change in the last day with a value before it)? */
    public static function canUndo(?array $v): bool
    {
        return $v !== null && is_array($v['prev'] ?? null) && $v['at'] !== '' && strtotime($v['at'] . ' UTC') >= time() - 86400;
    }

    /**
     * A portal link opened in that account: [url, hinted]. Hinted when its host takes the account (Google's authuser, and
     * its project where the page is a project's); otherwise the URL as it was, for the card to name the account beside it.
     */
    public static function link(string $url, ?array $v): array
    {
        if (!$v || ($v['email'] ?? '') === '') return [$url, false];
        $p = parse_url($url); $host = strtolower((string) ($p['host'] ?? ''));
        if (!isset(self::HINTS[$host])) return [$url, false];
        [$ap, $pp] = self::HINTS[$host];
        parse_str((string) ($p['query'] ?? ''), $q);
        $q[$ap] = (string) $v['email'];
        $path = (string) ($p['path'] ?? '/');
        $noProj = false; foreach (self::NO_PROJECT as $re) if (preg_match($re, $path)) $noProj = true;
        if ($pp !== '' && ($v['project'] ?? '') !== '' && !$noProj) $q[$pp] = (string) $v['project'];
        return [($p['scheme'] ?? 'https') . '://' . $host . $path . '?' . http_build_query($q) . (isset($p['fragment']) ? '#' . $p['fragment'] : ''), true];
    }
}
