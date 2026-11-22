# 3f sign-in: the build brief from its survey (Design and trips, 2026-11-22)

Written by session_01CXLi5kMXdmwb4aE7hWkGeU at a hold. Jason said "go ahead with 3f" (2026-11-22). The survey is done and
no code is written yet. This session's context grew large, so a fresh session builds it, per Jason's rule of 2026-11-20
("hand off to new session so we can reduce token use without logic loss"). Read CLAUDE.md, `builder-playbook.md`,
`design-and-trips-2026-11-22.md` (the queue) and then this. **Build on the newest sync: 5237f07 (13:09 UTC) or later.**

## Approved scope (all approved; nothing here needs a new question)
Source: `handoffs/screens-proposal-2026-11-22.md` (A rows and B), Jason's answers 2026-11-22, and decisions/0100's note
"a lost work email is never a dead end".

| # | What | Where it lives today |
|---|---|---|
| A7 | Sign-in tabs: the chosen tab is pale and the other solid orange, so Create account looks chosen. Mark the chosen tab (brown, underline) and keep the other quiet | `website/login.php` l.100–104: `.au-t` (solid #C75A28) and `.au-t[aria-selected=true]` (pale #E3D3AC) |
| A9 | Google, Microsoft and Facebook buttons have no words. Use "Continue with Google"… (words beside the icon, or at least labels) | `app/ui/social-buttons.php` `tv_social_buttons($verb = 'Continue with', $next)`, `.tv-social` l.32 |
| A10 | Sign-in pages: the cart and bell look switched off with no reason, and the admin search shows before you're signed in. Leave them off the sign-in pages (nothing to show yet) | `app/ui/header.php` l.374–379 (`#tv-cart`, `#tv-bell`); the admin page `website/admin/login.php` uses `tv_page_start(..., ['app' => true])` (`app/ui/app-shell.php`): find where the shell draws its search and bell, and skip them for sign-in pages (login.php, admin/login.php, admin/setup.php, forgot-password.php) with one flag, not per-page CSS |
| A11 | Code step: placeholder "000000" becomes "6 digits"; "It works for 10 minutes" becomes the clock time in the viewer's zone ("until 4:42 AM MST": CLAUDE.md "Times where they happened", `TvTime`/`data-tv-time`); signs in at the 6th digit (submit the form when 6 digits are in); a pasted code with spaces is accepted (the server already strips spaces: `StaffLogin::checkCode` l.~480; the box's `maxlength="7"` must allow "123 456" → set 7+ or strip on input) | `website/admin/login.php` code form; the member site's code step (find it: `login.php` "Check your email" step and its API); `js/code-link.js` fills `#code=`; keep it working |
| A12 | Code email: "Click the code once to select it, then copy." becomes "Tap or click the code to select it, then copy." | `app/lib/Mailer.php` l.68 (`sendCode`) |
| B7 | Sentence case and the one link style: "SIGN IN"/"CREATE ACCOUNT" (tabs `text-transform:uppercase`, the `.au-go` buttons `uppercase tracking-wide`) and "Forgot password?" (bold serif, l.91) use the site's one link style | `website/login.php` |
| B8 | A lost work email. See below | new |

## B8: a lost work email (decisions/0100 note; the session manager's guard rails, screens-proposal "For the 3f build")
- **On the staff code step** (`admin/login.php`, `$who` set): **Can't get the email?** opens a short form: name, and a
  phone or other email to reach them. Unauthenticated, so: CSRF; tied to the step's staff login (`$_SESSION['tv_staff_step']`);
  rate-limited; honest words after sending ("An owner will contact you at … to confirm it's you"). It replaces the dead
  end at the foot ("Forgot your password, or can't get the email? Ask an owner…", l.~80): those words change to match
  ("an owner hands you a new setup link", not "by email").
- **It becomes an owners' Pulse case** (one case per request, e.g. key `staffmail:<id>`; one row per kind of chore per
  CLAUDE.md Pulse). Store requests in a new table (a migration, add-only, guarded; name its class in
  `app/lib/staging-tables.php`, R14). Find how other items are fed (the `$add(key, team, level, title, body, at, url,
  label…)` feed in `app/lib/Pulse.php` ~l.100–300) and how steps are given (`play` with `['do', what, label]` /
  `['form', what, label, fields]`, ~l.1170–1330), and handle the actions in `Pulse::manage()` (l.704), gated to owners
  (`Access::can`).
- **The case shows, before any button:** the staff login named, its current email, its last sign-in (`last_sign_in_at`),
  and the contact the requester gave, marked **not verified**.
- **Buttons, each confirmed first** ("Have you confirmed this is them?", danger): **Change work email**
  (`StaffLogin::setEmail`, a form with the new address; `workEmail()` validates the domain), **Reset the mailbox
  password** (`CPanelMail::setPassword($email, $pw)`, our own domain only; show the new password once to the owner),
  **New setup link** (`StaffLogin::reset()`, which returns `url`; **show it to the owner to hand over**, never only emailed).
  Pulse's `do`/`form` buttons have no confirm today: `app/ui/pulse-ui.php` draws them (l.~1215, `dataset.doWhat`,
  `dataset.formStep`) and handles them (l.~1367 `api({do:'manage'…})`, l.~1375 form). Add an optional 4th element (e.g.
  `{confirm, yes, danger}`) and gate with `TvAsk.gate(btn, msg, {yes, danger:true})`: one shared change, so claim it.
- **Every action is logged** (`StaffLogin::log` and the case's notes), and **the old work email gets a notice** that the
  change was made (Mailer, a system email: costs nothing).
- **After sign-in:** staff with no authenticator app (`StaffLogin::hasApp`) get one dismissable notice to add it (find
  where the admin shows notices; dismissal remembered per staff login).
- **The open question goes in the release summary only** (no separate pop-up): a sole owner can be reset only from the
  server. The recommendation is to show the setup link to the owner (B8 does) and keep a second owner or admin as the
  safety net.
- decisions/0100's note already records B8. Add a dated "built as" line when it's sent.

## Pitfalls found in the survey
- `StaffLogin::reset()` ends the person's sessions and blanks the password and the authenticator: say so on the button.
- `mailCode` refuses a new code within 30 seconds; Resend's words already handle it.
- `code-link.js` fills the box from `#code=` and asks the person to tap Sign in. With auto-submit at 6 digits, decide
  whether a filled-in code submits by itself (principle 1: the system does it). The code works only in the browser where
  the sign-in started; keep that message.
- The member and staff sign-ins are separate pages and flows: test both.
- FAQ: update the sign-in entries (grep `faq.md` for "code", "authenticator", "Staff sign-in") and add "I can't get into
  my work email".

## Tests to write (the builder playbook's standard)
Rendered at 1280 and 390 on the harness (`design-harness/README.md`; Tailwind is served from the npm package
`@tailwindcss/browser@4` by Playwright's route, as in `tools/design-tests/listing-page/run.cjs`): the tabs' state, the
social buttons' words, no cart, bell or admin search on the four sign-in pages, the code box (placeholder, clock time,
auto-submit, a pasted "123 456"), "Can't get the email?" sent and its case opened with every guard rail, each button
pressed (confirm first, then the action, logbook, the old-email notice), and the authenticator notice dismissed and
remembered. Every pop-up closes with ×, Esc and a click outside.

## State at this hold
- Live: #249 card-fixes, #250 listing page (verified in 5237f07). PRs #1247 and #1249 are merged.
- No 3f claims are held and no branch has 3f code (`claude/signin-3f` is only a checkout of main).
- Queue after 3f (`design-and-trips-2026-11-22.md` §3): own quick searches counted; the 5px gap above the hero; then the
  old queue.
