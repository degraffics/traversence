<?php
// app/lib/ReleaseKind.php — is a release really the low-risk kind it says it is? (decisions/0179 rule 2, its "Who classifies"
// line, and its note "Piece 3's decisions"; handoffs/piece3-check.md §4, P3-3.) A release's release.json names its kind;
// this proves it on the server, where both copies are: each file the release brings (app/storage/releases/<id>/new/)
// against the copy live has now. A kind that isn't proved is "needs approval" (0179: "a release that is not clearly
// low-risk counts as needing approval"). Nothing that touches a Tier 3 area is ever low-risk (TeamRunner::touches).
//   faq           only app/ui/faq.md; every question already there keeps its heading (the anchors, 0057)
//   wording       only words: in each changed PHP file the code is the same token for token, and each changed string or
//                 page text is a sentence whose tags, attributes, paths and SQL are the same
//   phone_layout  only CSS, and only inside @media (max-width: …) blocks for phones (767px or less, 0058 §11)
//   additive      0179's "safe additive": every changed file only gains lines; new migrations only CREATE TABLE IF NOT
//                 EXISTS or a guarded ADD COLUMN; nothing a live caller uses is changed or removed
//   data_fix      recognised; stays in the daily batch for now (Jason, DB3: "a row limit comes later, Jason's choice")
declare(strict_types=1);

final class ReleaseKind
{
    public const KINDS = ['faq' => 'the FAQ', 'wording' => 'wording', 'phone_layout' => 'a phone-layout tweak', 'additive' => 'a safe additive change', 'data_fix' => 'a data fix'];

    /**
     * ['kind' => k, 'ok' => bool, 'why' => words]. $files: [path => [new text, live text or null when the file is new]];
     * $kind: what its release.json says ('' none).
     */
    public static function prove(string $kind, array $files): array
    {
        $no = fn (string $why) => ['kind' => $kind, 'ok' => false, 'why' => $why];
        if ($kind === '') return $no('Its release.json names no kind: it needs approval.');
        if (!isset(self::KINDS[$kind])) return $no('"' . mb_substr($kind, 0, 30) . '" isn\'t one of the low-risk kinds (0179 rule 2).');
        if (!$files) return $no('It carries no files.');
        if ($kind === 'data_fix') return $no('Data fixes stay in the daily batch for now (Jason, 2026-11-25: DB3).');
        if (($t3 = self::tier3(array_keys($files), $files)) !== '') return $no('It touches a Tier 3 area (' . $t3 . '): never low-risk.');
        $why = match ($kind) {
            'faq' => self::faq($files),
            'wording' => self::wording($files),
            'phone_layout' => self::phone($files),
            'additive' => self::additive($files),
        };
        return $why === '' ? ['kind' => $kind, 'ok' => true, 'why' => 'Proved: ' . self::KINDS[$kind] . ', and nothing in a Tier 3 area.'] : $no($why);
    }

    /** The Tier 3 area a release's files touch (its pages, libraries, migrations and their tables), or ''. */
    public static function tier3(array $paths, array $files = []): string
    {
        require_once __DIR__ . '/TeamRunner.php';
        $tg = [];
        foreach ($paths as $p) {
            if (str_starts_with($p, 'website/')) $tg[] = 'path:/' . substr($p, 8);
            if (preg_match('#^app/lib/([A-Za-z]+)\.php$#', $p, $m)) $tg[] = 'lib:' . $m[1];
            if (str_starts_with($p, 'app/migrations/')) {
                $tg[] = 'path:' . $p;
                if (preg_match_all('/\b(?:TABLE|INTO|UPDATE|FROM|JOIN)\s+`?([a-z_0-9]+)`?/i', (string) ($files[$p][0] ?? ''), $mm)) foreach ($mm[1] as $t) $tg[] = 'table:' . strtolower($t);
            }
            if (str_starts_with($p, 'trvgo/')) $tg[] = 'path:' . $p;
        }
        return TeamRunner::touches($tg);
    }

    private static function faq(array $files): string
    {
        if (array_keys($files) !== ['app/ui/faq.md']) return 'A FAQ release carries only app/ui/faq.md.';
        [$new, $old] = $files['app/ui/faq.md'];
        $h = fn ($t) => preg_match_all('/^### .+$/m', (string) $t, $m) ? $m[0] : [];
        $gone = array_diff($h($old), $h($new));
        return $gone ? 'A question\'s heading changed, so its link changes: ' . mb_substr(reset($gone), 4, 80) : '';
    }

    private static function wording(array $files): string
    {
        foreach ($files as $p => [$new, $old]) {
            if ($old === null) return $p . ' is a new file: not only words.';
            if ($new === $old) continue;
            if (!str_ends_with($p, '.php')) return $p . ' isn\'t PHP: wording is the words in pages and their parts.';
            try { $a = token_get_all((string) $old); $b = token_get_all((string) $new); } catch (Throwable $x) { return $p . ' doesn\'t parse.'; }
            if (count($a) !== count($b)) return $p . ': its code changed, not only its words.';
            foreach ($a as $i => $ta) {
                $tb = $b[$i];
                if (is_string($ta) || is_string($tb)) { if ($ta !== $tb) return $p . ': its code changed, not only its words.'; continue; }
                if ($ta[0] !== $tb[0]) return $p . ': its code changed, not only its words.';
                if ($ta[1] === $tb[1]) continue;
                if (!in_array($ta[0], [T_CONSTANT_ENCAPSED_STRING, T_INLINE_HTML, T_ENCAPSED_AND_WHITESPACE], true)) return $p . ' line ' . $ta[2] . ': a name or number changed, not words.';
                if (($w = self::onlyWords((string) $ta[1], (string) $tb[1])) !== '') return $p . ' line ' . $ta[2] . ': ' . $w;
            }
        }
        return '';
    }

    /** '' when two strings differ only in their sentences: the same tags and attributes, no path, URL, code or SQL changed. */
    private static function onlyWords(string $a, string $b): string
    {
        $tags = fn ($s) => preg_match_all('/<[^>]*>|\b(?:href|src|data-[a-z-]+|class|id|name|action)=/i', $s, $m) ? $m[0] : [];
        if ($tags($a) !== $tags($b)) return 'a tag or attribute changed, not only words.';
        foreach ([$a, $b] as $s) {
            $text = trim(strip_tags($s), " \t\n\r'\"");
            if (preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|FROM|WHERE|DROP|ALTER)\b/', $text)) return 'it reads like SQL: not wording.';
            if ($text !== '' && !preg_match('/[A-Za-z]{2,}\s+[A-Za-z]/', $text) && preg_match('#[/_.:\\\\$]|^\w+$#', $text)) return 'it reads like a name, path or key: not wording.';
        }
        return '';
    }

    private static function phone(array $files): string
    {
        foreach ($files as $p => [$new, $old]) {
            if (!str_ends_with($p, '.css')) return $p . ' isn\'t a stylesheet: a phone-layout tweak changes CSS only.';
            if ($old === null) return $p . ' is a new stylesheet: not a tweak.';
            if (self::outsidePhone((string) $new) !== self::outsidePhone((string) $old)) return $p . ': it changes rules outside the phone\'s media queries (max-width 767px or less).';
        }
        return '';
    }

    /** A stylesheet with its phone-only @media blocks (max-width 767px or less) taken out, spaces normalised. */
    public static function outsidePhone(string $css): string
    {
        $out = ''; $i = 0; $n = strlen($css);
        while ($i < $n) {
            if (preg_match('/\G@media[^{]*max-width\s*:\s*(\d+)px[^{]*\{/A', $css, $m, 0, $i) && (int) $m[1] <= 767) {
                $j = $i + strlen($m[0]); $depth = 1;
                while ($j < $n && $depth > 0) { if ($css[$j] === '{') $depth++; elseif ($css[$j] === '}') $depth--; $j++; }
                $i = $j; continue;
            }
            $out .= $css[$i]; $i++;
        }
        return preg_replace('/\s+/', ' ', trim($out));
    }

    private static function additive(array $files): string
    {
        foreach ($files as $p => [$new, $old]) {
            if (str_starts_with($p, 'app/migrations/')) {
                $sql = preg_replace('/--[^\n]*/', '', (string) $new);
                if (preg_match('/\b(UPDATE|DELETE|INSERT|DROP|TRUNCATE|RENAME|MODIFY|CHANGE)\b/i', $sql, $m)) return $p . ': a migration that ' . strtoupper($m[1]) . 's isn\'t additive.';
                if (preg_match_all('/\bALTER\s+TABLE\b[^;\']*/i', $sql, $mm)) foreach ($mm[0] as $alt) if (!preg_match('/\bADD\s+(COLUMN|INDEX|KEY)\b/i', $alt)) return $p . ': an ALTER that doesn\'t only add.';
                continue;
            }
            if ($old === null) continue;   // a new file adds
            if (!self::onlyAdds((string) $old, (string) $new)) return $p . ': a line live has was changed or removed, not only added to.';
        }
        return '';
    }

    /** Does $new keep every line of $old, in order (lines only added)? */
    public static function onlyAdds(string $old, string $new): bool
    {
        $a = explode("\n", str_replace("\r", '', $old)); $b = explode("\n", str_replace("\r", '', $new));
        $j = 0; $nb = count($b);
        foreach ($a as $line) { while ($j < $nb && $b[$j] !== $line) $j++; if ($j >= $nb) return false; $j++; }
        return true;
    }
}
