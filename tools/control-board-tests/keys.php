<?php
// decisions/0173 release 2 (the key on its card): EnvSettings' one writer of secrets and its guards (only a key it knows,
// never on staging, only for whoever may set keys, never logged), the old value kept until Check passes and Put back /
// Forget, Remove; the card's buttons; Railway's keys with their link; the Park Service's key first, optional, so it never
// holds a switch-on. Writes the test root's .env and puts it back. Run: mariadb < ../release-b-tests/start-schema.sql &&
// php keys.php <root>/app
declare(strict_types=1);
$app = $argv[1];
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(is_string($got) ? $got : json_encode($got), 0, 400) : '') . "\n"; } };
// a step in a fresh process (the guard reads Access and staging once): prints its answer
$sub = function (string $code) use ($app): string {
    $f = tempnam(sys_get_temp_dir(), 'k'); file_put_contents($f, "<?php \$app = " . var_export($app, true) . "; require_once \$app . '/lib/Database.php'; require_once \$app . '/lib/EnvSettings.php';\n" . $code);
    $o = (string) shell_exec('php ' . escapeshellarg($f) . ' 2>&1'); unlink($f); return trim($o);
};
require_once $app . '/lib/Database.php'; require_once $app . '/lib/EnvSettings.php'; require_once $app . '/lib/Operations.php';
$env = Database::envPath(); $keep = (string) file_get_contents($env);
$baks = glob($env . '.bak-*') ?: [];
try {
    // which secrets the writer knows
    $sec = EnvSettings::secrets();
    $t('the writer knows every service key kept in .env, and the mailboxes\'', in_array('NPS_API_KEY', $sec, true) && in_array('ANTHROPIC_API_KEY', $sec, true) && in_array('SMTP_PASSWORD', $sec, true));
    $t('…but not one kept on Railway too (the crawler\'s token), nor the site\'s own (APP_KEY, the database\'s)', !in_array('CRAWLER_API_TOKEN', $sec, true) && !in_array('APP_KEY', $sec, true) && !in_array('DB_PASSWORD', $sec, true) && !in_array('GOOGLE_PLACES_KEY', $sec, true));

    // the guards, in the writer
    $o = $sub("try { EnvSettings::setKey('DB_PASSWORD', 'x'); echo 'WROTE'; } catch (Throwable \$x) { echo get_class(\$x) . ': ' . \$x->getMessage(); }");
    $t('a key it doesn\'t know is refused', str_contains($o, 'isn\'t a key the site writes'), $o);
    $o = $sub("putenv('STAGING=1'); try { EnvSettings::setKey('NPS_API_KEY', 'abc'); echo 'WROTE'; } catch (Throwable \$x) { echo \$x->getMessage(); }");
    $t('on staging nothing is written', str_contains($o, 'Staging never writes .env') && !str_contains((string) file_get_contents($env), 'NPS_API_KEY'), $o);
    $asExec = "require_once \$app . '/lib/Auth.php'; require_once \$app . '/admin/Access.php'; \$p = new ReflectionProperty('Access', 'staff'); \$p->setAccessible(true); \$p->setValue(null, ['id' => 9, 'level' => 'executive', 'department' => '']); \$l = new ReflectionProperty('Access', 'loaded'); \$l->setAccessible(true); \$l->setValue(null, true);";
    $o = $sub($asExec . " try { EnvSettings::setKey('NPS_API_KEY', 'abc'); echo 'WROTE'; } catch (Throwable \$x) { echo \$x->getMessage(); }");
    $t('an Executive is refused: keys are the Founder\'s (Access::can lets Executives through everything, so the level is asked)', !str_contains($o, 'WROTE') && str_contains($o, 'Keys are set by the Founder') && !str_contains((string) file_get_contents($env), 'NPS_API_KEY'), $o);
    $o = $sub($asExec . " try { EnvSettings::putBack('NPS_API_KEY'); echo 'PUT'; } catch (Throwable \$x) { echo \$x->getMessage(); }");
    $t('…Put back goes through the same guard', !str_contains($o, 'PUT'), $o);
    $o = $sub("try { EnvSettings::setKey('NPS_API_KEY', 'ab\"c'); echo 'WROTE'; } catch (Throwable \$x) { echo \$x->getMessage(); }");
    $t('a value .env can\'t hold is refused, saying why', str_contains($o, 'no quotes, backslashes or line breaks'), $o);
    $src = (string) file_get_contents($app . '/lib/EnvSettings.php');
    $t('setMailSecret goes through the same writer and guards', (bool) preg_match('/function setMailSecret.*?self::setKey\(\$key, \$value\)/s', $src));
    $t('the log names the key and how, never the value', (bool) preg_match("/'detail' => \\\$key \. ': ' \. \\\$how/", $src) && !preg_match('/\'detail\' =>[^\n]*\$value/', $src));

    // add, replace, put back, forget, remove
    $o = $sub("EnvSettings::setKey('NPS_API_KEY', 'first-key-123'); echo EnvSettings::value('NPS_API_KEY');");
    $t('Add its key: written to .env', $o === 'first-key-123' && str_contains((string) file_get_contents($env), 'NPS_API_KEY=first-key-123'), $o);
    $t('…and the file before it kept as a copy', count(glob($env . '.bak-*') ?: []) > count($baks));
    $o = $sub("EnvSettings::setKey('NPS_API_KEY', 'second-key-456'); echo EnvSettings::value('NPS_API_KEY') . '|' . (EnvSettings::hasOld('NPS_API_KEY') ? 'old' : 'none');");
    $t('Replace: the new one in place, the old one kept until Check passes', $o === 'second-key-456|old', $o);
    $o = $sub("EnvSettings::putBack('NPS_API_KEY'); echo EnvSettings::value('NPS_API_KEY') . '|' . (EnvSettings::hasOld('NPS_API_KEY') ? 'old' : 'none');");
    $t('Put the old one back: the first key again, nothing left to put back', $o === 'first-key-123|none', $o);
    $o = $sub("EnvSettings::setKey('NPS_API_KEY', 'third-key-789'); EnvSettings::forgetOld('NPS_API_KEY'); echo (EnvSettings::hasOld('NPS_API_KEY') ? 'old' : 'none') . '|' . (str_contains((string) file_get_contents(Database::envPath()), 'first-key-123') ? 'kept' : 'gone');");
    $t('Forget (or a passing Check): the old value is gone from .env', $o === 'none|gone', $o);
    $o = $sub("EnvSettings::removeKey('NPS_API_KEY'); echo '[' . EnvSettings::value('NPS_API_KEY') . ']|' . (EnvSettings::hasOld('NPS_API_KEY') ? 'old' : 'none');");
    $t('Remove the key: taken out, kept so Put back still works', $o === '[]|old', $o);
    $o = $sub("try { EnvSettings::removeKey('NPS_API_KEY'); echo 'X'; } catch (Throwable \$x) { echo \$x->getMessage(); }");
    $t('…removing one that isn\'t set says so', str_contains($o, 'nothing to remove'), $o);

    // the Park Service: optional, so it never holds a switch-on, and its stage isn't "missing"
    $nk = Operations::KEYS['nps'][0] ?? [];
    $t('the Park Service\'s key is on the register, marked optional (NPGallery needs none)', ($nk[0] ?? '') === 'NPS_API_KEY' && str_contains((string) ($nk[1] ?? ''), 'optional'));
    require_once $app . '/lib/ServiceStages.php';
    $t('…so a missing one leaves it Active', ServiceStages::decide([['NPS_API_KEY', $nk[1], false]], null, false, false)[0] === 'active');
    $t('…and the switch-on check skips an optional key', str_contains((string) file_get_contents($app . '/lib/Operations.php'), "if (!str_contains(\$x[1], 'optional')) \$need["));
    $pf = (string) file_get_contents($app . '/lib/Preflight.php');
    $t('…and its Check, once it\'s in, asks the Park Service\'s API with it (free), else NPGallery', str_contains($pf, "https://developer.nps.gov/api/v1/parks?limit=1") && str_contains($pf, "'X-Api-Key: ' . \$e('NPS_API_KEY')"));
    $t('where to add a key no longer says cPanel', !str_contains(Operations::whereToAdd('.env'), 'cPanel') && str_contains(Operations::whereToAdd('.env'), 'Add its key'));
} finally {
    file_put_contents($env, $keep);   // the test root's .env as it was
    foreach (array_diff(glob($env . '.bak-*') ?: [], $baks) as $b) @unlink($b);
}

// the card: the buttons, greyed with the reason for anyone else; Railway's with their link
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; $pdo = Database::connection();
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require_once $app . '/admin/sections.php'; require_once $app . '/admin/operations-console.php';
$c = tv_ops_keys('nps', Operations::keys('nps'), 'x');   // as the Founder (no staff login: an admin from before them)
$p = new ReflectionProperty('Access', 'staff'); $p->setAccessible(true); $l = new ReflectionProperty('Access', 'loaded'); $l->setAccessible(true); $l->setValue(null, true); $p->setValue(null, ['id' => 9, 'level' => 'executive', 'department' => '']);
$ce = tv_ops_keys('nps', Operations::keys('nps'), 'x'); $p->setValue(null, null);
$t('a card offers Add its key: a hidden box (Show/Hide), never the value', str_contains($c, 'value="key_set"') && str_contains($c, 'type="password" name="value" autocomplete="off"') && str_contains($c, '>Add its key</button>'));
$t('…open to the Founder', !preg_match('/value="key_set".*?disabled/s', $c), $c);
$t('…greyed with the reason for anyone else', (bool) preg_match('/>Add its key</', $ce) && str_contains($ce, 'disabled title="Keys are set by the Founder"'), $ce);
$t('…and Check, its pre-flight test', str_contains($c, 'value="key_check"') && str_contains($c, '>Check</button>'));
$g = tv_ops_keys('google', Operations::keys('google'), 'x');
$t('a key kept on Railway: where to set it, with the link to the crawler\'s Variables, and Check', str_contains($g, 'railway.com/project/') && str_contains($g, '/variables') && !str_contains($g, 'value="key_set"') && str_contains($g, 'value="key_check"'));
$html = tv_admin_mod_register($pdo);
$t('the register draws each card\'s keys this way', substr_count($html, 'class="os-key"') >= 15 && str_contains($html, 'id="key-NPS_API_KEY"'));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
