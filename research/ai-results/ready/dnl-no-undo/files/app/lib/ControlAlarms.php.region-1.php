<?php
// ANCHOR: replaces     /** Acknowledged by a person: stops the repeat email; it stays open until its reading is good. */ through     }
// (that last "    }" is the closing brace of unack(): the line just before "    /**" that opens list()'s docblock
//  "The list: open alarms, worst first, …"). The region is ack() and unack(), as in the attached live file.

    /**
     * Acknowledged by a person: stops the repeat email; it stays open until its reading is good. It's final: there is no
     * Undo on an acknowledgement (CLAUDE.md #1314). An open trip still counts in the light whether or not it's
     * acknowledged (counts()); a warning or notice leaves the count once acknowledged.
     */
    public static function ack(PDO $pdo, int $id, int $uid, string $who): bool
    {
        self::ensure($pdo);
        $st = $pdo->prepare('UPDATE control_alarms SET ack_uid = ?, ack_name = ?, ack_at = UTC_TIMESTAMP() WHERE id = ? AND ack_at IS NULL');
        $st->execute([$uid ?: null, mb_substr($who, 0, 80) ?: null, $id]);
        $ok = $st->rowCount() > 0;
        if ($ok && ($a = self::byId($pdo, $id))) self::log($pdo, 'ack', (string) $a['proc'], 'Acknowledged: ' . (string) $a['title'], $who);
        return $ok;
    }

    /**
     * No Undo on an alarm's acknowledgement (CLAUDE.md #1314). Kept only so a caller not yet changed gets false, never a
     * fatal: it reads nothing, writes nothing and logs nothing. Always false.
     */
    public static function unack(PDO $pdo, int $id): bool
    {
        return false;
    }
