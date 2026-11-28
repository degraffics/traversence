Review first, then the files.

**This item is gated.** `handoffs/decided-not-live.md` says the no-undo release is "built and tested, **not sent** … needs a Design look and Jason's pop-up." That is waiting for Jason, so these files are a draft to compare against, not something to send. The tested build is in `handoffs/no-undo/` (branch `claude/no-undo`), and that file is not shown. Where it differs from this draft, the tested build wins.

**Other skipped items** (none of them touch this item): M2b, "Paused by Jason 2026-11-24"; Order #12's books, "after holiday"; "Gaps for Jason's return".

**Corrections to job 19's draft:**
1. **Format.** `ControlAlarms.php` is far over 60 KB, and the attached copy is truncated. The draft's unified diff with estimated line numbers doesn't meet the required format. It is replaced below by one anchored region, and `regions: true` is set.
2. **Wrong skip.** The draft lists the Start card's "Mark issue resolved" as "for Jason to decide." The later 0171 note (osm-copy-wider) already decided it. It is dropped from the skip list.
3. **FAQ accuracy.**
   - The draft says an acknowledged alarm "still counts in the top bar's light." `counts()` shows that is true only for trips. An acknowledged warning or notice leaves the light's count and the board's Open filter. With no Undo, a mistaken acknowledgement can't bring it back. That is a behaviour change visitors must be told about, and it is now in the FAQ and the risks.
   - The draft's third FAQ entry, about Resolve's Undo staying as today, is neither new nor changed. It is dropped, and the open question is kept in the risks.
4. **The library-only change isn't ship-ready alone.**
   - Pulse's "Undo" on alarm items (0171 §8) and any board or email Undo are in files that are not shown.
   - The test's caller scan is a release blocker. It is expected to FAIL until those callers are removed.
5. **Test hygiene.**
   - The repo root can now be set with `TV_ROOT`, so the test runs from the release folder.
   - The cleanup `DELETE` gets a data_reason.
   - The test checks that `ack()` writes once and keeps the first person who acknowledged.
   - The `control_log` row it leaves behind is named in the risks.

=== FILE: files/app/lib/ControlAlarms.php.region-1.php ===
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
=== END FILE ===

=== FILE: tests/control-no-undo-ack.php ===
<?php
// tools/control-tests/no-undo-ack.php — No Undo on an alarm's acknowledgement (CLAUDE.md #1314). DRAFT: not run.
// Plain script: prints PASS/FAIL per check, exits 1 on any FAIL.
// Root: TV_ROOT, else two folders up (when placed in tools/control-tests/).
// The database part runs only on staging, or with TV_TESTS_DB=1 against a test database. It writes one control_alarms
// row (proc 'test:no-undo') and deletes it. ack() also writes one control_log line with proc 'test:no-undo', which is
// left in place (ControlLog's schema isn't shown).
// No money moves: nothing here touches QuickBooks or payments.
declare(strict_types=1);

$root = rtrim((string) (getenv('TV_ROOT') ?: dirname(__DIR__, 2)), '/');
require_once $root . '/app/lib/Database.php';
require_once $root . '/app/lib/ControlAlarms.php';

$fails = 0;
$check = function (string $name, bool $ok, string $why = '') use (&$fails): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . ($ok || $why === '' ? '' : ' (' . $why . ')') . "\n";
    if (!$ok) $fails++;
};

// 1. Source: unack() writes nothing and always says false.
$rm = new ReflectionMethod('ControlAlarms', 'unack');
$lines = file((string) $rm->getFileName());
$src = implode('', array_slice($lines, $rm->getStartLine() - 1, $rm->getEndLine() - $rm->getStartLine() + 1));
$check('unack() has no UPDATE', stripos($src, 'UPDATE') === false);
$check('unack() has no prepare/exec', stripos($src, 'prepare') === false && stripos($src, 'exec') === false);
$check('unack() returns false', (bool) preg_match('/return\s+false;/', $src));

// 2. Release blocker: anything that still offers an Undo through unack() (Pulse's alarm items, the board's actions,
//    the email's link page). Expected to FAIL until those files are changed; the lane removes each one listed.
$left = [];
foreach (['app', 'website'] as $dir) {
    if (!is_dir($root . '/' . $dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!preg_match('/\.(php|js)$/', $f->getFilename())) continue;
        $p = $f->getPathname();
        if (str_ends_with($p, 'app/lib/ControlAlarms.php')) continue;
        if (preg_match('/\bunack\b/i', (string) file_get_contents($p))) $left[] = substr($p, strlen($root) + 1);
    }
}
$check('no caller of unack() left (release blocker)', !$left, implode(', ', $left));

// 3. Database: ack holds, unack changes nothing, a second ack doesn't overwrite the first.
$staging = false;
try { require_once $root . '/app/lib/Staging.php'; $staging = Staging::here(); } catch (Throwable $x) {}
if (!$staging && getenv('TV_TESTS_DB') !== '1') {
    echo "SKIP: database checks (not staging; set TV_TESTS_DB=1 on a test database)\n";
} else {
    $pdo = Database::connection();
    ControlAlarms::ensure($pdo);
    $key = 'test:no-undo:' . bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO control_alarms (akey, proc, level, kind, title, first_at, last_at) VALUES (?, 'test:no-undo', 'warning', 'check', 'No-undo test alarm', UTC_TIMESTAMP(), UTC_TIMESTAMP())")->execute([$key]);
    $id = (int) $pdo->lastInsertId();
    try {
        $check('unack() on an unseen alarm returns false', ControlAlarms::unack($pdo, $id) === false);
        $check('ack() the first time returns true', ControlAlarms::ack($pdo, $id, 0, 'Test runner') === true);
        $a = ControlAlarms::byId($pdo, $id);
        $at = (string) ($a['ack_at'] ?? '');
        $check('ack_at is set', $at !== '');
        $check('unack() on a seen alarm returns false', ControlAlarms::unack($pdo, $id) === false);
        $b = ControlAlarms::byId($pdo, $id);
        $check('ack_at unchanged after unack()', (string) ($b['ack_at'] ?? '') === $at);
        $check('ack_name unchanged after unack()', (string) ($b['ack_name'] ?? '') === 'Test runner');
        $check('ack() again returns false (already seen)', ControlAlarms::ack($pdo, $id, 0, 'Someone else') === false);
        $c = ControlAlarms::byId($pdo, $id);
        $check('first acknowledger kept', (string) ($c['ack_name'] ?? '') === 'Test runner');
        $check('still open after ack (stays until its reading is good)', ($c['cleared_at'] ?? null) === null);
    } finally {
        // data_reason: removes only the one row this test inserted (its own id, proc 'test:no-undo'); nothing else is touched
        $pdo->prepare("DELETE FROM control_alarms WHERE id = ? AND proc = 'test:no-undo'")->execute([$id]);
    }
}

echo $fails ? "FAILED: $fails\n" : "ALL PASS\n";
exit($fails ? 1 : 0);
=== END FILE ===

=== FILE: faq/faq-entries.md ===
<!-- DRAFT for faq.md (file not shown: its sections and format to be matched by the lane). Each entry is "coming" until the no-undo release is live. -->

**Can I undo acknowledging an alarm?** *(coming)*
No. Once you acknowledge an alarm on the Control Board, in Pulse, or from the alarm email's link to the board, it stays acknowledged everywhere. It's one record, so it can't be "unseen." Acknowledging only says you've seen it and stops the repeat email. It doesn't fix or close anything. The alarm stays open until its check passes.

**I acknowledged an alarm by mistake. What happens?** *(coming)*
Nothing is lost, and the alarm stays open until it's fixed.
- **If it's a trip,** it keeps the light red and still counts in the top bar. It still holds what it paused. It closes when its check passes (Recovered) or when someone presses **Check and start again**.
- **If it's a warning or a notice,** it leaves the top bar's count and the board's Open list once acknowledged, but it isn't cleared. If the system can't fix it, it becomes a trip, and a trip is emailed and shown in Pulse whether or not it was acknowledged before.
- **If the same problem comes back** after the alarm has closed, it opens as a new alarm.
=== END FILE ===

=== FILE: release.json ===
{
  "name": "no-undo-ack (DRAFT, job 19 reviewed; compare-only against handoffs/no-undo/)",
  "gate": "HELD: needs a Design look and Jason's pop-up before it is sent (handoffs/decided-not-live.md, Building now: 'built and tested, not sent … needs a Design look and Jason's pop-up'). The tested build in handoffs/no-undo/ (branch claude/no-undo) is preferred; this draft is for comparison. Also blocked until the UI callers of unack() are removed (test check 2).",
  "what": "No Undo on an alarm's acknowledgement (CLAUDE.md #1314; decided-not-live Control board item 2). ControlAlarms::unack() becomes a no-op that always returns false, so an acknowledgement can't be cleared. ack()'s docblock says it is final. No schema change, no migration.",
  "files": [
    "files/app/lib/ControlAlarms.php.region-1.php",
    "tests/control-no-undo-ack.php",
    "faq/faq-entries.md"
  ],
  "decisions": [
    "CLAUDE.md #1314 (not attached; scope taken from decided-not-live.md: 'No Undo on any alarm's acknowledgement')",
    "decisions/0171 §7: acknowledging in any of the three places acknowledges all; one record; it stops the email repeat; the alarm stays open until green",
    "decisions/0171 §8: 'Done in Pulse means acknowledged. Undo reopens it.' This is superseded by #1314 for alarms; the session manager adds the note (this draft doesn't edit the decision)",
    "decisions/0171 §7 3c: Undo of a Resolve for a day (unresolve()) is left unchanged; whether #1314 covers it is Jason's call",
    "Skipped, paused or waiting for Jason (untouched): this release's own send (Design look + Jason's pop-up); M2b Orders (Paused by Jason 2026-11-24); Order #12's books ('after holiday'); Gaps for Jason's return. Corrected from job 19: the Start card's 'Mark issue resolved' was already decided (0171 note osm-copy-wider), so it isn't a skip"
  ],
  "tests": [
    "tests/control-no-undo-ack.php → tools/control-tests/no-undo-ack.php (folder name assumed; tools tree not shown). Check 2 (no unack callers left) is a release blocker and is expected to FAIL until the Pulse, board and email-link Undo is removed. The database checks run only on staging or with TV_TESTS_DB=1"
  ],
  "risks": [
    "NOTHING here was run, linted, tested or zip-checked; it is a draft for the lane session",
    "The tested build in handoffs/no-undo/ exists and isn't attached. If it differs from this draft, the tested build wins; diff them first",
    "The library change alone is not ship-ready: Pulse's Undo on alarm:/alarms:<group> items (Pulse::mark), any board Undo (admin-control.js, the action endpoint) and the email link page aren't shown or patched. If Pulse's Undo only changes Pulse's own item state, Pulse and the alarm would disagree (the item reopens, the alarm stays acknowledged)",
    "Behaviour change: an acknowledged warning or notice leaves counts() and the board's Open filter, and with no Undo it can't be brought back by a person. It still becomes a trip if the system can't fix it, and a trip counts and is emailed regardless of acknowledgement (counts(), mail())",
    "unack() is kept as a no-op returning false, so an unchanged caller shows its 'nothing to undo' path rather than a fatal. Confirm each caller's false-path words are true",
    "unresolve() doesn't clear ack_*, so a reopened trip keeps its acknowledgement. That is consistent with #1314, but confirm the wording on the reopened card",
    "The test leaves one control_log line (proc 'test:no-undo') because ControlLog's schema isn't shown",
    "The region was taken from a truncated copy of ControlAlarms.php (the live mirror); the anchor must match the current live file byte for byte"
  ],
  "verify": [
    "file not shown: handoffs/no-undo/ and branch claude/no-undo (diff this draft against them)",
    "file not shown: CLAUDE.md #1314 (confirm the scope, including whether 'any alarm's acknowledgement' covers unresolve())",
    "file not shown: app/lib/Pulse.php, pulse-ui.php (Done/Undo on alarm items), admin-control.js, the board's action endpoint, the email link page: remove each Undo of an acknowledgement and run test check 2",
    "file not shown: faq.md (place the entries in its existing section and format)",
    "file not shown: tools/ tree (confirm the tools/control-tests/ folder) and app/lib/ControlLog.php",
    "The anchor lines match the live ControlAlarms.php exactly: first line '    /** Acknowledged by a person: stops the repeat email; it stays open until its reading is good. */', through unack()'s closing '    }'",
    "PHP 8.0 lint on ControlAlarms.php and the test (php -l)",
    "On staging: acknowledge a warning and a trip, and confirm no Undo is offered anywhere (board row, group row's Acknowledge all, Pulse, email link); a trip stays in the light and a warning leaves it",
    "The Design look and Jason's pop-up, then the zip check, sync and byte-check before the line moves to Live",
    "Session manager: add a #1314 note to 0171 §8 ('Undo reopens it')"
  ],
  "regions": true
}
=== END FILE ===