<?php
// app/admin/team-console.php — System Configuration → Team's head (decisions/0179's Piece 2, P2-3; Jason, 2026-11-25: "The team
// decision tree is what I need to approve for agents to begin assuming full capacity"; 0179's note on how the Team's capacity
// grows): the decision tree in plain words, drawn from the runner's own constants (TeamRunner: STEPS, STEP_AT, CEILINGS,
// TIER3_AREAS, TIER3, TIER1), never written beside them; Approve the decision tree (the Founder's, tied to the rules'
// fingerprint, so a wording fix keeps it and a rule change clears it); "Agents work up to", Planning until the Founder raises
// it, one stage at a time, each raise shown with its evidence lines and their readings; Confirmation waits for Piece 3.
declare(strict_types=1);

function tv_admin_mod_team(PDO $pdo): string
{
    if (!is_file(__DIR__ . '/../lib/TeamRunner.php')) return '';
    require_once __DIR__ . '/../lib/TeamRunner.php'; require_once __DIR__ . '/Access.php';
    $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
    try { TeamRunner::ensure($pdo); } catch (Throwable $x) { return ''; }
    $tree = TeamRunner::treeApproval($pdo); $ceil = TeamRunner::ceiling($pdo); $founder = Access::isFounder();
    $noF = 'Only the Founder, signed in with the Founder\'s own staff login (decisions/0179)';
    $when = fn ($at, $tz = '') => $at ? '<time datetime="' . $e(gmdate('c', (int) strtotime($at . ' UTC'))) . '" data-tv-time' . ($tz !== '' ? ' data-tz="' . $e($tz) . '"' : '') . '>' . $e(gmdate('M j, H:i', (int) strtotime($at . ' UTC'))) . ' UTC</time>' : '';
    $roles = []; foreach (TeamRoster::all($pdo) as $r) $roles[$r['slug']] = $r;
    $mode = ['act' => 'acts', 'propose' => 'proposes, a person accepts', 'off' => 'off'];
    // the decision tree, from the constants
    $h = '<section class="ak-card tv-bleed tm-head" id="tm-tree"><h3 class="tm-h">The decision tree <small>what the Team may do alone, and what always comes to the Founder</small></h3>';
    $h .= '<ol class="tm-tiers"><li><b>Tier 1:</b> only the low-risk kinds (' . $e(trim(str_replace(['/\b(', ')\b/i', '|', 's?'], ['', '', ', ', ''], TeamRunner::TIER1))) . '). The Team may do it. With rule 2 on, it goes live by itself once staging\'s checks and the before-and-after check pass; until then, in the batch the Founder approves.</li>'
        . '<li><b>Tier 2:</b> everything that isn\'t clearly Tier 1 or Tier 3. The Team may prepare it. With rule 2 on, it too goes live by itself once those checks pass (Jason, 2026-11-25); until then, in the batch.</li>'
        . '<li><b>Tier 3:</b> what touches a protected area. It always comes to the Founder as a Decision Brief:<ul class="tm-areas">';
    foreach (TeamRunner::TIER3_AREAS as $k => $a) {
        $parts = array_filter(['libraries ' . implode(', ', $a['libs']), $a['tables'] ? 'tables ' . implode(', ', $a['tables']) : '', $a['paths'] ? 'pages ' . implode(', ', $a['paths']) : '', $a['jobs'] ? 'jobs ' . implode(', ', $a['jobs']) : '', $a['services'] ? 'services ' . implode(', ', $a['services']) : '', !empty($a['sql']) ? 'a migration that ' . implode(', ', array_map('strtolower', $a['sql'])) . 's (one that only adds a table, column or index doesn\'t)' : ''], fn ($x) => $x !== '' && $x !== 'libraries ');
        $h .= '<li><b>' . $e(GaugeTasks::BOUNDARIES[$k] ?? $k) . ':</b> ' . $e(implode('; ', $parts)) . '</li>';
    }
    $h .= '</ul>Words alone (' . $e(implode(', ', array_keys(TeamRunner::TIER3))) . ' word lists) never make a finding Tier 3: they flag it Tier 2, "Words suggest a Tier 3 boundary: a person checks".</li></ol>';
    $h .= '<div class="tm-scroll"><table class="tm-t"><thead><tr><th>Role and step</th>'; foreach (TeamRunner::CEILINGS as $c) $h .= '<th>Up to ' . $e($c[0]) . '</th>'; $h .= '</tr></thead><tbody>';
    foreach (TeamRunner::STEPS as $k => [$slug, $what]) {
        $h .= '<tr><td><b>' . $e($roles[$slug]['name'] ?? $slug) . '</b><br><small>' . $e($what) . '</small></td>';
        foreach (array_keys(TeamRunner::CEILINGS) as $c) $h .= '<td' . ($c === $ceil ? ' class="tm-now"' : '') . '>' . $e($mode[TeamRunner::STEP_AT[$k][$c]] . ($c === 'confirmation' && TeamRunner::STEP_AT[$k][$c] === 'act' ? '; Tier 1 and 2 publish alone' : '')) . '</td>';
        $h .= '</tr>';
    }
    $h .= '</tbody></table></div><p class="tm-note"><b>Spending:</b> each role may spend $' . number_format(0, 2) . ' a month: no step calls paid AI or an outside service (Q4 B), so each step costs $0. <b>Always the Founder\'s:</b> every Tier 3 answer, approving the daily batch, approving this tree, and raising how far agents work. Agents never work a ticket a person has, never take one themselves, never answer a Tier 3, never write over a person\'s words, and never call an outside service or paid AI.</p>';
    $fp = substr(TeamRunner::fingerprint($pdo), 0, 12);
    $h .= $tree ? '<p data-tv-status="ok" data-tv-label="Approved:">By ' . $e($tree['who']) . ', ' . $when($tree['at'], (string) $tree['tz']) . ' · rules fingerprint ' . $e($fp) . '. A wording fix keeps your approval; a change to any rule clears it and agents go back to Planning.</p>'
        : '<div class="tm-acts"><p data-tv-status="info" data-tv-keep data-tv-label="Not approved yet:">Agents work up to Planning until it is (rules fingerprint ' . $e($fp) . '). A wording fix keeps your approval; a change to any rule clears it.</p><button type="button" class="ak-b" data-tm="tree"' . ($founder ? '' : ' disabled title="' . $e($noF) . '"') . '>Approve the decision tree</button></div>';
    $h .= '</section>';
    // the ceiling
    $keys = array_keys(TeamRunner::CEILINGS); $i = array_search($ceil, $keys, true); $next = $keys[$i + 1] ?? '';
    $h .= '<section class="ak-card tv-bleed tm-head" id="tm-ceiling"><h3 class="tm-h">Agents work up to: ' . $e(TeamRunner::CEILINGS[$ceil][0]) . '</h3><p>' . $e(TeamRunner::CEILINGS[$ceil][1]) . '</p>';
    if ($next !== '') {
        $why = !$tree ? 'Approve the decision tree first' : (!$founder ? $noF : ($next === 'confirmation' ? TeamRunner::confirmationWaits($pdo) : ''));
        $h .= '<h4 class="tm-h4">To raise it to ' . $e(TeamRunner::CEILINGS[$next][0]) . ': the evidence <small>you judge it; there are no pass marks</small></h4><table class="tm-t tm-ev"><tbody>';
        foreach (TeamRunner::evidence($pdo, $next) as [$lb, $rd, $src]) $h .= '<tr><td><b>' . $e($lb) . '</b></td><td>' . $e($rd) . '</td><td><small>' . $e($src) . '</small></td></tr>';
        $h .= '</tbody></table><div class="tm-acts"><button type="button" class="ak-b" data-tm="ceiling" data-to="' . $e($next) . '"' . ($why !== '' ? ' disabled title="' . $e($why) . '"' : '') . '>Raise to ' . $e(TeamRunner::CEILINGS[$next][0]) . '</button>' . ($why !== '' ? '<small>' . $e($why) . '</small>' : '') . '</div>';
    }
    if ($i > 0) $h .= '<div class="tm-acts">' . implode('', array_map(fn ($k) => '<button type="button" class="ak-b" data-tm="ceiling" data-to="' . $e($k) . '"' . ($founder ? '' : ' disabled title="' . $e($noF) . '"') . '>Lower to ' . $e(TeamRunner::CEILINGS[$k][0]) . '</button>', array_slice($keys, 0, $i))) . '</div>';
    try { $st = $pdo->query("SELECT at, tz, what, who FROM control_log WHERE what LIKE 'Team setting:%' ORDER BY id DESC LIMIT 10"); $hist = $st->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $x) { $hist = []; }
    $h .= '<details class="tm-hist"><summary>History</summary>' . ($hist ? '<ol>' . implode('', array_map(fn ($r) => '<li>' . $when($r['at'], (string) $r['tz']) . ' · ' . $e(preg_replace('/^Team setting:\s*/', '', $r['what'])) . ' <small>(' . $e($r['who']) . ')</small></li>', $hist)) . '</ol>' : '<p>No changes yet.</p>') . '</details></section>';
    // how far Implementation reaches (P3-6, the fence; decisions/0179's note "agents work on the backend first", Jason, 2026-11-26)
    $rch = TeamRunner::reach($pdo); $other = $rch === 'backend' ? 'everywhere' : 'backend';
    $h .= '<section class="ak-card tv-bleed tm-head" id="tm-reach"><h3 class="tm-h">How far Implementation reaches: ' . $e(TeamRunner::REACH[$rch][0]) . '</h3><p>' . $e(TeamRunner::REACH[$rch][1]) . '</p>'
        . '<p class="tm-note">Backend is every <code>/admin/</code> page, the site\'s internal code and the workers, where a change reaches no public page; the live mirror\'s reach map decides it. Changing this changes the decision tree\'s rules, so its approval is cleared.</p>'
        . '<div class="tm-acts"><button type="button" class="ak-b" data-tm="reach" data-to="' . $e($other) . '"' . ($founder ? '' : ' disabled title="' . $e($noF) . '"') . '>Change to ' . $e(TeamRunner::REACH[$other][0]) . '</button>' . ($founder ? '' : '<small>' . $e($noF) . '</small>') . '</div></section>';
    $h .= '<style>.tm-head{margin:0 0 .6rem}.tm-h{font:700 16px var(--tv-font-head,Georgia,serif);margin:0 0 .4rem}.tm-h small{font:400 12.5px var(--tv-font-ui,inherit);color:var(--tv-meta,#6b5d49)}.tm-h4{margin:.7rem 0 .3rem;font-size:14px}.tm-h4 small{font-weight:400;color:var(--tv-meta,#6b5d49)}'
        . '.tm-tiers,.tm-areas{margin:.2rem 0 .5rem;padding-left:1.2rem;font-size:13.5px}.tm-areas{font-size:12.5px}.tm-scroll{overflow-x:auto;max-width:100%}.tm-t{border-collapse:collapse;width:100%;font-size:13px;background:var(--tv-card-a,#F6F1E4)}.tm-t th,.tm-t td{border:1px solid var(--tv-line,#d8cdb4);padding:.35rem .45rem;text-align:left;vertical-align:top}'
        . '.tm-t small{color:var(--tv-meta,#6b5d49)}.tm-now{background:color-mix(in srgb,var(--tv-sage,#92BEA5) 30%,var(--tv-card-a,#F6F1E4));font-weight:600}.tm-note{font-size:13px}.tm-acts{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin:.5rem 0}.tm-acts small{font-size:12.5px;color:var(--tv-meta,#6b5d49)}.tm-hist{margin-top:.5rem;font-size:13px}'
        . '@media (max-width:767px){.tm-ev td:nth-child(3){display:none}}</style>';
    $h .= '<script>(function(){if(window.tvTeamHead)return;window.tvTeamHead=1;try{var said=JSON.parse(sessionStorage.getItem("tmSay")||"null");if(said){sessionStorage.removeItem("tmSay");var at=document.getElementById(said.id);setTimeout(function(){if(window.TvAsk)TvAsk.say(said.m,{anchor:at&&at.querySelector("h3")});if(at)at.scrollIntoView({block:"start"});},300);}}catch(x){}document.addEventListener("click",function(e){var b=e.target.closest("[data-tm]");if(!b||b.disabled)return;'
        . 'var tm=b.getAttribute("data-tm");var body={action:tm==="tree"?"teamtree":(tm==="reach"?"teamreach":"teamceiling"),to:b.getAttribute("data-to")||"",tz:(Intl.DateTimeFormat().resolvedOptions().timeZone||"")};if(window.TvAsk)TvAsk.busy(b,true);'
        . 'fetch("/api/auth/session.php",{credentials:"include"}).then(function(r){return r.json()}).then(function(s){return fetch("/admin/api/config/team",{method:"POST",credentials:"include",headers:{"Content-Type":"application/json","X-CSRF-Token":s.csrf_token||""},body:JSON.stringify(window.tvSafe?tvSafe(body):body)})})'
        . '.then(function(r){return r.json()}).then(function(j){if(window.TvAsk)TvAsk.busy(b,false);if(!j.ok){if(window.TvAsk)TvAsk.say((j.error&&j.error.message)||"It wasn\'t saved.",{anchor:b,bad:true});return;}'
        . 'var sec=b.closest("section");try{sessionStorage.setItem("tmSay",JSON.stringify({m:j.message||"Saved.",id:sec?sec.id:""}))}catch(x){}location.hash=sec?sec.id:"";location.reload();})'
        . '.catch(function(){if(window.TvAsk){TvAsk.busy(b,false);TvAsk.say("It wasn\'t saved: try again.",{anchor:b,bad:true});}});});})();</script>';
    return $h;
}
