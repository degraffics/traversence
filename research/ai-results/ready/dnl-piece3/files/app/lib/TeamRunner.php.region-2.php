// ANCHOR: replaces `    public static function undo(PDO $pdo, int $rid, string $who, string $tz = ''): string` through `    }` (the method's closing brace, the line right after `        return 'Undone: ' . lcfirst((string) $r['undo_how']) . '.';`)
    public static function undo(PDO $pdo, int $rid, string $who, string $tz = ''): string
    {
        $r = self::get($pdo, $rid); if (!$r) throw new RuntimeException('That run isn\'t there.');
        if ($r['undone_at'] !== null) throw new RuntimeException('Already undone, by ' . $r['undone_by'] . '.');
        if ($r['state'] !== 'done') throw new RuntimeException($r['state'] === 'proposed' ? 'A proposal changes nothing: Accept or Dismiss it.' : 'It did nothing, so there\'s nothing to undo.');
        $u = json_decode((string) $r['undo_data'], true) ?: []; $tid = (int) $r['task_id'];
        if (!in_array($r['step'], ['gate', 'handoff', 'audit'], true)) throw new RuntimeException('This kind of run has no Undo.');
        // Claimed first (Piece 3, P3-4: "a second Undo is refused"). Before this, the run was marked undone only after its Undo
        // acted, so two presses at once could both pass the check above and both act: a second move back, a second withdraw.
        // Now only the press that claims it acts. If its Undo fails, the claim is given back, so it can be pressed again.
        $by = mb_substr($who, 0, 80);
        $claim = $pdo->prepare('UPDATE team_runs SET undone_at = UTC_TIMESTAMP(), undone_by = ? WHERE id = ? AND undone_at IS NULL');
        $claim->execute([$by, $rid]);
        if ($claim->rowCount() !== 1) { $now = self::get($pdo, $rid); throw new RuntimeException('Already undone, by ' . (string) ($now['undone_by'] ?? 'someone else') . '.'); }
        try {
            if ($r['step'] === 'gate') { GaugeTasks::move($pdo, $tid, (string) $u['to'], $who, 'Undo of run #' . $rid, $tz); if (array_key_exists('owner', $u)) $pdo->prepare('UPDATE control_gauge_tasks SET owner = ?, owner_at = NULL WHERE id = ?')->execute([(string) $u['owner'], $tid]); }
            elseif ($r['step'] === 'handoff') {
                $pdo->prepare('UPDATE control_gauge_tasks SET build_brief = NULL, owner = ?, owner_at = ? WHERE id = ?')->execute([(string) ($u['owner'] ?? ''), ($u['owner'] ?? '') !== '' ? gmdate('Y-m-d H:i:s') : null, $tid]);
                ControlLog::add($pdo, 'control', '', 'Task #' . $tid . ' build brief taken back (Undo of run #' . $rid . ')', $who, '', $tz);
            } else GaugeTasks::withdraw($pdo, (int) $u['withdraw'], 'Undo of run #' . $rid, $who, $tz);
        } catch (Throwable $x) {
            $pdo->prepare('UPDATE team_runs SET undone_at = NULL, undone_by = \'\' WHERE id = ? AND undone_by = ?')->execute([$rid, $by]);
            throw $x;
        }
        return 'Undone: ' . lcfirst((string) $r['undo_how']) . '.';
    }
