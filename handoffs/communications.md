# Communications: hand-off (2026-11-18)

**Decision:** `decisions/0172-communications-who-reaches-whom.md`, draft 12, no code. Waiting for Jason's OK.
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
