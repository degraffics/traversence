<?php
// decisions/0172 §4.7's tests: the mail transport for outside mailboxes in Pulse (MailMime, MailImap, MailPop, MailSmtp,
// MsGraphMail), with no network and no database: the servers are scripted on a socket pair, Microsoft on a stand-in.
//   Run: php mail-transport.php <a copy of app/ with the release laid over it>
declare(strict_types=1);
$app = $argv[1] ?? '';
if (!is_file($app . '/lib/MailMime.php')) { fwrite(STDERR, "usage: php mail-transport.php <app dir>\n"); exit(2); }

// ---- stand-ins -------------------------------------------------------------------------------------------------------
final class StagingGate { public static ?string $say = null; public static array $asked = [];
    public static function hold(string $url, string $what = '', string $ref = '', bool $bulk = false): ?string { self::$asked[] = $url; return self::$say; } }
foreach (['MailMime', 'MailImap', 'MailPop', 'MailSmtp', 'MsGraphMail'] as $c) require $app . '/lib/' . $c . '.php';

$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$crlf = fn (string $s) => str_replace("\n", "\r\n", str_replace("\r\n", "\n", $s));
$throws = function (callable $f): string { try { $f(); return ''; } catch (Throwable $e) { return $e->getMessage(); } };

// A scripted server: everything it will say is written first; the client end is handed to the class. $sent() reads what the client said.
$server = function (string $script) use (&$peer) {
    [$a, $b] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    fwrite($b, $script);
    $peer = $b;
    return $a;
};
$sent = function () use (&$peer): string { stream_set_blocking($peer, false); $s = ''; while (($c = fread($peer, 65536)) !== false && $c !== '') $s .= $c; return $s; };

// ---- MailMime::parse ----------------------------------------------------------------------------------------------------
echo "MailMime::parse\n";
$alt = $crlf(<<<'EOM'
Return-Path: <ann@example.org>
Received: from mx.example.org by mail.biz.test; Tue, 17 Nov 2026 09:00:05 -0700
From: =?UTF-8?Q?Ann_M=C3=BCller?= <ann@example.org>
To: "Joe's Diner" <hello@joesdiner.test>, bob@example.net
Cc: Team: carl@example.net, dee@example.net;
Subject: =?UTF-8?B?Q2Fmw6kgb3BlbmluZyDimJU=?=
 =?UTF-8?B?IHRvbW9ycm93?=
Date: Tue, 17 Nov 2026 09:00:00 -0700
Message-ID: <abc123@example.org>
MIME-Version: 1.0
Content-Type: multipart/alternative;
 boundary="b1"

--b1
Content-Type: text/plain; charset=utf-8
Content-Transfer-Encoding: quoted-printable

Hello caf=C3=A9 fans, this is a long line that is soft broken by quoted-print=
able encoding.
=3D equals kept.
--b1
Content-Type: text/html; charset=utf-8
Content-Transfer-Encoding: base64

PHA+SGVsbG8gPGI+Y2Fmw6k8L2I+IGZhbnM8L3A+
--b1--
EOM);
$p = MailMime::parse($alt);
$t('From name decoded (Q, UTF-8)', $p['from'] === ['addr' => 'ann@example.org', 'name' => 'Ann Müller'], $p['from']);
$t('folded B subject joined across encoded words', $p['subject'] === 'Café opening ☕ tomorrow', $p['subject']);
$t('To: quoted name with apostrophe, and a bare address', $p['to'] === [['addr' => 'hello@joesdiner.test', 'name' => "Joe's Diner"], ['addr' => 'bob@example.net', 'name' => '']], $p['to']);
$t('Cc: a group flattened', array_column($p['cc'], 'addr') === ['carl@example.net', 'dee@example.net'], $p['cc']);
$t('Message-ID without brackets', $p['message_id'] === 'abc123@example.org');
$t('date to unix time', $p['date'] === strtotime('2026-11-17T16:00:00Z'), $p['date']);
$t('quoted-printable text with a soft break', $p['text'] === "Hello café fans, this is a long line that is soft broken by quoted-printable encoding.\n= equals kept.", $p['text']);
$t('base64 html', $p['html'] === '<p>Hello <b>café</b> fans</p>', $p['html']);
$t('no attachments in an alternative', $p['attachments'] === []);

$mixed = $crlf(<<<'EOM'
From: Bob <bob@example.net>
To: hello@joesdiner.test
Subject: =?ISO-8859-1?Q?R=E9servation_pour_la_f=EAte?=
Date: Wed, 18 Nov 2026 10:15:00 +0000
Message-ID: <reply-1@example.net>
In-Reply-To: <abc123@example.org>
References: <abc123@example.org>
 <mid-2@example.org>
Content-Type: multipart/mixed; boundary="outer"

This is the preamble.
--outer
Content-Type: multipart/related; boundary="rel"

--rel
Content-Type: multipart/alternative; boundary="alt"

--alt
Content-Type: text/plain; charset=iso-8859-1
Content-Transfer-Encoding: quoted-printable

Une r=E9servation, s'il vous pla=EEt.
--alt
Content-Type: text/html; charset=iso-8859-1

<p>Une r=E9servation <img src="cid:logo@x"></p>
--alt--
--rel
Content-Type: image/png
Content-ID: <logo@x>
Content-Transfer-Encoding: base64

iVBORw0KGgo=
--rel--
--outer
Content-Type: application/pdf; name="menu.pdf"
Content-Disposition: attachment; filename="menu.pdf"
Content-Transfer-Encoding: base64

JVBERi0xLjQKJcfsj6IK
--outer
Content-Type: text/plain
Content-Disposition: attachment;
 filename*=UTF-8''%C3%A9t%C3%A9%20notes.txt

summer notes
--outer--
epilogue
EOM);
$q = MailMime::parse($mixed);
$t('ISO-8859-1 Q subject to UTF-8', $q['subject'] === 'Réservation pour la fête', $q['subject']);
$t('ISO-8859-1 QP text to UTF-8', $q['text'] === "Une réservation, s'il vous plaît.", $q['text']);
$t('in_reply_to and folded references', $q['in_reply_to'] === 'abc123@example.org' && $q['references'] === ['abc123@example.org', 'mid-2@example.org'], [$q['in_reply_to'], $q['references']]);
$names = array_column($q['attachments'], 'name');
$t('three attachments: inline picture, pdf, RFC 2231 name', count($q['attachments']) === 3 && $names[1] === 'menu.pdf' && $names[2] === 'été notes.txt', $q['attachments']);
$t('part paths follow the nesting', array_column($q['attachments'], 'part') === ['1.2', '2', '3'], array_column($q['attachments'], 'part'));
$t('the inline picture carries its cid', $q['attachments'][0]['cid'] === 'logo@x' && $q['attachments'][0]['type'] === 'image/png');
$t('attachment size is the decoded size', $q['attachments'][1]['size'] === 15, $q['attachments'][1]['size']);
$t('no attachment bytes in the result', !array_key_exists('data', $q['attachments'][1]));
$pdf = MailMime::part($mixed, '2');
$t('part() pulls the pdf decoded', $pdf['data'] === "%PDF-1.4\n%\xC7\xEC\x8F\xA2\n" && $pdf['name'] === 'menu.pdf' && $pdf['type'] === 'application/pdf', bin2hex($pdf['data']));
$t('part() pulls a nested part', MailMime::part($mixed, '1.1.1')['data'] === "Une r\xE9servation, s'il vous pla\xEEt.");
$t('part() of a missing path throws', $throws(fn () => MailMime::part($mixed, '9')) !== '');

$htmlOnly = "From: news@shop.test\nTo: me@x.test\nSubject: Sale\nContent-Type: text/html; charset=windows-1252\n\n<html><body><p>Big \x93sale\x94 &amp; more</p><br>Today only</body></html>";
$h = MailMime::parse($htmlOnly);
$t('html-only: text made from the html (Windows-1252 quotes)', $h['text'] === "Big “sale” & more\n\nToday only", $h['text']);
$t('LF-only message and no Date reads as 0', $h['date'] === 0);
$plain = MailMime::parse("Subject: hi\n\nJust text, no MIME at all.\n");
$t('plain message without MIME headers', $plain['text'] === 'Just text, no MIME at all.' && $plain['subject'] === 'hi');
$t('garbage doesn\'t throw', is_array(MailMime::parse("\x00\xff--==?=?Q?\r\n\r\n--x--")));
$bad = MailMime::parse("Subject: =?UTF-8?B?w6l0w6k=?= =?UTF-8?B?IOKYlQ==?= and =?utf-8?q?plain?=\nFrom: \"Last, First\" <lf@x.test>\nContent-Type: text/plain; charset=x-unknown-thing\n\nBytes \xE9");
$t('adjacent encoded words joined, then plain text kept', $bad['subject'] === 'été ☕ and plain', $bad['subject']);
$t('comma inside a quoted name', $bad['from'] === ['addr' => 'lf@x.test', 'name' => 'Last, First'], $bad['from']);
$t('an unknown charset is tolerated (valid UTF-8 out)', mb_check_encoding($bad['text'], 'UTF-8') && str_starts_with($bad['text'], 'Bytes'), $bad['text']);

// ---- safeHtml ----------------------------------------------------------------------------------------------------------
echo "MailMime::safeHtml\n";
$dirty = '<html><head><style>body{display:none}</style><script>alert(1)</script></head><body onload="x()">'
    . '<p onclick="steal()" style="color:red;background:url(http://track.test/p.gif)">Hi <a href="javascript:alert(1)">bad</a> '
    . '<a href="https://good.test/x">good</a> <a href=" JaVa&#x0A;Script:alert(2)">tricky</a></p>'
    . '<img src="https://track.test/open.gif" width="1"><img src="cid:logo@x"><img src="data:image/png;base64,AAAA">'
    . '<iframe src="https://evil.test"></iframe><form action="https://evil.test"><input name="pw"></form>'
    . '<svg><script>alert(3)</script></svg><object data="x.swf"></object><div style="width:expression(alert(4))">e</div>'
    . '<!--[if mso]><p>old outlook</p><![endif]--><table background="http://track.test/bg.png"><tr><td>cell</td></tr></table></body></html>';
$s = MailMime::safeHtml($dirty);
$t('scripts, styles, frames, forms, svg, object gone', !preg_match('/<(script|style|iframe|form|input|svg|object)\b/i', $s) && !str_contains($s, 'alert(1)') && !str_contains($s, 'alert(3)'), $s);
$t('every on… attribute gone', !preg_match('/\son[a-z]+\s*=/i', $s), $s);
$t('javascript: links neutralized (even disguised)', !preg_match('/javascript/i', $s), $s);
$t('good links open in a new tab, no referrer', str_contains($s, 'href="https://good.test/x" target="_blank" rel="noopener noreferrer nofollow"'), $s);
$t('remote picture held, original kept', str_contains($s, 'data-held-src="https://track.test/open.gif"') && !preg_match('/\ssrc="https?:/', $s), $s);
$t('CSS url() held', !str_contains(preg_replace('/data-held-style="[^"]*"/', '', $s), 'track.test/p.gif') && str_contains($s, 'data-held-style'), $s);
$t('table background held', !preg_match('/\sbackground="http/', $s) && str_contains($s, 'data-held-background'), $s);
$t('cid picture → data-cid', str_contains($s, 'data-cid="logo@x"'), $s);
$t('data: picture not shown without pictures', !str_contains($s, 'data:image/png;base64,AAAA'), $s);
$t('CSS expression removed', !str_contains($s, 'expression'), $s);
$t('comments (conditional) removed', !str_contains($s, 'old outlook'), $s);
$t('content kept', str_contains($s, 'cell') && str_contains($s, 'Hi'), $s);
$sp = MailMime::safeHtml($dirty, true);
$t('$pictures = true keeps remote pictures and data:image', str_contains($sp, 'src="https://track.test/open.gif"') && str_contains($sp, 'data:image/png;base64,AAAA'), $sp);
$t('$pictures = true still has no script', !preg_match('/<script|javascript|onclick/i', $sp), $sp);
$t('garbage HTML survives', is_string(MailMime::safeHtml('<<<>>><p<div></span>&&&<img src=x onerror=alert(1)//')) && !str_contains(MailMime::safeHtml('<img src=x onerror=alert(1)//'), 'onerror'));
$t('UTF-8 kept through DOM', str_contains(MailMime::safeHtml('<p>Café ☕</p>'), 'Café ☕'));

// ---- snippet ----------------------------------------------------------------------------------------------------------
echo "MailMime::snippet\n";
$t('quoted reply cut', MailMime::snippet("Sounds good, see you at 6.\n\nOn Tue, Nov 17, 2026 at 9:00 AM Ann <ann@example.org> wrote:\n> Are we on?") === 'Sounds good, see you at 6.');
$t('"> " lines skipped', MailMime::snippet("> old\nNew words") === 'New words');
$long = MailMime::snippet(str_repeat('word ', 100), 30);
$t('cut at a word with …', mb_strlen($long) <= 31 && str_ends_with($long, '…'), $long);

// ---- build ------------------------------------------------------------------------------------------------------------
echo "MailMime::build\n";
$raw = MailMime::build(['from' => ['addr' => 'hello@joesdiner.test', 'name' => 'Joé’s Diner'], 'to' => [['addr' => 'ann@example.org', 'name' => 'Ann Müller']],
    'cc' => [['addr' => 'bob@example.net', 'name' => '']], 'subject' => 'Re: Café opening ☕ tomorrow — a much longer subject line so that it has to be split into several encoded words',
    'text' => "Yes!\n.\nA line starting with a dot.", 'html' => '<p>Yes!</p>', 'in_reply_to' => 'abc123@example.org', 'references' => ['abc123@example.org'],
    'attachments' => [['name' => 'map.pdf', 'type' => 'application/pdf', 'data' => "%PDF bytes\x00\xff"]]]);
$t('CRLF only', !preg_match('/(?<!\r)\n/', $raw));
$t('no header line over 998, encoded subject lines ≤ 78', max(array_map('strlen', explode("\r\n", substr($raw, 0, strpos($raw, "\r\n\r\n"))))) <= 78, array_filter(explode("\r\n", substr($raw, 0, strpos($raw, "\r\n\r\n"))), fn ($l) => strlen($l) > 78));
$t('Message-ID made from the sender\'s domain', (bool) preg_match('/^Message-ID: <[0-9a-f]{32}@joesdiner\.test>\r$/m', $raw));
$b = MailMime::parse($raw);
$t('round trip: from, to, cc', $b['from'] === ['addr' => 'hello@joesdiner.test', 'name' => 'Joé’s Diner'] && $b['to'][0]['name'] === 'Ann Müller' && $b['cc'][0]['addr'] === 'bob@example.net', [$b['from'], $b['to'], $b['cc']]);
$t('round trip: subject', $b['subject'] === 'Re: Café opening ☕ tomorrow — a much longer subject line so that it has to be split into several encoded words', $b['subject']);
$t('round trip: text and html', $b['text'] === "Yes!\n.\nA line starting with a dot." && $b['html'] === '<p>Yes!</p>', [$b['text'], $b['html']]);
$t('round trip: attachment', count($b['attachments']) === 1 && $b['attachments'][0]['name'] === 'map.pdf' && MailMime::part($raw, $b['attachments'][0]['part'])['data'] === "%PDF bytes\x00\xff", $b['attachments']);
$t('round trip: threading headers', $b['in_reply_to'] === 'abc123@example.org' && $b['references'] === ['abc123@example.org']);
$t('a quoted ASCII name', str_contains(MailMime::build(['addr' => 'a@b.test', 'name' => 'A "B" C', 'to' => 'x@y.test', 'subject' => 's', 'text' => 't']), 'From: "A \"B\" C" <a@b.test>'));
$t('build without a sender throws', $throws(fn () => MailMime::build(['to' => 'x@y.test'])) !== '');

// ---- threadKey --------------------------------------------------------------------------------------------------------
echo "MailMime::threadKey\n";
$t('a reply groups with its original', MailMime::threadKey($p) === MailMime::threadKey($q) && strlen(MailMime::threadKey($p)) === 40);
$t('our built reply groups with the original too', MailMime::threadKey($b) === MailMime::threadKey($p));
$k1 = MailMime::threadKey(['subject' => 'Booking', 'from' => ['addr' => 'a@x.test'], 'to' => [['addr' => 'b@y.test']]]);
$k2 = MailMime::threadKey(['subject' => 'RE: Fwd: AW: booking', 'from' => ['addr' => 'B@y.test'], 'to' => [['addr' => 'a@x.test']]]);
$t('no ids: subject without Re:/Fwd:/AW: and the two addresses', $k1 === $k2);
$t('a different subject is another thread', $k1 !== MailMime::threadKey(['subject' => 'Invoice', 'from' => ['addr' => 'a@x.test'], 'to' => [['addr' => 'b@y.test']]]));

// ---- MailImap ---------------------------------------------------------------------------------------------------------
echo "MailImap\n";
$msg1 = "From: a@x.test\r\nSubject: one\r\n\r\nBody {5}\r\nwith ) and \"quotes\"\r\n";
$msg2 = $crlf($alt);
$script = "* OK [CAPABILITY IMAP4rev1 AUTH=PLAIN] Dovecot ready.\r\n"
    . "A0001 OK [CAPABILITY IMAP4rev1 MOVE UIDPLUS SPECIAL-USE] Logged in\r\n"
    . "* LIST (\\HasNoChildren) \".\" INBOX\r\n"
    . "* LIST (\\HasNoChildren \\Sent) \".\" \"Sent\"\r\n"
    . "* LIST (\\HasNoChildren) \".\" \"Junk\"\r\n"
    . "* LIST (\\HasNoChildren) \".\" {15}\r\nEntw&APw-rfe \"x\r\n"
    . "* LIST (\\Noselect \\HasChildren) \".\" \"Caf&AOk-\"\r\n"
    . "A0002 OK List completed\r\n"
    . "* FLAGS (\\Answered \\Flagged \\Deleted \\Seen \\Draft)\r\n* 12 EXISTS\r\n* 0 RECENT\r\n* OK [UIDVALIDITY 1700000000] UIDs valid\r\n* OK [UIDNEXT 43] Predicted next UID\r\n"
    . "A0003 OK [READ-WRITE] Select completed\r\n"
    . "* SEARCH 42\r\nA0004 OK Search completed\r\n"
    . "* SEARCH 40 41 42\r\nA0005 OK Search completed\r\n"
    . "* 11 FETCH (UID 41 FLAGS (\\Seen) RFC822.SIZE " . strlen($msg1) . " BODY[] {" . strlen($msg1) . "}\r\n" . $msg1 . ")\r\n"
    . "* 12 FETCH (UID 42 RFC822.SIZE " . strlen($msg2) . " FLAGS () BODY[] {" . strlen($msg2) . "}\r\n" . $msg2 . ")\r\n"
    . "* 12 FETCH (FLAGS (\\Recent))\r\n"
    . "A0006 OK Fetch completed\r\n"
    . "A0007 OK Store completed\r\n"
    . "* OK [COPYUID 1 42 7] Moved\r\n* 12 EXPUNGE\r\nA0008 OK Move completed\r\n"
    . "+ Ready for literal data\r\nA0009 OK [APPENDUID 1 8] Append completed\r\n"
    . "* BYE Logging out\r\nA0010 OK Logout completed\r\n";
MailImap::$open = function (string $host, int $port, bool $tls) use ($server, $script) { return $server($script); };
$im = new MailImap(['host' => 'mail.biz.test', 'login' => 'hello@joesdiner.test', 'password' => 'p"w\\d', 'address' => 'hello@joesdiner.test']);
$im->login();
$f = $im->folders();
$t('folders: INBOX marked inbox, \\Sent marked sent', $f[0]['use'] === 'inbox' && $f[1]['use'] === 'sent', $f);
$t('folders: Junk found by its name', $f[2]['use'] === 'junk');
$t('folders: a literal name, modified UTF-7 decoded', $f[3]['name'] === 'Entwürfe "x', $f[3]['name']);
$t('folders: \\Noselect not selectable, name decoded', $f[4]['selectable'] === false && $f[4]['name'] === 'Café', $f[4]);
$sel = $im->select('INBOX');
$t('select: exists, uidvalidity, uidnext', $sel === ['exists' => 12, 'uidvalidity' => 1700000000, 'uidnext' => 43], $sel);
$t('uidsAfter: the last UID the server returns anyway is dropped', $im->uidsAfter(42) === []);
$t('uidsAfter: new ones, oldest first, capped', $im->uidsAfter(39, 2) === [40, 41]);
$got = $im->fetch([41, 42]);
$t('fetch: two messages by UID', count($got) === 2 && $got[0]['uid'] === 41 && $got[1]['uid'] === 42, array_map(fn ($r) => $r['uid'], $got));
$t('fetch: a literal holding {5}, ) and quotes read exactly', $got[0]['raw'] === $msg1, $got[0]['raw']);
$t('fetch: the big literal byte for byte, and parses', $got[1]['raw'] === $msg2 && MailMime::parse($got[1]['raw'])['subject'] === 'Café opening ☕ tomorrow');
$t('fetch: flags and size', $got[0]['flags'] === ['\\Seen'] && $got[0]['size'] === strlen($msg1) && $got[1]['flags'] === ['\\Recent'], [$got[0]['flags'], $got[1]['flags']]);
$im->setSeen(42, true);
$im->move(42, 'Archive');
$im->append('Sent', "Subject: x\nTo: a@b.test\n\nhello");
$im->logout();
$said = $sent();
$t('LOGIN quotes the password (\\ and " escaped)', str_contains($said, 'A0001 LOGIN "hello@joesdiner.test" "p\\"w\\\\d"'), $said);
$t('BODY.PEEK so reading doesn\'t mark it read', str_contains($said, 'A0006 UID FETCH 41:42 (UID FLAGS RFC822.SIZE BODY.PEEK[])'));
$t('STORE +FLAGS.SILENT (\\Seen)', str_contains($said, 'A0007 UID STORE 42 +FLAGS.SILENT (\\Seen)'));
$t('MOVE used when the server has it', str_contains($said, 'A0008 UID MOVE 42 "Archive"'));
$t('APPEND sends a literal, CRLF, after the go-ahead', str_contains($said, "A0009 APPEND \"Sent\" (\\Seen) {33}\r\nSubject: x\r\nTo: a@b.test\r\n\r\nhello\r\n"), $said);
$t('the folder name is sent in modified UTF-7', MailImap::utf7Encode('Entwürfe & Café') === 'Entw&APw-rfe &- Caf&AOk-' && MailImap::utf7Decode('Entw&APw-rfe &- Caf&AOk-') === 'Entwürfe & Café');

MailImap::$open = fn ($h, $p, $tls) => $server("* OK IMAP4rev1 ready\r\n* CAPABILITY IMAP4rev1 AUTH=PLAIN\r\nA0001 OK done\r\nA0002 NO [AUTHENTICATIONFAILED] Authentication failed.\r\n");
$e = MailImap::check(['host' => 'mail.biz.test', 'login' => 'x@biz.test', 'password' => 'wrong']);
$t('check: a refused password in plain words', $e === 'The mail server refused the password for x@biz.test (it said "Authentication failed.").', $e);
$t('check: the password isn\'t in the message', !str_contains($e, 'wrong'));
MailImap::$open = fn ($h, $p, $tls) => $server("* OK [CAPABILITY IMAP4rev1] hi\r\nA0001 NO [ALERT] Application-specific password required: https://support.google.com/accounts/answer/185833\r\n");
$t('check: Gmail\'s app password demand', str_contains(MailImap::check(['host' => 'imap.gmail.com', 'login' => 'g@gmail.test', 'password' => 'x']), 'wants an app password'));
MailImap::$open = fn ($h, $p, $tls) => $server("* OK [CAPABILITY IMAP4rev1] hi\r\nA0001 OK in\r\n* CAPABILITY IMAP4rev1\r\nA0002 OK cap\r\nA0003 NO [OVERQUOTA] Quota exceeded (mailbox for user is full)\r\n");
$t('append: a full mailbox in plain words', str_contains($throws(function () { $m = new MailImap(['host' => 'h', 'login' => 'q@b.test', 'password' => 'x']); $m->append('Sent', 'x'); }), 'q@b.test is full'));
MailImap::$open = fn ($h, $p, $tls) => $server("* OK [CAPABILITY IMAP4rev1] hi\r\nA0001 OK [CAPABILITY IMAP4rev1] in\r\nA0002 OK [READ-ONLY] examined\r\nA0003 OK bye\r\n");
$t('check: signs in and examines INBOX → \'\'', MailImap::check(['host' => 'h', 'login' => 'ok@b.test', 'password' => 'x']) === '');
MailImap::$open = function ($h, $p, $tls) use ($server, &$peer) { $c = $server("* OK [CAPABILITY IMAP4rev1] hi\r\n"); fclose($peer); return $c; };   // then it hangs up
$e = $throws(function () { $m = new MailImap(['host' => 'h.test', 'login' => 'a@b.test', 'password' => 'x']); $m->login(); }); $t('a server that hangs up is said plainly', str_contains($e, 'closed the connection'), $e);
MailImap::$open = fn ($h, $p, $tls) => $server("* OK [CAPABILITY IMAP4rev1] hi\r\nA0001 OK [CAPABILITY IMAP4rev1 IDLE] in\r\n* 3 EXISTS\r\nA0002 OK sel\r\nA0003 OK copied\r\nA0004 OK stored\r\n");
$m = new MailImap(['host' => 'h', 'login' => 'a@b.test', 'password' => 'x']);
$m->move(5, 'Trash');
$said = $sent();
$t('move without MOVE: COPY + \\Deleted, no EXPUNGE without UIDPLUS', str_contains($said, 'A0003 UID COPY 5 "Trash"') && str_contains($said, 'A0004 UID STORE 5 +FLAGS.SILENT (\\Deleted)') && !str_contains($said, 'EXPUNGE'), $said);
MailImap::$open = null;
StagingGate::$say = 'TEST MODE: ACTIVE · mail.biz.test wasn\'t called (held on staging). Nothing was sent.';
$t('staging: held before any connection', str_contains(MailImap::check(['host' => 'mail.biz.test', 'login' => 'a@b.test', 'password' => 'x']), 'held on staging') && end(StagingGate::$asked) === 'imap://mail.biz.test');
StagingGate::$say = null;

// ---- MailPop ----------------------------------------------------------------------------------------------------------
echo "MailPop\n";
$body = "From: a@x.test\r\nSubject: dots\r\n\r\n..leading dot\r\n...two dots\r\nend\r\n";
MailPop::$open = fn ($h, $p, $tls) => $server("+OK POP3 ready\r\n+OK\r\n+OK Logged in.\r\n+OK\r\n1 000001a\r\n2 000001b\r\n.\r\n+OK 60 octets\r\n" . $body . ".\r\n+OK Bye\r\n");
$pop = new MailPop(['host' => 'pop.x.test', 'login' => 'a@x.test', 'password' => 'pw']);
$pop->login();
$t('uidl: number → id', $pop->uidl() === [1 => '000001a', 2 => '000001b']);
$r = $pop->retr(1);
$t('retr: dot-unstuffed, terminator dropped', $r === "From: a@x.test\r\nSubject: dots\r\n\r\n.leading dot\r\n..two dots\r\nend\r\n", $r);
$pop->quit();
$t('never DELE (a copy stays on the server)', !str_contains($sent(), 'DELE'));
MailPop::$open = fn ($h, $p, $tls) => $server("+OK ready\r\n+OK\r\n-ERR [AUTH] Authentication failed.\r\n");
$e = MailPop::check(['host' => 'pop.x.test', 'login' => 'a@x.test', 'password' => 'pw']);
$t('check: refused password in plain words', $e === 'The mail server refused the password for a@x.test (it said "Authentication failed.").', $e);
MailPop::$open = fn ($h, $p, $tls) => $server("+OK ready\r\n+OK\r\n-ERR [IN-USE] Mailbox locked by another session\r\n");
$t('check: a mailbox in use says it will be tried again', str_contains(MailPop::check(['host' => 'h', 'login' => 'a@x.test', 'password' => 'pw']), 'busy in another mail app'));
MailPop::$open = fn ($h, $p, $tls) => $server("+OK ready\r\n+OK Begin TLS\r\n+OK\r\n+OK in\r\n+OK\r\n.\r\n+OK bye\r\n");
MailPop::$starttls = fn ($s) => true;
$t('port 110 upgrades with STLS before USER', MailPop::check(['host' => 'h', 'port' => 110, 'login' => 'a@x.test', 'password' => 'pw']) === '' && str_starts_with($sent(), "STLS\r\nUSER a@x.test\r\n"));
MailPop::$open = null; MailPop::$starttls = null;

// ---- MailSmtp ---------------------------------------------------------------------------------------------------------
echo "MailSmtp\n";
$ehlo = "250-mail.biz.test\r\n250-SIZE 52428800\r\n250-AUTH PLAIN LOGIN\r\n250 STARTTLS\r\n";
MailSmtp::$starttls = fn ($s) => true;
MailSmtp::$open = fn ($h, $p, $tls) => $server("220 mail.biz.test ESMTP\r\n" . $ehlo . "220 2.0.0 Ready to start TLS\r\n" . str_replace('250 STARTTLS', '250 HELP', $ehlo) . "535 5.7.8 Error: authentication failed: UGFzc3dvcmQ6\r\n");
$e = MailSmtp::check(['host' => 'mail.biz.test', 'port' => 587, 'login' => 'hello@joesdiner.test', 'password' => 'secret-pw']);
$t('check: wrong password said plainly', str_starts_with($e, 'The mail server refused the password for hello@joesdiner.test'), $e);
$t('check: the password isn\'t in the message', !str_contains($e, 'secret-pw'));
$said = $sent();
$t('STARTTLS before AUTH, EHLO again after', (bool) preg_match('/^EHLO \S+\r\nSTARTTLS\r\nEHLO \S+\r\nAUTH PLAIN /', $said), $said);
MailSmtp::$open = fn ($h, $p, $tls) => $server("220 hi\r\n250-hi\r\n250 AUTH LOGIN\r\n535 5.7.0 Account suspended: contact your administrator\r\n");
$t('check: a suspended mailbox said plainly (port 465, AUTH LOGIN)', str_contains(MailSmtp::check(['host' => 'h', 'port' => 465, 'login' => 's@b.test', 'password' => 'x']), 's@b.test is suspended'));
MailSmtp::$open = fn ($h, $p, $tls) => $server("220 hi\r\n250-hi\r\n250 AUTH PLAIN\r\n235 ok\r\n221 bye\r\n");
$t('check: all good → \'\'', MailSmtp::check(['host' => 'h', 'port' => 465, 'login' => 'a@b.test', 'password' => 'x']) === '');
MailSmtp::$open = fn ($h, $p, $tls) => $server("220 hi\r\n250-hi\r\n250 AUTH PLAIN\r\n235 ok\r\n250 ok\r\n250 ok\r\n250 ok\r\n354 go\r\n250 queued\r\n221 bye\r\n");
MailSmtp::send(['host' => 'h', 'port' => 465, 'login' => 'a@b.test', 'password' => 'x'], 'a@b.test', ['c@d.test', ['addr' => 'e@f.test', 'name' => 'E']], "Subject: s\n\n.dot line\nok");
$said = $sent();
$t('send: AUTH PLAIN, both recipients, dot-escaped DATA', str_contains($said, 'AUTH PLAIN ' . base64_encode("\0a@b.test\0x")) && str_contains($said, "RCPT TO:<c@d.test>\r\nRCPT TO:<e@f.test>\r\nDATA\r\nSubject: s\r\n\r\n..dot line\r\nok\r\n.\r\n"), $said);
MailSmtp::$open = fn ($h, $p, $tls) => $server("220 hi\r\n250-hi\r\n250 AUTH PLAIN\r\n235 ok\r\n250 ok\r\n550 5.1.1 No such user\r\n250 reset\r\n");
$e = $throws(fn () => MailSmtp::send(['host' => 'h', 'port' => 465, 'login' => 'a@b.test', 'password' => 'x'], 'a@b.test', ['nobody@d.test'], 'x'));
$t('send: a refused recipient named, nothing sent', str_contains($e, 'nobody@d.test') && str_contains($e, 'nothing was sent') && !str_contains($sent(), 'DATA'), $e);
$t('send: a header injection in an address refused', $throws(fn () => MailSmtp::send(['host' => 'h', 'login' => 'a', 'password' => 'x'], "a@b.test>\r\nRCPT TO:<x@y", ['c@d.test'], 'x')) !== '');
MailSmtp::$open = null; MailSmtp::$starttls = null;
$t('a closed port said plainly', MailImap::connectError('smtp.x.test', 587, 110, 'Connection timed out') === 'Port 587 on smtp.x.test is blocked or the server didn\'t answer.');
$t('an unknown server said plainly', str_contains(MailImap::connectError('nope.test', 993, 0, 'php_network_getaddresses: getaddrinfo failed'), 'wasn\'t found'));

// ---- MsGraphMail ------------------------------------------------------------------------------------------------------
echo "MsGraphMail\n";
putenv('MS_MAIL_CLIENT_ID=cid-123'); putenv('MS_MAIL_CLIENT_SECRET=shh');
$t('ready with both keys', MsGraphMail::ready());
$u = MsGraphMail::authUrl('st8');
$t('authUrl: common tenant, scopes, redirect, select_account', str_starts_with($u, 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?') && str_contains($u, 'scope=offline_access%20openid%20email%20Mail.ReadWrite%20Mail.Send%20User.Read')
    && str_contains($u, 'redirect_uri=https%3A%2F%2Ftraversence.com%2Fapi%2Fms-mail.php') && str_contains($u, 'prompt=select_account') && str_contains($u, 'state=st8'), $u);
$calls = [];
MsGraphMail::$http = function (string $m, string $url, array $h, ?string $body) use (&$calls) {
    $calls[] = [$m, $url, $body];
    if (str_ends_with($url, '/token')) {
        parse_str((string) $body, $f);
        if (($f['refresh_token'] ?? '') === 'dead') return [400, '{"error":"invalid_grant","error_description":"AADSTS700082: The refresh token has expired."}'];
        return [200, json_encode(['access_token' => 'AT2', 'refresh_token' => 'RT2', 'expires_in' => 3600])];
    }
    if (str_contains($url, '/me?')) return [200, '{"mail":"jo@contoso.test","displayName":"Jo"}'];
    if (str_contains($url, '/mailFolders/inbox/messages')) return [200, json_encode(['value' => [['id' => 'AAMk1', 'internetMessageId' => '<m1@contoso.test>', 'conversationId' => 'c1',
        'from' => ['emailAddress' => ['address' => 'ann@example.org', 'name' => 'Ann']], 'toRecipients' => [['emailAddress' => ['address' => 'jo@contoso.test', 'name' => 'Jo']]],
        'subject' => 'Hi', 'receivedDateTime' => '2026-11-18T10:00:00Z', 'bodyPreview' => 'Hello', 'body' => ['contentType' => 'html', 'content' => '<p>Hello <b>Jo</b></p>'],
        'hasAttachments' => true, 'isRead' => false]]])];
    if (str_contains($url, '/reply') || str_ends_with($url, '/sendMail')) return [202, ''];
    if ($m === 'PATCH') return [401, '{"error":{"code":"InvalidAuthenticationToken","message":"Lifetime validation failed"}}'];
    return [404, '{"error":{"code":"ErrorItemNotFound","message":"Not found"}}'];
};
$tok = MsGraphMail::exchange('code1');
$t('exchange: tokens and the address from /me', $tok['access_token'] === 'AT2' && $tok['refresh_token'] === 'RT2' && $tok['address'] === 'jo@contoso.test' && $tok['expires_at'] > time());
$tok['expires_at'] = time() - 10; $tok['access_token'] = 'old';
$list = MsGraphMail::list($tok, '2026-11-18T00:00:00Z');
$t('an expired token refreshed in place first', $tok['access_token'] === 'AT2' && $tok['expires_at'] > time());
$t('list: filter and oldest first', str_contains(urldecode($calls[count($calls) - 1][1]), '$filter=receivedDateTime ge 2026-11-18T00:00:00Z') && str_contains(urldecode($calls[count($calls) - 1][1]), 'receivedDateTime asc'));
$g = $list[0];
$t('list: MailMime\'s shape plus uid and seen', $g['uid'] === 'AAMk1' && $g['message_id'] === 'm1@contoso.test' && $g['from']['addr'] === 'ann@example.org' && $g['text'] === 'Hello Jo' && $g['seen'] === false && $g['has_attachments'] === true, $g);
MsGraphMail::send($tok, ['to' => [['addr' => 'ann@example.org', 'name' => 'Ann']], 'subject' => 'Re: Hi', 'text' => 'Thanks', 'reply_to_graph_id' => 'AAMk1']);
$t('a reply goes through /messages/{id}/reply', str_contains($calls[count($calls) - 1][1], '/me/messages/AAMk1/reply'));
MsGraphMail::send($tok, ['to' => ['ann@example.org'], 'subject' => 'New', 'html' => '<p>x</p>']);
$last = json_decode((string) $calls[count($calls) - 1][2], true);
$t('a new message through /me/sendMail, saved to Sent Items', str_ends_with($calls[count($calls) - 1][1], '/me/sendMail') && $last['saveToSentItems'] === true && $last['message']['body']['contentType'] === 'HTML');
$tok['refresh_token'] = 'dead';
$e = $throws(function () use (&$tok) { MsGraphMail::markRead($tok, 'AAMk1', true); });
$t('an expired sign-in: "… expired: reconnect it"', $e === 'Microsoft\'s sign-in for jo@contoso.test expired: reconnect it.', $e);
$tok['refresh_token'] = 'RT2'; $tok['expires_at'] = time() + 3600;
$t('a missing message said plainly', str_contains($throws(function () use (&$tok) { MsGraphMail::move($tok, 'gone', 'archive'); }), 'no longer in jo@contoso.test'));
$t('every Microsoft call asked StagingGate first', count(array_filter(StagingGate::$asked, fn ($u) => str_starts_with($u, 'https://graph.microsoft.com/') || str_starts_with($u, 'https://login.microsoftonline.com/'))) === count($calls));

echo "\n" . ($fail === 0 ? "all $pass passed\n" : "$fail FAILED, $pass passed\n");
exit($fail === 0 ? 0 : 1);
