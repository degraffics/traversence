<?php
// tools/pause-tests/review-seed.php: 1,000 staged finds for Review's bench at 390 (review-390.cjs): every seventh with long
// hyphenated words and every eleventh with words that have no place to break (a name, an address, a category, a website, an email, a source), as real finds have.
// Run: php review-seed.php <database> [host] [user] [password]
declare(strict_types=1);
$pdo = new PDO('mysql:host=' . ($argv[2] ?? '127.0.0.1') . ';dbname=' . ($argv[1] ?? 'lv_pb') . ';charset=utf8mb4', $argv[3] ?? 'tv', $argv[4] ?? 'tv', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE IF NOT EXISTS crawler_raw_index (id INT AUTO_INCREMENT PRIMARY KEY, status VARCHAR(20), payload_type VARCHAR(20), raw_payload LONGTEXT, confidence_score DOUBLE DEFAULT 0.5, entity_id INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, source_url VARCHAR(500) DEFAULT '')");
$pdo->exec("CREATE TABLE IF NOT EXISTS micro_clusters (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200), geo_hub_id INT NULL, primary_zip VARCHAR(10) NULL)");
$pdo->exec('DELETE FROM crawler_raw_index');
$ins = $pdo->prepare("INSERT INTO crawler_raw_index (status, payload_type, raw_payload, source_url, confidence_score) VALUES ('staged', 'listing', ?, ?, ?)");
for ($i = 1; $i <= 1000; $i++) {
    $long = $i % 7 === 0; $junk = $i % 11 === 0;   // junk: a name, an address and a category with no place to break at all, as a crawled address or slug is
    $p = ['name' => $long ? 'Northeastern-Arizona-Regional-Behavioral-Health-And-Wellness-Collaborative-Center-Of-Excellence-' . $i : "Place $i Family Clinic", 'hold' => 1,
        'address' => $long ? '1234567890-A-Very-Long-Unbroken-Street-Name-Boulevard-Suite-1200-Building-C, Show Low, AZ 85901' : "$i W Deuce of Clubs, Show Low, AZ 85901",
        'phone' => '(928) 555-01' . str_pad((string) ($i % 100), 2, '0', STR_PAD_LEFT), 'category' => $long ? 'Behavioral-health-and-substance-use-outpatient-treatment-facility' : 'Clinic',
        'website' => 'https://www.example.org/' . ($long ? str_repeat('a-very-long-path-segment-', 6) . $i : 'p' . $i), 'description' => $long ? str_repeat('Unbrokenwordunbrokenword', 8) : 'A clinic.',
        'email' => $long ? 'a.very.long.mailbox.name.for.the.front.desk.of.the.clinic' . $i . '@northeasternarizonaregionalbehavioralhealth.example.org' : "desk$i@example.org"];
    if ($junk) { $p['name'] = 'https://www.northeasternarizonaregionalbehavioralhealthcollaborative.example.org/locations/showlow_main_campus_' . $i; $p['address'] = str_repeat('UnbrokenStreetNameWithNoSpaces', 4) . $i; $p['category'] = str_repeat('uncategorized_slug_', 5) . $i; }
    $ins->execute([json_encode($p), 'https://www.example.org/' . ($long ? str_repeat('source-path-segment-', 8) . $i : 's' . $i), ($i % 10) / 10]);
}
echo $pdo->query("SELECT COUNT(*) FROM crawler_raw_index")->fetchColumn(), " staged\n";
