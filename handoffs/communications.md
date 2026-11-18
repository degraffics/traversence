# Communications: hand-off (2026-11-18)

**Decision:** `decisions/0172-communications-who-reaches-whom.md`, draft 5, no code. Waiting for Jason's OK.
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
its own answer box. He said "there is a lot to unpack" in the rest: expect more changes.
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
