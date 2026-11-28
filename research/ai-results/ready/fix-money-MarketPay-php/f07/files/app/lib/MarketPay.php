<?php
// app/lib/MarketPay.php — a business's own payment gateway (decisions/0135, "Step 4 decided"): Stripe (Connect,
// Standard accounts) and Square (OAuth). Traversence is registered once with each as a platform; each business signs in
// to its own account from the portal and approves us. Nothing is pasted. The money goes straight to the business; we
// take 0% and never see a card: Stripe's embedded Checkout and Square's Web Payments form put the gateway's own fields
// inside our page.
//
// .env (Jason sets these once; until then connecting says "opens soon"):
//   MARKET_PAY_ENV        live | sandbox | mock   (mock: a local test with no network, refused on the server; sandbox: Stripe test mode and
//                                                  Square's sandbox)
//   STRIPE_CLIENT_ID, STRIPE_SECRET_KEY, STRIPE_PUBLISHABLE_KEY, STRIPE_WEBHOOK_SECRET   (the platform's, for the mode)
//   SQUARE_APP_ID, SQUARE_APP_SECRET, SQUARE_WEBHOOK_KEY                                  (the app's, for the mode)
//   With MARKET_PAY_ENV=live, a test account signed in pays in test mode (Jason, 2026-11-10), and test mode never uses a
//   live key (2026-11-11: the same keys served both, so a "sandbox" test could reach Stripe's live system). Its keys are
//   STRIPE_TEST_CLIENT_ID, STRIPE_TEST_SECRET_KEY, STRIPE_TEST_PUBLISHABLE_KEY, STRIPE_TEST_WEBHOOK_SECRET and
//   SQUARE_SANDBOX_APP_ID, SQUARE_SANDBOX_APP_SECRET, SQUARE_SANDBOX_WEBHOOK_KEY; until they're set, test accounts can't
//   pay a business by card on the live site (the page says so).
// Square's tokens are sealed (libsodium secretbox, keyed from the site's own secret, as QuickBooks' are) and refreshed by
// the daily job a week before they expire (they last 30 days). Stripe keeps only the connected account's id.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class MarketPay
{
    public const PROVIDERS = ['stripe' => 'Stripe', 'square' => 'Square'];
    private const SQUARE_SCOPES = 'MERCHANT_PROFILE_READ PAYMENTS_WRITE PAYMENTS_READ ORDERS_WRITE ORDERS_READ ITEMS_READ INVENTORY_READ INVENTORY_WRITE';   // INVENTORY_WRITE: sales here come off Square's count (4b)
    private const SQUARE_VERSION = '2025-01-23';
    // Stripe's API version, pinned so a newer account default can't change what our calls get back (decisions/0135).
    // 2026-11-02: Stripe retired ui_mode "embedded" for "embedded_page" (Stripe.js: createEmbeddedCheckoutPage), on older
    // versions too. Move it only with a tested change.
    private const STRIPE_VERSION = '2026-10-28.endive';

    /** Each gateway's sandbox test cards, shown where a business tests its checkout: [label, number, the rest]. */
    public const TEST_CARDS = [
        'stripe' => [['Pays', '4242 4242 4242 4242', 'any future date, any CVC and ZIP'], ['Declined', '4000 0000 0000 0002', 'shows the buyer the decline message']],
        'square' => [['Pays', '4111 1111 1111 1111', 'CVV 111, any future date, any ZIP']],
    ];

    /** live | sandbox | mock, or 'off' when nothing is set */
    public static function env(): string
    {
        $e = strtolower(trim((string) getenv('MARKET_PAY_ENV')));
        if ($e === 'mock' && !self::localTest()) {   // mock lets a buyer mark their own order paid: never on the server (4a review)
            static $said = false;
            if (!$said) { $said = true; error_log('SECURITY market pay: MARKET_PAY_ENV=mock refused outside a local test; checkout is off. Set sandbox or live in .env.'); }
            return 'off';
        }
        if ($e === 'live') {   // a test account signed in pays in the gateways' test mode, never with a real card (Jason, 2026-11-10)
            require_once __DIR__ . '/QuickBooksPay.php';
            if (QuickBooksPay::testHere()) return 'sandbox';
        }
        return in_array($e, ['live', 'sandbox', 'mock'], true) ? $e : 'off';
    }

    /** A local test: PHP's own command line, or its built-in test server answering on this computer only. The server
     *  (Bluehost) answers through its web server, so mock mode can't switch on there whatever .env says. */
    public static function localTest(): bool
    {
        if (PHP_SAPI === 'cli') return true;   // tests and scripts: no buyer can reach a mock payment from here
        if (PHP_SAPI !== 'cli-server') return false;
        $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        $from = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true) && in_array($from, ['127.0.0.1', '::1'], true);
    }

    /** Can businesses connect this provider now? [yes, why not] */
    public static function available(string $p): array
    {
        $env = self::env();
        if ($env === 'mock') return [true, ''];
        if ($env === 'off') return [false, 'Connecting opens soon: Traversence is finishing its setup with ' . (self::PROVIDERS[$p] ?? $p) . '.'];
        if (self::missing($p) && self::testOnLive()) return [false, 'Test accounts pay in ' . self::PROVIDERS[$p] . '\'s test mode, and its test keys aren\'t set yet (' . implode(', ', self::missing($p)) . ' in .env), so card payments are off for test accounts here. Nothing real is ever charged.'];
        if (self::missing($p)) return [false, 'Connecting opens soon: Traversence is finishing its setup with ' . self::PROVIDERS[$p] . '.'];
        return [true, ''];
    }

    /** The .env settings connecting this provider needs and still lacks, by name only (never a value). */
    public static function missing(string $p): array
    {
        $need = $p === 'stripe' ? ['STRIPE_CLIENT_ID', 'STRIPE_SECRET_KEY', 'STRIPE_PUBLISHABLE_KEY'] : ['SQUARE_APP_ID', 'SQUARE_APP_SECRET'];
        return array_values(array_map([self::class, 'keyName'], array_filter($need, fn ($k) => self::key($k) === '')));
    }

    /** Every missing setting, the webhook's too (it marks orders paid), for the admin's Gateways page. */
    public static function missingAll(string $p): array
    {
        $need = $p === 'stripe' ? ['STRIPE_CLIENT_ID', 'STRIPE_SECRET_KEY', 'STRIPE_PUBLISHABLE_KEY', 'STRIPE_WEBHOOK_SECRET'] : ['SQUARE_APP_ID', 'SQUARE_APP_SECRET', 'SQUARE_WEBHOOK_KEY'];
        return array_values(array_map([self::class, 'keyName'], array_filter($need, fn ($k) => self::key($k) === '')));
    }

    /** A test account on the live site: test mode, with its own keys (never the live ones). */
    public static function testOnLive(): bool
    {
        return self::env() === 'sandbox' && strtolower(trim((string) getenv('MARKET_PAY_ENV'))) === 'live';
    }

    /** The .env name a setting is read from now: STRIPE_TEST_… / SQUARE_SANDBOX_… for a test account on the live site. */
    private static function keyName(string $k): string
    {
        return self::testOnLive() ? (string) preg_replace(['/^STRIPE_/', '/^SQUARE_/'], ['STRIPE_TEST_', 'SQUARE_SANDBOX_'], $k) : $k;
    }

    /** A setting for the mode. In test mode a key that is a live one reads as empty, so nothing test reaches a live account. */
    private static function key(string $k): string
    {
        $v = trim((string) getenv(self::keyName($k)));
        if (self::env() === 'sandbox' && $v !== '' && (preg_match('/^(sk|pk|rk)_live_/', $v) || (str_starts_with($k, 'SQUARE_APP_') && !str_starts_with($v, 'sandbox-')))) {
            static $said = [];
            if (empty($said[$k])) { $said[$k] = true; error_log('SECURITY market pay: a live ' . $k . ' was refused in test mode.'); }
            return '';
        }
        return $v;
    }

    // ---- sealing tokens ------------------------------------------------------------------------------------------------

    private static function sealKey(): string
    {
        $site = (string) (getenv('APP_SECRET') ?: getenv('DB_PASSWORD') ?: '');
        return sodium_crypto_generichash('tv-market-pay|' . $site, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public static function seal(string $s): string
    {
        $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($n . sodium_crypto_secretbox($s, $n, self::sealKey()));
    }

    public static function unseal(?string $b): string
    {
        if ($b === null || $b === '') return '';
        $raw = base64_decode($b, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return '';
        $out = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::sealKey());
        return $out === false ? '' : $out;
    }

    // ---- HTTP ------------------------------------------------------------------------------------------------------------

    /** [status, decoded body]. Throws on a network failure. */
    private static function http(string $method, string $url, array $headers, ?string $body): array
    {
        require_once __DIR__ . '/StagingGate.php'; if (($w = StagingGate::hold($url, 'payments ' . $method)) !== null) throw new RuntimeException($w);   // staging: the sandbox only
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_HTTPHEADER => $headers]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $raw = curl_exec($ch);
        require_once __DIR__ . '/ServiceHealth.php'; 
        if ($raw === false) { $e = curl_error($ch); curl_close($ch); ServiceHealth::seen($url, 0, '', $e, 'Payments', true); throw new RuntimeException('The payment service didn\'t answer (' . $e . '). Try again.'); }
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        ServiceHealth::seen($url, $code, '', '', 'Payments', true);   // server errors only; a payment is never held by it
        return [$code, json_decode((string) $raw, true) ?: []];
    }

    private static function stripe(string $method, string $path, array $params = [], string $account = '', string $idem = ''): array
    {
        $h = ['Authorization: Bearer ' . self::key('STRIPE_SECRET_KEY'), 'Content-Type: application/x-www-form-urlencoded', 'Stripe-Version: ' . self::STRIPE_VERSION];
        if ($account !== '') $h[] = 'Stripe-Account: ' . $account;
        if ($idem !== '') $h[] = 'Idempotency-Key: ' . $idem;
        $q = http_build_query($params);
        [$code, $d] = self::http($method, 'https://api.stripe.com' . $path . ($method === 'GET' && $q !== '' ? '?' . $q : ''), $h, $method === 'GET' ? null : $q);
        if ($code >= 400) throw new RuntimeException('Stripe: ' . (string) ($d['error']['message'] ?? 'error ' . $code));
        return $d;
    }

    private static function squareHost(): string { return self::env() === 'sandbox' ? 'https://connect.squareupsandbox.com' : 'https://connect.squareup.com'; }

    private static function square(string $method, string $path, ?array $body, string $token): array
    {
        [$code, $d] = self::http($method, self::squareHost() . $path, ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Square-Version: ' . self::SQUARE_VERSION], $body === null ? null : json_encode($body));
        if ($code >= 400) throw new RuntimeException('Square: ' . (string) ($d['errors'][0]['detail'] ?? 'error ' . $code));
        return $d;
    }

    /** Square, the answer as it came: [status, body], for a caller that reads Square's error codes. Throws on no answer. */
    private static function squareCall(string $method, string $path, ?array $body, string $token): array
    {
        return self::http($method, self::squareHost() . $path, ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Square-Version: ' . self::SQUARE_VERSION], $body === null ? null : json_encode($body));
    }

    /** Square's API for the catalog sync (MarketSquare): the same calls, errors as RuntimeException. */
    public static function squareApi(string $method, string $path, ?array $body, string $token): array { return self::square($method, $path, $body, $token); }

    // ---- connecting (OAuth) ----------------------------------------------------------------------------------------------

    /** Where "Connect Stripe / Square" sends the business's owner. $state ties the answer to them (checked on return). */
    public static function connectUrl(string $p, string $state, string $back): string
    {
        if (self::env() === 'mock') return $back . (str_contains($back, '?') ? '&' : '?') . http_build_query(['provider' => $p, 'code' => 'mock-' . $p, 'state' => $state]);
        if ($p === 'stripe') return 'https://connect.stripe.com/oauth/authorize?' . http_build_query(['response_type' => 'code', 'client_id' => self::key('STRIPE_CLIENT_ID'), 'scope' => 'read_write', 'state' => $state, 'redirect_uri' => $back]);
        return self::squareHost() . '/oauth2/authorize?' . http_build_query(['client_id' => self::key('SQUARE_APP_ID'), 'scope' => self::SQUARE_SCOPES, 'session' => 'false', 'state' => $state, 'redirect_uri' => $back]);
    }

    /** The answer from the provider: the code exchanged for the account (and Square's tokens). Returns what to keep. */
    public static function finishConnect(string $p, string $code): array
    {
        if (self::env() === 'mock') return ['account_id' => 'mock_' . $p . '_' . substr(sha1($code . microtime()), 0, 10), 'location_id' => $p === 'square' ? 'mock_loc' : '', 'access' => '', 'refresh' => '', 'expires_at' => null];
        if ($p === 'stripe') {
            [$c, $d] = self::http('POST', 'https://connect.stripe.com/oauth/token', ['Content-Type: application/x-www-form-urlencoded'], http_build_query(['client_secret' => self::key('STRIPE_SECRET_KEY'), 'code' => $code, 'grant_type' => 'authorization_code']));
            if ($c >= 400 || empty($d['stripe_user_id'])) throw new RuntimeException('Stripe didn\'t connect: ' . (string) ($d['error_description'] ?? 'try again'));
            return ['account_id' => (string) $d['stripe_user_id'], 'location_id' => '', 'access' => '', 'refresh' => '', 'expires_at' => null];
        }
        [$c, $d] = self::http('POST', self::squareHost() . '/oauth2/token', ['Content-Type: application/json', 'Square-Version: ' . self::SQUARE_VERSION],
            json_encode(['client_id' => self::key('SQUARE_APP_ID'), 'client_secret' => self::key('SQUARE_APP_SECRET'), 'code' => $code, 'grant_type' => 'authorization_code']));
        if ($c >= 400 || empty($d['access_token'])) throw new RuntimeException('Square didn\'t connect: ' . (string) ($d['errors'][0]['detail'] ?? $d['message'] ?? 'try again'));
        $loc = self::square('GET', '/v2/locations', null, (string) $d['access_token']);
        $main = '';
        foreach ((array) ($loc['locations'] ?? []) as $l) if (($l['status'] ?? '') === 'ACTIVE' && in_array('CREDIT_CARD_PROCESSING', (array) ($l['capabilities'] ?? []), true)) { $main = (string) $l['id']; break; }
        if ($main === '') throw new RuntimeException('Your Square account has no location that takes card payments yet. Set one up in Square, then connect again.');
        return ['account_id' => (string) ($d['merchant_id'] ?? ''), 'location_id' => $main, 'access' => (string) $d['access_token'], 'refresh' => (string) ($d['refresh_token'] ?? ''),
            'expires_at' => !empty($d['expires_at']) ? date('Y-m-d H:i:s', strtotime((string) $d['expires_at'])) : null];
    }

    /** Square: a fresh access token from the refresh token. [access, refresh, expires_at] */
    public static function refreshSquare(string $refresh): array
    {
        if (self::env() === 'mock') return ['', $refresh, date('Y-m-d H:i:s', strtotime('+30 days'))];
        [$c, $d] = self::http('POST', self::squareHost() . '/oauth2/token', ['Content-Type: application/json', 'Square-Version: ' . self::SQUARE_VERSION],
            json_encode(['client_id' => self::key('SQUARE_APP_ID'), 'client_secret' => self::key('SQUARE_APP_SECRET'), 'refresh_token' => $refresh, 'grant_type' => 'refresh_token']));
        if ($c >= 400 || empty($d['access_token'])) throw new RuntimeException('Square: ' . (string) ($d['errors'][0]['detail'] ?? 'the connection needs to be made again'));
        return [(string) $d['access_token'], (string) ($d['refresh_token'] ?? $refresh), !empty($d['expires_at']) ? date('Y-m-d H:i:s', strtotime((string) $d['expires_at'])) : null];
    }

    /** Disconnecting: Stripe deauthorizes our platform from the account; Square revokes our token. Never fails the disconnect. */
    public static function revoke(array $g): void
    {
        if (self::env() === 'mock') return;
        try {
            if ($g['provider'] === 'stripe') self::http('POST', 'https://connect.stripe.com/oauth/deauthorize', ['Authorization: Bearer ' . self::key('STRIPE_SECRET_KEY'), 'Content-Type: application/x-www-form-urlencoded'],
                http_build_query(['client_id' => self::key('STRIPE_CLIENT_ID'), 'stripe_user_id' => $g['account_id']]));
            else self::http('POST', self::squareHost() . '/oauth2/revoke', ['Authorization: Client ' . self::key('SQUARE_APP_SECRET'), 'Content-Type: application/json', 'Square-Version: ' . self::SQUARE_VERSION],
                json_encode(['client_id' => self::key('SQUARE_APP_ID'), 'merchant_id' => $g['account_id']]));
        } catch (Throwable $x) { error_log('market pay revoke: ' . $x->getMessage()); }
    }

    // ---- paying ----------------------------------------------------------------------------------------------------------

    /**
     * Starts paying for an order. Stripe: an embedded Checkout session on the business's account (its client secret,
     * for Stripe.js). Square: what its Web Payments form needs (the card is tokenized in Square's own fields, then
     * chargeSquare). Mock: a test button. Returns ['kind' => stripe|square|mock, …].
     */
    // Card only (Jason, 2026-11-03): Apple Pay and Google Pay come with it (they pay by card). A business's other Stripe
    // methods (bank debits, buy now pay later, crypto) can settle days later, long after an order's 35-minute hold.
    public static function start(array $g, array $order, string $returnUrl): array
    {
        if (self::env() === 'mock') return ['kind' => 'mock'];
        if ($g['provider'] === 'stripe') {
            $p = ['ui_mode' => 'embedded_page', 'mode' => 'payment', 'payment_method_types[0]' => 'card', 'return_url' => $returnUrl, 'client_reference_id' => (string) $order['id'], 'metadata[tv_order]' => (string) $order['id'],
                  'payment_intent_data[metadata][tv_order]' => (string) $order['id'], 'customer_email' => (string) $order['email'],
                  'expires_at' => (string) (time() + 31 * 60)];   // Stripe's least (30 min); ends before our hold (CARD_HOLD_MIN), so a released item can't be paid for
            $mode = (string) ($order['pay_mode'] ?? 'payment');
            $due = self::due($order);
            $i = 0;
            if ($mode === 'subscription') {   // charged each month or year until the buyer cancels (decisions/0141 §5)
                $p['mode'] = 'subscription';
                unset($p['payment_intent_data[metadata][tv_order]']);
                $p['subscription_data[metadata][tv_order]'] = (string) $order['id'];
                $l = (array) ($order['lines'][0] ?? []);
                if (!empty($l['kx']['trial_days'])) $p['subscription_data[trial_period_days]'] = (string) (int) $l['kx']['trial_days'];
                foreach (self::stripeLines($order) as [$name, $cents, $qty]) {
                    $p["line_items[$i][price_data][currency]"] = 'usd'; $p["line_items[$i][price_data][product_data][name]"] = $name;
                    $p["line_items[$i][price_data][unit_amount]"] = $cents; $p["line_items[$i][price_data][recurring][interval]"] = ($l['kx']['interval'] ?? 'month') === 'year' ? 'year' : 'month';
                    $p["line_items[$i][quantity]"] = $qty; $i++;
                }
            } elseif ($due < (int) $order['total_cents']) {   // a deposit now; the rest invoiced when it's ready
                $p['line_items[0][price_data][currency]'] = 'usd'; $p['line_items[0][price_data][product_data][name]'] = 'Deposit on order ' . (int) $order['id'] . ' (of ' . '$' . number_format((int) $order['total_cents'] / 100, 2) . ')';
                $p['line_items[0][price_data][unit_amount]'] = $due; $p['line_items[0][quantity]'] = 1;
            } else {
                if ($mode === 'hold') $p['payment_intent_data[capture_method]'] = 'manual';   // a rental's deposit held on the card: captured on return, the rest released
                foreach (self::stripeLines($order) as [$name, $cents, $qty]) {
                    $p["line_items[$i][price_data][currency]"] = 'usd'; $p["line_items[$i][price_data][product_data][name]"] = $name;
                    $p["line_items[$i][price_data][unit_amount]"] = $cents; $p["line_items[$i][quantity]"] = $qty; $i++;
                }
            }
            $s = self::stripe('POST', '/v1/checkout/sessions', $p, (string) $g['account_id'], 'tv-order-' . $order['id'] . '-' . substr((string) $order['token'], 0, 8));
            return ['kind' => 'stripe', 'ref' => (string) $s['id'], 'client_secret' => (string) $s['client_secret'], 'publishable' => self::key('STRIPE_PUBLISHABLE_KEY'), 'account' => (string) $g['account_id']];
        }
        return ['kind' => 'square', 'app_id' => self::key('SQUARE_APP_ID'), 'location_id' => (string) $g['location_id'], 'sandbox' => self::env() === 'sandbox'];
    }

    /** The order as lines for Stripe: its items, then shipping and tax as their own lines (the totals are ours). */
    private static function stripeLines(array $o): array
    {
        $out = [];
        foreach ((array) $o['lines'] as $l) $out[] = [(string) $l['title'], (int) $l['unit_cents'], (int) $l['qty']];
        if ((int) $o['ship_cents']) $out[] = ['Shipping or delivery', (int) $o['ship_cents'], 1];
        if ((int) $o['tax_cents']) $out[] = ['Sales tax', (int) $o['tax_cents'], 1];
        return $out;
    }

    /**
     * Square: charges the token its Web Payments form made, on the business's account. Returns the payment id.
     * f07: the idempotency key is the order and what's due, not the card token. A retry after a timeout carries a new
     * token; keyed by token it was a second charge, and the first went unrecorded. Now a charge that already went through
     * for this order is found (reference TV-<id>) and returned instead of charging again. Only when the order's key was
     * used by a card that was declined does a new card get its own key (order + amount + card).
     */
    public static function chargeSquare(array $g, array $order, string $sourceToken, string $access): string
    {
        $done = self::squareExisting(self::squareFindOrderPayment($g, $order, $access), $order);   // a retry: the first charge went through
        if ($done !== '') return $done;
        $body = ['source_id' => $sourceToken, 'idempotency_key' => self::squareChargeKey($order),
            'amount_money' => ['amount' => self::due($order), 'currency' => 'USD'], 'location_id' => (string) $g['location_id'], 'reference_id' => 'TV-' . $order['id'],
            'note' => 'Traversence Marketplace order ' . $order['id'], 'buyer_email_address' => (string) $order['email'], 'autocomplete' => true];
        try {
            [$code, $d] = self::squareCall('POST', '/v2/payments', $body, $access);
        } catch (RuntimeException $x) {   // no answer: it may have gone through all the same, so look before saying it didn't
            $late = self::squareExisting(self::squareFindOrderPayment($g, $order, $access), $order);
            if ($late !== '') return $late;
            throw $x;
        }
        if ($code >= 400 && self::squareKeyReused($d)) {
            // the order's key was used before with another card: that charge went through (found and returned), or it was
            // declined and this card gets its own key, still tied to the order and the amount
            $done = self::squareExisting(self::squareFindOrderPayment($g, $order, $access), $order);
            if ($done !== '') return $done;
            $body['idempotency_key'] = self::squareChargeKey($order, $sourceToken);
            [$code, $d] = self::squareCall('POST', '/v2/payments', $body, $access);
        }
        if ($code >= 400) throw new RuntimeException('Square: ' . (string) ($d['errors'][0]['detail'] ?? 'error ' . $code));
        $p = (array) ($d['payment'] ?? []);
        if (($p['status'] ?? '') !== 'COMPLETED') throw new RuntimeException('The card wasn\'t charged (' . strtolower((string) ($p['status'] ?? 'declined')) . '). Nothing was taken.');
        return (string) $p['id'];
    }

    /** Square's idempotency key for an order's charge: the order and what's due (≤ 45 characters). With a card token, that card's own key. */
    public static function squareChargeKey(array $order, string $sourceToken = ''): string
    {
        $k = 'tv-order-' . (int) $order['id'] . '-' . self::due($order);
        return $sourceToken === '' ? $k : $k . '-' . substr(sha1($sourceToken), 0, 10);
    }

    /** Did Square refuse because the key was used before with other details? */
    public static function squareKeyReused(array $d): bool
    {
        foreach ((array) ($d['errors'] ?? []) as $e) if (is_array($e) && (string) ($e['code'] ?? '') === 'IDEMPOTENCY_KEY_REUSED') return true;
        return false;
    }

    /** From a list of Square payments: this order's payment that went through or is going through (reference TV-<id>), else []. */
    public static function squarePaymentFor(array $payments, array $order): array
    {
        foreach ($payments as $p) {
            if (!is_array($p) || (string) ($p['reference_id'] ?? '') !== 'TV-' . $order['id']) continue;
            if (in_array((string) ($p['status'] ?? ''), ['COMPLETED', 'APPROVED', 'PENDING'], true)) return $p;
        }
        return [];
    }

    /** A payment already found for the order: its id when it's complete for what's due; '' when there's none; else it stops the charge. */
    public static function squareExisting(array $p, array $order): string
    {
        if (!$p) return '';
        $cents = (int) ($p['amount_money']['amount'] ?? 0);
        if ((string) ($p['status'] ?? '') === 'COMPLETED' && $cents === self::due($order)) return (string) ($p['id'] ?? '');
        if ((string) ($p['status'] ?? '') === 'COMPLETED') throw new RuntimeException('A card payment of $' . number_format($cents / 100, 2) . ' for this order already went through, and it isn\'t what\'s due now. Nothing more was charged; the business will sort it out with you.');
        throw new RuntimeException('A card payment for this order is still going through. Nothing more was charged; refresh in a minute.');
    }

    /** Square: this order's payment at the business's location in the last day, else [] (also when Square can't be asked). */
    private static function squareFindOrderPayment(array $g, array $order, string $access): array
    {
        try {
            $cursor = ''; $pages = 0;
            do {
                $q = ['location_id' => (string) $g['location_id'], 'begin_time' => gmdate('Y-m-d\TH:i:s\Z', time() - 86400), 'sort_order' => 'DESC', 'limit' => 100];
                if ($cursor !== '') $q['cursor'] = $cursor;
                $d = self::square('GET', '/v2/payments?' . http_build_query($q), null, $access);
                $hit = self::squarePaymentFor((array) ($d['payments'] ?? []), $order);
                if ($hit) return $hit;
                $cursor = (string) ($d['cursor'] ?? '');
            } while ($cursor !== '' && ++$pages < 10);
        } catch (Throwable $x) { error_log('market pay square lookup, order ' . (int) $order['id'] . ': ' . $x->getMessage()); }
        return [];
    }

    /**
     * Asks the gateway whether an order's payment went through (never the return page alone). [paid, payment ref]
     * Stripe: the Checkout session; Square: the payment.
     */
    public static function confirm(array $g, array $order, string $access = ''): array
    {
        if (self::env() === 'mock') return [$order['provider_ref'] !== '' && str_starts_with((string) $order['provider_ref'], 'mock_paid'), (string) $order['provider_ref']];
        if ($order['provider_ref'] === '') return [false, ''];
        if ($g['provider'] === 'stripe') {
            $s = self::stripe('GET', '/v1/checkout/sessions/' . rawurlencode((string) $order['provider_ref']), [], (string) $g['account_id']);
            if ((string) ($order['pay_mode'] ?? '') === 'subscription')   // its first charge (or a free trial) went through: the subscription is the reference
                return [($s['status'] ?? '') === 'complete' && in_array($s['payment_status'] ?? '', ['paid', 'no_payment_required'], true), (string) ($s['subscription'] ?? ''), (string) ($s['subscription'] ?? '')];
            if ((string) ($order['pay_mode'] ?? '') === 'hold') {   // authorized, to be captured on return
                $pi = !empty($s['payment_intent']) ? self::stripe('GET', '/v1/payment_intents/' . rawurlencode((string) $s['payment_intent']), [], (string) $g['account_id']) : [];
                return [in_array($pi['status'] ?? '', ['requires_capture', 'succeeded'], true) && (int) ($pi['amount'] ?? 0) === (int) $order['total_cents'], (string) ($s['payment_intent'] ?? '')];
            }
            return [($s['payment_status'] ?? '') === 'paid' && (int) ($s['amount_total'] ?? 0) === self::due($order), (string) ($s['payment_intent'] ?? $order['provider_ref'])];
        }
        $d = self::square('GET', '/v2/payments/' . rawurlencode((string) $order['provider_ref']), null, $access);
        $p = (array) ($d['payment'] ?? []);
        return [($p['status'] ?? '') === 'COMPLETED' && (int) ($p['amount_money']['amount'] ?? 0) === self::due($order), (string) ($p['id'] ?? '')];
    }

    // ---- the connected account's own standing (decisions/0141 §3, Trust score) ----------------------------------------

    /** What Stripe's requirement names mean to a business owner (by their first part). */
    private const STRIPE_NEEDS = ['external_account' => 'a bank account for your payouts', 'tos_acceptance' => 'accepting Stripe\'s terms',
        'business_profile' => 'your business details (what you sell, your website or a description)', 'business_type' => 'what kind of business it is',
        'company' => 'your company\'s details (legal name, address, tax ID)', 'individual' => 'the owner\'s details (name, birth date, address, ID)',
        'representative' => 'the owner\'s details (name, birth date, address, ID)', 'owners' => 'who owns the business', 'directors' => 'the business\'s directors',
        'executives' => 'the business\'s executives', 'person' => 'a person\'s details on the account', 'settings' => 'your account settings'];

    /**
     * Asks the gateway how the business's own account stands: can it take charges, will it pay out, and what is still
     * needed. Stripe: the connected account, read with our platform key. Square: the merchant (ACTIVE takes payments; Square
     * pays out to the bank its own setup holds). ['ok', 'charges', 'payouts', 'due' => [plain words], 'note', 'link'].
     * Throws RuntimeException when the gateway can't be asked.
     */
    public static function accountStatus(array $g, string $access = ''): array
    {
        $sandbox = self::env() === 'sandbox';
        if (self::env() === 'mock') return ['ok' => true, 'charges' => true, 'payouts' => true, 'due' => [], 'note' => 'Local test', 'link' => ''];
        if ($g['provider'] === 'stripe') {
            $a = self::stripe('GET', '/v1/accounts/' . rawurlencode((string) $g['account_id']));
            $due = [];
            foreach (array_merge((array) ($a['requirements']['past_due'] ?? []), (array) ($a['requirements']['currently_due'] ?? [])) as $r) {
                $w = self::STRIPE_NEEDS[strtok((string) $r, '.')] ?? null;
                if (str_contains((string) $r, 'verification.document')) $w = 'a photo of the owner\'s ID';
                $w = $w ?? 'more details Stripe asks for';
                if (!in_array($w, $due, true)) $due[] = $w;
            }
            $ch = !empty($a['charges_enabled']); $po = !empty($a['payouts_enabled']);
            $why = (string) ($a['requirements']['disabled_reason'] ?? '');
            return ['ok' => $ch && $po, 'charges' => $ch, 'payouts' => $po, 'due' => $due,
                'note' => $why !== '' ? 'Stripe: ' . str_replace(['requirements.', '_', '.'], ['', ' ', ': '], $why) : '',
                'link' => 'https://dashboard.stripe.com/' . ($sandbox ? 'test/' : '') . 'settings/account'];
        }
        $d = self::square('GET', '/v2/merchants/me', null, $access);
        $st = strtoupper((string) ($d['merchant']['status'] ?? ''));
        $on = $st === 'ACTIVE';
        return ['ok' => $on, 'charges' => $on, 'payouts' => $on, 'due' => $on ? [] : ['finishing your Square account (Square says it isn\'t active)'],
            'note' => $st !== '' ? 'Square: ' . strtolower($st) : '', 'link' => $sandbox ? 'https://developer.squareup.com/apps' : 'https://squareup.com/dashboard/account/business'];
    }

    /** What's charged now: a deposit (pay_now_cents), else the whole order. */
    public static function due(array $o): int
    {
        $p = $o['pay_now_cents'] ?? null;
        return $p !== null && (int) $p > 0 && (int) $p < (int) $o['total_cents'] ? (int) $p : (int) $o['total_cents'];
    }

    /** A rental held on the card, returned: captures what's kept (the rental, and any damage), the rest is released. */
    public static function capture(array $g, string $paymentIntent, int $cents): void
    {
        if (self::env() === 'mock') return;
        if ($g['provider'] !== 'stripe') throw new RuntimeException('Holds are on Stripe only.');
        self::stripe('POST', '/v1/payment_intents/' . rawurlencode($paymentIntent) . '/capture', ['amount_to_capture' => (string) $cents], (string) $g['account_id'], 'tv-capture-' . $paymentIntent);
    }

    /** A part refunded on the business's own account (a rental's deposit back). */
    public static function refund(array $g, string $ref, int $cents, string $access = ''): void
    {
        if (self::env() === 'mock' || $cents <= 0) return;
        if ($g['provider'] === 'stripe') { self::stripe('POST', '/v1/refunds', ['payment_intent' => $ref, 'amount' => (string) $cents, 'reason' => 'requested_by_customer'], (string) $g['account_id'], 'tv-refund-' . $ref . '-' . $cents); return; }
        self::square('POST', '/v2/refunds', ['idempotency_key' => 'tv-refund-' . substr(sha1($ref . $cents), 0, 20), 'payment_id' => $ref, 'amount_money' => ['amount' => $cents, 'currency' => 'USD'], 'reason' => 'Deposit returned'], $access);
    }

    /** A subscription: ['status', 'ends' (when it stops, if cancelled), 'renews'] from Stripe; or ends it at the close of the paid period. */
    public static function subscription(array $g, string $sub, bool $cancel = false): array
    {
        if (self::env() === 'mock') return ['status' => $cancel ? 'cancelling' : 'active', 'ends' => $cancel ? date('Y-m-d', strtotime('+1 month')) : '', 'renews' => $cancel ? '' : date('Y-m-d', strtotime('+1 month'))];
        $s = $cancel ? self::stripe('POST', '/v1/subscriptions/' . rawurlencode($sub), ['cancel_at_period_end' => 'true'], (string) $g['account_id'])
                     : self::stripe('GET', '/v1/subscriptions/' . rawurlencode($sub), [], (string) $g['account_id']);
        $end = (int) ($s['current_period_end'] ?? ($s['items']['data'][0]['current_period_end'] ?? 0));
        $stopping = !empty($s['cancel_at_period_end']) || ($s['status'] ?? '') === 'canceled';
        return ['status' => ($s['status'] ?? '') === 'canceled' ? 'cancelled' : ($stopping ? 'cancelling' : (string) ($s['status'] ?? '')), 'ends' => $stopping && $end ? date('Y-m-d', $end) : '', 'renews' => !$stopping && $end ? date('Y-m-d', $end) : ''];
    }

    // ---- webhooks: the signature first ----------------------------------------------------------------------------------

    /** Stripe: the Stripe-Signature header (t=…,v1=…), HMAC-SHA256 of "t.body", within 5 minutes. */
    public static function stripeSigned(string $raw, string $header): bool
    {
        $secret = self::key('STRIPE_WEBHOOK_SECRET');
        if ($secret === '' || $header === '') return false;
        $t = 0; $sigs = [];
        foreach (explode(',', $header) as $part) { [$k, $v] = array_pad(explode('=', trim($part), 2), 2, ''); if ($k === 't') $t = (int) $v; if ($k === 'v1') $sigs[] = $v; }
        if (!$t || abs(time() - $t) > 300) return false;
        $want = hash_hmac('sha256', $t . '.' . $raw, $secret);
        foreach ($sigs
