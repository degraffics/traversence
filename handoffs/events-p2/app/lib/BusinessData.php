<?php
// app/lib/BusinessData.php — the business realm's one way into data (decisions/0110, walls 2 to 4). It signs in as the
// listings login, which reaches only the listings database (never main, our admin data or members' data), on a
// connection of its own, and sets that connection to one business (@tv_tenant) before any tool runs. In the listings
// database every business table has owner_id (the listing), and the tools see it only through its tenant view
// (b_<table>: that business's rows and nothing else); triggers refuse any write, update or delete of another
// business's row, even one that went around the view. Opened only by the business router, after it has checked the
// person's role on that listing.
declare(strict_types=1);

require_once __DIR__ . '/Scope.php';
require_once __DIR__ . '/Commerce.php';   // its tables: a business's own tax rates, shipping and settings (decisions/0119)

final class BusinessData
{
    private const VERSION = 8;   // raise when the tables below change, so each database rebuilds its views and triggers

    /** The business tables: name => columns. Every one has owner_id (the listing); tools read them through b_<name>. */
    public const TABLES = [
        'crm_customers' => '(id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, owner_id INT UNSIGNED NOT NULL DEFAULT 0, email VARCHAR(190) NOT NULL DEFAULT \'\',
            name VARCHAR(160) NOT NULL DEFAULT \'\', company VARCHAR(160) NOT NULL DEFAULT \'\', phone VARCHAR(40) NOT NULL DEFAULT \'\',
            user_id INT UNSIGNED DEFAULT NULL, entity_id INT UNSIGNED DEFAULT NULL, qb_customer_id VARCHAR(40) NOT NULL DEFAULT \'\',
            first_at DATETIME DEFAULT NULL, last_at DATETIME DEFAULT NULL, orders_n INT UNSIGNED NOT NULL DEFAULT 0, plans_n INT UNSIGNED NOT NULL DEFAULT 0,
            plans_active INT UNSIGNED NOT NULL DEFAULT 0, spent_cents BIGINT NOT NULL DEFAULT 0, refunded_cents BIGINT NOT NULL DEFAULT 0,
            manual TINYINT(1) NOT NULL DEFAULT 0, edited TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_crm_owner_email (owner_id, email), KEY idx_crm_last (owner_id, last_at))',
        'crm_tags' => '(id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, owner_id INT UNSIGNED NOT NULL DEFAULT 0, name VARCHAR(40) NOT NULL, UNIQUE KEY uq_crm_tag (owner_id, name))',
        'crm_customer_tags' => '(customer_id INT UNSIGNED NOT NULL, tag_id INT UNSIGNED NOT NULL, owner_id INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (customer_id, tag_id), KEY idx_crm_ct_owner (owner_id, tag_id))',
        'crm_notes' => '(id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, customer_id INT UNSIGNED NOT NULL, owner_id INT UNSIGNED NOT NULL DEFAULT 0,
            body TEXT NOT NULL, by_user INT UNSIGNED DEFAULT NULL, created_at DATETIME NOT NULL, KEY idx_crm_notes (owner_id, customer_id, created_at))',
        'crm_sync' => '(owner_id INT UNSIGNED NOT NULL PRIMARY KEY, synced_at DATETIME NOT NULL)',
        // the catalog (decisions/0116): as ours (Products), with the business's own codes and kinds of item
        'products' => '(id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, owner_id INT UNSIGNED NOT NULL DEFAULT 0, sku VARCHAR(40) NOT NULL, name VARCHAR(120) NOT NULL,
            kind VARCHAR(12) NOT NULL DEFAULT \'item\', service_type VARCHAR(12) NOT NULL DEFAULT \'\', description VARCHAR(600) NOT NULL DEFAULT \'\', options TEXT DEFAULT NULL, tiers TEXT DEFAULT NULL,
            taxable TINYINT(1) NOT NULL DEFAULT 1, vendor VARCHAR(120) NOT NULL DEFAULT \'\', turnaround VARCHAR(80) NOT NULL DEFAULT \'\', qb_item_id VARCHAR(40) DEFAULT NULL, qb_item_env VARCHAR(12) DEFAULT NULL,
            status VARCHAR(8) NOT NULL DEFAULT \'active\', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_products_owner_sku (owner_id, sku))',
        // quotes and invoices, and the orders they become (BizQuotes, decisions/0118): one row from quote to paid order
        'biz_quotes' => '(id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, owner_id INT UNSIGNED NOT NULL DEFAULT 0, number INT UNSIGNED NOT NULL DEFAULT 0,
            customer_id INT UNSIGNED DEFAULT NULL, cust_name VARCHAR(160) NOT NULL DEFAULT \'\', cust_email VARCHAR(190) NOT NULL DEFAULT \'\', title VARCHAR(160) NOT NULL DEFAULT \'\',
            status VARCHAR(10) NOT NULL DEFAULT \'draft\', stage VARCHAR(12) NOT NULL DEFAULT \'\', tracking VARCHAR(160) NOT NULL DEFAULT \'\',
            terms VARCHAR(10) NOT NULL DEFAULT \'receipt\', deposit_pct TINYINT UNSIGNED NOT NULL DEFAULT 0, tax_milli INT UNSIGNED NOT NULL DEFAULT 0,
            pay_link VARCHAR(400) NOT NULL DEFAULT \'\', pay_note VARCHAR(400) NOT NULL DEFAULT \'\', notes TEXT DEFAULT NULL, valid_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
            token CHAR(32) DEFAULT NULL, total_cents BIGINT NOT NULL DEFAULT 0, paid_cents BIGINT NOT NULL DEFAULT 0,
            sent_at DATETIME DEFAULT NULL, expires_at DATETIME DEFAULT NULL, approved_at DATETIME DEFAULT NULL, approved_name VARCHAR(160) NOT NULL DEFAULT \'\', due_at DATE DEFAULT NULL,
            paid_at DATETIME DEFAULT NULL, closed_why VARCHAR(300) NOT NULL DEFAULT \'\', created_by INT UNSIGNED DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_bq_token (token), KEY idx_bq_owner (owner_id, status, updated_at))',
        'biz_quote_lines' => '(id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, owner_id INT UNSIGNED NOT NULL DEFAULT 0, quote_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED DEFAULT NULL,
            title VARCHAR(200) NOT NULL, qty INT UNSIGNED NOT NULL DEFAULT 1, unit_cents BIGINT NOT NULL DEFAULT 0, cost_cents BIGINT NOT NULL DEFAULT 0, taxable TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL, KEY idx_bql (owner_id, quote_id))',
        'biz_payments' => '(id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, owner_id INT UNSIGNED NOT NULL DEFAULT 0, quote_id INT UNSIGNED NOT NULL, amount_cents BIGINT NOT NULL,
            method VARCHAR(12) NOT NULL DEFAULT \'other\', note VARCHAR(300) NOT NULL DEFAULT \'\', by_user INT UNSIGNED DEFAULT NULL, paid_on DATE NOT NULL, created_at DATETIME NOT NULL,
            KEY idx_bp (owner_id, quote_id))',
        'biz_quote_log' => '(id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, owner_id INT UNSIGNED NOT NULL DEFAULT 0, quote_id INT UNSIGNED NOT NULL, kind VARCHAR(16) NOT NULL,
            text VARCHAR(600) NOT NULL, by_user INT UNSIGNED DEFAULT NULL, created_at DATETIME NOT NULL, KEY idx_bql2 (owner_id, quote_id, created_at))',
        // proofs and files on quotes (BizFiles, decisions/0120): the records; the files are under app/storage/biz-files/<business>/
        'biz_files' => '(id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, owner_id INT UNSIGNED NOT NULL DEFAULT 0, quote_id INT UNSIGNED NOT NULL, kind VARCHAR(8) NOT NULL DEFAULT \'file\',
            version SMALLINT UNSIGNED NOT NULL DEFAULT 0, file_name VARCHAR(160) NOT NULL, mime VARCHAR(120) NOT NULL, bytes BIGINT UNSIGNED NOT NULL DEFAULT 0, shared TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(10) NOT NULL DEFAULT \'\', note VARCHAR(300) NOT NULL DEFAULT \'\', decided_note VARCHAR(600) NOT NULL DEFAULT \'\', decided_at DATETIME DEFAULT NULL,
            by_user INT UNSIGNED DEFAULT NULL, created_at DATETIME NOT NULL, KEY idx_bf (owner_id, quote_id, kind, version))',
        // discount codes (BizCodes, decisions/0121): a business's own, no floor; and each use, counted on approval
        'biz_codes' => '(id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, owner_id INT UNSIGNED NOT NULL DEFAULT 0, code VARCHAR(40) NOT NULL, kind VARCHAR(8) NOT NULL DEFAULT \'percent\',
            value INT UNSIGNED NOT NULL DEFAULT 0, min_order_cents BIGINT UNSIGNED NOT NULL DEFAULT 0, starts DATE DEFAULT NULL, ends DATE DEFAULT NULL, max_uses INT UNSIGNED NOT NULL DEFAULT 0,
            once_each TINYINT(1) NOT NULL DEFAULT 1, uses INT UNSIGNED NOT NULL DEFAULT 0, active TINYINT(1) NOT NULL DEFAULT 1, note VARCHAR(200) NOT NULL DEFAULT \'\',
            created_by INT UNSIGNED DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE KEY uq_bc (owner_id, code))',
        'biz_code_uses' => '(id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, owner_id INT UNSIGNED NOT NULL DEFAULT 0, code_id INT UNSIGNED NOT NULL, quote_id INT UNSIGNED NOT NULL,
            email VARCHAR(190) NOT NULL DEFAULT \'\', off_cents BIGINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, KEY idx_bcu (owner_id, code_id, email))',
    ] + Commerce::TABLES;

    /** Columns added to tables after they were first made: table => [column => definition]. Added once, MySQL-safe. */
    private const COLUMNS = [
        'biz_quotes' => ['code_id' => 'INT UNSIGNED DEFAULT NULL', 'code' => "VARCHAR(40) NOT NULL DEFAULT ''", 'code_kind' => "VARCHAR(8) NOT NULL DEFAULT ''",
            'code_value' => 'INT UNSIGNED NOT NULL DEFAULT 0'],
        'biz_files' => ['store_key' => "VARCHAR(255) NOT NULL DEFAULT ''"],   // its key on S3 (decisions/0122); empty: on the server
        'products' => ['kind_data' => 'TEXT NULL'],   // the kind's own fields (ItemKinds, decisions/0141 §5): dates, capacity, deposit…
    ];   // taxes, shipping and its settings, as ours (decisions/0119)

    /** Opens the business realm for one listing and switches this request to it (Scope). Returns its connection. */
    public static function open(int $listingId): PDO
    {
        if ($listingId <= 0) throw new InvalidArgumentException('Not a business.');
        $pdo = Database::realmOwn('listings');
        self::ensure($pdo);
        $pdo->prepare('SET @tv_tenant = ?')->execute([$listingId]);
        Scope::enterBusiness($pdo, $listingId);
        return $pdo;
    }

    /** Makes the tables, the tenant function, the views and the triggers in the listings database (once per version). */
    public static function ensure(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS biz_schema (k VARCHAR(40) NOT NULL PRIMARY KEY, v INT NOT NULL) ENGINE=InnoDB');
        $v = (int) $pdo->query("SELECT v FROM biz_schema WHERE k = 'version'")->fetchColumn();
        if ($v >= self::VERSION) return;
        $tail = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';
        foreach (self::TABLES as $t => $cols) $pdo->exec('CREATE TABLE IF NOT EXISTS `' . $t . '` ' . $cols . $tail);
        foreach (self::COLUMNS as $t => $cols) foreach ($cols as $c => $def) {   // checked first: MySQL has no ADD COLUMN IF NOT EXISTS
            $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'); $st->execute([$t, $c]);
            if (!(int) $st->fetchColumn()) $pdo->exec('ALTER TABLE `' . $t . '` ADD COLUMN `' . $c . '` ' . $def);
        }
        $pdo->exec('DROP FUNCTION IF EXISTS tv_tenant');
        $pdo->exec('CREATE FUNCTION tv_tenant() RETURNS INT UNSIGNED DETERMINISTIC NO SQL RETURN @tv_tenant');
        foreach (array_keys(self::TABLES) as $t) {
            $pdo->exec('CREATE OR REPLACE VIEW `b_' . $t . '` AS SELECT * FROM `' . $t . '` WHERE owner_id = tv_tenant()');
            $refuse = "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'another business'";
            foreach (['ins' => 'BEFORE INSERT', 'upd' => 'BEFORE UPDATE', 'del' => 'BEFORE DELETE'] as $k => $when) {
                $pdo->exec('DROP TRIGGER IF EXISTS `tvb_' . $k . '_' . $t . '`');
                $body = match ($k) {
                    // a new row is the connection's business: filled in when not given, refused when it's another
                    'ins' => "IF @tv_tenant IS NULL THEN $refuse; END IF; IF NEW.owner_id = 0 THEN SET NEW.owner_id = @tv_tenant; END IF; IF NEW.owner_id <> @tv_tenant THEN $refuse; END IF;",
                    'upd' => "IF @tv_tenant IS NULL OR OLD.owner_id <> @tv_tenant OR NEW.owner_id <> @tv_tenant THEN $refuse; END IF;",
                    'del' => "IF @tv_tenant IS NULL OR OLD.owner_id <> @tv_tenant THEN $refuse; END IF;",
                };
                $pdo->exec('CREATE TRIGGER `tvb_' . $k . '_' . $t . '` ' . $when . ' ON `' . $t . '` FOR EACH ROW BEGIN ' . $body . ' END');
            }
        }
        $pdo->exec("INSERT INTO biz_schema (k, v) VALUES ('version', " . self::VERSION . ") ON DUPLICATE KEY UPDATE v = VALUES(v)");
    }

    /**
     * One business's discount code, as its public offer terms only, for a page drawn outside its portal (an event page's
     * Taking part, decisions/0134's note #1594). 0110's note (wall 3, the Founder's ruling) names it as the one exception
     * to "set once, by the router": the listings login on a connection of its own (never main's, never the portal's), set to
     * that business for one SELECT through its b_ view, then reset. Read only. [code, ends, min_cents] while it's switched on,
     * in its dates and not used up; null otherwise, or when the data can't be reached (nothing shown rather than something
     * untrue). Dates are the server's, as BizCodes::check's at checkout, so the page and checkout agree. Remembered for the
     * request; a connection that fails is tried once. $db is the test's seam: never the request's own business connection.
     */
    public static function publicCode(int $entity, string $code, ?PDO $db = null): ?array
    {
        static $own = null, $seen = [];
        $code = strtoupper(trim($code));
        if ($code === '' || $entity <= 0) return null;
        $live = $db === null;
        // a passed-in connection is the test's seam
        if (!$live && ((Scope::isBusiness() && $db === Scope::pdo($db)) || (Database::open() !== null && $db === Database::open()))) throw new LogicException('publicCode never uses the request\'s own business connection or main\'s.');
        $k = $entity . ':' . $code;
        if ($live && array_key_exists($k, $seen)) return $seen[$k];
        if ($live && $own === false) return null;
        $c = null;
        try {
            if ($live) { if ($own === null) { try { $own = Database::realmOwn('listings'); $own->exec('SET SESSION TRANSACTION READ ONLY'); } catch (Throwable $x) { $own = false; throw $x; } } $db = $own; }
            $db->exec('SET @tv_publiccode_was = @tv_tenant');   // put back exactly as it was afterwards (NULL on its own connection)
            $db->prepare('SET @tv_tenant = ?')->execute([$entity]);
            try {
                $st = $db->prepare('SELECT code, active, starts, ends, max_uses, uses, min_order_cents FROM `b_biz_codes` WHERE code = ? LIMIT 1'); $st->execute([$code]);
                $c = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            } finally {
                try { $db->exec('SET @tv_tenant = @tv_publiccode_was, @tv_publiccode_was = NULL'); }
                catch (Throwable $x) { if ($live) $own = false; throw $x; }   // a tenant that couldn't be put back: that connection is dropped, never used again
            }
        } catch (Throwable $x) { error_log('BusinessData::publicCode ' . $entity . ': ' . $x->getMessage()); if ($live) $seen[$k] = null; return null; }
        $today = date('Y-m-d');
        $ok = $c && (int) $c['active'] && !($c['starts'] && (string) $c['starts'] > $today) && !($c['ends'] && (string) $c['ends'] < $today) && !((int) $c['max_uses'] && (int) $c['uses'] >= (int) $c['max_uses']);
        $r = $ok ? ['code' => (string) $c['code'], 'ends' => $c['ends'] ?: null, 'min_cents' => (int) $c['min_order_cents']] : null;   // its public terms only: no counts, notes, value or other codes
        if ($live) $seen[$k] = $r;
        return $r;
    }

    /** Was this database error a business wall refusing something? (For the security log.) */
    public static function refused(Throwable $e): bool
    {
        return $e instanceof PDOException && (str_contains($e->getMessage(), 'another business') || in_array((int) ($e->errorInfo[1] ?? 0), [1044, 1142, 1143], true));
    }
}
