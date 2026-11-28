<?php
// app/lib/ListingIdentity.php — One identity check, on every path (decisions/0164 §3.1, §3.2's note of 2026-11-15, §6.1;
// 0163 §12's numbers; the cause: handoffs/duplicates.md). Jason, 2026-11-15: "a process failure … at the fundamental level
// in which the data was captured and evaluated"; "the knowledge engine is the master of this process".
//
//   resolve($pdo, $cand, $opt)   the match rules in one place, read FRESH from the identity keys (never a match stored
//                                earlier), hidden twins included, every candidate scored (no cap). Called inside the
//                                lock by everything that creates or un-hides a listing.
//   locked($pdo, $zip, $fn)      the identity lock: two workers can't both make one business (5 s, then once more)
//   fold($finds)                 a batch's finds grouped by identity key into one find with all their sources
//   merge($pdo, $keep, $drop…)   the drop folds into the keep: every table holding its id moves (found from the
//                                staging register's refOf over information_schema), gaps only (R4), logged for an exact Undo
//   undo($pdo, $mergeId…)        a merge undone exactly; the pair is recorded "not the same", never merged again
//   different($pdo, $a, $b…)     a person's "a different business", on the pair for good (R11), read by every path
//   recheckTick($pdo, $ids)      W2's task `recheck`: exact twins merge by themselves; variations to "Vet this place"
//                                (W2's `identity` task); what evidence can't settle, or anything claimed or a person's,
//                                to the identity queue (a step kind of "The engine needs a person", EngineCase)
//
// Shipped off: the recheck is a task of W2 on the switches (0163 §9), disabled until a person switches it on. The check on
// the write paths runs whenever its keys are there (its database update), since it only stops a twin being made.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Identity.php';

/** PHP 8.0 has no array_is_list (8.1): a list is keyed 0..n-1. */
function array_is_list_(array $a): bool { return $a === [] || array_keys($a) === range(0, count($a) - 1); }

final class ListingIdentity
{
    /** 0163 §12's settings (tuned by a director through WorkflowSettings W2 'identity'; these are the starting values). */
    public const SETTINGS = [
        'twin_name' => 0.95,      // `same` at ≥ this, with the same phone, name and ZIP or the same address: merges by itself
        'main_line' => 4,         // a phone shared by more listings than this is a main line: never proof alone
        'branch_m' => 150,        // "another location" needs points at least this far apart (or a different phone)
        'lock_wait' => 5,         // seconds the identity lock waits, then the write retries once
        'slice' => 2000,          // the recheck's daily slice, oldest checked first
        'run_merges' => 200,      // pairs merged in one run, at most
        'town_pct' => 5,          // more than this share of a town's listings in one run waits for a person's OK
    ];

    public const TASK = 'recheck';
    public const WF = 'W2';
    /** Legal endings folded for matching (kept on the listing as written). */
    private const LEGAL = ['inc', 'incorporated', 'llc', 'l l c', 'pllc', 'pc', 'plc', 'lp', 'llp', 'ltd', 'limited', 'corp', 'corporation', 'co', 'company', 'dba', 'the'];

    private static ?bool $ready = null;

    public static function ready(PDO $pdo): bool
    {
        if (self::$ready !== null) return self::$ready;
        try { $pdo->query('SELECT ident_phone, ident_name, ident_addr, ident_geo FROM entities LIMIT 0'); $pdo->query('SELECT 1 FROM identity_pairs LIMIT 0'); $pdo->query('SELECT 1 FROM identity_merges LIMIT 0'); return self::$ready = true; }
        catch (Throwable) { return self::$ready = false; }
    }

    public static function setting(?PDO $pdo, string $k): float
    {
        $v = self::SETTINGS[$k];
        if ($pdo) try { require_once __DIR__ . '/WorkflowSettings.php'; $s = WorkflowSettings::current($pdo, self::WF); if (isset($s['identity'][$k]) && is_numeric($s['identity'][$k])) $v = $s['identity'][$k] + 0; } catch (Throwable) {}
        return (float) $v;
    }

    // ---- the keys (§3.1: extensions removed, legal endings and plurals folded, street types both ways, a PO Box never a street)

    public static function phoneKey(string $p): string { return Identity::digits($p); }

    public static function nameKey(string $n): string
    {
        $k = ' ' . Identity::nameKey($n) . ' ';
        foreach (self::LEGAL as $w) $k = str_replace(' ' . $w . ' ', ' ', $k);
        $out = [];
        foreach (preg_split('/\s+/', trim($k)) as $w) {
            if ($w === '') continue;
            if (strlen($w) > 3 && str_ends_with($w, 's') && !preg_match('/(ss|us|is)$/', $w)) $w = substr($w, 0, -1);   // "Flights" is "Flight"
            $out[] = $w;
        }
        return mb_substr(implode(' ', $out), 0, 160);
    }

    public static function addrKey(string $a): string
    {
        if (self::poBox($a)) return '';
        return mb_substr(Identity::addr($a), 0, 160);
    }

    public static function poBox(string $a): bool { return (bool) preg_match('/^\s*(p\.?\s*o\.?\s*)?(box|drawer)\s*#?\s*\d+/i', $a); }

    /** A geohash of 7 characters (about 150 m) for a point, '' without one. */
    public static function geohash(?float $lat, ?float $lng, int $len = 7): string
    {
        if ($lat === null || $lng === null || ($lat == 0.0 && $lng == 0.0)) return '';
        $b32 = '0123456789bcdefghjkmnpqrstuvwxyz'; $la = [-90.0, 90.0]; $lo = [-180.0, 180.0]; $h = ''; $bit = 0; $ch = 0; $even = true;
        while (strlen($h) < $len) {
            if ($even) { $m = ($lo[0] + $lo[1]) / 2; if ($lng >= $m) { $ch |= 16 >> $bit; $lo[0] = $m; } else $lo[1] = $m; }
            else { $m = ($la[0] + $la[1]) / 2; if ($lat >= $m) { $ch |= 16 >> $bit; $la[0] = $m; } else $la[1] = $m; }
            $even = !$even;
            if ($bit < 4) $bit++; else { $h .= $b32[$ch]; $bit = 0; $ch = 0; }
        }
        return $h;
    }

    /** Metres between two points. */
    public static function metres(float $a, float $b, float $c, float $d): float
    {
        $r = 6371000; $p1 = deg2rad($a); $p2 = deg2rad($c); $dp = deg2rad($c - $a); $dl = deg2rad($d - $b);
        $x = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
        return 2 * $r * atan2(sqrt($x), sqrt(1 - $x));
    }

    /** A listing's keys written (on create, edit, and by the recheck for rows without them). */
    public static function writeKeys(PDO $pdo, int $id): void
    {
        if (!self::ready($pdo) || $id <= 0) return;
        try {
            $l = self::load($pdo, [$id])[$id] ?? null;
            if (!$l) return;
            $ph = self::phoneKey($l['phone']); $nm = self::nameKey($l['name']);
            $a = [$ph ?: null, $nm ?: null, self::addrKey($l['address']) ?: null, self::geohash($l['lat'], $l['lng']) ?: null];
            // the database guard's value (unique once the twins are cleared): only a live listing with a phone and a name holds one
            $live = !$l['hidden'] && $ph !== '' && $nm !== '' ? mb_substr($l['zip'] . ':' . $ph . ':' . mb_substr($nm, 0, 80), 0, 100) : null;
            try { $pdo->prepare('UPDATE entities SET ident_phone = ?, ident_name = ?, ident_addr = ?, ident_geo = ?, ident_live = ?, ident_at = UTC_TIMESTAMP() WHERE id = ?')->execute(array_merge($a, [$live, $id])); }
            catch (PDOException $x) {
                if ((string) $x->getCode() !== '23000') throw $x;
                // the guard caught a twin the check let through (two workers past the lock's wait): written without it, and to the engine
                $pdo->prepare('UPDATE entities SET ident_phone = ?, ident_name = ?, ident_addr = ?, ident_geo = ?, ident_live = NULL, ident_at = UTC_TIMESTAMP() WHERE id = ?')->execute(array_merge($a, [$id]));
                $o = $pdo->prepare('SELECT id FROM entities WHERE ident_live = ? AND id <> ?'); $o->execute([$live, $id]); $other = (int) $o->fetchColumn();
                if ($other) { require_once __DIR__ . '/EngineCase.php'; EngineCase::raise($pdo, 'identity', 'pair:' . min($id, $other) . ':' . max($id, $other), 'The database guard found "' . $l['name'] . '" listed twice (#' . $other . ' and #' . $id . ')', ['a' => $other, 'b' => $id]); }
            }
        } catch (Throwable $x) { error_log('identity keys #' . $id . ': ' . $x->getMessage()); }
    }

    /** Keys for a ZIP's listings that have none yet (so a check never misses a row older than the keys). */
    public static function ensureKeys(PDO $pdo, string $zip): void
    {
        if (!self::ready($pdo) || $zip === '') return;
        $st = $pdo->prepare("SELECT id FROM entities WHERE entity_type = 'merchant' AND primary_zip = ? AND ident_at IS NULL LIMIT 3000");
        $st->execute([$zip]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) self::writeKeys($pdo, (int) $id);
    }

    // ---- candidates ------------------------------------------------------------------------------------------------

    /**
     * A candidate from any shape: ['name', 'address', 'zip', 'phone', 'website', 'lat', 'lng'] or a crawler find
     * (['name', 'where' => [...], 'actions' => [...], 'geo' => [...]]).
     */
    public static function cand(array $c): array
    {
        if (isset($c['where']) || isset($c['actions'])) return $c;
        return ['name' => (string) ($c['name'] ?? ''), 'where' => ['address' => (string) ($c['address'] ?? ''), 'zip' => substr(trim((string) ($c['zip'] ?? '')), 0, 5), 'city' => (string) ($c['city'] ?? $c['town'] ?? '')],
                'actions' => ['phone' => (string) ($c['phone'] ?? ''), 'website' => (string) ($c['website'] ?? '')],
                'geo' => ['latitude' => $c['lat'] ?? $c['latitude'] ?? null, 'longitude' => $c['lng'] ?? $c['longitude'] ?? null]];
    }

    /** Listings by id with what the check reads: name, phone, address, ZIP, website, other names, point, claimed, a person's. */
    public static function load(PDO $pdo, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $in = implode(',', $chunk);
            foreach ($pdo->query("SELECT id, name, primary_zip, latitude, longitude, status, is_suppressed, created_at, created_by_type FROM entities WHERE id IN ($in)")->fetchAll(PDO::FETCH_ASSOC) as $e)
                $out[(int) $e['id']] = ['id' => (int) $e['id'], 'name' => (string) $e['name'], 'zip' => substr(trim((string) $e['primary_zip']), 0, 5), 'lat' => $e['latitude'] !== null ? (float) $e['latitude'] : null,
                    'lng' => $e['longitude'] !== null ? (float) $e['longitude'] : null, 'claimed' => !in_array((string) $e['status'], ['unclaimed', ''], true), 'hidden' => (int) $e['is_suppressed'] === 1,
                    'created' => (string) $e['created_at'], 'by' => (string) $e['created_by_type'], 'phone' => '', 'address' => '', 'website' => '', 'aka' => [], 'person' => in_array((string) $e['created_by_type'], ['owner', 'admin', 'user', 'staff'], true), 'same_as' => 0];
            foreach ($pdo->query("SELECT entity_id, meta_key, meta_value, source_type, is_locked FROM entity_metadata WHERE entity_id IN ($in) AND meta_key IN ('phone','address','website','aka','same_as')")->fetchAll(PDO::FETCH_ASSOC) as $m) {
                $id = (int) $m['entity_id']; if (!isset($out[$id])) continue;
                $v = json_decode((string) $m['meta_value'], true); if ($v === null) $v = (string) $m['meta_value'];
                if ($m['meta_key'] === 'aka') { $out[$id]['aka'] = array_values(array_filter((array) $v, 'is_string')); continue; }
                if ($m['meta_key'] === 'same_as') { $out[$id]['same_as'] = (int) (is_array($v) ? ($v['id'] ?? 0) : 0); continue; }
                $out[$id][$m['meta_key']] = is_array($v) ? (string) ($v['value'] ?? $v['line'] ?? '') : (string) $v;
                if ((int) $m['is_locked'] === 1 || in_array((string) $m['source_type'], ['admin', 'owner', 'user'], true)) $out[$id]['person'] = true;
            }
        }
        return $out;
    }

    /** Candidate ids for a find, read fresh: its ZIP, its phone anywhere, its point's neighbourhood (geohash 6). */
    public static function candidateIds(PDO $pdo, array $c, array $exclude = []): array
    {
        $zip = substr(trim((string) ($c['where']['zip'] ?? '')), 0, 5);
        $phone = self::phoneKey((string) ($c['actions']['phone'] ?? ''));
        $gh = self::geohash(isset($c['geo']['latitude']) && $c['geo']['latitude'] !== '' ? (float) $c['geo']['latitude'] : null, isset($c['geo']['longitude']) && $c['geo']['longitude'] !== '' ? (float) $c['geo']['longitude'] : null, 6);
        $ids = [];
        $q = function (string $sql, array $a) use ($pdo, &$ids) { $st = $pdo->prepare($sql); $st->execute($a); foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $i) $ids[(int) $i] = true; };
        if ($zip !== '') { self::ensureKeys($pdo, $zip); $q("SELECT id FROM entities WHERE entity_type = 'merchant' AND primary_zip = ?", [$zip]); }
        if (self::ready($pdo)) {
            if ($phone !== '') $q("SELECT id FROM entities WHERE entity_type = 'merchant' AND ident_phone = ?", [$phone]);
            if ($gh !== '') $q("SELECT id FROM entities WHERE entity_type = 'merchant' AND ident_geo LIKE ?", [$gh . '%']);
        } elseif ($phone !== '') {   // before the keys' database update: the phone's digits in the stored value
            $st = $pdo->prepare("SELECT entity_id, meta_value FROM entity_metadata WHERE meta_key = 'phone' AND meta_value LIKE ?");
            $st->execute(['%' . substr($phone, -4) . '%']);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) if (self::phoneKey((string) (json_decode((string) $r['meta_value'], true) ?? $r['meta_value'])) === $phone) $ids[(int) $r['entity_id']] = true;
        }
        foreach ($exclude as $x) unset($ids[(int) $x]);
        return array_keys($ids);
    }

    /** How many listings share a phone (a main line when more than the setting). */
    public static function phoneShare(PDO $pdo, string $phone): int
    {
        if ($phone === '' || !self::ready($pdo)) return 0;
        $st = $pdo->prepare("SELECT COUNT(*) FROM entities WHERE entity_type = 'merchant' AND is_suppressed = 0 AND ident_phone = ?"); $st->execute([$phone]);
        return (int) $st->fetchColumn();
    }

    /** One candidate against one listing: AutoImport::relationTo with the tuned rules (its geo and main-line inputs). */
    public static function relate(array $c, array $l, bool $mainLine = false): array
    {
        require_once __DIR__ . '/crawler/AutoImport.php';
        $name = $l['name']; $best = AutoImport::sameName((string) ($c['name'] ?? ''), $name);
        foreach ($l['aka'] as $a) if (($x = AutoImport::sameName((string) ($c['name'] ?? ''), $a)) > $best) { $best = $x; $name = $a; }
        $geo = ['lat' => $l['lat'], 'lng' => $l['lng'], 'main_line' => $mainLine];
        $r = AutoImport::relationTo($c, $name, $l['phone'], $l['address'], $l['zip'], $l['website'], $geo);
        $r['name_key'] = self::nameKey((string) ($c['name'] ?? '')) !== '' && self::nameKey((string) ($c['name'] ?? '')) === self::nameKey($name);
        $r['addr_key'] = ($k = self::addrKey((string) ($c['where']['address'] ?? ''))) !== '' && $k === self::addrKey($l['address']);
        $r['zip'] = substr(trim((string) ($c['where']['zip'] ?? '')), 0, 5) === $l['zip'];
        return $r;
    }

    /** The pairs a person (or an Undo) called different: [otherId => true] for one listing. */
    public static function differentFrom(PDO $pdo, int $id): array
    {
        if (!self::ready($pdo) || $id <= 0) return [];
        $st = $pdo->prepare("SELECT a_id, b_id FROM identity_pairs WHERE verdict = 'different' AND (a_id = ? OR b_id = ?)"); $st->execute([$id, $id]);
        $out = []; foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) $out[(int) $p['a_id'] === $id ? (int) $p['b_id'] : (int) $p['a_id']] = true;
        return $out;
    }

    /**
     * The check. Returns ['kind' => 'new'|'same'|'covered'|'maybe', 'id' => the listing it is (0 for new), 'exact' => an
     * exact twin (§12: merges by itself), 'why', 'hits' => every candidate that isn't another business, best first].
     * $opt: 'exclude' => ids (the listing itself), 'different' => ids a person called different now, 'self' => the
     * listing's own id (its recorded "different" pairs are read).
     */
    public static function resolve(PDO $pdo, array $cand, array $opt = []): array
    {
        $c = self::cand($cand);
        $self = (int) ($opt['self'] ?? 0);
        $skip = array_flip(array_map('intval', (array) ($opt['different'] ?? [])));
        if ($self) $skip += self::differentFrom($pdo, $self);
        $ids = self::candidateIds($pdo, $c, array_merge((array) ($opt['exclude'] ?? []), $self ? [$self] : []));
        $phone = self::phoneKey((string) ($c['actions']['phone'] ?? ''));
        $main = $phone !== '' && self::phoneShare($pdo, $phone) > self::setting($pdo, 'main_line');
        $hits = [];
        foreach (self::load($pdo, $ids) as $id => $l) {
            if (isset($skip[$id])) continue;
            if ($l['hidden'] && $l['same_as']) continue;   // already merged into another: its keep answers for it
            $r = self::relate($c, $l, $main);
            if (!in_array($r['kind'], ['same', 'covered', 'maybe'], true)) continue;
            $exact = $r['kind'] === 'same' && $r['name'] >= self::setting($pdo, 'twin_name') && $r['zip'] && (($r['phone'] && $r['name_key']) || $r['addr_key']);
            $hits[] = ['id' => $id, 'kind' => $r['kind'], 'exact' => $exact, 'score' => $r['name'], 'claimed' => $l['claimed'], 'person' => $l['person'], 'hidden' => $l['hidden'], 'created' => $l['created'],
                       'why' => implode(', ', array_filter([$r['phone'] ? 'same phone' : '', $r['addr'] ? 'same street address' : '', !empty($r['site']) ? 'same website' : '', $r['zip'] ? 'same ZIP' : '', (int) round($r['name'] * 100) . '% of the name'])), 'name' => $l['name']];
        }
        $rank = ['same' => 0, 'covered' => 1, 'maybe' => 2];
        usort($hits, fn ($a, $b) => [$rank[$a['kind']], !$a['exact'], $a['hidden'], !$a['claimed'], $a['created'], $a['id']] <=> [$rank[$b['kind']], !$b['exact'], $b['hidden'], !$b['claimed'], $b['created'], $b['id']]);
        if (!$hits) return ['kind' => 'new', 'id' => 0, 'exact' => false, 'why' => 'Nothing listed matches it.', 'hits' => []];
        $h = $hits[0];
        return ['kind' => $h['kind'], 'id' => $h['id'], 'exact' => $h['exact'], 'why' => ($h['kind'] === 'same' ? 'The same business as' : ($h['kind'] === 'covered' ? 'Covered by' : 'May be')) . ' "' . $h['name'] . '" (#' . $h['id'] . '): ' . $h['why'], 'hits' => $hits];
    }

    // ---- the lock ----------------------------------------------------------------------------------------------------

    /** Runs $fn holding the identity lock for a ZIP (or a phone): two workers can't both make it. */
    public static function locked(PDO $pdo, string $zip, callable $fn)
    {
        $name = 'tv_ident:' . (preg_replace('/[^0-9a-z]/i', '', $zip) ?: 'none');
        $got = false;
        for ($try = 0; $try < 2 && !$got; $try++) {
            try { $st = $pdo->prepare('SELECT GET_LOCK(?, ?)'); $st->execute([$name, (int) self::setting(null, 'lock_wait')]); $got = (int) $st->fetchColumn() === 1; } catch (Throwable) { break; }
        }
        if (!$got) error_log('identity lock ' . $name . ': not taken, writing anyway after its retry');
        try { return $fn(); }
        finally { if ($got) try { $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$name]); } catch (Throwable) {} }
    }

    // ---- within a batch (§3.1) ---------------------------------------------------------------------------------------

    /**
     * A load's finds grouped by identity key into one find with all their sources (one operator's two NPI records at one
     * base arrive as one find with two sources). Groups the rules call another location or a provider stay apart.
     * Each find: the crawler's shape; 'sources' (list) is merged, empty fields filled from the others.
     */
    public static function fold(array $finds): array
    {
        $out = [];
        foreach ($finds as $f) {
            $c = self::cand($f);
            $joined = false;
            foreach ($out as $i => $o) {
                $l = ['name' => (string) ($o['name'] ?? ''), 'phone' => (string) ($o['actions']['phone'] ?? ''), 'address' => (string) ($o['where']['address'] ?? ''), 'zip' => substr(trim((string) ($o['where']['zip'] ?? '')), 0, 5),
                      'website' => (string) ($o['actions']['website'] ?? ''), 'aka' => [], 'lat' => isset($o['geo']['latitude']) && $o['geo']['latitude'] !== '' ? (float) $o['geo']['latitude'] : null, 'lng' => isset($o['geo']['longitude']) && $o['geo']['longitude'] !== '' ? (float) $o['geo']['longitude'] : null];
                $r = self::relate($c, $l);
                if ($r['kind'] !== 'same') continue;
                $a = (array) ($o['sources'] ?? (isset($o['source']) ? [$o['source']] : [])); $b = (array) ($c['sources'] ?? (isset($c['source']) ? [$c['source']] : []));
                if ($a && !array_is_list_($a)) { foreach ($b as $f => $urls) $a[$f] = array_values(array_unique(array_merge((array) ($a[$f] ?? []), (array) $urls))); $out[$i]['sources'] = $a; }   // fact => [urls]
                else $out[$i]['sources'] = array_values(array_merge($a, $b));
                if (isset($c['source'])) $out[$i]['also'][] = $c['source'];   // each folded record's own source (an NPI each), kept
                $out[$i]['corroboration'] = array_values(array_merge((array) ($o['corroboration'] ?? []), (array) ($c['corroboration'] ?? [])));
                foreach (['where' => ['address', 'city', 'state', 'zip'], 'actions' => ['phone', 'website', 'email'], 'geo' => ['latitude', 'longitude']] as $g => $ks)
                    foreach ($ks as $k) if ((string) ($out[$i][$g][$k] ?? '') === '' && (string) ($c[$g][$k] ?? '') !== '') $out[$i][$g][$k] = $c[$g][$k];
                $out[$i]['folded'] = (int) ($out[$i]['folded'] ?? 1) + 1;
                $joined = true; break;
            }
            if (!$joined) $out[] = $c;
        }
        return $out;
    }

    // ---- pairs --------------------------------------------------------------------------------------------------------

    public static function pair(PDO $pdo, int $a, int $b, string $verdict, string $why, int $uid = 0, string $by = 'system'): void
    {
        if (!self::ready($pdo) || $a <= 0 || $b <= 0 || $a === $b) return;
        [$x, $y] = $a < $b ? [$a, $b] : [$b, $a];
        $pdo->prepare('INSERT INTO identity_pairs (a_id, b_id, verdict, why, by_user, by_kind, at) VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
                       ON DUPLICATE KEY UPDATE verdict = IF(verdict = \'different\' AND VALUES(by_kind) <> \'person\', verdict, VALUES(verdict)), why = VALUES(why), by_user = VALUES(by_user), by_kind = VALUES(by_kind), at = VALUES(at)')
            ->execute([$x, $y, $verdict, mb_substr($why, 0, 500), $uid ?: null, $by]);
    }

    /** A person's "a different business": on the pair for good (R11). */
    public static function different(PDO $pdo, int $a, int $b, int $uid, string $why = 'A person said they are different businesses'): void
    {
        self::pair($pdo, $a, $b, 'different', $why, $uid, 'person');
    }

    public static function verdict(PDO $pdo, int $a, int $b): ?string
    {
        if (!self::ready($pdo)) return null;
        [$x, $y] = $a < $b ? [$a, $b] : [$b, $a];
        $st = $pdo->prepare('SELECT verdict FROM identity_pairs WHERE a_id = ? AND b_id = ?'); $st->execute([$x, $y]);
        $v = $st->fetchColumn();
        return $v === false ? null : (string) $v;
    }

    // ---- merging (§6.1: which stays, what folds in, the old id, logged with Undo) -----------------------------------

    /** Which of two stays: the most evidence (independent sources, then confidence), then the oldest, then the most views. */
    public static function keeper(PDO $pdo, int $a, int $b): array
    {
        $ev = function (int $id) use ($pdo): array {
            $src = 0; $conf = 0; $views = 0;
            try { $st = $pdo->prepare('SELECT COUNT(DISTINCT s.url) FROM ' . Database::t('identity_facts') . ' f JOIN ' . Database::t('identity_fact_sources') . " s ON s.fact_id = f.id WHERE f.record_kind = 'listing' AND f.record_id = ?"); $st->execute([$id]); $src = (int) $st->fetchColumn(); } catch (Throwable) {}
            try { $st = $pdo->prepare('SELECT score FROM listing_confidence WHERE entity_id = ?'); $st->execute([$id]); $conf = (int) $st->fetchColumn(); } catch (Throwable) {}
            try { $st = $pdo->prepare("SELECT COALESCE(SUM(n), 0) FROM platform_counts WHERE metric = 'view' AND k = ?"); $st->execute(['listing:' . $id]); $views = (int) $st->fetchColumn(); } catch (Throwable) {}
            return [$src, $conf, -$id, $views];
        };
        return $ev($a) >= $ev($b) ? [$a, $b] : [$b, $a];
    }

    /** Every column (both databases) that holds a listing's id: [[schema, table, column, pk columns]]. */
    public static function refColumns(PDO $pdo): array
    {
        static $cols = null;
        if ($cols !== null) return $cols;
        require_once __DIR__ . '/staging-tables.php';
        $schemas = [(string) $pdo->query('SELECT DATABASE()')->fetchColumn()];
        try { [$adb] = Database::realmConfig('admin'); if ($adb && !in_array($adb, $schemas, true) && Database::mainCanSee($adb)) $schemas[] = $adb; } catch (Throwable) {}
        $cols = [];
        foreach ($schemas as $sc) {
            $st = $pdo->prepare("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?"); $st->execute([$sc]);
            $pks = [];
            $pk = $pdo->prepare("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION"); $pk->execute([$sc]);
            foreach ($pk->fetchAll(PDO::FETCH_ASSOC) as $r) $pks[$r['TABLE_NAME']][] = $r['COLUMN_NAME'];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $t = (string) $r['TABLE_NAME']; $col = (string) $r['COLUMN_NAME'];
                if (in_array($t, ['entities', 'entity_metadata', 'identity_pairs', 'identity_merges', 'listing_claims'], true) || str_starts_with($t, 'stg_')) continue;
                if (StagingTables::refOf($t, $col, (string) $r['DATA_TYPE']) !== 'entities') continue;
                $cols[] = [$sc, $t, $col, $pks[$t] ?? []];
            }
        }
        return $cols;
    }

    /**
     * The drop folds into the keep. Both must be unclaimed with nothing a person entered, unless $person (a person's Merge
     * from the queue, who chose which stays). Returns the ledger id. Never deletes: the drop is hidden with `same_as`.
     */
    public static function merge(PDO $pdo, int $keep, int $drop, string $why, int $uid = 0, string $by = 'system', bool $person = false, int $run = 0): int
    {
        if (!self::ready($pdo)) throw new RuntimeException('The identity check\'s database update hasn\'t run yet.');
        if ($keep === $drop || $keep <= 0 || $drop <= 0) throw new InvalidArgumentException('Choose two listings.');
        $L = self::load($pdo, [$keep, $drop]);
        if (!isset($L[$keep], $L[$drop])) throw new InvalidArgumentException('One of the two listings is gone.');
        if ($L[$drop]['claimed']) throw new InvalidArgumentException($L[$drop]['name'] . ' is managed by its owner, so it can\'t be the one hidden. Keep it instead.');
        if (!$person && ($L[$keep]['claimed'] || $L[$keep]['person'] || $L[$drop]['person'])) throw new InvalidArgumentException('A claimed listing or a person\'s entry: the identity queue decides, never the engine by itself.');
        if (self::verdict($pdo, $keep, $drop) === 'different') throw new InvalidArgumentException('A person said these are different businesses.');
        $moved = ['meta' => [], 'rows' => [], 'counts' => [], 'text' => [], 'was_hidden' => $L[$drop]['hidden']];
        $pdo->beginTransaction();
        try {
            // facts: gaps only (R4): a key the keep lacks is copied (the drop keeps its own row for an exact Undo)
            $have = array_flip($pdo->query('SELECT meta_key FROM entity_metadata WHERE entity_id = ' . $keep)->fetchAll(PDO::FETCH_COLUMN));
            $ins = $pdo->prepare('INSERT INTO entity_metadata (entity_id, meta_key, meta_value, source_type, is_locked, created_at, updated_at) SELECT ?, meta_key, meta_value, source_type, 0, NOW(), NOW() FROM entity_metadata WHERE entity_id = ? AND meta_key = ?');
            foreach ($pdo->query('SELECT meta_key FROM entity_metadata WHERE entity_id = ' . $drop)->fetchAll(PDO::FETCH_COLUMN) as $k) {
                if (isset($have[$k]) || in_array($k, ['same_as', 'aka', 'geocode', 'claim', 'closed'], true)) continue;
                if (in_array($k, ['description', 'hours'], true) && !$person) {   // the pause (2026-11-28): held for Review, not copied
                    require_once __DIR__ . '/crawler/AutoImport.php';
                    if (AutoImport::pausedWhy() !== null) {
                        $v = $pdo->prepare('SELECT meta_value FROM entity_metadata WHERE entity_id = ? AND meta_key = ?'); $v->execute([$drop, $k]);
                        $raw = (string) $v->fetchColumn(); $d = json_decode($raw, true) ?? $raw;   // a value that isn't JSON is held as it is
                        AutoImport::holdForReview($pdo, $keep, [$k => $d], 'crawler-identity-merge-paused', 'Its twin #' . $drop . ' (merged into it) has');
                        continue;
                    }
                }
                $ins->execute([$keep, $drop, $k]); $moved['meta'][] = $k;
            }
            // the drop's name kept as another name (0084)
            require_once __DIR__ . '/Aliases.php';
            try { Aliases::add($pdo, $keep, [$L[$drop]['name']], 'ai_crawler'); $moved['aka'] = $L[$drop]['name']; } catch (Throwable) {}
            // every row that points at the drop: moved where the keep has none (a unique key keeps the keep's)
            foreach (self::refColumns($pdo) as [$sc, $t, $col, $pk]) {
                $T = '`' . $sc . '`.`' . $t . '`';
                if ($pk === ['id'] || (count($pk) === 1 && $pk[0] !== $col)) {
                    $k0 = $pk[0];
                    $ids = $pdo->query("SELECT `$k0` FROM $T WHERE `$col` = $drop")->fetchAll(PDO::FETCH_COLUMN);
                    if (!$ids) continue;
                    $ok = [];
                    $up = $pdo->prepare("UPDATE IGNORE $T SET `$col` = ? WHERE `$k0` = ? AND `$col` = ?");
                    foreach ($ids as $i) { $up->execute([$keep, $i, $drop]); if ($up->rowCount()) $ok[] = $i; }
                    if ($ok) $moved['rows'][] = [$sc, $t, $col, $k0, $ok];
                } elseif ($pk && in_array($col, $pk, true)) {
                    $other = array_values(array_diff($pk, [$col]));
                    if (!$other) continue;   // the id is the whole key (one row per listing): the keep's stays
                    $sel = implode(', ', array_map(fn ($x) => "`$x`", $other));
                    $rows = $pdo->query("SELECT $sel FROM $T WHERE `$col` = $drop")->fetchAll(PDO::FETCH_ASSOC);
                    $ok = [];
                    $where = implode(' AND ', array_map(fn ($x) => "`$x` = ?", $other));
                    $up = $pdo->prepare("UPDATE IGNORE $T SET `$col` = ? WHERE `$col` = ? AND $where");
                    foreach ($rows as $r) { $up->execute(array_merge([$keep, $drop], array_values($r))); if ($up->rowCount()) $ok[] = $r; }
                    if ($ok) $moved['rows'][] = [$sc, $t, $col, $other, $ok];
                }
            }
            // views and counts add up (platform_counts' k = listing:<id>)
            try {
                $rows = $pdo->query("SELECT day, metric, n, visits FROM platform_counts WHERE k = 'listing:$drop'")->fetchAll(PDO::FETCH_ASSOC);
                $add = $pdo->prepare("INSERT INTO platform_counts (day, metric, k, n, visits) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE n = n + VALUES(n), visits = visits + VALUES(visits)");
                foreach ($rows as $r) $add->execute([$r['day'], $r['metric'], 'listing:' . $keep, (int) $r['n'], (int) $r['visits']]);
                if ($rows) $moved['counts'] = $rows;
            } catch (Throwable) {}
            // guide and content links (content_tags.ref = entity:<id>)
            try {
                $ids = $pdo->query("SELECT id FROM content_tags WHERE ref = 'entity:$drop'")->fetchAll(PDO::FETCH_COLUMN);
                if ($ids) { $pdo->exec("UPDATE content_tags SET ref = 'entity:$keep' WHERE id IN (" . implode(',', array_map('intval', $ids)) . ')'); $moved['text'][] = ['content_tags', 'ref', 'entity:', $ids]; }
            } catch (Throwable) {}
            $pdo->prepare("UPDATE entities SET is_suppressed = 1, updated_at = NOW() WHERE id = ?")->execute([$drop]);
            $pdo->prepare("INSERT INTO entity_metadata (entity_id, meta_key, meta_value, source_type, is_locked, created_at, updated_at) VALUES (?, 'same_as', ?, 'admin', 1, NOW(), NOW())
                           ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), updated_at = NOW()")
                ->execute([$drop, json_encode(['id' => $keep, 'at' => date('Y-m-d'), 'by' => $uid, 'how' => 'identity: ' . mb_substr($why, 0, 120)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
            $pdo->prepare('INSERT INTO identity_merges (keep_id, drop_id, why, by_user, by_kind, run_id, moved, at) VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())')
                ->execute([$keep, $drop, mb_substr($why, 0, 500), $uid ?: null, $by, $run ?: null, json_encode($moved, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
            $mid = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $x) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $x; }
        self::pair($pdo, $keep, $drop, 'same', $why, $uid, $person ? 'person' : 'system');
        Identity::mergeInto($pdo, $drop, $keep);   // the drop's evidence joins the keep's identity (R11)
        self::reindex($pdo, $keep, $drop);
        return $mid;
    }

    /** A merge undone exactly; the pair is recorded "not the same", never merged again (§6.1). */
    public static function undo(PDO $pdo, int $mergeId, int $uid, string $by = 'person'): array
    {
        $st = $pdo->prepare('SELECT * FROM identity_merges WHERE id = ?'); $st->execute([$mergeId]);
        $m = $st->fetch(PDO::FETCH_ASSOC);
        if (!$m) throw new InvalidArgumentException('That merge isn\'t in the ledger.');
        if ($m['undone_at'] !== null) throw new InvalidArgumentException('That merge was already undone.');
        $keep = (int) $m['keep_id']; $drop = (int) $m['drop_id']; $mv = json_decode((string) $m['moved'], true) ?: [];
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM entity_metadata WHERE entity_id = ? AND meta_key = ? AND is_locked = 0');
            foreach ((array) ($mv['meta'] ?? []) as $k) $del->execute([$keep, $k]);
            foreach ((array) ($mv['rows'] ?? []) as [$sc, $t, $col, $k0, $ok]) {
                $T = '`' . $sc . '`.`' . $t . '`';
                if (is_string($k0)) { $up = $pdo->prepare("UPDATE IGNORE $T SET `$col` = ? WHERE `$k0` = ? AND `$col` = ?"); foreach ($ok as $i) $up->execute([$drop, $i, $keep]); }
                else { $where = implode(' AND ', array_map(fn ($x) => "`$x` = ?", $k0)); $up = $pdo->prepare("UPDATE IGNORE $T SET `$col` = ? WHERE `$col` = ? AND $where"); foreach ($ok as $r) $up->execute(array_merge([$drop, $keep], array_values($r))); }
            }
            if (!empty($mv['counts'])) { $sub = $pdo->prepare('UPDATE platform_counts SET n = GREATEST(0, n - ?), visits = GREATEST(0, visits - ?) WHERE day = ? AND metric = ? AND k = ?'); foreach ($mv['counts'] as $r) $sub->execute([(int) $r['n'], (int) $r['visits'], $r['day'], $r['metric'], 'listing:' . $keep]); }
            foreach ((array) ($mv['text'] ?? []) as [$t, $col, $pre, $ids]) if ($ids) $pdo->exec("UPDATE `$t` SET `$col` = '" . $pre . $drop . "' WHERE id IN (" . implode(',', array_map('intval', $ids)) . ')');
            if (!empty($mv['aka'])) try { $a = $pdo->prepare("SELECT meta_value FROM entity_metadata WHERE entity_id = ? AND meta_key = 'aka'"); $a->execute([$keep]); $list = json_decode((string) $a->fetchColumn(), true);
                if (is_array($list)) { $list = array_values(array_filter($list, fn ($x) => $x !== $mv['aka'])); $pdo->prepare("UPDATE entity_metadata SET meta_value = ? WHERE entity_id = ? AND meta_key = 'aka'")->execute([json_encode($list, JSON_UNESCAPED_UNICODE), $keep]); } } catch (Throwable) {}
            $pdo->prepare("DELETE FROM entity_metadata WHERE entity_id = ? AND meta_key = 'same_as'")->execute([$drop]);
            $pdo->prepare('UPDATE entities SET is_suppressed = ?, updated_at = NOW() WHERE id = ?')->execute([!empty($mv['was_hidden']) ? 1 : 0, $drop]);
            $pdo->prepare('UPDATE identity_merges SET undone_at = UTC_TIMESTAMP(), undone_by = ? WHERE id = ?')->execute([$uid ?: null, $mergeId]);
            $pdo->commit();
        } catch (Throwable $x) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $x; }
        self::pair($pdo, $keep, $drop, 'different', 'Undone: not the same business', $uid, 'person');
        try { Identity::recompute($pdo, 'listing', $keep); } catch (Throwable) {}
        self::reindex($pdo, $keep, $drop);
        return ['keep' => $keep, 'drop' => $drop];
    }

    private static function reindex(PDO $pdo, int ...$ids): void
    {
        if (is_file(__DIR__ . '/SearchIndex.php')) { require_once __DIR__ . '/SearchIndex.php'; try { if (SearchIndex::ready($pdo)) SearchIndex::indexListings($pdo, $ids); } catch (Throwable) {} }
    }

    // ---- un-hiding (§3.1: every Show, Undo, Put back and "Help us find" answer) ---------------------------------------

    /**
     * Before a hidden listing is shown again: the live twin it would duplicate, or null. A machine's un-hide (a "Help us
     * find" answer, a settle) merges into an exact unclaimed twin instead (returns ['merged' => keepId]); a person's
     * un-hide goes ahead and the pair becomes a step of the identity queue (returns ['queued' => otherId]).
     */
    public static function beforeShow(PDO $pdo, int $id, bool $person, int $uid = 0): ?array
    {
        if (!self::ready($pdo)) return null;
        $l = self::load($pdo, [$id])[$id] ?? null;
        if (!$l) return null;
        $c = self::cand(['name' => $l['name'], 'address' => $l['address'], 'zip' => $l['zip'], 'phone' => $l['phone'], 'website' => $l['website'], 'lat' => $l['lat'], 'lng' => $l['lng']]);
        $r = self::resolve($pdo, $c, ['self' => $id]);
        $twin = null; foreach ($r['hits'] as $h) if (!$h['hidden'] && in_array($h['kind'], ['same', 'covered'], true)) { $twin = $h; break; }
        if (!$twin) return null;
        if (!$person && $twin['exact'] && !$l['claimed'] && !$l['person'] && !$twin['claimed'] && !$twin['person']) {
            try { self::merge($pdo, $twin['id'], $id, 'Shown again as a twin of #' . $twin['id'] . ' (' . $twin['why'] . ')', $uid); return ['merged' => $twin['id']]; } catch (Throwable $x) { error_log('identity beforeShow: ' . $x->getMessage()); }
        }
        require_once __DIR__ . '/EngineCase.php';
        EngineCase::raise($pdo, 'identity', 'pair:' . min($id, $twin['id']) . ':' . max($id, $twin['id']), 'Shown again beside "' . $twin['name'] . '" (#' . $twin['id'] . '): ' . $twin['why'], ['a' => $id, 'b' => $twin['id']]);
        return $person ? ['queued' => $twin['id']] : ['hold' => $twin['id']];
    }

    // ---- the recheck (W2 task `recheck`, §6.1) ------------------------------------------------------------------------

    /** The recheck's task row on the switches (W2), added off; a site task (the plan never hands it to the worker). */
    public static function ensureTask(PDO $pdo, string $task = self::TASK, string $wf = self::WF, string $mode = 'site', string $label = 'Identity recheck',
                                      string $what = 'Live listings checked against each other: exact twins merged, variations vetted, the rest to the identity queue (decisions/0164 §6.1).', int $perRun = 0, int $every = 1440): void
    {
        try {
            $ex = $pdo->prepare('SELECT COUNT(*) FROM crawl_capture_tasks WHERE task = ?'); $ex->execute([$task]);
            if ((int) $ex->fetchColumn()) return;
            $cols = [];
            foreach ($pdo->query('SHOW COLUMNS FROM crawl_capture_tasks')->fetchAll(PDO::FETCH_ASSOC) as $c) $cols[$c['Field']] = $c;
            $want = ['task' => $task, 'wf' => $wf, 'mode' => $mode, 'enabled' => 0, 'every_minutes' => $every, 'per_run' => $perRun ?: (int) self::SETTINGS['slice'], 'max_seconds' => $mode === 'site' ? 0 : 600, 'max_fetches' => 0, 'sort' => 90,
                     'label' => $label, 'name' => $label, 'what' => $what,
                     'off_why' => 'Added switched off (Release B): a person switches it on', 'off_at' => gmdate('Y-m-d H:i:s')];
            $row = [];
            foreach ($cols as $f => $c) {
                if (array_key_exists($f, $want)) { $row[$f] = $want[$f]; continue; }
                if ($c['Null'] === 'NO' && $c['Default'] === null && !str_contains((string) $c['Extra'], 'auto_increment')) $row[$f] = preg_match('/int|dec|float|double/i', (string) $c['Type']) ? 0 : (preg_match('/date|time/i', (string) $c['Type']) ? gmdate('Y-m-d H:i:s') : '');
            }
            $f = array_keys($row);
            $pdo->prepare('INSERT IGNORE INTO crawl_capture_tasks (`' . implode('`, `', $f) . '`) VALUES (' . implode(', ', array_fill(0, count($f), '?')) . ')')->execute(array_values($row));
        } catch (Throwable $x) { error_log('identity task ' . $task . ': ' . $x->getMessage()); }
    }

    /** Whether the recheck may run now: the master, W2 and its task on (0163 §9). Null when it may, else why not. */
    public static function off(PDO $pdo): ?string
    {
        require_once __DIR__ . '/Automation.php';
        if (($w = Automation::masterOff($pdo)) !== null) return $w;
        if (($w = Automation::wfOff($pdo, self::WF)) !== null) return $w;
        try { $st = $pdo->prepare('SELECT enabled FROM crawl_capture_tasks WHERE task = ?'); $st->execute([self::TASK]); $on = $st->fetchColumn(); if ($on === false || !(int) $on) return 'The identity recheck (W2) is switched off.'; } catch (Throwable $x) { return 'The task list isn\'t there yet.'; }
        try { require_once __DIR__ . '/Automation.php'; if (($w = Automation::sysAny($pdo, ['master:all', 'wf:' . self::WF, 'task:' . self::TASK])) !== null) return $w; }
        catch (Throwable) { return 'The task list isn\'t there yet.'; }
        return null;
    }

    /**
     * One pass. $ids: the listings a load touched (checked at once); else the daily slice, oldest checked first.
     * Returns ['checked', 'merged', 'vetted', 'queued', 'held' (town guard), 'why'].
     */
    public static function recheckTick(PDO $pdo, ?array $ids = null, bool $force = false): array
    {
        $out = ['checked' => 0, 'merged' => 0, 'vetted' => 0, 'queued' => 0, 'held' => 0, 'why' => ''];
        if (!self::ready($pdo)) { $out['why'] = 'Its database update hasn\'t run yet.'; return $out; }
        self::ensureTask($pdo);
        if (!$force && ($w = self::off($pdo)) !== null) { $out['why'] = $w; return $out; }
        @set_time_limit(600);
        if ($ids === null) {
            // first: pairs sent to it (the live-pairs pass, Data cleanup's duplicate rule) and Review's open "same business"
            // offers from the crawler (Pulse case #23's 60), so each is rechecked before anything else (0163 §8 stage 8)
            $first = [];
            foreach ($pdo->query("SELECT a_id, b_id FROM identity_pairs WHERE verdict = 'recheck' ORDER BY at LIMIT 500")->fetchAll(PDO::FETCH_ASSOC) as $p) { $first[] = (int) $p['a_id']; $first[] = (int) $p['b_id']; }
            try { foreach ($pdo->query("SELECT entity_id, same_as_id FROM suggestions WHERE kind = 'same' AND status = 'new' AND device_hash IN ('crawler-cleanup', 'crawler-dupes') ORDER BY id LIMIT 500")->fetchAll(PDO::FETCH_ASSOC) as $p) { $first[] = (int) $p['entity_id']; $first[] = (int) $p['same_as_id']; } } catch (Throwable) {}
            $n = (int) self::setting($pdo, 'slice');
            $ids = array_merge($first, $pdo->query("SELECT id FROM entities WHERE entity_type = 'merchant' AND is_suppressed = 0 ORDER BY ident_checked_at IS NOT NULL, ident_checked_at, id LIMIT " . max(1, min(20000, $n)))->fetchAll(PDO::FETCH_COLUMN));
            $ids = array_values(array_unique(array_map('intval', $ids)));
        }
        $cap = (int) self::setting($pdo, 'run_merges'); $byTown = []; $runId = (int) (microtime(true) * 10) % 2000000000;
        $L = self::load($pdo, $ids);
        require_once __DIR__ . '/EngineCase.php';
        foreach ($L as $id => $l) {
            if ($l['hidden']) continue;
            $out['checked']++;
            self::writeKeys($pdo, $id);
            $c = self::cand(['name' => $l['name'], 'address' => $l['address'], 'zip' => $l['zip'], 'phone' => $l['phone'], 'website' => $l['website'], 'lat' => $l['lat'], 'lng' => $l['lng']]);
            $r = self::resolve($pdo, $c, ['self' => $id]);
            foreach ($r['hits'] as $h) {
                if ($h['hidden'] || $h['id'] === $id) continue;
                $v = self::verdict($pdo, $id, $h['id']);
                if ($v === 'different' || $v === 'same' || $v === 'queue') continue;
                if ($v === 'recheck') $v = null;
                $both = !$l['claimed'] && !$h['claimed'] && !$l['person'] && !$h['person'];
                if ($h['exact'] && $both) {
                    if ($out['merged'] >= $cap) { $out['why'] = 'Stopped at ' . $cap . ' merges this run; the rest next run.'; break 2; }
                    $town = $l['zip'] ?: '-';
                    $byTown[$town] = ($byTown[$town] ?? 0) + 1;
                    if ($byTown[$town] > max(2, self::townShare($pdo, $town))) {   // like the sync's mass-delete guard: a person's OK first
                        EngineCase::raise($pdo, 'identity_mass', 'town:' . $town, 'More than ' . (int) self::setting($pdo, 'town_pct') . '% of ZIP ' . $town . '\'s listings would merge in one run: OK it to carry on', ['zip' => $town]);
                        $out['held']++; continue;
                    }
                    [$keep, $drop] = self::keeper($pdo, $id, $h['id']);
                    try { self::merge($pdo, $keep, $drop, 'Exact twins (' . $h['why'] . ')', 0, 'recheck', false, $runId); $out['merged']++; } catch (Throwable $x) { error_log('recheck merge: ' . $x->getMessage()); }
                    if ($drop === $id) continue 2;
                    continue;
                }
                $key = 'pair:' . min($id, $h['id']) . ':' . max($id, $h['id']);
                if (!$both) {   // claimed or a person's: the identity queue, with the reason
                    EngineCase::raise($pdo, 'identity', $key, ($l['claimed'] || $h['claimed'] ? 'A claimed listing' : 'A person\'s entry') . ' may be listed twice: "' . $l['name'] . '" (#' . $id . ') and "' . $h['name'] . '" (#' . $h['id'] . '): ' . $h['why'], ['a' => $id, 'b' => $h['id']]);
                    self::pair($pdo, $id, $h['id'], 'queue', $h['why']); $out['queued']++; continue;
                }
                if ($v === 'vet' && self::vetDone($pdo, $id, $h['id'])) {   // vetted, still unsettled: the queue
                    EngineCase::raise($pdo, 'identity', $key, 'Vetted, still unsettled: "' . $l['name'] . '" (#' . $id . ') and "' . $h['name'] . '" (#' . $h['id'] . '): ' . $h['why'], ['a' => $id, 'b' => $h['id']]);
                    self::pair($pdo, $id, $h['id'], 'queue', $h['why']); $out['queued']++; continue;
                }
                if ($v === null) { self::vet($pdo, [$id, $h['id']], self::inDoubt($l, self::load($pdo, [$h['id']])[$h['id']] ?? $l)); self::pair($pdo, $id, $h['id'], 'vet', $h['why']); $out['vetted']++; }
            }
            try { $pdo->prepare('UPDATE entities SET ident_checked_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]); } catch (Throwable) {}
        }
        self::settleOffers($pdo);
        if (!$out['merged'] && !$out['held']) self::uniqueKey($pdo);
        try { require_once __DIR__ . '/SearchIndex.php'; SearchIndex::setState($pdo, 'identity:last_run', json_encode($out + ['at' => gmdate('c')])); } catch (Throwable) {}
        return $out;
    }

    /**
     * Review's crawler "same business" offers, once the recheck has looked at their pair: merged ones are marked used,
     * pairs moved to the identity queue or found different are withdrawn with the reason, so nothing waits twice.
     */
    public static function settleOffers(PDO $pdo): int
    {
        $n = 0;
        try {
            $st = $pdo->query("SELECT id, entity_id, same_as_id FROM suggestions WHERE kind = 'same' AND status = 'new' AND device_hash IN ('crawler-cleanup', 'crawler-dupes') LIMIT 500");
            $up = $pdo->prepare("UPDATE suggestions SET status = ?, handled_at = NOW(), note = CONCAT(COALESCE(note, ''), ?) WHERE id = ? AND status = 'new'");
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                // a merge moves the rows that point at the hidden one, this offer's too: both ids then name the keep
                $v = (int) $r['entity_id'] === (int) $r['same_as_id'] ? 'same' : self::verdict($pdo, (int) $r['entity_id'], (int) $r['same_as_id']);
                $to = ['same' => ['used', ' · The identity recheck merged them.'], 'queue' => ['dismissed', ' · Moved to the identity queue (The engine needs a person).'], 'different' => ['dismissed', ' · Two businesses: recorded on the pair.']][$v] ?? null;
                if ($to) { $up->execute([$to[0], $to[1], (int) $r['id']]); $n++; }
            }
        } catch (Throwable $x) { error_log('identity offers: ' . $x->getMessage()); }
        return $n;
    }

    /** The town guard's number: the share of a ZIP's listings one run may merge. */
    private static function townShare(PDO $pdo, string $zip): int
    {
        static $n = [];
        if (!isset($n[$zip])) { $st = $pdo->prepare("SELECT COUNT(*) FROM entities WHERE entity_type = 'merchant' AND is_suppressed = 0 AND primary_zip = ?"); $st->execute([$zip]); $n[$zip] = (int) floor((int) $st->fetchColumn() * self::setting($pdo, 'town_pct') / 100); }
        return $n[$zip];
    }

    /** The details two listings disagree on (what "Vet this place" checks). */
    public static function inDoubt(array $a, array $b): array
    {
        $d = [];
        if (self::nameKey($a['name']) !== self::nameKey($b['name'])) $d[] = 'name';
        if (self::addrKey($a['address']) !== self::addrKey($b['address'])) $d[] = 'address';
        if (self::phoneKey($a['phone']) !== self::phoneKey($b['phone'])) $d[] = 'phone';
        if (Identity::host($a['website'] ?: 'x.invalid') !== Identity::host($b['website'] ?: 'x.invalid')) $d[] = 'website';
        return $d ?: ['name', 'address', 'phone'];
    }

    /**
     * "Vet this place": W2's `identity` task, the one entry point (§6.1). Queues each listing with the details in doubt
     * (IdentityJobs' look, first in its queue); what it can't settle waits on the retry ladder; the recheck reads its
     * answer on its next pass.
     */
    public static function vet(PDO $pdo, array $ids, array $details): void
    {
        require_once __DIR__ . '/IdentityJobs.php';
        if (!IdentityJobs::ready($pdo)) return;
        foreach ($ids as $id) {
            try {
                IdentityJobs::look($pdo, (int) $id);
                $pdo->prepare('UPDATE ' . Database::t('identity_jobs') . " SET need = ?, note = ? WHERE record_kind = 'listing' AND record_id = ?")
                    ->execute([mb_substr(implode(',', array_unique(array_merge(['phone', 'website'], $details))), 0, 100), mb_substr('Vet this place: ' . implode(', ', $details) . ' in doubt (identity recheck)', 0, 255), (int) $id]);
            } catch (Throwable $x) { error_log('identity vet #' . $id . ': ' . $x->getMessage()); }
        }
    }

    /** Whether both listings' looks have finished since the pair was sent to be vetted. */
    private static function vetDone(PDO $pdo, int $a, int $b): bool
    {
        try {
            [$x, $y] = $a < $b ? [$a, $b] : [$b, $a];
            $p = $pdo->prepare('SELECT at FROM identity_pairs WHERE a_id = ? AND b_id = ?'); $p->execute([$x, $y]); $at = (string) $p->fetchColumn();
            $st = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::t('identity_jobs') . " WHERE record_kind = 'listing' AND record_id IN (?, ?) AND status IN ('done', 'failed') AND updated_at >= ?");
            $st->execute([$a, $b, $at]);
            return (int) $st->fetchColumn() >= 2;
        } catch (Throwable) { return false; }
    }

    /** The unique key on ZIP + phone + name, added once no exact twins are left (0164 §3.1's database guard). */
    public static function uniqueKey(PDO $pdo): bool
    {
        try {
            $has = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entities' AND INDEX_NAME = 'uq_ident'")->fetchColumn();
            if ($has) return true;
            $left = (int) $pdo->query("SELECT COUNT(*) FROM (SELECT primary_zip, ident_phone, ident_name FROM entities WHERE is_suppressed = 0 AND ident_phone IS NOT NULL AND ident_name IS NOT NULL GROUP BY 1, 2, 3 HAVING COUNT(*) > 1) t")->fetchColumn();
            $unkeyed = (int) $pdo->query("SELECT COUNT(*) FROM entities WHERE entity_type = 'merchant' AND ident_at IS NULL")->fetchColumn();
            if ($left || $unkeyed) return false;
            // hidden rows carry no value in it (NULL), so a hidden twin never blocks; writeKeys() keeps it as listings change
            $pdo->exec("UPDATE entities SET ident_live = IF(is_suppressed = 0 AND ident_phone IS NOT NULL AND ident_name IS NOT NULL, LEFT(CONCAT(COALESCE(primary_zip, ''), ':', ident_phone, ':', LEFT(ident_name, 80)), 100), NULL)");
            $pdo->exec('ALTER TABLE entities ADD UNIQUE KEY uq_ident (ident_live)');
            return true;
        } catch (Throwable $x) { error_log('identity unique key: ' . $x->getMessage()); return false; }
    }

    /** The recheck's line on the board and in Data cleanup: its last run. */
    public static function lastRun(PDO $pdo): ?array
    {
        try { require_once __DIR__ . '/SearchIndex.php'; $v = json_decode(SearchIndex::state($pdo, 'identity:last_run'), true); return is_array($v) ? $v : null; } catch (Throwable) { return null; }
    }

    /** The merges in the ledger, newest first (Data cleanup's identity panel, with Undo). */
    public static function merges(PDO $pdo, int $n = 50): array
    {
        if (!self::ready($pdo)) return [];
        return $pdo->query('SELECT m.id, m.keep_id, m.drop_id, m.why, m.by_kind, m.at, m.undone_at, k.name AS keep_name, d.name AS drop_name FROM identity_merges m LEFT JOIN entities k ON k.id = m.keep_id LEFT JOIN entities d ON d.id = m.drop_id ORDER BY m.id DESC LIMIT ' . max(1, min(500, $n)))->fetchAll(PDO::FETCH_ASSOC);
    }
}
