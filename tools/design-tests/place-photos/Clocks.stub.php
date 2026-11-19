<?php final class Clocks { public static function sqlCutoff($pdo, $wf, $days): string { return date('Y-m-d H:i:s', time() - $days * 86400); } }
