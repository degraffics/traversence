<?php
// user/api/posts.php's database errors (the session manager, 2026-11-27, gate 1): a PDOException is a RuntimeException, so
// it used to reach the caller with its SQLSTATE. Now it's logged and the caller gets the plain words with its way forward;
// the RuntimeException codes it means to pass (401, 428, 429) still pass, and a refusal still says why. No database: the
// endpoint runs against stand-ins for bootstrap, Auth and MemberPosts, one request per check, in its own PHP process.
// php tools/posts-tests/pdo-error.php <path to website/user/api/posts.php>
$api = $argv[1] ?? '';
if (!is_file($api)) { fwrite(STDERR, "usage: php pdo-error.php <website/user/api/posts.php>\n"); exit(2); }
$tmp = sys_get_temp_dir() . '/posts-test-' . getmypid(); @mkdir($tmp . '/website/user/api', 0777, true); @mkdir($tmp . '/app/lib', 0777, true);
copy($api, $tmp . '/website/user/api/posts.php');
file_put_contents($tmp . '/app/bootstrap.php', '<?php
final class Response {
  public static function json(array $p, int $c = 200): never { echo json_encode(["status" => $c] + $p); exit; }
  public static function error(string $m, int $c = 400, string $k = "error", array $x = []): never { self::json(["ok" => false, "error" => ["code" => $k, "message" => $m] + $x], $c); }
  public static function requireMethod(string $m): void {} public static function requireCsrf(): void {} public static function jsonBody(): array { return []; }
}
final class Database { public static function connection(): PDO { return new PDO("sqlite::memory:"); } }
final class Auth { public static function currentUser(): ?array { return null; } }');
file_put_contents($tmp . '/app/lib/MemberPosts.php', '<?php
final class MemberPosts {
  public static function ready(PDO $p): bool { return true; }
  public static function forOwner(PDO $p, int $o, ?int $u, int $b): array {
    switch (getenv("THROW")) {
      case "pdo": throw new PDOException("SQLSTATE[42S22]: Column not found: 1054 Unknown column \'mp.secret\' in \'field list\'");
      case "rate": throw new RuntimeException("Slow down: try again in a minute.", 429);
      case "plain": throw new RuntimeException("The thing broke inside.");
      case "arg": throw new InvalidArgumentException("That post is shared with fewer people.");
    }
    return [];
  }
}');
$run = function (string $throw) use ($tmp): array {
    $code = 'putenv("THROW=' . $throw . '"); $_SERVER["REQUEST_METHOD"] = "GET"; $_GET = ["owner" => "1"]; require "' . $tmp . '/website/user/api/posts.php";';
    $out = []; exec(escapeshellarg(PHP_BINARY) . ' -d log_errors=1 -d error_log=' . escapeshellarg($tmp . '/err.log') . ' -r ' . escapeshellarg($code) . ' 2>&1', $out);
    return json_decode(implode('', $out), true) ?: ['raw' => implode("\n", $out)];
};
$ok = 0; $bad = 0; $t = function (string $n, bool $c, string $x = '') use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? '  ok  ' : 'FAIL  ') . $n . ($x !== '' ? "  [$x]" : '') . "\n"; };
@unlink($tmp . '/err.log');
$r = $run('pdo'); $m = (string) ($r['error']['message'] ?? '');
$t('a database error answers 500 with code server_error', ($r['status'] ?? 0) === 500 && ($r['error']['code'] ?? '') === 'server_error', json_encode($r));
$t('its words never show the SQLSTATE, the column or the query', $m !== '' && !preg_match('/SQLSTATE|42S22|column|mp\.secret/i', $m), $m);
$t('its words carry the way forward: try again, and how to tell us', str_contains($m, 'Try again') && str_contains($m, 'support@traversence.com'), $m);
$t('the error is logged for us, in full', str_contains((string) @file_get_contents($tmp . '/err.log'), 'user/api/posts.php database: SQLSTATE[42S22]'));
$r = $run('rate'); $t('a 429 still passes as too_many, its words kept', ($r['status'] ?? 0) === 429 && ($r['error']['code'] ?? '') === 'too_many' && ($r['error']['message'] ?? '') === 'Slow down: try again in a minute.', json_encode($r));
$r = $run('plain'); $t('another RuntimeException is unchanged (500, its words)', ($r['status'] ?? 0) === 500 && ($r['error']['message'] ?? '') === 'The thing broke inside.', json_encode($r));
$r = $run('arg'); $t('a refusal still says why (422)', ($r['status'] ?? 0) === 422 && ($r['error']['code'] ?? '') === 'not_allowed', json_encode($r));
$r = $run(''); $t('a good read is unchanged', ($r['status'] ?? 0) === 200 && ($r['ok'] ?? false) === true && ($r['posts'] ?? null) === [], json_encode($r));
array_map('unlink', array_filter(glob($tmp . '/{,*/,*/*/,*/*/*/}*', GLOB_BRACE), 'is_file'));
echo "$ok passed, $bad failed\n"; exit($bad ? 1 : 0);
