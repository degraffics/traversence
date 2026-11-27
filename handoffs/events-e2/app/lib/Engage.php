<?php
// api/lib/Engage.php — the standard engagement on every content page (decisions/0058 §20): views (the platform's
// counts, never who), likes (how many; never who), public comments, Share and Report. For listings, outdoor places,
// experiences and place pages; journeys keep their own (Journeys::comments). A comment needs a signed-in member who
// is 18 or older; it can be removed by its writer or by staff, and reported (Admin → Reports). One level of replies
// (decisions/0172 §2.3): a reply tells the comment's writer; a listing's Owner and Managers can hide comments on it.
declare(strict_types=1);

require_once __DIR__ . '/ListingSafety.php';

final class Engage
{
    public const KINDS = ['listing', 'rec', 'exp', 'place', 'lm', 'guide', 'how', 'sj', 'page', 'pg', 'event'];   // event: a business's event (decisions/0134 §5)   // pg: a Personal Guide (decisions/0174 §1)   // page: one of our own pages (SitePages), through its blocks (decisions/0159 step 4d)   // sj: a suggested journey (decisions/0146); 'mkt' (a Marketplace post) has views, Share and Report only; 'group' (a community group, decisions/0137 §4) views, Share and QR
    /** the platform's view-count key for each (decisions/0058 §13) */
    public const COUNT_KEY = ['listing' => 'listing:', 'rec' => 'rec:', 'exp' => 'experience:', 'place' => 'place:', 'lm' => 'landmark:', 'guide' => 'guide:', 'how' => 'how:', 'sj' => 'suggested:', 'pg' => 'personal_guide:', 'mkt' => 'market:', 'group' => 'group:', 'page' => 'page:', 'event' => 'event:'];

    public static function ready(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try { $pdo->query('SELECT 1 FROM content_comments LIMIT 1'); $pdo->query('SELECT 1 FROM content_likes LIMIT 1'); return $ok = true; } catch (PDOException) { return $ok = false; }
    }

    private static function now(): string { return date('Y-m-d H:i:s'); }

    /** A valid target: [kind, ref], or an exception. */
    public static function target(string $kind, string $ref): array
    {
        $ok = match ($kind) {
            'listing', 'exp', 'lm', 'guide', 'how', 'sj', 'page', 'pg', 'photo', 'event' => ctype_digit($ref),   // photo: a gallery item, only through Gallery (decisions/0126), which checks who may see it
            'mp' => ctype_digit($ref),   // a member's post, only through MemberPosts (decisions/0153), which checks who may see it
            'rec' => (bool) preg_match('/^[A-Za-z0-9_-]{1,40}$/', $ref),
            'place' => (bool) preg_match('/^(hub|geo_hub|cluster|town|sub):\d{1,9}$/', $ref),   // town, sub: city and neighborhood guides (decisions/0131)
            default => false,
        };
        if (!$ok) throw new InvalidArgumentException('That can\'t be liked or commented on.');
        return [$kind, $ref];
    }

    /** How many visits saw it (all time), from the platform's counts. */
    public static function views(PDO $pdo, string $kind, string $ref): int
    {
        try {
            require_once __DIR__ . '/PlatformCounts.php';
            return (int) PlatformCounts::total($pdo, 'view', 3650, self::COUNT_KEY[$kind] . $ref)['visits'];
        } catch (Throwable) { return 0; }
    }

    public static function likes(PDO $pdo, string $kind, string $ref): int
    {
        if (!self::ready($pdo)) return 0;
        require_once __DIR__ . '/TestAccounts.php';   // a test account's like isn't counted (decisions/0145 §3)
        $st = $pdo->prepare('SELECT COUNT(*) FROM content_likes l' . (TestAccounts::ready($pdo) ? ' WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.id = l.user_id AND u.is_test = 1) AND' : ' WHERE') . ' l.kind = ? AND l.ref = ?');
        $st->execute([$kind, $ref]);
        return (int) $st->fetchColumn() + self::guestLikes($pdo, $kind, $ref);
    }

    public static function liked(PDO $pdo, string $kind, string $ref, ?int $uid): bool
    {
        if (!$uid || !self::ready($pdo)) return false;
        $st = $pdo->prepare('SELECT 1 FROM content_likes WHERE kind = ? AND ref = ? AND user_id = ?');
        $st->execute([$kind, $ref, $uid]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Like or unlike, signed in. Returns [liked, likes]. A like passes the checks first (LikeGuard: speed, timing, 100 a
     * day), which can throw RuntimeException 428 (the human check) or 429 (today's likes used). Gallery photos are liked
     * inside a gallery its owner shares, so they skip them.
     */
    /**
     * Is there something to like (site audit P11, 2026-11-13: likes were taken for ids nothing has)? Each kind through its
     * own library, so a landmark that isn't shown can't be liked either. True when it can't be told (a library or table
     * missing): a like is never refused for our own gap. Kinds checked elsewhere (photo, mp) or by shape only (rec,
     * place) pass.
     */
    public static function exists(PDO $pdo, string $kind, string $ref): bool
    {
        try {
            switch ($kind) {
                case 'lm': require_once __DIR__ . '/Landmarks.php'; return !Landmarks::ready($pdo) || Landmarks::get($pdo, (int) $ref) !== null;
                case 'pg': require_once __DIR__ . '/Trips.php'; return !Trips::guidesReady($pdo) || Trips::guide($pdo, (int) $ref) !== null;   // published, its trip not deleted
                case 'sj': require_once __DIR__ . '/JourneyPlan.php'; return !JourneyPlan::ready($pdo) || JourneyPlan::get($pdo, (int) $ref) !== null;
                case 'guide': require_once __DIR__ . '/ResourceGuides.php'; return !ResourceGuides::ready($pdo) || ResourceGuides::guide($pdo, (int) $ref) !== null;
                case 'exp': $sql = "SELECT 1 FROM experiences WHERE id = ? AND status = 'published'"; break;
                case 'how': $sql = 'SELECT 1 FROM how_guides WHERE id = ?'; break;
                case 'listing': $sql = 'SELECT 1 FROM entities WHERE id = ? AND is_suppressed = 0'; break;
                case 'event': require_once __DIR__ . '/Events.php'; return Events::publicGet($pdo, (int) $ref) !== null;   // published or cancelled, its host up
                case 'page': $sql = "SELECT 1 FROM site_pages WHERE id = ? AND owner_id = 0 AND status = 'live'"; break;   // ours, and live
                default: return true;
            }
            $st = $pdo->prepare($sql); $st->execute([(int) $ref]);
            return (bool) $st->fetchColumn();
        } catch (Throwable) { return true; }
    }

    public static function toggleLike(PDO $pdo, int $uid, string $kind, string $ref): array
    {
        self::target($kind, $ref);
        $guard = $kind !== 'photo';
        if ($guard) require_once __DIR__ . '/LikeGuard.php';
        if (self::liked($pdo, $kind, $ref, $uid)) {
            $pdo->prepare('DELETE FROM content_likes WHERE kind = ? AND ref = ? AND user_id = ?')->execute([$kind, $ref, $uid]);
            if ($guard) LikeGuard::unrecord($pdo, $uid, $kind, $ref);
        } else {
            if (!self::exists($pdo, $kind, $ref)) throw new InvalidArgumentException('That isn\'t here any more, so it can\'t be liked.');
            if ($guard) LikeGuard::check($pdo, $uid);
            $pdo->prepare('INSERT INTO content_likes (kind, ref, user_id, created_at) VALUES (?, ?, ?, ?)')->execute([$kind, $ref, $uid, self::now()]);
            if ($guard) LikeGuard::record($pdo, $uid, $kind, $ref);
        }
        return [self::liked($pdo, $kind, $ref, $uid), self::likes($pdo, $kind, $ref)];
    }

    /** Guests' likes (decisions/0009; 0058 §20 as changed 2026-11-05): added straight to the item's count. */
    public static function guestLikes(PDO $pdo, string $kind, string $ref): int
    {
        try { $st = $pdo->prepare('SELECT guest_likes FROM content_like_tally WHERE kind = ? AND ref = ?'); $st->execute([$kind, $ref]); return (int) $st->fetchColumn(); }
        catch (PDOException) { return 0; }   // before the migration runs
    }

    /**
     * A guest likes or unlikes (Jason, 2026-11-05). Once per item per visit; up to Guest::PER_ITEM_CONNECTION guest likes
     * on one item from one connection a day (a family or an office on one connection can each like it). After
     * Guest::LIKES_PER_VISIT likes the account panel asks them to join, with Keep going as a guest (a RuntimeException 401;
     * its message ends "|more" when they may keep going), up to Guest::MAX_PER_VISIT. The checks (LikeGuard) watch every
     * guest closely: they can throw 428, the human check. Returns [liked, likes].
     */
    public static function toggleGuestLike(PDO $pdo, string $kind, string $ref): array
    {
        require_once __DIR__ . '/Guest.php';
        require_once __DIR__ . '/LikeGuard.php';
        self::target($kind, $ref);
        if (Guest::isBot()) throw new InvalidArgumentException('That can\'t be liked from here.');
        $k = $kind . ':' . $ref;
        if (!empty(Guest::liked()[$k])) {   // unliking this visit's own like
            $pdo->prepare('UPDATE content_like_tally SET guest_likes = GREATEST(guest_likes, 1) - 1 WHERE kind = ? AND ref = ?')->execute([$kind, $ref]);
            LikeGuard::unrecord($pdo, null, $kind, $ref);
            Guest::setLiked($k, false);
            return [false, self::likes($pdo, $kind, $ref)];
        }
        $n = count(Guest::liked());
        if ($n >= Guest::MAX_PER_VISIT) throw new RuntimeException('That\'s ' . Guest::MAX_PER_VISIT . ' likes this visit. Create a free account to keep liking: it takes a minute.', 401);
        if ($n >= Guest::LIKES_PER_VISIT && !Guest::more()) throw new RuntimeException('You\'re liking a lot of places. Create a free account to keep liking (it takes a minute): likes from an account count wherever you are.|more', 401);
        if (LikeGuard::ready($pdo)) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM like_events WHERE kind = ? AND ref = ? AND ip_key = ? AND src LIKE 'g:%' AND voided_at IS NULL AND created_at >= ?");
            $st->execute([$kind, $ref, LikeGuard::ipKey(), date('Y-m-d 00:00:00')]);
            if ((int) $st->fetchColumn() >= Guest::PER_ITEM_CONNECTION) throw new InvalidArgumentException('Guests on this connection have liked it today already. Create a free account to add yours.');
        }
        if (!self::exists($pdo, $kind, $ref)) throw new InvalidArgumentException('That isn\'t here any more, so it can\'t be liked.');
        LikeGuard::check($pdo, null);
        $pdo->prepare('INSERT INTO content_like_tally (kind, ref, guest_likes) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE guest_likes = guest_likes + 1')->execute([$kind, $ref]);
        LikeGuard::record($pdo, null, $kind, $ref);
        Guest::setLiked($k, true);
        return [true, self::likes($pdo, $kind, $ref)];
    }

    /** The connection check's records go after 30 days (decisions/0009); the likes stay in the counts. */
    public static function purgeGuestConnections(PDO $pdo): int
    {
        $d = $pdo->prepare('DELETE FROM guest_ip_likes WHERE created_at < ?'); $d->execute([date('Y-m-d H:i:s', time() - 30 * 86400)]);
        return $d->rowCount();
    }

    /** Whether replies are switched on (content_comments.parent_id, decisions/0172 §2.3). */
    public static function repliesReady(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok === null) { try { $pdo->query('SELECT parent_id FROM content_comments LIMIT 0'); $ok = true; } catch (Throwable) { $ok = false; } }
        return $ok;
    }

    /** Whether who hid a comment, and when, is kept (content_comments.hidden_by, hidden_at). */
    public static function hideReady(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok === null) { try { $pdo->query('SELECT hidden_by, hidden_at FROM content_comments LIMIT 0'); $ok = true; } catch (Throwable) { $ok = false; } }
        return $ok;
    }

    /**
     * The visible comments, oldest first: [id, user_id, body, created_at, parent_id, name, team]. One level of replies
     * (decisions/0172 §2.3): a reply carries its comment's id in parent_id, and a reply whose comment is hidden or gone
     * isn't listed. 'team': on a listing, the business's name when the writer is on its team (they answer as themselves,
     * named with the business).
     */
    public static function comments(PDO $pdo, string $kind, string $ref, int $limit = 200): array
    {
        if (!self::ready($pdo)) return [];
        require_once __DIR__ . '/People.php';
        $replies = self::repliesReady($pdo);
        $st = $pdo->prepare('SELECT c.id, c.user_id, c.body, c.created_at' . ($replies ? ', c.parent_id' : '') . " FROM content_comments c WHERE c.kind = ? AND c.ref = ? AND c.status = 'visible'"
            . ($replies ? " AND (c.parent_id IS NULL OR EXISTS (SELECT 1 FROM content_comments p WHERE p.id = c.parent_id AND p.status = 'visible'))" : '') . ' ORDER BY c.created_at, c.id LIMIT ' . max(1, $limit));
        $st->execute([$kind, $ref]);
        $biz = ''; $team = [];
        if ($kind === 'listing') { $s = $pdo->prepare('SELECT name FROM entities WHERE id = ?'); $s->execute([(int) $ref]); $biz = (string) $s->fetchColumn(); $team = self::teamIds($pdo, (int) $ref); }
        return array_map(function ($c) use ($pdo, $biz, $team) {
            $u = People::user($pdo, (int) $c['user_id']);
            $w = (int) $c['user_id'];
            return ['id' => (int) $c['id'], 'user_id' => $w, 'body' => (string) $c['body'], 'created_at' => (string) $c['created_at'], 'parent_id' => isset($c['parent_id']) ? (int) $c['parent_id'] : 0,
                    'name' => $u ? People::name($u) : 'Someone who left', 'team' => $biz !== '' && isset($team[$w]) ? $biz : ''];
        }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function commentCount(PDO $pdo, string $kind, string $ref): int
    {
        if (!self::ready($pdo)) return 0;
        $st = $pdo->prepare("SELECT COUNT(*) FROM content_comments c WHERE c.kind = ? AND c.ref = ? AND c.status = 'visible'"
            . (self::repliesReady($pdo) ? " AND (c.parent_id IS NULL OR EXISTS (SELECT 1 FROM content_comments p WHERE p.id = c.parent_id AND p.status = 'visible'))" : ''));
        $st->execute([$kind, $ref]);
        return (int) $st->fetchColumn();
    }

    /** One comment, any status: [id, kind, ref, user_id, body, status, created_at, parent_id, hidden_by, hidden_at] or null. */
    public static function getComment(PDO $pdo, int $id): ?array
    {
        if (!self::ready($pdo) || $id <= 0) return null;
        $st = $pdo->prepare('SELECT id, kind, ref, user_id, body, status, created_at' . (self::repliesReady($pdo) ? ', parent_id' : '') . (self::hideReady($pdo) ? ', hidden_by, hidden_at' : '') . ' FROM content_comments WHERE id = ?');
        $st->execute([$id]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) return null;
        return ['id' => (int) $c['id'], 'kind' => (string) $c['kind'], 'ref' => (string) $c['ref'], 'user_id' => (int) $c['user_id'], 'body' => (string) $c['body'], 'status' => (string) $c['status'],
                'created_at' => (string) $c['created_at'], 'parent_id' => (int) ($c['parent_id'] ?? 0), 'hidden_by' => (int) ($c['hidden_by'] ?? 0), 'hidden_at' => (string) ($c['hidden_at'] ?? '')];
    }

    /** The visible replies to a comment, oldest first. */
    public static function replies(PDO $pdo, int $parentId, int $limit = 20): array
    {
        if (!self::repliesReady($pdo)) return [];
        require_once __DIR__ . '/People.php';
        $st = $pdo->prepare("SELECT id, user_id, body, created_at FROM content_comments WHERE parent_id = ? AND status = 'visible' ORDER BY created_at, id LIMIT " . max(1, $limit));
        $st->execute([$parentId]);
        return array_map(function ($c) use ($pdo) { $u = People::user($pdo, (int) $c['user_id']); return $c + ['name' => $u ? People::name($u) : 'Someone who left']; }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Where a comment's page is (a path on the site), as the Reports page links it. */
    public static function pageUrl(PDO $pdo, string $kind, string $ref): string
    {
        try {
            if ($kind === 'place') { require_once __DIR__ . '/Links.php'; return (string) Links::resolve($pdo, 'place', $ref)[1]; }
            if ($kind === 'page') { require_once __DIR__ . '/SitePages.php'; $p = SitePages::get($pdo, (int) $ref); return $p ? SitePages::url($p) : '/'; }
        } catch (Throwable) { return '/'; }
        return (['listing' => '/listing/view.php?id=', 'rec' => '/place/recreation.php?id=', 'exp' => '/experiences/view.php?id=', 'lm' => '/place/landmark.php?id=',
                 'guide' => '/guide/resources.php?id=', 'how' => '/guide/how.php?id=', 'sj' => '/journeys/suggested.php?id=', 'pg' => '/guide/personal.php?id=', 'mp' => '/user/post.php?id='][$kind] ?? '/?') . rawurlencode($ref);
    }

    /**
     * Posts a comment, or ($parentId) a reply to one: one level, so a reply to a reply joins its comment. Who hears
     * (decisions/0172 §2.3): a reply → the comment's writer (comment.reply), never themselves; a comment on a listing → the
     * business's team (biz.comment), replies too; a comment on an experience → its writer (content.comment).
     */
    public static function comment(PDO $pdo, int $uid, string $kind, string $ref, string $body, int $parentId = 0): array
    {
        require_once __DIR__ . '/Standing.php'; Standing::guard($pdo, $uid);   // limited accounts can't post or send (decisions/0079)
        self::target($kind, $ref);
        require_once __DIR__ . '/AgeGate.php';
        AgeGate::require($pdo, $uid);
        $body = mb_substr(trim(strip_tags($body)), 0, 2000);
        if ($body === '') throw new InvalidArgumentException($parentId > 0 ? 'Write a reply first.' : 'Write a comment first.');
        if ($p = ListingSafety::contentProblem($body)) throw new InvalidArgumentException('That comment can\'t be posted: ' . $p . '.');
        $parent = null;
        if ($parentId > 0) {
            if (!self::repliesReady($pdo)) throw new InvalidArgumentException('Replies switch on once the site\'s update has run. Add a comment for now.');
            $parent = self::getComment($pdo, $parentId);
            if ($parent && $parent['parent_id'] > 0) $parent = self::getComment($pdo, $parent['parent_id']);   // one level: a reply to a reply joins its comment
            if (!$parent || $parent['status'] !== 'visible' || $parent['kind'] !== $kind || $parent['ref'] !== $ref) throw new InvalidArgumentException('That comment isn\'t here any more, so it can\'t be answered. Add a comment instead.');
        }
        $st = $pdo->prepare('SELECT COUNT(*) FROM content_comments WHERE user_id = ? AND created_at >= ?');
        $st->execute([$uid, date('Y-m-d H:i:s', time() - 3600)]);
        if ((int) $st->fetchColumn() >= 30) throw new InvalidArgumentException('That\'s a lot of comments for one hour. Try again later.');
        if ($parent) $pdo->prepare('INSERT INTO content_comments (kind, ref, user_id, body, created_at, parent_id) VALUES (?, ?, ?, ?, ?, ?)')->execute([$kind, $ref, $uid, $body, self::now(), $parent['id']]);
        else $pdo->prepare('INSERT INTO content_comments (kind, ref, user_id, body, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$kind, $ref, $uid, $body, self::now()]);
        $id = (int) $pdo->lastInsertId();   // read before any other query: MySQL's insert id is reset by the next statement
        require_once __DIR__ . '/People.php';
        $u = People::user($pdo, $uid);
        $name = $u ? People::name($u) : 'Someone';
        $url = self::pageUrl($pdo, $kind, $ref) . '#comments';
        $told = [];
        if ($parent && $parent['user_id'] !== $uid && !People::blocked($pdo, $uid, $parent['user_id'])) {   // a reply to my comment (comment.reply)
            try {
                require_once __DIR__ . '/Notify.php';
                Notify::send($pdo, 'comment.reply', ['users' => [$parent['user_id']]],
                    ['thing' => 'reply:' . $id, 'thread' => 'replies:' . $parent['id'], 'url' => $url, 'from' => ['user', $uid, $name]],
                    ['title' => $name . ' replied to your comment', 'body' => '"' . mb_strimwidth($body, 0, 240, '…') . '"']);
                $told[] = $parent['user_id'];
            } catch (Throwable $x) { error_log('comment reply notify: ' . $x->getMessage()); }
        }
        if ($kind === 'listing') {   // a comment on a listing is a notice to its business, in its Pulse (decisions/0172 §2.3, gap 3); email if they chose it. Replies too: the business still hears.
            try {
                require_once __DIR__ . '/Notify.php';
                $st = $pdo->prepare('SELECT name FROM entities WHERE id = ?'); $st->execute([(int) $ref]);
                $biz = (string) $st->fetchColumn();
                Notify::send($pdo, 'biz.comment', ['entity' => (int) $ref, 'except' => [$uid]],
                    ['thing' => 'comment:' . $id, 'thread' => 'comments:' . $ref, 'url' => '/listing/view.php?id=' . (int) $ref, 'from' => ['user', $uid, $name]],
                    ['title' => $name . ($parent ? ' replied in the comments on ' : ' commented on ') . ($biz !== '' ? $biz : 'your listing'), 'body' => '"' . mb_strimwidth($body, 0, 240, '…') . '"']);
            } catch (Throwable $x) { error_log('listing comment notify: ' . $x->getMessage()); }
        }
        if ($kind === 'exp') {   // a comment on something a person made: its maker (decisions/0172 §2.3); guides, guides' places and our pages have no maker to tell
            try {
                $st = $pdo->prepare('SELECT author_id, title FROM experiences WHERE id = ?'); $st->execute([(int) $ref]);
                $ex = $st->fetch(PDO::FETCH_ASSOC);
                $maker = $ex ? (int) $ex['author_id'] : 0;
                if ($maker > 0 && $maker !== $uid && !in_array($maker, $told, true) && !People::blocked($pdo, $uid, $maker)) {
                    require_once __DIR__ . '/Notify.php';
                    Notify::send($pdo, 'content.comment', ['users' => [$maker]],
                        ['thing' => 'comment:' . $id, 'thread' => 'comments:exp:' . $ref, 'url' => $url, 'from' => ['user', $uid, $name]],
                        ['title' => $name . ' commented on ' . ((string) $ex['title'] !== '' ? $ex['title'] : 'your experience'), 'body' => '"' . mb_strimwidth($body, 0, 240, '…') . '"']);
                }
            } catch (Throwable $x) { error_log('experience comment notify: ' . $x->getMessage()); }
        }
        return ['id' => $id, 'name' => $u ? People::name($u) : 'You', 'body' => $body, 'parent_id' => $parent ? $parent['id'] : 0];
    }

    /** The writer, or staff who look after content, can remove a comment. */
    public static function removeComment(PDO $pdo, int $uid, int $id): void
    {
        $st = $pdo->prepare('SELECT user_id FROM content_comments WHERE id = ?');
        $st->execute([$id]);
        $who = $st->fetchColumn();
        require_once __DIR__ . '/Journeys.php';
        if ($who === false || ((int) $who !== $uid && !Journeys::staffCan($pdo, $uid, 'journeys'))) throw new InvalidArgumentException('That comment isn\'t yours to remove.');
        $pdo->prepare('DELETE FROM content_comments WHERE id = ?')->execute([$id]);
    }

    /** Hides or shows a comment. $by: who did it (kept, with when, once the update has run: "Hidden by … ."). */
    public static function setCommentHidden(PDO $pdo, int $id, bool $hide, ?int $by = null): void
    {
        if (self::hideReady($pdo)) $pdo->prepare('UPDATE content_comments SET status = ?, hidden_by = ?, hidden_at = ? WHERE id = ?')->execute([$hide ? 'hidden' : 'visible', $hide ? $by : null, $hide ? self::now() : null, $id]);
        else $pdo->prepare('UPDATE content_comments SET status = ? WHERE id = ?')->execute([$hide ? 'hidden' : 'visible', $id]);
    }

    /**
     * The person's role on a business's team (Team's roles, or a role granted on it: Roles), or null. Used for who answers
     * and hides comments on its listing.
     */
    public static function teamRole(PDO $pdo, int $uid, int $entityId): ?string
    {
        if ($uid <= 0 || $entityId <= 0) return null;
        try {
            require_once __DIR__ . '/Team.php';
            $r = Team::roleOf($pdo, $uid, $entityId);
            if ($r !== null && $r !== 'white_glove') return $r;
        } catch (Throwable) {}
        try {
            require_once __DIR__ . '/Roles.php';
            foreach (Roles::team($pdo, 'org', 'entity', (string) $entityId) as $m) if ((int) $m['user_id'] === $uid) return (string) $m['role'];
        } catch (Throwable) {}
        return null;
    }

    /** Everyone on a business's team, as a set [user id => true]: Team's members, the claim's owner and managers, roles granted on it. */
    private static function teamIds(PDO $pdo, int $entityId): array
    {
        $ids = [];
        try { require_once __DIR__ . '/Team.php'; foreach (Team::members($pdo, $entityId) as $m) $ids[(int) $m['user_id']] = true; } catch (Throwable) {}
        try { $st = $pdo->prepare('SELECT user_id FROM listing_access WHERE entity_id = ?'); $st->execute([$entityId]); foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $u) $ids[(int) $u] = true; } catch (Throwable) {}
        try { $st = $pdo->prepare('SELECT owner_user_id FROM entities WHERE id = ?'); $st->execute([$entityId]); if ($o = (int) $st->fetchColumn()) $ids[$o] = true; } catch (Throwable) {}
        try { require_once __DIR__ . '/Roles.php'; foreach (Roles::team($pdo, 'org', 'entity', (string) $entityId) as $m) $ids[(int) $m['user_id']] = true; } catch (Throwable) {}
        return $ids;
    }

    /**
     * Whether this person may hide (or show again) this comment, and if not, why: [yes, why]. Staff who look after
     * content may; on a listing, the business's Owner and Managers may (decisions/0172 §2.3). A comment our team hid
     * after reports stays with our team.
     */
    public static function canHide(PDO $pdo, int $uid, array $c): array
    {
        if (self::canTidy($pdo, $uid)) return [true, ''];
        if ($c['kind'] !== 'listing') return [false, 'Only our team hides comments here. Report it, and we\'ll look.'];
        if (!in_array(self::teamRole($pdo, $uid, (int) $c['ref']), ['owner', 'manager'], true)) return [false, 'The business\'s Owner and Managers hide comments on its listing.'];
        if ($c['status'] === 'hidden' && $c['hidden_by'] !== $uid && ($c['hidden_by'] <= 0 || self::teamRole($pdo, $c['hidden_by'], (int) $c['ref']) === null))
            return [false, 'Our team hid it after it was reported, so it stays hidden. Ask us about it: support@traversence.com.'];
        return [true, ''];
    }

    /** Whether this person may remove comments here (staff who look after content). */
    public static function canTidy(PDO $pdo, ?int $uid): bool
    {
        if (!$uid) return false;
        require_once __DIR__ . '/Journeys.php';
        return Journeys::staffCan($pdo, $uid, 'journeys');
    }
}
