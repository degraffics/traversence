"""gauges.py reads the live mirror where it's told (2026-11-27: the site audit failed on every run since run 69, its fence step
calling gauges.py, which looked only at the session's /home/user/traverse-3way-sync; CI's checkout is ./mirror).
  python3 tools/page-audit/test_gauges_mirror.py [source mirror]   (default /home/user/traverse-3way-sync)
Copies the four files the generators read into a fixture folder and runs gauges.py there with --mirror, then with TV_MIRROR;
a path with no mirror is refused, naming the path."""
import os, shutil, subprocess, sys, tempfile
HERE = os.path.dirname(os.path.abspath(__file__))
SRC = sys.argv[1] if len(sys.argv) > 1 else '/home/user/traverse-3way-sync'
FILES = ['sync-app/admin/manifests.php', 'sync-app/lib/Automation.php', 'sync-app/lib/Preflight.php', 'sync-app/lib/service-defs.php']
ok = fail = 0
def t(name, good, got=''):
    global ok, fail
    if good: ok += 1; print('  ok  ' + name)
    else: fail += 1; print('FAIL  ' + name + (' — ' + str(got)[:400] if got else ''))
if not all(os.path.isfile(os.path.join(SRC, f)) for f in FILES):
    print(f'skipped: no mirror at {SRC} with {", ".join(FILES)}'); sys.exit(0)
d = tempfile.mkdtemp(prefix='gauges-mirror-')
try:
    fx = os.path.join(d, 'fixture mirror')   # a space in the path, as a checkout's could have
    for f in FILES: os.makedirs(os.path.dirname(os.path.join(fx, f)), exist_ok=True); shutil.copy(os.path.join(SRC, f), os.path.join(fx, f))
    env = {k: v for k, v in os.environ.items() if k != 'TV_MIRROR'}
    run = lambda args, e=env: subprocess.run([sys.executable, os.path.join(HERE, 'gauges.py')] + args, capture_output=True, text=True, env=e, cwd=d)
    r = run(['--mirror', fx, '--out', os.path.join(d, 'a.json')])
    t('--mirror <fixture>: written from the fixture', r.returncode == 0 and os.path.isfile(os.path.join(d, 'a.json')), r.stdout + r.stderr)
    r = run(['--mirror', 'fixture mirror', '--out', os.path.join(d, 'b.json')])
    t('… a path relative to where it runs (CI: --mirror mirror)', r.returncode == 0 and os.path.isfile(os.path.join(d, 'b.json')), r.stdout + r.stderr)
    r = run(['--out', os.path.join(d, 'c.json')], dict(env, TV_MIRROR=fx))
    t('TV_MIRROR=<fixture>: the same', r.returncode == 0 and open(os.path.join(d, 'a.json')).read().split('"generated_at"')[0] == open(os.path.join(d, 'c.json')).read().split('"generated_at"')[0], r.stdout + r.stderr)
    r = run(['--mirror', os.path.join(d, 'none'), '--out', os.path.join(d, 'x.json')])
    t('no mirror there: refused (exit 1), naming the path, nothing written', r.returncode == 1 and os.path.join(d, 'none') in (r.stdout + r.stderr) and not os.path.exists(os.path.join(d, 'x.json')), r.stdout + r.stderr)
finally:
    shutil.rmtree(d, ignore_errors=True)
# the generators gauges.py imports find the repo from where they are, never a session's path (CI checks out elsewhere)
import re
SRC_DIR = os.path.join(HERE, '..', '..', 'handoffs', 'control-board-plan', 'step3', 'mockups', 'src')
bad = []
for f in sorted(os.listdir(SRC_DIR)):
    if not f.endswith('.py') or f == 'board-validate.py': continue   # board-validate takes its path as an argument
    for n, line in enumerate(open(os.path.join(SRC_DIR, f)), 1):
        if '/home/user/traversence' in line: bad.append(f'{f}:{n}')
t('no generator hard-codes the repo\'s path (each finds it from its own file)', not bad, bad)
print(f'{ok} of {ok + fail} checks pass'); sys.exit(1 if fail else 0)
