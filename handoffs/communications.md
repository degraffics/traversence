# Communications: hand-off (2026-11-18)

**Decision:** `decisions/0172-communications-who-reaches-whom.md`, draft 16, no code. Waiting for Jason's OK.
**Draft 2 (Jason's first changes, 2026-11-18):** Pulse is the inbox (everything lands there; email, push and text point
back); outside mailboxes in Pulse (§4.7: our cPanel, IMAP, POP, Gmail, Microsoft, SES receiving); his six levels
(Critical, Urgent, Alert, Notice, General, Special) with what each does, kept apart from a person's own flags (Follow
up, importance) and tags. Then: three touch points (Members, Listings, Admin), and a
community group is a channel inside the member's Pulse, not its own inbox; Pulse works like an email inbox: one stream,
searchable, archivable, organized only by the person (views, rules), never reordered by us. Then (draft 3): **Pulse collects, organizes and reads;
the message carries its own process** (full details and its action buttons inside it, state read live). Draft 4:
Pulse keeps its established look (0105), not an Outlook layout (Jason: "i didnt say to use the outlook layout");
bundled cases split. Draft 5: the profile page's Pulse per Jason's mockup
(`design/pulse-profile-mockup.png`): channel chips, list with row icons, a row opens in place with its content and
its own answer box. Draft 6: the row's seven icons as Pulse's managing actions on every kind, and an
email in Pulse; Jason chose: every Pulse opens rows in place; 0105's drawer becomes ⤢ open wide. Nothing is pinned or held on top by the site: the bell and the
item's own marks carry the signal (account notices included); the needs block and the Operational Task Queue go to
the bell. He said "there is a lot to unpack" in the rest: expect more changes.
**Checked against:** the live mirror at "Sync: 2026-11-18 02:36 UTC".
**Started by:** the session manager, with Jason's scope (2026-11-18): internal, between accounts, and listings
communications; each WHO, then each WHAT; listing cards; texting from the decisions.

## What's in the draft

- §1 each WHO (guest, member, creator, business owner, business staff, a business's customer, our staff by role, the
  system): who can reach them, who they reach, channels, preferences, privacy, who pays.
- §2 each WHAT (12 services): every event, who's told, channel, level, reply, who pays, and built / decided / gap.
- §3 listing cards: one "managed" test and one actions helper; the cards checked (file:line); "Wish you could message
  them?" for unclaimed listings, counted for Sales.
- §4 one dispatcher (`Notify`, `comms_messages`, `comms_deliveries`), quiet hours, digests, opt-outs; built on 0167 and
  0171, not against them.
- §5 texting: where it applies, and the open questions answered or marked for Jason.
- §6 the build order: 0171 → 0167 → in-site dispatcher → cards → web push → comments, bookings, groups → text.

## Gaps found in the live code

1. A business isn't told about a new message (`BizMessages::staffUnread` never called).
2. A business isn't emailed about a marketplace order or booking (`MarketCheckout::notify` writes in-site only).
3. Comments tell no one (except a member's own post's author); comments are flat.
4. Escalating a Pulse case tells no one (`Pulse::mark` adds a note).
5. "Claimed" (`entities.status`) and "managed" (`listing_access`) differ between search and the listing page.
6. Cards: Dashboard → Marketing shows "Message them" for unmanaged listings; the storefront's "Message us" relies on
   the listing page; grids, maps and guide cards never show Message, even on managed listings.

## Open, for Jason (§5.2)

- Q1 a Sales texting budget (default $0) and the ~$1/month claim number.
- Q2 transactional texts to a business's customers, ticked per order.
- Q3 no text sign-in codes for members.
- Q4c toll-free first; who registers 10DLC and when.
- Q5a STOP per number plus per-business choices; Q5b HELP wording (attorney); Q5d consent kept 5 years.
- Q6 whether Basic and Core may buy text packs.
- Pulse's four severities become six (`Pulse::SEVERITY`, keys kept).
- §4.7: outside mailboxes for staff (changes 0167 §2a); members' mailboxes on Personal Plus and Pro, or not yet; Gmail
  by sign-in only once revenue covers Google's yearly assessment.
- Notes: §3.3 (no held messages for unclaimed listings) and §4.3 (0167's Sent view reads the one delivery log).

## Next

Once Jason approves (or after his changes), the session manager merges. Each §6 step is its own build session with its
own release; the first new one is step 2 (the in-site dispatcher), after 0171 and 0167.

## Draft 7 (Jason, 2026-11-18)

- Critical and Urgent items: in Pulse with the bell, and emailed.
- Admin: the Operational Task Queue goes (the bell's list replaces it); Your day and Quick Actions stay, opening
  collapsed (maybe Quick Actions as ⋯ and Your day as a pop-up icon, decided at the build).
- Unclaimed listings offer their own data (Call, Email, Website, Directions), opened on the visitor's device and
  counted (`data-out`, email added), the counts to Sales; claimed listings join the communication system. Open: ever
  sending a visitor's question through Traversence to an unclaimed listing's public email (0108's outreach limits).
- Outside mailboxes: staff once their manager approves; members as an App store add-on (0169), set up at its build.
- **Texting is outlined in its own decision.** The research and the open questions above (Q1–Q6) are kept here for it.

## Draft 8 (Jason, 2026-11-18)

- **Routing** is a message's path from creation through delivery to its end point (filed, archived, deleted, expired):
  §4.2's eight stages. **Message control** is a separate function over every route: creating, tracking, monitoring,
  verifying, reporting, controlling (§4.3), in Communications (0167) and on the Control Board (0171). The log table is
  `comms_route`.
- Open: an Email to an unclaimed listing by the visitor's own app (only the tap is seen) or written in Traversence and
  delivered by us (the whole route tracked, the reply back in Pulse).

## Draft 9 (Jason, 2026-11-18)

- Unclaimed listings get **Leave a message**: held on the listing's id, delivered only when it's claimed, the sender
  told plainly it isn't going to a live business; Sales sees counts only; expires after 90 days (for Jason).
- Draft 10: if the unclaimed listing has a public email, the message is emailed there by us (tracked, reply back to
  the sender's Pulse); if not, held until a claim.
- Draft 11: a message to an unclaimed listing needs a way to answer its sender (account, confirmed email or phone),
  else it's not accepted; guest email confirmed first (Jason agreed); emails to a listing: the first at once, then one
  per 5 waiting, with screening, its own sender, stop and report links, and a self-pause on complaints; Message and
  Leave a message on the listing page only (Jason). A queue under 5: one notice after 10 days (Jason).
- Draft 12: texting is back in this decision (Jason: "will address this build here too"); §5 restored with its
  questions, plus a guest's phone confirmed by a text code once texting is built.
- Draft 13 (Jason's answers): quiet hours a setting with no default; one Communications setting per person; web push
  asked when an action turns it on; a comment on a listing is a notice to the business, sent like a message, with
  reply, hide and report inside it; no new digests (future); team routing is 0102's RBAC; a group works like Link
  (link settings decide); spam reports follow the abuse process (0052, 0137, 0079); the waiting-messages email is a
  notice, reading needs an account; no clocks set on messages (0105's staff clocks as built); business mailboxes to
  the App store build (sent to the session manager). **Open: rows in place vs the admin's drawer: to discuss.**
- Draft 14: rows open with the expanded toggle (what happened, stepper, up to 5 actions, notice, last 3 log entries,
  reply), and a pop-up (0074) when the work needs room; the side drawer retires. Proposed; Jason to confirm.
- Draft 15: three views with roles (scan → look → work → fix): expanded toggle to look, drawer to work a case (no
  tools; Previous/Next), pop-up to fix. Proposed; Jason to confirm.
- Draft 16: email, push and text are delivery engines, separate builds on one contract (§4.6): email is 0167, push is
  its own build, text is its own decision then build. The texting research is kept below for that decision.

## For the text decision: the research and proposals from the first draft (2026-11-18)

Checked against the live mirror at Sync 2026-11-18 02:36 UTC: no SMS provider in the code; `sms:` links only.

### T1 Where texts apply (from the decisions)

| Use | Decided in | Who pays |
|---|---|---|
| An owner texts **in** to prove a claim | 0070, 0071 (fallback), 0133 | $: our number, inbound fees |
| A claim code sent **out** by text | 0015, 0016 (end state) | $ (Q1) |
| Claim invites and sales outreach, "Reply YES" | 0108 | $ (Q1) |
| A business's marketing to people who shared their number and opted in | 0047, 0049, 0141, 0154 | P: plan allowance or text pack |
| A business's customers about their order or booking | **new here** (Q2) | P |
| GO Codes and member invites opening the phone's own app | 0094–0098, 0047 | free (built) |

**Ruled out** (kept): staff sign-in (0100), Control Board alarms (0171), any text with no revenue behind it, texting
someone who only tapped Link (0047), and members' sign-in codes (Q3).

### T2 The open questions

| # | Question | Proposed answer | For Jason? |
|---|---|---|---|
| Q1 | Who pays for our own texts (claim invites, claim codes out)? | Email and GO Codes stay first (free). Texts to businesses come from a **Sales texting budget** Jason sets in 0163's cost register, **default $0 (off)**; each text counted against it, failed ones too. The inbound claim number (~$1 a month) is the one standing cost. | **Yes:** the budget, and the $1 number |
| Q2 | Texts to a business's customers about orders and bookings | Allowed as **transactional** texts: the customer ticks "Text me about this order" at checkout (per order, unticked by default); only that order's updates; STOP ends it; paid from the business's text allowance. Never marketing. | Yes: confirm |
| Q3 | Members' sign-in codes by text | **No.** Members sign in by password or social login; codes stay email (free). Text codes cost with no revenue and are open to SIM swaps. Passkeys later. | Yes: confirm |
| Q4a | The provider | **Twilio** (0108: "Twilio first"), one card under Outside services (0152), keys in `.env` (`TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_MESSAGING_SID`), checked by the pre-flight (0171), held on staging (0165) | no |
| Q4b | The incoming-text webhook | `/api/sms-in.php`, checking Twilio's signature, logged in `comms_deliveries`; added to the paths Bluehost is asked to exempt (`research/bluehost-bot-check-2026-11-15.md`), since a blocked STOP must never be lost | no |
| Q4c | Toll-free or 10DLC; who registers | Start with **one toll-free number**, verified by Traversence (simpler and fast at low volume; no brand fee). Every business text starts with the business's name. Move to 10DLC, Traversence registering as the platform and each business as its own brand, only when a business's volume needs it. | **Yes:** who registers, and when to move |
| Q5a | One STOP list or one per business? | **Both, as the carriers require:** STOP to our number stops every text from that number (carriers enforce it). Per-business choices live in the person's preferences, so they can stop one business without stopping all. | Yes: confirm |
| Q5b | HELP wording | "Traversence: help at https://traversence.com/faq.php or support@traversence.com. Msg & data rates may apply. Reply STOP to stop." Attorney-reviewed before launch (0141). | Yes: attorney |
| Q5c | Whose time zone sets quiet hours | **The recipient's:** the device's stored zone; else the area code's; never earlier than 8 am or later than 9 pm there (US texting rules). | no |
| Q5d | How long consent records are kept | **5 years after consent ends** (the federal claim limit is 4), with wording, version, when, from where and how it ended (0108). Never deleted early by account deletion: kept as the proof, with nothing else of the person. | **Yes:** check against the privacy policy |
| Q6 | Which plans include texts, the pack price | Already set in `commercial.md` §1.1: **Strategic 500, Enterprise 2,500 a month**; packs **+500 $19.95, +2,500 $89.95, +5,000 $169.95, +10,000 $299.95**. Not in `Billing::ADDONS` yet (storage and AI only): they're added with the texting release. Basic and Core can buy a pack, with no allowance. | Yes: Basic and Core may buy packs? |

