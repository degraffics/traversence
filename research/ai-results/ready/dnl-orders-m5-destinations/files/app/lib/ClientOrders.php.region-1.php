<?php
// ANCHOR: replaces "    public static function depositFor(PDO $pdo, array $o, int $total, ?array $lines = null): array" through "        return [$dep, $words, $open, $openIds];"
// The method's docblock above it and its closing "    }" below it stay as they are in the live file.
// Same behaviour as before (the rule moved to OrderDestinations::deposit so each destination's invoice uses it too):
// lines are read only when no staff % is set, as before.
    public static function depositFor(PDO $pdo, array $o, int $total, ?array $lines = null): array
    {
        require_once __DIR__ . '/OrderDestinations.php';   // one rule for the order and for each destination's invoice (decisions/0177 §3; M5)
        if ((int) ($o['deposit_pct'] ?? 0) <= 0) $lines ??= Orders::lines($pdo, (int) $o['id']);
        return OrderDestinations::deposit($o, $total, $lines ?? [], self::DEPOSIT_MARKUP, self::DEPOSIT_DEFAULT);
