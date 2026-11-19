<?php
// The board laid out after Jason's example (2026-11-18): a need's sources marked by the need's own verdict, the core
// hosts, which needs use a company. No database. Run: php layout.php <root>/app
declare(strict_types=1);
$app = $argv[1];
require_once $app . '/lib/Database.php'; require $app . '/lib/ControlBoard.php'; require $app . '/lib/Preflight.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES) : '') . "\n"; } };
$res = [
    'nd:bulk' => ['s' => 'warn', 'said' => 'Running on a backup (source 2). 1. Ours (our open-data copies): about 41,200 rows, newest 52 days old (stale: over 45 days); 2. Overture Maps: works; 3. Foursquare: not tested yet; 4. Geofabrik: works.', 'at' => 5],
    'nd:nonprofits' => ['s' => 'bad', 'said' => 'No source works, so the last load is kept. 1. Ours (our IRS copy): about 900 rows, newest today; 2. IRS (exempt organizations): not ready.', 'at' => 5],
    'nd:health' => ['s' => 'ok', 'said' => 'Answered by its first source. 1. Ours (our NPI copy): about 9,800 rows, newest 12 days old; 2. CMS: works.', 'at' => 5],
    'nd:hosting' => ['s' => 'ok', 'said' => 'Answered by its first source. No backup yet (planned: a read-only copy). 1. Bluehost: the site and its databases: works.', 'at' => 5],
    'svc:bluehost' => ['s' => 'ok'], 'svc:railway' => ['s' => 'bad'],
];
$n = ControlBoard::needs($res, ['bulk', 'nonprofits', 'health', 'hosting', 'places']);
$t('on a backup: the sources before it marked ✗, the one it names ✓', $n['bulk'][1] === 'warn' && $n['bulk'][2][0][2] === false && $n['bulk'][2][1][2] === true && $n['bulk'][2][2][2] === false && $n['bulk'][2][3][2] === true, $n['bulk'][2]);
$t('red: every source marked ✗, whatever its words', !array_filter(array_column($n['nonprofits'][2], 2)), $n['nonprofits'][2]);
$t('answered first: source 1 ✓, its name read whole', $n['health'][2][0] === ['Ours (our NPI copy)', 'about 9,800 rows, newest 12 days old', true] && $n['health'][2][1][2] === true, $n['health'][2]);
$t('a source name with a colon in its words, after "No backup yet"', count($n['hosting'][2]) === 1 && $n['hosting'][2][0][2] === true && $n['hosting'][0] === 'Hosting', $n['hosting']);
$t('not tested: its sources from the map, marked unknown', $n['places'][1] === 'new' && $n['places'][2][0][2] === null && count($n['places'][2]) === 2, $n['places']);   // our copy, then Overture (Overpass retired 2026-11-19)
$t('the need\'s fallback and soft flag carried', $n['nonprofits'][3] === 'the last load is kept' && $n['nonprofits'][4] === false);
$c = ControlBoard::core($res);
$t('core hosts: Bluehost, Railway, GitHub with their states', array_keys($c) === ['bluehost', 'railway', 'github'] && $c['railway'][1] === 'bad' && $c['github'][1] === 'new', $c);
$u = ControlBoard::usedBy('gemini');
$t('used by: Gemini feeds prose and semantic search', in_array('Page prose into facts (W1–W3, W10, W11)', $u, true) && in_array('Semantic search (W3)', $u, true), $u);
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
