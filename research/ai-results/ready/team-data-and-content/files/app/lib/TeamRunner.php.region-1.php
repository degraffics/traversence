// ANCHOR: replaces     public const STEPS = [ through     ];
    public const STEPS = [
        'gate' => ['project-manager', 'Moves a Work ticket from Planning to Building once its gate passes', 'team.gate', ['min' => 'manager', 'depts' => ['ops']]],
        'handoff' => ['system-architect', 'Writes a Building ticket\'s build brief and hands it to the session manager', 'team.handoff', ['min' => 'staff', 'depts' => ['eng']]],
        'brief' => ['translator', 'Proposes a Resolution brief\'s plain words from its ticket and its decisions', 'team.brief', ['min' => 'staff', 'depts' => ['ops']]],
        'audit' => ['ops-reconciliation', 'Runs the daily health and drift audit: a finding is looked at twice before it opens a ticket', 'team.audit', ['min' => 'manager', 'depts' => ['ops']]],
        // the Front-End Content Writers (decisions/0180 appendix row 26; app/lib/TeamContent.php): a proposal only, never a person's words
        'content' => ['content-writers', 'Proposes an editorial Work ticket\'s goal, scope and Done when from its kind (FAQ, words, guide, listing); a person accepts', 'team.content', ['min' => 'staff', 'depts' => []]],
    ];
