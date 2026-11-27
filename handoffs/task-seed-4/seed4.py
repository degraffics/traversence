# task-seed-4 (Data & Content session, as Build & QA (21), IT lane; for the PM (8)): the goals as parent tasks, then every open
# item since Nov 25 as a child linked to its goal (CLAUDE.md "Goals and records live with the roles", Jason 2026-11-27, #1552).
# Data only. Every id named (MAX(id)+1, guarded by NOT EXISTS): on staging a records table copied by catch-up has its
# AUTO_INCREMENT behind its rows (the session manager's rule until 0165's catch-up fix). Run: python3 seed4.py
import json, sys
G = json.load(open(sys.argv[1] if len(sys.argv) > 1 else 'items.json'))
WHO = 'task-seed-4 (Build & QA, 21)'
def q(v):
    if v is None or v == '': return "NULL"
    return "'" + str(v).replace(';', ',').replace("'", "''") + "'"
def qs(v): return "'" + str(v or '').replace(';', ',').replace("'", "''") + "'"
out = ["-- app/migrations/2026-11-27_task_seed_4.sql: the goals as parent tasks, each with its owner (decisions/0180's appendix), measure,",
       "-- due date and decision, then the open items since Nov 25 as their children (CLAUDE.md #1552, Jason 2026-11-27). Data only, no CREATE.",
       "-- Every id is named (MAX(id)+1, guarded by NOT EXISTS): staging's copied tables have AUTO_INCREMENT behind their rows (until 0165's fix).",
       "-- Nothing that exists is created again: a task is matched by (gauge, item), and existing tasks only gain a parent where they have none."]
def adr(task_sql, d):
    out.append("SET @a = (SELECT COALESCE(MAX(id), 0) + 1 FROM control_gauge_task_adrs);")
    out.append(f"SET @t = ({task_sql});")
    out.append(f"INSERT INTO control_gauge_task_adrs (id, task_id, adr, at, who) SELECT @a, @t, '{d}', UTC_TIMESTAMP(), {qs(WHO)} FROM DUAL WHERE @t IS NOT NULL AND NOT EXISTS (SELECT 1 FROM control_gauge_task_adrs x WHERE x.task_id = @t AND x.adr = '{d}' AND x.removed_at IS NULL);")
def find(g, item): return f"SELECT id FROM control_gauge_tasks WHERE kind = 'fix' AND gauge = {qs(g)} AND item = {qs(item)} ORDER BY id LIMIT 1"
def task(g, t, parent_sql=None):
    for k in ('what', 'why', 'source'): assert len(t.get(k, '')) <= (255 if k == 'source' else 400), (k, t['what'])
    assert len(t['what']) <= 255, t['what']
    ticket = t.get('ticket', 'work'); stage = {'work': 'planning', 'resolution': t.get('stage', 'brief'), 'audit': 'found'}[ticket]
    out.append("SET @n = (SELECT COALESCE(MAX(id), 0) + 1 FROM control_gauge_tasks);")
    out.append(f"SET @p = ({parent_sql});" if parent_sql else "SET @p = NULL;")
    out.append("INSERT INTO control_gauge_tasks (id, at, tz, gauge, item, kind, what, bucket, value, who, uid, why, source, owner, owner_at, goal, in_scope, out_scope, done_when, priority, ticket, stage, stage_at, tier, parent_id) "
               f"SELECT @n, UTC_TIMESTAMP(), '', {qs(g)}, {qs(t['what'])}, 'fix', {qs(t['what'])}, 'task', '', {qs(WHO)}, 0, {qs(t['why'])}, {qs(t['source'])}, {qs(t.get('owner',''))}, {'UTC_TIMESTAMP()' if t.get('owner') else 'NULL'}, "
               f"{q(t.get('goal'))}, {q(t.get('in'))}, {q(t.get('out'))}, {q(t.get('done'))}, {t.get('priority', 2)}, '{ticket}', '{stage}', UTC_TIMESTAMP(), {t.get('tier') or 'NULL'}, @p "
               f"FROM DUAL WHERE NOT EXISTS ({find(g, t['what'])});")
    if ticket == 'resolution':
        out.append("SET @b = (SELECT COALESCE(MAX(id), 0) + 1 FROM control_gauge_task_briefs);")
        out.append(f"SET @t = ({find(g, t['what'])});")
        out.append(f"INSERT INTO control_gauge_task_briefs (id, task_id, decision, why_you, delay_impact) SELECT @b, @t, {q(t.get('decision', t['what']))}, {q(t.get('why_you'))}, {q(t.get('delay'))} FROM DUAL WHERE @t IS NOT NULL AND NOT EXISTS (SELECT 1 FROM control_gauge_task_briefs x WHERE x.task_id = @t);")
    for d in t.get('adrs', []): adr(find(g, t['what']), d)
n_goal = n_child = n_link = 0
for goal in G['goals']:
    task('area:goals', goal); n_goal += 1
    psql = find('area:goals', goal['what'])
    for c in goal.get('children', []):
        task(c.get('gauge', 'area:' + goal['key']), c, psql); n_child += 1
    for (g, item) in goal.get('link', []):
        out.append(f"SET @p = ({psql});")
        out.append(f"UPDATE control_gauge_tasks SET parent_id = @p WHERE kind = 'fix' AND gauge = {qs(g)} AND item = {qs(item)} AND parent_id IS NULL AND @p IS NOT NULL;"); n_link += 1
    for i in goal.get('link_ids', []):
        out.append(f"SET @p = ({psql});")
        out.append(f"UPDATE control_gauge_tasks SET parent_id = @p WHERE id = {int(i)} AND parent_id IS NULL AND @p IS NOT NULL;"); n_link += 1
s = "\n".join(out) + "\n"
open('app/migrations/2026-11-27_task_seed_4.sql', 'w').write(s)
print(n_goal, 'goals', n_child, 'children', n_link, 'links')
