The two fixes are below, highest risk first. Both change the same live file, so **f11 is built on top of f02** and must ship after it. Nothing here has been run, linted or tested. The live path `sync-storage/api/auth/email/revert.php` is my guess from the attachment's file name and its `require_once` depth. The lane must confirm it.

=== FILE: f02/files/sync-storage/api/auth/email/revert.php ===
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../app/bootstrap.php';

// Deliberately does NOT require an authenticated session — same token-
// as-proof-of-identity pattern as referral confirmation. This link is
// meant to work even if the account is compromised and the real owner
// is logged out everywhere already.
Response::requireMethod('POST');

$body = Response::jsonBody();
$token = (string) ($body['token'] ?? '');
$confirmEmail = strtolower(trim((string) ($body['confirm_email'] ?? '')));
if ($token === '' || $confirmEmail === '') {
    Response::error('A confirmation token and the original email address are both required.', 422, 'validation_error');
}

$pdo = Database::connection();

$stmt = $pdo->prepare(
    "SELECT id, user_id, metadata, expires_at, confirmed_at FROM email_confirmations
     WHERE token = :token AND action_type = 'email_revert' LIMIT 1"
);
$stmt->execute(['token' => $token]);
$row = $stmt->fetch();

if ($row === false) {
    Response::error('Invalid or unknown revert token.', 404, 'not_found');
}
if ($row['confirmed_at'] !== null) {
    Response::error('This revert link has already been used.', 409, 'already_used');
}
if (strtotime($row['expires_at']) < time()) {
    Response::error('This revert link has expired.', 410, 'expired');
}

$metadata = json_decode((string) $row['metadata'], true);
$revertToEmail = $metadata['revert_to_email'] ?? null;
if ($revertToEmail === null) {
    error_log('email_revert confirmation missing revert_to_email in metadata, id=' . $row['id']);
    Response::error('This revert link is malformed.', 500, 'malformed_token');
}

// Second factor beyond mere possession of the emailed link: the caller
// must ALSO correctly state which address should be restored. Possession
// of the link alone was previously sufficient to execute this — a real,
// consequential action (email rollback + force-logout-everywhere +
// mandatory password reset) — with nothing forcing a deliberate,
// verified action beyond a single click. Deliberately does NOT reveal
// the correct address in the error message on mismatch, so this can't
// be trivially defeated by trial-and-error against someone who merely
// has the link but doesn't actually know the account's original email.
if (!hash_equals($revertToEmail, $confirmEmail)) {
    Response::error('The email address you entered does not match our records for this account.', 403, 'email_mismatch');
}

$userId = (int) $row['user_id'];

// A revert click is a strong signal the ACCOUNT is compromised, not just
// the email field — the attacker still has the password. Reverting the
// email alone would let them just repeat the change. Three things
// happen together, per Section 20.1's design, in ONE transaction:
//   1. Roll back users.email to the pre-change address.
//   2. Invalidate every active session (bumpSessionVersion) — this
//      includes the session that's currently making this very request,
//      if any, since it wasn't required to be authenticated to get here.
//   3. Force a password reset before the account can be used again.
//      password_hash is replaced with a VALID hash of a random secret
//      that is never stored or shown anywhere. Any password_verify()
//      against it fails, so the old (attacker-known) password stops
//      working immediately. It stays a well-formed hash, so code that
//      calls password_get_info()/password_needs_rehash() is unaffected.
//      This is not destructive for a legitimate owner: the email is now
//      back at the address they control, so the existing forgot-password
//      flow lets them set a new password. is_suspended is NOT set
//      (that's a separate admin action).
// The random secret is hex (no NUL bytes, 64 chars < bcrypt's 72-byte
// limit). It is hashed BEFORE the transaction so bcrypt's cost isn't
// paid while row locks are held.
$lockedPasswordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

Database::transaction(function (PDO $pdo) use ($row, $userId, $revertToEmail, $lockedPasswordHash) {
    $pdo->prepare('UPDATE email_confirmations SET confirmed_at = NOW() WHERE id = :id')
        ->execute(['id' => $row['id']]);

    $pdo->prepare(
        'UPDATE users SET email = :email, password_hash = :password_hash, updated_at = NOW() WHERE id = :id'
    )->execute([
        'email' => $revertToEmail,
        'password_hash' => $lockedPasswordHash,
        'id' => $userId,
    ]);

    Auth::bumpSessionVersion($pdo, $userId);
});

Response::success([
    'message' => 'Your email has been reverted, and all active sessions have been signed out for your security. '
        . 'Your old password no longer works: please use "Forgot password" with your restored email address '
        . 'to set a new password before signing in again.',
]);
=== END FILE ===

=== FILE: f02/tests/email-revert-forced-reset.php ===
<?php
// tools/auth-tests style: plain PHP, prints PASS/FAIL, exits 1 on any FAIL.
// Usage: php tests/email-revert-forced-reset.php [path/to/revert.php]
// Default target is this release's copy; pass the live path after deploy.

declare(strict_types=1);

$target = $argv[1] ?? __DIR__ . '/../files/sync-storage/api/auth/email/revert.php';
$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS' : 'FAIL') . " - $name\n";
    if (!$ok) {
        $fails++;
    }
}

check('target file exists', is_file($target));
$src = (string) @file_get_contents($target);

// 1. Lint.
$out = [];
$rc = 1;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($target) . ' 2>&1', $out, $rc);
check('php -l passes', $rc === 0);

// 2. The users UPDATE sets password_hash together with email.
check(
    'users UPDATE sets email and password_hash in one statement',
    (bool) preg_match('/UPDATE users SET email = :email, password_hash = :password_hash/', $src)
);

// 3. That UPDATE is inside the Database::transaction closure, before bumpSessionVersion.
$txPos = strpos($src, 'Database::transaction(');
$updPos = strpos($src, 'password_hash = :password_hash');
$bumpPos = strpos($src, 'Auth::bumpSessionVersion(');
check('password lock is inside the transaction', $txPos !== false && $updPos !== false && $updPos > $txPos);
check('session bump still present after the lock', $bumpPos !== false && $bumpPos > $updPos);

// 4. The hash is computed outside (before) the transaction.
$hashPos = strpos($src, '$lockedPasswordHash = password_hash(');
check('locked hash computed before the transaction', $hashPos !== false && $hashPos < $txPos);

// 5. Behaviour of the exact locking expression used in the endpoint.
$h1 = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
$h2 = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
check('locked hash is a recognised algorithm', password_get_info($h1)['algo'] !== null && password_get_info($h1)['algo'] !== 0);
check('locked hashes differ per call', $h1 !== $h2);
$guesses = ['', 'password', '0', 'null', $h1, str_repeat('a', 64)];
$anyMatch = false;
foreach ($guesses as $g) {
    if (password_verify($g, $h1)) {
        $anyMatch = true;
    }
}
check('no guessable input verifies against the locked hash', !$anyMatch);
check('random secret is 64 hex chars (no NUL, under bcrypt 72-byte limit)', strlen(bin2hex(random_bytes(32))) === 64);

// 6. The visitor is told how to recover.
check('response tells user to use Forgot password', str_contains($src, 'Forgot password'));

echo $fails === 0 ? "ALL PASS\n" : "$fails FAIL(S)\n";
exit($fails === 0 ? 0 : 1);
=== END FILE ===

=== FILE: f02/faq/faq-entries.md ===
### I clicked "This wasn't me" on an email-change notice. Why can't I sign in with my password?

When you tell us an email change wasn't made by you, we treat your whole account as possibly compromised. Whoever changed the email may also know your password, so we do three things at once:

1. We put your original email address back on the account.
2. We sign out every device that was logged in.
3. We switch off your old password so it can't be used by anyone.

To get back in, go to the sign-in page and choose **Forgot password**. Enter your original email address and follow the link we send to set a new password. Choose a password you haven't used before.
=== END FILE ===

=== FILE: f02/release.json ===
{
  "name": "f02-email-revert-forced-password-reset",
  "gate": "high - ship first; f11 is built on top of this file",
  "what": "revert.php promised a mandatory password reset but never enforced it, so an attacker who knew the password could sign straight back in after the revert. Inside the existing transaction, users.password_hash is now replaced with a valid bcrypt/default hash of a 64-hex-char random secret that is never stored or shown. The old password stops working at the same moment as the email rollback and session bump. The owner recovers via the existing forgot-password flow to the restored address. The response message tells them so.",
  "files": [
    "files/sync-storage/api/auth/email/revert.php",
    "tests/email-revert-forced-reset.php",
    "faq/faq-entries.md"
  ],
  "decisions": [
    "NEEDS HUMAN DECISION: how to force the reset. Default implemented: option A, replace password_hash with an unusable-but-well-formed hash (no schema change, only this file). Option B: add a users.must_reset_password flag (migration) and enforce it in login and the session/auth middleware. Those files are not shown, so B was not built. Choose A or B.",
    "The unusable hash is a real password_hash() of random hex rather than a marker string such as '!locked'. Any code calling password_get_info()/password_needs_rehash()/password_verify() keeps working and simply fails to match.",
    "The hash is computed before the transaction so bcrypt cost is not paid while row locks are held.",
    "is_suspended is still not set (it remains a separate admin action), matching the original comment."
  ],
  "data_reason": "UPDATE users SET password_hash: the account owner has stated via the revert link that the email change was not theirs. The current password is presumed known to an attacker and must stop working at once. The change affects only the single row users.id = email_confirmations.user_id of the consumed token.",
  "tests": [
    "php f02/tests/email-revert-forced-reset.php",
    "php f02/tests/email-revert-forced-reset.php <live>/sync-storage/api/auth/email/revert.php (after deploy)"
  ],
  "risks": [
    "NOTHING HERE HAS BEEN RUN, LINTED OR TESTED; it is a draft for the lane session to verify.",
    "If the forgot-password flow refuses accounts whose hash fails some check, or requires the current password, the owner could be locked out. The forgot-password code is not shown.",
    "Password-reset tokens requested by the attacker while the email was theirs may still be valid. They could then set a new password and undo this lock. Where those tokens are stored is not shown.",
    "Remember-me cookies or API tokens not covered by Auth::bumpSessionVersion would survive. Not shown.",
    "Two concurrent valid revert requests can both pass the confirmed_at check (pre-existing TOCTOU, not in scope). Both would lock the password, which is harmless here."
  ],
  "verify": [
    "Confirm the live path is sync-storage/api/auth/email/revert.php. It was derived from the attachment name and the require_once depth.",
    "Confirm the users column is named password_hash, as the original comment implies. Schema file not shown.",
    "Human decision on option A (implemented) vs option B (must-reset flag), see decisions.",
    "Login code is not shown: confirm it uses password_verify() and has no special case that would accept or crash on this hash.",
    "Forgot-password code is not shown: confirm it works for an account whose password_hash was replaced, and sends to users.email (the restored address).",
    "Password-reset token storage is not shown: decide whether outstanding reset tokens for this user should be invalidated in the same transaction. That is recommended as a follow-up.",
    "Auth::bumpSessionVersion and Database::transaction are not shown: confirm the signatures used here are unchanged.",
    "Manual check on staging: change an email, click revert with the correct original address, try the old password (must fail), then complete forgot-password and sign in with the new password."
  ],
  "regions": false
}
=== END FILE ===

=== FILE: f11/files/sync-storage/api/auth/email/revert.php ===
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../app/bootstrap.php';

// Deliberately does NOT require an authenticated session — same token-
// as-proof-of-identity pattern as referral confirmation. This link is
// meant to work even if the account is compromised and the real owner
// is logged out everywhere already.
Response::requireMethod('POST');

$body = Response::jsonBody();
$token = (string) ($body['token'] ?? '');
$confirmEmail = strtolower(trim((string) ($body['confirm_email'] ?? '')));
if ($token === '' || $confirmEmail === '') {
    Response::error('A confirmation token and the original email address are both required.', 422, 'validation_error');
}

$pdo = Database::connection();

$stmt = $pdo->prepare(
    "SELECT id, user_id, metadata, expires_at, confirmed_at FROM email_confirmations
     WHERE token = :token AND action_type = 'email_revert' LIMIT 1"
);
$stmt->execute(['token' => $token]);
$row = $stmt->fetch();

if ($row === false) {
    Response::error('Invalid or unknown revert token.', 404, 'not_found');
}
if ($row['confirmed_at'] !== null) {
    Response::error('This revert link has already been used.', 409, 'already_used');
}
if (strtotime($row['expires_at']) < time()) {
    Response::error('This revert link has expired.', 410, 'expired');
}

$metadata = json_decode((string) $row['metadata'], true);
$revertToEmail = $metadata['revert_to_email'] ?? null;
if ($revertToEmail === null) {
    error_log('email_revert confirmation missing revert_to_email in metadata, id=' . $row['id']);
    Response::error('This revert link is malformed.', 500, 'malformed_token');
}

// Second factor beyond mere possession of the emailed link: the caller
// must ALSO correctly state which address should be restored. Possession
// of the link alone was previously sufficient to execute this — a real,
// consequential action (email rollback + force-logout-everywhere +
// mandatory password reset) — with nothing forcing a deliberate,
// verified action beyond a single click. Deliberately does NOT reveal
// the correct address in the error message on mismatch, so this can't
// be trivially defeated by trial-and-error against someone who merely
// has the link but doesn't actually know the account's original email.
if (!hash_equals($revertToEmail, $confirmEmail)) {
    Response::error('The email address you entered does not match our records for this account.', 403, 'email_mismatch');
}

$userId = (int) $row['user_id'];

// A revert click is a strong signal the ACCOUNT is compromised, not just
// the email field — the attacker still has the password. Reverting the
// email alone would let them just repeat the change. Three things
// happen together, per Section 20.1's design, in ONE transaction:
//   1. Roll back users.email to the pre-change address.
//   2. Invalidate every active session (bumpSessionVersion) — this
//      includes the session that's currently making this very request,
//      if any, since it wasn't required to be authenticated to get here.
//   3. Force a password reset before the account can be used again.
//      password_hash is replaced with a VALID hash of a random secret
//      that is never stored or shown anywhere. Any password_verify()
//      against it fails, so the old (attacker-known) password stops
//      working immediately. It stays a well-formed hash, so code that
//      calls password_get_info()/password_needs_rehash() is unaffected.
//      This is not destructive for a legitimate owner: the email is now
//      back at the address they control, so the existing forgot-password
//      flow lets them set a new password. is_suspended is NOT set
//      (that's a separate admin action).
// The random secret is hex (no NUL bytes, 64 chars < bcrypt's 72-byte
// limit). It is hashed BEFORE the transaction so bcrypt's cost isn't
// paid while row locks are held.
$lockedPasswordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

// Collision guard (same rule as confirm-change: the address must not
// belong to any OTHER user). Between the original change and this revert,
// another account may have registered or switched to the old address.
// Without this check, a missing unique index would silently create two
// users with the same email. The check runs inside the transaction,
// with FOR UPDATE, before anything is written. On conflict NOTHING is
// changed: the token stays unused, sessions and password are untouched,
// and the caller gets 409. A unique-index violation (SQLSTATE 23000)
// from a race is mapped to the same 409.
$emailConflict = false;
try {
    Database::transaction(function (PDO $pdo) use ($row, $userId, $revertToEmail, $lockedPasswordHash, &$emailConflict) {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1 FOR UPDATE');
        $check->execute(['email' => $revertToEmail, 'id' => $userId]);
        if ($check->fetch() !== false) {
            $emailConflict = true;
            return;
        }

        $pdo->prepare('UPDATE email_confirmations SET confirmed_at = NOW() WHERE id = :id')
            ->execute(['id' => $row['id']]);

        $pdo->prepare(
            'UPDATE users SET email = :email, password_hash = :password_hash, updated_at = NOW() WHERE id = :id'
        )->execute([
            'email' => $revertToEmail,
            'password_hash' => $lockedPasswordHash,
            'id' => $userId,
        ]);

        Auth::bumpSessionVersion($pdo, $userId);
    });
} catch (PDOException $e) {
    if ($e->getCode() !== '23000') {
        throw $e;
    }
    $emailConflict = true;
}

if ($emailConflict) {
    error_log('email_revert blocked: revert_to_email already used by another user, confirmation id=' . $row['id']
        . ', user_id=' . $userId);
    Response::error(
        'We could not restore that email address because it is now attached to another account. '
        . 'Nothing was changed. Please contact support so we can help secure your account.',
        409,
        'email_in_use'
    );
}

Response::success([
    'message' => 'Your email has been reverted, and all active sessions have been signed out for your security. '
        . 'Your old password no longer works: please use "Forgot password" with your restored email address '
        . 'to set a new password before signing in again.',
]);
=== END FILE ===

=== FILE: f11/tests/email-revert-collision.php ===
<?php
// tools/auth-tests style: plain PHP, prints PASS/FAIL, exits 1 on any FAIL.
// Usage: php tests/email-revert-collision.php [path/to/revert.php]
// Default target is this release's copy; pass the live path after deploy.

declare(strict_types=1);

$target = $argv[1] ?? __DIR__ . '/../files/sync-storage/api/auth/email/revert.php';
$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS' : 'FAIL') . " - $name\n";
    if (!$ok) {
        $fails++;
    }
}

check('target file exists', is_file($target));
$src = (string) @file_get_contents($target);

// 1. Lint.
$out = [];
$rc = 1;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($target) . ' 2>&1', $out, $rc);
check('php -l passes', $rc === 0);

// 2. Structure: check exists, inside the transaction, before any write.
$sql = 'SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1 FOR UPDATE';
$txPos = strpos($src, 'Database::transaction(');
$chkPos = strpos($src, $sql);
$tokPos = strpos($src, 'UPDATE email_confirmations SET confirmed_at');
$usrPos = strpos($src, 'UPDATE users SET email');
$bumpPos = strpos($src, 'Auth::bumpSessionVersion(');
check('collision SELECT (id != self) present', $chkPos !== false);
check('collision check is inside the transaction', $chkPos !== false && $txPos !== false && $chkPos > $txPos);
check('collision check runs before token is consumed', $chkPos !== false && $tokPos !== false && $chkPos < $tokPos);
check('collision check runs before users UPDATE', $chkPos !== false && $usrPos !== false && $chkPos < $usrPos);
check('collision check runs before session bump', $chkPos !== false && $bumpPos !== false && $chkPos < $bumpPos);
check('returns 409 email_in_use', (bool) preg_match("/409,\s*'email_in_use'/", $src));
check('unique-index race (23000) mapped to conflict', str_contains($src, "getCode() !== '23000'"));
check('other PDO errors are rethrown', str_contains($src, 'throw $e;'));
check('f02 password lock still present', str_contains($src, 'password_hash = :password_hash'));

// 3. Behaviour of the collision query (FOR UPDATE stripped; SQLite has no row locks).
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP - pdo_sqlite not available, query behaviour not exercised\n";
} else {
    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT)');
    $db->exec("INSERT INTO users (id, email) VALUES (1, 'owner@example.test'), (2, 'other@example.test'), (3, 'attacker@example.test')");
    $q = $db->prepare(str_replace(' FOR UPDATE', '', $sql));

    // Self already holds the address (e.g. repeated revert) -> not a conflict.
    $q->execute(['email' => 'owner@example.test', 'id' => 1]);
    check('own address is not a conflict', $q->fetch() === false);

    // Free address -> not a conflict.
    $q->execute(['email' => 'free@example.test', 'id' => 3]);
    check('free address is not a conflict', $q->fetch() === false);

    // Address now owned by another user -> conflict.
    $q->execute(['email' => 'other@example.test', 'id' => 3]);
    check('address held by another user IS a conflict', $q->fetch() !== false);
}

echo $fails === 0 ? "ALL PASS\n" : "$fails FAIL(S)\n";
exit($fails === 0 ? 0 : 1);
=== END FILE ===

=== FILE: f11/faq/faq-entries.md ===
### I tried to undo an email change, but it says the address is "attached to another account". What now?

Sometimes, between the email change and your click on the "This wasn't me" link, another account starts using your original email address. We can't put the address back on your account while that is the case, because two accounts can't share one email.

When this happens, **nothing on your account is changed**. Your revert link is not used up. Please contact support straight away and tell them you received this message. They will check who owns each account and help you secure yours.
=== END FILE ===

=== FILE: f11/release.json ===
{
  "name": "f11-email-revert-collision-check",
  "gate": "med - ship AFTER f02 (this file already contains the f02 change); if f02 is held, rebase only the collision block onto the live file",
  "what": "revert.php wrote revert_to_email back to users without checking whether another user now holds that address. If no unique index exists, that creates duplicate emails. Inside the transaction and before any write, it now runs SELECT ... WHERE email = :email AND id != :id ... FOR UPDATE, the same id != self rule as confirm-change. On a hit, nothing is written and it returns 409 'email_in_use'. A unique-index violation (SQLSTATE 23000) from a race is mapped to the same 409. Other PDO errors are rethrown unchanged.",
  "files": [
    "files/sync-storage/api/auth/email/revert.php",
    "tests/email-revert-collision.php",
    "faq/faq-entries.md"
  ],
  "decisions": [
    "On conflict NOTHING is changed: the token is not consumed, the email is not reverted, sessions are not bumped and the password is not locked. Locking the password while the email still points at the possibly-attacker address would hand recovery to the attacker via forgot-password. NEEDS HUMAN DECISION: should a conflict still bump sessions, or flag the account for admin review? The default is no change plus error_log for support.",
    "The 409 message admits the address belongs to another account. The caller has already proved they know that address (hash_equals check above), so this leaks nothing new to them.",
    "Exact equality (email = :email) is used, assuming case-insensitive collation and lowercase-stored emails; confirm-change.php is not shown, so this could not be matched to its query byte for byte.",
    "A by-reference flag is used instead of calling Response::error inside the transaction closure, so the transaction never ends via exit."
  ],
  "data_reason": "No new UPDATE/DELETE. The existing UPDATEs from f02 are now skipped when the restored address belongs to another user.",
  "tests": [
    "php f11/tests/email-revert-collision.php",
    "php f11/tests/email-revert-collision.php <live>/sync-storage/api/auth/email/revert.php (after deploy)",
    "php f02/tests/email-revert-forced-reset.php f11/files/sync-storage/api/auth/email/revert.php (f02 behaviour must still pass on this file)"
  ],
  "risks": [
    "NOTHING HERE HAS BEEN RUN, LINTED OR TESTED; it is a draft for the lane session to verify.",
    "Without a unique index on users.email, FOR UPDATE under REPEATABLE READ relies on gap locks on an email index. If there is no index on email at all, it may lock more rows than intended or not fully close the race. A unique index is the real fix; its migration is not included.",
    "Assumes Database::transaction rolls back and rethrows exceptions (so the 23000 catch works) and commits normally when the closure returns early. Database class not shown.",
    "On conflict the token stays unused but will still expire. Support must act before expires_at or reissue a link.",
    "If users.email is stored or compared case-sensitively, a differently-cased duplicate would slip past this check."
  ],
  "verify": [
    "Confirm the live path is sync-storage/api/auth/email/revert.php (derived from the attachment name and require_once depth).",
    "confirm-change.php: file not shown. Compare its collision query and 409 error code with the ones here, and align the code name ('email_in_use') if it differs.",
    "Database::transaction: file not shown. Confirm the early-return and rethrow behaviour described in risks.",
    "Schema: file not shown. Check whether users.email has a unique index and what its collation is. If there is no unique index, consider a separate migration after deduplicating existing rows, which needs its own data_reason.",
    "Human decision on conflict behaviour (see decisions).",
    "Ship order: f02 first, then this file. If f02 is rejected, do NOT ship this file as-is, because it contains the f02 password lock.",
    "Manual check on staging: A changes email; B registers A's old address; A clicks revert. Expect 409 email_in_use, A's row, token and sessions unchanged, and an error_log line."
  ],
  "regions": false
}
=== END FILE ===