# Draft: outside links open in the browser, with ↗, the service and the account, and a first-tap hint on phones

**Status check.** No attached document says this item is built or live:
- `handoffs/control-board-2026-11-24.md` lists it as item 8 under "Next".
- `handoffs/control-board-2026-11-25.md` doesn't list it as live. It says everything outstanding went into the Work queue (task-seed-3), so it should be a task there.

This draft covers the whole item. The lane must still grep the live mirror for `data-tv-outside` and `target="_blank"` in `app/admin/kit-css.php` before building, in case something landed after 11-25.

**Files not shown:** `app/admin/kit-css.php`, the kit's JS ("its JS" in the hand-off; its path isn't named), the admin layout that loads scripts, `control-console.php`, `OutsideServices.php`, `ControlEngine.php` and `ui/faq.md`. I don't diff any of them, and I don't guess at their content.

---

## 1. Plan

1. **Add a small helper, `app/lib/OutsideLink.php`** (new). It decides whether a link is our own page (opened in Peek through `data-peek-frame`, as `ControlBoard::pausedHtml` already does) or an outside link (opened in the browser with `target="_blank" rel="noopener"`, ↗, and a title naming the service and the account). Source: CLAUDE.md #1344 and hand-off 2026-11-24 item 8.
2. **Tag outside "Fix it" links in `ControlGauges::fix()`** with the service they open, so the gauge drawer can draw ↗ and the title. It already allows "this site or https only". Source: hand-off item 8, "every page follows it".
3. **Add a kit script, `website/js/tv-outside.js`** (new). It upgrades any outside `http(s)` link on an admin page, including links drawn on the board's 30-second redraw. On phones (below 768px, the board-phone breakpoint in hand-off 11-24 item 1), it shows a one-time hint at the first outside tap. The hint closes with ×, Esc or a click outside (hand-off rule: "every dropdown closes with ×, Esc and a click outside"). Source: hand-off item 8.
   - The hand-off says to build this in the kit's JS, which isn't shown. The lane either folds it into that file or loads it beside it.
4. **Kit CSS for ↗ and the hint.** It goes in `app/admin/kit-css.php` (file not shown), so I give a CSS block to place, not a diff. Source: hand-off item 8.
5. **No change to `ControlAlarms::portalLine`.** It writes plain text for email and push, where a link already opens in the browser.
6. **No change to `ControlBoard::pausedHtml`.** Its own-page links already carry `data-peek-frame`.
7. **Gap, offered as a suggestion (CLAUDE.md #1334–#1336):** no attached file says which sign-in account belongs to each service.
   - The helper takes the account as a parameter, or reads it from `data-tv-account` on the link.
   - Where nothing supplies it, the title names the service only.
   - Where the account comes from (the service register's card, 0173 R2 "the key on its card") is for the session manager to confirm. I don't invent it.

---

## 2. Diffs

### `app/lib/OutsideLink.php` (new file)

```diff
--- /dev/null
+++ b/app/lib/OutsideLink.php
@@ -0,0 +1,95 @@
+<?php
+// app/lib/OutsideLink.php — outside links open in the browser, our own pages in Peek (CLAUDE.md #1344, Jason 2026-11-24;
+// handoffs/control-board-2026-11-24.md item 8). An outside link that needs a sign-in (QuickBooks, Railway, Google Cloud,
+// Cloudflare, AWS) opens in a new browser tab (target="_blank" rel="noopener") behind an ↗ titled with the service and the
+// account; our own pages carry data-peek-frame. The admin kit's script (website/js/tv-outside.js) does the same for links
+// drawn without this helper, and shows the one-time hint on phones. Words and links only: nothing about a person.
+declare(strict_types=1);
+
+final class OutsideLink
+{
+    /**
+     * Hosts of the services that need a sign-in => the service's name. A host matches itself or any sub-host
+     * ("app.qbo.intuit.com" matches "intuit.com"). The five services are CLAUDE.md #1344's; these hosts are a suggestion
+     * for the session manager to confirm against the service register (OutsideServices::COMPANIES, file not shown).
+     */
+    public const SERVICES = [
+        'console.cloud.google.com' => 'Google Cloud', 'cloud.google.com' => 'Google Cloud',
+        'qbo.intuit.com' => 'QuickBooks', 'intuit.com' => 'QuickBooks',
+        'railway.app' => 'Railway', 'railway.com' => 'Railway',
+        'cloudflare.com' => 'Cloudflare',
+        'aws.amazon.com' => 'AWS',
+    ];
+    /** Our own hosts (and any sub-host: www, staging). The request's own host counts too. */
+    public const OWN = ['traversence.com'];
+
+    private static function under(string $host, string $suffix): bool
+    {
+        return $host === $suffix || str_ends_with($host, '.' . $suffix);
+    }
+
+    /** Our own page: a path on this site ("/admin/…", "#…", "?…"), or an https link to one of our hosts. */
+    public static function isOwn(string $url): bool
+    {
+        $url = trim($url);
+        if ($url === '' || $url[0] === '#' || $url[0] === '?' || preg_match('#^/(?!/)#', $url)) return true;
+        if (!preg_match('#^https?://#i', $url)) return false;
+        $h = strtolower((string) parse_url($url, PHP_URL_HOST));
+        if ($h === '') return false;
+        $here = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
+        if ($here !== '' && $h === $here) return true;
+        foreach (self::OWN as $o) if (self::under($h, $o)) return true;
+        return false;
+    }
+
+    /** The sign-in service an outside link opens ('' when it isn't one of them). The longest matching host wins. */
+    public static function serviceOf(string $url): string
+    {
+        $h = strtolower((string) parse_url(trim($url), PHP_URL_HOST));
+        if ($h === '') return '';
+        $best = ''; $len = 0;
+        foreach (self::SERVICES as $suffix => $name) if (self::under($h, $suffix) && strlen($suffix) > $len) { $best = $name; $len = strlen($suffix); }
+        return $best;
+    }
+
+    /** The ↗'s title: "Opens in your browser: QuickBooks (account)"; the host when it isn't a sign-in service. */
+    public static function title(string $url, string $service = '', string $account = ''): string
+    {
+        $s = $service !== '' ? $service : self::serviceOf($url);
+        if ($s === '') $s = (string) parse_url($url, PHP_URL_HOST);
+        return 'Opens in your browser: ' . $s . (trim($account) !== '' ? ' (' . trim($account) . ')' : '');
+    }
+
+    /**
+     * A link drawn the one way: our own page in Peek; an https outside link in the browser with ↗; anything else (another
+     * scheme, "//host") as its words with no link, as ControlGauges::fix() already does.
+     */
+    public static function html(string $url, string $words, string $service = '', string $account = ''): string
+    {
+        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
+        $url = trim($url);
+        if (preg_match('#^(/(?!/)|[#?])#', $url) || ($url !== '' && preg_match('#^https://#i', $url) && self::isOwn($url)))
+            return '<a class="underline" href="' . $e($url) . '" data-peek-frame>' . $e($words) . '</a>';
+        if (!preg_match('#^https://#i', $url)) return $e($words);
+        $svc = $service !== '' ? $service : self::serviceOf($url);
+        $t = self::title($url, $svc, $account);
+        return '<a class="underline tv-out" href="' . $e($url) . '" target="_blank" rel="noopener" data-tv-outside'
+            . ($svc !== '' ? ' data-tv-service="' . $e($svc) . '"' : '') . (trim($account) !== '' ? ' data-tv-account="' . $e(trim($account)) . '"' : '')
+            . ' title="' . $e($t) . '" aria-label="' . $e($words . '. ' . $t) . '">' . $e($words)
+            . '<span class="tv-out-i" aria-hidden="true">↗</span></a>';
+    }
+}
```

The `@@ -0,0 +1,95 @@` count is approximate. The lane regenerates the header with `git diff` when the file is added.

### `app/lib/ControlGauges.php`

The hunk line numbers are estimates; the context lines are copied exactly.

```diff
--- a/app/lib/ControlGauges.php
+++ b/app/lib/ControlGauges.php
@@ -21,5 +21,6 @@
 
 require_once __DIR__ . '/PageCache.php';
 require_once __DIR__ . '/GaugeTasks.php';
+require_once __DIR__ . '/OutsideLink.php';
 
 final class ControlGauges
 {
@@ -560,12 +561,15 @@
-    /** Fix it links: this site or https only; one the map can't give stays as its words with no link. */
+    /**
+     * Fix it links: this site or https only; one the map can't give stays as its words with no link. Each is [label, href,
+     * outside]: outside is the service (or host) an outside link opens in the browser with ↗ (CLAUDE.md #1344), null for our
+     * own page (opened in Peek) or no link.
+     */
     private static function fix(array $f): array
     {
         $out = [];
         foreach ($f as $x) {
             if (!is_array($x) || !isset($x[0])) continue;
             $h = isset($x[1]) && is_string($x[1]) && preg_match('#^(/(?!/)|https://)#', $x[1]) ? $x[1] : null;
-            $out[] = [(string) $x[0], $h];
+            $out[] = [(string) $x[0], $h, $h !== null && !OutsideLink::isOwn($h) ? (OutsideLink::serviceOf($h) ?: (string) parse_url($h, PHP_URL_HOST)) : null];
         }
         return $out;
     }
```

The drawer that draws `fix` (in `control-console.php` or the gauge panel's JS, file not shown) should call `OutsideLink::html()`. Failing that, the kit script below upgrades the link when it's drawn.

### `website/js/tv-outside.js` (new file; fold it into the kit's JS if the lane prefers, file not shown)

```diff
--- /dev/null
+++ b/website/js/tv-outside.js
@@ -0,0 +1,104 @@
+// website/js/tv-outside.js — outside links open in the browser (CLAUDE.md #1344; handoffs/control-board-2026-11-24.md item 8).
+// Every outside http(s) link on an admin page gets target="_blank" rel="noopener", an ↗ and a title with its service and
+// account (data-tv-account when the page gives it). Our own pages are left as they are (Peek's links carry data-peek-frame).
+// On a phone, the first outside tap shows a one-time hint; it closes with ×, Esc or a click outside.
+(function () {
+  'use strict';
+  var SEEN = 'tv-outside-hint-seen';
+  var PHONE = '(max-width: 767px)';
+  // the same hosts as OutsideLink::SERVICES (app/lib/OutsideLink.php): keep them in step
+  var SERVICES = [['console.cloud.google.com', 'Google Cloud'], ['cloud.google.com', 'Google Cloud'], ['qbo.intuit.com', 'QuickBooks'],
+    ['intuit.com', 'QuickBooks'], ['railway.app', 'Railway'], ['railway.com', 'Railway'], ['cloudflare.com', 'Cloudflare'], ['aws.amazon.com', 'AWS']];
+
+  function under(h, s) { return h === s || h.slice(-(s.length + 1)) === '.' + s; }
+  function hostOf(a) { try { return new URL(a.getAttribute('href'), location.href).hostname.toLowerCase(); } catch (e) { return ''; } }
+  function own(h) { return h === '' || h === location.hostname.toLowerCase() || under(h, 'traversence.com'); }
+  function serviceOf(h) { var best = '', n = 0; SERVICES.forEach(function (s) { if (under(h, s[0]) && s[0].length > n) { best = s[1]; n = s[0].length; } }); return best; }
+  function seen() { try { return localStorage.getItem(SEEN) === '1'; } catch (e) { return true; } }   // no storage: never nag
+  function markSeen() { try { localStorage.setItem(SEEN, '1'); } catch (e) {} }
+
+  function mark(a) {
+    if (a.hasAttribute('data-tv-outside-done')) return;
+    var href = a.getAttribute('href') || '';
+    if (!/^https?:\/\//i.test(href) || a.hasAttribute('data-peek-frame')) return;
+    var h = hostOf(a); if (own(h)) return;
+    a.setAttribute('data-tv-outside-done', '1');
+    a.setAttribute('data-tv-outside', '');
+    a.setAttribute('target', '_blank');
+    var rel = (a.getAttribute('rel') || '').split(/\s+/).filter(Boolean);
+    if (rel.indexOf('noopener') < 0) rel.push('noopener');
+    a.setAttribute('rel', rel.join(' '));
+    var svc = a.getAttribute('data-tv-service') || serviceOf(h);
+    if (svc && !a.getAttribute('data-tv-service')) a.setAttribute('data-tv-service', svc);
+    var acct = (a.getAttribute('data-tv-account') || '').trim();
+    if (!a.getAttribute('title')) a.setAttribute('title', 'Opens in your browser: ' + (svc || h) + (acct ? ' (' + acct + ')' : ''));
+    if (!a.querySelector('.tv-out-i')) { var i = document.createElement('span'); i.className = 'tv-out-i'; i.setAttribute('aria-hidden', 'true'); i.textContent = '↗'; a.appendChild(i); }
+    a.classList.add('tv-out');
+  }
+  function upgrade(root) { (root.querySelectorAll ? root : document).querySelectorAll('a[href]').forEach(mark); }
+
+  var open = null;
+  function close() {
+    if (!open) return;
+    open.remove(); open = null;
+    document.removeEventListener('keydown', onKey, true);
+  }
+  function onKey(e) { if (e.key === 'Escape') close(); }
+
+  function hint(a) {
+    markSeen();   // one time only, however it closes
+    var svc = a.getAttribute('data-tv-service') || hostOf(a);
+    var acct = (a.getAttribute('data-tv-account') || '').trim();
+    var wrap = document.createElement('div'); wrap.className = 'tv-out-hint-back';
+    var box = document.createElement('div'); box.className = 'tv-out-hint'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true');
+    box.setAttribute('aria-label', 'Opens outside Traversence');
+    var x = document.createElement('button'); x.type = 'button'; x.className = 'tv-out-x'; x.setAttribute('aria-label', 'Close'); x.textContent = '×';
+    var p = document.createElement('p');
+    p.textContent = svc + ' opens in your browser, outside Traversence.' + (acct ? ' Sign in there as ' + acct + ' if it asks.' : ' Sign in there if it asks.')
+      + ' This page stays open. Links marked ↗ always open this way.';
+    var go = document.createElement('button'); go.type = 'button'; go.className = 'tv-out-go'; go.textContent = 'Open ' + svc + ' ↗';
+    var stay = document.createElement('button'); stay.type = 'button'; stay.className = 'tv-out-stay'; stay.textContent = 'Stay here';
+    box.appendChild(x); box.appendChild(p); box.appendChild(go); box.appendChild(stay); wrap.appendChild(box);
+    x.addEventListener('click', close); stay.addEventListener('click', close);
+    go.addEventListener('click', function () { window.open(a.href, '_blank', 'noopener'); close(); });
+    wrap.addEventListener('click', function (e) { if (e.target === wrap) close(); });
+    document.addEventListener('keydown', onKey, true);
+    document.body.appendChild(wrap); open = wrap; go.focus();
+  }
+
+  document.addEventListener('click', function (e) {
+    var a = e.target && e.target.closest ? e.target.closest('a[data-tv-outside]') : null;
+    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) return;
+    if (seen() || !window.matchMedia || !window.matchMedia(PHONE).matches) return;   // desktop, or hint already shown: the link opens as it is
+    e.preventDefault();
+    hint(a);
+  }, true);
+
+  function start() {
+    upgrade(document);
+    // the board redraws every 30 seconds and panels open later: links drawn after load are upgraded too
+    if (window.MutationObserver) new MutationObserver(function (ms) {
+      ms.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) { if (n.matches && n.matches('a[href]')) mark(n); upgrade(n); } }); });
+    }).observe(document.body, { childList: true, subtree: true });
+  }
+  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
+  window.TvOutside = { upgrade: upgrade, serviceOf: function (u) { try { return serviceOf(new URL(u).hostname.toLowerCase()); } catch (e) { return ''; } } };
+})();
```

**CSS for `app/admin/kit-css.php`** (file not shown, so this is not a diff). The lane places it there and swaps the literal colours for the kit's palette tokens: per 0171 §8, never literals, and the token names aren't in the attached files.

```css
.tv-out-i { display: inline-block; margin-left: .2em; font-size: .85em; text-decoration: none; }
.tv-out-hint-back { position: fixed; inset: 0; background: rgba(0,0,0,.35); display: flex; align-items: flex-end; justify-content: center; z-index: 1000; }
.tv-out-hint { position: relative; width: 100%; max-width: 480px; background: #fff; padding: 16px 16px 20px; border-radius: 12px 12px 0 0; font: inherit; }
.tv-out-x { position: absolute; top: 6px; right: 8px; width: 30px; height: 30px; border-radius: 50%; border: 0; background: transparent; font-size: 20px; }
.tv-out-hint p { margin: 0 32px 12px 0; }
.tv-out-go, .tv-out-stay { min-height: 44px; padding: 0 14px; margin-right: 8px; border-radius: 8px; }
```

- The z-index must sit above the board's side panel. The 11-25 hand-off notes that `TvAsk` dialogs open behind it.
- The `.tv-out-go` fill should use the kit's brand-brown filled button (kit-buttons #275).

---

## 3. Tests

### `tools/control-board-tests/outside-links.php`

```php
<?php
// tools/control-board-tests/outside-links.php — outside links open in the browser, our own pages in Peek (CLAUDE.md #1344;
// handoffs/control-board-2026-11-24.md item 8). Usage: php outside-links.php <root>  (root holds app/ and website/)
declare(strict_types=1);

