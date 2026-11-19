# claim-kinds (2026-11-19)

One release, `claim-kinds.zip`, built on Sync 23:27 UTC (020ef77). Jason approved it in the pop-up, after a review by the session manager. For plans he chose "Their own plan later". Decision note: decisions/0070 (2026-11-19). Tests: `tools/listing-tests/claim-kinds.php` (11 checks) and `claim-kinds.cjs` (33 checks at 1280 and 390).

**Files:**
- `app/lib/Claims.php`
- `website/claim.php`
- `website/js/claim.js`
- `website/listing/api/claim.php`
- `website/listing/view.php`
- `app/ui/faq.md`

**Kept, moved, dropped:**
- **Kept:** every claim step, route, hold and dispute. A business sees what it saw before, apart from the shorter button.
- **Moved:** the button's second line goes into its title.
- **Dropped:** the words "Claim this business" and "Add your own photos, hours and more".

**Next:**
- A decision on plans for agencies and nonprofits (Jason: "their own plan later").
- Claiming a whole Nexus at once (decisions/0054 §5).

**Rebuilding:** `CK_PARTS="ck ckfaq" handoffs/claim-kinds/mk-ck.sh`. Edit its `S` and `M` paths first.
