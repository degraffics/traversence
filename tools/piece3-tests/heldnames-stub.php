<?php
// beforeafter.test.mjs's view of HeldNames' hash, with no database: loads the class with a stand-in Landmarks.
// HELDNAMES_LIB: the HeldNames.php to test (default: the P3-5 build's).
declare(strict_types=1);
if (!class_exists('Landmarks')) { final class Landmarks { public const STORY_ONLY = 'story_only'; public static function ready($p): bool { return false; } public static function visibleSql(string $t = ''): string { return "status IN ('shown', 'approved')"; } } }
$lib = getenv('HELDNAMES_LIB') ?: __DIR__ . '/../../handoffs/piece3-build/p3-5-held/build/app/lib/HeldNames.php';
$src = (string) file_get_contents($lib);
eval('?>' . str_replace(["require_once __DIR__ . '/Landmarks.php';", 'declare(strict_types=1);'], '', $src));
