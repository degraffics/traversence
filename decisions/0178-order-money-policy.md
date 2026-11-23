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
