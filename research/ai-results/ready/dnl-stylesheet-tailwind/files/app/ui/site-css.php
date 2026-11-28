<?php
declare(strict_types=1);
/**
 * The site's built stylesheet. It replaces the Play CDN script (Jason, 2026-11-27).
 *
 * Echo tv_site_css_tag() in <head>, where the cdn.tailwindcss.com script was.
 * The ?v= value is the file's content hash. A rebuild reaches visitors at once,
 * and re-uploading the same bytes does not bust caches.
 *
 * DRAFT: this assumes css/ sits beside app/ under the web root. File layout not shown.
 */

if (!function_exists('tv_site_css_tag')) {
    function tv_site_css_tag(): string
    {
        static $tag = null;
        if ($tag !== null) {
            return $tag;
        }
        $rel  = '/css/site.css';
        $file = dirname(__DIR__, 2) . $rel;
        if (is_file($file)) {
            $v = substr((string) md5_file($file), 0, 10);
        } else {
            $v = '0';
            error_log('tv_site_css_tag: css/site.css is missing; run tools/css-build/build.sh');
        }
        $tag = '<link rel="stylesheet" href="'
            . htmlspecialchars($rel . '?v=' . $v, ENT_QUOTES, 'UTF-8') . '">';
        return $tag;
    }
}
