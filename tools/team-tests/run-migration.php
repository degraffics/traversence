<?php
// For the rendered tests: runs one migration file as the site does (DbUpdates::statements), on the bench's lv_main.
// Run: php run-migration.php <root>/app <file.sql>
require $argv[1] . '/lib/Database.php'; require $argv[1] . '/lib/DbUpdates.php';
$p = Database::connection(); $p->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");   /* as live runs: not strict */ foreach (DbUpdates::statements((string) file_get_contents($argv[2])) as $q) $p->exec($q);
