<?php
// A small site for beforeafter.mjs's tests (Piece 3, P3-1): four pages (/ public, /faq.php public, /user/account.php a
// member's own, /admin/x staff only), the member and staff sign-ins check-kit.mjs uses, and one variant at a time (the file
// named by VARIANT_FILE: "base", or a page made worse in one way). Run: VARIANT_FILE=… php -S 127.0.0.1:PORT fixture-router.php
$v = trim((string) @file_get_contents((string) getenv('VARIANT_FILE'))) ?: 'base';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$member = $_COOKIE['fx_member'] ?? ''; $staff = $_COOKIE['fx_staff'] ?? '';
$json = function ($a) { header('Content-Type: application/json'); echo json_encode($a); return true; };
if ($path === '/api/auth/session.php') return $json(['csrf_token' => 'x']);
if ($path === '/api/auth/login.php') { $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
    if (!preg_match('/^check\+([a-z-]+)@traversence\.com$/', (string) ($in['email'] ?? ''), $m)) { http_response_code(401); return $json(['ok' => false]); }
    setcookie('fx_member', $m[1], 0, '/'); return $json(['ok' => true]); }
if ($path === '/admin/login.php') {
    if (($_POST['sid'] ?? '') !== '') { setcookie('fx_sid', $_POST['sid'], 0, '/'); echo '<form method=post><input id=code name=code><button class=b>Sign in</button></form>'; return true; }
    if (($_POST['code'] ?? '') !== '') { setcookie('fx_staff', $_COOKIE['fx_sid'] ?? 'x', 0, '/'); header('Location: /admin/'); return true; }
    echo '<!doctype html><title>Sign in</title><form method=post><input id=sid name=sid><input id=pw name=pw type=password><button class=b>Next</button></form>'; return true;
}
if ($path === '/favicon.ico') { http_response_code(204); return true; }
$page = function (string $title, string $main) use ($v) {
    $wide = $v === 'scroll' ? '<div style="width:1400px">wide</div>' : '';
    $nav = $v === 'nonav' ? '' : '<a href="/">Home</a> <a href="/faq.php">FAQ</a>';
    echo '<!doctype html><html><head><meta name=viewport content="width=device-width,initial-scale=1"><title>' . $title . '</title></head><body>'
        . '<header>' . $nav . '</header><main><h1>' . $title . '</h1>' . $main . $wide . '</main></body></html>';
    return true;
};
if ($path === '/' || $path === '/index.php') {
    $n = $v === 'fewer' ? 2 : 3;
    $cards = ''; for ($i = 1; $i <= $n; $i++) $cards .= '<article class="card"><h3>Place ' . $i . '</h3><p>About place ' . $i . '.</p></article>';
    $btns = $v === 'overlap' ? '<div style="position:relative;height:40px"><button style="position:absolute;left:0;top:0;width:120px">Save</button><button style="position:absolute;left:30px;top:5px;width:120px">Share</button></div>' : '<button>Share</button>';
    $go = $v === 'noprimary' ? '' : '<button class="ak-b go">Plan a trip</button>';
    $extra = ($v === 'canary' ? '<p>Contact: check+owner@traversence.com</p>' : '') . ($v === 'canary0066' ? '<p>Zz Held Burial Canary</p>' : '') . ($v === 'neutral' ? '<p>New words on the home page.</p>' : '');
    return $page('Home', '<p>1–' . $n . ' of ' . $n . '</p>' . $cards . $btns . $go . $extra . '<p>Now: <time data-tv-time>' . microtime(true) . '</time></p>'
        . '<p class=clock>Visitors right now: ' . random_int(1, 999) . '</p>');   // moves on every load, in every variant (as a live counter does)
}
if ($path === '/faq.php') return $page('FAQ', '<p>' . ($v === 'drift' ? 'Answers, reworded.' : 'Answers.') . '</p>' . '<p>' . random_int(1, 999) . ' asked today</p>');
if ($path === '/user/account.php') {
    if ($member === '' || $v === 'lost') { header('Location: /user/login.php'); return true; }
    return $page('Your account', '<p>Signed in as check+' . htmlspecialchars($member) . '@traversence.com</p><button class="ak-b go">Save</button>');
}
if ($path === '/user/login.php') return $page('Sign in', '<p>Sign in</p>');
if (str_starts_with($path, '/admin/')) {
    if ($staff === '' && $v !== 'leak') { header('Location: /admin/login.php'); return true; }
    return $page('Admin', '<p>The admin.</p><button class="ak-b go">Save</button>' . ($v === 'greyed' ? '<button disabled>Send</button>' : '<button>Send</button>'));
}
http_response_code(404); echo 'not found'; return true;
