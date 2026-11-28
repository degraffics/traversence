=== FILE: faq/faq-entries.md ===
# FAQ part 03 (lines 514–751): replacement blocks

**No replacement blocks in this release.**

The job 6 promise check came through empty:

`/tmp/claude-0/-home-user/d9fd7c97-9e1f-53c1-b650-d50e5825cce4/scratchpad/n/plain_traversence__research__ai-results__job6-promise-check__promise-faq-part-03.md.c40000`

The `.c40000` suffix suggests it is a chunked or truncated copy that didn't carry over. With no findings, there is nothing to fix. Rewording FAQ entries without findings would mean making up promises to correct, so every entry in faq-part-03 stays as it is.

Two other things are missing that the brief requires:

- **faq.php is not attached.** Even with findings, no heading could be reworded, because the `faq.php#` anchors can't be checked.
- **None of the code behind these entries is attached.** That includes Pulse, the System Control Board, search, the crawler, Billing, Claims and Takedowns. So no FAQ claim in lines 514–751 can be confirmed or disproved against code.

Next step for the lane session: re-run or re-attach the job 6 answer for faq-part-03, plus `faq.php` and the libraries it names. Then regenerate this file.
=== END FILE ===

=== FILE: faq/page-wording.md ===
# Page-wording fixes (plans, our-approach, privacy, terms, takedown, data-deletion, claim)

**No OLD/NEW blocks in this release.**

The job 6 promise check came through empty, so it names no page-wording findings to fix.

While reading the attached pages side by side, I noticed some possible mismatches. These are not job 6 findings, and the code behind them is not attached, so I have not reworded anything here. They are listed in release.json "verify" for the lane session to confirm first. If the job 6 answer or the code confirms them, they become OLD/NEW blocks in the next draft.
=== END FILE ===

=== FILE: release.json ===
{
  "name": "faq-part-03-promise-fixes",
  "gate": "blocked: job 6 promise-check input is empty; no fixes drafted",
  "what": "Was meant to turn the job 6 promise-check findings for faq-part-03 (lines 514–751) and the seven pages into OLD/NEW replacement blocks. The attached job 6 answer is empty, so this release changes no files and drafts no rewording. It records the gap and lists possible mismatches seen across the attached pages for the lane session to verify.",
  "files": [
    "faq/faq-entries.md",
    "faq/page-wording.md",
    "release.json"
  ],
  "decisions": [
    "No FAQ or page text was changed. The promise check that should name what to fix is empty (file ...job6-promise-check__promise-faq-part-03.md.c40000, zero content), and the brief says to fix only the findings it lists, so nothing was invented.",
    "No live PHP files are changed, so there is nothing under files/ and regions is false.",
    "No headings reworded: faq.php is not attached, so the faq.php# anchors could not be checked.",
    "Possible page mismatches spotted while reading the attachments are listed under verify, not drafted as fixes, because they are not job 6 findings and the code behind them is not attached."
  ],
  "tests": [],
  "risks": [
    "Nothing in this release was run, tested, verified or zip-checked. It is a draft for the lane session.",
    "The FAQ promises in lines 514–751 remain unchecked against code. Any overpromise job 6 was meant to catch is still live.",
    "The .c40000 suffix on the job 6 file suggests a chunked or truncated copy. The real answer may exist elsewhere and needs re-attaching."
  ],
  "verify": [
    "Re-attach the job 6 promise-check answer for faq-part-03 (the attached file is empty), then regenerate faq/faq-entries.md and faq/page-wording.md.",
    "faq.php: file not shown. Needed to check anchors before any heading is reworded, and to confirm anchors linked from the pages: #what-does-ai-assist-do (plans.php line 70) and #can-my-account-be-limited-or-revoked (privacy.php line 113).",
    "Code behind the FAQ entries: file not shown. This covers Pulse/stream, System Control Board (trips, Recovered, IR-####, outside site check), Communications/mailboxes/My inbox, Scheduled tasks, Marketplace test posts, place photos, search (suggestions, near me, OpenStreetMap, situations, Did you mean, how-to guides, Wikipedia/Claude summaries), crawler merging, and Pairs with / Make a day of it. None can be confirmed or disproved from the attachments.",
    "our-approach.php line 71 says 'Download and delete your data: coming soon.' But privacy.php line 37 ('You can see, change, download or delete your information'), privacy.php line 138 and data-deletion.php lines 24–30 describe self-service deletion at /user/delete-account.php. /user/delete-account.php and any data-download tool: file not shown. Confirm which is built before rewording either page.",
    "our-approach.php line 80 says the privacy policy 'is being written'. privacy.php lines 21–23 serve a published LegalTexts version when one exists. LegalTexts.php: file not shown. Whether a version is published is unknown.",
    "terms.php line 97 says a free trial 'starts only after you review it and agree to these Terms'. plans.php lines 66–68 and 101–105 start the personal free week with one button calling api action 'try', with no terms-agreement step on the page. /api/plans.php and Billing.php: file not shown; the agreement may be enforced there.",
    "terms.php line 97 says to 'choose Keep my plan'. plans.php line 179 labels the button 'Keep ' + the plan name + price. Minor wording difference; confirm against Billing.",
    "terms.php line 94 (yearly reminder 7 days before renewal), line 97 (trial emails 5 days, 1 day and on the day) and line 99 (retry after 3 and 7 days): Billing.php and the scheduled tasks are not shown. plans.php shows only s.next_try.",
    "claim.php line 226 offers businesses 'Free, or a paid plan (free trial for now)'. plans.php only offers the self-trial for type 'user' (line 66); a listing trial may come from another path. Billing.php / Claims.php: file not shown.",
    "plans.php line 84 ('Each payment is also emailed to you, with a receipt from QuickBooks') and terms.php line 94: the receipt email code is not shown.",
    "takedown.php line 51 (protected reasons come down at once, others within 24 hours): Takedowns.php is not shown. The page's own success text (line 55) depends on $done['hidden'] and hide_by from that library.",
    "privacy.php line 138 ('you have 30 days to change your mind') vs data-deletion.php line 29 ('Or choose to delete it right away'). Not a contradiction, but privacy.php leaves out the immediate option. /user/delete-account.php: file not shown."
  ],
  "regions": false
}
=== END FILE ===