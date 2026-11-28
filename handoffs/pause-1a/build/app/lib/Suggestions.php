<?php

declare(strict_types=1);

/**
 * api/lib/Suggestions.php — what people tell us (decisions/0053; HITL, decisions/0005).
 *
 * Kinds: missing (something we don't list: a business, a lake, a trail, a landmark, an event), fix (something wrong on
 * any listing), closed (closed or moved), same (two listings are one business), story (information for a place's guide
 * story), feedback (on search results). Topic says what it is about; section says where on the site it was missing
 * ("Lakes" near Show Low). A person reviews every suggestion in Review; nothing changes on the site by itself.
 *
 * Match context for the crawler: a suggestion that names a website or a phone for a business is kept as a hint.
 * When a crawl finds that website or phone, IntakeStager offers the suggested listing as a possible match (hints()),
 * so "this is their website" reaches the person merging. Who suggested it is kept for abuse checks only.
 */
final class Suggestions
{
    public const KINDS = ['missing' => 'Something missing', 'fix' => 'Suggested fix', 'closed' => 'Closed or moved', 'same' => 'Same business',
                          'story' => 'For a guide story', 'feedback' => 'Search feedback', 'word' => 'A word search didn\'t know'];
    public const TOPICS = ['business' => 'Business', 'outdoors' => 'Outdoors (lake, trail, park, campground)', 'culture' => 'History, arts or culture',
                           'food' => 'Food or drink', 'event' => 'Event', 'story' => 'Story', 'other' => 'Something else'];
    public const FIELDS = ['name' => 'Name', 'what' => 'What it is', 'website' => 'Website', 'phone' => 'Phone', 'address' => 'Address or town',
                           'hours' => 'Hours', 'offers' => 'What it offers', 'other_names' => 'Other names it goes by', 'source' => 'Where this comes from',
                           'point' => 'Map point'];   // "lat,lon": a visitor's pin or "I'm here now" (rural places often have no street address)
    /** Fields only the system's own holds carry (never the visitor's form, which shows FIELDS): the About text the pause
     *  holds for Review (2026-11-28). Taken only from a machine's device name. */
    public const HELD_FIELDS = ['description' => 'About (held while paused)'];
    public const HELD_DEVICES = ['crawler-merge', 'crawler-refresh-paused', 'crawler-identity-paused', 'crawler-identity-merge-paused'];
    private const PER_DAY = 12;   // a person (or device) can send this many a day

    public static function ready(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try { $pdo->query('SELECT 1 FROM suggestions LIMIT 1'); return $ok = true; } catch (PDOException) { return $ok = false; }
    }

    public static function siteKey(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
        return mb_substr(preg_replace('/^www\./i', '', strtolower((string) parse_url($url, PHP_URL_HOST))), 0, 120);
    }

    public static function phone10(string $p): string
    {
        $d = preg_replace('/\D/', '', $p);
        return strlen($d) >= 10 ? substr($d, -10) : '';
    }

    /** Save a suggestion. Returns its id. */
    public static function create(PDO $pdo, array $in, ?int $userId, string $device): int
    {
        if (!self::ready($pdo)) throw new RuntimeException('Suggestions aren\'t switched on yet.');
        $kind = (string) ($in['kind'] ?? '');
        if (!isset(self::KINDS[$kind])) throw new InvalidArgumentException('What kind of suggestion is it?');
        $fields = [];
        foreach (self::FIELDS + (in_array($device, self::HELD_DEVICES, true) ? self::HELD_FIELDS : []) as $k => $_) {
            $v = trim(strip_tags((string) ($in['fields'][$k] ?? '')));
            if ($v !== '') $fields[$k] = mb_substr($v, 0, $k === 'description' ? 1500 : ($k === 'offers' || $k === 'hours' ? 400 : 160));
        }
        if (isset($fields['point'])) {   // a map point: two numbers inside the U.S., or dropped
            $pt = array_map('trim', explode(',', $fields['point']));
            $ok = count($pt) === 2 && is_numeric($pt[0]) && is_numeric($pt[1]) && (float) $pt[0] >= 17 && (float) $pt[0] <= 72 && (float) $pt[1] >= -180 && (float) $pt[1] <= -60;
            if ($ok) $fields['point'] = round((float) $pt[0], 6) . ',' . round((float) $pt[1], 6); else unset($fields['point']);
        }
        $note = mb_substr(trim(strip_tags((string) ($in['note'] ?? ''))), 0, $kind === 'story' ? 4000 : 1000);
        $entity = (int) ($in['entity_id'] ?? 0) ?: null;
        $same = (int) ($in['same_as_id'] ?? 0) ?: null;
        $topic = (string) ($in['topic'] ?? '');
        if (!isset(self::TOPICS[$topic])) $topic = $kind === 'story' ? 'story' : ($entity ? 'business' : 'other');
        // Anything else on the site: a recreation place (rec:ID) or a place (place:cluster:12)
        $target = preg_match('/^(rec:[A-Za-z0-9_-]{1,40}|place:(hub|geo_hub|cluster|town|sub):\d{1,9})$/', (string) ($in['target'] ?? '')) ? (string) $in['target'] : '';
        $placeKey = preg_match('/^(hub|geo_hub|cluster|town|sub):\d{1,9}$/', (string) ($in['place_key'] ?? '')) ? (string) $in['place_key'] : '';
        $clip = fn ($k, $n) => mb_substr(trim(strip_tags((string) ($in[$k] ?? ''))), 0, $n);
        if ($kind === 'missing' && empty($fields['name'])) throw new InvalidArgumentException('What is it called?');
        if (in_array($kind, ['fix', 'closed'], true) && !$entity && $target === '') throw new InvalidArgumentException('Which listing is it about?');
        if ($kind === 'same' && !$entity) throw new InvalidArgumentException('Which listing is it about?');
        if ($kind === 'story' && mb_strlen($note) < 20) throw new InvalidArgumentException('Tell us a little more: what should the story say?');
        if ($kind === 'same' && (!$same || $same === $entity)) throw new InvalidArgumentException('Choose the other listing that is the same business.');
        if ($kind === 'fix' && !$fields && $note === '') throw new InvalidArgumentException('Tell us what to change.');
        if ($kind === 'feedback' && $note === '') throw new InvalidArgumentException('Tell us what you were looking for.');
        if ($kind === 'word' && trim((string) ($in['target_name'] ?? '')) === '') throw new InvalidArgumentException('Which word?');
        if ($entity) {
            $st = $pdo->prepare('SELECT 1 FROM entities WHERE id = ?'); $st->execute([$entity]);
            if (!$st->fetchColumn()) throw new InvalidArgumentException('That listing isn\'t there any more.');
        }
        // a person's or device's daily limit; the site's own checks (the crawler's "crawler-…", "system:…") aren't visitors:
        // each asks once per thing by its own rules (2026-11-13: the refresh's 13th Review item of a day was lost)
        if ($userId !== null || !preg_match('/^(crawler-|system:)/', $device)) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM suggestions WHERE (device_hash = ?' . ($userId ? ' OR user_id = ?' : '') . ') AND created_at >= ?');
            $st->execute(array_merge([$device], $userId ? [$userId] : [], [date('Y-m-d H:i:s', time() - 86400)]));
            if ((int) $st->fetchColumn() >= self::PER_DAY) throw new InvalidArgumentException('Thanks, that\'s plenty for today. Please send more tomorrow.');
        }
        $ctx = mb_substr(trim(strip_tags((string) ($in['context'] ?? ''))), 0, 400);
        $pdo->prepare('INSERT INTO suggestions (kind, topic, entity_id, same_as_id, target, target_name, place_key, section, fields, note, context, site_key, phone10, user_id, device_hash)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$kind, $topic, $entity, $same, $target, $clip('target_name', 200), $placeKey, $clip('section', 120), $fields ? json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, $note, $ctx,
                       self::siteKey((string) ($fields['website'] ?? '')), self::phone10((string) ($fields['phone'] ?? '')), $userId, $device]);
        $sid = (int) $pdo->lastInsertId();
        if ($placeKey !== '' && in_array($kind, ['story', 'fix', 'closed', 'missing'], true) && $userId !== null) {   // a visitor's word about a guide brings its check forward
            try { require_once __DIR__ . '/Guides.php'; Guides::due($pdo, $placeKey, 'a visitor suggested a change'); } catch (Throwable $e) {}
        }
        return $sid;
    }

    /** Waiting for a person (Review). */
    public static function waiting(PDO $pdo, int $limit = 200): array
    {
        if (!self::ready($pdo)) return [];
        $st = $pdo->query("SELECT s.*, e.name AS entity_name, e2.name AS same_name FROM suggestions s
                             LEFT JOIN entities e ON e.id = s.entity_id LEFT JOIN entities e2 ON e2.id = s.same_as_id
                            WHERE s.status = 'new' ORDER BY s.created_at DESC LIMIT " . max(1, min(500, $limit)));
        return array_map(function ($r) { $r['fields'] = json_decode((string) $r['fields'], true) ?: []; return $r; }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** The site's own checks (the crawler's "crawler-…", "system:…"): work for the review checkers, never a visitor's message. */
    public const OWN_SQL = "(device_hash LIKE 'crawler-%' OR device_hash LIKE 'system:%')";

    /** Waiting from the site's own checks: ['new' => all waiting, 'hour' => made in the last hour]. */
    public static function ownWaiting(PDO $pdo): array
    {
        if (!self::ready($pdo)) return ['new' => 0, 'hour' => 0];
        try {
            $st = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(created_at >= ?), 0) FROM suggestions WHERE status = 'new' AND " . self::OWN_SQL);
            $st->execute([date('Y-m-d H:i:s', time() - 3600)]);
            [$n, $h] = $st->fetch(PDO::FETCH_NUM);
            return ['new' => (int) $n, 'hour' => (int) $h];
        } catch (PDOException) { return ['new' => 0, 'hour' => 0]; }
    }

    /** Waiting from people (visitors, members, staff calls), not the site's own checks. */
    public static function peopleCount(PDO $pdo): int
    {
        if (!self::ready($pdo)) return 0;
        try { return (int) $pdo->query("SELECT COUNT(*) FROM suggestions WHERE status = 'new' AND NOT " . self::OWN_SQL)->fetchColumn(); } catch (PDOException) { return 0; }
    }

    public static function count(PDO $pdo): int
    {
        if (!self::ready($pdo)) return 0;
        try { return (int) $pdo->query("SELECT COUNT(*) FROM suggestions WHERE status = 'new'")->fetchColumn(); } catch (PDOException) { return 0; }
    }

    public static function decide(PDO $pdo, int $id, string $status, int $admin): void
    {
        if (!in_array($status, ['used', 'dismissed', 'new'], true)) throw new InvalidArgumentException('Unknown decision.');
        $pdo->prepare('UPDATE suggestions SET status = ?, handled_by = ?, handled_at = ? WHERE id = ?')->execute([$status, $admin, date('Y-m-d H:i:s'), $id]);
    }

    /**
     * Match context for a crawled business: suggestions that gave the same website or phone.
     * @return list<array{id: int, name: string, status: string, zip: string, why: string, suggestion: int}>
     */
    public static function hints(PDO $pdo, string $site, string $phone): array
    {
        if (!self::ready($pdo)) return [];
        $site = self::siteKey($site); $phone = self::phone10($phone);
        if ($site === '' && $phone === '') return [];
        $w = []; $a = [];
        if ($site !== '') { $w[] = 's.site_key = ?'; $a[] = $site; }
        if ($phone !== '') { $w[] = 's.phone10 = ?'; $a[] = $phone; }
        $st = $pdo->prepare("SELECT s.id, s.kind, s.entity_id, s.site_key, s.phone10, s.note, s.fields, e.name, e.status, e.primary_zip FROM suggestions s
                               LEFT JOIN entities e ON e.id = s.entity_id WHERE s.status <> 'dismissed' AND s.topic IN ('business', 'food') AND (" . implode(' OR ', $w) . ') ORDER BY s.created_at DESC LIMIT 10');
        $st->execute($a);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $by = $site !== '' && $r['site_key'] === $site ? 'website' : 'phone';
            $note = $r['note'] !== '' ? ': “' . mb_strimwidth((string) $r['note'], 0, 120, '…') . '”' : '';
            if ($r['entity_id']) {
                $out[] = ['id' => (int) $r['entity_id'], 'name' => (string) $r['name'], 'status' => (string) $r['status'], 'zip' => (string) $r['primary_zip'],
                          'why' => 'a visitor gave this ' . $by . ' for it' . $note, 'suggestion' => (int) $r['id']];
            } else {
                $f = json_decode((string) $r['fields'], true) ?: [];
                $out[] = ['id' => 0, 'name' => (string) ($f['name'] ?? ''), 'status' => 'suggested', 'zip' => '',
                          'why' => 'a visitor suggested this business (same ' . $by . ')' . $note, 'suggestion' => (int) $r['id']];
            }
        }
        return $out;
    }
}
