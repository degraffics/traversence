# 0073. Listing categories: one main, up to four more, and offerings

- **Status:** Accepted (§§1–6 built); §7 (conversion by group) is a draft for review
- **Date:** 2026-10-10
- **Builds on:** 0068 (plain category names), 0062 (identity: the crawler finds a listing's details), 0070/0071 (claims, adding a business)

## Context

Each listing had exactly one category (`entities.category_id`). Directories that win local search use three tiers: one primary
category, a few secondary ones, and offerings or attributes. Google allows 10 categories, Yelp, Facebook and Apple Maps 3,
Bing 10. Traversence has 23 groups and 820 categories under them, plus an offerings list per listing (up to 30 short items).

## Decision

### 1. Three tiers

| Tier | Where | Shown |
|---|---|---|
| **Main category** (1) | `entities.category_id`, as before | On the card and in its line in search; decides the group |
| **Also listed under** (up to 4) | `listing_categories` (new) | As search pills on the listing page; found on those category pages and searches, after the listings whose main category it is |
| **Offerings** (up to 30) | `entity_metadata` `offerings`, as before | Searched word for word; the owner's editor now has the field, with ideas from others in the same categories |

Five categories in all (Jason, 2026-10-10). The same limit on every plan: categories decide where a business is found,
and no plan ranks higher (`commercial.md`). Plans unlock features, not reach.

### 2. Adding one: the + icon

Beside the category field, a round **+** adds another category field, up to four, each with × (`js/cat-more.js`,
`app/ui/category-fields.php`). It's in the staff editor, the owner's editor (saves at once) and the add-a-business form.
On the add form the short "What it is" list gives a broad group; the first exact category picked inside that group
becomes the main one, and the rest are extra.

### 3. Nobody approves them, for now

Owners set their extra categories themselves, unmonitored (Jason: "let it be an advantage for those using the system
early"). Revisit if stuffing becomes a problem.

### 4. Names: recommend, don't police

We are a tool for businesses to connect and convert. Nothing blocks a name. When a name carries a slogan or a list of
services ("Hometown Theater - Movies, Popcorn & Parties"), the add form shows a tip: businesses do best with the name on
their sign, and what they do belongs in the categories and offerings, where searches find it.

### 5. Search engines

Listing pages publish schema.org `LocalBusiness` data: the name, the categories in order (main first) as keywords, the
phone, address and website (none for a listing whose location is kept private).

### 6. Staff add a listing without an owner

Admin → Listings & places → **Add a listing** (`/admin/listing-add.php`), for admins and regional managers (admin_access
`regional_admin`; regional managers can also edit listings now, the rest of the crawler stays admin-only). Only the name,
the category and the town are needed. The listing goes live unclaimed, nobody assigned, and goes to the front of the
crawler's identity queue (`IdentityJobs::look`), which looks it up and fills in its phone, website, email, social pages and
hours once each is confirmed. The worker checks the listing's own website, the NPI Registry and web search, so **no website
is needed**. Google, Yelp and Facebook pages only confirm a value and are kept as links (their terms don't allow copying).
When the listing has a website, the editor offers **Crawl its website** (Listing Intake) for the rest: about, offerings, hours.
If the crawler finds nothing, it tries again in 30 days, and the editor and Admin → Identities show what it checked.

### 7. Content to commerce: each group's main conversion (draft for review)

Categories are about to unlock page features. Before building any, each group's primary sales funnel needs agreeing,
because the marketplace, the listing page and messaging must all support the same everyday action. First draft:

| Group | Main action (the conversion) | Then | What it needs from us |
|---|---|---|---|
| Food & Dining | **Order** (pickup or delivery) / **Reserve** a table | Visit, review | Menu in the marketplace catalog, reserve request, hours "open now" |
| Hospitality & Lodging | **Book** a stay | Ask a question | Rooms or sites as catalog items with dates, availability request |
| Entertainment & Recreation | **Buy tickets** / **Book** an activity | Visit | Events and showtimes, ticket or booking link, capacity |
| Retail & Shopping | **Buy** (or reserve for pickup) | Visit, ask if in stock | Product catalog with variants, "hold for me" |
| Health & Medical | **Book an appointment** | Call | Appointment request, accepted insurance, new patients yes/no |
| Pet & Veterinary | **Book an appointment** | Call | As Health; boarding and grooming as bookable services |
| Home Services & Repair | **Request a quote** | Call, book a visit | Quote form with photos, service area, response time |
| Construction & Contracting | **Request a quote** | Call | Quote form, project photos, licences |
| Automotive & Repair | **Request a quote** / **Book service** | Call | Service list with prices from, appointment request |
| Professional Services | **Book a consultation** | Request a quote | Consultation request, service list |
| Legal Services | **Book a consultation** | Call | Consultation request, practice areas |
| Financial & Insurance | **Request a quote** / **Book a meeting** | Call | Quote request by product, meeting request |
| Real Estate | **Ask about a property** / **Book a showing** | Call | Listings as catalog items, showing request |
| Education & Childcare | **Enroll** / **Book a tour** | Ask | Programs as catalog items, enrollment or tour request |
| Agriculture & Farming | **Buy** (farm goods) / **Request a quote** (services) | Visit | Seasonal catalog, pickup times |
| Industrial & Manufacturing | **Request a quote** | Call | Capabilities list, RFQ form with files |
| Technology & IT | **Request a quote** / **Book a consultation** | Call | Service list, support request |
| Transportation & Logistics | **Request a quote** / **Book** a ride or move | Call | Quote form with from/to and date |
| Utilities | **Report** or **Start service** | Call | Links to the provider's own start-service and outage pages |
| Government & Public Services | **Find the right office and call** | Visit | Who-to-call lines (resource guides), hours, forms links |
| Non-Profit & Community | **Volunteer** / **Donate** / **Get help** | Attend events | Needs list, events, donate link |
| Religious & Faith-Based | **Visit** (service times) | Events, contact | Service times, events |
| Other | **Contact** | Visit | Messaging |

Four conversion types cover nearly all of it: **book or reserve** (a time), **buy or order** (a thing), **request a quote**
(a job), and **contact or visit**. Each should be one shared building block (a request in messaging that the business
answers from its inbox, with the listing's catalog behind it), so a group's page only chooses which block leads.
Next step: agree this table, then build the four blocks one at a time, starting with the group that brings the most
businesses.

## Consequences

- One migration (`app/migrations/2026-11-13_listing_categories.sql`): a plain table and one column. It moves with any
  normal database export, so leaving Bluehost changes nothing.
- Search finds a listing under each of its categories; its own category's businesses come first.
- Directory sync (Yext, BrightLocal, aggregators) stays a future consideration.
- Code: `app/lib/ListingCategories.php`, `app/ui/category-fields.php`, `website/js/cat-more.js`, `website/admin/listing-add.php`;
  changes in `SearchIndex`, `UniversalSearch`, `AddBusiness`, the staff and owner editors, `claim.php`, the listing page and
  the admin menu.
