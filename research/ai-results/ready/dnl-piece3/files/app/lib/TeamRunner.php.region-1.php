// ANCHOR: replaces `    private static function log(PDO $pdo, array $role, int $task, string $step, string $seen, string $did, string $state, string $why = '', ?array $proposal = null, string $undoHow = '', ?array $undo = null, string $touches = ''): string` through `    }` (the method's closing brace, the line right after `        return $line;`)
    private static function log(PDO $pdo, array $role, int $task, string $step, string $seen, string $did, string $state, string $why = '', ?array $proposal = null, string $undoHow = '', ?array $undo = null, string $touches = ''): string
    {
        // Undo on every agent action (decisions/0179's "What comes next"; Piece 3, P3-4): a step that did something without its
        // Undo isn't recorded as done; it's refused, so it shows, and a step that can't be undone only ever proposes
        if ($state === 'done' && (trim($undoHow) === '' || $undo === null)) { $state = 'refused'; $why = 'It did "' . mb_substr($did, 0, 120) . '" with no Undo: a step that can\'t be undone only proposes (Piece 3).'; }
        // The spending limit per agent counts what's booked here (Piece 3, P3-4). A step that went ahead (done or proposed) books
        // the cost run() checked against its role's limit (STEP_COST_MICROS), so spentThisMonth() reads it. A refused or failed
        // step books nothing. Every step is $0 today (Q4 B), so the month's totals stay $0.
        $cost = in_array($state, ['done', 'proposed'], true) ? max(0, (int) (self::STEP_COST_MICROS[$step] ?? 0)) : 0;
        $pdo->prepare('INSERT INTO team_runs (at, role, task_id, step, seen, did, state, why, proposal, undo_how, undo_data, touches, cost_micros) VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([(string) $role['slug'], $task, $step, mb_substr($seen, 0, 400), mb_substr($did, 0, 400), $state, mb_substr($why, 0, 400), $proposal !== null ? json_encode($proposal, JSON_UNESCAPED_UNICODE) : null,
                mb_substr($undoHow, 0, 400), $undo !== null ? json_encode($undo) : null, mb_substr($touches, 0, 400), $cost]);
        $rid = (int) $pdo->lastInsertId();
        $line = TeamRoster::actor($role) . ' · task #' . $task . ' · ' . ($state === 'done' || $state === 'proposed' ? $did : $state . ': ' . $why);
        ControlLog::add($pdo, 'control', '', 'Task #' . $task . ' run #' . $rid . ' (' . $step . '): ' . ($state === 'done' || $state === 'proposed' ? $did : $state . ', ' . $why), TeamRoster::actor($role));
        return $line;
    }
