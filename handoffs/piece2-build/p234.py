# The P2-3+P2-4 root: the live mirror, the bench's Piece 2 files over it, and P2-5's pieces taken out (they go in team-batch.zip).
# Kept with the builds (2026-11-25): how team-runner.zip (P2-3 + P2-4) was cut from the bench, which also holds P2-5. Its check: every suite passes on the root it makes.
import os, re, shutil, subprocess, sys
M='/home/user/traverse-3way-sync'; B='/home/user/bench/w/root'; O='/home/user/bench/p234/root'
shutil.rmtree('/home/user/bench/p234', ignore_errors=True); os.makedirs(O)
shutil.copytree(M+'/sync-app', O+'/app'); shutil.copytree(M+'/sync-storage', O+'/website')
FILES=['app/admin/control-console.php','app/lib/GaugeTasks.php','website/admin/api.php','website/js/admin-control.js','app/ui/faq.md','app/admin/tools.php','app/lib/Scheduler.php','app/lib/staging-tables.php','app/admin/manifests.php','app/admin/sections.php','app/lib/Switches.php',
       'app/admin/team-console.php','app/lib/TeamRunner.php','app/scripts/team_tick.php','app/scripts/team_audit_tick.php','app/migrations/2026-11-25_team_runs.sql','app/migrations/2026-11-25_team_flag.sql']
for f in FILES: os.makedirs(os.path.dirname(O+'/'+f), exist_ok=True); shutil.copy(B+'/'+f, O+'/'+f)
def ed(f, pairs):
    p=O+'/'+f; s=open(p).read()
    for a,b in pairs:
        if isinstance(a, re.Pattern):
            s2,n=a.subn(b,s); assert n==1,(f,a.pattern[:60],n); s=s2
        else:
            assert s.count(a)==1,(f,a[:70],s.count(a)); s=s.replace(a,b)
    open(p,'w').write(s)
L=lambda f: open(M+('/sync-app/'+f[4:] if f.startswith('app/') else '/sync-storage/'+f[8:])).read()
live_g=L('app/lib/GaugeTasks.php').splitlines(True)
g=open(O+'/app/lib/GaugeTasks.php').read().splitlines(True)
def liveline(sub): return [l for l in live_g if sub in l][0]
ed('app/lib/GaugeTasks.php', [
  ("        if (!self::gaugeOk($gauge) || !in_array($kind, ['scope', 'target', 'batch'], true)) throw new RuntimeException('Which gauge?');\n", liveline("in_array($kind, ['scope', 'target'], true)")),
  (re.compile(r"        if \(\$kind === 'batch' && !preg_match[^\n]*\n"), ''),
  (re.compile(r"        ControlLog::add\(\$pdo, 'control', '', \(\$kind === 'batch' \? [^\n]*\n"), liveline("ControlLog::add($pdo, 'control', '', ($kind === 'scope' ?").replace('\\','\\\\')),
  ("WHERE kind IN ('scope','target','batch')", "WHERE kind IN ('scope','target')"),
  (re.compile(r"\n    /\*\*\n     \* The Founder's answers relayed.*?\n    }\n", re.S), '\n'),
])
ed('app/admin/control-console.php', [
  (re.compile(r"/\*\*\n \* Today's batch.*?\n}\n\n(?=/\*\*)", re.S), ''),
  ("        . tv_cb_wq_batch($pdo, $active)\n", ""),
])
ed('website/admin/api.php', [
  ("'qrundismiss', 'qbatch', 'qflag'], true)", "'qrundismiss', 'qflag'], true)"),
  (re.compile(r"                    // Approve the batch \(decisions/0179 rule 1; P2-5\).*?Response::success\(\['message' => 'Approved\. The session manager has it at the next hourly report\.'\]\); }\n", re.S), ''),
])
ed('website/js/admin-control.js', [(re.compile(r"    if \(act === 'batchok'\)[^\n]*\n"), '')])
ed('app/ui/faq.md', [(re.compile(r"### I'm on the Traversence team\. What is Today's batch at the top of the Work queue\?\n.*?\n\n", re.S), '')])
for f in FILES:
    if f.endswith('.php'): r=subprocess.run(['php','-l',O+'/'+f],capture_output=True,text=True); print(f, r.stdout.strip().split('\n')[-1][:40])
# for the tests only (never in the zip): the migrations live has already run and deleted (#287, #288)
H='/home/user/traversence/handoffs/piece2-build/'
for f in ['team-roster/build/app/migrations/2026-11-25_team_roster.sql','team-roster/build/app/migrations/2026-11-25_team_roster_seed.sql','team-tickets/build/app/migrations/2026-11-25_tickets.sql','team-tickets/build/app/migrations/2026-11-25_tickets_stage.sql']:
    shutil.copy(H+f, O+'/app/migrations/'+os.path.basename(f))
