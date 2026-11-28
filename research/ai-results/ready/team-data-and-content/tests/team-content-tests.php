<?php
// tools/team-tests/team-content-tests.php: the Front-End Content Writers' step (TeamContent and TeamRunner's 'content').
// Plain PHP with no database: every check here is a pure function. Prints PASS or FAIL per check, and exits 1 on any FAIL.
// Nothing is written, nothing is sent, and no money moves.
declare(strict_types=1);

$root = '';
foreach ([dirname(__DIR__), dirname(__DIR__, 2), getcwd()] as $r) if (is_file($r . '/app/lib/TeamContent.php')) { $root = $r; break; }
if ($root === '') { echo "FAIL setup: app/lib/TeamContent.php not found (run from the site root or tools/team-tests/)\n"; exit(1); }
require_once $root . '/app/lib/TeamContent.php';

$bad = 0;
function check(string $name, bool $pass, string $detail = ''): void
{
    global $bad; if (!$pass) $bad++;
    echo ($pass ? 'PASS ' : 'FAIL ') . $name . (!$pass && $detail !== '' ? ' (' . $detail . ')' : '') . "\n";
}
function tk(array $over = []): array
{
    return $over + ['id' => 1, 'ticket' => 'work', 'stage' => 'planning', 'what' => 'Add an FAQ entry on saved trips', 'why' => 'Members ask how saving works',
        'goal' => '', 'in_scope' => '', 'out_scope' => '', 'done_when' => '', 'flag' => '', 'build_brief' => '', 'owner' => '', 'source' => ''];
}

// the step's wiring in TeamRunner
check('step exists in STEPS', isset(TeamRunner::STEPS['content']));
check('step slug matches TeamContent::SLUG', (TeamRunner::STEPS['content'][0] ?? '') === TeamContent::SLUG);
$at = TeamRunner::STEP_AT['content'] ?? [];
check('STEP_AT covers every ceiling', array_keys($at) === array_keys(TeamRunner::CEILINGS));
check('never acts, only proposes', $at !== [] && array_unique(array_values($at)) === ['propose']);
check('costs $0', (TeamRunner::STEP_COST_MICROS['content'] ?? -1) === 0);
check('joins the decision tree rules (fingerprint changes: Founder re-approval)', isset(TeamRunner::ruleData()['steps']['content']));
echo 'INFO new rule fingerprint (no reach): ' . substr(TeamRunner::fingerprint(), 0, 12) . "\n";

// its lane routing (TeamRoster::LANE_ROUTES, unchanged)
check('the FAQ routes to the Editorial Director', TeamRoster::laneOf(['path:/faq.php'], 'FAQ') === 'editorial-director');

// its checks and proposals
$a = TeamContent::assess(tk(), 'editorial-director', ['path:/faq.php']);
check('FAQ ticket: kind faq', $a['kind'] === 'faq', $a['kind']);
check('FAQ ticket: proposes all four empty fields', $a['ok'] && array_keys($a['fields']) === ['goal', 'in_scope', 'out_scope', 'done_when'], json_encode(array_column($a['checks'], 2)));
check('FAQ Done when says coming', str_contains($a['fields']['done_when'] ?? '', '"coming"'));
$a = TeamContent::assess(tk(['goal' => 'A person wrote this goal']), 'editorial-director', ['path:/faq.php']);
check('a person\'s field is never proposed', $a['ok'] && !isset($a['fields']['goal']));
$a = TeamContent::assess(tk(['goal' => 'g', 'in_scope' => 'i', 'out_scope' => 'o', 'done_when' => 'd']), 'editorial-director', ['path:/faq.php']);
check('all written: nothing to propose', !$a['ok'] && $a['fields'] === []);
check('a territory target stops it', !TeamContent::assess(tk(), 'editorial-director', ['path:/order.php'])['ok']);
check('a stored flag stops it', !TeamContent::assess(tk(['flag' => 'address']), 'editorial-director', ['path:/faq.php'])['ok']);
check('nations words stop it', !TeamContent::assess(tk(['what' => 'Fix the wording on the sacred site notice']), 'editorial-director', [])['ok']);
check('another lane stops it', !TeamContent::assess(tk(), 'it-manager', ['path:/faq.php'])['ok']);
check('Building stage stops it', !TeamContent::assess(tk(['stage' => 'building']), 'editorial-director', ['path:/faq.php'])['ok']);
check('a design-only ticket is not its kind', TeamContent::kindOf('Redesign the hero layout', []) === '');
check('a listing path is listing', TeamContent::kindOf('fix it', ['path:/place/123']) === 'listing');
check('a guide word is guide', TeamContent::kindOf('The Show Low guide misses its sources', []) === 'guide');

// AI access order: code, then API, then session
$r = TeamContent::route('guide');
check('route order is code, api, session', array_column($r, 0) === ['code', 'api', 'session']);
check('API closed until the agents\' counter is live', TeamContent::apiOpen() === false && $r[1][1] === TeamContent::API_CLOSED);
check('session ask template stays lean (under 600 chars)', mb_strlen(TeamContent::SESSION_ASK) < 600);

echo ($bad === 0 ? 'ALL PASS' : $bad . ' FAILED') . "\n";
exit($bad === 0 ? 0 : 1);
