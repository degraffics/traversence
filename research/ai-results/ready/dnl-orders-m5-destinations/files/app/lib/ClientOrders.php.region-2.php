<?php
// ANCHOR: replaces "    public static function deleteTest(PDO $pdo, int $oid): void" through "        $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$oid]);"
// The method's docblock above it and its closing "    }" below it stay as they are in the live file.
// One change: order_invoices (2027-02-10_order_invoices.sql, M5) is cleared with a sandbox order. Before that migration
// runs, $q swallows the missing table, as it already does for the others.
    public static function deleteTest(PDO $pdo, int $oid): void
    {
        $o = Orders::get($pdo, $oid);
        if (!$o) throw new RuntimeException('That order is already gone.');
        if (!Orders::isTest($o)) throw new RuntimeException('Only a sandbox (test) order can be deleted. A real one is cancelled, and kept.');
        require_once __DIR__ . '/Store.php';
        $q = function (string $sql) use ($pdo, $oid) { try { $pdo->prepare($sql)->execute([$oid]); } catch (Throwable $x) { } };   // a table from a migration not run yet: nothing to clear
        try { foreach ($pdo->query('SELECT * FROM order_proofs WHERE order_id = ' . $oid)->fetchAll(PDO::FETCH_ASSOC) as $p) if ($p['mime'] !== 'go-code') Store::remove(self::proofPath($p)); } catch (Throwable $x) { }
        try { foreach ($pdo->query('SELECT * FROM order_files WHERE order_id = ' . $oid)->fetchAll(PDO::FETCH_ASSOC) as $f) Store::remove(self::filePath($f)); } catch (Throwable $x) { }
        $q('DELETE FROM order_line_parts WHERE line_id IN (SELECT id FROM order_lines WHERE order_id = ?)');
        $q('DELETE FROM order_pack_items WHERE pack_id IN (SELECT id FROM order_packs WHERE order_id = ?)');
        foreach (['order_proofs', 'order_files', 'order_packs', 'order_lots', 'order_addresses', 'order_invoices', 'order_vendor_jobs', 'order_work_orders', 'vendor_jobs', 'order_projects', 'promotion_uses', 'order_ledger', 'order_events', 'order_lines'] as $t) $q("DELETE FROM $t WHERE order_id = ?");
        $q('DELETE FROM qr_issued WHERE order_id = ?');
        $q('UPDATE order_inbox SET order_id = NULL WHERE order_id = ?');
        $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$oid]);
