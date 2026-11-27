# posts.php: a database error never reaches the caller (gate 1; the session manager, 2026-11-27)

One file, `website/user/api/posts.php` (decisions/0153's Moments and Keep), built on Sync 2026-11-27 16:23 UTC. Its own
release, after C.8 (the session manager's order).

**Why:** `PDOException` extends `RuntimeException`, so the endpoint's `catch (RuntimeException)` answered a database error
with its own message: the SQLSTATE, a column name, sometimes the query, shown to the visitor (seen on the bench at
posts.php:81; live checked clean, so a latent leak, not a live harm today).

**The fix:** `catch (PDOException)` before it: logged in full for us (`error_log`), and the caller gets "Something went
wrong on our side. Try again in a minute; if it keeps happening, tell us at support@traversence.com." (CLAUDE.md "Never
a dead end"; the session manager's "every alarm carries its fix"). The codes it passes on purpose (401, 428, 429), other
RuntimeExceptions, refusals (422) and good reads are unchanged.

**Test:** `php tools/posts-tests/pdo-error.php <posts.php>`: 8/8 on the fix; the live copy fails the 3 it fixes. No
database (stand-ins for bootstrap, Auth and MemberPosts).
