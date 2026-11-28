=== FILE: release.json ===
{
  "name": "dnl-0179-safe-additive-proof",
  "gate": "Daily batch (0179 rule 1). This release changes how ReleaseKind decides what counts as low-risk, so it is not a low-risk kind itself: it names no kind and needs Jason's approval in the batch. It is not sent to releases/inbox/ until the batch with it is approved. It is not agent-built, and it is not one of the four releases the before-and-after check was paused for.",
  "what": "SKIPPED FIRST, as the attached documents require. (1) Switching rule 2 on (the before_after and rule2_autopublish switches) waits for Piece 3 and for Jason, who presses staging's switches himself (0179, note 'Piece 3's decisions'). (2) The before-and-after check is paused for four releases (0179, note 2026-11-26 18:30 UTC). This release leaves that pause and the switch alone. (3) A row limit for data fixes is 'Jason's choice' (DB3). data_fix stays in the batch. (4) Brief A, whether a staging Publish should be before-and-after checked, is to be revisited with Jason before he raises agents to Confirmation. (5) The ruling on Gemini and internal text (0164 §0.8) waits for Jason. (6) Growing the low-risk list happens only by Jason's word. No kind is added. (7) M2b is paused and Order #12's books wait for Jason; neither is touched. REVIEW OF THE DRAFT: the attached job-19 draft (dnl-publish-authority-0179.md) is empty (0 bytes), so there was nothing to review and this release is built from the live files. Most of 0179 is already in the live code: the batch wait in Releases::autoPublish ('Waits for the daily batch'), ReleaseKind's proof of the low-risk kinds, the territories, and TeamRunner::confirmationWaits gating Confirmation on before_after and rule2_autopublish. One real gap remains between the decision's text and the code. 0179's note 'Piece 3's last two briefs' says an add-only migration is safe additive only when it is 'CREATE TABLE IF NOT EXISTS, or a guarded ADD COLUMN or INDEX'. ReleaseKind::additive() currently also passes (a) a CREATE TABLE without IF NOT EXISTS, which is not safe to run twice, (b) CREATE OR REPLACE, which replaces, and (c) an ALTER TABLE that mixes one ADD COLUMN with other clauses, such as ALTER COLUMN ... SET DEFAULT, CONVERT TO CHARACTER SET or ENGINE=. Its ALTER check also stops at the first quote, so clauses after a quoted DEFAULT were never read. This release tightens additive() to the decision's words. Every ALTER TABLE clause must be ADD COLUMN, ADD INDEX or ADD KEY, and a CREATE TABLE must say IF NOT EXISTS. Both are checked outside quoted strings and inside them, where a guarded ADD COLUMN keeps its ALTER for PREPARE. The change only narrows what passes: a release that no longer proves 'additive' counts as needing approval and waits for the batch. Nothing else in ReleaseKind changes, and TeamRunner is untouched, so the decision tree's fingerprint is unchanged.",
  "files": [
    "files/app/lib/ReleaseKind.php"
  ],
  "decisions": [
    "0179 rule 1 (the daily batch): this release goes through it",
    "0179 rule 2 and 'Who classifies': a release that is not clearly low-risk counts as needing approval",
    "0179 'Safe additive' and the note 'Piece 3's last two briefs': an add-only migration is CREATE TABLE IF NOT EXISTS, or a guarded ADD COLUMN or INDEX; any UPDATE, DELETE, DROP, MODIFY or RENAME stays Tier 3",
    "0180 §4: territories detected by what a change touches (unchanged here)",
    "0162 / 0145 §6: migrations only add (Releases::migrationChecks, unchanged)",
    "SKIPPED: switching rule 2 on (waits for Piece 3 and Jason's own press of staging's switches)",
    "SKIPPED: the before-and-after check's pause for four releases (paused; not touched)",
    "SKIPPED: data_fix row limit (DB3, Jason's choice)",
    "SKIPPED: Brief A revisit before Confirmation (waits for Jason)",
    "SKIPPED: Gemini internal-text ruling, 0164 §0.8 (waits for Jason)",
    "SKIPPED: a 'tests' kind in ReleaseKind. Tests are in rule 2's list, but release zips carry only app/, website/ and trvgo/ (Releases::pathOk), and where tests would ship isn't shown, so there is nothing to prove yet. This is a question for the lane, not a new kind."
  ],
  "tests": [
    "tests/releasekind-0179-tests.php"
  ],
  "risks": [
    "Nothing here was run, linted, tested or zip-checked. It is a draft for the lane session to verify, test and zip-check.",
    "The change narrows 'additive'. Patterns that passed before now count as needing approval and wait for the batch: an ALTER with ALGORITHM=/LOCK= clauses, a CREATE TABLE without IF NOT EXISTS, CREATE OR REPLACE, and ALTER COLUMN / CONVERT / ENGINE mixed with ADD. This is the safe direction, but an in-flight release that relied on 'additive' would now wait.",
    "If the site's 'guarded ADD COLUMN' uses a form this check doesn't recognise, guarded adds would stop proving 'additive' and would wait for the batch. The form recognised here is an ALTER inside a quoted string for PREPARE, or a bare ADD COLUMN. A non-ALTER guard statement (SET/PREPARE/EXECUTE/DEALLOCATE) is allowed, since only ALTER and CREATE are read. Check this against the live migrations.",
    "A standalone CREATE INDEX or CREATE VIEW (without OR REPLACE) still passes, as before. CREATE INDEX isn't safe to run twice. It is left alone because the site's index-guard convention isn't shown.",
    "Releases::rule2 and Releases::agentGate are past the truncation of the attached Releases.php. Whether they call ReleaseKind::prove for every kind is not confirmed here.",
    "No money moves, no migration, and no data change in this release, so no data_reason is needed."
  ],
  "verify": [
    "Releases.php: file shown only up to publish(); rule2(), agentGate() and the rest are not shown. Confirm they read ReleaseKind::prove (and 'kind:<id>') and that nothing else calls the private additive().",
    "BeforeAfter.php, DbUpdates.php, DbCopies.php, SchemaUpkeep.php, Switches.php: file not shown. Confirm none depends on additive()'s old leniency.",
    "app/migrations/: file not shown. Run each live migration that a past release declared kind 'additive' through ReleaseKind::prove('additive', ...) before and after this change. List any that flip, and confirm each flip is a pattern the decision does not call safe additive.",
    "app/ui/faq.md: file not shown. Merge faq/faq-entries.md into it by hand. Pick a heading no existing question uses, because headings are anchors (0057), and keep every existing heading so a later FAQ release still proves 'faq'.",
    "TeamRunner::fingerprint() before and after: it should be identical, since TeamRunner is not in this release and ReleaseKind is not in ruleData(). If it differs, stop: the tree's approval would clear.",
    "Move tests/releasekind-0179-tests.php to tools/release-kind-tests/ and run it with php from the repo root (or set TV_ROOT). It loads TeamRunner.php, which requires Database.php, ControlLog.php, GaugeTasks.php and TeamRoster.php at load. Those are not shown; confirm they have no side effects on load.",
    "php -l on files/app/lib/ReleaseKind.php under PHP 8.0. It uses str_starts_with/str_ends_with, match and a possessive quantifier in PCRE.",
    "handoffs/decided-not-live.md is stale: its Piece 3 row says 'not started', while the 2026-11-26 batch note and the live code show P3-3 to P3-6. That is for the session manager to update. It is not in this release."
  ],
  "regions": false
}
=== END FILE ===
=== FILE: files/app/lib/ReleaseKind.php ===
<?php
// app/lib/ReleaseKind.php — is a release really the low-risk kind it says it is? (decisions/0179 rule 2, its "Who classifies"
// line, and its note "Piece 3's decisions"; handoffs/piece3-check.md §4, P3-3.) A release's release.json names its kind;
// this proves it on the server, where both copies are: each file the release brings (app/storage/releases/<id>/new/)
// against the copy live has now. A kind that isn't proved is "needs approval" (0179: "a release that is not clearly
// low-risk counts as needing approval"). Nothing that touches a high-risk territory (decisions/0180 §4) is ever low-risk (TeamRunner::touches).
//   faq           only app/ui/faq.md; every question already there keeps its heading (the anchors, 0057)
//   wording       only words: in each changed PHP file the code is the same token for token, and each changed string or
//                 page text is a sentence whose tags, attributes, paths and SQL are the same
//   phone_layout  only CSS, and only inside @media (max-width: …) blocks for phones (767px or less, 0058 §11)
//   additive      0179's "safe additive": every changed file only gains lines; new migrations only CREATE TABLE IF NOT
//                 EXISTS or a guarded ADD COLUMN/INDEX (Jason, 2026-11-25: such a migration is safe additive, Tier 2;
//                 one that UPDATEs, DELETEs, DROPs, MODIFYs or RENAMEs is a high-risk territory, data, 0180 §4); nothing a live caller uses is
//                 changed or removed. Each ALTER TABLE clause is an ADD COLUMN, ADD INDEX or ADD KEY, and each CREATE TABLE
//                 says IF NOT EXISTS, read outside the migration's quoted strings and inside them (a guard's PREPAREd ALTER)
//   data_fix      recognised; stays in the daily batch for now (Jason, DB3: "a row limit comes later, Jason's choice")
declare(strict_types=1);

final class ReleaseKind
{
    public const KINDS = ['faq' => 'the FAQ', 'wording' => 'wording', 'phone_layout' => 'a phone-layout tweak', 'additive' => 'a safe additive change', 'data_fix' => 'a data fix'];

    /** A quoted SQL string ('…' or "…", with '' / "" or a backslash escaping its quote). */
    private const QUOTED = '/\'(?:[^\'\\\\]|\\\\.|\'\')*\'|"(?:[^"\\\\]|\\\\.|"")*"/s';

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
        if (($t3 = self::territory(array_keys($files), $files)) !== '') return $no('It touches a high-risk territory (' . $t3 . '): never low-risk.');
        $why = match ($kind) {
            'faq' => self::faq($files),
            'wording' => self::wording($files),
            'phone_layout' => self::phone($files),
            'additive' => self::additive($files),
        };
        return $why === '' ? ['kind' => $kind, 'ok' => true, 'why' => 'Proved: ' . self::KINDS[$kind] . ', and nothing in a high-risk territory.'] : $no($why);
    }

    /** The name before decisions/0180 R1 (tier3), kept as an alias of territory() so nothing that still calls it breaks. */
    public static function tier3(array $paths, array $files = []): string { return self::territory($paths, $files); }

    /** The high-risk territory a release's files touch (decisions/0180 §4: its pages, libraries, migrations and their tables), or ''. */
    public static function territory(array $paths, array $files = []): string
    {
        require_once __DIR__ . '/TeamRunner.php';
        $tg = [];
        foreach ($paths as $p) {
            if (str_starts_with($p, 'website/')) $tg[] = 'path:/' . substr($p, 8);
            if (preg_match('#^app/lib/([A-Za-z]+)\.php$#', $p, $m)) $tg[] = 'lib:' . $m[1];
            if (str_starts_with($p, 'app/migrations/')) {
                foreach (self::writes((string) ($files[$p][0] ?? '')) as $w) $tg[] = 'sql:' . $w;   // high-risk (data) by what it does
                if (preg_match_all('/\b(?:TABLE|INTO|UPDATE|FROM|JOIN)\s+(?:IF\s+(?:NOT\s+)?EXISTS\s+)?`?([a-z_0-9]+)`?/i', (string) ($files[$p][0] ?? ''), $mm)) foreach ($mm[1] as $t) $tg[] = 'table:' . strtolower($t);
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
                $sql = self::bare((string) $new);
                if ($w = self::writes((string) $new)) return $p . ': a migration that ' . $w[0] . 's isn\'t additive.';
                if (preg_match('/\bINSERT\b/i', $sql)) return $p . ': a migration that INSERTs isn\'t additive (it goes in the daily batch).';
                if (($why = self::onlyAddsSql($sql)) !== '') return $p . ': ' . $why;   // 0179's note "Piece 3's last two briefs"
                continue;
            }
            if ($old === null) continue;   // a new file adds
            if (!self::onlyAdds((string) $old, (string) $new)) return $p . ': a line live has was changed or removed, not only added to.';
        }
        return '';
    }

    /**
     * '' when a migration's statements only add, as decisions/0179's note "Piece 3's last two briefs" puts it: "CREATE TABLE IF
     * NOT EXISTS, or a guarded ADD COLUMN or INDEX". Each ALTER TABLE clause must be ADD COLUMN, ADD INDEX or ADD KEY; a CREATE
     * TABLE must say IF NOT EXISTS (safe to run twice); CREATE OR REPLACE replaces. Read outside the quoted strings, then inside
     * those that hold a CREATE or an ALTER (a guarded ADD COLUMN keeps its ALTER in a string for PREPARE). Else why not.
     */
    private static function onlyAddsSql(string $sql, int $depth = 0): string
    {
        $inner = [];
        $s = (string) preg_replace_callback(self::QUOTED, function (array $m) use (&$inner): string { $inner[] = $m[0]; return "''"; }, $sql);
        if (preg_match('/\bCREATE\s+OR\s+REPLACE\b/i', $s)) return 'a migration that replaces (CREATE OR REPLACE) isn\'t additive.';
        if (preg_match('/\bCREATE\s+TABLE\s++(?!IF\s+NOT\s+EXISTS\b)/i', $s)) return 'a CREATE TABLE without IF NOT EXISTS isn\'t safe to run twice, so it isn\'t additive (0179: CREATE TABLE IF NOT EXISTS).';
        if (preg_match_all('/\bALTER\s+(?:ONLINE\s+|IGNORE\s+)*TABLE\s+(?:IF\s+EXISTS\s+)?[`\w.$]+\s*([^;]*)/i', $s, $mm)) {
            foreach ($mm[1] as $tail) {
                $t = (string) $tail;
                do { $t = (string) preg_replace('/\([^()]*\)/', ' ', $t, -1, $n); } while ($n > 0);   // column types and key lists, whose commas aren't clauses
                foreach (explode(',', $t) as $c) {
                    $c = trim($c);
                    if ($c !== '' && !preg_match('/^ADD\s+(COLUMN|INDEX|KEY)\b/i', $c)) return 'an ALTER that doesn\'t only add (' . mb_substr(preg_replace('/\s+/', ' ', $c), 0, 60) . ').';
                }
            }
        }
        if ($depth < 2) foreach ($inner as $q) {
            if (!preg_match('/\b(CREATE|ALTER)\b/i', $q)) continue;
            $body = str_replace([$q[0] . $q[0], '\\' . $q[0]], $q[0], substr($q, 1, -1));
            if (($why = self::onlyAddsSql($body, $depth + 1)) !== '') return $why;
        }
        return '';
    }

    /** A migration's SQL with its comments, and the column and key clauses that only name a verb, taken out. */
    private static function bare(string $sql): string
    {
        $sql = preg_replace(['#/\*.*?\*/#s', '/(--|#)[^\n]*/'], ' ', $sql);
        return (string) preg_replace('/\bON\s+(UPDATE|DELETE)\b|\bDUPLICATE\s+KEY\s+UPDATE\b/i', ' ', $sql);   // ON UPDATE CURRENT_TIMESTAMP, ON DELETE CASCADE
    }

    /** The data-changing verbs a migration uses (TeamRunner::TERRITORIES' data "sql"), in its order; [] for CREATE TABLE IF NOT EXISTS or guarded ADDs alone. */
    public static function writes(string $sql): array
    {
        require_once __DIR__ . '/TeamRunner.php';
        $v = TeamRunner::TERRITORIES['data']['sql'];
        return preg_match_all('/\b(' . implode('|', $v) . ')\b/i', self::bare($sql), $m) ? array_values(array_unique(array_map('strtoupper', $m[1]))) : [];
    }

    /** Does a release's migration write rows (an INSERT)? A data fix: it stays in the daily batch (DB3). */
    public static function insertsRows(array $files): bool
    {
        foreach ($files as $p => [$new]) if (str_starts_with($p, 'app/migrations/') && preg_match('/\b(INSERT|REPLACE)\s+(IGNORE\s+)?INTO\b/i', self::bare((string) $new))) return true;
        return false;
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
=== END FILE ===
=== FILE: tests/releasekind-0179-tests.php ===
<?php
// tests/releasekind-0179-tests.php (to live at tools/release-kind-tests/) — decisions/0179's "safe additive" and the low-risk
// kinds, as ReleaseKind proves them. Plain PHP, no database, no network, no money. Run from the repo root: php tools/release-kind-tests/releasekind-0179-tests.php
// (or TV_ROOT=/path/to/repo php …). Prints PASS/FAIL per case; exits 1 on any FAIL.
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
$lib = $root . '/app/lib/ReleaseKind.php';
if (!is_file($lib)) { echo "FAIL setup: no app/lib/ReleaseKind.php under $root (set TV_ROOT to the repo root)\n"; exit(1); }
try { require_once $lib; require_once $root . '/app/lib/TeamRunner.php'; }
catch (Throwable $x) { echo 'FAIL setup: ' . $x->getMessage() . "\n"; exit(1); }

$pass = 0; $fail = 0;
$t = function (string $name, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name" . ($detail !== '' ? " ($detail)" : '') . "\n"; }
};
$add = fn (string $sql) => ReleaseKind::prove('additive', ['app/migrations/2026_12_01_widgets.sql' => [$sql, null]]);

// --- add-only migrations that 0179 calls safe additive -------------------------------------------------------------
$r = $add("CREATE TABLE IF NOT EXISTS widgets (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, label VARCHAR(80) NOT NULL DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$t('CREATE TABLE IF NOT EXISTS is additive', $r['ok'] === true, $r['why']);

$r = $add("CREATE TABLE\n    IF NOT EXISTS widgets (id INT NOT NULL PRIMARY KEY);");
$t('CREATE TABLE IF NOT EXISTS across lines is additive', $r['ok'] === true, $r['why']);

$r = $add("ALTER TABLE widgets ADD COLUMN size INT NOT NULL DEFAULT 0, ADD KEY size (size);");
$t('ALTER TABLE with only ADD COLUMN / ADD KEY is additive', $r['ok'] === true, $r['why']);

$r = $add("ALTER TABLE widgets ADD COLUMN note VARCHAR(10) NOT NULL DEFAULT 'a,b' COMMENT 'x, y', ADD INDEX note_i (note, size);");
$t('commas inside quotes and parentheses are not clauses', $r['ok'] === true, $r['why']);

$guard = <<<'SQL'
SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'widgets' AND COLUMN_NAME = 'colour') = 0,
  'ALTER TABLE widgets ADD COLUMN colour VARCHAR(20) NOT NULL DEFAULT '''', ADD KEY colour (colour)', 'SELECT 1');
PREPARE s FROM @s; EXECUTE s; DEALLOCATE PREPARE s;
SQL;
$r = $add($guard);
$t('a guarded ADD COLUMN (ALTER inside a PREPAREd string) is additive', $r['ok'] === true, $r['why']);

// --- what 0179 does not call safe additive --------------------------------------------------------------------------
$r = $add("CREATE TABLE widgets (id INT NOT NULL PRIMARY KEY);");
$t('CREATE TABLE without IF NOT EXISTS is not additive', $r['ok'] === false && str_contains($r['why'], 'IF NOT EXISTS'), $r['why']);

$r = $add("CREATE OR REPLACE VIEW widget_view AS SELECT id FROM widgets;");
$t('CREATE OR REPLACE is not additive', $r['ok'] === false, $r['why']);

$r = $add("ALTER TABLE widgets ADD COLUMN size INT NOT NULL DEFAULT 0, ALTER COLUMN label SET DEFAULT 'x';");
$t('an ALTER mixing ADD with ALTER COLUMN is not additive (passed before this release)', $r['ok'] === false && str_contains($r['why'], 'doesn\'t only add'), $r['why']);

$r = $add("ALTER TABLE widgets ADD COLUMN size INT NOT NULL DEFAULT 0, CONVERT TO CHARACTER SET utf8mb4;");
$t('an ALTER mixing ADD with CONVERT is not additive', $r['ok'] === false, $r['why']);

$guardBad = <<<'SQL'
SET @s = IF(1 = 1, 'ALTER TABLE widgets ADD COLUMN size INT, ENGINE=MyISAM', 'SELECT 1');
PREPARE s FROM @s; EXECUTE s; DEALLOCATE PREPARE s;
SQL;
$r = $add($guardBad);
$t('a non-ADD clause inside a guard\'s string is caught', $r['ok'] === false, $r['why']);

$r = $add("SET @s = 'ALTER TABLE widgets MODIFY label VARCHAR(200)'; PREPARE s FROM @s; EXECUTE s;");
$t('MODIFY inside a string is still a write (data territory)', $r['ok'] === false, $r['why']);

$r = $add("UPDATE widgets SET size = 1;");
$t('UPDATE is never additive', $r['ok'] === false, $r['why']);

$r = $add("INSERT INTO widgets (id) VALUES (1);");
$t('INSERT is not additive (a data fix: the daily batch)', $r['ok'] === false, $r['why']);

$r = $add("ALTER TABLE orders ADD COLUMN note VARCHAR(10) NOT NULL DEFAULT '';");
$t('an add-only migration on a money table is a territory, never low-risk', $r['ok'] === false && str_contains($r['why'], 'territory'), $r['why']);

// --- the rest of rule 2's proof, unchanged ----------------------------------------------------------------------------
$r = ReleaseKind::prove('additive', ['app/lib/WidgetThing.php' => ["<?php\n// new\n", null]]);
$t('a new file outside a territory is additive', $r['ok'] === true, $r['why']);

$t('onlyAdds: lines only added', ReleaseKind::onlyAdds("a\nb", "a\nx\nb\ny") === true);
$t('onlyAdds: a line removed', ReleaseKind::onlyAdds("a\nb", "a\ny") === false);

$r = ReleaseKind::prove('data_fix', ['app/migrations/x.sql' => ['UPDATE widgets SET size = 1;', null]]);
$t('data_fix stays in the daily batch (DB3)', $r['ok'] === false, $r['why']);

$r = ReleaseKind::prove('', ['app/ui/faq.md' => ['x', 'x']]);
$t('no kind named: needs approval', $r['ok'] === false, $r['why']);

$r = ReleaseKind::prove('tests', ['app/ui/faq.md' => ['x', 'x']]);
$t('"tests" is not a provable kind yet (the low-risk list grows only by Jason\'s word)', $r['ok'] === false, $r['why']);

$old = "# FAQ\n\n### How do I sign in?\nOld words.\n";
$r = ReleaseKind::prove('faq', ['app/ui/faq.md' => ["# FAQ\n\n### How do I sign in?\nNew words.\n", $old]]);
$t('faq: words changed, heading kept', $r['ok'] === true, $r['why']);
$r = ReleaseKind::prove('faq', ['app/ui/faq.md' => ["# FAQ\n\n### How to sign in\nNew words.\n", $old]]);
$t('faq: a heading changed is refused (its anchor)', $r['ok'] === false, $r['why']);

$t('outsidePhone drops phone-only media blocks', ReleaseKind::outsidePhone("a{b:c}\n@media (max-width: 767px){ .x{y:z} }") === 'a{b:c}');

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
=== END FILE ===
=== FILE: faq/faq-entries.md ===
<!-- For app/ui/faq.md (file not shown: merge by hand under a heading no existing question uses; keep every existing heading). -->

### How do changes to Traversence go live?

Every change is built and tested on a separate private copy of the site first, called staging. Only after its checks pass there can it reach the site you use.

For now, a person reviews a daily summary of everything waiting to go live and approves it as one batch. Nothing that needs approval goes live before that. If something on the site is broken, the fix can go out straight away with a written reason, and it is checked on the live site right after.

Decisions about money, billing and refunds, deleting or reshaping major data, changing public addresses, tribal nations' land and sensitive places, privacy, or our mission are always made by our founder.

**Coming:** small, low-risk changes (wording, this FAQ, tests, phone-layout tweaks and safe additions that remove nothing) will go live on their own. Each will need to pass its checks on staging and an automatic before-and-after look at every page it touches. Each will also appear in the daily summary with a way to undo it.
=== END FILE ===