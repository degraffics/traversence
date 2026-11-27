<?php
// app/lib/OpenNow.php — "open now" from known hours only, in the listing's own time (P10, reopened by Jason 2026-11-27:
// "Open now is marked and filtered only where hours are known (the owner's or OpenStreetMap's). Everything else says
// 'hours not known', never a guess"; the launch plan's C.8). One place for the three rules search, Get Local and the hub
// share, so they never disagree:
//   known()  hours count as known when the owner or our staff entered them (entity_metadata.source_type owner or admin,
//            or a locked row), or when OpenStreetMap gave them (a current hours fact with an openstreetmap.org source).
//            Staff hours count (settled by the team under P10's ruling, 2026-11-27, gap a). Hours read by the crawler
//            from a website, or found for a guide, are shown as text on the listing's page but never make it open or closed.
//   zone()   a listing's own time zone (CLAUDE.md "Times where they happened"): by its state (JourneyPlan::timezone),
//            and on Navajo Nation land in Arizona, daylight time (America/Denver), except the Hopi Reservation inside it,
//            which keeps Arizona time (settled by the team under P10's ruling, gap c).
//   now()    the day and minute it is now in that zone.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class OpenNow
{
    /** The words for a listing whose hours aren't known (P10's ruling). */
    public const UNKNOWN = 'Hours not known';

    private const PERSON = ['owner', 'admin'];

    /** @param int[] $ids  @return array<int,true> the listings among them whose hours are known */
    public static function known(PDO $pdo, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $out = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            $in = implode(',', $chunk);   // integers only
            try {
                foreach ($pdo->query("SELECT entity_id FROM entity_metadata WHERE meta_key = 'hours' AND entity_id IN ($in)
                                        AND (is_locked = 1 OR source_type IN ('owner', 'admin'))")->fetchAll(PDO::FETCH_COLUMN) as $id) $out[(int) $id] = true;
            } catch (Throwable $e) { error_log('OpenNow known: ' . $e->getMessage()); }
            try {
                foreach ($pdo->query('SELECT DISTINCT f.record_id FROM ' . Database::t('identity_facts') . ' f JOIN ' . Database::t('identity_fact_sources') . " s ON s.fact_id = f.id
                                       WHERE f.record_kind = 'listing' AND f.record_id IN ($in) AND f.fact = 'hours' AND f.is_current = 1
                                         AND f.status NOT IN ('rejected', 'replaced') AND (s.site IN ('openstreetmap.org', 'osm.org') OR s.site LIKE '%.openstreetmap.org')")->fetchAll(PDO::FETCH_COLUMN) as $id) $out[(int) $id] = true;
            } catch (Throwable $e) { /* before the identity tables: owner and staff hours only */ }
        }
        return $out;
    }

    /** Every listing whose hours are known (for Get Local's open-now filter over the whole directory). */
    public static function knownAll(PDO $pdo): array
    {
        try { $ids = $pdo->query("SELECT DISTINCT entity_id FROM entity_metadata WHERE meta_key = 'hours'")->fetchAll(PDO::FETCH_COLUMN); }
        catch (Throwable) { return []; }
        return self::known($pdo, $ids);
    }

    /** A listing's own time zone, from its state and point (Navajo and Hopi land in Arizona told apart). */
    public static function zone(PDO $pdo, string $state, ?float $lat, ?float $lon): string
    {
        require_once __DIR__ . '/JourneyPlan.php';
        $st = strtoupper(trim($state));
        $z = JourneyPlan::timezone(['state' => $st, 'lon' => $lon ?? -100]);
        if (($st === 'AZ' || ($st === '' && $z === 'America/Phoenix')) && $lat !== null && $lon !== null) {
            static $memo = [];
            $k = round($lat, 3) . ',' . round($lon, 3);
            if (!isset($memo[$k])) {
                require_once __DIR__ . '/Nations.php';
                $names = implode(' | ', Nations::allAt($pdo, $lat, $lon));
                $memo[$k] = preg_match('/hopi/i', $names) ? 'America/Phoenix' : (preg_match('/navajo/i', $names) ? 'America/Denver' : 'America/Phoenix');
            }
            return $memo[$k];
        }
        return $z;
    }

    /** [day 0 = Sunday … 6, minute after midnight] now in the zone. */
    public static function now(string $zone, ?int $at = null): array
    {
        try { $d = new DateTime('@' . ($at ?? time())); $d->setTimezone(new DateTimeZone($zone)); }
        catch (Throwable) { $d = new DateTime('@' . ($at ?? time())); }
        return [(int) $d->format('w'), (int) $d->format('G') * 60 + (int) $d->format('i')];
    }
}
