<?php
// app/lib/Scope.php — whose data a shared tool is working on (business-portal-architecture.md §1, decisions/0110). By
// default it's ours: owner 0, our tables wherever Database::t() puts them. The business router switches it, once per
// request, to one business: the owner becomes that listing, the connection is the business gateway's (the listings
// login, which can't reach any other database) and every table becomes its tenant view (b_<table>), which shows that
// business's rows only. A tool written for us works for a business with nothing written twice.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Scope
{
    private static int $owner = 0;
    private static ?PDO $pdo = null;

    /** Switches this request to one business. Only the business router and gateway call this, and only once. */
    public static function enterBusiness(PDO $businessPdo, int $listingId): void
    {
        if (self::$pdo !== null) throw new LogicException('This request is already working for a business.');
        if ($listingId <= 0) throw new InvalidArgumentException('Not a business.');
        self::$pdo = $businessPdo;
        self::$owner = $listingId;
    }

    /**
     * Ends one business switch: the request is ours again (owner 0, our connection). Only BusinessData::scoped calls this,
     * in its finally, with the connection it entered with; any other connection is refused, so the portal's own switch
     * (the business router's) is never undone from here.
     */
    public static function leave(PDO $businessPdo): void
    {
        if (self::$pdo !== $businessPdo) throw new LogicException('Only the connection that entered a business can leave it.');
        self::$pdo = null;
        self::$owner = 0;
    }

    private static array $people = [];

    /** The team's names (user id => name), handed in by the router: the business realm can't read members itself. */
    public static function setPeople(array $people): void { self::$people = $people; }
    public static function person(int $uid): string { return (string) (self::$people[$uid] ?? ($uid ? 'A team member' : '')); }

    public static function isBusiness(): bool { return self::$pdo !== null; }

    /** The owner whose rows a tool reads and writes: 0 (Traversence) or the business. */
    public static function owner(): int { return self::$owner; }

    /** The connection a tool must use: the business gateway's inside the business portal, else the one it was given. */
    public static function pdo(PDO $ours): PDO { return self::$pdo ?? $ours; }

    /** A table's name: its tenant view for a business, else wherever our data keeps it. */
    public static function t(string $table): string { return self::$pdo !== null ? '`b_' . str_replace('`', '', $table) . '`' : Database::t($table); }
}
