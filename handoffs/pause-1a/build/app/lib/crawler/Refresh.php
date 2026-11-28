<?php

declare(strict_types=1);

require_once __DIR__ . '/IntakeStager.php';
require_once __DIR__ . '/CrawlQueue.php';
require_once __DIR__ . '/../CrawlerIngest.php';
require_once __DIR__ . '/../PlaceScope.php';
require_once __DIR__ . '/ListingExtractor.php';
require_once __DIR__ . '/AutoImport.php';   // the pause (2026-11-28): About text and hours held for Review

/**
 * api/lib/crawler/Refresh.php — refresh live listings from their own websites (decisions/0045).
 *
 * The worker takes a few public listings that have a website, reads a handful of pages there, and sends
 * back what it found. The site only fills fields that are empty and came from no person (never overwrites,
 * never touches locked fields), and stages any other locations it lists, so they go through the ADR 0044
 * guardrails like every other find. How often follows how sure we are (decisions/0133, cadence by band): every 30 days
 * below 51, every 90 days at 51 and up; never longer than 90. The least sure go first.
 *
 * A refresh counts when the worker reports back (KEY); taking one only holds it for two hours (LEASE), so a run that
 * dies takes it again. Each refresh is also the listing's closed check (decisions/0133): a website that stops
 * working, twice at least three days apart, comes off the listing when the crawler gave it (kept as website_dead) or
 * goes to Review when a person did; a site that says the business has closed marks it Closed (still listed, "Reopened?
 * Tell us", decisions/0072) when nobody has claimed it, else goes to Review; a site that says the business moved goes
 * to Review with the new street (moved()). A lease that ran out with no report is not a try (review B5): it's leased
 * again, and only counted (crawler_refresh_abandoned) for the crawler's report.
 *
 * A changed phone or hours (review E24, decisions/0160 W2): the worker also sends what the site plainly says now
 * (`seen`: one phone, the hours it reads). When that differs from the listing, it goes to Review as a fix with the new
 * value filled in, never written by itself (a site's number can be a head office's, and hours read off a page can be a
 * season's); the same value is asked about once (kept as site_said).
 */
final class Refresh
{
    private const EVERY_DAYS = 30;        // below 51 (red and orange), or with no score yet
    private const EVERY_DAYS_SURE = 90;   // 51 and up: gold and green, and never longer
    private const RETRY_DAYS = 3;   // a website that didn't answer is tried again this soon
    private const KEY = 'crawler_refreshed_at';
    private const LEASE = 'crawler_refresh_lease';
    private const CHECK = 'site_check';
    private const ABANDON_MAX = 5;   // leases in a row never reported before it waits its normal turn (review B5)
    /** "We have closed", said by the business about itself (not one branch of several: see closedLine()). */
    /** "Possibly closed" (one source says so) is kept as closed_signal: shown, scored, never a closing on its own. */
    private const CLOSED = '/\b(permanently\s+closed|closed\s+permanently|closed\s+for\s+good|(we|we\s+have|we\'ve)\s+(permanently\s+)?(closed|shut)\s+(our|the)\s+(doors|business|shop|store|restaurant|office)|(have|has)\s+(permanently\s+)?ceased\s+(operations|trading)|is\s+no\s+longer\s+in\s+business|out\s+of\s+business|closing\s+(our|its)\s+doors\s+for\s+good)\b/i';

    /** "We've moved", said by the business about itself. */
    private const MOVED = '/\b((we|we\s+have|we\'ve|we\s+are|we\'re|has|have)\s+(now\s+)?(moved|relocated)|(our|the)\s+new\s+(location|address|home|shop|store|office)\s+is|(now|newly)\s+located\s+at|new\s+location\s*:|find\s+us\s+at\s+our\s+new|moved\s+to\s+a\s+new\s+(location|home|space|building))\b/i';
    /** A street address in a sentence: number, name and kind of street. */
    private const STREET = '/\b(\d{1,6}\s+(?:[NSEW]\.?\s+)?(?:[A-Z0-9][\w\'.-]*\s+){0,4}(?:St|Street|Ave|Avenue|Rd|Road|Blvd|Boulevard|Hwy|Highway|Dr|Drive|Ln|Lane|Way|Pl|Place|Ct|Court|Pkwy|Parkway|Loop|Trail|Trl|Circle|Cir|Route|Rte|US\s*\d+|SR\s*\d+)\b\.?)/u';

    /** The next listings to refresh; marked now so two runs don't take the same one. */
    public static function lease(PDO $pdo, int $limit): array
    {
        $limit = max(1, min(10, $limit));
        $pub = implode(',', array_map(fn ($s) => $pdo->quote($s), PlaceScope::PUBLIC_STATUSES));
        // how often follows how sure we are: monthly below 51, quarterly at 51 and up (the least sure first)
        require_once __DIR__ . '/../ListingConfidence.php';
        $byBand = ListingConfidence::ready($pdo);
        $conf = $byBand ? ' LEFT JOIN listing_confidence lc ON lc.entity_id = e.id' : '';
        $every = $byBand ? '(CASE WHEN lc.score >= 51 THEN ' . self::EVERY_DAYS_SURE . ' ELSE ' . self::EVERY_DAYS . ' END)' : (string) self::EVERY_DAYS;
        $least = $byBand ? 'COALESCE(lc.score, 0),' : '';
        // the bands count W2's switched-on time only (decisions/0164 §6.1, X1): a pause doesn't make every listing fall due at once
        require_once __DIR__ . '/../Clocks.php';
        $c30 = $pdo->quote(Clocks::sqlCutoff($pdo, 'W2', self::EVERY_DAYS)); $c90 = $pdo->quote(Clocks::sqlCutoff($pdo, 'W2', self::EVERY_DAYS_SURE)); $c3 = $pdo->quote(Clocks::sqlCutoff($pdo, 'W2', self::RETRY_DAYS));
        $due = $byBand ? "(CASE WHEN lc.score >= 51 THEN $c90 ELSE $c30 END)" : $c30;
        $st = $pdo->query("SELECT e.id, e.name, e.primary_zip, w.meta_value AS website, l.updated_at AS leased, r.updated_at AS done
                             FROM entities e
                             JOIN entity_metadata w ON w.entity_id = e.id AND w.meta_key = 'website'
                        LEFT JOIN entity_metadata r ON r.entity_id = e.id AND r.meta_key = '" . self::KEY . "'
                        LEFT JOIN entity_metadata l ON l.entity_id = e.id AND l.meta_key = '" . self::LEASE . "'
                        LEFT JOIN entity_metadata c ON c.entity_id = e.id AND c.meta_key = '" . self::CHECK . "'
                        LEFT JOIN entity_metadata cl ON cl.entity_id = e.id AND cl.meta_key = 'closed'" . $conf . "
                            WHERE e.is_suppressed = 0 AND e.status IN ($pub) AND cl.id IS NULL
                              AND (l.id IS NULL OR l.updated_at < NOW() - INTERVAL 2 HOUR)
                              AND (r.meta_value IS NULL OR r.updated_at < $due
                                   OR (c.id IS NOT NULL AND r.updated_at < $c3))
                            ORDER BY c.id IS NULL, r.meta_value IS NOT NULL, $least r.updated_at, e.id LIMIT $limit");
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $site = json_decode((string) $r['website'], true);
            // a website written without its https:// ("www.example.com") is read as one; anything else isn't a website to read,
            // and waits its normal turn (review §3: rows skipped without a mark took every slot, forever)
            if (is_string($site) && !preg_match('#^https?://#i', $site) && preg_match('#^(www\.)?[a-z0-9-]+(\.[a-z0-9-]+)+(/\S*)?$#i', trim($site))) $site = 'https://' . trim($site);
            if (!is_string($site) || !preg_match('#^https?://#i', $site)) { CrawlerIngest::upsertMetadataKey($pdo, (int) $r['id'], self::KEY, date('c')); continue; }
            // taken before and never reported (the run ran out of time, or stopped): not tried, never "unreachable"
            // (review B5, 2026-11-13). Nothing is counted against its website; it's simply leased again now.
            if ($r['leased'] !== null && ($r['done'] === null || $r['done'] < $r['leased']) && !self::abandoned($pdo, (int) $r['id'])) continue;
            CrawlerIngest::upsertMetadataKey($pdo, (int) $r['id'], self::LEASE, date('c'));
            $l = IntakeStager::existingListing($pdo, (int) $r['id']);
            $has = fn (string $k) => !empty($l['meta'][$k]['value']);
            $out[] = ['entity_id' => (int) $r['id'], 'name' => (string) $r['name'], 'website' => $site, 'zip' => (string) $r['primary_zip'],
                      'address' => (string) ($l['meta']['address']['value'] ?? ''),
                      'missing' => array_values(array_filter(['description', 'hours', 'phone', 'offerings'], fn ($k) => !$has($k)))];
        }
        return $out;
    }

    /**
     * A lease that ran out with no report: counted for the crawler's report (how often runs abandon work), never against
     * its website. Returns whether to lease it again now; after ABANDON_MAX in a row it waits its normal turn instead
     * (so one listing can't hold the front of the queue), still with nothing marked against it.
     */
    private static function abandoned(PDO $pdo, int $id): bool
    {
        try {
            $st = $pdo->prepare("SELECT meta_value FROM entity_metadata WHERE entity_id = ? AND meta_key = 'crawler_refresh_abandoned'"); $st->execute([$id]);
            $was = json_decode((string) $st->fetchColumn(), true) ?: [];
            $n = (int) ($was['n'] ?? 0) + 1;
            CrawlerIngest::upsertMetadataKey($pdo, $id, 'crawler_refresh_abandoned', ['n' => $n, 'total' => (int) ($was['total'] ?? 0) + 1, 'last' => date('c')]);
            if ($n < self::ABANDON_MAX) return true;
            CrawlerIngest::upsertMetadataKey($pdo, $id, 'crawler_refresh_abandoned', ['n' => 0, 'total' => (int) ($was['total'] ?? 0) + 1, 'last' => date('c')]);
            CrawlerIngest::upsertMetadataKey($pdo, $id, self::KEY, date('c'));   // its normal turn: 30 or 90 days, nothing against its website
            $pdo->prepare('DELETE FROM entity_metadata WHERE entity_id = ? AND meta_key = ?')->execute([$id, self::LEASE]);
            return false;
        } catch (Throwable $x) { error_log('refresh abandoned ' . $id . ': ' . $x->getMessage()); return true; }
    }

    /**
     * What the worker found for one listing.
     * found: {description?, hours?: [], phone?, offerings?: []}; locations: intake.v1 candidates for other places;
     * site: {status: ok|unreachable|dead, closed: the sentence that says it closed} (older workers send none: ok).
     * @return array{filled:string[], staged:int, auto_imported:int, site:string}
     */
    public static function complete(PDO $pdo, int $entityId, array $found, array $locations, string $website, array $site = []): array
    {
        $l = IntakeStager::existingListing($pdo, $entityId) ?? throw new InvalidArgumentException('No such listing.');
        if (($site['status'] ?? '') === 'skipped') {   // the worker couldn't read it this run (robots.txt, its budget): let it go, no mark
            $pdo->prepare('DELETE FROM entity_metadata WHERE entity_id = ? AND meta_key = ?')->execute([$entityId, self::LEASE]);
            return ['filled' => [], 'staged' => 0, 'auto_imported' => 0, 'site' => 'skipped'];
        }
        CrawlerIngest::upsertMetadataKey($pdo, $entityId, self::KEY, date('c'));   // done: the next one in 30 or 90 days, by its band
        if (($site['status'] ?? '') === 'blocked') {   // robots.txt asks us not to: held for a person's Reactivate (P1-2), never retried by itself
            require_once __DIR__ . '/../EngineCase.php'; EngineCase::blockedSite($pdo, $website, 'its robots.txt asks us not to read it', 'keeping listings current');
            return ['filled' => [], 'staged' => 0, 'auto_imported' => 0, 'site' => 'not read: its robots.txt asks us not to (held for a person)'];
        }
        $status = in_array($site['status'] ?? 'ok', ['ok', 'unreachable', 'dead', 'archived'], true) ? (string) ($site['status'] ?? 'ok') : 'ok';
        // archived (worker 2.11.0; decisions/0163's source map, direct → Common Crawl): it didn't load for us, and Common
        // Crawl's newest crawl has a copy; at, its capture time (UTC, YYYYMMDDhhmmss)
        $arch = $status === 'archived' ? self::archiveAt((array) ($site['archive'] ?? [])) : null;
        $said = self::siteResult($pdo, $entityId, $status, (string) ($site['closed'] ?? ''), $website, $l, $arch);
        if ($status === 'ok' && $said === 'ok' && trim((string) ($site['moved'] ?? '')) !== '') $said = self::moved($pdo, $entityId, (string) $site['moved'], $website, $l);
        if ($status === 'archived' && $arch !== null) {   // only an empty description, from the copy, marked as the copy's (never hours, a phone or what changed)
            $d = mb_substr(trim(strip_tags((string) ($found['description'] ?? ''))), 0, IntakeStager::FIELD_RULES['description']['max']);
            $now = $l['meta']['description'] ?? null;
            if ($d !== '' && !($now !== null && ($now['locked'] || !empty($now['value']))) && AutoImport::pausedWhy() !== null) {
                AutoImport::holdForReview($pdo, $entityId, ['description' => $d], 'crawler-refresh-paused', 'Common Crawl\'s copy of its own website (' . $website . ') gives');
                return ['filled' => [], 'staged' => 0, 'auto_imported' => 0, 'site' => $said . '; paused: its About held for Review'];
            }
            if ($d !== '' && !($now !== null && ($now['locked'] || !empty($now['value'])))
                && CrawlerIngest::upsertMetadataKey($pdo, $entityId, 'description', $d, null, ['kind' => 'own_site', 'url' => $website, 'label' => 'Its own website, as Common Crawl copied it on ' . date('M j, Y', $arch)]))
                return ['filled' => ['description'], 'staged' => 0, 'auto_imported' => 0, 'site' => $said];
        }
        if ($status !== 'ok') return ['filled' => [], 'staged' => 0, 'auto_imported' => 0, 'site' => $said];
        $filled = [];
        $clean = fn ($s, int $max) => mb_substr(trim(strip_tags((string) $s)), 0, $max);
        $values = [
            'description' => $clean($found['description'] ?? '', IntakeStager::FIELD_RULES['description']['max']),
            'hours' => array_values(array_filter(array_map(fn ($h) => $clean($h, 60), array_slice((array) ($found['hours'] ?? []), 0, 14)))),
            'offerings' => array_values(array_filter(array_map(fn ($h) => $clean($h, 60), array_slice((array) ($found['offerings'] ?? []), 0, 30)))),
            'phone' => ($p = ListingExtractor::phone((string) ($found['phone'] ?? ''))) ? $p : '',
        ];
        $held = [];
        foreach ($values as $key => $value) {
            if ($value === '' || $value === []) continue;
            $now = $l['meta'][$key] ?? null;
            if ($now !== null && ($now['locked'] || !empty($now['value']))) continue;   // only fill what's empty and crawler-owned
            if (in_array($key, ['description', 'hours'], true) && AutoImport::pausedWhy() !== null) { $held[$key] = $value; continue; }   // the pause: to Review
            if (CrawlerIngest::upsertMetadataKey($pdo, $entityId, $key, $value, null, ['kind' => 'own_site', 'url' => $website, 'label' => 'Its own website'])) $filled[] = $key;
        }
        if ($held && AutoImport::holdForReview($pdo, $entityId, $held, 'crawler-refresh-paused', 'Its own website (' . $website . ', ' . date('Y-m-d') . ') gives')) $filled[] = 'held for Review: ' . implode(', ', array_keys($held));
        // the site's own map point ({lat, lon} from an embedded map or schema.org geo): fills a missing or ZIP-middle
        // point only (rural places often have no street address). The worker sends it as found.point.
        $pt = $found['point'] ?? null;
        if (is_array($pt) && is_numeric($pt['lat'] ?? null) && is_numeric($pt['lon'] ?? null)) {
            require_once __DIR__ . '/../Geocoder.php';
            if (Geocoder::setPoint($pdo, $entityId, (float) $pt['lat'], (float) $pt['lon'], 'site')) $filled[] = 'point';
        }
        $asked = self::changed($pdo, $entityId, (array) ($found['seen'] ?? []), $website, IntakeStager::existingListing($pdo, $entityId) ?? $l);
        if ($asked) $said = ($said === 'ok' ? '' : $said . '; ') . 'changed ' . implode(' and ', $asked) . ': sent to Review';
        $staged = 0; $auto = 0;
        $cands = array_values(array_filter(array_map([CrawlQueue::class, 'normalize'], array_slice($locations, 0, 25))));
        if ($cands) {
            $sourceId = IntakeStager::createSource($pdo, $website, ['create_listings'], "Refresh of listing #$entityId: other locations", 0);
            $r = IntakeStager::stage($pdo, $sourceId, $cands);
            $staged = count($r['staged']); $auto = count($r['auto_imported']);
        }
        return ['filled' => $filled, 'staged' => $staged, 'auto_imported' => $auto, 'site' => $said];
    }

    /** What the website said, acted on (see the class note). Returns what was done, in a few words. */
    /** Common Crawl's capture time as a Unix time, or null when it's missing or not a time. */
    private static function archiveAt(array $a): ?int
    {
        $t = preg_replace('/\D/', '', (string) ($a['at'] ?? ''));
        if (strlen($t) < 8) return null;
        $u = strtotime(substr($t, 0, 8) . 'T' . str_pad(substr($t, 8, 6), 6, '0') . 'Z');
        return $u !== false && $u <= time() + 86400 ? $u : null;
    }

    /** A Common Crawl copy this recent means the website is up for others: it goes to a person, never off the listing by itself. */
    private const ARCHIVE_DAYS = 90;

    private static function siteResult(PDO $pdo, int $id, string $status, string $closed, string $website, ?array $l = null, ?int $archived = null): string
    {
        $chk = $pdo->prepare('SELECT meta_value FROM entity_metadata WHERE entity_id = ? AND meta_key = ?');
        $chk->execute([$id, self::CHECK]);
        $c = json_decode((string) $chk->fetchColumn(), true) ?: [];
        if ($status === 'ok') {
            if ($c) $pdo->prepare('DELETE FROM entity_metadata WHERE entity_id = ? AND meta_key = ?')->execute([$id, self::CHECK]);
            $line = self::closedLine($closed);
            if ($line === '') { $pdo->prepare("DELETE FROM entity_metadata WHERE entity_id = ? AND meta_key = 'closed_signal'")->execute([$id]); return 'ok'; }   // its site no longer says so
            $l = $l ?? IntakeStager::existingListing($pdo, $id);
            require_once __DIR__ . '/../SuggestionActions.php';
            if (SuggestionActions::closedInfo($pdo, $id) !== null) return 'already closed';
            $note = 'Its own website says: "' . $line . '" (' . $website . ', ' . date('Y-m-d') . ')';
            $shared = self::sharedSite($pdo, $id, $website);
            // one website's word is a signal, never a closing (decisions/0160 W2, Jason 2026-11-13; decisions/0133 "Possibly
            // closed": one source says so; review B10): the listing shows "Possibly closed", its score drops, and a person
            // (or its owner) confirms. It closes when they do.
            CrawlerIngest::upsertMetadataKey($pdo, $id, 'closed_signal', ['line' => $line, 'url' => $website, 'at' => date('Y-m-d'), 'from' => 'own_site']);
            self::ask($pdo, $id, 'closed', [], $note . ($shared ? '. Other listings share this website (a chain or a group), so a person decides which one closed.' : (empty($l['claimed']) ? '. One source says so: it shows as possibly closed until a person confirms.' : '. It has an owner, so a person decides.')));
            return 'possibly closed: sent to Review';
        }
        // didn't load, or a parked / suspended page
        $c = ['fails' => (int) ($c['fails'] ?? 0) + 1, 'first' => (string) ($c['first'] ?? date('c')), 'last' => date('c'), 'how' => $status === 'archived' ? 'unreachable' : $status, 'url' => $website]
            + ($archived !== null ? ['archived' => gmdate('c', $archived)] : (isset($c['archived']) ? ['archived' => (string) $c['archived']] : []));
        CrawlerIngest::upsertMetadataKey($pdo, $id, self::CHECK, $c);
        if ($c['fails'] < 2 || strtotime($c['first']) > time() - self::RETRY_DAYS * 86400) return 'website failed once: tried again in ' . self::RETRY_DAYS . ' days' . ($archived !== null ? ' (Common Crawl copied it on ' . gmdate('M j, Y', $archived) . ')' : '');
        // up for others (a recent Common Crawl copy), so maybe refusing us rather than gone: a person decides, never taken off
        if ($c['how'] === 'unreachable' && !empty($c['archived']) && strtotime((string) $c['archived']) > time() - self::ARCHIVE_DAYS * 86400) {
            $pdo->prepare('DELETE FROM entity_metadata WHERE entity_id = ? AND meta_key = ?')->execute([$id, self::CHECK]);
            self::ask($pdo, $id, 'fix', [], 'Its website (' . $website . ') doesn\'t load for our crawler (checked ' . substr($c['first'], 0, 10) . ' and ' . substr($c['last'], 0, 10) . '), but Common Crawl copied it on ' . substr((string) $c['archived'], 0, 10) . ': it may be refusing us rather than gone. A person decides.');
            return 'website doesn\'t load for us but Common Crawl has a recent copy: sent to Review';
        }
        $how = $status === 'dead' ? 'shows a parked or suspended page' : 'doesn\'t load';
        $w = $pdo->prepare("SELECT is_locked, source_type FROM entity_metadata WHERE entity_id = ? AND meta_key = 'website'");
        $w->execute([$id]);
        $row = $w->fetch(PDO::FETCH_ASSOC);
        $pdo->prepare('DELETE FROM entity_metadata WHERE entity_id = ? AND meta_key = ?')->execute([$id, self::CHECK]);
        if ($row && (int) $row['is_locked'] === 0 && $row['source_type'] === 'ai_crawler') {   // ours: taken off, kept as website_dead
            CrawlerIngest::upsertMetadataKey($pdo, $id, 'website_dead', ['url' => $website, 'since' => $c['first'], 'checked' => $c['last'], 'how' => $how]);
            $pdo->prepare("DELETE FROM entity_metadata WHERE entity_id = ? AND meta_key = 'website' AND is_locked = 0 AND source_type = 'ai_crawler'")->execute([$id]);
            require_once __DIR__ . '/../Identity.php';
            Identity::listingWrote($pdo, $id, 'website', null, 'ai_crawler', 0, ['note' => 'it ' . $how]);   // kept as history, never written back
            if (is_file(__DIR__ . '/../SearchIndex.php')) { require_once __DIR__ . '/../SearchIndex.php'; try { if (SearchIndex::ready($pdo)) SearchIndex::indexListings($pdo, [$id]); } catch (Throwable $x) {} }
            return 'website taken off (it ' . $how . ')';
        }
        self::ask($pdo, $id, 'fix', [], 'Its website (' . $website . ') ' . $how . ': checked ' . substr($c['first'], 0, 10) . ' and ' . substr($c['last'], 0, 10) . '. A person entered it, so a person decides.');
        return 'dead website sent to Review';
    }

    /** Another listing uses the same website (a chain's or a group's), or this one is part of a Nexus. */
    private static function sharedSite(PDO $pdo, int $id, string $website): bool
    {
        $host = preg_replace('/^www\./', '', strtolower((string) parse_url($website, PHP_URL_HOST)));
        if ($host === '') return true;
        $st = $pdo->prepare("SELECT COUNT(*) FROM entity_metadata m JOIN entities e ON e.id = m.entity_id
                              WHERE m.meta_key = 'website' AND m.entity_id <> ? AND e.is_suppressed = 0 AND (m.meta_value LIKE ? OR m.meta_value LIKE ?)");
        $st->execute([$id, '%//' . $host . '%', '%//www.' . $host . '%']);
        if ((int) $st->fetchColumn() > 0) return true;
        $n = $pdo->prepare('SELECT nexus_id FROM entities WHERE id = ?'); $n->execute([$id]);
        return (int) $n->fetchColumn() > 0;
    }

    /**
     * "We've moved" on its own website (decisions/0133 Phase 6): never changed by itself (an address moves the pin, and
     * a branch moving isn't the business moving), so it goes to Review with the new street when the sentence gives one
     * that isn't the listing's. The same sentence is asked about once (kept as site_moved).
     */
    private static function moved(PDO $pdo, int $id, string $text, string $website, array $l): string
    {
        [$line, $street] = self::movedLine($text);
        if ($line === '') return 'ok';
        $now = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', (string) ($l['meta']['address']['value'] ?? '')));
        if ($street !== '' && str_contains(' ' . $now . ' ', ' ' . trim(strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $street))) . ' ')) return 'ok';   // it already has the new address
        $seen = $pdo->prepare("SELECT meta_value FROM entity_metadata WHERE entity_id = ? AND meta_key = 'site_moved'"); $seen->execute([$id]);
        $was = json_decode((string) $seen->fetchColumn(), true) ?: [];
        if (($was['line'] ?? '') === $line) return 'moved? asked before';
        CrawlerIngest::upsertMetadataKey($pdo, $id, 'site_moved', ['line' => $line, 'street' => $street, 'url' => $website, 'seen' => date('c')]);
        self::ask($pdo, $id, 'fix', $street !== '' ? ['address' => $street] : [], 'Its own website says: "' . $line . '" (' . $website . ', ' . date('Y-m-d') . ').'
            . ($street !== '' ? ' The new street is filled in: check the town and ZIP, and that it\'s this business that moved, not one branch.' : ' It doesn\'t say where: look on the site.'));
        return 'moved? sent to Review';
    }

    /** [the sentence, the new street if it gives one] when the business says it moved; not one branch, not for a season. */
    public static function movedLine(string $text): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
        if ($text === '' || !preg_match(self::MOVED, $text)) return ['', ''];
        $sents = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9"\'(])/u', $text);
        foreach ($sents as $i => $sent) {
            if (!preg_match(self::MOVED, $sent)) continue;
            if (preg_match('/\b(temporar\w*|for\s+the\s+(season|winter|summer)|while\s+we|during\s+(construction|renovation|the)|our\s+(\w+\s+){1,3}(location|branch|store|office|shop)\s+(has|have|is)|moved\s+(online|to\s+(online|our\s+website|facebook|instagram|a\s+new\s+(website|site|domain|platform|system)))|(moved|relocated)\s+(our|the|this)\s+(\w+\s+)?(hours|date|event|sale|class|classes|meeting|time|show|market|party|appointment|deadline|launch)|website|newsletter|blog|page)\b/i', $sent)) continue;
            $look = $sent . ' ' . ($sents[$i + 1] ?? '');   // "We've moved! Our new address is ..."
            $street = preg_match(self::STREET, $look, $m) ? trim($m[1]) : '';
            return [mb_substr(trim($street !== '' && !str_contains($sent, $street) ? $look : $sent), 0, 240), mb_substr($street, 0, 120)];
        }
        return ['', ''];
    }

    /** The sentence, when it says this business closed: not one branch ("our Main St location"), not an hours notice. */
    public static function closedLine(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
        if ($text === '' || !preg_match(self::CLOSED, $text)) return '';
        foreach (preg_split('/(?<=[.!?])\s+/u', $text) as $sent) {
            if (!preg_match(self::CLOSED, $sent)) continue;
            if (preg_match('/\b(location|branch|store\s+(at|on|in)|office\s+(at|on|in)|\d{2,5}\s+\w+\s+(st|street|ave|avenue|rd|road|blvd|hwy|highway|dr|drive)\b|today|tomorrow|holiday|christmas|thanksgiving|monday|tuesday|wednesday|thursday|friday|saturday|sunday|until|reopen|re-open|temporarily|for\s+the\s+(season|winter|summer))/i', $sent)) continue;
            return mb_substr($sent, 0, 240);
        }
        return '';
    }

    /**
     * What the site says now against the listing: a phone or hours that differ go to Review as one fix (the new values
     * filled in). Returns the fields asked about. Nothing is written to the listing.
     */
    private static function changed(PDO $pdo, int $id, array $seen, string $website, array $l): array
    {
        if (!$seen) return [];   // an older worker sends none
        require_once __DIR__ . '/../Hours.php';
        $st = $pdo->prepare("SELECT meta_value FROM entity_metadata WHERE entity_id = ? AND meta_key = 'site_said'"); $st->execute([$id]);
        $was = json_decode((string) $st->fetchColumn(), true) ?: [];
        $fields = []; $lines = []; $keep = $was;
        $phone = ($p = ListingExtractor::phone((string) ($seen['phone'] ?? ''))) ? $p : '';
        $now = (string) (is_string($l['meta']['phone']['value'] ?? null) ? $l['meta']['phone']['value'] : '');
        $d10 = fn (string $x) => strlen($d = preg_replace('/\D/', '', $x)) >= 10 ? substr($d, -10) : '';
        if ($phone !== '' && $d10($now) !== '' && $d10($phone) !== $d10($now)) {
            $keep['phone'] = $d10($phone);
            if (($was['phone'] ?? '') !== $d10($phone)) { $fields['phone'] = $phone; $lines[] = 'phone ' . $phone . ' (the listing has ' . $now . ')'; }
        }
        $hours = array_values(array_filter(array_map(fn ($h) => mb_substr(trim(strip_tags((string) $h)), 0, 60), array_slice((array) ($seen['hours'] ?? []), 0, 14))));
        $have = $l['meta']['hours']['value'] ?? null;
        if ($hours && $have && self::hoursDiffer($have, $hours)) {
            $key = md5(strtolower(implode('|', $hours)));
            $keep['hours'] = $key;
            if (($was['hours'] ?? '') !== $key) { $fields['hours'] = implode('; ', $hours); $lines[] = 'hours ' . implode('; ', $hours); }
        }
        if ($keep !== $was) CrawlerIngest::upsertMetadataKey($pdo, $id, 'site_said', $keep + ['url' => $website, 'at' => date('Y-m-d')]);
        if (!$fields) return [];
        $who = !empty($l['claimed']) ? ' It has an owner: ask them, or leave it to them.' : '';
        if (!self::ask($pdo, $id, 'fix', $fields, 'Its own website (' . $website . ', ' . date('Y-m-d') . ') now gives ' . implode(', and ', $lines)
            . '. Check that it\'s this place\'s, not a head office\'s or one season\'s, then apply it.' . $who)) {
            CrawlerIngest::upsertMetadataKey($pdo, $id, 'site_said', $was + ['url' => $website, 'at' => date('Y-m-d')]);   // not asked yet: asked next time
            return [];
        }
        return array_keys($fields);
    }

    /** Hours that read differently on the days both give (three days at least); unreadable hours never differ. */
    public static function hoursDiffer($a, $b): bool
    {
        $x = Hours::parse($a); $y = Hours::parse($b);
        if ($x === null || $y === null) return false;
        $days = array_intersect(array_keys($x), array_keys($y));
        if (count($days) < 3) return false;
        foreach ($days as $d) {
            $p = $x[$d]; $q = $y[$d]; sort($p); sort($q);
            if ($p !== $q) return true;
        }
        return false;
    }

    /** A Review item from the crawler (never limited like a visitor's). Returns whether it's waiting in Review now. */
    private static function ask(PDO $pdo, int $id, string $kind, array $fields, string $note): bool
    {
        require_once __DIR__ . '/../Suggestions.php';
        try {
            $st = $pdo->prepare("SELECT 1 FROM suggestions WHERE entity_id = ? AND kind = ? AND status = 'new' AND device_hash = ?");
            $st->execute([$id, $kind, 'crawler-refresh']);
            if ($st->fetchColumn()) return false;   // one at a time: this one is asked when that one is settled
            Suggestions::create($pdo, ['kind' => $kind, 'topic' => 'business', 'entity_id' => $id, 'fields' => $fields, 'note' => $note, 'context' => 'Crawler refresh'], null, 'crawler-refresh');
            return true;
        } catch (Throwable $x) { error_log('refresh ask ' . $id . ': ' . $x->getMessage()); return false; }
    }
}
