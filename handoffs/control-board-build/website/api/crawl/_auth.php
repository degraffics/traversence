<?php
// api/crawl/_auth.php — shared by the off-site crawl worker endpoints (decisions/0044).
// Bearer token = CRAWLER_API_TOKEN from .env, the same token api/ingest.php uses for off-site workers.
// Fails closed when the token isn't configured.

declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/lib/crawler/CrawlQueue.php';

// The site reads .env when it first connects to the database, so connect before looking for the token.
$pdo = Database::connection();
$expected = getenv('CRAWLER_API_TOKEN') ?: '';
if ($expected === '') {
    error_log('CRAWLER_API_TOKEN is not configured — refusing crawl worker requests.');
    Response::error('Crawl worker access is not configured on this server.', 503, 'not_configured');
}
// Shared hosting often strips the Authorization header before PHP sees it, so X-Crawler-Token works too.
$given = preg_match('/^Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '', $m) ? $m[1]
       : (string) ($_SERVER['HTTP_X_CRAWLER_TOKEN'] ?? '');
if ($given === '' || !hash_equals($expected, $given)) {
    Response::error('Invalid or missing crawler API token.', 401, 'unauthenticated');
}
// the one engine's check of this endpoint (the control board's build: an endpoint of ours is a part, its check an
// authenticated no-work request inside its speed gate; ControlEngine::checkEndpoint): it answers once signed in and the
// database answers, before the ledger measures anything, so a check never counts as one of its calls
if (($_GET['check'] ?? '') === '1') { $pdo->query('SELECT 1'); Response::json(['ok' => true, 'check' => 'answered', 'endpoint' => basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''), '.php')]); }
// the System Control Board's ledger (decisions/0171 §4): this call's row, written after it answers
require_once __DIR__ . '/../../../app/lib/ControlLedger.php';
ControlLedger::begin($pdo, 'crawl/' . basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? 'unknown'), '.php'));
if (!CrawlQueue::tableExists($pdo)) {
    Response::error('Run migrations/2026-09-29_crawl_jobs_and_page_cache.sql first.', 503, 'not_configured');
}
