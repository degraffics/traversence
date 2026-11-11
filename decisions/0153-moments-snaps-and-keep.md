# ADR 0153: Moments, Snaps and Keep (the personal feed)

**Status:** Accepted (Jason, 2026-11-11, in conversation). Zip 1 built (`moments-keep_20261111.zip`, below); zips 2–4 to come, in the order in §11. Builds on
`decisions/0047` (links and the feeds), `0050` (data use), `0052` (one messaging system), `0058` (the public profile),
`0126` (the Gallery), `0127` (one uploader), `0132` (tags and links) and `0151` (files on Cloudflare R2). Amends 0050
(§6 below: viewers named on your own Moments and Snaps) and 0151 §3 (§8 below: the video worker).

## Context

Jason, 2026-11-11: the personal profile is being modelled on Facebook's profile (cover, card, details down the left,
"What's on your mind?" and Posts on the right, List and Grid views, Manage posts), blended with Snapchat's deleting
media and YouTube's channel for building an audience. "Every post is a moment, unless the user selects the keep
feature. That way one feed receives the post no matter the content." Members can post today only inside groups
(`Community::post`); businesses post through `posts`. The Dashboard tab already shows the profile with the Pulse
(`MemberPulse`, `Links::feed`), and the profile's tabs are `MemberProfile::TABS`.

## Decision

### 1. One kind of post: a Moment, unless kept

- **Every post is a Moment** (text, photos, a video up to 120 seconds, a check-in, a life update) and goes to **one
  feed**. A Moment lasts **72 hours** by default; the poster can choose **7 days** for that post (`3 days ▾ / 7 days`
  next to Keep). When it ends, the post and its files are deleted and it drops out of every feed.
- **Keep** (a switch in the composer, and on the post until it ends) makes the post stay. Its photos go to the owner's
  Gallery (a Photos album) and its videos to their **Channel** (§7). From then on they count against the owner's storage.
- **Owners:** a person, or a listing when someone on its team posts as the business (0058 §2). A business's Moments
  count against its plan.

### 2. Who it goes to

The composer's **Send to**: **Feed** (the poster's profile and the Pulse of their followers and connections), **Direct**
(one or more people) or **Group** (a private circle or a group chat), alone or together ("Feed + Maria").
- A feed post's audience starts from the member's **default post audience** (App settings; starts at **Connections**)
  and can be changed per post: Public, Followers, Connections, Only me. A private profile reads Public as Followers.
- Direct and Group follow the member's message controls and blocking (0052), unchanged.

### 3. Snaps: Moments in chat

A **Snap** is a Moment sent Direct or to a Group. The public profile gets a **Snap** button beside Message, shown when
`People::canMessage()` allows it, otherwise greyed out with the reason. It opens the composer set to Direct for that
person. A Snap shows in the thread as a tile that plays when tapped, lasts 72 hours (or 7 days), and anyone in the
chat can **Keep** it; everyone sees "Jason kept this". Text messages are unchanged. Group chats use the circles' group
conversation (0047 progress note, 2026-09-30).

### 4. Recording

- One cap: **120 seconds**. Press record; a ring counts down from 120 to 0 and stops at 0, or at stop.
- Recorded **compressed in the browser** (about 720p, about 1.2 Mbps; MP4 where the browser records it, WebM
  otherwise), so a full clip is about 20 MB. A clip from the camera roll is trimmed to 120 seconds first.
- Quality by plan (decisions/0154): **720p free, 1080p Plus, 4K Pro**. A clip above 720p gets a phone-size copy from
  the video worker (§8), and feeds play that copy.
- Files of 25 MB or less go up in **one request** (R2 takes single uploads to 5 GB), not in parts: one write a post.

### 5. Where files live (decisions/0124, 0151)

- **Day 0:** the upload goes to the owner's **Temporary** bin: `temporary/members/<folder>/…` or
  `temporary/businesses/<folder>/…`, at the top of the bucket so one R2 lifecycle rule covers every bin. It's still the
  owner's (`Store::files()`, `usage()`, `wipe()` include it) and **never counts against storage**.
- **24 hours before the end:** a notice, "This goes tomorrow. Keep it?", with **Keep it** and **Let it go**.
- **Keep:** `S3::copy()` into the owner's folder (`members/<folder>/public/…`), the temporary copy deleted; it now counts
  in `Gallery::bytes()`. Short of room: **Add storage**, an upgrade, or **Choose what to clear**, never a dead end.
- **The end:** an hourly job (`Scheduler::JOBS`, `moments_tick`) deletes the post, its files, its views and its
  comments. Backstop: an R2 lifecycle rule deletes anything under `temporary/` after **9 days**.
- **Held:** a reported Moment or Snap is not deleted at its end. It's hidden from viewers and a **compressed copy** goes
  to `held/<report id>/…` (private, staff only; photos re-compressed on the server, videos kept as recorded). See §9.

### 6. Who saw it (amends 0050)

Moments and Snaps are direct communication, so the poster sees a **viewer list** on their own: a viewer is named when
they're in the poster's Address Book or linked to them (a connection or follower); everyone else is counted ("Maria
B., Troy A. and 12 others"). Views are deleted with the post. Everything else on the site stays "counts only, never
who" (0050). The FAQ tells viewers that people they're linked to see their name.

### 7. The Channel and reactions

- **Channel:** a Gallery album kind beside Portfolio and Brand & documents (0126). Kept videos go there; the profile's
  **Videos** tab shows it, with playlists made from albums, one pinned video, a short link and QR code (TRVGO) each.
- **Reactions, the full set:** Like, Love, Care, Haha, Wow, Sad, Angry, drawn as brand icons (`tv_icon()`), never emoji.
  `Engage`'s likes gain a `reaction` column; today's likes become Like. Posts get the kind `'mp'`.
- No ads, boosted posts or recommendation algorithm (0007, 0050): feeds run newest first.

### 8. The video worker (amends 0151 §3)

A Railway worker beside the crawler (`workers/video/`, ffmpeg) makes, for **every uploaded video** above 720p: a 720p
copy for feeds, a poster frame, and its length, stored next to the original in R2. The site queues the job; the worker
fetches jobs, converts, uploads and reports (the crawler's pattern: two small requests to Bluehost a run). Pages play
the 720p copy and offer **Watch in 4K** where there's one. Its running cost is covered by the plans that make large
videos (Plus, Pro, business plans); anyone else's comes from the community budget (§10).

### 9. Keeping deleted posts (law)

Ordinary posts are deleted for good at their end, and kept posts when their owner deletes them. What stays:
- **A one-line record** that a post existed: owner, time, when it ended, never its content (reports and abuse patterns).
- **A reported post's held copy:** until the report is decided, then **90 days**.
- **A legal preservation request** (18 U.S.C. §2703(f)): the held copy, untouched, 90 days, renewable once.
- **Child sexual abuse material:** reported to NCMEC as the law requires, the held copy kept **1 year** (REPORT Act, 2024).

Held copies are never shown and never used for AI. The Terms state this marked **[confirm with counsel]**.

### 10. Guardrails: no surprise bill

- **Per account, enforced on the server:** **5 new photo or video posts a day** (Moments and Snaps together; text posts
  don't count), **25 active at once**, 120 seconds and 25 MB a file. At the limit the composer lists the active ones,
  each with **Delete**, which frees a slot. New accounts: **2 a day** for their first 7 days, with a verified email.
- **The community budget** (decisions/0154 §4): free posting's costs are counted by the site from the uploads it records.
  At 80%, a Pulse case for the team (with Cloudflare's link, `Pulse::SERVICES`); at 100%, new free **video** Keeps and
  video Moments wait until the 1st (photo and text Moments carry on), with a notice saying why and an upgrade offered.
- **Cloudflare's billing notification** set as a second check (R2 has no spending cap of its own).
- **Light on Bluehost:** signed links built once per feed load, posters as plain images, feeds 20 posts at a time,
  nothing plays until tapped.
- **Later, when reads near R2's free 10 million a month:** public Moments served through Cloudflare's cache on a custom
  domain, so repeat plays don't count as reads.

Cost reference (R2, 72 hours, about 20 MB a video): at 500,000 members, a typical month about $50, a busy one about
$375; the ceiling with everyone at the limit every day, about $4,000. Plays never cost bandwidth on R2.

### 11. The profile layout and the build order

- **Profile:** cover; photo (a ring when there are active Moments), name, @handle, follower count; **Follow** and
  **Snap** for visitors, **Add a Moment** and **Edit profile** for the owner. Tabs: All, About, Connections, Photos,
  **Videos**, Journeys, More ▾. Left: Personal, Work and Education details, Highlights (kept Moments grouped and
  pinned), Connections, Photos. Right: the composer, then **Posts** with **List / Grid**, Filters, **Manage posts**.
- **Dashboard tab:** the same header with the Pulse; the Pulse gains a **People you follow** source.
- **The ⋯ menu:** own post: edit (history kept), change who sees it, pin, who can comment, Keep, delete. Someone else's:
  save, turn off notifications, remove tag, hide from profile, report.
- **Zips:** (1) Moments and Keep: composer, Temporary bins, the end and its job, Held, the feed, Posts tab, reactions,
  viewer lists, limits. (2) Snaps in chat. (3) Gallery and Channel: Videos tab, playlists, short links and QR,
  insights; the video worker. (4) Tags and check-ins: "with @…", tagged posts on the profile, check-ins (the Check-ins
  tab), life updates, "Featured in" on place pages (0132).

### 12. Data (zip 1's migration, safe to run twice)

`member_posts` (owner kind and id, body, sent to, audience, window, `expires_at`, `kept_at`, pinned, status, time
zone), `member_post_media` (storage key, kind, poster, length, bytes, temporary or kept, the 720p copy's key),
`member_post_views`, `member_post_edits`, `member_post_holds` (report, held copy's key, hold ends), `member_post_log`
(the one-line record, §9); `Engage` likes get `reaction`. Library `app/lib/MemberPosts.php`; endpoint
`user/api/posts.php`; upload targets `moment` (Temporary) and `post_keep` (into the Gallery); `js/tv-snap.js` (record).

## Consequences

- One-time Cloudflare steps for Jason (in the zip's hand-off): the `temporary/` 9-day lifecycle rule, the `held/` rule,
  and a billing notification.
- The FAQ gains entries for Moments, Keep, Snaps, who sees your name, the limits and what's kept after deletion, each
  saying "coming" until its zip is live.
- 0058 §1's "Activity stream: opt-in, off by default" still holds: Moments are what someone chose to post, not activity.

## Built: zip 1, Moments and Keep (`moments-keep_20261111.zip`, built on the 16:26 UTC sync, 2026-11-11)

- **Posting:** `app/lib/MemberPosts.php`, `website/user/api/posts.php`, the upload target `moment`, migration
  `2027-02-26_member_posts.sql` (posts, media, views, edits, holds, the one-line log, spend, default audience; a
  `reaction` column on `content_likes`). Members only for now; posting as a business comes with zip 2.
- **Pages:** "What's on your mind?" at the top of the Dashboard and on the profile's new **Posts** tab (public profiles
  open on it; owner and public views); `website/user/post.php` for one post; `website/js/tv-moments.js` (composer, the
  2-minute recorder at about 1.2 Mbps, the feed with List / Grid, the seven reactions, comments, Share, Seen by, the ⋯
  menu). Media served by `website/user/api/post-media.php` (signed R2 links, or the server without S3).
- **Keep:** photos and videos go into the Gallery's private **Kept Moments** album for now; the Channel album kind and
  the Videos tab come with zip 3, which moves kept videos into the Channel.
- **The end:** `app/scripts/moments_tick.php` (hourly, `Scheduler::AUTO`): the day-before notice (`moment_ending`),
  deletion, unposted uploads after 24 hours, held copies released 90 days after the decision. Comment notices
  (`post_comment`).
- **Reports:** kind `member_post` in `Reports` (filed from the ⋯ menu): held copy at the first report, hidden at the third
  or at once for private information, a sacred site or danger; decided on the Reports page.
- **Budget:** `COMMUNITY_BUDGET_PCT` (3) and `COMMUNITY_BUDGET_FLOOR_USD` (25) in Settings; a Pulse case at 80% (key
  `moments:<month>`, with Cloudflare's link); free accounts' video waits at 100%.
- **The Pulse:** a **People you follow** source with photo thumbnails.
- **Fixed on the way:** `Engage::comment()` returned id 0 (the insert id was read after another query).
- **Not yet:** single-request uploads for small files (uploads still go in parts; a change to the one uploader, its own
  build); higher quality by plan (needs the video worker, zip 3); the Moments ring on the profile photo and Highlights.
- **Cloudflare, once (Jason):** R2 → the bucket → Settings → Object lifecycle rules → Add rule: prefix `temporary/`,
  delete objects after **9 days**. Billing → notifications: a usage alert for R2.
