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
