<?php
// founder-link's proof that the dispatcher is unchanged for every kind but the Founder's (the session manager, 2026-11-27:
// "prove every other kind is unchanged, byte for byte"). tools/notify-tests/ no longer runs against live's Notify (Notices
// isn't required there, and its migrations ran and were deleted on live), so this reads the code itself: the release's
// Notify.php, with its founder.* additions taken out, is live's byte for byte; each addition is guarded by the founder.
// prefix; the email's HTML is '' unless a founder.* kind set it, so Mailer::send gets what it got before (its $html
// defaults to ''). Run: php notify-diff.php <live app/lib/Notify.php> <release app/lib/Notify.php>
declare(strict_types=1);
[$live, $rel] = [(string) @file_get_contents($argv[1] ?? ''), (string) @file_get_contents($argv[2] ?? '')];
if ($live === '' || $rel === '') { fwrite(STDERR, "usage: php notify-diff.php <live Notify.php> <release Notify.php>\n"); exit(2); }
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n\n"; } };
$adds = [
    "            // the Founder's push is its subject and one plain sentence, no address or code (Jason, 2026-11-27: \"The push is\n            // clutter with code vs a clean on brand message\"); FounderMail owns the founder.* kinds' words\n            if (str_starts_with((string) \$m['kind'], 'founder.')) { require_once __DIR__ . '/FounderMail.php'; \$pbody = FounderMail::pushLine((string) \$m['body'], (string) \$m['title']); }\n",
    "        // the Founder's emails name their links (Jason, 2026-11-27; FounderMail): an HTML part with each link as its name and\n        // Open it as a button; the text part keeps each address after its name, for mail apps without HTML\n        \$html = '';\n        if (str_starts_with((string) \$m['kind'], 'founder.')) { require_once __DIR__ . '/FounderMail.php'; [\$text, \$html] = FounderMail::emailParts((string) \$m['title'], (string) \$m['body'], \$link, \$text); }\n",
    "        // the Founder's messages with their links named in Pulse too (Jason, 2026-11-27; FounderMail)\n        'founder.batch' => ['FounderMail.php', 'FounderMail', 'pulseBody'],\n        'founder.decision' => ['FounderMail.php', 'FounderMail', 'pulseBody'],\n        'founder.message' => ['FounderMail.php', 'FounderMail', 'pulseBody'],\n",
];
foreach ($adds as $i => $a) $t('addition ' . ($i + 1) . ' is in the release once', substr_count($rel, $a) === 1);
$back = str_replace($adds, '', $rel);
$back = str_replace("\$text, \$name !== '' ? \$name : null, \$as, \$html); }", "\$text, \$name !== '' ? \$name : null, \$as); }", $back, $n);
$t('the one changed line: Mailer::send is passed $html (once)', $n === 1);
$t('with the additions taken out, the release is live\'s Notify.php byte for byte', $back === $live);
$t('every behaviour added is guarded by the founder. prefix (push, email) or keyed by a founder.* kind (Pulse)', substr_count($rel, "str_starts_with((string) \$m['kind'], 'founder.')") === 2);
$t('Mailer::send\'s $html defaults to \'\' (the call for any other kind is the call it was)', (bool) preg_match('/public static function send\(string \$toEmail, string \$subject, string \$textBody, \?string \$toName = null, string \$as = \'\', string \$html = \'\'/', (string) @file_get_contents(dirname($argv[1]) . '/Mailer.php')));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
