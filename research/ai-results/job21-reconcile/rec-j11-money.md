# Reconciliation of the j11 money-path reviews: pass 1 vs pass 2

**Truncated inputs.** Several attached passes are cut off, so a "single-pass" mark against them is provisional:

- **pass1 ClientOrders:** the summary stops at row 2. The body (defects 1–10) is complete.
- **pass1 MarketCheckout:** the summary's last row is cut. The body (defects 1–12) is complete.
- **pass1 MarketPay:** defect 11's fix is cut.
- **pass1 QuickBooksPay:** stops inside defect 5.
- **pass2 ClientOrders:** stops inside F2.
- **pass2 QuickBooksPay:** stops at defect 10.

Where a finding appears in only one pass and the other pass is truncated, the table says "single-pass (other pass truncated)". The missing pass may simply not have reached that finding.

---

## 1. Where the passes agree

**Across all files**
- Both passes find that 0177 does not reach AiCredits, KindCheckout, MarketCheckout, MarketPay or Promotions. They also agree that 0178 reaches the Marketplace only through "Not changed" (`guardReal`, no test moves real money), the money note, and the 2026-11-27 CFO note.
- Both raise the same open scope question: does 0178 §1 (Jason's OK on every refund) cover Marketplace refunds from a business's own gateway?
  - Both passes on MarketCheckout raise it (`rentalReturned`).
  - pass1 MarketPay raises it (defect 5, `refund()`).
  - pass2 MarketPay raises it (:378–383).
  - Neither pass counts it as a defect.

**AiCredits**
- Fail-open on database errors (P1 :88, :57; P2 :88, :57).
- Only `done` jobs are counted, so parallel asks overspend (P1 :87; P2 :87).
- The co-owner cover is multiplied (P1 :42, 55, 57; P2 :42, :55, :59 vs :57).
- The constants are unused as fallbacks (P1 :59, :76; P2 :76, :59).
- Add-on price is treated as monthly (P1 :34; P2 :34).

**ClientOrders**
- The `paidInQuickBooks` fallback records the balance as paid when no money is shown (P1 1753–1758, 1775, 1784, 1788; P2 :1753–1758, :1775, :1784, :1788).
- A test copy can be invoiced and charged in Production (P1 1470, 1481, 1503, 1731; P2 :1470, :1481, :1503, :1731).
- `recordPayment` takes no lock and keys duplicates on the minute (P1 2188, 2193, 2194, 2202, 2510; P2 :2188–2194, :2193, :2202, :2510).
- A card can be charged with no ledger row (P1 1735, 1744, 1734; P2 :1735, :1744, :1734).
- An unchecked or shipping-only cost counts as checked COGS (P1 139–140, 214; P2 :139–140, :214).
- `outside` orders skip `shipHold` (P1 781; P2 :781).
- A linked-invoice payment can be read back and recorded twice (P1 942–945; P2 :942, :950).

**KindCheckout**
- The rental hold is judged by rental length, not time to return (both :171).
- The `LIKE` JSON match undercounts taken seats and dates (P1 :71–72; P2 :72).
- Availability is checked, not reserved (P1 :156; P2 :62, :90, :156).
- Check-in and download read only `status='paid'` (P1 :273, :289; P2 :273, :289).
- A missing item passes as a physical line (both :124).

**MarketCheckout**
- The rental refund or capture can run twice (both :726, 732–734).
- Cancelling a pending card order leaves a later charge unrecorded (P1 :518, :788; P2 :518, :574, :788).
- Expired orders are never reconciled (both :804, :810).
- A hold is recorded as paid before capture (P1 :574, 648, 732; P2 :648, :732).
- `'refunded'` is never set and appears only in STATUSES :32 (both).

**MarketPay**
- The Square idempotency key is per token, so a retry after a timeout double-charges (both :290 with :150).
- Hold plus deposit is charged at once and never confirms (P1 :261–265, :310–312; P2 :261 vs :265, :312).
- The line-sum charged differs from `due()` confirmed (P1 :266–269, 278–285 vs 314; P2 :266–269, 281–283 vs 312, 314).
- The refund key is ref plus cents, and there is no refundable cap (both :381–382).
- A non-COMPLETED Square status is reported as "Nothing was taken" (both :294).
- The Stripe session key is reused with a changing `expires_at` (both :271 with :246).
- Subscription confirm checks no amount (both :309).

**Promotions**
- Unknown cost lets a product line be discounted 100% (P1 :217, 44, 70–74; P2 :44, :217).
- Retry and race over-redeem (P1 :227–229 and :188, 197→229; P2 :188, :197, :227–229).
- A guest with no email bypasses "uses once" (both :203–205).
- `release` deletes the discount record with no paid check (P1 :233–240; P2 :233–241).

**QuickBooksPay**
- The `recordCardPayment` fallback posts a second payment under a new request id (P1 :941–948; P2 :941–949).
- A refund can't name its charge and has no cap (P1 :969–987, 993–1001; P2 :971, 978–983, 993–1001).
- The invoice request id `'inv-'.$oid` is shared by every invoice for an order (P1 :568, 623, 486–488; P2 :568, 623, 486–487).
- The credit memo uses `customerId` rather than `orderCustomer`, and posts to the GO Code item (P1 :1091, 1028, 1096; P2 :1089–1091, 1096, 1028).

---

## 2. Where the passes differ or contradict

### Contradictions

**ClientOrders: line numbers inside pass1.**
- pass1 places `paidInQuickBooks` at 2751–2759 and the fallback at 2758.
- Its own fix text and summary use 1753–1758, and pass2 uses :1753 and :1758.
- **Better supported: 1753–1758.** Pass1's other references (1744, 1775, 1784, 1788) and all of pass2 are consistent with it. Pass1's "2751–2759" looks like a slip.

**ClientOrders: 0177 §1 and §2 (destinations and tax).**
- pass1 found nothing. Release A refuses more than one address (1437–1441), and tax is applied per line (1466).
- pass2 finds three defects:
  - E1: the item's own address is not used as Ship to (:1533–1534, :2160, :1430, :1468).
  - E2: `aN` addresses and pickup items are not counted as destinations (:1531–1535, :2122, :1437).
  - E3: `outside` orders skip the refusal (:1437).
- **Better supported: pass2.** It shows the mechanism by which the >1 check at 1437 can be bypassed. Pass1 only notes that the check exists and does not test its inputs.
- These rows stay single-pass, because pass1 looked and found nothing.

**ClientOrders: the `releaseHold($outside=true)` path.**
- pass1 (defect 7 fix, :2493) relies on this path to record the release.
- pass2 F2 (:2485, text truncated) says the same path skips the admin check and the reason.
- **Better supported: pass2's concern.** 0178 "Not changed" requires "an admin's release with a reason". Pass1's fix should not depend on that path until F2 is checked.

**ClientOrders: post-paid QuickBooks money.**
- pass1 defect 9 (:1787) contrasts `fromQuickBooksLocked` with `applyQuickBooks`, which "logs `needs_books`" (954).
- pass2 C4 says `applyQuickBooks` itself `break`s silently at :947 and :949 before reaching :954.
- These are not strictly incompatible: 954 may cover a different case. Both are kept as separate single-pass rows.

**KindCheckout: whether the ticket path checks seats.**
- pass1 says `prepare` never calls `seatsLeft` (lines 58–63).
- pass2 lists :62 among the availability checks.
- **Unresolved.** The two passes read the same lines differently, and the file is not attached. Check line 62 directly.

**KindCheckout: `fulfil` retries.**
- pass1 calls them idempotent (:224–225, :231–233).
- pass2 (#8 :224–226, #9 :231–236) shows that concurrent calls (webhook plus return page) both count 0 and both insert.
- **Better supported: pass2.** Pass1's claim holds only for sequential retries. Count-then-insert with no unique key is a race.

**MarketCheckout: webhook dedupe `firstTime`.**
- pass1 defect 5 (:777–781) says the event is marked seen before it is handled, so a failed handling is never redelivered.
- pass2 lists `firstTime` (:777–782) under "nothing found".
- **Better supported: pass1.** It names the mechanism. It is still conditional on the caller (not shown), so it stays med, conditional.

**MarketPay: webhook signatures.**
- pass1 defect 10 (:401 with :49–51, :99–101) says test-mode webhooks are checked against the live secret.
- pass2 found nothing in 399–417.
- **Better supported: pass1.** It gives a specific path: `key()` depends on `testHere()`, and a webhook has no signed-in user. Impact is test money only. It depends on `QuickBooksPay::testHere()`, which is not shown.

**MarketPay: CLI and line 60.**
- pass1 #11 says mock is honoured on the CLI, so `capture()` and `refund()` silently no-op (:60, :372, :380).
- pass2 says the CLI with `env=live` uses live keys unless `testHere`/`StagingGate` stop it (:60, :49–53).
- These are different risks from the same `localTest()` line. Both are kept.
- pass2 also lists `capture()` (:374) as nothing found, which is in mild tension with pass1's mock point.

### Severity differences (merged choice and reason)

| Finding | P1 | P2 | Merged | Reason |
|---|---|---|---|---|
| ClientOrders: payment read back twice | med | low | **med** | Both make it conditional on the unshown `ours` mark; a doubled money record warrants med until verified |
| KindCheckout: check-in at :273 | med | high | **high** | Both cite the CFO note that a refunded order stays `paid` today, so the harm is current, not hypothetical |
| MarketPay: hold plus deposit | high | med | **high** | Both describe a deterministic charge-without-record on every such order |
| MarketPay: :294 | low/med | med | **med** | Money may move while the buyer is told it didn't |
| Promotions: retry/race | med (two rows) | high (one row) | **med** | Loss is bounded to the discount amount; P1 splits it into two rows |
| Promotions: guest | low | med | **low–med** | P1 notes it is unstated whether guests are covered by "an account uses a code once" |
| QuickBooksPay: fallback double-post | high | med | **high** | P1's chain (excess becomes credit that auto-applies, comment 1083–1084) is not rebutted by P2 |
| QuickBooksPay: refund not tied to its charge | high | med | **med-high** | P2 notes Intuit's per-charge cap limits over-refund on a single charge; the wrong-charge risk remains |
| QuickBooksPay: wrong customer/product | med | low-med | **med** | — |

### Fix differences

**MarketCheckout :726, 732–734.**
- pass1: claim a `'returning'` state with a conditional UPDATE, then use an idempotency key from the order id.
- pass2: set `'returned'` first and restore it on throw.
- **Prefer pass1's fix.** Restoring on throw after a gateway timeout re-opens the double refund, which is the failure pass1's idempotency key covers.

**ClientOrders test copy.**
- pass1 refuses `isTest && env==='production'` and writes the order's own mode at 1481.
- pass2 refuses `isTest && env!=='sandbox'` and stops overwriting at :1481.
- pass2's guard is tighter. It also covers any non-sandbox mode.

**QuickBooksPay mode mismatch.**
- pass1 #2 (live order processed while `env()` is sandbox; fix in `guardReal` :115–122) and pass2 #1 (sandbox order refunded or credited in production; guard in five functions) are two directions of one root cause.
- Each is single-pass. Both cite :819 as the model, and one shared guard would cover both directions.

---

## 3. Merged findings

| file:line | finding | passes | severity | fix |
|---|---|---|---|---|
| AiCredits.php P1:88; P2:88 | `count()` returns 0 on error, so credits and free asks look unused (fails open) | P1, P2 | med | Rethrow, or return `PHP_INT_MAX` |
| AiCredits.php P1:57; P2:57 | Spend query error leaves `$spent=0`; the backstop never trips | P1, P2 | med | Catch sets `$spent=INF` / `why='cover'` |
| AiCredits.php P1:87; P2:87 (via :36, :60) | Only `done` jobs counted; parallel asks overrun credits and free asks | P1, P2 | med | Count all non-failed jobs, or reserve atomically at insert |
| AiCredits.php P1:42,55,57; P2:42,55,59 vs 57 | Each co-owner's cover includes the whole business plan | P1, P2 | low (confirm intent) | Per-pot cover over `charged_to`, or split cents by owner |
| AiCredits.php P1:59,76; P2:76,59 (:18,:20) | `COVER`/`FREE_PER_DAY` not used as fallback; unset threshold gives 0 (fails closed) | P1, P2 | low | `?: self::COVER` / `?: self::FREE_PER_DAY` |
| AiCredits.php P1:34; P2:34,:36 | Add-on price counted as monthly; P2 adds that one-time packs may refill credits monthly (Billing not shown) | P1, P2 | low | Confirm add-ons recur; else count against the purchase |
| AiCredits.php P2:36 | Calendar-month reset, not billing period | single-pass (P2) | low | Count from the subscription period start |
| ClientOrders.php P1:1753–1758 (body also says 2751–2759); P2:1753–1758 | `paidInQuickBooks` records the whole balance when `fromQuickBooks` returns '' (lock timeout, nothing new, part already paid); no lock, can double with `pay()` | P1, P2 | high | Delete the fallback, or record only the QB-reported amount under the `tv-pay-` lock |
| ClientOrders.php P1:194–196,1470,1481,1503,1731; P2:196,1470,1481,1485,1503,1731 | Test copy can be invoiced and charged in Production; `qb_env` overwritten | P1, P2 | high | Refuse `isTest && env!=='sandbox'` in `cantInvoice`; wrap `invoiceFor` in `withEnv($o['qb_env'])`; don't overwrite `qb_env` |
| ClientOrders.php P1:942–945,948–950; P2:942,950 | Payment recorded here can be read back from the linked invoice and recorded twice (depends on `ours` mark, not shown) | P1, P2 | med | Also skip QB payments whose id is in `order_ledger.qb_payment_id` |
| ClientOrders.php P1:2188,2194; P2:2188–2194 | `recordPayment` takes no `tv-pay-` lock; can overrun the total in a race with a card charge | P1, P2 | med | `GET_LOCK('tv-pay-'.$oid)`, re-read `left` inside |
| ClientOrders.php P1:2193,2202,2210,2508,2510; P2:2193,2202,2510 | Duplicate key uses the minute: double posts across a minute boundary, merged real payments, QB post still sent on a deduped record | P1, P2 | med | `received()` returns bool; skip post/event on false; key on a form nonce |
| ClientOrders.php P1:1734,1735,1738,1744; P2:1734,1735,1740,1744 | Card charged before the ledger write; a failure leaves a charge with no record | P1, P2 | med | Write the ledger right after the charge, or catch and log `needs_books` with the charge id; confirm `$key` is the gateway Request-Id |
| ClientOrders.php P1:137,139–140,214,2851; P2:139–140,214,316,404,2049 | Deposit treats any non-zero cost (unchecked, or shipping only) as checked COGS; 65% fallback skipped (0177 §3) | P1, P2 | med | Cost path only when `cost_cents>0` and Costing says checked; add shipping on top |
| ClientOrders.php P1:781,798–799,1449,1453; P2:781,1050 | `outside` orders skip `shipHold`; can ship or be ready with money owed and no admin reason | P1, P2 | med | Remove the `outside` shortcut at 781 |
| ClientOrders.php P2:2485 | `releaseHold($outside=true)` writes a release with no admin or reason (pass2 text truncated) | single-pass (P2; P1 relies on this path) | med (caller not shown) | Require admin and reason on this path |
| ClientOrders.php P2:1533–1534,2160,1430,1468 | A single item-address destination isn't used as the invoice Ship to; wrong tax location | single-pass (P2; P1 found nothing) | med | Pass the sole group's address as `$shipTo` |
| ClientOrders.php P2:1531–1535,2122,1437 | `aN` order addresses and pickup items not counted as destinations; multi-address orders pass the >1 check | single-pass (P2; P1 found nothing) | med | Resolve `aN` in `destinationGroups`; count pickup as our address |
| ClientOrders.php P2:1437 | Outside orders skip the several-destinations refusal | single-pass (P2) | low | Drop `!$outside`, or record the exception in 0177 |
| ClientOrders.php P2:1730,695,1479 | `$expect` optional, so the amount shown isn't always confirmed; email deposit on pre-tax subtotal vs taxed total | single-pass (P2) | low/med | Make `$expect` required |
| ClientOrders.php P2:947,949 (vs 954) | `applyQuickBooks` silently ignores money arriving after paid | single-pass (P2) | low | Write `needs_books` instead of `break` |
| ClientOrders.php P1:1787 | `fromQuickBooksLocked` silently drops overpayment | single-pass (P2 truncated) | low | Log `needs_books` when paid > total |
| ClientOrders.php P1:264,1961–1963,1039 | Any staff can set Net 15/30; 0178 §4 says Jason alone | single-pass (P2 truncated) | low (rises with M7) | Gate on a Jason-only grant before M7 |
| ClientOrders.php P1:2134 vs 1464 | Comment says shipping is left off the invoice; code puts it on | single-pass (P2 truncated) | low | Correct the comment |
| KindCheckout.php P1:171 (with :8); P2:171 | Hold allowed by rental length, not time to return; authorization lapses before capture | P1, P2 | high | Hold only if `strtotime($to)-time() <= 6*86400` |
| KindCheckout.php P1:273; P2:273 | Check-in reads only `status='paid'`; refunded orders' tickets still admit (0178 CFO note) | P1, P2 | high | Refuse voided tickets (`voided_at`), or confirm Mark refunded leaves `paid` |
| KindCheckout.php P1:289; P2:289 | Downloads read only `status='paid'` | P1, P2 | med | Same void/status check |
| KindCheckout.php P1:156,145–147; P2:62,90,156 | Availability checked, not reserved; concurrent buyers oversold (P1 adds: tickets not capacity-checked in `prepare`; P2's :62 reading conflicts, so verify) | P1, P2 | med | Re-check `freeOn`/`seatsLeft` under row lock at order write |
| KindCheckout.php P1:71–72; P2:72 | `LIKE '%"item_id":N,%'` misses quoted or last-key encodings; `taken()` undercounts | P1, P2 | med (P2 low–med) | Drop or loosen the LIKE; rely on the exact check at :75 |
| KindCheckout.php P1:124; P2:124 | Missing item treated as a physical/test line at the caller's price | P1, P2 | low/med | Require an explicit test flag; otherwise refuse |
| KindCheckout.php P1:172; P2:8 vs 172 | Deposit "refunded on return" has no path here; comments disagree on what is held | P1, P2 | low–med (question) | Document authorize vs capture and the refund path in the caller |
| KindCheckout.php P1:101–103 (with :78) | Reversed rental range becomes 1 day, charged, holds no dates | single-pass (P1) | med | Throw if `$t < $f` before `max(1,$n)` |
| KindCheckout.php P2:71 | Payment after `hold_until` expiry can double-book resold dates | single-pass (P2) | med (question) | Re-check `freeOn` on payment completion |
| KindCheckout.php P2:224–226 | Concurrent `fulfil` issues ticket codes twice (contradicts P1's "idempotent", which covers only sequential retries) | single-pass (P2) | med | Unique `(order_id,item_id,seat)` + `INSERT IGNORE` |
| KindCheckout.php P2:231–236 | Concurrent `fulfil` creates two download tokens | single-pass (P2) | low | Unique `(order_id,item_id)` |
| KindCheckout.php P2:216–220 | `fulfil` doesn't check paid | single-pass (P2) | low | Return early unless `status==='paid'` |
| MarketCheckout.php P1:726,732–734; P2:726,732–734 | Rental deposit refund or capture can run twice; record written after money moves, unconditionally | P1, P2 | high | Conditional claim UPDATE before the gateway call plus an idempotency key on the order (P1) |
| MarketCheckout.php P1:518,788; P2:518,574,788 | Cancel of a pending card order leaves the session payable; a later charge has no record | P1, P2 | high | Refuse cancel of pending card orders (or expire the session); ticket on paid-but-cancelled |
| MarketCheckout.php P1:804,810,788; P2:804,810 | Reconcile asks only about pending; late-paid expired orders never recorded | P1, P2 | med | `status IN ('pending','expired')`, age-bounded |
| MarketCheckout.php P1:574,648,732; P2:648,732 | Rental hold recorded as paid in the business's books before capture | P1, P2 | med (MarketPay not shown) | For holds, record payment at capture |
| MarketCheckout.php P1:32,725; P2:32 (scope) | `'refunded'` never set; `rentalReturned` checks only `paid` | P1 (defect), P2 (noted, not a defect) | low | Refuse refund once a refund is recorded, when Mark refunded exists |
| MarketCheckout.php P1:485 (in #11); P2:485,502,509 vs 383 | Test-cleanup cancels a pending test order that may have been paid; P2 adds that selectors match buyer-writable note text, unscoped by env | P1, P2 (different angles) | low | Scope by env and test flag; check the gateway or `provider_ref=''` before cancelling |
| MarketCheckout.php P1:471 | Live-mode advice tells the business to test with a real purchase and refund | single-pass (P1) | low | Reword |
| MarketCheckout.php P1:789–791,730–733 | Confirm and refund use the business's current gateway, not the order's account | single-pass (P1) | med | Store `account_id` on the order; refuse on mismatch |
| MarketCheckout.php P1:777–781 | Webhook event marked seen before handling (P2 found nothing here) | single-pass (P1) | med (caller not shown) | Insert after success, or delete the row on failure |
| MarketCheckout.php P1:372,642–645 | Invoice tax rate read at payment time, total from placement | single-pass (P1) | low–med | Store `tax_milli` at placement |
| MarketCheckout.php P1:625–648 | Transaction on `$bp`, writes via `$main`; possible half invoice, then duplicated | single-pass (P1) | med if connections differ | Use `$bp` throughout, or assert `$bp === $main` |
| MarketCheckout.php P1:386,549 | Deposit ≥ total silently becomes a full charge | single-pass (P1) | low | Throw when `balance >= total` |
| MarketCheckout.php P1:518,523,608,810 | Cancelled or expired direct orders keep approved invoices; later payments ignored | single-pass (P1) | low | `quotePaid` notes and tickets cancelled-but-paid orders |
| MarketCheckout.php P2:733–736 | Refund or capture kept only as note text; invoice and status unchanged | single-pass (P2) | med | Book the refund via `BizQuotes` and a `refunded_cents` column |
| MarketCheckout.php P2:657–664,807–810,608 | Link-to-copy failure writes no COPY_HELD; order expires and the direct payment never reaches it | single-pass (P2) | med | Write the COPY_HELD note in the catch |
| MarketCheckout.php P2:729–733 | Deposit refund capped at deposit lines, not amount charged | single-pass (P2) | low | `min($back, self::due($o))` |
| MarketCheckout.php P2:732 | Square capture called without access token (signature not shown) | single-pass (P2) | low | Pass `self::access($pdo,$g)` |
| MarketPay.php P1:290,150; P2:290 (with 150) | Square idempotency key per card token; retry after timeout double-charges; first charge unrecorded | P1, P2 | high | Key on order + `due()` (verify Square rejects a reused key with new params), or a pending-charge marker |
| MarketPay.php P1:261–265,310–312; P2:261 vs 265,312 | Hold with deposit charged outright; confirm wants `total_cents`, so it never reads paid | P1, P2 | high | Branch on hold before deposit (`$mode !== 'hold' &&` at :261); confirm against `due()` |
| MarketPay.php P1:266–269,278–285 vs 314; P2:266–269,281–283 vs 312,314 | Session charges the line sum; confirm checks `due()`; mismatch leaves a charge unrecorded | P1, P2 | med | Assert line sum == `due()` before creating the session |
| MarketPay.php P1:381–382; P2:381,382 | Refund key = ref + cents: equal partial refunds collapse; Stripe key expiry; no refundable cap | P1, P2 | med | Key on the refund-record id; cap at captured − refunded |
| MarketP