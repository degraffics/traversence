<?php
// Hours::now and Hours::zone (search release 2; decisions/0181's note, 2026-11-27: honest hours): where a place stands
// now, in its own zone, never by the visitor's clock; "Hours not known" when none can be read; the zone table's one home
// (JourneyPlan::timezone reads it). No database.
// Run: php tools/search-tests/hours-now.php <path to app/lib>
declare(strict_types=1);
$lib = rtrim($argv[1] ?? '', '/');
require_once $lib . '/Hours.php';
$ok = 0; $bad = 0;
$t = function (string $n, bool $c, string $x = '') use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? 'PASS ' : 'FAIL ') . $n . ($x !== '' ? "  [$x]" : '') . "\n"; };
// moments, as UTC epoch seconds
$at = fn (string $utc) => (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->getTimestamp();
$wk = Hours::parse("Mon-Fri 8am-9pm\nSat 9am-5pm\nSun Closed");
$phx = Hours::zone('AZ');
$t('Arizona keeps Phoenix time', $phx === 'America/Phoenix', $phx);
$t('New Mexico is Denver', Hours::zone('nm') === 'America/Denver');
$t('no state: by longitude (California)', Hours::zone('', -118.2) === 'America/Los_Angeles');
$t('no state, no longitude: Chicago, as JourneyPlan always read it', Hours::zone('') === 'America/Chicago');
// Fri 2026-11-27 20:30 Phoenix = Sat 03:30 UTC
$r = Hours::now($wk, $phx, $at('2026-11-28 03:30'));
$t('open in its own zone at 8:30 PM: closes 9 PM', $r['open'] === true && $r['text'] === 'Open · Closes 9 PM' && $r['known'], $r['text']);
// the same instant read by a visitor in New York would be 10:30 PM there: the listing's zone decides
$r2 = Hours::now($wk, 'America/New_York', $at('2026-11-28 03:30'));
$t('the same moment in another zone reads differently (the zone matters)', $r2['open'] === false, $r2['text']);
// Fri 21:30 Phoenix = Sat 04:30 UTC: closed, opens tomorrow (Sat) 9 AM
$r = Hours::now($wk, $phx, $at('2026-11-28 04:30'));
$t('closed after hours: opens tomorrow', $r['open'] === false && $r['text'] === 'Closed · Opens tomorrow 9 AM', $r['text']);
// Sat 07:00 Phoenix = Sat 14:00 UTC: closed, opens 9 AM today
$r = Hours::now($wk, $phx, $at('2026-11-28 14:00'));
$t('before opening: opens at 9 AM', $r['open'] === false && $r['text'] === 'Closed · Opens 9 AM', $r['text']);
// Sun 12:00 Phoenix = Sun 19:00 UTC: closed Sunday, opens Mon 8 AM
$r = Hours::now($wk, $phx, $at('2026-11-29 19:00'));
$t('closed all day: the next day it opens', $r['open'] === false && $r['text'] === 'Closed · Opens tomorrow 8 AM', $r['text']);
// past midnight: Fri 6pm-2am; Sat 01:00 Phoenix = Sat 08:00 UTC
$late = Hours::parse('Fri 6pm-2am');
$r = Hours::now($late, $phx, $at('2026-11-28 08:00'));
$t('open past midnight: closes 2 AM', $r['open'] === true && $r['text'] === 'Open · Closes 2 AM', $r['text']);
$r = Hours::now(Hours::parse('Open 24 hours'), $phx, $at('2026-11-28 08:00'));
$t('open 24 hours from the hours themselves', $r['open'] === true && $r['text'] === 'Open 24 hours', $r['text']);
$r = Hours::now(null, $phx);
$t('no hours: "Hours not known", never open', $r['open'] === null && $r['text'] === 'Hours not known' && !$r['known'], $r['text']);
$r = Hours::now(Hours::parse('Call for an appointment'), $phx);
$t('hours it can\'t read: "Hours not known"', $r['open'] === null && $r['text'] === 'Hours not known', $r['text']);
$r = Hours::now($wk, 'Not/AZone', $at('2026-11-28 03:30'));
$t('a bad zone never breaks the page', is_array($r) && $r['text'] !== '', $r['text']);
// JourneyPlan reads the same table (its one home is Hours)
if (is_file($lib . '/JourneyPlan.php')) {
    $src = file_get_contents($lib . '/JourneyPlan.php');
    $t('JourneyPlan::timezone reads Hours::zone, no table of its own', str_contains($src, 'Hours::zone(') && !str_contains($src, 'STATE_TZ = ['));
}
echo "$ok passed, $bad failed\n"; exit($bad ? 1 : 0);
