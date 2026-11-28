Two release folders follow, in risk order: **f08** (medium), then **f24** (low).

- Both fixes change the same live file. f24 is built on top of f08, so ship f08 first. The release.json files state this.
- The live path is my inference, `website/api/stg-bar.php`. I read it from the file's own header and its `__DIR__ . '/../../app/'` requires. Each `verify` list asks the lane session to confirm it.
- Nothing here has been run.

=== FILE: f08/files/website/api/stg-bar.php ===
<?php
// /api/stg-bar.php — on the staging copy only (decisions/0145 Phase 5): the staging bar's line to the live site. The bar's
// cookie (signed by staging with RELEASE_BAR_KEY when the live admin let you in) says who you are on live and what you may
// do with releases; this passes the bar's request to the live site (website/api/release-bar.php), signed with the same key,
// and hands back its answer. Releases live on the live site: staging only shows them.
//   GET ?rel=<id>            -> {ok, releases: [...], release: {...}|null}
//   POST {action, rel, args}  -> {ok, message}
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/Staging.php';
require_once __DIR__ . '/../../app/lib/Releases.php';
header('Cache-Control: no-store');
if (!Staging::here()) { http_response_code(404); exit; }
Database::readEnv();
$key = (string) (getenv('RELEASE_BAR_KEY') ?: '');
if ($key === '') Response::error('The staging bar isn\'t set up on this server (no release key). Open the release on the live admin instead.', 503, 'bar_unconfigured');   // an empty key would let anyone sign a bar cookie and the live call
$bar = (string) ($_COOKIE['tv_stg_bar'] ?? '');
if (!Releases::verify($bar, $key)) Response::error('The staging bar has run out (it lasts 12 hours): open staging again from Releases on the live site.', 401, 'bar_expired');
$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
if ($post && ($_SERVER['HTTP_X_TV_STG_BAR'] ?? '') !== '1') Response::error('Not allowed.', 403);   // only the bar itself (a header no other site can send)
$in = $post ? Response::jsonBody() : $_GET;
$body = base64_encode((string) json_encode(['bar' => $bar, 'action' => $post ? (string) ($in['action'] ?? '') : 'state', 'rel' => (int) ($in['rel'] ?? 0), 'args' => (array) ($in['args'] ?? [])], JSON_UNESCAPED_SLASHES));
// the host's bot check (Bluehost, 2026-11-15) answers some server calls 409 with a cookie to set: sent always, its name
// read from the challenge if it changes, and asked once more (the stopgap until Bluehost exempts /api/)
$cookie = 'humans_21909=1';
for ($try = 0; $try < 2; $try++) {
    $ch = curl_init('https://traversence.com/api/release-bar.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 280, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_POSTFIELDS => json_encode(['b64' => $body]), CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Cookie: ' . $cookie, 'X-Bar-Sig: ' . hash_hmac('sha256', $body, $key)]]);
    $res = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code !== 409 || !is_string($res) || !preg_match('/document\.cookie\s*=\s*["\'](humans_\d+)=([^;"\']*)/', $res, $hm)) break;
    $cookie = $hm[1] . '=' . ($hm[2] !== '' ? $hm[2] : '1');
}
$j = is_string($res) ? json_decode($res, true) : null;
if (!is_array($j)) Response::error('The live site didn\'t answer (' . ($code ?: 'no connection') . '). Try again, or open the release on the live admin.', 502);
http_response_code($code ?: 200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($j, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
=== END FILE ===

=== FILE: f08/tests/stg-bar-empty-key.php ===
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
=== END FILE ===

=== FILE: f08/faq/faq-entries.md ===
no faq change

(The endpoint only serves the staging bar. The new refusal message is shown only to admins on a misconfigured staging server.)
=== END FILE ===

=== FILE: f08/release.json ===
{
  "name": "f08-stg-bar-refuse-empty-key",
  "gate": "staging-only endpoint (Staging::here()); ship BEFORE f24 (f24's file is built on this one)",
  "what": "website/api/stg-bar.php refuses with 503 'bar_unconfigured' when RELEASE_BAR_KEY is empty or missing. The refusal comes before the bar cookie is checked and before anything is signed or sent to the live site. Before this fix, an empty key would sign with HMAC key '' and let anyone forge a bar cookie and the X-Bar-Sig.",
  "files": ["files/website/api/stg-bar.php"],
  "decisions": [
    "Status 503 with reason 'bar_unconfigured' (a server setup problem, not the visitor's fault). The message points to the live admin.",
    "A value of '0' is also refused, because the existing (getenv(...) ?: '') already turns '0' into ''. This is unchanged behaviour, now made explicit."
  ],
  "tests": ["tests/stg-bar-empty-key.php (install as tools/staging-tests/stg-bar-empty-key.php; it takes the live file path as argv[1])"],
  "risks": [
    "DRAFT: nothing here has been run, linted, tested or zip-checked.",
    "If Response::error() does not end the request, execution would continue to Releases::verify. The test's stub exits, so the real Response::error must be checked.",
    "If staging currently runs with no RELEASE_BAR_KEY, the bar will stop working there and show the new 503 message until the key is set."
  ],
  "verify": [
    "Confirm the live path is website/api/stg-bar.php. It was inferred from the header comment and the __DIR__ . '/../../app/' requires; the attached copy came from a scratchpad path.",
    "staging-updates.php: file not shown. Confirm its line 16 guard matches this one in status code and wording, and align them if a person prefers that file's style.",
    "app/bootstrap.php, app/lib/Releases.php, Response: file not shown. Confirm Response::error(string, int, string) exits.",
    "Confirm RELEASE_BAR_KEY is set on staging before shipping, so the bar keeps working.",
    "Run tests/stg-bar-empty-key.php and php -l on the file."
  ],
  "regions": false
}
=== END FILE ===

=== FILE: f24/files/website/api/stg-bar.php ===
<?php
// /api/stg-bar.php — on the staging copy only (decisions/0145 Phase 5): the staging bar's line to the live site. The bar's
// cookie (signed by staging with RELEASE_BAR_KEY when the live admin let you in) says who you are on live and what you may
// do with releases; this passes the bar's request to the live site (website/api/release-bar.php), signed with the same key,
// and hands back its answer. Releases live on the live site: staging only shows them.
//   GET ?rel=<id>            -> {ok, releases: [...], release: {...}|null}
//   POST {action, rel, args}  -> {ok, message}
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/Staging.php';
require_once __DIR__ . '/../../app/lib/Releases.php';
header('Cache-Control: no-store');
if (!Staging::here()) { http_response_code(404); exit; }
Database::readEnv();
$key = (string) (getenv('RELEASE_BAR_KEY') ?: '');
if ($key === '') Response::error('The staging bar isn\'t set up on this server (no release key). Open the release on the live admin instead.', 503, 'bar_unconfigured');   // an empty key would let anyone sign a bar cookie and the live call
$bar = (string) ($_COOKIE['tv_stg_bar'] ?? '');
if (!Releases::verify($bar, $key)) Response::error('The staging bar has run out (it lasts 12 hours): open staging again from Releases on the live site.', 401, 'bar_expired');
$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
if ($post && ($_SERVER['HTTP_X_TV_STG_BAR'] ?? '') !== '1') Response::error('Not allowed.', 403);   // only the bar itself (a header no other site can send)
$in = $post ? Response::jsonBody() : $_GET;
$body = base64_encode((string) json_encode(['bar' => $bar, 'action' => $post ? (string) ($in['action'] ?? '') : 'state', 'rel' => (int) ($in['rel'] ?? 0), 'args' => (array) ($in['args'] ?? [])], JSON_UNESCAPED_SLASHES));
// the host's bot check (Bluehost, 2026-11-15) answers some server calls 409 with a cookie to set: sent always, its name
// read from the challenge if it changes, and asked once more (the stopgap until Bluehost exempts /api/)
$cookie = 'humans_21909=1';
for ($try = 0; $try < 2; $try++) {
    $ch = curl_init('https://traversence.com/api/release-bar.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 280, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_POSTFIELDS => json_encode(['b64' => $body]), CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Cookie: ' . $cookie, 'X-Bar-Sig: ' . hash_hmac('sha256', $body, $key)]]);
    $res = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code !== 409 || !is_string($res) || !preg_match('/document\.cookie\s*=\s*["\'](humans_\d+)=([^;"\']*)/', $res, $hm)) break;
    if (!preg_match('/\A[A-Za-z0-9._-]*\z/', $hm[2])) break;   // only a plain token may reach the Cookie header (no CR/LF, spaces or ';'); otherwise keep the 409 answer
    $cookie = $hm[1] . '=' . ($hm[2] !== '' ? $hm[2] : '1');
}
$j = is_string($res) ? json_decode($res, true) : null;
if (!is_array($j)) Response::error('The live site didn\'t answer (' . ($code ?: 'no connection') . '). Try again, or open the release on the live admin.', 502);
http_response_code($code ?: 200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($j, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
=== END FILE ===

=== FILE: f24/tests/stg-bar-cookie-value.php ===
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
=== END FILE ===

=== FILE: f24/faq/faq-entries.md ===
no faq change

(This changes only the server-to-server call from staging to the live site. Nothing visible changes for visitors or admins.)
=== END FILE ===

=== FILE: f24/release.json ===
{
  "name": "f24-stg-bar-cookie-value-charset",
  "gate": "staging-only endpoint; ship AFTER f08 (this file already contains f08's empty-key guard)",
  "what": "In website/api/stg-bar.php, a cookie value read from the host's 409 bot-check page is used only if the whole value matches [A-Za-z0-9._-]*. The check uses \\A...\\z, because '$' would allow a trailing newline. Otherwise the retry is skipped and the 409 answer is handled as before. This keeps CR/LF, spaces and ';' out of the outgoing Cookie header.",
  "files": ["files/website/api/stg-bar.php"],
  "decisions": [
    "Safest default: on a bad value, stop retrying and do not fall back to 'name=1'. This avoids sending a guessed cookie. The caller gets the existing 502 or the live site's answer as before.",
    "The cookie name is already limited to humans_\\d+ by the existing pattern and is left unchanged.",
    "Built on f08's file so that both fixes can ship in order without conflict."
  ],
  "tests": ["tests/stg-bar-cookie-value.php (install as tools/staging-tests/stg-bar-cookie-value.php; it takes the live file path as argv[1])"],
  "risks": [
    "DRAFT: nothing here has been run, linted, tested or zip-checked.",
    "If Bluehost's challenge ever issues values outside [A-Za-z0-9._-] (for example base64 with '+', '/' or '='), the retry stops working and the bar sees the 409 or 502 path. A person should check a real challenge page.",
    "If this ships without f08, it also ships f08's change. If f08 is rejected, remove the $key === '' line from this file."
  ],
  "verify": [
    "Decision needed: confirm with a real Bluehost 409 challenge body that its cookie values fit [A-Za-z0-9._-]. If they don't, choose between widening the set (never CR, LF, ';' or space) and keeping the refusal.",
    "Confirm the live path is website/api/stg-bar.php (inferred; the attached copy came from a scratchpad path).",
    "Confirm f08 has shipped, or ship both together.",
    "Run tests/stg-bar-cookie-value.php and php -l on the file."
  ],
  "regions": false
}
=== END FILE ===