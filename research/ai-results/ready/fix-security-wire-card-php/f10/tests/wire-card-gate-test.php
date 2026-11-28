<?php
// Place at tools/wire-card-tests/wire-card-gate-test.php. Run: php tools/wire-card-tests/wire-card-gate-test.php
// Override the repo root with WIRE_TEST_ROOT=/path/to/repo. Touches no DB and moves no money.
declare(strict_types=1);

$root = rtrim((string) (getenv('WIRE_TEST_ROOT') ?: __DIR__ . '/../..'), '/');
require_once $root . '/app/lib/WireCardGate.php';

$fail = 0;
function t(string $name, bool $ok): void
{
    global $fail;
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $name . PHP_EOL;
    if (!$ok) { $fail++; }
}

$now = 1_800_000_000;
$min = 15;

$s = [];
t('unmarked session is refused', !WireCardGate::allowedIn($s, 'tokA', $now, $min));

WireCardGate::markIn($s, 'tokA', $now, $min);
t('marked token allowed in same session', WireCardGate::allowedIn($s, 'tokA', $now + 60, $min));
t('other token not allowed', !WireCardGate::allowedIn($s, 'tokB', $now + 60, $min));
t('empty token never allowed', !WireCardGate::allowedIn($s, '', $now, $min));
t('different (empty) session refused', !WireCardGate::allowedIn([], 'tokA', $now + 60, $min));
t('expires at window end', !WireCardGate::allowedIn($s, 'tokA', $now + $min * 60, $min));
t('still valid just before end', WireCardGate::allowedIn($s, 'tokA', $now + $min * 60 - 1, $min));
t('future-dated marker refused', !WireCardGate::allowedIn($s, 'tokA', $now - 1, $min));
t('raw token not stored', strpos(serialize($s), 'tokA') === false);

$s2 = [WireCardGate::KEY => 'garbage'];
t('garbage session value refused', !WireCardGate::allowedIn($s2, 'tokA', $now, $min));
WireCardGate::markIn($s2, 'tokA', $now, $min);
t('garbage replaced on mark', WireCardGate::allowedIn($s2, 'tokA', $now, $min));

$s3 = [];
for ($i = 0; $i < WireCardGate::MAX + 5; $i++) { WireCardGate::markIn($s3, 'tok' . $i, $now, $min); }
t('marker map capped', count($s3[WireCardGate::KEY]) === WireCardGate::MAX);
t('newest kept after cap', WireCardGate::allowedIn($s3, 'tok' . (WireCardGate::MAX + 4), $now, $min));
t('oldest dropped after cap', !WireCardGate::allowedIn($s3, 'tok0', $now, $min));

$s4 = [];
WireCardGate::markIn($s4, 'old', $now, $min);
WireCardGate::markIn($s4, 'new', $now + $min * 60 + 5, $min);
t('expired markers pruned on mark', count($s4[WireCardGate::KEY]) === 1);

// Static check that the endpoint consults the gate before building the picture.
$src = (string) @file_get_contents($root . '/website/api/wire-card.php');
t('endpoint requires WireCardGate', strpos($src, "require_once __DIR__ . '/../../app/lib/WireCardGate.php';") !== false);
$g = strpos($src, 'WireCardGate::allowed($tok)');
$w = strpos($src, 'ClientOrders::wireCard($pdo, $o)');
t('gate checked before wireCard()', $g !== false && $w !== false && $g < $w);

// Static check that the code-entry step sets the marker (file not shown; the lane sets WIRE_CODE_FILE).
$codeFile = (string) (getenv('WIRE_CODE_FILE') ?: '');
if ($codeFile === '') {
    echo 'FAIL code-entry file not given (set WIRE_CODE_FILE); WireCardGate::mark() wiring unchecked' . PHP_EOL;
    $fail++;
} else {
    t('code-entry step calls WireCardGate::mark', strpos((string) @file_get_contents($codeFile), 'WireCardGate::mark(') !== false);
}

echo ($fail === 0 ? 'ALL PASS' : "$fail FAILED") . PHP_EOL;
exit($fail === 0 ? 0 : 1);
