// tools/page-audit/search-probe.mjs — what the live search makes of real searches (Jason, 2026-11-10: "search is
// failing to perform at the level I expect"). Each probe runs /api/search.php as the visitor would (signed out, with the
// place they'd have set), and records what search read as the place, the words left, the guide it offers, and the
// first results. Read only. Writes site-audit/search-probe.md and site-audit/results/search-probe.json.
// Run: SITE=https://traversence.com node tools/page-audit/search-probe.mjs  (.github/workflows/search-probe.yml)
import { writeFileSync, mkdirSync } from 'node:fs';

const SITE = process.env.SITE || 'https://traversence.com';
// [what was typed, the place already set (query string), what a person expects]
const PROBES = [
  ['Dana point', '', 'Dana Point, CA and its guide (it has one), not Point, TX'],
  ['Dana Point, CA', '', 'Dana Point, CA: its guide, places and listings'],
  ['dana point california', '', 'Dana Point, CA'],
  ['restaurants in Dana Point, CA', '', 'places to eat in Dana Point'],
  ['Places to eat around dana point, ca', '', 'places to eat in Dana Point'],
  ['Phoenix, az', '', 'Phoenix, AZ: its guide, listings, places'],
  ['Phoenix', '', 'Phoenix, AZ (the pilot state first)'],
  ['restaurants', 'city=Phoenix&state=AZ&reach=25', 'places to eat in Phoenix'],
  ['pizza in phoenix', '', 'pizza places in Phoenix'],
  ['coffee', 'zip=85302&reach=10', 'coffee near Glendale, AZ 85302'],
  ['coffee near me', 'city=Phoenix&state=AZ&reach=10', 'coffee shops in Phoenix (OpenStreetMap at once, ours once crawled)'],
  ['Things to do near me', 'zip=85302&reach=25', 'things to do around 85302'],
  ['Glendale, AZ', '', 'Glendale, AZ: its guide (built on demand) and listings'],
  ['hotels in sedona', '', 'places to stay in Sedona'],
  ['show low', '', 'Show Low, AZ (a pilot town)'],
  ['greer arizona', '', 'Greer, AZ (a pilot town)'],
  ['Four Corners', '', 'Four Corners Monument, not Four Corners, WY'],
  ['grand canyon', '', 'Grand Canyon National Park'],
  ['four corners monument', '', 'Four Corners Monument (a landmark added by hand)'],
  ['laguna art museum', '', 'Laguna Art Museum (found for the Laguna Beach guide)'],
  ['plumber in show low', '', 'plumbers in Show Low'],
];

const s = (v) => (v == null ? '' : String(v));
async function probe([q, ctx, expect]) {
  const url = `${SITE}/api/search.php?q=${encodeURIComponent(q)}${ctx ? '&' + ctx : ''}`;
  const out = { q, ctx, expect, url: url.replace(SITE, ''), status: 0 };
  const t0 = Date.now();
  try {
    const r = await fetch(url, { headers: { 'User-Agent': 'traversence-search-probe' } });
    out.status = r.status; out.ms = Date.now() - t0;
    const d = await r.json();
    const chips = (d.chips || []).map((c) => `${s(c.type)}: ${s(c.label)}`);
    out.place = s(d.place?.label || (d.chips || []).find((c) => c.type === 'place')?.label);
    out.chips = chips; out.text = s(d.text);
    out.guide = d.guide ? s(d.guide.label || d.guide.title || '') + (d.guide.state ? ` [${d.guide.state}]` : '') + (d.guide.text ? `: ${d.guide.text}` : '') : '';
    out.ask = d.ask ? s(d.ask.title || d.ask.q || d.ask.kind || 'yes') : '';
    const res = d.results || {};
    out.total = res.total ?? (res.items || []).length;
    out.kinds = res.kinds || null;
    out.note = s(res.note);
    out.osm = res.osm ? `${s(res.osm.kind)} around ${s(res.osm.place)}` : '';
    out.mentions = (res.mentions?.items || []).length;
    out.first = (res.items || []).slice(0, 6).map((i) => `${s(i.label)} (${s(i.kind)}${i.sub ? ' · ' + s(i.sub) : ''})`);
    out.groups = (d.groups || []).map((g) => `${s(g.title || g.kind)} ${(g.items || []).length}`);
    out.did_you_mean = s(d.did_you_mean?.q || d.did_you_mean);
  } catch (e) { out.error = String(e).slice(0, 200); out.ms = Date.now() - t0; }
  return out;
}

// as someone types (Jason, 2026-11-10: "we should be showing possible locations as someone types"): the first rows
const TYPING = [['Dana p', ''], ['pho', ''], ['phoenix', ''], ['pizza in pho', ''], ['sedo', ''], ['glendale', 'city=Phoenix&state=AZ'], ['show l', ''], ['four cor', ''], ['four corners', ''], ['four corners mon', ''], ['dana point harb', ''], ['laguna art', '']];
async function typed([q, ctx]) {
  const url = `${SITE}/api/search.php?suggest=1&q=${encodeURIComponent(q)}${ctx ? '&' + ctx : ''}`;
  try {
    const d = await (await fetch(url, { headers: { 'User-Agent': 'traversence-search-probe' } })).json();
    return { q, ctx, first: (d.items || []).slice(0, 6).map((i) => `${s(i.label)} (${s(i.kind)}${i.crumb ? ' · ' + s(i.crumb) : ''})`) };
  } catch (e) { return { q, ctx, error: String(e).slice(0, 160) }; }
}

const results = [];
for (const p of PROBES) results.push(await probe(p));   // one at a time: never a load on the site
const typing = [];
for (const t of TYPING) typing.push(await typed(t));

const cell = (v) => s(v).replace(/\|/g, '/').replace(/\n/g, ' ');
let md = `# Search probe\n\nWhat the live search makes of real searches, signed out. Generated by \`tools/page-audit/search-probe.mjs\` on ${new Date().toISOString().slice(0, 16).replace('T', ' ')} UTC against ${SITE}. Don't edit by hand.\n\n`;
for (const r of results) {
  md += `## ${cell(r.q)}${r.ctx ? ` · with ${cell(r.ctx)}` : ''}\n\n`;
  md += `- **Expected:** ${cell(r.expect)}\n`;
  if (r.error) { md += `- **Error:** ${cell(r.error)}\n\n`; continue; }
  md += `- **Read as:** place *${cell(r.place) || 'none'}*, words *${cell(r.text) || 'none'}*${r.chips.length ? ` (chips: ${cell(r.chips.join('; '))})` : ''}\n`;
  if (r.guide) md += `- **Guide offered:** ${cell(r.guide)}\n`;
  if (r.ask) md += `- **Asks:** ${cell(r.ask)}\n`;
  md += `- **Results:** ${r.total ?? 0}${r.kinds ? ` (${cell(Object.entries(r.kinds).map(([k, v]) => `${k} ${typeof v === 'object' ? JSON.stringify(v) : v}`).join(', '))})` : ''}${r.note ? ` · ${cell(r.note)}` : ''} · ${r.ms} ms, HTTP ${r.status}\n`;
  if (r.osm) md += `- **OpenStreetMap offered:** ${cell(r.osm)}\n`;
  if (r.mentions) md += `- **Mentioning:** ${r.mentions}\n`;
  if (r.first.length) md += `- **First:** ${cell(r.first.join('; '))}\n`;
  if (r.groups.length) md += `- **Groups:** ${cell(r.groups.join('; '))}\n`;
  if (r.did_you_mean) md += `- **Did you mean:** ${cell(r.did_you_mean)}\n`;
  md += '\n';
}
md += `## As someone types\n\nThe first rows of the suggestions while typing.\n\n`;
for (const t of typing) md += `- **${cell(t.q)}**${t.ctx ? ` (with ${cell(t.ctx)})` : ''}: ${t.error ? 'Error: ' + cell(t.error) : cell(t.first.join('; ')) || 'nothing'}\n`;
md += '\n';
mkdirSync('site-audit/results', { recursive: true });
writeFileSync('site-audit/search-probe.md', md);
writeFileSync('site-audit/results/search-probe.json', JSON.stringify({ results, typing }, null, 1));
console.log(`${results.length} searches probed`);
