<?php

declare(strict_types=1);

require_once __DIR__ . '/PlaceScope.php';
require_once __DIR__ . '/Marketing.php';

/**
 * api/lib/Links.php — connection points: the Link button (decisions/0047).
 *
 * A person links a thing (a business, a place, later a person, topic, guide, marketplace item or event);
 * the feeds are built from what they've linked. Linking a business also opens two-way in-app messages
 * with it; unlinking ends them (decisions/0047 §3, api/lib/Marketing.php). Target ids:
 *   business  entities.id                       place  "hub:1" | "geo_hub:3" | "cluster:12"
 * The label and url are resolved on the server and kept as a snapshot, so a person's list never needs a
 * join to show, and a client can't label a link with made-up text.
 */
final class Links
{
    /** Kinds that can be linked today; the rest of decisions/0047's list arrives with its feature. */
    public const TYPES = ['person' => 'People you follow', 'business' => 'Businesses', 'place' => 'Places'];
    private const PLACE_LEVELS = ['hub' => ['hubs', 'hub'], 'geo_hub' => ['geo_hubs', 'geo'], 'cluster' => ['micro_clusters', 'cluster']];

    public static function tableExists(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT 1 FROM user_links LIMIT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    /** [label, url] for a target, or InvalidArgumentException when it doesn't exist or isn't public. */
    public static function resolve(PDO $pdo, string $type, string $id, int $viewerId = 0): array
    {
        if ($type === 'person') {
            // Following: public activity only, never location (decisions/0047 §2). Not yourself, not across a
            // block, and not a private profile (people send those a connection request instead).
            require_once __DIR__ . '/People.php';
            $u = ctype_digit($id) ? People::user($pdo, (int) $id) : null;
            if ($u === null || (int) $id === $viewerId) throw new InvalidArgumentException('That person can\'t be followed.');
            if ($viewerId && People::blocked($pdo, $viewerId, (int) $id)) throw new InvalidArgumentException('That person can\'t be followed.');
            if (People::settings($pdo, (int) $id)['profile_private'] && !($viewerId && People::connected($pdo, $viewerId, (int) $id))) {
                throw new InvalidArgumentException('This profile is private. Send a connection request instead.');
            }
            return [People::name($u), '/user/profile.php?id=' . (int) $id];
        }
        if ($type === 'business') {
            if (!ctype_digit($id)) throw new InvalidArgumentException('Unknown listing.');
            $in = implode(',', array_fill(0, count(PlaceScope::PUBLIC_STATUSES), '?'));
            $st = $pdo->prepare("SELECT name FROM entities WHERE id = ? AND is_suppressed = 0 AND status IN ($in)");
            $st->execute(array_merge([(int) $id], PlaceScope::PUBLIC_STATUSES));
            $name = $st->fetchColumn();
            if ($name === false) throw new InvalidArgumentException('That listing isn\'t available.');
            return [(string) $name, '/listing/view.php?id=' . (int) $id];
        }
        if ($type === 'place' && preg_match('/^rec:([A-Za-z0-9_-]{1,40})$/', $id, $rm)) {   // a public recreation place (place/recreation.php)
            require_once __DIR__ . '/crawler/RecBulk.php';
            $rec = RecBulk::get($pdo, $rm[1]);
            if (!$rec) throw new InvalidArgumentException('That place isn\'t available.');
            return [RecBulk::displayName((string) $rec['name']), '/place/recreation.php?id=' . rawurlencode($rm[1])];
        }
        if ($type === 'place' && preg_match('/^lm:(\d{1,10})$/', $id, $lm)) {   // a natural landmark (place/landmark.php, decisions/0058 §24)
            require_once __DIR__ . '/Landmarks.php';
            $l = Landmarks::get($pdo, (int) $lm[1]);
            if (!$l) throw new InvalidArgumentException('That place isn\'t available.');
            return [(string) $l['name'], '/place/landmark.php?id=' . (int) $lm[1]];
        }
        if ($type === 'place' && preg_match('/^guide:(\d{1,10})$/', $id, $gm)) {   // a resource guide (guide/resources.php, decisions/0061)
            $st = $pdo->prepare("SELECT title FROM resource_guides WHERE id = ? AND status = 'live'");
            $st->execute([(int) $gm[1]]);
            $t = $st->fetchColumn();
            if ($t === false) throw new InvalidArgumentException('That guide isn\'t available.');
            return [(string) $t, '/guide/resources.php?id=' . (int) $gm[1]];
        }
        if ($type === 'place' && preg_match('/^pg:(\d{1,10})$/', $id, $pm)) {   // a Personal Guide (guide/personal.php, decisions/0174 §1)
            require_once __DIR__ . '/Trips.php';
            $g = Trips::guide($pdo, (int) $pm[1]);
            if (!$g) throw new InvalidArgumentException('That guide isn\'t available.');
            return [(string) $g['title'], '/guide/personal.php?id=' . (int) $pm[1]];
        }
        if ($type === 'place' && preg_match('/^jr:(\d{1,10})$/', $id, $jm)) {   // a published journey (journeys/view.php; Jason, 2026-11-20: "Fix it here")
            $st = $pdo->prepare("SELECT title FROM journeys WHERE id = ? AND status = 'published'");
            $st->execute([(int) $jm[1]]);
            $t = $st->fetchColumn();
            if ($t === false) throw new InvalidArgumentException('That journey isn\'t available.');
            return [(string) $t, '/journeys/view.php?id=' . (int) $jm[1]];
        }
        if ($type === 'place' && preg_match('/^how:(\d{1,10})$/', $id, $hm)) {   // a how-to guide (guide/how.php, decisions/0063 §8)
            $st = $pdo->prepare("SELECT title FROM how_guides WHERE id = ? AND status = 'published'");
            $st->execute([(int) $hm[1]]);
            $t = $st->fetchColumn();
            if ($t === false) throw new InvalidArgumentException('That guide isn\'t available.');
            return [(string) $t, '/guide/how.php?id=' . (int) $hm[1]];
        }
        if ($type === 'place') {
            if (preg_match('/^(town|sub):\d+$/', $id)) {   // a city or neighborhood guide (decisions/0131)
                require_once __DIR__ . '/Guides.php';
                $gp = Guides::place($pdo, $id) ?: throw new InvalidArgumentException('That place isn\'t available.');
                return [$gp['name'], $gp['url']];
            }
            if (!preg_match('/^(hub|geo_hub|cluster):(\d+)$/', $id, $m)) throw new InvalidArgumentException('Unknown place.');
            [$table, $param] = self::PLACE_LEVELS[$m[1]];
            $st = $pdo->prepare("SELECT name, slug FROM $table WHERE id = ?");
            $st->execute([(int) $m[2]]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new InvalidArgumentException('That place isn\'t available.');
            return [PlaceScope::publicName((string) $row['name']), '/hub/?' . $param . '=' . rawurlencode((string) $row['slug'])];
        }
        throw new InvalidArgumentException('That can\'t be linked yet.');
    }

    /**
     * Open links (decisions/0052): who has linked this person or business, as the other side sees it. A link shows
     * once it was made under the open terms (after the linker consented to Linking) or its owner has seen the
     * one-time notice; links of people who paused Linking don't show.
     * @return list<array{user_id:int, name:string, since:string}>
     */
    public static function linkedBy(PDO $pdo, string $type, string $id, int $limit = 100): array
    {
        try {
            $st = $pdo->prepare("SELECT l.user_id, l.created_at, u.display_name FROM user_links l JOIN users u ON u.id = l.user_id
                                   LEFT JOIN tool_consents c ON c.user_id = l.user_id AND c.tool = 'linking'
                                   LEFT JOIN user_settings s ON s.user_id = l.user_id
                                  WHERE l.target_type = ? AND l.target_id = ? AND COALESCE(u.is_suspended, 0) = 0
                                    AND COALESCE(c.status, '') <> 'inactive'
                                    AND (s.open_links_ack IS NOT NULL OR (c.status = 'active' AND c.activated_at <= l.created_at))
                                  ORDER BY l.created_at DESC LIMIT " . max(1, min(500, $limit)));
            $st->execute([$type, $id]);
        } catch (PDOException) { return []; }   // tables not there yet: nothing to show
        return array_map(fn ($r) => ['user_id' => (int) $r['user_id'], 'since' => (string) $r['created_at'],
            'name' => trim((string) $r['display_name']) !== '' ? (string) $r['display_name'] : 'Member #' . (int) $r['user_id']], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** A person removes someone who linked them (and they can block them in the Address Book). */
    public static function removeFollower(PDO $pdo, int $uid, int $follower): void
    {
        $pdo->prepare("DELETE FROM user_links WHERE user_id = ? AND target_type = 'person' AND target_id = ?")->execute([$follower, (string) $uid]);
    }

    /** Has this person links from before links were open, and not yet seen the notice? */
    public static function needsOpenNotice(PDO $pdo, int $uid): bool
    {
        try {
            $st = $pdo->prepare('SELECT open_links_ack FROM user_settings WHERE user_id = ?');
            $st->execute([$uid]);
            if ($st->fetchColumn()) return false;
            $st = $pdo->prepare("SELECT activated_at FROM tool_consents WHERE user_id = ? AND tool = 'linking' AND status = 'active'");
            $st->execute([$uid]);
            $since = $st->fetchColumn();
            $q = $pdo->prepare('SELECT 1 FROM user_links WHERE user_id = ? AND target_type IN (\'person\', \'business\')' . ($since ? ' AND created_at < ?' : '') . ' LIMIT 1');
            $q->execute($since ? [$uid, $since] : [$uid]);
            return $q->fetchColumn() !== false;
        } catch (PDOException) { return false; }
    }

    public static function ackOpenNotice(PDO $pdo, int $uid): void
    {
        $st = $pdo->prepare('SELECT 1 FROM user_settings WHERE user_id = ?');
        $st->execute([$uid]);
        if ($st->fetchColumn() === false) $pdo->prepare('INSERT INTO user_settings (user_id) VALUES (?)')->execute([$uid]);
        $pdo->prepare('UPDATE user_settings SET open_links_ack = ? WHERE user_id = ?')->execute([date('Y-m-d H:i:s'), $uid]);
    }

    /** Links or unlinks. Returns the new state. */
    public static function set(PDO $pdo, int $userId, string $type, string $id, bool $linked): bool
    {
        if (!$linked) {
            $pdo->prepare('DELETE FROM user_links WHERE user_id = ? AND target_type = ? AND target_id = ?')->execute([$userId, $type, $id]);
            if ($type === 'business') Marketing::onUnlink($pdo, $userId, (int) $id);   // messages from it stop
            return false;
        }
        [$label, $url] = self::resolve($pdo, $type, $id, $userId);
        $st = $pdo->prepare('SELECT id FROM user_links WHERE user_id = ? AND target_type = ? AND target_id = ?');
        $st->execute([$userId, $type, $id]);
        if ($st->fetchColumn() === false) {
            $pdo->prepare('INSERT INTO user_links (user_id, target_type, target_id, label, url) VALUES (?, ?, ?, ?, ?)')
                ->execute([$userId, $type, $id, mb_substr($label, 0, 255), $url]);
            if ($type === 'business') Marketing::onLink($pdo, $userId, (int) $id);     // two-way in-app messages open
        }
        return true;
    }

    public static function mute(PDO $pdo, int $userId, string $type, string $id, bool $muted): void
    {
        $pdo->prepare('UPDATE user_links SET muted = ? WHERE user_id = ? AND target_type = ? AND target_id = ?')
            ->execute([$muted ? 1 : 0, $userId, $type, $id]);
    }

    /** Which of these "type:id" keys the person has linked: [key => bool]. */
    public static function states(PDO $pdo, int $userId, array $keys): array
    {
        $out = [];
        foreach (array_slice($keys, 0, 200) as $k) $out[(string) $k] = false;
        if (!$out) return [];
        $st = $pdo->prepare('SELECT target_type, target_id FROM user_links WHERE user_id = ?');
        $st->execute([$userId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $k = $r['target_type'] . ':' . $r['target_id'];
            if (isset($out[$k])) $out[$k] = true;
        }
        return $out;
    }

    /** Everything the person has linked, newest first, grouped by kind. */
    public static function all(PDO $pdo, int $userId): array
    {
        $st = $pdo->prepare('SELECT target_type, target_id, label, url, muted, created_at FROM user_links WHERE user_id = ? ORDER BY created_at DESC');
        $st->execute([$userId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['target_type']][] = $r;
        return $out;
    }

    /** How many people have linked a target (Business Insights shows counts, never names). */
    public static function count(PDO $pdo, string $type, string $id): int
    {
        $st = $pdo->prepare('SELECT COUNT(*) FROM user_links WHERE target_type = ? AND target_id = ?');
        $st->execute([$type, $id]);
        return (int) $st->fetchColumn();
    }

    /**
     * A feed built from what the person linked. Muted links are left out.
     *   listings   posts from linked businesses, and new listings in linked places
     *   discovery  the linked places, with what's new in each
     *   community  new connections and contact cards shared with you (People::communityFeed)
     *   marketplace  arrives with the Marketplace
     * @return list<array{kind:string,title:string,body:string,url:string,when:string,from:string}>
     */
    public static function feed(PDO $pdo, int $userId, string $tab, int $limit = 40): array
    {
        // Linking switched off in Your tools (decisions/0049): links are kept but paused, so no feed from them.
        if (is_file(__DIR__ . '/Consent.php')) {
            require_once __DIR__ . '/Consent.php';
            if (Consent::paused($pdo, $userId, 'linking')) return [];
        }
        $links = self::all($pdo, $userId);
        $active = fn (string $t) => array_values(array_filter($links[$t] ?? [], fn ($l) => !(int) $l['muted']));
        $clusters = self::placeClusters($pdo, $active('place'));
        $items = [];
        $pub = implode(',', array_map(fn ($s) => $pdo->quote($s), PlaceScope::PUBLIC_STATUSES));

        if ($tab === 'listings') {
            $biz = array_map(fn ($l) => (int) $l['target_id'], $active('business'));
            if ($biz) {
                $in = implode(',', $biz);
                try {
                    foreach ($pdo->query("SELECT p.body, p.published_at, e.id, e.name FROM posts p JOIN entities e ON e.id = p.entity_id
                                           WHERE p.entity_id IN ($in) AND p.status = 'published' AND p.is_deleted = 0
                                           ORDER BY p.published_at DESC LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                        $items[] = ['kind' => 'post', 'title' => (string) $r['name'], 'body' => mb_strimwidth((string) $r['body'], 0, 280, '…'),
                                    'url' => '/listing/view.php?id=' . (int) $r['id'], 'when' => (string) $r['published_at'], 'from' => (string) $r['name']];
                    }
                } catch (PDOException) { /* posts not in use yet */ }
            }
            if ($clusters) {
                $in = implode(',', array_keys($clusters));
                foreach ($pdo->query("SELECT id, name, micro_cluster_id, created_at FROM entities
                                       WHERE micro_cluster_id IN ($in) AND is_suppressed = 0 AND status IN ($pub)
                                         AND created_at >= NOW() - INTERVAL 60 DAY
                                       ORDER BY created_at DESC LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $items[] = ['kind' => 'new_listing', 'title' => (string) $r['name'], 'body' => 'New in ' . $clusters[(int) $r['micro_cluster_id']],
                                'url' => '/listing/view.php?id=' . (int) $r['id'], 'when' => (string) $r['created_at'], 'from' => $clusters[(int) $r['micro_cluster_id']]];
                }
            }
        } elseif ($tab === 'community') {
            require_once __DIR__ . '/People.php';
            if (People::tableExists($pdo)) {
                foreach (People::communityFeed($pdo, $userId) as $i) $items[] = $i + ['from' => ''];
            }
            if (is_file(__DIR__ . '/Community.php')) {
                require_once __DIR__ . '/Community.php';
                if (Community::ready($pdo)) foreach (Community::feed($pdo, $userId) as $i) $items[] = $i;   // posts in groups they belong to
            }
        } elseif ($tab === 'discovery') {
            foreach ($active('place') as $l) {
                $ids = array_keys(self::placeClusters($pdo, [$l]));
                $new = 0;
                if ($ids) {
                    $new = (int) $pdo->query('SELECT COUNT(*) FROM entities WHERE micro_cluster_id IN (' . implode(',', $ids) . ")
                                               AND is_suppressed = 0 AND status IN ($pub) AND created_at >= NOW() - INTERVAL 30 DAY")->fetchColumn();
                }
                $items[] = ['kind' => 'place', 'title' => (string) $l['label'], 'body' => $new ? "$new new listing" . ($new === 1 ? '' : 's') . ' this month' : (str_starts_with((string) $l['target_id'], 'jr:') ? 'A journey you linked' : 'Its guide, places and local picks'),
                            'url' => (string) $l['url'], 'when' => (string) $l['created_at'], 'from' => 'Linked place'];
            }
        }
        // the pieces that name what you follow (decisions/0132 §4): a guide, a journey, a story… that mentions it
        if (is_file(__DIR__ . '/ContentTags.php')) {
            require_once __DIR__ . '/ContentTags.php';
            try { foreach (ContentTags::feedFor($pdo, $links, $tab, $limit, $userId) as $i) $items[] = $i; } catch (Throwable $e) { error_log('feed tags: ' . $e->getMessage()); }
        }
        usort($items, fn ($a, $b) => strcmp($b['when'], $a['when']));
        return array_slice($items, 0, $limit);
    }

    /** Cluster ids covered by linked places: [cluster_id => place label]. */
    private static function placeClusters(PDO $pdo, array $placeLinks): array
    {
        $out = [];
        foreach ($placeLinks as $l) {
            if (preg_match('/^(town|sub):\d+$/', (string) $l['target_id'])) {   // a town or neighborhood: its cluster
                require_once __DIR__ . '/Guides.php';
                $gp = Guides::place($pdo, (string) $l['target_id']); if ($gp && $gp['cluster']) $out[(int) $gp['cluster']] ??= (string) $l['label'];
                continue;
            }
            if (!preg_match('/^(hub|geo_hub|cluster):(\d+)$/', (string) $l['target_id'], $m)) continue;
            $id = (int) $m[2];
            $sql = match ($m[1]) {
                'cluster' => "SELECT id FROM micro_clusters WHERE id = $id",
                'geo_hub' => "SELECT id FROM micro_clusters WHERE geo_hub_id = $id",
                'hub' => "SELECT mc.id FROM micro_clusters mc JOIN geo_hubs g ON g.id = mc.geo_hub_id WHERE g.hub_id = $id",
            };
            foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $cid) $out[(int) $cid] ??= (string) $l['label'];
        }
        return $out;
    }
}
