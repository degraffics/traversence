**Skipped first.** Three parts of the work are not drafted, because the attached documents put them on hold or outside M4:

- **Retiring the archived E-Commerce orders page.** The design says "Jason clears the archive entry" (§2, `archive.md` 49). That step waits for Jason, and `admin/ecommerce.php` isn't attached anyway.
- **Several destinations, credit terms and categories.** These are M5 (0177 §1), M7 (0178 §4, granted by Jason only) and M6. The guided steps say "coming" for each and route around them. A multi-address order is told to send each address as its own order, as 0177 requires until release B.
- **M2b.** Jason paused it on 2026-11-24. M4 doesn't touch it.

**Review of the attached DRAFT (job 19).** The file is empty (0 bytes), so there are no patches, tests or FAQ to check. I rebuilt only what the attached live code supports. Most of M4 cannot be drafted honestly:

- **The page itself.** The guided steps belong in `admin/client-order.php` (lines 459-481 per design §1.1). That file isn't attached, so I can't quote or patch it.
- **The board wording.** `tools.php` (`tv_order_next_step`) isn't attached either.
- **The library file is partial.** `app/lib/ClientOrders.php` is attached but truncated and well over 60 KB, so it's given as a region.

What this release does contain is the groundwork: a pure `ClientOrders::placeSteps()`. It works out the five steps, which are done, which is current, and the routing by choices already made (design §4.2, §3.5). It has a DB-free test and the FAQ entries. It is not wired into any page.

=== FILE: files/app/lib/ClientOrders.php.region-1.php ===
<?php
// ANCHOR: replaces     public static function offeredMode(string $mode): string { return in_array($mode, self::OFFERED, true) ? $mode : 'full'; } through     public static function offeredMode(string $mode): string { return in_array($mode, self::OFFERED, true) ? $mode : 'full'; }
// (the one line is kept as it was; placeSteps() is added after it, inside class ClientOrders)

    public static function offeredMode(string $mode): string { return in_array($mode, self::OFFERED, true) ? $mode : 'full'; }

    /**
     * Place an order, guided (research/order-management-design-2026-11-23.md §4.2, M4): the five steps of one record page
     * that fills in from the top: Client, Items, Where it goes and tax, Payment terms, Review and send. A finished step
     * folds to one line (its summary) with Change; the first one not done is current. The steps that follow are routed
     * by the choices made (§3.5): a pickup asks for no address; nothing to deliver has no delivery links later.
     * Not built yet, and said so where it shows: several destinations (decisions/0177 §1, M5: send each address as its
     * own order until then) and credit terms (decisions/0178 §4, M7, granted by Jason only).
     * Pure: reads the order row and its lines (Orders::lines), writes nothing.
     * Returns a list of ['key', 'label', 'done' => bool, 'current' => bool, 'summary', 'note'].
     */
    public static function placeSteps(array $o, array $lines): array
    {
        $live = array_values(array_filter($lines, fn ($l) => ($l['status'] ?? '') !== 'cancelled'));
        $items = array_values(array_filter($live, fn ($l) => ($l['product'] ?? '') !== 'shipping'));
        $hasShipLine = count($live) > count($items);
        $steps = [];

        // 1. Client
        $name = trim((string) ($o['bill_name'] ?? '')); $email = trim((string) ($o['bill_email'] ?? '')); $co = trim((string) ($o['client_company'] ?? ''));
        $steps[] = ['key' => 'client', 'label' => 'Client', 'done' => $name !== '' && (bool) filter_var($email, FILTER_VALIDATE_EMAIL),
            'summary' => $name !== '' ? $name . ($co !== '' ? ' (' . $co . ')' : '') . ($email !== '' ? ' · ' . $email : '') : '', 'note' => ''];

        // 2. Items
        $n = count($items);
        $steps[] = ['key' => 'items', 'label' => 'Items', 'done' => $n > 0,
            'summary' => $n ? $n . ' item' . ($n === 1 ? '' : 's') . ' · ' . Orders::usd((int) ($o['subtotal_cents'] ?? 0)) . ' before tax' : '', 'note' => ''];

        // 3. Where it goes and tax (routed by how it reaches the client)
        $how = (string) ($o['delivery'] ?? '');
        if ($how === '' && $hasShipLine) $how = 'ship';   // DELIVERY: '' = shipped when the order has a shipping line
        $where = ['key' => 'where', 'label' => 'Where it goes and tax', 'done' => false, 'summary' => '', 'note' => ''];
        if ($how === 'pickup') { $where['done'] = true; $where['summary'] = 'The client picks it up at our shop: no address needed; taxed at our shop.'; }
        elseif ($how === 'none') { $where['done'] = true; $where['summary'] = 'Nothing to deliver (GO Codes, services).'; }
        elseif ($how === 'ship') {
            $addr = array_map(fn ($c) => trim((string) ($o[$c] ?? '')), ['ship_street', 'ship_city', 'ship_state', 'ship_zip']);
            $where['done'] = !in_array('', $addr, true);
            $where['summary'] = $where['done'] ? 'Shipped to ' . $addr[0] . ', ' . $addr[1] . ', ' . $addr[2] . ' ' . $addr[3] . '; taxed for that address.' : '';
            $where['note'] = ($where['done'] ? '' : 'Add the address it ships to: the tax follows it. ')
                . 'Going to more than one address? Send each address as its own order for now; one order with an invoice per address is coming.';
        } else $where['note'] = 'Choose: we ship it, the client picks it up, or nothing to deliver.';
        $steps[] = $where;

        // 4. Payment terms (only what's offered; invoice later isn't, credit terms aren't built)
        $mode = (string) ($o['pay_mode'] ?? 'full');
        $terms = ['key' => 'terms', 'label' => 'Payment terms', 'done' => in_array($mode, self::OFFERED, true), 'summary' => '',
            'note' => 'Credit terms (Net 15 or Net 30) are coming; only Jason grants them.'];
        if ($terms['done']) {
            $pct = (int) ($o['deposit_pct'] ?? 0);
            $terms['summary'] = self::PAY_MODES[$mode] . ($mode === 'deposit' ? ' (' . ($pct > 0 ? $pct . '% of the total, as set' : 'the items\' cost + ' . self::DEPOSIT_MARKUP . '%; ' . self::DEPOSIT_DEFAULT . '% of price for an item whose cost isn\'t recorded') . ')' : '');
        } else $terms['note'] = 'Invoice later isn\'t offered any more: choose Pay in full or a Deposit. ' . $terms['note'];
        $steps[] = $terms;

        // 5. Review and send
        $sent = !in_array((string) ($o['status'] ?? 'draft'), ['draft'], true);
        $steps[] = ['key' => 'review', 'label' => 'Review and send', 'done' => $sent,
            'summary' => $sent ? 'Sent' . (!empty($o['sent_at']) ? ' ' . date('M j', strtotime((string) $o['sent_at'])) : '') . '.' : '', 'note' => $sent ? '' : 'See what the client will see, then Send the order (or keep it as a draft).'];

        $cur = false;
        foreach ($steps as &$s) { $s['current'] = !$cur && !$s['done']; if ($s['current']) $cur = true; }
        unset($s);
        return $steps;
    }
=== END FILE ===

=== FILE: tests/place-steps.php ===
<?php
// tools/orders-tests/place-steps.php — ClientOrders::placeSteps() (M4, Place an order, guided). No database, no money:
// placeSteps is pure (it reads arrays), so nothing here touches QuickBooks or the order tables.
// Run from the repo: php tools/orders-tests/place-steps.php   (or set TV_LIB to app/lib's path)
declare(strict_types=1);

$lib = getenv('TV_LIB') ?: __DIR__ . '/../../app/lib';
require_once $lib . '/ClientOrders.php';

$fail = 0;
$t = function (string $what, bool $ok) use (&$fail) { echo ($ok ? 'PASS' : 'FAIL') . ' ' . $what . "\n"; if (!$ok) $fail++; };
$by = fn (array $steps) => array_column($steps, null, 'key');

$base = ['status' => 'draft', 'bill_name' => 'Ana Ruiz', 'bill_email' => 'ana@example.com', 'client_company' => '', 'subtotal_cents' => 12000,
         'delivery' => '', 'pay_mode' => 'full', 'deposit_pct' => 0, 'ship_street' => '', 'ship_city' => '', 'ship_state' => '', 'ship_zip' => ''];
$item = ['product' => 'product', 'status' => 'open', 'title' => 'Banner · 1'];

// a new draft, client only: Items is current
$s = $by(ClientOrders::placeSteps($base, []));
$t('client done with a name and email', $s['client']['done']);
$t('no items: Items is the current step', !$s['items']['done'] && $s['items']['current']);
$t('only one current step', count(array_filter(array_column(ClientOrders::placeSteps($base, []), 'current'))) === 1);

// a cancelled line and a shipping line aren't items
$s = $by(ClientOrders::placeSteps($base, [['product' => 'product', 'status' => 'cancelled'], ['product' => 'shipping', 'status' => 'open']]));
$t('cancelled and shipping lines are not items', !$s['items']['done']);
$t('a shipping line with delivery blank routes to shipped', str_contains($s['where']['note'], 'address it ships to'));

// pickup: no address asked
$s = $by(ClientOrders::placeSteps(['delivery' => 'pickup'] + $base, [$item]));
$t('pickup is done without an address', $s['where']['done'] && str_contains($s['where']['summary'], 'our shop'));
$t('pickup says nothing about several addresses', $s['where']['note'] === '');

// shipped: address needed; several destinations not offered (0177)
$s = $by(ClientOrders::placeSteps(['delivery' => 'ship'] + $base, [$item]));
$t('shipped without an address is not done, and current', !$s['where']['done'] && $s['where']['current']);
$t('shipped says each address goes as its own order', str_contains($s['where']['note'], 'its own order'));
$full = ['delivery' => 'ship', 'ship_street' => '1 Main St', 'ship_city' => 'Show Low', 'ship_state' => 'AZ', 'ship_zip' => '85901'] + $base;
$s = $by(ClientOrders::placeSteps($full, [$item]));
$t('shipped with a full address is done', $s['where']['done'] && str_contains($s['where']['summary'], 'Show Low'));

// terms: invoice later isn't offered; credit terms are coming; deposit says how it's figured
$s = $by(ClientOrders::placeSteps(['pay_mode' => 'invoice', 'delivery' => 'none'] + $base, [$item]));
$t('invoice later is not done', !$s['terms']['done'] && str_contains($s['terms']['note'], 'isn\'t offered'));
$t('credit terms said as coming', str_contains($s['terms']['note'], 'coming'));
$s = $by(ClientOrders::placeSteps(['pay_mode' => 'deposit', 'delivery' => 'none'] + $base, [$item]));
$t('deposit with no % is cost + 15%', str_contains($s['terms']['summary'], 'cost + 15%'));
$s = $by(ClientOrders::placeSteps(['pay_mode' => 'deposit', 'deposit_pct' => 40, 'delivery' => 'none'] + $base, [$item]));
$t('deposit with a % says it', str_contains($s['terms']['summary'], '40%'));

// all filled on a draft: Review and send is current; once sent, nothing is current
$s = $by(ClientOrders::placeSteps(['delivery' => 'none'] + $base, [$item]));
$t('everything filled: Review and send is current', $s['review']['current'] && !$s['review']['done']);
$s = ClientOrders::placeSteps(['delivery' => 'none', 'status' => 'sent', 'sent_at' => '2026-12-01 10:00:00'] + $base, [$item]);
$t('sent: no current step', !in_array(true, array_column($s, 'current'), true));

echo $fail ? "FAIL: $fail\n" : "PASS: all\n";
exit($fail ? 1 : 0);
=== END FILE ===

=== FILE: faq/faq-entries.md ===
## How does your team place an order for me? (coming)

Our team builds it with you in five steps:

1. Who it's for.
2. What's in it.
3. Where it goes and the tax.
4. How it's paid.
5. A last look before it's sent.

You get a private link to review the proofs, approve the order and pay. This guided way of placing an order is **coming**. Until then our team builds the order the way it does today, and you see the same link.

## Can one order ship to several addresses? (coming)

Not yet. For now, each address is its own order, so each is taxed for where it goes. One order with an invoice per address is **coming**.

## Can I pay on terms, like Net 15 or Net 30? (coming)

Terms are **coming**, granted to an account after a credit check. Today an order is paid in full when you approve it, or by a deposit then and the rest before delivery.

## How is a deposit worked out?

Usually it's what the items cost us to make and ship, plus 15%, and never more than the order. If an item's cost isn't recorded yet, that item counts at 65% of its price. Our team can also set a percentage for your order. Your order page shows the amount before you approve.
=== END FILE ===

=== FILE: release.json ===
{
  "name": "orders-m4-place-order-groundwork",
  "gate": "not set: no gate scheme is attached; the lane session sets it. This is groundwork, not M4 complete",
  "what": "Orders M4 (research/order-management-design-2026-11-23.md §4.2, §6), groundwork only. ClientOrders::placeSteps(), a pure function giving Place an order's five guided steps (Client, Items, Where it goes and tax, Payment terms, Review and send): which are done, which is current, a one-line summary for a folded step, and routing by the choices made (pickup asks no address; nothing to deliver; deposit shown as cost + 15%). Several destinations and credit terms are said as coming. NOT wired into any page: admin/client-order.php, tools.php and admin/ecommerce.php were not attached, so the guided page and the archived page's redirect are not drafted. The attached job-19 DRAFT was empty (0 bytes); nothing from it was used.",
  "skipped": [
    "Retiring /admin/ecommerce.php#orders (redirect, remove its New order): waits for Jason, per the design §2 ('Jason clears the archive entry', archive.md 49); file not shown either",
    "Several destinations, one invoice each: decisions/0177 §1, M5; until then an order to more than one address is sent as one order per address (said in the step)",
    "Credit terms Net 15 / Net 30: decisions/0178 §4, M7, granted by Jason only (said as coming)",
    "A category on a new product or service in step 2: M6, not built",
    "M2b (how an order goes): paused by Jason 2026-11-24; not touched"
  ],
  "files": [
    "files/app/lib/ClientOrders.php.region-1.php",
    "tests/place-steps.php",
    "faq/faq-entries.md"
  ],
  "decisions": [
    "research/order-management-design-2026-11-23.md §3.5, §4.2, §6 M4 (approved as the plan, Jason, 2026-11-23)",
    "decisions/0177 §1 (until release B an order to several addresses isn't approved: send each address as its own order), §3 (deposit: cost + 15%, 65% where cost isn't recorded)",
    "decisions/0178 §4 (credit terms: Net 15/Net 30, Jason only, M7)",
    "ClientOrders::OFFERED (invoice later not offered; decisions/0101 note 2026-11-23)",
    "Jason's rule, 2026-11-23: a finished step folds to one line with Change"
  ],
  "tests": [
    "tests/place-steps.php → tools/orders-tests/place-steps.php: pure, no database, no QuickBooks, no money; prints PASS/FAIL per check and exits 1 on any FAIL"
  ],
  "risks": [
    "Nothing here was run, tested, linted or zip-checked: it is a DRAFT for the lane session to verify",
    "placeSteps() is unused until admin/client-order.php calls it; shipping it alone changes nothing visible",
    "The test requires app/lib/ClientOrders.php, which requires Orders.php, Products.php and Store.php at load. If Products.php or Store.php need a database or config at load time (not shown), the test fails to start rather than failing a check",
    "The tax choice (release A, order page Terms) is not read: its column name isn't in the attached code. Step 3 is done on the delivery and address alone; the lane session decides whether step 3 also waits on the tax choice",
    "Payment terms counts as done whenever pay_mode is offered, and create() sets 'full', so step 4 is done by default. If staff must confirm it, that needs a flag that doesn't exist yet",
    "The queue (handoffs/decided-not-live.md) puts M3 and M3's first fix (the order tests green again) before M4; the live Work queue isn't attached, so the order isn't checked",
    "The region anchor is one line (offeredMode), kept unchanged, with the new method after it; check that the line in live is byte-identical before applying"
  ],
  "verify": [
    "admin/client-order.php: file not shown. The guided page (design §4.2) and its use of placeSteps() are not drafted; the lane session drafts them against the live file (lines 459-481 per design §1.1)",
    "app/admin/backoffice tools.php (tv_order_next_step, tiles): file not shown; not drafted",
    "admin/ecommerce.php and archive.md: file not shown, and the redirect waits for Jason clearing the archive entry",
    "app/lib/Products.php, app/lib/Store.php: file not shown; check they load without a database for the test",
    "app/lib/ClientOrders.php: attached truncated (over 60 KB), hence regions; confirm the anchor line matches exactly and the method lands inside the class",
    "PHP 8.0: no 8.1+ syntax used (no enums, readonly, never, first-class callables); str_contains/str_starts_with are 8.0",
    "No migration in this release; no UPDATE or DELETE"
  ],
  "regions": true
}
=== END FILE ===