<?php

declare(strict_types=1);

require_once __DIR__ . '/crawler/Sources.php';
require_once __DIR__ . '/ListingSafety.php';

/**
 * Identities (decisions/0062): one record per thing, its facts by the 5 W's (and How to reach), every fact with its
 * sources. No fact without a source.
 *
 *   add()          a value for a fact, with one source; the same value from another source adds that source
 *   recompute()    each fact's status: confirmed, single or conflict; and which value is shown
 *   card()         the identity: facts by W, sources, what's missing, whether it's confirmed
 *   fromListing()  builds a listing's identity from everything we already hold: its record and details, what the
 *                  crawler found (its page and the websites that confirmed it), the sources we read, the NPI and IRS
 *                  registries, and the owner's claim. A confidential address (a shelter) is never stored: phone only.
 */
final class Identity
{
    /** fact => [W, label, one value only (so two values are a conflict)] */
    public const FACTS = [
        'name' => ['who', 'Name', true], 'other_name' => ['who', 'Other name', false], 'owner' => ['who', 'Claimed by', false], 'run_by' => ['who', 'Run by', false],
        'category' => ['what', 'Kind', false], 'offerings' => ['what', 'Offers', false], 'description' => ['what', 'About', false],
        'address' => ['where', 'Address', true], 'city' => ['where', 'Town', true], 'state' => ['where', 'State', true], 'zip' => ['where', 'ZIP', true], 'point' => ['where', 'Map point', true], 'nation' => ['where', 'On the land of', true],
        'hours' => ['when', 'Hours', true],
        'purpose' => ['why', 'Purpose', true], 'answers' => ['why', 'Answers', false],
        'phone' => ['how', 'Phone', true], 'website' => ['how', 'Website', true], 'email' => ['how', 'Email', true], 'social' => ['how', 'Social and directory pages', false],
    ];
    public const W = ['who' => 'Who', 'what' => 'What', 'where' => 'Where', 'when' => 'When', 'why' => 'Why', 'how' => 'How to reach'];
    /** Sources that confirm a value on their own: the owner, staff, an official registry. */
    private const STRONG = ['owner', 'admin', 'staff', 'registry'];   // our team's own input is as good as a registry (Jason, 2026-11-01: rural places often have nothing else)
    /** Sources that are provenance only (where a value came into our records), not independent confirmation. A value
     *  staff typed in the editor is 'staff'; 'admin' is kept for a value staff checked ("✎ I know this", step 3). */
    private const WEAK = ['crawler', 'member', 'import'];
    /** A person said this value is wrong ("Not right" in the helper): kept, so the crawler doesn't bring it back, never shown. */
    public const REJECTED = 'rejected';

    private static ?bool $ready = null;

    public static function ready(PDO $pdo): bool
    {
        if (self::$ready !== null) return self::$ready;
        try { $pdo->query('SELECT 1 FROM ' . Database::t('identity_facts') . ' LIMIT 1'); $pdo->query('SELECT 1 FROM ' . Database::t('identity_fact_sources') . ' LIMIT 1'); self::$ready = true; } catch (Throwable) { self::$ready = false; }
        return self::$ready;
    }

    // ---- values ----------------------------------------------------------------------------------------------------

    /** A phone's 10 digits: an extension removed first ("928-555-1234 ext 22" is 9285551234, decisions/0164 §3.1), a leading 1 dropped. */
    public static function digits(string $s): string
    {
        $s = (string) preg_replace('/\s*(?:,|;)?\s*(?:ext\.?|extension|x|#)\s*\d{1,6}\s*$/i', '', trim($s));
        $d = (string) preg_replace('/\D/', '', $s);
        if (strlen($d) === 11 && $d[0] === '1') $d = substr($d, 1);
        return strlen($d) >= 10 ? substr($d, -10) : '';
    }

    public static function host(string $url): string
    {
        if (!preg_match('#^https?://#i', $url)) $url = 'https://' . ltrim($url, '/');
        return preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
    }

    /** A website: Sources::site folds sub-domains, so two pages of one site count once. */
    public static function site(string $url): string { $h = self::host($url); return $h === '' ? '' : Sources::site($h); }

    /** "1662 S. 2nd Street" -> "1662 s 2nd st": the number and street, for comparing. */
    public static function addr(string $a): string
    {
        $a = mb_strtolower(preg_replace(['/#\s*[0-9a-z-]+/i', '/[.,#]/'], ['', ' '], $a));   // "#4" is a suite
        if (preg_match('/^\s*(p\s*o\s*)?(box|drawer)\s*\d+/', $a)) return '';   // a PO Box is never a street (decisions/0164 §3.1)
        // street types both ways: the long and the short form read the same ("Rd", "Road"; "Pkwy", "Parkway")
        $a = preg_replace(['/\b(street|str)\b/', '/\b(avenue|av)\b/', '/\broad\b/', '/\b(highway|hiway)\b/', '/\bdrive\b/', '/\blane\b/', '/\b(boulevard|boul)\b/', '/\bplace\b/', '/\bcourt\b/', '/\bparkway\b/',
                           '/\bcircle\b/', '/\b(route|rte)\b/', '/\bterrace\b/', '/\btrail\b/', '/\bsquare\b/', '/\bnorth\b/', '/\bsouth\b/', '/\beast\b/', '/\bwest\b/', '/\b(suite|ste|unit|apt|bldg|building|space|spc)\b.*$/'],
                          ['st', 'ave', 'rd', 'hwy', 'dr', 'ln', 'blvd', 'pl', 'ct', 'pkwy', 'cir', 'rt', 'ter', 'trl', 'sq', 'n', 's', 'e', 'w', ''], $a);
        return trim(preg_replace('/\s+/', ' ', $a));
    }

    /** A name for comparing: lower case, no punctuation or company endings. */
    public static function nameKey(string $n): string
    {
        $n = mb_strtolower(html_entity_decode($n));
        $n = preg_replace(['/&/', '/[^a-z0-9 ]+/', '/\b(the|inc|llc|ltd|lp|llp|pllc|pc|plc|co|corp|corporation|company|dba)\b/'], [' and ', ' ', ' '], $n);
        return trim(preg_replace('/\s+/', ' ', $n));
    }

    /** Share of one name's words in the other (0 to 1): "Ace Hardware" and "ACE HARDWARE OF ST JOHNS" share 1.0. */
    public static function nameMatch(string $a, string $b): float
    {
        $x = array_values(array_filter(explode(' ', self::nameKey($a)), fn ($w) => strlen($w) >= 2));
        $y = array_flip(array_filter(explode(' ', self::nameKey($b)), fn ($w) => strlen($w) >= 2));
        if (!$x || !$y) return 0.0;
        $hit = 0; foreach ($x as $w) if (isset($y[$w])) $hit++;
        return $hit / min(count($x), count($y));
    }

    public static function norm(string $fact, string $value): string
    {
        $v = trim($value);
        $n = match ($fact) {
            'phone' => self::digits($v),
            'website' => self::host($v),
            'address' => self::addr($v),
            'name', 'other_name' => self::nameKey($v),
            'email' => mb_strtolower($v),
            'point' => (function () use ($v) { $p = array_map('floatval', explode(',', $v)); return count($p) === 2 ? sprintf('%.3f,%.3f', $p[0], $p[1]) : ''; })(),
            default => mb_strtolower(preg_replace('/\s+/', ' ', $v)),
        };
        return mb_substr((string) $n, 0, 190);
    }

    /** A source kind from a website: as the source list classifies it, with open data and social named. */
    public static function kindFor(string $url, string $foundAs = ''): string
    {
        $h = self::host($url);
        if ($h === '') return 'crawler';
        if (preg_match('/(^|\.)openstreetmap\.org$/', $h)) return 'evidence';   // a lead: its share-alike licence would reach our record (decisions/0133)
        if (preg_match('/(^|\.)wikidata\.org$/', $h)) return 'open_data';   // CC0: ours to keep
        if ($foundAs === 'registry') return 'registry';
        [$k] = Sources::classify($h, $foundAs);
        return ['community_directory' => 'community', 'organization' => 'source', 'other' => 'source', 'commercial' => 'directory'][$k] ?? $k;
    }

    // ---- storing ---------------------------------------------------------------------------------------------------

    /**
     * A value for a fact, with one source: ['kind', 'url', 'label', 'private', 'by', 'note']. The same value (as
     * compared) from another source just adds the source. Returns the fact's id, or 0 when there's nothing to store.
     */
    public static function add(PDO $pdo, string $kind, int $id, string $fact, string $value, array $src): int
    {
        if (!isset(self::FACTS[$fact])) return 0;
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)));
        $norm = self::norm($fact, $value);
        if ($value === '' || $norm === '') return 0;
        $st = $pdo->prepare('SELECT id FROM ' . Database::t('identity_facts') . ' WHERE record_kind = ? AND record_id = ? AND fact = ? AND value_norm = ?');
        $st->execute([$kind, $id, $fact, $norm]);
        $fid = (int) $st->fetchColumn();
        if (!$fid) {
            try {
                $pdo->prepare('INSERT INTO ' . Database::t('identity_facts') . ' (record_kind, record_id, w, fact, value, value_norm, updated_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)')
                    ->execute([$kind, $id, self::FACTS[$fact][0], $fact, mb_substr($value, 0, 600), $norm]);
                $fid = (int) $pdo->lastInsertId();
            } catch (PDOException) { $st->execute([$kind, $id, $fact, $norm]); $fid = (int) $st->fetchColumn(); }
        }
        if (!$fid) return 0;
        $sk = (string) ($src['kind'] ?? 'crawler');
        $url = mb_substr((string) ($src['url'] ?? ''), 0, 500);
        $site = in_array($sk, ['owner', 'admin', 'member', 'staff'], true) ? '' : ($url !== '' ? self::site($url) : '');
        $up = $pdo->prepare('UPDATE ' . Database::t('identity_fact_sources') . ' SET url = CASE WHEN url = \'\' THEN ? ELSE url END, label = CASE WHEN label = \'\' THEN ? ELSE label END, checked_at = CURRENT_TIMESTAMP WHERE fact_id = ? AND kind = ? AND site = ?');
        $up->execute([$url, mb_substr((string) ($src['label'] ?? ''), 0, 190), $fid, $sk, $site]);
        if ($up->rowCount() === 0) {
            try {
                $pdo->prepare('INSERT INTO ' . Database::t('identity_fact_sources') . ' (fact_id, kind, site, url, label, private, added_by, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$fid, $sk, $site, $url, mb_substr((string) ($src['label'] ?? ''), 0, 190), !empty($src['private']) ? 1 : 0,
                               isset($src['by']) ? (int) $src['by'] : null, mb_substr((string) ($src['note'] ?? ''), 0, 300)]);
            } catch (PDOException) { /* the same source again */ }
        }
        return $fid;
    }

    /**
     * Each fact of a record: how many independent sources agree (each website once; the owner, staff and an official
     * registry each confirm on their own), its status, and which value is shown when sources disagree (the owner's,
     * else staff's, else the one most sources give, else the newest).
     */
    public static function recompute(PDO $pdo, string $kind, int $id): void
    {
        $st = $pdo->prepare('SELECT f.id, f.fact, s.kind, s.site FROM ' . Database::t('identity_facts') . ' f LEFT JOIN ' . Database::t('identity_fact_sources') . ' s ON s.fact_id = f.id WHERE f.record_kind = ? AND f.record_id = ?');
        $st->execute([$kind, $id]);
        $by = []; $factOf = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $fid = (int) $r['id']; $factOf[$fid] = (string) $r['fact'];
            $by[$fid] = $by[$fid] ?? ['sites' => [], 'strong' => [], 'regsites' => [], 'any' => 0, 'rejected' => false, 'replaced' => false];
            if ($r['kind'] === null) continue;
            if ($r['kind'] === self::REJECTED) { $by[$fid]['rejected'] = true; continue; }
            if ($r['kind'] === self::REPLACED) { $by[$fid]['replaced'] = true; continue; }
            $by[$fid]['any']++;
            if (in_array($r['kind'], self::STRONG, true)) { $by[$fid]['strong'][$r['kind']] = 1; if ($r['kind'] === 'registry' && $r['site'] !== '') $by[$fid]['regsites'][$r['site']] = 1; }
            elseif (!in_array($r['kind'], self::WEAK, true) && !in_array($r['kind'], self::LINK_ONLY, true) && $r['site'] !== '') $by[$fid]['sites'][$r['site']] = 1;   // a link (Google, Facebook, Yelp) is where to look, not evidence: we don't own what they say
        }
        $groups = [];
        foreach ($by as $fid => $b) $groups[$factOf[$fid]][] = $fid;
        $set = $pdo->prepare('UPDATE ' . Database::t('identity_facts') . ' SET status = ?, is_current = ?, sources_n = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        // independent sources: each site once (a registry read on its own site is that site), plus the owner and staff
        $cnt = fn (array $b) => count($b['sites'] + $b['regsites']) + count(array_diff_key($b['strong'], ['registry' => 1])) + (isset($b['strong']['registry']) && !$b['regsites'] ? 1 : 0);
        foreach ($groups as $fact => $fids) {
            $single = self::FACTS[$fact][2] ?? false;
            foreach ($fids as $k => $fid) if ($by[$fid]['rejected']) { $set->execute([self::REJECTED, 0, 0, $fid]); unset($fids[$k]); }
                elseif ($by[$fid]['replaced']) { $set->execute([self::REPLACED, 0, $cnt($by[$fid]), $fid]); unset($fids[$k]); }   // history: not shown, not written back
            $fids = array_values($fids);
            if (!$fids) continue;
            $rank = function (int $fid) use ($by, $cnt) { $b = $by[$fid]; return [isset($b['strong']['owner']) ? 1 : 0, isset($b['strong']['admin']) ? 1 : 0, $cnt($b), $fid]; };
            usort($fids, fn ($a, $b) => $rank($b) <=> $rank($a));
            // the owner or staff settled it: their value is shown and confirmed; the others stay, as not shown
            // evidence wins (2026-10-04): a value with more independent sources than any other settles it too; only a tie
            // in the evidence is a conflict for a person ("Our records" alone is where a value came from, not evidence)
            $ev = fn (int $f) => $cnt($by[$f]);
            $settled = $single && count($fids) > 1 && (isset($by[$fids[0]]['strong']['owner']) || isset($by[$fids[0]]['strong']['admin']) || $ev($fids[0]) > $ev($fids[1]));
            foreach ($fids as $i => $fid) {
                $b = $by[$fid]; $n = $cnt($b);
                $confirmed = $b['strong'] || count($b['sites']) >= 2;
                $status = $single && count($fids) > 1 && !$settled ? 'conflict' : ($confirmed ? 'confirmed' : 'single');
                $set->execute([$status, $single ? ($i === 0 ? 1 : 0) : 1, $n, $fid]);
            }
        }
        if ($kind === 'listing') { try { self::applyEvidence($pdo, $id); } catch (Throwable $e) { error_log('identity apply: ' . $e->getMessage()); } }
        if ($kind === 'listing' && is_file(__DIR__ . '/ListingConfidence.php')) { require_once __DIR__ . '/ListingConfidence.php'; ListingConfidence::refresh($pdo, $id); }   // its score follows its evidence (decisions/0133)
    }

    // ---- evidence wins, unless claimed (2026-10-04) ------------------------------------------------------------------

    /** Sources that are a link to look at, never a value we take on their own (Google, Yelp: decisions/0062). */
    private const LINK_ONLY = ['evidence'];
    /** The owner set a value aside ("Dismiss" on the evidence offered to them): kept so it isn't offered again. */
    public const DISMISSED = 'dismissed';

    public static function appliedReady(PDO $pdo): bool
    {
        static $r = null;
        if ($r !== null) return $r;
        try { $pdo->query('SELECT 1 FROM ' . Database::t('identity_applied') . ' LIMIT 1'); return $r = true; } catch (Throwable) { return $r = false; }
    }

    /**
     * Whether a listing is claimed and confirmed: then its owner decides, and evidence is only offered to them.
     * A listing that's only Managed by owner (an email claim, not confirmed yet) still takes evidence for its name, phone,
     * address and website (decisions/0070 §2); fields the owner set are locked and never changed either way.
     */
    public static function claimed(PDO $pdo, int $id): bool
    {
        $st = $pdo->prepare('SELECT owner_user_id, status FROM entities WHERE id = ?'); $st->execute([$id]);
        $e = $st->fetch(PDO::FETCH_ASSOC);
        return $e && in_array((string) $e['status'], ['verified', 'claimed'], true);
    }

    /** A value's independent evidence: each website once, plus a registry; never our own records or a link-only source. */
    private static function evidence(PDO $pdo, int $factId): array
    {
        $st = $pdo->prepare('SELECT kind, site, url, label, private FROM ' . Database::t('identity_fact_sources') . ' WHERE fact_id = ?'); $st->execute([$factId]);
        $sites = []; $person = false; $list = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $k = (string) $s['kind'];
            if (in_array($k, ['owner', 'admin', 'staff'], true)) { $person = true; continue; }
            if (in_array($k, self::WEAK, true) || in_array($k, self::LINK_ONLY, true) || in_array($k, [self::REJECTED, self::DISMISSED, self::REPLACED], true)) continue;
            $key = $k === 'registry' && (string) $s['site'] === '' ? 'registry:' . $s['label'] : (string) $s['site'];   // one site once, however it was found
            if ($key === '' || isset($sites[$key])) continue;
            $sites[$key] = 1;
            $list[] = ['kind' => $k, 'site' => (string) $s['site'], 'url' => (string) $s['url'], 'label' => (string) $s['label'], 'private' => (int) $s['private']];
        }
        return ['n' => count($sites), 'person' => $person, 'sources' => $list];
    }

    /** Whether $new keeps every word of $now ("Zuni Recovery Ctr" -> "Zuni Recovery Center"), short forms spelled out. */
    private static function fullerName(string $new, string $now): bool
    {
        require_once __DIR__ . '/crawler/AutoImport.php';
        $a = AutoImport::nameWords($now); $b = AutoImport::nameWords($new);
        return $a !== [] && !array_diff_key($a, $b);
    }

    /** What a listing value reads as, for comparing ("[\"Mon 9-5\"]" is "Mon 9-5"). */
    private static function plain($raw): string
    {
        $raw = (string) $raw;
        $j = json_decode($raw, true);
        if (is_array($j)) return implode('; ', array_map('strval', $j));
        if (is_string($j)) return $j;
        return $raw;
    }

    /**
     * Evidence wins: on a listing nobody has claimed, each fact the evidence settles (more independent sources than any
     * other value) is written to the listing, unless a person set that field (staff, the owner, a member's accepted
     * fix). A new name needs two sources, or one that shares the name's words. Each is logged (identity_applied) with
     * what was there, for Undo. A claimed listing is left alone: offers() shows its owner the evidence instead.
     */
    public static function applyEvidence(PDO $pdo, int $id): array
    {
        if (!self::appliedReady($pdo) || self::claimed($pdo, $id)) return [];
        $keys = "'" . implode("','", array_keys(self::KNOWABLE)) . "'";
        $st = $pdo->prepare("SELECT id, fact, value FROM " . Database::t('identity_facts') . " WHERE record_kind = 'listing' AND record_id = ? AND is_current = 1 AND status IN ('confirmed', 'single') AND fact IN ($keys)");
        $st->execute([$id]);
        $done = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $fact = (string) $f['fact']; $value = (string) $f['value'];
            $ev = self::evidence($pdo, (int) $f['id']);
            if ($ev['n'] < 1 || $ev['person']) continue;   // no evidence, or a person already decided (and it was written then)
            $cur = self::listingValue($pdo, $id, $fact);
            if ($cur !== null && self::norm($fact, self::plain($cur[0])) === self::norm($fact, $value)) continue;   // the listing already says it
            if ($cur !== null && ((int) $cur[2] === 1 || in_array((string) $cur[1], ['admin', 'owner', 'user'], true))) continue;   // a person set it
            if ($fact === 'name' && $ev['n'] < 2 && $cur !== null && self::nameMatch($value, (string) $cur[0]) < 0.5) continue;   // a different name: a person looks
            if ($fact === 'name' && $cur !== null && !self::fullerName($value, (string) $cur[0])) continue;
            if ($fact !== 'name' && $ev['n'] < 2 && $cur !== null && trim(self::plain($cur[0])) !== '') continue;   // replacing what a listing says takes two independent sources; one can fill a gap   // only a fuller form of the same name: never a legal name over the one people know
            // the pause (2026-11-28; the IT Manager's Tier 4 and its independent check): what the evidence would write itself,
            // About text and hours, from any path that recomputes (a merge's adopt, the identity worker, the place store), is
            // held for Review instead. Asked only here, after "a person set it" and "the listing already says it": a person's
            // own save (the owner's portal, the staff editor) has already written, and is never held
            if (in_array($fact, ['description', 'hours'], true)) {
                require_once __DIR__ . '/crawler/AutoImport.php';
                if (AutoImport::pausedWhy() !== null) { AutoImport::holdForReview($pdo, $id, [$fact => self::plain($value)], 'crawler-identity-paused', 'The evidence for this listing gives'); continue; }
            }
            self::setListing($pdo, $id, $fact, $value, false, ['ai_crawler', 0]);
            $pdo->prepare('INSERT INTO ' . Database::t('identity_applied') . ' (record_id, fact, fact_id, value, before_json, sources_n, applied_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$id, $fact, (int) $f['id'], mb_substr($value, 0, 400), $cur === null ? null : json_encode($cur, JSON_UNESCAPED_UNICODE), $ev['n'], date('Y-m-d H:i:s')]);
            $done[] = $fact;
        }
        return $done;
    }

    /** Undo an applied value: the listing gets what it had back, and that value is marked "Not right" so it stays out. */
    public static function unapply(PDO $pdo, int $appliedId, int $uid): array
    {
        $st = $pdo->prepare('SELECT * FROM ' . Database::t('identity_applied') . ' WHERE id = ? AND undone_at IS NULL'); $st->execute([$appliedId]);
        $a = $st->fetch(PDO::FETCH_ASSOC) ?: throw new InvalidArgumentException('That was already undone.');
        $pdo->prepare("INSERT INTO " . Database::t('identity_fact_sources') . " (fact_id, kind, site, url, label, private, added_by, note) VALUES (?, ?, '', '', 'Not right', 1, ?, 'Undone after it was applied from the evidence')")
            ->execute([(int) $a['fact_id'], self::REJECTED, $uid]);
        $pdo->prepare('UPDATE ' . Database::t('identity_applied') . ' SET undone_at = ?, undone_by = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $uid, $appliedId]);
        $before = $a['before_json'] !== null ? json_decode((string) $a['before_json'], true) : null;
        self::setListing($pdo, (int) $a['record_id'], (string) $a['fact'], $before, true);
        self::recompute($pdo, 'listing', (int) $a['record_id']);
        return ['listing' => (int) $a['record_id'], 'fact' => (string) $a['fact']];
    }

    /** What evidence wrote to listings lately: [[id, record_id, name, fact, value, before, sources_n, applied_at, undone_at]]. */
    public static function applied(PDO $pdo, int $limit = 30): array
    {
        if (!self::appliedReady($pdo)) return [];
        $st = $pdo->query('SELECT a.*, e.name FROM ' . Database::t('identity_applied') . ' a JOIN entities e ON e.id = a.record_id ORDER BY a.id DESC LIMIT ' . max(1, min(200, $limit)));
        return array_map(function ($r) {
            $b = $r['before_json'] !== null ? json_decode((string) $r['before_json'], true) : null;
            return ['id' => (int) $r['id'], 'record_id' => (int) $r['record_id'], 'name' => (string) $r['name'], 'fact' => (string) $r['fact'], 'label' => self::FACTS[$r['fact']][1] ?? $r['fact'],
                    'value' => (string) $r['value'], 'before' => is_array($b) ? self::plain($b[0] ?? '') : '', 'sources_n' => (int) $r['sources_n'], 'applied_at' => (string) $r['applied_at'], 'undone_at' => $r['undone_at']];
        }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * For a claimed listing's owner: what the evidence says that differs from their listing, each with its public
     * sources, to use or dismiss at their discretion. Never a private source, never a value staff marked "Not right".
     * [['fact_id', 'fact', 'label', 'value', 'now', 'sources' => [[kind, site, url, label]]]]
     */
    public static function offers(PDO $pdo, int $id): array
    {
        if (!self::ready($pdo)) return [];
        $keys = "'" . implode("','", array_keys(self::KNOWABLE)) . "'";
        $st = $pdo->prepare("SELECT id, fact, value FROM " . Database::t('identity_facts') . " WHERE record_kind = 'listing' AND record_id = ? AND status <> ? AND fact IN ($keys) ORDER BY sources_n DESC, id DESC");
        $st->execute([$id, self::REJECTED]);
        $out = []; $seen = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $fact = (string) $f['fact']; $value = (string) $f['value'];
            $d = $pdo->prepare('SELECT 1 FROM ' . Database::t('identity_fact_sources') . ' WHERE fact_id = ? AND kind IN (?, ?, ?)'); $d->execute([(int) $f['id'], self::DISMISSED, self::REJECTED, self::REPLACED]);
            if ($d->fetchColumn()) continue;
            $ev = self::evidence($pdo, (int) $f['id']);
            $pub = array_values(array_filter($ev['sources'], fn ($x) => !$x['private']));
            if (!$pub) continue;
            $cur = self::listingValue($pdo, $id, $fact);
            $now = $cur !== null ? self::plain($cur[0]) : '';
            if (self::norm($fact, $now) === self::norm($fact, $value)) continue;
            $k = $fact . '|' . self::norm($fact, $value);
            if (isset($seen[$k])) continue;
            $seen[$k] = 1;
            $out[] = ['fact_id' => (int) $f['id'], 'fact' => $fact, 'label' => self::FACTS[$fact][1] ?? $fact, 'value' => $value, 'now' => $now,
                      'sources' => array_map(fn ($x) => ['kind' => $x['kind'], 'site' => $x['site'], 'url' => $x['url'], 'label' => $x['label']], array_slice($pub, 0, 3))];
        }
        return $out;
    }

    /** The owner takes an offered value: it becomes theirs (the owner as its source) and goes on their listing. */
    public static function ownerUse(PDO $pdo, int $id, int $factId, int $uid): void
    {
        $st = $pdo->prepare("SELECT fact, value FROM " . Database::t('identity_facts') . " WHERE id = ? AND record_kind = 'listing' AND record_id = ?"); $st->execute([$factId, $id]);
        $f = $st->fetch(PDO::FETCH_ASSOC) ?: throw new InvalidArgumentException('That information is gone.');
        if (!isset(self::KNOWABLE[$f['fact']])) throw new InvalidArgumentException('That can\'t be set from here.');
        $pdo->prepare("INSERT INTO " . Database::t('identity_fact_sources') . " (fact_id, kind, site, url, label, private, added_by, note) VALUES (?, 'owner', '', '', 'The owner', 0, ?, 'Used from the evidence offered')")->execute([$factId, $uid]);
        self::setListing($pdo, $id, (string) $f['fact'], (string) $f['value'], false, ['owner', 1]);
        self::recompute($pdo, 'listing', $id);
    }

    /** The owner sets an offered value aside: kept, not offered again, never shown. */
    public static function ownerDismiss(PDO $pdo, int $id, int $factId, int $uid): void
    {
        $st = $pdo->prepare("SELECT 1 FROM " . Database::t('identity_facts') . " WHERE id = ? AND record_kind = 'listing' AND record_id = ?"); $st->execute([$factId, $id]);
        if (!$st->fetchColumn()) throw new InvalidArgumentException('That information is gone.');
        $pdo->prepare("INSERT INTO " . Database::t('identity_fact_sources') . " (fact_id, kind, site, url, label, private, added_by, note) VALUES (?, ?, '', '', 'Dismissed by the owner', 1, ?, '')")->execute([$factId, self::DISMISSED, $uid]);
    }

    // ---- reading ---------------------------------------------------------------------------------------------------

    /** The key facts an identity needs to be confirmed: a name, where it is, and a way to reach it. */
    public const KEY = ['who' => ['name'], 'where' => ['address', 'point'], 'how' => ['phone', 'website']];

    /**
     * The kinds of record with an identity (step 6): listings; places (landmarks, and Recreation.gov's areas and
     * facilities, as 'recarea' and 'facility' with the number from their id); resource lines. Events and Market posts
     * get the same card when they're built. key: what makes one confirmed.
     */
    public const KINDS = [
        'listing' => ['Listing', 'Listings', ['who' => ['name'], 'where' => ['address', 'point'], 'how' => ['phone', 'website']]],
        'landmark' => ['Landmark', 'Landmarks', ['who' => ['name'], 'where' => ['point']]],
        'recarea' => ['Outdoor place', 'Outdoor places', ['who' => ['name'], 'where' => ['point']]],
        'facility' => ['Outdoor place', 'Outdoor places', ['who' => ['name'], 'where' => ['point']]],
        'resource' => ['Resource line', 'Resource lines', ['who' => ['name'], 'how' => ['phone']]],
    ];
    public const COMING = ['event' => 'Events', 'item' => 'Market posts'];

    /**
     * The identity: ['facts' => [W => [[fact, label, value, status, current, sources_n, sources => [[kind, site, url, label, private, note, checked_at]]]]],
     * 'missing' => [labels], 'conflicts' => n, 'confirmed' => bool, 'private_where' => bool]. $staff: private sources too.
     */
    public static function card(PDO $pdo, string $kind, int $id, bool $staff = true): array
    {
        $out = ['facts' => array_fill_keys(array_keys(self::W), []), 'missing' => [], 'conflicts' => 0, 'confirmed' => false];
        if (!self::ready($pdo)) return $out;
        $st = $pdo->prepare('SELECT * FROM ' . Database::t('identity_facts') . ' WHERE record_kind = ? AND record_id = ? ORDER BY w, fact, is_current DESC, sources_n DESC');
        $st->execute([$kind, $id]);
        $facts = $st->fetchAll(PDO::FETCH_ASSOC);
        $src = [];
        if ($facts) {
            $ids = implode(',', array_map(fn ($f) => (int) $f['id'], $facts));
            foreach ($pdo->query("SELECT * FROM " . Database::t('identity_fact_sources') . " WHERE fact_id IN ($ids) ORDER BY checked_at DESC")->fetchAll(PDO::FETCH_ASSOC) as $s) {
                if (!$staff && (int) $s['private']) continue;
                $src[(int) $s['fact_id']][] = ['kind' => $s['kind'], 'site' => $s['site'], 'url' => $s['url'], 'label' => $s['label'], 'private' => (int) $s['private'], 'note' => $s['note'], 'checked_at' => $s['checked_at']];
            }
        }
        $have = []; $conf = [];
        foreach ($facts as $f) {
            if (in_array($f['status'], [self::REJECTED, self::REPLACED], true) && !$staff) continue;
            $out['facts'][$f['w']][] = ['id' => (int) $f['id'], 'fact' => $f['fact'], 'label' => self::FACTS[$f['fact']][1] ?? $f['fact'], 'value' => $f['value'], 'status' => $f['status'],
                                        'current' => (bool) (int) $f['is_current'], 'sources_n' => (int) $f['sources_n'], 'sources' => $src[(int) $f['id']] ?? []];
            if ((int) $f['is_current']) { $have[$f['fact']] = 1; if ($f['status'] === 'confirmed') $conf[$f['fact']] = 1; }
            if ($f['status'] === 'conflict' && (int) $f['is_current']) $out['conflicts']++;
        }
        $ok = true;
        // a confidential listing (a shelter) has no address by design: phone only, so it isn't "missing"
        $out['private_where'] = false;
        if ($kind === 'listing') {
            $e = $pdo->prepare('SELECT category_id, name FROM entities WHERE id = ?'); $e->execute([$id]); $e = $e->fetch(PDO::FETCH_ASSOC);
            $out['private_where'] = $e && ListingSafety::isConfidential($e['category_id'] !== null ? (int) $e['category_id'] : null, (string) $e['name']);
        }
        foreach (self::KINDS[$kind][2] ?? self::KEY as $W => $list) {
            if ($W === 'where' && $out['private_where']) continue;
            $any = array_filter($list, fn ($x) => isset($have[$x]));
            if (!$any) {
                $words = implode(' or ', array_map(fn ($x) => mb_strtolower(self::FACTS[$x][1]), $list));
                $out['missing'][] = (preg_match('/^[aeiou]/', $words) ? 'an ' : 'a ') . $words; $ok = false;
            }
            elseif (!array_filter($list, fn ($x) => isset($conf[$x]))) $ok = false;
        }
        $out['confirmed'] = $ok;
        return $out;
    }

    // ---- "✎ I know this": a person's own fix, saved with them as its source ----------------------------------------

    /** Facts a person can set on a listing from its card, and where each lives on the listing. */
    public const KNOWABLE = ['name' => 'core', 'phone' => 'phone', 'website' => 'website', 'email' => 'email', 'address' => 'address',
                             'city' => 'city', 'state' => 'state', 'hours' => 'hours', 'description' => 'description'];

    /**
     * A value staff know (they called, they were there, they read it somewhere): saved as a fact with the person as its
     * source and their note, then written to the listing itself so the site shows it. A confidential listing's address
     * is never taken. Returns ['fact_id', 'source_id', 'before' => what the listing held, for Undo].
     */
    public static function know(PDO $pdo, int $id, string $fact, string $value, string $note, bool $private, int $uid): array
    {
        if (!isset(self::KNOWABLE[$fact])) throw new InvalidArgumentException('That can\'t be set from here. Use Edit the listing.');
        $value = trim(preg_replace('/\s+/', ' ', $value));
        $e = $pdo->prepare("SELECT id, name, category_id FROM entities WHERE id = ? AND entity_type = 'merchant'"); $e->execute([$id]); $e = $e->fetch(PDO::FETCH_ASSOC);
        if (!$e) throw new InvalidArgumentException('That listing no longer exists.');
        if (in_array($fact, ['address'], true) && ListingSafety::isConfidential($e['category_id'] !== null ? (int) $e['category_id'] : null, (string) $e['name']))
            throw new InvalidArgumentException('This listing\'s address is confidential: phone only.');
        if ($value === '') throw new InvalidArgumentException('Type the value first.');
        if ($fact === 'phone') {
            require_once __DIR__ . '/crawler/ListingExtractor.php';
            $value = ListingExtractor::phone($value) ?? throw new InvalidArgumentException('A phone number needs 10 digits.');
        }
        if ($fact === 'website') {
            if (!preg_match('#^https?://#i', $value)) $value = 'https://' . $value;
            if (!filter_var($value, FILTER_VALIDATE_URL)) throw new InvalidArgumentException('That isn\'t a web address.');
        }
        if ($fact === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('That isn\'t an email address.');
        if ($fact === 'state') { $value = strtoupper($value); if (!preg_match('/^[A-Z]{2}$/', $value)) throw new InvalidArgumentException('State is 2 letters (AZ).'); }
        $note = mb_substr(trim($note), 0, 300);
        $before = self::listingValue($pdo, $id, $fact);
        $fid = self::add($pdo, 'listing', $id, $fact, $value, ['kind' => 'admin', 'label' => '', 'by' => $uid, 'note' => $note, 'private' => $private]);
        if (!$fid) throw new InvalidArgumentException('Nothing to save.');
        // the same person saying it again updates their note, rather than adding a second source
        $pdo->prepare("UPDATE " . Database::t('identity_fact_sources') . " SET note = ?, private = ?, added_by = ?, checked_at = CURRENT_TIMESTAMP WHERE fact_id = ? AND kind = 'admin' AND site = ''")
            ->execute([$note, $private ? 1 : 0, $uid, $fid]);
        $sid = $pdo->prepare("SELECT id FROM " . Database::t('identity_fact_sources') . " WHERE fact_id = ? AND kind = 'admin' AND site = ''"); $sid->execute([$fid]);
        self::setListing($pdo, $id, $fact, $value);
        self::recompute($pdo, 'listing', $id);
        return ['fact_id' => $fid, 'source_id' => (int) $sid->fetchColumn(), 'before' => $before];
    }

    /** Takes back a "✎ I know this": removes that source and puts the listing's old value back. */
    public static function unknow(PDO $pdo, int $id, string $fact, int $sourceId, $before): void
    {
        $st = $pdo->prepare('SELECT fact_id FROM ' . Database::t('identity_fact_sources') . ' WHERE id = ?'); $st->execute([$sourceId]); $fid = (int) $st->fetchColumn();
        $pdo->prepare('DELETE FROM ' . Database::t('identity_fact_sources') . ' WHERE id = ?')->execute([$sourceId]);
        if ($fid) $pdo->prepare('DELETE FROM ' . Database::t('identity_facts') . ' WHERE id = ? AND id NOT IN (SELECT fact_id FROM ' . Database::t('identity_fact_sources') . ')')->execute([$fid]);
        self::setListing($pdo, $id, $fact, $before, true);
        self::recompute($pdo, 'listing', $id);
    }

    /** "Not right": a person rejects a value. Returns the source id, for Undo (unknow() with no listing value to restore). */
    public static function reject(PDO $pdo, int $factId, string $note, int $uid): array
    {
        $st = $pdo->prepare("SELECT record_kind, record_id, fact FROM " . Database::t('identity_facts') . " WHERE id = ?"); $st->execute([$factId]);
        $f = $st->fetch(PDO::FETCH_ASSOC) ?: throw new InvalidArgumentException('That value is gone.');
        $pdo->prepare("INSERT INTO " . Database::t('identity_fact_sources') . " (fact_id, kind, site, url, label, private, added_by, note) VALUES (?, ?, '', '', 'Not right', 1, ?, ?)")
            ->execute([$factId, self::REJECTED, $uid, mb_substr($note, 0, 300)]);
        $sid = (int) $pdo->lastInsertId();
        self::recompute($pdo, (string) $f['record_kind'], (int) $f['record_id']);
        return ['source_id' => $sid, 'listing' => (int) $f['record_id'], 'fact' => (string) $f['fact']];
    }

    public static function unreject(PDO $pdo, int $sourceId, int $listing): void
    {
        $pdo->prepare("DELETE FROM " . Database::t('identity_fact_sources') . " WHERE id = ? AND kind = ?")->execute([$sourceId, self::REJECTED]);
        self::recompute($pdo, 'listing', $listing);
    }

    /** What the listing holds now for a fact: [value, source_type, locked] (null when it has none). */
    private static function listingValue(PDO $pdo, int $id, string $fact): ?array
    {
        if ($fact === 'name') { $st = $pdo->prepare('SELECT name, updated_by_type FROM entities WHERE id = ?'); $st->execute([$id]); $r = $st->fetch(PDO::FETCH_NUM); return $r ? [$r[0], $r[1], 0] : null; }
        $st = $pdo->prepare('SELECT meta_value, source_type, is_locked FROM entity_metadata WHERE entity_id = ? AND meta_key = ?'); $st->execute([$id, self::KNOWABLE[$fact]]);
        $r = $st->fetch(PDO::FETCH_NUM);
        return $r ? [(string) $r[0], (string) $r[1], (int) $r[2]] : null;
    }

    /** Writes a fact to the listing (as staff, locked so the crawler won't overwrite it), or restores what was there. */
    private static function setListing(PDO $pdo, int $id, string $fact, $value, bool $restore = false, array $as = ['admin', 1]): void
    {
        if ($fact === 'name') {
            if ($restore && !$value) return;
            $pdo->prepare("UPDATE entities SET name = ?, updated_by_type = ?, updated_at = NOW() WHERE id = ?")->execute([$restore ? $value[0] : $value, $restore ? $value[1] : $as[0], $id]);
        } else {
            $key = self::KNOWABLE[$fact];
            if ($restore && $value === null) { $pdo->prepare('DELETE FROM entity_metadata WHERE entity_id = ? AND meta_key = ?')->execute([$id, $key]); }
            else {
                [$json, $src, $lock] = $restore ? $value : [json_encode($fact === 'hours' ? array_values(array_filter(array_map('trim', explode(';', (string) $value)), 'strlen')) : $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $as[0], $as[1]];
                $pdo->prepare("INSERT INTO entity_metadata (entity_id, meta_key, meta_value, source_type, is_locked, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                               ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), source_type = VALUES(source_type), is_locked = VALUES(is_locked), updated_at = NOW()")
                    ->execute([$id, $key, $json, $src, $lock]);
            }
        }
        try { require_once __DIR__ . '/SearchIndex.php'; if (SearchIndex::ready($pdo)) SearchIndex::indexListings($pdo, [$id]); } catch (Throwable) {}
    }

    // ---- two records side by side (duplicates) --------------------------------------------------------------------

    /**
     * Compares a proposed listing (the crawler's payload) with an existing one, field by field: same (green), differs
     * (amber), only one has it. Returns [field => ['label', 'new', 'old', 'state' => same|alike|differs|new_only|old_only, 'pick' => new|old]].
     * The suggested value: the existing one, unless it's empty (then the new one), or the new one is confirmed elsewhere
     * and the existing one was only ever ours.
     */
    public static function compare(array $c, array $old): array
    {
        $m = $old['meta'] ?? [];
        $mv = function (string $k) use ($m) { $v = $m[$k]['value'] ?? ''; return is_array($v) ? implode('; ', array_map('strval', $v)) : (string) $v; };
        $rows = [
            'name' => ['Name', (string) ($c['name'] ?? ''), (string) $old['name']],
            'address' => ['Address', (string) ($c['where']['address'] ?? ''), $mv('address')],
            'city' => ['Town', (string) ($c['where']['city'] ?? ''), $mv('city')],
            'zip' => ['ZIP', (string) ($c['where']['zip'] ?? ''), (string) $old['primary_zip']],
            'phone' => ['Phone', (string) ($c['actions']['phone'] ?? ''), $mv('phone')],
            'website' => ['Website', (string) ($c['actions']['website'] ?? ''), $mv('website')],
            'email' => ['Email', (string) ($c['actions']['email'] ?? ''), $mv('email')],
            'category_id' => ['Kind', (string) ($c['category_suggestions'][0]['name'] ?? ''), (string) ($old['category_name'] ?? '')],
            'hours' => ['Hours', implode('; ', array_map('strval', (array) ($c['when']['hours'] ?? []))), $mv('hours')],
            'description' => ['About', mb_strimwidth((string) ($c['what']['description'] ?? ''), 0, 240, '…'), mb_strimwidth($mv('description'), 0, 240, '…')],
        ];
        $normKey = ['name' => 'name', 'address' => 'address', 'phone' => 'phone', 'website' => 'website', 'email' => 'email'];
        $out = [];
        foreach ($rows as $f => [$label, $new, $was]) {
            if ($new === '' && $was === '') continue;
            $a = isset($normKey[$f]) ? self::norm($normKey[$f], $new) : mb_strtolower(trim($new));
            $b = isset($normKey[$f]) ? self::norm($normKey[$f], $was) : mb_strtolower(trim($was));
            // a name counts as the same only when it is the same ("Heritage House West" is alike, not the same)
            $same = $a !== '' && $a === $b;
            $state = $same ? 'same' : ($was === '' ? 'new_only' : ($new === '' ? 'old_only' : ($f === 'name' && self::nameMatch($new, $was) >= 0.8 ? 'alike' : 'differs')));
            $person = isset($m[$f]) && ($m[$f]['source'] ?? 'ai_crawler') !== 'ai_crawler';
            $out[$f] = ['label' => $label, 'new' => $new, 'old' => $was, 'state' => $state, 'pick' => $state === 'new_only' ? 'new' : 'old', 'person' => $person];
        }
        return $out;
    }

    // ---- the one writer's record: every change to a listing goes into its identity (decisions/0133, 2026-11-01) ------

    /** A value taken off or replaced on the listing: kept as history, never shown or written back by the evidence,
     *  until the listing is given that value again. */
    public const REPLACED = 'replaced';
    /** While syncFromListing() records several fields, each one's recompute waits for the last. */
    private static bool $defer = false;

    /** Listing fields (entity_metadata keys, and name, point, zip on the listing itself) and the fact each one is. */
    public const LISTING_FACTS = ['name' => 'name', 'aka' => 'other_name', 'address' => 'address', 'city' => 'city', 'state' => 'state', 'zip' => 'zip',
                                  'point' => 'point', 'phone' => 'phone', 'website' => 'website', 'email' => 'email', 'hours' => 'hours',
                                  'description' => 'description', 'offerings' => 'offerings', 'category' => 'category'];

    /**
     * Records a change to a listing in its identity, at the moment it's written: whoever wrote it (source_type: the
     * crawler, staff, the owner, a member's accepted fix, the Google check) becomes a source of the new value, kept
     * through rebuilds (added_by is set: the person, else 0). What the identity showed before and the listing no longer
     * says is marked replaced, so the evidence can't write it back (a website the refresh took off stays off). $value:
     * the new value (a string, a list, or the JSON stored), or null when the field was cleared. $src: optional
     * ['kind', 'url', 'label', 'note'] when the writer knows where it read it (the business's own site, Google).
     * Never fails the write that called it.
     */
    public static function listingWrote(PDO $pdo, int $id, string $key, $value, string $sourceType = 'ai_crawler', int $uid = 0, array $src = []): void
    {
        $fact = self::LISTING_FACTS[$key] ?? null;
        if ($fact === null || $id <= 0) return;
        try {
            if (!self::ready($pdo)) return;
            if (in_array($fact, ['address', 'point'], true)) {
                $e = $pdo->prepare('SELECT category_id, name FROM entities WHERE id = ?'); $e->execute([$id]); $e = $e->fetch(PDO::FETCH_ASSOC);
                if (!$e || ListingSafety::isConfidential($e['category_id'] !== null ? (int) $e['category_id'] : null, (string) $e['name'])) return;   // never kept
            }
            $values = self::values($value);
            if ($values && (self::FACTS[$fact][2] ?? false)) $values = [implode('; ', $values)];   // one value (hours as one line)
            [$bk, $bl] = self::BY[$sourceType] ?? ['crawler', 'Added by the crawler'];
            $source = ['kind' => (string) ($src['kind'] ?? $bk), 'url' => (string) ($src['url'] ?? ''), 'label' => (string) ($src['label'] ?? $bl),
                       'note' => (string) ($src['note'] ?? ''), 'by' => $uid > 0 ? $uid : 0];
            $keep = [];
            foreach ($values as $v) {
                $fid = self::add($pdo, 'listing', $id, $fact, $v, $source);
                if (!$fid) continue;
                $keep[$fid] = 1;
                if ($source['kind'] !== $bk) self::add($pdo, 'listing', $id, $fact, $v, ['kind' => $bk, 'label' => $bl, 'by' => $source['by']]);   // where it came into our records, too
                $pdo->prepare('DELETE FROM ' . Database::t('identity_fact_sources') . ' WHERE fact_id = ? AND kind = ?')->execute([$fid, self::REPLACED]);   // it's back
            }
            // what the identity showed for this fact and the listing no longer says
            $st = $pdo->prepare('SELECT id FROM ' . Database::t('identity_facts') . " WHERE record_kind = 'listing' AND record_id = ? AND fact = ? AND is_current = 1 AND status <> ?");
            $st->execute([$id, $fact, self::REJECTED]);
            $who = ['admin' => 'staff', 'owner' => 'the owner', 'user' => 'a member\'s fix'][$sourceType] ?? 'the crawler';
            $mark = $pdo->prepare('INSERT INTO ' . Database::t('identity_fact_sources') . " (fact_id, kind, site, url, label, private, added_by, note) VALUES (?, ?, '', '', 'Taken off the listing', 1, ?, ?) ON DUPLICATE KEY UPDATE note = VALUES(note), checked_at = CURRENT_TIMESTAMP");
            // other names and kinds also come from registries and other sites: only one the listing alone gave is taken off
            $outside = $pdo->prepare('SELECT 1 FROM ' . Database::t('identity_fact_sources') . " WHERE fact_id = ? AND (site <> '' OR kind = 'registry') LIMIT 1");
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $old) {
                if (isset($keep[(int) $old])) continue;
                if (in_array($fact, ['other_name', 'category'], true)) { $outside->execute([(int) $old]); if ($outside->fetchColumn()) continue; }
                $mark->execute([(int) $old, self::REPLACED, $uid > 0 ? $uid : 0, mb_substr(($values ? 'Replaced' : 'Cleared') . ' by ' . $who . ' on ' . date('Y-m-d') . ($source['note'] !== '' ? ': ' . $source['note'] : ''), 0, 300)]);
            }
            if (!self::$defer) self::recompute($pdo, 'listing', $id);
        } catch (Throwable $x) { error_log('identity listingWrote: ' . $x->getMessage()); }
    }

    /**
     * After a writer changed a listing in several places at once (the editor, an approve or merge, an owner's edit, a
     * rebuild): every listing field that no longer matches what its identity shows is recorded through listingWrote(),
     * with that writer as its source. Fields that didn't change are left alone. Never fails the save that called it.
     */
    public static function syncFromListing(PDO $pdo, int $id, string $sourceType = 'ai_crawler', int $uid = 0, array $src = []): void
    {
        try {
            if ($id <= 0 || !self::ready($pdo)) return;
            $e = $pdo->prepare('SELECT e.name, e.primary_zip, e.latitude, e.longitude, c.name AS category_name FROM entities e LEFT JOIN categories c ON c.id = e.category_id WHERE e.id = ?');
            $e->execute([$id]); $e = $e->fetch(PDO::FETCH_ASSOC);
            if (!$e) return;
            $now = ['name' => (string) $e['name'], 'zip' => (string) $e['primary_zip'], 'category' => (string) $e['category_name'],
                    'point' => $e['latitude'] !== null && $e['longitude'] !== null ? ['lat' => round((float) $e['latitude'], 6), 'lon' => round((float) $e['longitude'], 6)] : null];
            $m = $pdo->prepare('SELECT meta_key, meta_value FROM entity_metadata WHERE entity_id = ?'); $m->execute([$id]);
            foreach ($m->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) if (isset(self::LISTING_FACTS[$k]) && $k !== 'category') $now[$k] = $v;
            $cur = $pdo->prepare('SELECT fact, value_norm FROM ' . Database::t('identity_facts') . " WHERE record_kind = 'listing' AND record_id = ? AND is_current = 1 AND status NOT IN (?, ?)");
            $cur->execute([$id, self::REJECTED, self::REPLACED]);
            $have = [];
            foreach ($cur->fetchAll(PDO::FETCH_ASSOC) as $r) $have[(string) $r['fact']][(string) $r['value_norm']] = 1;
            $wrote = false;
            self::$defer = true;
            foreach (self::LISTING_FACTS as $key => $fact) {
                $vals = self::values($now[$key] ?? null);
                if ($vals && (self::FACTS[$fact][2] ?? false)) $vals = [implode('; ', $vals)];
                $norms = [];
                foreach ($vals as $v) { $n = self::norm($fact, $v); if ($n !== '') $norms[$n] = 1; }
                $was = $have[$fact] ?? [];
                if ($fact === 'category' || $fact === 'other_name') { if (!array_diff_key($norms, $was)) continue; }   // fed from more than one place: only what's new
                elseif ($norms == $was) continue;
                if ($fact === 'point' && !$norms) continue;   // a point taken away is a move, recorded by the move itself
                self::listingWrote($pdo, $id, $key, $now[$key] ?? null, $sourceType, $uid, $src);
                $wrote = true;
            }
            self::$defer = false;
            if ($wrote) self::recompute($pdo, 'listing', $id);
        } catch (Throwable $x) { self::$defer = false; error_log('identity syncFromListing: ' . $x->getMessage()); }
    }

    /**
     * Two listings were one business (a merge): the hidden one's evidence joins the kept one's identity, each source
     * noted "Merged from #<id>" so unmerge() can take back exactly what came across. A value a person marked "Not
     * right" stays not right. The hidden listing keeps its own identity, for Undo. Never fails the merge.
     */
    public static function mergeInto(PDO $pdo, int $from, int $keep, string $fromKind = 'listing', string $tag = '', ?array $only = null): void
    {
        try {
            if ($from <= 0 || $keep <= 0 || ($from === $keep && $fromKind === 'listing') || !self::ready($pdo)) return;
            $tag = $tag !== '' ? $tag : 'Merged from #' . $from;
            $st = $pdo->prepare('SELECT f.fact, f.value, s.kind, s.url, s.label, s.private, s.added_by, s.note FROM ' . Database::t('identity_facts') . ' f JOIN ' . Database::t('identity_fact_sources') . " s ON s.fact_id = f.id
                                 WHERE f.record_kind = ? AND f.record_id = ? AND s.kind <> ?");
            $st->execute([$fromKind, $from, self::REPLACED]);
            $e = $pdo->prepare('SELECT category_id, name FROM entities WHERE id = ?'); $e->execute([$keep]); $e = $e->fetch(PDO::FETCH_ASSOC);
            $hidden = $e && ListingSafety::isConfidential($e['category_id'] !== null ? (int) $e['category_id'] : null, (string) $e['name']);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if ($hidden && in_array($r['fact'], ['address', 'point'], true)) continue;
                if ($only !== null && !in_array($r['fact'], $only, true)) continue;
                self::add($pdo, 'listing', $keep, (string) $r['fact'], (string) $r['value'], ['kind' => (string) $r['kind'], 'url' => (string) $r['url'], 'label' => (string) $r['label'],
                          'private' => (int) $r['private'], 'by' => $r['added_by'] !== null ? (int) $r['added_by'] : 0, 'note' => mb_substr($tag . ($r['note'] !== '' ? ': ' . $r['note'] : ''), 0, 300)]);
            }
            self::recompute($pdo, 'listing', $keep);
        } catch (Throwable $x) { error_log('identity mergeInto: ' . $x->getMessage()); }
    }

    /**
     * A source's licence tightened (gap review P4-2): the listing facts that rest only on it (no other site, no person
     * behind them): [[fact_id, listing id, fact, value]], at most $limit.
     */
    public static function restingOnly(PDO $pdo, string $host, int $limit = 2000): array
    {
        try {
            $S = Database::t('identity_fact_sources'); $F = Database::t('identity_facts');
            $st = $pdo->prepare("SELECT f.id, f.record_id, f.fact, f.value FROM $F f
                                  WHERE f.record_kind = 'listing' AND f.is_current = 1 AND f.status <> ?
                                    AND EXISTS (SELECT 1 FROM $S a WHERE a.fact_id = f.id AND a.site = ?)
                                    AND NOT EXISTS (SELECT 1 FROM $S b WHERE b.fact_id = f.id AND b.kind <> ? AND b.site <> ?)
                                    AND NOT EXISTS (SELECT 1 FROM $S r WHERE r.fact_id = f.id AND r.kind = ?)
                                  LIMIT " . max(1, min(5000, $limit)));
            $st->execute([self::REJECTED, $host, self::REPLACED, $host, self::REPLACED]);
            return $st->fetchAll(PDO::FETCH_NUM);
        } catch (Throwable $x) { error_log('restingOnly: ' . $x->getMessage()); return []; }
    }

    /**
     * Takes such a fact off its listing (R11: kept as taken off, so the evidence never writes it back): the listing's field
     * is cleared when it still says that value. Names, the point and the ZIP are never taken off this way (a person's call).
     */
    public static function takeOffFact(PDO $pdo, int $factId, string $why, int $uid): bool
    {
        $st = $pdo->prepare('SELECT record_id, fact, value FROM ' . Database::t('identity_facts') . " WHERE id = ? AND record_kind = 'listing'");
        $st->execute([$factId]); $f = $st->fetch(PDO::FETCH_ASSOC);
        if (!$f) return false;
        $key = array_search((string) $f['fact'], self::LISTING_FACTS, true);
        if ($key === false || in_array($key, ['name', 'point', 'zip', 'state', 'city'], true)) return false;
        $pdo->prepare('INSERT INTO ' . Database::t('identity_fact_sources') . " (fact_id, kind, site, url, label, private, added_by, note) VALUES (?, ?, '', '', 'Taken off the listing', 1, ?, ?)")
            ->execute([$factId, self::REPLACED, $uid > 0 ? $uid : 0, mb_substr($why, 0, 255)]);
        $m = $pdo->prepare('SELECT meta_value FROM entity_metadata WHERE entity_id = ? AND meta_key = ?'); $m->execute([(int) $f['record_id'], $key]);
        $cur = $m->fetchColumn();
        if ($cur !== false) {
            $v = json_decode((string) $cur, true); $v = is_string($v) ? $v : (is_array($v) ? implode('; ', $v) : (string) $cur);
            if (self::norm((string) $f['fact'], $v) === self::norm((string) $f['fact'], (string) $f['value']))
                $pdo->prepare('DELETE FROM entity_metadata WHERE entity_id = ? AND meta_key = ? AND is_locked = 0')->execute([(int) $f['record_id'], $key]);
        }
        return true;
    }

    /** Undo of a merge: what mergeInto() brought across from $from is taken off $keep again. */
    public static function unmerge(PDO $pdo, int $from, int $keep): void
    {
        try {
            if (!self::ready($pdo)) return;
            $mine = 'SELECT id FROM ' . Database::t('identity_facts') . " WHERE record_kind = 'listing' AND record_id = ?";
            $pdo->prepare('DELETE FROM ' . Database::t('identity_fact_sources') . " WHERE fact_id IN ($mine) AND note LIKE ?")->execute([$keep, 'Merged from #' . $from . '%']);
            // a value that came only from the other listing, and was marked taken off since, goes with it
            $src = Database::t('identity_fact_sources');
            $pdo->prepare("DELETE FROM $src WHERE kind = ? AND fact_id IN ($mine) AND fact_id NOT IN (SELECT fact_id FROM (SELECT fact_id FROM $src WHERE kind <> ?) x)")->execute([self::REPLACED, $keep, self::REPLACED]);
            $pdo->prepare('DELETE FROM ' . Database::t('identity_facts') . " WHERE record_kind = 'listing' AND record_id = ? AND id NOT IN (SELECT fact_id FROM " . Database::t('identity_fact_sources') . ')')->execute([$keep]);
            self::recompute($pdo, 'listing', $keep);
        } catch (Throwable $x) { error_log('identity unmerge: ' . $x->getMessage()); }
    }

    /**
     * Listings whose identity still has old rebuildable rows (from before 2026-11-01), or none at all, are repaired,
     * $limit a run (scheduled jobs call it; one query once done). Identities whose listing is gone are cleared.
     * Returns how many it repaired.
     */
    public static function backfillListings(PDO $pdo, int $limit = 50): int
    {
        try {
            if (!self::ready($pdo)) return 0;
            $F = Database::t('identity_facts'); $S = Database::t('identity_fact_sources');
            $st = $pdo->query("SELECT e.id FROM entities e WHERE e.entity_type = 'merchant' AND (
                                   NOT EXISTS (SELECT 1 FROM $F f WHERE f.record_kind = 'listing' AND f.record_id = e.id)
                                   OR EXISTS (SELECT 1 FROM $F f JOIN $S s ON s.fact_id = f.id WHERE f.record_kind = 'listing' AND f.record_id = e.id AND s.added_by IS NULL))
                               ORDER BY e.id DESC LIMIT " . max(1, min(500, $limit)));
            $n = 0;
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) { self::fromListing($pdo, (int) $id); $n++; }
            if ($n === 0) {   // done: clear what belongs to listings that are gone
                $gone = $pdo->query("SELECT DISTINCT f.record_id FROM $F f LEFT JOIN entities e ON e.id = f.record_id WHERE f.record_kind = 'listing' AND e.id IS NULL LIMIT 200")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($gone as $g) {
                    $pdo->prepare("DELETE FROM $S WHERE fact_id IN (SELECT id FROM (SELECT id FROM $F WHERE record_kind = 'listing' AND record_id = ?) x)")->execute([(int) $g]);
                    $pdo->prepare("DELETE FROM $F WHERE record_kind = 'listing' AND record_id = ?")->execute([(int) $g]);
                }
            }
            return $n;
        } catch (Throwable $x) { error_log('identity backfillListings: ' . $x->getMessage()); return 0; }
    }

    // ---- sightings: an identity from the first time the crawler sees a business (decisions/0133, 2026-11-01) ---------

    /** A sighting's identity: record_kind 'intake', keyed by its crawler_raw_index row. */
    public const INTAKE = 'intake';

    /**
     * Builds (or rebuilds) the identity of one crawler sighting from its payload: the page it came from, the sites that
     * confirmed it, and the reference index and registries matched once, now. Kept through rebuilds (added_by 0). A
     * sighting that's already a listing goes onto that listing instead (adopt). A confidential name or kind (a
     * shelter) keeps no address or point. Returns the number of facts. Never fails the crawl that called it.
     */
    public static function fromIntake(PDO $pdo, int $rowId): int
    {
        try {
            if ($rowId <= 0 || !self::ready($pdo)) return 0;
            $st = $pdo->prepare('SELECT raw_payload, source_url, status, entity_id, matched_entity_id FROM ' . Database::t('crawler_raw_index') . " WHERE id = ? AND payload_type = 'listing'");
            $st->execute([$rowId]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if (!$r) return 0;
            $c = json_decode((string) $r['raw_payload'], true);
            if (!is_array($c) || trim((string) ($c['name'] ?? '')) === '') return 0;
            self::dropIntake($pdo, $rowId);   // rebuilt whole: a sighting holds only what the crawler found
            $hidden = ListingSafety::isConfidential(isset($c['category_suggestions'][0]['id']) ? (int) $c['category_suggestions'][0]['id'] : null, (string) $c['name']);
            $add = function (string $fact, string $value, array $src) use ($pdo, $rowId, $hidden): int {
                if ($hidden && in_array($fact, ['address', 'point'], true)) return 0;
                return self::add($pdo, self::INTAKE, $rowId, $fact, $value, $src + ['by' => 0]);
            };
            [$cp, $ca, $cz] = self::fromPayload($c, (string) $r['source_url'], (string) $c['name'], $add);
            self::fromReferences($pdo, (string) $c['name'], self::digits($cp), $cz, $ca, $add);
            self::recompute($pdo, self::INTAKE, $rowId);
            if (in_array($r['status'], ['published', 'merged'], true) && (int) $r['entity_id'] > 0) { self::adopt($pdo, $rowId, (int) $r['entity_id']); return 0; }
            $n = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::t('identity_facts') . ' WHERE record_kind = ? AND record_id = ?'); $n->execute([self::INTAKE, $rowId]);
            return (int) $n->fetchColumn();
        } catch (Throwable $x) { error_log('identity fromIntake #' . $rowId . ': ' . $x->getMessage()); return 0; }
    }

    /**
     * A sighting became a listing, or was found to be one we list (approved, merged, "same as", covered by): its
     * evidence joins that listing's identity, noted with the sighting's number, and the sighting's own copy goes, so
     * there is one record. Built first when it has none yet (a sighting from before 2026-11-01).
     */
    public static function adopt(PDO $pdo, int $rowId, int $entityId): void
    {
        try {
            if ($rowId <= 0 || $entityId <= 0 || !self::ready($pdo)) return;
            $has = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::t('identity_facts') . ' WHERE record_kind = ? AND record_id = ?'); $has->execute([self::INTAKE, $rowId]);
            if (!(int) $has->fetchColumn()) {   // build it, without letting fromIntake adopt it again
                $st = $pdo->prepare('SELECT raw_payload, source_url FROM ' . Database::t('crawler_raw_index') . ' WHERE id = ?'); $st->execute([$rowId]);
                $r = $st->fetch(PDO::FETCH_ASSOC);
                $c = $r ? json_decode((string) $r['raw_payload'], true) : null;
                if (!is_array($c)) return;
                $e = $pdo->prepare('SELECT category_id, name FROM entities WHERE id = ?'); $e->execute([$entityId]); $e = $e->fetch(PDO::FETCH_ASSOC);
                $hidden = !$e || ListingSafety::isConfidential($e['category_id'] !== null ? (int) $e['category_id'] : null, (string) $e['name']);
                $add = function (string $fact, string $value, array $src) use ($pdo, $rowId, $hidden): int {
                    if ($hidden && in_array($fact, ['address', 'point'], true)) return 0;
                    return self::add($pdo, self::INTAKE, $rowId, $fact, $value, $src + ['by' => 0]);
                };
                [$cp, $ca, $cz] = self::fromPayload($c, (string) $r['source_url'], (string) ($e['name'] ?? ''), $add);
                self::fromReferences($pdo, (string) ($c['name'] ?? ''), self::digits($cp), $cz, $ca, $add);
            }
            // under another name (a provider at a hospital, an office in a building), only where it is and how to reach it
            // are the same business's: its name and what it says about itself stay out
            $n = $pdo->prepare('SELECT e.name, r.raw_payload FROM entities e, ' . Database::t('crawler_raw_index') . ' r WHERE e.id = ? AND r.id = ?'); $n->execute([$entityId, $rowId]);
            $nr = $n->fetch(PDO::FETCH_ASSOC);
            $seen = $nr ? (string) (json_decode((string) $nr['raw_payload'], true)['name'] ?? '') : '';
            $same = !$nr || $seen === '' || self::nameMatch($seen, (string) $nr['name']) >= 0.5;
            self::mergeInto($pdo, $rowId, $entityId, self::INTAKE, 'From the crawler\'s sighting #' . $rowId, $same ? null : ['address', 'city', 'state', 'zip', 'phone', 'point']);
            self::dropIntake($pdo, $rowId);
        } catch (Throwable $x) { error_log('identity adopt #' . $rowId . ': ' . $x->getMessage()); }
    }

    /** A sighting's own identity, removed (it became a listing's, or is rebuilt). */
    private static function dropIntake(PDO $pdo, int $rowId): void
    {
        $pdo->prepare('DELETE FROM ' . Database::t('identity_fact_sources') . ' WHERE fact_id IN (SELECT id FROM (SELECT id FROM ' . Database::t('identity_facts') . ' WHERE record_kind = ? AND record_id = ?) x)')->execute([self::INTAKE, $rowId]);
        $pdo->prepare('DELETE FROM ' . Database::t('identity_facts') . ' WHERE record_kind = ? AND record_id = ?')->execute([self::INTAKE, $rowId]);
    }

    /**
     * The sightings from before identities started at first sighting: those waiting, held or turned down get theirs,
     * $limit a run (scheduled jobs call it; cheap once done). One turned down as the same as a listing is already read
     * into that listing's identity by its rebuild.
     */
    public static function backfillIntake(PDO $pdo, int $limit = 100): int
    {
        try {
            if (!self::ready($pdo)) return 0;
            $st = $pdo->prepare('SELECT r.id FROM ' . Database::t('crawler_raw_index') . " r WHERE r.payload_type = 'listing' AND r.status IN ('staged', 'quarantined', 'rejected')
                                   AND NOT EXISTS (SELECT 1 FROM " . Database::t('identity_facts') . " f WHERE f.record_kind = 'intake' AND f.record_id = r.id)
                                   AND r.entity_id IS NULL AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.raw_payload, '$.name')), '') <> ''
                                 ORDER BY r.id DESC LIMIT " . max(1, min(500, $limit)));
            $st->execute();
            $n = 0;
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                self::fromIntake($pdo, (int) $r['id']);
                $n++;
            }
            return $n;
        } catch (Throwable $x) { error_log('identity backfillIntake: ' . $x->getMessage()); return 0; }
    }

    /** A listing value as plain values: a JSON list or string, a list, or text (hours are one value, "Mon 9-5; Tue 9-5"). */
    private static function values($value): array
    {
        if ($value === null) return [];
        if (is_string($value)) { $j = json_decode($value, true); if (json_last_error() === JSON_ERROR_NONE && (is_array($j) || is_string($j))) $value = $j; }
        if (is_array($value)) {
            if (isset($value['lat'], $value['lon'])) return [$value['lat'] . ',' . $value['lon']];
            if (isset($value['url']) && is_string($value['url'])) return [$value['url']];
            $flat = array_values(array_filter(array_map(fn ($x) => is_scalar($x) ? trim((string) $x) : '', $value), 'strlen'));
            return $flat;
        }
        $v = trim((string) $value);
        return $v === '' ? [] : [$v];
    }

    // ---- building a listing's identity from what we hold -----------------------------------------------------------

    private const META = ['address' => 'address', 'city' => 'city', 'state' => 'state', 'phone' => 'phone', 'website' => 'website', 'hours' => 'hours',
                          'description' => 'description', 'offerings' => 'offerings', 'email' => 'email', 'category' => 'category'];
    private const BY = ['ai_crawler' => ['crawler', 'Added by the crawler'], 'user' => ['member', 'Suggested by a member'], 'owner' => ['owner', 'The owner'], 'admin' => ['staff', 'Entered by staff']];

    /**
     * What one crawler sighting says, as facts with their sources: the page it came from, and the websites that
     * confirmed it (what each matched). $add(fact, value, source). Returns [phone, address, zip] as it found them.
     */
    private static function fromPayload(array $c, string $sourceUrl, string $nameFallback, callable $add): array
    {
        $start = (string) ($c['source']['start_url'] ?? $sourceUrl);
        $from = $start !== '' ? ['kind' => self::kindFor($start), 'url' => $start, 'label' => 'Where the crawler found it'] : ['kind' => 'crawler', 'label' => 'Where the crawler found it'];
        $cp = (string) ($c['actions']['phone'] ?? ''); $ca = (string) ($c['where']['address'] ?? '');
        $name = (string) ($c['name'] ?? '') ?: $nameFallback;
        $add('name', $name, $from);
        foreach (['phone' => $cp, 'address' => $ca, 'city' => (string) ($c['where']['city'] ?? ''), 'state' => (string) ($c['where']['state'] ?? ''), 'zip' => (string) ($c['where']['zip'] ?? ''),
                  'website' => (string) ($c['actions']['website'] ?? ''), 'email' => (string) ($c['actions']['email'] ?? ''), 'description' => (string) ($c['what']['description'] ?? '')] as $f => $v)
            if ($v !== '') $add($f, $v, $from);
        if (($c['geo']['source'] ?? '') === 'site' && is_numeric($c['geo']['latitude'] ?? null) && is_numeric($c['geo']['longitude'] ?? null)) $add('point', $c['geo']['latitude'] . ',' . $c['geo']['longitude'], $from);   // its own map (a ZIP's middle isn't a point)
        if (!empty($c['when']['hours'])) $add('hours', implode('; ', array_map('strval', (array) $c['when']['hours'])), $from);
        foreach ((array) ($c['what']['offerings'] ?? []) as $o) if (is_string($o) && trim($o) !== '') $add('offerings', $o, $from);
        foreach ((array) ($c['actions']['social'] ?? []) as $soc) if (is_string($soc) && $soc !== '') $add('social', $soc, ['kind' => 'evidence', 'url' => $soc, 'label' => 'A page about it']);
        foreach ((array) ($c['corroboration'] ?? []) as $u) {
            $u = is_array($u) ? $u : ['url' => (string) $u, 'kind' => 'own_site', 'evidence' => ''];
            $url = (string) ($u['url'] ?? ''); if ($url === '') continue;
            $found = (string) ($u['kind'] ?? '');
            // "its own website" only when the host isn't a government page, directory or social page about it
            if ($found === 'own_site' && !in_array(self::kindFor($url), ['source', 'own_site'], true)) $found = '';
            $src = ['kind' => self::kindFor($url, $found), 'url' => $url, 'label' => (string) ($u['label'] ?? '') ?: 'Confirmed the listing'];
            $add('name', $name, $src);
            $ev = (string) ($u['evidence'] ?? '');
            if (($ev === 'phone' || $ev === 'both') && $cp !== '') $add('phone', $cp, $src);
            if (($ev === 'address' || $ev === 'both') && $ca !== '') $add('address', $ca, $src);
            // its own website naming it confirms the website too; a directory or social page is a way to reach it
            if ($found === 'own_site') $add('website', $url, $src);
            elseif (in_array($src['kind'], ['directory', 'social'], true)) $add('social', $url, ['kind' => 'evidence', 'url' => $url, 'label' => $src['label']]);
        }
        return [$cp, $ca, (string) ($c['where']['zip'] ?? '')];
    }

    /**
     * The sources we read (a page that lists the same phone, or the same street number in the same ZIP, with the
     * name) and the official registries (NPI by phone or street and ZIP; IRS nonprofits by name and ZIP).
     */
    private static function fromReferences(PDO $pdo, string $name, string $phone, string $zip, string $addr, callable $add): void
    {
        $num = preg_match('/^\s*(\d+)/', $addr, $mm) ? $mm[1] : '';
        $sf = $pdo->prepare('SELECT f.host, f.page_url, f.name, f.address, f.phone_digits, f.zip, f.house_number, r.kind AS rkind, r.label AS rlabel FROM ' . Database::t('source_facts') . ' f LEFT JOIN ' . Database::t('reference_sources') . ' r ON r.host = f.host
                             WHERE (f.phone_digits = ? AND f.phone_digits <> \'\') OR (f.zip = ? AND f.house_number = ? AND f.house_number <> \'\') LIMIT 40');
        try { $sf->execute([$phone, $zip, $num]); $rows = $sf->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable) { $rows = []; }
        foreach ($rows as $f) {
            if (self::nameMatch((string) $f['name'], $name) < 0.5) continue;
            $k = ['community_directory' => 'community', 'organization' => 'source', 'other' => 'source', 'commercial' => 'directory'][(string) $f['rkind']] ?? ((string) $f['rkind'] ?: 'source');
            $src = ['kind' => $k, 'url' => (string) $f['page_url'], 'label' => (string) ($f['rlabel'] ?: $f['host'])];
            $add('name', $name, $src);
            if ($f['phone_digits'] && $f['phone_digits'] === $phone) $add('phone', (string) $f['phone_digits'], $src);
            if ($f['address'] && $num !== '' && $f['house_number'] === $num) $add('address', (string) $f['address'], $src);
        }
        // official registries: the NPI Registry (by phone or street number and ZIP), IRS nonprofits (by name and ZIP)
        try {
            $np = $pdo->prepare('SELECT npi, name, other_name, address, city, state, zip5, phone FROM ' . Database::t('npi_orgs') . ' WHERE (phone = ? AND phone <> \'\') OR (zip5 = ? AND address LIKE ?) LIMIT 10');
            $np->execute([$phone, $zip, $num !== '' ? $num . ' %' : '-']);
            foreach ($np->fetchAll(PDO::FETCH_ASSOC) as $n) {
                if (self::nameMatch((string) $n['name'], $name) < 0.5 && self::nameMatch((string) ($n['other_name'] ?? ''), $name) < 0.5) continue;
                $src = ['kind' => 'registry', 'url' => 'https://npiregistry.cms.hhs.gov/provider-view/' . $n['npi'], 'label' => 'NPI Registry'];
                $add(self::nameKey((string) $n['name']) === self::nameKey($name) ? 'name' : 'other_name', (string) $n['name'], $src);
                if ($n['phone'] && $n['phone'] === $phone) $add('phone', (string) $n['phone'], $src);
                if ($n['address'] && $num !== '' && str_starts_with((string) $n['address'], $num . ' ')) $add('address', (string) $n['address'], $src);
            }
        } catch (Throwable) {}
        try {
            $ir = $pdo->prepare('SELECT ein, name, street, zip5 FROM ' . Database::t('irs_orgs') . ' WHERE zip5 = ? LIMIT 400');
            $ir->execute([$zip]);
            foreach ($ir->fetchAll(PDO::FETCH_ASSOC) as $n) {
                if (self::nameMatch((string) $n['name'], $name) < 0.8) continue;
                $src = ['kind' => 'registry', 'url' => 'https://apps.irs.gov/app/eos/', 'label' => 'IRS exempt organizations (EIN ' . $n['ein'] . ')'];
                $add(self::nameKey((string) $n['name']) === self::nameKey($name) ? 'name' : 'other_name', (string) $n['name'], $src);
                if ($n['street'] && $num !== '' && str_starts_with((string) $n['street'], $num . ' ')) $add('address', (string) $n['street'], $src);
            }
        } catch (Throwable) {}
    }

    /**
     * Repairs a listing's identity from everything we hold (decisions/0133, 2026-11-01): a backfill and repair tool, no
     * longer a rebuild. What it adds is kept (added_by 0) and joins what's there, so running it again changes nothing;
     * only the old rebuildable rows (added_by NULL, from before) are cleared and written again the kept way. The
     * crawler's sightings of this business join through adopt(). Returns the number of facts.
     */
    public static function fromListing(PDO $pdo, int $id): int
    {
        if (!self::ready($pdo)) return 0;
        $st = $pdo->prepare('SELECT e.*, c.name AS category_name FROM entities e LEFT JOIN categories c ON c.id = e.category_id WHERE e.id = ?');
        $st->execute([$id]);
        $e = $st->fetch(PDO::FETCH_ASSOC);
        if (!$e) return 0;
        $K = 'listing';
        // a shelter's or other confidential address is never kept, not even for staff: phone only (decisions/0058 §26 and the listing rules)
        $hidden = ListingSafety::isConfidential($e['category_id'] !== null ? (int) $e['category_id'] : null, (string) $e['name']);
        $where = ['address' => 1, 'point' => 1, 'city' => 0, 'state' => 0, 'zip' => 0];
        $add = function (string $fact, string $value, array $src) use ($pdo, $K, $id, $hidden, $where): int {
            if ($hidden && !empty($where[$fact])) return 0;
            return self::add($pdo, $K, $id, $fact, $value, $src + ['by' => 0]);
        };
        // the old rebuildable rows (added_by NULL) are cleared and written again below the kept way; everything kept
        // (a person's, the identity jobs', the one writer's, a sighting's) stays. The owner's claim is re-read.
        $mine = "SELECT id FROM " . Database::t('identity_facts') . " WHERE record_kind = ? AND record_id = ?";
        $pdo->prepare("DELETE FROM " . Database::t('identity_fact_sources') . " WHERE fact_id IN ($mine) AND (added_by IS NULL OR label LIKE 'Claimed%')")->execute([$K, $id]);
        if ($hidden) $pdo->prepare("DELETE FROM " . Database::t('identity_fact_sources') . " WHERE fact_id IN ($mine AND fact IN ('address', 'point'))")->execute([$K, $id]);
        $pdo->prepare("DELETE FROM " . Database::t('identity_facts') . " WHERE record_kind = ? AND record_id = ? AND id NOT IN (SELECT fact_id FROM " . Database::t('identity_fact_sources') . ")")->execute([$K, $id]);
        [$bk, $bl] = self::BY[(string) $e['created_by_type']] ?? ['crawler', 'Our records'];
        $rec = ['kind' => $bk, 'label' => $bl];
        $add('name', (string) $e['name'], $rec);
        if ($e['category_name']) $add('category', (string) $e['category_name'], $rec);
        if ($e['latitude'] !== null && $e['longitude'] !== null) $add('point', $e['latitude'] . ',' . $e['longitude'], $rec);
        if ($e['primary_zip']) $add('zip', (string) $e['primary_zip'], $rec);
        // the owner's claim: a verified claim confirms the name; an unverified one is noted, not counted
        if ((int) $e['owner_user_id'] && in_array($e['status'], ['verified', 'claimed-unverified', 'pending-verification'], true)) {
            $verified = $e['status'] === 'verified';
            $add('name', (string) $e['name'], ['kind' => $verified ? 'owner' : 'member', 'label' => $verified ? 'Claimed and verified by the owner' : 'Claimed, not verified yet', 'by' => (int) $e['owner_user_id']]);
        }
        // its details, each with who added it
        $mt = $pdo->prepare('SELECT meta_key, meta_value, source_type FROM entity_metadata WHERE entity_id = ?');
        $mt->execute([$id]);
        $phone = ''; $addr = ''; $zip = (string) $e['primary_zip'];
        foreach ($mt->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $fact = self::META[(string) $m['meta_key']] ?? null;
            if (!$fact) continue;
            $v = json_decode((string) $m['meta_value'], true);
            if (is_array($v)) $vals = array_values(array_filter(array_map(fn ($x) => is_scalar($x) ? (string) $x : '', $v)));
            else $vals = [is_string($v) ? $v : (string) $m['meta_value']];
            if ($fact === 'hours') $vals = [implode('; ', $vals)];
            [$sk, $sl] = self::BY[(string) $m['source_type']] ?? ['crawler', 'Our records'];
            foreach ($vals as $v1) {
                if ($fact === 'phone' && $phone === '') $phone = self::digits($v1);
                if ($fact === 'address' && $addr === '') $addr = $v1;
                $add($fact, $v1, ['kind' => $sk, 'label' => $sl]);
            }
        }
        // what the crawler found: each sighting that became this listing, merged into it or was closed as the same as it
        // joins through adopt() (a sighting still waiting keeps its own identity)
        $rx = $pdo->prepare("SELECT id, raw_payload FROM " . Database::t('crawler_raw_index') . " WHERE payload_type = 'listing'
                              AND ((entity_id = ? AND status IN ('published', 'merged', 'rejected')) OR (matched_entity_id = ? AND status = 'rejected')) ORDER BY id");
        $rx->execute([$id, $id]);
        foreach ($rx->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $c = json_decode((string) $r['raw_payload'], true);
            if (!is_array($c)) continue;
            if ($phone === '' && !empty($c['actions']['phone'])) $phone = self::digits((string) $c['actions']['phone']);
            if ($addr === '' && !empty($c['where']['address'])) $addr = (string) $c['where']['address'];
            if ($zip === '' && !empty($c['where']['zip'])) $zip = (string) $c['where']['zip'];
            self::adopt($pdo, (int) $r['id'], $id);
        }
        // the sources we read: a page that lists the same phone, or the same street number in the same ZIP, with the name
        self::fromReferences($pdo, (string) $e['name'], $phone, $zip, $addr, $add);
        self::recompute($pdo, $K, $id);
        $c = $pdo->prepare("SELECT COUNT(*) FROM " . Database::t('identity_facts') . " WHERE record_kind = 'listing' AND record_id = ?"); $c->execute([$id]);
        return (int) $c->fetchColumn();
    }

    // ---- step 6: places and resource lines, on the same card ------------------------------------------------------

    /** A record's kind and number from how pages name it: a landmark's GNIS number, Recreation.gov's "a1100" or
     *  "f234567", a resource line's number. [kind, id] or null. */
    public static function ref(string $kind, string $id): ?array
    {
        if (in_array($kind, ['recarea', 'facility', 'outdoor'], true) && preg_match('/^([af])(\d{1,15})$/', $id, $m)) return [$m[1] === 'a' ? 'recarea' : 'facility', (int) $m[2]];
        if (isset(self::KINDS[$kind]) && ctype_digit($id) && (int) $id > 0) return [$kind, (int) $id];
        return null;
    }

    /** What the card shows at its top: ['name', 'sub', 'url' (its public page), 'edit' (where staff change it), 'ref'] or null. */
    public static function record(PDO $pdo, string $kind, int $id): ?array
    {
        try {
            if ($kind === 'listing') {
                $st = $pdo->prepare('SELECT e.name, e.status, e.primary_zip, c.name AS category FROM entities e LEFT JOIN categories c ON c.id = e.category_id WHERE e.id = ?'); $st->execute([$id]);
                $r = $st->fetch(PDO::FETCH_ASSOC);
                return $r ? ['name' => $r['name'], 'sub' => ($r['category'] ?: 'Listing') . ' · ' . $r['primary_zip'] . ' · #' . $id . ' · ' . $r['status'], 'url' => '/listing/view.php?id=' . $id, 'edit' => '/admin/listing-edit.php?id=' . $id, 'ref' => (string) $id] : null;
            }
            if ($kind === 'landmark') {
                require_once __DIR__ . '/Landmarks.php';
                $st = $pdo->prepare('SELECT name, fclass, state, county, status FROM landmarks WHERE gnis_id = ?'); $st->execute([$id]);
                $r = $st->fetch(PDO::FETCH_ASSOC);
                if (!$r || !Landmarks::visible($r)) return null;   // waiting or closed (a nation's land): no card at all
                return ['name' => $r['name'], 'sub' => $r['fclass'] . ' · ' . $r['county'] . ' County, ' . $r['state'] . ' · GNIS ' . $id, 'url' => '/place/landmark.php?id=' . $id, 'edit' => '/admin/landmarks.php', 'ref' => (string) $id];
            }
            if ($kind === 'recarea' || $kind === 'facility') {
                $rid = ($kind === 'recarea' ? 'a' : 'f') . $id;
                $st = $pdo->prepare('SELECT name, type, org, state FROM rec_places WHERE ridb_id = ? AND hidden = 0'); $st->execute([$rid]);
                $r = $st->fetch(PDO::FETCH_ASSOC);
                return $r ? ['name' => $r['name'], 'sub' => ($r['type'] ?: 'Outdoor place') . ' · ' . $r['org'] . ' · ' . $r['state'] . ' · Recreation.gov ' . $rid, 'url' => '/place/recreation.php?id=' . $rid, 'edit' => '', 'ref' => $rid] : null;
            }
            if ($kind === 'resource') {
                $st = $pdo->prepare('SELECT e.name, e.status, e.guide_id, g.title FROM resource_entries e LEFT JOIN resource_guides g ON g.id = e.guide_id WHERE e.id = ?'); $st->execute([$id]);
                $r = $st->fetch(PDO::FETCH_ASSOC);
                return $r ? ['name' => $r['name'], 'sub' => 'Resource line · ' . ($r['title'] ?: 'guide ' . $r['guide_id']) . ' · #' . $id . ($r['status'] !== 'live' ? ' · hidden' : ''), 'url' => '/guide/resources.php?id=' . (int) $r['guide_id'], 'edit' => '/admin/resource-guides.php?id=' . (int) $r['guide_id'], 'ref' => (string) $id] : null;
            }
        } catch (Throwable) {}
        return null;
    }

    /** Builds (or refreshes) any kind's identity. Returns the number of facts (0: nothing to build, or not shown). */
    public static function build(PDO $pdo, string $kind, int $id): int
    {
        return match ($kind) {
            'listing' => self::fromListing($pdo, $id),
            'landmark' => self::fromLandmark($pdo, $id),
            'recarea', 'facility' => self::fromRec($pdo, $kind, $id),
            'resource' => self::fromResource($pdo, $id),
            default => 0,
        };
    }

    /** Starts a rebuild of a non-listing record: what this builder added before goes; what people added stays. */
    private static function clearBuilt(PDO $pdo, string $kind, int $id): void
    {
        $mine = "SELECT id FROM " . Database::t('identity_facts') . " WHERE record_kind = ? AND record_id = ?";
        $pdo->prepare("DELETE FROM " . Database::t('identity_fact_sources') . " WHERE fact_id IN ($mine) AND added_by IS NULL")->execute([$kind, $id]);
        $pdo->prepare("DELETE FROM " . Database::t('identity_facts') . " WHERE record_kind = ? AND record_id = ? AND id NOT IN (SELECT fact_id FROM " . Database::t('identity_fact_sources') . ")")->execute([$kind, $id]);
    }

    /** All of a record's facts, gone: for a place that isn't shown (on a nation's land and not opened, or hidden). */
    private static function forget(PDO $pdo, string $kind, int $id): int
    {
        $pdo->prepare("DELETE FROM " . Database::t('identity_fact_sources') . " WHERE fact_id IN (SELECT id FROM " . Database::t('identity_facts') . " WHERE record_kind = ? AND record_id = ?)")->execute([$kind, $id]);
        $pdo->prepare("DELETE FROM " . Database::t('identity_facts') . " WHERE record_kind = ? AND record_id = ?")->execute([$kind, $id]);
        return 0;
    }

    private static function count(PDO $pdo, string $kind, int $id): int
    {
        $c = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::t('identity_facts') . ' WHERE record_kind = ? AND record_id = ?'); $c->execute([$kind, $id]);
        return (int) $c->fetchColumn();
    }

    /**
     * A landmark: its name, kind and map point from the federal names registry (USGS GNIS), its description from
     * Wikidata and Wikipedia. Only one people can see: on a nation's land only what the nation opened (decisions/0058
     * §26-27); anything waiting or closed has no identity at all, so nothing about it is kept or shown here. The nation's
     * own name is used, never the Census area's.
     */
    public static function fromLandmark(PDO $pdo, int $id): int
    {
        if (!self::ready($pdo)) return 0;
        require_once __DIR__ . '/Landmarks.php';
        $st = $pdo->prepare('SELECT * FROM landmarks WHERE gnis_id = ?'); $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r || !Landmarks::visible($r)) return self::forget($pdo, 'landmark', $id);
        $K = 'landmark';
        self::clearBuilt($pdo, $K, $id);
        $gnis = ['kind' => 'registry', 'url' => 'https://edits.nationalmap.gov/apps/gaz-domestic/public/summary/' . $id, 'label' => 'USGS names registry (GNIS)'];
        self::add($pdo, $K, $id, 'name', (string) $r['name'], $gnis);
        if ($r['fclass'] !== '') self::add($pdo, $K, $id, 'category', (string) $r['fclass'], $gnis);
        self::add($pdo, $K, $id, 'point', $r['lat'] . ',' . $r['lon'], $gnis);
        self::add($pdo, $K, $id, 'state', (string) $r['state'], $gnis);
        if ($r['near_town'] !== '') self::add($pdo, $K, $id, 'city', (string) $r['near_town'], ['kind' => 'crawler', 'label' => 'Nearest town, from our places']);
        if ($r['zip5'] !== '') self::add($pdo, $K, $id, 'zip', (string) $r['zip5'], ['kind' => 'crawler', 'label' => 'From our places']);
        if ($r['nation'] !== '') {
            require_once __DIR__ . '/Nations.php';
            self::add($pdo, $K, $id, 'nation', Nations::label($pdo, (string) $r['nation']), ['kind' => 'crawler', 'label' => 'Inside the nation\'s boundaries (US Census)']);
            $ev = (string) ($r['evidence_url'] ?? '');   // the nation's own page, the evidence it was opened with
            if (preg_match('#^https?://#i', $ev)) self::add($pdo, $K, $id, 'nation', Nations::label($pdo, (string) $r['nation']), ['kind' => 'government', 'url' => $ev, 'label' => 'The nation\'s own page (opened it to visitors)']);
        }
        if ($r['qid'] !== '' && $r['wd_desc'] !== '') self::add($pdo, $K, $id, 'description', (string) $r['wd_desc'], ['kind' => 'open_data', 'url' => 'https://www.wikidata.org/wiki/' . $r['qid'], 'label' => 'Wikidata']);
        if ($r['wiki_title'] !== '') {   // Wikipedia: cited with a link (its rule), its words kept only where the rule allows
            require_once __DIR__ . '/Landmarks.php';
            $wurl = 'https://en.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', (string) $r['wiki_title']));
            if (trim((string) $r['extract']) !== '' && Landmarks::wikiAllowed($pdo)) self::add($pdo, $K, $id, 'description', mb_strimwidth(trim((string) $r['extract']), 0, 590, '…'), ['kind' => 'open_data', 'url' => $wurl, 'label' => 'Wikipedia']);
            else self::add($pdo, $K, $id, 'social', $wurl, ['kind' => 'evidence', 'url' => $wurl, 'label' => 'Wikipedia (cited)']);
        }
        self::recompute($pdo, $K, $id);
        return self::count($pdo, $K, $id);
    }

    /** An outdoor place from Recreation.gov (RIDB, an official federal registry): name, kind, who runs it, what to do there, phone, page, map point. */
    public static function fromRec(PDO $pdo, string $kind, int $id): int
    {
        if (!self::ready($pdo) || !in_array($kind, ['recarea', 'facility'], true)) return 0;
        $rid = ($kind === 'recarea' ? 'a' : 'f') . $id;
        $st = $pdo->prepare('SELECT * FROM rec_places WHERE ridb_id = ?'); $st->execute([$rid]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r || (int) $r['hidden']) return self::forget($pdo, $kind, $id);
        self::clearBuilt($pdo, $kind, $id);
        $url = (string) $r['url'];
        $page = str_contains($url, 'recreation.gov/search') ? 'https://www.recreation.gov/' : $url;   // a search page isn't the place's own page
        $ridb = ['kind' => 'registry', 'url' => $page, 'label' => 'Recreation.gov (RIDB)'];
        $add = fn (string $f, string $v) => trim($v) !== '' ? self::add($pdo, $kind, $id, $f, trim($v), $ridb) : 0;
        $add('name', (string) $r['name']);
        $add('category', (string) $r['type']);
        $add('run_by', (string) $r['org']);
        $add('description', (string) $r['summary']);
        foreach (array_filter(array_map('trim', explode(',', (string) $r['activities']))) as $a)
            $add('offerings', $a === mb_strtoupper($a) ? ucfirst(mb_strtolower($a)) : $a);   // RIDB writes them in capitals
        $add('phone', (string) $r['phone']);
        if ($page !== 'https://www.recreation.gov/') $add('website', $url);
        if ($r['lat'] !== null && $r['lon'] !== null) $add('point', $r['lat'] . ',' . $r['lon']);
        $add('city', (string) $r['city']); $add('state', (string) $r['state']); $add('zip', (string) $r['zip5']);
        self::recompute($pdo, $kind, $id);
        return self::count($pdo, $kind, $id);
    }

    /**
     * A resource line (decisions/0061): what staff entered (name, phone, hours, purpose, the situations it answers),
     * and the provider's own page. The page confirms the phone only when the last check found the number on it.
     */
    public static function fromResource(PDO $pdo, int $id): int
    {
        if (!self::ready($pdo)) return 0;
        $st = $pdo->prepare('SELECT * FROM resource_entries WHERE id = ?'); $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return self::forget($pdo, 'resource', $id);
        $K = 'resource';
        self::clearBuilt($pdo, $K, $id);
        $staff = ['kind' => 'staff', 'label' => 'Entered by staff (resource guide)'];
        $add = fn (string $f, string $v, array $src) => trim($v) !== '' ? self::add($pdo, $K, $id, $f, trim($v), $src) : 0;
        $add('name', (string) $r['name'], $staff);
        $add('phone', (string) $r['phone'], $staff);
        $add('hours', (string) $r['hours'], $staff);
        $add('purpose', (string) $r['purpose'], $staff);
        $slugs = array_values(array_filter(explode(',', (string) $r['situations'])));
        if ($slugs) {
            try {
                $sq = $pdo->prepare('SELECT label FROM situations WHERE slug IN (' . implode(',', array_fill(0, count($slugs), '?')) . ')'); $sq->execute($slugs);
                foreach ($sq->fetchAll(PDO::FETCH_COLUMN) as $l) $add('answers', (string) $l, $staff);
            } catch (Throwable) {}
        }
        $url = (string) $r['source_url'];
        if ($url !== '') {
            $kd = self::kindFor($url);
            $pageSrc = ['kind' => $kd === 'source' ? 'own_site' : $kd, 'url' => $url, 'label' => 'The provider\'s own page'];
            $add('website', $url, $pageSrc);
            if ((int) $r['verified'] && $r['phone'] !== '')
                $add('phone', (string) $r['phone'], $pageSrc + ['label' => 'The provider\'s own page: the number was on it' . ($r['last_checked'] ? ' (' . date('M j, Y', strtotime((string) $r['last_checked'])) . ')' : '')]);
        }
        self::recompute($pdo, $K, $id);
        return self::count($pdo, $K, $id);
    }

    /** Any kind's records in batches, by id: ['done', 'last', 'more']. Only what people can see is built. */
    public static function buildKind(PDO $pdo, string $kind, int $after, int $limit = 150): array
    {
        if ($kind === 'listing') return self::buildListings($pdo, $after, $limit);
        $limit = max(1, min(500, $limit));
        $ids = [];
        try {
            if ($kind === 'landmark') {
                require_once __DIR__ . '/Landmarks.php';
                $st = $pdo->prepare('SELECT gnis_id FROM landmarks WHERE gnis_id > ? AND ' . Landmarks::visibleSql() . ' ORDER BY gnis_id LIMIT ' . $limit); $st->execute([$after]);
                $ids = $st->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($kind === 'recarea' || $kind === 'facility') {
                // ids are text ("f234567"): read them all once, in number order, and take the next batch
                $st = $pdo->prepare('SELECT ridb_id FROM rec_places WHERE kind = ? AND hidden = 0'); $st->execute([$kind]);
                $nums = [];
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $rid) if (preg_match('/^[af](\d{1,15})$/', (string) $rid, $m) && (int) $m[1] > $after) $nums[] = (int) $m[1];
                sort($nums); $ids = array_slice($nums, 0, $limit);
            } elseif ($kind === 'resource') {
                $st = $pdo->prepare('SELECT id FROM resource_entries WHERE id > ? ORDER BY id LIMIT ' . $limit); $st->execute([$after]);
                $ids = $st->fetchAll(PDO::FETCH_COLUMN);
            }
        } catch (Throwable $e) { error_log('identity build ' . $kind . ': ' . $e->getMessage()); }
        $done = 0; $last = $after;
        foreach ($ids as $i) { try { if (self::build($pdo, $kind, (int) $i)) $done++; } catch (Throwable $e) { error_log("identity $kind $i: " . $e->getMessage()); } $last = (int) $i; }
        return ['done' => $done, 'last' => $last, 'more' => count($ids) === $limit];
    }

    /** Numbers for each kind: records people can see, built, confirmed. */
    public static function kindStats(PDO $pdo, string $kind): array
    {
        $one = function (string $sql, array $a = []) use ($pdo): int { try { $st = $pdo->prepare($sql); $st->execute($a); return (int) $st->fetchColumn(); } catch (Throwable) { return 0; } };
        require_once __DIR__ . '/Landmarks.php';
        $records = match ($kind) {
            'landmark' => $one('SELECT COUNT(*) FROM landmarks WHERE ' . Landmarks::visibleSql()),
            'recarea', 'facility' => $one('SELECT COUNT(*) FROM rec_places WHERE kind = ? AND hidden = 0', [$kind]),
            'resource' => $one('SELECT COUNT(*) FROM resource_entries'),
            default => 0,
        };
        $built = $one('SELECT COUNT(DISTINCT record_id) FROM ' . Database::t('identity_facts') . ' WHERE record_kind = ?', [$kind]);
        $confirmed = $one("SELECT COUNT(DISTINCT record_id) FROM " . Database::t('identity_facts') . " WHERE record_kind = ? AND fact = 'name' AND status = 'confirmed'", [$kind]);
        $single = $one("SELECT COUNT(DISTINCT record_id) FROM " . Database::t('identity_facts') . " WHERE record_kind = ? AND status = 'single' AND is_current = 1 AND fact IN ('name', 'phone', 'point')", [$kind]);
        return compact('records', 'built', 'confirmed', 'single');
    }

    /** Builds listings' identities in batches, by id: returns ['done' => n, 'last' => id, 'more' => bool]. */
    public static function buildListings(PDO $pdo, int $after, int $limit = 150): array
    {
        $st = $pdo->prepare("SELECT id FROM entities WHERE entity_type = 'merchant' AND is_suppressed = 0 AND id > ? ORDER BY id LIMIT " . max(1, min(500, $limit)));
        $st->execute([$after]);
        $ids = $st->fetchAll(PDO::FETCH_COLUMN);
        $done = 0; $last = $after;
        foreach ($ids as $i) { try { self::fromListing($pdo, (int) $i); $done++; } catch (Throwable $e) { error_log('identity ' . $i . ': ' . $e->getMessage()); } $last = (int) $i; }
        return ['done' => $done, 'last' => $last, 'more' => count($ids) === max(1, min(500, $limit))];
    }

    /** Numbers for Admin: listings with an identity, confirmed, missing a way to reach them, in conflict. */
    public static function stats(PDO $pdo): array
    {
        $one = function (string $sql) use ($pdo): int { try { return (int) $pdo->query($sql)->fetchColumn(); } catch (Throwable) { return 0; } };
        $listings = $one("SELECT COUNT(*) FROM entities WHERE entity_type = 'merchant' AND is_suppressed = 0");
        $built = $one("SELECT COUNT(DISTINCT record_id) FROM " . Database::t('identity_facts') . " WHERE record_kind = 'listing'");
        $noReach = $one("SELECT COUNT(*) FROM (SELECT record_id FROM " . Database::t('identity_facts') . " WHERE record_kind = 'listing' GROUP BY record_id HAVING SUM(CASE WHEN fact IN ('phone', 'website') THEN 1 ELSE 0 END) = 0) x");
        $conflicts = $one("SELECT COUNT(DISTINCT record_id) FROM " . Database::t('identity_facts') . " WHERE record_kind = 'listing' AND status = 'conflict'");
        $confirmedName = $one("SELECT COUNT(DISTINCT record_id) FROM " . Database::t('identity_facts') . " WHERE record_kind = 'listing' AND fact = 'name' AND status = 'confirmed'");
        $sources = $one("SELECT COUNT(*) FROM " . Database::t('identity_fact_sources') . "");
        return compact('listings', 'built', 'noReach', 'conflicts', 'confirmedName', 'sources');
    }
}
