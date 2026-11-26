<?php
// /api/held-hashes.php — the names the site holds back (decisions/0066), as keyed hashes only, for the before-and-after
// check (Piece 3, P3-5; Jason, 2026-11-25, Brief B). Staging only: on the live site it answers nothing. Locked like the
// health report: the checks' token (X-Check-Token, or Authorization: Bearer <HEALTH_TOKEN>); off while HEALTH_TOKEN isn't
// set. Read only, one table read, no outside call. Never a name or a place (HeldNames).
//   GET /api/held-hashes.php -> {ok, at, count, skipped_one_word, hashes: [24-hex HMAC-SHA256 of each reduced name]}
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/Staging.php';
header('Cache-Control: no-store');

if (!Staging::here()) Response::error('Not here: this read is on staging only.', 404, 'staging_only');
Database::readEnv();
$want = (string) (getenv('HEALTH_TOKEN') ?: '');
if ($want === '') Response::error('The held names\' read is off (no HEALTH_TOKEN).', 503, 'off');
$got = (string) ($_SERVER['HTTP_X_CHECK_TOKEN'] ?? '');
if ($got === '' && preg_match('/^Bearer\s+(\S+)$/i', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''), $m)) $got = $m[1];   // staging's viewing password uses Authorization: Basic, so the header comes first
if (!hash_equals($want, $got)) Response::error('Not allowed.', 401, 'not_allowed');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') Response::error('GET only.', 405, 'method');

require_once __DIR__ . '/../../app/lib/HeldNames.php';
try {
    Response::json(['ok' => true, 'at' => gmdate('c')] + HeldNames::hashes(Database::connection(), $want));
} catch (Throwable $x) {
    error_log('held-hashes: ' . $x->getMessage());
    Response::error('The held names couldn\'t be read.', 500, 'read');
}
