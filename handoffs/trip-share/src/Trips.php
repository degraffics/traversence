<?php
// app/lib/Trips.php: trips you can save (decisions/0174 §1, §2, release 2). A trip is a member's own list of stops in order
// (day 1 until the trip page, release 3, adds days). A stop is a kind and a ref, read fresh each time through resolve(), so
// the rules of the day apply: a place kept private, told only, closed for good or gone shows greyed with why, never with a
// point. Private by default: nobody but its owner reads a trip (sharing is release 4), and nothing here counts per person.
// A guest keeps one trip on the device (js/tv-trips.js); signing in moves it into the account (claim()).
// Release 3 (the trip page): days, the order within each, how long at each stop, each day's start, and plan(): every
// stop timed against its hours on the day (as ready-made journeys are, decisions/0146), the legs between as straight-line
// estimates ("about": routes come in release 5), in the time zone of the trip's first stop's place.
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
    public const MAX_DAYS = 14;
    /** How long at a stop when the person hasn't said: minutes, by kind. */
    public const STAY = ['listing' => 60, 'lm' => 60, 'rec' => 90, 'place' => 90, 'osm' => 45];
    /** The choices for a stay, in minutes. */
    public const STAYS = [15, 30, 45, 60, 90, 120, 180, 240, 360];

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
                $st = $pdo->prepare("SELECT e.id, e.name, e.category_id, e.latitude, e.longitude, e.is_suppressed, c.name AS category, ma.meta_value AS address, mc.meta_value AS city, mcl.meta_value AS closed,
                                            ms.meta_value AS state, mh.meta_value AS hours
                                       FROM entities e LEFT JOIN categories c ON c.id = e.category_id
                                       LEFT JOIN entity_metadata ma ON ma.entity_id = e.id AND ma.meta_key = 'address'
                                       LEFT JOIN entity_metadata mc ON mc.entity_id = e.id AND mc.meta_key = 'city'
                                       LEFT JOIN entity_metadata ms ON ms.entity_id = e.id AND ms.meta_key = 'state'
                                       LEFT JOIN entity_metadata mh ON mh.entity_id = e.id AND mh.meta_key = 'hours'
                                       LEFT JOIN entity_metadata mcl ON mcl.entity_id = e.id AND mcl.meta_key = 'closed'
                                      WHERE e.id = ? LIMIT 1");
                $st->execute([(int) $ref]); $r = $st->fetch(PDO::FETCH_ASSOC);
                if (!$r || (int) $r['is_suppressed'] === 1) return $no('No longer listed.') + ['private' => true];
                foreach (['address', 'city', 'state'] as $c) {   // entity_metadata holds JSON values ("Snowflake")
                    $v = $r[$c] === null ? null : json_decode((string) $r[$c], true);
                    $r[$c] = $r[$c] === null ? null : (is_scalar($v) ? (string) $v : (json_last_error() === JSON_ERROR_NONE ? '' : (string) $r[$c]));
                }
                $name = (string) $r['name'];
                require_once __DIR__ . '/ListingSafety.php'; require_once __DIR__ . '/ListingActions.php';
                if (ListingSafety::isConfidential($r['category_id'] !== null ? (int) $r['category_id'] : null, $name)) return $no('Its location is kept private: call them.', $name) + ['private' => true];
                if ($r['closed'] !== null) return $no('Marked closed: closed for good, or moved.', $name);   // the 'closed' mark (decisions/0072, 0164 R12)
                if (ListingActions::isMailbox((string) $r['address'])) return $no('Only a mailbox address: no place to drive to.', $name);
                if ($r['latitude'] === null || $r['longitude'] === null) return $no('It has no point on the map yet.', $name);
                [$la, $lo] = $pt((float) $r['latitude'], (float) $r['longitude']);
                $hrs = null;
                if ($r['hours'] !== null && $r['hours'] !== '') { $hv = json_decode((string) $r['hours'], true); require_once __DIR__ . '/Hours.php'; $hrs = Hours::parse(json_last_error() === JSON_ERROR_NONE ? $hv : (string) $r['hours']); }
                return ['ok' => true, 'kind' => $kind, 'ref' => $ref, 'name' => $name, 'sub' => preg_replace('/\s*\([^)]*$/', '', (string) $r['category']), 'url' => '/listing/view.php?id=' . (int) $r['id'],
                        'lat' => $la, 'lon' => $lo, 'town' => (string) $r['city'], 'credit' => '', 'state' => (string) $r['state'], 'hours' => $hrs, 'daylight' => false];
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
                        'lat' => $la, 'lon' => $lo, 'town' => (string) $r['near_town'], 'credit' => '', 'state' => (string) $r['state'], 'hours' => null, 'daylight' => true];
            }
            if ($kind === 'rec') {
                require_once __DIR__ . '/crawler/RecBulk.php';
                $r = RecBulk::get($pdo, $ref);
                if (!$r) return $no('No longer shown.');
                $name = RecBulk::displayName((string) $r['name']);
                if ($r['lat'] === null || $r['lon'] === null) return $no('It has no point on the map.', $name);
                [$la, $lo] = $pt((float) $r['lat'], (float) $r['lon']);
                return ['ok' => true, 'kind' => $kind, 'ref' => $ref, 'name' => $name, 'sub' => 'Outdoor place', 'url' => '/place/recreation.php?id=' . rawurlencode($ref),
                        'lat' => $la, 'lon' => $lo, 'town' => (string) ($r['city'] ?? ''), 'credit' => '', 'state' => (string) ($r['state'] ?? ''), 'hours' => null, 'daylight' => true];
            }
            if ($kind === 'place') {
                require_once __DIR__ . '/Guides.php';
                $p = Guides::place($pdo, $ref);
                if (!$p) return $no('No longer shown.');
                if (($p['lat'] ?? null) === null) return $no('It has no point on the map.', (string) $p['name']);
                return ['ok' => true, 'kind' => $kind, 'ref' => $ref, 'name' => (string) $p['name'], 'sub' => 'Town or area', 'url' => (string) ($p['url'] ?? ''),
                        'lat' => (float) $p['lat'], 'lon' => (float) $p['lon'], 'town' => (string) $p['name'], 'credit' => '', 'state' => (string) ($p['state'] ?? ''), 'hours' => null, 'daylight' => false];
            }
            // osm: our copy only, credited, never mixed into our records (0065)
            $st = $pdo->prepare('SELECT name, what, lat, lon, city, state, hours FROM open_places WHERE osm_ref = ? LIMIT 1');
            $st->execute([$ref]); $r = $st->fetch(PDO::FETCH_ASSOC);
            if (!$r) return $no('No longer in our copy of OpenStreetMap.');
            [$la, $lo] = $pt((float) $r['lat'], (float) $r['lon']);
            require_once __DIR__ . '/Hours.php';
            return ['ok' => true, 'kind' => $kind, 'ref' => $ref, 'name' => (string) $r['name'], 'sub' => (string) ($r['what'] ?: 'Place'), 'url' => 'https://www.openstreetmap.org/' . $ref,
                    'lat' => $la, 'lon' => $lo, 'town' => (string) $r['city'], 'credit' => 'OpenStreetMap contributors', 'state' => (string) $r['state'],
                    'hours' => (string) $r['hours'] !== '' ? Hours::parse((string) $r['hours']) : null, 'daylight' => false];
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

    public static function create(PDO $pdo, int $uid, string $name, string $tz = '', array $set = []): int
    {
        $n = $pdo->prepare('SELECT COUNT(*) FROM trips WHERE user_id = ? AND deleted_at IS NULL'); $n->execute([$uid]);
        if ((int) $n->fetchColumn() >= self::MAX_TRIPS) throw new RuntimeException('You have ' . self::MAX_TRIPS . ' trips: delete one to start another.');
        $name = self::clean($name) ?: 'My trip';
        $tz = preg_match('#^[A-Za-z_]+(/[A-Za-z_+-]+){0,2}$#', $tz) ? $tz : '';
        $pdo->prepare('INSERT INTO trips (user_id, name, tz, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())')->execute([$uid, $name, $tz]);
        $id = (int) $pdo->lastInsertId();
        if ($set) self::configure($pdo, $uid, $id, $set);
        return $id;
    }

    /** Adds a stop at the end of day 1. Returns its id. Refuses what can't go in a trip, with why. */
    public static function add(PDO $pdo, int $uid, int $tripId, string $kind, string $ref, int $position = -1, string $note = '', int $day = 0): int
    {
        $trip = self::own($pdo, $uid, $tripId);
        if (!$trip) throw new RuntimeException('That trip isn\'t yours, or was deleted.');
        $r = self::resolve($pdo, $kind, $ref);
        if (!$r['ok']) throw new RuntimeException(($r['name'] !== '' ? $r['name'] . ': ' : '') . $r['why']);
        if ($day < 1) {   // the last day that has stops (a new stop goes at the end of the trip)
            $d = $pdo->prepare('SELECT COALESCE(MAX(day), 1) FROM trip_stops WHERE trip_id = ?'); $d->execute([$tripId]); $day = (int) $d->fetchColumn();
        }
        $day = max(1, min(self::MAX_DAYS, $day));
        $st = $pdo->prepare('SELECT COUNT(*), (SELECT COALESCE(MAX(position), -1) FROM trip_stops WHERE trip_id = ? AND day = ?) FROM trip_stops WHERE trip_id = ?'); $st->execute([$tripId, $day, $tripId]);
        [$n, $max] = $st->fetch(PDO::FETCH_NUM);
        if ((int) $n >= self::MAX_STOPS) throw new RuntimeException('A trip holds ' . self::MAX_STOPS . ' stops: start another for the rest.');
        $dup = $pdo->prepare('SELECT id FROM trip_stops WHERE trip_id = ? AND kind = ? AND ref = ? LIMIT 1'); $dup->execute([$tripId, $kind, $ref]);
        if ($d = $dup->fetchColumn()) return -(int) $d;   // already there: its id, negative
        if ($position < 0) $position = (int) $max + 1;
        else $pdo->prepare('UPDATE trip_stops SET position = position + 1 WHERE trip_id = ? AND day = ? AND position >= ?')->execute([$tripId, $day, $position]);
        if ($day > (int) $trip['days']) $pdo->prepare('UPDATE trips SET days = ? WHERE id = ?')->execute([$day, $tripId]);
        $pdo->prepare('INSERT INTO trip_stops (trip_id, day, position, kind, ref, note) VALUES (?, ?, ?, ?, ?, ?)')->execute([$tripId, $day, $position, $kind, $ref, mb_substr(self::clean($note), 0, 500)]);
        $id = (int) $pdo->lastInsertId();
        self::touch($pdo, $tripId);
        self::counted($pdo, $kind, $ref);
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
        return ['trip' => (int) $s['trip_id'], 'day' => (int) $s['day'], 'position' => (int) $s['position'], 'kind' => (string) $s['kind'], 'ref' => (string) $s['ref'], 'note' => (string) $s['note'], 'stay' => $s['stay'] === null ? null : (int) $s['stay']];
    }

    /** The stops in order, each resolved today; a stop that can't be shown keeps its place, greyed with why. */
    public static function stops(PDO $pdo, int $tripId): array
    {
        $st = $pdo->prepare('SELECT * FROM trip_stops WHERE trip_id = ? ORDER BY day, position, id');
        $st->execute([$tripId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) $out[] = ['id' => (int) $s['id'], 'note' => (string) $s['note'], 'day' => (int) $s['day'],
            'stay' => isset($s['stay']) && $s['stay'] !== null ? (int) $s['stay'] : null] + self::resolve($pdo, (string) $s['kind'], (string) $s['ref']);
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

    /** Adds a list of stops at the end of an existing trip (Use these stops: a ready-made journey). [added, left out]. */
    public static function addMany(PDO $pdo, int $uid, int $tripId, array $stops, int $day = 0): array
    {
        $added = 0; $left = [];
        foreach (array_slice($stops, 0, self::MAX_STOPS) as $s) {
            $k = self::key((string) ($s['kind'] ?? $s[0] ?? ''), (string) ($s['ref'] ?? $s[1] ?? ''));
            if (!$k) continue;
            try { if (self::add($pdo, $uid, $tripId, $k[0], $k[1], -1, '', $day) > 0) $added++; }
            catch (RuntimeException $e) { $left[] = $e->getMessage(); }
        }
        return [$added, $left];
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

    /** Changed: its time, and its zone from its first stop's place (CLAUDE.md "Times where they happened"). */
    private static function touch(PDO $pdo, int $tripId): void
    {
        $tz = '';
        $st = $pdo->prepare('SELECT kind, ref FROM trip_stops WHERE trip_id = ? ORDER BY day, position, id LIMIT 5'); $st->execute([$tripId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) { $r = self::resolve($pdo, (string) $s['kind'], (string) $s['ref']); if ($r['ok']) { $tz = self::tzOf($r); break; } }
        if ($tz !== '') $pdo->prepare('UPDATE trips SET tz = ?, updated_at = NOW() WHERE id = ?')->execute([$tz, $tripId]);
        else $pdo->prepare('UPDATE trips SET updated_at = NOW() WHERE id = ?')->execute([$tripId]);
    }

    /**
     * Added to a trip, counted for the place (0174 §1: "Businesses and places get counts only: "added to 12 trips this
     * month", never who (0058 §13)"). A business: `trip` in listing_actions (Business Insights, as 0094's `save`); any
     * other place: PlatformCounts' `trip` (Admin → Insights). Not the team, not a business's own people (0087), not bots.
     */
    private static function counted(PDO $pdo, string $kind, string $ref): void
    {
        try {
            require_once __DIR__ . '/PlatformCounts.php';
            if (PlatformCounts::isBot() || PlatformCounts::isTeam($pdo)) return;
            if ($kind === 'listing') {
                if (PlatformCounts::runsListing($pdo, (int) $ref)) return;
                require_once __DIR__ . '/ListingActions.php';
                ListingActions::count($pdo, (int) $ref, 'trip');
            } else PlatformCounts::add($pdo, 'trip', $kind . ':' . $ref);
        } catch (Throwable $e) { error_log('trips count: ' . $e->getMessage()); }
    }

    /** A place's count shows at 0 too (Jason, 2026-11-19: "the social numbers should always be present"; 0058 §20 note). */
    public const SHOW_ZERO_TRIPS = true;

    /**
     * "Added to N trips this month" for a place, shown on its page (0174 §1; Jason, 2026-11-20, "Also on the place's
     * page"): counts only, never who (0058 §13). A business's stays in its own insights.
     */
    public static function addedThisMonth(PDO $pdo, string $kind, string $ref): int
    {
        if ($kind === 'listing' || !self::key($kind, $ref)) return 0;
        try { require_once __DIR__ . '/PlatformCounts.php'; return (int) PlatformCounts::total($pdo, 'trip', max(0, (int) date('j') - 1), $kind . ':' . $ref)['n']; }
        catch (Throwable $e) { return 0; }
    }

    /**
     * Share a link (0174 §1: "anyone with the link can view it, not edit it. The link can be turned off any time"). On:
     * a new link (a link turned off stays dead). Off: no link. Returns the link's address, or ''.
     */
    public static function share(PDO $pdo, int $uid, int $tripId, bool $on): string
    {
        if (!self::own($pdo, $uid, $tripId)) throw new RuntimeException('That trip isn\'t yours, or was deleted.');
        $tok = $on ? bin2hex(random_bytes(16)) : null;
        $pdo->prepare('UPDATE trips SET shared = ?, share_token = ?, updated_at = NOW() WHERE id = ?')->execute([$on ? 1 : 0, $tok, $tripId]);
        return $tok ? 'https://traversence.com/trip/?t=' . $tok : '';
    }

    /** The trip a shared link opens: shared, not deleted. */
    public static function byToken(PDO $pdo, string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
        $st = $pdo->prepare('SELECT * FROM trips WHERE share_token = ? AND shared = 1 AND deleted_at IS NULL LIMIT 1');
        $st->execute([$token]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ---- Personal Guides (release 4; 0174 §1: "public, credited, and checked like a journey before it shows") -------------

    public const GUIDE_STATUSES = ['published' => 'Published', 'held' => 'Held for a check', 'hidden' => 'Hidden by our team', 'withdrawn' => 'Withdrawn by you'];

    public static function guidesReady(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try { $pdo->query('SELECT 1 FROM personal_guides LIMIT 0'); return $ok = true; } catch (Throwable $e) { return $ok = false; }
    }

    /** A trip's guide, if it has one. */
    public static function guideOf(PDO $pdo, int $tripId): ?array
    {
        if (!self::guidesReady($pdo)) return null;
        $st = $pdo->prepare('SELECT * FROM personal_guides WHERE trip_id = ? LIMIT 1'); $st->execute([$tripId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** A guide anyone may read: published, its trip not deleted. */
    public static function guide(PDO $pdo, int $id): ?array
    {
        if (!self::guidesReady($pdo)) return null;
        $st = $pdo->prepare("SELECT g.* FROM personal_guides g JOIN trips t ON t.id = g.trip_id WHERE g.id = ? AND g.status = 'published' AND t.deleted_at IS NULL LIMIT 1");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Publish a trip as a Personal Guide, or change its words: the journey's checks (Journeys::save): an account that
     * isn't limited (Standing, 0079), a contributor (0056/0058: 18 or older, a public profile, the guidelines), a title, a
     * few sentences (as a journey's story before it publishes), and the automated check, which holds it (not shown) with
     * why. Returns the guide.
     */
    public static function publishGuide(PDO $pdo, int $uid, int $tripId, string $title, string $intro): array
    {
        $trip = self::own($pdo, $uid, $tripId);
        if (!$trip) throw new RuntimeException('That trip isn\'t yours, or was deleted.');
        if (!self::guidesReady($pdo)) throw new RuntimeException('Personal Guides arrive with the next site update.');
        require_once __DIR__ . '/Standing.php'; require_once __DIR__ . '/Journeys.php'; require_once __DIR__ . '/ListingSafety.php';
        try { Standing::guard($pdo, $uid); } catch (InvalidArgumentException $e) { throw new RuntimeException($e->getMessage()); }
        if (!Journeys::contributor($pdo, $uid)) throw new RuntimeException('Become a contributor first (Your journeys → Become a contributor): a Personal Guide is published like a journey.');
        $title = mb_substr(self::clean($title), 0, 160);
        $intro = trim(str_replace("\r", '', strip_tags($intro)));
        if ($title === '') throw new RuntimeException('Give your guide a title.');
        if (mb_strlen($intro) < 40) throw new RuntimeException('Say a little about it (a few sentences at least) before publishing.');
        $public = array_filter(self::stops($pdo, $tripId), fn ($s) => $s['ok']);
        if (!$public) throw new RuntimeException('Add a place it can show first: a guide needs at least one stop.');
        $held = ListingSafety::contentProblem($title, $intro);
        $cur = self::guideOf($pdo, $tripId);
        if ($cur && $cur['status'] === 'hidden') throw new RuntimeException('Our team hid this guide' . ($cur['hidden_reason'] !== '' ? ': ' . $cur['hidden_reason'] : '') . '. Ask our team at support@traversence.com to look again.');
        $status = $held ? 'held' : 'published';
        if ($cur) $pdo->prepare('UPDATE personal_guides SET title = ?, intro = ?, status = ?, hidden_reason = ?, published_at = COALESCE(published_at, ' . ($held ? 'NULL' : 'NOW()') . '), updated_at = NOW() WHERE id = ?')
                     ->execute([$title, $intro, $status, $held ? 'Held by the automated check: ' . $held : '', (int) $cur['id']]);
        else {
            $pdo->prepare('INSERT INTO personal_guides (trip_id, user_id, title, intro, status, hidden_reason, published_at) VALUES (?, ?, ?, ?, ?, ?, ' . ($held ? 'NULL' : 'NOW()') . ')')
                ->execute([$tripId, $uid, $title, $intro, $status, $held ? 'Held by the automated check: ' . $held : '']);
            $pdo->prepare('UPDATE trips SET guide_id = ? WHERE id = ?')->execute([(int) $pdo->lastInsertId(), $tripId]);
        }
        return self::guideOf($pdo, $tripId);
    }

    /** The owner takes it down (Withdraw) or puts it back up (a way back for every action). */
    public static function withdrawGuide(PDO $pdo, int $uid, int $tripId, bool $back = false): void
    {
        $g = self::own($pdo, $uid, $tripId) ? self::guideOf($pdo, $tripId) : null;
        if (!$g) throw new RuntimeException('That trip has no guide.');
        if ($g['status'] === 'hidden') throw new RuntimeException('Our team hid this guide. Ask our team at support@traversence.com to look again.');
        if ($back) { self::publishGuide($pdo, $uid, $tripId, (string) $g['title'], (string) $g['intro']); return; }
        $pdo->prepare("UPDATE personal_guides SET status = 'withdrawn', updated_at = NOW() WHERE id = ?")->execute([(int) $g['id']]);
    }

    /** Staff hide or show a guide (Admin → Journeys, as journeys are hidden: Journeys::setHidden). */
    public static function hideGuide(PDO $pdo, int $id, bool $hide, string $why): void
    {
        $pdo->prepare("UPDATE personal_guides SET status = ?, hidden_reason = ?, updated_at = NOW() WHERE id = ? AND status <> 'withdrawn'")
            ->execute([$hide ? 'hidden' : 'published', $hide ? mb_substr($why, 0, 300) : '', $id]);
    }

    /** Guides for Admin → Journeys: the newest first, with their owners. */
    public static function guidesForStaff(PDO $pdo, int $limit = 100): array
    {
        if (!self::guidesReady($pdo)) return [];
        return $pdo->query('SELECT g.*, t.name AS trip_name, t.deleted_at FROM personal_guides g JOIN trips t ON t.id = g.trip_id ORDER BY g.updated_at DESC LIMIT ' . max(1, min(500, $limit)))->fetchAll(PDO::FETCH_ASSOC);
    }

    /** A stop's time zone: its place's (decisions/0146's rule, JourneyPlan::timezone). */
    public static function tzOf(array $stop): string
    {
        if (($stop['lat'] ?? null) === null) return '';
        require_once __DIR__ . '/JourneyPlan.php';
        return JourneyPlan::timezone(['state' => (string) ($stop['state'] ?? ''), 'lon' => (float) $stop['lon']]);
    }

    // ---- the trip page (release 3) ---------------------------------------------------------------------------------------

    /** Moves a stop to a day and a place in that day's order (0-based). */
    public static function move(PDO $pdo, int $uid, int $stopId, int $day, int $position): void
    {
        $st = $pdo->prepare('SELECT s.trip_id, s.day, t.days FROM trip_stops s JOIN trips t ON t.id = s.trip_id WHERE s.id = ? AND t.user_id = ? AND t.deleted_at IS NULL');
        $st->execute([$stopId, $uid]); $s = $st->fetch(PDO::FETCH_ASSOC);
        if (!$s) throw new RuntimeException('That stop is gone, or the trip isn\'t yours.');
        $trip = (int) $s['trip_id']; $day = max(1, min(self::MAX_DAYS, $day));
        $ids = $pdo->prepare('SELECT id FROM trip_stops WHERE trip_id = ? AND day = ? AND id <> ? ORDER BY position, id'); $ids->execute([$trip, $day, $stopId]);
        $order = array_map('intval', $ids->fetchAll(PDO::FETCH_COLUMN));
        array_splice($order, max(0, min(count($order), $position)), 0, [$stopId]);
        $up = $pdo->prepare('UPDATE trip_stops SET day = ?, position = ? WHERE id = ?');
        foreach ($order as $i => $id) $up->execute([$day, $i, $id]);
        if ($day > (int) $s['days']) $pdo->prepare('UPDATE trips SET days = ? WHERE id = ?')->execute([$day, $trip]);
        self::touch($pdo, $trip);
    }

    /** The trip's start date, its days and when each day starts. Fewer days than its stops use moves those stops to the last day. */
    public static function configure(PDO $pdo, int $uid, int $tripId, array $set): array
    {
        $t = self::own($pdo, $uid, $tripId);
        if (!$t) throw new RuntimeException('That trip isn\'t yours, or was deleted.');
        if (array_key_exists('starts_on', $set)) {
            $d = trim((string) $set['starts_on']);
            if ($d !== '' && !(preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4)))) throw new RuntimeException('Pick a date.');
            $pdo->prepare('UPDATE trips SET starts_on = ? WHERE id = ?')->execute([$d === '' ? null : $d, $tripId]);
        }
        if (array_key_exists('start_min', $set)) {
            $m = (int) $set['start_min'];
            if ($m < 0 || $m > 1380) throw new RuntimeException('Pick a time.');
            $pdo->prepare('UPDATE trips SET start_min = ? WHERE id = ?')->execute([$m, $tripId]);
        }
        if (array_key_exists('days', $set)) {
            $n = max(1, min(self::MAX_DAYS, (int) $set['days']));
            $pdo->prepare('UPDATE trips SET days = ? WHERE id = ?')->execute([$n, $tripId]);
            $late = $pdo->prepare('SELECT id FROM trip_stops WHERE trip_id = ? AND day > ? ORDER BY day, position, id'); $late->execute([$tripId, $n]);
            $moved = array_map('intval', $late->fetchAll(PDO::FETCH_COLUMN));
            if ($moved) {   // nothing is dropped: the stops of the days taken away go to the end of the last day
                $mx = $pdo->prepare('SELECT COALESCE(MAX(position), -1) FROM trip_stops WHERE trip_id = ? AND day = ?'); $mx->execute([$tripId, $n]); $p = (int) $mx->fetchColumn();
                $up = $pdo->prepare('UPDATE trip_stops SET day = ?, position = ? WHERE id = ?');
                foreach ($moved as $id) $up->execute([$n, ++$p, $id]);
            }
        }
        if (array_key_exists('place_key', $set) && preg_match('/^((hub|geo_hub|cluster|town|sub):\d+)?$/', (string) $set['place_key']))
            $pdo->prepare('UPDATE trips SET place_key = ? WHERE id = ?')->execute([(string) $set['place_key'], $tripId]);
        self::touch($pdo, $tripId);
        return self::own($pdo, $uid, $tripId);
    }

    /** How long at a stop, in minutes (null: the usual for its kind). */
    public static function setStay(PDO $pdo, int $uid, int $stopId, ?int $minutes): void
    {
        if ($minutes !== null && !in_array($minutes, self::STAYS, true)) throw new RuntimeException('Pick how long.');
        $st = $pdo->prepare('SELECT s.trip_id FROM trip_stops s JOIN trips t ON t.id = s.trip_id WHERE s.id = ? AND t.user_id = ? AND t.deleted_at IS NULL');
        $st->execute([$stopId, $uid]); $trip = $st->fetchColumn();
        if (!$trip) throw new RuntimeException('That stop is gone, or the trip isn\'t yours.');
        $pdo->prepare('UPDATE trip_stops SET stay = ? WHERE id = ?')->execute([$minutes, $stopId]);
        self::touch($pdo, (int) $trip);
    }

    /**
     * The trip, day by day: each stop with its arrival and leaving (minutes after midnight), the leg before it ("about":
     * a straight-line estimate), and whether it's open then (with a date; a stop with no hours known says so; outdoor
     * places: daylight). [days => [[day, date|null, start, stops, miles, minutes, ends]], tz, miles, minutes].
     */
    public static function plan(PDO $pdo, array $trip, ?array $stops = null): array
    {
        require_once __DIR__ . '/Hours.php'; require_once __DIR__ . '/TravelLegs.php';
        $stops = $stops ?? self::stops($pdo, (int) $trip['id']);
        $tz = (string) $trip['tz'] !== '' ? (string) $trip['tz'] : 'America/Phoenix';
        try { $zone = new DateTimeZone($tz); } catch (Throwable $e) { $zone = new DateTimeZone('America/Phoenix'); $tz = 'America/Phoenix'; }
        $nDays = max(1, (int) $trip['days'], ...array_map(fn ($s) => (int) $s['day'], $stops ?: [['day' => 1]]));
        $start = (int) ($trip['start_min'] ?? 540);
        $out = ['days' => [], 'tz' => $tz, 'miles' => 0.0, 'minutes' => 0.0];
        for ($d = 1; $d <= $nDays; $d++) {
            $date = !empty($trip['starts_on']) ? (new DateTimeImmutable((string) $trip['starts_on'], $zone))->modify('+' . ($d - 1) . ' days') : null;
            $dow = $date ? (int) $date->format('w') : null;
            $t = $start; $prev = null; $day = ['day' => $d, 'date' => $date ? $date->format('Y-m-d') : null, 'start' => $start, 'stops' => [], 'miles' => 0.0, 'minutes' => 0.0, 'ends' => null];
            foreach ($stops as $s) {
                if ((int) $s['day'] !== $d) continue;
                if (!$s['ok']) { $day['stops'][] = $s + ['arrive' => null]; continue; }
                $leg = null;
                if ($prev) {
                    $line = TravelLegs::miles((float) $prev['lat'], (float) $prev['lon'], (float) $s['lat'], (float) $s['lon']);
                    $mode = TravelLegs::modeFor($line);
                    $e = TravelLegs::estimate($line, $mode);
                    $leg = ['minutes' => (int) round($e['minutes']), 'miles' => round($e['meters'] / 1609.34, 1), 'mode' => $mode, 'about' => true];
                    $t += $leg['minutes']; $day['miles'] += $leg['miles']; $day['minutes'] += $leg['minutes'];
                }
                $stay = $s['stay'] ?? self::STAY[$s['kind']] ?? 60;
                $open = null; $why = '';
                if ($date !== null) {
                    if ($s['hours'] === null && !empty($s['daylight'])) {
                        $sun = date_sun_info($date->setTime(12, 0)->getTimestamp(), (float) $s['lat'], (float) $s['lon']);
                        $min = function ($ts) use ($zone) { if (!is_int($ts)) return null; $x = (new DateTimeImmutable('@' . $ts))->setTimezone($zone); return (int) $x->format('G') * 60 + (int) $x->format('i'); };
                        $rise = $min($sun['sunrise'] ?? null) ?? 390; $set = $min($sun['sunset'] ?? null) ?? 1110;
                        $open = $t >= $rise && $t + $stay <= $set;
                        $why = $open ? 'In daylight' : ($t < $rise ? 'Before sunrise (' . Hours::clock($rise) . ')' : 'After dark: sunset ' . Hours::clock($set));
                    } elseif ($s['hours'] !== null) {
                        $a = Hours::openAt($s['hours'], $dow, $t % 1440); $b = Hours::openAt($s['hours'], $dow, ($t + max(0, $stay - 10)) % 1440);
                        $open = $a === true && $b !== false ? true : ($a === null ? null : false);
                        if ($open === false) { $stt = Hours::status($s['hours'], $dow, $t % 1440); $why = 'Closed when you\'d arrive' . ($stt['text'] !== '' ? ': ' . preg_replace('/^Closed · /', '', $stt['text']) : ''); }
                        elseif ($open) $why = 'Open then';
                    }
                    if ($open === null && $why === '') $why = 'Hours not known: check before you go';
                }
                $day['stops'][] = $s + ['arrive' => $t, 'leave' => $t + $stay, 'stay_min' => $stay, 'leg' => $leg, 'open' => $open, 'hours_note' => $why, 'tz' => self::tzOf($s)];
                $t += $stay; $prev = $s;
            }
            $day['ends'] = $prev ? $t : null;
            $out['miles'] += $day['miles']; $out['minutes'] += $day['minutes'];
            $out['days'][] = $day;
        }
        return $out;
    }
}
