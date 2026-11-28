<?php
// /api/tag-card.php?ref=entity:12 — what a highlighted name in a piece of content opens as its preview card (decisions/0132
// §2a): the name, what it is, where, a line about it and its page. Public facts only; nothing for a hidden, held or
// unknown thing. Cached briefly on the server (TagCardCache::TTL) and privately in the browser only, so a hide or hold
// stops showing within minutes even before TagCardCache::forget() is wired into the hide/hold actions (f09).
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/ContentTags.php';
require_once __DIR__ . '/../../app/lib/PageCache.php';
require_once __DIR__ . '/../../app/lib/TagCardCache.php';

$ref = trim((string) ($_GET['ref'] ?? ''));
if (!preg_match('/^(entity:\d+|landmark:\d+|rec:[A-Za-z0-9_-]{1,40}|(hub|geo_hub|cluster|town|sub):\d+)$/', $ref)) Response::error('Unknown.', 404, 'not_found');
try {
    $pdo = Database::connection();
    $c = PageCache::remember($pdo, TagCardCache::key($ref), TagCardCache::TTL, fn () => ContentTags::card($pdo, $ref) ?? ['none' => 1]);
    if (!is_array($c) || !empty($c['none'])) Response::error('Unknown.', 404, 'not_found');
    header('Cache-Control: private, max-age=' . TagCardCache::BROWSER_MAX_AGE);
    Response::json(['ok' => true, 'card' => $c]);
} catch (Throwable $e) {
    error_log('tag-card: ' . $e->getMessage());
    Response::error('Unavailable.', 503, 'unavailable');
}
