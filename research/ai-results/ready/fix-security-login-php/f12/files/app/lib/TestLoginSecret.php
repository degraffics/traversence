<?php

declare(strict_types=1);

/**
 * Finding f12: the captcha bypass for test accounts must also present a server
 * secret. The bypass was keyed on the email alone.
 *
 * Secret: env TEST_LOGIN_SECRET (at least 32 chars). Request header: X-Test-Login-Secret.
 * If the secret is unset or too short, the bypass is OFF and the captcha always applies.
 */
final class TestLoginSecret
{
    public const MIN_LENGTH = 32;
    public const ENV_NAME = 'TEST_LOGIN_SECRET';

    public static function configured(): string
    {
        $v = getenv(self::ENV_NAME);
        if ($v === false || $v === '') {
            $v = $_ENV[self::ENV_NAME] ?? $_SERVER[self::ENV_NAME] ?? '';
        }
        return is_string($v) ? trim($v) : '';
    }

    public static function matches(string $configured, string $given): bool
    {
        if (strlen($configured) < self::MIN_LENGTH || $given === '') {
            return false;
        }
        return hash_equals($configured, $given);
    }
}
