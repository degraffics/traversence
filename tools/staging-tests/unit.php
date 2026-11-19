<?php
// tools/staging-tests/unit.php — the rules of decisions/0165 with no database: the three-way merge (StagingMerge), the
// register of record numbers' rewriting (StagingIds), the table register (StagingTables) and the scan for outside calls
// that skip the gate (StagingGate::scanText).
//   php tools/staging-tests/unit.php <the app folder: a mirror's sync-app with the release's app/ laid over it>
declare(strict_types=1);

$app = rtrim($argv[1] ?? '', '/');
if ($app === '' || !is_file($app . '/lib/StagingMerge.php')) { fwrite(STDERR, "usage: php unit.php <app folder>\n"); exit(2); }
require_once $app . '/lib/StagingMerge.php';
require_once $app . '/lib/StagingIds.php';
require_once $app . '/lib/StagingGate.php';

$fails = 0; $n = 0;
function ok(bool $c, string $what): void { global $fails, $n; $n++; if (!$c) { $fails++; echo "FAIL: $what\n"; } }
function eqv($a, $b, string $what): void { ok($a === $b, $what . ($a === $b ? '' : ' — got ' . json_encode($a) . ', want ' . json_encode($b))); }

// ---- the merge: every line of §4's table -------------------------------------------------------------------------
$base = ['id' => '1', 'name' => 'Cafe', 'phone' => '555', 'about' => 'old', 'email' => 'p1@staging.invalid'];
$M = 'StagingMerge';
eqv($M::row($base, ['name' => 'Café'] + $base, $base)['do'], $M::UPDATE, 'staging changed, live unchanged → staging\'s row');
eqv($M::row($base, ['name' => 'Café'] + $base, $base)['fields'], ['name' => 'Café'], '…only the changed field is written');
eqv($M::row($base, $base, ['name' => 'Live'] + $base)['do'], $M::KEEP, 'unchanged on staging, changed on live → live stays');
$r = $M::row($base, ['name' => 'Café'] + $base, ['phone' => '777'] + $base);
eqv([$r['do'], $r['fields']], [$M::UPDATE, ['name' => 'Café']], 'both changed, different fields → each side\'s fields');
eqv($M::row($base, ['name' => 'Same'] + $base, ['name' => 'Same'] + $base)['do'], $M::KEEP, 'same field to the same value → nothing to do');
$r = $M::row($base, ['name' => 'A'] + $base, ['name' => 'B'] + $base);
eqv([$r['do'], $r['clash']], [$M::CONFLICT, ['name' => ['Cafe', 'A', 'B']]], 'same field, different values → a conflict');
eqv($M::row(null, ['id' => '9', 'name' => 'New'], null)['do'], $M::INSERT, 'added on staging → added to live');
eqv($M::row($base, null, $base)['do'], $M::DELETE, 'removed on staging, unchanged on live → removed on live');
eqv($M::row($base, null, ['name' => 'X'] + $base)['do'], $M::CONFLICT, 'removed on staging, changed on live → a conflict');
eqv($M::row($base, ['name' => 'X'] + $base, null)['do'], $M::CONFLICT, 'changed on staging, removed on live → a conflict');
eqv($M::row($base, null, null)['do'], $M::KEEP, 'removed on both → nothing');
eqv($M::row(null, ['id' => '5', 'name' => 'Mine'], ['id' => '5', 'name' => 'Theirs'])['do'], $M::INSERT, 'both have a row by a key the base never had → staging\'s is new (gets a new number)');
// stripped columns: a stand-in on staging is never carried, and never counts as a change on either side
$skip = ['email'];
$r = $M::row($base, ['email' => 'p2@staging.invalid'] + $base, ['email' => 'real@example.com'] + $base, $skip);
eqv([$r['do'], $r['left']], [$M::KEEP, ['email']], 'a change to a stripped field is shown as not carried');
eqv($M::row($base, ['name' => 'Z'] + $base, ['email' => 'real@example.com'] + $base, $skip)['fields'], ['name' => 'Z'], 'live\'s real value of a stripped field is no change');
eqv($M::row(null, ['id' => '9', 'email' => 'typed@x.com'], null, $skip)['fields']['email'], 'typed@x.com', 'a row made on staging carries every field it was given');
// values as the database gives them back
ok($M::eq(1, '1') && !$M::eq(null, '') && $M::eq(null, null), 'eq: 1 and "1" are the same; null and "" aren\'t');
// settling
$c = $M::row($base, ['name' => 'A', 'about' => 'new'] + $base, ['name' => 'B'] + $base);
eqv($M::settle($c, 'live', null, null)['do'], $M::KEEP, 'Keep live');
eqv($M::settle($c, 'staging', null, null)['fields'], ['about' => 'new', 'name' => 'A'], 'Use staging: staging\'s fields, the clash included');
eqv($M::settle($c, ['name' => ['value' => 'C']], null, null)['fields'], ['about' => 'new', 'name' => 'C'], 'a typed value');
eqv($M::settle($M::row($base, null, ['name' => 'X'] + $base), 'staging', null, ['name' => 'X'] + $base)['do'], $M::DELETE, 'Use staging on a removal → removed');
$thrown = false; try { $M::settle($c, ['about' => 'staging'], null, null); } catch (InvalidArgumentException $e) { $thrown = true; }
ok($thrown, 'a per-field choice must cover every clashing field');

// ---- the register of record numbers ------------------------------------------------------------------------------
$map = ['entities' => [12 => 40], 'users' => [5 => 6]];
eqv(StagingIds::text('see listing:12 and listing:120 and user:5', $map), ['see listing:40 and listing:120 and user:6', 2], 'kind:id strings, not a longer number');
eqv(StagingIds::text('<a href="/listing/view.php?id=12">x</a> /listing/view.php?id=121', $map), ['<a href="/listing/view.php?id=40">x</a> /listing/view.php?id=121', 1], 'links in text');
eqv(StagingIds::text('entity:12', []), ['entity:12', 0], 'nothing to move, nothing changed');
[$j, $k] = StagingIds::json('{"blocks":[{"listing_id":12},{"listing_id":"12"},{"listing_id":13}],"note":"listing:12"}', ['blocks.*.listing_id' => 'entities'], $map);
eqv([$j, $k], ['{"blocks":[{"listing_id":40},{"listing_id":"40"},{"listing_id":13}],"note":"listing:40"}', 3], 'ids in JSON at a path, and strings in it');
[$j] = StagingIds::json('{"ids":[{"kind":"listing","id":12},{"kind":"user","id":12}]}', ['ids.*.id' => '?kind'], $map);
eqv($j, '{"ids":[{"kind":"listing","id":40},{"kind":"user","id":12}]}', 'a ?kind path reads the kind beside the id');
eqv(StagingIds::storeKey('entity/12/photo.jpg', $map), 'entity/40/photo.jpg', 'a store key with an id');
eqv(StagingIds::storeKey('entities/12/a.jpg', $map), 'entities/40/a.jpg', 'a store key (plural)');
eqv(StagingIds::storeKey('entity/13/a.jpg', $map), 'entity/13/a.jpg', 'a store key not moved');
[$row, $k] = StagingIds::row(['id' => 3, 'entity_id' => 12, 'ref_kind' => 'user', 'ref_id' => 5, 'notes' => 'from listing:12'], ['entity_id' => 'entities', 'ref_id' => '?ref_kind'], [], ['notes'], $map);
eqv([$row, $k], [['id' => 3, 'entity_id' => 40, 'ref_kind' => 'user', 'ref_id' => 6, 'notes' => 'from listing:40'], 3], 'a row: columns, a ?kind column, text');
$b = StagingIds::build([['main', 'entity_metadata', 'entity_id', 'int(10) unsigned'], ['main', 'entity_metadata', 'notes', 'text'], ['main', 'gizmo_parts', 'widget_id', 'int'],
                        ['main', 'orders', 'stripe_id', 'varchar(64)'], ['main', 'pulse_notes', 'ref_id', 'int'], ['main', 'pulse_notes', 'ref_kind', 'varchar(20)'], ['main', 'entities', 'owner_user_id', 'int']]);
$refs = array_map(fn ($x) => $x[1] . '.' . $x[2] . '>' . $x[3] . ':' . $x[4], $b['refs']);
ok(in_array('entity_metadata.entity_id>col:entities', $refs, true), 'build: entity_id → entities');
ok(in_array('entity_metadata.notes>kind:*', $refs, true), 'build: a notes column is searched for kind:id');
ok(in_array('entities.owner_user_id>col:users', $refs, true), 'build: owner_user_id → users (REFS)');
ok(in_array('pulse_notes.ref_id>col:?ref_kind', $refs, true), 'build: ref_id with a kind column beside it');
eqv($b['unknown'], [['main', 'gizmo_parts', 'widget_id']], 'build: a *_id that names no known table is unknown (and stops a move that touches it)');
ok(!in_array('orders.stripe_id>col:?', $refs, true), 'build: a text id (Stripe\'s) is not a record number');

// ---- the table register --------------------------------------------------------------------------------------------
eqv(StagingTables::classOf('entities'), 'records', 'listings are records');
eqv(StagingTables::classOf('orders'), 'money', 'orders are money');
eqv(StagingTables::classOf('zip_coordinates'), 'follows', 'zip_coordinates follows live');
eqv(StagingTables::classOf('journey_views'), 'own', 'views on staging are staging\'s own (choice 5)');
eqv(StagingTables::classOf('vault_creds'), 'never', 'the vault is never copied');
eqv(StagingTables::classOf('a_table_nobody_named'), 'follows', 'an unlisted table follows live');
ok(!StagingTables::named('a_table_nobody_named') && StagingTables::named('crawl_whatever'), 'named(): by name or pattern');
foreach ([StagingTables::RECORDS, StagingTables::MONEY, StagingTables::OWN, StagingTables::NEVER] as $i => $l) foreach ([StagingTables::RECORDS, StagingTables::MONEY, StagingTables::OWN, StagingTables::NEVER] as $j2 => $l2)
    if ($i < $j2 && ($x = array_intersect($l, $l2))) ok(false, 'a table in two classes: ' . implode(', ', $x));

// ---- the scan for outside calls that skip the gate -----------------------------------------------------------------
$src = "<?php\nfunction a() {\n  \$c = curl_init('https://x');\n}\nfunction b() {\n  if (StagingGate::hold(\$u) !== null) return;\n  \$c = curl_init(\$u);\n}\nfunction c() { // @gate live only\n  curl_init('x');\n}\nfunction d() {\n  return file_get_contents('https://api.example.com/x');\n}\n";
$hits = StagingGate::scanText($src);
eqv(array_map(fn ($h) => $h[1], $hits), ['a', 'd'], 'scan: ungated calls found, gated and noted ones not');
eqv(StagingGate::scanText("<?php\nfunction e() {\n  if (ServiceHealth::held(\$u) !== null) return;\n  curl_init(\$u);\n}\n"), [], 'scan: ServiceHealth::held() is a gate (it asks StagingGate first)');
// what a held call says on staging (Jason, 2026-11-17), with no database: the ledger write fails quietly and the call stays held
StagingGate::pretend(true);
eqv(StagingGate::hold('https://api.name.com/v4/domains'), "TEST MODE: ACTIVE (STAGE REPLIES ONLY) · Name.com wasn't called (held on staging). Nothing was sent.", 'a held call starts TEST MODE: ACTIVE (STAGE REPLIES ONLY) and names the service');
eqv(StagingGate::hold('https://quickbooks.api.intuit.com/v3/company/1/invoice'), "TEST MODE: ACTIVE (STAGE REPLIES ONLY) · QuickBooks (Intuit) wasn't called (sandbox only on staging). Nothing was sent.", 'payments: only the sandbox');
eqv(StagingGate::hold('https://sandbox-quickbooks.api.intuit.com/v3/company/1/invoice'), null, '…the sandbox goes');
eqv(StagingGate::hold('https://api.census.gov/data/2020/x', '', '', true), "TEST MODE: ACTIVE (STAGE REPLIES ONLY) · U.S. Census Bureau wasn't called (bulk pulls are held on staging). Nothing was sent.", 'a bulk pull of a free service is held');
ok(str_contains((string) StagingGate::hold('https://textract.us-east-2.amazonaws.com/'), 'Amazon Textract wasn\'t called (held on staging)'), 'Textract (paid) is held though AWS is allowed');
ok(str_contains((string) StagingGate::hold('https://email.us-east-2.amazonaws.com/v2/email/identities'), 'Amazon SES wasn\'t called'), 'SES (sends, changes our account) is held');
eqv(StagingGate::hold('https://bucket.s3.us-east-2.amazonaws.com/staging/x.jpg'), null, 'the file store is allowed');
eqv(StagingGate::hold('https://staging.trvgo.us/api/staging-updates.php'), null, 'a call to staging itself goes');
ok(str_starts_with((string) StagingGate::hold('https://box.example-host.com:2083/execute/Email/add_pop'), 'TEST MODE: ACTIVE (STAGE REPLIES ONLY) · box.example-host.com wasn\'t called'), 'a host with no service card (cPanel) is held');
StagingGate::pretend(false);
// the gate on live: always go, and no database touched
StagingGate::pretend(false);
eqv(StagingGate::hold('https://api.anthropic.com/v1/messages'), null, 'on live the gate always says go');
eqv(StagingGate::liveKeys(['QB_CLIENT_ID' => 'x', 'STRIPE_SECRET_KEY' => 'sk_live_1']), ['QB_CLIENT_ID', 'STRIPE_SECRET_KEY (a live key)'], 'live payment keys are named');
eqv(StagingGate::liveKeys(['QB_SANDBOX_CLIENT_ID' => 'x', 'STRIPE_SECRET_KEY' => 'sk_test_1']), [], 'sandbox keys are fine');
require_once $app . '/lib/OutsideServices.php';
$missing = array_diff(array_keys(OutsideServices::COMPANIES), array_keys(StagingGate::ANSWERS));
eqv(array_values($missing), [], 'every service in the register has a staging answer');
ok(!preg_match('~https://(?!(\*\.)?(tile\.openstreetmap|basemap\.nationalmap|upload\.wikimedia|commons\.wikimedia|cdnjs\.cloudflare|cdn\.tailwindcss|challenges\.cloudflare|js\.stripe|checkout\.stripe|api\.stripe|m\.stripe|stripe|web\.squarecdn|sandbox\.web\.squarecdn|squarecdn|sandbox\.api\.intuit|pci-connect\.squareupsandbox|connect\.squareupsandbox|amazonaws|r2\.cloudflarestorage|cloudfront|www\.youtube-nocookie|player\.vimeo|traversence|tile)\.)~', StagingGate::csp()), 'the CSP names only the hosts pages need');

// ---- the guards that ask StagingGate::here() (2026-11-19): it was private, so their calls threw, were caught as "not
// staging", and staging could write .env, mailbox passwords and our AWS account ----------------------------------------
ok((new ReflectionMethod('StagingGate', 'here'))->isPublic(), 'StagingGate::here() can be asked from outside');
$guard = function (string $cls, string $m) use ($app): ?bool {
    require_once $app . '/lib/' . $cls . '.php';
    $r = new ReflectionMethod($cls, $m); $r->setAccessible(true); return $r->invoke(null);
};
StagingGate::pretend(true);
eqv($guard('CommsSetup', 'onStaging'), true, 'CommsSetup\'s guard answers "staging" on staging');
eqv($guard('Mailboxes', 'staging'), true, 'Mailboxes\' guard answers "staging" on staging');
require_once $app . '/lib/SesInbound.php';
$said = ''; try { SesInbound::setup(new PDO('sqlite::memory:'), 0); } catch (Throwable $x) { $said = get_class($x) . ': ' . $x->getMessage(); }
eqv($said, 'RuntimeException: Staging never changes our AWS account.', 'SesInbound refuses on staging, as a RuntimeException (not an Error)');
StagingGate::pretend(false);
eqv($guard('CommsSetup', 'onStaging'), false, '…and answers "live" on live');
eqv($guard('Mailboxes', 'staging'), false, '…Mailboxes too');
StagingGate::pretend(null);

echo $fails ? "$fails of $n failed\n" : "All $n passed\n";
exit($fails ? 1 : 0);
