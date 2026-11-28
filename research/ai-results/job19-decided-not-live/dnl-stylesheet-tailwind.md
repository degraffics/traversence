**Skipped first:** this handoff has three items that are paused or waiting for Jason. Nothing below touches them.
- M2b (order legs): paused by Jason on 2026-11-24.
- No Undo on an alarm's acknowledgement: needs a Design look and Jason's pop-up.
- Order #12's books: Jason will address this after the holiday.

The stylesheet item itself was approved by Jason on 2026-11-27 and is not paused. Its handoff row does set an order, though:
- It is **its own release, after C.5a and C.5b**.
- **A plan goes to the session manager first.**
- Then the Design reviewer checks it at 1280 and 390 on every page kind.

So everything here is a draft for that plan review. It is not a release to send.

**No live code files were attached.** Only the two decision documents and the handoff came with the request. So I can only give full diffs for new files. For each live file that needs a change, I say "file not shown" and describe the change without writing hunks.

---

## 1. Plan

1. **Add the build config** `tools/css-build/tailwind.config.cjs`. It scans the site's own files, as the note on `design/css-theme-brand.md` (2026-11-27) says: "built once … with the tailwindcss CLI over the site's files".
2. **Add the build source** `tools/css-build/input.css`. It holds the base, components and utilities layers that the Play CDN injects, plus a place for any `text/tailwindcss` blocks moved out of the pages.
3. **Add the build script** `tools/css-build/build.sh`. It pins Tailwind CLI v3, the line the Play CDN serves, so the output matches what visitors get today. It writes `css/site.css`, which is "served from our server" (same note).
4. **Add a helper** `app/ui/site-css.php` with `tv_site_css_tag()`. It prints one render-blocking `<link>` with a content-hash `?v=`. This "ends the CDN-caused layout shift sitewide" (same note).
5. **Change the head templates** (file not shown): remove the `cdn.tailwindcss.com` script and its inline `tailwind.config`, and echo `tv_site_css_tag()` in their place. The shared shell is named in `design/css-theme-brand.md` §3 as `app/ui/app-shell.php`.
6. **Update CSP and outside-services** (files not shown): drop `cdn.tailwindcss.com` from any CSP header and close decisions/0098's outside-services row and decisions/0135. The note says they "are carried out by it".
7. **Add a test** `tools/css-tests/stylesheet-test.php`. It checks the build output, confirms no CDN references remain, checks the helper, and confirms every literal utility class in the PHP is in the built CSS.
8. **Add FAQ entries**, marked "coming" (faq.md not shown).
9. **No migration** is needed.

## 2. Diffs

### New files

```diff
--- /dev/null
+++ b/tools/css-build/tailwind.config.cjs
@@ -0,0 +1,31 @@
+/*
+ * Traversence: the site's stylesheet, built once with the tailwindcss CLI
+ * (Jason, 2026-11-27, "Yes to the stylesheet"; note on design/css-theme-brand.md).
+ * Replaces the Play CDN (cdn.tailwindcss.com). Run tools/css-build/build.sh.
+ *
+ * content: every place a class name can be written. Paths are relative to the
+ * repo root (the build runs there). tools/css-tests/stylesheet-test.php fails
+ * if a top-level folder with PHP in it is missing from this list.
+ */
+module.exports = {
+  content: [
+    './*.php',
+    './admin/**/*.php',
+    './api/**/*.php',
+    './app/**/*.php',
+    './discovery/**/*.php',
+    './hub/**/*.php',
+    './includes/**/*.php',
+    './journeys/**/*.php',
+    './listing/**/*.php',
+    './place/**/*.php',
+    './user/**/*.php',
+    './js/**/*.js',
+  ],
+  // LANE: copy the live page's `tailwind.config = { ... }` (the inline script
+  // next to the CDN tag) here unchanged, and any CDN ?plugins=. File not shown.
+  theme: {
+    extend: {},
+  },
+  plugins: [],
+};
```

```diff
--- /dev/null
+++ b/tools/css-build/input.css
@@ -0,0 +1,10 @@
+/* Traversence site stylesheet source. Built to css/site.css by tools/css-build/build.sh. */
+@tailwind base;
+@tailwind components;
+@tailwind utilities;
+
+/*
+ * LANE: move here, unchanged, the contents of every <style type="text/tailwindcss">
+ * block in the live pages (they only work with the Play CDN). File not shown;
+ * tools/css-tests/stylesheet-test.php fails while any such block remains.
+ */
```

```diff
--- /dev/null
+++ b/tools/css-build/build.sh
@@ -0,0 +1,12 @@
+#!/bin/sh
+# Builds css/site.css from the site's files with the tailwindcss CLI (v3, as the Play CDN served).
+# Run in the lane session, never on Bluehost; css/site.css is committed and goes in the zip.
+set -eu
+ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
+cd "$ROOT"
+npx --yes tailwindcss@3.4.17 \
+  -c tools/css-build/tailwind.config.cjs \
+  -i tools/css-build/input.css \
+  -o css/site.css \
+  --minify
+php tools/css-tests/stylesheet-test.php
```

```diff
--- /dev/null
+++ b/app/ui/site-css.php
@@ -0,0 +1,18 @@
+<?php
+/**
+ * The site's built stylesheet (replaces the Play CDN script; Jason, 2026-11-27).
+ * Echo tv_site_css_tag() in <head> where the cdn.tailwindcss.com script was.
+ * The ?v= is the file's content hash, so a rebuild reaches visitors at once and
+ * re-uploading the same bytes doesn't bust caches.
+ */
+
+if (!function_exists('tv_site_css_tag')) {
+    function tv_site_css_tag(): string
+    {
+        $rel  = '/css/site.css';
+        $file = dirname(__DIR__, 2) . $rel;
+        $v    = is_file($file) ? substr((string) md5_file($file), 0, 10) : '0';
+        return '<link rel="stylesheet" href="'
+            . htmlspecialchars($rel . '?v=' . $v, ENT_QUOTES, 'UTF-8') . '">';
+    }
+}
```

`css/site.css` is the build output. It is generated, committed, and not drafted here.

### Live files: file not shown, no hunks invented

- **`app/ui/app-shell.php`**, plus every other template with its own `<head>` (admin, crawler and Dashboard pages if they don't use the shell): file not shown.
  - Remove the `<script src="https://cdn.tailwindcss.com…">` tag.
  - Remove the inline `tailwind.config = {…}` script. Copy its content into the `.cjs` config first.
  - Remove any `<style type="text/tailwindcss">` blocks. Move their content into `input.css` first.
  - Add `require_once` for `app/ui/site-css.php` and `<?= tv_site_css_tag() ?>`.
  - Place the link **after the other stylesheets in `<head>`**. The Play CDN appends its `<style>` at the end of `<head>`, so its rules currently win ties against our own CSS. Putting the link last keeps that cascade order.
- **`.htaccess` or wherever CSP is set**: file not shown. Remove `cdn.tailwindcss.com` from `script-src` only if it is listed there.
- **`decisions/0098`** (the outside-services row) and **`decisions/0135`**: files not shown. Mark the row done or dropped from the list, as the note says.
- **`faq.md`**: file not shown. The entries are in §4 below.
- **Deploy zip manifest or exclude list**: file not shown. `css/site.css` must be in the zip. `tools/css-build/` doesn't need to be.

## 3. Tests

`tools/css-tests/stylesheet-test.php`:

```php
<?php
/**
 