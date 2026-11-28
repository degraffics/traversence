# Client order emails through the one dispatcher (0172): draft patches

**Skipped, and why:**
- **M2b, "how an order goes" (two legs):** paused by Jason on 2026-11-24 ("Pause for now. add to work queue."). Nothing here touches stages or legs. The patches only change who carries the existing emails.
- **Order #12's books:** open with the Founder ("I will address this after holiday"). Nothing here touches money or invoices.
- **M7 credit terms (Net 15 / Net 30):** granted by Jason (0178). Not drafted.
- **Texting (0172 step 8):** needs its own decision (0172 "Still open"). No text channel here.
- **Status of this item in the Work queue:** the Work queue is the queue, and it lives in the database, which isn't attached. The lane must check that this task is open and not paused before building.
- **Telling staff about the client's answers** (0172 §2.4 says "buyer; staff"): `staffEmail()` is shown but its callers aren't (file truncated). Not drafted.
- **Unseen code:** `ClientOrders::mail()` itself, `email/stop.php`, `CommsSetup`, the test harness, `faq.md` and 0172 §4 and §6 are **file not shown**. No content is invented for them.

---

## 1. Plan

1. **Two new kinds in `Notify::KINDS`:**
   - `order.client` at Notice, for a client order being placed, updated, at a stage, done or cancelled (0172 §2.4: "Client order (ours) … Notice").
   - `order.invoice` at