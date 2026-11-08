# Proposals

Decision 0145 §2. Improvements drawn from the audit's findings, each with its evidence and verdict. Jason approves,
rejects or rewords each one; nothing changes until he does. P1–P6 decided 2026-11-08.

**Verdicts:**
- **fix:** the page breaks a rule.
- **amend:** the rule should change.
- **missing:** no rule covers it.
- **conflict:** two rules disagree.

## From the first run (2026-11-08, mirror `08b8643`)

### P1. The server's PHP version: amend `CLAUDE.md` (verdict: amend)

`CLAUDE.md` says the live server runs PHP 8.0. But code on the live site uses the `never` return type, which needs
PHP 8.1. It is in `app/lib/Response.php`, which every endpoint uses, and in four other files. The site works, so the
server must run 8.1 or newer.

**Proposal:** the health report (Phase 2) reads the real version. `CLAUDE.md`'s rule and check R04 then name that version.

**Decided (Jason, 2026-11-08): approved.** The health report reads the real version; `CLAUDE.md` and R04 then name it.

### P2. Pages without the session or account bar (verdict: fix, or accept with a reason)

Seven pages draw without the session or the account bar (R05):
- `login.php`, `register.php`, `verify-email.php`, `email-revert.php`: sign-in pages, where no bar may be right. Signed
  in, they could send you on to your dashboard instead.
- `forgot-password.php`, `reset-password.php`: the same.
- `admin/chamber-builder.php`: an old admin page with no session at all.

**Proposal:** sign-in pages redirect signed-in visitors to the dashboard, and are listed as accepted exceptions. The
chamber builder is archived (it also has the only remaining browser dialogs, P3).

**Decided (Jason, 2026-11-08): approved.** Sign-in pages send signed-in visitors to their dashboard and are listed as
accepted exceptions; the chamber builder is archived.

### P3. The last browser dialogs (verdict: fix)

Three `alert()`/`confirm()` calls remain, all in `admin/chamber-builder.php` (R02). Archive the page, or move them to
`TvAsk`.

**Decided (Jason, 2026-11-08): approved.** `admin/chamber-builder.php` is archived (with P2).

### P4. Emoji in what pages show (verdict: fix)

29 places use emoji where the brand's icons belong (R10). Examples: 📍 and 🧭 on journeys and Discovery, and 👉 on the
order sign-off.

**Proposal:** swap them for `tv_icon()` in one tidy-up release.

**Decided (Jason, 2026-11-08): approved.** All 29 swapped for `tv_icon()` in one tidy-up release.

### P5. Endpoints without the security token (verdict: accept with reasons, or fix)

Six endpoints take a POST without the security token (R07). Five are public by design and should be listed as accepted
exceptions, each with its reason:
- `api/out.php`: tap counts;
- `api/short.php`: short links;
- `api/locate.php`: location;
- `api/qr.php`: QR images;
- `api/csp-report.php`: browser security reports.

`listing/api/referral/confirm.php` needs a look: it should have a token, or a reason it doesn't.

**Decided (Jason, 2026-11-08): approved.** The five are accepted exceptions with their reasons;
`listing/api/referral/confirm.php` gets the token unless the review finds a reason it can't.

### P6. The page tree (verdict: missing)

72 pages are on the server but in neither `routes.md` nor `archive.md` (R09).

**Proposal:** approve the generated `page-tree.md` as the page tree, replacing the hand-kept `routes.md`. Then mark each
unlisted page live, archived, or to remove.

**Decided (Jason, 2026-11-08): the page tree becomes the source of truth, without losing a route.**
1. The audit checks every route in `routes.md` against `page-tree.md` and lists any that aren't there.
2. Each of those is settled: it's a real page the tree missed (the tree is fixed), or it's gone (noted in `archive.md`).
3. Only when every route in `routes.md` is confirmed in the tree is `routes.md` archived.
4. The 72 unlisted pages are then marked live, archived or to remove, in one list for Jason to approve.

