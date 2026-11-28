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
