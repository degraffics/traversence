# task-seed-6's items (the release line, as Build & QA (21), for the PM (8)): one ticket per agent, each its own release, in the
# CEO's wave order (Jason, 2026-11-28: "Bring each one online individually… just in case we run out of usage"). It replaces
# task-seed-5's four team group tickets. Role numbers and names: decisions/0180's appendix. Run: python3 items.py > items.json
import json
R = 'research/ai-results/job18-team/'
ROLES = {3: 'Chief Operating Officer', 4: 'Chief Financial Officer', 5: 'Chief Technology Officer', 6: 'Chief Information Officer', 7: 'Chief Marketing Officer',
 8: 'Project Manager', 9: 'Customer Service Manager', 10: 'Accounting Manager', 11: 'IT Manager', 12: 'Editorial Director', 13: 'Marketing Director', 14: 'Sales Director',
 15: 'Cognitive Load & Simplicity Guardian', 16: 'Operations & Policy Director', 17: 'Order & Fulfillment Lead', 18: 'Customer Support Specialists', 19: 'Finance, Tax & Billing Ops',
 20: 'System Architect', 21: 'Build & QA Sessions', 22: 'Staging & Release Workers', 23: 'Operations & Reconciliation Manager', 24: 'Schema & Migration Guards',
 25: 'Privacy & Decision 0066 Guards', 26: 'Front-End Content Writers', 27: 'Public UI/UX Designers', 28: 'Executive Translator & Briefing Lead', 29: 'Brand & Campaign Strategists',
 30: 'SEO, Taxonomy & Funnel Analysts', 31: 'Merchant Onboarding Specialists', 32: 'Partnerships & Retention Leads'}
ACTIVE = {8, 20, 21, 23, 26, 28}   # active today: upgraded in place
ADV = ['A%d' % i for i in range(1, 13)]
WAVES = [[8, 22, 16, 11, 3], [21, 24, 25, 20, 23, 28], [12, 26] + ADV + [15, 27, 9, 18, 17], [5, 6, 7, 4, 10, 19, 13, 14, 29, 30, 31, 32]]
def draft(n):
    if isinstance(n, str): return R + 'team-advocates.md'
    if n <= 7: return R + 'team-executive-suite.md'
    if n <= 14: return R + 'team-middle-managers.md'
    return R + ('team-specialists-auditors-guards.md' if n in (15, 22, 24, 25) else 'team-specialists-operators.md')
new, pos = [], 0
for w, wave in enumerate(WAVES, 1):
    for n in wave:
        pos += 1
        if isinstance(n, str):
            what = 'Agent online %02d (wave %d): test-account advocate %s of 12, an independent audit that raises tickets' % (pos, w, n)
            why = 'Being built by the batch (jobs 18, 18b, 18c, 21). Jason, 2026-11-28: "Bring each one online individually". Its own release. The advocates audit the live site on their own schedule and never block a release (0180\'s note, 2026-11-28). Live only once its Run a test ticket passes on /admin/config/team'
        else:
            what = 'Agent online %02d (wave %d): %s (%d)%s' % (pos, w, ROLES[n], n, ', upgraded in place' if n in ACTIVE else '')
            why = 'Being built by the batch (jobs 18, 18b, 18c, 21). Jason, 2026-11-28: "Bring each one online individually… just in case we run out of usage". Its own release, on staging\'s runner per 0179, stage ceiling and fences unchanged' + ('. Active today: upgraded in place' if n in ACTIVE else '') + '. Live only once its Run a test ticket passes on /admin/config/team'
        new.append(dict(goal='transition', gauge='area:transition', parent='The transition cutover: our business and conversations on the platform by Dec 15', what=what, why=why,
                        source=draft(n) + ' · decisions/0180 appendix · handoffs/piece2-team.md', stage='planning', lane='project-manager', rel='', priority=min(w, 3), ticket='work'))
assert pos == 42 and len({x['what'] for x in new}) == 42
for x in new: assert len(x['what']) <= 255 and len(x['why']) <= 400 and len(x['source']) <= 255 and ';' not in x['what'] + x['why'] + x['source'], x['what']
five = json.load(open('../task-seed-5/items.json'))['new']
withdrawn = [dict(gauge=x['gauge'], item=x['what']) for x in five if x['what'].startswith('The team release:')]; assert len(withdrawn) == 4
print(json.dumps(dict(new=new, moved=[], withdrawn=withdrawn), indent=1, ensure_ascii=False))
