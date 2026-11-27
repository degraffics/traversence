/* js/tv-time.js — times shown where they happened, always with their zone (Jason, 2026-11-10).
 * The server sends the exact moment (ISO 8601 with its offset) and, when it knows it, the time zone of the device that
 * did the thing. A record shows its time in that zone ("Nov 9, 10:00 AM PST"), so a scan made in California reads as
 * California time wherever it's read later; without one it shows in the viewer's own zone (their phone's, which follows
 * the device wherever it is). Always labeled, never a bare clock.
 *   <time datetime="2026-11-09T18:00:12+00:00" data-tv-time data-tz="America/Los_Angeles"></time>  → painted on load
 *   TvTime.fmt(iso, tz)  → the same words in script;  TvTime.zone() → this device's zone (sent with what it does)
 *   TvTime.paint(el)     → paints the <time data-tv-time> inside el (after drawing new content)
 *   data-tv-clock        → the clock time and zone only ("4:42 AM MST"), for a moment minutes away (a code's end, 3f A11)
 */
(function () {
  if (window.TvTime) return;
  var here = '';
  try { here = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}

  function fmt(iso, tz, clock) {
    var d = new Date(iso);
    if (!iso || isNaN(d.getTime())) return '';
    var o = clock ? { hour: 'numeric', minute: '2-digit', timeZoneName: 'short' } : { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', timeZoneName: 'short' };
    if (!clock && d.getFullYear() !== new Date().getFullYear()) o.year = 'numeric';
    var zones = [tz, here].filter(Boolean);
    for (var i = 0; i < zones.length; i++) {
      try { return new Intl.DateTimeFormat('en-US', Object.assign({ timeZone: zones[i] }, o)).format(d); } catch (e) {}   // an unknown zone name: the next one
    }
    try { return new Intl.DateTimeFormat('en-US', o).format(d); } catch (e) { return d.toString(); }
  }

  function paint(root) {
    var list = (root || document).querySelectorAll('time[data-tv-time]');
    for (var i = 0; i < list.length; i++) {
      var t = list[i], ck = t.hasAttribute('data-tv-clock'), s = fmt(t.getAttribute('datetime'), t.getAttribute('data-tz') || '', ck);
      if (!s) continue;
      t.textContent = s;
      var mine = t.getAttribute('data-tz') && t.getAttribute('data-tz') !== here ? fmt(t.getAttribute('datetime'), here, ck) : '';
      if (mine && mine !== s) t.title = 'Your time: ' + mine;   // where it happened, and where you are now
    }
  }

  window.TvTime = { zone: function () { return here; }, fmt: fmt, paint: paint };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { paint(); }); else paint();
})();
