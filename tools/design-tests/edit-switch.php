<?php
// Release 3a: the Edit page switch (Blocks::zone, Blocks::site, Blocks::editingOn). CLI counts as an editor (Blocks::can).
declare(strict_types=1);
$root = $argv[1];
require $root . '/app/lib/Database.php';
foreach (['Response', 'Auth'] as $c) if (is_file("$root/app/lib/$c.php")) require_once "$root/app/lib/$c.php";
require $root . '/app/lib/Blocks.php';
$pdo = Database::connection();
$pdo->exec('DROP TABLE IF EXISTS site_block_zones, site_block_versions, site_block_schedule');
$ok = 0; $bad = 0;
function t(string $name, bool $c) { global $ok, $bad; echo ($c ? 'ok   ' : 'FAIL ') . $name . "\n"; $c ? $ok++ : $bad++; }
$z = 'home.after-doors';
$_COOKIE = [];
$off = Blocks::zone($pdo, $z, '<p>fallback</p>', 'Home');
t('off: no editing outline', strpos($off, 'class="tvb-zone tvb-edit') === false);
t('off: no Edit button', strpos($off, 'data-tvb-edit') === false);
t('off: the published content (fallback) shows', strpos($off, 'fallback') !== false);
t('off: editingOn() is false', Blocks::editingOn() === false);
$_COOKIE['tv_edit'] = '1';
$on = Blocks::zone($pdo, $z, '<p>fallback</p>', 'Home');
t('on: outlined with Edit', strpos($on, 'class="tvb-zone tvb-edit') !== false && strpos($on, 'data-tvb-edit') !== false);
t('on: editingOn() is true', Blocks::editingOn() === true);
// a draft shows only with the switch on
$pdo->prepare('INSERT INTO site_block_zones (zone, draft, draft_name, updated_at) VALUES (?, ?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE draft = VALUES(draft)')
    ->execute([$z, json_encode(['sections' => [['cols' => [[['t' => 'text', 'md' => 'DRAFTTEXT']]]]]]), 'Test']);
$_COOKIE = [];
t('off: the draft is not shown', strpos(Blocks::zone($pdo, $z, '<p>fallback</p>', 'Home'), 'DRAFTTEXT') === false);
$_COOKIE['tv_edit'] = '1';
t('on: the draft is shown, marked Draft', ($h = Blocks::zone($pdo, $z, '<p>fallback</p>', 'Home')) && strpos($h, 'DRAFTTEXT') !== false && strpos($h, 'Draft') !== false);
$_COOKIE['tv_edit'] = '0';
t('a cookie other than 1 is off', Blocks::editingOn() === false);
echo "$ok passed, $bad failed\n";
exit($bad ? 1 : 0);
