// decisions/0176 release 4, rendered at 1280 and 390: a place photo's staff thread inside its Pulse message (Open it in the
// side panel, Hide with its reason, Take it down and Leave it up asking first, each sent to /api/upload-act.php) and an
// order's (Open the order, the parts to come greyed with their reason in words); nothing scrolls sideways.
// Run after r4.php (its state stays): node r4.cjs <root>
const { chromium } = require('playwright'); const fs = require('fs'); const { execFileSync } = require('child_process'); const path = require('path');
const root = process.argv[2];
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got) : '')); } };
(async () => { const b = await chromium.launch(); const html = execFileSync('php', [path.join(__dirname, 'r4-page.php'), root + '/app'], { stdio: ['ignore', 'pipe', 'inherit'] });
 const pulse = execFileSync('php', [path.join(__dirname, 'r4-page.php'), root + '/app', 'pulse'], { stdio: ['ignore', 'pipe', 'ignore'] });
 const rid = (/data-key="(report:\d+)"/.exec(pulse.toString()) || [])[1];
 for (const w of [1280, 390]) {
  const p = await b.newPage({ viewport: { width: w, height: 900 } }); const posted = [];
  await p.route('**/*', r => { const u = new URL(r.request().url());
   if (u.pathname.startsWith('/js/')) { try { return r.fulfill({ body: fs.readFileSync(root + '/website' + u.pathname), contentType: 'text/javascript' }); } catch (e) { return r.fulfill({ body: '', contentType: 'text/javascript' }); } }
   if (u.pathname === '/api/upload-act.php') { posted.push(r.request().postData() || ''); return r.fulfill({ body: '{"ok":true,"message":"Done."}', contentType: 'application/json' }); }
   if (u.pathname.startsWith('/admin/api/')) { posted.push(r.request().postData() || ''); return r.fulfill({ body: '{"ok":true,"message":"Done.","case_no":0,"ticked":[]}', contentType: 'application/json' }); }
   if (u.pathname === '/admin/dashboard/stream') return r.fulfill({ body: pulse, contentType: 'text/html' });
   if (u.pathname === '/t') return r.fulfill({ body: html, contentType: 'text/html' });
   return r.fulfill({ body: '<p>page</p>', contentType: 'text/html' }); });
  await p.goto('http://t.local/t'); await p.waitForTimeout(800);
  const m1 = p.locator('#m1');
  t(w + ': the place photo\'s thread: its title, reported, and its buttons', (await m1.innerText()).includes('New photo') && await m1.locator('button', { hasText: 'Take it down' }).count() === 1 && await m1.locator('button', { hasText: 'Leave it up' }).count() === 1 && await m1.locator('button', { hasText: /^Hide$/ }).count() === 1);
  await m1.locator('a', { hasText: 'Open it' }).click(); await p.waitForTimeout(700);
  t(w + ': Open it opens the journey in the side panel', await p.locator('.tv-pk').count() === 1 && /\/journeys\/view\.php\?id=40/.test((await p.locator('.tv-pk iframe').getAttribute('src')) || ''));
  await p.keyboard.press('Escape'); await p.waitForTimeout(300);
  t(w + ': …and closes with Esc', await p.locator('.tv-pk:visible').count() === 0);
  await m1.locator('button', { hasText: /^Hide$/ }).click(); await p.waitForTimeout(400);
  t(w + ': Hide with no reason isn\'t sent', !posted.length);
  await m1.locator('input[name="body"]').fill('Not taken here'); await m1.locator('button', { hasText: /^Hide$/ }).click(); await p.waitForTimeout(600);
  t(w + ': Hide sends its reason to the thread\'s endpoint', posted.some(x => x.includes('"act":"hide"') && x.includes('Not taken here')), posted);
  await m1.locator('button', { hasText: 'Take it down' }).click(); await p.waitForTimeout(400);
  const yes = p.locator('button', { hasText: /^Take it down$/ }).last();
  t(w + ': Take it down asks first', !posted.some(x => x.includes('report_remove')));
  await yes.click(); await p.waitForTimeout(600);
  t(w + ': …then sends it', posted.some(x => x.includes('report_remove')), posted);
  await m1.locator('button', { hasText: 'Leave it up' }).click(); await p.waitForTimeout(400);
  t(w + ': Leave it up asks first', !posted.some(x => x.includes('report_dismiss')));
  await p.locator('button', { hasText: /^Leave it up$/ }).last().click(); await p.waitForTimeout(600);
  t(w + ': …then sends it', posted.some(x => x.includes('report_dismiss')), posted);
  const m2 = p.locator('#m2');
  t(w + ': the order\'s thread: Open the order, and Ask for a new file and Check the files greyed with their reason in words', await m2.locator('a', { hasText: 'Open the order' }).count() === 1 && await m2.locator('button[disabled]', { hasText: 'Ask for a new file' }).count() === 1 && await m2.locator('button[disabled]', { hasText: 'Check the files' }).count() === 1 && (await m2.innerText()).includes('Jason sets the file check'));
  await m2.locator('a', { hasText: 'Open the order' }).click(); await p.waitForTimeout(700);
  t(w + ': Open the order opens its Files in the side panel', /\/admin\/backoffice\/orders\/9\?.*#docs$/.test((await p.locator('.tv-pk iframe').getAttribute('src')) || ''));
  await p.locator('.tv-pk [aria-label="Close"], .tv-pk .tv-pk-x').first().click(); await p.waitForTimeout(300);
  t(w + ': …and closes with its ×', await p.locator('.tv-pk:visible').count() === 0);
  t(w + ': no sideways scroll', await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth));
  // the report's own Pulse case: one row, its clock kept, the thread's steps inside
  await p.goto('http://t.local/admin/dashboard/stream#case=' + encodeURIComponent(rid || '')); await p.waitForTimeout(1500);
  const steps = await p.locator('#pb-dr-play li').allInnerTexts();
  t(w + ': the report\'s case opens with the thread\'s steps: see the photos, Take it down, Leave it up, Hide', !!rid && steps.some(x => x.includes('See the photos')) && steps.some(x => x.includes('Take it down')) && steps.some(x => x.includes('Or leave it up')) && steps.some(x => x.includes('hide it with your own reason')), [rid, steps]);
  t(w + ': …its row keeps its clock (Due …)', (await p.locator('[data-key="' + rid + '"]').first().innerText()).match(/Due|SLA|h left|over/i) !== null || (await p.locator('#pb-dr-chips').innerText()).includes('SLA'), await p.locator('#pb-dr-chips').innerText());
  const n0 = posted.length; const tb = p.locator('#pb-dr-play li', { hasText: 'Take it down' }).locator('button[data-do-what]').first();
  await tb.click(); await p.waitForTimeout(800);
  const yes2 = p.locator('button', { hasText: /^(Take it down|Yes)$/ }).last(); if (posted.length === n0 && await yes2.count()) { await yes2.click(); await p.waitForTimeout(800); }
  t(w + ': …Take it down there sends its step through Pulse', posted.slice(n0).some(x => x.includes('upload_report_remove')), posted.slice(n0));
  t(w + ': Pulse: no sideways scroll', await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth));
  await p.close(); }
 console.log('\n' + pass + ' passed, ' + fail + ' failed'); await b.close(); process.exit(fail ? 1 : 0); })();
