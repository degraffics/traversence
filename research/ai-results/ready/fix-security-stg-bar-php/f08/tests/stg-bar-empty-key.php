<?php
// tools/staging-tests/stg-bar-empty-key.php — f08: /api/stg-bar.php must refuse when RELEASE_BAR_KEY is empty, before
// the bar cookie is checked and before any call to the live site. Runs the endpoint in a throwaway sandbox with stub
// bootstrap/Staging/Releases (no network: the stub Releases::verify stops the script the moment it is reached).
// Usage: php stg-bar-empty-key.php [path/to/website/api/stg-bar.php]
declare(strict_types=1);

$live = $argv[1] ?? __DIR__ . '/../../website/api/stg-bar.php';
$fail = 0;
function t(string $name, bool $ok, string $extra = ''): void
{
    global $fail;
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . ($ok || $extra === '' ? '' : ' — ' . $extra) . "\n";
    if (!$ok) $fail++;
}
if (!is_file($live)) { echo "FAIL: stg-bar.php not found at $live (pass its path as the first argument)\n"; exit(1); }
$src = (string) file_get_contents($live);

// static: the guard exists and sits before the cookie check
$gp = strpos($src, "if (\$key === '') Response::error(");
$vp = strpos($src, 'Releases::verify(');
t('empty-key guard present', $gp !== false);
t('guard comes before Releases::verify', $gp !== false && $vp !== false && $gp < $vp);

// lint
$out = []; $rc = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($live) . ' 2>&1', $out, $rc);
t('php -l clean', $rc === 0, implode(' ', $out));

// sandbox: <tmp>/app/{bootstrap.php,lib/Staging.php,lib/Releases.php} + <tmp>/website/api/stg-bar.php
$tmp = sys_get_temp_dir() . '/stgbar-f08-' . bin2hex(random_bytes(4));
mkdir($tmp . '/app/lib', 0777, true);
mkdir($tmp . '/website/api', 0777, true);
copy($live, $tmp . '/website/api/stg-bar.php');
file_put_contents($tmp . '/app/bootstrap.php', <<<'PHP'
<?php
final class Database { public static function readEnv(): void {} }
final class Response {
    public static function error(string $m, int $code = 400, string $reason = ''): void { echo "ERROR $code $reason\n"; exit(0); }
    public static function jsonBody(): array { return []; }
}
PHP);
file_put_contents($tmp . '/app/lib/Staging.php', "<?php\nfinal class Staging { public static function here(): bool { return true; } }\n");
file_put_contents($tmp . '/app/lib/Releases.php', "<?php\nfinal class Releases { public static function verify(string \$bar, string \$key): bool { echo \"REACHED_VERIFY\\n\"; exit(0); } }\n");

function runBar(string $tmp, ?string $keyVal): string
{
    $env = ['PATH' => (string) getenv('PATH')];
    if ($keyVal !== null) $env['RELEASE_BAR_KEY'] = $keyVal;
    $p = proc_open([PHP_BINARY, $tmp . '/website/api/stg-bar.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $tmp, $env);
    if (!is_resource($p)) return 'PROC_OPEN_FAILED';
    $o = (string) stream_get_contents($pipes[1]); fclose($pipes[1]);
    $e = (string) stream_get_contents($pipes[2]); fclose($pipes[2]);
    proc_close($p);
    return trim($o . $e);
}

$r = runBar($tmp, '');
t('empty key -> 503 bar_unconfigured', str_contains($r, 'ERROR 503 bar_unconfigured') && !str_contains($r, 'REACHED_VERIFY'), $r);
$r = runBar($tmp, null);
t('missing key -> 503 bar_unconfigured', str_contains($r, 'ERROR 503 bar_unconfigured') && !str_contains($r, 'REACHED_VERIFY'), $r);
$r = runBar($tmp, 'a-real-key');
t('set key -> goes on to the cookie check', str_contains($r, 'REACHED_VERIFY') && !str_contains($r, 'bar_unconfigured'), $r);

// cleanup
foreach (['website/api/stg-bar.php', 'app/lib/Staging.php', 'app/lib/Releases.php', 'app/bootstrap.php'] as $f) @unlink($tmp . '/' . $f);
foreach (['website/api', 'website', 'app/lib', 'app', ''] as $d) @rmdir(rtrim($tmp . '/' . $d, '/'));

echo $fail === 0 ? "ALL PASS\n" : "$fail FAIL\n";
exit($fail === 0 ? 0 : 1);
