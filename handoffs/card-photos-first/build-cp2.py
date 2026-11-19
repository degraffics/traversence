#!/usr/bin/env python3
"""Card photos, follow-up (fix in place of #205; Jason's screenshot, 2026-11-19: Lyman Lake State Park still showed a
stock photo): a qualifying listing someone opens with no photo yet goes to the front of the finder's next run. Same
finder, same pace, same rules. Replayable: build-cp2.py <root> with app/lib/PlacePhotos.php."""
import sys, os
R = sys.argv[1]
def patch(rel, pairs):
    p = os.path.join(R, rel); s = open(p, encoding='utf-8').read()
    for a, b in pairs:
        assert s.count(a) == 1, (rel, a[:90], s.count(a)); s = s.replace(a, b)
    open(p, 'w', encoding='utf-8').write(s)
patch('app/lib/PlacePhotos.php', [
 (" *   slides($pdo, $kind, $ref)     the hero's slide for one place, or []\n",
  " *   slides($pdo, $kind, $ref)     the hero's slide for one place, or [] (and the place is wanted: looked up first)\n"
  " *   want($pdo, $kind, $ref)       a place someone opened with no photo yet: the next run looks it up first (2026-11-19)\n"),
 ("        $todo = [];\n        try {   // our listings first",
  "        require_once __DIR__ . '/PageCache.php';\n"
  "        $wanted = array_values(array_filter(array_map('intval', (array) (PageCache::get($pdo, self::WANT) ?: [])), fn ($x) => $x > 0));   // opened by someone: first\n"
  "        $first = $wanted ? 'e.id IN (' . implode(',', array_slice($wanted, 0, 50)) . ') DESC, ' : '';   // integers only\n"
  "        $todo = [];\n        try {   // our listings first"),
 ("                                ORDER BY (c.name LIKE 'National%') DESC, e.id LIMIT $n\");",
  "                                ORDER BY $first(c.name LIKE 'National%') DESC, e.id LIMIT $n\");"),
 ("        return [$looked, $found];\n    }",
  "        if ($wanted) { $done = array_map(fn ($r) => (int) $r['ref'], array_filter($todo, fn ($r) => $r['k'] === 'listing'));   // what this run reached leaves the list\n"
  "            PageCache::put($pdo, self::WANT, 7 * 86400, array_values(array_diff($wanted, $done))); }\n"
  "        return [$looked, $found];\n    }\n\n"
  "    /** The listings someone opened with no photo yet (PageCache): looked up first, at the finder's own pace. */\n"
  "    private const WANT = 'pp:want';\n\n"
  "    public static function want(PDO $pdo, string $kind, string $ref): void\n    {\n"
  "        if ($kind !== 'listing' || (int) $ref <= 0) return;   // the finder's kinds and rules decide the rest (a private place is never looked up)\n"
  "        try {\n"
  "            require_once __DIR__ . '/PageCache.php';\n"
  "            $l = array_map('intval', (array) (PageCache::get($pdo, self::WANT) ?: []));\n"
  "            if (in_array((int) $ref, $l, true)) return;\n"
  "            array_unshift($l, (int) $ref);\n"
  "            PageCache::put($pdo, self::WANT, 7 * 86400, array_slice($l, 0, 50));\n"
  "        } catch (Throwable $e) { error_log('place photos want: ' . $e->getMessage()); }\n"
  "    }"),
 ("        $p = self::for($pdo, $kind, [$ref])[$ref] ?? null;\n        if (!$p) return [];",
  "        $p = self::for($pdo, $kind, [$ref])[$ref] ?? null;\n        if (!$p) { self::want($pdo, $kind, $ref); return []; }   // opened without one: the next run looks for it first"),
])
print('built')
