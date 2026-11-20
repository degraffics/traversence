# A listing's page sees who's signed in; Back where there's no route (2026-11-20): built, waiting

Design and trips session. Built on **Sync 18:05 UTC (c6eff9a)**, after #238 (faq.md rebuilt on it) by `handoffs/listing-signin/build-ls.py`.

## Why
Jason, 2026-11-20, on https://traversence.com/listing/view.php?id=8916: "Where is the top command bar for the user? Why is
there no way back to where I came from". Asked in the pop-up (reviewed by the session manager): he was signed in ("Yes,
signed in"), and Back with no route should go "back to where they came from".

## Causes and fixes
1. `listing/view.php` doesn't load the bootstrap. It starts its own read-only session with only the name, so it read
   PHP's default session folder, not the site's (`app/storage/sessions`, bootstrap.php, 2026-10-10). Since then every
   listing page has shown a signed-in person as a guest: no account bar (decisions/0055), no Back inside it, no admin
   Edit, owner's Manage, team peek, or Claim knowing it's yours. Fixed by setting the site's folder before its read-only
   start. It's the only page that starts its own session (checked across the mirror).
2. `js/tv-trail.js`: a page that carries the route (Let's Explore's and the pages reached from it), opened with none in
   its tab, shows **‹ Back** to the same-site page it came from (`document.referrer`), named by its section where known
   ("Back to your account", "Back to the admin"), in the account bar when signed in, else the slim line. Nothing shows
   from outside the site. Other sections are unchanged (0058's 2026-11-12 note: the route only for Let's Explore). It's
   recorded as 0058's note of 2026-11-20.
3. FAQ: "How do I get back to where I was?"

## Tests (harness, rendered, 18 of 18 at 1280 and 390)
Signed in, the account bar is on a listing. From the dashboard: "‹ Back to your account", in the bar. A guest from the
admin: "‹ Back to the admin" in the slim line. From outside the site: no Back. The Explore route still works, with ▾.
Get Local unchanged. No sideways scroll. Back goes to the page. No script errors.

## Not a bug (re-tested)
The Link icon hides while ⋯ is open and comes back after every way of closing it. A first test said otherwise because
the page opened scrolled, and a scroll closes ⋯.
