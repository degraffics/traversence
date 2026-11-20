#!/usr/bin/env python3
"""The Wikidata 429 fix (Jason, 2026-11-20, approved; board issue #26): PlaceFinder and Dossier ask the query service
through app/lib/Wdqs.php (one at a time, its wait honoured, answers kept 7 days). Replayable: build-wq.py <root>."""
import sys, os, shutil
R = sys.argv[1]; HERE = os.path.dirname(os.path.abspath(__file__))
shutil.copy(os.path.join(HERE, 'Wdqs.php'), os.path.join(R, 'app/lib/Wdqs.php'))
def patch(rel, pairs):
    p = os.path.join(R, rel); s = open(p, encoding='utf-8').read()
    for a, b in pairs:
        assert s.count(a) == 1, (rel, a[:90], s.count(a)); s = s.replace(a, b)
    open(p, 'w', encoding='utf-8').write(s)
patch('app/lib/PlaceFinder.php', [
 ("        require_once __DIR__ . '/ServiceHealth.php'; if (ServiceHealth::held($url) !== null) return null;\n"
  "        $ch = curl_init($url);\n"
  "        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_USERAGENT => self::UA, CURLOPT_HTTPHEADER => ['Accept: application/json']]);\n"
  "        $raw = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);\n"
  "        ServiceHealth::seen($url, $code, $raw, '', 'Place lookup');\n"
  "        if ($code !== 200 || !is_string($raw)) { error_log('places on demand: ' . $code . ' ' . mb_substr($url, 0, 120)); return null; }\n",
  "        require_once __DIR__ . '/ServiceHealth.php'; if (ServiceHealth::held($url) !== null) return null;\n"
  "        $call = function () use ($url, $timeout) {\n"
  "            $after = '';\n"
  "            $ch = curl_init($url);\n"
  "            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_USERAGENT => self::UA, CURLOPT_HTTPHEADER => ['Accept: application/json'],\n"
  "                CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$after) { if (stripos($h, 'Retry-After:') === 0) $after = trim(substr($h, 12)); return strlen($h); }]);\n"
  "            $raw = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);\n"
  "            ServiceHealth::seen($url, $code, $raw, '', 'Place lookup');\n"
  "            if ($code !== 200 || !is_string($raw)) error_log('places on demand: ' . $code . ' ' . mb_substr($url, 0, 120));\n"
  "            return [$code, is_string($raw) ? $raw : null, $after];\n"
  "        };\n"
  "        require_once __DIR__ . '/Wdqs.php';\n"
  "        if (Wdqs::is($url)) $raw = Wdqs::ask($url, $call);   // the query service: one at a time, its wait honoured, kept 7 days (2026-11-20)\n"
  "        else { [$code, $raw] = $call(); if ($code !== 200) $raw = null; }\n"
  "        if (!is_string($raw)) return null;\n"),
])
patch('app/lib/Dossier.php', [
 ("        require_once __DIR__ . '/ServiceHealth.php'; if (ServiceHealth::held($url) !== null) return null;\n        $ch = curl_init($url);\n",
  "        require_once __DIR__ . '/ServiceHealth.php'; if (ServiceHealth::held($url) !== null) return null;\n"
  "        require_once __DIR__ . '/Wdqs.php';\n"
  "        if (Wdqs::is($url)) return Wdqs::ask($url, function () use ($url, $timeout) {   // the query service: one at a time, its wait honoured, kept 7 days (2026-11-20)\n"
  "            $after = ''; $ch = curl_init($url);\n"
  "            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => true, CURLOPT_ENCODING => '', CURLOPT_USERAGENT => self::UA,\n"
  "                CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$after) { if (stripos($h, 'Retry-After:') === 0) $after = trim(substr($h, 12)); return strlen($h); }]);\n"
  "            $b = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);\n"
  "            ServiceHealth::seen($url, $code, $b, '', 'Dossier');\n"
  "            return [$code, is_string($b) ? $b : null, $after];\n"
  "        });\n"
  "        $ch = curl_init($url);\n"),
])
print('built')
