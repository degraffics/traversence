# The Hometown Theater pilot: the touch-point map

*Owned by the CMO (7); drawn by Data & Content (these six) and Frontend & Shop (the event page, the ticket purchase and its
email, the QR pass, check-in, the trail, sign-ups). Due Dec 3 with the offer (decisions/0181's note, #1595). Built on
Sync 2026-11-27 16:23 UTC. Each point: what it depends on (file:line in the live mirror), its owner, and whether it works
today. "Works today" is said only where a read-only live read showed it; the rest says what's unread and why.*

**How "works today" was read:** live-read (`live-read.yml`, the test accounts) reads admin pages and operational tables
only, never a public page or a scan. So public pages are marked *code path present, not walked live*; the advocates'
walk on staging about Dec 15–16 (0181's note) is where each is proved. Adding a public-page read would be new tooling
(CLAUDE.md "Launch first"), so it isn't proposed here.

## Data & Content's points

| # | Touch point | Depends on (live code) | Owner | Works today? |
|---|---|---|---|---|
| D1 | **The poster's GO Code scan** (lobby, restaurants) | trvgo.us router `trvgo/index.php:51` (`ShortCodes::resolve`, `app/lib/ShortCodes.php:306`) and its count at `trvgo/index.php:95` (`ShortCodes::count`, ShortCodes.php:335: code, day, device, never who). An unclaimed listing's poster uses its page's Share code, `ShortCodes::forPage` (ShortCodes.php:109), reached from `website/api/qr.php:72` and `website/api/short.php:19` | IT (11) for the router; CMO (7) for the posters | Code path present, not walked live. **Gap:** a page code has no owner and can't be re-pointed or switched off (the CMO's addendum in `go-code-kit.md`), so #8807 and each restaurant are checked for duplicates first |
| D2 | **Listing #8807** (the theater's page) | `website/listing/view.php` (the engage bar at :374, 0058 §20–21) | Editorial (12) for its facts; the theater once claimed | **Read live 2026-11-27:** the row exists (Hometown Theater, St. Johns 85936, cluster 1, unclaimed, Free, not suppressed). The public page itself not walked live |
| D3 | **The restaurant cards and their own offers** on the event page | The participants tool (0134's note, #1589): **not built**. A business's own offer: `app/lib/Promotions.php` makes only Traversence's own promotions, by Managers and Directors (`canMake`, :31), on our plans and products (`TARGETS`, :24); `issueGoCode` (:128) issues one as a GO Code | Frontend & Shop builds the tool; Sales (14) by hand until then | **No.** Two gaps: (1) the participants tool isn't built (Sales sets them up by hand as a showcase if it misses Dec 14–15, #1589); (2) **there's no way for a business to make its own promotion**: Promotions.php is staff-only and discounts our products, not a diner's meal. The CMO's ruling ("a diner offer appears only after its owner makes it, as their own promotion") has no tool behind it. Possible solutions, as suggestions: a restaurant's offer as words on its participant card (no code, no discount engine), or a business-owned promotion target in 0108 (a later build) |
| D4 | **A restaurant's invite and claim** | The invite: the host's invite from its event (0134 #1589, not built); today's claim invite is a rep's, `Sales::emailInvite` (`app/lib/Sales.php:301`), only when `Sales::canEmail()` (:295, the sender's postal address set). The claim: `website/listing/api/claim.php:77` → `Claims::start` (`app/lib/Claims.php:229`), then `confirm` (:250) | Sales (14) for invites; Data & Content for the listings; the claim queue's staff for verification (`Claims::verify`, :600) | Claim: code path present, not walked live. Invite: the host's invite isn't built; a rep's invite works only with the postal address set, and 0134 #1589 holds an unclaimed business's invite until real email sending is on (`SesMail::mode()`, `app/lib/SesMail.php:42`; SES_MODE live is the Founder's call, 0181's note). **Not read live:** the Service register has no card titled "Amazon SES" (live read 17:54 UTC), so SES's mode wasn't seen |
| D5 | **The pushes and emails** (to the theater, the restaurants, buyers) | `Notify::send` (`app/lib/Notify.php:174`) over its kinds (`KINDS`, :76): `biz.order` for a Marketplace order (ticket sales), `biz.message`, `biz.comment`, `listing.held` (messages left before a claim). Push: `app/lib/WebPush.php` (`ready`, :31). Email: `app/lib/Mailer.php` (SES the backup, :171) | IT (11) for Notify; Editorial (12) for the words; the promise check (0181 gate 1 note, #1579) | Code path present. **Gaps:** Notify has **no event, invite or participant kinds** (an invite accepted, a participant added or removed, "Part of <event>"); each needs one when #1589's tool is built, and each passes the promise check (its link lands on its thing) |
| D6 | **The story afterwards** | `app/lib/Stories.php` is one guide story per place (`cluster:1` for St. Johns, :8–16), published only by a person ticking its checklist (:18–23, 0005). A magazine-style event story (0164's note of 2026-11-27) has no kind of its own | Editorial (12), with the theater's and each restaurant's OK on what's said about them | **Partly.** St. Johns' guide story exists as a place story; an event's own story has no home. Suggestion: write it as a section of the St. Johns guide story, or wait for the magazine stories kind (0181's "content to commerce" note, January) |

## Frontend & Shop's points

*To be drawn by Frontend & Shop (the event page, the ticket purchase and its email, the QR pass, check-in, the trail,
sign-ups).*

## What this map says needs building before Dec 18 (for the PM (8) and the CMO (7))

- **#1589's participants tool**, or Sales' hand setup by Dec 14–15 (already planned).
- **A business's own offer:** no tool exists (D3). It's needed for the CMO's ruling point 2; until one exists, a
  restaurant's offer can only be words on its card, said as theirs.
- **Notify kinds for the event and its participants** (D5), each with a link that passes the promise check.
- **SES's mode** read once (D4): whether unclaimed restaurants' invites can go at all before the night.
