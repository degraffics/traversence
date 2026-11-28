<?php
// app/lib/HelpFind.php — "Help us find this business" (decisions/0164 §3.5; Jason, 2026-11-14: "b is a great public
// request"). A business we know only by its name and town is not a listing: it's a public request, shown on its town's
// page and in search, never as a listing (no pin, no Call or Directions, no confidence badge), asking visitors for its
// phone or address, and its owner to claim it.
//
// What a card is: a crawler-made listing taken off the site by Data cleanup with the target help_find (its 'removed' note,
// app/lib/DataCleanup.php). Answers come in as a person's suggestion with its source (Suggestions, kind fix: R4), which
// our team accepts in Review; the owner's claim works on it too. Once it has a phone or a street address it passes the
// "real business" floor and becomes a listing again (promote()). Its name keeps to the rules for names: page text, a
// person's name (R6), a protected or confidential name (R7) is never shown. It retires, kept, after the retry ladder's
// long wait (180 days, decisions/0163 §12) with no answer; any answer brings it back.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class HelpFind
{
    /** The retry ladder's long wait (decisions/0163 §12): a card nobody answers retires after it, kept. */
    public const RETIRE_DAYS = 180;
    /** On a town page and in search: a few, newest answers last. */
    public const ON_PAGE = 8;

    /** SQL for a card: hidden by Data cleanup with the help_find target. */
    private const CARD = "e.entity_type = 'merchant' AND e.is_suppressed = 1 AND e.status = 'unclaimed'
        AND EXISTS (SELECT 1 FROM entity_metadata hf WHERE hf.entity_id = e.id AND hf.meta_key = 'removed' AND hf.meta_value LIKE '%\"target\":\"help_find\"%')";

    /** Whether a listing is a "Help us find" card (Claims::listing lets its owner claim it). */
    public static function isCard(PDO $pdo, int $id): bool
    {
        try { $st = $pdo->prepare('SELECT 1 FROM entities e WHERE e.id = ? AND ' . self::CARD); $st->execute([$id]); return (bool) $st->fetchColumn(); } catch (Throwable $x) { return false; }
    }

    /**
     * Cards in a place: $where is the place's listing scope (Guides::place()['sql'], on alias e). Each:
     * ['id', 'name', 'what', 'town', 'state']. Promotes any that have been found first.
     */
    public static function inPlace(PDO $pdo, string $where, int $limit = self::ON_PAGE): array
    {
        self::promoteSome($pdo);
        $st = $pdo->query(self::select() . ' AND (' . $where . ') ORDER BY e.id DESC LIMIT ' . (int) ($limit * 3));
        return self::shown($pdo, $st->fetchAll(PDO::FETCH_ASSOC), $limit);
    }

    /** Cards near a point whose name or kind has the words (search): each with 'miles'. */
    public static function near(PDO $pdo, string $text, float $lat, float $lon, float $miles = 25, int $limit = 5): array
    {
        $pad = $miles / 60; $padLon = $miles / max(10, 69 * cos(deg2rad($lat)));
        $a = [$lat - $pad, $lat + $pad, $lon - $padLon, $lon + $padLon];
        $words = array_values(array_filter(preg_split('/[^\p{L}\p{N}\']+/u', mb_strtolower(trim($text))) ?: [], fn ($w) => mb_strlen($w) >= 3 && !in_array($w, ['near', 'the', 'and', 'for', 'in', 'me'], true)));
        $match = '';
        if ($words) {
            $or = [];
            foreach (array_slice($words, 0, 4) as $w) { $or[] = '(e.name LIKE ? OR c.name LIKE ?)'; $a[] = '%' . $w . '%'; $a[] = '%' . $w . '%'; }
            $match = ' AND (' . implode(' OR ', $or) . ')';
        }
        $st = $pdo->prepare(self::select() . ' AND COALESCE(e.latitude, z.latitude) BETWEEN ? AND ? AND COALESCE(e.longitude, z.longitude) BETWEEN ? AND ?' . $match . ' ORDER BY e.id DESC LIMIT ' . (int) ($limit * 4));
        $st->execute($a);
        $out = [];
        foreach (self::shown($pdo, $st->fetchAll(PDO::FETCH_ASSOC), $limit * 2) as $r) {
            $d = self::miles($lat, $lon, (float) $r['lat'], (float) $r['lon']);
            if ($d <= $miles) $out[] = $r + ['miles' => round($d, 1)];
        }
        usort($out, fn ($x, $y) => $x['miles'] <=> $y['miles']);
        return array_slice($out, 0, $limit);
    }

    private static function select(): string
    {
        return "SELECT e.id, e.name, e.category_id, c.name AS what, JSON_UNQUOTE(mc.meta_value) AS town, JSON_UNQUOTE(ms.meta_value) AS state,
                       COALESCE(e.latitude, z.latitude) AS lat, COALESCE(e.longitude, z.longitude) AS lon, JSON_UNQUOTE(JSON_EXTRACT(hf.meta_value, '$.at')) AS since
                  FROM entities e
                  JOIN entity_metadata hf ON hf.entity_id = e.id AND hf.meta_key = 'removed'
             LEFT JOIN categories c ON c.id = e.category_id
             LEFT JOIN entity_metadata mc ON mc.entity_id = e.id AND mc.meta_key = 'city'
             LEFT JOIN entity_metadata ms ON ms.entity_id = e.id AND ms.meta_key = 'state'
             LEFT JOIN zip_coordinates z ON z.zip = LEFT(e.primary_zip, 5)
                 WHERE " . self::CARD;
    }

    /** The cards whose name may be shown, not retired: [id, name, what, town, state, lat, lon]. */
    private static function shown(PDO $pdo, array $rows, int $limit): array
    {
        require_once __DIR__ . '/DataCleanup.php';
        require_once __DIR__ . '/Landmarks.php';
        require_once __DIR__ . '/ListingSafety.php';
        require_once __DIR__ . '/crawler/AutoImport.php';
        $answered = self::answered($pdo, array_map(fn ($r) => (int) $r['id'], $rows));
        $out = [];
        foreach ($rows as $r) {
            $name = trim((string) $r['name']);
            if ($name === '' || DataCleanup::junkName($name)[0]) continue;                          // page text: never a request
            if (AutoImport::looksPerson($name)) continue;                                            // a person's name (R6)
            if (Landmarks::isProtected($name)) continue;                                             // a protected place's name (R7)
            if (ListingSafety::isConfidential($r['category_id'] !== null ? (int) $r['category_id'] : null, $name)) continue;
            $since = max(strtotime((string) $r['since']) ?: 0, $answered[(int) $r['id']] ?? 0);
            if ($since && $since < time() - self::RETIRE_DAYS * 86400) continue;                     // retired: the long wait passed with no answer
            $out[] = ['id' => (int) $r['id'], 'name' => $name, 'what' => (string) preg_replace('/\s*\([^)]*\)$/', '', (string) $r['what']),
                      'town' => (string) $r['town'], 'state' => (string) $r['state'], 'lat' => $r['lat'] !== null ? (float) $r['lat'] : null, 'lon' => $r['lon'] !== null ? (float) $r['lon'] : null];
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /** When each card was last answered (a visitor's suggestion about it): id => time. */
    private static function answered(PDO $pdo, array $ids): array
    {
        if (!$ids) return [];
        try {
            $st = $pdo->query('SELECT entity_id, MAX(created_at) FROM suggestions WHERE entity_id IN (' . implode(',', array_map('intval', $ids)) . ') GROUP BY entity_id');
            return array_map(fn ($t) => (int) strtotime((string) $t), $st->fetchAll(PDO::FETCH_KEY_PAIR));
        } catch (Throwable $x) { return []; }
    }

    private static function miles(float $a, float $b, float $c, float $d): float
    {
        $r = 3958.8; $p = M_PI / 180;
        $h = sin(($c - $a) * $p / 2) ** 2 + cos($a * $p) * cos($c * $p) * sin(($d - $b) * $p / 2) ** 2;
        return 2 * $r * asin(min(1, sqrt($h)));
    }

    // ---- found: it becomes a listing ------------------------------------------------------------------------------------

    /**
     * Cards that now have a phone or a street address (a fix accepted in Review, the owner's own entry, our team's) pass
     * the "real business" floor: back on the site as listings, a note kept of how they were found. Returns how many.
     */
    public static function promoteSome(PDO $pdo, int $limit = 50): int
    {
        // the pause (2026-11-28; the IT Manager's Tier 4): while it holds, a card comes back only on a phone or address a person
        // entered (Review, the owner, our team), never on one the system filled by itself
        require_once __DIR__ . '/crawler/AutoImport.php';
        $byPerson = fn (string $m) => AutoImport::pausedWhy() !== null ? " AND ($m.is_locked = 1 OR $m.source_type IN ('admin', 'owner', 'user'))" : '';
        try {
            $st = $pdo->query("SELECT e.id FROM entities e
                                 LEFT JOIN entity_metadata p ON p.entity_id = e.id AND p.meta_key = 'phone'
                                 LEFT JOIN entity_metadata a ON a.entity_id = e.id AND a.meta_key = 'address'
                                WHERE e.entity_type = 'merchant' AND e.is_suppressed = 1
                                  AND EXISTS (SELECT 1 FROM entity_metadata hf WHERE hf.entity_id = e.id AND hf.meta_key = 'removed' AND hf.meta_value LIKE '%\"target\":\"help_find\"%')
                                  AND ((LENGTH(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(p.meta_value, ''), '\"', ''), '-', ''), ' ', ''), '(', ''), ')', '')) >= 10" . $byPerson('p') . ")
                                       OR (COALESCE(a.meta_value, '') REGEXP '^\"?[[:space:]]*#?[0-9]'" . $byPerson('a') . "))
                                LIMIT " . (int) $limit);
            $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $x) { return 0; }
        foreach ($ids as $id) {
            try { self::promote($pdo, $id); } catch (Throwable $x) { error_log('HelpFind promote #' . $id . ': ' . $x->getMessage()); }
        }
        return count($ids);
    }

    private static function promote(PDO $pdo, int $id): void
    {
        $st = $pdo->prepare("SELECT meta_value FROM entity_metadata WHERE entity_id = ? AND meta_key = 'removed'"); $st->execute([$id]);
        $was = json_decode((string) $st->fetchColumn(), true) ?: [];
        $pdo->prepare("INSERT INTO entity_metadata (entity_id, meta_key, meta_value, source_type, is_locked, created_at, updated_at) VALUES (?, 'found', ?, 'admin', 1, NOW(), NOW())
                       ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), updated_at = NOW()")
            ->execute([$id, json_encode(['at' => date('Y-m-d'), 'how' => 'Help us find this business: a phone or address came in', 'was' => $was], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        // the one identity check before it's shown (decisions/0164 §3.1): an answer carrying a live listing's phone or address
        // folds this one into it (an exact unclaimed twin), or waits as a step of the identity queue; never a second listing
        require_once __DIR__ . '/ListingIdentity.php';
        ListingIdentity::writeKeys($pdo, $id);
        $twin = ListingIdentity::beforeShow($pdo, $id, false);
        if ($twin !== null) return;
        $pdo->prepare("DELETE FROM entity_metadata WHERE entity_id = ? AND meta_key = 'removed'")->execute([$id]);   // its "taken off" note, as the editor's Put back does
        $pdo->prepare("UPDATE entities SET is_suppressed = 0, updated_at = NOW() WHERE id = ?")->execute([$id]);
        try { require_once __DIR__ . '/SearchIndex.php'; SearchIndex::indexListings($pdo, [$id]); } catch (Throwable $x) {}
        try { require_once __DIR__ . '/ListingConfidence.php'; ListingConfidence::refresh($pdo, $id); } catch (Throwable $x) {}
    }

    /** For Data cleanup's page: [shown on the site, retired, found and listed again]. */
    public static function counts(PDO $pdo): array
    {
        try {
            $rows = $pdo->query(self::select() . ' ORDER BY e.id DESC LIMIT 2000')->fetchAll(PDO::FETCH_ASSOC);
            $shown = count(self::shown($pdo, $rows, 2000));
            $found = (int) $pdo->query("SELECT COUNT(*) FROM entity_metadata WHERE meta_key = 'found' AND meta_value LIKE '%Help us find%'")->fetchColumn();
            return [$shown, count($rows) - $shown, $found];
        } catch (Throwable $x) { return [0, 0, 0]; }
    }

    // ---- the card --------------------------------------------------------------------------------------------------------

    /** The town page's block: a short heading, the cards, and what to do. '' when there are none. $sg: the page's "Tell us" context. */
    public static function block(PDO $pdo, array $place, array $sg): string
    {
        $cards = self::inPlace($pdo, (string) $place['sql']);
        if (!$cards) return '';
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $h = '<h3 class="gd-h3">Help us find these businesses</h3><p class="gd-hf-lead">We know their names in ' . $e($place['name']) . ', but not yet how to reach them. Know a phone number or an address? Tell us, and we\'ll check it and list them.</p><div class="gd-hf">';
        foreach ($cards as $c) $h .= self::card($c, $sg);
        return $h . '</div>' . self::style();
    }

    /** One card: the name (not a link: it isn't a listing), its kind and town, and the two ways to help. */
    public static function card(array $c, array $sg = []): string
    {
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $ask = json_encode(['kind' => 'fix', 'entity_id' => $c['id'], 'entity_name' => $c['name'], 'help_find' => 1, 'place_key' => (string) ($sg['place_key'] ?? ''), 'place_name' => (string) ($sg['place_name'] ?? '')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $icon = fn (string $n) => function_exists('tv_icon') ? tv_icon($n, 'gd-hf-i') : '';
        return '<div class="gd-hf-c tv-bleed"><div class="gd-hf-t"><b>' . $e($c['name']) . '</b><span class="gd-hf-tag">Help us find it</span></div>'
            . '<small>' . $e(implode(' · ', array_filter([$c['what'], trim($c['town'] . ($c['state'] !== '' ? ', ' . $c['state'] : ''), ', ')]))) . '</small>'
            . '<div class="gd-hf-a"><button type="button" class="gd-hf-b" data-tv-suggest="' . $e($ask) . '">' . $icon('phone') . 'Know its phone or address?</button>'
            . '<a class="gd-hf-b" href="/claim.php?listing=' . (int) $c['id'] . '">' . $icon('store') . 'Is it yours? Claim it</a></div></div>';
    }

    private static function style(): string
    {
        static $done = false;
        if ($done) return '';
        $done = true;
        return '<style>.gd-hf-lead{margin:.2rem 0 .5rem}.gd-hf{display:grid;gap:.5rem;grid-template-columns:repeat(auto-fill,minmax(16rem,1fr))}.gd-hf-c{background:#FFFBF2;border:1px dashed #D9C7A8;border-radius:.5rem;padding:.6rem .7rem}'
            . '.gd-hf-t{display:flex;justify-content:space-between;gap:.5rem;align-items:baseline}.gd-hf-t b{color:#3E2B1F}.gd-hf-tag{font-size:.72rem;background:#F5EBDD;color:#7C2D12;border-radius:999px;padding:.05rem .5rem;white-space:nowrap}'
            . '.gd-hf-c small{display:block;color:#6B5B45;margin:.15rem 0 .4rem}.gd-hf-a{display:flex;flex-wrap:wrap;gap:.4rem}.gd-hf-b{display:inline-flex;align-items:center;gap:.3rem;font:inherit;font-size:.85rem;background:#fff;border:1px solid #B45309;color:#92400E;border-radius:999px;padding:.2rem .65rem;cursor:pointer;text-decoration:none}'
            . '.gd-hf-i{width:15px;height:15px;color:#B45309}</style>';
    }
}
