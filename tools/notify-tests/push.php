<?php
// decisions/0172 §4.6's tests: the push engine (WebPush) with no network and no database: keys, the VAPID JWT, and
// RFC 8291's encryption, checked from the browser's side and against the RFC's own test vector (§5).
//   Run: php push.php <app dir with the release laid over it>   (e.g. php push.php /home/user/build/comms/app)
//   (stand-ins for Database, EnvSettings and StagingGate are below; nothing is written to the app dir.)
declare(strict_types=1);
$app = $argv[1] ?? '';
if (!is_file($app . '/lib/WebPush.php')) { fwrite(STDERR, "usage: php push.php <app dir>\n"); exit(2); }

// ---- stand-ins -------------------------------------------------------------------------------------------------------
final class Database { public static function readEnv(): void {} public static function envPath(): string { return '/nonexistent/.env'; } }
final class EnvSettings {
    public const WRITE_ONLY = ['HEALTH_TOKEN', 'VAPID_PRIVATE_PEM_B64', 'VAPID_PUBLIC_B64U'];
    public static array $written = [];
    public static function setSecret(string $key, string $value): void
    {
        if (!in_array($key, self::WRITE_ONLY, true) || !preg_match('/^[A-Za-z0-9_-]{32,128}$/', $value)) throw new InvalidArgumentException('That isn\'t a secret the site makes.');
        self::$written[$key] = $value; putenv($key . '=' . $value); $_ENV[$key] = $value;
    }
}
final class StagingGate { public static ?string $answer = null; public static function hold(string $u, string $w = '', string $r = '', bool $b = false): ?string { return self::$answer; } }
putenv('VAPID_PRIVATE_PEM_B64'); putenv('VAPID_PUBLIC_B64U'); unset($_ENV['VAPID_PRIVATE_PEM_B64'], $_ENV['VAPID_PUBLIC_B64U']);

require $app . '/lib/WebPush.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES) : '') . "\n"; } };
$u = fn (string $s) => WebPush::unb64u($s);
$b = fn (string $s) => WebPush::b64u($s);

// the receiver's side of RFC 8291: what a browser does with a push it gets
$decrypt = function (string $msg, $uaPriv, string $uaPub, string $auth): ?string {
    $salt = substr($msg, 0, 16); $rs = unpack('N', substr($msg, 16, 4))[1]; $idlen = ord($msg[20]);
    $asPub = substr($msg, 21, $idlen); $ct = substr($msg, 21 + $idlen);
    if ($idlen !== 65 || strlen($ct) > $rs) return null;
    $secret = openssl_pkey_derive(WebPush::publicFromPoint($asPub), $uaPriv, 32);
    $prkKey = hash_hmac('sha256', $secret, $auth, true);                                       // RFC 8291 §3.3, by hand
    $ikm = substr(hash_hmac('sha256', "WebPush: info\x00" . $uaPub . $asPub . "\x01", $prkKey, true), 0, 32);
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk, true), 0, 16);
    $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk, true), 0, 12);
    $pt = openssl_decrypt(substr($ct, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($ct, -16));
    if ($pt === false) return null;
    $pt = rtrim($pt, "\x00");
    return substr($pt, -1) === "\x02" ? substr($pt, 0, -1) : null;                              // last record: delimiter 2
};

// ---- (a) keys ---------------------------------------------------------------------------------------------------------
$k1 = WebPush::publicKey();
$pt = $u($k1);
$t('the first call makes a key pair and writes both to .env', isset(EnvSettings::$written['VAPID_PRIVATE_PEM_B64'], EnvSettings::$written['VAPID_PUBLIC_B64U']));
$t('publicKey() is base64url (no padding, no + or /)', (bool) preg_match('/^[A-Za-z0-9_-]+$/', $k1), $k1);
$t('publicKey() is a 65-byte uncompressed point', strlen($pt) === 65 && $pt[0] === "\x04", strlen($pt));
$t('the private value is 32 bytes and fits setSecret\'s pattern', strlen($u(EnvSettings::$written['VAPID_PRIVATE_PEM_B64'])) === 32);
$t('the public value fits setSecret\'s pattern (≤ 128)', strlen(EnvSettings::$written['VAPID_PUBLIC_B64U']) === 87);
$n = count(EnvSettings::$written); EnvSettings::$written = [];
$t('the second call reuses the keys (nothing written)', WebPush::publicKey() === $k1 && EnvSettings::$written === []);
[$d, $pub] = WebPush::keys();
$fromD = openssl_pkey_get_details(WebPush::privateFromD($d))['ec'];
$t('d alone rebuilds the same public point', "\x04" . str_pad($fromD['x'], 32, "\x00", STR_PAD_LEFT) . str_pad($fromD['y'], 32, "\x00", STR_PAD_LEFT) === $pub);
$t('privateFromRaw reads the stored pair', WebPush::privateFromRaw($d, $pub) !== false);
$t('a point off the curve is refused', (function () { try { WebPush::publicFromPoint("\x04" . str_repeat("\x01", 64)); return false; } catch (InvalidArgumentException $e) { return true; } })());

// ---- (b) the VAPID JWT ------------------------------------------------------------------------------------------------
$now = time();
$jwt = WebPush::jwt('https://fcm.googleapis.com', null, $now);
$parts = explode('.', $jwt);
$t('the JWT has three base64url parts', count($parts) === 3 && !preg_match('/[^A-Za-z0-9_.-]/', $jwt));
$h = json_decode($u($parts[0]), true); $c = json_decode($u($parts[1]), true);
$t('header: ES256, JWT', ($h['alg'] ?? '') === 'ES256' && ($h['typ'] ?? '') === 'JWT', $h);
$t('claims: aud is the push service\'s origin, sub mailto, exp within 12 hours', ($c['aud'] ?? '') === 'https://fcm.googleapis.com' && ($c['sub'] ?? '') === 'mailto:support@traversence.com' && $c['exp'] > $now && $c['exp'] <= $now + 12 * 3600, $c);
$raw = $u($parts[2]);
$t('the signature is raw r||s, 64 bytes', strlen($raw) === 64, strlen($raw));
$t('it verifies with openssl_verify after r||s → DER', openssl_verify($parts[0] . '.' . $parts[1], WebPush::rawToDer($raw), WebPush::publicFromPoint($pub), OPENSSL_ALGO_SHA256) === 1);
$t('a changed claim doesn\'t verify', openssl_verify($parts[0] . '.' . $b('{"aud":"https://evil.example"}'), WebPush::rawToDer($raw), WebPush::publicFromPoint($pub), OPENSSL_ALGO_SHA256) === 0);
$okAll = true;   // r or s with a leading zero byte or a high bit: DER ↔ raw both ways, 200 times
for ($i = 0; $i < 200; $i++) { $s = WebPush::jwt('https://a.notify.windows.com', null, $now); $p = explode('.', $s); if (openssl_verify($p[0] . '.' . $p[1], WebPush::rawToDer($u($p[2])), WebPush::publicFromPoint($pub), OPENSSL_ALGO_SHA256) !== 1) $okAll = false; }
$t('200 signatures all convert and verify (short r and s padded)', $okAll);

// ---- (c) RFC 8291 -----------------------------------------------------------------------------------------------------
// §5's test vector
$V = [
    'plain' => 'When I grow up, I want to be a watermelon',
    'as_priv' => 'yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw',
    'as_pub' => 'BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8',
    'ua_priv' => 'q1dXpw3UpT5VOmu_cf_v6ih07Aems3njxI-JWgLcM94',
    'ua_pub' => 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4',
    'auth' => 'BTBZMqHH6r4Tts7J_aSIgg',
    'salt' => 'DGv6ra1nlYgDCS1FRnbzlw',
    'ecdh' => 'kyrL1jIIOHEzg3sM2ZWRHDRB62YACZhhSlknJ672kSs',
    'cek' => 'oIhVW04MRdy2XN9CiKLxTg',
    'nonce' => '4h_95klXJ5E_qnoN',
    'body' => 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN',
];
$ecdh = openssl_pkey_derive(WebPush::publicFromPoint($u($V['ua_pub'])), WebPush::privateFromD($u($V['as_priv'])), 32);
$t('RFC 8291 §5: the ECDH secret matches', $ecdh === $u($V['ecdh']), $b((string) $ecdh));
[$cek, $nonce] = WebPush::derive($ecdh, $u($V['auth']), $u($V['ua_pub']), $u($V['as_pub']), $u($V['salt']));
$t('RFC 8291 §5: CEK matches', $cek === $u($V['cek']), $b($cek));
$t('RFC 8291 §5: NONCE matches', $nonce === $u($V['nonce']), $b($nonce));
$vec = WebPush::encrypt($V['plain'], $V['ua_pub'], $V['auth'], $u($V['as_priv']), $u($V['salt']));
$t('RFC 8291 §5: the whole message matches byte for byte', $b($vec) === $V['body'], $b($vec));
$t('RFC 8291 §5: the receiver decrypts the published message', $decrypt($u($V['body']), WebPush::privateFromD($u($V['ua_priv'])), $u($V['ua_pub']), $u($V['auth'])) === $V['plain']);

// a round trip: we play the browser
[$uaD, $uaPub] = WebPush::newPair();
$auth = random_bytes(16);
$msg = WebPush::payload(['title' => 'Joe\'s Diner wrote to you', 'body' => str_repeat('Long message é ', 400), 'url' => 'https://traversence.com/user/dashboard.php#pulse', 'stop' => '/user/dashboard.php#communications', 'tag' => 'msg-10', 'level' => 'alert']);
$t('the payload stays under 3 KB (a long body is shortened)', strlen($msg) <= 3072, strlen($msg));
$pj = json_decode($msg, true);
$t('the payload keeps title, url, a full https Stop these link, tag, level', $pj['title'] === 'Joe\'s Diner wrote to you' && $pj['url'] === 'https://traversence.com/user/dashboard.php#pulse' && $pj['stop'] === 'https://traversence.com/user/dashboard.php#communications' && $pj['tag'] === 'msg-10' && $pj['level'] === 'alert', $pj);
$t('a link off our sites is replaced', json_decode(WebPush::payload(['url' => 'https://evil.example/x', 'stop' => 'javascript:alert(1)']), true)['url'] === 'https://traversence.com/');
$enc = WebPush::encrypt($msg, $b($uaPub), $b($auth));
$t('header: rs 4096, idlen 65, the key id is a point', unpack('N', substr($enc, 16, 4))[1] === 4096 && ord($enc[20]) === 65 && $enc[21] === "\x04");
$t('ciphertext is payload + delimiter + 16-byte tag', strlen($enc) === 16 + 4 + 1 + 65 + strlen($msg) + 1 + 16, strlen($enc));
$t('the browser decrypts it to the same payload', $decrypt($enc, WebPush::privateFromD($uaD), $uaPub, $auth) === $msg);
$t('a wrong auth secret can\'t decrypt it', $decrypt($enc, WebPush::privateFromD($uaD), $uaPub, random_bytes(16)) === null);
$enc2 = WebPush::encrypt($msg, $b($uaPub), $b($auth));
$t('each push has its own salt and ephemeral key', substr($enc, 0, 16) !== substr($enc2, 0, 16) && substr($enc, 21, 65) !== substr($enc2, 21, 65));

// ---- endpoints --------------------------------------------------------------------------------------------------------
$t('push services we send to are allowed', WebPush::allowedEndpoint('https://fcm.googleapis.com/fcm/send/abc') && WebPush::allowedEndpoint('https://updates.push.services.mozilla.com/wpush/v2/x')
    && WebPush::allowedEndpoint('https://web.push.apple.com/Qx') && WebPush::allowedEndpoint('https://wns2-bl2p.notify.windows.com/w/?token=x') && WebPush::allowedEndpoint('https://api.push.apple.com/3/device/x'));
$t('others are refused: http, look-alikes, ports, user info, the bare suffix', !WebPush::allowedEndpoint('http://fcm.googleapis.com/x') && !WebPush::allowedEndpoint('https://fcm.googleapis.com.evil.example/x')
    && !WebPush::allowedEndpoint('https://evilnotify.windows.com/x') && !WebPush::allowedEndpoint('https://notify.windows.com/x') && !WebPush::allowedEndpoint('https://fcm.googleapis.com:8443/x')
    && !WebPush::allowedEndpoint('https://u@fcm.googleapis.com/x') && !WebPush::allowedEndpoint('https://169.254.169.254/x') && !WebPush::allowedEndpoint('https://localhost/x'));
$bad = function (array $sub) { try { WebPush::subscribe(new PDO('sqlite::memory:'), 5, $sub, 'x'); return false; } catch (InvalidArgumentException $e) { return true; } };
$t('subscribe refuses an unknown push service before touching the table', $bad(['endpoint' => 'https://evil.example/x', 'keys' => ['p256dh' => $b($uaPub), 'auth' => $b($auth)]]));
$t('subscribe refuses a short key or secret', $bad(['endpoint' => 'https://fcm.googleapis.com/x', 'keys' => ['p256dh' => 'abc', 'auth' => $b($auth)]]) && $bad(['endpoint' => 'https://fcm.googleapis.com/x', 'keys' => ['p256dh' => $b($uaPub), 'auth' => 'abc']]));

// ---- subscriptions and sending, on a real MySQL or MariaDB when one is there (no network: the transport is stubbed) ----
$pdo = null;
try { $pdo = new PDO('mysql:host=localhost;dbname=tvtest;charset=utf8mb4', 'tv', 'tv', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); } catch (Throwable $e) { echo "  --  no test database (mariadb -e 'CREATE DATABASE tvtest'): subscriptions and sending skipped\n"; }
if ($pdo) {
    $pdo->exec('DROP TABLE IF EXISTS push_subs');
    $mig = glob($app . '/migrations/*comms_engine*.sql');
    if ($mig && preg_match('/CREATE TABLE IF NOT EXISTS push_subs \(.*?\) ENGINE=InnoDB[^;]*;/s', (string) file_get_contents($mig[0]), $m)) $pdo->exec($m[0]);
    else $pdo->exec("CREATE TABLE push_subs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, endpoint VARCHAR(600) NOT NULL, endpoint_hash CHAR(64) NOT NULL, p256dh VARCHAR(200) NOT NULL, auth_key VARCHAR(64) NOT NULL, device VARCHAR(120) NOT NULL DEFAULT '', created_at DATETIME NOT NULL, last_ok_at DATETIME NULL, failed_at DATETIME NULL, fails INT NOT NULL DEFAULT 0, UNIQUE KEY (endpoint_hash))");
    $t('ready() sees the table', WebPush::ready($pdo));
    $one = fn (string $s) => $pdo->query($s)->fetchColumn();
    $devs = [];
    foreach (['https://fcm.googleapis.com/fcm/send/a1', 'https://updates.push.services.mozilla.com/wpush/v2/b2', 'https://web.push.apple.com/c3'] as $i => $ep) {
        [$dd, $pp] = WebPush::newPair(); $aa = random_bytes(16);
        $devs[$ep] = [WebPush::privateFromD($dd), $pp, $aa];
        WebPush::subscribe($pdo, 7, ['endpoint' => $ep, 'keys' => ['p256dh' => $b($pp), 'auth' => $b($aa)]], "Device $i\x07 on test");
    }
    $t('three devices subscribed', (int) $one('SELECT COUNT(*) FROM push_subs WHERE user_id = 7') === 3);
    WebPush::subscribe($pdo, 7, ['endpoint' => 'https://fcm.googleapis.com/fcm/send/a1', 'keys' => ['p256dh' => $b($devs['https://fcm.googleapis.com/fcm/send/a1'][1]), 'auth' => $b($devs['https://fcm.googleapis.com/fcm/send/a1'][2])]], 'Chrome on Android');
    $list = WebPush::devices($pdo, 7);
    $t('subscribing again updates the device, not a second row', count($list) === 3 && in_array('Chrome on Android', array_column($list, 'device'), true));
    $t('devices() gives the endpoint\'s hash (a page tells its own), never the endpoint', in_array(hash('sha256', 'https://web.push.apple.com/c3'), array_column($list, 'hash'), true) && !isset($list[0]['endpoint']));
    $t('control characters are taken out of the device name', !preg_match('/[\x00-\x1f]/', implode('', array_column($list, 'device'))));
    $t('nobody else\'s devices', WebPush::devices($pdo, 8) === [] && WebPush::sendToUser($pdo, 8, ['title' => 'x'])['none'] === true);

    $calls = []; $answers = [];
    WebPush::$transport = function (string $url, array $h, string $body) use (&$calls, &$answers) { $calls[] = compact('url', 'h', 'body'); return $answers[$url] ?? 201; };
    $r = WebPush::sendToUser($pdo, 7, ['title' => 'Your order is ready', 'body' => 'Order #9', 'url' => '/user/dashboard.php#pulse', 'stop' => '/user/dashboard.php#communications', 'tag' => 'order-9', 'level' => 'urgent']);
    $t('sent to all three', $r === ['sent' => 3, 'failed' => 0, 'held' => 0, 'none' => false], $r);
    $hv = function (array $h, string $name) { foreach ($h as $l) if (stripos($l, $name . ':') === 0) return trim(substr($l, strlen($name) + 1)); return null; };
    $c0 = $calls[0];
    $t('urgent: TTL 86400, Urgency high, Topic from the tag, aes128gcm', $hv($c0['h'], 'TTL') === '86400' && $hv($c0['h'], 'Urgency') === 'high' && $hv($c0['h'], 'Topic') === 'order-9' && $hv($c0['h'], 'Content-Encoding') === 'aes128gcm');
    $auth = (string) $hv($c0['h'], 'Authorization');
    $t('Authorization: vapid t=<jwt>, k=<our public key>', (bool) preg_match('/^vapid t=([A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+), k=' . preg_quote($k1, '/') . '$/', $auth, $mm), $auth);
    $t('each push service gets a JWT for its own origin', json_decode($u(explode('.', $mm[1] ?? '..')[1]), true)['aud'] === 'https://' . parse_url($c0['url'], PHP_URL_HOST));
    $got = true; foreach ($calls as $c) { [$pk, $pp, $aa] = $devs[$c['url']]; $j = json_decode((string) $decrypt($c['body'], $pk, $pp, $aa), true); if (($j['title'] ?? '') !== 'Your order is ready' || $j['url'] !== 'https://traversence.com/user/dashboard.php#pulse') $got = false; }
    $t('each device decrypts its own push (links made absolute)', $got);
    $t('last_ok_at set', (int) $one('SELECT COUNT(*) FROM push_subs WHERE user_id = 7 AND last_ok_at IS NOT NULL') === 3);

    $calls = []; WebPush::sendToUser($pdo, 7, ['title' => 'x', 'level' => 'general']);
    $t('general: TTL 43200, Urgency low; alert: normal', $hv($calls[0]['h'], 'TTL') === '43200' && $hv($calls[0]['h'], 'Urgency') === 'low'
        && ($calls = []) === [] && WebPush::sendToUser($pdo, 7, ['title' => 'x', 'level' => 'alert']) && $hv($calls[0]['h'], 'Urgency') === 'normal');

    $answers = ['https://updates.push.services.mozilla.com/wpush/v2/b2' => 410, 'https://web.push.apple.com/c3' => 503];
    $r = WebPush::sendToUser($pdo, 7, ['title' => 'x']);
    $t('410 deletes that subscription; 503 counts a failure', $r['sent'] === 1 && $r['failed'] === 2 && (int) $one("SELECT COUNT(*) FROM push_subs WHERE endpoint LIKE '%mozilla%'") === 0
        && (int) $one("SELECT fails FROM push_subs WHERE endpoint LIKE '%apple%'") === 1, $r);
    for ($i = 0; $i < 4; $i++) WebPush::sendToUser($pdo, 7, ['title' => 'x']);
    $t('5 failures in a row mark it failed', $one("SELECT failed_at IS NOT NULL FROM push_subs WHERE endpoint LIKE '%apple%'") == 1);
    $calls = []; WebPush::sendToUser($pdo, 7, ['title' => 'x']);
    $t('a failed device isn\'t tried again the same day', count($calls) === 1 && $calls[0]['url'] === 'https://fcm.googleapis.com/fcm/send/a1');
    $answers = [];
    $pdo->exec("UPDATE push_subs SET failed_at = UTC_TIMESTAMP() - INTERVAL 2 DAY WHERE endpoint LIKE '%apple%'");
    WebPush::sendToUser($pdo, 7, ['title' => 'x']);
    $t('it\'s tried once a day after, and a 201 clears it', $one("SELECT CONCAT(fails, '/', failed_at IS NULL) FROM push_subs WHERE endpoint LIKE '%apple%'") === '0/1');

    StagingGate::$answer = 'TEST MODE · held'; $calls = [];
    $r = WebPush::sendToUser($pdo, 7, ['title' => 'x']);
    $t('on staging the gate holds every push; nothing is sent', $r['held'] === 2 && $r['sent'] === 0 && $calls === [], $r);
    StagingGate::$answer = null;

    $t2 = WebPush::test($pdo, 7);
    $t('test() reaches each device', $t2['sent'] === 2, $t2);
    WebPush::unsubscribe($pdo, 7, 'https://fcm.googleapis.com/fcm/send/a1');
    WebPush::remove($pdo, 8, (int) $one("SELECT id FROM push_subs WHERE endpoint LIKE '%apple%'"));
    $t('unsubscribe takes this device off; remove() only the person\'s own', (int) $one('SELECT COUNT(*) FROM push_subs') === 1);
    WebPush::remove($pdo, 7, (int) $one("SELECT id FROM push_subs WHERE endpoint LIKE '%apple%'"));
    $t('remove() by id', (int) $one('SELECT COUNT(*) FROM push_subs') === 0);
    $pdo->exec('DROP TABLE push_subs');
}

echo "\n" . $pass . ' passed, ' . $fail . " failed\n";
exit($fail ? 1 : 0);
