<?php
// app/lib/Jurisdictions.php — the jurisdiction register (decisions/0133): the law of the place, layered federal, then the
// state or territory, then the tribal nation whose land it is. Each record lists its laws and their effect on what we
// may show, and who reviewed it: the team reviews each record against its laws, and an attorney's review is advised, not
// required (Jason, 2026-11-27, 0164 R8's note). Where a layer is missing or not reviewed, the strictest known rule still
// applies: a protected place's story is held back entirely until it is reviewed. A nation's rule outranks ours on
// its land, and a nation that hasn't published its rules is treated as restricting what it hasn't made public.
// Read by the heritage catalog (tiers) and the source register; the protected tiers (decisions/0133 item 11) build on it.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Jurisdictions
{
    /** What a law does to what we show. */
    public const EFFECTS = [
        'site_location' => 'Archaeological and sacred site locations kept confidential',
        'burials' => 'Graves, burials and human remains never located',
        'cultural_items' => 'Sacred objects and cultural items not shown',
        'privacy' => 'Personal information protected',
        'children' => 'Children\'s information never collected',
        'scraping' => 'Limits on reading or copying websites',
        'other' => 'Other',
    ];

    /** Every state, D.C. and the territories: a record is opened for each as the plan reaches it. */
    public const STATES = ['AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut',
        'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana',
        'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan',
        'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
        'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia',
        'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        'PR' => 'Puerto Rico', 'GU' => 'Guam', 'VI' => 'U.S. Virgin Islands', 'MP' => 'Northern Mariana Islands', 'AS' => 'American Samoa'];
    public const TERRITORIES = ['PR', 'GU', 'VI', 'MP', 'AS'];
    /**
     * Regions open before the register (decisions/0048: Arizona and New Mexico first): kept open while their records are
     * drafts, so they never go dark; once a record is reviewed, the record rules (0164 R8's note, 2026-11-27). Retired by a
     * later release once US, US-AZ and US-NM are reviewed on live.
     */
    public const OPENED = ['AZ', 'NM'];

    public static function ready(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try { $pdo->query('SELECT 1 FROM jurisdictions LIMIT 0'); $pdo->query('SELECT 1 FROM jurisdiction_laws LIMIT 0'); return $ok = true; } catch (Throwable $e) { return $ok = false; }
    }

    /** One record with its laws, or null when there's none yet. */
    public static function get(PDO $pdo, string $code): ?array
    {
        if (!self::ready($pdo)) return null;
        $st = $pdo->prepare('SELECT * FROM jurisdictions WHERE code = ?'); $st->execute([$code]);
        $j = $st->fetch(PDO::FETCH_ASSOC);
        if (!$j) return null;
        $st = $pdo->prepare('SELECT * FROM jurisdiction_laws WHERE code = ? ORDER BY id'); $st->execute([$code]);
        $j['laws'] = $st->fetchAll(PDO::FETCH_ASSOC);
        return $j;
    }

    /**
     * The layers that apply to a place: the federal baseline, its state or territory, and the nation whose land it is.
     * Returns ['layers' => [[code, name, record or null]], 'reviewed' => every layer has a reviewed record,
     * 'effects' => [effect => [law names]], 'missing' => [names of layers with no reviewed record]].
     */
    public static function forPlace(PDO $pdo, string $state, ?int $nationId = null, string $nationName = ''): array
    {
        $layers = [['US', 'United States']];
        $state = strtoupper(trim($state));
        if ($state !== '' && isset(self::STATES[$state])) $layers[] = ['US-' . $state, self::STATES[$state]];
        if ($nationId) $layers[] = ['nation:' . $nationId, $nationName !== '' ? $nationName : 'Tribal nation #' . $nationId];
        $out = ['layers' => [], 'reviewed' => true, 'effects' => [], 'missing' => []];
        foreach ($layers as [$code, $name]) {
            $j = self::get($pdo, $code);
            $out['layers'][] = [$code, $name, $j];
            if (!$j || $j['status'] !== 'reviewed') { $out['reviewed'] = false; $out['missing'][] = $name; }
            foreach ((array) ($j['laws'] ?? []) as $l) if ((string) $l['effect'] !== '') $out['effects'][$l['effect']][] = (string) $l['name'];
        }
        if ($state === '') { $out['reviewed'] = false; $out['missing'][] = 'the state (not known)'; }
        return $out;
    }

    /**
     * The tier a piece of heritage gets here (decisions/0133 protected-information standard): 'public', 'story' (the story
     * may be told, never the way in: no point, no directions) or 'restricted' (kept, never shown). $sensitive: it is about
     * an archaeological, sacred, burial or ceremonial place. On a nation's land a sensitive item stays restricted until the
     * nation says; elsewhere it is 'story', also while its state's record is still under the team's review (Jason,
     * 2026-11-27, ruling Q8 "Show"; 0164 R8/P5-3 note): the strictest known rule applies, so never the way in.
     */
    public static function tier(PDO $pdo, bool $sensitive, string $state, ?int $nationId = null): string
    {
        if (!$sensitive) return 'public';
        if ($nationId) return 'restricted';
        return 'story';
    }

    /**
     * Is the region open (0164 R8, its 2026-11-27 note: a region opens on its team-reviewed jurisdiction record)? A state or
     * territory opens when its record and the federal baseline are both reviewed; the ones open before the register (OPENED)
     * stay open while theirs are drafts. A place with no state yet is in a region we opened (our hubs and regions are only made there).
     * Closed: guides there don't go live (by themselves or by a person), and the crawler doesn't gather history there.
     */
    public static function regionOpen(PDO $pdo, string $state): bool
    {
        $state = strtoupper(trim($state));
        if ($state === '' || in_array($state, self::OPENED, true)) return true;
        if (!isset(self::STATES[$state])) return false;
        $us = self::get($pdo, 'US'); $st = self::get($pdo, 'US-' . $state);
        return $us && $st && $us['status'] === 'reviewed' && $st['status'] === 'reviewed';
    }

    /** Why a region is closed, in words (empty when it's open). */
    public static function closedWhy(PDO $pdo, string $state): string
    {
        if (self::regionOpen($pdo, $state)) return '';
        $name = self::STATES[strtoupper(trim($state))] ?? $state;
        return $name . ' isn\'t open yet: its jurisdiction record is reviewed by the team first (System Configuration → Jurisdictions).';
    }

    /** Does the law of the place limit reading or copying websites there (a 'scraping' law, federal or the state's)? */
    public static function limitsReading(PDO $pdo, string $state): bool
    {
        return isset(self::forPlace($pdo, $state)['effects']['scraping']);
    }

    /** Every record for the admin: federal, the states and territories (with or without one), and the nations we hold. */
    public static function register(PDO $pdo): array
    {
        $have = [];
        if (self::ready($pdo)) foreach ($pdo->query('SELECT j.*, (SELECT COUNT(*) FROM jurisdiction_laws l WHERE l.code = j.code) AS laws FROM jurisdictions j')->fetchAll(PDO::FETCH_ASSOC) as $r) $have[$r['code']] = $r;
        $rows = [['US', 'federal', 'United States (federal baseline)', $have['US'] ?? null]];
        foreach (self::STATES as $st => $name) $rows[] = ['US-' . $st, in_array($st, self::TERRITORIES, true) ? 'territory' : 'state', $name, $have['US-' . $st] ?? null];
        try {
            require_once __DIR__ . '/Nations.php';
            foreach (Nations::all($pdo) as $n) $rows[] = ['nation:' . (int) $n['id'], 'nation', (string) $n['name'], $have['nation:' . (int) $n['id']] ?? null];
        } catch (Throwable $e) {}
        return $rows;
    }

    /** Opens a record (a state as the plan reaches it, a nation that has published its rules). */
    public static function open(PDO $pdo, string $code, string $level, string $name): void
    {
        if (!preg_match('/^(US|US-[A-Z]{2}|nation:\d{1,9}|[A-Z]{2}(-[A-Z0-9]{1,3})?)$/', $code)) throw new InvalidArgumentException('That isn\'t a jurisdiction code.');
        $pdo->prepare('INSERT IGNORE INTO jurisdictions (code, level, name, status) VALUES (?, ?, ?, \'draft\')')->execute([$code, $level, mb_substr($name, 0, 120)]);
    }

    public static function addLaw(PDO $pdo, string $code, array $in, int $uid): void
    {
        if (!self::get($pdo, $code)) throw new InvalidArgumentException('Open the record first.');
        $name = trim((string) ($in['name'] ?? '')); $effect = (string) ($in['effect'] ?? '');
        if ($name === '') throw new InvalidArgumentException('What is the law called?');
        if (!isset(self::EFFECTS[$effect])) throw new InvalidArgumentException('Choose what it does to what we show.');
        $url = trim((string) ($in['url'] ?? ''));
        if ($url !== '' && !preg_match('#^https?://#i', $url)) throw new InvalidArgumentException('The link should be a web address.');
        $c = fn ($k, $n) => mb_substr(trim((string) ($in[$k] ?? '')), 0, $n);
        $pdo->prepare('INSERT INTO jurisdiction_laws (code, name, citation, protects, requires_us, effect, url, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$code, mb_substr($name, 0, 190), $c('citation', 190), $c('protects', 500), $c('requires_us', 500), $effect, mb_substr($url, 0, 500), $uid]);
        self::reopen($pdo, $code);   // a changed record is reviewed again
    }

    public static function removeLaw(PDO $pdo, int $id): void
    {
        $st = $pdo->prepare('SELECT code FROM jurisdiction_laws WHERE id = ?'); $st->execute([$id]);
        $code = $st->fetchColumn();
        if ($code === false) return;
        $pdo->prepare('DELETE FROM jurisdiction_laws WHERE id = ?')->execute([$id]);
        self::reopen($pdo, (string) $code);
    }

    /**
     * The team marks the record reviewed against its laws (0164 R8's note, 2026-11-27: "An attorney's review is advised, not
     * required to open a region"). $as: a team role's name when a role did the review (decisions/0180 §5, e.g. "Privacy &
     * Decision 0066 Guards (25)"), else the person ($uid). $attorney and $date: an attorney's review, when there was one.
     */
    public static function review(PDO $pdo, string $code, int $uid, string $attorney = '', string $date = '', string $as = ''): void
    {
        $j = self::get($pdo, $code) ?? throw new InvalidArgumentException('Open the record first.');
        if (!$j['laws'] && $j['level'] !== 'nation') throw new InvalidArgumentException('Add its laws first.');
        $attorney = trim($attorney); $as = mb_substr(trim($as), 0, 160);
        if ($uid <= 0 && $as === '') throw new InvalidArgumentException('Who reviewed it? A person signed in, or a team role.');
        $d = $date !== '' ? date('Y-m-d', strtotime($date) ?: time()) : date('Y-m-d');
        $pdo->prepare("UPDATE jurisdictions SET status = 'reviewed', reviewed_by = ?, reviewed_as = ?, reviewed_at = NOW(), signed_by = IF(? <> '', ?, signed_by), signed_at = IF(? <> '', ?, signed_at) WHERE code = ?")
            ->execute([$uid > 0 ? $uid : null, $as, $attorney, mb_substr($attorney, 0, 160), $attorney, $d, $code]);
        require_once __DIR__ . '/ControlLog.php';
        ControlLog::add($pdo, 'control', 'jur:' . $code, (string) $j['name'] . '\'s jurisdiction record marked reviewed' . ($attorney !== '' ? ' (attorney: ' . $attorney . ')' : ''), $as !== '' ? $as : self::personName($pdo, $uid));
    }

    /** Undo a review: the record goes back to draft as if it was never reviewed (the strictest rule applies again). */
    public static function undoReview(PDO $pdo, string $code, int $uid, string $who = ''): void
    {
        $j = self::get($pdo, $code) ?? throw new InvalidArgumentException('That record isn\'t open.');
        if ($j['status'] !== 'reviewed') throw new InvalidArgumentException('It isn\'t reviewed, so there\'s nothing to undo.');
        $pdo->prepare("UPDATE jurisdictions SET status = 'draft', reviewed_by = NULL, reviewed_as = '', reviewed_at = NULL WHERE code = ?")->execute([$code]);
        require_once __DIR__ . '/ControlLog.php';
        ControlLog::add($pdo, 'control', 'jur:' . $code, (string) $j['name'] . '\'s review undone: back to draft, the strictest rule applies', $who !== '' ? $who : self::personName($pdo, $uid), 'was reviewed by ' . self::reviewer($pdo, $j));
    }

    /** Who reviewed a record, in words: the team role, else the person; '' when it isn't reviewed. */
    public static function reviewer(PDO $pdo, array $j): string
    {
        if (($j['status'] ?? '') !== 'reviewed') return '';
        if (trim((string) ($j['reviewed_as'] ?? '')) !== '') return (string) $j['reviewed_as'];
        return self::personName($pdo, (int) ($j['reviewed_by'] ?? 0)) ?: 'the team';
    }

    private static function personName(PDO $pdo, int $uid): string
    {
        if ($uid <= 0) return '';
        try { $st = $pdo->prepare("SELECT COALESCE(NULLIF(display_name, ''), email) FROM users WHERE id = ?"); $st->execute([$uid]); $n = (string) $st->fetchColumn(); if ($n !== '') return $n; } catch (Throwable) {}
        return 'Staff #' . $uid;
    }

    private static function reopen(PDO $pdo, string $code): void
    {
        $pdo->prepare("UPDATE jurisdictions SET status = 'draft', reviewed_as = '' WHERE code = ?")->execute([$code]);
    }
}
