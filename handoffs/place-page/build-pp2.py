#!/usr/bin/env python3
"""The place page, step 2 (Jason, 2026-11-19: "Should share, add a photo, etc be added to minimize how much real-estate is
used on hero."; his screenshot showed QR and Save contact twice on a listing). The hero's corner keeps the numbers and ⋯:
views, ♥ where likes apply, comments. Share, QR, Share a photo (the owner's Photos and video), Save to Address Book go
into ⋯ in words, first; each thing in one place: what the page's own column already shows (map, directions, QR, the
phone's contact card) isn't repeated. Runs after build-pp.py. Replayable: build-pp2.py <root>."""
import sys, os
R = sys.argv[1]
def patch(rel, pairs):
    p = os.path.join(R, rel); s = open(p, encoding='utf-8').read()
    for a, b in pairs:
        assert s.count(a) == 1, (rel, a[:100], s.count(a)); s = s.replace(a, b)
    open(p, 'w', encoding='utf-8').write(s)

patch('app/ui/engage-ui.php', [
 # folded: Share and QR leave the bar for ⋯
 ("    $h .= '<button type=\"button\" class=\"eg-b\" data-eg-share title=\"Share\">'",
  "    $fold = !empty($o['fold']) && !empty($o['tools']);   // on a hero: the corner keeps the numbers and ⋯ (Jason, 2026-11-19)\n"
  "    if (!$fold) $h .= '<button type=\"button\" class=\"eg-b\" data-eg-share title=\"Share\">'"),
 ("    $h .= '<button type=\"button\" class=\"eg-b\" data-qr title=\"QR code\"",
  "    if (!$fold) $h .= '<button type=\"button\" class=\"eg-b\" data-qr title=\"QR code\""),
 ("    if (!empty($o['tools'])) $h .= tv_engage_tools((array) $o['tools'], (string) ($o['report'] ?? ''), $ic);",
  "    $first = '';\n"
  "    if ($fold) $first = '<button type=\"button\" role=\"menuitem\" data-eg-share-m>' . $ic('<circle cx=\"18\" cy=\"5\" r=\"3\"/><circle cx=\"6\" cy=\"12\" r=\"3\"/><circle cx=\"18\" cy=\"19\" r=\"3\"/><line x1=\"8.6\" y1=\"13.5\" x2=\"15.4\" y2=\"17.5\"/><line x1=\"15.4\" y1=\"6.5\" x2=\"8.6\" y2=\"10.5\"/>') . 'Share</button>'\n"
  "        . (($o['qr'] ?? true) ? '<button type=\"button\" role=\"menuitem\" data-eg-qr-m>' . $ic('<rect x=\"3\" y=\"3\" width=\"7\" height=\"7\" rx=\"1\"/><rect x=\"14\" y=\"3\" width=\"7\" height=\"7\" rx=\"1\"/><rect x=\"3\" y=\"14\" width=\"7\" height=\"7\" rx=\"1\"/><path d=\"M14 14h3v3h-3zM20 14v.01M14 20h.01M17 20h4v-3\"/>') . 'QR code</button>' : '')\n"
  "        . (string) ($o['first'] ?? '');\n"
  "    if (!empty($o['tools'])) $h .= tv_engage_tools((array) $o['tools'], (string) ($o['report'] ?? ''), $ic, $first);"),
 ("function tv_engage_tools(array $t, string $report, callable $ic): string\n{\n",
  "function tv_engage_tools(array $t, string $report, callable $ic, string $first = ''): string\n{\n"),
 # first the folded items, then the hero's own (<!--eg-more-->), then the tools; a tool the page doesn't pass isn't listed
 ("    $map = $t['map'] ?? '';\n",
  "    $m .= $first . '<!--eg-more-->';\n    $map = $t['map'] ?? '';\n"),
 ("    $m .= is_array($map) && isset($map['lat'], $map['lon'])",
  "    if (array_key_exists('map', $t)) $m .= is_array($map) && isset($map['lat'], $map['lon'])"),
 ("  .eg-trip{position:fixed;",
  "  .eg-ml .tv-save-wrap{display:block}.eg-ml .tv-save{display:flex!important;align-items:center;gap:.5rem;width:100%;padding:.45rem .55rem!important;border:0!important;background:none!important;border-radius:.4rem;font:500 .9rem var(--tv-font-ui)!important;color:var(--tv-ink)!important;cursor:pointer;box-shadow:none!important}\n"
  "  .eg-ml .tv-save:hover{background:var(--tv-field)!important}.eg-ml .tv-save svg{width:16px;height:16px;color:var(--tv-icon,#B45309)}.eg-ml .tv-save span{display:inline!important}.eg-ml .tv-save::after{content:none!important}.eg-ml .tv-save-note{font-size:.78rem;color:var(--tv-meta);padding:0 .55rem .35rem}\n"
  "  .eg-trip{position:fixed;"),
])

patch('website/js/engage.js', [
 ("    var rp = t.closest('.eg-ml [data-eg-report]');\n",
  "    // Share and QR from ⋯ (a hero's): read the page's own bar; the answer shows beside ⋯\n"
  "    var sm = t.closest('[data-eg-share-m]'), qm = t.closest('[data-eg-qr-m]');\n"
  "    if (sm || qm) {\n"
  "      var menu = (sm || qm).closest('.eg-ml'), home = menu && menu._home, owner = home && home.closest('.eg-bar'), btn = home && home.querySelector('.eg-more');\n"
  "      closeMenus();\n"
  "      var here = location.origin + location.pathname + location.search, title = (owner && owner.getAttribute('data-eg-title')) || document.title;\n"
  "      if (qm) { if (window.tvQr) tvQr(here, { anchor: btn, title: title }); return; }\n"
  "      var url = window.tvShort ? tvShort.now(here) : here;\n"
  "      if (navigator.share) { navigator.share({ title: title, url: url }).catch(function () {}); return; }\n"
  "      var said = function () { if (window.TvAsk) TvAsk.say('Link copied', { anchor: btn }); }, ask = function () { if (window.TvAsk) TvAsk.ask('Copy this link', url, { yes: 'Done' }); };\n"
  "      if (navigator.clipboard) navigator.clipboard.writeText(url).then(said, ask); else ask();\n"
  "      return;\n"
  "    }\n"
  "    var rp = t.closest('.eg-ml [data-eg-report]');\n"),
 ("    if (t.closest('.eg-ml a, .eg-ml [data-tv-suggest]')) { setTimeout(closeMenus, 0); return; }\n",
  "    if (t.closest('.eg-ml a, .eg-ml [data-tv-suggest], .eg-ml [data-hm-kind]')) { setTimeout(closeMenus, 0); return; }\n"),
])

# the hero: its camera (or the owner's Photos and video) goes into ⋯ when the bar has one
patch('app/ui/explore-hero.php', [
 ("    if ($acts !== '') $h .= '<div class=\"xh-acts\">' . $acts . '</div>';",
  "    // a bar with ⋯ takes the camera in words, after Share and QR (Jason, 2026-11-19: \"minimize how much real-estate is used on hero\")\n"
  "    if (strpos($acts, '<!--eg-more-->') !== false) {\n"
  "        $cam = '<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" aria-hidden=\"true\"><path d=\"M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z\"/><circle cx=\"12\" cy=\"13\" r=\"4\"/></svg>';\n"
  "        $in = '';\n"
  "        if (preg_match('#<button type=\"button\" class=\"xh-own\"[^>]*data-hm-kind=\"([^\"]*)\" data-hm-ref=\"(\\d+)\">.*?</button>#s', $acts, $mm)) {\n"
  "            $acts = str_replace($mm[0], '', $acts); $in .= '<button type=\"button\" role=\"menuitem\" data-hm-kind=\"' . $mm[1] . '\" data-hm-ref=\"' . $mm[2] . '\">' . $cam . 'Photos and video</button>'; }\n"
  "        if (preg_match('#<a class=\"xh-a xh-shr\" href=\"([^\"]*)\".*?</a>#s', $acts, $mm)) {\n"
  "            $acts = str_replace($mm[0], '', $acts); $in .= '<a role=\"menuitem\" href=\"' . $mm[1] . '\">' . $cam . 'Share a photo or video</a>'; }\n"
  "        $acts = str_replace('<!--eg-more-->', $in, $acts);\n"
  "    }\n"
  "    if ($acts !== '') $h .= '<div class=\"xh-acts\">' . $acts . '</div>';"),
])

# the pages: fold on the hero; what the page's own column shows isn't repeated in ⋯
patch('website/listing/view.php', [
 ("    'actions' => tv_engage_bar($pdo, 'listing', (string) $eid, ['title' => $biz['name'], 'likes' => false, 'tools' => ['map' => $hasPt ? '#lv-map' : '',\n"
  "        'dir' => $mapHref && empty($privateLocation) ? $mapHref : ['', !empty($privateLocation) ? 'Its location is kept private: call them.' : ($mailOnly ? 'Mailing address only: no street to drive to.' : 'No street address yet.')], 'out' => $eid,\n"
  "        'trip' => $hasPt ? ['lat' => (float) $biz['latitude'], 'lon' => (float) $biz['longitude']] : [], 'contact' => '/api/vcard.php?listing=' . $eid,\n"
  "        'suggest' => ['kind' => 'fix', 'entity_id' => $eid, 'entity_name' => $biz['name']]]])   // ⋯ (2026-11-19)   // views, comments, Share (§20)\n"
  "        . ($isOwner ? '' : '<span data-contact-save=\"business:' . $eid . '\" data-link-label=\"' . tv_e($biz['name']) . '\"></span>'),   // private (decisions/0052 §2)",
  "    // the corner: views, comments and ⋯ (Jason, 2026-11-19); the card's own column keeps Call, Directions, the map, Save\n"
  "    // contact and QR, so ⋯ doesn't repeat them; Save to Address Book (private, decisions/0052 §2) moves into ⋯ in words\n"
  "    'actions' => tv_engage_bar($pdo, 'listing', (string) $eid, ['title' => $biz['name'], 'likes' => false, 'fold' => true, 'qr' => false,\n"
  "        'first' => $isOwner ? '' : '<span data-contact-save=\"business:' . $eid . '\" data-link-label=\"' . tv_e($biz['name']) . '\"></span>',\n"
  "        'tools' => ['trip' => $hasPt ? ['lat' => (float) $biz['latitude'], 'lon' => (float) $biz['longitude']] : []]]),   // Suggest an edit: the page's own link under the card"),
])
patch('website/place/landmark.php', [   # the card's column has See on map and Directions; Suggest an edit is the page's own link
 ("['title' => $name, 'tools' => ['map' => '#lm-map', 'dir' => $dir, 'trip' => ['lat' => $lat, 'lon' => $lon],\n        'suggest' => ['target' => 'landmark:' . $id, 'target_name' => $name, 'kind' => 'fix']]])]);",
  "['title' => $name, 'fold' => true, 'tools' => ['trip' => ['lat' => $lat, 'lon' => $lon]]])]);"),
])
patch('website/place/recreation.php', [
 ("['title' => $name, 'tools' => ['map' => $hasPt ? '#rp-map' : '', 'dir' => $dir,\n        'trip' => $hasPt ? ['lat' => (float) $r['lat'], 'lon' => (float) $r['lon']] : [], 'suggest' => ['target' => 'rec:' . $r['ridb_id'], 'target_name' => $name, 'kind' => 'fix']]])]);",
  "['title' => $name, 'fold' => true, 'tools' => [\n        'trip' => $hasPt ? ['lat' => (float) $r['lat'], 'lon' => (float) $r['lon']] : []]])]);"),
])
patch('website/hub/index.php', [
 ("['title' => $p['name'], 'tools' => ['map'", "['title' => $p['name'], 'fold' => true, 'tools' => ['map'"),
])
print('built')
