<?php
// admin/crawler-review.php — Admin → Crawler → Review (decisions/0046)
//
// Everything the crawler needs a person for, in one list: listings to approve, possible duplicates to
// merge, websites to sort (Sources), local names to accept (sub-groups), and clusters still marked
// "needs review". Each card carries its common actions; "Edit" opens the full form. Filter by kind and by
// place; search, sort and group in the page; select listings to approve, check again or reject together.
// Opening one to edit or merge remembers the list's order, so the next one is a tap away when it's done.
// Admin-only, checked server-side; every POST carries the session CSRF token and redirects back.

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8'); // bootstrap defaults to JSON
require_once __DIR__ . '/../../app/ui/admin-crawler.php';

$pdo = Database::connection();
$me = tv_crawler_require_admin($pdo);
require_once __DIR__ . '/../../app/lib/crawler/AutoImport.php';
if (is_file(__DIR__ . '/../../app/lib/Tags.php')) require_once __DIR__ . '/../../app/lib/Tags.php';
foreach (['Verify', 'Sources', 'Subgroups', 'ClusterAdmin'] as $lib) {
    if (is_file(__DIR__ . "/../../app/lib/crawler/$lib.php")) require_once __DIR__ . "/../../app/lib/crawler/$lib.php";
}
require_once __DIR__ . '/../../app/lib/Suggestions.php';
require_once __DIR__ . '/../../app/lib/SuggestionActions.php';
$csrf = (string) ($_SESSION['csrf_token'] ?? '');
$type = (string) ($_GET['type'] ?? 'all');
$place = (int) ($_GET['place'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) throw new InvalidArgumentException('Your session expired. Reload the page and try again.');
        $id = (int) ($_POST['id'] ?? 0);
        switch ((string) ($_POST['action'] ?? '')) {
            case 'bulk':
                $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($_POST['ids'] ?? ''))))));
                if (!$ids) throw new InvalidArgumentException('Nothing selected.');
                $op = (string) ($_POST['op'] ?? '');
                $names = [];
                $nm = $pdo->prepare('SELECT raw_payload FROM ' . Database::t('crawler_raw_index') . ' WHERE id = ?');
                $name = function (int $id) use ($nm): string { $nm->execute([$id]); $c = json_decode((string) $nm->fetchColumn(), true); return (string) ($c['name'] ?? "#$id"); };
                if ($op === 'approve') {
                    $r = AutoImport::quickApprove($pdo, $ids, (int) $me['id']);
                    foreach ($r['skipped'] as $sid => $why) $names[] = $name((int) $sid) . ' (' . $why . ')';
                    tv_crawler_flash([$r['approved'] > 0, 'Approved ' . $r['approved'] . '.' . ($names ? ' Skipped ' . count($names) . ': ' . implode('; ', array_slice($names, 0, 8)) . (count($names) > 8 ? '; …' : '') : '')]);
                } elseif ($op === 'reject') {
                    $n = 0;
                    foreach ($ids as $rid) { try { IntakeStager::reject($pdo, $rid, 'rejected in Review (bulk)', (int) $me['id']); $n++; } catch (InvalidArgumentException) {} }
                    tv_crawler_flash([true, "Rejected $n."]);
                } elseif ($op === 'recheck' && class_exists('Verify')) {
                    foreach ($ids as $rid) Verify::reset($pdo, $rid);
                    tv_crawler_flash([true, 'Queued ' . count($ids) . ' for another look on the next crawler run.']);
                } else {
                    throw new InvalidArgumentException('Unknown bulk action.');
                }
                break;
            case 'l_approve':
                $r = AutoImport::quickApprove($pdo, [$id], (int) $me['id']);
                if ($r['approved']) tv_crawler_flash([true, 'Approved and published.']);
                else tv_crawler_flash([false, 'Not approved: ' . (reset($r['skipped']) ?: 'unknown reason') . '.']);
                break;
            case 'l_recheck':
                Verify::reset($pdo, $id);
                tv_crawler_flash([true, 'Queued for another second look on the next crawler run.']);
                break;
            case 'l_reject':
                IntakeStager::reject($pdo, $id, 'rejected in Review', (int) $me['id']);
                tv_crawler_flash([true, 'Rejected.']);
                break;
            case 's_set':
                Sources::update($pdo, $id, (string) ($_POST['status'] ?? ''), null);
                tv_crawler_flash([true, 'Saved.']);
                break;
            case 'n_accept':
                $st = $pdo->prepare('SELECT name, aliases FROM cluster_subgroups WHERE id = ?');
                $st->execute([$id]);
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: throw new InvalidArgumentException('That name is gone.');
                Subgroups::accept($pdo, $id, (string) $row['name'], implode(', ', json_decode((string) $row['aliases'], true) ?: []));
                tv_crawler_flash([true, 'Accepted "' . $row['name'] . '".']);
                break;
            case 'n_reject':
                Subgroups::reject($pdo, $id);
                tv_crawler_flash([true, 'Removed. Scans won\'t suggest it again.']);
                break;
            case 't_accept':
            case 't_dismiss':
                Tags::decide($pdo, (string) ($_POST['key'] ?? ''), $_POST['action'] === 't_accept');
                tv_crawler_flash([true, $_POST['action'] === 't_accept' ? 'Added as a topic people can tag.' : 'Dismissed. It stays a plain tag.']);
                break;
            case 'sg_point':   // a visitor's pin becomes the listing's map point (staff decide, so it may replace one)
                require_once __DIR__ . '/../../app/lib/Geocoder.php';
                $sg = $pdo->prepare('SELECT entity_id, fields FROM suggestions WHERE id = ?'); $sg->execute([$id]); $sgr = $sg->fetch(PDO::FETCH_ASSOC);
                $pt = $sgr ? explode(',', (string) ((json_decode((string) $sgr['fields'], true) ?: [])['point'] ?? '')) : [];
                if (!$sgr || !$sgr['entity_id'] || count($pt) !== 2) throw new InvalidArgumentException('That suggestion has no map point for a listing.');
                if (!Geocoder::setPoint($pdo, (int) $sgr['entity_id'], (float) $pt[0], (float) $pt[1], 'staff')) throw new InvalidArgumentException('That point isn\'t in the U.S.');
                try { require_once __DIR__ . '/../../app/lib/SearchIndex.php'; if (SearchIndex::ready($pdo)) SearchIndex::indexListings($pdo, [(int) $sgr['entity_id']]); } catch (Throwable) {}   // the map moves at once
                Suggestions::decide($pdo, $id, 'used', (int) $me['id']);
                tv_crawler_flash([true, 'The listing\'s map point is set. Thank you, visitor.']);
                break;
            case 'sg_closed':   // a visitor said it closed: hidden from the site, kept (decisions/0072)
                $n = SuggestionActions::closed($pdo, $id, (int) $me['id']);
                tv_crawler_flash([true, $n . ' shows as Closed now.']);
                break;
            case 'sg_reopen':   // undo a closure from its done card
                $sg = SuggestionActions::get($pdo, $id);
                if (!$sg || !$sg['entity_id']) throw new InvalidArgumentException('That suggestion has no listing.');
                SuggestionActions::reopen($pdo, (int) $sg['entity_id']);
                tv_crawler_flash([true, 'Shown as open again.']);
                break;
            case 'sg_used':
            case 'sg_dismiss':
                Suggestions::decide($pdo, $id, $_POST['action'] === 'sg_used' ? 'used' : 'dismissed', (int) $me['id']);
                tv_crawler_flash([true, $_POST['action'] === 'sg_used' ? 'Marked used. Thank you, visitor.' : 'Dismissed.']);
                break;
            case 'c_reviewed':
                ClusterAdmin::markReviewed($pdo, $id, (int) ($_POST['hub'] ?? 0));
                tv_crawler_flash([true, 'Marked reviewed.']);
                break;
            default:
                throw new InvalidArgumentException('Unknown action.');
        }
    } catch (InvalidArgumentException $e) {
        tv_crawler_flash([false, $e->getMessage()]);
    }
    $to = (string) $_SERVER['REQUEST_URI'];
    if (str_starts_with((string) ($_POST['action'] ?? ''), 'sg_')) {   // a visitor's suggestion: come back to its result, so it can be checked (decisions/0072)
        $to = preg_replace('/([?&])done=\d+&?/', '$1', $to);
        $to = rtrim($to, '?&') . (str_contains(rtrim($to, '?&'), '?') ? '&' : '?') . 'done=' . (int) ($_POST['id'] ?? 0);
    }
    header('Location: ' . $to, true, 303);
    exit;
}

// ---- What's waiting ----------------------------------------------------------------------------
$items = [];   // each: kind, id, cluster_id, cluster, sort
// Listings: the highest-confidence first. The page holds up to 300 (500 with "show more"); the rest wait their turn,
// and the page says how many there are, so its counts match the badge.
$cap = isset($_GET['more']) ? 500 : 300;
$stagedTotal = 0;
try { $stagedTotal = (int) $pdo->query("SELECT COUNT(*) FROM " . Database::t('crawler_raw_index') . " WHERE status = 'staged' AND payload_type = 'listing'")->fetchColumn(); } catch (Throwable) {}
// The pipeline's pass (decisions/0076): waiting listings matched again, the same business merged, ready ones published.
$autoMerged = 0; $autoPublished = 0;
try { $adv = AutoImport::advance($pdo, 150); $autoMerged = $adv['merged']; $autoPublished = $adv['published']; } catch (Throwable $x) { error_log('review pass: ' . $x->getMessage()); }
if ($autoMerged || $autoPublished) {
    try { $stagedTotal = (int) $pdo->query("SELECT COUNT(*) FROM " . Database::t('crawler_raw_index') . " WHERE status = 'staged' AND payload_type = 'listing'")->fetchColumn(); } catch (Throwable) {}
}
// Only what the pipeline has given up on reaches a person (decisions/0076 §2), grouped by why; the rest is a number.
$working = 0; $whyGroups = []; $why = (string) ($_GET['why'] ?? '');
// while auto-publish is paused, nothing staged leaves on its own, so Review reads a window, newest first: at most
// AutoImport::REVIEW_WINDOW at a time, Show more for the next; the count above stays the true total (#330)
$pausedWindow = AutoImport::pausedWhy() !== null ? AutoImport::REVIEW_WINDOW * (1 + min(50, max(0, (int) ($_GET['more'] ?? 0)))) : 0;
$all = $pdo->query("SELECT r.id, r.raw_payload, r.created_at, r.source_url, r.confidence_score FROM " . Database::t('crawler_raw_index') . " r WHERE r.status = 'staged' AND r.payload_type = 'listing' "
    . ($pausedWindow ? "ORDER BY r.id DESC LIMIT " . $pausedWindow : "ORDER BY r.confidence_score DESC, r.id"))->fetchAll(PDO::FETCH_ASSOC);
foreach ($all as $r) {
    $p = json_decode((string) $r['raw_payload'], true);
    if (!is_array($p)) continue;
    if (!AutoImport::exhausted($p, (string) $r['created_at'])) { $working++; continue; }
    $rs = AutoImport::reason($p);
    $whyGroups[$rs['key']] ??= ['text' => $rs['text'], 'publish' => $rs['publish'], 'ids' => []];
    $whyGroups[$rs['key']]['ids'][] = (int) $r['id'];
    if ($why !== '' && $rs['key'] !== $why) continue;
    if (count(array_filter($items, fn ($x) => in_array($x['kind'], ['listing', 'merge'], true))) >= $cap) continue;
    $r['payload'] = $p; unset($r['raw_payload']);
    $r['eval'] = AutoImport::evaluate($p);
    // each possible match, sorted: the likely same business, or another at the same facility under another name
    $r['rel'] = ['dupe' => [], 'place' => [], 'branch' => []];
    foreach (AutoImport::matchIds($p) as $mid) {
        if (!($l = IntakeStager::existingListing($pdo, $mid))) continue;
        $rel = AutoImport::relation($p, $l);
        if (in_array($rel['kind'], ['other', 'covered'], true)) continue;   // a different business sharing a line or a building (decisions/0076)
        $r['rel'][in_array($rel['kind'], ['place', 'branch'], true) ? $rel['kind'] : 'dupe'][] = ['id' => $mid, 'name' => (string) $l['name'], 'r' => $rel];
    }
    $dup = (bool) $r['rel']['dupe'];
    $items[] = ['kind' => $dup ? 'merge' : 'listing', 'id' => (int) $r['id'], 'row' => $r, 'why' => $rs['key'],
                'cluster_id' => (int) ($p['geo']['cluster']['id'] ?? 0), 'cluster' => ClusterAdmin::displayName((string) ($p['geo']['cluster']['name'] ?? ''))];
}
uasort($whyGroups, fn ($a, $b) => count($b['ids']) <=> count($a['ids']));
if (class_exists('Sources') && Sources::tableExists($pdo)) {
    foreach (Sources::all($pdo, 'suggested') as $s) $items[] = ['kind' => 'source', 'id' => (int) $s['id'], 'row' => $s, 'cluster_id' => 0, 'cluster' => ''];
}
if (class_exists('Subgroups') && Subgroups::tableExists($pdo)) {
    $st = $pdo->query("SELECT s.*, c.name AS cluster_name FROM cluster_subgroups s JOIN micro_clusters c ON c.id = s.cluster_id
                        WHERE s.status = 'suggested' ORDER BY s.mentions DESC, s.name");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $n) {
        $items[] = ['kind' => 'name', 'id' => (int) $n['id'], 'row' => $n, 'cluster_id' => (int) $n['cluster_id'],
                    'cluster' => ClusterAdmin::displayName((string) $n['cluster_name'])];
    }
}
// New @tags several people used (decisions/0051 §3): leads, never who said them.
if (class_exists('Tags')) {
    foreach (Tags::leads($pdo) as $i => $l) $items[] = ['kind' => 'tag', 'id' => 900000 + $i, 'row' => $l, 'cluster_id' => 0, 'cluster' => ''];
}
// What visitors told us (decisions/0053): something missing, a fix, closed, listed twice, story information, feedback.
$sgPlace = [];
foreach (Suggestions::waiting($pdo, 300) as $g) {
    $cid = preg_match('/^cluster:(\d+)$/', (string) $g['place_key'], $m) ? (int) $m[1] : 0;
    if ($cid && !isset($sgPlace[$cid])) {
        try { $st = $pdo->prepare('SELECT name FROM micro_clusters WHERE id = ?'); $st->execute([$cid]); $sgPlace[$cid] = ClusterAdmin::displayName((string) $st->fetchColumn()); } catch (Throwable) { $sgPlace[$cid] = ''; }
    }
    $items[] = ['kind' => 'suggest', 'id' => (int) $g['id'], 'row' => $g, 'cluster_id' => $cid, 'cluster' => $cid ? $sgPlace[$cid] : ''];
}
foreach ($pdo->query("SELECT id, name, geo_hub_id, primary_zip FROM micro_clusters WHERE name LIKE '%(auto-seeded%' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $items[] = ['kind' => 'cluster', 'id' => (int) $c['id'], 'row' => $c, 'cluster_id' => (int) $c['id'], 'cluster' => ClusterAdmin::displayName((string) $c['name'])];
}

$kinds = ['listing' => 'Listings', 'merge' => 'Duplicates?', 'source' => 'Sources', 'name' => 'Local names', 'tag' => 'Tag leads', 'suggest' => 'From visitors', 'cluster' => 'Clusters'];
$counts = array_fill_keys(array_keys($kinds), 0);
$places = [];
foreach ($items as $it) {
    $counts[$it['kind']]++;
    if ($it['cluster_id'] && $it['cluster'] !== '') $places[$it['cluster_id']] = $it['cluster'];
}
asort($places);
$shown = array_values(array_filter($items, fn ($it) => ($type === 'all' || $it['kind'] === $type) && (!$place || $it['cluster_id'] === $place) && ($why === '' || ($it['why'] ?? '') === $why)));

$qs = fn (array $over) => '?' . http_build_query(array_filter(array_merge(['type' => $type === 'all' ? null : $type, 'place' => $place ?: null, 'why' => $why !== '' ? $why : null, 'more' => isset($_GET['more']) ? max(1, (int) $_GET['more']) : null], $over)));
$form = fn (string $action, int $id, string $extra = '') => '<input type="hidden" name="csrf" value="' . tv_e($csrf) . '"><input type="hidden" name="action" value="'
    . $action . '"><input type="hidden" name="id" value="' . $id . '">' . $extra;

tv_crawler_start('review', 'Review', $pdo, 'Everything the crawler needs you for, in one list. Tap a card\'s button: it opens over this page, you decide or fix it, and the card leaves the list. Find any listing to edit it the same way.');
?>
<style>
  .rv-chips{display:flex;gap:.4rem;overflow-x:auto;scrollbar-width:none;padding:.2rem 0;margin-top:.9rem}
  .rv-chips::-webkit-scrollbar{display:none}
  .rv-chip{flex:0 0 auto;font-size:.85rem;padding:.3rem .8rem;border-radius:999px;border:1px solid #CBB98D;background:#FFFBF2;color:#3E2B1F;white-space:nowrap}
  .rv-chip.on{background:#3E2B1F;color:#FFFBF2;border-color:#3E2B1F}
  .rv-chip b{font-weight:600;margin-left:.2rem;opacity:.75}
  .rv-card{background:#F7EFDC;border:1px solid rgba(203,185,141,.7);border-radius:1rem;padding:.75rem .9rem;margin-top:.6rem;box-shadow:0 2px 4px -2px rgba(0,0,0,.08)}
  .rv-top{display:flex;gap:.6rem;align-items:flex-start;justify-content:space-between}
  .rv-name{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:1.05rem;line-height:1.25}
  .rv-sub{font-size:.8rem;color:#8A7355;margin-top:.1rem}
  .rv-kind{font-size:.65rem;text-transform:uppercase;letter-spacing:.06em;color:#8A7355}
  .rv-score{flex:0 0 auto;font-size:.8rem;font-weight:700;border-radius:999px;padding:.1rem .55rem;border:1px solid #CBB98D;background:#FFFBF2}
  .rv-score.hi{background:#DCFCE7;border-color:#86EFAC;color:#14532D}
  .rv-acts{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.55rem;align-items:center}
  .rv-acts form{display:inline}
  .rv-b{font-size:.82rem;font-weight:600;border-radius:.6rem;padding:.35rem .8rem;border:1px solid #CBB98D;background:#FFFBF2;color:#3E2B1F}
  .rv-b.go{background:#15803D;border-color:#15803D;color:#fff}
  .rv-b.no{color:#991B1B;border-color:#FCA5A5}
  .rv-b.am{background:#D97706;border-color:#D97706;color:#fff}
  .rv-b.sel{background:#3E2B1F;border-color:#3E2B1F;color:#FFFBF2}
  .rv-more summary{cursor:pointer;font-size:.8rem;color:#B45309;margin-top:.45rem;list-style:none}
  .rv-more summary::-webkit-details-marker{display:none}
  .rv-more summary::before{content:'▸ ';}
  .rv-more[open] summary::before{content:'▾ ';}
  .rv-ck{font-size:.8rem;margin-top:.3rem}
  .rv-ok{color:#166534}.rv-no{color:#991B1B}
  .rv-ref{font-size:.78rem;color:#B45309;text-decoration:underline;margin-right:.5rem}
  .rv-bar{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin-top:.8rem}
  .rv-lab{font-size:.8rem;color:#8A7355;display:inline-flex;align-items:center;gap:.3rem}
  .rv-pick{flex:0 0 auto;padding:.15rem .1rem 0 0;cursor:pointer}.rv-pick input{width:1.15rem;height:1.15rem;accent-color:#B45309;cursor:pointer}
  .rv-card.rv-picked{outline:2px solid #D97706;outline-offset:-1px}
  .rv-gh{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;margin-top:1.1rem;padding-bottom:.2rem;border-bottom:1px solid #CBB98D;display:flex;align-items:center;gap:.4rem}
  .rv-gh b{font-family:ui-sans-serif,system-ui,sans-serif;font-size:.75rem;font-weight:600;color:#8A7355;margin-left:.3rem}
  .rv-gh input{width:1.05rem;height:1.05rem;accent-color:#B45309;margin-right:.35rem}
  .rv-bulk{position:sticky;bottom:.75rem;z-index:20;display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;margin-top:1rem;background:#3E2B1F;color:#FFFBF2;border-radius:1rem;padding:.6rem .8rem;box-shadow:0 10px 24px -8px rgba(0,0,0,.45);font-size:.9rem}
  .rv-bulk[hidden]{display:none}
  .rv-sel{background:#FFFBF2;border:1px solid #CBB98D;border-radius:.6rem;padding:.3rem .5rem;font-size:.85rem;max-width:100%}
  .rv-find{margin-top:.8rem;background:#F7EFDC;border:1px solid #EADFC4;border-radius:.8rem;padding:.6rem .75rem}
  .rv-find-l{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#8A7355;margin-bottom:.25rem}
  .rv-find #rv-find-q{width:100%;font-size:.95rem;padding:.45rem .65rem}
  .rv-fr{display:flex;align-items:center;gap:.6rem;padding:.45rem .2rem;border-top:1px solid #EADFC4;font-size:.88rem}
  .rv-fr:first-child{border-top:0}.rv-fr .n{flex:1;min-width:0}.rv-fr .n a{color:#92400E;font-weight:700;text-decoration:underline}
  .rv-fr .s{display:block;color:#8A7355;font-size:.78rem}.rv-fr .t{font-size:.68rem;font-weight:700;border-radius:999px;padding:.05rem .45rem;margin-left:.3rem;background:#EADFC4;color:#5C4632}
  .rv-fr .t.bad{background:#FDE2E2;color:#991B1B}
  .rv-why{margin:.7rem 0;padding:.7rem .85rem;border:1px solid #F3D9A4;border-left:4px solid #B45309;border-radius:.6rem;background:#FFF8EA}
  .rv-why-h{font:700 1rem var(--tv-font-head,Georgia,serif);margin:0 0 .3rem}.rv-why-h span{font-weight:400;color:#8A7355}
  .rv-why-g{padding:.5rem 0;border-top:1px solid #F3E2BD;font-size:.88rem}.rv-why-g:first-of-type{border-top:0}
  .rv-why-g.on p{font-weight:600}
  .rv-why-a{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.35rem}.rv-why-a form{display:inline}
  .rv-match{margin:.35rem 0 0;font-size:.84rem;color:#5C4632}.rv-match b{color:#92400E}
  .rv-card.rv-gone{opacity:0;transform:scale(.98);transition:opacity .35s,transform .35s}
  .ta-work .tv-note.tv-bleed{width:auto}   /* a status line drawn as a notice keeps 3px each side at 390px (0058 §11): the notice's own width:100% pushed it 24px off the right edge (#328's before-and-after) */
  .rv-card{min-width:0}.rv-card p,.rv-card a,.rv-card summary,.rv-card dd,.rv-card dt,.rv-why p{overflow-wrap:anywhere}   /* a find's words with no place to break (a web address as its name, an address run together) wrap inside the card: newest first shows such finds, and one pushed the page sideways at 390px (#335's before-and-after) */
</style>

<?php // find any listing (live, hidden, closed or unclaimed) and edit it in a pop-up, without leaving Review (decisions/0074) ?>
<div class="rv-find tv-bleed">
  <label for="rv-find-q" class="rv-find-l">Find any listing to edit</label>
  <input id="rv-find-q" class="rv-sel" type="search" placeholder="Name, phone, address or #id" autocomplete="off">
  <div id="rv-find-out"></div>
</div>
<?php $loadedListings = $counts['listing'] + $counts['merge']; ?>
<?php if ($autoMerged || $autoPublished): ?><p class="tv-bleed" data-tv-status="ok" data-tv-label="Done:"><?php if ($autoMerged): ?><b><?= $autoMerged ?></b> obvious duplicate<?= $autoMerged === 1 ? ' was' : 's were' ?> merged on <?= $autoMerged === 1 ? 'its' : 'their' ?> own (same phone, street address, ZIP and name): only the existing listings' empty fields were filled.<?php endif; ?>
<?php if ($autoPublished): ?><?= $autoMerged ? ' ' : '' ?><b><?= $autoPublished ?></b> listing<?= $autoPublished === 1 ? '' : 's' ?> that now pass<?= $autoPublished === 1 ? 'es' : '' ?> every check <?= $autoPublished === 1 ? 'was' : 'were' ?> published on <?= $autoPublished === 1 ? 'its' : 'their' ?> own, each checked again for duplicates first. <a class="underline" href="/admin/auto-imports.php">See them</a><?php endif; ?></p><?php endif; ?>
<div class="rv-chips" data-more role="tablist">
  <a class="rv-chip <?= $type === 'all' ? 'on' : '' ?>" href="<?= tv_e($qs(['type' => null])) ?>">All<b><?= count($items) ?></b></a>
  <?php foreach ($kinds as $k => $label): if (!$counts[$k]) continue; ?>
    <a class="rv-chip <?= $type === $k ? 'on' : '' ?>" href="<?= tv_e($qs(['type' => $k])) ?>"><?= tv_e($label) ?><b><?= $counts[$k] ?></b></a>
  <?php endforeach; ?>
</div>
<?php $listingView = in_array($type, ['all', 'listing'], true);   // the crawler's listings stay off the other lists (From visitors is people, not the crawler) ?>
<?php if ($working && $listingView): ?><p data-tv-status="info" data-tv-label="Working:">The crawler is still working on <b><?= number_format($working) ?></b> listing<?= $working === 1 ? '' : 's' ?>: looking for more that confirms them, and publishing each one on its own when it's ready. They reach you here only if it runs out of things to try.</p><?php endif; ?>
<?php if ($pausedWindow && $listingView && $stagedTotal > $pausedWindow): ?><p data-tv-status="info" data-tv-label="Paused:">Auto-publish is paused, so finds wait here. Showing the newest <b><?= number_format($pausedWindow) ?></b> of <b><?= number_format($stagedTotal) ?></b> waiting. <a class="underline" href="<?= tv_e($qs(['more' => (int) ($_GET['more'] ?? 0) + 1])) ?>">Show <?= number_format(min(AutoImport::REVIEW_WINDOW, $stagedTotal - $pausedWindow)) ?> more</a></p><?php endif; ?>
<?php if ($whyGroups && $listingView): ?>
<div class="rv-why tv-bleed">
  <p class="rv-why-h">Listings that need you <span>(<?= array_sum(array_map(fn ($g) => count($g['ids']), $whyGroups)) ?>)</span></p>
  <?php foreach ($whyGroups as $wk => $g): $n = count($g['ids']); ?>
  <div class="rv-why-g<?= $why === $wk ? ' on' : '' ?>">
    <p><b><?= number_format($n) ?></b> · <?= tv_e($g['text']) ?>.</p>
    <div class="rv-why-a">
      <a class="rv-b" href="<?= tv_e($qs(['why' => $why === $wk ? null : $wk, 'type' => null])) ?>"><?= $why === $wk ? 'Show all reasons' : 'Show them' ?></a>
      <?php if ($g['publish']): ?><form method="post"><input type="hidden" name="csrf" value="<?= tv_e($csrf) ?>"><input type="hidden" name="action" value="bulk"><input type="hidden" name="op" value="approve"><input type="hidden" name="ids" value="<?= implode(',', $g['ids']) ?>">
        <button class="rv-b go" type="submit" data-confirm="Publish all <?= $n ?>? Each goes live unclaimed, with the category it was given; you can edit or remove any later." data-yes="Publish <?= $n ?>">Publish all <?= $n ?></button></form><?php endif; ?>
      <form method="post"><input type="hidden" name="csrf" value="<?= tv_e($csrf) ?>"><input type="hidden" name="action" value="bulk"><input type="hidden" name="op" value="reject"><input type="hidden" name="ids" value="<?= implode(',', $g['ids']) ?>">
        <button class="rv-b no" type="submit" data-confirm="Leave all <?= $n ?> out? They won't be listed or suggested again." data-yes="Leave them out" data-danger>Leave them out</button></form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php elseif (!$working && $listingView): ?><p data-tv-status="ok" data-tv-label="All clear:">No listings need you. The crawler publishes what's ready on its own.</p>
<?php endif; ?>
<?php if ($places): ?>
<form method="get" class="mt-2 flex items-center gap-2 text-sm">
  <?php if ($type !== 'all'): ?><input type="hidden" name="type" value="<?= tv_e($type) ?>"><?php endif; ?>
  <label for="rv-place" class="text-[#8A7355]">Place</label>
  <select id="rv-place" name="place" class="rv-sel" onchange="this.form.submit()">
    <option value="">Everywhere</option>
    <?php foreach ($places as $pid => $pname): ?><option value="<?= $pid ?>" <?= $pid === $place ? 'selected' : '' ?>><?= tv_e($pname) ?></option><?php endforeach; ?>
  </select>
  <noscript><button class="rv-b" type="submit">Show</button></noscript>
</form>
<?php endif; ?>

<?php // the suggestion just acted on: what's true now, with links to check it (decisions/0072)
if (($doneId = (int) ($_GET['done'] ?? 0)) && ($dn = SuggestionActions::get($pdo, $doneId))):
    $oc = SuggestionActions::outcome($pdo, $dn);
    $dnName = (string) (($dn['fields']['name'] ?? '') ?: ($dn['target_name'] ?: (Suggestions::KINDS[$dn['kind']] ?? 'Suggestion')));
    $close = tv_e(preg_replace('/([?&])done=\d+&?/', '$1', (string) $_SERVER['REQUEST_URI'])); ?>
  <div class="rv-card rv-done mt-4" id="rv-done" style="border-color:<?= $oc['ok'] ? '#8FBCA8' : '#F3D9A4' ?>;box-shadow:0 0 0 2px <?= $oc['ok'] ? 'rgba(143,188,168,.45)' : 'rgba(243,217,164,.6)' ?>">
    <div class="rv-top"><div>
      <p class="rv-kind" style="color:<?= $oc['ok'] ? '#3F7A5E' : '#92400E' ?>"><?= $oc['ok'] ? '✓ Done' : 'Check this' ?> · <?= tv_e(Suggestions::KINDS[$dn['kind']] ?? $dn['kind']) ?></p>
      <p class="rv-name"><?= tv_e($dnName) ?></p>
    </div><a class="rv-b" href="<?= $close ?>" title="Close" aria-label="Close" style="margin-left:auto">×</a></div>
    <?php foreach ($oc['lines'] as $ln): ?><p class="rv-ck" style="overflow-wrap:anywhere"><?= tv_e($ln) ?></p><?php endforeach; ?>
    <div class="rv-acts">
      <?php foreach ($oc['links'] as [$lab, $url]): ?><a class="rv-b" href="<?= tv_e($url) ?>"<?= str_starts_with($url, '/listing/') || str_starts_with($url, '/place/') ? ' data-peek-frame' : '' ?>><?= tv_e($lab) ?></a><?php endforeach; ?>
      <?php if ($oc['restore']): ?><form method="post"><?= $form('sg_reopen', $doneId) ?><button class="rv-b" type="submit">Undo: it's open</button></form><?php endif; ?>
    </div>
  </div>
<?php endif; ?>
<?php if (!$shown): ?>
  <div class="rv-card mt-4"><p class="text-sm"><?= $items ? 'Nothing here with these filters.' : 'Nothing waiting. The crawler will add things here as it works.' ?></p></div>
<?php endif; ?>

<div id="rv-tools" hidden>
  <div class="rv-bar">
    <input id="rv-q" class="rv-sel" type="search" placeholder="Search name, address, category" style="flex:1 1 14rem;min-width:0">
    <label class="rv-lab">Sort <select id="rv-sort" class="rv-sel">
      <option value="score_d">Confidence, high first</option><option value="score_a">Confidence, low first</option>
      <option value="name">Name A–Z</option><option value="place">Place</option><option value="cat">Category</option>
      <option value="new">Newest first</option><option value="old">Oldest first</option></select></label>
    <label class="rv-lab">Group <select id="rv-group" class="rv-sel">
      <option value="">None</option><option value="place">Place</option><option value="cat">Category</option>
      <option value="kind">Type</option><option value="ready">Ready / held back</option></select></label>
    <label class="rv-lab">Show <select id="rv-show" class="rv-sel">
      <option value="">Everything</option><option value="ready">Ready to approve</option><option value="held">Held back</option></select></label>
  </div>
  <p class="rv-sub" style="margin-top:.4rem"><label><input type="checkbox" id="rv-all"> Select all shown listings</label> · <span id="rv-count"></span></p>
</div>
<div id="rv-list">
<?php foreach ($shown as $it): $id = $it['id']; $r = $it['row'];
    $isL = $it['kind'] === 'listing' || $it['kind'] === 'merge';
    $p = $isL ? $r['payload'] : []; $e = $isL ? $r['eval'] : ['checks' => [], 'score' => 0, 'category_name' => ''];
    $failing = array_values(array_filter($e['checks'], fn ($c) => !$c['pass']));
    $label = $isL ? (string) ($p['name'] ?? '') : (string) ($r['host'] ?? $r['name'] ?? $r['label'] ?? $it['cluster']);
    $search = mb_strtolower(implode(' ', [$label, (string) ($p['where']['address'] ?? ''), (string) ($p['where']['city'] ?? ''), (string) $e['category_name'], $it['cluster']])); ?>
  <div class="rv-card" id="<?= $it['kind'] . '-' . $id ?>" data-kind="<?= $it['kind'] ?>" data-id="<?= $id ?>" data-name="<?= tv_e($label) ?>"
       data-score="<?= (int) $e['score'] ?>" data-place="<?= tv_e($it['cluster']) ?>" data-cat="<?= tv_e((string) $e['category_name']) ?>"
       data-ready="<?= $isL ? ($failing || $it['kind'] === 'merge' ? '0' : '1') : '' ?>" data-at="<?= tv_e((string) ($r['created_at'] ?? '')) ?>" data-q="<?= tv_e($search) ?>">
  <?php if ($isL):
      $w = $p['where'] ?? [];
      $addr = trim(implode(', ', array_filter([(string) ($w['address'] ?? ''), (string) ($w['city'] ?? '')])));
      $refs = class_exists('Verify') ? Verify::references($p) : []; ?>
    <div class="rv-top">
      <label class="rv-pick" title="Select"><input type="checkbox" class="rv-ck-sel" value="<?= $id ?>" aria-label="Select <?= tv_e($label) ?>"></label>
      <div style="flex:1;min-width:0">
        <p class="rv-kind"><?= $it['kind'] === 'merge' ? 'Possible duplicate' : 'Listing' ?><?= $it['cluster'] !== '' ? ' · ' . tv_e($it['cluster']) : '' ?></p>
        <p class="rv-name"><?= tv_e((string) ($p['name'] ?? '')) ?></p>
        <p class="rv-sub"><?= tv_e($addr ?: 'No street address') ?><?= $e['category_name'] !== '' ? ' · ' . tv_e($e['category_name']) : '' ?></p>
      </div>
      <span class="rv-score <?= $e['score'] >= 70 ? 'hi' : '' ?>" title="Confidence score (it orders the work; the checks decide)"><?= (int) $e['score'] ?></span>
    </div>
    <?php $rel = ($r['rel'] ?? []) + ['dupe' => [], 'place' => [], 'branch' => []]; $nm = fn ($m) => '<b>' . tv_e($m['name']) . '</b>'; ?>
    <?php if ($rel['dupe']): ?>
      <p class="rv-match">May already be listed as: <?= implode(', ', array_map(fn ($m) => $nm($m) . ' (' . ($m['r']['phone'] ? 'same phone' : 'similar') . ', ' . (int) round($m['r']['name'] * 100) . '% of the name)', array_slice($rel['dupe'], 0, 3))) ?><?= count($rel['dupe']) > 3 ? ' and ' . (count($rel['dupe']) - 3) . ' more' : '' ?></p>
    <?php endif; ?>
    <?php if ($rel['branch']): ?>
      <p class="rv-match" style="color:#3F7A5E">Another location of <?= implode(', ', array_map(fn ($m) => $nm($m) . ' (#' . (int) $m['id'] . ')', array_slice($rel['branch'], 0, 3))) ?>: same name, different street address, so its own listing.</p>
    <?php endif; ?>
    <?php if ($rel['place']): ?>
      <p class="rv-match" style="color:#3F7A5E">Same place as <?= implode(', ', array_map($nm, array_slice($rel['place'], 0, 3))) ?><?= count($rel['place']) > 3 ? ' and ' . (count($rel['place']) - 3) . ' more' : '' ?>: likely providers or offices at one facility, not duplicates.</p>
    <?php endif; ?>
    <?php $failing2 = array_values(array_filter($failing, fn ($c) => !preg_match('/already|duplicate/i', $c['label']))); if ($failing2): ?><p class="rv-ck rv-no">Needs: <?= tv_e(implode('; ', array_map(fn ($c) => lcfirst(preg_replace('/ — .*$/', '', $c['label'])), $failing2))) ?></p><?php endif; ?>
    <div class="rv-acts">
      <?php if ($it['kind'] === 'merge'): ?>
        <a class="rv-b am" href="/admin/review-item.php?row=<?= $id ?>" data-peek-frame="<?= tv_e((string) ($p['name'] ?? '')) ?>">Compare side by side</a>
      <?php else: ?>
        <form method="post"><?= $form('l_approve', $id) ?><button class="rv-b go" type="submit">Approve</button></form>
        <a class="rv-b" href="/admin/review-item.php?row=<?= $id ?>" data-peek-frame="<?= tv_e((string) ($p['name'] ?? '')) ?>">Check and fix</a>
      <?php endif; ?>
      <?php if (class_exists('Verify') && !empty($p['verify']['done_at'])): ?><form method="post"><?= $form('l_recheck', $id) ?><button class="rv-b" type="submit" title="Look it up again: NPI Registry, then web search">Check again</button></form><?php endif; ?>
      <form method="post" data-yes="Reject" data-danger data-confirm="Reject <?= tv_e((string) ($p['name'] ?? '')) ?>?"><?= $form('l_reject', $id) ?><button class="rv-b no" type="submit">Reject</button></form>
    </div>
    <details class="rv-more"><summary>Why, and where it was found</summary>
      <?php foreach ($e['checks'] as $ck): ?><p class="rv-ck <?= $ck['pass'] ? 'rv-ok' : 'rv-no' ?>"><?= $ck['pass'] ? '✓' : '✗' ?> <?= tv_e($ck['label']) ?></p><?php endforeach; ?>
      <p class="rv-ck"><?php foreach ($refs as $ref): ?><a class="rv-ref" href="<?= tv_e($ref['url']) ?>" target="_blank" rel="noopener nofollow"><?= tv_e($ref['kind']) ?>: <?= tv_e($ref['host']) ?></a><?php endforeach; ?></p>
      <?php $v = (array) ($p['verify'] ?? []); if (!empty($v['done_at'])): ?><p class="rv-ck text-[#8A7355]">Second look <?= tv_e(substr((string) $v['done_at'], 0, 10)) ?>: <?= (int) ($v['added'] ?? 0) ? (int) $v['added'] . ' more found' : 'nothing more found' ?></p><?php endif; ?>
    </details>

  <?php elseif ($it['kind'] === 'source'): ?>
    <div class="rv-top">
      <div>
        <p class="rv-kind">Source · <?= tv_e(Sources::KINDS[$r['kind']] ?? $r['kind']) ?></p>
        <p class="rv-name"><a class="underline" href="<?= tv_e((string) ($r['sample_url'] ?: $r['home_url'])) ?>" target="_blank" rel="noopener nofollow"><?= tv_e($r['host']) ?></a></p>
        <p class="rv-sub">Confirmed <?= (int) $r['confirmations'] ?> listing<?= (int) $r['confirmations'] === 1 ? '' : 's' ?><?= $r['last_listing'] ? ', latest ' . tv_e((string) $r['last_listing']) : '' ?></p>
      </div>
    </div>
    <div class="rv-acts">
      <?php foreach (['read' => ['Read regularly', 'go'], 'cite_only' => ['Cite only', ''], 'ignore' => ['Ignore', 'no']] as $st => [$label, $cls]): ?>
        <form method="post"><?= $form('s_set', $id, '<input type="hidden" name="status" value="' . $st . '">') ?><button class="rv-b <?= $cls ?>" type="submit"><?= $label ?></button></form>
      <?php endforeach; ?>
      <a class="rv-b" href="/admin/sources.php">All sources</a>
    </div>

  <?php elseif ($it['kind'] === 'name'):
      $ex = json_decode((string) $r['examples'], true) ?: []; ?>
    <div class="rv-top">
      <div>
        <p class="rv-kind">Local name · <?= tv_e($it['cluster']) ?></p>
        <p class="rv-name"><?= tv_e((string) $r['name']) ?></p>
        <p class="rv-sub">Used at <?= (int) $r['mentions'] ?> address<?= (int) $r['mentions'] === 1 ? '' : 'es' ?></p>
      </div>
    </div>
    <div class="rv-acts">
      <form method="post"><?= $form('n_accept', $id) ?><button class="rv-b go" type="submit">Accept</button></form>
      <form method="post"><?= $form('n_reject', $id) ?><button class="rv-b no" type="submit">Not a local name</button></form>
      <a class="rv-b" href="/admin/cluster-tools.php?view=places#c<?= (int) $r['cluster_id'] ?>">Rename in Places</a>
    </div>
    <?php if ($ex): ?><details class="rv-more"><summary>Where it's used</summary>
      <?php foreach (array_slice($ex, 0, 5) as $x): ?><p class="rv-ck">“<?= tv_e(is_array($x) ? (string) ($x['text'] ?? '') : (string) $x) ?>”<?php if (is_array($x) && !empty($x['entity_id'])): ?> <a class="rv-ref" href="/listing/view.php?id=<?= (int) $x['entity_id'] ?>" target="_blank">listing</a><?php endif; ?></p><?php endforeach; ?>
    </details><?php endif; ?>

  <?php elseif ($it['kind'] === 'tag'): ?>
    <div class="rv-top">
      <div>
        <p class="rv-kind">Tag lead · new @tag</p>
        <p class="rv-name">@<?= tv_e($r['label']) ?></p>
        <p class="rv-sub">Tagged by <?= (int) $r['people'] ?> different people since <?= tv_e($r['since']) ?><?= $r['where'] ? ' · ' . tv_e(implode(', ', $r['where'])) : '' ?></p>
      </div>
    </div>
    <p class="rv-ck text-[#8A7355]">Only the tag is known, never who wrote it or what else they said. It may be a local name for a place, a missing listing, or a topic.</p>
    <div class="rv-acts">
      <form method="post"><?= $form('t_accept', 0, '<input type="hidden" name="key" value="' . tv_e($r['key']) . '">') ?><button class="rv-b go" type="submit">Make it a topic</button></form>
      <a class="rv-b" href="/admin/cluster-tools.php?view=places">Add as a place in Places</a>
      <a class="rv-b" href="/admin/listing-intake.php">Look for a listing</a>
      <form method="post"><?= $form('t_dismiss', 0, '<input type="hidden" name="key" value="' . tv_e($r['key']) . '">') ?><button class="rv-b no" type="submit">Not useful</button></form>
    </div>

  <?php elseif ($it['kind'] === 'suggest'):
      $f = $r['fields']; $site = (string) $r['site_key'];
      $about = (string) ($f['name'] ?? '') ?: ((string) ($r['entity_name'] ?? '') ?: ((string) $r['target_name'] ?: $it['cluster']));
      $topic = Suggestions::TOPICS[$r['topic']] ?? ''; ?>
    <div class="rv-top">
      <div>
        <?php $byGoogle = $r['context'] === 'google'; ?>
        <p class="rv-kind"><?= $byGoogle ? 'From the Google Places check' : 'From a visitor' ?> · <?= tv_e(Suggestions::KINDS[$r['kind']] ?? $r['kind']) ?><?= $r['topic'] !== 'business' && $r['kind'] !== 'story' ? ' · ' . tv_e(strtok($topic, ' (:')) : '' ?><?= $it['cluster'] !== '' ? ' · ' . tv_e($it['cluster']) : '' ?></p>
        <p class="rv-name"><?php $nm = tv_e($about !== '' ? $about : ($r['kind'] === 'feedback' ? 'Search feedback' : 'Suggestion'));
          echo $r['entity_id'] ? '<a href="/listing/view.php?id=' . (int) $r['entity_id'] . '" data-peek-frame style="color:#92400E;text-decoration:underline" title="See the listing">' . $nm . '</a>' : $nm; ?></p>
        <p class="rv-sub"><?= $r['section'] !== '' ? 'Missing from “' . tv_e($r['section']) . '” · ' : '' ?><?= $r['kind'] === 'same' && $r['same_name'] ? 'Same business as “' . tv_e($r['same_name']) . '” · ' : '' ?><?= $byGoogle ? 'Found by the crawler' : ($r['user_id'] ? 'Signed-in member' : 'Guest') ?> · <?= tv_e(date('M j, g:i a', strtotime((string) $r['created_at']))) ?></p>
      </div>
    </div>
    <?php if ($f): ?><dl class="rv-ck" style="display:grid;grid-template-columns:max-content 1fr;gap:.1rem .7rem;margin:.3rem 0">
      <?php foreach ($f as $k => $v): ?><dt class="text-[#8A7355]"><?= tv_e(Suggestions::FIELDS[$k] ?? $k) ?></dt><dd style="margin:0;overflow-wrap:anywhere"><?php if ($k === 'point'): ?><a class="rv-ref" href="https://www.openstreetmap.org/?mlat=<?= tv_e(explode(',', $v)[0]) ?>&amp;mlon=<?= tv_e(explode(',', $v)[1] ?? '') ?>#map=17/<?= tv_e(str_replace(',', '/', $v)) ?>" target="_blank" rel="noopener"><?= tv_icon('pin', 'tv-ei') ?> <?= tv_e($v) ?> (see it on a map)</a><?php else: ?><?= tv_e($v) ?><?php endif; ?></dd><?php endforeach; ?>
    </dl><?php endif; ?>
    <?php if (trim((string) $r['note']) !== ''): ?><p class="rv-ck" style="white-space:pre-line">“<?= tv_e((string) $r['note']) ?>”</p><?php endif; ?>
    <?php if ($r['context'] !== '' && !$byGoogle): ?><p class="rv-ck text-[#8A7355]">Sent from <?= tv_e($r['context']) ?></p><?php endif; ?>
    <?php if ($site !== '' || $r['phone10'] !== ''): ?><p class="rv-ck text-[#8A7355]">Kept as match context: when the crawler finds this <?= $site !== '' ? 'website' : 'phone' ?>, it offers this as a possible match.</p><?php endif; ?>
    <div class="rv-acts">
      <?php // the one-step decision for this kind, first and green (decisions/0072)
      $one = '';
      if ($r['kind'] === 'missing' && in_array($r['topic'], ['business', 'food', 'other'], true) && empty($r['listing_id'])) $one = '<a class="rv-b go" href="/admin/listing-new.php?suggestion=' . (int) $id . '">Create the listing</a>';
      elseif ($r['kind'] === 'missing' && $r['topic'] === 'outdoors') $one = '<a class="rv-b go" href="/admin/suggestion.php?id=' . (int) $id . '">Create the place</a>';
      elseif ($r['kind'] === 'closed' && ($r['entity_id'] || str_starts_with((string) $r['target'], 'rec:'))) $one = '<form method="post" data-yes="Mark it closed" data-confirm="' . tv_e('Mark ' . ($about !== '' ? $about : 'it') . ' closed? It stays on the site, shown as Closed, so people searching see it.') . '">' . $form('sg_closed', $id) . '<button class="rv-b go" type="submit">Mark closed</button></form>'
          . ($r['entity_id'] ? '<a class="rv-b" href="/admin/listing-edit.php?id=' . (int) $r['entity_id'] . '">Moved: edit the address</a>' : '');
      elseif ($r['kind'] === 'fix' && $r['entity_id'] && array_intersect_key($f, SuggestionActions::FIX)) $one = '<a class="rv-b go" href="/admin/suggestion.php?id=' . (int) $id . '">Apply the fix</a>';
      elseif ($r['kind'] === 'same' && $r['entity_id'] && $r['same_as_id']) $one = '<a class="rv-b go" href="/admin/suggestion.php?id=' . (int) $id . '">Merge these</a>';
      echo $one; ?>
      <?php if ($site !== '' && in_array($r['topic'], ['business', 'food', 'other'], true)): ?><a class="rv-b" href="/admin/listing-intake.php?url=<?= tv_e(rawurlencode('https://' . $site)) ?>">Crawl this website</a><?php endif; ?>
      <?php if ($r['entity_id'] && !empty($f['point'])): ?><form method="post"><?= $form('sg_point', $id) ?><button class="rv-b<?= $one === '' ? ' go' : '' ?>" type="submit">Use this map point</button></form><?php endif; ?>
      <?php if ($r['entity_id'] && $r['kind'] !== 'closed'): ?><a class="rv-b" href="/admin/listing-edit.php?id=<?= (int) $r['entity_id'] ?>">Edit the listing</a><?php endif; ?>
      <?php if ($r['same_as_id']): ?><a class="rv-b" href="/admin/listing-edit.php?id=<?= (int) $r['same_as_id'] ?>">Edit the other listing</a><?php endif; ?>
      <?php if (str_starts_with((string) $r['target'], 'rec:')): ?><a class="rv-b" href="/place/recreation.php?id=<?= tv_e(rawurlencode(substr((string) $r['target'], 4))) ?>" target="_blank">Open the place</a><?php endif; ?>
      <?php if ($r['kind'] === 'story' && $r['place_key'] !== ''): ?><a class="rv-b go" href="/admin/stories.php?place=<?= tv_e(rawurlencode((string) $r['place_key'])) ?>">Open the story editor</a><?php endif; ?>
      <?php if ($r['kind'] === 'missing' && $site === '' && $about !== ''): ?><a class="rv-b" href="https://www.google.com/search?q=<?= tv_e(rawurlencode(trim($about . ' ' . ($f['address'] ?? '') . ' ' . ($r['place_key'] !== '' ? $it['cluster'] : '')))) ?>" target="_blank" rel="noopener">Look it up</a><?php endif; ?>
      <form method="post"><?= $form('sg_used', $id) ?><button class="rv-b<?= $one === '' && $site === '' && $r['kind'] !== 'story' ? ' go' : '' ?>" type="submit">Done, used it</button></form>
      <form method="post"><?= $form('sg_dismiss', $id) ?><button class="rv-b no" type="submit">Dismiss</button></form>
    </div>

  <?php else: /* cluster */ ?>
    <div class="rv-top">
      <div>
        <p class="rv-kind">Cluster · auto-seeded</p>
        <p class="rv-name"><?= tv_e($it['cluster']) ?></p>
        <p class="rv-sub">Grouped automatically. Check its towns and anchor, then mark it reviewed.</p>
      </div>
    </div>
    <div class="rv-acts">
      <form method="post"><?= $form('c_reviewed', $id, '<input type="hidden" name="hub" value="' . (int) $r['geo_hub_id'] . '">') ?><button class="rv-b go" type="submit">Looks right</button></form>
      <a class="rv-b" href="/admin/cluster-tools.php?view=places&amp;hub=<?= (int) $r['geo_hub_id'] ?>#c<?= $id ?>">Open in Places</a>
    </div>
  <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>

<form method="post" id="rv-bulk" class="rv-bulk" hidden>
  <input type="hidden" name="csrf" value="<?= tv_e($csrf) ?>"><input type="hidden" name="action" value="bulk"><input type="hidden" name="ids" value=""><input type="hidden" name="op" value="">
  <b id="rv-n">0</b> selected
  <button type="button" class="rv-b go" data-op="approve" title="Duplicates and listings without a category are skipped, with the reason">Approve</button>
  <?php if (class_exists('Verify')): ?><button type="button" class="rv-b" data-op="recheck">Check again</button><?php endif; ?>
  <button type="button" class="rv-b no" data-op="reject">Reject</button>
  <button type="button" class="rv-b" data-op="clear">Clear</button>
</form>

<script>
(function () {
  'use strict';
  var list = document.getElementById('rv-list'), tools = document.getElementById('rv-tools');
  if (!list) return;
  var cards = [].slice.call(list.querySelectorAll('.rv-card[data-kind]'));
  tools.hidden = false;
  var q = document.getElementById('rv-q'), sort = document.getElementById('rv-sort'), group = document.getElementById('rv-group'), show = document.getElementById('rv-show');
  var bulk = document.getElementById('rv-bulk'), all = document.getElementById('rv-all');
  var KIND = { listing: 'Listings', merge: 'Possible duplicates', source: 'Sources', name: 'Local names', tag: 'Tag leads', cluster: 'Clusters' };
  function load() { try { return JSON.parse(localStorage.getItem('tv_rv_view') || '{}'); } catch (x) { return {}; } }
  function save() { try { localStorage.setItem('tv_rv_view', JSON.stringify({ sort: sort.value, group: group.value, show: show.value })); } catch (x) {} }
  var saved = load();
  if (saved.sort) sort.value = saved.sort; if (saved.group !== undefined) group.value = saved.group; if (saved.show) show.value = saved.show;

  function key(c, g) {
    if (g === 'place') return c.dataset.place || 'No place yet';
    if (g === 'cat') return c.dataset.cat || (c.dataset.ready === '' ? 'Other' : 'No category');
    if (g === 'kind') return KIND[c.dataset.kind] || c.dataset.kind;
    if (g === 'ready') return c.dataset.ready === '1' ? 'Ready to approve' : c.dataset.ready === '0' ? 'Held back or possible duplicate' : 'Other';
    return '';
  }
  function cmp(a, b) {
    var s = sort.value, A = a.dataset, B = b.dataset;
    if (s === 'score_d') return B.score - A.score || A.name.localeCompare(B.name);
    if (s === 'score_a') return A.score - B.score || A.name.localeCompare(B.name);
    if (s === 'place') return (A.place || '~').localeCompare(B.place || '~') || A.name.localeCompare(B.name);
    if (s === 'cat') return (A.cat || '~').localeCompare(B.cat || '~') || A.name.localeCompare(B.name);
    if (s === 'new') return (B.at || '').localeCompare(A.at || '');
    if (s === 'old') return (A.at || '').localeCompare(B.at || '');
    return A.name.localeCompare(B.name);
  }
  function visible(c) {
    var t = q.value.trim().toLowerCase();
    if (t && c.dataset.q.indexOf(t) < 0) return false;
    if (show.value === 'ready' && c.dataset.ready !== '1') return false;
    if (show.value === 'held' && c.dataset.ready !== '0') return false;
    return true;
  }
  function render() {
    save();
    var shown = cards.filter(visible).sort(cmp), g = group.value;
    list.innerHTML = '';
    cards.forEach(function (c) { if (shown.indexOf(c) < 0) { c.hidden = true; list.appendChild(c); } });
    var groups = [], by = {};
    shown.forEach(function (c) { var k = key(c, g); if (!by[k]) { by[k] = []; groups.push(k); } by[k].push(c); c.hidden = false; });
    if (g) groups.sort(function (a, b) { return by[b].length - by[a].length || a.localeCompare(b); });
    groups.forEach(function (k) {
      if (g) {
        var h = document.createElement('div'); h.className = 'rv-gh';
        var sel = by[k].some(function (c) { return c.querySelector('.rv-ck-sel'); });
        h.innerHTML = (sel ? '<label><input type="checkbox" data-grp> ' : '<span>') + esc(k) + ' <b>' + by[k].length + '</b>' + (sel ? '</label>' : '</span>');
        h._cards = by[k];
        list.appendChild(h);
      }
      by[k].forEach(function (c) { list.appendChild(c); });
    });
    document.getElementById('rv-count').textContent = shown.length + ' of ' + cards.length + ' shown';
    if (!shown.length) { var p = document.createElement('div'); p.className = 'rv-card'; p.innerHTML = '<p class="text-sm">Nothing matches.</p>'; list.appendChild(p); }
    sync();
  }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function picked() { return cards.filter(function (c) { var b = c.querySelector('.rv-ck-sel'); return b && b.checked; }); }
  function sync() {
    var n = picked().length;
    bulk.hidden = !n; document.getElementById('rv-n').textContent = n;
    var selectable = cards.filter(function (c) { return !c.hidden && c.querySelector('.rv-ck-sel'); });
    all.checked = selectable.length > 0 && selectable.every(function (c) { return c.querySelector('.rv-ck-sel').checked; });
    list.querySelectorAll('.rv-gh').forEach(function (h) {
      var b = h.querySelector('[data-grp]'); if (!b) return;
      var cs = h._cards.filter(function (c) { return c.querySelector('.rv-ck-sel'); });
      b.checked = cs.length > 0 && cs.every(function (c) { return c.querySelector('.rv-ck-sel').checked; });
    });
    cards.forEach(function (c) { var b = c.querySelector('.rv-ck-sel'); c.classList.toggle('rv-picked', !!(b && b.checked)); });
  }
  [q, sort, group, show].forEach(function (x) { x.addEventListener(x === q ? 'input' : 'change', render); });
  all.addEventListener('change', function () {
    cards.forEach(function (c) { var b = c.querySelector('.rv-ck-sel'); if (b && !c.hidden) b.checked = all.checked; }); sync();
  });
  list.addEventListener('change', function (e) {
    if (e.target.matches('[data-grp]')) e.target.closest('.rv-gh')._cards.forEach(function (c) { var b = c.querySelector('.rv-ck-sel'); if (b) b.checked = e.target.checked; });
    sync();
  });
  // Shift-click a checkbox to select everything between it and the last one clicked.
  var last = null;
  list.addEventListener('click', function (e) {
    var b = e.target.closest('.rv-ck-sel'); if (!b) return;
    var boxes = [].slice.call(list.querySelectorAll('.rv-card:not([hidden]) .rv-ck-sel'));
    if (e.shiftKey && last && boxes.indexOf(last) >= 0) {
      var i = boxes.indexOf(last), j = boxes.indexOf(b);
      boxes.slice(Math.min(i, j), Math.max(i, j) + 1).forEach(function (x) { x.checked = b.checked; });
      sync();
    }
    last = b;
  });
  bulk.addEventListener('click', function (e) {
    var b = e.target.closest('[data-op]'); if (!b) return;
    var op = b.getAttribute('data-op'), sel = picked();
    if (op === 'clear') { sel.forEach(function (c) { c.querySelector('.rv-ck-sel').checked = false; }); sync(); return; }
    var dups = sel.filter(function (c) { return c.dataset.kind === 'merge'; }).length;
    var msg = { approve: 'Approve ' + sel.length + ' as they are?' + (dups ? ' ' + dups + ' possible duplicate' + (dups === 1 ? ' is' : 's are') + ' skipped: merge those one by one.' : ''),
                reject: 'Reject ' + sel.length + '?', recheck: 'Look up ' + sel.length + ' again on the next crawler run?' }[op];
    TvAsk.confirm(msg, { anchor: b, yes: { approve: 'Approve', reject: 'Reject', recheck: 'Look again' }[op], danger: op === 'reject' }).then(function (ok) {
      if (!ok) return;
      bulk.ids.value = sel.map(function (c) { return c.dataset.id; }).join(','); bulk.op.value = op; bulk.submit();
    });
  });
  // Work through the list: opening one remembers the order shown, so Listing Intake can offer "Next".
  list.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-open]'); if (!a) return;
    var queue = cards.filter(function (c) { return !c.hidden && (c.dataset.kind === 'listing' || c.dataset.kind === 'merge'); })
      .map(function (c) { return [+c.dataset.id, c.dataset.name, c.dataset.kind]; });
    try { sessionStorage.setItem('tv_rv_queue', JSON.stringify(queue)); sessionStorage.setItem('tv_rv_back', location.pathname + location.search); } catch (x) {}
  });
  render();
  // a pop-up decided this one: the card leaves the list where you are (decisions/0074)
  document.addEventListener('tv-review-done', function (e) {
    var id = e.detail && e.detail.tvReviewDone; if (!id) return;
    ['listing-', 'merge-'].forEach(function (k) {
      var c = document.getElementById(k + id); if (!c) return;
      c.classList.add('rv-gone'); setTimeout(function () { c.remove(); cards = cards.filter(function (x) { return x !== c; }); render(); }, 380);
    });
    if (e.detail.msg) TvAsk.say(e.detail.msg, { bad: false });
  });
})();

// Find any listing: type, and the matches show here; Edit opens it over this page
(function () {
  var q = document.getElementById('rv-find-q'), out = document.getElementById('rv-find-out'); if (!q) return;
  var seq = 0, t = null;
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var run = function () {
    var v = q.value.trim(), my = ++seq;
    if (v.length < 2) { out.innerHTML = ''; return; }
    fetch('/admin/api/find-listing.php?q=' + encodeURIComponent(v), { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) {
      if (my !== seq) return;
      var items = (d && d.items) || [];
      out.innerHTML = items.length ? items.map(function (x) {
        return '<div class="rv-fr"><span class="n"><a href="/listing/view.php?id=' + x.id + '" data-peek-frame="' + esc(x.name) + '">' + esc(x.name) + '</a>'
          + (x.hidden ? '<span class="t bad">hidden</span>' : '') + (x.closed ? '<span class="t bad">closed</span>' : '') + '<span class="t">' + esc(x.status) + '</span>'
          + '<span class="s">#' + x.id + ' · ' + esc([x.category, x.address, x.city].filter(Boolean).join(' · ')) + '</span></span>'
          + '<a class="rv-b go" href="/admin/listing-edit.php?id=' + x.id + '" data-peek-frame="Edit: ' + esc(x.name) + '">Edit</a></div>';
      }).join('') : '<p class="rv-sub" style="margin:.4rem 0 0">No listing matches. <a class="underline" href="/admin/listing-add.php" data-peek-frame="Add a listing">Add one</a></p>';
    });
  };
  q.addEventListener('input', function () { clearTimeout(t); t = setTimeout(run, 220); });
  document.addEventListener('tv-peek-closed', function () { if (q.value.trim().length >= 2) run(); });   // see what you just changed
})();
</script>
<?php tv_crawler_end();
