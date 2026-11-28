<?php
// tools/control-tests/no-undo-ack.php — No Undo on an alarm's acknowledgement (CLAUDE.md #1314). DRAFT: not run.
// Plain script: prints PASS/FAIL per check, exits 1 on any FAIL.
// Root: TV_ROOT, else two folders up (when placed in tools/control-tests/).
// The database part runs only on staging, or with TV_TESTS_DB=1 against a test database. It writes one control_alarms
// row (proc 'test:no-undo') and deletes it. ack() also writes one control_log line with proc 'test:no-undo', which is
// left in place (ControlLog's schema isn't shown).
// No money moves: nothing here touches QuickBooks or payments.
declare(strict_types=1);

$root = rtrim((string) (getenv('TV_ROOT') ?: dirname(__DIR__, 2)), '/');
require_once $root . '/app/lib/Database.php';
require_once $root . '/app/lib/ControlAlarms.php';

$fails = 0;
$check = function (string $name, bool $ok, string $why = '') use (&$fails): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . ($ok || $why === '' ? '' : ' (' . $why . ')') . "\n";
    if (!$ok) $fails++;
};

// 1. Source: unack() writes nothing and always says false.
$rm = new ReflectionMethod('ControlAlarms', 'unack');
$lines = file((string) $rm->getFileName());
$src = implode('', array_slice($lines, $rm->getStartLine() - 1, $rm->getEndLine() - $rm->getStartLine() + 1));
$check('unack() has no UPDATE', stripos($src, 'UPDATE') === false);
$check('unack() has no prepare/exec', stripos($src, 'prepare') === false && stripos($src, 'exec') === false);
$check('unack() returns false', (bool) preg_match('/return\s+false;/', $src));

// 2. Release blocker: anything that still offers an Undo through unack() (Pulse's alarm items, the board's actions,
//    the email's link page). Expected to FAIL until those files are changed; the lane removes each one listed.
$left = [];
foreach (['app', 'website'] as $dir) {
    if (!is_dir($root . '/' . $dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!preg_match('/\.(php|js)$/', $f->getFilename())) continue;
        $p = $f->getPathname();
        if (str_ends_with($p, 'app/lib/ControlAlarms.php')) continue;
        if (preg_match('/\bunack\b/i', (string) file_get_contents($p))) $left[] = substr($p, strlen($root) + 1);
    }
}
$check('no caller of unack() left (release blocker)', !$left, implode(', ', $left));

// 3. Database: ack holds, unack changes nothing, a second ack doesn't overwrite the first.
$staging = false;
try { require_once $root . '/app/lib/Staging.php'; $staging = Staging::here(); } catch (Throwable $x) {}
if (!$staging && getenv('TV_TESTS_DB') !== '1') {
    echo "SKIP: database checks (not staging; set TV_TESTS_DB=1 on a test database)\n";
} else {
    $pdo = Database::connection();
    ControlAlarms::ensure($pdo);
    $key = 'test:no-undo:' . bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO control_alarms (akey, proc, level, kind, title, first_at, last_at) VALUES (?, 'test:no-undo', 'warning', 'check', 'No-undo test alarm', UTC_TIMESTAMP(), UTC_TIMESTAMP())")->execute([$key]);
    $id = (int) $pdo->lastInsertId();
    try {
        $check('unack() on an unseen alarm returns false', ControlAlarms::unack($pdo, $id) === false);
        $check('ack() the first time returns true', ControlAlarms::ack($pdo, $id, 0, 'Test runner') === true);
        $a = ControlAlarms::byId($pdo, $id);
        $at = (string) ($a['ack_at'] ?? '');
        $check('ack_at is set', $at !== '');
        $check('unack() on a seen alarm returns false', ControlAlarms::unack($pdo, $id) === false);
        $b = ControlAlarms::byId($pdo, $id);
        $check('ack_at unchanged after unack()', (string) ($b['ack_at'] ?? '') === $at);
        $check('ack_name unchanged after unack()', (string) ($b['ack_name'] ?? '') === 'Test runner');
        $check('ack() again returns false (already seen)', ControlAlarms::ack($pdo, $id, 0, 'Someone else') === false);
        $c = ControlAlarms::byId($pdo, $id);
        $check('first acknowledger kept', (string) ($c['ack_name'] ?? '') === 'Test runner');
        $check('still open after ack (stays until its reading is good)', ($c['cleared_at'] ?? null) === null);
    } finally {
        // data_reason: removes only the one row this test inserted (its own id, proc 'test:no-undo'); nothing else is touched
        $pdo->prepare("DELETE FROM control_alarms WHERE id = ? AND proc = 'test:no-undo'")->execute([$id]);
    }
}

echo $fails ? "FAILED: $fails\n" : "ALL PASS\n";
exit($fails ? 1 : 0);
