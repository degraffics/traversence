<?php
// app/lib/StagingSite.php — sets up the staging copy at staging.trvgo.us and refreshes it from the live site
// (decisions/0145 §5 step 5, site-audit/phase-3-staging.md). Runs on the live site only: System Configuration → Staging
// (Founder only) and the weekly staging_tick job. What staging itself does differently is Staging.php.
//
// The layout mirrors live's, so the same code finds staging's own .env four folders above app/lib:
//   live     <home>/.env                     <home>/public_html/traversence/{website,app,trvgo}
//   staging  <home>/staging.trvgo.us/.env    <home>/staging.trvgo.us/public_html/traversence/{website,app,trvgo}
// (or, when cPanel keeps document roots inside public_html, <home>/public_html/staging.trvgo.us/…, its top folder shut to
// the web). staging.trvgo.us serves …/traversence, whose .htaccess sends pages into website/ and /go/… to trvgo/.
//
// Set up staging, one button, safe to press again (it checks and fills in what's missing), through cPanel's API:
// the plan's room, the subdomain, a database for each of live's (main, admin, listings, users) and a login for each of
// live's, privileges, staging's own .env (database passwords made here and never shown or logged), the viewing password,
// the DNS record and the certificate. Refresh: live's files, then each table copied on the server (CREATE TABLE … LIKE,
// INSERT … SELECT, by the live login that owns it, which may write to its staging copy), then people's details replaced
// with test values (strip()), except the test accounts'. In steps, so the page shows its progress and no request runs long.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Staging.php';
require_once __DIR__ . '/staging-tables.php';
if (!class_exists('CPanel')) require_once __DIR__ . '/CPanel.php';

final class StagingSite
{
    public const SUB = 'staging';
    public const ZONE = 'trvgo.us';
    public const VIEW_USER = 'staging';
    /** Cloudflare Turnstile's official test keys: they always pass, so staging needs nothing in Cloudflare (punch list 21). */
    public const TURNSTILE_TEST = ['1x00000000000000000000AA', '1x0000000000000000000000000000000AA'];
    /** A refresh started over a week since the last (the weekly job), or by the button. */
    public const WEEK_HOURS = 160;

    // ---- what is copied ----------------------------------------------------------------------------------------------

    private const FOLDERS = ['website', 'app', 'trvgo'];
    /** Never copied: sign-in sessions, caches and locks (app/storage, the sync's own rule); people's files left on the server
     *  from before S3 (website/uploads: staging reads files from the store instead); notes folders; the files the sync
     *  never carries. Dotfiles aren't copied either, except the folders' .htaccess (trvgo's own is replaced by staging's). */
    private const SKIP_PATHS = ['app/storage', 'website/uploads'];
    private const SKIP_NAMES = ['_notes', '_SUPPORT', '_pending', '_concepts', '_gsdata_', 'error_log', 'bootstrap-super-admin.php', '_htaccess-staged.txt'];
    /** Staging's own files in its folders, kept when a refresh clears what live no longer has. */
    private const OWN_FILES = ['website/robots.txt'];

    /** Tables never copied: the old copies a data move keeps in main (they'd carry people's details), live's own record of staging. */
    private const SKIP_TABLES = ['staging_state'];
    /** Copied empty (their layout only): sign-in secrets, devices, the vault, caches. */
    private const EMPTY_TABLES = ['vault_meta', 'vault_creds', 'vault_items', 'vault_ext', 'vault_log', 'email_confirmations', 'user_otp_challenges', 'user_identities',
        'two_step_devices', 'staff_recovery_codes', 'guest_devices', 'support_grants', 'page_cache'];
    /** Rows taken out after the copy: live payment connections (staging connects to the sandboxes itself), staff set-up links. */
    private const DELETE_ROWS = ['qb_connection' => ['env', "env <> 'sandbox'"], 'market_gateways' => ['env', "env <> 'sandbox'"], 'staff_settings' => ['name', "name LIKE 'setup:%'"]];

    // ---- what is stripped (decisions/0145 Phase 3: names, emails, phone numbers, addresses and messages) ----------------
    // Emails, phone numbers, IP addresses and browser descriptions are found by their column in every table except the
    // public business and place records (PUBLIC), where they're the business's published details. Names and street
    // addresses are replaced in the tables about people (PEOPLE); private messages in PRIVATE_TEXT. The test accounts keep
    // theirs (check+…@traversence.com, users.is_test, the staff logins Test accounts made).

    private const PUBLIC = ['entities', 'entity_metadata', 'nations', 'open_places', 'npi_orgs', 'irs_orgs', 'rec_places', 'resource_entries', 'source_facts', 'landmarks',
        'crawler_raw_index', 'crawl_targets', 'crawler_sources', 'reference_sources', 'identity_facts', 'identity_fact_sources', 'place_profile', 'live_events', 'storefronts',
        'business_domains', 'guide_finds', 'search_index', 'zip_coordinates', 'jurisdictions', 'jurisdiction_laws', 'heritage_sources'];
    private const PEOPLE = ['users', 'member_profiles', 'staff_accounts', 'contact_cards', 'address_contacts', 'people_invites', 'listing_invites', 'crm_customers', 'biz_quotes',
        'orders', 'order_addresses', 'market_orders', 'subscriptions', 'collateral', 'guest_inquiries', 'site_form_entries', 'prospects', 'sales_cards', 'listing_claims',
        'gallery_album_people', 'takedowns', 'promotion_uses', 'guide_watchers', 'user_places', 'contributors', 'account_closures', 'verify_requests', 'contact_requests',
        'client_listings', 'order_work_orders', 'opt_out_requests', 'daily_reports', 'customers', 'vendors'];
    /** In PEOPLE tables, columns holding a person's name. */
    private const NAME_COLS = ['display_name', 'first_name', 'last_name', 'full_name', 'real_name', 'nickname', 'bill_name', 'ship_name', 'cust_name', 'client_name', 'contact_name',
        'claimant_name', 'approved_name', 'owner_name', 'payer_name', 'signer_name', 'person_name', 'requester_name', 'requested_by_name'];
    /** …and the PEOPLE tables whose plain "name" is a person's (in the others it's a business's or a thing's). */
    private const NAME_IS_PERSON = ['crm_customers', 'address_contacts', 'people_invites', 'listing_invites', 'guest_inquiries', 'site_form_entries', 'sales_cards',
        'gallery_album_people', 'takedowns', 'order_addresses', 'market_orders', 'staff_accounts', 'contact_cards', 'member_profiles', 'customers', 'contact_requests'];
    private const STREET_COLS = ['street', 'street1', 'street2', 'address', 'address1', 'address2', 'addr', 'addr1', 'addr2', 'street_address', 'bill_street', 'bill_address',
        'ship_street', 'ship_address', 'home_address', 'mailing_address', 'postal_address', 'line1', 'line2'];
    private const PRIVATE_TEXT = ['messages' => ['body'], 'business_messages' => ['body'], 'guest_inquiry_messages' => ['body'], 'crm_notes' => ['body'], 'market_notes' => ['body'],
        'notices_sent' => ['body'], 'site_form_entries' => ['message'], 'member_post_holds' => ['body'], 'contact_requests' => ['message', 'note'], 'address_contacts' => ['note'],
        'prospect_log' => ['note'], 'listing_claims' => ['role_note'], 'verification_attempts' => ['notes'], 'biz_quote_log' => ['text']];
    private const TEXT_TYPES = ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'];

    // ---- staging's .env --------------------------------------------------------------------------------------------
    /** Live settings left out of staging's .env: cPanel's token (staging never touches the hosting account), mail logins (mail is
     *  held), SES and worker keys, the edge and Name.com's live keys, social sign-in (email only on staging, Jason 2026-11-08),
     *  analytics, live paths, and every database setting (staging's own are written instead). */
    private const ENV_DROP = '/^(CPANEL_|GH_|GITHUB_|SES_(PERSONAL|BULK)_|CRAWLER_API_TOKEN$|VIDEO_WORKER_TOKEN$|STOREFRONT_EDGE_KEY$|FLY_API_TOKEN$|NAMECOM_(TOKEN|USER)$|CLAIM_STORAGE$|ANALYTICS_|GOOGLE_CLIENT_|FACEBOOK_APP_|MICROSOFT_CLIENT|MICROSOFT_TENANT$|APPLE_CLIENT|DB_(DATABASE|USERNAME|PASSWORD|ADMIN_|USERS_|LISTINGS_))/';
    /** Live keys of services held on staging (decisions/0165 §1: the keys left over after 0145), also taken out of an .env
     *  written before (cleanEnv()). A test that needs one switches the service on for a while: StagingGate::key() lends live's. */
    private const ENV_DROP_0165 = '/^(ORS_API_KEY|PIXABAY_KEY|GOOGLE_PLACES_KEY|GOOGLE_MAPS_KEY|TAVILY_API_KEY|BRAVE_API_KEY|GEMINI_API_KEY|SUPABASE_(URL|SERVICE_KEY)|RAILWAY_API_TOKEN|GITHUB_DOCS_TOKEN|GITHUB_SYNC_TOKEN|STRIPE_LIVE_\w+|SQUARE_LIVE_\w+)$/';
    /** Each realm's settings in .env: [database, login, password]. */
    private const ENV_DB = ['main' => ['DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'], 'admin' => ['DB_ADMIN_DATABASE', 'DB_ADMIN_USERNAME', 'DB_ADMIN_PASSWORD'],
        'listings' => ['DB_LISTINGS_DATABASE', 'DB_LISTINGS_USERNAME', 'DB_LISTINGS_PASSWORD'], 'users' => ['DB_USERS_DATABASE', 'DB_USERS_USERNAME', 'DB_USERS_PASSWORD']];

    private static array $conns = [];
    private static array $connAt = [];   // when each realm's connection was last handed out (conn())

    // ---- live's record of staging (main database) --------------------------------------------------------------------

    public static function ensure(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS staging_state (k VARCHAR(32) NOT NULL PRIMARY KEY, v MEDIUMTEXT NOT NULL, updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    public static function get(PDO $pdo, string $k): ?array
    {
        try { $st = $pdo->prepare('SELECT v FROM staging_state WHERE k = ?'); $st->execute([$k]); $v = $st->fetchColumn(); } catch (Throwable) { return null; }
        return $v === false ? null : (json_decode((string) $v, true) ?: null);
    }

    private static function put(PDO $pdo, string $k, ?array $v): void
    {
        self::ensure($pdo);
        if ($v === null) { $pdo->prepare('DELETE FROM staging_state WHERE k = ?')->execute([$k]); return; }
        $pdo->prepare('INSERT INTO staging_state (k, v, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = NOW()')->execute([$k, json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    }

    public static function isSetUp(PDO $pdo): bool
    {
        $s = self::get($pdo, 'setup');
        return $s !== null && !empty($s['base']) && is_file(self::layout((string) $s['base'])['env']);
    }

    /** May the person here set up or refresh staging? The Founder only (punch list 20): [yes, why not]. */
    public static function canManage(): array
    {
        $why = 'Only the Founder can set up or refresh staging.';
        if (Staging::here()) return [false, 'Staging is set up and refreshed from the live site\'s System Configuration → Staging.'];
        if (!class_exists('Access')) return [false, $why];
        [$ok, $no] = Access::can('config.staging', ['min' => 'founder', 'depts' => []]);
        if (!$ok) return [false, $no];
        $me = Access::staff();
        return $me && ($me['role'] ?? '') === 'owner' ? [true, ''] : [false, $why];
    }

    // ---- where things are ----------------------------------------------------------------------------------------------

    private static function liveHome(): string { return dirname(Database::envPath()); }
    private static function liveRoot(): string { return dirname(__DIR__, 2); }

    /** Staging's folders under a base: [home (its .env), root (what staging.trvgo.us serves), env, htpasswd]. */
    public static function layout(string $base): array
    {
        $base = rtrim($base, '/');
        return ['home' => $base, 'root' => $base . '/public_html/traversence', 'env' => $base . '/.env', 'htpasswd' => $base . '/.htpasswd'];
    }

    /** The two places staging can live: in the hosting account's home (outside public_html), or in public_html when cPanel
     *  keeps document roots there. */
    private static function bases(): array
    {
        $h = self::liveHome();
        return [$h . '/staging.trvgo.us', $h . '/public_html/staging.trvgo.us'];
    }

    private static function docroot(string $base): string { return substr(rtrim($base, '/'), strlen(self::liveHome()) + 1) . '/public_html/traversence'; }

    /** Live's databases and the login each is copied with: realm => [database, login, password]. */
    private static function liveDbs(): array
    {
        Database::readEnv();
        $out = ['main' => [Database::mainName(), Database::mainUser(), (string) (getenv('DB_PASSWORD') ?: '')]];
        foreach (['admin', 'listings', 'users'] as $r) {
            [$db, $u, $p] = Database::realmConfig($r);
            if ($db && $u && $db !== $out['main'][0]) $out[$r] = [(string) $db, (string) $u, (string) $p];
        }
        return $out;
    }

    /** Staging's database for a realm, checked: one Set up staging named (…stg, …stg_admin…), never one of live's. */
    private static function stg(array $setup, string $realm): string
    {
        $db = (string) ($setup['dbs'][$realm] ?? '');
        if ($db === '' || !preg_match('/stg(_[a-z]+)?$/', $db) || in_array($db, array_column(self::liveDbs(), 0), true))
            throw new RuntimeException('Staging\'s ' . $realm . ' database isn\'t one Set up staging made, so nothing was changed in it.');
        return $db;
    }

    private static function q(string $db, string $t): string { return '`' . str_replace('`', '', $db) . '`.`' . str_replace('`', '', $t) . '`'; }

    /** A connection as one of live's logins (to copy from its database and write to its staging copy). */
    private static function conn(string $realm): PDO
    {
        // A connection kept from earlier in a long run (staging_carry_tick works up to 55 minutes in one process) may have
        // been closed by the server while it sat idle: the admin realm's sat through main's whole strip, and the first admin
        // step met "MySQL server has gone away" (2026-11-26, "strip admin admin_access"). Idle a while: asked first, and
        // made again when the server has dropped it.
        if (isset(self::$conns[$realm])) {
            if (time() - (self::$connAt[$realm] ?? 0) < 20) { self::$connAt[$realm] = time(); return self::$conns[$realm]; }
            try { @self::$conns[$realm]->query('SELECT 1'); self::$connAt[$realm] = time(); return self::$conns[$realm]; }
            catch (Throwable $e) { unset(self::$conns[$realm]); error_log('staging: the ' . $realm . ' connection had closed (' . $e->getMessage() . '); made again'); }
        }
        $l = self::liveDbs()[$realm] ?? null;
        if (!$l) throw new RuntimeException('The live ' . $realm . ' database isn\'t set up in .env.');
        Database::readEnv();
        $dsn = 'mysql:host=' . (getenv('DB_HOST') ?: 'localhost') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . $l[0] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $l[1], $l[2], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        $pdo->exec("SET SESSION sql_mode = REPLACE(REPLACE(@@sql_mode, 'NO_ZERO_DATE', ''), 'NO_ZERO_IN_DATE', '')");   // old rows with zero dates copy as they are
        try { $pdo->exec('SET SESSION wait_timeout = 3600'); } catch (Throwable) {}   // an hour idle, as long as the job runs; the check above covers a server that won't
        self::$connAt[$realm] = time();
        return self::$conns[$realm] = $pdo;
    }

    /** Staging's names: databases and logins (cPanel puts the account's prefix first), and who may use which. */
    private static function names(string $pre, array $live): array
    {
        $dbs = ['main' => $pre . 'stg'];
        foreach (['admin' => 'stg_admin', 'listings' => 'stg_lister', 'users' => 'stg_user'] as $r => $n) if (isset($live[$r])) $dbs[$r] = $pre . $n;
        $users = ['main' => $pre . 'stg'];
        if (isset($live['admin']) && $live['admin'][1] !== $live['main'][1]) $users['admin'] = $pre . 'stga';
        if (isset($live['listings'])) $users['listings'] = $pre . 'stgl';
        if (isset($live['users'])) $users['users'] = $pre . 'stgu';
        $grants = [];
        foreach (['main', 'admin'] as $u) if (isset($users[$u])) foreach (['main', 'admin'] as $d) if (isset($dbs[$d])) $grants[] = [$users[$u], $dbs[$d]];   // as on live: the main login reaches the admin database
        foreach (['listings', 'users'] as $r) if (isset($users[$r])) $grants[] = [$users[$r], $dbs[$r]];
        foreach ($live as $r => [, $lu]) $grants[] = [$lu, $dbs[$r]];   // live's login for each database copies into its staging copy (and nothing of staging's reads live)
        $grants = array_values(array_unique(array_map(fn ($g) => implode("\n", $g), $grants)));
        return ['dbs' => $dbs, 'users' => $users, 'grants' => array_map(fn ($g) => explode("\n", $g), $grants)];
    }

    // ---- Set up staging --------------------------------------------------------------------------------------------

    /**
     * Makes or completes staging (safe to run again: it checks each part and fills in what's missing). Returns
     * ['log' => lines]. Throws, saying what was done and what wasn't, on a problem. The viewing password is set after
     * (newViewing(), with the test accounts' new sign-ins), and the copy follows with a refresh.
     */
    public static function setup(PDO $pdo, int $uid, string $tz = ''): array
    {
        if (Staging::here()) throw new RuntimeException('Staging is set up from the live site (System Configuration → Staging there), not from staging itself.');
        if (!CPanel::ready()) throw new RuntimeException('The site can\'t reach cPanel yet: CPANEL_HOST, CPANEL_USER and CPANEL_TOKEN go in the live .env (the same token that makes staff mailboxes).');
        self::ensure($pdo);
        @set_time_limit(300);
        $home = self::liveHome();
        if (dirname(self::liveRoot(), 2) !== $home || !is_file($home . '/.env')) throw new RuntimeException('The live site isn\'t laid out as expected (its .env two folders above public_html/traversence), so nothing was set up.');
        if (!self::lock($pdo)) throw new RuntimeException('Staging is already being set up or refreshed in another window. Wait for it to finish, then try again.');
        try {
            $log = [];
            $was = self::get($pdo, 'setup') ?? [];
            // 1. trvgo.us is on this account, and whether staging.trvgo.us already is
            $d = (array) CPanel::uapi('DomainInfo', 'list_domains');
            $doms = array_map('strtolower', array_merge([(string) ($d['main_domain'] ?? '')], (array) ($d['addon_domains'] ?? [])));
            if (!in_array(self::ZONE, $doms, true)) throw new RuntimeException('trvgo.us isn\'t a domain on this hosting account, so staging.trvgo.us can\'t be made here. Nothing was changed.');
            $haveSub = in_array(Staging::HOST, array_map('strtolower', (array) ($d['sub_domains'] ?? [])), true);
            // 2. names, and what's there already
            $r = (array) CPanel::uapi('Mysql', 'get_restrictions');
            $live = self::liveDbs();
            $plan = self::names((string) ($r['prefix'] ?? ''), $live);
            $maxDb = (int) ($r['max_database_name_length'] ?? 64); $maxUser = (int) ($r['max_username_length'] ?? 32);
            foreach ($plan['dbs'] as $n) if (strlen($n) > $maxDb) throw new RuntimeException('The database name ' . $n . ' is longer than cPanel allows (' . $maxDb . '). Nothing was changed.');
            foreach ($plan['users'] as $n) if (strlen($n) > $maxUser) throw new RuntimeException('The login name ' . $n . ' is longer than cPanel allows (' . $maxUser . '). Nothing was changed.');
            foreach ($plan['dbs'] as $n) if (in_array($n, array_column($live, 0), true)) throw new RuntimeException('Staging\'s database name ' . $n . ' is one of the live site\'s. Nothing was changed.');
            $dbsHave = array_map(fn ($x) => (string) ($x['database'] ?? ''), (array) CPanel::uapi('Mysql', 'list_databases'));
            $usersHave = array_map(fn ($x) => (string) ($x['user'] ?? ''), (array) CPanel::uapi('Mysql', 'list_users'));
            foreach ($plan['dbs'] as $n) if (in_array($n, $dbsHave, true) && !in_array($n, (array) ($was['dbs'] ?? []), true))
                throw new RuntimeException('A database named ' . $n . ' is already on the account and wasn\'t made by Set up staging, so it was left alone and nothing was changed. Ask us at support@traversence.com to choose another name.');
            foreach ($plan['users'] as $n) if (in_array($n, $usersHave, true) && !in_array($n, (array) ($was['users'] ?? []), true))
                throw new RuntimeException('A database login named ' . $n . ' is already on the account and wasn\'t made by Set up staging, so it was left alone and nothing was changed. Ask us at support@traversence.com to choose another name.');
            $newDbs = array_values(array_diff($plan['dbs'], $dbsHave));
            // 3. room on the plan
            self::room(!$haveSub, count($newDbs));
            // what this press will make is recorded first, so a press that stops part way is finished by the next
            self::put($pdo, 'setup', ['dbs' => $plan['dbs'], 'users' => $plan['users']] + $was);
            $changed = true;
            // 4. the subdomain and its folder
            $base = self::subdomain($haveSub, $log);
            self::put($pdo, 'setup', ['base' => $base, 'docroot' => self::docroot($base), 'dbs' => $plan['dbs'], 'users' => $plan['users']] + $was);
            $L = self::layout($base);
            // 5. databases, logins (passwords made here, kept only in staging's .env), privileges
            $old = self::readEnvFile($L['env']);
            foreach ($newDbs as $n) { CPanel::uapi('Mysql', 'create_database', ['name' => $n]); $log[] = 'Made the database ' . $n . '.'; }
            $pw = [];
            foreach ($plan['users'] as $realm => $n) {
                $p = (string) ($old[self::ENV_DB[$realm][2]] ?? '');
                if (!preg_match('/^[A-Za-z0-9._-]{20,64}$/', $p)) $p = self::password(28);
                if (in_array($n, $usersHave, true)) CPanel::uapi('Mysql', 'set_password', ['user' => $n, 'password' => $p]);   // the same password again, so the login and .env agree
                else { CPanel::uapi('Mysql', 'create_user', ['name' => $n, 'password' => $p]); $log[] = 'Made the database login ' . $n . '.'; }
                $pw[$realm] = $p;
            }
            foreach ($plan['grants'] as [$u, $db]) CPanel::uapi('Mysql', 'set_privileges_on_database', ['user' => $u, 'database' => $db, 'privileges' => 'ALL PRIVILEGES']);
            $log[] = 'Gave each login its databases: staging\'s logins reach only staging\'s; live\'s may write to their staging copies (for the refresh).';
            // 6. folders, staging's .env, its rules and a closed door until the viewing password is set
            foreach ([$L['home'], $L['root'] . '/website'] as $dir) if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new RuntimeException('Couldn\'t make the folder ' . $dir . '.');
            @chmod($L['home'], 0711);
            self::writeEnv($L, $plan, $pw, $old);
            if (!is_file($L['htpasswd'])) self::writeFile($L['htpasswd'], self::VIEW_USER . ':' . password_hash(self::password(32), PASSWORD_BCRYPT) . "\n", 0644);
            self::writeRules($L);
            $log[] = 'Wrote staging\'s own .env (its database passwords stay in it, never shown), its rules (the viewing password, no indexing) and robots.txt.';
            // 7. DNS (trvgo.us's zone is on Bluehost, 2026-11-08) and the certificate
            $log[] = self::dns();
            try { CPanel::uapi('SSL', 'start_autossl_check'); $log[] = 'Asked cPanel for its HTTPS certificate (AutoSSL): it usually arrives within the hour.'; }
            catch (Throwable) { $log[] = 'Its HTTPS certificate comes with cPanel\'s next AutoSSL run (usually within a few hours).'; }
            self::put($pdo, 'setup', ['at' => date('c'), 'tz' => $tz, 'by' => $uid, 'base' => $base, 'docroot' => self::docroot($base), 'dbs' => $plan['dbs'], 'users' => $plan['users'],
                'copy' => array_map(fn ($l) => [$l[0], $l[1]], $live)]);
            error_log('staging set up by user #' . $uid);
            return ['log' => $log];
        } catch (RuntimeException $e) {
            if (empty($changed)) throw $e;
            throw new RuntimeException('Set up staging stopped part way: ' . $e->getMessage() . ' Press it again: it finishes what\'s missing.' . ($log ? ' Done so far: ' . implode(' ', $log) : ''));
        } finally { self::unlock($pdo); }
    }

    /** Enough room on the hosting plan for what's still to make (StatsBar: subdomains and databases). */
    private static function room(bool $sub, int $dbs): void
    {
        if (!$sub && !$dbs) return;
        $rows = (array) CPanel::uapi('StatsBar', 'get_stats', ['display' => 'subdomains|sqldatabases']);
        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? $row['id'] ?? '');
            $max = $row['_max'] ?? $row['max'] ?? null; $count = (int) ($row['_count'] ?? $row['count'] ?? 0);
            if (!is_numeric($max)) continue;   // unlimited
            $left = (int) $max - $count;
            if ($name === 'subdomains' && $sub && $left < 1) throw new RuntimeException('The hosting plan has no room for another subdomain (' . $count . ' of ' . (int) $max . ' used), so nothing was changed. Staging needs one: Bluehost can raise the limit, or ask us at support@traversence.com about the folder option.');
            if ($name === 'sqldatabases' && $left < $dbs) throw new RuntimeException('The hosting plan has room for ' . max(0, $left) . ' more database' . ($left === 1 ? '' : 's') . ' and staging needs ' . $dbs . ', so nothing was changed. Bluehost can raise the limit, or ask us at support@traversence.com.');
        }
    }

    /** staging.trvgo.us, serving staging's folder: made, or found and checked (and moved to staging's folder when it serves another). */
    private static function subdomain(bool $have, array &$log): string
    {
        $err = '';
        if ($have) {
            $dd = (array) CPanel::uapi('DomainInfo', 'single_domain_data', ['domain' => Staging::HOST]);
            $doc = rtrim((string) ($dd['documentroot'] ?? ''), '/');
            foreach (self::bases() as $b) if ($doc === self::layout($b)['root']) { $log[] = 'staging.trvgo.us was already there.'; return $b; }
            foreach (self::bases() as $b) {
                try { CPanel::api2('SubDomain', 'changedocroot', ['subdomain' => self::SUB, 'rootdomain' => self::ZONE, 'dir' => self::docroot($b)]); $log[] = 'Pointed staging.trvgo.us at staging\'s folder (' . self::docroot($b) . ').'; return $b; }
                catch (RuntimeException $e) { $err = $e->getMessage(); }
            }
            throw new RuntimeException('staging.trvgo.us is already on the account, serving ' . ($doc ?: 'another folder') . ', and cPanel wouldn\'t point it at staging\'s folder (' . $err . '). Nothing else was changed.');
        }
        foreach (self::bases() as $i => $b) {
            try {
                CPanel::uapi('SubDomain', 'addsubdomain', ['domain' => self::SUB, 'rootdomain' => self::ZONE, 'dir' => self::docroot($b), 'disallowdot' => 1]);
                $log[] = 'Made staging.trvgo.us, serving ' . self::docroot($b) . '.';
                return $b;
            } catch (RuntimeException $e) {
                $err = $e->getMessage();
                if ($i === 0 && !preg_match('/public_html|document\s*root|directory|folder/i', $err)) throw $e;   // only a folder outside public_html refused: try inside it
            }
        }
        throw new RuntimeException('cPanel wouldn\'t make staging.trvgo.us (' . $err . '). Nothing else was changed.');
    }

    /** The DNS record for staging.trvgo.us: cPanel usually adds it with the subdomain; if not, one like trvgo.us's own. */
    private static function dns(): string
    {
        $has = array_filter(CPanel::records(self::ZONE, self::SUB), fn ($r) => in_array($r[0], ['A', 'AAAA', 'CNAME'], true));
        if ($has) return 'The DNS record for staging.trvgo.us is there.';
        $apex = array_values(array_filter(CPanel::records(self::ZONE, '@'), fn ($r) => $r[0] === 'A'));
        if (!$apex) throw new RuntimeException('trvgo.us has no address (A) record to copy, so staging.trvgo.us\'s DNS record wasn\'t added. Everything else is set up: press Set up staging again once trvgo.us has one, or ask us at support@traversence.com.');
        CPanel::dnsEnsure(self::ZONE, [[self::SUB, 'A', $apex[0][1]]]);
        return 'Added the DNS record staging.trvgo.us → ' . $apex[0][1] . ' (it can take up to an hour to be seen everywhere).';
    }

    /**
     * A new viewing password for staging (shown once, in the CHECK_ACCOUNTS block; only its hash is kept, in staging's
     * folder). Returns the block's "staging" part, or null when staging isn't set up. TestAccounts::makeAll calls it.
     */
    public static function newViewing(PDO $pdo): ?array
    {
        $s = self::get($pdo, 'setup');
        if (!$s || empty($s['base'])) return null;
        $L = self::layout((string) $s['base']);
        if (!is_file($L['env'])) return null;
        $pw = self::password(24);
        self::writeFile($L['htpasswd'], self::VIEW_USER . ':' . password_hash($pw, PASSWORD_BCRYPT) . "\n", 0644);
        $env = self::readEnvFile($L['env']);
        return ['site' => Staging::siteBase(), 'router' => Staging::siteBase() . '/go/', 'user' => self::VIEW_USER, 'password' => $pw, 'health_token' => (string) ($env['HEALTH_TOKEN'] ?? '')];
    }

    // ---- staging's files: .env, rules, robots ------------------------------------------------------------------------

    /** KEY=value lines of an .env file (values without their quotes). */
    public static function readEnvFile(string $path): array
    {
        $out = [];
        foreach (is_readable($path) ? (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [] as $l) {
            $l = trim($l);
            if ($l === '' || $l[0] === '#' || !str_contains($l, '=')) continue;
            [$k, $v] = explode('=', $l, 2);
            $out[trim($k)] = self::unquote(trim($v));
        }
        return $out;
    }

    private static function unquote(string $v): string
    {
        return strlen($v) >= 2 && (($v[0] === '"' && $v[-1] === '"') || ($v[0] === "'" && $v[-1] === "'")) ? substr($v, 1, -1) : $v;
    }

    /**
     * Staging's .env from live's: live's settings, less those staging must not have (ENV_DROP, live payment keys, live paths),
     * then staging's own. Returns [text, the names left out]. Never logged or shown.
     */
    public static function deriveEnv(string $liveText, array $set, string $liveHome): array
    {
        $raw = [];
        foreach (preg_split('/\r\n|\n/', $liveText) as $l) {
            $l = trim($l);
            if ($l === '' || $l[0] === '#' || !str_contains($l, '=')) continue;
            [$k, $v] = explode('=', $l, 2);
            $raw[trim($k)] = trim($v);
        }
        $val = fn (string $k) => self::unquote((string) ($raw[$k] ?? ''));
        // QuickBooks: production's keys stay out; an older .env with the sandbox's keys under the plain names keeps them
        $qbPlainSandbox = $val('QB_SANDBOX_CLIENT_ID') === '' && strtolower($val('QB_ENV')) === 'sandbox';
        $stripeTest = str_starts_with($val('STRIPE_SECRET_KEY'), 'sk_test_');   // Stripe and Square: only test keys come along
        $squareTest = str_starts_with($val('SQUARE_APP_ID'), 'sandbox-');
        $keep = []; $left = [];
        foreach ($raw as $k => $v) {
            if (array_key_exists($k, $set)) continue;
            $drop = preg_match(self::ENV_DROP, $k) === 1 || preg_match(self::ENV_DROP_0165, $k) === 1 || preg_match('/SMTP_(USERNAME|PASSWORD)$/', $k) === 1
                || (in_array($k, ['QB_CLIENT_ID', 'QB_CLIENT_SECRET', 'QB_WEBHOOK_TOKEN'], true) && !$qbPlainSandbox)
                || preg_match('/^(STRIPE|SQUARE)_LIVE_/', $k) === 1   // the live set never comes along; the test set always does
                || (preg_match('/^STRIPE_(?!TEST_)/', $k) === 1 && !$stripeTest) || (preg_match('/^SQUARE_(?!SANDBOX_)/', $k) === 1 && !$squareTest)
                || str_starts_with(self::unquote($v), rtrim($liveHome, '/') . '/');
            if ($drop) { $left[] = $k; continue; }
            $keep[] = $k . '=' . $v;
        }
        $own = [];
        foreach ($set as $k => $v) $own[] = $k . '=' . (preg_match('/[\s#"\']/', (string) $v) ? '"' . str_replace('"', '', (string) $v) . '"' : (string) $v);
        $text = "# Staging's own .env (staging.trvgo.us), written by the live site's Set up staging (decisions/0145 Phase 3).\n"
              . "# Its database passwords were made on the server and are only here. Press Set up staging again to rewrite it.\n"
              . "# From the live .env, less: " . ($left ? implode(', ', $left) : 'nothing') . "\n\n"
              . implode("\n", $keep) . "\n\n# ---- staging's own\n" . implode("\n", $own) . "\n";
        return [$text, $left];
    }

    private static function writeEnv(array $L, array $plan, array $pw, array $old): void
    {
        $set = ['STAGING' => '1', 'SITE_BASE' => Staging::siteBase(), 'APP_URL' => Staging::siteBase(), 'SITE_HOSTS' => Staging::HOST, 'TRVGO_BASE' => Staging::siteBase() . '/go'];
        foreach ($plan['dbs'] as $realm => $db) {
            $login = $plan['users'][$realm] ?? $plan['users']['main'];
            [$kd, $ku, $kp] = self::ENV_DB[$realm];
            $set[$kd] = $db; $set[$ku] = $login; $set[$kp] = $pw[isset($plan['users'][$realm]) ? $realm : 'main'];
        }
        $set += [
            'APP_SECRET' => preg_match('/^[A-Za-z0-9_-]{32,}$/', (string) ($old['APP_SECRET'] ?? '')) ? (string) $old['APP_SECRET'] : self::password(48),   // staging's own seal: nothing sealed on live opens here
            'HEALTH_TOKEN' => preg_match('/^[A-Za-z0-9_-]{32,}$/', (string) ($old['HEALTH_TOKEN'] ?? '')) ? (string) $old['HEALTH_TOKEN'] : self::password(48),
            'TURNSTILE_SITE_KEY' => self::TURNSTILE_TEST[0], 'TURNSTILE_SECRET_KEY' => self::TURNSTILE_TEST[1],
            'TEST_MAIL' => 'on',            // test accounts' email is held like everyone's (and readable on staging)
            'SES_MODE' => 'mock', 'QB_ENV' => 'sandbox', 'MARKET_PAY_ENV' => 'sandbox', 'NAMECOM_ENV' => 'sandbox', 'DOMAINS_MODE' => 'off', 'DOMAIN_SALES_MODE' => 'off',
            'CRAWL_QUEUE' => 'off', 'VIDEO_WORKER_PAUSED' => '1',
        ];
        $live = (string) @file_get_contents(self::liveHome() . '/.env');
        if ($live === '') throw new RuntimeException('Couldn\'t read the live .env to start staging\'s from.');
        [$text] = self::deriveEnv($live, $set, self::liveHome());
        self::writeFile($L['env'], $text, 0600);
    }

    /** Staging's .htaccess (the viewing password, no indexing, /go/ to the router, then live's own rules for its host),
     *  its top folder shut to the web, and robots.txt. */
    private static function writeRules(array $L): void
    {
        $live = (string) @file_get_contents(self::liveRoot() . '/.htaccess');
        if (!str_contains($live, 'RewriteRule ^(.*)$ /website/$1 [L]')) $live = self::LIVE_RULES;   // live's rules as of this build, if the file can't be read as expected
        $live = str_replace('^(www\.)?traversence\.com$', '^staging\.trvgo\.us$', $live);
        $live = str_replace('RewriteRule ^(.*)$ /website/$1 [L]', "RewriteCond %{REQUEST_URI} !^/trvgo/index\\.php\nRewriteRule ^(.*)$ /website/$1 [L]", $live);
        $env = self::readEnvFile($L['env']);
        $pass = preg_match('/^[A-Za-z0-9]{32,}$/', (string) ($env['STAGING_PASS'] ?? '')) && ($env['STAGING_PASS_RULES'] ?? 'on') !== 'off' ? (string) $env['STAGING_PASS'] : '';
        // the PHP guard: staging's guard file (written below) runs Staging::guard() before every PHP script there, which asks
        // for the viewing password itself (the same browser prompt) and takes the pass of those who came in from the live
        // admin. With the pass on (2026-11-13: two forms of a web-server rule for it were refused), the web server's own
        // password rule is left out and PHP is the gate; without it, the web server asks as before.
        self::writeGuard($L);
        $top = "# ---- Staging (staging.trvgo.us): written by the live site's Set up staging and each refresh (decisions/0145 Phase 3).\n"
             . ($pass !== '' ? "# Every page asks for the staging viewing password in PHP (staging-guard.php runs first, by .user.ini), which also takes\n# the pass of those who came in from the live admin (Open on staging). Its own way in, /stg-enter.php, checks the live site's signature.\n"
                             // granted here, or the folder above's Require all denied (it keeps the .env private) shuts every page (403; Check the way in, 2026-11-13)
                             . "Require all granted\n"
                             : "# Every page asks for the staging viewing password, except robots.txt, which keeps search engines out.\nAuthType Basic\nAuthName \"Traversence staging\"\nAuthUserFile \"" . $L['htpasswd'] . "\"\nRequire valid-user\n")
             . "<Files \"stg-enter.php\">\n  Require all granted\n</Files>\n"
             . "<Files \"robots.txt\">\n  Require all granted\n</Files>\n"
             . "<IfModule mod_headers.c>\n  Header always set X-Robots-Tag \"noindex, nofollow\"\n</IfModule>\n"
             . "RewriteEngine On\n"
             . "# the password reaches PHP too: Staging::guard() checks it again, in case a folder's own rules let a page through\n"
             . "RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\n"
             . "RewriteCond %{HTTPS} !=on\nRewriteCond %{HTTP:X-Forwarded-Proto} !https\nRewriteRule ^ https://" . Staging::HOST . "%{REQUEST_URI} [R=301,L]\n"
             . "# the trvgo.us router's copy answers at /go/… (trvgo.us/<code> on the live site); its folder is never asked for directly\n"
             . "RewriteCond %{THE_REQUEST} \\s/trvgo/ [NC]\nRewriteRule ^trvgo/ - [F,L]\n"
             . "RewriteRule ^go/?$ /trvgo/index.php?c= [L,QSA]\nRewriteRule ^go/(.+)$ /trvgo/index.php?c=$1 [L,QSA]\n"
             . "# ---- the live site's own rules, for staging's host\n";
        self::writeFile($L['root'] . '/.htaccess', $top . $live . (str_ends_with($live, "\n") ? '' : "\n"), 0644);
        self::writeFile($L['home'] . '/.htaccess', "# Staging's own folder (its .env and viewing password) is never served.\nRequire all denied\nOptions -Indexes\n", 0644);
        if (!is_dir($L['root'] . '/website')) @mkdir($L['root'] . '/website', 0755, true);
        self::writeFile($L['root'] . '/website/robots.txt', "# staging.trvgo.us is a private copy of traversence.com for testing.\nUser-agent: *\nDisallow: /\n", 0644);
    }

    /**
     * Staging's guard file (in its own folder, never served) and the .user.ini that has PHP run it before every script
     * on staging: Staging::guard(), the viewing password and the pass. Any other .user.ini settings are kept.
     */
    private static function writeGuard(array $L): void
    {
        $g = $L['home'] . '/staging-guard.php';
        $code = <<<'PHP'
<?php
// Written by the live site (StagingSite::writeGuard, decisions/0145 Phase 5). PHP runs it before every script on staging
// (.user.ini auto_prepend_file): the viewing password (the browser's prompt, checked against the password file) or the
// pass of those who came in from the live admin (Open on staging). Self-contained: it loads none of the site's classes.
(function () {
    if (PHP_SAPI === 'cli' || basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'stg-enter.php') return;
    if (!defined('TV_STG_GUARD')) define('TV_STG_GUARD', 1);   // it ran (the way-in check's probe asks)
    $pass = '';
    foreach (@file(__ENV__, FILE_IGNORE_NEW_LINES) ?: [] as $l) if (preg_match('/^\s*STAGING_PASS\s*=\s*["\']?([A-Za-z0-9]{32,})/', $l, $m)) $pass = $m[1];
    if ($pass !== '' && hash_equals($pass, (string) ($_COOKIE['tv_stg_pass'] ?? ''))) return;
    $u = $_SERVER['PHP_AUTH_USER'] ?? null; $p = $_SERVER['PHP_AUTH_PW'] ?? null;
    if ($u === null) {
        $h = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (stripos($h, 'basic ') === 0) { $d = base64_decode(substr($h, 6), true); if ($d !== false && strpos($d, ':') !== false) [$u, $p] = explode(':', $d, 2); }
    }
    if ($u !== null && $p !== null && $u !== '') foreach (@file(__PASSWD__, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        [$lu, $lh] = array_pad(explode(':', trim($line), 2), 2, '');
        if ($lh !== '' && hash_equals($lu, (string) $u) && password_verify((string) $p, $lh)) return;
    }
    http_response_code(401);
    header('WWW-Authenticate: Basic realm="Traversence staging", charset="UTF-8"');
    header('X-Tv-Stg-Gate: guard-file');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Traversence staging</title></head>'
       . '<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#F7EFDC;color:#3E2B1F;font:16px/1.5 var(--tv-font-head,Georgia,serif);padding:16px">'
       . '<div style="max-width:28rem;background:#FFFBF2;border:1px solid #CBB98D;border-radius:.5rem;padding:1.25rem"><p style="margin:0 0 .5rem;color:#92400E;font-weight:700">This is Traversence\'s staging copy</p>'
       . '<p style="margin:0">It\'s where changes are tried before they reach traversence.com, and it needs its viewing password. Looking for Traversence? It\'s at <a href="https://traversence.com">traversence.com</a>. On our team? Open it from the live admin (System Configuration → Staging → Open staging), or ask at <a href="mailto:support@traversence.com">support@traversence.com</a>.</p></div></body></html>';
    exit;
})();
PHP;
        $code = str_replace(['__ENV__', '__PASSWD__'], [var_export($L['env'], true), var_export($L['htpasswd'], true)], $code);
        self::writeFile($g, $code, 0644);
        $ini = $L['root'] . '/.user.ini';
        $lines = is_file($ini) ? array_values(array_filter(file($ini, FILE_IGNORE_NEW_LINES) ?: [], fn ($l) => !preg_match('/^\s*auto_prepend_file\s*=/', $l))) : [];
        $lines[] = 'auto_prepend_file = "' . $g . '"';
        self::writeFile($ini, implode("\n", $lines) . "\n", 0644);
    }

    /**
     * The keys for coming in from the live admin (decisions/0145 Phase 5, the staging bar): RELEASE_BAR_KEY (the live site
     * signs the way in and the bar's requests with it) and STAGING_PASS (the pass cookie), kept in staging's .env, and
     * staging's rules written to let the pass through. Checked once: if staging's server refuses those rules (an error on
     * every page), they're written again without them (STAGING_PASS_RULES=off) and the viewing password is asked as before.
     * Returns [bar key, pass] or null when staging isn't set up.
     */
    public static function barKeys(PDO $pdo): ?array
    {
        $s = self::get($pdo, 'setup');
        if (!$s || empty($s['base'])) return null;
        $L = self::layout((string) $s['base']);
        if (!is_file($L['env'])) return null;
        $env = self::readEnvFile($L['env']);
        $key = (string) ($env['RELEASE_BAR_KEY'] ?? ''); $pass = (string) ($env['STAGING_PASS'] ?? '');
        $new = [];
        // letters and digits only (2026-11-13: password() adds a mark for cPanel, which every check of these refused, so the
        // pass was never set and staging kept asking): made fresh here when what's there isn't of that shape
        if (!preg_match('/^[A-Za-z0-9]{40,}$/', $key)) $new['RELEASE_BAR_KEY'] = $key = bin2hex(random_bytes(24));
        if (!preg_match('/^[A-Za-z0-9]{32,}$/', $pass)) $new['STAGING_PASS'] = $pass = bin2hex(random_bytes(20));
        $rules = (string) @file_get_contents($L['root'] . '/.htaccess');
        // the bar's own files: staging is a copy of live from its last refresh, so a release system newer than that isn't
        // there yet (2026-11-13: Open staging found no /stg-enter.php). They're copied over whenever they differ.
        foreach (self::BAR_FILES as $f) {
            $from = self::liveRoot() . '/' . $f; $to = $L['root'] . '/' . $f;
            if (!is_file($from) || (is_file($to) && hash_file('sha256', $to) === hash_file('sha256', $from))) continue;
            if (!is_dir(dirname($to))) @mkdir(dirname($to), 0755, true);
            $tmp = $to . '.new-' . bin2hex(random_bytes(4));
            if (@copy($from, $tmp)) @rename($tmp, $to); else @unlink($tmp);
        }
        // the gate's third form (2026-11-13: PHP alone; the web server refused both rules for the pass): tried until it's taken
        // tried again until its test says taken or refused (2026-11-13: the first test ran against staging's older PHP, which
        // didn't know the pass yet, and said "not taken")
        $tested = (string) ($env['STAGING_PASS_TEST'] ?? '');
        $again = !preg_match('/ UTC (taken|refused):/', $tested);
        // (and when the guard file is older than its mark, X-Tv-Stg-Gate, so Check the way in can tell the layers apart)
        $guard = (string) @file_get_contents($L['home'] . '/staging-guard.php');
        if ($new || $again || ($env['STAGING_PASS_FORM'] ?? '') !== '4' || (($env['STAGING_PASS_RULES'] ?? 'on') !== 'off' && (!str_contains($rules, 'staging-guard.php') || !str_contains($guard, 'X-Tv-Stg-Gate')))) {
            self::setEnv($L, $new + ['STAGING_PASS_RULES' => 'on', 'STAGING_PASS_FORM' => '4']);   // form 4: the site's folder grants itself (2026-11-13)
            self::writeRules($L);
            // does staging's server take it? robots.txt (open to all) answers 200 unless the rules break every page (500);
            // a page with the pass and no password should answer, not ask (401)
            $ask = function (string $path, bool $withPass) use ($pass): int {
                require_once __DIR__ . '/StagingGate.php'; if (StagingGate::hold(Staging::siteBase() . $path, 'check the way in') !== null) return 0;   // a call to staging itself: always goes
                $ch = curl_init(Staging::siteBase() . $path);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => $withPass ? ['Cookie: tv_stg_pass=' . $pass] : []]);
                curl_exec($ch); $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
                return $c;
            };
            // both sides: without the pass a page must still ask (401); with it, it must open
            $closed = $ask('/faq.php', false); $open = $closed === 0 || $closed >= 500 ? 0 : $ask('/faq.php', true);
            $off = $closed >= 500 || ($closed !== 0 && $closed !== 401);   // an error, a page shut to everyone or open to anyone: the web server's password goes back on
            $result = $closed === 0 ? 'not tested: the server couldn\'t reach staging to try it'
                : ($closed >= 500 ? 'refused: staging answered ' . $closed . ', so the web server asks for the password again'
                : ($closed === 403 ? 'refused: pages were shut to everyone (403), not asking, so the web server asks for the password again'
                : ($closed !== 401 ? 'refused: a page opened without the password (' . $closed . '), so the web server asks for it again'
                : ($open === 401 ? 'not taken: the page still asked with the pass (401)' : 'taken: without the pass a page asks (401); with it, it opens (' . $open . ')'))));
            self::setEnv($L, ['STAGING_PASS_TEST' => gmdate('Y-m-d H:i') . ' UTC ' . preg_replace('/[^A-Za-z0-9 :().,\'-]/', '', $result)] + ($off ? ['STAGING_PASS_RULES' => 'off'] : []));
            if ($off) self::writeRules($L);
        }
        return [$key, $pass];
    }

    /** The files the way in from the live admin and the staging bar need on staging (copied by barKeys when they differ). */
    private const BAR_FILES = ['website/stg-enter.php', 'website/api/stg-bar.php', 'website/api/staging-updates.php', 'website/api/stg-probe.php', 'website/js/stg-bar.js',
                               'app/lib/Staging.php', 'app/lib/Releases.php', 'app/lib/TestAccounts.php'];

    /** What the last test of the pass rule found ('' before the first Open staging). For the Staging page. */
    public static function passTest(PDO $pdo): string
    {
        $s = self::get($pdo, 'setup');
        if (!$s || empty($s['base'])) return '';
        return (string) (self::readEnvFile(self::layout((string) $s['base'])['env'])['STAGING_PASS_TEST'] ?? '');
    }

    // ---- Check the way in (decisions/0159 step 1) ----------------------------------------------------------------------

    /**
     * Check the way in: asks staging for a page several ways, as a browser would, and says which layer answered each, so a
     * password prompt on Open staging is traced to the web server, staging's guard file or the page itself (X-Tv-Stg-Gate),
     * never guessed. Then what's on disk there: the rules on the way down to staging's pages, .user.ini, the guard file, the
     * pass's shape (never its value), and what staging's own PHP sees (/api/stg-probe.php). Founder only (the Staging page).
     * Opening staging this way is the same as pressing Open staging (barKeys runs). Returns [rows, text]: rows of
     * [mark, tone, what, detail] for the page, and the same as plain text to copy and send.
     */
    public static function checkWayIn(PDO $pdo, int $uid, string $name, int $sid): array
    {
        require_once __DIR__ . '/Releases.php';
        $s = self::get($pdo, 'setup');
        if (!$s || empty($s['base'])) throw new RuntimeException('Staging isn\'t set up: press Set up staging first.');
        $L = self::layout((string) $s['base']);
        $k = self::barKeys($pdo);
        if (!$k) throw new RuntimeException('Staging\'s .env isn\'t in its folder: press Set up staging again.');
        $env = self::readEnvFile($L['env']);
        $rows = [];
        $add = function (string $what, bool $good, string $detail, string $mark = '') use (&$rows): void { $rows[] = [$mark !== '' ? $mark : ($good ? '✓' : '✗'), $mark === 'ⓘ' ? '' : ($mark === '!' || !$good ? 'bad' : 'ok'), $what, $detail]; };

        // 1. the requests, as a browser makes them
        $faq = self::probe('/faq.php');
        $add('No credentials (/faq.php)', $faq['code'] === 401, self::said($faq) . ' (should ask: 401)');
        $withPass = self::probe('/faq.php', ['Cookie: tv_stg_pass=' . $k[1]]);
        $add('With the pass cookie', $withPass['code'] === 200, self::said($withPass) . ' (should open: 200)');
        $chk = Releases::checkLogin($pdo);
        $withLogin = $chk ? self::probe('/faq.php', [], $chk) : null;
        $add('With the checks\' sign-in (tv-releases)', $withLogin && $withLogin['code'] === 200, $withLogin ? self::said($withLogin) . ' (should open: 200)' : 'no sign-in to try');
        $robots = self::probe('/robots.txt');
        $add('robots.txt, open to all', $robots['code'] === 200, self::said($robots));
        // the whole of Open staging: a fresh signed link (as you, to /faq.php), then the page it sends you to with the cookies it gave
        try {
            $url = Releases::enterUrl($pdo, 0, 'me', '/faq.php', $uid, $name, $sid);
            $enter = self::probe(substr($url, strlen(Staging::siteBase())));
            $names = implode(', ', $enter['cookies']) ?: 'none';
            $add('Open staging: /stg-enter.php', $enter['code'] === 302 && in_array('tv_stg_pass', $enter['cookies'], true),
                self::said($enter) . ' · cookies given: ' . $names . ' · sends you to ' . ($enter['location'] !== '' ? $enter['location'] : '(nowhere)') . ' (should be 302, with tv_stg_pass)');
            if ($enter['code'] === 302) {
                $jar = []; foreach ($enter['jar'] as $c => $v) $jar[] = $c . '=' . $v;
                $to = $enter['location'] !== '' && $enter['location'][0] === '/' ? $enter['location'] : '/faq.php';
                $after = self::probe($to, $jar ? ['Cookie: ' . implode('; ', $jar)] : []);
                $add('…then the page, with those cookies', $after['code'] === 200, self::said($after) . ' (should open: 200)');
            }
        } catch (Throwable $x) { $add('Open staging: /stg-enter.php', false, 'couldn\'t make the link: ' . $x->getMessage()); }

        // 2. what staging's own PHP sees (with the checks' sign-in, its token and the pass cookie)
        $tok = (string) ($env['HEALTH_TOKEN'] ?? '');
        $p = $chk && $tok !== '' ? self::probe('/api/stg-probe.php', ['X-Health-Token: ' . $tok, 'Cookie: tv_stg_pass=' . $k[1]], $chk) : null;
        $j = $p ? json_decode($p['body'], true) : null;
        if (is_array($j) && !empty($j['ok'])) {
            $flat = [];
            foreach (['php', 'guard', 'env', 'cookie', 'auth'] as $g) foreach ((array) ($j[$g] ?? []) as $kk => $v) $flat[] = $g . ' ' . $kk . ': ' . (is_bool($v) ? ($v ? 'yes' : 'no') : (string) $v);
            $good = !empty($j['guard']['ran']) && !empty($j['cookie']['matches']);
            $add('What staging\'s PHP sees', $good, implode(' · ', $flat));
        } else $add('What staging\'s PHP sees', false, $p ? 'the probe didn\'t answer clearly: ' . self::said($p) : 'not asked (no checks\' sign-in or no HEALTH_TOKEN)');

        // 3. on disk: the keys' shapes, the guard, .user.ini and the rules on the way down
        $shape = function (string $v, int $min): string { return $v === '' ? 'missing' : (preg_match('/^[A-Za-z0-9]{' . $min . ',}$/', $v) ? 'the right shape (' . strlen($v) . ' letters and digits)' : 'the wrong shape (' . strlen($v) . ' characters, not all letters and digits)'); };
        $pass = (string) ($env['STAGING_PASS'] ?? '');
        $add('Staging\'s .env', preg_match('/^[A-Za-z0-9]{32,}$/', $pass) === 1, 'STAGING_PASS ' . $shape($pass, 32) . ' · RELEASE_BAR_KEY ' . $shape((string) ($env['RELEASE_BAR_KEY'] ?? ''), 40)
            . ' · STAGING_PASS_RULES ' . ($env['STAGING_PASS_RULES'] ?? '(unset: on)') . ' · STAGING_PASS_FORM ' . ($env['STAGING_PASS_FORM'] ?? '(unset)') . ' · last test: ' . ((string) ($env['STAGING_PASS_TEST'] ?? '') ?: 'none'));
        $g = $L['home'] . '/staging-guard.php'; $gc = (string) @file_get_contents($g);
        $add('The guard file', $gc !== '' && str_contains($gc, 'X-Tv-Stg-Gate'), $gc === '' ? 'not there (' . $g . ')' : 'there, ' . strlen($gc) . ' bytes, ' . (str_contains($gc, 'X-Tv-Stg-Gate') ? 'with its mark' : 'an older copy, without its mark') . ', written ' . gmdate('M j H:i', (int) @filemtime($g)) . ' UTC');
        $inis = [];
        foreach ([$L['root'] . '/.user.ini', $L['root'] . '/php.ini', $L['root'] . '/website/.user.ini', $L['root'] . '/website/php.ini'] as $f)
            if (is_file($f)) $inis[] = substr($f, strlen($L['home']) + 1) . ': ' . self::hide(implode(' / ', array_map('trim', file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [])));
        $ui = is_file($L['root'] . '/.user.ini') && str_contains((string) @file_get_contents($L['root'] . '/.user.ini'), 'staging-guard.php');
        $add('.user.ini (has PHP run the guard first)', $ui, $inis ? implode(' · ', $inis) : 'none in staging\'s root or website/');
        $add('The live server\'s PHP (staging\'s is likely the same)', true, 'PHP ' . PHP_VERSION . ' · ' . PHP_SAPI . ' · user_ini.filename ' . (string) ini_get('user_ini.filename') . ' · user_ini.cache_ttl ' . (string) ini_get('user_ini.cache_ttl') . ' s', 'ⓘ');
        $users = [];
        foreach (@file($L['htpasswd'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) $users[] = explode(':', $l, 2)[0];
        $add('The viewing-password file', $users !== [], $users ? 'sign-ins: ' . implode(', ', $users) : 'not there or empty (' . $L['htpasswd'] . ')');
        // every .htaccess the web server reads on the way to a page: from the hosting account's home down to website/
        $dirs = []; $d = $L['root'] . '/website'; $stop = dirname(self::liveHome());
        while ($d !== '' && $d !== '/' && $d !== $stop && strlen($d) > strlen($stop)) { $dirs[] = $d; $d = dirname($d); }
        foreach (array_reverse($dirs) as $dir) {
            $f = $dir . '/.htaccess';
            if (!is_file($f)) continue;
            $lines = file($f, FILE_IGNORE_NEW_LINES) ?: [];
            $rel = $dir === self::liveHome() ? '~' : '~/' . substr($dir, strlen(self::liveHome()) + 1);
            $keep = [];
            foreach ($lines as $i => $l) {
                $t = trim($l);
                if ($t === '' || $t[0] === '#') continue;
                if ($dir === $L['root'] ? $i < 40 : preg_match('/^(<\/?)?(Auth|Require|Satisfy|Order|Deny|Allow|php_|SetEnv|Files|If|Directory|Limit|Header|Options|LiteSpeed|RewriteRule|ErrorDocument)/i', $t)) $keep[] = $t;
            }
            $auth = preg_grep('/^\s*(AuthType|Require\s+(valid-user|user|all\s+denied))/i', $lines);
            $add('Rules in ' . $rel . '/.htaccess', true, (count($lines) . ' lines' . ($auth ? '; asks or refuses here: ' . implode(' | ', array_map('trim', $auth)) : '') . ' · ' . self::hide(implode(' / ', array_slice($keep, 0, 40)))), $auth ? '!' : 'ⓘ');
        }

        $text = 'Check the way in, ' . gmdate('Y-m-d H:i') . " UTC\n";
        foreach ($rows as $r) $text .= $r[0] . ' ' . $r[2] . ': ' . $r[3] . "\n";
        return [$rows, $text];
    }

    /** One request to staging, not followed: the code, the headers that tell the layers apart, the cookies given and a little of the page. */
    private static function probe(string $path, array $headers = [], ?array $auth = null): array
    {
        $h = [];
        require_once __DIR__ . '/StagingGate.php'; if (($w = StagingGate::hold(Staging::siteBase() . $path, 'check the way in')) !== null) return ['code' => 0, 'err' => $w, 'www' => '', 'gate' => '', 'server' => '', 'location' => '', 'cookies' => [], 'jar' => [], 'title' => '', 'body' => ''];   // a call to staging itself: always goes
        $ch = curl_init(Staging::siteBase() . $path);
        $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'Traversence way-in check',
              CURLOPT_HTTPHEADER => $headers,
              CURLOPT_HEADERFUNCTION => function ($c, string $line) use (&$h): int { $i = strpos($line, ':'); if ($i !== false) $h[strtolower(trim(substr($line, 0, $i)))][] = trim(substr($line, $i + 1)); return strlen($line); }];
        if ($auth) { $o[CURLOPT_USERPWD] = $auth[0] . ':' . $auth[1]; $o[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC; }
        curl_setopt_array($ch, $o);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch);
        curl_close($ch);
        $body = is_string($body) ? $body : '';
        $names = []; $jar = [];
        foreach ($h['set-cookie'] ?? [] as $c) if (preg_match('/^([^=;\s]+)=([^;]*)/', $c, $m)) { $names[] = $m[1]; $jar[$m[1]] = $m[2]; }
        $title = preg_match('#<title[^>]*>(.*?)</title>#is', $body, $m) ? trim(html_entity_decode(strip_tags($m[1]))) : '';
        return ['code' => $code, 'err' => $err, 'www' => (string) ($h['www-authenticate'][0] ?? ''), 'gate' => (string) ($h['x-tv-stg-gate'][0] ?? ''),
                'server' => (string) ($h['server'][0] ?? ''), 'location' => (string) ($h['location'][0] ?? ''), 'cookies' => $names, 'jar' => $jar, 'title' => $title, 'body' => $body];
    }

    /** What a probe got, in a line: the code, and for a 401 which layer asked. */
    private static function said(array $p): string
    {
        if ($p['code'] === 0) return 'no answer (' . ($p['err'] ?: 'couldn\'t connect') . ')';
        $who = '';
        if ($p['code'] === 401) $who = $p['gate'] === 'guard-file' ? ' from staging\'s guard file'
            : ($p['gate'] === 'page' ? ' from the page itself (Staging::guard)'
            : (stripos($p['www'], 'charset') !== false ? ' from PHP (a guard without its mark: an older copy)' : ' from the web server (its own password rule, not PHP)'));
        return $p['code'] . $who . ($p['www'] !== '' ? ' · asks: ' . $p['www'] : '') . ($p['gate'] !== '' ? ' · gate: ' . $p['gate'] : '')
             . ($p['server'] !== '' ? ' · server: ' . $p['server'] : '') . ($p['title'] !== '' ? ' · page: "' . mb_substr($p['title'], 0, 60) . '"' : '');
    }

    /** Long secrets in a line of rules or settings, hidden (a run of 24 letters and digits or more: the pass and keys are hex). */
    private static function hide(string $s): string { return (string) preg_replace('/[A-Za-z0-9]{24,}/', '•••', $s); }

    /** Sets lines in staging's .env (keeping the rest). */
    private static function setEnv(array $L, array $kv): void
    {
        $lines = is_file($L['env']) ? (file($L['env'], FILE_IGNORE_NEW_LINES) ?: []) : [];
        foreach ($kv as $k => $v) {
            $hit = false;
            foreach ($lines as $i => $l) if (preg_match('/^\s*' . preg_quote($k, '/') . '\s*=/', $l)) { $lines[$i] = $k . '=' . $v; $hit = true; }
            if (!$hit) $lines[] = $k . '=' . $v;
        }
        self::writeFile($L['env'], implode("\n", $lines) . "\n", 0600);
    }

    /** The live root .htaccess as of 2026-11-11 (canonical/public_html-traversence.htaccess), for when it can't be read. */
    private const LIVE_RULES = "RewriteEngine On\nRewriteRule ^app(/|$) - [F,L]\nRewriteCond %{THE_REQUEST} \\s/website/ [NC]\nRewriteRule ^website/(.*)$ /$1 [R=301,L]\n"
        . "RewriteCond %{HTTP_HOST} ^(www\\.)?traversence\\.com$ [NC]\nRewriteCond %{REQUEST_URI} !^/website/\nRewriteRule ^(.*)$ /website/$1 [L]\n"
        . "<FilesMatch \"^\\.\">\n  Require all denied\n</FilesMatch>\n<FilesMatch \"\\.(sql|md|log|bak|zip|ini|sh|py|tsv)$\">\n  Require all denied\n</FilesMatch>\n<Files \"error_log\">\n  Require all denied\n</Files>\n"
        . "RewriteCond %{REQUEST_URI} ^(/traversence)?/website/(_SUPPORT|_notes|_gsdata_|_pending|_concepts|includes|scripts|api/(lib|migrations|scripts|middleware))(/|$) [NC]\nRewriteRule ^ - [F,L]\n"
        . "Options -Indexes\nErrorDocument 403 /website/_errors/403.html\n";

    private static function writeFile(string $path, string $text, int $mode): void
    {
        $tmp = $path . '.new-' . bin2hex(random_bytes(4));
        $old = umask(0077);
        try {
            if (@file_put_contents($tmp, $text) !== strlen($text)) { @unlink($tmp); throw new RuntimeException('Couldn\'t write ' . basename($path) . ' in staging\'s folder.'); }
            @chmod($tmp, $mode);
            if (!@rename($tmp, $path)) { @unlink($tmp); throw new RuntimeException('Couldn\'t put ' . basename($path) . ' in place in staging\'s folder.'); }
        } finally { umask($old); }
    }

    private static function password(int $n): string
    {
        $a = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $p = 'Q7a-';   // an upper, a digit, a lower and a mark, for cPanel's strength check
        for ($i = strlen($p); $i < $n; $i++) $p .= $a[random_int(0, strlen($a) - 1)];
        return str_shuffle($p);
    }

    // ---- Refresh -----------------------------------------------------------------------------------------------------

    /** The refresh in progress, or null. */
    public static function running(PDO $pdo): ?array
    {
        $r = self::get($pdo, 'refresh');
        return $r && !isset($r['finished_at']) ? $r : null;
    }

    /**
     * Starts a refresh (or carries on the one in progress): its steps are live's files, each table of each database,
     * clearing tables live no longer has, the strip, and finishing. Returns the progress, like step().
     */
    public static function start(PDO $pdo, int $uid, string $tz = ''): array
    {
        if (Staging::here()) throw new RuntimeException('Staging is refreshed from the live site, not from staging itself.');
        if (!self::isSetUp($pdo)) throw new RuntimeException('Staging isn\'t set up yet: press Set up staging first.');
        $setup = (array) self::get($pdo, 'setup');
        if ($r = self::running($pdo)) {
            if (time() - (int) ($r['updated'] ?? 0) < 1800) return self::progress($r);
            $r['log'][] = 'An earlier refresh stopped part way; this one starts again.';
        }
        $steps = [['begin'], ['files']];
        foreach ($setup['dbs'] as $realm => $db) {
            self::stg($setup, $realm);
            $l = self::liveDbs()[$realm] ?? null;
            if (!$l) throw new RuntimeException('The live ' . $realm . ' database isn\'t in the live .env any more. Press Set up staging again.');
            $st = self::conn($realm)->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
            $st->execute([$l[0]]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $t) if (!self::skipTable((string) $t)) $steps[] = ['copy', $realm, (string) $t];
            $steps[] = ['tidy', $realm];
        }
        $steps = self::stripSteps($steps, array_keys($setup['dbs']));
        // keeping staging caught up (decisions/0165 §3): after the copy, each kept table's triggers and base, so staging's
        // work from here on is recorded and catch-up knows where the sides matched
        $mirror = self::get($pdo, 'mirror');
        if (!empty($mirror['on']) || !empty($mirror['starting'])) foreach ($steps as $x) if (($x[0] ?? '') === 'copy' && in_array(StagingTables::classOf((string) $x[2]), ['records', 'money'], true)) $steps[] = ['base', $x[1], $x[2]];
        $steps[] = ['finish'];
        $r = ['id' => bin2hex(random_bytes(6)), 'started_at' => date('c'), 'tz' => $tz, 'by' => $uid, 'steps' => $steps, 'pos' => 0, 'log' => [], 'rows' => 0, 'tables' => 0,
              'files' => [], 'stripped' => [], 'salt' => bin2hex(random_bytes(16)), 'updated' => time()];
        self::put($pdo, 'refresh', $r);
        return self::progress($r);
    }

    /**
     * The strip a table at a time (each copied table of each database), so no one request runs past the web server's limit:
     * a database's whole strip at once ran for minutes on the main database and the page lost its answer ("Failed to fetch").
     * Also turns a refresh begun before this change (one ['strip', realm] step) into the same.
     */
    private static function stripSteps(array $steps, array $realms): array
    {
        $out = [];
        foreach ($steps as $x) if (($x[0] ?? '') !== 'strip' || isset($x[2])) $out[] = $x;
        foreach ($realms as $realm) foreach ($steps as $x) if (($x[0] ?? '') === 'copy' && $x[1] === $realm) $out[] = ['strip', $realm, $x[2]];
        return $out;
    }

    /** A refresh begun before the strip went a table at a time: its one strip step per database becomes one per table. */
    private static function splitOldStrip(array &$r): void
    {
        $pos = (int) $r['pos']; $s = $r['steps'];
        if (!array_filter($s, fn ($x) => ($x[0] ?? '') === 'strip' && !isset($x[2]))) return;
        $done = array_slice($s, 0, $pos); $rest = array_slice($s, $pos);
        $realms = array_values(array_map(fn ($x) => $x[1], array_filter($rest, fn ($x) => ($x[0] ?? '') === 'strip' && !isset($x[2]))));
        $tail = array_values(array_filter($rest, fn ($x) => ($x[0] ?? '') !== 'strip'));
        $new = [];
        foreach ($realms as $realm) foreach ($s as $x) if (($x[0] ?? '') === 'copy' && $x[1] === $realm) $new[] = ['strip', $realm, $x[2]];
        $r['steps'] = array_merge($done, array_values(array_filter($tail, fn ($x) => ($x[0] ?? '') !== 'finish')), $new, [['finish']]);
    }

    private static function skipTable(string $t): bool
    {
        return str_starts_with($t, '_moved_') || in_array($t, self::SKIP_TABLES, true) || in_array($t, Staging::OWN_TABLES, true);
    }

    /** Works on the refresh for up to $seconds (a step at a time), then says where it is: [done, ok, pos, of, now, message, log]. */
    public static function step(PDO $pdo, int $seconds = 20): array
    {
        $r = self::running($pdo);
        if (!$r) { $last = self::get($pdo, 'refresh'); return $last ? self::progress($last) : ['done' => true, 'ok' => false, 'message' => 'No refresh is running.']; }
        if (!self::lock($pdo)) return self::progress($r) + ['busy' => true];
        @set_time_limit(max(60, $seconds * 6));
        $t0 = time();
        try {
            $setup = (array) self::get($pdo, 'setup');
            self::splitOldStrip($r);
            while ($r['pos'] < count($r['steps']) && (time() - $t0 < $seconds || $r['pos'] === 0)) {
                self::runStep($pdo, $setup, $r, $r['steps'][$r['pos']]);
                $r['pos']++; $r['updated'] = time();
                self::put($pdo, 'refresh', $r);
            }
            if ($r['pos'] >= count($r['steps'])) {
                $r['finished_at'] = date('c'); $r['ok'] = true; unset($r['salt']);
                $r['log'][] = 'Done: ' . number_format((int) $r['tables']) . ' tables, ' . number_format((int) $r['rows']) . ' rows, personal details replaced in ' . count($r['stripped']) . ' tables.';
                self::put($pdo, 'refresh', $r);
                self::tellDone($pdo, $r);
                self::put($pdo, 'last', ['at' => $r['finished_at'], 'started_at' => $r['started_at'], 'tz' => $r['tz'], 'ok' => true, 'tables' => $r['tables'], 'rows' => $r['rows'], 'files' => $r['files'], 'stripped' => $r['stripped'], 'by' => $r['by']]);
            }
        } catch (Throwable $e) {
            // staging stays closed (its refresh flag stays up) until a refresh finishes: a half copy is never shown
            $step = implode(' ', array_slice((array) ($r['steps'][$r['pos']] ?? []), 0, 3));
            error_log('staging refresh (' . $step . '): ' . $e->getMessage());
            $r['finished_at'] = date('c'); $r['ok'] = false; unset($r['salt']);
            $why = $e instanceof PDOException ? 'the database refused (' . $e->getMessage() . '). Pressing Set up staging again gives each login its databases again' : ($e instanceof RuntimeException ? $e->getMessage() : 'an error (it\'s in the error log)');
            $r['message'] = 'The refresh stopped at "' . $step . '": ' . rtrim($why, '.') . '. Staging stays closed until a refresh finishes: press Start staging over to try again, or ask us at support@traversence.com.';
            $r['log'][] = $r['message'];
            self::put($pdo, 'refresh', $r);
            self::tellDone($pdo, $r);
            self::put($pdo, 'last', ['at' => $r['finished_at'], 'started_at' => $r['started_at'], 'tz' => $r['tz'], 'ok' => false, 'message' => $r['message'], 'by' => $r['by']]);
        } finally { self::unlock($pdo); }
        return self::progress($r);
    }

    /**
     * An email to whoever started the refresh, when it's done or stops (a system email: it costs nothing), with the time in
     * the zone they started it from. The weekly job's refreshes (no one started them) send none.
     */
    private static function tellDone(PDO $pdo, array $r): void
    {
        try {
            $uid = (int) ($r['by'] ?? 0);
            if (!$uid) return;
            $st = $pdo->prepare('SELECT email FROM users WHERE id = ?'); $st->execute([$uid]);
            $to = (string) $st->fetchColumn();
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;
            $tz = 'UTC'; try { if ((string) ($r['tz'] ?? '') !== '') { new DateTimeZone((string) $r['tz']); $tz = (string) $r['tz']; } } catch (Throwable) {}
            $when = (new DateTimeImmutable('now', new DateTimeZone($tz)))->format('M j, g:i A T');
            $ok = !empty($r['ok']);
            $text = ($ok ? 'Staging (staging.trvgo.us) is refreshed: ' . (string) end($r['log']) : (string) ($r['message'] ?? 'The staging refresh stopped.'))
                  . "

Finished " . $when . ".
See it in System Configuration → Staging: https://traversence.com/admin/config/staging"
                  . "

Something not right? Write to support@traversence.com.";
            require_once __DIR__ . '/Mailer.php';
            Mailer::send($to, $ok ? 'Staging is refreshed' : 'The staging refresh stopped', $text);
        } catch (Throwable $e) { error_log('staging done email: ' . $e->getMessage()); }
    }

    /**
     * Works on the refresh until it's done or $max seconds pass (the server's own job, staging_carry_tick: the command line
     * has no time limit). Stops at once when another run holds it.
     */
    public static function carry(PDO $pdo, int $max = 3300): array
    {
        $end = time() + $max; $p = ['done' => true, 'ok' => false, 'message' => 'No refresh is running.'];
        while (time() < $end) {
            $p = self::step($pdo, 50);
            if (!empty($p['done']) || !empty($p['busy'])) break;
        }
        return $p;
    }

    /**
     * Gets the refresh going on the server, so it carries on with the page closed: makes sure the every-minute job
     * (staging_carry_tick) is in cPanel, and starts a run of it now where the server lets a page start one. Never fails
     * the request: the page's own steps still carry it on.
     */
    public static function kick(PDO $pdo): void
    {
        require_once __DIR__ . '/Scheduler.php';
        $lines = [];
        try {
            if (CPanel::ready()) {
                $lines = Scheduler::cronLines();
                if (!Scheduler::lineFor($lines, 'staging_carry_tick')) { Scheduler::add('staging_carry_tick', 'minute'); $lines = Scheduler::cronLines(); }
            }
        } catch (Throwable $e) { error_log('staging kick (cron): ' . $e->getMessage()); }
        $off = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (!function_exists('exec') || in_array('exec', $off, true)) return;
        try { @exec(Scheduler::command('staging_carry_tick', $lines) . ' &'); } catch (Throwable $e) { error_log('staging kick (run): ' . $e->getMessage()); }
    }

    /** Where the latest refresh is (for the page), or null when there's never been one. */
    public static function progressNow(PDO $pdo): ?array
    {
        $r = self::get($pdo, 'refresh');
        return $r ? self::progress($r) : null;
    }

    private static function progress(array $r): array
    {
        $of = count($r['steps'] ?? []); $pos = (int) ($r['pos'] ?? 0);
        $next = $r['steps'][$pos] ?? null;
        $now = !$next ? 'Finished' : match ($next[0]) {
            'begin' => 'Closing staging while it\'s copied', 'files' => 'Copying the site\'s files', 'copy' => 'Copying ' . $next[2] . ' (' . $next[1] . ')',
            'tidy' => 'Clearing tables live no longer has (' . $next[1] . ')', 'strip' => 'Replacing personal details with test values (' . (isset($next[2]) ? $next[2] . ', ' : '') . $next[1] . ')', default => 'Finishing',
        };
        $done = isset($r['finished_at']);
        return ['done' => $done, 'ok' => $done ? (bool) ($r['ok'] ?? false) : null, 'pos' => $pos, 'of' => $of, 'now' => $now,
                'message' => (string) ($r['message'] ?? ($done ? (string) end($r['log']) : '')), 'log' => array_slice((array) ($r['log'] ?? []), -12)];
    }

    private static function runStep(PDO $pdo, array $setup, array &$r, array $s): void
    {
        $L = self::layout((string) $setup['base']);
        switch ($s[0]) {
            case 'begin':
                self::writeFile($L['home'] . '/.refreshing', date('c') . "\n", 0644);
                return;
            case 'files':
                $n = ['copied' => 0, 'deleted' => 0, 'files' => 0];
                foreach (self::FOLDERS as $f) self::mirror(self::liveRoot() . '/' . $f, $L['root'] . '/' . $f, $f, $n);
                $r['files'] = $n;
                $r['log'][] = 'Files: ' . $n['files'] . ' in the copy, ' . $n['copied'] . ' copied, ' . $n['deleted'] . ' removed (live no longer has them).';
                $hosts = self::hostRules($L['root']);
                if ($hosts) $r['log'][] = 'Note: ' . implode(', ', $hosts) . ' name traversence.com; if staging sends you to the live site, that\'s where.';
                return;
            case 'copy':
                [, $realm, $t] = $s;
                $c = self::conn($realm); $src = self::liveDbs()[$realm][0]; $dst = self::stg($setup, $realm);
                $c->exec('SET FOREIGN_KEY_CHECKS = 0');
                $c->exec('DROP TABLE IF EXISTS ' . self::q($dst, $t));
                $c->exec('CREATE TABLE ' . self::q($dst, $t) . ' LIKE ' . self::q($src, $t));
                if (!in_array($t, self::EMPTY_TABLES, true)) {
                    $cols = self::copyColumns($c, $src, $t);
                    $r['rows'] += (int) $c->exec('INSERT INTO ' . self::q($dst, $t) . ' (' . $cols . ') SELECT ' . $cols . ' FROM ' . self::q($src, $t));
                }
                $c->exec('SET FOREIGN_KEY_CHECKS = 1');
                $r['tables']++;
                return;
            case 'tidy':
                [, $realm] = $s;
                $c = self::conn($realm); $dst = self::stg($setup, $realm);
                $want = array_map(fn ($x) => $x[2], array_filter($r['steps'], fn ($x) => $x[0] === 'copy' && $x[1] === $realm));
                $st = $c->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'"); $st->execute([$dst]);
                $c->exec('SET FOREIGN_KEY_CHECKS = 0');
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $t) if (!in_array($t, $want, true) && !in_array($t, Staging::OWN_TABLES, true)) $c->exec('DROP TABLE ' . self::q($dst, (string) $t));
                $c->exec('SET FOREIGN_KEY_CHECKS = 1');
                return;
            case 'strip':
                [, $realm] = $s;
                if (!isset($r['test_ids'])) $r['test_ids'] = self::testIds($pdo);   // once a refresh, not once a table
                [$users, $staff] = $r['test_ids'];
                foreach (self::strip(self::conn($realm), self::stg($setup, $realm), $users, $staff, (string) $r['salt'], $s[2] ?? null) as $t => $cols) $r['stripped'][$realm . '.' . $t] = $cols;
                return;
            case 'base':
                require_once __DIR__ . '/StagingSync.php';
                [, $realm, $t] = $s;
                $m = (array) self::get($pdo, 'mirror');
                // "Don't turn it on" pressed while the copy goes in: no trigger goes on live or staging after it (keepCaughtUp(false) took live's off)
                if (empty($m['on']) && empty($m['starting'])) { if (empty($r['base_skipped'])) { $r['base_skipped'] = true; $r['log'][] = 'Keeping caught up was turned off while the copy went in: no outbox goes on live.'; } return; }
                if (!isset($m['trig'][$realm])) { $m['trig'][$realm] = ['staging' => StagingSync::canTrigger($realm, 'staging'), 'live' => StagingSync::canTrigger($realm, 'live')]; self::put($pdo, 'mirror', $m); }
                if ($m['trig'][$realm]['staging']) StagingSync::stagingTriggers($realm, $t);
                if ($m['trig'][$realm]['live']) StagingSync::outboxTriggers($realm, $t);
                StagingSync::baseTable($realm, $t);
                return;
            case 'finish':
                $m = self::get($pdo, 'mirror');
                if ($m && !empty($m['starting'])) { unset($m['starting']); $m['on'] = true; $m['since'] = date('c'); self::put($pdo, 'mirror', $m); $r['log'][] = 'Staging keeps caught up from here: live\'s changes come in within a minute, staging\'s own work is kept.'; }
                $notes = self::finish($pdo, $setup);
                foreach ($notes as $n) $r['log'][] = $n;
                self::writeRules($L);
                @unlink($L['home'] . '/.refreshing');
                return;
        }
    }

    /** The columns to copy (all but generated ones, which the database fills itself). */
    private static function copyColumns(PDO $c, string $db, string $t): string
    {
        $st = $c->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND EXTRA NOT LIKE '%GENERATED%' AND EXTRA NOT LIKE '%VIRTUAL%' AND EXTRA NOT LIKE '%PERSISTENT%' AND EXTRA NOT LIKE '%STORED%' ORDER BY ORDINAL_POSITION");
        $st->execute([$db, $t]);
        $cols = $st->fetchAll(PDO::FETCH_COLUMN);
        if (!$cols) throw new RuntimeException('Couldn\'t read the columns of ' . $t . '.');
        return implode(', ', array_map(fn ($x) => '`' . str_replace('`', '', (string) $x) . '`', $cols));
    }

    /** Copies one of live's folders into staging's: new and changed files (by size and time), and removes what live no longer has. */
    private static function mirror(string $from, string $to, string $rel, array &$n): void
    {
        if (!is_dir($from)) return;
        if (!is_dir($to) && !@mkdir($to, 0755, true)) throw new RuntimeException('Couldn\'t make staging\'s ' . $rel . ' folder.');
        $seen = [];
        $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
            fn ($f) => !self::skipPath($rel . '/' . substr((string) $f->getPathname(), strlen($from) + 1), $f->isDir())), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $f) {
            $sub = substr((string) $f->getPathname(), strlen($from) + 1);
            $dst = $to . '/' . $sub;
            $seen[$sub] = true;
            if ($f->isDir()) { if (!is_dir($dst)) @mkdir($dst, 0755, true); continue; }
            $n['files']++;
            if (is_file($dst) && filesize($dst) === $f->getSize() && filemtime($dst) === $f->getMTime()) continue;
            if (!@copy((string) $f->getPathname(), $dst)) throw new RuntimeException('Couldn\'t copy ' . $rel . '/' . $sub . ' to staging.');
            @chmod($dst, fileperms((string) $f->getPathname()) & 0777);
            @touch($dst, $f->getMTime());
            $n['copied']++;
        }
        // what live no longer has goes from staging too (never its own files, nor the folders a refresh doesn't copy)
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($to, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $sub = substr((string) $f->getPathname(), strlen($to) + 1);
            if (isset($seen[$sub]) || in_array($rel . '/' . $sub, self::OWN_FILES, true) || self::kept($rel . '/' . $sub)) continue;
            if ($f->isDir()) { @rmdir((string) $f->getPathname()); continue; }   // only when empty
            if (@unlink((string) $f->getPathname())) $n['deleted']++;
        }
    }

    /** Not copied (SKIP_PATHS, SKIP_NAMES, dotfiles but folders' .htaccess, Dreamweaver's leftovers). */
    private static function skipPath(string $rel, bool $dir): bool
    {
        foreach (self::SKIP_PATHS as $p) if ($rel === $p || str_starts_with($rel, $p . '/')) return true;
        $parts = explode('/', $rel); $name = (string) end($parts);
        foreach ($parts as $x) if (in_array($x, self::SKIP_NAMES, true)) return true;
        if ($name !== '' && $name[0] === '.') return $dir || $name !== '.htaccess' || str_starts_with($rel, 'trvgo/');
        return !$dir && preg_match('/\.(ste|mno)$/i', $name) === 1;
    }

    /** Staging's own files under the paths a refresh doesn't copy (its sessions and caches in app/storage, its uploads) are kept, never removed. */
    private static function kept(string $rel): bool
    {
        if (self::skipPath($rel, false) && !str_ends_with($rel, '.htaccess')) return true;   // app/storage, uploads, notes: staging's own
        return false;
    }

    /** Copied .htaccess files that name the live host (a redirect there would send staging's visitors to live). */
    private static function hostRules(string $root): array
    {
        $out = [];
        foreach (['website', 'app'] as $f) {
            if (!is_dir($root . '/' . $f)) continue;
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $f, FilesystemIterator::SKIP_DOTS)) as $x)
                if ($x->getFilename() === '.htaccess' && stripos((string) @file_get_contents((string) $x->getPathname()), 'traversence.com') !== false) $out[] = substr((string) $x->getPathname(), strlen($root) + 1);
        }
        return $out;
    }

    /** The test accounts: [member user ids, staff login ids] (TestAccounts' record, decisions/0145 §3). */
    private static function testIds(PDO $pdo): array
    {
        $users = []; $staff = [];
        try {
            require_once __DIR__ . '/TestAccounts.php';
            foreach (TestAccounts::made($pdo) as $k => $id) { if (str_starts_with($k, 'user|')) $users[] = (int) $id; if (str_starts_with($k, 'staff|')) $staff[] = (int) $id; }
            if (TestAccounts::ready($pdo)) foreach ($pdo->query('SELECT id FROM users WHERE is_test = 1')->fetchAll(PDO::FETCH_COLUMN) as $id) $users[] = (int) $id;
            foreach ($staff as $sid) { $st = $pdo->prepare('SELECT user_id, member_user_id FROM ' . Database::t('staff_accounts') . ' WHERE id = ?'); $st->execute([$sid]); foreach ((array) $st->fetch(PDO::FETCH_NUM) as $u) if ((int) $u) $users[] = (int) $u; }
        } catch (Throwable $e) { error_log('staging test ids: ' . $e->getMessage()); }
        return [array_values(array_unique(array_filter($users))), array_values(array_unique(array_filter($staff)))];
    }

    /**
     * Replaces people's details in one of staging's databases with test values (the same value for the same person, so
     * what matched still matches, and never the real one). Test accounts keep theirs. Returns [table => columns changed].
     */
    public static function strip(PDO $c, string $db, array $testUsers, array $testStaff, string $salt, ?string $only = null, ?string $rowWhere = null): array
    {
        $c->prepare('SET @tv_salt = ?')->execute([$salt]);
        $c->prepare('SET @tv_nohash = ?')->execute([password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT)]);   // a password nobody knows
        $st = $c->prepare("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS c
                           WHERE TABLE_SCHEMA = ? AND EXISTS (SELECT 1 FROM information_schema.TABLES t WHERE t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME AND t.TABLE_TYPE = 'BASE TABLE')
                           " . ($only !== null ? 'AND c.TABLE_NAME = ? ' : '') . "ORDER BY TABLE_NAME, ORDINAL_POSITION");
        $st->execute($only !== null ? [$db, $only] : [$db]);
        $tables = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $x) $tables[(string) $x['TABLE_NAME']][(string) $x['COLUMN_NAME']] = $x;
        $in = fn (array $ids) => implode(',', array_map('intval', $ids));
        $out = [];
        foreach ($tables as $t => $cols) {
            if (in_array($t, Staging::OWN_TABLES, true)) continue;
            if (isset(self::DELETE_ROWS[$t])) {
                [$col, $where] = self::DELETE_ROWS[$t];
                if ($rowWhere !== null && isset($cols[$col])) $where = '(' . $where . ') AND (' . $rowWhere . ')';
                $c->exec('DELETE FROM ' . self::q($db, $t) . (isset($cols[$col]) ? ' WHERE ' . $where : ''));
                $out[$t][] = '(rows: ' . (isset($cols[$col]) ? $where : 'all') . ')';
                if ($t !== 'staff_settings') continue;
            }
            $set = [];
            foreach ($cols as $name => $x) {
                $e = self::stripRule($t, (string) $name, strtolower((string) $x['DATA_TYPE']), (int) ($x['CHARACTER_MAXIMUM_LENGTH'] ?? 0), $x['IS_NULLABLE'] === 'YES');
                if ($e !== null) $set[] = '`' . str_replace('`', '', (string) $name) . '` = ' . $e;
            }
            if (!$set) continue;
            $where = [];   // the test accounts keep theirs
            if ($t === 'users') { if (isset($cols['is_test'])) $where[] = 'is_test = 0'; if ($testUsers) $where[] = 'id NOT IN (' . $in($testUsers) . ')'; }
            elseif ($t === 'staff_accounts') { if ($testStaff) $where[] = 'id NOT IN (' . $in($testStaff) . ')'; }
            elseif (isset($cols['user_id']) && $testUsers) $where[] = '(user_id IS NULL OR user_id NOT IN (' . $in($testUsers) . '))';
            if ($rowWhere !== null) $where[] = '(' . $rowWhere . ')';   // catch-up: one row on its way in (decisions/0165 §3)
            $c->exec('UPDATE ' . self::q($db, $t) . ' SET ' . implode(', ', $set) . ($where ? ' WHERE ' . implode(' AND ', $where) : ''));
            $out[$t] = array_merge($out[$t] ?? [], array_map(fn ($s) => trim(strtok($s, '='), ' `'), $set));
        }
        return $out;
    }

    /**
     * The test value a column gets in the strip (SQL), or null when it keeps its own: emails, phone numbers, IP addresses
     * and browser descriptions outside the public records; people's names, streets and private messages in the tables
     * about people. One rule for the refresh and catch-up (decisions/0165 §3), and for which columns Publish never carries.
     */
    private static function stripRule(string $t, string $name, string $type, int $len, bool $null): ?string
    {
        $n = strtolower($name); $text = in_array($type, self::TEXT_TYPES, true);
        $public = in_array($t, self::PUBLIC, true);
        $people = in_array($t, self::PEOPLE, true);
        $col = '`' . str_replace('`', '', $name) . '`';
        $cut = fn (string $e) => $len > 0 && $len < 65535 ? 'LEFT(' . $e . ', ' . $len . ')' : $e;
        $blank = $null ? 'NULL' : ($text || in_array($type, ['varbinary', 'binary', 'blob'], true) ? "''" : null);
        $flag = preg_match('/_hash$|_hmac$|_key$|verified|policy|^find_by|_at$|_sent$|^emailed$|_count$|_ok$|_on$|^is_|^has_/', $n) === 1;
        if ($t === 'users' && $n === 'password_hash') return '@tv_nohash';
        if ($t === 'users' && preg_match('/(^|_)sub$/', $n)) return $null ? 'NULL' : null;   // social sign-ins (google_sub…)
        if ($t === 'staff_accounts' && in_array($n, ['password_hash', 'totp_secret', 'setup_hash'], true)) return $null ? 'NULL' : "''";
        if ($text && !$public && !$flag && str_contains($n, 'email'))   // a staff login's own address (StaffLogin::DOMAIN) is a marker, not a person's: kept, so staff are staff on staging (Jason, 2026-11-14)
            return "IF($col IS NULL OR $col = '' OR $col LIKE 'check%@traversence.com' OR $col LIKE '%@staging.invalid' OR $col LIKE '%@staff.traversence.invalid', $col, " . $cut("CONCAT('p', LEFT(SHA2(CONCAT(LOWER($col), @tv_salt), 256), 16), '@staging.invalid')") . ')';
        if ($text && !$public && !$flag && preg_match('/phone|mobile|(^|_)fax($|_)|(^|_)tel($|_)|(^|_)sms($|_)/', $n))
            return "IF($col IS NULL OR $col = '' OR $col LIKE '(505) 555-01__', $col, " . $cut("CONCAT('(505) 555-01', LPAD(CONV(LEFT(SHA2(CONCAT($col, @tv_salt), 256), 4), 16, 10) MOD 100, 2, '0'))") . ')';
        if (!$public && !str_starts_with($t, 'ip_') && $blank !== null && preg_match('/^(ip|ip_addr|ip_address|remote_ip|client_ip|last_ip|ip_key|ip_hash|user_agent|useragent|ua)$|_ip$/', $n)) return $blank;
        if ($people && $text && (in_array($n, self::NAME_COLS, true) || ($n === 'name' && in_array($t, self::NAME_IS_PERSON, true))))
            return "IF($col IS NULL OR $col = '' OR $col LIKE 'Test Person ____', $col, " . $cut("CONCAT('Test Person ', UPPER(LEFT(SHA2(CONCAT($col, @tv_salt), 256), 4)))") . ')';
        if ($people && $text && in_array($n, self::STREET_COLS, true)) return "IF($col IS NULL OR $col = '', $col, " . $cut("'100 Test Street'") . ')';
        if ($text && in_array($n, self::PRIVATE_TEXT[$t] ?? [], true)) return "IF($col IS NULL OR $col = '', $col, " . $cut("'Test message (the original isn''t copied to staging).'") . ')';
        return null;
    }

    /** The columns of a table the strip replaces ($cols = [name => data type]): staging's stand-ins, never carried by Publish. */
    public static function strippedCols(string $t, array $cols): array
    {
        $out = [];
        foreach ($cols as $name => $type) if (self::stripRule($t, (string) $name, strtolower((string) $type), 0, true) !== null) $out[] = (string) $name;
        return $out;
    }

    // ---- for the mirror (decisions/0165: StagingSync, StagingPublish) ------------------------------------------------

    /** Each realm's pair: [live's login for it, live's database, staging's database]. Staging must be set up. */
    public static function pairs(PDO $pdo): array
    {
        $setup = (array) self::get($pdo, 'setup');
        if (empty($setup['dbs'])) throw new RuntimeException('Staging isn\'t set up: System Configuration → Staging → Set up staging.');
        $out = [];
        foreach ($setup['dbs'] as $realm => $db) {
            $l = self::liveDbs()[$realm] ?? null;
            if (!$l) continue;
            $out[(string) $realm] = [self::conn((string) $realm), (string) $l[0], self::stg($setup, (string) $realm)];
        }
        return $out;
    }

    /** Live's record of staging, written (for StagingPublish's runs). */
    public static function putState(PDO $pdo, string $k, ?array $v): void { self::put($pdo, $k, $v); }

    /** [test user ids, test staff ids, staging's one salt]: the strip on the way in (a person keeps the same stand-in for staging's life). */
    public static function stripping(PDO $pdo): array
    {
        [$users, $staff] = self::testIds($pdo);
        $s = self::get($pdo, 'salt');
        if (!$s || empty($s['v'])) { $s = ['v' => bin2hex(random_bytes(16)), 'at' => date('c')]; self::put($pdo, 'salt', $s); }
        return [$users, $staff, (string) $s['v']];
    }

    /** [test user ids, test staff ids] (TestAccounts' record). */
    public static function testAccounts(PDO $pdo): array { return self::testIds($pdo); }

    /** Settings that must not be in staging's .env since decisions/0165 §1 (live keys of held services): taken out of the one there now. Returns the names taken out. */
    public static function cleanEnv(PDO $pdo): array
    {
        $s = self::get($pdo, 'setup');
        if (!$s || empty($s['base'])) return [];
        $L = self::layout((string) $s['base']);
        if (!is_file($L['env'])) return [];
        $lines = file($L['env'], FILE_IGNORE_NEW_LINES) ?: [];
        $keep = []; $gone = [];
        foreach ($lines as $l) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=/', $l, $m) && preg_match(self::ENV_DROP_0165, $m[1])) { $gone[] = $m[1]; continue; }
            $keep[] = $l;
        }
        if ($gone) self::writeFile($L['env'], implode("\n", $keep) . "\n", 0600);
        return $gone;
    }

    /**
     * Keep staging caught up (decisions/0165 §3), shipped off. On: staging starts over once (a fresh copy, so the sides
     * match), then each kept table gets staging's triggers, live's outbox triggers (in the database that owns the table)
     * and its base; from then live's changes come in within a minute and the nightly pass checks every row. Off: live's
     * outbox triggers come off at once (live is as it was) and catch-up stops; staging keeps what it has. Founder only.
     */
    public static function keepCaughtUp(PDO $pdo, bool $on, int $uid, string $tz = ''): string
    {
        [$ok, $why] = self::canManage();
        if (!$ok) throw new RuntimeException($why);
        require_once __DIR__ . '/StagingSync.php';
        $m = (array) self::get($pdo, 'mirror');
        if (!$on) {
            $n = 0; foreach (array_keys(StagingSync::pairs()) as $realm) $n += StagingSync::dropOutbox($realm);
            self::put($pdo, 'mirror', ['on' => false, 'off_at' => date('c'), 'by' => $uid]);
            return 'Staging no longer keeps caught up: live\'s ' . $n . ' outbox triggers are off, so live is exactly as before. Staging keeps what it has.';
        }
        if (!empty($m['on'])) return 'Staging already keeps caught up.';
        self::put($pdo, 'mirror', ['starting' => true, 'asked_at' => date('c'), 'by' => $uid]);
        self::start($pdo, $uid, $tz);
        self::kick($pdo);
        return 'Staging starts over once (a fresh copy of live, a few minutes), then keeps caught up from live. You get an email when it\'s done.';
    }

    /** Why staging can't be previewed or published from now, or '' (Jason, 2026-11-26): a fresh copy going in, or one that
     *  stopped part way (staging stays closed until a copy finishes, so what's there is half a copy). */
    public static function copyHold(PDO $pdo): string
    {
        if (self::running($pdo)) return 'A fresh copy of live is going into staging: preview and publish once it finishes (you get an email).';
        $last = self::get($pdo, 'last');
        if ($last && empty($last['ok'])) return 'The last copy into staging stopped part way, so staging holds half a copy: press Start staging over, then preview and publish.';
        return '';
    }

    /** Is staging kept caught up? */
    public static function mirrorOn(PDO $pdo): bool { $m = self::get($pdo, 'mirror'); return !empty($m['on']); }

    /**
     * Where Keep staging caught up stands, so the page's words follow it (Jason, 2026-11-26: "it is supposed to move/change
     * as actions fire"): ['state' => off | turning_on | on | stuck, 'pct', 'pos', 'of', 'now', 'why']. Turning on: it was
     * pressed and its fresh copy is going in (it switches on at the copy's last step, 'finish'). Stuck: it was pressed but no
     * copy is going in any more (the copy stopped): Try again starts it once more.
     */
    public static function mirrorState(PDO $pdo): array
    {
        $m = (array) self::get($pdo, 'mirror');
        if (!empty($m['on'])) return ['state' => 'on'];
        if (empty($m['starting'])) return ['state' => 'off'];
        $run = self::running($pdo);
        if ($run) { $p = self::progress($run); $of = max(1, (int) ($p['of'] ?? 0)); $pos = (int) ($p['pos'] ?? 0);
            return ['state' => 'turning_on', 'pos' => $pos, 'of' => $of, 'pct' => (int) floor(100 * $pos / $of), 'now' => (string) ($p['now'] ?? '')]; }
        $last = self::get($pdo, 'refresh');
        return ['state' => 'stuck', 'why' => (string) (($last['message'] ?? '') ?: 'the fresh copy stopped before its last step')];
    }

    /** Staging runs its own scheduled jobs (decisions/0165 §1), shipped off: live's cPanel adds staging's copy of each. */
    public static function jobs(PDO $pdo, bool $on, int $uid): string
    {
        [$ok, $why] = self::canManage();
        if (!$ok) throw new RuntimeException($why);
        $s = (array) self::get($pdo, 'setup');
        if (empty($s['base'])) throw new RuntimeException('Staging isn\'t set up yet.');
        require_once __DIR__ . '/Scheduler.php';
        [$added, $gone] = Scheduler::syncStaging($on, self::layout((string) $s['base'])['root'] . '/app/scripts');
        self::put($pdo, 'jobs', ['on' => $on, 'at' => date('c'), 'by' => $uid, 'jobs' => $added]);
        return $on ? 'Staging runs its own jobs now (' . count($added) . ' added in cPanel), on staging\'s data, under the gate.' : 'Staging\'s own jobs are off (' . count($gone) . ' taken out of cPanel).';
    }

    /** Staging's own code root (for the scan of ungated outside calls), or ''. */
    public static function stagingRoot(PDO $pdo): string
    {
        $s = self::get($pdo, 'setup');
        return $s && !empty($s['base']) ? self::layout((string) $s['base'])['root'] : '';
    }

    /** What Start staging over would lose (decisions/0165 §3: offered with Publish first). */
    public static function startOverLosses(PDO $pdo): array
    {
        require_once __DIR__ . '/StagingSync.php';
        require_once __DIR__ . '/Releases.php';
        $out = ['changes' => 0, 'held' => 0, 'releases' => count(Releases::onStaging($pdo))];
        try {
            foreach (StagingSync::pairs() as [$c, , $db]) { try { $out['changes'] += (int) $c->query('SELECT COUNT(*) FROM ' . self::q($db, 'stg_changes'))->fetchColumn(); } catch (Throwable) {} }
            $p = StagingSync::pairs();
            if (isset($p['main'])) { try { $out['held'] += (int) $p['main'][0]->query('SELECT COUNT(*) FROM ' . self::q($p['main'][2], 'staging_held'))->fetchColumn(); } catch (Throwable) {} }
        } catch (Throwable) {}
        return $out;
    }

    /** After the copy: the test staff logins' two-step keys sealed for staging, the listings database's views rebuilt,
     *  staging's switches for paid services off, and staging open again. */
    private static function finish(PDO $pdo, array $setup): array
    {
        $notes = [];
        $notes[] = self::resealStaff($pdo, $setup);
        if (isset($setup['dbs']['listings'])) {   // BusinessData rebuilds its views and triggers on staging when it next opens
            $c = self::conn('listings'); $db = self::stg($setup, 'listings');
            try { $c->exec('DELETE FROM ' . self::q($db, 'biz_schema') . " WHERE k = 'version'"); } catch (Throwable) {}
        }
        try { $c = self::conn('main'); $c->exec('DELETE FROM ' . self::q(self::stg($setup, 'main'), 'feature_switches') . " WHERE switch_key IN ('" . implode("', '", Staging::SWITCHES) . "')"); } catch (Throwable) {}
        return array_values(array_filter($notes));
    }

    /** The test staff logins on staging: their two-step keys sealed with staging's own secret (staging can't open live's). */
    private static function resealStaff(PDO $pdo, array $setup): string
    {
        require_once __DIR__ . '/StaffLogin.php';
        [, $staff] = self::testIds($pdo);
        if (!$staff) return '';
        $secret = (string) (self::readEnvFile(self::layout((string) $setup['base'])['env'])['APP_SECRET'] ?? '');
        if ($secret === '') return 'Staging\'s .env has no APP_SECRET: press Set up staging again so the test staff logins can sign in there.';
        $moved = Database::moves()['staff_accounts'] ?? null;
        $realm = $moved && isset($setup['dbs'][$moved]) ? $moved : 'main';
        $c = self::conn($realm); $db = self::stg($setup, $realm);
        $n = 0;
        foreach ($staff as $sid) {
            $st = $pdo->prepare('SELECT password_hash, totp_secret, status FROM ' . Database::t('staff_accounts') . ' WHERE id = ?'); $st->execute([$sid]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) continue;
            $c->prepare('UPDATE ' . self::q($db, 'staff_accounts') . ' SET password_hash = ?, totp_secret = ?, status = ?, totp_last_step = 0, failed = 0, locked_until = NULL WHERE id = ?')
              ->execute([(string) $row['password_hash'], StaffLogin::resealFor((string) $row['totp_secret'], $secret), (string) $row['status'], $sid]);
            $n++;
        }
        return $n ? 'The ' . $n . ' test staff logins sign in on staging with the same sign-ins and two-step keys.' : '';
    }

    /**
     * After Test accounts gives new sign-ins on live (TestAccounts::makeAll): the same sign-ins on staging at once, so the
     * checks can use one block for both. A test account made since the last refresh comes with the next. Returns a note.
     */
    public static function syncTestAccounts(PDO $pdo): string
    {
        if (!self::isSetUp($pdo) || self::running($pdo)) return '';
        $setup = (array) self::get($pdo, 'setup');
        try {
            [$users] = self::testIds($pdo);
            $c = self::conn('main'); $db = self::stg($setup, 'main'); $live = self::liveDbs()['main'][0];
            $has = $c->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users'"); $has->execute([$db]);
            if (!(int) $has->fetchColumn()) return 'Staging gets the test accounts with its first refresh.';
            if ($users) $c->exec('UPDATE ' . self::q($db, 'users') . ' s JOIN ' . self::q($live, 'users') . ' l ON l.id = s.id SET s.password_hash = l.password_hash, s.email = l.email, s.is_test = l.is_test,
                                  s.is_suspended = l.is_suspended, s.email_verified_at = l.email_verified_at, s.created_at = l.created_at WHERE l.id IN (' . implode(',', array_map('intval', $users)) . ')');
            self::resealStaff($pdo, $setup);
            return 'Staging has the same new sign-ins (test accounts made since its last refresh come with the next).';
        } catch (Throwable $e) { error_log('staging test sync: ' . $e->getMessage()); return 'Staging didn\'t take the new sign-ins yet: press Start staging over.'; }
    }

    // ---- a lock, so two windows never set up or refresh at once -------------------------------------------------------

    private static function lock(PDO $pdo): bool
    {
        try { return (int) $pdo->query("SELECT GET_LOCK('tv_staging', 0)")->fetchColumn() === 1; } catch (Throwable) { return true; }
    }

    private static function unlock(PDO $pdo): void
    {
        try { $pdo->query("SELECT RELEASE_LOCK('tv_staging')"); } catch (Throwable) {}
    }

    // ---- what the Staging section shows ----------------------------------------------------------------------------

    /** [setup, refresh in progress, last refresh, next weekly refresh (unix time or null)]. */
    public static function status(PDO $pdo): array
    {
        self::ensure($pdo);
        $setup = self::get($pdo, 'setup');
        if ($setup && (empty($setup['base']) || !is_file(self::layout((string) $setup['base'])['env']))) $setup['missing'] = true;
        $last = self::get($pdo, 'last');
        $next = null;   // no weekly refresh since decisions/0165: staging catches up from live
        return ['setup' => $setup, 'running' => self::running($pdo), 'last' => $last, 'next' => $next];
    }

    /** Is a weekly refresh due? Never, since decisions/0165 §3: staging catches up instead, and Start staging over is a button. */
    public static function due(PDO $pdo): bool
    {
        return false;
    }
}
