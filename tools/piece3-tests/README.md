# Piece 3's tests (decisions/0179 rule 2; handoffs/piece3-check.md)

- `beforeafter.test.mjs` with `fixture-router.php`: `beforeafter.mjs` against a small site. It takes two before looks of
  the plain site, then one variant per case. A page made worse in each way must block with its rule: a member opens an
  admin page; a member's own page sends it to sign in; another account's email; a held name (0066); sideways scroll;
  overlap; the main action gone; the navigation gone; a control greyed; fewer cards; an unnamed page changed. A change
  the release names, and a counter that moves on its own, must pass. 19 checks.
  Run: `node tools/piece3-tests/beforeafter.test.mjs`. It needs `php` and Playwright's Chromium. If Node's ES-module
  loader can't find Playwright, link it into a `node_modules` above the repo.
- `reach_test.py`: `reach.py` on a mirror it makes. A class reached through a UI part; a link isn't a load; a comment
  isn't a use; endpoints aren't pages; a page's own helpers; a script by its tag; a release's pages and expected list.
  9 checks. Run: `python3 tools/piece3-tests/reach_test.py`
- `pipeline.php`: P3-2, the site's side, on MariaDB, with GitHub stubbed (`BeforeAfter::$askFn`, `$readFn`). 22 checks:
  - The switch off leaves everything as today.
  - A release waits for its before look, asked with its release, zip and blob.
  - GitHub not reached is asked again. Late is asked again once, then a Work queue ticket, never twice.
  - before-done goes on staging. On staging with its checks passed, the after look is asked.
  - A block withdraws the release and opens its Audit ticket.
  - A held pass, an incomplete look and a spent budget each wait for review. Minutes are counted once.
  - A withdrawn release's wait is dropped. Nothing happens on staging.
  Run: `php tools/piece3-tests/pipeline.php <root>/app`. It resets lv_main.
