<?php
// B.2 step 1: Bluehost's own backups read from cPanel (HostBackups). php host-backups.php <root>, a root whose app/lib has
// HostBackups.php and the live CPanel.php, StagingGate.php, SearchIndex.php. No database, no network.
require $argv[1] . '/app/lib/HostBackups.php';
$f = 0; $t = function ($n, $ok, $g = null) use (&$f) { echo ($ok ? "  ok  " : "FAIL  ") . $n . ($ok ? '' : ' — ' . json_encode($g)) . "\n"; $f += $ok ? 0 : 1; };
$t('dates from strings, once each, oldest first', HostBackups::dates(['2026-11-26', '2026-11-25', '2026-11-26']) === ['2026-11-25', '2026-11-26']);
$t('dates from rows with a time or a date', HostBackups::dates([['time' => 1764115200], ['date' => '20261120']]) === ['2025-11-26', '2026-11-20']);
$t('dates from keys', HostBackups::dates(['2026-11-24' => 'x']) === ['2026-11-24']);
$t('nothing dated: none', HostBackups::dates([['name' => 'weekly']]) === []);
$t('cadence: daily dates say about daily', HostBackups::cadence(['2026-11-24', '2026-11-25', '2026-11-26']) === 'about daily');
$t('cadence: weekly', HostBackups::cadence(['2026-11-05', '2026-11-12', '2026-11-19', '2026-11-26']) === 'about weekly');
$t('cadence: too few or uneven says nothing', HostBackups::cadence(['2026-11-25', '2026-11-26']) === '' && HostBackups::cadence(['2026-11-01', '2026-11-04', '2026-11-20']) === '');
$s = ['at' => 1764214800, 'ok' => true, 'backups' => ['2026-11-24', '2026-11-25', '2026-11-26'], 'latest' => '2026-11-26', 'count' => 3, 'jetbackup' => ['jetbackup5'], 'why' => ''];
[$st, $w] = HostBackups::words($s);
$t('found: one fact, the site\'s dates, how often, JetBackup in plain words', $st === 'found' && $w === 'Bluehost keeps 3, from Nov 24 to Nov 26, about daily, going by their dates. JetBackup is on for the account.', $w);
[$st, $w] = HostBackups::words(['count' => 0, 'backups' => [], 'jetbackup' => []] + $s);
$t('none listed', $st === 'none' && $w === 'cPanel lists no backups for this account. JetBackup isn\'t on for the account.', $w);
[$st, $w, $at, $next] = HostBackups::words(['ok' => false, 'why' => 'cPanel\'s Backup module: cPanel: The feature is not enabled.'] + $s);
$t('unread: cPanel\'s own error, and the next read a day on', $st === 'unread' && str_contains($w, 'couldn\'t be read: cPanel\'s Backup module') && $next - $at === 86400, [$w, $at, $next]);
[$st, $w] = HostBackups::words($s, true);
$t('staging: said plainly, never as a fault', $st === 'staging' && $w === 'Not read on staging; cPanel is read on live only.', $w);
[$st, $w] = HostBackups::words(['jetbackup' => null] + $s);
$t('JetBackup unknown: left unsaid', !str_contains($w, 'JetBackup'), $w);
putenv('CPANEL_HOST='); $r = HostBackups::read();
$t('read without cPanel keys: says so, nothing called', $r['ok'] === false && str_contains($r['why'], 'isn\'t connected'), $r);
echo $f ? "$f failed\n" : "all passed\n"; exit($f ? 1 : 0);
