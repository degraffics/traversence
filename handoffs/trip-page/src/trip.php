<?php
// user/trips/trip.php?id=: the trip page (decisions/0174 §3, release 3). The owner's only: the stops by day, in order
// (drag, or ↑ ↓ on a phone or keyboard; a day to move to), when each day starts and how long at each stop, every stop
// timed against its hours on the day ("Closed when you'd arrive: opens 11 AM", with a way to move it), outdoor places by
// daylight; the map with numbered pins and the legs between as straight lines, labelled "about", with the miles and time
// per leg and per day. Times are the trip's own zone (its first stop's place), always labelled; a stop in another zone
// shows its own. Send to Maps, Print and Delete; sharing is release 4, routes on our own map release 5.
declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8'); // bootstrap defaults to JSON
require_once __DIR__ . '/../../../app/ui/page-shell.php';
require_once __DIR__ . '/../../../app/ui/maps-send.php';
require_once __DIR__ . '/../../../app/lib/Trips.php';
require_once __DIR__ . '/../../../app/lib/Hours.php';

$pdo = Database::connection();
$uid = Auth::currentUserId();
$id = (int) ($_GET['id'] ?? 0);
if (!$uid) { header('Location: /user/trips/', true, 302); exit; }   // a guest's one trip lives on Your trips (on the device)
$trip = Trips::ready($pdo) ? Trips::own($pdo, (int) $uid, $id) : null;
if (!$trip) {
    http_response_code(404);
    tv_page_start('Trip not found', ['no_crumbs' => true]);
    echo '<div class="max-w-xl"><h1 class="font-serif font-bold text-2xl">Trip not found</h1><p class="text-sm mt-2">It may have been deleted, or it\'s someone else\'s: trips are private. '
       . '<a class="underline" href="/user/trips/">Your trips</a> (deleted ones are kept 30 days there) · <a class="underline" href="mailto:support@traversence.com">Ask our team</a></p></div>';
    tv_page_end(); exit;
}
$stops = Trips::stops($pdo, $id);
$plan = Trips::plan($pdo, $trip, $stops);
$zone = new DateTimeZone($plan['tz']);
$refDate = $trip['starts_on'] ?: (new DateTimeImmutable('now', $zone))->format('Y-m-d');
// "9:00 AM MST": a minute of a day in the trip's zone, labelled; in another zone, that zone's own time
$at = function (int $min, ?string $date, string $tz = '') use ($zone, $refDate): string {
    $d = (new DateTimeImmutable(($date ?: $refDate) . ' 00:00', $zone))->modify('+' . $min . ' minutes');
    if ($tz !== '' && $tz !== $zone->getName()) { try { $d = $d->setTimezone(new DateTimeZone($tz)); } catch (Throwable $e) {} }
    return $d->format('g:i A T');
};
$span = function (float $m): string { $m = (int) round($m); return $m < 60 ? $m . ' min' : intdiv($m, 60) . ' h' . ($m % 60 ? ' ' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT) . ' min' : ''); };
$nDays = count($plan['days']);
$okStops = array_values(array_filter($stops, fn ($s) => $s['ok']));
$maps = tv_maps_attrs($okStops, (string) $trip['name']);
$pins = []; $n = 0;
foreach ($plan['days'] as $d) foreach ($d['stops'] as $s) if ($s['ok']) $pins[] = ['n' => ++$n, 'day' => $d['day'], 'lat' => (float) $s['lat'], 'lon' => (float) $s['lon'], 'name' => $s['name'], 'at' => $s['arrive'] !== null ? $at((int) $s['arrive'], $d['date']) : ''];
$journeys = [];
if (!$stops && $trip['place_key'] !== '') { try { require_once __DIR__ . '/../../../app/lib/JourneyPlan.php'; $journeys = array_slice(JourneyPlan::forPlace($pdo, (string) $trip['place_key']), 0, 6); } catch (Throwable $e) {} }
$times = []; for ($m = 300; $m <= 780; $m += 30) $times[$m] = Hours::clock($m);
$stayLabel = fn (int $m) => $m < 60 ? $m . ' min' : ($m % 60 ? ($m / 60) . ' hours' : ($m === 60 ? '1 hour' : ($m / 60) . ' hours'));
$hasOsm = (bool) array_filter($okStops, fn ($s) => $s['kind'] === 'osm');

tv_page_start((string) $trip['name'], ['no_crumbs' => true]);
?>
<style>
  .tp-h{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem .7rem;margin:.1rem 0 .3rem}.tp-h h1{font:700 1.6rem/1.2 var(--tv-font-head,Georgia,serif);margin:0;color:var(--tv-ink);flex:1 1 14rem;min-width:0;overflow-wrap:anywhere}
  .tp-b{display:inline-flex;align-items:center;gap:.3rem;border:1px solid var(--tv-line-strong,#CBB98D);background:var(--tv-card-a,#FFFBF2);color:var(--tv-ink);border-radius:999px;padding:.32rem .8rem;font:600 .84rem/1.2 inherit;cursor:pointer;text-decoration:none;min-height:34px}
  .tp-b:hover{border-color:#B45309}.tp-b.d{color:#9B2C2C}
  .tp-set{display:flex;flex-wrap:wrap;gap:.5rem .9rem;align-items:flex-end;background:var(--tv-card-a,#FFFBF2);border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.8rem;padding:.6rem .8rem;margin:.5rem 0}
  .tp-set label{display:flex;flex-direction:column;font-size:.75rem;font-weight:600;color:var(--tv-meta);gap:.15rem}.tp-set input,.tp-set select{font:inherit;font-size:.9rem;color:var(--tv-ink);border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.45rem;padding:.3rem .45rem;background:#fff;min-height:34px}
  .tp-acts{display:flex;flex-wrap:wrap;gap:.4rem;margin:.4rem 0 .6rem}
  .tp-grid{display:grid;gap:.7rem}@media (max-width:1023px){.tp-mapw{order:-1}}@media (min-width:1024px){.tp-grid{grid-template-columns:minmax(0,1fr) minmax(0,26rem)}.tp-mapw{position:sticky;top:5rem;align-self:start}}
  #tp-map{height:340px;border-radius:.8rem;border:1px solid var(--tv-line-strong,#CBB98D);background:#e8e2d0}
  .tp-pin{position:relative}.tp-pin span{display:block;width:26px;height:26px;border-radius:999px 999px 999px 2px;transform:rotate(-45deg);background:#B45309;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35)}
  .tp-pin b{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font:700 11px/1 system-ui}
  .tp-day{background:var(--tv-card-a,#FFFBF2);border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.8rem;padding:.7rem .8rem;margin:0 0 .7rem}
  .tp-day h2{font:700 1.1rem/1.3 var(--tv-font-head,Georgia,serif);margin:0;color:var(--tv-ink)}.tp-dm{font-size:.8rem;color:var(--tv-meta);margin:.1rem 0 .4rem}
  .tp-ol{list-style:none;margin:0;padding:0;min-height:2.2rem}
  .tp-s{display:grid;grid-template-columns:auto 1fr auto;gap:.2rem .55rem;align-items:start;padding:.45rem 0;border-top:1px solid rgba(203,185,141,.6)}.tp-s:first-child{border-top:0}
  .tp-s.drag{opacity:.45}.tp-ol.over{outline:2px dashed #8FBCA8;outline-offset:2px;border-radius:.4rem}
  .tp-n{width:26px;height:26px;border-radius:999px;background:#B45309;color:#fff;font:700 .75rem/26px system-ui;text-align:center}
  .tp-s.off .tp-n{background:#CBB98D}
  .tp-tm{font-weight:700;font-size:.85rem;color:var(--tv-ink);font-variant-numeric:tabular-nums}
  .tp-nm a{color:#92400E;font-weight:600;text-decoration:underline}.tp-nm b{color:var(--tv-meta)}.tp-nm small{display:block;font-size:.76rem;color:var(--tv-meta)}
  .tp-st{font-size:.78rem;margin:.15rem 0 0}.tp-st.bad{color:#9B2C2C;font-weight:600}.tp-st button{font:inherit;font-weight:600;color:#92400E;text-decoration:underline;background:none;border:0;padding:0;cursor:pointer;margin-left:.3rem}
  .tp-ctl{display:flex;flex-wrap:wrap;gap:.25rem;justify-content:flex-end;align-items:center}
  .tp-ctl button,.tp-ctl select{min-width:30px;height:30px;border-radius:999px;border:1px solid var(--tv-line-strong,#CBB98D);background:#FFFBF2;color:#92400E;font:600 .8rem/1 inherit;cursor:pointer;padding:0 .4rem}
  .tp-ctl button:disabled{opacity:.4;cursor:default}
  .tp-stay{font:inherit;font-size:.76rem;border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.4rem;background:#fff;color:var(--tv-ink);padding:.1rem .25rem;margin-top:.2rem}
  .tp-leg{font-size:.76rem;color:var(--tv-meta);padding:.1rem 0 .1rem 2.2rem}
  .tp-tot{font-size:.8rem;color:var(--tv-meta);margin:.4rem 0 0;border-top:1px solid rgba(203,185,141,.6);padding-top:.35rem}
  .tp-note{font-size:.76rem;color:var(--tv-meta);margin:.4rem 0}
  .tp-js{list-style:none;padding:0;margin:.3rem 0;display:flex;flex-direction:column;gap:.35rem}.tp-js li{display:flex;flex-wrap:wrap;gap:.3rem .6rem;align-items:center;border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.5rem;padding:.45rem .6rem;background:#FFFBF2}
  .tp-js b{flex:1 1 12rem}.tp-pt{display:none}
  @media (max-width:767px){.tp-day,.tp-set{border-radius:.5rem;padding:.6rem .55rem}#tp-map{height:260px;border-radius:.5rem}.tp-s{grid-template-columns:auto 1fr}.tp-ctl{grid-column:1/-1;justify-content:flex-start;padding-left:2.2rem}}
  @media print{header,nav,aside,.tv-shell-rail,.tp-acts,.tp-ctl,.tp-set,.tp-b,.tp-s button,.tp-stay,#tp-map,.tp-mapw,[data-trips-js]{display:none!important}.tp-pt{display:inline}.tp-grid{display:block}.tp-day{break-inside:avoid;border:0;padding:0}}
</style>
<div class="tp-h"><h1 data-trip-name><?= tv_e($trip['name']) ?></h1><button type="button" class="tp-b" data-tp-rename>Rename</button></div>
<p class="tp-dm"><a class="underline" href="/user/trips/">Your trips</a> · private: only you see it · <?= count($stops) ?> stop<?= count($stops) === 1 ? '' : 's' ?><?= $plan['minutes'] > 0 ? ' · about ' . tv_e($span($plan['minutes'])) . ' of travel, ' . tv_e(number_format($plan['miles'], 1)) . ' mi' : '' ?></p>
<form class="tp-set tv-bleed" data-tp-set onsubmit="return false">
  <label>Starts<input type="date" name="starts_on" value="<?= tv_e((string) $trip['starts_on']) ?>"></label>
  <label>Days<select name="days"><?php for ($i = 1; $i <= Trips::MAX_DAYS; $i++): ?><option value="<?= $i ?>"<?= $i === $nDays ? ' selected' : '' ?>><?= $i ?></option><?php endfor; ?></select></label>
  <label>Each day starts<select name="start_min"><?php foreach ($times as $m => $t): ?><option value="<?= $m ?>"<?= $m === (int) $trip['start_min'] ? ' selected' : '' ?>><?= tv_e($t) ?></option><?php endforeach; ?></select></label>
  <p class="tp-note" style="margin:0;flex:1 1 12rem">Times are <?= tv_e((new DateTimeImmutable($refDate, $zone))->format('T')) ?>, the time at the trip's first stop.<?= $trip['starts_on'] ? '' : ' Pick a date to check each stop\'s hours on the day.' ?></p>
</form>
<div class="tp-acts">
  <?php if ($maps !== ''): ?><?= tv_maps_script() ?><a class="tp-b" <?= $maps ?> aria-haspopup="dialog">Send to Maps</a><?php endif; ?>
  <button type="button" class="tp-b" data-tp-print>Print</button>
  <button type="button" class="tp-b d" data-tp-delete>Delete</button>
</div>
<div class="tp-grid">
  <div>
  <?php if (!$stops): ?>
    <div class="tp-day tv-bleed"><h2>No stops yet</h2><p class="tp-dm">On a place or business, tap ⋯ then <b>Add to a trip</b>.<?= $journeys ? ' Or start from a ready-made journey for this place:' : ' <a class="underline" href="/discovery/">Let\'s Explore</a>' ?></p>
    <?php if ($journeys): ?><ul class="tp-js"><?php foreach ($journeys as $j): ?><li><b><?= tv_e($j['title']) ?></b><span class="tp-dm" style="margin:0"><?= count((array) $j['stops']) ?> stops</span><button type="button" class="tp-b" data-tp-journey="<?= (int) $j['id'] ?>">Use these stops</button></li><?php endforeach; ?></ul><?php endif; ?></div>
  <?php endif; ?>
  <?php $num = 0; foreach ($plan['days'] as $d): $date = $d['date'] ? new DateTimeImmutable($d['date'], $zone) : null; ?>
    <?php if (!$stops) break; ?>
    <section class="tp-day tv-bleed" data-day="<?= (int) $d['day'] ?>">
      <h2>Day <?= (int) $d['day'] ?><?= $date ? ' · ' . tv_e($date->format('D, M j')) : '' ?></h2>
      <p class="tp-dm">Starts <?= tv_e($at((int) $d['start'], $d['date'])) ?></p>
      <ol class="tp-ol" data-tp-day="<?= (int) $d['day'] ?>">
      <?php $i = 0; foreach ($d['stops'] as $s): $first = $i === 0; $last = $i === count($d['stops']) - 1; ?>
        <?php if ($s['ok'] && !empty($s['leg'])): ?><li class="tp-leg" aria-hidden="true">about <?= tv_e($span((float) $s['leg']['minutes'])) ?> <?= $s['leg']['mode'] === 'walk' ? 'walk' : 'drive' ?> · <?= tv_e(number_format((float) $s['leg']['miles'], 1)) ?> mi</li><?php endif; ?>
        <li class="tp-s<?= $s['ok'] ? '' : ' off' ?>" draggable="true" data-stop="<?= (int) $s['id'] ?>" data-pos="<?= $i ?>">
          <span class="tp-n" aria-hidden="true"><?= $s['ok'] ? ++$num : '–' ?></span>
          <div>
            <?php if ($s['ok']): ?><span class="tp-tm"><?= tv_e($at((int) $s['arrive'], $d['date'], (string) $s['tz'])) ?><?= $s['tz'] !== '' && $s['tz'] !== $plan['tz'] ? ' there' : '' ?></span>
              <div class="tp-nm"><a href="<?= tv_e($s['url']) ?>"<?= $s['kind'] === 'osm' ? ' target="_blank" rel="noopener"' : '' ?>><?= tv_e($s['name']) ?></a><small><?= tv_e($s['sub']) ?></small></div>
              <?php if ($s['hours_note'] !== ''): ?><p class="tp-st<?= $s['open'] === false ? ' bad' : '' ?>"<?= $s['open'] === false ? ' data-tv-status="bad" data-tv-label="Heads up:"' : '' ?>><?= tv_e($s['hours_note']) ?><?php if ($s['open'] === false && $d['day'] < Trips::MAX_DAYS): ?><button type="button" data-tp-move="<?= (int) $s['id'] ?>" data-to-day="<?= (int) $d['day'] + 1 ?>">Move to day <?= (int) $d['day'] + 1 ?></button><?php endif; ?></p><?php endif; ?>
              <label class="tp-dm" style="display:block;margin:0">Stay <select class="tp-stay" data-tp-stay="<?= (int) $s['id'] ?>" aria-label="How long at <?= tv_e($s['name']) ?>"><?php foreach (Trips::STAYS as $m): ?><option value="<?= $m ?>"<?= $m === (int) $s['stay_min'] ? ' selected' : '' ?>><?= tv_e($stayLabel($m)) ?></option><?php endforeach; ?></select><span class="tp-pt"> <?= tv_e($stayLabel((int) $s['stay_min'])) ?></span></label>
            <?php else: ?><div class="tp-nm"><b><?= tv_e($s['name'] !== '' ? $s['name'] : 'A stop') ?></b><small><?= tv_e($s['why']) ?></small></div><?php endif; ?>
          </div>
          <div class="tp-ctl">
            <button type="button" data-tp-up="<?= (int) $s['id'] ?>" aria-label="Move <?= tv_e($s['name']) ?> up" title="Move up"<?= $first && $d['day'] === 1 ? ' disabled' : '' ?>>↑</button>
            <button type="button" data-tp-down="<?= (int) $s['id'] ?>" aria-label="Move <?= tv_e($s['name']) ?> down" title="Move down"<?= $last && $d['day'] === $nDays ? ' disabled' : '' ?>>↓</button>
            <?php if ($nDays > 1): ?><select data-tp-day-of="<?= (int) $s['id'] ?>" aria-label="Day for <?= tv_e($s['name']) ?>"><?php for ($k = 1; $k <= $nDays; $k++): ?><option value="<?= $k ?>"<?= $k === (int) $d['day'] ? ' selected' : '' ?>>Day <?= $k ?></option><?php endfor; ?></select><?php endif; ?>
            <button type="button" data-tp-out="<?= (int) $s['id'] ?>" aria-label="Take <?= tv_e($s['name']) ?> out of the trip" title="Take it out">×</button>
          </div>
        </li>
      <?php $i++; endforeach; ?>
      </ol>
      <?php if (!$d['stops']): ?><p class="tp-dm">Nothing on this day yet: drag a stop here, or pick Day <?= (int) $d['day'] ?> beside it.</p><?php endif; ?>
      <?php if ($d['ends'] !== null): ?><p class="tp-tot">About <?= tv_e($span($d['minutes'])) ?> of travel, <?= tv_e(number_format($d['miles'], 1)) ?> mi · done about <?= tv_e($at((int) $d['ends'], $d['date'])) ?></p><?php endif; ?>
    </section>
  <?php endforeach; ?>
  <p class="tp-note">Travel times between stops are straight-line estimates ("about"); routes on our own map are coming. <?= $hasOsm ? 'Places marked from <a class="underline" href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors. ' : '' ?>Check hours before you go.</p>
  </div>
  <?php if ($pins): ?><div class="tp-mapw tv-bleed"><div id="tp-map" role="region" aria-label="The trip on a map" data-pins="<?= tv_e(json_encode($pins, JSON_UNESCAPED_UNICODE)) ?>"></div><p class="tp-note">Numbered in order; lines are straight, not roads.</p></div><?php endif; ?>
</div>
<script src="/js/tv-trips.js?v=<?= (int) @filemtime(__DIR__ . '/../../js/tv-trips.js') ?>" defer></script>
<script>
(function () {
  var TRIP = <?= (int) $id ?>, csrf = null;
  function post(body, btn) {
    if (btn && window.TvAsk) TvAsk.busy(btn, true);
    var go = function (c) { return fetch('/api/trips.php', {method: 'POST', credentials: 'include', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': c}, body: JSON.stringify(window.tvSafe ? tvSafe(body) : body)})
      .then(function (r) { return r.json().then(function (j) { if (!r.ok || j.ok === false) throw j; return j; }); }); };
    var p = csrf ? go(csrf) : fetch('/api/trips.php', {credentials: 'include'}).then(function (r) { return r.json(); }).then(function (d) { csrf = d.csrf || ''; return go(csrf); });
    return p.catch(function (e) { if (btn && window.TvAsk) { TvAsk.busy(btn, false); TvAsk.say((e && e.message) || 'That didn\'t work: try again.', {anchor: btn, bad: true}); } throw e; });
  }
  function again() { sessionStorage.setItem('tp-y', String(window.scrollY)); location.reload(); }
  try { var y = sessionStorage.getItem('tp-y'); if (y) { sessionStorage.removeItem('tp-y'); window.addEventListener('load', function () { window.scrollTo(0, +y); }); } } catch (x) {}
  function moveTo(id, day, pos, btn) { return post({action: 'move', stop: id, day: day, position: pos}, btn).then(again); }
  var days = [].slice.call(document.querySelectorAll('[data-tp-day]'));
  function where(li) { var ol = li.closest('[data-tp-day]'); return {day: +ol.getAttribute('data-tp-day'), pos: [].indexOf.call(ol.querySelectorAll('.tp-s'), li), n: ol.querySelectorAll('.tp-s').length}; }
  document.addEventListener('click', function (e) {
    var b;
    if ((b = e.target.closest('[data-tp-up]')) || (b = e.target.closest('[data-tp-down]'))) {
      var li = b.closest('.tp-s'), w = where(li), up = b.hasAttribute('data-tp-up'), id = +li.getAttribute('data-stop');
      if (up) { if (w.pos > 0) moveTo(id, w.day, w.pos - 1, b); else if (w.day > 1) moveTo(id, w.day - 1, 999, b); }
      else { if (w.pos < w.n - 1) moveTo(id, w.day, w.pos + 1, b); else if (w.day < days.length) moveTo(id, w.day + 1, 0, b); }
      return;
    }
    if ((b = e.target.closest('[data-tp-move]'))) { moveTo(+b.getAttribute('data-tp-move'), +b.getAttribute('data-to-day'), 0, b); return; }
    if ((b = e.target.closest('[data-tp-out]'))) {
      var li2 = b.closest('.tp-s'), nm = (li2.querySelector('.tp-nm a, .tp-nm b') || {}).textContent || 'A stop';
      post({action: 'remove', stop: +b.getAttribute('data-tp-out')}, b).then(function (r) {
        li2.className = 'tp-s off'; li2.removeAttribute('draggable');
        li2.innerHTML = '<span class="tp-n">–</span><div class="tp-nm"><b></b><small data-tv-status="info" data-tv-label="Taken out:">Taken out of the trip. <button type="button" class="tp-undo" style="font:inherit;color:#92400E;text-decoration:underline;background:none;border:0;padding:0;cursor:pointer">Undo</button></small></div>';
        li2.querySelector('b').textContent = nm;
        li2.querySelector('.tp-undo').addEventListener('click', function (ev) { post({action: 'restore', undo: r.undo}, ev.currentTarget).then(again); });
      });
      return;
    }
    if ((b = e.target.closest('[data-tp-journey]'))) { post({action: 'use_journey', trip: TRIP, id: +b.getAttribute('data-tp-journey')}, b).then(again); return; }
    if ((b = e.target.closest('[data-tp-print]'))) { window.print(); return; }
    if ((b = e.target.closest('[data-tp-rename]'))) {
      var h = document.querySelector('[data-trip-name]');
      TvAsk.ask('Name this trip', h.textContent).then(function (v) { if (v == null || !String(v).trim()) return; post({action: 'rename', trip: TRIP, name: String(v)}, b).then(function (r) { TvAsk.busy(b, false); h.textContent = r.name; document.title = r.name; TvAsk.say('Renamed.', {anchor: b}); }); });
      return;
    }
    if ((b = e.target.closest('[data-tp-delete]'))) {
      TvAsk.confirm('Delete this trip? It\'s kept 30 days under Deleted trips on Your trips, where you can put it back.', {anchor: b, yes: 'Delete it', danger: true}).then(function (y) {
        if (y) post({action: 'delete', trip: TRIP}, b).then(function () { location.href = '/user/trips/'; });
      });
    }
  });
  document.addEventListener('change', function (e) {
    var t = e.target;
    if (t.matches('[data-tp-stay]')) { post({action: 'stay', stop: +t.getAttribute('data-tp-stay'), minutes: +t.value}, t).then(again); return; }
    if (t.matches('[data-tp-day-of]')) { moveTo(+t.getAttribute('data-tp-day-of'), +t.value, 999, t); return; }
    if (t.closest('[data-tp-set]')) { var o = {action: 'configure', trip: TRIP}; o[t.name] = t.name === 'starts_on' ? t.value : +t.value; post(o, t).then(again); }
  });
  // drag to reorder, or onto another day (a phone or keyboard uses ↑ ↓ and the day)
  var dragged = null;
  document.addEventListener('dragstart', function (e) { var li = e.target.closest && e.target.closest('.tp-s[draggable]'); if (!li) return; dragged = li; li.classList.add('drag'); try { e.dataTransfer.setData('text/plain', li.getAttribute('data-stop')); e.dataTransfer.effectAllowed = 'move'; } catch (x) {} });
  document.addEventListener('dragend', function () { if (dragged) dragged.classList.remove('drag'); dragged = null; days.forEach(function (o) { o.classList.remove('over'); }); });
  days.forEach(function (ol) {
    ol.addEventListener('dragover', function (e) { if (!dragged) return; e.preventDefault(); ol.classList.add('over'); });
    ol.addEventListener('dragleave', function () { ol.classList.remove('over'); });
    ol.addEventListener('drop', function (e) {
      if (!dragged) return; e.preventDefault();
      var items = [].slice.call(ol.querySelectorAll('.tp-s')).filter(function (x) { return x !== dragged; }), pos = items.length;
      for (var i = 0; i < items.length; i++) { var r = items[i].getBoundingClientRect(); if (e.clientY < r.top + r.height / 2) { pos = i; break; } }
      moveTo(+dragged.getAttribute('data-stop'), +ol.getAttribute('data-tp-day'), pos);
    });
  });
  // the map: numbered pins, straight lines between, one colour a day
  var el = document.getElementById('tp-map'); if (!el) return;
  var pins = JSON.parse(el.getAttribute('data-pins') || '[]');
  function leaflet() {
    if (window.L) return Promise.resolve();
    var cdn = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/';
    return new Promise(function (res, rej) {
      var c = document.createElement('link'); c.rel = 'stylesheet'; c.href = cdn + 'leaflet.min.css'; document.head.appendChild(c);
      var s = document.createElement('script'); s.src = cdn + 'leaflet.min.js'; s.onload = res; s.onerror = function () { rej(new Error('The map could not load.')); }; document.head.appendChild(s);
    });
  }
  leaflet().then(function () {
    var map = L.map(el, {scrollWheelZoom: false});
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors', referrerPolicy: 'strict-origin-when-cross-origin'}).addTo(map);
    var COL = ['#B45309', '#2F6F57', '#6B4E9B', '#9B2C2C', '#1F5F8B'], byDay = {};
    pins.forEach(function (p) {
      (byDay[p.day] = byDay[p.day] || []).push([p.lat, p.lon]);
      var m = L.marker([p.lat, p.lon], {title: p.name, icon: L.divIcon({className: 'tp-pin', html: '<span style="background:' + COL[(p.day - 1) % COL.length] + '"></span><b>' + p.n + '</b>', iconSize: [26, 26], iconAnchor: [13, 26]})}).addTo(map);
      var pop = document.createElement('div'); pop.innerHTML = '<b></b><br><small></small>'; pop.querySelector('b').textContent = p.n + '. ' + p.name; pop.querySelector('small').textContent = 'Day ' + p.day + (p.at ? ' · ' + p.at : '');
      m.bindPopup(pop);
    });
    Object.keys(byDay).forEach(function (d) { if (byDay[d].length > 1) L.polyline(byDay[d], {color: COL[(d - 1) % COL.length], weight: 3, dashArray: '6 6', opacity: .85}).addTo(map); });
    var all = pins.map(function (p) { return [p.lat, p.lon]; });
    if (all.length === 1) map.setView(all[0], 12); else map.fitBounds(all, {padding: [28, 28]});
  }).catch(function (x) { el.innerHTML = '<p data-tv-status="info" data-tv-label="Map:" style="padding:.8rem">' + x.message + ' The list has every stop.</p>'; });
})();
</script>
<?php tv_page_end();
