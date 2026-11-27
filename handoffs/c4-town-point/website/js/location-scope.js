// js/location-scope.js
//
// Shared location scope for every page that has the header (landing,
// /directory/, /discovery/). The header's location icon opens the location
// modal (includes/location-modal.php) on any page; what a location DOES is
// scope search proximity — it never routes anywhere.
//
// Extracted verbatim from the Directory page so behavior is unchanged. The
// one addition is the notifyLocationChanged() hook: pages with a search
// define onLocationChanged() to refresh hubs / re-run a search after the
// location changes; pages without one (the landing) simply have nothing to
// refresh. Search-only elements (#radius-slider, #search-location-clear,
// #collapse-status-text) are optional here.

// Zero-state by default: nothing pre-filled. currentLocation only gets set when
// the visitor picks a place this visit, arrives through a region (?hub= / ?geo= /
// ?cluster=), or a signed-in visitor searches the directory directly and their
// default saved place is applied (applyDefaultPlaceIfAny). `scope` is a region:
// {level:'hub'|'geo_hub'|'cluster', slug, label}; when present it replaces
// distance search. See docs/spec-draft-geographic-hierarchy.md (Shared Place Context).
let currentLocation = { state: null, city: null, zip: null, lat: null, lon: null, radius: 10, scope: null, source: null };
// True only when currentLocation's current value came from localStorage
// on this page load (i.e. it's an authenticated-session location, not
// something the user just set). Lets checkRealAuthState() tell a stale
// pre-loaded location apart from one legitimately typed/detected before
// its async session check resolves, so it only clears the former.
let locationLoadedFromLocalStorage = false;
// True once checkRealAuthState() confirms a genuine authenticated
// session. Gates both which storage tier location/search state persist
// to (see saveCurrentLocation/saveSearchState) and whether
// persistAccountHomeLocation() is allowed to write to the account.
// window.appCsrfToken (declared in shared-auth.js) is set alongside it.
let realAuthActive = false;

// The place a visitor picks (or arrives with) lives for this tab/session only.
// It is NOT carried into a later visit: a signed-in user's lasting default comes
// from their saved places (dashboard), and is applied only when they search the
// directory directly — never on a funneled (region) visit. See applyDefaultPlaceIfAny().
function saveCurrentLocation() {
  sessionStorage.setItem('traversence_location', JSON.stringify(currentLocation));
  localStorage.removeItem('traversence_location'); // older builds kept it here
}

function clearSavedLocation() {
  localStorage.removeItem('traversence_location');
  sessionStorage.removeItem('traversence_location');
}

// A location is "set" if it has a region scope (hub / geo-hub / cluster),
// a place name/ZIP OR raw coordinates —
// auto-detect increasingly relies on the latter, since reverse-geocoded
// rural addresses (this app's whole coverage area) often lack a usable
// city/town/postcode field even when the GPS fix itself is exact.
function hasLocationSet(loc) {
  return !!(loc && (loc.scope || loc.city || loc.zip || (loc.lat != null && loc.lon != null) || (loc.state && loc.state !== 'all')));
}

// A place that is just a state ("Arizona"): an area, not a point, so it has no
// radius. The directory shows its towns and categories instead of searching at once.
function isStateOnly(loc) {
  return !!(loc && !loc.scope && !loc.city && !loc.zip && (loc.lat == null || loc.lon == null) && loc.state && loc.state !== 'all');
}

const STATE_NAMES = {
  AL:'Alabama',AK:'Alaska',AZ:'Arizona',AR:'Arkansas',CA:'California',CO:'Colorado',CT:'Connecticut',DE:'Delaware',DC:'District of Columbia',
  FL:'Florida',GA:'Georgia',HI:'Hawaii',ID:'Idaho',IL:'Illinois',IN:'Indiana',IA:'Iowa',KS:'Kansas',KY:'Kentucky',LA:'Louisiana',
  ME:'Maine',MD:'Maryland',MA:'Massachusetts',MI:'Michigan',MN:'Minnesota',MS:'Mississippi',MO:'Missouri',MT:'Montana',NE:'Nebraska',
  NV:'Nevada',NH:'New Hampshire',NJ:'New Jersey',NM:'New Mexico',NY:'New York',NC:'North Carolina',ND:'North Dakota',OH:'Ohio',
  OK:'Oklahoma',OR:'Oregon',PA:'Pennsylvania',RI:'Rhode Island',SC:'South Carolina',SD:'South Dakota',TN:'Tennessee',TX:'Texas',
  UT:'Utah',VT:'Vermont',VA:'Virginia',WA:'Washington',WV:'West Virginia',WI:'Wisconsin',WY:'Wyoming',PR:'Puerto Rico'
};

// Formerly wrote every picked location to users.home_cluster_id. That is gone:
// picking a place while searching must not change the account's default. Defaults
// are managed as saved places (api/places.php) from the dashboard. Kept as a
// no-op so existing call sites stay harmless.
function persistAccountHomeLocation() {}

// opts.near (2026-11-10): opened for a "near me" search with no place: only where you are (Auto-detect first, and tried
// at once unless this browser has blocked location), or your town or ZIP; no trips, regions, distance or everywhere.
function openLocationModal(opts) {
  const near = !!(opts && opts.near);
  dockLocationPicker();
  document.getElementById('location-modal').classList.remove('hidden');
  const lbox = document.querySelector('.lm-box'), lh = document.getElementById('location-modal-title');
  if (lbox) lbox.classList.toggle('lm-near', near);
  if (lh) lh.textContent = near ? 'Where are you?' : 'Where are you looking?';
  if (near) {
    const det = document.getElementById('loc-detect');
    const tryIt = () => { if (det && navigator.geolocation) autoDetectLocation({ currentTarget: det }); };
    try {
      if (navigator.permissions && navigator.permissions.query) navigator.permissions.query({ name: 'geolocation' }).then(st => { if (st.state !== 'denied') tryIt(); else showDeviceMessage('Location access is blocked in this browser. Type your town or ZIP below, or allow location in your browser settings.'); }, tryIt);
      else tryIt();
    } catch (e) { tryIt(); }
  }
  syncLocationReach();
  renderSavedPlaces();
  const rp = document.getElementById('region-picker');
  if (rp) renderRegionPicker(rp);
  // Only frame this as "you need a location to search" when that's
  // actually why the modal opened (see requireLocationToSearch) — the
  // header/icon "Set Location" controls open the same modal without it.
  document.getElementById('loc-context-message').classList.toggle('hidden', typeof pendingSearchQuery === 'undefined' || pendingSearchQuery === null);
  syncSignedIn();
  // opened before the page knew you're signed in (Discovery's map opens it at once; Jason, 2026-11-11: "I am signed in as
  // admin so why sign in for your places?"): drawn again once it knows
  if (window.authReady && window.authReady.then) window.authReady.then(() => { syncSignedIn(); renderSavedPlaces(); });
  wireDestination();
}

// signed in: your saved places and your default; signed out: a way to sign in for them
function syncSignedIn() {
  const si = document.getElementById('loc-signin'), df = document.getElementById('loc-default');
  if (si) si.classList.toggle('hidden', !!realAuthActive);
  if (df) df.classList.toggle('hidden', !realAuthActive);
  if (si) si.href = '/login.php?next=' + encodeURIComponent(location.pathname + location.search);
}

// ---- Destination: a place typed by name (a town, an area, a region, or a local name like "Wide Ruins") ----
// Our own places, landmarks included ("Grand Canyon"), come first (/api/place.php?action=find); then towns elsewhere
// from the type-ahead (/api/suggest.php); an exact name, local names included, from the
// search's own reading of places (/api/search.php). Choosing one sets the place, like a region chip.
let destWired = false;
// ---- A place by name: a Town or ZIP box (the picker's own, and the one that replaces "Choose a place" on pages) ----
// "St. Johns, A": what's after the comma is a state on its way ("A", "AZ", "Ariz"). Search the town; keep the towns in
// a state that starts with it. Our areas and regions have no state in their name and always stay.
const tvEsc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/** The places a name may be, ours first (decisions/0058 §17): anchor towns, town areas, local names, towns in our areas,
 *  areas and regions, landmarks, then any U.S. town by that name. */
function placeOptions(raw) {
  const STATES = { AL: 'alabama', AK: 'alaska', AZ: 'arizona', AR: 'arkansas', CA: 'california', CO: 'colorado', CT: 'connecticut', DE: 'delaware', FL: 'florida', GA: 'georgia',
    HI: 'hawaii', ID: 'idaho', IL: 'illinois', IN: 'indiana', IA: 'iowa', KS: 'kansas', KY: 'kentucky', LA: 'louisiana', ME: 'maine', MD: 'maryland', MA: 'massachusetts',
    MI: 'michigan', MN: 'minnesota', MS: 'mississippi', MO: 'missouri', MT: 'montana', NE: 'nebraska', NV: 'nevada', NH: 'new hampshire', NJ: 'new jersey', NM: 'new mexico',
    NY: 'new york', NC: 'north carolina', ND: 'north dakota', OH: 'ohio', OK: 'oklahoma', OR: 'oregon', PA: 'pennsylvania', RI: 'rhode island', SC: 'south carolina',
    SD: 'south dakota', TN: 'tennessee', TX: 'texas', UT: 'utah', VT: 'vermont', VA: 'virginia', WA: 'washington', WV: 'west virginia', WI: 'wisconsin', WY: 'wyoming', DC: 'district of columbia' };
  const cm = raw.match(/^(.+?),\s*([a-z .]*)$/i);
  const q = cm ? cm[1].trim() : raw, frag = cm ? cm[2].trim().toLowerCase() : '';
  const stateOf = o => { const m = String(o.state || '').match(/^[A-Z]{2}$/) || String(o.label).match(/,\s*([A-Z]{2})$/) || String(o.sub || '').match(/^([A-Z]{2})\b/); return m ? (m[1] || m[0]) : ''; };
  const fits = (code, f) => !f || code.toLowerCase().indexOf(f) === 0 || (STATES[code] || '').indexOf(f) === 0;
  return Promise.all([
    fetch('/api/search.php?drop=scope&q=' + encodeURIComponent(q), { credentials: 'include' }).then(r => r.json()).catch(() => ({})),
    fetch('/api/suggest.php?q=' + encodeURIComponent(q)).then(r => r.json()).catch(() => ({})),
    fetch('/api/place.php?action=find&q=' + encodeURIComponent(q)).then(r => r.json()).catch(() => ({})),   // our own places, forgivingly
  ]).then(([a, b, c]) => {
    const out = [], seen = {};
    const add = o => { const k = (o.level || 'city') + ':' + (o.slug || '') + ':' + String(o.label).toLowerCase() + (o.state || ''); if (!seen[k]) { seen[k] = 1; out.push(o); } };   // two landmarks in one town area are both listed
    (c.places || []).forEach(p => add(p.level ? { label: p.label, sub: p.sub, level: p.level, slug: p.slug, area: p.area } : { label: p.label, sub: p.sub, city: p.city, state: p.state }));
    // a town the search read in its area ("Show Low, AZ · Town in …") sets that area, so it's listed as the area, its own
    // choice beside the town itself (a point, from place.php: decisions/0053; P9, Jason 2026-11-13), and once
    (a.chips || []).filter(c => c.type === 'place' && !c.scope && c.slug).forEach(c => {
      const ti = /^Town( in (.+))?$/.exec(String(c.sub || ''));
      if (!ti) { add({ label: c.label, sub: c.sub || 'Place', level: c.level, slug: c.slug }); return; }
      if (out.some(o => o.level === c.level && o.slug === c.slug)) return;
      add({ label: ti[2] || String(c.label).split(',')[0].trim() + ' area', sub: 'Town area', level: c.level, slug: c.slug });
    });
    const starts = l => new RegExp('(^|[\\s.\'-])' + q.toLowerCase().replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).test(String(l).toLowerCase());   // a word starts with it
    (b.suggestions || []).filter(x => x.type !== 'city' || starts(x.label)).forEach(x => {
      if (x.type === 'region' && !x.soon) add({ label: x.label, sub: x.level === 'hub' ? 'Region' : 'Area', level: x.level, slug: x.slug });
      // a town the search already placed in its area ("Saint Johns, AZ · Town in St. Johns") isn't listed twice
      const town = l => String(l).split(',')[0].trim().toLowerCase();   // "Show Low, AZ" and "SHOW LOW" are one town
      if (x.type === 'city' && !out.some(o => (o.level ? o.label.toLowerCase().indexOf(x.label.toLowerCase()) === 0 : town(o.label) === town(x.label) && (!o.state || !x.sub || String(x.sub).indexOf(o.state) === 0))))
        add({ label: x.label, sub: x.sub + (x.listings ? ' · ' + x.listings + ' listed' : ''), city: x.label, state: x.sub });
    });
    return out.filter(o => { const st = stateOf(o); return !st || fits(st, frag); }).slice(0, 8);
  });
}

function pickPlace(o) {
  if (o.level) applyLocation({ scope: { level: o.level, slug: o.slug, label: o.area || o.label } }, 'picked');   // a landmark sets its town area
  else applyLocation({ state: o.state || 'all', city: o.city }, 'picked');
}

/** Typing a town or ZIP in a box: the places it may be, listed under it; Enter takes the first, or a ZIP as typed.
 *  opts.zip(zip): how a ZIP is set (the picker submits its own form); opts.empty: what to say when nothing has the name;
 *  opts.more: offer the full picker (regions, saved places, the Trip Planner) at the foot, so it's never a dead end. */
function wirePlaceInput(inp, box, opts) {
  opts = opts || {};
  let t = null, seq = 0, list = [];
  const show = l => {
    list = l;
    box.innerHTML = (l.length ? l.map((o, i) => '<button type="button" role="option" data-i="' + i + '"><span>' + tvEsc(o.label) + '</span><small>' + tvEsc(o.sub || '') + '</small></button>').join('')
      : '<p>' + tvEsc(opts.empty || 'No place by that name yet. Try a nearby town or a ZIP.') + '</p>')
      + (opts.more ? '<button type="button" class="tv-pbox-more" data-where="change">More choices: regions, your places, the Trip Planner</button>' : '');
    box.hidden = false;
  };
  const pick = o => { box.hidden = true; inp.value = ''; inp.blur(); pickPlace(o); };
  inp.addEventListener('input', () => {
    clearTimeout(t);
    const raw = inp.value.trim();
    if (raw.length < 2 || /^\d{1,5}$/.test(raw)) { box.hidden = true; return; }   // a ZIP on its way: Enter sets it
    t = setTimeout(() => { const my = ++seq; placeOptions(raw).then(l => { if (my === seq && inp.value.trim() === raw) show(l); }); }, 250);
  });
  inp.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      e.preventDefault();
      // a ZIP in the one box (2026-11-11: "a single input box for Town/Zip")
      const z = inp.value.trim().match(/^(\d{5})(-\d{4})?$/);
      if (z) { box.hidden = true; inp.value = ''; inp.blur(); if (opts.zip) opts.zip(z[1]); else applyLocation({ zip: z[1] }, 'picked'); return; }
      if (list[0] && !box.hidden) pick(list[0]);
    }
    if (e.key === 'Escape') { box.hidden = true; }
    if (e.key === 'ArrowDown') { const f = box.querySelector('button'); if (f) { e.preventDefault(); f.focus(); } }
  });
  box.addEventListener('click', e => {
    const b = e.target.closest('[data-i]'); if (b) { pick(list[+b.getAttribute('data-i')]); return; }
    // the full picker; inside the search's pop-up, its own handler opens it docked
    if (e.target.closest('.tv-pbox-more')) { box.hidden = true; if (!box.closest('.tvs-panel') && typeof openLocationModal === 'function') openLocationModal(); }
  });
  box.addEventListener('keydown', e => {
    const b = e.target.closest('button'); if (!b) return;
    if (e.key === 'ArrowDown' && b.nextElementSibling) { e.preventDefault(); b.nextElementSibling.focus(); }
    if (e.key === 'ArrowUp') { e.preventDefault(); (b.previousElementSibling || inp).focus(); }
  });
}

function wireDestination() {
  const inp = document.getElementById('loc-dest'), box = document.getElementById('loc-dest-list');
  if (!inp || !box || destWired) return;
  destWired = true;
  wirePlaceInput(inp, box, {
    empty: 'No place by that name yet. Try a nearby town, or State · City · ZIP below.',
    // set as the State · City · ZIP form does
    zip: z => { const zi = document.getElementById('loc-zip'), fm = zi && zi.form; if (zi && fm) { zi.value = z; if (fm.requestSubmit) fm.requestSubmit(); else fm.dispatchEvent(new Event('submit', { cancelable: true })); } else applyLocation({ zip: z }, 'picked'); },
  });
}

// The simple place box (Jason, 2026-11-11: "choose a place should have been replaced with the simple town/autodetect", as on
// the home page's split search): one box showing the place, where a town or ZIP is typed, and the target beside it that
// finds you in one tap. Markup: [data-place-box] holding .tv-pbox-in, .tv-pbox-tgt, .tv-pbox-list and, optionally,
// .tv-pbox-x (clears the place; data-own-clear when the page handles it); data-label overrides what the box shows.
const TV_PIN_SVG = '<svg class="tv-pbox-pin" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>';
const TV_TGT_SVG = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="2"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg>';
function placeBoxHtml() {
  return TV_PIN_SVG + '<input class="tv-pbox-in" type="search" placeholder="Town or ZIP" autocomplete="off" aria-label="Town or ZIP" aria-autocomplete="list">'
    + '<button type="button" class="tv-pbox-x" hidden title="Clear the place: search everywhere" aria-label="Clear the place">&times;</button>'
    + '<button type="button" class="tv-pbox-tgt" title="Use my location" aria-label="Use my location">' + TV_TGT_SVG + '</button>'
    + '<div class="tv-pbox-list lm-sug" role="listbox" hidden></div>';
}
function tvPlaceLabel() {
  const l = currentLocation || {};
  if (!hasLocationSet(l)) return '';
  if (l.scope && l.scope.label) return String(l.scope.label).split('·')[0].trim();
  if (l.townName) return l.townName;   // Auto-detect: the town it found (2026-11-12)
  if (l.zip) return l.zip;
  if (l.city) return l.city;
  if (isStateOnly(l)) return STATE_NAMES[l.state] || l.state;
  if (l.savedLabel) return l.savedLabel;
  return l.lat != null ? 'Near you' : '';
}
window.tvPlaceBoxes = window.tvPlaceBoxes || [];
function placeBoxCss() {
  if (document.getElementById('tv-pbox-css')) return;
  const st = document.createElement('style'); st.id = 'tv-pbox-css';
  st.textContent = '.tv-pbox{position:relative;display:inline-flex;align-items:center;gap:.3rem;min-width:0;border:1px solid #CBB98D;background:#FFFBF2;color:#3E2B1F;border-radius:999px;padding:.12rem .2rem .12rem .7rem}'
    + '.tv-pbox:focus-within{border-color:#B45309}.tv-pbox-pin{color:#B45309;flex:0 0 auto}'
    + '.tv-pbox-in{flex:1;min-width:6.5rem;width:11rem;border:0;background:transparent;outline:none;color:inherit;font-size:.86rem;font-weight:600;padding:.3rem 0}.tv-pbox-in::placeholder{color:#8A7355;font-weight:500}'
    + '.tv-pbox-in::-webkit-search-cancel-button{display:none}'
    + '.tv-pbox-x{flex:0 0 auto;border:0;background:none;color:#92400E;font-size:1.1rem;line-height:1;cursor:pointer;padding:0 .2rem}.tv-pbox-x[hidden]{display:none}'
    + '.tv-pbox-tgt{flex:0 0 auto;width:30px;height:30px;border-radius:999px;border:1px solid #CBB98D;background:#F7EFDC;color:#B45309;display:flex;align-items:center;justify-content:center;cursor:pointer}.tv-pbox-tgt:hover{background:#8FBCA8;color:#1F3B30;box-shadow:0 0 0 3px rgba(143,188,168,.35)}'
    + '.tv-pbox-list{position:absolute;left:0;top:100%;z-index:60;min-width:16rem;right:0;text-align:left;background:#FFFBF2;border:1px solid #CBB98D;border-radius:.6rem;margin-top:.25rem;box-shadow:0 12px 24px -12px rgba(0,0,0,.4);overflow:hidden}.tv-pbox-list[hidden]{display:none}'
    + '.tv-pbox-list button{display:flex;justify-content:space-between;gap:.6rem;width:100%;text-align:left;background:none;border:0;border-top:1px solid #EADFC4;padding:.45rem .65rem;font-size:.86rem;color:#3E2B1F;cursor:pointer}.tv-pbox-list button:first-child{border-top:0}'
    + '.tv-pbox-list button:hover,.tv-pbox-list button:focus{background:#F7EFDC;outline:none}.tv-pbox-list small{color:#7A6348;text-align:right}.tv-pbox-list p{margin:0;padding:.45rem .65rem;font-size:.82rem;color:#5C4632}'
    + '.tv-pbox-more{color:#92400E!important;font-weight:600}'
    + '.tvs-top .tv-pbox{background:#2A1B12;border-color:#6B5440;color:#F7EFDC;align-self:center}.tvs-top .tv-pbox-in::placeholder{color:#CBB98D}.tvs-top .tv-pbox-x{color:#F3E3CB}.tvs-top .tv-pbox-tgt{background:#3E2B1F;border-color:#6B5440;color:#E0C68E}.tvs-top .tv-pbox-list{color:#3E2B1F}';
  document.head.appendChild(st);
}
function wirePlaceBox(root) {
  if (!root || root.getAttribute('data-pb-wired')) return;
  root.setAttribute('data-pb-wired', '1');
  placeBoxCss();
  root.classList.add('tv-pbox');
  if (!root.querySelector('.tv-pbox-in')) root.innerHTML = placeBoxHtml();
  const inp = root.querySelector('.tv-pbox-in'), box = root.querySelector('.tv-pbox-list'), tgt = root.querySelector('.tv-pbox-tgt'), x = root.querySelector('.tv-pbox-x');
  const paint = () => {
    const lb = root.hasAttribute('data-label') ? root.getAttribute('data-label') : tvPlaceLabel();
    if (document.activeElement !== inp) inp.value = lb;
    if (x && !root.hasAttribute('data-own-clear')) x.hidden = !lb;
  };
  root.tvPaint = paint;
  wirePlaceInput(inp, box, { more: true });
  inp.addEventListener('focus', () => { try { inp.select(); } catch (e) {} });
  inp.addEventListener('blur', () => setTimeout(() => { if (!root.contains(document.activeElement)) { box.hidden = true; paint(); } }, 200));
  if (tgt) tgt.addEventListener('click', autoDetectLocation);
  if (x && !root.hasAttribute('data-own-clear')) x.addEventListener('click', () => chooseAnywhere());
  window.tvPlaceBoxes.push(paint);
  paint();
}
window.wirePlaceBox = wirePlaceBox;
// "We need a location to search" (Jason, 2026-11-12, his drawing): one pop-up for every page that needs a place: a header with
// ! and ×, one bar (a map tile, the site's Town or ZIP box with the target, GO), and LOG IN (MY PLACES when signed in) | NOT SURE?.
// tvPlacePop({ notSure: fn|url }) opens it over the page, greyed; it closes when a place is chosen, on ×, Esc or the grey.
function tvPlacePop(o) {
  o = o || {};
  const old = document.querySelector('.tvpp'); if (old) old.remove();
  if (!document.getElementById('tvpp-css')) {
    const st = document.createElement('style'); st.id = 'tvpp-css';
    st.textContent = '.tvpp{position:fixed;inset:0;z-index:2100}.tvpp-dim{position:absolute;inset:0;background:rgba(43,29,20,.45)}'
      + '.tvpp-c{position:absolute;left:50%;top:38%;transform:translate(-50%,-50%);width:min(30rem,calc(100% - 1.5rem));background:#FFFBF2;border:1.5px solid #E08A3C;border-radius:1rem;box-shadow:0 22px 48px -18px rgba(0,0,0,.6);color:#3E2B1F}'
      + '.tvpp-h{display:flex;align-items:center;gap:.55rem;padding:.6rem .8rem;border-bottom:1px solid #E8D5BC}.tvpp-i{flex:none;width:26px;height:26px;border-radius:999px;background:#E07A2F;color:#fff;display:flex;align-items:center;justify-content:center;font:800 1rem system-ui,sans-serif}'
      + '.tvpp-h b{flex:1;font:600 .95rem system-ui,sans-serif;letter-spacing:.04em;text-transform:uppercase;color:#7A6A5A}.tvpp-x{flex:none;width:24px;height:24px;border-radius:999px;border:1.5px solid #8A7A6A;background:#fff;color:#5C4632;font-size:.95rem;line-height:1;cursor:pointer;padding:0}'
      + '.tvpp-bar{display:flex;align-items:stretch;margin:1.4rem 1.1rem;border:1.5px solid #E08A3C;border-radius:.4rem;overflow:visible;background:#F7EFDC;position:relative}'
      + '.tvpp-map{flex:none;width:3.4rem;display:flex;align-items:center;justify-content:center;background:#978C80;color:#fff;border-radius:.3rem 0 0 .3rem}.tvpp-map svg{width:30px;height:30px}'
      + '.tvpp-bar .tv-pbox{flex:1;min-width:0;border:0!important;border-radius:0!important;background:transparent!important;padding:0 .3rem 0 .7rem!important}'
      + '.tvpp-bar .tv-pbox-in{font:600 1.15rem system-ui,sans-serif!important;text-transform:uppercase;letter-spacing:.02em;padding:.75rem 0!important;color:#3E2B1F}.tvpp-bar .tv-pbox-in::placeholder{color:#3E2B1F;opacity:.85}'
      + '.tvpp-bar .tv-pbox-pin,.tvpp-bar .tv-pbox-x{display:none!important}.tvpp-bar .tv-pbox-tgt{color:#E07A2F;border:0;background:none;transform:scale(1.35)}'
      + '.tvpp-go{flex:none;border:0;background:var(--tv-btn);color:var(--tv-btn-text);font:800 1.25rem system-ui,sans-serif;letter-spacing:.05em;text-transform:uppercase;padding:0 1.1rem;border-radius:0 .3rem .3rem 0;cursor:pointer}.tvpp-go:hover{background:#A2461D}'
      + '.tvpp-f{display:flex;justify-content:center;align-items:center;gap:.7rem;padding:.65rem;border-top:1px solid #E8D5BC;font:600 1.05rem system-ui,sans-serif;letter-spacing:.04em;text-transform:uppercase}'
      + '.tvpp-mine{margin:-.6rem 1.1rem 1rem;display:flex;flex-wrap:wrap;align-items:center;gap:.35rem}.tvpp-mine[hidden]{display:none}.tvpp-mine span{font:600 .78rem system-ui,sans-serif;letter-spacing:.05em;text-transform:uppercase;color:#7A6A5A;margin-right:.2rem}'
      + '.tvpp-mine select{flex:1;min-width:0;border:1px solid #E08A3C;background:#fff;color:#3E2B1F;border-radius:.4rem;padding:.4rem .5rem;font:500 .9rem system-ui,sans-serif;cursor:pointer}'
      + '.tvpp-f a,.tvpp-f button{color:var(--tv-link,#92400E);text-decoration:none;background:none;border:0;font:inherit;text-transform:uppercase;letter-spacing:inherit;cursor:pointer;padding:0}.tvpp-f .tvpp-ns{color:var(--tv-meta,#5E4F3C);font-style:italic;font-weight:500}.tvpp-f i{color:#E07A2F;font-style:normal}';
    document.head.appendChild(st);
  }
  const back = encodeURIComponent(location.pathname + location.search);
  const w = document.createElement('div'); w.className = 'tvpp';
  w.innerHTML = '<div class="tvpp-dim" data-pp-x></div><div class="tvpp-c" role="dialog" aria-modal="true" aria-label="We need a location to search">'
    + '<div class="tvpp-h"><span class="tvpp-i" aria-hidden="true">!</span><b>We need a location to search</b><button type="button" class="tvpp-x" data-pp-x aria-label="Close" title="Close">&times;</button></div>'
    + '<div class="tvpp-bar"><span class="tvpp-map" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 13.5s4-3.6 4-7a4 4 0 0 0-8 0c0 3.4 4 7 4 7z"/><circle cx="12" cy="6.5" r="1.4"/><path d="M8 13H5l-3 8h20l-3-8h-3"/><path d="M6.5 17l5 2 6-3"/></svg></span>'
    + '<span data-place-box></span><button type="button" class="tvpp-go">Go</button></div>'
    + '<div class="tvpp-mine" hidden></div>'
    + '<div class="tvpp-f"><span data-pp-acct></span><i aria-hidden="true">|</i>'
    + '<button type="button" class="tvpp-ns" data-pp-ns>Not sure?</button></div></div>';
  document.body.appendChild(w);
  const pb = w.querySelector('[data-place-box]'); pb.setAttribute('data-label', ''); wirePlaceBox(pb);
  const inp = pb.querySelector('.tv-pbox-in'); if (inp) { inp.placeholder = 'Local: town, ZIP'; inp.value = ''; setTimeout(() => inp.focus(), 30); }
  // signed in or not, and your saved places: the page learns them a moment after it opens (Jason, 2026-11-12: "the login was
  // showing now" after setting places), so this follows when they arrive: My places, and each saved place one tap away
  const acct = () => {
    const a = w.querySelector('[data-pp-acct]'); if (!a) return;
    a.innerHTML = realAuthActive ? '<a href="/user/dashboard.php#places">My places</a>' : '<a href="/login.php?next=' + back + '">Log in</a>';
    const mine = w.querySelector('.tvpp-mine'), ps = realAuthActive ? (window.appSavedPlaces || []) : [];
    mine.hidden = !ps.length;
    // one dropdown, not a row of pills (Jason, 2026-11-12: "the pills are janky looking … make it a dropdown")
    const e = (s) => String(s || '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    mine.innerHTML = ps.length ? '<span>Your places</span><select data-pp-places aria-label="Your places"><option value="">Choose one of your places…</option>'
      + ps.map((p, i) => '<option value="' + i + '">' + e(p.label) + (p.is_default ? ' (default)' : '') + '</option>').join('') + '</select>' : '';
  };
  acct();
  let tries = 0; const seen = () => String(realAuthActive) + (window.appSavedPlaces || []).length;
  let last = seen(); const watch = setInterval(() => { if (!document.body.contains(w) || ++tries > 30) { clearInterval(watch); return; } if (seen() !== last) { last = seen(); acct(); } }, 300);
  const close = () => { clearInterval(watch); w.remove(); document.removeEventListener('keydown', esc); window.removeEventListener('tv:location', close); if (o.onClose) o.onClose(); };
  const esc = (e) => { if (e.key === 'Escape') close(); };
  document.addEventListener('keydown', esc); window.addEventListener('tv:location', close);
  w.addEventListener('change', (ev) => {
    if (!ev.target.matches('[data-pp-places]') || ev.target.value === '') return;
    const p = (window.appSavedPlaces || [])[+ev.target.value]; if (p && p.location) applyLocation({ ...p.location, savedLabel: p.label }, 'picked');
  });
  w.addEventListener('click', (e) => {
    if (e.target.closest('[data-pp-x]')) { close(); return; }

    if (e.target.closest('.tvpp-go')) { if (inp && inp.value.trim()) inp.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true })); else if (inp) inp.focus(); return; }
    if (e.target.closest('[data-pp-ns]')) { if (typeof o.notSure === 'function') { close(); o.notSure(); } else location.href = typeof o.notSure === 'string' ? o.notSure : '/discovery/?view=regions'; }
  });
  return close;
}
window.tvPlacePop = tvPlacePop;
// Where the place came from, said wherever results follow it (Jason, 2026-11-11: "if the results are showing from a default,
// history, or setting I want it stated on the top of the listings panel"): {place, why}, or null with no place set.
function tvPlaceWhy() {
  const l = currentLocation || {};
  if (!hasLocationSet(l)) return null;
  // how it was chosen, never only "earlier in this visit" (Jason, 2026-11-11: "visit hides how the place was chosen: I clicked
  // autodetect")
  const why = l.source === 'default' ? 'your default place'
    : l.source === 'device' ? 'found by your device (the target)'
    : l.source === 'arrived' ? 'the region you came in through'
    : l.savedLabel ? 'one of your saved places'
    : l.source === 'picked' ? 'chosen by you' : 'set earlier in this visit';
  return { place: tvPlaceLabel(), why: why };
}
window.tvPlaceWhy = tvPlaceWhy;
function wirePlaceBoxes() { document.querySelectorAll('[data-place-box]').forEach(wirePlaceBox); }

function closeLocationModal() { document.getElementById('location-modal').classList.add('hidden'); undockLocationPicker(); }

// With the search open, the picker opens inside the search's own window, under its top line (the words, the place, ×),
// in place of the results, so that line is never lost (Jason, 2026-11-05). Picking a place, or × on the picker, returns
// to the results. Elsewhere it is its own pop-up.
function dockLocationPicker() {
  const m = document.getElementById('location-modal'), top = document.querySelector('.tvs-panel.full .tvs-top');
  const box = document.querySelector('.lm-box');
  if (!m || !box || !top) return;
  top.after(box); top.parentNode.classList.add('tvs-placing'); m.classList.add('lm-docked');
  top.parentNode.scrollTop = 0;
}
function undockLocationPicker() {
  const m = document.getElementById('location-modal'), box = document.querySelector('.lm-box');
  if (!m || !box || !m.classList.contains('lm-docked')) return;
  const pn = box.closest('.tvs-panel'); if (pn) pn.classList.remove('tvs-placing');
  m.appendChild(box); m.classList.remove('lm-docked');
}
window.undockLocationPicker = undockLocationPicker;

// How far around the place to look: the search's own Distance setting (kept on this device), shown in the picker
// wherever the search is on the page.
function syncLocationReach() {
  const rch = document.getElementById('loc-reach'); if (!rch) return;
  // a region or an area has its own edges: a distance around it means nothing (Jason, 2026-11-11), so Distance is for a town, a ZIP or your location
  const lvl = (typeof currentLocation !== 'undefined' && currentLocation && currentLocation.scope && currentLocation.scope.level) || '';
  // and only once a place is set: how far around comes after where (Jason, 2026-11-11: "distance is something that comes after a
  // location is set")
  const placed = typeof currentLocation !== 'undefined' && hasLocationSet(currentLocation);
  rch.hidden = !(window.TvSearch && TvSearch.reach) || !placed || lvl === 'hub' || lvl === 'geo_hub';
  if (rch.hidden) return;
  const r = TvSearch.reach(), inp = document.getElementById('loc-reach-in'), out = document.getElementById('loc-reach-v');
  inp.max = String(r.steps.length - 1); inp.disabled = !r.on; inp.value = String(r.on ? r.i : r.steps.length - 1);
  out.textContent = r.steps[+inp.value][1]; inp.setAttribute('aria-valuetext', out.textContent);
  inp.title = r.on ? '' : 'Choose a place to limit distance';
}
document.addEventListener('input', (e) => {
  if (e.target.id !== 'loc-reach-in' || !window.TvSearch || !TvSearch.reach) return;
  const t = TvSearch.reach().steps[+e.target.value][1], o = document.getElementById('loc-reach-v');
  if (o) o.textContent = t; e.target.setAttribute('aria-valuetext', t);
});
document.addEventListener('change', (e) => {
  if (e.target.id === 'loc-reach-in' && window.TvSearch && TvSearch.setReach) TvSearch.setReach(+e.target.value);
});


// Real account status. Defaults to showing signed-out on any failure,
// so an unreachable API never silently implies a login that was never
// actually verified. window.appCsrfToken (declared in shared-auth.js,
// included above) is what logout() and every other authenticated
// fetch on this page reads — set here, alongside realAuthActive, the
// moment a session is confirmed.
async function checkRealAuthState() {
  let authenticated = false;
  try {
    const res = await fetch('/api/auth/session.php', { credentials: 'include' });
    const data = await res.json();
    authenticated = !!data.authenticated;
    window.appSessionUser = authenticated ? (data.user || null) : null;
    window.appCsrfToken = data.csrf_token || null;
    realAuthActive = authenticated;

    // The account's default place is NOT applied here. It is applied only when
    // the visitor searches the directory directly (applyDefaultPlaceIfAny), so a
    // funneled visit (a Discovery hub / region) is never overridden by it.
    window.appHomeLocation = authenticated ? (data.home_location || null) : null;
    window.appHasAdminAccess = authenticated ? !!data.has_admin_access : false;
    window.appSavedPlaces = [];
    if (authenticated) {
      try {
        const pr = await fetch('/api/places.php', { credentials: 'include' });
        if (pr.ok) {
          const pd = await pr.json();
          if (pd && pd.success) window.appSavedPlaces = pd.places || [];
        }
      } catch (placesErr) { /* saved places not available: fall back to home_location */ }
    }
  } catch (e) {
    authenticated = false;
    realAuthActive = false;
    window.appSessionUser = null;
    window.appCsrfToken = null;
    window.appHomeLocation = null;
    window.appSavedPlaces = [];
  }
  // real-auth-guest / real-auth-user removed 2026-09-18 — header is now
  // a single icon-only profile button (includes/header.php); its
  // grey/green state and click destination are driven by
  // window.appAuthActive instead of showing/hiding two separate blocks.
  window.appAuthActive = authenticated;
  window.appIsAdmin = authenticated && (!!(window.appSessionUser && window.appSessionUser.is_admin) || !!window.appHasAdminAccess);
  if (typeof window.updateHeaderIconStates === 'function') window.updateHeaderIconStates();
}

// Applies a complete location object as the current place (one path for every
// way of choosing a place: region, saved place, device suggestion, default).
function applyLocation(loc, source) {
  currentLocation = { state: 'all', city: '', zip: '', lat: null, lon: null, scope: null, ...loc, radius: 10, source: source || 'picked' };
  window.tvLocationRestored = false;   // chosen now, not still set from earlier in the visit
  locationLoadedFromLocalStorage = false;
  try { sessionStorage.removeItem('traversence_anywhere'); } catch (e) {}
  saveCurrentLocation();
  setRadiusUi(10);
  updateLocationLabels();
  closeLocationModal();
  notifyLocationChanged();
}

// Signed-in visitor opening the directory directly and searching with no place:
// use their default saved place. Returns true when one was applied.
// (Never called for a region/funnel visit: that sets currentLocation first.)
function applyDefaultPlaceIfAny() {
  if (!realAuthActive || hasLocationSet(currentLocation)) return false;
  // They chose "everywhere" this visit: don't pull the default back in.
  try { if (sessionStorage.getItem('traversence_anywhere') === '1') return false; } catch (e) {}
  const d = (window.appSavedPlaces || []).find(p => p.is_default);
  const loc = d ? d.location : window.appHomeLocation;
  if (!loc || !hasLocationSet(loc)) return false;
  applyLocation(d ? { ...loc, savedLabel: d.label } : loc, 'default');
  return true;
}

// ---- Region picker (replaces the old "set a location" wall) ----
let regionHubsCache = null;
async function loadRegionHubs() {
  if (regionHubsCache) return regionHubsCache;
  const res = await fetch('/api/place.php?action=hubs');
  const data = await res.json();
  regionHubsCache = (data && data.success) ? (data.hubs || []) : [];
  return regionHubsCache;
}

function selectRegion(hub) {
  applyLocation({ scope: { level: 'hub', slug: hub.slug, label: hub.name } }, 'picked');
}

async function renderRegionPicker(container) {
  if (container.childElementCount) return;   // drawn once
  container.textContent = 'Loading regions...';
  let hubs;
  try { hubs = await loadRegionHubs(); }
  catch (e) { container.textContent = 'Regions are unavailable right now. You can still type a ZIP below.'; return; }
  container.textContent = '';
  if (!hubs.length) { container.textContent = 'No regions are available yet.'; return; }
  const live = hubs.filter(h => h.status !== 'planned');
  const planned = hubs.filter(h => h.status === 'planned');
  const mk = (h, enabled) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.textContent = enabled ? h.name : h.name + ' · soon';
    b.title = enabled ? h.name : h.name + ': coming soon';
    b.className = 'text-xs px-3 py-1.5 rounded-full border border-[#CBB98D] transition text-left ' +
      (enabled ? 'bg-[#3E2B1F] text-[#FFFBF2] hover:bg-[#2A1B12]' : 'bg-transparent text-[#8A7355] opacity-60 cursor-not-allowed');
    if (enabled) b.addEventListener('click', () => selectRegion(h)); else b.disabled = true;
    return b;
  };
  live.forEach(h => container.appendChild(mk(h, true)));
  planned.forEach(h => container.appendChild(mk(h, false)));
}

// ---- Saved places (signed-in) ----
function renderSavedPlaces() {
  const section = document.getElementById('saved-places-section');
  const list = document.getElementById('saved-places-list');
  if (!section || !list) return;
  const places = (realAuthActive && window.appSavedPlaces) ? window.appSavedPlaces : [];
  section.classList.toggle('hidden', places.length === 0);
  list.textContent = '';
  places.forEach(p => {
    const b = document.createElement('button');
    b.type = 'button';
    b.textContent = p.label + (p.is_default ? ' (default)' : '');
    b.className = 'text-xs px-3 py-1.5 rounded-full border border-[#CBB98D] bg-[#EFE3C8] hover:bg-[#D5C39A] text-[#3E2B1F] transition';
    b.addEventListener('click', () => applyLocation({ ...p.location, savedLabel: p.label }, 'picked'));
    list.appendChild(b);
  });
}

// ---- Device guess: a suggestion, never applied automatically ----
function showDeviceMessage(text) {
  // the target beside a Town or ZIP box on a page: the message beside it, as the site's notice (the picker isn't open)
  const modal = document.getElementById('location-modal');
  if (detectBtn && document.body.contains(detectBtn) && !(modal && modal.contains(detectBtn)) && window.TvAsk && TvAsk.say) { TvAsk.say(text, { anchor: detectBtn, bad: true }); return; }
  const box = document.getElementById('device-suggestion');
  if (!box) return;
  box.textContent = '';
  const p = document.createElement('p');
  p.className = 'text-xs text-[#5C4632]';
  p.textContent = text;
  box.appendChild(p);
  box.classList.remove('hidden');
  try { box.scrollIntoView({ block: 'nearest' }); } catch (e) {}   // on a phone it can sit below the fold
}

function showDeviceSuggestion(place, fix) {
  const box = document.getElementById('device-suggestion');
  if (!box) return;
  box.textContent = '';
  const msg = document.createElement('p');
  msg.className = 'text-sm font-semibold text-[#3E2B1F]';
  msg.textContent = 'Looks like ' + place.label + '. Use this?';
  box.appendChild(msg);
  if (place.approximate) {
    const note = document.createElement('p');
    note.className = 'text-[11px] text-amber-700 mt-1';
    note.textContent = 'Approximate: this guess comes from your network or device and can be off by many miles.';
    box.appendChild(note);
  }
  const row = document.createElement('div');
  row.className = 'flex gap-2 mt-2';
  const yes = document.createElement('button');
  yes.type = 'button';
  yes.textContent = 'Use this';
  yes.className = 'bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs px-3 py-1.5 rounded-lg transition';
  yes.addEventListener('click', () => {
    applyLocation({
      state: place.state || 'all', city: place.city || '', zip: place.zip || '',
      // a precise GPS fix can search by coordinates; an approximate guess searches by the named ZIP only
      lat: place.approximate ? null : fix.lat, lon: place.approximate ? null : fix.lon,
    }, 'device');
  });
  const no = document.createElement('button');
  no.type = 'button';
  no.textContent = 'Not right';
  no.className = 'bg-[#EFE3C8] hover:bg-[#D5C39A] border border-[#CBB98D] text-[#3E2B1F] text-xs px-3 py-1.5 rounded-lg transition';
  no.addEventListener('click', () => box.classList.add('hidden'));
  row.appendChild(yes);
  row.appendChild(no);
  box.appendChild(row);
  box.classList.remove('hidden');
}

let detectBtn = null;
function autoDetectLocation(event) {
  const btn = event.currentTarget;
  const idle = btn.innerHTML;
  detectBtn = btn;
  if (!navigator.geolocation) {
    showDeviceMessage("Your browser can't share a location. Pick a region or type a ZIP instead.");
    return;
  }
  // the target icon spins while it looks (Jason, 2026-11-02: never a button that looks dead); a worded button says so
  const icon = btn.classList.contains('tv-pbox-tgt') || btn.classList.contains('lm-tgt') || btn.classList.contains('cx');
  if (icon && window.TvAsk && TvAsk.busy) TvAsk.busy(btn, true); else btn.textContent = 'Finding you…';
  const done = () => { if (icon && window.TvAsk && TvAsk.busy) TvAsk.busy(btn, false); btn.innerHTML = idle; };
  navigator.geolocation.getCurrentPosition(async (pos) => {
    try {
      const q = new URLSearchParams({ lat: pos.coords.latitude, lon: pos.coords.longitude });
      if (Number.isFinite(pos.coords.accuracy)) q.set('accuracy', Math.round(pos.coords.accuracy));
      const res = await fetch('/api/place.php?' + q.toString());
      const data = await res.json();
      if (!(data && data.success && data.place)) throw new Error((data && data.error) || 'lookup failed');
      // Tapping Auto-detect is the choice: apply it at once, as the town area it's in when we know one, so every page
      // (Let's Explore, the directory) follows it. Changing it is one tap on the place again. (decisions/0058 §8)
      // The town it found, by name ("St. Johns"), never its greater area (Jason, 2026-11-12: "i expected the ST. johns town not
      // the greater area"): the town and the miles around it; Let's Explore still opens the area the town is in.
      const pl = data.place;
      const town = (pl.town && pl.town.name) || pl.city || '';
      applyLocation({
        state: pl.state || 'all', city: town, zip: pl.zip || '',
        // a precise GPS fix can search by coordinates; an approximate guess searches by the named ZIP only
        lat: pl.approximate ? null : pos.coords.latitude, lon: pl.approximate ? null : pos.coords.longitude,
        scope: null, townName: town,
      }, 'device');
    } catch (e) {
      console.error('Device place lookup failed:', e);
      showDeviceMessage("We got a location from your device but couldn't name the area. Pick a region or type a ZIP instead.");
    } finally {
      done();
    }
  }, (err) => {
    done();
    const msgs = {
      1: 'Location access was blocked. You can allow it in your browser settings, or pick a region or type a ZIP instead.',
      2: "Your device couldn't work out where it is. Pick a region or type a ZIP instead.",
      3: 'Finding your location took too long. Try again, or pick a region or type a ZIP instead.',
    };
    showDeviceMessage(msgs[err && err.code] || msgs[2]);
  }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 });
}

function handleLocationSubmit(event) {
  event.preventDefault();
  currentLocation.state = document.getElementById('loc-state').value;
  currentLocation.city = document.getElementById('loc-city').value.trim() || '';
  currentLocation.zip = document.getElementById('loc-zip').value.trim();
  // A manually typed location replaces any coordinates from a prior
  // auto-detect — same reasoning as switchRegion.
  currentLocation.lat = null;
  currentLocation.lon = null;
  currentLocation.scope = null;
  currentLocation.source = 'picked';
  // A newly submitted location always starts at the default radius —
  // same rule as auto-detect, sign-in restore, and hub pills.
  currentLocation.radius = 10;
  setRadiusUi(10);
  saveCurrentLocation();
  persistAccountHomeLocation(currentLocation);

  updateLocationLabels();
  closeLocationModal();
  notifyLocationChanged();
  // Intentional Query-Driven Results: setting a location alone never
  // renders listings — only refreshes a search that's already active,
  // or resumes one that was waiting on a location to exist.
}

// "Search everywhere": no place at all. Remembered for this tab so a signed-in
// visitor's default isn't re-applied over an explicit choice.
function chooseAnywhere() {
  currentLocation = { state: null, city: null, zip: null, lat: null, lon: null, radius: 10, scope: null, source: 'anywhere' };
  clearSavedLocation();
  try { sessionStorage.setItem('traversence_anywhere', '1'); } catch (e) {}
  setRadiusUi(10);
  updateLocationLabels();
  closeLocationModal();
  notifyLocationChanged();
}

// "Reset to Default": a signed-in visitor goes back to their default saved
// place; anyone else (or someone with no default) goes to "everywhere".
function resetLocationScope() {
  const d = (window.appSavedPlaces || []).find(p => p.is_default);
  const loc = d ? d.location : window.appHomeLocation;
  if (realAuthActive && loc && hasLocationSet(loc)) { applyLocation(loc, 'default'); return; }
  chooseAnywhere();
}

function updateLocationLabels() {
  const hasLocation = hasLocationSet(currentLocation);
  const sc = currentLocation.scope;
  let labelText = 'Set Location';
  if (sc) labelText = sc.label;
  else if (currentLocation.townName) labelText = currentLocation.townName;   // Auto-detect: the town by name, not its ZIP (2026-11-12)
  else if (currentLocation.zip) labelText = `ZIP: ${currentLocation.zip}`;
  else if (currentLocation.city) labelText = currentLocation.city;
  else if (isStateOnly(currentLocation)) labelText = STATE_NAMES[currentLocation.state] || currentLocation.state;
  else if (currentLocation.savedLabel) labelText = currentLocation.savedLabel;   // a named point (a saved place, or a distance search)
  else if (currentLocation.lat != null && currentLocation.lon != null) labelText = 'Current Location';

  // Say where the place came from when it wasn't typed/picked this visit.
  const suffix = currentLocation.source === 'default' ? ' \u00b7 your default'
    : currentLocation.source === 'arrived' ? ' \u00b7 region you came from'
    : currentLocation.source === 'device' ? ' \u00b7 near you' : '';

  document.getElementById('search-location-clear')?.classList.toggle('hidden', !hasLocation);
  const statusEl = document.getElementById('collapse-status-text');
  if (statusEl) statusEl.textContent = hasLocation
    ? ((sc || isStateOnly(currentLocation)) ? `${labelText}${suffix}` : `${labelText} (${currentLocation.radius || 10} mi radius)${suffix}`)
    : `All Areas (${currentLocation.radius || 10} mi radius)`;

  // The menu's "Set Location" item shows the current place (there is no
  // separate location icon in the header any more).
  const menuLabel = document.getElementById('menu-location-label');
  if (menuLabel) menuLabel.textContent = hasLocation ? `${labelText}${suffix}` : 'Not set';

  window.appHasLocation = hasLocation;
  (window.tvPlaceBoxes || []).forEach(f => { try { f(); } catch (e) {} });   // the Town or ZIP boxes show the place
  if (typeof onPlaceLabelChanged === 'function') onPlaceLabelChanged();
  if (typeof window.updateHeaderIconStates === 'function') window.updateHeaderIconStates();
}

// Radius slider readout — only exists on search pages.
function setRadiusUi(v) {
  const slider = document.getElementById('radius-slider');
  if (slider) slider.value = v;
  const label = document.getElementById('radius-val');
  if (label) label.textContent = v;
}

// Tells the host page the location changed. See file header.
function notifyLocationChanged() {
  if (typeof onLocationChanged === 'function') onLocationChanged();
  // the search panel goes back to 10 mi around the new place (js/search-panel.js)
  try { window.dispatchEvent(new Event('tv:location')); } catch (e) {}
}

// Account-menu dropdown removed 2026-09-18 — profile is now a single
// icon linking directly to /login.php or /user/dashboard.php (see
// includes/header.php, js/header-nav.js). The site-menu (hamburger)
// dropdown's own outside-click/Escape handling now lives in
// header-nav.js instead of here.
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    // the picker closes first; whatever it was opened over (the search) stays open
    if (!document.getElementById('location-modal').classList.contains('hidden')) { closeLocationModal(); e.stopImmediatePropagation(); }
  }
});
// A click on the dimmed backdrop (outside the box) closes it too. Pressing
// inside and letting go outside (e.g. selecting text) doesn't count.
(function () {
  let downOnBackdrop = false;
  document.addEventListener('mousedown', (e) => { downOnBackdrop = !!(e.target && e.target.id === 'location-modal'); }, true);
  document.addEventListener('click', (e) => {
    if (e.target && e.target.id === 'location-modal' && downOnBackdrop) closeLocationModal();
    downOnBackdrop = false;
  });
})();

// Restore location state and confirm the session on every page load. The
// listener is registered when this script loads, i.e. before any page-inline
// DOMContentLoaded handler, so currentLocation is ready for them.
// A visit that arrives through a region (?hub=, ?geo=, ?cluster=, e.g. from a
// Discovery hub page) uses that region as its place. It beats any saved default.
function readArrivalRegion() {
  const p = new URLSearchParams(location.search);
  const pairs = [['cluster', 'cluster'], ['geo', 'geo_hub'], ['hub', 'hub']];
  for (const [key, level] of pairs) {
    const v = (p.get(key) || '').trim().toLowerCase();
    if (/^[a-z0-9][a-z0-9-]{0,99}$/.test(v)) return { key, level, slug: v };
  }
  return null;
}

document.addEventListener('DOMContentLoaded', () => {
  wirePlaceBoxes();   // the Town or ZIP boxes on the page
  // Registered before any page-inline DOMContentLoaded handler, so
  // currentLocation is ready for them. window.authReady lets a page wait for
  // the session (and saved places) before deciding whether a default applies.
  window.authReady = checkRealAuthState();

  // This tab's place only (see saveCurrentLocation). Never restored from a
  // previous visit: a lasting default is a saved place, applied on search.
  localStorage.removeItem('traversence_location');
  const savedLocation = sessionStorage.getItem('traversence_location');
  if (savedLocation) {
    try { currentLocation = JSON.parse(savedLocation); window.tvLocationRestored = true; } catch (e) {}   // still set from earlier in this visit
  }

  const arrival = readArrivalRegion();
  // the page is the place already chosen (Auto-detect, a saved place …): keep how it was chosen, not "came from"
  const same = arrival && currentLocation && currentLocation.scope && currentLocation.scope.slug === arrival.slug && currentLocation.source && currentLocation.source !== 'arrived';
  // only a first arrival takes the page's region as its place. Once a place is set, opening another area's page (a town
  // from search, a link) shows that area and leaves the place alone: it changes only when the person changes it (Jason,
  // 2026-10-07, decisions/0060)
  const placeSet = window.tvLocationRestored && typeof hasLocationSet === 'function' && hasLocationSet(currentLocation);
  if (arrival && !same && !placeSet) {
    window.tvLocationRestored = false;   // this page set it, not an earlier visit
    currentLocation = {
      state: 'all', city: '', zip: '', lat: null, lon: null, radius: 10, source: 'arrived',
      scope: { level: arrival.level, slug: arrival.slug, label: arrival.slug },
    };
    saveCurrentLocation();
    // Name it properly (and drop it if the region doesn't exist).
    fetch('/api/place.php?' + arrival.key + '=' + encodeURIComponent(arrival.slug))
      .then(r => r.json().then(d => ({ ok: r.ok, d })))
      .then(({ ok, d }) => {
        if (currentLocation.scope && currentLocation.scope.slug === arrival.slug) {
          if (ok && d && d.success && d.place) {
            currentLocation.scope.label = d.place.label || arrival.slug;
            saveCurrentLocation();
            updateLocationLabels();
          } else {
            currentLocation = { state: null, city: null, zip: null, lat: null, lon: null, radius: 10 };
            clearSavedLocation();
            updateLocationLabels();
            notifyLocationChanged();
          }
        }
      })
      .catch(() => { /* keep the slug as its label */ });
  }

  // Radius is a live, in-session dial (see applyRadiusSlider), never carried
  // over: every page load starts at the default.
  currentLocation.radius = 10;
  setRadiusUi(currentLocation.radius || 10);
  updateLocationLabels();

  // Signed in with no place yet this visit (just signed in, a new tab): start from your default place, on every page
  // (Jason, 2026-11-05: signed in, the search said "Choose a place" though Home was the default). A region you
  // arrived through still wins, and choosing "everywhere" this visit keeps it.
  if (!arrival) window.authReady.then(() => { try { applyDefaultPlaceIfAny(); } catch (e) {} });
});
