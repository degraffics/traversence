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
