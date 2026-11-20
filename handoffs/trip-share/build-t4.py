#!/usr/bin/env python3
"""decisions/0174 release 4: sharing, Personal Guides and counts. Reads the live mirror, writes out/. Replayable."""
import os, shutil
M = '/home/user/traverse-3way-sync'; S = os.path.dirname(os.path.abspath(__file__)); O = os.path.join(S, 'out'); SRC = os.path.join(S, 'src')
shutil.rmtree(O, ignore_errors=True)
def live(rel): return open(M + '/' + rel.replace('website/', 'sync-storage/', 1).replace('app/', 'sync-app/', 1), encoding='utf-8').read()
def put(rel, s):
    p = os.path.join(O, rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(s)
def rep(s, a, b, n=1):
    assert s.count(a) == n, (a[:90], s.count(a)); return s.replace(a, b)
src = lambda f: open(os.path.join(SRC, f), encoding='utf-8').read()

put('app/lib/Trips.php', src('Trips.php'))
put('website/api/trips.php', src('api-trips.php'))
put('website/user/trips/trip.php', src('trip.php'))
put('website/trip/index.php', src('trip-index.php'))

# a place's count on its page (Jason, 2026-11-20: "Also on the place's page"): beside its views, never who
f = 'app/ui/engage-ui.php'; s = live(f)
s = rep(s, "        . $ic('<path d=\"M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z\"/><circle cx=\"12\" cy=\"12\" r=\"3\"/>') . '<span>' . number_format($views) . '</span></span>';",
           "        . $ic('<path d=\"M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z\"/><circle cx=\"12\" cy=\"12\" r=\"3\"/>') . '<span>' . number_format($views) . '</span></span>';\n"
           "    if (in_array($kind, ['lm', 'rec', 'place'], true) && is_file(__DIR__ . '/../lib/Trips.php')) {   // added to trips this month (decisions/0174 §1; Jason, 2026-11-20)\n"
           "        require_once __DIR__ . '/../lib/Trips.php';\n"
           "        $tn = Trips::addedThisMonth($pdo, $kind, $ref);\n"
           "        if ($tn > 0 || Trips::SHOW_ZERO_TRIPS) { $tw = 'Added to ' . number_format($tn) . ' trip' . ($tn === 1 ? '' : 's') . ' this month';\n"
           "            $h .= '<span class=\"eg-n\" data-eg-trips title=\"' . $tw . '\" aria-label=\"' . $tw . '\">' . $ic('<path d=\"M3 17l6-6 4 4 8-8\"/><path d=\"M14 7h7v7\"/>') . '<span>' . number_format($tn) . '</span></span>'; }\n"
           "    }")
put(f, s)

# counts (0174 §1, 0058 §13): a business's in listing_actions as 0094's `save`; a place's in PlatformCounts
f = 'app/lib/ListingActions.php'; s = live(f)
s = rep(s, "'save' => 'Contacts saved'];", "'save' => 'Contacts saved', 'trip' => 'Added to trips'];   // trip: decisions/0174 §1, never who")
put(f, s)
f = 'app/lib/PlatformCounts.php'; s = live(f)
s = rep(s, "'land', 'source', 'land_from'];", "'land', 'source', 'land_from', 'trip'];   // trip: a place added to a trip (decisions/0174 §1), k = kind:ref, never who")
put(f, s)
f = 'website/admin/insights.php'; s = live(f)
s = rep(s, "$acts = $ready ? PlatformCounts::top($pdo, 'act', $days, 12) : [];",
           "$acts = $ready ? PlatformCounts::top($pdo, 'act', $days, 12) : [];\n"
           "// places added to trips (decisions/0174 §1: counts only, never who); businesses' are in their listing actions\n"
           "$tripAdds = [];\n"
           "if ($ready && is_file(__DIR__ . '/../../app/lib/Trips.php')) { require_once __DIR__ . '/../../app/lib/Trips.php';\n"
           "    foreach (PlatformCounts::top($pdo, 'trip', $days, 12) as $k => $r) { [$tk, $tr] = array_pad(explode(':', $k, 2), 2, ''); $x = Trips::resolve($pdo, $tk, $tr); $tripAdds[$k] = $r + ['label' => $x['name'] !== '' ? $x['name'] : $k, 'url' => $x['ok'] ? (string) $x['url'] : '']; } }")
s = rep(s, "  <?php foreach ([['Most viewed listings', $topListings],",
           "  <section class=\"in-card\"><h2>Places added to trips</h2><p class=\"in-what\">Places, landmarks and outdoor places people added to a trip; never who. Businesses see theirs as \"Added to trips\" in their own insights.</p>\n"
           "    <?= $bar($tripAdds, fn ($k, $r) => $r['url'] !== '' ? '<a href=\"' . tv_e($r['url']) . '\">' . tv_e($r['label']) . '</a>' : tv_e($r['label'])) ?></section>\n\n"
           "  <?php foreach ([['Most viewed listings', $topListings],")
put(f, s)


# ---- Personal Guides (0174 §1; 0058 §1) ----
put('app/migrations/2026-11-21_personal-guides.sql', src('personal-guides.sql'))
put('website/guide/personal.php', src('guide-personal.php'))
f = 'app/lib/staging-tables.php'; s = live(f)
s = rep(s, "'trips', 'trip_stops' /* a member's trips (decisions/0174) */,", "'trips', 'trip_stops' /* a member's trips (decisions/0174) */, 'personal_guides' /* a trip published as a Personal Guide (0174 §1) */,")
put(f, s)
# a new content kind (CLAUDE.md "Content pages", 0058 §20): the engage bar, comments, views
f = 'app/lib/Engage.php'; s = live(f)
s = rep(s, "public const KINDS = ['listing', 'rec', 'exp', 'place', 'lm', 'guide', 'how', 'sj', 'page'];", "public const KINDS = ['listing', 'rec', 'exp', 'place', 'lm', 'guide', 'how', 'sj', 'page', 'pg'];   // pg: a Personal Guide (decisions/0174 §1)")
s = rep(s, "'sj' => 'suggested:', 'mkt' => 'market:',", "'sj' => 'suggested:', 'pg' => 'personal_guide:', 'mkt' => 'market:',")
s = rep(s, "            'listing', 'exp', 'lm', 'guide', 'how', 'sj', 'page', 'photo' => ctype_digit($ref),", "            'listing', 'exp', 'lm', 'guide', 'how', 'sj', 'page', 'pg', 'photo' => ctype_digit($ref),")
s = rep(s, "                case 'sj': require_once __DIR__ . '/JourneyPlan.php';", "                case 'pg': require_once __DIR__ . '/Trips.php'; return !Trips::guidesReady($pdo) || Trips::guide($pdo, (int) $ref) !== null;   // published, its trip not deleted\n                case 'sj': require_once __DIR__ . '/JourneyPlan.php';")
s = rep(s, "'sj' => '/journeys/suggested.php?id=', 'mp' => '/user/post.php?id='][$kind]", "'sj' => '/journeys/suggested.php?id=', 'pg' => '/guide/personal.php?id=', 'mp' => '/user/post.php?id='][$kind]")
put(f, s)
f = 'app/lib/Links.php'; s = live(f)
s = rep(s, "        if ($type === 'place' && preg_match('/^how:(\\d{1,10})$/', $id, $hm)) {",
           "        if ($type === 'place' && preg_match('/^pg:(\\d{1,10})$/', $id, $pm)) {   // a Personal Guide (guide/personal.php, decisions/0174 §1)\n"
           "            require_once __DIR__ . '/Trips.php';\n"
           "            $g = Trips::guide($pdo, (int) $pm[1]);\n"
           "            if (!$g) throw new InvalidArgumentException('That guide isn\\'t available.');\n"
           "            return [(string) $g['title'], '/guide/personal.php?id=' . (int) $pm[1]];\n"
           "        }\n"
           "        if ($type === 'place' && preg_match('/^how:(\\d{1,10})$/', $id, $hm)) {")
put(f, s)
# reportable like a journey (Reports: file, show, decide, author)
f = 'app/lib/Reports.php'; s = live(f)
s = rep(s, "        } elseif ($kind === 'content_comment' || $kind === 'experience') {   // decisions/0058 §20, 0059",
           "        } elseif ($kind === 'personal_guide') {   // decisions/0174 §1: reportable like a journey\n"
           "            $st = $pdo->prepare(\"SELECT user_id FROM personal_guides WHERE id = ? AND status = 'published'\");\n"
           "            $st->execute([$target]);\n"
           "            $who = $st->fetchColumn();\n"
           "            if ($who === false) throw new InvalidArgumentException('That isn\\'t available.');\n"
           "            if ((int) $who === $uid) throw new InvalidArgumentException('That\\'s your own: change it or withdraw it instead.');\n"
           "        } elseif ($kind === 'content_comment' || $kind === 'experience') {   // decisions/0058 §20, 0059")
s = rep(s, "            } elseif ($kind === 'content_comment' || $kind === 'experience') {\n                $st = $pdo->prepare($kind === 'experience' ?",
           "            } elseif ($kind === 'personal_guide') {\n"
           "                $st = $pdo->prepare('SELECT id, user_id AS uid, title AS t, intro AS body, status FROM personal_guides WHERE id = ?');\n"
           "                $st->execute([$id]);\n"
           "                $j = $st->fetch(PDO::FETCH_ASSOC);\n"
           "                if (!$j) { $out[] = $gone; continue; }\n"
           "                $u = People::user($pdo, (int) $j['uid']);\n"
           "                $item += ['text' => $j['t'] . ': ' . mb_strimwidth(strip_tags((string) $j['body']), 0, 600, '…'), 'author' => $u ? People::name($u) : 'Someone who left',\n"
           "                          'author_id' => (int) $j['uid'], 'where' => 'Personal Guide', 'group_id' => 0, 'hidden' => $j['status'] === 'hidden', 'url' => '/guide/personal.php?id=' . (int) $j['id']];\n"
           "            } elseif ($kind === 'content_comment' || $kind === 'experience') {\n                $st = $pdo->prepare($kind === 'experience' ?")
s = rep(s, "'journey' => 'Journey',", "'journey' => 'Journey', 'personal_guide' => 'Personal Guide',")
s = rep(s, "['group_post', 'group', 'thread', 'biz_message', 'journey', 'journey_comment',", "['group_post', 'group', 'thread', 'biz_message', 'journey', 'personal_guide', 'journey_comment',")
s = rep(s, "        if ($kind === 'journey' && $what !== 'dismiss') {   // hidden stays visible to its author (decisions/0056 §6)",
           "        if ($kind === 'personal_guide' && $what !== 'dismiss') { require_once __DIR__ . '/Trips.php'; Trips::hideGuide($pdo, $id, $what === 'remove', 'Hidden after reports'); }   // as a journey (0174 §1)\n"
           "        if ($kind === 'journey' && $what !== 'dismiss') {   // hidden stays visible to its author (decisions/0056 §6)")
s = rep(s, "                'journey' => 'SELECT author_id FROM journeys WHERE id = ?', 'journey_comment'", "                'journey' => 'SELECT author_id FROM journeys WHERE id = ?', 'personal_guide' => 'SELECT user_id FROM personal_guides WHERE id = ?', 'journey_comment'")
put(f, s)
f = 'website/api/engage.php'; s = live(f)
s = rep(s, "in_array($what, ['experience', 'market_post', 'market_note', 'market_item'], true)", "in_array($what, ['experience', 'market_post', 'market_note', 'market_item', 'personal_guide'], true)")
put(f, s)
# staff hide or restore a guide in Admin → Journeys (0058 §2 Journey Management; no page outside the manifests)
f = 'website/admin/journeys.php'; s = live(f)
s = rep(s, "        $id = (int) ($_POST['id'] ?? 0);\n        $j = Journeys::get($pdo, $id);",
           "        $id = (int) ($_POST['id'] ?? 0);\n"
           "        if (in_array((string) ($_POST['do'] ?? ''), ['pg_hide', 'pg_restore'], true)) {   // Personal Guides (decisions/0174 §1), hidden like journeys\n"
           "            require_once __DIR__ . '/../../app/lib/Trips.php';\n"
           "            if (!$can['journeys']) throw new InvalidArgumentException('Hiding guides needs an Editor.');\n"
           "            $why = mb_substr(trim(strip_tags((string) ($_POST['why'] ?? ''))), 0, 200);\n"
           "            if ($_POST['do'] === 'pg_hide' && $why === '') throw new InvalidArgumentException('Say why it\\'s hidden. Its author sees the reason.');\n"
           "            Trips::hideGuide($pdo, $id, $_POST['do'] === 'pg_hide', $why);\n"
           "            $_SESSION['jm_flash'] = [true, $_POST['do'] === 'pg_hide' ? 'Guide hidden. Its author sees your reason.' : 'Guide restored.'];\n"
           "            header('Location: ' . strtok((string) ($_SERVER['REQUEST_URI'] ?? '/admin/journeys.php'), '#') . '#personal-guides', true, 303); exit;\n"
           "        }\n"
           "        $j = Journeys::get($pdo, $id);")
s = rep(s, "  <p class=\"jm-s\" style=\"margin-top:.8rem\">Roles:",
           "  <?php require_once __DIR__ . '/../../app/lib/Trips.php'; $pgs = Trips::guidesForStaff($pdo); if ($pgs): ?>\n"
           "  <section id=\"personal-guides\" style=\"margin-top:1rem\"><h2 class=\"hb-h\">Personal Guides</h2><p class=\"jm-s\">Members' trips published as guides (decisions/0174 §1). Hide one as you would a journey: its author sees your reason.</p>\n"
           "  <?php foreach ($pgs as $g): ?><div class=\"jm-s\" style=\"display:flex;flex-wrap:wrap;gap:.4rem .8rem;align-items:center;border-top:1px solid #E5D9BD;padding:.4rem 0\">\n"
           "    <b style=\"flex:1 1 14rem\"><?= $g['status'] === 'published' ? '<a class=\"underline\" href=\"/guide/personal.php?id=' . (int) $g['id'] . '\">' . tv_e($g['title']) . '</a>' : tv_e($g['title']) ?></b>\n"
           "    <span><?= tv_e(Trips::GUIDE_STATUSES[$g['status']] ?? $g['status']) ?><?= $g['hidden_reason'] !== '' ? ': ' . tv_e($g['hidden_reason']) : '' ?></span>\n"
           "    <?php if ($can['journeys'] && $g['status'] === 'published'): ?><form method=\"post\" style=\"display:flex;gap:.3rem\"><input type=\"hidden\" name=\"csrf\" value=\"<?= tv_e($csrf) ?>\"><input type=\"hidden\" name=\"id\" value=\"<?= (int) $g['id'] ?>\"><input type=\"hidden\" name=\"do\" value=\"pg_hide\"><input name=\"why\" data-spell placeholder=\"Why (its author sees this)\" required style=\"font:inherit;font-size:.8rem;border:1px solid #CBB98D;border-radius:.4rem;padding:.2rem .4rem\"><button class=\"underline\" data-confirm=\"Hide this guide from the site?\" data-yes=\"Hide it\" data-danger>Hide</button></form>\n"
           "    <?php elseif ($can['journeys'] && $g['status'] === 'hidden'): ?><form method=\"post\"><input type=\"hidden\" name=\"csrf\" value=\"<?= tv_e($csrf) ?>\"><input type=\"hidden\" name=\"id\" value=\"<?= (int) $g['id'] ?>\"><input type=\"hidden\" name=\"do\" value=\"pg_restore\"><button class=\"underline\">Restore</button></form><?php endif; ?>\n"
           "  </div><?php endforeach; ?></section><?php endif; ?>\n"
           "  <p class=\"jm-s\" style=\"margin-top:.8rem\">Roles:")
put(f, s)
# account deletion: a guide follows the journeys' rule (0174 §6; journeys are hidden and emptied)
f = 'app/lib/AccountDeletion.php'; s = live(f)
s = rep(s, "            $do('DELETE s FROM trip_stops s JOIN trips t ON t.id = s.trip_id WHERE t.user_id = ?', [$uid]);   // their trips (decisions/0174 §6)",
           "            $do(\"UPDATE personal_guides SET status = 'hidden', hidden_reason = 'The author deleted their account', title = '', intro = '', updated_at = NOW() WHERE user_id = ?\", [$uid]);   // as journeys (0174 §6)\n"
           "            $do('DELETE s FROM trip_stops s JOIN trips t ON t.id = s.trip_id WHERE t.user_id = ?', [$uid]);   // their trips (decisions/0174 §6)")
put(f, s)

# FAQ
f = 'app/ui/faq.md'; s = live(f)
s = rep(s, "Only you. A trip is private: nobody else sees it, and nobody sees who added a place to a trip. Sharing a trip by link, and publishing one as a Personal Guide, are coming, and only when you choose.",
           "Only you, unless you share it. A trip is private: nobody sees who added a place to a trip. On a trip's page, **Share** makes a link: anyone with it can view the trip (its days, times and map), not change it, and it shows nothing about you. **Turn off** stops the link at once; sharing again makes a new link. Stops that can't be shown publicly (a place whose location is kept private, one marked closed) are left out of a shared trip. You can also publish a trip as a Personal Guide (see below), only when you choose.")
a = "### Why can't I add some places to a trip?"
s = rep(s, a, """### Can a business see who added it to a trip?
No. A business sees only how many times it was added to a trip ("Added to trips" in its insights, with its calls and directions), never who. A place, a landmark or an outdoor place shows it on its page, beside its views ("Added to 12 trips this month"), never who. Nobody on our team is counted.

""" + a)
a = "### Can a business see who added it to a trip?"
s = rep(s, a, """### What's a Personal Guide?
A trip you publish for everyone: on a trip's page, **Publish as a guide**, give it a title and a few sentences about it, and **Publish**. It's credited to you, like a journey, and it changes as your trip does. You need to be a contributor first (**Your journeys → Become a contributor**: 18 or older, with a public profile). Your words get the same automated check as a journey's: if something is held, it says why and you can change it. Readers can like it, comment and report it, and our team can hide it, telling you why. **Withdraw** takes it down; **Publish again** puts it back. Places whose location is kept private never show in it. If you delete your account, your guides are taken down and their words removed.

""" + a)
put(f, s)
print('built', sum(len(fs) for _, _, fs in os.walk(O)), 'files')
