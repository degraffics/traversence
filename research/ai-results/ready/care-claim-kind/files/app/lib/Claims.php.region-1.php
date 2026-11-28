// ANCHOR: replaces     // ------------------------------------------------------------------ who might claim it (Jason, 2026-11-19; decisions/0070 note)
// through     }
// (that is, the closing brace of kind(): the line straight after         return $memo[$e] = $k;)
// Live Claims.php is over 60 KB, so only this region and region-2 are given. agency(), domains() and everything after are unchanged.
// What changes here:
//   - care in KIND_GROUPS, KIND_ROLES and KIND_WORDS;
//   - the kind() docblock.
// kind()'s body is unchanged: the map lookup covers care, and only public goes on to the nation check.
    // ------------------------------------------------------------------ who might claim it (Jason, 2026-11-19; decisions/0070 note)

    /** The category groups (decisions/0073) whose listings are claimed by an agency, an organization or the people who run it, not an owner (care: decisions/0070 note, 2026-11-27/28). */
    public const KIND_GROUPS = ['Government & Public Services' => 'public', 'Non-Profit & Community' => 'community', 'Religious & Faith-Based' => 'community', 'Health & Medical' => 'care'];
    /** The roles offered on the claim form, by kind. */
    public const KIND_ROLES = [
        'business'  => ['Owner', 'Co-owner or partner', 'Manager', 'Employee', 'Other'],
        'public'    => ['Site or park manager', 'Agency staff', 'Communications or public information', 'Other'],
        'community' => ['Director, pastor or leader', 'Staff', 'Board member', 'Volunteer', 'Other'],
        'nation'    => ['Nation staff', 'Tribal enterprise staff', 'Other'],
        'care'      => ['Administrator or practice manager', 'Staff', 'Communications', 'Other'],   // decisions/0070 note, 2026-11-27/28
    ];
    /** The words, by kind: the short button, its tip (%s: the name), the claim page's title, and "yours" in its sentences. */
    public const KIND_WORDS = [
        'business'  => ['button' => 'Claim it', 'tip' => 'Own or manage %s? Claim it to add photos, hours and more.', 'title' => 'Claim your business', 'yours' => 'your business'],
        'public'    => ['button' => 'Staff? Claim it', 'tip' => 'Work for the agency that runs %s? Claim it to keep its hours, photos and notices current.', 'title' => 'Claim this place for your agency', 'yours' => 'your agency'],
        'community' => ['button' => 'Run it? Claim it', 'tip' => 'Run %s? Claim it to add photos, hours and more.', 'title' => 'Claim your organization', 'yours' => 'your organization'],
        'nation'    => ['button' => 'Nation staff? Claim it', 'tip' => 'Work for the nation that runs %s? Ask to claim it: our team checks with the nation first.', 'title' => 'A place on a nation\'s land', 'yours' => 'the nation'],
        // tip and title: decisions/0070 note, 2026-11-27/28. button and yours are drafts awaiting Design review (not in the note).
        'care'      => ['button' => 'Work here? Claim it', 'tip' => 'Work at %s? Claim it to keep its hours and services current.', 'title' => 'Claim this health care listing', 'yours' => 'your organization'],
    ];

    /** business | public | community | care | nation (a public place on a nation's land: the nation's own tools decide, 0054, 0066; care: Health & Medical, decisions/0070 note 2026-11-27/28). */
    public static function kind(PDO $pdo, int $e): string
    {
        static $memo = [];
        if (isset($memo[$e])) return $memo[$e];
        $k = 'business';
        try {
            $st = $pdo->prepare('SELECT COALESCE(g.name, c.name) AS grp, e.latitude, e.longitude FROM entities e LEFT JOIN categories c ON c.id = e.category_id LEFT JOIN categories g ON g.id = c.parent_id WHERE e.id = ?');
            $st->execute([$e]); $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $k = self::KIND_GROUPS[(string) ($r['grp'] ?? '')] ?? 'business';
            if ($k === 'public' && isset($r['latitude'], $r['longitude']) && is_file(__DIR__ . '/Nations.php')) {
                require_once __DIR__ . '/Nations.php';
                if (Nations::at($pdo, (float) $r['latitude'], (float) $r['longitude']) !== null) $k = 'nation';
            }
        } catch (Throwable $x) { error_log('Claims::kind: ' . $x->getMessage()); }
        return $memo[$e] = $k;
    }
