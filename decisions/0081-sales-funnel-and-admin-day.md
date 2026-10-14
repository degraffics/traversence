# 0081. Sales: unclaimed listings become active ones; the admin dashboard is a day's work in steps

- **Status:** Accepted
- **Date:** 2026-10-14
- **Builds on:** 0076 (listings build themselves), 0078 (admin dashboard), 0070 (claims)

## Context

Jason, 2026-10-14:

- **The Helper:** "Why is it still having issues with things that are almost identical?" It asked about
  "Navajo Health Foundation-Sage Memorial Hospital, Inc." against listed "Sage Memorial Hospital": same phone,
  website, town, ZIP and kind. Today said 432 waiting while Review said 11.
- **The admin dashboard:** "I see a bunch of buttons but no real process for me to follow… I need to be able to
  login, address issues, and do admin or sales tasks that push the progress of the site and the business forward."
  The inbox and notifications seemed lost.
- **Listings & places:** "1 listing claimed out of 4k+, no way to see the new listings added easily and/or target
  unclaimed listings for prospecting. The sales funnel and converting unclaimed listings into active listings is
  essential." And: "As I collect physical info I need a clean way to input new listings and put them into the funnel."

## Decision

1. **Almost identical is the same place.** A name that is the other with a parent or legal name in front ("Navajo
   Health Foundation-Sage Memorial Hospital, Inc." ends with "Sage Memorial Hospital", two words or more) is the same
   place when it also has:
   - the same phone, or the same website;
   - the same ZIP;
   - no different street number.

   It merges on its own, filling gaps only (`AutoImport::nameEndsWith`). A name with more after the other ("Sage
   Memorial Hospital Behavioral Health Clinic") is a department and still asks a person. Shared platforms (Facebook,
   Wix…) don't count as the same website. The merge note says what matched.
2. **Only what needs a person is counted as waiting.** The Helper, Today and the menu badges count only the listings
   the pipeline has given up on (`AutoImport::exhausted`, `tv_listings_need_person`), the same number Review shows.
   The rest show as "still with the crawler".
3. **Sales** (`/admin/sales.php`, `app/lib/Sales.php`, tables `prospects` and `prospect_log`), second in the admin
   menu:
   - **Calls today:**
     - follow-ups due;
     - then the 10 best to call next: not contacted, with a phone, ranked by views and taps in the last 30 days, how
       reachable they are, and newness.
   - **All prospects:** every unclaimed listing worth a call, filtered by name or phone, town, category, stage and
     contact, and sorted by best, most viewed, newest, A to Z or follow-up date.
   - **Each prospect** shows:
     - call;
     - log a contact (call, visit, email, text, note) with what happened: no answer, left a message, interested,
       sent the claim link, not now, not interested, closed;
     - who to ask for;
     - a note.

     The next follow-up is set from what happened (2, 3 or 30 days) and can be changed. The history is kept.
   - **Claim link:**
     - copy it to text or say it;
     - "I sent it", to log the invite;
     - email it, one at a time from a person.

     Emailing needs `BUSINESS_POSTAL_ADDRESS` in `.env`, which every commercial email must carry, and says how to opt
     out. "Not interested" stops further invites.
   - **Add from the field:** a business seen in person is listed right away (unclaimed; the crawler fills in the
     rest) and enters the funnel with the visit logged. A possible duplicate offers "It's this one: log the visit".
   - **New this week:** every listing added in the last 7 days.
   - **Funnel:** not contacted → contacted → interested → invite sent → claimed → verified → paying. Also the week's
     contacts, invites, claims started and listings added.
   - Listings & places links to Sales from its counts of unclaimed and new listings.
4. **The admin dashboard is the day, as steps:**
   1. Safety first;
   2. People waiting;
   3. Sales (follow-ups due, and 10 contacts a day as the pace);
   4. Listings;
   5. Search & content;
   6. System.

   - Each step shows its count and one button to start; it shows ✓ Clear when there's nothing left, and its kinds of
     work fold out.
   - Beside the steps, one **Inbox**: what came in, newest first (reports, claims, review requests, owners' new
     listings, visitors' notes, crawler failures, your own unread messages). What's new since your last visit is
     marked.
   - Below: **how the business is doing**: claimed and paying listings, claims started, contacts, the funnel, new
     listings, members, searches, views.
   - The system's status folds into one line unless something needs a look.
5. **FAQ:** "Why did Traversence contact my business?"
6. **A missing kind named for what you buy goes under Retail & Shopping.** A draft kind like "Kitchen Supplies",
   "Home Goods" or "Bike Shop" (…Supplies, …Goods, …Store, …Shop, …Wares, …Accessories, …Outlet, …Dealers) is
   placed under Retail & Shopping, whatever its words lean toward. "Kitchen" leaned to Food & Dining
   (`SearchDrafts::refresh`).

## Consequences

- Run `app/migrations/2026-11-18_sales.sql`.
- Staff calls from Sales aren't counted as visitors' taps.
- Next:
  - a prospect assigned to a sales rep and their territory (the General role's `leads`);
  - a daily goal set per person;
  - bulk import of a list of businesses collected offline;
  - a printable leave-behind with the claim link and a QR code.
