# 0120: Handing over a business; proofs and files on quotes, counting against storage

Status: Built (2026-10-24). Jason: "Should a business owner be able to transfer ownership themselves, with the new
owner accepting? Yes, this is already a standard. See docs." (decisions/0103 §2) and "Should businesses get proofs and
files on their quotes, counting against their storage? Yes."

## Handing over (0103 §2, "By hand")

- **The Owner asks** (Business portal → Plan & settings → Hand over the business): someone on the team, or any
  member account by email (not a staff login). One open request at a time; asking again replaces it; Cancel withdraws
  it. Stored in `transfers` (kind `business`, from, to, requested, expires, accepted / declined / cancelled), as 0103 §6
  set out: `app/migrations/2026-12-26_transfers.sql`, also made on first use.
- **The successor answers** at `/user/transfer.php?id=…` (from the email, signed in as that account): what taking over
  means, Accept or Decline. **Nothing changes until they accept.** The request lapses after 30 days (0103's ⏱30).
  It also lapses if the one who asked is no longer the Owner.
- **On accept:** the successor is the Owner (team, `listing_access`, `entities.owner_user_id`), the one before stays
  as Manager, everyone on the team is emailed, and it's in the action log. Decline tells the Owner.
- **The plan:** the previous Owner's card comes off the listing's subscription (`card_id` cleared,
  `cancel_at_end` set), so they're never charged for a business that isn't theirs; the plan runs to the end of its paid
  period, and the new Owner is emailed to add their card and keep it (Plans page). The same happens when our team
  assigns a new Owner from the admin (Listings → Owner & team).
- **Terms** `#business-handover`; FAQ "How do I hand my business over to someone else?".
- **Not yet:** 0103's automatic succession when an Owner is gone (deleted account, removed) and its daily job.

## Proofs and files on quotes

- **Records** in the business's walled data (`b_biz_files`, BusinessData VERSION 5: 14 tables, all walled);
  **files** under `app/storage/biz-files/<business>/` (never served directly: the storage folder denies the web).
- **The team** (Quotes view to see, edit to change): a quote's card links to **Proofs & files**
  (`/business/<listing>/quotes?files=<quote>`): upload a **proof version** (PDF or images; all files in one upload
  share the version; an unanswered earlier version is marked replaced; an answered one keeps its answer as the record
  and can't be removed) or **files** (also Word, Excel, PowerPoint, text, CSV, ZIP), each shared with the customer or
  kept for the team; up to 12 at a time, 25 MB each; remove a file to free its space. `business/files.php` uploads,
  hands out and removes, after the portal's role and plan checks; White Glove changes are logged.
- **The customer** (on `/q.php`): the current proof version (pictures shown inline) with **Approve the proof** or
  **Ask for changes** (what to change), and the shared files; the business's owner is emailed either way, and the
  quote's history records it. Files come through `q-file.php`, keyed by the quote's private link: only its proofs and
  shared files, never the team's own; another business's number is a 404. Files are sent with `nosniff` and a
  sandboxing CSP; anything but a PDF or picture downloads instead of opening.
- **Storage:** the first meter of a business's storage (`BizFiles::used`, the bytes of its files) against its plan's
  allowance (`Billing::allowance`, packs included). Shown on the Overview and each quote's Proofs & files page; an
  upload past it is refused with what's used. Other tools' files join the meter as they store files.
- **Terms** (business quotes: approving a proof; files and storage) and FAQ ("Can I send proofs and files with a
  quote?", "How much storage does my business have?").
