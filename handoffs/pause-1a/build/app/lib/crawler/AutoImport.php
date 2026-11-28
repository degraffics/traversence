<?php

declare(strict_types=1);

require_once __DIR__ . '/IntakeStager.php';
require_once __DIR__ . '/ListingExtractor.php';   // IntakeStager::approve() validates phones with it
require_once __DIR__ . '/../ListingSafety.php';
require_once __DIR__ . '/../TrustScore.php';
require_once __DIR__ . '/../Identity.php';
require_once __DIR__ . '/../WebIdentity.php';
require_once __DIR__ . '/../Aliases.php';

/**
 * api/lib/crawler/AutoImport.php — confidence-gated auto-import of crawled listings (decisions/0044).
 *
 * A staged candidate is published without a person when the readiness checks pass (decisions/0076, 0133: readiness is
 * the listing gate; the confidence score only orders the work):
 *   1. real          — from a trusted official registry, or seen on 2+ independent websites, or its own site;
 *   2. complete      — a name, a way to reach it and a place;
 *   3. appropriate   — a category was found, it isn't a human-review category, and the text is clean;
 *   4. not duplicate — no exact or look-alike existing listing (a match is always a merge for review).
 * It publishes through IntakeStager::approve(), the one ingest path, with no admin id and a note saying
 * why it qualified. Everything else stays in Listing Intake, sorted by score, where high scorers can be
 * approved in a batch ("quick approve"). Recent auto-imports are listed for spot-checks and can be
 * pulled (suppressed) in one click.
 *
 * Settings (.env): AUTO_IMPORT=off disables it. PAUSED, when it holds words, does too, with its reason shown wherever
 * Auto-publish is (the pause on Jason's call, 2026-11-28: nothing the system finds is published until the data-capture
 * review's fixes are live; staging, matching and filling gaps go on, and a person's own approval in Review still works).
 */
final class AutoImport
{
    public const NOTE_PREFIX = 'auto-import';

    /**
     * Official registries whose records count as "not made up" on their own. A crawler that reads one
     * sets candidate.source.registry to its key; a verify pass that matches one adds it to
     * candidate.registries (api/lib/crawler/Verify.php).
     */
    public const TRUSTED_REGISTRIES = ['npi' => 'NPI Registry (CMS)'];

    /** The pause (Jason, 2026-11-28, through the CEO: "Pause it. Yes"). Way back: this release's Roll back, or a release
     *  that empties it, once research/data-capture-review-2026-12-02.md's fixes are live. */
    public const PAUSED = 'Paused on Jason\'s call 2026-11-28: "Pause it. Yes" (the data-capture review, research/data-capture-review-2026-12-02.md). Nothing the system finds is published until its fixes are live: finds wait in Review, where a person can still approve one.';

    public static function enabled(): bool
    {
        if (self::pausedWhy() !== null) return false;
        return strtolower(trim((string) (getenv('AUTO_IMPORT') ?: 'on'))) !== 'off';
    }

    /**
     * While paused: values the system would have written into a published listing on its own (About text, hours), held
     * as one suggestion for Review instead (Suggestions, kind fix), one open at a time per listing and source. Returns
     * whether it was asked. A person applies it there, or doesn't.
     */
    public static function holdForReview(PDO $pdo, int $entityId, array $fields, string $device, string $what): bool
    {
        $fields = array_filter($fields, fn ($v) => $v !== null && $v !== '' && $v !== []);
        if (!$fields) return false;
        $fields = array_map(fn ($v) => is_array($v) ? implode('; ', array_map('strval', $v)) : (string) $v, $fields);
        try {
            require_once __DIR__ . '/../Suggestions.php';
            $st = $pdo->prepare("SELECT 1 FROM suggestions WHERE entity_id = ? AND kind = 'fix' AND status = 'new' AND device_hash = ?");
            $st->execute([$entityId, $device]);
            if ($st->fetchColumn()) return false;
            Suggestions::create($pdo, ['kind' => 'fix', 'topic' => 'business', 'entity_id' => $entityId, 'fields' => $fields,
                'note' => $what . ' ' . implode(' and ', array_map(fn ($k) => $k === 'description' ? 'an About' : $k, array_keys($fields))) . '. Held for a person while the pause holds (' . self::PAUSED . '). Check it is this place\'s own, not a head office\'s or another location\'s, then apply it.',
                'context' => 'Paused: held for Review'], null, $device);
            return true;
        } catch (Throwable $x) { error_log('pause hold #' . $entityId . ': ' . $x->getMessage()); return false; }
    }

    /** Why auto-publish is paused by a release (not by .env), or null. */
    public static function pausedWhy(): ?string
    {
        return trim(self::PAUSED) !== '' ? self::PAUSED : null;
    }

    /** Distinct websites the candidate's facts came from (www. ignored). */
    public static function sourceHosts(array $c): array
    {
        $urls = [(string) ($c['source']['start_url'] ?? ''), (string) ($c['where']['location_page'] ?? '')];
        foreach ((array) ($c['sources'] ?? []) as $list) foreach ((array) $list as $u) $urls[] = (string) $u;
        foreach ((array) ($c['corroboration'] ?? []) as $u) $urls[] = (string) (is_array($u) ? ($u['url'] ?? '') : $u);
        $hosts = [];
        foreach ($urls as $u) {
            $h = preg_replace('/^www\./', '', strtolower((string) parse_url($u, PHP_URL_HOST)));
            // OpenStreetMap is a lead, never a source (decisions/0065; review C11), and the worker's own job address isn't a site
            if ($h === '' || preg_match('/(^|\.)(openstreetmap\.org|osm\.org)$/', $h) || str_ends_with($h, 'traversence')) continue;
            $hosts[$h] = true;
        }
        return array_keys($hosts);
    }

    /**
     * A government page among its sources (a public body's site we read: .gov, a state or local .us, or one the register
     * calls government). Jason, 2026-11-13 (decisions/0160 W1): a government page we read can establish a listing by itself.
     */
    public static function governmentSource(array $c): string
    {
        $hosts = self::sourceHosts($c);
        foreach ($hosts as $h) if (preg_match('/\.(gov|mil)$|\.us$/', $h) || str_contains($h, '.gov.')) return $h;
        try {
            if ($hosts) {
                $pdo = Database::connection();
                $st = $pdo->prepare('SELECT host FROM ' . Database::t('reference_sources') . " WHERE kind = 'government' AND status = 'read' AND host IN (" . implode(',', array_fill(0, count($hosts), '?')) . ') LIMIT 1');
                $st->execute($hosts);
                if ($h = $st->fetchColumn()) return (string) $h;
            }
        } catch (Throwable $e) {}
        return '';
    }

    /** The score one source needs to establish a listing by itself (Thresholds & gates; Jason, 2026-11-13: scoring decides). */
    public static function oneSourceLine(): int
    {
        static $v = null;
        if ($v !== null) return $v;
        try { require_once __DIR__ . '/../Thresholds.php'; return $v = (int) Thresholds::get(Database::connection(), 'listing_one_source'); } catch (Throwable $e) { return $v = 60; }
    }

    /**
     * Whether a candidate is ready to publish (decisions/0076, replacing 0044's guardrails). Four checks:
     *   real        one strong source: its own website, an official registry, or two independent sites (the NPI file
     *               alone needs one more: its addresses can be old, 0048);
     *   complete    its name; a phone or a website; a street address or a map point, placed by its ZIP or its point;
     *   appropriate a relevant category (its words in what the business says it is: its declared kind, name or
     *               offerings; not its description alone), and no spam or adult text. No kind of business is held for
     *               a person: listings come from public information;
     *   unique      no unresolved duplicate (other locations and providers at a facility aren't duplicates, 0074).
     * 'score' is still the confidence, for sorting.
     * @return array{eligible:bool, score:int, checks:list<array{code:string,pass:bool,label:string}>, category_id:?int, category_name:string}
     */
    public static function evaluate(array $c): array
    {
        $registry = '';
        foreach (array_merge([(string) ($c['source']['registry'] ?? '')], (array) ($c['registries'] ?? [])) as $r) {
            if (isset(self::TRUSTED_REGISTRIES[$r])) { $registry = $r; break; }
        }
        $trusted = $registry !== '';
        $hosts = self::sourceHosts($c);
        $corroborated = count($hosts) >= 2;
        $inCluster = !empty($c['geo']['cluster']);
        $fromFile = ($c['source']['kind'] ?? '') === 'npi_bulk';
        $own = self::ownSite($c);

        $score = (int) ($c['confidence'] ?? 0);
        if ($corroborated) $score += 10;
        if ($trusted) $score += 15;
        if ($own) $score += 10;
        if (!$inCluster) $score -= 15;
        $score = max(0, min(100, $score));

        // the category: a sure one first, else the best relevant guess
        $cat = null; $catName = ''; $sure = false;
        $sugs = array_slice((array) ($c['category_suggestions'] ?? []), 0, 3);
        $unclear = '';
        if (isset($sugs[0]['confidence']) && ($sugs[0]['from'] ?? '') !== 'registry') {
            // the weighted classifier (decisions/0024): at the auto line it's set; between the lines the fit checks decide;
            // below the review line, or the fallback group, a person picks
            require_once __DIR__ . '/../CategoryClassifier.php';
            [$auto, $review] = CategoryClassifier::lines(Database::connection());
            $top = $sugs[0]; $other = in_array((string) ($top['group'] ?? ''), CategoryClassifier::FALLBACK, true);
            if (!$other && (int) $top['confidence'] >= $auto) { $cat = (int) $top['id']; $catName = (string) $top['name']; $sure = true; }
            elseif (!$other && (int) $top['confidence'] >= $review) {
                $mid = array_values(array_filter($sugs, fn ($x) => (int) ($x['confidence'] ?? 0) >= $review && !in_array((string) ($x['group'] ?? ''), CategoryClassifier::FALLBACK, true)
                    && !preg_match('/incidental|only general words|categories\' names/', (string) ($x['why'] ?? ''))));   // one incidental or common word: a person picks
                foreach ($mid as $sug) if (self::categoryFits((string) $sug['name'], $c)) { $cat = (int) $sug['id']; $catName = (string) $sug['name']; $sure = true; break; }
                if (!$sure) foreach ($mid as $sug) if (self::categoryRelevant((string) $sug['name'], $c)) { $cat = (int) $sug['id']; $catName = (string) $sug['name']; break; }
            } else $unclear = $other ? 'Its best fit is in "Other": a person picks the category' : 'No category is clear enough (best guess "' . $top['name'] . '", ' . (int) $top['confidence'] . '%): a person picks';
        } else {   // a registry's own label, or a find staged before the classifier
            foreach ($sugs as $sug) if (($sug['from'] ?? '') === 'registry' || self::categoryFits((string) ($sug['name'] ?? ''), $c)) { $cat = (int) $sug['id']; $catName = (string) $sug['name']; $sure = true; break; }
            if (!$sure) foreach ($sugs as $sug) if (self::categoryRelevant((string) ($sug['name'] ?? ''), $c)) { $cat = (int) $sug['id']; $catName = (string) $sug['name']; break; }
        }
        $guess = $cat === null && isset($sugs[0]['id']) ? (string) $sugs[0]['name'] : '';
        $problem = ListingSafety::contentProblem((string) ($c['name'] ?? ''), (string) ($c['what']['description'] ?? ''));

        // other locations of one business, and providers at a facility, aren't duplicates (0074)
        $dup = !empty($c['match']['exact']);
        foreach ((array) ($c['match']['possible'] ?? []) as $m) {
            if (isset($m['address'])) {
                if (in_array(self::relationTo($c, (string) ($m['name'] ?? ''), (string) ($m['phone'] ?? ''), (string) $m['address'], (string) ($m['zip'] ?? ''))['kind'], ['same', 'maybe'], true)) $dup = true;
            } elseif (self::sameName((string) ($c['name'] ?? ''), (string) ($m['name'] ?? '')) >= 0.4 || stripos((string) ($m['why'] ?? ''), 'name') !== false
                      || !(self::looksPerson((string) ($c['name'] ?? '')) || self::looksPerson((string) ($m['name'] ?? '')))) $dup = true;
        }

        $hasReach = !empty($c['actions']['phone']) || !empty($c['actions']['website']);
        // a page's label read as a name ("Physical Address", "Contact Us") isn't a business
        $nameOk = mb_strlen(trim((string) ($c['name'] ?? ''))) >= 2 && !preg_match('/^\s*((physical|mailing|street|business|our)\s+address|address|contact(\s+us)?|about(\s+us)?|home|locations?|directions|hours|phone|email|menu|welcome)\s*$/i', (string) ($c['name'] ?? ''));
        // and Data cleanup's own check (decisions/0164 §3.5, Jason 2026-11-15), so the gate and the cleanup agree: page text
        // ("Contact Us:", "PO Box 190", a webinar or form title, a sentence) never goes public; drop boxes aren't page text
        require_once __DIR__ . '/../DataCleanup.php';
        $junk = DataCleanup::junkName((string) ($c['name'] ?? ''));
        if ($junk[0]) $nameOk = false;
        $hasPlace = preg_match('/\d/', (string) ($c['where']['address'] ?? '')) === 1 || !empty($c['where']['point']) || !empty($c['geo']['source']);
        $placed = !empty($c['geo']['resolved']);
        // what publishing asks for (IntakeStager::approve: street, town, state, ZIP): a find without them was "ready" and then
        // refused, in no list (review E23). Now it isn't ready: it takes the second look, then reaches Review with what's missing.
        $w = (array) ($c['where'] ?? []);
        $missing = array_keys(array_filter(['a street address' => trim((string) ($w['address'] ?? '')) === '', 'its town' => trim((string) ($w['city'] ?? '')) === '',
            'its state' => !preg_match('/^[A-Za-z]{2}$/', trim((string) ($w['state'] ?? ''))), 'its ZIP' => !preg_match('/^\d{5}$/', trim((string) ($w['zip'] ?? '')))]));
        // decisions/0164 §3.5 (the "real business" floor) and §5.4 (readiness), not 0133's score line (gap review X2;
        // Jason, 2026-11-14: "confidence, not restriction"): real = a real business name and a way to find it, a street
        // address or a phone we may keep (its own site, a registry or licence, an open dataset whose licence allows it, a
        // page whose terms allow it). OpenStreetMap is a lead, not a source; a cite-only page confirms but isn't kept.
        $keepHosts = array_values(array_filter($hosts, fn ($h) => !self::isOsmHost($h) && !self::isGoogleHost($h) && self::keepRule($h)));   // Google confirms, never a source we keep
        $dataset = in_array((string) ($c['source']['kind'] ?? ''), self::OPEN_DATASETS, true);
        $keepable = $own || $trusted || $fromFile || $dataset || (bool) $keepHosts;
        $street = preg_match('/^\s*#?\s*\d/', (string) ($c['where']['address'] ?? '')) === 1 && !self::poBox((string) ($c['where']['address'] ?? ''));
        $phoneOk = !empty($c['actions']['phone']);
        $npiHidden = $fromFile && self::npiNotPublic($c);   // NPI's second guard (0164 §1.5): kept as a record, not shown as a place to visit
        $real = $nameOk && $keepable && ($street || $phoneOk) && !$npiHidden;
        $moved = !empty($c['moved']) && !$own;   // OpenStreetMap or its own website has it elsewhere (DeepLook)
        if ($moved) $real = false;
        $gov = '';
        // complete (P3-1, one rule): the floor's name and a keepable phone or street address, a website adding to it, and a
        // point in an area we cover (the address, the dataset's own point, or the town's middle, marked approximate)
        $hasPlace = $street || !empty($c['where']['point']) || !empty($c['geo']['source']) || ($phoneOk && trim((string) ($w['city'] ?? '')) !== '');
        $hasReach = $phoneOk || $street || !empty($c['actions']['website']);
        if ($phoneOk) $missing = array_values(array_diff($missing, ['a street address']));   // a phone and a town are enough: "No phone yet"'s twin, "No address yet"

        $checks = [
            ['code' => 'real', 'pass' => $real,
             'label' => $moved ? 'Probably moved: ' . (string) ($c['moved']['note'] ?? 'another source has it elsewhere')
                      : ($real ? 'Past the "real business" floor: ' . ($own ? 'its own website' : ($trusted ? self::TRUSTED_REGISTRIES[$registry] : ($fromFile ? 'the NPI Registry' : ($dataset ? 'an open dataset (' . (string) $c['source']['kind'] . ')' : implode(', ', $keepHosts))))) . ' gives ' . ($street ? 'its street address' : 'its phone') . '; public at its confidence (' . $score . '), then vetted continuously'
                      : ($npiHidden ? 'An NPI record of a kind we keep but don\'t show as a place to visit (air ambulance, home care, residential treatment, a VA registration): list it anyway in Review if it serves visitors'
                      : (!$nameOk ? 'Its name reads like page text, not a business'
                      : (!$keepable ? ($hosts && array_filter($hosts, fn ($h) => self::isOsmHost($h)) === $hosts ? 'Only OpenStreetMap, which is a lead, not a source: needs a source we may keep (its own site, a registry, an open dataset)' : 'Only pages we may cite, not keep (' . implode(', ', $hosts) . '): needs a source we may keep')
                      : 'No street address or phone yet: a sighting until a source we may keep gives one'))))],
            ['code' => 'complete', 'pass' => $nameOk && $hasReach && $hasPlace && $placed && !$missing,
             'label' => !$nameOk ? 'Its name reads like page text ("' . trim((string) ($c['name'] ?? '')) . '"' . ($junk[1] !== '' ? ': ' . lcfirst($junk[1]) : '') . '), not a business' : (!$hasReach ? 'No phone or website' : (!$hasPlace ? 'No street address or map point' : (!$placed ? 'Its ZIP isn\'t one we can place on the map' : ($missing ? 'It needs ' . implode(', ', $missing) . ' to be listed' : 'Has a way to reach it and a place on the map'))))],
            ['code' => 'appropriate', 'pass' => $cat !== null && $problem === null,
             'label' => $problem !== null ? $problem
                      : ($cat === null ? ($unclear !== '' ? $unclear : ($guess !== '' ? "No category fits what it says it is (closest: \"$guess\", from its description only)" : 'No category found'))
                      : ($sure ? "Category \"$catName\"" : "Category \"$catName\" (best fit from what it says it is)"))],
            ['code' => 'unique', 'pass' => !$dup,
             'label' => $dup ? 'May already be listed' : (!empty($c['match']['possible']) ? 'Matches only other locations or providers at the same place, not duplicates' : 'Not listed yet')],
        ];
        $eligible = !in_array(false, array_column($checks, 'pass'), true);
        return ['eligible' => $eligible, 'score' => $score, 'checks' => $checks, 'category_id' => $cat, 'category_name' => $catName];
    }

    /** Open datasets whose licence lets us keep name, address and phone, with credit (0164 §1.5; their cards must be on). */
    public const OPEN_DATASETS = ['overture', 'foursquare_os'];
    /** NPI kinds kept as records but not shown as places to visit (0164 §1.5, NPI's two guards). */
    public const NPI_NOT_PUBLIC = '/\b(air ambulance|ambulance \(air|home (health|care)|in[- ]home|residential treatment|veterans affairs|\bva\b|department of veterans)\b/i';

    public static function npiNotPublic(array $c): bool
    {
        $t = implode(' ', array_map('strval', array_merge([(string) ($c['name'] ?? '')], (array) ($c['what']['offerings'] ?? []))));
        return (bool) preg_match(self::NPI_NOT_PUBLIC, $t);
    }

    /** Google's pages (a Maps link from the paid backup, a search result): they confirm what we found, never a source we keep. */
    private static function isGoogleHost(string $h): bool { return (bool) preg_match('/(^|\.)google\.[a-z.]+$/', strtolower($h)) || strtolower($h) === 'goo.gl'; }

    private static function isOsmHost(string $h): bool { return (bool) preg_match('/(^|\.)(openstreetmap\.org|osm\.org)$/', strtolower($h)) || $h === 'osm' || $h === 'openstreetmap'; }

    private static function poBox(string $a): bool { return (bool) preg_match('/^\s*(p\.?\s*o\.?\s*)?(box|drawer)\s*#?\s*\d+/i', $a); }

    /** Whether a host's facts may be kept (the source register: not cite only or ignore, R9). */
    private static function keepRule(string $h): bool
    {
        static $seen = [];
        if (!isset($seen[$h])) { try { require_once __DIR__ . '/SourceIndex.php'; $seen[$h] = !in_array(SourceIndex::useRule(Database::connection(), $h), ['cite', 'ignore'], true); } catch (Throwable) { $seen[$h] = true; } }
        return $seen[$h];
    }

    /** Whether the candidate came from, or was confirmed on, the business's own website. */
    public static function ownSite(array $c): bool
    {
        $site = self::hostOf((string) ($c['actions']['website'] ?? ''));
        if ($site === '') return false;
        foreach ([(string) ($c['where']['location_page'] ?? ''), (string) ($c['source']['start_url'] ?? '')] as $u) if (WebIdentity::brand($site) !== '' ? WebIdentity::sameBrand($u, $site) : self::hostOf($u) === $site) return true;
        foreach ((array) ($c['corroboration'] ?? []) as $u) if (is_array($u) && ($u['kind'] ?? '') === 'own_site') return true;
        return false;
    }

    private static function hostOf(string $u): string
    {
        $u = trim($u);
        if ($u === '') return '';
        return (string) preg_replace('/^www\./', '', strtolower((string) parse_url(preg_match('#^[a-z]+://#i', $u) ? $u : 'https://' . $u, PHP_URL_HOST)));
    }

    /**
     * Whether a waiting listing has run out of things the pipeline can try (decisions/0076 §2): it isn't ready, and the
     * crawler's second look is done or spent (or there's nothing to look it up by), or it's two weeks old with no look
     * started. Only these reach a person in Review.
     */
    public static function exhausted(array $c, string $createdAt = ''): bool
    {
        if (!empty($c['hold'])) return true;   // outside our areas, held for a person (0066)
        $e = self::evaluate($c);
        if ($e['eligible']) return false;
        $fail = array_column(array_filter($e['checks'], fn ($k) => !$k['pass']), 'code');
        if (in_array('appropriate', $fail, true) && ListingSafety::contentProblem((string) ($c['name'] ?? ''), (string) ($c['what']['description'] ?? '')) !== null) return true;   // nothing to look up fixes that
        if (!empty($c['moved'])) return true;   // its address looks old: a person says whether to list it
        $v = (array) ($c['verify'] ?? []);
        // 14 days of W1 switched on, not of the calendar (decisions/0164 §6.1, X1): a pause never makes finds run out
        static $old = null;
        if ($old === null) { try { require_once __DIR__ . '/../Clocks.php'; $old = Clocks::cutoff(Database::connection(), 'W1', 14 * 86400); } catch (Throwable) { $old = time() - 14 * 86400; } }
        // the deeper look (OpenStreetMap, its own website) comes first, when it has a point to look around
        if (empty($c['deep']['at']) && isset($c['geo']['latitude']) && !($createdAt !== '' && strtotime($createdAt) < $old)) return false;
        if (!empty($v['done_at']) || (int) ($v['attempts'] ?? 0) >= 3) return true;
        $lookable = preg_match('/\d/', (string) ($c['where']['address'] ?? '')) === 1 || !empty($c['actions']['phone']);
        if (!$lookable) return true;
        return $createdAt !== '' && strtotime($createdAt) < $old && empty($v['attempts']) && !empty($v['index_at']);
    }

    /**
     * Why an exhausted listing needs a person, as one reason per listing so Review can group them and answer a group at
     * once (decisions/0076): key, what it means in plain words, and whether "publish them all" is an answer.
     * @return array{key:string, text:string, publish:bool}
     */
    public static function reason(array $c): array
    {
        if (!empty($c['hold'])) return ['key' => 'hold', 'text' => 'Outside the areas we cover yet: list it anyway, or leave it out', 'publish' => true];
        $fail = array_column(array_filter(self::evaluate($c)['checks'], fn ($k) => !$k['pass']), 'label', 'code');
        if (isset($fail['appropriate']) && ListingSafety::contentProblem((string) ($c['name'] ?? ''), (string) ($c['what']['description'] ?? '')) !== null)
            return ['key' => 'content', 'text' => 'Its name or description reads as spam or adult content', 'publish' => false];
        if (isset($fail['complete'])) return ['key' => 'incomplete', 'text' => 'Missing what a listing needs (a way to reach it, a street address, or a place on the map), and the crawler found nothing more', 'publish' => false];
        if (isset($fail['unique'])) return ['key' => 'dupe', 'text' => 'May already be listed under a similar name: compare each side by side', 'publish' => false];
        if (isset($fail['appropriate'])) return ['key' => 'category', 'text' => 'No category fits what it says it is: choose one for each', 'publish' => false];
        if (!empty($c['moved'])) return ['key' => 'moved', 'text' => 'Probably moved or closed: OpenStreetMap or the business\'s own website has it at another address', 'publish' => false];
        if (($c['source']['kind'] ?? '') === 'npi_bulk')
            return ['key' => 'npi', 'text' => 'Only the NPI Registry (the federal health-provider list) has it, and nothing else confirms it\'s still there. Registry addresses can be old', 'publish' => true];
        return ['key' => 'one', 'text' => 'Only one website lists it, and the crawler found nothing else that confirms it', 'publish' => true];
    }

    /**
     * A category is relevant when one of its meaningful words is in what the business says it is: its declared kind
     * (schema.org), its name or its offerings (registry service types included). Its description alone isn't enough.
     */
    public static function categoryRelevant(string $categoryName, array $c): bool
    {
        // every distinctive word of the category (not "family", "care", "consultants" …) must be in what the business says
        // it is: "Family Medicine Associates" isn't Family Entertainment Centers, "Peak Physical Therapy" isn't Aquatic
        // Therapy. A category made only of general words ("Health Services") needs all of them (categoryFits).
        $generic = ['and', 'the', 'for', 'other', 'services', 'service', 'svc', 'store', 'stores', 'shop', 'new', 'used', 'sale', 'sales', 'retail', 'whls', 'nec',
            'supplies', 'supply', 'equipment', 'center', 'centers', 'ctr', 'family', 'care', 'home', 'homes', 'house', 'consultants', 'consulting', 'general',
            'health', 'medical', 'government', 'county', 'state', 'programs', 'program', 'organizations', 'information', 'agencies', 'agency', 'products',
            'systems', 'association', 'associations', 'company', 'group', 'mfrs', 'wholesale', 'road', 'office', 'offices', 'facilities', 'specialty'];
        // the same thing in other words ("dental" is dentists), then a long word by its first six letters ("therapy" ~ "therapists")
        $same = ['dental' => 'dentist', 'dentistry' => 'dentist', 'optometric' => 'optometrist', 'optometry' => 'optometrist', 'chiropractic' => 'chiropractor',
                 'orthodontic' => 'orthodontist', 'orthodontics' => 'orthodontist', 'podiatry' => 'podiatrist', 'pediatric' => 'pediatrician', 'veterinary' => 'veterinarian',
                 'vet' => 'veterinarian', 'eatery' => 'restaurant', 'grill' => 'restaurant', 'diner' => 'restaurant', 'auto' => 'automotive', 'pharmacy' => 'pharmacies',
                 'drugstore' => 'pharmacies', 'motel' => 'motels', 'lodge' => 'lodging'];
        $key = function (string $w) use ($same) { $w = $same[$w] ?? $w; return strlen($w) >= 6 ? substr($w, 0, 6) : preg_replace('/(?<!s)s$/', '', $w); };
        $need = [];
        foreach (preg_split('/[^a-z0-9]+/', strtolower($categoryName)) as $w) if (strlen($w) >= 3 && !in_array($w, $generic, true)) $need[$key($w)] = true;
        if (!$need) return self::categoryFits($categoryName, $c);
        $have = [];
        $text = ($c['name'] ?? '') . ' ' . implode(' ', (array) ($c['what']['offerings'] ?? [])) . ' ' . preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', implode(' ', (array) ($c['what']['types'] ?? [])));
        foreach (preg_split('/[^a-z0-9]+/', strtolower($text)) as $w) if (strlen($w) >= 3) $have[$key($w)] = true;
        return !array_diff_key($need, $have);
    }

    /**
     * True when every meaningful word of the category's name appears in the listing's own name,
     * offerings or description, so a guess like "House Cleaning" for "Safe House" never auto-publishes.
     */
    public static function categoryFits(string $categoryName, array $c): bool
    {
        $stem = fn (string $w) => preg_replace('/(?<!s)s$/', '', preg_replace('/ies$/', 'y', $w));
        $skip = ['and', 'the', 'for', 'other', 'services', 'service', 'store', 'shop', 'new', 'used', 'sale', 'sales', 'retail', 'whls', 'nec', 'svc'];
        $words = function (string $t) use ($stem, $skip): array {
            $out = [];
            foreach (preg_split('/[^a-z0-9]+/', strtolower($t)) as $w) {
                if (strlen($w) >= 3 && !in_array($w, $skip, true)) $out[$stem($w)] = true;
            }
            return $out;
        };
        $need = $words($categoryName);
        if (!$need) return false;
        $have = $words(($c['name'] ?? '') . ' ' . implode(' ', (array) ($c['what']['offerings'] ?? [])) . ' ' . ($c['what']['description'] ?? '')
            . ' ' . preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', implode(' ', (array) ($c['what']['types'] ?? []))));
        // Same thing, different word: a food pantry is a food bank, a drugstore a pharmacy.
        foreach (['pantry' => 'bank', 'drugstore' => 'pharmacy', 'counseling' => 'counselor', 'counselor' => 'counseling'] as $a => $b) {
            if (isset($have[$a])) $have[$b] = true;
        }
        return !array_diff_key($need, $have);
    }

    public const OBVIOUS_NOTE = 'auto-merge: the same business as';

    /** Short forms folded to one spelling, so "Apache Behavioral Health Svc" reads as "… Services". */
    private const ABBREV = ['svc' => 'services', 'svcs' => 'services', 'service' => 'services', 'srvcs' => 'services', 'ctr' => 'center', 'cntr' => 'center',
        'centre' => 'center', 'hlth' => 'health', 'med' => 'medical', 'mntns' => 'mountains', 'mtns' => 'mountains', 'mtn' => 'mountain', 'mt' => 'mount',
        'assn' => 'association', 'assoc' => 'association', 'dept' => 'department', 'hosp' => 'hospital', 'intl' => 'international', 'natl' => 'national',
        'st' => 'saint', 'ste' => 'suite', 'dist' => 'district', 'sch' => 'school', 'schl' => 'school', 'unifed' => 'unified', 'usd' => 'unified',
        'clnc' => 'clinic', 'cnslng' => 'counseling', 'regl' => 'regional', 'chr' => 'church', 'bapt' => 'baptist'];
    private const NAME_SKIP = ['of', 'and', 'at', 'for', 'in', 'on', 'a', 'an'];

    /** A name's words, short forms spelled out ("Dist" is "district"), small words left out: [word => true]. */
    public static function nameWords(string $n): array
    {
        $out = [];
        foreach (explode(' ', Identity::nameKey($n)) as $x) {
            $x = self::ABBREV[$x] ?? $x;
            if (in_array($x, ['dba', 'plc', 'pllc', 'llp', 'lp', 'incorporated', 'limited'], true)) continue;   // legal endings folded (decisions/0164 §3.1)
            if (strlen($x) > 3 && str_ends_with($x, 's') && !preg_match('/(ss|us|is)$/', $x)) $x = substr($x, 0, -1);   // plurals: "Guardian Flights" is "Guardian Flight"
            if ($x !== '' && !in_array($x, self::NAME_SKIP, true)) $out[$x] = true;
        }
        return $out;
    }

    /**
     * How alike two business names are (0 to 1), counted against the longer name, so a provider listed at a facility
     * ("Smith, John - Apache Behavioral Health") doesn't read as the facility itself.
     */
    public static function sameName(string $a, string $b): float
    {
        $x = self::nameWords($a); $y = self::nameWords($b);
        if (!$x || !$y) return 0.0;
        return count(array_intersect_key($x, $y)) / max(count($x), count($y));
    }

    /**
     * How a possible match relates to the found listing (decisions/0074):
     *   'same'   the same phone, street address and ZIP, and nearly the same name: the same business;
     *   'branch' nearly the same name at another street number: another location of the same business (Changepoint
     *            Integrated Health's clinics share one name and main number), not a duplicate;
     *   'place'  the same phone, or the same address and ZIP, under a different name: another provider, office or
     *            business at the same facility, not a duplicate;
     *   'maybe'  anything else the crawler flagged: a person decides.
     * @return array{kind:string, name:float, phone:bool, addr:bool}
     */
    public static function relation(array $c, array $l): array
    {
        $val = fn (string $k) => is_array($l['meta'][$k]['value'] ?? null) ? '' : (string) ($l['meta'][$k]['value'] ?? '');
        // its other names count as its name (decisions/0084): "Infinia at Show Low" is the listed "Tall Pines Care And Rehab"
        $name = (string) ($l['name'] ?? ''); $best = self::sameName((string) ($c['name'] ?? ''), $name);
        foreach ((array) ($l['meta']['aka']['value'] ?? []) as $aka) if (is_string($aka) && ($x = self::sameName((string) ($c['name'] ?? ''), $aka)) > $best) { $best = $x; $name = $aka; }
        return self::relationTo($c, $name, $val('phone'), $val('address'), (string) ($l['primary_zip'] ?? ''), $val('website'));
    }

    /** relation() from a match's own name, phone, street address and ZIP (as findLookalikes gives them). */
    public static function relationTo(array $c, string $name, string $phone, string $address, string $zip, string $website = '', array $geo = []): array
    {
        // $geo (decisions/0164 §3.1, the tuned rules): the other one's point ('lat', 'lng'), so "another location" needs two
        // street numbers AND a different phone or points over 150 m apart; 'main_line': the phone is shared by more listings
        // than 0163 §12's number, so it's never proof alone
        // one name is the other with a parent or legal name in front ("Navajo Health Foundation-Sage Memorial Hospital,
        // Inc." and "Sage Memorial Hospital"): with the same phone or the same website in the same ZIP, the same place
        $tail = self::nameEndsWith((string) ($c['name'] ?? ''), $name) || self::nameEndsWith($name, (string) ($c['name'] ?? ''));
        // the same page of the same site (or the same home page of a site that isn't a chain's): a chain's website, shared
        // by its stores, says only "the same brand" (WebIdentity, decisions/0083)
        $sameSite = WebIdentity::samePlace((string) ($c['actions']['website'] ?? ''), $website);
        $myPhone = Identity::digits((string) ($c['actions']['phone'] ?? ''));
        $myAddr = Identity::addr((string) ($c['where']['address'] ?? ''));
        $myZip = substr(trim((string) ($c['where']['zip'] ?? '')), 0, 5);
        $samePhone = $myPhone !== '' && Identity::digits($phone) === $myPhone;
        $sameAddr = $myAddr !== '' && $myZip !== '' && Identity::addr($address) === $myAddr && $zip === $myZip;
        $lat = $c['geo']['latitude'] ?? null; $lng = $c['geo']['longitude'] ?? null;
        $far = null;   // null: no two points to compare
        if (isset($geo['lat'], $geo['lng']) && is_numeric($lat) && is_numeric($lng) && (float) $lat != 0.0) {
            require_once __DIR__ . '/../ListingIdentity.php';
            $far = ListingIdentity::metres((float) $lat, (float) $lng, (float) $geo['lat'], (float) $geo['lng']) > ListingIdentity::setting(null, 'branch_m');
        }
        $nm = self::sameName((string) ($c['name'] ?? ''), $name);
        // the street number each one starts with: two different ones are two places
        $num = fn (string $a) => preg_match('/^\s*(\d+)/', $a, $m) ? $m[1] : '';
        $n1 = $num((string) ($c['where']['address'] ?? '')); $n2 = $num($address);
        // Decided without a person (decisions/0076):
        //   nearly the same name at the same phone and street address, or at the same street number written another way
        //   ("1920 W Commerce Park Dr", "1920 West Commerce Dr"), is the same business: it merges, filling gaps only;
        //   another business name at the same phone and street address is covered by the listed one: a legal name
        //   ("German Dobson Cvs LLC" is the CVS Pharmacy) or a billing company at a hospital (an anesthesia group): it isn't
        //   listed again and nothing of it is copied in;
        //   a person's name there is a provider at the facility; a different name sharing only the phone or only the
        //   address is another business (a suite in the same building, a shared main line);
        //   only a part-similar name ("Little Colorado Behavioral Health" and "Little Colorado Medical Center") is a maybe.
        $person = self::looksPerson((string) ($c['name'] ?? '')) || self::looksPerson($name);
        $sameNum = $n1 !== '' && $n1 === $n2 && $zip === $myZip;
        $noNum = ($n1 === '' || $n2 === '') && $zip === $myZip;   // a route or highway address: nothing to compare
        $otherNum = $n1 !== '' && $n2 !== '' && $n1 !== $n2;
        if (!empty($geo['main_line'])) $samePhone = $samePhone && ($sameAddr || $sameNum);   // a main line: never proof alone
        // another street number is another location only with a different phone or points apart: a typo'd or suite-first
        // number at the same phone isn't a branch (2026-11-15, the duplicates' cause)
        if ($nm >= 0.75 && $otherNum && !$sameAddr && (!$samePhone || $far === true)) $kind = 'branch';
        elseif ($tail && ($samePhone || $sameSite) && $zip === $myZip && !$person && (!$otherNum || $samePhone)) $kind = 'same';   // a parent or legal name in front (decisions/0081); the same phone too: another entrance of the same place
        elseif ($samePhone && $sameAddr && $nm >= 0.75) $kind = 'same';
        elseif ($samePhone && $sameAddr) $kind = $person ? 'place' : 'covered';       // a billing company or a legal name at a listed place: nothing new to list
        elseif ($samePhone && $nm >= 0.75) $kind = 'same';                             // the address written another way ("Hwy 264 And 191", "US 191;SR 264")
        elseif ($sameNum && $nm >= 0.75) $kind = 'same';
        elseif (($sameNum || $sameAddr) && $nm >= 0.5 && !$person) $kind = 'covered'; // "Show Low Unified School District #10" at a listed "Show Low School District": not listed again, nothing copied
        elseif ($nm >= 0.75 && $noNum) $kind = 'same';                                 // "Bia Rt 125", "Bia Route 125"
        elseif (($samePhone || $sameAddr) && $nm < 0.4) $kind = $person ? 'place' : 'other';
        elseif (!$samePhone && !$sameAddr && $nm < 0.75) $kind = 'other';          // only some words in common, nothing else ("White Mountain Imaging", "White Mountain Assn")
        else $kind = 'maybe';
        return ['kind' => $kind, 'name' => $nm, 'phone' => $samePhone, 'addr' => $sameAddr, 'site' => $sameSite];
    }

    /**
     * Whether $long ends with all of $short's words, in order, with something in front ("Navajo Health Foundation-Sage
     * Memorial Hospital, Inc." ends with "Sage Memorial Hospital"). Legal endings (Inc, LLC…) don't count. The short
     * name needs two words or more, so one shared word ("Hospital") is never enough. A name with more AFTER the other
     * ("Sage Memorial Hospital Behavioral Health Clinic") is a department, not the same place, so it doesn't count.
     */
    public static function nameEndsWith(string $long, string $short): bool
    {
        $w = fn (string $n) => array_values(array_filter(explode(' ', Identity::nameKey($n)), fn ($x) => $x !== '' && !in_array($x, ['inc', 'llc', 'pllc', 'pc', 'plc', 'ltd', 'corp', 'co', 'the', 'of', 'and'], true)));
        $l = $w($long); $s = $w($short);
        if (count($s) < 2 || count($l) <= count($s)) return false;
        return array_slice($l, -count($s)) === $s;
    }

    /** Words a business's name has and a person's doesn't. */
    private const BUSINESS_WORDS = ['llc', 'inc', 'pc', 'pllc', 'plc', 'ltd', 'lp', 'llp', 'corp', 'co', 'company', 'center', 'ctr', 'centre', 'clinic',
        'clinics', 'services', 'service', 'svc', 'svcs', 'health', 'healthcare', 'care', 'medical', 'med', 'hospital', 'hospice', 'pharmacy', 'dental',
        'dentistry', 'dentures', 'group', 'associates', 'assoc', 'association', 'society', 'church', 'school', 'schools', 'district', 'county', 'city',
        'town', 'tribe', 'tribal', 'nation', 'apache', 'navajo', 'hopi', 'zuni', 'department', 'dept', 'office', 'agency', 'foundation', 'institute',
        'program', 'counseling', 'therapy', 'rehab', 'rehabilitation', 'home', 'homes', 'house', 'senior', 'citizens', 'club', 'store', 'shop', 'market',
        'restaurant', 'cafe', 'bank', 'insurance', 'realty', 'motel', 'hotel', 'inn', 'ranch', 'farm', 'access', 'point', 'bureau', 'council', 'court',
        'library', 'museum', 'integrated', 'family', 'practice', 'partners', 'solutions', 'systems', 'supply', 'wellness', 'recovery', 'mountain',
        'valley', 'regional', 'community', 'center', 'centers', 'the', 'and', 'of'];

    /**
     * Whether a name reads as one person's ("Hirrlinger, Erinn", "Dalmolin Carly Anne", "Cole Harry Shelton Jr"): two to
     * four words, no business word, no digits. A practice ("Randolph P Platt DDS, PC") is a business.
     */
    public static function looksPerson(string $name): bool
    {
        $n = mb_strtolower(trim($name));
        if ($n === '' || preg_match('/\d/', $n)) return false;
        if (preg_match('/\b(at|of|on|by)\b/', $n)) return false;   // "Infinia At Showlow": a place's name, not a person's (decisions/0084)
        $w = preg_split('/[^a-z]+/', preg_replace('/\b(md|do|dds|dmd|phd|psyd|lcsw|lpc|lmft|np|pa|rn|jr|sr|ii|iii)\b/', ' ', $n), -1, PREG_SPLIT_NO_EMPTY);
        if (count($w) < 2 || count($w) > 4) return false;
        return !array_intersect($w, self::BUSINESS_WORDS) && !preg_match('/\b(pc|pllc|plc|llc|inc)\b/', $n);
    }

    /** The ids of a candidate's possible matches, the exact one first, each once. */
    public static function matchIds(array $c): array
    {
        $ids = [];
        if (!empty($c['match']['exact']['id'])) $ids[] = (int) $c['match']['exact']['id'];
        foreach ((array) ($c['match']['possible'] ?? []) as $m) if (!empty($m['id'])) $ids[] = (int) $m['id'];
        return array_values(array_unique($ids));
    }

    /**
     * The listing this candidate obviously is, or null (decisions/0074, 0076): a possible match that is the 'same'
     * business by relation(). Others at the same facility under other names (providers, offices) don't count.
     * @return array{id:int, why:string}|null
     */
    public static function obvious(PDO $pdo, array $c): ?array
    {
        // when the directory already has it twice, the found one goes into the claimed one, else the oldest (gaps only)
        $hits = [];
        foreach (self::matchIds($c) as $id) {
            $l = IntakeStager::existingListing($pdo, $id);
            if (!$l) continue;
            $r = self::relation($c, $l);
            // the same phone, street address and ZIP, under another name, and the same kind of place: the same place
            // renamed or under its legal name ("Infinia at Show Low" is "Tall Pines Care And Rehab"); its name is kept as
            // an other name (decisions/0084). Another kind there (a billing company at a hospital) stays 'covered'.
            $sameKind = !empty($l['category_id']) && (int) ($c['category_suggestions'][0]['id'] ?? 0) === (int) $l['category_id'];
            if ($r['kind'] === 'covered' && $sameKind && !self::looksPerson((string) ($c['name'] ?? ''))) {
                $hits[] = ['id' => $id, 'claimed' => !empty($l['claimed']), 'why' => 'the same phone, street address and ZIP, and the same kind of place, under another name (kept as an other name)'];
                continue;
            }
            if ($r['kind'] !== 'same') continue;
            $why = array_filter([$r['phone'] ? 'same phone' : '', !empty($r['site']) ? 'same website' : '', $r['addr'] ? 'same street address' : '', 'same ZIP']);
            $nm = $r['name'] >= 0.75 ? (int) round($r['name'] * 100) . '% of the name in common' : 'its name with a parent or legal name in front';
            $hits[] = ['id' => $id, 'claimed' => !empty($l['claimed']), 'why' => implode(', ', $why) . '; ' . $nm];
        }
        if (!$hits) return null;
        usort($hits, fn ($x, $y) => [$y['claimed'], $x['id']] <=> [$x['claimed'], $y['id']]);
        return ['id' => $hits[0]['id'], 'why' => $hits[0]['why'] . (count($hits) > 1 ? '; it\'s listed ' . count($hits) . ' times, so into #' . $hits[0]['id'] : '')];
    }

    /**
     * Merges a found listing into an existing one by filling only what the existing one is missing (never overwriting
     * what it has: a person's, the owner's or the crawler's). With nothing new, the found one is just closed.
     * $adminId null: done on its own. Returns ['entity_id', 'action' => 'merged'|'closed', 'filled' => [fields]].
     */
    public static function fillGaps(PDO $pdo, int $rowId, int $entityId, array $c, ?int $adminId, string $note, ?array $in = null, int $categoryId = 0): array
    {
        $ex = IntakeStager::existingListing($pdo, $entityId) ?? throw new InvalidArgumentException('That listing no longer exists.');
        $in ??= self::input($c, $categoryId ?: (int) (self::evaluate($c)['category_id'] ?? 0));
        $choices = [];
        foreach (IntakeStager::MERGE_FIELDS as $f => [$type, $key]) {
            if ($type === 'core') {
                if ($f === 'category_id' && empty($ex['category_id']) && !empty($in['category_id'])) $choices[$f] = 'replace';
                if ($f === 'pin' && $ex['latitude'] === null && !empty($in['latitude'])) $choices[$f] = 'replace';
                continue;
            }
            $have = $ex['meta'][$key]['value'] ?? null; $new = $in[$f] ?? null;
            if ($new === null || $new === '' || $new === []) continue;
            if ($type === 'list') {   // only items it doesn't list yet
                $old = array_map(fn ($x) => mb_strtolower(trim((string) $x)), is_array($have) ? $have : []);
                if (array_filter((array) $new, fn ($x) => !in_array(mb_strtolower(trim((string) $x)), $old, true))) $choices[$f] = 'append';
                continue;
            }
            if ($have === null || $have === '' || $have === []) $choices[$f] = 'replace';
        }
        // the pause (2026-11-28; the IT Manager's Tier 4): with no person, About text and hours aren't written into a
        // published listing (the audit's C1 and C2). They go to Review as a suggestion; a person's own merge is untouched
        if ($adminId === null && self::pausedWhy() !== null) {
            $held = array_values(array_intersect(array_keys($choices), ['description', 'hours']));
            if ($held) {
                $choices = array_diff_key($choices, array_flip($held));
                self::holdForReview($pdo, $entityId, array_intersect_key($in, array_flip($held)), 'crawler-merge', 'A find matched to this listing (staged row #' . $rowId . ') gives');
                $note .= '; held for Review while paused: ' . implode(', ', $held);
            }
        }
        if ($choices) {
            try {
                IntakeStager::merge($pdo, $rowId, $entityId, $in, $choices, $adminId);
                if ($adminId === null) $pdo->prepare('UPDATE ' . Database::t('crawler_raw_index') . ' SET review_note = ? WHERE id = ?')->execute([mb_substr($note, 0, 1000), $rowId]);
                return ['entity_id' => $entityId, 'action' => 'merged', 'filled' => array_keys($choices)];
            } catch (InvalidArgumentException $x) {
                if ($adminId !== null) throw $x;   // a person sees why; on its own, a value that won't validate just isn't added
            }
        }
        IntakeStager::reject($pdo, $rowId, mb_substr($note . '; nothing new to add', 0, 1000), $adminId);
        $pdo->prepare("UPDATE " . Database::t('crawler_raw_index') . " SET entity_id = ? WHERE id = ?")->execute([$entityId, $rowId]);
        require_once __DIR__ . '/../Identity.php'; Identity::adopt($pdo, $rowId, $entityId);   // nothing new for the listing, but its sources count (decisions/0133)
        try { if (trim((string) ($c['name'] ?? '')) !== '') Aliases::add($pdo, $entityId, [(string) $c['name']], $adminId ? 'admin' : 'ai_crawler'); } catch (Throwable) {}   // its other name (decisions/0084)
        return ['entity_id' => $entityId, 'action' => 'closed', 'filled' => []];
    }

    /**
     * The pipeline's pass over waiting listings (decisions/0076), oldest-checked first, $limit at a time: each is matched
     * again against what's listed now; the same business merges (gaps only), a ready one publishes (and joins its other
     * locations' Nexus), and the rest keep their fresh matches for the next pass or, once exhausted, for Review.
     * @return array{merged:int, published:int, covered:int, checked:int}
     */
    public static function advance(PDO $pdo, int $limit = 150): array
    {
        $out = ['merged' => 0, 'published' => 0, 'covered' => 0, 'checked' => 0];
        $st = $pdo->query("SELECT id, raw_payload FROM " . Database::t('crawler_raw_index') . " WHERE status = 'staged' AND payload_type = 'listing'
                            ORDER BY JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.advanced_at')), confidence_score DESC, id LIMIT " . max(1, min(500, $limit)));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $c = json_decode((string) $r['raw_payload'], true);
            if (!is_array($c)) continue;
            $out['checked']++;
            $rowId = (int) $r['id'];
            try {
                $c['match'] = IntakeStager::freshMatch($pdo, $c);
                $c['category_suggestions'] = IntakeStager::suggestFor($pdo, $c);   // today's reading, not the one it was staged with
                $c['advanced_at'] = date('c');
                if (empty($c['hold'])) {
                    if (!self::obvious($pdo, $c) && ($cov = self::coveredBy($pdo, $c))) {
                        IntakeStager::reject($pdo, $rowId, mb_substr('auto: covered by #' . $cov['id'] . ' ' . $cov['name'] . ' (the same phone and street address under another name); not listed again', 0, 1000), null);
                        $pdo->prepare('UPDATE ' . Database::t('crawler_raw_index') . ' SET entity_id = ? WHERE id = ?')->execute([$cov['id'], $rowId]);
                        require_once __DIR__ . '/../Identity.php'; Identity::adopt($pdo, $rowId, (int) $cov['id']);
                        $out['covered'] = ($out['covered'] ?? 0) + 1;
                        continue;
                    }
                    if ($m = self::obvious($pdo, $c)) {
                        self::fillGaps($pdo, $rowId, $m['id'], $c, null, self::OBVIOUS_NOTE . ' #' . $m['id'] . ' (' . $m['why'] . ')');
                        $out['merged']++;
                        continue;
                    }
                    if (self::enabled() && self::evaluate($c)['eligible']) {
                        $res = self::tryImport($pdo, $rowId, $c);
                        if ($res !== null) { if (($res['action'] ?? '') === 'created') $out['published']++; else $out['merged']++; continue; }
                    }
                }
                $pdo->prepare("UPDATE " . Database::t('crawler_raw_index') . " SET raw_payload = ? WHERE id = ? AND status = 'staged'")
                    ->execute([json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $rowId]);
            } catch (Throwable $x) { error_log('pipeline advance #' . $rowId . ': ' . $x->getMessage()); }
        }
        return $out;
    }

    /** The listed business that covers this one (relation 'covered'), or null. */
    public static function coveredBy(PDO $pdo, array $c): ?array
    {
        foreach (self::matchIds($c) as $id) {
            $l = IntakeStager::existingListing($pdo, $id);
            if ($l && self::relation($c, $l)['kind'] === 'covered') return ['id' => $id, 'name' => (string) $l['name']];
        }
        return null;
    }

    /**
     * A new listing that is another location of a listed business, with the evidence of a shared website or main phone,
     * joins that business's Nexus, made now if it has none (decisions/0054 §6, 0076). Never from a similar name alone.
     */
    public static function autoNexus(PDO $pdo, int $entityId, array $c): void
    {
        if (!class_exists('Nexus')) require_once __DIR__ . '/../Nexus.php';
        if (!Nexus::ready($pdo)) return;
        $site = self::hostOf((string) ($c['actions']['website'] ?? ''));
        $phone = Identity::digits((string) ($c['actions']['phone'] ?? ''));
        foreach ((array) ($c['match']['possible'] ?? []) as $m) {
            if (empty($m['id']) || (int) $m['id'] === $entityId || !isset($m['address'])) continue;
            if (self::relationTo($c, (string) $m['name'], (string) ($m['phone'] ?? ''), (string) $m['address'], (string) ($m['zip'] ?? ''))['kind'] !== 'branch') continue;
            $l = IntakeStager::existingListing($pdo, (int) $m['id']);
            if (!$l) continue;
            $lSite = self::hostOf(is_array($l['meta']['website']['value'] ?? null) ? '' : (string) ($l['meta']['website']['value'] ?? ''));
            // the same brand's website (sub-domains folded: "locations.theupsstore.com" is The UPS Store) or the same main phone
            $evidence = ($site !== '' && WebIdentity::sameBrand($site, $lSite)) || ($phone !== '' && $phone === Identity::digits((string) ($m['phone'] ?? '')));
            if (!$evidence) continue;
            // one provider at two offices ("Moore, Mathew D Dc") is a person, not an organization: no Nexus by their name
            if (self::looksPerson((string) ($c['name'] ?? '')) || self::looksPerson((string) $l['name'])) continue;
            $st = $pdo->prepare('SELECT nexus_id FROM entities WHERE id = ?'); $st->execute([(int) $m['id']]);
            $nid = (int) $st->fetchColumn();
            if (!$nid) {
                $name = mb_strlen((string) $l['name']) <= mb_strlen((string) ($c['name'] ?? '')) ? (string) $l['name'] : (string) $c['name'];
                $nid = Nexus::create($pdo, ['name' => $name, 'kind' => 'brand', 'website' => ($b = WebIdentity::brand($site !== '' ? $site : $lSite)) !== '' ? 'https://' . $b : ''], null);
                Nexus::link($pdo, (int) $m['id'], $nid, 'location');
            }
            Nexus::link($pdo, $entityId, $nid, 'location');
            return;
        }
    }

    /** approve() input built from the candidate's own values. */
    public static function input(array $c, int $categoryId): array
    {
        return [
            'name' => $c['name'] ?? '', 'address' => $c['where']['address'] ?? '', 'city' => $c['where']['city'] ?? '',
            'state' => $c['where']['state'] ?? '', 'zip' => $c['where']['zip'] ?? '',
            'phone' => $c['actions']['phone'] ?? '', 'description' => mb_substr((string) ($c['what']['description'] ?? ''), 0, 2000),
            'category_id' => $categoryId,
            'latitude' => $c['geo']['latitude'] ?? '', 'longitude' => $c['geo']['longitude'] ?? '',
            'email' => $c['actions']['email'] ?? '', 'website' => $c['actions']['website'] ?? '', 'whatsapp' => $c['actions']['whatsapp'] ?? '',
            'contact_url' => $c['actions']['contact_url'] ?? '',
            'source_url' => $c['where']['location_page'] ?? '',
            'hours' => $c['when']['hours'] ?? [], 'service_area' => $c['where']['service_area'] ?? [],
            'offerings' => $c['what']['offerings'] ?? [], 'audience' => $c['who']['audience'] ?? [],
            'highlights' => $c['why']['highlights'] ?? [], 'social' => $c['actions']['social'] ?? [],
        ];
    }

    /**
     * Publishes a staged row if it passes every guardrail. Returns the approve() result, or null when it
     * stays in Listing Intake. Never throws for an ineligible or invalid candidate.
     */
    public static function tryImport(PDO $pdo, int $rowId, array $c): ?array
    {
        if ($m = self::obvious($pdo, $c)) {   // the same business already listed: fill its gaps, no person needed (decisions/0074)
            try { return self::fillGaps($pdo, $rowId, $m['id'], $c, null, self::OBVIOUS_NOTE . ' #' . $m['id'] . ' (' . $m['why'] . ')'); }
            catch (Throwable $x) { error_log('auto merge: ' . $x->getMessage()); }
        }
        if (!self::enabled()) return null;
        if (!empty($c['hold'])) return null;   // held for a person (a search target, maybe outside our areas: decisions/0066)
        $e = self::evaluate($c);
        if (!$e['eligible']) return null;
        $note = self::NOTE_PREFIX . ": score {$e['score']}; " . implode('; ', array_column($e['checks'], 'label'))
              . '; sources: ' . implode(', ', self::sourceHosts($c));
        try {
            $r = IntakeStager::approve($pdo, $rowId, self::input($c, $e['category_id']), null, mb_substr($note, 0, 1000));
        } catch (InvalidArgumentException) {
            return null;    // e.g. missing address or city: a person finishes it in Listing Intake
        }
        if ($r['action'] === 'created') {
            try { self::autoNexus($pdo, (int) $r['entity_id'], $c); } catch (Throwable $x) { error_log('auto nexus: ' . $x->getMessage()); }
            // Starts below a person-approved listing, per decisions/0044.
            $pdo->prepare('UPDATE entities SET trust_score = GREATEST(0, trust_score + :d) WHERE id = :id')
                ->execute(['d' => TrustScore::ENTITY_AUTO_IMPORT_ADJUST, 'id' => $r['entity_id']]);
        }
        return $r;
    }

    /** Staged rows with their guardrail results, best first. */
    public static function staged(PDO $pdo, int $limit = 200): array
    {
        $out = [];
        foreach (IntakeStager::listRows($pdo, 'staged', 500, 0, true) as $r) {   // the most confident 500, however many wait
            if (!is_array($r['payload'])) continue;
            $r['eval'] = self::evaluate($r['payload']);
            $out[] = $r;
        }
        usort($out, fn ($a, $b) => $b['eval']['score'] <=> $a['eval']['score']);
        return array_slice($out, 0, $limit);
    }

    /**
     * Approves the chosen staged rows as they are (a person's batch decision). Rows needing a merge, or
     * with no category, are skipped with the reason.
     * @return array{approved:int, skipped:array<int,string>}
     */
    public static function quickApprove(PDO $pdo, array $rowIds, int $adminId): array
    {
        $approved = 0;
        $skipped = [];
        foreach (array_unique(array_map('intval', $rowIds)) as $id) {
            $st = $pdo->prepare("SELECT raw_payload FROM " . Database::t('crawler_raw_index') . " WHERE id = ? AND status = 'staged'");
            $st->execute([$id]);
            $c = json_decode((string) $st->fetchColumn(), true);
            if (!is_array($c)) { $skipped[$id] = 'no longer staged'; continue; }
            $e = self::evaluate($c);
            if ($e['category_id'] === null) { $skipped[$id] = 'no category fits: choose one in Check and fix'; continue; }
            $uniq = array_values(array_filter($e['checks'], fn ($k) => $k['code'] === 'unique'))[0] ?? ['pass' => true];
            if (!$uniq['pass']) { $skipped[$id] = 'may already be listed: compare it side by side'; continue; }   // other locations and providers aren't duplicates (0076)
            try {
                IntakeStager::approve($pdo, $id, self::input($c, $e['category_id']), $adminId, 'quick approve: score ' . $e['score']);
                $approved++;
            } catch (InvalidArgumentException $ex) {
                $skipped[$id] = $ex->getMessage();
            }
        }
        return ['approved' => $approved, 'skipped' => $skipped];
    }

    /** Listings published automatically, newest first, for spot-checks. */
    public static function recent(PDO $pdo, int $limit = 100): array
    {
        $st = $pdo->prepare(
            "SELECT r.id AS row_id, r.entity_id, r.review_note, r.reviewed_at, e.name, e.is_suppressed, e.status, mc.name AS cluster
               FROM " . Database::t('crawler_raw_index') . " r
               JOIN entities e ON e.id = r.entity_id
               LEFT JOIN micro_clusters mc ON mc.id = e.micro_cluster_id
              WHERE r.status = 'published' AND r.reviewed_by_user_id IS NULL AND r.review_note LIKE :p
              ORDER BY r.reviewed_at DESC
              LIMIT " . max(1, min(500, $limit))
        );
        $st->execute(['p' => self::NOTE_PREFIX . '%']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Pulls an auto-imported listing off the public site (reversible). */
    public static function suppress(PDO $pdo, int $entityId, bool $suppress = true): void
    {
        $pdo->prepare('UPDATE entities SET is_suppressed = ?, updated_at = NOW() WHERE id = ?')->execute([$suppress ? 1 : 0, $entityId]);
        if (!$suppress) { require_once __DIR__ . '/../ListingIdentity.php'; try { ListingIdentity::beforeShow($pdo, $entityId, true); } catch (Throwable $x) { error_log('identity on show: ' . $x->getMessage()); } }   // decisions/0164 §3.1
    }
}
