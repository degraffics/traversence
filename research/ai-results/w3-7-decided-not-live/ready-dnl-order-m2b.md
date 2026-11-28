**Skipped: M2b is paused by Jason, so this release has no patches, tests or FAQ entries.**

Two attached files say so:
- `handoffs/decided-not-live.md` (Building now): "**Paused by Jason 2026-11-24 ("Pause for now. add to work queue.")**".
- `handoffs/order-management-m2.md` (Final hand-off): "M2b, decided, built, reviewed, paused (not in flight) … PR #1346 merged as the record only".

Your instruction is to skip anything paused, and this is the whole item. The open Work-queue parents are in the live database and aren't attached, so nothing attached shows that Jason has resumed it.

**Review of the job 19 draft:** it reaches the right conclusion, and its "how to resume" plan matches the Final hand-off. Five points to correct or add:

1. **Test count.** The draft says `m2b.php` has 35 tests. The M2b-built section of the hand-off says 31; only the Final hand-off says 35. The lane should use the suite as it stands in the repo, not either number.
2. **`moveItems` has an exact anchor for a resume.** The live line M2b replaces is `if ($to === 'delivered' && in_array($w['delivery'], ['will_call', 'internal', 'local'], true)) return '';   // it came to us: we take it from here`. M2b changes this to a `goesTo()` check.
3. **`setTracking` has a live defect M2b fixes.** Live `setTracking` writes "Courier booked: …" to the history before the vendor email. It then swallows a `Mailer::send` failure with only `error_log`. This is the defect M2b's `_told` / `tellVendor` change addresses, and it stays live while M2b is paused.
4. **The draft is right that `ClientOrders.php` is truncated.** The cut is inside `boardState`'s Order section. `deliverables()`, `delivery()`, `outTheDoor()` and `itemStatus()` aren't visible, so whole-function regions can't be quoted from what's attached.
5. **Queue order holds.** M3's first fix (order tests green again) comes before M2b in both `decided-not-live.md` (Orders item 0) and the hand-off ("Next, in order" 1).

=== FILE: release.json ===
{
  "name": "dnl-order-m2b (skipped: paused by Jason)",
  "gate": "SKIPPED. Not for release. handoffs/decided-not-live.md: \"Paused by Jason 2026-11-24 (\\\"Pause for now. add to work queue.\\\")\"; handoffs/order-management-m2.md (Final hand-off): \"M2b, decided, built, reviewed, paused (not in flight)\".",
  "what": "Nothing is changed. The item 'Change how an order goes: two legs on 0101's existing fields (M2b; PR #1346 merged as the record)' is paused by Jason, and the brief says to skip anything paused. No site files, no tests, no FAQ entries, no migration. The build stays in handoffs/order-m2b/order-m2b.zip (built on Sync 02:52 UTC, 2eab529) as the record.",
  "files": [],
  "decisions": [
    "decisions/0101 note (2026-11-24): M2b approved (Jason's Approve and Yes). That note itself is not attached: the attached 0101 is truncated before it.",
    "Jason, 2026-11-24: \"Pause for now. add to work queue.\" (handoffs/decided-not-live.md; handoffs/order-management-m2.md Final hand-off)",
    "From 2026-11-25 the Work queue on the System Control Board's Development page is the queue (handoffs/decided-not-live.md). Whether M2b has been resumed is recorded there, and it is not attached.",
    "Queue order (handoffs/order-management-m2.md 'Next, in order'): 1. M3's first fix (order tests green again), 2. M2b when Jason resumes it."
  ],
  "tests": [],
  "risks": [
    "Nothing here was run or verified. This is a draft decision to skip, not a tested release.",
    "The skip rests on the attached documents only. If Jason resumed M2b in the live Work queue after 2026-11-25, this skip is stale. Check the queue before acting.",
    "While M2b stays paused, live behaviour 1: WorkOrders::setTracking writes 'Courier booked: …' to the work order's history before the vendor email. A Mailer::send failure is only logged (error_log('work order tracking mail: …')), so staff can believe the vendor was told when they weren't.",
    "While M2b stays paused, live behaviour 2: ClientOrders::boardState reads a vendor's Will Call 'ready' as 'Ready for pickup at our shop' (the Order #12 case in the hand-off).",
    "If M2b is resumed, risk 1: the zip was built on 2eab529. Re-apply its diff onto the live copies; never unzip over them.",
    "If M2b is resumed, risk 2: m2b.php and m2b.cjs run on a harness the hand-off says has drifted from the live tables. Do M3's first fix first.",
    "Test-count discrepancy in the sources: m2b.php is 31 tests in 'M2b built' and 35 in the Final hand-off. Use the suite as it is in the repo."
  ],
  "verify": [
    "Confirm in the live Work queue (not attached) whether Jason has resumed M2b. If he hasn't, nothing further is needed for this item.",
    "file not shown: handoffs/order-m2b/order-m2b.zip",
    "file not shown: website/admin/client-order.php",
    "file not shown: app/admin/tools.php ('backoffice/work_orders')",
    "file not shown: app/ui/faq.md (M2b's three entries are in the zip, not attached)",
    "file not shown: decisions/0101's 2026-11-24 note (the attached 0101 is truncated before it)",
    "file truncated: app/lib/ClientOrders.php, cut inside boardState. deliverables(), delivery(), outTheDoor() and itemStatus() are not visible, so no exact ANCHOR regions can be written from the attachment.",
    "On resume, the anchor in live app/lib/WorkOrders.php::moveItems that M2b replaces with a goesTo() check: \"if ($to === 'delivered' && in_array($w['delivery'], ['will_call', 'internal', 'local'], true)) return '';   // it came to us: we take it from here\"",
    "On resume, renew the claims, which lapsed when it was paused: ClientOrders.php::boardState,deliverables (change) + legs (add); WorkOrders.php::moveItems,setTracking (change) + goesTo,legWords,setDelivery,tellVendor (add); tools.php::'backoffice/work_orders'; website/admin/client-order.php"
  ],
  "regions": false
}
=== END FILE ===