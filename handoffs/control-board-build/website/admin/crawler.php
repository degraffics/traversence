<?php
// admin/crawler.php — Admin → Crawler → Overview (decisions/0046)
//
// At a glance: is the worker running, how far along the crawl queue is, what went live, and what's
// waiting for you. Read-only; each number links to the page where you act on it.

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8'); // bootstrap defaults to JSON
require_once __DIR__ . '/../../app/ui/admin-crawler.php';

$pdo = Database::connection();
tv_crawler_require_admin($pdo);
require_once __DIR__ . '/../../app/lib/crawler/AutoImport.php';
require_once __DIR__ . '/../../app/lib/crawler/NpiBulk.php';
if (is_file(__DIR__ . '/../../app/lib/crawler/CrawlQueue.php')) require_once __DIR__ . '/../../app/lib/crawler/CrawlQueue.php';

// "Try them all again now": failed jobs back in the queue with fresh tries (Jason, 2026-10-14: "I have no idea how to fix these")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'retry_failed' && hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
    $n = 0;
    try { $n = CrawlQueue::requeueFailed($pdo); } catch (Throwable) {}
    tv_crawler_flash([true, $n . ' failed job' . ($n === 1 ? '' : 's') . ' back in the queue. The worker picks them up on its next runs (5 at a time) once it\'s running.']);
    header('Location: /admin/crawler.php' . (!empty($_GET['embed']) ? '?embed=1' : ''), true, 303);
    exit;
}

$one = function (string $sql, array $args = []) use ($pdo) {
    try { $st = $pdo->prepare($sql); $st->execute($args); return $st->fetchColumn(); } catch (Throwable) { return null; }
};
$hasQueue = class_exists('CrawlQueue') && CrawlQueue::tableExists($pdo);
$q = $hasQueue ? CrawlQueue::stats($pdo) : ['queued' => 0, 'leased' => 0, 'done' => 0, 'failed' => 0];
$total = array_sum($q);
$lastSeen = $hasQueue ? $one("SELECT MAX(updated_at) FROM " . Database::t('crawl_jobs') . " WHERE status <> 'queued' OR attempts > 0") : null;
// the worker checks in every run, jobs or not (its web search and Google check reports): its latest word counts too, so an
// empty queue never reads as "Quiet" (Jason, 2026-11-01: the card said 9 h while Railway showed a run every 5 minutes)
if (!class_exists('SearchIndex') && is_file(__DIR__ . '/../../app/lib/SearchIndex.php')) require_once __DIR__ . '/../../app/lib/SearchIndex.php';
if (class_exists('SearchIndex') && SearchIndex::ready($pdo)) {
    $hb = max((int) SearchIndex::state($pdo, 'websearch_seen'), (int) SearchIndex::state($pdo, 'places_seen'));
    if ($hb > 0 && (!$lastSeen || $hb > strtotime((string) $lastSeen))) $lastSeen = date('Y-m-d H:i:s', $hb);
}
$ago = $lastSeen ? time() - strtotime((string) $lastSeen) : null;
$agoText = $ago === null ? 'not yet' : ($ago < 90 ? 'just now' : ($ago < 5400 ? round($ago / 60) . ' min ago' : ($ago < 172800 ? round($ago / 3600) . ' h ago' : round($ago / 86400) . ' days ago')));
$healthy = $ago !== null && $ago < 20 * 60;
$paused = $hasQueue && CrawlQueue::paused();

$autoNote = AutoImport::NOTE_PREFIX . '%';
$pub24 = (int) $one("SELECT COUNT(*) FROM " . Database::t('crawler_raw_index') . " WHERE status = 'published' AND review_note LIKE ? AND reviewed_at >= NOW() - INTERVAL 1 DAY", [$autoNote]);
$pub7 = (int) $one("SELECT COUNT(*) FROM " . Database::t('crawler_raw_index') . " WHERE status = 'published' AND review_note LIKE ? AND reviewed_at >= NOW() - INTERVAL 7 DAY", [$autoNote]);
$byYou7 = (int) $one("SELECT COUNT(*) FROM " . Database::t('crawler_raw_index') . " WHERE status = 'published' AND reviewed_by_user_id IS NOT NULL AND reviewed_at >= NOW() - INTERVAL 7 DAY");
$waiting = tv_crawler_waiting($pdo);
$failed = [];
if ($hasQueue) {
    $st = $pdo->query("SELECT j.id, j.group_label, j.result, j.updated_at, c.name FROM " . Database::t('crawl_jobs') . " j LEFT JOIN micro_clusters c ON c.id = j.cluster_id
                        WHERE j.status = 'failed' ORDER BY j.updated_at DESC LIMIT 5");
    $failed = $st->fetchAll(PDO::FETCH_ASSOC);
    // a situation gap's job (decisions/0061, batch 3) shows its need, not its key
    foreach ($failed as &$f0) if (str_starts_with((string) $f0['group_label'], 'sit:')) {
        try { $g0 = $pdo->prepare('SELECT need_label FROM situation_gaps WHERE need_key = ? LIMIT 1'); $g0->execute([substr((string) $f0['group_label'], 4)]); $f0['group_label'] = (string) ($g0->fetchColumn() ?: 'A situation need'); } catch (Throwable) {}
    }
    unset($f0);
}

// Bulk data (decisions/0048): the monthly NPI Registry and IRS nonprofit loads.
$bulk = [];
foreach ([['NPI Registry', 'npi_loads', 'npi_orgs', 'health-care organizations'], ['IRS nonprofits', 'irs_loads', 'irs_orgs', 'exempt organizations'],
          ['Recreation.gov', 'rec_loads', 'rec_places', 'public recreation places']] as [$bl, $lt, $ot, $what]) {
    $last = null; $running = null; $n = null;
    try {
        $last = $pdo->query("SELECT finished_at, rows_seen FROM " . Database::t($lt) . " WHERE finished_at IS NOT NULL ORDER BY finished_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;
        $running = $pdo->query("SELECT started_at FROM " . Database::t($lt) . " WHERE finished_at IS NULL ORDER BY id DESC LIMIT 1")->fetchColumn() ?: null;
        $n = (int) $pdo->query('SELECT COUNT(*) FROM ' . Database::t($ot))->fetchColumn();
    } catch (Throwable) { $n = null; }
    $bulk[] = [$bl, $what, $last, $running, $n];
}

tv_crawler_start('overview', 'Overview', $pdo);
?>
<style>
  .ov-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(210px,100%),1fr));gap:.7rem;margin-top:1rem}
  .ov-card{display:block;background:#F7EFDC;border:1px solid rgba(203,185,141,.7);border-radius:1rem;padding:.8rem .95rem;box-shadow:0 2px 4px -2px rgba(0,0,0,.08)}
  a.ov-card:hover{border-color:#B8863B}
  .ov-l{font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:#8A7355}
  .ov-n{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:1.6rem;line-height:1.15;margin-top:.15rem}
  .ov-s{font-size:.8rem;color:#5C4632;margin-top:.15rem}
  .ov-bar{height:8px;border-radius:999px;background:#E3D3AC;overflow:hidden;margin-top:.45rem;display:flex}
  .ov-bar span{display:block;height:100%}
  .ov-dot{display:inline-block;width:.6rem;height:.6rem;border-radius:999px;margin-right:.35rem;vertical-align:middle}
</style>

<div class="ov-grid">
  <div class="ov-card">
    <p class="ov-l">Worker</p>
    <p class="ov-n"><span class="ov-dot" style="background:<?= $paused ? '#D97706' : ($healthy ? '#22C55E' : '#DC2626') ?>"></span><?= $paused ? 'Paused' : ($healthy ? 'Running' : 'Quiet') ?></p>
    <p class="ov-s">Last heard from <?= tv_e($agoText) ?><?= $paused ? ' · CRAWL_QUEUE=off in .env' : '' ?></p>
    <?php if (!$paused && !$healthy && $ago !== null): ?><p class="ov-s">It should check in on each scheduled run (Start the system sets the schedule). <a class="underline text-[#92400E]" href="#fix-worker">What to do</a></p><?php endif; ?>
  </div>

  <a class="ov-card" href="/admin/cluster-tools.php?view=coverage">
    <p class="ov-l">Crawl queue</p>
    <p class="ov-n"><?= $q['done'] ?> <span class="text-base font-normal text-[#8A7355]">of <?= $total ?> done</span></p>
    <?php if ($total): ?>
    <div class="ov-bar" aria-hidden="true">
      <span style="width:<?= 100 * $q['done'] / $total ?>%;background:#22C55E"></span>
      <span style="width:<?= 100 * $q['leased'] / $total ?>%;background:#B8863B"></span>
      <span style="width:<?= 100 * $q['failed'] / $total ?>%;background:#DC2626"></span>
    </div>
    <?php endif; ?>
    <p class="ov-s"><?= $q['queued'] ?> waiting · <?= $q['leased'] ?> in progress · <?= $q['failed'] ?> failed</p>
  </a>

  <a class="ov-card" href="/admin/auto-imports.php">
    <p class="ov-l">Published by the crawler</p>
    <p class="ov-n"><?= $pub24 ?> <span class="text-base font-normal text-[#8A7355]">today</span></p>
    <p class="ov-s"><?= $pub7 ?> this week · <?= $byYou7 ?> approved by you</p>
    <p class="ov-s">Auto-import is <?= AutoImport::enabled() ? 'on' : '<strong>off</strong>' ?></p>
  </a>

  <a class="ov-card" href="/admin/crawler-review.php">
    <p class="ov-l">Waiting for you</p>
    <p class="ov-n"><?= array_sum($waiting) ?></p>
    <p class="ov-s"><?= $waiting['listings'] ?> listings · <?= $waiting['sources'] ?> sources · <?= $waiting['names'] ?> local names · <?= $waiting['clusters'] ?> clusters</p>
  </a>
</div>

<div class="ov-card mt-4">
  <p class="ov-l">Bulk data (monthly)</p>
  <?php foreach ($bulk as [$bl, $what, $last, $running, $n]): ?>
  <p class="ov-s mt-2"><strong><?= tv_e($bl) ?>:</strong>
    <?php if ($bl === 'NPI Registry' && ($npiOff = NpiBulk::off($pdo)) !== null): ?><?= tv_e(lcfirst($npiOff)) ?><?= $n ? ' · ' . number_format($n) . ' ' . tv_e($what) . ' kept for confirming listings' : '' ?>
    <?php elseif ($n === null): ?>not set up yet (run its SQL)
    <?php elseif ($running && (!$last || $running > $last['finished_at'])): ?>loading now or stopped; started <?= tv_e(substr((string) $running, 0, 16)) ?> (retried after 6 hours; see Railway's logs)
    <?php elseif ($last): ?><?= number_format($n) ?> <?= tv_e($what) ?> · last loaded <?= tv_e(substr((string) $last['finished_at'], 0, 10)) ?>
    <?php else: ?>waiting for the worker's first load
    <?php endif; ?></p>
  <?php endforeach; ?>
  <p class="ov-s mt-2 text-[#8A7355]">Used to confirm listings (the NPI Registry also suggests new health-care places in our towns). The IRS list is never shown or turned into listings. Recreation.gov places show on place pages: <a class="underline" href="/admin/recreation.php">review or hide them</a>. Universal search looks through the <a class="underline" href="/admin/search-index.php">search index</a>.</p>
</div>

<?php if (!$paused && !$healthy && $ago !== null): ?>
<div class="ov-card mt-4" id="fix-worker" style="border-color:#F3D9A4;background:#FFF8EA">
  <p class="ov-l">The crawler worker isn't running: how to fix it</p>
  <p class="ov-s mt-1">The worker is the small program on <b>Railway</b> (not Bluehost) that does the crawling on the schedule Start the system sets: finding places, checking listings, retrying failed jobs. It last checked in <?= tv_e($agoText) ?>. Nothing on this website needs changing; the fix is on Railway.</p>
  <ol class="ov-s mt-2" style="list-style:decimal;padding-left:1.2rem;line-height:1.6">
    <li>Sign in at <a class="underline text-[#92400E]" href="https://railway.com/dashboard" target="_blank" rel="noopener">railway.com</a> and open the Traversence project, then the <b>crawler</b> service.</li>
    <li>Look at the top of the page or under <b>Settings → Plan/Usage</b>: if it says your trial ended or credit ran out, add a payment method. This is the most common reason it stops.</li>
    <li>Open <b>Deployments</b>. If the latest one says <b>Failed</b> or <b>Crashed</b>, press <b>⋯ → Redeploy</b>.</li>
    <li>Leave its <b>Cron Schedule</b> alone: <b>Start the system</b> on the <a class="underline text-[#92400E]" href="/admin/config/control">System Control Board</a> sets it, and Shut down in order or Pause everything removes it. Check <b>Variables</b> still has <code>TRAVERSENCE_URL</code> and <code>CRAWLER_API_TOKEN</code> (the same token as in this site's .env).</li>
    <li>If the board says the system is stopped, that's why nothing runs: press <b>Start the system</b> there. Otherwise wait for its next run and reload this page: <b>Worker</b> should say <b>Running</b>. If not, open the latest run's <b>Logs</b> on Railway and send the last lines to support@traversence.com.</li>
  </ol>
</div>
<?php endif; ?>
<?php if ($failed): ?>
<div class="ov-card mt-4">
  <p class="ov-l">Failed jobs (<?= (int) $q['failed'] ?>)</p>
  <?php
  // what went wrong, in plain words; the technical message stays one tap away
  $why = function (string $err): string {
      if (str_contains($err, 'OpenStreetMap')) return 'OpenStreetMap\'s servers refused or were too busy to answer. Nothing is wrong with the job; it needs another try.';
      if (preg_match('/site .* said 40[36]/', $err)) return 'This website\'s firewall (Bluehost) refused the worker\'s report. It usually clears up; try again.';
      if (stripos($err, 'timed out') !== false || stripos($err, 'timeout') !== false) return 'It took too long and was stopped. Try again.';
      return 'Something went wrong while it ran. Try again; if it keeps failing, send me the technical details.';
  };
  foreach ($failed as $f): $err = json_decode((string) $f['result'], true)['error'] ?? ''; ?>
    <?php $again = CrawlQueue::retryAt($f); ?>
    <div class="ov-s mt-2"><strong><?= tv_e($f['group_label']) ?></strong><?= trim((string) $f['name']) !== '' ? ' in ' . tv_e(preg_replace('/\s*\(auto-seeded[^)]*\)/i', '', (string) $f['name'])) : '' ?>
      <span class="text-[#8A7355]">· failed <?= tv_e(date('M j', strtotime((string) $f['updated_at']))) ?> · <?= $again ? 'tries again by itself ' . tv_e(date('M j, g:ia', $again)) : 'no more automatic tries' ?></span><br>
      <?= tv_e($why((string) $err)) ?>
      <details><summary class="text-xs text-[#8A7355] cursor-pointer">Technical details</summary><span class="text-xs text-[#8A7355]" style="word-break:break-all"><?= tv_e(mb_strimwidth((string) $err, 0, 600, '…')) ?></span></details></div>
  <?php endforeach; ?>
  <form method="post" class="mt-3" data-confirm="Put all <?= (int) $q['failed'] ?> failed jobs back in the queue now?" data-yes="Try again">
    <input type="hidden" name="csrf" value="<?= tv_e((string) ($_SESSION['csrf_token'] ?? '')) ?>"><input type="hidden" name="do" value="retry_failed">
    <button type="submit" class="ov-btn" style="font-weight:700;border-radius:.6rem;padding:.45rem .9rem;background:#3E2B1F;color:#FFF8EA">Try them all again now</button>
  </form>
  <p class="ov-s mt-2">They also try again by themselves (after 6 hours, a day, then 3 days), but only while the worker is running<?= !$healthy ? ': fix that first (above)' : '' ?>.</p>
</div>
<?php endif; ?>
<?php tv_crawler_end();
