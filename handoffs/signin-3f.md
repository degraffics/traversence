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

## Built (2026-11-22, session_01CXLi5jNQCN3zFZ2Fh3qUNf)
**Built on Sync 13:09 UTC 2026-11-22 (5237f07).** `signin-3f.zip` in `releases/inbox/`, built by `signin-3f/build-3f.sh`
from `signin-3f/src/3f.patch` (replayable on a newer mirror; a hunk that no longer applies stops it). Zip check clean
(`--callers-checked fmt`: `TvTime.fmt` takes an optional third argument at the end; every old call is unchanged).
Files (16): `website/login.php`, `website/admin/login.php`, `website/admin/staff.php`, `website/admin/api/pulse.php`,
`website/two-step.php`, `website/js/code-link.js`, `website/js/tv-time.js`, `app/ui/header.php`,
`app/ui/social-buttons.php`, `app/ui/pulse-ui.php`, `app/ui/faq.md`, `app/lib/Pulse.php`, `app/lib/StaffLogin.php`
(`codeUntil`, added), `app/lib/Mailer.php`, `app/lib/staging-tables.php`, `app/lib/StaffMailHelp.php` (new).

### Each behaviour and its source
| Behaviour | Source |
|---|---|
| The chosen tab: brown words, brown underline, card colour; the other quiet (muted words on a darker sand) | A7 ("brown, underline", screens proposal; Jason "Approve" 2026-11-22) |
| Tabs and the Sign in / Create account buttons in sentence case; Forgot password? brand brown, underlined, the UI face (`--tv-link`) | B7 ("sentence case and the one link style: yes") |
| Google, Microsoft, Facebook: full-width white buttons, the logo and "Continue with …" (sign-up page: "Sign up with …"); the same in the guests' join panel | A9 ("words beside") |
| No cart, bell, site search or admin search on /login, /register, /forgot-password, /reset-password, /admin/login, /admin/setup (one flag, `$tv_signin`); the menu stays | A10 ("left off the sign-in pages"; the brief's four pages plus register and reset-password, the same flow) |
| Code box: placeholder "6 digits"; "It works until 6:42 AM MST" (`<time data-tv-clock>`, viewer's zone); digits only kept as typed or pasted ("123 456", "123-456"); the 6th digit sends the form with its button's spinner | A11; CLAUDE.md "Times where they happened"; principle 1 |
| The email's **Sign in with this code** fills the code and sends it by itself (`data-tv-code` boxes only; other code boxes keep "tap Sign in") | A11 and the brief's pitfall ("the system does it", principle 1) |
| Code email: "Tap or click the code to select it, then copy."; its button note "Signs you in with this code, on the device where you started." | A12; words follow the behaviour (playbook §2) |
| Two-step sign-in (/two-step.php) gets the same code box | A11 ("the code step"; the brief's "find the member site's code step": it's this page) |
| **Can't get the email?** on the staff code step: opens in place; ×, Esc, a click outside (only while empty) close it; name + a phone or other email; CSRF; tied to the sign-in step; limits; honest words after | B8; 0100's note; CLAUDE.md "Everything that pops open closes", "never a dead end" |
| The password step's foot: "they'll hand you a new setup link" (not "by email"), how to reach Can't get the email?, and support@ | B8's guard rails ("the live text … changes to match") |
| An owners' Pulse case per request (`staffmail:<id>`, Security, Alert); a second request while one is open updates it | B8 ("one case per request") |
| The case shows first: login, role, work email now, last sign-in, name and contact given "not verified", when asked (device's zone) and from what | B8 guard rails |
| Change work email (form; `StaffLogin::workEmail` checks it), Reset the mailbox password (`CPanelMail`, our domain only), New setup link (`StaffLogin::reset`): each asks "Have you confirmed this is them?" (danger) first | B8 guard rails |
| Non-owners who see Security (executives) see those buttons greyed with "Only an owner can do this: it changes how someone signs in"; cPanel not connected greys the mailbox reset with its reason | B8 ("gated to owners"); CLAUDE.md "greyed out with the reason" |
| The setup link and the new mailbox password shown to the owner once: hidden until Show, Copy, Done | B8 ("show it to the owner to hand over"); CLAUDE.md "one-time secrets stay hidden until asked for" |
| Every action in the staff log and the case's notes; the old address told of an email change; the mailbox told of a reset | B8 guard rails |
| Close it (with what happened) closes the request and the case | B8 (a case needs a way to end; the request's own state) |
| Staff with no authenticator app: one bar at the foot of admin pages, **Add it** (/admin/staff.php#my-app), **Not now** and ×, remembered per staff login | B8 ("one dismissable notice … remembered per staff login") |
| Pulse step buttons (shared): an optional options element `{confirm, yes, danger, off}`; the API's `once` | the brief ("one shared change, so claim it") |

### Kept, moved, dropped (playbook §3)
- Social buttons: logo tiles → worded buttons; each still links to the same `/api/auth/<p>.php?start=1&next=…`; the
  guests' join panel and the sign-up page take the same buttons. Kept: hover ring, order, "or" line.
- The tabs: both still switch the form, keep the typed email, `?tab=create`; only the look changed.
- The code step: Send a new code, Not you? Start again, the authenticator hint, the wrong-browser message for a code link:
  all kept. The email's link no longer waits for a tap (A11).
- Pulse steps without options: unchanged (pressed: a Site health form step opens its form, no question, no errors).
- Dropped: nothing.

### Admin's test (principle 5), one line per new state
- A staff member asked for help → Jason sees "Can't get their work email: TRV-0002 · Sam Rivera" in Pulse (Security);
  it means someone with the right password says they can't reach the mailbox; he reads the first step, calls them, then
  presses the button that fits (or Close it if it wasn't them).
- He presses New setup link → it asks first; then the link, hidden, with Show, Copy and Done; he hands it over by phone.
- He presses Reset the mailbox password with cPanel off → the button is greyed and says why (cPanel not connected).
- A non-owner opens the case → the buttons are greyed: "Only an owner can do this".
- Signed in with no authenticator app → the bar at the foot: Add it, or Not now / × (gone for good).

### Proof (`tools/design-tests/signin-3f/run.cjs` with `seed.sql`, on the harness; `signin-3f/proof/`)
128 checks, 128 passed, 1280 and 390: every flow above pressed in a real browser (the run's output is
`proof/run-output.txt`; screenshots `proof/3f-*.png`, `proof/join-390.png`). The cPanel reset was pressed against the
harness's stand-in (the real call goes through `CPanelMail`, held on staging by `StagingGate`); mail was captured, not
sent. The real run happens on staging and live.

### Open question for the summary (no separate pop-up, per the brief)
A sole owner can be reset only from the server (`app/scripts/staff-reset.php`). Recommendation: keep a second owner or
admin as the safety net; B8 shows the setup link to the owner to hand over, never only emailed.
