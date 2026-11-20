# Jason's "Order Processing Review" against what's built and decided (2026-11-20)

Jason, 2026-11-20: "review this and let's see how it helps" (a .docx: an outside write-up of a hybrid order model, eight
pillars). Checked by the session manager against `research/order-system-map-2026-11-19.md` (the map, live code at Sync
2026-11-19 16:21) and the decisions it cites. The write-up is a checklist, not a source: nothing in it is built unless a
decision or Jason's own words say so (builder-playbook §3). Each row: **Built** (decided and live), **Decided, not
built**, or **Open** (no decision: a question for Jason).

| Its idea | Here | Where |
|---|---|---|
| One parent order, line items each with their own state | **Built** | `orders` + `order_lines.item_status` (~25 statuses), three status layers (map §3) |
| Proof approval is the hard gate before production | **Built** | signed final approval (initials, two boxes); approve blocks while a proof waits; workflow's `proofs_approved` before production (map §3 steps 7–8). Open: a printed item with no proof can be sent (map §5, "ask Jason") |
| Design and production as separate lines (design fee earned at sign-off; production refundable until plated) | **Open** | projects and parts exist, creative items' finals release when there's no print (map §3a); no rule on a cancel after approval. Linked open item: a refund of printed work doesn't cancel the open work order (map §5) |
| Vendor routing with print-ready files | **Built** | work orders, vendor's page `/wo.php`, file preference per vendor, 4over paste (map §3 steps 11–12, §3a) |
| Blind drop-ship, our brand as sender | **Built (method)** | `WorkOrders::DELIVERY` has `blind_drop`; 0099 names our return address for blind shipping (d1). Not checked: what the packing slip carries |
| Tracking back to the client under our brand | **Built** | vendor marks shipped with tracking; "on its way" email when the last item is out (map §3 step 12) |
| Pay in full, deposit, or later; capture at a milestone | **Built (our way)** | in full, deposit %, invoice later, outside, Release to production before payment (0101 10-28). No card authorize-then-capture for our own orders |
| Webhook idempotency | **Built** | `order_ledger.request_key` unique (inferred, map §6); Marketplace: each webhook event once |
| Subscriptions: renewals, failed cards, proration | **Built (plans)** | `subscriptions` + `subscription_charges`, card on file; a declined plan ends on Free (0108). Marketplace renewals have no webhook handler (map §6) |
| One-off digital products | **Built** | one-offs (0154), Marketplace downloads (c5) |
| Revenue by kind, vendor cost matched to the invoice, net of card fees | **Decided** | 0098: margin = paid − (vendor costs + payment fee); Cost tab COGS (0130); 0166 §2 the site's own ledger, only the wallet built |
| Sales tax by item kind | **Built (QuickBooks)** | tax at approval on the QuickBooks invoice (0101); by-kind taxability is QuickBooks' setting, not ours |
| Reorder: exact repeat vs update and reprint | **Built** | `Collateral::reorder`: same art (approved proof reused, client confirms with initials) or with changes (new proof round), at today's prices (0101 10-28, 10-30) |
| Version-locked proofs, approved file only to the vendor, audit trail | **Built (mostly)** | proof versions, signed approval, files released only when paid (0101 10-27). Not built: a hash of the exact approved file |
| Artwork preflight (bleed, resolution, colour, fonts) | **Open, waiting** | nothing checks artwork (map §3a); 0176 R4's order file check waits on Jason's limits |
| Client uploads artwork; nudges when assets are missing | **Open** | today only our team uploads; no rule either way (map §3a) |
| Design capacity (slots per week) | **Open** | no decision |
| Every order ends in a cart | **Missing (Jason's correction)** | Jason, 2026-11-20: "We dont quote and build, our orders either configured by us with separate building processes to yield something in a cart or the client can configure it and have us support their item needs ending up as a cart as well." Today print can't be configured by the client (the Shop's card is "Ask for a quote", map §2), and a staff-built order goes to the client's private order page, not a cart. The one cart exists for GO Codes, one-offs, domains and plans (map §2 rows 5–7). The 4over example (`research/order-intake-2026-11-19.md`) shows the configure → cart → checkout path |
| Set expectations before money is taken | **Decided today** | 0135 note (Jason, 2026-11-20): stocked or back-ordered known before paying; made to order and services confirm first |

Jason, 2026-11-20, on our team configuring it: "Yes it can be graphic design, printed product, etc handles as separate processes, but becomes on cart (Invoice) for approval(confirmation) and payment." So each line (design, printed product, other) keeps its own process, and the cart is the invoice the client confirms and pays.

Jason, 2026-11-20, on proofs and the cart: "Process of an item should address requirements to release to cart, so proofs get approved, production files get uploaded, vendor is selected, drops ship is configured, etc. then released." So proofs come before checkout: each item's own process has a list of requirements (proof approved, production files uploaded, vendor selected, drop-ship configured, and so on), and only when they're met is the item released to the cart.

**The main gap** (Jason's correction above): both ways in, our team configuring it or the client configuring it, end as a cart, as on 4over. The Orders session's first proposal starts here, on top of the map; how it meets 0101's proofs and approval is its question for Jason.

What it adds for the Orders session: the **Open** rows are questions for Jason, asked one at a time with the map's six,
never built as choices. The order file check's limits are already on his list.

## The Gemini review's summary (Jason shared it, 2026-11-20)

Jason reviewed the map with Gemini and shared its "Summary of Suggestions for Claude". Each point against what's
decided (the session manager's check):

1. **One cart/invoice for every order, each line released by its own pre-cart steps.** Matches Jason's words above
   (cart, cart as invoice, release to cart). Business sales (0135, the Marketplace) stay a separate system (map §1).
2. **Terms and availability before payment, per kind** (stocked or back-ordered; made to order and services confirmed;
   tickets final, the pass is the product). Matches 0135's note of 2026-11-20. **To confirm:** that note sits in the
   Marketplace's decision; Gemini applies it to our own sales too. Jason's words were general, so likely yes.
3. **Design and production as separate tracks with their own money rules.** Still open (above): when design is earned,
   when production is refundable, and what a refund does to an open work order. A suggestion, not a decision.
4. **Proof gates, collateral hygiene, exact repeat vs update and reprint.** Mostly built (0101: signed approval, files
   released when paid, reorder same art or with changes). Open: a fingerprint of the exact approved file.
5. **Event-driven messages without repeats; ledger and payments aligned.** Order mail goes through `Mailer`, not the one
   dispatcher (map §3, Emails; 0172); moving it is a question. Idempotency is built; part payments on QuickBooks' page
   record nothing until paid in full (0101, deliberate, map §5).
6. **Keep 0101's deliberate rules, fix the real bug, list unknowns as questions.** Matches the map (§5, §7) and the
   playbook; the chargeFor fix is in the first release (order-processing.md).
