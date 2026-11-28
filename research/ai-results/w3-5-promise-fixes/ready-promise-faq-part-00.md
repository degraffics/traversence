=== FILE: faq/faq-entries.md ===
# FAQ replacement blocks (faq/faq-entries.md)

This file has no replacement blocks.

The promise check this release is based on (job 6 answer, `promise-faq-part-00.md`) came through empty. With no findings to work from, no FAQ entry in the slice (faq-part-00.txt) has been reworded.

I did not invent findings. Some places where the attached files disagree are listed in release.json under "verify". Each one should be checked against the job 6 answer and the code it names before any wording changes.

No headings were changed, so no faq.php# anchors are affected. For example, `#why-do-some-photos-say-stock-photo`, `#what-does-ai-assist-do` and `#can-my-account-be-limited-or-revoked`, which other pages link to, are untouched.
=== END FILE ===
=== FILE: faq/page-wording.md ===
# Page-wording replacement blocks (faq/page-wording.md)

This file has no replacement blocks for plans, our-approach, privacy, terms, takedown, data-deletion or claim.

The promise check (job 6 answer) came through empty, so no finding could be checked against the attached page code. Nothing has been reworded.

Possible wording conflicts between the attached pages are listed in release.json under "verify". None of them comes from job 6, and none has been changed here.
=== END FILE ===
=== FILE: release.json ===
{
  "name": "faq-promise-fixes-part-00",
  "gate": "HOLD: no findings to apply. The job 6 promise-check answer for faq-part-00 came through empty.",
  "what": "Was meant to replace FAQ and page wording that promises more than the code does, based on the job 6 promise check. The findings file is empty, so this release has no replacement blocks. It only lists what to verify.",
  "files": [
    "faq/faq-entries.md",
    "faq/page-wording.md",
    "release.json"
  ],
  "decisions": [
    "No FAQ or page wording changed. Every fix had to come from the job 6 findings and be confirmed by the attached code, and the findings file is empty.",
    "No headings changed, so all faq.php# anchors stay as they are.",
    "No live PHP files changed, so there are no files/ entries and no tests.",
    "Conflicts noticed between the attached pages are recorded under 'verify' as candidates only. They were not applied, because they are not job 6 findings and most depend on code that wasn't attached."
  ],
  "tests": [],
  "risks": [
    "Nothing in this release was run, tested, verified or zip-checked. It is a draft for the lane session.",
    "If the job 6 answer does have findings and only the copy was empty, this release fixes none of them. Rerun this job with the real file attached.",
    "Visitors keep seeing the current wording until the real findings are applied."
  ],
  "verify": [
    "file not shown: the job 6 promise-check answer (scratchpad .../job6-promise-check__promise-faq-part-00.md.c40000) came through empty. Attach the real findings and rerun.",
    "Candidate (not from job 6): privacy.php line 37 says 'You can see, change, download or delete your information', but our-approach.php line 71 says 'Download and delete your data: coming soon.' Deletion exists (data-deletion.php, /user/delete-account.php). Whether download exists is unknown; the export code is not shown.",
    "Candidate (not from job 6): FAQ 'Why are names in my journey highlighted?' (slice line 101) says Claude reads your words only with AI Assist & Learning on AND a paid plan. privacy.php line 79 mentions only AI Assist & Learning. The tagging code is not shown, so check which condition the code actually uses.",
    "Candidate (not from job 6): FAQ 'How do I ask you to take something down?' (slice line 168) says a tribal nation's request comes down the moment it's sent. takedown.php line 51 names only a sacred place, privacy, or a risk to safety. Takedowns::REASONS (app/lib/Takedowns.php) is not shown.",
    "Candidate (not from job 6): terms.php line 97 promises trial emails 5 days and 1 day before the trial ends and on the day. Line 94 promises a reminder 7 days before a yearly plan renews. Billing.php and the mail jobs are not shown.",
    "Candidate (not from job 6): terms.php line 99 says a declined renewal is retried after 3 and 7 days. plans.php only shows s.next_try. Billing.php is not shown.",
    "Candidate (not from job 6): plans.php line 70 says '3 free asks a day' for AI Assist. The allowance code is not shown.",
    "Candidate (not from job 6): privacy.php line 138 says 'you have 30 days to change your mind, then it's permanent', but data-deletion.php line 29 also offers 'delete it right away'. Wording could be made consistent; /user/delete-account.php is not shown.",
    "Candidate (not from job 6): claim.php line 226 says 'Free, or a paid plan (free trial for now)'. Check this against Billing::selfTrialWhyNot and the trial rules (Billing.php not shown).",
    "Candidate (not from job 6): visitor-facing FAQ text contains build dates such as '(2026-11-20)' (slice line 212) and '(2026-11-26)' (line 266). This is a style issue, not a promise; confirm whether it is intended."
  ],
  "regions": false
}
=== END FILE ===