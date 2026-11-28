<?php

declare(strict_types=1);

require_once __DIR__ . '/PlaceScope.php';
require_once __DIR__ . '/SearchText.php';
require_once __DIR__ . '/ListingSafety.php';
require_once __DIR__ . '/Links.php';
require_once __DIR__ . '/Hours.php';
if (is_file(__DIR__ . '/Community.php')) require_once __DIR__ . '/Community.php';
if (is_file(__DIR__ . '/crawler/RecBulk.php')) require_once __DIR__ . '/crawler/RecBulk.php';
if (is_file(__DIR__ . '/Landmarks.php')) require_once __DIR__ . '/Landmarks.php';
require_once __DIR__ . '/NameText.php';

/**
 * api/lib/SearchIndex.php — the 5W index behind universal search (decisions/0053 §8).
 *
 * One row per searchable thing (a listing, an outdoors place, a story, a community group, a topic), tagged with:
 *   What   terms: its name, category and group, offerings, activities, type, topic;
 *   Who    public only: the managing agency or organization, and who it is for or run by (veterans, seniors,
 *          kids and families, tribal nations and communities, "-owned" as the listing itself says);
 *   When   a listing's hours as published (read by Hours.php when searching);
 *   Where  ZIP, town, state, cluster, place key and point;
 *   Why    intents (eat, stay, heal, help, explore, connect, buy, learn), read from what it is.
 * Nothing personal is indexed: no member profiles, contacts, messages, or members' names.
 *
 * build():   one batch of one kind, for the admin's "Build" button (Admin > Crawler > Data loads > Search index).
 * refresh(): keeps it current without a cron: at most every 15 minutes, a search picks up listings changed since the
 *            last look and rebuilds the small kinds (stories, groups, topics).
 * find():    candidates for the 5W reading of a query, scored and ordered.
 */
require_once __DIR__ . '/CategoryAlso.php';

final class SearchIndex
{
    public const KINDS = ['listing', 'outdoors', 'story', 'group', 'topic'];
    public const KIND_LABEL = ['listing' => 'Listing', 'outdoors' => 'Outdoors', 'story' => 'Story', 'group' => 'Group', 'topic' => 'Topic', 'experience' => 'Experience'];
    /** The site's sections (the header's funnels, decisions/0055 §2) each kind lives in. Topics split: a category is Get Local, a community topic is Connect. */
    public const FUNNELS = ['explore' => "Let's Explore", 'local' => 'Get Local', 'social' => 'Connect', 'market' => 'Marketplace'];
    public static function funnelOf(string $kind, string $id = ''): string
    {
        return match ($kind) { 'listing' => 'local', 'topic' => str_starts_with($id, 'ct:') ? 'social' : 'local', 'group' => 'social', default => 'explore' };
    }
    /** SQL for the kinds in a section. */
    private static function funnelSql(string $f): string
    {
        return match ($f) {
            'local' => "(si.kind = 'listing' OR (si.kind = 'topic' AND si.item_id LIKE 'cat:%'))",
            'social' => "(si.kind = 'group' OR (si.kind = 'topic' AND si.item_id LIKE 'ct:%'))",
            'explore' => "si.kind IN ('outdoors', 'story')",
            default => '1 = 0',   // Market: nothing to search yet
        };
    }
    public const KIND_PLURAL = ['listing' => 'Listings', 'outdoors' => 'Outdoors', 'story' => 'Stories & guides', 'group' => 'Groups', 'topic' => 'Topics', 'experience' => 'Experiences'];
    private const STATUSES = ['unclaimed', 'claimed-unverified', 'pending-verification', 'verified'];

    /** Why: what a thing is for, read from its name, category, type and activities. Order matters only for display. */
    public const WHY = [
        'eat'     => '/\b(restaurants?|food|foods|cafe|café|coffee|bakery|bakeries|diner|pizza|grill|bar|bars|pub|brew\w*|taco\w*|burgers?|kitchen|catering|deli|ice cream|donuts?|bbq|barbecue|steak\w*|cantina|dining|eatery|mexican|chinese|italian|sandwich\w*|fast food|drive.?in)\b/u',
        'stay'    => '/\b(hotels?|motels?|lodges?|lodging|inns?|cabins?|rv parks?|campgrounds?|camping|resorts?|bed and breakfast|b&b|vacation rentals?)\b/u',
        'heal'    => '/\b(health\w*|medical|clinics?|hospitals?|doctors?|physicians?|dental|dentists?|therap\w*|chiropract\w*|pharmac\w*|counsel\w*|mental|wellness|optometr\w*|urgent care|nursing|rehab\w*|physical therapists?|behavioral)\b/u',
        'help'    => '/\b(food banks?|pantr\w*|shelters?|assistance|crisis|legal aid|social services?|recovery|veterans services|housing authority|domestic violence|hotlines?|outreach|charit\w*|non.?profits?|human services|family services|community action)\b/u',
        'explore' => '/\b(parks?|trails?|trailheads?|recreation areas?|recreation centers?|lakes?|national forests?|wilderness|museums?|national monuments?|scenic|overlooks?|hiking|fishing|visitor centers?|tours?|historic\w*|galler\w*|attractions?|picnic\w*|day use|rivers?|canyons?|campgrounds?|skiing|ski areas?|golf|outdoors?|amusement\w*|bowling|theaters?|theatres?|zoos?)\b/u',
        'connect' => '/\b(church\w*|communit\w*|clubs?|associations?|chapter|volunteer\w*|lodge hall|grange|senior centers?|community centers?|fellowship|ministr\w*|group)\b/u',
        'buy'     => '/\b(stores?|shops?|shopping|retail\w*|markets?|boutiques?|suppl\w*|hardware|grocer\w*|dealers?|dealerships?|feed|gifts?|thrift|furniture|clothing|jewel\w*|nurser\w*|florists?)\b/u',
        'fix'     => '/\b(contractors?|construction|remodel\w*|plumb\w*|electric(al|ians?)|roof\w*|hvac|heating|air conditioning|handyman|repair\w*|flooring|cabinets?|painting|landscap\w*|lawn|pest|exterminat\w*|septic|home improvement|carpet\w*|windows?|garage doors?|concrete|masonry|fenc\w*|builders?|movers?|cleaning|janitorial|locksmiths?|appliances?)\b/u',
        'learn'   => '/\b(schools?|colleges?|librar\w*|education\w*|classes|training|tutor\w*|academ\w*|preschools?|universit\w*|museums?|learning)\b/u',
    ];
    public const WHY_LABEL = ['eat' => 'Somewhere to eat', 'stay' => 'Somewhere to stay', 'heal' => 'Care and health', 'help' => 'Getting help',
                              'explore' => 'Things to do', 'connect' => 'Meeting people', 'buy' => 'Shopping', 'learn' => 'Learning', 'fix' => 'Repairs and projects'];
    /** Words that carry the question, not the thing ("find me someone to…"): left out of matching. */
    public const STOP = ['my', 'our', 'your', 'me', 'i', 'we', 'us', 'the', 'a', 'an', 'of', 'for', 'to', 'with', 'and', 'or', 'some', 'need', 'needs', 'want',
        'looking', 'find', 'get', 'someone', 'somebody', 'anyone', 'who', 'can', 'could', 'please', 'near', 'around', 'here', 'nearby', 'local', 'best', 'good',
        'great', 'cheap', 'affordable', 'top', 'recommended', 'that', 'this', 'do', 'does', 'is', 'are', 'on', 'at', 'in', 'by', 'it', 'up'];
    /** Words that help the order but shouldn't be required ("remodel my home": home is where, not what). */
    public const SOFT = ['home', 'house', 'place', 'family', 'service', 'services', 'company', 'shop', 'store', 'business',
        'new', 'newest', 'latest', 'recent', 'spot', 'spots', 'joint', 'town'];   // "a new pizza spot": pizza is the thing

    /** Who it's for or run by. Read from the thing's own name, category and description; never guessed about a person. */
    public const AUDIENCE = [
        'veterans' => '/\b(veterans?|vfw|american legion|amvets|va (clinic|medical|office))\b/u',
        'seniors'  => '/\b(seniors?|elders?|elderly|aging|older adults?|retirement|assisted living)\b/u',
        'kids'     => '/\b(youth|kids?|children|child|teens?|boys (and|&) girls|head start|preschool\w*|day ?care|child ?care)\b/u',
        'families' => '/\b(famil(y|ies)|parents?|parenting)\b/u',
        'women'    => '/\b(women|womens|mothers?|moms?|maternity)\b/u',
        // tribal: only the thing's own words for it (Apache and Navajo are also county and forest names)
        'tribal'   => '/\b(tribal|tribes?|nation|pueblo|chapter house)\b/u',
    ];
    /**
     * Outings (Jason, 2026-11-10, "place to take my kids": "Kids? Ages? Type of activities?"): who's coming or the kind
     * of outing, read from the words, chooses the kinds of places that fit, as whole words in a thing's terms (its name,
     * category, type, activities). Each: [label, words that fit, words that rule it out]. Ages and settings narrow the
     * family's list or put some first; UniversalSearch::outing() reads them.
     */
    public const OUTINGS = [
        'family' => ['With kids', ['playground', 'playgrounds', 'zoo', 'zoos', 'zoological', 'aquarium', 'aquariums', 'museum', 'museums', 'library', 'libraries',
            'park', 'parks', 'splash pad', 'splash pads', 'water park', 'water parks', 'mini golf', 'miniature golf', 'putt putt', 'bowling', 'trampoline',
            'arcade', 'arcades', 'family fun', 'fun center', 'fun centers', 'amusement', 'amusement parks', 'skating', 'roller rink', 'ice rink', 'skate park',
            'swimming pool', 'swimming pools', 'aquatic', 'aquatics', 'nature center', 'science center', 'discovery center', 'children s museum', 'childrens museum',
            'botanical', 'garden', 'gardens', 'farm', 'farms', 'petting', 'pumpkin patch', 'ice cream', 'frozen yogurt', 'gelato', 'carousel', 'laser tag',
            'go kart', 'go karts', 'batting cages', 'climbing gym', 'rock climbing', 'planetarium', 'observatory', 'recreation center', 'recreation centers',
            'picnic', 'picnic area', 'lake', 'lakes', 'beach', 'nature trail', 'movie theater', 'movie theaters', 'cinema', 'escape room', 'escape rooms',
            'dinosaur', 'train', 'railroad', 'wildlife', 'visitor center', 'family entertainment']],
        'dog' => ['With your dog', ['dog park', 'dog parks', 'dog friendly', 'pet friendly', 'off leash', 'park', 'parks', 'trail', 'trails', 'trailhead', 'lake',
            'lakes', 'beach', 'preserve', 'open space', 'patio', 'brewery', 'pet supplies', 'pet stores', 'dog grooming']],
        'date' => ['Date night', ['fine dining', 'steakhouse', 'steak house', 'wine', 'winery', 'wineries', 'wine bar', 'cocktail', 'cocktails', 'lounge', 'bistro',
            'italian', 'french', 'sushi', 'tapas', 'dessert', 'desserts', 'chocolate', 'theater', 'theaters', 'theatre', 'live music', 'music venue', 'jazz',
            'comedy', 'comedy club', 'concert', 'concerts', 'observatory', 'planetarium', 'scenic', 'overlook', 'viewpoint', 'botanical', 'garden', 'gardens',
            'spa', 'day spas', 'art gallery', 'art galleries', 'galleries', 'museum', 'museums', 'dance', 'dancing', 'brewery', 'breweries', 'rooftop', 'cinema',
            'movie theater', 'bowling', 'escape room', 'mini golf']],
    ];
    /** The places that fit an age (put first) or a setting (only those), within an outing's list. */
    public const OUTING_AGES = [
        'little' => ['Toddlers and little ones', ['playground', 'playgrounds', 'splash pad', 'library', 'libraries', 'petting', 'children s museum', 'childrens museum',
            'zoo', 'aquarium', 'carousel', 'farm', 'park', 'parks', 'train', 'ice cream', 'discovery center', 'nature center', 'picnic']],
        'kids' => ['Kids', ['playground', 'zoo', 'aquarium', 'museum', 'science center', 'children s museum', 'childrens museum', 'mini golf', 'miniature golf', 'bowling',
            'trampoline', 'water park', 'splash pad', 'skating', 'family fun', 'fun center', 'park', 'library', 'nature center', 'ice cream', 'dinosaur', 'farm']],
        'teens' => ['Teens', ['arcade', 'arcades', 'trampoline', 'laser tag', 'go kart', 'go karts', 'escape room', 'escape rooms', 'climbing gym', 'rock climbing',
            'skate park', 'bowling', 'mini golf', 'movie theater', 'cinema', 'water park', 'amusement', 'batting cages', 'ice rink', 'roller rink', 'lake', 'trail']],
    ];
    public const OUTING_SETTINGS = [
        'indoor' => ['Indoors', ['museum', 'museums', 'library', 'libraries', 'bowling', 'arcade', 'arcades', 'trampoline', 'aquarium', 'aquariums', 'laser tag',
            'escape room', 'escape rooms', 'climbing gym', 'roller rink', 'ice rink', 'skating', 'science center', 'discovery center', 'children s museum',
            'childrens museum', 'planetarium', 'movie theater', 'movie theaters', 'cinema', 'theater', 'theaters', 'art gallery', 'art galleries', 'galleries',
            'family fun', 'fun center', 'fun centers', 'ice cream', 'frozen yogurt', 'gelato', 'recreation center', 'recreation centers', 'aquatic', 'mall',
            'shopping center', 'spa', 'day spas', 'dessert', 'desserts', 'comedy club', 'wine bar', 'lounge', 'family entertainment']],
        'outdoor' => ['Outdoors', ['playground', 'playgrounds', 'zoo', 'zoos', 'park', 'parks', 'splash pad', 'water park', 'mini golf', 'miniature golf',
            'skate park', 'botanical', 'garden', 'gardens', 'farm', 'farms', 'petting', 'pumpkin patch', 'go kart', 'go karts', 'batting cages', 'picnic',
            'lake', 'lakes', 'beach', 'trail', 'trails', 'trailhead', 'nature trail', 'nature center', 'preserve', 'open space', 'scenic', 'overlook',
            'viewpoint', 'dog park', 'wildlife', 'observatory']],
    ];
    /** Never an outing, whatever its words (an RV park isn't a park for the kids). */
    public const OUTING_NOT = ['inn', 'inns', 'hotel', 'hotels', 'motel', 'motels', 'lodging', 'suites', 'historic district', 'rv park', 'rv parks', 'mobile home', 'manufactured home', 'trailer park', 'industrial park', 'business park', 'office park',
        'park model', 'parking', 'liquor', 'smoke shop', 'vape', 'tobacco', 'cannabis', 'dispensary', 'marijuana', 'tattoo', 'casino', 'adult', 'strip club',
        'night club', 'nightclub', 'bail bonds', 'pawn', 'storage', 'funeral', 'cemetery', 'real estate', 'apartments', 'property management', 'contractor',
        'contractors', 'insurance', 'attorney', 'attorneys', 'lawyer', 'dental', 'dentist', 'dentists', 'clinic', 'pharmacy', 'pharmacies', 'church', 'churches',
        'school district', 'preschool', 'day care', 'daycare', 'child care', 'auto', 'towing', 'plumbing', 'roofing', 'landscaping', 'pool service',
        'pool supplies', 'pool cleaning', 'pool builders', 'pool contractors', 'spa services', 'garden centers', 'nursery', 'farm supply', 'farm equipment', 'feed'];

    /** The words that fit an outing ['for' => family|dog|date|'', 'age' => …, 'setting' => indoor|outdoor|''], or []. */
    public static function outingWords(array $o): array
    {
        $for = self::OUTINGS[$o['for'] ?? ''][1] ?? [];
        $set = self::OUTING_SETTINGS[$o['setting'] ?? ''][1] ?? [];
        if ($for && $set) { $both = array_values(array_intersect($for, $set)); return $both ?: $set; }
        return $for ?: $set;
    }

    public const AUDIENCE_LABEL = ['veterans' => 'Veterans', 'seniors' => 'Seniors', 'kids' => 'Kids and youth', 'families' => 'Families',
                                   'women' => 'Women', 'tribal' => 'Tribal nations and communities'];
    /**
     * What's served, read from the whole description, offerings and name (not only the category): each match adds the
     * words found and the cuisine they point to, so "mexican" finds a place whose description says "tacos and tamales".
     */
    public const FOOD = [
        'mexican'     => '/\b(mexican|tex.?mex|tacos?|taqueria|burritos?|enchiladas?|tamales?|carne asada|menudo|pozole|posole|chile rellenos?|fajitas?|quesadillas?|tortas?|horchata|sopapillas?)\b/u',
        'southwestern'=> '/\b(southwestern|new mexican|green chile|red chile|chile verde|navajo tacos?|fry ?bread|blue corn|mutton stew)\b/u',
        'native foods'=> '/\b(fry ?bread|navajo tacos?|blue corn|mutton stew|indian tacos?)\b/u',
        'italian'     => '/\b(italian|pasta|lasagna|spaghetti|calzones?|stromboli)\b/u',
        'pizza'       => '/\b(pizzas?|pizzeria)\b/u',
        'chinese'     => '/\b(chinese|chow mein|lo mein|dim sum|szechuan|kung pao|egg rolls?)\b/u',
        'japanese'    => '/\b(japanese|sushi|ramen|teriyaki|hibachi|tempura)\b/u',
        'thai'        => '/\b(thai|pad thai|curry)\b/u',
        'vietnamese'  => '/\b(vietnamese|pho|banh mi)\b/u',
        'bbq'         => '/\b(bbq|barbecue|barbeque|smokehouse|brisket|pulled pork|smoked meats?)\b/u',
        'burgers'     => '/\b(burgers?|hamburgers?|cheeseburgers?)\b/u',
        'steak'       => '/\b(steaks?|steakhouse|prime rib|ribeye)\b/u',
        'seafood'     => '/\b(seafood|fish|shrimp|crab|lobster|oysters?)\b/u',
        'breakfast'   => '/\b(breakfast|brunch|pancakes?|waffles?|omelets?|omelettes?|biscuits and gravy)\b/u',
        'coffee'      => '/\b(coffee|espresso|lattes?|cappuccinos?|coffee ?house)\b/u',
        'bakery'      => '/\b(bakery|bakeries|pastr(y|ies)|donuts?|doughnuts?|cinnamon rolls?|cupcakes?|breads?|pies)\b/u',
        'dessert'     => '/\b(ice cream|frozen yogurt|gelato|shakes?|milkshakes?|desserts?|fudge|candy)\b/u',
        'sandwiches'  => '/\b(deli|sandwich(es)?|subs|hoagies?|wraps?)\b/u',
        'wings'       => '/\b(wings|chicken wings)\b/u',
        'vegetarian'  => '/\b(vegetarian|vegan|plant.?based)\b/u',
        'gluten free' => '/\bgluten.?free\b/u',
        'catering'    => '/\bcatering\b/u',
    ];

    /** Food words in a text: [cuisine tags..., the words themselves...]. */
    public static function food(string $text): array
    {
        $out = [];
        foreach (self::FOOD as $tag => $re) if (preg_match_all($re, $text, $m)) { $out[] = $tag; foreach ($m[0] as $w) $out[] = $w; }
        return array_values(array_unique($out));
    }

    /** Ownership only as the listing itself states it ("veteran-owned"). */
    private const OWNED = '/\b(veteran|native|tribally|tribal|woman|women|family|locally|black|hispanic|latino|latina)[- ]owned\b/u';

    // ---- setup and bookkeeping ----------------------------------------------------------------------------

    public static function ready(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try { $pdo->query('SELECT 1 FROM search_index LIMIT 1'); $pdo->query('SELECT 1 FROM search_index_state LIMIT 1'); return $ok = true; }
        catch (PDOException) { return $ok = false; }
    }

    public static function counts(PDO $pdo): array
    {
        $out = array_fill_keys(self::KINDS, 0);
        foreach ($pdo->query('SELECT kind, COUNT(*) AS n FROM search_index GROUP BY kind')->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(string) $r['kind']] = (int) $r['n'];
        return $out;
    }

    public static function state(PDO $pdo, string $k): string
    {
        // the whole value from v_long once its database update has run (2026-11-17_search_index_state_wide.sql); v alone
        // holds 200 characters, and the server cut longer ones without an error (Start the system's state among them)
        $st = $pdo->prepare(self::hasLong($pdo) ? 'SELECT COALESCE(v_long, v) FROM search_index_state WHERE k = ?' : 'SELECT v FROM search_index_state WHERE k = ?');
        $st->execute([$k]);
        return (string) ($st->fetchColumn() ?: '');
    }

    public static function setState(PDO $pdo, string $k, string $v): void
    {
        $pdo->prepare('DELETE FROM search_index_state WHERE k = ?')->execute([$k]);
        if (self::hasLong($pdo)) $pdo->prepare('INSERT INTO search_index_state (k, v, v_long) VALUES (?, ?, ?)')->execute([$k, mb_substr($v, 0, 200), $v]);
        else $pdo->prepare('INSERT INTO search_index_state (k, v) VALUES (?, ?)')->execute([$k, $v]);
    }

    /** Whether search_index_state has v_long yet (its database update has run); asked once a request. */
    private static function hasLong(PDO $pdo): bool
    {
        static $has = null;
        if ($has !== null) return $has;
        try { $pdo->query('SELECT v_long FROM search_index_state LIMIT 0'); return $has = true; } catch (Throwable $x) { return $has = false; }
    }

    /** Lowercase words with single spaces and a space at each end, so "% word%" finds a word's start. */
    public static function norm(string ...$parts): string
    {
        $s = mb_strtolower(implode(' ', $parts));
        $s = SearchText::spell(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s));   // "Theatre" is stored as "theater", like what people type
        return ' ' . trim(preg_replace('/\s+/', ' ', $s)) . ' ';
    }

    private static function tags(array $rules, string $text): array
    {
        $out = [];
        foreach ($rules as $k => $re) if (preg_match($re, $text)) $out[] = $k;
        return $out;
    }

    /** Replace these rows (one kind). */
    private static function put(PDO $pdo, string $kind, array $ids, array $rows): void
    {
        foreach (array_chunk($ids, 200) as $chunk) {
            if (!$chunk) continue;
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $pdo->prepare("DELETE FROM search_index WHERE kind = ? AND item_id IN ($in)")->execute(array_merge([$kind], array_map('strval', $chunk)));
        }
        $cols = ['kind', 'item_id', 'label', 'sub', 'url', 'terms', 'who', 'why', 'hours', 'about', 'category_id', 'zip', 'city', 'state', 'place_key',
                 'micro_cluster_id', 'lat', 'lon', 'weight', 'private_location', 'indexed_at'];
        if (self::hasAlso($pdo)) $cols[] = 'also_cats';   // secondary categories (decisions/0073), once its migration has run
        $now = date('Y-m-d H:i:s');
        foreach (array_chunk($rows, 100) as $chunk) {
            $sql = 'INSERT INTO search_index (' . implode(',', $cols) . ') VALUES ' . implode(',', array_fill(0, count($chunk), '(' . implode(',', array_fill(0, count($cols), '?')) . ')'));
            $args = [];
            foreach ($chunk as $r) {
                $r += ['kind' => $kind, 'sub' => '', 'url' => '', 'terms' => '', 'who' => '', 'why' => '', 'hours' => null, 'about' => '', 'category_id' => null,
                       'zip' => '', 'city' => '', 'state' => '', 'place_key' => '', 'micro_cluster_id' => null, 'lat' => null, 'lon' => null, 'weight' => 0,
                       'private_location' => 0, 'indexed_at' => $now, 'also_cats' => ''];
                $r['label'] = mb_substr((string) $r['label'], 0, 200); $r['sub'] = mb_substr((string) $r['sub'], 0, 200);
                $r['who'] = mb_substr((string) $r['who'], 0, 300); $r['about'] = mb_substr((string) $r['about'], 0, 400);
                $r['city'] = mb_substr((string) $r['city'], 0, 80); $r['state'] = mb_substr(strtoupper(trim((string) $r['state'])), 0, 2);
                $r['zip'] = substr(trim((string) $r['zip']), 0, 5);
                foreach ($cols as $c) $args[] = $r[$c];
            }
            $pdo->prepare($sql)->execute($args);
        }
    }

    // ---- building ------------------------------------------------------------------------------------------

    /**
     * One batch of one kind. $after is the last id done ('' to start). Returns ['done' => rows looked at, 'next' => id or
     * null when the kind is finished]. A full build removes, at its end, rows that weren't seen (deleted items).
     */
    public static function build(PDO $pdo, string $kind, string $after = '', int $limit = 400): array
    {
        if ($after === '') self::setState($pdo, 'build_' . $kind . '_started', date('Y-m-d H:i:s'));
        $limit = max(50, min(1000, $limit));
        switch ($kind) {
            case 'listing':  [$n, $last] = self::listingsAfter($pdo, (int) $after, $limit); break;
            case 'outdoors': [$n, $last] = self::outdoorsAfter($pdo, $after, $limit); break;
            case 'story': case 'group': case 'topic': [$n, $last] = [self::rebuildSmall($pdo, $kind), null]; break;
            default: throw new InvalidArgumentException('Unknown kind.');
        }
        $next = ($kind === 'listing' || $kind === 'outdoors') && $n >= $limit ? (string) $last : null;
        if ($next === null) {
            $since = self::state($pdo, 'build_' . $kind . '_started');
            if ($since !== '' && ($kind === 'listing' || $kind === 'outdoors')) $pdo->prepare('DELETE FROM search_index WHERE kind = ? AND indexed_at < ?')->execute([$kind, $since]);
            self::setState($pdo, 'built_' . $kind, date('Y-m-d H:i:s'));
            if ($kind === 'listing') self::setState($pdo, 'listing_mark', $since !== '' ? $since : date('Y-m-d H:i:s'));
        }
        return ['done' => $n, 'next' => $next];
    }

    /** Listings with id > $after. */
    private static function listingsAfter(PDO $pdo, int $after, int $limit): array
    {
        $st = $pdo->prepare('SELECT e.id FROM entities e WHERE e.id > ? ORDER BY e.id LIMIT ' . $limit);
        $st->execute([$after]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        if ($ids) self::indexListings($pdo, $ids);
        return [count($ids), $ids ? end($ids) : $after];
    }

    /** search_index.also_cats exists (app/migrations/2026-11-13_listing_categories.sql). */
    public static function hasAlso(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try { $pdo->query('SELECT also_cats FROM search_index LIMIT 1'); return $ok = true; } catch (PDOException) { return $ok = false; }
    }


    /** Staff's words for each kind of business (category_terms, decisions/0075): [category id => [term]]. */
    private static function kindWords(PDO $pdo): array
    {
        static $m = null;
        if ($m !== null) return $m;
        $m = [];
        try { foreach ($pdo->query('SELECT category_id, term FROM category_terms')->fetchAll(PDO::FETCH_ASSOC) as $r) $m[(int) $r['category_id']][] = (string) $r['term']; } catch (Throwable) {}
        return $m;
    }
    /** (Re)index these listings; the ones no longer public are removed. */
    public static function indexListings(PDO $pdo, array $ids): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) return 0;
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT e.id, e.name, e.entity_type, e.status, e.is_suppressed, e.category_id, e.primary_zip, e.micro_cluster_id, e.latitude, e.longitude,
                                    e.verified_tier, c.name AS cat, g.name AS grp
                               FROM entities e LEFT JOIN categories c ON c.id = e.category_id LEFT JOIN categories g ON g.id = c.parent_id
                              WHERE e.id IN ($in)");
        $st->execute($ids);
        $ents = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $e) {
            if ($e['entity_type'] !== 'merchant' || (int) $e['is_suppressed'] === 1 || !in_array($e['status'], self::STATUSES, true)) continue;
            $ents[(int) $e['id']] = $e;
        }
        $meta = [];
        if ($ents) {
            $in2 = implode(',', array_fill(0, count($ents), '?'));
            $ms = $pdo->prepare("SELECT entity_id, meta_key, meta_value FROM entity_metadata WHERE entity_id IN ($in2) AND meta_key IN ('closed','city','state','description','offerings','hours','category','aka')");
            $ms->execute(array_keys($ents));
            foreach ($ms->fetchAll(PDO::FETCH_ASSOC) as $m) {
                $v = json_decode((string) $m['meta_value'], true);
                $meta[(int) $m['entity_id']][(string) $m['meta_key']] = $v ?? $m['meta_value'];
            }
        }
        if (!class_exists('ListingCategories')) require_once __DIR__ . '/ListingCategories.php';
        $alsoCats = $ents ? ListingCategories::many($pdo, array_keys($ents)) : [];   // its secondary categories (decisions/0073)
        $kindWords = self::kindWords($pdo);   // staff's words for each kind ("mental health" for Counselors): found by them too
        if (!class_exists('Nexus')) require_once __DIR__ . '/Nexus.php';
        $nexusOf = $ents ? Nexus::namesFor($pdo, array_keys($ents)) : [];   // the organization it's part of (decisions/0054): "changepoint" finds every location
        $rows = [];
        foreach ($ents as $id => $e) {
            $m = $meta[$id] ?? [];
            $also = $alsoCats[$id] ?? [];
            $alsoWords = implode(' ', array_map(fn ($c) => $c['name'] . ' ' . SearchText::catLabel($c['name']), $also));
            if (isset($nexusOf[$id])) $alsoWords .= ' ' . $nexusOf[$id];
            if (is_array($m['aka'] ?? null)) $alsoWords .= ' ' . implode(' ', array_filter($m['aka'], 'is_string'));   // its other names (decisions/0084)
            $alsoWords .= ' ' . implode(' ', array_unique(array_merge($kindWords[(int) $e['category_id']] ?? [], ...array_map(fn ($c) => $kindWords[(int) ($c['id'] ?? 0)] ?? [], $also))));
            $cat = (string) ($e['cat'] ?? '') ?: (is_string($m['category'] ?? null) ? $m['category'] : '');
            $catShow = $cat !== '' ? SearchText::catLabel($cat) : '';   // its plain name, shown; both names are searched (decisions/0068)
            $offer = is_array($m['offerings'] ?? null) ? implode(' ', array_map('strval', array_filter($m['offerings'], 'is_scalar'))) : (string) ($m['offerings'] ?? '');
            $desc = is_string($m['description'] ?? null) ? $m['description'] : '';
            $city = is_string($m['city'] ?? null) ? $m['city'] : ''; $stt = is_string($m['state'] ?? null) ? $m['state'] : '';
            $name = NameText::listing((string) $e['name'], (string) $e['status']);   // in full words, unless its owner claimed it (decisions/0058 §31)
            $what = mb_strtolower($name . ' ' . $cat . ' ' . ($e['grp'] ?? '') . ' ' . $offer);
            // Why: what it is (its category and offerings; its broad group only when those say nothing), never its name
            // (a broad group like "Automotive Repair" also holds car rentals and washes, so it never says fix, help or buy)
            $whyTags = self::tags(self::WHY, mb_strtolower($cat . ' ' . $alsoWords . ' ' . $offer))
                ?: array_values(array_intersect(self::tags(self::WHY, mb_strtolower((string) ($e['grp'] ?? ''))), ['eat', 'stay', 'explore', 'learn', 'heal']));
            $aud = self::tags(self::AUDIENCE, $what);   // who it is for: from its name and category (a description is too loose)
            if (preg_match_all(self::OWNED, mb_strtolower($desc . ' ' . $name), $mm)) foreach ($mm[0] as $o) $aud[] = str_replace(' ', '-', $o);
            // What it serves, from the whole description, offerings and name (food places and food sellers only)
            $foodish = in_array('eat', $whyTags, true) || preg_match('/\b(food|grocer\w*|market|bakery|caterer\w*|butcher|meat)\b/u', mb_strtolower($cat . ' ' . ($e['grp'] ?? '')));
            $food = $foodish ? self::food(mb_strtolower($name . ' ' . $offer . ' ' . $desc)) : [];
            $private = ListingSafety::isConfidential($e['category_id'] !== null ? (int) $e['category_id'] : null, $name);
            $hours = $m['hours'] ?? null;
            $shut = isset($m['closed']);   // marked closed (decisions/0072): still found, shown as Closed, last, never open now
            if ($shut) $hours = ['Mon-Sun closed'];
            $rows[] = [
                'item_id' => (string) $id, 'label' => $name, 'sub' => trim(implode(' · ', array_filter([$shut ? 'Closed' : '', $catShow, trim($city . ($stt !== '' ? ', ' . $stt : ''))])), ' ·'),
                'url' => '/listing/view.php?id=' . $id,
                'terms' => self::norm($name, $cat . ($catShow !== $cat ? ' ' . $catShow : ''), (string) ($e['grp'] ?? ''), $alsoWords, $offer, implode(' ', array_map(fn ($a) => self::AUDIENCE_LABEL[$a] ?? $a, $aud)), implode(' ', $food)),
                'who' => implode(' ', array_unique($aud)), 'why' => implode(' ', $whyTags),
                'hours' => $hours !== null && $hours !== '' && $hours !== [] ? (is_string($hours) ? $hours : json_encode($hours)) : null,
                'about' => mb_strimwidth(trim(preg_replace('/\s+/', ' ', $desc)), 0, 390, '…'),
                'category_id' => $e['category_id'] !== null ? (int) $e['category_id'] : null,
                'zip' => (string) ($e['primary_zip'] ?? ''), 'city' => $city, 'state' => $stt,
                'micro_cluster_id' => $e['micro_cluster_id'] !== null ? (int) $e['micro_cluster_id'] : null,
                'lat' => $e['latitude'] !== null ? (float) $e['latitude'] : null, 'lon' => $e['longitude'] !== null ? (float) $e['longitude'] : null,
                'weight' => $shut ? -40 : (int) ($e['status'] === 'verified' ? 10 : ($e['status'] === 'claimed-unverified' || $e['status'] === 'pending-verification' ? 5 : 0)),
                'private_location' => $private ? 1 : 0,
                'also_cats' => ListingCategories::token($also),
            ];
        }
        self::put($pdo, 'listing', $ids, $rows);
        return count($rows);
    }

    /**
     * Outdoors: Recreation.gov places (ids 'a…' and 'f…'), then natural landmarks (decisions/0058 §24; ids 'g' + the
     * GNIS id, 10 digits, so they sort after). Hidden ones and ones waiting for a person are never indexed.
     */
    private static function outdoorsAfter(PDO $pdo, string $after, int $limit): array
    {
        if ($after === '' || $after[0] !== 'g') {
            [$n, $last] = self::recAfter($pdo, $after, $limit);
            if ($n >= $limit) return [$n, $last];
            [$m, $last2] = self::landmarksAfter($pdo, 'g', $limit - $n);
            return [$n + $m, $m ? $last2 : ($n ? $last : $after)];
        }
        return self::landmarksAfter($pdo, $after, $limit);
    }

    /** Re-indexes one landmark after a person approved or hid it (it's removed when it's no longer shown). */
    public static function refreshLandmark(PDO $pdo, int $gnisId): void
    {
        self::landmarksAfter($pdo, 'g' . str_pad((string) max(0, $gnisId - 1), 10, '0', STR_PAD_LEFT), 1);
    }

    /** Natural landmarks with an index id after $after ('g0000000123'). */
    private static function landmarksAfter(PDO $pdo, string $after, int $limit): array
    {
        if ($limit <= 0 || !class_exists('Landmarks') || !Landmarks::ready($pdo)) return [0, $after];
        $st = $pdo->prepare('SELECT * FROM landmarks WHERE gnis_id > ? ORDER BY gnis_id LIMIT ' . $limit);
        $st->execute([(int) substr($after, 1)]);
        $all = $st->fetchAll(PDO::FETCH_ASSOC);
        $key = fn (array $r) => 'g' . str_pad((string) $r['gnis_id'], 10, '0', STR_PAD_LEFT);
        if (is_file(__DIR__ . '/Nations.php')) require_once __DIR__ . '/Nations.php';
        $nationName = fn (string $shape) => class_exists('Nations') ? Nations::label($pdo, $shape) : $shape;   // the nation's own name (decisions/0058 §26)
        $ids = []; $rows = [];
        foreach ($all as $r) {
            $ids[] = $key($r);
            if (!Landmarks::visible($r)) continue;   // nothing on a nation's land while the nation tools are off (decisions/0058 §27)
            $type = Landmarks::kindLabel($r);
            require_once __DIR__ . '/Landmarks.php';
            $what = mb_strtolower($r['name'] . ' ' . $type . ' ' . Landmarks::about($pdo, $r));
            $rows[] = [
                'item_id' => $key($r), 'label' => (string) $r['name'],
                'sub' => implode(' · ', array_filter([$type, $r['nation'] !== '' ? $nationName((string) $r['nation']) . ' land' : ($r['near_town'] !== '' ? 'near ' . $r['near_town'] : (string) $r['county'] . ' County')])),
                'url' => '/place/landmark.php?id=' . (int) $r['gnis_id'],
                'terms' => self::norm((string) $r['name'], $type, (string) $r['fclass'], (string) $r['wd_desc'], 'landmark natural outdoors', Landmarks::activityWords($r)),
                'why' => implode(' ', array_unique(array_merge(['explore'], self::tags(self::WHY, $what)))),
                'about' => mb_strimwidth(Landmarks::about($pdo, $r), 0, 390, '…'),
                'zip' => (string) $r['zip5'], 'city' => (string) $r['near_town'], 'state' => (string) $r['state'],
                'lat' => (float) $r['lat'], 'lon' => (float) $r['lon'],
                'category_id' => (int) ($r['category_id'] ?? 0) > 0 ? (int) $r['category_id'] : null,   // its category: "museums near me" finds it (2026-11-10)
                'weight' => ($r['image_url'] !== '' ? 3 : 0) + ($r['wiki_title'] !== '' ? 3 : 0) + min(20, (int) (($r['interest'] ?? 0) / 2)),   // a photo, an article, and the interest it draws (2026-11-10) first
            ];
        }
        self::put($pdo, 'outdoors', $ids, $rows);
        return [count($all), $all ? $key(end($all)) : $after];
    }

    /** Public recreation places (Recreation.gov); hidden ones are never indexed. */
    private static function recAfter(PDO $pdo, string $after, int $limit): array
    {
        if (!class_exists('RecBulk') || !RecBulk::ready($pdo)) return [0, $after];
        $st = $pdo->prepare('SELECT ridb_id, kind, name, type, org, summary, lat, lon, city, state, zip5, activities, hidden FROM rec_places WHERE ridb_id > ? ORDER BY ridb_id LIMIT ' . $limit);
        $st->execute([$after]);
        $all = $st->fetchAll(PDO::FETCH_ASSOC);
        $ids = []; $rows = [];
        foreach ($all as $r) {
            $ids[] = (string) $r['ridb_id'];
            if ((int) $r['hidden'] === 1) continue;
            $name = RecBulk::displayName((string) $r['name']);
            $type = RecBulk::kindLabel($r);
            $what = mb_strtolower($name . ' ' . $type . ' ' . $r['type'] . ' ' . $r['activities']);
            $rows[] = [
                'item_id' => (string) $r['ridb_id'], 'label' => $name, 'sub' => trim(implode(' · ', array_filter([$type, (string) $r['org']])), ' ·'),
                'url' => '/place/recreation.php?id=' . rawurlencode((string) $r['ridb_id']),
                'terms' => self::norm($name, $type, (string) $r['type'], (string) $r['activities'], (string) $r['org'], 'outdoors'),
                'who' => mb_strtolower((string) $r['org']), 'why' => implode(' ', array_unique(array_merge(['explore'], self::tags(self::WHY, $what)))),
                'about' => mb_strimwidth((string) $r['summary'], 0, 390, '…'),
                'zip' => (string) $r['zip5'], 'city' => (string) $r['city'], 'state' => (string) $r['state'],
                'lat' => $r['lat'] !== null ? (float) $r['lat'] : null, 'lon' => $r['lon'] !== null ? (float) $r['lon'] : null,
            ];
        }
        self::put($pdo, 'outdoors', $ids, $rows);
        return [count($all), $all ? (string) end($all)['ridb_id'] : $after];
    }

    /** Stories, groups and topics are few: rebuilt whole. */
    private static function rebuildSmall(PDO $pdo, string $kind): int
    {
        $rows = [];
        if ($kind === 'story') {
            try {
                $st = $pdo->query("SELECT id, place_key, title, body FROM place_stories WHERE status = 'published'");
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) {
                    try { [$pl, $url] = Links::resolve($pdo, 'place', (string) $s['place_key']); } catch (Throwable) { continue; }
                    $body = trim(preg_replace('/\s+/', ' ', strip_tags((string) $s['body'])));
                    $rows[] = self::placed($pdo, (string) $s['place_key'], [
                        'item_id' => (string) $s['id'], 'label' => (string) $s['title'], 'sub' => 'Story · ' . $pl, 'url' => $url . '#story-h',
                        'terms' => self::norm((string) $s['title'], $pl, 'story guide'),
                        'why' => implode(' ', array_unique(array_merge(['explore'], self::tags(self::WHY, mb_strtolower((string) $s['title']))))),
                        'about' => mb_strimwidth($body, 0, 390, '…')]);
                }
            } catch (PDOException) {}
        } elseif ($kind === 'group' && class_exists('Community') && Community::ready($pdo)) {
            foreach (Community::directory($pdo, '', 200) as $g) {
                $pk = '';
                try { $q = $pdo->prepare('SELECT place_key FROM community_groups WHERE id = ?'); $q->execute([$g['id']]); $pk = (string) ($q->fetchColumn() ?: ''); } catch (PDOException) {}
                $what = mb_strtolower($g['name'] . ' ' . $g['topic_label'] . ' ' . $g['about']);
                $rows[] = self::placed($pdo, $pk, [
                    'item_id' => (string) $g['id'], 'label' => (string) $g['name'],
                    'sub' => 'Group · ' . $g['members'] . ' member' . ($g['members'] === 1 ? '' : 's') . ($g['topic_label'] ? ' · ' . $g['topic_label'] : ''),
                    'url' => '/community/group.php?id=' . $g['id'],
                    'terms' => self::norm((string) $g['name'], (string) $g['topic_label'], 'group community ' . implode(' ', $g['tags'] ?? [])),   // its tags find it (decisions/0136 §1)
                    'who' => implode(' ', self::tags(self::AUDIENCE, $what)),
                    'why' => implode(' ', array_unique(array_merge(['connect'], self::tags(self::WHY, $what)))),
                    'about' => (string) $g['about'], 'weight' => min(20, (int) $g['members'])]);
            }
        } elseif ($kind === 'topic') {
            try {
                $seen = [];
                foreach ($pdo->query('SELECT c.id, c.name, c.slug, p.name AS parent FROM categories c LEFT JOIN categories p ON p.id = c.parent_id WHERE c.is_active = 1
                                      ORDER BY (SELECT COUNT(*) FROM entities e WHERE e.category_id = c.id) DESC, c.id')->fetchAll(PDO::FETCH_ASSOC) as $c) {
                    $plain = SearchText::catLabel((string) $c['name']);   // the name visitors see (decisions/0068); both are searched
                    // one topic for a shared plain name: the directory reads all its categories (Self Storage is three)
                    if (isset($seen[mb_strtolower($plain)])) continue;
                    $seen[mb_strtolower($plain)] = 1;
                    $rows[] = ['item_id' => 'cat:' . $c['id'], 'label' => $plain, 'sub' => 'Category' . ($c['parent'] ? ' in ' . $c['parent'] : ''),
                               'url' => '/directory/?cat=' . rawurlencode((string) $c['slug']) . '&catname=' . rawurlencode($plain),
                               'terms' => self::norm((string) $c['name'], $plain, (string) ($c['parent'] ?? '')), 'category_id' => (int) $c['id'],
                               'why' => implode(' ', self::tags(self::WHY, mb_strtolower((string) $c['name'])))];   // its own name, not its broad group
                }
            } catch (PDOException) {}
            if (class_exists('Community')) foreach (Community::TOPICS as $k => $label) {
                if ($k === 'other') continue;
                $rows[] = ['item_id' => 'ct:' . $k, 'label' => $label, 'sub' => 'Community topic', 'url' => '/community/?topic=' . rawurlencode($k),
                           'terms' => self::norm($label, 'community groups'), 'why' => 'connect'];
            }
        }
        $pdo->prepare('DELETE FROM search_index WHERE kind = ?')->execute([$kind]);
        self::put($pdo, $kind, [], $rows);
        return count($rows);
    }

    /** Where a story or group is: its place key, and the place's anchor ZIP, cluster and point. */
    private static function placed(PDO $pdo, string $key, array $row): array
    {
        $row['place_key'] = $key;
        if (preg_match('/^cluster:(\d+)$/', $key, $m)) {
            try {
                $st = $pdo->prepare('SELECT mc.id, mc.primary_zip, z.latitude, z.longitude, TRIM(z.city) AS city, TRIM(z.state) AS state FROM micro_clusters mc
                                       LEFT JOIN zip_coordinates z ON TRIM(z.zip) = TRIM(mc.primary_zip) WHERE mc.id = ? LIMIT 1');
                $st->execute([(int) $m[1]]);
                if ($c = $st->fetch(PDO::FETCH_ASSOC)) $row += ['micro_cluster_id' => (int) $c['id'], 'zip' => (string) $c['primary_zip'], 'city' => (string) $c['city'], 'state' => (string) $c['state'],
                                                                'lat' => $c['latitude'] !== null ? (float) $c['latitude'] : null, 'lon' => $c['longitude'] !== null ? (float) $c['longitude'] : null];
            } catch (PDOException) {}
        }
        return $row;
    }

    /**
     * Keeps the index current with no cron: at most every 15 minutes, listings changed since the last look (their row
     * or their details) are re-read (up to 300), and stories, groups and topics are rebuilt. Quiet on any failure.
     */
    public static function refresh(PDO $pdo, bool $force = false): void
    {
        try {
            if (!self::ready($pdo)) return;
            $last = self::state($pdo, 'refreshed_at');
            if (!$force && $last !== '' && strtotime($last) > time() - 900) return;
            self::setState($pdo, 'refreshed_at', date('Y-m-d H:i:s'));
            $mark = self::state($pdo, 'listing_mark');
            if ($mark !== '') {
                // listings changed since the mark, oldest first, 600 at a time. The mark moves to the last one taken, so each
                // run carries on where the last stopped (2026-11-10: it moved only when fewer than 300 had changed, so with
                // the crawler busy it re-read the same 300 every time and new listings, Dana Point's, never reached search)
                $now = date('Y-m-d H:i:s'); $n = 600;
                $st = $pdo->prepare('SELECT id, updated_at FROM entities WHERE updated_at > ? ORDER BY updated_at, id LIMIT ' . $n);
                $st->execute([$mark]); $er = $st->fetchAll(PDO::FETCH_ASSOC);
                $st = $pdo->prepare('SELECT entity_id, MAX(updated_at) u FROM entity_metadata WHERE updated_at > ? GROUP BY entity_id ORDER BY u LIMIT ' . $n);
                $st->execute([$mark]); $mr = $st->fetchAll(PDO::FETCH_ASSOC);
                $ids = array_values(array_unique(array_merge(array_map(fn ($r) => (int) $r['id'], $er), array_map(fn ($r) => (int) $r['entity_id'], $mr))));
                if ($ids) foreach (array_chunk($ids, 300) as $chunk) self::indexListings($pdo, $chunk);
                // as far as both lists reached: a full list stops at its last row; one with room left reached now
                $back = fn (string $t) => date('Y-m-d H:i:s', strtotime($t) - 1);   // a second back: others changed that same second are read again
                $reach = [count($er) >= $n ? $back((string) end($er)['updated_at']) : $now, count($mr) >= $n ? $back((string) end($mr)['u']) : $now];
                $next = min($reach);
                if ($next <= $mark) $next = date('Y-m-d H:i:s', strtotime($mark) + 1);   // a second full of changes: step past it
                self::setState($pdo, 'listing_mark', $next);
            }
            foreach (['story', 'group', 'topic'] as $k) self::rebuildSmall($pdo, $k);
            // landmarks added after the federal data (by hand, or found for a guide on demand: ids from 900000000), new since
            // last time; they were only indexed on a full rebuild (2026-11-10: Four Corners Monument, Dana Point's places)
            if (class_exists('Landmarks') || is_file(__DIR__ . '/Landmarks.php')) {
                require_once __DIR__ . '/Landmarks.php';
                if (Landmarks::ready($pdo)) {
                    $lm = self::state($pdo, 'landmark_added_mark');
                    if ($lm === '') $lm = 'g' . str_pad((string) (Landmarks::MANUAL_FROM - 1), 10, '0', STR_PAD_LEFT);
                    [$n, $next] = self::landmarksAfter($pdo, $lm, 300);
                    if ($n > 0) self::setState($pdo, 'landmark_added_mark', $next);
                    // each landmark filed under one of our categories by its source's class (2026-11-10: "from categories not
                    // guesses"); a few filed now are re-indexed at once, many go through the walk below
                    $filed = Landmarks::categorize($pdo);
                    if (count($filed) > 300) { self::setState($pdo, 'landmark_kind_walk3', 'g'); }
                    else foreach ($filed as $fid) self::refreshLandmark($pdo, (int) $fid);
                    // every landmark again under its kind, 1000 a run until done (once, and after a big filing)
                    $wk = self::state($pdo, 'landmark_kind_walk3');   // 3: with its activities (Landmarks::activities, 2026-11-10)
                    if ($wk === '') $wk = 'g';
                    if ($wk !== 'done') { [$wn, $wnext] = self::landmarksAfter($pdo, $wk, 1000); self::setState($pdo, 'landmark_kind_walk3', $wn < 1000 ? 'done' : $wnext); }
                }
            }
        } catch (Throwable $e) {
            error_log('SearchIndex::refresh: ' . $e->getMessage());
        }
    }

    // ---- searching -----------------------------------------------------------------------------------------

    /**
     * @param array $o text (words left after the chips), cat (['id','label']), place (a place chip), area (['lat','lon','radius']),
     *                 kind (one kind or null), why (intent or ''), audience (key or ''), when (['now' => [dow, min]] or ['days' => [...]] or []),
     *                 limit
     * @return array{items: list<array>, kinds: array<string,int>, audience_found: bool, total: int}
     */
    public static function find(PDO $pdo, array $o): array
    {
        $text = trim((string) ($o['text'] ?? '')); $cat = $o['cat'] ?? null; $place = $o['place'] ?? null; $area = $o['area'] ?? null;
        $kind = $o['kind'] ?? null; $why = (string) ($o['why'] ?? ''); $aud = (string) ($o['audience'] ?? ''); $when = (array) ($o['when'] ?? []);
        $limit = max(1, min(40, (int) ($o['limit'] ?? 12)));
        $nearest = !empty($o['nearest']) && $area;   // "near me" alone (2026-11-10): everything around, nearest first
        $p = [];
        $w = [];
        // What: every word (or one of its everyday alternatives) starts a word in the terms. Question words are left
        // out, and soft words ("home") only help the order, unless they're all there is.
        $all = array_values(array_filter(SearchText::tokens($text, !empty($o['any']) ? 'all' : 'things'), fn ($t) => !in_array($t, self::STOP, true)));
        $toks = array_values(array_filter($all, fn ($t) => !in_array($t, self::SOFT, true))) ?: $all;
        $soft = array_values(array_diff($all, $toks));
        $any = !empty($o['any']);   // the loose try: any one of the words
        $ors = [];
        foreach ($toks as $i => $t) {
            $or = [];
            foreach (SearchText::alternatives($t) as $j => $a) {
                $a = trim(self::norm($a));
                if ($a === '') continue;
                $ph = ":w{$i}_{$j}";
                $or[] = "si.terms LIKE $ph";
                $p[$ph] = ((mb_strlen($a) <= 3 && $j === 0) || !empty($o['whole']) ? '% ' . $a . ' %' : '% ' . $a . '%');   // 'whole': "lake" isn't "Lakeside"
            }
            // the start of a word in its description: "sports" isn't "Passports"
            // whole-word mode: a long word ("stargazing") still counts in a description
            if (mb_strlen($t) >= (empty($o['whole']) ? 4 : 6) && !in_array($t, (array) ($o['no_about'] ?? []), true)) { $or[] = "CONCAT(' ', REPLACE(REPLACE(REPLACE(REPLACE(si.about, '-', ' '), '/', ' '), ',', ' '), '(', ' ')) LIKE :wa$i"; $p[":wa$i"] = '% ' . $t . '%'; }
            if ($or) $ors[] = '(' . implode(' OR ', $or) . ')';
        }
        if ($ors) $w[] = $any ? '(' . implode(' OR ', $ors) . ')' : implode(' AND ', $ors);
        // words that name a health need ("urgent care", "clinic") find health businesses only: never Child Care or Pet Care
        // (0061's note, 2026-11-28; the words are WHY['heal']'s, which tags each listing at indexing)
        if ($ors && $why === '' && preg_match(self::WHY['heal'], ' ' . mb_strtolower($text) . ' ')) $w[] = "(si.kind <> 'listing' OR si.why LIKE '%heal%')";
        if ($cat) {
            // a category read from words ("pizza") also finds listings with the words in them (Dittys Pizza & Pie, a restaurant)
            $cl = trim(self::norm((string) ($cat['words'] ?? '')));
            // its kin count as it ("Parks": State Parks); a word other kinds of business share ("park": RV Parks) is never
            // read from a name (decisions/0061)
            $kin = array_map('intval', (array) ($cat['kin'] ?? []));
            if (!empty($cat['shared'])) $cl = '';
            $w[] = "((si.kind = 'listing' AND (si.category_id IN (SELECT id FROM categories WHERE id = :ca OR parent_id = :cb" . CategoryAlso::sqlFor($pdo, (int) $cat['id']) . ")" . ($kin ? ' OR si.category_id IN (' . implode(',', $kin) . ')' : '') . (self::hasAlso($pdo) ? ' OR si.also_cats LIKE :cz' : '') . ($cl !== '' ? ' OR si.terms LIKE :cl' : '') . ")) OR (si.kind <> 'listing' AND si.terms LIKE :cw))";
            if (self::hasAlso($pdo)) $p[':cz'] = '% ' . (int) $cat['id'] . ' %';   // also listed under it (decisions/0073)
            $catSet = array_merge([(int) $cat['id']], $kin, array_map('intval', $pdo->query('SELECT id FROM categories WHERE parent_id = ' . (int) $cat['id'] . CategoryAlso::sqlFor($pdo, (int) $cat['id']))->fetchAll(PDO::FETCH_COLUMN)));
            $cw = explode(' ', trim(self::norm((string) $cat['label'])))[0];
            $cw = strlen($cw) > 5 ? preg_replace('/(ists?|ies|s)$/', '', $cw) : $cw;
            $p += [':ca' => (int) $cat['id'], ':cb' => (int) $cat['id'], ':cw' => '% ' . $cw . '%'] + ($cl !== '' ? [':cl' => '% ' . $cl . ' %'] : []);   // the whole word: "pub" isn't "public"
        }
        if ($kind) { $w[] = 'si.kind = :kind'; $p[':kind'] = $kind; }
        // an activity ("going fishing"): places for doing things, or a business named for the activity itself
        // (a bait shop says fishing); "Silver Lakes Family Fellowship" isn't one (decisions/0060)
        if (!empty($o['act'])) {
            $w[] = "(si.why LIKE '%explore%' OR si.kind IN ('outdoors', 'story', 'group', 'experience') OR si.terms LIKE :act)";
            $p[':act'] = '% ' . trim(self::norm((string) $o['act'])) . '%';
        }
        // a kind of place to see ("landmarks", "canyons"): places for going, never a business only named for it (Landmark Homes)
        if (!empty($o['explore_only'])) $w[] = "(si.why LIKE '%explore%' OR si.kind IN ('outdoors', 'story', 'group', 'experience'))";
        if (!empty($o['funnel'])) $w[] = self::funnelSql((string) $o['funnel']);
        // A place alone: its stories, groups and outdoors (its listings are a count and "See all", not an A-Z list)
        $placeOnly = !$toks && !$cat && $why === '' && $aud === '' && !$kind && !$when;
        if ($placeOnly && !$place && !$area) return ['items' => [], 'kinds' => [], 'funnels' => [], 'audience_found' => true, 'total' => 0];
        if ($placeOnly) $w[] = "si.kind IN ('story', 'group', 'outdoors')";
        if ($aud !== '') { $w[] = 'si.who LIKE :aud'; $p[':aud'] = '%' . $aud . '%'; }
        // an outing ("place to take my kids", "rainy day activities"): only the kinds of places that fit, never what rules
        // them out; a business or a place to go, never a category, group or guide
        $outing = (array) ($o['outing'] ?? []);
        $fit = $outing ? self::outingWords($outing) : [];
        if ($fit) {
            $ow = [];
            foreach ($fit as $i => $f) { $ow[] = "si.terms LIKE :of$i"; $p[":of$i"] = '% ' . $f . ' %'; }
            $w[] = '(' . implode(' OR ', $ow) . ')';
            $nw = [];
            foreach (self::OUTING_NOT as $i => $f) { $nw[] = "si.terms LIKE :on$i"; $p[":on$i"] = '% ' . $f . ' %'; }
            $w[] = 'NOT (' . implode(' OR ', $nw) . ')';
            $w[] = "si.kind IN ('listing', 'outdoors', 'experience')";
        }
        // Where
        $where = self::whereSql($pdo, $place, $area, $p);
        if (!empty($o['not_place'])) {   // beyond a place: around it, but not in it (decisions/0060)
            $not = self::whereSql($pdo, $o['not_place'], null, $p);
            if ($not !== '') $w[] = "NOT COALESCE($not, 0)";   // a row with no ZIP or place key is outside it, not unknown
        }
        if ($where !== '') $w[] = $placeOnly || !$toks ? $where : "(si.kind = 'topic' OR (si.kind = 'group' AND si.place_key = '') OR $where)";
        // Why alone ("places to eat in St. Johns") chooses what's shown; with other words it only changes the order
        $whyOnly = $why !== '' && !$toks && !$cat && empty($o['why_loose']) && !$fit;   // an outing's own list chooses
        if ($why !== '' && !$toks && !$cat) $w[] = "si.kind <> 'topic'";
        if ($whyOnly) { $w[] = 'si.why LIKE :whyf'; $p[':whyf'] = '%' . $why . '%'; }
        $sql = 'SELECT si.* FROM search_index si' . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
             . ' ORDER BY ' . ($why !== '' ? '(si.why LIKE :whyo) DESC, ' : '') . 'si.weight DESC, si.label LIMIT 400';
        if ($why !== '') $p[':whyo'] = '%' . $why . '%';
        $st = $pdo->prepare($sql);
        $st->execute($p);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $audFound = true;
        if (!$rows && $whyOnly) { $o['why_loose'] = true; return self::find($pdo, $o); }
        if (!$rows && count($toks) >= 2 && !$any && empty($o['strict'])) { $o['any'] = true; return self::find($pdo, $o); }   // (not for "beyond": only real matches)   // then any of the words, most matched first
        if (!$rows && $aud !== '') {   // nothing says it's for them: show the rest, and say so
            unset($o['audience']); $r = self::find($pdo, $o); $r['audience_found'] = false; return $r;
        }

        $lt = trim(self::norm(implode(' ', $all)));
        // a word in a text: the start of a word, or the whole word when it's short ("car" isn't "carpet")
        $has = fn (string $normText, string $t) => str_contains($normText, mb_strlen($t) <= 3 ? ' ' . $t . ' ' : ' ' . $t);
        // the loose try (any one of the words): a rare word counts for more than a common one ("pizza" over "new")
        $rare = [];
        if ($any && $rows) foreach ($toks as $t) {
            $n = count(array_filter($rows, fn ($r) => $has((string) $r['terms'], $t) || $has(' ' . self::norm((string) $r['label']) . ' ', $t)));
            $rare[$t] = $n ? (int) round(60 * (1 - $n / count($rows))) : 0;
        }
        $out = [];
        foreach ($rows as $r) {
            $lab = trim(self::norm((string) $r['label']));
            $nlab = ' ' . $lab . ' ';
            $score = 0; $named = false;
            // found through a secondary category: after the listings whose primary category it is (decisions/0073)
            if (!empty($catSet) && $r['kind'] === 'listing' && !in_array((int) $r['category_id'], $catSet, true) && str_contains((string) ($r['also_cats'] ?? ''), ' ' . (int) $cat['id'] . ' ')) $score -= 15;
            if ($lt !== '') {
                if ($lab === $lt) { $score += 100; $named = true; }
                elseif (str_starts_with($nlab, ' ' . $lt . (mb_strlen($lt) <= 3 ? ' ' : ''))) { $score += 60; $named = mb_strlen($lt) >= 6; }   // its own name, typed
                elseif (!array_filter($toks, fn ($t) => !$has($nlab, $t))) $score += 40;
                elseif (!array_filter($toks, fn ($t) => !$has((string) $r['terms'], $t))) $score += 20;
                if ($any) foreach ($toks as $t) if ($has((string) $r['terms'], $t) || $has($nlab, $t)) $score += 15 + ($rare[$t] ?? 0);
                foreach ($toks as $t) foreach (SearchText::alternatives($t) as $j => $a) {   // the word itself, then its nearest alternatives
                    if (str_contains((string) $r['terms'], ' ' . trim(self::norm($a)))) { $score += max(0, 20 - 3 * $j); break; }
                }
                foreach ($soft as $t) if (str_contains((string) $r['terms'], ' ' . $t)) $score += 5;
            }
            if ($why !== '' && in_array($why, explode(' ', (string) $r['why']), true)) $score += 45;   // they said what they want to do
            if ($fit) {
                foreach ((array) (self::OUTING_AGES[$outing['age'] ?? ''][1] ?? []) as $aw) if (str_contains((string) $r['terms'], ' ' . $aw . ' ')) { $score += 30; break; }
                $hits = 0; foreach ($fit as $f) if (str_contains((string) $r['terms'], ' ' . $f . ' ')) $hits++;
                $score += min(3, $hits) * 8;   // a zoo that says zoo, park and picnic over a café named "Garden"
            }
            $open = null;
            if ($when && $r['kind'] === 'listing') {
                $wk = $r['hours'] ? Hours::parse((string) $r['hours']) : null;
                if (isset($when['now'])) $open = Hours::openAt($wk, (int) $when['now'][0], (int) $when['now'][1]);
                elseif (!empty($when['days'])) {
                    $vals = array_map(fn ($d) => Hours::openOn($wk, (int) $d), $when['days']);
                    $open = in_array(true, $vals, true) ? true : (count(array_filter($vals, fn ($v) => $v === false)) === count($vals) ? false : null);
                }
                if ($open === false) continue;   // known to be closed then
                if ($open === true) $score += 25;
            }
            $miles = null;
            if ($area && $r['lat'] !== null) {
                $miles = self::miles((float) $area['lat'], (float) $area['lon'], (float) $r['lat'], (float) $r['lon']);
                if ($miles > (float) $area['radius']) continue;
                $score -= (int) round($miles / 5);
            }
            if ($r['kind'] === 'topic') $score -= ($place || $area) ? 60 : 25;   // a category is a way in, not usually the answer
            if ($r['kind'] === 'outdoors' && $why === 'explore') $score += 10;
            // the Chameleon Filter (brand.md §5; decisions/0058 §19): on Let's Explore, the place's stories, outdoors,
            // groups and topics come first and businesses follow as connections; on Get Local, businesses lead as before
            if (($o['track'] ?? '') === 'explore') $score += ['story' => 60, 'outdoors' => 45, 'group' => 20, 'topic' => 55, 'listing' => -20][$r['kind']] ?? 0;
            // ...and of the businesses, the places people go to see (parks, monuments, landmarks, museums, historic sites)
            if (($o['track'] ?? '') === 'explore' && $r['kind'] === 'listing' && preg_match('/ (national park|monument|landmark|museum|historic|visitor cent|state park|preserve|observator)/', (string) $r['terms'])) $score += 45;
            if (!empty($o['prefer']) && str_contains((string) $r['terms'], ' ' . $o['prefer'] . ' ')) $score += 20;
            // what an action is for ("fishing for apache trout"): those with the words first, never required
            foreach ((array) ($o['boost'] ?? []) as $bw) { $bn = trim(self::norm((string) $bw)); if ($bn !== '' && (str_contains((string) $r['terms'], ' ' . $bn) || str_contains($nlab, ' ' . $bn) || str_contains(' ' . self::norm((string) ($r['about'] ?? '')), ' ' . $bn))) $score += 30; }   // "the mayor": city offices first
            $score += (int) $r['weight'];
            $out[] = ['score' => $score, 'kind' => (string) $r['kind'], 'id' => (string) $r['item_id'], 'label' => (string) $r['label'],
                      'sub' => ($r['kind'] === 'listing' && class_exists('SearchText') ? preg_replace_callback('/^[^·]+/u', fn ($m) => SearchText::catLabel(trim($m[0])) . (str_contains((string) $r['sub'], '·') ? ' ' : ''), (string) $r['sub']) : (string) $r['sub']) . ($miles !== null ? ' · ' . ($miles < 1 ? '<1' : (string) round($miles)) . ' mi' : ''), 'url' => (string) $r['url'],
                      'open' => $open, 'miles' => $miles, 'peek' => $r['kind'] === 'listing' || $r['kind'] === 'outdoors', 'who' => (string) $r['who'], 'funnel' => self::funnelOf((string) $r['kind'], (string) $r['item_id']),
                      'named' => $named];
        }
        // Local first (decisions/0060, 0075): with a distance around a place, nearer steps come first (10, 25, 50 mi,
        // then the rest), the best match first within each; a place searched by its own name leads wherever it is.
        // Without it, "mental health" 250 mi around St. Johns led with names 40 mi away over counselors in town.
        $band = fn ($x) => $x['named'] ? 0 : ($x['miles'] === null ? 4 : ($x['miles'] <= 10 ? 1 : ($x['miles'] <= 25 ? 2 : ($x['miles'] <= 50 ? 3 : 4))));
        if ($area && (float) $area['radius'] > 10) usort($out, fn ($a, $b) => [$band($a), $b['score'], $a['label']] <=> [$band($b), $a['score'], $b['label']]);
        else usort($out, fn ($a, $b) => [$b['score'], $a['label']] <=> [$a['score'], $b['label']]);
        if ($nearest) usort($out, fn ($a, $b) => [$a['miles'] === null ? 1 : 0, (float) ($a['miles'] ?? 0), $a['label']] <=> [$b['miles'] === null ? 1 : 0, (float) ($b['miles'] ?? 0), $b['label']]);
        $kinds = []; $funnels = [];
        foreach ($out as $x) { $kinds[$x['kind']] = ($kinds[$x['kind']] ?? 0) + 1; if ($x['kind'] !== 'topic') $funnels[$x['funnel']] = ($funnels[$x['funnel']] ?? 0) + 1; }   // a section counts what's listed under it, never the categories (2026-11-22)
        $total = count($out);
        // enough of each kind to group by kind, in the order found
        $items = [];
        $per = [];
        foreach ($out as $x) {
            if (count($items) >= $limit && ($per[$x['kind']] ?? 0) >= 6) continue;   // enough of each to fill a section tab
            if (($per[$x['kind']] ?? 0) >= ($x['kind'] === 'topic' ? 3 : $limit)) continue;
            $per[$x['kind']] = ($per[$x['kind']] ?? 0) + 1;
            unset($x['score']);
            $items[] = $x;
        }
        return ['items' => $items, 'kinds' => $kinds, 'funnels' => $funnels, 'audience_found' => $audFound, 'total' => $total, 'loose' => $any];
    }

    /**
     * Rows in a place for a page or a map (Discover's views, map pins): by kind, Why, and any of some words.
     * @param array $o kinds (list), why ('' or intent), words (any of), limit, geo (only rows with a point)
     */
    public static function rows(PDO $pdo, ?array $place, ?array $area, array $o = []): array
    {
        $p = [];
        $w = [];
        if (!empty($o['kinds'])) {
            $in = [];
            foreach (array_values($o['kinds']) as $i => $k) { $in[] = ":k$i"; $p[":k$i"] = $k; }
            $w[] = 'si.kind IN (' . implode(',', $in) . ')';
        }
        if (!empty($o['why'])) { $w[] = 'si.why LIKE :why'; $p[':why'] = '%' . $o['why'] . '%'; }
        if (!empty($o['words'])) {
            $or = [];
            foreach (array_values($o['words']) as $i => $word) {
                // a short word ("art") or one marked whole ("nation=") matches the whole word, not "arthritis" or "national"
                $whole = str_ends_with((string) $word, '=') || mb_strlen(rtrim((string) $word, '=')) <= 3;
                $n = trim(self::norm(rtrim((string) $word, '=')));
                $or[] = "si.terms LIKE :ww$i"; $p[":ww$i"] = '% ' . $n . ($whole ? ' %' : '%');
            }
            $w[] = '(' . implode(' OR ', $or) . ')';
        }
        if (!empty($o['geo'])) $w[] = 'si.lat IS NOT NULL AND si.lon IS NOT NULL';
        if (!empty($o['not_place']) && ($not = self::whereSql($pdo, $o['not_place'], null, $p)) !== '') $w[] = "NOT COALESCE($not, 0)";   // around a place, not in it
        $where = self::whereSql($pdo, $place, $area, $p);
        if ($where !== '') $w[] = $where;
        $limit = max(1, min(5000, (int) ($o['limit'] ?? 200)));
        $st = $pdo->prepare('SELECT si.kind, si.item_id, si.label, si.sub, si.url, si.terms, si.why, si.who, si.about, si.lat, si.lon, si.city, si.state, si.weight, si.category_id
                               FROM search_index si' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY si.weight DESC, si.label LIMIT ' . $limit);
        $st->execute($p);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($area && $r['lat'] !== null) {
                $r['miles'] = self::miles((float) $area['lat'], (float) $area['lon'], (float) $r['lat'], (float) $r['lon']);
                if ($r['miles'] > (float) $area['radius']) continue;
            }
            $out[] = $r;
        }
        if ($area) usort($out, fn ($a, $b) => ($a['miles'] ?? 0) <=> ($b['miles'] ?? 0));
        return $out;
    }

    /** The cuisines named in a row's terms (from FOOD), for grouping places to eat. */
    public static function cuisines(string $terms): array
    {
        $out = [];
        foreach (array_keys(self::FOOD) as $tag) if (str_contains($terms, ' ' . $tag . ' ')) $out[] = $tag;
        return $out;
    }

    public static function miles(float $a, float $b, float $c, float $d): float
    {
        $r = M_PI / 180;
        $x = sin(($c - $a) * $r / 2) ** 2 + cos($a * $r) * cos($c * $r) * sin(($d - $b) * $r / 2) ** 2;
        return 3958.8 * 2 * asin(min(1, sqrt($x)));
    }

    /** SQL on si.* for a place (boundary) or an area (a box; the exact distance is checked after). '' = anywhere. */
    public static function whereSql(PDO $pdo, ?array $place, ?array $area, array &$p): string
    {
        if ($area) {
            $dLat = $area['radius'] / 69.0; $dLon = $area['radius'] / max(0.0001, 69.0 * cos(deg2rad((float) $area['lat'])));
            $p += [':la1' => $area['lat'] - $dLat, ':la2' => $area['lat'] + $dLat, ':lo1' => $area['lon'] - $dLon, ':lo2' => $area['lon'] + $dLon];
            return '(si.lat BETWEEN :la1 AND :la2 AND si.lon BETWEEN :lo1 AND :lo2)';
        }
        if (!$place) return '';
        $or = [];
        $param = ['cluster' => 'cluster', 'geo_hub' => 'geo', 'hub' => 'hub'][$place['level'] ?? ''] ?? null;
        if ($param && !empty($place['slug']) && empty($place['town'])) {   // a town in its area: its own ZIPs only
            $s = PlaceScope::resolve($pdo, [$param => (string) $place['slug']]);
            if ($s && $s['found']) $or[] = str_replace(['e.micro_cluster_id', 'e.primary_zip'], ['si.micro_cluster_id', 'si.zip'], (string) $s['sql']);
        }
        $zips = array_slice(array_values(array_filter(array_map('strval', (array) ($place['zips'] ?? [])))), 0, 60);
        // a county: every ZIP in it, not only the first 60 ("Los Angeles County" has hundreds)
        if (!empty($place['county']) && !empty($place['state'])) {
            $or[] = 'si.zip IN (SELECT TRIM(zc.zip) FROM zip_coordinates zc WHERE TRIM(zc.county) = :pco AND TRIM(zc.state) = :pcs)';
            $p[':pco'] = (string) $place['county']; $p[':pcs'] = (string) $place['state'];
            $zips = [];
        }
        // a state: every ZIP in it
        if (!empty($place['is_state']) && !empty($place['state'])) {
            $or[] = 'si.zip IN (SELECT TRIM(zc.zip) FROM zip_coordinates zc WHERE TRIM(zc.state) = :pst)';
            $p[':pst'] = (string) $place['state'];
            $zips = [];
        }
        // a town: every ZIP in it, and listings that give it as their city, not only its first 60 ZIPs ("pizza in phoenix":
        // Phoenix has more than 60, and a downtown pizzeria's ZIP was past them)
        if (!empty($place['city']) && !empty($place['state']) && empty($place['county']) && empty($place['is_state'])) {
            $or[] = 'si.zip IN (SELECT TRIM(zc.zip) FROM zip_coordinates zc WHERE zc.city = :ptc AND TRIM(zc.state) = :pts)';
            $or[] = '(LOWER(si.city) = :ptc2 AND si.state = :pts2)';
            $p[':ptc'] = (string) $place['city']; $p[':pts'] = (string) $place['state'];
            $p[':ptc2'] = mb_strtolower(trim((string) $place['city'])); $p[':pts2'] = (string) $place['state'];
        }
        if ($zips) {
            $ph = [];
            foreach ($zips as $i => $z) { $ph[] = ":z$i"; $p[":z$i"] = substr(trim($z), 0, 5); }
            $or[] = 'si.zip IN (' . implode(',', $ph) . ')';
        }
        if (!empty($place['key'])) { $or[] = 'si.place_key = :pkey'; $p[':pkey'] = (string) $place['key']; }
        if (!$or && !empty($place['city'])) { $or[] = '(LOWER(si.city) = :pcity AND si.state = :pst)'; $p[':pcity'] = mb_strtolower((string) $place['city']); $p[':pst'] = (string) ($place['state'] ?? ''); }
        if (!$or && !empty($place['state'])) { $or[] = 'si.state = :pst2'; $p[':pst2'] = (string) $place['state']; }
        return $or ? '(' . implode(' OR ', $or) . ')' : '';
    }
}
