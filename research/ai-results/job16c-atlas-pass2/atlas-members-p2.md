# Platform atlas: Members

`research/platform-atlas/members.md`. **Draft for human review.**

**Scope and visibility.** This entry covers the signed-in member area: `/user/*` pages, the `/user/api/*` endpoints and the libraries attached to them. The code is only partly shown.

- **Pages not shown:** `/user/dashboard.php` (the Dashboard itself) and `/user/profile.php`.
- **Libraries not shown:** `MemberPulse`, `PulseAct`, `Notify`, `People`, `Links`, `Marketing`, `BizMessages`, `Roles`, `MemberPosts`, `WebPush`, `Mailboxes`, `AccountDeletion`, `Standing`, `Trips` and `Journeys`.
- **Truncated documents:** decisions 0172, 0137 and 0171.
- **Attached but outside this area:** `PlaceProfile.php` (Census place figures for guides).

---

## (a) What each person can do, page by page

### Resident and traveler (signed-out guest)

- **Trips:** `/user/trips/` shows the one trip kept on the device. It offers an account, which keeps any number of trips and moves the device trip into the account at sign-in (`user/trips/index.php:44-46`).
- **Posts:** `/user/post.php?id=` shows a post if it is shared with them. Otherwise it offers "Sign in to react, comment" (`user/post.php:15,22`).
- **Linking:** guests can check Link states. Linking itself needs sign-in (`user/api/links.php:3,34`).
- **Account notice by link:** a member whose access was revoked can use `/user/notice.php?t=` to read the notice and ask for a review without signing in (`user/notice.php:14-18`; `user/api/notice.php:2-3`).

### Member (signed in)

**Dashboard (`/user/dashboard.php`, not shown).** Its parts are reached through these endpoints:

- **Profile.**
  - Display name, bio and the public/private switch: `user/api/profile.php:3-4`.
  - Per-field publish or hide, avatar and cover photos, and photo framing: `member-profile.php:3-10`.
  - The email is never part of the profile (`Profile.php:2-3`).
  - Name rules: 2–40 characters, no phone numbers, reserved words blocked (`Profile.php:16-27`).
- **Ways to sign in.**
  - Google, Apple, Microsoft or Facebook can be connected or disconnected (`SignInLinks.php:16`; `user/api/sign-ins.php:2-3`).
  - One account per provider (`SignInLinks.php:42,51`).
  - A provider account linked to someone else is never moved (`SignInLinks.php:11`).
- **Your tools and consent:** tool consent on/off and verification status (`user/api/consent.php:3-4,17`).
- **Link button and feeds.**
  - Link, unlink, mute and unmute (`links.php:6`).
  - Feeds: listings, discovery, community and marketplace (`links.php:5`).
  - Linking must be activated once, with consent (`links.php:43-46`).
- **Businesses I've linked.**
  - Subscribe with a frequency, unsubscribe with "ask again in…", choose which contact fields to share, or unlink (`marketing.php:5-8`).
  - A master "no marketing" switch (`marketing.php:9`).
  - A business must be linked first (`marketing.php:46`).
- **Address Book.**
  - Connect, respond, block, message, share card fields, invite, and groups of people (`people.php:3-11`).
  - Person search by name (`people.php:36`).
  - Private contacts need the Address Book consent (`people.php:84-86`).
  - The other side is "never told and never sees it"; limit 2,000 contacts (`Contacts.php:12-14,21`).
- **Messages with businesses.**
  - Inbox and threads, send, mute message types, report a mislabelled message (`business-messages.php:4-9`).
- **Pulse.**
  - The stream for the current context (`pulse.php:2-4`).
  - Row actions: reply, forward, report, block, mute, add to Address Book, Keep (`pulse-act.php:8-19`).
  - Messages open in place and the bell list shows Alert or above (`pulse-msg.php:6-7,23`).
  - The bell counts across sources (`bell.php:4`).
  - Own marks: Follow up, importance, tags, pin, folder, archive. These are "never shown to the sender" (`marks.php:2-3`).
  - Saved views, up to 30 (`PulseViews.php:10`).
- **Communications.**
  - Per kind × channel choice (Pulse, email or push), quiet hours, weekly digest (`comms.php:5-9`).
  - Older notice switches and delivery choice (`notices.php:3-5`; kinds in `Notices.php:14-25`).
  - Push on each device, including a test send (`push.php:4-9`).
  - Outside mailboxes in Pulse: connect, read, reply, make a case (`mailboxes.php:4-8`).
- **Saved searches:** up to 20, with "new" counts (`saved-searches.php:3-6,36`).
- **Referrals:** referrals to confirm, confirmed and sent (`referrals.php:13-45`).
- **My QR codes / GO Codes:** create, update, switch on/off, stats, and a free trial "until billing opens" (`qr.php:3-8`).

**Moments.**

- Post with audience, window, Keep, react, comment, report, and preview as others see you (`posts.php:3-20`).
- Posts "last 3 or 7 days unless kept" (`posts.php:41`).
- Media is served only to people who may see the post (`post-media.php:22-23`).

**Pages under `/user/`.**

| Page | What it does |
|---|---|
| `/user/accounts.php` | Every context you can act as; listings grouped by Nexus, with search past 6 (`accounts.php:2-6,94`). |
| `/user/trips/`, `trip.php` | Private trips: rename, take a stop out with Undo, Send to Maps, Delete (kept 30 days, Put back) (`trips/index.php:2-6,41,69`). |
| `/user/journeys/` | Become a contributor: needs a public profile, 18+ and a confirmed email (`journeys/index.php:94-100`). Then write, edit or delete journeys and accept co-author invites (`:72-83,104-121`). |
| `/user/orders.php` | Orders sent to your confirmed email, plus your artwork with "Ask to reorder" (`orders.php:2-4,23-37,66`). |
| `/user/domains/` | Domains: renew, auto-renew, DNS, transfer, wallet (`domains/index.php:2-5`). |
| `/user/notice.php` | Account notice: three steps (Warning → Limited → Access revoked), confirm or ask for a review (`notice.php:84-99`). |
| `/user/delete-account.php`, `restore-account.php` | Close (30 days) or delete now; restore from the email link (`delete-account.php:30-41`; `restore-account.php:36-41`). |
| `/user/staff-link.php` | Confirm a staff-login link; "This account still can't open the admin side" (`staff-link.php:39-41`). |
| `/user/transfer.php` | Accept or decline taking over a business (`transfer.php:2-4`). |

**AI Assist (`/ai/`).** Answers "only from what we have researched". Unknown subjects are queued for research and followed up by email (`AiAssist.php:2-8,136-157`). Needs a paid plan (`:39`).

### Business (a member acting for a listing)

- **Switch context.** Switch from `/user/accounts.php` or `context.php` (`context.php:4`).
- **Manage the team.**
  - Owners grant and revoke roles.
  - Managers may add General.
  - Anyone may step down (`context.php:7-9,33`).
- **Business inbox** (`business-messages.php:10-17`):
  - read threads;
  - reply by type (sales, support, inquiry, relationship);
  - send an offer to subscribers;
  - re-ask people whose wait has passed;
  - answer guest questions (emailed if the guest gave an email);
  - mark guest questions as spam or unreachable.
- **Insights.** Linked/subscriber counts only, for the listing's staff (`marketing.php:4,24-27`).
- **Comments on the listing arrive in Pulse.**
  - Reply "as yourself, named with" the business.
  - Owners and Managers can Hide or Show the comment.
  - Report (`CommentNotices.php:6-9,64-79`).
- **Bell counts** include business messages and guest questions for listings the person helps run (`bell.php:5-6,36-43`).
- **Ownership handover.** Accepting a transfer makes you Owner. The previous owner stays as Manager. Their card comes off the plan at the end of the paid period (`transfer.php:62-64`).

---

## (b) How it works underneath

**Request pattern.** Every endpoint follows the same steps:

1. `require app/bootstrap.php`.
2. `Auth::currentUser()` or `requireAuth()`; signed out gets 401.
3. GET returns a `csrf` token; every POST calls `Response::requireCsrf()`.
4. Example: `profile.php:11-20`.

**Readiness checks.** Many endpoints guard on a library's `ready()` or `tableExists()`. Some return text such as "Run app/migrations/… first" (`links.php:15`, `marketing.php:18`, `people.php:21`, `consent.php:12`, `saved-searches.php:17`). Others return a softer "switch on with the next update" (`push.php:21`, `mailboxes.php:20`).

**Context.**

- `Roles::current()` and `Roles::contexts()` decide whose Pulse and tools are shown (`pulse.php:18-23`, `context.php:23-45`).
- The context kinds seen are `member`, `org`, `steward` and `admin` (`accounts.php:81`).
- A staff login's bell is the admin Pulse's count, cached for 900 s (`bell.php:23-31`).

**Pulse, the inbox.**

- `MemberPulse::stream` is decorated by `PulseAct::decorate` (`pulse.php:23`).
- Dispatcher messages live in `comms_messages` and are read by `Notify::one/body/routeOf` (`pulse-msg.php:23,32-38`).
- Each message body is drawn by the library that owns its kind. Example: `CommentNotices::pulseBody` for `biz.comment`, `comment.reply` and `content.comment` (`CommentNotices.php:2-4,27`).
- Row actions re-read the thing from its own tables and "never trust the row's words" (`pulse-act.php:20-21`).
- Marks: `PulseMarks` (`marks.php:13`). Saved views: `pulse_views` table (`PulseViews.php:25`).

**Notices.**

- `Notices::send` sends once per person, kind and thing, through `Notify::send('notice.<kind>')` (`Notices.php:40-46`).
- Email goes through SES from `notices@send.traversence.com` (`Notices.php:88-91`).
- The weekly digest job is `Notices::digest` (`:100-124`).
- Opening Pulse marks every notice seen (`:144-148`).

**Verification.**

- `MemberVerification::verified` = email confirmed and age confirmed (`MemberVerification.php:66-70`).
- Verification is required only for contributor, owner, steward and staff roles (`:19-24`).
- The phone check is shown as "coming" (`:57`).

**Account deletion.**

- Deletion needs `DELETE` typed and a sign-in within the last 15 minutes (`delete-account.php` API `:15-16`).
- Default is a close with a grace period; `now` deletes immediately (`:19-40`).
- The restore token is a 40-hex string, and the restore page sends `Referrer-Policy: no-referrer` (`restore-account.php` page `:10,13`).

**AI Assist.**

- `AiAssist::knowledge` builds a brief from Topics, vetted heritage facts (public and story tiers only) and the place's published guide (`AiAssist.php:60-98`).
- The prompt forbids the model's own memory and forbids locating STORY ONLY items (`:105-108`).
- Each ask is kept in `ai_asks` with its cost and who paid (`:50-52`).

---

## (c) Decisions

| ADR | What it rules here |
|---|---|
| 0010 | AI-linked identity is opt-in, default off (PRD "Consent dependency"). |
| 0026 | The DSAR export and erasure pipeline. Export is still Coming (pages.md §8). |
| 0047 | The Link button, Address Book, business messages, and marketing per business (`links.php:2`, `people.php:2`, `marketing.php:2`). |
| 0049 | Tool activation and consent before first use (`consent.php:2`, `links.php:43`). |
| 0052 | Private contacts (§2) and guest questions (§5) (`Contacts.php:10`, `business-messages.php:16`). |
| 0053 §6 | Saved searches (`saved-searches.php:2`). |
| 0055 §4, §5, §8 | Context switching, Pulse, team (`context.php:2`, `pulse.php:2`). |
| 0056, 0058 | Journeys and contributors (`journeys/index.php:2`). |
| 