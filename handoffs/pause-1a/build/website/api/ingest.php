<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/CrawlerIngest.php';
require_once __DIR__ . '/../../app/lib/crawler/IntakeStager.php';

Response::requireMethod('POST');

// --- Bearer token auth (offsite crawler workers, not a user session) ---
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$expectedToken = getenv('CRAWLER_API_TOKEN') ?: '';

if ($expectedToken === '') {
    // Fail closed: a misconfigured server (no token set) must not accept
    // ingestion requests from anyone.
    error_log('CRAWLER_API_TOKEN is not configured — refusing all ingest requests.');
    Response::error('Ingestion is not configured on this server.', 503, 'not_configured');
}

if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches) || !hash_equals($expectedToken, $matches[1])) {
    Response::error('Invalid or missing crawler API token.', 401, 'unauthenticated');
}

$body = Response::jsonBody();

// Accepts either a single record object or a JSON array of records.
$isBatch = is_array($body) && $body !== [] && array_keys($body) === range(0, count($body) - 1) && isset($body[0]) && is_array($body[0]);
$records = $isBatch ? $body : [$body];

if (empty($records)) {
    Response::error('Request body must contain a listing object or an array of listing objects.', 422, 'validation_error');
}

// The pilot operates within a single geo-hub. See the doc-comment on
// CrawlerIngest::resolveMicroClusterId() for the known limitation this
// creates once a second hub/geo-hub goes live.
$defaultGeoHubSlug = getenv('DEFAULT_GEO_HUB_SLUG') ?: 'ancient-borderlands';

$pdo = Database::connection();
$geoHubStmt = $pdo->prepare('SELECT id FROM geo_hubs WHERE slug = :slug LIMIT 1');
$geoHubStmt->execute(['slug' => $defaultGeoHubSlug]);
$defaultGeoHubId = $geoHubStmt->fetchColumn();

if ($defaultGeoHubId === false) {
    error_log("DEFAULT_GEO_HUB_SLUG '{$defaultGeoHubSlug}' does not exist in geo_hubs.");
    Response::error('Default geo-hub is not configured correctly on this server.', 503, 'not_configured');
}
$defaultGeoHubId = (int) $defaultGeoHubId;

// the pause (Jason, 2026-11-28, "Pause it. Yes"; the CEO: no caller fails, retries or loses a find): this endpoint makes
// listings with no gate of its own, so while the pause holds it stages each record for Review, unpublished, like every
// other path, and answers success with "held for review"
require_once __DIR__ . '/../../app/lib/crawler/AutoImport.php';
if (($paused = AutoImport::pausedWhy()) !== null) {
    require_once __DIR__ . '/../../app/lib/crawler/CrawlQueue.php';
    $cands = [];
    foreach ($records as $record) {
        if (!is_array($record)) continue;
        $c = CrawlQueue::normalize(['name' => $record['name'] ?? '', 'source' => ['site' => 'api/ingest.php'],
            'where' => ['address' => $record['address'] ?? '', 'city' => $record['city'] ?? '', 'state' => $record['state'] ?? '', 'zip' => CrawlerIngest::normalizeZip($record['zip'] ?? '')],
            'actions' => ['phone' => $record['phone'] ?? '', 'website' => $record['website'] ?? ''], 'what' => ['description' => $record['description'] ?? '']]);
        if ($c) { $c['hold'] = 'api/ingest.php while paused'; $cands[] = $c; }
    }
    $held = 0;
    if ($cands) {
        $sid = IntakeStager::createSource($pdo, 'https://traversence.com/api/ingest.php', ['create_listings'], 'api/ingest.php while paused: held for Review', 0);
        $held = count((IntakeStager::stage($pdo, $sid, $cands))['staged'] ?? []);
    }
    Response::success(['processed' => count($records), 'status' => 'held for review', 'held_for_review' => $held, 'why' => $paused], 200);
}

$results = [];
$hasErrors = false;

foreach ($records as $i => $record) {
    if (!is_array($record)) {
        $results[] = ['index' => $i, 'status' => 'error', 'message' => 'Record is not a valid object.'];
        $hasErrors = true;
        continue;
    }

    try {
        // Each record is its own transaction — one bad record in a batch
        // must not roll back everything else that succeeded.
        // the one identity check (decisions/0164 §3.1): inside the identity lock, so two twins in one batch (or two
        // workers) can't both be made; each record sees the ones before it
        require_once __DIR__ . '/../../app/lib/ListingIdentity.php';
        $result = ListingIdentity::locked(Database::connection(), CrawlerIngest::normalizeZip($record['zip'] ?? '00000'), fn () => Database::transaction(
            fn (PDO $pdo) => CrawlerIngest::upsertEntity($pdo, $record, $defaultGeoHubId, true)
        ));
        $results[] = [
            'index'          => $i,
            'status'         => $result['action'], // 'created' | 'updated' | 'matched' (the identity check found it listed)
            'entity_id'      => $result['entity_id'],
            'composite_hash' => $result['composite_hash'],
        ];
    } catch (InvalidArgumentException $e) {
        $results[] = ['index' => $i, 'status' => 'error', 'message' => $e->getMessage()];
        $hasErrors = true;
    } catch (Throwable $e) {
        error_log('Ingest error on record ' . $i . ': ' . $e->getMessage());
        $results[] = ['index' => $i, 'status' => 'error', 'message' => 'Internal error processing this record.'];
        $hasErrors = true;
    }
}

Response::success([
    'processed' => count($records),
    'results'   => $results,
], $hasErrors ? 207 : 200); // 207 Multi-Status when a batch had partial failures
