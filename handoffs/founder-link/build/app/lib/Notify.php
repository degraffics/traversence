<?php
// app/lib/Notify.php — the one dispatcher (decisions/0172 §4, §6 step 2). Every message the site sends a person goes
// through Notify::send: one record per recipient in comms_messages (sender, recipient, kind, level, words), routed by the
// recipient's choices, along one route written to comms_route (created → addressed → held → sent → delivered → read).
//
// Pulse is the inbox: every message lands in the person's Pulse; email only tells them something is waiting there.
// The channels in this step are Pulse and free email: system email (Mailer: Bluehost) for the new kinds, and Amazon SES
// for notices, as decisions/0137 §8 set. Push and text are later builds on the same contract (0172 §4.6).
//
// What a kind is, its level, whom it goes to and its default channels are in one register, Notify::kinds(), which the
// person's Communications setting (Dashboard → App Settings) and the admin read alike. A person's choices are in
// comms_prefs (kind × channel) and comms_quiet (quiet hours: off until they set them, 0172 §4.4).
//
// Rules from the decision kept here:
//   - No clock is set on anyone's messages (Jason, 2026-11-18): a level says how a message reaches you, never how fast
//     you must act. No new digests: the weekly notice digest (Notices::digest) stays as it is.
//   - Quiet hours hold email for Notice, General and Special; Critical, Urgent and Alert go anyway (Alert would hold
//     only push, which isn't built). Held email goes out when they end (the notify_release job).
//   - One email per conversation while it's unread: a kind sent with a `thread` adds to the unread message already in
//     the person's Pulse instead of sending another email, until they've read it. (Not a digest: nothing waits.)
//   - Never throws: a failed send is written on the route and logged; the caller's own work carries on.
declare(strict_types=1);

final class Notify
{
    /** The six levels (0172 "The six levels"): key => name. Pulse::SEVERITY takes them on in step 3. */
    public const LEVELS = ['critical' => 'Critical', 'urgent' => 'Urgent', 'alert' => 'Alert', 'notice' => 'Notice', 'general' => 'General', 'special' => 'Special'];
    /** Levels whose email waits for quiet hours to end; push waits for Alert too (0172 "What each level does"). */
    private const WAITS = ['notice', 'general', 'special'];
    private const WAITS_PUSH = ['alert', 'notice', 'general', 'special'];
    /** Levels that reach email and push whatever the person chose: Critical can't be turned off (0172). */
    private const ALWAYS = ['critical'];
    /** Levels push is on for by default, once the person has a device (Alert and up); the rest by their choice. */
    private const PUSH_DEFAULT = ['critical', 'urgent', 'alert'];
    /** The channels (push since decisions/0172 §6 step 5: our own keys, free; text is its own decision, §5). */
    public const CHANNELS = ['pulse' => 'Pulse', 'email' => 'Email', 'push' => 'Push'];

    /**
     * A message carries its own process (0172 "The message"): the part of the system that owns a kind draws its body when
     * the row opens in Pulse: [file in app/lib, class, method]; the method is pulseBody(PDO, array $message, int $viewer)
     * → ['html' => …, 'state' => one line, read live]. Its buttons act through their own endpoints (js/pulse-msg.js).
     */
    public const BODIES = [   // the alarms' kinds below are drawn in the one shape (decisions/0176 §2: ControlAlarms::pulseBody → Thread)
        'mail.failure' => ['CommsSetup.php', 'CommsSetup', 'pulseBody'],
        'mailbox.reconnect' => ['Mailboxes.php', 'Mailboxes', 'pulseBody'],
        'followup.due' => ['PulseMarks.php', 'PulseMarks', 'pulseBody'],
        'biz.comment' => ['CommentNotices.php', 'CommentNotices', 'pulseBody'],   // reply, hide, report inside the notice (0172 §2.3)
        'comment.reply' => ['CommentNotices.php', 'CommentNotices', 'pulseBody'],
        'content.comment' => ['CommentNotices.php', 'CommentNotices', 'pulseBody'],
        'booking.tomorrow' => ['Bookings.php', 'Bookings', 'pulseBody'],
        'booking.soon' => ['Bookings.php', 'Bookings', 'pulseBody'],
        'booking.changed' => ['Bookings.php', 'Bookings', 'pulseBody'],
        'biz.booking' => ['Bookings.php', 'Bookings', 'pulseBody'],
        'group.post' => ['GroupNotices.php', 'GroupNotices', 'pulseBody'],
        'group.join' => ['GroupNotices.php', 'GroupNotices', 'pulseBody'],
        'group.report' => ['GroupNotices.php', 'GroupNotices', 'pulseBody'],
        'group.mention' => ['GroupNotices.php', 'GroupNotices', 'pulseBody'],
        'alarm.trip' => ['ControlAlarms.php', 'ControlAlarms', 'pulseBody'],   // Acknowledge inside the message (0171 §7)
        'alarm.warning' => ['ControlAlarms.php', 'ControlAlarms', 'pulseBody'],
        'alarm.recovered' => ['ControlAlarms.php', 'ControlAlarms', 'pulseBody'],
        'alarm.escalated' => ['ControlAlarms.php', 'ControlAlarms', 'pulseBody'],
        // Site health on one path (Jason, 2026-11-22, check-register.md: "Failures = notifications" → one path): a finding of the
        // site audit (a build regression) is a Pulse item through the dispatcher, never a push, once per finding
        'sitehealth.finding' => ['A site audit finding (Site health)', 'A check of the site audit found something new on a page: a build regression. In Pulse only, no push; the audit\'s full list is on the System Control Board.', 'notice', 'staff', 'Team', false, true, 'system', ''],
        'upload.place_photo' => ['UploadThreads.php', 'UploadThreads', 'pulseBody'],   // people's uploads as threads (decisions/0176 §6)
        'upload.place_photo.reported' => ['UploadThreads.php', 'UploadThreads', 'pulseBody'],
        'upload.order_file' => ['UploadThreads.php', 'UploadThreads', 'pulseBody'],
        // the Founder's messages with their links named in Pulse too (Jason, 2026-11-27; FounderMail)
        'founder.batch' => ['FounderMail.php', 'FounderMail', 'pulseBody'],
        'founder.decision' => ['FounderMail.php', 'FounderMail', 'pulseBody'],
        'founder.message' => ['FounderMail.php', 'FounderMail', 'pulseBody'],
    ];

    /**
     * The kinds that aren't notices: key => [name, what it is, level, who it's for (business|staff|member), group,
     * email on by default, email can be turned off, engine (system: Bluehost; ses: Amazon SES), the team role's
     * capability that receives it (business kinds, Roles::CAN 'org')].
     * Notices (Notices::KINDS) join as 'notice.<kind>' in kinds().
     */
    public const KINDS = [
        'biz.message'  => ['A new message to your business', 'Someone wrote to a business you help run: a message, a question on an item, an offer.', 'alert', 'business', 'Your business', true, true, 'system', 'messages'],
        'biz.question' => ['A guest question', 'A visitor who isn\'t signed in asked your business a question.', 'alert', 'business', 'Your business', true, true, 'system', 'messages'],
        'biz.order'    => ['A Marketplace order for your business', 'An order or booking was placed, paid or changed by the buyer.', 'urgent', 'business', 'Your business', true, true, 'system', 'orders'],
        'biz.comment'  => ['A comment on your listing', 'Someone commented on a listing you help run.', 'general', 'business', 'Your business', false, true, 'system', 'messages'],
        // the Founder, from the site itself (Jason, 2026-11-25: "Can you use the system for emailing?"; FounderMail): the daily
        // batch, a Founder ruling (Tier 5) waiting, the session manager's messages. Email on, and not one that can be turned off.
        'founder.batch'    => ['Today\'s batch for your OK', 'The releases waiting for your approval today (decisions/0179 rule 1), with the link to approve them.', 'alert', 'staff', 'Team', true, false, 'system', ''],
        'founder.decision' => ['A Founder ruling for you (Tier 5)', 'A 4-part Decision Brief waiting for your ruling (decisions/0180): the decision, why it needs you, the options and the recommendation.', 'urgent', 'staff', 'Team', true, false, 'system', ''],
        'founder.message'  => ['A message from the session manager', 'What the session manager needs you to know or answer, sent by the site.', 'alert', 'staff', 'Team', true, false, 'system', ''],
        // staging's own news to whoever pressed (2026-11-26: "Staging is refreshed" had gone to a staff login's hidden address):
        // a fresh copy finished or stopped, a publish done or stopped (StagingSite::tellPerson)
        'staging.done'   => ['Staging: a copy or a publish finished', 'Staging was refreshed or published, or one of those stopped: what happened and where to see it.', 'notice', 'staff', 'Team', true, true, 'system', ''],
        'case.assigned'  => ['A Pulse case assigned to you', 'A teammate gave you a case to handle.', 'alert', 'staff', 'Team', true, true, 'system', ''],
        'pulse.forward'  => ['Passed on to you', 'Someone on your team passed a message on to you in Pulse.', 'notice', 'any', 'Pulse', false, true, 'system', ''],   // the row's ↗ (decisions/0172)
        'case.escalated' => ['A Pulse case escalated to you', 'A case in your department was escalated to its Manager or Director.', 'alert', 'staff', 'Team', true, true, 'system', ''],
        // the System Control Board's alarms (decisions/0171 §7, release 3a; 0172 §2.12): a trip at once, by email and push, any hour
        'alarm.trip'     => ['A system trip (the Control Board)', 'Something stopped, crashed or is held: what happened, what it means and what to do, with Acknowledge.', 'critical', 'staff', 'Team', true, false, 'system', ''],
        'alarm.warning'  => ['A system warning (no longer sent)', 'Since 2026-11-19 a warning stays on the System Control Board: the system clears it, or it becomes a trip, which reaches you at once (0171 §7).', 'urgent', 'staff', 'Team', true, true, 'system', ''],
        // a part fixed by the system: a notice with nothing to press; its tries run out, the Escalation Notice (0171 §5's note,
        // 2026-11-19; 0175 §3). Both are the trip's own message, updated in place (decisions/0176 §1)
        'alarm.recovered' => ['A part recovered by itself', 'A part failed and the system fixed it: how long it was down. Nothing to do. It\'s the same message as its trip, closed (decisions/0176).', 'notice', 'staff', 'Team', true, true, 'system', ''],
        'alarm.escalated' => ['Escalation Notice: retries unsuccessful, admin required', 'A part the system couldn\'t fix: what failed, what to do. It\'s the same message as its trip, raised; it closes as Recovered when its check passes (decisions/0175, 0176).', 'critical', 'staff', 'Team', true, false, 'system', ''],
        // decisions/0172 §6 steps 3-7 (Communications, the rest as one release, 2026-11-18)
        'biz.booking'    => ['A booking changed or cancelled', 'A customer changed or cancelled a booking or rental with your business.', 'urgent', 'business', 'Your business', true, true, 'system', 'orders'],
        'listing.held'   => ['Messages left before you claimed', 'Visitors left messages on your listing before it was claimed; they\'re delivered now.', 'alert', 'business', 'Your business', true, true, 'system', 'messages'],
        'listing.left'   => ['Your message to a listing', 'Where a message you left on a listing no one had claimed stands: sent by email, delivered, answered or expired.', 'notice', 'member', 'Messages', true, true, 'system', ''],
        'content.comment' => ['A comment on something you made', 'Someone commented on an experience you wrote.', 'general', 'member', 'Comments', false, true, 'system', ''],
        'comment.reply'  => ['A reply to your comment', 'Someone answered a comment you wrote.', 'general', 'member', 'Comments', false, true, 'system', ''],
        'booking.tomorrow' => ['A booking tomorrow', 'A reminder a day before a booking or rental of yours.', 'notice', 'member', 'Bookings', true, true, 'system', ''],
        'booking.soon'   => ['A booking in 2 hours', 'A reminder two hours before a booking of yours.', 'urgent', 'member', 'Bookings', true, true, 'system', ''],
        'booking.changed' => ['A booking changed or cancelled', 'The business changed or cancelled a booking of yours.', 'urgent', 'member', 'Bookings', true, true, 'system', ''],
        'group.post'     => ['Activity in your groups', 'A new post, message or comment in a group you joined, as your link settings say.', 'general', 'member', 'Groups', false, true, 'system', ''],
        'group.join'     => ['A request to join your group', 'Someone asked to join a group you moderate: accept or decline inside the message.', 'alert', 'member', 'Groups', true, true, 'system', ''],
        'group.report'   => ['A report in your group', 'A post or member was reported in a group you own or moderate.', 'alert', 'member', 'Groups', true, true, 'system', ''],
        'group.mention'  => ['Mentioned in a group', 'Someone mentioned you, or replied to your post, in a group.', 'alert', 'member', 'Groups', false, true, 'system', ''],
        'followup.due'   => ['A follow-up you set', 'An item you flagged to follow up is back at the top of your Pulse, unread.', 'notice', 'any', 'Pulse', false, true, 'system', ''],
        'mailbox.reconnect' => ['A connected mailbox needs you', 'A mailbox you read in Pulse refused its password, its sign-in expired or it\'s full: Reconnect is in the message.', 'alert', 'any', 'Mailboxes', true, true, 'system', ''],
        // people's uploads as threads for staff (decisions/0176 §6, release 4): Pulse first, email by choice, never a push (NO_PUSH)
        'upload.place_photo' => ['A new photo of a place', 'A visitor\'s photo of a place went live (post first): Open it, or Hide it with a reason its author sees.', 'notice', 'staff', 'Team', false, true, 'system', ''],
        'upload.place_photo.reported' => ['A place\'s photo was reported', 'Someone reported a place\'s photo: it joins the photo\'s thread, with Take it down and Leave it up.', 'alert', 'staff', 'Team', false, true, 'system', ''],
        'upload.order_file' => ['New artwork on an order', 'A file was added to an order your team works on: Open the order to see its files.', 'notice', 'staff', 'Team', false, true, 'system', ''],
        'mail.failure'   => ['The site\'s email failed', 'A sender\'s email failed, or message control paused something: Check again is in the message.', 'alert', 'staff', 'Team', true, true, 'system', ''],
    ];

    /**
     * One thread per thing (decisions/0176 §1, Jason 2026-11-20: "one thread per thing … updated in place, never re-sent"):
     * these kinds are steps of one thread, so a send for a thing already in the person's Pulse updates that message in
     * place, read or not: its kind, level, words and link change; it's unread again only when its level rises or it's
     * Critical (the push's own rule, 0172's push-polish note); email and push go as the kind's level says.
     */
    public const IN_PLACE = ['alarm.trip', 'alarm.warning', 'alarm.escalated', 'alarm.recovered', 'upload.place_photo', 'upload.place_photo.reported', 'upload.order_file'];
    /** Kinds that never send a push, whatever a person chose (decisions/0176 §6: uploads' staff threads; 0176 "no new kinds of push"). */
    public const NO_PUSH = ['upload.place_photo', 'upload.place_photo.reported', 'upload.order_file', 'sitehealth.finding'];

    /** Notices' levels (0172 §2): the rest are General. */
    private const NOTICE_LEVELS = ['group_join' => 'notice', 'group_charter' => 'notice', 'group_post' => 'alert', 'group_chapter' => 'notice', 'group_about' => 'notice',
                                   'guide_ready' => 'notice'];

    private const SITE = 'https://traversence.com';
    /** A person's push pace (2026-11-19): past this many pushes in this many minutes, the rest fold into one summary. */
    public const PUSH_PACE = 3;
    public const PUSH_WINDOW = 5;
    private const SETTINGS = 'https://traversence.com/user/dashboard.php#settings';

    public static function ready(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok === null) {
            try { foreach (['comms_messages', 'comms_route', 'comms_prefs', 'comms_quiet'] as $t) $pdo->query("SELECT 1 FROM $t LIMIT 0"); $ok = true; } catch (Throwable) { $ok = false; }
        }
        return $ok;
    }

    /** Every kind, notices included: key => [name, what, level, for, group, email default, email can be off, engine, cap]. */
    public static function kinds(): array
    {
        $k = self::KINDS;
        if (is_file(__DIR__ . '/Notices.php')) {
            require_once __DIR__ . '/Notices.php';
            foreach (Notices::KINDS as $n => [$label, $why]) $k['notice.' . $n] = [$label, $why, self::NOTICE_LEVELS[$n] ?? 'general', 'member', 'Notices', false, true, 'ses', ''];
        }
        return $k;
    }

    private static function now(): string { return date('Y-m-d H:i:s'); }

    /**
     * Sends a message of this kind. Returns how many people got a new message (or an unread one added to).
     *
     * $to     ['users' => [ids]] a person; ['entity' => id, 'except' => [ids]] a business's team, by the roles that take
     *         the kind (0102 §4, RBAC); ['staff' => [user ids]] our staff.
     * $about  'thing' (once per person, kind and thing; required), 'thread' (adds to an unread message of the same
     *         thread instead of sending another), 'url' (where it opens: a path on the site), 'pulse_ref' (when the
     *         sender's own table already shows it in Pulse, e.g. a business conversation), 'from' [type, id, name],
     *         'no_push' (a reply on a thread already pushed: Pulse and email only, e.g. a trip joining its incident, 02 row 124).
     * $words  'title', 'body'.
     */
    public static function send(PDO $pdo, string $kind, array $to, array $about, array $words): int
    {
        $kinds = self::kinds();
        if (!isset($kinds[$kind])) { error_log('Notify: unknown kind ' . $kind); return 0; }
        $def = $kinds[$kind];
        $isNotice = str_starts_with($kind, 'notice.');
        try { $users = self::recipients($pdo, $def, $to); } catch (Throwable $e) { error_log('Notify recipients: ' . $e->getMessage()); return 0; }
        if (!$users) return 0;
        $thing = mb_substr((string) ($about['thing'] ?? ''), 0, 120);
        if ($thing === '') $thing = bin2hex(random_bytes(8));
        $thread = mb_substr((string) ($about['thread'] ?? ''), 0, 120);
        $title = mb_substr(trim((string) ($words['title'] ?? '')), 0, 200);
        $body = mb_substr(trim((string) ($words['body'] ?? '')), 0, 2000);
        $url = mb_substr((string) ($about['url'] ?? ''), 0, 255);
        [$fType, $fId, $fName] = array_pad((array) ($about['from'] ?? []), 3, null);
        $entity = isset($to['entity']) ? (int) $to['entity'] : null;
        $n = 0;
        foreach ($users as $u) {
            try {
                if ($isNotice) {   // a notice keeps notices_sent's "once per person, kind and thing", and its own off switch
                    $pulseRef = self::noticeRow($pdo, $u, substr($kind, 7), $thing, $title, $body, $url);
                    if ($pulseRef === null) continue;
                } else {
                    $pulseRef = isset($about['pulse_ref']) ? mb_substr((string) $about['pulse_ref'], 0, 120) : null;
                }
                if (!self::ready($pdo)) { if ($isNotice) { $n++; self::noticeEmailOld($pdo, $u, $title, $body, $url); } continue; }   // before the update runs: as notices did
                $was = null;
                if (in_array($kind, self::IN_PLACE, true) && ($old = self::inPlace($pdo, $u, $thing))) {   // the same thread: updated where it is (0176 §1)
                    $rank = array_flip(array_keys(self::LEVELS)); $was = (string) $old['lvl'];
                    $louder = $def[2] === 'critical' || ($rank[$def[2]] ?? 9) < ($rank[$was] ?? 9);
                    $pdo->prepare('UPDATE comms_messages SET kind = ?, lvl = ?, title = ?, body = ?, url = ?, pulse_ref = ?, times = times + 1, updated_at = ?' . ($louder ? ', read_at = NULL' : '') . ' WHERE id = ?')
                        ->execute([$kind, $def[2], $title, $body, $url, isset($about['pulse_ref']) ? mb_substr((string) $about['pulse_ref'], 0, 120) : null, self::now(), (int) $old['id']]);
                    $mid = (int) $old['id']; $n++;
                    self::route($pdo, $mid, 'pulse', 'delivered', 'Updated in place: ' . (Notify::KINDS[$kind][0] ?? $kind) . ($louder ? ', unread again' : ''));
                    $quiet = self::quietNow($pdo, $u) && !in_array($def[2], ['critical', 'urgent'], true);
                    if (self::emailOn($pdo, $u, $kind)) {
                        if (($why = self::pausedFor($pdo, $kind, 'email')) !== '') self::route($pdo, $mid, 'email', 'held', $why);
                        elseif ($quiet && in_array($def[2], self::WAITS, true)) self::route($pdo, $mid, 'email', 'held', 'Quiet hours: it goes when they end');
                        else self::deliverEmail($pdo, $mid);
                    }
                    if (!empty($about['no_push'])) self::route($pdo, $mid, 'push', 'skipped', 'A reply on a thread already pushed: no second push');
                    elseif (self::pushOn($pdo, $u, $kind)) {
                        if (($why = self::pausedFor($pdo, $kind, 'push')) !== '') self::route($pdo, $mid, 'push', 'held', $why);
                        elseif ($quiet && in_array($def[2], self::WAITS_PUSH, true)) self::route($pdo, $mid, 'push', 'held', 'Quiet hours: it goes when they end');
                        else self::deliverPush($pdo, $mid, $was);
                    }
                    continue;
                }
                if ($thread !== '' && ($mid = self::unreadInThread($pdo, $u, $kind, $thread))) {
                    $pdo->prepare('UPDATE comms_messages SET title = ?, body = ?, url = ?, times = times + 1, updated_at = ? WHERE id = ?')->execute([$title, $body, $url, self::now(), $mid]);
                    self::route($pdo, $mid, 'pulse', 'delivered', 'Added to the unread message already in their Pulse; no second email');
                    $n++;
                    continue;
                }
                $ins = $pdo->prepare('INSERT IGNORE INTO comms_messages (kind, lvl, sender_type, sender_id, sender_name, to_user, to_entity, thing, thread, title, body, url, pulse_ref, created_at, updated_at)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $ins->execute([$kind, $def[2], $fType ?: 'system', $fId !== null ? (int) $fId : null, mb_substr((string) ($fName ?? ''), 0, 120), $u, $entity, $thing, $thread, $title, $body, $url, $pulseRef, self::now(), self::now()]);
                if (!$ins->rowCount()) continue;   // sent once already
                $mid = (int) $pdo->lastInsertId();
                $n++;
                self::route($pdo, $mid, 'pulse', 'created', $fName ? 'From ' . $fName : 'From the system');
                $email = self::emailOn($pdo, $u, $kind);
                $push = empty($about['no_push']) && self::pushOn($pdo, $u, $kind);
                self::route($pdo, $mid, 'pulse', 'addressed', 'Pulse' . ($email ? ', email' : '') . ($push ? ', push' : '') . ($email ? '' : ' (email off for this kind)'));
                self::route($pdo, $mid, 'pulse', 'delivered', $pulseRef ? 'Shown in Pulse by ' . strtok($pulseRef, ':') : 'In their Pulse');
                $quiet = self::quietNow($pdo, $u) && !in_array($def[2], ['critical', 'urgent'], true);
                if ($email) {
                    if (($why = self::pausedFor($pdo, $kind, 'email')) !== '') self::route($pdo, $mid, 'email', 'held', $why);
                    elseif ($quiet && in_array($def[2], self::WAITS, true)) self::route($pdo, $mid, 'email', 'held', 'Quiet hours: it goes when they end');
                    else self::deliverEmail($pdo, $mid);
                }
                if ($push) {
                    if (($why = self::pausedFor($pdo, $kind, 'push')) !== '') self::route($pdo, $mid, 'push', 'held', $why);
                    elseif ($quiet && in_array($def[2], self::WAITS_PUSH, true)) self::route($pdo, $mid, 'push', 'held', 'Quiet hours: it goes when they end');
                    else self::deliverPush($pdo, $mid);
                }
            } catch (Throwable $e) { error_log('Notify ' . $kind . ' to ' . $u . ': ' . $e->getMessage()); }
        }
        return $n;
    }

    /** This person's message on a thing that's one thread (IN_PLACE), newest first, or null. */
    private static function inPlace(PDO $pdo, int $u, string $thing): ?array
    {
        $st = $pdo->prepare('SELECT id, lvl FROM comms_messages WHERE to_user = ? AND thing = ? AND kind IN (' . implode(',', array_fill(0, count(self::IN_PLACE), '?')) . ') ORDER BY id DESC LIMIT 1');
        $st->execute(array_merge([$u, $thing], self::IN_PLACE));
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Who gets it: user ids, without the sender, suspended accounts left out. */
    private static function recipients(PDO $pdo, array $def, array $to): array
    {
        $ids = [];
        if (isset($to['users'])) $ids = (array) $to['users'];
        elseif (isset($to['staff'])) $ids = (array) $to['staff'];
        elseif (isset($to['entity'])) {
            require_once __DIR__ . '/Roles.php';
            $cap = (string) $def[8];
            foreach (Roles::team($pdo, 'org', 'entity', (string) (int) $to['entity']) as $m) {
                $caps = Roles::CAN['org'][$m['role']] ?? [];
                $takes = in_array('*', $caps, true) || in_array($cap, $caps, true) || ($cap === 'orders' && (in_array('messages', $caps, true) || in_array('billing', $caps, true)));
                if ($takes) $ids[] = (int) $m['user_id'];
            }
        }
        $ids = array_values(array_diff(array_unique(array_filter(array_map('intval', $ids), fn ($i) => $i > 0)), array_map('intval', (array) ($to['except'] ?? []))));
        if (!$ids) return [];
        $st = $pdo->query('SELECT id FROM users WHERE is_suspended = 0 AND id IN (' . implode(',', $ids) . ')');
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** A notice's own row (notices_sent), unless the person switched the kind off. Its Pulse reference, or null. */
    private static function noticeRow(PDO $pdo, int $u, string $nkind, string $thing, string $title, string $body, string $url): ?string
    {
        require_once __DIR__ . '/Notices.php';
        if (!Notices::ready($pdo)) return null;
        $st = $pdo->prepare('SELECT 1 FROM notice_prefs WHERE user_id = ? AND kind = ?'); $st->execute([$u, $nkind]);
        if ($st->fetchColumn() !== false) return null;
        $ins = $pdo->prepare('INSERT IGNORE INTO notices_sent (user_id, kind, thing, title, body, url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([$u, $nkind, mb_substr($thing, 0, 80), mb_substr($title, 0, 200), mb_substr($body, 0, 400), mb_substr($url, 0, 255), self::now()]);
        if (!$ins->rowCount()) return null;   // once, as before
        return 'notices:' . (int) $pdo->lastInsertId();
    }

    /** Before the update has run: a notice is emailed exactly as Notices did (by SES, to those who chose email). */
    private static function noticeEmailOld(PDO $pdo, int $u, string $title, string $body, string $url): void
    {
        require_once __DIR__ . '/Notices.php';
        if (Notices::emailOn($pdo) && Notices::delivery($pdo, $u) === 'email') Notices::emailOne($pdo, $u, $title, $body, $url);
    }

    private static function unreadInThread(PDO $pdo, int $u, string $kind, string $thread): int
    {
        $st = $pdo->prepare('SELECT id FROM comms_messages WHERE to_user = ? AND kind = ? AND thread = ? AND read_at IS NULL ORDER BY id DESC LIMIT 1');
        $st->execute([$u, $kind, $thread]);
        return (int) ($st->fetchColumn() ?: 0);
    }

    /** One stage of a message's route. */
    public static function route(PDO $pdo, int $mid, string $via, string $stage, string $detail = '', string $providerId = ''): void
    {
        try {
            $pdo->prepare('INSERT INTO comms_route (message_id, via, stage, detail, provider_id, happened_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$mid, $via, $stage, mb_substr($detail, 0, 255), mb_substr($providerId, 0, 120), self::now()]);
        } catch (Throwable $e) { error_log('Notify route: ' . $e->getMessage()); }
    }

    // ---- the person's choices: kind × channel, quiet hours ------------------------------------------------------------

    /** Whether this kind reaches this person by email: their choice, else the kind's default, and only if the engine works. */
    public static function emailOn(PDO $pdo, int $u, string $kind): bool
    {
        $def = self::kinds()[$kind] ?? null;
        if (!$def) return false;
        if ($def[7] === 'ses') {
            require_once __DIR__ . '/Notices.php';
            if (!Notices::emailOn($pdo)) return false;
            if (self::noticesStopped($pdo, $u)) return false;   // "Stop these" in a notice email stops them all (SesMail::unsubscribe)
        }
        if (!$def[6] || in_array($def[2], self::ALWAYS, true)) return true;   // can't be turned off: Critical, or the kind's own rule
        if (self::ready($pdo)) {
            $st = $pdo->prepare('SELECT is_on FROM comms_prefs WHERE user_id = ? AND kind = ? AND via = ?'); $st->execute([$u, $kind, 'email']);
            $v = $st->fetchColumn();
            if ($v !== false) return (bool) (int) $v;
        }
        if ($def[7] === 'ses') return Notices::delivery($pdo, $u) === 'email';   // notices: the "by email as they happen" choice from before
        return (bool) $def[5];
    }

    /** Whether this kind reaches this person by push: only with a device; then their choice, else on for Alert and up. */
    public static function pushOn(PDO $pdo, int $u, string $kind): bool
    {
        $def = self::kinds()[$kind] ?? null;
        if (!$def || in_array($kind, self::NO_PUSH, true) || !self::hasDevice($pdo, $u)) return false;
        if (in_array($def[2], self::ALWAYS, true)) return true;
        if (self::ready($pdo)) {
            $st = $pdo->prepare('SELECT is_on FROM comms_prefs WHERE user_id = ? AND kind = ? AND via = ?'); $st->execute([$u, $kind, 'push']);
            $v = $st->fetchColumn();
            if ($v !== false) return (bool) (int) $v;
        }
        return in_array($def[2], self::PUSH_DEFAULT, true);
    }

    private static function hasDevice(PDO $pdo, int $u): bool
    {
        try { $st = $pdo->prepare('SELECT 1 FROM push_subs WHERE user_id = ? AND failed_at IS NULL LIMIT 1'); $st->execute([$u]); return (bool) $st->fetchColumn(); } catch (Throwable) { return false; }
    }

    /** Message control (0172 §4.3): why a kind or a channel is paused now, or ''. */
    private static function pausedFor(PDO $pdo, string $kind, string $via): string
    {
        if (!is_file(__DIR__ . '/CommsSetup.php')) return '';
        require_once __DIR__ . '/CommsSetup.php';
        if (CommsSetup::paused($pdo, 'kind:' . $kind)) return 'Paused by message control: this kind';
        if (CommsSetup::paused($pdo, 'channel:' . $via)) return 'Paused by message control: ' . $via;
        return '';
    }

    /** Pushes one message now to the person's devices (WebPush) and writes how it went on its route. */
    private static function deliverPush(PDO $pdo, int $mid, ?string $wasLevel = null): bool
    {
        $st = $pdo->prepare('SELECT * FROM comms_messages WHERE id = ?'); $st->execute([$mid]);
        $m = $st->fetch(PDO::FETCH_ASSOC);
        if (!$m) return false;
        try {
            require_once __DIR__ . '/WebPush.php';
            $url = str_starts_with((string) $m['url'], 'http') ? (string) $m['url'] : self::SITE . ((string) $m['url'] !== '' ? $m['url'] : '/user/dashboard.php#pulse');
            $u = (int) $m['to_user']; $lvl = (string) $m['lvl'];
            // one notification per thing (2026-11-19, Chrome's "possible spam" on Jason's phone): its tag is the thing, so a
            // repeat or an update replaces it; it alerts again only when the level rises, or for Critical (alarm.escalated)
            $tag = 't' . substr(sha1($u . '|' . (string) $m['thing']), 0, 12);
            $prev = $pdo->prepare('SELECT lvl FROM comms_messages WHERE to_user = ? AND thing = ? AND id < ? ORDER BY id DESC LIMIT 1'); $prev->execute([$u, (string) $m['thing'], $mid]);
            $was = $wasLevel ?? $prev->fetchColumn();   // a thread updated in place passes the level it had (0176 §1)
            $rank = array_flip(array_keys(self::LEVELS));   // critical 0 … special 5: lower is louder
            $renotify = $lvl === 'critical' || ($was !== false && ($rank[$lvl] ?? 9) < ($rank[(string) $was] ?? 9));
            // a thread's push is its title and status only (decisions/0176 §2): the status is the first line of its words
            $pbody = in_array((string) $m['kind'], self::IN_PLACE, true) ? (string) strtok((string) $m['body'], "\n") : (string) $m['body'];
            // the Founder's push is its subject and one plain sentence, no address or code (Jason, 2026-11-27: "The push is
            // clutter with code vs a clean on brand message"); FounderMail owns the founder.* kinds' words
            if (str_starts_with((string) $m['kind'], 'founder.')) { require_once __DIR__ . '/FounderMail.php'; $pbody = FounderMail::pushLine((string) $m['body']); }
            // an alarm's push: "SYSTEM ALARM: <item>", its body starting with the action (decisions/0176's note, 2026-11-20:
            // "Push shortened"; a phone cuts a title at about 40 characters)
            // its action may hold a colon ("Down: the system is still trying", the one engine's §5.4), so it's matched by name
            $ptitle = preg_match('/^SYSTEM ALARM NOTICE: (.+?): (?:Paused by the system|Down: the system is still trying|Tripped: nothing is held|Retries unsuccessful, now admin required|Recovered|Closed|Fixed by .+)$/u', (string) $m['title'], $nm) ? 'SYSTEM ALARM: ' . $nm[1] : (string) $m['title'];
            $msg = ['title' => $ptitle, 'body' => mb_substr($pbody, 0, 240), 'url' => $url,
                    'stop' => self::stopUrl($mid, $u), 'tag' => $tag, 'renotify' => $renotify, 'level' => $lvl];
            // a pace per person: past PUSH_PACE pushes in PUSH_WINDOW minutes the rest fold into one summary that updates
            // quietly ("4 new in your Pulse"); Critical always goes on its own. Nothing is dropped: each is still in Pulse
            // and email, and its route says it went in the summary.
            $summary = 0;
            if ($lvl !== 'critical') {
                $since = gmdate('Y-m-d H:i:s', time() - self::PUSH_WINDOW * 60);
                $c = $pdo->prepare("SELECT SUM(r.detail NOT LIKE 'In a summary%') AS one, SUM(r.detail LIKE 'In a summary%') AS folded FROM comms_route r JOIN comms_messages x ON x.id = r.message_id
                                    WHERE x.to_user = ? AND r.via = 'push' AND r.stage = 'sent' AND r.happened_at >= ? AND r.message_id <> ?");
                $c->execute([$u, $since, $mid]); $cnt = $c->fetch(PDO::FETCH_ASSOC) ?: [];
                if ((int) ($cnt['one'] ?? 0) + (int) ($cnt['folded'] ?? 0) >= self::PUSH_PACE) {
                    $summary = (int) ($cnt['folded'] ?? 0) + 1;
                    $msg = ['title' => $summary . ' new in your Pulse', 'body' => 'The latest: ' . mb_substr((string) $m['title'], 0, 200), 'url' => self::SITE . '/user/dashboard.php#pulse',
                            'stop' => self::stopUrl($mid, $u), 'tag' => 'summary', 'renotify' => false, 'level' => 'notice'];
                }
            }
            $r = WebPush::sendToUser($pdo, $u, $msg);
            if (!empty($r['none'])) { self::route($pdo, $mid, 'push', 'skipped', 'No device to push to'); return false; }
            if (($r['held'] ?? 0) > 0 && !($r['sent'] ?? 0)) { self::route($pdo, $mid, 'push', 'held', 'Held on staging'); return false; }
            $ok = (int) ($r['sent'] ?? 0) > 0;
            self::route($pdo, $mid, 'push', $ok ? 'sent' : 'failed', $ok ? ($summary ? 'In a summary push (' . $summary . ' new in ' . self::PUSH_WINDOW . ' minutes), to ' : 'Pushed to ') . (int) $r['sent'] . ' device' . ((int) $r['sent'] === 1 ? '' : 's') . ((int) ($r['failed'] ?? 0) ? ', ' . (int) $r['failed'] . ' failed' : '') : 'No device took it');
            return $ok;
        } catch (Throwable $e) { self::route($pdo, $mid, 'push', 'failed', mb_substr($e->getMessage(), 0, 200)); return false; }
    }

    /** A kind's body for Pulse (BODIES), or null when the kind has none: then its title, words and link are shown. */
    public static function body(PDO $pdo, array $m, int $viewer): ?array
    {
        $b = self::BODIES[(string) $m['kind']] ?? null;
        if (!$b || !is_file(__DIR__ . '/' . $b[0])) return null;
        try { require_once __DIR__ . '/' . $b[0]; return ($b[1] . '::' . $b[2])($pdo, $m, $viewer); }
        catch (Throwable $e) { error_log('Notify body ' . $m['kind'] . ': ' . $e->getMessage()); return null; }
    }

    /** One of this person's messages, or null (Pulse opens a row with it). */
    public static function one(PDO $pdo, int $u, int $mid): ?array
    {
        if (!self::ready($pdo)) return null;
        $st = $pdo->prepare('SELECT * FROM comms_messages WHERE id = ? AND to_user = ?'); $st->execute([$mid, $u]);
        $m = $st->fetch(PDO::FETCH_ASSOC);
        return $m ?: null;
    }

    /** Whether this person stopped notice emails with "Stop these" (an unsubscribe on their address, scope notices). */
    private static function noticesStopped(PDO $pdo, int $u): bool
    {
        $addr = self::addressOf($pdo, $u, false)[0];
        if ($addr === '' || !class_exists('SesMail')) return false;
        try { return SesMail::suppressed($pdo, SesMail::hash($addr), 'notices'); } catch (Throwable) { return false; }
    }

    /** Quiet hours: ['from' => minutes, 'to' => minutes, 'tz' => zone] or null (off). */
    public static function quiet(PDO $pdo, int $u): ?array
    {
        if (!self::ready($pdo)) return null;
        $st = $pdo->prepare('SELECT quiet_from, quiet_to, tz FROM comms_quiet WHERE user_id = ?'); $st->execute([$u]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? ['from' => (int) $r['quiet_from'], 'to' => (int) $r['quiet_to'], 'tz' => (string) $r['tz']] : null;
    }

    public static function quietNow(PDO $pdo, int $u, ?int $at = null): bool
    {
        $q = self::quiet($pdo, $u);
        if (!$q || $q['from'] === $q['to']) return false;
        try { $tz = new DateTimeZone($q['tz'] !== '' ? $q['tz'] : date_default_timezone_get()); } catch (Throwable) { $tz = new DateTimeZone('UTC'); }
        $d = (new DateTimeImmutable('@' . ($at ?? time())))->setTimezone($tz);
        $m = (int) $d->format('G') * 60 + (int) $d->format('i');
        return $q['from'] < $q['to'] ? ($m >= $q['from'] && $m < $q['to']) : ($m >= $q['from'] || $m < $q['to']);
    }

    /** The Communications setting: every kind this person can get, with their choices; quiet hours. */
    public static function settings(PDO $pdo, int $u, array $forWho): array
    {
        require_once __DIR__ . '/Notices.php';
        $offPulse = [];
        if (Notices::ready($pdo)) { $st = $pdo->prepare('SELECT kind FROM notice_prefs WHERE user_id = ?'); $st->execute([$u]); $offPulse = array_flip($st->fetchAll(PDO::FETCH_COLUMN)); }
        $out = [];
        foreach (self::kinds() as $k => $d) {
            if ($d[3] !== 'any' && !in_array($d[3], $forWho, true)) continue;
            $notice = str_starts_with($k, 'notice.');
            $engineOn = $d[7] !== 'ses' || Notices::emailOn($pdo);
            $out[] = ['kind' => $k, 'name' => $d[0], 'what' => $d[1], 'level' => self::LEVELS[$d[2]], 'group' => $d[4],
                'pulse' => $notice ? !isset($offPulse[substr($k, 7)]) : true,
                'pulse_fixed' => $notice ? '' : 'Messages and work always reach your Pulse, so nothing that needs an answer is lost.',
                'email' => $engineOn && self::emailOn($pdo, $u, $k),
                'email_fixed' => !$engineOn ? 'Notices by email aren\'t switched on yet.' : (!$d[6] || in_array($d[2], self::ALWAYS, true) ? 'This one always comes by email: it\'s about your account\'s safety.' : ''),
                'push' => !in_array($k, self::NO_PUSH, true) && self::pushChoice($pdo, $u, $k),
                'push_fixed' => in_array($k, self::NO_PUSH, true) ? 'This one never comes by push: it waits in your Pulse (and your email, if you choose).' : (in_array($d[2], self::ALWAYS, true) ? 'Critical messages always come by push on your devices.' : '')];
        }
        $q = self::quiet($pdo, $u);
        $digest = Notices::deliveryReady($pdo) && Notices::delivery($pdo, $u) === 'digest';
        return ['kinds' => $out, 'quiet' => $q, 'digest' => $digest, 'digest_on' => Notices::emailOn($pdo), 'ready' => self::ready($pdo), 'devices' => self::hasDevice($pdo, $u)];
    }

    /** The push choice as the setting shows it, whether or not a device is on yet (it applies once one is). */
    private static function pushChoice(PDO $pdo, int $u, string $kind): bool
    {
        $def = self::kinds()[$kind] ?? null;
        if (!$def) return false;
        if (in_array($def[2], self::ALWAYS, true)) return true;
        if (self::ready($pdo)) { $st = $pdo->prepare('SELECT is_on FROM comms_prefs WHERE user_id = ? AND kind = ? AND via = ?'); $st->execute([$u, $kind, 'push']); $v = $st->fetchColumn(); if ($v !== false) return (bool) (int) $v; }
        return in_array($def[2], self::PUSH_DEFAULT, true);
    }

    /** Sets one kind × channel. Pulse for notices is their old off switch (notice_prefs); other kinds always reach Pulse. */
    public static function setChannel(PDO $pdo, int $u, string $kind, string $via, bool $on): void
    {
        $def = self::kinds()[$kind] ?? null;
        if (!$def) throw new InvalidArgumentException('Unknown kind of message.');
        if ($via === 'pulse') {
            if (!str_starts_with($kind, 'notice.')) throw new InvalidArgumentException('Messages and work always reach your Pulse.');
            require_once __DIR__ . '/Notices.php';
            Notices::setPref($pdo, $u, substr($kind, 7), $on);
            return;
        }
        if ($via === 'push') {
            if (in_array($def[2], self::ALWAYS, true)) throw new InvalidArgumentException('Critical messages always come by push on your devices.');
            if (!self::ready($pdo)) throw new InvalidArgumentException('These choices switch on once the site\'s update has run.');
            $pdo->prepare('INSERT INTO comms_prefs (user_id, kind, via, is_on, changed_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_on = VALUES(is_on), changed_at = VALUES(changed_at)')
                ->execute([$u, $kind, 'push', $on ? 1 : 0, self::now()]);
            return;
        }
        if ($via !== 'email') throw new InvalidArgumentException('Choose Pulse, email or push.');
        if (!$def[6] || in_array($def[2], self::ALWAYS, true)) throw new InvalidArgumentException('This one always comes by email.');
        if (!self::ready($pdo)) throw new InvalidArgumentException('These choices switch on once the site\'s update has run.');
        if ($on && $def[7] === 'ses' && ($addr = self::addressOf($pdo, $u, false)[0]) !== '' && is_file(__DIR__ . '/SesMail.php')) {
            require_once __DIR__ . '/SesMail.php';   // signed in and asking again: an earlier "Stop these" is lifted
            try { $pdo->prepare("DELETE FROM email_suppress WHERE addr_hash = ? AND scope = 'notices' AND reason = 'unsubscribe'")->execute([SesMail::hash($addr)]); } catch (Throwable) {}
        }
        $pdo->prepare('INSERT INTO comms_prefs (user_id, kind, via, is_on, changed_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_on = VALUES(is_on), changed_at = VALUES(changed_at)')
            ->execute([$u, $kind, 'email', $on ? 1 : 0, self::now()]);
    }

    /**
     * The weekly email of notices (Notices::digest, as it is; no new digests) on or off. Whoever had chosen "by email as
     * they happen" keeps each notice's email as it was: it's written per kind first, so turning the digest on or off
     * changes nothing else.
     */
    public static function setDigest(PDO $pdo, int $u, bool $on): void
    {
        require_once __DIR__ . '/Notices.php';
        if (!Notices::deliveryReady($pdo)) throw new InvalidArgumentException('The weekly email isn\'t switched on yet.');
        if (self::ready($pdo) && Notices::delivery($pdo, $u) === 'email') {
            $ins = $pdo->prepare('INSERT IGNORE INTO comms_prefs (user_id, kind, via, is_on, changed_at) VALUES (?, ?, ?, 1, ?)');
            foreach (array_keys(Notices::KINDS) as $k) $ins->execute([$u, 'notice.' . $k, 'email', self::now()]);
        }
        Notices::setDelivery($pdo, $u, $on ? 'digest' : 'pulse');
    }

    /** Quiet hours on ("HH:MM" to "HH:MM", in the device's zone) or off (null). */
    public static function setQuiet(PDO $pdo, int $u, ?string $from, ?string $to, string $tz): void
    {
        if (!self::ready($pdo)) throw new InvalidArgumentException('Quiet hours switch on once the site\'s update has run.');
        if ($from === null) { $pdo->prepare('DELETE FROM comms_quiet WHERE user_id = ?')->execute([$u]); return; }
        $m = function (?string $t): int {
            if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', (string) $t, $x)) throw new InvalidArgumentException('Choose a start and an end time.');
            return (int) $x[1] * 60 + (int) $x[2];
        };
        $f = $m($from); $t = $m($to);
        if ($f === $t) throw new InvalidArgumentException('Quiet hours need a different start and end.');
        try { new DateTimeZone($tz); } catch (Throwable) { $tz = ''; }
        $pdo->prepare('INSERT INTO comms_quiet (user_id, quiet_from, quiet_to, tz, changed_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quiet_from = VALUES(quiet_from), quiet_to = VALUES(quiet_to), tz = VALUES(tz), changed_at = VALUES(changed_at)')
            ->execute([$u, $f, $t, mb_substr($tz, 0, 64), self::now()]);
    }

    // ---- delivering by email --------------------------------------------------------------------------------------

    /** Emails one message now, by its kind's engine, and writes how it went on its route. */
    private static function deliverEmail(PDO $pdo, int $mid): bool
    {
        $st = $pdo->prepare('SELECT * FROM comms_messages WHERE id = ?'); $st->execute([$mid]);
        $m = $st->fetch(PDO::FETCH_ASSOC);
        if (!$m) return false;
        $def = self::kinds()[(string) $m['kind']] ?? null;
        if (!$def) return false;
        $u = (int) $m['to_user'];
        if ($def[7] === 'ses') {   // notices: Amazon SES, as decisions/0137 §8 set
            require_once __DIR__ . '/Notices.php';
            $ok = Notices::emailOne($pdo, $u, (string) $m['title'], (string) $m['body'], (string) $m['url']);
            self::route($pdo, $mid, 'email', $ok ? 'sent' : 'failed', $ok ? 'By Amazon SES (notices)' : 'Amazon SES didn\'t take it (see the error log)');
            return $ok;
        }
        [$addr, $name] = self::addressOf($pdo, $u, $def[3] === 'staff');
        if ($addr === '') { self::route($pdo, $mid, 'email', 'failed', 'No email address on the account'); return false; }
        $link = str_starts_with((string) $m['url'], 'http') ? (string) $m['url'] : self::SITE . ((string) $m['url'] !== '' ? $m['url'] : '/user/dashboard.php#pulse');
        $text = trim((string) $m['title']) . "\n\n" . ((string) $m['body'] !== '' ? $m['body'] . "\n\n" : '')
              // a thread updated in place is one story, never a count of copies (02 §5.3; g4 F8)
              . ((int) $m['times'] > 1 && !in_array((string) $m['kind'], self::IN_PLACE, true) ? '(' . (int) $m['times'] . " of these are waiting.)\n\n" : '')
              . 'Open it: ' . $link . "\n\n"
              . '-- ' . "\n" . ($def[3] === 'staff'
                  ? 'You get this email because of your choices for "' . $def[0] . '". Change them: https://traversence.com/admin/dashboard/comms'
                  : 'You get this email because of your choices for "' . $def[0] . '".' . "\nStop these: " . self::stopUrl($mid, $u) . "\nChange what reaches you, and how: " . self::SETTINGS) . "\nTraversence";
        // the Founder's emails name their links (Jason, 2026-11-27; FounderMail): an HTML part with each link as its name and
        // Open it as a button; the text part keeps each address after its name, for mail apps without HTML
        $html = '';
        if (str_starts_with((string) $m['kind'], 'founder.')) { require_once __DIR__ . '/FounderMail.php'; [$text, $html] = FounderMail::emailParts((string) $m['title'], (string) $m['body'], $link, $text); }
        try {
            require_once __DIR__ . '/Mailer.php';
            $flag = property_exists('Mailer', 'routed');   // Mailer logs its own sends; this message's route is written here instead
            if ($flag) Mailer::$routed = true;
            $as = '';
            if (is_file(__DIR__ . '/CommsSetup.php')) { require_once __DIR__ . '/CommsSetup.php'; $as = CommsSetup::prefixFor('notices', $pdo); }   // the sender routing gives "Account notices" (0167 §1)
            // an alarm's notice is one email thread (decisions/0176's notes, 2026-11-20): its subject is the notice itself
            // ("SYSTEM ALARM NOTICE: <item>: <action>"), the first email starts the thread and each later state (the Escalation
            // Notice, Recovered) replies in it (In-Reply-To and References to the first)
            $alarm = str_starts_with((string) $m['kind'], 'alarm.') && in_array((string) $m['kind'], self::IN_PLACE, true);
            if ($alarm && property_exists('Mailer', 'thread')) {
                $sent = 0; try { $c = $pdo->prepare("SELECT COUNT(*) FROM comms_route WHERE message_id = ? AND via = 'email' AND stage = 'sent'"); $c->execute([$mid]); $sent = (int) $c->fetchColumn(); } catch (Throwable) {}
                $root = '<tv-alarm-' . $mid . '@traversence.com>';
                Mailer::$thread = $sent ? ['id' => '<tv-alarm-' . $mid . '-' . ($sent + 1) . '@traversence.com>', 'parent' => $root] : ['id' => $root];
            }
            try { Mailer::send($addr, $alarm ? (string) $m['title'] : (string) $m['title'] . ' · Traversence', $text, $name !== '' ? $name : null, $as, $html); }
            finally { if ($flag) Mailer::$routed = false; if (property_exists('Mailer', 'thread')) Mailer::$thread = null; }
            self::route($pdo, $mid, 'email', 'sent', 'System email (Bluehost) to ' . self::maskAddr($addr));
            return true;
        } catch (Throwable $e) {
            self::route($pdo, $mid, 'email', 'failed', mb_substr($e->getMessage(), 0, 200));
            error_log('Notify email ' . $mid . ': ' . $e->getMessage());
            return false;
        }
    }

    // ---- "Stop these": one tap in an email stops that kind by email (decisions/0141 §5; /email/stop.php) -----------------

    private static function linkKey(): string
    {
        $k = getenv('EMAIL_LINK_KEY');
        return $k !== false && trim((string) $k) !== '' ? 'notify|' . trim((string) $k) : hash('sha256', 'tv-notify|' . (getenv('DB_PASSWORD') ?: '') . '|' . dirname(__DIR__));
    }

    public static function stopUrl(int $mid, int $u): string
    {
        return self::SITE . '/email/stop.php?t=' . $mid . '-' . substr(hash_hmac('sha256', $mid . '|' . $u, self::linkKey()), 0, 24);
    }

    /** The message a stop link is for, or null: [id, to_user, kind, name of the kind]. */
    public static function fromStop(PDO $pdo, string $t): ?array
    {
        if (!self::ready($pdo) || !preg_match('/^(\d+)-([a-f0-9]{24})$/', $t, $m)) return null;
        $st = $pdo->prepare('SELECT id, to_user, kind FROM comms_messages WHERE id = ?'); $st->execute([(int) $m[1]]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r || !hash_equals(substr(hash_hmac('sha256', (int) $r['id'] . '|' . (int) $r['to_user'], self::linkKey()), 0, 24), $m[2])) return null;
        $def = self::kinds()[(string) $r['kind']] ?? null;
        return $def ? ['id' => (int) $r['id'], 'to_user' => (int) $r['to_user'], 'kind' => (string) $r['kind'], 'name' => $def[0], 'can' => (bool) $def[6]] : null;
    }

    /** Stops this kind by email for that person (Pulse keeps it). */
    public static function stop(PDO $pdo, array $r): void
    {
        if (!$r['can']) return;
        $pdo->prepare('INSERT INTO comms_prefs (user_id, kind, via, is_on, changed_at) VALUES (?, ?, ?, 0, ?) ON DUPLICATE KEY UPDATE is_on = 0, changed_at = VALUES(changed_at)')
            ->execute([$r['to_user'], $r['kind'], 'email', self::now()]);
        self::route($pdo, $r['id'], 'email', 'stopped', 'Stop these: no more of this kind by email');
    }

    /** [email, name]: a staff member's own mailbox where they have one, else the account's email. */
    private static function addressOf(PDO $pdo, int $u, bool $staff): array
    {
        if ($staff) {
            try {
                $st = $pdo->prepare("SELECT email, name FROM " . Database::t('staff_accounts') . " WHERE user_id = ? AND status = 'active' LIMIT 1"); $st->execute([$u]);
                if (($s = $st->fetch(PDO::FETCH_ASSOC)) && trim((string) $s['email']) !== '') return [trim((string) $s['email']), (string) $s['name']];
            } catch (Throwable) {}
        }
        $st = $pdo->prepare('SELECT email, display_name FROM users WHERE id = ? AND is_suspended = 0'); $st->execute([$u]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return [trim((string) ($r['email'] ?? '')), (string) ($r['display_name'] ?? '')];
    }

    /** The route keeps where it went, not the whole address: j***@example.com. */
    private static function maskAddr(string $a): string
    {
        [$l, $d] = array_pad(explode('@', $a, 2), 2, '');
        return mb_substr($l, 0, 1) . '***@' . $d;
    }

    /**
     * The notify_release job: email held for quiet hours goes once they end. A message read in Pulse meanwhile isn't
     * emailed (it's been seen). Returns how many went.
     */
    public static function releaseHeld(PDO $pdo, int $limit = 200): int
    {
        if (!self::ready($pdo)) return 0;
        $st = $pdo->query("SELECT r.message_id, r.via, m.to_user, m.read_at FROM comms_route r JOIN comms_messages m ON m.id = r.message_id
                            WHERE r.via IN ('email','push') AND r.stage = 'held' AND r.detail LIKE 'Quiet hours%'
                              AND NOT EXISTS (SELECT 1 FROM comms_route x WHERE x.message_id = r.message_id AND x.via = r.via AND x.id > r.id)
                            ORDER BY r.id LIMIT " . max(1, $limit));
        $sent = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $h) {
            $mid = (int) $h['message_id'];
            $via = (string) $h['via'];
            if ($h['read_at'] !== null) { self::route($pdo, $mid, $via, 'skipped', 'Read in Pulse during quiet hours, so not ' . ($via === 'push' ? 'pushed' : 'emailed')); continue; }
            if (self::quietNow($pdo, (int) $h['to_user'])) continue;
            if ($via === 'push' ? self::deliverPush($pdo, $mid) : self::deliverEmail($pdo, $mid)) $sent++;
        }
        return $sent;
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * This person's messages that Pulse shows from here (those another table shows, pulse_ref, are left to it), newest
     * first: [{id, kind, level, title, body, url, when, unread, from, times}]. $entity: only those for that business.
     */
    public static function forUser(PDO $pdo, int $u, ?int $entity = null, int $limit = 30): array
    {
        if (!self::ready($pdo)) return [];
        $st = $pdo->prepare('SELECT id, kind, lvl, title, body, url, sender_name, times, updated_at, read_at FROM comms_messages
                              WHERE to_user = ? AND pulse_ref IS NULL AND ' . ($entity === null ? 'to_entity IS NULL' : 'to_entity = ' . (int) $entity) . ' ORDER BY updated_at DESC LIMIT ' . max(1, min(100, $limit)));
        $st->execute([$u]);
        return array_map(fn ($r) => ['id' => (int) $r['id'], 'kind' => (string) $r['kind'], 'level' => (string) $r['lvl'], 'title' => (string) $r['title']
            . ((int) $r['times'] > 1 ? ' (' . (int) $r['times'] . ')' : ''), 'body' => (string) $r['body'], 'url' => (string) $r['url'] ?: '#',
            'when' => (string) $r['updated_at'], 'unread' => $r['read_at'] === null, 'from' => (string) $r['sender_name'] ?: 'Traversence'], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Unread at Alert or above, for the bell (0172: the bell counts everything unread at Alert or above). */
    public static function unreadForBell(PDO $pdo, int $u): int
    {
        if (!self::ready($pdo)) return 0;
        $st = $pdo->prepare("SELECT COUNT(*) FROM comms_messages WHERE to_user = ? AND read_at IS NULL AND pulse_ref IS NULL AND lvl IN ('critical','urgent','alert')");
        $st->execute([$u]);
        return (int) $st->fetchColumn();
    }

    /** Read: these message ids, or every unread one of a kind and thread (a conversation opened). */
    public static function markRead(PDO $pdo, int $u, array $ids = [], string $kind = '', string $thread = ''): void
    {
        if (!self::ready($pdo)) return;
        try {
            if ($ids) $st = $pdo->query('SELECT id FROM comms_messages WHERE read_at IS NULL AND to_user = ' . $u . ' AND id IN (' . implode(',', array_map('intval', $ids)) . ')');
            elseif ($kind !== '') { $st = $pdo->prepare('SELECT id FROM comms_messages WHERE read_at IS NULL AND to_user = ? AND kind = ? AND thread = ?'); $st->execute([$u, $kind, $thread]); }
            else return;
            foreach (array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)) as $mid) {
                $pdo->prepare('UPDATE comms_messages SET read_at = ? WHERE id = ?')->execute([self::now(), $mid]);
                self::route($pdo, $mid, 'pulse', 'read', 'Opened in Pulse');
            }
        } catch (Throwable $e) { error_log('Notify read: ' . $e->getMessage()); }
    }

    /** A message's whole route, oldest first (message control, 0172 §4.3): [{via, stage, detail, at}]. */
    public static function routeOf(PDO $pdo, int $mid): array
    {
        if (!self::ready($pdo)) return [];
        $st = $pdo->prepare('SELECT via, stage, detail, happened_at FROM comms_route WHERE message_id = ? ORDER BY id'); $st->execute([$mid]);
        return array_map(fn ($r) => ['via' => (string) $r['via'], 'stage' => (string) $r['stage'], 'detail' => (string) $r['detail'], 'at' => (string) $r['happened_at']], $st->fetchAll(PDO::FETCH_ASSOC));
    }
}
