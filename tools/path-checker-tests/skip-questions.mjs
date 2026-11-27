// Sign-up's questions (Gate 2, 2026-11-27): paths-staging.mjs's skipQuestions skips all four questions after the email's
// link and reaches "You're all set" with its link. The old pick (the first Skip in the page, s1's, hidden once answered)
// stopped after one and reported a false dead end. Against fixtures/verify-email.html, the live verify-email.php as
// rendered on the bench from the mirror at Sync 19:50 UTC; its two API calls are stubbed (a link that checks out).
// Run: node tools/path-checker-tests/skip-questions.mjs   (no server needed)
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { skipQuestions, SKIP_Q } from '../page-audit/paths-staging.mjs';
const { chromium } = createRequire(import.meta.url)('playwright');
const here = dirname(fileURLToPath(import.meta.url));
const HTML = readFileSync(join(here, 'fixtures', 'verify-email.html'), 'utf8');
const SITE = 'http://fixture.test';
const b = await chromium.launch(); let bad = 0;
const t = (n, c, x) => { if (!c) bad++; console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  [' + x + ']' : '')); };
async function open(w, signedIn) {
  const c = await b.newContext({ viewport: { width: w, height: 900 } });
  await c.route('**/*', (r) => {
    const u = r.request().url();
    if (u.startsWith(SITE + '/verify-email.php')) return r.fulfill({ contentType: 'text/html', body: HTML });
    if (u.includes('/api/auth/session.php')) return r.fulfill({ contentType: 'application/json', body: '{"authenticated":false,"csrf_token":"x"}' });
    if (u.includes('/api/auth/verify-email.php')) return r.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, email: 'a@b.c', signed_in: signedIn, csrf_token: 'y' }) });
    return r.fulfill({ status: 404, body: '' });
  });
  const p = await c.newPage(); await p.goto(SITE + '/verify-email.php?token=abc');
  await p.waitForSelector(SKIP_Q, { timeout: 10000 }).catch(() => {});
  return [c, p];
}
for (const w of [1280, 390]) for (const signedIn of [true, false]) {
  const [c, p] = await open(w, signedIn); const tag = `${w} ${signedIn ? 'signed in by the link' : 'not signed in'}:`;
  const asked = await skipQuestions(p, (s) => s.click());
  t(`${tag} all four questions skipped`, asked === 4, String(asked));
  const go = p.locator('a#s5-go');
  t(`${tag} "You're all set" and its link show`, await go.isVisible().catch(() => false));
  const href = (await go.getAttribute('href').catch(() => '')) || '';
  t(`${tag} the link goes on`, signedIn ? href === '/user/dashboard.php' : /^\/login\.php/.test(href), href);
  await c.close();
}
// the old pick, kept here as the regression: the first Skip in the page stops after one
{
  const [c, p] = await open(390, true); let n = 0;
  for (let i = 0; i < 6; i++) { const s = p.locator('button.vs-s[data-skip]').first(); if (!(await s.isVisible().catch(() => false))) break; n++; await s.click(); await p.waitForTimeout(300); }
  t('the old pick (first Skip in the page) stops after one, as Gate 2 saw', n === 1 && !(await p.locator('a#s5-go').isVisible()), String(n));
  await c.close();
}
await b.close();
console.log(bad ? `${bad} failed` : 'all passed'); process.exit(bad ? 1 : 0);
