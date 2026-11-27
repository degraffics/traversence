"""P2-5's file side (decisions/0179 rule 1; Jason's Q3 A): gauges.py reads releases/batch.json and gauge-pickups.json's
"answers" into gauges.json, checked field by field. Run: python3 tools/team-tests/batch_py.py"""
import importlib.util, json, os, sys, tempfile
here = os.path.dirname(os.path.abspath(__file__))
spec = importlib.util.spec_from_file_location('gauges', os.path.join(here, '..', 'page-audit', 'gauges.py'))
g = importlib.util.module_from_spec(spec); sys.argv = ['gauges.py']; spec.loader.exec_module(g)
ok = bad = 0
def t(n, c, got=None):
    global ok, bad
    if c: ok += 1; print('  ok  ' + n)
    else: bad += 1; print('FAIL  ' + n + ('' if got is None else ' — ' + json.dumps(got)[:300]))
def tmp(d):
    f = tempfile.NamedTemporaryFile('w', suffix='.json', delete=False); f.write(d if isinstance(d, str) else json.dumps(d)); f.close(); return f.name
b = g._batch(tmp({'note': 'x', 'date': '2026-11-26', 'lines': [
    {'release': 'team-runner.zip', 'what': 'The runner', 'why': 'Piece 2', 'decision': '0179', 'checks': 'runner.php 45', 'way_back': 'Roll back', 'tickets': [12, '13', 'x'], 'extra': 'dropped'},
    {'release': '', 'what': 'no release: left out'}, 'not a line']}))
t('a batch: its date and lines, each field as words, ticket ids as numbers, extra fields and junk left out',
  b == {'date': '2026-11-26', 'lines': [{'release': 'team-runner.zip', 'what': 'The runner', 'why': 'Piece 2', 'decision': '0179', 'checks': 'runner.php 45', 'way_back': 'Roll back', 'tickets': [12, 13]}]}, b)
t('no date (the template), a bad date, a broken file, no file: no batch', g._batch(tmp({'date': '', 'lines': []})) == {} and g._batch(tmp({'date': 'tomorrow'})) == {} and g._batch(tmp('{not json')) == {} and g._batch('/nonexistent/batch.json') == {})
t('the committed template reads as no batch', g._batch() == {} or g._batch().get('date') != '', g._batch())
a = g._answers(tmp({'pickups': [], 'answers': [{'id': 7, 'ans': 'b', 'words': '', 'at': '2026-11-26T09:00:00Z'}, {'id': 8, 'ans': 'o', 'words': 'Keep it public-only' * 1}, {'id': 9, 'ans': 'yes'}, {'id': 'x', 'ans': 'a'}, 'junk']}))
t('answers: id, a/b/o and the words kept; any other answer, a bad id or junk left out', [x['id'] for x in a] == [7, 8] and a[1]['words'] == 'Keep it public-only' and a[0]['ans'] == 'b', a)
t('words cut at 4000', len(g._answers(tmp({'answers': [{'id': 1, 'ans': 'o', 'words': 'w' * 5000}]}))[0]['words']) == 4000)
t('no answers key, no file: none', g._answers(tmp({'pickups': []})) == [] and g._answers('/nonexistent.json') == [])
s, _ = g.build()
import io, contextlib
_e = io.StringIO()
with contextlib.redirect_stderr(_e):
    m = g._messages(tmp({'messages': [{'id': 'a', 'subject': 's', 'link': 'https://github.com/degraffics/traversence/x.pdf'}, {'id': 'b', 'subject': 's', 'task': 41}, {'id': 'c', 'subject': 's'}, {'id': 'd', 'subject': 's', 'task': True}]}))
t('messages carry link and task (notify-founder\'s Open it); a true task is no task', m[0].get('link', '').endswith('x.pdf') and m[1].get('task') == 41 and 'task' not in m[3], m)
t('… neither: warned at write time, never refused', [x['id'] for x in m] == ['a', 'b', 'c', 'd'] and 'message c has neither' in _e.getvalue() and 'message d has neither' in _e.getvalue() and 'message a' not in _e.getvalue(), _e.getvalue())
_e2 = io.StringIO()
with contextlib.redirect_stderr(_e2):
    m2 = g._messages(tmp({'messages': [{'id': 'f1', 'subject': 's', 'task': 1, 'from': 'ceo'}, {'id': 'f2', 'subject': 's', 'task': 1, 'from': 'someone'}, {'id': 'f3', 'subject': 's', 'task': 1, 'body': 'a\nb\nc\nd'}]}))
t('from: ceo or session-manager carried, anything else left out', m2[0].get('from') == 'ceo' and 'from' not in m2[1], m2)
t('… a body over 3 lines warned (the template is 2-3 short lines), never refused', 'message f3 runs 4 lines' in _e2.getvalue() and len(m2) == 3 and 'f1 runs' not in _e2.getvalue(), _e2.getvalue())
t('gauges.json carries batch and answers', 'batch' in s and 'answers' in s and isinstance(s['answers'], list))
print('\n%d passed, %d failed' % (ok, bad)); sys.exit(1 if bad else 0)
