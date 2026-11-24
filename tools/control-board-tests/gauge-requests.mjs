// gauge-requests.mjs's shape (the hourly pickup, 2026-11-23): each open request marked late past a day, approvals kept,
// nothing but items, words and times. No network: node tools/control-board-tests/gauge-requests.mjs
import { shape } from '../page-audit/gauge-requests.mjs';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got) : '')); } };
const now = Date.parse('2026-11-24T12:00:00Z');
const [req, ap] = shape({ requests: [{ id: 1, at_utc: '2026-11-23T11:00:00Z', gauge: 'development:discovery', build: 'Discovery', item: 'A', what: 'x', was: 'fail' },
  { id: 2, at_utc: '2026-11-24T10:00:00Z', gauge: 'development:discovery', build: 'Discovery', item: 'B', what: 'y', was: 'check' }], approvals: [{ gauge: 'g', kind: 'scope', value: '', at_utc: '2026-11-24T09:00:00Z' }] }, now);
t('a request over a day old is late', req.requests[0].late === true, req.requests[0]);
t('a request under a day isn\'t', req.requests[1].late === false, req.requests[1]);
t('approvals kept as given', ap.approvals.length === 1 && ap.approvals[0].kind === 'scope');
t('no name in either file', !/who|Jason/.test(JSON.stringify([req, ap])));
const [rq] = shape({ requests: [{ id: 3, at_utc: '2026-11-22T11:00:00Z', what: 'a task', owner: 'Orders session', release: '' }, { id: 4, at_utc: '2026-11-22T11:00:00Z', what: 'b task', owner: '', release: 'x.zip' }, { id: 5, at_utc: '2026-11-22T11:00:00Z', what: 'c task', owner: '', release: '', why: 'w', source: 's' }] }, now);
t('Work queue: a task someone has, or with its release named, is never late', rq.requests[0].late === false && rq.requests[1].late === false);
t('Work queue: a day old with nobody on it is late, its why and source carried', rq.requests[2].late === true && rq.requests[2].why === 'w' && rq.requests[2].source === 's');
const [r0] = shape({}, now); t('no section: empty lists, never a throw', Array.isArray(r0.requests) && r0.requests.length === 0);
console.log(`\n${pass} passed, ${fail} failed`); process.exit(fail ? 1 : 0);
