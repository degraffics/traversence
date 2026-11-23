<?php
// app/lib/Captcha.php — a captcha on sign-in, sign-up and "forgot password" (decisions/0097): Cloudflare Turnstile,
// free, usually invisible (no puzzles), no ad tracking. Off until TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY are in
// .env (a free widget at dash.cloudflare.com → Turnstile; the site's DNS doesn't have to be on Cloudflare).
//
//   Captcha::widget()   the box and its script for a form (nothing when off); its token is read by tvCaptcha() in JS
//   Captcha::verify($t) checks a token with Cloudflare; throws RuntimeException when it isn't valid
declare(strict_types=1);

final class Captcha
{
    private const VERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private static function env(string $k): string
    {
        if (getenv($k) === false && class_exists('Database')) { try { Database::connection(); } catch (Throwable) {} }   // loads .env
        return trim((string) getenv($k));
    }

    public static function on(): bool { return self::env('TURNSTILE_SITE_KEY') !== '' && self::env('TURNSTILE_SECRET_KEY') !== ''; }

    /** The public site key, for a check drawn by a page's own script (likes: js/engage.js); empty when off. */
    public static function siteKey(): string { return self::on() ? self::env('TURNSTILE_SITE_KEY') : ''; }

    /** The widget for a form, plus tvCaptcha() (the token) and tvCaptchaReset() (a token works once). Empty when off. */
    public static function widget(): string
    {
        // tvCaptchaWait(): the check often runs unseen and takes a few seconds (Jason, 2026-11-05: "Please wait…" as he
        // pressed Sign in). A form waits for it (up to 12 s), resets it once if it stalls or fails, and waits again (8 s);
        // only then does it send without a token, and the server explains what to do.
        $js = '<script>window.tvCaptcha=function(){var i=document.querySelector(\'[name="cf-turnstile-response"]\');return i?i.value:"";};'
            . 'window.tvCaptchaReset=function(){window.tvCaptchaErr=false;try{window.turnstile&&turnstile.reset();}catch(e){}};'
            . 'window.tvCaptchaFail=function(){window.tvCaptchaErr=true;};'
            . 'window.tvCaptchaWait=function(){var w=function(ms){return new Promise(function(res){var t0=Date.now();(function poll(){var v=tvCaptcha();'
            . 'if(v||!document.querySelector(".cf-turnstile")||window.tvCaptchaErr||Date.now()-t0>ms)return res(v);setTimeout(poll,250);})();});};'
            . 'return w(12000).then(function(v){if(v||!document.querySelector(".cf-turnstile"))return v;tvCaptchaReset();return w(8000);});};</script>';
        if (!self::on()) return $js;
        return $js . '<div class="cf-turnstile" data-sitekey="' . htmlspecialchars(self::env('TURNSTILE_SITE_KEY'), ENT_QUOTES)
            . '" data-theme="light" data-size="flexible" data-error-callback="tvCaptchaFail" data-retry="auto" data-refresh-expired="auto" style="margin:.6rem 0"></div>'
            . '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    }

    public static function verify(string $token): void
    {
        if (!self::on()) return;
        if ($token === '') throw new RuntimeException('The "verify you\'re human" check didn\'t finish. Reload the page and try again; if it keeps happening, turn off any ad or script blocker for this site (it needs challenges.cloudflare.com), or sign in with Google, Microsoft or Facebook below.');
        require_once __DIR__ . '/StagingGate.php'; if (StagingGate::hold(self::VERIFY, 'the human check') !== null) return;   // staging: as when Cloudflare doesn't answer (test keys there; decisions/0165)
        $ch = curl_init(self::VERIFY);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_POSTFIELDS => http_build_query(['secret' => self::env('TURNSTILE_SECRET_KEY'), 'response' => $token, 'remoteip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '')])]);
        $out = curl_exec($ch);
        curl_close($ch);
        if ($out === false) { error_log('captcha: Cloudflare didn\'t answer'); return; }   // Cloudflare down: don't lock everyone out
        $j = json_decode((string) $out, true);
        $codes = (array) ($j['error-codes'] ?? []);
        // our own secret refused: people get through without the check, as when Cloudflare is down, and sign-in's duty trips
        // (critical, at once) so a new key goes in (Jason, 2026-11-21, 03 §24 3: "a refused secret lets people through")
        if (empty($j['success']) && array_intersect($codes, ['invalid-input-secret', 'missing-input-secret'])) {
            error_log('captcha: Cloudflare refused our Turnstile secret (' . implode(',', $codes) . '); letting the check through');
            try { require_once __DIR__ . '/Database.php'; require_once __DIR__ . '/ControlAlarms.php';
                ControlAlarms::raise(Database::connection(), 'captcha:secret', 'site:signin', 'trip', 'held', 'Cloudflare refused our Turnstile secret',
                    'Sign-in, sign-up and Forgot password carry on without the "verify you\'re human" check until a new key goes in.',
                    'Put a new TURNSTILE_SECRET_KEY on Its key (Cloudflare\'s card), from Cloudflare\'s Turnstile page; then Check.', implode(', ', $codes),
                    [['Its key (Cloudflare\'s card)', 'peek', '/admin/config/register#svc-cloudflare-key'], ['Open Cloudflare Turnstile', 'link', 'https://dash.cloudflare.com/?to=/:account/turnstile'], ['Check', 'check', 'svc:cloudflare']], true, false, 'site:signin'); } catch (Throwable $x) {}
            return;
        }
        if (!empty($j['success'])) { try { require_once __DIR__ . '/Database.php'; require_once __DIR__ . '/ControlAlarms.php'; ControlAlarms::clear(Database::connection(), 'captcha:secret', 'Cloudflare accepted our secret at ' . gmdate('H:i') . ' UTC.'); } catch (Throwable $x) {} }
        if (empty($j['success'])) {
            error_log('captcha refused: ' . implode(',', $codes));
            throw new RuntimeException('The "verify you\'re human" check didn\'t pass. Please try again.');
        }
    }
}
