// Your own quick searches counted (Jason, 2026-11-22: "Count them", B2's follow-up; handoffs/own-quick-counts.md),
// rendered at 1280 and 390 on the harness with search_index seeded (4 pool listings, a pizza place). Each of your own
// phrases shows the number its search's Results tab gives; "open now" none (it changes by the minute); one that finds
// nothing none; ⊕ and ✎ Done bring the new phrase's count; counting records nothing (no_side: no crawler, no counts); a count past its hour is cleared.
// Run: node own-quick-counts.cjs [base] — mysql on the path with the harness database tvh.
const { chromium } = require('playwright');
const cp = require('child_process');
const BASE = process.argv[2] || 'http://127.0.0.1:8790';
let pass = 0, fail = 0; const ok = (c, m) => { c ? pass++ : fail++; console.log((c ? '  ✓ ' : '  ✗ ') + m); };
const sql = q => { try { return cp.execFileSync('mysql', ['-N', '-B', 'tvh', '-e', q]).toString().trim(); } catch (e) { return 'n/a'; } };
(async () => {
  const b = await chromium.launch();
  sql("DELETE FROM search_index_state WHERE k LIKE 'pm:%' OR k = 'pmsweep'");
  sql("INSERT INTO search_index_state (k, v, v_long) VALUES ('pm:old0000000000000000', '{\"t\":1000,\"n\":3}', '{\"t\":1000,\"n\":3}')");   // a count past its hour
  const side0 = sql('SELECT COUNT(*) FROM search_counts');
  for (const w of [1280, 390]) {
    console.log('— ' + w + 'px');
    const c = await b.newContext({ viewport: { width: w, height: 900 }, userAgent: 'Mozilla/5.0 Chrome/130' });
    await c.addInitScript(() => { try { if (!sessionStorage.getItem('seeded')) { localStorage.setItem('tv_my_searches', JSON.stringify(['pools', 'open now', 'zzqx'])); localStorage.setItem('tv_reach3', '8'); sessionStorage.setItem('seeded', '1'); } } catch (e) {} });
    const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
    await p.route(/^https:\/\/(?!127)/, r => r.abort());
    let asked = '';
    p.on('request', r => { const u = new URL(r.url()); if (u.pathname === '/api/search.php' && u.searchParams.has('presets')) asked = u.searchParams.get('mine') || ''; });
    await p.goto(BASE + '/faq.php', { waitUntil: 'load' }); await p.waitForTimeout(600);
    await p.evaluate(() => TvSearch.open('')); await p.waitForTimeout(2500);
    const rows = () => p.evaluate(() => [...document.querySelectorAll('.tvs-presets .tvs-qr')].map(r => ({ t: r.querySelector('span').textContent, n: (r.querySelector('.tvs-n') || {}).textContent || '' })));
    let r = await rows();
    const tot = async q => { const j = await p.evaluate(async q => (await fetch('/api/search.php?q=' + encodeURIComponent(q) + '&reach=all')).json(), q); return (j.results || {}).total || 0; };
    const poolsTotal = await tot('pools');
    ok(/pools/.test(asked), 'your phrases go with the Quick Search request (' + asked + ')');
    if (w === 1280) ok(sql("SELECT COUNT(*) FROM search_index_state WHERE k = 'pm:old0000000000000000'") === '0' && +sql("SELECT COUNT(*) FROM search_index_state WHERE k LIKE 'pm:%'") >= 1, 'a count past its hour is cleared; fresh ones are kept');
    ok((r.find(x => x.t === 'pools') || {}).n === String(poolsTotal) && poolsTotal === 4, '"pools" shows ' + (r.find(x => x.t === 'pools') || {}).n + ', what its search finds (' + poolsTotal + ')');
    ok((r.find(x => x.t === 'open now') || { n: 'x' }).n === '', '"open now" shows no number: it changes by the minute');
    ok((r.find(x => x.t === 'zzqx') || { n: 'x' }).n === '', 'a phrase that finds nothing shows no number');
    // ⊕: the typed words become a quick search, and its count comes
    if (w > 800) {   // ⊕ beside Quick Search (on phones it sits below the results; ✎ below is pressed at both widths)
    await p.fill('.tvs-in2, .tvs-in', 'swimming'); await p.keyboard.press('Enter'); await p.waitForTimeout(2000);   // its results, then ⊕
    await p.locator('[data-qadd]:visible').first().click(); await p.waitForTimeout(2000);
    await p.fill('.tvs-in2, .tvs-in', ''); await p.waitForTimeout(1200);
    r = await rows(); const sw = await tot('swimming');
    ok((r.find(x => x.t === 'swimming') || {}).n === String(sw) && sw > 0, '⊕ adds "swimming" and its count comes: ' + (r.find(x => x.t === 'swimming') || {}).n);
    }
    // ✎: add one in the editor, Done: its count comes
    await p.locator('[data-pedit]:visible').first().click(); await p.waitForTimeout(300);
    await p.fill('.tvs-padd .tvs-phr', 'pizza place'); await p.click('[data-pnew]'); await p.waitForTimeout(200);
    await p.click('[data-pdone]'); await p.waitForTimeout(2000);
    r = await rows(); const pz = await tot('pizza place');
    ok(r.some(x => x.t === 'pizza place') && (r.find(x => x.t === 'pizza place').n === (pz ? String(pz) : '')), '✎ Done: "pizza place" listed, with ' + (pz ? 'its count ' + pz : 'no number (its search finds nothing here)'));
    const sideNow = sql('SELECT COUNT(*) FROM search_counts');
    ok(sideNow === side0, 'counting recorded nothing: the place\'s search counts unchanged (' + sideNow + '); the crawler isn\'t asked (no_side, as while typing)');
    const sw2 = await p.evaluate(() => document.documentElement.scrollWidth);
    ok(sw2 <= w, 'no sideways scroll (' + sw2 + ')');
    ok(errs.length === 0, 'no script errors' + (errs.length ? ': ' + errs.join(' | ') : ''));
    if (w === 390) await p.screenshot({ path: '/tmp/claude-0/shots/oqc-390.png' });
    if (w === 1280) await p.screenshot({ path: '/tmp/claude-0/shots/oqc-1280.png' });
    await c.close();
  }
  await b.close();
  console.log(pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
