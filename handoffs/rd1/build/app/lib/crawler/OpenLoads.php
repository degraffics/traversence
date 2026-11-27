<?php
// app/lib/crawler/OpenLoads.php — The open-dataset loads for Arizona and New Mexico (decisions/0164 §1.5, §3.5; 0163 §8
// stage 8, §12's lead order): Overture Maps Places as the base layer, Foursquare's open places where Overture doesn't
// already carry its record, and Geofabrik's state files for OpenStreetMap (the first step: one test download from the
// Railway worker). The worker reads each town's box (DuckDB over the public files) and sends the records here; this side:
//   - keeps what the licence allows: name, street, phone, website, category, the record's id, its sources and licences,
//     with credit; never emails (decisions/0164 §1.5), never a closed record as a listing (a closure signal instead)
//   - folds the batch (ListingIdentity::fold), drops what's already listed (the one identity check), and stages the rest
//     through the crawler's one pipeline, so the floor (§3.5), readiness and the identity check decide each one
//   - runs the identity recheck at once on the listings the load touched (0163 §12: "the recheck after a load"), straight
//     after the batch's reply (IR-0001, 2026-11-24: 500 rows took 101–130 s against the worker's 120 s read)
//   - each batch carries its number: one taken again answers what it did and does nothing (open_load_batches)
// Towns go in the launch area's order first (R-D1; decisions/0181 §3, D.1 §2.2–2.4 of
// research/launch-area-nations-and-towns-2026-11-27.md): 1A's towns, then every town within 200 miles of the Four Corners
// Monument by its distance from it, each made one of our towns (an anchor, OnDemand::townFor) and loaded as `town:<id>`.
// After the area, the order of our coverage gap: the fewest listings we have first (Kayenta has 0; Run 2).
// Shipped off: each load is a W1 task added disabled, its service paused (Operations::ADDED_OFF), under the master switch.
declare(strict_types=1);

require_once __DIR__ . '/../Database.php';

final class OpenLoads
{
    public const DATASETS = [
        'overture' => ['Overture Maps Places', 'overture_bulk', 'https://docs.overturemaps.org/attribution/'],
        'foursquare' => ['Foursquare open places', 'foursquare_bulk', 'https://huggingface.co/datasets/foursquare/fsq-os-places'],
    ];
    public const STATES = ['AZ', 'NM'];
    /** A town's box around its middle, in degrees (about 7 km each way). */
    private const BOX = [0.065, 0.08];

    public static function ready(PDO $pdo): bool
    {
        try { $pdo->query('SELECT 1 FROM open_loads LIMIT 0'); return true; } catch (Throwable) { return false; }
    }

    /** The load tasks on the switches (W1), added off; site-side bookkeeping, the worker does the reading. */
    public static function ensureTasks(PDO $pdo): void
    {
        require_once __DIR__ . '/../ListingIdentity.php';
        foreach (self::DATASETS as $ds => [$name, $task]) ListingIdentity::ensureTask($pdo, $task, 'W1', 'bulk', $name . ': load by town', 'A town\'s records from ' . $name . ' through the one identity check (decisions/0164 §1.5).', 3, 1440);
        ListingIdentity::ensureTask($pdo, 'geofabrik_bulk', 'W1', 'bulk', 'Geofabrik: OpenStreetMap by state', 'The OpenStreetMap files of Arizona, New Mexico, Utah, Colorado and Nevada, weekly (its first run is one test download).', 1, 10080);
    }

    /** Why a dataset's load may not run now, or null. */
    public static function off(PDO $pdo, string $ds): ?string
    {
        require_once __DIR__ . '/../Automation.php';
        if (($w = Automation::masterOff($pdo)) !== null) return $w;
        if (($w = Automation::wfOff($pdo, 'W1')) !== null) return $w;
        if (($w = Automation::serviceOff($pdo, $ds)) !== null) return $w;
        $task = self::DATASETS[$ds][1] ?? ($ds === 'geofabrik' ? 'geofabrik_bulk' : '');
        try { $st = $pdo->prepare('SELECT enabled FROM crawl_capture_tasks WHERE task = ?'); $st->execute([$task]); if (!(int) $st->fetchColumn()) return 'Its load task (' . $task . ', W1) is switched off.'; } catch (Throwable) { return 'The task list isn\'t there yet.'; }
        if (($w = Automation::sysAny($pdo, ['master:all', 'wf:W1', 'service:' . $ds, 'task:' . $task])) !== null) return $w;   // paused by the system: a trip (decisions/0175)
        return null;
    }

    /** The launch area (decisions/0181 §3): every town within this many miles of the Four Corners Monument. */
    public const AREA_CENTER = [36.999, -109.045];
    public const AREA_MILES = 200;
    /**
     * 1A's towns are regions.md's 1A row (St. Johns, Springerville, Eagar, Show Low, Holbrook, Winslow, the Petrified Forest
     * gateway); the data carries it as a cluster slug starting `cluster-1a-`. One 1A cluster still has its old slug until
     * W15 r2 renames it (Springerville & Eagar, "cluster-1b-round-valley"): named here only until then, and matched only
     * while no `cluster-1a-` slug holds its ZIP (tools/crawler-tests/rd1.php fails once r2's slug is live, so it's removed).
     */
    public const SLUG_1A = 'cluster-1a-';
    public const R2_PENDING = ['cluster-1b-round-valley'];

    private static function miles(float $a, float $b, float $c, float $d): float
    {
        $r = M_PI / 180; $x = sin(($c - $a) * $r / 2) ** 2 + cos($a * $r) * cos($c * $r) * sin(($d - $b) * $r / 2) ** 2;
        return 2 * 3958.8 * asin(min(1, sqrt($x)));
    }

    /** The ZIPs of 1A's clusters (their primary and associated ZIPs). */
    public static function zips1A(PDO $pdo): array
    {
        $out = []; $slugs = [];
        $rows = $pdo->query("SELECT slug, primary_zip, associated_zips FROM micro_clusters")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) $slugs[(string) $r['slug']] = $r;
        foreach ($rows as $r) {
            $slug = (string) $r['slug'];
            $is = str_starts_with($slug, self::SLUG_1A);
            if (!$is && in_array($slug, self::R2_PENDING, true)) $is = !isset($slugs[self::SLUG_1A . preg_replace('/^cluster-\d+[a-z]-/', '', $slug)]);   // until r2's renamed slug is there
            if (!$is) continue;
            $z = array_merge([(string) $r['primary_zip']], array_map('strval', (array) (json_decode((string) $r['associated_zips'], true) ?: [])));
            foreach ($z as $x) if (preg_match('/^\d{5}$/', $x)) $out[$x] = true;
        }
        return array_map('strval', array_keys($out));   // PHP keeps a ZIP key as an int
    }

    /**
     * The launch area's towns in their load order: [{city, state, zips, lat, lon, miles, is1a}], 1A's first, then by miles
     * from the Four Corners Monument. A town is in the area when one of its ZIPs is within AREA_MILES; 1A's towns are in
     * whatever their distance (Eagar's middle is 200.3 mi). Only the states we load (STATES: Colorado and Utah wait for
     * their nations' boundaries, G1).
     */
    public static function area(PDO $pdo): array
    {
        $one = array_flip(self::zips1A($pdo));
        $in = implode(',', array_map(fn ($s) => $pdo->quote($s), self::STATES));
        $rows = $pdo->query("SELECT zip, city, state, latitude, longitude FROM zip_coordinates WHERE state IN ($in) AND latitude IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        $t = [];
        foreach ($rows as $r) {
            $mi = self::miles(self::AREA_CENTER[0], self::AREA_CENTER[1], (float) $r['latitude'], (float) $r['longitude']);
            $is1a = isset($one[(string) $r['zip']]);
            if ($mi > self::AREA_MILES && !$is1a) continue;
            $k = strtoupper((string) $r['state']) . '|' . (string) $r['city'];
            $t[$k] ??= ['city' => (string) $r['city'], 'state' => strtoupper((string) $r['state']), 'zips' => [], 'pts' => [], 'miles' => $mi, 'is1a' => false];
            $t[$k]['zips'][] = (string) $r['zip']; $t[$k]['pts'][] = [(float) $r['latitude'], (float) $r['longitude']];
            $t[$k]['miles'] = min($t[$k]['miles'], $mi); $t[$k]['is1a'] = $t[$k]['is1a'] || $is1a;
        }
        foreach ($t as &$x) { $n = count($x['pts']); $x['lat'] = array_sum(array_column($x['pts'], 0)) / $n; $x['lon'] = array_sum(array_column($x['pts'], 1)) / $n; unset($x['pts']); }
        unset($x);
        $t = array_values($t);
        usort($t, fn ($a, $b) => [$a['is1a'] ? 0 : 1, $a['miles'], $a['city']] <=> [$b['is1a'] ? 0 : 1, $b['miles'], $b['city']]);
        return $t;
    }

    /** A town box around its middle: [x0, y0, x1, y1]. */
    private static function box(float $la, float $lo): array
    {
        return [round($lo - self::BOX[1], 4), round($la - self::BOX[0], 4), round($lo + self::BOX[1], 4), round($la + self::BOX[0], 4)];
    }

    /**
     * The next towns due for a dataset's release: the launch area's first (each made our town, `town:<id>`), then the
     * biggest gap first among our clusters: [{key, name, state, zip, listed, box: [x0, y0, x1, y1]}].
     */
    public static function due(PDO $pdo, string $ds, string $release, int $n = 3): array
    {
        $out = [];
        $done = $pdo->prepare('SELECT 1 FROM open_loads WHERE dataset = ? AND place_key = ? AND release_id = ? AND finished_at IS NOT NULL');
        $isDone = function (string $key) use ($done, $ds, $release): bool { $done->execute([$ds, $key, $release]); return (bool) $done->fetchColumn(); };
        if ($n <= 0) return $out;
        require_once __DIR__ . '/../OnDemand.php';
        $listed = $pdo->prepare('SELECT COUNT(*) FROM entities WHERE primary_zip = ? AND is_suppressed = 0');
        foreach (self::area($pdo) as $tw) {   // the launch area first (R-D1)
            $key = OnDemand::townFor($pdo, $tw['city'], $tw['state']);
            if ($key === null || $isDone($key)) continue;
            $listed->execute([$tw['zips'][0]]);
            $out[] = ['key' => $key, 'name' => $tw['city'], 'state' => $tw['state'], 'zip' => $tw['zips'][0], 'listed' => (int) $listed->fetchColumn(), 'box' => self::box($tw['lat'], $tw['lon'])];
            if (count($out) >= $n) return $out;
        }
        $rows = $pdo->query("SELECT mc.id, mc.name, mc.primary_zip, z.latitude, z.longitude, z.state,
                                    (SELECT COUNT(*) FROM entities e WHERE e.primary_zip = mc.primary_zip AND e.is_suppressed = 0) AS listed
                               FROM micro_clusters mc JOIN zip_coordinates z ON z.zip = mc.primary_zip
                              ORDER BY listed, mc.id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {   // then, as before: our clusters, the fewest listings first
            if (!in_array(strtoupper((string) ($r['state'] ?? '')), self::STATES, true)) continue;
            $key = 'cluster:' . (int) $r['id'];
            if ($isDone($key)) continue;
            $out[] = ['key' => $key, 'name' => (string) $r['name'], 'state' => strtoupper((string) $r['state']), 'zip' => (string) $r['primary_zip'], 'listed' => (int) $r['listed'],
                      'box' => self::box((float) $r['latitude'], (float) $r['longitude'])];
            if (count($out) >= $n) break;
        }
        return $out;
    }

    /**
     * One town's records from the worker. $rows: [{id, name, street, city, state, zip, phone, website, category, confidence,
     * lat, lng, status, sources: [[dataset, licence]]}]. Returns ['seen', 'staged', 'listed', 'closed', 'skipped'].
     */
    public static function batch(PDO $pdo, string $ds, string $release, string $key, array $rows, ?int $no = null, ?array &$recheck = null): array
    {
        $out = ['seen' => 0, 'staged' => 0, 'listed' => 0, 'closed' => 0, 'skipped' => 0];
        if (!isset(self::DATASETS[$ds])) throw new InvalidArgumentException('Unknown dataset.');
        // a batch already taken answers what it did then, and does nothing (IR-0001: a reply that reached the worker too late
        // is sent again; resent, it must not stage or count twice)
        if ($no !== null && ($was = self::batchDone($pdo, $ds, $release, $key, $no)) !== null) return $was + ['again' => true];
        require_once __DIR__ . '/../ListingIdentity.php'; require_once __DIR__ . '/../DataCleanup.php'; require_once __DIR__ . '/IntakeStager.php'; require_once __DIR__ . '/CrawlQueue.php';
        $cands = [];
        foreach (array_slice($rows, 0, 2000) as $r) {
            $out['seen']++;
            $name = trim((string) ($r['name'] ?? ''));
            if ($name === '' || DataCleanup::junkName($name)[0]) { $out['skipped']++; continue; }
            $state = strtoupper(substr((string) ($r['state'] ?? ''), 0, 2));
            if ($state !== '' && !in_array($state, self::STATES, true)) { $out['skipped']++; continue; }
            $phone = ListingIdentity::phoneKey((string) ($r['phone'] ?? ''));
            $street = trim((string) ($r['street'] ?? ''));
            $closed = in_array(strtolower((string) ($r['status'] ?? '')), ['permanently_closed', 'closed'], true) || trim((string) ($r['date_closed'] ?? '')) !== '';
            if ($closed) {   // a closure signal against a listing we have (−40, 0163 §12), never a new listing
                $m = ListingIdentity::resolve($pdo, ['name' => $name, 'address' => $street, 'zip' => (string) ($r['zip'] ?? ''), 'phone' => $phone]);
                if ($m['kind'] === 'same') { self::closureSignal($pdo, $m['id'], $ds); $out['closed']++; } else $out['skipped']++;
                continue;
            }
            $lic = []; foreach ((array) ($r['sources'] ?? []) as $s) { $s = (array) $s; $lic[] = trim((string) ($s[0] ?? '')) . ':' . trim((string) ($s[1] ?? '')); }
            $url = $ds === 'overture' ? 'https://explore.overturemaps.org/#/place/' . rawurlencode((string) ($r['id'] ?? '')) : 'https://foursquare.com/v/' . rawurlencode((string) ($r['id'] ?? ''));
            $conf = (float) ($r['confidence'] ?? 0.5);
            $c = CrawlQueue::normalize([
                'name' => $name,
                'source' => ['start_url' => $url, 'site' => $ds === 'overture' ? 'overturemaps.org' : 'foursquare.com', 'kind' => $ds === 'overture' ? 'overture' : 'foursquare_os', 'record' => (string) ($r['id'] ?? ''),
                             'release' => $release, 'licences' => array_values(array_unique($lic)), 'credit' => self::DATASETS[$ds][0], 'crawled_at' => gmdate('Y-m-d\TH:i:s\Z')],
                'where' => ['address' => $street, 'city' => (string) ($r['city'] ?? ''), 'state' => $state, 'zip' => substr(preg_replace('/\D/', '', (string) ($r['zip'] ?? '')), 0, 5)],
                'what' => ['offerings' => array_values(array_filter([(string) ($r['category'] ?? '')])), 'description' => ''],
                'when' => ['hours' => []],
                'actions' => ['phone' => $phone !== '' ? substr($phone, 0, 3) . '-' . substr($phone, 3, 3) . '-' . substr($phone, 6) : '', 'website' => (string) ($r['website'] ?? '')],
                'sources' => array_filter(['name' => [$url], 'address' => $street !== '' ? [$url] : [], 'phone' => $phone !== '' ? [$url] : []]),
                'corroboration' => [], 'confidence' => (int) round(30 + 30 * min(1, $conf)),   // under 0.5 Overture confidence counts half (0163 §12)
            ]);
            if (!$c) { $out['skipped']++; continue; }
            // kept after the intake's shape (CrawlQueue::normalize keeps only its own keys): the dataset's own point, its record
            // id, release, licences and credit (R9: each record's licence kept with it)
            $c['source'] += ['kind' => '', 'record' => (string) ($r['id'] ?? ''), 'release' => $release, 'licences' => array_values(array_unique($lic)), 'credit' => self::DATASETS[$ds][0]];
            $c['source']['kind'] = $ds === 'overture' ? 'overture' : 'foursquare_os';   // AutoImport::OPEN_DATASETS: a source we may keep
            if (is_numeric($r['lat'] ?? null) && is_numeric($r['lng'] ?? null)) $c['where']['point'] = ['lat' => (float) $r['lat'], 'lon' => (float) $r['lng']];
            $cands[] = $c;
        }
        $cands = ListingIdentity::fold($cands);
        $new = [];
        foreach ($cands as $c) { $m = ListingIdentity::resolve($pdo, $c); if (in_array($m['kind'], ['same', 'covered'], true)) { $out['listed']++; $touched[] = $m['id']; } $new[] = $c; }   // listed ones still stage: their source joins the listing's identity (R11)
        $touched = $touched ?? [];
        if ($new) {
            $src = self::sourceId($pdo, $ds);
            $r = IntakeStager::stage($pdo, $src, $new);
            $out['staged'] = count($r['staged']) + count($r['auto_imported']);
            foreach ((array) $r['auto_imported'] as $a) if (is_array($a) && !empty($a['entity_id'])) $touched[] = (int) $a['entity_id'];
        }
        // the recheck after a load: the ids it touched, at once (0163 §12), while the recheck is on. With $recheck the caller runs
        // it straight after its reply (loads.php: the worker doesn't wait on it; the session manager, 2026-11-24)
        $touched = array_values(array_unique($touched));
        if (func_num_args() >= 7) $recheck = $touched;
        elseif ($touched) self::recheck($pdo, $touched);
        $pdo->prepare('INSERT INTO open_loads (dataset, place_key, release_id, started_at, seen, staged, listed, closed) VALUES (?, ?, ?, UTC_TIMESTAMP(), ?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE seen = seen + VALUES(seen), staged = staged + VALUES(staged), listed = listed + VALUES(listed), closed = closed + VALUES(closed)')
            ->execute([$ds, $key, mb_substr($release, 0, 40), $out['seen'], $out['staged'], $out['listed'], $out['closed']]);
        if ($no !== null) self::batchKeep($pdo, $ds, $release, $key, $no, $out);
        return $out;
    }

    /** The identity recheck on the listings a batch touched (0163 §12); never fails the load. */
    public static function recheck(PDO $pdo, array $ids): void
    {
        if (!$ids) return;
        require_once __DIR__ . '/../ListingIdentity.php';
        try { ListingIdentity::recheckTick($pdo, $ids); } catch (Throwable $x) { error_log('open load recheck: ' . $x->getMessage()); }
    }

    /** A batch already taken (open_load_batches): what it did, or null. No table yet: null (the batch runs as before). */
    private static function batchDone(PDO $pdo, string $ds, string $release, string $key, int $no): ?array
    {
        try { $st = $pdo->prepare('SELECT result FROM open_load_batches WHERE dataset = ? AND place_key = ? AND release_id = ? AND batch_no = ?');
              $st->execute([$ds, $key, mb_substr($release, 0, 40), $no]); $r = $st->fetchColumn(); } catch (Throwable $x) { return null; }
        $d = $r !== false ? json_decode((string) $r, true) : null;
        return is_array($d) ? $d : null;
    }

    private static function batchKeep(PDO $pdo, string $ds, string $release, string $key, int $no, array $out): void
    {
        try { $pdo->prepare('INSERT IGNORE INTO open_load_batches (dataset, place_key, release_id, batch_no, result, at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())')
                  ->execute([$ds, $key, mb_substr($release, 0, 40), $no, json_encode($out)]); } catch (Throwable $x) { error_log('open load batch key: ' . $x->getMessage()); }
    }

    public static function finish(PDO $pdo, string $ds, string $release, string $key): void
    {
        $pdo->prepare('UPDATE open_loads SET finished_at = UTC_TIMESTAMP() WHERE dataset = ? AND place_key = ? AND release_id = ?')->execute([$ds, $key, $release]);
        // a state's extract: which kinds it was kept by, so W1 reads it before Overpass only for those (OpenPlaces::forJob)
        if ($ds === 'geofabrik' && isset(self::GEOFABRIK_STATES[$key])) { require_once __DIR__ . '/../OpenPlaces.php'; require_once __DIR__ . '/../SearchIndex.php'; SearchIndex::setState($pdo, 'open:geofabrik_sig:' . $key, OpenPlaces::copySignature()); }
        // "returns empty" (0163 §4): a bulk load's first empty run is flagged by Operations::empties from the ledger
    }

    /** Geofabrik's states (its file names) and which is due: one download every geofabrik_days a state, at most. */
    public const GEOFABRIK_STATES = ['arizona' => 'Arizona', 'new-mexico' => 'New Mexico', 'utah' => 'Utah', 'colorado' => 'Colorado', 'nevada' => 'Nevada'];   // UT, CO, NV: Jason, 2026-11-19

    public static function geofabrikDue(PDO $pdo): array
    {
        require_once __DIR__ . '/../Thresholds.php';
        require_once __DIR__ . '/../OpenPlaces.php'; require_once __DIR__ . '/../SearchIndex.php';
        $days = max(1, (int) Thresholds::get($pdo, 'geofabrik_days'));
        $out = [];
        $st = $pdo->prepare("SELECT MAX(finished_at) FROM open_loads WHERE dataset = 'geofabrik' AND place_key = ?");
        foreach (self::GEOFABRIK_STATES as $k => $name) {
            $st->execute([$k]); $last = $st->fetchColumn();
            // kept by today's kinds (OpenPlaces::copySignature): a state loaded before the crawler's kinds were kept is due at once
            $sig = ''; try { $sig = SearchIndex::state($pdo, 'open:geofabrik_sig:' . $k); } catch (Throwable $x) {}
            if ($last && strtotime($last . ' UTC') > time() - $days * 86400 && $sig === OpenPlaces::copySignature()) continue;
            $out[] = ['key' => $k, 'name' => $name, 'release' => gmdate('Y-m-d')];
        }
        return $out;
    }

    /** One batch of a state's extract: kept in OpenStreetMap's own table (OpenPlaces::keepExtract), counted on its load. */
    public static function geofabrikBatch(PDO $pdo, string $release, string $state, array $els): array
    {
        if (!isset(self::GEOFABRIK_STATES[$state])) throw new InvalidArgumentException('Which state?');
        $pdo->prepare('INSERT IGNORE INTO open_loads (dataset, place_key, release_id, started_at) VALUES (\'geofabrik\', ?, ?, UTC_TIMESTAMP())')->execute([$state, $release]);
        require_once __DIR__ . '/../OpenPlaces.php';
        $kept = OpenPlaces::keepExtract($pdo, array_slice($els, 0, 2000));
        $pdo->prepare("UPDATE open_loads SET seen = seen + ?, listed = listed + ? WHERE dataset = 'geofabrik' AND place_key = ? AND release_id = ?")->execute([count($els), $kept, $state, $release]);
        return ['seen' => count($els), 'kept' => $kept];
    }

    /** A closure signal from a dataset against a listing: recorded with its source, lowers confidence, asks "Still open?" (R12). */
    private static function closureSignal(PDO $pdo, int $id, string $ds): void
    {
        try {
            $pdo->prepare("INSERT INTO entity_metadata (entity_id, meta_key, meta_value, source_type, is_locked, created_at, updated_at) VALUES (?, 'closed_signal', ?, 'ai_crawler', 0, NOW(), NOW())
                           ON DUPLICATE KEY UPDATE meta_value = IF(is_locked = 1, meta_value, VALUES(meta_value)), updated_at = NOW()")
                ->execute([$id, json_encode(['line' => self::DATASETS[$ds][0] . ' marks it closed', 'url' => self::DATASETS[$ds][2], 'at' => date('Y-m-d'), 'from' => $ds], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
            require_once __DIR__ . '/../ListingConfidence.php'; ListingConfidence::refresh($pdo, $id);
        } catch (Throwable $x) { error_log('open load closure #' . $id . ': ' . $x->getMessage()); }
    }

    private static function sourceId(PDO $pdo, string $ds): int
    {
        $url = self::DATASETS[$ds][2];
        $st = $pdo->prepare('SELECT id FROM ' . Database::t('crawler_sources') . ' WHERE url = ? ORDER BY id LIMIT 1'); $st->execute([$url]);
        return (int) ($st->fetchColumn() ?: IntakeStager::createSource($pdo, $url, ['create_listings'], self::DATASETS[$ds][0] . ' (open data, credited)', 0));
    }

    // ---- Geofabrik: the first step is one test download from Railway (0163 §8 stage 8) --------------------------------

    /** The worker's test of Geofabrik: [{state, ok, status, bytes, why, at}], kept for the card and the switch-on check. */
    public static function probed(PDO $pdo, array $r): void
    {
        require_once __DIR__ . '/../SearchIndex.php';
        SearchIndex::setState($pdo, 'open:geofabrik_probe', json_encode(array_slice($r, 0, 4) + ['at' => gmdate('c')], JSON_UNESCAPED_SLASHES));
        foreach ($r as $x) if (is_array($x) && empty($x['ok'])) { require_once __DIR__ . '/../EngineCase.php'; EngineCase::raise($pdo, 'service', 'geofabrik', 'Geofabrik refused the Railway worker\'s test download (' . ($x['why'] ?? $x['status'] ?? '?') . '): use another free OpenStreetMap mirror, by hand, never repeated tries.', ['host' => 'download.geofabrik.de']); break; }
    }

    public static function lastProbe(PDO $pdo): ?array
    {
        try { require_once __DIR__ . '/../SearchIndex.php'; $v = json_decode(SearchIndex::state($pdo, 'open:geofabrik_probe'), true); return is_array($v) ? $v : null; } catch (Throwable) { return null; }
    }
}
