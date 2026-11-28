<?php

declare(strict_types=1);

/**
 * Finding f13: map Auth::attemptLogin failures to HTTP responses without
 * revealing whether an account exists.
 *
 * 409 'closing' and 403 'unconfirmed' can only be shown safely if
 * Auth::attemptLogin verifies the password BEFORE it throws 4090/4030.
 * Auth is not shown, so the default treats them as a generic 401.
 */
final class LoginErrorMap
{
    // PERSON'S DECISION (f13): set to true only after confirming that Auth::attemptLogin
    // runs password_verify before throwing code 4090 or 4030.
    public const STATUS_AFTER_PASSWORD_CONFIRMED = false;

    public const GENERIC_MESSAGE = 'Incorrect email or password.';

    /** @return array{0:int,1:string,2:string} [http status, error code, message] */
    public static function forException(int $code, string $message, bool $statusAfterPassword = self::STATUS_AFTER_PASSWORD_CONFIRMED): array
    {
        if ($statusAfterPassword && $code === 4090) {
            return [409, 'closing', $message];      // decisions/0091: on to /user/restore-account.php
        }
        if ($statusAfterPassword && $code === 4030) {
            return [403, 'unconfirmed', $message];  // decisions/0014: confirm the email first
        }
        // One fixed message for every 401, so the text cannot differ between "no such user" and "wrong password".
        return [401, 'invalid_credentials', self::GENERIC_MESSAGE];
    }
}
