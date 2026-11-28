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
