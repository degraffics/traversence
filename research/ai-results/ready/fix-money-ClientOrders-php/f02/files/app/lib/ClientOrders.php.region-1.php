<?php
// ANCHOR: replaces |    public static function offeredMode(string $mode): string { return in_array($mode, self::OFFERED, true) ? $mode : 'full'; }|
// through |    public static function offeredMode(string $mode): string { return in_array($mode, self::OFFERED, true) ? $mode : 'full'; }|
// (one line; the lane pastes everything below this comment in its place)
    public static function offeredMode(string $mode): string { return in_array($mode, self::OFFERED, true) ? $mode : 'full'; }

    /**
     * Why this order can't be invoiced or charged ('' when it can): a test copy (duplicate() with $test: its name starts
     * "TEST · ", or Orders::isTest) whose QuickBooks mode isn't Sandbox (or the local mock). Jason, 2026-11-10: a test copy
     * is always a sandbox order, no real money, ever. The name is checked as well as Orders::isTest, because a test copy
     * whose qb_env was overwritten with Production may no longer read as a test (f02).
     */
    public static function testEnvBlock(array $o): string
    {
        $test = str_starts_with((string) ($o['bill_name'] ?? ''), 'TEST · ');
        if (!$test) { try { $test = Orders::isTest($o); } catch (Throwable) { $test = false; } }
        if (!$test) return '';
        $env = (string) ($o['qb_env'] ?? '');
        if (in_array($env, ['sandbox', 'mock'], true)) return '';
        return 'This order is a test copy, but it\'s set to QuickBooks ' . ($env !== '' ? $env : '(no mode recorded)') . '. A test is invoiced and paid in Sandbox only, so nothing real is charged: make a new test copy.';
    }

    /**
     * $fn run in the QuickBooks mode the order was made in (its qb_env), never in whatever mode Payments is in now, and
     * refused for a test copy outside Sandbox (testEnvBlock). Wrap invoiceFor() and the card charge in this (f02).
     */
    public static function inOrderEnv(array $o, callable $fn): mixed
    {
        if (($why = self::testEnvBlock($o)) !== '') throw new RuntimeException($why);
        require_once __DIR__ . '/QuickBooksPay.php';
        return QuickBooksPay::withEnv((string) (($o['qb_env'] ?? '') ?: QuickBooksPay::env()), $fn);
    }
