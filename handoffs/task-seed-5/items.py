# task-seed-5's items (the release line, as Build & QA (21), for the PM (8)): every current item as a Work-queue ticket under its
# goal, in its lane, at its real phase, linked to its draft or release (Jason, 2026-11-28, through the session manager: "Work
# queue is our only development lane so never bypass it!"). Run: python3 items.py > items.json, then python3 seed5.py
import json
IT, ED, PM, MK, AC = 'it-manager', 'editorial-director', 'project-manager', 'marketing-director', 'accounting-manager'
R = 'research/ai-results/'
def t(goal, what, why, source, stage='planning', lane=IT, rel='', prio=2, ticket='work', by=''):
    if by: why = 'Being built by ' + by + '. ' + why
    return dict(goal=goal, what=what, why=why, source=source, stage=stage, lane=lane, rel=rel, priority=prio, ticket=ticket)
new = [
 # the line's releases
 t('gate-data', 'The pause: nothing the system finds publishes by itself until the data-capture review\'s fixes are live (pause-1a-4)', 'Jason, 2026-11-28: "Pause it. Yes". Live harm: bad health data. Sent four times (#328, #329, #330 withdrawn), Tier 4 by the IT Manager (11)', 'handoffs/pause-1a/README.md · release pause-1a-4.zip · PR #1669', 'implementation', ED, 'pause-1a-4.zip', 1, by='the release line session, from Data & Content'),
 t('records', 'task-seed-4: the goals as parent tasks, each with owner, measure, due date and decision', 'CLAUDE.md "Goals and records live with the roles" (Jason, 2026-11-27). Data only, Tier 2', 'handoffs/task-seed-4/README.md · release task-seed-4.zip · PR #1671', 'implementation', PM, 'task-seed-4.zip', 1, by='the release line session, from Data & Content'),
 t('records', 'task-seed-5: every current item a ticket under its goal, in its lane, at its real phase', 'Jason, 2026-11-28: "Work queue is our only development lane so never bypass it!" From here the queue is the record', 'handoffs/task-seed-5/README.md · release task-seed-5.zip', 'implementation', PM, 'task-seed-5.zip', 1, by='the release line session'),
 t('records', 'No work without a ticket: send.py refuses a release.json without "ticket", batch.py a job without one', 'Jason, 2026-11-28: never bypass the Work queue. In the repo with task-seed-5, with tests for both refusals', 'tools/page-audit/ticket.py · tools/page-audit/test_send_ticket.py · tools/batch/test_batch_ticket.py', 'confirmation', IT, '', 1, by='the release line session'),
 t('records', 'A ticket\'s phase moves by itself: send.py on send, the release record on Live, and Live with its check passed is Confirmation', 'The session manager, 2026-11-28 (the CEO, quoting Jason): the queue is the record. Work by a session or the batch shows on its ticket as being built by it. Not built: today the phase is moved by hand', 'handoffs/task-seed-5/README.md (gaps) · app/lib/Releases.php · app/lib/GaugeTasks.php', 'planning', IT, '', 1),
 # the team releases (job 18), being drafted by the batch
 t('transition', 'The team release: the executives', 'Jason, 2026-11-28: as much as possible live today. Runs on staging\'s runner per 0179, stage ceiling and fences unchanged', R + 'job18-team/team-executive-suite.md · handoffs/piece2-team.md', lane=PM, by='the batch (jobs 18, 18b, 18c, 21)'),
 t('transition', 'The team release: the middle managers', 'One release per role group, on staging\'s runner per 0179, stage ceiling and fences unchanged', R + 'job18-team/team-middle-managers.md · handoffs/piece2-team.md', lane=PM, by='the batch (jobs 18, 18b, 18c, 21)'),
 t('transition', 'The team release: the operators and keepers (specialists, auditors, guards)', 'One release per role group, on staging\'s runner per 0179, stage ceiling and fences unchanged', R + 'job18-team/team-specialists-operators.md · ' + R + 'job18-team/team-specialists-auditors-guards.md', lane=PM, by='the batch (jobs 18, 18b, 18c, 21)'),
 t('transition', 'The team release: the 12 advocates', 'One release per role group. The advocates are an independent audit, not a release gate (0180\'s note, 2026-11-28)', R + 'job18-team/team-advocates.md · decisions/0180 note 2026-11-28', lane=PM, by='the batch (jobs 18, 18b, 18c, 21)'),
 t('transition', 'Ready to be vetted: /admin/config/team shows each role\'s charter, lane, tier, AI access and queue, with Run a test ticket', 'Jason, 2026-11-28. A test ticket runs on staging and shows its result and cost. Roles with API access run code-first until the agents\' budget key exists, and their card says so with the step for Jason', R + 'job18-team/team-runner-and-queue-patches.md · ' + R + 'job18c-team-pass3-ai-access/', lane=PM, by='the batch (jobs 18, 18b, 18c, 21)'),
 t('transition', 'The advocates\' own scheduled audit of the live site: each walks its account type\'s paths at 1280 and 390, each finding a ticket in the owning lane', 'Jason, 2026-11-28: "The account advocates should also act like the audit, and independently operations vs attached to key deployment work". They never block a release', 'decisions/0180 note 2026-11-28 (the advocates are an independent audit)', lane=PM),
 # search and the care claim kinds
 t('gate-paths', 'Search release 1 (1a): urgent care read as one phrase, 0061\'s need and kind order, the health guard, the index catch-up out of the visitor\'s search', 'Built, not sent. Needs Scheduler\'s search_index_tick lines after goal_check is live, faq.md, the Design reviewer, the path re-run and a test', 'branch claude/search-1a @049ea23f · ' + R + 'job17-patch-drafts/patch-search-release-1.md · handoffs/it-lane-2026-11-28.md', 'building', IT, '', 1, by='the IT lane\'s session and the batch (job 17)'),
 t('gate-paths', 'Search 1b: REGISTRY_KIND and registryCategory() in IntakeStager, AutoImport and Identity rebuilt on the pause', 'Not built. The file list is in the session manager\'s thread (items 7 to 13)', 'handoffs/it-lane-2026-11-28.md (Search 1b)'),
 t('gate-paths', 'Search 1c: the reclassify pass for registry-mapped kinds, with preview and Undo', 'Not built. Data & Content owns the person rule', 'handoffs/it-lane-2026-11-28.md (Search 1c)'),
 t('gate-paths', 'Search release 2: places and honest hours', 'Built and held, with its patch, tests and the checker\'s line', 'branch claude/search-r2 @25cd20bc', 'building', IT, by='the IT lane\'s session'),
 t('gate-data', 'The care claim kinds (care-claim-kind)', 'The listing audit: about 240 people shown as places, hospitals filed as clinics. A draft only, to verify, test and zip-check', R + 'job17-patch-drafts/patch-care-claim-kind.md · research/listing-audit-2026-11-27.md', lane=ED, prio=1, by='the batch (job 17)'),
 # the IT tickets
 t('gate-money', 'MarketSquare::import and sync onto BusinessData::scoped, Customers::$ready per business', 'Gate 5, not built (MarketSquare.php:120, :160)', 'handoffs/it-lane-2026-11-28.md (MarketSquare and Customers)'),
 t('gate-money', 'writeOrder\'s retry and backfill, behind market_copy_backfill (off as it ships), with a dry run for the CFO', 'Not built. Before about Dec 10, the theater\'s ticket sales. The CFO\'s seven points and Design\'s message 2', 'handoffs/writeorder-tenant.md · handoffs/it-lane-2026-11-28.md'),
 t('gate-money', 'Mark refunded: a business\'s Mark refunded voids the order\'s tickets, Cancel the record restores them', 'Gate 5, not built. History kept, tests, the CFO\'s Tier 4', 'handoffs/it-lane-2026-11-28.md (Mark refunded)'),
 t('gate-data', 'adhs-a1: its Tier 4 review and one independent check', 'Built on its branch. A Tier 4 review is owed before it is sent', 'branch claude/adhs-a1 @dfbe57eb · handoffs/it-lane-2026-11-28.md (Tier 4 reviews owed)', 'building', ED, by='Data & Content\'s session'),
 t('gate-safety', 'f9-cleanup: the control_tick hook, then step two once run() and Undo are tested on MariaDB', 'Built on its branch. A Tier 4 review is owed', 'branch claude/f9-cleanup @d4b646f2 · handoffs/it-lane-2026-11-28.md (Tier 4 reviews owed)', 'building', IT, by='the IT lane\'s session'),
 t('gate-safety', 'The before-and-after check\'s verdict tickets listed in the daily report', 'Logged for the IT lane: the verdict tickets are read by nobody yet', 'handoffs/it-lane-2026-11-28.md (Logged for the IT lane)'),
 t('gate-paths', '/admin/crawler-review.php overflows at 390', 'Logged for the IT lane', 'handoffs/it-lane-2026-11-28.md (Logged for the IT lane)', prio=3),
 # the audit findings
 t('gate-paths', '/admin/people.php at 390: the side menu\'s controls drawn over the page\'s own', 'Found by the before-and-after check (#329, #330): 1 before, 3 after, on a page the release\'s changed code doesn\'t run. Fix the overlap at its cause', 'site-audit/results/beforeafter/audit.json · handoffs/it-lane-2026-11-28.md', 'found', IT, ticket='audit'),
 t('gate-paths', '/admin/crawler-review.php didn\'t open in 45 s on staging for the executive', 'Found by the before-and-after check (#330): while paused every staged find stays and Review evaluated all of them. pause-1a-4 bounds it (200, Show more)', 'site-audit/results/beforeafter/audit.json · release pause-1a-4.zip', 'confirmed', IT, ticket='audit'),
 # Frontend & Shop's builds, waiting to be sent
 t('gate-paths', 'C.4: the town\'s point', 'Built, waiting to be sent, in Frontend & Shop\'s order', 'handoffs/c4-town-point/ (town-point.zip) · handoffs/frontend-shop.md', 'building', IT, 'town-point.zip', by='Frontend & Shop\'s session'),
 t('gate-paths', 'place-list', 'Built, waiting to be sent, in Frontend & Shop\'s order', 'handoffs/place-list/ (place-list.zip) · handoffs/frontend-shop.md', 'building', IT, 'place-list.zip', by='Frontend & Shop\'s session'),
 t('gate-paths', 'C.8: open now', 'Built, waiting to be sent, in Frontend & Shop\'s order', 'handoffs/c8-open-now/ (open-now.zip) · handoffs/frontend-shop.md', 'building', IT, 'open-now.zip', by='Frontend & Shop\'s session'),
 t('gate-safety', 'posts-pdo: user/api/posts.php\'s database errors logged, never shown', 'Built (one file, 8 of 8), waiting to be sent', 'handoffs/posts-pdo/ · tools/posts-tests/pdo-error.php', 'building', IT, by='Frontend & Shop\'s session'),
 t('launch', 'Events E1: the store and the host\'s form (0134\'s minimum)', 'Built on Sync Nov 27 16:23, Design approved. Rebuild on the live copy before it is sent. Needed live by Dec 10 to 12 for the Hometown Theater\'s community night', 'handoffs/events-e1/README.md · tools/event-tests/events.php', 'building', MK, by='Frontend & Shop\'s session'),
 t('launch', 'Events E2: the event\'s page, the listing\'s and the guide\'s Coming up', 'Built on E1, Design approved. Goes right after E1', 'handoffs/events-e2/README.md · tools/event-tests/events-public.php', 'building', MK, by='Frontend & Shop\'s session'),
 t('launch', 'Events P1: invitations and offers', 'Built (22 of 22, 34 of 34), with the Design reviewer. The offer to the CMO\'s ruling #1594', 'handoffs/events-p1/ · tools/event-tests/events-parts.php', 'building', MK, by='Frontend & Shop\'s session'),
 t('launch', 'Events P2: Taking part on the event page, Send the evening to Maps', 'Built (43 of 43), with the Design reviewer', 'handoffs/events-p2/ · tools/event-tests/event-parts-page.cjs', 'building', MK, by='Frontend & Shop\'s session'),
 t('ladder', 'Hometown Theater: the touch points F1 to F9, with F9 an owner signs up (the claim, then Events in the portal, then the team invite)', 'Due Dec 3. F9\'s claim path is present but not walked live, and Events isn\'t live (F1, F6)', 'handoffs/hometown-theater/touch-points.md · handoffs/hometown-theater/README.md', lane=MK, prio=1),
 # decided, not live (job 19's drafts)
 t('launch', 'Client order emails through the one dispatcher (0172)', 'Decided, not live. A draft only, to verify, test and zip-check', R + 'job19-decided-not-live/dnl-client-order-emails.md · handoffs/decided-not-live.md', by='the batch (job 19)'),
 t('gate-safety', 'An alarm\'s acknowledgement: no Undo', 'Decided, not live. A draft only', R + 'job19-decided-not-live/dnl-no-undo.md · handoffs/decided-not-live.md', by='the batch (job 19)'),
 t('gate-money', 'Orders M3: money', 'Decided, not live (the order-management design §6). A draft only', R + 'job19-decided-not-live/dnl-orders-m3-money.md · research/order-management-design-2026-11-23.md §6', lane=AC, by='the batch (job 19)'),
 t('launch', 'Orders M4: place an order', 'Decided, not live. A draft only', R + 'job19-decided-not-live/dnl-orders-m4-place-order.md · research/order-management-design-2026-11-23.md §6', by='the batch (job 19)'),
 t('launch', 'Orders M5: destinations', 'Decided, not live. A draft only', R + 'job19-decided-not-live/dnl-orders-m5-destinations.md · research/order-management-design-2026-11-23.md §6', by='the batch (job 19)'),
 t('launch', 'Orders M6: the catalog', 'Decided, not live. A draft only', R + 'job19-decided-not-live/dnl-orders-m6-catalog.md · research/order-management-design-2026-11-23.md §6', by='the batch (job 19)'),
 t('gate-money', 'Orders M7: credit terms', 'Decided, not live. A draft only', R + 'job19-decided-not-live/dnl-orders-m7-credit-terms.md · research/order-management-design-2026-11-23.md §6', lane=AC, by='the batch (job 19)'),
 t('transition', 'Publish authority by tier (0179)', 'Decided, not live. A draft only', R + 'job19-decided-not-live/dnl-publish-authority-0179.md · decisions/0179', lane=PM, by='the batch (job 19)'),
 t('launch', 'A vendor\'s default category from QuickBooks (0178)', 'Decided, not live (Later). A draft only', R + 'job19-decided-not-live/dnl-vendor-default-category.md · decisions/0178', lane=AC, prio=3, by='the batch (job 19)'),
 # the Control Board items (job 20's drafts)
 t('gate-safety', 'Control Board: Diagnose', 'Queued in decided-not-live. A draft only', R + 'job20-control-board/cb-diagnose.md · handoffs/control-board-2026-11-24.md', by='the batch (job 20)'),
 t('gate-safety', 'Control Board: the Find box', 'Queued in decided-not-live. A draft only', R + 'job20-control-board/cb-find-box.md · handoffs/control-board-2026-11-24.md', prio=3, by='the batch (job 20)'),
 t('gate-safety', 'Control Board: the Ledger tab in Modules', 'Queued in decided-not-live. A draft only', R + 'job20-control-board/cb-ledger-tab.md · handoffs/control-board-2026-11-24.md', prio=3, by='the batch (job 20)'),
 t('gate-safety', 'Control Board: outside links open in the browser, with the service and the account', 'CLAUDE.md "Every action is a task". A draft only', R + 'job20-control-board/cb-outside-links.md · handoffs/control-board-2026-11-24.md', prio=3, by='the batch (job 20)'),
 t('gate-safety', 'Control Board: Request a fix, followed through', 'Queued in decided-not-live. A draft only', R + 'job20-control-board/cb-request-fix-follow-through.md · handoffs/control-board-2026-11-24.md', by='the batch (job 20)'),
 t('gate-safety', 'Control Board: the workflow-shape audit', 'Queued in decided-not-live. A draft only', R + 'job20-control-board/cb-workflow-shape-audit.md · handoffs/control-board-2026-11-24.md', prio=3, by='the batch (job 20)'),
]
# task-seed-4's own tickets moved to their real phase, in place (never a duplicate): [goal key, its title's start, stage, release, what the source gains]
moved = [
 ['records', 'The daily goal check:', 'building', 'goal-check.zip', 'built: handoffs/goal-check/ (25 of 25)'],
 ['gate-paths', 'Layout shift:', 'building', '', 'built: handoffs/c5a-list-hold/, handoffs/c5b-guide-live/'],
 ['launch', 'The Marketplace\'s four sections for Jan 1', 'building', 'm-sections.zip', 'built: handoffs/m-sections/'],
 ['gate-paths', 'The site\'s stylesheet built once', 'planning', '', 'draft: ' + R + 'job19-decided-not-live/dnl-stylesheet-tailwind.md'],
 ['launch', 'Piece 3: Undo per agent action', 'planning', '', 'draft: ' + R + 'job19-decided-not-live/dnl-piece3.md'],
 ['transition', 'Role queues as the agents\' inbox', 'planning', '', 'draft: ' + R + 'job19-decided-not-live/dnl-role-queues.md'],
 ['gate-paths', 'The promise check:', 'planning', '', 'findings: ' + R + 'job6-promise-check/'],
]
G = json.load(open('../task-seed-4/items.json'))['goals']
goal = {g['key']: g for g in G}
out_moved = []
for k, start, stage, rel, add in moved:
    m = [c for c in goal[k]['children'] if c['what'].startswith(start)]; assert len(m) == 1, start
    src = m[0]['source'] + ' · ' + add; assert len(src) <= 255, (start, len(src))
    out_moved.append(dict(gauge=m[0].get('gauge', 'area:' + k), item=m[0]['what'], stage=stage, rel=rel, seed_source=m[0]['source'], source=src))
titles = set()
for x in new:
    assert x['goal'] in goal, x['goal']; x['parent'] = goal[x['goal']]['what']; x['gauge'] = 'area:' + x['goal']
    assert len(x['what']) <= 255 and len(x['why']) <= 400 and len(x['source']) <= 255, (x['what'], len(x['why']), len(x['source']))
    assert x['what'] not in titles and not any(c['what'] == x['what'] for g in G for c in g.get('children', [])), x['what']; titles.add(x['what'])
    assert ';' not in x['what'] + x['why'] + x['source'], x['what']
print(json.dumps(dict(new=new, moved=out_moved), indent=1, ensure_ascii=False))
