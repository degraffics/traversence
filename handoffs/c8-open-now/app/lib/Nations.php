<?php

declare(strict_types=1);

/**
 * api/lib/Nations.php — tribal nations and their visitor rules (decisions/0058 §26).
 *
 * On a nation's land Traversence shows only the places the nation lists for visitors, each with the nation's rules.
 *  - nations: one row per nation; match_names are the Census (AIANNH) area names that are its land ("Acoma|Acoma Pueblo").
 *    Its rules show on pages only once a person has checked them against the nation's own site (verified_at).
 *  - nation_shapes: the boundaries, simplified, sent by the worker with the monthly landmarks load.
 *  - nation_decision_log: every opening and closing of a place on a nation's land; written once, never edited.
 * Pins: a journey's or a photo's pin on a nation's land is kept only at a public place (a landmark the nation opened, a
 * public listing, a public outdoor place), moved onto it; anywhere else it is dropped (decisions/0058 §26).
 */
final class Nations
{
    /** How close a pin must be to one of the nation's public places to be kept (snapped to the place). */
    public const PIN_MILES = 0.5;
    /** The rules, in the order a page shows them: [column, label]. */
    public const RULES = ['permits' => 'Permits and licenses', 'fees' => 'Fees', 'guides' => 'Guides', 'photography' => 'Photography',
                          'drones' => 'Drones', 'alcohol' => 'Alcohol', 'time_zone' => 'Time', 'other_rules' => 'Also'];

    private static ?array $all = null;

    public static function ready(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try { $pdo->query('SELECT 1 FROM nations LIMIT 1'); $pdo->query('SELECT 1 FROM nation_shapes LIMIT 1'); $pdo->query('SELECT 1 FROM nation_decision_log LIMIT 1'); return $ok = true; }
        catch (PDOException) { return $ok = false; }
    }

    public static function all(PDO $pdo): array
    {
        if (self::$all !== null) return self::$all;
        if (!self::ready($pdo)) return self::$all = [];
        return self::$all = $pdo->query('SELECT * FROM nations ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function get(PDO $pdo, int $id): ?array
    {
        foreach (self::all($pdo) as $n) if ((int) $n['id'] === $id) return $n;
        return null;
    }

    /** The nation whose land a Census area name ("Zuni", "Navajo Nation") is, or null. */
    public static function byShape(PDO $pdo, string $shape): ?array
    {
        $shape = mb_strtolower(trim($shape));
        if ($shape === '') return null;
        foreach (self::all($pdo) as $n) {
            if (mb_strtolower($n['name']) === $shape) return $n;
            foreach (explode('|', (string) $n['match_names']) as $m) if (mb_strtolower(trim($m)) === $shape) return $n;
        }
        return null;
    }

    /** The nation's own name for a Census area name ("Zuni" -> "Pueblo of Zuni"); the area name when no row matches. */
    public static function label(PDO $pdo, string $shape): string
    {
        $n = self::byShape($pdo, $shape);
        return $n ? (string) $n['name'] : $shape;
    }

    /** The rules a page shows: [[label, text], …], only once checked against the nation's own site. */
    public static function rules(?array $n): array
    {
        if (!$n || empty($n['verified_at'])) return [];
        $out = [];
        foreach (self::RULES as $col => $label) if (trim((string) ($n[$col] ?? '')) !== '') $out[] = [$label, trim((string) $n[$col])];
        return $out;
    }

    /** The nation's own visitor page, else its website, else ''. */
    public static function visitorLink(?array $n): string
    {
        if (!$n) return '';
        foreach (['visitor_url', 'website'] as $c) if (preg_match('#^https?://#i', (string) $n[$c])) return (string) $n[$c];
        return '';
    }

    // ---- boundaries ----------------------------------------------------------------------------------------

    /** The Census area name of the nation's land this point is on, or null (or null when no boundaries are loaded). */
    public static function at(PDO $pdo, float $lat, float $lon): ?string
    {
        return self::allAt($pdo, $lat, $lon)[0] ?? null;
    }

    /** Every nation's land this point is on, by Census area name (the Hopi Reservation sits inside the Navajo Nation's
     *  outer line: OpenNow's time zone tells them apart, P10's ruling 2026-11-27). Empty when no boundaries are loaded. */
    public static function allAt(PDO $pdo, float $lat, float $lon): array
    {
        if (!self::ready($pdo)) return [];
        $st = $pdo->prepare('SELECT name, rings FROM nation_shapes WHERE min_lat <= ? AND max_lat >= ? AND min_lon <= ? AND max_lon >= ?');
        $st->execute([$lat, $lat, $lon, $lon]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $rings = json_decode((string) $s['rings'], true);
            if (is_array($rings) && self::inside($lon, $lat, $rings)) $out[] = (string) $s['name'];
        }
        return $out;
    }

    /** Even-odd point in polygon over all rings (a hole counts as outside). Each ring is [[lon, lat], …]. */
    private static function inside(float $x, float $y, array $rings): bool
    {
        $hit = false;
        foreach ($rings as $ring) {
            $n = count($ring);
            for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
                [$xi, $yi] = $ring[$i]; [$xj, $yj] = $ring[$j];
                if ((($yi > $y) !== ($yj > $y)) && $x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-12) + $xi) $hit = !$hit;
            }
        }
        return $hit;
    }

    /**
     * A pin, following the nations' rules: off a nation's land it stays; on it, it's kept only at one of the nation's
     * public places (moved onto it), else dropped. Returns [lat|null, lon|null, note|null].
     */
    public static function pin(PDO $pdo, ?float $lat, ?float $lon): array
    {
        if ($lat === null || $lon === null) return [$lat, $lon, null];
        $shape = self::at($pdo, $lat, $lon);
        if ($shape === null) return [$lat, $lon, null];
        $place = self::publicPlaceNear($pdo, $shape, $lat, $lon);
        $label = self::label($pdo, $shape);
        if ($place) return [(float) $place['lat'], (float) $place['lon'], 'On ' . $label . ' land, a pin shows only at a place the nation opens to visitors, so it now points to ' . $place['name'] . '.'];
        return [null, null, 'On ' . $label . ' land, a pin shows only at a place the nation opens to visitors, so this one isn\'t shown. Name the place in your words instead.'];
    }

    /**
     * The nearest public place within PIN_MILES: a landmark the nation opened, a public listing (never one with a
     * confidential address), or a public outdoor place. Only what search already shows the public counts.
     */
    private static function publicPlaceNear(PDO $pdo, string $shape, float $lat, float $lon): ?array
    {
        $pad = self::PIN_MILES / 60;
        $box = [$lat - $pad, $lat + $pad, $lon - $pad * 1.3, $lon + $pad * 1.3];
        $rows = [];
        try {
            $st = $pdo->prepare("SELECT label AS name, lat, lon FROM search_index WHERE kind IN ('listing', 'outdoors') AND private_location = 0 AND lat BETWEEN ? AND ? AND lon BETWEEN ? AND ?");
            $st->execute($box);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            if (!class_exists('Landmarks')) require_once __DIR__ . '/Landmarks.php';
            if (Landmarks::ready($pdo)) {
                $st = $pdo->prepare("SELECT name, lat, lon FROM landmarks WHERE " . Landmarks::visibleSql() . " AND lat BETWEEN ? AND ? AND lon BETWEEN ? AND ?");
                $st->execute($box);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        $best = null; $bd = INF;
        foreach ($rows as $p) {
            if ($p['lat'] === null || $p['lon'] === null) continue;
            $x = deg2rad((float) $p['lon'] - $lon) * cos(deg2rad(((float) $p['lat'] + $lat) / 2)); $y = deg2rad((float) $p['lat'] - $lat);
            $d = 3959 * sqrt($x * $x + $y * $y);
            if ($d <= self::PIN_MILES && $d < $bd) { $best = $p; $bd = $d; }
        }
        return $best;
    }

    /** One area's boundary from the worker: {name, bbox: [minlon, minlat, maxlon, maxlat], rings: [[[lon, lat], …], …]}. */
    public static function saveShape(PDO $pdo, string $file, array $s): bool
    {
        $name = mb_substr(trim(strip_tags((string) ($s['name'] ?? ''))), 0, 160);
        $b = array_map('floatval', (array) ($s['bbox'] ?? []));
        $rings = [];
        foreach ((array) ($s['rings'] ?? []) as $ring) {
            $r = [];
            foreach ((array) $ring as $pt) if (is_array($pt) && count($pt) >= 2 && is_numeric($pt[0]) && is_numeric($pt[1])) $r[] = [round((float) $pt[0], 5), round((float) $pt[1], 5)];
            if (count($r) >= 4) $rings[] = $r;
        }
        if ($name === '' || count($b) !== 4 || !$rings) return false;
        $pdo->prepare('INSERT INTO nation_shapes (name, min_lat, max_lat, min_lon, max_lon, rings, file, loaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$name, $b[1], $b[3], $b[0], $b[2], json_encode($rings), mb_substr($file, 0, 40), date('Y-m-d H:i:s')]);
        return true;
    }

    /** After a load: last month's boundaries go, this month's stay. */
    public static function finishShapes(PDO $pdo, string $file): void
    {
        $pdo->prepare('DELETE FROM nation_shapes WHERE file <> ?')->execute([$file]);
    }

    // ---- the log and the admin --------------------------------------------------------------------------

    public static function log(PDO $pdo, string $nation, string $kind, string $ref, string $name, string $from, string $to, ?int $uid, string $contact = '', string $evidence = '', string $note = ''): void
    {
        if (!self::ready($pdo)) return;
        $pdo->prepare('INSERT INTO nation_decision_log (nation, place_kind, place_ref, place_name, from_status, to_status, by_user, nation_contact, evidence_url, note, at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([mb_substr($nation, 0, 160), $kind, mb_substr($ref, 0, 40), mb_substr($name, 0, 200), $from, $to, $uid, mb_substr($contact, 0, 200), mb_substr($evidence, 0, 500), mb_substr($note, 0, 300), date('Y-m-d H:i:s')]);
    }

    public static function history(PDO $pdo, string $kind, string $ref): array
    {
        if (!self::ready($pdo)) return [];
        $st = $pdo->prepare('SELECT * FROM nation_decision_log WHERE place_kind = ? AND place_ref = ? ORDER BY at DESC, id DESC LIMIT 20');
        $st->execute([$kind, $ref]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Saves a nation's details and rules. "Checked against the nation's own site" stamps verified_at; any change without it clears it. */
    public static function save(PDO $pdo, int $uid, int $id, array $in): void
    {
        $n = self::get($pdo, $id);
        if (!$n) throw new InvalidArgumentException('That nation isn\'t in the list.');
        $t = fn (string $k, int $max) => mb_substr(trim(strip_tags((string) ($in[$k] ?? ''))), 0, $max);
        $url = function (string $k) use ($in): string { $u = trim((string) ($in[$k] ?? '')); if ($u !== '' && !preg_match('#^https?://[^\s]+$#i', $u)) throw new InvalidArgumentException('A link must start with https://.'); return mb_substr($u, 0, 300); };
        $name = $t('name', 120);
        if (mb_strlen($name) < 3) throw new InvalidArgumentException('Give the nation\'s name as it uses it.');
        $verified = !empty($in['verified']);
        if ($verified && self::visitorLink(['visitor_url' => $url('visitor_url'), 'website' => $url('website')]) === '') throw new InvalidArgumentException('Checking the rules needs the nation\'s own website or visitor page.');
        $cols = ['name' => $name, 'match_names' => $t('match_names', 400), 'website' => $url('website'), 'visitor_url' => $url('visitor_url'), 'office' => $t('office', 160),
                 'office_phone' => $t('office_phone', 60), 'office_email' => $t('office_email', 160), 'listed_only' => 1, 'time_zone' => $t('time_zone', 160)];
        foreach (['permits', 'fees', 'guides', 'photography', 'drones', 'alcohol', 'other_rules'] as $c) $cols[$c] = $t($c, 1000);
        $sql = 'UPDATE nations SET ' . implode(' = ?, ', array_keys($cols)) . ' = ?, verified_at = ?, verified_by = ?, updated_at = ? WHERE id = ?';
        $pdo->prepare($sql)->execute(array_merge(array_values($cols), [$verified ? date('Y-m-d H:i:s') : null, $verified ? $uid : null, date('Y-m-d H:i:s'), $id]));
        self::$all = null;
    }
}
