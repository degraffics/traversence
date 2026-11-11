# Payments and video: hands-on checklist (2026-11-11)

What went live on 2026-11-11 (decisions/0153 §8, 0154 §5–6): plans and packs in the one cart, Try Pro free for 7 days,
Keep in 4K ($0.99), a single month of Pro, and the video worker. Each was tested on a local database with QuickBooks
in mock mode; this is the first run on the live site. Run it as the test accounts (`test-accounts.md`): they pay in the
QuickBooks sandbox only (card 4111 1111 1111 1111, any future date, any 3-digit code) and their emails aren't sent.
Status: not run yet (to pick up 2026-11-12). Send Claude the step number and what the screen said for anything that
doesn't match.

1. **Free week of Pro** (Test Member, free plan): `/plans.php?me=1` → **Start my free week**. Expect "Personal Pro is
   yours free until …", the box gone, Trial with its end date after a reload, and no second offer.
2. **Plan into the cart** (a member who hasn't used the free week, e.g. Test New Member): Shop → **Add to cart** on
   Personal Plus. Expect the cart with it under Plans and storage, the header cart showing 1, and Continue shopping /
   Checkout.
3. **Pay for the plan:** **Continue to payment**, the test card. Expect the Plans page with Personal Plus active and the
   card on file; the cart empty.
4. **A pack on a paid plan:** Shop → **Add to cart** on +25 GB, pay. Expect no card form ("on your card on file"); the
   pack on the Plans page.
5. **Plan and GO Codes together** (Test Business Owner, acting as the test listing): a business plan from the Shop and a
   GO Code from `/qr/` in the cart; check out with the billing address and pay once. Expect "Pay $… in all", one card
   entry, the plan active, the code in My GO Codes.
6. **A declined card:** anything in the cart, card 4000 0000 0000 0002. Expect "The card wasn't accepted… Nothing was
   charged", the item still in the cart, and 4111… then working.
7. **A month of Pro** (a member with no renewing plan): Shop → A month of Personal Pro → Add to cart, pay. Expect Pro
   until a month from today, set to stop, no card; a second month moves the end a month later.
8. **Keep in 4K** (free plan): post a 1080p/4K video with Photo/video, wait about 5 minutes for the worker (Railway log),
   then the post's ⋯ → **Keep in 4K · $0.99**, pay. Expect the post kept.
9. **Watch in 4K** (the Pro member from step 1): post a 1080p/4K video, wait about 5 minutes. Expect **Watch in 4K** (or
   HD) switching to the original from the same moment.
10. **The 2-minute limit** (free plan): **Record** to 0:00. Expect the sheet with **Try Pro free for 7 days** and
    **Personal Pro**, beside **Keep my 2 minutes**; once a visit.
