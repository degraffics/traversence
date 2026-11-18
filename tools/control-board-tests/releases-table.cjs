const { chromium } = require('playwright'); const fs = require('fs');
const R = '/home/user/tt/z/root/website';
(async () => {
  const b = await chromium.launch(); const out = [];
  for (const [w, h] of [[390, 900], [1280, 800]]) {
    const p = await b.newPage({ viewport: { width: w, height: h }, deviceScaleFactor: w === 390 ? 2 : 1 });
    await p.route('**/*', r => { const u = new URL(r.request().url());
      if (u.pathname.startsWith('/js/')) return r.fulfill({ body: fs.readFileSync(R + u.pathname), contentType: 'text/javascript' });
      if (u.pathname.startsWith('/admin/api/config/releases')) return r.fulfill({ body: fs.readFileSync('render/t-json.html'), contentType: 'application/json' });
      if (u.pathname === '/api/auth/session.php') return r.fulfill({ body: '{"csrf_token":"x"}', contentType: 'application/json' });
      if (u.pathname === '/admin/config/releases/122') return r.fulfill({ body: fs.readFileSync('render/t-item122.html'), contentType: 'text/html' });
      if (u.pathname === '/admin/config/releases/121') return r.fulfill({ body: fs.readFileSync('render/t-item.html'), contentType: 'text/html' });
      return r.fulfill({ body: fs.readFileSync('render/t-section.html'), contentType: 'text/html' }); });
    await p.goto('http://t.local/admin/config/releases'); await p.waitForTimeout(800);
    const r = await p.evaluate(() => ({ sw: document.documentElement.scrollWidth, marks: [...document.querySelectorAll('.ak-mark')].map(m => m.getAttribute('aria-label')), links: [...document.querySelectorAll('a.ak-nm')].map(a => a.getAttribute('href')).slice(0, 2) }));
    out.push(w + 'px list: ' + JSON.stringify(r));
    await p.screenshot({ path: `render/table-${w}.png`, fullPage: w === 390 });
    await p.goto('http://t.local/admin/config/releases/122'); await p.waitForTimeout(300);
    out.push(w + 'px #122 page: ' + JSON.stringify(await p.evaluate(() => ({ sw: document.documentElement.scrollWidth, open: document.getElementById('rel-122').open, notices: document.querySelectorAll('.rel-body > p[data-tv-status]').length, checksFolded: !document.querySelector('details.rel-checks').open }))));
    await p.goto('http://t.local/admin/config/releases/121'); await p.waitForTimeout(300);
    out.push(w + 'px #121 page: ' + JSON.stringify(await p.evaluate(() => ({ sw: document.documentElement.scrollWidth, notice: (document.querySelector('.rel-body > p[data-tv-status]') || {}).textContent }))));
    if (w === 390) await p.screenshot({ path: 'render/item121-390.png', fullPage: true });
    await p.close();
  }
  console.log(out.join('\n')); await b.close();
})();
