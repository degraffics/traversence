<?php // harness stand-in: the kept reading from the page's ?state=
final class SearchIndex { public static $v = ''; public static function state($pdo, $k): string { return self::$v; } public static function setState($pdo, $k, $v): void { self::$v = $v; } }
