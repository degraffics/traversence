# 0082. Kinds shown under more than one group; work opens in a stepped pop-up; no "ZIP 00000"

- **Status:** Accepted
- **Date:** 2026-10-14
- **Builds on:** 0073 (a listing's categories), 0081 (the admin day as steps)

## Context

Jason, 2026-10-14:

- "Cooking classes needs two categories. Food & Dining for when it is an experience vs. entertainment, vs. education?"
- "I am not a fan of clicking into a process and then having to go back to the admin dashboard to resume working."
- "From dashboard / search / review / places, that is multiple steps to get to a task."
- "Also why would there be a 00000 zip cluster?"

## Decision

1. **Also show under** (table `category_also`, `app/lib/CategoryAlso.php`).
   - A kind of place keeps one home group: its place in the list and on a listing's card.
   - It can also be shown under other main groups. Browsing or filtering a group then includes those kinds too: the
     directory's category list (marked `also`), the directory filter, universal search's group filter and the search
     index.
   - It's set on a draft kind before it's approved ("Also show under", beside "Under"), and on any existing kind in
     Search → Words for each kind.
   - Searches never needed it: a kind's words find it whatever its group.
   - Example: Cooking Classes lives in Food & Dining, and also shows under Education & Childcare and Entertainment &
     Recreation.
2. **A step's work opens in a pop-up, with breadcrumbs** (Jason: "The standard should be popup lightbox so I can work
   the task and once complete closes the popup… Show steps as breadcrumbs so if I need to go between steps I can
   easily". An earlier in-place version was replaced).
   - Any step, or any of its kinds of work, opens a large pop-up over the dashboard. Two rows of breadcrumbs sit
     above the work: the day's six steps with their counts (✓ when clear), then the current step's tasks. Tap any
     crumb to jump.
   - **Next ▸** goes through the step's tasks, then on to the next step that has work. **◂ Back** goes back.
     **Done**, × or Esc closes it and reloads the dashboard, so the counts are fresh.
   - Whatever the tool opens (a listing to compare, a question) shows on top of the pop-up, never inside it.
   - Crumbs that don't fit go under More ▾.
   - Ctrl/⌘-click still opens the full page.
3. **One click to the task.**
   - "Listings the crawler couldn't finish" opens the Helper on possible duplicates (or the rest), one at a time.
   - "Areas to name" opens the places tool on the first area to name.
   - Review's "All" counts only what needs a person; the crawler's own work in progress is told below it.
4. **No "ZIP 00000".**
   - Owner-added businesses looked their town up against `zip_coordinates.state_code`, which holds the county's
     number, not the state's letters. So "Saint Johns, AZ" never matched, the business got "00000", and an area was
     made for that ZIP.
   - Now the lookup uses `state`, matches "St.", "St" and "Saint" alike, and a town it can't find gives no ZIP (the
     map point places the business later).
   - An invalid ZIP never makes an area (`CrawlerIngest::resolveMicroClusterId` returns none).
   - `2026-11-20_fix_zip_00000.sql` gives the listings already saved with "00000" their town's ZIP and area, and
     removes the empty "ZIP 00000" area.

5. **Failed crawl jobs and a stopped worker, in plain words** (Jason: "I have no idea how to fix these issues").
   - Crawler shows **The crawler worker isn't running: how to fix it**, with the steps on Railway: plan or credit,
     Redeploy, cron schedule, variables, logs.
   - Each failed job says what went wrong in plain words; the technical message is one tap away.
   - **Try them all again now** puts every failed job back in the queue.
   - On the dashboard, the System step lists each problem and links straight to these.
   - The worker (`workers/crawler/crawler.py`) has a fourth OpenStreetMap server, and retries a refused (406) request
     as a GET. Railway must redeploy it.
6. **More near-identical names merge.** A name that is the other with a parent agency in front, with the same phone
   and ZIP, is the same place even at another street number: another entrance of the same hospital ("Dhhs, phs,
   naihs, Gallup Indian Medical Center" and "Gallup Indian Medical Center").

## Consequences

- Run `2026-11-19_category_also.sql` and `2026-11-20_fix_zip_00000.sql`.
- A listing whose town can't be read has no ZIP until its map point is found.
