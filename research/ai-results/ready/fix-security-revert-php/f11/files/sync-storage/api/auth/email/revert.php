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
