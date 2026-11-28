<?php
// tools/staging-tests/csp-report-tests.php — f25: CspReport input checks. Run: php csp-report-tests.php
declare(strict_types=1);

$candidates = [
    __DIR__ . '/../files/app/lib/CspReport.php',  // from the release folder
    __DIR__ . '/../../app/lib/CspReport.php',     // from tools/staging-tests/ in the repo
];
$lib = null;
foreach ($candidates as $c) {
    if (is_file($c)) { $lib = $c; break; }
}
if ($lib === null) { echo "FAIL could not find CspReport.php\n"; exit(1); }
require_once $lib;

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
    if (!$ok) { $fails++; }
}
function inert(string $s): bool
{
    return preg_match('/[<>"\'`\\\\\s\x00-\x1F\x7F]/', $s) !== 1;
}

// content type
check('type csp-report', CspReport::typeOk('application/csp-report'));
check('type json with charset', CspReport::typeOk('application/json; charset=utf-8'));
check('type form rejected', !CspReport::typeOk('application/x-www-form-urlencoded'));
check('type text rejected', !CspReport::typeOk('text/plain'));
check('type empty rejected', !CspReport::typeOk(''));

// host
check('host same', CspReport::hostOk('https://stg.example.com/a', 'stg.example.com', ''));
check('host same with port', CspReport::hostOk('https://stg.example.com:8443/a', 'stg.example.com:8443', ''));
check('host other doc rejected', !CspReport::hostOk('https://evil.test/a', 'stg.example.com', ''));
check('host other origin rejected', !CspReport::hostOk('https://stg.example.com/a', 'stg.example.com', 'https://evil.test'));
check('host null origin ok', CspReport::hostOk('https://stg.example.com/a', 'stg.example.com', 'null'));
check('host missing doc rejected', !CspReport::hostOk('', 'stg.example.com', ''));
check('host missing HTTP_HOST rejected', !CspReport::hostOk('https://stg.example.com/a', '', ''));

// blocked-uri cleaning
$x = CspReport::cleanUri('"><img src=x onerror=alert(1)>', 190);
check('xss payload made inert', inert($x) && $x !== '');
check('script tag made inert', inert(CspReport::cleanUri('<script>alert(1)</script>', 190)));
check('javascript scheme labelled', CspReport::cleanUri('javascript:alert(1)', 190) === 'other-scheme(javascript)');
check('JaVaScRiPt scheme labelled', CspReport::cleanUri('JaVaScRiPt:alert(1)', 190) === 'other-scheme(javascript)');
check('data url reduced', CspReport::cleanUri('data:text/html,<b>x</b>', 190) === 'data');
check('keyword inline kept', CspReport::cleanUri('inline', 190) === 'inline');
check('https url unchanged', CspReport::cleanUri('https://cdn.example.com/a.js?x=1&y=2', 190) === 'https://cdn.example.com/a.js?x=1&y=2');
check('length capped', strlen(CspReport::cleanUri('https://a.test/' . str_repeat('"', 500), 190)) <= 190);
check('empty stays empty', CspReport::cleanUri("  \t", 190) === '');

// page + directive
check('path inert', inert(CspReport::cleanPath('/a/"><svg onload=1>', 200)));
check('path normal kept', CspReport::cleanPath('/shop/item-1', 200) === '/shop/item-1');
check('directive cleaned', CspReport::cleanDirective('script-src-elem"><b>', 40) === 'script-src-elemb');

// rate cap
$f = tempnam(sys_get_temp_dir(), 'csp-rate-test');
$t = 1_700_000_000;
$a = CspReport::allow($f, $t, 3) && CspReport::allow($f, $t + 1, 3) && CspReport::allow($f, $t + 2, 3);
check('rate allows up to limit', $a);
check('rate blocks over limit', !CspReport::allow($f, $t + 3, 3));
check('rate resets next minute', CspReport::allow($f, $t + 60, 3));
@unlink($f);
check('rate fails closed on bad path', !CspReport::allow(sys_get_temp_dir() . '/no-such-dir-f25/x/y', $t, 3));

echo $fails === 0 ? "ALL PASS\n" : "FAILURES: $fails\n";
exit($fails === 0 ? 0 : 1);
