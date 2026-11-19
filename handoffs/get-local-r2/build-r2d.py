#!/usr/bin/env python3
"""Get Local R2, part 4: the comments box that invites a comment (Jason approved the cards-and-comments plan: "An open box
right there, 'Been to Clubhouse Grill? Share a tip…', that grows when tapped. For someone not signed in, tapping it asks
them to sign in. A few starters under it: Best thing to order · Parking · Good for kids · Accessible? 'Comments are public'
as a small note under the box, not next to the button. The 18+ rule and the Report button stay").
tv_engage_comments($pdo, $kind, $ref, $o): $o['title'] names the place in the box; $o['starters'] the starters (a
listing's page passes the approved four). Replayable: build-r2d.py <root> with app/ui/engage-ui.php, website/listing/view.php."""
import sys, os
R = sys.argv[1]
def patch(rel, pairs):
    p = os.path.join(R, rel); s = open(p, encoding='utf-8').read()
    for a, b in pairs:
        assert s.count(a) == 1, (rel, a[:90], s.count(a)); s = s.replace(a, b)
    open(p, 'w', encoding='utf-8').write(s)
U = 'app/ui/engage-ui.php'
p = os.path.join(R, U); s = open(p, encoding='utf-8').read()
i = s.index("    if (!$uid) {\n        // a guest: the account panel, right here (js/tv-join.js), then back to the comments\n")
j = s.index("    return $h . '</section>';\n}", i)
s = s[:i] + (
"    // the box that invites a comment, open right here (Jason approved, 2026-11-19): it grows when tapped; a guest's tap\n"
"    // asks them to sign in (js/tv-join.js), then back to the comments; starters under it; \"Comments are public\" as a note\n"
"    $ask = $title !== '' ? 'Been to ' . $title . '? Share a tip…' : 'Been here? Share a tip…';\n"
"    $st = '';\n"
"    if ($starters) { $st = '<p class=\"eg-starters\" aria-label=\"Start with\">'; foreach ($starters as $w) $st .= '<button type=\"button\" class=\"eg-st\" data-eg-start=\"' . tv_e($w) . '\">' . tv_e($w) . '</button>'; $st .= '</p>'; }\n"
"    if (!$uid) {\n"
"        $need = ' data-needs-account=\"Comments come back to you: replies, edits and removing them. Create a free account (it takes a minute) to comment.\" data-next=\"' . tv_e((string) ($_SERVER['REQUEST_URI'] ?? '/') . '#comments') . '\"';\n"
"        $h .= '<div class=\"eg-box\"><button type=\"button\" class=\"eg-in eg-fake\"' . $need . '>' . tv_e($ask) . '</button>'\n"
"            . ($starters ? str_replace('class=\"eg-st\"', 'class=\"eg-st\"' . $need, $st) : '')\n"
"            . '<p class=\"eg-note\">Comments are public. You need to be 18 or older.</p></div>';\n"
"    } else {\n"
"        require_once __DIR__ . '/../lib/AgeGate.php';\n"
"        $h .= '<form data-eg-form class=\"eg-box\"><textarea class=\"eg-in eg-grow\" maxlength=\"2000\" rows=\"1\" placeholder=\"' . tv_e($ask) . '\" aria-label=\"' . tv_e($ask) . '\" required></textarea>' . $st\n"
"            . (!AgeGate::confirmed($pdo, $uid) ? '<label class=\"eg-note\"><input type=\"checkbox\" data-eg-age required> I am 18 or older</label>' : '')\n"
"            . '<p class=\"eg-row\"><button class=\"eg-b\" type=\"submit\">Post comment</button> <span class=\"eg-note\" data-eg-msg></span></p><p class=\"eg-note\">Comments are public.</p></form>';\n"
"    }\n") + s[j:]
s = s.replace("function tv_engage_comments(PDO $pdo, string $kind, string $ref): string\n{",
"function tv_engage_comments(PDO $pdo, string $kind, string $ref, array $o = []): string\n{\n"
"    $title = trim((string) ($o['title'] ?? '')); $starters = array_values(array_filter(array_map('strval', (array) ($o['starters'] ?? []))));", 1)
assert "array $o = []" in s
s = s.replace("    if (!$list) $h .= '<p class=\"eg-note\" data-eg-none>No comments yet. Been here? Share a tip.</p>';",
              "    if (!$list) $h .= '<p class=\"eg-note\" data-eg-none>No comments yet: be the first.</p>';", 1)
s = s.replace("  .eg-note{font-size:.8rem;", "  .eg-box{margin-top:.4rem}.eg-fake{display:block;text-align:left;color:var(--tv-meta);cursor:text;min-height:2.8rem!important}\n"
"  .eg-grow{min-height:2.8rem;transition:min-height .15s}.eg-grow:focus,.eg-grow:not(:placeholder-shown){min-height:6rem}\n"
"  .eg-starters{display:flex;flex-wrap:wrap;gap:.35rem;margin:.45rem 0 .1rem}.eg-st{border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);border-radius:999px;padding:.25rem .7rem;font:500 .82rem var(--tv-font-ui);cursor:pointer}\n"
"  .eg-st:hover{border-color:var(--tv-link);color:var(--tv-link)}.eg-row{margin:.4rem 0 0}\n"
"  .eg-note{font-size:.8rem;", 1)
open(p, 'w', encoding='utf-8').write(s)
patch('website/listing/view.php', [
 ("echo tv_engage_comments($pdo, 'listing', (string) $eid);",
  "echo tv_engage_comments($pdo, 'listing', (string) $eid, ['title' => (string) $biz['name'], 'starters' => ['Best thing to order', 'Parking', 'Good for kids', 'Accessible?']]);   // the box that invites a tip (2026-11-19)")])
pe = os.path.join(R, 'website/js/engage.js'); e = open(pe, encoding='utf-8').read().rstrip()
assert e.endswith('})();'), e[-40:]
e = e[:-5] + ("  // a starter begins the tip: its words, then the box, ready to type (2026-11-19); a guest's starter asks them to sign in first\n"
  "  document.addEventListener('click', function (e) {\n"
  "    var b = e.target.closest('[data-eg-start]'); if (!b || b.hasAttribute('data-needs-account')) return;\n"
  "    var t = b.closest('form') && b.closest('form').querySelector('textarea'); if (!t) return;\n"
  "    var w = b.getAttribute('data-eg-start').replace(/\\?$/, '') + ': '; if (t.value.indexOf(w) < 0) t.value = (t.value ? t.value.replace(/\\s*$/, '\\n') : '') + w;\n"
  "    t.focus(); t.setSelectionRange(t.value.length, t.value.length);\n"
  "  });\n})();\n")
open(pe, 'w', encoding='utf-8').write(e)
print('built')
