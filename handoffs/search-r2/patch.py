# Search release 2 (Frontend & Shop): places and honest hours. Applied on a copy of the live files laid out as the mirror
# (sync-app/…, sync-storage/…). Re-run on the sync after IT's release 1 is live: every anchor is asserted, so a moved
# line stops the build instead of landing wrong.
#   python3 patch.py <root>
import sys, re
R = sys.argv[1].rstrip('/')
def rd(p): return open(R + '/' + p, encoding='utf-8').read()
def wr(p, s): open(R + '/' + p, 'w', encoding='utf-8').write(s)
def rep(s, old, new, what, count=1):
    n = s.count(old)
    assert n == count, f'{what}: anchor found {n} times, wanted {count}'
    return s.replace(old, new)

# ---- Hours: the zone table's one home, and Hours::now (decisions/0181's note: honest hours; #1618) --------------------
p = 'sync-app/lib/Hours.php'; s = rd(p)
s = rep(s, """    /** Open at some point on this day? null when unknown. */""",
"""    /** Each state's zone (moved here from JourneyPlan, its one home since 2026-11-27, so hours read without the journeys). */
    public const STATE_TZ = ['AZ' => 'America/Phoenix', 'CA' => 'America/Los_Angeles', 'NV' => 'America/Los_Angeles', 'OR' => 'America/Los_Angeles', 'WA' => 'America/Los_Angeles',
        'NM' => 'America/Denver', 'UT' => 'America/Denver', 'CO' => 'America/Denver', 'WY' => 'America/Denver', 'MT' => 'America/Denver', 'ID' => 'America/Boise',
        'TX' => 'America/Chicago', 'OK' => 'America/Chicago', 'KS' => 'America/Chicago', 'NE' => 'America/Chicago', 'SD' => 'America/Chicago', 'ND' => 'America/Chicago',
        'MN' => 'America/Chicago', 'IA' => 'America/Chicago', 'MO' => 'America/Chicago', 'AR' => 'America/Chicago', 'LA' => 'America/Chicago', 'WI' => 'America/Chicago',
        'IL' => 'America/Chicago', 'MS' => 'America/Chicago', 'AL' => 'America/Chicago', 'TN' => 'America/Chicago', 'AK' => 'America/Anchorage', 'HI' => 'Pacific/Honolulu'];

    /** The zone a place keeps its hours in: its state's, else by its longitude (as JourneyPlan has timed stops since 0174). */
    public static function zone(string $state, ?float $lon = null): string
    {
        $st = strtoupper(trim($state));
        if (isset(self::STATE_TZ[$st])) return self::STATE_TZ[$st];
        $lon = $lon ?? -100.0;
        return $lon < -114.5 ? 'America/Los_Angeles' : ($lon < -102 ? 'America/Denver' : ($lon < -87 ? 'America/Chicago' : 'America/New_York'));
    }

    /**
     * Where it stands now, in the place's own zone (decisions/0181's note, 2026-11-27: honest hours): status()'s answer at
     * this moment there, plus 'known'. With no hours to read, open null and the text "Hours not known", never a guess.
     * Search's rows, the listing page and the path checker all read this one answer.
     */
    public static function now(?array $week, string $tz, ?int $at = null): array
    {
        try { $d = (new DateTimeImmutable('@' . ($at ?? time())))->setTimezone(new DateTimeZone($tz !== '' ? $tz : 'America/Phoenix')); }
        catch (Throwable $e) { $d = new DateTimeImmutable('@' . ($at ?? time())); }
        $s = self::status($week, (int) $d->format('w'), (int) $d->format('G') * 60 + (int) $d->format('i'));
        return $s['text'] === '' ? ['open' => null, 'text' => 'Hours not known', 'known' => false] : $s + ['known' => true];
    }

    /** Open at some point on this day? null when unknown. */""", 'Hours.now')
wr(p, s)

# "24/7" read as open all day (the listing audit's F4): line 73 already gives it every day; the open-all-day test missed /7
p = 'sync-app/lib/Hours.php'; s = rd(p)
s = rep(s, """            if (preg_match('/24\\s*(hours|hrs)|open\\s*24/', $low))""", """            if (preg_match('/24\\s*(hours|hrs|\\/\\s*7)|open\\s*24/', $low))""", 'Hours.247')
wr(p, s)

p = 'sync-app/lib/JourneyPlan.php'; s = rd(p)
s = re.sub(r"    private const STATE_TZ = \[.*?\];\n", "", s, count=1, flags=re.S)
assert 'STATE_TZ = [' not in s, 'JourneyPlan STATE_TZ'
s = rep(s, """        $st = strtoupper(trim((string) ($p['state'] ?? '')));
        if (isset(self::STATE_TZ[$st])) return self::STATE_TZ[$st];
        $lon = (float) ($p['lon'] ?? -100);
        return $lon < -114.5 ? 'America/Los_Angeles' : ($lon < -102 ? 'America/Denver' : ($lon < -87 ? 'America/Chicago' : 'America/New_York'));""",
"""        return Hours::zone((string) ($p['state'] ?? ''), isset($p['lon']) && $p['lon'] !== null ? (float) $p['lon'] : null);   // the table's home is Hours""", 'JourneyPlan.timezone')
wr(p, s)

# ---- SearchIndex: open and its words in the listing's own zone, on every listing row --------------------------------
p = 'sync-app/lib/SearchIndex.php'; s = rd(p)
s = rep(s, """            $open = null;
            if ($when && $r['kind'] === 'listing') {
                $wk = $r['hours'] ? Hours::parse((string) $r['hours']) : null;
                if (isset($when['now'])) $open = Hours::openAt($wk, (int) $when['now'][0], (int) $when['now'][1]);""",
"""            $open = null; $hrs = '';
            if ($r['kind'] === 'listing') {   // every listing row says where it stands now, in its own zone (0181's note: honest hours)
                $wk = $r['hours'] ? Hours::parse((string) $r['hours']) : null;
                $hn = Hours::now($wk, Hours::zone((string) ($r['state'] ?? ''), $r['lon'] !== null ? (float) $r['lon'] : null));
                $hrs = $hn['text'];
            }
            if ($when && $r['kind'] === 'listing') {
                if (isset($when['now'])) $open = $hn['open'];   // open now where the listing is, never by the visitor's clock""", 'SearchIndex.open')
s = rep(s, """                      'open' => $open, 'miles' => $miles, 'peek' =>""", """                      'open' => $open, 'hours_now' => $hrs, 'miles' => $miles, 'peek' =>""", 'SearchIndex.out')
wr(p, s)

# ---- Situations: open in the listing's zone; "Open 24 hours" only from the hours themselves -------------------------
p = 'sync-app/lib/Situations.php'; s = rd(p)
s = rep(s, """            $always = (bool) preg_match('/24\\s*(h|hours|\\/7)|open 24/i', $hoursTxt);
            $open = null;
            if ($always) $open = true;
            elseif ($hoursTxt !== '' && class_exists('Hours')) { try { $open = Hours::openAt(Hours::parse($hoursTxt), (int) $dow, (int) $min); } catch (Throwable) {} }""",
"""            // where it stands now in its own zone (Hours::now); "Open 24 hours" only when its hours say so, never from a
            // "24 hour nurse line" in the words (0181's note: honest hours)
            $hn = ['open' => null, 'text' => 'Hours not known'];
            if ($r['kind'] === 'listing' && class_exists('Hours')) { try { $hn = Hours::now($hoursTxt !== '' ? Hours::parse($hoursTxt) : null, Hours::zone((string) ($r['state'] ?? ''), $r['lon'] !== null ? (float) $r['lon'] : null)); } catch (Throwable) {} }
            $always = $hn['text'] === 'Open 24 hours';
            $open = $hn['open'];""", 'Situations.always')
s = rep(s, """'open' => $open, 'always' => $always,""", """'open' => $open, 'always' => $always, 'hours_now' => $r['kind'] === 'listing' ? $hn['text'] : '',""", 'Situations.out')
wr(p, s)

# ---- the search panel: the row's hours, and "Where are you?" for everyday needs ------------------------------------
p = 'sync-storage/js/search-panel.js'; s = rd(p)
s = rep(s, """  function saysNear() { return !!(input && NEARRX.test(input.value)); }""",
"""  function saysNear() { return !!(input && NEARRX.test(input.value)); }
  // an everyday local need (0060's note, 2026-11-27, #1619): the words name somewhere to go nearby (health and emergency
  // care, and the everyday kinds: 0060's "near me" list and 0181's common needs), so with no place the "Where are you?"
  // picker opens on Enter, Auto-detect first, as for "near me"; closed without a place, the search stays everywhere
  var LOCALRX = /(^|\\s)(urgent care|walk-?in clinic|clinics?|hospitals?|emergency room|er|doctors?|pharmac(y|ies)|drug ?stores?|gas stations?|fuel|groceries|grocery( stores?)?|coffee|places to eat|restaurants?|lunch|breakfast|dinner|where to stay|places to stay|hotels?|motels?|lodging|mechanics?|auto repair|tow(ing)?|tire shops?)(\\s|$)/i;
  function localNeed() { return !!(input && LOCALRX.test(input.value)); }
  function needsPlace() { return saysNear() || localNeed(); }""", 'panel.needsPlace')
s = rep(s, """  function nearModal(force) {   // the place picker, docked in the panel as the Location line's pencil opens it
    if (!panel || panel.classList.contains('tvs-placing') || typeof openLocationModal !== 'function') return;
    if (!force && nearAsked === nearKey()) return;   // once for these words; Enter or the button asks again
    nearAsked = nearKey();
    nearPending = true;   // the place it sets is for "near me": 2 mi""",
"""  function nearModal(force, local) {   // the place picker, docked in the panel as the Location line's pencil opens it
    if (!panel || panel.classList.contains('tvs-placing') || typeof openLocationModal !== 'function') return;
    if (!force && nearAsked === nearKey()) return;   // once for these words; Enter or the button asks again
    nearAsked = nearKey();
    nearPending = !local;   // the place it sets is for "near me": 2 mi; for an everyday need, the usual distance""", 'panel.nearModal')
s = rep(s, """    if (nearGate()) { var bn = body(); if (bn) bn.innerHTML = nearWait(); nearModal(true); return; }   // Enter on "near me" with no place: where first, no search from everywhere""",
"""    if (nearGate()) { var bn = body(); if (bn) bn.innerHTML = nearWait(); nearModal(true); return; }   // Enter on "near me" with no place: where first, no search from everywhere
    if (localNeed() && !hasPlace() && !everywhereOk()) nearModal(false, true);   // an everyday need with no place: where, once for these words; the search still runs everywhere below""", 'panel.run')
# a listing row: where it stands now, in words, beside its kind and town
s = rep(s, """      var tag = it.open === true ? '<em class="tvs-open">Open</em>' : (when && it.kind === 'listing' && it.open === null ? '<em class="tvs-unk">Hours not listed</em>' : '');""",
"""      var tag = it.kind === 'listing' && it.hours_now ? hoursTag(it.hours_now) : (it.open === true ? '<em class="tvs-open">Open</em>' : '');""", 'panel.item')
s = rep(s, """        var open = it.always ? '<em class="tvs-open">Open 24 hours</em>' : (it.open === true ? '<em class="tvs-open">Open now</em>' : '');""",
"""        var open = it.hours_now ? hoursTag(it.hours_now) : (it.always ? '<em class="tvs-open">Open 24 hours</em>' : (it.open === true ? '<em class="tvs-open">Open now</em>' : ''));""", 'panel.situation')
s = rep(s, """  function row(it, kind) {""",
"""  // where a listing stands now (Hours::now's words, in its own zone): open in green, closed and unknown quiet
  function hoursTag(t) { return '<em class="' + (/^Open/.test(t) ? 'tvs-open' : 'tvs-unk') + '">' + esc(t) + '</em>'; }
  function row(it, kind) {""", 'panel.hoursTag')
s = rep(s, """  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && document.querySelector('.tvs-peek, .tv-ask-lb')) e.tvInner = true; }, true);""",
"""  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && document.querySelector('.tvs-peek, .tv-ask-lb')) e.tvInner = true; }, true);
  // the place picker over the search ("Where are you?", docked or its own pop-up): Escape closes the picker only, back to the results (as ×
  // does), never the whole search (0060's note #1619: closed without a place, the search stays everywhere)
  document.addEventListener('keydown', function (e) { var lm = document.getElementById('location-modal'); if (e.key === 'Escape' && lm && !lm.classList.contains('hidden') && typeof closeLocationModal === 'function') { e.tvInner = true; e.preventDefault(); closeLocationModal(); } }, true);   // preventDefault: a search box clears itself on Escape""", 'panel.escPicker')
s = rep(s, """    if (e.key === 'Escape') {   // first Escape clears the words, the next closes""",
"""    if (e.key === 'Escape') {   // first Escape clears the words, the next closes
      if (e.tvInner) return;   // it closed a picker or a Peek over the results: the words and results stay""", 'panel.keysEsc')
wr(p, s)

# ---- the listing page: where it stands now, over its hours ----------------------------------------------------------
p = 'sync-storage/listing/view.php'; s = rd(p)
s = rep(s, """      $addons[] = ['Hours', '<p class="la-pre">' . tv_e(is_array($hj) ? implode("\\n", array_map('strval', $hj)) : (string) $biz['hours']) . '</p>', true];""",
"""      $htxt = is_array($hj) ? implode("\\n", array_map('strval', $hj)) : (string) $biz['hours'];
      // where it stands now, in its own zone (0181's note: honest hours; the path checker reads this line, data-hours-now)
      require_once __DIR__ . '/../../app/lib/Hours.php';
      $hnow = $closed ? null : Hours::now(Hours::parse($htxt), Hours::zone((string) ($biz['state'] ?? ''), isset($biz['longitude']) && $biz['longitude'] !== null ? (float) $biz['longitude'] : null));
      $addons[] = ['Hours', ($hnow ? '<div class="la-now" data-hours-now="' . ($hnow['open'] === true ? 'open' : ($hnow['open'] === false ? 'closed' : 'unknown')) . '"><p data-tv-status="' . ($hnow['open'] === true ? 'ok' : 'info') . '" data-tv-label="Now:" data-tv-keep>' . tv_e($hnow['text']) . '</p></div>' : '')
          . '<p class="la-pre">' . tv_e($htxt) . '</p>', true];""", 'view.hours')
wr(p, s)

# ---- the FAQ: what visitors now see (decisions/0057) ---------------------------------------------------------------
p = 'sync-app/ui/faq.md'; s = rd(p)
s = rep(s, """Once you've set a place, search uses it. That includes a city, state or ZIP outside our regions, like Seattle, so it won't ask again.""",
"""Once you've set a place, search uses it. That includes a city, state or ZIP outside our regions, like Seattle, so it won't ask again.

It asks the same way when you search for something everyday with no place set, like urgent care, a pharmacy, groceries, gas, lunch, a place to stay or a mechanic: **Where are you?** opens with **Use my location** first. Close it (×, or Esc) to keep searching everywhere; it doesn't ask again for the same words.""", 'faq.where')
s = rep(s, """### I set a place outside your regions. What does search show?""",
"""### Does search say whether a place is open?
Yes. Each business in the results says where it stands right now, in its own time zone: **Open · Closes 9 PM**, **Closed · Opens 8 AM**, or **Hours not known** when we don't have its hours (we never guess). Its page says the same above its hours. **Open now** shows the places open now where they are, so a search from another time zone still gets it right.

### I set a place outside your regions. What does search show?""", 'faq.open')
wr(p, s)

print('patched', R)
