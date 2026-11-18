<?php
// test only (never shipped): a worker call that stores, refreshes and refuses, through the real _auth.php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../../app/lib/CrawlerIngest.php';
$body = Response::jsonBody();
CrawlerIngest::upsertMetadataKey($pdo, 501, 'zz_phone', '555');
CrawlerIngest::upsertMetadataKey($pdo, 501, 'zz_site', 'x');
CrawlerIngest::upsertMetadataKey($pdo, 502, 'zz_phone', '1');   // locked: a person's entry
ControlLedger::touched('review', 77, 'review');
ControlLedger::touched('listing', 503, 'updated: zz_hours');
ControlLedger::touched('listing', 503, 'stored');
if (!empty($body['fail'])) Response::error('Bad job.', 409, 'job_state');
Response::json(['ok' => true, 'already_known' => 2]);
