<?php
// tools/tag-card-tests style. Plain PHP and does not need bootstrap or a database.
// Checks the drafted tag-card.php no longer allows public caching or a 1-hour server cache.
// Run with: php f09/tests/tag-card-cache-static-tests.php
declare(strict_types=1);

$fail = 0;
function check(string $name, bool $ok): void { global $fail; echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL; if (!$ok) $fail++; }

$api = @file_get_contents(__DIR__ . '/../files/api/tag-card.php');
check('tag-card.php readable', is_string($api));
$api = (string) $api;

check('no public Cache-Control', stripos($api, 'Cache-Control: public') === false);
check('sends private Cache-Control', stripos($api, "Cache-Control: private") !== false);
check('no 3600s server TTL literal', strpos($api, '3600') === false);
check('uses TagCardCache::key', strpos($api, 'TagCardCache::key($ref)') !== false);
check('uses TagCardCache::TTL', strpos($api, 'TagCardCache::TTL') !== false);
check('requires TagCardCache.php', strpos($api, "/../../app/lib/TagCardCache.php'") !== false);

require_once __DIR__ . '/../files/app/lib/TagCardCache.php';
check('TTL at most 300s', TagCardCache::TTL <= 300);
check('browser max-age at most 60s', TagCardCache::BROWSER_MAX_AGE <= 60);
check('key format unchanged', TagCardCache::key('entity:12') === 'tagcard:entity:12');
preg_match("#preg_match\('(/\^.*?\\$/)'#", $api, $m);
check('ref pattern matches tag-card.php', isset($m[1]) && $m[1] === TagCardCache::REF_PATTERN);

echo $fail === 0 ? "ALL PASS\n" : "$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
