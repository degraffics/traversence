// ANCHOR: replaces     public static function applies(PDO $pdo, string $step, array $t): bool through     }
    public static function applies(PDO $pdo, string $step, array $t): bool
    {
        $tk = (string) ($t['ticket'] ?? 'work'); $sg = (string) ($t['stage'] ?? '');
        if ($step === 'gate') return $tk === 'work' && $sg === 'planning' && !self::openProposal($pdo, (int) $t['id'], 'gate')
            && (GaugeTasks::gate($pdo, $t) === [] || (self::mode($pdo, 'gate') === 'act' && self::takesItself($pdo, $t) && GaugeTasks::gate($pdo, $t) === ['someone to have it (Take it)']));
        if ($step === 'handoff') return $tk === 'work' && $sg === 'building' && trim((string) $t['goal']) !== '' && trim((string) ($t['build_brief'] ?? '')) === '' && (string) $t['rel'] === '';
        if ($step === 'audit') return false;   // the audit is its own daily job, not one ticket's step
        if ($step === 'brief') { if ($tk !== 'resolution' || $sg !== 'brief') return false; return self::briefDraft($pdo, $t) !== []; }
        if ($step === 'content') { if ($tk !== 'work' || $sg !== 'planning') return false; require_once __DIR__ . '/TeamContent.php'; return TeamContent::wants($pdo, $t); }   // the Front-End Content Writers: once a ticket
        return false;
    }
