<?php
// tools/staging-tests/stg-bar-cookie-value.php — f24: a cookie value read from the host's 409 bot-check page may only
// reach the outgoing Cookie header if it is [A-Za-z0-9._-]* (whole string; no CR/LF). Static check of the live file plus
// the same challenge-parsing steps replayed on sample pages (no network).
// Usage: php stg-bar-cookie-value.php [path/to/website/api/stg-bar.php]
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

$challenge = <<<'G'
preg_match('/document\.cookie\s*=\s*["\'](humans_\d+)=([^;"\']*)/', $res, $hm)
G;
$guard = <<<'G'
if (!preg_match('/\A[A-Za-z0-9._-]*\z/', $hm[2])) break;
G;
$assign = <<<'G'
$cookie = $hm[1] . '=' . ($hm[2] !== '' ? $hm[2] : '1');
G;
$cp = strpos($src, $challenge); $gp = strpos($src, $guard); $ap = strpos($src, $assign);
t('challenge parse line unchanged', $cp !== false);
t('value guard present', $gp !== false);
t('guard sits between parse and cookie assignment', $cp !== false && $gp !== false && $ap !== false && $cp < $gp && $gp < $ap);
t('f08 empty-key guard still present (f24 is built on f08)', str_contains($src, "if (\$key === '') Response::error("));

$out = []; $rc = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($live) . ' 2>&1', $out, $rc);
t('php -l clean', $rc === 0, implode(' ', $out));

// replay of the loop's parse steps: returns the next cookie, or null when the loop would stop
function nextCookie(string $res): ?string
{
    if (!preg_match('/document\.cookie\s*=\s*["\'](humans_\d+)=([^;"\']*)/', $res, $hm)) return null;
    if (!preg_match('/\A[A-Za-z0-9._-]*\z/', $hm[2])) return null;
    return $hm[1] . '=' . ($hm[2] !== '' ? $hm[2] : '1');
}
t('plain token accepted', nextCookie('<script>document.cookie = "humans_21909=abc.DEF-1_2; path=/"</script>') === 'humans_21909=abc.DEF-1_2');
t('empty value becomes 1', nextCookie("document.cookie='humans_5='") === 'humans_5=1');
t('CRLF refused', nextCookie("document.cookie=\"humans_1=abc\r\nX-Evil: 1\"") === null);
t('lone LF refused', nextCookie("document.cookie=\"humans_1=abc\nX: y\"") === null);
t('trailing newline refused (\\z, not $)', nextCookie("document.cookie=\"humans_1=abc\n\"") === null);
t('space / comma refused', nextCookie('document.cookie="humans_1=a b,c"') === null);
t('no challenge -> stop', nextCookie('{"ok":false}') === null);
foreach (['humans_21909=1', nextCookie('document.cookie="humans_7=zz"') ?? ''] as $c) {
    t('header line has no CR/LF: ' . $c, !preg_match('/[\r\n]/', 'Cookie: ' . $c));
}

echo $fail === 0 ? "ALL PASS\n" : "$fail FAIL\n";
exit($fail === 0 ? 0 : 1);
