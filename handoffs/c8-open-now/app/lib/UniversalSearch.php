<?php

declare(strict_types=1);

require_once __DIR__ . '/PlaceScope.php';
require_once __DIR__ . '/SearchText.php';
require_once __DIR__ . '/ListingSafety.php';
require_once __DIR__ . '/Links.php';
require_once __DIR__ . '/Community.php';
if (is_file(__DIR__ . '/PlaceProfile.php')) require_once __DIR__ . '/PlaceProfile.php';
if (is_file(__DIR__ . '/Stories.php')) require_once __DIR__ . '/Stories.php';
if (is_file(__DIR__ . '/Tags.php')) require_once __DIR__ . '/Tags.php';
if (is_file(__DIR__ . '/SearchIndex.php')) require_once __DIR__ . '/SearchIndex.php';

/**
 * api/lib/UniversalSearch.php — one search for everything, answered in place (decisions/0053).
 *
 * understand(): the query becomes chips the person can see and remove.
 *   place ("Greer Arizona", "in Round Valley"): a place name wins over a business name unless the whole query is
 *     exactly a business's name; category ("physical therapy"); kind ("groups", "stories"); conditions ("has phone",
 *     "has website"); facts ("population", "demographics", "median age", "income"). What's left is the search text.
 * search(): grouped results, each routed to its home: places, a facts answer, listings (peek first), stories and
 *   guides, community groups, topics, and a hand-off to the Address Book for people (people are never in this search).
 * Nothing is stored about the query here (decisions/0053 §7).
 */
require_once __DIR__ . '/CategoryAlso.php';

final class UniversalSearch
{
    private const STATUSES = ['unclaimed', 'claimed-unverified', 'pending-verification', 'verified'];
    private const STATES = ['alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR', 'california' => 'CA', 'colorado' => 'CO',
        'connecticut' => 'CT', 'delaware' => 'DE', 'florida' => 'FL', 'georgia' => 'GA', 'hawaii' => 'HI', 'idaho' => 'ID', 'illinois' => 'IL',
        'indiana' => 'IN', 'iowa' => 'IA', 'kansas' => 'KS', 'kentucky' => 'KY', 'louisiana' => 'LA', 'maine' => 'ME', 'maryland' => 'MD',
        'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN', 'mississippi' => 'MS', 'missouri' => 'MO', 'montana' => 'MT',
        'nebraska' => 'NE', 'nevada' => 'NV', 'new hampshire' => 'NH', 'new jersey' => 'NJ', 'new mexico' => 'NM', 'new york' => 'NY',
        'north carolina' => 'NC', 'north dakota' => 'ND', 'ohio' => 'OH', 'oklahoma' => 'OK', 'oregon' => 'OR', 'pennsylvania' => 'PA',
        'rhode island' => 'RI', 'south carolina' => 'SC', 'south dakota' => 'SD', 'tennessee' => 'TN', 'texas' => 'TX', 'utah' => 'UT',
        'vermont' => 'VT', 'virginia' => 'VA', 'washington' => 'WA', 'west virginia' => 'WV', 'wisconsin' => 'WI', 'wyoming' => 'WY'];
    private const KINDS = ['groups' => 'groups', 'group' => 'groups', 'stories' => 'stories', 'story' => 'stories', 'guides' => 'stories',
        'guide' => 'stories', 'places' => 'places', 'towns' => 'places', 'businesses' => 'listings', 'business' => 'listings',
        'listings' => 'listings', 'topics' => 'topics'];
    private const KIND_LABEL = ['groups' => 'Groups', 'stories' => 'Stories & guides', 'places' => 'Places', 'listings' => 'Listings', 'topics' => 'Topics'];
    private const FACTS = '/\b(population|demographics?|median\s+age|(household\s+)?income|how\s+many\s+people|census|poverty|home\s+values?|rent)\b/i';
    private const CONDS = ['has phone' => 'phone', 'with phone' => 'phone', 'has website' => 'website', 'with website' => 'website', 'with a website' => 'website'];

    // The 5 Ws (decisions/0053 §8). Where, What (category, kind, words) are read below; these read When, Why and Who.
    private const DAYS = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
    /** Why: phrases that say what the person wants to do, longest first. They leave the search words. */
    private const WHY_PHRASES = [
        'where can i get help' => 'help', 'somewhere to eat' => 'eat', 'something to eat' => 'eat', 'places to eat' => 'eat', 'place to eat' => 'eat',
        'where to eat' => 'eat', 'grab a bite' => 'eat', 'to eat' => 'eat', 'eat' => 'eat', 'hungry' => 'eat', 'breakfast' => 'eat', 'lunch' => 'eat',
        'dinner' => 'eat', 'brunch' => 'eat',
        'somewhere to stay' => 'stay', 'places to stay' => 'stay', 'place to stay' => 'stay', 'where to stay' => 'stay', 'to stay' => 'stay', 'overnight' => 'stay',
        'see a doctor' => 'heal', 'get care' => 'heal', 'get checked' => 'heal', 'feel better' => 'heal',
        'get help' => 'help', 'need help' => 'help', 'help with' => 'help', 'help' => 'help',
        'things to do' => 'explore', 'what to do' => 'explore', 'stuff to do' => 'explore', 'places to go' => 'explore', 'where to go' => 'explore',
        'i should check out' => 'explore', 'should check out' => 'explore', 'to check out' => 'explore', 'check out' => 'explore', 'checkout' => 'explore',
        'worth the drive' => 'explore', 'worth the trip' => 'explore', 'worth seeing' => 'explore', 'worth visiting' => 'explore', 'must see' => 'explore', 'should see' => 'explore', 'should visit' => 'explore', 'to visit' => 'explore',
        'places to see' => 'explore', 'to see' => 'explore', 'to explore' => 'explore', 'explore' => 'explore', 'sightseeing' => 'explore',
        'day trip' => 'explore', 'to do' => 'explore', 'fun' => 'explore', 'adventure' => 'explore',
        'meet people' => 'connect', 'get involved' => 'connect', 'volunteer' => 'connect', 'connect with' => 'connect',
        'where to buy' => 'buy', 'to buy' => 'buy', 'buy' => 'buy', 'shopping' => 'buy', 'shop for' => 'buy', 'to shop' => 'buy',
        'for beginners' => 'learn', 'beginners' => 'learn', 'beginner' => 'learn', 'to learn' => 'learn', 'learn' => 'learn', 'classes' => 'learn', 'lessons' => 'learn',
        'fix my' => 'fix', 'repair my' => 'fix', 'fix up' => 'fix', 'fix' => 'fix', 'home improvement' => 'fix', 'home repair' => 'fix', 'home repairs' => 'fix',
    ];
    /** Words that only carried the question ("where can I find good …"), dropped once a Why was read. */
    /** Words that describe the thing but don't name it ("a new pizza place", "a good taco spot"). */
    private const DESCRIBE = ['new', 'newest', 'latest', 'recent', 'good', 'best', 'great', 'nice', 'local', 'little', 'favorite', 'popular', 'cheap', 'a', 'an', 'the', 'some', 'any',
                              'place', 'places', 'spot', 'spots', 'joint', 'joints', 'store', 'stores', 'shop', 'shops'];
    /**
     * "Near me" and its kin (decisions/0067, 2026-10-08): where to search, never words to match. Taken out before anything
     * reads the words, so "me" is never Maine or the start of "medical", and "concerts near me" searches concerts.
     * Longest first.
     */
    private const NEAR_ME = 'close to me|close by me|near to me|closest to me|nearest to me|near me|nearme|around me|by me|nearby|near by|close by|closest|nearest|near here|around here';
    /** Other words that mean where the person is (decisions/0067, 2026-10-08): kept as words, but they need a place. */
    private const HERE_WORDS = '/\\b(local|locally|in my area|my area|in town|around town|walking distance|driving distance|within \\d+ ?(mi|mile|miles|km)|\\d+ ?(mi|miles) (away|from)|directions to|how far|how close)\\b/u';

    /** Whether the words need to know where the person is to mean anything: "near me" and its kin, "local", "in town", "how far". */
    public static function saysHere(string $q): bool
    {
        return self::nearMe($q)['near'] || (bool) preg_match(self::HERE_WORDS, mb_strtolower($q));
    }

    /** The phrases offered when one is still being typed ("near m" → near me), in order. */
    private const NEAR_ME_SAY = ['near me', 'nearby', 'nearest', 'closest', 'close to me', 'close by', 'around me'];

    /**
     * The words without "near me" (and its kin), whether it was said, and the start of one still being typed at the end
     * ("concerts near m": "near m"). ['text' => 'concerts', 'near' => bool, 'partial' => 'near m'|''].
     */
    public static function nearMe(string $q): array
    {
        $t = ' ' . trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($q))) . ' ';
        $near = false; $partial = '';
        if (preg_match('/ (?:' . self::NEAR_ME . ') /u', $t)) {
            $near = true;
            do { $t0 = $t; $t = (string) preg_replace('/ (?:' . self::NEAR_ME . ') /u', ' ', $t); } while ($t !== $t0);
        } elseif (preg_match('/ (near|near m|near t|near to|near to m|nearb|neare|neares|close t|close to|close to m|close b|closes|around m)$/u', rtrim($t), $m)) {
            // the start of one, still being typed; "near" alone may yet be "near show low", so it's only offered, never assumed
            $partial = $m[1];
            $t = mb_substr(rtrim($t), 0, -mb_strlen($m[0])) . ' ';
        }
        return ['text' => trim((string) preg_replace('/\s+/u', ' ', $t)), 'near' => $near, 'partial' => $partial];
    }

    /**
     * Outings (Jason, 2026-11-10: "Kids? Ages? Type of activities? We have an Intelligence built into the search but it
     * seems to fall short when sizing up the context"): who's coming (kids, toddlers, teens, the family, the dog, a date)
     * and the kind of day (rainy, indoors, outdoors), said around words that only frame the ask ("place to take my",
     * "things to do with"). [phrase => [for, age]], longest first.
     */
    private const OUTING_WHO = ['little ones' => ['family', 'little'], 'little kids' => ['family', 'little'], 'young kids' => ['family', 'little'],
        'small kids' => ['family', 'little'], 'big kids' => ['family', 'kids'], 'older kids' => ['family', 'teens'], 'date night' => ['date', ''],
        'grandchildren' => ['family', 'kids'], 'grandkids' => ['family', 'kids'], 'toddlers' => ['family', 'little'], 'toddler' => ['family', 'little'],
        'preschoolers' => ['family', 'little'], 'babies' => ['family', 'little'], 'baby' => ['family', 'little'], 'teenagers' => ['family', 'teens'],
        'teenager' => ['family', 'teens'], 'tweens' => ['family', 'teens'], 'teens' => ['family', 'teens'], 'teen' => ['family', 'teens'],
        'children' => ['family', 'kids'], 'child' => ['family', 'kids'], 'kiddos' => ['family', 'kids'], 'kids' => ['family', 'kids'], 'kid' => ['family', 'kids'],
        'families' => ['family', ''], 'family' => ['family', ''], 'puppy' => ['dog', ''], 'pups' => ['dog', ''], 'pup' => ['dog', ''], 'dogs' => ['dog', ''],
        'dog' => ['dog', ''], 'girlfriend' => ['date', ''], 'boyfriend' => ['date', ''], 'wife' => ['date', ''], 'husband' => ['date', ''],
        'romantic' => ['date', ''], 'date' => ['date', '']];
    private const OUTING_SET = ['rainy day' => 'indoor', 'rainy days' => 'indoor', 'too hot' => 'indoor', 'hot day' => 'indoor', 'air conditioned' => 'indoor',
        'raining' => 'indoor', 'rainy' => 'indoor', 'rain' => 'indoor', 'indoors' => 'indoor', 'indoor' => 'indoor', 'inside' => 'indoor',
        'nice day' => 'outdoor', 'sunny' => 'outdoor', 'outdoors' => 'outdoor', 'outdoor' => 'outdoor', 'outside' => 'outdoor'];
    /** Words that only frame an outing ("a place to take my", "fun things to do with the"). */
    private const OUTING_FRAME = ['place', 'places', 'somewhere', 'spot', 'spots', 'thing', 'things', 'stuff', 'something', 'anything', 'activity', 'activities',
        'fun', 'to', 'do', 'take', 'taking', 'bring', 'bringing', 'with', 'for', 'my', 'our', 'your', 'the', 'a', 'an', 'go', 'going', 'where', 'can', 'could',
        'should', 'would', 'i', 'we', 'us', 'me', 'what', 'some', 'good', 'best', 'great', 'cool', 'entertain', 'entertaining', 'keep', 'busy', 'occupy', 'occupied',
        'outing', 'outings', 'day', 'days', 'out', 'ideas', 'idea', 'night', 'friendly', 'visit', 'see', 'play', 'let', 'lets', 'and', 'or', 'of', 'on', 'at',
        'enjoy', 'around', 'here', 'when', 'its', 'it', 'is', 'are', 'there', 'any', 'free', 'cheap', 'family-friendly', 'kid-friendly', 'dog-friendly', 'pet-friendly',
        'things-to-do', 'to-do', 'have', 'has', 'need', 'want', 'like', 'love', 'that', 'which', 'who', 'all', 'year', 'old', 'olds', 'ages', 'age', 'aged'];
    /** Time words an outing leaves for the When step ("with kids this weekend"). */
    private const OUTING_PASS = ['today', 'tonight', 'tomorrow', 'weekend', 'weekends', 'this', 'now', 'saturday', 'sunday', 'friday', 'afternoon', 'morning', 'evening'];
    /** A "friendly" ask beside a thing ("family friendly restaurants"): its words put those first, never hide the rest. */
    private const OUTING_BOOST = ['family' => ['family', 'kids', 'children', 'playground', 'kid'], 'dog' => ['dog', 'dogs', 'pet', 'pets', 'patio'],
        'date' => ['wine', 'bistro', 'steakhouse', 'fine dining', 'romantic', 'cocktail', 'dessert', 'patio', 'view']];

    /**
     * An outing in the words: ['for' => family|dog|date|'', 'age' => little|kids|teens|'', 'setting' => indoor|outdoor|'',
     * 'text' => the words left, 'soft' => bool (who it's for beside a thing: orders only), 'said' => [phrases]], or null.
     * "kids dentist", "dog grooming", "indoor pool" are things, not outings: null.
     */
    public static function outing(string $text): ?array
    {
        $t = ' ' . trim((string) preg_replace('/\s+/u', ' ', mb_strtolower(str_replace(['’', "'"], ['', ''], $text)))) . ' ';
        $t = (string) preg_replace('/ (\d{1,2})(?: ?(?:-|to) ?\d{1,2})? ?(?:years?|yrs?|y\/?o)( olds?)? /u', ' $1yo ', $t);   // "5 year old", "3-5 yo"
        $for = ''; $age = ''; $set = ''; $said = [];
        if (preg_match('/ (\d{1,2})yo /u', $t, $am)) { $n = (int) $am[1]; $for = 'family'; $age = $n <= 4 ? 'little' : ($n <= 12 ? 'kids' : 'teens'); $said[] = trim($am[0]); $t = str_replace($am[0], ' ', $t); }
        foreach (self::OUTING_WHO as $ph => [$f, $a]) {
            if (!preg_match('/ ' . preg_quote($ph, '/') . ' /u', $t)) continue;
            if ($for === '') $for = $f;
            if ($a !== '' && ($age === '' || $f === $for)) $age = $age ?: $a;
            $t = (string) preg_replace('/ ' . preg_quote($ph, '/') . ' /u', ' ', $t); $said[] = $ph;
        }
        foreach (self::OUTING_SET as $ph => $s) {
            if (!preg_match('/ ' . preg_quote($ph, '/') . ' /u', $t)) continue;
            if ($set === '') $set = $s;
            $t = (string) preg_replace('/ ' . preg_quote($ph, '/') . ' /u', ' ', $t); $said[] = $ph;
        }
        if (!$said) return null;
        $words = array_values(array_filter(explode(' ', trim($t)), fn ($w) => $w !== ''));
        $rest = array_values(array_filter($words, fn ($w) => !in_array($w, self::OUTING_FRAME, true) && !in_array($w, self::OUTING_PASS, true)));
        $pass = array_values(array_filter($words, fn ($w) => in_array($w, self::OUTING_PASS, true)));
        if (!$rest) return ['for' => $for, 'age' => $for === 'family' ? $age : '', 'setting' => $set, 'text' => implode(' ', $pass), 'soft' => false, 'said' => $said];
        // a thing with who it's for ("family friendly restaurants", "romantic dinner", "kid friendly pizza"): the thing is
        // searched; who it's for puts the fitting ones first
        $friendly = (bool) preg_match('/ (friendly|with|for) /u', ' ' . mb_strtolower($text) . ' ') || preg_match('/-friendly/u', mb_strtolower($text));
        if ($for !== '' && ($friendly || $for === 'date') && !$set) {
            $keep = array_values(array_filter($words, fn ($w) => !in_array($w, ['friendly', 'family-friendly', 'kid-friendly', 'dog-friendly', 'pet-friendly', 'with', 'for', 'my', 'our', 'the', 'a'], true)));
            return ['for' => $for, 'age' => '', 'setting' => '', 'text' => implode(' ', $keep), 'soft' => true, 'said' => $said];
        }
        return null;
    }

    /**
     * An outing being typed ("place to take my k"), as full words with what they find: the words as they are when they
     * already read as an outing, else the last word finished as one word of who's coming or the kind of day (kids,
     * toddlers, rainy), the commoner ways first, one per reading. [['label', 'means', 'q', 'kind' => 'outing']], at most two.
     */
    private static function outingIntents(string $q, bool $end = false): array
    {
        $q = trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($q)));
        if (!str_contains($q, ' ')) return [];   // one word ("kids", "dog") is a thing, read as typed
        $cands = [];
        if (($og = self::outing($q)) && !$og['soft']) $cands[] = $q;
        elseif (!$end && preg_match('/^(.+\s)(\S+)$/u', $q, $m) && !in_array($m[2], array_merge(self::OUTING_FRAME, self::OUTING_PASS), true)) {   // "things to do" isn't "things to dogs"
            $ph = array_values(array_filter(array_merge(array_keys(self::OUTING_WHO), array_keys(self::OUTING_SET)), fn ($p) => !str_contains($p, ' ') && str_starts_with($p, $m[2]) && $p !== $m[2]));
            usort($ph, fn ($a, $b) => [!str_ends_with($a, 's'), mb_strlen($a)] <=> [!str_ends_with($b, 's'), mb_strlen($b)]);   // "kids" before "kiddos" and "kid"
            foreach ($ph as $p) $cands[] = $m[1] . $p;
        }
        $out = []; $seen = [];
        foreach ($cands as $c) {
            $og = self::outing($c);
            if (!$og || $og['soft'] || isset($seen[$l = self::outingLabel($og)])) continue;
            $seen[$l] = 1;
            $out[] = ['label' => ucfirst($c), 'means' => $l . ': places that suit them, nearest first', 'q' => $c, 'kind' => 'outing'];
            if (count($out) >= 2) break;
        }
        return $out;
    }

    /** The outing's chip label: "With toddlers", "With the family · indoors", "Rainy day: indoors", "Date night". */
    private static function outingLabel(array $o): string
    {
        $l = ['little' => 'With little ones', 'kids' => 'With kids', 'teens' => 'With teens'][$o['age']] ?? (['family' => 'With the family', 'dog' => 'With your dog', 'date' => 'Date night'][$o['for']] ?? '');
        $s = ['indoor' => 'indoors', 'outdoor' => 'outdoors'][$o['setting']] ?? '';
        return $l !== '' ? $l . ($s !== '' ? ' · ' . $s : '') : ucfirst($s);
    }

    private const FILLER = ['where', 'what', 'somewhere', 'some', 'good', 'best', 'great', 'nice', 'a', 'an', 'to', 'can', 'i', 'we', 'go', 'get', 'find', 'me', 'is', 'are', 'there', 'any', 'for', 'some', 'place', 'places'];
    /** The site's sections, named in a query ("in the directory", "community"): results then come from that section only. */
    // Only with "in", "on" or "from" in front, so "farmers market", "community college" and "social services" stay words.
    private const FUNNEL_WORDS = ["let's explore" => 'explore', 'lets explore' => 'explore', 'discovery' => 'explore', 'discover' => 'explore',
        'get local' => 'local', 'directory' => 'local', 'listings' => 'local', 'social' => 'social', 'community' => 'social', 'groups' => 'social',
        'marketplace' => 'market', 'market' => 'market'];
    /** Who it's for: "for veterans", "for kids". Keys match SearchIndex::AUDIENCE. */
    private const FOR_WHOM = ['veterans' => 'veterans', 'veteran' => 'veterans', 'vets' => 'veterans', 'seniors' => 'seniors', 'elders' => 'seniors',
        'the elderly' => 'seniors', 'older adults' => 'seniors', 'kids' => 'kids', 'children' => 'kids', 'teens' => 'kids', 'youth' => 'kids',
        'the kids' => 'kids', 'families' => 'families', 'family' => 'families', 'women' => 'women', 'moms' => 'women', 'mothers' => 'women'];
    /** Public offices: the role is searched as the office that holds it (never the person). */
    private const ROLES = ['board of supervisors' => ['County supervisors', 'county government'], 'county supervisors' => ['County supervisors', 'county government'],
        'county supervisor' => ['County supervisor', 'county government'],   // [label, searched as, preferred word] 'school board' => ['School board', 'school district'],
        'superintendent' => ['Superintendent', 'school district'], 'tribal council' => ['Tribal council', 'tribal government'],
        'city council' => ['City council', 'government', 'city'], 'town council' => ['Town council', 'government', 'city'], 'council' => ['Council', 'government'],
        'mayor' => ['Mayor', 'government', 'city'], 'city clerk' => ['City clerk', 'government', 'city'], 'town clerk' => ['Town clerk', 'government', 'city'],
        'police chief' => ['Police chief', 'police'], 'fire chief' => ['Fire chief', 'fire'], 'sheriff' => ['Sheriff', 'sheriff'],
        'judge' => ['Judge', 'court'], 'postmaster' => ['Postmaster', 'post office']];

    // ---- understanding the query ----------------------------------------------------------------------

    /**
     * @param array $ctx  scope: ['level' => 'cluster'|'geo_hub'|'hub', 'slug' => ...] the page's or visitor's place (may be empty)
     * @param array $drop chip types the person removed: 'place', 'scope', 'category', 'kind', 'cond', 'facts'
     */
    /** Words that name a kind of feature, and are also a town's name somewhere: only a place on their own or after "in". */
    private const FEATURE_WORDS = ['lake', 'lakes', 'river', 'creek', 'park', 'springs', 'spring', 'canyon', 'mesa', 'valley', 'beach', 'forest', 'mountain',
        'mountains', 'falls', 'rock', 'rocks', 'peak', 'butte', 'ridge', 'pass', 'point', 'island', 'bay', 'harbor', 'hills', 'meadow', 'meadows', 'pines',
        'woods', 'trail', 'dam', 'reservoir', 'pond', 'wells', 'center', 'junction', 'city', 'village', 'crossing', 'ranch', 'gap', 'bluff', 'grove'];

    /** A step in how the words were read, for the term analysis (api/lib/SearchTrace.php); nothing unless it's on. */
    private static function tr(string $w, string $what, string $because = '', $words = []): void
    {
        if (class_exists('SearchTrace', false) && SearchTrace::active()) SearchTrace::add($w, $what, $because, is_array($words) ? $words : preg_split('/\s+/u', trim((string) $words)));
    }

    public static function understand(PDO $pdo, string $q, array $ctx = [], array $drop = []): array
    {
        $text = ' ' . mb_strtolower(trim(preg_replace('/\s+/', ' ', $q))) . ' ';
        self::tr('How', 'Read the words, in a fixed order', 'Why (actions), conditions, facts, which section, When, Who, help, Why (phrases), kind, Where, What (category). Each step takes its words out before the next looks.');
        // one thing spelled two ways: "star gazing", "star gaz" (still typing) are stargazing
        $t0 = $text; $text = preg_replace('/ star[ -]?gaz\w* /u', ' stargazing ', $text);
        if ($t0 !== $text) self::tr('What', 'Spelled two ways: read as "stargazing"', 'One thing spelled two ways ("star gazing", "star gaz") is one word.', 'stargazing');
        // "near me", "nearby", "closest" (decisions/0067, 2026-10-08): where, not what; taken out before anything reads the words
        $nm0 = self::nearMe($text);
        $nearMe = $nm0['near'];
        if ($nearMe) { $text = ' ' . $nm0['text'] . ' '; self::tr('Where', 'Near you', '"Near me", "nearby" and "closest" say where, so they aren\'t words to match: your place, or yours to set.', 'near me'); }
        $chips = [];
        // A city first (Jason, 2026-11-10: "Locations should always search the locations db, and identify cities as
        // mandatory even if there is no data nor open hub"): read from the words as typed, before any step below can take
        // a word out ("dana point" lost "dana" and became Point, TX). Every search is demand.
        $place0 = null;
        if (!in_array('place', $drop, true) && trim($text) !== '') {
            [$place0, $rest0] = self::cityFirst($pdo, trim($text));
            if ($place0) { $text = ' ' . $rest0 . ' '; self::tr('Where', (string) $place0['label'], 'A city in the words, read first from the locations data: it is the place, with or without our data there.', (string) $place0['label']); }
        }
        // an outing (Jason, 2026-11-10): who's coming and what kind of day, before anything reads its words one by one
        // ("take" isn't Takeout; "kids" isn't Youth Centers; "date" isn't a When)
        if (!in_array('outing', $drop, true) && trim($text) !== '' && ($og = self::outing(trim($text)))) {
            $chips[] = ['type' => 'outing', 'value' => $og['for'], 'age' => $og['age'], 'setting' => $og['setting'], 'soft' => $og['soft'], 'label' => self::outingLabel($og)];
            if (!$og['soft']) $chips[] = ['type' => 'why', 'value' => 'explore', 'label' => 'Things to do'];
            $text = ' ' . $og['text'] . ' ';
            self::tr('Who', self::outingLabel($og) ?: 'An outing', $og['soft'] ? 'Who it\'s for, beside the thing: the places that suit them come first, never hiding the rest.'
                : 'Who\'s coming and what kind of day, around words that only frame the ask ("place to take my"): the kinds of places that suit them, nearest first.', $og['said']);
        }
        // the 5 W's per word (decisions/0061): a word with more than one sense gets the one the words around it pick.
        // "fall festival" is When (the season), "Show Low Falls" a waterfall (What), "I took a fall" Why (the situation
        // reads that one). The season becomes a When; a word read as another is replaced; a passing remark is set aside.
        if (is_file(__DIR__ . '/WordSenses.php')) {
            require_once __DIR__ . '/WordSenses.php';
            $ws = WordSenses::read($pdo, $text);
            foreach ($ws['read'] as [$W, $label, $because, $w0, $taught]) {
                if ($W === 'why') continue;   // the situation's own step shows it
                self::tr(WordSenses::W[$W] ?? 'What', 'Read "' . $w0 . '" as: ' . $label . ($taught ? ' (taught)' : ''), ucfirst($because) . '.', $w0);
            }
            if ($ws['when'] && !in_array('when', $drop, true)) $chips[] = ['type' => 'when', 'value' => $ws['when']['value'], 'label' => $ws['when']['label']];
            if (!$ws['why']) {
                // only the word itself changes, so the rest keeps its punctuation ("24/7", "St.")
                foreach ($ws['read'] as $r0) {
                    $to = str_starts_with($r0[5], 'words:') ? ' ' . trim(substr($r0[5], 6)) . ' ' : (str_starts_with($r0[5], 'season:') || $r0[5] === 'none' ? ' ' : null);
                    if ($to !== null) $text = preg_replace('/(?<=\W)' . preg_quote($r0[3], '/') . '(?=\W)/u', $to, $text, 1);
                }
                $text = ' ' . trim(preg_replace('/\s+/', ' ', $text)) . ' ';
            }
        }
        // An action ("going fishing for apache trout", "want to hike"): what they'll do, and what it's for. The activity
        // finds the places; the rest ("apache trout") puts the best of them first. Never read as a place.
        if (!in_array('do', $drop, true)) {
            foreach (self::ACTIVITIES as $act => $forms) {
                foreach ($forms as $f) {
                    $re = '/ (?:(?:' . self::GOING . ') )*' . preg_quote($f, '/') . ' /u';   // "where can i go stargazing": every going-word
                    if (!preg_match($re, $text, $am)) continue;
                    if (isset(self::ACT_NOT[$act]) && preg_match(self::ACT_NOT[$act], $text)) { self::tr('What', '"' . $f . '" here is a thing, not an activity', 'A word with it ("copper", "hose", a size) gives it its other meaning', ''); break 2; }
                    $cued = trim($am[0]) !== $f;   // a going-word came with it
                    $alone = trim($text) === $f;
                    if (!$cued && !$alone && !str_ends_with($f, 'ing') && mb_strlen($f) < 8) continue;   // "fish tacos" is dinner
                    if (!$cued && $f === 'fish') continue;   // "fish" alone too
                    if (!$cued && self::findCategory($pdo, trim($text))) { self::tr('What', '"' . trim($text) . '" is a kind of shop, not an activity', 'Said without a going-word, and a category has that name ("fishing bait").', trim($text)); break 2; }   // "fishing bait" is a shop's kind: the category reads it
                    $text = str_replace($am[0], ' ', $text);
                    $about = trim(preg_replace('/^(?:(?:for|with|at|on|the|some|a|an|to|in|near|around)\s+)+/u', '', trim($text)));
                    $chips[] = ['type' => 'do', 'value' => $act, 'label' => ucfirst($act), 'about' => ''];
                    self::tr('Why', 'An action: going ' . $act, $cued ? '"' . trim($am[0]) . '": an activity said with a going-word ("go", "want to", "where can we")' : 'An activity word on its own or ending in -ing', trim($am[0]));
                    $text = ' ' . $about . ' ';
                    break 2;
                }
            }
        }
        if (!in_array('cond', $drop, true)) {
            foreach (self::CONDS as $phrase => $v) {
                if (str_contains($text, " $phrase ")) { $chips[] = ['type' => 'cond', 'value' => $v, 'label' => ucfirst($phrase)]; $text = str_replace(" $phrase ", ' ', $text); self::tr('What', 'A condition: ' . $phrase, 'Only places that ' . str_replace(['has ', 'with '], ['have ', 'have '], $phrase), $phrase); }
            }
        }
        if (!in_array('facts', $drop, true) && preg_match(self::FACTS, $text, $m)) {
            $chips[] = ['type' => 'facts', 'label' => 'Place facts'];
            self::tr('What', 'Asks for facts about the place', 'Population, age or income words: answered from the U.S. Census.', $m[0]);
            $text = preg_replace(self::FACTS, ' ', $text);
            $text = preg_replace('/\b(of|for|the|what\s+is|whats|what\'s)\b/', ' ', $text);
        }
        // Words that only mean "here" ("a new pizza place in town"): the place being searched already says where
        $t0 = $text;
        $text = preg_replace('/ (in|around|about) (the )?(town|area|city|neighborhood) | (around|near|over) here | nearby | (close|close by|close to me) | (near|around) me /', ' ', $text);
        if ($t0 !== $text) self::tr('Where', 'Words that only mean "here"', '"Near me", "in town", "nearby" mean the place being searched; they\'re taken out.', array_diff(explode(' ', trim($t0)), explode(' ', trim($text))));
        // Which section of the site ("in the directory", "on the marketplace")
        if (!in_array('funnel', $drop, true)) {
            foreach (self::FUNNEL_WORDS as $w => $f) {
                if (preg_match('/ (in|on|from) (the )?' . preg_quote($w, '/') . ' /', $text, $m) || ($w === 'marketplace' && preg_match('/ (the )?marketplace /', $text, $m))) {
                    $chips[] = ['type' => 'funnel', 'value' => $f, 'label' => 'In ' . (class_exists('SearchIndex') ? SearchIndex::FUNNELS[$f] : $f)];
                    self::tr('Where', 'Only one section of the site: ' . $w, '"In", "on" or "from" before a section\'s name.', $m[0]);
                    $text = str_replace($m[0], ' ', $text);
                    break;
                }
            }
            // the panel's "Search in" chose a section
            if (!empty($ctx['funnel']) && !array_filter($chips, fn ($c) => $c['type'] === 'funnel') && class_exists('SearchIndex') && isset(SearchIndex::FUNNELS[$ctx['funnel']])) {
                $chips[] = ['type' => 'funnel', 'value' => $ctx['funnel'], 'label' => 'In ' . SearchIndex::FUNNELS[$ctx['funnel']], 'scope' => true];
            }
        }
        // When: open now, tonight, today, this weekend, a day of the week
        if (!in_array('when', $drop, true)) {
            $days = implode('|', array_keys(self::DAYS));
            $whens = [['/ (open )?(right )?now /', 'now', 'Open now'], ['/ (open )?(late tonight|tonight|late night|late) /', 'tonight', 'Open tonight'],
                      ['/ (open )?(on )?(this |the )?weekends? /', 'weekend', 'Open this weekend'], ['/ (open )?today /', 'today', 'Open today']];
            // a modifier that asks for someone available now ("emergency plumber", "24 hour towing"): open now and
            // 24-hour places first, the closed ones out; "plumber" stays the thing searched. Not part of a name:
            // "emergency room", "emergency medical services" stay words.
            array_unshift($whens, ['/ (emergency|urgent)(?! (room|rooms|medical|management|services|service|kit|kits|preparedness|department|dept)) /', 'now', 'Emergency: open now first'],
                                  ['/ (24 ?(hours?|hrs?)|24\/7|after[- ]hours) /', 'now', 'Open now first']);
            foreach ($whens as [$re, $v, $label]) {
                if (preg_match($re, $text, $wm)) { $chips[] = ['type' => 'when', 'value' => $v, 'label' => $label]; $text = preg_replace($re, ' ', $text, 1);
                    self::tr('When', $label, str_starts_with($label, 'Emergency') || $label === 'Open now first' ? '"' . trim($wm[0]) . '" asks for someone available now: open now and 24-hour places first, ones known to be closed left out. Not a thing in itself.' : 'A time word: places known to be closed then are left out.', trim($wm[0])); break; }
            }
            if (!array_filter($chips, fn ($c) => $c['type'] === 'when') && preg_match('/ (open )?(on )?(' . $days . ')s? /', $text, $m)) {
                $chips[] = ['type' => 'when', 'value' => 'day:' . self::DAYS[$m[3]], 'label' => 'Open ' . ucfirst($m[3])];
                self::tr('When', 'Open ' . ucfirst($m[3]), 'A day of the week.', trim($m[0]));
                $text = str_replace($m[0], ' ', $text);
            }
            // the other ways people say when (decisions/0061, the intent taxonomy's Time kind): "asap", "upcoming",
            // "this morning", and the time words taught in Admin → Meanings
            if (!array_filter($chips, fn ($c) => $c['type'] === 'when') && is_file(__DIR__ . '/SituationReasoner.php')) {
                require_once __DIR__ . '/SituationReasoner.php';
                SituationReasoner::load($pdo);
                if ($tw = SituationReasoner::when($text)) {
                    $chips[] = ['type' => 'when', 'value' => $tw['value'], 'label' => $tw['label']];
                    $text = preg_replace('/ ' . preg_quote($tw['word'], '/') . ' /', ' ', $text, 1);
                    self::tr('When', $tw['label'], '"' . $tw['word'] . '" is a time word (' . $tw['class'] . ')' . ($tw['class'] === 'upcoming' ? ': what\'s coming up. Events listings are coming; until then it\'s set aside, not searched for.' : ($tw['class'] === 'urgent' ? ': open now and 24-hour places first.' : '.')), $tw['word']);
                }
            }
        }
        // Who: who it's for ("for veterans", "veteran-owned"), or a public office ("the mayor"), never a person
        if (!in_array('who', $drop, true)) {
            $FW = self::forWhom($pdo);   // built in, plus "who it's for" words taught in the Workbench (decisions/0063)
            $plain = array_values(array_unique(array_merge(['kids', 'children', 'teens', 'seniors', 'veterans'], array_keys(self::taught($pdo, 'audience')))));
            if (preg_match('/ (veteran|native|tribally|tribal|woman|women|family|locally)[- ]owned /', $text, $m)) {
                $k = $m[1] . '-owned';
                $chips[] = ['type' => 'who', 'value' => $k, 'label' => ucfirst($m[1]) . '-owned'];
                self::tr('Who', ucfirst($m[1]) . '-owned', 'Who runs it, as the place says itself.', trim($m[0]));
                $text = str_replace($m[0], ' ', $text);
            } elseif (preg_match('/ for (the )?(' . implode('|', array_map(fn ($x) => preg_quote($x, '/'), array_keys($FW))) . ') /', $text, $m)) {
                $k = $FW[$m[2]] ?? $FW[trim(($m[1] ?? '') . $m[2])] ?? 'veterans';
                $chips[] = ['type' => 'who', 'value' => $k, 'label' => 'For ' . mb_strtolower(SearchIndex::AUDIENCE_LABEL[$k] ?? $k)];
                $text = str_replace($m[0], ' ', $text);
                self::tr('Who', 'For ' . mb_strtolower(SearchIndex::AUDIENCE_LABEL[$k] ?? $k), '"For" and who it\'s for: places that say they\'re for them come first.', trim($m[0]));
            } elseif (preg_match('/ (' . implode('|', array_map(fn ($x) => preg_quote($x, '/'), $plain)) . ') /', $text, $m) && str_word_count(trim(str_replace($m[0], ' ', $text))) >= 1
                      && !self::findCategory($pdo, trim($text))) {
                // Who said plainly, beside a What ("kids activities", "seniors meals"): who it's for. A category's own
                // name ("senior centers") stays the category.
                $k = $FW[$m[1]] ?? 'kids';
                $chips[] = ['type' => 'who', 'value' => $k, 'label' => 'For ' . mb_strtolower(SearchIndex::AUDIENCE_LABEL[$k] ?? $k)];
                self::tr('Who', 'For ' . mb_strtolower(SearchIndex::AUDIENCE_LABEL[$k] ?? $k), 'Who it\'s for, said beside the thing ("kids activities").', trim($m[1]));
                $text = str_replace($m[0], ' ', $text);
            } else {
                foreach (self::ROLES as $w => $role) {
                    [$label, $as] = $role;
                    if (preg_match('/ (the )?' . preg_quote($w, '/') . '( of| for| in)? /', $text, $m)) {
                        $chips[] = ['type' => 'who', 'value' => '', 'role' => true, 'prefer' => $role[2] ?? '', 'label' => $label . ' (public office)'];
                        self::tr('Who', $label . ' (public office)', 'A public role is searched as the office that holds it ("' . $as . '"), never a person.', trim($m[0]));
                        $text = str_replace($m[0], ' ' . $as . ' ', $text);
                        break;
                    }
                }
            }
        }
        // A word next to "help" names the help ("housekeeping help", "legal help"): a local service, not "Getting help"
        // (decisions/0060, the token window). "help" is dropped from the words; the service word stays.
        $helpWith = preg_match('/ ([a-z]{3,}) (help|helper|helpers|support|assistance) /', $text, $hm) && !in_array($hm[1], ['get', 'need', 'some', 'any', 'want', 'can', 'where', 'find', 'for', 'the', 'and', 'you', 'who', 'needs', 'getting', 'finding', 'free'], true);
        if ($helpWith) { $text = str_replace($hm[0], ' ' . $hm[1] . ' ', $text); self::tr('What', '"' . trim($hm[0]) . '" is a local service', 'A word next to "help" names the help (the token window): not "getting help".', trim($hm[0])); }
        // Why: what they want to do ("places to eat", "things to do"); it orders the results, never hides one
        if (!in_array('why', $drop, true) && !$helpWith) {
            foreach (self::WHY_PHRASES as $ph => $v) {
                if (str_contains($text, ' ' . $ph . ' ')) {
                    $chips[] = ['type' => 'why', 'value' => $v, 'label' => SearchIndex::WHY_LABEL[$v] ?? ucfirst($v)];
                    $text = str_replace(' ' . $ph . ' ', ' ', $text);
                    self::tr('Why', 'What they want to do: ' . (SearchIndex::WHY_LABEL[$v] ?? ucfirst($v)), '"' . $ph . '" is one of the Why phrases. It puts those places first; with nothing else to search, it chooses what\'s shown.', $ph);
                    $text = ' ' . implode(' ', array_filter(explode(' ', trim($text)), fn ($x) => $x !== '' && !in_array($x, self::FILLER, true))) . ' ';
                    break;
                }
            }
        }
        if (!in_array('kind', $drop, true) && !empty($ctx['kind']) && isset(self::KIND_LABEL[$ctx['kind']])) {
            $chips[] = ['type' => 'kind', 'value' => $ctx['kind'], 'label' => self::KIND_LABEL[$ctx['kind']], 'scope' => true];   // the panel's "Search in"
        } elseif (!in_array('kind', $drop, true)) {
            foreach (self::KINDS as $w => $k) {
                if (preg_match('/ ' . $w . ' /', $text)) { $chips[] = ['type' => 'kind', 'value' => $k, 'label' => self::KIND_LABEL[$k]]; self::tr('What', 'Only ' . mb_strtolower(self::KIND_LABEL[$k]), 'A kind of thing on Traversence named in the words.', $w); $text = preg_replace('/ ' . $w . ' /', ' ', $text, 1); break; }
            }
        }
        $text = trim(preg_replace('/\s+/', ' ', $text));

        // A place: the longest run of words at the end (or the start) naming a town, local name, area or region.
        $place = null;
        $exact = $text !== '' ? self::exactBusiness($pdo, $text) : null;   // "Greer Arizona" can be a place and a business: both are shown
        if ($place0) $place = $place0;   // read first, above
        elseif ($text !== '') {
            $t0 = $text;
            [$place, $text] = self::findPlace($pdo, $text);
            if ($place) self::tr('Where', (string) $place['label'], 'A town, area or region at the end or the start of the words (with "in/near/at/around", or one of ours); for this search only. Your place stays as you set it.', array_diff(explode(' ', $t0), explode(' ', $text)));
            // Where can be a landmark ("plumber near Lyman Lake"): after near/at/by/around, a lake, park, trail or business
            // we know by name. The search is around it (decisions/0061, the 5 W's parser).
            if (!$place && preg_match('/^(.+?) (near|at|by|around|close to) (.{3,60})$/u', $text, $lm) && ($lk = self::landmarkNamed($pdo, $lm[3]))) {
                $place = $lk; $text = trim($lm[1]);
                self::tr('Where', 'Near ' . $lk['label'], '"' . $lm[2] . '" and a place we know by name: about 15 miles around it.', $lm[2] . ' ' . $lm[3]);
            }
            if ($place) $text = trim(preg_replace('/(^| )(in|near|around|at|the)( |$)/', ' ', $text));
            // its × was tapped: the place words go and the search runs everywhere, not in the page's own place
            if ($place && in_array('place', $drop, true)) { $place = null; $drop[] = 'scope'; }
        }
        if ($place) $chips[] = ['type' => 'place'] + $place;
        elseif (!in_array('scope', $drop, true) && self::hasScope($ctx) && ($s = self::placeFromScope($pdo, $ctx))) { $chips[] = ['type' => 'place', 'scope' => true] + $s; self::tr('Where', (string) $s['label'], 'No place in the words: the place set on the site.'); }
        else self::tr('Where', 'Everywhere', 'No place in the words and none set on the site.');

        // A category: what's left is exactly a category's name (or an everyday word for it).
        // Also with the words that only describe it left out: "new pizza place" is Pizza.
        $core = trim(implode(' ', array_filter(explode(' ', $text), fn ($x) => $x !== '' && !in_array($x, self::DESCRIBE, true))));
        $cat = null; $catWords = $text;
        if (!in_array('category', $drop, true) && $text !== '') {
            $cat = self::findCategory($pdo, $text);
            if (!$cat && $core !== '' && $core !== $text && ($cat = self::findCategory($pdo, $core))) $catWords = $core;
        }
        if ($cat) {
            $cat += self::catKin($pdo, $cat);
            self::tr('What', 'The ' . SearchText::catLabel((string) $cat['label']) . ' category', ($catWords !== $text ? 'Once the describing words were left out ("new", "good", "store"), what\'s left is a category\'s name or an everyday word for it.' : 'What\'s left is a category\'s name, or an everyday word for it.')
                . (!empty($cat['kin']) ? ' Its kin count too (' . count($cat['kin']) . ' related categories).' : '') . (!empty($cat['shared']) ? ' Other kinds of business share its word, so a business is never read as it from its name alone.' : ' A business with the word in its name counts too.'), $catWords);
            $chips[] = ['type' => 'category', 'words' => $catWords, 'label' => SearchText::catLabel((string) $cat['label'])] + $cat;   // "pizza": the Pizza category, or "pizza" in a name (Dittys Pizza & Pie)
            $text = '';
        }
        $text = trim(preg_replace('/^(in|near|around|at|the)\s+|\s+(in|near|around|at)$/', '', $text));
        // only describing words left once something else was read ("where to buy new" is shopping): nothing to match.
        // "new" would find New England and every "-New" category
        if ($text !== '' && array_filter($chips, fn ($c) => $c['type'] !== 'place') && !array_diff(explode(' ', $text), self::DESCRIBE)) { self::tr('What', 'Only describing words left: nothing to match', '"New", "good", "best" describe a thing; on their own they aren\'t one.', $text); $text = ''; }
        // a kind of place or a thing to do ("springs", "boating"): outdoor places first, businesses with the word after
        // (decisions/0060). It orders, never hides.
        if (!array_filter($chips, fn ($c) => $c['type'] === 'why') && !in_array('why', $drop, true)) {
            foreach (self::TAGS as $tag => $group) if (mb_strtolower($tag) === $text) {
                $chips[] = ['type' => 'why', 'value' => 'explore', 'label' => $group === 'Natural features' ? 'Natural features' : 'Things to do'];
                self::tr('Why', 'To go and see: ' . $tag, 'A kind of place or thing to do: outdoor places first.', $text);
                break;
            }
        }
        return ['chips' => $chips, 'text' => $text, 'exact' => $exact, 'near_me' => $nearMe];
    }

    /** A named place with a point, for "near <it>": an outdoor place first, then a business; or null. */
    private static function landmarkNamed(PDO $pdo, string $name): ?array
    {
        if (!class_exists('SearchIndex') || !SearchIndex::ready($pdo)) return null;
        $name = trim(preg_replace('/^the /', '', $name));
        if (!str_contains($name, ' ') && isset(self::whatVocab($pdo)[self::root($name)])) return null;   // "near the park" is no park's name
        try {
            $st = $pdo->prepare("SELECT label, url, lat, lon FROM search_index WHERE kind IN ('outdoors', 'listing') AND lat IS NOT NULL AND private_location = 0
                                 AND label" . SearchText::CI . " LIKE ? ORDER BY kind = 'outdoors' DESC, LENGTH(label) LIMIT 1");
            $st->execute([SearchText::esc($name) . '%']);
            $r = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) { return null; }
        return $r ? ['label' => (string) $r['label'], 'sub' => 'Near', 'key' => '', 'url' => (string) $r['url'], 'landmark' => true,
                     'lat' => (float) $r['lat'], 'lon' => (float) $r['lon']] : null;
    }

    /**
     * The words the directory knows as things (the What, decisions/0061's 5 W's parser): every word of every category
     * name, the everyday words for them, kinds of place and things to do. Roots: "plumbers" is "plumber".
     */
    private static function whatVocab(PDO $pdo): array
    {
        static $v = null;
        if ($v !== null) return $v;
        $v = [];
        $add = function (string $s) use (&$v) { foreach (preg_split('/[^\p{L}0-9]+/u', mb_strtolower($s)) ?: [] as $w) if (mb_strlen($w) >= 3) $v[self::root($w)] = 1; };
        try { foreach ($pdo->query('SELECT name FROM categories WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN) as $n) $add(SearchText::catLabel((string) $n)); } catch (Throwable) {}
        foreach (SearchText::synonyms() as $k => $alts) { $add((string) $k); foreach ((array) $alts as $a) $add((string) $a); }
        foreach (array_keys(self::TAGS) as $t) $add($t);
        foreach (self::ACTIVITIES as $forms) foreach ($forms as $f) $add($f);
        foreach (self::ACT_WORDS as $ws) $add($ws);
        foreach (self::FEATURE_WORDS as $w) $add($w);
        foreach (array_keys(self::KINDS) as $w) $add($w);
        foreach (['and', 'the', 'for', 'other', 'retail', 'wholesale', 'new', 'used', 'whls', 'mfrs', 'except', 'misc', 'general'] as $w) unset($v[self::root($w)]);
        return $v;
    }

    private static function root(string $w): string
    {
        $w = mb_strtolower($w);
        if (mb_strlen($w) > 4 && str_ends_with($w, 'ies')) return mb_substr($w, 0, -3) . 'y';
        if (mb_strlen($w) > 4 && preg_match('/(x|ch|sh|ss)es$/', $w)) return mb_substr($w, 0, -2);   // taxes, churches, bushes, classes
        if (mb_strlen($w) > 3 && preg_match('/[^s]s$/', $w)) return mb_substr($w, 0, -1);
        return $w;
    }

    /**
     * A category's close kin and whether its word is shared (decisions/0061): "Parks" takes in State Parks and National
     * Parks/Preserves; but "park" is also in RV Parks, Mobile Home Parks and Parking, so a business named "…Park" counts
     * only if it's in Parks or its kin. "Pizza" is in no other category, so Dittys Pizza & Pie (a restaurant) still counts.
     * ['kin' => [ids], 'shared' => bool]
     */
    private static function catKin(PDO $pdo, array $cat): array
    {
        $label = SearchText::catLabel((string) $cat['label']);
        // the categories that share its plain name are one kind to a visitor ("Self Storage" is three of ours) (decisions/0068)
        $same = [];
        foreach (SearchText::plainNames($pdo) as $rn => $pn) if (mb_strtolower($pn) === mb_strtolower($label) && $rn !== mb_strtolower((string) $cat['label'])) $same[] = $rn;
        $kin = []; $shared = false;
        if ($same) try {
            $st = $pdo->prepare('SELECT id FROM categories WHERE is_active = 1 AND LOWER(name) IN (' . implode(',', array_fill(0, count($same), '?')) . ')');
            $st->execute($same);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) $kin[] = (int) $id;
        } catch (Throwable) {}
        $w = self::root(mb_strtolower((string) (preg_split('/[^\p{L}]+/u', $label, -1, PREG_SPLIT_NO_EMPTY)[0] ?? '')));
        if (mb_strlen($w) < 3) return ['kin' => $kin, 'shared' => false];
        try {
            $st = $pdo->prepare("SELECT id, name FROM categories WHERE is_active = 1 AND id <> ? AND (parent_id IS NULL OR parent_id <> ?) AND LOWER(name) LIKE ?");
            $st->execute([(int) $cat['id'], (int) $cat['id'], '%' . $w . '%']);
            $q = preg_quote(mb_strtolower($label), '/');
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $n = mb_strtolower((string) $c['name']);
                if (in_array((int) $c['id'], $kin, true)) continue;
                if (preg_match('/^(state|national|county|city|public|regional|community|tribal|local) ' . $q . '\b/u', $n)) $kin[] = (int) $c['id'];
                else $shared = true;
            }
        } catch (Throwable) {}
        return ['kin' => array_values(array_unique($kin)), 'shared' => $shared];
    }

    /** A category's name has these words together ("auto parts", "oil change"). */
    private static function catHas(PDO $pdo, string $phrase): bool
    {
        try {
            $st = $pdo->prepare('SELECT 1 FROM categories WHERE is_active = 1 AND name' . SearchText::CI . " LIKE ? ESCAPE '!' LIMIT 1");
            $st->execute(['%' . SearchText::esc($phrase) . '%']);
            return (bool) $st->fetchColumn();
        } catch (Throwable) { return false; }
    }

    /** Something here is named with these words, in this order ("st johns market"): a Who, searched by its name. */
    private static function nameHas(PDO $pdo, string $text): bool
    {
        try {
            $st = $pdo->prepare("SELECT 1 FROM search_index WHERE kind IN ('listing', 'outdoors', 'story', 'group', 'experience')
                                 AND CONCAT(' ', REPLACE(REPLACE(label, '-', ' '), '&', ' '), ' ')" . SearchText::CI . " LIKE ? LIMIT 1");
            $st->execute(['% ' . SearchText::esc($text) . ' %']);
            return (bool) $st->fetchColumn();
        } catch (Throwable) { return false; }
    }

    /** A listing whose name is exactly the query, as a result row; or null. */
    private static function exactBusiness(PDO $pdo, string $text): ?array
    {
        $in = "'" . implode("','", self::STATUSES) . "'";
        $st = $pdo->prepare("SELECT e.id, e.name, c.name AS category FROM entities e LEFT JOIN categories c ON c.id = e.category_id
                              WHERE e.entity_type = 'merchant' AND e.is_suppressed = 0 AND e.status IN ($in) AND e.name" . SearchText::CI . " = ? LIMIT 1");
        $st->execute([$text]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? ['id' => (int) $r['id'], 'label' => (string) $r['name'], 'sub' => (string) ($r['category'] ?? 'Listing'), 'url' => '/listing/view.php?id=' . (int) $r['id'], 'peek' => true] : null;
    }

    /** [place|null, remaining text]. Tries runs of 1–4 words at the end, then the start; a state name or code narrows it. */
    /** Words taught in the Workbench for one kind (decisions/0063): word => class. */
    private static function taught(PDO $pdo, string $kind): array
    {
        static $t = null;
        if ($t === null) {
            $t = [];
            try { foreach ($pdo->query("SELECT kind, class, word FROM search_meanings WHERE kind IN ('audience', 'notplace')")->fetchAll(PDO::FETCH_ASSOC) as $r) $t[$r['kind']][mb_strtolower((string) $r['word'])] = (string) $r['class']; } catch (Throwable) {}
        }
        return $t[$kind] ?? [];
    }

    /** Who it's for: the built-in words, and the ones taught (each to one of the audiences search knows). */
    private static function forWhom(PDO $pdo): array
    {
        $fw = self::FOR_WHOM;
        foreach (self::taught($pdo, 'audience') as $w => $a) if (isset(SearchIndex::AUDIENCE_LABEL[$a])) $fw[$w] = $a;
        return $fw;
    }

    private static function findPlace(PDO $pdo, string $text): array
    {
        // "washington state": the state, not a town or a listing with "state" in its name
        $stRe = '/^(.*?)\s*\b(' . implode('|', array_map(fn ($x) => preg_quote($x, '/'), array_keys(self::STATES))) . ')\s+state$/u';
        if (preg_match($stRe, trim($text), $sm)) {
            if (trim($sm[1], ' ,') === '' && ($sp = self::stateNamed($pdo, self::STATES[$sm[2]]))) return [$sp, ''];
            $text = trim($sm[1]) . ' ' . $sm[2];
        }
        $w = explode(' ', $text);
        $state = null;
        // a trailing state ("greer arizona", "show low, az")
        for ($k = min(2, count($w) - 1); $k >= 1; $k--) {
            $tail = trim(implode(' ', array_slice($w, -$k)), ' ,');
            $abbr = self::STATES[$tail] ?? (strlen($tail) === 2 && in_array(strtoupper($tail), self::STATES, true) ? strtoupper($tail) : null);
            if ($abbr && count($w) > $k) { $state = $abbr; $w = array_slice($w, 0, -$k); break; }
        }
        // a state's full name first, with the thing after it ("arizona food", "new mexico hiking": Jason, 2026-11-11, the
        // words as typed are the query); never a two-letter code here ("in", "or", "me" are words)
        if (!$state) for ($k = min(2, count($w) - 1); $k >= 1; $k--) {
            $head = trim(implode(' ', array_slice($w, 0, $k)), ' ,');
            if (isset(self::STATES[$head]) && count($w) > $k) { $state = self::STATES[$head]; $w = array_slice($w, $k); break; }
        }
        $n = count($w);
        // the whole search is the exact name of something we have ("laguna art museum", "fool hollow lake"): that's what they
        // mean, so a town or local name in some of its words isn't read (Laguna, NM; Fool Hollow). As in cityFirst; the
        // words as a whole may still be a place ("show low").
        $whole = trim((string) preg_replace('/\s+/', ' ', str_replace(',', ' ', $text)));
        $named = substr_count($whole, ' ') >= 1 && self::namedExactly($pdo, $whole);
        for ($len = min(4, $n); $len >= 1; $len--) {
            if ($named && $len < $n) break;
            foreach ([[$n - $len, 'end'], [0, 'start']] as [$from, $side]) {
                $phrase = trim(implode(' ', array_slice($w, $from, $len)), ' ,');
                if (mb_strlen($phrase) < 3) continue;
                // "st johns" is Saint Johns, "mt" Mount, "ft" Fort, as the ZIP list spells them
                $phrase = preg_replace(['/^st\.? /', '/^mt\.? /', '/^ft\.? /'], ['saint ', 'mount ', 'fort '], $phrase);
                if (isset(self::taught($pdo, 'notplace')[mb_strtolower($phrase)])) continue;   // taught as never a place (decisions/0063)
                // a lone feature word that is also a town somewhere ("Lake", MI; "Park", KS; "Springs") is part of a
                // name when other words come with it: "Lyman lake" is a lake, not Lake, Michigan. It's a place only
                // after "in", "near", "at" or "around".
                if ($len === 1 && $n > 1 && in_array(mb_strtolower($phrase), self::FEATURE_WORDS, true)) {
                    $before = mb_strtolower((string) ($w[$from - 1] ?? ''));
                    if (!in_array($before, ['in', 'near', 'at', 'around'], true)) continue;
                }
                // a town outside our areas counts only when it's the whole search, comes after "in/near/at/around", or has
                // its state: "four corn" isn't Corn, Oklahoma (decisions/0058 §25)
                $prev = mb_strtolower((string) ($w[$from - 1] ?? ''));
                // ...and a town outside our regions needs "in/near/at/around" or its state, even typed alone: "Lyman" is
                // Lyman Lake by St. Johns, not Lyman, Nebraska (decisions/0060)
                $strict = !in_array($prev, ['in', 'near', 'at', 'around'], true);
                if ($p = self::placeNamed($pdo, $phrase, $state, $strict)) {
                    $rest = $side === 'end' ? array_slice($w, 0, $n - $len) : array_slice($w, $len);
                    return [$p, trim(implode(' ', $rest), ' ,')];
                }
            }
        }
        // a state and no town in it named ("pizza in washington", "arizona"): the state is the place
        if ($state && ($sp = self::stateNamed($pdo, $state))) return [$sp, trim(implode(' ', $w), ' ,')];
        return [null, trim(implode(' ', $w) . '', ' ,')];
    }

    /**
     * The city in the words, as typed (CLAUDE.md, Places in search): the longest run of words at the end, at the start, or
     * after "in / near / at / around / to", with or without its state, that names a city in the locations data or one of
     * our places. A city counts whether or not we have anything there. Guards kept: a lone feature word ("lake", "point")
     * among other words, or a lone word that's a kind of thing ("golf", "price"), is a city only with its state or after
     * "in"; words taught as never a place stay so. Returns [place or null, the other words].
     */
    private static function cityFirst(PDO $pdo, string $text): array
    {
        // the whole search is the exact name of something we have ("four corners monument", "laguna art museum"): that's
        // what they mean, not a town in its first words (Jason, 2026-11-10). Two words or more, so "phoenix" stays the city.
        $whole = trim(preg_replace('/\s+/', ' ', str_replace(',', ' ', $text)));
        if (substr_count($whole, ' ') >= 1 && self::namedExactly($pdo, $whole)) return [null, $text];
        $w = array_values(array_filter(explode(' ', str_replace(',', ' , ', $text)), fn ($x) => $x !== ''));
        $state = null; $stateAt = null;
        $vocab = self::whatVocab($pdo); $not = self::taught($pdo, 'notplace');
        $cue = ['in', 'near', 'at', 'around', 'to'];
        $tryRun = function (int $from, int $len) use (&$w, $pdo, $vocab, $not, $cue, &$state): ?array {
            $words = array_slice($w, $from, $len);
            if (in_array(',', $words, true)) return null;
            $phrase = trim(implode(' ', $words));
            if (mb_strlen($phrase) < 3 || preg_match('/\d/', $phrase)) return null;
            $phrase = preg_replace(['/^st\.? /', '/^mt\.? /', '/^ft\.? /'], ['saint ', 'mount ', 'fort '], $phrase);
            if (isset($not[$phrase])) return null;
            $cued = in_array((string) ($w[$from - 1] ?? ''), $cue, true);
            $alone = count(array_filter($w, fn ($x) => $x !== ',')) === $len;
            if ($len === 1 && !$state && !$cued) {
                if (!$alone && in_array($phrase, self::FEATURE_WORDS, true)) return null;   // "lyman lake" is a lake
                if (isset($vocab[self::root($phrase)])) return null;   // "golf", "price": a thing, unless "in golf" or "golf, il"
            }
            if ($len === 1 && in_array($phrase, array_merge(self::FILLER, self::DESCRIBE), true)) return null;
            $p = self::placeNamed($pdo, $phrase, $state, false, true);
            if (!$p || !empty($p['is_state'])) return null;
            // a name used in several states, none of them ours ("springfield"): search asks Which Springfield? (whichPlace)
            if (!$state && !empty($p['outside'])) {
                require_once __DIR__ . '/crawler/NpiBulk.php';
                $ns = $pdo->prepare('SELECT COUNT(DISTINCT state) FROM zip_coordinates WHERE city = ?'); $ns->execute([(string) ($p['city'] ?? $phrase)]);
                if ((int) $ns->fetchColumn() > 1 && !in_array((string) ($p['state'] ?? ''), NpiBulk::STATES, true)) return null;
            }
            // one word beside others, no "in" and no state ("best pizza"): a town only if it's one of ours, in the home
            // states, or big enough that people mean it ("pizza phoenix")
            if ($len === 1 && !$alone && !$cued && !$state && !empty($p['outside'])) {
                require_once __DIR__ . '/crawler/NpiBulk.php';
                if (!in_array((string) ($p['state'] ?? ''), NpiBulk::STATES, true)) {
                    $pp = $pdo->prepare('SELECT COALESCE(SUM(population), 0) FROM zip_coordinates WHERE city = ? AND state = ?'); $pp->execute([(string) ($p['city'] ?? $phrase), (string) ($p['state'] ?? '')]);
                    if ((int) $pp->fetchColumn() < 20000) return null;
                }
            }
            return $p;
        };
        // a trailing state ("phoenix az", "dana point, california")
        $n = count($w);
        for ($k = min(2, $n - 1); $k >= 1; $k--) {
            $tail = trim(implode(' ', array_slice($w, -$k)), ' ,');
            $abbr = self::STATES[$tail] ?? (strlen($tail) === 2 && in_array(strtoupper($tail), self::STATES, true) ? strtoupper($tail) : null);
            if ($abbr && $n > $k) { $state = $abbr; $w = array_slice($w, 0, -$k); break; }
        }
        while ($w && end($w) === ',') array_pop($w);
        $n = count($w);
        $rest = function (int $from, int $len) use (&$w, $cue): string {
            $before = array_slice($w, 0, $from); $after = array_slice($w, $from + $len);
            if ($before && in_array(end($before), $cue, true)) array_pop($before);   // "in", "near": part of saying where
            return trim(preg_replace('/\s*,\s*/', ' ', implode(' ', array_merge($before, $after))));
        };
        for ($len = min(4, $n); $len >= 1; $len--) {
            $starts = [$n - $len, 0];
            // after "in" anywhere, unless the name runs on: "near lyman lake" is the lake, not Lyman; "in phoenix tonight" is Phoenix
            foreach ($w as $i => $x) if (in_array($x, $cue, true) && $i + 1 + $len <= $n && !in_array((string) ($w[$i + 1 + $len] ?? ''), self::FEATURE_WORDS, true)) $starts[] = $i + 1;
            foreach (array_unique($starts) as $from) {
                if ($from < 0) continue;
                if ($p = $tryRun($from, $len)) return [$p, $rest($from, $len)];
            }
        }
        return [null, $text];
    }

    /** A landmark, outdoor place or business on Traversence named exactly these words (not just starting with them). */
    private static function namedExactly(PDO $pdo, string $phrase): bool
    {
        static $seen = [];   // asked by cityFirst and findPlace for the same words
        $k = mb_strtolower($phrase);
        if (!isset($seen[$k])) $seen[$k] = self::namedExactlyAsk($pdo, $phrase);
        return $seen[$k];
    }

    private static function namedExactlyAsk(PDO $pdo, string $phrase): bool
    {
        try {
            $st = $pdo->prepare("SELECT 1 FROM search_index WHERE kind IN ('outdoors', 'listing') AND label" . SearchText::CI . " = ? LIMIT 1");
            $st->execute([$phrase]);
            if ($st->fetchColumn()) return true;
            require_once __DIR__ . '/Landmarks.php';
            if (!Landmarks::ready($pdo)) return false;
            $st = $pdo->prepare('SELECT 1 FROM landmarks WHERE name' . SearchText::CI . ' = ? AND ' . Landmarks::visibleSql() . ' LIMIT 1');
            $st->execute([$phrase]);
            return (bool) $st->fetchColumn();
        } catch (Throwable) { return false; }
    }

    private static function clusterRow(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare('SELECT id, name, slug, primary_zip, associated_zips FROM micro_clusters WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private static function placeOut(PDO $pdo, string $level, int $id, string $label, string $slug, array $zips, string $sub): array
    {
        $key = $level . ':' . $id;
        try { [, $url] = Links::resolve($pdo, 'place', $key); } catch (InvalidArgumentException) { $url = ''; }
        return ['label' => $label, 'sub' => $sub, 'key' => $key, 'level' => $level, 'slug' => $slug, 'url' => $url, 'zips' => $zips];
    }

    /** A place by exact name: a town (via its ZIPs to its cluster), a cluster or its accepted local name, an area, a region. */
    /** An outdoor place, listing or landmark named these words, or starting with them as whole words ("Four Corners Monument"). */
    private static function namesSomething(PDO $pdo, string $phrase): bool
    {
        try {
            $st = $pdo->prepare("SELECT 1 FROM search_index WHERE kind IN ('outdoors', 'listing') AND (label" . SearchText::CI . " = ? OR label" . SearchText::CI . " LIKE ?) LIMIT 1");
            $st->execute([$phrase, str_replace(['%', '_'], ['\\%', '\\_'], $phrase) . ' %']);
            if ($st->fetchColumn()) return true;
            // a landmark kept but not shown (on a nation's land, until the nation tools) still isn't a far-off town
            $st = $pdo->prepare("SELECT 1 FROM landmarks WHERE status <> 'hidden' AND (name" . SearchText::CI . " = ? OR name" . SearchText::CI . " LIKE ?) LIMIT 1");
            $st->execute([$phrase, str_replace(['%', '_'], ['\\%', '\\_'], $phrase) . ' %']);
            return (bool) $st->fetchColumn();
        } catch (Throwable) { return false; }
    }

    private static function placeNamed(PDO $pdo, string $phrase, ?string $state, bool $strict = false, bool $city = false): ?array
    {
        $ci = SearchText::CI;
        // a cluster by its public name, or an accepted local name
        static $clusters = null;   // name => row, loaded once per request
        if ($clusters === null) {
            $clusters = [];
            foreach ($pdo->query('SELECT id, name, slug, primary_zip, associated_zips FROM micro_clusters')->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $pub = mb_strtolower(PlaceScope::publicName((string) $c['name']));
                $clusters[$pub] ??= $c;
                $clusters[trim(preg_replace('/\s*\(.*\)$/', '', $pub))] ??= $c;
            }
        }
        // a county ("navajo county", "king county, wa"): every ZIP in it, its middle, and the state (decisions/0066)
        if (preg_match('/^(.{2,40}?) county$/u', $phrase, $cm) && ($co = self::countyNamed($pdo, trim($cm[1]), $state))) return $co;
        if (isset($clusters[$phrase])) {
            $c = $clusters[$phrase];
            // an area named for the town it's in ("Phoenix", "Glendale"): the town is the place, with all its own ZIPs and its
            // state ("Phoenix, AZ"), as any town named is. An area's few ZIPs left "pizza in phoenix" with only OpenStreetMap,
            // and "Glendale" read without its state (2026-11-11). Its area stays its own (url, slug).
            $elsewhere = false;
            if ($tw = self::townOfArea($pdo, $c, $phrase, $state, $elsewhere)) return $tw;
            // another state's town of that name, typed with its state ("phoenix, or"): that town, below
            if (!$elsewhere) return self::placeOut($pdo, 'cluster', (int) $c['id'], PlaceScope::publicName((string) $c['name']), (string) $c['slug'], self::zipsOf($c), 'Town');
        }
        try {
            $st = $pdo->prepare("SELECT cluster_id, name FROM cluster_subgroups WHERE status = 'accepted' AND name$ci = ? LIMIT 1");
            $st->execute([$phrase]);
            if ($sg = $st->fetch(PDO::FETCH_ASSOC)) {
                $c = self::clusterRow($pdo, (int) $sg['cluster_id']);
                if ($c) return self::placeOut($pdo, 'cluster', (int) $c['id'], (string) $sg['name'], (string) $c['slug'], self::zipsOf($c), 'Local name');
            }
        } catch (PDOException) {}
        // a town in the ZIP list, then the cluster it belongs to
        $st = $pdo->prepare("SELECT zip, TRIM(state) AS state, TRIM(city) AS city, COALESCE(population, 0) AS pop, latitude, longitude FROM zip_coordinates WHERE city = ?" . ($state ? ' AND state = ?' : '') . ' LIMIT 600');
        $st->execute($state ? [$phrase, $state] : [$phrase]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        // the same name typed without its punctuation or spacing ("coeur d alene" is Coeur d'Alene, "winston salem")
        if (!$rows && ($sq = self::squash($phrase)) !== '' && mb_strlen($sq) >= 4) {
            $st = $pdo->prepare('SELECT zip, TRIM(state) AS state, TRIM(city) AS city, COALESCE(population, 0) AS pop FROM zip_coordinates WHERE ' . self::squashSql('city') . ' = ?' . ($state ? ' AND TRIM(state) = ?' : '') . ' LIMIT 600');
            $st->execute($state ? [$sq, $state] : [$sq]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        }
        // the pilot states' town first ("Four Corners" is also a town in Wyoming and Vermont)
        require_once __DIR__ . '/crawler/NpiBulk.php';
        $home = fn (array $r) => in_array($r['state'], NpiBulk::STATES, true) ? 1 : 0;
        $people = []; foreach ($rows as $r) $people[$r['state']] = ($people[$r['state']] ?? 0) + (int) $r['pop'];   // then the most people ("springfield": Illinois's)
        // the one near the person first, within 150 mi (Glendale, AZ to someone in Phoenix, not Glendale, CA)
        $close = []; if (self::$near) foreach ($rows as $r) if (isset($r['latitude']) && $r['latitude'] !== null && self::mi(self::$near[0], self::$near[1], (float) $r['latitude'], (float) $r['longitude']) <= 150) $close[$r['state']] = 1;
        usort($rows, fn ($a, $b) => [isset($close[$b['state']]) ? 1 : 0, $home($b), $people[$b['state']]] <=> [isset($close[$a['state']]) ? 1 : 0, $home($a), $people[$a['state']]]);
        if ($rows) $rows = array_values(array_filter($rows, fn ($r) => $r['state'] === $rows[0]['state']));   // one state's ZIPs, never Phoenix, AZ with Phoenix, OR
        if ($rows) {
            $zips = array_values(array_unique(array_map(fn ($r) => trim((string) $r['zip']), $rows)));
            $town = ucwords((string) $rows[0]['city']) . ', ' . $rows[0]['state'];
            if ($c = self::clusterForZips($pdo, $zips)) {
                $area = PlaceScope::publicName((string) $c['name']);
                return ['label' => $town, 'sub' => preg_match('/^ZIP \d/', $area) || mb_strtolower($area) === mb_strtolower((string) $rows[0]['city']) ? 'Town' : 'Town in ' . $area] + array_diff_key(self::placeOut($pdo, 'cluster', (int) $c['id'], $town, (string) $c['slug'], $zips, ''), ['label' => 1, 'sub' => 1]) + ['town' => true];
            }
            // a town outside our areas, named without its state, is only the place when nothing on Traversence has that
            // name: "Four Corners" is the monument, not Four Corners, Wyoming (decisions/0058 §25)
            if (!$city && !$state && ($strict || self::namesSomething($pdo, $phrase))) return null;   // a city read first (cityFirst) is always the place
            return ['label' => $town, 'sub' => 'Town', 'key' => '', 'level' => '', 'slug' => '', 'url' => '', 'zips' => $zips, 'town' => true, 'city' => (string) $rows[0]['city'], 'state' => (string) $rows[0]['state'], 'outside' => true];   // outside our areas (decisions/0060, 2026-10-04 notes)
        }
        foreach (['geo_hub' => 'geo_hubs', 'hub' => 'hubs'] as $level => $table) {
            try {
                $st = $pdo->prepare("SELECT id, name, slug FROM $table WHERE name$ci = ? LIMIT 1");
                $st->execute([$phrase]);
                if ($r = $st->fetch(PDO::FETCH_ASSOC)) return self::placeOut($pdo, $level, (int) $r['id'], (string) $r['name'], (string) $r['slug'], [], $level === 'hub' ? 'Region' : 'Area');
            } catch (PDOException) {}
        }
        return null;
    }

    /**
     * The town an area of ours is named for, when the area's own main ZIP is in that town ("Phoenix" is Phoenix, AZ): a
     * town place in the area, with the town's ZIPs (the area's main ZIP first, so its middle stays where it was, then the
     * town's by how many people, then the rest of the area's) and its state. Null when the area's name isn't its town's;
     * $elsewhere says a state was typed that isn't the area's ("phoenix, or" is Phoenix, Oregon).
     */
    private static function townOfArea(PDO $pdo, array $c, string $phrase, ?string $state, bool &$elsewhere = false): ?array
    {
        try {
            $st = $pdo->prepare('SELECT TRIM(city) AS city, TRIM(state) AS state FROM zip_coordinates WHERE zip = ? LIMIT 1');
            $st->execute([trim((string) $c['primary_zip'])]);
            $z = $st->fetch(PDO::FETCH_ASSOC);
            $elsewhere = $z && $state && $state !== (string) $z['state'];   // the area is in another state than the one typed
            if (!$z || $elsewhere || self::squash((string) $z['city']) !== self::squash($phrase)) return null;
            $st = $pdo->prepare('SELECT TRIM(zip) FROM zip_coordinates WHERE city = ? AND state = ? ORDER BY COALESCE(population, 0) DESC LIMIT 600');
            $st->execute([(string) $z['city'], (string) $z['state']]);
            $zips = array_values(array_unique(array_filter(array_merge([trim((string) $c['primary_zip'])], array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN)), self::zipsOf($c)))));
        } catch (Throwable) { return null; }
        $town = ucwords(mb_strtolower((string) $z['city'])) . ', ' . $z['state'];
        return ['label' => $town, 'sub' => 'Town'] + array_diff_key(self::placeOut($pdo, 'cluster', (int) $c['id'], $town, (string) $c['slug'], $zips, ''), ['label' => 1, 'sub' => 1])
             + ['city' => (string) $z['city'], 'state' => (string) $z['state']];   // not 'town': the area is the town, so all of it still counts
    }

    /**
     * A county by name, in the state given or, with none, the pilot states first, then wherever the name is used most:
     * ['label' => 'Navajo County, AZ', 'sub' => 'County', 'county', 'state', 'zips', 'lat', 'lon', 'outside'].
     */
    private static function countyNamed(PDO $pdo, string $name, ?string $state): ?array
    {
        $ci = SearchText::CI;
        $bare = preg_replace('/\s+county$/i', '', trim($name));
        try {
            $st = $pdo->prepare("SELECT TRIM(state) AS state, COUNT(*) AS n FROM zip_coordinates WHERE (TRIM(county)$ci = ? OR TRIM(county)$ci = ?)" . ($state ? ' AND TRIM(state) = ?' : '') . ' GROUP BY TRIM(state)');
            $st->execute($state ? [$bare, $bare . ' County', $state] : [$bare, $bare . ' County']);
            $states = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) { return null; }
        if (!$states) return null;
        require_once __DIR__ . '/crawler/NpiBulk.php';
        usort($states, fn ($a, $b) => [in_array($b['state'], NpiBulk::STATES, true), (int) $b['n']] <=> [in_array($a['state'], NpiBulk::STATES, true), (int) $a['n']]);
        $stt = (string) $states[0]['state'];
        $st = $pdo->prepare("SELECT TRIM(zip) AS zip, TRIM(county) AS county, latitude, longitude FROM zip_coordinates WHERE (TRIM(county)$ci = ? OR TRIM(county)$ci = ?) AND TRIM(state) = ?");
        $st->execute([$bare, $bare . ' County', $stt]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return null;
        $zips = array_values(array_unique(array_column($rows, 'zip')));
        $pts = array_filter($rows, fn ($r) => $r['latitude'] !== null);
        $lat = $pts ? array_sum(array_map(fn ($r) => (float) $r['latitude'], $pts)) / count($pts) : null;
        $lon = $pts ? array_sum(array_map(fn ($r) => (float) $r['longitude'], $pts)) / count($pts) : null;
        $county = (string) $rows[0]['county'];
        $label = (preg_match('/county$/i', $county) ? ucwords(mb_strtolower($county)) : ucwords(mb_strtolower($county)) . ' County') . ', ' . $stt;
        $p = ['label' => $label, 'sub' => 'County', 'key' => '', 'level' => '', 'slug' => '', 'url' => '', 'zips' => $zips, 'county' => $county, 'state' => $stt,
              'outside' => !self::clusterForZips($pdo, array_slice($zips, 0, 200))];
        if ($lat !== null) $p += ['lat' => round($lat, 4), 'lon' => round($lon, 4)];
        return $p;
    }

    /**
     * A state as a place: every ZIP in it, its middle, and whether we have any areas there:
     * ['label' => 'Washington', 'sub' => 'State', 'state' => 'WA', 'is_state' => true, 'lat', 'lon', 'outside'].
     */
    private static function stateNamed(PDO $pdo, string $abbr): ?array
    {
        $name = array_search($abbr, self::STATES, true);
        if ($name === false) return null;
        try {
            $st = $pdo->prepare('SELECT AVG(latitude) AS lat, AVG(longitude) AS lon FROM zip_coordinates WHERE state = ?');
            $st->execute([$abbr]);
            $c = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $st = $pdo->prepare('SELECT 1 FROM micro_clusters mc JOIN zip_coordinates zc ON TRIM(zc.zip) = mc.primary_zip WHERE TRIM(zc.state) = ? LIMIT 1');
            $st->execute([$abbr]);
            $ours = (bool) $st->fetchColumn();
        } catch (Throwable) { $c = []; $ours = false; }
        $p = ['label' => ucwords($name), 'sub' => 'State', 'key' => '', 'level' => '', 'slug' => '', 'url' => '', 'zips' => [], 'state' => $abbr, 'is_state' => true, 'outside' => !$ours];
        if (($c['lat'] ?? null) !== null) $p += ['lat' => round((float) $c['lat'], 4), 'lon' => round((float) $c['lon'], 4)];
        return $p;
    }

    /** A name for comparing whatever its punctuation and spacing: "Coeur d'Alene" and "coeur d alene" are "coeurdalene". */
    private static function squash(string $s): string { return (string) preg_replace('/[^a-z0-9]+/', '', mb_strtolower($s)); }

    /** The same, in SQL, for a column. */
    private static function squashSql(string $col): string
    {
        // the town's name squashed, kept in zip_coordinates.city_key with an index (2026-11-22_zip_city_index.sql), so
        // a town is found without reading all 41,000 ZIPs
        if ($col === 'city' && self::hasCityKey()) return 'city_key';
        $x = "LOWER(TRIM($col))";
        foreach ([' ', "''", '.', '-', '’', ','] as $c) $x = "REPLACE($x, '$c', '')";   // '' is a quote inside SQL
        return $x;
    }

    private static ?bool $cityKey = null;

    private static function hasCityKey(): bool
    {
        if (self::$cityKey === null) { try { Database::connection()->query('SELECT city_key FROM zip_coordinates LIMIT 0'); self::$cityKey = true; } catch (Throwable) { self::$cityKey = false; } }
        return self::$cityKey;
    }

    private static function zipsOf(array $c): array
    {
        return array_values(array_unique(array_filter(array_merge([(string) $c['primary_zip']], (array) (json_decode((string) ($c['associated_zips'] ?? ''), true) ?: [])))));
    }

    private static function clusterForZips(PDO $pdo, array $zips): ?array
    {
        $zips = array_slice($zips, 0, 10);
        $in = implode(',', array_fill(0, count($zips), '?'));
        $st = $pdo->prepare("SELECT id, name, slug, primary_zip, associated_zips FROM micro_clusters WHERE primary_zip IN ($in) LIMIT 1");
        $st->execute($zips);
        if ($c = $st->fetch(PDO::FETCH_ASSOC)) return $c;
        $or = implode(' OR ', array_fill(0, count($zips), 'associated_zips LIKE ?'));
        $st = $pdo->prepare("SELECT id, name, slug, primary_zip, associated_zips FROM micro_clusters WHERE $or LIMIT 1");
        $st->execute(array_map(fn ($z) => '%"' . $z . '"%', $zips));
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** The visitor's current place (a scope) as a place chip, or null. */
    public static function scopePlace(PDO $pdo, array $ctx): ?array { return self::hasScope($ctx) ? self::placeFromScope($pdo, $ctx) : null; }

    /** A place is set on the site: one of our areas (a slug), or any town, ZIP or point someone chose ("Seattle, WA"). */
    private static function hasScope(array $ctx): bool
    {
        return !empty($ctx['slug']) || !empty($ctx['city']) || !empty($ctx['zip']) || (isset($ctx['lat'], $ctx['lon']) && $ctx['lat'] !== null);
    }

    /**
     * A place set by state, city or ZIP, or found by Auto-detect, with no area of ours named. When its ZIPs (or, for a
     * bare point, the nearest town's) are in one of our areas, it's that area's town, like a town chosen there. Outside
     * our areas ("Seattle, WA") it's still the person's place. Its ZIPs and middle come from the ZIP list when we have
     * them, else from the point they gave. Search answers there with what's near it, or says nothing is listed there
     * yet; it never asks where again.
     */
    private static function loosePlace(PDO $pdo, array $ctx): ?array
    {
        $city = trim((string) ($ctx['city'] ?? '')); $state = strtoupper(trim((string) ($ctx['state'] ?? '')));
        if (!preg_match('/^[A-Z]{2}$/', $state)) $state = '';
        $zip = preg_match('/^\d{5}$/', trim((string) ($ctx['zip'] ?? ''))) ? trim((string) $ctx['zip']) : '';
        $lat = isset($ctx['lat']) && is_numeric($ctx['lat']) ? (float) $ctx['lat'] : null;
        $lon = isset($ctx['lon']) && is_numeric($ctx['lon']) ? (float) $ctx['lon'] : null;
        $zips = [];
        try {
            if ($zip !== '') {
                $st = $pdo->prepare('SELECT TRIM(city) AS city, TRIM(state) AS state, latitude, longitude FROM zip_coordinates WHERE zip = ? LIMIT 1');
                $st->execute([$zip]);
                if ($z = $st->fetch(PDO::FETCH_ASSOC)) { $city = $city ?: ucwords(mb_strtolower((string) $z['city'])); $state = $state ?: (string) $z['state']; $lat ??= (float) $z['latitude']; $lon ??= (float) $z['longitude']; }
                $zips = [$zip];
            } elseif ($city !== '') {
                $st = $pdo->prepare('SELECT zip, latitude, longitude FROM zip_coordinates WHERE city = ?' . ($state !== '' ? ' AND state = ?' : '') . ' LIMIT 60');
                $st->execute($state !== '' ? [$city, $state] : [$city]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $z) { $zips[] = trim((string) $z['zip']); if ($lat === null) { $lat = (float) $z['latitude']; $lon = (float) $z['longitude']; } }
            }
        } catch (Throwable) {}
        if ($city === '' && $zip === '' && $lat === null) return null;
        $label = $city !== '' ? ucwords(mb_strtolower($city)) . ($state !== '' ? ', ' . $state : '') : ($zip !== '' ? 'ZIP ' . $zip : 'Near you');
        // Inside one of our areas, the device's place is that area's town, never "outside" (the launch plan's C.1;
        // decisions/0060): the ZIPs it named, or for a bare point the nearest town's ZIP, read against our clusters, the
        // same check a typed town or county gets (placeNamed, countyNamed). The shape is a town chosen in its area's.
        $own = $zips;
        if (!$own && $lat !== null && $lon !== null) {
            try { require_once __DIR__ . '/Landmarks.php'; if ($nz = Landmarks::nearestZip($pdo, $lat, $lon)) $own = [$nz[0]]; } catch (Throwable) {}
        }
        $c = null;
        try { if ($own) $c = self::clusterForZips($pdo, $own); } catch (Throwable) {}
        if ($c) {
            $p = ['label' => $label, 'sub' => 'Your place'] + array_diff_key(self::placeOut($pdo, 'cluster', (int) $c['id'], $label, (string) $c['slug'], $own, 'Your place'), ['label' => 1, 'sub' => 1])
               + ['town' => $city !== '', 'area' => PlaceScope::publicName((string) $c['name']), 'city' => $city, 'state' => $state, 'outside' => false];
            if ($lat !== null && $lon !== null) $p += ['lat' => $lat, 'lon' => $lon];
            return $p;
        }
        $p = ['label' => $label, 'sub' => 'Your place', 'key' => '', 'level' => '', 'slug' => '', 'url' => '', 'zips' => $zips, 'city' => $city, 'state' => $state, 'outside' => true];
        if ($lat !== null && $lon !== null) $p += ['lat' => $lat, 'lon' => $lon];
        return $p;
    }

    private static function placeFromScope(PDO $pdo, array $ctx): ?array
    {
        $level = (string) ($ctx['level'] ?? '');
        $param = ['cluster' => 'cluster', 'geo_hub' => 'geo', 'hub' => 'hub'][$level] ?? null;
        if (!$param || empty($ctx['slug'])) return self::loosePlace($pdo, $ctx);
        $s = PlaceScope::resolve($pdo, [$param => (string) $ctx['slug']]);
        if (!$s || !$s['found']) return self::loosePlace($pdo, $ctx);
        $zips = [];
        if ($level === 'cluster' && ($c = self::clusterRow($pdo, (int) $s['id']))) $zips = self::zipsOf($c);
        // a town chosen in its town area ("Saint Johns, AZ" in The Greater St. Johns Area) is the place itself: its own
        // ZIPs first; the rest of the area is "beyond", nearest first (decisions/0060)
        if ($level === 'cluster' && $zips && preg_match('/^(.{2,60}),\s*([A-Z]{2})$/u', (string) ($ctx['town'] ?? ''), $tm)) {
            $st = $pdo->prepare('SELECT zip FROM zip_coordinates WHERE city = ? AND state = ?');
            $st->execute([trim($tm[1]), $tm[2]]);
            $tz = array_values(array_intersect(array_map(fn ($z) => trim((string) $z), $st->fetchAll(PDO::FETCH_COLUMN)), $zips));
            if ($tz) return ['label' => trim($tm[1]) . ', ' . $tm[2], 'sub' => 'Your place', 'key' => '', 'level' => $level, 'slug' => (string) $ctx['slug'],
                             'url' => self::placeOut($pdo, $level, (int) $s['id'], '', (string) $ctx['slug'], [], '')['url'], 'zips' => $tz, 'town' => true, 'area' => PlaceScope::publicName((string) $s['name'])];
        }
        return self::placeOut($pdo, $level, (int) $s['id'], PlaceScope::publicName((string) $s['name']), (string) $ctx['slug'], $zips, 'Your place');
    }

    private static function findCategory(PDO $pdo, string $text): ?array
    {
        $cands = [$text, $text . 's', preg_replace('/s$/', '', $text)];
        $toks = SearchText::tokens($text);
        // an everyday word for one category ("pub" is Bars); a broad word ("sports": stadiums, gyms, golf…) stays words
        if (count($toks) === 1 && count($alts = SearchText::alternatives($toks[0])) <= 3) foreach ($alts as $a) $cands[] = $a;
        $cands = array_values(array_unique(array_filter($cands)));
        $in = implode(',', array_fill(0, count($cands), '?'));
        $st = $pdo->prepare('SELECT id, name, slug, parent_id FROM categories WHERE is_active = 1 AND LOWER(name) IN (' . $in . ') ORDER BY parent_id IS NULL, id LIMIT 1');
        $st->execute($cands);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            // its plain name, as visitors see it ("gyms & fitness centers", "self storage"): a name several categories share
            // means the one with the most listings (decisions/0068)
            $raw = [];
            foreach (SearchText::plainNames($pdo) as $rn => $pn) if (in_array(mb_strtolower($pn), $cands, true)) $raw[] = $rn;
            if ($raw) {
                $in2 = implode(',', array_fill(0, count($raw), '?'));
                $st = $pdo->prepare("SELECT c.id, c.name, c.slug, c.parent_id, (SELECT COUNT(*) FROM search_index si WHERE si.kind = 'listing' AND si.category_id = c.id) AS n FROM categories c WHERE c.is_active = 1 AND LOWER(c.name) IN ($in2) ORDER BY n DESC, c.id LIMIT 1");
                $st->execute($raw);
                $r = $st->fetch(PDO::FETCH_ASSOC);
            }
        }
        if (!$r) {
            // the same words by their roots (therapy / therapists, pharmacy / pharmacies), same number of words
            $root = fn (string $w) => strlen($w) > 5 ? preg_replace('/(ists?|ies|y|s)$/', '', $w) : preg_replace('/s$/', '', $w);
            $mine = array_map($root, explode(' ', $text));
            $st = $pdo->prepare("SELECT id, name, slug, parent_id FROM categories WHERE is_active = 1 AND LOWER(name) LIKE ? ESCAPE '!' ORDER BY parent_id IS NULL, id LIMIT 5");
            $st->execute([SearchText::esc(implode(' ', $mine)) . '%']);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
                if (array_map($root, explode(' ', mb_strtolower((string) $c['name']))) === $mine) { $r = $c; break; }
            }
        }
        if (!$r && mb_strlen($text) >= 4) {
            // the name as shown, without its trade codes ("automobile parts & supplies" is "…Supplies-Retail-New")
            $st = $pdo->prepare("SELECT id, name, slug, parent_id FROM categories WHERE is_active = 1 AND (LOWER(name) LIKE ? ESCAPE '!' OR LOWER(name) LIKE ? ESCAPE '!') ORDER BY parent_id IS NULL, id LIMIT 8");
            $st->execute([SearchText::esc($text) . '-%', SearchText::esc($text) . ' (%']);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) if (mb_strtolower(SearchText::catLabel((string) $c['name'])) === $text) { $r = $c; break; }
        }
        return $r ? ['label' => (string) $r['name'], 'slug' => (string) $r['slug'], 'id' => (int) $r['id']] : null;
    }

    // ---- results ---------------------------------------------------------------------------------------

    /** OpenStreetMap's kinds that are places to go, not errands: a drive is near for them. */
    private const OSM_GOING = ['trail', 'camping', 'park', 'viewpoint', 'todo', 'family', 'dog'];

    /**
     * How far OpenStreetMap is asked, and ours are kept beside it, for "near me": 25 mi, or the Distance the person set when
     * it's nearer and the kind is an errand (a café, a clinic); places to go keep the drive.
     */
    private static function osmReach(string $kind, array $ctx, bool $nearMe): int
    {
        $r = (string) ($ctx['reach'] ?? '');
        return $nearMe && ctype_digit($r) && (int) $r > 0 && (int) $r < 25 && !in_array($kind, self::OSM_GOING, true) ? (int) $r : 25;
    }

    /** "near me" needs at least this many places (ours and OpenStreetMap's) within 2 mi, else it offers 5; within 5, else it's lacking */
    public const NEAR_ENOUGH = 3;
    /** how near is near for "near me": errands (Jason's 2 mi, then 5) and the outdoors (a drive) */
    public const NEAR_MILES = 5;
    public const NEAR_MILES_OUTDOORS = 30;

    /** Where the person is (their point, else the place set), for telling apart towns of one name: Glendale, AZ to someone in Phoenix. */
    private static ?array $near = null;
    private static function mi(float $a, float $b, float $c, float $d): float
    {
        $r = M_PI / 180; $x = sin(($c - $a) * $r / 2) ** 2 + cos($a * $r) * cos($c * $r) * sin(($d - $b) * $r / 2) ** 2;
        return 2 * 3958.8 * asin(min(1, sqrt($x)));
    }
    private static function nearFrom(PDO $pdo, array $ctx): ?array
    {
        if (is_numeric($ctx['lat'] ?? null) && is_numeric($ctx['lon'] ?? null)) return [(float) $ctx['lat'], (float) $ctx['lon']];
        try { if (self::hasScope($ctx) && ($s = self::placeFromScope($pdo, $ctx)) && ($c = self::centerOf($pdo, $s))) return [(float) $c['lat'], (float) $c['lon']]; } catch (Throwable) {}
        return null;
    }

    public static function search(PDO $pdo, string $q, array $ctx = [], array $drop = [], ?int $viewer = null, int $radius = 0): array
    {
        // where the person is, for telling towns of one name apart; the words are searched as typed: a slip is offered as
        // "Did you mean", never changed (decisions/0063, 0064; Jason, 2026-11-10: the auto-correct tried that day is rolled back)
        self::$near = self::nearFrom($pdo, $ctx);
        return self::searchAs($pdo, $q, $ctx, $drop, $viewer, $radius);
    }

    private static function searchAs(PDO $pdo, string $q, array $ctx = [], array $drop = [], ?int $viewer = null, int $radius = 0): array
    {
        $u = self::understand($pdo, $q, $ctx, $drop);
        // a word whose other meaning the words around it give ("copper tubing": pipe) isn't matched in descriptions, where it
        // means the activity ("snow tubing" at a ski resort) (Jason, 2026-10-15)
        $noAb = ['no_about' => array_values(array_filter(array_keys(self::ACT_NOT), fn ($w) => self::actNot($w, $q)))];
        $chips = $u['chips'];
        $text = $u['text'];
        $chip = fn (string $t) => array_values(array_filter($chips, fn ($c) => $c['type'] === $t))[0] ?? null;
        $place = $chip('place');
        // "Where should I go" (decisions/0053 §4b): a distance around the place instead of its boundary.
        $area = null;
        // the Distance slider (decisions/0060): miles around the place instead of its boundary
        $reach = $place ? (string) ($ctx['reach'] ?? 'all') : 'all';   // no place set: nothing to measure from
        // "near me" (Jason, 2026-11-10): the nearest, with no distance cap ("Can't it find the nearest locations without a
        // distance cap"), each with its miles; NEAR_MILES is the check, not a fence: fewer than NEAR_ENOUGH within it says our
        // results are lacking (2 mi then 5 for errands, as first asked; 30 for the outdoors). No place: the panel asks first.
        $nearMe = !empty($u['near_me']) || self::nearMe($q)['near'];
        if ($place && $nearMe && empty($place['landmark'])) { $reach = '250'; $radius = 250; }
        if ($radius <= 0 && ctype_digit($reach)) $radius = (int) $reach;
        if ($radius <= 0 && !empty($place['landmark'])) $radius = 15;   // "near Lyman Lake": around it
        if ($radius > 0 && $place && ($c = self::centerOf($pdo, $place))) {
            $area = ['lat' => $c['lat'], 'lon' => $c['lon'], 'radius' => max(1, min(250, $radius))];
            foreach ($chips as $i => $c) if ($c['type'] === 'place') $chips[$i]['radius'] = $area ? $area['radius'] : 0;
        }
        // an outing (2026-11-10): the kinds of places that suit who's coming, kept local: 25 mi around the place unless
        // a distance was set ("Phoenix + 250 mi" sent the kids to a trailhead 47 mi off)
        $outC = $chip('outing');
        $outing = $outC && empty($outC['soft']) ? ['for' => (string) $outC['value'], 'age' => (string) ($outC['age'] ?? ''), 'setting' => (string) ($outC['setting'] ?? '')] : [];
        if ($outing && $place && (!$area || $area['radius'] > 25) && !$nearMe && ($c = self::centerOf($pdo, $place))) {
            $area = ['lat' => $c['lat'], 'lon' => $c['lon'], 'radius' => 25]; $radius = 25;
            foreach ($chips as $i => $c0) if ($c0['type'] === 'place') $chips[$i]['radius'] = 25;
            self::tr('Where', 'Within 25 mi', 'An outing stays local: the nearest that suit them.');
        }
        $cat = $chip('category');
        $kind = $chip('kind')['value'] ?? null;
        $conds = array_column(array_filter($chips, fn ($c) => $c['type'] === 'cond'), 'value');
        $want = fn (string $k) => $kind === null || $kind === $k;
        // When, Why, Who (decisions/0053 §8)
        $whenC = $chip('when'); $whyC = $chip('why'); $whoC = $chip('who');
        [$dow, $min] = isset($ctx['at']) && is_array($ctx['at']) ? $ctx['at'] : [(int) date('w'), (int) date('G') * 60 + (int) date('i')];
        $when = [];
        if ($whenC) {
            $v = (string) $whenC['value'];
            $when = $v === 'now' ? ['now' => [$dow, $min]] : ($v === 'tonight' ? ['now' => [$dow, 20 * 60]] : ($v === 'today' ? ['days' => [$dow]]
                  : ($v === 'weekend' ? ['days' => [6, 0]] : (str_starts_with($v, 'day:') ? ['days' => [(int) substr($v, 4)]] : []))));
        }
        $why = (string) ($whyC['value'] ?? '');
        // an action: the activity is what's searched, outdoor places first; the words left over put the best first
        $doC = $chip('do'); $boost = []; $anyWord = false;
        if ($doC) {
            $boost = array_values(array_filter(preg_split('/\s+/u', $text) ?: [], fn ($w) => mb_strlen($w) >= 3
                && !in_array($w, array_merge(self::DESCRIBE, SearchIndex::STOP, ['where', 'what', 'when', 'how', 'can', 'could', 'should', 'would', 'go', 'going', 'take', 'kids', 'family', 'friends', 'today', 'tomorrow']), true)));
            if ($boost) foreach ($chips as $i => $c) if ($c['type'] === 'do') $chips[$i]['about'] = $text;
            if ($boost) self::tr('What', 'What it\'s for: "' . implode(' ', $boost) . '"', 'Places that mention it come first; it never hides the others.', $boost);
            $text = self::ACT_WORDS[(string) $doC['value']] ?? (string) $doC['value'];
            $anyWord = true;   // a trail, a trailhead or "hiking": any of the activity's words
            $cat = null;
            if ($why === '') $why = 'explore';
        }
        $aud = $whoC && empty($whoC['role']) ? (string) $whoC['value'] : '';
        if ($outC && !empty($outC['soft'])) $boost = array_merge($boost, self::OUTING_BOOST[(string) $outC['value']] ?? []);   // "family friendly restaurants"
        $fiveW = $why !== '' || $when || $whoC;
        // One index for everything (decisions/0053 §8), once it has been built; until then, the kinds one by one.
        $useIndex = class_exists('SearchIndex') && SearchIndex::ready($pdo) && SearchIndex::state($pdo, 'built_listing') !== '';
        // the Relational Intent Map (decisions/0061): when the words name a situation, the rest of the results match
        // whole words only ("broken down" isn't Downspouts or Downtown)
        $sitA = $outing ? null : self::situationFor($pdo, $q, $place, $area, $reach, $ctx, $drop);
        if ($sitA) self::tr('Why', 'A situation: ' . implode(' + ', $sitA['labels']), 'The words match ' . (count($sitA['phrases'] ?? []) === 1 ? 'the phrase' : 'the phrases') . ' "' . implode('", "', $sitA['phrases'] ?? []) . '" in the situation map (longest first, up to two words between). Its needs answer first' . (!empty($sitA['need_place']) ? ', once a place is set' : '') . '.', implode(' ', $sitA['phrases'] ?? []));
        if ($radius > 0 && $area) self::tr('Where', 'Within ' . $area['radius'] . ' mi', 'The Distance slider (or "near" a landmark).');        // the Why used the word a category was read from ("where can I park overnight": park is the verb, not Parks)
        if ($sitA && $cat) {
            $said0 = [];
            foreach ((array) ($sitA['phrases'] ?? []) as $ph) foreach (explode(' ', (string) $ph) as $pw) $said0[$pw] = 1;
            $cw0 = array_filter(explode(' ', Situations::norm((string) ($cat['words'] ?? $cat['label']))));
            if ($cw0 && !array_diff($cw0, array_keys($said0))) {
                self::tr('What', 'Not the ' . $cat['label'] . ' category', 'The situation\'s phrase already uses "' . ($cat['words'] ?? '') . '" ("where can I park": park is the verb).');
                $cat = null;
                $chips = array_values(array_filter($chips, fn ($c) => $c['type'] !== 'category'));
            }
        }
        // The What gate (decisions/0061, the 5 W's parser). Why is read first: the situation's own words are used up.
        // What's left must name something the directory knows (a category word, an everyday word for one, a kind of
        // place or thing to do) or be something's name. Words that only describe it ("commercial", "heavy duty") put
        // the things that mention them first. Nothing known and no name: we say we couldn't tell, rather than match
        // stray words ("standing alone on the corner" isn't New Hope Pregnancy Resource or portable toilets).
        $unread = false; $attrs = []; $gated = false; $nameOfCode = false;
        if ($text !== '' && !$cat && !$doC && !$u['exact'] && !in_array('what', $drop, true)) {
            $skip = array_merge(SearchIndex::STOP, self::DESCRIBE, self::FILLER, ['when', 'how', 'why', 'which', 'there', 'am', 'was', 'were', 'be', 'been', 'im', 'its', 'just', 'so', 'very', 'really', 'too', 'out', 'should', 'must', 'could', 'would', 'will', 'maybe']);
            $ws = array_values(array_filter(preg_split('/\s+/u', $text) ?: [], fn ($w) => $w !== '' && !in_array($w, $skip, true)));
            if ($sitA) {   // the situation's words are the Why, not the What
                $said = [];
                foreach ((array) ($sitA['phrases'] ?? []) as $ph) foreach (explode(' ', (string) $ph) as $pw) $said[$pw] = 1;
                $ws = array_values(array_filter($ws, fn ($w) => !isset($said[Situations::norm($w)])));
            }
            $vocab = self::whatVocab($pdo);
            $known = array_values(array_filter($ws, fn ($w) => isset($vocab[self::root($w)])));
            $unk = array_values(array_diff($ws, $known));
            if (!$ws) $text = '';
            elseif ($sitA) { $text = $known ? implode(' ', $known) : ''; self::tr('What', $known ? 'Also: ' . $text : 'Nothing else to search', $known ? 'Beside the situation, only words the directory knows are searched, as whole words.' : 'The situation used the words; the rest (' . implode(', ', $ws) . ') aren\'t things the directory knows.', $known ?: []); }   // only a real What beside a situation
            elseif (count($ws) === 1 && !$known && preg_match('/ (how to|how do i|how can i|how do you|learn to|learn how to|steps to) ' . preg_quote($ws[0], '/') . ' /', ' ' . mb_strtolower($q) . ' ') && !self::nameHas($pdo, $ws[0])) {
                // a procedure on a word nothing knows ("how to compost"): say so, rather than search it as a name (decisions/0063)
                $unread = true; $text = '';
                self::tr('What', 'A procedure, on a word we don\'t know', '"' . $ws[0] . '" follows "how to", so it\'s something to do, but search doesn\'t know the word yet. It\'s offered to teach (staff) or suggest (visitors); how-to guides are coming.', $ws);
            }
            elseif (count($ws) === 1 && !$known && mb_strlen($ws[0]) >= 3) { $text = $ws[0]; self::tr('What', 'A name: "' . $text . '"', 'One word the directory doesn\'t know as a thing: searched as the start of a name.', $text); }   // one word: a name being typed ("lym", "mudslingers")
            elseif (self::nameHas($pdo, implode(' ', $ws))) { $text = implode(' ', $ws); self::tr('Who', 'Something\'s name: "' . $text . '"', 'These words, in this order, are in a listing\'s, place\'s or group\'s name.', $ws); }   // a Who: something's own name
            elseif ($known) {
                // the What is the last thing named (the head noun): "commercial plumber" is a plumber, "heavy duty
                // generator" a generator; the words before it are attributes that put the best first. Unless the
                // known words together are a kind of thing ("oil change", "auto parts").
                $phrase = implode(' ', $known);
                $whole = count($known) === 1 || self::catHas($pdo, $phrase);
                $head = $whole ? $known : [end($known)];
                $attrs = array_values(array_diff($ws, $head));
                $text = implode(' ', $head); $boost = array_merge($boost, $attrs); $gated = true;
                self::tr('What', '"' . $text . '"', ($whole && count($known) > 1 ? 'Known words that together are a kind of thing.' : (count($known) > 1 ? 'The last thing named is the What (the head noun); the words before it are attributes.' : 'A word the directory knows as a thing.'))
                    . ($attrs ? ' "' . implode('", "', $attrs) . '" ' . (count($attrs) === 1 ? 'puts' : 'put') . ' the places that mention ' . (count($attrs) === 1 ? 'it' : 'them') . ' first, never hiding the rest.' : ''), array_merge($head, $attrs));
            }
            // a name with words that aren't words at all ("ford f150": a model, a code): the name ("ford"). Real words
            // around a name still aren't ("standing on the corner" isn't Corner Store)
            elseif (($nmW = array_values(array_filter($ws, fn ($w) => mb_strlen($w) >= 3 && self::nameHas($pdo, $w))))
                && (function () use ($pdo, $q, $ws, $nmW) { try { require_once __DIR__ . '/Lexicon.php'; $uk = Lexicon::unknown($pdo, $q); } catch (Throwable) { return false; }
                       return !array_diff(array_diff($ws, $nmW), $uk); })()) {
                $text = count($nmW) > 1 && self::nameHas($pdo, implode(' ', $nmW)) ? implode(' ', $nmW) : $nmW[0];
                $nameOfCode = true;   // a business's name, not a town's: no "Which Ford?"
                self::tr('Who', 'Something\'s name: "' . $text . '"', 'The other words (' . implode(', ', array_diff($ws, $nmW)) . ') aren\'t words search knows, so the name is searched.', $nmW);
            }
            // words whose meaning leads to a kind of place (decisions/0067): "x ray" is Diagnostic Imaging. Searched as
            // they are, and the closest kinds follow the results
            elseif (is_file(__DIR__ . '/Concepts.php') && (require_once __DIR__ . '/Concepts.php') && Concepts::kinds($pdo, implode(' ', $ws), 1)) {
                $text = implode(' ', $ws);
                self::tr('What', 'By what it means: "' . $text . '"', 'Not a kind we have by name; the kinds it means are offered with the results.', $ws);
            }
            else { $unread = true; $text = ''; self::tr('What', 'Couldn\'t tell what\'s wanted', 'None of "' . implode('", "', $ws) . '" is a thing the directory knows, a name, or a situation\'s phrase. Nothing is searched rather than matching stray words.', $ws); }
        }
        // a kind of place to see, said as the thing wanted ("landmarks I should check out", "canyons")
        $seeing = false;
        foreach (self::TAGS as $tag => $grp) if (!$cat && $text !== '' && self::root(mb_strtolower($tag)) === self::root($text) && !self::actNot(mb_strtolower($tag), $q)) { $seeing = true; break; }
        if ($seeing) self::tr('What', 'Only places for going', '"' . $text . '" is a kind of place to see: businesses only named for it don\'t count (Landmark Homes).', $text);
        $groups = [];
        // An exact business name leads, unless the query was also a place: then the place leads and the business is listed.
        $placeQuery = $place && !($place['scope'] ?? false) && $text === '';
        $explore = ($ctx['track'] ?? '') === 'explore';   // the Chameleon Filter: on Let's Explore a business name isn't the top answer
        $top = $u['exact'] && !$placeQuery && !$explore ? ['kind' => 'listing'] + $u['exact'] : null;

        // Facts: a direct answer (population, median age, household income) for the place asked about.
        if ($chip('facts') && $place && !empty($place['zips']) && class_exists('PlaceProfile')) {
            try {
                if ($f = PlaceProfile::forZips($pdo, $place['zips'])) {
                    $lines = [];
                    if ($f['population'] !== null) $lines[] = number_format((float) $f['population']) . ' people';
                    if ($f['median_age'] !== null) $lines[] = 'Median age ' . round((float) $f['median_age']);
                    if ($f['median_income'] !== null) $lines[] = 'Median household income $' . number_format((float) $f['median_income']);
                    if ($lines) $top = ['kind' => 'facts', 'label' => $place['label'], 'lines' => $lines, 'note' => 'U.S. Census, American Community Survey' . (!empty($f['approx']) ? ' (combined across ZIPs, approximate)' : ''),
                                        'url' => $place['url'] ? $place['url'] . '#living-h' : ''];
                }
            } catch (Throwable) {}
        }

        // Places
        if ($want('places')) {
            $items = [];
            if ($place && !($place['scope'] ?? false) && $place['url']) $items[] = ['label' => $place['label'], 'sub' => $place['sub'], 'url' => $place['url']];
            if (mb_strlen($text) >= 3) {
                foreach (Community::findPlaces($pdo, $text) as $p) {
                    try { [, $url] = Links::resolve($pdo, 'place', $p['key']); } catch (InvalidArgumentException) { continue; }
                    $items[] = ['label' => $p['label'], 'sub' => $p['level'], 'url' => $url];
                }
            }
            $items = array_values(array_intersect_key($items, array_unique(array_column($items, 'url'))));
            if ($items) $groups[] = ['kind' => 'places', 'title' => 'Places', 'items' => array_slice($items, 0, 5)];
        }

        // Listings (a peek card first; "See all" opens the directory with the same place and words)
        if ($want('listings')) {
            [$rows, $total] = self::listings($pdo, $text, $place, $cat, $conds, $area);
            if ($text === '' && !$cat && !$conds) $rows = [];   // a place alone: the count and "See all", not an A–Z list
            if ($u['exact'] && $placeQuery) $rows = [$u['exact']];   // "Greer Arizona" the business, below Greer the place
            if (!$top && !$explore && $text !== '' && $rows && mb_strtolower($rows[0]['label']) === mb_strtolower($text)) $top = ['kind' => 'listing'] + $rows[0];
            if ($rows || $cat || ($total > 0 && $place)) {
                $args = [];
                $pp = ['cluster' => 'cluster', 'geo_hub' => 'geo', 'hub' => 'hub'][$place['level'] ?? ''] ?? null;
                if ($area) { $args += ['lat' => $area['lat'], 'lon' => $area['lon'], 'radius' => $area['radius'], 'near' => $place['label'] ?? '']; $pp = 'area'; }
                elseif ($pp && !empty($place['slug'])) $args[$pp] = $place['slug'];
                $words = trim($text . (!$pp && !empty($place['city']) ? ' ' . $place['city'] : ''));
                if ($words !== '') $args['q'] = $words;
                if ($cat && ($cat['words'] ?? '') !== '') $args['q'] = trim(($args['q'] ?? '') . ' ' . $cat['words']);   // the directory reads the words the same way
                elseif ($cat) { $args['cat'] = $cat['slug']; $args['catname'] = $cat['label']; }
                $groups[] = ['kind' => 'listings', 'title' => 'Listings', 'items' => $rows, 'total' => $total,
                             'more' => $total > 0 && ($total > count($rows) || $cat) && !($why !== '' && $text === '' && !$cat) ? ['label' => 'See all ' . $total . ' listings' . ($area ? ' within ' . $area['radius'] . ' mi of ' . ($place['label'] ?? 'here') : ($place ? ' in ' . ($place['area'] ?? $place['label']) : '')), 'url' => '/directory/?' . http_build_query($args)] : null];   // the directory counts a town's whole area
            }
        }

        // Stories and guides
        if (!$useIndex && $want('stories') && class_exists('Stories') && Stories::ready($pdo)) {
            $items = [];
            $sql = "SELECT place_key, title FROM place_stories WHERE status = 'published'";
            $args = [];
            if ($text !== '') { $sql .= ' AND (title LIKE ? OR body LIKE ?)'; $args = ['%' . $text . '%', '%' . $text . '%']; }
            elseif ($place && $place['key'] !== '' && !$cat) { $sql .= ' AND place_key = ?'; $args = [$place['key']]; }
            else $sql .= ' AND 1 = 0';
            $st = $pdo->prepare($sql . ' ORDER BY updated_at DESC LIMIT 5');
            $st->execute($args);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) {
                try { [$pl, $url] = Links::resolve($pdo, 'place', (string) $s['place_key']); } catch (InvalidArgumentException) { continue; }
                $items[] = ['label' => (string) $s['title'], 'sub' => 'Story · ' . $pl, 'url' => $url . '#story-h'];
            }
            if ($items) $groups[] = ['kind' => 'stories', 'title' => 'Stories & guides', 'items' => $items];
        }

        // Community groups (members-only posts are never searched)
        if (!$useIndex && $want('groups') && Community::ready($pdo) && ($text !== '' || ($place && !$cat))) {
            $pk = ($place['level'] ?? '') !== '' && $place['key'] !== '' ? (string) $place['key'] : '';
            $gs = Community::directory($pdo, $text, 5, $text === '' ? $pk : '');
            $items = array_map(fn ($g) => ['label' => $g['name'], 'sub' => $g['members'] . ' member' . ($g['members'] === 1 ? '' : 's') . ($g['topic_label'] ? ' · ' . $g['topic_label'] : ''),
                'url' => '/community/group.php?id=' . $g['id']], $gs);
            if ($items) $groups[] = ['kind' => 'groups', 'title' => 'Groups', 'items' => $items, 'more' => $text !== '' ? ['label' => 'All matching groups', 'url' => '/community/?q=' . rawurlencode($text)] : null];
        }

        // Topics: categories and community topics whose name matches
        if (!$useIndex && $want('topics') && mb_strlen($text) >= 3) {
            $items = [];
            $st = $pdo->prepare('SELECT name, slug FROM categories WHERE is_active = 1 AND name' . SearchText::CI . ' LIKE ? ORDER BY parent_id IS NULL DESC, name LIMIT 4');
            $st->execute(['%' . SearchText::esc($text) . '%']);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) $items[] = ['label' => (string) $c['name'], 'sub' => 'Category', 'url' => '/directory/?cat=' . rawurlencode((string) $c['slug'])];
            $topics = class_exists('Tags') ? Tags::topics($pdo) : Community::TOPICS;
            foreach ($topics as $k => $label) if (str_contains(mb_strtolower($label), $text) && isset(Community::TOPICS[$k])) $items[] = ['label' => $label, 'sub' => 'Community topic', 'url' => '/community/?topic=' . rawurlencode($k)];
            if ($items) $groups[] = ['kind' => 'topics', 'title' => 'Topics', 'items' => array_slice($items, 0, 5)];
        }

        // Everything that matches, whatever its kind, in one ordered list (grouping by kind is the person's choice)
        $results = null;
        if ($useIndex && $kind !== 'places') {
            $r = SearchIndex::find($pdo, $noAb + ['text' => $text, 'cat' => $cat, 'place' => $place, 'area' => $area, 'why' => $why, 'audience' => $aud, 'when' => $when, 'prefer' => (string) ($whoC['prefer'] ?? (str_starts_with((string) ($whenC['label'] ?? ''), 'Emergency') ? 'emergency' : (($whenC['label'] ?? '') === 'Open now first' ? '24' : ''))), 'boost' => $boost, 'outing' => $outing, 'any' => $anyWord, 'whole' => $anyWord || $sitA, 'strict' => $gated, 'explore_only' => $seeing, 'act' => $doC ? explode(' ', (string) $doC['value'])[0] : '', 'funnel' => (string) ($chip('funnel')['value'] ?? ''),
                'kind' => ['listings' => 'listing', 'stories' => 'story', 'groups' => 'group', 'topics' => 'topic'][$kind ?? ''] ?? null,
                // "near me" alone (Jason, 2026-11-10): everything near you, nearest first, up to 25
                'limit' => $nearMe && $area ? ($text === '' && !$cat ? 25 : 10) : 12, 'nearest' => $nearMe && $area,   // "near me": the nearest first
                'track' => (string) ($ctx['track'] ?? '')]);
            $items = $r['items'];
            if (($ctx['track'] ?? '') === 'explore' && $text !== '' && ($kind === null || $kind === 'stories')) {
                $jr = self::journeysFor($pdo, $text, $place);   // journeys people shared here, first on Let's Explore
                if ($jr) { $items = array_merge($jr, $items); $r['kinds']['story'] = ($r['kinds']['story'] ?? 0) + count($jr);
                           $r['funnels']['explore'] = ($r['funnels']['explore'] ?? 0) + count($jr); $r['total'] = (int) ($r['total'] ?? 0) + count($jr); }
                $xp = self::experiencesFor($pdo, $text, $place);   // experiences (decisions/0059) lead on Let's Explore
                if ($xp) { $items = array_merge($xp, $items); $r['kinds'] = ['experience' => count($xp)] + $r['kinds'];
                           $r['funnels']['explore'] = ($r['funnels']['explore'] ?? 0) + count($xp); $r['total'] = (int) ($r['total'] ?? 0) + count($xp); }
            }
            $wider = null;
            $real = array_filter($items, fn ($x) => $x['kind'] !== 'topic');   // a category link is a way in, not an answer
            if (($ctx['track'] ?? '') === 'explore' && $place && $text !== '' && !$real && $kind === null) {
                // On Let's Explore, nothing in the place: look across the open regions (decisions/0058 §22), and say so
                $r2 = SearchIndex::find($pdo, $noAb + ['text' => $text, 'cat' => $cat, 'place' => null, 'area' => null, 'why' => $why, 'limit' => 12, 'track' => 'explore']);
                $i2 = array_merge(self::experiencesFor($pdo, $text, null), self::journeysFor($pdo, $text, null), $r2['items']);
                usort($i2, fn ($a, $b) => ($a['kind'] === 'topic') <=> ($b['kind'] === 'topic'));   // real places first, the categories after
                if (array_filter($i2, fn ($x) => $x['kind'] !== 'topic')) { $items = $i2; $r = $r2; $r['total'] = count($i2); $wider = (string) $place['label']; foreach ($i2 as $x) if (!isset($r['kinds'][$x['kind']])) $r['kinds'][$x['kind']] = 1; }
            }
            $listingsHere = $wider !== null ? 0 : (int) ($r['kinds']['listing'] ?? 0);
            if ($top && ($top['kind'] ?? '') === 'listing') $items = array_values(array_filter($items, fn ($x) => !($x['kind'] === 'listing' && (int) $x['id'] === (int) $top['id'])));
            $kinds = [];
            foreach ($r['kinds'] as $k => $n) $kinds[] = ['kind' => $k, 'label' => SearchIndex::KIND_LABEL[$k] ?? $k, 'plural' => SearchIndex::KIND_PLURAL[$k] ?? $k, 'count' => $n];
            $more = [];
            if (($r['kinds']['group'] ?? 0) > 0 && $text !== '') $more[] = ['label' => 'All matching groups', 'url' => '/community/?q=' . rawurlencode($text)];
            // The site's sections, with what each holds for this search and where its full view is
            $placesN = 0;
            foreach ($groups as $g) if ($g['kind'] === 'places') $placesN = count($g['items']);
            $lg = array_values(array_filter($groups, fn ($g) => $g['kind'] === 'listings' && !empty($g['more'])))[0]['more']['url'] ?? null;
            $funnels = [];
            foreach (SearchIndex::FUNNELS as $f => $label) {
                $n = ($r['funnels'][$f] ?? 0) + ($f === 'explore' ? $placesN : 0);
                $url = match ($f) {
                    // with outdoor places among them, Let's Explore opens its Outdoors view, not the overview (2026-11-10: "hikes near me")
                    'explore' => (function () use ($place, $r) { $u0 = $place && !empty($place['url']) ? (string) $place['url'] : '/discovery/'; return ($r['kinds']['outdoors'] ?? 0) > 0 ? $u0 . (str_contains($u0, '?') ? '&' : '?') . 'view=outdoors' : $u0; })(),
                    'local' => $lg ?? '/directory/' . ($text !== '' ? '?q=' . rawurlencode($text) : ''),
                    'social' => '/community/' . ($text !== '' ? '?q=' . rawurlencode($text) : ''),
                    default => null,
                };
                $funnels[] = ['key' => $f, 'label' => $label, 'count' => $n, 'url' => $url, 'soon' => $f === 'market'];
            }
            $results = ['items' => $items, 'kinds' => $kinds, 'funnels' => $funnels, 'total' => $r['total'], 'more' => $more, 'loose' => !empty($r['loose']),
                        'note' => $wider !== null ? 'Nothing in ' . $wider . ' yet. These are from across the open regions.' : ($aud !== '' && !($r['audience_found'] ?? true) ? ($r['total'] ? 'Nothing here says it is ' . mb_strtolower((string) $whoC['label']) . ', so everything that matches is shown.'
                                                                             : 'Nothing listed here says it is ' . mb_strtolower((string) $whoC['label']) . ' yet.') : null)]
                + (isset($r['hours_known']) ? ['hours_known' => $r['hours_known']] : []);   // asked open now: how many have known hours (P10, 2026-11-27)
        }

        // Who: a business named outright leads, with what it offers, so one tap finds more like it (decisions/0061)
        if ($top && ($top['kind'] ?? '') === 'listing' && ($oc = SearchText::catLabel(trim(explode(' · ', (string) ($top['sub'] ?? ''))[0]))) !== '' && $oc !== 'Listing') $top['offers'] = $oc;
        if ($top && ($top['kind'] ?? '') === 'listing') self::tr('Who', 'A business named outright: ' . $top['label'], 'It leads, with what it offers (' . ($top['offers'] ?? 'its kind') . ') and "More like it nearby".', $top['label']);
        // Why answered it all ("stuck in the mud"), or nothing was read: no directory list under it
        $quiet = $unread || ($sitA && $text === '' && !$cat);
        if ($quiet) {
            $groups = [];
            $results = ['items' => [], 'kinds' => [], 'funnels' => [], 'total' => 0, 'more' => [], 'loose' => false, 'note' => null];
        }
        // how to do it: a published how-to guide for these words leads the list (decisions/0063 §8); none yet, and an
        // action leads to who does it, as before
        if ($want('stories') && is_file(__DIR__ . '/HowGuides.php')) {
            require_once __DIR__ . '/HowGuides.php';
            try {
                if ($hg = HowGuides::forQuery($pdo, $q)) {
                    array_unshift($groups, ['kind' => 'stories', 'title' => 'How-to guide', 'items' => [['label' => $hg['title'], 'sub' => 'How-to guide · ' . $hg['steps'] . ' steps, from official sources', 'url' => '/guide/how.php?id=' . $hg['id']]]]);
                    self::tr('Why', 'A how-to guide: ' . $hg['title'], 'Published by our team from official how-to pages; it leads, and who does it follows.', $hg['title']);
                }
            } catch (Throwable $e) { error_log('how guide: ' . $e->getMessage()); }
        }
        // our own knowledge first (decisions/0133): a topic in the index, or a question about a place's past, answered from
        // the guides' vetted facts before anything else
        if (!$top && !$cat && is_file(__DIR__ . '/Topics.php')) {
            try { require_once __DIR__ . '/Topics.php'; if ($ans = Topics::answer($pdo, $text, $place, trim(str_ireplace((string) ($place['label'] ?? '~'), '', $q)))) $top = $ans; } catch (Throwable $e) { error_log('search knowledge: ' . $e->getMessage()); }
        }
        // and the catalog's vetted facts the words name, as "From our guides" (never a restricted one; a story-only one without its place)
        if (!$cat && $text !== '' && is_file(__DIR__ . '/Heritage.php')) {
            try {
                require_once __DIR__ . '/Heritage.php';
                $said = array_map(fn ($l) => (string) strtok((string) $l, ':('), (array) ($top['lines'] ?? []));
                $hz = array_values(array_filter(Heritage::searchPublic($pdo, trim(str_ireplace((string) ($place['label'] ?? '~'), '', $q)) ?: $text, 6), fn ($h) => !in_array(trim($h['label']), array_map('trim', $said), true)));
                if ($hz) $groups[] = ['kind' => 'heritage', 'title' => 'From our guides', 'items' => array_slice($hz, 0, 4)];
            } catch (Throwable $e) { error_log('search heritage: ' . $e->getMessage()); }
        }
        if (!$quiet && !$top && !$fiveW && $place && !($place['scope'] ?? false) && $text === '' && !$cat && $place['url']) {
            $top = ['kind' => 'place', 'label' => $place['label'], 'sub' => $place['sub'], 'url' => $place['url']];
            foreach ($groups as $i => $g) if ($g['kind'] === 'places') {   // not twice
                $groups[$i]['items'] = array_values(array_filter($g['items'], fn ($it) => $it['url'] !== $place['url']));
                if (!$groups[$i]['items']) unset($groups[$i]);
            }
            $groups = array_values($groups);
        }

        // People: never in this search; a hand-off to the Address Book's Find, with the words typed.
        $words = trim($text !== '' ? $text : $q);
        // A name: 1-3 plain words, none of them question or everyday words, and nothing else found
        $wl = explode(' ', mb_strtolower($words));
        $common = class_exists('SearchIndex') ? array_merge(SearchIndex::STOP, SearchIndex::SOFT, array_keys(SearchText::synonyms())) : [];
        $personLike = (bool) preg_match('/^[\p{L}\'-]+( [\p{L}\'-]+){0,2}$/u', $words) && !$place && !$cat && !$groups && !$chip('why') && !$chip('who') && !$chip('do')
            && !array_intersect($wl, $common) && (!($results['items'] ?? []) || !empty($results['loose']));
        $people = mb_strlen($words) >= 2 && !$cat && !$sitA && !$chip('do') && ($text !== '' || !array_filter($chips, fn ($c) => $c['type'] !== 'place')) ? ['label' => 'Looking for a person? Search your Address Book for “' . $words . '”',
            'url' => $viewer !== null ? '/user/dashboard.php#addressbook:find=' . rawurlencode($words) : '/login.php', 'signed_in' => $viewer !== null, 'likely' => $personLike] : null;

        // "Did you mean…?" (Jason, 2026-10-03): a word nothing knows is never changed; the closest known words are
        // offered instead, likeliest first, each with what it would answer
        $dym = null; $dyms = [];
        if (mb_strlen($q) >= 4) {   // a slip can look like a person's name: asking does no harm
            try {
                $dyms = self::didYouMean($pdo, $q);
                $dym = $dyms[0]['q'] ?? null;
                if ($dyms) self::tr('What', 'Did you mean: ' . implode(' · ', array_column($dyms, 'q')), 'A word nothing knows is never changed by search; the closest known words are offered, the ones keeping most of what was typed first.', array_map(fn ($f) => $f[0], $dyms[0]['fixed']));
            } catch (Throwable $e) { error_log('did you mean: ' . $e->getMessage()); }
        }
        // Nothing like it in the place asked about: say so, and show where it is nearby (decisions/0058 §12)
        $elsewhere = null;
        if ($useIndex && $place && !$doC && !$seeing && $reach !== 'place' && !$area && !($place['facts'] ?? false) && ($text !== '' || $cat) && ($listingsHere ?? 0) === 0 && !$top
            && !($explore && $results && !empty($results['items']))) {   // on Let's Explore, stories or outdoors here are an answer
            try { $elsewhere = self::elsewhere($pdo, $place, $text !== '' ? $text : (string) $cat['label']); } catch (Throwable $e) { error_log('elsewhere: ' . $e->getMessage()); }
        }
        // Local first, never only local (decisions/0060): what matches beyond the place follows, nearest first, so a
        // search can reach past the place someone set without changing it
        // (a landmark's 15 miles too: "plumber near Lyman Lake" with none that close shows the nearest beyond it)
        if ($useIndex && $results !== null && $place && (!$area || !empty($place['landmark'])) && $reach !== 'place' && ($text !== '' || $cat) && $kind !== 'places' && empty($wider)) {
            try {
                $c = self::centerOf($pdo, $place);
                if ($c) {
                    // everywhere, nearest first
                    $rb = SearchIndex::find($pdo, $noAb + ['text' => $text, 'cat' => $cat, 'place' => null, 'area' => $c + ['radius' => !empty($place['outside']) ? 150 : 3000], 'not_place' => $place, 'why' => $why, 'audience' => $aud, 'explore_only' => $seeing,
                        'when' => $when, 'funnel' => (string) ($chip('funnel')['value'] ?? ''), 'strict' => true, 'limit' => 40, 'boost' => $boost, 'any' => $anyWord, 'whole' => $anyWord, 'act' => $doC ? explode(' ', (string) $doC['value'])[0] : '',   // the best 40, then the nearest 12
                        'kind' => ['listings' => 'listing', 'stories' => 'story', 'groups' => 'group'][$kind ?? ''] ?? null, 'track' => (string) ($ctx['track'] ?? '')]);
                    $shown = []; foreach ($results['items'] as $x) $shown[$x['kind'] . ':' . $x['id']] = 1;   // not twice (a group with no place shows in both)
                    $bi = array_values(array_filter($rb['items'], fn ($x) => $x['kind'] !== 'topic' && !isset($shown[$x['kind'] . ':' . $x['id']]) && !(!empty($elsewhere['towns']) && $x['kind'] === 'listing')));   // "none here" already lists nearby businesses by town
                    usort($bi, fn ($x, $y) => ($x['miles'] ?? INF) <=> ($y['miles'] ?? INF));   // nearest first
                    $bi = array_slice($bi, 0, 12);
                    if ($bi) $results['beyond'] = ['place' => (string) $place['label'], 'items' => $bi, 'total' => count($bi)];
                }
            } catch (Throwable $e) { error_log('beyond: ' . $e->getMessage()); }
        }
        // "My place only" with nothing there isn't a dead end: how near the nearest is, and how many a wider reach
        // finds, each one tap (decisions/0060, graduated widening). The place stays as set.
        // the same for a distance ("10 mi", the default): the next steps out, each one tap; never past it on its own
        if ($useIndex && $results !== null && $place && !$nearMe && ($reach === 'place' || ctype_digit($reach)) && ($text !== '' || $cat || $doC)
            && !array_filter($results['items'], fn ($x) => $x['kind'] !== 'topic') && !$top) {
            try {
                $c = self::centerOf($pdo, $place);
                if ($c) {
                    $rw = SearchIndex::find($pdo, $noAb + ['text' => $text, 'cat' => $cat, 'place' => null, 'area' => $c + ['radius' => !empty($place['outside']) ? 150 : 3000], 'not_place' => $place, 'why' => $why,
                        'audience' => $aud, 'when' => $when, 'strict' => true, 'limit' => 40, 'boost' => $boost, 'any' => $anyWord, 'whole' => $anyWord, 'act' => $doC ? explode(' ', (string) $doC['value'])[0] : '',
                        'kind' => ['listings' => 'listing', 'stories' => 'story', 'groups' => 'group'][$kind ?? ''] ?? null, 'track' => (string) ($ctx['track'] ?? '')]);
                    $mi = array_values(array_filter(array_map(fn ($x) => $x['miles'] ?? null, array_filter($rw['items'], fn ($x) => $x['kind'] !== 'topic')), fn ($m) => $m !== null));
                    sort($mi);
                    if ($mi) {
                        $steps = [];
                        $cur = ctype_digit($reach) ? (int) $reach : 0;
                        foreach (($nearMe ? [5] : [5, 10, 25, 50, 100, 250]) as $r0) {   // "near me" widens to 5 mi, no further
                            if ($r0 <= $cur || count($steps) >= 2) continue;
                            if (($n0 = count(array_filter($mi, fn ($m) => $m <= $r0))) > 0) $steps[] = ['reach' => (string) $r0, 'label' => $r0 . ' mi', 'count' => $n0];
                            if ($n0 >= count($mi)) break;   // everything is already in this step: no wider one finds more
                        }
                        if (!$nearMe && empty($place['outside']) && count($mi) > (int) (end($steps)['count'] ?? 0)) $steps[] = ['reach' => 'all', 'label' => 'No limit', 'count' => count($mi)];
                        if ($steps) $results['widen'] = ['place' => (string) $place['label'], 'within' => $cur, 'nearest' => (int) max(1, round($mi[0])), 'steps' => $steps, 'more' => count($mi) >= 40];
                    }
                }
            } catch (Throwable $e) { error_log('widen: ' . $e->getMessage()); }
        }
        // what the words mean, when they aren't a kind we have by name (decisions/0067): "x-rays" is Diagnostic Imaging,
        // "cardio" a gym. The closest kinds, each with how many are here, one tap each; and when nothing is found at all,
        // what mentions the words. Never a dead end.
        if ($useIndex && $results !== null && !$cat && empty($sitA) && $text !== '' && empty($place['outside'])) {
            try {
                $meant = self::meant($pdo, $text, $place);
                if ($meant) $results['meant'] = ['words' => $text, 'kinds' => $meant];
                if (!array_filter($results['items'], fn ($x) => $x['kind'] !== 'topic') && empty($results['beyond']['items'] ?? null)) {
                    $men = self::mentions($pdo, $text, $place);
                    if ($men) $results['mentions'] = ['words' => $text, 'items' => $men];
                }
            } catch (Throwable $e) { error_log('meant: ' . $e->getMessage()); }
        }
        // a place outside our areas with nothing listed near it ("Seattle, WA"): say so, rather than a bare "nothing found"
        if ($results !== null && !empty($place['outside']) && empty($sitA) && empty($results['note']) && !array_filter($results['items'], fn ($x) => $x['kind'] !== 'topic') && empty($results['beyond']['items'] ?? null)) {
            $results['note'] = 'We have no listings in ' . $place['label'] . ' yet.';
            $gw = trim($text !== '' ? $text : (string) ($cat['label'] ?? ''));
            if ($gw !== '') $results['google'] = ['q' => $gw . ' near ' . $place['label'], 'place' => (string) $place['label']];   // only a place: the place card asks what about it
        }
        // a town of ours or not, and something we hold whose name starts with the same words ("Four Corners": the town in
        // Wyoming is read, and Four Corners Monument leads, decisions/0058 §25): the named place first, the town still offered
        if ($results !== null && $place && empty($place['scope']) && !empty($place['outside']) && $text === '' && !$cat && !$top && $useIndex) {
            $typed = mb_strtolower(trim((string) preg_replace('/[\s,]+/u', ' ', $q)));
            try {
                foreach (SearchIndex::find($pdo, ['text' => $typed, 'kind' => 'outdoors', 'limit' => 3])['items'] as $nx) {
                    if (str_starts_with(mb_strtolower((string) $nx['label']) . ' ', $typed . ' ') && mb_strtolower((string) $nx['label']) !== $typed) {
                        array_unshift($results['items'], ['named' => true] + $nx); $results['total'] = (int) ($results['total'] ?? 0) + 1; break;
                    }
                }
            } catch (Throwable $e) { error_log('named lead: ' . $e->getMessage()); }
        }
        // a kind of business with nothing of ours within reach, anywhere (2026-11-10, Jason: "why doesn't our search use our
        // crawler instantly"): what OpenStreetMap has around the place, at once (the panel asks it, decisions/0065), in place
        // of loose matches far away ("Coffeepot Rock" for coffee); and the town goes to our crawler, so real listings follow
        // (TownCrawl::fromSearch, decisions/0149). Not for the search run while typing.
        $oursIn = $results !== null ? count(array_filter($results['items'], fn ($x) => $x['kind'] !== 'topic')) : 0;
        // "near me": how far is near for these words (the outdoors is a drive), and how many of ours are that near
        $outdoorsQ = $doC || (is_file(__DIR__ . '/OpenPlaces.php') && (require_once __DIR__ . '/OpenPlaces.php') && in_array(OpenPlaces::kindFor($text . ' ' . ($cat['label'] ?? '')), ['trail', 'camping', 'park', 'viewpoint'], true));
        $nearMi = $outing ? 15 : ($outdoorsQ ? self::NEAR_MILES_OUTDOORS : self::NEAR_MILES);
        $oursNear = $results !== null ? count(array_filter($results['items'], fn ($x) => $x['kind'] !== 'topic' && ($x['miles'] ?? 999) <= $nearMi)) : 0;
        // a kind of business in a town with only one or two of ours there ("pizza in phoenix": one): OpenStreetMap's too, so the
        // answer isn't one place (2026-11-11)
        if ($results !== null && $place && empty($sitA) && ($oursIn === 0 || ($oursIn < self::NEAR_ENOUGH && ($text !== '' || $cat) && !$top) || (($nearMe || $outing) && $area && $oursNear < ($outing ? 8 : self::NEAR_ENOUGH))) && is_file(__DIR__ . '/OpenPlaces.php')) {
            require_once __DIR__ . '/OpenPlaces.php';
            $gw = trim($text !== '' ? $text : (string) ($cat['label'] ?? ''));
            // "near me" alone with few of ours: everything everyday OpenStreetMap has (2026-11-10); else the kind the words ask for
            $ok = $outing ? (['family' => 'family', 'dog' => 'dog'][$outing['for']] ?? 'todo')   // an outing: OpenStreetMap's places that suit it
                : ($gw === '' && $nearMe && $area ? 'all' : ($gw !== '' ? OpenPlaces::kindFor($gw . ' ' . ($cat['label'] ?? '')) : ''));
            if ($ok !== '' && !isset(OpenPlaces::KINDS[$ok])) $ok = '';
            if ($ok !== '' && ($oc = self::centerOf($pdo, $place))) {
                $results['osm'] = ['kind' => $ok, 'lat' => round($oc['lat'], 2), 'lon' => round($oc['lon'], 2), 'place' => (string) $place['label']];
                unset($results['mentions']);
                if (($nearMe || $outing) && $area) $results['osm']['mi'] = self::osmReach($ok, $ctx, $nearMe);   // OpenStreetMap's nearest, as far as it's asked
                if (empty($ctx['no_side']) && !empty($place['city']) && !empty($place['state']) && is_file(__DIR__ . '/crawler/TownCrawl.php')) {
                    require_once __DIR__ . '/crawler/TownCrawl.php';
                    try { TownCrawl::fromSearch($pdo, (string) $place['city'], (string) $place['state'], $ok); } catch (Throwable $e) { error_log('search crawl: ' . $e->getMessage()); }
                }
            }
        }
        // places named on pages we read that we don't list yet (decisions/0157 §3): our own index, searched like the rest, when
        // ours are few ("garbage" near St. Johns: Blue Hills Environmental, on the county's page). Each is on its way through
        // the usual checks (0044); its source is one tap away. Not while typing.
        if ($results !== null && $place && empty($sitA) && empty($ctx['no_side']) && empty($place['outside']) && ($text !== '' || $cat)
                && ($oursIn < self::NEAR_ENOUGH || (($nearMe || $outing) && $area && $oursNear < self::NEAR_ENOUGH)) && is_file(__DIR__ . '/crawler/SourceIndex.php')) {
            try {
                require_once __DIR__ . '/crawler/SourceIndex.php';
                $oc = self::centerOf($pdo, $place);
                if ($oc) {
                    $rd = SourceIndex::search($pdo, ['text' => $text !== '' ? $text : (string) ($cat['label'] ?? ''), 'cats' => $cat ? [(int) $cat['id']] : [],
                                                     'lat' => $oc['lat'], 'lon' => $oc['lon'], 'miles' => ($nearMe || $outing) && $area ? $nearMi : 25, 'limit' => 8]);
                    if ($rd) { $results['read'] = ['place' => (string) $place['label'], 'items' => array_map([SourceIndex::class, 'card'], $rd)]; unset($results['mentions']); }
                }
            } catch (Throwable $e) { error_log('search read: ' . $e->getMessage()); }
        }
        // businesses we know only by name near the place, asking for their phone or address (decisions/0164 §3.5): a request,
        // never a listing. Only for words that name them or their kind; not while typing.
        if ($results !== null && $place && $text !== '' && empty($ctx['no_side']) && empty($place['outside']) && is_file(__DIR__ . '/HelpFind.php')) {
            try {
                require_once __DIR__ . '/HelpFind.php';
                $hc = self::centerOf($pdo, $place);
                if ($hc && ($hf = HelpFind::near($pdo, $text, (float) $hc['lat'], (float) $hc['lon'], 25, 4)))
                    $results['help'] = ['place' => (string) $place['label'], 'items' => array_map(fn ($c) => ['id' => $c['id'], 'label' => $c['name'], 'what' => $c['what'], 'town' => trim($c['town'] . ($c['state'] !== '' ? ', ' . $c['state'] : ''), ', '), 'miles' => $c['miles']], $hf)];
            } catch (Throwable $e) { error_log('search help find: ' . $e->getMessage()); }
        }
        // "near me" or an outing, with OpenStreetMap's places around: ours more than 25 mi off go (it answers nearer), and an
        // outdoor place only named for the thing ("Coffeepot Rock" for coffee) never stands in for a business
        // (or nearer, the Distance the person set for an errand: "coffee near me" within 10 mi led with one 22 mi off, 2026-11-11)
        if ($results !== null && !empty($results['osm']) && ($nearMe || $outing) && $area) {
            $bizKind = !in_array((string) $results['osm']['kind'], self::OSM_GOING, true);
            $cap = self::osmReach((string) $results['osm']['kind'], $ctx, $nearMe);
            $results['items'] = array_values(array_filter($results['items'], fn ($x) => ($x['kind'] ?? '') === 'topic'
                || (($x['miles'] ?? null) !== null && $x['miles'] <= $cap && !($bizKind && ($x['kind'] ?? '') === 'outdoors'))));
        }
        // a situation "near me" with nothing of ours near for it ("urgent care near me" in Phoenix: a women's center 122 mi
        // off led, 2026-11-11): what OpenStreetMap has for it around the place, at once, and ours past where it answers go,
        // as for any "near me" above; the town goes to the crawler. Nothing near is still told to the team (results lacking).
        if ($results !== null && $sitA && !empty($sitA['needs']) && $nearMe && $area && $place && is_file(__DIR__ . '/OpenPlaces.php')) {
            require_once __DIR__ . '/OpenPlaces.php';
            $sitNear = 0;
            foreach ($sitA['needs'] as $nd) foreach ((array) ($nd['items'] ?? []) as $it) if (($it['miles'] ?? 999) <= $nearMi) $sitNear++;
            $ok = OpenPlaces::kindFor($q);
            if ($ok === '') $ok = OpenPlaces::kindFor(implode(' ', array_map(fn ($nd) => (string) ($nd['label'] ?? ''), $sitA['needs'])));
            if ($sitNear < self::NEAR_ENOUGH && $ok !== '' && isset(OpenPlaces::KINDS[$ok]) && ($oc = self::centerOf($pdo, $place))) {
                $cap = self::osmReach($ok, $ctx, true);
                $results['osm'] = ['kind' => $ok, 'lat' => round($oc['lat'], 2), 'lon' => round($oc['lon'], 2), 'place' => (string) $place['label'], 'mi' => $cap];
                // a phone line has no miles: it stays. The needs' own "none here" isn't set, so OpenStreetMap shows once, below
                foreach ($sitA['needs'] as $i => $nd) $sitA['needs'][$i]['items'] = array_values(array_filter((array) ($nd['items'] ?? []), fn ($it) => ($it['miles'] ?? null) === null || $it['miles'] <= $cap));
                if (empty($ctx['no_side']) && !empty($place['city']) && !empty($place['state']) && is_file(__DIR__ . '/crawler/TownCrawl.php')) {
                    require_once __DIR__ . '/crawler/TownCrawl.php';
                    try { TownCrawl::fromSearch($pdo, (string) $place['city'], (string) $place['state'], $ok); } catch (Throwable $e) { error_log('search crawl: ' . $e->getMessage()); }
                }
            }
        }
        // "near me": enough here? The panel adds OpenStreetMap's and decides: fewer than NEAR_ENOUGH within 2 mi offers 5;
        // fewer within 5 says our results are lacking and records it for the team (SearchDemand 'lacking')
        if ($results !== null && $place && $nearMe && $area) {
            $mi0 = array_values(array_filter(array_map(fn ($x) => $x['kind'] !== 'topic' ? ($x['miles'] ?? null) : null, $results['items']), fn ($m) => $m !== null));
            $results['near'] = ['within' => $nearMi, 'ours' => $oursNear, 'nearest' => $mi0 ? round(min($mi0), 1) : null, 'enough' => self::NEAR_ENOUGH, 'place' => (string) $place['label'],
                                'words' => trim($text !== '' ? $text : (string) ($cat['label'] ?? ''))];
            unset($results['beyond'], $results['mentions']);
        }
        // an outing: refine by who's coming and inside or out, one tap each ("Kids? Ages? Type of activities?")
        if ($outing && $results !== null && $outing['for'] === 'family') {
            $where = $nearMe ? ' near me' : ($place && empty($place['scope']) ? ' in ' . $place['label'] : '');
            $qq = fn (string $age, string $set) => 'places to take ' . (['little' => 'toddlers', 'kids' => 'kids', 'teens' => 'teens'][$age] ?? 'the kids') . ($set !== '' ? ' ' . $set . 's' : '') . $where;
            $ref = [];
            foreach (['little' => 'Little ones', 'kids' => 'Kids', 'teens' => 'Teens'] as $a => $l) $ref[] = ['label' => $l, 'q' => $qq($a, $outing['setting']), 'on' => $outing['age'] === $a];
            foreach (['indoor' => 'Indoors', 'outdoor' => 'Outdoors'] as $s => $l) $ref[] = ['label' => $l, 'q' => $qq($outing['age'], $outing['setting'] === $s ? '' : $s), 'on' => $outing['setting'] === $s];
            $results['refine'] = ['title' => 'Who\'s coming?', 'items' => $ref];
        }
        // an activity with nothing for it in reach: say so plainly (the panel offers "Tell us")
        if ($doC && $results !== null && !array_filter($results['items'], fn ($x) => $x['kind'] !== 'topic') && empty($results['beyond']['items'] ?? null) && empty($results['note'])) {
            $results['note'] = 'Nothing listed for ' . $doC['value'] . ($area ? ' within ' . $area['radius'] . ' mi of ' : ($place ? ' near ' : '')) . ($place['label'] ?? '') . ' yet. Know a spot? Tell us below.';
        }
        // Nothing has all the words ("news new hat"): each word on its own, with how many it finds around the place, so
        // the person can pick one (decisions/0060, graceful fallback)
        $split = [];
        $noneAll = $useIndex && $results !== null && !array_filter($results['items'], fn ($x) => $x['kind'] !== 'topic') && empty($results['beyond']['items'] ?? null)
            && !$top && (!$elsewhere || !$elsewhere['towns']);
        if ($noneAll && $text !== '' && !$doC) {
            $ws = array_values(array_unique(array_filter(preg_split('/\s+/u', mb_strtolower($text)) ?: [],
                fn ($w) => mb_strlen($w) >= 3 && !in_array($w, array_merge(self::DESCRIBE, SearchIndex::STOP, SearchIndex::SOFT), true))));
            if (count($ws) >= 2) {
                $c0 = $place ? self::centerOf($pdo, $place) : null;
                foreach (array_slice($ws, 0, 4) as $w) {
                    try {
                        $r1 = SearchIndex::find($pdo, $noAb + ['text' => $w, 'place' => null, 'area' => $c0 ? $c0 + ['radius' => 250] : null, 'strict' => true, 'limit' => 40]);
                        $n = count(array_filter($r1['items'], fn ($x) => $x['kind'] !== 'topic'));
                        if ($n) $split[] = ['q' => $w, 'count' => $n];
                    } catch (Throwable) {}
                }
            }
        }
        if (class_exists('SearchTrace', false) && SearchTrace::active()) {
            $nItems = count(array_filter((array) ($results['items'] ?? []), fn ($x) => ($x['kind'] ?? '') !== 'topic'));
            $nBeyond = count((array) ($results['beyond']['items'] ?? []));
            self::tr('How', $quiet ? ($unread ? 'Nothing searched' : 'The situation is the answer') : 'Found ' . $nItems . ' here' . ($nBeyond ? ' and ' . $nBeyond . ' beyond' : ''),
                $quiet ? ($unread ? 'Nothing was read as a thing, a name or a situation.' : 'Its words were all used by the situation, so there\'s no list under it.') : ($gated ? 'Searched as read, whole matches only (no loose "any one word" try).' : 'Searched as read.') . (!empty($elsewhere['towns']) ? ' None in the place: the nearest towns that have it are shown.' : '') . (!empty($results['widen']) ? ' "My place only" found none: widening offered.' : ''));
        }
        // the intent first (decisions/0157 §4.3, §7.4): a question about one thing we hold is answered; the pages we read that
        // match come with the results; and how the search was read is said, with how else it could be. Not while typing.
        $fact = null; $reading = null;
        if (is_file(__DIR__ . '/SearchReading.php')) {
            try {
                require_once __DIR__ . '/SearchReading.php';
                if (empty($ctx['no_side'])) {
                    $pc = $place ? (self::centerOf($pdo, $place) ?? []) : [];
                    $fact = SearchReading::fact($pdo, $q, $place ? ['label' => (string) $place['label']] + $pc : null);
                    $pw = trim($text !== '' ? $text : (string) ($cat['label'] ?? ''));
                    if ($results !== null && $pw !== '' && !$fact && ($pg = SearchReading::pages($pdo, $pw, $place))) $results['pages'] = ['words' => $pw, 'items' => $pg];
                }
                $reading = SearchReading::line(['q' => $q, 'text' => $text, 'place' => $place['label'] ?? null, 'cat' => $cat['label'] ?? null,
                    'meant' => (array) ($results['meant']['kinds'] ?? []), 'top' => $top && ($top['kind'] ?? '') === 'listing' ? (string) $top['label'] : null, 'top_kind' => $top['offers'] ?? null,
                    'need' => $sitA['labels'] ?? null, 'do' => $doC ? (string) $doC['value'] : null, 'when' => $whenC['label'] ?? null, 'fact' => $fact]);
            } catch (Throwable $e) { error_log('search reading: ' . $e->getMessage()); }
        }
        // What the full-screen map asks /api/search_listings.php for (view=map): the same place or distance, words and filters.
        $mq = [];
        if ($area) $mq += ['lat' => $area['lat'], 'lon' => $area['lon'], 'radius' => $area['radius']];
        elseif ($place && ($pp = ['cluster' => 'cluster', 'geo_hub' => 'geo', 'hub' => 'hub'][$place['level'] ?? ''] ?? null) && !empty($place['slug'])) $mq[$pp] = $place['slug'];
        elseif ($place && !empty($place['state'])) $mq['state'] = $place['state'];
        if ($text !== '') $mq['q'] = $text;
        if ($cat && ($cat['words'] ?? '') !== '') $mq['q'] = trim(($mq['q'] ?? '') . ' ' . $cat['words']);
        elseif ($cat) $mq['category'] = $cat['slug'];
        if ($conds) $mq['has'] = implode(',', $conds);
        return ['chips' => array_map(fn ($c) => array_diff_key($c, ['zips' => 1]), $chips), 'text' => $text, 'top' => $top, 'groups' => $groups, 'people' => $people, 'did_you_mean' => $dym, 'did_you_mean_all' => $dyms, 'results' => $results,
                'map' => $mq ? http_build_query($mq) : null, 'area' => $area, 'elsewhere' => $elsewhere, 'fact' => $fact, 'reading' => $reading,
                'ask' => self::askHelp($q, $place) ?? self::askTool($q, $place)
                    // only a place in the words ("seattle washington", "navajo county"): what about it? (decisions/0066)
                    ?? ($place && empty($place['scope']) && empty($place['landmark']) && $text === '' && !$cat && !$sitA && !$outing ? self::placeCard($pdo, $place, $q) : null)
                    // a town's name with no state, nothing else found ("springfield"): which one?
                    ?? (!$cat && !$sitA && $text !== '' && empty($nameOfCode) && !array_filter((array) ($results['items'] ?? []), fn ($x) => ($x['kind'] ?? '') !== 'topic') ? self::whichPlace($pdo, $text) : null),
                'split' => $split,
                // a place outside our areas with no guide yet (decisions/0147): we can build one now
                'guide' => is_file(__DIR__ . '/OnDemand.php') && (!empty($place['outside']) || (!$place && $text !== '')) ? (function () use ($pdo, $place, $text, $cat) { try { require_once __DIR__ . '/OnDemand.php'; if (!empty($place['outside'])) return OnDemand::offer($pdo, $place, trim($text . ' ' . ($cat['label'] ?? ''))); $t = OnDemand::townInWords($pdo, $text); return $t ? OnDemand::offer($pdo, $t, '') : null; } catch (Throwable) { return null; } })() : null,   // a place outside our areas, or a town's name in the words with no place set ("dana point")
                'near_me' => !empty($u['near_me']) && !$place,
                // no place set, and the words are for somewhere to go (decisions/0067, 2026-10-08): "near me", "local", a
                // kind of place, a thing to do. The panel asks for a place instead of quietly searching everywhere. A
                // situation that needs one asks on its own.
                'need_place' => !$place && empty($sitA['need_place']) && (!empty($u['near_me']) || self::saysHere($q) || $cat
                    || array_filter($chips, fn ($c) => in_array($c['type'], ['do', 'why'], true))),
                'situation' => $sitA,
                // nothing read as a What (decisions/0061): say so, and offer the situations its Why words point to
                'unread' => $unread,
                'situation_near' => $near = (!$sitA && class_exists('Situations') ? (Situations::near($pdo, $q, 3, !$unread)
                    // one word that starts a situation ("found": Found someone's belongings), as the suggestions offered it while typing
                    ?: (!str_contains(trim($q), ' ') && mb_strlen(trim($q)) >= 4 ? array_slice(array_values(array_filter((array) Situations::suggest($pdo, $q, 2, true), fn ($x) => !empty($x['label']) && !empty($x['q']))), 0, 2) : [])) : []),   // a Why word ("my dog is lost somewhere"): offered above whatever else was found
                // still unread: where the words are heading, for the pause while typing ("stuck on the hig" → Stuck on the road)
                'predict' => $unread && class_exists('Situations') ? array_values(array_filter(array_map(fn ($x) => ['label' => (string) $x['label'], 'q' => (string) $x['q'], 'crumb' => (string) ($x['crumb'] ?? '')],
                    Situations::suggest($pdo, $q)), fn ($x) => !in_array($x['label'], array_column($near, 'label'), true))) : [],
                'parse' => ['why' => $sitA['labels'] ?? ($whyC ? [$whyC['label']] : []), 'what' => $cat ? $cat['label'] : ($doC ? (string) $doC['value'] : $text),
                            'attrs' => $attrs, 'where' => $place['label'] ?? null, 'when' => $whenC['label'] ?? null, 'who' => $whoC['label'] ?? ($top && ($top['kind'] ?? '') === 'listing' ? $top['label'] : null)],   // "near me" with no place set: offer to set one
                'intent' => $doC ? ['do' => (string) $doC['value'], 'about' => implode(' ', $boost), 'place' => $place['label'] ?? null] : null,   // "help" alone: which help (decisions/0060)
                // learned from what people open after these words (decisions/0061, batch 3): Published leads, May also help follows
                // what people opened after these words is for internal improvement (Admin → Search learning, where a person
                // decides what to teach), never shown as information (decisions/0093, Jason 2026-10-15)
                'learned' => [],
                // recognized or not (decisions/0063 §2): the words search doesn't know, and whether the whole ask was read
                'lexicon' => (function () use ($pdo, $q, $unread) { try { require_once __DIR__ . '/Lexicon.php'; return ['unknown' => Lexicon::unknown($pdo, $q), 'unread' => $unread]; } catch (Throwable) { return ['unknown' => [], 'unread' => $unread]; } })()];
    }

    /** Ranked "Did you mean…?" searches, each with the situation it would answer when it names one. */
    /**
     * The kinds of place words mean (decisions/0067), with how many are in the place and in all: [['label', 'q', 'here',
     * 'all', 'via']]. Only kinds that have something listed.
     */
    public static function meant(PDO $pdo, string $words, ?array $place, int $max = 4, array $also = []): array
    {
        if (!is_file(__DIR__ . '/Concepts.php')) return [];
        require_once __DIR__ . '/Concepts.php';
        // a town or a state's name ("washington") is a place, not a kind of business
        if (self::isPlaceName($pdo, $words)) return [];
        $ks = Concepts::kinds($pdo, $words, $max + 12);   // all of staff's kinds for the words; the best $max kept below
        // what people who typed these words went on to open (decisions/0067; 0093, 2026-10-15): a hint only, never a ranking
        // factor ("usage reinforces trends more than solves", and taps can be gamed). A kind the words lead to is labeled
        // "most picked"; one they don't lead to isn't added at all. The meaning decides what's shown; clicks only label it.
        $picked = [];
        if (is_file(__DIR__ . '/Learning.php')) {
            require_once __DIR__ . '/Learning.php';
            try { $picked = Learning::picks($pdo, array_merge([$words], $also)); } catch (Throwable) {}
        }
        if ($picked) {
            $have = array_column($ks, 'id');
            try {
                $st = $pdo->prepare('SELECT id, name FROM categories WHERE is_active = 1 AND id IN (' . implode(',', array_map('intval', array_keys($picked))) . ')');
                $st->execute();
                $nm = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'name', 'id');
            } catch (Throwable) { $nm = []; }
            foreach ($picked as $cid => $n) {
                if (!isset($nm[$cid])) continue;
                $i = array_search($cid, $have, true);
                if ($i !== false) $ks[$i]['picked'] = $n;   // a label on what the words already mean; nothing added
            }
        }
        $out = [];
        foreach ($ks as $k) {
            $p = [':cid' => (int) $k['id']];
            $here = $place ? SearchIndex::whereSql($pdo, $place, null, $p) : '';
            $st = $pdo->prepare("SELECT COUNT(*) AS n" . ($here !== '' ? ", SUM(CASE WHEN $here THEN 1 ELSE 0 END) AS h" : ', 0 AS h') . " FROM search_index si WHERE si.kind = 'listing' AND si.category_id = :cid");
            $st->execute($p);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: ['n' => 0, 'h' => 0];
            if ((int) $r['n'] === 0) continue;
            $lab = SearchText::catLabel((string) $k['name']);
            // two categories that read the same ("Beer & Ale" retail and wholesale): one kind, both counted
            $dup = array_search(mb_strtolower($lab), array_map(fn ($o) => mb_strtolower($o['label']), $out), true);
            if ($dup !== false) { $out[$dup]['here'] += (int) $r['h']; $out[$dup]['all'] += (int) $r['n']; $out[$dup]['ids'][] = (int) $k['id']; $out[$dup]['sure'] = $out[$dup]['sure'] || (bool) $k['sure']; continue; }
            $out[] = ['label' => $lab, 'q' => mb_strtolower($lab), 'here' => (int) $r['h'], 'all' => (int) $r['n'], 'via' => (string) $k['via'], 'sure' => (bool) $k['sure'],
                      'ids' => [(int) $k['id']], 'picked' => (int) ($k['picked'] ?? 0), 'score' => round((float) $k['score'], 2)];
        }
        // what people opened first; then the sure ones, then the maybes; in each, the ones in the place first, keeping the
        // meaning's order otherwise
        // the kind named by the words first, then the meaning's order; among equals (staff's words give several kinds at
        // once: "mental health"), the most listed
        $named = fn ($o) => str_contains(mb_strtolower($o['label']), mb_strtolower(trim($words)));
        usort($out, fn ($a, $b) => [$b['sure'], $b['here'] > 0, $named($b), $b['score'], $b['here'], $b['all']] <=> [$a['sure'], $a['here'] > 0, $named($a), $a['score'], $a['here'], $a['all']]);
        return array_slice($out, 0, $max);
    }

    /** Whether the words are a town's or a state's name ("washington", "show low"). */
    public static function isPlaceName(PDO $pdo, string $words): bool
    {
        $wl = trim(mb_strtolower($words));
        if (isset(self::STATES[$wl])) return true;
        try { $st = $pdo->prepare('SELECT 1 FROM zip_coordinates WHERE city = ? LIMIT 1'); $st->execute([$wl]); return (bool) $st->fetchColumn(); } catch (Throwable) { return false; }
    }

    /** Listings and places whose description mentions the words, in the place first: the last resort before "nothing". */
    private static function mentions(PDO $pdo, string $words, ?array $place): array
    {
        $ws = array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($words)) ?: [], fn ($w) => mb_strlen($w) >= 4));
        if (!$ws) return [];
        $p = [];
        $or = [];
        foreach (array_slice($ws, 0, 3) as $i => $w) {
            $stem = preg_replace('/(es|s)$/', '', $w);
            $or[] = "(CONCAT(' ', LOWER(si.about), ' ') LIKE :m$i OR CONCAT(' ', LOWER(si.terms), ' ') LIKE :n$i)";
            $p[":m$i"] = '% ' . SearchText::esc($stem) . '%'; $p[":n$i"] = '% ' . SearchText::esc($stem) . '%';
        }
        $here = $place ? SearchIndex::whereSql($pdo, $place, null, $p) : '';
        $st = $pdo->prepare("SELECT si.kind, si.item_id, si.label, si.sub, si.url" . ($here !== '' ? ", COALESCE($here, 0) AS here" : ', 0 AS here')
            . " FROM search_index si WHERE si.kind IN ('listing', 'outdoors', 'story', 'experience') AND (" . implode(' OR ', $or) . ") ORDER BY here DESC, si.weight DESC LIMIT 5");
        $st->execute($p);
        return array_map(fn ($r) => ['label' => (string) $r['label'], 'sub' => SearchText::catLabel((string) $r['sub']), 'url' => (string) $r['url'], 'kind' => (string) $r['kind'],
                                     'id' => (string) $r['item_id'], 'here' => (int) $r['here'] === 1, 'peek' => $r['kind'] === 'listing'], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Offers for words nothing knows: [['q' => the words with each fixed, 'fixed' => [[typed, offered]]]], the likeliest first. */
    private static function offers(PDO $pdo, string $q): array
    {
        if (!is_file(__DIR__ . '/Spelling.php')) return [];
        require_once __DIR__ . '/Spelling.php';
        $ws = preg_split('/\s+/u', mb_strtolower(trim($q))) ?: [];
        $vocab = self::whatVocab($pdo); $out = [];
        foreach ($ws as $i => $w) {
            if (mb_strlen($w) < 4 || !preg_match('/^[a-z]+$/', $w) || isset($vocab[self::root($w)]) || self::nameHas($pdo, $w)) continue;
            $cands = [];
            if (mb_strlen($w) >= 6) for ($k = 3, $n = mb_strlen($w); $k <= $n - 3; $k++) {   // run together: the kind of place it makes
                $a = mb_substr($w, 0, $k); $b = mb_substr($w, $k);
                if (Spelling::known($a) && Spelling::known($b) && self::catHas($pdo, $a . ' ' . $b)) { $cands[] = $a . ' ' . $b; break; }
            }
            if (!Spelling::known($w)) foreach (Spelling::near($w, 3) as $c) if (isset($vocab[self::root($c)]) || self::catHas($pdo, $c)) $cands[] = $c;
            // a town's name a letter off ("flagstaf": Flagstaff), from the locations data: offered, never read as the place
            // on its own (decisions/0063, 0064)
            if (!$cands && !Spelling::known($w) && ($tn = self::townSlip($pdo, $w)) !== null) $cands[] = $tn;
            foreach (array_slice(array_values(array_unique($cands)), 0, 3) as $c) {
                $f = $ws; $f[$i] = $c;
                $out[] = ['q' => implode(' ', $f), 'score' => 0, 'fixed' => [[$w, $c]]];
            }
            if ($out) break;   // the first word that needs help
        }
        return $out;
    }

    /**
     * The town a word is a letter from (one added, missing, wrong or two swapped: "flagstaf", "sedonna"), in the pilot
     * states first, then the most people; or null. Only names that start the same way are compared (the ZIP list's index).
     */
    private static function townSlip(PDO $pdo, string $w): ?string
    {
        $w = mb_strtolower(trim($w));
        if (mb_strlen($w) < 5 || !preg_match('/^[a-z]+$/', $w)) return null;
        try {
            require_once __DIR__ . '/crawler/NpiBulk.php';
            $in = "'" . implode("','", NpiBulk::STATES) . "'";
            $st = $pdo->prepare("SELECT LOWER(TRIM(city)) AS city FROM zip_coordinates WHERE city LIKE ? GROUP BY TRIM(city), TRIM(state) ORDER BY (TRIM(state) IN ($in)) DESC, SUM(COALESCE(population, 0)) DESC LIMIT 300");
            $st->execute([SearchText::esc(mb_substr($w, 0, 3)) . '%']);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $c) {
                $c = (string) $c;
                if ($c === $w || !preg_match('/^[a-z]+$/', $c)) continue;
                $swap = false;
                if (strlen($c) === strlen($w)) for ($i = 0; $i + 1 < strlen($w); $i++) if ($w === substr($c, 0, $i) . $c[$i + 1] . $c[$i] . substr($c, $i + 2)) { $swap = true; break; }
                if ($swap || levenshtein($w, $c) === 1) return $c;
            }
        } catch (Throwable) {}
        return null;
    }

    /** The same spelling offers as this search's, for the directory's own search (2026-11-12): [['q', …]]. */
    public static function spellOffers(PDO $pdo, string $q): array { return self::didYouMean($pdo, $q); }

    private static function didYouMean(PDO $pdo, string $q, bool $typing = false): array
    {
        if (is_file(__DIR__ . '/Situations.php')) require_once __DIR__ . '/Situations.php';
        if (is_file(__DIR__ . '/WordSenses.php')) require_once __DIR__ . '/WordSenses.php';
        $out = SearchText::suggestions($pdo, $q, 5, $typing);
        // only words that fit where the typed one sits (decisions/0063): no noun where a verb belongs ("how to compost"
        // isn't "how to compass"), and none a person removed for this search
        require_once __DIR__ . '/Lexicon.php'; require_once __DIR__ . '/SuggestionFlags.php';
        $tok = Lexicon::tokens($q);
        $out = array_values(array_filter($out, function ($d) use ($pdo, $tok) {
            foreach ($d['fixed'] as [$typed, $cand]) {
                // a real word is never "corrected" (decisions/0064): "prepare" isn't a slip for "propane"
                require_once __DIR__ . '/WordFamilies.php';
                if (WordFamilies::partsOf((string) $typed) !== '' || WordFamilies::partsOf(WordFamilies::singular((string) $typed)) !== '') return false;   // "hills" too, not only "hill"
                if (is_file(__DIR__ . '/LocalWords.php')) { require_once __DIR__ . '/LocalWords.php'; if (LocalWords::knows((string) $typed)) return false; }   // a word used here (decisions/0157)
                $i = array_search($typed, $tok, true); if ($i !== false && !Lexicon::fitsSlot($pdo, $tok, (int) $i, (string) $cand)) return false;
            }
            return true;
        }));
        // never neither (decisions/0064: "a slip still gets help"; Jason, 2026-11-10: "Fixed or spelling offered"): a word
        // nothing knows with no offer yet gets the closest words our directory uses, and words run together the kind of
        // place they make ("icecream": ice cream). Offered only, never changed.
        if (!$out) $out = self::offers($pdo, $q);
        // two real words a letter from a thing ("seal lions": sea lions): offered first
        if (($ps = self::phraseSlip($q)) && !in_array($ps['q'], array_column($out, 'q'), true)) array_unshift($out, $ps);
        $out = array_slice(SuggestionFlags::filter($pdo, $q, $out, 'q'), 0, 3);
        foreach ($out as &$d) {
            $d['situation'] = null;
            if (class_exists('Situations')) { try { $s = Situations::read($pdo, $d['q']); if ($s) $d['situation'] = (string) $s[0]['label']; } catch (Throwable) {} }
        }
        unset($d);
        return $out;
    }

    /**
     * Where the thing searched for is, near a place that has none: the nearest towns that have it (within 200 mi of
     * the place, nearest first), each with how many and the nearest few, each with its point for the map.
     * ['place', 'what', 'towns' => [['town', 'miles', 'count', 'items' => [['id', 'label', 'sub', 'lat', 'lon', 'miles']]]]]
     */
    // ---- Tier 1: suggestions as you type (decisions/0060) -----------------------------------------------------

    /**
     * Things people go and do, with the ways they say them (decisions/0060, the Why read as an action). "-ing" words count
     * on their own ("fishing bait"); a base word needs a going-word before it ("go fish", "want to hike").
     */
    private const ACTIVITIES = [
        'fishing' => ['fishing', 'fly fishing', 'fly-fishing', 'angling', 'fish'], 'hiking' => ['hiking', 'hike', 'hikes', 'backpacking'],
        'camping' => ['camping', 'camp', 'camp out', 'glamping'], 'boating' => ['boating', 'boat', 'kayaking', 'kayak', 'canoeing', 'canoe', 'paddling', 'paddle boarding'],
        'hunting' => ['hunting', 'hunt'], 'swimming' => ['swimming', 'swim'], 'stargazing' => ['stargazing', 'stargaze'],
        'skiing' => ['skiing', 'ski', 'snowboarding', 'snowboard'], 'horseback riding' => ['horseback riding', 'trail ride', 'trail riding', 'horseback'],
        'biking' => ['mountain biking', 'biking', 'bike ride', 'cycling'], 'golf' => ['golfing', 'golf'], 'picnic' => ['picnicking', 'picnic'],
        'birding' => ['birding', 'bird watching', 'birdwatching'], 'rock climbing' => ['rock climbing', 'climbing', 'bouldering'], 'off-roading' => ['off-roading', 'off roading', 'four wheeling', 'atv'],
        // on the river and in the snow (Jason, 2026-10-15: "tubing" is also an activity, not only pipe)
        'sledding' => ['snow tubing', 'tubing hill', 'sledding hill', 'sledding', 'sled hill', 'sled'],
        'tubing' => ['river tubing', 'tube the river', 'tubing the river', 'float the river', 'floating the river', 'river float', 'tubing'],
        'rafting' => ['white water rafting', 'whitewater rafting', 'river rafting', 'rafting', 'whitewater'],
    ];
    /** Whether the words around an activity word give it its other meaning ("copper tubing": pipe). */
    private static function actNot(string $act, string $q): bool
    {
        return isset(self::ACT_NOT[$act]) && (bool) preg_match(self::ACT_NOT[$act], ' ' . mb_strtolower($q) . ' ');
    }

    /** Words that make an activity word mean something else: "copper tubing" is pipe, not a float down the river. */
    private const ACT_NOT = [
        'tubing' => '/\b(?:copper|pvc|pex|cpvc|steel|stainless|aluminum|brass|vinyl|plastic|rubber|silicone|poly|hose|hoses|pipe|pipes|piping|plumbing|plumber|fitting|fittings|hydraulic|medical|oxygen|aquarium|inch|in|mm|ft|feet|foot|diameter|od|id)\b|\d/u',
    ];
    private const GOING = '(?:i\'?m |we\'?re |i am |we are )?(?:go|going|goes|went|gonna|wanna|want to|wants to|would like to|like to|love to|plan to|planning to|planning on|heading|headed|let\'?s|lets|let us|take (?:the |my |our )?(?:kids|family|dog|wife|husband|friends)|where (?:can|to|do|should)(?: i| we)?|place to|places to|spot to|somewhere to|good|best|to)';

    /** The words places use for each activity: a hike is at a trail, fishing at a lake (searched as any of them). */
    private const ACT_WORDS = [
        'fishing' => 'fishing lake lakes pond river reservoir stream', 'hiking' => 'hiking trail trails trailhead', 'camping' => 'camping campground campgrounds camp rv',
        'boating' => 'boating boat lake marina kayak canoe', 'hunting' => 'hunting game', 'swimming' => 'swimming pool pools aquatic beach',
        'stargazing' => 'stargazing observatory astronomy dark sky', 'skiing' => 'skiing ski snowboard', 'horseback riding' => 'horseback riding stables trail rides',
        'biking' => 'biking bike trail trails cycling', 'golf' => 'golf', 'picnic' => 'picnic day use park', 'birding' => 'birding wildlife refuge',
        'rock climbing' => 'climbing', 'off-roading' => 'off-road ohv atv jeep',
        'tubing' => 'tubing river float rafting', 'rafting' => 'rafting river whitewater', 'sledding' => 'sledding sled tubing ski skiing',
    ];

    /** Kinds of place and things to do, offered as you type ("spr": Springs, Hot springs). */
    private const TAGS = ['Springs' => 'Natural features', 'Hot springs' => 'Natural features', 'Arches' => 'Natural features', 'Badlands' => 'Natural features',
        'Buttes' => 'Natural features', 'Canyons' => 'Natural features', 'Caves' => 'Natural features', 'Craters' => 'Natural features', 'Lakes' => 'Natural features',
        'Waterfalls' => 'Natural features', 'Peaks' => 'Natural features', 'Mesas' => 'Natural features', 'Sand dunes' => 'Natural features', 'Lava fields' => 'Natural features',
        'Rivers' => 'Natural features', 'Rock formations' => 'Natural features',
        'Petroglyphs' => 'Natural features',   // public sites (Petroglyph National Monument, Newspaper Rock); results keep the visibility rules (decisions/0058 §26)
        'Boating' => 'Things to do', 'Camping' => 'Things to do', 'Campgrounds' => 'Things to do', 'Fishing' => 'Things to do', 'Hiking' => 'Things to do', 'Trails' => 'Things to do',
        'Hunting' => 'Things to do', 'Swimming' => 'Things to do', 'Stargazing' => 'Things to do', 'Skiing' => 'Things to do', 'Horseback riding' => 'Things to do',
        'Museums' => 'Things to do', 'Historic sites' => 'Things to do', 'Landmarks' => 'Things to do', 'Parks' => 'Things to do', 'Picnic areas' => 'Things to do', 'Golf' => 'Things to do',
        'Tubing' => 'Things to do', 'Rafting' => 'Things to do', 'Sledding' => 'Things to do'];

    /** Words that ask for the platform's own help, typed alone ("help", "hel", "support"). */
    private const PLATFORM_HELP = ['help', 'help me', 'help center', 'support', 'i need help', 'need help'];

    /**
     * The question back when "help" stands alone: help using Traversence, or help near the place.
     * $prefix: the words may be on their way to it ("hel"). ['q', 'options' => [['label', 'url'|'q']]] or null.
     */
    public static function askHelp(string $q, ?array $place, bool $prefix = false): ?array
    {
        $q = trim(mb_strtolower($q));
        $hit = false;
        foreach (self::PLATFORM_HELP as $w) if ($q === $w || ($prefix && mb_strlen($q) >= 3 && str_starts_with($w, $q))) { $hit = true; break; }
        if (!$hit) return null;
        $where = $place ? PlaceScope::publicName((string) $place['label']) : '';
        return ['q' => 'Are you looking for…', 'options' => [
            ['label' => 'Help using Traversence', 'sub' => 'Help Center and FAQ', 'url' => '/user/dashboard.php#help'],
            ['label' => 'Help ' . ($where !== '' ? 'in ' . $where : 'near you'), 'sub' => 'Assistance, services and support', 'q' => 'get help' . ($where !== '' ? ' in ' . $where : '')],
        ]];
    }

    /**
     * A place on its own (the search names only where): what would you like to know about it? Each choice is a search
     * there; "Make it my place" changes the set location (only when they choose to get local). A town whose name is used
     * in other states ("springfield") also offers those: "Not this one?". ['kind' => 'place', 'q', 'options', 'alts']
     */
    public static function placeCard(PDO $pdo, array $place, string $typed = ''): ?array
    {
        $label = trim((string) ($place['label'] ?? ''));
        if ($label === '') return null;
        $short = trim(explode('·', $label)[0]);
        if (!empty($place['is_state'])) $short .= ' state';   // "things to do in washington state", never Washington, DC
        $opts = [
            ['label' => 'Things to do', 'sub' => 'Outdoors, culture, places to go', 'q' => 'things to do in ' . $short],
            ['label' => 'Places to eat', 'sub' => 'Restaurants, cafés, local food', 'q' => 'restaurants in ' . $short],
            ['label' => 'Places to stay', 'sub' => 'Hotels, lodges, camping', 'q' => 'places to stay in ' . $short],
            ['label' => 'Moving there', 'sub' => 'Movers, storage, utilities, places to live', 'q' => 'moving to ' . $short],
            ['label' => 'Plan a trip', 'sub' => 'Where, when, how long and what', 'url' => '/discovery/?view=plan' . (!empty($place['slug']) && ($pp = ['cluster' => 'cluster', 'geo_hub' => 'geo', 'hub' => 'hub'][$place['level'] ?? ''] ?? null) ? '&' . $pp . '=' . rawurlencode((string) $place['slug']) : '')],
        ];
        // get local: make it the set place (in our areas, the area; outside them, the town or county itself)
        $set = !empty($place['slug']) ? ['scope' => ['level' => (string) $place['level'], 'slug' => (string) $place['slug'], 'label' => $short]]
             : array_filter(['city' => (string) ($place['city'] ?? ''), 'state' => (string) ($place['state'] ?? ''), 'zip' => empty($place['city']) && !empty($place['zips']) ? (string) $place['zips'][0] : '']);
        if ($set) $opts[] = ['label' => 'Make it my place', 'sub' => 'Get local: search here by default', 'set' => $set + ['label' => $short]];
        // the same town name in other states, when no state was typed
        $alts = [];
        if (!empty($place['city']) && !empty($place['state']) && !preg_match('/\b' . preg_quote(mb_strtolower((string) $place['state']), '/') . '\b|' . preg_quote(mb_strtolower((string) (array_search($place['state'], self::STATES, true) ?: '#')), '/') . '/u', mb_strtolower($typed))) {
            try {
                $st = $pdo->prepare('SELECT TRIM(state) AS state, COUNT(*) AS n FROM zip_coordinates WHERE city = ? AND state <> ? GROUP BY TRIM(state) ORDER BY n DESC LIMIT 6');
                $st->execute([(string) $place['city'], (string) $place['state']]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $alts[] = ['label' => ucwords(mb_strtolower((string) $place['city'])) . ', ' . $r['state'], 'q' => mb_strtolower((string) $place['city']) . ', ' . mb_strtolower((string) $r['state'])];
            } catch (Throwable) {}
        }
        return ['kind' => 'place', 'q' => 'What would you like to know about ' . $short . '?', 'options' => $opts, 'alts' => $alts, 'place' => $short];
    }

    /** Towns by that name in several states, when the words are only a name: ['kind' => 'place', 'q', 'options'] or null. */
    public static function whichPlace(PDO $pdo, string $text): ?array
    {
        $t = trim(mb_strtolower($text));
        if ($t === '' || substr_count($t, ' ') > 2 || mb_strlen($t) < 3) return null;
        try {
            $t1 = preg_replace(['/^st\.? /', '/^mt\.? /', '/^ft\.? /'], ['saint ', 'mount ', 'fort '], $t);
            $st = $pdo->prepare('SELECT TRIM(state) AS state, TRIM(city) AS city, COUNT(*) AS n FROM zip_coordinates WHERE ' . self::squashSql('city') . ' = ? GROUP BY TRIM(state), TRIM(city) ORDER BY n DESC LIMIT 8');
            $st->execute([self::squash($t1)]);   // whatever the punctuation or spacing ("coeur d alene")
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) { return null; }
        $sp = isset(self::STATES[$t]) ? self::stateNamed($pdo, self::STATES[$t]) : null;   // "washington": the state first
        if (!$rows) return $sp ? self::placeCard($pdo, $sp, $text) : null;
        // one town by that name ("seattle"): its place card straight away
        if (count($rows) === 1 && !$sp && ($p1 = self::placeNamed($pdo, mb_strtolower((string) $rows[0]['city']), (string) $rows[0]['state']))) return self::placeCard($pdo, $p1, $text . ' ' . $rows[0]['state']);
        $name = ucwords(mb_strtolower((string) $rows[0]['city']));
        $opts = array_map(fn ($r) => ['label' => $name . ', ' . $r['state'], 'sub' => (array_search($r['state'], self::STATES, true) ? ucwords((string) array_search($r['state'], self::STATES, true)) : $r['state']), 'q' => mb_strtolower($name) . ', ' . mb_strtolower((string) $r['state'])], $rows);
        if ($sp) array_unshift($opts, ['label' => $sp['label'] . ' (the state)', 'sub' => 'State', 'q' => $t . ' state']);
        return ['kind' => 'place', 'q' => count($rows) > 1 ? 'Which ' . $name . '?' : 'Did you mean ' . $name . ', ' . $rows[0]['state'] . '?', 'options' => $opts, 'alts' => []];
    }

    /** After Enter: a tool the words ask for, offered above the results ("planning a trip" → the Trip Planner). */
    private static function askTool(string $q, ?array $place): ?array
    {
        $opts = [];
        foreach (self::tools($q, $place) as $t) if ($t['lead']) $opts[] = ['label' => $t['item']['label'], 'sub' => preg_replace('/^Tool · /', '', $t['item']['crumb']), 'url' => $t['item']['url']];
        return $opts ? ['q' => 'Are you looking for…', 'options' => $opts] : null;
    }

    /**
     * The site's own tools, offered when the words ask for one ("planning", "plan a trip", "road trip" → the Trip Planner).
     * 'strong' words always mean the tool; 'words' mean it when they are most of the search ("planning", "my plans"), and
     * otherwise it's offered after what's in the place ("planning and zoning" is still the office).
     */
    private const TOOLS = [
        'trip-planner' => ['label' => 'Trip Planner', 'sub' => 'Plan a trip: where, when, how long and what you\'re after', 'url' => '/discovery/?view=plan',
            'strong' => ['trip', 'trips', 'planner', 'itinerary', 'itineraries', 'vacation', 'vacations', 'getaway', 'getaways', 'roadtrip', 'holiday', 'holidays'],
            'words' => ['plan', 'plans', 'planning', 'planned', 'visit', 'visiting', 'travel', 'traveling', 'travelling']],
    ];
    private const TOOL_FILL = ['a', 'an', 'the', 'my', 'our', 'to', 'for', 'me', 'i', 'we', 'help', 'with', 'road', 'day', 'weekend', 'family', 'next', 'this', 'up'];

    /** Tools the words ask for: [['item' => suggestion row, 'lead' => bool]]. $prefix: the last word may still be being typed. */
    public static function tools(string $q, ?array $place, bool $prefix = false): array
    {
        $toks = preg_split('/[^\p{L}0-9]+/u', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY);
        if (!$toks) return [];
        $out = [];
        foreach (self::TOOLS as $t) {
            $all = array_merge($t['strong'], $t['words']);
            $strong = false; $any = false; $rest = 0;
            foreach ($toks as $i => $w) {
                $last = $prefix && $i === count($toks) - 1 && mb_strlen($w) >= 4;
                $isS = in_array($w, $t['strong'], true) || ($last && array_filter($t['strong'], fn ($k) => str_starts_with($k, $w)));
                $isW = in_array($w, $t['words'], true) || ($last && array_filter($all, fn ($k) => str_starts_with($k, $w)));
                if ($isS) $strong = true;
                if ($isS || $isW) $any = true;
                elseif (!in_array($w, self::TOOL_FILL, true)) $rest++;
            }
            if (!$any) continue;
            $url = $t['url'];
            if ($place && !empty($place['level']) && !empty($place['slug'])) $url .= '&' . (['cluster' => 'cluster', 'geo_hub' => 'geo', 'hub' => 'hub'][$place['level']] ?? 'cluster') . '=' . rawurlencode((string) $place['slug']);
            $out[] = ['item' => ['label' => $t['label'], 'crumb' => 'Tool · ' . $t['sub'], 'url' => $url, 'kind' => 'tool', 'here' => true], 'lead' => $strong || $rest === 0];
        }
        return $out;
    }

    /**
     * Direct hits for what's typed so far: names and categories that start with it, in the person's place first, then
     * beyond it, nearest first. Each has a place › kind line and opens its page. The place someone set isn't changed.
     * ['items' => [['label', 'crumb', 'url', 'kind', 'here', 'peek'?, 'id'?]], 'here' => bool any in the place, 'place' => label]
     */
    /**
     * How well a suggestion's name matches what was typed: 3 the whole name ("phoenix": Phoenix), 2 the typed words whole
     * at its start ("four corners": Four Corners Monument), 1 plus the share typed when it only starts that way
     * ("phoenix" is 7 of Phoenixville's 12 letters: 1.58), 0 otherwise (a kind it means). One for the place you're in
     * ("plum" in Show Low: Plumbers) adds 1.
     */
    public static function matchScore(string $label, string $q, bool $here = false, string $kind = '', string $track = ''): float
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N} ]+/u', ' ', mb_strtolower(explode(',', explode(' · ', $label)[0])[0]))));
        $typed = trim((string) preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N} ]+/u', ' ', mb_strtolower($q))));
        if ($name === '' || $typed === '') return 0.0;
        if ($name === $typed) $s = 3.0;
        elseif (str_starts_with($name . ' ', $typed . ' ')) $s = 2.0;
        elseif (str_starts_with($name, $typed)) $s = 1.0 + mb_strlen($typed) / max(1, mb_strlen($name));
        else $s = 0.0;
        // the page decides what leads, among names that match (the Chameleon Filter, decisions/0058 §19; Jason, 2026-11-11):
        // places, the outdoors, stories and culture on Let's Explore; businesses and their kinds on Get Local
        $fits = $track === 'explore' ? in_array($kind, ['place', 'outdoors', 'story', 'group', 'knowledge', 'tag', 'experience', 'culture'], true)
              : ($track === 'local' ? in_array($kind, ['listing', 'category', 'food'], true) : false);
        return $s + ($here && $s > 0 ? 1.0 : 0.0) + ($fits && $s > 0 ? 0.5 : 0.0);
    }

    /**
     * Suggestions in order of matchScore, the given order kept among equals. "Here" counts only with a place set or named:
     * with none, every kind of business was "here" and "pho" led with Photographers over Phoenix (2026-11-11).
     */
    private static function byMatch(array $items, string $q, string $track = '', bool $placed = true): array
    {
        $k = [];
        foreach (array_values($items) as $i => $it) $k[] = [self::matchScore((string) ($it['label'] ?? ''), $q, $placed && !empty($it['here']), (string) ($it['kind'] ?? ''), $track), $i, $it];
        usort($k, fn ($a, $b) => [$b[0], $a[1]] <=> [$a[0], $b[1]]);
        return array_column($k, 2);
    }

    /**
     * What a person may want with a thing the words name (Jason, 2026-11-11: "Sea Lions is a thing so what information would
     * someone be wanting? To see, to care for, to learn more, instead a blank search"). By what the thing is, from WordNet
     * (WordFamilies: creature, pet, food…; else word-senses.php: p plant, o natural object), never a word list of our own:
     * [label, what it finds, the search it runs]. {pl} and {sg} are the thing, plural and singular; {w} as typed (food and
     * medicine are often counted as one: "ice cream", never "ice creams").
     */
    private const THING_WAYS = [
        'creature' => [['Where to see {pl}', 'places that mention them: wildlife areas, parks, preserves', '{pl}'],
                       ['Wildlife sanctuaries, zoos and aquariums', 'to see {pl} up close', 'wildlife sanctuaries'],
                       ['Help a hurt or stranded animal', 'animal rescue and wildlife rehab', 'animal rescue'],
                       ['Learn about {pl}', 'museums and nature centers', 'museums']],
        'pet' => [['Adopt a {sg}', 'animal shelters and adoptions', 'animal shelters'], ['Care for a {sg}', 'veterinarians, grooming and boarding', 'veterinarians'],
                  ['Pet supplies', 'food and gear for a {sg}', 'pet supplies']],
        'food' => [['Where to eat {w}', 'restaurants and places that serve it', '{w}'], ['Where to buy {w}', 'grocery stores and markets', 'grocery stores']],
        'vehicle' => [['{Sg} repair', 'shops that fix them', '{sg} repair'], ['{Sg} rental', 'rent one', '{sg} rental'], ['{Sg} dealers', 'buy one', '{sg} dealers']],
        'device' => [['{Sg} repair', 'shops that fix them', '{sg} repair'], ['Where to buy a {sg}', 'stores that sell them', '{sg}']],
        'meds' => [['Pharmacies', 'where to get {w}', 'pharmacy']],
        'p' => [['Where to see {pl}', 'gardens, preserves and trails that mention them', '{pl}'], ['Gardens', 'to see {pl} growing', 'gardens'], ['Garden centers', 'to buy {pl}', 'garden centers']],
        'o' => [['Where to see {pl}', 'the outdoors and places that mention them', '{pl}']],
    ];

    /**
     * The thing the words name and what it is: ['words' as typed, 'sg', 'pl', 'what' => a THING_WAYS key], or null. A
     * two- or three-word thing anywhere in the words ("sea lion preserves": sea lion); one word only when it's all there is.
     */
    private static function thingIn(string $q): ?array
    {
        require_once __DIR__ . '/WordFamilies.php';
        $ws = array_values(array_filter(preg_split('/\s+/u', trim(mb_strtolower($q))) ?: [], fn ($w) => preg_match('/^[a-z\'-]+$/', $w)));
        if (!$ws || count($ws) > 4) return null;
        $whatOf = function (string $ph): string {
            $f = (string) WordFamilies::family($ph);
            if (isset(self::THING_WAYS[$f])) return $f;
            $sn = SearchText::thingSense($ph);
            if ($sn === '' && !str_contains($ph, ' ')) { static $sx = null; if ($sx === null) { $fx = __DIR__ . '/../data/word-senses.php'; $sx = is_file($fx) ? (array) require $fx : []; } $sn = (string) ($sx[$ph] ?? $sx[WordFamilies::singular($ph)] ?? ''); }
            $k = $sn !== '' ? (str_contains($sn, 'f') ? 'food' : $sn[0]) : '';   // a food among its senses reads as food (jalapeno, not the plant)
            return isset(self::THING_WAYS[$k]) ? $k : '';
        };
        $found = null;
        foreach ([3, 2] as $len) for ($i = 0; $i + $len <= count($ws) && !$found; $i++) {
            $ph = implode(' ', array_slice($ws, $i, $len));
            if (SearchText::thingSense($ph) === '' && WordFamilies::family($ph) === null) continue;
            if (($k = $whatOf($ph)) !== '') $found = [$ph, $k];
        }
        if (!$found && count($ws) === 1 && mb_strlen($ws[0]) >= 3 && ($k = $whatOf($ws[0])) !== '') $found = [$ws[0], $k];
        if (!$found) return null;
        [$ph, $k] = $found;
        // a food named for an animal is its food ("dog food", "cat food"): the pet's ways (supplies, vets), not a restaurant
        if ($k === 'food' && str_contains($ph, ' ') && in_array(WordFamilies::family(explode(' ', $ph)[0]), ['pet', 'creature'], true)) { $k = 'pet'; $ph = explode(' ', $ph)[0]; }
        $parts = explode(' ', $ph); $last = (string) end($parts);
        $sgLast = WordFamilies::singular($last);
        $sg = implode(' ', array_merge(array_slice($parts, 0, -1), [$sgLast]));
        if ($sgLast !== $last) $pl = $ph;
        else {
            $plLast = preg_match('/[^aeiou]y$/', $last) ? mb_substr($last, 0, -1) . 'ies' : (preg_match('/(s|x|z|ch|sh)$/', $last) ? $last . 'es' : $last . 's');
            $pl = implode(' ', array_merge(array_slice($parts, 0, -1), [$plLast]));
        }
        return ['words' => $ph, 'sg' => $sg, 'pl' => $pl, 'what' => $k];
    }

    /** The ways to a thing the words name, as intents: [['label', 'means', 'q', 'kind' => 'thing']]. */
    private static function thingWays(string $q): array
    {
        $t = self::thingIn($q);
        if (!$t) return [];
        $fill = fn (string $s) => strtr($s, ['{pl}' => $t['pl'], '{sg}' => $t['sg'], '{w}' => $t['words'], '{Sg}' => ucfirst($t['sg']), '{Pl}' => ucfirst($t['pl'])]);
        return array_map(fn ($w) => ['label' => ucfirst($fill($w[0])), 'means' => $fill($w[1]), 'q' => $fill($w[2]), 'kind' => 'thing'], self::THING_WAYS[$t['what']]);
    }

    /**
     * Two real words, one letter from a thing ("seal lions": sea lions), offered, never changed (decisions/0064 note,
     * 2026-11-11). Only the first word of the pair is read as the slip, so the thing the person named stays theirs ("red car"
     * is never a "red cap"). ['q', 'score', 'fixed' => [[typed, offered]]], or null.
     */
    public static function phraseSlip(string $q): ?array
    {
        $ws = preg_split('/\s+/u', trim(mb_strtolower($q))) ?: [];
        for ($i = 0; $i + 1 < count($ws); $i++) {
            [$a, $b] = [$ws[$i], $ws[$i + 1]];
            if (!preg_match('/^[a-z]{3,}$/', $a) || !preg_match('/^[a-z]{3,}$/', $b) || SearchText::thingSense("$a $b") !== '') continue;
            $n = strlen($a); $cands = [];
            for ($k = 0; $k < $n; $k++) $cands[] = substr($a, 0, $k) . substr($a, $k + 1);   // a letter too many (seal: sea)
            for ($k = 0; $k + 1 < $n; $k++) $cands[] = substr($a, 0, $k) . $a[$k + 1] . $a[$k] . substr($a, $k + 2);   // two swapped
            foreach (range('a', 'z') as $ch) {
                for ($k = 0; $k < $n; $k++) $cands[] = substr($a, 0, $k) . $ch . substr($a, $k + 1);   // one wrong
                for ($k = 0; $k <= $n; $k++) $cands[] = substr($a, 0, $k) . $ch . substr($a, $k);   // one missing
            }
            foreach ($cands as $c) {
                if ($c === $a || strlen($c) < 2 || SearchText::thingSense("$c $b") === '') continue;
                $f = $ws; $f[$i] = $c;
                return ['q' => implode(' ', $f), 'score' => 0, 'fixed' => [[$a, $c]]];
            }
        }
        return null;
    }

    /**
     * The intents a word being typed may serve, as full words with their context (Jason, 2026-11-11: "Partial word,
     * suggests a full word, that suggestion has the context matching potential intent"; "What we hold seems to be a gate
     * to information"; "Navajo, Hopi, and Apache are words first"). From sources, not from what we hold: a country or
     * state and its people (app/data/demonyms.php, from Wikidata: Mexican, Mexico), a tribal nation (the nations table,
     * its public side only, decisions/0066), the U.S. town or county it names. A people or a nation brings its food and its
     * culture; what we hold only orders and fills the results. [['label', 'means', 'q', 'kind']], places first.
     */
    private static function intents(PDO $pdo, string $q, array $done): array
    {
        $tk = preg_split('/\s+/u', mb_strtolower(trim($q))) ?: [];
        $code = mb_strlen(trim($q)) === 2 && in_array(strtoupper(trim($q)), self::STATES, true);   // "pa": a state's code
        if (count($tk) > 2 || (mb_strlen(trim($q)) < 3 && !$code) || !preg_match('/^[\p{L} ]+$/u', trim(mb_strtolower($q)))) return [];
        $typed = trim(mb_strtolower($q));
        static $dem = null;
        if ($dem === null) { $f = __DIR__ . '/../data/demonyms.php'; $dem = is_file($f) ? (array) require $f : ['peoples' => [], 'places' => []]; }
        require_once __DIR__ . '/crawler/NpiBulk.php';
        $abbr = array_flip(self::STATES);
        $places = []; $peoples = []; $seen = [];
        $starts = fn (string $name) => (bool) preg_match('/(^| )' . preg_quote($typed, '/') . '/u', $name);   // the typed letters start one of its words
        // a state's two-letter code on its own ("pa"): the state, offered
        if (mb_strlen($typed) === 2 && ($sc = array_search(strtoupper($typed), self::STATES, true)) !== false) {
            $places[] = ['label' => ucwords((string) $sc), 'means' => in_array(strtoupper($typed), NpiBulk::STATES, true) ? 'a state to explore' : 'a state', 'q' => $sc . ' state', 'kind' => 'place', 'ours' => true];
            return array_map(fn ($x) => array_diff_key($x, ['ours' => 1]), $places);   // two letters: the state, nothing more
        }
        // a state's code before other words ("AZ a", "nm hik"): the state, offered (never assumed)
        if (count($tk) === 2 && mb_strlen($tk[0]) === 2 && ($sc2 = array_search(strtoupper($tk[0]), self::STATES, true)) !== false) {
            $places[] = ['label' => ucwords((string) $sc2), 'means' => in_array(strtoupper($tk[0]), NpiBulk::STATES, true) ? 'a state to explore' : 'a state', 'q' => $sc2 . ' state', 'kind' => 'place', 'ours' => true];
        }
        // a kind of place, in OpenStreetMap's own words ("dog park", "playground", "water park"): the obvious one first
        $kinds = [];
        if (is_file(__DIR__ . '/OpenPlaces.php')) {
            require_once __DIR__ . '/OpenPlaces.php';
            foreach (OpenPlaces::tagWords() as $tw => $kk) {
                if (!str_starts_with($tw, $typed) || mb_strlen($typed) < 3 || count($kinds) >= 2) continue;
                $label = ucfirst($tw) . (str_ends_with($tw, 's') ? '' : 's');
                $kinds[] = ['label' => $label, 'means' => mb_strtolower((string) OpenPlaces::KINDS[$kk][0]) . ', nearest first', 'q' => $tw, 'kind' => 'kindof'];
            }
        }
        // countries and states, and the peoples whose word is being typed ("mexi": Mexico, New Mexico; Mexican)
        foreach ((array) $dem['places'] as $pl => $ws) {
            if (!$starts((string) $pl)) continue;
            $isState = isset(self::STATES[$pl]);
            $places[] = ['label' => ucwords((string) $pl), 'means' => $isState ? (in_array(self::STATES[$pl], NpiBulk::STATES, true) ? 'a state to explore' : 'a state') : 'a country', 'q' => $isState ? $pl . ' state' : (string) $pl, 'kind' => 'place', 'ours' => $isState && in_array(self::STATES[$pl], NpiBulk::STATES, true)];
            $seen[(string) $pl] = 1;
        }
        // a country's people (a U.S. state's people read oddly as a cuisine: "Arizonan food"), and its country with it ("iri": Ireland)
        foreach ((array) $dem['peoples'] as $pw => $pls) {
            $pls = array_values(array_filter((array) $pls, fn ($x) => !isset(self::STATES[mb_strtolower((string) $x)])));
            if (!$pls || !$starts((string) $pw) || str_ends_with((string) $pw, 's') || count($peoples) >= 2) continue;
            $peoples[(string) $pw] = $pls;
            foreach ($pls as $cn) if (!isset($seen[$k = mb_strtolower((string) $cn)])) { $seen[$k] = 1; $places[] = ['label' => (string) $cn, 'means' => 'a country', 'q' => $k, 'kind' => 'place', 'ours' => false]; }
        }
        // tribal nations by name: their public side (the nation, its visitor information), decisions/0066
        if (class_exists('Nations') || is_file(__DIR__ . '/Nations.php')) {
            require_once __DIR__ . '/Nations.php';
            try {
                foreach (Nations::all($pdo) as $n) {
                    $nm = mb_strtolower((string) $n['name']);
                    if (!$starts($nm) || isset($seen[$nm])) continue;
                    $seen[$nm] = 1;
                    $places[] = ['label' => (string) $n['name'], 'means' => 'a tribal nation: its land and visitor information', 'q' => $nm, 'kind' => 'place', 'ours' => true];
                    if (preg_match('/(^| )(' . preg_quote($typed, '/') . '\p{L}*)/u', $nm, $m) && count($peoples) < 2) $peoples[$m[2]] = $peoples[$m[2]] ?? [(string) $n['name']];
                }
            } catch (Throwable) {}
        }
        // the U.S. town and county it names, the one near the person first
        foreach (array_unique(array_merge([$typed], array_map(fn ($d) => mb_strtolower(trim((string) $d)), $done))) as $w) {
            if (!$starts($w) || mb_strlen($w) < 4 || isset($seen[$w])) continue;
            try {
                // a town outside our areas whose name starts a name we hold, typed out ("four corners": Four Corners Monument
                // over Four Corners, WY, decisions/0058 §25): the town stays among the places below, not ahead of the name
                $p0 = self::placeNamed($pdo, $w, null, true, true);
                if ($p0 && empty($p0['url']) && str_contains($w, ' ') && self::namesSomething($pdo, $w)) $p0 = null;
                if ($p0 && !isset($seen[$k = mb_strtolower((string) $p0['label'])])) {
                    $seen[$k] = 1; $st = (string) ($p0['state'] ?? '');
                    $places[] = ['label' => (string) $p0['label'], 'means' => 'a town' . ($st !== '' && isset($abbr[$st]) ? ' in ' . ucwords((string) $abbr[$st]) : ''), 'q' => mb_strtolower((string) $p0['label']), 'kind' => 'place', 'ours' => !empty($p0['url'])];
                }
                $cs = $pdo->prepare('SELECT TRIM(county) AS county, TRIM(state) AS state FROM zip_coordinates WHERE county LIKE ? GROUP BY TRIM(county), TRIM(state) ORDER BY COUNT(*) DESC LIMIT 2');
                $cs->execute([SearchText::esc($w) . '%']);
                foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $c) {
                    $cl = ucwords(mb_strtolower((string) $c['county'])) . ' County, ' . $c['state'];
                    if (isset($seen[$k = mb_strtolower($cl)])) continue; $seen[$k] = 1;
                    $places[] = ['label' => $cl, 'means' => 'a county', 'q' => mb_strtolower($cl), 'kind' => 'place', 'ours' => in_array((string) $c['state'], NpiBulk::STATES, true)];
                }
            } catch (Throwable) {}
        }
        // ours first among places (a state we cover, a nation, our towns), then the rest as found
        usort($places, fn ($a, $b) => (empty($b['ours']) ? 0 : 1) <=> (empty($a['ours']) ? 0 : 1));
        // a people brings its food and its culture
        $more = [];
        foreach ($peoples as $pw => $pls) {
            $more[] = ['label' => ucwords($pw) . ' food', 'means' => 'restaurants and ' . ucwords($pw) . ' cuisine', 'q' => $pw . ' food', 'kind' => 'food'];
            $more[] = ['label' => ucwords($pw) . ' culture', 'means' => 'heritage, history, museums, cultural centers', 'q' => $pw . ' culture', 'kind' => 'culture'];
        }
        return array_map(fn ($x) => array_diff_key($x, ['ours' => 1]), array_slice(array_merge($kinds, array_slice($places, 0, 4), $more), 0, 7));
    }

    public static function suggest(PDO $pdo, string $q, array $ctx = []): array
    {
        $q = SearchText::spell(trim(preg_replace('/\s+/', ' ', mb_strtolower($q))));   // "theatre" reads as "theater" (names match both)
        $out = ['items' => [], 'here' => false, 'place' => null];
        if (mb_strlen($q) < 3) return $out;   // suggestions wait for three letters (Jason, 2026-11-11)
        // "near me" and its kin say where, not what (decisions/0067, 2026-10-08): searched without them, so "me" is never
        // Maine or "medical"; the words before them are finished. One still being typed ("near m") is offered whole.
        self::$near = self::nearFrom($pdo, $ctx);
        $q0 = $q;
        $nm = self::nearMe($q);
        if ($nm['near'] || $nm['partial'] !== '') {
            $q = $nm['text']; $ctx['end'] = true;
            $out['near_me'] = $nm['near'];
            if ($nm['partial'] !== '') foreach (self::NEAR_ME_SAY as $say) if (str_starts_with($say, $nm['partial'])) {
                $out['near_complete'] = ['q' => trim($q . ' ' . $say), 'typed' => $q0, 'rest' => mb_substr(trim($q . ' ' . $say), mb_strlen($q0))];
                break;
            }
            if (mb_strlen($q) < 2) { if (!empty($out['near_complete'])) $out['complete'] = [$out['near_complete']]; unset($out['near_complete']); return $out; }
        }
        $place = self::placeFromScope($pdo, $ctx);
        // no place set, but the words name one ("moving to seattle washington"): that's the place while typing too, as
        // it is after Enter, so a situation answers there instead of asking where
        // a place named in the words is the place for these words, set place or not (decisions/0066): "seattle
        // washington" with St. Johns set answers for Seattle; the set place doesn't change
        $onlyPlace = null;
        // a last word of two letters still being typed that's also a state's code ("dog pa": park, or Pennsylvania?) isn't
        // assumed to be the state: the words are read without it, and "in Pennsylvania" is offered (Jason, 2026-11-11: "if an
        // abbreviation is possible maybe ask if they are looking for the state? Or in a state?")
        $askState = null;
        if (empty($ctx['end']) && preg_match('/^(.+)\s([a-z]{2})$/u', $q, $sm2) && !str_contains($q, ',') && ($sn2 = array_search(strtoupper($sm2[2]), self::STATES, true)) !== false) {
            $askState = ['words' => trim($sm2[1]), 'state' => (string) $sn2];
        }
        if (mb_strlen($q) >= 4) { try { [$wp, $wrest] = self::findPlace($pdo, $askState ? $askState['words'] : $q); if ($wp && empty($wp['landmark'])) { $place = $wp; if (trim((string) $wrest) === '') $onlyPlace = $wp; } } catch (Throwable) {} }
        $out['place'] = $place['label'] ?? null;
        $items = [];
        // "help" on its own could be either: ask (decisions/0060). "Legal help" isn't asked: it's local.
        if (mb_strlen($q) >= 3 && ($ask = self::askHelp($q, $place, true))) $out['ask'] = $ask;
        elseif ($onlyPlace && ($pc = self::placeCard($pdo, $onlyPlace, $q))) $out['ask'] = $pc;   // only a place: what about it?
        $tools = mb_strlen($q) >= 4 ? self::tools($q, $place, true) : [];   // the site's own tools (the Trip Planner)
        if (!class_exists('SearchIndex') || !SearchIndex::ready($pdo)) { $out['items'] = $items; return $out; }
        $esc = SearchText::esc($q);
        $c = $place ? self::centerOf($pdo, $place) : null;
        // the last word may still be being typed (no space after it yet): what the words most likely are once it's
        // finished ("live mu" is live music, "self stor" self storage), from the words that lead somewhere, the commonest
        // first (decisions/0067, 2026-10-07). Read for what they mean, names and what Enter would find.
        $fin = [];
        if (empty($ctx['end']) && !$onlyPlace && is_file(__DIR__ . '/Concepts.php') && !self::isPlaceName($pdo, $q) && empty($wp)) { require_once __DIR__ . '/Concepts.php'; try { $fin = Concepts::finish($pdo, $q, 3); } catch (Throwable $e) { error_log('suggest finish: ' . $e->getMessage()); } }   // a place being named isn't a word to finish
        $fq = $fin[0] ?? $q;
        // what the words mean (decisions/0067): "xrays" → Diagnostic Imaging Centers, "cardio" → gyms, "live mu" → Music &
        // Live Entertainment, with how many are in the place. The words without the place in them ("x-rays in show low").
        // What people opened after the same words leads. The sure ones order everything below (step 3: rank by meaning).
        $meantRows = []; $sureL = []; $sureIds = [];
        $thingWays = [];
        if (mb_strlen($q) >= 3 && !$onlyPlace) {
            $rest0 = !empty($wp) && isset($wrest) && trim((string) $wrest) !== '' ? trim((string) preg_replace('/\s+(in|near|around|at|by)$/u', '', trim((string) $wrest))) : '';
            $phr = $rest0 !== '' ? [$rest0] : ($fin ?: [$q]);
            try {
                foreach ($phr as $pi => $ph) foreach (self::meant($pdo, $ph, $place, 3, $rest0 !== '' ? [] : [$q]) as $m) {
                    if (isset($meantRows[$k0 = mb_strtolower($m['label'])])) continue;
                    // the second way to finish it: only what it surely means, when the first means nothing for sure; never the third
                    if ($pi > 0 && (!$m['sure'] || $sureL || $pi > 1)) continue;
                    $meantRows[$k0] = $m + ['for' => $ph];
                    if ($m['sure'] && $pi === 0) { $sureL[$k0] = 1; $sureIds[] = $m['ids']; }   // the likeliest words order the rest, kind by kind
                    if (count($meantRows) >= 4) break 2;
                }
            } catch (Throwable $e) { error_log('suggest meant: ' . $e->getMessage()); }
        }
        $nameRows = function (string $qq) use ($pdo, $place, $c): array {
            $e = SearchText::esc($qq);
            $p = [':a' => $e . '%', ':b' => '% ' . $e . '%'];
            $wide = str_contains(trim($qq), ' ') && mb_strlen(trim($qq)) >= 8;
            // both spellings of a two-spelling word ("theatre" finds Roxy Theater, "theater" finds El Rio Theatre)
            $alsoSql = '';
            foreach (array_slice(SearchText::spellings($qq), 1) as $i => $alt) {
                $ea = SearchText::esc($alt);
                $p[':sa' . $i] = $ea . '%'; $p[':sb' . $i] = '% ' . $ea . '%';
                $alsoSql .= ' OR si.label' . SearchText::CI . ' LIKE :sa' . $i . ' OR si.label' . SearchText::CI . ' LIKE :sb' . $i;
            }
            $here = $place ? SearchIndex::whereSql($pdo, $place, null, $p) : '';
            $sql = "SELECT si.kind, si.item_id, si.label, si.sub, si.url, si.city, si.state, si.lat, si.lon, si.private_location, si.weight"
                 . ($here !== '' ? ", COALESCE($here, 0) AS here" : ', 0 AS here')
                 . " FROM search_index si WHERE si.kind IN ('listing', 'outdoors', 'story', 'group', 'experience') AND (si.label" . SearchText::CI . " LIKE :a OR si.label" . SearchText::CI . " LIKE :b" . $alsoSql . ")"
                 // a name typed out (two words or more) that starts a name we have is offered wherever it is: "four corners" reads as
                 // Four Corners, WY, and Four Corners Monument is 600 miles from there (Jason, 2026-11-10)
                 . ($c ? ' AND (si.lat IS NULL OR (si.lat BETWEEN :la1 AND :la2 AND si.lon BETWEEN :lo1 AND :lo2)' . ($wide ? ' OR si.label' . SearchText::CI . ' LIKE :w' : '') . ')' : '')
                 . ' ORDER BY here DESC, (si.label' . SearchText::CI . ' LIKE :a2) DESC, si.weight DESC LIMIT 60';
            $p[':a2'] = $e . '%';
            if ($c && $wide) $p[':w'] = $e . '%';
            if ($c) { $d = 250 / 69.0; $p += [':la1' => $c['lat'] - $d, ':la2' => $c['lat'] + $d, ':lo1' => $c['lon'] - $d * 1.3, ':lo2' => $c['lon'] + $d * 1.3]; }
            try { $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { error_log('suggest: ' . $e->getMessage()); return []; }
        };
        $rows = $nameRows($q);
        // finished words are whole words in a name too: "pond near" is Pond Creek, never Ponderosa Meadow Apartments
        // (Jason, 2026-10-15). Only when some name has them whole; otherwise the start of a word still finds it.
        // Still being typed, whole words come first ("pond": Pond Hollow before Ponderosa).
        if ($rows) {
            $alts = array_map(fn ($w) => preg_quote($w, '/'), SearchText::spellings($q) ?: [$q]);
            $wholeRe = '/(?<![\p{L}\p{N}])(?:' . implode('|', $alts) . ')(?![\p{L}\p{N}])/iu';
            $wr = array_values(array_filter($rows, fn ($r) => (bool) preg_match($wholeRe, (string) $r['label'])));
            if (!empty($ctx['end'])) { if ($wr) $rows = $wr; }
            elseif ($wr) $rows = array_merge($wr, array_values(array_filter($rows, fn ($r) => !preg_match($wholeRe, (string) $r['label']))));
        }
        $mq = $q;   // the words the names were matched with
        if (!$rows && $fq !== $q && ($rows = $nameRows($fq))) $mq = $fq;
        // no name has all the words, and some of them are words search doesn't know ("ford f150"): the names with the
        // words it does know ("ford"), and the kinds of place those names are (decisions/0066, 2026-10-07)
        $known = '';
        // (not when the words surely mean a kind of place: "live mu" isn't names with "live" in them)
        if (!$rows && str_contains($q, ' ') && !$sureL) {
            try {
                require_once __DIR__ . '/Lexicon.php';
                $unk0 = Lexicon::unknown($pdo, $q);
                if ($unk0) {
                    $kw = array_values(array_filter(explode(' ', $q), fn ($w) => !in_array($w, $unk0, true)));
                    if ($kw && mb_strlen($k2 = implode(' ', $kw)) >= 3 && ($rows = $nameRows($k2))) $known = $k2;
                }
            } catch (Throwable $e) { error_log('suggest known: ' . $e->getMessage()); }
        }
        // no name has the whole phrase ("hearing test"): what Enter would find, read by the 5 W's (What: hearing), as the
        // predictions, so there's something to tap (Jason, 2026-10-02: a lone "Press Enter" row is a gate). Search logs
        // nothing; only what's kept is counted (the panel's outcome beacon).
        $ahead = [];
        if (!$rows && str_contains($q, ' ') && mb_strlen($q) >= 5) {
            try {
                $full = self::search($pdo, $fq, $ctx + ['no_side' => 1]);   // while typing: nothing sent to the crawler
                foreach (array_merge($full['results']['items'] ?? [], $full['results']['beyond']['items'] ?? []) as $it) {
                    if (in_array($it['kind'] ?? '', ['topic', 'resource'], true) || empty($it['url'])) continue;
                    // as far as names are matched while typing (250 mi, above): "dana point harb" isn't Harbor Freight 443 mi off
                    if (($it['miles'] ?? null) !== null && (float) $it['miles'] > 250) continue;
                    $ahead[] = ['label' => (string) $it['label'], 'crumb' => (string) ($it['sub'] ?? ''), 'url' => (string) $it['url'], 'kind' => (string) $it['kind'],
                                'here' => ($it['miles'] ?? null) === null, 'peek' => !empty($it['peek']), 'id' => (string) ($it['id'] ?? '')];
                    if (count($ahead) >= 5) break;
                }
                // nothing in the place: the nearest towns that have it (the "elsewhere" answer)
                foreach (($full['elsewhere']['towns'] ?? []) as $tw) foreach ($tw['items'] ?? [] as $it) {
                    if (count($ahead) >= 5) break 2;
                    $ahead[] = ['label' => (string) $it['label'], 'crumb' => trim((string) $tw['town'] . ' › ' . SearchText::catLabel((string) ($it['sub'] ?? '')) . ' · ' . (int) $it['miles'] . ' mi', ' ›'),
                                'url' => '/listing/view.php?id=' . (int) $it['id'], 'kind' => 'listing', 'here' => false, 'peek' => true, 'id' => (string) $it['id']];
                }
                // and the kinds it read the words as ("Hearing aids"): all of that kind
                foreach ($full['results']['items'] ?? [] as $it) {
                    if (($it['kind'] ?? '') !== 'topic' || count($ahead) >= 7) continue;
                    $ahead[] = ['label' => SearchText::catLabel((string) $it['label']), 'crumb' => ($place ? $place['label'] . ' › ' : '') . 'All of this kind', 'q' => mb_strtolower(SearchText::catLabel((string) $it['label'])), 'kind' => 'category', 'here' => true];
                }
            } catch (Throwable $e) { error_log('suggest ahead: ' . $e->getMessage()); }
        }
        $kindName = ['listing' => 'Listings', 'outdoors' => 'Outdoors', 'story' => 'Stories', 'group' => 'Groups', 'experience' => 'Experiences'];
        $hits = [];
        foreach ($rows as $r) {
            $isHere = (int) $r['here'] === 1;
            $miles = null;
            if (!$isHere && $c && $r['lat'] !== null && !(int) $r['private_location']) $miles = SearchIndex::miles($c['lat'], $c['lon'], (float) $r['lat'], (float) $r['lon']);
            $what = SearchText::catLabel(trim(explode(' · ', (string) $r['sub'])[0])) ?: $kindName[$r['kind']];
            $where = $isHere && $place ? (string) $place['label'] : ((int) $r['private_location'] ? '' : trim((string) $r['city'] !== '' ? ucwords(mb_strtolower((string) $r['city'])) : ''));
            $lab = preg_replace('/\b(\w[\w\s]{2,40}?) \1$/iu', '$1', trim((string) $r['label']));   // "Dump Station Dump Station"
            $hits[] = ['label' => $lab, 'what' => $what, 'lat' => (int) $r['private_location'] ? null : $r['lat'], 'lon' => (int) $r['private_location'] ? null : $r['lon'], 'crumb' => trim(($where !== '' ? $where . ' › ' : '') . $what . ($miles !== null ? ' · ' . ($miles < 1 ? '<1' : (string) round($miles)) . ' mi' : '')),
                       'url' => (string) $r['url'], 'kind' => (string) $r['kind'], 'here' => $isHere, 'miles' => $miles,
                       'peek' => $r['kind'] === 'listing', 'id' => (string) $r['item_id'], 'start' => str_starts_with(mb_strtolower((string) $r['label']), $known !== '' ? $known : $mq),
                       // of the kind the words mean; and the words whole in the name ("music" isn't Musick)
                       'agree' => $r['kind'] === 'listing' && isset($sureL[mb_strtolower($what)]),
                       'whole' => (bool) preg_match('/(^|[^\p{L}])' . preg_quote((string) preg_replace('/^.* /', '', $known !== '' ? $known : $mq), '/') . '([^\p{L}]|$)/iu', (string) $r['label'])];
        }
        // the words mean a kind of place: its listings, in the place first, lead the names (Exit Mobile DJ for "live mu")
        if ($sureIds && count(array_filter($hits, fn ($h) => $h['agree'] && $h['here'])) < 3) {
            $haveU = array_column($hits, 'url');
            $left = 5;
            foreach ($sureIds as $gi => $ids) {   // the first kind meant gets the most room
                if ($left <= 0) break;
                try {
                    $p2 = [];
                    $here2 = $place ? SearchIndex::whereSql($pdo, $place, null, $p2) : '';
                    $st = $pdo->prepare("SELECT si.kind, si.item_id, si.label, si.sub, si.url, si.city, si.lat, si.lon, si.private_location" . ($here2 !== '' ? ", COALESCE($here2, 0) AS here" : ', 0 AS here')
                        . " FROM search_index si WHERE si.kind = 'listing' AND (si.category_id IN (" . ($idl = implode(',', array_map('intval', array_unique($ids)))) . ')'
                        // also listed under it (decisions/0073): shown after the ones whose primary category it is
                        . (SearchIndex::hasAlso($pdo) ? implode('', array_map(fn ($x) => " OR si.also_cats LIKE '% " . (int) $x . " %'", array_unique($ids))) : '') . ')'
                        . ($c ? ' AND (si.lat IS NULL OR (si.lat BETWEEN :la1 AND :la2 AND si.lon BETWEEN :lo1 AND :lo2))' : '') . " ORDER BY (si.category_id IN ($idl)) DESC, here DESC, si.weight DESC LIMIT " . ($gi === 0 ? 3 : 2));
                    if ($c) { $d2 = 250 / 69.0; $p2 += [':la1' => $c['lat'] - $d2, ':la2' => $c['lat'] + $d2, ':lo1' => $c['lon'] - $d2 * 1.3, ':lo2' => $c['lon'] + $d2 * 1.3]; }
                    $st->execute($p2);
                    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                        if (in_array((string) $r['url'], $haveU, true)) continue;
                        $haveU[] = (string) $r['url']; $left--;
                        $isHere = (int) $r['here'] === 1;
                        $miles = !$isHere && $c && $r['lat'] !== null && !(int) $r['private_location'] ? SearchIndex::miles($c['lat'], $c['lon'], (float) $r['lat'], (float) $r['lon']) : null;
                        $what = SearchText::catLabel(trim(explode(' · ', (string) $r['sub'])[0]));
                        $where = $isHere && $place ? (string) $place['label'] : ((int) $r['private_location'] ? '' : ucwords(mb_strtolower(trim((string) $r['city']))));
                        $hits[] = ['label' => trim((string) $r['label']), 'what' => $what, 'lat' => null, 'lon' => null, 'crumb' => trim(($where !== '' ? $where . ' › ' : '') . $what . ($miles !== null ? ' · ' . ($miles < 1 ? '<1' : (string) round($miles)) . ' mi' : '')),
                                   'url' => (string) $r['url'], 'kind' => 'listing', 'here' => $isHere, 'miles' => $miles, 'peek' => true, 'id' => (string) $r['item_id'], 'start' => false, 'agree' => true, 'whole' => false, 'rank' => $gi];
                    }
                } catch (Throwable $e) { error_log('suggest kind listings: ' . $e->getMessage()); }
            }
        }
        // of the kind the words mean before names that only share a word (decisions/0067, 2026-10-07)
        usort($hits, fn ($a, $b) => [!$a['here'], !$a['agree'], $a['rank'] ?? 0, $a['here'] ? 0 : ($a['miles'] ?? 9999), !$a['start']] <=> [!$b['here'], !$b['agree'], $b['rank'] ?? 0, $b['here'] ? 0 : ($b['miles'] ?? 9999), !$b['start']]);
        // many names that start the same way ("Big Lake Dam…", "Big Lake Boating…", "Big Lake Visitor Center…"): one row
        // for them all, which searches the name, instead of a screenful of the same words (Jason, 2026-10-02)
        $nq = count(explode(' ', $known !== '' ? $known : $q)); $grp = [];
        foreach ($hits as $i => $h) {
            if (!$h['start']) continue;
            $lw = preg_split('/[\s\-\/,:]+/u', mb_strtolower($h['label']), -1, PREG_SPLIT_NO_EMPTY);
            if (count($lw) <= $nq) continue;   // the name is just the words: it is the place itself
            $grp[implode(' ', array_slice($lw, 0, $nq))][] = $i;
        }
        $groups = [];
        foreach ($grp as $key => $ix) {
            if (count($ix) < 3) continue;
            // one spot (a lake, a park, a forest site): all within about 5 miles of each other. "Pizza …" and "St Johns …"
            // are many businesses, not one place
            $pts = array_filter(array_map(fn ($i) => $hits[$i]['lat'] !== null ? [(float) $hits[$i]['lat'], (float) $hits[$i]['lon']] : null, $ix));
            if (count($pts) < 3) continue;
            $far2 = false; $p1 = reset($pts);
            foreach ($pts as $pt) if (SearchIndex::miles($p1[0], $p1[1], $pt[0], $pt[1]) > 5) { $far2 = true; break; }
            if ($far2) continue;
            try { $ck = $pdo->prepare('SELECT 1 FROM categories WHERE name' . SearchText::CI . ' LIKE ? LIMIT 1'); $ck->execute([SearchText::esc($key) . '%']); if ($ck->fetchColumn()) continue; } catch (Throwable) {}
            if (self::placeNamed($pdo, $key, null, true)) continue;
            $ms = array_filter(array_map(fn ($i) => $hits[$i]['miles'], $ix), fn ($m) => $m !== null);
            $whats = array_slice(array_values(array_unique(array_map(fn ($i) => $hits[$i]['what'], $ix))), 0, 3);
            $any = $hits[$ix[0]]; $allHere = !array_filter($ix, fn ($i) => !$hits[$i]['here']);
            $nm = implode(' ', array_slice(preg_split('/[\s\-\/,:]+/u', $any['label'], -1, PREG_SPLIT_NO_EMPTY), 0, $nq));
            $groups[] = ['label' => $nm, 'crumb' => count($ix) . ' places' . ($whats ? ': ' . implode(', ', $whats) : '') . ($ms ? ' · ' . ($allHere ? '' : round(min($ms)) . (round(max($ms)) > round(min($ms)) ? '–' . round(max($ms)) : '') . ' mi') : ''),
                         'q' => $key, 'kind' => 'group', 'here' => $allHere, 'count' => count($ix)];
            foreach ($ix as $i) $hits[$i]['grouped'] = true;
        }
        $hits = array_values(array_filter($hits, fn ($h) => empty($h['grouped'])));
        // read from the words it knows ("ford" in "ford f150"): what kind of place those names mostly are, all of it
        if ($known !== '' && $hits) {
            $whats = array_count_values(array_filter(array_map(fn ($h) => $h['kind'] === 'listing' ? (string) $h['what'] : '', $hits)));
            arsort($whats);
            foreach (array_slice($whats, 0, 2, true) as $wt => $wn) $items[] = ['label' => $wt, 'crumb' => ($place ? $place['label'] . ' › ' : '') . 'What “' . $known . '” names are', 'q' => mb_strtolower($wt), 'kind' => 'category', 'here' => true];
        }
        // categories that start with the words: a way to see all of them, in the place
        $bizIntent = null;
        try {
            $nm = SearchText::catNameSql($pdo, 'categories');   // its plain name too ("gyms" → Gyms & Fitness Centers)
            $st = $pdo->prepare('SELECT id, name, ' . $nm . ' AS plain FROM categories WHERE is_active = 1 AND (name' . SearchText::CI . ' LIKE ? OR ' . $nm . SearchText::CI . ' LIKE ?) ORDER BY parent_id IS NULL DESC, name LIMIT 16');
            $st->execute([$esc . '%', $esc . '%']);
            // a finished word ("trail near", "trail ") is a whole word: Trailers isn't one; still being typed, whole words
            // come first and the rest after ("trail": Trails before Trailers) (Jason, 2026-10-15)
            $cats = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $whole = (bool) preg_match('/^' . preg_quote($q, '/') . '(?![\p{L}\p{N}])/iu', (string) $r['name']) || (bool) preg_match('/^' . preg_quote($q, '/') . '(?![\p{L}\p{N}])/iu', (string) $r['plain']);
                if (!$whole && !empty($ctx['end'])) continue;
                $cats[] = [(string) $r['name'], $whole, (int) $r['id']];
            }
            usort($cats, fn ($a, $b) => $b[1] <=> $a[1]);
            // one word being typed, with a place: the kind of business it starts that has the most listings here is a full
            // word with its intent ("plum" in Show Low: Plumbers, 4 here), offered with the other full words, ahead of a
            // town of that name in another state. A word the directory already knows whole ("trail") only by its whole name.
            if ($place && $cats && !str_contains($q, ' ') && empty($ctx['end'])) {
                $kw = isset(self::whatVocab($pdo)[self::root($q)]) ? array_values(array_filter($cats, fn ($x) => $x[1])) : $cats;
                if ($kw) {
                    $pk = [];
                    $hereK = SearchIndex::whereSql($pdo, $place, null, $pk);
                    if ($hereK !== '') {
                        $ck = $pdo->prepare('SELECT si.category_id, COUNT(*) AS n FROM search_index si WHERE si.kind = \'listing\' AND si.category_id IN (' . implode(',', array_map(fn ($x) => (int) $x[2], array_slice($kw, 0, 8))) . ") AND $hereK GROUP BY si.category_id ORDER BY n DESC LIMIT 1");
                        $ck->execute($pk);
                        if (($top0 = $ck->fetch(PDO::FETCH_ASSOC)) && (int) $top0['n'] > 0) {
                            foreach ($kw as $x) if ($x[2] === (int) $top0['category_id']) {
                                $bl = SearchText::catLabel($x[0]);
                                $bizIntent = ['label' => $bl, 'means' => (int) $top0['n'] . ' here in ' . $place['label'], 'q' => mb_strtolower($bl), 'kind' => 'business'];
                                break;
                            }
                        }
                    }
                }
            }
            foreach (array_slice(array_column($cats, 0), 0, 4) as $cn) $items[] = ['label' => SearchText::catLabel((string) $cn), 'crumb' => ($place ? $place['label'] . ' › ' : '') . 'All of this kind', 'q' => mb_strtolower(SearchText::catLabel((string) $cn)), 'kind' => 'category', 'here' => true];
        } catch (Throwable) {}
        // what the words mean (worked out above): sure kinds lead, maybes after ("Maybe?")
        foreach ($meantRows as $m) {
            $cw = (string) $m['for'];
            $named = str_contains(mb_strtolower($m['label']), $cw);
            $items[] = ['label' => $m['label'], 'crumb' => ($m['sure'] ? '' : 'Maybe? ') . ($place ? $place['label'] . ' › ' . ($m['here'] ? $m['here'] . ' here' : 'none here yet · ' . $m['all'] . ' listed in all') : $m['all'] . ' listed')
                        . (!empty($m['picked']) ? ' · most picked for “' . $q . '”' : ($named ? '' : ' · for “' . $cw . '”')), 'q' => $m['q'], 'kind' => 'category', 'here' => true, 'maybe' => true, 'sure' => $m['sure']];   // left out when a situation answers
        }
        // kinds of place and things to do (the spec's tags: "spr" is Springs as well as Springerville, "boa" is Boating):
        // each searches for all of them, starting in the place
        $n = 0;
        foreach (self::TAGS as $tag => $group) {
            if (!str_starts_with(mb_strtolower($tag), $q) && !preg_match('/ ' . preg_quote($q, '/') . '/u', ' ' . mb_strtolower($tag))) continue;
            $items[] = ['label' => $tag, 'crumb' => ($place ? $place['label'] . ' › ' : '') . $group, 'q' => mb_strtolower($tag), 'kind' => 'tag', 'here' => true];
            if (++$n >= 4) break;
        }
        // towns and counties that start with the words, anywhere in the country (decisions/0066, 2026-10-07): offered
        // while typing, never switched to (decisions/0060). Ours first (a town area by its own name too, "Round Valley"),
        // then the rest by how many ZIP codes they have, the bigger towns first. One outside our areas searches its name
        // with its state ("seattle, wa"), which asks what about it.
        try {
            $seen = [];
            if ($place) $seen[mb_strtolower((string) $place['label'])] = 1;
            require_once __DIR__ . '/crawler/NpiBulk.php';
            $in = "'" . implode("','", NpiBulk::STATES) . "'";
            // states: "wash" offers Washington, the state (its searches say "state": never Washington, DC)
            // two letters are enough: the start of its name or its postal code ("ok", "az")
            $qs0 = preg_replace('/\s+state$/', '', $q);
            $stM = array_filter(self::STATES, fn ($sa, $sn) => str_starts_with($sn, $qs0) || (strlen($qs0) === 2 && $sa === strtoupper($qs0)), ARRAY_FILTER_USE_BOTH);
            uksort($stM, fn ($a, $b) => [$stM[$a] !== strtoupper($qs0), $a] <=> [$stM[$b] !== strtoupper($qs0), $b]);   // its code first ("ne": Nebraska, then New…)
            $stM = array_slice($stM, 0, 5, true);
            foreach ($stM as $sn => $sa) {
                if (isset($seen[$sn])) continue;
                $seen[$sn] = 1;
                $ours0 = in_array($sa, NpiBulk::STATES, true);
                $items[] = ['label' => ucwords($sn), 'crumb' => 'State' . ($ours0 ? '' : ' · no listings yet'), 'q' => $sn . ' state', 'kind' => 'place', 'here' => false];
            }
            $townsLike = function (string $pre) use ($pdo, $in): array {
                $st = $pdo->prepare("SELECT TRIM(city) AS city, TRIM(state) AS state, COUNT(*) AS n, AVG(latitude) AS lat, AVG(longitude) AS lon FROM zip_coordinates WHERE city LIKE ? GROUP BY TRIM(city), TRIM(state) ORDER BY (TRIM(state) IN ($in)) DESC, n DESC, SUM(population) DESC, city LIMIT 24");
                $st->execute([SearchText::esc($pre) . '%']);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                // the name exactly as typed first ("phoenix" is Phoenix, not Phoenixville), then the one near the person
                $pl = mb_strtolower(trim($pre));
                $key = fn ($r) => [mb_strtolower((string) $r['city']) === $pl ? 1 : 0, self::$near && $r['lat'] !== null && self::mi(self::$near[0], self::$near[1], (float) $r['lat'], (float) $r['lon']) <= 150 ? 1 : 0];
                $ix = array_flip(array_keys($rows));
                uksort($rows, fn ($i, $j) => [$key($rows[$j]), $ix[$i]] <=> [$key($rows[$i]), $ix[$j]]);
                return array_values($rows);
            };
            $towns = mb_strlen($q) >= 3 ? $townsLike($q) : [];
            // finished words name a whole town: "trail near" is Trail, OR, never Traill (2026-10-15)
            $wholeName = fn (string $name, string $w): bool => (bool) preg_match('/^' . preg_quote($w, '/') . '(?![\p{L}\p{N}])/iu', $name);
            if (!empty($ctx['end'])) $towns = array_values(array_filter($towns, fn ($t) => $wholeName((string) $t['city'], $q)));
            else usort($towns, fn ($a, $b) => $wholeName((string) $b['city'], $q) <=> $wholeName((string) $a['city'], $q));   // whole names first, the order kept within
            foreach ($pdo->query('SELECT name FROM micro_clusters')->fetchAll(PDO::FETCH_COLUMN) as $nm) {
                $nm = PlaceScope::publicName((string) $nm);
                if (!preg_match('/^ZIP \d/', $nm) && str_starts_with(mb_strtolower($nm), $q)) array_unshift($towns, ['city' => $nm, 'state' => '']);
            }
            $ours = []; $others = [];
            foreach ($towns as $t) {
                if (count($ours) >= 4 && count($others) >= 3) break;
                $p0 = self::placeNamed($pdo, mb_strtolower((string) $t['city']), $t['state'] !== '' ? (string) $t['state'] : null, true);
                if (!$p0 || isset($seen[$k = mb_strtolower((string) $p0['label'])])) continue;
                $seen[$k] = 1;
                if (!empty($p0['url'])) { if (count($ours) < 4) $ours[] = ['label' => (string) $p0['label'], 'crumb' => trim((string) ($p0['sub'] ?? '')) ?: 'Town', 'url' => (string) $p0['url'], 'kind' => 'place', 'here' => false]; }
                elseif (count($others) < 4) $others[] = ['label' => (string) $p0['label'], 'crumb' => 'Town · see what\'s there', 'q' => $k, 'kind' => 'place', 'here' => false];
            }
            // the words end in a town being typed ("moving to sea", "pizza in flag"): the words with each town that
            // starts that way, as searches ("moving to seattle, wa")
            if (!$towns && preg_match('/^(.+?\s(?:to|in|near|around|at|from|for))\s+(\S.{2,30})$/u', $q, $tm)) {
                foreach ($townsLike(trim($tm[2])) as $t) {
                    if (count($others) >= 5) break;
                    $tl = ucwords(mb_strtolower((string) $t['city'])) . ', ' . $t['state'];
                    $p0 = self::placeNamed($pdo, mb_strtolower((string) $t['city']), (string) $t['state'], true);
                    $tq = $tm[1] . ' ' . mb_strtolower($tl);
                    if (isset($seen[$tq])) continue;
                    $seen[$tq] = 1;
                    $others[] = ['label' => $tm[1] . ' ' . $tl, 'crumb' => $p0 && !empty($p0['url']) ? 'Search there' : 'Search there · no listings yet', 'q' => $tq, 'kind' => 'place', 'here' => false];
                }
            }
            // counties: "navajo", "navajo co", "king county"
            $cw = trim((string) preg_replace('/\s+(c|co|cou|coun|count|county)$/u', '', $q));
            if (mb_strlen($cw) >= 3) {
                $st = $pdo->prepare("SELECT TRIM(county) AS county, TRIM(state) AS state, COUNT(*) AS n FROM zip_coordinates WHERE county LIKE ? GROUP BY TRIM(county), TRIM(state) ORDER BY (TRIM(county)" . SearchText::CI . " IN (?, ?)) DESC, (TRIM(state) IN ($in)) DESC, n DESC LIMIT 2");
                $st->execute([SearchText::esc($cw) . '%', $cw, $cw . ' county']);   // the name itself first ("king": King, not Kings)
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    if (!empty($ctx['end']) && !$wholeName((string) $r['county'], $cw)) continue;
                    $cn = ucwords(mb_strtolower((string) $r['county']));
                    $lab = (preg_match('/ county$/i', $cn) ? $cn : $cn . ' County') . ', ' . $r['state'];
                    if (isset($seen[$k = mb_strtolower($lab)])) continue;
                    $seen[$k] = 1;
                    $others[] = ['label' => $lab, 'crumb' => 'County · ' . (int) $r['n'] . ((int) $r['n'] === 1 ? ' ZIP code' : ' ZIP codes'), 'q' => $k, 'kind' => 'place', 'here' => false];
                }
            }
            array_push($items, ...$ours, ...$others);
        } catch (Throwable $e) { error_log('suggest towns: ' . $e->getMessage()); }
        // situations that start with what's typed lead (decisions/0061): "the power w" offers Power out
        // (only a place, "seattle washington": no situation guesses; the place card asks what about it)
        if (!$onlyPlace && is_file(__DIR__ . '/Situations.php')) { require_once __DIR__ . '/Situations.php'; try { $sitSug = Situations::suggest($pdo, $q, 3, !empty($ctx['end'])); } catch (Throwable) { $sitSug = []; } } else $sitSug = [];
        // the words already name a situation: a preview of its answer while typing, the nearest one or two for each need
        // (decisions/0061), so nobody has to press Enter to see it
        if (!$onlyPlace && ($sitSug || mb_strlen($q) >= 6)) {
            try {
                $sits = class_exists('Situations') ? Situations::read($pdo, $q) : [];
                // a situation read from the words' meaning (no phrase said it): it leads the suggestions too
                foreach ($sits as $si) {
                    if (empty($si['reasoned']) || in_array($si['label'], array_column($sitSug, 'label'), true)) continue;
                    $nl = $pdo->prepare('SELECT label FROM situation_needs WHERE situation_id = ? ORDER BY pos LIMIT 4'); $nl->execute([(int) $si['id']]);
                    array_unshift($sitSug, ['label' => (string) $si['label'], 'crumb' => implode(' · ', $nl->fetchAll(PDO::FETCH_COLUMN)), 'q' => $q, 'kind' => 'situation',
                                            'urgent' => $si['urgency'] === 'urgent', 'danger' => (bool) $si['danger'], 'here' => true]);
                }
                if ($sits && !$place) $out['preview'] = self::askPlace($pdo, $sits);
                elseif ($sits) {
                    $cc = $place ? self::centerOf($pdo, $place) : null;
                    $a = Situations::answer($pdo, $sits, $place, ['center' => $cc ? [$cc['lat'], $cc['lon']] : null, 'reach' => $place ? (string) ($ctx['reach'] ?? 'all') : 'all', 'per' => 2]);
                    if ($a && array_filter($a['needs'], fn ($n) => $n['items'])) $out['preview'] = $a + ['place' => $place['label'] ?? null];
                    // a place outside our areas with nothing listed: the same answer as after Enter (no listings yet, then
                    // OpenStreetMap and Google Maps), never a blank
                    elseif ($a && !empty($place['outside'])) $out['preview'] = $a + ['place' => $place['label'] ?? null, 'none_here' => true, 'outside' => true,
                        'center' => $cc ? [round((float) $cc['lat'], 2), round((float) $cc['lon'], 2)] : null];
                }
            } catch (Throwable $e) { error_log('suggest preview: ' . $e->getMessage()); }
        }
        // a town's name on its own that nothing on Traversence matches ("seattle"): its place card, or which one
        if (empty($out['ask']) && !$onlyPlace && !$rows && !$sitSug && mb_strlen($q) >= 4) { try { if ($wp2 = self::whichPlace($pdo, $q)) $out['ask'] = $wp2; } catch (Throwable) {} }
        // completions, Google-style (decisions/0061): what the words typed may carry on to, from the situation map and
        // from the 5 W's (a thing, then when, who or where). Tapping one searches it.
        $nc0 = $out['near_complete'] ?? null;   // "coffee near": "near me" being typed, finished below
        try {
            require_once __DIR__ . '/SuggestionFlags.php';
            $cmp = self::completions($pdo, $q, $place);
            // the word being typed, finished ("live mu" → live music, "new mus" → new museum, new music): first
            $fc = [];
            foreach ($fin as $i => $f) {
                if ($f === $q || !str_starts_with($f, $q)) continue;
                // after the likeliest, only common words ("new music", not "new musings")
                if ($i > 0 && (Concepts::rank((string) preg_replace('/^.* /', '', $f)) ?? 999999) > 20000) continue;
                $fc[] = ['q' => $f, 'typed' => $q, 'rest' => mb_substr($f, mb_strlen($q))];
            }
            $cmp = array_values(array_filter($cmp, fn ($x) => !in_array($x['q'], $fin, true)));
            $out['complete'] = SuggestionFlags::filter($pdo, $q, array_slice(array_merge($fc, $cmp), 0, 6), 'q');
            $out['maybe_try'] = SuggestionFlags::filter($pdo, $q, self::maybeTry($pdo, $q, array_column($out['complete'], 'q')), 'q');
            // "near me" said: the words' own carry-ons don't fit after it ("concerts near me open now" reads oddly); one
            // being typed: it, finished, first
            if (!empty($out['near_me'])) $out['complete'] = $out['maybe_try'] = [];
            if (!empty($out['near_complete'])) $out['complete'] = [$out['near_complete']];
            unset($out['near_complete']);
            // a word being typed, read as the full words it's becoming, each with the intent it would serve (Jason, 2026-11-11:
            // "Partial word, suggests a full word, that suggestion has the context matching potential intent")
            // "near me" being typed after a thing ("coffee near"): the thing is finished, so the full words are the thing near
            // you, and near your place when one is set; never a town or county the thing's word is also ("Coffee County")
            if ($nc0) {
                $out['intents'] = [['label' => ucfirst((string) $nc0['q']), 'means' => 'the nearest first, with how far', 'q' => (string) $nc0['q'], 'kind' => 'near']];
                if ($place && !empty($place['label']) && $q !== '') $out['intents'][] = ['label' => ucfirst($q) . ' near ' . $place['label'], 'means' => 'around your place', 'q' => $q . ' near ' . mb_strtolower((string) $place['label']), 'kind' => 'near'];
            }
            elseif (empty($out['near_me'])) {
                $out['intents'] = self::intents($pdo, $q, array_column((array) $out['complete'], 'q'));
                // an outing being typed ("place to take my k"): who's coming, finished, with what it finds
                if ($oi = self::outingIntents($q, !empty($ctx['end']))) $out['intents'] = array_merge($oi, $out['intents']);
                // the kind of business the word starts, with how many are here (above): after a kind of place the words name
                if ($bizIntent) {
                    $nk = 0; foreach ($out['intents'] as $x) { if (($x['kind'] ?? '') === 'kindof') $nk++; else break; }
                    array_splice($out['intents'], $nk, 0, [$bizIntent]);
                }
            }
            if ($askState) $out['intents'] = array_merge((array) ($out['intents'] ?? []), [['label' => '“' . $askState['words'] . '” in ' . ucwords($askState['state']) . '?', 'means' => 'search in the state', 'q' => $askState['words'] . ' in ' . $askState['state'] . ' state', 'kind' => 'place']]);
            // a thing the words name (a sea lion, a plant, a dish): what a person may want with it, when nothing surely reads the words
            // as a kind of business; for two real words a letter from a thing ("seal lions"), the thing offered
            if (empty($out['near_me']) && !$nc0 && !$onlyPlace && !$sureL) {
                $tq = ($ps0 = self::phraseSlip($q)) ? $ps0['q'] : $q;
                if ($thingWays = self::thingWays($tq)) $out['intents'] = array_merge((array) ($out['intents'] ?? []), $thingWays);
            }
            // the words already name a kind of place ("dog park"): carry-ons that read a word another way ("dog park and sleep in
            // my car", where "park" is the verb) are far reaches, so only its plural stays
            if (array_filter((array) ($out['intents'] ?? []), fn ($x) => ($x['kind'] ?? '') === 'kindof' && $x['q'] === mb_strtolower(trim($q))))
                $out['complete'] = array_values(array_filter((array) $out['complete'], fn ($c) => in_array((string) ($c['rest'] ?? ''), ['s', 'es'], true)));
        } catch (Throwable $e) { error_log('complete: ' . $e->getMessage()); }
        // recognized or not (decisions/0063 §2): the words search doesn't know, said first. The last word may still be
        // being typed, so it counts only once it's finished (a space after it).
        try {
            require_once __DIR__ . '/Lexicon.php';
            $unk = Lexicon::unknown($pdo, $q);
            // the last word may still be being typed: it waits while a word we know starts with it ("compo" → compost)
            if ($unk) { $last = end($unk); $toks = Lexicon::tokens($q); if ($last === end($toks)) { foreach (array_keys(SearchText::vocab($pdo)) as $vw) if (str_starts_with((string) $vw, $last) && $vw !== $last) { array_pop($unk); break; } } }
            $out['lexicon'] = ['unknown' => array_values($unk)];
        } catch (Throwable $e) { error_log('suggest lexicon: ' . $e->getMessage()); }
        // a finished word nothing knows: ask, never change it (the last word may still be being typed)
        if (mb_strlen($q) >= 4) { try { $out['did_you_mean'] = self::didYouMean($pdo, $q, true); } catch (Throwable $e) { error_log('suggest dym: ' . $e->getMessage()); } }
        // finishing a word comes before guessing at it (Jason, 2026-11-11: "Jala = jalapeno is a closer match than Java?"): while
        // a word offered to finish the search starts with the last word typed, no guess changes that word
        if (!empty($out['did_you_mean']) && !empty($out['complete'])) {
            $tk = Lexicon::tokens($q); $lastW = (string) end($tk);
            $finishing = $lastW !== '' && (bool) array_filter((array) $out['complete'], function ($c) use ($q, $lastW) {
                $cq = mb_strtolower((string) ($c['q'] ?? '')); $ct = preg_split('/\s+/u', $cq) ?: [];
                return str_starts_with($cq, mb_strtolower($q)) || (bool) array_filter($ct, fn ($w) => $w !== $lastW && str_starts_with($w, $lastW));
            });
            if ($finishing) $out['did_you_mean'] = array_values(array_filter((array) $out['did_you_mean'], fn ($d) => !array_filter((array) ($d['fixed'] ?? []), fn ($f) => (string) ($f[0] ?? '') === $lastW)));
        }
        // a situation already answers the words ("Cooking and baking", "Flat tire"): its own list is the answer, so no
        // kinds read from single words under it
        if ($sitSug || !empty($out['preview'])) $items = array_values(array_filter($items, fn ($x) => empty($x['maybe']) || !empty($x['sure'])));
        $platform = array_merge($sitSug, array_values(array_filter($items, fn ($x) => $x['kind'] === 'platform')));
        $rest = []; $seenL = [];
        foreach ($items as $x) {   // a tag and a category with the same name: once (what the words surely mean wins)
            if ($x['kind'] === 'platform') continue;
            if (isset($seenL[$k = mb_strtolower($x['label'])])) { if (!empty($x['sure'])) $rest[$seenL[$k]] = $x; continue; }
            $seenL[$k] = count($rest); $rest[] = $x;
        }
        // the platform's help; what's in the place; towns and kinds; then what's beyond, nearest first
        $reach = $place ? (string) ($ctx['reach'] ?? 'all') : 'all';   // the Distance slider: only the place, or within so many miles
        $local = array_values(array_filter($hits, fn ($h) => $h['here']));
        $far = $reach === 'place' || (!empty($place['outside']) && !$c) ? [] : array_values(array_filter($hits, fn ($h) => !$h['here'] && (!ctype_digit($reach) || ($h['miles'] !== null && $h['miles'] <= (int) $reach))));
        // names that match only past the Distance setting ("ford": Show Low Ford, 38 mi, with 10 mi set): never shown as
        // if they were near; one row says how far the nearest is and widens to it when tapped, as after Enter
        $widenRow = [];
        if (!$local && !$far && $place && ($reach === 'place' || ctype_digit($reach))) {
            $past = array_values(array_filter($hits, fn ($h) => !$h['here'] && $h['miles'] !== null));
            usort($past, fn ($a, $b) => $a['miles'] <=> $b['miles']);
            if ($past) {
                $m0 = $past[0]['miles']; $to = null;
                foreach ([5, 10, 25, 50, 100, 250] as $r0) if ($r0 >= $m0 && (!ctype_digit($reach) || $r0 > (int) $reach)) { $to = $r0; break; }
                if ($to) {
                    $nIn = count(array_filter($past, fn ($h) => $h['miles'] <= $to));
                    $widenRow[] = ['label' => $nIn === 1 ? $past[0]['label'] : $nIn . ' named “' . ($known !== '' ? $known : $q) . '”', 'crumb' => ($nIn === 1 ? $past[0]['what'] . ' · ' : 'Nearest ' ) . round($m0) . ' mi, past your ' . ($reach === 'place' ? 'place' : $reach . ' mi') . ' · Widen to ' . $to . ' mi',
                                   'widen' => (string) $to, 'kind' => 'widen', 'here' => false];
                }
            }
        }
        $leadT = array_column(array_filter($tools, fn ($t) => $t['lead']), 'item');
        $afterT = array_column(array_filter($tools, fn ($t) => !$t['lead']), 'item');
        // as many as match, up to 15, until Enter or a pick (Jason, 2026-10-07). While it's still one word ("for"), what
        // people most likely mean leads: places and kinds of thing, then listings and outdoor places that start with
        // it, and stories, groups and experiences (discover and community) after them
        if (!str_contains($q, ' ')) {
            $isCom = fn ($h) => in_array($h['kind'], ['story', 'group', 'experience'], true);
            $hitsT = array_values(array_filter(array_merge($local, $far), fn ($h) => !$isCom($h)));
            usort($hitsT, fn ($a, $b) => [!$a['agree'], !$a['start'], !$a['here']] <=> [!$b['agree'], !$b['start'], !$b['here']]);
            $com = array_values(array_filter(array_merge($local, $far), $isCom));
            $out['items'] = array_slice(array_merge($leadT, $platform, $rest, $widenRow, $groups, $hitsT, $ahead, $com, $afterT), 0, 15);
        } elseif ($sureL) {
            // the words mean a kind of place for sure ("live music"): that kind, then its places, then what Enter finds; names
            // that only share a word, and only whole ("music", not Musick), after the rest
            $kindRows = array_values(array_filter($rest, fn ($x) => !empty($x['sure'])));
            $rest = array_values(array_filter($rest, fn ($x) => empty($x['sure'])));
            // no place set (Everywhere): everything is near enough
            if (!$place) { $local = array_merge($local, $far); $far = []; }
            $agreeL = array_values(array_filter($local, fn ($h) => $h['agree']));
            $otherL = array_values(array_filter($local, fn ($h) => !$h['agree'] && $h['whole']));
            $farS = array_values(array_filter($far, fn ($h) => $h['agree'] || $h['whole']));
            // what Enter would find: of the kind meant, or with the words whole in the name
            $lw = (string) preg_replace('/^.* /', '', $fq);
            $agA = fn ($a) => isset($sureL[mb_strtolower(SearchText::catLabel(trim(explode(' · ', (string) preg_replace('/^.*› /', '', (string) $a['crumb']))[0])))]);
            $ahead = array_values(array_filter($ahead, fn ($a) => $agA($a) || preg_match('/(^|[^\p{L}])' . preg_quote($lw, '/') . '([^\p{L}]|$)/iu', (string) $a['label'])));
            usort($ahead, fn ($a, $b) => !$agA($a) <=> !$agA($b));   // of the kind meant first
            usort($farS, fn ($a, $b) => [!$a['agree'], $a['rank'] ?? 0, $a['miles'] ?? 9999] <=> [!$b['agree'], $b['rank'] ?? 0, $b['miles'] ?? 9999]);
            $out['items'] = array_slice(array_merge($leadT, $platform, $kindRows, $widenRow, $groups, array_slice($agreeL, 0, 6), $ahead, $rest, array_slice($otherL, 0, 3), $farS, $afterT), 0, 15);
        } else $out['items'] = array_slice(array_merge($leadT, $platform, $widenRow, $groups, array_slice($local, 0, 6), $ahead, $rest, $far, $afterT), 0, 15);
        // topics our guides know about (decisions/0133), right after the tools the words ask for
        if (mb_strlen($q) >= 3 && is_file(__DIR__ . '/Topics.php')) {
            try {
                require_once __DIR__ . '/Topics.php';
                $have = array_map(fn ($x) => mb_strtolower((string) ($x['label'] ?? '')), $out['items']);
                $kn = array_values(array_filter(Topics::suggest($pdo, $q), fn ($x) => !in_array(mb_strtolower($x['label']), $have, true)));
                if ($kn) { array_splice($out['items'], count($leadT), 0, $kn); $out['items'] = array_slice($out['items'], 0, 15); }
            } catch (Throwable $e) { error_log('suggest topics: ' . $e->getMessage()); }
        }
        // places lead while typing (Jason, 2026-11-10: "since we are a location based search we should be showing possible
        // locations as someone types"): towns, counties and states that start with the words, and "pizza in Phoenix, AZ"
        // for words ending in a town being typed, right after the site's own tools, up to five; the rest keep their order
        $pl = array_values(array_filter($out['items'], fn ($x) => ($x['kind'] ?? '') === 'place'));
        if ($pl) {
            $pl = array_slice($pl, 0, 5);
            $restI = array_values(array_filter($out['items'], fn ($x) => ($x['kind'] ?? '') !== 'place'));
            $nLead = 0; foreach ($restI as $x) { if (($x['kind'] ?? '') === 'platform' || in_array($x, $leadT, true)) $nLead++; else break; }
            $out['items'] = array_slice(array_merge(array_slice($restI, 0, $nLead), $pl, array_slice($restI, $nLead)), 0, 15);
        }
        // a tool the words ask for: no "planning open now"
        if ($leadT && !empty($out['complete'])) $out['complete'] = array_values(array_filter($out['complete'], fn ($c) => !preg_match('/ (open now|open this weekend)$/', (string) $c['q'])));
        $out['here'] = (bool) array_filter($hits, fn ($h) => $h['here']);
        // no place set, and the words are for somewhere to go: "near me", "local", a kind of place they surely mean, a thing
        // to do (decisions/0067, 2026-10-08). The panel asks for a place. A situation's preview asks on its own.
        $out['need_place'] = !$place && !$onlyPlace && empty($out['preview']['need_place']) && empty($out['preview'])
            && !array_filter($hits, fn ($h) => str_starts_with(mb_strtolower((string) $h['label']), $q) && str_contains($q, ' '))   // a business's name being typed
            && (!empty($out['near_me']) || self::saysHere($q0) || $sureL || array_filter($out['items'], fn ($x) => in_array($x['kind'] ?? '', ['category', 'tag'], true)));
        // once each: a kind can come from its name and from what the words mean ("yoga class": Yoga Instruction)
        $seenR = [];
        $out['items'] = array_values(array_filter($out['items'], function ($x) use (&$seenR) { $k = mb_strtolower((string) $x['label']) . '|' . ($x['url'] ?? $x['q'] ?? ''); if (isset($seenR[$k]) || isset($seenR[mb_strtolower((string) $x['label'])])) return false; $seenR[$k] = 1; if (($x['kind'] ?? '') === 'category') $seenR[mb_strtolower((string) $x['label'])] = 1; return true; }));
        // the most likely first (Jason, 2026-11-11: "a whole word vs partial word match for scoring … 3 letters of 8 vs 8 of
        // 8"): each suggestion scored by how its name matches what was typed, the order above kept among equals
        // the words name a thing (a sea lion): the ways to it stand in for the loose guesses at a kind of business ("Fishing Lakes &
        // Ponds" for sea lions); a kind the words surely mean stays
        if ($thingWays) $out['items'] = array_values(array_filter($out['items'], fn ($x) => !(($x['kind'] ?? '') === 'category' && str_starts_with((string) ($x['crumb'] ?? ''), 'Maybe?'))));
        // an outing being typed: kinds read from the words that only frame it ("take": Takeout) aren't what's meant; and the
        // kind of business offered with the full words isn't offered again below
        if (array_filter((array) ($out['intents'] ?? []), fn ($x) => ($x['kind'] ?? '') === 'outing')) $out['items'] = array_values(array_filter($out['items'], fn ($x) => ($x['kind'] ?? '') !== 'category'));
        if ($bizIntent && array_filter((array) ($out['intents'] ?? []), fn ($x) => $x === $bizIntent)) $out['items'] = array_values(array_filter($out['items'], fn ($x) => !(($x['kind'] ?? '') === 'category' && mb_strtolower((string) $x['label']) === mb_strtolower($bizIntent['label']))));
        $out['items'] = self::byMatch($out['items'], $q, (string) ($ctx['track'] ?? ''), $place !== null);
        // a name typed out (two words or more) that's a town outside our areas and also starts a name we hold ("four corners"):
        // the name we hold leads, the town right after it (decisions/0058 §25; CLAUDE.md, Places in search)
        if (str_contains($q, ' ')) {
            $ti = null; $ni = null;
            foreach ($out['items'] as $i => $x) {
                $xl = mb_strtolower(trim(explode(',', (string) ($x['label'] ?? ''))[0]));
                if ($ti === null && ($x['kind'] ?? '') === 'place' && empty($x['url']) && $xl === $q) $ti = $i;
                if ($ni === null && in_array($x['kind'] ?? '', ['outdoors', 'listing'], true) && str_starts_with(mb_strtolower((string) $x['label']) . ' ', $q . ' ') && mb_strtolower((string) $x['label']) !== $q) $ni = $i;
            }
            if ($ti !== null && $ni !== null && $ni > $ti) { $nx = $out['items'][$ni]; array_splice($out['items'], $ni, 1); array_splice($out['items'], $ti, 0, [$nx]); }
        }
        // the full words offered while typing, in the page's order: where to go and its culture on Let's Explore, food
        // first on Get Local; all of them on both
        if (!empty($out['intents'])) {
            $rank = ($ctx['track'] ?? '') === 'local' ? ['kindof' => -1, 'near' => -1, 'outing' => -1, 'business' => -1, 'food' => 0, 'place' => 1, 'culture' => 2, 'thing' => 3] : (($ctx['track'] ?? '') === 'explore' ? ['kindof' => -1, 'near' => -1, 'outing' => -1, 'place' => 0, 'thing' => 1, 'culture' => 2, 'food' => 3, 'business' => 4] : []);   // the kind of place the words name leads on every page
            if ($rank) {
                $its = array_values($out['intents']); $keys = array_keys($its);
                usort($keys, fn ($a, $b) => [($rank[$its[$a]['kind']] ?? 9), $a] <=> [($rank[$its[$b]['kind']] ?? 9), $b]);
                $out['intents'] = array_map(fn ($k) => $its[$k], $keys);
            }
        }
        foreach ($out['items'] as &$it) unset($it['start'], $it['miles'], $it['what'], $it['lat'], $it['lon'], $it['maybe'], $it['sure'], $it['agree'], $it['whole'], $it['rank']);
        return $out;
    }

    /** What the typed words may carry on to: situation phrases first, then the 5 W's around a thing ("parks" -> "parks for kids"). */
    private static function completions(PDO $pdo, string $q, ?array $place): array
    {
        $t = trim(preg_replace('/\s+/', ' ', mb_strtolower($q)));
        if (mb_strlen($t) < 3) return [];
        $out = class_exists('Situations') ? Situations::complete($pdo, $t, 4) : [];
        // the words already name a situation (by its phrase or by what they mean): only its own completions, never
        // "bathroom open now" or "bite by something to eat"
        $named = class_exists('Situations') && Situations::read($pdo, $t);
        // the Why phrases carry words on too ("landmarks i should" -> "...check out", "...see", "...visit")
        if (!$out && !$named) {
            $ws = explode(' ', $t);
            for ($k = min(3, count($ws)); $k >= 1 && !$out; $k--) {
                $tail = implode(' ', array_slice($ws, -$k));
                if ($k === 1 && (mb_strlen($tail) < 4 || count($ws) >= 3)) continue;   // one word of a longer search carries on to anything
                foreach (array_keys(self::WHY_PHRASES) as $ph) {
                    if (!str_starts_with($ph, $tail) || $ph === $tail || !str_contains($ph, ' ')) continue;
                    $rest = mb_substr($ph, mb_strlen($tail));
                    if (!str_starts_with($rest, ' ')) continue;   // whole words only
                    $out[] = ['q' => $t . $rest, 'typed' => $t, 'rest' => $rest];
                    if (count($out) >= 4) break;
                }
            }
        }
        // a thing named (on its own or with a where): when, who and where it could carry on with. Only when no situation
        // carries the words on ("car" is Car broke down, not "car open now")
        if (!$out && !$named) {
            $core = trim(preg_replace('/ (near|in|around|at) .*$/', '', ' ' . $t));
            $core = trim(implode(' ', array_filter(explode(' ', $core), fn ($w) => !in_array($w, self::DESCRIBE, true))));
            $isTag = false;
            foreach (self::TAGS as $tag => $g) if (self::root(mb_strtolower($tag)) === self::root($core)) { $isTag = true; break; }
            $thing = $core !== '' && ($isTag || self::findCategory($pdo, $core) || (!str_contains($core, ' ') && isset(self::whatVocab($pdo)[self::root($core)])));
            if ($thing) {
                $where = $place ? trim(explode('·', (string) $place['label'])[0]) : '';
                $ext = $isTag ? [' for kids', ' worth the drive', ' near ' . ($where ?: 'me')] : [' open now', ' open this weekend', ' near ' . ($where ?: 'me')];
                foreach ($ext as $x) {
                    $key = trim(explode(' ', trim($x))[0]);
                    if (preg_match('/\b' . preg_quote($key, '/') . '\b/', $t)) continue;   // already said ("parks near me" isn't offered "near" again)
                    $out[] = ['q' => $t . $x, 'typed' => $t, 'rest' => $x];
                }
            }
        }
        // (a pattern in what's searched is offered apart, as "Maybe try": maybeTry(), decisions/0093)
        return array_slice($out, 0, 6);
    }

    /**
     * "Maybe try" (decisions/0093, Jason 2026-10-15): a pattern the search noticed, offered as its own suggestion, never
     * as "what other people search". Only phrases searched by real visitors on at least 3 different days that found
     * something, made only of words the search already understands (never a name, an address, anything personal), and
     * not already among the predictions. At most 2.
     */
    private static function maybeTry(PDO $pdo, string $t, array $have): array
    {
        if (!is_file(__DIR__ . '/Learning.php')) return [];
        require_once __DIR__ . '/Learning.php';
        $out = [];
        foreach (Learning::completions($pdo, $t, 4) as $c) {
            if (in_array($c['q'], $have, true)) continue;
            $out[] = ['q' => $c['q'], 'typed' => $t, 'rest' => $c['rest']];
            if (count($out) >= 2) break;
        }
        return $out;
    }

    /** A situation's needs with no answers yet, and the ask for a place (decisions/0061). */
    private static function askPlace(PDO $pdo, array $sits): ?array
    {
        $a = Situations::answer($pdo, $sits, null, ['per' => 0]);
        if (!$a) return null;
        foreach ($a['needs'] as $i => $n) { $a['needs'][$i]['items'] = []; $a['needs'][$i]['incomplete'] = 0; }
        return $a + ['place' => null, 'need_place' => true];
    }

    /** The situations a search names, with each need's solutions, or null (decisions/0061). */
    private static function situationFor(PDO $pdo, string $q, ?array $place, ?array $area, string $reach, array $ctx, array $drop): ?array
    {
        if (in_array('situation', $drop, true) || !is_file(__DIR__ . '/Situations.php')) return null;
        require_once __DIR__ . '/Situations.php';
        try {
            $sits = Situations::read($pdo, $q);
            if (!$sits) return null;
            // no place set: "nearest" has nothing to measure from, so ask where first (never a guess hundreds of miles off)
            if (!$place || !empty($place['facts'])) {
                $ask = self::askPlace($pdo, $sits);
                // a state's code in the words ("ca tow truck"): asked, never assumed ("In California?"), and the question is
                // where in it (Jason, 2026-11-11: refine by asking more questions)
                if ($ask && preg_match('/(?:^|\s)([a-z]{2})(?:\s|$)/u', mb_strtolower(trim($q)), $sc) && ($sn = array_search(strtoupper($sc[1]), self::STATES, true)) !== false)
                    $ask['state_hint'] = ['code' => strtoupper($sc[1]), 'name' => ucwords((string) $sn)];
                return $ask;
            }
            $c = $area ? [$area['lat'], $area['lon']] : (($cc = $place ? self::centerOf($pdo, $place) : null) ? [$cc['lat'], $cc['lon']] : null);
            $a = Situations::answer($pdo, $sits, $place && empty($place['facts']) ? $place : null,
                ['center' => $c, 'reach' => $area ? (string) $area['radius'] : $reach, 'at' => is_array($ctx['at'] ?? null) ? $ctx['at'] : null]);
            // nothing listed for any need there yet ("Seattle, WA", outside our areas): the situation still answers, saying so
            // outside our regions: the town's middle (about a mile), so the panel can ask OpenStreetMap for businesses (decisions/0065)
            $oc = !empty($place['outside']) && $c ? [round((float) $c[0], 2), round((float) $c[1], 2)] : null;
            return $a ? $a + ['place' => $place['label'] ?? null, 'none_here' => !array_filter($a['needs'], fn ($n) => $n['items']), 'outside' => !empty($place['outside']), 'center' => $oc] : null;
        } catch (Throwable $e) { error_log('situation: ' . $e->getMessage()); return null; }
    }

    /** A place's middle: its first ZIP, else an area's or region's own center. ['lat', 'lon'] or null. */
    public static function centerOf(PDO $pdo, array $place): ?array
    {
        if (isset($place['lat'], $place['lon'])) return ['lat' => (float) $place['lat'], 'lon' => (float) $place['lon']];
        $zip = (string) ($place['zips'][0] ?? '');
        if ($zip !== '') {
            $st = $pdo->prepare('SELECT latitude, longitude FROM zip_coordinates WHERE zip = ? LIMIT 1');
            $st->execute([$zip]);
            if ($z = $st->fetch(PDO::FETCH_ASSOC)) return ['lat' => (float) $z['latitude'], 'lon' => (float) $z['longitude']];
        }
        $t = ['geo_hub' => 'geo_hubs', 'hub' => 'hubs'][$place['level'] ?? ''] ?? null;
        if ($t && !empty($place['slug'])) {
            $st = $pdo->prepare("SELECT center_lat, center_lon FROM $t WHERE slug = ? LIMIT 1");
            $st->execute([(string) $place['slug']]);
            if (($c = $st->fetch(PDO::FETCH_ASSOC)) && $c['center_lat'] !== null) return ['lat' => (float) $c['center_lat'], 'lon' => (float) $c['center_lon']];
        }
        return null;
    }

    private static function elsewhere(PDO $pdo, array $place, string $what): ?array
    {
        // the words that name the thing: not "new", "good", "place" (decisions/0058 §36)
        $stop = array_merge(['in', 'near', 'the', 'a', 'an', 'and', 'of', 'for', 'to', 'at', 'on', 'with', 'places', 'place'], self::DESCRIBE, SearchIndex::STOP, SearchIndex::SOFT);
        $words = array_slice(array_values(array_filter(preg_split('/\s+/u', mb_strtolower($what)) ?: [], fn ($w) => mb_strlen($w) >= 3 && !in_array($w, $stop, true))), 0, 4);
        if (!$words) return null;
        $center = null;
        // a place outside our areas ("Seattle, WA"): only towns within about 150 miles of it, and none when we don't know
        // where it is (never Arizona's towns for Seattle); the results then carry on on Google Maps
        if (!empty($place['outside'])) {
            if (!isset($place['lat'], $place['lon'])) return null;
            $center = ['lat' => (float) $place['lat'], 'lon' => (float) $place['lon'], 'radius' => 150];
        }
        $zip = $center ? '' : (string) ($place['zips'][0] ?? '');
        if ($zip !== '') {
            $st = $pdo->prepare('SELECT latitude, longitude FROM zip_coordinates WHERE zip = ? LIMIT 1');
            $st->execute([$zip]);
            if ($z = $st->fetch(PDO::FETCH_ASSOC)) $center = ['lat' => (float) $z['latitude'], 'lon' => (float) $z['longitude'], 'radius' => 200];
        }
        if (!$center && !empty($place['level']) && !empty($place['slug'])) {   // an area or region: its own middle
            $t = ['geo_hub' => 'geo_hubs', 'hub' => 'hubs'][$place['level']] ?? null;
            if ($t) {
                $st = $pdo->prepare("SELECT center_lat, center_lon FROM $t WHERE slug = ? LIMIT 1");
                $st->execute([(string) $place['slug']]);
                if (($c = $st->fetch(PDO::FETCH_ASSOC)) && $c['center_lat'] !== null) $center = ['lat' => (float) $c['center_lat'], 'lon' => (float) $c['center_lon'], 'radius' => 300];
            }
        }
        // each word, singular or plural ("hat" finds Hats Off)
        $alts = [];
        foreach ($words as $w) $alts[$w] = array_values(array_unique([$w, preg_match('/s$/', $w) ? substr($w, 0, -1) : $w . 's']));
        $rows = SearchIndex::rows($pdo, null, $center, ['kinds' => ['listing'], 'words' => array_merge(...array_values($alts)), 'geo' => true, 'limit' => 400, 'not_place' => $place]);
        // every word, not any one ("new hat" is hats, not New Covenant Church)
        $rows = array_values(array_filter($rows, function ($r) use ($alts) {
            $t = SearchIndex::norm((string) ($r['terms'] ?? ''), (string) $r['label']);
            foreach ($alts as $forms) {
                $ok = false;
                foreach ($forms as $f) { $n = trim(SearchIndex::norm($f)); if (str_contains($t, ' ' . $n . (mb_strlen($n) <= 3 ? ' ' : ''))) { $ok = true; break; } }
                if (!$ok) return false;
            }
            return true;
        }));
        // each one belongs to the nearest town area we cover (within 30 mi), else its own town
        $areas = [];
        try {
            foreach ($pdo->query('SELECT mc.slug, mc.name, COALESCE(mc.center_lat, z.latitude) AS lat, COALESCE(mc.center_lon, z.longitude) AS lon
                                    FROM micro_clusters mc LEFT JOIN zip_coordinates z ON z.zip = mc.primary_zip')->fetchAll(PDO::FETCH_ASSOC) as $c) {
                // the place's own area isn't "elsewhere", unless the place is one town in it (the rest of the area is)
                if ($c['lat'] !== null && (string) $c['slug'] !== '' && ((string) $c['slug'] !== (string) ($place['slug'] ?? '') || !empty($place['town']))) $areas[] = $c;
            }
        } catch (Throwable) {}
        $zipTown = [];
        $areaName = function (array $c) use ($pdo, &$zipTown): string {   // an area still named "ZIP 85939" reads as its town
            $n = PlaceScope::publicName((string) $c['name']);
            if (!preg_match('/^ZIP (\d{5})/', $n, $zm)) return $n;
            if (!array_key_exists($zm[1], $zipTown)) {
                $st = $pdo->prepare('SELECT city, state FROM zip_coordinates WHERE zip = ? LIMIT 1'); $st->execute([$zm[1]]);
                $z = $st->fetch(PDO::FETCH_ASSOC);
                $zipTown[$zm[1]] = $z ? ucwords(mb_strtolower((string) $z['city'])) . ', ' . $z['state'] : $n;
            }
            return $zipTown[$zm[1]];
        };
        $mi = fn ($a, $b, $c, $d) => 3958.8 * 2 * asin(min(1, sqrt(sin(deg2rad($c - $a) / 2) ** 2 + cos(deg2rad($a)) * cos(deg2rad($c)) * sin(deg2rad($d - $b) / 2) ** 2)));
        foreach ($rows as &$r) {
            $best = null; $bd = 30.0;
            foreach ($areas as $c) { $d = $mi((float) $r['lat'], (float) $r['lon'], (float) $c['lat'], (float) $c['lon']); if ($d < $bd) { $bd = $d; $best = $c; } }
            $r['area'] = $best ? $areaName($best) : trim(implode(', ', array_filter([ucwords(mb_strtolower((string) $r['city'])), (string) $r['state']])));
            $r['area_slug'] = $best ? (string) $best['slug'] : '';
        }
        unset($r);
        $towns = [];
        foreach ($rows as $r) {
            $town = (string) $r['area'];
            if ($town === '') continue;
            $t = &$towns[$town];
            $t ??= ['town' => $town, 'slug' => (string) $r['area_slug'], 'miles' => null, 'count' => 0, 'items' => []];
            $t['count']++;
            $mi = isset($r['miles']) ? round((float) $r['miles']) : null;
            if ($mi !== null && ($t['miles'] === null || $mi < $t['miles'])) $t['miles'] = $mi;
            if (count($t['items']) < 3) $t['items'][] = ['id' => (int) $r['item_id'], 'label' => (string) $r['label'], 'sub' => (string) $r['sub'], 'lat' => (float) $r['lat'], 'lon' => (float) $r['lon'], 'miles' => $mi];
            unset($t);
        }
        if (!$towns) return !empty($place['outside']) ? null : ['place' => (string) $place['label'], 'what' => $what, 'towns' => []];   // outside our areas: the results say it, with Google Maps
        $towns = array_values($towns);
        usort($towns, fn ($a, $b) => [$a['miles'] ?? 9999, -$a['count']] <=> [$b['miles'] ?? 9999, -$b['count']]);
        return ['place' => (string) $place['label'], 'what' => $what, 'towns' => array_slice($towns, 0, 5)];
    }

    /** Up to 5 matching listings and the total, in the place (or town) asked about. */
    private static function listings(PDO $pdo, string $text, ?array $place, ?array $cat, array $conds, ?array $area = null): array
    {
        $in = "'" . implode("','", self::STATUSES) . "'";
        $params = [];
        $where = [];
        if ($area) {
            // within the distance: a box first, then the exact distance
            $dLat = $area['radius'] / 69.0; $dLon = $area['radius'] / max(0.0001, 69.0 * cos(deg2rad($area['lat'])));
            $where[] = 'e.latitude BETWEEN :la1 AND :la2 AND e.longitude BETWEEN :lo1 AND :lo2';
            $where[] = '(3958.8 * 2 * ASIN(SQRT(POWER(SIN(RADIANS(e.latitude - :lat_a) / 2), 2) + COS(RADIANS(:lat_b)) * COS(RADIANS(e.latitude)) * POWER(SIN(RADIANS(e.longitude - :lon_a) / 2), 2)))) <= :rad';
            $params += [':la1' => $area['lat'] - $dLat, ':la2' => $area['lat'] + $dLat, ':lo1' => $area['lon'] - $dLon, ':lo2' => $area['lon'] + $dLon,
                        ':lat_a' => $area['lat'], ':lat_b' => $area['lat'], ':lon_a' => $area['lon'], ':rad' => $area['radius']];
        } elseif ($place && ($place['level'] ?? '') !== '') {
            $param = ['cluster' => 'cluster', 'geo_hub' => 'geo', 'hub' => 'hub'][$place['level']];
            $s = PlaceScope::resolve($pdo, [$param => (string) $place['slug']]);
            if ($s && $s['found']) $where[] = $s['sql'];
        } elseif ($place && !empty($place['city'])) {
            $where[] = "JSON_UNQUOTE(m_city.meta_value)" . SearchText::CI . " = :city";
            $params[':city'] = $place['city'];
        } elseif ($place && !empty($place['state'])) {
            $where[] = "JSON_UNQUOTE(m_state.meta_value)" . SearchText::CI . " = :st";
            $params[':st'] = $place['state'];
        }
        $catWords = $cat ? trim((string) ($cat['words'] ?? '')) : '';
        if ($cat && $catWords === '') { $where[] = 'e.category_id IN (SELECT id FROM categories WHERE id = :cat_a OR parent_id = :cat_b' . CategoryAlso::sqlFor($pdo, (int) $cat['id']) . ')'; $params += [':cat_a' => $cat['id'], ':cat_b' => $cat['id']]; }
        if (in_array('phone', $conds, true)) $where[] = "COALESCE(JSON_UNQUOTE(m_phone.meta_value), '') <> ''";
        if (in_array('website', $conds, true)) $where[] = "COALESCE(JSON_UNQUOTE(m_web.meta_value), '') <> ''";
        if ($text === '' && !$cat && !$place) return [[], 0];
        $inner = "SELECT e.id, e.name AS business_name, e.category_id, COALESCE(c.name, CAST(JSON_UNQUOTE(m_cat.meta_value) AS CHAR(120))) COLLATE utf8mb4_general_ci AS category,
                         g.name AS category_group, " . ($catWords !== '' ? '(CASE WHEN e.category_id IN (SELECT id FROM categories WHERE id = ' . (int) $cat['id'] . ' OR parent_id = ' . (int) $cat['id'] . ') THEN 1 ELSE 0 END)' : '0') . " AS in_cat,
                         CAST(JSON_UNQUOTE(m_city.meta_value) AS CHAR(120)) AS city, CAST(JSON_UNQUOTE(m_state.meta_value) AS CHAR(40)) AS state,
                         JSON_UNQUOTE(m_desc.meta_value) AS description,
                         REPLACE(REPLACE(REPLACE(CAST(m_offer.meta_value AS CHAR(2000)), '[', ' '), ']', ' '), '\"', ' ') COLLATE utf8mb4_general_ci AS offerings
                    FROM entities e
                    LEFT JOIN entity_metadata m_city  ON m_city.entity_id  = e.id AND m_city.meta_key  = 'city'
                    LEFT JOIN entity_metadata m_state ON m_state.entity_id = e.id AND m_state.meta_key = 'state'
                    LEFT JOIN entity_metadata m_cat   ON m_cat.entity_id   = e.id AND m_cat.meta_key   = 'category'
                    LEFT JOIN entity_metadata m_desc  ON m_desc.entity_id  = e.id AND m_desc.meta_key  = 'description'
                    LEFT JOIN entity_metadata m_offer ON m_offer.entity_id = e.id AND m_offer.meta_key = 'offerings'
                    LEFT JOIN entity_metadata m_phone ON m_phone.entity_id = e.id AND m_phone.meta_key = 'phone'
                    LEFT JOIN entity_metadata m_web   ON m_web.entity_id   = e.id AND m_web.meta_key   = 'website'
                    LEFT JOIN categories c ON c.id = e.category_id
                    LEFT JOIN categories g ON g.id = c.parent_id
                   WHERE e.entity_type = 'merchant' AND e.is_suppressed = 0 AND e.status IN ($in)" . ($where ? ' AND ' . implode(' AND ', $where) : '');
        $run = function (bool $strict) use ($pdo, $inner, $params, $text, $catWords): array {
            $qp = [];
            $qs = $text !== '' ? SearchText::where($text, $strict, $qp, ['x.offerings']) : '';
            if ($text !== '' && $qs === '') $qs = '1 = 0';
            if ($catWords !== '') {   // a category read from words: in the category, or the words in its name, offerings or description
                $cp = [];
                $cs = SearchText::where($catWords, $strict, $cp, ['x.offerings']);
                $cs = str_replace(':t', ':ct', $cs);
                foreach ($cp as $k => $v) $qp[str_replace(':t', ':ct', $k)] = $v;
                $cs = '(x.in_cat = 1' . ($cs !== '' ? ' OR ' . $cs : '') . ')';
                $qs = $qs !== '' ? "$qs AND $cs" : $cs;
            }
            $w = $qs !== '' ? " WHERE $qs" : '';
            $st = $pdo->prepare("SELECT x.id, x.business_name, x.category_id, x.category, x.city, x.state FROM ($inner) x$w ORDER BY (x.business_name" . SearchText::CI . " LIKE :pre) DESC, x.business_name LIMIT 5");
            $st->execute($params + $qp + [':pre' => SearchText::esc($text) . '%']);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            // fewer than 5 is all of them: counted without reading the listings a second time (2026-10-14: speed)
            if (count($rows) < 5) return [$rows, count($rows)];
            $n = $pdo->prepare("SELECT COUNT(*) FROM ($inner) x$w");
            $n->execute($params + $qp);
            return [$rows, (int) $n->fetchColumn()];
        };
        [$rows, $total] = $run(true);
        if ($text !== '' && $total < SearchText::LOOSE_BELOW) { [$r2, $t2] = $run(false); if ($t2 > $total) [$rows, $total] = [$r2, $t2]; }
        $out = array_map(fn ($r) => ['id' => (int) $r['id'], 'label' => (string) $r['business_name'],
            'sub' => trim(implode(' · ', array_filter([(string) $r['category'], trim(implode(', ', array_filter([(string) $r['city'], (string) $r['state']])))]))),
            'url' => '/listing/view.php?id=' . (int) $r['id'], 'peek' => true], $rows);
        return [$out, $total];
    }

    /** Published experiences whose title, place or summary mention the words, in the place (and the places in it). */
    private static function experiencesFor(PDO $pdo, string $text, ?array $place): array
    {
        try {
            if (!class_exists('Experiences')) require_once __DIR__ . '/Experiences.php';
            $toks = array_slice(array_filter(SearchText::tokens($text), fn ($t) => mb_strlen($t) >= 3), 0, 4);
            return array_map(fn ($e) => ['kind' => 'experience', 'id' => 'x' . (int) $e['id'], 'label' => (string) $e['title'],
                'sub' => trim((Experiences::TYPES[$e['type']] ?? 'Experience') . ' · ' . $e['place_label'], ' ·'), 'url' => '/experiences/view.php?id=' . (int) $e['id'],
                'open' => null, 'peek' => false, 'who' => '', 'funnel' => 'explore'], Experiences::search($pdo, $toks, $place ? (string) ($place['key'] ?? '') : '', 4));
        } catch (Throwable $e) { error_log('experiencesFor: ' . $e->getMessage()); return []; }
    }

    /** Published journeys whose title, place, category or story mention the words, in the place (and the places in it). */
    private static function journeysFor(PDO $pdo, string $text, ?array $place): array
    {
        try {
            if (!class_exists('Journeys')) require_once __DIR__ . '/Journeys.php';
            if (!Journeys::ready($pdo)) return [];
            $toks = array_slice(array_filter(SearchText::tokens($text), fn ($t) => mb_strlen($t) >= 3), 0, 4);
            if (!$toks) return [];
            $w = ["j.status = 'published'"]; $p = [];
            foreach ($toks as $i => $t) { $w[] = "(CONCAT(' ', LOWER(j.title), ' ', LOWER(COALESCE(j.place_label, '')), ' ', LOWER(COALESCE(j.category, ''))) LIKE ? ESCAPE '!')"; $p[] = '% ' . SearchText::esc($t) . '%'; }
            if ($place && !empty($place['key'])) { $w[] = 'j.scope_keys LIKE ?'; $p[] = '%|' . $place['key'] . '|%'; }
            $st = $pdo->prepare('SELECT j.id, j.title, j.place_label, j.category FROM journeys j WHERE ' . implode(' AND ', $w) . ' ORDER BY (j.likes * 3 + j.comments * 2) DESC, j.published_at DESC LIMIT 4');
            $st->execute($p);
            return array_map(fn ($j) => ['kind' => 'story', 'id' => 'j' . (int) $j['id'], 'label' => (string) $j['title'],
                'sub' => trim('Journey' . ($j['place_label'] ? ' · ' . $j['place_label'] : '')), 'url' => '/journeys/view.php?id=' . (int) $j['id'],
                'open' => null, 'peek' => false, 'who' => '', 'funnel' => 'explore'], $st->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) { error_log('journeysFor: ' . $e->getMessage()); return []; }
    }

    /** The peek card for a public recreation place (Recreation.gov), in the listing card's shape. */
    public static function peekOutdoors(PDO $pdo, string $id): ?array
    {
        if (preg_match('/^g0*(\d{1,10})$/', $id, $g)) {   // a natural landmark (decisions/0058 §24)
            require_once __DIR__ . '/Landmarks.php';
            $l = Landmarks::get($pdo, (int) $g[1]);
            if (!$l) return null;
            require_once __DIR__ . '/PlaceFinder.php';
            if (trim((string) ($l['extract'] ?? '')) === '' && trim((string) ($l['research'] ?? '')) === '') PlaceFinder::wantAbout($pdo, (int) $l['gnis_id']);   // opened with nothing written: Claude writes it, once
            $town = $l['near_town'] !== '' ? 'Near ' . $l['near_town'] . ', ' . $l['state'] : $l['county'] . ' County, ' . $l['state'];
            return ['id' => 'rec:' . $id, 'kind' => 'outdoors', 'name' => (string) $l['name'], 'category' => trim(Landmarks::kindLabel($l) . ($l['nation'] !== '' ? ' · ' . (is_file(__DIR__ . '/Nations.php') && (require_once __DIR__ . '/Nations.php') ? Nations::label($pdo, (string) $l['nation']) : $l['nation']) . ' land' : '')), 'category_slug' => '',
                    'address' => $town, 'town' => $town, 'private_location' => false, 'phone' => '', 'website' => '', 'contact_url' => '', 'hours' => [],
                    'about' => mb_strimwidth((string) (Landmarks::about($pdo, $l) ?: ucfirst((string) $l['wd_desc'])), 0, 320, '…'),   // Wikipedia's words only where its rule allows (Landmarks::about; 0164 R9)
                    'activities' => array_map(fn ($a) => ['key' => $a, 'label' => Landmarks::ACTIVITIES[$a][0], 'how' => Landmarks::ACTIVITIES[$a][1]], Landmarks::activities($l)),
                    'guide' => (require_once __DIR__ . '/Guides.php') ? Guides::lineAbout($pdo, 'landmark:' . (int) $l['gnis_id'], (string) $l['name']) : null,
                    'suggest' => ['target' => 'landmark:' . (int) $l['gnis_id'], 'target_name' => (string) $l['name'], 'topic' => 'outdoors'],
                    'image' => (string) ($l['image_url'] ?? ''), 'image_credit' => trim(((string) ($l['image_credit'] ?? '')) . (($l['image_license'] ?? '') !== '' ? ' · ' . $l['image_license'] : ''), ' ·'),   // the map card's photo (2026-11-10)
                    'directions' => 'https://www.google.com/maps/dir/?api=1&destination=' . $l['lat'] . ',' . $l['lon'], 'url' => '/place/landmark.php?id=' . (int) $l['gnis_id']];
        }
        if (!class_exists('RecBulk')) { if (!is_file(__DIR__ . '/crawler/RecBulk.php')) return null; require_once __DIR__ . '/crawler/RecBulk.php'; }
        $r = RecBulk::get($pdo, $id);
        if (!$r) return null;
        $town = trim(implode(', ', array_filter([(string) $r['city'], (string) $r['state']])));
        return ['id' => 'rec:' . $r['ridb_id'], 'kind' => 'outdoors', 'name' => RecBulk::displayName((string) $r['name']),
                'category' => trim(implode(' · ', array_filter([RecBulk::kindLabel($r), (string) $r['org']])), ' ·'), 'category_slug' => '',
                'address' => $town, 'town' => $town, 'private_location' => false, 'phone' => (string) $r['phone'], 'website' => (string) $r['url'],
                'contact_url' => '', 'hours' => [], 'about' => mb_strimwidth((string) $r['summary'], 0, 240, '…'),
                'directions' => $r['lat'] !== null ? 'https://www.google.com/maps/dir/?api=1&destination=' . $r['lat'] . ',' . $r['lon'] : null,
                'url' => '/place/recreation.php?id=' . rawurlencode((string) $r['ridb_id'])];
    }

    /** The peek card: public details only; a confidential location shows the phone only (decisions/0044). */
    public static function peek(PDO $pdo, int $id): ?array
    {
        $in = "'" . implode("','", self::STATUSES) . "'";
        $st = $pdo->prepare("SELECT e.id, e.name, e.status, e.category_id, e.latitude, e.longitude, c.name AS category, c.slug AS category_slug FROM entities e LEFT JOIN categories c ON c.id = e.category_id
                              WHERE e.id = ? AND e.entity_type = 'merchant' AND e.is_suppressed = 0 AND e.status IN ($in)");
        $st->execute([$id]);
        $e = $st->fetch(PDO::FETCH_ASSOC);
        if (!$e) return null;
        $m = [];
        $ms = $pdo->prepare("SELECT meta_key, meta_value FROM entity_metadata WHERE entity_id = ? AND meta_key IN ('address','city','state','phone','website','hours','description','contact_url')");
        $ms->execute([$id]);
        foreach ($ms->fetchAll(PDO::FETCH_ASSOC) as $r) $m[$r['meta_key']] = json_decode((string) $r['meta_value'], true) ?? $r['meta_value'];
        $private = ListingSafety::isConfidential($e['category_id'] !== null ? (int) $e['category_id'] : null, (string) $e['name']);
        $town = trim(implode(', ', array_filter([(string) ($m['city'] ?? ''), (string) ($m['state'] ?? '')])));
        require_once __DIR__ . '/NameText.php';
        $town = NameText::full($town);
        return ['id' => (int) $e['id'], 'name' => NameText::listing((string) $e['name'], (string) $e['status']), 'category' => (string) ($e['category'] ?? ''), 'category_slug' => (string) ($e['category_slug'] ?? ''),
                'address' => $private ? null : trim(implode(', ', array_filter([(string) ($m['address'] ?? ''), $town]))), 'town' => $town,
                'private_location' => $private, 'mailing_only' => !$private && (require_once __DIR__ . '/ListingActions.php') && ListingActions::isMailbox((string) ($m['address'] ?? '')),
                'approx' => !$private && ((require_once __DIR__ . '/ListingActions.php') && ListingActions::isMailbox((string) ($m['address'] ?? '')) || self::approx($pdo, (int) $e['id'])),
                'phone' => (string) ($m['phone'] ?? ''), 'website' => $private ? '' : (string) ($m['website'] ?? ''),
                'contact_url' => (string) ($m['contact_url'] ?? ''), 'hours' => is_array($m['hours'] ?? null) ? array_slice($m['hours'], 0, 7) : [],
                'about' => mb_strimwidth((string) ($m['description'] ?? ''), 0, 240, '…'),
                'guide' => (require_once __DIR__ . '/Guides.php') ? Guides::lineAbout($pdo, 'entity:' . (int) $e['id'], (string) $e['name']) : null,
                'suggest' => ['target' => 'entity:' . (int) $e['id'], 'target_name' => (string) $e['name'], 'topic' => 'listing'],
                'directions' => !$private && $e['latitude'] !== null && !ListingActions::isMailbox((string) ($m['address'] ?? '')) ? 'https://www.google.com/maps/dir/?api=1&destination=' . $e['latitude'] . ',' . $e['longitude'] : null,
                'url' => '/listing/view.php?id=' . (int) $e['id']];
    }

    /** The pin is only its ZIP area's middle (decisions/0058 §14): the map card says so and asks for the street point. */
    private static function approx(PDO $pdo, int $id): bool
    {
        if (!class_exists('Geocoder')) require_once __DIR__ . '/Geocoder.php';
        try { return Geocoder::isApprox($pdo, $id); } catch (Throwable $e) { return false; }
    }
}
