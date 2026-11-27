# 0178: Order money policy: refunds, cancelling, credit, terms, cards and delivery with a balance

**Status:** accepted (Jason, 2026-11-23, in two pop-ups, reviewed by the session manager and the Design reviewer).
**Builds on:** 0098 (fix, credit, then refund; credits 90 days), 0101, 0177 §4 ("Paid in full or overrides"), the order
model (`research/order-model-2026-11-21.md` §1.8, §1.9), the order management design
(`research/order-management-design-2026-11-23.md`, approved the same day as the plan, releases M1–M7).

## Why

The design's §5 collected the money-policy questions for one review instead of asking them one by one. These are his
answers. Each is built in the release named.

## Decision

1. **Refunds: always ask Jason.** Every refund, whatever the amount, waits for his OK. An admin prepares it on the
   order (the amount, the reason, the payment it comes from); it goes to Jason as a Pulse case with Approve and
   Decline; only on Approve is the money sent through QuickBooks. Today any admin can refund any amount
   (`admin/order.php`): that narrows. (M2)
2. **Cancel window: until the vendor accepts the work order.** Cancel is offered until the vendor accepts it with
   their job number; after that it's greyed with the reason ("The vendor accepted the work order on <date>: it can't be
   cancelled; a credit or a refund instead"). Model 1.9's "once production schedules it" is read as the vendor's
   acceptance. (M2)
3. **Credit on account: staff apply it, the system suggests it.** When a client with credit is invoiced, the order
   shows "Credit available: $X · Apply it"; staff press it, with Undo. It is never taken off by itself. This builds the
   missing half of 0098's credits (`credit_used` is written for the first time). (M3)
4. **Credit terms: Net 15 and Net 30, granted by Jason only.** Terms replace "invoice later" (model 1.8); only Jason
   grants them to a client's account, after the credit process; a deposit can still be asked to offset risk. An order
   on terms is due by its terms' date, and the vendor and delivery don't wait for payment (model 1.8: "balance due per
   terms"). (M7)
5. **Card by phone: no keyed cards.** Staff send or copy the client's pay link; the client enters their own card.
   Nothing in the admin takes a card number. (No release: it confirms what's built.)
6. **"Yes, I have it" with money owed** (Jason: "should prompt the admin to address balance, if received, mark paid,
   if not, collect... override with reason, then proceed."):
   - The client's confirmation records the delivery and thanks them; the order does **not** complete.
   - It becomes the order's next task, for the admin: **Delivered, balance due**, with three ways forward where it's
     shown (the board's row, the order page's Money line, a Pulse step):
     - **The money was received** → Record the payment (mark it paid), then the order completes;
     - **It wasn't** → Collect the balance (the payment step, reminders);
     - **Override with a reason** → complete it anyway (an admin; the reason kept in the history; #1307's override).
   - Then it proceeds: once paid or overridden, the order completes and the client gets the thank-you as today.
   (M1, with the state model: Delivered is the fulfilment state, Balance due the money state.)

## Not changed

0177 §4 (the vendor and delivery wait for the whole order's payment, or an admin's release with a reason), 0098's
order of remedies (fix, credit, refund), and `QuickBooksPay::guardReal()` (no test moves real money).

**Note (2026-11-23, Jason, asked in the Orders session's chat about a mockup's "Paid … read from QuickBooks · ✓ Recorded ·
Undo"): "for money, would a undo be correct or would it be cancel unless processed then it would be refunded."** Money
never has Undo. A payment read from QuickBooks shows ↗ to open it there (QuickBooks is the record; a correction is made
there and read again). A payment recorded here and not processed (a check noted, nothing charged or posted to
QuickBooks) has **Cancel the record**, gone as if never recorded. One processed (a card charged, or posted to a
QuickBooks invoice) has **Refund…**, with his OK (§1). Agreed by the session manager as the standing rule; CLAUDE.md's
"Edit or Undo" says it once. Also decided by the session manager the same day (M1b, design §3.6 (a)): a payment
QuickBooks already shows on a linked invoice is applied at once, said as "Paid in QuickBooks <date> · <method/ref>",
corrected with ✎, History kept; it writes nothing outside our records.

**Note (2026-11-23, Jason, in the Orders session's pop-up, reviewed by the session manager): M1b's two QuickBooks
answers**, for design §3.6 (built in M1b):
- **(b) "Yes, post it":** a payment recorded on an order whose QuickBooks invoice was made by hand and linked by its
  number is also received on that invoice in QuickBooks (`QuickBooksPay::recordOtherPayment()`), so it's never entered
  twice. `guardReal()` / `testHere()` hold.
- **(c) "Yes, create it":** a vendor bill recorded on an order (typed, or from their dropped invoice) is also created in
  QuickBooks as a Bill against the vendor (amount, number, date, due), linked to the order. Paying vendors stays a
  separate decision.
He also approved M1b's design (mockups `open`, `open-after`) and M1 (PR #1320) in the same pop-up.

**Note (2026-11-24, Jason, in the Orders M2 session's pop-up, reviewed by the session manager): credit memos wait for him
too.** Asked: "A credit memo also gives money back in effect … Should credit memos wait for your OK too?" Answer: **"Need
my OK"**: Issue a credit memo prepares it; he gets a Pulse case with Approve and Decline, like a refund (§1); nothing is
credited (and nothing made in QuickBooks) until he approves. Built in M2.

**Note (2026-11-25, the Orders session's wrap-up): where it stands.** Live with M2 (#279): §1 refunds and, by the
2026-11-24 note, credit memos prepared on the order and waiting for Jason's Approve or Decline in Pulse; §2 cancel until
the vendor accepts; §5 no keyed cards, the pay link to copy; the money note's ↗ / Cancel the record / Refund… on each
payment. Not built: §6's credit terms (Net 15 / Net 30), M7 in the queue; Outstanding and Take a payment across orders, M3.

**Note (the CFO (4), 2026-11-27, kept in Tier 4 by the Prior Ruling Check; recorded as Operations & Policy (16)): a
refund the business made itself is recorded by the business.** A Marketplace refund made in the business's own Stripe or
Square dashboard (app/business/checkout.php:133) never reached our order: 'refunded' is only displayed
(market/order.php:34), never set, so a refunded ticket order stayed paid and its tickets still checked in at the door
(`KindCheckout::checkIn` checks only for a paid order). The fix: the business's own **Mark refunded** on the order in the
portal records the refund it already made (no money of ours moves) and voids that order's tickets, so check-in refuses
them, with History kept. As a money record it takes **Cancel the record**, not Undo (CLAUDE.md "Edit or Undo": money never
has Undo; a recorded, unprocessed entry has Cancel the record), which restores the paid state and the tickets.
Gateway refund webhooks come later. A gate 5 ticket in the IT lane, after the writeOrder fix, with an independent check.
