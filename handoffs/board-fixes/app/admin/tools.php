<?php
// app/admin/tools.php — the admin's tools (decisions/0109 A3, A5): each tool's data and how it's shown, read by the
// widgets (Table, Pipeline, MetricCards, DetailPanel) and served by the one endpoint (/admin/api/<module>/<tool>). A
// tool lives in one module (its home) and can be opened from any other (A3a). Each tool says:
//   title, view (the rule to see it: lowest level and departments; Permissions can change it),
//   columns [[key, label, kind]] (kind: name, text, money, date, stage, pill, count),
//   filters [[key, label, options]] (the Shortcuts-style FilterMenu), workflow (its stages and moves),
//   list(PDO, query, uid) → [rows, total, counts by stage], get(PDO, id) → the detail's fields,
//   open: 'detail' (the DetailPanel), 'lead' (the lead panel), or a page for its full view,
//   metrics(PDO) → [[label, value, filter]], create (a quick task): fields and do(PDO, input, uid).
declare(strict_types=1);

$usd = fn ($c) => '$' . number_format(((int) $c) / 100, 2);
$day = fn ($d) => $d ? date('M j, Y', strtotime((string) $d)) : '';
if (!function_exists('ak_gallery_tell')) {
    /** An email to a gallery's owner (a member, or a business's owners and managers) when the team takes something off the site. */
    function ak_gallery_tell(PDO $pdo, array $row, string $subject, string $body): void
    {
        try {
            require_once __DIR__ . '/../lib/Mailer.php';
            $to = [];
            if ($row['owner_kind'] === 'members') { $st = $pdo->prepare('SELECT email FROM users WHERE id = ?'); $st->execute([(int) $row['owner_ref']]); $to[] = (string) $st->fetchColumn(); }
            else { require_once __DIR__ . '/../lib/Team.php'; foreach (Team::members($pdo, (int) $row['owner_ref']) as $m) if (in_array($m['role'], ['owner', 'manager'], true)) $to[] = (string) $m['email']; }
            foreach (array_filter($to) as $em) Mailer::send($em, $subject, $body . "

Traversence
");
        } catch (Throwable $x) { error_log('gallery tell: ' . $x->getMessage()); }
    }
}

require_once __DIR__ . '/../lib/Orders.php';
$tvSortSql = function (array $q, array $cols, string $def): string { $k = (string) ($q['sort'] ?? ''); return isset($cols[$k]) ? $cols[$k] . (($q['dir'] ?? '') === 'desc' ? ' DESC' : ' ASC') . ', e.id' : $def; };   // a list that pages itself: the columns it sorts by
$tvNoTest = fn (PDO $pdo, string $a = 'o') => Orders::hasCol($pdo, 'qb_env') ? " AND ($a.qb_env IS NULL OR $a.qb_env <> 'sandbox')" : '';   // sandbox orders have their own list

return [

    // ---- Back-Office ------------------------------------------------------------------------------------------------------

    'backoffice/orders' => [
        'title' => 'Client orders', 'view' => ['min' => 'staff', 'depts' => ['fin', 'svc', 'sal']], 'workflow' => 'client_order', 'open' => '/admin/backoffice/orders/{id}',
        'columns' => [['title', 'Order', 'name'], ['client', 'Client', 'text'], ['stage', 'Stage', 'stage'], ['total', 'Total', 'money'], ['updated', 'Updated', 'date']],
        'filters' => [['mine', 'Whose', ['' => 'Everyone\'s', 'me' => 'Started by me']]],
        'list' => function (PDO $pdo, array $q, int $uid) use ($tvNoTest) {
            require_once __DIR__ . '/Workflow.php';
            $w = ['o.created_by IS NOT NULL' . $tvNoTest($pdo)]; $a = [];
            if (($q['mine'] ?? '') === 'me') { $w[] = 'o.created_by = ?'; $a[] = $uid; }
            if (($s = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(' . (Orders::hasCol($pdo, 'order_no') ? 'COALESCE(o.order_no, o.id)' : 'o.id') . ' = ? OR o.client_company LIKE ? OR o.bill_name LIKE ? OR o.bill_email LIKE ?)'; array_push($a, (int) ltrim($s, '#'), "%$s%", "%$s%", "%$s%"); }
            $st = $pdo->prepare('SELECT o.id FROM orders o WHERE ' . implode(' AND ', $w) . ' ORDER BY o.id DESC LIMIT 400'); $st->execute($a);
            $rows = []; $counts = [];
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $it = Workflow::item($pdo, 'client_order', (int) $id); $o = $it['row'];
                $counts[$it['stage']] = ($counts[$it['stage']] ?? 0) + 1;
                $rows[] = ['id' => (int) $id, 'title' => 'Order #' . Orders::no($o), 'client' => $o['client_company'] ?: $o['bill_name'], 'stage' => $it['stage'], 'total' => (int) $o['total_cents'], 'updated' => $o['updated_at']];
            }
            return [$rows, count($rows), $counts];
        },
        'get' => function (PDO $pdo, int $id) use ($usd, $day) {
            require_once __DIR__ . '/../lib/ClientOrders.php';
            $o = Orders::get($pdo, $id); if (!$o) return null;
            $m = Orders::money($pdo, $id);
            $lines = array_map(fn ($l) => $l['title'] . ' · ' . (int) $l['qty'] . ' × ' . $usd($l['unit_cents']), array_filter(Orders::lines($pdo, $id), fn ($l) => $l['status'] !== 'cancelled'));
            return ['title' => 'Order #' . Orders::no($o) . (Orders::isTest($o) ? ' (sandbox)' : ''), 'subtitle' => ($o['client_company'] ?: $o['bill_name']) . ' · ' . $o['bill_email'],
                'fields' => [['Client', trim(($o['client_company'] ? $o['client_company'] . ', ' : '') . $o['bill_name'])], ['Email', $o['bill_email']], ['Phone', $o['client_phone'] ?? ''],
                    ['Items', implode("\n", $lines)], ['Total', $usd($o['total_cents']) . ($o['tax_cents'] ? ' (tax ' . $usd($o['tax_cents']) . ')' : '')], ['Paid', $usd($m['paid'])],
                    ['Terms', ClientOrders::PAY_MODES[$o['pay_mode']] ?? ''], ['Invoice', $o['invoice_no'] ?: ''], ['Tracking', $o['tracking'] ?? ''], ['Started', $day($o['created_at'])]],
                'links' => [['Open the order', '/admin/backoffice/orders/' . $id, 'page'], ['The client\'s page', ClientOrders::url($o), 'new']]];
        },
        'metrics' => function (PDO $pdo) use ($tvNoTest) {
            require_once __DIR__ . '/Workflow.php';
            $n = []; foreach ($pdo->query('SELECT id FROM orders o WHERE created_by IS NOT NULL' . $tvNoTest($pdo) . ' ORDER BY id DESC LIMIT 400')->fetchAll(PDO::FETCH_COLUMN) as $id) { $s = Workflow::item($pdo, 'client_order', (int) $id)['stage']; $n[$s] = ($n[$s] ?? 0) + 1; }
            return [['Drafts', $n['draft'] ?? 0, 'draft'], ['With clients', $n['sent'] ?? 0, 'sent'], ['Waiting for payment', $n['approved'] ?? 0, 'approved'], ['To make', ($n['paid'] ?? 0) + ($n['production'] ?? 0) + ($n['ordered'] ?? 0), 'paid,production,ordered']];
        },
        'create' => ['label' => 'New client order', 'page' => '/admin/backoffice/orders/new'],
    ],

    // Order management (Jason, 2026-10-28, like his old dashboard): every printed item on an approved order, where it
    // stands, who makes it and when it's due; In the queue / In production / Shipped / Delivered with their counts, and the
    // completed ones in the archive (Completed, or found by searching).
    'backoffice/production' => [
        'title' => 'Order management', 'view' => ['min' => 'staff', 'depts' => ['svc', 'fin', 'sal']], 'open' => '/admin/backoffice/orders/{id}', 'count_by' => 'status',
        'columns' => [['title', 'Order', 'name'], ['client', 'Client', 'text'], ['product', 'Product', 'text'], ['code', 'Product code', 'text'], ['status', 'Status', 'pill'], ['vendor', 'Vendor', 'text'], ['due', 'Due', 'date']],
        'filters' => [['status', 'Status', ['' => 'All open', 'queue' => 'In the queue', 'production' => 'In production', 'shipped' => 'Shipped or ready', 'delivered' => 'Delivered', 'complete' => 'Completed (archive)']]],
        'list' => function (PDO $pdo, array $q) use ($tvNoTest) {
            require_once __DIR__ . '/../lib/ClientOrders.php'; require_once __DIR__ . '/../lib/WorkOrders.php'; require_once __DIR__ . '/../lib/Vendors.php'; require_once __DIR__ . '/../lib/Products.php';
            $bucket = function (string $st): string {
                if ($st === 'complete') return 'complete';   // done: the archive (Jason, 2026-10-30), shown when asked for or searched
                if ($st === 'delivered') return 'delivered';
                if (in_array($st, ['shipped', 'in_route', 'ready'], true)) return 'shipped';
                if (in_array($st, ['production', 'finishing', 'post'], true)) return 'production';
                return 'queue';
            };
            $t = trim((string) ($q['q'] ?? ''));
            $base = "SELECT o.* FROM orders o WHERE o.created_by IS NOT NULL AND o.approved_at IS NOT NULL AND o.status IN ('pending','paid','part_refunded')" . $tvNoTest($pdo);
            $orders = array_merge($pdo->query($base . " AND COALESCE(o.stage,'') <> 'complete' ORDER BY o.approved_at DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC),
                $pdo->query($base . " AND o.stage = 'complete' ORDER BY o.approved_at DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC));   // the archive: the newest 300 completed
            $wos = [];
            if ($orders && WorkOrders::statusReady($pdo)) foreach ($pdo->query('SELECT * FROM order_work_orders WHERE order_id IN (' . implode(',', array_map(fn ($o) => (int) $o['id'], $orders)) . ") AND status NOT IN ('cancelled','declined','rejected') ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $w)
                foreach (array_filter(explode(',', (string) $w['line_ids'])) as $lid) $wos[(int) $lid] = $w;   // the item's latest work order
            $rows = []; $counts = [];
            foreach ($orders as $o) foreach (Orders::lines($pdo, (int) $o['id']) as $l) {
                if (in_array($l['product'], ['shipping', 'go_code'], true) || in_array($l['status'], ['cancelled', 'refunded'], true)) continue;
                $st = ClientOrders::itemStatus($o, $l);
                if (in_array($st, ['cancelled', 'archived', 'deactivated', 'deleted', 'payment_hold', 'collections'], true)) continue;
                $p = $l['product'] === 'product' && $l['product_id'] ? Products::get($pdo, (int) $l['product_id']) : null;
                $w = $wos[(int) $l['id']] ?? null; $v = $w ? $w['vendor'] : ((Vendors::forLine($pdo, $l) ?? [])['name'] ?? '');
                $row = ['id' => (int) $o['id'], 'href' => '/admin/backoffice/orders/' . (int) $o['id'] . '#l' . (int) $l['id'], 'title' => '#' . Orders::no($o), 'client' => $o['client_company'] ?: $o['bill_name'],
                    'product' => preg_replace('/ · [\d,]+$/', '', (string) $l['title']) . ' · ' . number_format($l['product'] === 'product' ? ClientOrders::runQty($l) : (int) $l['qty']),
                    'code' => (string) ($p['sku'] ?? ''), 'status' => ClientOrders::statusLabel($st), 'vendor' => $v . ($w ? ' · ' . $w['wo_no'] : ''),
                    'due' => $w && WorkOrders::dueAt($w) ? date('Y-m-d', WorkOrders::dueAt($w)) : ($o['due_date'] ?? null), 'b' => $bucket($st)];
                if ($t !== '' && stripos(implode(' ', [$row['title'], $row['client'], $row['product'], $row['code'], $row['vendor']]), $t) === false) continue;
                $counts[$row['b']] = ($counts[$row['b']] ?? 0) + 1;
                $rows[] = $row;
            }
            $f = (string) ($q['status'] ?? '');
            $rows = array_values(array_filter($rows, fn ($r) => $f !== '' ? $r['b'] === $f : ($r['b'] !== 'complete' || $t !== '')));   // All open: the archive only when searched
            if ($f === 'complete') usort($rows, fn ($a, $b) => $b['id'] <=> $a['id']);   // the archive: newest first
            else usort($rows, fn ($a, $b) => [$a['due'] ? 0 : 1, (string) $a['due']] <=> [$b['due'] ? 0 : 1, (string) $b['due']]);   // soonest due first
            return [$rows, count($rows), $counts];
        },
    ],

    // Work orders (decisions/0101, addendum 2026-10-28): every one we've made, where it stands, for which order and client.
    'backoffice/work_orders' => [
        'title' => 'Work orders', 'view' => ['min' => 'staff', 'depts' => ['svc', 'fin']], 'open' => 'detail', 'count_by' => 'status',   // each opens the work order itself (Jason, 2026-10-28)
        'columns' => [['title', 'Work order', 'name'], ['order', 'Order', 'text'], ['client', 'Client', 'text'], ['project', 'Project', 'text'], ['vendor', 'Vendor', 'text'], ['status', 'Status', 'pill'], ['due', 'Must have', 'date'], ['cost', 'Cost', 'money']],
        'filters' => function (PDO $pdo) {
            require_once __DIR__ . '/../lib/WorkOrders.php';
            $vs = ['' => 'Every vendor'];
            try { foreach ($pdo->query('SELECT DISTINCT vendor FROM order_work_orders ORDER BY vendor')->fetchAll(PDO::FETCH_COLUMN) as $v) $vs[$v] = $v; } catch (Throwable $x) {}
            return [['status', 'Status', ['' => 'All'] + array_diff_key(WorkOrders::STATUS, ['draft' => 1]) + ['draft' => 'Not sent']], ['vendor', 'Vendor', $vs]];
        },
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/WorkOrders.php';
            if (!WorkOrders::statusReady($pdo)) return [[], 0, []];
            $w = [Orders::hasCol($pdo, 'qb_env') ? "o.qb_env NOT IN ('sandbox','mock')" : '1']; $a = [];
            if (($v = (string) ($q['vendor'] ?? '')) !== '') { $w[] = 'w.vendor = ?'; $a[] = $v; }
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(w.wo_no LIKE ? OR w.project LIKE ? OR w.vendor LIKE ? OR o.client_company LIKE ? OR o.bill_name LIKE ? OR w.job_no = ?)'; array_push($a, "%$t%", "%$t%", "%$t%", "%$t%", "%$t%", $t); }
            $st = $pdo->prepare('SELECT w.*, o.client_company, o.bill_name FROM order_work_orders w JOIN orders o ON o.id = w.order_id WHERE ' . implode(' AND ', $w) . ' ORDER BY w.id DESC LIMIT 400'); $st->execute($a);   // the order's number from the order itself (order_no and test_no come with 2026-12-29_order_numbers.sql)
            $ord = [];
            $rows = []; $counts = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
                if (($f = (string) ($q['status'] ?? '')) !== '' && $r['status'] !== $f) continue;
                $due = WorkOrders::dueAt($r);
                $rows[] = ['id' => (int) $r['id'], 'title' => $r['wo_no'], 'order' => '#' . Orders::no($ord[(int) $r['order_id']] ??= (Orders::get($pdo, (int) $r['order_id']) ?: ['id' => (int) $r['order_id']])),
                    'client' => $r['client_company'] ?: $r['bill_name'], 'project' => (string) $r['project'], 'vendor' => $r['vendor'] . (($r['job_no'] ?? '') !== '' ? ' · job ' . $r['job_no'] : ''),
                    'status' => WorkOrders::STATUS[$r['status']] ?? $r['status'], 'due' => $due ? date('Y-m-d', $due) : null, 'cost' => $r['cost_cents'] !== null ? (int) $r['cost_cents'] : null];
            }
            return [$rows, count($rows), $counts];
        },
        'get' => function (PDO $pdo, int $id) {
            require_once __DIR__ . '/../lib/WorkOrders.php';
            $w = WorkOrders::get($pdo, $id); if (!$w) return null;
            $o = Orders::get($pdo, (int) $w['order_id']) ?: ['id' => (int) $w['order_id']];
            $st = (string) ($w['status'] ?? 'sent'); $due = WorkOrders::dueAt($w); $parts = WorkOrders::parts($w);
            $items = WorkOrders::items($pdo, $w); $files = WorkOrders::files($pdo, $w);
            $log = []; foreach (WorkOrders::log($pdo, $id) as $g) {   // the same step saved again and again: one line, with how many times
                $k = count($log) - 1; $txt = $g['note'] ?: ($g['status'] ?? '');
                if ($k >= 0 && ($log[$k]['note'] ?: ($log[$k]['status'] ?? '')) === $txt && (int) $log[$k]['by_vendor'] === (int) $g['by_vendor']) { $log[$k]['n'] = ($log[$k]['n'] ?? 1) + 1; $log[$k]['created_at'] = $g['created_at']; continue; }
                $log[] = $g;
            }
            $vurl = '/wo.php?w=' . rawurlencode((string) $w['token']);
            return ['title' => 'Work order ' . $w['wo_no'], 'subtitle' => (WorkOrders::STATUS[$st] ?? $st) . ' · ' . $w['vendor'] . (($w['job_no'] ?? '') !== '' ? ' · their job ' . $w['job_no'] : '') . ' · order #' . Orders::no($o) . ' · ' . trim((string) (($o['client_company'] ?? '') ?: ($o['bill_name'] ?? ''))),
                'fields' => array_values(array_filter([
                    ($w['project'] ?? '') !== '' ? ['Project', (string) $w['project']] : null,
                    ['Vendor', $w['vendor'] . ($w['vendor_email'] !== '' ? ' <' . $w['vendor_email'] . '>' : ' (no email yet)')],
                    !empty($w['accepted_by']) ? ['Received by', $w['accepted_by'] . (($w['job_no'] ?? '') !== '' ? ' · their job ' . $w['job_no'] : '')] : null,
                    ['Must have by', $due ? date('l, M j, Y', $due) . (!empty($w['due_time']) ? ' at ' . date('g:i a', $due) : '') : 'Not set'],
                    ($w['delivery'] ?? '') !== '' ? ['Delivery', WorkOrders::DELIVERY[$w['delivery']] ?? (string) $w['delivery']] : null,
                    ['Total cost', isset($w['cost_cents']) && $w['cost_cents'] !== null ? Orders::usd((int) $w['cost_cents']) : 'Not set'],
                    ($w['po_no'] ?? '') !== '' ? ['P.O. number', (string) $w['po_no']] : null,
                    ['Items', implode("\n", array_map(fn ($l) => WorkOrders::itemText($l, $parts[(int) $l['id']] ?? ['', '']), $items)) ?: 'None'],
                    ['Ship to', (string) $w['ship_to'] !== '' ? (string) $w['ship_to'] : 'Not set'],
                    ['Files', $files ? implode("\n", array_map(fn ($f) => $f[1] . ' (' . $f[2] . ')', $files)) : 'None'],
                    (string) $w['note'] !== '' ? ['Production notes', (string) $w['note']] : null,
                    ['Sent', $w['sent_at'] ? date('M j, g:i a', strtotime((string) $w['sent_at'])) : 'Not yet'],
                    $log ? ['History', implode("\n", array_map(fn ($g) => date('M j, g:i a', strtotime((string) $g['created_at'])) . ' · ' . ($g['note'] ?: (WorkOrders::STATUS[$g['status']] ?? $g['status'])) . (($g['n'] ?? 1) > 1 ? ' (×' . $g['n'] . ')' : '') . ((int) $g['by_vendor'] ? ' (' . $w['vendor'] . ')' : ''), array_reverse($log)))] : null,
                ])),
                'links' => array_values(array_filter([
                    ['See it as the vendor', $vurl, 'new'],
                    $st === 'draft' ? ['Edit it (not sent yet)', '/admin/backoffice/orders/' . (int) $w['order_id'] . '?wo_edit=' . $id . '#wo-edit', 'page'] : null,
                    ['Its order #' . Orders::no($o), '/admin/backoffice/orders/' . (int) $w['order_id'] . '#work', 'page'],
                ])),
                'acts' => array_values(array_filter([
                    !in_array($st, ['cancelled', 'declined', 'rejected', 'received'], true) ? ['key' => 'link', 'label' => 'Get the link to send yourself', 'asks' => [], 'go' => $st === 'draft' ? 'Get the link (marks it sent)' : 'Get the link'] : null,
                    in_array($st, ['draft', 'sent'], true) ? ['key' => 'send', 'label' => $st === 'draft' ? 'Send it' : 'Send it again', 'asks' => [], 'go' => 'Email it to ' . $w['vendor']] : null,
                    ($w['delivery'] ?? '') === 'courier' && !in_array($st, ['received', 'cancelled', 'rejected', 'declined'], true) ? ['key' => 'tracking', 'label' => ($w['tracking'] ?? '') !== '' ? 'Courier tracking: ' . trim(($w['carrier'] ?? '') . ' ' . $w['tracking']) : 'Courier booked: add tracking', 'asks' => [['carrier', 'Courier', 'input', (string) ($w['carrier'] ?? ''), null, 'optional'], ['tracking', 'Tracking number', 'input', (string) ($w['tracking'] ?? '')]], 'go' => 'Save' . ($w['sent_at'] ? ' and email the vendor' : '')] : null,
                    !in_array($st, ['received', 'cancelled', 'rejected', 'declined'], true) ? ['key' => 'cancel', 'label' => 'Cancel it', 'asks' => [['why', 'Why (the vendor sees this if it was sent)', 'input', '', null, 'optional']], 'go' => 'Cancel the work order', 'danger' => true] : null,
                ]))];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid = 0) {
            require_once __DIR__ . '/../lib/WorkOrders.php';
            $w = WorkOrders::get($pdo, $id) ?: throw new RuntimeException('That work order isn\'t here.');
            if ($key === 'link') { [$w, $url] = WorkOrders::handLink($pdo, $w, $uid); return 'The vendor\'s link (paste it in your email to ' . ($w['vendor_email'] ?: $w['vendor']) . '): ' . $url; }
            if ($key === 'send') { $w = WorkOrders::send($pdo, $w, $uid); WorkOrders::markOrdered($pdo, Orders::get($pdo, (int) $w['order_id']) ?: [], $uid, (string) $w['vendor']); return 'Emailed to ' . $w['vendor'] . ' (' . $w['vendor_email'] . ').'; }
            if ($key === 'tracking') { $w = WorkOrders::setTracking($pdo, $w, (string) ($in['carrier'] ?? ''), (string) ($in['tracking'] ?? ''), $uid); return 'Tracking saved' . ($w['sent_at'] ? ' and emailed to ' . $w['vendor'] : ' (it goes with the work order)') . '.'; }
            if ($key === 'cancel') { WorkOrders::cancel($pdo, $w, (string) ($in['why'] ?? ''), $uid); return 'Work order ' . $w['wo_no'] . ' cancelled.'; }
            throw new InvalidArgumentException('Unknown action.');
        },
    ],

    // The Collateral Center (decisions/0101, addendum 2026-10-28): each client's configured pieces, from their approved
    // orders, to see, release to the client, and reorder (the same art for the client to confirm, or with changes).
    'backoffice/collateral' => [
        'title' => 'Collateral', 'view' => ['min' => 'staff', 'depts' => ['svc', 'sal', 'fin']], 'open' => 'detail', 'count_by' => 'show',
        'columns' => [['title', 'Item', 'name'], ['client', 'Client', 'text'], ['product', 'Product', 'text'], ['qty', 'Quantity', 'text'], ['files', 'Files', 'text'], ['shown', 'Client sees it', 'pill'], ['last', 'Last ordered', 'date']],
        'filters' => [['show', 'Show', ['' => 'In use', 'released' => 'Released to the client', 'held' => 'Not released', 'archived' => 'Archived']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/Collateral.php'; require_once __DIR__ . '/../lib/Products.php';
            if (!Collateral::ready($pdo)) return [[], 0, []];
            if (!(int) $pdo->query('SELECT COUNT(*) FROM collateral')->fetchColumn()) Collateral::backfill($pdo);   // the first time: past approved orders brought in
            $t = trim((string) ($q['q'] ?? '')); $w = ['1']; $a = [];
            if ($t !== '') { $w[] = '(c.name LIKE ? OR c.client_company LIKE ? OR c.client_name LIKE ? OR c.client_email LIKE ?)'; array_push($a, "%$t%", "%$t%", "%$t%", "%$t%"); }
            $st = $pdo->prepare('SELECT c.*, o.approved_at AS last_at FROM collateral c LEFT JOIN orders o ON o.id = c.source_order_id WHERE ' . implode(' AND ', $w) . ' ORDER BY c.client_company, c.client_name, c.name LIMIT 1000'); $st->execute($a);
            $rows = []; $counts = []; $f = (string) ($q['show'] ?? '');
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $k = $c['status'] === 'archived' ? 'archived' : ((int) $c['released'] ? 'released' : 'held');
                $counts[$k] = ($counts[$k] ?? 0) + 1;
                if ($f === '' ? $k === 'archived' : $k !== $f) continue;
                $p = $c['product_id'] ? Products::get($pdo, (int) $c['product_id']) : null;
                $fs = Collateral::files($pdo, $c); $kinds = array_count_values(array_column($fs, 0));
                $rows[] = ['id' => (int) $c['id'], 'title' => $c['name'], 'client' => $c['client_company'] ?: ($c['client_name'] ?: $c['client_email']), 'product' => trim(($p['sku'] ?? '') . ' ' . ($p['name'] ?? '')) ?: 'Custom',
                    'qty' => number_format((int) $c['qty']), 'files' => $fs ? implode(', ', array_map(fn ($k, $n) => ($n > 1 ? $n . ' ' : '') . $k, array_keys($kinds), $kinds)) : 'none', 'shown' => (int) $c['released'] ? 'released' : 'not released', 'last' => $c['last_at']];
            }
            $counts[''] = ($counts['released'] ?? 0) + ($counts['held'] ?? 0);
            return [$rows, count($rows), $counts];
        },
        'get' => function (PDO $pdo, int $id) {
            require_once __DIR__ . '/../lib/Collateral.php'; require_once __DIR__ . '/../lib/Products.php'; require_once __DIR__ . '/../lib/Vendors.php';
            $c = Collateral::get($pdo, $id); if (!$c) return null;
            $p = $c['product_id'] ? Products::get($pdo, (int) $c['product_id']) : null; $v = $c['vendor_id'] ? Vendors::get($pdo, (int) $c['vendor_id']) : null;
            $src = Orders::get($pdo, (int) $c['source_order_id']); $first = Orders::get($pdo, (int) $c['first_order_id']);
            $fs = Collateral::files($pdo, $c);
            return ['title' => $c['name'], 'subtitle' => ($c['client_company'] ?: $c['client_name']) . ' · ' . $c['client_email'] . ((int) $c['released'] ? ' · the client sees it' : ' · not released to the client') . ($c['status'] === 'archived' ? ' · archived' : ''),
                'fields' => [['Product', trim(($p['sku'] ?? '') . ' · ' . ($p['name'] ?? 'Custom'), ' ·')], ['Quantity', number_format((int) $c['qty'])], ['Specs', (string) $c['options']], ['Note', (string) $c['note']], ['Vendor', (string) ($v['name'] ?? '')],
                    ['Ordered', (int) $c['times_ordered'] . ' time' . ((int) $c['times_ordered'] === 1 ? '' : 's') . ($src ? ', last on order #' . Orders::no($src) . ($src['approved_at'] ? ' (' . date('M j, Y', strtotime((string) $src['approved_at'])) . ')' : '') : '')],
                    ['Files', $fs ? implode("\n", array_map(fn ($f) => $f[0] . ': ' . $f[1], $fs)) : 'None yet']],
                'links' => array_merge(array_map(fn ($f) => [$f[0] . ': ' . $f[1], $f[2] . (str_contains($f[2], 'download=1') ? '' : '&download=1'), 'new'], $fs),
                    $src ? [['Its newest order (#' . Orders::no($src) . ')', '/admin/backoffice/orders/' . (int) $src['id'], 'page']] : [],
                    $first && $src && (int) $first['id'] !== (int) $src['id'] ? [['Its first order (#' . Orders::no($first) . ')', '/admin/backoffice/orders/' . (int) $first['id'], 'page']] : []),
                'acts' => [
                    ['key' => 'reorder', 'label' => 'Reorder', 'asks' => [['qty', 'Quantity', 'input', (string) $c['qty']], ['art', 'The artwork', 'select', [['same', 'The same art (the client confirms the reuse)'], ['changes', 'With changes (a new proof)']], 'same']], 'go' => 'Make the reorder (a draft)'],
                    ['key' => 'release', 'label' => (int) $c['released'] ? 'Stop showing the client' : 'Release to the client', 'asks' => [], 'go' => (int) $c['released'] ? 'Yes, stop showing it' : 'Yes, the client may see and reuse it'],
                    ['key' => 'rename', 'label' => 'Rename', 'asks' => [['name', 'Name', 'input', $c['name']]]],
                    ['key' => 'archive', 'label' => $c['status'] === 'archived' ? 'Bring it back' : 'Archive', 'asks' => [], 'go' => $c['status'] === 'archived' ? 'Bring it back' : 'Archive it', 'danger' => $c['status'] !== 'archived']]];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid = 0) {
            require_once __DIR__ . '/../lib/Collateral.php';
            $c = Collateral::get($pdo, $id) ?: throw new InvalidArgumentException('That collateral item isn\'t here.');
            if ($key === 'reorder') {
                $n = (int) preg_replace('/\D/', '', (string) ($in['qty'] ?? $c['qty']));
                $nid = Collateral::reorder($pdo, [$id], [$id => $n ?: (int) $c['qty']], ($in['art'] ?? 'same') !== 'changes', $uid);
                return 'Reorder made: order #' . Orders::no(Orders::get($pdo, $nid) ?: ['id' => $nid]) . ' (a draft). Open it from Orders, check it and send it to the client.';
            }
            if ($key === 'release') { Collateral::edit($pdo, $id, ['released' => !(int) $c['released']]); return (int) $c['released'] ? 'The client no longer sees it.' : 'Released: the client sees it on their Your orders page.'; }
            if ($key === 'rename') { Collateral::edit($pdo, $id, ['name' => (string) ($in['name'] ?? '')]); return 'Renamed.'; }
            if ($key === 'archive') { Collateral::edit($pdo, $id, ['status' => $c['status'] === 'archived' ? 'active' : 'archived']); return $c['status'] === 'archived' ? 'Back in use.' : 'Archived.'; }
            throw new InvalidArgumentException('Unknown action.');
        },
    ],

    // Clients are listings (decisions/0101, addendum 2026-10-28): each client business (tagged, or a private listing made
    // for them), its contacts (the listing's team, and contacts not yet claimed) and its orders.
    'backoffice/clients' => [
        'title' => 'Clients', 'view' => ['min' => 'staff', 'depts' => ['svc', 'sal', 'fin']], 'open' => 'detail', 'count_by' => 'kind',
        'columns' => [['title', 'Business', 'name'], ['city', 'Town', 'text'], ['kind', 'Listing', 'pill'], ['people', 'Contacts', 'text'], ['orders', 'Orders', 'text'], ['last', 'Last order', 'date']],
        'filters' => [['kind', 'Listing', ['' => 'All', 'private' => 'Private', 'public' => 'On Traversence']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/Clients.php';
            if (!Clients::ready($pdo)) return [[], 0, []];
            $t = trim((string) ($q['q'] ?? '')); $w = ['1']; $a = [];
            if ($t !== '') { $w[] = '(e.name LIKE ? OR e.id = ?)'; array_push($a, "%$t%", (int) $t); }
            $st = $pdo->prepare("SELECT e.id, e.name, e.status, e.is_suppressed, JSON_UNQUOTE(mc.meta_value) AS city,
                    (SELECT COUNT(*) FROM orders o WHERE o.listing_id = e.id AND o.status <> 'void') AS n_orders, (SELECT MAX(COALESCE(o.sent_at, o.created_at)) FROM orders o WHERE o.listing_id = e.id) AS last_at,
                    (SELECT COUNT(*) FROM listing_memberships m WHERE m.listing_id = e.id AND m.status = 'active') AS n_team,
                    (SELECT COUNT(*) FROM listing_invites i WHERE i.listing_id = e.id AND i.accepted_at IS NULL AND i.cancelled_at IS NULL) AS n_open
                  FROM client_listings c JOIN entities e ON e.id = c.entity_id LEFT JOIN entity_metadata mc ON mc.entity_id = e.id AND mc.meta_key = 'city' WHERE " . implode(' AND ', $w) . ' ORDER BY e.name LIMIT 1000');
            $st->execute($a);
            $rows = []; $counts = []; $f = (string) ($q['kind'] ?? '');
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $k = (int) $r['is_suppressed'] ? 'private' : 'public'; $counts[$k] = ($counts[$k] ?? 0) + 1;
                if ($f !== '' && $f !== $k) continue;
                $rows[] = ['id' => (int) $r['id'], 'title' => $r['name'], 'city' => (string) $r['city'], 'kind' => $k === 'private' ? 'private' : ($r['status'] === 'unclaimed' ? 'not claimed' : 'claimed'),
                    'people' => (int) $r['n_team'] . ' active' . ((int) $r['n_open'] ? ' · ' . (int) $r['n_open'] . ' invited' : ''), 'orders' => (string) (int) $r['n_orders'], 'last' => $r['last_at']];
            }
            return [$rows, count($rows), $counts];
        },
        'get' => function (PDO $pdo, int $id) {
            require_once __DIR__ . '/../lib/Clients.php';
            $c = Clients::get($pdo, $id); if (!$c) return null;
            $people = Clients::contacts($pdo, $id);
            $orders = $pdo->prepare("SELECT * FROM orders WHERE listing_id = ? AND status <> 'void' ORDER BY id DESC LIMIT 30"); $orders->execute([$id]); $os = $orders->fetchAll(PDO::FETCH_ASSOC);
            $line = fn ($p) => trim($p['name'] . ' <' . $p['email'] . '>') . ' · ' . Team::roleName($p['role']) . ' · ' . ($p['kind'] === 'member' ? 'active' : ($p['sent_at'] ? 'invited ' . date('M j', strtotime((string) $p['sent_at'])) : 'not invited yet'));
            $open = array_values(array_filter($people, fn ($p) => $p['kind'] === 'invite'));
            $roles = array_map(fn ($k, $v) => [$k, $v], array_keys(Team::ROLES), Team::ROLES);
            return ['title' => $c['name'], 'subtitle' => ((int) $c['is_suppressed'] ? 'A private listing (never shown on the site)' : ($c['status'] === 'unclaimed' ? 'On Traversence, not claimed' : 'On Traversence, claimed')) . ($c['city'] ? ' · ' . $c['city'] : ''),
                'fields' => [['Contacts', $people ? implode("\n", array_map($line, $people)) : 'None yet: add the point of contact'], ['Orders', count($os) . ($os ? ' (latest #' . Orders::no($os[0]) . ')' : '')], ['Note', (string) ($c['note'] ?? '')]],
                'links' => array_merge(array_map(fn ($o) => ['Order #' . Orders::no($o) . ' · ' . ($o['status'] === 'draft' ? 'draft' : Orders::usd((int) ($o['total_cents'] ?: $o['subtotal_cents']))), '/admin/backoffice/orders/' . (int) $o['id'], 'page'], array_slice($os, 0, 10)),
                    [['The listing (admin)', '/admin/local/listings/' . $id, 'page']], (int) $c['is_suppressed'] ? [] : [['Its public page', '/listing/view.php?id=' . $id, 'new']]),
                'acts' => array_values(array_filter([
                    ['key' => 'contact', 'label' => 'Add a contact', 'asks' => [['name', 'Name', 'input', ''], ['email', 'Email', 'input', ''], ['phone', 'Phone (optional)', 'input', '', null, 'optional'], ['role', 'Role', 'select', $roles, 'manager']], 'go' => 'Add the contact'],
                    $open ? ['key' => 'invite', 'label' => 'Send an invite', 'asks' => [['who', 'To', 'select', array_map(fn ($p) => [(string) $p['id'], trim($p['name'] . ' <' . $p['email'] . '>') . ($p['sent_at'] ? ' (again)' : '')], $open), (string) $open[0]['id']]], 'go' => 'Email the invite'] : null,
                    $open ? ['key' => 'uninvite', 'label' => 'Take a contact off', 'asks' => [['who', 'Who', 'select', array_map(fn ($p) => [(string) $p['id'], trim($p['name'] . ' <' . $p['email'] . '>')], $open), (string) $open[0]['id']]], 'go' => 'Take them off', 'danger' => true] : null,
                    ['key' => 'note', 'label' => 'Note', 'asks' => [['note', 'Note (our team only)', 'textarea', (string) ($c['note'] ?? '')]], 'go' => 'Save'],
                ]))];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid = 0) {
            require_once __DIR__ . '/../lib/Clients.php';
            if ($key === 'contact') { Clients::addContact($pdo, $uid, $id, $in, true); return 'Contact added (not invited yet): Send an invite when you\'re ready.'; }
            if ($key === 'invite') { Clients::sendInvite($pdo, $uid, (int) ($in['who'] ?? 0), true); return 'Invite emailed: they claim their place and make their account from it.'; }
            if ($key === 'uninvite') { Clients::removeInvite($pdo, $uid, (int) ($in['who'] ?? 0), true); return 'Taken off.'; }
            if ($key === 'note') { $pdo->prepare('UPDATE client_listings SET note = ? WHERE entity_id = ?')->execute([mb_substr(trim((string) ($in['note'] ?? '')), 0, 500), $id]); return 'Saved.'; }
            throw new InvalidArgumentException('Unknown action.');
        },
        'create' => ['label' => 'Add a client', 'fields' => [['name', 'Business name', 'input'], ['town', 'Town', 'input', null, 'optional'], ['state', 'State (two letters)', 'input', null, 'optional'],
                ['phone', 'Phone', 'input', null, 'optional'], ['email', 'Email', 'input', null, 'optional'], ['listing', 'Its listing # (if you know it)', 'input', null, 'optional'],
                ['private', 'If no listing matches', 'select', ['check' => 'Show me the matches first', 'make' => 'Make a private listing even so']]],
            'do' => function (PDO $pdo, array $in, int $uid) {
                require_once __DIR__ . '/../lib/Clients.php';
                if (!Clients::ready($pdo)) throw new RuntimeException('Run app/migrations/2027-01-04_clients.sql first.');
                if (ctype_digit(trim((string) ($in['listing'] ?? '')))) { $c = Clients::tag($pdo, (int) $in['listing'], $uid); return ['id' => (int) $c['id'], 'message' => 'Connected: ' . $c['name'] . ' is a client.']; }
                // it checks for listings first (Jason): a clear match is connected; possible ones are named; none: a private listing
                $found = []; foreach (array_filter([trim((string) $in['name']), trim((string) ($in['phone'] ?? ''))]) as $q) foreach (Clients::find($pdo, $q, (string) ($in['email'] ?? '')) as $f) $found[$f['id']] ??= $f;
                $town = mb_strtolower(trim((string) ($in['town'] ?? '')));
                $clear = array_values(array_filter($found, fn ($f) => $f['why'] === 'the phone' || (mb_strtolower($f['name']) === mb_strtolower(trim((string) $in['name'])) && ($town === '' || mb_strtolower($f['city']) === $town))));
                if (count($clear) === 1) { $c = Clients::tag($pdo, $clear[0]['id'], $uid); return ['id' => (int) $c['id'], 'message' => 'Already on Traversence: connected ' . $c['name'] . ' as a client.']; }
                if ($found && ($in['private'] ?? 'check') !== 'make') throw new RuntimeException('Possible listings: ' . implode('; ', array_map(fn ($f) => $f['name'] . ($f['city'] ? ' (' . $f['city'] . ')' : '') . ' #' . $f['id'], array_slice($found, 0, 5))) . '. Type its listing # to connect it, or choose "Make a private listing even so".');
                $c = Clients::makePrivate($pdo, $in, $uid); return ['id' => (int) $c['id'], 'message' => 'Not on Traversence: a private listing was made for ' . $c['name'] . ' (never shown on the site).'];
            }],
    ],

    'backoffice/client_match' => [   // our orders not connected to a business yet, grouped to settle at once (decisions/0101, addendum 2026-10-28)
        'title' => 'Orders to match', 'view' => ['min' => 'staff', 'depts' => ['svc', 'sal', 'fin']], 'open' => 'detail',
        'columns' => [['title', 'Client', 'name'], ['by', 'Grouped by', 'pill'], ['orders', 'Orders', 'text'], ['emails', 'Emails', 'text'], ['last', 'Latest', 'date']],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/Clients.php';
            $t = mb_strtolower(trim((string) ($q['q'] ?? ''))); $rows = [];
            foreach (Clients::matchGroups($pdo) as $g) {
                if ($t !== '' && !str_contains(mb_strtolower($g['label'] . ' ' . implode(' ', $g['emails'])), $t)) continue;
                $rows[] = ['id' => (int) $g['orders'][0]['id'], 'title' => $g['label'], 'by' => ['c' => 'company', 'd' => 'email domain', 'e' => 'email'][$g['key'][0]],
                    'orders' => (string) count($g['orders']), 'emails' => implode(', ', array_slice($g['emails'], 0, 3)) . (count($g['emails']) > 3 ? ' +' . (count($g['emails']) - 3) : ''), 'last' => $g['orders'][0]['sent_at'] ?: $g['orders'][0]['created_at']];
            }
            foreach (Clients::toCheck($pdo) as $o) {   // connected, but the listing doesn't clearly match: check it
                $c = Clients::get($pdo, (int) $o['listing_id']); $lab = trim((string) ($o['client_company'] ?: ($o['bill_name'] ?: $o['bill_email'])));
                if ($t !== '' && !str_contains(mb_strtolower($lab . ' ' . $o['bill_email'] . ' ' . ($c['name'] ?? '')), $t)) continue;
                $rows[] = ['id' => (int) $o['id'], 'title' => $lab, 'by' => 'check: is it ' . ($c['name'] ?? '#' . $o['listing_id']) . '?', 'orders' => '#' . Orders::no($o), 'emails' => (string) $o['bill_email'], 'last' => $o['sent_at'] ?: $o['created_at']];
            }
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) {
            require_once __DIR__ . '/../lib/Clients.php';
            $g = Clients::matchGroup($pdo, $id);
            if (!$g) {   // a connected order to check
                $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND listing_id > 0'); $st->execute([$id]); $o = $st->fetch(PDO::FETCH_ASSOC); if (!$o) return null;
                $c = Clients::get($pdo, (int) $o['listing_id']);
                $found = []; foreach (array_filter([trim((string) $o['client_company']), trim((string) ($o['client_phone'] ?? ''))]) as $q) foreach (Clients::find($pdo, $q, (string) $o['bill_email']) as $f) if ($f['id'] !== (int) $o['listing_id']) $found[$f['id']] ??= $f;
                $opts = array_map(fn ($f) => [(string) $f['id'], $f['name'] . ($f['city'] ? ' (' . $f['city'] . ')' : '') . ' #' . $f['id'] . ' · by ' . $f['why']], array_values($found));
                return ['title' => 'Order #' . Orders::no($o) . ' · ' . trim((string) ($o['client_company'] ?: $o['bill_name'])), 'subtitle' => 'Connected to ' . ($c['name'] ?? '#' . $o['listing_id']) . ($c && $c['city'] ? ' (' . $c['city'] . ')' : '') . ', which doesn\'t clearly match: is it right?',
                    'fields' => [['Client', trim($o['bill_name'] . "\n" . $o['client_company'] . "\n" . $o['bill_email'] . "\n" . ($o['client_phone'] ?? ''))], ['Connected to', ($c['name'] ?? '') . ' #' . $o['listing_id']], ['Other possible listings', $opts ? implode("\n", array_map(fn ($x) => $x[1], $opts)) : 'None found']],
                    'links' => [['Order #' . Orders::no($o), '/admin/backoffice/orders/' . (int) $o['id'], 'page'], ['The listing', '/admin/local/listings/' . (int) $o['listing_id'], 'page']],
                    'acts' => array_values(array_filter([
                        ['key' => 'ok', 'label' => 'It\'s right', 'asks' => [], 'go' => 'Keep it'],
                        ['key' => 'undo', 'label' => 'Not this one', 'asks' => [], 'go' => 'Take it off (back to Orders to match)', 'danger' => true],
                        $opts ? ['key' => 'connect', 'label' => 'Connect to another listing', 'asks' => [['listing', 'Its listing', 'select', $opts, $opts[0][0]]], 'go' => 'Connect it'] : null,
                        ['key' => 'number', 'label' => 'Connect by listing #', 'asks' => [['listing', 'Listing #', 'input', '']], 'go' => 'Connect it'],
                    ]))];
            }
            $o0 = $g['orders'][0];
            $opts = array_map(fn ($f) => [(string) $f['id'], $f['name'] . ($f['city'] ? ' (' . $f['city'] . ')' : '') . ' #' . $f['id'] . ' · ' . ($f['private'] ? 'private' : ($f['status'] === 'unclaimed' ? 'not claimed' : 'claimed')) . ' · by ' . $f['why']], $g['found']);
            return ['title' => $g['label'], 'subtitle' => count($g['orders']) . ' order' . (count($g['orders']) === 1 ? '' : 's') . ' not connected to a business yet',
                'fields' => [['Emails', implode("\n", $g['emails'])], ['Possible listings', $g['found'] ? implode("\n", array_map(fn ($x) => $x[1], $opts)) : 'None found on Traversence']],
                'links' => array_map(fn ($o) => ['Order #' . Orders::no($o) . ' · ' . trim(($o['bill_name'] ?: $o['bill_email']) . ' · ' . ($o['status'] === 'draft' ? 'draft' : Orders::usd((int) ($o['total_cents'] ?: $o['subtotal_cents'])))), '/admin/backoffice/orders/' . (int) $o['id'], 'page'], array_slice($g['orders'], 0, 15)),
                'acts' => array_values(array_filter([
                    $opts ? ['key' => 'connect', 'label' => 'Connect to a listing', 'asks' => [['listing', 'Its listing', 'select', $opts, $opts[0][0]]], 'go' => 'Connect all ' . count($g['orders'])] : null,
                    ['key' => 'number', 'label' => 'Connect by listing #', 'asks' => [['listing', 'Listing #', 'input', '']], 'go' => 'Connect all ' . count($g['orders'])],
                    $g['key'][0] !== 'e' ? ['key' => 'private', 'label' => 'Make a private listing', 'asks' => [['name', 'Business name', 'input', $g['label']], ['town', 'Town', 'input', (string) ($o0['bill_city'] ?? ''), null, 'optional']], 'go' => 'Make it and connect'] : null,
                    ['key' => 'person', 'label' => 'A person, no business', 'asks' => [], 'go' => 'Settle as the person alone'],
                ]))];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid = 0) {
            require_once __DIR__ . '/../lib/Clients.php';
            $g = Clients::matchGroup($pdo, $id);
            if (!$g) {   // a connected order to check
                $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND listing_id > 0'); $st->execute([$id]); $o = $st->fetch(PDO::FETCH_ASSOC) ?: throw new RuntimeException('That order isn\'t here.');
                if ($key === 'ok') { Orders::event($pdo, $id, 'client_ok', 'Its business checked: ' . (Clients::get($pdo, (int) $o['listing_id'])['name'] ?? '#' . $o['listing_id']), $uid); return 'Kept.'; }
                if ($key === 'undo') { $pdo->prepare('UPDATE orders SET listing_id = NULL WHERE id = ?')->execute([$id]); try { $pdo->prepare('UPDATE collateral SET listing_id = NULL WHERE source_order_id = ? OR first_order_id = ?')->execute([$id, $id]); } catch (Throwable) {}
                    Orders::event($pdo, $id, 'client', 'Taken off its business (it wasn\'t the right one)', $uid); return 'Taken off: it\'s back in Orders to match.'; }
                if ($key === 'connect' || $key === 'number') { $lid = (int) preg_replace('/\D/', '', (string) ($in['listing'] ?? '')); $c = Clients::tag($pdo, $lid, $uid); Clients::attachOrder($pdo, $id, $lid, $uid); Orders::event($pdo, $id, 'client_ok', 'Its business checked: ' . $c['name'], $uid); return 'Connected to ' . $c['name'] . '.'; }
                throw new InvalidArgumentException('Unknown action.');
            }
            $o0 = $g['orders'][0];
            if ($key === 'connect' || $key === 'number') {
                $lid = (int) preg_replace('/\D/', '', (string) ($in['listing'] ?? ''));
                $c = Clients::tag($pdo, $lid, $uid);
                return Clients::settleGroup($pdo, $g, $lid, $uid) . ' order(s) connected to ' . $c['name'] . '.';
            }
            if ($key === 'private') {
                $c = Clients::makePrivate($pdo, ['name' => (string) ($in['name'] ?? $g['label']), 'town' => (string) ($in['town'] ?? ''), 'state' => (string) ($o0['bill_state'] ?? ''), 'phone' => (string) ($o0['client_phone'] ?? ''),
                    'email' => (string) $o0['bill_email'], 'address' => (string) ($o0['bill_street'] ?? '')], $uid);
                return 'A private listing was made for ' . $c['name'] . ' (never shown on the site); ' . Clients::settleGroup($pdo, $g, (int) $c['id'], $uid) . ' order(s) connected.';
            }
            if ($key === 'person') return Clients::settleGroup($pdo, $g, 0, $uid) . ' order(s) settled as the person alone.';
            throw new InvalidArgumentException('Unknown action.');
        },
    ],

    'backoffice/site_orders' => [
        'title' => 'Site orders (GO Codes)', 'view' => ['min' => 'staff', 'depts' => ['fin', 'svc']], 'open' => '/admin/order.php?id={id}',
        'columns' => [['title', 'Order', 'name'], ['who', 'Buyer', 'text'], ['status', 'Status', 'pill'], ['total', 'Total', 'money'], ['when', 'Date', 'date']],
        'filters' => [['status', 'Status', ['' => 'All', 'pending' => 'Waiting for payment', 'paid' => 'Paid', 'disputed' => 'Disputed', 'refunded' => 'Refunded']]],
        'list' => function (PDO $pdo, array $q) use ($tvNoTest) {
            $w = ["o.created_by IS NULL" . $tvNoTest($pdo), "o.status <> 'void'"]; $a = [];
            if (($s = (string) ($q['status'] ?? '')) !== '') { $w[] = 'o.status = ?'; $a[] = $s; }
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(o.id = ? OR o.bill_email LIKE ? OR o.bill_name LIKE ? OR o.invoice_no = ?)'; array_push($a, (int) ltrim($t, '#'), "%$t%", "%$t%", $t); }
            $st = $pdo->prepare('SELECT o.* FROM orders o WHERE ' . implode(' AND ', $w) . ' ORDER BY o.id DESC LIMIT 200'); $st->execute($a);
            $rows = array_map(fn ($o) => ['id' => (int) $o['id'], 'title' => 'Order #' . Orders::no($o), 'who' => $o['bill_name'] ?: $o['bill_email'], 'status' => $o['status'], 'total' => (int) $o['total_cents'], 'when' => $o['paid_at'] ?: $o['created_at']], $st->fetchAll(PDO::FETCH_ASSOC));
            return [$rows, count($rows), []];
        },
    ],

    'backoffice/plans' => [
        'title' => 'Plans & subscriptions', 'view' => ['min' => 'staff', 'depts' => ['fin', 'sal']], 'workflow' => 'subscription', 'open' => 'detail',
        'columns' => [['title', 'Account', 'name'], ['plan', 'Plan', 'text'], ['stage', 'Stage', 'stage'], ['price', 'Price', 'text'], ['renews', 'Renews / ends', 'date']],
        'filters' => [['kind', 'Accounts', ['' => 'All', 'listing' => 'Businesses', 'user' => 'Personal']]],
        'list' => function (PDO $pdo, array $q) use ($usd) {
            require_once __DIR__ . '/../lib/Billing.php';
            $w = ["s.status <> 'ended'"]; $a = [];
            if (in_array($q['kind'] ?? '', ['listing', 'user'], true)) { $w[] = 's.owner_type = ?'; $a[] = $q['kind']; }
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(e.name LIKE ? OR u.email LIKE ? OR s.bill_email LIKE ?)'; array_push($a, "%$t%", "%$t%", "%$t%"); }
            $st = $pdo->prepare("SELECT s.*, COALESCE(e.name, u.display_name, u.email) AS who FROM subscriptions s LEFT JOIN entities e ON s.owner_type = 'listing' AND e.id = s.owner_id
                LEFT JOIN users u ON s.owner_type = 'user' AND u.id = s.owner_id WHERE " . implode(' AND ', $w) . ' ORDER BY s.period_end LIMIT 300');
            $st->execute($a);
            $rows = []; $counts = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $counts[$s['status']] = ($counts[$s['status']] ?? 0) + 1;
                $rows[] = ['id' => (int) $s['id'], 'title' => (string) $s['who'], 'plan' => Billing::PLANS[$s['plan']][0] ?? $s['plan'], 'stage' => $s['status'],
                    'price' => $s['status'] === 'trial' ? 'Free trial' : $usd($s['price_cents']) . ($s['cycle'] === 'year' ? '/yr' : '/mo'), 'renews' => $s['status'] === 'trial' ? $s['trial_ends'] : $s['period_end']];
            }
            return [$rows, count($rows), $counts];
        },
        'get' => function (PDO $pdo, int $id) use ($usd, $day) {
            require_once __DIR__ . '/../lib/Billing.php';
            $s = Billing::get($pdo, $id);
            $o = Billing::owner($pdo, $s['owner_type'], (int) $s['owner_id']);
            $packs = array_map(fn ($a) => (Billing::ADDONS[$a['addon']][0] ?? $a['addon']) . ((int) $a['qty'] > 1 ? ' ×' . $a['qty'] : '') . ($a['remove_at_end'] ? ' (comes off at renewal)' : ''), Billing::addons($pdo, $id));
            $ch = array_map(fn ($c) => $day($c['created_at']) . ' · ' . $c['kind'] . ' · ' . $usd($c['amount_cents']) . ($c['status'] !== 'paid' ? ' (declined)' : ''), Billing::charges($pdo, $id));
            return ['title' => $o['name'], 'subtitle' => (Billing::PLANS[$s['plan']][0] ?? $s['plan']) . ($s['owner_type'] === 'user' ? ' · personal' : ''),
                'fields' => [['Plan', (Billing::PLANS[$s['plan']][0] ?? '') . ', ' . ($s['status'] === 'trial' ? 'free trial until ' . $day($s['trial_ends']) : $usd($s['price_cents']) . ($s['cycle'] === 'year' ? ' a year' : ' a month'))],
                    ['Renews', $s['cancel_at_end'] ? 'Stops ' . $day($s['period_end']) : $day($s['period_end'])], ['Then', $s['next_plan'] ? (Billing::PLANS[$s['next_plan']][0] ?? '') : ''],
                    ['Card', $s['card_label'] ?: 'None'], ['Declines', (int) $s['fails'] ? (int) $s['fails'] . ', next try ' . $day($s['next_try']) : ''], ['Packs', implode("\n", $packs)],
                    ['Billing email', $s['bill_email']], ['Charges', implode("\n", $ch)]],
                'links' => [['Their Plans page', Billing::link($s['owner_type'], (int) $s['owner_id']), 'new']]];
        },
        'metrics' => function (PDO $pdo) {
            require_once __DIR__ . '/../lib/Billing.php';
            $mrr = 0; $n = ['trial' => 0, 'active' => 0, 'past_due' => 0];
            foreach ($pdo->query("SELECT id, status, cycle, price_cents FROM subscriptions WHERE status <> 'ended'") as $s) {
                $n[$s['status']] = ($n[$s['status']] ?? 0) + 1;
                if ($s['status'] !== 'trial') $mrr += $s['cycle'] === 'year' ? (int) round($s['price_cents'] / 12) : (int) $s['price_cents'];
            }
            foreach ($pdo->query("SELECT a.addon, a.qty FROM subscription_addons a JOIN subscriptions s ON s.id = a.sub_id WHERE s.status IN ('active','past_due')") as $a) $mrr += (Billing::ADDONS[$a['addon']][1] ?? 0) * (int) $a['qty'];
            return [['A month (yearly by the month)', '$' . number_format($mrr / 100, 2), ''], ['Paid plans', $n['active'] + $n['past_due'], 'active'], ['On a trial', $n['trial'], 'trial'], ['Declined', $n['past_due'], 'past_due']];
        },
        'create' => ['label' => 'Start a trial', 'fields' => [['listing', 'Listing number', 'number'], ['plan', 'Plan', 'select', ['basic' => 'Basic Partner', 'core' => 'Core Partner', 'strategic' => 'Strategic Partner', 'cornerstone' => 'Cornerstone']], ['days', 'Days', 'select', ['30' => '30 days', '60' => '60 days', '90' => '90 days']]],
            'rule' => ['min' => 'manager', 'depts' => ['sal', 'fin']],
            'do' => function (PDO $pdo, array $in, int $uid) { require_once __DIR__ . '/../lib/Billing.php'; $s = Billing::trial($pdo, $uid, 'listing', (int) ($in['listing'] ?? 0), (string) ($in['plan'] ?? ''), (int) ($in['days'] ?? 30)); return ['id' => (int) $s['id'], 'message' => 'Trial started. The owner has an email.']; }],
    ],

    'backoffice/products' => [
        'title' => 'Products', 'view' => ['min' => 'staff', 'depts' => ['fin', 'svc', 'sal']], 'edit' => ['min' => 'staff', 'depts' => ['fin', 'svc']], 'open' => 'detail',
        'columns' => [['title', 'Item', 'name'], ['sku', 'Code', 'text'], ['kind', 'Kind', 'text'], ['price', 'From', 'text'], ['promo', 'For promo codes', 'text'], ['status', 'Shown', 'pill'], ['market', 'Marketplace', 'pill']],
        'business_hide' => ['promo'],   // ours only (decisions/0108); 'market': what the Marketplace's rules say, while the item's still being shaped (decisions/0142 §4)
        'filters' => [['status', 'Shown', ['' => 'All', 'active' => 'Active', 'hidden' => 'Hidden']]],
        'list' => function (PDO $pdo, array $q) use ($usd) {
            require_once __DIR__ . '/../lib/Products.php';
            $rows = [];
            foreach (Products::all($pdo) as $p) {
                if (($q['status'] ?? '') !== '' && $p['status'] !== $q['status']) continue;
                if (($t = trim((string) ($q['q'] ?? ''))) !== '' && stripos($p['name'] . ' ' . $p['sku'], $t) === false) continue;
                $first = $p['tiers'][0] ?? null;
                $rows[] = ['id' => (int) $p['id'], 'title' => $p['name'], 'sku' => $p['sku'], 'kind' => Products::kinds()[$p['kind']] ?? $p['kind'],
                    'price' => $first ? $usd($first['price']) . ((int) $first['qty'] > 1 ? ' for ' . number_format((int) $first['qty']) : '') : '', 'market' => Scope::isBusiness() ? (function () use ($p) { require_once __DIR__ . '/../lib/MarketRules.php'; try { return MarketRules::forCatalog((string) $p['name'], (string) ($p['description'] ?? ''), (string) ($p['kind'] ?? ''))[0]; } catch (Throwable) { return ''; } })() : '',
                    'promo' => ucfirst((string) ($p['service_type'] ?? '')) ?: 'Not set', 'status' => $p['status']];
            }
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) use ($usd) {
            require_once __DIR__ . '/../lib/Products.php'; require_once __DIR__ . '/../lib/ItemKinds.php';
            $p = Products::get($pdo, $id); if (!$p) return null;
            $biz = Scope::isBusiness();
            $tiers = array_map(fn ($r) => number_format((int) $r['qty']) . ': ' . $usd($r['price']) . ($r['cost'] ? ' (cost ' . $usd($r['cost']) . ')' : ''), $p['tiers']);
            require_once __DIR__ . '/../lib/Costing.php'; $mo = $biz ? null : Costing::madeOf($pdo, $id);   // its recipe: what it's made of (decisions/0130)
            $made = $mo ? implode("\n", array_map(fn ($l) => $l['label'] . ': ' . $usd($l['cents']), $mo['lines'])) . "\nCOGS for " . number_format($mo['run']) . ': ' . $usd($mo['cogs']) . ' (' . $usd((int) round($mo['cogs'] / max(1, $mo['run']))) . ' each)' : 'No recipe yet. Cost one of its order items (the item\'s Cost tab), then Keep as the product\'s recipe.';
            $opt = fn (array $a) => array_map(fn ($k, $v) => is_array($v) ? [(string) $k, (string) $v[0], (string) ($v[1] ?? '')] : [(string) $k, $v], array_keys($a), $a);   // [key, name, what it is (the list's ⓘ)]
            // putting it online, here in its card (Jason, 2026-11-04: "another place to finish my work for putting items online. Make
            // this seamless"): where it stands, and Publish, Publish changes or Unpublish with the category and buttons asked here
            $pub = $biz ? (function () use ($p, $id) {
                require_once __DIR__ . '/../lib/MarketBiz.php'; require_once __DIR__ . '/../lib/Market.php';
                $main = Database::connection(); $e = Scope::owner();
                try { if (!MarketBiz::ready($main)) return null; $st = MarketBiz::state($main, $e, $p); } catch (Throwable) { return null; }
                $it = $st['item']; $state = $st['state'];
                $line = ['draft' => 'Not online yet: Publish puts it on the Marketplace, your storefront and your Shop.', 'published' => 'Online and up to date',
                    'changed' => 'Online, with changes not published yet: Publish changes shows them', 'unpublished' => 'Not online (unpublished): Publish brings it back',
                    'expired' => 'Expired: Publish puts it back', 'removed' => 'Taken down by our team: ' . (string) ($it['staff_note'] ?? '')][$state] ?? (string) $st['label'];
                if ($it && $it['status'] === 'held') $line = 'Waiting for our team to look, usually within a day';
                if ($it && in_array($state, ['published', 'changed'], true)) $line .= ' (version ' . (int) $it['version'] . ', ' . date('M j', strtotime((string) $it['updated_at'])) . ').';
                $cats = array_keys(Market::categories($main)); $cat = $it ? (string) $it['category'] : '';
                $sees = implode(', ', array_map(fn ($b) => MarketBiz::label($b, $it ?: (MarketBiz::publicOf($p) + ['entity_id' => $e])), MarketBiz::buttonsFor($main, $it ?: (MarketBiz::publicOf($p) + ['entity_id' => $e]))));   // from its kind
                $acts = [];
                if ($state !== 'removed' && !($it && $it['status'] === 'held') && $state !== 'published')
                    $acts[] = ['key' => 'publish', 'primary' => true, 'label' => $state === 'changed' ? 'Publish changes' : 'Publish', 'go' => $state === 'changed' ? 'Publish changes' : 'Publish it',
                        'note' => 'It goes on the Marketplace, your storefront and your Shop' . ($sees !== '' ? ', where buyers see ' . $sees . ' (from its kind)' : '') . '. Later edits wait here until you tap Publish changes.',
                        'asks' => [['category', 'Marketplace category', 'select', array_merge([['', 'Choose one']], array_map(fn ($c) => [$c, $c], $cats)), $cat]]];
                if (in_array($state, ['published', 'changed'], true)) $acts[] = ['key' => 'unpublish', 'label' => 'Unpublish', 'go' => 'Unpublish it', 'danger' => true, 'asks' => [],
                    'note' => 'It comes off the Marketplace, your storefront and your Shop, and stays in your catalog. Publish brings it back.'];
                return ['line' => $line, 'acts' => $acts, 'live' => $it && in_array($it['status'], ['published'], true) ? '/market/item.php?id=' . (int) $it['id'] : ''];
            })() : null;
            return ['title' => $p['name'], 'subtitle' => $p['sku'] . ' · ' . (Products::kinds()[$p['kind']] ?? $p['kind']) . ($p['status'] === 'hidden' ? ' · Hidden' : ''),
                'fields' => [[$biz ? ItemKinds::name($p['kind']) : 'Kind', $biz ? (function () use ($p) {   // the kind's own details (decisions/0141 §5), as buyers see them, and what it still needs
                        $f = ItemKinds::facts($p['kind'], (array) $p['kind_data']); [$ok, $miss] = ItemKinds::ready($p['kind'], (array) $p['kind_data']);
                        return implode("\n", array_map(fn ($x) => $x[0] . ': ' . $x[1], $f)) . ($ok ? '' : ($f ? "\n" : '') . 'Before it can be sold: ' . $miss); })() : ''],
                    ['Online', $pub ? $pub['line'] : ''],
                    ['Rules check', $biz ? (function () use ($p) { require_once __DIR__ . '/../lib/MarketRules.php'; try { return MarketRules::forCatalog((string) $p['name'], (string) ($p['description'] ?? ''), (string) ($p['kind'] ?? ''))[1]; } catch (Throwable) { return ''; } })() : ''],
                    ['Description', $p['description']], [$biz ? 'Prices' : 'Run sizes', implode("\n", $tiers)], ['Made of', $biz ? '' : $made], ['Markup', $p['markup'] ? $p['markup'] . '%' : ''], ['Options', Products::optionsText($p)],
                    [$biz ? 'Sales tax' : 'Taxable', $biz ? ($p['taxable'] ? 'Charged, at your store\'s rate' : 'Not charged') : ($p['taxable'] ? 'Yes' : 'No')], [$biz ? 'Supplier (private)' : 'Vendor', $p['vendor']], [$biz ? 'Ready in' : 'Turnaround', $p['turnaround']], ['For promo codes', $biz ? '' : (ucfirst((string) ($p['service_type'] ?? '')) ?: 'Not set')]],
                'photos' => $biz ? (function () use ($id) {   // its photos, here in its card (Jason, 2026-11-04: "what about photos?"): the draft the Marketplace publishes
                    require_once __DIR__ . '/../lib/MarketBiz.php'; $main = Database::connection(); $e = Scope::owner();
                    try { $ph = MarketBiz::photos($main, $e, $id, false); } catch (Throwable) { return null; }
                    return ['items' => array_map(fn ($x) => ['id' => (int) $x['id'], 'card' => (string) $x['card']], $ph), 'max' => MarketBiz::MAX_PHOTOS, 'target' => 'catalog_photo', 'ctx' => ['b' => $e, 'product' => $id],
                        'note' => 'The first is its cover. Photos go on the Marketplace and your storefront when you publish (or Publish changes).'];
                })() : null,
                'links' => $biz ? array_merge($pub && $pub['live'] !== '' ? [['See it live', $pub['live'], 'new']] : [], [['Check what can be sold', '/market/rules.php?q=' . rawurlencode((string) $p['name']), 'new']]) : [['The full catalog editor', '/admin/products.php#p' . $id, 'page']],
                'acts' => array_values(array_merge($pub ? $pub['acts'] : [], [
                    ['key' => 'details', 'label' => 'Details', 'assist' => $biz ? (function () {   // Write it for me under Description (ItemWriter): greyed with why when it can't
                        require_once __DIR__ . '/../lib/ItemWriter.php';
                        try { [$ok, $why] = ItemWriter::can(Database::connection(), (int) (Auth::currentUserId() ?? 0), Scope::owner()); } catch (Throwable) { [$ok, $why] = [false, 'Not available right now.']; }
                        return ['field' => 'description', 'act' => 'write', 'ok' => $ok, 'why' => $why];
                    })() : null, 'asks' => [['name', 'Name', 'input', $p['name']], ['sku', 'Code', 'input', $p['sku']], ['kind', 'Kind', 'select', $opt($biz ? ItemKinds::choices() : Products::kinds()), $p['kind']],
                        ['description', 'Description', 'textarea', $p['description'], 3, 'optional'], $biz ? ['taxable', 'Sales tax', 'select', ItemKinds::taxChoices(), (string) (int) $p['taxable']] : ['taxable', 'Taxable', 'select', [['1', 'Yes'], ['0', 'No']], (string) (int) $p['taxable']],
                        ['vendor', $biz ? 'Supplier (private)' : 'Vendor', 'input', $p['vendor'], null, 'optional'], ['turnaround', $biz ? 'Ready in' : 'Turnaround', 'input', $p['turnaround'], null, 'optional'],
                        ['status', 'Shown', 'select', [['active', 'Active'], ['hidden', 'Hidden']], $p['status']]]],
                    ['key' => 'prices', 'label' => 'Prices', 'asks' => [['runs', 'One per line: how many, your cost, the price (leave the price off for cost + markup). "1 8.50 15.00"', 'textarea', Products::runsText($p), 5],
                        ['markup', 'Markup % (for prices left off)', 'input', (string) $p['markup'], null, 'optional']]],
                    ['key' => 'options', 'label' => 'Options', 'asks' => [['options', 'One per line: "Size: Small, Large +2.00" (+2.00 a price, +10% of the price)', 'textarea', Products::optionsText($p), 4, 'optional']]],
                ] + ($biz ? [3 => ['key' => 'kinddetails', 'label' => 'Kind details', 'asks' => (function () use ($p, $opt) {   // the kind's own fields (ItemKinds::FIELDS)
                    $d = (array) $p['kind_data']; $out = [];
                    foreach (ItemKinds::FIELDS[$p['kind']] ?? [] as $f => $def) {
                        [$label, $type, $help] = $def; $v = $d[$f] ?? '';
                        if ($type === 'money' && $v !== '') $v = number_format((int) $v / 100, 2, '.', '');
                        $lab = $label . ($help !== '' ? ' (' . $help . ')' : '');
                        if ($type === 'select') $out[] = [$f, $lab, 'select', $opt($def[4]), (string) ($v !== '' ? $v : $def[3])];
                        elseif ($type === 'yesno') $out[] = [$f, $lab, 'select', [['yes', 'Yes'], ['no', 'No']], (string) ($v !== '' ? $v : $def[3])];
                        elseif ($type === 'textarea' || $type === 'lines') $out[] = [$f, $lab, 'textarea', (string) $v, 3, 'optional'];
                        else $out[] = [$f, $lab, 'input', (string) $v, null, 'optional'];
                    }
                    return $out; })()], 4 => ['key' => 'delete', 'label' => 'Delete', 'danger' => true, 'go' => 'Delete it for good',
                        'asks' => [], 'note' => 'It comes off the Marketplace and your storefront, and out of your catalog. Quotes, invoices and orders that have it keep their own copy. To keep it but not sell it, set Shown to Hidden in Details instead.']] : [])))];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid = 0) {
            require_once __DIR__ . '/../lib/Products.php';
            if (in_array($key, ['publish', 'unpublish'], true) && Scope::isBusiness()) {   // online from the card: the Marketplace page's own path (MarketBiz)
                require_once __DIR__ . '/../lib/MarketBiz.php'; require_once __DIR__ . '/../lib/MarketRules.php'; require_once __DIR__ . '/../lib/Team.php';
                $main = Database::connection(); $e = Scope::owner();
                if (!Team::can($main, $uid, $e, 'publish', 'edit')) throw new InvalidArgumentException('Your role can\'t publish. The owner can change your role.');
                if ($key === 'unpublish') { MarketBiz::unpublish($main, $e, $id); return 'Unpublished. It stays in your catalog: Publish brings it back.'; }
                $p = Products::get($pdo, $id); if (!$p) throw new InvalidArgumentException('That item isn\'t here.');
                try {
                    $it = MarketBiz::publish($main, $uid, $e, $p, (string) ($in['category'] ?? ''), '', ['rule' => (string) ($in['confirm_rule'] ?? ''), 'claim' => (string) ($in['claim'] ?? '')]);   // its buttons come from its kind
                } catch (MarketRuleAsk $q) {   // licensed or conditional (decisions/0142 §4): asked here, then sent again with the answer
                    $r = $q->rule; $base = ['category' => (string) ($in['category'] ?? ''), 'confirm_rule' => $r['key']];
                    $cond = $r['name'] . ' can be sold here when: ' . $r['why'] . ' Confirm that\'s true for this item.' . ($r['tier'] === 'restricted' ? ' Card processors restrict it, so card checkout is off for it: buyers pay you directly or ask for a quote.' : ' Buyers see these conditions on the item.');
                    if ($r['word'] !== '' && empty($in['is_it'])) return ['ask' => ['text' => $q->getMessage() . ' Is the item itself ' . mb_strtolower($r['name']) . '? (A glass, a sign or a book about it isn\'t.)',
                        'yes' => 'Yes, it is', 'no' => 'No, it isn\'t', 'yes_input' => $base + ['is_it' => 1], 'no_input' => $base + ['claim' => 'not']]];
                    return ['ask' => ['text' => $cond, 'yes' => 'I confirm', 'no' => 'Not now', 'yes_input' => $base + ['claim' => 'is'], 'no_input' => null]];
                }
                if ($it['status'] === 'held') return ((string) ($it['rule_flag'] ?? '') === 'review:recall' ? 'Sent to our team first: its name matches a product on the CPSC\'s recall list, so we check it isn\'t the recalled one, usually within a day.' : 'Sent to our team first: a word in it is one we look at before it shows, usually within a day.');
                $nt = MarketRules::notice($it);
                return 'Published (version ' . (int) $it['version'] . '): it\'s on the Marketplace, your storefront and your Shop.' . (MarketRules::flag($it)[0] === 'restricted' ? ' Card checkout is off for it: buyers pay you directly or ask for a quote.' : ($nt !== '' ? ' Buyers see: ' . $nt : ''));
            }
            if ($key === 'write' && Scope::isBusiness()) {   // Write it for me: a draft for the Description box, saved only when they save (ItemWriter)
                require_once __DIR__ . '/../lib/ItemWriter.php'; require_once __DIR__ . '/../lib/MarketBiz.php';
                $main = Database::connection(); $e = Scope::owner(); $p = Products::get($pdo, $id) ?: [];
                $w = ItemWriter::write($main, $uid, $e, (string) (MarketBiz::entity($main, $e)['name'] ?? ''), '',
                    ['name' => (string) ($in['name'] ?? ($p['name'] ?? '')), 'kind' => (string) ($in['kind'] ?? ($p['kind'] ?? '')), 'kind_data' => $p['kind_data'] ?? null, 'description' => (string) ($in['description'] ?? '')]);
                return ['draft' => $w['text'], 'message' => 'Written: read it over and change anything, then Save. ' . $w['note']];
            }
            if ($key === 'photos' && Scope::isBusiness()) {   // its photos kept, in order (the card's ×): the draft's
                require_once __DIR__ . '/../lib/MarketBiz.php';
                MarketBiz::setDraftPhotos(Database::connection(), Scope::owner(), $id, array_map('intval', (array) ($in['ids'] ?? [])));
                return 'Taken off. Publish changes to update the live item.';
            }
            if ($key === 'delete' && Scope::isBusiness()) {   // gone for good: off the Marketplace too; past quotes and orders keep their copy
                require_once __DIR__ . '/../lib/MarketBiz.php';
                $p = Products::get($pdo, $id); if (!$p) throw new InvalidArgumentException('That product isn\'t here.');
                MarketBiz::forget(Database::connection(), Scope::owner(), $id, $p);
                Products::delete($pdo, $id);
                return ['gone' => true, 'message' => $p['name'] . ' is deleted.'];
            }
            if ($key === 'kinddetails' && Scope::isBusiness()) {   // its kind's own fields (decisions/0141 §5)
                require_once __DIR__ . '/../lib/ItemKinds.php';
                $p = Products::get($pdo, $id); if (!$p) throw new InvalidArgumentException('That product isn\'t here.');
                $p = Products::setKind($pdo, $id, (string) $p['kind'], $in);
                [$ok, $miss] = ItemKinds::ready($p['kind'], (array) $p['kind_data']);
                return 'Saved.' . ($ok ? '' : ' ' . $miss);
            }
            $allowed = ['details' => ['name', 'sku', 'kind', 'description', 'taxable', 'vendor', 'turnaround', 'status'], 'prices' => ['runs', 'markup'], 'options' => ['options']][$key] ?? null;
            if (!$allowed) throw new InvalidArgumentException('Unknown action.');
            $taxNote = '';
            if ($key === 'details' && Scope::isBusiness()) {   // its name says what it is: a kind that fits it (decisions/0144)
                require_once __DIR__ . '/../lib/ItemKinds.php';
                $p = Products::get($pdo, $id);
                if ($p && isset($in['kind']) && ItemKinds::key((string) $in['kind']) !== ItemKinds::key((string) $p['kind']) && (string) ($in['taxable'] ?? (int) $p['taxable']) === (string) (int) $p['taxable']) {
                    $in['taxable'] = (string) ItemKinds::taxed((string) $in['kind']);   // a new kind, tax left alone: the new kind's usual
                    if ((int) $in['taxable'] !== (int) $p['taxable']) $taxNote = ' Sales tax is now ' . ((int) $in['taxable'] ? 'charged' : 'not charged') . ', as usual for ' . ItemKinds::name((string) $in['kind']) . '; change it in Details if yours differs.';
                }
                [$fok, $fwhy] = ItemKinds::fits((string) ($in['name'] ?? ($p['name'] ?? '')), (string) ($in['kind'] ?? ($p['kind'] ?? '')));
                if (!$fok) throw new InvalidArgumentException($fwhy);
            }
            Products::update($pdo, $id, array_intersect_key($in, array_flip($allowed)));
            return 'Saved.' . $taxNote;
        },
        'create' => ['label' => 'Add an item', 'rule' => ['min' => 'staff', 'depts' => ['fin', 'svc']],
            'fields' => function (PDO $pdo) { require_once __DIR__ . '/../lib/Products.php'; if (Scope::isBusiness()) require_once __DIR__ . '/../lib/ItemKinds.php';
                return [['name', 'Name', 'input'], ['kind', 'Kind', 'select', Scope::isBusiness() ? ItemKinds::choices() : Products::kinds()], ['price', 'Price ($)', 'input'], ['qty', 'For how many (blank: 1)', 'input', null, 'optional']]; },
            'do' => function (PDO $pdo, array $in) {
                require_once __DIR__ . '/../lib/Products.php';
                if (Scope::isBusiness()) { require_once __DIR__ . '/../lib/ItemKinds.php'; [$fok, $fwhy] = ItemKinds::fits((string) ($in['name'] ?? ''), (string) ($in['kind'] ?? '')); if (!$fok) throw new InvalidArgumentException($fwhy); }   // its name says what it is (decisions/0144)
                $id = Products::save($pdo, 0, ['name' => $in['name'] ?? '', 'kind' => $in['kind'] ?? '', 'qty' => [trim((string) ($in['qty'] ?? '')) ?: '1'], 'cost' => [''], 'price' => [$in['price'] ?? ''], 'status' => 'active', 'taxable' => Scope::isBusiness() ? ItemKinds::taxed((string) ($in['kind'] ?? '')) : 1]);   // its kind's usual sales tax (decisions/0144)
                return ['id' => $id, 'message' => 'Added. Its details, prices and options are in the card' . (Scope::isBusiness() ? ', and what this kind needs under Kind details.' : '.')];
            }],
    ],

    'backoffice/vendors' => [
        'title' => 'Vendor orders', 'view' => ['min' => 'staff', 'depts' => ['svc', 'fin']], 'open' => '/admin/vendors.php?q={ref}',
        'columns' => [['title', 'Vendor order', 'name'], ['vendor', 'Vendor', 'text'], ['jobs', 'Jobs', 'text'], ['for', 'For', 'text'], ['total', 'Cost', 'money'], ['when', 'Bought', 'date']],
        'filters' => [['open', 'Show', ['' => 'All', '1' => 'Open jobs only']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/VendorOrders.php';
            $rows = [];
            foreach (VendorOrders::listing($pdo, '', trim((string) ($q['q'] ?? '')), !empty($q['open']), 150) as $id => $v) {
                $o = $v['order']; $jobs = $v['jobs'] ?? [];
                $rows[] = ['id' => (int) $id, 'ref' => $o['vendor_order_no'], 'title' => $o['vendor_order_no'] ?: 'Vendor order ' . $id, 'vendor' => $o['vendor'],
                    'jobs' => count($jobs) . ' · ' . implode(', ', array_unique(array_filter(array_column($jobs, 'job_status')))), 'for' => implode(', ', array_unique(array_filter(array_map(fn ($j) => $j['client_company'] ?: $j['bill_name'], $jobs)))),
                    'total' => (int) $o['total_cents'], 'when' => $o['purchased_on']];
            }
            return [$rows, count($rows), []];
        },
    ],

    'backoffice/taxes' => [
        'title' => 'Sales tax rates', 'view' => ['min' => 'staff', 'depts' => ['fin']], 'edit' => ['min' => 'manager', 'depts' => ['fin']], 'open' => 'detail',
        'columns' => [['title', 'Rate', 'name'], ['where', 'Where', 'text'], ['rate', 'Percent', 'text'], ['ship', 'Shipping taxed', 'text'], ['status', 'Status', 'pill']],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/Commerce.php';
            $t = trim((string) ($q['q'] ?? '')); $rows = [];
            foreach (Commerce::rates($pdo) as $r) {
                if ($t !== '' && stripos($r['name'] . ' ' . $r['state'] . ' ' . $r['area'], $t) === false) continue;
                $rows[] = ['id' => (int) $r['id'], 'title' => $r['name'], 'where' => $r['state'] . ($r['area_kind'] === 'state' ? ' (whole state)' : ': ' . $r['area']), 'rate' => Commerce::pct((int) $r['rate_milli']),
                    'ship' => $r['tax_shipping'] ? 'Yes' : 'No', 'status' => $r['active'] ? 'active' : 'off'];
            }
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) {
            require_once __DIR__ . '/../lib/Commerce.php';
            $r = Commerce::rate($pdo, $id); if (!$r) return null;
            $pct = rtrim(Commerce::pct((int) $r['rate_milli']), '%');
            $areas = array_map(fn ($k, $v) => [$k, $v], array_keys(Commerce::AREAS), Commerce::AREAS);
            return ['title' => $r['name'], 'subtitle' => $pct . '% · ' . $r['state'] . ($r['area_kind'] === 'state' ? '' : ': ' . $r['area']),
                'fields' => [['State', $r['state']], ['Area', Commerce::AREAS[$r['area_kind']] . ($r['area'] ? ': ' . $r['area'] : '')], ['Rate', $pct . '%'], ['Shipping taxed', $r['tax_shipping'] ? 'Yes' : 'No'], ['Status', $r['active'] ? 'In use' : 'Off']],
                'links' => [],
                'acts' => [['key' => 'edit', 'label' => 'Change it', 'asks' => [['name', 'Name', 'input', $r['name']], ['state', 'State (two letters)', 'input', $r['state']], ['area_kind', 'Area', 'select', $areas, $r['area_kind']],
                    ['area', 'City or ZIP codes (for those areas)', 'input', $r['area'], null, 'optional'], ['rate', 'Percent', 'input', $pct], ['tax_shipping', 'Tax shipping too', 'select', [['0', 'No'], ['1', 'Yes']], (string) $r['tax_shipping']],
                    ['active', 'Status', 'select', [['1', 'In use'], ['0', 'Off']], (string) $r['active']]]]]];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in) {
            require_once __DIR__ . '/../lib/Commerce.php';
            if ($key !== 'edit') throw new InvalidArgumentException('Unknown action.');
            Commerce::saveRate($pdo, $id, $in); return 'Saved.';
        },
        'create' => ['label' => 'Add a rate', 'rule' => ['min' => 'manager', 'depts' => ['fin']], 'fields' => [['state', 'State (two letters)', 'input'], ['area_kind', 'Area', 'select', ['state' => 'Whole state', 'city' => 'A city', 'zip' => 'ZIP codes']], ['area', 'City or ZIP codes (for those areas)', 'input', null, 'optional'], ['rate', 'Percent', 'input'], ['name', 'Name (optional)', 'input', null, 'optional']],
            'do' => function (PDO $pdo, array $in) { require_once __DIR__ . '/../lib/Commerce.php'; return ['id' => Commerce::saveRate($pdo, 0, $in), 'message' => 'Added.']; }],
    ],

    'backoffice/shipping' => [
        'title' => 'Shipping and pickup', 'view' => ['min' => 'staff', 'depts' => ['fin', 'svc']], 'edit' => ['min' => 'manager', 'depts' => ['fin', 'svc']], 'open' => 'detail',
        'columns' => [['title', 'Option', 'name'], ['kind', 'Kind', 'text'], ['cost', 'Cost', 'text'], ['where', 'Where', 'text'], ['status', 'Status', 'pill']],
        'list' => function (PDO $pdo) {
            require_once __DIR__ . '/../lib/Commerce.php';
            $rows = array_map(fn ($r) => ['id' => (int) $r['id'], 'title' => $r['name'], 'kind' => Commerce::SHIP_KINDS[$r['kind']] ?? $r['kind'], 'cost' => Commerce::shipText($r),
                'where' => $r['states'] ?: ($r['kind'] === 'pickup' ? 'At our place' : 'Everywhere'), 'status' => $r['active'] ? 'active' : 'off'], Commerce::shipRules($pdo));
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) {
            require_once __DIR__ . '/../lib/Commerce.php';
            $r = Commerce::shipRule($pdo, $id); if (!$r) return null;
            $m = fn ($c) => number_format($c / 100, 2, '.', '');
            $kinds = array_map(fn ($k, $v) => [$k, $v], array_keys(Commerce::SHIP_KINDS), Commerce::SHIP_KINDS);
            return ['title' => $r['name'], 'subtitle' => (Commerce::SHIP_KINDS[$r['kind']] ?? '') . ' · ' . Commerce::shipText($r),
                'fields' => [['Kind', Commerce::SHIP_KINDS[$r['kind']] ?? ''], ['Cost', Commerce::shipText($r)], ['States', $r['states'] ?: 'Everywhere'], ['Note for customers', $r['note']], ['Status', $r['active'] ? 'Offered' : 'Off']],
                'links' => [],
                'acts' => [['key' => 'edit', 'label' => 'Change it', 'asks' => [['name', 'Name', 'input', $r['name']], ['kind', 'Kind', 'select', $kinds, $r['kind']], ['price', 'Price ($)', 'input', $m($r['price_cents']), null, 'optional'],
                    ['per_lb', 'Per pound ($, by weight)', 'input', $m($r['per_lb_cents']), null, 'optional'], ['min_order', 'Free from an order of ($)', 'input', $m($r['min_order_cents']), null, 'optional'],
                    ['states', 'Only these states (blank for all)', 'input', $r['states'], null, 'optional'], ['zips', 'ZIP codes (local delivery)', 'input', $r['zips'], null, 'optional'],
                    ['origin_zip', 'Deliver from ZIP (distance)', 'input', $r['origin_zip'], null, 'optional'], ['miles', 'Within miles (distance)', 'input', (string) $r['miles'], null, 'optional'],
                    ['note', 'Note for customers', 'input', $r['note'], null, 'optional'], ['active', 'Status', 'select', [['1', 'Offered'], ['0', 'Off']], (string) $r['active']]]]]];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in) {
            require_once __DIR__ . '/../lib/Commerce.php';
            if ($key !== 'edit') throw new InvalidArgumentException('Unknown action.');
            Commerce::saveShipRule($pdo, $id, $in); return 'Saved.';
        },
        'create' => ['label' => 'Add an option', 'rule' => ['min' => 'manager', 'depts' => ['fin', 'svc']], 'fields' => [['kind', 'Kind', 'select', ['flat' => 'Flat rate', 'weight' => 'By weight', 'free_over' => 'Free over an amount', 'pickup' => 'Local pickup', 'local_zip' => 'Local delivery (ZIP codes)', 'local_miles' => 'Local delivery (distance)']], ['name', 'Name (optional)', 'input', null, 'optional'], ['price', 'Price ($)', 'input', null, 'optional'],
            ['min_order', 'Free from an order of ($)', 'input', null, 'optional'], ['zips', 'ZIP codes (local delivery)', 'input', null, 'optional'], ['origin_zip', 'Deliver from ZIP (distance)', 'input', null, 'optional'], ['miles', 'Within miles (distance)', 'input', null, 'optional']],
            'do' => function (PDO $pdo, array $in) { require_once __DIR__ . '/../lib/Commerce.php'; return ['id' => Commerce::saveShipRule($pdo, 0, $in), 'message' => 'Added.']; }],
    ],

    'backoffice/pricelists' => [
        'title' => 'Price lists', 'view' => ['min' => 'staff', 'depts' => ['fin', 'sal', 'svc']], 'edit' => ['min' => 'manager', 'depts' => ['fin', 'sal']], 'open' => 'detail',
        'columns' => [['title', 'Price list', 'name'], ['for', 'For', 'text'], ['off', 'Off the catalog', 'text'], ['rules', 'Breaks and set prices', 'count'], ['status', 'Status', 'pill']],
        'list' => function (PDO $pdo) {
            require_once __DIR__ . '/../lib/PriceLists.php';
            $rows = array_map(fn ($l) => ['id' => (int) $l['id'], 'title' => $l['name'], 'for' => $l['tag'] ? 'Customers tagged ' . $l['tag'] : 'Everyone', 'off' => (float) $l['percent_off'] ? rtrim(rtrim((string) $l['percent_off'], '0'), '.') . '%' : '',
                'rules' => (int) $l['rules'], 'status' => $l['active'] ? 'active' : 'off'], PriceLists::all($pdo));
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) use ($usd) {
            require_once __DIR__ . '/../lib/PriceLists.php'; require_once __DIR__ . '/../lib/Products.php';
            $l = PriceLists::get($pdo, $id); if (!$l) return null;
            $prods = []; foreach (Products::all($pdo) as $p) $prods[(int) $p['id']] = $p['name'];
            $tags = [['0', 'Everyone']]; foreach (Customers::tags($pdo) as $k => $v) $tags[] = [(string) $k, 'Customers tagged ' . $v];
            $rule = fn ($r) => ($r['product_id'] ? ($prods[(int) $r['product_id']] ?? 'Product #' . $r['product_id']) : 'Everything') . ', from ' . number_format((int) $r['min_qty']) . ': '
                . ($r['unit_price'] !== null ? PriceLists::money((float) $r['unit_price']) . ' each' : rtrim(rtrim((string) $r['percent_off'], '0'), '.') . '% off');
            $pp = [['0', 'Everything']]; foreach ($prods as $k => $v) $pp[] = [(string) $k, $v];
            $acts = [['key' => 'edit', 'label' => 'Change it', 'asks' => [['name', 'Name', 'input', $l['name']], ['tag_id', 'For', 'select', $tags, (string) (int) $l['tag_id']], ['percent_off', 'Percent off the catalog', 'input', (string) (float) $l['percent_off'], null, 'optional'],
                    ['note', 'Note', 'input', $l['note'], null, 'optional'], ['active', 'Status', 'select', [['1', 'In use'], ['0', 'Off']], (string) $l['active']]]],
                ['key' => 'rule', 'label' => 'Add a break or set price', 'go' => 'Add', 'asks' => [['product_id', 'Product', 'select', $pp, '0'], ['min_qty', 'From a quantity of', 'input', '1'], ['percent_off', 'Percent off', 'input', '', null, 'optional'], ['unit', 'Or a set unit price ($, one product)', 'input', '', null, 'optional']]]];
            if ($l['rules_list']) $acts[] = ['key' => 'unrule', 'label' => 'Remove one', 'go' => 'Remove', 'danger' => true, 'asks' => [['rule', 'Which', 'select', array_map(fn ($r) => [(string) $r['id'], $rule($r)], $l['rules_list']), '']]];
            return ['title' => $l['name'], 'subtitle' => ($l['tag'] ? 'For customers tagged ' . $l['tag'] : 'For everyone') . ((float) $l['percent_off'] ? ' · ' . rtrim(rtrim((string) $l['percent_off'], '0'), '.') . '% off' : ''),
                'fields' => [['For', $l['tag'] ? 'Customers tagged ' . $l['tag'] . ' (tag them in Customers)' : 'Everyone'], ['Off the catalog', (float) $l['percent_off'] ? rtrim(rtrim((string) $l['percent_off'], '0'), '.') . '%' : 'Nothing (breaks only)'],
                    ['Breaks and set prices', implode("\n", array_map($rule, $l['rules_list']))], ['Note', $l['note']], ['Status', $l['active'] ? 'In use' : 'Off']],
                'links' => [['Customers', '/admin/growth/customers', 'page']], 'acts' => $acts];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in) {
            require_once __DIR__ . '/../lib/PriceLists.php';
            if ($key === 'edit') { PriceLists::save($pdo, $id, $in); return 'Saved.'; }
            if ($key === 'rule') { PriceLists::addRule($pdo, $id, $in); return 'Added.'; }
            if ($key === 'unrule') { PriceLists::removeRule($pdo, $id, (int) ($in['rule'] ?? 0)); return 'Removed.'; }
            throw new InvalidArgumentException('Unknown action.');
        },
        'create' => ['label' => 'New price list', 'rule' => ['min' => 'manager', 'depts' => ['fin', 'sal']],
            'fields' => function (PDO $pdo) { require_once __DIR__ . '/../lib/PriceLists.php'; PriceLists::ensure($pdo); $t = ['0' => 'Everyone']; foreach (Customers::tags($pdo) as $k => $v) $t[(string) $k] = 'Customers tagged ' . $v;
                return [['name', 'Name', 'input'], ['tag_id', 'For', 'select', $t], ['percent_off', 'Percent off the catalog', 'input', null, 'optional']]; },
            'do' => function (PDO $pdo, array $in) { require_once __DIR__ . '/../lib/PriceLists.php'; return ['id' => PriceLists::save($pdo, 0, $in), 'message' => 'Made. Add breaks or set prices from it.']; }],
    ],

    // ---- Sales & Growth ---------------------------------------------------------------------------------------------------

    'growth/prospects' => [
        'title' => 'Prospects', 'view' => ['min' => 'staff', 'depts' => ['sal', 'svc']], 'workflow' => 'lead', 'open' => 'lead',
        'columns' => [['title', 'Business', 'name'], ['where', 'Where', 'text'], ['stage', 'Stage', 'stage'], ['score', 'Score', 'count'], ['next', 'Next follow-up', 'date'], ['owner', 'Rep', 'text']],
        'filters' => [['owner', 'Whose', ['' => 'Everyone\'s', 'me' => 'Mine', 'none' => 'Shared queue']], ['added', 'Added', ['' => 'Any time', '7' => 'This week', '30' => 'This month']], ['web', 'Web presence', ['' => 'Any', 'not_own' => 'No website of its own', 'none' => 'No website at all']]],
        'list' => function (PDO $pdo, array $q, int $uid) {
            require_once __DIR__ . '/../lib/Sales.php';
            $f = ['q' => (string) ($q['q'] ?? ''), 'claimed' => 'no'];
            if (($q['stage'] ?? '') !== '') $f['stage'] = $q['stage'];
            if (($q['added'] ?? '') !== '') $f['added'] = (int) $q['added'];
            if (($q['web'] ?? '') !== '') $f['web'] = $q['web'];
            if (($q['owner'] ?? '') === 'me') $f['mine'] = $uid; elseif (($q['owner'] ?? '') === 'none') $f['owner'] = 'none';
            $page = max(0, (int) ($q['page'] ?? 0)); $per = (int) ($q['per'] ?? 50);
            [$r, $total] = Sales::list($pdo, $f, 'best', $per, $page * $per);
            $rows = array_map(fn ($p) => ['id' => $p['id'], 'title' => $p['name'], 'where' => trim($p['category'] . ($p['city'] ? ' · ' . $p['city'] : ''), ' ·'), 'stage' => $p['lead_stage'],
                'score' => $p['score'], 'next' => $p['next_at'], 'owner' => $p['owner']], $r);
            $F = Sales::funnel($pdo);
            $counts = array_intersect_key($F, array_flip(['new', 'contacted', 'met', 'interested', 'meeting', 'invited', 'quote', 'won', 'not_now', 'no', 'lost', 'closed']));
            return [$rows, $total, $counts];
        },
    ],

    'growth/customers' => [
        'title' => 'Customers', 'view' => ['min' => 'staff', 'depts' => ['sal', 'svc', 'fin']], 'open' => 'detail',
        'edit' => ['min' => 'staff', 'depts' => ['sal', 'svc', 'fin']],
        'columns' => [['title', 'Customer', 'name'], ['who', 'Company or email', 'text'], ['seg', 'Segment', 'pill'], ['buys', 'Orders · plans', 'text'], ['spent', 'Spent', 'money'], ['last', 'Last bought', 'date']],
        'filters' => function (PDO $pdo) {
            require_once __DIR__ . '/../lib/Customers.php';
            $n = Customers::tagCounts($pdo);
            $tags = ['' => 'Any tag'];
            foreach (Customers::tags($pdo) as $id => $name) $tags[(string) $id] = $name . ' (' . ($n[$id] ?? 0) . ')';
            return [['seg', 'Segment', ['' => 'Everyone'] + Customers::SEGMENTS], ['tag', 'Tag', $tags]];
        },
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/Customers.php';
            Customers::sync($pdo);
            $seg = (string) (($q['seg'] ?? '') ?: ($q['stage'] ?? ''));
            $rows = [];
            foreach (Customers::all($pdo, 0, trim((string) ($q['q'] ?? '')), (int) ($q['tag'] ?? 0)) as $c) {
                $s = Customers::segments($c);
                if ($seg !== '' && !in_array($seg, $s, true)) continue;
                $lead = array_values(array_intersect(['vip', 'plan', 'new', 'returning', 'inactive', 'lead'], $s))[0] ?? '';
                $rows[] = ['id' => (int) $c['id'], 'title' => $c['name'] ?: $c['company'] ?: $c['email'], 'who' => $c['company'] ?: $c['email'], 'seg' => Customers::SHORT[$lead] ?? '',
                    'buys' => (int) $c['orders_n'] . ' · ' . (int) $c['plans_n'] . ($c['tags'] ? ' · ' . $c['tags'] : ''), 'spent' => (int) $c['spent_cents'], 'last' => $c['last_at'] ?: '', 'stage' => $seg];
            }
            return [$rows, count($rows), []];
        },
        'metrics' => function (PDO $pdo) {
            require_once __DIR__ . '/../lib/Customers.php';
            Customers::sync($pdo);
            $n = array_fill_keys(array_keys(Customers::SEGMENTS), 0); $all = 0; $ltv = 0;
            foreach (Customers::all($pdo) as $c) { $all++; $ltv += (int) $c['spent_cents']; foreach (Customers::segments($c) as $s) $n[$s]++; }
            return [['Customers', $all, ''], ['New this month', $n['new'], 'new'], ['Returning', $n['returning'], 'returning'], ['VIP', $n['vip'], 'vip'], ['Inactive', $n['inactive'], 'inactive'],
                ['Lifetime value', '$' . number_format($ltv / 100, 0), '']];
        },
        'get' => function (PDO $pdo, int $id) use ($usd, $day) {
            require_once __DIR__ . '/../lib/Customers.php';
            $c = Customers::get($pdo, $id); if (!$c) return null;
            $tags = Customers::tags($pdo);
            $segs = array_map(fn ($s) => Customers::SEGMENTS[$s], Customers::segments($c));
            $listing = '';
            $biz = Scope::isBusiness();   // in a business's portal: no member cards or our records, only theirs
            if ($c['entity_id'] && !$biz) { $st = $pdo->prepare('SELECT name FROM entities WHERE id = ?'); $st->execute([(int) $c['entity_id']]); $listing = (string) $st->fetchColumn(); }
            $links = [];
            if ($c['user_id'] && !$biz) $links[] = ['Their member card', '/admin/people.php?sec=members#members:' . (int) $c['user_id'], 'page'];
            if ($c['entity_id'] && !$biz) $links[] = ['Their listing', '/listing/view.php?id=' . (int) $c['entity_id'], 'new'];
            $links[] = ['Email them', 'mailto:' . $c['email'], 'new'];
            $act = Customers::activity($pdo, $c);
            foreach ($act as $a) if ($a[4] !== '' && count($links) < 9) $links[] = [strtok($a[2], '·') ?: $a[2], $a[4], 'page'];
            return ['title' => $c['name'] ?: $c['company'] ?: $c['email'], 'subtitle' => trim(($c['company'] && $c['name'] ? $c['company'] . ' · ' : '') . $c['email']),
                'fields' => [['Email', $c['email']], ['Phone', $c['phone']], ['Company', $c['company']], ['Segments', implode(', ', $segs)],
                    ['Tags', implode(', ', array_map(fn ($t) => $tags[$t] ?? '', $c['tag_ids']))], ['Orders', (string) (int) $c['orders_n']], ['Plans', (int) $c['plans_n'] ? (int) $c['plans_n'] . ((int) $c['plans_active'] ? ' (' . (int) $c['plans_active'] . ' active)' : '') : ''],
                    ['Lifetime value', $usd($c['spent_cents']) . ((int) $c['refunded_cents'] ? ' after ' . $usd($c['refunded_cents']) . ' refunded' : '')], ['Their listing', $listing],
                    ['First bought', $day($c['first_at'])], ['Last bought', $day($c['last_at'])], ['QuickBooks customer', $c['qb_customer_id']], ['Added', $c['manual'] ? 'By hand' : ($biz ? 'From their orders' : 'From their orders and plans')]],
                'links' => $links,
                'acts' => [
                    ['key' => 'note', 'label' => 'Add a note', 'asks' => [['body', 'Note', 'textarea', '']]],
                    ['key' => 'tags', 'label' => 'Tags', 'asks' => [['tags', 'Tags', 'checks', array_map(fn ($k, $v) => [(string) $k, $v], array_keys($tags), $tags), array_map('strval', $c['tag_ids'])], ['new_tag', 'A new tag (optional)', 'input', '', null, 'optional']]],
                    ['key' => 'edit', 'label' => 'Edit details', 'asks' => [['name', 'Name', 'input', $c['name'], null, 'optional'], ['company', 'Company', 'input', $c['company'], null, 'optional'], ['phone', 'Phone', 'input', $c['phone'], null, 'optional']]],
                ],
                'timeline' => array_map(fn ($a) => ['at' => date('M j, Y g:ia', strtotime((string) $a[0])), 'kind' => $a[1], 'text' => $a[2], 'by' => $a[3]], array_slice($act, 0, 60)),
                'timeline_title' => 'Notes and activity'];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid) {
            require_once __DIR__ . '/../lib/Customers.php';
            if ($key === 'note') { Customers::addNote($pdo, $id, (string) ($in['body'] ?? ''), $uid); return 'Note added.'; }
            if ($key === 'tags') { Customers::setTags($pdo, $id, (array) ($in['tags'] ?? []), (string) ($in['new_tag'] ?? '')); return 'Tags saved.'; }
            if ($key === 'edit') { Customers::edit($pdo, $id, $in); return 'Saved.'; }
            throw new InvalidArgumentException('Unknown action.');
        },
        'create' => ['label' => 'Add a customer', 'fields' => [['name', 'Name', 'input'], ['email', 'Email', 'input']], 'rule' => ['min' => 'staff', 'depts' => ['sal', 'svc', 'fin']],
            'do' => function (PDO $pdo, array $in) { require_once __DIR__ . '/../lib/Customers.php'; $id = Customers::create($pdo, $in); return ['id' => $id, 'message' => 'Added.']; }],
    ],

    // ---- A business's quotes, invoices and orders (business portal only: BizQuotes, decisions/0118) -------------------------

    'business/quotes' => [
        'title' => 'Quotes & invoices', 'view' => ['min' => 'owner', 'depts' => []], 'open' => 'detail',
        'columns' => [['title', 'Quote', 'name'], ['what', 'For', 'text'], ['status', 'Where it is', 'pill'], ['total', 'Total', 'text'], ['when', 'Updated', 'date']],
        'filters' => [['status', 'Show', ['' => 'Everything', 'draft' => 'Drafts', 'sent' => 'Sent, waiting', 'approved' => 'Invoices, not paid', 'paid' => 'Paid', 'declined' => 'Declined', 'void' => 'Void']]],
        'list' => function (PDO $pdo, array $q) {
            if (!Scope::isBusiness()) throw new InvalidArgumentException('Quotes & invoices are a business tool.');
            require_once __DIR__ . '/../lib/BizQuotes.php';
            $rows = array_map(fn ($r) => ['id' => (int) $r['id'], 'title' => BizQuotes::ref($r) . ' · ' . ($r['cust_name'] ?: $r['cust_email']), 'what' => (string) $r['title'],
                'status' => BizQuotes::STATUS[$r['status']] ?? $r['status'], 'total' => BizQuotes::money((int) $r['total_cents']), 'when' => $r['updated_at']],
                BizQuotes::all($pdo, (string) ($q['status'] ?? ''), trim((string) ($q['q'] ?? ''))));
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/BizQuotes.php';
            require_once __DIR__ . '/../lib/Products.php';
            $q = BizQuotes::get($pdo, $id); if (!$q) return null;
            $m = [BizQuotes::class, 'money'];
            $edit = in_array($q['status'], ['draft', 'sent'], true);
            $lines = array_map(fn ($l) => (int) $l['qty'] . ' × ' . $l['title'] . ' at ' . $m((int) $l['unit_cents']) . ' = ' . $m((int) $l['qty'] * (int) $l['unit_cents']) . ((int) $l['taxable'] ? '' : ' (no tax)'), $q['lines']);
            $acts = [];
            if ($edit) {
                $prods = array_values(array_filter(Products::all($pdo, true), fn ($p) => $p['status'] === 'active'));
                if ($prods) $acts[] = ['key' => 'add_item', 'label' => 'Add from your catalog', 'go' => 'Add it', 'asks' => [['product', 'Item', 'select', array_map(fn ($p) => [(string) $p['id'], $p['name']], $prods), (string) $prods[0]['id']],
                    ['qty', 'How many', 'input', '1'], ['price', 'Price each (blank: your catalog price)', 'input', '', null, 'optional']]];
                $acts[] = ['key' => 'add_line', 'label' => 'Add a line', 'go' => 'Add it', 'asks' => [['title', 'What it is', 'input', ''], ['qty', 'How many', 'input', '1'], ['price', 'Price each ($)', 'input', ''], ['taxable', 'Taxable', 'select', [['1', 'Yes'], ['0', 'No']], '1']]];
                if ($q['lines']) $acts[] = ['key' => 'remove_line', 'label' => 'Remove a line', 'go' => 'Remove it', 'danger' => true, 'asks' => [['line', 'Which', 'select', array_map(fn ($l) => [(string) $l['id'], (int) $l['qty'] . ' × ' . $l['title']], $q['lines']), (string) $q['lines'][0]['id']]]];
                $acts[] = ['key' => 'terms', 'label' => 'Terms & how to pay', 'asks' => [['title', 'What it\'s for (shown to the customer)', 'input', (string) $q['title'], null, 'optional'],
                    ['terms', 'When it\'s due', 'select', array_map(fn ($k, $v) => [$k, $v[0]], array_keys(BizQuotes::TERMS), BizQuotes::TERMS), $q['terms']],
                    ['deposit_pct', 'Deposit on approval (%)', 'input', (string) (int) $q['deposit_pct'], null, 'optional'],
                    ...(function () use ($pdo) { require_once __DIR__ . '/../lib/Commerce.php'; $r = array_values(array_filter(Commerce::rates($pdo), fn ($x) => (int) $x['active']));   // the business's own rates (Sales tax)
                        return $r ? [['tax_rate', 'From your tax rates', 'select', array_merge([['', 'None, or the rate below']], array_map(fn ($x) => [(string) $x['id'], $x['name'] . ' · ' . Commerce::pct((int) $x['rate_milli'])], $r)), '', 'optional']] : []; })(),
                    ['tax', 'Sales tax rate (%)', 'input', (int) $q['tax_milli'] ? rtrim(rtrim(number_format((int) $q['tax_milli'] / 1000, 3, '.', ''), '0'), '.') : '', null, 'optional'],
                    ['pay_link', 'Your pay link (Square, PayPal…), optional', 'input', (string) $q['pay_link'], null, 'optional'],
                    ['pay_note', 'How else to pay (Zelle to…, checks to…)', 'textarea', (string) $q['pay_note'], 2, 'optional'],
                    ['notes', 'Notes for the customer', 'textarea', (string) $q['notes'], 3, 'optional'], ['valid_days', 'Quote good for (days)', 'input', (string) (int) $q['valid_days'], null, 'optional']]];
            }
            require_once __DIR__ . '/../lib/BizCodes.php';
            if ($edit) $acts[] = ['key' => 'code', 'label' => $q['code'] ? 'Discount code: ' . $q['code'] : 'Add a discount code', 'go' => 'Save', 'asks' => [['code', 'Code (blank takes it off)', 'input', (string) $q['code'], null, 'optional']]];
            if (in_array($q['status'], ['draft', 'sent', 'approved'], true) && $q['lines']) $acts[] = ['key' => 'send', 'label' => $q['status'] === 'draft' ? 'Send to the customer' : 'Send again', 'go' => 'Send it', 'asks' => []];
            if (in_array($q['status'], ['sent', 'approved'], true)) $acts[] = ['key' => 'pay', 'label' => 'Record a payment', 'go' => 'Record it', 'asks' => [['amount', 'Amount ($)', 'input', number_format($q['balance'] / 100, 2, '.', '')],
                ['method', 'How', 'select', array_map(fn ($k, $v) => [$k, $v], array_keys(BizQuotes::METHODS), BizQuotes::METHODS), 'zelle'], ['on', 'Date (YYYY-MM-DD)', 'input', date('Y-m-d')], ['note', 'Note (check number…)', 'input', '', null, 'optional']]];
            $acts[] = ['key' => 'copy', 'label' => 'Make a copy', 'go' => 'Copy it', 'asks' => []];
            if (!in_array($q['status'], ['void', 'paid'], true)) $acts[] = ['key' => 'void', 'label' => 'Void it', 'go' => 'Void it', 'danger' => true, 'asks' => [['why', 'Why (for your records)', 'input', '', null, 'optional']]];
            $link = $q['token'] ? BizQuotes::link(Scope::owner(), (string) $q['token']) : '';
            require_once __DIR__ . '/../lib/BizFiles.php';
            $files = BizFiles::forQuote($pdo, $id); $pv = array_filter($files, fn ($f) => $f['kind'] === 'proof');
            $proofLine = '';
            if ($pv) { $top = max(array_map(fn ($f) => (int) $f['version'], $pv)); $tf = array_values(array_filter($pv, fn ($f) => (int) $f['version'] === $top))[0]; $proofLine = 'Version ' . $top . ': ' . (BizFiles::STATUS[$tf['status']] ?? $tf['status']); }
            return ['title' => BizQuotes::ref($q) . ' · ' . ($q['cust_name'] ?: $q['cust_email']), 'subtitle' => (BizQuotes::STATUS[$q['status']] ?? $q['status']) . ' · ' . $m($q['total']) . ($q['title'] ? ' · ' . $q['title'] : ''),
                'fields' => [['Customer', trim($q['cust_name'] . ' · ' . $q['cust_email'], ' ·')], ['Lines', implode("\n", $lines) ?: 'None yet: add from your catalog or add a line'],
                    ['Subtotal', $q['lines'] ? $m($q['sub']) : ''], ['Discount', $q['code'] ? $q['code'] . ' (' . BizCodes::offer((string) $q['code_kind'], (int) $q['code_value']) . '): −' . $m($q['off']) : ''], ['Sales tax', $q['tax'] ? $m($q['tax']) . ' (' . rtrim(rtrim(number_format((int) $q['tax_milli'] / 1000, 3, '.', ''), '0'), '.') . '%)' : ''],
                    ['Total', $q['lines'] ? $m($q['total']) : ''], ['Deposit on approval', $q['deposit'] ? $m($q['deposit']) . ' (' . (int) $q['deposit_pct'] . '%)' : ''],
                    ['Paid', $q['paid'] ? $m($q['paid']) : ''], ['Still due', in_array($q['status'], ['approved', 'sent'], true) && $q['lines'] ? $m($q['balance']) . ($q['due_at'] ? ' by ' . $day($q['due_at']) : '') : ''],
                    ['Terms', BizQuotes::TERMS[$q['terms']][0]], ['Pay link', (string) $q['pay_link']], ['How else to pay', (string) $q['pay_note']], ['Notes', (string) $q['notes']],
                    ['Approved by', $q['approved_name'] ? $q['approved_name'] . ' on ' . $day($q['approved_at']) : ''], ['Order stage', $q['stage'] ? BizQuotes::STAGES[$q['stage']] ?? $q['stage'] : ''],
                    ['Good until', $q['status'] === 'sent' && $q['expires_at'] ? $day($q['expires_at']) : ''], ['Why closed', (string) $q['closed_why']], ['Proof', $proofLine]],
                'links' => array_merge([['Proofs & files (' . count($files) . ')', tv_biz_url(Scope::owner(), 'quotes') . '?files=' . $id, 'page']], $link ? [['The customer\'s page', $link, 'new']] : []),
                'acts' => $acts,
                'timeline' => array_map(fn ($a) => ['at' => date('M j, Y g:ia', strtotime((string) $a['created_at'])), 'kind' => $a['kind'], 'text' => $a['text'], 'by' => $a['by_user'] ? Scope::person((int) $a['by_user']) : ($a['kind'] === 'approved' || $a['kind'] === 'declined' ? 'The customer' : '')], $q['log']),
                'timeline_title' => 'What happened'];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid) {
            require_once __DIR__ . '/../lib/BizQuotes.php';
            $biz = function () { $st = Database::connection()->prepare('SELECT name FROM entities WHERE id = ?'); $st->execute([Scope::owner()]); return (string) ($st->fetchColumn() ?: 'Your business'); };
            switch ($key) {
                case 'add_item': BizQuotes::addLine($pdo, $id, (int) ($in['product'] ?? 0), '', (int) ($in['qty'] ?? 1), (string) ($in['price'] ?? ''), '', $uid); return 'Added.';
                case 'add_line': BizQuotes::addLine($pdo, $id, 0, (string) ($in['title'] ?? ''), (int) ($in['qty'] ?? 1), (string) ($in['price'] ?? ''), (string) ($in['taxable'] ?? '1'), $uid); return 'Added.';
                case 'remove_line': BizQuotes::removeLine($pdo, $id, (int) ($in['line'] ?? 0), $uid); return 'Removed.';
                case 'terms': BizQuotes::setTerms($pdo, $id, $in, $uid); return 'Saved.';
                case 'send': return 'Emailed to ' . BizQuotes::send($pdo, $id, $uid, $biz()) . '.';
                case 'pay': return BizQuotes::pay($pdo, $id, (string) ($in['amount'] ?? ''), (string) ($in['method'] ?? ''), (string) ($in['on'] ?? ''), (string) ($in['note'] ?? ''), $uid);
                case 'void': BizQuotes::void($pdo, $id, (string) ($in['why'] ?? ''), $uid); return 'Voided.';
                case 'code': return BizQuotes::setCode($pdo, $id, (string) ($in['code'] ?? ''), $uid);
                case 'copy': $n = BizQuotes::copy($pdo, $id, $uid); return 'Copied as a new draft, ' . BizQuotes::ref(BizQuotes::get($pdo, $n)) . '.';
            }
            throw new InvalidArgumentException('Unknown action.');
        },
        'create' => ['label' => 'New quote', 'fields' => [['email', 'Customer\'s email', 'input'], ['name', 'Their name', 'input', null, 'optional'], ['title', 'What it\'s for', 'input', null, 'optional']],
            'do' => function (PDO $pdo, array $in, int $uid) {
                require_once __DIR__ . '/../lib/BizQuotes.php';
                $id = BizQuotes::create($pdo, (string) ($in['email'] ?? ''), (string) ($in['name'] ?? ''), (string) ($in['title'] ?? ''), $uid);
                return ['id' => $id, 'message' => 'Started. Add its lines and terms in the card, then send it.'];
            }],
    ],

    'business/codes' => [
        'title' => 'Discount codes', 'view' => ['min' => 'owner', 'depts' => []], 'open' => 'detail',
        'columns' => [['title', 'Code', 'name'], ['offer', 'Offer', 'text'], ['used', 'Used', 'text'], ['ends', 'Ends', 'date'], ['status', 'Status', 'pill']],
        'filters' => [['show', 'Show', ['' => 'All', 'on' => 'On', 'off' => 'Off']]],
        'list' => function (PDO $pdo, array $q) {
            if (!Scope::isBusiness()) throw new InvalidArgumentException('Discount codes here are a business tool; ours are in Growth → Promotions.');
            require_once __DIR__ . '/../lib/BizCodes.php';
            $rows = [];
            foreach (BizCodes::all($pdo, trim((string) ($q['q'] ?? ''))) as $c) {
                if (($q['show'] ?? '') === 'on' && !(int) $c['active'] || ($q['show'] ?? '') === 'off' && (int) $c['active']) continue;
                $rows[] = ['id' => (int) $c['id'], 'title' => $c['code'], 'offer' => BizCodes::offer($c['kind'], (int) $c['value']), 'used' => (int) $c['uses'] . ((int) $c['max_uses'] ? ' of ' . (int) $c['max_uses'] : ''),
                    'ends' => $c['ends'], 'status' => (int) $c['active'] ? 'on' : 'off'];
            }
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/BizCodes.php';
            $c = BizCodes::get($pdo, $id); if (!$c) return null;
            $val = $c['kind'] === 'amount' ? number_format((int) $c['value'] / 100, 2, '.', '') : (string) (int) $c['value'];
            $uses = BizCodes::uses($pdo, $id);
            return ['title' => $c['code'], 'subtitle' => BizCodes::offer($c['kind'], (int) $c['value']) . ((int) $c['active'] ? '' : ' · off'),
                'fields' => [['Offer', BizCodes::offer($c['kind'], (int) $c['value'])], ['For orders of', (int) $c['min_order_cents'] ? '$' . number_format((int) $c['min_order_cents'] / 100, 2) . ' or more' : ''],
                    ['Starts', $day($c['starts'])], ['Ends', $day($c['ends'])], ['Used', (int) $c['uses'] . ((int) $c['max_uses'] ? ' of ' . (int) $c['max_uses'] : ' (no limit)')],
                    ['Once per customer', (int) $c['once_each'] ? 'Yes' : 'No'], ['Note (yours)', (string) $c['note']], ['Status', (int) $c['active'] ? 'On' : 'Off']],
                'acts' => [['key' => 'edit', 'label' => 'Change it', 'asks' => [['code', 'Code', 'input', $c['code']], ['kind', 'Offer', 'select', array_map(fn ($k, $v) => [$k, $v], array_keys(BizCodes::KINDS), BizCodes::KINDS), $c['kind']],
                    ['value', 'How much off (percent, or $)', 'input', $val], ['min_order', 'For orders of at least ($, optional)', 'input', (int) $c['min_order_cents'] ? number_format((int) $c['min_order_cents'] / 100, 2, '.', '') : '', null, 'optional'],
                    ['starts', 'Starts (YYYY-MM-DD, optional)', 'input', (string) $c['starts'], null, 'optional'], ['ends', 'Ends (YYYY-MM-DD, optional)', 'input', (string) $c['ends'], null, 'optional'],
                    ['max_uses', 'How many times in all (0 for no limit)', 'input', (string) (int) $c['max_uses'], null, 'optional'], ['once_each', 'Once per customer', 'select', [['1', 'Yes'], ['0', 'No']], (string) (int) $c['once_each']],
                    ['note', 'Note for your team (optional)', 'input', (string) $c['note'], null, 'optional'], ['active', 'Status', 'select', [['1', 'On'], ['0', 'Off']], (string) (int) $c['active']]]]],
                'timeline' => array_map(fn ($u) => ['at' => date('M j, Y g:ia', strtotime((string) $u['created_at'])), 'kind' => 'used', 'text' => 'Used on quote #' . (int) $u['quote_id'] . ' by ' . $u['email'] . ': $' . number_format((int) $u['off_cents'] / 100, 2) . ' off', 'by' => ''], $uses),
                'timeline_title' => 'Where it was used'];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid) {
            require_once __DIR__ . '/../lib/BizCodes.php';
            if ($key !== 'edit') throw new InvalidArgumentException('Unknown action.');
            BizCodes::save($pdo, $id, $in, $uid); return 'Saved.';
        },
        'create' => ['label' => 'New code', 'fields' => [['code', 'Code (like SPRING15)', 'input'], ['kind', 'Offer', 'select', ['percent' => 'A percent off', 'amount' => 'An amount off']], ['value', 'How much off (percent, or $)', 'input'],
            ['ends', 'Ends (YYYY-MM-DD, optional)', 'input', null, 'optional'], ['max_uses', 'How many times in all (blank: no limit)', 'input', null, 'optional']],
            'do' => function (PDO $pdo, array $in, int $uid) { require_once __DIR__ . '/../lib/BizCodes.php'; $id = BizCodes::save($pdo, 0, $in + ['once_each' => '1', 'active' => '1'], $uid); return ['id' => $id, 'message' => 'Made. Customers type it on their quote page, or you add it to a quote.']; }],
    ],

    'business/orders' => [
        'title' => 'Orders', 'view' => ['min' => 'owner', 'depts' => []], 'open' => 'detail',
        'columns' => [['title', 'Order', 'name'], ['what', 'For', 'text'], ['stage', 'Stage', 'pill'], ['pay', 'Payment', 'text'], ['when', 'Updated', 'date']],
        'filters' => [['stage', 'Stage', ['' => 'Not done yet', 'new' => 'New', 'production' => 'In production', 'ready' => 'Ready for pickup', 'shipped' => 'Shipped', 'done' => 'Done', 'all' => 'Every order']]],
        'list' => function (PDO $pdo, array $q) {
            if (!Scope::isBusiness()) throw new InvalidArgumentException('Orders are a business tool.');
            require_once __DIR__ . '/../lib/BizQuotes.php';
            $f = (string) ($q['stage'] ?? '');
            $rows = [];
            foreach (BizQuotes::all($pdo, 'orders', trim((string) ($q['q'] ?? ''))) as $r) {
                $st = $r['stage'] ?: 'new';
                if ($f === '' ? $st === 'done' : ($f !== 'all' && $st !== $f)) continue;
                $rows[] = ['id' => (int) $r['id'], 'title' => BizQuotes::ref($r) . ' · ' . ($r['cust_name'] ?: $r['cust_email']), 'what' => (string) $r['title'], 'stage' => BizQuotes::STAGES[$st] ?? $st,
                    'pay' => $r['status'] === 'paid' ? 'Paid' : BizQuotes::money(max(0, (int) $r['total_cents'] - (int) $r['paid_cents'])) . ' due', 'when' => $r['updated_at']];
            }
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/BizQuotes.php';
            $q = BizQuotes::get($pdo, $id); if (!$q || !in_array($q['status'], ['approved', 'paid'], true)) return null;
            $m = [BizQuotes::class, 'money'];
            $st = $q['stage'] ?: 'new';
            $acts = [['key' => 'stage', 'label' => 'Move it on', 'go' => 'Move it', 'asks' => [['stage', 'Stage (Ready and Shipped email the customer)', 'select', array_map(fn ($k, $v) => [$k, $v], array_keys(BizQuotes::STAGES), BizQuotes::STAGES), $st],
                ['tracking', 'Tracking or pickup note', 'input', (string) $q['tracking'], null, 'optional']]]];
            if ($q['status'] === 'approved') $acts[] = ['key' => 'pay', 'label' => 'Record a payment', 'go' => 'Record it', 'asks' => [['amount', 'Amount ($)', 'input', number_format($q['balance'] / 100, 2, '.', '')],
                ['method', 'How', 'select', array_map(fn ($k, $v) => [$k, $v], array_keys(BizQuotes::METHODS), BizQuotes::METHODS), 'zelle'], ['on', 'Date (YYYY-MM-DD)', 'input', date('Y-m-d')], ['note', 'Note', 'input', '', null, 'optional']]];
            return ['title' => BizQuotes::ref($q) . ' · ' . ($q['cust_name'] ?: $q['cust_email']), 'subtitle' => (BizQuotes::STAGES[$st] ?? $st) . ' · ' . ($q['status'] === 'paid' ? 'Paid' : $m($q['balance']) . ' due'),
                'fields' => [['Customer', trim($q['cust_name'] . ' · ' . $q['cust_email'], ' ·')], ['What', implode("\n", array_map(fn ($l) => (int) $l['qty'] . ' × ' . $l['title'], $q['lines']))],
                    ['Total', $m($q['total'])], ['Paid', $m($q['paid'])], ['Due', $q['status'] === 'paid' ? '' : $m($q['balance']) . ($q['due_at'] ? ' by ' . $day($q['due_at']) : '')],
                    ['Stage', BizQuotes::STAGES[$st] ?? $st], ['Tracking', (string) $q['tracking']], ['Approved by', $q['approved_name'] ? $q['approved_name'] . ' on ' . $day($q['approved_at']) : ''], ['Notes', (string) $q['notes']]],
                'links' => [['Its invoice (Quotes & invoices)', tv_biz_url(Scope::owner(), 'quotes') . '#quotes:' . $id, 'page']] + ($q['token'] ? [1 => ['The customer\'s page', BizQuotes::link(Scope::owner(), (string) $q['token']), 'new']] : []),
                'acts' => $acts,
                'timeline' => array_map(fn ($a) => ['at' => date('M j, Y g:ia', strtotime((string) $a['created_at'])), 'kind' => $a['kind'], 'text' => $a['text'], 'by' => $a['by_user'] ? Scope::person((int) $a['by_user']) : ''], $q['log']),
                'timeline_title' => 'What happened'];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid) {
            require_once __DIR__ . '/../lib/BizQuotes.php';
            if ($key === 'stage') { $st = Database::connection()->prepare('SELECT name FROM entities WHERE id = ?'); $st->execute([Scope::owner()]); return BizQuotes::stage($pdo, $id, (string) ($in['stage'] ?? ''), (string) ($in['tracking'] ?? ''), $uid, (string) ($st->fetchColumn() ?: 'Your business')); }
            if ($key === 'pay') return BizQuotes::pay($pdo, $id, (string) ($in['amount'] ?? ''), (string) ($in['method'] ?? ''), (string) ($in['on'] ?? ''), (string) ($in['note'] ?? ''), $uid);
            throw new InvalidArgumentException('Unknown action.');
        },
    ],

    'growth/calls' => [
        'title' => 'Calls today', 'view' => ['min' => 'staff', 'depts' => ['sal']], 'workflow' => 'lead', 'open' => 'lead',
        'columns' => [['title', 'Business', 'name'], ['where', 'Where', 'text'], ['stage', 'Stage', 'stage'], ['next', 'Due', 'date'], ['last', 'Last time', 'text']],
        'filters' => [['what', 'Calls', ['' => 'Sales calls', 'confirm' => 'Confirm listings']], ['owner', 'Whose', ['me' => 'Mine', '' => 'Everyone\'s', 'none' => 'Shared queue']]],
        'list' => function (PDO $pdo, array $q, int $uid) {
            require_once __DIR__ . '/../lib/Sales.php';
            if (($q['what'] ?? '') === 'confirm') {   // the Call list (decisions/0133): the least sure listings with a phone, never called in 90 days
                $rows = array_map(fn ($r) => ['id' => (int) $r['id'], 'title' => $r['name'], 'where' => trim(($r['category'] ?? '') . ($r['city'] ? ' · ' . $r['city'] : ''), ' ·'),
                                              'stage' => 'new', 'next' => !empty($r['still_open']) ? 'Still open? ' . $r['still_open'] : '', 'last' => 'Confidence ' . (int) $r['score'] . ' · ' . $r['phone']], Sales::toConfirm($pdo, 100, trim((string) ($q['q'] ?? ''))));
                return [$rows, count($rows), []];
            }
            $f = ['due' => 1, 'claimed' => 'all', 'q' => (string) ($q['q'] ?? '')];
            if (($q['owner'] ?? 'me') === 'me') $f['mine'] = $uid; elseif (($q['owner'] ?? '') === 'none') $f['owner'] = 'none';
            [$due] = Sales::list($pdo, $f, 'due', 100);
            unset($f['due']); $f['claimed'] = 'no';
            [$best] = Sales::list($pdo, $f, 'best', 25);
            $seen = array_column($due, 'id');
            $rows = [];
            foreach (array_merge($due, array_filter($best, fn ($p) => !in_array($p['id'], $seen, true))) as $p)
                $rows[] = ['id' => $p['id'], 'title' => $p['name'], 'where' => trim($p['category'] . ($p['city'] ? ' · ' . $p['city'] : ''), ' ·'), 'stage' => $p['lead_stage'], 'next' => $p['next_at'] ?: '', 'last' => $p['last_outcome']];
            return [$rows, count($rows), []];
        },
    ],

    'growth/promotions' => [
        'title' => 'Promotions', 'view' => ['min' => 'staff', 'depts' => ['sal']], 'workflow' => 'promotion', 'open' => 'detail',
        'columns' => [['title', 'Code', 'name'], ['offer', 'Offer', 'text'], ['stage', 'Status', 'stage'], ['used', 'Used', 'text'], ['ends', 'Ends', 'date']],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/Promotions.php';
            $rows = []; $counts = [];
            foreach (Promotions::all($pdo) as $p) {
                if (($t = trim((string) ($q['q'] ?? ''))) !== '' && stripos($p['code'] . ' ' . $p['name'], $t) === false) continue;
                $counts[$p['status']] = ($counts[$p['status']] ?? 0) + 1;
                $rows[] = ['id' => (int) $p['id'], 'title' => $p['code'], 'offer' => Promotions::offer($p), 'stage' => $p['status'], 'used' => (int) $p['uses'] . ($p['max_uses'] !== null ? ' of ' . (int) $p['max_uses'] : ''), 'ends' => $p['ends_on']];
            }
            return [$rows, count($rows), $counts];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/Promotions.php';
            $p = Promotions::get($pdo, $id);
            $leads = Promotions::leadCodes($pdo, $id);
            return ['title' => $p['code'], 'subtitle' => Promotions::offer($p),
                'fields' => [['Name', $p['name']], ['Who can use it', $p['for_type'] === 'any' ? 'Anyone with the code' : 'Only ' . $p['for_type'] . ' #' . (int) $p['for_id']], ['Dates', $day($p['starts_on']) . ' – ' . ($p['ends_on'] ? $day($p['ends_on']) : 'no end')],
                    ['Used', (int) $p['uses'] . ($p['max_uses'] !== null ? ' of ' . (int) $p['max_uses'] : '')], ['Terms', $p['terms']], ['GO Code', $p['short_code'] ? 'trvgo.us/' . $p['short_code'] : 'Not issued'],
                    ['Lead codes', $leads ? count($leads) . ' made, ' . count(array_filter($leads, fn ($l) => $l[5])) . ' scanned, ' . count(array_filter($leads, fn ($l) => $l[6])) . ' used' : '']],
                'links' => array_values(array_filter([$p['short_code'] ? ['Its page', Promotions::pageUrl((string) $p['code']), 'new'] : null, $leads ? ['Lead codes (CSV)', '/admin/promotions.php?csv=' . $id, 'new'] : null]))];
        },
    ],
    // ---- Directory & Places (zip 2) ---------------------------------------------------------------------------------------

    'local/listings' => [
        'title' => 'Listings', 'view' => ['min' => 'staff', 'depts' => ['ops', 'con', 'sal', 'svc']], 'open' => 'detail',
        'columns' => [['title', 'Listing', 'name'], ['where', 'Where', 'text'], ['category', 'Category', 'text'], ['status', 'Status', 'pill'], ['updated', 'Updated', 'date']],
        'sortable' => ['title', 'category', 'status', 'updated'],   // it pages itself: the columns its SQL sorts by
        'filters' => [['status', 'Status', ['' => 'Every listing', 'unclaimed' => 'Unclaimed', 'claimed' => 'Claimed or verified', 'hidden' => 'Hidden from the site']],
                      ['lacks', 'Missing', ['' => 'Anything', 'phone' => 'No phone', 'address' => 'No address', 'pin' => 'No map pin']]],
        'list' => function (PDO $pdo, array $q) use ($tvSortSql) {
            $w = ["e.entity_type = 'merchant'"]; $a = [];
            $st = (string) ($q['status'] ?? '');
            if ($st === 'unclaimed') $w[] = "e.status = 'unclaimed' AND e.is_suppressed = 0";
            elseif ($st === 'claimed') $w[] = "e.status IN ('claimed-unverified','pending-verification','verified')";
            elseif ($st === 'hidden') $w[] = 'e.is_suppressed = 1';
            $l = (string) ($q['lacks'] ?? '');
            if ($l === 'pin') $w[] = 'e.latitude IS NULL';
            elseif (in_array($l, ['phone', 'address'], true)) $w[] = "NOT EXISTS (SELECT 1 FROM entity_metadata m WHERE m.entity_id = e.id AND m.meta_key = '$l' AND m.meta_value NOT IN ('\"\"', '[]', 'null', ''))";
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(e.id = ? OR e.name LIKE ?)'; array_push($a, (int) ltrim($t, '#'), "%$t%"); }
            $where = implode(' AND ', $w);
            $n = $pdo->prepare("SELECT COUNT(*) FROM entities e WHERE $where"); $n->execute($a);
            $page = max(0, (int) ($q['page'] ?? 0));
            $sql = "SELECT e.id, e.name, e.status, e.is_suppressed, e.updated_at, c.name AS category,
                           (SELECT JSON_UNQUOTE(m.meta_value) FROM entity_metadata m WHERE m.entity_id = e.id AND m.meta_key = 'city' LIMIT 1) AS city,
                           (SELECT JSON_UNQUOTE(m.meta_value) FROM entity_metadata m WHERE m.entity_id = e.id AND m.meta_key = 'state' LIMIT 1) AS state
                      FROM entities e LEFT JOIN categories c ON c.id = e.category_id WHERE $where ORDER BY " . $tvSortSql($q, ['title' => 'e.name', 'status' => 'e.status', 'updated' => 'e.updated_at', 'category' => 'c.name'], 'e.updated_at DESC') . ' LIMIT ' . (int) ($q['per'] ?? 50) . ' OFFSET ' . ($page * (int) ($q['per'] ?? 50));
            $r = $pdo->prepare($sql); $r->execute($a);
            $rows = array_map(fn ($e) => ['id' => (int) $e['id'], 'title' => $e['name'], 'where' => trim(($e['city'] ?? '') . (($e['state'] ?? '') !== '' ? ', ' . $e['state'] : ''), ', '), 'category' => (string) $e['category'],
                'status' => $e['is_suppressed'] ? 'hidden' : $e['status'], 'updated' => $e['updated_at']], $r->fetchAll(PDO::FETCH_ASSOC));
            return [$rows, (int) $n->fetchColumn(), []];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            $st = $pdo->prepare('SELECT e.*, c.name AS category, n.name AS nexus FROM entities e LEFT JOIN categories c ON c.id = e.category_id LEFT JOIN nexus n ON n.id = e.nexus_id WHERE e.id = ?'); $st->execute([$id]);
            $e = $st->fetch(PDO::FETCH_ASSOC); if (!$e) return null;
            $m = []; $mt = $pdo->prepare("SELECT meta_key, meta_value FROM entity_metadata WHERE entity_id = ? AND meta_key IN ('address','city','state','phone','website','email','hours','description')"); $mt->execute([$id]);
            foreach ($mt as $r) { $v = json_decode((string) $r['meta_value'], true); $m[$r['meta_key']] = is_scalar($v) ? (string) $v : (is_array($v) ? implode(', ', array_filter(array_map(fn ($x) => is_scalar($x) ? (string) $x : '', $v))) : (string) $r['meta_value']); }
            $d = ['title' => $e['name'], 'subtitle' => trim(($e['category'] ?? '') . ' · #' . $id, ' ·'),
                'fields' => [['Status', ($e['is_suppressed'] ? 'Hidden from the site · ' : '') . $e['status']], ['Address', trim(($m['address'] ?? '') . ', ' . ($m['city'] ?? '') . ', ' . ($m['state'] ?? '') . ' ' . $e['primary_zip'], ', ')],
                    ['Phone', $m['phone'] ?? ''], ['Website', $m['website'] ?? ''], ['Email', $m['email'] ?? ''], ['Plan', $e['plan_tier'] !== 'free' ? $e['plan_tier'] : ''], ['Part of', (string) $e['nexus']],
                    ['Map pin', $e['latitude'] !== null ? round((float) $e['latitude'], 5) . ', ' . round((float) $e['longitude'], 5) : 'None'], ['About', mb_strimwidth($m['description'] ?? '', 0, 400, '…')], ['Updated', $day($e['updated_at'])]],
                'links' => [['Edit the listing', '/admin/listing-edit.php?id=' . $id, 'page'], ['See it as visitors do', '/listing/view.php?id=' . $id, 'new']]]
                + (function () use ($pdo, $id) {   // its owner and team: our team adds members by their account email
                    require_once __DIR__ . '/../lib/Team.php';
                    $team = []; $who = [];
                    foreach (Team::members($pdo, $id) as $m) { $n = trim((string) $m['display_name']) ?: (string) $m['email']; $team[] = $n . ' (' . (Team::ROLES[$m['role']] ?? $m['role']) . ')'; if ($m['role'] !== 'owner') $who[] = [(string) $m['user_id'], $n . ' · ' . $m['email']]; }
                    $roles = Team::ROLES;
                    $hasOwner = (bool) array_filter(Team::members($pdo, $id), fn ($m) => $m['role'] === 'owner');
                    $acts = [['key' => 'team_add', 'label' => $hasOwner ? 'Add a user account' : 'Assign to a user account', 'asks' => [['email', 'User account email', 'input', ''],
                        ['role', 'As', 'select', array_map(fn ($k, $v) => [$k, $v . ($k === 'owner' && $hasOwner ? ' (replaces the owner, who stays as Manager)' : '')], array_keys($roles), $roles), $hasOwner ? 'manager' : 'owner']]]];
                    if ($who) $acts[] = ['key' => 'team_remove', 'label' => 'Remove a user account', 'danger' => true, 'asks' => [['user', 'Who', 'select', $who, $who[0][0]]]];
                    return ['acts' => $acts, 'team' => $team];
                })();
            array_unshift($d['fields'], ['Owner & team', implode(', ', $d['team']) ?: 'Not assigned to anyone\'s account yet']);   // first: whose it is
            unset($d['team']);
            require_once __DIR__ . '/../lib/SupportAccess.php';   // White Glove access (decisions/0117): set by its plan and its rep
            $wg = SupportAccess::active($pdo, $id);
            array_splice($d['fields'], 1, 0, [['White Glove', $wg ? trim((string) $wg['agent_name'] . ' (' . $wg['staff_id'] . ')') . ' has access since ' . $day($wg['granted_at'])
                : (!SupportAccess::included($pdo, $id) ? 'Not on its plan' : (SupportAccess::ownerOff($pdo, $id) ? 'Turned off by the owner' : 'On its plan; no rep yet (Sales → its lead → rep)'))]]);
            if ($wg) $d['links'][] = ['Open its business portal (White Glove)', '/business/' . $id, 'new'];
            if (SupportAccess::included($pdo, $id) && !$wg && !SupportAccess::ownerOff($pdo, $id)) $d['acts'][] = ['key' => 'wg_sync', 'label' => 'Give its rep White Glove access', 'asks' => []];
            return $d;
        },
        'edit' => ['min' => 'manager', 'depts' => ['ops', 'svc', 'sal']],
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid) {
            require_once __DIR__ . '/../lib/Team.php';
            if ($key === 'team_add') { $n = Team::adminSet($pdo, $id, (string) ($in['email'] ?? ''), (string) ($in['role'] ?? ''), $uid); return 'Assigned to ' . $n . '\'s account. We\'ve emailed them.'; }
            if ($key === 'team_remove') { Team::adminRemove($pdo, $id, (int) ($in['user'] ?? 0), $uid); return 'Removed.'; }
            if ($key === 'wg_sync') { require_once __DIR__ . '/../lib/SupportAccess.php'; $r = SupportAccess::sync($pdo, $id, $uid); return ['started' => 'Its rep has White Glove access; the owner is emailed.', 'moved' => 'Moved to its rep; the owner is emailed.'][$r] ?? 'No rep to give it to: set its lead\'s rep in Sales first.'; }
            throw new InvalidArgumentException('Unknown action.');
        },
        'create' => ['label' => 'Add a listing', 'page' => '/admin/listing-add.php'],
    ],

    'local/suggestions' => [
        'title' => 'What visitors told us', 'view' => ['min' => 'staff', 'depts' => ['ops', 'con']], 'open' => '/admin/suggestion.php?id={id}',
        'columns' => [['title', 'About', 'name'], ['kind', 'Kind', 'pill'], ['note', 'What they said', 'text'], ['when', 'When', 'date']],
        'filters' => [['status', 'Show', ['new' => 'Waiting', 'used' => 'Used', 'dismissed' => 'Dismissed', '' => 'All']]],
        'list' => function (PDO $pdo, array $q) {
            $w = []; $a = [];
            if (($s = (string) ($q['status'] ?? 'new')) !== '') { $w[] = 's.status = ?'; $a[] = $s; }
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(s.target_name LIKE ? OR s.note LIKE ? OR e.name LIKE ?)'; array_push($a, "%$t%", "%$t%", "%$t%"); }
            $st = $pdo->prepare('SELECT s.*, e.name AS listing FROM suggestions s LEFT JOIN entities e ON e.id = s.entity_id' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY s.id DESC LIMIT 300'); $st->execute($a);
            $kinds = ['missing' => 'missing place', 'fix' => 'a fix', 'closed' => 'closed', 'same' => 'duplicate', 'story' => 'a story', 'feedback' => 'feedback'];
            $rows = array_map(fn ($r) => ['id' => (int) $r['id'], 'title' => $r['listing'] ?: ($r['target_name'] ?: 'Something new'), 'kind' => $kinds[$r['kind']] ?? $r['kind'], 'note' => mb_strimwidth((string) $r['note'], 0, 90, '…'), 'when' => $r['created_at']], $st->fetchAll(PDO::FETCH_ASSOC));
            return [$rows, count($rows), []];
        },
    ],

    'local/claims' => [
        'title' => 'Claims', 'view' => ['min' => 'staff', 'depts' => ['ops', 'svc', 'sal']], 'workflow' => 'claim', 'open' => 'detail',
        'columns' => [['title', 'Listing', 'name'], ['who', 'Claimant', 'text'], ['stage', 'Stage', 'stage'], ['due', 'Decide by', 'date'], ['when', 'Started', 'date']],
        'list' => function (PDO $pdo, array $q) {
            $w = []; $a = [];
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(e.name LIKE ? OR u.email LIKE ? OR u.display_name LIKE ?)'; array_push($a, "%$t%", "%$t%", "%$t%"); }
            $st = $pdo->prepare('SELECT c.id, c.status, c.due_at, c.created_at, e.name, COALESCE(NULLIF(u.display_name, \'\'), u.email) AS who FROM listing_claims c JOIN entities e ON e.id = c.entity_id LEFT JOIN users u ON u.id = c.user_id'
                . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY c.status IN ('managed','on_hold') DESC, c.due_at IS NULL, c.due_at, c.id DESC LIMIT 400"); $st->execute($a);
            $rows = array_map(fn ($c) => ['id' => (int) $c['id'], 'title' => $c['name'], 'who' => (string) $c['who'], 'stage' => $c['status'], 'due' => in_array($c['status'], ['managed', 'on_hold'], true) ? $c['due_at'] : '', 'when' => $c['created_at']], $st->fetchAll(PDO::FETCH_ASSOC));
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/Claims.php';
            $st = $pdo->prepare('SELECT c.*, e.name AS listing, u.email, u.display_name FROM listing_claims c JOIN entities e ON e.id = c.entity_id LEFT JOIN users u ON u.id = c.user_id WHERE c.id = ?'); $st->execute([$id]);
            $c = $st->fetch(PDO::FETCH_ASSOC); if (!$c) return null;
            $log = array_map(fn ($l) => ($l['at'] ?? '') . ' · ' . ($l['channel'] ?? '') . ': ' . ($l['note'] ?? ''), json_decode((string) $c['contact_log'], true) ?: []);
            return ['title' => $c['listing'], 'subtitle' => trim((string) $c['display_name']) . ' · ' . $c['email'],
                'fields' => [['Their role', $c['role_note']], ['Listed phone', Claims::fmtPhone((string) $c['listed_phone'])], ['Their phone', Claims::fmtPhone((string) $c['claimant_phone'])], ['Text code', $c['text_code']],
                    ['How', $c['route']], ['Document', $c['doc_name'] ? $c['doc_name'] : ''], ['Plan chosen', Claims::PLANS[$c['plan']] ?? ''], ['Decide by', $c['due_at'] ? $day($c['due_at']) : ''], ['Notes', $c['admin_note']], ['Contact log', implode("\n", $log)], ['Started', $day($c['created_at'])]],
                'links' => [['The listing', '/admin/listing-edit.php?id=' . (int) $c['entity_id'], 'page']] + ($c['doc_path'] ? [1 => ['Their document', '/admin/claims.php?doc=' . $id, 'new']] : [])];
        },
    ],

    'local/nexus' => [
        'title' => 'Nexus (brands and systems)', 'view' => ['min' => 'staff', 'depts' => ['ops', 'con']], 'open' => 'detail',
        'columns' => [['title', 'Name', 'name'], ['kind', 'Kind', 'text'], ['n', 'Locations', 'count'], ['status', 'Shown', 'pill']],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/Nexus.php';
            $rows = [];
            foreach (Nexus::all($pdo, trim((string) ($q['q'] ?? '')), 400) as $n) $rows[] = ['id' => (int) $n['id'], 'title' => $n['name'], 'kind' => Nexus::KINDS[$n['kind']] ?? $n['kind'], 'n' => (int) ($n['members'] ?? $n['n'] ?? 0), 'status' => $n['status']];
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) {
            require_once __DIR__ . '/../lib/Nexus.php';
            $n = Nexus::get($pdo, $id); if (!$n) return null;
            $m = Nexus::members($pdo, $id);
            return ['title' => $n['name'], 'subtitle' => Nexus::KINDS[$n['kind']] ?? '',
                'fields' => [['Website', $n['website']], ['About', $n['about']], ['Locations', implode("\n", array_map(fn ($x) => $x['name'] . (isset($x['city']) && $x['city'] ? ' · ' . $x['city'] : ''), array_slice($m, 0, 40))) . (count($m) > 40 ? "\n…and " . (count($m) - 40) . ' more' : '')]],
                'links' => [['Manage it (add, remove, rebuild from its website)', '/admin/nexus.php?id=' . $id, 'page'], ['Its public page', '/nexus.php?slug=' . rawurlencode((string) $n['slug']), 'new']]];
        },
        'create' => ['label' => 'New Nexus', 'page' => '/admin/nexus.php#new'],
    ],

    'local/places' => [
        'title' => 'Landmarks', 'view' => ['min' => 'staff', 'depts' => ['con', 'ops']], 'workflow' => 'landmark', 'open' => 'detail',
        'columns' => [['title', 'Place', 'name'], ['kind', 'Kind', 'text'], ['where', 'Where', 'text'], ['stage', 'Stage', 'stage']],
        'filters' => [['photo', 'Photo', ['' => 'Any', '1' => 'With a photo', '0' => 'No photo yet']]],
        'list' => function (PDO $pdo, array $q) {
            $w = []; $a = [];
            if (($s = (string) ($q['stage'] ?? '')) !== '') { $w[] = 'status = ?'; $a[] = $s; }
            if (($q['photo'] ?? '') !== '') $w[] = ($q['photo'] === '1' ? "image_url <> ''" : "image_url = ''");
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(name LIKE ? OR near_town LIKE ? OR county LIKE ?)'; array_push($a, "%$t%", "%$t%", "%$t%"); }
            $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
            $n = $pdo->prepare('SELECT COUNT(*) FROM landmarks' . $where); $n->execute($a);
            $page = max(0, (int) ($q['page'] ?? 0));
            $st = $pdo->prepare('SELECT gnis_id, name, fclass, near_town, county, state, status FROM landmarks' . $where . ' ORDER BY status = \'waiting\' DESC, (image_url <> \'\') DESC, name LIMIT ' . (int) ($q['per'] ?? 50) . ' OFFSET ' . ($page * (int) ($q['per'] ?? 50))); $st->execute($a);
            $rows = array_map(fn ($r) => ['id' => (int) $r['gnis_id'], 'title' => $r['name'], 'kind' => $r['fclass'], 'where' => trim(($r['near_town'] ?: $r['county']) . ', ' . $r['state'], ', '), 'stage' => $r['status']], $st->fetchAll(PDO::FETCH_ASSOC));
            $counts = []; foreach ($pdo->query('SELECT status, COUNT(*) n FROM landmarks GROUP BY status') as $r) $counts[$r['status']] = (int) $r['n'];
            return [$rows, (int) $n->fetchColumn(), $counts];
        },
        'get' => function (PDO $pdo, int $id) {
            $st = $pdo->prepare('SELECT * FROM landmarks WHERE gnis_id = ?'); $st->execute([$id]);
            $r = $st->fetch(PDO::FETCH_ASSOC); if (!$r) return null;
            return ['title' => $r['name'], 'subtitle' => $r['fclass'] . ' · ' . trim(($r['near_town'] ?: $r['county']) . ', ' . $r['state'], ', '),
                'fields' => [['Nation', $r['nation']], ['Why it waits', $r['status'] === 'waiting' ? $r['why'] : ''], ['About', mb_strimwidth((string) ($r['extract'] ?: $r['wd_desc']), 0, 500, '…')], ['Photo', $r['image_url'] ? 'Yes · ' . $r['image_credit'] . ' · ' . $r['image_license'] : 'None yet'],
                    ['Review note', $r['review_note']], ['Evidence', $r['evidence_url']], ['From', $r['file'] === 'manual' ? 'Added by hand' : 'The federal names data']],
                'links' => in_array($r['status'], ['shown', 'approved'], true) ? [['Its page', '/place/landmark.php?id=' . $id, 'new']] : []];
        },
    ],

    'local/nations' => [
        'title' => 'Nations', 'view' => ['min' => 'staff', 'depts' => ['ops', 'con']], 'open' => 'detail',
        'columns' => [['title', 'Nation', 'name'], ['office', 'Office', 'text'], ['phone', 'Phone', 'text'], ['verified', 'Rules checked', 'date']],
        'list' => function (PDO $pdo, array $q) {
            $t = trim((string) ($q['q'] ?? ''));
            $rows = [];
            foreach ($pdo->query('SELECT id, name, office, office_phone, verified_at FROM nations ORDER BY name') as $n)
                if ($t === '' || stripos($n['name'], $t) !== false) $rows[] = ['id' => (int) $n['id'], 'title' => $n['name'], 'office' => (string) $n['office'], 'phone' => (string) $n['office_phone'], 'verified' => $n['verified_at']];
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/Nations.php';
            $n = Nations::get($pdo, $id); if (!$n) return null;
            $f = [['Website', $n['website']], ['For visitors', $n['visitor_url']], ['Office', trim($n['office'] . ' · ' . $n['office_phone'] . ' · ' . $n['office_email'], ' ·')], ['Time zone', $n['time_zone']]];
            foreach (Nations::RULES as $k => $l) $f[] = [$l, (string) ($n[$k] ?? '')];
            $f[] = ['Other rules', $n['other_rules']]; $f[] = ['Rules checked', $n['verified_at'] ? $day($n['verified_at']) : 'Not yet'];
            $held = (int) $pdo->query("SELECT COUNT(*) FROM landmarks WHERE nation = " . $pdo->quote($n['name']) . " AND status IN ('waiting','closed')")->fetchColumn();
            $f[] = ['Held places', $held ? $held . ' kept, never shown (decisions/0066)' : ''];
            return ['title' => $n['name'], 'subtitle' => 'Its rules for visitors', 'fields' => $f, 'links' => [['Edit its rules', '/admin/nations.php?id=' . $id, 'page']]];
        },
    ],

    // ---- Journeys & Exploration (zip 2) -----------------------------------------------------------------------------------

    'explore/journeys' => [
        'title' => 'Journeys', 'view' => ['min' => 'staff', 'depts' => ['con', 'ops']], 'workflow' => 'journey', 'open' => 'detail',
        'columns' => [['title', 'Journey', 'name'], ['by', 'By', 'text'], ['stage', 'Stage', 'stage'], ['views', 'Views', 'count'], ['when', 'Updated', 'date']],
        'filters' => [['voice', 'Whose', ['' => 'Everyone\'s', 'traversence' => 'Traversence pieces', 'contributor' => 'Contributors\'']]],
        'list' => function (PDO $pdo, array $q) {
            $w = []; $a = [];
            if (in_array($q['voice'] ?? '', ['traversence', 'contributor'], true)) { $w[] = 'j.voice = ?'; $a[] = $q['voice']; }
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(j.title LIKE ? OR j.place_label LIKE ?)'; array_push($a, "%$t%", "%$t%"); }
            $st = $pdo->prepare('SELECT j.id, j.title, j.status, j.views, j.updated_at, j.voice, COALESCE(NULLIF(u.display_name, \'\'), u.email) AS who FROM journeys j LEFT JOIN users u ON u.id = j.author_id'
                . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY j.updated_at DESC LIMIT 400'); $st->execute($a);
            $rows = array_map(fn ($j) => ['id' => (int) $j['id'], 'title' => $j['title'] ?: 'Untitled', 'by' => $j['voice'] === 'traversence' ? 'Traversence' : (string) $j['who'], 'stage' => $j['status'], 'views' => (int) $j['views'], 'when' => $j['updated_at']], $st->fetchAll(PDO::FETCH_ASSOC));
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            $st = $pdo->prepare('SELECT j.*, COALESCE(NULLIF(u.display_name, \'\'), u.email) AS who FROM journeys j LEFT JOIN users u ON u.id = j.author_id WHERE j.id = ?'); $st->execute([$id]);
            $j = $st->fetch(PDO::FETCH_ASSOC); if (!$j) return null;
            return ['title' => $j['title'] ?: 'Untitled', 'subtitle' => ($j['voice'] === 'traversence' ? 'Traversence' : $j['who']) . ' · ' . $j['category'],
                'fields' => [['Place', $j['place_label']], ['Story', mb_strimwidth(strip_tags((string) $j['body']), 0, 600, '…')], ['Hidden because', $j['hidden_reason']], ['Views · likes · comments', (int) $j['views'] . ' · ' . (int) $j['likes'] . ' · ' . (int) $j['comments']], ['Published', $j['published_at'] ? $day($j['published_at']) : '']],
                'links' => [['Edit or manage', '/admin/journeys.php#j' . $id, 'page']]];
        },
        'create' => ['label' => 'Write a journey', 'page' => '/user/journeys/compose.php?editorial=1'],
    ],

    'explore/experiences' => [
        'title' => 'Experiences', 'view' => ['min' => 'staff', 'depts' => ['con', 'ops']], 'workflow' => 'experience', 'open' => 'detail',
        'columns' => [['title', 'Experience', 'name'], ['type', 'Type', 'text'], ['where', 'Where', 'text'], ['stage', 'Stage', 'stage'], ['score', 'Credibility', 'count']],
        'list' => function (PDO $pdo, array $q) {
            $w = []; $a = [];
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(title LIKE ? OR place_label LIKE ?)'; array_push($a, "%$t%", "%$t%"); }
            $st = $pdo->prepare('SELECT id, title, type, place_label, status, credibility FROM experiences' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY status = 'submitted' DESC, updated_at DESC LIMIT 400"); $st->execute($a);
            require_once __DIR__ . '/../lib/Experiences.php';
            $rows = array_map(fn ($x) => ['id' => (int) $x['id'], 'title' => $x['title'], 'type' => Experiences::TYPES[$x['type']] ?? $x['type'], 'where' => (string) $x['place_label'], 'stage' => $x['status'], 'score' => $x['credibility']], $st->fetchAll(PDO::FETCH_ASSOC));
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) {
            require_once __DIR__ . '/../lib/Experiences.php';
            $x = Experiences::get($pdo, $id); if (!$x) return null;
            return ['title' => $x['title'], 'subtitle' => (Experiences::TYPES[$x['type']] ?? '') . ' · ' . $x['place_label'],
                'fields' => [['Summary', $x['summary']], ['Seasons', $x['seasons']], ['How long', Experiences::DURATIONS[$x['duration']] ?? ''], ['Effort', Experiences::EFFORTS[$x['effort']] ?? ''], ['Respect', $x['respect_note']],
                    ['Credibility', $x['credibility'] !== null ? $x['credibility'] . ' · ' . $x['credibility_why'] : ''], ['Review note', $x['review_note']], ['From', $x['source']]],
                'links' => [['See it', '/experiences/view.php?id=' . $id, 'new'], ['The experiences page', '/admin/experiences.php', 'page']]];
        },
    ],

    'explore/stories' => [
        'title' => 'Place stories', 'view' => ['min' => 'staff', 'depts' => ['con']], 'open' => '/admin/stories.php?place={ref}',
        'columns' => [['title', 'Story', 'name'], ['place', 'Place', 'text'], ['status', 'Status', 'pill'], ['origin', 'From', 'text'], ['when', 'Updated', 'date']],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/Stories.php';
            $t = trim((string) ($q['q'] ?? '')); $rows = []; $i = 0;
            foreach (Stories::all($pdo) as $r) {
                $i++;
                if ($t !== '' && stripos($r['title'] . ' ' . $r['label'], $t) === false) continue;
                $rows[] = ['id' => $i, 'ref' => $r['place_key'], 'title' => $r['title'] ?: 'Untitled', 'place' => (string) $r['label'], 'status' => $r['status'], 'origin' => $r['origin'] === 'crawler' ? 'Drafted from sources' : 'A person', 'when' => $r['updated_at']];
            }
            return [$rows, count($rows), []];
        },
        'create' => ['label' => 'Write a story', 'page' => '/admin/stories.php'],
    ],

    // Guides (decisions/0131): one per place on the six-tier funnel, from the region down to the neighborhood. Each opens
    // its editor (what we write, the names it mentions and what they link to, the picks per section).
    'explore/place_guides' => [
        'title' => 'Guides', 'view' => ['min' => 'staff', 'depts' => ['con']], 'open' => '/admin/explore/place_guides/{id}', 'count_by' => 'status',
        'columns' => [['title', 'Guide', 'name'], ['kind', 'Kind', 'text'], ['parent', 'In', 'text'], ['status', 'Status', 'pill'], ['score', 'Score', 'text'], ['names', 'Names linked', 'text'], ['when', 'Updated', 'date']],
        'filters' => [['tier', 'Kind', ['' => 'Every kind', '2' => 'Regional', '3' => 'Area', '4' => 'Community', '5' => 'City', '6' => 'Neighborhood']], ['status', 'Status', ['' => 'Written', 'all' => 'Every place, written or not', 'live' => 'Live', 'auto' => 'Live (compiled by Claude)', 'review' => 'Claude\'s draft, waiting', 'draft' => 'Draft', 'gathering' => 'Gathering its sources', 'none' => 'Not written', 'held' => 'Names holding it back']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/Guides.php'; require_once __DIR__ . '/../lib/GuideLinks.php';
            if (!Guides::ready($pdo)) return [[], 0, []];
            $tree = Guides::tree($pdo); $names = [];
            foreach ($tree as [$k, $nm]) $names[$k] = $nm;
            $rows_g = []; foreach ($pdo->query('SELECT * FROM place_guides')->fetchAll(PDO::FETCH_ASSOC) as $r) $rows_g[$r['place_key']] = $r;
            $m = []; foreach ($pdo->query("SELECT SUBSTRING(src, 7) AS place_key, COUNT(*) n, SUM(status IN ('linked','thin')) l, SUM(status IN ('held','closed')) b, SUM(status IN ('finding','review','unconfirmed')) w FROM content_tags WHERE src LIKE 'guide:%' GROUP BY src")->fetchAll(PDO::FETCH_ASSOC) as $r) $m[$r['place_key']] = $r;
            $t = trim((string) ($q['q'] ?? '')); $rows = []; $counts = [];
            foreach ($tree as [$k, $nm, $tier, $parent]) {
                $gr = $rows_g[$k] ?? null; $mm = $m[$k] ?? null;
                $st = $gr ? $gr['status'] : 'none';
                $gi = $gr ? (json_decode((string) ($gr['draft_info'] ?? ''), true) ?: []) : [];
                $sc = $gr && array_key_exists('confidence', $gi) ? Guides::score($pdo, $k, $gi)[0] : null;
                if ($gr && $st === 'draft' && ($gr['draft_status'] ?? '') === 'done') $st = !empty($gi['kept']) ? 'draft' : 'review';   // Claude wrote it and it's waiting (a held or closed name, or publishing by itself is off); one our team took down is a draft
                elseif ($st === 'live' && !empty($gi['auto_published'])) $st = 'auto';
                elseif ($gr && $st === 'draft' && in_array($gr['draft_status'] ?? '', ['queued', 'writing'], true) && in_array(trim((string) $gr['sections']), ['', '{}', '[]'], true)) $st = 'none';
                elseif ($gr && ($gr['draft_status'] ?? '') === 'gathering' && in_array(trim((string) $gr['sections']), ['', '{}', '[]'], true)) $st = 'gathering';
                $row = ['id' => Guides::num($k), 'title' => $nm, 'kind' => Guides::TIERS[explode(':', $k)[0]][1], 'parent' => $parent ? ($names[$parent] ?? '') : '',
                        'status' => ['live' => 'Live', 'auto' => 'Live (compiled by Claude)', 'review' => 'Claude\'s draft, waiting', 'draft' => 'Draft', 'none' => 'Not written', 'gathering' => 'Gathering its sources'][$st], 'score' => $sc === null ? '' : (string) $sc, 'names' => $mm ? (int) $mm['l'] . ' of ' . (int) $mm['n'] . ((int) $mm['w'] ? ' · ' . (int) $mm['w'] . ' being found' : '') . ((int) $mm['b'] ? ' · ' . (int) $mm['b'] . ' holding it back' : '') : '',
                        'when' => $gr['updated_at'] ?? null, 'tier' => $tier, 's' => $st, 'held' => $mm && (int) $mm['b'] > 0];
                if ($t !== '' && stripos($nm . ' ' . $row['parent'], $t) === false) continue;
                if (($q['tier'] ?? '') !== '' && (int) $q['tier'] !== $tier) continue;
                // each choice's count (Jason, 2026-11-12: "why am I seeing not written guides here?"): Written opens the list;
                // places with no guide yet are a choice of their own, and in Every place
                $counts[$st === 'auto' ? 'live' : $st] = ($counts[$st === 'auto' ? 'live' : $st] ?? 0) + 1; if ($st === 'auto') $counts['auto'] = ($counts['auto'] ?? 0) + 1;
                $counts['all'] = ($counts['all'] ?? 0) + 1; if ($st !== 'none') $counts[''] = ($counts[''] ?? 0) + 1; if ($row['held']) $counts['held'] = ($counts['held'] ?? 0) + 1;
                $f = (string) ($q['status'] ?? '');
                if ($f === '' ? $st === 'none' : ($f === 'all' ? false : ($f === 'held' ? !$row['held'] : ($f !== $st && !($f === 'live' && $st === 'auto'))))) continue;
                $rows[] = $row;
            }
            usort($rows, fn ($a, $b) => [!$a['held'], $a['s'] !== 'review', $a['s'] === 'none', $a['tier'], $a['title']] <=> [!$b['held'], $b['s'] !== 'review', $b['s'] === 'none', $b['tier'], $b['title']]);   // what's held back first, then Claude's waiting drafts, then written, then by tier
            return [$rows, count($rows), $counts];
        },
    ],

    'explore/guides' => [
        'title' => 'Who to call', 'view' => ['min' => 'staff', 'depts' => ['con']], 'open' => '/admin/resource-guides.php?id={id}',
        'columns' => [['title', 'Guide', 'name'], ['area', 'Area', 'text'], ['n', 'Entries', 'count'], ['status', 'Status', 'pill'], ['when', 'Updated', 'date']],
        'list' => function (PDO $pdo, array $q) {
            $t = trim((string) ($q['q'] ?? ''));
            $st = $pdo->prepare('SELECT g.*, (SELECT COUNT(*) FROM resource_entries r WHERE r.guide_id = g.id) AS n FROM resource_guides g' . ($t !== '' ? ' WHERE g.title LIKE ? OR g.area_key LIKE ?' : '') . ' ORDER BY g.updated_at DESC');
            $st->execute($t !== '' ? ["%$t%", "%$t%"] : []);
            $rows = array_map(fn ($g) => ['id' => (int) $g['id'], 'title' => $g['title'], 'area' => $g['area_key'], 'n' => (int) $g['n'], 'status' => $g['status'], 'when' => $g['updated_at']], $st->fetchAll(PDO::FETCH_ASSOC));
            return [$rows, count($rows), []];
        },
        'create' => ['label' => 'New guide', 'page' => '/admin/resource-guides.php?id=0'],
    ],

    'explore/howto' => [
        'title' => 'How-to guides', 'view' => ['min' => 'staff', 'depts' => ['con']], 'workflow' => 'how_guide', 'open' => 'detail',
        'columns' => [['title', 'Guide', 'name'], ['asked', 'Searches', 'count'], ['stage', 'Stage', 'stage'], ['when', 'Updated', 'date']],
        'list' => function (PDO $pdo, array $q) {
            $t = trim((string) ($q['q'] ?? ''));
            $st = $pdo->prepare('SELECT id, gkey, title, asked_n, status, updated_at FROM how_guides' . ($t !== '' ? ' WHERE title LIKE ? OR gkey LIKE ?' : '') . " ORDER BY status = 'draft' DESC, asked_n DESC LIMIT 400");
            $st->execute($t !== '' ? ["%$t%", "%$t%"] : []);
            $rows = array_map(fn ($g) => ['id' => (int) $g['id'], 'title' => $g['title'] ?: $g['gkey'], 'asked' => (int) $g['asked_n'], 'stage' => $g['status'], 'when' => $g['updated_at']], $st->fetchAll(PDO::FETCH_ASSOC));
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) {
            require_once __DIR__ . '/../lib/HowGuides.php';
            $g = HowGuides::get($pdo, $id); if (!$g) return null;
            $steps = is_array($g['steps'] ?? null) ? $g['steps'] : (json_decode((string) ($g['steps'] ?? ''), true) ?: []);
            return ['title' => $g['title'] ?: $g['gkey'], 'subtitle' => (int) $g['asked_n'] . ' searches',
                'fields' => [['Intro', $g['intro']], ['Steps', implode("\n", array_map(fn ($x, $i) => ($i + 1) . '. ' . (is_array($x) ? ($x['text'] ?? $x['step'] ?? '') : $x), $steps, array_keys($steps)))], ['Note', $g['note']]],
                'links' => $g['status'] === 'published' ? [['Its page', '/guide/how.php?id=' . $id, 'new']] : []];
        },
        'create' => ['label' => 'Ask for a guide', 'fields' => [['title', 'What people want to do ("Winterize a camper")', 'text']], 'rule' => ['min' => 'staff', 'depts' => ['con']],
            'do' => function (PDO $pdo, array $in) { require_once __DIR__ . '/../lib/HowGuides.php'; return ['id' => HowGuides::request($pdo, (string) $in['title']), 'message' => 'Asked: the crawler looks on its next run.']; }],
    ],

    // ---- Marketplace (zip 2) -------------------------------------------------------------------------------------------------

    'market/codes' => [
        'title' => 'GO Codes & short links', 'view' => ['min' => 'staff', 'depts' => ['ops', 'svc', 'sal']], 'open' => 'detail',
        'columns' => [['title', 'Code', 'name'], ['kind', 'Kind', 'text'], ['what', 'Opens', 'text'], ['scans', 'Scans', 'count'], ['status', 'Status', 'pill']],
        'filters' => [['kind', 'Kind', ['' => 'Every kind', 'go' => 'GO Codes', 'page' => 'Shared pages', 'url' => 'Links', 'vcard' => 'Contact cards']], ['on', 'Status', ['' => 'All', '1' => 'Working', '0' => 'Switched off']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/ShortCodes.php';
            $w = []; $a = [];
            if (isset(ShortCodes::KINDS[$q['kind'] ?? ''])) { $w[] = 'kind = ?'; $a[] = $q['kind']; }
            if (in_array($q['on'] ?? '', ['0', '1'], true)) { $w[] = 'active = ?'; $a[] = (int) $q['on']; }
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(code LIKE ? OR title LIKE ? OR target LIKE ?)'; array_push($a, "%$t%", "%$t%", "%$t%"); }
            $st = $pdo->prepare('SELECT * FROM short_codes' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY id DESC LIMIT 400'); $st->execute($a);
            $rows = array_map(fn ($c) => ['id' => (int) $c['id'], 'title' => $c['code'], 'kind' => ShortCodes::KINDS[$c['kind']] ?? $c['kind'], 'what' => mb_strimwidth((string) ($c['title'] ?: $c['target']), 0, 60, '…'), 'scans' => (int) $c['scans'], 'status' => $c['active'] ? 'active' : 'off'], $st->fetchAll(PDO::FETCH_ASSOC));
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/ShortCodes.php';
            $st = $pdo->prepare('SELECT c.*, u.email, e.name AS listing FROM short_codes c LEFT JOIN users u ON u.id = c.owner_user_id LEFT JOIN entities e ON e.id = c.entity_id WHERE c.id = ?'); $st->execute([$id]);
            $c = $st->fetch(PDO::FETCH_ASSOC); if (!$c) return null;
            return ['title' => ShortCodes::url($c['code']), 'subtitle' => (ShortCodes::KINDS[$c['kind']] ?? '') . ($c['title'] ? ' · ' . $c['title'] : ''),
                'fields' => [['Opens', $c['target']], ['Owner', $c['listing'] ?: (string) $c['email']], ['Changeable', $c['dynamic'] ? 'Yes (dynamic)' : 'No'], ['Status', $c['active'] ? 'Working' : 'Switched off' . ($c['off_reason'] ? ': ' . $c['off_reason'] : '')],
                    ['Scans', (int) $c['scans'] . ($c['last_scan_at'] ? ', last ' . $day($c['last_scan_at']) : '')], ['Made', $day($c['created_at'])]],
                'links' => [['Open it', ShortCodes::url($c['code']), 'new'], ['Its QR code (PNG)', '/api/qr.php?code=' . rawurlencode($c['code']) . '&format=png&px=1200&download=1', 'new'], ['Manage short links', '/admin/short-codes.php?q=' . rawurlencode($c['code']), 'page']]];
        },
        'create' => ['label' => 'New short link', 'page' => '/admin/short-codes.php'],
    ],

    // ---- Community & Events (zip 2) ------------------------------------------------------------------------------------------

    'market/pages' => [
        'title' => 'Our pages', 'view' => ['min' => 'staff', 'depts' => ['con', 'sal', 'svc']], 'edit' => ['min' => 'staff', 'depts' => ['con', 'sal']], 'open' => 'detail',
        'columns' => [['title', 'Page', 'name'], ['addr', 'Address', 'text'], ['status', 'Status', 'pill'], ['form', 'Form', 'text'], ['updated', 'Changed', 'date']],
        'filters' => [['status', 'Status', ['' => 'All', 'live' => 'Live', 'draft' => 'Drafts', 'hidden' => 'Hidden']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/SitePages.php';
            $rows = [];
            foreach (SitePages::all($pdo) as $p) {
                if (($q['status'] ?? '') !== '' && $p['status'] !== $q['status']) continue;
                if (($t = trim((string) ($q['q'] ?? ''))) !== '' && stripos($p['title'] . ' ' . $p['slug'], $t) === false) continue;
                $rows[] = ['id' => (int) $p['id'], 'title' => $p['title'], 'addr' => '/pages/' . $p['slug'], 'status' => $p['status'],
                    'form' => $p['form'] === 'none' ? '' : SitePages::FORMS[$p['form']] . ((int) $p['new_entries'] ? ' · ' . (int) $p['new_entries'] . ' new' : ''), 'updated' => $p['updated_at']];
            }
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/SitePages.php';
            $p = SitePages::get($pdo, $id); if (!$p) return null;
            $opts = fn ($a) => array_map(fn ($k, $v) => [$k, $v], array_keys($a), $a);
            return ['title' => $p['title'], 'subtitle' => SitePages::STATUSES[$p['status']] . ' · ' . SitePages::url($p),
                'fields' => [['Status', SitePages::STATUSES[$p['status']] . ($p['status'] === 'live' ? '' : ' (only our team can see it)')], ['Address', SitePages::url($p)],
                    ['Search description', $p['summary'] ?: 'None yet: search engines and shares use the first lines'], ['Form', SitePages::FORMS[$p['form']]], ['Share picture', $p['share_image']],
                    ['Length', SitePages::words((string) $p['body']) . ' words'], ['Changed', $day($p['updated_at'])]],
                'links' => [['See the page', SitePages::url($p), 'new'], ['Its form entries', '/admin/market/leads', 'page']],
                'acts' => [
                    ['key' => 'write', 'label' => 'Write', 'asks' => [['body', 'The page (## heading, - list, **bold**, [link](/path), ![picture](https://...))', 'textarea', (string) $p['body'], 14, 'optional']]],
                    ['key' => 'edit', 'label' => 'Settings', 'asks' => [['title', 'Title', 'input', $p['title']], ['slug', 'Address: /pages/…', 'input', $p['slug']], ['summary', 'Search description (about 155 letters)', 'textarea', $p['summary'], 2, 'optional'],
                        ['form', 'Form on the page', 'select', $opts(SitePages::FORMS), $p['form']], ['share_image', 'Share picture (https://… or /uploads/…)', 'input', $p['share_image'], null, 'optional'], ['status', 'Status', 'select', $opts(SitePages::STATUSES), $p['status']]]],
                ]];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid) {
            require_once __DIR__ . '/../lib/SitePages.php';
            if ($key === 'write') { SitePages::save($pdo, $id, ['body' => (string) ($in['body'] ?? '')], $uid); return 'Saved.'; }
            if ($key === 'edit') { SitePages::save($pdo, $id, array_intersect_key($in, array_flip(['title', 'slug', 'summary', 'form', 'share_image', 'status'])), $uid); return 'Saved.'; }
            throw new InvalidArgumentException('Unknown action.');
        },
        'create' => ['label' => 'New page', 'rule' => ['min' => 'staff', 'depts' => ['con', 'sal']], 'fields' => [['title', 'Title', 'input']],
            'do' => function (PDO $pdo, array $in, int $uid) { require_once __DIR__ . '/../lib/SitePages.php'; return ['id' => SitePages::create($pdo, (string) $in['title'], $uid), 'message' => 'Made as a draft. Write it, then set it live in Settings.']; }],
    ],

    // ---- Market 360° → Marketplace (decisions/0135): people's posts --------------------------------------------------------

    'market/items' => [
        'title' => 'Items', 'view' => ['min' => 'staff', 'depts' => ['ops', 'svc', 'con']], 'edit' => ['min' => 'staff', 'depts' => ['ops', 'svc', 'con']], 'open' => 'detail',
        'columns' => [['title', 'Post', 'name'], ['price', 'Price', 'text'], ['seller', 'Seller', 'text'], ['place', 'Where', 'text'], ['status', 'Status', 'pill'], ['when', 'Posted', 'date']],
        'filters' => [['status', 'Status', ['' => 'All', 'held' => 'Waiting for a look', 'active' => 'Showing', 'pending' => 'Pending', 'sold' => 'Sold', 'paused' => 'Paused', 'expired' => 'Expired', 'removed' => 'Taken down', 'test' => 'Test posts (our team\'s)']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/Market.php';
            if (!Market::ready($pdo)) return [[], 0, []];
            $w = []; $a = [];
            if (isset(Market::STATUSES[(string) ($q['status'] ?? '')])) { $w[] = 'p.status = ?'; $a[] = (string) $q['status']; }
            if (($q['status'] ?? '') === 'test' && Market::testsReady($pdo)) $w[] = 'p.is_test = 1';
            if (($s = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(p.title LIKE ? OR p.about LIKE ? OR u.display_name LIKE ? OR p.place_label LIKE ?)'; array_push($a, "%$s%", "%$s%", "%$s%", "%$s%"); }
            $st = $pdo->prepare('SELECT p.*, u.display_name FROM market_posts p LEFT JOIN users u ON u.id = p.user_id' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY (p.status = 'held') DESC, p.created_at DESC LIMIT 300");
            $st->execute($a);
            $rows = []; $counts = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $counts[$p['status']] = ($counts[$p['status']] ?? 0) + 1;
                $rows[] = ['id' => (int) $p['id'], 'title' => (Market::isTest($p) ? '[Test] ' : '') . $p['title'], 'price' => Market::price($p), 'seller' => trim((string) $p['display_name']) ?: 'Member #' . (int) $p['user_id'],
                    'place' => $p['place_label'], 'status' => Market::STATUSES[$p['status']] ?? $p['status'], 'when' => $p['created_at']];
            }
            return [$rows, count($rows), $counts];
        },
        'metrics' => function (PDO $pdo) { require_once __DIR__ . '/../lib/Market.php'; return Market::ready($pdo) ? Market::metrics($pdo) : [['Switched on', 'Not yet: the database update runs within the hour', '']]; },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/Market.php';
            $p = Market::get($pdo, $id); if (!$p) return null;
            $u = People::user($pdo, (int) $p['user_id']);
            $fields = json_decode((string) ($p['fields'] ?? ''), true) ?: [];
            if ($p['item_condition'] !== '') $fields = ['Condition' => Market::CONDITIONS[$p['item_condition']] ?? $p['item_condition']] + $fields;
            $st = $pdo->prepare('SELECT COUNT(*) FROM market_contacts WHERE post_id = ?'); $st->execute([$id]); $talks = (int) $st->fetchColumn();
            $st = $pdo->prepare("SELECT COUNT(*) FROM content_reports WHERE kind = 'market_post' AND target_id = ? AND status = 'open'"); $reports = 0; try { $st->execute([$id]); $reports = (int) $st->fetchColumn(); } catch (Throwable) {}
            $s = $p['status'];
            return ['title' => $p['title'], 'subtitle' => Market::price($p) . ' · ' . $p['place_label'] . ' · ' . (Market::STATUSES[$s] ?? $s),
                'fields' => array_merge([['Status', (Market::STATUSES[$s] ?? $s) . ($p['hold_note'] !== '' && $s === 'held' ? ': ' . $p['hold_note'] : '') . ($p['staff_note'] !== '' && $s === 'removed' ? ': ' . $p['staff_note'] : '')],
                    ['Seller', ($u ? People::name($u) : 'Someone who left') . ' (member #' . (int) $p['user_id'] . ')'], ['Kind', (Market::KINDS[$p['kind']] ?? $p['kind']) . ' · ' . $p['category']],
                    ['Description', (string) $p['about']]], array_map(fn ($k, $v) => [$k, $v], array_keys($fields), $fields),
                    [['Photos', count(Market::photoIds($pdo, $id)) . ' (open the post to see them)'], ['Buyers who messaged', (string) $talks], ['Open reports', $reports ? $reports . ': decide in Social → Reports' : 'None'],
                     ['Offered for', (int) $p['offer_days'] ? (int) $p['offer_days'] . ' days' : ($p['ends_on'] ? 'Until ' . $day($p['ends_on']) : 'Ongoing')],
                     ['Next check', $p['check_due'] ? $day($p['check_due']) . ($p['asked_at'] ? ' (asked ' . $day($p['asked_at']) . ', not confirmed yet)' : '') : ''], ['Posted', $day($p['created_at'])]]),
                'links' => [['See the post', '/market/view.php?id=' . $id, 'new'], ['The seller', '/user/profile.php?id=' . (int) $p['user_id'], 'new']],
                'acts' => array_values(array_filter([
                    $s === 'held' ? ['key' => 'ok', 'label' => 'Let it show', 'asks' => [], 'go' => 'Let it show'] : null,
                    $s !== 'removed' ? ['key' => 'remove', 'label' => 'Take it down', 'asks' => [['note', 'Why, in a line the seller will see', 'input', '']], 'go' => 'Take it down', 'danger' => true] : null,
                    $s === 'removed' ? ['key' => 'restore', 'label' => 'Put it back up', 'asks' => [], 'go' => 'Put it back up'] : null,
                ]))];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid = 0) {
            require_once __DIR__ . '/../lib/Market.php';
            if (!in_array($key, ['ok', 'remove', 'restore'], true)) throw new InvalidArgumentException('Unknown action.');
            return Market::staffAct($pdo, $uid, $id, $key, (string) ($in['note'] ?? ''));
        },
    ],

    'market/offers' => [
        'title' => 'Offers & disputes', 'view' => ['min' => 'staff', 'depts' => ['ops', 'svc', 'con']], 'edit' => ['min' => 'staff', 'depts' => ['ops', 'svc']], 'open' => 'detail',
        'columns' => [['title', 'Post', 'name'], ['amount', 'Amount', 'text'], ['people', 'Buyer → seller', 'text'], ['status', 'Stage', 'pill'], ['when', 'Last move', 'date']],
        'filters' => [['status', 'Stage', ['disputed' => 'Problem reported', '' => 'All', 'asked' => 'Offer made', 'countered' => 'Countered', 'accepted' => 'Accepted', 'completed' => 'Completed', 'fell_through' => 'Fell through', 'declined' => 'Declined', 'withdrawn' => 'Withdrawn', 'lapsed' => 'No answer']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/MarketOffers.php';
            if (!MarketOffers::ready($pdo)) return [[], 0, []];
            $w = []; $a = [];
            $s = (string) ($q['status'] ?? 'disputed');
            if (isset(MarketOffers::STATUSES[$s])) { $w[] = 'o.status = ?'; $a[] = $s; }
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(p.title LIKE ? OR b.display_name LIKE ? OR se.display_name LIKE ?)'; array_push($a, "%$t%", "%$t%", "%$t%"); }
            $bz = MarketOffers::bizReady($pdo);
            $st = $pdo->prepare('SELECT o.*, ' . ($bz ? 'COALESCE(p.title, i.title) AS title, en.name AS biz' : 'p.title, NULL AS biz') . ', b.display_name AS bn, se.display_name AS sn FROM market_offers o LEFT JOIN market_posts p ON p.id = o.post_id'
                . ($bz ? ' LEFT JOIN market_items i ON i.id = o.item_id LEFT JOIN entities en ON en.id = o.entity_id' : '') . ' LEFT JOIN users b ON b.id = o.buyer_id LEFT JOIN users se ON se.id = o.seller_id'
                . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY o.updated_at DESC LIMIT 300');
            $st->execute($a);
            $rows = []; $counts = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $o) {
                $counts[$o['status']] = ($counts[$o['status']] ?? 0) + 1;
                $rows[] = ['id' => (int) $o['id'], 'title' => $o['title'], 'amount' => $o['amount_cents'] !== null ? MarketOffers::money((int) $o['amount_cents']) : 'No price',
                    'people' => (trim((string) $o['bn']) ?: 'Member #' . $o['buyer_id']) . ' → ' . ($o['biz'] ? $o['biz'] . ' (business)' : (trim((string) $o['sn']) ?: 'Member #' . $o['seller_id'])), 'status' => MarketOffers::STATUSES[$o['status']] ?? $o['status'], 'when' => $o['updated_at']];
            }
            return [$rows, count($rows), $counts];
        },
        'metrics' => function (PDO $pdo) {
            require_once __DIR__ . '/../lib/MarketOffers.php';
            if (!MarketOffers::ready($pdo)) return [['Switched on', 'Not yet: the database update runs within the hour', '']];
            $n = []; foreach ($pdo->query('SELECT status, COUNT(*) c FROM market_offers GROUP BY status') as $r) $n[(string) $r['status']] = (int) $r['c'];
            $wk = (int) $pdo->query("SELECT COUNT(*) FROM market_offers WHERE status = 'completed' AND completed_at >= '" . date('Y-m-d H:i:s', strtotime('-7 days')) . "'")->fetchColumn();
            return [['Problems reported', $n['disputed'] ?? 0, 'disputed'], ['Open offers', ($n['asked'] ?? 0) + ($n['countered'] ?? 0), 'asked'], ['Accepted, not confirmed yet', $n['accepted'] ?? 0, 'accepted'],
                    ['Completed this week', $wk, 'completed'], ['Completed in all', $n['completed'] ?? 0, 'completed'], ['Fell through', $n['fell_through'] ?? 0, 'fell_through']];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/MarketOffers.php';
            $o = MarketOffers::get($pdo, $id); if (!$o) return null;
            $p = MarketOffers::isBiz($o) ? (function () use ($pdo, $o) { require_once __DIR__ . '/../lib/MarketBiz.php'; return MarketBiz::get($pdo, (int) $o['item_id']); })() : Market::get($pdo, (int) $o['post_id']);
            $name = fn ($u) => ($x = People::user($pdo, (int) $u)) ? People::name($x) . ' (member #' . (int) $u . ')' : 'Someone who left (#' . (int) $u . ')';
            $hist = [];
            foreach (MarketOffers::events($pdo, $id) as $e) $hist[] = date('M j, g:i a', strtotime((string) $e['created_at'])) . ' · ' . ($e['by_user'] ? ((int) $e['by_user'] === (int) $o['buyer_id'] ? 'buyer' : ((int) $e['by_user'] === (int) $o['seller_id'] ? 'seller' : 'our team')) . ': ' : '')
                . $e['what'] . ($e['amount_cents'] !== null ? ' ' . MarketOffers::money((int) $e['amount_cents']) : '') . ($e['note'] !== '' ? ' (' . $e['note'] . ')' : '');
            $s = $o['status'];
            return ['title' => ($p['title'] ?? 'A post') . ': ' . ($o['amount_cents'] !== null ? MarketOffers::money((int) $o['amount_cents']) : 'no price'), 'subtitle' => (MarketOffers::STATUSES[$s] ?? $s) . ' · ' . $day($o['updated_at']),
                'fields' => [['Stage', MarketOffers::STATUSES[$s] ?? $s], ['Buyer', $name($o['buyer_id'])], ['Seller', MarketOffers::isBiz($o) ? 'A business (listing #' . (int) $o['entity_id'] . '), answered by its team' : $name($o['seller_id'])],
                    ['Sold / Got it', ($o['seller_done_at'] ? 'Seller tapped Sold ' . $day($o['seller_done_at']) : 'Seller: not yet') . ' · ' . ($o['buyer_done_at'] ? 'Buyer tapped Got it ' . $day($o['buyer_done_at']) : 'Buyer: not yet')],
                    ['Problem', $o['problem_note'] !== '' ? 'Reported by the ' . $o['problem_by'] . ': ' . $o['problem_note'] : ''],
                    ['Settled', $o['outcome'] !== '' ? (MarketOffers::OUTCOMES[$o['outcome']] ?? $o['outcome']) . ': ' . $o['outcome_note'] . ' (' . $day($o['resolved_at']) . ')' : ''],
                    ['History', implode("\n", $hist)]],
                'links' => [MarketOffers::isBiz($o) ? ['See the item', '/market/item.php?id=' . (int) $o['item_id'], 'new'] : ['See the post', '/market/view.php?id=' . (int) $o['post_id'], 'new'], ['The buyer', '/user/profile.php?id=' . (int) $o['buyer_id'], 'new'], ['The seller', '/user/profile.php?id=' . (int) $o['seller_id'], 'new']],
                'acts' => $s === 'disputed' ? [['key' => 'settle', 'label' => 'Settle it', 'asks' => [['outcome', 'How it ended', 'select', array_map(fn ($k, $v) => [$k, $v], array_keys(MarketOffers::OUTCOMES), MarketOffers::OUTCOMES), 'fell_through'],
                    ['note', 'What you found (both of them are emailed this)', 'textarea', '', 3]], 'go' => 'Settle it']] : []];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid = 0) {
            require_once __DIR__ . '/../lib/MarketOffers.php';
            if ($key !== 'settle') throw new InvalidArgumentException('Unknown action.');
            return MarketOffers::settle($pdo, $uid, $id, (string) ($in['outcome'] ?? ''), (string) ($in['note'] ?? ''));
        },
    ],

    'market/bizitems' => [
        'title' => 'Business items', 'view' => ['min' => 'staff', 'depts' => ['ops', 'svc', 'con']], 'edit' => ['min' => 'staff', 'depts' => ['ops', 'svc', 'con']], 'open' => 'detail',
        'columns' => [['title', 'Item', 'name'], ['price', 'Price', 'text'], ['biz', 'Business', 'text'], ['place', 'Where', 'text'], ['status', 'Status', 'pill'], ['when', 'Published', 'date']],
        'filters' => [['status', 'Status', ['' => 'All', 'held' => 'Waiting for our team', 'published' => 'Published', 'unpublished' => 'Unpublished', 'expired' => 'Expired', 'removed' => 'Taken down']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/MarketBiz.php';
            if (!MarketBiz::ready($pdo)) return [[], 0, []];
            $w = []; $a = [];
            if (isset(MarketBiz::STATUSES[(string) ($q['status'] ?? '')])) { $w[] = 'i.status = ?'; $a[] = (string) $q['status']; }
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(i.title LIKE ? OR e.name LIKE ?)'; array_push($a, "%$t%", "%$t%"); }
            $st = $pdo->prepare('SELECT i.*, e.name AS biz FROM market_items i LEFT JOIN entities e ON e.id = i.entity_id' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY i.published_at DESC LIMIT 300');
            $st->execute($a);
            $rows = []; $counts = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $i) {
                $counts[$i['status']] = ($counts[$i['status']] ?? 0) + 1;
                $rows[] = ['id' => (int) $i['id'], 'title' => $i['title'], 'price' => MarketBiz::price($i), 'biz' => (string) $i['biz'], 'place' => $i['place_label'], 'status' => MarketBiz::STATUSES[$i['status']] ?? $i['status'], 'when' => $i['published_at']];
            }
            return [$rows, count($rows), $counts];
        },
        'metrics' => function (PDO $pdo) {
            require_once __DIR__ . '/../lib/MarketBiz.php';
            if (!MarketBiz::ready($pdo)) return [['Switched on', 'Not yet: the database update runs within the hour', '']];
            $n = []; foreach ($pdo->query('SELECT status, COUNT(*) c FROM market_items GROUP BY status') as $r) $n[(string) $r['status']] = (int) $r['c'];
            $b = (int) $pdo->query("SELECT COUNT(DISTINCT entity_id) FROM market_items WHERE status = 'published'")->fetchColumn();
            return [['Waiting for our team', $n['held'] ?? 0, 'held'], ['Published', $n['published'] ?? 0, 'published'], ['Businesses selling', $b, ''], ['Unpublished', $n['unpublished'] ?? 0, 'unpublished'], ['Expired', $n['expired'] ?? 0, 'expired'], ['Taken down', $n['removed'] ?? 0, 'removed']];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/MarketBiz.php';
            $i = MarketBiz::get($pdo, $id); if (!$i) return null;
            $b = MarketBiz::entity($pdo, (int) $i['entity_id']);
            $st = $pdo->prepare('SELECT version, by_user, created_at FROM market_item_versions WHERE item_id = ? ORDER BY version DESC LIMIT 10'); $st->execute([$id]);
            $vers = array_map(fn ($v) => 'Version ' . (int) $v['version'] . ' · ' . $day($v['created_at']) . ((int) $v['by_user'] ? ' · member #' . (int) $v['by_user'] : ''), $st->fetchAll(PDO::FETCH_ASSOC));
            return ['title' => $i['title'], 'subtitle' => MarketBiz::price($i) . ' · ' . ($b['name'] ?? '') . ' · ' . (MarketBiz::STATUSES[$i['status']] ?? $i['status']),
                'fields' => [['Status', (MarketBiz::STATUSES[$i['status']] ?? $i['status']) . ($i['staff_note'] !== '' ? ': ' . $i['staff_note'] : '')], ['Business', ($b['name'] ?? '') . ' (listing #' . (int) $i['entity_id'] . ', ' . Plan::TIERS[MarketBiz::tier($pdo, (int) $i['entity_id'])] . ')'],
                    ['Description', (string) $i['about']], ['Category', (string) $i['category']], ['Buttons', implode(', ', array_map(fn ($x) => MarketBiz::BUTTONS[$x][0], MarketBiz::buttonsFor($pdo, $i)))],
                    ['The rulebook (decisions/0142 §4)', (function () use ($i) { require_once __DIR__ . '/../lib/MarketRules.php'; [$t, $k] = MarketRules::flag($i);
                        return ['ok' => 'Nothing matched', 'notice' => 'Notice: ' . (MarketRules::RULES[$k][1] ?? $k), 'restricted' => 'Restricted: ' . (MarketRules::RULES[$k][1] ?? $k) . '; the business confirmed its license and conditions (card checkout off)',
                            'confirm' => 'Conditions: ' . (MarketRules::RULES[$k][1] ?? $k) . '; the business confirmed them (card checkout on)',
                            'said-not' => 'Matched ' . (MarketRules::RULES[$k][1] ?? $k) . '; the business said the item isn\'t that', 'review' => $k === 'recall' ? 'A possible recall (CPSC): see Status for the recall; approve only if it isn\'t the recalled product' : 'A hold word: waiting for our team',
                            'cleared' => 'A possible recall our team cleared'][$t] ?? $t; })()],
                    ['Published versions', implode("\n", $vers)], ['Next check', $i['check_due'] ? $day($i['check_due']) . ($i['asked_at'] ? ' (asked, not confirmed yet)' : '') : '']],
                'links' => [['See it', '/market/item.php?id=' . $id, 'new'], ['The listing', '/listing/view.php?id=' . (int) $i['entity_id'], 'new']],
                'acts' => array_values(array_filter([
                    $i['status'] !== 'removed' ? ['key' => 'remove', 'label' => 'Take it down', 'asks' => [['note', 'Why, in a line the business will see', 'input', '']], 'go' => 'Take it down', 'danger' => true] : null,
                    $i['status'] === 'removed' ? ['key' => 'restore', 'label' => 'Put it back up', 'asks' => [], 'go' => 'Put it back up'] : null,
                    $i['status'] === 'held' ? ['key' => 'approve', 'label' => 'Approve: it can go up', 'asks' => [], 'go' => 'Approve'] : null]))];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid = 0) {
            require_once __DIR__ . '/../lib/MarketBiz.php';
            return MarketBiz::staffAct($pdo, $id, $key, (string) ($in['note'] ?? ''));
        },
    ],

    'market/gateways' => [
        'title' => 'Gateways', 'view' => ['min' => 'staff', 'depts' => ['fin', 'ops', 'svc']], 'open' => 'detail',
        'columns' => [['title', 'Business', 'name'], ['provider', 'Gateway', 'text'], ['status', 'Status', 'pill'], ['live', 'Checkout', 'text'], ['when', 'Connected', 'date']],
        'filters' => [['status', 'Status', ['' => 'All', 'connected' => 'Connected', 'broken' => 'Stopped', 'disconnected' => 'Disconnected']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/MarketCheckout.php';
            if (!MarketCheckout::ready($pdo)) return [[], 0, []];
            $w = ['g.env = ?']; $a = [MarketPay::env()];
            if (in_array((string) ($q['status'] ?? ''), ['connected', 'broken', 'disconnected'], true)) { $w[] = 'g.status = ?'; $a[] = (string) $q['status']; }
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = 'e.name LIKE ?'; $a[] = "%$t%"; }
            $st = $pdo->prepare('SELECT g.*, e.name, c.live FROM market_gateways g LEFT JOIN entities e ON e.id = g.entity_id LEFT JOIN market_checkout c ON c.entity_id = g.entity_id WHERE ' . implode(' AND ', $w) . ' ORDER BY g.updated_at DESC LIMIT 300');
            $st->execute($a);
            $rows = []; $counts = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $g) {
                $counts[$g['status']] = ($counts[$g['status']] ?? 0) + 1;
                $rows[] = ['id' => (int) $g['entity_id'], 'title' => (string) $g['name'], 'provider' => MarketPay::PROVIDERS[$g['provider']] ?? $g['provider'], 'status' => ['connected' => 'Connected', 'broken' => 'Stopped', 'disconnected' => 'Disconnected'][$g['status']] ?? $g['status'],
                    'live' => (int) $g['live'] ? 'Live' : 'Not live', 'when' => $g['connected_at']];
            }
            return [$rows, count($rows), $counts];
        },
        'metrics' => function (PDO $pdo) {
            require_once __DIR__ . '/../lib/MarketCheckout.php';
            if (!MarketCheckout::ready($pdo)) return [['Switched on', 'Not yet: the database update runs within the hour', '']];
            $n = []; $st = $pdo->prepare('SELECT status, COUNT(*) c FROM market_gateways WHERE env = ? GROUP BY status'); $st->execute([MarketPay::env()]); foreach ($st as $r) $n[(string) $r['status']] = (int) $r['c'];
            $wk = date('Y-m-d H:i:s', strtotime('-7 days'));
            $st = $pdo->prepare("SELECT COUNT(*) n, COALESCE(SUM(total_cents), 0) s FROM market_orders WHERE status = 'paid' AND paid_at >= ? AND env = ?"); $st->execute([$wk, MarketPay::env()]); $p = $st->fetch(PDO::FETCH_ASSOC);
            $live = (int) $pdo->query('SELECT COUNT(*) FROM market_checkout WHERE live = 1')->fetchColumn();
            return [['Mode', ['live' => 'Live', 'sandbox' => 'Sandbox (test)', 'mock' => 'Local test', 'off' => 'Not set up (.env)'][MarketPay::env()], ''],
                    ['Stripe' . (($m = MarketPay::missingAll('stripe')) ? ': missing ' . implode(', ', $m) . ' in .env' : ': all set in .env'), $m ? (MarketPay::available('stripe')[0] ? 'Connects' : 'Can\'t connect') : 'Ready', ''],
                    ['Square' . (($m = MarketPay::missingAll('square')) ? ': missing ' . implode(', ', $m) . ' in .env' : ': all set in .env'), $m ? (MarketPay::available('square')[0] ? 'Connects' : 'Can\'t connect') : 'Ready', ''],
                    ['Connected', $n['connected'] ?? 0, 'connected'], ['Stopped', $n['broken'] ?? 0, 'broken'],
                    ['Checkouts live', $live, ''], ['Paid orders this week', (int) $p['n'], ''], ['Their sales this week (to them, not us)', MarketCheckout::money((int) $p['s']), '']];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/MarketCheckout.php';
            $g = MarketCheckout::gateway($pdo, $id); if (!$g) return null;
            $b = MarketBiz::entity($pdo, $id); $s = MarketCheckout::settings($pdo, $id);
            $st = $pdo->prepare("SELECT status, COUNT(*) c FROM market_orders WHERE entity_id = ? GROUP BY status"); $st->execute([$id]);
            $oc = implode(', ', array_map(fn ($r) => (MarketCheckout::STATUSES[$r['status']] ?? $r['status']) . ' ' . (int) $r['c'], $st->fetchAll(PDO::FETCH_ASSOC)));
            return ['title' => (string) ($b['name'] ?? 'Listing #' . $id), 'subtitle' => (MarketPay::PROVIDERS[$g['provider']] ?? $g['provider']) . ' · ' . $g['status'] . ' · ' . $g['env'],
                'fields' => [['Gateway', (MarketPay::PROVIDERS[$g['provider']] ?? $g['provider']) . ' account ' . $g['account_id'] . ($g['location_id'] !== '' ? ', location ' . $g['location_id'] : '')],
                    ['Status', $g['status'] . ($g['last_error'] !== '' ? ': ' . $g['last_error'] : '')], ['Token renews', $g['expires_at'] ? $day($g['expires_at']) : ($g['provider'] === 'stripe' ? 'Doesn\'t expire' : '')],
                    ['Checkout', ($s['live'] ? 'Live' : 'Not live') . ' · card ' . (!empty($s['card']) ? 'on' : 'off') . ' · direct: ' . ($s['methods'] ? implode(', ', array_column($s['methods'], 'label')) : 'none') . ' · tax ' . ($s['tax_milli'] / 1000) . '%'],
                    ['Orders', $oc ?: 'None yet'], ['Connected', $day($g['connected_at'])]],
                'links' => [['The listing', '/listing/view.php?id=' . $id, 'new']], 'acts' => []];
        },
    ],

    'market/storefronts' => [   // businesses' storefronts on trvgo.us (decisions/0139, step 5a)
        'title' => 'Storefronts', 'view' => ['min' => 'staff', 'depts' => ['ops', 'svc', 'con']], 'edit' => ['min' => 'staff', 'depts' => ['ops', 'svc', 'con']], 'open' => 'detail',
        'columns' => [['title', 'Business', 'name'], ['shop', 'Address', 'text'], ['status', 'Status', 'pill'], ['plan', 'Plan', 'text'], ['when', 'Changed', 'date']],
        'filters' => [['status', 'Status', ['' => 'All', 'building' => 'Building', 'live' => 'Live', 'paused' => 'Paused']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/Storefront.php'; require_once __DIR__ . '/../lib/Plan.php';
            if (!Storefront::ready($pdo)) return [[], 0, []];
            $rows = []; $counts = [];
            foreach (Storefront::all($pdo, (string) ($q['status'] ?? ''), (string) ($q['q'] ?? '')) as $s) {
                $counts[$s['status']] = ($counts[$s['status']] ?? 0) + 1;
                $rows[] = ['id' => (int) $s['entity_id'], 'title' => (string) ($s['name'] ?? 'Listing #' . $s['entity_id']), 'shop' => preg_replace('#^https?://#', '', Storefront::url($s)),
                    'status' => Storefront::STATUSES[$s['status']] ?? $s['status'], 'plan' => Plan::TIERS[Plan::tier((string) ($s['plan_tier'] ?? 'free'))] ?? '', 'when' => $s['updated_at']];
            }
            return [$rows, count($rows), $counts];
        },
        'metrics' => function (PDO $pdo) {
            require_once __DIR__ . '/../lib/Storefront.php';
            if (!Storefront::ready($pdo)) return [['Switched on', 'Not yet: the database update runs within the hour', '']];
            $n = []; foreach ($pdo->query('SELECT status, COUNT(*) c FROM storefronts GROUP BY status') as $r) $n[(string) $r['status']] = (int) $r['c'];
            $loc = (int) $pdo->query('SELECT COUNT(*) FROM storefronts WHERE parent_id IS NOT NULL')->fetchColumn();
            return [['Live', $n['live'] ?? 0, 'live'], ['Building', $n['building'] ?? 0, 'building'], ['Paused', $n['paused'] ?? 0, 'paused'], ['Locations under a brand', $loc, '']];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/Storefront.php'; require_once __DIR__ . '/../lib/SitePages.php'; require_once __DIR__ . '/../lib/MarketBiz.php';
            $s = Storefront::get($pdo, $id); if (!$s) return null;
            $b = MarketBiz::entity($pdo, $id);
            $pages = SitePages::all($pdo, $id);
            return ['title' => (string) ($b['name'] ?? 'Listing #' . $id), 'subtitle' => Storefront::STATUSES[$s['status']] . ' · ' . preg_replace('#^https?://#', '', Storefront::url($s)),
                'fields' => [['Status', Storefront::STATUSES[$s['status']] . ($s['live_at'] ? ' · first live ' . $day($s['live_at']) : '') . (Storefront::showing($pdo, $s) ? '' : ($s['status'] === 'live' ? ' (not showing: its plan or its listing)' : ''))],
                    ['Look', (Storefront::THEMES[$s['theme']][0] ?? $s['theme']) . ($s['accent'] !== '' ? ', accent ' . $s['accent'] : '') . ($s['logo_id'] ? ', a logo' : '') . ($s['cover_id'] ? ', a cover' : '')],
                    ['Tagline', $s['tagline'] ?: 'None'], ['A location of', $s['parent_slug'] ? 'trvgo.us/' . $s['parent_slug'] : 'No'],
                    ['Pages', $pages ? implode(', ', array_map(fn ($p) => $p['title'] . ' (' . (SitePages::STATUSES[$p['status']] ?? $p['status']) . ')', $pages)) : 'None'],
                    ['Changed', $day($s['updated_at'])]],
                'links' => array_values(array_filter([Storefront::showing($pdo, $s) ? ['The storefront', Storefront::url($s), 'new'] : ['Preview it', Storefront::url($s) . '?preview=' . Storefront::previewKey($id), 'new'], ['The listing', '/listing/view.php?id=' . $id, 'new']])),
                'acts' => $s['status'] === 'live' ? [['key' => 'pause', 'label' => 'Pause it', 'asks' => [], 'go' => 'Pause it', 'danger' => true]] : []];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid = 0) {
            require_once __DIR__ . '/../lib/Storefront.php';
            if ($key !== 'pause') throw new InvalidArgumentException('Unknown action.');
            Storefront::setStatus($pdo, $id, $uid, 'paused');
            return 'Paused. The business can open it again from its portal.';
        },
    ],

    'market/domains' => [   // businesses' own domains on their storefronts (decisions/0139 §3, step 5b)
        'title' => 'Custom domains', 'view' => ['min' => 'staff', 'depts' => ['ops', 'svc', 'eng', 'fin']], 'edit' => ['min' => 'staff', 'depts' => ['ops', 'svc', 'eng']], 'open' => 'detail',
        'columns' => [['title', 'Domain', 'name'], ['biz', 'Business', 'text'], ['status', 'Status', 'pill'], ['source', 'How', 'text'], ['expires', 'Paid until', 'date'], ['when', 'Checked', 'date']],
        'filters' => [['status', 'Status', ['' => 'All', 'added' => 'Added', 'pointed' => 'DNS pointed', 'checked' => 'Certificate coming', 'live' => 'Live', 'failed' => 'Failed', 'removed' => 'Removed']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/Domains.php';
            if (!Domains::ready($pdo)) return [[], 0, []];
            $rows = []; $counts = [];
            foreach (Domains::all($pdo, (string) ($q['status'] ?? ''), (string) ($q['q'] ?? '')) as $d) {
                $counts[$d['status']] = ($counts[$d['status']] ?? 0) + 1;
                $rows[] = ['id' => (int) $d['id'], 'title' => (string) $d['domain'], 'biz' => (string) ($d['name'] ?? ''), 'status' => Domains::STATUSES[$d['status']] ?? $d['status'],
                    'source' => Domains::SOURCES[$d['source']] ?? $d['source'], 'expires' => $d['expires_at'], 'when' => $d['last_check_at']];
            }
            return [$rows, count($rows), $counts];
        },
        'metrics' => function (PDO $pdo) {
            require_once __DIR__ . '/../lib/Domains.php';
            if (!Domains::ready($pdo)) return [['Switched on', 'Not yet: the database update runs within the hour', '']];
            $n = []; foreach ($pdo->query('SELECT status, COUNT(*) c FROM business_domains GROUP BY status') as $r) $n[(string) $r['status']] = (int) $r['c'];
            $soon = (int) $pdo->query("SELECT COUNT(*) FROM business_domains WHERE source = 'bought' AND status <> 'removed' AND expires_at < DATE_ADD(NOW(), INTERVAL 30 DAY)")->fetchColumn();
            [$av] = Domains::available(); [$sell] = Domains::canSell();
            return [['Mode', ['live' => 'Live', 'test' => 'Test (our team only, no charges)', 'mock' => 'Local test', 'off' => 'Off'][Domains::mode()] . ($av ? '' : ' · not open') . ($sell ? ' · selling' : (Domains::mode() === 'live' && strtolower(trim((string) getenv('NAMECOM_ENV'))) !== 'live' ? ' · not selling: Name.com is on its sandbox (switch to Test, or set NAMECOM_ENV=live)' : (Domains::ncDown() ? ' · paused: Name.com refused our sign-in (see Pulse)' : ''))), ''],
                ['Live', $n['live'] ?? 0, 'live'], ['Waiting', ($n['added'] ?? 0) + ($n['pointed'] ?? 0) + ($n['checked'] ?? 0), ''], ['Failed', $n['failed'] ?? 0, 'failed'], ['Bought ones due in 30 days', $soon, '']];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/Domains.php'; require_once __DIR__ . '/../lib/MarketBiz.php';
            $d = Domains::get($pdo, $id); if (!$d) return null;
            $b = MarketBiz::entity($pdo, (int) $d['entity_id']);
            return ['title' => (string) $d['domain'], 'subtitle' => (Domains::STATUSES[$d['status']] ?? $d['status']) . ' · ' . (Domains::SOURCES[$d['source']] ?? '') . ' · ' . (string) ($b['name'] ?? ''),
                'fields' => [['Status', (Domains::STATUSES[$d['status']] ?? $d['status']) . ($d['last_error'] !== '' ? ': ' . $d['last_error'] : '')], ['DNS answers', $d['seen'] ?: 'Nothing yet'],
                    ['Certificate', $d['cert_status'] ?: 'Not asked for yet'], ['Email records', json_decode((string) $d['dkim'], true) ? ((int) $d['email_ok'] ? 'Found' : 'Not found yet') : 'Not made (SES not set up)'],
                    ['Bought', $d['source'] === 'bought' ? 'Name.com order ' . $d['provider_order'] . ' · paid until ' . ($d['expires_at'] ? $day($d['expires_at']) : '?') . ' · ' . ((int) $d['auto_renew'] ? 'renews at ' . Domains::usd((int) $d['renew_cents']) : 'won\'t renew') : 'No: the business brought it'],
                    ['Last checked', $d['last_check_at'] ? $day($d['last_check_at']) : 'Not yet'], ['Added', $day($d['created_at'])]],
                'links' => array_values(array_filter([$d['status'] === 'live' ? ['The storefront', 'https://' . $d['domain'], 'new'] : null, ['The listing', '/listing/view.php?id=' . (int) $d['entity_id'], 'new']])),
                'acts' => $d['status'] === 'removed' ? [] : [['key' => 'check', 'label' => 'Check now', 'asks' => [], 'go' => 'Check now'], ['key' => 'disconnect', 'label' => 'Disconnect it', 'asks' => [], 'go' => 'Disconnect it', 'danger' => true]]];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid = 0) {
            require_once __DIR__ . '/../lib/Domains.php';
            $d = Domains::get($pdo, $id); if (!$d) throw new InvalidArgumentException('That domain isn\'t here.');
            if ($key === 'check') { $d = Domains::check($pdo, $id); return Domains::STATUSES[$d['status']] . ($d['last_error'] !== '' ? ': ' . $d['last_error'] : '.'); }
            if ($key === 'disconnect') { Domains::disconnect($pdo, (int) $d['entity_id']); return 'Disconnected. Its storefront is back at trvgo.us.'; }
            throw new InvalidArgumentException('Unknown action.');
        },
    ],

    'market/inform' => [   // the INFORM Consumers Act (decisions/0142): businesses at or near the high-volume levels
        'title' => 'Seller compliance', 'view' => ['min' => 'staff', 'depts' => ['ops', 'svc', 'fin']], 'edit' => ['min' => 'staff', 'depts' => ['ops', 'fin']], 'open' => 'detail',
        'columns' => [['title', 'Business', 'name'], ['level', 'Level', 'pill'], ['sales', 'Sales (12 mo)', 'text'], ['gross', 'Gross (12 mo)', 'text'], ['state', 'Details', 'pill'], ['when', 'Confirmed', 'date']],
        'filters' => [['level', 'Show', ['' => 'All', 'overdue' => 'Overdue or paused', 'high' => 'High-volume', 'disclose' => 'Shown to buyers', 'below' => 'Below the levels']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/Inform.php';
            $rows = []; $counts = [];
            foreach (Inform::all($pdo, (string) ($q['level'] ?? '')) as $r) {
                if (($t = trim((string) ($q['q'] ?? ''))) !== '' && stripos((string) $r['name'], $t) === false) continue;
                [$need, , $over] = Inform::needs($r);
                $state = !empty($r['paused_at']) ? 'Paused: overdue' : ($need ? ($over ? 'Overdue' : 'Asked, due ' . date('M j', strtotime((string) $r['due_at']))) : ($r['certified_at'] ? 'Confirmed' : 'Not needed'));
                $counts[$r['level']] = ($counts[$r['level']] ?? 0) + 1;
                $rows[] = ['id' => (int) $r['entity_id'], 'title' => (string) ($r['name'] ?? 'Listing #' . $r['entity_id']), 'level' => Inform::LEVELS[$r['level']] ?? $r['level'], 'sales' => number_format((int) $r['sales_12m']),
                    'gross' => '$' . number_format((int) $r['cents_12m'] / 100), 'state' => $state, 'when' => $r['certified_at']];
            }
            return [$rows, count($rows), $counts];
        },
        'metrics' => function (PDO $pdo) {
            require_once __DIR__ . '/../lib/Inform.php';
            if (!Inform::ready($pdo)) return [['Switched on', 'Not yet: the database update runs within the hour', '']];
            $n = []; foreach ($pdo->query('SELECT level, COUNT(*) c FROM inform_sellers GROUP BY level') as $r) $n[(string) $r['level']] = (int) $r['c'];
            $over = (int) $pdo->query('SELECT COUNT(*) FROM inform_sellers WHERE paused_at IS NOT NULL OR (due_at IS NOT NULL AND due_at < NOW())')->fetchColumn();
            return [['High-volume', ($n['high'] ?? 0) + ($n['disclose'] ?? 0), 'high'], ['Shown to buyers ($20,000+)', $n['disclose'] ?? 0, 'disclose'], ['Overdue or paused', $over, 'overdue'],
                ['Report by phone', trim((string) getenv('LEGAL_PHONE')) !== '' ? 'On: ' . getenv('LEGAL_PHONE') : 'Not set: System Configuration → Settings', '']];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/Inform.php';
            $r = Inform::get($pdo, $id); $i = $r['info']; [$need, $why, $over] = Inform::needs($r);
            $b = MarketBiz::entity($pdo, $id);
            return ['title' => (string) ($b['name'] ?? 'Listing #' . $id), 'subtitle' => (Inform::LEVELS[$r['level']] ?? $r['level']) . ' · ' . number_format((int) $r['sales_12m']) . ' sales, $' . number_format((int) $r['cents_12m'] / 100) . ' in 12 months',
                'fields' => [['Where it stands', !empty($r['paused_at']) ? 'Card checkout paused ' . $day($r['paused_at']) . ': details overdue' : ($need ? $why . ' Due ' . $day($r['due_at']) . '.' : ($r['certified_at'] ? 'Confirmed ' . $day($r['certified_at']) : 'Not needed yet'))],
                    ['Legal name', (string) ($i['legal_name'] ?? '')], ['Address', (string) ($i['address'] ?? '') . (!empty($i['residential']) ? ' (a home: buyers see the state only)' : '')],
                    ['Phone', (string) ($i['phone'] ?? '')], ['Email', (string) ($i['email'] ?? '')],
                    ['Tax ID', !empty($i['tax_last4']) ? strtoupper((string) $i['tax_type']) . ' ending ' . $i['tax_last4'] . ' (kept encrypted)' : ''], ['Bank', (string) ($i['bank'] ?? '')],
                    ['Asked', $r['requested_at'] ? $day($r['requested_at']) : '']],
                'links' => [['The listing', '/listing/view.php?id=' . $id, 'new']], 'acts' => []];
        },
    ],

    'market/leads' => [
        'title' => 'Form entries', 'view' => ['min' => 'staff', 'depts' => ['con', 'sal', 'svc']], 'edit' => ['min' => 'staff', 'depts' => ['con', 'sal', 'svc']], 'open' => 'detail',
        'columns' => [['title', 'From', 'name'], ['email', 'Email', 'text'], ['page', 'Page', 'text'], ['status', 'Status', 'pill'], ['when', 'Sent', 'date']],
        'filters' => [['status', 'Status', ['new' => 'New', '' => 'All', 'handled' => 'Handled', 'spam' => 'Spam']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/SitePages.php';
            $rows = array_map(fn ($e) => ['id' => (int) $e['id'], 'title' => $e['name'] ?: $e['email'], 'email' => $e['email'], 'page' => (string) $e['page_title'], 'status' => $e['status'], 'when' => $e['created_at']],
                SitePages::entries($pdo, 0, (string) ($q['status'] ?? 'new'), trim((string) ($q['q'] ?? ''))));
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/SitePages.php'; require_once __DIR__ . '/../lib/Customers.php';
            $e = SitePages::entry($pdo, $id); if (!$e) return null;
            Customers::sync($pdo);
            $st = $pdo->prepare('SELECT id FROM ' . Database::t('crm_customers') . ' WHERE owner_id = 0 AND email = ?'); $st->execute([$e['email']]); $cid = (int) $st->fetchColumn();
            $links = [['Email them', 'mailto:' . $e['email'] . '?subject=' . rawurlencode('Re: ' . ($e['page_title'] ?: 'your message')), 'new']];
            if ($e['phone']) $links[] = ['Call them', 'tel:' . preg_replace('/[^\d+]/', '', (string) $e['phone']), 'new'];
            if ($e['slug']) $links[] = ['The page', '/pages/view.php?s=' . rawurlencode((string) $e['slug']), 'new'];
            return ['title' => $e['name'] ?: $e['email'], 'subtitle' => (SitePages::FORMS[$e['kind']] ?? 'Form') . ' on ' . ($e['page_title'] ?: 'a page') . ' · ' . $day($e['created_at']),
                'fields' => [['Message', $e['message']], ['Email', $e['email']], ['Phone', $e['phone']], ['Status', SitePages::ENTRY_STATUSES[$e['status']] ?? $e['status']], ['In Customers', $cid ? 'Yes (#' . $cid . '): open Sales & Growth → Customers' : 'Not yet']],
                'links' => $links,
                'acts' => [['key' => 'mark', 'label' => 'Mark it', 'asks' => [['status', 'Status', 'select', array_map(fn ($k, $v) => [$k, $v], array_keys(SitePages::ENTRY_STATUSES), SitePages::ENTRY_STATUSES), $e['status'] === 'new' ? 'handled' : $e['status']]]]]];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in) {
            require_once __DIR__ . '/../lib/SitePages.php';
            if ($key !== 'mark') throw new InvalidArgumentException('Unknown action.');
            SitePages::markEntry($pdo, $id, (string) ($in['status'] ?? '')); return 'Marked.';
        },
    ],

    'social/reports' => [
        'title' => 'Reports', 'view' => ['min' => 'staff', 'depts' => ['ops', 'svc', 'con']], 'workflow' => 'report', 'open' => 'detail',
        'columns' => [['title', 'What was reported', 'name'], ['n', 'Reports', 'count'], ['why', 'Why', 'text'], ['stage', 'Stage', 'stage'], ['when', 'First report', 'date']],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/Reports.php';
            $rows = [];
            foreach ($pdo->query("SELECT MIN(id) id, kind, target_id, COUNT(*) n, SUM(status = 'open') open_n, MIN(created_at) first_at, GROUP_CONCAT(DISTINCT reason) reasons, MAX(status) st FROM content_reports WHERE 1=1" . Reports::staffOnly($pdo) . " GROUP BY kind, target_id ORDER BY SUM(status = 'open') > 0 DESC, MIN(created_at) DESC LIMIT 400") as $r)
                $rows[] = ['id' => (int) $r['id'], 'title' => (Reports::KIND_LABEL[$r['kind']] ?? ($r['kind'] ?: 'Lost report')) . ' #' . (int) $r['target_id'], 'n' => (int) $r['n'],
                    'why' => implode(', ', array_map(fn ($x) => Reports::label($x), array_filter(explode(',', (string) $r['reasons'])))), 'stage' => (int) $r['open_n'] ? 'open' : (string) $r['st'], 'when' => $r['first_at']];
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') $rows = array_values(array_filter($rows, fn ($r) => stripos($r['title'] . ' ' . $r['why'], $t) !== false));
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) {
            require_once __DIR__ . '/../lib/Reports.php';
            $st = $pdo->prepare('SELECT * FROM content_reports WHERE id = ?'); $st->execute([$id]);
            $r = $st->fetch(PDO::FETCH_ASSOC); if (!$r) return null;
            $item = null; foreach (Reports::open($pdo) as $o) if ($o['kind'] === $r['kind'] && (int) $o['id'] === (int) $r['target_id']) $item = $o;
            $all = $pdo->prepare('SELECT reason, note, status, created_at FROM content_reports WHERE kind = ? AND target_id = ? ORDER BY id'); $all->execute([$r['kind'], $r['target_id']]);
            return ['title' => (Reports::KIND_LABEL[$r['kind']] ?? 'Something') . ' #' . (int) $r['target_id'], 'subtitle' => $item['where'] ?? '',
                'fields' => [['What it says', (string) ($item['text'] ?? 'Decided already: open its page to see it.')], ['Written by', (string) ($item['author'] ?? '')],
                    ['Reports', implode("\n", array_map(fn ($x) => substr((string) $x['created_at'], 0, 10) . ' · ' . Reports::label((string) $x['reason']) . ($x['note'] ? ': ' . $x['note'] : '') . ' (' . $x['status'] . ')', $all->fetchAll(PDO::FETCH_ASSOC)))]],
                'links' => !empty($item['url']) ? [['See it where it was posted', $item['url'], 'new']] : []];
        },
    ],

    // ---- Galleries (decisions/0126 phase 3): what's on the site from everyone's galleries, for moderation. Private albums
    //      and the photos only in them stay private: they're counted, never listed or opened here. Taking something off
    //      the site never deletes the owner's files: an album goes private (its links stop), a photo comes out of its
    //      shared albums and stays in the owner's gallery.

    // Community groups (decisions/0047, 0136, 0137): every group, chapters under their parent. Hosts run their own groups
    // (0102 §4b); our team looks, hides one that breaks our Terms (its owner is told why), shows it again, and decides on
    // tags that may name a sacred or protected site (0066).
    'social/groups' => [
        'title' => 'Groups', 'view' => ['min' => 'staff', 'depts' => ['ops', 'svc', 'con']], 'edit' => ['min' => 'staff', 'depts' => ['ops', 'svc']], 'open' => 'detail',
        'columns' => [['title', 'Group', 'name'], ['kind', 'Kind', 'pill'], ['owner', 'Run by', 'text'], ['n', 'Members', 'count'], ['status', 'Status', 'pill'], ['when', 'Started', 'date']],
        'filters' => [['kind', 'Kind', ['' => 'Any', 'interest' => 'Interest groups', 'local' => 'Local groups', 'business' => 'Business groups', 'chapter' => 'Chapters']],
            ['status', 'Status', ['' => 'Open and hidden', 'active' => 'Open', 'hidden' => 'Hidden', 'proposed' => 'Chapter requests']], ['held', 'Tags', ['' => 'Any', 'held' => 'A tag waits for us']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/Community.php';
            if (!Community::ready($pdo)) return [[], 0, []];
            $grow = Community::growReady($pdo); $own = Community::ownersReady($pdo);
            $w = []; $a = [];
            $w[] = in_array($q['status'] ?? '', ['active', 'hidden', 'proposed'], true) ? 'g.status = ?' : "g.status IN ('active', 'hidden')"; if (in_array($q['status'] ?? '', ['active', 'hidden', 'proposed'], true)) $a[] = $q['status'];
            $k = (string) ($q['kind'] ?? '');
            if ($k === 'chapter' && $grow) $w[] = 'g.parent_id IS NOT NULL';
            elseif ($k === 'business' && $own) $w[] = "g.owner_kind = 'listing'";
            elseif ($k === 'local') $w[] = "g.place_key IS NOT NULL AND g.place_key <> ''" . ($grow ? ' AND g.parent_id IS NULL' : '') . ($own ? " AND g.owner_kind = 'person'" : '');
            elseif ($k === 'interest') $w[] = "(g.place_key IS NULL OR g.place_key = '')" . ($grow ? ' AND g.parent_id IS NULL' : '') . ($own ? " AND g.owner_kind = 'person'" : '');
            if (($q['held'] ?? '') === 'held' && $grow) $w[] = "EXISTS (SELECT 1 FROM community_group_tags t WHERE t.group_id = g.id AND t.status = 'held')";
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(g.name LIKE ? OR g.id = ?)'; array_push($a, "%$t%", (int) ltrim($t, '#')); }
            $st = $pdo->prepare('SELECT g.*, (SELECT COUNT(*) FROM community_group_members m WHERE m.group_id = g.id AND m.status = \'active\') n' . ($grow ? ', (SELECT p.name FROM community_groups p WHERE p.id = g.parent_id) pname' : '')
                . ' FROM community_groups g WHERE ' . implode(' AND ', $w) . ' ORDER BY ' . ($grow ? 'COALESCE(g.parent_id, g.id), g.parent_id IS NOT NULL, ' : '') . 'n DESC LIMIT 400');
            $st->execute($a);
            $rows = array_map(function ($r) use ($pdo) {
                $g = $r + ['owner_kind' => 'person', 'owner_ref' => 0]; $g['owner_ref'] = (int) $g['owner_ref'];
                $o = Community::owner($pdo, $g);
                $kind = !empty($r['parent_id']) ? 'chapter' : ($g['owner_kind'] === 'listing' ? 'business' : (!empty($r['place_key']) ? 'local' : 'interest'));
                return ['id' => (int) $r['id'], 'title' => (!empty($r['parent_id']) ? '↳ ' : '') . (string) $r['name'], 'kind' => $kind, 'owner' => $o['name'] . ($o['kind'] === 'listing' ? ' (business)' : ''),
                        'n' => (int) $r['n'], 'status' => $r['status'] === 'active' ? 'open' : (string) $r['status'], 'when' => $r['created_at']];
            }, $st->fetchAll(PDO::FETCH_ASSOC));
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/Community.php'; require_once __DIR__ . '/../lib/GroupCharters.php';
            if (is_file(__DIR__ . '/../lib/GroupChapters.php')) require_once __DIR__ . '/../lib/GroupChapters.php';
            $g = Community::group($pdo, $id, true); if (!$g) return null;
            $o = Community::owner($pdo, $g); $grow = Community::growReady($pdo);
            $pl = ['', '', '']; try { $pl = Community::place($pdo, (string) ($g['place_key'] ?? '')); } catch (Throwable) {}
            $c = GroupCharters::current($pdo, $id);
            $tags = $grow ? GroupGrowth::tags($pdo, $id, true) : [];
            $held = array_values(array_filter($tags, fn ($t) => $t['status'] === 'held'));
            $parent = $grow && $g['parent_id'] ? Community::group($pdo, $g['parent_id'], true) : null;
            $chs = $grow ? count(GroupChapters::of($pdo, $id)) : 0;
            $acts = [$g['status'] === 'hidden' ? ['key' => 'show', 'label' => 'Show it again', 'go' => 'Show it', 'asks' => [['why', 'Note for the log', 'textarea', '']]]
                                               : ['key' => 'hide', 'label' => 'Hide the group', 'danger' => true, 'go' => 'Hide it', 'asks' => [['why', 'Why (kept in the log; its owner is told)', 'textarea', '']]]];
            foreach ($held as $t) $acts[] = ['key' => 'tag_ok:' . $t['tag'], 'label' => 'Let "' . $t['tag'] . '" show', 'go' => 'Let it show'];
            foreach ($held as $t) $acts[] = ['key' => 'tag_no:' . $t['tag'], 'label' => 'Take "' . $t['tag'] . '" off', 'danger' => true, 'go' => 'Take it off'];
            return ['title' => (string) $g['name'], 'subtitle' => ($parent ? 'Chapter of ' . $parent['name'] . ' · ' : '') . 'Run by ' . $o['name'] . ' · ' . $g['members'] . ' members · ' . ($g['status'] === 'active' ? 'Open' : ucfirst((string) $g['status'])),
                'fields' => [['About', (string) $g['about']], ['Place', $pl[1]], ['Topic', Community::TOPICS[(string) ($g['topic'] ?? '')] ?? ''],
                    ['Tags', implode(', ', array_map(fn ($t) => $t['tag'] . ($t['status'] === 'held' ? ' (waits for us)' : ''), $tags))],
                    ['Chapters', $chs ? (string) $chs : ''], ['Joining', $g['join_mode'] === 'approval' ? 'Approval' : 'Open'],
                    ['Charter', $c ? 'Version ' . $c['version'] . ', ' . $day($c['changed_at']) : 'None yet'], ['Posts this week', (string) Community::postsSince($pdo, $id, 7)], ['Started', $day($g['created_at'])]],
                'links' => array_values(array_filter([['See the group', '/community/group.php?id=' . $id, 'new'], $parent ? ['Its parent', '/community/group.php?id=' . $parent['id'], 'new'] : null,
                    $o['kind'] === 'listing' ? ['The business\'s listing', $o['url'], 'new'] : null])),
                'acts' => $acts];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid = 0) {
            require_once __DIR__ . '/../lib/Community.php';
            $g = Community::group($pdo, $id, true); if (!$g) throw new InvalidArgumentException('That group isn\'t here.');
            require_once __DIR__ . '/../lib/Roles.php';
            if (preg_match('/^tag_(ok|no):(.+)$/', $key, $m) && Community::growReady($pdo)) {
                if ($m[1] === 'ok') $pdo->prepare("UPDATE community_group_tags SET status = 'shown' WHERE group_id = ? AND tag = ?")->execute([$id, $m[2]]);
                else $pdo->prepare('DELETE FROM community_group_tags WHERE group_id = ? AND tag = ?')->execute([$id, $m[2]]);
                Roles::log($pdo, $uid, null, 'community', 'group tag ' . ($m[1] === 'ok' ? 'allowed' : 'removed'), 'group ' . $id . ': ' . mb_substr($m[2], 0, 40));
                return $m[1] === 'ok' ? 'It shows now.' : 'Taken off.';
            }
            if (!in_array($key, ['hide', 'show'], true)) throw new InvalidArgumentException('Unknown action.');
            $why = trim((string) ($in['why'] ?? ''));
            if ($key === 'hide' && $why === '') throw new InvalidArgumentException('Say why.');
            Community::setHidden($pdo, $uid, $id, $key === 'hide', true);
            Roles::log($pdo, $uid, null, 'community', 'group ' . ($key === 'hide' ? 'hidden' : 'shown again'), 'group ' . $id . ($why !== '' ? ': ' . mb_substr($why, 0, 90) : ''));
            if ($key === 'hide' && is_file(__DIR__ . '/../lib/Notices.php')) {   // its owner is told why, once
                require_once __DIR__ . '/../lib/Notices.php'; require_once __DIR__ . '/../lib/GroupChapters.php';
                try { Notices::send($pdo, GroupChapters::runners($pdo, $g), 'group_post', 'hidden:' . $id, 'Traversence hid your group ' . $g['name'], mb_substr($why, 0, 380), '/community/'); } catch (Throwable) {}
            }
            return $key === 'hide' ? 'Hidden. Its owner is told why.' : 'It shows again.';
        },
        'metrics' => function (PDO $pdo) {
            require_once __DIR__ . '/../lib/Community.php';
            if (!Community::ready($pdo)) return [];
            $n = []; foreach ($pdo->query('SELECT status, COUNT(*) c FROM community_groups GROUP BY status') as $r) $n[$r['status']] = (int) $r['c'];
            $grow = Community::growReady($pdo);
            $ch = $grow ? (int) $pdo->query("SELECT COUNT(*) FROM community_groups WHERE parent_id IS NOT NULL AND status = 'active'")->fetchColumn() : 0;
            $held = $grow ? (int) $pdo->query("SELECT COUNT(DISTINCT group_id) FROM community_group_tags WHERE status = 'held'")->fetchColumn() : 0;
            return [['Open groups', $n['active'] ?? 0, ''], ['Chapters', $ch, ''], ['Hidden', $n['hidden'] ?? 0, ''], ['Tags waiting for us', $held, '']];
        },
    ],

    'social/gallery_albums' => [
        'title' => 'Shared albums', 'view' => ['min' => 'staff', 'depts' => ['ops', 'svc', 'con']], 'edit' => ['min' => 'staff', 'depts' => ['ops', 'svc']], 'open' => 'detail',
        'columns' => [['title', 'Album', 'name'], ['owner', 'Whose', 'text'], ['vis', 'Who sees it', 'pill'], ['mode', 'For', 'text'], ['n', 'Items', 'count'], ['when', 'Updated', 'date']],
        'filters' => [['vis', 'Who sees it', ['' => 'Public and unlisted', 'public' => 'Public', 'unlisted' => 'Unlisted (link only)']],
            ['mode', 'For', ['' => 'Any', 'social' => 'Personal', 'portfolio' => 'Portfolio', 'brand' => 'Brand & documents']], ['okind', 'Whose', ['' => 'Anyone', 'members' => 'Members', 'businesses' => 'Businesses']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/Gallery.php'; Gallery::ensure($pdo);
            $w = ["a.visibility IN ('public', 'unlisted')"]; $a = [];
            if (in_array($q['vis'] ?? '', ['public', 'unlisted'], true)) { $w[] = 'a.visibility = ?'; $a[] = $q['vis']; }
            if (isset(Gallery::MODES[$q['mode'] ?? ''])) { $w[] = 'a.mode = ?'; $a[] = $q['mode']; }
            if (in_array($q['okind'] ?? '', ['members', 'businesses'], true)) { $w[] = 'a.owner_kind = ?'; $a[] = $q['okind']; }
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(a.title LIKE ? OR a.id = ?)'; array_push($a, "%$t%", (int) ltrim($t, '#')); }
            $st = $pdo->prepare('SELECT a.*, (SELECT COUNT(*) FROM gallery_album_items ai WHERE ai.album_id = a.id) n FROM gallery_albums a WHERE ' . implode(' AND ', $w) . ' ORDER BY COALESCE(a.updated_at, a.created_at) DESC LIMIT 400'); $st->execute($a);
            $rows = array_map(fn ($r) => ['id' => (int) $r['id'], 'title' => (string) $r['title'], 'owner' => Gallery::ownerName($pdo, (string) $r['owner_kind'], (string) $r['owner_ref']) . ($r['owner_kind'] === 'businesses' ? ' (business)' : ''),
                'vis' => strtolower(Gallery::VIS[$r['visibility']]), 'mode' => Gallery::MODES[$r['mode']] ?? $r['mode'], 'n' => (int) $r['n'], 'when' => $r['updated_at'] ?: $r['created_at']], $st->fetchAll(PDO::FETCH_ASSOC));
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/GalleryModes.php';
            $a = Gallery::album($pdo, $id);
            if (!$a) return null;
            if ($a['visibility'] === 'private') return ['title' => 'A private album', 'subtitle' => 'Off the site: only its owner (and people they invite) see it.', 'fields' => [], 'links' => []];   // the owner's alone
            $n = $pdo->prepare("SELECT SUM(i.kind = 'photo'), SUM(i.kind = 'video'), SUM(i.kind = 'file') FROM gallery_album_items ai JOIN gallery_items i ON i.id = ai.item_id WHERE ai.album_id = ?"); $n->execute([$id]); [$ph, $vi, $fi] = array_map('intval', $n->fetch(PDO::FETCH_NUM) ?: [0, 0, 0]);
            $people = count(Gallery::people($pdo, $id)); $guests = Gallery::guestKey($pdo, $id) !== '';
            $o = $a['mode'] === 'portfolio' ? GalleryModes::opts($pdo, $id) : null;
            $url = $a['visibility'] === 'unlisted' ? '/gallery/album.php?k=' . $a['token'] : '/gallery/album.php?a=' . $id;
            return ['title' => (string) $a['title'], 'subtitle' => Gallery::ownerName($pdo, (string) $a['owner_kind'], (string) $a['owner_ref']) . ' · ' . Gallery::VIS[$a['visibility']] . ' · ' . (Gallery::MODES[$a['mode']] ?? ''),
                'fields' => [['About', (string) $a['about']], ['Holds', $ph . ' photos, ' . $vi . ' videos' . ($fi ? ', ' . $fi . ' files' : '')], ['People invited', (string) $people], ['Guest uploads', $guests ? 'On' : 'Off'],
                    ['Portfolio rules', $o ? (($o['picks'] ? 'Picks' . ($o['pick_limit'] ? ' (up to ' . $o['pick_limit'] . ')' : '') : 'No picks') . '; ' . GalleryModes::DOWNLOADS[$o['downloads']] . '; ' . ($o['watermark'] ? 'watermark' : 'no watermark')) : ''],
                    ['Date', $day($a['event_date'])], ['Made', $day($a['created_at'])]],
                'links' => [['See the album', $url, 'new']] + ($a['owner_kind'] === 'businesses' ? [1 => ['The business\'s listing', '/listing/view.php?id=' . (int) $a['owner_ref'], 'new']] : []),
                'acts' => [['key' => 'private', 'label' => 'Take it off the site', 'danger' => true, 'go' => 'Make it private', 'asks' => [['why', 'Why (kept in the log; the owner gets an email)', 'textarea', '']]]]];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid) {
            require_once __DIR__ . '/../lib/Gallery.php';
            if ($key !== 'private') throw new InvalidArgumentException('Unknown action.');
            $a = Gallery::album($pdo, $id); if (!$a) throw new InvalidArgumentException('That album isn\'t here.');
            $why = trim((string) ($in['why'] ?? '')); if ($why === '') throw new InvalidArgumentException('Say why.');
            $pdo->prepare("UPDATE gallery_albums SET visibility = 'private', token = ?, updated_at = NOW() WHERE id = ?")->execute([bin2hex(random_bytes(16)), $id]);
            require_once __DIR__ . '/../lib/Roles.php'; Roles::log($pdo, $uid, null, 'gallery', 'album made private', 'album ' . $id . ': ' . mb_substr($why, 0, 90));
            ak_gallery_tell($pdo, $a, 'Your album "' . $a['title'] . '" was made private', 'Our team made your album "' . $a['title'] . '" private, so it\'s no longer on the site and its links stop working. Its photos are still yours, in your gallery.' . "\n\nWhy: " . $why . "\n\nIf you think this was a mistake, reply to this email.");
            return 'Made private. Its links stop working, and the owner has an email.';
        },
        'metrics' => function (PDO $pdo) {
            require_once __DIR__ . '/../lib/Gallery.php'; Gallery::ensure($pdo);
            $n = []; foreach ($pdo->query('SELECT visibility, COUNT(*) c FROM gallery_albums GROUP BY visibility') as $r) $n[$r['visibility']] = (int) $r['c'];
            $bytes = (int) $pdo->query("SELECT COALESCE(SUM(bytes), 0) FROM gallery_items WHERE source = 'gallery'")->fetchColumn();
            return [['Public albums', $n['public'] ?? 0, 'public'], ['Unlisted albums', $n['unlisted'] ?? 0, 'unlisted'], ['Private (not shown here)', $n['private'] ?? 0, ''], ['Gallery storage', $bytes >= 1073741824 ? round($bytes / 1073741824, 1) . ' GB' : round($bytes / 1048576) . ' MB', '']];
        },
    ],

    'social/gallery_items' => [
        'title' => 'Shared photos and files', 'view' => ['min' => 'staff', 'depts' => ['ops', 'svc', 'con']], 'edit' => ['min' => 'staff', 'depts' => ['ops', 'svc']], 'open' => 'detail',
        'columns' => [['title', 'Item', 'name'], ['owner', 'Whose', 'text'], ['kind', 'Kind', 'pill'], ['where', 'In', 'text'], ['when', 'Added', 'date']],
        'filters' => [['kind', 'Kind', ['' => 'Any', 'photo' => 'Photos', 'video' => 'Videos', 'file' => 'Files']], ['guest', 'From', ['' => 'Anyone', 'guest' => 'Guests (no account)']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/Gallery.php'; Gallery::ensure($pdo);
            $w = ["i.source = 'gallery'", 'i.ready = 1']; $a = [];
            if (in_array($q['kind'] ?? '', ['photo', 'video', 'file'], true)) { $w[] = 'i.kind = ?'; $a[] = $q['kind']; }
            if (($q['guest'] ?? '') === 'guest') $w[] = 'EXISTS (SELECT 1 FROM gallery_guest_items g WHERE g.item_id = i.id)';
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(i.caption LIKE ? OR i.id = ?)'; array_push($a, "%$t%", (int) ltrim($t, '#')); }
            $st = $pdo->prepare("SELECT i.*, GROUP_CONCAT(DISTINCT al.title ORDER BY al.id SEPARATOR ', ') albums FROM gallery_items i JOIN gallery_album_items ai ON ai.item_id = i.id JOIN gallery_albums al ON al.id = ai.album_id AND al.visibility IN ('public', 'unlisted')
                WHERE " . implode(' AND ', $w) . ' GROUP BY i.id ORDER BY i.id DESC LIMIT 400'); $st->execute($a);
            $rows = array_map(fn ($r) => ['id' => (int) $r['id'], 'title' => ($r['caption'] !== '' ? mb_substr((string) $r['caption'], 0, 60) : ucfirst((string) $r['kind']) . ' #' . (int) $r['id']), 'owner' => Gallery::ownerName($pdo, (string) $r['owner_kind'], (string) $r['owner_ref']),
                'kind' => (string) $r['kind'], 'where' => (string) $r['albums'], 'when' => $r['created_at']], $st->fetchAll(PDO::FETCH_ASSOC));
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/GalleryModes.php';
            $it = Gallery::item($pdo, $id); if (!$it) return null;
            $st = $pdo->prepare("SELECT a.id, a.title, a.visibility, a.token FROM gallery_album_items ai JOIN gallery_albums a ON a.id = ai.album_id WHERE ai.item_id = ? AND a.visibility IN ('public', 'unlisted')"); $st->execute([$id]);
            $albums = $st->fetchAll(PDO::FETCH_ASSOC);
            if (!$albums) return ['title' => 'Not on the site', 'subtitle' => 'It\'s in no shared album now: only its owner sees it.', 'fields' => [], 'links' => []];   // the owner's alone
            $k = $albums[0]['visibility'] === 'unlisted' ? '&k=' . $albums[0]['token'] : '';
            $f = GalleryModes::file($pdo, $id);
            $g = $pdo->prepare('SELECT name FROM gallery_guest_items WHERE item_id = ?'); $g->execute([$id]); $guest = $g->fetchColumn();
            $links = $it['kind'] === 'file' ? [] : [['See it', '/gallery/m.php?i=' . $id . '&s=full' . $k, 'new']];
            foreach ($albums as $al) $links[] = ['Album: ' . $al['title'], $al['visibility'] === 'unlisted' ? '/gallery/album.php?k=' . $al['token'] : '/gallery/album.php?a=' . (int) $al['id'], 'new'];
            return ['title' => $f ? (string) $f['name'] : ucfirst((string) $it['kind']) . ' #' . $id, 'subtitle' => Gallery::ownerName($pdo, (string) $it['owner_kind'], (string) $it['owner_ref']) . ($guest !== false ? ' · added by ' . ($guest ?: 'a guest') . ' (guest)' : ''),
                'fields' => [['Caption', (string) $it['caption']], ['Alt text', (string) $it['alt']], ['In', implode(', ', array_map(fn ($x) => $x['title'] . ' (' . strtolower(Gallery::VIS[$x['visibility']]) . ')', $albums))],
                    ['Size', $it['width'] ? $it['width'] . ' × ' . $it['height'] : ''], ['Taken', $day($it['taken_at'])], ['Added', $day($it['created_at'])]],
                'links' => $links,
                'acts' => [['key' => 'hide', 'label' => 'Take it off the site', 'danger' => true, 'go' => 'Take it out of shared albums', 'asks' => [['why', 'Why (kept in the log; the owner gets an email)', 'textarea', '']]]]];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid) {
            require_once __DIR__ . '/../lib/Gallery.php';
            if ($key !== 'hide') throw new InvalidArgumentException('Unknown action.');
            $it = Gallery::item($pdo, $id); if (!$it) throw new InvalidArgumentException('That isn\'t here.');
            $why = trim((string) ($in['why'] ?? '')); if ($why === '') throw new InvalidArgumentException('Say why.');
            $st = $pdo->prepare("SELECT a.* FROM gallery_album_items ai JOIN gallery_albums a ON a.id = ai.album_id WHERE ai.item_id = ? AND a.visibility IN ('public', 'unlisted')"); $st->execute([$id]);
            $albums = $st->fetchAll(PDO::FETCH_ASSOC);
            $del = $pdo->prepare('DELETE FROM gallery_album_items WHERE album_id = ? AND item_id = ?');
            foreach ($albums as $a) { $del->execute([(int) $a['id'], $id]); $pdo->prepare('UPDATE gallery_albums SET cover_item = NULL WHERE id = ? AND cover_item = ?')->execute([(int) $a['id'], $id]); }
            require_once __DIR__ . '/../lib/Roles.php'; Roles::log($pdo, $uid, null, 'gallery', 'item taken off the site', 'item ' . $id . ': ' . mb_substr($why, 0, 90));
            ak_gallery_tell($pdo, $it, 'A photo was taken out of your shared albums', 'Our team took one of your ' . ($it['kind'] === 'file' ? 'files' : $it['kind'] . 's') . ' out of ' . implode(', ', array_map(fn ($a) => '"' . $a['title'] . '"', $albums)) . '. It\'s still yours, in your gallery.' . "\n\nWhy: " . $why . "\n\nIf you think this was a mistake, reply to this email.");
            return 'Taken out of ' . count($albums) . ' shared album' . (count($albums) === 1 ? '' : 's') . '. The owner has an email.';
        },
    ],

    'social/standing' => [
        'title' => 'Standing notices', 'view' => ['min' => 'staff', 'depts' => ['ops', 'svc']], 'workflow' => 'standing', 'open' => 'detail',
        'columns' => [['title', 'Member', 'name'], ['step', 'Step', 'text'], ['stage', 'Stage', 'stage'], ['due', 'Due', 'date'], ['when', 'Opened', 'date']],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/Standing.php';
            $t = trim((string) ($q['q'] ?? ''));
            $st = $pdo->prepare('SELECT a.id, a.step, a.status, a.due_at, a.created_at, COALESCE(NULLIF(u.display_name, \'\'), u.email) AS who FROM member_actions a JOIN users u ON u.id = a.user_id'
                . ($t !== '' ? ' WHERE u.display_name LIKE ? OR u.email LIKE ?' : '') . " ORDER BY a.status IN ('open','review') DESC, a.id DESC LIMIT 400");
            $st->execute($t !== '' ? ["%$t%", "%$t%"] : []);
            $rows = array_map(fn ($a) => ['id' => (int) $a['id'], 'title' => (string) $a['who'], 'step' => Standing::STEP[(int) $a['step']] ?? '', 'stage' => $a['status'], 'due' => $a['due_at'], 'when' => $a['created_at']], $st->fetchAll(PDO::FETCH_ASSOC));
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            require_once __DIR__ . '/../lib/Standing.php';
            $st = $pdo->prepare('SELECT a.*, u.display_name, u.email FROM member_actions a JOIN users u ON u.id = a.user_id WHERE a.id = ?'); $st->execute([$id]);
            $a = $st->fetch(PDO::FETCH_ASSOC); if (!$a) return null;
            return ['title' => trim((string) $a['display_name']) ?: $a['email'], 'subtitle' => (Standing::STEP[(int) $a['step']] ?? '') . ($a['serious'] ? ' · serious' : ''),
                'fields' => [['Why', $a['reason']], ['What to do', $a['steps']], ['Their review request', $a['review_text']], ['Decision', $a['decision_note']], ['Due', $a['due_at'] ? $day($a['due_at']) : ''], ['Opened', $day($a['created_at'])]],
                'links' => []];
        },
    ],

    // ---- Back-Office: people (zip 2) -----------------------------------------------------------------------------------------

    'backoffice/members' => [
        'title' => 'Members', 'view' => ['min' => 'staff', 'depts' => ['ops', 'svc']], 'open' => 'detail',
        'columns' => [['title', 'Member', 'name'], ['email', 'Email', 'text'], ['area', 'Area', 'text'], ['status', 'Status', 'pill'], ['when', 'Joined', 'date']],
        'filters' => [['show', 'Show', ['' => 'Everyone', 'suspended' => 'Access revoked', 'deleted' => 'Closing their account']]],
        'list' => function (PDO $pdo, array $q) {
            $w = []; $a = [];
            if (($q['show'] ?? '') === 'suspended') $w[] = 'is_suspended = 1'; elseif (($q['show'] ?? '') === 'deleted') $w[] = 'deleted_at IS NOT NULL';
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(id = ? OR email LIKE ? OR display_name LIKE ?)'; array_push($a, (int) $t, "%$t%", "%$t%"); }
            $st = $pdo->prepare('SELECT id, email, display_name, area_label, is_suspended, deleted_at, created_at FROM users' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY id DESC LIMIT 400'); $st->execute($a);
            $rows = array_map(fn ($u) => ['id' => (int) $u['id'], 'title' => trim((string) $u['display_name']) ?: (string) strstr((string) $u['email'], '@', true), 'email' => $u['email'], 'area' => (string) $u['area_label'],
                'status' => $u['deleted_at'] ? 'closing' : ($u['is_suspended'] ? 'revoked' : 'active'), 'when' => $u['created_at']], $st->fetchAll(PDO::FETCH_ASSOC));
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) use ($day) {
            $st = $pdo->prepare('SELECT * FROM users WHERE id = ?'); $st->execute([$id]);
            $u = $st->fetch(PDO::FETCH_ASSOC); if (!$u) return null;
            $ls = $pdo->prepare('SELECT e.name FROM entities e WHERE e.owner_user_id = ? LIMIT 20'); $ls->execute([$id]);
            $sa = $pdo->prepare('SELECT COUNT(*) FROM member_actions WHERE user_id = ? AND status IN (\'open\',\'review\')'); $sa->execute([$id]);
            return ['title' => trim((string) $u['display_name']) ?: $u['email'], 'subtitle' => 'Member #' . $id,
                'fields' => [['Email', $u['email'] . ($u['email_verified_at'] ? ' (confirmed)' : ' (not confirmed)')], ['Area', $u['area_label']], ['Trust', (string) $u['trust_score']], ['Open notices', (int) $sa->fetchColumn() ?: ''],
                    ['Listings they run', implode(', ', $ls->fetchAll(PDO::FETCH_COLUMN))], ['Status', $u['deleted_at'] ? 'Closing their account' : ($u['is_suspended'] ? 'Access revoked' : 'Active')], ['Joined', $day($u['created_at'])]],
                'links' => [['Their member card (standing, notes)', '/admin/people.php?sec=members#members:' . $id, 'page']]];
        },
    ],

    'backoffice/staff' => [
        'title' => 'Staff', 'view' => ['min' => 'manager', 'depts' => ['ops', 'fin']], 'open' => '/admin/staff-profile.php?id={id}',
        'columns' => [['title', 'Name', 'name'], ['sid', 'Staff ID', 'text'], ['level', 'Level', 'text'], ['dept', 'Department', 'text'], ['status', 'Status', 'pill'], ['last', 'Last sign-in', 'date']],
        'list' => function (PDO $pdo, array $q) {
            $t = trim((string) ($q['q'] ?? '')); $rows = [];
            foreach ($pdo->query('SELECT * FROM ' . Database::t('staff_accounts') . ' ORDER BY status = \'ended\', name') as $s) {
                if ($t !== '' && stripos($s['name'] . ' ' . $s['staff_id'] . ' ' . $s['email'], $t) === false) continue;
                $rows[] = ['id' => (int) $s['id'], 'title' => $s['name'], 'sid' => $s['staff_id'], 'level' => ucfirst((string) $s['level']), 'dept' => Access::DEPT_NAMES[$s['department']] ?? (string) $s['department'], 'status' => $s['status'], 'last' => $s['last_sign_in_at']];
            }
            return [$rows, count($rows), []];
        },
        'create' => ['label' => 'Add a staff login', 'page' => '/admin/people.php?sec=logins'],
    ],

    // ---- Insights & System Configuration (zip 2) -----------------------------------------------------------------------------

    // ---- the System Control Board's tables (decisions/0171 §12, release 12): its alarms, logbook, ledger and upkeep, each a
    // list to search, filter and open; actions go through the board's own library calls and permissions, greyed with the
    // reason when they can't be done. Executives, as the board. Nothing new is kept: they read what the board keeps.
    'config/alarms' => [
        'title' => 'Alarms', 'view' => ['min' => 'executive', 'depts' => []], 'open' => 'detail', 'count_by' => 'show',
        'columns' => [['title', 'Alarm', 'name'], ['level', 'Level', 'pill'], ['proc', 'Process', 'text'], ['raised', 'Raised', 'date'], ['times', 'Times', 'count'], ['ack', 'Acknowledged by', 'text'], ['cleared', 'Cleared', 'date']],
        'filters' => [['show', 'Show', ['' => 'Open', 'ack' => 'Acknowledged', 'cleared' => 'Cleared', 'all' => 'All', 'trip' => 'Trips', 'warning' => 'Warnings', 'notice' => 'Notices']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/ControlAlarms.php'; require_once __DIR__ . '/../lib/ControlRegister.php'; ControlAlarms::ensure($pdo);
            $names = []; try { foreach (ControlRegister::processes($pdo) as $k => $p) $names[$k] = (string) $p[1]; } catch (Throwable $x) {}
            $t = trim((string) ($q['q'] ?? '')); $f = (string) ($q['show'] ?? '');
            $st = $pdo->prepare('SELECT * FROM control_alarms WHERE (cleared_at IS NULL OR cleared_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY)' . ($t !== '' ? ' AND (title LIKE ? OR proc LIKE ?)' : '') . ' ORDER BY cleared_at IS NULL DESC, FIELD(level, \'trip\', \'warning\', \'notice\'), last_at DESC LIMIT 1000');
            $st->execute($t !== '' ? ["%$t%", "%$t%"] : []);
            $rows = []; $counts = ['' => 0, 'ack' => 0, 'cleared' => 0, 'all' => 0, 'trip' => 0, 'warning' => 0, 'notice' => 0];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
                $state = $a['cleared_at'] !== null ? 'cleared' : ($a['ack_at'] !== null ? 'ack' : '');
                $counts[$state]++; $counts['all']++; if ($state !== 'cleared') $counts[(string) $a['level']] = ($counts[(string) $a['level']] ?? 0) + 1;
                $hit = $f === 'all' || $f === $state || (in_array($f, ['trip', 'warning', 'notice'], true) && $a['level'] === $f && $state !== 'cleared');
                if (!$hit) continue;
                $rows[] = ['id' => (int) $a['id'], 'title' => (string) $a['title'], 'level' => $a['cleared_at'] !== null ? 'cleared' : (string) $a['level'], 'proc' => $names[(string) $a['proc']] ?? ControlAlarms::partName($pdo, (string) $a['proc']),   // a plain name, never job:… (02 §5.4)
                    'raised' => (string) $a['first_at'], 'times' => (int) $a['n_times'], 'ack' => (string) ($a['ack_name'] ?? ''), 'cleared' => (string) ($a['cleared_at'] ?? '')];
            }
            return [$rows, count($rows), $counts];
        },
        'get' => function (PDO $pdo, int $id) {
            require_once __DIR__ . '/../lib/ControlAlarms.php'; require_once __DIR__ . '/../lib/ControlBoard.php'; require_once __DIR__ . '/../lib/SystemStart.php';
            $a = ControlAlarms::byId($pdo, $id); if (!$a) return null;
            $open = $a['cleared_at'] === null; $trip = $open && $a['level'] === 'trip' && !empty($a['tripped_at']);
            [$okB, $whyB] = ControlBoard::can(); [$okS, $whyS] = SystemStart::can();
            $stopped = (bool) json_decode((string) ($a['stopped'] ?? ''), true);
            $part = ControlAlarms::partName($pdo, (string) $a['proc']);   // the part's plain name, never its raw id (02 §5.4)
            return ['title' => (string) $a['title'], 'subtitle' => (ControlAlarms::LEVELS[$a['level']] ?? $a['level']) . ' · ' . $part . ($open ? '' : ' · cleared'),
                'fields' => [['What it means', (string) $a['means']], ['What to do', (string) $a['todo']], ['Reading', (string) $a['reading']], ['Times', (int) $a['n_times'] . ', first ' . ControlAlarms::atUtc((int) strtotime($a['first_at'] . ' UTC')) . ', last ' . ControlAlarms::atUtc((int) strtotime($a['last_at'] . ' UTC'))],   // one format per alarm (the design review, 2026-11-23)
                    ['Acknowledged', $a['ack_at'] ? $a['ack_name'] . ', ' . ControlAlarms::atUtc((int) strtotime($a['ack_at'] . ' UTC')) : ($a['level'] === 'warning' ? 'Nothing to acknowledge: the system is handling it; it clears itself or becomes a trip' : 'Not yet')], ['Cleared', $a['cleared_at'] ? ControlAlarms::atUtc((int) strtotime($a['cleared_at'] . ' UTC')) . ': ' . $a['cleared_how'] : 'Open']],
                // its fix first (never a dead end: "What to do" names it), then the board and its part's page
                'links' => array_values(array_filter([
                    str_starts_with((string) $a['proc'], 'job:') ? ['Open its job: ' . ControlAlarms::jobPlain((string) preg_replace('/:.*$/', '', substr((string) $a['proc'], 4))), '/admin/config/scheduler#job-' . preg_replace('/:.*$/', '', substr((string) $a['proc'], 4)), 'page']
                        : (str_starts_with((string) $a['proc'], 'svc:') ? ['Open its card: ' . $part, '/admin/config/register#svc-' . substr((string) $a['proc'], 4), 'page'] : null),
                    ['On the board', '/admin/config/control#cb-alarm-' . (int) $a['id'], 'page'], [$part, '/admin/config/control/' . str_replace(':', '-', (string) $a['proc']), 'page']])),   // its own page, titled by its name
                'acts' => array_values(array_filter([
                    $open && $a['ack_at'] === null && $a['level'] !== 'warning' ? ['key' => 'ack', 'label' => 'Acknowledge', 'asks' => [], 'off' => $okB ? '' : $whyB] : null,   // not on a warning: the system handles it (0171 §7's 3c note; 0175 R2a)
                    $trip ? ['key' => 'resolve', 'label' => 'Check and start again', 'asks' => [], 'primary' => true, 'off' => !$okB ? $whyB : ($stopped && !$okS ? $whyS . ': it would start again what the trip stopped' : '')] : null,
                ]))];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid = 0) {
            require_once __DIR__ . '/../lib/ControlAlarms.php'; require_once __DIR__ . '/../lib/ControlBoard.php'; require_once __DIR__ . '/../lib/SystemStart.php';
            [$okB, $whyB] = ControlBoard::can(); if (!$okB) throw new InvalidArgumentException($whyB . '.');
            $who = (string) (Access::staff()['name'] ?? '');
            if ($key === 'ack') { if (!ControlAlarms::ack($pdo, $id, $uid, $who)) throw new InvalidArgumentException('It was already acknowledged.'); return 'Acknowledged: its emails won\'t repeat; it comes back if it gets worse.'; }
            if ($key === 'resolve') {
                $a = ControlAlarms::byId($pdo, $id);
                if ($a && json_decode((string) ($a['stopped'] ?? ''), true)) { [$okS, $whyS] = SystemStart::can(); if (!$okS) throw new InvalidArgumentException($whyS . ': it would start again what the trip stopped.'); }
                [$done, $msg] = ControlAlarms::resolve($pdo, $id, $uid, $who, '');
                if (!$done) throw new InvalidArgumentException($msg);
                return $msg . ' It\'s in the logbook.';
            }
            throw new InvalidArgumentException('Unknown action.');
        },
    ],
    'config/log' => [
        'title' => 'Logbook', 'view' => ['min' => 'executive', 'depts' => []], 'open' => 'detail',
        'columns' => [['title', 'What', 'name'], ['kind', 'Kind', 'pill'], ['proc', 'Process', 'text'], ['who', 'Who', 'text'], ['at', 'When', 'date']],
        'filters' => function (PDO $pdo) {
            require_once __DIR__ . '/../lib/ControlRegister.php';
            $o = ['' => 'The whole system']; foreach (ControlRegister::SYSTEMS as $k => $n) $o['sys:' . $k] = $n;
            try { foreach (ControlRegister::processes($pdo) as $k => $p) $o[$k] = (string) $p[1]; } catch (Throwable $x) {}
            return [['scope', 'Whose', $o]];
        },
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/ControlLog.php'; require_once __DIR__ . '/../lib/ControlRegister.php';
            $names = []; try { foreach (ControlRegister::processes($pdo) as $k => $p) $names[$k] = (string) $p[1]; } catch (Throwable $x) {}
            $sc = (string) ($q['scope'] ?? ''); if ($sc !== '' && !preg_match('/^(sys|wf|t|wk|svc|job|site):[A-Za-z0-9_.-]{1,50}$/', $sc)) $sc = '';
            $t = mb_strtolower(trim((string) ($q['q'] ?? '')));
            $rows = [];
            foreach (ControlLog::feed($pdo, $sc, 0, 200) as $i => [$at, $tz, $kind, $proc, $what, $who, $why]) {
                if ($t !== '' && !str_contains(mb_strtolower($what . ' ' . $who . ' ' . $why), $t)) continue;
                $rows[] = ['id' => $at * 1000 + $i, 'title' => $what, 'kind' => ControlLog::KINDS[$kind] ?? $kind, 'proc' => $names[$proc] ?? ($proc ?: 'The system'), 'who' => $who, 'at' => gmdate('Y-m-d H:i:s', $at), 'why' => $why];
            }
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) {
            require_once __DIR__ . '/../lib/ControlLog.php';
            $at = intdiv($id, 1000); $i = $id % 1000;
            foreach (ControlLog::feed($pdo, '', $at + 1, 200) as $j => [$a, $tz, $kind, $proc, $what, $who, $why]) if ($a === $at) return ['title' => $what, 'subtitle' => (ControlLog::KINDS[$kind] ?? $kind) . ' · ' . gmdate('M j, H:i', $a) . ' UTC' . ($tz !== '' ? ' (logged in ' . $tz . ')' : ''),
                'fields' => [['Who', $who], ['Why', $why !== '' ? $why : '—'], ['Process', $proc !== '' ? $proc : 'The system']], 'links' => [['The board\'s logbook', '/admin/config/control#log', 'page']]];
            return null;
        },
    ],
    // the System Control Board's parts as the admin's one table (design/table-standard.md, T1; 2026-11-19): a group row per
    // system, each part opening its own page; drawn on the board by tv_cb_process_table() (app/admin/control-console.php)
    'config/processes' => [
        'title' => 'The parts of the system', 'view' => ['min' => 'executive', 'depts' => []], 'open' => '/admin/config/control?proc={ref}', 'count_by' => 'show', 'pages_all' => true,
        'columns' => [['name', 'Process', 'name'], ['light', 'Status', 'status'], ['stage', 'Stage', 'pill'], ['where', 'Where it stands', 'text'], ['runs', 'Last 24 hours', 'text'], ['speed', 'Speed', 'text'], ['ok', 'OK', 'text']],
        'filters' => [['show', 'Show', ['' => 'Every part', 'bad' => 'Tripped', 'hold' => 'Held', 'warn' => 'On a backup', 'maint' => 'In maintenance', 'off' => 'Off', 'wait' => 'Not tested', 'ok' => 'Working']]],
        'list' => function (PDO $pdo, array $q) { require_once __DIR__ . '/control-console.php'; return tv_cb_table_rows($pdo, $q); },
    ],
    // a process's own data (Jason, 2026-11-19: "How do I see what data the workflow is creating"): every record its calls
    // created or changed, from the ledger's records (ids and what happened only, never contents: 0171 §4), paged in SQL
    'config/proc_records' => [
        'title' => 'Its data', 'view' => ['min' => 'executive', 'depts' => []], 'open' => '', 'count_by' => 'what',
        'columns' => [['label', 'Record', 'name'], ['what', 'What happened', 'pill'], ['call', 'Its call', 'text'], ['at', 'When', 'when']],
        'filters' => [['what', 'Show', ['' => 'Everything', 'stored' => 'Stored', 'updated' => 'Updated', 'review' => 'Sent to Review', 'refused' => 'Refused']]],
        'list' => function (PDO $pdo, array $q) { require_once __DIR__ . '/control-console.php'; return tv_cb_proc_records($pdo, $q); },
    ],
    'config/ledger' => [
        'title' => 'Ledger', 'view' => ['min' => 'executive', 'depts' => []], 'open' => 'detail', 'count_by' => 'show',
        'columns' => [['title', 'Call', 'name'], ['code', 'Answer', 'pill'], ['received', 'Received', 'count'], ['stored', 'Stored', 'count'], ['refused', 'Refused', 'count'], ['ms', 'ms', 'count'], ['at', 'When', 'date']],
        'filters' => [['show', 'Show', ['' => 'Every call', 'errors' => 'Server errors', 'refused' => 'With refusals']]],
        'list' => function (PDO $pdo, array $q) {
            $f = (string) ($q['show'] ?? ''); $t = trim((string) ($q['q'] ?? ''));
            $w = ['at >= UTC_TIMESTAMP() - INTERVAL 7 DAY']; $a = [];
            if ($f === 'errors') $w[] = 'code >= 500'; elseif ($f === 'refused') $w[] = 'n_refused > 0';
            if ($t !== '') { $w[] = 'endpoint LIKE ?'; $a[] = "%$t%"; }
            $rows = [];
            try { $st = $pdo->prepare('SELECT * FROM control_ledger WHERE ' . implode(' AND ', $w) . ' ORDER BY id DESC LIMIT 500'); $st->execute($a);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[] = ['id' => (int) $r['id'], 'title' => $r['method'] . ' /api/' . $r['endpoint'] . ($r['run_id'] ? ' · run #' . (int) $r['run_id'] : ''), 'code' => (string) $r['code'],
                    'received' => (int) $r['n_received'], 'stored' => (int) $r['n_stored'], 'refused' => (int) $r['n_refused'], 'ms' => (int) $r['ms'], 'at' => (string) $r['at']]; } catch (Throwable $x) {}
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) {
            try { $st = $pdo->prepare('SELECT * FROM control_ledger WHERE id = ?'); $st->execute([$id]); $r = $st->fetch(PDO::FETCH_ASSOC); } catch (Throwable $x) { return null; }
            if (!$r) return null;
            $recs = []; try { $rs = $pdo->prepare('SELECT kind, ref_id, what FROM control_records WHERE ledger_id = ? ORDER BY id LIMIT 50'); $rs->execute([$id]); $recs = $rs->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $x) {}
            return ['title' => $r['method'] . ' /api/' . $r['endpoint'], 'subtitle' => 'Answered ' . $r['code'] . ' in ' . $r['ms'] . ' ms · ' . $r['at'] . ' UTC' . ($r['run_id'] ? ' · crawler run #' . (int) $r['run_id'] : ''),
                'fields' => [['Handed out', (int) $r['n_sent']], ['Received', (int) $r['n_received']], ['Stored', (int) $r['n_stored']], ['Updated', (int) $r['n_updated']], ['For review', (int) $r['n_review']], ['Refused', (int) $r['n_refused'] . ($r['reasons'] ? ': ' . $r['reasons'] : '')],
                    ['Records it touched', $recs ? implode("\n", array_map(fn ($x) => $x['what'] . ' ' . $x['kind'] . ' #' . (int) $x['ref_id'], $recs)) . ((int) $r['records'] > count($recs) ? "\n(and " . ((int) $r['records'] - count($recs)) . ' more)' : '') : 'None kept']],
                'links' => [['On the board', '/admin/config/control', 'page']]];
        },
    ],
    'config/upkeep' => [
        'title' => 'Upkeep calendar', 'view' => ['min' => 'executive', 'depts' => []], 'open' => 'detail', 'count_by' => 'show',
        'columns' => [['title', 'What', 'name'], ['when', 'When', 'date'], ['how', 'How often', 'text'], ['state', 'Where it stands', 'text']],
        'filters' => [['show', 'Show', ['' => 'Coming up', 'windows' => 'Maintenance windows']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/ControlMaintenance.php';
            $rows = [];
            if ((string) ($q['show'] ?? '') === 'windows') {
                foreach (ControlMaintenance::listing($pdo) as $m) $rows[] = ['id' => 100000 + (int) $m['id'], 'title' => (string) $m['proc'] . ': ' . (string) $m['why'], 'when' => (string) $m['from_at'], 'how' => 'until ' . $m['until_at'] . ' UTC', 'state' => (string) $m['state'] . ($m['by_name'] ? ' · ' . $m['by_name'] : '')];
            } else foreach (ControlMaintenance::upkeep($pdo) as $i => $u) $rows[] = ['id' => $i + 1, 'title' => (string) $u[0], 'when' => $u[1] ? gmdate('Y-m-d H:i:s', (int) $u[1]) : '', 'how' => (string) $u[2], 'state' => (string) $u[3]];
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) {
            require_once __DIR__ . '/../lib/ControlMaintenance.php';
            if ($id > 100000) { foreach (ControlMaintenance::listing($pdo) as $m) if ((int) $m['id'] === $id - 100000) return ['title' => (string) $m['proc'] . ': ' . (string) $m['why'], 'subtitle' => ucfirst((string) $m['state']) . ($m['by_name'] ? ', set by ' . $m['by_name'] : ''),
                'fields' => [['From', $m['from_at'] . ' UTC'], ['Back by', $m['until_at'] . ' UTC'], ['What it stopped', (string) ($m['stopped'] ?? '—')], ['Back', $m['back_at'] ? $m['back_at'] . ' UTC' . ($m['back_by'] ? ', by ' . $m['back_by'] : '') : 'Not yet']],
                'links' => [['Maintenance on the board', '/admin/config/control#maintenance', 'page']]]; return null; }
            $u = ControlMaintenance::upkeep($pdo)[$id - 1] ?? null; if (!$u) return null;
            return ['title' => (string) $u[0], 'subtitle' => $u[1] ? 'Next ' . gmdate('M j, H:i', (int) $u[1]) . ' UTC' : 'Not scheduled', 'fields' => [['How often', (string) $u[2]], ['Where it stands', (string) $u[3]]],
                'links' => [['Maintenance on the board', '/admin/config/control#maintenance', 'page']]];
        },
    ],

    'insights/sources' => [
        'title' => 'Crawler sources', 'view' => ['min' => 'staff', 'depts' => ['ops', 'eng', 'con']], 'open' => 'detail',
        'columns' => [['title', 'Source', 'name'], ['engine', 'Kind', 'text'], ['status', 'Status', 'pill'], ['pages', 'Pages last time', 'count'], ['last', 'Last crawled', 'date']],
        'list' => function (PDO $pdo, array $q) {
            $t = trim((string) preg_replace('#^(?:https?://)?(?:www\.)?#i', '', trim((string) ($q['q'] ?? ''))));   // "www.visitarizona.com": its domain (2026-11-12)
            // one row per site (Jason, 2026-11-01: "Why the duplicates"): each crawl run is its own record, so a site crawled
            // twice was listed twice. Its latest run shows, with how many times it's been crawled; the crawler's own jobs
            // (worker.traversence, one per area sweep or search target) are one row. Grouped in the database, only the
            // columns shown and at most 500 sites (2026-11-18: reading every run's log made the page time out, Site health J01)
            $tb = Database::t('crawler_sources'); $w = $t !== '' ? ' WHERE url LIKE ? OR domain LIKE ?' : '';
            $st = $pdo->prepare("SELECT c.id, c.url, c.domain, c.engine, c.status, c.last_error, c.last_pages_fetched, c.last_crawled_at, g.n
                                 FROM $tb c JOIN (SELECT MAX(id) id, COUNT(*) n FROM $tb$w GROUP BY COALESCE(NULLIF(domain, ''), url)) g ON g.id = c.id
                                 ORDER BY c.last_crawled_at IS NULL, c.last_crawled_at DESC LIMIT 500");
            $st->execute($t !== '' ? ["%$t%", "%$t%"] : []);
            $by = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) $by[$c['domain'] ?: $c['url']] = ['c' => $c, 'n' => (int) $c['n']];
            $rows = [];
            foreach ($by as $k => ['c' => $c, 'n' => $n]) {
                $own = $k === 'worker.traversence';
                $rows[] = ['id' => (int) $c['id'], 'title' => ($own ? 'The crawler\'s own jobs' : $k) . ($n > 1 ? ' · ' . $n . ($own ? ' runs' : ' crawls') : ''),
                           'engine' => $own ? 'Area sweeps' : ($c['engine'] === 'directory' ? 'Directory' : 'Discovery'), 'status' => $c['last_error'] ? 'failed' : $c['status'],
                           'pages' => (int) $c['last_pages_fetched'], 'last' => $c['last_crawled_at']];
            }
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) {
            $st = $pdo->prepare('SELECT * FROM ' . Database::t('crawler_sources') . ' WHERE id = ?'); $st->execute([$id]);
            $c = $st->fetch(PDO::FETCH_ASSOC); if (!$c) return null;
            return ['title' => $c['domain'] ?: $c['url'], 'subtitle' => $c['url'],
                'fields' => [['What for', $c['purposes']], ['Why', $c['rationale']], ['Last error', $c['last_error']], ['Last run', mb_strimwidth((string) $c['last_log'], 0, 800, '…')]],
                'links' => [['Manage sources', '/admin/sources.php', 'page']]];
        },
    ],

    // ---- System Configuration → Stock library (decisions/0143): the library's photos. Searching the sources and adding
    // are above the list (app/admin/sections.php, tv_admin_mod_stock); each photo opens here to retag or retire it.
    'config/stock' => [
        'title' => 'The library', 'view' => ['min' => 'staff', 'depts' => ['con', 'ops', 'eng']], 'edit' => ['min' => 'staff', 'depts' => ['con', 'ops', 'eng']], 'open' => 'detail',
        'columns' => [['title', 'Photo', 'name'], ['subjects', 'Subjects', 'text'], ['place', 'Place', 'text'], ['source', 'Source', 'text'], ['status', 'Status', 'pill'], ['added', 'Added', 'date']],
        'filters' => [['status', 'Status', ['live' => 'In use', 'retired' => 'Retired', 'all' => 'All']], ['source', 'Source', ['' => 'Every source', 'wikimedia' => 'Wikimedia Commons', 'loc' => 'Library of Congress', 'pixabay' => 'Pixabay']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/StockLibrary.php';
            if (!StockLibrary::ready($pdo)) return [[], 0, []];
            $w = ['1 = 1']; $a = [];
            $stt = (string) ($q['status'] ?? 'live');
            if ($stt !== 'all') { $w[] = 'status = ?'; $a[] = $stt === 'retired' ? 'retired' : 'live'; }
            if (isset(StockLibrary::SOURCES[(string) ($q['source'] ?? '')])) { $w[] = 'source = ?'; $a[] = (string) $q['source']; }
            if (($t = trim((string) ($q['q'] ?? ''))) !== '') { $w[] = '(title LIKE ? OR subjects LIKE ? OR author LIKE ? OR credit LIKE ?)'; array_push($a, "%$t%", "%$t%", "%$t%", "%$t%"); }
            $st = $pdo->prepare('SELECT * FROM stock_images WHERE ' . implode(' AND ', $w) . ' ORDER BY id DESC LIMIT 500'); $st->execute($a);
            $rows = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[] = ['id' => (int) $r['id'], 'title' => (string) ($r['title'] ?: '#' . $r['id']), 'subjects' => trim(str_replace(',', ', ', (string) $r['subjects']), ', '),
                'place' => $r['place_key'] !== '' ? StockLibrary::placeName($pdo, (string) $r['place_key']) . ' · ' . StockLibrary::SCOPES[$r['place_scope']] : 'Any place (representative)',
                'source' => StockLibrary::sourceName((string) $r['source']), 'status' => $r['status'] === 'live' ? 'in use' : 'retired', 'added' => $r['added_at']];
            return [$rows, count($rows), []];
        },
        'get' => function (PDO $pdo, int $id) {
            require_once __DIR__ . '/../lib/StockLibrary.php';
            $r = StockLibrary::ready($pdo) ? StockLibrary::get($pdo, $id) : null; if (!$r) return null;
            $by = ''; if ($r['added_by']) { $u = $pdo->prepare('SELECT COALESCE(NULLIF(display_name, \'\'), email) FROM users WHERE id = ?'); $u->execute([(int) $r['added_by']]); $by = (string) $u->fetchColumn(); }
            $live = $r['status'] === 'live';
            return ['title' => (string) ($r['title'] ?: 'Stock photo #' . $id), 'subtitle' => StockLibrary::credit($r),
                'fields' => array_values(array_filter([
                    ['Status', $live ? 'In use: cards and heroes may show it' : 'Retired ' . date('M j, Y', strtotime((string) $r['retired_at'])) . ($r['retired_why'] !== '' ? ': ' . $r['retired_why'] : '')],
                    ['Licence', (string) $r['licence']], ['Author', (string) $r['author']], ['Credit', StockLibrary::credit($r)],
                    ['Source', StockLibrary::sourceName((string) $r['source']) . ' · ' . $r['source_id']],
                    ['Subjects', trim(str_replace(',', ', ', (string) $r['subjects']), ', ')],
                    ['Place', $r['place_key'] !== '' ? StockLibrary::placeName($pdo, (string) $r['place_key']) . ' · ' . StockLibrary::SCOPES[$r['place_scope']] : 'Any place (representative)'],
                    ['Size', (int) $r['width'] . ' × ' . (int) $r['height'] . ($r['path'] !== '' ? ' (our WebP copy)' : ' (shown from the source)')],
                    ['Chosen for', ($n = StockLibrary::uses($pdo, $id)) ? $n . ' place' . ($n === 1 ? '' : 's') : 'None by choice (picked automatically where it fits)'],
                    ['Added', date('M j, Y g:ia', strtotime((string) $r['added_at'])) . ($by !== '' ? ' by ' . $by : '')],
                ])),
                'links' => array_values(array_filter([['See the photo', (string) ($r['path'] ?: $r['remote_url']), 'new'], ['Its page at ' . StockLibrary::sourceName((string) $r['source']), (string) $r['source_url'], 'new'],
                    $r['licence_url'] !== '' ? ['The licence', (string) $r['licence_url'], 'new'] : null])),
                'acts' => array_values(array_filter([
                    ['key' => 'tag', 'label' => 'Change its tags', 'go' => 'Save tags', 'asks' => [
                        ['subjects', 'Subjects (what it shows, with commas)', 'input', trim(str_replace(',', ', ', (string) $r['subjects']), ', ')],
                        ['place', 'Place key from the places tree (cluster:12, town:4…; blank for any place)', 'input', (string) $r['place_key'], null, 'optional'],
                        ['scope', 'Does it show that place?', 'select', [['representative', 'Representative (the kind of place)'], ['this_place', 'This place itself']], (string) $r['place_scope']]]],
                    $live ? ['key' => 'retire', 'label' => 'Retire it', 'go' => 'Retire it', 'danger' => true, 'asks' => [['why', 'Why (for the team)', 'input', '', null, 'optional']]]
                          : ['key' => 'restore', 'label' => 'Put it back in use', 'go' => 'Put it back', 'asks' => []],
                ]))];
        },
        'act' => function (PDO $pdo, int $id, string $key, array $in, int $uid = 0) {
            require_once __DIR__ . '/../lib/StockLibrary.php';
            if (!StockLibrary::get($pdo, $id)) throw new InvalidArgumentException('That photo isn\'t in the library.');
            if ($key === 'tag') { StockLibrary::retag($pdo, $id, $in, $uid); return 'Tags saved.'; }
            if ($key === 'retire') { StockLibrary::retire($pdo, $id, (string) ($in['why'] ?? ''), $uid); return 'Retired: it\'s off every card and hero now, and anything that had chosen it picks another by itself.'; }
            if ($key === 'restore') { StockLibrary::restore($pdo, $id); return 'Back in use.'; }
            throw new InvalidArgumentException('Unknown action.');
        },
    ],

    // the decision records (ADRs) on GitHub, read through app/lib/DocsRepo.php (Jason, 2026-11-12); each opens in the section
    'config/decisions' => [
        'title' => 'Decisions', 'open' => '/admin/config/decisions?d={ref}',
        'columns' => [['num', '#', 'text'], ['title', 'Decision', 'name'], ['status', 'Status', 'pill'], ['notes', 'Notes', 'text'], ['replaced', 'Replaced by', 'text']],
        'filters' => [['status', 'Status', ['' => 'All', 'accepted' => 'Accepted', 'proposed' => 'Proposed', 'replaced' => 'Replaced', 'other' => 'Others']]],
        'list' => function (PDO $pdo, array $q) {
            require_once __DIR__ . '/../lib/DocsRepo.php';
            $t = mb_strtolower(trim((string) ($q['q'] ?? ''))); $f = (string) ($q['status'] ?? '');
            $rows = [];
            foreach (array_reverse(DocsRepo::all(), true) as $n => $d) {   // the newest first
                $st = strtolower($d['status']);
                if ($f !== '' && ($f === 'other' ? in_array($st, ['accepted', 'proposed', 'replaced'], true) : $st !== $f)) continue;
                if ($t !== '' && !str_contains(mb_strtolower($n . ' ' . $d['title']), $t)) continue;
                $rows[] = ['id' => (int) $n, 'ref' => (string) $n, 'href' => '/admin/config/decisions?d=' . $n, 'num' => (string) $n, 'title' => $d['title'], 'status' => $st !== '' ? $st : '·', 'notes' => $d['notes'] ? (string) $d['notes'] : '', 'replaced' => $d['replaced_by']];
            }
            return [$rows, count($rows), []];
        },
    ],
    'config/logs' => [
        'title' => 'Action log', 'view' => ['min' => 'director', 'depts' => ['eng']], 'open' => 'detail',
        'columns' => [['title', 'What', 'name'], ['who', 'Who', 'text'], ['where', 'Where', 'text'], ['when', 'When', 'date']],
        'filters' => [['log', 'Log', ['' => 'Actions', 'workflow' => 'Workflow moves', 'staff' => 'Staff sign-ins']]],
        'list' => function (PDO $pdo, array $q) {
            $t = trim((string) ($q['q'] ?? '')); $like = "%$t%"; $page = max(0, (int) ($q['page'] ?? 0)); $lim = ' LIMIT 50 OFFSET ' . ($page * 50);
            if (($q['log'] ?? '') === 'staff') {
                $st = $pdo->prepare("SELECT l.id, l.event AS what, s.staff_id AS who, l.note AS wh, l.created_at FROM " . Database::t('staff_log') . " l JOIN " . Database::t('staff_accounts') . " s ON s.id = l.staff_account_id WHERE (? = '' OR l.event LIKE ? OR s.staff_id LIKE ? OR l.note LIKE ?) ORDER BY l.id DESC" . $lim);
                $st->execute([$t, $like, $like, $like]);
            } elseif (($q['log'] ?? '') === 'workflow') {
                $st = $pdo->prepare("SELECT h.id, CONCAT(h.wf_key, ': ', COALESCE(h.from_stage, ''), ' → ', h.to_stage) AS what, COALESCE(NULLIF(u.display_name, ''), u.email) AS who, CONCAT('#', h.item_id, IF(h.note <> '', CONCAT(' · ', h.note), '')) AS wh, h.created_at
                                       FROM " . Database::t('wf_history') . " h LEFT JOIN users u ON u.id = h.actor WHERE (? = '' OR h.wf_key LIKE ? OR h.note LIKE ?) ORDER BY h.id DESC" . $lim);
                $st->execute([$t, $like, $like]);
            } else {
                $st = $pdo->prepare("SELECT a.id, a.action AS what, COALESCE(NULLIF(u.display_name, ''), u.email) AS who, CONCAT_WS(' · ', a.route, a.detail) AS wh, a.created_at FROM " . Database::t('audit_log') . " a LEFT JOIN users u ON u.id = a.actor_user
                                      WHERE (? = '' OR a.action LIKE ? OR a.detail LIKE ? OR u.display_name LIKE ?) ORDER BY a.id DESC" . $lim);
                $st->execute([$t, $like, $like, $like]);
            }
            $off = ['workflow' => 1000000000, 'staff' => 2000000000][$q['log'] ?? ''] ?? 0;   // which log a row is from, for its detail
            $rows = array_map(fn ($r) => ['id' => $off + (int) $r['id'], 'title' => (string) $r['what'], 'who' => (string) $r['who'], 'where' => mb_strimwidth((string) $r['wh'], 0, 90, '…'), 'when' => $r['created_at']], $st->fetchAll(PDO::FETCH_ASSOC));
            return [$rows, $page * 50 + count($rows) + (count($rows) === 50 ? 1 : 0), []];
        },
        'get' => function (PDO $pdo, int $id) {
            if ($id >= 2000000000) {
                $st = $pdo->prepare('SELECT l.*, s.staff_id, s.name FROM ' . Database::t('staff_log') . ' l JOIN ' . Database::t('staff_accounts') . ' s ON s.id = l.staff_account_id WHERE l.id = ?'); $st->execute([$id - 2000000000]);
                $l = $st->fetch(PDO::FETCH_ASSOC);
                return $l ? ['title' => $l['event'], 'subtitle' => $l['created_at'], 'fields' => [['Staff login', $l['staff_id'] . ' · ' . $l['name']], ['Note', $l['note']], ['By', (string) $l['actor_staff_id']]], 'links' => []] : null;
            }
            if ($id >= 1000000000) {
                $st = $pdo->prepare('SELECT h.*, COALESCE(NULLIF(u.display_name, \'\'), u.email) AS who FROM ' . Database::t('wf_history') . ' h LEFT JOIN users u ON u.id = h.actor WHERE h.id = ?'); $st->execute([$id - 1000000000]);
                $h = $st->fetch(PDO::FETCH_ASSOC);
                return $h ? ['title' => $h['wf_key'] . ' #' . $h['item_id'] . ': ' . $h['move'], 'subtitle' => $h['created_at'], 'fields' => [['Who', (string) $h['who']], ['From', (string) $h['from_stage']], ['To', $h['to_stage']], ['Version', (string) $h['version']], ['Note', $h['note']], ['What happened after', (string) $h['effects']]], 'links' => []] : null;
            }
            $st = $pdo->prepare('SELECT a.*, COALESCE(NULLIF(u.display_name, \'\'), u.email) AS who FROM ' . Database::t('audit_log') . ' a LEFT JOIN users u ON u.id = a.actor_user WHERE a.id = ?'); $st->execute([$id]);
            $a = $st->fetch(PDO::FETCH_ASSOC); if (!$a) return null;
            return ['title' => $a['action'], 'subtitle' => $a['created_at'], 'fields' => [['Who', (string) $a['who']], ['Staff login', (string) $a['actor_staff']], ['Where', $a['route']], ['Listing', (string) $a['listing_id']], ['Detail', $a['detail']]], 'links' => []];
        },
    ],
];
