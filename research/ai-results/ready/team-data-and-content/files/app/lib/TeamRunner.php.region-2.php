// ANCHOR: replaces     public const STEP_AT = [ through     ];
    public const STEP_AT = [
        'gate' => ['planning' => 'propose', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
        'handoff' => ['planning' => 'off', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
        'brief' => ['planning' => 'propose', 'building' => 'propose', 'implementation' => 'propose', 'confirmation' => 'propose'],
        'audit' => ['planning' => 'act', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
        'content' => ['planning' => 'propose', 'building' => 'propose', 'implementation' => 'propose', 'confirmation' => 'propose'],   // words a person may write: always a proposal (0164; 0180 §7)
    ];
