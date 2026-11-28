The attached promise check (job 6 answer) is empty: `/tmp/claude-0/.../promise-faq-part-04.md.c40000` contains no text. That file was supposed to list what needs fixing. So this draft has no replacement blocks. I didn't invent findings. While reading the slice against the attached pages, I noticed a few possible mismatches. I listed them under `verify` for the lane session to check, not as fixes.

=== FILE: faq/faq-entries.md ===
# FAQ replacement blocks: faq-part-04 (lines 752–1054)

DRAFT. Not applied, not verified.

No replacement blocks in this release.

The input that should list the findings, the promise check (job 6 answer), was attached empty (0 bytes) at:
/tmp/claude-0/-home-user/d9fd7c97-9e1f-53c1-b650-d50e5825cce4/scratchpad/n/plain_traversence__research__ai-results__job6-promise-check__promise-faq-part-04.md.c40000

Without findings there's nothing to fix, and no FAQ entry was reworded. No faq.php# anchor is affected.

Possible mismatches noticed while reading are listed in release.json "verify" for the lane session to confirm. None is applied here.
=== END FILE ===

=== FILE: faq/page-wording.md ===
# Page-wording replacement blocks (plans, our-approach, privacy, terms, takedown, data-deletion, claim)

DRAFT. Not applied, not verified.

No replacement blocks in this release.

The promise check (job 6 answer) that should name the page-wording findings was attached empty, so no page wording was changed.

Possible mismatches between the attached pages are listed in release.json "verify". Once a filled-in promise check confirms them, they can become OLD/NEW blocks.
=== END FILE ===

=== FILE: release.json ===
{
  "name": "faq-part-04 promise fixes (job 6 follow-up)",
  "gate": "blocked: the promise check input (job 6 answer) was attached empty; re-run with the findings file attached",
  "what": "Was meant to turn the job 6 promise-check findings for FAQ lines 752–1054 into OLD/NEW replacement blocks (faq/faq-entries.md) and page-wording blocks (faq/page-wording.md). The findings file arrived empty, so both files contain no blocks. Nothing was invented; observed candidates are listed under verify only.",
  "files": [
    "faq/faq-entries.md",
    "faq/page-wording.md",
    "release.json"
  ],
  "decisions": [
    "No findings were made up: the brief says to fix what the promise check found, and the attached check is empty (0 bytes).",
    "Candidate mismatches noticed while reading the slice and pages are listed under verify, not written as OLD/NEW blocks, because the output files are machine-applied.",
    "No live PHP file is changed, so no regions and no tests."
  ],
  "tests": [],
  "risks": [
    "Nothing here was run, tested, applied or zip-checked; this is a draft for the lane session.",
    "If the job 6 findings exist elsewhere, this release fixes none of them; FAQ part 04 may still promise more than the code does.",
    "The candidates under verify come from a quick read, not from the promise check, and may be incomplete or wrong."
  ],
  "verify": [
    "Promise-check input empty: /tmp/claude-0/-home-user/d9fd7c97-9e1f-53c1-b650-d50e5825cce4/scratchpad/n/plain_traversence__research__ai-results__job6-promise-check__promise-faq-part-04.md.c40000 has no content. Re-attach the job 6 answer for faq-part-04 and re-run.",
    "Candidate (privacy vs our-approach): privacy.php line 37 says 'You can see, change, download or delete your information, and change your choices any time.' while our-approach.php line 71 says '<li>Download and delete your data: coming soon.</li>'. data-deletion.php shows deleting is built; no download/export code is attached. Check which is true and mark download 'coming' if unbuilt.",
    "Candidate (terms vs plans): terms.php line 97 says 'To keep the plan, set up billing (add a card and choose <i>Keep my plan</i>)'. plans.php labels that button with \"label: (s ? 'Keep ' : 'Start ') + p.name\", so the button reads 'Keep <plan name>', not 'Keep my plan'.",
    "Candidate (terms vs plans): terms.php line 97 says 'A free trial starts only after you review it and agree to these Terms.' The plans.php trial card (lines 67–68) shows 'Start my free week' with no Terms link or agreement step; /api/plans.php action 'try' is not shown. Check whether agreeing to the Terms is really required.",
    "Candidate (terms, unconfirmed): terms.php line 94 'a yearly plan gets a reminder 7 days before it renews' and line 97 'We email you 5 days and 1 day before it ends, and on the day'. Billing.php and the renewal/trial mailers: file not shown.",
    "Candidate (our-approach vs privacy): our-approach.php line 41 'Your email and location are never shown to anyone.' versus privacy.php line 112 'Your email and phone are shown only when needed to verify you or contact you officially'. Staff can see email in some cases, so check the wording.",
    "Candidate (FAQ vs claim): FAQ line 1025 '3. **Choose a plan.** Free, or a paid plan (see below).' versus claim.php line 226 'Free, or a paid plan (free trial for now).'. Check whether paid plans are on sale or trial-only now.",
    "Unconfirmable from the attached code (file not shown): app/lib/Claims.php, js/claim.js, app/lib/Billing.php, api/plans.php, app/lib/Takedowns.php, faq.php (for its # anchors), listing/view.php, and the search, map and crawler code behind most FAQ lines 752–1010."
  ],
  "regions": false
}
=== END FILE ===