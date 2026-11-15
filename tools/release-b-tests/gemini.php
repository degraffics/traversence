<?php
declare(strict_types=1);
$app = $argv[1] ?? '/home/user/relb/app';
require $app . '/lib/Database.php'; require $app . '/lib/AiClient.php'; require $app . '/lib/Gemini.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got) : '') . "\n"; } };
$pdo->exec("CREATE TABLE IF NOT EXISTS zip_coordinates (zip CHAR(5), city VARCHAR(80), latitude DECIMAL(9,6), longitude DECIMAL(9,6))");
$pdo->exec("CREATE TABLE IF NOT EXISTS landmarks (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200), status VARCHAR(20), nation VARCHAR(80), fclass VARCHAR(40))");
$pdo->exec("DELETE FROM zip_coordinates"); $pdo->exec("INSERT INTO zip_coordinates VALUES ('85920', 'Saint Johns', 34.5, -109.36)");
$pdo->exec("DELETE FROM landmarks"); $pdo->exec("INSERT INTO landmarks (name, status, nation, fclass) VALUES ('Lyman Lake Pueblo Site', 'story_only', '', ''), ('Big Spring', 'shown', 'Navajo Nation', 'Spring')");
foreach (['topic_assist', 'item_write', 'card', 'heritage_extract', 'guide_draft', 'find_settle', 'content_names'] as $f) $t("refused for $f", Gemini::refused($f) !== null);
$t('allowed for page_read (public)', Gemini::refused('page_read') === null);
$t('an undeclared feature is refused', Gemini::refused('something_new') !== null);
$t('providerFor never picks Gemini for a non-public kind', AiClient::providerFor('topic_assist') !== 'gemini');
[$x, $g] = Gemini::safe("Ace Hardware, 100 W Main St, St Johns, 928-337-4444. Email us at info@ace.example. John Smith, 82, of St Johns died Tuesday. Visit the petroglyph trail behind the store. Tours of Lyman Lake Pueblo Site start here. The Big Spring is open. SSN 123-45-6789 card 4111 1111 1111 1111. A women's shelter nearby takes donations.", $pdo);
$t('the business phone and street stay', str_contains($x, '928-337-4444') && str_contains($x, '100 W Main St'), $x);
$t('emails removed', !str_contains($x, '@'), $x);
$t('a private person\'s death notice removed', !str_contains($x, 'John Smith'), $x);
$t('a protected place (petroglyph) removed', !str_contains(strtolower($x), 'petroglyph'), $x);
$t('a story-only landmark\'s name removed', !str_contains($x, 'Lyman Lake Pueblo'), $x);
$t('a nation-sensitive name removed', !str_contains($x, 'Big Spring'), $x);
$t('ID and card numbers removed', !str_contains($x, '6789') && !str_contains($x, '1111 1111'), $x);
$t('a confidential place removed', !str_contains(strtolower($x), 'shelter'), $x);
$ok = true; try { Gemini::prompt($pdo, 'extract_places', ['town' => 'Saint Johns', 'state' => 'AZ', 'kinds' => ['food', 'fuel'], 'page' => 'x']); } catch (Throwable $e) { $ok = false; }
$t('a prompt from our vocabulary is built', $ok);
foreach ([['town' => 'pizza near me cheap', 'state' => 'AZ', 'kinds' => ['food'], 'page' => 'x'], ['town' => 'Saint Johns', 'state' => 'AZ', 'kinds' => ['my favourite bar'], 'page' => 'x']] as $i => $v) {
    $no = false; try { Gemini::prompt($pdo, 'extract_places', $v); } catch (InvalidArgumentException) { $no = true; }
    $t('a person\'s words refuse the prompt (' . $i . ')', $no);
}
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
