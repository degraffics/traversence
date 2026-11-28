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
