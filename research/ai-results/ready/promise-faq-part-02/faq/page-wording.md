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
