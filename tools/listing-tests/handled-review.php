<?php
// A review item that's no longer waiting is never a dead end (Jason's screenshot, 2026-11-20: a board link to "Review item
// #26906" opened only "This listing has already been handled" and Back to Review): Review's one-row lookup says what
// happened (published, merged or rejected), who did it and when, and the listing it became, and the page shows it with a
// link. Run: mariadb < ../release-b-tests/start-schema.sql && php handled-review.php <root>  (root holds app/ and website/)
declare(strict_types=1);
$root = rtrim($argv[1], '/');
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(is_string($got) ? $got : json_encode($got), 0, 400) : '') . "\n"; } };
require_once $root . '/app/lib/Database.php'; $pdo = Database::connection();
$pdo->exec('DROP TABLE IF EXISTS crawler_raw_index'); $pdo->exec('CREATE TABLE IF NOT EXISTS crawler_sources (id INT PRIMARY KEY, url VARCHAR(255), purposes VARCHAR(255), rationale TEXT)');
$pdo->exec("CREATE TABLE crawler_raw_index (id INT PRIMARY KEY, source_id INT NULL, source_url VARCHAR(255) NULL, payload_type VARCHAR(20) DEFAULT 'listing', raw_payload TEXT, reviewed_payload TEXT NULL, confidence_score INT DEFAULT 0, status VARCHAR(20), matched_entity_id INT NULL, entity_id INT NULL, review_note VARCHAR(255) NULL, reviewed_at DATETIME NULL, reviewed_by_user_id INT NULL, created_at DATETIME NULL)");
try { $pdo->exec("INSERT INTO users (id, display_name) VALUES (77, 'Jason D')"); } catch (Throwable $e) {}
$pdo->exec("INSERT INTO crawler_raw_index (id, raw_payload, status, entity_id, reviewed_at, reviewed_by_user_id) VALUES (26906, '{}', 'published', 16544, '2026-11-20 08:25:00', 77), (26907, '{}', 'rejected', NULL, '2026-11-20 08:30:00', NULL)");
$pdo->exec("UPDATE crawler_raw_index SET review_note = 'Not a business: a parking lot' WHERE id = 26907");

// the endpoint, with its guard stood in for (the test is about the answer, not the sign-in)
$dir = sys_get_temp_dir() . '/hr' . getmypid(); @mkdir($dir);
file_put_contents($dir . '/_common.php', "<?php require_once '$root/app/lib/Database.php'; require_once '$root/app/lib/crawler/IntakeStager.php';\nfunction intake_json(array \$d, int \$c = 200): void { echo json_encode(\$d); exit; }\n");
copy($root . '/website/api/admin/intake/candidates.php', $dir . '/candidates.php');
$ask = fn (int $id) => json_decode((string) shell_exec('cd ' . escapeshellarg($dir) . ' && php -r ' . escapeshellarg('$_GET = ["status" => "staged", "id" => "' . $id . '"]; $_SESSION = []; include "candidates.php";')), true) ?: [];
$a = $ask(26906);
$t('a handled row: no rows, and its outcome', ($a['rows'] ?? null) === [] && ($a['outcome']['status'] ?? '') === 'published' && ($a['outcome']['entity_id'] ?? 0) === 16544, $a);
$t('…who handled it and when', ($a['outcome']['by'] ?? '') === 'Jason D' && str_starts_with((string) ($a['outcome']['at'] ?? ''), '2026-11-20T08:25:00'), $a['outcome'] ?? null);
$b = $ask(26907);
$t('a rejected row: its note, and no listing to link', ($b['outcome']['status'] ?? '') === 'rejected' && ($b['outcome']['note'] ?? '') === 'Not a business: a parking lot' && array_key_exists('entity_id', $b['outcome'] ?? []) && $b['outcome']['entity_id'] === null, $b);

// the page's words, run as the page runs them
$js = (string) file_get_contents($root . '/website/admin/listing-intake.php');
preg_match('/function esc\(s\) \{.*?\n/s', $js, $e1); preg_match('/  function handledWords\(o\) \{.*?\n  \}\n/s', $js, $e2);
$node = ($e1[0] ?? '') . ($e2[0] ?? '') . 'console.log(JSON.stringify([handledWords(' . json_encode($a['outcome'] ?? null) . '), handledWords(' . json_encode($b['outcome'] ?? null) . '), handledWords(null)]));';
$w = json_decode((string) shell_exec('node -e ' . escapeshellarg($node)), true) ?: ['', '', ''];
$t('the page says Published, by whom, when, with a link to the listing', str_contains($w[0], '<b>Published</b> by Jason D') && str_contains($w[0], 'datetime="2026-11-20T08:25:00') && str_contains($w[0], 'href="/admin/listing-edit.php?id=16544">Open listing #16544</a>'), $w[0]);
$t('…a rejected one says so, with its note', str_contains($w[1], '<b>Rejected</b> by the system') && str_contains($w[1], 'Note: Not a business: a parking lot.'), $w[1]);
$t('…and with no outcome to read, the old words stay', $w[2] === 'This listing has already been handled.', $w[2]);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
