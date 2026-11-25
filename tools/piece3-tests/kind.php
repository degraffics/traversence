<?php
// Piece 3, P3-3: ReleaseKind::prove, each low-risk kind's true and false cases (decisions/0179 rule 2; piece3-check.md §4).
// No database. Run: php kind.php <root>/app
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/'); require_once $app . '/lib/ReleaseKind.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$P = fn ($k, $f) => ReleaseKind::prove($k, $f);
$faq0 = "### Where is the map?\nOn the page.\n\n### How do I sign in?\nWith email.\n";
$t('faq: an answer reworded and a question added: proved', $P('faq', ['app/ui/faq.md' => [$faq0 . "\n### What's new?\nThis.\n", $faq0]])['ok']);
$t('… an answer changed in place: proved', $P('faq', ['app/ui/faq.md' => [str_replace('With email.', 'With your email or a code.', $faq0), $faq0]])['ok']);
$t('faq: a question\'s heading reworded (its link changes): not proved', !$P('faq', ['app/ui/faq.md' => [str_replace('How do I sign in?', 'How can I sign in?', $faq0), $faq0]])['ok']);
$t('faq: another file with it: not proved', !$P('faq', ['app/ui/faq.md' => [$faq0, $faq0], 'website/x.php' => ['<?php', '<?php']])['ok']);
$pg = "<?php\n\$x = 1;\necho '<p class=\"note\">Your trip is saved.</p>';\n?>\n<h2>Plan a trip</h2>\n";
$t('wording: a sentence reworded in a string and in page text: proved', $P('wording', ['website/trip.php' => [str_replace(['Your trip is saved.', 'Plan a trip'], ['Saved: your trip is in Your trips.', 'Plan your trip'], $pg), $pg]])['ok'], $P('wording', ['website/trip.php' => [str_replace(['Your trip is saved.', 'Plan a trip'], ['Saved: your trip is in Your trips.', 'Plan your trip'], $pg), $pg]]));
$t('wording: a number changed in the code: not proved', !$P('wording', ['website/trip.php' => [str_replace('$x = 1', '$x = 2', $pg), $pg]])['ok']);
$t('wording: a class attribute changed: not proved', !$P('wording', ['website/trip.php' => [str_replace('class="note"', 'class="warn"', $pg), $pg]])['ok']);
$t('wording: an href sneaked in: not proved', !$P('wording', ['website/trip.php' => [str_replace('Your trip is saved.', 'Your trip is <a href=\"/x\">saved</a>.', $pg), $pg]])['ok']);
$sq = "<?php\n\$q = 'SELECT id FROM trips WHERE uid = ?';\n";
$t('wording: a string that is SQL changed: not proved', !$P('wording', ['website/trip.php' => [str_replace('uid = ?', 'uid > ?', $sq), $sq]])['ok']);
$t('wording: a key or path string changed: not proved', !$P('wording', ['website/trip.php' => ["<?php\n\$k = 'trip_saved';\n", "<?php\n\$k = 'trip_kept';\n"]])['ok']);
$css = "a { color: red; }\n@media (max-width: 767px) { .card { margin: 3px; } }\n";
$t('phone_layout: a rule inside the phone\'s media query: proved', $P('phone_layout', ['website/css/app.css' => [str_replace('margin: 3px', 'margin: 4px', $css), $css]])['ok']);
$t('phone_layout: a rule outside it: not proved', !$P('phone_layout', ['website/css/app.css' => [str_replace('color: red', 'color: blue', $css), $css]])['ok']);
$t('phone_layout: a tablet query (max-width 1024px): not proved', !$P('phone_layout', ['website/css/app.css' => [$css . "@media (max-width: 1024px) { a { color: blue; } }\n", $css]])['ok']);
$lib = "<?php\nfinal class Trips {\n    public static function all() {}\n}\n";
$t('additive: lines only added and a new file: proved', $P('additive', ['app/lib/Trips.php' => [str_replace("}\n}", "}\n    public static function count() {}\n}", $lib), $lib], 'app/lib/TripsNew.php' => ['<?php', null]])['ok']);
$t('… with any migration, even CREATE TABLE: Tier 3 (data) as TIER3_AREAS stands (P2-4: "data: migrations"), so not low-risk', str_contains($P('additive', ['app/migrations/2026-12-01_trips_x.sql' => ['CREATE TABLE IF NOT EXISTS trips_x (id INT);', null]])['why'], 'Tier 3 area (data)'));
$t('additive: one line changed: not proved', !$P('additive', ['app/lib/Trips.php' => [str_replace('all()', 'all(int $n = 0)', $lib), $lib]])['ok']);
$t('additive: a migration that UPDATEs: not proved', !$P('additive', ['app/migrations/2026-12-01_x.sql' => ["UPDATE trips SET x = 1;", null]])['ok']);
$t('additive: an ALTER that modifies: not proved', !$P('additive', ['app/migrations/2026-12-01_x.sql' => ["ALTER TABLE trips MODIFY x INT;", null]])['ok']);
$t('a Tier 3 area: wording on the checkout\'s page or in Orders: never low-risk', !$P('wording', ['website/order.php' => ["<?php echo 'Pay now please';", "<?php echo 'Pay now';"]])['ok'] && str_contains($P('additive', ['app/lib/Orders.php' => ["<?php\n//x\n", "<?php\n"]])['why'], 'Tier 3'));
$t('… a migration on the users table: never low-risk', !$P('additive', ['app/migrations/2026-12-01_u.sql' => ['CREATE TABLE IF NOT EXISTS users_x (id INT); ALTER TABLE users ADD COLUMN x INT;', null]])['ok']);
$t('data_fix: recognised, stays in the batch (DB3)', str_contains($P('data_fix', ['app/migrations/2026-12-01_f.sql' => ['UPDATE trips SET x = 1;', null]])['why'], 'DB3'));
$t('no kind, or one that isn\'t a kind: needs approval', !$P('', ['app/ui/faq.md' => ['a', 'b']])['ok'] && !$P('urgent', ['app/ui/faq.md' => ['a', 'b']])['ok']);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
