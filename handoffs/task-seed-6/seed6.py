# task-seed-6 (the release line, as Build & QA (21), for the PM (8)): one Work-queue ticket per agent, each its own
# release, in the CEO's wave order (Jason, 2026-11-28: "Bring each one online individually… just in case we run out of usage"),
# and task-seed-5's four team group tickets withdrawn. Data only. Ids named (MAX(id)+1, guarded by NOT EXISTS), as task-seed-4. Run: python3 seed6.py
import json
D = json.load(open('items.json'))
WHO, WHO4, WHO5 = 'task-seed-6 (Build & QA, 21)', 'task-seed-4 (Build & QA, 21)', 'task-seed-5 (Build & QA, 21)'
def qs(v): return "'" + str(v or '').replace(';', ',').replace("'", "''") + "'"
def find(g, item): return f"SELECT id FROM control_gauge_tasks WHERE kind = 'fix' AND gauge = {qs(g)} AND item = {qs(item)} ORDER BY id LIMIT 1"
out = ["-- app/migrations/2026-11-28_task_seed_6.sql: one Work-queue ticket per agent (0180's 30 roles after the Founder and the CEO, and the 12",
       "-- advocates), each its own release, in the CEO's wave order (Jason, 2026-11-28). task-seed-5's four team group tickets are withdrawn.",
       "-- Data only, no CREATE, nothing deleted. Every id named (MAX(id)+1, guarded by NOT EXISTS). A ticket is made only where its goal",
       "-- exists and no ticket has its (gauge, item). A withdrawn ticket is kept, and only withdrawn while still as task-seed-5 wrote it."]
# the lane column is R2's (2026-11-27): written only where it exists
HAS_LANE = "(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_gauge_tasks' AND COLUMN_NAME = 'lane')"
for x in D['new']:
    stage_ok = {'work': ('planning', 'building', 'implementation', 'confirmation'), 'audit': ('found', 'diagnosing', 'confirmed')}[x['ticket']]
    assert x['stage'] in stage_ok, x
    out.append("SET @n = (SELECT COALESCE(MAX(id), 0) + 1 FROM control_gauge_tasks);")
    out.append(f"SET @p = ({find('area:goals', x['parent'])});")
    out.append("INSERT INTO control_gauge_tasks (id, at, tz, gauge, item, kind, what, bucket, value, who, uid, why, source, rel, priority, ticket, stage, stage_at, parent_id) "
               f"SELECT @n, UTC_TIMESTAMP(), '', {qs(x['gauge'])}, {qs(x['what'])}, 'fix', {qs(x['what'])}, 'task', '', {qs(WHO)}, 0, {qs(x['why'])}, {qs(x['source'])}, {qs(x['rel'])}, {int(x['priority'])}, "
               f"'{x['ticket']}', '{x['stage']}', UTC_TIMESTAMP(), @p FROM DUAL WHERE @p IS NOT NULL AND NOT EXISTS ({find(x['gauge'], x['what'])});")
    upd = f"UPDATE control_gauge_tasks SET lane = '{x['lane']}' WHERE kind = 'fix' AND gauge = {qs(x['gauge'])} AND item = {qs(x['what'])} AND who = {qs(WHO)} AND (lane IS NULL OR lane = '')"
    out.append(f"SET @s = (SELECT IF({HAS_LANE} = 1, {qs(upd)}, 'DO 0'));")
    out.append("PREPARE q FROM @s;"); out.append("EXECUTE q;"); out.append("DEALLOCATE PREPARE q;")
for m in D['moved']:
    w = f"kind = 'fix' AND gauge = {qs(m['gauge'])} AND item = {qs(m['item'])} AND who = {qs(WHO4)}"
    out.append(f"UPDATE control_gauge_tasks SET source = {qs(m['source'])} WHERE {w} AND source = {qs(m['seed_source'])};")
    if m['stage'] != 'planning':   # moved only while still as seeded (Planning, no release named): a person's move is never undone
        out.append(f"SET @t = (SELECT id FROM control_gauge_tasks WHERE {w} AND stage = 'planning' AND rel = '' ORDER BY id LIMIT 1);")
        out.append("SET @v = (SELECT COALESCE(MAX(id), 0) + 1 FROM control_gauge_task_moves);")
        out.append(f"INSERT INTO control_gauge_task_moves (id, task_id, at, tz, from_stage, to_stage, who, why) SELECT @v, @t, UTC_TIMESTAMP(), '', 'planning', '{m['stage']}', {qs(WHO)}, 'Its real phase: built, waiting to be sent' FROM DUAL WHERE @t IS NOT NULL;")
        out.append(f"UPDATE control_gauge_tasks SET stage = '{m['stage']}', stage_at = UTC_TIMESTAMP(), rel = {qs(m['rel'])} WHERE id = @t;")
# task-seed-5's four team group tickets, replaced by one ticket per agent: withdrawn (kept, never deleted), only while still as seeded
HOW = 'Withdrawn: replaced by one ticket per agent, each its own release (the CEO, 2026-11-28, quoting Jason)'
for x in D.get('withdrawn', []):
    out.append(f"UPDATE control_gauge_tasks SET undone_at = UTC_TIMESTAMP(), done_how = {qs(HOW)} WHERE kind = 'fix' AND gauge = {qs(x['gauge'])} AND item = {qs(x['item'])} AND who = {qs(WHO5)} AND stage = 'planning' AND rel = '' AND owner = '' AND undone_at IS NULL AND cleared_at IS NULL;")
open('app/migrations/2026-11-28_task_seed_6.sql', 'w').write("\n".join(out) + "\n")
print(len(D['new']), 'new tickets', len(D.get('withdrawn', [])), 'withdrawn')
