<?php final class ListingSafety { public static function isConfidential(?int $c, ?string $n): bool { return stripos((string) $n, 'shelter') !== false; } }
