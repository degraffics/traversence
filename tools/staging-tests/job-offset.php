<?php
// Staging's copies of the jobs start STAGING_OFFSET (7) minutes after live's (decisions/0165 §1; the CEO's ruling, 2026-11-26:
// live's 6:13 daily jobs missed their start with staging's copies in the same minute against 25 database connections).
// Scheduler::stagingTime (the offset, past :59 and past midnight), syncStaging (new copies at the offset) and offsetStaging (copies
// made before moved, live's lines never touched, a second run moves nothing), on a fake cPanel crontab. No database, no network.
// Run: php job-offset.php <root>/app
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/'); if (!is_file($app . '/lib/Scheduler.php')) { fwrite(STDERR, "usage: php job-offset.php <root>/app\n"); exit(2); }
$tmp = sys_get_temp_dir() . '/jo-' . getmypid(); @mkdir($tmp . '/lib', 0777, true);
foreach (glob($app . '/lib/*.php') as $f) copy($f, $tmp . '/lib/' . basename($f));
file_put_contents($tmp . '/lib/CPanel.php', '<?php final class CPanel { public static array $cron = []; public static array $calls = [];
  public static function ready(): bool { return true; }
  public static function api2(string $m, string $fn, array $q = []): array { self::$calls[] = $fn;
    if ($fn === "listcron") { $o = []; foreach (array_values(self::$cron) as $i => $c) $o[] = $c + ["line" => $i + 1, "linekey" => md5(json_encode($c))]; $o[] = ["count" => count($o)]; return $o; }
    if ($fn === "add_line") { self::$cron[] = ["minute" => $q["minute"], "hour" => $q["hour"], "day" => $q["day"], "month" => $q["month"], "weekday" => $q["weekday"], "command" => $q["command"]]; return []; }
    if ($fn === "edit_line") { foreach (self::$cron as $k => $c) if (md5(json_encode($c)) === $q["linekey"]) self::$cron[$k] = ["minute" => $q["minute"], "hour" => $q["hour"], "day" => $q["day"], "month" => $q["month"], "weekday" => $q["weekday"], "command" => $q["command"]]; return []; }
    if ($fn === "remove_line") { foreach (self::$cron as $k => $c) if (md5(json_encode($c)) === $q["linekey"]) unset(self::$cron[$k]); return []; }
    return []; } }');
require $tmp . '/lib/Scheduler.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got) : '') . "\n"; } };
$at = fn (string $line) => implode(' ', Scheduler::stagingTime(array_combine(['minute', 'hour', 'day', 'month', 'weekday'], explode(' ', $line))));
$t('the offset is 7 minutes', Scheduler::STAGING_OFFSET === 7);
$t('a daily 6:13 job: staging at 6:20', $at('13 6 * * *') === '20 6 * * *', $at('13 6 * * *'));
$t('hourly at :17: staging at :24', $at('17 * * * *') === '24 * * * *');
$t('past :59 carries into the next hour (6:55 → 7:02)', $at('55 6 * * *') === '2 7 * * *', $at('55 6 * * *'));
$t('every hour at :58 → :05 of the next hour', $at('58 * * * *') === '5 * * * *', $at('58 * * * *'));
$t('past 23:59 with no day set: 0:02 the next day', $at('55 23 * * *') === '2 0 * * *', $at('55 23 * * *'));
$t('past 23:59 on a set weekday: 7 minutes earlier instead (23:48), never another day', $at('55 23 * * 0') === '48 23 * * 0', $at('55 23 * * 0'));
$t('Sunday 3:13 → Sunday 3:20', $at('13 3 * * 0') === '20 3 * * 0');
$t('every 6 hours at :58: 7 minutes earlier (:51), its hours kept', $at('58 */6 * * *') === '51 */6 * * *', $at('58 */6 * * *'));
$t('every N minutes, every minute and a list: kept as live\'s', $at('*/15 * * * *') === '*/15 * * * *' && $at('* * * * *') === '* * * * *' && $at('5,35 * * * *') === '5,35 * * * *');
$t('… */24 kept too', $at('*/24 * * * *') === '*/24 * * * *');
// syncStaging: new copies at the offset
$L = '/home4/x/public_html/traversence/app/scripts/'; $S = '/home4/x/public_html/staging.trvgo.us/app/scripts';
$line = fn ($m, $h, $job, $base) => ['minute' => $m, 'hour' => $h, 'day' => '*', 'month' => '*', 'weekday' => '*', 'command' => '/usr/local/bin/php ' . $base . $job . '.php >/dev/null 2>&1'];
CPanel::$cron = [$line('13', '6', 'billing_tick', $L), $line('13', '6', 'guest_tick', $L), $line('*/15', '*', 'control_tick', $L)];
[$added] = Scheduler::syncStaging(true, $S);
$st = array_values(array_filter(CPanel::$cron, fn ($c) => str_contains($c['command'], '/staging.trvgo.us/')));
$by = []; foreach ($st as $c) { preg_match('#scripts/([a-z_]+)\.php#', $c['command'], $m); $by[$m[1]] = $c['minute'] . ' ' . $c['hour']; }
$t('syncStaging: guest_tick\'s copy at 6:20 (billing_tick isn\'t copied: it talks to the outside world)', ($by['guest_tick'] ?? '') === '20 6' && !isset($by['billing_tick']), $by);
$t('… control_tick\'s every 15 minutes kept', !isset($by['control_tick']) || $by['control_tick'] === '*/15 *', $by);
$t('… live\'s lines unchanged', CPanel::$cron[0]['minute'] === '13' && CPanel::$cron[1]['minute'] === '13');
// offsetStaging: copies made before the offset moved, live's never touched
CPanel::$cron = [$line('13', '6', 'guest_tick', $L), $line('13', '6', 'earned_storage_tick', $L), $line('17', '*', 'moments_tick', $L),
  $line('13', '6', 'guest_tick', $S . '/'), $line('13', '6', 'earned_storage_tick', $S . '/'), $line('17', '*', 'moments_tick', $S . '/'), $line('5', '4', 'gone_tick', $S . '/')];
$live0 = array_slice(CPanel::$cron, 0, 3);
$moved = Scheduler::offsetStaging(); sort($moved);
$t('offsetStaging: the three copies at live\'s minute moved', $moved === ['earned_storage_tick', 'guest_tick', 'moments_tick'], $moved);
$now = []; foreach (CPanel::$cron as $c) if (str_contains($c['command'], '/staging.trvgo.us/')) { preg_match('#scripts/([a-z_]+)\.php#', $c['command'], $m); $now[$m[1]] = $c['minute'] . ' ' . $c['hour']; }
$t('… to 6:20, 6:20 and :24', ($now['guest_tick'] ?? '') === '20 6' && ($now['earned_storage_tick'] ?? '') === '20 6' && ($now['moments_tick'] ?? '') === '24 *', $now);
$t('… a copy with no live line left as it is', ($now['gone_tick'] ?? '') === '5 4', $now);
$t('… live\'s lines untouched, byte for byte', array_slice(array_values(CPanel::$cron), 0, 3) === $live0);
$t('… the same command kept', count(array_filter(CPanel::$cron, fn ($c) => str_contains($c['command'], 'staging.trvgo.us/app/scripts/guest_tick.php'))) === 1);
$t('a second run moves nothing', Scheduler::offsetStaging() === []);
CPanel::$cron[0]['minute'] = '40';   // live's line changed by hand
$t('live\'s line changed: its copy follows (6:47)', Scheduler::offsetStaging() === ['guest_tick'] && (function () { foreach (CPanel::$cron as $c) if (str_contains($c['command'], 'staging.trvgo.us/app/scripts/guest_tick.php')) return $c['minute'] === '47'; return false; })());
$src = file_get_contents($app . '/lib/Scheduler.php');
$t('sync() runs offsetStaging, hourly at most, a failure only logged', (bool) preg_match('/try \{ if \(\$mv = self::offsetStaging\(\)\)/', $src) && strpos($src, 'self::offsetStaging()') > strpos($src, "WHERE job = '_sync'"));
array_map('unlink', glob($tmp . '/lib/*')); rmdir($tmp . '/lib'); rmdir($tmp);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
