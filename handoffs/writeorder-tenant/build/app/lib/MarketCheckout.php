<?php
// app/lib/MarketCheckout.php — the Marketplace, step 4: buying from a business (decisions/0135, "Step 4 decided").
//
//   settings  a business's checkout, set up in its portal (E-commerce setup) and published like its items: the ways it
//             takes payment (card through its own Stripe or Square, Core and up; Zelle, bank transfer, check, pay at
//             pickup, on every plan, each with its own instructions), its sales tax rate, its shipping and pickup
//             options, how long an unpaid order holds stock. Only these public parts leave its walled data.
//   cart      one per business per buyer (each business is paid through its own gateway); Buy now is a cart of one.
//   orders    our ledger (market_orders): pending (card, being paid; stock held 30 minutes) · awaiting (a manual
//             method; stock held the business's days) · paid · cancelled · expired · refunded. The business's own copy is
//             an order in its walled Orders (BizQuotes), written by the server through the same gate as its portal:
//             a manual order when placed, a card order only once the gateway confirms it's paid.
//   paid      a card payment is paid only when the gateway itself says so (its webhook, checked first, or our question
//             to it), never because the buyer came back to the page. Each webhook event is handled once.
//   stock     an item with "Track stock": available = stock − what's held by pending and awaiting orders; paid takes it
//             off; at 0 it's Sold out.
// The totals follow the business's own invoices exactly (BizQuotes::sums): tax on taxable lines at its rate, shipping
// untaxed, so the order and its invoice always agree.
declare(strict_types=1);

require_once __DIR__ . '/MarketBiz.php';
require_once __DIR__ . '/MarketPay.php';
require_once __DIR__ . '/LegalTexts.php';
require_once __DIR__ . '/Acceptance.php';
require_once __DIR__ . '/KindCheckout.php';

final class MarketCheckout
{
    public const METHODS = ['zelle' => 'Zelle', 'bank' => 'Bank transfer', 'check' => 'Check', 'pickup' => 'Pay when you pick up', 'other' => 'Another way'];
    /** a method's kind in the business's invoices (BizQuotes::METHODS) */
    private const QUOTE_METHOD = ['zelle' => 'zelle', 'bank' => 'bank', 'check' => 'check', 'pickup' => 'cash', 'other' => 'other', 'card' => 'card'];
    public const STATUSES = ['pending' => 'Paying', 'awaiting' => 'Waiting for payment', 'paid' => 'Paid', 'cancelled' => 'Cancelled', 'expired' => 'Expired', 'refunded' => 'Refunded'];
    public const CARD_HOLD_MIN = 35;   // a little past the gateway's own pay window (Stripe's session: 31 minutes), so nothing is paid for after release
    public const MAX_QTY = 999;

    public static function ready(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try { $pdo->query('SELECT 1 FROM market_orders LIMIT 0'); $pdo->query('SELECT track_stock, taxable FROM market_items LIMIT 0'); return $ok = true; } catch (PDOException) { return $ok = false; }
    }

    private static function now(): string { return date('Y-m-d H:i:s'); }

    public static function money(int $c): string { return '$' . number_format($c / 100, 2); }

    // ---- the connection to its own gateway -----------------------------------------------------------------------------

    public static function gateway(PDO $pdo, int $e): ?array
    {
        $st = $pdo->prepare('SELECT * FROM market_gateways WHERE entity_id = ? AND env = ?'); $st->execute([$e, MarketPay::env()]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function connect(PDO $pdo, int $e, int $uid, string $provider, array $d): void
    {
        $now = self::now();
        $pdo->prepare("INSERT INTO market_gateways (entity_id, env, provider, account_id, location_id, access_enc, refresh_enc, expires_at, status, last_error, connected_by, connected_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'connected', '', ?, ?, ?) ON DUPLICATE KEY UPDATE provider = VALUES(provider), account_id = VALUES(account_id), location_id = VALUES(location_id),
            access_enc = VALUES(access_enc), refresh_enc = VALUES(refresh_enc), expires_at = VALUES(expires_at), status = 'connected', last_error = '', connected_by = VALUES(connected_by),
            connected_at = VALUES(connected_at), updated_at = VALUES(updated_at)")
            ->execute([$e, MarketPay::env(), $provider, $d['account_id'], $d['location_id'], $d['access'] !== '' ? MarketPay::seal($d['access']) : null, $d['refresh'] !== '' ? MarketPay::seal($d['refresh']) : null,
                $d['expires_at'], $uid ?: null, $now, $now]);
    }

    public static function disconnect(PDO $pdo, int $e): void
    {
        $g = self::gateway($pdo, $e);
        if (!$g) return;
        MarketPay::revoke($g);
        $pdo->prepare("UPDATE market_gateways SET status = 'disconnected', access_enc = NULL, refresh_enc = NULL, updated_at = ? WHERE entity_id = ? AND env = ?")->execute([self::now(), $e, MarketPay::env()]);
    }

    /** The gateway's own side ended it (a revoked authorization): kept as broken, so the business is told. */
    public static function broken(PDO $pdo, string $provider, string $account, string $why): void
    {
        $pdo->prepare("UPDATE market_gateways SET status = 'broken', last_error = ?, updated_at = ? WHERE provider = ? AND account_id = ? AND env = ?")->execute([mb_substr($why, 0, 300), self::now(), $provider, $account, MarketPay::env()]);
    }

    /** Square's access token, refreshed first when it's within a week of expiring. */
    public static function access(PDO $pdo, array $g): string
    {
        if ($g['provider'] !== 'square') return '';
        if ($g['expires_at'] && strtotime((string) $g['expires_at']) < strtotime('+7 days')) {
            [$a, $r, $exp] = MarketPay::refreshSquare(MarketPay::unseal($g['refresh_enc']));
            $pdo->prepare('UPDATE market_gateways SET access_enc = ?, refresh_enc = ?, expires_at = ?, updated_at = ? WHERE entity_id = ? AND env = ?')
                ->execute([$a !== '' ? MarketPay::seal($a) : $g['access_enc'], MarketPay::seal($r), $exp, self::now(), (int) $g['entity_id'], (string) $g['env']]);
            return $a !== '' ? $a : MarketPay::unseal($g['access_enc']);
        }
        return MarketPay::unseal($g['access_enc']);
    }

    /**
     * How the business's own Stripe or Square account stands (MarketPay::accountStatus), kept on its gateway row and asked
     * again when older than $maxAge seconds (the daily check asks every day; E-commerce setup and the Trust page sooner).
     * null when nothing is connected. ['ok', 'charges', 'payouts', 'due', 'note', 'link', 'checked', 'error'].
     */
    public static function payStatus(PDO $pdo, int $e, int $maxAge = 86400): ?array
    {
        $g = self::gateway($pdo, $e);
        if (!$g || $g['status'] !== 'connected') return null;
        $have = array_key_exists('acct_json', $g);   // the database update adds the columns (2027-02-01_gateway_status.sql)
        $old = $have ? json_decode((string) ($g['acct_json'] ?? ''), true) : null;
        if (is_array($old) && ($old['acct'] ?? '') === (string) $g['account_id'] && $g['acct_checked_at'] && strtotime((string) $g['acct_checked_at']) > time() - $maxAge) return $old + ['checked' => (string) $g['acct_checked_at'], 'error' => ''];
        try {
            $st = MarketPay::accountStatus($g, $g['provider'] === 'square' ? self::access($pdo, $g) : '');
            $st['acct'] = (string) $g['account_id'];   // a reconnected, different account is asked afresh
        } catch (Throwable $x) {
            error_log('market pay status ' . $e . ': ' . $x->getMessage());
            return (is_array($old) && ($old['acct'] ?? '') === (string) $g['account_id'] ? $old : ['ok' => false, 'charges' => false, 'payouts' => false, 'due' => [], 'note' => '', 'link' => ''])
                + ['checked' => (string) ($g['acct_checked_at'] ?? ''), 'error' => (MarketPay::PROVIDERS[$g['provider']] ?? 'The gateway') . ' didn\'t answer just now, so this is from the last check.'];
        }
        $now = self::now();
        if ($have) $pdo->prepare('UPDATE market_gateways SET acct_json = ?, acct_checked_at = ? WHERE entity_id = ? AND env = ?')->execute([json_encode($st), $now, $e, MarketPay::env()]);
        return $st + ['checked' => $now, 'error' => ''];
    }

    // ---- the business's checkout settings -----------------------------------------------------------------------------

    public static function defaults(): array
    {
        return ['methods' => [], 'card' => false, 'tax_milli' => 0, 'ship' => [['id' => 'pickup', 'name' => 'Pick up', 'price_cents' => 0, 'pickup' => true]], 'hold_days' => 3, 'pickup_note' => ''];
    }

    public static function settings(PDO $pdo, int $e): array
    {
        $st = $pdo->prepare('SELECT settings, live FROM market_checkout WHERE entity_id = ?'); $st->execute([$e]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        $s = $r ? (json_decode((string) $r['settings'], true) ?: []) + self::defaults() : self::defaults();
        $s['live'] = $r ? (bool) $r['live'] : false;
        return $s;
    }

    /** Card checkout: the business switched it on, its plan opens it, and its gateway is connected. [yes, why not] */
    public static function cardOk(PDO $pdo, int $e, ?array $s = null): array
    {
        [$plan, $start] = Plan::allows($pdo, MarketBiz::tier($pdo, $e), 'card_checkout');
        if (!$plan) return [false, Plan::upgradeNote($start)];
        $g = self::gateway($pdo, $e);
        if (!$g || $g['status'] !== 'connected') return [false, $g && $g['status'] === 'broken' ? 'The connection to ' . MarketPay::PROVIDERS[$g['provider']] . ' stopped: connect again' : 'Connect Stripe or Square first'];
        $s ??= self::settings($pdo, $e);
        return empty($s['card']) ? [false, 'Card payment is switched off in your checkout'] : [true, ''];
    }

    /** Saves the business's checkout (E-commerce setup). Validated; what it can't use yet is left off with the reason. */
    public static function saveSettings(PDO $pdo, int $e, int $uid, array $in): array
    {
        $s = self::settings($pdo, $e);
        $methods = [];
        foreach (self::METHODS as $k => $label) {
            $m = (array) (($in['methods'] ?? [])[$k] ?? []);
            if (empty($m['on'])) continue;
            $text = mb_substr(trim(strip_tags((string) ($m['text'] ?? ''))), 0, 400);
            if ($text === '' && $k !== 'pickup') throw new InvalidArgumentException($label . ': say how to pay (for Zelle, your Zelle email or phone and the name it shows).');
            $methods[$k] = ['text' => $text, 'label' => $k === 'other' ? mb_substr(trim(strip_tags((string) ($m['label'] ?? 'Another way'))), 0, 40) ?: 'Another way' : $label];
        }
        $pct = str_replace([',', '%', ' '], '', (string) ($in['tax'] ?? '0'));
        if ($pct !== '' && !preg_match('/^\d{1,2}(\.\d{1,3})?$/', $pct)) throw new InvalidArgumentException('Write the sales tax as a percent, like 8.35.');
        $ship = [];
        foreach ((array) ($in['ship'] ?? []) as $i => $o) {
            $name = mb_substr(trim(strip_tags((string) ($o['name'] ?? ''))), 0, 60);
            if ($name === '') continue;
            $raw = str_replace([',', '$', ' '], '', (string) ($o['price'] ?? '0'));
            if ($raw !== '' && !preg_match('/^\d+(\.\d{1,2})?$/', $raw)) throw new InvalidArgumentException($name . ': write its price in dollars.');
            $ship[] = ['id' => 's' . $i, 'name' => $name, 'price_cents' => (int) round(((float) ($raw ?: 0)) * 100), 'pickup' => !empty($o['pickup'])];
            if (count($ship) >= 8) break;
        }
        if (!$ship) throw new InvalidArgumentException('Add at least one way to get it: pickup, delivery or shipping.');
        $s = ['methods' => $methods, 'card' => !empty($in['card']), 'tax_milli' => (int) round(((float) ($pct ?: 0)) * 1000), 'ship' => $ship,
              'hold_days' => max(1, min(14, (int) ($in['hold_days'] ?? 3))), 'pickup_note' => mb_substr(trim(strip_tags((string) ($in['pickup_note'] ?? ''))), 0, 300)];
        $pdo->prepare('INSERT INTO market_checkout (entity_id, settings, live, updated_at) VALUES (?, ?, 0, ?) ON DUPLICATE KEY UPDATE settings = VALUES(settings), updated_at = VALUES(updated_at)')
            ->execute([$e, json_encode($s, JSON_UNESCAPED_UNICODE), self::now()]);
        return $s;
    }

    /** Go live (or pause): buyers see Buy now and Add to cart on its items that have them. */
    /**
     * The Seller Agreement for this business (decisions/0142 §1): the version shown now, its last acceptance, and whether
     * it stands. A business accepts it once; a new published version marked "needs acceptance" asks again ('new').
     */
    public static function seller(PDO $pdo, int $e): array
    {
        [$v, $text] = LegalTexts::current($pdo, 'seller');
        $last = Acceptance::forEntity($pdo, $e, 'seller_agreement', 1)[0] ?? null;
        $live = null; try { $live = LegalTexts::live($pdo, 'seller'); } catch (Throwable) {}
        $new = $last && $live && !empty($live['needs']) && (string) $last['version'] !== (string) $live['label'];
        return ['version' => $v, 'text' => $text, 'last' => $last, 'new' => $new, 'ok' => $last !== null && !$new];
    }

    /** Accepting the Seller Agreement on the business's behalf: a record (Acceptance). */
    public static function acceptSeller(PDO $pdo, int $e, int $uid): array
    {
        if (!Acceptance::ready($pdo)) throw new RuntimeException('Accepting opens once the database update has run (within the hour).');
        $s = self::seller($pdo, $e);
        if ($s['ok']) return $s;
        Acceptance::record($pdo, 'seller_agreement', $uid, $e, null, 'seller', $s['version'], $s['text'], ['business' => self::bizName($pdo, $e)]);
        return self::seller($pdo, $e);
    }

    public static function setLive(PDO $pdo, int $e, int $uid, bool $on): void
    {
        $s = self::settings($pdo, $e);
        if ($on && Acceptance::ready($pdo) && !self::seller($pdo, $e)['ok']) throw new InvalidArgumentException('Accept the Seller Agreement first (above Go live).');
        if ($on && is_file(__DIR__ . '/Inform.php')) { require_once __DIR__ . '/Inform.php'; [$ok, $why] = Inform::mayCharge($pdo, $e); if (!$ok) throw new InvalidArgumentException('Confirm your seller details first (Trust score → INFORM Act details): the law asks for them before selling resumes.'); }
        if ($on) {
            $card = self::cardOk($pdo, $e, $s)[0];
            if (!$card && !$s['methods']) throw new InvalidArgumentException('Choose at least one way to be paid first: card (Core and up, with Stripe or Square connected) or a direct way like Zelle.');
        }
        $pdo->prepare('INSERT INTO market_checkout (entity_id, settings, live, published_by, published_at, updated_at) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE live = VALUES(live), published_by = VALUES(published_by), published_at = VALUES(published_at), updated_at = VALUES(updated_at)')
            ->execute([$e, json_encode(array_diff_key($s, ['live' => 1]), JSON_UNESCAPED_UNICODE), $on ? 1 : 0, $uid ?: null, self::now(), self::now()]);
    }

    /** The ways this buyer can pay this business now: ['card' => label] + its direct methods. */
    public static function payWays(PDO $pdo, int $e, array $lines = []): array
    {
        $s = self::settings($pdo, $e);
        $out = [];
        $restricted = false;   // a restricted item (decisions/0142 §4): a direct way of paying, not card
        if ($lines && is_file(__DIR__ . '/MarketRules.php')) {
            require_once __DIR__ . '/MarketRules.php';
            foreach ($lines as $l) if (!empty($l['item_id']) && ($it = MarketBiz::get($pdo, (int) $l['item_id'])) && !MarketRules::cardOk($it)) $restricted = true;
        }
        if (!$restricted && self::cardOk($pdo, $e, $s)[0]) $out['card'] = 'Card, Apple Pay or Google Pay';
        foreach ($s['methods'] as $k => $m) $out[$k] = (string) $m['label'];
        return $out;
    }

    // ---- items: can it be bought, and how many are left ----------------------------------------------------------------

    /** Can this item be bought now? [yes, why not] */
    public static function buyable(PDO $pdo, array $it): array
    {
        if ($it['status'] !== 'published') return [false, 'It isn\'t up.'];
        $s = self::settings($pdo, (int) $it['entity_id']);
        if (!$s['live']) return [false, 'This business isn\'t taking orders here yet.'];
        if (!in_array('buy', MarketBiz::buttonsFor($pdo, $it), true)) return [false, 'Ask the business about this one.'];
        if ($it['price_mode'] !== 'fixed' || $it['price_cents'] === null) return [false, $it['price_mode'] === 'from' ? 'Its price depends on options: request a quote.' : 'It has no set price: ask the business.'];
        [$kOk, $kWhy] = KindCheckout::buyable($pdo, $it);   // its kind (decisions/0141 §5): a quote, a date passed, a gateway…
        if (!$kOk) return [false, $kWhy];
        if (self::available($pdo, $it) === 0) return [false, 'Sold out.'];
        return [true, ''];
    }

    /** How many can be bought now (null: not tracked). */
    public static function available(PDO $pdo, array $it): ?int
    {
        $seats = KindCheckout::seatsLeft($pdo, $it);   // a ticket: its capacity, less seats sold and being paid for
        if ($seats !== null) return $seats;
        if (!(int) ($it['track_stock'] ?? 0) || in_array(ItemKinds::key((string) ($it['kind'] ?? '')), ['booking', 'rental', 'service', 'digital', 'subscription'], true)) return null;   // dates, time and files aren't counted stock
        return max(0, (int) $it['stock'] - self::held($pdo, (int) $it['id']));
    }

    /** Held by orders still being paid (pending) or waiting for a direct payment (awaiting), within their hold. */
    private static function held(PDO $pdo, int $itemId, int $except = 0): int
    {
        $st = $pdo->prepare("SELECT order_lines FROM market_orders WHERE status IN ('pending','awaiting') AND hold_until > ? AND id <> ? AND order_lines LIKE ?");
        $st->execute([self::now(), $except, '%"item_id":' . $itemId . ',%']);
        $n = 0;
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $j) foreach ((array) json_decode((string) $j, true) as $l) if ((int) $l['item_id'] === $itemId) $n += (int) $l['qty'];
        return $n;
    }

    /** The business sets an item's stock (its portal's Marketplace page). */
    public static function setStock(PDO $pdo, int $e, int $product, bool $track, int $stock): void
    {
        $pdo->prepare('UPDATE market_items SET track_stock = ?, stock = ?, updated_at = ? WHERE entity_id = ? AND product_id = ?')->execute([$track ? 1 : 0, max(0, min(1000000, $stock)), self::now(), $e, $product]);
    }

    // ---- the cart: one per business ------------------------------------------------------------------------------------

    public static function add(PDO $pdo, int $uid, int $itemId, int $qty): void
    {
        $it = MarketBiz::get($pdo, $itemId);
        if (!$it) throw new InvalidArgumentException('That item isn\'t available.');
        [$ok, $why] = self::buyable($pdo, $it);
        if (!$ok) throw new InvalidArgumentException($why);
        if (MarketBiz::onTeam($pdo, $uid, (int) $it['entity_id'])) throw new InvalidArgumentException('That\'s your own business\'s item.');
        if (in_array(ItemKinds::key((string) $it['kind']), KindCheckout::ALONE, true)) throw new InvalidArgumentException('Use Buy now for this one: ' . (ItemKinds::key((string) $it['kind']) === 'subscription' ? 'it\'s a subscription.' : 'it needs its dates.'));
        $qty = max(1, min(self::MAX_QTY, $qty));
        $pdo->prepare('INSERT INTO market_cart (user_id, item_id, entity_id, qty, added_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE qty = LEAST(?, qty + VALUES(qty))')
            ->execute([$uid, $itemId, (int) $it['entity_id'], $qty, self::now(), self::MAX_QTY]);
    }

    public static function setQty(PDO $pdo, int $uid, int $itemId, int $qty): void
    {
        if ($qty <= 0) { $pdo->prepare('DELETE FROM market_cart WHERE user_id = ? AND item_id = ?')->execute([$uid, $itemId]); return; }
        $pdo->prepare('UPDATE market_cart SET qty = ? WHERE user_id = ? AND item_id = ?')->execute([min(self::MAX_QTY, $qty), $uid, $itemId]);
    }

    /** The buyer's carts: entity id => [name, lines (with what's wrong with each now), sub]. */
    public static function carts(PDO $pdo, int $uid): array
    {
        $st = $pdo->prepare('SELECT c.qty, i.*, e.name AS biz FROM market_cart c JOIN market_items i ON i.id = c.item_id JOIN entities e ON e.id = c.entity_id WHERE c.user_id = ? ORDER BY c.added_at');
        $st->execute([$uid]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $e = (int) $r['entity_id'];
            $out[$e] ??= ['entity_id' => $e, 'name' => (string) $r['biz'], 'lines' => [], 'sub' => 0];
            [$ok, $why] = self::buyable($pdo, $r);
            $left = self::available($pdo, $r);
            if ($ok && $left !== null && $left < (int) $r['qty']) { $ok = false; $why = 'Only ' . $left . ' left.'; }
            $out[$e]['lines'][] = ['kind' => ItemKinds::key((string) $r['kind']), 'item_id' => (int) $r['id'], 'product_id' => (int) $r['product_id'], 'title' => (string) $r['title'] . ((int) $r['price_qty'] > 1 ? ' (pack of ' . (int) $r['price_qty'] . ')' : ''),
                'qty' => (int) $r['qty'], 'unit_cents' => (int) $r['price_cents'], 'taxable' => (int) $r['taxable'], 'ok' => $ok, 'why' => $why, 'left' => $left];
            if ($ok) $out[$e]['sub'] += (int) $r['qty'] * (int) $r['price_cents'];
        }
        return $out;
    }

    public static function cartCount(PDO $pdo, int $uid): int
    {
        if (!self::ready($pdo)) return 0;
        $st = $pdo->prepare('SELECT COALESCE(SUM(qty), 0) FROM market_cart WHERE user_id = ?'); $st->execute([$uid]);
        return (int) $st->fetchColumn();
    }

    // ---- totals and placing an order -----------------------------------------------------------------------------------

    /** The order's money, exactly as the business's invoice will add it up (BizQuotes::sums). */
    public static function totals(array $lines, int $shipCents, int $taxMilli): array
    {
        $sub = 0; $taxable = 0;
        foreach ($lines as $l) { $t = (int) $l['qty'] * (int) $l['unit_cents']; $sub += $t; if ((int) $l['taxable']) $taxable += $t; }
        $tax = (int) round($taxable * $taxMilli / 100000);
        return ['sub' => $sub, 'ship' => $shipCents, 'tax' => $tax, 'total' => $sub + $shipCents + $tax];
    }

    /**
     * Places the buyer's order with one business: its cart, or Buy now ($in['item'], $in['qty']). $in: method (card or one of
     * its direct ways), ship (an option id), name, address (when shipped or delivered), note. Returns the order.
     */
    public static function place(PDO $pdo, int $uid, int $e, array $in): array
    {
        require_once __DIR__ . '/Standing.php'; Standing::guard($pdo, $uid);
        $s = self::settings($pdo, $e);
        if (!$s['live']) throw new InvalidArgumentException('This business isn\'t taking orders here yet.');
        if (MarketBiz::onTeam($pdo, $uid, $e)) throw new InvalidArgumentException('That\'s your own business.');
        if (!empty($in['item'])) {
            $it = MarketBiz::get($pdo, (int) $in['item']);
            if (!$it || (int) $it['entity_id'] !== $e) throw new InvalidArgumentException('That item isn\'t available.');
            [$ok, $why] = self::buyable($pdo, $it); if (!$ok) throw new InvalidArgumentException($why);
            $lines = [['item_id' => (int) $it['id'], 'product_id' => (int) $it['product_id'], 'title' => (string) $it['title'] . ((int) $it['price_qty'] > 1 ? ' (pack of ' . (int) $it['price_qty'] . ')' : ''),
                'qty' => max(1, min(self::MAX_QTY, (int) ($in['qty'] ?? 1))), 'unit_cents' => (int) $it['price_cents'], 'taxable' => (int) $it['taxable'], 'ok' => true, 'left' => self::available($pdo, $it)]];
            $fromCart = false;
        } else {
            $cart = self::carts($pdo, $uid)[$e] ?? null;
            if (!$cart) throw new InvalidArgumentException('Your cart for this business is empty.');
            $lines = $cart['lines']; $fromCart = true;
        }
        foreach ($lines as $l) {
            if (!$l['ok']) throw new InvalidArgumentException($l['title'] . ': ' . ($l['why'] ?? 'not available'));
            if ($l['left'] !== null && $l['qty'] > $l['left']) throw new InvalidArgumentException($l['title'] . ': only ' . $l['left'] . ' left.');
        }
        // by kind (decisions/0141 §5): dates, windows, specs; fees, lodging tax, deposits; what's paid now
        $gw = self::gateway($pdo, $e);
        $kp = KindCheckout::prepare($pdo, $lines, (array) ($in['kx'] ?? []), true, $gw && $gw['status'] === 'connected' ? (string) $gw['provider'] : '');
        $lines = array_merge($kp['lines'], $kp['extra']);
        $ways = self::payWays($pdo, $e, $lines);
        $method = (string) ($in['method'] ?? '');
        if (!isset($ways[$method])) throw new InvalidArgumentException('Choose how to pay.');
        if ($kp['mode'] === 'subscription' && $method !== 'card') throw new InvalidArgumentException('A subscription is paid by card, each ' . ($kp['lines'][0]['kx']['interval'] ?? 'month') . '.');
        if ($kp['ships']) {
            $opt = null; foreach ($s['ship'] as $o) if ($o['id'] === (string) ($in['ship'] ?? '')) $opt = $o;
            if (!$opt) throw new InvalidArgumentException('Choose pickup, delivery or shipping.');
            if (!empty($kp['pickup_only']) && empty($opt['pickup'])) throw new InvalidArgumentException('Some of this is pickup only: choose pickup.');
        } else $opt = ['id' => 'none', 'name' => KindCheckout::getting($kp), 'price_cents' => 0, 'pickup' => true];   // nothing shipped: a ticket, a download, a stay…
        $name = mb_substr(trim(strip_tags((string) ($in['name'] ?? ''))), 0, 120);
        $addr = mb_substr(trim(strip_tags((string) ($in['address'] ?? ''))), 0, 400);
        if (empty($opt['pickup']) && ($name === '' || mb_strlen($addr) < 8)) throw new InvalidArgumentException('Add the name and full address it goes to.');
        $st = $pdo->prepare('SELECT email, display_name FROM users WHERE id = ?'); $st->execute([$uid]); $u = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $t = self::totals($lines, (int) $opt['price_cents'], (int) $s['tax_milli']);
        if ($t['total'] < 50) throw new InvalidArgumentException('The order is too small to pay online.');
        $card = $method === 'card';
        $now = self::now();
        $hold = date('Y-m-d H:i:s', $card ? strtotime('+' . self::CARD_HOLD_MIN . ' minutes') : strtotime('+' . (int) $s['hold_days'] . ' days'));
        $keep = array_map(fn ($l) => array_intersect_key($l, array_flip(['item_id', 'product_id', 'title', 'qty', 'unit_cents', 'taxable', 'kind', 'kx', 'extra'])), $lines);
        $g = $card ? self::gateway($pdo, $e) : null;
        $pdo->prepare('INSERT INTO market_orders (token, entity_id, buyer_id, email, name, order_lines, ship_name, ship_address, subtotal_cents, ship_cents, tax_cents, total_cents, method, provider, env, status, hold_until, note, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([bin2hex(random_bytes(16)), $e, $uid, (string) ($u['email'] ?? ''), $name !== '' ? $name : (string) ($u['display_name'] ?? ''), json_encode($keep, JSON_UNESCAPED_UNICODE),
                (string) $opt['name'], $addr, $t['sub'], $t['ship'], $t['tax'], $t['total'], $method, $g ? (string) $g['provider'] : '', MarketPay::env(), $card ? 'pending' : 'awaiting', $hold,
                mb_substr(trim(strip_tags((string) ($in['note'] ?? ''))), 0, 600), $now, $now]);
        $o = self::get($pdo, (int) $pdo->lastInsertId());
        if (KindCheckout::ready($pdo) && (!empty($kp['balance']) || $kp['mode'] === 'subscription' || $kp['hold'])) {   // a deposit now (the rest when ready), a subscription, a rental's hold
            $pdo->prepare('UPDATE market_orders SET pay_now_cents = ?, pay_mode = ? WHERE id = ?')->execute([!empty($kp['balance']) ? (int) $t['total'] - (int) $kp['balance'] : null, $kp['mode'] === 'subscription' ? 'subscription' : ($kp['hold'] ? 'hold' : 'payment'), (int) $o['id']]);
        }
        if ($kp['house_rules'] !== '') Acceptance::record($pdo, 'house_rules', $uid, $e, (int) $o['id'], 'house_rules', 'item-' . (int) ($kp['lines'][0]['item_id'] ?? 0), $kp['house_rules'], ['rules' => $kp['house_rules'], 'dates' => $kp['lines'][0]['kx'] ?? []]);   // the guest agreed to them (decisions/0142)
        self::agreed($pdo, $o, $uid);   // the store policies it was placed under, and the record (decisions/0142 §2)
        $o = self::get($pdo, (int) $o['id']) ?: $o;
        if ($fromCart) $pdo->prepare('DELETE FROM market_cart WHERE user_id = ? AND entity_id = ?')->execute([$uid, $e]);
        if (!$card) {   // a direct way: the business's order now, waiting for its payment; the buyer gets the instructions
            $copy = '';   // the business's own order: a failure is noted and said, never the buyer's error (the order is placed)
            try { self::writeOrder($pdo, $o, null); } catch (Throwable $x) { error_log('market order ' . $o['id'] . ' to the business: ' . $x->getMessage()); $copy = 'no'; }
            self::notify($pdo, $o, 'New order #' . $o['id'] . ' from the Marketplace: ' . self::money((int) $o['total_cents']) . ', paying by ' . strtolower($ways[$method]) . '. '
                . ($copy === '' ? 'Mark it paid in Quotes & invoices when it arrives.' : self::copyLater()));
            self::mailBuyer($pdo, $o, 'Your order from ' . self::bizName($pdo, $e) . ': how to pay', self::instructions($pdo, $o));
        }
        return self::get($pdo, (int) $o['id']);
    }

    /** The checkout line and the business's store policies as shown at checkout. [line, snapshot, version] */
    public static function agreement(PDO $pdo, int $e): array
    {
        require_once __DIR__ . '/StorePolicies.php';
        $name = self::bizName($pdo, $e);
        $snap = StorePolicies::snapshot($pdo, $e, null, $name);
        $c = LegalTexts::clause($pdo, 'checkout', 'line');
        [$tv] = LegalTexts::current($pdo, 'terms');
        $snap['terms'] = $tv;
        return [LegalTexts::fill((string) $c['body'], LegalTexts::identity() + ['business' => $name]), $snap, mb_substr($c['version'] . '|' . $tv . '|s' . $snap['v'], 0, 40)];
    }

    /** Placing an order: it keeps its own copy of the store policies, and the buyer's acceptance is recorded. Never stops an order. */
    private static function agreed(PDO $pdo, array $o, int $uid): void
    {
        try {
            [$line, $snap, $v] = self::agreement($pdo, (int) $o['entity_id']);
            $snap['line'] = $line;   // the exact line above Place order, kept with the order (its dispute packet)
            if (StorePolicies::ready($pdo)) $pdo->prepare('UPDATE market_orders SET policy_json = ? WHERE id = ?')->execute([json_encode($snap, JSON_UNESCAPED_UNICODE), (int) $o['id']]);
            Acceptance::record($pdo, 'checkout', $uid, (int) $o['entity_id'], (int) $o['id'], 'checkout', $v, $line . "\n\n" . StorePolicies::plain($snap), $snap);
        } catch (Throwable $x) { error_log('market order ' . $o['id'] . ' acceptance: ' . $x->getMessage()); }
    }

    /**
     * The dispute packet (decisions/0141 §6): an order with its receipt, the policies it was placed under and what the
     * buyer accepted, as one HTML file the business saves (or prints to PDF) and sends its payment company.
     */
    public static function packet(PDO $pdo, array $o): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $biz = self::bizName($pdo, (int) $o['entity_id']);
        $snap = json_decode((string) ($o['policy_json'] ?? ''), true);
        $recs = Acceptance::forOrder($pdo, (int) $o['id']);
        $row = fn (string $k, string $v) => '<tr><th>' . $e($k) . '</th><td>' . $e($v) . '</td></tr>';
        $h = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Order ' . (int) $o['id'] . ' · ' . $e($biz) . ' · dispute packet</title>'
            . '<style>body{font:15px/1.5 Georgia,serif;color:#222;max-width:46rem;margin:1.5rem auto;padding:0 1rem}h1{font-size:1.4rem;margin:0 0 .2rem}h2{font-size:1.05rem;margin:1.4rem 0 .3rem;border-bottom:1px solid #ccc}'
            . 'table{border-collapse:collapse;width:100%;font-size:.9rem}th,td{text-align:left;vertical-align:top;padding:.3rem .4rem;border-bottom:1px solid #eee}th{width:13rem;color:#555;font-weight:600}.n{color:#555;font-size:.85rem}code{font-size:.78rem;word-break:break-all}</style></head><body>'
            . '<h1>Order ' . (int) $o['id'] . ' from ' . $e($biz) . '</h1><p class="n">Dispute packet, made ' . $e(date('M j, Y g:i a T')) . ' on Traversence. Save it as PDF (Print, then Save as PDF) and attach it to your answer to the dispute.</p>'
            . '<h2>The order</h2><table>' . $row('Placed', date('M j, Y g:i a T', strtotime((string) $o['created_at']))) . $row('Buyer', trim($o['name'] . ' <' . $o['email'] . '>'))
            . $row('Getting it', $o['ship_name'] . ($o['ship_address'] !== '' ? ': ' . $o['ship_address'] : '')) . $row('Paid by', $o['method'] === 'card' ? 'Card, through ' . $biz . '\'s ' . (MarketPay::PROVIDERS[$o['provider']] ?? $o['provider']) . ' account' : (self::METHODS[$o['method']] ?? $o['method']))
            . $row('Payment reference', (string) $o['provider_ref']) . $row('Status', self::STATUSES[$o['status']] ?? $o['status']) . ($o['note'] !== '' ? $row('Buyer\'s note', (string) $o['note']) : '') . '</table>'
            . '<h2>Receipt</h2><table>';
        foreach ((array) $o['lines'] as $l) $h .= $row($l['title'] . ' × ' . (int) $l['qty'], self::money((int) $l['qty'] * (int) $l['unit_cents']));
        $h .= $row('Getting it', self::money((int) $o['ship_cents'])) . $row('Sales tax', self::money((int) $o['tax_cents'])) . $row('Total', self::money((int) $o['total_cents'])) . '</table>';
        $h .= '<h2>Store policies the order was placed under</h2>';
        if (is_array($snap) && !empty($snap['lines'])) { $h .= '<table>'; foreach ($snap['lines'] as $l) $h .= $row((string) $l[0], (string) $l[1]); $h .= '</table><p class="n">Store policies version ' . (int) ($snap['v'] ?? 0) . '; Traversence Terms version ' . $e((string) ($snap['terms'] ?? '')) . '.</p>'; }
        else $h .= '<p class="n">This order was placed before store policies were kept with orders.</p>';
        $h .= '<h2>What the buyer accepted</h2>';
        if ($recs) {
            $h .= '<table>';
            foreach ($recs as $r) $h .= $row('Accepted', date('M j, Y g:i:s a T', strtotime((string) $r['accepted_at']))) . $row('By', 'The buyer (account #' . (int) $r['user_id'] . ')') . $row('What', 'Placing the order, under Traversence\'s Terms and the store policies above (record version ' . $r['version'] . ')')
                . $row('Fingerprint of the text shown', (string) $r['text_hash']) . $row('From', $r['ip'] . ' · ' . $r['user_agent']);
            $said = (string) (is_array($snap) && !empty($snap['line']) ? $snap['line'] : self::agreement($pdo, (int) $o['entity_id'])[0]);
            $h .= '</table><p class="n">The buyer tapped Place order below this line: "' . $e(trim((string) preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $said))) . '" The fingerprint (SHA-256) matches the exact text and policies they were shown; the record can\'t be changed.</p>';
        } else $h .= '<p class="n">No acceptance record: this order was placed before records were kept.</p>';
        return $h . '<p class="n">' . $e($biz) . ' is the seller; Traversence runs the Marketplace and isn\'t a party to the sale. Questions about this packet: support@traversence.com.</p></body></html>';
    }

    // ---- the setup's test purchase (E-commerce setup, step 5; Jason, 2026-11-02) ------------------------------------------
    // In sandbox only: someone on the business's team buys a $1 stand-in from it, through its own connected gateway, with
    // a test card, and sees the whole path work: the card form, the gateway's confirmation (webhook), the order in its
    // Quotes & invoices, the receipt. No product and no going live; the stand-in never shows on the Marketplace.

    public const TEST_NOTE = 'Test purchase from E-commerce setup';

    /** Can this person run a test purchase for this business now? [yes, why not] */
    public static function testAllowed(PDO $pdo, int $uid, int $e): array
    {
        if (!in_array(MarketPay::env(), ['sandbox', 'mock'], true)) return [false, 'Test purchases run only while payments are in test mode. Once live, buy one of your own items from a personal account, then refund it.'];
        if (!MarketBiz::onTeam($pdo, $uid, $e, 'ledger')) return [false, 'Only the business\'s team can run its test purchase.'];
        $g = self::gateway($pdo, $e);
        if (!$g || $g['status'] !== 'connected') return [false, 'Connect Stripe or Square first (step 1).'];
        return [true, ''];
    }

    /** The test order: one $1 stand-in, picked up, card, waiting for the payment. Returns the order. */
    public static function placeTest(PDO $pdo, int $uid, int $e): array
    {
        [$ok, $why] = self::testAllowed($pdo, $uid, $e);
        if (!$ok) throw new InvalidArgumentException($why);
        $g = self::gateway($pdo, $e);
        // an earlier test still waiting is closed, so only the newest one counts
        $pdo->prepare("UPDATE market_orders SET status = 'cancelled', updated_at = ? WHERE entity_id = ? AND status = 'pending' AND note LIKE ?")->execute([self::now(), $e, self::TEST_NOTE . '%']);
        $st = $pdo->prepare('SELECT email, display_name FROM users WHERE id = ?'); $st->execute([$uid]); $u = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $lines = [['item_id' => 0, 'product_id' => 0, 'title' => 'Test purchase (test card, no real money)', 'qty' => 1, 'unit_cents' => 100, 'taxable' => 0]];
        $now = self::now();
        $pdo->prepare('INSERT INTO market_orders (token, entity_id, buyer_id, email, name, order_lines, ship_name, ship_address, subtotal_cents, ship_cents, tax_cents, total_cents, method, provider, env, status, hold_until, note, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([bin2hex(random_bytes(16)), $e, $uid, (string) ($u['email'] ?? ''), (string) ($u['display_name'] ?? ''), json_encode($lines, JSON_UNESCAPED_UNICODE),
                'Pick up (a test: nothing is sent)', '', 100, 0, 0, 100, 'card', (string) $g['provider'], MarketPay::env(), 'pending',
                date('Y-m-d H:i:s', strtotime('+' . self::CARD_HOLD_MIN . ' minutes')), self::TEST_NOTE, $now, $now]);
        $o = self::get($pdo, (int) $pdo->lastInsertId());
        self::agreed($pdo, $o, $uid);   // a test purchase records its acceptance too, so the whole path can be checked
        return $o;
    }

    /** The test's card form didn't open: the order is closed with the gateway's reason, which step 5 then shows. */
    public static function testFailed(PDO $pdo, int $id, string $why): void
    {
        $pdo->prepare("UPDATE market_orders SET status = 'cancelled', note = ?, updated_at = ? WHERE id = ? AND note LIKE ?")
            ->execute([self::TEST_NOTE . ' · failed: ' . mb_substr($why, 0, 300), self::now(), $id, self::TEST_NOTE . '%']);
    }

    /** The business's latest test purchase, or null. */
    public static function lastTest(PDO $pdo, int $e): ?array
    {
        $st = $pdo->prepare('SELECT * FROM market_orders WHERE entity_id = ? AND note LIKE ? ORDER BY id DESC LIMIT 1');
        $st->execute([$e, self::TEST_NOTE . '%']);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** The buyer cancels an order not paid yet: its items are released, and a direct-pay order's business is told; a stay or rental's always is (Bookings::changed). */
    public static function cancel(PDO $pdo, int $uid, array $o): void
    {
        if ((int) $o['buyer_id'] !== $uid) throw new InvalidArgumentException('That order isn\'t yours.');
        $st = $pdo->prepare("UPDATE market_orders SET status = 'cancelled', updated_at = ? WHERE id = ? AND status IN ('pending','awaiting')");
        $st->execute([self::now(), (int) $o['id']]);
        if (!$st->rowCount()) throw new InvalidArgumentException('It can\'t be cancelled now.');
        require_once __DIR__ . '/Bookings.php';
        $booking = Bookings::of($o) !== null;   // a stay or a rental: the team is told as a booking (biz.booking, decisions/0172 §2.5), once
        if ($o['biz_quote_id']) self::notify($pdo, $o, 'Order #' . (int) $o['id'] . ' was cancelled by the buyer before paying. Void it in Quotes & invoices.', $booking ? '' : 'biz.order');
        if ($booking) { try { Bookings::changed($pdo, $o, 'customer', 'cancelled before paying; the dates are free again'); } catch (Throwable $x) { error_log('booking cancelled notify: ' . $x->getMessage()); } }
    }

    public static function get(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare('SELECT * FROM market_orders WHERE id = ?'); $st->execute([$id]);
        $o = $st->fetch(PDO::FETCH_ASSOC);
        if (!$o) return null;
        $o['lines'] = json_decode((string) $o['order_lines'], true) ?: [];
        return $o;
    }

    public static function byToken(PDO $pdo, string $tok): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $tok)) return null;
        $st = $pdo->prepare('SELECT id FROM market_orders WHERE token = ?'); $st->execute([$tok]);
        $id = (int) $st->fetchColumn();
        return $id ? self::get($pdo, $id) : null;
    }

    /** What the buyer is told for a direct way of paying: the business's own words, the amount and the order number. */
    /** What's paid now: a Made-to-order deposit, else the whole order. */
    public static function due(array $o): int
    {
        $p = $o['pay_now_cents'] ?? null;
        return $p !== null && (int) $p > 0 && (int) $p < (int) $o['total_cents'] ? (int) $p : (int) $o['total_cents'];
    }

    public static function instructions(PDO $pdo, array $o): string
    {
        if ($o['method'] === 'card') return '';
        $s = self::settings($pdo, (int) $o['entity_id']);
        $m = $s['methods'][$o['method']] ?? ['label' => self::METHODS[$o['method']] ?? 'Payment', 'text' => ''];
        $txt = trim((string) $m['text']);
        if ($txt !== '' && !preg_match('/[.!?)]$/', $txt)) $txt .= '.';
        return 'Pay ' . self::money(self::due($o)) . (self::due($o) < (int) $o['total_cents'] ? ' (the deposit; the rest is invoiced when it\'s ready)' : '') . ' by ' . $m['label'] . '. ' . $txt . ' Put "Order ' . (int) $o['id'] . '" in the memo or note.'
            . ' The business confirms when it arrives. Your items are held until ' . date('M j', strtotime((string) $o['hold_until'])) . '.'
            . ($m['label'] === 'Zelle' ? ' Zelle has no buyer protection: only send to a business you know.' : '');
    }

    // ---- paid --------------------------------------------------------------------------------------------------------

    /**
     * Marks an order paid, once (a webhook can arrive twice; a buyer can come back twice): takes the stock, writes the
     * business's own order (or records the payment on it), tells both. $via: the gateway's payment reference, or
     * 'quote' when the business marked a direct payment received in its invoices.
     */
    public static function markPaid(PDO $pdo, int $id, string $ref, string $via = 'gateway'): bool
    {
        $now = self::now();
        $st = $pdo->prepare("UPDATE market_orders SET status = 'paid', paid_at = ?, updated_at = ?, provider_ref = IF(? = '', provider_ref, ?) WHERE id = ? AND status IN ('pending','awaiting','expired')");
        $st->execute([$now, $now, $ref, $ref, $id]);
        if (!$st->rowCount()) return false;   // already paid (or cancelled): nothing more
        $o = self::get($pdo, $id);
        foreach ($o['lines'] as $l) {
            if (empty($l['item_id']) || ($l['kind'] ?? 'physical') === 'booking' || ($l['kind'] ?? '') === 'rental') continue;   // nights and days aren't stock
            $pdo->prepare('UPDATE market_items SET stock = GREATEST(0, stock - ?) WHERE id = ? AND track_stock = 1')->execute([(int) $l['qty'], (int) $l['item_id']]);
            $it = ($l['kind'] ?? 'physical') === 'physical' ? MarketBiz::get($pdo, (int) $l['item_id']) : null;   // physical goods: the business's stock alert (ItemKinds)
            $at = $it ? (int) (ItemKinds::data('physical', $it['kind_data'] ?? null)['stock_alert'] ?? 0) : 0;
            if ($it && $at > 0 && (int) ($it['track_stock'] ?? 0) && (int) $it['stock'] <= $at && (int) $it['stock'] + (int) $l['qty'] > $at)
                self::notify($pdo, $o, 'Stock: ' . $it['title'] . ' is down to ' . (int) $it['stock'] . ' (your alert is at ' . $at . '). Restock it in Marketplace → Track stock.');
        }
        if (is_file(__DIR__ . '/MarketSquare.php')) {   // a business on the Square sync: what sold comes off Square's count, and on Core the order goes to Square (4b)
            try { require_once __DIR__ . '/MarketSquare.php'; MarketSquare::sold($pdo, $o); }
            catch (Throwable $x) { error_log('market order to Square: ' . $x->getMessage()); self::note($pdo, $id, 'Paid; Square\'s stock or order wasn\'t updated (' . $x->getMessage() . '): adjust it in Square.'); }
        }
        if ($via !== 'quote') {
            $copy = true; try { self::writeOrder($pdo, $o, $ref); } catch (Throwable $x) { error_log('market order ' . $id . ' to the business: ' . $x->getMessage()); $copy = false; }
            $mto = in_array('madetoorder', array_column((array) $o['lines'], 'kind'), true);
            self::notify($pdo, $o, 'Order #' . $o['id'] . ' from the Marketplace is paid by card: ' . self::money(self::due($o)) . (self::due($o) < (int) $o['total_cents'] ? ' deposit of ' . self::money((int) $o['total_cents']) : '') . '. ' . ($copy ? 'It\'s in your Orders.' : self::copyLater())
                . ($mto && $copy ? ' Made to order: in Quotes & invoices, add your proof to it and Send; the buyer approves the proof and pays the balance from there.' : ''));
        }
        $got = []; try { $got = KindCheckout::fulfil($pdo, $o); } catch (Throwable $x) { error_log('market order ' . $id . ' fulfil: ' . $x->getMessage()); self::note($pdo, $id, 'Paid, but its tickets or download weren\'t made: ' . $x->getMessage()); }   // tickets, downloads (decisions/0141 §5)
        $due = self::due($o);
        self::mailBuyer($pdo, $o, 'Paid: your order from ' . self::bizName($pdo, (int) $o['entity_id']), 'Thank you. ' . self::bizName($pdo, (int) $o['entity_id']) . ' has your payment of '
            . self::money($due) . ' for order ' . (int) $o['id'] . ($due < (int) $o['total_cents'] ? ' (the deposit; the rest, ' . self::money((int) $o['total_cents'] - $due) . ', is invoiced when it\'s ready)' : '') . '.'
            . ($got ? "\n\n" . implode("\n", $got) : ' They\'ll be in touch about ' . (stripos((string) $o['ship_name'], 'pick') !== false ? 'pickup.' : 'getting it to you.')));
        return true;
    }

    /** The business recorded a direct payment in full on its invoice (BizQuotes::pay): its Marketplace order is paid too. */
    public static function quotePaid(PDO $pdo, int $e, int $quoteId): void
    {
        if (!self::ready($pdo)) return;
        $st = $pdo->prepare("SELECT id FROM market_orders WHERE entity_id = ? AND biz_quote_id = ? AND status IN ('awaiting','expired')"); $st->execute([$e, $quoteId]);
        if ($id = (int) $st->fetchColumn()) self::markPaid($pdo, $id, '', 'quote');
    }

    private static function note(PDO $pdo, int $id, string $n): void
    {
        $pdo->prepare('UPDATE market_orders SET note = CONCAT(note, ?) WHERE id = ?')->execute([' [' . date('M j') . '] ' . mb_substr($n, 0, 200), $id]);
    }

    /**
     * The business's own copy of the order, in its walled data (decisions/0110): written by the server through the same
     * gate as its portal (BusinessData::open), as one of its invoices with the same lines, tax and total, approved;
     * with the payment recorded when it's paid by card. A direct-pay order is written when placed, unpaid.
     */
    private static function writeOrder(PDO $pdo, array $o, ?string $paidRef): void
    {
        require_once __DIR__ . '/BusinessData.php'; require_once __DIR__ . '/BizQuotes.php'; require_once __DIR__ . '/Customers.php';
        $main = Database::connection();
        $had = (int) ($o['biz_quote_id'] ?? 0);
        $qid = BusinessData::scoped((int) $o['entity_id'], function (PDO $bp) use ($pdo, $o, $paidRef, $main, $had): int {   // the tenant put back after, also on a throw
            Customers::ensure($main);   // its one-time set-up first: nothing in it may end the transaction below
            $title = 'Marketplace order #' . (int) $o['id'];
            $note = (MarketPay::PROVIDERS[$o['provider']] ?? 'Card') . ($paidRef !== null && $paidRef !== '' ? ' ' . $paidRef : '');
            $bp->beginTransaction();   // the copy whole or not at all: its lines, its total and its payment; a throw leaves nothing in the business's books
            try {
                $qid = $had;
                if (!$qid) {   // written before but never linked (the link below failed): that one, never a second
                    $st = $bp->prepare('SELECT id FROM ' . Scope::t('biz_quotes') . ' WHERE title = ? ORDER BY id LIMIT 1'); $st->execute([$title]);
                    $qid = (int) $st->fetchColumn();
                }
                if (!$qid) {
                    $qid = BizQuotes::create($main, (string) $o['email'], (string) $o['name'], $title, 0);
                    foreach ($o['lines'] as $l) {
                        $price = number_format((int) $l['unit_cents'] / 100, 2, '.', '');
                        try { BizQuotes::addLine($main, $qid, (int) $l['product_id'], (string) $l['title'], (int) $l['qty'], $price, (string) (int) $l['taxable'], 0); }
                        catch (InvalidArgumentException $x) { BizQuotes::addLine($main, $qid, 0, (string) $l['title'], (int) $l['qty'], $price, (string) (int) $l['taxable'], 0); }   // gone from the catalog since
                    }
                    if ((int) $o['ship_cents']) BizQuotes::addLine($main, $qid, 0, (string) $o['ship_name'], 1, number_format((int) $o['ship_cents'] / 100, 2, '.', ''), '0', 0);
                    $s = self::settings($pdo, (int) $o['entity_id']);
                    $how = $o['method'] === 'card' ? 'Paid by card on the Traversence Marketplace' : self::instructions($pdo, $o);
                    $bp->prepare('UPDATE ' . Scope::t('biz_quotes') . " SET tax_milli = ?, deposit_pct = " . (self::due($o) < (int) $o['total_cents'] ? (int) round(100 * self::due($o) / max(1, (int) $o['total_cents'])) : 0) . ", status = 'approved', stage = 'new', approved_at = ?, approved_name = ?, due_at = ?, total_cents = ?, pay_note = ?, notes = ?, updated_at = ? WHERE id = ?")
                        ->execute([(int) $s['tax_milli'], self::now(), 'Ordered on the Traversence Marketplace', date('Y-m-d', strtotime((string) $o['hold_until'])), (int) $o['total_cents'], mb_substr($how, 0, 400),
                            trim($title . ' · ' . $o['ship_name'] . ($o['ship_address'] !== '' ? ': ' . $o['ship_address'] : '') . ($o['note'] !== '' ? "\n" . $o['note'] : '')), self::now(), $qid]);
                }
                if ($paidRef !== null) {   // the card payment, once: a copy written before with it already recorded isn't paid again
                    $st = $bp->prepare('SELECT COUNT(*) FROM ' . Scope::t('biz_payments') . ' WHERE quote_id = ? AND note = ?'); $st->execute([$qid, mb_substr($note, 0, 300)]);
                    if (!(int) $st->fetchColumn()) BizQuotes::pay($main, $qid, number_format(self::due($o) / 100, 2, '.', ''), 'card', date('Y-m-d'), $note, 0);
                }
                $bp->commit();
                return $qid;
            } catch (Throwable $x) {
                if ($bp->inTransaction()) $bp->rollBack();
                throw $x;
            }
        });
        if ($qid !== $had) $pdo->prepare('UPDATE market_orders SET biz_quote_id = ? WHERE id = ?')->execute([$qid, (int) $o['id']]);
    }

    /**
     * The daily pass's second try at a business's copy that couldn't be written (writeOrder threw; nothing half-written is
     * left): orders waiting for a direct payment or paid by card with no copy yet, oldest first. Each written is linked and
     * its business told it's in Quotes & invoices now. Before the expiry below, so a waiting order can still be marked paid.
     */
    private static function rewriteCopies(PDO $pdo): int
    {
        $st = $pdo->prepare("SELECT id FROM market_orders WHERE status IN ('awaiting','paid') AND (biz_quote_id IS NULL OR biz_quote_id = 0) AND created_at < ? AND note NOT LIKE ? ORDER BY id LIMIT 50");
        $st->execute([date('Y-m-d H:i:s', strtotime('-10 minutes')), self::TEST_NOTE . '%']);
        $done = 0;
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $o = self::get($pdo, (int) $id);
            if (!$o) continue;
            try {
                self::writeOrder($pdo, $o, $o['status'] === 'paid' ? (string) $o['provider_ref'] : null);
                $done++;
                self::notify($pdo, $o, 'Order #' . (int) $o['id'] . ' from the Marketplace is now in Quotes & invoices' . ($o['status'] === 'paid' ? ', with its card payment.' : ': mark it paid there when the payment arrives.'));
            } catch (Throwable $x) { error_log('market order ' . $id . ' copy, second try: ' . $x->getMessage()); }
        }
        return $done;
    }

    /** What the business is told when its copy couldn't be written: where the order is now, and what happens next. */
    private static function copyLater(): string
    {
        return 'Its invoice couldn\'t be added to Quotes & invoices just now. Until it is, the order and its total are in E-commerce setup → Marketplace orders. '
            . 'It\'s added again at the next daily Marketplace check, and you\'ll get a message when it\'s there to mark paid. If that message doesn\'t come, write to support@traversence.com with the order number.';
    }

    // ---- telling people -----------------------------------------------------------------------------------------------

    private static function bizName(PDO $pdo, int $e): string { return (string) (MarketBiz::entity($pdo, $e)['name'] ?? 'The business'); }

    /** To the business's inbox (its messages), from the buyer's side; its team is told as an order (Urgent, decisions/0172 gap 2), or not at all ($tell '') when the caller tells it another way. */
    private static function notify(PDO $pdo, array $o, string $text, string $tell = 'biz.order'): void
    {
        try {
            require_once __DIR__ . '/BizMessages.php'; require_once __DIR__ . '/Marketing.php';
            BizMessages::personSend($pdo, (int) $o['buyer_id'], (int) $o['entity_id'], $text . "\nhttps://traversence.com/business/" . (int) $o['entity_id'] . '/quotes', $tell);
        } catch (Throwable $x) { error_log('market order notify: ' . $x->getMessage()); }
    }

    private static function mailBuyer(PDO $pdo, array $o, string $subject, string $body): void
    {
        try {
            if (trim((string) $o['email']) === '') return;
            require_once __DIR__ . '/Mailer.php';
            $pol = '';   // the store policies it was placed under (decisions/0142 §5.3)
            $snap = json_decode((string) ($o['policy_json'] ?? ''), true);
            if (is_array($snap) && !empty($snap['lines'])) { require_once __DIR__ . '/StorePolicies.php'; $pol = "\n\nStore policies for this order:\n" . StorePolicies::plain($snap); }
            Mailer::send((string) $o['email'], $subject, 'Hi ' . (trim((string) $o['name']) ?: 'there') . ",\n\n" . $body . $pol . "\n\nYour order: https://traversence.com/market/order.php?t=" . $o['token'] . "\n\nTraversence", (string) $o['name']);
        } catch (Throwable $x) { error_log('market order email: ' . $x->getMessage()); }
    }

    // ---- after it's paid, by kind (decisions/0141 §5) ------------------------------------------------------------------

    /**
     * A rental came back: its deposit returned, less any damage. Held on the card: the rest is captured and the deposit
     * released; charged: the deposit (less damage) refunded. Once. Returns what the business is told.
     */
    public static function rentalReturned(PDO $pdo, int $uid, array $o, int $damageCents): string
    {
        if (!self::onOrderTeam($pdo, $uid, $o)) throw new InvalidArgumentException('Only the business\'s team marks a rental returned.');
        if ($o['status'] !== 'paid') throw new InvalidArgumentException('It isn\'t paid.');
        if ((string) ($o['pay_mode'] ?? '') === 'returned') throw new InvalidArgumentException('Already marked returned.');
        $dep = 0; foreach ((array) $o['lines'] as $l) if (($l['extra'] ?? '') === 'deposit') $dep += (int) $l['unit_cents'];
        if (!$dep) throw new InvalidArgumentException('This order has no deposit.');
        $damage = max(0, min($dep, $damageCents)); $back = $dep - $damage;
        $card = $o['method'] === 'card'; $g = $card ? self::gateway($pdo, (int) $o['entity_id']) : null;
        if ($card && !$g) throw new InvalidArgumentException('Connect the card account again first.');
        if ((string) ($o['pay_mode'] ?? '') === 'hold') MarketPay::capture($g, (string) $o['provider_ref'], (int) $o['total_cents'] - $back);   // the rest captured, the deposit's unused part released
        elseif ($card) MarketPay::refund($g, (string) $o['provider_ref'], $back, self::access($pdo, $g));
        $pdo->prepare("UPDATE market_orders SET pay_mode = 'returned', updated_at = ? WHERE id = ?")->execute([self::now(), (int) $o['id']]);
        $said = 'Returned. ' . ($back ? self::money($back) . ' of the deposit ' . ($card ? 'goes back to the card' : 'is the buyer\'s to get back from you, the way they paid') : 'The deposit is kept for the damage') . ($damage && $back ? '; ' . self::money($damage) . ' kept for damage.' : '.');
        self::note($pdo, (int) $o['id'], $said);
        self::mailBuyer($pdo, $o, 'Your rental is returned', 'Thank you. ' . self::bizName($pdo, (int) $o['entity_id']) . ' marked order ' . (int) $o['id'] . ' returned. ' . ($back ? self::money($back) . ' of your deposit ' . ($card ? 'goes back to your card (a few days to show).' : 'comes back to you the way you paid.') : 'The deposit was kept for damage.') . ($damage && $back ? ' ' . self::money($damage) . ' was kept for damage.' : ''));
        return $said;
    }

    /** The buyer stops a subscription: it ends at the close of the period paid for. Returns its state (MarketPay::subscription). */
    public static function cancelSubscription(PDO $pdo, int $uid, array $o): array
    {
        if ((int) $o['buyer_id'] !== $uid) throw new InvalidArgumentException('That order isn\'t yours.');
        if ((string) ($o['pay_mode'] ?? '') !== 'subscription' || (string) ($o['sub_ref'] ?? '') === '') throw new InvalidArgumentException('This isn\'t a subscription.');
        $g = self::gateway($pdo, (int) $o['entity_id']);
        if (!$g) throw new InvalidArgumentException('Contact the business to cancel: its card account isn\'t connected here now.');
        $r = MarketPay::subscription($g, (string) $o['sub_ref'], true);
        self::note($pdo, (int) $o['id'], 'The buyer cancelled the subscription' . ($r['ends'] !== '' ? ', ending ' . date('M j', strtotime($r['ends'])) : '') . '.');
        self::notify($pdo, $o, 'Order #' . (int) $o['id'] . ': the buyer cancelled their subscription' . ($r['ends'] !== '' ? '; it ends ' . date('M j', strtotime($r['ends'])) : '') . '.');
        return $r;
    }

    public static function onOrderTeam(PDO $pdo, int $uid, array $o): bool { return MarketBiz::onTeam($pdo, $uid, (int) $o['entity_id'], 'orders', 'edit'); }

    // ---- reading ------------------------------------------------------------------------------------------------------

    /** A buyer's orders, newest first (Your Marketplace → Buying). */
    public static function forBuyer(PDO $pdo, int $uid): array
    {
        if (!self::ready($pdo)) return [];
        $st = $pdo->prepare('SELECT o.*, e.name AS biz FROM market_orders o LEFT JOIN entities e ON e.id = o.entity_id WHERE o.buyer_id = ? ORDER BY o.created_at DESC LIMIT 50'); $st->execute([$uid]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** A business's Marketplace orders (its portal's checkout page): newest first. */
    public static function forEntity(PDO $pdo, int $e, int $limit = 50): array
    {
        if (!self::ready($pdo)) return [];
        $st = $pdo->prepare('SELECT * FROM market_orders WHERE entity_id = ? ORDER BY created_at DESC LIMIT ' . (int) $limit); $st->execute([$e]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---- webhooks ---------------------------------------------------------------------------------------------------

    /** Once per event: false when this event was seen already. */
    public static function firstTime(PDO $pdo, string $provider, string $eventId, string $kind): bool
    {
        $st = $pdo->prepare('INSERT IGNORE INTO market_pay_events (provider, event_id, kind, received_at) VALUES (?, ?, ?, ?)');
        $st->execute([$provider, mb_substr($eventId, 0, 120), mb_substr($kind, 0, 60), self::now()]);
        return $st->rowCount() > 0;
    }

    /** An order the gateway says is paid: asked of the gateway again before anything changes. */
    public static function confirmAndMark(PDO $pdo, int $id): bool
    {
        $o = self::get($pdo, $id);
        if (!$o || !in_array($o['status'], ['pending', 'expired'], true) || $o['method'] !== 'card') return false;
        $g = self::gateway($pdo, (int) $o['entity_id']);
        if (!$g) return false;
        $c = MarketPay::confirm($g, $o, self::access($pdo, $g));
        [$paid, $ref] = $c;
        if ($paid && !empty($c[2]) && KindCheckout::ready($pdo)) $pdo->prepare('UPDATE market_orders SET sub_ref = ? WHERE id = ?')->execute([(string) $c[2], $id]);   // a subscription: its own reference, to manage it
        return $paid ? self::markPaid($pdo, $id, $ref) : false;
    }

    // ---- the daily pass (market_tick) and the reconcile -----------------------------------------------------------------

    /** Expires unpaid orders past their hold (stock released), asks the gateways about card orders still waiting, refreshes Square. */
    public static function tick(PDO $pdo): array
    {
        if (!self::ready($pdo)) return [0, 0, 0];
        $now = self::now(); $paid = 0; $expired = 0; $refreshed = 0;
        $st = $pdo->prepare("SELECT id FROM market_orders WHERE status = 'pending' AND method = 'card' AND provider_ref <> '' AND created_at < ? LIMIT 200"); $st->execute([date('Y-m-d H:i:s', strtotime('-10 minutes'))]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) { try { if (self::confirmAndMark($pdo, (int) $id)) $paid++; } catch (Throwable $x) { error_log('market reconcile: ' . $x->getMessage()); } }
        // a stay or rental waiting for a direct payment whose hold ran out: both sides are told its dates are released (decisions/0172 §2.5)
        $st = $pdo->prepare("SELECT id FROM market_orders WHERE status = 'awaiting' AND hold_until < ? AND (order_lines LIKE '%\"kind\":\"booking\"%' OR order_lines LIKE '%\"kind\":\"rental\"%') LIMIT 200"); $st->execute([$now]);
        $released = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        try { self::rewriteCopies($pdo); } catch (Throwable $x) { error_log('market copies, second try: ' . $x->getMessage()); }   // before the expiry: a waiting order's copy, so it can still be marked paid
        $st = $pdo->prepare("UPDATE market_orders SET status = 'expired', updated_at = ? WHERE status IN ('pending','awaiting') AND hold_until < ?"); $st->execute([$now, $now]); $expired = $st->rowCount();
        if ($released) {
            require_once __DIR__ . '/Bookings.php';
            foreach ($released as $id) {
                try { $o = self::get($pdo, $id); if ($o && $o['status'] === 'expired') Bookings::changed($pdo, $o, 'system', 'released: the payment didn\'t arrive by ' . date('M j', strtotime((string) $o['hold_until']))); }
                catch (Throwable $x) { error_log('booking released notify ' . $id . ': ' . $x->getMessage()); }
            }
        }
        $st = $pdo->prepare("SELECT * FROM market_gateways WHERE provider = 'square' AND status = 'connected' AND env = ? AND expires_at < ?"); $st->execute([MarketPay::env(), date('Y-m-d H:i:s', strtotime('+7 days'))]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $g) {
            try { self::access($pdo, $g); $refreshed++; } catch (Throwable $x) { self::broken($pdo, 'square', (string) $g['account_id'], $x->getMessage()); }
        }
        // the INFORM Consumers Act: each business's last 12 months, a request when it crosses, a pause when overdue
        if (is_file(__DIR__ . '/Inform.php')) { try { require_once __DIR__ . '/Inform.php'; Inform::tick($pdo); } catch (Throwable $x) { error_log('inform tick: ' . $x->getMessage()); } }
        // each connected account's standing, asked once a day (the Trust score and Get selling read it)
        try {
            $st = $pdo->prepare("SELECT entity_id FROM market_gateways WHERE status = 'connected' AND env = ? AND (acct_checked_at IS NULL OR acct_checked_at < ?) LIMIT 200");
            $st->execute([MarketPay::env(), date('Y-m-d H:i:s', strtotime('-20 hours'))]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $e) self::payStatus($pdo, (int) $e, 0);
        } catch (PDOException) {}   // before the database update
        return [$paid, $expired, $refreshed];
    }
}
