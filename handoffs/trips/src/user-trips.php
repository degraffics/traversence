<?php
// user/trips/index.php: Your trips (decisions/0174 §2, §3, release 2). A member's trips, the newest first: each with its
// stops in order (a stop that can't be shown keeps its place, greyed with why), × to take one out (Undo), Rename, Send to
// Maps (decisions/0174 §4) and Delete (kept 30 days, Put back). A guest sees the one trip kept on this device
// (js/tv-trips.js) and an account offered to keep it. Private: only the owner ever sees a trip. Days, reordering, times and
// the map come with the trip page (release 3); sharing with release 4.
declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8'); // bootstrap defaults to JSON
require_once __DIR__ . '/../../../app/ui/page-shell.php';
require_once __DIR__ . '/../../../app/ui/maps-send.php';
require_once __DIR__ . '/../../../app/lib/Trips.php';

$pdo = Database::connection();
$uid = Auth::currentUserId();
$ready = Trips::ready($pdo);
$mine = $uid && $ready ? Trips::mine($pdo, (int) $uid) : ['trips' => [], 'deleted' => []];
$iso = fn (?string $t) => $t ? date('c', strtotime($t)) : '';

tv_page_start('Your trips', ['no_crumbs' => true]);
?>
<style>
  .tvtr-h{display:flex;flex-wrap:wrap;align-items:baseline;gap:.4rem .8rem;margin:.2rem 0 .6rem}.tvtr-h h1{font:700 1.6rem/1.2 var(--tv-font-head,Georgia,serif);margin:0;color:var(--tv-ink)}
  .tvtr-h p{margin:0;font-size:.85rem;color:var(--tv-meta)}
  .tvtr{background:var(--tv-card-a,#FFFBF2);border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.8rem;padding:.8rem .9rem;margin:0 0 .7rem;scroll-margin-top:5rem}
  .tvtr h2{font:700 1.15rem/1.3 var(--tv-font-head,Georgia,serif);margin:0;color:var(--tv-ink)}
  .tvtr-top{display:flex;align-items:flex-start;gap:.5rem}.tvtr-top h2{flex:1;min-width:0}
  .tvtr-m{font-size:.8rem;color:var(--tv-meta);margin:.15rem 0 .4rem}
  .tvtr-s{margin:.2rem 0 .5rem;padding-left:1.4rem;display:flex;flex-direction:column;gap:.25rem}
  .tvtr-s li{padding:.15rem 2.2rem .15rem 0;position:relative;line-height:1.35}.tvtr-s li a{color:#92400E;font-weight:600;text-decoration:underline}
  .tvtr-s li small{display:block;font-size:.76rem;color:var(--tv-meta)}.tvtr-s li.off b{color:var(--tv-meta);font-weight:600}
  .tvtr-x{position:absolute;right:0;top:.1rem;width:30px;height:30px;border-radius:999px;border:1px solid var(--tv-line-strong,#CBB98D);background:#FFFBF2;color:#92400E;font-size:1rem;line-height:1;cursor:pointer}
  .tvtr-a{display:flex;flex-wrap:wrap;gap:.4rem;margin:.3rem 0 0}
  .tvtr-b{display:inline-flex;align-items:center;gap:.3rem;border:1px solid var(--tv-line-strong,#CBB98D);background:var(--tv-card-a,#FFFBF2);color:var(--tv-ink);border-radius:999px;padding:.32rem .8rem;font:600 .84rem/1.2 inherit;cursor:pointer;text-decoration:none}
  .tvtr-b:hover{border-color:#B45309}.tvtr-b.d{color:#9B2C2C}.tvtr-u{font:inherit;color:#92400E;text-decoration:underline;background:none;border:0;padding:0;cursor:pointer}
  .tvtr-none{font-size:.9rem;color:var(--tv-ink);background:var(--tv-card-a,#FFFBF2);border:1px dashed var(--tv-line-strong,#CBB98D);border-radius:.8rem;padding:.8rem .9rem}
  .tvtr-del summary{cursor:pointer;font-size:.85rem;color:var(--tv-meta);margin:.6rem 0 .4rem}
  @media (max-width:767px){.tvtr,.tvtr-none{border-radius:.5rem;padding:.7rem .75rem}}
</style>
<div class="tvtr-h"><h1>Your trips</h1><p>Private: only you see them. <a class="underline" href="/discovery/?view=plan">Plan a trip</a></p></div>
<?php if (!$ready): ?>
  <p class="tvtr-none" data-tv-status="info" data-tv-label="Coming:">Trips arrive with the next site update. Ask our team at <a class="underline" href="mailto:support@traversence.com">support@traversence.com</a> if this stays.</p>
<?php elseif (!$uid): ?>
  <div data-trips-device></div>
  <p class="tvtr-m" style="margin-top:.6rem">An account keeps as many trips as you like and shows them on any device. <a class="underline" href="/login.php?next=%2Fuser%2Ftrips%2F">Sign in</a> and the trip on this device moves into it.</p>
<?php else: ?>
  <div id="tvtr-claimed"></div>
  <?php if (!$mine['trips']): ?>
    <p class="tvtr-none tv-bleed">No trips yet. On any place or business, tap ⋯ then <b>Add to a trip</b>, or <b>Make it my trip</b> on a ready-made journey. <a class="underline" href="/discovery/">Let's Explore</a></p>
  <?php endif; ?>
  <?php foreach ($mine['trips'] as $t): $stops = Trips::stops($pdo, (int) $t['id']); $ok = array_values(array_filter($stops, fn ($s) => $s['ok'])); $m = tv_maps_attrs($ok, (string) $t['name']); ?>
  <section class="tvtr tv-bleed" id="trip-<?= (int) $t['id'] ?>" data-trip="<?= (int) $t['id'] ?>">
    <div class="tvtr-top"><h2 data-trip-name><?= tv_e($t['name']) ?></h2><button type="button" class="tvtr-b" data-trip-rename title="Rename it">Rename</button></div>
    <p class="tvtr-m"><?= count($stops) ?> stop<?= count($stops) === 1 ? '' : 's' ?> · changed <time datetime="<?= tv_e($iso($t['updated_at'])) ?>" data-tv-time<?= $t['tz'] !== '' ? ' data-tz="' . tv_e($t['tz']) . '"' : '' ?>></time></p>
    <?php if ($stops): ?><ol class="tvtr-s">
      <?php foreach ($stops as $s): ?><li<?= $s['ok'] ? '' : ' class="off"' ?> data-stop="<?= (int) $s['id'] ?>">
        <?php if ($s['ok']): ?><a href="<?= tv_e($s['url']) ?>"<?= $s['kind'] === 'osm' ? ' target="_blank" rel="noopener"' : '' ?>><?= tv_e($s['name']) ?></a><small><?= tv_e($s['sub']) ?></small>
        <?php else: ?><b><?= tv_e($s['name'] !== '' ? $s['name'] : 'A stop') ?></b><small><?= tv_e($s['why']) ?></small><?php endif; ?>
        <button type="button" class="tvtr-x" data-trip-out="<?= (int) $s['id'] ?>" aria-label="Take <?= tv_e($s['name']) ?> out of the trip" title="Take it out">×</button></li>
      <?php endforeach; ?></ol>
    <?php else: ?><p class="tvtr-m">No stops yet: tap ⋯ then <b>Add to a trip</b> on a place or business.</p><?php endif; ?>
    <?php if (array_filter($stops, fn ($s) => $s['ok'] && $s['kind'] === 'osm')): ?><p class="tvtr-m">Places marked from <a class="underline" href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors.</p><?php endif; ?>
    <p class="tvtr-a"><?php if ($m !== ''): ?><?= tv_maps_script() ?><a class="tvtr-b" <?= $m ?> aria-haspopup="dialog">Send to Maps</a><?php endif; ?>
      <button type="button" class="tvtr-b d" data-trip-delete>Delete</button></p>
  </section>
  <?php endforeach; ?>
  <?php if ($mine['deleted']): ?>
  <details class="tvtr-del"><summary>Deleted trips (<?= count($mine['deleted']) ?>), kept <?= Trips::KEEP_DELETED_DAYS ?> days</summary>
    <?php foreach ($mine['deleted'] as $t): ?><p class="tvtr-m" data-trip="<?= (int) $t['id'] ?>"><b><?= tv_e($t['name']) ?></b> · <?= (int) $t['stops'] ?> stops · deleted <time datetime="<?= tv_e($iso($t['deleted_at'])) ?>" data-tv-time></time> · <button type="button" class="tvtr-u" data-trip-undelete>Put back</button></p><?php endforeach; ?>
  </details>
  <?php endif; ?>
<?php endif; ?>
<script src="/js/tv-time.js?v=<?= (int) @filemtime(__DIR__ . '/../../js/tv-time.js') ?>" defer></script>
<script src="/js/tv-trips.js?v=<?= (int) @filemtime(__DIR__ . '/../../js/tv-trips.js') ?>" defer></script>
<script>
(function () {
  var csrf = null;
  function post(body) {
    var go = function (c) { return fetch('/api/trips.php', {method: 'POST', credentials: 'include', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': c}, body: JSON.stringify(window.tvSafe ? tvSafe(body) : body)})
      .then(function (r) { return r.json().then(function (j) { if (!r.ok || j.ok === false) throw j; return j; }); }); };
    if (csrf) return go(csrf);
    return fetch('/api/trips.php', {credentials: 'include'}).then(function (r) { return r.json(); }).then(function (d) { csrf = d.csrf || ''; return go(csrf); });
  }
  function err(e) { return (e && e.message) || 'That didn\'t work: try again.'; }
  document.addEventListener('tv-trips-claimed', function () { location.reload(); });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-trip-out]');
    if (b) {
      var li = b.closest('li'), name = (li.querySelector('a,b') || {}).textContent || 'A stop';
      TvAsk.busy(b, true);
      post({action: 'remove', stop: +b.getAttribute('data-trip-out')}).then(function (r) {
        li.className = 'off'; li.innerHTML = '<b>' + name.replace(/[&<>]/g, '') + '</b><small data-tv-status="info" data-tv-label="Taken out:">Taken out of the trip. <button type="button" class="tvtr-u">Undo</button></small>';
        li.querySelector('.tvtr-u').addEventListener('click', function (ev) {
          TvAsk.busy(ev.currentTarget, true);
          post({action: 'restore', undo: r.undo}).then(function () { location.reload(); }).catch(function (x) { TvAsk.say(err(x), {anchor: li, bad: true}); });
        });
      }).catch(function (x) { TvAsk.busy(b, false); TvAsk.say(err(x), {anchor: b, bad: true}); });
      return;
    }
    b = e.target.closest('[data-trip-rename]');
    if (b) {
      var sec = b.closest('[data-trip]'), h = sec.querySelector('[data-trip-name]');
      TvAsk.ask('Name this trip', h.textContent).then(function (v) {
        if (v == null || !String(v).trim()) return;
        TvAsk.busy(b, true);
        post({action: 'rename', trip: +sec.getAttribute('data-trip'), name: String(v)}).then(function (r) { TvAsk.busy(b, false); h.textContent = r.name; TvAsk.say('Renamed.', {anchor: b}); })
          .catch(function (x) { TvAsk.busy(b, false); TvAsk.say(err(x), {anchor: b, bad: true}); });
      });
      return;
    }
    b = e.target.closest('[data-trip-delete]');
    if (b) {
      var s2 = b.closest('[data-trip]');
      TvAsk.confirm('Delete "' + s2.querySelector('[data-trip-name]').textContent + '"? It\'s kept <?= Trips::KEEP_DELETED_DAYS ?> days under Deleted trips, where you can put it back.', {anchor: b, yes: 'Delete it', danger: true}).then(function (y) {
        if (!y) return;
        TvAsk.busy(b, true);
        post({action: 'delete', trip: +s2.getAttribute('data-trip')}).then(function () { location.reload(); }).catch(function (x) { TvAsk.busy(b, false); TvAsk.say(err(x), {anchor: b, bad: true}); });
      });
      return;
    }
    b = e.target.closest('[data-trip-undelete]');
    if (b) {
      TvAsk.busy(b, true);
      post({action: 'undelete', trip: +b.closest('[data-trip]').getAttribute('data-trip')}).then(function () { location.reload(); }).catch(function (x) { TvAsk.busy(b, false); TvAsk.say(err(x), {anchor: b, bad: true}); });
    }
  });
})();
</script>
<?php tv_page_end();
