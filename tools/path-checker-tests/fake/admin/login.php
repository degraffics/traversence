<?php
// test stand-in for /admin/login.php: password step, then a code step that refuses a step at or before the last used
// one, as StaffLogin::totpMatch does. Never shipped.
session_start();
$secret = 'JBSWY3DPEHPK3PXP';
function b32($s){$a='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';foreach(str_split(strtoupper($s)) as $c)$bits.=str_pad(decbin(strpos($a,$c)),5,'0',STR_PAD_LEFT);$o='';for($i=0;$i+8<=strlen($bits);$i+=8)$o.=chr(bindec(substr($bits,$i,8)));return $o;}
function totp($sec,$step){$h=hash_hmac('sha1',pack('N*',0,$step),b32($sec),true);$o=ord($h[19])&15;return str_pad((string)(((ord($h[$o])&127)<<24|ord($h[$o+1])<<16|ord($h[$o+2])<<8|ord($h[$o+3]))%1000000),6,'0',STR_PAD_LEFT);}
$lastF = __DIR__ . '/../last.txt'; $last = (int) @file_get_contents($lastF); $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sid'])) { $_SESSION['pw'] = 1; }
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
  $now = intdiv(time(), 30); $ok = 0;
  foreach ([0, -1, 1] as $d) { $s = $now + $d; if ($s > $last && hash_equals(totp($secret, $s), $_POST['code'])) { $ok = $s; break; } }
  if ($ok) { file_put_contents($lastF, (string) $ok);  unset($_SESSION['pw']); header('Location: /admin/'); exit; }
   $err = "That code doesn't match.";
}
?><!doctype html><html><body><?php if (!empty($_SESSION['pw'])): ?>
<form method="post"><?php if ($err) echo '<p class="no" role="alert">' . $err . '</p>'; ?><input id="code" name="code"><button class="b">Sign in</button></form>
<?php else: ?><form method="post"><input id="sid" name="sid"><input id="pw" name="pw" type="password"><button class="b">Next</button></form><?php endif; ?></body></html>
