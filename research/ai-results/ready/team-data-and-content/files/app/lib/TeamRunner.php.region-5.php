// ANCHOR: replaces     public static function run(PDO $pdo, array $t, string $step, array $role): string through     }
    public static function run(PDO $pdo, array $t, string $step, array $role): string
    {
        self::ensure($pdo);
        [, $name, $action, $def] = self::STEPS[$step];
        $who = TeamRoster::actor($role); $id = (int) $t['id'];
        [$ok, $no] = TeamRoster::can($role, $action, $def);
        if (!$ok) return self::log($pdo, $role, $id, $step, 'its permissions', '', 'refused', $no . ' (Permissions: ' . $action . ')');
        $cost = (int) (self::STEP_COST_MICROS[$step] ?? 0);   // the spending limit per agent (Piece 3, P3-4)
        if ($cost > 0 && self::spentThisMonth($pdo, (string) $role['slug']) + $cost > self::spendLimit($pdo, (string) $role['slug']))
            return self::log($pdo, $role, $id, $step, 'its spending limit', '', 'refused', 'It would spend $' . number_format($cost / 1e6, 2) . ', over this month\'s limit for ' . $role['name'] . ' ($' . number_format(self::spendLimit($pdo, (string) $role['slug']) / 1e6, 2) . ').');
        try {
            if ($step === 'gate' && self::mode($pdo, 'gate') === 'propose') {   // at Planning: a proposal a person accepts (Jason, 2026-11-25)
                return self::log($pdo, $role, $id, $step, 'its goal, Done when, decisions and owner', 'Proposed: ready for Building (its gate passes)', 'proposed', '', ['move' => 'building'], 'A proposal changes nothing until a person accepts it', null, 'task:' . $id);
            }
            if ($step === 'gate') {
                $took = trim((string) $t['owner']) === '' && self::takesItself($pdo, $t);   // an audit-raised ticket: it takes it first
                if ($took) $pdo->prepare('UPDATE control_gauge_tasks SET owner = ?, owner_at = UTC_TIMESTAMP() WHERE id = ? AND owner = \'\'')->execute([mb_substr($who, 0, 80), $id]);
                try { $msg = GaugeTasks::move($pdo, $id, 'building', $who, 'its gate passed: a goal, Done when, its decisions and someone who has it'); }
                catch (RuntimeException $x) { if ($took) $pdo->prepare('UPDATE control_gauge_tasks SET owner = \'\', owner_at = NULL WHERE id = ?')->execute([$id]); throw $x; }
                return self::log($pdo, $role, $id, $step, 'its goal, Done when, decisions and owner', ($took ? 'Took it (the audit raised it); ' . lcfirst($msg) : $msg), 'done', '', null,
                    $took ? 'Moves it back to Planning and gives it back to nobody' : 'Moves it back to Planning', $took ? ['to' => 'planning', 'owner' => ''] : ['to' => 'planning'], 'task:' . $id);
            }
            if ($step === 'handoff') {
                $brief = self::buildBrief($pdo, $t);
                $was = (string) $t['owner'];
                $pdo->prepare('UPDATE control_gauge_tasks SET build_brief = ?, owner = ?, owner_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$brief, mb_substr($who, 0, 80), $id]);
                $to = self::briefFor($pdo, ['build_brief' => $brief] + $t);   // Implementation on: straight to a build session (Jason, 2026-11-25), behind the fence (P3-6)
                $said = $to === 'build' ? 'straight to a build session' : ($to === 'founder' ? 'front end: waiting for you (it reaches a public page: ' . mb_substr(implode(', ', self::frontOf($pdo, ['build_brief' => $brief] + $t)), 0, 160) . ')' : 'with the session manager');
                ControlLog::add($pdo, 'control', '', 'Task #' . $id . ' build brief written; ' . $said . ' (hourly report, build-requests.json)', $who);
                return self::log($pdo, $role, $id, $step, 'its scope, decisions and steps' . ($to !== 'session manager' ? ', and how far it reaches (the fence)' : ''), 'Wrote its build brief; ' . $said . ' at the next hourly report', 'done', '', null,
                    'Takes the brief back and gives the ticket back to ' . ($was !== '' ? $was : 'nobody'), ['owner' => $was], 'task:' . $id);
            }
            if ($step === 'brief') {
                $d = self::briefDraft($pdo, $t);
                return self::log($pdo, $role, $id, $step, 'its words, its decisions and the boundary', 'Proposed ' . count($d) . ' part' . (count($d) === 1 ? '' : 's') . ' of its brief: ' . implode(', ', array_map(fn ($k) => strtolower(GaugeTasks::BRIEF[$k][0]), array_keys($d))), 'proposed', '', $d,
                    'A proposal changes nothing until a person accepts it', null, 'task:' . $id);
            }
            if ($step === 'content') {   // the Front-End Content Writers (TeamContent): scope from templates, $0; a proposal only, never a person's words
                require_once __DIR__ . '/TeamContent.php';
                $c = TeamContent::draft($pdo, $t);
                if ($c['fields'] === []) return self::log($pdo, $role, $id, $step, $c['seen'], '', 'failed', $c['why']);
                return self::log($pdo, $role, $id, $step, $c['seen'], $c['did'], 'proposed', '', $c['proposal'], 'A proposal changes nothing until a person accepts it', null, 'task:' . $id);
            }
        } catch (RuntimeException $x) { return self::log($pdo, $role, $id, $step, $name, '', 'failed', $x->getMessage()); }
        return '';
    }
