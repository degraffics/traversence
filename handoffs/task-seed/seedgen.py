E={
'No Undo on alarm acknowledgements':"No Undo on any alarm's acknowledgement: \"Seen by …, <time>\" as its state line",
'Diagnose':"Diagnose: a step-by-step process in Diagnostics, from one item's dependencies, from the outside in",
'Workflow-shape audit':"The workflow-shape audit: a report to Jason first",
'Ledger tab':"The Ledger tab in Modules: one row per paid account, its cap, spend, bills and source",
'Find box':"The Find box in System Configuration (\"/\" opens it), and each card's top line",
'Request a fix follow-through':"Request a fix's follow-through: its check runs when the fix goes live, one notice",
'Outside links in the browser':"Outside links open in the browser: ↗ with the service and account, the first-tap hint on phones",
'Crawler 503 retry':"A load batch the server answers 503 is retried after a pause, never failing the whole task",
'Receipt counts':"The ledger's receipt counts: \"/api/crawl/loads: received 100, accounted for 80\" and W2's places, refresh and identity warnings",
'Plain alarm titles':"Plain names in alarm titles for /api/crawl/… (alarmTitle)",
'The :17 crowd':"The :17 crowd: spread the hourly jobs over more minutes, and Scheduler::sync moves existing lines",
'M2b: how an order goes':"M2b: how an order goes: two legs on 0101's fields, a shipping work order with no ship-to comes to us",
'M3: Money section':"M3: the Money section: Outstanding with aging, Take a payment across orders, Credits on account",
'M4: Place an order, guided':"M4: Place an order, guided, the archived E-Commerce orders page retired",
'M5: several destinations':"M5: several destinations",
'M6: catalog':"M6: the catalog: Services, Categories",
'M7: credit terms':"M7: credit terms, Net 15 / Net 30, granted by Jason",
'Client emails via the one notification system':"Client order emails through the one dispatcher",
"Vendor's default category from QuickBooks":"A vendor's default category from QuickBooks, once confirmed in the sandbox",
}
R=[
('control-board','Work queue phase 2','Open a task to scope it, set priority, list its steps, and work it in a thread with Gemini and Claude','Scope editor, priority, steps, thread; Claude inside the $40 cap; Start the build goes via the session manager','Starting builds directly','A task can be scoped and worked to Done on the site','1','Your spec, 2026-11-24',[]),
('control-board','Backlog seed','Every older queued item in the Work queue',"session-manager.md's working queue, each with its source",'Items already done','All open items show in the queue','1','Includes App builder and App store',[]),
('control-board','No Undo on alarm acknowledgements','"Seen by …" as a state line','Every alarm','','No Undo on any alarm','2','Rule #1314',[]),
('control-board','Diagnose','Step-by-step check of one item, outside in',"Diagnostics page, from the item's dependencies",'','Opens from any issue; first failing step offers its fix','2','',[]),
('control-board','Workflow-shape audit','Report: which workflows are lists vs processes, and which are mis-shaped','Read-only, live data','Changes to the site','Report to you','3','',[]),
('control-board','Ledger tab','One row per paid account: cap, spend, bills, source','Modules','','Every paid service on it','2','',[]),
('control-board','Find box','"/" finds any service, key, account, workflow, alarm, setting','System Configuration','','Jumps to the exact card','3','',[]),
('control-board','Request a fix follow-through','Check runs when the fix goes live; one notice','','','Requests clear by themselves','2','',[]),
('control-board','Outside links in the browser','↗ with service and account; first-tap hint on phones','Whole admin','','QuickBooks etc. open in the browser','3','',[]),
('control-board','Crawler 503 retry','A batch answered 503 retries after a pause','W1 loads','','No whole-task failure from one 503','1','Foursquare run #2148',[]),
('control-board','Receipt counts','"received 100, accounted for 80" fixed','Loads, W2 places, refresh','','Counts add up','3','',[]),
('control-board','Plain alarm titles','Plain names for /api/crawl/…','','','','3','',[]),
('control-board','The :17 crowd','Spread the hourly jobs','Scheduler presets, sync moves lines','','Every hourly job runs each hour','2','After your :02 test',[]),
('orders','M2b: how an order goes',"Delivery reads the vendor's leg until Receive, then the leg to the client","0101's methods, the Change-how-it-goes step, vendor email",'Courier or freight booking through outside services','Live; Order #12 reads right','2','Built, paused by you (PR #1346)',['0101']),
('orders','M3: Money section','Who owes what (aging), Take a payment across orders, credits on account','','','','2','',[]),
('orders','M4: Place an order, guided','Client, items, where and tax, terms, review and send','The old E-Commerce orders page retired','','','2','',[]),
('orders','M5: several destinations','One order, several ship-tos, one invoice each','Decision 0177','','','3','',['0177']),
('orders','M6: catalog','Services and categories as their own lists','','','','3','',[]),
('orders','M7: credit terms','Net 15 / Net 30, granted by you only','Decision 0178','','','3','',['0178']),
('orders','Client emails via the one notification system','','','','','3','',['0172']),
('orders',"Vendor's default category from QuickBooks","Bills use the vendor's own category first",'','','Confirmed in the sandbox','3','',[]),
('orders',"Order #12's books",'$1,111.14 in QuickBooks vs $809.10 on the order','','','','1','Yours to check',[]),
('backlog','App builder: 0175 R3','The eight-stage strip and its actions on every module','','','','2','Decision 0175',['0175']),
('backlog','App builder: 0175 R4','One definition shared by every module','','','','2','Decision 0175',['0175']),
('backlog','App store (0169)',"The Shop's App store, built on the app builder",'','','','2','After 0175 R3/R4',['0169','0175']),
('backlog','App store apps','QuickBooks Bookkeeper and Payments, Stripe, Facebook Personal and Business, Storefront, outside mailboxes','','','','3','Decision 0169',['0169']),
('backlog','W15 Funnel','','','','','3','Decision 0170',['0170']),
('backlog','State licence lists','','','','','3','Decision first',[]),
('backlog',"Release B's leftovers",'','','','','3','',[]),
('backlog','Backups for parts with none','','','','','3','',[]),
('backlog','OpenStreetMap coverage','From our Geofabrik copy','','','','3','',[]),
('backlog','Dispatch tool, the rest','Message gate rules, role-based actions, a log of every action','','','','3','Decision first',[]),
('backlog','Insights: people vs bots','Real visitors apart from automated; new vs returning','','','','3','Free',[]),
('backlog','.ai domains: 2 years','Register, renew and price .ai for 2 years','','','Before domain sales open','3','',[]),
('backlog','Routes on our site (0174 R5)','openrouteservice routes on our map','','','','3','Waits for you to allow its 2 hosts',['0174']),
("backlog","Communications' open items",'Texting (decision first), journeys and Marketplace feed in Pulse','','','','3','',['0172']),
('backlog','Pulse load-time warning','','','','','3','',[]),
('backlog','Area clusters by funnel level','The ten "Albuquerque" clusters','','','','3','',[]),
('backlog','Your cleanup, visual fixes, purge lists','From Nov 17','','','','3','Lists in session-manager.md',[]),
]
def q(v):
    if v is None or v=='': return "NULL"
    return "'"+v.replace(';',' ·').replace("'","''")+"'"
SRC="Jason's scope doc (Work queue: task scopes), 2026-11-24"
out=["-- app/migrations/2026-11-24_task_seed.sql: every row of Jason's scope doc (\"Work queue: task scopes\", 2026-11-24, his words: \"i need all",
"-- the tasks in the que\") in the Work queue, with its scope (goal, in, out, done when, priority) and the decisions it names. A task already",
"-- in the queue gets its scope (his notes on it into its source), a new one is added once: safe to run twice.",
"CREATE TABLE IF NOT EXISTS control_gauge_task_adrs (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, task_id INT UNSIGNED NOT NULL, adr CHAR(4) NOT NULL, at DATETIME NOT NULL, who VARCHAR(80) NOT NULL DEFAULT '', removed_at DATETIME NULL, removed_by VARCHAR(80) NOT NULL DEFAULT '', KEY task_adr (task_id, adr)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"]
for (tb,task,goal,ins,outs,done,pr,notes,adrs) in R:
    g='area:'+tb; item=E.get(task,task)
    if task not in E:
        rel = "'task-seed.zip'" if task=='Backlog seed' else "''"
        out.append(f"INSERT INTO control_gauge_tasks (at, tz, gauge, item, kind, what, bucket, value, who, uid, why, source, rel) SELECT UTC_TIMESTAMP(), '', {q(g)}, {q(item)}, 'fix', {q(item)}, 'task', '', 'the queue''s seed', 0, {q(notes or 'In the scope doc')}, {q(SRC)}, {rel} FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM control_gauge_tasks WHERE kind = 'fix' AND gauge = {q(g)} AND item = {q(item)});")
    sets=f"goal = {q(goal)}, in_scope = {q(ins)}, out_scope = {q(outs)}, done_when = {q(done)}, priority = {pr}"
    if task in E and notes: sets += f", source = {q(SRC + ' · note: ' + notes)}"
    out.append(f"UPDATE control_gauge_tasks SET {sets} WHERE kind = 'fix' AND bucket = 'task' AND gauge = {q(g)} AND item = {q(item)};")
    for a in adrs:
        out.append(f"INSERT INTO control_gauge_task_adrs (task_id, adr, at, who) SELECT t.id, '{a}', UTC_TIMESTAMP(), 'the queue''s seed' FROM control_gauge_tasks t WHERE t.kind = 'fix' AND t.gauge = {q(g)} AND t.item = {q(item)} AND NOT EXISTS (SELECT 1 FROM control_gauge_task_adrs x WHERE x.task_id = t.id AND x.adr = '{a}' AND x.removed_at IS NULL);")
s="\n".join(out)+"\n"
assert s.count(';')==len(out)-3, 'a stray semicolon'
open('app/migrations/2026-11-24_task_seed.sql','w').write(s)
print(len(R),'rows,',sum(1 for r in R if r[1] not in E),'new,',sum(len(r[8]) for r in R),'decisions')
