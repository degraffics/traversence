// The gauge panel's requests, hourly (the session manager's OK, 2026-11-23): reads only health.php?part=gauge_tasks and writes
// site-audit/results/build-requests.json (each open Request a fix, a day old marked late) and gauge-approvals.json, only when
// they changed, so main gets a commit only when a request or an approval does. The session manager's hourly check routes each
// request to the session that builds it, and alerts on the late ones; that session records its pickup in
// releases/gauge-pickups.json, which gauges.py puts in gauges.json ("Requested: the … session has it").
// Items, words and times only: no person's name (0145 §1). Needs HEALTH_TOKEN, else CHECK_ACCOUNTS' health_token.
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
const SITE = process.env.SITE || 'https://traversence.com';
let TOKEN = process.env.HEALTH_TOKEN || '';
if (!TOKEN && process.env.CHECK_ACCOUNTS) { try { TOKEN = JSON.parse(process.env.CHECK_ACCOUNTS).health_token || ''; } catch (e) {} }
const DAY = 86400e3;
const strip = d => JSON.stringify({ ...d, at: undefined });   // compared without its own time
const keep = (file, body) => {   // written only when what it holds changed
  const old = existsSync(file) ? (() => { try { return JSON.parse(readFileSync(file, 'utf8')); } catch (e) { return {}; } })() : {};
  if (strip(old) === strip(body)) return false;
  writeFileSync(file, JSON.stringify(body, null, 1) + '\n'); return true;
};
export function shape(gt, now = Date.now()) {   // the report's section as the two files hold it (exported for its test)
  const requests = (gt.requests || []).map(r => ({ ...r, late: now - Date.parse(r.at_utc) > DAY }));
  return [{ at: new Date(now).toISOString(), requests }, { at: new Date(now).toISOString(), approvals: gt.approvals || [] }];
}
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {   // run, not imported by its test
  if (!TOKEN) { console.log('No health token: neither HEALTH_TOKEN nor CHECK_ACCOUNTS (with its health_token) is set.'); process.exit(0); }
  const r = await fetch(`${SITE}/api/health.php?part=gauge_tasks`, { headers: { Authorization: `Bearer ${TOKEN}`, 'User-Agent': 'traversence-gauge-requests' } });
  const d = await r.json().catch(() => ({}));
  if (!r.ok || !d.gauge_tasks || d.gauge_tasks.error) { console.log('::warning::the health report gave no gauge_tasks:', r.status, (d.gauge_tasks || {}).error || ''); process.exit(0); }
  const [req, ap] = shape(d.gauge_tasks);
  const a = keep('site-audit/results/build-requests.json', req), b = keep('site-audit/results/gauge-approvals.json', ap);
  console.log(`${req.requests.length} open request(s), ${req.requests.filter(x => x.late).length} late; ${ap.approvals.length} approval(s);`, a || b ? 'changed' : 'no change');
}
