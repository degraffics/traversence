<?php
// ANCHOR: replaces     private static function solutions(PDO $pdo, array $n, ?array $place, array $o, bool $urgent): array through         return [array_slice($items, 0, $per), $incomplete, $missed];
// (the method's closing brace after that line stays as it is)
// What changed:
// - the need's own order ahead of weight (decisions/0061's health-order note; audit C7): the category pattern a listing
//   was found by, in the need's order, then a listing whose words carry the need's words ("urgent"), then weight, in SQL
//   (so LIMIT 400 never cuts the first kind) and, in the place, ahead of distance in the final sort.
// - an interim guard for audit C5: a listing named in NPI's "Last, First" form with no business word is a person, not
//   a place, and isn't offered for a need. The root fix is AutoImport/IntakeStager/DataCleanup (not in this release).
    private static function solutions(PDO $pdo, array $n, ?array $place, array $o, bool $urgent): array
    {
        $kinds = array_values(array_intersect(array_map('trim', explode(',', (string) $n['kinds'])), ['listing', 'outdoors', 'story', 'group', 'experience', 'topic']));
        if (!$kinds) $kinds = ['listing'];
        $p = [];
        $or = [];
        $cats = self::catIds($pdo, (string) $n['cats']);
        // the need's own order: the first category it names leads ("Urgent Care%" before "Clinics"), never the listing number
        $pos = [];
        foreach (explode('|', (string) $n['cats']) as $i => $pat) {
            $pat = trim($pat); if ($pat === '') continue;
            foreach (self::catIds($pdo, $pat) as $cid) if (!isset($pos[$cid])) $pos[$cid] = $i;
        }
        // a need that names categories takes businesses from them only ("key" isn't Key Cannabis Dispensary); its words
        // find outdoor places, stories, groups and experiences, and businesses only when it names no category
        if ($cats) $or[] = "(si.kind = 'listing' AND si.category_id IN (" . implode(',', array_map('intval', $cats)) . '))';
        $wor = []; $nw = [];
        foreach (array_slice(array_values(array_filter(explode(' ', (string) $n['words']))), 0, 8) as $i => $w) {
            $wn = trim(SearchIndex::norm($w));
            if ($wn === '') continue;
            $wor[] = "si.terms LIKE :nw$i";
            $p[":nw$i"] = '% ' . $wn . ' %';
            $nw[$i] = $wn;
        }
        if ($wor) $or[] = ($cats ? "si.kind <> 'listing' AND " : '') . '(' . implode(' OR ', $wor) . ')';
        if (!$or) return [[], 0];
        $kin = implode(',', array_map(fn ($k) => "'" . $k . "'", $kinds));
        $here = $place ? SearchIndex::whereSql($pdo, $place, null, $p) : '';
        $center = $o['center'] ?? null; $reach = (string) ($o['reach'] ?? 'all');
        $box = '';
        if ($center && $reach !== 'all' && $reach !== 'place') {
            $d = max(1, (int) $reach) / 69.0;
            $box = ' AND si.lat BETWEEN :b1 AND :b2 AND si.lon BETWEEN :b3 AND :b4';
            $p += [':b1' => $center[0] - $d, ':b2' => $center[0] + $d, ':b3' => $center[1] - $d * 1.3, ':b4' => $center[1] + $d * 1.3];
        }
        // the need's order and its words ahead of weight, so the 400 kept are the right ones
        $posSql = '';
        if (count(array_unique($pos)) > 1) {
            $by = [];
            foreach ($pos as $cid => $i) $by[$i][] = (int) $cid;
            ksort($by);
            $posSql = 'CASE';
            foreach ($by as $i => $ids) $posSql .= ' WHEN si.category_id IN (' . implode(',', $ids) . ') THEN ' . (int) $i;
            $posSql .= ' ELSE 99 END, ';
        }
        $hitSql = '';
        if ($nw) {
            $hs = [];
            foreach ($nw as $i => $wn) { $hs[] = "si.terms LIKE :nh$i"; $p[":nh$i"] = '% ' . $wn . ' %'; }   // own names: a name can't be bound twice
            $hitSql = '(' . implode(' OR ', $hs) . ') DESC, ';
        }
        $sql = "SELECT si.kind, si.item_id, si.label, si.sub, si.url, si.lat, si.lon, si.hours, si.private_location, si.city, si.state, si.category_id, si.terms"
             . ($here !== '' ? ", COALESCE($here, 0) AS here" : ', 0 AS here')
             . " FROM search_index si WHERE si.kind IN ($kin) AND (" . implode(' OR ', $or) . ")$box"
             . ' ORDER BY here DESC, ' . $posSql . $hitSql . 'si.weight DESC LIMIT 400';
        try { $st = $pdo->prepare($sql); $st->execute($p); $rows = $st->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { error_log('situations need: ' . $e->getMessage()); return [[], 0]; }
        // a person isn't a place (audit C5, interim): NPI's "Last, First" form with no business word in it
        $person = static fn (string $s): bool => (bool) preg_match('/^\p{Lu}[\p{L}\'\-]+,\s*\p{Lu}[\p{L}.]*(\s+\p{Lu}[\p{L}.]*){0,3}$/u', trim($s))
            && !preg_match('/&|\b(inc|llc|co|company|clinic|center|centre|pharmacy|hospital|health|care|services?|group|associates|and|medical)\b/i', $s);
        // phones for the listings (from their details)
        $phones = [];
        $lids = array_map(fn ($r) => (int) $r['item_id'], array_filter($rows, fn ($r) => $r['kind'] === 'listing'));
        if ($lids) {
            foreach (array_chunk($lids, 500) as $chunk) {
                foreach ($pdo->query("SELECT entity_id, meta_value FROM entity_metadata WHERE meta_key = 'phone' AND entity_id IN (" . implode(',', $chunk) . ')')->fetchAll(PDO::FETCH_ASSOC) as $m) {
                    $v = json_decode((string) $m['meta_value'], true); $v = is_string($v) ? $v : (string) $m['meta_value'];
                    if (trim($v) !== '') $phones[(int) $m['entity_id']] = trim($v);
                }
            }
        }
        [$dow, $min] = $o['at'] ?? [(int) date('w'), (int) date('G') * 60 + (int) date('i')];
        $req = array_filter(explode(',', (string) $n['requires']));
        $items = []; $incomplete = 0; $seenL = []; $missed = [];
        foreach ($rows as $r) {
            if ($r['kind'] === 'listing' && $person((string) $r['label'])) continue;
            if ($reach === 'place' && $place && (int) $r['here'] !== 1) continue;   // the Distance slider at "My place only"
            // a place outside our areas ("Seattle, WA"): only what's in it or within about 150 miles, never Arizona's movers
            if (!empty($place['outside']) && (int) $r['here'] !== 1 && (!$center || $r['lat'] === null || (int) $r['private_location']
                || SearchIndex::miles($center[0], $center[1], (float) $r['lat'], (float) $r['lon']) > 150)) continue;
            $phone = $r['kind'] === 'listing' ? ($phones[(int) $r['item_id']] ?? '') : '';
            $hoursTxt = (string) ($r['hours'] ?? '');
            $always = (bool) preg_match('/24\s*(h|hours|\/7)|open 24/i', $hoursTxt);
            $open = null;
            if ($always) $open = true;
            elseif ($hoursTxt !== '' && class_exists('Hours')) { try { $open = Hours::openAt(Hours::parse($hoursTxt), (int) $dow, (int) $min); } catch (Throwable) {} }
            $hasWhere = !(int) $r['private_location'] && ($r['lat'] !== null || trim((string) $r['city']) !== '');
            // what can't complete the task isn't a solution (decisions/0061, principle 5)
            $missing = (in_array('phone', $req, true) && $phone === '') || (in_array('hours', $req, true) && $hoursTxt === '') || (in_array('where', $req, true) && !$hasWhere);
            if ($missing) {
                $incomplete++;
                if (!empty($o['collect']) && $r['kind'] === 'listing') $missed[] = ['id' => (int) $r['item_id'], 'label' => (string) $r['label'], 'sub' => (string) $r['sub'],
                    'missing' => array_values(array_filter([in_array('phone', $req, true) && $phone === '' ? 'phone' : '', in_array('hours', $req, true) && $hoursTxt === '' ? 'hours' : '', in_array('where', $req, true) && !$hasWhere ? 'place' : '']))];
                continue;
            }
            if ($urgent && $open === false) continue;   // known to be closed now: not a solution to an urgent need
            $miles = ($center && $r['lat'] !== null && !(int) $r['private_location']) ? SearchIndex::miles($center[0], $center[1], (float) $r['lat'], (float) $r['lon']) : null;
            $dk = mb_strtolower((string) $r['label']) . '|' . mb_strtolower(trim(explode('·', (string) $r['sub'])[0])) . '|' . mb_strtolower(trim((string) $r['city']));
            if (isset($seenL[$dk])) continue;   // the same business listed twice: once
            $seenL[$dk] = 1;
            $terms = ' ' . trim((string) ($r['terms'] ?? '')) . ' ';
            $hit = false;
            foreach ($nw as $wn) if (str_contains($terms, ' ' . $wn . ' ')) { $hit = true; break; }
            $items[] = ['kind' => (string) $r['kind'], 'id' => (string) $r['item_id'], 'label' => (string) $r['label'], 'sub' => (string) $r['sub'], 'url' => (string) $r['url'],
                        'phone' => $phone, 'open' => $open, 'always' => $always, 'here' => (int) $r['here'] === 1, 'miles' => $miles !== null ? round($miles) : null,
                        'peek' => $r['kind'] === 'listing',
                        '_pos' => $r['category_id'] !== null && isset($pos[(int) $r['category_id']]) ? $pos[(int) $r['category_id']] : 99, '_hit' => $hit];
        }
        // in the place first; urgent: open now first; in the place, the need's own order (its first kind, then its words)
        // before distance; beyond the place, nearest first, then the need's order
        usort($items, fn ($a, $b) => [!$a['here'], $urgent ? $a['open'] !== true : 0, $a['here'] ? $a['_pos'] : 0, $a['here'] ? !$a['_hit'] : 0, $a['miles'] ?? 99999, $a['_pos'], !$a['_hit']]
                                 <=> [!$b['here'], $urgent ? $b['open'] !== true : 0, $b['here'] ? $b['_pos'] : 0, $b['here'] ? !$b['_hit'] : 0, $b['miles'] ?? 99999, $b['_pos'], !$b['_hit']]);
        $items = array_map(function ($x) { unset($x['_pos'], $x['_hit']); return $x; }, $items);
        $per = (int) ($o['per'] ?? 5);
        return [array_slice($items, 0, $per), $incomplete, $missed];
