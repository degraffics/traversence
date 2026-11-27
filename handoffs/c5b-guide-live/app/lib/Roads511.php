<?php
// app/lib/Roads511.php — road conditions for a guide (decisions/0131 §5), from Arizona's 511 (az511.com). Two sources:
//   - the Work Zone Data Exchange feed (az511.com/api/wzdx, the national WZDx format): road work, lane closures and
//     detours; public, no key;
//   - the developer API (a free key: AZ511_API_KEY in .env, set by hand; register at az511.com/developers): its Events
//     (incidents, closures, restrictions) and Alerts (statewide notices, shown when they name the guide's roads).
//     Without the key, only the work zones show. (It also has cameras, weather stations, message boards and rest areas.) The statewide feeds are read at most every 10 minutes (the API allows 10 calls a
// minute) and filtered per place: what's on the roads the guide names (SR 260, US 180, I-40) and within reach of it.
// Other states' 511 services join here as their regions open (their APIs share this shape).
declare(strict_types=1);

require_once __DIR__ . '/PageCache.php';

final class Roads511
{
    private const BASE = 'https://az511.com/api/v2/get/';
    private const WZDX = 'https://az511.com/api/wzdx';
    /** WZDx vehicle_impact → what a driver reads; all-lanes-closed is a closure. */
    private const IMPACT = ['all-lanes-closed' => 'All lanes closed', 'some-lanes-closed' => 'Lanes closed', 'some-lanes-closed-merge-left' => 'Lanes closed: merge left',
                            'some-lanes-closed-merge-right' => 'Lanes closed: merge right', 'all-lanes-open-shift-left' => 'Lanes shifted left', 'all-lanes-open-shift-right' => 'Lanes shifted right',
                            'some-lanes-closed-split' => 'Lanes split', 'flagging' => 'Flaggers: expect stops', 'alternating-one-way' => 'One lane, alternating directions',
                            'temporary-traffic-signal' => 'Temporary traffic signal', 'all-lanes-open' => 'All lanes open'];   // WZDx v4 VehicleImpact
    /** feed => what it is. Each is read on its own; one that fails or doesn't exist is skipped. */
    public const FEEDS = ['event' => 'Closures, incidents, construction and restrictions', 'alerts' => 'Statewide alert notices'];

    /** @var callable|null for tests: fn(string $feed): ?array */
    public static $fetch = null;

    /** Work zones need no key, so road conditions are always on; the key adds the developer API's feeds. */
    public static function on(): bool { return true; }
    private static function keyed(): bool { return self::key() !== '' || self::$fetch !== null; }
    private static function key(): string { return trim((string) (getenv('AZ511_API_KEY') ?: '')); }

    /** "SR 260", "State Route 260", "AZ-260" → "SR 260"; "US-180" → "US 180"; "I 40" → "I-40". '' when it isn't a road. */
    public static function road(string $s): string
    {
        $s = strtoupper(trim($s));
        if (preg_match('/\b(?:I|INTERSTATE)[\s-]*(\d{1,3})\b/', $s, $m)) return 'I-' . (int) $m[1];
        if (preg_match('/\b(?:US|U\.S\.)(?:\s*(?:HIGHWAY|HWY|ROUTE|RTE))?[\s-]*(\d{1,3})\b/', $s, $m)) return 'US ' . (int) $m[1];
        if (preg_match('/\b(?:SR|AZ|STATE\s+(?:ROUTE|HIGHWAY|HWY|RTE)|ARIZONA\s+(?:ROUTE|HIGHWAY))[\s-]*(\d{1,3})\b/', $s, $m)) return 'SR ' . (int) $m[1];
        return '';
    }

    /** Every road named in a text ("via State Route 260 and SR 373") as road(): unique, in order. */
    public static function roadsIn(string $text): array
    {
        preg_match_all('/\b(?:I|Interstate|US|U\.S\.|SR|AZ|State\s+(?:Route|Highway|Hwy|Rte)|Arizona\s+(?:Route|Highway))(?:\s*(?:Highway|Hwy|Route|Rte))?[\s-]*\d{1,3}\b/i', $text, $m);
        $out = [];
        foreach ($m[0] as $x) { $r = self::road($x); if ($r !== '') $out[$r] = true; }
        return array_keys($out);
    }

    /**
     * What's on the roads for a place: [type, road, where, what, closed (bool), updated (unix)], closures first, newest next.
     * $roads: the roads the guide names (road() form); $lat/$lon/$miles: anything within reach as well.
     */
    /**
     * near(), from what's stored only: null when a feed it reads isn't stored (a guide page draws this at once, so nothing
     * jumps when the live answer comes; api/guide-live.php asks the service). C.5b, 0145 V06.
     */
    public static function stored(PDO $pdo, array $roads, ?float $lat, ?float $lon, float $miles = 30, int $n = 8): ?array
    {
        foreach (array_merge(['wzdx'], self::keyed() ? array_keys(self::FEEDS) : []) as $f) if (PageCache::get($pdo, 'az511:' . $f) === null) return null;
        return self::near($pdo, $roads, $lat, $lon, $miles, $n);
    }

    public static function near(PDO $pdo, array $roads, ?float $lat, ?float $lon, float $miles = 30, int $n = 8): array
    {
        $want = array_flip($roads);
        $out = [];
        $now = time();
        $zones = self::wzdx($pdo);
        foreach ($zones as $z) {   // work zones: going on now, or starting within three days
            if ($z['end'] && $z['end'] < $now) continue;
            if ($z['start'] && $z['start'] > $now + 3 * 86400) continue;
            $close = false;
            if ($lat !== null) foreach ($z['pts'] as [$la, $lo]) if (self::miles($lat, (float) $lon, $la, $lo) <= $miles) { $close = true; break; }
            if (!($z['road'] !== '' && isset($want[$z['road']])) && !$close) continue;
            $soon = $z['start'] > $now ? 'Starts ' . date('M j', $z['start']) . '. ' : '';
            $until = $z['end'] ? ' Until ' . date('M j', $z['end']) . '.' : '';
            $what = trim($soon . $z['what']); $what = $what !== '' && !preg_match('/[.!?]$/', $what) ? $what . '.' : $what;
            $out[] = ['type' => $z['closed'] ? 'Closure' : $z['type'], 'road' => $z['road'] ?: $z['road_name'], 'where' => $z['where'], 'what' => trim($what . $until), 'closed' => $z['closed'], 'updated' => $z['updated']];
        }
        foreach (self::keyed() ? array_keys(self::FEEDS) : [] as $feed) {
            foreach (self::feed($pdo, $feed) as $e) {
                if ($feed === 'alerts') {   // statewide notices: not placed on the map, so only those that name the guide's roads
                    $txt = trim((string) ($e['Message'] ?? '') . ' ' . (string) ($e['Notes'] ?? ''));
                    $hit = array_values(array_intersect(self::roadsIn($txt), $roads));
                    $endA = self::when($e['EndTime'] ?? null);
                    if (!$hit || ($endA && $endA < $now)) continue;
                    $out[] = ['type' => 'Alert', 'road' => $hit[0], 'where' => '', 'what' => mb_substr($txt, 0, 300), 'closed' => false, 'updated' => self::when($e['StartTime'] ?? null)];
                    continue;
                }
                // the Events API (az511.com/api/v2/get/event): RoadwayName is sometimes a project's name, so the description's roads count too
                $rn = trim((string) ($e['RoadwayName'] ?? ''));
                $desc = trim((string) ($e['Description'] ?? ''));
                if ($rn !== '' && stripos($desc, $rn) === 0) $desc = ltrim(substr($desc, strlen($rn)), " -–:");   // it repeats the name first
                $road = self::road($rn); if ($road === '') $road = self::roadsIn($rn . ' ' . $desc)[0] ?? '';
                $end = self::when($e['PlannedEndDate'] ?? null); $start = self::when($e['StartDate'] ?? null);
                if (($end && $end < $now) || ($start && $start > $now + 3 * 86400)) continue;
                $pts = [];
                foreach ([['Latitude', 'Longitude'], ['LatitudeSecondary', 'LongitudeSecondary']] as [$a, $b]) if (!empty($e[$a]) && !empty($e[$b])) $pts[] = [(float) $e[$a], (float) $e[$b]];
                $close = false;
                if ($lat !== null) foreach ($pts as [$la, $lo]) if (self::miles($lat, (float) $lon, $la, $lo) <= $miles) { $close = true; break; }
                if (!($road !== '' && isset($want[$road])) && !$close) continue;
                if (($e['EventType'] ?? '') === 'roadwork' && $pts) {   // road work a work zone already shows
                    foreach ($zones as $z) if ($z['road'] === $road) foreach ($z['pts'] as [$zl, $zo]) if (self::miles($pts[0][0], $pts[0][1], $zl, $zo) < 1.5) continue 3;
                }
                $closed = !empty($e['IsFullClosure']);   // "closures" with lanes open (chains required) is a restriction
                $extra = [];
                $lanes = trim((string) ($e['LanesAffected'] ?? '')); if ($lanes !== '' && !preg_match('/^no data$/i', $lanes) && stripos($desc, $lanes) === false) $extra[] = ucfirst($lanes) . ' affected';
                foreach (['Speed' => ['Speed limit', 'mph'], 'Height' => ['Height limit', 'ft'], 'Width' => ['Width limit', 'ft'], 'Length' => ['Length limit', 'ft'], 'Weight' => ['Weight limit', 'tons']] as $rk => [$rl, $ru])
                    if (isset($e['Restrictions'][$rk]) && $e['Restrictions'][$rk] !== null && $e['Restrictions'][$rk] !== '') $extra[] = $rl . ' ' . $e['Restrictions'][$rk] . ' ' . $ru;
                if (trim((string) ($e['DetourInstructions'] ?? '')) !== '') $extra[] = 'Detour: ' . trim((string) $e['DetourInstructions']);
                if ($end) $extra[] = 'Until ' . date('M j', $end);
                $what = rtrim($desc, '. ') . ($desc !== '' ? '. ' : '') . implode('. ', array_map(fn ($x) => rtrim($x, '. '), $extra));
                $dir = (string) ($e['DirectionOfTravel'] ?? ''); if (in_array($dir, ['Unknown', 'All Directions', 'Both Directions'], true)) $dir = $dir === 'Unknown' ? '' : 'Both directions';
                $out[] = ['type' => self::type((string) ($e['EventType'] ?? ''), $closed), 'road' => $road ?: mb_strimwidth(ucwords(strtolower($rn)), 0, 60, '…'), 'where' => $dir,
                          'what' => mb_substr(trim($what), 0, 400) . (preg_match('/[.!?]$/', trim($what)) || trim($what) === '' ? '' : '.'), 'closed' => $closed, 'updated' => self::when($e['LastUpdated'] ?? $e['Reported'] ?? null)];
            }
        }
        usort($out, fn ($a, $b) => [$b['closed'], $b['updated']] <=> [$a['closed'], $a['updated']]);
        $seen = []; $keep = [];
        foreach ($out as $r) { $k = $r['road'] . '|' . $r['what']; if (isset($seen[$k])) continue; $seen[$k] = 1; $keep[] = $r; }
        return array_slice($keep, 0, $n);
    }

    private static function type(string $t, bool $closed): string
    {
        if ($closed) return 'Closure';
        $t = strtolower($t);
        if (str_contains($t, 'closure')) return 'Partial closure';
        return str_contains($t, 'accident') || str_contains($t, 'incident') ? 'Incident' : (str_contains($t, 'roadwork') || str_contains($t, 'construction') ? 'Road work' : (str_contains($t, 'special') ? 'Event' : 'Restriction'));
    }

    private static function when($v): int
    {
        if ($v === null || $v === '') return 0;
        if (is_numeric($v)) return (int) $v;
        if (preg_match('/\/Date\((\d+)/', (string) $v, $m)) return (int) ((int) $m[1] / 1000);
        return (int) (strtotime((string) $v) ?: 0);
    }

    /**
     * The work zones statewide (WZDx v4; v3's flat properties also read), kept 25 minutes in a slim form (the guide_live_tick job renews it every 15, so a visitor never waits on it): road, a few
     * points along it, type, closed, what, where, start, end, updated.
     */
    private static function wzdx(PDO $pdo): array
    {
        $v = PageCache::remember($pdo, 'az511:wzdx', 1500, function () {
            if (self::$fetch) $j = (self::$fetch)('wzdx');
            else {
                if (!function_exists('curl_init')) return [];
                require_once __DIR__ . '/ServiceHealth.php'; if (ServiceHealth::held(self::WZDX) !== null) return [];
                $ch = curl_init(self::WZDX);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_ENCODING => '', CURLOPT_HTTPHEADER => ['User-Agent: Traversence guides (support@traversence.com)', 'Accept: application/json']]);
                $body = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
                ServiceHealth::seen(self::WZDX, $code, $body, '', 'AZ 511 WZDx');
                if (!is_string($body) || $code >= 400) { error_log('az511 wzdx: ' . $code); return []; }
                $j = json_decode($body, true);
            }
            $out = [];
            foreach ((array) ($j['features'] ?? []) as $f) {
                $pp = (array) ($f['properties'] ?? []); $cd = (array) ($pp['core_details'] ?? []) + $pp;   // v4 core_details, else v3 flat
                if (!in_array((string) ($cd['event_type'] ?? 'work-zone'), ['work-zone', 'detour', 'restriction'], true)) continue;
                $names = array_values(array_filter(array_map('strval', (array) ($cd['road_names'] ?? [$cd['road_name'] ?? '']))));
                $road = ''; foreach ($names as $nm) if (($road = self::road($nm)) !== '') break;
                $coords = (array) ($f['geometry']['coordinates'] ?? []);
                if (($f['geometry']['type'] ?? '') === 'Point') $coords = [$coords];
                if (($f['geometry']['type'] ?? '') === 'MultiLineString') $coords = array_merge(...array_map('array_values', $coords ?: [[]]));
                $pts = [];
                $step = max(1, (int) ceil(count($coords) / 6));
                for ($i = 0; $i < count($coords); $i += $step) if (isset($coords[$i][1])) $pts[] = [round((float) $coords[$i][1], 4), round((float) $coords[$i][0], 4)];
                if ($coords && isset(end($coords)[1])) $pts[] = [round((float) end($coords)[1], 4), round((float) end($coords)[0], 4)];
                $impact = (string) ($pp['vehicle_impact'] ?? '');
                $what = trim((string) ($cd['description'] ?? ''));
                $label = self::IMPACT[$impact] ?? '';
                if ($label !== '' && stripos($what, $label) === false) $what = $label . ($what !== '' ? '. ' . $what : '');
                $extra = [];
                $kph = $pp['reduced_speed_limit_kph'] ?? null;   // sometimes text, not a number (2026-11-13: it stopped every run)
                if (is_numeric($kph) && (float) $kph > 0) $extra[] = 'Speed limit ' . (int) (round((float) $kph * 0.621371 / 5) * 5) . ' mph';
                if (!empty($pp['worker_presence']['are_workers_present'])) $extra[] = 'Workers present';
                foreach ((array) ($pp['restrictions'] ?? []) as $r) { $rt = str_replace('-', ' ', (string) ($r['type'] ?? '')); if ($rt !== '') $extra[] = ucfirst($rt) . (isset($r['value']) ? ' ' . $r['value'] . ' ' . str_replace(['feet', 'meters'], ['ft', 'm'], (string) ($r['unit'] ?? '')) : ''); }
                if ($extra) $what = rtrim($what, '. ') . ($what !== '' ? '. ' : '') . implode('. ', $extra);
                $span = ($pp['beginning_cross_street'] ?? '') !== '' ? 'from ' . $pp['beginning_cross_street'] . (($pp['ending_cross_street'] ?? '') !== '' ? ' to ' . $pp['ending_cross_street'] : '')
                    : (isset($pp['beginning_milepost']) ? 'milepost ' . $pp['beginning_milepost'] . (isset($pp['ending_milepost']) ? '–' . $pp['ending_milepost'] : '') : '');
                $where = trim(ucfirst((string) ($cd['direction'] ?? '')) . ' ' . $span);
                $out[] = ['road' => $road, 'road_name' => $names[0] ?? '', 'pts' => $pts, 'type' => ($cd['event_type'] ?? '') === 'detour' ? 'Detour' : 'Road work', 'closed' => $impact === 'all-lanes-closed',
                          'what' => mb_substr($what, 0, 300), 'where' => mb_substr($where, 0, 160), 'start' => self::when($pp['start_date'] ?? null), 'end' => self::when($pp['end_date'] ?? null),
                          'updated' => self::when($cd['update_date'] ?? $pp['update_date'] ?? null)];
            }
            return $out;
        });
        return is_array($v) ? $v : [];
    }

    /** Fetch the feeds again now (the guide_live_tick job, ahead of visitors). */
    public static function warm(PDO $pdo): int
    {
        PageCache::forget($pdo, 'az511:');
        $n = count(self::wzdx($pdo));
        foreach (self::keyed() ? array_keys(self::FEEDS) : [] as $feed) self::feed($pdo, $feed);
        return $n;
    }

    /** One statewide feed, kept 25 minutes (renewed every 15 by guide_live_tick). */
    private static function feed(PDO $pdo, string $feed): array
    {
        $v = PageCache::remember($pdo, 'az511:' . $feed, 1500, function () use ($feed) {
            if (self::$fetch) return (self::$fetch)($feed) ?? [];
            if (!function_exists('curl_init')) return [];
            require_once __DIR__ . '/ServiceHealth.php'; if (ServiceHealth::held(self::BASE) !== null) return [];
            $ch = curl_init(self::BASE . $feed . '?key=' . rawurlencode(self::key()) . '&format=json');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_HTTPHEADER => ['User-Agent: Traversence guides (support@traversence.com)']]);
            $body = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            ServiceHealth::seen(self::BASE . $feed, $code, $body, '', 'AZ 511 ' . $feed);
            if (!is_string($body) || $code >= 400) { if ($code !== 404) error_log('az511 ' . $feed . ': ' . $code); return []; }
            $j = json_decode($body, true);
            return is_array($j) ? array_values(array_filter($j, 'is_array')) : [];
        });
        return is_array($v) ? $v : [];
    }

    private static function miles(float $la1, float $lo1, float $la2, float $lo2): float
    {
        $dLat = deg2rad($la2 - $la1); $dLon = deg2rad($lo2 - $lo1);
        $x = sin($dLat / 2) ** 2 + cos(deg2rad($la1)) * cos(deg2rad($la2)) * sin($dLon / 2) ** 2;
        return 2 * 3959 * asin(min(1, sqrt($x)));
    }
}
