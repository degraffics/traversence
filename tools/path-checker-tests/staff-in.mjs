// C.2 (the launch plan; decisions/0145): check-kit.mjs's staffIn never reuses a two-step step, and tries once more on
// the next step when another check took this one. Against the stand-in sign-in page in fake/ (it refuses a step at or
// before the last used, as StaffLogin::totpMatch does). Run:
//   php -S 127.0.0.1:8795 -t tools/path-checker-tests/fake &   then   node tools/path-checker-tests/staff-in.mjs
// Expect: 3 sign-ins in a row pass, each on a new step; then, with the step taken behind its back, one passes on retry.
import { createRequire } from 'node:module';
import { writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { staffIn } from '../page-audit/check-kit.mjs';
const { chromium } = createRequire(import.meta.url)('playwright');
const here = dirname(fileURLToPath(import.meta.url)); const SITE = 'http://127.0.0.1:' + (process.env.PORT || 8795);
const a = { staff_id: 'TRV-TEST', password: 'x', totp: 'JBSWY3DPEHPK3PXP' };
rmSync(join(here, 'fake', 'last.txt'), { force: true }); rmSync(join(tmpdir(), 'tv-staff-steps.json'), { force: true });
const b = await chromium.launch(); let bad = 0;
const one = async (label) => { const ctx = await b.newContext(); try { await staffIn(ctx, a, SITE); console.log('PASS', label); } catch (e) { bad++; console.log('FAIL', label, String(e).slice(0, 120)); } await ctx.close(); };
for (let i = 1; i <= 3; i++) await one(`sign-in ${i} in a row`);
writeFileSync(join(here, 'fake', 'last.txt'), String(Math.floor(Date.now() / 30000)));   // another check used this step
rmSync(join(tmpdir(), 'tv-staff-steps.json'), { force: true });
await one('sign-in after another check took the step (retried on the next)');
await b.close(); rmSync(join(here, 'fake', 'last.txt'), { force: true });
console.log(bad ? `${bad} failed` : 'all passed'); process.exit(bad ? 1 : 0);
