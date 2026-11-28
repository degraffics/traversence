<?php
// app/lib/DataCleanup.php — Insights → Data cleanup (decisions/0163 §8 stage 1b, 0164 §6.1b; Jason, 2026-11-14: "a tool
// clean up all the bad data recently added").
//
// It brings what the old crawler made into line with today's rules (0164), from handoffs/crawler-revamp.md Phase 4's
// assimilation passes. Each rule finds the records that fail it, in four stores (listings, staged finds, the place store,
// landmarks), newest first, and says what would happen to each:
//   act    the rule's action applies (quarantine, sighting, "Help us find", re-source, caution, merge offer, hold);
//   check  a person entered or decided something here (0164 R4): never changed, a "Check these" step instead;
//   left   it fails nothing today's rules act on (shown in the preview so the count is honest).
// Step 2 (2026-11-14) acts on them by rule, reversibly: every change is a row in the ledger (cleanup_ledger) with its old
// and new value, undone one record or one whole pass at a time; nothing is ever deleted. What needs a person is one Pulse
// case. A pass runs by a person's button; the scheduled pass (data_cleanup_tick) is shipped off, under the master switch.
//
// Reused or restricted, never a dead end (Jason, 2026-11-15: "They still exist, just hidden: this is a dead end. Either
// they are reused or moved into restricted"; the table in handoffs/data-cleanup.md, approved "table is right"). Every
// record a pass takes off goes back to work: a "Help us find" card (a name and a town), a sighting back in the intake
// (a name only; resting on cite-only pages), or a sighting "name unknown" (page text for a name, but a phone or street
// address), each retried on the retry ladder (decisions/0163 §12) until a source answers. What can't be reused is
// Restricted (0164 R7's tier: kept for our records only), listed on the page with Put back and Make it a request.
// Drop and collection boxes are their own listings, "Mail & package drop-off", with their pins. Markers: a listing's
// 'removed' note carries its target (help_find, sighting, name_unknown, restricted, found) and, for a sighting, its
// intake row ('row'); the intake row's payload carries 'cleanup' (the listing, the kind) and 'retry' (the ladder).
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class DataCleanup
{
    /** Who may run passes, and who may undo them (System Configuration → Permissions). */
    public const PERMS = [
        'insights.cleanup' => 'Data cleanup: see the preview and run passes',
        'insights.cleanup_undo' => 'Data cleanup: undo a change or a whole pass',
    ];
    public const RULE_RUN = ['min' => 'executive', 'depts' => []];
    public const RULE_UNDO = ['min' => 'executive', 'depts' => []];

    /** How many of each store a preview reads (newest first): enough for the crawler's stores today, bounded for the server. */
    public const READ = 6000;
    public const EXAMPLES = 6;

    /**
     * The rules: key => [store, title, what it finds, what happens (the action), its decision].
     * Actions: quarantine (off the public site, kept, reason stored), sighting (back to a sighting), help_find (a "Help us find
     * this business" card: not built yet, so quarantined with that as its planned target), unconfirm (the value marked
     * unconfirmed until re-sourced), caution (Closed becomes a caution with "Still open?"), merge (offered for a merge in
     * Review), hold (held, as the landmark rules hold a place), story_only (held until landmarks have the story-only level),
     * clear_text (a source's words we may only cite: cleared, its title and link kept).
     */
    public const RULES = [
        'junk_name' => ['listings', 'Not a business name', 'A listing whose name is page text ("Contact Us:", "PO Box 190", a webinar or form title, a heading). With a phone or street address it goes back as a sighting "name unknown"; with nothing else it\'s restricted.', 'restricted', '0164 §3.5 (the "real business" floor); Jason, 2026-11-15'],
        'no_floor' => ['listings', 'No address or phone', 'A listing with no street address and no phone, so nothing lets a visitor find it. With a town it\'s a "Help us find" card; a name only goes back as a sighting.', 'help_find', '0164 §3.5 (the floor; "Help us find this business")'],
        'drop_box' => ['listings', 'Mail & package drop-offs', 'A drop or collection box (USPS, UPS, FedEx…): a real place people use, its own kind of listing with its pin, "Mail & package drop-off". One an earlier pass took off is put back.', 'drop_off', 'Jason, 2026-11-15 ("their own listing and map pin")'],
        'taken_off' => ['off', 'Taken off by an earlier pass, not yet back to work', 'A listing an earlier pass hid as page text or a sighting before the routing of 2026-11-15: sent where it goes now (a sighting, "name unknown", a "Help us find" card, a drop-off, or Restricted).', 'reuse', 'Jason, 2026-11-15 ("reused or moved into restricted")'],
        'person_place' => ['listings', 'People shown as places', 'A listing whose name is a person\'s (a provider, in NPI\'s "Last, First" form or the crawler\'s person test), not a place to visit: kept as a record under its practice (the listing at the same street address or phone, when there is one), off the public site.', 'quarantine', 'The CMO (7), 2026-11-27: individual providers become records attached to their practice, never a standalone place; the CEO, 2026-11-28 (F9)'],
        'legacy_unsourced' => ['listings', 'September listings with no source we may keep', 'A listing from the one-time September 2026 migration whose phone and address no source we may keep confirms (only "Found by our crawler"): back to a sighting until one does. A government or agency listing with any source we keep stays up. Its scope is a setting the CEO rules: off, health (Health & Medical and the emergency services 0061 names), or all.', 'sighting', '0164 §3.5 (the floor); the CEO, 2026-11-28 (F9, 1A first)'],
        'page_only' => ['listings', 'Made from a page we may only cite', 'A listing whose address and phone rest only on pages whose terms let us cite them, not keep their facts.', 'sighting', '0164 R9, §3.5'],
        'osm_only' => ['listings', 'Values from OpenStreetMap only', 'A phone, address, website, hours or map point whose only source is OpenStreetMap (a lead, never a source).', 'unconfirm', '0065, 0164 R9'],
        'closed_one_site' => ['listings', '"Closed" from one site', 'A listing the crawler marked Closed on one site\'s word.', 'caution', '0164 R12 ("Still open?")'],
        'duplicate' => ['listings', 'Likely duplicates', 'Two live listings the intake\'s own test calls one business (the same phone, and the same Google place, name or address); providers at one clinic and businesses sharing a building aren\'t duplicates (0074).', 'merge', '0164 R12, decisions/0074, 0133'],
        'staged_junk' => ['staged', 'Staged finds with a page-text name', 'A find waiting in the intake whose name is page text.', 'quarantine', '0164 §3.5'],
        'store_junk' => ['store', 'Place-store names that are page text', 'A place in the place store whose name is page text.', 'hold', '0164 §3.5'],
        'store_guard' => ['store', 'Place-store places the guards would hold', 'A protected place, one on a nation\'s land with no phone or website, or a person\'s name, not yet marked held.', 'hold', '0164 R6, R7; 0066'],
        'store_cite' => ['store', 'Place-store facts from cite-only sources', 'Facts kept from a source whose rule is cite only (or ignore).', 'hold', '0164 R9'],
        'protected_open' => ['landmarks', 'Protected names shown too openly', 'A landmark whose name marks a sacred, burial or archaeological place, shown with its pin on a machine\'s word (a Wikipedia article alone).', 'story_only', '0164 R7 (story only), P5-3; 0066'],
        'nation_sensitive' => ['landmarks', 'Nation-sensitive names shown', 'A landmark on a nation\'s land of a kind specific to the nation (its springs), shown.', 'hold', '0066, 0164 R7'],
        'wiki_text' => ['landmarks', 'Wikipedia text kept while its rule is cite only', 'A landmark holding Wikipedia\'s words, though Wikipedia\'s rule in the source register is cite only. Its replacement is written first (our own words, kept as research); the text is cleared only once that exists.', 'clear_text', '0164 R9, W9 (Jason, 2026-11-14: a replacement before it\'s stripped)'],
    ];

    public const ACTIONS = [
        'quarantine' => 'Restricted: off the public site, kept for our records only, with its reason (Put back or Make it a request under Restricted)',
        'restricted' => 'Restricted: off the public site, kept for our records only, with its reason (Put back or Make it a request under Restricted)',
        'sighting' => 'Back to a sighting in the intake, retried until a source we may keep gives its address or phone',
        'name_unknown' => 'Back to a sighting "name unknown": its phone or address kept, its source re-read for the real name',
        'help_find' => 'A "Help us find this business" card on its town\'s page and in search',
        'drop_off' => 'Its own listing with its pin: "Mail & package drop-off"',
        'reuse' => 'Sent back to work by today\'s routing (a sighting, "name unknown", "Help us find", a drop-off, or Restricted)',
        'found' => 'Found as another listing: kept, pointing to it (one record)',
        'unconfirm' => 'Re-source, or mark the value unconfirmed',
        'caution' => 'Caution with "Still open?" instead of Closed',
        'merge' => 'Offered for a merge in Review',
        'hold' => 'Restricted: kept, never shown (a page\'s link kept as a citation)',
        'story_only' => 'Story only: named and told, no pin, map or directions (publicly known, off a nation\'s land); else held',
        'clear_text' => 'Its words cleared once our own description replaces them; its title and link kept',
        'write_first' => 'Its replacement written first (Place descriptions); cleared on a later pass, once written',
    ];

    public const STORES = ['listings' => 'Listings', 'off' => 'Listings taken off', 'staged' => 'Staged finds', 'store' => 'The place store', 'landmarks' => 'Landmarks'];

    /** What counts as Restricted on the page: these rules' changes, and the targets below (0164 R7's tier). */
    public const RESTRICTED_RULES = ['staged_junk', 'store_junk', 'store_guard', 'store_cite', 'protected_open', 'nation_sensitive'];
    public const RESTRICTED_TARGETS = ['restricted', 'quarantine'];
    /** Targets that are a sighting back in the intake (a row in crawler_raw_index, its 'cleanup' payload). */
    public const SIGHTINGS = ['sighting', 'name_unknown'];

    /** The retry ladder (decisions/0163 §12): [fails in a row => days to the next try]; under 3, the normal cadence (a day). */
    public const LADDER = [10 => 180, 6 => 30, 3 => 7];
    /** Failures in a row after which a find is retried no more (about a year and a half on the ladder); a person's answer still settles it. */
    public const STOP_AFTER = 12;
    /** The 'next try' of a find the ladder gave up on. */
    public const NEVER = '2999-12-31 00:00:00';

    /** "Mail & package drop-off" (Jason, 2026-11-15): its leaf, added by app/migrations/2026-11-15_drop_off_category.sql. */
    public const DROP_SLUG = 'mail-package-drop-off';
    public const DROP_NAME = 'Mail & package drop-off';
    private const DROP = '/\b(drop[\s-]?(off\s+)?box(es)?|collection\s+box(es)?|blue\s+box|mail\s*box\s+drop|(usps|ups|fed\s?ex|dhl|amazon)\s+(drop([\s-]?off)?|collection|blue\s+box|locker|hub\s+locker)s?)\b/iu';
    private const NOT_DROP = '/\b(store|shop|storage|cent(er|re)|services?|printing|pack(ing)?\s+and\s+ship|solutions|inc|llc)\b/iu';

    /** Whether a name is a mail or package drop or collection box (not a staffed store). */
    public static function dropName(string $name): bool
    {
        $n = trim(html_entity_decode(strip_tags($name), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return (bool) preg_match(self::DROP, $n) && !preg_match(self::NOT_DROP, $n);
    }

    /** The drop-off leaf's id, or 0 while its database update hasn't run. */
    public static function dropCategory(PDO $pdo): int
    {
        static $id = null;
        if ($id === null) { try { $st = $pdo->prepare('SELECT id FROM categories WHERE slug = ? AND is_active = 1 LIMIT 1'); $st->execute([self::DROP_SLUG]); $id = (int) ($st->fetchColumn() ?: 0); } catch (Throwable $x) { $id = 0; } }
        return $id;
    }

    /**
     * Where a listing taken off goes (the approved table): drop_off, name_unknown (page text, but a phone or street
     * address), restricted (page text with nothing else), help_find (a name and a town), sighting (a name only, or facts
     * resting on cite-only pages).
     */
    public static function route(string $name, bool $phone, bool $street, bool $town): string
    {
        if (self::dropName($name)) return 'drop_off';
        if (self::junkName($name)[0]) return $phone || $street ? 'name_unknown' : 'restricted';
        if (!$phone && !$street) return $town ? 'help_find' : 'sighting';
        return 'sighting';   // a phone or address that rests only on pages we may cite: a sighting until a keepable source
    }

    // ---- the "real business" name check (0164 §3.5; handoffs/crawler-revamp.md §7.3) ------------------------------------

    /** A page's labels read as a name. */
    private const LABELS = '/^\s*((physical|mailing|street|business|our|postal)\s+address(es)?|address(es)?|contact(\s+(us|info|information|me|page))?|about(\s+us)?|home(\s+page)?|locations?|directions|hours(\s+of\s+operation)?|office\s+hours|phone(\s+numbers?)?|email|e-mail|fax|menu|welcome|news|events?|calendar|resources|services|faqs?|search|login|log\s+in|sign\s+(in|up)|privacy(\s+policy)?|terms(\s+of\s+(use|service))?|site\s*map|map|gallery|photos|links|staff|team|board|careers|jobs|donate|subscribe|register|more|read\s+more|learn\s+more|click\s+here|untitled|n\/?a|none|null|test)\s*[:.\-–—]*\s*$/iu';
    /** Words that make a name a document, an event or a form, not a business. */
    private const DOC_WORDS = '/\b(webinars?|slide\s?shows?|slides|newsletters?|brochures?|flyers?|fliers?|agendas?|meeting\s+minutes|press\s+releases?|pdf|docx?|(application|registration|enrollment|intake|request|order|complaint|permit)\s+(forms?|packets?)|forms?\s+(and|&)\s+(documents|applications)|fillable|click\s+here|read\s+more|learn\s+more|sign\s+up|download|informational|annual\s+report|fact\s+sheet|faq)\b/iu';
    /** Openings that make a name a sentence about something ("An agency within…", "Part of…"). */
    private const SENTENCE = '/^\s*(an?\s+(agency|program|programme|service|division|department|unit|office|bureau|branch|partnership|initiative|project|resource|list|guide|directory|collection)\b|part\s+of\b|home\s+of\b|welcome\s+to\b|learn\s+(about|more)\b|find\s+(a|an|the|your)\b|how\s+to\b|what\s+(is|are)\b|this\s+(is|page)\b|we\s+(are|offer)\b|please\b|for\s+more\b)/iu';
    /** A mailing address read as a name ("PO Box 190"). Drop and collection boxes are real places people use (Jason,
     *  2026-11-15: "drop boxes are good to know"), so they aren't page text. */
    private const BOXES = '/^\s*(p\.?\s*o\.?\s*box|post\s+office\s+box|box|rr|hc|star\s+route)\s*#?\s*\d/iu';

    /**
     * Whether a name is page text rather than a business's name, and why: [true, why] or [false, ''].
     * Conservative on purpose: a real name that trips it is a "Check these" for a person only when a person touched it, so
     * the patterns are the junk names of 2026-11-13 and their kin, never a guess about an unusual name.
     */
    /** A person's name, not a place's (F9): NPI's "Last, First" form, a name ending in a provider's credential, or the
     *  never one with a business word. Narrow on purpose: a miss stays public as today, a wrong hit hides a place. */
    public static function isPerson(string $name): bool
    {
        $n = trim($name);
        if ($n === '' || preg_match('/\b(llc|inc|pllc|pc|plc|ltd|corp|group|cent(er|re)|clinic|services?|associates|company|hospital|pharmacy|medical|health|care|dental|therapy|rehab|wellness|of|the|and|at)\b|&|\d/i', $n)) return false;
        if (preg_match("/^[A-Za-z'\-]+, [A-Za-z]/", $n)) return true;
        return (bool) preg_match('/\s(np|pa|pa-c|rn|md|do|dc|dpt|pt|lpc|fnp|crna|dds|dmd|od|pharmd|rph)\.?$/i', $n);   // not looksPerson: two plain words are often a place ("Taylor Pool")
    }

    /** One street address or phone as a key, to find a person's practice at the same place. */
    private static function placeKey(string $k, string $v): string
    {
        if ($k === 'phone') { $d = preg_replace('/\D/', '', $v); return strlen((string) $d) >= 10 ? 'p:' . substr((string) $d, -10) : ''; }
        $v = strtolower(trim(preg_replace('/\s+(ste|suite|bldg|building|unit|#)\s*\S+$/i', '', $v)));
        return preg_match('/^\d+\s+\S/', $v) ? 'a:' . preg_replace('/[^a-z0-9]+/', ' ', $v) : '';
    }

    public static function junkName(string $name): array
    {
        $n = trim(html_entity_decode(strip_tags($name), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (mb_strlen($n) < 2) return [true, 'No name'];
        if (preg_match(self::LABELS, $n)) return [true, 'A page\'s label, not a name'];
        if (preg_match('/[:]\s*$/u', $n)) return [true, 'Ends with a colon: a page\'s label'];
        if (preg_match('/^\s*contact\s*[:\-–—]/iu', $n)) return [true, 'A contact line, not a name'];
        if (preg_match('/^\s*(contact|call|email|e-mail|phone|fax|write\s+to|mail\s+to)\s+(.+)$/iu', $n, $m)) {
            require_once __DIR__ . '/crawler/AutoImport.php';
            $contact = mb_strtolower($m[1]) === 'contact';   // "Contact Greg Trapp"; "Call Ahead Pizza" is a business
            if (preg_match('/^(us|info|information|me|our|today|now)\b/iu', $m[2]) || preg_match('/^\(?\d/', $m[2]) || ($contact && (AutoImport::looksPerson($m[2]) || preg_match('/^(the|for|at)\b/iu', $m[2])))) return [true, 'A contact line, not a name'];
        }
        if (preg_match(self::BOXES, $n)) return [true, 'A mailing address, not a business'];
        if (preg_match(self::SENTENCE, $n)) return [true, 'A sentence about something, not a name'];
        if (preg_match(self::DOC_WORDS, $n)) return [true, 'A document, event or form title'];
        if (preg_match('#^\s*(https?://|www\.)#i', $n) || preg_match('/@[a-z0-9-]+\.[a-z]{2,}/i', $n)) return [true, 'A web or email address'];
        if (preg_match('/^[\d\s().+\-]{7,}$/', $n)) return [true, 'A phone number'];
        $words = preg_split('/\s+/u', $n, -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) > 12 || mb_strlen($n) > 100) return [true, 'Too long for a name: a sentence from a page'];
        if (!preg_match('/\p{Lu}|\d/u', $n) && count($words) >= 4) return [true, 'A phrase from a page (no capital letters)'];
        return [false, ''];
    }

    // ---- the preview -----------------------------------------------------------------------------------------------

    /**
     * Every rule's finds, without changing anything: ['rules' => key => ['act', 'check', 'left', 'examples' => [guard =>
     * [item…]]], 'stores' => store => ['read', 'of', 'error'], 'at' => when]. $only: one rule's key, or '' for all.
     * An item: ['store', 'ref', 'name', 'rule', 'guard', 'why', 'link', 'target'].
     */
    public static function preview(PDO $pdo, string $only = ''): array
    {
        @set_time_limit(120);
        $out = ['rules' => [], 'stores' => [], 'at' => date('c')];
        foreach (self::RULES as $k => $r) $out['rules'][$k] = ['act' => 0, 'check' => 0, 'left' => 0, 'examples' => ['act' => [], 'check' => [], 'left' => []]];
        $add = function (array $it) use (&$out): void {
            $r = &$out['rules'][$it['rule']];
            $r[$it['guard']]++;
            if (count($r['examples'][$it['guard']]) < self::EXAMPLES) $r['examples'][$it['guard']][] = $it;
        };
        $want = fn (string $store) => $only === '' || (self::RULES[$only][0] ?? '') === $store;
        foreach (['listings' => 'findListings', 'off' => 'findOff', 'staged' => 'findStaged', 'store' => 'findStore', 'landmarks' => 'findLandmarks'] as $store => $fn) {
            if (!$want($store)) continue;
            try { $out['stores'][$store] = self::$fn($pdo, $add, $only); }
            catch (Throwable $x) { $out['stores'][$store] = ['read' => 0, 'of' => 0, 'error' => 'Couldn\'t read it: ' . self::plainError($x)]; error_log('DataCleanup ' . $store . ': ' . $x->getMessage()); }
        }
        if ($only !== '') $out['rules'] = array_intersect_key($out['rules'], [$only => 1]);
        return $out;
    }

    private static function plainError(Throwable $x): string
    {
        $m = $x->getMessage();
        if (preg_match("/Table '[^']*?\.?([a-z_]+)' doesn't exist|no such table: ([a-z_]+)/i", $m, $t)) return 'the table ' . ($t[1] ?: $t[2]) . ' isn\'t there yet';
        return 'a database error (in the log)';
    }

    private static function item(string $store, string $ref, string $name, string $rule, string $guard, string $why, string $link, string $target = '', array $data = []): array
    {
        return ['store' => $store, 'ref' => $ref, 'name' => mb_substr($name, 0, 160), 'rule' => $rule, 'guard' => $guard, 'why' => $why, 'link' => $link, 'target' => $target ?: self::RULES[$rule][3], 'data' => $data];
    }

    /** Every item a pass would act on or show, in full (preview() keeps only counts and examples). */
    public static function collect(PDO $pdo, string $only = ''): array
    {
        @set_time_limit(300);
        $all = []; $errors = [];
        $add = function (array $it) use (&$all): void { $all[] = $it; };
        foreach (['listings' => 'findListings', 'off' => 'findOff', 'staged' => 'findStaged', 'store' => 'findStore', 'landmarks' => 'findLandmarks'] as $store => $fn) {
            if ($only !== '' && (self::RULES[$only][0] ?? '') !== $store) continue;
            try { self::$fn($pdo, $add, $only); } catch (Throwable $x) { $errors[$store] = self::plainError($x); error_log('DataCleanup ' . $store . ': ' . $x->getMessage()); }
        }
        return ['items' => $all, 'errors' => $errors];
    }

    private static function jsonText($v): string
    {
        $d = json_decode((string) $v, true);
        if (is_string($d)) return $d;
        if (is_array($d)) return (string) ($d['value'] ?? $d['line'] ?? implode(' ', array_filter($d, 'is_scalar')));
        return (string) $v;
    }

    private static function host(string $u): string
    {
        $u = trim($u);
        if ($u === '') return '';
        return (string) preg_replace('/^www\./', '', strtolower((string) parse_url(preg_match('#^[a-z]+://#i', $u) ? $u : 'https://' . $u, PHP_URL_HOST)));
    }

    private static function isOsm(string $site): bool
    {
        return (bool) preg_match('/(^|\.)(openstreetmap\.org|osm\.org)$/', self::host($site) ?: strtolower($site));
    }

    /** A source's use rule for its host: 'cite' when we may only cite it (or ignore it), else what the register says. */
    private static function useRule(PDO $pdo, string $host): string
    {
        static $seen = [];
        if ($host === '') return 'facts';
        if (!isset($seen[$host])) {
            try { require_once __DIR__ . '/crawler/SourceIndex.php'; $seen[$host] = SourceIndex::useRule($pdo, $host); }
            catch (Throwable $x) { $seen[$host] = 'facts'; }
        }
        return $seen[$host];
    }

    // ---- listings --------------------------------------------------------------------------------------------------

    /** Source kinds that are where a value came into our records, not evidence of it. */
    private const PROVENANCE = ['crawler', 'import', 'evidence', 'rejected', 'replaced', 'dismissed'];
    /** Source kinds that are a person's word: keepable, and a person decided. */
    private const PERSON_KINDS = ['owner', 'admin', 'staff', 'member'];

    private static function findListings(PDO $pdo, callable $add, string $only): array
    {
        require_once __DIR__ . '/PlaceScope.php';
        $pub = implode(',', array_map(fn ($s) => $pdo->quote($s), PlaceScope::PUBLIC_STATUSES));
        $of = (int) $pdo->query("SELECT COUNT(*) FROM entities WHERE entity_type = 'merchant' AND created_by_type = 'ai_crawler' AND is_suppressed = 0 AND status IN ($pub)")->fetchColumn();
        $rows = $pdo->query("SELECT id, name, status, owner_user_id, updated_by_type, primary_zip, category_id, latitude, longitude, created_at, micro_cluster_id FROM entities
                              WHERE entity_type = 'merchant' AND created_by_type = 'ai_crawler' AND is_suppressed = 0 AND status IN ($pub)
                              ORDER BY id DESC LIMIT " . self::READ)->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return ['read' => 0, 'of' => $of, 'error' => ''];
        $ids = array_map(fn ($r) => (int) $r['id'], $rows);
        $meta = []; $raw = []; $facts = [];
        foreach (array_chunk($ids, 800) as $chunk) {
            $in = implode(',', $chunk);
            foreach ($pdo->query("SELECT entity_id, meta_key, meta_value, source_type, is_locked FROM entity_metadata WHERE entity_id IN ($in)
                                   AND meta_key IN ('phone', 'address', 'city', 'website', 'hours', 'closed', 'google', 'removed', 'unconfirmed')")->fetchAll(PDO::FETCH_ASSOC) as $m)
                $meta[(int) $m['entity_id']][$m['meta_key']] = $m;
            // who decided it: a person approving the find in the intake is a person's decision (R4)
            try {
                foreach ($pdo->query('SELECT entity_id, reviewed_by_user_id, source_url, JSON_EXTRACT(raw_payload, \'$.source\') AS src, JSON_EXTRACT(raw_payload, \'$.sources\') AS srcs,
                                             JSON_EXTRACT(raw_payload, \'$.registries\') AS regs, JSON_EXTRACT(raw_payload, \'$.corroboration\') AS corr, JSON_EXTRACT(raw_payload, \'$.actions.website\') AS site
                                        FROM ' . Database::t('crawler_raw_index') . " WHERE status = 'published' AND entity_id IN ($in)")->fetchAll(PDO::FETCH_ASSOC) as $r)
                    $raw[(int) $r['entity_id']] = $r;
            } catch (Throwable $x) { error_log('DataCleanup raw: ' . $x->getMessage()); }
            try {
                foreach ($pdo->query('SELECT f.record_id, f.fact, f.value, s.kind, s.site FROM ' . Database::t('identity_facts') . ' f JOIN ' . Database::t('identity_fact_sources') . " s ON s.fact_id = f.id
                                       WHERE f.record_kind = 'listing' AND f.record_id IN ($in) AND f.is_current = 1 AND f.fact IN ('phone', 'address', 'website', 'hours', 'point')
                                         AND f.status NOT IN ('rejected', 'replaced')")->fetchAll(PDO::FETCH_ASSOC) as $f)
                    $facts[(int) $f['record_id']][$f['fact']][] = $f;
            } catch (Throwable $x) { error_log('DataCleanup identity: ' . $x->getMessage()); }
        }
        $link = fn (int $id) => '/admin/listing-edit.php?id=' . $id;
        $want = fn (string $k) => $only === '' || $only === $k;
        $byPhone = [];
        // F9: the pilot's listings, the health group's categories, and each street's and phone's places (a person's practice)
        $pilot = array_flip(self::pilotIds($pdo)); $scope = self::legacyScope($pdo);
        $area = self::areaClusters($pdo); $inArea = fn (array $e) => !$area || isset($area[(int) ($e['micro_cluster_id'] ?? 0)]);   // F9's two rules: 1A first (the CEO)
        // the "health" scope (the CEO's (c), 2026-11-28; the session manager): the Health & Medical group, plus the safety
        // categories 0061's emergency needs already name (Situations.php:441, :442, :446). A public kind (Claims::KIND_GROUPS) is
        // told apart so one with any keepable source stays up
        $health = []; $public = [];
        try {
            $q = $pdo->prepare("SELECT c.id, c.name, c.slug, g.slug AS gslug, COALESCE(g.name, c.name) AS gname FROM categories c LEFT JOIN categories g ON g.id = c.parent_id");
            $q->execute();
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $c) {
                if ($c['slug'] === 'health-and-medical' || $c['gslug'] === 'health-and-medical' || in_array((string) $c['name'], self::SAFETY_CATEGORIES, true)) $health[(int) $c['id']] = true;
                if (in_array((string) $c['gname'], self::PUBLIC_GROUPS, true)) $public[(int) $c['id']] = true;
            }
        } catch (Throwable $x) { error_log('DataCleanup scope: ' . $x->getMessage()); }
        $practiceAt = [];
        foreach ($rows as $e) {
            $pm = $meta[(int) $e['id']] ?? []; $nm = (string) $e['name'];
            if (self::isPerson($nm)) continue;
            foreach (['address', 'phone'] as $k) if (isset($pm[$k])) { $v = self::placeKey($k, self::jsonText($pm[$k]['meta_value'])); if ($v !== '' && !isset($practiceAt[$v])) $practiceAt[$v] = ['id' => (int) $e['id'], 'name' => $nm]; }
        }
        foreach ($rows as $e) {
            $id = (int) $e['id']; $m = $meta[$id] ?? []; $r = $raw[$id] ?? null; $name = (string) $e['name'];
            $claimed = (int) $e['owner_user_id'] > 0 || $e['status'] !== 'unclaimed';
            $personField = fn (string $k) => isset($m[$k]) && ((int) $m[$k]['is_locked'] === 1 || in_array((string) $m[$k]['source_type'], ['admin', 'owner', 'user'], true));
            $touched = $claimed || ($r && (int) $r['reviewed_by_user_id'] > 0) || in_array((string) $e['updated_by_type'], ['admin', 'owner', 'user'], true)
                       || (bool) array_filter($m, fn ($x) => (int) $x['is_locked'] === 1 || in_array((string) $x['source_type'], ['admin', 'owner', 'user'], true));
            $whole = ($touched || isset($pilot[$id])) ? 'check' : 'act';   // the pilot's listings: always a person's check (F9)
            $phone = isset($m['phone']) ? preg_replace('/\D/', '', self::jsonText($m['phone']['meta_value'])) : '';
            $phone = strlen((string) $phone) >= 10 ? substr((string) $phone, -10) : '';
            $street = isset($m['address']) && preg_match('/^\s*#?\s*\d/', self::jsonText($m['address']['meta_value']));
            $f = $facts[$id] ?? [];
            // a fact's sources: OpenStreetMap only, cite-only pages only, or something we may keep
            $kind = function (string $fact) use ($f, $pdo): string {
                if (empty($f[$fact])) return 'unknown';
                $osm = false; $cite = false;
                foreach ($f[$fact] as $s) {
                    $k = (string) $s['kind']; $site = (string) $s['site'];
                    if (in_array($k, self::PERSON_KINDS, true) || $k === 'registry') return 'keep';
                    if ($site !== '' && self::isOsm($site)) { $osm = true; continue; }
                    if (in_array($k, self::PROVENANCE, true)) continue;
                    if ($site !== '' && self::useRule($pdo, $site) === 'cite') { $cite = true; continue; }
                    return 'keep';
                }
                return $osm ? 'osm' : ($cite ? 'cite' : 'unknown');
            };
            $town = isset($m['city']) || trim((string) $e['primary_zip']) !== '';
            if (self::dropName($name)) {   // a drop or collection box: its own kind of listing (Jason, 2026-11-15), never page text
                $drop = self::dropCategory($pdo);
                if ($want('drop_box') && ($drop === 0 || (int) $e['category_id'] !== $drop))
                    $add(self::item('listings', 'entity:' . $id, $name, 'drop_box', $drop === 0 ? 'left' : $whole,
                        $drop === 0 ? 'The "' . self::DROP_NAME . '" category isn\'t there yet (its database update runs within the hour)' : 'A drop or collection box: "' . self::DROP_NAME . '"' . ($e['latitude'] === null ? ' (no map point yet: its pin comes with one)' : ''), $link($id), '', ['category' => $drop]));
                if ($e['latitude'] !== null || $street || $phone !== '') continue;   // a box with its pin or address is found by it: no floor to meet
            }
            $junk = self::junkName($name);
            if ($junk[0]) {
                $to = self::route($name, $phone !== '', (bool) $street, $town);
                if ($want('junk_name')) $add(self::item('listings', 'entity:' . $id, $name, 'junk_name', $whole, $junk[1] . ($to === 'name_unknown' ? ': its phone or address kept, the name looked for' : ''), $link($id), $to));
                continue;   // one reason a listing: the strongest first
            }
            if (self::isPerson($name) && $inArea($e)) {   // F9: a person, not a place to visit (the CMO's ruling); in the pass's area
                $at = null;
                foreach (['address', 'phone'] as $k) if (!$at && isset($m[$k])) { $v = self::placeKey($k, self::jsonText($m[$k]['meta_value'])); if ($v !== '' && isset($practiceAt[$v]) && $practiceAt[$v]['id'] !== $id) $at = $practiceAt[$v]; }
                if ($want('person_place')) $add(self::item('listings', 'entity:' . $id, $name, 'person_place', $whole, $at ? 'A person, kept as a record of ' . $at['name'] . ' (#' . $at['id'] . ', the same ' . ($phone !== '' ? 'street or phone' : 'street') . ')' : 'A person; no practice found at the same street or phone, so kept as a record on its own', $link($id), 'quarantine', $at ? ['practice' => $at['id']] : []));
                continue;
            }
            $pk = $phone !== '' ? $kind('phone') : ''; $ak = $street ? $kind('address') : '';
            if ($phone === '' && !$street) {
                if ($want('no_floor')) $add(self::item('listings', 'entity:' . $id, $name, 'no_floor', $whole, $town ? 'A name and a town, no address or phone' : 'A name only: no town, address or phone', $link($id), $town ? 'help_find' : 'sighting'));
                continue;
            }
            $keepable = ($phone !== '' && !in_array($pk, ['osm', 'cite'], true)) || ($street && !in_array($ak, ['osm', 'cite'], true));
            if (!$keepable && ($pk === 'cite' || $ak === 'cite')) {
                if ($want('page_only')) $add(self::item('listings', 'entity:' . $id, $name, 'page_only', $whole, 'Its address and phone come only from pages we may cite, not keep', $link($id)));
                continue;
            }
            $legacy = (string) ($e['created_at'] ?? '') !== '' && (string) $e['created_at'] < self::LEGACY_BEFORE && $r === null;   // the September migration: never through the intake
            $anyKeep = (bool) array_filter(['phone', 'address', 'website', 'hours', 'point'], fn ($x) => $kind($x) === 'keep');
            if ($legacy && $inArea($e) && $pk !== 'keep' && $ak !== 'keep' && !(isset($public[(int) $e['category_id']]) && $anyKeep)) {   // a government or agency listing with any source we keep stays up
                $inScope = $scope === 'all' || ($scope === 'health' && isset($health[(int) $e['category_id']]));
                if ($want('legacy_unsourced')) $add(self::item('listings', 'entity:' . $id, $name, 'legacy_unsourced', $inScope ? $whole : 'left',
                    ($inScope ? '' : ($scope === 'off' ? 'Waiting on the CEO\'s scope (Data cleanup\'s legacy_scope is off): ' : 'Outside the scope set (' . $scope . '): ')) . 'From the September migration; no source we may keep confirms its ' . ($phone !== '' && $street ? 'phone or address' : ($phone !== '' ? 'phone' : 'address')), $link($id)));
                continue;
            }
            if ($want('osm_only')) {
                $osm = [];
                foreach (['phone' => 'phone', 'address' => 'address', 'website' => 'website', 'hours' => 'hours', 'point' => 'map point'] as $fact => $label)
                    if ((($fact === 'point') || isset($m[$fact])) && $kind($fact) === 'osm') $osm[$fact] = $label;
                $done = isset($m['unconfirmed']) ? (array) (json_decode((string) $m['unconfirmed']['meta_value'], true)['fields'] ?? []) : [];
                $osm = array_diff_key($osm, $done);   // marked on an earlier pass
                if ($osm) {
                    $g = $claimed || array_filter(array_keys($osm), fn ($k) => $personField($k)) ? 'check' : 'act';
                    $add(self::item('listings', 'entity:' . $id, $name, 'osm_only', $g, 'From OpenStreetMap only: ' . implode(', ', $osm) . (!$keepable ? ' (nothing else gives its address or phone)' : ''), $link($id), '', ['fields' => $osm]));
                }
            }
            if ($want('closed_one_site') && isset($m['closed'])) {
                $c = json_decode((string) $m['closed']['meta_value'], true);
                $c = is_array($c) ? $c : [];
                if ((int) ($c['by'] ?? 0) === 0) {
                    $note = (string) ($c['note'] ?? '');
                    if (preg_match('/^its own website says/i', $note)) $add(self::item('listings', 'entity:' . $id, $name, 'closed_one_site', 'left', 'Its own website says it closed: R12 lets that show as Closed', $link($id)));
                    else $add(self::item('listings', 'entity:' . $id, $name, 'closed_one_site', $claimed ? 'check' : 'act', 'Marked Closed by the crawler' . ($note !== '' ? ': ' . mb_substr($note, 0, 120) : ''), $link($id), '', ['note' => $note, 'at' => (string) ($c['at'] ?? '')]));
                }
            }
            if ($phone !== '') $byPhone[$phone][] = ['id' => $id, 'name' => $name, 'primary_zip' => (string) $e['primary_zip'], 'address' => $m['address']['meta_value'] ?? '', 'google' => $m['google']['meta_value'] ?? '',
                                                    'website' => isset($m['website']) ? self::jsonText($m['website']['meta_value']) : '', 'phone' => $phone, 'claimed' => $claimed];
        }
        if ($only === '' || $only === 'duplicate') self::findDupes($pdo, $add, $byPhone, $link);
        return ['read' => count($rows), 'of' => $of, 'error' => ''];
    }

    /** Pairs among the crawler's listings that look like one business (LiveDupes' test), not asked about before. */
    private static function findDupes(PDO $pdo, callable $add, array $byPhone, callable $link): void
    {
        require_once __DIR__ . '/crawler/LiveDupes.php';
        require_once __DIR__ . '/crawler/AutoImport.php';
        $asked = [];
        try {
            foreach ($pdo->query("SELECT entity_id, same_as_id FROM suggestions WHERE kind = 'same' AND same_as_id IS NOT NULL")->fetchAll(PDO::FETCH_NUM) as [$a, $b])
                $asked[min((int) $a, (int) $b) . '-' . max((int) $a, (int) $b)] = true;
        } catch (Throwable $x) {}
        foreach ($byPhone as $g) {
            if (count($g) < 2 || count($g) > 4) continue;   // more share a switchboard's line: proves nothing
            for ($i = 0; $i < count($g); $i++) for ($j = $i + 1; $j < count($g); $j++) {
                $why = LiveDupes::same($g[$i], $g[$j]);
                if ($why === '') continue;
                // the intake's own test (AutoImport::relationTo, decisions/0074): providers at one clinic, another location,
                // another business in the same building or a name covered by the other aren't duplicates
                $a = $g[$i]; $b = $g[$j];
                $rel = AutoImport::relationTo(['name' => $a['name'], 'actions' => ['phone' => $a['phone'], 'website' => $a['website']],
                    'where' => ['address' => self::jsonText($a['address']), 'zip' => substr($a['primary_zip'], 0, 5)]], $b['name'], $b['phone'], self::jsonText($b['address']), substr($b['primary_zip'], 0, 5), $b['website']);
                if (!in_array($rel['kind'], ['same', 'covered', 'maybe'], true)) continue;   // 'covered': one is the other's legal or parent name
                $why .= $rel['kind'] === 'maybe' ? ' (names only partly alike: a person decides)' : ($rel['kind'] === 'covered' ? ' (one name looks like the other\'s legal or parent name)' : '');
                $k = min($g[$i]['id'], $g[$j]['id']) . '-' . max($g[$i]['id'], $g[$j]['id']);
                $add(self::item('listings', 'pair:' . $k, $g[$i]['name'] . ' and ' . $g[$j]['name'], 'duplicate', isset($asked[$k]) ? 'left' : ($g[$i]['claimed'] || $g[$j]['claimed'] ? 'check' : 'act'),
                    isset($asked[$k]) ? 'Already asked about in Review' : 'The same phone, ' . $why . ' (#' . $g[$i]['id'] . ', #' . $g[$j]['id'] . ')', $link($g[$i]['id']), '', ['a' => $g[$i]['id'], 'b' => $g[$j]['id'], 'why' => $why]));
            }
        }
    }

    // ---- listings taken off earlier: back to work (Jason, 2026-11-15) ---------------------------------------------------

    /** The crawler's listings a cleanup pass hid before the routing of 2026-11-15 (quarantined, or a sighting with no intake row). */
    private static function findOff(PDO $pdo, callable $add, string $only): array
    {
        if ($only !== '' && $only !== 'taken_off') return ['read' => 0, 'of' => 0, 'error' => ''];
        $rows = $pdo->query("SELECT e.id, e.name, e.status, e.owner_user_id, e.primary_zip, e.latitude, r.meta_value AS removed
                               FROM entities e JOIN entity_metadata r ON r.entity_id = e.id AND r.meta_key = 'removed'
                              WHERE e.entity_type = 'merchant' AND e.created_by_type = 'ai_crawler' AND e.is_suppressed = 1 AND r.meta_value LIKE '%\"cleanup\":%'
                              ORDER BY e.id DESC LIMIT " . self::READ)->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return ['read' => 0, 'of' => 0, 'error' => ''];
        $ids = array_map(fn ($r) => (int) $r['id'], $rows);
        $meta = [];
        foreach (array_chunk($ids, 800) as $chunk)
            foreach ($pdo->query('SELECT entity_id, meta_key, meta_value FROM entity_metadata WHERE entity_id IN (' . implode(',', $chunk) . ") AND meta_key IN ('phone', 'address', 'city')")->fetchAll(PDO::FETCH_ASSOC) as $m)
                $meta[(int) $m['entity_id']][$m['meta_key']] = self::jsonText($m['meta_value']);
        $n = 0;
        foreach ($rows as $e) {
            $was = json_decode((string) $e['removed'], true);
            if (!is_array($was) || empty($was['cleanup'])) continue;
            $t = (string) ($was['target'] ?? '');
            if (!in_array($t, ['quarantine', 'sighting', ''], true) || ($t === 'sighting' && !empty($was['row']))) continue;   // already back at work, or restricted on purpose
            $n++;
            $id = (int) $e['id']; $m = $meta[$id] ?? [];
            $phone = strlen((string) preg_replace('/\D/', '', (string) ($m['phone'] ?? ''))) >= 10;
            $street = (bool) preg_match('/^\s*#?\s*\d/', (string) ($m['address'] ?? ''));
            $town = isset($m['city']) || trim((string) $e['primary_zip']) !== '';
            $to = self::route((string) $e['name'], $phone, $street, $town);
            if ($t === 'sighting' && $to === 'help_find') $to = 'sighting';   // page_only's sightings keep their road
            $claimed = (int) $e['owner_user_id'] > 0 || $e['status'] !== 'unclaimed';
            $add(self::item('off', 'entity:' . $id, (string) $e['name'], 'taken_off', $claimed ? 'check' : ($to === 'drop_off' && self::dropCategory($pdo) === 0 ? 'left' : 'act'),
                'Taken off as ' . ($t === 'sighting' ? 'a sighting' : 'page text') . ' on pass #' . (int) $was['cleanup'] . '; now: ' . ['drop_off' => 'a drop-off, put back', 'name_unknown' => 'a sighting "name unknown"', 'restricted' => 'Restricted', 'help_find' => 'a "Help us find" card', 'sighting' => 'a sighting'][$to],
                '/admin/listing-edit.php?id=' . $id, $to, ['was' => $was, 'category' => $to === 'drop_off' ? self::dropCategory($pdo) : 0]));
        }
        return ['read' => count($rows), 'of' => $n, 'error' => ''];
    }

    // ---- staged finds ------------------------------------------------------------------------------------------------

    private static function findStaged(PDO $pdo, callable $add, string $only): array
    {
        if ($only !== '' && $only !== 'staged_junk') return ['read' => 0, 'of' => 0, 'error' => ''];
        $T = Database::t('crawler_raw_index');
        $of = (int) $pdo->query("SELECT COUNT(*) FROM $T WHERE status = 'staged' AND payload_type = 'listing'")->fetchColumn();
        $rows = $pdo->query("SELECT id, reviewed_by_user_id, JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.name')) AS name FROM $T WHERE status = 'staged' AND payload_type = 'listing'
                              AND JSON_EXTRACT(raw_payload, '$.cleanup.kind') IS NULL ORDER BY id DESC LIMIT " . self::READ)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $j = self::junkName((string) $r['name']);
            if ($j[0]) $add(self::item('staged', 'staged:' . (int) $r['id'], (string) $r['name'], 'staged_junk', (int) $r['reviewed_by_user_id'] > 0 ? 'check' : 'act', $j[1], '/admin/listing-intake.php?row=' . (int) $r['id']));
        }
        return ['read' => count($rows), 'of' => $of, 'error' => ''];
    }

    // ---- the place store ---------------------------------------------------------------------------------------------

    private static function findStore(PDO $pdo, callable $add, string $only): array
    {
        if ($only !== '' && !in_array($only, ['store_junk', 'store_guard', 'store_cite'], true)) return ['read' => 0, 'of' => 0, 'error' => ''];
        require_once __DIR__ . '/crawler/SourceIndex.php';
        $F = Database::t('source_facts');
        $hasHeld = (bool) $pdo->query("SHOW COLUMNS FROM $F LIKE 'held'")->fetch();
        $of = (int) $pdo->query("SELECT COUNT(*) FROM $F" . ($hasHeld ? ' WHERE held IS NULL' : ''))->fetchColumn();
        $rich = (bool) $pdo->query("SHOW COLUMNS FROM $F LIKE 'category_id'")->fetch();
        $rows = $pdo->query("SELECT id, host, page_url, name, phone_digits, " . ($rich ? 'category_id, kind_words, section, website, lat, lng' : 'NULL AS category_id, NULL AS kind_words, NULL AS section, NULL AS website, NULL AS lat, NULL AS lng')
                            . " FROM $F" . ($hasHeld ? ' WHERE held IS NULL' : '') . ' ORDER BY id DESC LIMIT ' . self::READ)->fetchAll(PDO::FETCH_ASSOC);
        $want = fn (string $k) => $only === '' || $only === $k;
        $link = fn (array $r) => '/admin/insights/sources?q=' . rawurlencode((string) $r['host']);
        foreach ($rows as $r) {
            $name = (string) $r['name'];
            $j = self::junkName($name);
            if ($j[0]) { if ($want('store_junk')) $add(self::item('store', 'fact:' . (int) $r['id'], $name, 'store_junk', 'act', $j[1] . ' (' . $r['host'] . ')', $link($r), '', ['held' => 'junk'])); continue; }
            if ($want('store_guard')) {
                $pl = $r['lat'] !== null && $r['lng'] !== null ? [(float) $r['lat'], (float) $r['lng']] : null;
                $g = self::guardOnly($pdo, $r, $pl);
                if ($g !== '') { $add(self::item('store', 'fact:' . (int) $r['id'], $name, 'store_guard', 'act', ['protected' => 'May mark a sacred, burial or archaeological place', 'nation' => 'On a nation\'s land, with no phone or website', 'person' => 'Reads as a person\'s name'][$g] ?? $g, $link($r), 'hold:' . $g, ['held' => $g])); continue; }
            }
            if ($want('store_cite') && self::useRule($pdo, self::host((string) $r['host'])) === 'cite')
                $add(self::item('store', 'fact:' . (int) $r['id'], $name, 'store_cite', 'act', $r['host'] . '\'s rule is cite only', $link($r), '', ['held' => 'cite']));
        }
        return ['read' => count($rows), 'of' => $of, 'error' => $hasHeld ? '' : 'The place store has no held column yet: what would be held is shown, and holding waits for it.'];
    }

    /** SourceIndex::guard()'s verdict, without its marking the row (the preview writes nothing). */
    private static function guardOnly(PDO $pdo, array $r, ?array $pl): string
    {
        require_once __DIR__ . '/Landmarks.php';
        require_once __DIR__ . '/Nations.php';
        require_once __DIR__ . '/crawler/AutoImport.php';
        Landmarks::loadPeoples($pdo);
        $name = (string) ($r['name'] ?? '');
        $shape = $pl ? Nations::at($pdo, (float) $pl[0], (float) $pl[1]) : null;
        $text = trim($name . ' ' . (string) ($r['kind_words'] ?? '') . ' ' . (string) ($r['section'] ?? ''));
        if (Landmarks::isProtected($text, (string) $shape)
            && !Landmarks::publiclyKnown(['name' => $name, 'wd_desc' => trim((string) ($r['kind_words'] ?? '') . ' ' . (string) ($r['section'] ?? '')), 'nation' => (string) $shape])) return 'protected';   // as SourceIndex::guard() decides today
        if ($shape !== null && trim((string) ($r['phone_digits'] ?? '')) === '' && trim((string) ($r['website'] ?? '')) === '') return 'nation';
        if (AutoImport::looksPerson($name) && $r['category_id'] === null && !preg_match('/\b(overlook|mesa|park|trail|lake|canyon|peak|creek|springs?|butte|ranch|school|church|cent(er|re)|museum|hall|house|lodge|inn|cafe|store|shop|market|river|valley|point|rock|falls|farm|station|office|library|clinic|motel|hotel|bridge|monument|memorial|cemetery|fort)\b/i', $name)) return 'person';
        return '';
    }

    // ---- landmarks ------------------------------------------------------------------------------------------------------

    private static function findLandmarks(PDO $pdo, callable $add, string $only): array
    {
        if ($only !== '' && !in_array($only, ['protected_open', 'nation_sensitive', 'wiki_text'], true)) return ['read' => 0, 'of' => 0, 'error' => ''];
        require_once __DIR__ . '/Landmarks.php';
        Landmarks::loadPeoples($pdo);
        $of = (int) $pdo->query("SELECT COUNT(*) FROM landmarks WHERE status IN ('shown', 'approved')")->fetchColumn();
        $research = (bool) $pdo->query("SHOW COLUMNS FROM landmarks LIKE 'research'")->fetch();
        $rows = $pdo->query("SELECT gnis_id, name, fclass, nation, wd_desc, wiki_title, extract, status, why, reviewed_by, " . ($research ? 'research, about_queued_at' : "'' AS research, NULL AS about_queued_at") . " FROM landmarks
                              WHERE status IN ('shown', 'approved') ORDER BY gnis_id DESC LIMIT " . (self::READ * 3))->fetchAll(PDO::FETCH_ASSOC);
        $wiki = Landmarks::wikiAllowed($pdo);
        $want = fn (string $k) => $only === '' || $only === $k;
        $link = fn (array $r) => '/admin/local/places/' . (int) $r['gnis_id'];
        foreach ($rows as $r) {
            $person = $r['status'] === 'approved' || (int) $r['reviewed_by'] > 0;   // a person's decision stands (R4); never a machine's call on a sensitive place (R7)
            $name = (string) $r['name'];
            $text = $name . ' ' . (string) $r['wd_desc'];
            if ($want('protected_open') && Landmarks::isProtected($text, (string) $r['nation'])) {
                $add(self::item('landmarks', 'landmark:' . (int) $r['gnis_id'], $name, 'protected_open', $person ? 'check' : 'act',
                    ($person ? 'Approved by a person' : ((string) $r['why'] === Landmarks::KNOWN_WHY ? 'Shown on a machine\'s word (its own article, or a guide named it)' : 'Shown with its pin')) . ($r['nation'] !== '' ? ', on ' . $r['nation'] . '\'s land' : ''), $link($r)));
                continue;
            }
            if ($want('nation_sensitive') && trim((string) $r['nation']) !== '' && in_array((string) $r['fclass'], Landmarks::NATION_SENSITIVE_CLASSES, true)) {
                $add(self::item('landmarks', 'landmark:' . (int) $r['gnis_id'], $name, 'nation_sensitive', $person ? 'check' : 'act', 'A ' . strtolower((string) $r['fclass']) . ' on ' . $r['nation'] . '\'s land' . ($person ? ', approved by a person' : ''), $link($r)));
                continue;
            }
            if ($want('wiki_text') && !$wiki && trim((string) $r['extract']) !== '') {
                $has = trim((string) $r['research']) !== '';   // our own description is written: the text can go
                $add(self::item('landmarks', 'landmark:' . (int) $r['gnis_id'], $name, 'wiki_text', !$has && $r['about_queued_at'] !== null ? 'left' : 'act',
                    ($has ? 'Our own description is written: Wikipedia\'s words can go' : ($r['about_queued_at'] !== null ? 'Waiting for its description to be written, then cleared' : 'Its description is written first, then the text cleared'))
                    . ($r['wiki_title'] !== '' ? ' ("' . $r['wiki_title'] . '": the link stays)' : ''), $link($r), $has ? 'clear_text' : 'write_first', ['ready' => $has, 'queued' => $r['about_queued_at'] !== null]));
            }
        }
        // held by an earlier pass for its name, waiting for the story-only level (0164 R7, P5-3): told as a story now, when
        // it's publicly known and off a nation's land; the rest stay held. A person's press, logged, undoable.
        if ($want('protected_open')) {
            foreach (self::heldForStory($pdo) as $r) {
                $add(self::item('landmarks', 'landmark:' . (int) $r['gnis_id'], (string) $r['name'], 'protected_open', 'act', 'Held for its name until the story-only level: told as a story now (no pin)', $link($r), '', ['held' => 1]));
            }
        }
        return ['read' => count($rows), 'of' => $of, 'error' => ''];
    }

    /**
     * Landmarks an earlier pass held for their names (Restricted, "until the story-only level") that the level now takes:
     * still hidden by that change (not undone, not decided since), publicly known, off a nation's land.
     */
    public static function heldForStory(PDO $pdo, int $limit = 600): array
    {
        if (!self::ready($pdo)) return [];
        require_once __DIR__ . '/Landmarks.php';
        try {
            $st = $pdo->prepare("SELECT l.gnis_id, l.name, l.nation, l.wd_desc, l.wiki_title, l.extract, l.status FROM landmarks l
                                  JOIN (SELECT DISTINCT ref FROM " . Database::t('cleanup_ledger') . " WHERE rule = 'protected_open' AND field = 'status' AND new_value = 'hidden' AND undone_at IS NULL) g
                                    ON g.ref = CONCAT('landmark:', l.gnis_id)
                                 WHERE l.status = 'hidden' ORDER BY l.gnis_id DESC LIMIT " . max(1, min(2000, $limit)));
            $st->execute();
            return array_values(array_filter($st->fetchAll(PDO::FETCH_ASSOC), fn ($r) => trim((string) $r['nation']) === '' && Landmarks::publiclyKnown($r)));
        } catch (Throwable $x) { return []; }
    }

    // ---- acting: runs, the ledger and Undo (step 2) ---------------------------------------------------------------------

    /** Records acted on in one press; a bigger pass carries on with Continue (what's done no longer fails its rule). */
    public const BATCH = 600;
    /** Merge offers from one pass waiting in Review at once: Review isn't flooded (LiveDupes keeps 40). */
    public const MAX_OFFERS = 60;
    /** "Check these" kept on a run for the Pulse case. */
    private const CHECKS_KEPT = 400;
    public const DEVICE = 'crawler-cleanup';   // the crawler's own checks (Suggestions::OWN_SQL): Review's checkers, not Support

    public static function ready(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok === null) { try { $pdo->query('SELECT 1 FROM ' . Database::t('cleanup_ledger') . ' LIMIT 0'); $pdo->query('SELECT 1 FROM ' . Database::t('cleanup_runs') . ' LIMIT 0'); $ok = true; } catch (Throwable $x) { $ok = false; } }
        return $ok;
    }

    public static function setting(PDO $pdo, string $k): string
    {
        try { $st = $pdo->prepare('SELECT v FROM ' . Database::t('cleanup_settings') . ' WHERE k = ?'); $st->execute([$k]); return (string) ($st->fetchColumn() ?: ''); } catch (Throwable $x) { return ''; }
    }

    public static function setSetting(PDO $pdo, string $k, string $v, int $uid): void
    {
        $pdo->prepare('INSERT INTO ' . Database::t('cleanup_settings') . ' (k, v, by_id, at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v), by_id = VALUES(by_id), at = VALUES(at)')->execute([$k, $v, $uid, date('Y-m-d H:i:s')]);
    }

    /** The scheduled pass: off unless a person switches it on (and always under the automation master switch). */
    public static function autoOn(PDO $pdo): bool { return self::setting($pdo, 'auto') === 'on'; }

    /** F9 (2026-11-28): which September listings legacy_unsourced takes, as the CEO rules: off (shown, never acted on), health, all. */
    public const LEGACY_BEFORE = '2026-10-01';
    /** F9's area, as micro-cluster ids (cleanup_settings area_clusters, e.g. "1,2,47,55": regions.md's 1A row as R-D1 sets it,
     *  the session manager 2026-11-27). Empty: everywhere. Only person_place and legacy_unsourced read it. */
    public static function areaClusters(PDO $pdo): array
    {
        $ids = array_filter(array_map('intval', preg_split('/[\s,]+/', self::setting($pdo, 'area_clusters'), -1, PREG_SPLIT_NO_EMPTY)), fn ($i) => $i > 0);
        return array_flip($ids);
    }

    /** F9's rules, the ones a once-pass runs. */
    public const ONCE_RULES = ['person_place', 'legacy_unsourced'];

    /**
     * The once-pass a release asks for (the session manager, 2026-11-28; the IT Manager's design): its id, preview or run,
     * the scope and the area. Step one (the health-corrections release): a preview, whose exact counts go on Jason's batch
     * line. Step two, after his batch OK: a one-line release changes this to ['f9-run-1', 'run', …]. Asked in code, not by
     * a migration, because cleanup_settings may live in the admin database (data_moves) and a failed migration holds every
     * one after it; setSetting finds the table wherever it is. Empty: nothing asked.
     */
    public const ONCE = ['f9-preview-1', 'preview', 'health', '1,2,47,55'];

    /**
     * Taken by control_tick (it runs while automation is paused: a decided pass, not gathering). Each request runs once:
     * its marker (once:<id>) is written first with INSERT IGNORE, so a second tick, or two at once, never run it again,
     * and a failure never loops. A run bigger than one batch leaves pass_once = 'run' for the next tick: the same open
     * pass continued, never a retry of a failure.
     *   preview: counts per rule (act, check, left), nothing changed; kept at passonce:1
     *   run: each rule through run() (the ledger, Undo per record and per pass, "Check these"); kept at passonce:2, the
     *        preview's counts staying at passonce:1 beside it
     * Results are compact JSON in cleanup_settings under a kind:number key, so live-read's row read returns them whole.
     */
    public static function takeOnce(PDO $pdo): ?string
    {
        if (!self::ready($pdo)) return null;
        // live only: cleanup_settings is a records table, carried at Publish, so a marker staging wrote would stop live's pass
        // from ever running (and staging's listings aren't the ones to change); unsure which copy this is: not taken
        try { require_once __DIR__ . '/StagingGate.php'; if (StagingGate::here()) return null; } catch (Throwable) { return null; }
        $T = Database::t('cleanup_settings');
        $mode = '';
        if (self::setting($pdo, 'pass_once') === 'run') {   // a run's continuation
            $take = $pdo->prepare("UPDATE $T SET v = '', at = ? WHERE k = 'pass_once' AND v = 'run'"); $take->execute([date('Y-m-d H:i:s')]);
            if ($take->rowCount() === 1) $mode = 'run';
        }
        if ($mode === '' && self::ONCE && in_array(self::ONCE[1] ?? '', ['preview', 'run'], true)) {
            $mark = $pdo->prepare("INSERT IGNORE INTO $T (k, v, by_id, at) VALUES (?, ?, NULL, ?)");
            $mark->execute(['once:' . mb_substr((string) self::ONCE[0], 0, 34), 'taken', date('Y-m-d H:i:s')]);
            if ($mark->rowCount() !== 1) return null;   // done already, or another tick took it
            $mode = (string) self::ONCE[1];
            self::setSetting($pdo, 'legacy_scope', in_array(self::ONCE[2] ?? '', ['off', 'health', 'all'], true) ? (string) self::ONCE[2] : 'off', 0);
            self::setSetting($pdo, 'area_clusters', preg_replace('/[^0-9,]/', '', (string) (self::ONCE[3] ?? '')), 0);
        }
        if ($mode === '') return null;
        // compact, to fit cleanup_settings.v (VARCHAR(255), read live 2026-11-28): m mode, at, s scope, a area, then per rule
        // pp (person_place) and lu (legacy_unsourced) as [act, check, left] for a preview, [done, check, left, more] for a run
        $out = ['m' => $mode, 'at' => gmdate('Y-m-d H:i'), 's' => self::legacyScope($pdo), 'a' => mb_substr(self::setting($pdo, 'area_clusters'), 0, 40)];
        $short = ['person_place' => 'pp', 'legacy_unsourced' => 'lu'];
        $again = false;
        try {
            foreach (self::ONCE_RULES as $rule) {
                if ($mode === 'preview') {
                    $p = self::preview($pdo, $rule)['rules'][$rule] ?? [];
                    $out[$short[$rule]] = [(int) ($p['act'] ?? 0), (int) ($p['check'] ?? 0), (int) ($p['left'] ?? 0)];
                    continue;
                }
                $r = self::run($pdo, $rule, 0, 'the health-corrections pass (Jason\'s batch OK)', 'UTC', 'once');
                $c = json_decode((string) ($r['counts_json'] ?? ''), true) ?: [];
                $out[$short[$rule]] = [array_sum(array_map('intval', (array) ($c['done'] ?? []))), (int) ($c['check'] ?? 0), (int) ($c['left'] ?? 0), empty($c['more']) ? 0 : 1];
                if (!empty($c['more'])) { $again = true; break; }   // the rest next tick, the same open pass
            }
        } catch (Throwable $x) { $out['e'] = mb_substr(self::plainError($x), 0, 80); error_log('DataCleanup once: ' . $x->getMessage()); }
        // the preview's counts stay at passonce:1 beside the run's at passonce:2, so the batch line shows both
        $json = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        self::setSetting($pdo, $mode === 'preview' ? 'passonce:1' : 'passonce:2', mb_substr((string) $json, 0, 255), 0);
        if ($again && !isset($out['e'])) self::setSetting($pdo, 'pass_once', 'run', 0);
        return 'Data cleanup once-pass (' . $mode . '): ' . $json . ($again ? '; more next tick' : '');
    }

    /** The safety kinds in the health scope: exactly the categories 0061's emergency needs name (Situations.php:441, :442, :446).
     *  Shelters aren't named there, so they're not here (a gap, in the data-capture review). */
    public const SAFETY_CATEGORIES = ['Fire Departments', 'County Government-Fire Protection', 'Civil Defense Agencies', 'County Government-Public Order & Safety', 'Sheriff', 'Police Departments'];
    /** The public kinds (Claims::KIND_GROUPS' 'public'), whose listings stay up on any keepable source. */
    public const PUBLIC_GROUPS = ['Government & Public Services'];
    public static function legacyScope(PDO $pdo): string { $v = self::setting($pdo, 'legacy_scope'); return in_array($v, ['health', 'all'], true) ? $v : 'off'; }

    /** Listings named in the pilot plan (handoffs/hometown-theater/): never off the site by a pass, always "Check these".
     *  Hometown Theater #8807, and each invited participant Editorial (12) adds to the setting (ids, comma-separated). */
    public static function pilotIds(PDO $pdo): array
    {
        $ids = array_map('intval', preg_split('/[\s,]+/', '8807,' . self::setting($pdo, 'pilot_ids'), -1, PREG_SPLIT_NO_EMPTY));
        return array_values(array_unique(array_filter($ids, fn ($i) => $i > 0)));
    }

    /** A run that isn't finished (a pass bigger than one press), or null. */
    public static function openRun(PDO $pdo): ?array
    {
        if (!self::ready($pdo)) return null;
        $r = $pdo->query('SELECT * FROM ' . Database::t('cleanup_runs') . ' WHERE finished_at IS NULL AND undone_at IS NULL ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public static function runs(PDO $pdo, int $n = 10): array
    {
        if (!self::ready($pdo)) return [];
        return $pdo->query('SELECT * FROM ' . Database::t('cleanup_runs') . ' ORDER BY id DESC LIMIT ' . max(1, min(50, $n)))->fetchAll(PDO::FETCH_ASSOC);
    }

    /** The changes of a run, one row per record (its fields together), newest first. */
    public static function items(PDO $pdo, int $runId, int $limit = 60): array
    {
        $st = $pdo->prepare('SELECT ref, MIN(name) AS name, MIN(rule) AS rule, MIN(action) AS action, MIN(target) AS target, MIN(why) AS why, MIN(at) AS at, MIN(tz) AS tz,
                                    COUNT(*) AS n, SUM(undone_at IS NOT NULL) AS undone, MAX(undo_note) AS undo_note
                               FROM ' . Database::t('cleanup_ledger') . ' WHERE run_id = ? GROUP BY ref ORDER BY MAX(id) DESC LIMIT ' . max(1, min(500, $limit)));
        $st->execute([$runId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * One press of a pass: finds again, acts on up to BATCH records by rule ('act' only: never a person's), logs each
     * change, and keeps the "Check these" for the Pulse case. $only: one rule or '' for all. Returns the run.
     */
    public static function run(PDO $pdo, string $only, int $uid, string $who, string $tz = '', string $how = 'button'): array
    {
        if (!self::ready($pdo)) throw new RuntimeException('Data cleanup\'s database update hasn\'t run yet: it runs within the hour, or open System Configuration → Database updates.');
        if ($only !== '' && !isset(self::RULES[$only])) throw new InvalidArgumentException('Unknown rule.');
        $R = Database::t('cleanup_runs');
        $open = self::openRun($pdo);
        if ($open && (string) $open['rule'] !== $only) throw new RuntimeException('A pass for ' . ($open['rule'] === '' ? 'every rule' : self::RULES[$open['rule']][1]) . ' is still under way: Continue it first.');
        if ($open) $runId = (int) $open['id'];
        else {
            $pdo->prepare("INSERT INTO $R (rule, by_id, by_name, by_tz, how, started_at) VALUES (?, ?, ?, ?, ?, ?)")->execute([$only, $uid ?: null, mb_substr($who, 0, 120), mb_substr($tz, 0, 64), $how, date('Y-m-d H:i:s')]);
            $runId = (int) $pdo->lastInsertId();
        }
        self::settle($pdo);   // what Review decided about earlier sightings, first
        $found = self::collect($pdo, $only);
        $done = ['quarantined' => 0, 'restricted' => 0, 'sighting' => 0, 'name_unknown' => 0, 'help_find' => 0, 'drop_off' => 0, 'found' => 0, 'unconfirmed' => 0, 'caution' => 0, 'offered' => 0, 'held' => 0, 'story_only' => 0, 'cleared' => 0, 'queued' => 0, 'failed' => 0];
        $checks = []; $left = 0; $leftBy = []; $acted = 0; $more = false; $listings = []; $landmarks = [];
        $offers = self::openOffers($pdo);
        foreach ($found['items'] as $it) {
            if ($it['guard'] === 'check') { if (count($checks) < self::CHECKS_KEPT) $checks[] = array_intersect_key($it, array_flip(['ref', 'name', 'rule', 'why', 'link'])); continue; }
            if ($it['guard'] !== 'act') { $left++; $leftBy[self::leftWhy($it)] = ($leftBy[self::leftWhy($it)] ?? 0) + 1; continue; }
            if ($it['rule'] === 'duplicate' && $offers >= self::MAX_OFFERS) { $left++; $w = 'Over the cap of ' . self::MAX_OFFERS . ' open merge offers (the next pass offers more)'; $leftBy[$w] = ($leftBy[$w] ?? 0) + 1; continue; }   // Review isn't flooded: the next pass offers more
            if ($acted >= self::BATCH) { $more = true; break; }
            try {
                $k = self::act($pdo, $runId, $it, $uid, $tz);
                if ($k === '') { $left++; $w = self::leftWhy($it, true); $leftBy[$w] = ($leftBy[$w] ?? 0) + 1; continue; }
                $done[$k]++; $acted++;
                if ($k === 'offered') $offers++;
                if (str_starts_with($it['ref'], 'entity:')) $listings[] = (int) substr($it['ref'], 7);
                if (str_starts_with($it['ref'], 'landmark:')) $landmarks[] = (int) substr($it['ref'], 9);
            } catch (Throwable $x) { $done['failed']++; error_log('DataCleanup act ' . $it['ref'] . ': ' . $x->getMessage()); }
        }
        self::reindex($pdo, $listings, $landmarks);
        $prev = $open ? (json_decode((string) $open['counts_json'], true) ?: []) : [];
        foreach ($done as $k => $v) $done[$k] = $v + (int) ($prev['done'][$k] ?? 0);
        arsort($leftBy);   // why each was left, so "left" explains itself (Jason, 2026-11-15)
        $counts = ['done' => $done, 'left' => $left, 'left_by' => $leftBy, 'check' => count($checks), 'checks' => $checks, 'errors' => $found['errors'], 'more' => $more];
        $pdo->prepare("UPDATE $R SET counts_json = ?, finished_at = ? WHERE id = ?")->execute([json_encode($counts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $more ? null : date('Y-m-d H:i:s'), $runId]);
        $st = $pdo->prepare("SELECT * FROM $R WHERE id = ?"); $st->execute([$runId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** Why a pass left a record as it is, in a few words (the page's "left" breakdown). $nothing: its action found nothing to change. */
    public static function leftWhy(array $it, bool $nothing = false): string
    {
        $r = (string) $it['rule'];
        if ($nothing) return $r === 'wiki_text' ? 'Waiting for its own description' : ($r === 'protected_open' || $r === 'nation_sensitive' ? 'Approved by a person since' : 'Already as the rule wants');
        switch ($r) {
            case 'wiki_text': return 'Waiting for its own description (written while automation is on, or Write 10 now)';
            case 'duplicate': return 'Already asked about in Review';
            case 'closed_one_site': return 'Its own website says it closed (shown as Closed)';
            case 'drop_box': case 'taken_off': return 'The drop-off category isn\'t there yet';
        }
        return (self::RULES[$r][1] ?? $r) . ': ' . mb_substr((string) $it['why'], 0, 60);
    }

    private static function openOffers(PDO $pdo): int
    {
        try { $st = $pdo->prepare("SELECT COUNT(*) FROM suggestions WHERE device_hash = ? AND status = 'new'"); $st->execute([self::DEVICE]); return (int) $st->fetchColumn(); } catch (Throwable $x) { return 0; }
    }

    /** One record, by its rule's action. Returns what was done (a key of run()'s counts) or '' when nothing changed. */
    private static function act(PDO $pdo, int $runId, array $it, int $uid, string $tz): string
    {
        $ref = (string) $it['ref']; $rule = (string) $it['rule']; $target = (string) $it['target']; $d = (array) ($it['data'] ?? []);
        $why = 'Data cleanup: ' . self::RULES[$rule][1] . '. ' . $it['why'];
        $log = function (string $field, $new) use ($pdo, $runId, $it, $ref, $rule, $target, $why, $uid, $tz): void {
            $old = self::get($pdo, $ref, $field);
            if ($old === $new) return;
            self::put($pdo, $ref, $field, $new);
            $pdo->prepare('INSERT INTO ' . Database::t('cleanup_ledger') . ' (run_id, store, ref, name, rule, action, target, field, old_value, new_value, why, by_id, at, tz) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$runId, $it['store'], $ref, mb_substr((string) $it['name'], 0, 160), $rule, self::RULES[$rule][3], strtok($target, ':'), $field, $old, $new, mb_substr($why, 0, 300), $uid ?: null, date('Y-m-d H:i:s'), mb_substr($tz, 0, 64)]);
        };
        $meta = fn (array $v, string $src = 'admin', int $locked = 1) => json_encode(['v' => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 's' => $src, 'l' => $locked], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        switch ($rule) {
            case 'junk_name': case 'no_floor': case 'page_only': case 'person_place': case 'legacy_unsourced':   // off the public site, kept, with its reason and where it goes now
                $note = ['at' => date('Y-m-d'), 'by' => $uid, 'reason' => mb_substr($why, 0, 300), 'cleanup' => $runId, 'target' => $target];
                if (in_array($target, self::SIGHTINGS, true)) $note['row'] = self::toSighting($pdo, $runId, $it, (int) substr($ref, 7), $target, $uid, $tz);
                $log('meta:removed', $meta($note));
                $log('is_suppressed', '1');
                return $target === 'quarantine' ? 'restricted' : $target;
            case 'taken_off':   // hidden by an earlier pass: sent where it goes now
                $was = (array) ($d['was'] ?? []);
                if ($target === 'drop_off') {   // a drop box is a real place: put back, as its own kind
                    if ((int) ($d['category'] ?? 0) > 0) $log('category_id', (string) (int) $d['category']);
                    $log('meta:removed', null);
                    $log('is_suppressed', '0');
                    return 'drop_off';
                }
                $note = array_merge($was, ['target' => $target, 'routed' => date('Y-m-d'), 'routed_by' => $runId, 'reason' => mb_substr((string) ($was['reason'] ?? $why), 0, 300)]);
                if (in_array($target, self::SIGHTINGS, true)) $note['row'] = self::toSighting($pdo, $runId, $it, (int) substr($ref, 7), $target, $uid, $tz);
                $log('meta:removed', $meta($note));
                return $target;
            case 'drop_box':   // its own kind, "Mail & package drop-off" (its pin is the listing's point)
                if ((int) ($d['category'] ?? 0) <= 0) return '';
                $log('category_id', (string) (int) $d['category']);
                return 'drop_off';
            case 'osm_only':
                $cur = self::get($pdo, $ref, 'meta:unconfirmed');
                $have = $cur !== null ? (array) (json_decode((string) (json_decode($cur, true)['v'] ?? ''), true)['fields'] ?? []) : [];
                $f = $have + array_map(fn ($l) => 'From OpenStreetMap only: not yet confirmed', (array) ($d['fields'] ?? []));
                $log('meta:unconfirmed', $meta(['fields' => $f, 'at' => date('Y-m-d'), 'cleanup' => $runId], 'ai_crawler', 0));
                return 'unconfirmed';
            case 'closed_one_site':
                $log('meta:closed_signal', $meta(['line' => (string) ($d['note'] ?? '') !== '' ? (string) $d['note'] : 'One site said it closed', 'url' => '', 'at' => (string) ($d['at'] ?? '') ?: date('Y-m-d'), 'from' => 'cleanup'], 'ai_crawler', 0));
                $log('meta:closed', null);
                return 'caution';
            case 'duplicate':
                // the identity recheck settles duplicates (decisions/0164 §3.2's note, §6.1; Jason, 2026-11-15: "a recheck not
                // a pass to human for review"): the pair is sent to it; only once its keys are there, else offered as before
                require_once __DIR__ . '/ListingIdentity.php';
                if (ListingIdentity::ready($pdo)) {
                    ListingIdentity::pair($pdo, (int) $d['a'], (int) $d['b'], 'recheck', 'Data cleanup: ' . mb_substr((string) $it['why'], 0, 200));
                    $pdo->prepare('INSERT INTO ' . Database::t('cleanup_ledger') . ' (run_id, store, ref, name, rule, action, target, field, old_value, new_value, why, by_id, at, tz) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)')
                        ->execute([$runId, 'listings', $ref, mb_substr((string) $it['name'], 0, 160), $rule, 'recheck', 'merge', 'recheck', (string) $d['b'], mb_substr($why, 0, 300), $uid ?: null, date('Y-m-d H:i:s'), mb_substr($tz, 0, 64)]);
                    return 'rechecked';
                }
                require_once __DIR__ . '/Suggestions.php';
                $id = Suggestions::create($pdo, ['kind' => 'same', 'topic' => 'business', 'entity_id' => (int) $d['a'], 'same_as_id' => (int) $d['b'],
                    'note' => 'Found by Data cleanup: ' . $it['name'] . ' have ' . lcfirst((string) $it['why']) . '. Merge them if they\'re one business; turn it down if not (it isn\'t asked again).', 'context' => 'Data cleanup: duplicates'], null, self::DEVICE);
                $pdo->prepare('INSERT INTO ' . Database::t('cleanup_ledger') . ' (run_id, store, ref, name, rule, action, target, field, old_value, new_value, why, by_id, at, tz) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)')
                    ->execute([$runId, 'listings', $ref, mb_substr((string) $it['name'], 0, 160), $rule, 'merge', 'merge', 'suggestion', (string) $id, mb_substr($why, 0, 300), $uid ?: null, date('Y-m-d H:i:s'), mb_substr($tz, 0, 64)]);
                return 'offered';
            case 'staged_junk':
                $note = self::get($pdo, $ref, 'review_note');
                $log('review_note', mb_substr($why . ($note ? ' · ' . $note : ''), 0, 1000));
                $log('status', 'quarantined');
                return 'quarantined';
            case 'store_junk': case 'store_guard': case 'store_cite':
                $log('held', mb_substr((string) ($d['held'] ?? 'junk'), 0, 12));
                return 'held';
            case 'protected_open': case 'nation_sensitive':   // story only, or held ("Hidden by us" survives the monthly reload), a person's press
                $r = self::landmark($pdo, (int) substr($ref, 9));
                $held = !empty($d['held']) && $r && $r['status'] === 'hidden';   // held by an earlier pass, waiting for the story-only level
                if (!$r || (!$held && !in_array($r['status'], ['shown'], true))) return '';   // a person's approval is never undone by a machine
                require_once __DIR__ . '/Landmarks.php';
                $story = $rule === 'protected_open' && trim((string) $r['nation']) === '' && Landmarks::publiclyKnown($r);   // told, not placed (R7)
                $log('review_note', mb_substr($why . ($story ? ' Story only: named and told, no pin (R7).' : ''), 0, 300));
                $log('reviewed_by', (string) $uid);
                if ($story) { $log('why', Landmarks::STORY_WHY); $log('status', Landmarks::STORY_ONLY); return 'story_only'; }
                if ($held) return '';
                $log('status', 'hidden');
                if (trim((string) $r['nation']) !== '') { try { require_once __DIR__ . '/Nations.php'; Nations::log($pdo, (string) $r['nation'], 'landmark', (string) $r['gnis_id'], (string) $r['name'], 'shown', 'hidden', $uid, '', '', mb_substr($why, 0, 250)); } catch (Throwable $x) {} }
                return 'held';
            case 'wiki_text':
                if (!empty($d['ready'])) { $log('extract', ''); return 'cleared'; }
                if (!empty($d['queued'])) return '';
                $log('about_queued_at', date('Y-m-d H:i:s'));   // Place descriptions writes it (PlaceFinder::aboutTick), then a later pass clears
                return 'queued';
        }
        return '';
    }

    // ---- drop-offs' pins (Jason, 2026-11-15: "their own listing and map pin") -------------------------------------------

    /**
     * Drop-offs with no map point (or only their ZIP's middle): [with a street address (the Census can place them), without
     * one (a person drops a pin: Edit listing, "Pick on the map")].
     */
    public static function dropPins(PDO $pdo): array
    {
        $cat = self::dropCategory($pdo);
        if ($cat === 0) return [0, 0];
        try {
            require_once __DIR__ . '/Geocoder.php';
            $r = $pdo->query("SELECT SUM(a.meta_value REGEXP '^\"?[[:space:]]*#?[0-9]'), SUM(a.meta_value IS NULL OR NOT a.meta_value REGEXP '^\"?[[:space:]]*#?[0-9]')
                                FROM entities e LEFT JOIN entity_metadata a ON a.entity_id = e.id AND a.meta_key = 'address'
                               WHERE e.entity_type = 'merchant' AND e.is_suppressed = 0 AND e.category_id = " . $cat . ' AND ' . Geocoder::APPROX)->fetch(PDO::FETCH_NUM);
            return [(int) $r[0], (int) $r[1]];
        } catch (Throwable $x) { return [0, 0]; }
    }

    /**
     * A person's press: up to $n drop-offs with a street address placed by the U.S. Census geocoder (free, public data,
     * as every listing's point is: Geocoder::locateOne, which keeps a point a person set). OpenStreetMap's boxes are
     * its own layer and never copied here (decisions/0065). Returns [placed, tried].
     */
    public static function placeDrops(PDO $pdo, int $n = 25): array
    {
        $cat = self::dropCategory($pdo);
        if ($cat === 0) throw new RuntimeException('The "' . self::DROP_NAME . '" category isn\'t there yet: its database update runs within the hour.');
        require_once __DIR__ . '/Geocoder.php';
        $ids = $pdo->query("SELECT e.id FROM entities e JOIN entity_metadata a ON a.entity_id = e.id AND a.meta_key = 'address'
                             WHERE e.entity_type = 'merchant' AND e.is_suppressed = 0 AND e.category_id = " . $cat . ' AND ' . Geocoder::APPROX . "
                               AND a.meta_value REGEXP '^\"?[[:space:]]*#?[0-9]' ORDER BY e.id LIMIT " . max(1, min(50, $n)))->fetchAll(PDO::FETCH_COLUMN);
        @set_time_limit(30 + 10 * count($ids));
        $placed = 0;
        foreach ($ids as $id) { try { if (Geocoder::locateOne($pdo, (int) $id)) $placed++; } catch (Throwable $x) { error_log('DataCleanup place #' . $id . ': ' . $x->getMessage()); } }
        if ($placed) self::reindex($pdo, array_map('intval', $ids), []);
        return [$placed, count($ids)];
    }

    // ---- sightings: back to the intake, on the retry ladder (decisions/0163 §12) ------------------------------------------

    /**
     * Puts a listing (or a place-store fact) back in the intake as a sighting: a staged row in crawler_raw_index whose
     * payload says where it came from ('cleanup': the listing, the kind, the page to re-read) and when it's tried next
     * ('retry'). Its exact match is the hidden listing, so Review's Merge (or Approve) brings that one back: one record.
     * Logged as a 'sighting' change; Undo turns the row down while it's still waiting. Returns the row's id.
     */
    private static function toSighting(PDO $pdo, int $runId, array $it, int $entityId, string $kind, int $uid, string $tz, int $factId = 0): int
    {
        $T = Database::t('crawler_raw_index');
        $c = ['name' => '', 'where' => ['address' => '', 'city' => '', 'state' => '', 'zip' => ''], 'actions' => ['phone' => '', 'website' => ''], 'source' => ['site' => '', 'start_url' => ''],
              'confidence' => 0, 'match' => ['exact' => null, 'possible' => []], 'flags' => []];
        $page = ''; $sourceId = null;
        if ($entityId > 0) {
            $st = $pdo->prepare('SELECT id, name, primary_zip, status FROM entities WHERE id = ?'); $st->execute([$entityId]);
            $e = $st->fetch(PDO::FETCH_ASSOC) ?: ['name' => (string) $it['name'], 'primary_zip' => '', 'status' => 'unclaimed'];
            $m = [];
            $st = $pdo->prepare("SELECT meta_key, meta_value FROM entity_metadata WHERE entity_id = ? AND meta_key IN ('phone', 'address', 'city', 'state', 'website')"); $st->execute([$entityId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $m[$r['meta_key']] = self::jsonText($r['meta_value']);
            try {   // the page it was found on: re-read for its name, or for a source we may keep
                $st = $pdo->prepare("SELECT source_id, source_url FROM $T WHERE entity_id = ? AND status IN ('published', 'merged') ORDER BY id LIMIT 1"); $st->execute([$entityId]);
                if ($r = $st->fetch(PDO::FETCH_ASSOC)) { $page = (string) $r['source_url']; $sourceId = $r['source_id'] !== null ? (int) $r['source_id'] : null; }
            } catch (Throwable $x) {}
            $c['name'] = $kind === 'name_unknown' ? '' : (string) $e['name'];
            $c['where'] = ['address' => (string) ($m['address'] ?? ''), 'city' => (string) ($m['city'] ?? ''), 'state' => (string) ($m['state'] ?? ''), 'zip' => substr((string) $e['primary_zip'], 0, 5)];
            $c['actions'] = ['phone' => (string) ($m['phone'] ?? ''), 'website' => (string) ($m['website'] ?? '')];
            $c['match']['exact'] = ['id' => $entityId, 'name' => (string) $e['name'], 'status' => (string) $e['status']];
        } else {   // a place-store fact a person made a request
            $F = Database::t('source_facts');
            $st = $pdo->prepare("SELECT host, page_url, name, phone_digits FROM $F WHERE id = ?"); $st->execute([$factId]);
            $f = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $page = (string) ($f['page_url'] ?? '');
            $c['name'] = $kind === 'name_unknown' ? '' : (string) ($f['name'] ?? '');
            $c['actions']['phone'] = (string) ($f['phone_digits'] ?? '');
        }
        $site = self::host($page);
        $c['source'] = ['site' => $site, 'start_url' => $page];
        $c['cleanup'] = ['entity_id' => $entityId, 'fact_id' => $factId, 'run' => $runId, 'kind' => $kind, 'rule' => (string) $it['rule'], 'page_name' => $kind === 'name_unknown' ? (string) $it['name'] : '', 'reread' => $page, 'at' => date('c')];
        $c['retry'] = ['fails' => 0, 'next_at' => date('Y-m-d H:i:s', time() + 86400), 'last_at' => null, 'answer' => null];
        $c['flags'][] = ['code' => 'cleanup', 'message' => 'Back from Data cleanup (' . self::RULES[$it['rule']][1] . '): '
            . ($kind === 'name_unknown' ? 'its name was page text ("' . mb_substr((string) $it['name'], 0, 80) . '"), so it needs its real name; its phone and address are kept.'
                : 'it needs a phone or street address from a source we may keep.') . ($entityId > 0 ? ' Merge it into listing #' . $entityId . ' (hidden until then) once it has one.' : '') . ' Retried until a source answers.'];
        $pdo->prepare("INSERT INTO $T (source_id, source_url, payload_type, raw_payload, content_hash, confidence_score, status, matched_entity_id) VALUES (?, ?, 'listing', ?, ?, 0, 'staged', ?)")
            ->execute([$sourceId, mb_substr($page, 0, 500), json_encode($c, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), hash('sha256', 'cleanup:' . $entityId . ':' . $factId . ':' . $runId . ':' . $kind), $entityId > 0 ? $entityId : null]);
        $row = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO ' . Database::t('cleanup_ledger') . ' (run_id, store, ref, name, rule, action, target, field, old_value, new_value, why, by_id, at, tz) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)')
            ->execute([$runId, (string) $it['store'], (string) $it['ref'], mb_substr((string) $it['name'], 0, 160), (string) $it['rule'], self::RULES[$it['rule']][3], $kind, 'sighting', (string) $row,
                       mb_substr('Data cleanup: ' . self::RULES[$it['rule']][1] . '. ' . $it['why'], 0, 300), $uid ?: null, date('Y-m-d H:i:s'), mb_substr($tz, 0, 64)]);
        return $row;
    }

    /** The next try after $fails misses in a row (the ladder; under 3, the normal cadence of a day). */
    public static function nextTry(int $fails, ?int $now = null): string
    {
        $days = 1;
        // the retry ladder from W1's settings (0163 §12, a director's, with a Reset), else its starting steps
        $ladder = self::LADDER;
        try { require_once __DIR__ . '/WorkflowSettings.php'; $l = (array) (WorkflowSettings::current(Database::connection(), 'W1')['ladder'] ?? []); if ($l) { $ladder = []; foreach ($l as [$after, $d]) $ladder[(int) $after] = (int) $d; krsort($ladder); } } catch (Throwable) {}
        // retries end (gap review P3-2, P3-3, P4-1): after STOP_AFTER failures in a row (past a year on the ladder), it's kept
        // in its record and Review with its step in "The engine needs a person", and never retried by itself again
        if ($fails >= self::STOP_AFTER) return self::NEVER;
        foreach ($ladder as $after => $d) if ($fails >= $after) { $days = $d; break; }
        return date('Y-m-d H:i:s', ($now ?? time()) + $days * 86400);
    }

    /** Sightings from Data cleanup waiting in the intake: [waiting, answered, name unknown]. */
    public static function sightingCounts(PDO $pdo): array
    {
        try {
            $r = $pdo->query('SELECT COUNT(*), SUM(JSON_EXTRACT(raw_payload, \'$.retry.answer\') IS NOT NULL AND JSON_TYPE(JSON_EXTRACT(raw_payload, \'$.retry.answer\')) <> \'NULL\'), SUM(JSON_UNQUOTE(JSON_EXTRACT(raw_payload, \'$.cleanup.kind\')) = \'name_unknown\')
                                FROM ' . Database::t('crawler_raw_index') . " WHERE status = 'staged' AND JSON_EXTRACT(raw_payload, '$.cleanup.kind') IS NOT NULL")->fetch(PDO::FETCH_NUM);
            return [(int) $r[0], (int) $r[1], (int) $r[2]];
        } catch (Throwable $x) { return [0, 0, 0]; }
    }

    /**
     * The retry ladder for cleanup's sightings: each one due is looked up again in what our sources hold (the place
     * store, from sources we may keep: no fetch, no cost). An answer (a phone, or a real name for "name unknown") is
     * added to the sighting with its source for a person in Review and resets the ladder (new evidence); a miss moves it
     * a step down the ladder. It never gives up (0164 §6.1). Returns [looked, answered].
     */
    public static function retryTick(PDO $pdo, int $limit = 200): array
    {
        $T = Database::t('crawler_raw_index'); $F = Database::t('source_facts');
        try {
            $st = $pdo->prepare("SELECT id, raw_payload FROM $T WHERE status = 'staged' AND JSON_EXTRACT(raw_payload, '$.cleanup.kind') IS NOT NULL
                                  AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.retry.next_at')), '') <= ? ORDER BY id LIMIT " . max(1, min(1000, $limit)));
            $st->execute([date('Y-m-d H:i:s')]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            $hasHeld = (bool) $pdo->query("SHOW COLUMNS FROM $F LIKE 'held'")->fetch();
        } catch (Throwable $x) { error_log('DataCleanup retry: ' . $x->getMessage()); return [0, 0]; }
        $looked = 0; $answered = 0;
        $up = $pdo->prepare("UPDATE $T SET raw_payload = ? WHERE id = ? AND status = 'staged'");
        foreach ($rows as $r) {
            $c = json_decode((string) $r['raw_payload'], true);
            if (!is_array($c) || empty($c['cleanup'])) continue;
            $looked++;
            $answer = null;
            try { $answer = self::lookFor($pdo, $c, $F, $hasHeld); } catch (Throwable $x) { error_log('DataCleanup look #' . $r['id'] . ': ' . $x->getMessage()); }
            $t = (array) ($c['retry'] ?? []);
            $t['last_at'] = date('Y-m-d H:i:s');
            if ($answer) {
                $t['answer'] = $answer + ['at' => date('c')];
                $t['fails'] = 0;
                $c['flags'][] = ['code' => 'cleanup_answer', 'message' => 'A source answered: ' . $answer['what'] . ' (' . $answer['host'] . '). Check it, then Merge into the hidden listing.'];
                if (($c['cleanup']['kind'] ?? '') === 'name_unknown' && ($c['name'] ?? '') === '' && !empty($answer['name'])) $c['name'] = $answer['name'];
                if (($c['actions']['phone'] ?? '') === '' && !empty($answer['phone'])) $c['actions']['phone'] = $answer['phone'];
                $answered++;
            } else $t['fails'] = (int) ($t['fails'] ?? 0) + 1;
            $t['next_at'] = self::nextTry((int) $t['fails']);
            $c['retry'] = $t;
            $up->execute([json_encode($c, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int) $r['id']]);
        }
        return [$looked, $answered];
    }

    /** What our place store holds for a sighting, from a source we may keep: ['what', 'host', 'page', 'phone', 'name'] or null. */
    private static function lookFor(PDO $pdo, array $c, string $F, bool $hasHeld): ?array
    {
        $phone = substr((string) preg_replace('/\D/', '', (string) ($c['actions']['phone'] ?? '')), -10);
        $kind = (string) ($c['cleanup']['kind'] ?? '');
        $held = $hasHeld ? ' AND held IS NULL' : '';
        $found = [];
        if (strlen($phone) === 10) {   // the same phone elsewhere: a real name, or a source we may keep
            $st = $pdo->prepare("SELECT host, page_url, name, phone_digits FROM $F WHERE phone_digits LIKE ?$held ORDER BY id DESC LIMIT 20"); $st->execute(['%' . $phone]);
            $found = $st->fetchAll(PDO::FETCH_ASSOC);
        } elseif (trim((string) ($c['name'] ?? '')) !== '') {   // a name only: the same name with a phone (one phone only: a chain proves nothing)
            $st = $pdo->prepare("SELECT host, page_url, name, phone_digits FROM $F WHERE name = ? AND phone_digits <> ''$held ORDER BY id DESC LIMIT 20"); $st->execute([trim((string) $c['name'])]);
            $found = $st->fetchAll(PDO::FETCH_ASSOC);
            if (count(array_unique(array_map(fn ($f) => substr((string) $f['phone_digits'], -10), $found))) !== 1) return null;
        }
        $from = self::host((string) ($c['cleanup']['reread'] ?? ''));
        foreach ($found as $f) {
            $host = self::host((string) $f['host']) ?: (string) $f['host'];
            if ($host === '' || self::isOsm($host) || self::useRule($pdo, $host) === 'cite') continue;   // a lead, or cite only: not an answer
            if ($kind === 'name_unknown') {
                if (self::junkName((string) $f['name'])[0] || self::dropName((string) $f['name'])) continue;
                return ['what' => 'its name, "' . $f['name'] . '"', 'host' => $host, 'page' => (string) $f['page_url'], 'name' => (string) $f['name'], 'phone' => ''];
            }
            if ($host === $from && strlen($phone) === 10) continue;   // the page it already rested on
            return ['what' => 'its phone, from ' . $host, 'host' => $host, 'page' => (string) $f['page_url'], 'phone' => substr((string) $f['phone_digits'], -10), 'name' => ''];
        }
        return null;
    }

    /**
     * What Review decided about cleanup's sightings: approved or merged into the hidden listing, it's put back (as Help us
     * find's promote does); approved as another listing, the hidden one points to it ("found", one record); turned
     * down by a person, it's Restricted with that reason. A person's decision, so not in the ledger. Returns how many.
     */
    public static function settle(PDO $pdo, int $limit = 100): int
    {
        $T = Database::t('crawler_raw_index');
        try {
            $rows = $pdo->query("SELECT r.id, r.status, r.entity_id, r.reviewed_by_user_id, JSON_EXTRACT(r.raw_payload, '$.cleanup.entity_id') AS hid
                                   FROM $T r WHERE r.status IN ('published', 'merged', 'rejected') AND JSON_EXTRACT(r.raw_payload, '$.cleanup.kind') IS NOT NULL
                                    AND EXISTS (SELECT 1 FROM entity_metadata m WHERE m.entity_id = JSON_EXTRACT(r.raw_payload, '$.cleanup.entity_id') AND m.meta_key = 'removed'
                                                AND m.meta_value LIKE CONCAT('%\"row\":', r.id, '%'))
                                  ORDER BY r.id LIMIT " . max(1, min(500, $limit)))->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $x) { error_log('DataCleanup settle: ' . $x->getMessage()); return 0; }
        $n = 0;
        foreach ($rows as $r) {
            $hid = (int) $r['hid']; $to = (int) $r['entity_id'];
            try {
                $st = $pdo->prepare("SELECT meta_value FROM entity_metadata WHERE entity_id = ? AND meta_key = 'removed'"); $st->execute([$hid]);
                $was = json_decode((string) $st->fetchColumn(), true) ?: [];
                if ((int) ($was['row'] ?? 0) !== (int) $r['id']) continue;   // a newer road since
                $set = function (array $note) use ($pdo, $hid): void {
                    $pdo->prepare("UPDATE entity_metadata SET meta_value = ?, updated_at = NOW() WHERE entity_id = ? AND meta_key = 'removed'")->execute([json_encode($note, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $hid]);
                };
                if ($r['status'] === 'rejected') {
                    if ((int) $r['reviewed_by_user_id'] <= 0) continue;   // Undo turned it down: the ledger put the listing back already
                    $set(array_merge($was, ['target' => 'restricted', 'target_was' => (string) ($was['target'] ?? ''), 'reason' => 'Turned down in Review (sighting #' . (int) $r['id'] . ')', 'settled' => date('Y-m-d')]));
                } elseif ($to > 0 && $to !== $hid) {
                    $set(array_merge($was, ['target' => 'found', 'as' => $to, 'reason' => 'Found as listing #' . $to . ' (sighting #' . (int) $r['id'] . ')', 'settled' => date('Y-m-d')]));
                } else {
                    $st = $pdo->prepare("SELECT e.name, (SELECT meta_value FROM entity_metadata WHERE entity_id = e.id AND meta_key = 'phone') AS phone, (SELECT meta_value FROM entity_metadata WHERE entity_id = e.id AND meta_key = 'address') AS address FROM entities e WHERE e.id = ?");
                    $st->execute([$hid]); $now = $st->fetch(PDO::FETCH_ASSOC) ?: [];
                    $floor = strlen((string) preg_replace('/\D/', '', self::jsonText($now['phone'] ?? ''))) >= 10 || preg_match('/^\s*#?\s*\d/', self::jsonText($now['address'] ?? ''));
                    if (self::junkName((string) ($now['name'] ?? ''))[0] || !$floor) {   // merged, but still page text or no way to find it: Restricted, said why
                        $set(array_merge($was, ['target' => 'restricted', 'target_was' => (string) ($was['target'] ?? ''), 'reason' => 'Merged in Review (sighting #' . (int) $r['id'] . '), but ' . (!$floor ? 'it still has no phone or street address' : 'its name is still page text') . ': fix it in the editor, then Put back', 'settled' => date('Y-m-d')]));
                        $n++; continue;
                    }
                    $pdo->prepare("INSERT INTO entity_metadata (entity_id, meta_key, meta_value, source_type, is_locked, created_at, updated_at) VALUES (?, 'found', ?, 'admin', 1, NOW(), NOW())
                                   ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), updated_at = NOW()")
                        ->execute([$hid, json_encode(['at' => date('Y-m-d'), 'how' => 'Data cleanup: its sighting was approved in Review (#' . (int) $r['id'] . ')', 'was' => $was], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                    require_once __DIR__ . '/ListingIdentity.php';   // the one identity check before it's shown (decisions/0164 §3.1)
                    ListingIdentity::writeKeys($pdo, $hid);
                    if (ListingIdentity::beforeShow($pdo, $hid, false) !== null) { $n++; continue; }
                    $pdo->prepare("DELETE FROM entity_metadata WHERE entity_id = ? AND meta_key = 'removed'")->execute([$hid]);
                    $pdo->prepare('UPDATE entities SET is_suppressed = 0, updated_at = NOW() WHERE id = ?')->execute([$hid]);
                    self::reindex($pdo, [$hid], []);
                }
                $n++;
            } catch (Throwable $x) { error_log('DataCleanup settle #' . $r['id'] . ': ' . $x->getMessage()); }
        }
        return $n;
    }

    // ---- field access: one place that knows how each stored value is read and written ----------------------------------

    /** [table, key column, id] for a ref. */
    private static function where(string $ref): array
    {
        if (!preg_match('/^(entity|staged|fact|landmark|pair):([\d-]+)$/', $ref, $m)) throw new InvalidArgumentException('Unknown record.');
        return [$m[1], $m[2]];
    }

    private const COLUMNS = ['entity' => ['is_suppressed', 'category_id'], 'staged' => ['status', 'review_note'], 'fact' => ['held'], 'landmark' => ['status', 'review_note', 'reviewed_by', 'extract', 'about_queued_at', 'why']];

    /** The stored value now (a metadata row as JSON {v, s, l}), or null. */
    private static function get(PDO $pdo, string $ref, string $field): ?string
    {
        [$kind, $id] = self::where($ref);
        if ($kind === 'entity' && str_starts_with($field, 'meta:')) {
            $st = $pdo->prepare('SELECT meta_value, source_type, is_locked FROM entity_metadata WHERE entity_id = ? AND meta_key = ?'); $st->execute([(int) $id, substr($field, 5)]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            return $r ? json_encode(['v' => (string) $r['meta_value'], 's' => (string) $r['source_type'], 'l' => (int) $r['is_locked']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        }
        if (($kind === 'pair' && $field === 'suggestion') || $field === 'sighting') return null;
        if (!in_array($field, self::COLUMNS[$kind] ?? [], true)) throw new InvalidArgumentException('Unknown field.');
        [$t, $k] = self::table($kind);
        $st = $pdo->prepare("SELECT $field FROM $t WHERE $k = ?"); $st->execute([(int) $id]);
        $v = $st->fetchColumn();
        return $v === false || $v === null ? null : (string) $v;
    }

    private static function put(PDO $pdo, string $ref, string $field, ?string $v): void
    {
        [$kind, $id] = self::where($ref);
        if ($kind === 'entity' && str_starts_with($field, 'meta:')) {
            $key = substr($field, 5);
            if ($v === null) { $pdo->prepare('DELETE FROM entity_metadata WHERE entity_id = ? AND meta_key = ?')->execute([(int) $id, $key]); return; }
            $x = json_decode($v, true);
            $pdo->prepare('INSERT INTO entity_metadata (entity_id, meta_key, meta_value, source_type, is_locked, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                           ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), source_type = VALUES(source_type), is_locked = VALUES(is_locked), updated_at = NOW()')
                ->execute([(int) $id, $key, (string) $x['v'], (string) $x['s'], (int) $x['l']]);
            return;
        }
        if (!in_array($field, self::COLUMNS[$kind] ?? [], true)) throw new InvalidArgumentException('Unknown field.');
        [$t, $k] = self::table($kind);
        if ($kind === 'entity') { $pdo->prepare("UPDATE $t SET $field = ?, updated_by_type = 'admin', updated_at = NOW() WHERE id = ?")->execute([$v === null ? null : (int) $v, (int) $id]); return; }
        $pdo->prepare("UPDATE $t SET $field = ? WHERE $k = ?")->execute([$field === 'reviewed_by' && $v !== null ? (int) $v : $v, (int) $id]);
    }

    private static function table(string $kind): array
    {
        return ['entity' => ['entities', 'id'], 'staged' => [Database::t('crawler_raw_index'), 'id'], 'fact' => [Database::t('source_facts'), 'id'], 'landmark' => ['landmarks', 'gnis_id']][$kind];
    }

    private static function landmark(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare('SELECT gnis_id, name, nation, status, wd_desc, wiki_title, extract FROM landmarks WHERE gnis_id = ?'); $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private static function reindex(PDO $pdo, array $listings, array $landmarks): void
    {
        try {
            require_once __DIR__ . '/SearchIndex.php';
            foreach (array_chunk(array_values(array_unique($listings)), 200) as $c) SearchIndex::indexListings($pdo, $c);
            foreach (array_unique($landmarks) as $id) SearchIndex::refreshLandmark($pdo, $id);
        } catch (Throwable $x) { error_log('DataCleanup reindex: ' . $x->getMessage()); }
        if ($listings && is_file(__DIR__ . '/ListingConfidence.php')) { require_once __DIR__ . '/ListingConfidence.php'; foreach (array_slice(array_unique($listings), 0, 1500) as $id) ListingConfidence::refresh($pdo, $id); }
    }

    /**
     * Undo: one record's changes in a run ($ref), or the whole run ($ref ''). A field changed since (someone edited it,
     * a later pass) is left as it is now, and said: a person's later change wins. Returns [undone, left].
     */
    public static function undo(PDO $pdo, int $runId, string $ref, int $uid): array
    {
        if (!self::ready($pdo)) throw new RuntimeException('Data cleanup isn\'t set up yet.');
        $L = Database::t('cleanup_ledger');
        $st = $pdo->prepare("SELECT * FROM $L WHERE run_id = ? AND undone_at IS NULL" . ($ref !== '' ? ' AND ref = ?' : '') . ' ORDER BY id DESC');
        $st->execute($ref !== '' ? [$runId, $ref] : [$runId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) throw new RuntimeException($ref !== '' ? 'Nothing left to undo for it.' : 'Nothing left to undo in this pass.');
        $undone = 0; $left = 0; $listings = []; $landmarks = [];
        $mark = $pdo->prepare("UPDATE $L SET undone_at = ?, undone_by = ?, undo_note = ? WHERE id = ?");
        foreach ($rows as $r) {
            try {
                if ($r['field'] === 'sighting') {   // a sighting sent to the intake: turned down while it's still waiting
                    $T = Database::t('crawler_raw_index');
                    $s = $pdo->prepare("SELECT status FROM $T WHERE id = ?"); $s->execute([(int) $r['new_value']]);
                    if ((string) $s->fetchColumn() !== 'staged') { $mark->execute([date('Y-m-d H:i:s'), $uid, 'Already decided in Review: left as decided', (int) $r['id']]); $left++; continue; }
                    $pdo->prepare("UPDATE $T SET status = 'rejected', review_note = 'Undone in Data cleanup: the listing went back as it was' WHERE id = ? AND status = 'staged'")->execute([(int) $r['new_value']]);
                } elseif ($r['field'] === 'suggestion') {   // a merge offer: withdrawn while nobody has answered it
                    $s = $pdo->prepare('SELECT status FROM suggestions WHERE id = ?'); $s->execute([(int) $r['new_value']]);
                    if ((string) $s->fetchColumn() !== 'new') { $mark->execute([date('Y-m-d H:i:s'), $uid, 'Already answered in Review: left as decided', (int) $r['id']]); $left++; continue; }
                    require_once __DIR__ . '/Suggestions.php';
                    Suggestions::decide($pdo, (int) $r['new_value'], 'dismissed', $uid);
                } else {
                    $now = self::get($pdo, (string) $r['ref'], (string) $r['field']);
                    if ($now !== ($r['new_value'] === null ? null : (string) $r['new_value'])) { $mark->execute([date('Y-m-d H:i:s'), $uid, 'Changed since: left as it is now', (int) $r['id']]); $left++; continue; }
                    self::put($pdo, (string) $r['ref'], (string) $r['field'], $r['old_value'] === null ? null : (string) $r['old_value']);
                }
                $mark->execute([date('Y-m-d H:i:s'), $uid, 'Undone', (int) $r['id']]); $undone++;
                if (str_starts_with((string) $r['ref'], 'entity:')) $listings[] = (int) substr((string) $r['ref'], 7);
                if (str_starts_with((string) $r['ref'], 'landmark:')) $landmarks[] = (int) substr((string) $r['ref'], 9);
            } catch (Throwable $x) { $left++; error_log('DataCleanup undo #' . $r['id'] . ': ' . $x->getMessage()); }
        }
        // listings shown again by the Undo: the one identity check (decisions/0164 §3.1); a twin live already is a queue step
        if ($listings) { require_once __DIR__ . '/ListingIdentity.php'; foreach (array_unique($listings) as $lid) try { $h = $pdo->prepare('SELECT is_suppressed FROM entities WHERE id = ?'); $h->execute([$lid]); if ((string) $h->fetchColumn() === '0') ListingIdentity::beforeShow($pdo, $lid, true); } catch (Throwable) {} }
        self::reindex($pdo, $listings, $landmarks);
        if ($ref === '') $pdo->prepare('UPDATE ' . Database::t('cleanup_runs') . ' SET undone_at = ?, undone_by = ?, finished_at = COALESCE(finished_at, ?) WHERE id = ?')->execute([date('Y-m-d H:i:s'), $uid, date('Y-m-d H:i:s'), $runId]);
        return [$undone, $left];
    }

    /** A pass's changes grouped by their reason, with how many records are still changed: [[rule, why, n]]. */
    public static function groups(PDO $pdo, int $runId): array
    {
        $st = $pdo->prepare('SELECT rule, why, COUNT(DISTINCT ref) AS n FROM ' . Database::t('cleanup_ledger') . ' WHERE run_id = ? AND undone_at IS NULL GROUP BY rule, why HAVING n > 1 ORDER BY n DESC LIMIT 20');
        $st->execute([$runId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Undo every record a pass changed for one reason (Jason, 2026-11-15: the drop boxes): [undone, left]. */
    public static function undoGroup(PDO $pdo, int $runId, string $rule, string $why, int $uid): array
    {
        $st = $pdo->prepare('SELECT DISTINCT ref FROM ' . Database::t('cleanup_ledger') . ' WHERE run_id = ? AND rule = ? AND why = ? AND undone_at IS NULL');
        $st->execute([$runId, $rule, $why]);
        $u = 0; $l = 0;
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $ref) { try { [$a, $b] = self::undo($pdo, $runId, (string) $ref, $uid); $u += $a; $l += $b; } catch (RuntimeException $x) {} }
        if (!$u && !$l) throw new RuntimeException('Nothing left to undo for that reason.');
        return [$u, $l];
    }

    // ---- Restricted: every record held, its reason, Put back and Make it a request (Jason, 2026-11-15) ----------------

    /**
     * What's restricted now: the newest change on each record that's still in force and holds it (0164 R7's tier: kept
     * for our records only). Each: ['ref', 'run', 'name', 'rule', 'target', 'why', 'at', 'tz', 'store', 'request' => why
     * not ('' when Make it a request can)]. Listings Review turned down are here too. $rule narrows it; newest first.
     */
    public static function restricted(PDO $pdo, string $rule = '', int $limit = 200): array
    {
        if (!self::ready($pdo)) return [];
        $L = Database::t('cleanup_ledger');
        $rules = implode(',', array_map(fn ($r) => $pdo->quote($r), self::RESTRICTED_RULES));
        $targets = implode(',', array_map(fn ($r) => $pdo->quote($r), self::RESTRICTED_TARGETS));
        $st = $pdo->prepare("SELECT l.ref, l.run_id, l.name, l.rule, l.target, l.why, l.at, l.tz, l.store, l.field FROM $L l
                               JOIN (SELECT ref, MAX(id) AS id FROM $L WHERE undone_at IS NULL AND field NOT IN ('suggestion') GROUP BY ref) m ON m.id = l.id
                              WHERE (l.rule IN ($rules) OR l.target IN ($targets))" . ($rule !== '' ? ' AND l.rule = ?' : '') . ' ORDER BY l.id DESC LIMIT ' . max(1, min(2000, $limit * 2)));
        $st->execute($rule !== '' ? [$rule] : []);
        $out = [];
        foreach ($rule === 'review' ? [] : $st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ref = (string) $r['ref'];
            if ($r['field'] === 'sighting') continue;   // made a request since: at work
            if (str_starts_with($ref, 'staged:') && self::get($pdo, $ref, 'status') !== 'quarantined') continue;   // decided in Review since
            if (str_starts_with($ref, 'fact:') && self::get($pdo, $ref, 'held') === null) continue;
            if (str_starts_with($ref, 'landmark:') && self::get($pdo, $ref, 'status') !== 'hidden') continue;
            if (str_starts_with($ref, 'entity:')) {   // still hidden, and still restricted (not sent back to work since, nor put back in the editor)
                $note = self::get($pdo, $ref, 'meta:removed');
                $v = $note !== null ? (json_decode((string) (json_decode($note, true)['v'] ?? ''), true) ?: []) : [];
                if (!$v || !in_array((string) ($v['target'] ?? ''), self::RESTRICTED_TARGETS, true) || self::get($pdo, $ref, 'is_suppressed') !== '1') continue;
                if (!empty($v['reason'])) $r['why'] = (string) $v['reason'];
            }
            $r['request'] = self::canRequest($ref, (string) $r['rule']);
            $out[] = $r;
            if (count($out) >= $limit) break;
        }
        // listings Review turned down after a cleanup sighting (settle()): not a ledger change, a person's decision
        if (($rule === '' || $rule === 'review') && count($out) < $limit) {
            try {
                foreach ($pdo->query("SELECT e.id, e.name, m.meta_value FROM entities e JOIN entity_metadata m ON m.entity_id = e.id AND m.meta_key = 'removed'
                                       WHERE e.is_suppressed = 1 AND m.meta_value LIKE '%\"settled\":%' AND m.meta_value LIKE '%\"target\":\"restricted\"%' ORDER BY e.id DESC LIMIT " . ($limit - count($out)))->fetchAll(PDO::FETCH_ASSOC) as $e) {
                    $v = json_decode((string) $e['meta_value'], true) ?: [];
                    $out[] = ['ref' => 'entity:' . (int) $e['id'], 'run_id' => (int) ($v['cleanup'] ?? 0), 'name' => (string) $e['name'], 'rule' => 'review', 'target' => 'restricted', 'why' => (string) ($v['reason'] ?? 'Turned down in Review'),
                              'at' => (string) ($v['settled'] ?? ''), 'tz' => '', 'store' => 'listings', 'request' => '', 'settled' => true];
                }
            } catch (Throwable $x) {}
        }
        return $out;
    }

    /** How many are restricted, by rule. */
    public static function restrictedCounts(PDO $pdo): array
    {
        $by = [];
        foreach (self::restricted($pdo, '', 2000) as $r) $by[$r['rule']] = ($by[$r['rule']] ?? 0) + 1;
        arsort($by);
        return $by;
    }

    /** '' when Make it a request can act on this record, else why not (the button is greyed with it). */
    public static function canRequest(string $ref, string $rule): string
    {
        if (str_starts_with($ref, 'landmark:')) return $rule === 'nation_sensitive' ? 'The nation decides about its places (0164 R7): Put back is a person\'s call, never a request.' : 'Held for its name (a sacred, burial or archaeological word, or on a nation\'s land): Put back is a person\'s call.';
        if ($rule === 'store_guard') return 'Held by the guards (a protected place, a nation\'s land or a person\'s name, R6/R7): never a public request.';
        return '';
    }

    /**
     * Put back: every change the cleanup made to one record, undone (newest pass first), as Undo does. [undone, left].
     */
    public static function putBack(PDO $pdo, string $ref, int $uid): array
    {
        $st = $pdo->prepare('SELECT DISTINCT run_id FROM ' . Database::t('cleanup_ledger') . ' WHERE ref = ? AND undone_at IS NULL ORDER BY run_id DESC');
        $st->execute([$ref]);
        $u = 0; $l = 0;
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $run) { try { [$a, $b] = self::undo($pdo, (int) $run, $ref, $uid); $u += $a; $l += $b; } catch (RuntimeException $x) {} }
        if (str_starts_with($ref, 'entity:') && !$u) {   // turned down in Review (not a ledger change): a person's Put back lists it again
            $id = (int) substr($ref, 7);
            $note = self::get($pdo, $ref, 'meta:removed');
            if ($note !== null && str_contains($note, 'settled')) {
                $pdo->prepare("DELETE FROM entity_metadata WHERE entity_id = ? AND meta_key = 'removed'")->execute([$id]);
                $pdo->prepare("UPDATE entities SET is_suppressed = 0, updated_by_type = 'admin', updated_at = NOW() WHERE id = ?")->execute([$id]);
                require_once __DIR__ . '/ListingIdentity.php'; try { ListingIdentity::beforeShow($pdo, $id, true); } catch (Throwable) {}   // a twin live already: a step of the identity queue
                self::reindex($pdo, [$id], []);
                $u++;
            }
        }
        if (!$u && !$l) throw new RuntimeException('Nothing to put back for it.');
        return [$u, $l];
    }

    /**
     * Make it a request: a restricted record goes back to work, as a person's press (its own pass, logged, undoable).
     * A listing: a "Help us find" card when it has a town and a real name, else a sighting (or "name unknown" when its
     * name is page text but it has a phone or address). A staged find: back to waiting in Review. A place-store fact:
     * a sighting from its page, the fact still held. Landmarks and the guards' holds: never (canRequest()).
     */
    public static function request(PDO $pdo, string $ref, int $uid, string $who, string $tz = ''): string
    {
        if (!self::ready($pdo)) throw new RuntimeException('Data cleanup isn\'t set up yet.');
        [$kind, $id] = self::where($ref);
        $L = Database::t('cleanup_ledger');
        $st = $pdo->prepare("SELECT rule, name, store, why FROM $L WHERE ref = ? AND undone_at IS NULL ORDER BY id DESC LIMIT 1"); $st->execute([$ref]);
        $last = $st->fetch(PDO::FETCH_ASSOC) ?: ['rule' => 'junk_name', 'name' => '', 'store' => 'listings', 'why' => ''];
        if (($no = self::canRequest($ref, (string) $last['rule'])) !== '') throw new RuntimeException($no);
        $rule = isset(self::RULES[$last['rule']]) ? (string) $last['rule'] : 'junk_name';
        $R = Database::t('cleanup_runs');
        $pdo->prepare("INSERT INTO $R (rule, by_id, by_name, by_tz, how, started_at) VALUES (?, ?, ?, ?, 'request', ?)")->execute([$rule, $uid ?: null, mb_substr($who, 0, 120), mb_substr($tz, 0, 64), date('Y-m-d H:i:s')]);
        $runId = (int) $pdo->lastInsertId();
        $it = ['store' => (string) $last['store'], 'ref' => $ref, 'name' => (string) $last['name'], 'rule' => $rule, 'why' => 'Made a request by ' . ($who !== '' ? $who : 'staff'), 'target' => ''];
        $why = 'Data cleanup: ' . self::RULES[$rule][1] . '. ' . $it['why'];
        $log = function (string $field, ?string $new, string $target) use ($pdo, $runId, $it, $ref, $rule, $why, $uid, $tz, $L): void {
            $old = self::get($pdo, $ref, $field);
            if ($old === $new) return;
            self::put($pdo, $ref, $field, $new);
            $pdo->prepare("INSERT INTO $L (run_id, store, ref, name, rule, action, target, field, old_value, new_value, why, by_id, at, tz) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$runId, $it['store'], $ref, mb_substr((string) $it['name'], 0, 160), $rule, 'reuse', $target, $field, $old, $new, mb_substr($why, 0, 300), $uid ?: null, date('Y-m-d H:i:s'), mb_substr($tz, 0, 64)]);
        };
        $said = '';
        if ($kind === 'entity') {
            $st = $pdo->prepare("SELECT e.name, e.primary_zip, (SELECT meta_value FROM entity_metadata WHERE entity_id = e.id AND meta_key = 'phone') AS phone, (SELECT meta_value FROM entity_metadata WHERE entity_id = e.id AND meta_key = 'address') AS address,
                                        (SELECT COUNT(*) FROM entity_metadata WHERE entity_id = e.id AND meta_key = 'city') AS city FROM entities e WHERE e.id = ?");
            $st->execute([(int) $id]); $e = $st->fetch(PDO::FETCH_ASSOC);
            if (!$e) throw new RuntimeException('That listing isn\'t there any more.');
            $it['name'] = (string) $e['name'];
            $phone = strlen((string) preg_replace('/\D/', '', self::jsonText($e['phone'] ?? ''))) >= 10; $street = (bool) preg_match('/^\s*#?\s*\d/', self::jsonText($e['address'] ?? ''));
            $to = self::route((string) $e['name'], $phone, $street, (int) $e['city'] > 0 || trim((string) $e['primary_zip']) !== '');
            if ($to === 'restricted') $to = 'name_unknown';   // a person asked: back to the intake, for a person to name it
            if ($to === 'drop_off') $to = 'help_find';
            $cur = self::get($pdo, $ref, 'meta:removed');
            $note = $cur !== null ? (json_decode((string) (json_decode($cur, true)['v'] ?? ''), true) ?: []) : [];
            $note = array_merge($note, ['target' => $to, 'cleanup' => (int) ($note['cleanup'] ?? $runId), 'requested' => date('Y-m-d'), 'requested_by' => $uid, 'settled' => null]);
            unset($note['settled']);
            if (in_array($to, self::SIGHTINGS, true)) $note['row'] = self::toSighting($pdo, $runId, $it, (int) $id, $to, $uid, $tz);
            $log('meta:removed', json_encode(['v' => json_encode($note, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 's' => 'admin', 'l' => 1], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $to);
            $log('is_suppressed', '1', $to);
            $said = ['help_find' => 'It\'s a "Help us find" card on its town\'s page and in search.', 'sighting' => 'It\'s a sighting in Review, retried until a source answers.', 'name_unknown' => 'It\'s a sighting "name unknown" in Review, its real name looked for.'][$to];
        } elseif ($kind === 'staged') {
            $log('status', 'staged', 'sighting');
            $said = 'It\'s waiting in Review again.';
        } elseif ($kind === 'fact') {
            $st = $pdo->prepare('SELECT name, phone_digits FROM ' . Database::t('source_facts') . ' WHERE id = ?'); $st->execute([(int) $id]); $f = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $it['name'] = (string) ($f['name'] ?? $it['name']);
            $k = self::junkName($it['name'])[0] ? (strlen((string) ($f['phone_digits'] ?? '')) >= 10 ? 'name_unknown' : 'sighting') : 'sighting';
            self::toSighting($pdo, $runId, $it, 0, $k, $uid, $tz, (int) $id);
            $said = 'A sighting from its page is in Review; the fact itself stays held (its link kept as a citation).';
        } else throw new RuntimeException('That can\'t be made a request.');
        $pdo->prepare("UPDATE $R SET counts_json = ?, finished_at = ? WHERE id = ?")->execute([json_encode(['done' => ['reused' => 1], 'left' => 0, 'check' => 0, 'checks' => []]), date('Y-m-d H:i:s'), $runId]);
        return 'Made a request: ' . $said;
    }

    // ---- Wikipedia's words: replaced before they're cleared (Jason, 2026-11-14) ---------------------------------------

    /** [queued and waiting, written and ready to clear, still holding the text]. */
    public static function wikiCounts(PDO $pdo): array
    {
        try {
            $r = $pdo->query("SELECT SUM(about_queued_at IS NOT NULL AND research_at IS NULL), SUM(research IS NOT NULL AND research <> ''), COUNT(*) FROM landmarks WHERE extract <> '' AND status IN ('shown', 'approved')")->fetch(PDO::FETCH_NUM);
            return [(int) $r[0], (int) $r[1], (int) $r[2]];
        } catch (Throwable $x) { return [0, 0, 0]; }
    }

    /** Whether Place descriptions may write now: [true, ''] or [false, why] (AI use, the automation switches). */
    public static function canWrite(PDO $pdo): array
    {
        try { require_once __DIR__ . '/AiClient.php'; $a = AiClient::allow($pdo, 'place_about'); return [(bool) $a[0], (string) ($a[1] ?? '')]; } catch (Throwable $x) { return [false, 'AI use isn\'t set up here.']; }
    }

    /** A staff press: writes up to $n queued descriptions now (inside AI use's cap and switches). */
    public static function writeNow(PDO $pdo, int $n = 10): int
    {
        [$ok, $why] = self::canWrite($pdo);
        if (!$ok) throw new RuntimeException($why !== '' ? $why : 'Place descriptions can\'t write now.');
        require_once __DIR__ . '/PlaceFinder.php';
        return PlaceFinder::aboutTick($pdo, max(1, min(25, $n)));
    }

    // ---- Pulse: one case for what needs a person (CLAUDE.md: one case per kind of chore, a step per item) --------------

    /** The last pass that finished and wasn't undone, with its counts. */
    public static function lastRun(PDO $pdo): ?array
    {
        if (!self::ready($pdo)) return null;
        $r = $pdo->query('SELECT * FROM ' . Database::t('cleanup_runs') . ' WHERE finished_at IS NOT NULL AND undone_at IS NULL ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if (!$r) return null;
        $r['counts'] = json_decode((string) $r['counts_json'], true) ?: [];
        return $r;
    }

    /** Check these from the last pass that nobody has marked looked at, by rule. */
    public static function openChecks(PDO $pdo, ?array $run = null): array
    {
        $run = $run ?? self::lastRun($pdo);
        if (!$run) return [];
        $seen = [];
        try { foreach ($pdo->query('SELECT CONCAT(rule, \'|\', ref) FROM ' . Database::t('cleanup_checks'))->fetchAll(PDO::FETCH_COLUMN) as $k) $seen[$k] = true; } catch (Throwable $x) {}
        $by = [];
        foreach ((array) ($run['counts']['checks'] ?? []) as $c) if (!isset($seen[$c['rule'] . '|' . $c['ref']])) $by[$c['rule']][] = $c;
        return $by;
    }

    /** [key, severity, title, why, when, action word] for Pulse::items. */
    public static function pulseItems(PDO $pdo): array
    {
        $run = self::lastRun($pdo);
        if (!$run) return [];
        $by = self::openChecks($pdo, $run);
        $offers = self::openOffers($pdo);
        $n = array_sum(array_map('count', $by));
        if (!$n && !$offers && empty($run['counts']['more'])) return [];
        $d = (array) ($run['counts']['done'] ?? []);
        $report = 'Last pass ' . date('M j', strtotime((string) $run['finished_at'])) . ': ' . implode(', ', array_filter([
            ($w = ($d['sighting'] ?? 0) + ($d['name_unknown'] ?? 0) + ($d['help_find'] ?? 0)) ? $w . ' back to work (sightings and "Help us find")' : '',
            ($q = ($d['quarantined'] ?? 0) + ($d['restricted'] ?? 0)) ? $q . ' restricted' : '', ($d['drop_off'] ?? 0) ? $d['drop_off'] . ' drop-offs listed' : '',
            ($d['unconfirmed'] ?? 0) ? $d['unconfirmed'] . ' marked unconfirmed' : '', ($d['caution'] ?? 0) ? $d['caution'] . ' Closed to caution' : '',
            ($d['held'] ?? 0) + ($d['story_only'] ?? 0) ? (($d['held'] ?? 0) + ($d['story_only'] ?? 0)) . ' held' : '', ($d['offered'] ?? 0) ? $d['offered'] . ' merges offered' : '',
            ($d['cleared'] ?? 0) ? $d['cleared'] . ' Wikipedia texts replaced' : '', (int) ($run['counts']['left'] ?? 0) ? (int) $run['counts']['left'] . ' left' : '']) ?: ['nothing changed']) . '.';
        return [['cleanup:checks', 'update', 'Data cleanup needs a person: ' . ($n ? $n . ' to check' : '') . ($n && $offers ? ', ' : '') . ($offers ? $offers . ' merge offer' . ($offers === 1 ? '' : 's') . ' in Review' : ''),
                 'Records a person entered or decided, which the cleanup never changes, and what it offered for a person to decide. ' . $report, (string) $run['finished_at'], 'Look']];
    }

    /** The case's steps: a step per kind, each a button that does it (Pulse::manage → pulseDo). */
    public static function pulseSteps(PDO $pdo, callable $s): array
    {
        $st = []; $by = self::openChecks($pdo);
        foreach ($by as $rule => $list) {
            $names = implode('; ', array_map(fn ($c) => $c['name'] . ' (' . $c['why'] . ')', array_slice($list, 0, 5)));
            $st[] = $s('Check these: ' . self::RULES[$rule][1] . ' (' . count($list) . ')', 'A person entered or decided something on each, so the cleanup leaves them: open each and fix it by hand, or leave it. ' . $names . (count($list) > 5 ? '; and ' . (count($list) - 5) . ' more on the page.' : '.'),
                ['do', 'cleanup_seen:' . $rule, 'Looked at them']);
            $st[] = $s('Open the list', 'Insights → Data cleanup lists them with a link to each.', ['tool', '/admin/insights/cleanup?checks=' . $rule]);
        }
        if (($o = self::openOffers($pdo)) > 0) $st[] = $s('Answer the merge offers (' . $o . ')', 'In Review: merge the ones that are one business, turn down the rest (never asked again).', ['tool', '/admin/crawler-review.php?type=suggest']);
        $run = self::lastRun($pdo);
        if ($run && !empty($run['counts']['more'])) $st[] = $s('Finish the pass', 'It stopped at ' . self::BATCH . ' records a press; Continue carries on.', ['tool', '/admin/insights/cleanup']);
        $st[] = $s('Resolve the case', 'Once these are looked at. A new pass opens it again only for new ones.', ['resolve']);
        return $st;
    }

    /** Pulse::manage: "Looked at them" for one rule's Check these. */
    public static function pulseDo(PDO $pdo, string $what, int $uid): string
    {
        if (!preg_match('/^cleanup_seen:([a-z_]+)$/', $what, $m) || !isset(self::RULES[$m[1]])) throw new RuntimeException('Unknown step.');
        $list = self::openChecks($pdo)[$m[1]] ?? [];
        $ins = $pdo->prepare('INSERT IGNORE INTO ' . Database::t('cleanup_checks') . ' (ref, rule, looked_by, looked_at) VALUES (?, ?, ?, ?)');
        foreach ($list as $c) $ins->execute([$c['ref'], $m[1], $uid, date('Y-m-d H:i:s')]);
        return count($list) . ' marked looked at: they aren\'t asked about again unless they change.';
    }
}
