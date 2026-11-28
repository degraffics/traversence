=== FILE: f05/files/app/lib/MarketCheckout.php.region-1.php ===
<?php
// ANCHOR: replaces `    public static function rentalReturned(PDO $pdo, int $uid, array $o, int $damageCents): string` through `        return $said;` and the method's closing `    }` on the next line. The docblock above it stays.
// (The `<?php` line and this ANCHOR comment are not pasted. The region adds one new private helper, payCall, after rentalReturned.)
    public static function rentalReturned(PDO $pdo, int $uid, array $o, int $damageCents): string
    {
        if (!self::onOrderTeam($pdo, $uid, $o)) throw new InvalidArgumentException('Only the business\'s team marks a rental returned.');
        $o = self::get($pdo, (int) $o['id']) ?? $o;   // read fresh: the page's copy can be minutes old (f05)
        if ($o['status'] !== 'paid') throw new InvalidArgumentException('It isn\'t paid.');
        $mode = $o['pay_mode'] ?? null;   // how it was paid: 'hold' (held on the card) or 'payment' (charged)
        if ((string) $mode === 'returned') throw new InvalidArgumentException('Already marked returned.');
        if ((string) $mode === 'returning') throw new InvalidArgumentException('It\'s already being marked returned. If that was more than a few minutes ago, our team is checking it with the card company and will finish it. Questions? Write to support@traversence.com with order ' . (int) $o['id'] . '.');
        $dep = 0; foreach ((array) $o['lines'] as $l) if (($l['extra'] ?? '') === 'deposit') $dep += (int) $l['unit_cents'];
        if (!$dep) throw new InvalidArgumentException('This order has no deposit.');
        $damage = max(0, min($dep, $damageCents)); $back = $dep - $damage;
        $card = $o['method'] === 'card'; $g = $card ? self::gateway($pdo, (int) $o['entity_id']) : null;
        if ($card && !$g) throw new InvalidArgumentException('Connect the card account again first.');
        $hold = (string) $mode === 'hold';
        if ($hold && !$card) throw new InvalidArgumentException('This order\'s hold isn\'t on a card. Write to support@traversence.com with order ' . (int) $o['id'] . '.');
        // Claim the return before any money moves. Only one of two clicks, tabs or team members gets past this (f05).
        // data_reason: f05. Claims the rental's return once ('returning'), so its capture or refund runs once.
        $st = $pdo->prepare("UPDATE market_orders SET pay_mode = 'returning', updated_at = ? WHERE id = ? AND status = 'paid' AND pay_mode <=> ?");
        $st->execute([self::now(), (int) $o['id'], $mode]);
        if (!$st->rowCount()) throw new InvalidArgumentException('Someone is marking it returned already, or just did: reload the order.');
        // The same key for any retry of this order's return, so the gateway does it once. The amount is left out on
        // purpose: a retry with different damage is refused by the gateway, never paid twice.
        $key = 'rental-return-' . (int) $o['id'];
        try {
            if ($hold) self::payCall('capture', [$g, (string) $o['provider_ref'], (int) $o['total_cents'] - $back], $key);   // capture the rest; release the deposit's unused part
            elseif ($card && $back > 0) self::payCall('refund', [$g, (string) $o['provider_ref'], $back, self::access($pdo, $g)], $key);
        } catch (Throwable $x) {   // the gateway didn't confirm, so we can't know whether money moved: the order stays claimed ('returning') and our team checks (f05)
            error_log('market rental return ' . (int) $o['id'] . ': ' . $x->getMessage());
            try { self::note($pdo, (int) $o['id'], 'Marking it returned stopped at the card company. Our team is checking whether the money moved, and will finish it.'); } catch (Throwable $y) { error_log('market rental return note ' . (int) $o['id'] . ': ' . $y->getMessage()); }
            try {
                require_once __DIR__ . '/GaugeTasks.php';
                $gwName = MarketPay::PROVIDERS[$o['provider']] ?? 'the gateway';
                GaugeTasks::add($pdo, 'area:backlog', 'Marketplace order #' . (int) $o['id'] . ': a rental\'s return stopped at ' . $gwName,
                    'Gate 5: rentalReturned claimed it (pay_mode = returning). Then the ' . ($hold ? 'capture of ' . self::money((int) $o['total_cents'] - $back) : 'refund of ' . self::money($back)) . ' (ref ' . (string) $o['provider_ref'] . ', idempotency key ' . $key . ') threw: "' . mb_substr($x->getMessage(), 0, 200) . '". '
                    . 'Check the payment in business ' . (int) $o['entity_id'] . '\'s ' . $gwName . ' account. If the money moved: set pay_mode = returned and tell the buyer. If it didn\'t: set pay_mode back to ' . ($mode === null ? 'NULL' : (string) $mode) . ' so the business can mark it returned again. Damage kept: ' . self::money($damage) . '.',
                    'MarketCheckout (a rental\'s return)', 'the Marketplace', 0);
            } catch (Throwable $y) { error_log('market rental return ticket ' . (int) $o['id'] . ': ' . $y->getMessage()); }
            throw new RuntimeException('The card company didn\'t confirm it, so nothing more happens to this order until our team has checked with it. They\'ll finish it and let you know. Please don\'t refund or capture it yourself.');
        }
        // data_reason: f05. Records the return after the gateway confirmed it. Only the row this call claimed is changed.
        $pdo->prepare("UPDATE market_orders SET pay_mode = 'returned', updated_at = ? WHERE id = ? AND pay_mode = 'returning'")->execute([self::now(), (int) $o['id']]);
        $said = 'Returned. ' . ($back ? self::money($back) . ' of the deposit ' . ($card ? 'goes back to the card' : 'is the buyer\'s to get back from you, the way they paid') : 'The deposit is kept for the damage') . ($damage && $back ? '; ' . self::money($damage) . ' kept for damage.' : '.');
        self::note($pdo, (int) $o['id'], $said);
        self::mailBuyer($pdo, $o, 'Your rental is returned', 'Thank you. ' . self::bizName($pdo, (int) $o['entity_id']) . ' marked order ' . (int) $o['id'] . ' returned. ' . ($back ? self::money($back) . ' of your deposit ' . ($card ? 'goes back to your card (a few days to show).' : 'comes back to you the way you paid.') : 'The deposit was kept for damage.') . ($damage && $back ? ' ' . self::money($damage) . ' was kept for damage.' : ''));
        return $said;
    }

    /**
     * Makes a MarketPay money call. The idempotency key is passed only if MarketPay's method takes a parameter named
     * $idempotencyKey. Without one, the 'returning' claim in rentalReturned is the only guard (f05).
     */
    private static function payCall(string $fn, array $args, string $key): mixed
    {
        static $takes = [];
        $takes[$fn] ??= in_array('idempotencyKey', array_map(fn (ReflectionParameter $p) => $p->getName(), (new ReflectionMethod(MarketPay::class, $fn))->getParameters()), true);
        if ($takes[$fn]) return MarketPay::$fn(...$args, idempotencyKey: $key);
        return MarketPay::$fn(...$args);
    }
=== END FILE ===
=== FILE: f05/tests/f05-rental-return-once.php ===
<?php
// tools/market-tests/f05-rental-return-once.php: checks the installed MarketCheckout.php. Plain PHP, prints PASS/FAIL.
// No database, no gateway, no money: it reads the source only.
declare(strict_types=1);

$file = getenv('MARKET_CHECKOUT') ?: dirname(__DIR__, 2) . '/app/lib/MarketCheckout.php';
$fails = 0;
function check(string $name, bool $ok): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . "\n"; if (!$ok) $fails++; }
function method_src(string $file, string $name): ?string
{
    $t = token_get_all((string) file_get_contents($file)); $n = count($t);
    for ($i = 0; $i < $n; $i++) {
        if (!is_array($t[$i]) || $t[$i][0] !== T_FUNCTION) continue;
        $j = $i + 1; while ($j < $n && is_array($t[$j]) && $t[$j][0] === T_WHITESPACE) $j++;
        if ($j >= $n || !is_array($t[$j]) || $t[$j][0] !== T_STRING || $t[$j][1] !== $name) continue;
        $out = ''; $depth = 0; $started = false;
        for ($k = $i; $k < $n; $k++) {
            $s = is_array($t[$k]) ? $t[$k][1] : $t[$k]; $out .= $s;
            if ($s === '{' || (is_array($t[$k]) && in_array($t[$k][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) { $depth++; $started = true; }
            elseif ($s === '}') { $depth--; if ($started && $depth === 0) return $out; }
        }
    }
    return null;
}

check('live file found: ' . $file, is_file($file));
if (!is_file($file)) exit(1);
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $lint, $rc);
check('php -l passes', $rc === 0);

$b = method_src($file, 'rentalReturned') ?? '';
check('rentalReturned found', $b !== '');
$claim = strpos($b, "SET pay_mode = 'returning'");
$cap = strpos($b, "payCall('capture'");
$ref = strpos($b, "payCall('refund'");
check('claims "returning" with a conditional UPDATE', $claim !== false && str_contains($b, 'AND pay_mode <=> ?') && str_contains($b, "status = 'paid'"));
check('claim checks rowCount before any money call', $claim !== false && ($rc2 = strpos($b, 'rowCount()', $claim)) !== false && $cap !== false && $rc2 < $cap && $ref !== false && $rc2 < $ref);
check('no direct MarketPay::capture/refund left in rentalReturned', !str_contains($b, 'MarketPay::capture(') && !str_contains($b, 'MarketPay::refund('));
check('idempotency key derived from the order id only', (bool) preg_match("/\\\$key = 'rental-return-' \\. \\(int\\) \\\$o\\['id'\\];/", $b));
check('record written only on the claimed row', str_contains($b, "SET pay_mode = 'returned', updated_at = ? WHERE id = ? AND pay_mode = 'returning'"));
$rec = strpos($b, "SET pay_mode = 'returned'");
check('record comes after the money call', $rec !== false && $cap !== false && $rec > $cap);
check('a gateway failure leaves it claimed and tickets our team', str_contains($b, 'GaugeTasks::add(') && str_contains($b, 'throw new RuntimeException('));
check('order is read fresh before the checks', strpos($b, 'self::get($pdo, (int) $o[\'id\'])') !== false && strpos($b, 'self::get($pdo, (int) $o[\'id\'])') < (int) strpos($b, "!== 'paid'"));
check('a zero refund is not sent', str_contains($b, '$card && $back > 0'));
$h = method_src($file, 'payCall') ?? '';
check('payCall passes the key only when MarketPay takes $idempotencyKey', str_contains($h, "'idempotencyKey'") && str_contains($h, 'idempotencyKey: $key'));

echo $fails ? "FAILED: $fails\n" : "ALL PASS\n";
exit($fails ? 1 : 0);
=== END FILE ===
=== FILE: f05/faq/faq-entries.md ===
no faq change
=== END FILE ===
=== FILE: f05/release.json ===
{
  "name": "f05-rental-return-once",
  "gate": "5",
  "what": "Marking a rental returned now moves its deposit money at most once. Before the gateway is called, a conditional UPDATE claims the order (pay_mode 'returning'), based on a fresh read of the order. The capture or refund carries an idempotency key, 'rental-return-<order id>', whenever MarketPay accepts one. 'returned' is written only on the row this call claimed. If the gateway call fails, the order stays claimed and a Work queue ticket goes to our team, so nothing can run twice.",
  "files": ["files/app/lib/MarketCheckout.php.region-1.php", "tests/f05-rental-return-once.php", "faq/faq-entries.md"],
  "decisions": [
    "When the gateway throws, the safest default is to leave the order at 'returning' and ticket our team. A throw can come after money moved (a timeout), so an automatic retry could double it. A person should decide whether retries can be automatic once MarketPay's idempotency key is confirmed.",
    "The idempotency key leaves out the amount. A retry with different damage is then refused by the gateway instead of paying twice.",
    "A charged (not held) rental with all of the deposit kept for damage no longer sends a $0 refund. Before, it called refund with 0."
  ],
  "tests": ["tests/f05-rental-return-once.php (install as tools/market-tests/f05-rental-return-once.php; reads the source only, no DB and no money)"],
  "risks": [
    "DRAFT: nothing here was run, linted or tested. The lane session must run php -l, the test, and a sandbox/mock rental return (twice, in two tabs).",
    "MarketPay.php is not shown. Unless capture()/refund() take a parameter named $idempotencyKey and send it (Stripe Idempotency-Key header, Square idempotency_key), no key reaches the gateway, and only the DB claim prevents the double run.",
    "If pay_mode is an ENUM rather than a VARCHAR, 'returning' won't fit and the claim UPDATE will fail or be truncated (schema not shown).",
    "An order left at 'returning' waits for our team. The business sees a message saying so.",
    "The finding's line numbers (726, 732-734) don't match the attached file's layout. Apply the region by its anchor text."
  ],
  "verify": [
    "file not shown: app/lib/MarketPay.php. Check the signatures of capture() and refund(). If neither has $idempotencyKey, add it in MarketPay (its own release) so the key is sent. payCall finds it by reflection and needs no change.",
    "file not shown: the market_orders schema. Confirm pay_mode is a VARCHAR wide enough for 'returning' (9 chars). If it's an ENUM, a safe-twice migration is needed first.",
    "Square: capture is called without an access token, as in the live code. Confirm MarketPay::capture fetches its own for Square.",
    "file not shown: the portal page that calls rentalReturned. Confirm it shows a thrown RuntimeException's message to the business, and that its 'Mark returned' button hides when pay_mode is 'returning'.",
    "grep for other code that reads pay_mode === 'hold' or 'returned', and make sure 'returning' is handled there.",
    "Sandbox run: mark one rental returned twice at once and confirm exactly one capture/refund appears in the gateway dashboard."
  ],
  "regions": true
}
=== END FILE ===
=== FILE: f06/files/app/lib/MarketCheckout.php.region-1.php ===
<?php
// ANCHOR: replaces `    public static function cancel(PDO $pdo, int $uid, array $o): void` through `        if ($booking) { try { Bookings::changed($pdo, $o, 'customer', 'cancelled before paying; the dates are free again'); } catch (Throwable $x) { error_log('booking cancelled notify: ' . $x->getMessage()); } }` and the method's closing `    }` on the next line. The docblock above it stays.
// (The `<?php` line and this ANCHOR comment are not pasted.)
    public static function cancel(PDO $pdo, int $uid, array $o): void
    {
        if ((int) $o['buyer_id'] !== $uid) throw new InvalidArgumentException('That order isn\'t yours.');
        $o = self::get($pdo, (int) $o['id']) ?? $o;   // read fresh: what it is now decides (f06)
        // A card order still being paid has an open payment at the gateway. Cancelling here wouldn't close that payment, so a
        // later payment would land on a cancelled order. It's refused; the order expires by itself after its hold (f06).
        if ($o['status'] === 'pending' && $o['method'] === 'card') throw new InvalidArgumentException('Its card payment is still open, so it can\'t be cancelled here. If you don\'t pay, it\'s released on its own within ' . self::CARD_HOLD_MIN . ' minutes of placing it. If you were charged, write to support@traversence.com with order ' . (int) $o['id'] . '.');
        // data_reason: f06. The buyer cancels an unpaid order waiting for a direct payment. Card orders being paid are excluded.
        $st = $pdo->prepare("UPDATE market_orders SET status = 'cancelled', updated_at = ? WHERE id = ? AND (status = 'awaiting' OR (status = 'pending' AND method <> 'card'))");
        $st->execute([self::now(), (int) $o['id']]);
        if (!$st->rowCount()) throw new InvalidArgumentException('It can\'t be cancelled now.');
        require_once __DIR__ . '/Bookings.php';
        $booking = Bookings::of($o) !== null;   // a stay or a rental: the team is told as a booking (biz.booking, decisions/0172 §2.5), once
        if ($o['biz_quote_id']) self::notify($pdo, $o, 'Order #' . (int) $o['id'] . ' was cancelled by the buyer before paying. Void it in Quotes & invoices.', $booking ? '' : 'biz.order');
        if ($booking) { try { Bookings::changed($pdo, $o, 'customer', 'cancelled before paying; the dates are free again'); } catch (Throwable $x) { error_log('booking cancelled notify: ' . $x->getMessage()); } }
    }
=== END FILE ===
=== FILE: f06/files/app/lib/MarketCheckout.php.region-2.php ===
<?php
// ANCHOR: replaces `    public static function confirmAndMark(PDO $pdo, int $id): bool` through `        return $paid ? self::markPaid($pdo, $id, $ref) : false;` and the method's closing `    }` on the next line. The docblock above it stays.
// (The `<?php` line and this ANCHOR comment are not pasted.)
    public static function confirmAndMark(PDO $pdo, int $id): bool
    {
        $o = self::get($pdo, $id);
        if (!$o || $o['method'] !== 'card') return false;
        if ($o['status'] === 'cancelled') {   // paid at the gateway after it was cancelled: nothing is recorded on the order; our team gets one ticket (f06)
            if ((string) $o['provider_ref'] === '') return false;   // no payment was ever opened
            $g = self::gateway($pdo, (int) $o['entity_id']);
            if (!$g) return false;
            [$paid, $ref] = MarketPay::confirm($g, $o, self::access($pdo, $g));
            if (!$paid) return false;
            $mark = 'Paid by card after it was cancelled';
            // data_reason: f06. Notes once that a cancelled card order was paid at the gateway, so only one ticket is made.
            $st = $pdo->prepare("UPDATE market_orders SET note = CONCAT(note, ?), updated_at = ? WHERE id = ? AND status = 'cancelled' AND note NOT LIKE ?");
            $st->execute([' [' . date('M j') . '] ' . $mark . ' (' . mb_substr((string) $ref, 0, 80) . '): our team has it.', self::now(), $id, '%' . $mark . '%']);
            if ($st->rowCount() && !str_starts_with((string) $o['note'], self::TEST_NOTE)) {   // a setup test purchase (sandbox) is noted, not ticketed
                try {
                    require_once __DIR__ . '/GaugeTasks.php';
                    GaugeTasks::add($pdo, 'area:backlog', 'Marketplace order #' . $id . ' was paid by card after it was cancelled',
                        'Gate 5: ' . (MarketPay::PROVIDERS[$o['provider']] ?? 'The gateway') . ' says order #' . $id . ' (business ' . (int) $o['entity_id'] . ', ' . self::money((int) $o['total_cents']) . ') is paid (ref ' . (string) $ref . '). The order is cancelled, so nothing was recorded: no stock taken, no copy in the business\'s Quotes & invoices, no receipt to the buyer. '
                        . 'Decide with the business: refund it in their own ' . (MarketPay::PROVIDERS[$o['provider']] ?? 'gateway') . ' account, or reinstate the order. markPaid doesn\'t take a cancelled order, so that is a deliberate step.',
                        'MarketCheckout (a paid, cancelled order)', 'the Marketplace', 0);
                } catch (Throwable $x) { error_log('market paid-after-cancel ticket ' . $id . ': ' . $x->getMessage()); }
            }
            return false;
        }
        if (!in_array($o['status'], ['pending', 'expired'], true)) return false;
        $g = self::gateway($pdo, (int) $o['entity_id']);
        if (!$g) return false;
        $c = MarketPay::confirm($g, $o, self::access($pdo, $g));
        [$paid, $ref] = $c;
        if ($paid && !empty($c[2]) && KindCheckout::ready($pdo)) $pdo->prepare('UPDATE market_orders SET sub_ref = ? WHERE id = ?')->execute([(string) $c[2], $id]);   // a subscription: its own reference, to manage it
        return $paid ? self::markPaid($pdo, $id, $ref) : false;
    }
=== END FILE ===
=== FILE: f06/tests/f06-no-cancel-open-card.php ===
<?php
// tools/market-tests/f06-no-cancel-open-card.php: checks the installed MarketCheckout.php. Plain PHP, prints PASS/FAIL.
// No database, no gateway, no money: it reads the source only.
declare(strict_types=1);

$file = getenv('MARKET_CHECKOUT') ?: dirname(__DIR__, 2) . '/app/lib/MarketCheckout.php';
$fails = 0;
function check(string $name, bool $ok): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . "\n"; if (!$ok) $fails++; }
function method_src(string $file, string $name): ?string
{
    $t = token_get_all((string) file_get_contents($file)); $n = count($t);
    for ($i = 0; $i < $n; $i++) {
        if (!is_array($t[$i]) || $t[$i][0] !== T_FUNCTION) continue;
        $j = $i + 1; while ($j < $n && is_array($t[$j]) && $t[$j][0] === T_WHITESPACE) $j++;
        if ($j >= $n || !is_array($t[$j]) || $t[$j][0] !== T_STRING || $t[$j][1] !== $name) continue;
        $out = ''; $depth = 0; $started = false;
        for ($k = $i; $k < $n; $k++) {
            $s = is_array($t[$k]) ? $t[$k][1] : $t[$k]; $out .= $s;
            if ($s === '{' || (is_array($t[$k]) && in_array($t[$k][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY