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
print('\n%d passed, %d failed' % (ok, bad)); sys.exit(1 if bad else 0)
