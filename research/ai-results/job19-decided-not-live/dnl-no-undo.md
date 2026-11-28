# No Undo on an alarm's acknowledgement: draft patches

**Paused or waiting for Jason (skipped or gated):**
- **This item is gated.** `handoffs/decided-not-live.md` says the no-undo release "needs a Design look and Jason's pop-up." This draft can be prepared, but it must not be sent until both happen.
- **The tested build isn't attached.** `handoffs/no-undo/` and branch `claude/no-undo` are file not shown. This draft is written fresh against the live `ControlAlarms.php`. If that build exists and passed its tests, prefer it. Use this draft only to compare against it.
- **Other skipped items:** these are paused or Jason's call, so this draft doesn't touch them:
  - M2b (Orders), "Paused by Jason 2026-11-24".
  - The Start the system card's "Mark issue resolved" (0171 §7 note, "for Jason to decide").
  - Order #12's books ("after holiday").
  - "Gaps for Jason's return."
- **CLAUDE.md #1314 isn't attached.** Its exact wording isn't quoted. The scope below comes from the queue line "No Undo on any alarm's acknowledgement" (`decided-not-live.md`, Control board item 2).
- **Open Work-queue parents** are in the live database and not attached.

---

## 1. Plan

1. **`ControlAlarms::unack()` becomes a no-op that returns `false`.** An acknowledgement can no longer be cleared at the library level (CLAUDE.md #1314; decided-not-live Control board item 2). The method stays, so callers not yet changed get `false` instead of a fatal.
2. **`ControlAlarms::ack()`'s docblock says it is final.** This keeps the code's words true to the rule (CLAUDE.md #1314).
3. **No change to `unresolve()` (Undo of a Resolve).** It is a separate control decided in 0171 §7 ("Undo for a day"), and it doesn't clear `ack_*`. If #1314 also covers it, that is Jason's call, not this draft's.
4. **The UI that offers Undo after an acknowledgement isn't patched here (file not shown).** That covers Pulse's Undo on `alarm:` items (`Pulse::mark()`, 0171 §8) and any board button (`admin-control.js`, the action handler). The test below lists any `unack(` callers left so the lane can remove them.
5. **No migration.** The schema is unchanged.

## 2. Diffs

`ControlBoard.php` and `0171` need no change for this draft. The 0171 §8 wording "Undo reopens it" is noted in §5 for the session manager.

The hunk line numbers below are estimated, because the attached copy is truncated. The context lines are exact, and `git apply` / `patch -p1` search nearby lines for an offset.

```diff
--- a/app/lib/ControlAlarms.php
+++ b/app/lib/ControlAlarms.php
@@ -598,7 +598,8 @@
         return $n;
     }
 
-    /** Acknowledged by a person: stops the repeat email; it stays open until its reading is good. */
+    /** Acknowledged by a person: stops the repeat email; it stays open until its reading is good. It can't be undone
+     *  (CLAUDE.md #1314): once a person has seen it, it stays seen. */
     public static function ack(PDO $pdo, int $id, int $uid, string $who): bool
     {
         self::ensure($pdo);
@@ -606,12 +607,13 @@
         return $ok;
     }
 
-    /** Undo an acknowledgement (it emails again only if it's a new problem). */
+    /**
+     * No Undo on an acknowledgement (CLAUDE.md #1314). Kept only so a caller not yet changed gets false, never a fatal; it
+     * changes nothing and writes nothing. Returns false always.
+     */
     public static function unack(PDO $pdo, int $id): bool
     {
-        self::ensure($pdo);
-        $st = $pdo->prepare('UPDATE control_alarms SET ack_uid = NULL, ack_name = NULL, ack_at = NULL WHERE id = ? AND ack_at IS NOT NULL'); $st->execute([$id]);
-        return $st->rowCount() > 0;
+        return false;
     }
 
     /**
```

## 3. Tests

**`tools/control-tests/no-undo-ack.php`** (new)

```php
<?php
// tools/control-tests/no-undo-ack.php — No Undo on an alarm's acknowledgement (CLAUDE.md #1314).
// Plain script: prints PASS/FAIL per check, exits 1 on any FAIL. The database part runs only on staging, or with
// TV_TESTS_DB=1 against a test database; it writes one control_alarms row (proc 'test:no-undo') and deletes it.
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/lib/Database.php';
require_once $root . '/app/lib/ControlAlarms.php';

$fails = 0;
$check = function (string $name, bool $ok, string $why = '') use (&$fails): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . ($ok || $why === '' ? '' : ' (' . $why . ')') . "\n";
    if (!$ok) $fails++;
};

// 1. Source: unack() writes nothing.
$rm = new ReflectionMethod('ControlAlarms', 'unack');
$src = implode('', array_slice(file($rm->getFileName()), $rm->getStartLine() - 1, $rm->getEndLine() - $rm->getStartLine() + 1));
$check('unack() has no UPDATE', stripos($src, 'UPDATE') === false);
$check('unack() returns false', (bool) preg_match('/return\s+false;/', $src));

// 2. Callers left: anything that still offers an Undo through unack() (the lane removes them; Pulse, the board's actions).
$left = [];
foreach (['app', 'website'] as $dir) {
    if (!is_dir($root . '/' . $dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!preg_match('/\.(php|js)$/', $f->getFilename())) continue;
        $p = $f->getPathname();
        if (str_ends_with($p, 'app/lib/ControlAlarms.php')) continue;
        $t = (string) file_get_contents($p);
        if (preg_match('/\bunack\b/', $t)) $left[] = substr($p, strlen($root) + 1);
    }
}
$check('no caller of unack() left', !$left, implode(', ', $left));

// 3. Database: ack holds, unack changes nothing.
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
    } finally {
        $pdo->prepare('DELETE FROM control_alarms WHERE id = ?')->execute([$id]);
    }
}

echo $fails ? "FAILED: $fails\n" : "ALL PASS\n";
exit($fails ? 1 : 0);
```

Notes on the test:
- The `tools/control-tests/` folder name is assumed, because the tools tree isn't shown. Move the script to the existing area folder if it differs.
- `ack()` writes one `control_log` line with proc `test:no-undo`. `ControlLog`'s schema isn't shown, so the test doesn't delete that line.

## 4. faq.md entries

`faq.md` isn't attached, so its sections and format are file not shown. Below are the entries to add, written from a staff visitor's view and marked "coming" until the release is live.

> **Can I undo acknowledging an alarm?** *(coming)*
> No. Once you acknowledge an alarm on the Control Board, in Pulse or from the alarm email, it stays acknowledged everywhere. It's one record, so it can't be "unseen." Acknowledging only says you've seen it. A trip stays open and keeps the light red until its check passes or someone presses **Check and start again**.

> **I acknowledged an alarm by mistake. What happens?** *(coming)*
> Nothing is lost. Acknowledging stops the repeat email, but the alarm stays on the board until it's fixed. If it's a trip, it still waits for its check to pass or for **Check and start again**, and it still counts in the top bar's light. If the same problem comes back after the alarm closes, it opens as a new alarm and reaches you again.

> **Is the Undo after "Check and start again" going away too?** *(as today)*
> No. Undoing a Resolve within a day still reopens the trip and stops again what it had stopped. Only the acknowledgement has no Undo.

The third entry assumes #1314 doesn't cover Resolve's Undo. The lane should drop or reword it if #1314 says otherwise.

## 5. Risks and what the lane session must verify

**Before anything is sent:**
- **Gate.** Get the Design look and Jason's pop-up (`decided-not-live.md`) first.
- **Prefer the tested build.** Diff this draft against `handoffs/no-undo/` and branch `claude/no-undo`. Where they differ, the tested build wins.
- **Read CLAUDE.md #1314 itself.** Confirm its scope, especially whether "any alarm's acknowledgement" includes the Undo of a Resolve (`unresolve()`). This draft leaves that Undo alone.

**The UI (not shown here):**
- **Pulse.** In `Pulse.php` and `pulse-ui.php`, Done/Undo on `alarm:` and `alarms:<group>` items (`Pulse::mark()`) must no longer show Undo for an alarm.
- **Mismatch risk.** If Pulse's Undo only changes Pulse's own item state, items and alarms will disagree after this patch: Pulse would reopen the item while the alarm stays acknowledged. Check that path.
- **Board and email link.** Remove any Undo button from the board (`admin-control.js`, the board's action endpoint) and from the email's link page.
- **Run the caller check.** Test check 2 lists every remaining `unack` caller.

**Group rows:** `ackGroup()` (the row's "Acknowledge all") is also final now. Check that the group row shows no Undo.

**Decision text:** 0171 §8 still reads "**Done** in Pulse means acknowledged. **Undo** reopens it." The session manager should add a note citing #1314. This draft doesn't edit the decision.

**Behaviour to confirm on staging:**
- `counts()`: an acknowledged trip still counts (it does), and acknowledged warnings and notices drop off the light.
- No email repeats after an acknowledgement.
- PHP 8.0 lint passes on both files (no 8.1+ syntax is used).
- The zip check passes.

**Migration:** none, so nothing to run twice on Bluehost.

**Not run:** none of the above has been executed. The lane must lint, test on staging, and zip-check.