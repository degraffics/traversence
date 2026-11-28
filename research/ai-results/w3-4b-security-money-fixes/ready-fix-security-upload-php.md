Three release folders follow, highest risk first: **f06** (medium), **f21** (low), **f22** (low).

- **Each folder stands alone.** Each one is cut from the attached `api/upload.php` and contains the whole file.
- **Don't drop them in one after another as they are.** Each `files/api/upload.php` is a complete copy, so a later folder would undo the earlier fix. The three edits don't overlap (around line 24, around line 32 plus lines 52–54, and line 38), so whichever ships second or third must be re-cut on top of the ones already live. Every `release.json` says this.
- **The tests use stand-ins.** Each test runs the release's `upload.php` in a throwaway sandbox with stand-in `Response`, `Auth`, `Database` and `Uploads` classes, one PHP process per case. There is no database, no real upload and no money involved.
- **Nothing has been run.**

=== FILE: f06/files/api/upload.php ===
<?php
// api/upload.php — every file upload on the site (decisions/0127), sent by js/tv-upload.js; app/lib/Uploads.php does
// the work and each destination's rules are in app/lib/upload-targets.php.
//   POST JSON {do: start, target, ctx: {…}, name, size, type}  → {token, mode: s3|pieces, part, urls | pieces, max}
//   POST form do=piece, token, index, piece (a slice of the file)  when S3 isn't set up: pieces through this server
//   POST JSON {do: done, token, etags: [...], extra: {…}}      → what the destination says ({message, …})
//   POST JSON {do: abort, token}
//   POST JSON {do: status, token}                               → how far it got, to carry on from another page
//   POST JSON {do: gallery, target, ctx: {_from_item, …}, extra}  a photo or file from the Gallery: copied (or used where it
//                                                                  is) server-side, nothing sent again (Uploads::gallery)
// Signed in with the page's X-CSRF-Token, except a target open to guests (the guest photo drop), which checks its own key.
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/Uploads.php';

// if PHP stops part way (out of memory, out of time), still answer in JSON so the page says why
register_shutdown_function(function () {
    $e = error_get_last();
    if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) return;
    error_log('upload stopped: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
    if (headers_sent()) return;
    http_response_code(500); header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => ['code' => 'stopped', 'message' => str_contains($e['message'], 'memory') ? 'The server ran out of memory working on that file.' : 'The server stopped part way (' . basename((string) $e['file']) . ', line ' . (int) $e['line'] . '). Try again; if it keeps happening, tell us.']]);
});

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') Response::error('Send it as a POST.', 405);
$json = str_starts_with((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');
$in = $json ? Response::jsonBody() : $_POST;
$do = (string) ($in['do'] ?? '');
$uid = (int) (Auth::currentUserId() ?? 0);
$pdo = Database::connection();
try {
    $target = in_array($do, ['start', 'gallery'], true) ? (string) ($in['target'] ?? '') : Uploads::targetOf((string) ($in['token'] ?? ''));
    $guest = $target !== '' && Uploads::guest($target);   // open to guests: the target checks its own key (and the token is signed)
    if (!$guest) {
        if (!$uid) Response::error('Sign in first.', 401, 'unauthenticated');
        // f06: a session with no token (never made, lost, or not a string) must never match — an empty header used to pass
        $csrf = $_SESSION['csrf_token'] ?? '';
        if (!is_string($csrf) || $csrf === '' || !hash_equals($csrf, (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) Response::error('This page had expired. Reload it and try again.', 403, 'csrf');
    }
    switch ($do) {
        case 'start': Response::success(Uploads::start($pdo, $uid, $target, (array) ($in['ctx'] ?? []), (string) ($in['name'] ?? ''), (int) ($in['size'] ?? 0), (string) ($in['type'] ?? '')));
        case 'piece': Response::success(Uploads::piece($uid, (string) ($in['token'] ?? ''), (int) ($in['index'] ?? -1), $_FILES['piece'] ?? []));
        case 'done': Response::success(Uploads::finish($pdo, $uid, (string) ($in['token'] ?? ''), (array) ($in['etags'] ?? []), (array) ($in['extra'] ?? [])));
        case 'abort': Uploads::abort($uid, (string) ($in['token'] ?? '')); Response::success();
        case 'status': Response::success(Uploads::status($uid, (string) ($in['token'] ?? '')));
        case 'gallery': if ($guest) Response::error('Sign in first.', 401, 'unauthenticated');
            Response::success(Uploads::gallery($pdo, (int) $uid, $target, (array) ($in['ctx'] ?? []), (array) ($in['extra'] ?? [])));
    }
    Response::error('Unknown action.', 422, 'bad_action');
} catch (InvalidArgumentException $x) {
    Response::error($x->getMessage(), 422, 'refused', (property_exists($x, 'upgrade') && $x->upgrade ? ['upgrade' => $x->upgrade] : []) + (property_exists($x, 'feature') && $x->feature ? ['feature' => $x->feature] : []));   // feature: the upgrade sheet's (js/tv-upgrade.js)
} catch (RuntimeException $x) {
    error_log('upload: ' . $x->getMessage());
    Response::error($x->getMessage(), 409, 'error');
}
=== END FILE ===

=== FILE: f06/tests/upload-csrf-empty-token.php ===
<?php
// tools/upload-tests/upload-csrf-empty-token.php — DRAFT (f06). Runs api/upload.php in a throwaway sandbox with stand-in
// classes (no database, no real uploads, no money), one PHP process per case. Prints PASS/FAIL per check.
//   php upload-csrf-empty-token.php [path/to/api/upload.php]     default: this release's files/api/upload.php
// Run it against the live file too: the empty-token cases should FAIL there (that is the bug).
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

$base = ['uid' => 5, 'body' => ['do' => 'status', 'token' => 't1'], 'session' => ['csrf_token' => 'abc'], 'header' => 'abc'];
$cases = [
    'empty session token, no header → refused'              => [['session' => ['csrf_token' => ''], 'header' => null], 403, 'csrf'],
    'empty session token, empty header → refused'           => [['session' => ['csrf_token' => ''], 'header' => ''], 403, 'csrf'],
    'no session token at all, empty header → refused'       => [['session' => [], 'header' => ''], 403, 'csrf'],
    'empty session token, any header → refused'             => [['session' => ['csrf_token' => ''], 'header' => 'x'], 403, 'csrf'],
    'non-string session token → refused'                    => [['session' => ['csrf_token' => ['abc']], 'header' => 'Array'], 403, 'csrf'],
    'wrong header → refused'                                => [['header' => 'abd'], 403, 'csrf'],
    'matching token → allowed'                              => [[], 200, null],
    'signed out on a members target → sign in first'        => [['uid' => null, 'session' => [], 'header' => null], 401, 'unauthenticated'],
    'guest drop target, no token → still allowed (own key)' => [['uid' => null, 'target' => 'guest-drop', 'session' => [], 'header' => null], 200, null],
];
foreach ($cases as $name => [$change, $wantStatus, $wantCode]) {
    $r = run($box, array_replace($base, $change));
    $status = $r['out']['status'] ?? null;
    $code = $r['out']['error']['code'] ?? null;
    check($name, $status === $wantStatus && $code === $wantCode, $r['raw']);
}

rmTree($box);
echo $fails ? "FAIL: $fails check(s) failed\n" : "PASS: all checks\n";
exit($fails ? 1 : 0);
=== END FILE ===

=== FILE: f06/faq/faq-entries.md ===
no faq change
=== END FILE ===

=== FILE: f06/release.json ===
{
  "name": "f06-upload-csrf-empty-token",
  "gate": "lane review; tests/upload-csrf-empty-token.php prints only PASS against files/api/upload.php and shows FAIL on the empty-token cases against the live api/upload.php",
  "what": "api/upload.php line 38: the CSRF check compared the page's X-CSRF-Token with $_SESSION['csrf_token'] using hash_equals on two strings, so a signed-in session with no token (or an empty one) matched an empty or missing header and any form post was accepted. The check now refuses when the session token is missing, empty or not a string, before comparing. Guest targets (the guest photo drop) are unchanged: they still skip this check and use their own key.",
  "files": ["files/api/upload.php"],
  "decisions": [
    "Safest default implemented: a signed-in session without a usable csrf_token is refused with the existing 'This page had expired. Reload it and try again.' (403, code csrf). No token is minted on the fly in the API, because a fresh token could never match the header the page already sent.",
    "Needs a person: confirm that every page that uploads creates $_SESSION['csrf_token'] for every signed-in session, including remember-me or other auto sign-ins. If some session type never gets a token, those people will now be refused on every upload (previously they were let through without protection). The fix for that belongs where the token is made, not here."
  ],
  "tests": ["tests/upload-csrf-empty-token.php (php tests/upload-csrf-empty-token.php [path/to/api/upload.php])"],
  "risks": [
    "DRAFT: nothing here has been run, tested or verified. The lane session must run the tests and zip-check.",
    "Signed-in sessions with no csrf_token are now refused (see decisions). Before shipping, watch for a rise in 403 'csrf' answers from api/upload.php.",
    "files/api/upload.php is the whole file, cut from the attached copy. f21 and f22 also replace this whole file. Whichever of them ships after this must be re-cut on top of it, or this fix is undone. The edits don't overlap: f06 changes line 38, f21 changes line 32 and lines 52-54, f22 changes line 24.",
    "The tests use stand-in Response/Auth/Database/Uploads classes. They check this script's logic, not the real bootstrap or session handling."
  ],
  "verify": [
    "Live path: the attached header says api/upload.php, but it requires __DIR__ . '/../../app/bootstrap.php', so it sits one folder below the site root (e.g. public/api/upload.php). Place the file at the real live path.",
    "app/bootstrap.php: file not shown. Check where $_SESSION['csrf_token'] is created and that it is always a non-empty string for signed-in sessions.",
    "js/tv-upload.js: file not shown. Check that it always sends X-CSRF-Token, including on the form-post 'piece' requests.",
    "Check that the attached copy still matches live before replacing the whole file.",
    "Manual: signed in, upload a photo (works). Then clear csrf_token from the session (or start a session without one) and retry: expect 403 'This page had expired…'. Guest photo drop still works signed out."
  ],
  "regions": false
}
=== END FILE ===

=== FILE: f21/files/api/upload.php ===
<?php
// api/upload.php — every file upload on the site (decisions/0127), sent by js/tv-upload.js; app/lib/Uploads.php does
// the work and each destination's rules are in app/lib/upload-targets.php.
//   POST JSON {do: start, target, ctx: {…}, name, size, type}  → {token, mode: s3|pieces, part, urls | pieces, max}
//   POST form do=piece, token, index, piece (a slice of the file)  when S3 isn't set up: pieces through this server
//   POST JSON {do: done, token, etags: [...], extra: {…}}      → what the destination says ({message, …})
//   POST JSON {do: abort, token}
//   POST JSON {do: status, token}                               → how far it got, to carry on from another page
//   POST JSON {do: gallery, target, ctx: {_from_item, …}, extra}  a photo or file from the Gallery: copied (or used where it
//                                                                  is) server-side, nothing sent again (Uploads::gallery)
// Signed in with the page's X-CSRF-Token, except a target open to guests (the guest photo drop), which checks its own key.
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/Uploads.php';

// if PHP stops part way (out of memory, out of time), still answer in JSON so the page says why
register_shutdown_function(function () {
    $e = error_get_last();
    if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) return;
    error_log('upload stopped: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
    if (headers_sent()) return;
    http_response_code(500); header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => ['code' => 'stopped', 'message' => str_contains($e['message'], 'memory') ? 'The server ran out of memory working on that file.' : 'The server stopped part way (' . basename((string) $e['file']) . ', line ' . (int) $e['line'] . '). Try again; if it keeps happening, tell us.']]);
});

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') Response::error('Send it as a POST.', 405);
$json = str_starts_with((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');
$in = $json ? Response::jsonBody() : $_POST;
$do = (string) ($in['do'] ?? '');
$uid = (int) (Auth::currentUserId() ?? 0);
try {
    $pdo = Database::connection();   // inside the try (f21): a database that won't connect gets the plain message below, not its details
    $target = in_array($do, ['start', 'gallery'], true) ? (string) ($in['target'] ?? '') : Uploads::targetOf((string) ($in['token'] ?? ''));
    $guest = $target !== '' && Uploads::guest($target);   // open to guests: the target checks its own key (and the token is signed)
    if (!$guest) {
        if (!$uid) Response::error('Sign in first.', 401, 'unauthenticated');
        if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) Response::error('This page had expired. Reload it and try again.', 403, 'csrf');
    }
    switch ($do) {
        case 'start': Response::success(Uploads::start($pdo, $uid, $target, (array) ($in['ctx'] ?? []), (string) ($in['name'] ?? ''), (int) ($in['size'] ?? 0), (string) ($in['type'] ?? '')));
        case 'piece': Response::success(Uploads::piece($uid, (string) ($in['token'] ?? ''), (int) ($in['index'] ?? -1), $_FILES['piece'] ?? []));
        case 'done': Response::success(Uploads::finish($pdo, $uid, (string) ($in['token'] ?? ''), (array) ($in['etags'] ?? []), (array) ($in['extra'] ?? [])));
        case 'abort': Uploads::abort($uid, (string) ($in['token'] ?? '')); Response::success();
        case 'status': Response::success(Uploads::status($uid, (string) ($in['token'] ?? '')));
        case 'gallery': if ($guest) Response::error('Sign in first.', 401, 'unauthenticated');
            Response::success(Uploads::gallery($pdo, (int) $uid, $target, (array) ($in['ctx'] ?? []), (array) ($in['extra'] ?? [])));
    }
    Response::error('Unknown action.', 422, 'bad_action');
} catch (PDOException $x) {   // f21: before RuntimeException (PDOException is one); its text can name tables, users and hosts: log it, never show it
    error_log('upload db: ' . $x->getMessage() . ' in ' . $x->getFile() . ':' . $x->getLine());
    Response::error('Something went wrong saving that. Try again; if it keeps happening, tell us.', 409, 'error');
} catch (InvalidArgumentException $x) {
    Response::error($x->getMessage(), 422, 'refused', (property_exists($x, 'upgrade') && $x->upgrade ? ['upgrade' => $x->upgrade] : []) + (property_exists($x, 'feature') && $x->feature ? ['feature' => $x->feature] : []));   // feature: the upgrade sheet's (js/tv-upgrade.js)
} catch (RuntimeException $x) {
    error_log('upload: ' . $x->getMessage());
    Response::error($x->getMessage(), 409, 'error');
}
=== END FILE ===

=== FILE: f21/tests/upload-pdo-message-hidden.php ===
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
=== END FILE ===

=== FILE: f21/faq/faq-entries.md ===
no faq change
=== END FILE ===

=== FILE: f21/release.json ===
{
  "name": "f21-upload-hide-pdo-message",
  "gate": "lane review; tests/upload-pdo-message-hidden.php prints only PASS against files/api/upload.php and shows FAIL on the database cases against the live api/upload.php",
  "what": "api/upload.php lines 52-54: PDOException extends RuntimeException, so a database error was caught by the RuntimeException branch and its text (SQLSTATE, table and schema names, DB user and host) was echoed to the page. A PDOException branch now comes before it: it logs the full text and answers with a generic message. Database::connection() also moved inside the try, so a failed connection gets the same generic answer instead of crashing.",
  "files": ["files/api/upload.php"],
  "decisions": [
    "Kept HTTP 409 and error code 'error' for database failures, so js/tv-upload.js handles them exactly as before; only the message text changes. A person may prefer 500. If so, check js/tv-upload.js (file not shown) for how it treats each status before changing it.",
    "Other RuntimeExceptions keep showing their own message: Uploads.php appears to use them for visitor-facing text (e.g. an expired upload). Only PDOException is hidden."
  ],
  "tests": ["tests/upload-pdo-message-hidden.php (php tests/upload-pdo-message-hidden.php [path/to/api/upload.php])"],
  "risks": [
    "DRAFT: nothing here has been run, tested or verified. The lane session must run the tests and zip-check.",
    "If app/lib/Uploads.php catches a PDOException and re-throws it as a RuntimeException with the database text in the message, that text will still be shown. This fix cannot see that (file not shown); see verify.",
    "files/api/upload.php is the whole file, cut from the attached copy. f06 and f22 also replace this whole file. Whichever ships after another must be re-cut on top of it, or the earlier fix is undone. The edits don't overlap: f21 changes line 32 and lines 52-54, f06 changes line 38, f22 changes line 24.",
    "The tests use stand-in Response/Auth/Database/Uploads classes, not the real database code."
  ],
  "verify": [
    "Live path: the attached header says api/upload.php, but it requires __DIR__ . '/../../app/bootstrap.php', so it sits one folder below the site root (e.g. public/api/upload.php). Place the file at the real live path.",
    "app/lib/Uploads.php and app/lib/upload-targets.php: file not shown. Grep them for 'new RuntimeException' built from a caught exception's getMessage() and for messages that include SQL. Any such case needs the same treatment.",
    "app/bootstrap.php: file not shown. Check whether it sets PDO::ERRMODE_EXCEPTION (assumed) and whether Database::connection() connects eagerly or lazily.",
    "js/tv-upload.js: file not shown. Check that it shows error.message for 409 answers.",
    "Check that the attached copy still matches live before replacing the whole file.",
    "Manual on staging only: point the app at a wrong DB password and try an upload. Expect 'Something went wrong saving that…' on the page and the full error in the PHP error log."
  ],
  "regions": false
}
=== END FILE ===

=== FILE: f22/files/api/upload.php ===
<?php
// api/upload.php — every file upload on the site (decisions/0127), sent by js/tv-upload.js; app/lib/Uploads.php does
// the work and each destination's rules are in app/lib/upload-targets.php.
//   POST JSON {do: start, target, ctx: {…}, name, size, type}  → {token, mode: s3|pieces, part, urls | pieces, max}
//   POST form do=piece, token, index, piece (a slice of the file)  when S3 isn't set up: pieces through this server
//   POST JSON {do: done, token, etags: [...], extra: {…}}      → what the destination says ({message, …})
//   POST JSON {do: abort, token}
//   POST JSON {do: status, token}                               → how far it got, to carry on from another page
//   POST JSON {do: gallery, target, ctx: {_from_item, …}, extra}  a photo or file from the Gallery: copied (or used where it
//                                                                  is) server-side, nothing sent again (Uploads::gallery)
// Signed in with the page's X-CSRF-Token, except a target open to guests (the guest photo drop), which checks its own key.
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/Uploads.php';

// if PHP stops part way (out of memory, out of time), still answer in JSON so the page says why
register_shutdown_function(function () {
    $e = error_get_last();
    if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) return;
    // f22: file and line go only to the log; the visitor gets a reference that finds this log line
    $ref = substr(md5(uniqid('', true)), 0, 8);
    error_log('upload stopped [' . $ref . ']: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
    if (headers_sent()) return;
    http_response_code(500); header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => ['code' => 'stopped', 'message' => str_contains($e['message'], 'memory') ? 'The server ran out of memory working on that file.' : 'The server stopped part way. Try again; if it keeps happening, tell us (reference ' . $ref . ').']]);
});

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') Response::error('Send it as a POST.', 405);
$json = str_starts_with((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');
$in = $json ? Response::jsonBody() : $_POST;
$do = (string) ($in['do'] ?? '');
$uid = (int) (Auth::currentUserId() ?? 0);
$pdo = Database::connection();
try {
    $target = in_array($do, ['start', 'gallery'], true) ? (string) ($in['target'] ?? '') : Uploads::targetOf((string) ($in['token'] ?? ''));
    $guest = $target !== '' && Uploads::guest($target);   // open to guests: the target checks its own key (and the token is signed)
    if (!$guest) {
        if (!$uid) Response::error('Sign in first.', 401, 'unauthenticated');
        if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) Response::error('This page had expired. Reload it and try again.', 403, 'csrf');
    }
    switch ($do) {
        case 'start': Response::success(Uploads::start($pdo, $uid, $target, (array) ($in['ctx'] ?? []), (string) ($in['name'] ?? ''), (int) ($in['size'] ?? 0), (string) ($in['type'] ?? '')));
        case 'piece': Response::success(Uploads::piece($uid, (string) ($in['token'] ?? ''), (int) ($in['index'] ?? -1), $_FILES['piece'] ?? []));
        case 'done': Response::success(Uploads::finish($pdo, $uid, (string) ($in['token'] ?? ''), (array) ($in['etags'] ?? []), (array) ($in['extra'] ?? [])));
        case 'abort': Uploads::abort($uid, (string) ($in['token'] ?? '')); Response::success();
        case 'status': Response::success(Uploads::status($uid, (string) ($in['token'] ?? '')));
        case 'gallery': if ($guest) Response::error('Sign in first.', 401, 'unauthenticated');
            Response::success(Uploads::gallery($pdo, (int) $uid, $target, (array) ($in['ctx'] ?? []), (array) ($in['extra'] ?? [])));
    }
    Response::error('Unknown action.', 422, 'bad_action');
} catch (InvalidArgumentException $x) {
    Response::error($x->getMessage(), 422, 'refused', (property_exists($x, 'upgrade') && $x->upgrade ? ['upgrade' => $x->upgrade] : []) + (property_exists($x, 'feature') && $x->feature ? ['feature' => $x->feature] : []));   // feature: the upgrade sheet's (js/tv-upgrade.js)
} catch (RuntimeException $x) {
    error_log('upload: ' . $x->getMessage());
    Response::error($x->getMessage(), 409, 'error');
}
=== END FILE ===

=== FILE: f22/tests/upload-stopped-no-file-line.php ===
<?php
// tools/upload-tests/upload-stopped-no-file-line.php — DRAFT (f22). Runs api/upload.php in a throwaway sandbox with stand-in
// classes (no database, no real uploads, no money), one PHP process per case, and makes PHP stop with a fatal error.
//   php upload-stopped-no-file-line.php [path/to/api/upload.php]     default: this release's files/api/upload.php
// Against the live file the "no file or line" check should FAIL (that is the bug).
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

$base = ['uid' => 5, 'session' => ['csrf_token' => 'abc'], 'header' => 'abc', 'body' => ['do' => 'status', 'token' => 't1']];

// PHP stops with a fatal error (call to a missing function) inside the upload work
$r = run($box, $base + ['throw' => 'fatal']);
$msg = (string) ($r['out']['error']['message'] ?? '');
check('fatal: still answers in JSON with code stopped', ($r['out']['error']['code'] ?? null) === 'stopped' && $msg !== '', $r['raw']);
check('fatal: no file name or line number in the answer', !str_contains($r['raw'], '.php') && !preg_match('/\bline\b/i', $msg), $r['raw']);
check('fatal: answer carries a reference', (bool) preg_match('/reference ([0-9a-f]{8})/', $msg, $m), $msg);
$ref = $m[1] ?? 'none';
check('fatal: the log line has the same reference plus file and line', str_contains($r['log'], "[$ref]") && preg_match('/bootstrap\.php:\d+/', $r['log']) === 1, $r['log']);

// PHP runs out of memory: unchanged message, still no file/line
$r = run($box, $base + ['throw' => 'oom'], ['memory_limit' => '16M']);
check('out of memory: unchanged memory message', ($r['out']['error']['message'] ?? '') === 'The server ran out of memory working on that file.', $r['raw']);
check('out of memory: no file name in the answer', !str_contains($r['raw'], '.php'), $r['raw']);

// a normal request is untouched
$r = run($box, $base);
check('normal request still succeeds', ($r['out']['status'] ?? null) === 200