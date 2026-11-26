<?php
// app/lib/HeldNames.php — the names the site holds back, as keyed hashes only (Piece 3, P3-5; Jason, 2026-11-25, Brief B:
// "verify Decision 0066 via a locked read against live-protected places rather than adding fake test records"). The
// before-and-after check (tools/page-audit/beforeafter.mjs) hashes the phrases on each page the same way and blocks a
// release when a held name shows after it and didn't before. Never a name, never a place: each hash is an HMAC keyed with
// the checks' token, so the list says nothing to anyone without it. Held: a landmark no visitor sees by name (waiting,
// hidden, closed by a nation; through Landmarks::visibleSql), less any name a visible or story-only landmark shares (a
// hidden duplicate of a public place isn't a secret). Names of one word are left out: as page words they'd match too much.
declare(strict_types=1);

require_once __DIR__ . '/Landmarks.php';

final class HeldNames
{
    public const MAX = 5000;   // the most hashes in one answer

    /** A name or a page's phrase reduced the way beforeafter.mjs reduces it: lower case, letters and digits, single spaces. */
    public static function norm(string $s): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower($s)));
    }

    /** One hash: the first 24 hex of HMAC-SHA256(the reduced name, key). */
    public static function hash(string $name, string $key): string
    {
        return substr(hash_hmac('sha256', self::norm($name), $key), 0, 24);
    }

    /** ['hashes' => [...], 'count' => n, 'skipped_one_word' => n]: sorted, so the answer says nothing by its order. */
    public static function hashes(PDO $pdo, string $key): array
    {
        if ($key === '' || !Landmarks::ready($pdo)) return ['hashes' => [], 'count' => 0, 'skipped_one_word' => 0];
        $seen = [];
        foreach ($pdo->query("SELECT name FROM landmarks WHERE " . Landmarks::visibleSql() . " OR status = '" . Landmarks::STORY_ONLY . "'")->fetchAll(PDO::FETCH_COLUMN) as $n) $seen[self::norm((string) $n)] = true;
        $out = []; $one = 0;
        $st = $pdo->query("SELECT name FROM landmarks WHERE NOT (" . Landmarks::visibleSql() . ") AND status <> '" . Landmarks::STORY_ONLY . "'");
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $n) {
            $k = self::norm((string) $n);
            if ($k === '' || isset($seen[$k])) continue;
            if (!str_contains($k, ' ')) { $one++; continue; }
            $out[self::hash($k, $key)] = true;
            if (count($out) >= self::MAX) break;
        }
        $h = array_keys($out); sort($h);
        return ['hashes' => $h, 'count' => count($h), 'skipped_one_word' => $one];
    }
}
