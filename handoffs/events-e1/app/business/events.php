<?php
// app/business/events.php — the business portal's Events (decisions/0134's minimum, the note of 2026-11-27, option (a)):
// the business's events in one list, each with its state and its moves, and the form that adds or edits one. Free events;
// an event can link one of the business's own published Event tickets items (sold on the Marketplace path). The event's
// public page and its place on the listing come with the next release; until then a published event waits for them.
declare(strict_types=1);

require_once __DIR__ . '/../lib/Events.php';

function tv_biz_events(PDO $main, array $ctx, int $uid): string
{
    $e = 'tv_w_e'; $id = (int) $ctx['id'];
    if (!Events::ready($main)) return '<div class="ak-card tv-bleed"><p data-tv-status="info" data-tv-label="Coming:">Events open once their database update has run (it runs by itself within the hour).</p></div>';
    [$canEdit, $whyEdit] = tv_biz_can($main, $ctx, 'events', 'edit');
    if ($canEdit) [$canEdit, $whyEdit] = Events::canPost($main, $uid, $id);   // a claimed listing, and the role's Catalog & content
    $csrf = (string) ($_SESSION['csrf_token'] ?? '');
    $edit = (int) ($_GET['edit'] ?? 0); $ev = $edit ? Events::get($main, $edit) : null;
    if ($ev && (int) $ev['entity_id'] !== $id) $ev = null;
    $new = isset($_GET['new']) || $ev;
    $all = Events::forHost($main, $id);
    $tickets = Events::ticketItems($main, $id);
    $h = '<div class="ak-card tv-bleed"><p class="ak-note" style="margin:0">Post what\'s happening at your business: a show, a class, a market day, a community night. '
        . 'Events are free to post. To sell tickets, publish an <b>Event tickets</b> item on the Marketplace and link it here; people buy them there, and you\'re paid your usual way.</p>'
        . '<p data-tv-status="info" data-tv-label="Coming:" style="margin:.5rem 0 0">Each published event\'s own page, and its place on your listing, open with the next update.</p></div>';
    if (!$canEdit) $h .= '<p data-tv-status="info" data-tv-label="Can\'t post:" data-tv-keep>' . $e($whyEdit) . '</p>';
    // ---- the form: add or edit (closed until asked for, so the list is the first thing seen)
    if ($canEdit && $new) {
        $v = $ev ?: ['title' => '', 'about' => '', 'kind' => '', 'starts_at' => '', 'ends_at' => '', 'all_day' => 0, 'age' => 'all', 'showtimes' => '', 'ticket_item_id' => null, 'gallery_item_id' => null, 'zone' => Events::zone($main, $id)];
        $ad = (int) $v['all_day'];
        $dt = fn ($x) => $x ? ($ad ? substr((string) $x, 0, 10) : str_replace(' ', 'T', substr((string) $x, 0, 16))) : '';
        $opt = fn (array $all, string $cur) => implode('', array_map(fn ($k, $l) => '<option value="' . $e((string) $k) . '"' . ((string) $k === $cur ? ' selected' : '') . '>' . $e($l) . '</option>', array_keys($all), $all));
        $cover = '';
        if ($v['gallery_item_id']) { require_once __DIR__ . '/../lib/Gallery.php'; $gi = Gallery::item($main, (int) $v['gallery_item_id']); if ($gi) $cover = Gallery::url($gi, 'card'); }
        $h .= '<form class="ak-card tv-bleed ev-f" id="ev-f" data-id="' . (int) ($ev['id'] ?? 0) . '"><h2 class="ak-h" style="margin-top:0">' . ($ev ? 'Edit ' . $e((string) $ev['title']) : 'New event') . '</h2>'
            . '<label class="w">Name<input class="ak-in" name="title" maxlength="120" required data-spell value="' . $e((string) $v['title']) . '" placeholder="Community night at the movies"></label>'
            . '<label>Kind<select class="ak-in" name="kind" required>' . ($v['kind'] === '' ? '<option value="">Choose…</option>' : '') . $opt(Events::KINDS, (string) $v['kind']) . '</select></label>'
            . '<label>Who for<select class="ak-in" name="age">' . $opt(Events::AGES, (string) $v['age']) . '</select></label>'
            . '<label class="ck"><input type="checkbox" name="all_day" value="1"' . ($ad ? ' checked' : '') . '> All day</label>'
            . '<label>Starts<input class="ak-in" name="starts_at" type="' . ($ad ? 'date' : 'datetime-local') . '" required value="' . $e($dt($v['starts_at'])) . '"></label>'
            . '<label>Ends <small>Optional</small><input class="ak-in" name="ends_at" type="' . ($ad ? 'date' : 'datetime-local') . '" value="' . $e($dt($v['ends_at'])) . '"></label>'
            . '<p class="ak-note w" style="margin:0">Times are your business\'s own (' . $e(str_replace('_', ' ', (string) $v['zone'])) . '), shown to everyone with the zone named.</p>'
            . '<label class="w">Description <small>What it is, who it\'s for, what to bring. No links or "pay at the door" notes.</small><textarea class="ak-in" name="about" rows="4" maxlength="2000">' . $e((string) $v['about']) . '</textarea></label>'
            . '<label class="w">Showtimes <small>Optional, information only: for a theater\'s regular showings, say. Shown as written, with no ticket link.</small><textarea class="ak-in" name="showtimes" rows="2" maxlength="600">' . $e((string) $v['showtimes']) . '</textarea></label>'
            . '<label class="w">Tickets <small>' . ($tickets ? 'One of your published Event tickets items, bought on the Marketplace.' : 'You have no published Event tickets items. Publish one in Market 360° → Marketplace to sell tickets; the event can be free without one.') . '</small>'
            . '<select class="ak-in" name="ticket_item_id"><option value="0">None: a free event</option>' . $opt($tickets, (string) ($v['ticket_item_id'] ?? '')) . '</select></label>'
            . '<div class="w ev-cv"><span>Cover photo <small>Optional</small></span><div class="ev-cvb" id="ev-cvb">' . ($cover !== '' ? '<img src="' . $e($cover) . '" alt="">' : '<span class="ev-none">No photo</span>') . '</div>'
            . '<label class="ak-b ev-up">' . ($cover !== '' ? 'Change photo' : 'Add a photo') . '<input type="file" accept="image/jpeg,image/png,image/webp" id="ev-photo" hidden></label>'
            . '<input type="hidden" name="gallery_item_id" value="' . (int) ($v['gallery_item_id'] ?? 0) . '"></div>'
            . '<div class="w ev-btns"><button class="ak-b go">' . ($ev ? 'Save event' : 'Save as a draft') . '</button><a class="ak-b" href="/business/' . $id . '/events">Close</a></div></form>';
    } elseif ($canEdit) $h .= '<p style="margin:.6rem 0"><a class="ak-b go" href="/business/' . $id . '/events?new=1">' . tv_icon('plus') . ' New event</a></p>';
    // ---- the list
    $h .= '<h2 class="ak-h">Your events</h2>';
    if (!$all) $h .= '<p class="ak-note">No events yet.' . ($canEdit ? ' New event starts one.' : '') . '</p>';
    else {
        $h .= '<div class="ak-card tv-bleed ev-list">';
        foreach ($all as $r) {
            $s = (string) $r['status']; $rid = (int) $r['id'];
            $label = ['draft' => ['info', 'Draft:', 'only your team sees it.'], 'published' => ['ok', 'Published:', 'it shows on its page and your listing as they open.'],
                      'cancelled' => ['bad', 'Cancelled:', 'it stays listed as cancelled.'], 'deleted' => ['info', 'Deleted:', 'kept until ' . date('M j', strtotime((string) $r['deleted_at']) + Events::KEEP_DAYS * 86400) . ', then gone.']][$s] ?? ['info', '', ''];
            $past = strtotime((string) ($r['ends_at'] ?: $r['starts_at'])) < time() - 86400;
            $moves = ['draft' => [['publish', 'Publish', '']], 'published' => [['unpublish', 'Unpublish', 'Back to a draft? Only your team will see it; Publish brings it back.'], ['cancel', 'Cancel the event', 'Mark it cancelled? It stays listed as cancelled; Put back on undoes it.']],
                      'cancelled' => [['restore', 'Put back on', '']], 'deleted' => [['undelete', 'Put back', '']]][$s] ?? [];
            if (in_array($s, ['draft', 'cancelled'], true)) $moves[] = ['delete', 'Delete', 'Delete it? It\'s kept ' . Events::KEEP_DAYS . ' days, with Put back.'];
            $h .= '<div class="ev-row" data-ev="' . $rid . '"><div class="ev-top"><a class="ev-t" href="/business/' . $id . '/events?edit=' . $rid . '">' . $e((string) $r['title']) . '</a>'
                . '<small>' . $e(Events::KINDS[$r['kind']] ?? '') . ' · ' . $e(Events::when($r)) . ($r['ticket_item_id'] ? ' · tickets linked' : ' · free') . ($past ? ' · past' : '') . '</small></div>'
                . '<p data-tv-status="' . $label[0] . '" data-tv-label="' . $e($label[1]) . '" data-tv-keep style="margin:.3rem 0">' . $e($label[2]) . '</p>'
                . '<div class="ev-acts">';
            if ($canEdit) {
                foreach ($moves as [$act, $word, $ask]) $h .= '<button type="button" class="ak-b' . ($act === 'publish' || $act === 'restore' ? ' go' : '') . '" data-ev-act="' . $act . '" data-id="' . $rid . '"' . ($ask !== '' ? ' data-ask="' . $e($ask) . '"' . ($act === 'delete' || $act === 'cancel' ? ' data-danger="1"' : '') : '') . '>' . $e($word) . '</button>';
                if ($s !== 'deleted') $h .= '<a class="ev-ic" href="/business/' . $id . '/events?edit=' . $rid . '" title="Edit ' . $e((string) $r['title']) . '" aria-label="Edit ' . $e((string) $r['title']) . '">' . tv_icon('pen') . '</a>';
            }
            $hist = Events::history($main, $rid);
            $h .= '<details class="ev-h"><summary class="ev-ic" title="History of ' . $e((string) $r['title']) . '" aria-label="History of ' . $e((string) $r['title']) . '">' . tv_icon('clock') . '</summary><ul>';
            foreach ($hist as $x) {   // the moment in the zone it was done in, labeled (CLAUDE.md "Times where they happened"); else the venue's
                try { $hz = new DateTimeZone((string) ($x['tz'] ?: $r['zone'] ?: 'America/Phoenix')); } catch (Throwable) { $hz = new DateTimeZone('America/Phoenix'); }
                $hd = (new DateTime((string) $x['at'], new DateTimeZone('UTC')))->setTimezone($hz);
                $h .= '<li><time datetime="' . $e($hd->format('c')) . '">' . $e($hd->format('M j, g:i A T')) . '</time> · ' . $e((string) ($x['who'] ?: 'your team')) . ': '
                . $e($x['field'] === 'created' ? 'added it' : ($x['field'] === 'status' ? 'moved it from ' . strtolower(Events::STATUSES[$x['old_value']] ?? (string) $x['old_value']) : 'changed ' . str_replace('_', ' ', (string) $x['field']) . ($x['old_value'] !== null && $x['old_value'] !== '' ? ' (was "' . mb_strimwidth((string) $x['old_value'], 0, 80, '…') . '")' : ''))) . '</li>'; }
            $h .= '</ul></details></div></div>';
        }
        $h .= '</div>';
    }
    $h .= '<style>.ev-f{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(13rem,100%),1fr));gap:.5rem .8rem}.ev-f label{display:flex;flex-direction:column;gap:.2rem;font-size:.84rem;color:#5C4632;font-weight:600}'
        . '.ev-f label small,.ev-cv small{font-weight:400;color:#8A7355}.ev-f .w,.ev-f h2{grid-column:1/-1}.ev-f label.ck{flex-direction:row;align-items:center;gap:.4rem;align-self:end}'
        . '.ev-cv{display:flex;flex-wrap:wrap;align-items:center;gap:.6rem;font-size:.84rem;color:#5C4632;font-weight:600}.ev-cv>span{width:100%}.ev-cvb{width:160px;height:90px;border-radius:.5rem;overflow:hidden;background:#EFE3C8;border:1px solid #CBB98D;display:flex;align-items:center;justify-content:center}.ev-cvb img{width:100%;height:100%;object-fit:cover}.ev-none{font-weight:400;color:#8A7355;font-size:.8rem}'
        . '.ev-up{cursor:pointer}.ev-btns{display:flex;gap:.5rem;flex-wrap:wrap}'
        . '.ev-row{border-top:1px solid #EADFC4;padding:.7rem 0}.ev-row:first-child{border-top:0}.ev-top{display:flex;flex-wrap:wrap;gap:.2rem .6rem;align-items:baseline}.ev-t{font-weight:700;color:#92400E;text-decoration:underline}.ev-top small{color:#8A7355;font-size:.8rem}'
        . '.ev-acts{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center}.ev-ic{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:999px;border:1px solid #CBB98D;background:#FFFBF2;cursor:pointer;list-style:none}.ev-ic::-webkit-details-marker{display:none}.ev-ic .ta-svg{width:15px;height:15px;color:#B45309}'
        . '.ev-h{position:relative}.ev-h ul{margin:.4rem 0 0;padding-left:1rem;font-size:.8rem;color:#5C4632}.ev-h[open]{flex-basis:100%}</style>';
    if ($canEdit) $h .= '<script>(function () {
  var API = ' . json_encode('/business/api/' . $id . '/events') . ', TOK = ' . json_encode($csrf) . ', BACK = ' . json_encode('/business/' . $id . '/events') . ';
  var tz = ""; try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ""; } catch (x) {}
  function post(b) { b.tz = tz; return fetch(API, { method: "POST", credentials: "include", headers: { "Content-Type": "application/json", "X-CSRF-Token": TOK }, body: JSON.stringify(window.tvSafe ? tvSafe(b) : b) })
    .then(function (r) { return r.json(); }).then(function (d) { if (!d.ok) throw new Error((d.error && d.error.message) || "That didn’t work."); return d; }); }
  document.addEventListener("click", function (e) {
    var b = e.target.closest("[data-ev-act]"); if (!b) return;
    var ask = b.getAttribute("data-ask");
    if (ask && !TvAsk.gate(b, ask, { yes: b.textContent, danger: b.hasAttribute("data-danger") })) return;
    TvAsk.busy(b, true);
    post({ action: b.getAttribute("data-ev-act"), id: +b.getAttribute("data-id") }).then(function (d) { TvAsk.busy(b, false); TvAsk.say(d.message, { anchor: b }); setTimeout(function () { location.reload(); }, 1100); },
      function (x) { TvAsk.busy(b, false); TvAsk.say(x.message, { anchor: b, bad: true }); });
  });
  var f = document.getElementById("ev-f"); if (!f) return;
  f.all_day.addEventListener("change", function () { var t = f.all_day.checked ? "date" : "datetime-local"; ["starts_at", "ends_at"].forEach(function (n) { var v = f[n].value; f[n].type = t; f[n].value = t === "date" ? v.slice(0, 10) : (v && v.length === 10 ? v + "T19:00" : v); }); });
  var up = document.getElementById("ev-photo");
  if (up) up.addEventListener("change", function () { if (!up.files.length) return; var box = document.getElementById("ev-cvb");
    TvUpload.send(up.files, { target: "event_photo", ctx: { b: ' . $id . ' }, el: box, shrink: 2400, csrf: TOK }).then(function (r) { var it = r[0] && r[0].item; if (!it) return;
      f.gallery_item_id.value = it.id; box.innerHTML = "<img alt=\"\" src=\"" + it.card + "\">"; TvAsk.say((r[0].message || "Added.") + " Save the event to keep it.", { anchor: box }); }, function () { up.value = ""; }); });
  f.addEventListener("submit", function (ev) { ev.preventDefault(); var b = f.querySelector("button.go"); TvAsk.busy(b, true);
    var d = { action: "save", id: +f.getAttribute("data-id") }; ["title", "kind", "age", "starts_at", "ends_at", "about", "showtimes", "ticket_item_id", "gallery_item_id"].forEach(function (n) { d[n] = f[n].value; }); d.all_day = f.all_day.checked ? 1 : 0;
    post(d).then(function (r) { TvAsk.busy(b, false); TvAsk.say(r.message, { anchor: b }); setTimeout(function () { location.href = BACK; }, 1100); }, function (x) { TvAsk.busy(b, false); TvAsk.say(x.message, { anchor: b, bad: true }); });
  });
})();</script>';
    return $h;
}

/** Events' actions: save (add or edit), and the moves (publish, unpublish, cancel, restore, delete, undelete). */
function tv_biz_events_act(PDO $main, array $ctx, int $uid, string $act, array $in): string
{
    [$ok, $why] = tv_biz_can($main, $ctx, 'events', 'edit');
    if (!$ok) throw new InvalidArgumentException($why);
    $tz = (string) ($in['tz'] ?? '');
    if ($act === 'save') {
        $id = (int) ($in['id'] ?? 0);
        $new = Events::save($main, $uid, (int) $ctx['id'], $in, $id, $tz);
        return $id ? 'Saved.' : 'Saved as a draft. Publish it from the list when it\'s ready.';
    }
    return Events::act($main, $uid, (int) $ctx['id'], (int) ($in['id'] ?? 0), $act, $tz);
}
