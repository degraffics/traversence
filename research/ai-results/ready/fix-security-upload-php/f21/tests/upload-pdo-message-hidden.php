<?php
// tools/upload-tests/upload-pdo-message-hidden.php — DRAFT (f21). Runs api/upload.php in a throwaway sandbox with stand-in
// classes (no database, no real uploads, no money), one PHP process per case. Prints PASS/FAIL per check.
//   php upload-pdo-message-hidden.php [path/to/api/upload.php]     default: this release's files/api/upload.php
// Against the live file the database cases should FAIL (that is the bug). Needs the pdo extension (for PDOException).
declare(strict_types=1);

$subject = $argv[1] ?? __DIR__ . '/../files/api/upload.php';
if (!is_file($subject)) { echo "FAIL: can't find $subject\n"; exit(1); }

$stubs = <<<'PHP'
<?php
// stand-ins for the real bootstrap: every answer is thrown so the test can read it instead of the script exiting
final class StopResponse extends Exception {
    public array $out;
    public function __construct(array $out) { parent::__construct('stop'); $this->out = $out; }
}
final class Response {
    public static function jsonBody(): array { return $GLOBALS['T']['body'] ?? []; }
    public static function success($data = null) { throw new StopResponse(['status' => 200, 'ok' => true, 'data' => $data]); }
    public static function error(string $message, int $status = 400, string $code = 'error', array $extra = []) {
        throw new StopResponse(['status' => $status, 'ok' => false, 'error' => ['code' => $code, 'message' => $message] + $extra]);
    }
}
final class Auth { public static function currentUserId(): ?int { return $GLOBALS['T']['uid'] ?? null; } }
final class Database {
    public static function connection() {
        if (!empty($GLOBALS['T']['db_down'])) throw new PDOException("SQLSTATE[HY000] [1045] Access denied for user 'tv_secret'@'10.0.0.9'");
        return new stdClass();
    }
}
final class Uploads {
    public static function targetOf(string $token): string { return $GLOBALS['T']['target'] ?? 'photos'; }
    public static function guest(string $target): bool { return $target === 'guest-drop'; }
    private static function act(string $what) {
        switch ($GLOBALS['T']['throw'] ?? '') {
            case 'pdo': throw new PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'tv_secret.uploads' doesn't exist");
            case 'runtime': throw new RuntimeException('That upload has expired. Start it again.');
            case 'fatal': return tv_no_such_function();
            case 'oom': return strlen(str_repeat('x', 64 * 1024 * 1024));
        }
        return ['did' => $what];
    }
    public static function start(...$a) { return self::act('start'); }
    public static function piece(...$a) { return self::act('piece'); }
    public static function finish(...$a) { return self::act('finish'); }
    public static function abort(...$a) { self::act('abort'); }
    public static function status(...$a) { return self::act('status'); }
    public static function gallery(...$a) { return self::act('gallery'); }
}
PHP;

$runner = <<<'PHP'
<?php
$T = json_decode((string) file_get_contents($argv[1]), true);
$GLOBALS['T'] = $T;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';
if (isset($T['header'])) $_SERVER['HTTP_X_CSRF_TOKEN'] = $T['header'];
$_SESSION = $T['session'] ?? [];
try {
    require __DIR__ . '/web/api/upload.php';
    echo json_encode(['fell_through' => true]);
} catch (StopResponse $s) {
    echo json_encode($s->out);
}
PHP;

$box = sys_get_temp_dir() . '/upload-test-' . bin2hex(random_bytes(4));
mkdir($box . '/web/api', 0777, true);
mkdir($box . '/app/lib', 0777, true);
copy($subject, $box . '/web/api/upload.php');
file_put_contents($box . '/app/bootstrap.php', $stubs);
file_put_contents($box . '/app/lib/Uploads.php', "<?php\n// stand-in: the test Uploads class is in bootstrap.php\n");
file_put_contents($box . '/run.php', $runner);

function run(string $box, array $case, array $ini = []): array {
    static $n = 0;
    $n++;
    $caseFile = "$box/case$n.json";
    $log = "$box/case$n.log";
    file_put_contents($caseFile, json_encode($case));
    $cmd = [PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'error_log=' . $log];
    foreach ($ini as $k => $v) array_push($cmd, '-d', "$k=$v");
    array_push($cmd, "$box/run.php", $caseFile);
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = (string) stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
    return ['raw' => $out, 'out' => json_decode($out, true) ?: [], 'log' => is_file($log) ? (string) file_get_contents($log) : ''];
}
function rmTree(string $d): void {
    foreach (array_diff(scandir($d) ?: [], ['.', '..']) as $f) { is_dir("$d/$f") ? rmTree("$d/$f") : unlink("$d/$f"); }
    rmdir($d);
}
$fails = 0;
function check(string $name, bool $ok, string $detail = ''): void {
    echo ($ok ? 'PASS' : 'FAIL') . ": $name" . ($ok || $detail === '' ? '' : " — got: $detail") . "\n";
    if (!$ok) $GLOBALS['fails']++;
}

$base = ['uid' => 5, 'session' => ['csrf_token' => 'abc'], 'header' => 'abc',
         'body' => ['do' => 'start', 'target' => 'photos', 'name' => 'a.jpg', 'size' => 10, 'type' => 'image/jpeg']];

// a query fails inside Uploads
$r = run($box, $base + ['throw' => 'pdo']);
$msg = (string) ($r['out']['error']['message'] ?? '');
check('query error: answered by our handler (409, code error)', ($r['out']['status'] ?? null) === 409 && ($r['out']['error']['code'] ?? null) === 'error', $r['raw']);
check('query error: no SQLSTATE / table / schema in the answer', $msg !== '' && !str_contains($r['raw'], 'SQLSTATE') && !str_contains($r['raw'], 'tv_secret'), $r['raw']);
check('query error: full text still goes to the log', str_contains($r['log'], 'SQLSTATE[42S02]'), $r['log']);

// the database won't connect at all
$r = run($box, $base + ['db_down' => true]);
check('no connection: answered by our handler, not a crash', ($r['out']['status'] ?? null) === 409 && ($r['out']['error']['code'] ?? null) === 'error', $r['raw']);
check('no connection: no user / host / "Access denied" in the answer', !str_contains($r['raw'], 'tv_secret') && !str_contains($r['raw'], '10.0.0.9') && !str_contains($r['raw'], 'Access denied'), $r['raw']);
check('no connection: full text still goes to the log', str_contains($r['log'], 'Access denied'), $r['log']);

// unchanged: other RuntimeExceptions still show their own (visitor-facing) message
$r = run($box, $base + ['throw' => 'runtime']);
check('other errors keep their message (409)', ($r['out']['status'] ?? null) === 409 && ($r['out']['error']['message'] ?? '') === 'That upload has expired. Start it again.', $r['raw']);

// unchanged: a normal start works
$r = run($box, $base);
check('normal start still succeeds', ($r['out']['status'] ?? null) === 200 && ($r['out']['data']['did'] ?? '') === 'start', $r['raw']);

rmTree($box);
echo $fails ? "FAIL: $fails check(s) failed\n" : "PASS: all checks\n";
exit($fails ? 1 : 0);
