<?php
// app/lib/Pulse.php — the admin Pulse (decisions/0105): the role's daily work as cases. Items are read live from where
// they happen (orders, proofs, sales, claims, reports, visitors, staff sign-ins, the crawler) and team posts; nothing is
// copied. Each item is a case with a source, a severity and an SLA clock:
//
//   severity   critical (2 h) · alert (8 h) · update (2 days) · event (no clock)      (Jason, 2026-10-21; Stream settings later)
//   source     support · sales · fulfillment · payments · safety · security · system · team
//
// pulse_marks keeps a case's number, who has it, whether it's resolved and how far it's escalated: Escalate sends it to
// the department's Manager; still open a further SLA later, it goes to the Director. pulse_notes is the case's history
// (notes, replies, assign, escalate, resolve). Post works like a reply: back to the sender when a person raised the item,
// otherwise a note to the department. pulse_posts are notes posted to a department's stream.
declare(strict_types=1);

final class Pulse
{
    private const OWN_BACKLOG = 300;   // the crawler's own Review items waiting: past this, one System case says so
    private const OWN_PER_HOUR = 100;  // or this many made in an hour
    /** The kinds of work a person owes an answer to: only these carry a clock on the row (Jason, 2026-11-19; 0105's note). */
    public const CLOCKED = ['support', 'sales', 'fulfillment', 'payments', 'safety', 'security'];
    public const SOURCES = [   // label, icon, the area a person must reach (Workspaces::allowsArea), the department it belongs to
        'support' => ['Support', 'contact', 'listings', 'ops'],
        'sales' => ['Sales', 'chart', 'sales', 'sal'],
        'fulfillment' => ['Fulfillment', 'cart', 'ecommerce', 'svc'],
        'payments' => ['Payments', 'inbox', 'ecommerce', 'fin'],
        'safety' => ['Safety', 'shield', 'safety', 'ops'],
        'security' => ['Security', 'power', 'settings', 'eng'],
        'system' => ['System', 'settings', 'data', 'eng'],
        'team' => ['Team', 'users', 'admin', ''],
    ];
    /** Outside services a step can send you to: key => [name, the link]. A step that needs one carries it inline
     *  (Jason, 2026-11-01: "Railway link should be inline with the steps to fix issues and any other outside service too"). */
    public const SERVICES = [   // key => [name, the link, what we use it for]
        'railway' => ['Railway', 'https://railway.com/dashboard', 'Runs the crawler worker every 5 minutes (the Traversence project, crawler service). The first place to look when the crawler stops.'],
        'quickbooks' => ['QuickBooks', 'https://qbo.intuit.com/', 'Invoices, card payments, refunds and disputes for orders.'],
        'aws' => ['AWS S3', 'https://s3.console.aws.amazon.com/s3/home', 'Where uploaded files and photos were stored until the move to Cloudflare R2 (decisions/0151): empty it once nothing is missing.'],
        'cloudflare' => ['Cloudflare', 'https://dash.cloudflare.com/', 'File storage (R2: every photo, video and file, decisions/0151) and the captcha on sign-in and sign-up (Turnstile).'],
        'anthropic' => ['Claude Console', 'https://console.anthropic.com/', 'Claude for AI Assist, Find it, guide writing and reading business cards: usage and billing.'],
        'tavily' => ['Tavily', 'https://app.tavily.com/', 'The crawler\'s web search: usage and its key.'],
        'google' => ['Google Cloud', 'https://console.cloud.google.com/', 'Google Places checks and Continue with Google sign-in.'],
        'bluehost' => ['Bluehost', 'https://my.bluehost.com/', 'The web host: cPanel\'s File Manager for the secrets in .env (the one thing still done by hand).'],
        'github' => ['GitHub: the sync', 'https://github.com/degraffics/traverse-3way-sync/actions', 'The 3-way sync between the server and OneDrive: Run it now, or see what it last settled on.'],
        'stripe' => ['Stripe', 'https://dashboard.stripe.com/connect/accounts/overview', 'Card checkout on the Marketplace for businesses on Stripe (decisions/0135): our platform account, its connected businesses, webhooks.'],
        'ses' => ['Amazon SES', 'https://console.aws.amazon.com/ses/home#/get-set-up', 'Sends notices, the weekly digest and, later, businesses\' own email (decisions/0137 §8): production access, sending domains, and the account\'s health.'],
        'aws_iam' => ['AWS IAM', 'https://us-east-1.console.aws.amazon.com/iam/home#/users', 'The site\'s AWS key (the traversence-site user): what it may do. Its SES permissions are the traversence-ses policy.'],
        'namecom' => ['Name.com', 'https://www.name.com/account/settings/api', 'Domains businesses buy for their storefronts (decisions/0139): our API username and token, the sandbox\'s (…-test) and the live one. With two-step verification on the account, its Name.com API Access switch (Security settings) must be on.'],
        'square' => ['Square', 'https://developer.squareup.com/apps', 'Card checkout and catalog sync on the Marketplace for businesses on Square (decisions/0135): our app, its OAuth and webhooks.'],
    ];
    // the six levels (decisions/0172, Jason 2026-11-18): Urgent and Special added; Update shows as Notice and Event as
    // General, their stored keys kept so nothing already recorded changes. No clock on the new ones (0172: "No clock is set
    // on anyone's messages"); 0105's staff clocks stay as built for the four that had them.
    public const SEVERITY = ['critical' => 'Critical', 'urgent' => 'Urgent', 'alert' => 'Alert', 'update' => 'Notice', 'event' => 'General', 'special' => 'Special'];
    public const SLA_HOURS = ['critical' => 2, 'urgent' => 0, 'alert' => 8, 'update' => 48, 'event' => 0, 'special' => 0];
    /** Notify's level keys → Pulse's stored keys. */
    public const FROM_LEVEL = ['critical' => 'critical', 'urgent' => 'urgent', 'alert' => 'alert', 'notice' => 'update', 'general' => 'event', 'special' => 'special'];
    public const FILE_CEILING = 200000;
    private const CRITICAL_REASONS = ['safety', 'danger', 'threat', 'harassment', 'sacred', 'restricted', 'private', 'privacy', 'personal'];

    public static function ready(PDO $pdo): bool
    {
        try { $pdo->query('SELECT 1 FROM ' . Database::t('pulse_marks') . ' LIMIT 1'); return true; } catch (Throwable) { return false; }
    }
    public static function casesReady(PDO $pdo): bool
    {
        try { $pdo->query('SELECT case_no FROM ' . Database::t('pulse_marks') . ' LIMIT 1'); $pdo->query('SELECT 1 FROM ' . Database::t('pulse_notes') . ' LIMIT 1'); return true; } catch (Throwable) { return false; }
    }

    /**
     * This person's cases, most pressing first: escalated to them, then critical, alert, update, event; newest first
     * within. Each: key, source, severity, title, why, action (the link's word), when, url, about (what a reply calls it),
     * reply_to [email, name]|null, dept, sla_due|null, over (bool), state ''|done, mine, who, esc (0–2), esc_to_me.
     * $staff null = an admin from before staff logins (sees everything).
     */
    public static function items(PDO $pdo, ?array $staff, int $uid): array
    {
        require_once __DIR__ . '/Workspaces.php';
        $sees = fn (string $src) => Workspaces::allowsArea($staff, self::SOURCES[$src][2], $pdo);
        $rows = function (string $sql, array $a = []) use ($pdo): array { try { $st = $pdo->prepare($sql); $st->execute($a); return $st->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable) { return []; } };
        $since = date('Y-m-d H:i:s', strtotime('-30 days'));
        $out = [];
        $add = function (string $key, string $src, string $sev, string $title, string $why, ?string $when, string $url, string $action, string $about = '', ?array $to = null) use (&$out, $pdo) {
            $out[$key] = ['key' => $key, 'source' => $src, 'severity' => $sev, 'title' => $title, 'why' => $why, 'when' => (string) $when, 'url' => $url,
                'action' => $action, 'about' => $about, 'reply_to' => $to && !empty($to[0]) ? $to : null, 'dept' => self::deptFor($pdo, $src)];
        };
        if (is_file(__DIR__ . '/ClientOrders.php')) { try { require_once __DIR__ . '/ClientOrders.php'; } catch (Throwable) {} }
        $ref = fn (array $o) => class_exists('ClientOrders') ? ClientOrders::ref($o) : '#' . $o['id'];
        $who = fn (array $o) => trim((string) ($o['client_company'] ?: $o['bill_name'])) ?: 'a client';
        $client = fn (array $o) => [(string) $o['bill_email'], (string) ($o['bill_name'] ?: $o['client_company'])];
        $order = fn (array $o) => 'your order ' . $ref($o);

        // the System Control Board's alarms (decisions/0171 §8, release 3a): one item per open trip, never one case gathering
        // many; it goes when its alarm clears; Done acknowledges it, Undo takes that back. Only trips (Jason, 2026-11-19: "trips
        // that require the admin are sent immediately… everything else should be cleared by the system so why send and
        // pulse?"): a warning stays on the board, and becomes a trip if the system can't clear it
        try {
            require_once __DIR__ . '/ControlAlarms.php';
            foreach (ControlAlarms::list($pdo, 200) as $al) {
                if ($al['cleared_at'] !== null || $al['level'] !== 'trip') continue;
                // its words in the dispatcher's one shape, everything but the details (decisions/0176 §2): status, what it
                // affects, what the system is doing, what to do; its steps are its buttons
                $words = trim((string) $al['means'] . ' ' . (string) $al['todo']);
                try { require_once __DIR__ . '/Thread.php'; $words = Thread::text(ControlAlarms::facts($pdo, $al), 'pulse'); } catch (Throwable $x) {}
                // its row is the alarm notice: "SYSTEM ALARM NOTICE: <item>: <action>" (decisions/0176's note, 2026-11-20)
                $add('alarm:' . (int) $al['id'], 'system', 'critical', ControlAlarms::noticeTitle($pdo, $al),
                     $words, (string) $al['first_at'], '/admin/config/control#cb-alarm-' . (int) $al['id'], 'Open it');
            }
        } catch (Throwable $x) { error_log('pulse alarms: ' . $x->getMessage()); }

        // a refund prepared on an order waits for Jason (decisions/0178 §1): one case per refund, for the Founder only, with
        // Approve and Decline; it goes when it's decided or withdrawn (the order page shows where it stands meanwhile)
        try {
            require_once __DIR__ . '/Orders.php';
            if (Orders::canDecideRefund()[0]) foreach (Orders::refundsWaiting($pdo) as $rq) {
                $ro = Orders::get($pdo, (int) $rq['order_id']); if (!$ro) continue;
                $cr = ($rq['kind'] ?? 'refund') === 'credit';
                $add('refundok:' . (int) $rq['id'], 'payments', 'alert', ($cr ? 'Credit memo to approve: ' : 'Refund to approve: ') . Orders::usd((int) $rq['amount_cents']) . ' on order ' . $ref($ro),
                    (trim((string) $rq['reason']) !== '' ? trim((string) $rq['reason']) : 'No note') . ' · asked by ' . ($rq['asked_name'] ?: 'staff') . ((int) $rq['cancel_order'] ? ' · the order is cancelled with it' : '') . '. Nothing is ' . ($cr ? 'credited' : 'refunded') . ' until you approve it.',
                    (string) $rq['asked_at'], '/admin/backoffice/orders/' . (int) $ro['id'] . '#money', 'Approve or decline', $order($ro), $client($ro));
            }
            // approved, and not by card: the money goes back by a person (a check, a wire): one case each, for Payments,
            // until ✓ Sent (the session manager's call on (b), 2026-11-24)
            if ($sees('payments')) foreach (Orders::refundsToSend($pdo) as $rq) {
                $ro = Orders::get($pdo, (int) $rq['order_id']); if (!$ro) continue;
                $add('refundsend:' . (int) $rq['id'], 'payments', 'alert', 'Send ' . Orders::usd((int) $rq['amount_cents']) . ' back: order ' . $ref($ro),
                    'Jason approved the refund and its Refund Receipt is in QuickBooks; the money didn\'t come by card, so it goes back by check or wire. ' . trim((string) $rq['reason']) . '.',
                    (string) $rq['decided_at'], '/admin/backoffice/orders/' . (int) $ro['id'] . '#money', 'Send it', $order($ro), $client($ro));
            }
        } catch (Throwable $x) { error_log('pulse refunds: ' . $x->getMessage()); }

        if ($sees('payments')) {   // orders still on invoice later once it's retired: one case, a step each, nothing changed until chosen (decisions/0101 note 2026-11-23)
            try { require_once __DIR__ . '/ClientOrders.php'; $il = ClientOrders::invoiceLaterOpen($pdo);
                if ($il) { $np = count(array_filter($il, fn ($x) => $x['pulled']));
                    $add('invlater:open', 'payments', 'alert', 'Open invoice-later orders: choose for each (' . count($il) . ')', 'Invoice later isn\'t offered any more, so each now waits for payment before the vendor and delivery. Send each as an invoice (paid in full now), or an admin releases it before payment with a reason.' . ($np ? ' ' . $np . ' came from QuickBooks.' : ''), (string) max(array_map(fn ($x) => (string) ($x['approved_at'] ?: $x['created_at']), $il)), '/admin/dashboard/stream#case=invlater%3Aopen', 'Choose'); } } catch (Throwable $x) { error_log('pulse invoice later: ' . $x->getMessage()); }
            foreach ($rows("SELECT * FROM orders WHERE status = 'disputed' ORDER BY updated_at DESC LIMIT 20") as $o)
                $add('dispute:' . $o['id'], 'payments', 'critical', 'Card dispute: order ' . $ref($o), 'The bank wants an answer by a date. Answer it in QuickBooks, then record how it ended.', $o['updated_at'], '/admin/order.php?id=' . $o['id'], 'Answer', $order($o), $client($o));
            foreach ($rows("SELECT * FROM orders WHERE paid_at >= ? ORDER BY paid_at DESC LIMIT 15", [$since]) as $o)
                $add('paid:' . $o['id'], 'payments', 'event', 'Paid: order ' . $ref($o), $who($o) . ' · $' . number_format($o['total_cents'] / 100, 2), $o['paid_at'], '/admin/order.php?id=' . $o['id'], 'View', $order($o), $client($o));
            foreach ($rows("SELECT * FROM orders WHERE status IN ('refunded','part_refunded') AND updated_at >= ? ORDER BY updated_at DESC LIMIT 10", [$since]) as $o)
                $add('refund:' . $o['id'] . ':' . $o['status'], 'payments', 'update', ($o['status'] === 'refunded' ? 'Refunded' : 'Part refunded') . ': order ' . $ref($o), $who($o), $o['updated_at'], '/admin/order.php?id=' . $o['id'], 'View', $order($o), $client($o));
            try { require_once __DIR__ . '/Orders.php'; foreach (Orders::inbox($pdo) as $i => $r)
                $add('qbinbox:' . ($r['id'] ?? $i), 'payments', 'alert', 'A QuickBooks refund to place', 'It could be more than one order: say which, so the right GO Codes stop.', (string) ($r['created_at'] ?? ''), '/admin/ecommerce.php?sec=orders', 'Place it'); } catch (Throwable) {}
        }
        if ($sees('fulfillment')) {
            foreach ($rows("SELECT * FROM orders WHERE due_date IS NOT NULL AND due_date < CURDATE() AND status IN ('sent','pending') ORDER BY due_date LIMIT 20") as $o)
                $add('overdue:' . $o['id'], 'fulfillment', 'critical', 'Past due: order ' . $ref($o), 'Due ' . date('M j', strtotime((string) $o['due_date'])) . ' · ' . $who($o), $o['due_date'] . ' 00:00:00', '/admin/backoffice/orders/' . $o['id'], 'Review', $order($o), $client($o));
            foreach ($rows("SELECT p.*, o.client_company, o.bill_name, o.bill_email, o.created_at AS o_created FROM order_proofs p JOIN orders o ON o.id = p.order_id WHERE p.status = 'changes' ORDER BY p.decided_at DESC LIMIT 20") as $p) {
                $add('proof:' . $p['id'], 'fulfillment', 'alert', 'Changes asked on a proof', ($p['client_note'] !== '' ? '"' . mb_strimwidth((string) $p['client_note'], 0, 90, '…') . '" · ' : '') . $who($p), $p['decided_at'], '/admin/backoffice/orders/' . $p['order_id'], 'Make changes', 'your proof', $client($p));
                $out['proof:' . $p['id']]['order_id'] = (int) $p['order_id'];
            }
            $withVendor = [];   // orders a vendor has: the work order's own alerts (not accepted, past its must-have time) do the chasing
            foreach ($rows("SELECT order_id, MAX(COALESCE(status_at, sent_at)) AS at FROM order_work_orders WHERE status IN ('sent','accepted','production','shipped','ready') GROUP BY order_id") as $w) $withVendor[(int) $w['order_id']] = (string) $w['at'];
            foreach ($rows("SELECT * FROM orders WHERE approved_at IS NOT NULL AND status IN ('pending','paid') AND COALESCE(stage,'') NOT IN ('shipped','ready','complete') AND COALESCE(ship_mode,'') <> 'none' AND created_by > 0 ORDER BY approved_at DESC LIMIT 30") as $o) {
                $wv = $withVendor[(int) $o['id']] ?? null;
                $add('ship:' . $o['id'], 'fulfillment', $wv !== null ? 'update' : 'alert', 'To make and ship: order ' . $ref($o), ($wv !== null ? 'With the vendor' : 'Approved ' . date('M j', strtotime((string) $o['approved_at']))) . ' · ' . $who($o), $wv ?: $o['approved_at'], '/admin/backoffice/orders/' . $o['id'] . '#ship', 'Ship it', $order($o), $client($o));
            }
            // reminded and still not confirmed (Jason, 2026-10-30: "Client or myself should complete this")
            foreach ($rows("SELECT o.*, MAX(r.created_at) AS reminded FROM orders o JOIN order_events r ON r.order_id = o.id AND r.event = 'got_it_reminder' WHERE o.status IN ('pending','paid','part_refunded') AND COALESCE(o.stage,'') <> 'complete' GROUP BY o.id ORDER BY reminded DESC LIMIT 20") as $o)
                $add('gotit:' . $o['id'], 'fulfillment', 'update', 'Not confirmed yet: order ' . $ref($o), 'The client was reminded ' . date('M j', strtotime((string) $o['reminded'])) . '. Complete it if you know it arrived · ' . $who($o), (string) $o['reminded'], '/admin/backoffice/orders/' . $o['id'] . '#finish', 'Complete it', $order($o), $client($o));
            // what clients told us once it arrived (Jason, 2026-10-30): a problem to put right, and their stars and words
            foreach ($rows("SELECT f.*, o.client_company, o.bill_name, o.bill_email, o.id AS oid FROM order_feedback f JOIN orders o ON o.id = f.order_id WHERE (f.kind = 'problem' AND f.resolved_at IS NULL) OR (f.kind = 'review' AND f.created_at >= ?) ORDER BY f.id DESC LIMIT 20", [$since]) as $f) {
                require_once __DIR__ . "/Orders.php"; $o = Orders::get($pdo, (int) $f["oid"]); if (!$o) continue;
                $bad = $f['kind'] === 'problem';
                $add('fb:' . $f['id'], $bad ? 'support' : 'fulfillment', $bad ? 'critical' : 'event', ($bad ? 'Support ticket: order ' : 'Feedback: order ') . $ref($o) . (!$bad && $f['rating'] ? ' ' . str_repeat('★', (int) $f['rating']) : ''),
                    ((string) $f['words'] !== '' ? '"' . mb_strimwidth((string) $f['words'], 0, 90, '…') . '" · ' : '') . $who($o), (string) $f['created_at'], '/admin/backoffice/orders/' . $o['id'] . '#finish', $bad ? 'Put it right' : 'View', $order($o), $client($o));
                $out['fb:' . $f['id']]['order_id'] = (int) $o['id'];
            }
        }
        if ($sees('fulfillment')) {   // work orders that need us (decisions/0101, addendum 2026-10-28)
            try {
                require_once __DIR__ . '/WorkOrders.php';
                if (WorkOrders::statusReady($pdo)) {
                    $wurl = fn (array $w) => '/admin/backoffice/orders/' . $w['order_id'] . '#work';
                    $wadd = function (string $k, array $w, string $sev, string $title, string $why, string $when, string $act) use ($add, &$out, $wurl) {
                        $add($k . ':' . $w['id'], 'fulfillment', $sev, $title, $why, $when, $wurl($w), $act); $out[$k . ':' . $w['id']]['order_id'] = (int) $w['order_id'];
                    };
                    foreach ($rows("SELECT w.* FROM order_work_orders w JOIN orders o ON o.id = w.order_id WHERE w.status NOT IN ('received','cancelled','complete') AND o.qb_env NOT IN ('sandbox','mock') ORDER BY w.id DESC LIMIT 60") as $w) {
                        $due = WorkOrders::dueAt($w); $label = 'Work order ' . $w['wo_no'] . ' (' . $w['vendor'] . ')';
                        if ($w['status'] === 'declined') $wadd('wodecl', $w, 'critical', 'Declined: ' . $label, (string) $w['vendor_note'], (string) $w['status_at'], 'Send elsewhere');
                        elseif ($w['status'] === 'rejected') $wadd('worej', $w, 'alert', 'Rejected on receipt: ' . $label, (string) $w['receive_note'], (string) $w['status_at'], 'Reprint');
                        elseif (in_array($w['status'], ['sent', 'accepted', 'production'], true) && $due && $due < time()) $wadd('wolate', $w, 'critical', 'Past its must-have time: ' . $label, 'Due ' . date('M j, g:i a', $due) . ' · ' . (WorkOrders::STATUS[$w['status']] ?? ''), date('Y-m-d H:i:s', $due), 'Chase it');
                        elseif ($w['status'] === 'sent' && $w['sent_at'] && time() >= WorkOrders::nextBusinessNine(strtotime((string) $w['sent_at']))) $wadd('woacc', $w, 'alert', 'Not accepted yet: ' . $label, 'Sent ' . date('M j, g:i a', strtotime((string) $w['sent_at'])), (string) $w['sent_at'], 'Chase it');
                        elseif (in_array($w['status'], ['shipped', 'ready'], true) && in_array($w['delivery'], ['will_call', 'internal', 'local', 'courier'], true)) $wadd('worcv', $w, 'update', ($w['status'] === 'ready' ? 'Ready to pick up: ' : 'On its way to us: ') . $label, 'Receive it when it comes in', (string) $w['status_at'], 'Receive');
                    }
                }
                // the vendor has done their part, but the order waits for its balance before it's released for delivery
                // (decisions/0101 note 2026-11-23, model 1.9): one step on the order's case, gone once it's paid or released
                require_once __DIR__ . '/ClientOrders.php';
                foreach ($rows("SELECT o.*, (SELECT MAX(e.created_at) FROM order_events e WHERE e.order_id = o.id AND e.event = 'balance_hold') AS held_at FROM orders o
                                WHERE o.status = 'pending' AND o.created_by > 0 AND COALESCE(o.qb_env, '') NOT IN ('sandbox', 'mock') AND EXISTS (SELECT 1 FROM order_events e WHERE e.order_id = o.id AND e.event = 'balance_hold') ORDER BY o.id DESC LIMIT 30") as $o) {
                    if (ClientOrders::shipHold($pdo, $o) === '') continue;
                    $left = max(0, (int) $o['total_cents'] - Orders::money($pdo, (int) $o['id'])['paid']);
                    $add('wobal:' . $o['id'], 'fulfillment', 'alert', 'Balance due before delivery: order ' . $ref($o), Orders::usd($left) . ' to collect · the vendor has done their part', (string) $o['held_at'], '/admin/backoffice/orders/' . $o['id'] . '#payhold', 'Collect it', $order($o), $client($o));
                    $out['wobal:' . $o['id']]['order_id'] = (int) $o['id'];
                }
                // delivered, balance due (decisions/0178 §6): the client has it and money is still owed; our team records the
                // payment, collects it, or an admin completes it with a reason. Gone once it's paid or completed.
                if (ClientOrders::hasReceived($pdo)) foreach ($rows("SELECT o.* FROM orders o WHERE o.status = 'pending' AND o.created_by > 0 AND o.received_at IS NOT NULL AND COALESCE(o.stage, '') <> 'complete' AND COALESCE(o.qb_env, '') NOT IN ('sandbox', 'mock') ORDER BY o.id DESC LIMIT 30") as $o) {
                    if (ClientOrders::completeHold($pdo, $o) === '') continue;
                    $left = max(0, (int) $o['total_cents'] - Orders::money($pdo, (int) $o['id'])['paid']);
                    $add('delbal:' . $o['id'], 'fulfillment', 'alert', 'Delivered, balance due: order ' . $ref($o), Orders::usd($left) . ' owed · the client told us they have it', (string) $o['received_at'], '/admin/backoffice/orders/' . $o['id'] . '#payhold', 'Settle it', $order($o), $client($o));
                    $out['delbal:' . $o['id']]['order_id'] = (int) $o['id'];
                }
            } catch (Throwable) {}
        }
        if ($sees('sales')) {
            // the Call list (decisions/0133): one item a day while listings we're least sure of wait for a call
            try { require_once __DIR__ . '/Sales.php'; $cl = count(Sales::toConfirm($pdo, 500));
                if ($cl) $add('calllist:' . date('Y-m-d'), 'sales', 'update', 'Call list: ' . ($cl >= 500 ? '500+' : $cl) . ' listing' . ($cl === 1 ? '' : 's') . ' to confirm',
                    'Red and orange listings with a phone and no call in 90 days, the least sure first. Each answered call confirms the listing and starts a conversation.', date('Y-m-d') . ' 08:00:00', '/admin/growth/calls?what=confirm', 'Start calling');
            } catch (Throwable) {}
            foreach ($rows("SELECT p.id, p.next_at, p.contact_name, p.last_outcome, e.name FROM " . Database::t('prospects') . " p JOIN entities e ON e.id = p.entity_id WHERE p.next_at IS NOT NULL AND p.next_at <= CURDATE() AND p.stage NOT IN ('no','closed') ORDER BY p.next_at LIMIT 25") as $p)
                $add('prospect:' . $p['id'] . ':' . $p['next_at'], 'sales', 'update', 'Follow up: ' . $p['name'], trim(($p['contact_name'] ? 'Ask for ' . $p['contact_name'] . '. ' : '') . (string) $p['last_outcome']), $p['next_at'] . ' 08:00:00', '/admin/sales.php#calls', 'Call');
            foreach ($rows("SELECT * FROM orders WHERE status = 'draft' AND created_by > 0 AND created_at >= ? ORDER BY created_at DESC LIMIT 15", [$since]) as $o)   // a new order being built (Jason, 2026-10-28: "why don't I see incoming orders")
                $add('draft:' . $o['id'], 'sales', 'update', 'New order, not sent yet: order ' . $ref($o), 'Started ' . date('M j', strtotime((string) $o['created_at'])) . ' · ' . $who($o), $o['created_at'], '/admin/backoffice/orders/' . $o['id'], 'Finish it', $order($o), $client($o));
            foreach ($rows("SELECT * FROM orders WHERE status = 'sent' AND sent_at >= ? ORDER BY sent_at DESC LIMIT 10", [$since]) as $o)
                $add('sent:' . $o['id'], 'sales', 'update', 'Waiting on the client: order ' . $ref($o), 'Sent ' . date('M j', strtotime((string) $o['sent_at'])) . ' · ' . $who($o), $o['sent_at'], '/admin/backoffice/orders/' . $o['id'], 'Follow up', $order($o), $client($o));
            foreach ($rows("SELECT * FROM orders WHERE approved_at >= ? ORDER BY approved_at DESC LIMIT 10", [$since]) as $o)
                $add('won:' . $o['id'], 'sales', 'event', 'Approved: order ' . $ref($o), $who($o) . ' · $' . number_format($o['total_cents'] / 100, 2), $o['approved_at'], '/admin/backoffice/orders/' . $o['id'], 'View', $order($o), $client($o));
        }
        if ($sees('support')) {
            // Group help (decisions/0137 §5): a group's leaders asked us, or its reports have waited 14 days with nobody acting
            foreach ($rows("SELECT h.id, h.group_id, h.body, h.created_at, g.name, u.email, u.display_name FROM community_help h JOIN community_groups g ON g.id = h.group_id
                             LEFT JOIN users u ON u.id = h.user_id WHERE h.status = 'open' ORDER BY h.created_at LIMIT 25") as $hq)
                $add('grouphelp:' . $hq['id'], 'support', 'alert', 'Group help: ' . $hq['name'], mb_strimwidth((string) $hq['body'], 0, 140, '…'), $hq['created_at'],
                     '/community/group.php?id=' . (int) $hq['group_id'], 'Help', 'your request for help with ' . $hq['name'], [(string) $hq['email'], (string) $hq['display_name']]);
            foreach ($rows("SELECT p.group_id, g.name, COUNT(DISTINCT r.target_id) n, MIN(r.created_at) first_at FROM content_reports r JOIN community_posts p ON p.id = r.target_id AND p.removed_at IS NULL
                             JOIN community_groups g ON g.id = p.group_id WHERE r.kind = 'group_post' AND r.status = 'open' AND r.route = 'group' AND r.created_at < ?
                             GROUP BY p.group_id, g.name ORDER BY first_at LIMIT 25", [date('Y-m-d H:i:s', time() - 14 * 86400)]) as $hq)
                $add('grouphelp:stale' . $hq['group_id'], 'support', 'alert', 'Group help: ' . $hq['name'] . ' has reports waiting 14 days', $hq['n'] . ' reported post' . ($hq['n'] == 1 ? '' : 's')
                     . ' nobody in the group has decided on.', $hq['first_at'], '/community/group.php?id=' . (int) $hq['group_id'], 'Help');
            foreach ($rows("SELECT c.id, c.status, c.created_at, e.name, u.email, u.display_name FROM listing_claims c JOIN entities e ON e.id = c.entity_id LEFT JOIN users u ON u.id = c.user_id WHERE c.status IN ('managed','on_hold','pending') ORDER BY c.created_at DESC LIMIT 15") as $c)
                $add('claim:' . $c['id'] . ':' . $c['status'], 'support', 'alert', ($c['status'] === 'on_hold' ? 'Claim on hold: ' : 'Claim to confirm: ') . $c['name'], 'Match the text code, or contact the claimant within 48 hours.', $c['created_at'], '/admin/claims.php', 'Confirm', 'your claim for ' . $c['name'], [(string) $c['email'], (string) $c['display_name']]);
            foreach ($rows("SELECT s.id, s.kind, s.topic, s.context, s.fields, s.target_name, s.note, s.created_at, s.user_id, u.email, u.display_name FROM suggestions s LEFT JOIN users u ON u.id = s.user_id WHERE s.status = 'new' AND COALESCE(s.context,'') <> 'google'
                             AND NOT (s.device_hash LIKE 'crawler-%' OR s.device_hash LIKE 'system:%') ORDER BY s.created_at DESC LIMIT 25") as $s) {
                $guide = str_starts_with((string) $s['context'], 'guide:');
                $add('sugg:' . $s['id'], 'support', 'update', ($guide ? 'Found for the guide' : ($s['kind'] === 'feedback' ? 'Feedback' : 'From a visitor: ' . str_replace('_', ' ', (string) $s['kind']))) . ($s['target_name'] ? ($guide ? ': ' : ' · ') . $s['target_name'] : ''), mb_strimwidth((string) $s['note'], 0, 120, '…'), $s['created_at'], '/admin/crawler-review.php?type=suggest', 'Read', 'your note to Traversence', [(string) $s['email'], (string) $s['display_name']]);
                $out['sugg:' . $s['id']]['sugg'] = ['id' => (int) $s['id'], 'kind' => (string) $s['kind'], 'topic' => (string) $s['topic'], 'guide' => $guide, 'row' => $s];
                if ($guide) $out['sugg:' . $s['id']]['no_clock'] = true;   // nobody is waiting on a reply: work to do, not a promise to keep
            }
            if (is_file(__DIR__ . '/People.php')) { try { require_once __DIR__ . '/People.php';
                if (People::tableExists($pdo)) { $w = People::waiting($pdo, $uid); $n = (int) $w['unread'] + (int) ($w['message_requests'] ?? 0);
                    if ($n) $add('msgs:' . $uid . ':' . $n, 'support', 'update', $n . ' message' . ($n === 1 ? '' : 's') . ' for you', 'In your own Address Book.', date('Y-m-d H:i:s'), '/user/dashboard.php#addressbook:messages', 'Read'); } } catch (Throwable) {} }
        }
        if ($sees('safety')) {
            // a group's own reports are its moderators' (decisions/0137 §5): only those that come to us are cases here
            if (is_file(__DIR__ . '/Reports.php')) require_once __DIR__ . '/Reports.php';
            $routeSql = class_exists('Reports') ? Reports::staffOnly($pdo, 'r') : '';
            foreach ($rows("SELECT r.id, r.kind, r.reason, r.note, r.created_at, u.email, u.display_name FROM content_reports r LEFT JOIN users u ON u.id = r.reporter_id WHERE r.status = 'open'" . $routeSql . " ORDER BY r.created_at DESC LIMIT 25") as $r) {
                $crit = (bool) array_filter(self::CRITICAL_REASONS, fn ($w) => str_contains(strtolower((string) $r['reason']), $w));
                $add('report:' . $r['id'], 'safety', $crit ? 'critical' : 'alert', 'Report: ' . str_replace('_', ' ', (string) $r['reason']) . ' (' . $r['kind'] . ')', mb_strimwidth((string) $r['note'], 0, 120, '…'), $r['created_at'], '/admin/reports.php', 'Decide', 'your report', [(string) $r['email'], (string) $r['display_name']]);
            }
            // Marketplace posts a rule word held for a person (decisions/0135): one case, a step for each post
            if (is_file(__DIR__ . '/Market.php')) {
                try {
                    require_once __DIR__ . '/Market.php';
                    if ($held = Market::held($pdo)) {
                        $add('mkt:held', 'safety', 'update', count($held) . ' Marketplace post' . (count($held) === 1 ? '' : 's') . ' waiting for a look',
                             'A word in the Marketplace rules held ' . (count($held) === 1 ? 'it' : 'them') . ' back. Let each show, or take it down with a line the seller sees.',
                             (string) ($held[0]['updated_at'] ?: $held[0]['created_at']), '/admin/market/items?status=held', 'Look');
                        $out['mkt:held']['mkt'] = $held;
                    }
                    if (is_file(__DIR__ . '/MarketOffers.php')) {   // problems reported on a trade (0135 §4): one case, a step each
                        require_once __DIR__ . '/MarketOffers.php';
                        if ($dis = MarketOffers::disputes($pdo)) {
                            $add('mkt:disputes', 'safety', 'alert', count($dis) . ' Marketplace trade' . (count($dis) === 1 ? '' : 's') . ' with a problem reported',
                                 'Read what happened, then settle each: it went through, it fell through, or one of them was in the wrong. Both are emailed your note.',
                                 (string) $dis[0]['updated_at'], '/admin/market/exchange', 'Settle');
                            $out['mkt:disputes']['mkt'] = $dis;
                        }
                    }
                } catch (Throwable) {}
            }
            // what the hourly look at likes found (app/lib/LikeGuard.php): one case, a step each
            if (is_file(__DIR__ . '/LikeGuard.php')) {
                try {
                    require_once __DIR__ . '/LikeGuard.php';
                    if ($lf = LikeGuard::flags($pdo)) {
                        $add('likes:checks', 'safety', 'update', count($lf) . ' like check' . (count($lf) === 1 ? '' : 's') . ' to look at',
                             'Likes that may not be genuine. Clear machine likes already came off the count; decide each: it looks fine, or remove them.',
                             (string) $lf[0]['created_at'], '/admin/pulse.php', 'Decide');
                        $out['likes:checks']['likes'] = $lf;
                    }
                } catch (Throwable) {}
            }
            $n = 0; try { require_once __DIR__ . '/Standing.php'; $n = Standing::reviewsWaiting($pdo); } catch (Throwable) {}
            if ($n) $add('standing:' . $n, 'safety', 'alert', $n . ' review' . ($n === 1 ? '' : 's') . ' asked on a notice', 'A member asked a person to look again; their clock is stopped until you decide.', date('Y-m-d H:i:s'), '/admin/safety.php#standing', 'Decide');
        }
        if ($sees('payments')) {   // a business's card checkout that stopped (decisions/0135, step 4b): one case per business
            try {
                require_once __DIR__ . '/MarketPay.php';
                foreach ($rows("SELECT g.entity_id, g.provider, g.last_error, g.updated_at, e.name FROM market_gateways g LEFT JOIN entities e ON e.id = g.entity_id WHERE g.status = 'broken' AND g.env = ? ORDER BY g.updated_at DESC LIMIT 30", [MarketPay::env()]) as $g) {
                    $k = 'mkgw:' . $g['entity_id'] . ':' . $g['provider'];
                    $pn = MarketPay::PROVIDERS[$g['provider']] ?? $g['provider'];
                    $add($k, 'payments', 'alert', 'Card payments stopped: ' . ($g['name'] ?: 'business ' . $g['entity_id']) . ' (' . $pn . ')',
                        ($g['last_error'] !== '' ? $g['last_error'] . '. ' : '') . 'Buyers can still pay the business\'s other ways; card checkout waits for them to reconnect.', (string) $g['updated_at'], '/admin/market/gateways', 'Help reconnect');
                    $out[$k]['gw'] = ['entity' => (int) $g['entity_id'], 'provider' => (string) $g['provider'], 'name' => (string) ($g['name'] ?: 'the business'), 'pn' => $pn];
                }
            } catch (Throwable) {}
        }
        if ($sees('payments') && is_file(__DIR__ . '/Inform.php')) {   // the INFORM Consumers Act: sellers whose details are overdue (decisions/0142); one case
            try {
                require_once __DIR__ . '/Inform.php';
                if (Inform::ready($pdo) && ($ov = Inform::all($pdo, 'overdue'))) $add('inform:overdue', 'payments', 'alert', count($ov) . ' seller' . (count($ov) === 1 ? '\'s' : 's\'') . ' INFORM Act details overdue',
                    implode(', ', array_slice(array_map(fn ($r) => (string) ($r['name'] ?: 'listing ' . $r['entity_id']), $ov), 0, 5)) . '. Their card checkout is paused until they confirm; their listings stay up.', (string) ($ov[0]['due_at'] ?? date('Y-m-d H:i:s')), '/admin/market/compliance', 'Look');
            } catch (Throwable) {}
        }
        if ($sees('system') && is_file(__DIR__ . '/MemberPosts.php')) {   // free members' Moments past 80% of the month's community budget (decisions/0154 §4): one case a month
            try {
                require_once __DIR__ . '/MemberPosts.php';
                if (MemberPosts::ready($pdo) && ($mb = MemberPosts::budget($pdo)) && $mb['pct'] >= 80)
                    $add('moments:' . $mb['month'], 'system', $mb['pct'] >= 100 ? 'critical' : 'alert', 'Free members\' Moments: ' . $mb['pct'] . '% of this month\'s community budget',
                        '$' . number_format($mb['spent'], 2) . ' of $' . number_format($mb['budget'], 2) . ' (' . $mb['share'] . '% of last month\'s revenue, at least $' . number_format($mb['floor'], 0) . ').'
                        . ($mb['pct'] >= 100 ? ' Free accounts\' video Moments are paused until the 1st; photos and words carry on.' : ' At 100%, free accounts\' video Moments pause until the 1st.'),
                        date('Y-m-d H:i:s'), '/admin/config/settings', 'Look');
            } catch (Throwable) {}
        }
        if ($sees('system')) {   // requests we're waiting on from an outside company (OutsideServices::requests): one case each until answered
            try {
                require_once __DIR__ . '/OutsideServices.php';
                $reqs = OutsideServices::requests($pdo);
                if (OutsideServices::$reqError !== '') $add('outreqerr:' . substr(md5(OutsideServices::$reqError), 0, 8), 'system', 'alert', 'Requests to outside companies can\'t be saved',
                    OutsideServices::$reqError . ' (shown on each company\'s card too, under Waiting on them).', date('Y-m-d H:i:s'), '/admin/config/services#svc-namecom', 'Look');   // never silent (Jason, 2026-11-17: "nothing in pulse")
                foreach ($reqs as $id => $r) {
                    $name = OutsideServices::COMPANIES[$r['svc']][0] ?? $r['svc']; $days = (int) floor((time() - strtotime((string) $r['sent'])) / 86400);
                    $add('outreq:' . $id, 'system', $days >= 5 ? 'alert' : 'update', 'Waiting on ' . $name . ': ' . mb_strimwidth((string) $r['what'], 0, 90, '…') . ($r['ticket'] !== '' ? ' (ticket #' . $r['ticket'] . ')' : ''),
                        'Asked ' . date('M j', strtotime((string) $r['sent'])) . '. ' . ($days >= 5 ? 'No answer in ' . $days . ' days: chase them.' : 'Record their answer here when it comes; it closes the case.'), (string) $r['sent'], '/admin/config/services#svc-' . $r['svc'], 'Open');
                }
            } catch (Throwable $x) { error_log('pulse outside requests: ' . $x->getMessage()); }
        }
        if ($sees('system') && is_file(__DIR__ . '/Domains.php')) {   // Name.com refusing our sign-in: buying domains paused (decisions/0139); one case
            try {
                require_once __DIR__ . '/Domains.php';
                if ($nc = Domains::ncDown()) $add('namecom:down', 'system', 'alert', 'Name.com refused our sign-in: buying domains is paused',
                    'Name.com (' . $nc['env'] . ', as ' . $nc['user'] . ') said: ' . $nc['error'] . '. Businesses see that buying is paused and can still connect a domain they own.', (string) $nc['at'], '/admin/market/domains', 'Fix it');
            } catch (Throwable) {}
        }
        if ($sees('fulfillment') && is_file(__DIR__ . '/AccountDomains.php')) {   // domains bought in the Shop (decisions/0169 §1a): orders that need a person, and auto-renew out of tries
            try {
                require_once __DIR__ . '/AccountDomains.php';
                foreach (AccountDomains::attention($pdo) as $k => $x) {
                    $renew = str_starts_with($k, 'domrenew:'); $verify = str_starts_with($k, 'domverify:');   // domverify: the registrant hasn't confirmed, 3 days or less left: critical (Jason, 2026-11-17: "HIGH PRIORITY")
                    $add($k, 'fulfillment', $renew ? 'alert' : 'critical', $verify ? 'Domain contact not confirmed' : ($renew ? 'Domain renewal stopped' : 'Domain order needs attention' . ((int) $x['order_id'] ? ': #' . (int) $x['order_id'] : '')), (string) $x['note'], (string) $x['created_at'],
                        (int) $x['order_id'] ? '/admin/backoffice/orders/' . (int) $x['order_id'] : '/admin/backoffice/orders', 'Put it right');
                }
            } catch (Throwable $x) { error_log('pulse domains: ' . $x->getMessage()); }
        }
        if ($sees('support') && is_file(__DIR__ . '/AccountDomains.php')) {   // a brought domain's support ticket (one bought has its ticket on its order)
            try {
                foreach ($pdo->query("SELECT e.id, e.note, e.created_at, d.domain FROM domain_events e JOIN account_domains d ON d.id = e.domain_id WHERE e.kind = 'ticket' AND e.order_id IS NULL AND e.created_at > DATE_SUB(NOW(), INTERVAL 60 DAY) ORDER BY e.id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC) as $t)
                    $add('domtk:' . $t['id'], 'support', 'critical', 'Support ticket: ' . $t['domain'], mb_strimwidth((string) $t['note'], 0, 140, '…'), (string) $t['created_at'], '/admin/backoffice/orders', 'Answer');
            } catch (Throwable) {}
        }
        if ($sees('security')) {
            // the card pages' policy blocked a lot in a day (PCI DSS 11.6.1; decisions/0098, 0135): one case for the day
            try {
                require_once __DIR__ . '/CspReports.php';
                if (CspReports::ready($pdo)) foreach (CspReports::alerts($pdo, date('Y-m-d', strtotime('-2 days'))) as $d) {
                    $add('csp:' . $d['day'], 'security', 'alert', 'Card pages: ' . $d['total'] . ' blocked by their security policy on ' . date('M j', strtotime($d['day'])),
                        implode(' · ', array_map([CspReports::class, 'line'], array_slice($d['top'], 0, 3))) . '. A script that isn\'t on the list tried to run on a page that takes a card.', $d['at'], '/admin/logs.php', 'Look into it');
                    $out['csp:' . $d['day']]['csp'] = $d;
                }
            } catch (Throwable) {}
            foreach ($rows("SELECT l.staff_account_id, MAX(l.created_at) t, COUNT(*) n, s.staff_id, s.name FROM " . Database::t('staff_log') . " l JOIN " . Database::t('staff_accounts') . " s ON s.id = l.staff_account_id WHERE l.event = 'locked' AND l.created_at >= ? GROUP BY l.staff_account_id", [date('Y-m-d H:i:s', strtotime('-7 days'))]) as $r)
                $add('locked:' . $r['staff_account_id'] . ':' . substr((string) $r['t'], 0, 13), 'security', 'alert', 'Staff login locked: ' . $r['staff_id'], ($r['name'] ?: $r['staff_id']) . ': too many wrong tries. It unlocks on its own; check it was them.', $r['t'], '/admin/people.php?sec=logins', 'View logs');
            $f = $rows("SELECT COUNT(*) n, MAX(created_at) t FROM " . Database::t('staff_log') . " WHERE event = 'failed' AND created_at >= ?", [date('Y-m-d H:i:s', strtotime('-1 hour'))])[0] ?? ['n' => 0];
            if ((int) $f['n'] >= 5)
                $add('spike:' . substr((string) $f['t'], 0, 13), 'security', 'critical', 'Failed sign-in spike: ' . (int) $f['n'] . ' in the last hour', 'Someone may be guessing staff passwords. Check the staff log.', $f['t'], '/admin/logs.php', 'View logs');
            // "Can't get the email?" from the staff code step: one case per request, for the owners (decisions/0100's note
            // of 2026-11-22, Release 3f B8); its steps and buttons are StaffMailHelp's
            try {
                require_once __DIR__ . '/StaffMailHelp.php';
                foreach (StaffMailHelp::open($pdo) as $smr) {
                    [$smt, $smw] = StaffMailHelp::row($smr);
                    $add('staffmail:' . (int) $smr['id'], 'security', 'alert', $smt, $smw, (string) $smr['updated_at'], '/admin/staff.php', 'Staff logins');
                    $out['staffmail:' . (int) $smr['id']]['staffmail'] = $smr;
                }
            } catch (Throwable $x) { error_log('pulse staffmail: ' . $x->getMessage()); }
        }
        if ($sees('system') && is_file(__DIR__ . '/SesMail.php')) {   // email sending through Amazon SES, set up step by step (decisions/0137 §8)
            try {
                require_once __DIR__ . '/SesMail.php';
                if (SesMail::ready($pdo) && ($todo = SesMail::todo($pdo)))
                    $add('ses:setup', 'system', 'update', 'Set up email sending (Amazon SES)', implode(' · ', array_slice(array_values($todo), 0, 3)) . (count($todo) > 3 ? ' · …' : ''),
                         SesMail::state($pdo, 'case_since') ?: date('Y-m-d 00:00:00'), '/admin/config/services', 'Set up');
            } catch (Throwable) {}
        }
        if ($sees('system') && is_file(__DIR__ . '/Suggestions.php')) {   // the crawler's own Review items are the checkers' work, not Support's (2026-11-13 flood):
            try { require_once __DIR__ . '/Suggestions.php'; $ow = Suggestions::ownWaiting($pdo);   // one case, only when they back up
                if ($ow['new'] >= self::OWN_BACKLOG || $ow['hour'] >= self::OWN_PER_HOUR)
                    $add('ownchecks:' . date('Y-m-d'), 'system', 'alert', 'The crawler\'s own checks are backing up: ' . $ow['new'] . ' waiting',
                         $ow['hour'] . ' added in the last hour. These are the crawler\'s findings (changed phones or hours, closings, the same business twice), not visitors\' messages. Something is making too many: look before they pile up.',
                         date('Y-m-d H:i:s'), '/admin/crawler-review.php?type=suggest', 'Look');
            } catch (Throwable) {}
        }
        if ($sees('system')) {
            // failed crawl jobs: one case for all of them (Jason, 2026-11-01: one Pulse, not one each), with the places named
            $fj = $rows("SELECT j.id, j.updated_at, m.name FROM " . Database::t('crawl_jobs') . " j LEFT JOIN micro_clusters m ON m.id = j.cluster_id WHERE j.status = 'failed' AND j.updated_at >= ? ORDER BY j.updated_at DESC LIMIT 50", [$since]);
            if ($fj) {
                $where = array_values(array_unique(array_filter(array_map(fn ($j) => trim((string) preg_replace('/\s*\(auto-seeded[^)]*\)/i', '', (string) $j['name'])), $fj))));
                $add('crawl:failed', 'system', 'update', count($fj) === 1 ? 'A crawl job failed' : count($fj) . ' crawl jobs failed',
                     ($where ? 'In ' . implode(', ', array_slice($where, 0, 6)) . (count($where) > 6 ? ' and ' . (count($where) - 6) . ' more' : '') . '. ' : '') . 'Usually OpenStreetMap was busy: put them back in the queue.',
                     $fj[0]['updated_at'], '/admin/crawler.php#fix-worker', 'Retry');
            }
            // guides a visitor asked for that couldn't be built after their three tries (review W5): one case, the places named
            try {
                require_once __DIR__ . '/OnDemand.php';
                if ($gu = OnDemand::givenUp($pdo)) {
                    $names = array_values(array_unique(array_map(fn ($g) => (string) $g['label'], $gu)));
                    $add('guidefail:' . preg_replace('/\D/', '', (string) $gu[0]['failed_at']), 'system', 'update', count($gu) === 1 ? 'A guide on demand couldn\'t be built' : count($gu) . ' guides on demand couldn\'t be built',
                         implode(', ', array_slice($names, 0, 6)) . (count($names) > 6 ? ' and ' . (count($names) - 6) . ' more' : '') . ': tried three times. Last: ' . mb_strimwidth((string) $gu[0]['note'], 0, 160, '…'),
                         (string) $gu[0]['failed_at'], '/admin/explore/place_guides', 'Retry');
                }
            } catch (Throwable) {}
            // whether the crawler is working: from its runs once they come through the service (decisions/0160; the old
            // check watched only the coverage queue and cried wolf), else as before
            $svc = null;
            try { require_once __DIR__ . '/crawler/CrawlService.php'; $svc = CrawlService::ready($pdo) && $pdo->query('SELECT 1 FROM crawl_runs LIMIT 1')->fetchColumn() ? CrawlService::stalled($pdo) : false; } catch (Throwable) { $svc = false; }
            if (is_array($svc)) $add($svc[0], 'system', 'critical', $svc[1], $svc[2], $svc[3], '/admin/insights/crawler', 'Check status');
            elseif ($svc === false) {
                $last = (string) ($rows("SELECT MAX(updated_at) t FROM " . Database::t('crawl_jobs') . " WHERE status <> 'queued' OR attempts > 0")[0]['t'] ?? '');
                $queued = (int) ($rows("SELECT COUNT(*) n FROM " . Database::t('crawl_jobs') . " WHERE status = 'queued'")[0]['n'] ?? 0);
                if ($queued && $last !== '' && strtotime($last) < time() - 86400)
                    $add('stopped:' . substr($last, 0, 10), 'system', 'critical', 'The crawler has stopped', 'No crawl work since ' . date('M j, g:i a', strtotime($last)) . ' with ' . $queued . ' jobs waiting. Check its scheduled task.', $last, '/admin/crawler.php', 'Check status');
            }
            // an outside service with an issue (ServiceHealth; Jason, 2026-11-14): one case each, naming the exact issue and its
            // evidence; calls to it are held, everything else carries on
            try {
                require_once __DIR__ . '/ServiceHealth.php';
                require_once __DIR__ . '/ControlAlarms.php';
                foreach (ServiceHealth::needHelp($pdo) as $si) {
                    if (ControlAlarms::openOf($pdo, 'svc:issue:' . (int) $si['id']) || ($si['service'] === 'osm' && ControlAlarms::openOf($pdo, 'svc:osm:held'))) continue;   // its alarm's item stands for it (0171 §8, release 3b)
                    [, , $sv_] = ServiceHealth::describe($si);   // its level; its words are the issue's thread (decisions/0176 §2–3: Pulse shows all but the details)
                    require_once __DIR__ . '/Thread.php'; $sf_ = ServiceHealth::facts($pdo, $si); $st_ = $sf_['title']; $sw_ = Thread::text($sf_, 'pulse');
                    $add('svc:' . (int) $si['id'], 'system', $sv_, $st_, $sw_, (string) $si['first_at'], '/admin/config/services#svc-' . $si['service'], 'Look');
                    $out['svc:' . (int) $si['id']]['svc'] = $si;
                }
                // a block that lifted by itself (its probe answered 200): the team is told, and the server is back at half
                foreach (ServiceHealth::cameBack($pdo) as $si) {
                    $co = OutsideServices::COMPANIES[$si['service']][0] ?? $si['service'];
                    $add('svcback:' . (int) $si['id'], 'system', 'update', $co . ': ' . $si['host'] . ' answers us again',
                         (string) $si['resolved_how'] . ' Its issue #' . (int) $si['id'] . ' resolved by itself and calls to it started again' . ($si['service'] === 'osm' ? ', at half the daily budget for the ramp days (Thresholds & gates)' : '') . '. If it refuses us again it\'s held at once and a new case opens.',
                         (string) $si['resolved_at'], '/admin/config/services#svc-' . $si['service'], 'Look');
                }
            } catch (Throwable) {}
            // a site the crawler couldn't read (it may have been down): crawl it again or remove it. Failures that need no
            // one (crawled fine since, never a website, social media) are cleared on their own (IntakeStager::tidySources)
            foreach ($rows("SELECT id, url, domain, last_error, last_crawled_at FROM " . Database::t('crawler_sources') . " WHERE last_error IS NOT NULL ORDER BY last_crawled_at DESC LIMIT 20") as $c)
                $add('srcfail:' . $c['id'], 'system', 'update', 'A site couldn\'t be crawled: ' . ($c['domain'] ?: $c['url']), mb_strimwidth((string) $c['last_error'], 0, 160, '…'), $c['last_crawled_at'], '/admin/listing-intake.php', 'Crawl again');
            // the sources the engine runs on whose terms no person has read yet (or not in a year): checked here, in one tap
            try {
                require_once __DIR__ . '/crawler/Sources.php';
                $due = []; $dueAt = '';
                foreach (Sources::STANDING as $host => $s) {
                    $r = Sources::rules($pdo, $host);
                    $at = (string) ($r['terms_checked_at'] ?? '');
                    if ($at !== '' && strtotime($at) > strtotime('-1 year')) continue;
                    $became = $at !== '' ? date('Y-m-d 00:00:00', strtotime($at . ' +1 year')) : '2026-01-01 00:00:00';   // when it came due
                    if ($became > $dueAt) $dueAt = $became;   // a newly due source reopens a case resolved by hand
                    $due[] = ['host' => $host, 'name' => (string) $s[0], 'again' => $at !== '', 'url' => (string) ($r['terms_url'] ?? ''), 'restricted' => $r['licence'] === 'terms' && in_array($r['use_rule'], ['facts', 'credit'], true),
                              'rule' => 'Our rule: ' . (Sources::USES[$r['use_rule']] ?? $r['use_rule']) . ' (' . (Sources::LICENCES[$r['licence']] ?? $r['licence']) . '). ' . $s[4]];
                }
                if ($due) {   // one case for all of them (Jason, 2026-11-01), a step each
                    $add('terms:due', 'system', 'update', 'Read the terms: ' . count($due) . ' source' . (count($due) === 1 ? '' : 's'),
                         implode(', ', array_column($due, 'name')) . '. For each, read its terms and say they still allow our rule.', $dueAt, '/admin/sources.php', 'Read the terms');
                    $out['terms:due']['terms'] = $due;
                    $out['terms:due']['no_clock'] = true;   // a yearly check: no clock to run over
                }
            } catch (Throwable) {}
            // the site audit's open findings (decisions/0145 Phase 4): one case, a step for each, most paths blocked first.
            // Behind the switch site_health (staff and test accounts first); read from the audit's published findings.
            try {
                if (is_file(__DIR__ . '/SiteHealth.php')) {
                    require_once __DIR__ . '/SiteHealth.php';
                    // Site health on one path (Jason, 2026-11-22, check-register.md): its own case stops; each finding comes
                    // through the dispatcher once as a Pulse item with no push (SiteHealth::dispatch, from the board's watch),
                    // and the audit's list stays as its view on the board (SiteHealth::LIST_URL). Its actions below stay.
                }
            } catch (Throwable) {}
            // what the database holds that nothing uses (decisions/0162): rolled-back releases' leftovers past 30 days, and the
            // monthly unused-schema check; one case each, a step per item
            try {
                if (is_file(__DIR__ . '/SchemaUpkeep.php')) {
                    require_once __DIR__ . '/SchemaUpkeep.php';
                    foreach (SchemaUpkeep::pulseItems($pdo) as [$sk, $ssev, $stitle, $swhy, $swhen, $sword]) {
                        $add($sk, 'system', $ssev, $stitle, $swhy, $swhen, '/admin/config/dbupdates' . ($sk === 'schema:unused' ? '#unused' : ''), $sword);   // its tool opens on its list (2026-11-18: it opened blank)
                        $out[$sk]['no_clock'] = true;   // it waits on a decision, not a reply
                    }
                }
            } catch (Throwable) {}
            // Data cleanup (decisions/0163 §8 stage 1b): one case for what needs a person, a step per kind, and the last pass's report
            try {
                if (is_file(__DIR__ . '/DataCleanup.php')) {
                    require_once __DIR__ . '/DataCleanup.php';
                    foreach (DataCleanup::pulseItems($pdo) as [$ck, $csev, $ctitle, $cwhy, $cwhen, $cword]) {
                        $add($ck, 'system', $csev, $ctitle, $cwhy, $cwhen, '/admin/insights/cleanup', $cword);
                        $out[$ck]['no_clock'] = true;   // it waits on a person's look, not a reply
                    }
                }
            } catch (Throwable) {}
            // the service register (decisions/0163 §4, §5; Release A): a case per service at 50/75/95% of a cap or budget,
            // one for "returns empty", one per watched thing that failed or stalled; each live, gone when it's fixed
            try {
                if (is_file(__DIR__ . '/Operations.php')) {
                    require_once __DIR__ . '/Operations.php';
                    foreach (Operations::pulseItems($pdo) as [$opk, $osev, $otitle, $owhy, $owhen, $ourl]) {
                        $add($opk, 'system', $osev, $otitle, $owhy, $owhen, $ourl, 'Open it');
                        $out[$opk]['no_clock'] = true;   // it waits on a decision (a cap, a top-up, a pause), not a reply
                    }
                }
            } catch (Throwable) {}
            // the Knowledge Engine's one case, "The engine needs a person" (decisions/0164 §6.1, X3): every item it raises for
            // a person, a step each with a button that does it (the identity queue, sites that block us, banned services)
            try {
                if (is_file(__DIR__ . '/EngineCase.php')) {
                    require_once __DIR__ . '/EngineCase.php';
                    foreach (EngineCase::pulseItems($pdo) as [$ek, $esev, $etitle, $ewhy, $ewhen]) {
                        $add($ek, 'system', $esev, $etitle, $ewhy, $ewhen, '/admin/pulse', 'Open it');
                        $out[$ek]['no_clock'] = true;   // the engine keeps trying meanwhile; it waits on a decision
                    }
                }
            } catch (Throwable) {}
            // Hubs (decisions/0164 R17, §7.1, H4): a hub's stage and opening budget, and W14's staged sources to read or switch on
            try {
                if (is_file(__DIR__ . '/HubOpening.php')) {
                    require_once __DIR__ . '/HubOpening.php';
                    foreach (HubOpening::pulseItems($pdo) as [$hk, $hsev, $htitle, $hwhy, $hwhen]) { $add($hk, 'system', $hsev, $htitle, $hwhy, $hwhen, '/admin/pulse', 'Open it'); $out[$hk]['no_clock'] = true; }
                }
            } catch (Throwable) {}
            // releases waiting on someone, or paused by a failed check (decisions/0145 Phase 5): a case each, its next move a button
            try {
                if (is_file(__DIR__ . '/Releases.php')) {
                    require_once __DIR__ . '/Releases.php';
                    require_once __DIR__ . '/Switches.php';
                    if (Switches::on($pdo, 'releases')) foreach (Releases::pulseItems($pdo) as [$rk, $rsev, $rtitle, $rwhy, $rwhen, $rword]) {
                        $add($rk, 'system', $rsev, $rtitle, $rwhy, $rwhen, '/admin/config/releases#rel-' . substr($rk, 8), $rword);
                        $out[$rk]['no_clock'] = true;   // it waits on a decision, not a reply
                    }
                }
            } catch (Throwable) {}
            // the daily report didn't go out this morning (an hour after its time): send it from here
            try {
                require_once __DIR__ . '/DailyReport.php';
                if (DailyReport::ready($pdo) && (int) DailyReport::now()->format('G') >= DailyReport::hour($pdo) + 1) {
                    $t = DailyReport::today($pdo);
                    if (!$t || !(int) $t['ok']) {
                        $day = DailyReport::now()->format('Y-m-d');
                        $add('dailyrep:' . $day, 'system', 'alert', 'This morning\'s report didn\'t go out', $t && $t['error'] !== '' ? 'It tried at ' . date('g:i a', strtotime((string) $t['sent_at'])) . ' Arizona time: ' . $t['error'] : 'Its scheduled task (Daily report) didn\'t run this morning.', $day . ' 00:00:00', '/admin/config/scheduler', 'Send it now');
                        $out['dailyrep:' . $day]['no_clock'] = true;
                    }
                }
            } catch (Throwable) {}
            $files = self::fileCount();
            if ($files >= self::FILE_CEILING * 0.9)
                $add('files:' . (int) floor($files / 1000), 'system', 'critical', 'Files near the ceiling: ' . number_format($files) . ' of ' . number_format(self::FILE_CEILING), 'Clear old drafts, snapshots and orphaned uploads, or move uploads to S3.', date('Y-m-d H:i:s'), '/admin/settings.php', 'Check status');
        }
        // team posts: everyone's, and their own department's
        [, $myDep] = $staff ? Workspaces::levelOf($staff) : ['', ''];
        $all = !$staff || in_array(Workspaces::levelOf($staff)[0], ['founder', 'executive'], true);
        foreach ($rows("SELECT p.*, u.display_name, u.email FROM " . Database::t('pulse_posts') . " p JOIN users u ON u.id = p.user_id WHERE p.created_at >= ? ORDER BY p.created_at DESC LIMIT 30", [$since]) as $p) {
            if (!$all && $p['department'] !== '' && $p['department'] !== $myDep && (int) $p['user_id'] !== $uid) continue;
            $name = trim((string) $p['display_name']) ?: (string) strstr((string) $p['email'], '@', true);
            $add('post:' . $p['id'], 'team', 'event', $name . ($p['department'] !== '' ? ' to ' . (Workspaces::DEPARTMENTS[$p['department']][0] ?? '') : ' to everyone'), mb_strimwidth((string) $p['body'], 0, 160, '…'), $p['created_at'], '', 'Reply', '', [(string) $p['email'], $name]);
            $out['post:' . $p['id']]['dept'] = (string) $p['department'];
            $out['post:' . $p['id']]['body'] = (string) $p['body'];
            $out['post:' . $p['id']]['author'] = (int) $p['user_id'];
        }

        // one case per order: everything about an order (sent, approved, paid, proofs, past due, to ship, refunds,
        // disputes) folds into "order:ID", which follows it through to Complete with its next step
        $out = self::byOrder($pdo, $out, $ref, $who, $client);

        // marks: number, resolved, who has it, escalation
        $marks = [];
        if ($out && self::ready($pdo)) {
            $cases = self::casesReady($pdo);
            $in = implode(',', array_fill(0, count($out), '?'));
            $st = $pdo->prepare('SELECT k.*, u.display_name, u.email FROM ' . Database::t('pulse_marks') . ' k LEFT JOIN users u ON u.id = k.assigned_to WHERE k.item_key IN (' . $in . ')');
            $st->execute(array_keys($out));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $m) $marks[$m['item_key']] = $m + ($cases ? [] : ['esc_level' => 0, 'esc_at' => null, 'case_no' => 0]);
        }
        [$myLv] = $staff ? Workspaces::levelOf($staff) : ['founder'];
        foreach ($out as $k => &$it) {
            $m = $marks[$k] ?? null;
            $h = !empty($it['no_clock']) || !in_array($it['source'], self::CLOCKED, true) ? 0 : self::slaHours($pdo, $it['severity']);   // work cases only (Jason, 2026-11-19)
            $it['sla_due'] = $h && $it['when'] !== '' ? date('Y-m-d H:i:s', strtotime($it['when']) + $h * 3600) : null;
            $it['state'] = $m && $m['done_at'] && ($it['when'] === '' || strtotime((string) $m['done_at']) >= strtotime($it['when'])) ? 'done' : '';   // something newer reopens it
            if (!empty($it['order']['closed'])) $it['state'] = 'done';   // a completed order closes its case
            $it['over'] = $it['sla_due'] && $it['state'] === '' && strtotime($it['sla_due']) < time();
            $it['mine'] = $m && (int) $m['assigned_to'] === $uid;
            $it['who'] = $m && $m['assigned_to'] ? (trim((string) $m['display_name']) ?: (string) strstr((string) $m['email'], '@', true)) : '';
            $it['case_no'] = $m ? (int) ($m['case_no'] ?? 0) : 0;
            // escalated by hand to the Manager; still open a further SLA later, on to the Director
            $esc = $m ? (int) $m['esc_level'] : 0;
            if ($esc === 1 && $it['state'] === '' && $h && $m['esc_at'] && strtotime((string) $m['esc_at']) + $h * 3600 < time()) $esc = 2;
            // an open Control Board trip has no clock on its row, but if nobody resolves it, it still goes up (Jason, 2026-11-19: "YES"):
            // to the Manager after its level's hours, to the Director after twice that (0171 §8's note)
            if (str_starts_with((string) $k, 'alarm') && $it['state'] === '' && $it['when'] !== '' && ($th = self::slaHours($pdo, $it['severity']))) {
                $age = time() - strtotime($it['when']);
                $esc = max($esc, $age > 2 * $th * 3600 ? 2 : ($age > $th * 3600 ? 1 : 0));
            }
            $it['esc'] = $esc;
            $it['esc_to_me'] = $esc && $it['state'] === '' && (($esc === 1 && $myLv === 'manager') || ($esc === 2 && $myLv === 'director')) && (!$staff || Workspaces::levelOf($staff)[1] === $it['dept']);
        }
        unset($it);
        $out = array_values($out);
        // the Control Board's alarms grouped (decisions/0171 §8's note, Jason 2026-11-18): two or more sharing a system, level
        // and kind are one item, 'alarms:<key>', with its alarms as 'children'
        try { require_once __DIR__ . '/ControlAlarms.php'; $out = ControlAlarms::pulseGroups($pdo, $out); } catch (Throwable $x) { error_log('pulse alarm groups: ' . $x->getMessage()); }
        $rank = ['critical' => 0, 'urgent' => 1, 'alert' => 2, 'update' => 3, 'event' => 4, 'special' => 5];   // with 0172's six levels (#135)
        usort($out, fn ($a, $b) => [$a['state'] === 'done', !$a['esc_to_me'], $rank[$a['severity']], -strtotime($a['when'] ?: '2000-01-01')]
            <=> [$b['state'] === 'done', !$b['esc_to_me'], $rank[$b['severity']], -strtotime($b['when'] ?: '2000-01-01')]);
        return $out;
    }

    public const ORDER_KEYS = ['dispute', 'paid', 'refund', 'overdue', 'proof', 'ship', 'sent', 'won', 'draft', 'wodecl', 'worej', 'wolate', 'woacc', 'worcv', 'wobal', 'delbal'];

    /** Folds an order's items into one case per order, with its steps and next step (orderSteps). */
    private static function byOrder(PDO $pdo, array $out, callable $ref, callable $who, callable $client): array
    {
        $groups = [];
        foreach ($out as $k => $it) {
            $pre = strstr($k, ':', true);
            if (!in_array($pre, self::ORDER_KEYS, true)) continue;
            $oid = (int) ($it['order_id'] ?? explode(':', $k)[1]);
            $groups[$oid][] = $it; unset($out[$k]);
        }
        if (!$groups) return $out;
        $st = $pdo->prepare('SELECT * FROM orders WHERE id IN (' . implode(',', array_map('intval', array_keys($groups))) . ')'); $st->execute();
        $orders = array_column($st->fetchAll(PDO::FETCH_ASSOC), null, 'id');
        $rank = ['critical' => 0, 'urgent' => 1, 'alert' => 2, 'update' => 3, 'event' => 4, 'special' => 5];   // with 0172's six levels (#135)
        foreach ($groups as $oid => $its) {
            if (!isset($orders[$oid])) continue;
            $o = $orders[$oid];
            if (in_array((string) ($o['qb_env'] ?? ''), ['sandbox', 'mock'], true)) continue;   // test orders stay out of the Pulse (Jason, 2026-10-28): they're in Orders → Sandbox orders
            usort($its, fn ($a, $b) => [$rank[$a['severity']], -strtotime($a['when'] ?: '2000-01-01')] <=> [$rank[$b['severity']], -strtotime($b['when'] ?: '2000-01-01')]);
            $top = $its[0];
            $flow = self::orderSteps($o);
            if ($flow['tracking'] === '') { try { require_once __DIR__ . '/VendorOrders.php'; if (VendorOrders::ready($pdo)) $flow['tracking'] = VendorOrders::tracking($pdo, (int) $oid); } catch (Throwable) {} }
            $latest = max(array_map(fn ($i) => (string) $i['when'], $its));
            $work = [];
            try { require_once __DIR__ . '/WorkOrders.php'; foreach (WorkOrders::forOrder($pdo, (int) $oid) as $w) { $ws = (string) ($w['status'] ?? ($w['sent_at'] ? 'sent' : 'draft')); if ($ws === 'cancelled') continue; $d = WorkOrders::dueAt($w);
                $work[] = ['id' => (int) $w['id'], 'moves' => WorkOrders::staffMoves(['status' => $ws] + $w), 'no' => (string) $w['wo_no'], 'vendor' => (string) $w['vendor'], 'status' => $ws, 'label' => WorkOrders::STATUS[$ws] ?? $ws, 'due' => $d ? date('M j, g:i a', $d) : '',
                           'tracking' => trim((string) ($w['carrier'] ?? '') . ' ' . (string) ($w['tracking'] ?? '')), 'opened' => !empty($w['opened_at']), 'sent' => !empty($w['sent_at'])]; } } catch (Throwable) {}
            $vjobs = []; try { require_once __DIR__ . '/VendorOrders.php'; $vjobs = VendorOrders::forOrder($pdo, (int) $oid); } catch (Throwable) {}   // the printer's jobs (4OVER…), added on the order or pasted
            if ($work && ($flow['stage'] ?? '') === 'ordered') foreach ($flow['steps'] as &$fs) if ($fs['label'] === 'In production') $fs['label'] = 'With the vendor'; unset($fs);
            $mo = ['paid' => 0]; try { require_once __DIR__ . '/Orders.php'; $mo = Orders::money($pdo, (int) $oid); } catch (Throwable) {}
            $leftC = max(0, (int) $o['total_cents'] - (int) ($mo['paid'] ?? 0));
            $issue = (string) strstr($top['key'], ':', true);
            $explain = [
                'ship' => ($o['approved_at'] ? 'Approved ' . date('M j', strtotime((string) $o['approved_at'])) . ' and n' : 'N') . 'ot delivered yet, so it\'s open here until it ships, is ready for pickup, or is marked complete.' . ($work ? ' A vendor has it: this waits quietly, and the work order raises its own alert if the vendor doesn\'t accept it or misses its must-have time.' : ' No work order yet: issue one, or start production here.'),
                'overdue' => 'Its due date has passed and it isn\'t paid.', 'proof' => 'The client asked for changes on a proof.', 'sent' => 'Sent to the client, not approved yet.', 'dispute' => 'The client disputed a card payment with their bank.',
                'paid' => 'A payment came in.', 'wodecl' => 'The vendor rejected the work order.', 'worej' => 'What the vendor sent was rejected on receipt.', 'wolate' => 'A work order is past its must-have time.', 'woacc' => 'The vendor hasn\'t accepted the work order yet.', 'worcv' => 'The vendor\'s work is on its way or ready for pickup: receive it.',
                'delbal' => 'The client told us they have it, but money is still owed, so the order stays open. If the money came in, record it (the order completes); if not, collect it; or an admin completes it with a reason.',
                'wobal' => 'The vendor has shipped or finished it, but the balance isn\'t paid, so the order is held before it\'s released for delivery and the client hasn\'t been told it shipped. Once the balance is in, it moves on by itself.',
            ][$issue] ?? '';
            $out['order:' . $oid] = ['key' => 'order:' . $oid, 'source' => $top['source'], 'severity' => $top['severity'],
                'title' => 'Order ' . $ref($o) . ' · ' . $who($o), 'why' => $top['title'] . ($top['why'] !== '' && !str_contains($top['title'], $top['why']) ? ' · ' . $top['why'] : ''),
                'when' => $latest, 'url' => (int) ($o['created_by'] ?? 0) > 0 ? '/admin/backoffice/orders/' . $oid . (str_contains((string) $top['url'], '#') ? strstr((string) $top['url'], '#') : '') : '/admin/order.php?id=' . $oid, 'action' => $flow['next'] && !in_array((string) strstr($top['key'], ':', true), ['overdue', 'dispute', 'proof', 'sent', 'qbinbox', 'wobal', 'delbal'], true) ? $flow['next'][1] : $top['action'], 'about' => 'your order ' . $ref($o),
                'reply_to' => ($c = $client($o)) && $c[0] !== '' ? $c : null, 'dept' => self::deptFor($pdo, $top['source']), 'body' => $explain,
                'order' => $flow + ['id' => $oid, 'ref' => $ref($o), 'notes' => array_map(fn ($i) => $i['title'], $its), 'issue' => (string) strstr($top['key'], ':', true),
                    'status' => (string) $o['status'], 'paid' => !empty($o['paid_at']) || $o['status'] === 'paid', 'due' => (string) ($o['due_date'] ?? ''),
                    'client' => (int) ($o['created_by'] ?? 0) > 0, 'total' => '$' . number_format($o['total_cents'] / 100, 2),
                    'work' => $work, 'vjobs' => array_map(fn ($j) => trim((string) $j['vendor'] . ' ' . (string) $j['vendor_order_no']), $vjobs), 'left' => '$' . number_format($leftC / 100, 2), 'outside' => ($o['pay_mode'] ?? '') === 'outside', 'invoice' => (string) ($o['invoice_no'] ?? ''),
                    'shiphold' => (int) ($o['created_by'] ?? 0) > 0 && $o['status'] === 'pending' ? (function () use ($pdo, $o) { try { require_once __DIR__ . '/ClientOrders.php'; return ClientOrders::shipHold($pdo, $o); } catch (Throwable) { return ''; } })() : '']];
        }
        return $out;
    }

    /**
     * An order's steps and its next one: Sent → Approved → Paid → In production → Shipped / Ready → Complete. Orders
     * with nothing to ship skip the shipping step. next = [stage, label] for ClientOrders::setStage, or null.
     */
    public static function orderSteps(array $o): array
    {
        if ((int) ($o['created_by'] ?? 0) === 0) {   // bought on the site (GO Codes): paid, issued, done; nothing to make or ship
            $paid = !empty($o['paid_at']) || in_array($o['status'], ['paid', 'part_refunded', 'refunded'], true);
            $steps = [['label' => 'Ordered', 'done' => true, 'current' => !$paid], ['label' => 'Paid', 'done' => $paid, 'current' => false], ['label' => 'Codes issued', 'done' => $paid, 'current' => $paid]];
            return ['steps' => $steps, 'next' => null, 'alt' => null, 'stage' => '', 'tracking' => '', 'shop' => true,
                'closed' => in_array($o['status'], ['paid', 'refunded', 'void'], true)];
        }
        $stage = (string) ($o['stage'] ?? '');
        $made = in_array($stage, ['production', 'ordered', 'shipped', 'ready', 'complete'], true);
        $ships = ($o['ship_mode'] ?? '') !== 'none' && ($o['delivery'] ?? '') !== 'none';
        $pickup = ($o['delivery'] ?? '') === 'pickup';
        $paid = !empty($o['paid_at']) || in_array($o['status'], ['paid', 'part_refunded', 'refunded'], true);
        $steps = [['Sent', !empty($o['sent_at']) || !empty($o['approved_at'])], ['Approved', !empty($o['approved_at'])], ['Paid', $paid], ['In production', $made]];
        if ($ships) $steps[] = [$pickup ? 'Ready for pickup' : 'Shipped', in_array($stage, ['shipped', 'ready', 'complete'], true)];
        $steps[] = ['Complete', $stage === 'complete'];
        $next = null; $alt = null;
        $client = (int) ($o['created_by'] ?? 0) > 0;   // ClientOrders::isClient
        if ($client && !in_array($o['status'], ['draft', 'sent', 'void', 'refunded'], true) && $stage !== 'complete') {
            if (!$made) $next = ['production', 'Start production'];
            elseif (in_array($stage, ['production', 'ordered'], true)) {
                $next = $ships ? ($pickup ? ['ready', 'Ready for pickup'] : ['shipped', 'Mark shipped']) : ['complete', 'Mark complete'];
                if ($stage === 'production') $alt = ['ordered', 'Sent to the printer'];
            } else $next = ['complete', 'Mark complete'];
        }
        $cur = 0; foreach ($steps as $i => $st) if ($st[1]) $cur = $i;
        return ['steps' => array_map(fn ($st, $i) => ['label' => $st[0], 'done' => $st[1], 'current' => $i === $cur], $steps, array_keys($steps)),
            'next' => $next, 'alt' => $alt, 'stage' => $stage, 'tracking' => (string) ($o['tracking'] ?? ''), 'shop' => false, 'closed' => $stage === 'complete' || in_array($o['status'], ['refunded', 'void'], true)];
    }

    /** Moves an order on from its case (Start production, Mark shipped…): the order's own stage change, which also tells the client. */
    public static function stage(PDO $pdo, string $key, string $stage, string $tracking, int $uid, array $job = []): void
    {
        if (!preg_match('/^order:(\d+)$/', $key, $m)) throw new RuntimeException('That isn\'t an order.');
        require_once __DIR__ . '/ClientOrders.php';
        $vendor = trim((string) ($job['vendor'] ?? ''));
        if ($stage === 'ordered' && $vendor === '' && empty($job['vendor_order_id'])) throw new RuntimeException('Which printer or vendor has it?');
        ClientOrders::setStage($pdo, (int) $m[1], $stage, $tracking, $uid);   // the client hears only "sent to print"
        if ($vendor !== '' || !empty($job['vendor_order_id'])) self::vendorJob($pdo, (int) $m[1], $job, $uid, $key);
        self::markRow($pdo, $key);
        self::note($pdo, $key, $uid, 'stage', 'Moved to ' . (ClientOrders::STAGES[$stage] ?? $stage) . ($tracking !== '' ? ' · ' . $tracking : '') . '. The client was told.');
        if ($stage === 'complete') $pdo->prepare('UPDATE ' . Database::t('pulse_marks') . ' SET done_at = NOW(), done_by = ? WHERE item_key = ?')->execute([$uid, $key]);
    }

    /**
     * A support ticket from a client (Jason, 2026-10-30: a problem with what arrived "opens a support ticket"): the case
     * opens now, with its number, and what they wrote is its first entry. Returns the case number (0 before the Pulse update).
     */
    public static function openTicket(PDO $pdo, string $key, string $body): int
    {
        if (!self::ready($pdo)) return 0;
        $m = self::markRow($pdo, $key);
        if (self::casesReady($pdo)) $pdo->prepare('INSERT INTO ' . Database::t('pulse_notes') . " (item_key, user_id, kind, body, to_email) VALUES (?, 0, 'client', ?, '')")->execute([$key, mb_substr($body, 0, 4000)]);
        return (int) ($m['case_no'] ?? 0);
    }

    /** A ticket's case number, once it has one. */
    public static function caseNo(PDO $pdo, string $key): int
    {
        try { $st = $pdo->prepare('SELECT case_no FROM ' . Database::t('pulse_marks') . ' WHERE item_key = ?'); $st->execute([$key]); return (int) ($st->fetchColumn() ?: 0); } catch (Throwable) { return 0; }
    }

    /** The mark row for an item, made the first time a case is opened or acted on (it gets its number then). */
    private static function markRow(PDO $pdo, string $key): array
    {
        if (!preg_match('/^[a-z]+:[0-9a-z_:\- ]{1,70}$/', $key)) throw new RuntimeException('Unknown item.');
        $st = $pdo->prepare('SELECT * FROM ' . Database::t('pulse_marks') . ' WHERE item_key = ?'); $st->execute([$key]);
        if ($m = $st->fetch(PDO::FETCH_ASSOC)) return $m;
        $pdo->prepare('INSERT IGNORE INTO ' . Database::t('pulse_marks') . ' (item_key) VALUES (?)')->execute([$key]);   // only new cases take a number
        $st->execute([$key]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private static function note(PDO $pdo, string $key, int $uid, string $kind, string $body, string $to = ''): void
    {
        if (self::casesReady($pdo)) $pdo->prepare('INSERT INTO ' . Database::t('pulse_notes') . ' (item_key, user_id, kind, body, to_email) VALUES (?, ?, ?, ?, ?)')->execute([$key, $uid, $kind, $body, $to]);
        require_once __DIR__ . '/Team.php';
        Audit::log($pdo, ['user' => $uid, 'route' => 'pulse', 'action' => 'pulse.' . $kind, 'detail' => $key . ($to !== '' ? ' → ' . $to : '')]);
    }

    /** A value a case's action shows its owner one time (a setup link, a new password): [label, value]; the API sends it. */
    public static ?array $once = null;

    /**
     * The case panel's own actions (decisions/0107): on an order (resend, record a payment, due date, note, void), on a
     * team post (edit, delete: its author only) and on a note (edit, delete: its writer only). Returns the message to show.
     */
    public static function manage(PDO $pdo, string $key, string $what, array $a, int $uid): string
    {
        self::$once = null;
        if (preg_match('/^staffmail:(\d+)$/', $key, $m) && str_starts_with($what, 'sm_')) {   // a lost work email, done from its case (owners only)
            require_once __DIR__ . '/StaffMailHelp.php';
            $r = StaffMailHelp::act($pdo, (int) $m[1], $what, $a, $uid);
            self::note($pdo, $key, $uid, 'note', (string) $r['note']);
            if (!empty($r['close'])) self::mark($pdo, $key, 'done', $uid);
            self::$once = $r['once'];
            return (string) $r['message'];
        }
        if (preg_match('/^refundok:(\d+)$/', $key, $m) && in_array($what, ['refund_approve', 'refund_decline'], true)) {   // Jason's OK on a refund (decisions/0178 §1)
            require_once __DIR__ . '/Orders.php';
            if ($what === 'refund_approve') { $msg = Orders::approveRefund($pdo, (int) $m[1], $uid); self::note($pdo, $key, $uid, 'note', 'Approved. ' . $msg); self::mark($pdo, $key, 'done', $uid); return $msg; }
            $why = trim((string) ($a['why'] ?? ''));
            Orders::declineRefund($pdo, (int) $m[1], $why, $uid);
            self::note($pdo, $key, $uid, 'note', 'Declined: ' . $why);
            self::mark($pdo, $key, 'done', $uid);
            return 'Declined: nothing was ' . ((Orders::refundRequest($pdo, (int) $m[1])['kind'] ?? '') === 'credit' ? 'credited' : 'refunded') . '. The order shows your reason.';
        }
        if (preg_match('/^refundsend:(\d+)$/', $key, $m) && $what === 'refund_sent') {   // the money sent back by a person
            require_once __DIR__ . '/Orders.php';
            Orders::refundSent($pdo, (int) $m[1], (string) ($a['how'] ?? ''), $uid);
            self::note($pdo, $key, $uid, 'note', 'Sent: ' . trim((string) ($a['how'] ?? '')));
            self::mark($pdo, $key, 'done', $uid);
            return 'Recorded as sent back. The order shows it.';
        }
        if (preg_match('/^order:(\d+)$/', $key, $m)) {
            require_once __DIR__ . '/ClientOrders.php';
            $oid = (int) $m[1];
            $o = Orders::get($pdo, $oid);
            if (!$o) throw new RuntimeException('That order isn\'t here.');
            switch ($what) {
                case 'resend': ClientOrders::send($pdo, $oid, ClientOrders::DAYS, $uid); self::note($pdo, $key, $uid, 'note', 'Sent the order to the client again.'); return 'Sent to the client again.';
                case 'pay':
                    if (trim((string) ($a['amount'] ?? '')) === '') throw new RuntimeException('Enter the amount received.');
                    $c = ClientOrders::cents((string) ($a['amount'] ?? ''));
                    if ($c <= 0) throw new RuntimeException('Enter the amount received.');
                    ClientOrders::recordPayment($pdo, $oid, $c, (string) ($a['method'] ?? 'other'), trim((string) ($a['ref'] ?? '')), $uid);
                    return 'Payment of ' . Orders::usd($c) . ' recorded.';
                case 'due':
                    $d = (string) ($a['date'] ?? '');
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !strtotime($d)) throw new RuntimeException('Choose the new due date.');
                    $pdo->prepare('UPDATE orders SET due_date = ? WHERE id = ?')->execute([$d, $oid]);
                    Orders::event($pdo, $oid, 'due_date', 'Due ' . date('M j, Y', strtotime($d)), $uid);
                    return 'Due date changed.';
                case 'note':
                    $t = trim((string) ($a['text'] ?? ''));
                    if ($t === '') throw new RuntimeException('Write the note first.');
                    Orders::note($pdo, $oid, $t, $uid);
                    return 'Noted on the order.';
                case 'void': ClientOrders::cancel($pdo, $oid, trim((string) ($a['why'] ?? '')), $uid); return 'Voided.';
            }
        }
        if (in_array($what, ['sugg_create', 'sugg_dismiss'], true) && preg_match('/^sugg:(\d+)$/', $key, $m)) {   // a missing place, done from the case
            require_once __DIR__ . '/Suggestions.php';
            if ($what === 'sugg_dismiss') { Suggestions::decide($pdo, (int) $m[1], 'dismissed', $uid); self::note($pdo, $key, $uid, 'note', 'Set aside: not a real place, or not open.'); self::mark($pdo, $key, 'done', $uid); return 'Set aside. The case is closed.'; }
            require_once __DIR__ . '/AddBusiness.php';
            $id = AddBusiness::fromSuggestion($pdo, (int) $m[1], $a, $uid);
            self::note($pdo, $key, $uid, 'note', 'Created the listing #' . $id . '.');
            self::mark($pdo, $key, 'done', $uid);
            return 'Created: it\'s live at /listing/view.php?id=' . $id . '. The case is closed.';
        }
        if (preg_match('/^mkt_settle:(\d+)$/', $what, $m) && $key === 'mkt:disputes') {   // a Marketplace trade's problem, settled from the case
            require_once __DIR__ . '/../admin/Access.php';
            [$ok, $why] = Access::can('market/offers.edit', ['min' => 'staff', 'depts' => ['ops', 'svc']]);
            if (!$ok) throw new RuntimeException(($why ?: 'Your role can\'t settle Marketplace trades') . '.');
            require_once __DIR__ . '/MarketOffers.php';
            try { $msg = MarketOffers::settle($pdo, $uid, (int) $m[1], (string) ($a['outcome'] ?? ''), (string) ($a['note'] ?? '')); }
            catch (InvalidArgumentException $x) { throw new RuntimeException($x->getMessage()); }
            self::note($pdo, $key, $uid, 'note', 'Settled Marketplace trade #' . (int) $m[1] . ': ' . (MarketOffers::OUTCOMES[(string) ($a['outcome'] ?? '')] ?? '') . '.');
            return $msg;
        }
        if (preg_match('/^likes_decide:(\d+)$/', $what, $m) && $key === 'likes:checks') {   // a like check, decided from the case
            require_once __DIR__ . '/../admin/Access.php';
            [$ok, $why] = Access::can('safety/likes.decide', ['min' => 'staff', 'depts' => ['ops']]);
            if (!$ok) throw new RuntimeException(($why ?: 'Your role can\'t decide like checks') . '.');
            require_once __DIR__ . '/LikeGuard.php';
            $msg = LikeGuard::decide($pdo, (int) $m[1], (string) ($a['what'] ?? ''), $uid);
            self::note($pdo, $key, $uid, 'note', 'Like check #' . (int) $m[1] . ': ' . $msg);
            return $msg;
        }
        if (preg_match('/^mkt_ok:(\d+)$/', $what, $m) && $key === 'mkt:held') {   // a held Marketplace post, let show from the case
            require_once __DIR__ . '/../admin/Access.php';
            [$ok, $why] = Access::can('market/items.edit', ['min' => 'staff', 'depts' => ['ops', 'svc', 'con']]);
            if (!$ok) throw new RuntimeException(($why ?: 'Your role can\'t change Marketplace posts') . '.');
            require_once __DIR__ . '/Market.php';
            $msg = Market::staffAct($pdo, $uid, (int) $m[1], 'ok');
            self::note($pdo, $key, $uid, 'note', 'Let Marketplace post #' . (int) $m[1] . ' show.');
            return $msg;
        }
        if (preg_match('/^dom(order|renew|tk|verify):\d+$/', $key) && str_starts_with($what, 'dom_')) {   // a domain case's steps (AccountDomains::pulseDo)
            require_once __DIR__ . '/../admin/Access.php';
            [$ok, $why] = Access::can('backoffice/orders.edit', ['min' => 'staff', 'depts' => ['svc', 'fin', 'ops']]);
            if (!$ok) throw new RuntimeException(($why ?: 'Your role can\'t change orders') . '.');
            require_once __DIR__ . '/AccountDomains.php';
            $msg = AccountDomains::pulseDo($pdo, $key, $what, $a, $uid);
            self::note($pdo, $key, $uid, 'note', $msg);
            if (in_array($what, ['dom_retry', 'dom_refund', 'dom_clear'], true)) self::mark($pdo, $key, 'done', $uid);
            return $msg;
        }
        if (preg_match('/^outreq:(\d+)$/', $key, $om) && in_array($what, ['out_answer', 'out_chase'], true)) {   // a request to an outside company: chase it, or record their answer (closes it)
            require_once __DIR__ . '/OutsideServices.php';
            $msg = OutsideServices::pulseDo($pdo, (int) $om[1], $what, $a, $uid);
            self::note($pdo, $key, $uid, 'note', $msg);
            if ($what === 'out_answer') self::mark($pdo, $key, 'done', $uid);
            return $msg;
        }
        if ($what === 'nc_check' && $key === 'namecom:down') {   // Name.com: does it accept our sign-in now?
            require_once __DIR__ . '/Domains.php';
            try { $msg = Domains::ncCheck(); } catch (Throwable $x) { $nc = Domains::ncDown(); throw new RuntimeException('Still refused' . ($nc ? ': ' . $nc['error'] : ': ' . $x->getMessage())); }
            self::note($pdo, $key, $uid, 'note', $msg);
            self::mark($pdo, $key, 'done', $uid);
            return $msg;
        }
        if (preg_match('/^schema:(leftovers|unused)$/', $key) && preg_match('/^schema_keep:[0-9a-f]+$/', $what)) {   // Keep it, on a schema case (decisions/0162)
            require_once __DIR__ . '/SchemaUpkeep.php';
            $msg = SchemaUpkeep::pulseKeep($what, $uid);
            self::note($pdo, $key, $uid, 'note', $msg);
            return $msg;
        }
        if (preg_match('/^report:(\d+)$/', $key, $rm) && str_starts_with($what, 'upload_')) {   // a reported photo's thread, in its case (decisions/0176 §6.3)
            require_once __DIR__ . '/UploadThreads.php';
            $msg = UploadThreads::manage($pdo, (int) $rm[1], substr($what, 7), $a, $uid);
            self::note($pdo, $key, $uid, 'note', $msg);
            return $msg;
        }
        if (preg_match('/^alarm:(\d+)$/', $key, $am) && str_starts_with($what, 'alarm_')) {   // an alarm's own buttons (ControlAlarms::pulseDo)
            require_once __DIR__ . '/ControlAlarms.php';
            $who = '';
            try { $st = $pdo->prepare('SELECT display_name FROM users WHERE id = ?'); $st->execute([$uid]); $who = (string) ($st->fetchColumn() ?: ''); } catch (Throwable) {}
            $msg = ControlAlarms::pulseDo($pdo, (int) $am[1], $what, $a, $uid, $who);
            self::note($pdo, $key, $uid, 'note', $msg);
            return $msg;
        }
        if (str_starts_with($key, 'ops:') && str_starts_with($what, 'ops_')) {   // the service register's steps (Operations::pulseDo)
            require_once __DIR__ . '/Operations.php';
            $who = '';
            try { $st = $pdo->prepare('SELECT display_name FROM users WHERE id = ?'); $st->execute([$uid]); $who = (string) ($st->fetchColumn() ?: ''); } catch (Throwable) {}
            $msg = Operations::pulseDo($pdo, $key, $what, $a, $uid, $who);
            self::note($pdo, $key, $uid, 'note', $msg);
            return $msg;
        }
        if ($key === 'hubs:all' && str_starts_with($what, 'hub_')) {   // a hub's stage and budget, a source's terms or switch (HubOpening::pulseDo)
            require_once __DIR__ . '/HubOpening.php';
            require_once __DIR__ . '/../admin/Access.php';
            [$ok, $why] = Access::can('hubs.open', ['min' => 'director', 'depts' => []]);   // a director moves each stage (R17)
            if (!$ok) throw new RuntimeException($why . '.');
            $msg = HubOpening::pulseDo($pdo, $what, $a, $uid);
            self::note($pdo, $key, $uid, 'note', $msg);
            return $msg;
        }
        if ($key === 'engine:person' && str_starts_with($what, 'eng_')) {   // the engine's steps (EngineCase::pulseDo)
            require_once __DIR__ . '/EngineCase.php';
            require_once __DIR__ . '/../admin/Access.php';
            [$ok, $why] = Access::can('engine.person', ['min' => 'manager', 'depts' => []]);   // its own rule (Permissions), managers and up by default
            if (!$ok) throw new RuntimeException($why . '.');
            $who = '';
            try { $st = $pdo->prepare('SELECT display_name FROM users WHERE id = ?'); $st->execute([$uid]); $who = (string) ($st->fetchColumn() ?: ''); } catch (Throwable) {}
            $msg = EngineCase::pulseDo($pdo, $what, $a, $uid, $who);
            self::note($pdo, $key, $uid, 'note', $msg);
            return $msg;
        }
        if ($key === 'cleanup:checks' && str_starts_with($what, 'cleanup_seen:')) {   // Data cleanup: "Looked at them" for one kind
            require_once __DIR__ . '/DataCleanup.php';
            require_once __DIR__ . '/../admin/Access.php';
            [$ok, $why] = Access::can('insights.cleanup', DataCleanup::RULE_RUN);
            if (!$ok) throw new RuntimeException($why . '.');
            $msg = DataCleanup::pulseDo($pdo, $what, $uid);
            self::note($pdo, $key, $uid, 'note', $msg);
            return $msg;
        }
        if (preg_match('/^release:(\d+)$/', $key, $rm) && preg_match('/^rel_(approve|stage|signoff|publish|next|resume|off|back)$/', $what, $ra)) {   // a release's next move (decisions/0145 Phase 5)
            require_once __DIR__ . '/Releases.php';
            $msg = Releases::act($pdo, (int) $rm[1], $ra[1], $a, $uid);
            self::note($pdo, $key, $uid, 'note', $msg);
            return $msg;
        }
        if ($what === 'report_send' && preg_match('/^dailyrep:\d{4}-\d{2}-\d{2}$/', $key)) {   // the morning's report, sent now (counts as today's)
            require_once __DIR__ . '/DailyReport.php';
            $msg = DailyReport::send($pdo, true);
            self::note($pdo, $key, $uid, 'note', $msg);
            self::mark($pdo, $key, 'done', $uid);
            return $msg;
        }
        if (preg_match('/^inv_choose:(\d+)$/', $what, $im) && $key === 'invlater:open') {   // an order still on invoice later, chosen for
            require_once __DIR__ . '/ClientOrders.php';
            $msg = ClientOrders::invoiceLaterChoose($pdo, (int) $im[1], (string) ($a['choice'] ?? ''), (string) ($a['why'] ?? ''), $uid);
            self::note($pdo, $key, $uid, 'note', $msg);
            if (!ClientOrders::invoiceLaterOpen($pdo)) self::mark($pdo, $key, 'done', $uid);
            return $msg;
        }
        if (preg_match('/^wo_move:(\d+)$/', $what, $wm) && preg_match('/^order:(\d+)$/', $key, $om)) {   // a work order moved on for the vendor
            require_once __DIR__ . '/WorkOrders.php';
            $w = WorkOrders::get($pdo, (int) $wm[1], (int) $om[1]);
            if (!$w) throw new RuntimeException('That work order isn\'t on this order.');
            $who = ''; try { $st = $pdo->prepare('SELECT display_name FROM users WHERE id = ?'); $st->execute([$uid]); $who = trim((string) $st->fetchColumn()); } catch (Throwable) {}
            $msg = WorkOrders::staffMove($pdo, $w, (string) ($a['to'] ?? ''), $a, $uid, $who);
            self::note($pdo, $key, $uid, 'note', $msg . ' Marked for the vendor.');
            return $msg;
        }
        if ($what === 'ses_run' && $key === 'ses:setup') {   // the email setup's run, now rather than at the next 15 minutes
            require_once __DIR__ . '/SesMail.php';
            $did = SesMail::autoSetup($pdo);
            $err = SesMail::state($pdo, 'auto_error');
            $msg = $did ? implode(' ', $did) : 'Ran: nothing new to do yet.';
            self::note($pdo, $key, $uid, 'note', 'Ran the email setup by hand: ' . $msg);
            if ($err !== '') throw new RuntimeException('Ran, and stopped: ' . $err);
            return $msg;
        }
        if ($what === 'crawl_retry' && preg_match('/^crawl:(\d+|failed)$/', $key)) {   // every failed crawl job back in the queue; their cases close as they leave "failed"
            require_once __DIR__ . '/crawler/CrawlQueue.php';
            $n = CrawlQueue::requeueFailed($pdo);
            self::note($pdo, $key, $uid, 'note', 'Put ' . $n . ' failed crawl job' . ($n === 1 ? '' : 's') . ' back in the queue.');
            self::mark($pdo, $key, 'done', $uid);
            return $n ? $n . ' failed job' . ($n === 1 ? '' : 's') . ' back in the queue. The crawler picks them up on its next runs, 5 at a time.' : 'Nothing was failed: already back in the queue.';
        }
        if (in_array($what, ['svc_resolve', 'svc_probe'], true) && preg_match('/^svc:(\d+)$/', $key, $m)) {   // an outside service's issue (ServiceHealth)
            require_once __DIR__ . '/ServiceHealth.php';
            if ($what === 'svc_probe') {
                if (!ServiceHealth::probeSoon($pdo, (int) $m[1])) throw new RuntimeException('No check to send: it has no probe, it\'s resolved already, or it\'s an OpenStreetMap server, which we don\'t contact while it\'s held.');
                self::note($pdo, $key, $uid, 'note', 'Asked for its probe with the next run.');
                return 'Its probe goes out with the next crawler run (within 5 minutes). A 200 resolves it by itself; its answer shows here.';
            }
            $ok = ServiceHealth::resolve($pdo, (int) $m[1], $uid, 'Marked resolved in Pulse.');
            self::note($pdo, $key, $uid, 'note', 'Marked resolved: calls start again.');
            self::mark($pdo, $key, 'done', $uid);
            return $ok ? 'Resolved: calls to it start again. If it fails again, a new case opens with the new evidence.' : 'Already resolved.';
        }
        if ($what === 'guide_retry' && preg_match('/^guidefail:\d+$/', $key)) {   // every guide on demand that used its tries, started again
            require_once __DIR__ . '/OnDemand.php';
            $n = OnDemand::retryGivenUp($pdo);
            self::note($pdo, $key, $uid, 'note', 'Started ' . $n . ' guide' . ($n === 1 ? '' : 's') . ' on demand again.');
            self::mark($pdo, $key, 'done', $uid);
            return $n ? $n . ' guide' . ($n === 1 ? '' : 's') . ' started again, three more tries each. The case comes back if they fail again.' : 'Nothing waiting: already started again.';
        }
        if ($what === 'source_remove' && preg_match('/^srcfail:(\d+)$/', $key, $m)) {
            require_once __DIR__ . '/crawler/IntakeStager.php';
            IntakeStager::removeSource($pdo, (int) $m[1]);
            return 'Removed. What it found stays.';
        }
        if (preg_match('/^terms_read:([a-z0-9.-]+)$/', $what, $m) && $key === 'terms:due') $what = 'terms_read';   // one source of the terms case
        elseif ($what === 'terms_read' && !preg_match('/^terms:([a-z0-9.-]+)$/', $key, $m)) $m = null;
        if ($what === 'terms_read' && $m && $m[1] !== 'due') {   // a person read the source's terms: recorded with them and today
            require_once __DIR__ . '/crawler/Sources.php';
            if (!isset(Sources::STANDING[$m[1]])) throw new RuntimeException('That source isn\'t in the register.');
            $r = Sources::rules($pdo, $m[1]);
            Sources::markRead($pdo, $m[1], $uid);   // only that they were read: the rule stays as it is
            self::note($pdo, $key, $uid, 'note', 'Read ' . Sources::STANDING[$m[1]][0] . '\'s terms: they allow our rule (' . (Sources::USES[$r['use_rule']] ?? $r['use_rule']) . ').');
            return 'Recorded: ' . Sources::STANDING[$m[1]][0] . '\'s terms read today.';
        }
        if ($key === 'sitehealth:open' && preg_match('/^sh_run:([a-z-]{3,20})$/', $what, $m)) {   // ask GitHub to run one of the audit's checks (AuditClock)
            require_once __DIR__ . '/AuditClock.php';
            $msg = AuditClock::runNow($pdo, $m[1]);
            self::note($pdo, $key, $uid, 'note', $msg);
            return $msg;
        }
        if ($key === 'sitehealth:open' && ($what === 'sh_refresh' || preg_match('/^sh_mark:(f-[a-f0-9]{6,40})$/', $what, $m))) {   // the site audit's findings (SiteHealth)
            require_once __DIR__ . '/SiteHealth.php';
            if ($what === 'sh_refresh') {
                $d = SiteHealth::load($pdo, true);
                if (!$d['ok'] && $d['error'] !== '') throw new RuntimeException('Couldn\'t read the newest findings: ' . $d['error']);
                $n = count(SiteHealth::open($pdo, $d));
                return 'Read the audit of ' . ($d['at'] !== '' ? gmdate('M j, g:i A', strtotime($d['at'])) . ' UTC' : 'just now') . ': ' . $n . ' open finding' . ($n === 1 ? '' : 's') . '.';
            }
            require_once __DIR__ . '/../admin/Access.php';
            [$ok, $why] = Access::can('insights/site_health.decide', ['min' => 'staff', 'depts' => ['eng', 'ops']]);
            if (!$ok) throw new RuntimeException(($why ?: 'Your role can\'t record site health findings') . '.');
            $msg = SiteHealth::mark($pdo, $m[1], (string) ($a['state'] ?? ''), (string) ($a['note'] ?? ''), $uid);
            self::note($pdo, $key, $uid, 'note', $msg . (trim((string) ($a['note'] ?? '')) !== '' ? ' Why: ' . mb_substr(trim((string) $a['note']), 0, 300) : ''));
            return $msg;
        }
        if (in_array($what, ['post_edit', 'post_delete'], true) && preg_match('/^post:(\d+)$/', $key, $m)) {
            $st = $pdo->prepare('SELECT user_id FROM ' . Database::t('pulse_posts') . ' WHERE id = ?'); $st->execute([(int) $m[1]]);
            if ((int) $st->fetchColumn() !== $uid) throw new RuntimeException('Only the person who posted it can change it.');
            if ($what === 'post_delete') { $pdo->prepare('DELETE FROM ' . Database::t('pulse_posts') . ' WHERE id = ?')->execute([(int) $m[1]]); return 'Deleted.'; }
            $t = trim((string) ($a['text'] ?? ''));
            if ($t === '' || mb_strlen($t) > 4000) throw new RuntimeException('Write the post (up to 4,000 characters).');
            $pdo->prepare('UPDATE ' . Database::t('pulse_posts') . ' SET body = ? WHERE id = ?')->execute([$t, (int) $m[1]]);
            return 'Saved.';
        }
        if (in_array($what, ['note_edit', 'note_delete'], true)) {
            $st = $pdo->prepare('SELECT user_id, kind FROM ' . Database::t('pulse_notes') . ' WHERE id = ? AND item_key = ?'); $st->execute([(int) ($a['id'] ?? 0), $key]);
            $n = $st->fetch(PDO::FETCH_ASSOC);
            if (!$n || (int) $n['user_id'] !== $uid) throw new RuntimeException('Only the person who wrote it can change it.');
            if ($n['kind'] === 'reply') throw new RuntimeException('A reply was emailed: it stays as it was sent.');
            if ($what === 'note_delete') { $pdo->prepare('DELETE FROM ' . Database::t('pulse_notes') . ' WHERE id = ?')->execute([(int) $a['id']]); return 'Deleted.'; }
            $t = trim((string) ($a['text'] ?? ''));
            if ($t === '') throw new RuntimeException('Write the note first.');
            $pdo->prepare('UPDATE ' . Database::t('pulse_notes') . ' SET body = ? WHERE id = ?')->execute([mb_substr($t, 0, 4000), (int) $a['id']]);
            return 'Saved.';
        }
        throw new RuntimeException('That can\'t be done here.');
    }

    /** The Create the listing form for a missing business, filled in from what was found: [name, label, type, value, options]. */
    private static function missingForm(array $s): array
    {
        require_once __DIR__ . '/AddBusiness.php'; require_once __DIR__ . '/crawler/IntakeStager.php';
        $pdo = Database::connection();
        $p = AddBusiness::prefill($s);
        $parts = array_map('trim', explode(',', (string) $p['address']));   // "123 Main St, Show Low, AZ 85901"
        $street = $parts[0] ?? ''; $town = count($parts) >= 3 ? $parts[1] : ''; $state = preg_match('/\b([A-Z]{2})\b/', (string) end($parts), $m) ? $m[1] : 'AZ';
        $cats = IntakeStager::leafCategories($pdo); $pick = 0;
        try { $sg = IntakeStager::suggestFor($pdo, ['name' => $p['name'], 'what' => ['description' => $p['offers']]]); $pick = (int) ($sg[0]['id'] ?? 0); } catch (Throwable) {}
        $catOpts = [['', 'Choose its category']]; foreach ($cats as $c) $catOpts[] = [(string) $c['id'], $c['group_name'] . ' › ' . $c['plain']];
        // the exact category only: "what it is" (AddBusiness::KINDS) is the public form's rough pick for when no category is chosen
        return [['name', 'Name', 'text', $p['name']], ['category_id', 'Category', 'select', $pick ? (string) $pick : '', $catOpts],
                ['address', 'Street address', 'text', $street], ['town', 'Town', 'text', $town], ['state', 'State', 'text', $state],
                ['phone', 'Phone', 'tel', $p['phone']], ['website', 'Website', 'url', $p['website'] ?: $p['facebook']], ['hours', 'Hours', 'text', $p['hours']], ['offers', 'What it offers', 'textarea', $p['offers']]];
    }

    /**
     * Tells staff of a case that's now theirs (decisions/0172 §1.7, §4.5): through the one dispatcher, by email (the case
     * is already in their Pulse). Never throws.
     */
    private static function tellStaff(PDO $pdo, string $kind, string $key, array $to, int $by, string $did): void
    {
        if (!$to) return;
        try {
            require_once __DIR__ . '/Notify.php';
            $st = $pdo->prepare('SELECT display_name FROM users WHERE id = ?'); $st->execute([$by]);
            $who = trim((string) $st->fetchColumn()) ?: 'A teammate';
            $no = self::caseNo($pdo, $key);
            Notify::send($pdo, $kind, ['staff' => $to, 'except' => [$by]], ['thing' => $kind . ':' . $key . ':' . time(), 'url' => '/admin/pulse/stream',
                'pulse_ref' => 'case:' . $key, 'from' => ['user', $by, $who]],
                ['title' => $who . ' ' . $did . ' ' . ($no ? 'case #' . $no : 'a Pulse case'), 'body' => 'Find it at the top of your Pulse stream (' . $key . ').']);
        } catch (Throwable $e) { error_log('pulse tell: ' . $e->getMessage()); }
    }

    /** Who an escalation reaches: the active Managers (level 1) or Directors (level 2) of the case's department, other than who escalated it; none, the Founder. */
    private static function escalatedTo(PDO $pdo, string $key, int $lv, int $by): array
    {
        try {
            $dept = '';
            foreach (self::items($pdo, null, 0) as $it) if ($it['key'] === $key) { $dept = (string) $it['dept']; break; }
            $want = $lv >= 2 ? 'director' : 'manager';
            $out = [];
            require_once __DIR__ . '/Workspaces.php';
            foreach ($pdo->query("SELECT * FROM " . Database::t('staff_accounts') . " WHERE status = 'active' AND user_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $s) {
                [$l, $d] = Workspaces::levelOf($s);
                if ($l === $want && ($dept === '' || $d === $dept) && (int) $s['user_id'] !== $by) $out[] = (int) $s['user_id'];
            }
            if (!$out) foreach ($pdo->query("SELECT * FROM " . Database::t('staff_accounts') . " WHERE status = 'active' AND user_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $s)
                if (Workspaces::levelOf($s)[0] === 'founder') $out[] = (int) $s['user_id'];
            return $out;
        } catch (Throwable $e) { error_log('pulse escalated to: ' . $e->getMessage()); return []; }
    }

    /** Resolve, reopen, take, let go, assign (to a staff member's user id), escalate. */
    /**
     * A Control Board trip goes to an admin (decisions/0171 §7's note, release 3c): its item is put at this escalation
     * level (2: the Director's) with a note, never lowered. Before Pulse's cases update, it does nothing.
     */
    public static function escalateTo(PDO $pdo, string $key, int $lv, string $note): void
    {
        if (!self::casesReady($pdo)) return;
        $m = self::markRow($pdo, $key);
        if ((int) $m['esc_level'] >= $lv) return;
        $pdo->prepare('UPDATE ' . Database::t('pulse_marks') . ' SET esc_level = ?, esc_at = NOW() WHERE item_key = ?')->execute([min(2, $lv), $key]);
        self::note($pdo, $key, 0, 'escalate', $note);
    }

    public static function mark(PDO $pdo, string $key, string $do, int $uid, int $to = 0): void
    {
        if (str_starts_with($key, 'alarms:')) {   // a group of alarms: each of its alarms gets the mark (done acknowledges each)
            require_once __DIR__ . '/ControlAlarms.php';
            foreach (ControlAlarms::groupMembers($pdo, $key) as $id) self::mark($pdo, 'alarm:' . $id, $do, $uid, $to);
            return;
        }
        self::markRow($pdo, $key);
        switch ($do) {
            case 'done': $pdo->prepare('UPDATE ' . Database::t('pulse_marks') . ' SET done_at = NOW(), done_by = ? WHERE item_key = ?')->execute([$uid, $key]); self::note($pdo, $key, $uid, 'resolve', 'Marked resolved.');
                if (preg_match('/^alarm:(\d+)$/', $key, $am)) { require_once __DIR__ . '/ControlAlarms.php'; ControlAlarms::ack($pdo, (int) $am[1], $uid, 'Pulse'); }   // Done in Pulse acknowledges the alarm (0171 §8)
                break;
            case 'undo': $pdo->prepare('UPDATE ' . Database::t('pulse_marks') . ' SET done_at = NULL, done_by = NULL WHERE item_key = ?')->execute([$key]); self::note($pdo, $key, $uid, 'reopen', 'Reopened.');
                // an alarm's acknowledgement stays: who saw it and when is a record (CLAUDE.md "Edit or Undo"), never undone
                break;
            case 'mine': $pdo->prepare('UPDATE ' . Database::t('pulse_marks') . ' SET assigned_to = ? WHERE item_key = ?')->execute([$uid, $key]); self::note($pdo, $key, $uid, 'assign', 'Took it.'); break;
            case 'letgo': $pdo->prepare('UPDATE ' . Database::t('pulse_marks') . ' SET assigned_to = NULL WHERE item_key = ?')->execute([$key]); self::note($pdo, $key, $uid, 'assign', 'Let it go.'); break;
            case 'assign':
                $st = $pdo->prepare("SELECT s.name, s.staff_id FROM " . Database::t('staff_accounts') . " s WHERE s.user_id = ? AND s.status = 'active'"); $st->execute([$to]);
                $s = $st->fetch(PDO::FETCH_ASSOC);
                if (!$s) throw new RuntimeException('Choose someone on the team.');
                $pdo->prepare('UPDATE ' . Database::t('pulse_marks') . ' SET assigned_to = ? WHERE item_key = ?')->execute([$to, $key]);
                self::note($pdo, $key, $uid, 'assign', 'Assigned to ' . ($s['name'] ?: $s['staff_id']) . '.');
                if ($to !== $uid) self::tellStaff($pdo, 'case.assigned', $key, [$to], $uid, 'assigned you');   // they're told (decisions/0172 §1.7)
                break;
            case 'escalate':
                if (!self::casesReady($pdo)) throw new RuntimeException('Run the Pulse update (2026-12-15_pulse_cases.sql) first.');
                $m = self::markRow($pdo, $key);
                $lv = min(2, (int) $m['esc_level'] + 1);
                $pdo->prepare('UPDATE ' . Database::t('pulse_marks') . ' SET esc_level = ?, esc_at = NOW() WHERE item_key = ?')->execute([$lv, $key]);
                self::note($pdo, $key, $uid, 'escalate', 'Escalated to the ' . ($lv === 1 ? 'Manager' : 'Director') . '.');
                self::tellStaff($pdo, 'case.escalated', $key, self::escalatedTo($pdo, $key, $lv, $uid), $uid, 'escalated to you');   // gap 4: escalating told no one
                break;
            default: throw new RuntimeException('Unknown action.');
        }
        if (preg_match('/^fb:(\d+)$/', $key, $fm) && in_array($do, ['done', 'undo'], true))   // a client's ticket: the order shows it resolved, or open again
            try { $pdo->prepare("UPDATE order_feedback SET resolved_at = " . ($do === 'done' ? 'NOW()' : 'NULL') . " WHERE id = ? AND kind = 'problem'")->execute([(int) $fm[1]]); } catch (Throwable) {}
    }

    /**
     * Post on a case: 'reply' emails the sender (the item's reply_to) and keeps a copy in the history; 'team' is a note
     * on the case for the department. Without a case ($key ''), posts to a department's stream ($dept '' = everyone).
     */
    public static function post(PDO $pdo, ?array $staff, int $uid, string $key, string $mode, string $body, string $dept = ''): string
    {
        $body = trim($body);
        if ($body === '') throw new RuntimeException('Write something first.');
        if (mb_strlen($body) > 4000) throw new RuntimeException('That\'s too long for one post.');
        if ($key === '') {
            require_once __DIR__ . '/Workspaces.php';
            if ($dept !== '' && !isset(Workspaces::DEPARTMENTS[$dept])) $dept = '';
            $pdo->prepare('INSERT INTO ' . Database::t('pulse_posts') . ' (user_id, department, body) VALUES (?, ?, ?)')->execute([$uid, $dept, $body]);
            require_once __DIR__ . '/Team.php';
            Audit::log($pdo, ['user' => $uid, 'route' => 'pulse', 'action' => 'pulse.post', 'detail' => $dept ?: 'everyone']);
            return 'Posted to ' . ($dept !== '' ? Workspaces::DEPARTMENTS[$dept][0] : 'everyone') . '.';
        }
        self::markRow($pdo, $key);
        if ($mode === 'reply') {
            $it = null;
            foreach (self::items($pdo, $staff, $uid) as $x) if ($x['key'] === $key) { $it = $x; break; }
            if (!$it || !$it['reply_to']) throw new RuntimeException('This one has no sender to reply to. Post it as a note to the team.');
            [$email, $name] = $it['reply_to'];
            if (str_starts_with($key, 'post:')) { self::note($pdo, $key, $uid, 'reply', $body); return 'Replied to ' . ($name ?: 'them') . '.'; }
            $me = $pdo->prepare('SELECT display_name, email FROM users WHERE id = ?'); $me->execute([$uid]); $u = $me->fetch(PDO::FETCH_ASSOC) ?: [];
            $sig = trim((string) ($u['display_name'] ?? '')) ?: 'The Traversence team';
            require_once __DIR__ . '/Mailer.php';
            Mailer::send($email, 'About ' . ($it['about'] ?: 'your message') . ' · Traversence',
                ($name !== '' ? 'Hi ' . $name . ",\n\n" : '') . $body . "\n\n" . $sig . "\nTraversence · support@traversence.com\n", $name ?: null);
            self::note($pdo, $key, $uid, 'reply', $body, $email);
            return 'Sent to ' . ($name ?: $email) . '.';
        }
        self::note($pdo, $key, $uid, 'note', $body);
        return 'Noted on the case.';
    }

    /**
     * "How to clear this": the procedure for a case, as numbered steps. Each step: t (what to do), h (why or how),
     * act (what its button does: ['tool', url] opens it beside the case, ['reply', text] writes the email, ['stage', s, label]
     * moves an order on, ['resolve'] resolves), auto (true when the data already shows it's done). Steps without auto are
     * ticked by hand; ticks are kept on the case (pulse_notes 'stepN').
     */
    public static function playbook(array $it): array
    {
        $pre = (string) strstr($it['key'], ':', true);
        $name = $it['reply_to'][1] ?? '';
        $hi = $name !== '' ? 'Hi ' . strtok($name, ' ') . ",\n\n" : '';
        // $ext: an outside service the step needs (SERVICES key): its link sits in the step
        $s = fn (string $t, string $h, ?array $act = null, bool $auto = false, string $ext = '') => ['t' => $t, 'h' => $h, 'act' => $act, 'auto' => $auto]
            + (isset(self::SERVICES[$ext]) ? ['ext' => self::SERVICES[$ext]] : []);
        if ($pre === 'alarms') {   // a group of alarms: each one's row on the board; their own steps are on each child
            $st = [];
            foreach ((array) ($it['children'] ?? []) as $c) $st[] = $s((string) $c['title'], (string) $c['why'], ['tool', (string) $c['url']]);
            $st[] = $s('Acknowledge them all', 'Done on this row acknowledges every alarm in it; each stays open until its reading is good or, a trip, until it\'s resolved.', null);
            return $st;
        }
        if ($pre === 'alarm') {   // the Control Board's alarm: its row's buttons, in place (decisions/0171 §8)
            require_once __DIR__ . '/ControlAlarms.php';
            return ControlAlarms::pulseSteps(Database::connection(), (int) substr((string) $it['key'], 6), $s);
        }
        if ($pre === 'order') {
            $o = $it['order']; $oid = (int) $o['id']; $client = !empty($o['client']); $co = $o['client'] ? '/admin/backoffice/orders/' . $oid : '/admin/order.php?id=' . $oid; $money = '/admin/order.php?id=' . $oid;
            switch ($o['issue']) {
                case 'overdue': return [
                    $s('Check the payment', 'Is it really unpaid? Look at the money page: payments, credits, anything recorded outside QuickBooks.', ['tool', $money], $o['paid']),
                    $s('Remind the client', 'A friendly note with the amount and the link to pay.', ['reply', $hi . 'A quick reminder that order ' . $o['ref'] . ' (' . $o['total'] . ') was due ' . ($o['due'] !== '' ? date('M j', strtotime($o['due'])) : 'recently') . ". You can see it and pay here: the link in your order email.\n\nIf it's already on its way, thank you, and please ignore this."]),
                    $s('Settle it', 'Record the payment if they paid another way (cash, check, Zelle), move the due date if you agreed one, or void the order.', ['tool', $co], $o['paid']),
                    $s('Resolve the case', 'Once it\'s paid or settled. A payment resolves it on its own.', ['resolve'], $o['paid']),
                ];
                case 'proof': return [
                    $s('Read what the client asked', 'Their note is on the proof, under the item.', ['tool', $co . '#lines']),
                    $s('Make the changes and upload a new proof', 'Upload it on the same item; the old one is kept as an earlier version.', ['tool', $co . '#lines']),
                    $s('Send it to the client', 'Send update on the order emails them the new proof to approve.', ['tool', $co . '#lines']),
                    $s('Resolve when they approve', 'Their approval shows on the order; the case can close then.', ['resolve']),
                ];
                case 'dispute': return [
                    $s('Gather the evidence', 'The order history: what was ordered, the proofs they approved, delivery or tracking, emails.', ['tool', $money]),
                    $s('Answer the bank in QuickBooks', 'Payments → Disputes in QuickBooks, before the date the bank gives. Attach the evidence.', ['tool', '/admin/quickbooks.php'], false, 'quickbooks'),
                    $s('Record how it ended', 'Won or lost, on the money page. Its GO Codes stay paused until then.', ['tool', $money]),
                    $s('Resolve the case', '', ['resolve'], $o['status'] !== 'disputed'),
                ];
                case 'wobal': require_once __DIR__ . '/ClientOrders.php'; return [   // the balance before delivery (decisions/0101 note 2026-11-23, model 1.9)
                    $s('Ask the client for the balance', $o['left'] . ' is left on ' . $o['total'] . '. It\'s ready; it goes out once the balance is paid.', $it['reply_to'] ? ['reply', $hi . 'Order ' . $o['ref'] . ' is ready. ' . $o['left'] . " is left to pay before we release it for delivery: you can pay it from the link in your order email.\n\nIf it's already on its way, thank you, and please ignore this."] : null),
                    $s('Record the balance', 'Paid another way (a check, a wire, cash)? Record it here: the order is released for delivery and the client is told it\'s on its way.', ['form', 'pay', 'Record it', [['amount', 'Amount received', 'text', ltrim((string) $o['left'], '$')], ['method', 'How it came in', 'select', 'check', array_map(fn ($k, $v) => [$k, $v], array_keys(ClientOrders::PAY_METHODS), ClientOrders::PAY_METHODS)], ['ref', 'Reference (check number, wire confirmation)', 'text', '']]], (bool) $o['paid']),
                    $s('Or: release it before payment', 'Admins only, with a reason kept on the order (a client on agreed terms). It\'s on the order\'s Payment hold.', ['tool', $co . '#payhold'], (bool) $o['paid']),
                    $s('Resolve the case', 'The payment resolves it on its own.', ['resolve'], (bool) $o['paid']),
                ];
                case 'delbal': require_once __DIR__ . '/ClientOrders.php'; return [   // delivered, balance due (decisions/0178 §6): received → record it; not → collect it; or override with a reason
                    $s('The money came in: record the payment', 'A check, a wire, cash, or their card on their page: record it here and the order completes, with the client\'s thank-you.', ['form', 'pay', 'Record it', [['amount', 'Amount received', 'text', ltrim((string) $o['left'], '$')], ['method', 'How it came in', 'select', 'check', array_map(fn ($k, $v) => [$k, $v], array_keys(ClientOrders::PAY_METHODS), ClientOrders::PAY_METHODS)], ['ref', 'Reference (check number, wire confirmation)', 'text', '']]], (bool) $o['paid']),
                    $s('It didn\'t: ask the client to pay', $o['left'] . ' is left on ' . $o['total'] . '. Ask the client; they can pay from the link in their order email.', $it['reply_to'] ? ['reply', $hi . 'Thank you for letting us know you have order ' . $o['ref'] . '. ' . $o['left'] . " is still to pay: you can pay it from the link in your order email.\n\nIf it's already on its way, thank you, and please ignore this."] : ['tool', $co . '#payhold'], (bool) $o['paid']),
                    $s('Or: complete it with a reason', 'Admins only; the reason is kept on the order and the balance stays owed in the books.', ['tool', $co . '#finish'], (bool) $o['paid']),
                    $s('Resolve the case', 'Recording the payment or completing the order resolves it.', ['resolve'], (bool) $o['paid']),
                ];
                case 'sent': return [
                    $s('Check they got it', 'The order email went to ' . ($it['reply_to'][0] ?? 'the client') . '. See what they see.', ['tool', $co]),
                    $s('Follow up', 'A short nudge to look and approve.', ['reply', $hi . 'Just checking you got order ' . $o['ref'] . ' (' . $o['total'] . "). When it looks right, approve it from the link in the email and we'll get started.\n\nAny changes, reply here and we'll update it."]),
                    $s('Resolve when they approve', 'Approval moves the order on and the case with it.', ['resolve'], $o['status'] !== 'sent'),
                ];
                default:   // to make and ship, approved, paid: what the order and its work orders actually show (Jason, 2026-10-28)
                    $out = []; $work = $o['work'] ?? [];
                    if ($o['status'] === 'pending') $out[] = $s($o['paid'] ? 'Paid' : 'Record the payment (' . ($o['left'] ?? '') . ' left)',
                        $o['paid'] ? '' : (!empty($o['outside']) ? 'Paid outside the site (QuickBooks' . (($o['invoice'] ?? '') !== '' ? ' invoice ' . $o['invoice'] : '') . ', a check, a wire)? Record it on the order so it shows paid here.' : 'The client pays on their page, or record a payment made another way.'),
                        ['tool', $co . '#payhold'], (bool) $o['paid']);
                    if ($client) {
                        $vj = (array) ($o['vjobs'] ?? []);
                        if (!$work && $vj) $out[] = $s('With the printer', count($vj) . ' vendor job' . (count($vj) === 1 ? '' : 's') . ': ' . implode(', ', array_slice(array_filter($vj), 0, 3)) . '. Their status and tracking are in Vendor jobs above.', null, true);
                        elseif (!$work) $out[] = $s('Send it to the printer', 'Issue a work order from the order\'s Work orders, or, if you ordered it yourself (4OVER…), add it under Vendor jobs above: Paste from 4OVER reads the order page.', ['tool', $co . '#work']);
                        foreach ($work as $w) {
                            $vdone = in_array($w['status'], ['shipped', 'ready', 'complete', 'received'], true);
                            $out[] = $s('Work order ' . $w['no'] . ' · ' . $w['vendor'] . ': ' . $w['label'],
                                $w['status'] === 'draft' ? 'Not sent yet: send it, or copy its link to the vendor.'
                                : ($w['status'] === 'sent' ? ($w['opened'] ? 'The vendor opened it but hasn\'t accepted it yet.' : 'The vendor hasn\'t opened it yet.') . ($w['due'] !== '' ? ' Must have ' . $w['due'] . '.' : '')
                                : ($vdone ? ($w['tracking'] !== '' ? 'Tracking ' . $w['tracking'] . '.' : 'The vendor has done their part.') : 'The vendor moves it on from their page' . ($w['due'] !== '' ? '; must have ' . $w['due'] : '') . '.')),
                                // the vendor hasn't moved it (or hasn't since): we move it for them, right here
                                !empty($w['moves']) && !in_array($w['status'], ['draft'], true)
                                    ? ['form', 'wo_move:' . (int) ($w['id'] ?? 0), 'Update for the vendor', [
                                        ['to', 'Where it is now', 'select', in_array('production', $w['moves'], true) ? 'production' : $w['moves'][0], array_map(fn ($k) => [$k, WorkOrders::STATUS[$k]], $w['moves'])],
                                        ['carrier', 'Carrier (when shipped)', 'text', ''], ['tracking', 'Tracking (when shipped)', 'text', '']]]
                                    : ['tool', $co . '#work'], $vdone);
                        }
                    }
                    $held = $o['next'] && in_array($o['next'][0], ['shipped', 'ready'], true) && ($o['shiphold'] ?? '') !== '';   // greyed with its reason (model 1.9)
                    if ($o['next']) $out[] = $s($o['next'][1], $held ? (string) $o['shiphold'] : ($o['next'][0] === 'shipped' ? 'When it\'s on its way to the client (the vendor\'s tracking, or yours). ' : '') . 'Moves the order on; the client is emailed.', $held ? null : ['stage', $o['next'][0], $o['next'][1]]);
                    // the way to finish without shipping: handed over in person or picked up
                    if ($o['next'] && in_array($o['next'][0], ['shipped', 'ready'], true)) $out[] = $s('Or: the client has it already', 'Handed over in person or picked up? This completes the order; the client is emailed.', ['stage', 'complete', 'Mark complete']);
                    $out[] = $s('Resolve the case', 'Completing the order closes it on its own. Resolve by hand only to set it aside.', ['resolve'], !empty($o['closed']));
                    return $out;
            }
        }
        if ($pre === 'refundsend') {   // an approved refund's money, sent back by a person
            $pp = Database::connection(); require_once __DIR__ . '/Orders.php';
            $rq = Orders::refundRequest($pp, (int) substr((string) $it['key'], 11));
            if (!$rq || ($rq['payout'] ?? '') !== 'to_send') return [$s('Sent already', 'The order shows how it went back.', ['tool', (string) $it['url']], true)];
            return [$s('Send ' . Orders::usd((int) $rq['amount_cents']) . ' back to the client by check or wire', 'Its Refund Receipt is in QuickBooks already (' . preg_replace('/^Approved\. | is in QuickBooks.*$/', '', (string) $rq['result']) . '). Then say how it went.',
                ['form', 'refund_sent', '✓ Sent', [['how', 'How it went back (check 1234, wire reference)', 'text', '']]])];
        }
        if ($pre === 'refundok') {   // a refund waiting for Jason (decisions/0178 §1): what's asked, then Approve or Decline, in place
            $pp = Database::connection(); require_once __DIR__ . '/Orders.php';
            $rq = Orders::refundRequest($pp, (int) substr((string) $it['key'], 9));
            if (!$rq || $rq['status'] !== 'waiting') return [$s('Decided already', 'It was ' . strtolower(Orders::REFUND_STATUS[$rq['status'] ?? ''] ?? 'decided') . ': the order shows it.', ['tool', (string) $it['url']], true)];
            $ro = Orders::get($pp, (int) $rq['order_id']) ?: ['id' => (int) $rq['order_id']];
            $from = null; foreach (Orders::payments($pp, $ro) as $p) if ((int) $p['id'] === (int) $rq['ledger_id']) $from = $p;
            $wos = array_filter(explode(',', (string) $rq['stop_wos']));
            [$canD, $whyD] = Orders::canDecideRefund();
            if (($rq['kind'] ?? 'refund') === 'credit') return [   // a credit memo (decisions/0178's note, 2026-11-24)
                $s('What\'s asked', 'A credit memo of ' . Orders::usd((int) $rq['amount_cents']) . ' on the client\'s account, used toward their next order (good for ' . Orders::CREDIT_DAYS . ' days). ' . (trim((string) $rq['reason']) !== '' ? 'Its note: ' . trim((string) $rq['reason']) . '. ' : '') . 'Asked by ' . ($rq['asked_name'] ?: 'staff') . '.', ['tool', (string) $it['url']]),
                $s('Approve: give the credit', 'Gives it now and makes it in QuickBooks as a credit memo; the client is emailed.' . ($canD ? '' : ' ' . $whyD . '.'), $canD ? ['do', 'refund_approve', 'Approve: credit ' . Orders::usd((int) $rq['amount_cents'])] : null),
                $s('Or decline it', 'Nothing is credited; the order shows your reason to the one who asked.', $canD ? ['form', 'refund_decline', 'Decline', [['why', 'Why (the one who asked sees it)', 'text', '']]] : null),
            ];
            $what = Orders::usd((int) $rq['amount_cents']) . ' back to the client' . ($from ? ', from the ' . Orders::usd((int) $from['amount_cents']) . ' paid by ' . strtolower((string) $from['method']) . (strtotime((string) $from['created_at']) ? ' on ' . date('M j', strtotime((string) $from['created_at'])) : '') : '')
                . '. Why: ' . trim((string) $rq['reason']) . '.' . ($wos ? ' ' . count($wos) . ' work order' . (count($wos) === 1 ? '' : 's') . ' stop with it.' : '') . ((int) $rq['cancel_order'] ? ' The order is cancelled with it.' : '')
                . ' Order ' . Orders::no($ro) . ' has ' . Orders::usd(Orders::refundable($pp, (int) $rq['order_id'])) . ' paid and not yet refunded or credited.';
            return [
                $s('What\'s asked', $what . ' Asked by ' . ($rq['asked_name'] ?: 'staff') . '.', ['tool', (string) $it['url']]),
                $s('Approve: refund it', ($from && in_array($from['how'], ['posted', 'here'], true)
                        ? 'It wasn\'t paid by card: its Refund Receipt is made in QuickBooks now, the client is emailed, and Payments sends the money back by check or wire.'
                        : 'Sends it through QuickBooks now: a card gets it back (paid another way: its Refund Receipt, and Payments sends it back); the client is emailed.') . ($canD ? '' : ' ' . $whyD . '.'), $canD ? ['do', 'refund_approve', 'Approve: refund ' . Orders::usd((int) $rq['amount_cents'])] : null),
                $s('Or decline it', 'Nothing is refunded; the order shows your reason to the one who asked.', $canD ? ['form', 'refund_decline', 'Decline', [['why', 'Why (the one who asked sees it)', 'text', '']]] : null),
            ];
        }
        if ($pre === 'invlater') {   // one step per order still on invoice later (decisions/0101 note 2026-11-23); ours first, then those made from a QuickBooks invoice (named so)
            $pp = Database::connection(); require_once __DIR__ . '/ClientOrders.php';
            [$canR, $whyR] = ClientOrders::canRelease(); $st = [];
            $il = ClientOrders::invoiceLaterOpen($pp); usort($il, fn ($a, $b) => [$a['pulled'], (int) $a['id']] <=> [$b['pulled'], (int) $b['id']]);
            foreach ($il as $o) {
                $left = max(0, (int) $o['total_cents'] - (int) $o['paid_cents']); $approved = $o['status'] === 'pending';
                $opts = [['invoice', $approved ? 'Send invoice: due now (the client is emailed)' : 'Send invoice: Pay in full when it goes']];
                if ($approved && $canR) $opts[] = ['release', 'Release before payment (give the reason)'];
                $st[] = $s(($o['pulled'] ? 'From QuickBooks: order ' : 'Order ') . Orders::no($o) . ' · ' . ($o['client_company'] ?: $o['bill_name']) . ' · ' . Orders::usd($left) . ' to pay',
                    ($approved ? 'Approved ' . date('M j', strtotime((string) $o['approved_at'])) : ucfirst((string) $o['status'])) . '. Until you choose, nothing about it changes: it waits for payment before the vendor and delivery.'
                    . ($approved && !$canR ? ' Release before payment: ' . $whyR . ' (admins only).' : '') . ' Send cart comes with the cart route.',
                    ['form', 'inv_choose:' . (int) $o['id'], 'Do it', [['choice', 'What happens to it', 'select', 'invoice', $opts], ['why', 'Why it goes ahead before payment (for a release)', 'text', '']]]);
            }
            $st[] = $s('Resolve the case', 'It closes on its own once every order is chosen for.', ['resolve'], !$il);
            return $st;
        }
        $url = (string) $it['url'];
        switch ($pre) {
            case 'report':
                // a journey's photos reported: its photos' thread rides on this case, one row with its clock, assign and
                // escalate as before (decisions/0176 §6.3, A1; 0171 §8: "the trip's own Pulse item shows it: no second row")
                if (is_file(__DIR__ . '/UploadThreads.php')) { require_once __DIR__ . '/UploadThreads.php'; $th = UploadThreads::reportSteps(Database::connection(), (int) substr((string) $it['key'], 7), $s); if ($th) return array_merge($th, [
                    $s('Tell the person who reported it', 'They never learn who else reported; keep it short.', $it['reply_to'] ? ['reply', $hi . "Thank you for your report. We've looked at it and taken care of it.\n\nThe Traversence team"] : null),
                    $s('Resolve the case', '', ['resolve'])]); }
                return [
                $s('Read the report and see what it\'s about', 'The reason, the note and the item reported.', ['tool', $url]),
                $s('Decide: remove or hide it, or leave it', 'In Reports. Sensitive places and private details are removed, never argued.', ['tool', $url]),
                $s('Tell the person who reported it', 'They never learn who else reported; keep it short.', $it['reply_to'] ? ['reply', $hi . "Thank you for your report. We've looked at it and taken care of it.\n\nThe Traversence team"] : null),
                $s('Resolve the case', '', ['resolve']),
            ];
            case 'ses':
                require_once __DIR__ . '/SesMail.php';
                $sp = Database::connection();
                $todo = SesMail::todo($sp);
                // Everything a person isn't needed for runs by itself (SesMail::autoSetup, the ses_setup_tick job, every 15
                // minutes), so these steps have no buttons (Jason, 2026-11-01); they say where it stands. Only the AWS key, .env,
                // and production access if AWS won't take the request from the site, need a person.
                $err = SesMail::state($sp, 'auto_error'); $at = SesMail::state($sp, 'auto_at');
                $runs = $at !== '' ? ' Last run ' . date('M j, g:i a', strtotime($at)) . ($err !== '' ? ': stopped here, "' . $err . '"; it tries again in 15 minutes.' : '.') : ' It starts within 15 minutes of the .env lines.';
                $first = ''; foreach (['identities', 'dns', 'events', 'verified', 'production'] as $k) if (isset($todo[$k])) { $first = $k; break; }
                $wait = fn (string $k) => $k === $first ? $runs : '';   // where it stands, said once: at the first step not done
                $asked = SesMail::state($sp, 'prod_asked');
                return [
                    $s('Give the site\'s AWS key permission to send', 'In the existing AWS account: IAM → Users → the site\'s user (the one whose key is AWS_ACCESS_KEY_ID) → Add permissions → inline policy "traversence-ses" (decisions/0137 §8). No new key.', null, !isset($todo['keys']), 'aws_iam'),
                    $s('Switch it on in .env', 'SES_MODE=mock (live once production access is granted), SES_REGION=us-east-2 (US East, Ohio, where SES was set up), SES_MONTHLY_CAP_USD=10. In File Manager.', null, !isset($todo['mode']), 'bluehost'),
                    $s('Make the sending domains', 'Runs by itself: send.traversence.com and via.traversence.com in SES.' . $wait('identities'), null, !isset($todo['identities'])),
                    $s('Add their DNS records', 'Runs by itself: DKIM, the bounce address and DMARC, added to traversence.com through the cPanel API, and done only when public DNS shows every one (checked each run).' . $wait('dns')
                        . (($dm = json_decode(SesMail::state($sp, 'dns_missing'), true)) && isset($todo['dns']) ? ' Not published yet (' . count($dm) . '): ' . implode('; ', $dm) . '.' : ''), isset($todo['dns']) ? ['do', 'ses_run', 'Run now'] : null, !isset($todo['dns']), 'bluehost'),
                    $s('Connect bounce and complaint reports', 'Runs by itself: an SNS topic sending them to /api/ses-events.php, which stops mail to those addresses; done once AWS has confirmed the subscription with the site.' . $wait('events')
                        . (isset($todo['events']) && SesMail::state($sp, 'topic_personal') ? ' Topic made; waiting for AWS\'s confirmation (Run now asks again).' : ''), isset($todo['events']) ? ['do', 'ses_run', 'Run now'] : null, !isset($todo['events'])),
                    $s('AWS verifies the domains', 'Checked by itself every 15 minutes; AWS usually verifies within the hour of the DNS records.' . $wait('verified'), isset($todo['verified']) ? ['do', 'ses_run', 'Check now'] : null, !isset($todo['verified'])),
                    $s('Production access', 'Asked for by itself once a domain is verified (Transactional, https://traversence.com, the use case in decisions/0137 §8); AWS answers within a day and the check picks it up.'
                        . ($asked !== '' ? ' Asked ' . date('M j, g:i a', strtotime($asked)) . '.' : $wait('production'))
                        . ' If it stops here with "AccessDenied", add ses:PutAccountDetails to the traversence-ses policy, or ask in the SES console (Get set up → Request production access).', null, !isset($todo['production']), 'ses'),
                    $s('Go live', 'Once production access is on: SES_MODE=live in .env. Then a test goes to support@traversence.com by itself.', null, SesMail::mode() === 'live', 'bluehost'),
                    $s('Resolve the case', 'It closes on its own once everything above is done.', ['resolve']),
                ];
            case 'grouphelp': return [
                $s('Read what they need', str_contains($it['key'], 'stale') ? 'Reports on this group\'s posts have waited 14 days: its moderators haven\'t decided. The group page shows the group; its reports are its own.' : 'What the group\'s owner or moderator wrote, and the group.', ['tool', $url]),
                $s('Help them', 'Advise the owner and moderators; act only under our Terms (Safety) or with their charter. A member harassing across groups goes to Standing.', $it['reply_to'] ? ['reply', $hi . "Thanks for asking. \n\nThe Traversence team"] : null),
                $s('Resolve the case', '', ['resolve']),
            ];
            case 'claim': return [
                $s('Open the claim', 'Who is claiming, the listing, the text code and the evidence.', ['tool', $url]),
                $s('Confirm it\'s really them', 'Match the text code they got, or call the listing\'s public phone and ask for them. Within 48 hours.', ['tool', $url]),
                $s('Approve or decline', 'In Claims. Approving gives them the listing.', ['tool', $url]),
                $s('Tell them the outcome', '', $it['reply_to'] ? ['reply', $hi . "Your claim is confirmed: you can manage the listing now from your account.\n\nThe Traversence team"] : null),
                $s('Resolve the case', '', ['resolve']),
            ];
            case 'sugg':
                // a missing business: done right here (Jason, 2026-11-01: steps that do the action and walk you to the end)
                if (($it['sugg']['kind'] ?? '') === 'missing' && in_array($it['sugg']['topic'] ?? '', ['business', 'food', 'other', 'culture'], true)) {
                    $form = self::missingForm($it['sugg']['row']);
                    $src = array_values(array_filter(preg_split('/\s+/', (string) (json_decode((string) ($it['sugg']['row']['fields'] ?? ''), true)['source'] ?? '')) ?: [], fn ($u) => str_starts_with($u, 'http')));
                    return array_values(array_filter([
                        $s('Check it against its sources', $src ? 'What was found is in the form, ready to correct. Its sources: ' . implode(' · ', array_map(fn ($u) => parse_url($u, PHP_URL_HOST) ?: $u, $src)) . '.' : 'What was found is in the form, ready to correct.', $src ? ['tool', $src[0]] : null),
                        $s('Create the listing', 'Correct anything, pick its kind, and create it. It goes live checked by our team' . (!empty($it['sugg']['guide']) ? ', the guide\'s name links to it,' : '') . ' and this case closes.', ['form', 'sugg_create', 'Create the listing', $form]),
                        $s('Or: not a real place, or not open', 'Sets it aside in Review, kept, and closes this case.', ['do', 'sugg_dismiss', 'Set it aside']),
                        $it['reply_to'] ? $s('Thank them', 'Write a line back; it\'s emailed to them.', ['reply', $hi . "Thanks for telling us. It's on Traversence now.\n\nThe Traversence team"]) : null,
                    ]));
                }
                return [
                $s('Read what they sent', 'A missing place, a fix, or feedback.', ['tool', $url]),
                $s('Act on it', 'Add the place, make the fix, or set it aside in Review.', ['tool', $url]),
                $s('Thank them', '', $it['reply_to'] ? ['reply', $hi . "Thanks for letting us know. We've taken care of it.\n\nThe Traversence team"] : null),
                $s('Resolve the case', '', ['resolve']),
            ];
            case 'standing': return [
                $s('Read their request', 'Why they think the notice is wrong. Their clock is stopped meanwhile.', ['tool', $url]),
                $s('Decide: keep, change or lift the notice', 'In Safety → Standing. They\'re told either way.', ['tool', $url]),
                $s('Resolve the case', '', ['resolve']),
            ];
            case 'staffmail':   // a lost work email (StaffMailHelp, Release 3f B8)
                require_once __DIR__ . '/StaffMailHelp.php';
                return StaffMailHelp::steps($it, $s);
            case 'locked': return [
                $s('Check it was them', 'Ask the staff member if they mistyped. If not, someone may be guessing.', ['tool', '/admin/logs.php?log=signins']),
                $s('Reset their login if needed', 'Staff logins → Reset sends a new setup link; their sessions end.', ['tool', $url]),
                $s('Resolve the case', 'It unlocks on its own after 15 minutes.', ['resolve']),
            ];
            case 'spike': return [
                $s('Look at the sign-in log', 'Which staff IDs, and when.', ['tool', '/admin/logs.php?log=signins']),
                $s('Lock out what\'s at risk', 'Reset the logins being tried; logins lock themselves after 5 wrong tries.', ['tool', '/admin/backoffice/people']),
                $s('Resolve the case', '', ['resolve']),
            ];
            case 'stopped': return [
                $s('Check the crawler', 'When it last ran, and what each run did.', ['tool', '/admin/insights/crawler']),
                $s('Restart the worker on Railway', 'The worker runs on Railway, not this site. In the Traversence project, crawler service: billing first (an ended trial or credit is the usual cause), then Deployments → ⋯ → Redeploy if it says Failed or Crashed. Settings → Cron Schedule should be */5 * * * *.', null, false, 'railway'),
                $s('Resolve when it\'s working again', 'Resolves on its own once it does work.', ['resolve']),
            ];
            case 'stalled': return [
                $s('See what the runs say', 'Each run\'s report, task by task: what it did, what it didn\'t reach, and why it failed.', ['tool', '/admin/insights/crawler']),
                $s('Check the worker\'s log on Railway', 'In the Traversence project, crawler service → Deployments → the latest → View logs. A run that can\'t reach the site, or has no token, says so on its first lines.', null, false, 'railway'),
                $s('Resolve when runs work again', 'Resolves on its own once a run does its work.', ['resolve']),
            ];
            case 'svcback': return [
                $s('See its card', 'Its servers, their state and the day\'s budget.', ['tool', '/admin/config/services']),
                $s('Tell whoever we asked', 'If we wrote to the service about the block, a thank-you closes the loop.', null),
                $s('Done', 'Nothing more to do: it runs by itself.', ['resolve']),
            ];
            case 'svc':   // an outside service's issue: its evidence, its contacts and console, Check it now, Mark resolved
                require_once __DIR__ . '/ServiceHealth.php';
                return isset($it['svc']) ? ServiceHealth::steps($it['svc']) : [$s('Mark resolved', 'Calls to it start again.', ['do', 'svc_resolve', 'Mark resolved'])];
            case 'guidefail': return [
                $s('Read why', 'The last reason is in the summary. A busy service (OpenStreetMap, Claude) usually works later; "too little to write" means the place needs our sources first.', null),
                $s('Start them again', 'Each gets three more tries, from the step it stopped at. Visitors who asked to hear are told when it\'s ready.', ['do', 'guide_retry', 'Retry them all']),
                $s('Or look at the guides', 'Explore → Guides: write or hold one by hand.', ['tool', '/admin/explore/place_guides']),
            ];
            case 'crawl': return [
                $s('Put failed jobs back in the queue', 'Usually OpenStreetMap was busy. This retries every failed job and closes their cases; the crawler picks them up on its next runs.', ['do', 'crawl_retry', 'Retry them all']),
                $s('If they fail again: check the crawler', 'Whether the worker is running, and why the jobs failed. The worker itself runs on Railway.', ['tool', '/admin/crawler.php#fix-worker'], false, 'railway'),
            ];
            case 'calllist': return [
                $s('Open the Call list', 'Calls today, set to Confirm listings. Tap a name: the call panel has the number and the buttons.', ['tool', '/admin/growth/calls?what=confirm']),
                $s('Call and log what happened', 'Confirmed, wrong number, closed or moved changes the listing; interested or not now carries on in Sales.', null),
                $s('Resolve for today', 'Tomorrow brings a fresh list.', ['resolve']),
            ];
            case 'srcfail': return [
                $s('Look at why', 'The reason is in the summary. A site that was down usually works a day later.', null),
                $s('Crawl it again', 'Listing Intake: paste the address and crawl. A run that works clears this on its own.', ['tool', '/admin/listing-intake.php']),
                $s('Or remove it', 'If it\'s not a site worth crawling. What it found stays.', ['do', 'source_remove', 'Remove it']),
            ];
            case 'terms':   // a step for each source due: its terms one tap away, then I read them
                $st = [];
                foreach ((array) ($it['terms'] ?? []) as $t)
                    $st[] = ['t' => ($t['again'] ? 'Again: ' : '') . $t['name'], 'h' => $t['rule'] . (!empty($t['restricted']) ? ' Its own terms, not an open licence, and we show it: tick only if they allow showing it with a link back.' : ' Read its terms: do they still allow this?'), 'act' => ['do', 'terms_read:' . $t['host'], 'I read them'], 'auto' => false]
                          + ($t['url'] !== '' ? ['ext' => [$t['name'] . ' terms', $t['url']]] : []);
                $st[] = $s('One doesn\'t allow it: change our rule', 'In Manage sources, pick what it does allow (a pointer only, cite only, evidence only). Everything that uses it follows.', ['tool', '/admin/sources.php']);
                return $st;
            case 'likes':   // like checks: a step each, decided right here (LikeGuard::decide)
                $st = [];
                foreach ((array) ($it['likes'] ?? []) as $f)
                    $st[] = ['t' => $f['why'] . ': ' . $f['label'], 'h' => $f['detail'] . ($f['auto_removed'] ? ' ' . (int) $f['auto_removed'] . ' already taken off.' : '') . ' Who liked it is never shown: only counts and a shortened, scrambled connection.',
                        'act' => ['form', 'likes_decide:' . (int) $f['id'], 'Decide', [['what', 'What to do', 'select', 'clear', [['clear', 'Looks fine: keep the likes'], ['remove', 'Not genuine: remove these likes']]]]], 'auto' => false]
                        + ($f['url'] !== '' ? ['ext' => ['The page', $f['url']]] : []);
                return $st;
            case 'mkt':   // Marketplace cases: held posts (mkt:held), or trades with a problem (mkt:disputes)
                if ($it['key'] === 'mkt:disputes') {   // a step for each trade with a problem: settle it right here
                    $st = [];
                    require_once __DIR__ . '/MarketOffers.php';
                    $opts = array_map(fn ($k, $v) => [$k, $v], array_keys(MarketOffers::OUTCOMES), MarketOffers::OUTCOMES);
                    foreach ((array) ($it['mkt'] ?? []) as $d)
                        $st[] = ['t' => $d['title'] . ($d['amount_cents'] !== null ? ' (' . MarketOffers::money((int) $d['amount_cents']) . ')' : ''), 'h' => 'The ' . $d['problem_by'] . ' says: ' . $d['problem_note'] . ' The whole history is in Offers & disputes.',
                            'act' => ['form', 'mkt_settle:' . (int) $d['id'], 'Settle it', [['outcome', 'How it ended', 'select', 'fell_through', $opts], ['note', 'What you found (both are emailed this)', 'textarea', '']]], 'auto' => false];
                    $st[] = $s('Read the history first', 'Market 360° → Offers & disputes: every offer, counter and confirmation, with who did it.', ['tool', '/admin/market/exchange']);
                    return $st;
                }
                // a step for each held Marketplace post: let it show from here; taking one down needs a line, in Items
                $st = [];
                foreach ((array) ($it['mkt'] ?? []) as $m)
                    $st[] = ['t' => $m['title'] . ' (' . $m['place_label'] . ')', 'h' => $m['hold_note'] . ' Open it to read the whole post; Take down is in its drawer in Items.', 'act' => ['do', 'mkt_ok:' . (int) $m['id'], 'Let it show'], 'auto' => false];
                $st[] = $s('Take one down', 'In Market 360° → Items, open the post and choose Take down, with a line the seller will see.', ['tool', '/admin/market/items?status=held']);
                return $st;
            case 'sitehealth':   // the site audit's findings: a step each, the most paths blocked first (SiteHealth::steps)
                require_once __DIR__ . '/SiteHealth.php';
                return SiteHealth::steps($it);
            case 'schema':   // the database's leftovers and unused names (SchemaUpkeep::pulseSteps, decisions/0162)
                require_once __DIR__ . '/SchemaUpkeep.php';
                return SchemaUpkeep::pulseSteps(Database::connection(), (string) $it['key'], $s);
            case 'ops':   // the service register's warnings, "returns empty" and watching (Operations::pulseSteps)
                require_once __DIR__ . '/Operations.php';
                return Operations::pulseSteps(Database::connection(), (string) $it['key'], $s);
            case 'hubs':   // opening a hub and W14's sources (HubOpening::pulseSteps, decisions/0164 R17)
                require_once __DIR__ . '/HubOpening.php';
                return HubOpening::pulseSteps(Database::connection(), $s);
            case 'engine':   // "The engine needs a person" (EngineCase::pulseSteps, decisions/0164 §6.1)
                require_once __DIR__ . '/EngineCase.php';
                return EngineCase::pulseSteps(Database::connection(), $s);
            case 'cleanup':   // Data cleanup's Check these and merge offers (DataCleanup::pulseSteps)
                require_once __DIR__ . '/DataCleanup.php';
                return DataCleanup::pulseSteps(Database::connection(), $s);
            case 'release':   // a release's next move (Releases::pulseSteps)
                require_once __DIR__ . '/Releases.php';
                return Releases::pulseSteps(Database::connection(), (int) substr((string) $it['key'], 8), $s);
            case 'dailyrep': return [
                $s('Send it now', 'Builds this morning\'s report and emails it to you. It counts as today\'s.', ['do', 'report_send', 'Send it now']),
                $s('Check why it didn\'t go', 'System Configuration → Scheduled tasks: Daily report should run every hour. A mail error shows in the summary.', ['tool', '/admin/config/scheduler']),
            ];
            case 'files': return [
                $s('See what\'s taking the room', 'Settings shows the count; old drafts, snapshots and orphaned uploads are the usual cause.', ['tool', '/admin/config/services']),
                $s('Clear them, or move uploads to S3', 'System Configuration → Storage, once S3 is set up.', ['tool', '/admin/config/storage'], false, 'aws'),
                $s('Resolve the case', '', ['resolve']),
            ];
            case 'prospect': return [
                $s('Call them', 'Calls today has their number and what happened last time.', ['tool', '/admin/sales.php#calls']),
                $s('Log what happened and set the next follow-up', 'In Sales; the next date is suggested for you.', ['tool', '/admin/sales.php#calls']),
                $s('Resolve the case', '', ['resolve']),
            ];
            case 'inform': return [
                $s('See who\'s overdue', 'Seller compliance lists each one, its sales and gross, and what it has given. The law gives them 10 days; their card checkout is paused until they confirm (their listing stays up).', ['tool', $url]),
                $s('Reach them if it drags on', 'They were emailed when asked and when paused, and their Trust page says what\'s needed. A call helps: it\'s usually a missing tax ID or payouts not switched on in Stripe or Square.', null),
                $s('Resolve the case', 'It closes on its own once they\'ve confirmed.', ['resolve']),
            ];
            case 'moments': return [
                $s('Check the figure against Cloudflare', 'The site estimates its own spending from the uploads it records. R2\'s bucket Metrics shows what\'s really stored and read this month.', null, false, 'cloudflare'),
                $s('Raise the budget if revenue allows', 'Settings: Community budget share (percent of last month\'s revenue) and Community budget floor (dollars). Raising either lets video Moments carry on at once.', ['tool', '/admin/config/settings']),
                $s('Resolve the case', 'A new month starts a new count.', ['resolve']),
            ];
            case 'domorder': case 'domrenew': case 'domtk': case 'domverify':   // domains bought in the Shop (AccountDomains::pulseSteps, decisions/0169 §1a)
                require_once __DIR__ . '/AccountDomains.php';
                return AccountDomains::pulseSteps(Database::connection(), (string) $it['key'], $s);
            case 'outreq':   // a request waiting on an outside company (OutsideServices::requests)
                require_once __DIR__ . '/OutsideServices.php';
                $rq = OutsideServices::requests(Database::connection(), true)[(int) substr((string) $it['key'], 7)] ?? [];
                $who = OutsideServices::COMPANIES[$rq['svc'] ?? '']['0'] ?? 'them';
                return [
                    ['t' => 'Open their support' . (!empty($rq['ticket']) ? ', ticket #' . $rq['ticket'] : ''), 'h' => 'What we asked: ' . ($rq['what'] ?? ''), 'act' => null, 'auto' => false, 'ext' => [$who, (string) (($rq['link'] ?? '') ?: '/admin/config/services#svc-' . ($rq['svc'] ?? '')), 'Where the request was sent']],
                    ['t' => 'Chase them, after 5 days', 'h' => 'Most support desks (Name.com\'s among them) ask you not to reply before they answer: a reply moves the ticket to the back of their queue. If 5 days pass with no answer (the case turns to an Alert), write once, then note it here.', 'act' => ['form', 'out_chase', 'Note the chase', [['text', 'What you sent (optional)', 'textarea', '']]], 'auto' => false],
                    ['t' => 'Record their answer', 'h' => 'Their answer is kept on the case, and it closes.', 'act' => ['form', 'out_answer', 'Record it', [['text', 'What they answered', 'textarea', '']]], 'auto' => false],
                    $s('Resolve the case', 'Recording their answer closes it.', ['resolve']),
                ];
            case 'namecom': return [
                $s('Check the username and token', 'In .env (Bluehost File Manager): the sandbox needs its own pair, NAMECOM_TEST_USER (your username with -test, e.g. Traversence-test) and NAMECOM_TEST_TOKEN (made on Name.com\'s API page, sandbox section); live uses NAMECOM_USER and NAMECOM_TOKEN. If the account has two-step verification, switch on Name.com API Access in its Security settings. Never paste a token in a message.', null, false, 'namecom'),
                $s('Check again', 'Asks Name.com now. The 15-minute domains job also asks by itself, so buying reopens on its own once it\'s fixed.', ['do', 'nc_check', 'Check again']),
                $s('Resolve the case', 'It closes on its own once Name.com accepts our sign-in.', ['resolve']),
            ];
            case 'mkgw': $g = $it['gw'] ?? ['name' => 'the business', 'pn' => 'the gateway', 'provider' => '', 'entity' => 0]; return [
                $s('See what stopped it', 'The reason is in the summary: disconnected from ' . $g['pn'] . '\'s side, or a sign-in that couldn\'t be renewed. Gateways lists every business\'s connection.', ['tool', $url]),
                $s('Reconnect', 'Only the business can: someone on its team who handles billing opens E-commerce setup → Card payments → Connect ' . $g['pn'] . ' and approves Traversence again. Their Pulse already asks them. If they say it\'s connected, check our ' . $g['pn'] . ' platform for their account.', $it['reply_to'] ? ['reply', ''] : null, false, $g['provider']),
                $s('Resolve the case', 'It goes on its own once they\'ve reconnected.', ['resolve']),
            ];
            case 'csp': $d = $it['csp'] ?? ['top' => []]; return [
                $s('See what was blocked', $d['top'] ? implode('; ', array_map([CspReports::class, 'line'], $d['top'])) . '.' : 'Pages, rules and the hosts blocked, counted (never who).', null),
                $s('Was it us?', 'A script or service we added to a card page and didn\'t put on its list (decisions/0098 for our checkout, 0135 for the Marketplace)? Then the list changes through review, and app/ui/csp.php with it. Compare the card pages with the live mirror for anything that changed.', null, false, 'github'),
                $s('If not: treat it as an attack on the card pages', 'A host no one added means something tried to put a script on a page that takes a card. Check the pages\' files on the server against the mirror, change the passwords and keys that can write them, and tell Intuit if card data could have been seen.', ['tool', '/admin/logs.php'], false, 'bluehost'),
                $s('Resolve the case', '', ['resolve']),
            ];
            case 'qbinbox': return [
                $s('Place the QuickBooks refund on its order', 'It could be more than one order: say which, so the right GO Codes stop.', ['tool', $url], false, 'quickbooks'),
                $s('Resolve the case', '', ['resolve']),
            ];
            case 'post': return [
                $s('Read it', '', null),
                $s('Reply', 'Your reply is kept on the post for the team.', ['reply', '']),
                $s('Resolve', '', ['resolve']),
            ];
        }
        return [$s('Open it', '', $url !== '' ? ['tool', $url] : null), $s('Resolve the case', '', ['resolve'])];
    }

    // ---- Stream settings: routing and clocks (2026-12-19_pulse_settings.sql); only changes from the defaults are stored ----

    private static ?array $cfg = null;
    public static function settingsReady(PDO $pdo): bool { try { $pdo->query('SELECT 1 FROM ' . Database::t('pulse_settings') . ' LIMIT 0'); return true; } catch (Throwable) { return false; } }
    private static function cfg(PDO $pdo): array
    {
        if (self::$cfg === null) { self::$cfg = []; try { foreach ($pdo->query('SELECT k, v FROM ' . Database::t('pulse_settings') . '') as $r) self::$cfg[$r['k']] = (string) $r['v']; } catch (Throwable) {} }
        return self::$cfg;
    }

    /** A stored Stream setting, or null when it's at its default. */
    public static function setting(PDO $pdo, string $k): ?string { $c = self::cfg($pdo); return $c[$k] ?? null; }

    /** A severity's clock in hours (0 = no clock). */
    public static function slaHours(PDO $pdo, string $sev): int { $c = self::cfg($pdo); return isset($c['sla_' . $sev]) ? (int) $c['sla_' . $sev] : (self::SLA_HOURS[$sev] ?? 0); }

    /** The department a kind of item goes to ('' = everyone's stream). */
    public static function deptFor(PDO $pdo, string $src): string { $c = self::cfg($pdo); return array_key_exists('route_' . $src, $c) ? $c['route_' . $src] : (string) (self::SOURCES[$src][3] ?? ''); }

    /** "2 hours", "2 days", "No clock". */
    public static function clockLabel(int $h): string { return !$h ? 'No clock' : ($h % 24 === 0 && $h >= 48 ? ($h / 24) . ' days' : $h . ' hour' . ($h === 1 ? '' : 's')); }

    /** Who may change Stream settings: the Founder / Owner, an Executive or a Director. */
    public static function canSet(?array $staff): bool
    {
        require_once __DIR__ . '/Workspaces.php';
        return !$staff || in_array(Workspaces::levelOf($staff)[0], ['founder', 'executive', 'director'], true);
    }

    /** Saves the routing and clocks; a value back at its default is removed. Returns what changed, in words. */
    public static function saveSettings(PDO $pdo, ?array $staff, int $uid, array $in): string
    {
        require_once __DIR__ . '/Workspaces.php';
        if (!self::settingsReady($pdo)) throw new RuntimeException('Run the Stream settings update (2026-12-19_pulse_settings.sql) first.');
        if (!self::canSet($staff)) throw new RuntimeException('Only a Director, an Executive or the Founder changes Stream settings.');
        $set = []; $said = [];
        foreach (self::SOURCES as $src => [$label]) {
            if (!array_key_exists($src, (array) ($in['route'] ?? []))) continue;
            $d = (string) $in['route'][$src];
            if ($d !== '' && !isset(Workspaces::DEPARTMENTS[$d])) throw new RuntimeException('That department isn\'t one of ours.');
            if ($d !== self::deptFor($pdo, $src)) $said[] = $label . ' → ' . ($d === '' ? 'everyone' : Workspaces::DEPARTMENTS[$d][0]);
            $set['route_' . $src] = $d === (string) self::SOURCES[$src][3] ? null : $d;
        }
        foreach (self::SLA_HOURS as $sev => $def) {
            if (!array_key_exists($sev, (array) ($in['sla'] ?? []))) continue;
            $v = trim((string) $in['sla'][$sev]);
            if (!preg_match('/^\d{1,3}$/', $v) || (int) $v > 720) throw new RuntimeException(self::SEVERITY[$sev] . ': enter whole hours, 0 to 720 (0 = no clock).');
            if ((int) $v !== self::slaHours($pdo, $sev)) $said[] = self::SEVERITY[$sev] . ' ' . self::clockLabel((int) $v);
            $set['sla_' . $sev] = (int) $v === $def ? null : (string) (int) $v;
        }
        // the daily report: who it goes to (one Owner) and the hour, Arizona time
        if (array_key_exists('report_to', $in) || array_key_exists('report_hour', $in)) {
            require_once __DIR__ . '/DailyReport.php';
            $own = DailyReport::owners($pdo); $first = (int) array_key_first($own);
            if (array_key_exists('report_to', $in)) {
                $to = (int) $in['report_to'];
                if (!isset($own[$to])) throw new RuntimeException('The daily report goes to an Owner: pick one from the list.');
                if ($to !== (int) (DailyReport::recipient($pdo)[0] ?? 0)) $said[] = 'Daily report to ' . $own[$to][0];
                $set['report_to'] = $to === $first ? null : (string) $to;
            }
            if (array_key_exists('report_hour', $in)) {
                $hr = (string) $in['report_hour'];
                if (!preg_match('/^\d{1,2}$/', $hr) || (int) $hr > 23) throw new RuntimeException('Pick the hour the daily report goes.');
                if ((int) $hr !== DailyReport::hour($pdo)) $said[] = 'Daily report at ' . DailyReport::hourLabel((int) $hr) . ' Arizona time';
                $set['report_hour'] = (int) $hr === DailyReport::HOUR ? null : (string) (int) $hr;
            }
        }
        foreach ($set as $k => $v) {
            if ($v === null) $pdo->prepare('DELETE FROM ' . Database::t('pulse_settings') . ' WHERE k = ?')->execute([$k]);
            else $pdo->prepare('INSERT INTO ' . Database::t('pulse_settings') . ' (k, v, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v), updated_by = VALUES(updated_by)')->execute([$k, $v, $uid]);
        }
        self::$cfg = null;
        if ($said) try { $pdo->prepare("INSERT INTO " . Database::t('audit_log') . " (actor_user, actor_staff, listing_id, route, action, detail) VALUES (?, ?, NULL, 'pulse', 'stream_settings', ?)")->execute([$uid, $staff['id'] ?? null, mb_substr(implode('; ', $said), 0, 500)]); } catch (Throwable) {}
        return $said ? 'Saved: ' . implode('; ', $said) . '.' : 'Nothing changed.';
    }

    /** What each label at the top of a case means here, and where it leads. */
    public static function explain(PDO $pdo, array $it): array
    {
        require_once __DIR__ . '/Workspaces.php';
        $dep = (string) $it['dept'];
        $h = self::slaHours($pdo, $it['severity']);
        $who = function (string $lv) use ($pdo, $dep): string {
            try { $st = $pdo->prepare("SELECT name, staff_id FROM " . Database::t('staff_accounts') . " WHERE level = ? AND department = ? AND status = 'active' ORDER BY id LIMIT 1"); $st->execute([$lv, $dep]);
                $r = $st->fetch(PDO::FETCH_ASSOC); return $r ? ($r['name'] ?: $r['staff_id']) : 'not set yet'; } catch (Throwable) { return 'not set yet'; }
        };
        $ws = ['support' => 'local', 'sales' => 'growth', 'fulfillment' => 'backoffice', 'payments' => 'backoffice', 'safety' => 'social', 'security' => 'backoffice', 'system' => 'insights', 'team' => 'pulse'][$it['source']] ?? 'pulse';
        $why = ['overdue' => 'the invoice is past its due date', 'dispute' => 'a bank dispute has a deadline', 'report' => 'a report about safety, a sacred place or private details',
            'stopped' => 'the crawler has stopped', 'stalled' => 'the crawler\'s runs aren\'t working', 'files' => 'files are near the server ceiling', 'spike' => 'many failed staff sign-ins', 'csp' => 'scripts were blocked on pages that take a card'];
        $pre = (string) strstr($it['key'], ':', true);
        $issue = $pre === 'order' ? (string) ($it['order']['issue'] ?? '') : $pre;
        return [
            'source' => self::SOURCES[$it['source']][0] . ': ' . ($dep !== '' ? 'the ' . (Workspaces::DEPARTMENTS[$dep][0] ?? '') . ' department\'s queue.' : 'the team\'s stream.'),
            'source_url' => '/admin/' . $ws,
            'severity' => self::SEVERITY[$it['severity']] . ($it['severity'] === 'critical' && isset($why[$issue]) ? ' because ' . $why[$issue] . '.' : '.') . ' '
                . ($h ? ($it['severity'] === 'update' ? 'Within ' : 'Act within ') . self::clockLabel($h) . '.' : 'For your information: no clock.'),
            'sla' => $it['sla_due'] ? 'Due ' . date('M j, g:i a', strtotime($it['sla_due'])) . ' (' . $h . ' h from ' . date('M j, g:i a', strtotime($it['when'])) . '). Over SLA, it shows red; escalate if it\'s stuck.' : 'No clock: nothing is waiting on us.',
            'status' => 'Open until resolved. Something new on it reopens it.',
            'assigned' => 'Whoever has it works it. Take it, or assign it to someone by name.',
            'escalation' => 'Escalate goes to ' . ($dep !== '' ? 'the ' . (Workspaces::DEPARTMENTS[$dep][0] ?? '') . ' Manager (' . $who('manager') . '), then its Director (' . $who('director') . ') a further SLA later.' : 'the Manager, then the Director.'),
        ];
    }

    /** Ticks or unticks a step by hand (kept on the case as pulse_notes 'stepN' / 'unstepN'). */
    public static function step(PDO $pdo, string $key, int $n, bool $on, string $label, int $uid): void
    {
        if ($n < 0 || $n > 20) throw new RuntimeException('Unknown step.');
        self::markRow($pdo, $key);
        self::note($pdo, $key, $uid, ($on ? 'step' : 'unstep') . $n, ($on ? 'Done: ' : 'Not done: ') . mb_substr($label, 0, 200));
    }

    /** Adds a vendor job for an order (VendorOrders, 4OVER's layout) and notes it on the case. Never shown to the client. */
    public static function vendorJob(PDO $pdo, int $oid, array $job, int $uid, string $key = ''): void
    {
        require_once __DIR__ . '/VendorOrders.php';
        if (!VendorOrders::ready($pdo)) throw new RuntimeException('Run the vendor orders update (2026-12-17_vendor_orders.sql) first.');
        $jid = VendorOrders::addJob($pdo, $oid, $job, $uid);
        if ($key !== '') self::note($pdo, $key, $uid, 'vendor', 'Sent to ' . VendorOrders::label($pdo, $jid) . '. (Our team only.)');
    }

    /** An order's vendor jobs, the vendors and vendor orders to pick from, and the margin (staff only). */
    public static function vendorJobs(PDO $pdo, int $oid): ?array
    {
        require_once __DIR__ . '/VendorOrders.php';
        require_once __DIR__ . '/Orders.php';
        if (!VendorOrders::ready($pdo) || !($o = Orders::get($pdo, $oid))) return null;
        return ['jobs' => VendorOrders::forOrder($pdo, $oid), 'vendors' => VendorOrders::vendors($pdo),
            'orders' => array_map(fn ($r) => ['id' => (int) $r['id'], 'label' => $r['vendor'] . ' #' . $r['vendor_order_no']], array_filter(VendorOrders::recent($pdo), fn ($r) => $r['vendor_order_no'] !== '' && !str_starts_with($r['vendor_order_no'], 'old-'))),
            'margin' => VendorOrders::margin($pdo, $o), 'items' => VendorOrders::items($pdo, $oid)];
    }

    /** A case for the drawer: its mark, history and who it can be assigned to. */
    public static function caseOf(PDO $pdo, string $key): array
    {
        $m = self::markRow($pdo, $key);
        $hist = [];
        if (self::casesReady($pdo)) {
            $st = $pdo->prepare('SELECT n.id, n.user_id, n.kind, n.body, n.to_email, n.created_at, u.display_name, u.email FROM ' . Database::t('pulse_notes') . ' n LEFT JOIN users u ON u.id = n.user_id WHERE n.item_key = ? ORDER BY n.created_at DESC, n.id DESC LIMIT 50');
            $st->execute([$key]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $n) $hist[] = ['id' => (int) $n['id'], 'uid' => (int) $n['user_id'], 'kind' => $n['kind'], 'body' => $n['body'], 'to' => $n['to_email'], 'at' => $n['created_at'],
                'by' => trim((string) $n['display_name']) ?: (string) strstr((string) $n['email'], '@', true)];
        }
        $team = [];
        foreach ($pdo->query("SELECT user_id, name, staff_id FROM " . Database::t('staff_accounts') . " WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) as $s)
            $team[] = ['id' => (int) $s['user_id'], 'name' => ($s['name'] ?: $s['staff_id'])];
        $ticked = [];   // the latest tick or untick of each step wins
        foreach (array_reverse($hist) as $n) if (preg_match('/^(un)?step(\d+)$/', $n['kind'], $mm)) $ticked[(int) $mm[2]] = $mm[1] === '';
        $vend = preg_match('/^order:(\d+)$/', $key, $om) ? self::vendorJobs($pdo, (int) $om[1]) : null;
        if ($vend !== null || !empty($om)) {   // the order's own history (sent, approved, work orders, payments, opens) with the case's notes
            try {
                $st = $pdo->prepare('SELECT e.event, e.note, e.created_at, u.display_name FROM order_events e LEFT JOIN users u ON u.id = e.actor_user_id WHERE e.order_id = ? ORDER BY e.id DESC LIMIT 40');
                $st->execute([(int) $om[1]]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $ev) if (trim((string) $ev['note']) !== '' && !str_starts_with((string) $ev['event'], 'step'))
                    $hist[] = ['id' => 0, 'uid' => 0, 'kind' => 'order', 'body' => (string) $ev['note'], 'to' => '', 'at' => (string) $ev['created_at'], 'by' => trim((string) $ev['display_name']) ?: 'Order'];
                usort($hist, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
                $hist = array_slice($hist, 0, 60);
            } catch (Throwable) {}
        }
        return ['case_no' => (int) ($m['case_no'] ?? 0), 'history' => $hist, 'team' => $team, 'ticked' => array_keys(array_filter($ticked))] + ($vend ? ['vendor' => $vend] : []);
    }

    /** Files on the server (uploads and the site), counted at most once an hour. */
    public static function fileCount(): int
    {
        $cache = sys_get_temp_dir() . '/tv_filecount.json';
        $c = is_file($cache) ? json_decode((string) @file_get_contents($cache), true) : null;
        if (is_array($c) && ($c['at'] ?? 0) > time() - 3600) return (int) $c['n'];
        $n = 0;
        foreach ([dirname(__DIR__, 2) . '/website', dirname(__DIR__, 2) . '/app'] as $dir) {
            if (!is_dir($dir)) continue;
            try { foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) $n++; } catch (Throwable) {}
        }
        @file_put_contents($cache, json_encode(['at' => time(), 'n' => $n]));
        return $n;
    }
}
