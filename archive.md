# Archive register

Pages taken out of the admin menus (decisions/0109) but still on the server. Every one is listed here with **what
replaced it**, **what still opens it**, and **what has to happen before it can go**. Nothing is removed until Jason
clears it (Jason, 2026-10-28). Once cleared, the next build removes it:
1. Re-point anything that still opens it.
2. Take it out of its section's `replaces` list in `app/admin/manifests.php`.
3. Delete it from OneDrive `Traversence/`. The sync (traverse-3way-sync) takes it off the server and keeps a copy in
   its `deleted/` folder.
4. Move its row here to **Removed**.

The site map (System Configuration → Site map) shows each archived page and the section that does its work now.

**Status:**
- **Ready to clear:** nothing opens it any more.
- **Re-point first:** one or two links still open it, and its work is already done elsewhere.
- **Working tool:** still where the work happens, shown inside a module section (a bridge), or opened from one as a
  pop-up, detail or print page. It goes only when its section is rebuilt as widgets (decisions/0109).

## Ready to clear

| Page | What it is | Its work is now in |
|---|---|---|
| `/admin/adminportal.php` | A redirect to `/admin/`, kept for old bookmarks (the first admin home) | Dashboard → Today |

## Re-point first

| Page | Still opened from | Its work is now in | To do |
|---|---|---|---|
| `/admin/logs.php` | Pulse's failed-sign-in case (`app/lib/Pulse.php`, 3 links) | System Configuration → Logs (its "Staff sign-ins" filter) | Point Pulse to `/admin/config/logs` |
| `/admin/data-crawler.php` | Safety's weekly checklist (coverage, stories) | Insights & Research → Crawler | Point the two links to the Crawler section |
| `/admin/w.php` | Old workspace links (`app/ui/admin-hub.php` maps them; `StaffLogin.php` lets them through) | The 11 modules (`/admin/<module>`) | Keep the mapping as a redirect for a while, then remove |

## Working tools (kept until their section is rebuilt as widgets)

Shown inside a section (bridge):
- `helper.php`
- `listings.php`
- `listing-intake.php`
- `crawler-review.php`
- `identities.php`
- `recreation.php`
- `geocode.php`
- `safety.php`
- `cluster-tools.php`
- `sales.php`
- `auto-imports.php`
- `ecommerce.php`
- `quickbooks.php`
- `people.php`
- `staff-levels.php`
- `insights.php`
- `search.php`
- `crawler.php`
- `sources.php`
- `data.php`
- `settings.php`
- `sitemap.php`

Opened from a working tool (pop-up, detail, action or print page):
- `listing-edit.php`
- `listing-add.php`
- `review-item.php`
- `suggestion.php`
- `listing-new.php`
- `claims.php`
- `nexus.php`
- `rebuild.php`
- `landmarks.php`
- `nations.php`
- `journeys.php`
- `experiences.php`
- `stories.php`
- `resource-guides.php`
- `how-guides.php`
- `short-codes.php`
- `reports.php`
- `promotions.php`
- `order.php`
- `manifest.php`
- `products.php`
- `vendors.php`
- `staff.php`
- `staff-profile.php`
- `search-misses.php`
- `search-learning.php`
- `search-oversight.php`
- `search-index.php`
- `search-demand.php`

`/admin/index.php` is the Dashboard module's own entry (6 lines), not an archived page.

## Waiting on Jason (not ours to remove)

| File | Note |
|---|---|
| `website/admin/chamber-builder.php` and `chamber-builder.php.php` | Added on the server (Oct 26–27), not from a delivery. The `.php.php` copy looks like an accidental duplicate. As an admin page outside the manifests, it should become a section if it stays. |
| `website/MP5-Manual-Partial-Proof.pdf` | 56 MB in the web root, so anyone with the link can open it. A client proof belongs with its order (Orders → Files), not here. |
| `website/user/api/bootstrap-super-admin.php` (on the server) | The sync never touches this name, so the server copy is whatever was last put there by hand. Delete it in cPanel → File Manager. The admin is staff logins only now (decisions/0100). |

## Removed

| Date | File | Why |
|---|---|---|
| 2026-10-28 | `website/listing/api/my-listings.php` | Nothing called it |
| 2026-10-28 | `website/listing/api/referral/request.php` | Nothing called it |
| 2026-10-28 | `website/api/set-home-location.php` | Nothing called it |
| 2026-10-28 | `website/js/search-immersion.js`, `app/ui/search-immersion.php` | An early search mock-up; only used each other |
| 2026-10-28 | `website/gbp-landing-traversence.html` | A one-off landing page |
| 2026-10-28 | `website/image/Logo-icon.png`, `Logo-Wtext.png` | Not used anywhere |
| 2026-10-28 | `website/user/api/qr-codes.php` | Retired stub; GO Codes use `/api/go-codes.php` (decisions/0096) |
| 2026-10-28 | `website/user/api/bootstrap-super-admin.php` (OneDrive copy) | Retired; see above for the server copy |
| 2026-10-28 | `app/lib/ListingProfile.php` | Nothing used the class |
| 2026-10-28 | `app/admin/modules.php` | The old name of `manifests.php` |
| 2026-10-28 | `app/data/US.txt` | The source the ZIP code table was loaded from, once |
| 2026-10-28 | `app/scripts/split_concepts.php` | Not needed: the site does the split itself |
| 2026-10-28 | `app/migrations/degraff1_traversence.sql` | A full database dump, not a migration |
