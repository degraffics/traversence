<?php
// tools/kind-tests/f03-rental-hold-window.php — f03: a rental deposit is held on the card only when the return day
// starts within KindCheckout::HOLD_DAYS of now (time to return, not rental length). No database, no money.
// Run from the repo: php tools/kind-tests/f03-rental-hold-window.php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/lib/KindCheckout.php';
date_default_timezone_set('UTC');

$fail = 0;
function check(string $name, bool $ok): void { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$ok) $fail++; }

$now = strtotime('2026-12-01 10:00:00');
check('HOLD_DAYS is 6', KindCheckout::HOLD_DAYS === 6);
check('return in 5 days 14h: held', KindCheckout::holdFits('2026-12-07', $now) === true);
check('return in 6 days 14h: not held', KindCheckout::holdFits('2026-12-08', $now) === false);
check('exactly 6 days to the return day: held', KindCheckout::holdFits('2026-12-07', strtotime('2026-12-01 00:00:00')) === true);
check('a 2-day rental starting in 3 weeks: not held (the f03 case)', KindCheckout::holdFits('2026-12-24', $now) === false);
check('same-day rental (to = next day): held', KindCheckout::holdFits('2026-12-02', $now) === true);
check('unreadable date: not held', KindCheckout::holdFits('not a date', $now) === false);

$src = (string) file_get_contents($root . '/app/lib/KindCheckout.php');
check('prepare() uses holdFits($to) for the rental hold', str_contains($src, '!self::holdFits($to)'));
check('prepare() still refuses a hold off Stripe', str_contains($src, "\$provider !== 'stripe'"));

echo $fail ? "FAIL: $fail\n" : "PASS: all\n";
exit($fail ? 1 : 0);
