<?php
// tools/pause-tests/pause.php — the pause on Jason's call, 2026-11-28 ("Pause it. Yes"; handoffs/pause-1a): auto-publish off
// while AutoImport::PAUSED holds words, whatever .env says, and back on when it's emptied (the way back); the reason shown
// where Auto-import is; every automatic publish passes through enabled() (tryImport, advance); the ingest endpoint
// refuses before it reads a record. Run: php tools/pause-tests/pause.php <build root> <mirror's sync-app/lib>
declare(strict_types=1);

[$root, $live] = [rtrim($argv[1] ?? '', '/'), rtrim($argv[2] ?? '', '/')];
if (!is_file("$root/app/lib/crawler/AutoImport.php") || !is_dir($live)) { fwrite(STDERR, "usage: php pause.php <build root> <sync-app/lib>\n"); exit(2); }
$fails = 0;
function check(string $n, bool $ok): void { global $fails; echo ($ok ? '  ok  ' : 'FAIL  ') . $n . "\n"; if (!$ok) $fails++; }

// a copy of the live lib with the build's AutoImport over it, loaded in a child php (one with the pause, one emptied)
$tmp = sys_get_temp_dir() . '/pause-' . getmypid();
@mkdir($tmp, 0777, true); exec('cp -r ' . escapeshellarg($live) . ' ' . escapeshellarg("$tmp/lib"));
$src = file_get_contents("$root/app/lib/crawler/AutoImport.php");
$ask = function (string $code, string $auto) use ($tmp): string {
    $f = "$tmp/ask.php";
    file_put_contents($f, '<?php putenv("AUTO_IMPORT=' . $auto . '"); require "' . $tmp . '/lib/Database.php"; require "' . $tmp . '/lib/crawler/AutoImport.php"; ' . $code);
    return trim((string) shell_exec('php ' . escapeshellarg($f) . ' 2>&1'));
};
file_put_contents("$tmp/lib/crawler/AutoImport.php", $src);
check('paused: auto-publish is off even with AUTO_IMPORT=on', $ask('echo AutoImport::enabled() ? "on" : "off";', 'on') === 'off');
check('…its reason quotes Jason and names the review', str_contains($ask('echo AutoImport::pausedWhy();', 'on'), 'Paused on Jason\'s call 2026-11-28: "Pause it. Yes"') && str_contains($ask('echo AutoImport::pausedWhy();', 'on'), 'data-capture-review-2026-12-02.md'));
$empty = preg_replace("/public const PAUSED = '(?:[^'\\\\]|\\\\.)*';/", "public const PAUSED = '';", $src, 1, $n);
check('the way back: the constant can be emptied in one line', $n === 1);
file_put_contents("$tmp/lib/crawler/AutoImport.php", $empty);
check('…emptied, auto-publish follows .env again: on', $ask('echo AutoImport::enabled() ? "on" : "off";', 'on') === 'on');
check('…and off when .env says off, with no pause reason', $ask('echo (AutoImport::enabled() ? "on" : "off") . "|" . var_export(AutoImport::pausedWhy(), true);', 'off') === 'off|NULL');
exec('rm -rf ' . escapeshellarg($tmp));

// every automatic publish asks enabled() first
preg_match('/public static function tryImport\(.*?\n    \}\n/s', $src, $t);
check('tryImport: enabled() before evaluate and approve', isset($t[0]) && strpos($t[0], 'self::enabled()') < strpos($t[0], 'IntakeStager::approve('));
preg_match('/public static function advance\(.*?\n    \}\n/s', $src, $a);
check('advance (listings_tick): enabled() before tryImport', isset($a[0]) && strpos($a[0], 'self::enabled()') !== false && strpos($a[0], 'self::enabled()') < strpos($a[0], 'self::tryImport('));
check('obvious merges (filling a listed one\'s gaps) still run: they come before the gate, as before', isset($t[0]) && strpos($t[0], 'self::obvious(') < strpos($t[0], 'self::enabled()'));
$ing = file_get_contents("$root/website/api/ingest.php");
check('ingest.php refuses while paused, before reading a record or writing', strpos($ing, 'AutoImport::pausedWhy()') !== false && strpos($ing, 'AutoImport::pausedWhy()') < strpos($ing, 'Response::jsonBody()') && strpos($ing, 'AutoImport::pausedWhy()') < strpos($ing, 'upsertEntity('));
$dash = file_get_contents("$root/app/ui/admin-dashboard.php");
check('the dashboard reads AutoImport::enabled() (not .env alone) and shows the pause\'s reason', str_contains($dash, '$auto = AutoImport::enabled();') && str_contains($dash, 'AutoImport::pausedWhy()') && !str_contains($dash, "getenv('AUTO_IMPORT')"));
check('Auto-imports page shows the pause\'s reason, not ".env"', str_contains(file_get_contents("$root/website/admin/auto-imports.php"), 'AutoImport::pausedWhy()'));

echo $fails ? "$fails failed\n" : "all passed\n";
exit($fails ? 1 : 0);
