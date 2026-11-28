# The order page's split fix (slot #8): the lots dialog's Split button no longer shares the save group's data-split, so
# the save group's script (Save ▾'s list, its confirm question with the QuickBooks invoice and COGS warnings) runs again.
#   python3 patch.py <root laid out as the mirror>
import sys
R = sys.argv[1].rstrip('/'); p = R + '/sync-storage/admin/client-order.php'; s = open(p, encoding='utf-8').read()
def rep(s, old, new, what):
    assert s.count(old) == 1, f'{what}: anchor found {s.count(old)} times'
    return s.replace(old, new)
s = rep(s, """equal lots <button type="button" class="hb-b" data-split>Split</button>""", """equal lots <button type="button" class="hb-b" data-lots-split>Split</button>""", 'lots button')
s = rep(s, """d.querySelector('[data-split]').onclick""", """d.querySelector('[data-lots-split]').onclick""", 'lots handler')
s = rep(s, """  document.querySelectorAll('[data-split]').forEach(function (sp) {""",
"""  document.querySelectorAll('.co-split[data-split]').forEach(function (sp) {   // the save groups only (the lots dialog's Split is data-lots-split)""", 'save groups')
open(p, 'w', encoding='utf-8').write(s); print('patched', p)
