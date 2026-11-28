<?php
// ANCHOR: replaces |    public static function depositFor(PDO $pdo, array $o, int $total, ?array $lines = null): array|
// through |    /** Who to tell about the client's answers: the staff member who built it (their work email), else the site's address. */|
// (the lane pastes everything below this comment in its place; the last line is kept as it was)
    public static function depositFor(PDO $pdo, array $o, int $total, ?array $lines = null): array
    {
        $pct = (int) ($o['deposit_pct'] ?? 0);
        if ($pct > 0) return [min($total, (int) round($total * $pct / 100)), $pct . '% of the total, as set', [], []];
        $lines ??= Orders::lines($pdo, (int) $o['id']);
        // 0177 §3: only a COGS that's recorded and checked (Costing, the Cost tab) is figured from cost; unchecked or zero is 65%
        return self::depositFromLines($lines, $total, fn (array $l): bool => self::costed($pdo, (int) ($l['id'] ?? 0)));
    }

    /**
     * depositFor's sum, given which lines' COGS is checked ($checked($line) => bool). A checked item: (qty × cost_cents +
     * ship_cost_cents) × (100 + DEPOSIT_MARKUP)%; shipping we pay goes on top of its COGS, never counts as a COGS alone
     * (f16). Any other item: DEPOSIT_DEFAULT % of its price (with the shipping charged). Never above $total.
     * Returns [cents, words for our team, the titles and ids of items without a checked cost].
     */
    public static function depositFromLines(array $lines, int $total, callable $checked): array
    {
        $cost = 0; $guess = 0; $open = []; $openIds = [];
        foreach ($lines as $l) {
            if (($l['status'] ?? '') === 'cancelled') continue;
            $qty = max(1, (int) ($l['qty'] ?? 1));
            $cogs = $qty * (int) ($l['cost_cents'] ?? 0);
            if ($cogs > 0 && $checked($l)) { $cost += $cogs + max(0, (int) ($l['ship_cost_cents'] ?? 0)); continue; }
            $price = $qty * (int) ($l['unit_cents'] ?? 0) + (int) ($l['ship_cents'] ?? 0);
            if ($price <= 0) continue;
            $guess += (int) round($price * self::DEPOSIT_DEFAULT / 100); $open[] = (string) ($l['title'] ?? ''); $openIds[] = (int) ($l['id'] ?? 0);
        }
        $dep = min($total, (int) round($cost * (100 + self::DEPOSIT_MARKUP) / 100) + $guess);
        $words = 'items\' cost + ' . self::DEPOSIT_MARKUP . '%' . ($open ? '; ' . count($open) . ' item' . (count($open) === 1 ? '' : 's') . ' at ' . self::DEPOSIT_DEFAULT . '% of price (cost not checked)' : '');
        return [$dep, $words, $open, $openIds];
    }

    /** Who to tell about the client's answers: the staff member who built it (their work email), else the site's address. */
