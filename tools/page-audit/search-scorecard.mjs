// tools/page-audit/search-scorecard.mjs — the search, scored (Jason, 2026-11-10: "the search is hit or miss … it should
// have every thing already ironed out to be super fast, efficient, and accurate for any word or combination of terms").
// Each case is a search a person would type, the place they'd have set, and checks a person would make of the answer:
// the place it read, what leads, what must never lead, how far the first results are, and how fast it answered. Typing
// cases check the first suggestions. Read only, signed out, one request at a time.
// Writes site-audit/search-scorecard.md and site-audit/results/search-scorecard.json.
// Run: SITE=https://traversence.com node tools/page-audit/search-scorecard.mjs  (.github/workflows/search-probe.yml)
import { writeFileSync, mkdirSync } from 'node:fs';

const SITE = (process.env.SITE || 'https://traversence.com').replace(/\/$/, '');
const PHX = 'city=Phoenix&state=AZ&reach=25';
const PHXME = 'city=Phoenix&state=AZ&reach=10';
const SL = 'city=Show Low&state=AZ&reach=25';
const GLN = 'zip=85302&reach=25';
const SJ = 'city=Saint Johns&state=AZ&reach=25';
const FAST = 600, FAST_TYPING = 300;   // ms: a search answers inside FAST, a suggestion inside FAST_TYPING

// Checks (all optional): place /re/ the place read; noPlace true: none read; lead /re/ one of the first `top` (default 5)
// rows matches; leadAll /re/ each of the first `top` rows matches; never /re/ none of the first `top` rows matches;
// within N: each of the first `top` rows with a distance is within N mi; min N: at least N results; chip /re/ a chip
// (type: label) matches; noChip /re/ none does. Rows read as "label · kind · category · sub".
const CASES = [
  // Places: a town, said any way, is the town
  ['Places', 'Phoenix', '', { place: /^Phoenix/ }],
  ['Places', 'Phoenix, az', '', { place: /^Phoenix/ }],
  ['Places', 'Dana point', '', { place: /Dana Point, CA/ }],
  ['Places', 'show low', '', { place: /Show Low, AZ/, min: 1 }],
  ['Places', 'Showlow', '', { place: /Show Low, AZ/ }],
  ['Places', 'flagstaf', '', { dym: /flagstaff/i }],   // a misspelled town is offered, never read (decisions/0063, 0064)
  ['Places', 'sedona az', '', { place: /^Sedona/ }],
  ['Places', 'Glendale', PHX, { place: /^Glendale/ }],
  ['Places', 'Four Corners', '', { lead: /Four Corners Monument/, top: 3 }],
  ['Places', 'grand canyon', '', { lead: /Grand Canyon/, top: 3 }],
  ['Places', 'laguna art museum', '', { lead: /Laguna Art Museum/, noPlace: true }],
  // A kind of thing in a town
  ['In a town', 'pizza in phoenix', '', { place: /Phoenix, AZ/, min: 3, lead: /pizz/i }],
  ['In a town', 'plumber in show low', '', { place: /Show Low/, leadAll: /plumb/i, top: 3 }],
  ['In a town', 'hotels in sedona', '', { place: /Sedona/, lead: /listing|lodg|hotel|inn|resort/i, never: /topic/, top: 3 }],
  ['In a town', 'restaurants', PHX, { min: 5, leadAll: /listing/, top: 3 }],
  ['In a town', 'mexican food', SL, { lead: /mexic/i, never: /topic/, top: 3 }],
  ['In a town', 'car repair', SL, { leadAll: /auto|car|repair|mechanic|tire/i, top: 3 }],
  ['In a town', 'tires', SL, { lead: /tire/i, top: 3 }],
  ['In a town', 'vet', SL, { lead: /veterin|animal|pet/i, never: /veteran/i, top: 3 }],
  ['In a town', 'dentist', SL, { leadAll: /dent/i, top: 3 }],
  ['In a town', 'groceries', SL, { lead: /grocer|market|food|safeway|walmart|bashas|fry/i, top: 3 }],
  ['In a town', 'gas station', SL, { lead: /gas|fuel|station|circle k|chevron|shell|maverik/i, top: 3 }],
  ['In a town', 'coffee', GLN, { lead: /coffee|cafe|espresso|starbucks|dutch bros/i, never: /topic/, top: 3 }],
  // Near me: nearest first, never far
  ['Near me', 'coffee near me', PHXME, { leadAll: /coffee|cafe|espresso|starbucks|dutch/i, within: 10, top: 3, never: /Peak|Rock/ }],
  ['Near me', 'pizza near me', PHXME, { lead: /pizz/i, within: 10, top: 3 }],
  ['Near me', 'gas near me', PHXME, { lead: /gas|fuel|circle k|chevron|shell|qt|quiktrip/i, within: 10, top: 3 }],
  ['Near me', 'food near me', PHXME, { min: 3, within: 10, top: 3 }],
  ['Near me', 'hiking near me', PHXME, { lead: /trail|peak|mountain|park|preserve/i, within: 30, top: 3 }],
  ['Near me', 'parks near me', PHXME, { lead: /park/i, within: 10, top: 3 }],
  ['Near me', 'things to do near me', GLN, { min: 3, within: 25, top: 3 }],
  ['Near me', 'near me', PHXME, { min: 3, within: 10, top: 3 }],
  ['Near me', 'urgent care near me', PHXME, { lead: /urgent|clinic|care|medical|hospital/i, within: 10, top: 3 }],
  // Who and why: the context, not the words
  ['Context', 'place to take my kids', PHXME, { never: /Fast Food|Takeout|Trailhead|Wilderness/i, within: 15, top: 5, min: 3, chip: /outing: With/i }],
  ['Context', 'things to do with kids', PHX, { never: /Fast Food|Takeout|Wilderness/i, within: 25, top: 5, min: 3 }],
  ['Context', 'kids activities', PHX, { never: /Fast Food|Takeout|Wilderness/i, min: 3 }],
  ['Context', 'family friendly restaurants', PHX, { leadAll: /listing/, top: 3, chip: /who|famil/i }],
  ['Context', 'date night', PHX, { never: /Fast Food|Takeout|Trailhead|Plumb/i, min: 3, top: 5 }],
  ['Context', 'rainy day activities', PHX, { never: /Trailhead|Wilderness|Peak/i, min: 3, top: 5 }],
  ['Context', 'something fun to do', PHX, { never: /Plumb|Contractor|Real Estate|Fast Food/i, min: 3, top: 5 }],
  ['Context', 'where to take my dog', PHX, { never: /Fast Food|Takeout/i, min: 1, top: 5 }],
  ['Context', 'cheap eats', PHX, { leadAll: /listing/, top: 3 }],
  ['Context', 'romantic dinner', PHX, { leadAll: /listing/, top: 3, never: /Fast Food/i }],
  ['Context', 'I need a plumber', SL, { leadAll: /plumb/i, top: 3 }],
  ['Context', 'my car broke down', SL, { lead: /tow|auto|repair|mechanic/i, top: 3 }],
  ['Context', 'where can I go fishing', SL, { lead: /lake|reservoir|river|creek|fish/i, top: 3 }],
  ['Context', 'scenic drive', SL, { never: /Plumb|Driving School|Fast Food/i, min: 1, top: 3 }],
  // The words as typed are the query (Jason, 2026-11-11: "If someone types arizona food that is their desired search
  // criteria … There is a line between suggestions and query"): answered as written, the place and the thing
  ['As typed', 'arizona food', '', { place: /Arizona/i, lead: /listing|OpenStreetMap/i, top: 3, never: /topic/ }],
  ['As typed', 'new mexican food', '', { lead: /listing|OpenStreetMap/i, top: 3 }],
  ['As typed', 'mexican food', PHX, { lead: /mexic|listing|OpenStreetMap/i, top: 3 }],
  ['As typed', 'italian food', PHX, { lead: /ital|pizz|listing|OpenStreetMap/i, top: 3 }],
  ['As typed', 'navajo culture', '', { min: 1 }],
  // Words run together, spelled wrong, or said two ways
  ['Spelling', 'funhouse', PHX, { never: /Contractor|Real Estate|Painter|House Clean/i, top: 5 }],
  ['Spelling', 'fun house near me', PHXME, { never: /Contractor|Real Estate|Painter|House Clean/i, top: 5 }],
  ['Spelling', 'resturants', SL, { dym: /restaurant/ }],   // a slip is offered, never changed (decisions/0063, 0064)
  ['Spelling', 'piza', SL, { dym: /pizza/ }],
  ['Spelling', 'coffe', SL, { lead: /coffee/i, top: 3 }]   ,
  ['Spelling', 'plumbr', SL, { dym: /plumber/ }],
  ['Spelling', 'hikeing trails', SL, { lead: /trail|peak|lake|forest/i, top: 3 }],
  ['Spelling', 'icecream', PHX, { dym: /ice ?cream/ }],
  ['Spelling', 'bbq', SL, { lead: /bbq|barbecue|barbeque|smoke/i, top: 3 }],
  ['Spelling', 'hair cut', SL, { lead: /hair|barber|salon/i, top: 3 }],
  ['Spelling', 'auto parts', SL, { lead: /auto|parts|o'?reilly|napa|autozone/i, top: 3 }],
  // Named things: the name, whole
  ['Names', 'Four Corners Monument', '', { lead: /Four Corners Monument/, top: 1 }],
  ['Names', 'Fool Hollow Lake', '', { lead: /Fool Hollow/, top: 1 }],
  ['Names', 'Camelback Mountain', '', { lead: /Camelback/, top: 1 }],
  ['Names', 'Harbor Freight', SL, { lead: /Harbor Freight/, top: 1 }],
  ['Names', 'walmart', SL, { lead: /walmart/i, top: 3 }],
  // Our own index: decisions/0157's test set (Jason, 2026-11-12/13: "garbage" near St. Johns found nothing though Blue
  // Hills Environmental and the county landfill are blocks away). A change to reading pages or searches ships only when
  // these hold; each new slip found is added here.
  ['Our index', 'garbage', SJ, { lead: /Blue Hills|landfill|garbage|trash|waste|refuse/i, top: 5 }],
  ['Our index', 'trash collection', SJ, { lead: /Blue Hills|landfill|garbage|trash|waste|refuse/i, top: 5 }],
  ['Our index', 'garabe', SJ, { dym: /garbage/ }],
  ['Our index', 'places to shoot', SJ, { lead: /range|shoot|gun|rifle|firearm/i, never: /vaccin|immuniz|photo/i, top: 3 }],
  ['Our index', 'places to stay', SJ, { lead: /lodg|hotel|motel|inn|rv|camp|cabin/i, never: /topic/, top: 3 }],
  ['Our index', 'lunch', SJ, { lead: /restaurant|food|cafe|diner|grill|pizza|burger|mexican/i, top: 3 }],
  ['Our index', 'Besh-Ba-Gowah', '', { lead: /Besh-?Ba-?Gowah/i, top: 2 }],
];

// as someone types: the first rows of the suggestions
const TYPING = [
  ['pho', '', { lead: /^Phoenix(, AZ| ·)/, top: 1 }],
  ['phoenix', '', { lead: /^Phoenix(, AZ| ·)/, top: 1, never: /Phoenixville/, neverTop: 1 }],
  ['Dana p', '', { lead: /Dana Point/, top: 1, never: /Counselor|Pipe/ }],
  ['sedo', '', { lead: /Sedona/, top: 1 }],
  ['glendale', PHX, { lead: /Glendale, AZ/, top: 1 }],
  ['show l', '', { lead: /Show Low/, top: 1, never: /Live Music/ }],
  ['four cor', '', { lead: /Four Corners Monument/, top: 2, never: /Lumber|Beer/ }],
  ['four corners', '', { lead: /Four Corners Monument/, top: 1 }],
  ['dana point harb', '', { lead: /Dana Point Harbor/, top: 3, never: /Harbor Freight|Harbor Pump/ }],
  ['laguna art', '', { lead: /Laguna Art Museum/, top: 3 }],
  ['mexi', '', { lead: /New Mexico|Mexico/, top: 2, never: /Mexia/, neverTop: 2 }],
  ['ital', '', { lead: /Italy|Italian/, top: 3 }],
  ['nava', '', { lead: /Navajo/, top: 3 }],
  ['dog park', '', { lead: /Dog parks/, top: 1, never: /sleep in my car|parkway/i }],
  ['playg', '', { lead: /Playground/, top: 2 }],
  ['AZ a', 'city=Saint Johns&state=AZ', { min: 1 }],   // 2026-11-11: an empty panel on a computer
  ['az am', 'city=Saint Johns&state=AZ', { min: 1 }],
  // the page sets the order (the Chameleon Filter while typing, 2026-11-11): Let's Explore, then the Directory
  ['mexi', 'track=explore', { lead: /New Mexico|Mexico/, top: 1 }],
  ['mexi', 'track=local', { lead: /Mexican food/, top: 1 }],
  ['trail', SL + '&track=explore', { lead: /trail|hik/i, top: 2 }],
  ['pizz', SL + '&track=local', { lead: /pizz/i, top: 2 }],
  ['coff', SL, { lead: /coffee/i, top: 3 }],
  ['pizz', SL, { lead: /pizz/i, top: 3 }],
  ['plum', SL, { lead: /plumb/i, top: 3 }],
  ['hik', SL, { lead: /hik|trail/i, top: 3 }],
  ['place to take my k', PHX, { lead: /kid|famil|child/i, top: 3 }],
  ['things to do', PHX, { lead: /things to do|do|activit|attraction|outdoor|park|museum/i, top: 3 }],
  ['coffee near', SL, { lead: /coffee near/i, top: 3 }],
  ['resturant', SL, { lead: /restaurant/i, top: 3 }],
];

const s = (v) => (v == null ? '' : String(v));
const rowOf = (i) => [s(i.label), s(i.kind), s(i.category || i.cat || ''), s(i.sub || i.crumb || '')].filter(Boolean).join(' · ');
function judge(rows, chips, place, total, c, ms, budget, dym = '') {
  const fails = [];
  if (c.dym && !c.dym.test(dym)) fails.push(`no "Did you mean" like ${c.dym}${dym ? ' (offered: ' + dym + ')' : ''}`);
  const top = c.top || 5;
  const head = rows.slice(0, top);
  if (c.place && !c.place.test(place)) fails.push(`place read as "${place || 'none'}"`);
  if (c.noPlace && place) fails.push(`read a place: "${place}"`);
  if (c.min && total < c.min) fails.push(`${total} results`);
  if (c.lead && !head.some((r) => c.lead.test(r.text))) fails.push(`nothing like ${c.lead} in the first ${top}`);
  if (c.leadAll) { const bad = head.filter((r) => !c.leadAll.test(r.text)); if (bad.length || !head.length) fails.push(`not all of the first ${top} fit ${c.leadAll}${bad.length ? ': ' + bad[0].text : ''}`); }
  if (c.never) { const bad = rows.slice(0, c.neverTop || top).filter((r) => c.never.test(r.text)); if (bad.length) fails.push(`shouldn't lead: ${bad[0].text}`); }
  if (c.within) { const far = head.filter((r) => r.mi != null && r.mi > c.within); if (far.length) fails.push(`${far[0].text} is ${Math.round(far[0].mi)} mi (over ${c.within})`); }
  if (c.chip && !chips.some((x) => c.chip.test(x))) fails.push(`no chip like ${c.chip}`);
  if (c.noChip && chips.some((x) => c.noChip.test(x))) fails.push(`chip ${chips.find((x) => c.noChip.test(x))}`);
  if (ms > budget) fails.push(`slow: ${ms} ms`);
  return fails;
}

async function getJson(url) {
  await new Promise((ok) => setTimeout(ok, 250));   // gentle on the host: a burst of requests can get the runner throttled
  const t0 = Date.now();
  let r;
  for (let i = 0; ; i++) {   // a dropped connection is tried again twice, slower each time
    try { r = await fetch(url, { headers: { 'User-Agent': 'traversence-search-scorecard' } }); break; }
    catch (e) { if (i >= 2) throw e; await new Promise((ok) => setTimeout(ok, 2000 * (i + 1))); }
  }
  const raw = await r.text();   // an error page instead of JSON says what broke
  let d; try { d = JSON.parse(raw); } catch (e) { throw new Error(`HTTP ${r.status}: ${raw.replace(/\s+/g, ' ').slice(0, 220)}`); }
  return [d, Date.now() - t0, r.status];
}

const out = [];
for (const [group, q, ctx, c] of CASES) {
  const url = `${SITE}/api/search.php?q=${encodeURIComponent(q)}${ctx ? '&' + ctx : ''}`;
  const o = { group, q, ctx, url: url.replace(SITE, '') };
  try {
    await getJson(url).catch(() => {});   // the first ask warms the server's caches; the second is the one timed
    const [d, ms, status] = await getJson(url);
    const res = d.results || {};
    // what a person sees, in order: the exact business, a situation's answers (each need's places), the results, the places
    // named on pages we read (decisions/0157), and OpenStreetMap's places when the panel is told to ask it (counted by its kind)
    const mk = (i) => ({ text: rowOf(i), mi: typeof i.miles === 'number' ? i.miles : null });
    const rows = [...(d.top && d.top.label ? [mk(d.top)] : []), ...((d.situation?.needs || []).flatMap((n) => (n.items || []).map(mk))), ...(res.items || []).filter((i) => i.kind !== 'topic').map(mk),   // the panel puts categories with Suggestions, not results
                 
                  ...((res.read?.items || []).map((i) => ({ text: `${s(i.label)} · ${s(i.kind)} · named on a page we read`, mi: typeof i.miles === 'number' ? i.miles : null }))),   // our own index (decisions/0157)
                  ...(res.osm ? [{ text: `OpenStreetMap · ${s(res.osm.kind)}`, mi: null }] : [])];
    o.read = [d.situation ? 'situation: ' + s((d.situation.labels || []).join(' + ')) : '', d.unread ? 'unread' : '', d.did_you_mean ? 'did you mean: ' + s(d.did_you_mean.q || d.did_you_mean) : '',
              d.ask ? 'asks: ' + s(d.ask.kind || d.ask.title || 'yes') : '', res.osm ? 'OpenStreetMap: ' + s(res.osm.kind) : '', d.parse ? 'what: ' + s(d.parse.what) : ''].filter(Boolean);
    const chips = (d.chips || []).map((x) => `${s(x.type)}: ${s(x.label)}`);
    const place = s(d.place?.label || (d.chips || []).find((x) => x.type === 'place')?.label);
    const total = Math.max(res.total ?? 0, rows.length);
    Object.assign(o, { status, ms, place, chips, total, first: rows.slice(0, 5).map((r) => r.text + (r.mi != null ? ` · ${Math.round(r.mi * 10) / 10} mi` : '')), note: s(res.note), fails: judge(rows, chips, place, total, c, ms, FAST, [s(d.did_you_mean?.q || d.did_you_mean), ...(d.did_you_mean_all || []).map((x) => s(x.q || x))].filter(Boolean).join(' / ')) });
  } catch (e) { o.fails = ['error: ' + String(e).slice(0, 120)]; }
  o.pass = !o.fails.length;
  out.push(o);
}
const typing = [];
for (const [q, ctx, c] of TYPING) {
  const url = `${SITE}/api/search.php?suggest=1&q=${encodeURIComponent(q)}${ctx ? '&' + ctx : ''}`;
  const o = { group: 'Typing', q, ctx, url: url.replace(SITE, '') };
  try {
    await getJson(url).catch(() => {});
    const [d, ms] = await getJson(url);
    // a place typed alone shows as a card above the list ("What would you like to know about Phoenix, AZ?"): it leads
    const rows = [...(d.ask && d.ask.kind === 'place' && d.ask.place ? [{ text: `${s(d.ask.place)} · place card`, mi: null }] : []),
                  ...(d.intents || []).map((i) => ({ text: `${s(i.label)} · ${s(i.means)}`, mi: null })),   // full words with their intent, above the list
                  ...(d.items || []).map((i) => ({ text: rowOf(i), mi: typeof i.miles === 'number' ? i.miles : null }))];
    Object.assign(o, { ms, first: rows.slice(0, 5).map((r) => r.text), fails: judge(rows, [], '', rows.length, c, ms, FAST_TYPING) });
  } catch (e) { o.fails = ['error: ' + String(e).slice(0, 120)]; }
  o.pass = !o.fails.length;
  typing.push(o);
}

const all = [...out, ...typing];
const groups = [...new Set(all.map((o) => o.group))];
const pct = (a) => (a.length ? Math.round((100 * a.filter((o) => o.pass).length) / a.length) : 0);
const median = (a) => { const v = a.filter((x) => typeof x === 'number').sort((x, y) => x - y); return v.length ? v[Math.floor(v.length / 2)] : 0; };
const cell = (v) => s(v).replace(/\|/g, '/').replace(/\n/g, ' ');
let md = `# Search scorecard\n\nThe search, scored: real and hard searches with what a person would check of the answer. Generated by \`tools/page-audit/search-scorecard.mjs\` on ${new Date().toISOString().slice(0, 16).replace('T', ' ')} UTC against ${SITE}. Don't edit by hand.\n\n`;
md += `**${all.filter((o) => o.pass).length} of ${all.length} pass (${pct(all)}%).** Searches: median ${median(out.map((o) => o.ms))} ms; suggestions while typing: median ${median(typing.map((o) => o.ms))} ms (budgets ${FAST} and ${FAST_TYPING} ms, timed warm).\n\n`;
md += `| Group | Pass | Of |\n|---|---|---|\n${groups.map((g) => { const a = all.filter((o) => o.group === g); return `| ${g} | ${a.filter((o) => o.pass).length} | ${a.length} |`; }).join('\n')}\n\n`;
for (const g of groups) {
  md += `## ${g}\n\n`;
  for (const o of all.filter((x) => x.group === g)) {
    md += `- ${o.pass ? '✓' : '✗'} **${cell(o.q)}**${o.ctx ? ` (${cell(o.ctx)})` : ''} · ${o.ms ?? '?'} ms${o.place ? ` · place ${cell(o.place)}` : ''}${o.total != null && g !== 'Typing' ? ` · ${o.total} results` : ''}\n`;
    if (!o.pass) md += `  - ${cell(o.fails.join('; '))}\n  - First: ${cell((o.first || []).join('; ')) || 'nothing'}${o.chips && o.chips.length ? ` · chips: ${cell(o.chips.join('; '))}` : ''}\n`;
    if (!o.pass && o.read && o.read.length) md += `  - Read: ${cell(o.read.join('; '))}\n`;
  }
  md += '\n';
}
mkdirSync('site-audit/results', { recursive: true });
writeFileSync('site-audit/search-scorecard.md', md);
writeFileSync('site-audit/results/search-scorecard.json', JSON.stringify({ at: new Date().toISOString(), pass: all.filter((o) => o.pass).length, of: all.length, cases: all }, null, 1));
console.log(`${all.filter((o) => o.pass).length}/${all.length} pass`);
