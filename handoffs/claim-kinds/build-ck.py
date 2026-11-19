#!/usr/bin/env python3
"""claim-kinds (Jason, 2026-11-19: "Claim: shorten text and needs to know who might claim it... and how its form fitting its
claim process."; approved; plans for agencies and nonprofits: "Their own plan later"). Who might claim a listing comes from
its category group (decisions/0073): a business, a public place, a community group, or a public place on a nation's land
(0054: a nation is the top of its own tree; 0066). The button is short; the form's words, roles and proof fit the kind;
a public place's agency is its Nexus (0054), never typed in as its name; agencies and nonprofits stay on Free until their
own plan has a decision. Replayable: build-ck.py <root>."""
import sys, os
R = sys.argv[1]
def patch(rel, pairs):
    p = os.path.join(R, rel); s = open(p, encoding='utf-8').read()
    for a, b in pairs:
        assert s.count(a) == 1, (rel, a[:100], s.count(a)); s = s.replace(a, b)
    open(p, 'w', encoding='utf-8').write(s)

# ---------------- Claims: the kind, its words and roles, the agency, the domains ----------------
patch('app/lib/Claims.php', [
 ("    /** The domain of the listing's website, if it has one: \"example.com\". */",
  "    // ------------------------------------------------------------------ who might claim it (Jason, 2026-11-19; decisions/0070 note)\n\n"
  "    /** The category groups (decisions/0073) whose listings are claimed by an agency or an organization, not an owner. */\n"
  "    public const KIND_GROUPS = ['Government & Public Services' => 'public', 'Non-Profit & Community' => 'community', 'Religious & Faith-Based' => 'community'];\n"
  "    /** The roles offered on the claim form, by kind. */\n"
  "    public const KIND_ROLES = [\n"
  "        'business'  => ['Owner', 'Co-owner or partner', 'Manager', 'Employee', 'Other'],\n"
  "        'public'    => ['Site or park manager', 'Agency staff', 'Communications or public information', 'Other'],\n"
  "        'community' => ['Director, pastor or leader', 'Staff', 'Board member', 'Volunteer', 'Other'],\n"
  "        'nation'    => ['Nation staff', 'Tribal enterprise staff', 'Other'],\n"
  "    ];\n"
  "    /** The words, by kind: the short button, its tip (%s: the name), the claim page's title, and \"yours\" in its sentences. */\n"
  "    public const KIND_WORDS = [\n"
  "        'business'  => ['button' => 'Claim it', 'tip' => 'Own or manage %s? Claim it to add photos, hours and more.', 'title' => 'Claim your business', 'yours' => 'your business'],\n"
  "        'public'    => ['button' => 'Staff? Claim it', 'tip' => 'Work for the agency that runs %s? Claim it to keep its hours, photos and notices current.', 'title' => 'Claim this place for your agency', 'yours' => 'your agency'],\n"
  "        'community' => ['button' => 'Run it? Claim it', 'tip' => 'Run %s? Claim it to add photos, hours and more.', 'title' => 'Claim your organization', 'yours' => 'your organization'],\n"
  "        'nation'    => ['button' => 'Nation staff? Claim it', 'tip' => 'Work for the nation that runs %s? Ask to claim it: our team checks with the nation first.', 'title' => 'A place on a nation\\'s land', 'yours' => 'the nation'],\n"
  "    ];\n\n"
  "    /** business | public | community | nation (a public place on a nation's land: the nation's own tools decide, 0054, 0066). */\n"
  "    public static function kind(PDO $pdo, int $e): string\n    {\n"
  "        static $memo = [];\n"
  "        if (isset($memo[$e])) return $memo[$e];\n"
  "        $k = 'business';\n"
  "        try {\n"
  "            $st = $pdo->prepare('SELECT COALESCE(g.name, c.name) AS grp, e.latitude, e.longitude FROM entities e LEFT JOIN categories c ON c.id = e.category_id LEFT JOIN categories g ON g.id = c.parent_id WHERE e.id = ?');\n"
  "            $st->execute([$e]); $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];\n"
  "            $k = self::KIND_GROUPS[(string) ($r['grp'] ?? '')] ?? 'business';\n"
  "            if ($k === 'public' && isset($r['latitude'], $r['longitude']) && is_file(__DIR__ . '/Nations.php')) {\n"
  "                require_once __DIR__ . '/Nations.php';\n"
  "                if (Nations::at($pdo, (float) $r['latitude'], (float) $r['longitude']) !== null) $k = 'nation';\n"
  "            }\n"
  "        } catch (Throwable $x) { error_log('Claims::kind: ' . $x->getMessage()); }\n"
  "        return $memo[$e] = $k;\n    }\n\n"
  "    /** The agency that runs a listing: its Nexus (decisions/0054), with the tree above it; null when it has none yet. */\n"
  "    public static function agency(PDO $pdo, int $e): ?array\n    {\n"
  "        if (!is_file(__DIR__ . '/Nexus.php')) return null;\n"
  "        require_once __DIR__ . '/Nexus.php';\n"
  "        try {\n"
  "            if (!Nexus::ready($pdo)) return null;\n"
  "            $st = $pdo->prepare('SELECT nexus_id FROM entities WHERE id = ?'); $st->execute([$e]); $id = (int) $st->fetchColumn();\n"
  "            if ($id <= 0 || !($own = Nexus::get($pdo, $id))) return null;\n"
  "            $chain = [(string) $own['name']]; $site = (string) ($own['website'] ?? ''); $seen = [$id => 1];\n"
  "            for ($n = $own; !empty($n['parent_id']) && count($chain) < 6 && !isset($seen[(int) $n['parent_id']]); ) {\n"
  "                $seen[(int) $n['parent_id']] = 1; $n = Nexus::get($pdo, (int) $n['parent_id']); if (!$n) break;\n"
  "                array_unshift($chain, (string) $n['name']); if ($site === '') $site = (string) ($n['website'] ?? '');\n"
  "            }\n"
  "            return ['id' => $id, 'name' => (string) $own['name'], 'chain' => implode(' › ', $chain), 'domain' => self::siteDomain($site), 'members' => (int) ($own['members'] ?? 0)];\n"
  "        } catch (Throwable $x) { error_log('Claims::agency: ' . $x->getMessage()); return null; }\n"
  "    }\n\n"
  "    /** The domains whose email confirms a claim at once: the listing's own website, then (a public place) its agency's. */\n"
  "    public static function domains(PDO $pdo, int $e, ?string $website): array\n    {\n"
  "        $d = [self::siteDomain($website)];\n"
  "        if (in_array(self::kind($pdo, $e), ['public', 'nation'], true) && ($a = self::agency($pdo, $e))) $d[] = $a['domain'];\n"
  "        return array_values(array_unique(array_filter($d)));\n    }\n\n"
  "    /** The agency a claimant says runs it (none linked yet, or a different one): kept with the claim for the team to check and link (0054 §6: staff make every link). */\n"
  "    public static function agencyNote(PDO $pdo, int $uid, int $claimId, string $text): void\n    {\n"
  "        $text = trim(mb_substr(preg_replace('/\\s+/u', ' ', strip_tags($text)), 0, 120));\n"
  "        if ($text === '') return;\n"
  "        $c = self::ownClaim($pdo, $uid, $claimId);\n"
  "        self::setDetail($pdo, (int) $c['id'], 'agency_suggest', $text);\n"
  "        $l = self::listing($pdo, (int) $c['entity_id']);\n"
  "        self::tellAdmins($pdo, 'Agency named on a claim: ' . ($l['name'] ?? ''), self::who($pdo, $uid) . ' says ' . ($l['name'] ?? 'this listing') . ' is run by \"' . $text . '\". Check it and link its Nexus (Admin → Claims; decisions/0054).');\n"
  "    }\n\n"
  "    /** The domain of the listing's website, if it has one: \"example.com\". */"),
 ("        $dom = self::siteDomain($l['website'] ?? '');\n        $email = strtolower(trim($email));\n"
  "        if ($dom === '') throw new InvalidArgumentException('This listing has no website on file, so this way isn\\'t available.');\n"
  "        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@' . $dom)) throw new InvalidArgumentException(\"Use an address ending in @{$dom}.\");",
  "        $doms = self::domains($pdo, (int) $c['entity_id'], $l['website'] ?? '');   // a public place: its agency's domain too (2026-11-19)\n"
  "        $email = strtolower(trim($email));\n"
  "        if (!$doms) throw new InvalidArgumentException('This listing has no website on file, so this way isn\\'t available.');\n"
  "        $okDom = array_filter($doms, fn ($d) => str_ends_with($email, '@' . $d));\n"
  "        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$okDom) throw new InvalidArgumentException('Use an address ending in @' . implode(' or @', $doms) . '.');"),
])

# ---------------- the API: the kind and domains in a claim; the agency named ----------------
patch('website/listing/api/claim.php', [
 ("'domain' => Claims::siteDomain($l['website'] ?? ''),",
  "'domain' => implode(' or @', Claims::domains($pdo, (int) $c['entity_id'], $l['website'] ?? '')), 'kind' => Claims::kind($pdo, (int) $c['entity_id']),"),
 ("            Response::success(['held' => false, 'claim' => $out($c)]);",
  "            if (trim((string) ($body['agency'] ?? '')) !== '') Claims::agencyNote($pdo, $uid, (int) $c['id'], (string) $body['agency']);   // who runs it, for the team to check (0054)\n"
  "            Response::success(['held' => false, 'claim' => $out($c)]);"),
 ("            $c = Claims::hold($pdo, $user, $e, $body);\n",
  "            $c = Claims::hold($pdo, $user, $e, $body);\n"
  "            if ($c && trim((string) ($body['agency'] ?? '')) !== '') Claims::agencyNote($pdo, $uid, (int) $c['id'], (string) $body['agency']);\n"),
])

# ---------------- the claim page: words, roles, who runs it, the nation's way ----------------
patch('website/claim.php', [
 ("if ($notBiz !== '' && !$adding) $contribute = true;\n",
  "if ($notBiz !== '' && !$adding) $contribute = true;\n"
  "// who might claim it (Jason, 2026-11-19; decisions/0070 note): a business, a public place, a community group, a place on a nation's land\n"
  "$kind = $biz ? Claims::kind($pdo, (int) $biz['id']) : 'business';\n"
  "$kw = Claims::KIND_WORDS[$kind];\n"
  "$agency = $biz && in_array($kind, ['public', 'nation'], true) ? Claims::agency($pdo, (int) $biz['id']) : null;\n"),
 ("elseif ($holding || $biz['status'] !== 'unclaimed') $view = 'taken';",
  "elseif ($holding || $biz['status'] !== 'unclaimed') $view = 'taken';\n"
  "if ($view === 'claim' && $kind === 'nation') $view = 'nation';   // the nation's own tools decide (0054, 0066): a request our team checks with the nation"),
 ("'mine' => 'Your claim', 'dispute' => 'Question this listing\\'s owner', 'taken' => 'Claim your business'][$view] ?? 'Claim your business';",
  "'mine' => 'Your claim', 'dispute' => 'Question this listing\\'s owner', 'taken' => $kw['title'], 'nation' => $kw['title']][$view] ?? $kw['title'];"),
 ("$cOut = function (?array $c) use ($biz, $pdo): array {", "$cOut = function (?array $c) use ($biz, $pdo, $kind): array {"),
 ("            'domain' => Claims::siteDomain($biz['website'] ?? ''),",
  "            'domain' => implode(' or @', Claims::domains($pdo, (int) $biz['id'], $biz['website'] ?? '')), 'kind' => $kind,"),
 ("    <li><b>Choose a plan.</b> Free, or a paid plan (free trial for now).</li>\n  </ol>",
  "    <li><b><?= $kind === 'business' ? 'Choose a plan.' : 'Your plan.' ?></b> <?= $kind === 'business' ? 'Free, or a paid plan (free trial for now).' : 'Free. Plans for agencies and nonprofits are coming; until then, everything a claimed listing gets on Free is yours.' ?></li>\n  </ol>\n"
  "  <?php if ($kind === 'public'): // who runs it: its Nexus (decisions/0054), never typed in as its name ?>\n"
  "  <div class=\"tv-bleed cl-card\"><h2>Who runs it</h2>\n"
  "    <?php if ($agency): ?><p class=\"cl-s mt-1\">Run by <b><?= $e($agency['chain']) ?></b>. Your claim covers this place. Not the right agency? Say which one below; our team checks it.</p>\n"
  "    <?php else: ?><p class=\"cl-s mt-1\">We don't know yet which agency runs it. Say which one below (optional): our team checks it and links it.</p><?php endif; ?>\n"
  "    <p class=\"cl-chk mt-2\" data-tv-status=\"info\" data-tv-label=\"Coming:\">Claiming for the whole agency, every place it runs, in one go. For now, claim each place you look after.</p></div>\n"
  "  <?php endif; ?>"),
 ("    <label class=\"cl-lb\" for=\"c-role\">Your role with the business</label>\n"
  "    <select class=\"cl-in\" id=\"c-role\" name=\"role\"><option>Owner</option><option>Co-owner or partner</option><option>Manager</option><option>Employee</option><option>Other</option></select>",
  "    <label class=\"cl-lb\" for=\"c-role\">Your role with <?= $e($kw['yours']) ?></label>\n"
  "    <select class=\"cl-in\" id=\"c-role\" name=\"role\"><?php foreach (Claims::KIND_ROLES[$kind] as $r): ?><option><?= $e($r) ?></option><?php endforeach; ?></select>\n"
  "    <?php if ($kind === 'public'): ?><label class=\"cl-lb\" for=\"c-agency\"><?= $agency ? 'A different agency? (optional)' : 'Which agency runs it? (optional)' ?></label>\n"
  "    <input class=\"cl-in\" id=\"c-agency\" name=\"agency\" maxlength=\"120\" data-spell placeholder=\"For example, Arizona State Parks &amp; Trails\"><?php endif; ?>"),
 ("<?php elseif ($view === 'taken' || $view === 'held'): ?>",
  "<?php elseif ($view === 'taken' || $view === 'held' || $view === 'nation'): ?>"),
 ("    <p class=\"cl-s mt-2\"><?= $view === 'held' ? 'Your request is saved.' : 'Another claim for this listing is in progress.' ?>\n"
  "      Only one claim goes ahead at a time, so your request is on hold, not turned down. An admin has been told and will be in touch. Leave a way to reach you, and anything that shows the business is yours.</p>",
  "    <?php if ($view === 'nation'): ?>\n"
  "    <p class=\"cl-s mt-2\">This place is on a nation's land, and the nation decides who speaks for it. Until the nation's own tools are ready, your request comes to our team, who check with the nation before anything changes. Leave a way to reach you and your role.</p>\n"
  "    <?php else: ?>\n"
  "    <p class=\"cl-s mt-2\"><?= $view === 'held' ? 'Your request is saved.' : 'Another claim for this listing is in progress.' ?>\n"
  "      Only one claim goes ahead at a time, so your request is on hold, not turned down. An admin has been told and will be in touch. Leave a way to reach you, and anything that shows <?= $e($kw['yours']) ?> runs it.</p>\n"
  "    <?php endif; ?>"),
 ("      <label class=\"cl-lb\" for=\"h-role\">Your role with the business</label>",
  "      <label class=\"cl-lb\" for=\"h-role\">Your role with <?= $e($kw['yours']) ?></label>"),
])
# the hold form's pill says what it is for the nation
patch('website/claim.php', [
 ("    <span class=\"cl-pill h\">Claim Request On Hold</span>",
  "    <span class=\"cl-pill h\"><?= $view === 'nation' ? 'A request for our team' : 'Claim Request On Hold' ?></span>"),
])

# ---------------- claim.js: agency named, proof by the agency's email first, no plan step for agencies and nonprofits ----------------
patch('website/js/claim.js', [
 ("post({ action: 'confirm', entity_id: entity, code: val(start, 'code').trim(), role: val(start, 'role'), phone: val(start, 'phone') })",
  "post({ action: 'confirm', entity_id: entity, code: val(start, 'code').trim(), role: val(start, 'role'), phone: val(start, 'phone'), agency: val(start, 'agency') })"),
 ("      h += '<h2 class=\"mt-3\">Step 2: get verified</h2>';\n",
  "      h += '<h2 class=\"mt-3\">Step 2: get verified</h2>';\n"
  "      var org = claim.kind && claim.kind !== 'business';   // an agency or an organization: its own email confirms it at once, so that comes first (2026-11-19)\n"
  "      if (org && claim.domain) h += '<form class=\"mt-2\" id=\"cl-dom\"><p class=\"cl-s\"><b>Fastest: an email at @' + esc(claim.domain) + '.</b> We send a code there; entering it confirms you at once.</p>'\n"
  "        + '<div class=\"flex gap-2 mt-1\"><input class=\"cl-in\" name=\"email\" type=\"email\" placeholder=\"you@' + esc(String(claim.domain).split(' or @')[0]) + '\"><button class=\"cl-b\" type=\"submit\">Send code</button></div>'\n"
  "        + '<div data-dc hidden class=\"flex gap-2 mt-2\"><input class=\"cl-in\" name=\"code\" inputmode=\"numeric\" maxlength=\"6\" placeholder=\"6-digit code\"><button class=\"cl-b go\" type=\"button\" data-domok>Confirm</button></div>'\n"
  "        + '<p class=\"cl-msg\" role=\"status\"></p></form><p class=\"cl-s mt-2\">Or:</p>';\n"),
 ("      if (claim.domain) {\n        h += '<form class=\"mt-2\" id=\"cl-dom\">",
  "      if (claim.domain && !org) {\n        h += '<form class=\"mt-2\" id=\"cl-dom\">"),
 ("    h += '<form class=\"tv-bleed cl-card\" id=\"cl-plan\"><h2>' + (claim.plan ? 'Your plan' : 'Step 3: choose a plan') + '</h2>'",
  "    if (claim.kind && claim.kind !== 'business') {   // agencies and nonprofits: their own plan later (Jason, 2026-11-19); Free until then\n"
  "      h += '<form class=\"tv-bleed cl-card\" id=\"cl-plan\"><h2>Step 3: your plan</h2><p class=\"cl-s mt-1\">Free. Plans for agencies and nonprofits are coming; until then, everything a claimed listing gets on Free is yours.</p>'\n"
  "        + '<div class=\"mt-3 flex gap-2 flex-wrap\"><a class=\"cl-b go\" href=\"/listing/businessportal.php\">Your dashboard</a></div><p class=\"cl-msg\" role=\"status\"></p></form>';\n"
  "    } else {\n"
  "    h += '<form class=\"tv-bleed cl-card\" id=\"cl-plan\"><h2>' + (claim.plan ? 'Your plan' : 'Step 3: choose a plan') + '</h2>'"),
 ("      + '<a class=\"cl-b\" href=\"/listing/businessportal.php\">Business dashboard</a></div><p class=\"cl-msg\" role=\"status\"></p></form>';\n",
  "      + '<a class=\"cl-b\" href=\"/listing/businessportal.php\">Business dashboard</a></div><p class=\"cl-msg\" role=\"status\"></p></form>';\n    }\n"),
 ("      post({ action: 'hold', entity_id: entity, role: val(hold, 'role'), phone: val(hold, 'phone'), note: val(hold, 'note'),",
  "      post({ action: 'hold', entity_id: entity, role: val(hold, 'role'), phone: val(hold, 'phone'), note: val(hold, 'note'), agency: val(hold, 'agency'),"),
])

# ---------------- the listing: the short button, by kind ----------------
patch('website/listing/view.php', [
 ("'<a class=\"xh-a go\" href=\"/claim.php?listing=' . $eid . '\" title=\"Are you the owner? Claim it to add photos, hours and more.\">' . tv_icon('store') . 'Claim this business</a><small>Add your own photos, hours and more</small>'",
  "((function () use ($pdo, $eid, $biz) { require_once __DIR__ . '/../../app/lib/Claims.php'; $kw = Claims::KIND_WORDS[Claims::kind($pdo, $eid)];   // short, and who it's for (Jason, 2026-11-19)\n"
  "            return '<a class=\"xh-a go\" href=\"/claim.php?listing=' . $eid . '\" title=\"' . tv_e(sprintf($kw['tip'], $biz['name'])) . '\" aria-label=\"' . tv_e(sprintf($kw['tip'], $biz['name'])) . '\">' . tv_icon('store') . tv_e($kw['button']) . '</a>'; })())"),
])
print('built')
