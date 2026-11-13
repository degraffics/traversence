# ADR 0159: The builder: Traversence's platform builder and Storefront Studio

**Status:** Accepted (Jason, 2026-11-13: "accepted, but I want this built in a new session"; the hand-off is `handoffs/0159-the-builder.md`). Not built yet.
(the release process, Phases 5 and 6), `0158` (one way to publish), `0112` §3 (`SitePages`), `0139` (storefronts on
trvgo.us) and `0135` (the Marketplace and Storefronts), and keeps `0109` (the admin), `0129` (the command strip), `0058`
(the visual standard) and commercial.md's plans.

## Context

Jason, 2026-11-13, after the first releases went through the Releases page:
- "I am not a fan of this process so far. I would prefer to have an open site and see site manager and command bar. But
  that is more like a WYSIWYG editor." With reference shots of a drag-and-drop builder: a widget panel (text, image,
  HTML, contact, maps, icons, headings, columns), a toolbar on the chosen block (Manage text, Edit design, move,
  duplicate, delete), a design panel (alignment, margins and padding, size), breakpoints, themes, global components,
  header and footer, SEO tools, AI writing.
- "I should not have to upload to the staging site, it should be open a page, make changes, save or publish when done."
- "Look at how the storefront for listings is described under ADRs and commercial.md." That description: Traversence is
  a business's "primary digital home… replacing the need for a separate standalone website" (commercial.md §2); the
  AOS calls the tool Storefront Studio; `SitePages` already serves both our own pages (`owner_id` 0) and each business's
  storefront pages (0112 §3).
- "I just want to ensure we don't lose what we have built for Traversence in converting its development over to the
  studio. As for Traversence its website development is platform development, not just frontend presentation, so our
  tool must be more of an enterprise level full development builder vs listings wanting just a storefront."

## Decision

### 1. One family, two levels

- **The platform builder (Traversence):** the whole platform. Code, data, the admin, workflows, migrations, search,
  the crawler, and the pages and their layout.
- **Storefront Studio (listings):** a business's own site. Its pages, look and content, over its own data. No code.
- **Built once, shared:** the block engine and the editor, versions, publish and roll back (0158), and the bar. The
  platform builder has all of it and more; Storefront Studio is the part a business needs.

### 2. Nothing built is lost

The studio is added on top. It replaces none of these, and every one of them stays the way Traversence's code is
developed:
- the release system (0145 Phase 5): staging, the checks, the audit and paths, switches and steps, Publish, Turn off,
  Roll back, the git records;
- the admin (0109), its modules, manifests, workflows and permissions;
- every page built in code today. It keeps working as it is, and becomes editable only where a release adds an editable
  zone to it (§3.2);
- `SitePages`. Each page keeps its Markdown, read as one text block, so nothing is rewritten by hand (§4).

CLAUDE.md's "Build on what's there" rule and the zip check (it refuses a build that drops a live function, constant or
FAQ question) keep guarding this.

### 3. The platform builder, layer by layer

| Layer | What it covers | How it changes |
|---|---|---|
| Code and platform | features, admin tools, workflows, the database, search, the crawler, APIs | Built by Claude in sessions; tried on staging; published by Jason from the site manager (§5). Never in the editor. |
| Pages and layout | what Traversence's pages say and how they're laid out | The editor, in place on the page (§3.2). |
| Live components | the parts of a page that run on platform code (§3.3) | Placed and set up in the editor; their behaviour stays in code. |
| Data and operations | listings, orders, members, settings | The admin, as now. |

1. **The rule between the layers:** the editor never edits code. Moving a listings grid is a layout change and goes
   live from the editor. Changing how it ranks is a code change and goes through staging. So a content change can't
   break a feature, and a feature can't skip staging.
2. **The editor, on traversence.com:**
   - **Opening it:** staff with the permission press **Edit** on a page. Editable zones outline; the widget panel
     opens; the chosen block gets its toolbar (Manage, Design, move up and down, duplicate, delete) and the design
     panel (alignment, spacing, size), with desktop, tablet and phone views.
   - **Save** keeps a draft that only the team sees, on the real page: the draft is the preview, so content needs no
     staging trip. **Publish** puts it live now or on a schedule. Each publish is a version; **Roll back** returns to
     any earlier one.
   - **Where:** pages made of blocks (new pages, landing pages, `SitePages`), and editable zones a release adds to a
     coded page (the home page's sections first). Search, Explore, the admin and other tools stay coded.
3. **Live components:** a registry, in code, of the parts a page may place. For each one: its name, the settings it
   takes (a town, a category, how many) and where it may go. For example: the search box, a listings grid, a map, a
   guide, the engage bar, a form, a GO Code. A new component, or a change to one, is a code release.
4. **Global components:** the header, the footer and shared sections. Edited once, shown everywhere, versioned like a
   page.

### 4. Storefront Studio: the listings' site

- **What it is:** `SitePages` upgraded from Markdown to blocks, with the storefront's own themes (Canyon, Sage, Paper,
  Night, 0139) as starting styles. Edited in the portal's Storefront & pages on traversence.com; shown at
  `trvgo.us/<shop>` and on the business's own domain.
- **Commerce first** (§7): a storefront is built to sell, not as a brochure with a shop added.
- **Its blocks:**
  - **Selling:** a product grid with filters (kind, price, in stock); collections; a featured item; related items and
    add-ons on an item; Buy now and Add to cart; a menu with collections as its sections.
  - **The business:** hours, map and directions; its locations; a booking or quote form (leads, 0112); reviews.
  - **The page:** text, headings, image and gallery, columns, buttons and links.
  - No HTML block and no code.
- **The commerce under it stays the platform's:** the catalog and its variants, price lists (0112), stock, taxes and
  shipping, the cart and checkout on traversence.com through the business's own gateway (0139, 0135), and Square sync
  for shops that also sell in person (0135 step 4b). Blocks show these; they never rebuild them.
- **Who publishes:** the team role's Publish permission, no approval step (0158 §3).
- **Its pages stay script-free** (0139): blocks render to plain HTML on the server. The editor and its bar run only on
  traversence.com and in the team's preview.
- **Plans (commercial.md):**
  - **Free:** manual publishing, full control of presentation, no AI.
  - **Core:** its own domain and local SEO.
  - **Strategic:** AI writing within its allowance.
  - **White Glove:** our team builds it with them.

### 5. The site manager: code releases without uploads

The bar on staging becomes the **site manager**, in the admin command bar's look (Jason, 2026-11-13):
- **Releases ▾:** what's on staging now, what's waiting, what's live and at which step.
- **Upload:** a release zip dropped there goes onto staging at once, and its checks run. There's no separate Approve
  the plan or Put it on staging. Once Phase 6 is on (below), Claude's builds arrive by themselves and Upload is only a
  fallback.
- **Changes:** the pages a release touches, to jump to and look at. **Note** pins what's seen to the page.
- **Publish ▾:** Publish now, Schedule, the next step; then Turn off and Roll back once it's live.
- **Your look before publishing stays** (0145 note, 2026-11-13). Opening the release on staging counts as the look, and
  Publish is the sign-off. The separate Sign it off button goes.

**Phase 6 (deploy from git, 0145) is brought forward** to carry this. Claude's finished build becomes a release in git;
the sync puts it on staging; it appears in the site manager as waiting. Publishing deploys exactly its files, and Roll
back the previous ones. No zips.

### 6. The shared core

- **Blocks:** a page's content is a tree: sections, then columns, then blocks. Each block type has its settings and,
  where needed, settings per breakpoint.
- **Structured, not freeform** (§7): blocks sit in sections and columns laid out with grid and flex, never at fixed
  positions on a canvas. So every page reflows on a phone, as the visual standard requires (no sideways scroll), and a
  layout made on a desktop can't break on a phone. The design panel sets alignment, spacing, size, columns and order,
  per breakpoint. It is rendered on the server to HTML that follows the visual standard (0058:
  phone edges, card anatomy, `tv_icon()`, the brand's colours).
- **Versions:** each publish keeps the version before, numbered, with who and when (in the zone where it happened, as
  every time on the site). Drafts are kept per page.
- **The bar:** one component in two modes. As the site manager it acts on releases and pages (staff); as the studio
  bar it acts on a storefront's draft (the business's team).
- **Who may:** `Access::can` rules for staff (a new `pages.edit` and `pages.publish`, Founder only until given to a
  role), and `Team` permissions for businesses.
- **AI writing:** "write this for me" on a text block, inside the AI monthly cap for our pages, and by plan for
  businesses (no income, no expense).

## 7. What we take from the builders people know

Jason, 2026-11-13, a comparison of Snapps, Wix, Squarespace, Shopify, Webflow, Framer, BigCommerce, Square Online and
WooCommerce. What 0159 takes from it:

| Builder | Known for | What we take |
|---|---|---|
| Shopify | sections built around conversion: filtering, upsells, menus, a fast mobile checkout; large catalogs | the storefront's section and block model, and its selling blocks (§4); a short, mobile-first path to buy (the cart and checkout stay on traversence.com) |
| Webflow, Framer | true grid and flex control, clean code, bespoke design | the platform builder's layout control and clean server-rendered HTML (§6) |
| Wix, Snapps | drag-anywhere ease | the ease (a widget panel, drag in, a toolbar on the block), not the free positioning, which breaks on phones |
| BigCommerce | variants, B2B price tiers and faceted search without add-ons | already the platform's: variants, price lists (0112), search; storefront blocks show them |
| Square Online | in-store and online in step, pickup and delivery | Square sync (0135 4b), pickup and local delivery on items |
| WooCommerce | owning the code and data | already ours: our own code and database, with no third-party plugins to keep up |

Where Traversence goes further than any of them: the storefront sits inside a regional directory, the Marketplace, guides
and GO Codes, so it's found without paying for ads (commercial.md).

## Build order

Each step is a release.
1. **Into staging and the site manager:** fix the way in (the pass), then the site manager bar with the merged steps (§5).
2. **Phase 6:** builds land on staging from git; uploads end.

Progress (2026-11-13):
- **Step 1 built** (releases #4–#6, #8): the way into staging fixed (staging's private folder rule had shut its pages,
  found by Check the way in), and the site manager at the top of staging. Publish or Schedule is the sign-off after a
  look; Upload goes straight onto staging.
- **Step 2, part 1 built** (release #7): builds sent through git (`releases/inbox/`, `tools/page-audit/send.py`) are
  taken onto staging by the live site. Release #8 was the first to come that way. Jason agreed the switch the same day
  ("good to go on git zip syncing over manual uploads"): CLAUDE.md's delivery rules now send every build through git.
- **Claude's builds publish themselves** (Jason, 2026-11-13: "this feels redundant, since I would have already approved
  it in claude before it got pushed… publish is for the staging area development process"). This replaces §5's "your
  look before publishing stays" for builds sent through git: once its checks on staging pass, it goes live by itself
  (`Releases::autoPublish`). Failed checks stop it there; `"hold": true` in its release.json waits for his look and
  Publish. Uploads on staging and the editor keep Publish (release #9).
- **Step 2, still to do:** the sync's direction (`SYNC_HOME` to git) and drift reports, so edits made straight on the
  server or in OneDrive show as drift instead of going live without a release. Its timing is agreed with Jason first,
  since it changes how OneDrive edits behave.
3. **The editor's core:** blocks, Edit, Save draft, Publish, Roll back, on block pages and the home page's first zone.
4. **The full editor:** the widget panel, drag and drop, columns, the design panel, breakpoints, global components,
   live components, SEO fields.
5. **Storefront Studio:** `SitePages` to blocks, the storefront themes, the portal, the team's Publish, the preview bar.
6. **AI writing**, by plan.

## Open

- **Scheduling for shops** (0158 §6): Jason decides once he sees the need.
- **The first coded page to get zones:** the home page is suggested.
- **Phase 6's date:** with step 2, unless Jason says otherwise.

## Consequences

- Traversence keeps its full development pipeline, and gains in-place editing for what's content.
- A business gets a website builder that can't reach beyond its own site and data.
- One editor, one way to publish and one bar, built once.
