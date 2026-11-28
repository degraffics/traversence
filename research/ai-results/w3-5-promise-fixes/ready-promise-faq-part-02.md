=== FILE: faq/faq-entries.md ===
# FAQ replacement blocks: faq-part-02 (lines 383–513)

No replacement blocks in this draft.

The promise check (job 6 answer) was attached at
`/tmp/claude-0/-home-user/d9fd7c97-9e1f-53c1-b650-d50e5825cce4/scratchpad/n/plain_traversence__research__ai-results__job6-promise-check__promise-faq-part-02.md.c40000`,
but the file came through empty. With no findings to work from, no FAQ text has been reworded. Writing OLD/NEW blocks without its findings would mean guessing what it flagged.

These are passages that look like candidates on a plain read. They are listed in release.json "verify" and are not changed here:

- Line 448 ends "Coming: the **System Control Board** (System Configuration → System Control Board), which shows each process with what passes along its route, its alarms, its log and its reports." Lines 383–418 and 450–475 describe that board as built.
- Line 403 says "(a trip already open stays a trip until an admin resolves it)". Line 389 says "When its check passes, a trip clears itself (2026-11-20), escalated or not".
- Line 390 says "If its tries run out, an **Escalation Notice** … follows, and only **Resolve** clears it." Line 389 says a trip clears itself "escalated or not" once its check passes.
- Line 391 says "pushed to the Founder's phone at the session manager's next hourly check". Line 465 says "A trip goes at once, whatever your quiet hours".

No faq.php# anchors were checked, because no heading is reworded (faq.php is not shown).
=== END FILE ===
=== FILE: faq/page-wording.md ===
# Page-wording replacement blocks (plans, our-approach, privacy, terms, takedown, data-deletion, claim)

No replacement blocks in this draft.

The promise check (job 6 answer) that should name the page findings is empty, so no page wording has been changed. The page sources were read only to note possible mismatches. Each one is listed in release.json "verify" for the lane session to check against the real findings and the code. None is confirmed by attached code.

- page-our-approach.php line 71: "Download and delete your data: coming soon." But page-data-deletion.php lines 24–30 and page-privacy.php line 138 describe self-service deletion at /user/delete-account.php. That file is not shown. Download is not described as built anywhere attached.
- page-our-approach.php line 41: "Your email and location are never shown to anyone." But page-privacy.php line 112 says staff are shown email and phone "only when needed to verify you or contact you officially". Page-claim.php line 151 says the business email "isn't shown on your listing".
- page-privacy.php line 37: "You can see, change, download or delete your information". No download feature is shown in any attached file.
- page-terms.php line 94: "a yearly plan gets a reminder 7 days before it renews". page-terms.php line 97: "We email you 5 days and 1 day before it ends, and on the day". Billing.php and the mailers are not shown.
- page-terms.php line 99: "we try again after 3 and 7 days". page-plans.php line 115 shows only `s.next_try`. Billing.php is not shown.
- page-claim.php line 226: "Free, or a paid plan (free trial for now)." page-plans.php line 66 offers a once-only, no-card 7-day Personal Pro trial (`type === 'user'`) and paid plans for listings. Whether a business claim gets a trial depends on Claims.php and Billing.php, which are not shown.
- page-takedown.php line 51: "Anything else comes down within 24 hours while a person looks." Line 55 renders `$done['hide_by']`. Takedowns.php is not shown, so the 24-hour value is unconfirmed.
- page-plans.php line 84: "Each payment is also emailed to you, with a receipt from QuickBooks." The mailer and QuickBooksPay are not shown.
=== END FILE ===
=== FILE: release.json ===
{
  "name": "faq-part-02 promise fixes (job 6): held, no findings received",
  "gate": "HOLD: the job 6 promise-check input is empty; nothing to apply until it is re-attached",
  "what": "Draft only. The promise check meant to drive this release was attached empty, so no FAQ entry or page wording is changed. faq/faq-entries.md and faq/page-wording.md hold no replacement blocks. They list possible mismatches noticed on a plain read, for the lane session to compare with the real findings.",
  "files": [
    "faq/faq-entries.md",
    "faq/page-wording.md",
    "release.json"
  ],
  "decisions": [
    "No OLD/NEW blocks were written without the promise check's findings. Inventing findings would risk rewording true promises or missing the real ones.",
    "No live PHP file is changed, so there are no files/ entries and no regions.",
    "No faq.php# anchors were touched, because no heading is reworded."
  ],
  "tests": [],
  "risks": [
    "Nothing here has been run, tested or zip-checked. This is a draft for the lane session.",
    "The candidate mismatches listed come from reading text only. None is confirmed by attached code, and some may be false positives.",
    "If this release ships as is, it changes nothing. The FAQ and page promises the check found stay unfixed until the check is re-attached."
  ],
  "verify": [
    "file not shown (empty): the promise check, job 6 answer, plain_traversence__research__ai-results__job6-promise-check__promise-faq-part-02.md.c40000. Re-attach it and rerun this job.",
    "faq-part-02 line 448: 'Coming: the System Control Board' versus lines 383–418, which describe the board as built. Board code is not shown.",
    "faq-part-02 line 403: '(a trip already open stays a trip until an admin resolves it)' versus line 389: 'a trip clears itself (2026-11-20), escalated or not'. Code not shown.",
    "faq-part-02 line 390: 'only **Resolve** clears it' (Escalation Notice) versus line 389's self-clearing. Code not shown.",
    "faq-part-02 line 391: push 'at the session manager's next hourly check' versus line 465: 'A trip goes at once, whatever your quiet hours'. Code not shown.",
    "page-our-approach.php line 71: 'Download and delete your data: coming soon.' Deletion looks built (page-data-deletion.php, /user/delete-account.php). file not shown: user/delete-account.php.",
    "page-our-approach.php line 41: 'Your email and location are never shown to anyone' versus page-privacy.php line 112 (staff see email and phone when needed).",
    "page-privacy.php line 37: 'download' your information. No download feature appears in any attached file.",
    "page-terms.php lines 94, 97 and 99: renewal reminder 7 days, trial emails at 5 days, 1 day and the day, retries after 3 and 7 days. file not shown: app/lib/Billing.php.",
    "page-claim.php line 226: 'Free, or a paid plan (free trial for now).' file not shown: app/lib/Claims.php and app/lib/Billing.php.",
    "page-takedown.php line 51: 'within 24 hours'. file not shown: app/lib/Takedowns.php (hide_by).",
    "page-plans.php line 84: 'emailed to you, with a receipt from QuickBooks'. file not shown: api/plans.php and app/lib/QuickBooksPay.php.",
    "file not shown: faq.php. Anchors were not checked, and none were needed since no heading changed."
  ],
  "regions": false
}
=== END FILE ===