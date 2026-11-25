SRC_DOC="the holiday wrap-up (Jason, 2026-11-24: \"Push anything outstanding into the task que\")"
# new tasks: (area, what, why, source(docs), goal, in, out, done, prio, adrs)
N=[
('control-board',"The migration runner: a data migration is never cleared as already there unrun","#284's seed was logged \"already there\" and deleted unrun because the table it created existed: a silent false success (CLAUDE.md: no action claims success without a real check)","handoffs/task-seed.md (#284 and task-seed-2); app/lib/DbUpdates.php autoRun and all()","A file counts as run only by its own db_updates_log entry, never because its tables exist","DbUpdates::autoRun and all()'s state, and the zip check refusing such a file","","#284's case runs on the bench, and 0162 has its note",1,['0162']),
('backlog',"Gemini on the paid tier","Jason linked billing ($20 on Cloud Prepay, Google's $300 credit excludes Gemini) and paid-tier data isn't used for training","handoffs/task-seed.md; handoffs/work-queue-phase2.md gap (a); decisions/0164 §0.8","Gemini in the Service register as Paid tier, its cap the prepaid balance, shown on the Ledger","Which key and tier each caller uses","Changing 0164 §0.8 (internal text to the paid key is Jason's call)","The register, cap and Ledger say paid tier and the balance",2,['0164','0163']),
('control-board',"The Work queue redesigned around Jason's four stages","Jason, 2026-11-24: planning, building, implementation, confirmation, with \"tools that I understand\"","Waiting for Jason's outline. Then a mockup first, in his words only","The queue by his four stages","","Anything before his outline","He approves the mockup",1,[]),
('control-board',"Move Lead scores and Work order reminders to :02 (the :17 crowd test)","Lead scores and Work order reminders miss most hourly starts at :17 (jobs.json, 2026-11-24)","handoffs/board-phone.md \"Still open\"; tools/page-audit/towns.mjs jobs.json","Both jobs start every hour","The two jobs' minute","Spreading every preset (the :17 crowd task)","A day of jobs.json shows both every hour",2,[]),
('control-board',"Attach a document to a task","Jason: a task can link governance docs and ADRs, the md files as references","handoffs/task-adrs.md (Attach a decision, the same pattern)","A task lists the documents it rests on, each opening beside the board","The repo's md files: hand-offs, research, design","Uploading new files","A task shows its linked docs and History keeps each change",2,[]),
('orders',"M3's first fix: the order tests green again","m1, collect-balance, complete-owed and m1b's vendor-bill steps fail the same way on the live files","handoffs/order-management-m2.md (\"M3's first fix\", Final hand-off)","Every suite in tools/order-tests passes on the live files","The harness schema brought to the live tables, each seed clear on what it counts","","Every order suite green",1,[]),
('orders',"The order page at 390: Files and versions, Shipping","Jason's Order #12 screenshots, the Design reviewer's items 7 to 10","handoffs/order-management-m2.md (Open items); handoffs/design-reviewer-2026-11-24.md","The order page reads on a phone","Files and versions' add form stacked through the one uploader, explanations behind ⓘ, the per-item table as phone cards, Print the master manifest as a heading icon","","Order details, Files and Shipping pass at 390",2,['0127']),
('orders',"Every order release's proof includes Order details, Files and Shipping at 390","The Design reviewer, 2026-11-24","handoffs/design-reviewer-2026-11-24.md item 7","Each order release's screens show those three at 390","","","The next order release's proof has them",3,[]),
('orders',"Later: book a courier or freight with an outside company","Out of M2b's scope, for later","decisions/0101 (2026-11-24 note)","","","","",3,['0101']),
]
# existing tasks: their docs added to source (scope kept)
U=[
("area:control-board","No Undo on any alarm's acknowledgement: \"Seen by …, <time>\" as its state line","built, not sent: branch claude/no-undo, handoffs/no-undo.md"),
("area:control-board","Work queue phase 2","designed, not built: claude/wq-phase2, handoffs/work-queue-phase2.md (+ mockups), after the four-stage redesign"),
("area:control-board","A load batch the server answers 503 is retried after a pause, never failing the whole task","W1 run #2148: handoffs/control-board-2026-11-24.md, handoffs/loads-reply.md"),
("area:control-board","The ledger's receipt counts: \"/api/crawl/loads: received 100, accounted for 80\" and W2's places, refresh and identity warnings","handoffs/control-board-2026-11-24.md (open notes)"),
("area:control-board","Plain names in alarm titles for /api/crawl/… (alarmTitle)","handoffs/design-reviewer-2026-11-24.md"),
("area:orders","M2b: how an order goes: two legs on 0101's fields, a shipping work order with no ship-to comes to us","PR #1346 paused: handoffs/order-management-m2.md, order-m2b/, 0101 notes; on resume: email error buttons in box, Ship mode Not set ✎"),
("area:orders","M3: the Money section: Outstanding with aging, Take a payment across orders, Credits on account","research/order-management-design-2026-11-23.md §6, 0098 note, 0178"),
("area:orders","M4: Place an order, guided, the archived E-Commerce orders page retired","research/order-management-design-2026-11-23.md §6"),
("area:orders","M5: several destinations","0177 note, research/order-management-design-2026-11-23.md §6"),
("area:orders","M6: the catalog: Services, Categories","research/order-management-design-2026-11-23.md §6"),
("area:orders","M7: credit terms, Net 15 / Net 30, granted by Jason","0178 §6, research/order-management-design-2026-11-23.md §5"),
("area:orders","Order #12's books","handoffs/order-management-m2.md: QB invoice 112906; with Jason"),
]
PFX="Jason's scope doc (Work queue: task scopes), 2026-11-24"
for n in N: assert len(n[3])<=255, n[1]
def q(v):
    if v is None or v=='': return "NULL"
    return "'"+str(v).replace(';',' ·').replace("'","''")+"'"
out=["-- app/migrations/2026-11-25_task_seed_3.sql: everything outstanding into the Work queue before the holiday (Jason, 2026-11-24: \"Push",
"-- anything outstanding into the task que so I can review it when I return\"), each task linking its own docs in its source. Data only,",
"-- no CREATE (the runner clears a file whose tables all exist unrun, #284): new tasks once, the docs added to existing tasks' source."]
for (a,what,why,src,goal,ins,outs,done,pr,adrs) in N:
    g='area:'+a
    out.append(f"INSERT INTO control_gauge_tasks (at, tz, gauge, item, kind, what, bucket, value, who, uid, why, source, goal, in_scope, out_scope, done_when, priority) SELECT UTC_TIMESTAMP(), '', {q(g)}, {q(what)}, 'fix', {q(what)}, 'task', '', 'the queue''s seed', 0, {q(why)}, {q(src)}, {q(goal)}, {q(ins)}, {q(outs)}, {q(done)}, {pr} FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM control_gauge_tasks WHERE kind = 'fix' AND gauge = {q(g)} AND item = {q(what)});")
    for d in adrs:
        out.append(f"INSERT INTO control_gauge_task_adrs (task_id, adr, at, who) SELECT t.id, '{d}', UTC_TIMESTAMP(), 'the queue''s seed' FROM control_gauge_tasks t WHERE t.kind = 'fix' AND t.gauge = {q(g)} AND t.item = {q(what)} AND NOT EXISTS (SELECT 1 FROM control_gauge_task_adrs x WHERE x.task_id = t.id AND x.adr = '{d}' AND x.removed_at IS NULL);")
for (g,item,docs) in U:
    assert len(docs) < 140, (item, len(docs))
    out.append(f"UPDATE control_gauge_tasks SET source = LEFT(CONCAT(REPLACE(source, {q(PFX)}, 'scope doc 2026-11-24'), {q(' · docs: ' + docs)}), 255) WHERE kind = 'fix' AND bucket = 'task' AND gauge = {q(g)} AND item = {q(item)} AND source NOT LIKE '%· docs: %';")
s="\n".join(out)+"\n"
assert s.count(';')==len(out)-3
open('app/migrations/2026-11-25_task_seed_3.sql','w').write(s)
print(len(N),'new',len(U),'updated')
