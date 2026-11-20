<?php
// app/lib/Trips.php: trips you can save (decisions/0174 §1, §2, release 2). A trip is a member's own list of stops in order
// (day 1 until the trip page, release 3, adds days). A stop is a kind and a ref, read fresh each time through resolve(), so
// the rules of the day apply: a place kept private, told only, closed for good or gone shows greyed with why, never with a
// point. Private by default: nobody but its owner reads a trip (sharing is release 4), and nothing here counts per person.
// A guest keeps one trip on the device (js/tv-trips.js); signing in moves it into the account (claim()).
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Trips
{
    /** The kinds of stop: kind => what it is. listing: entities.id; lm: landmarks.gnis_id; rec: rec_places.ridb_id;
     *  place: a place key (cluster:12); osm: OpenStreetMap's place in our copy (credited, decisions/0065). */
    public const KINDS = ['listing' => 'Business', 'lm' => 'Place', 'rec' => 'Outdoor place', 'place' => 'Town or area', 'osm' => 'From OpenStreetMap'];
    public const MAX_STOPS = 60;
    public const MAX_TRIPS = 100;
    public const KEEP_DELETED_DAYS = 30;

    private static ?bool $ready = null;

    public static function ready(PDO $pdo): bool
    {
        if (self::$ready !== null) return self::$ready;
        try { $pdo->query('SELECT 1 FROM trips LIMIT 0'); $pdo->query('SELECT 1 FROM trip_stops LIMIT 0'); return self::$ready = true; }
        catch (Throwable $e) { return self::$ready = false; }
    }

    /** A stop's kind and ref as given by a page or the client: [kind, ref] or null. */
    public static function key(string $kind, string $ref): ?array
    {
        $ref = trim($ref);
        if (!isset(self::KINDS[$kind]) || $ref === '' || strlen($ref) > 80) return null;
        if (in_array($kind, ['listing', 'lm'], true) && !ctype_digit($ref)) return null;
        if ($kind === 'place' && !preg_match('/^(hub|geo_hub|cluster|town|sub):\d+$/', $ref)) return null;
        if ($kind === 'osm' && !preg_match('#^(node|way|relation)/\d+$#', $ref)) return null;
        if ($kind === 'rec' && !preg_match('/^[\w-]+$/', $ref)) return null;
        return [$kind, $ref];
    }

    /**
     * What a stop is today: ['ok' => true, 'name', 'sub', 'url', 'lat', 'lon', 'town', 'credit'] or ['ok' => false,
     * 'name', 'why'] (a place kept private, told only, closed for good, gone). The same rules as Directions (0174 §2).
     */
    public static function resolve(PDO $pdo, string $kind, string $ref): array
    {
        $no = fn (string $why, string $name = '') => ['ok' => false, 'kind' => $kind, 'ref' => $ref, 'name' => $name, 'why' => $why];
        if (!self::key($kind, $ref)) return $no('Not something a trip can hold.');
        $pt = function (?float $lat, ?float $lon) use ($pdo): array {
            if ($lat === null || $lon === null) return [null, null];
            try { require_once __DIR__ . '/Nations.php'; [$lat, $lon] = Nations::pin($pdo, $lat, $lon); } catch (Throwable $e) {}   // a nation's land: its public place (0066)
            return [$lat, $lon];
        };
        try {
            if ($kind === 'listing') {
                $st = $pdo->prepare("SELECT e.id, e.name, e.category_id, e.latitude, e.longitude, e.is_suppressed, c.name AS category, ma.meta_value AS address, mc.meta_value AS city, mcl.meta_value AS closed
                                       FROM entities e LEFT JOIN categories c ON c.id = e.category_id
                                       LEFT JOIN entity_metadata ma ON ma.entity_id = e.id AND ma.meta_key = 'address'
                                       LEFT JOIN entity_metadata mc ON mc.entity_id = e.id AND mc.meta_key = 'city'
                                       LEFT JOIN entity_metadata mcl ON mcl.entity_id = e.id AND mcl.meta_key = 'closed'
                                      WHERE e.id = ? LIMIT 1");
                $st->execute([(int) $ref]); $r = $st->fetch(PDO::FETCH_ASSOC);
                if (!$r || (int) $r['is_suppressed'] === 1) return $no('No longer listed.');
                foreach (['address', 'city'] as $c) {   // entity_metadata holds JSON values ("Snowflake")
                    $v = $r[$c] === null ? null : json_decode((string) $r[$c], true);
                    $r[$c] = $r[$c] === null ? null : (is_scalar($v) ? (string) $v : (json_last_error() === JSON_ERROR_NONE ? '' : (string) $r[$c]));
                }
                $name = (string) $r['name'];
                require_once __DIR__ . '/ListingSafety.php'; require_once __DIR__ . '/ListingActions.php';
                if (ListingSafety::isConfidential($r['category_id'] !== null ? (int) $r['category_id'] : null, $name)) return $no('Its location is kept private: call them.', $name);
                if ($r['closed'] !== null) return $no('Marked closed: closed for good, or moved.', $name);   // the 'closed' mark (decisions/0072, 0164 R12)
                if (ListingActions::isMailbox((string) $r['address'])) return $no('Only a mailbox address: no place to drive to.', $name);
                if ($r['latitude'] === null || $r['longitude'] === null) return $no('It has no point on the map yet.', $name);
                [$la, $lo] = $pt((float) $r['latitude'], (float) $r['longitude']);
                return ['ok' => true, 'kind' => $kind, 'ref' => $ref, 'name' => $name, 'sub' => preg_replace('/\s*\([^)]*$/', '', (string) $r['category']), 'url' => '/listing/view.php?id=' . (int) $r['id'],
                        'lat' => $la, 'lon' => $lo, 'town' => (string) $r['city'], 'credit' => ''];
            }
            if ($kind === 'lm') {
                require_once __DIR__ . '/Landmarks.php';
                $r = Landmarks::get($pdo, (int) $ref);   // visibleSql(): held places never come back (0066)
                if (!$r) {
                    $s = Landmarks::story($pdo, (int) $ref);
                    return $s ? $no('Told, not placed: it has no point on the map.', (string) $s['name']) : $no('No longer shown.');
                }
                if ($r['lat'] === null || $r['lon'] === null) return $no('It has no point on the map.', (string) $r['name']);
                [$la, $lo] = $pt((float) $r['lat'], (float) $r['lon']);
                return ['ok' => true, 'kind' => $kind, 'ref' => $ref, 'name' => (string) $r['name'], 'sub' => Landmarks::kindLabel($r), 'url' => '/place/landmark.php?id=' . (int) $r['gnis_id'],
                        'lat' => $la, 'lon' => $lo, 'town' => (string) $r['near_town'], 'credit' => ''];
            }
            if ($kind === 'rec') {
                require_once __DIR__ . '/crawler/RecBulk.php';
                $r = RecBulk::get($pdo, $ref);
                if (!$r) return $no('No longer shown.');
                $name = RecBulk::displayName((string) $r['name']);
                if ($r['lat'] === null || $r['lon'] === null) return $no('It has no point on the map.', $name);
                [$la, $lo] = $pt((float) $r['lat'], (float) $r['lon']);
                return ['ok' => true, 'kind' => $kind, 'ref' => $ref, 'name' => $name, 'sub' => 'Outdoor place', 'url' => '/place/recreation.php?id=' . rawurlencode($ref),
                        'lat' => $la, 'lon' => $lo, 'town' => '', 'credit' => ''];
            }
            if ($kind === 'place') {
                require_once __DIR__ . '/Guides.php';
                $p = Guides::place($pdo, $ref);
                if (!$p) return $no('No longer shown.');
                if (($p['lat'] ?? null) === null) return $no('It has no point on the map.', (string) $p['name']);
                return ['ok' => true, 'kind' => $kind, 'ref' => $ref, 'name' => (string) $p['name'], 'sub' => 'Town or area', 'url' => (string) ($p['url'] ?? ''),
                        'lat' => (float) $p['lat'], 'lon' => (float) $p['lon'], 'town' => (string) $p['name'], 'credit' => ''];
            }
            // osm: our copy only, credited, never mixed into our records (0065)
            $st = $pdo->prepare('SELECT name, what, lat, lon, city FROM open_places WHERE osm_ref = ? LIMIT 1');
            $st->execute([$ref]); $r = $st->fetch(PDO::FETCH_ASSOC);
            if (!$r) return $no('No longer in our copy of OpenStreetMap.');
            [$la, $lo] = $pt((float) $r['lat'], (float) $r['lon']);
            return ['ok' => true, 'kind' => $kind, 'ref' => $ref, 'name' => (string) $r['name'], 'sub' => (string) ($r['what'] ?: 'Place'), 'url' => 'https://www.openstreetmap.org/' . $ref,
                    'lat' => $la, 'lon' => $lo, 'town' => (string) $r['city'], 'credit' => 'OpenStreetMap contributors'];
        } catch (Throwable $e) {
            error_log('trips resolve ' . $kind . ':' . $ref . ': ' . $e->getMessage());
            return $no('Couldn\'t be read just now.');
        }
    }

    /** '' when the page's place can go in a trip, else why not (greyed with it, never hidden). */
    public static function addable(PDO $pdo, string $kind, string $ref): string
    {
        $r = self::resolve($pdo, $kind, $ref);
        return $r['ok'] ? '' : (string) $r['why'];
    }

    /** A new trip's name: "Trip to Show Low". */
    public static function nameFor(array $stop): string
    {
        $at = trim((string) ($stop['town'] ?? '')) ?: trim((string) ($stop['name'] ?? ''));
        $at = trim(explode(',', $at)[0]);
        return $at !== '' ? mb_substr('Trip to ' . $at, 0, 120) : 'My trip';
    }

    private static function clean(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', strip_tags($name)));
        return mb_substr($name, 0, 120);
    }

    /** A person's trips, the newest first, with their stop counts; deleted ones (kept 30 days) under 'deleted'. */
    public static function mine(PDO $pdo, int $uid): array
    {
        $pdo->prepare('DELETE s FROM trip_stops s JOIN trips t ON t.id = s.trip_id WHERE t.user_id = ? AND t.deleted_at < NOW() - INTERVAL ' . self::KEEP_DELETED_DAYS . ' DAY')->execute([$uid]);
        $pdo->prepare('DELETE FROM trips WHERE user_id = ? AND deleted_at < NOW() - INTERVAL ' . self::KEEP_DELETED_DAYS . ' DAY')->execute([$uid]);
        $st = $pdo->prepare('SELECT t.id, t.name, t.tz, t.days, t.starts_on, t.deleted_at, t.created_at, t.updated_at, (SELECT COUNT(*) FROM trip_stops s WHERE s.trip_id = t.id) AS stops
                               FROM trips t WHERE t.user_id = ? ORDER BY t.updated_at DESC, t.id DESC LIMIT ' . (self::MAX_TRIPS + 50));
        $st->execute([$uid]);
        $out = ['trips' => [], 'deleted' => []];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $t['id'] = (int) $t['id']; $t['stops'] = (int) $t['stops'];
            $out[$t['deleted_at'] === null ? 'trips' : 'deleted'][] = $t;
        }
        return $out;
    }

    /** The trip, if it's this person's (and not deleted, unless $deleted). */
    public static function own(PDO $pdo, int $uid, int $tripId, bool $deleted = false): ?array
    {
        $st = $pdo->prepare('SELECT * FROM trips WHERE id = ? AND user_id = ?' . ($deleted ? '' : ' AND deleted_at IS NULL'));
        $st->execute([$tripId, $uid]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function create(PDO $pdo, int $uid, string $name, string $tz = ''): int
    {
        $n = $pdo->prepare('SELECT COUNT(*) FROM trips WHERE user_id = ? AND deleted_at IS NULL'); $n->execute([$uid]);
        if ((int) $n->fetchColumn() >= self::MAX_TRIPS) throw new RuntimeException('You have ' . self::MAX_TRIPS . ' trips: delete one to start another.');
        $name = self::clean($name) ?: 'My trip';
        $tz = preg_match('#^[A-Za-z_]+(/[A-Za-z_+-]+){0,2}$#', $tz) ? $tz : '';
        $pdo->prepare('INSERT INTO trips (user_id, name, tz, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())')->execute([$uid, $name, $tz]);
        return (int) $pdo->lastInsertId();
    }

    /** Adds a stop at the end of day 1. Returns its id. Refuses what can't go in a trip, with why. */
    public static function add(PDO $pdo, int $uid, int $tripId, string $kind, string $ref, int $position = -1, string $note = ''): int
    {
        if (!self::own($pdo, $uid, $tripId)) throw new RuntimeException('That trip isn\'t yours, or was deleted.');
        $r = self::resolve($pdo, $kind, $ref);
        if (!$r['ok']) throw new RuntimeException(($r['name'] !== '' ? $r['name'] . ': ' : '') . $r['why']);
        $st = $pdo->prepare('SELECT COUNT(*), COALESCE(MAX(position), -1) FROM trip_stops WHERE trip_id = ?'); $st->execute([$tripId]);
        [$n, $max] = $st->fetch(PDO::FETCH_NUM);
        if ((int) $n >= self::MAX_STOPS) throw new RuntimeException('A trip holds ' . self::MAX_STOPS . ' stops: start another for the rest.');
        $dup = $pdo->prepare('SELECT id FROM trip_stops WHERE trip_id = ? AND kind = ? AND ref = ? LIMIT 1'); $dup->execute([$tripId, $kind, $ref]);
        if ($d = $dup->fetchColumn()) return -(int) $d;   // already there: its id, negative
        if ($position < 0) $position = (int) $max + 1;
        else $pdo->prepare('UPDATE trip_stops SET position = position + 1 WHERE trip_id = ? AND position >= ?')->execute([$tripId, $position]);
        $pdo->prepare('INSERT INTO trip_stops (trip_id, day, position, kind, ref, note) VALUES (?, 1, ?, ?, ?, ?)')->execute([$tripId, $position, $kind, $ref, mb_substr(self::clean($note), 0, 500)]);
        $id = (int) $pdo->lastInsertId();
        self::touch($pdo, $tripId);
        return $id;
    }

    /** Removes a stop; returns what Undo needs (its trip, place in line, kind, ref, note). */
    public static function remove(PDO $pdo, int $uid, int $stopId): array
    {
        $st = $pdo->prepare('SELECT s.* FROM trip_stops s JOIN trips t ON t.id = s.trip_id WHERE s.id = ? AND t.user_id = ?');
        $st->execute([$stopId, $uid]); $s = $st->fetch(PDO::FETCH_ASSOC);
        if (!$s) throw new RuntimeException('That stop is already gone.');
        $pdo->prepare('DELETE FROM trip_stops WHERE id = ?')->execute([$stopId]);
        self::touch($pdo, (int) $s['trip_id']);
        return ['trip' => (int) $s['trip_id'], 'position' => (int) $s['position'], 'kind' => (string) $s['kind'], 'ref' => (string) $s['ref'], 'note' => (string) $s['note']];
    }

    /** The stops in order, each resolved today; a stop that can't be shown keeps its place, greyed with why. */
    public static function stops(PDO $pdo, int $tripId): array
    {
        $st = $pdo->prepare('SELECT id, day, position, kind, ref, note FROM trip_stops WHERE trip_id = ? ORDER BY day, position, id');
        $st->execute([$tripId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) $out[] = ['id' => (int) $s['id'], 'note' => (string) $s['note']] + self::resolve($pdo, (string) $s['kind'], (string) $s['ref']);
        return $out;
    }

    public static function rename(PDO $pdo, int $uid, int $tripId, string $name): string
    {
        if (!self::own($pdo, $uid, $tripId)) throw new RuntimeException('That trip isn\'t yours, or was deleted.');
        $name = self::clean($name);
        if ($name === '') throw new RuntimeException('Give it a name.');
        $pdo->prepare('UPDATE trips SET name = ?, updated_at = NOW() WHERE id = ?')->execute([$name, $tripId]);
        return $name;
    }

    /** Delete keeps it 30 days (Put back); restore puts it back. */
    public static function delete(PDO $pdo, int $uid, int $tripId, bool $restore = false): void
    {
        if (!self::own($pdo, $uid, $tripId, true)) throw new RuntimeException('That trip isn\'t yours.');
        $pdo->prepare('UPDATE trips SET deleted_at = ' . ($restore ? 'NULL' : 'NOW()') . ', updated_at = NOW() WHERE id = ?')->execute([$tripId]);
    }

    /**
     * The guest's trip on the device, moved into the account at sign-in (0174 §2): a new trip with the stops that can
     * still go in one, in order. Returns [trip id, stops added, stops left out with why].
     */
    public static function claim(PDO $pdo, int $uid, string $name, array $stops, string $tz = ''): array
    {
        $tripId = self::create($pdo, $uid, $name !== '' ? $name : 'My trip', $tz);
        $added = 0; $left = [];
        foreach (array_slice($stops, 0, self::MAX_STOPS) as $s) {
            $k = self::key((string) ($s['kind'] ?? ''), (string) ($s['ref'] ?? ''));
            if (!$k) continue;
            try { if (self::add($pdo, $uid, $tripId, $k[0], $k[1]) > 0) $added++; }
            catch (RuntimeException $e) { $left[] = $e->getMessage(); }
        }
        return [$tripId, $added, $left];
    }

    /** A ready-made journey's stops as trip stops (Make it my trip, 0174 §1): [[kind, ref], …]. */
    public static function fromJourney(array $stops): array
    {
        $out = [];
        foreach ($stops as $s) {
            $ref = (string) ($s['ref'] ?? '');
            if (preg_match('/^entity:(\d+)$/', $ref, $m)) $out[] = ['listing', $m[1]];
            elseif (preg_match('/^landmark:(\d+)$/', $ref, $m)) $out[] = ['lm', $m[1]];
            elseif (preg_match('#^osm:((node|way|relation)/\d+)$#', $ref, $m)) $out[] = ['osm', $m[1]];
            elseif (preg_match('/^rec:([\w-]+)$/', $ref, $m)) $out[] = ['rec', $m[1]];
        }
        return $out;
    }

    private static function touch(PDO $pdo, int $tripId): void
    {
        $pdo->prepare('UPDATE trips SET updated_at = NOW() WHERE id = ?')->execute([$tripId]);
    }
}
