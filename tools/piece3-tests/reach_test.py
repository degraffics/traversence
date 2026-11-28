"""reach.py's tests (Piece 3, P3-1) on a small mirror made here: what counts as a use, and the pages reached.
Run: python3 tools/piece3-tests/reach_test.py"""
import json, os, sys, tempfile, zipfile
sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'page-audit'))
import reach as R
ok = bad = 0
def t(n, c, got=None):
    global ok, bad
    if c: ok += 1; print('  ok  ' + n)
    else: bad += 1; print('FAIL  ' + n + ('' if got is None else ' — ' + json.dumps(got, default=list)[:300]))
m = tempfile.mkdtemp()
def w(p, s):
    full = os.path.join(m, p); os.makedirs(os.path.dirname(full), exist_ok=True); open(full, 'w').write(s)
w('sync-app/lib/Orders.php', "<?php\nfinal class Orders { public static function all() {} }\n")
w('sync-app/lib/Quiet.php', "<?php\n// Orders::all() in a comment is not a use\nfinal class Quiet {}\n")
w('sync-app/ui/cart.php', "<?php\nfunction tv_cart_box() { return Orders::all(); }\n")
w('sync-app/ui/shell.php', "<?php\nfunction tv_page_start() {}\n")
w('sync-storage/cart.php', "<?php\nrequire_once __DIR__ . '/../app/ui/cart.php';\necho tv_cart_box();\n")
w('sync-storage/index.php', "<?php\nrequire_once __DIR__ . '/../app/ui/shell.php'; tv_page_start();\necho '<a href=\"/cart.php\">Cart</a>';\n")
w('sync-storage/quiet.php', "<?php\nrequire_once __DIR__ . '/../app/lib/Quiet.php';\n")
w('sync-storage/helpers.php', "<?php\nfunction page_helper_xyz() {}\n")
w('sync-storage/other.php', "<?php\npage_helper_xyz();\n")
w('sync-storage/api/orders.php', "<?php\nOrders::all();\n")
w('sync-storage/js/tv-cart.js', "window.TvCart = {};\n")
w('sync-storage/shop.php', "<?php\necho '<script src=\"/js/tv-cart.js?v=2\"></script>';\n")
texts, used = R.index(m)
d = R.pages_of('app/lib/Orders.php', used)
t('a class reached through a UI part to its page, 2 steps', d.get('/cart.php') == 2, d)
t('a plain link to a page is not a load (/ links to /cart.php)', '/' not in d, d)
t('a name in a comment is not a use', '/quiet.php' not in d, d)
t('an endpoint (api/) is never a page', not any(u.startswith('/api/') for u in d), d)
t("a page's own helper function isn't reached by another page calling the same name", '/other.php' not in R.pages_of('website/helpers.php', used), R.pages_of('website/helpers.php', used))
t('a script reached by its tag, its ?v= left off', R.pages_of('website/js/tv-cart.js', used) == {'/shop.php': 1}, R.pages_of('website/js/tv-cart.js', used))
t('a page reaches itself at 0 steps', R.pages_of('website/cart.php', used) == {'/cart.php': 0})
z = os.path.join(m, 'r.zip')
with zipfile.ZipFile(z, 'w') as f:
    f.writestr('app/lib/Orders.php', 'x'); f.writestr('website/index.php', 'x')
    f.writestr('release.json', json.dumps({'changes': [{'what': 'w', 'where': 'https://traversence.com/admin/backoffice/orders'}], 'also_changes': [{'page': '/shop.php', 'why': 'its cart line'}, {'page': '/x.php'}]}))
pages, exp = R.for_release(m, z)
t('a release: / and /faq.php first, its own page, what it names, then the reach', pages[:4] == ['/', '/faq.php', '/admin/backoffice/orders', '/cart.php'] or pages[:3] == ['/', '/faq.php', '/admin/backoffice/orders'], pages)
t('… expected: its pages, what it names, the near reach; also_changes only with a reason', '/cart.php' in exp['pages'] and '/admin/backoffice/orders' in exp['pages'] and exp['also_changes'] == [{'page': '/shop.php', 'why': 'its cart line'}], exp)
# #318 (2026-11-27): a file nearly every page loads (the shell's tv-ask.js) reaches them all but draws none of them
m2 = tempfile.mkdtemp()
def w2(p, s):
    full = os.path.join(m2, p); os.makedirs(os.path.dirname(full), exist_ok=True); open(full, 'w').write(s)
w2('sync-app/ui/admin-shell.php', "<?php\nfunction tv_admin_shell() { echo '<script src=\"/js/tv-ask.js\"></script>'; }\n")
w2('sync-app/lib/Lanes.php', "<?php\nfinal class Lanes { public static function all() {} }\n")
w2('sync-storage/js/tv-ask.js', "window.TvAsk = {};\n")
for n in ('a', 'b', 'c', 'd'):
    w2('sync-storage/admin/%s.php' % n, "<?php\nrequire_once __DIR__ . '/../../app/ui/admin-shell.php'; tv_admin_shell();\n" + ("Lanes::all();\n" if n == 'a' else ''))
w2('sync-storage/admin/e.php', "<?php\necho 'no shell';\n")
z2 = os.path.join(m2, 'r.zip')
with zipfile.ZipFile(z2, 'w') as f:
    f.writestr('website/js/tv-ask.js', 'x'); f.writestr('app/lib/Lanes.php', 'x'); f.writestr('release.json', '{}')
_, e2 = R.for_release(m2, z2)
t('a file within NEAR of half the pages or more is shared', e2['shared'] == ['website/js/tv-ask.js'], e2)
t('… its pages stay expected (a change there isn\'t drift)', all(u in e2['pages'] for u in ('/admin/a.php', '/admin/b.php', '/admin/c.php', '/admin/d.php')), e2['pages'])
t('… but only what the release\'s own files draw is drawn: the page Lanes reaches, not the others', '/admin/a.php' in e2['drawn'] and not any(u in e2['drawn'] for u in ('/admin/b.php', '/admin/c.php', '/admin/d.php')), e2['drawn'])
t('the earlier release: no shared file, so drawn is expected', exp['shared'] == [] and set(exp['drawn']) >= set(exp['pages']) - {'/shop.php'}, exp)

# the narrowed reach (Jason, 2026-11-28): a functions file the release changes reaches only the callers of what changed
m3 = tempfile.mkdtemp()
def w3(p, s):
    full = os.path.join(m3, p); os.makedirs(os.path.dirname(full), exist_ok=True); open(full, 'w').write(s)
DASH = "<?php\nfunction tv_admin_counts() { return 1; }\nfunction tv_admin_status() { return 'a'; }\nfunction tv_admin_dashboard() { return tv_admin_status(); }\n"
w3('sync-app/ui/admin-dashboard.php', DASH)
w3('sync-app/ui/section-rails.php', "<?php\nrequire_once __DIR__ . '/admin-dashboard.php';\nfunction tv_section_rail() { return tv_admin_counts(); }\n")
w3('sync-storage/admin/people.php', "<?php\nrequire_once __DIR__ . '/../../app/ui/section-rails.php';\necho tv_section_rail();\n")
w3('sync-storage/admin/dashboard.php', "<?php\nrequire_once __DIR__ . '/../../app/ui/admin-dashboard.php';\necho tv_admin_dashboard();\n")
def rel3(body):
    z3 = os.path.join(m3, 'r%d.zip' % (abs(hash(body)) % 10**6))
    with zipfile.ZipFile(z3, 'w') as f:
        f.writestr('app/ui/admin-dashboard.php', body); f.writestr('release.json', '{}')
    return R.for_release(m3, z3)[1]
e3 = rel3(DASH.replace("return 'a';", "return 'b';"))
t('narrowed: a change inside tv_admin_status reaches the dashboard that calls it, through its caller', '/admin/dashboard.php' in e3['pages'], e3)
t('narrowed: … not people.php, which loads the file only for tv_admin_counts (#329, #330)', '/admin/people.php' not in e3['pages'], e3)
e4 = rel3(DASH.replace('return 1;', 'return 2;'))
t('narrowed: a change in tv_admin_counts reaches people.php through the side menu', '/admin/people.php' in e4['pages'], e4)
e5 = rel3("<?php\nrequire_once 'x.php';\n" + DASH[6:])
t('narrowed: a change to the file\'s top-level code reaches every page that loads it, as before', '/admin/people.php' in e5['pages'] and '/admin/dashboard.php' in e5['pages'], e5)
t('changed_functions: closed over the file\'s own calls', R.changed_functions(DASH, DASH.replace("return 'a';", "return 'b';")) == {'tv_admin_status', 'tv_admin_dashboard'})
print('\n%d passed, %d failed' % (ok, bad)); sys.exit(1 if bad else 0)
