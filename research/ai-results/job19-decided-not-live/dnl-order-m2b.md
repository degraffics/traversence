**Skipped: M2b is paused by Jason, so this draft contains no patches.**

`handoffs/decided-not-live.md` (Building now) lists this item as **"Paused by Jason 2026-11-24 ('Pause for now. add to work queue.')"**. `handoffs/order-management-m2.md` (Final hand-off) says the same: "M2b, decided, built, reviewed, paused (not in flight) … PR #1346 merged as the record only." Your instructions say to skip anything paused, and this is the whole item.

The Work queue in the live database is now the queue ("From 2026-11-25 the Work queue … is the queue"), and it isn't attached. If Jason has resumed M2b there, send that evidence and I'll draft it then.

## 1. Plan

No changes. If M2b is resumed, the plan comes from `handoffs/order-management-m2.md` ("To resume"):
- **Start from the live copies:** begin each of the five files from its live copy and re-apply M2b's changes. The change is the diff of `handoffs/order-m2b/order-m2b.zip` against Sync 02:52 UTC (2eab529).
- **`ClientOrders.php`:** `legs()` added; `boardState` reads it; `deliverables` made public.
- **`WorkOrders.php`:**
  - `goesTo`, `DELIVERY_MEANS`, `legWords`, `setDelivery` and `tellVendor` added.
  - `setTracking` says the vendor was told only when the email went.
  - `moveItems` marks items delivered on the vendor's Complete only where `goesTo()` is the client.
- **`app/admin/tools.php` `'backoffice/work_orders'`:** Courier booked on a Will Call too; "emailed" only when it went.
- **`website/admin/client-order.php`:** the Delivery line's leg words, Receive / Add the courier's number, Change how it goes…, the `wo_courier` and `how_goes` handlers, and the vendor-email error notice.
- **`app/ui/faq.md`:** three entries.
- No migration.

## 2. Diffs

None. Even after a resume, some diffs couldn't be written from what's attached:
- **Files not shown:** `website/admin/client-order.php`, `app/admin/tools.php`, `app/ui/faq.md`, the M2b zip, and decisions/0101's 2026-11-24 note (the attached 0101 is cut off before it).
- **Truncated:** `ClientOrders.php` is cut off inside `boardState`. `deliverables()`, `itemStatus()`, `delivery()` and `outTheDoor()` aren't visible, so hunks touching them couldn't quote exact context.
- **Usable:** only `WorkOrders.php` and `Orders.php` are complete.

## 3. Tests

None. On resume, the existing suites are `tools/order-tests/m2b.php` (35), `m2b.cjs` (88, at 1280 and 390) and `m2b-seed.php`, kept in the repo rather than rewritten.

## 4. faq.md entries

None. M2b's three entries are in the zip, not attached. I won't invent their wording, and none should say "coming" for a paused item.

## 5. Risks and what the lane session should check

- **Queue order:** the hand-off puts **M3's first fix** (order tests green again: the harness schema brought to the live tables, seeds that clear what they count) ahead of M2b. Resuming M2b first means m2b.php/m2b.cjs run on a harness known to have drifted.
- **Stale base:** the zip was built on 2eab529. `WorkOrders.php` and `ClientOrders.php` on live may have changed since (e.g. `retireFile`/`retireProof`, `cancelBlock` from M2). Re-apply rather than unzip over them.
- **Claims to renew** (they lapsed when it was paused):
  - `ClientOrders.php::boardState,deliverables` (change) and `legs` (add)
  - `WorkOrders.php::moveItems,setTracking` (change) and `goesTo,legWords,setDelivery,tellVendor` (add)
  - `tools.php::'backoffice/work_orders'`
  - `website/admin/client-order.php`
- **Known pitfall:** a Will Call the vendor marks ready sets the order's stage to `ready`, so the leg must be read before the stage. Also, `Mailer::send` throws, so never say "emailed" without it returning.
- **Next step for you:** confirm in the live Work queue whether Jason has resumed M2b. If he has, attach the M2b zip, the full live `ClientOrders.php`, `client-order.php`, `tools.php`, `faq.md` and 0101's 2026-11-24 note.