<?php
// app/lib/MarketBiz.php — the Marketplace, step 3: businesses' items (decisions/0135 §2, §6, §7; Jason, 2026-11-01).
//
// "The catalog management is walled, but the post or publish to the marketplace should not be." A business edits its
// catalog behind the walls (decisions/0110: its listings-database rows, its team only). Publishing an item writes only
// its public parts (name, description, kind, price, photos, the business's town) here in the main database, as a
// published version, and the Marketplace, the listing's Shop and search read only that. Cost, markup and supplier never
// leave the walls. Publishing is an action the business portal takes after its role check (Team 'publish'), so it's
// never a door through the walls.
//
// Draft and published versions per item: the catalog item is the draft. Publish copies it out (version 1, 2, …, every
// one kept in market_item_versions); later edits stay in the draft ("Published, with unpublished changes") until
// Publish changes. Unpublish takes it off; Publish brings it back.
//   status   published · unpublished (by the business) · expired (not confirmed at a 30-day check) · removed (our team)
//   cap      published items per listing, by plan (commercial.md §1.1): Free 10, Basic 25, Core 100, Strategic 250,
//            Cornerstone 1,000. At the cap, Publish is greyed with the plan that raises it; nothing up is taken down.
//   buttons  what buyers can do, set by the business (Marketplace settings) and per item, within its plan and setup:
//            Message (its inbox), Make an offer (off until switched on), Request a quote (Basic and up: Quotes),
//            Buy now (Core and up, through its own gateway: coming in step 4).
//   check    every 30 days "Still on offer?" in the business's Pulse; not confirmed by the next check, it expires.
declare(strict_types=1);

require_once __DIR__ . '/ItemKinds.php';
require_once __DIR__ . '/Market.php';
require_once __DIR__ . '/Plan.php';

final class MarketBiz
{
    public const CAPS = ['free' => 10, 'basic' => 25, 'core' => 100, 'strategic' => 250, 'cornerstone' => 1000];
    public const STATUSES = ['published' => 'Published', 'held' => 'Waiting for our team', 'unpublished' => 'Unpublished', 'expired' => 'Expired', 'removed' => 'Taken down'];
    /** key => [label, the plan feature it needs (Plan::FEATURES), coming (not built yet)] */
    public const BUTTONS = [
        'message' => ['Message', 'inbox', false],
        'offer' => ['Make an offer', 'catalog', false],
        'quote' => ['Request a quote', 'quotes', false],
        'buy' => ['Buy now and Add to cart', 'manual_pay', false],   // step 4: on every plan once its checkout is live (card from Core)
    ];
    public const DEFAULT_BUTTONS = ['message'];   // offers off until a business switches them on (Jason, 2026-11-01)
    public const MAX_PHOTOS = 10;

    public static function ready(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try { $pdo->query('SELECT 1 FROM market_items LIMIT 0'); $pdo->query('SELECT 1 FROM market_biz LIMIT 0'); return $ok = true; } catch (PDOException) { return $ok = false; }
    }

    private static function now(): string { return date('Y-m-d H:i:s'); }

    // ---- the business: plan, cap, settings, place ----------------------------------------------------------------------

    public static function entity(PDO $pdo, int $e): ?array
    {
        $st = $pdo->prepare('SELECT id, name, plan_tier, micro_cluster_id, status, is_suppressed FROM entities WHERE id = ?'); $st->execute([$e]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function tier(PDO $pdo, int $e): string { return Plan::tier((string) (self::entity($pdo, $e)['plan_tier'] ?? 'free')); }

    public static function cap(PDO $pdo, int $e): int { return self::CAPS[self::tier($pdo, $e)] ?? 10; }

    /** The plan that raises the cap, for the greyed button: "Basic Partner (25)" or ''. */
    public static function nextCap(string $tier): string
    {
        $keys = array_keys(self::CAPS); $i = array_search($tier, $keys, true);
        if ($i === false || !isset($keys[$i + 1])) return '';
        return Plan::TIERS[$keys[$i + 1]] . ' (' . number_format(self::CAPS[$keys[$i + 1]]) . ')';
    }

    public static function publishedCount(PDO $pdo, int $e): int
    {
        $st = $pdo->prepare("SELECT COUNT(*) FROM market_items WHERE entity_id = ? AND status = 'published'"); $st->execute([$e]);
        return (int) $st->fetchColumn();
    }

    /** The buttons the business turned on (its Marketplace settings). */
    public static function buttons(PDO $pdo, int $e): array
    {
        $st = $pdo->prepare('SELECT buttons FROM market_biz WHERE entity_id = ?'); $st->execute([$e]);
        $v = $st->fetchColumn();
        return $v === false ? self::DEFAULT_BUTTONS : array_values(array_filter(explode(',', (string) $v), fn ($b) => isset(self::BUTTONS[$b])));
    }

    /** Which buttons this business's plan and setup open: key => [open, why not]. */
    public static function allowed(PDO $pdo, int $e): array
    {
        $tier = self::tier($pdo, $e); $out = [];
        foreach (self::BUTTONS as $k => [$label, $feature, $coming]) {
            [$ok, $start] = Plan::allows($pdo, $tier, $feature);
            $out[$k] = !$ok ? [false, Plan::upgradeNote($start)] : ($coming ? [false, 'Coming'] : [true, '']);
            if ($k === 'buy' && $ok && !self::checkoutLive($pdo, $e)) $out[$k] = [false, 'Set up your checkout first (E-commerce setup in your business portal)'];
        }
        return $out;
    }

    public static function saveButtons(PDO $pdo, int $e, int $uid, array $want): array
    {
        $ok = self::allowed($pdo, $e);
        $on = array_values(array_filter(array_keys(self::BUTTONS), fn ($b) => in_array($b, $want, true) && $ok[$b][0]));
        if (!$on) throw new InvalidArgumentException('Keep at least one way for buyers to reach you.');
        $pdo->prepare('INSERT INTO market_biz (entity_id, buttons, updated_by, updated_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE buttons = VALUES(buttons), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)')
            ->execute([$e, implode(',', $on), $uid ?: null, self::now()]);
        return $on;
    }

    /** Is the business's checkout set up and live (step 4, MarketCheckout)? */
    public static function checkoutLive(PDO $pdo, int $e): bool
    {
        try { $st = $pdo->prepare('SELECT live FROM market_checkout WHERE entity_id = ?'); $st->execute([$e]); return (bool) $st->fetchColumn(); } catch (Throwable) { return false; }
    }

    /** What a buyer sees on an item: its own choice, else the business's, within what the plan opens now. */
    /**
     * What buyers can do on an item, decided by what it is (Jason, 2026-11-04: "based on the product kind should the
     * marketplace UI UX adjust to how that product is handled. Services is request services, products buy now, made to
     * order request a quote"), never ticked by the seller: its kind's main action first, then Message, and Make an offer
     * on goods when the store takes offers. Buy needs a set price and live checkout; without them, a quote (or Message on a
     * plan without quotes). Each still needs what the plan and setup open (allowed()).
     */
    public static function buttonsFor(PDO $pdo, array $item): array
    {
        $ok = self::allowed($pdo, (int) $item['entity_id']);
        $main = self::mainAction($item);
        if ($main === 'buy' && !$ok['buy'][0]) $main = 'quote';
        $out = [];
        if ($main !== '' && $ok[$main][0]) $out[] = $main;
        if ($ok['message'][0]) $out[] = 'message';
        $kind = ItemKinds::key((string) ($item['kind'] ?? ''));
        if (in_array($kind, self::OFFER_KINDS, true) && $ok['offer'][0] && in_array('offer', self::buttons($pdo, (int) $item['entity_id']), true)) $out[] = 'offer';
        return $out;
    }

    /** Kinds a buyer can make an offer on (goods, when the store takes offers): not a service, a quote or a seat. */
    public const OFFER_KINDS = ['physical', 'harvest', 'rental'];

    /** The kind's own way to buy: 'buy' (a set price) or 'quote' (a service, made to order, priced by the hour or without a price). */
    public static function mainAction(array $item): string
    {
        $kind = ItemKinds::key((string) ($item['kind'] ?? ''));
        if (in_array($kind, ['service', 'madetoorder'], true)) return 'quote';
        if (ItemKinds::buy($kind, ItemKinds::data($kind, $item['kind_data'] ?? null)) === 'quote') return 'quote';
        return ($item['price_mode'] ?? 'fixed') === 'fixed' && ($item['price_cents'] ?? null) !== null ? 'buy' : 'quote';
    }

    /** A button's words for this item's kind: "Get tickets", "Request this service", "Book your stay"… */
    public static function label(string $b, array $item): string
    {
        $kind = ItemKinds::key((string) ($item['kind'] ?? ''));
        if ($b === 'buy') return ['digital' => 'Buy and download', 'ticket' => 'Get tickets', 'booking' => 'Book your stay', 'rental' => 'Rent it', 'subscription' => 'Subscribe'][$kind] ?? 'Buy now';
        if ($b === 'quote') return $kind === 'service' ? 'Request this service' : 'Request a quote';
        return self::BUTTONS[$b][0];
    }

    /** The business's town, for its items: [key, label, geo_hub, hub], or throws when its listing has none. */
    public static function place(PDO $pdo, int $e): array
    {
        $c = (int) (self::entity($pdo, $e)['micro_cluster_id'] ?? 0);
        if (!$c) throw new InvalidArgumentException('Your listing has no town yet, so buyers couldn\'t find your items. Add your address on your listing first.');
        return Market::place($pdo, 'cluster:' . $c);
    }

    // ---- draft and published -------------------------------------------------------------------------------------------

    /** The public parts of a catalog item (Products::get, inside the walls): name, description, kind and the price shown. */
    public static function publicOf(array $p): array
    {
        $tiers = (array) ($p['tiers'] ?? []);
        $t = $tiers[0] ?? null;
        $cents = $t ? (int) round((float) ($t['price'] ?? 0)) : 0;
        $mode = $cents <= 0 ? 'ask' : (count($tiers) > 1 || !empty($p['options']) ? 'from' : 'fixed');
        require_once __DIR__ . '/ItemKinds.php';
        $kind = ItemKinds::key((string) ($p['kind'] ?? ''));
        return ['title' => mb_substr(trim((string) $p['name']), 0, 120), 'about' => mb_substr(trim((string) ($p['description'] ?? '')), 0, 600), 'kind' => $kind,
            'price_mode' => $mode, 'price_cents' => $cents > 0 ? $cents : null, 'price_qty' => $t ? max(1, (int) $t['qty']) : 1, 'kind_data' => ItemKinds::data($kind, $p['kind_data'] ?? null)];
    }

    /** gallery item ids of an item's photos: the draft's (live false) or the published version's (live true) */
    public static function photoIds(PDO $pdo, int $e, int $product, bool $live): array
    {
        $st = $pdo->prepare('SELECT gallery_item_id FROM market_item_photos WHERE entity_id = ? AND product_id = ? AND live = ? ORDER BY sort, gallery_item_id');
        $st->execute([$e, $product, $live ? 1 : 0]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** A fingerprint of what Publish would show now, to tell "Published" from "Published, with unpublished changes". */
    public static function hash(array $pub, array $photos, string $category, string $buttons): string
    {
        return sha1(json_encode([$pub, $photos, $category, $buttons]));
    }

    public static function item(PDO $pdo, int $e, int $product): ?array
    {
        $st = $pdo->prepare('SELECT * FROM market_items WHERE entity_id = ? AND product_id = ?'); $st->execute([$e, $product]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function get(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare('SELECT * FROM market_items WHERE id = ?'); $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Where a catalog item stands: draft (never published), published, changed (published, draft differs), unpublished, expired, removed. */
    public static function state(PDO $pdo, int $e, array $p): array
    {
        $it = self::item($pdo, $e, (int) $p['id']);
        if (!$it) return ['state' => 'draft', 'label' => 'Draft only', 'item' => null];
        $pub = self::publicOf($p); $photos = self::photoIds($pdo, $e, (int) $p['id'], false);
        $now = self::hash($pub, $photos, (string) $it['category'], (string) $it['buttons']);
        if ($now !== $it['draft_hash'] && !(array) ($p['kind_data_raw'] ?? [])) {
            // published before the nine kinds (decisions/0141 §5): the same item under its earlier kind ("item", "class") isn't a change
            $legacy = $pub; unset($legacy['kind_data']);
            foreach (array_keys(ItemKinds::LEGACY + ['service' => 'service'], $pub['kind'], true) as $old) {
                $legacy['kind'] = $old;
                if (self::hash($legacy, $photos, (string) $it['category'], (string) $it['buttons']) === $it['draft_hash']) {
                    try { $pdo->prepare('UPDATE market_items SET draft_hash = ?, kind = ? WHERE id = ?')->execute([$now, $pub['kind'], (int) $it['id']]); $it['draft_hash'] = $now; } catch (Throwable) {}
                    break;
                }
            }
        }
        if ($it['status'] === 'published') return $now === $it['draft_hash'] ? ['state' => 'published', 'label' => 'Published (version ' . (int) $it['version'] . ')', 'item' => $it]
            : ['state' => 'changed', 'label' => 'Published, with unpublished changes', 'item' => $it];
        return ['state' => (string) $it['status'], 'label' => self::STATUSES[$it['status']] ?? $it['status'], 'item' => $it];
    }

    /**
     * Publishes a catalog item (the draft, read inside the walls by the portal) or its changes: its public parts become a
     * new published version. $category: a Marketplace category; $buttons: this item's own buttons ('' = the business's).
     */
    /**
     * $answer (a restricted rule, decisions/0142 §4): ['rule' => its key, 'claim' => 'is' (and the business confirms its
     * license and the conditions) or 'not' (the item isn't that)]. Without it, a restricted match throws MarketRuleAsk.
     */
    public static function publish(PDO $pdo, int $uid, int $e, array $p, string $category, string $buttons = '', array $answer = []): array
    {
        require_once __DIR__ . '/Standing.php'; Standing::guard($pdo, $uid);
        if (($p['status'] ?? 'active') !== 'active') throw new InvalidArgumentException('It\'s hidden in your catalog. Make it Active first.');
        $pub = self::publicOf($p);
        if (mb_strlen($pub['title']) < 2) throw new InvalidArgumentException('Give it a name in your catalog first.');
        $cats = Market::categories($pdo);
        if (!isset($cats[$category])) throw new InvalidArgumentException('Choose its Marketplace category.');
        // the rulebook (decisions/0142 §4): prohibited never goes up; restricted needs the business's answer; a hold word waits for our team
        require_once __DIR__ . '/MarketRules.php';
        [$kOk, $kMiss] = ItemKinds::ready((string) $pub['kind'], (array) $pub['kind_data']);   // a ticket needs its date, digital its file… (decisions/0141 §5)
        if (!$kOk) throw new InvalidArgumentException(ItemKinds::name((string) $pub['kind']) . ': ' . lcfirst($kMiss));
        [$fOk, $fWhy] = ItemKinds::fits($pub['title'], (string) $pub['kind']);   // its name says what it is (decisions/0144): change the kind in Catalog → Details
        if (!$fOk) throw new InvalidArgumentException($fWhy . ' Change its kind in Catalog → Details.');
        $rule = MarketRules::check($pdo, $pub['title'], $pub['about'], $category, (string) $pub['kind']);
        if ($rule['tier'] === 'prohibited') throw new InvalidArgumentException($rule['why'] . ' So it can\'t go up.');
        // a new Seller Agreement that needs accepting is asked for before the next publish
        require_once __DIR__ . '/MarketCheckout.php';
        $sa = MarketCheckout::seller($pdo, $e);
        if ($sa['new'] || (!$sa['last'] && !empty(MarketCheckout::settings($pdo, $e)['live']))) throw new InvalidArgumentException('Accept the ' . ($sa['new'] ? 'new ' : '') . 'Seller Agreement first: E-commerce setup, step 6.');
        $ok = self::allowed($pdo, $e);
        $buttons = implode(',', array_values(array_filter(explode(',', $buttons), fn ($b) => isset($ok[$b]) && $ok[$b][0])));
        $it = self::item($pdo, $e, (int) $p['id']);
        $flag = ''; $status = 'published'; $said = null;
        if ($rule['tier'] === 'restricted' || $rule['tier'] === 'confirm') {   // restricted: its license (card off); confirm: its conditions (card on)
            $prev = (string) ($it['rule_flag'] ?? '');
            $same = $it && $it['title'] === $pub['title'] && (string) $it['about'] === $pub['about'];   // an earlier answer stands only for the same words
            if (($answer['rule'] ?? '') === $rule['key'] && in_array($answer['claim'] ?? '', ['is', 'not'], true)) { $flag = ($answer['claim'] === 'is' ? $rule['tier'] . ':' : 'said-not:') . $rule['key']; $said = $answer['claim']; }
            elseif ($same && ($prev === $rule['tier'] . ':' . $rule['key'] || $prev === 'said-not:' . $rule['key'])) $flag = $prev;   // answered when it was published before
            else throw new MarketRuleAsk($rule);
        } elseif ($rule['tier'] === 'review') { $flag = 'review:hold'; $status = 'held'; }
        elseif ($rule['tier'] === 'notice') $flag = 'notice:' . $rule['key'];   // the notice buyers see (made to order, digital, perishable, used)
        $recall = null;   // a recalled product (CPSC): our team looks before it shows (decisions/0142 §4)
        if ($status === 'published' && !($it && $it['title'] === $pub['title'] && in_array((string) ($it['rule_flag'] ?? ''), ['cleared:recall'], true))) {
            $recall = MarketRules::recall($pub['title']);
            if ($recall) { $flag = 'review:recall'; $status = 'held'; }
        }
        $was = $it && $it['status'] === 'published';
        if (!$was && $it && $it['status'] === 'removed') throw new InvalidArgumentException('Our team took this item down: ' . $it['staff_note']);
        if (!$was && self::publishedCount($pdo, $e) >= self::cap($pdo, $e)) {
            $next = self::nextCap(self::tier($pdo, $e));
            throw new InvalidArgumentException('That\'s ' . number_format(self::cap($pdo, $e)) . ' published, your plan\'s most. Unpublish one' . ($next !== '' ? ', or move up to ' . $next : '') . '.');
        }
        [$pk, $pl, $geo, $hub] = self::place($pdo, $e);
        $photos = self::ownPhotos($pdo, $e, self::photoIds($pdo, $e, (int) $p['id'], false));
        $hash = self::hash($pub, $photos, $category, $buttons);
        $now = self::now(); $due = date('Y-m-d H:i:s', strtotime('+' . (int) Market::setting($pdo, 'check_days') . ' days'));
        $vals = [$pub['title'], $pub['about'], $pub['kind'], $category, $pub['price_mode'], $pub['price_cents'], $pub['price_qty'], $pk, $pl, $geo, $hub, $buttons, $hash, $due, $uid ?: null, $now];
        if ($it) {
            $pdo->prepare("UPDATE market_items SET title = ?, about = ?, kind = ?, category = ?, price_mode = ?, price_cents = ?, price_qty = ?, place_key = ?, place_label = ?, geo_hub_id = ?, hub_id = ?, buttons = ?,
                draft_hash = ?, check_due = ?, published_by = ?, updated_at = ?, status = 'published', asked_at = NULL, version = version + 1" . ($was ? '' : ', published_at = ?') . ' WHERE id = ?')
                ->execute(array_merge($vals, $was ? [] : [$now], [(int) $it['id']]));
            $id = (int) $it['id'];
        } else {
            $pdo->prepare("INSERT INTO market_items (title, about, kind, category, price_mode, price_cents, price_qty, place_key, place_label, geo_hub_id, hub_id, buttons, draft_hash, check_due, published_by, updated_at, entity_id, product_id, published_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute(array_merge($vals, [$e, (int) $p['id'], $now]));
            $id = (int) $pdo->lastInsertId();
        }
        $pdo->prepare('DELETE FROM market_item_photos WHERE entity_id = ? AND product_id = ? AND live = 1')->execute([$e, (int) $p['id']]);
        $ins = $pdo->prepare('INSERT INTO market_item_photos (entity_id, product_id, gallery_item_id, live, sort) VALUES (?, ?, ?, 1, ?)');
        foreach ($photos as $i => $g) $ins->execute([$e, (int) $p['id'], $g, $i]);
        $row = self::get($pdo, $id);
        $pdo->prepare('INSERT INTO market_item_versions (item_id, version, snapshot, by_user, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$id, (int) $row['version'], json_encode($pub + ['category' => $category, 'buttons' => $buttons, 'photos' => $photos], JSON_UNESCAPED_UNICODE), $uid ?: null, $now]);
        try { $pdo->prepare('UPDATE market_items SET taxable = ? WHERE id = ?')->execute([(int) ($p['taxable'] ?? 1) ? 1 : 0, $id]); $row['taxable'] = (int) ($p['taxable'] ?? 1) ? 1 : 0; } catch (Throwable) {}   // step 4's column
        try { $pdo->prepare('UPDATE market_items SET kind_data = ? WHERE id = ?')->execute([json_encode($pub['kind_data'], JSON_UNESCAPED_UNICODE), $id]); $row['kind_data'] = json_encode($pub['kind_data']); } catch (PDOException) {}   // its kind's details, once the database update has run
        if (MarketRules::ready($pdo)) {   // what the rulebook found; a hold word or a possible recall waits for our team
            $pdo->prepare('UPDATE market_items SET rule_flag = ?, status = ?' . ($recall ? ', staff_note = ?' : '') . ' WHERE id = ?')
                ->execute(array_merge([$flag, $status], $recall ? ['Possible recall: ' . mb_substr($recall['title'], 0, 180) . ($recall['date'] !== '' ? ' (' . $recall['date'] . ')' : '') . ($recall['url'] !== '' ? ' ' . $recall['url'] : '')] : [], [$id]));
            $row['rule_flag'] = $flag; $row['status'] = $status;
        }
        if ($said !== null) {   // the business's answer, kept (Acceptance)
            require_once __DIR__ . '/Acceptance.php';
            $cond = MarketRules::RULES[$rule['key']][3];
            Acceptance::record($pdo, 'restricted_item', $uid, $e, null, 'rule:' . $rule['key'], $said, $said === 'is' ? $rule['name'] . ': ' . $cond : 'Not ' . $rule['name'],
                ['item' => $id, 'title' => $pub['title'], 'rule' => $rule['key'], 'tier' => $rule['tier'], 'word' => $rule['word'], 'claim' => $said, 'conditions' => $said === 'is' ? $cond : '']);
        }
        self::syncAlbum($pdo, $e);
        return $row;
    }

    /** Takes an item off the Marketplace and the Shop (kept, with its versions; Publish brings it back). */
    public static function unpublish(PDO $pdo, int $e, int $product): void
    {
        $pdo->prepare("UPDATE market_items SET status = 'unpublished', asked_at = NULL, updated_at = ? WHERE entity_id = ? AND product_id = ? AND status IN ('published','expired')")->execute([self::now(), $e, $product]);
        self::syncAlbum($pdo, $e);
    }

    /**
     * A catalog item deleted (Jason, 2026-11-04: "how do I remove or delete items"): off the Marketplace, the storefront and
     * the Shop, its Square link kept as removed (so the next import doesn't bring it back), its digital file removed when
     * no published item sells it. Its Marketplace record stays, unpublished, for the orders that name it.
     */
    public static function forget(PDO $pdo, int $e, int $product, array $p): void
    {
        $pdo->prepare("UPDATE market_items SET status = 'unpublished', asked_at = NULL, updated_at = ? WHERE entity_id = ? AND product_id = ? AND status <> 'removed'")->execute([self::now(), $e, $product]);
        self::syncAlbum($pdo, $e);
        try { $pdo->prepare("UPDATE market_square_map SET status = 'removed' WHERE entity_id = ? AND product_id = ?")->execute([$e, $product]); } catch (PDOException) {}   // before Square's update
        $f = (string) (is_array($p['kind_data'] ?? null) ? ($p['kind_data']['file_key'] ?? '') : '');
        if ($f !== '' && !self::fileInUse($pdo, $f)) { require_once __DIR__ . '/Store.php'; Store::remove(dirname(__DIR__) . '/storage/' . $f); }
    }

    /** "Still on offer?": yes. */
    public static function confirm(PDO $pdo, int $e, int $product): void
    {
        $pdo->prepare("UPDATE market_items SET asked_at = NULL, check_due = ? WHERE entity_id = ? AND product_id = ? AND status = 'published'")
            ->execute([date('Y-m-d H:i:s', strtotime('+' . (int) Market::setting($pdo, 'check_days') . ' days')), $e, $product]);
    }

    // ---- photos: in the business's gallery, shown through its public "Marketplace" album while published --------------

    /** Of these gallery items, the business's own photos. */
    private static function ownPhotos(PDO $pdo, int $e, array $ids): array
    {
        if (!$ids) return [];
        $st = $pdo->prepare("SELECT id FROM gallery_items WHERE id IN (" . implode(',', array_map('intval', $ids)) . ") AND owner_kind = 'businesses' AND owner_ref = ? AND kind = 'photo' AND ready = 1");
        $st->execute([(string) $e]);
        $ok = array_flip(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
        return array_values(array_filter($ids, fn ($i) => isset($ok[$i])));
    }

    /** Adds a photo (already in the business's gallery) to an item's draft. */
    public static function addDraftPhoto(PDO $pdo, int $e, int $product, int $galleryItem): void
    {
        if (!self::ownPhotos($pdo, $e, [$galleryItem])) throw new InvalidArgumentException('That photo isn\'t in your gallery.');
        $have = self::photoIds($pdo, $e, $product, false);
        if (count($have) >= self::MAX_PHOTOS) throw new InvalidArgumentException('That\'s ' . self::MAX_PHOTOS . ' photos, the most for one item.');
        $pdo->prepare('INSERT IGNORE INTO market_item_photos (entity_id, product_id, gallery_item_id, live, sort) VALUES (?, ?, ?, 0, ?)')->execute([$e, $product, $galleryItem, count($have)]);
    }

    /** Sets the draft's photos and their order (a subset of what's there: removing and reordering). */
    public static function setDraftPhotos(PDO $pdo, int $e, int $product, array $ids): void
    {
        $ids = array_values(array_intersect(array_map('intval', $ids), self::photoIds($pdo, $e, $product, false)));
        $pdo->prepare('DELETE FROM market_item_photos WHERE entity_id = ? AND product_id = ? AND live = 0')->execute([$e, $product]);
        $ins = $pdo->prepare('INSERT INTO market_item_photos (entity_id, product_id, gallery_item_id, live, sort) VALUES (?, ?, ?, 0, ?)');
        foreach ($ids as $i => $g) $ins->execute([$e, $product, $g, $i]);
    }

    /** The photos of an item as a page shows them: [id, full, card]. */
    public static function photos(PDO $pdo, int $e, int $product, bool $live = true): array
    {
        require_once __DIR__ . '/Gallery.php';
        $ids = self::photoIds($pdo, $e, $product, $live);
        if (!$ids) return [];
        $rows = [];
        foreach ($pdo->query('SELECT * FROM gallery_items WHERE id IN (' . implode(',', $ids) . ') AND ready = 1') as $r) $rows[(int) $r['id']] = $r;
        $out = [];
        foreach ($ids as $i) if (isset($rows[$i])) { $v = Gallery::view($rows[$i]); $out[] = ['id' => $i, 'full' => $v['full'], 'card' => $v['card'], 'alt' => $v['alt']]; }
        return $out;
    }

    /** The business's "Marketplace" album holds exactly the photos of its published items (public), and nothing else. */
    public static function syncAlbum(PDO $pdo, int $e): void
    {
        try {
            require_once __DIR__ . '/Gallery.php';
            Gallery::ensure($pdo);
            $k = 'balbum:' . $e;
            $album = (int) (Market::setting($pdo, $k) ?? 0);
            $st = $pdo->prepare("SELECT id FROM gallery_albums WHERE id = ? AND owner_kind = 'businesses' AND owner_ref = ?"); $st->execute([$album, (string) $e]);
            if (!$album || !$st->fetchColumn()) {
                $pdo->prepare("INSERT INTO gallery_albums (owner_kind, owner_ref, title, about, mode, visibility, token, created_at) VALUES ('businesses', ?, 'Marketplace', ?, 'social', 'public', ?, ?)")
                    ->execute([(string) $e, 'Photos of what you have on the Marketplace. They show here while an item is published.', bin2hex(random_bytes(16)), self::now()]);
                $album = (int) $pdo->lastInsertId();
                Market::remember($pdo, $k, (string) $album);
            }
            $pdo->prepare("UPDATE gallery_albums SET visibility = 'public' WHERE id = ?")->execute([$album]);
            $st = $pdo->prepare("SELECT DISTINCT ph.gallery_item_id FROM market_item_photos ph JOIN market_items i ON i.entity_id = ph.entity_id AND i.product_id = ph.product_id
                WHERE ph.entity_id = ? AND ph.live = 1 AND i.status = 'published'");
            $st->execute([$e]);
            $want = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
            $st = $pdo->prepare('SELECT item_id FROM gallery_album_items WHERE album_id = ?'); $st->execute([$album]);
            $have = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
            foreach (array_diff($have, $want) as $g) $pdo->prepare('DELETE FROM gallery_album_items WHERE album_id = ? AND item_id = ?')->execute([$album, $g]);
            $ins = $pdo->prepare('INSERT IGNORE INTO gallery_album_items (album_id, item_id, sort, added_at) VALUES (?, ?, 0, ?)');
            foreach (array_diff($want, $have) as $g) $ins->execute([$album, $g, self::now()]);
        } catch (Throwable $x) { error_log('market business album: ' . $x->getMessage()); }
    }

    // ---- buyers ----------------------------------------------------------------------------------------------------------

    public static function price(array $it): string
    {
        if ($it['price_mode'] === 'ask' || $it['price_cents'] === null) return 'Ask';
        $c = (int) $it['price_cents'];
        $m = '$' . ($c % 100 ? number_format($c / 100, 2) : number_format(intdiv($c, 100)));
        return ($it['price_mode'] === 'from' ? 'From ' : '') . $m . ((int) $it['price_qty'] > 1 ? ' for ' . (int) $it['price_qty'] : '');
    }

    /** A card, in the same shape as a person's post (Market::card), with the business's name. */
    public static function card(array $it, string $photo, string $biz): array
    {
        require_once __DIR__ . '/ItemKinds.php';
        $kd = ItemKinds::data((string) $it['kind'], $it['kind_data'] ?? null);
        $k = ItemKinds::key((string) $it['kind']);
        $when = $k === 'ticket' && !empty($kd['starts']) ? date('D M j, g:i a', strtotime((string) $kd['starts'])) : '';   // a ticket's date shows on its card
        return ['id' => (int) $it['id'], 'title' => (string) $it['title'], 'price' => self::price($it) . (self::price($it) !== 'Ask' ? ItemKinds::per($k, $kd) : ''), 'kind' => $k, 'kind_name' => ItemKinds::name($k), 'category' => (string) $it['category'],
            'place' => (string) $it['place_label'] . ($when !== '' ? ' · ' . $when : ''), 'ago' => $when !== '' ? '' : Market::ago((string) $it['published_at']), 'status' => 'active', 'status_label' => 'Published', 'photo' => $photo,
            'url' => '/market/item.php?id=' . (int) $it['id'], 'test' => false, 'biz' => $biz, 'entity' => (int) $it['entity_id'], 'when' => (string) $it['published_at']];
    }

    private static function covers(PDO $pdo, array $items): array
    {
        if (!$items) return [];
        require_once __DIR__ . '/Gallery.php';
        $out = [];
        foreach ($items as $it) {
            $ids = self::photoIds($pdo, (int) $it['entity_id'], (int) $it['product_id'], true);
            if (!$ids) continue;
            $st = $pdo->prepare('SELECT * FROM gallery_items WHERE id = ? AND ready = 1'); $st->execute([$ids[0]]);
            if ($r = $st->fetch(PDO::FETCH_ASSOC)) $out[(int) $it['id']] = Gallery::url($r, 'card');
        }
        return $out;
    }

    /** Published items (the same filters as people's posts: Market::browse), newest first, with their business's name. */
    public static function browse(PDO $pdo, array $f, int $limit = 48, int $entity = 0): array
    {
        if (!self::ready($pdo)) return [];
        $wa = self::browseWhere($f, $entity);
        if ($wa === null) return [];
        [$w, $a] = $wa;
        if (!empty($f['before_at'])) { $w[] = 'i.published_at < ?'; $a[] = (string) $f['before_at']; }
        [$sk, $order] = Market::sortSql($f, "CASE WHEN i.price_mode = 'ask' OR i.price_cents IS NULL THEN NULL ELSE i.price_cents END", 'i', 'i.published_at DESC, i.id DESC');
        $st = $pdo->prepare('SELECT i.*, e.name AS biz' . ($sk !== '' ? ", $sk AS sk" : '') . ' FROM market_items i JOIN entities e ON e.id = i.entity_id WHERE ' . implode(' AND ', $w) . ' ORDER BY ' . $order . ' LIMIT ' . (int) $limit);
        $st->execute($a);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $cov = self::covers($pdo, $rows);
        $near = ($f['sort'] ?? '') === 'near' && !empty($f['origin']);
        return array_map(fn ($it) => self::card($it, $cov[(int) $it['id']] ?? '', (string) $it['biz'])
            + ($sk !== '' ? ['sk' => $it['sk'] === null ? null : (float) $it['sk']] + ($near && $it['sk'] !== null ? ['miles' => (int) round((float) $it['sk'])] : []) : []), $rows);
    }

    /** The WHERE of published items buyers browse (browse and count share it); null when the filters rule them all out. */
    private static function browseWhere(array $f, int $entity = 0): ?array
    {
        $w = ["i.status = 'published'", 'e.is_suppressed = 0']; $a = [];
        if ($entity) { $w[] = 'i.entity_id = ?'; $a[] = $entity; }
        $key = (string) ($f['place'] ?? '');
        if (preg_match('/^(cluster|geo_hub|hub):(\d{1,9})$/', $key, $m)) {
            if ($m[1] === 'cluster') { $w[] = 'i.place_key = ?'; $a[] = $key; }
            else { $w[] = '(i.place_key = ? OR i.' . ($m[1] === 'geo_hub' ? 'geo_hub_id' : 'hub_id') . ' = ?)'; array_push($a, $key, (int) $m[2]); }
        }
        if (($c = trim((string) ($f['category'] ?? ''))) !== '') { $w[] = 'i.category = ?'; $a[] = $c; }
        // kind (decisions/0141 §5): one of the nine; people's posts' "item" and "service" stand for physical and service
        $k = (string) ($f['kind'] ?? '');
        require_once __DIR__ . '/ItemKinds.php';
        if ($k === 'item') $k = 'physical';
        if (isset(ItemKinds::KINDS[$k])) { $olds = array_keys(ItemKinds::LEGACY, $k, true); $w[] = 'i.kind IN (' . implode(',', array_fill(0, count($olds) + 1, '?')) . ')'; $a = array_merge($a, [$k], $olds); }
        if (!empty($f['regional'])) {   // Experiences & Regional Goods (0135's note, Jason 2026-11-27; the CEO's rulings, Tier 4, same date)
            $rk = self::REGIONAL_KINDS;
            if (isset(ItemKinds::KINDS[$k]) && !in_array($k, $rk, true)) return null;   // a kind the section doesn't hold
            foreach (self::REGIONAL_KINDS as $rkk) $rk = array_merge($rk, array_keys(ItemKinds::LEGACY, $rkk, true));
            $w[] = 'i.kind IN (' . implode(',', array_fill(0, count($rk), '?')) . ')'; $a = array_merge($a, $rk);
            $w[] = 'i.geo_hub_id IN (SELECT id FROM geo_hubs)';   // the business's home is one of our areas (its listing's town, regions.md)
        }
        if (!empty($f['free'])) return null;   // a business's items always have a price or Ask
        if (($mx = (int) ($f['max'] ?? 0)) > 0) { $w[] = "i.price_mode <> 'ask' AND i.price_cents <= ?"; $a[] = $mx * 100; }
        if (($q = trim((string) ($f['q'] ?? ''))) !== '') foreach (array_slice(preg_split('/\s+/', mb_substr($q, 0, 80)) ?: [], 0, 6) as $word) {
            if (mb_strlen($word) < 2) continue;
            $w[] = '(i.title LIKE ? OR i.about LIKE ? OR i.category LIKE ? OR e.name LIKE ?)'; array_push($a, "%$word%", "%$word%", "%$word%", "%$word%");
        }
        return [$w, $a];
    }

    /**
     * The kinds Experiences & Regional Goods holds (the CEO's ruling 5, 2026-11-27): event tickets, bookings and
     * reservations, harvest and perishables, made to order. Services wait for a "guided" flag and physical goods for a
     * seller's "made here" tick (both Q1), so our label is never untrue on a resale item.
     */
    public const REGIONAL_KINDS = ['ticket', 'booking', 'harvest', 'madetoorder'];

    /**
     * Our own label on an item: "Made in <the geo hub's name>" (0135's note: our label says only that, never heritage),
     * for a regional kind from a business whose home is one of our areas; null otherwise.
     */
    public static function madeIn(PDO $pdo, array $it): ?string
    {
        require_once __DIR__ . '/ItemKinds.php';
        if (!in_array(ItemKinds::key((string) ($it['kind'] ?? '')), self::REGIONAL_KINDS, true) || empty($it['geo_hub_id'])) return null;
        try { $st = $pdo->prepare('SELECT name FROM geo_hubs WHERE id = ?'); $st->execute([(int) $it['geo_hub_id']]); $n = trim((string) $st->fetchColumn()); }
        catch (Throwable) { return null; }
        return $n !== '' ? 'Made in ' . $n : null;
    }

    /** How many published items match (for the count beside the grid's heading). */
    public static function count(PDO $pdo, array $f): int
    {
        if (!self::ready($pdo) || ($wa = self::browseWhere($f)) === null) return 0;
        try { $st = $pdo->prepare('SELECT COUNT(*) FROM market_items i JOIN entities e ON e.id = i.entity_id WHERE ' . implode(' AND ', $wa[0])); $st->execute($wa[1]); return (int) $st->fetchColumn(); }
        catch (PDOException $x) { error_log('market biz count: ' . $x->getMessage()); return 0; }
    }

    /** Can this person see the item? Published, on a listing that's up; its team and our staff always. */
    public static function canSee(PDO $pdo, array $it, ?int $uid): bool
    {
        if ($uid && (Market::isStaff($pdo, $uid) || self::onTeam($pdo, $uid, (int) $it['entity_id']))) return true;
        if ($it['status'] !== 'published') return false;
        $en = self::entity($pdo, (int) $it['entity_id']);
        return $en && !(int) $en['is_suppressed'];
    }

    /** Is a Digital goods file still sold by a published item (so replacing it in the catalog keeps it until republished)? */
    public static function fileInUse(PDO $pdo, string $key): bool
    {
        $name = preg_replace('/[^a-f0-9]/', '', pathinfo($key, PATHINFO_FILENAME));   // its random name: the same however the JSON spells the path
        if ($name === '') return false;
        try { $st = $pdo->prepare('SELECT 1 FROM market_items WHERE kind_data LIKE ? LIMIT 1'); $st->execute(['%' . $name . '%']); return (bool) $st->fetchColumn(); }
        catch (PDOException) { return true; }
    }

    public static function onTeam(PDO $pdo, int $uid, int $e, string $perm = 'catalog', string $need = 'view'): bool
    {
        require_once __DIR__ . '/Team.php';
        try { return Team::can($pdo, $uid, $e, $perm, $need); } catch (Throwable) { return false; }
    }

    /** A buyer's message about an item, to the business's inbox (its team answers). */
    public static function message(PDO $pdo, int $uid, int $id, string $body, string $kind = 'message', int $qty = 0): void
    {
        $it = self::get($pdo, $id);
        if (!$it || $it['status'] !== 'published' || !self::canSee($pdo, $it, $uid)) throw new InvalidArgumentException('That item isn\'t available.');
        $b = self::buttonsFor($pdo, $it);
        if (!in_array($kind, $b, true)) throw new InvalidArgumentException('This business takes ' . ($b ? strtolower(implode(' or ', array_map(fn ($x) => self::BUTTONS[$x][0], $b))) : 'no contact here') . ' for this item.');
        if (self::onTeam($pdo, $uid, (int) $it['entity_id'])) throw new InvalidArgumentException('That\'s your own business\'s item.');
        $body = trim(strip_tags($body));
        if ($kind === 'message' && $body === '') throw new InvalidArgumentException('Write a message first.');
        require_once __DIR__ . '/BizMessages.php'; require_once __DIR__ . '/Marketing.php';
        $head = $kind === 'quote' ? 'Quote request from the Marketplace: ' . $it['title'] . ($qty > 1 ? ' × ' . $qty : '') . ' (' . self::price($it) . ')'
            : 'About “' . $it['title'] . '” on the Marketplace (' . self::price($it) . ')';
        BizMessages::personSend($pdo, $uid, (int) $it['entity_id'], $head . "\nhttps://traversence.com/market/item.php?id=" . $id . ($body !== '' ? "\n\n" . $body : '')
            . ($kind === 'quote' ? "\n\nMake the quote in your business portal: Quotes & invoices → New quote." : ''));
    }

    // ---- the 30-day check ----------------------------------------------------------------------------------------------

    /** Published items whose check is due: asked (the business's Pulse), or expired when asked at the last check. [asked, expired] */
    public static function tick(PDO $pdo): array
    {
        if (!self::ready($pdo)) return [0, 0];
        $now = self::now(); $asked = 0; $expired = 0; $touched = [];
        $next = date('Y-m-d H:i:s', strtotime('+' . (int) Market::setting($pdo, 'check_days') . ' days'));
        $st = $pdo->prepare("SELECT id, entity_id, asked_at FROM market_items WHERE status = 'published' AND check_due <= ? LIMIT 2000"); $st->execute([$now]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($r['asked_at'] !== null) { $pdo->prepare("UPDATE market_items SET status = 'expired', asked_at = NULL, updated_at = ? WHERE id = ?")->execute([$now, (int) $r['id']]); $expired++; $touched[(int) $r['entity_id']] = 1; }
            else { $pdo->prepare('UPDATE market_items SET asked_at = ?, check_due = ? WHERE id = ?')->execute([$now, $next, (int) $r['id']]); $asked++; }
        }
        foreach (array_keys($touched) as $e) self::syncAlbum($pdo, $e);
        return [$asked, $expired];
    }

    /** Published items waiting on "Still on offer?" for a business (its Pulse). */
    public static function asking(PDO $pdo, int $e): array
    {
        if (!self::ready($pdo)) return [];
        $st = $pdo->prepare("SELECT * FROM market_items WHERE entity_id = ? AND status = 'published' AND asked_at IS NOT NULL ORDER BY asked_at"); $st->execute([$e]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---- our team --------------------------------------------------------------------------------------------------------

    public static function staffAct(PDO $pdo, int $id, string $what, string $note = ''): string
    {
        $it = self::get($pdo, $id);
        if (!$it) throw new InvalidArgumentException('That item isn\'t here.');
        $note = mb_substr(trim(strip_tags($note)), 0, 300);
        if ($what === 'remove') {
            if ($note === '') throw new InvalidArgumentException('Say why, in a line the business will see.');
            $pdo->prepare("UPDATE market_items SET status = 'removed', staff_note = ?, asked_at = NULL, updated_at = ? WHERE id = ?")->execute(['Taken down by our team: ' . $note, self::now(), $id]);
            $msg = 'Taken down. The business sees why in its portal.';
        } elseif ($what === 'approve') {   // a held item (a hold word): our team looked, and it can go up
            if ($it['status'] !== 'held') throw new InvalidArgumentException('It isn\'t waiting for a look.');
            $pdo->prepare("UPDATE market_items SET status = 'published', rule_flag = ?, staff_note = '', updated_at = ? WHERE id = ?")->execute([(string) $it['rule_flag'] === 'review:recall' ? 'cleared:recall' : '', self::now(), $id]);
            $msg = 'Approved: it\'s up.';
        } elseif ($what === 'restore') {
            if ($it['status'] !== 'removed') throw new InvalidArgumentException('It isn\'t taken down.');
            $pdo->prepare("UPDATE market_items SET status = 'published', staff_note = '', updated_at = ? WHERE id = ?")->execute([self::now(), $id]);
            $msg = 'Back up.';
        } else throw new InvalidArgumentException('Unknown action.');
        self::syncAlbum($pdo, (int) $it['entity_id']);
        return $msg;
    }
}
