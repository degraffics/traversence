# 0168: Facebook and Instagram for members and businesses: sign-in, sharing, connected Pages

Status: accepted as the outline (Jason, 2026-11-16: "lets write this up and queue it for future work"). Not built;
queued in `handoffs/session-manager.md`. Each phase is its own release for Jason's review.
Builds on: 0058 (public profiles), 0086 (web presence in Sales: "Facebook or social only"), 0090 (Sign in with Apple,
Microsoft and Facebook), 0134 (events and `event_sources`), 0141 (Market 360's paid features), 0164 R14 (Facebook is
never crawled; an owner connecting their own Page is "the one way a Page feeds the record"), 0165 (staging holds outside
calls), 0167 (communications in one place, Pulse inboxes).

## The question

Jason: "what about using the other facebook api features?", "can we access both personal profile and business from FB,
since we offer both within our platform?", and "can we build in a share post from personal profile that feels
integrated even though its a share. Also can we pull the personal feeds in and have a way to filter or control who gets
to actually be pulled in to trv feed?"

## What Meta allows (checked 2026-11-16)

- **One Meta app, one login, both sides.** The same person can be a member on Traversence and manage a business's
  Page. Facebook Login covers the person; **Facebook Login for Business** covers their Pages. The app is created as
  type **Business** (https://developers.facebook.com/apps/ → Create app → Other → Business) so it can do both.
- **Personal side, limited on purpose:**
  - name, email, profile photo (`public_profile`, `email`: no review);
  - with App Review: profile link (`user_link`), hometown and city (`user_hometown`, `user_location`), photos
    (`user_photos`, strict);
  - **no posting to a personal timeline** (removed by Meta in 2018); only the **Share Dialog**, which the person sends;
  - **no friends' posts** (closed since 2015); a member's own posts (`user_posts`) are granted only to show them back
    to that person, rarely, and never to republish to others;
  - personal Instagram: no API (Meta shut it down in December 2024).
- **Business side:** a connected Page's details, events, posts, publishing and messages, each with its permission and
  App Review; Instagram business and creator accounts linked to that Page likewise.

## Decision

### Rules for every phase

- **Personal and business kept apart.** A member's Facebook data stays on their member account, private unless they
  choose to show it. A Page's data goes only to that business's listing. Neither appears on the other.
- **A Page's data is used for that business only:** never the crawler, research, other listings or Gemini (0164 R14,
  "the licence decides the use"). Our crawler still never reads facebook.com.
- **Tokens:** each Page's token is stored encrypted in the database, one per business, refreshed before it expires,
  deleted on Disconnect or when Meta tells us the person removed our app. `.env` holds only our app's keys.
- **Never overwrite what a person entered** (0164): what a Page gives is a suggestion the owner accepts, cited "from the
  business's Facebook Page".
- **Disconnecting** either side leaves the other in place, and never locks anyone out (a Facebook-only account is
  offered a password first).
- **Staging** holds every Meta call (`StagingGate::hold()`, 0165); App Review recordings are made on staging with
  Meta's test users.
- **Not doing:** Meta Pixel and the Conversions API (ad tracking), the Marketing API, reading facebook.com without a
  connection.

### Phase 1: members connect their Facebook account, and share (now)

Meta side (Jason):
- The app, type Business, with Facebook Login: https://developers.facebook.com/apps/
- App settings → Basic (https://developers.facebook.com/apps/APP_ID/settings/basic/): App domains `traversence.com`;
  privacy policy https://traversence.com/privacy.php; data deletion https://traversence.com/data-deletion.php.
- Facebook Login → Settings (https://developers.facebook.com/apps/APP_ID/fb-login/settings/): redirect
  `https://traversence.com/api/auth/facebook.php`.
- Switch the app to **Live**. `FACEBOOK_APP_ID` and `FACEBOOK_APP_SECRET` in `.env`.

Our side:
- Check sign-up with Facebook and linking from Privacy (including an email that matches an existing account).
- Meta's **data deletion callback** (its signed request; removes the link; returns a status link) and the
  **deauthorize callback**, beside the instructions page.
- Privacy → Connected accounts shows Facebook with Disconnect; the Facebook profile photo offered as the avatar.
- **Share that feels built in:** the composer (Moments, journeys, reviews) has **"Also share to Facebook"**: one press
  posts on Traversence, then Facebook's Share Dialog opens with the post's card (a pop-up on desktop, the Facebook app on
  phones). A hashtag (`#Traversence` or the town's) is pre-filled; the person's own words can't be (Meta's rule). We count
  "shares started", since a share can't be confirmed. Phones also get the system share sheet (Facebook, Messenger,
  Instagram Stories, WhatsApp).
- **Open Graph tags** checked on every shareable page (photo, title, description, Traversence branding), so the card looks
  like ours on Facebook.
- FAQ entries.

Gate: sign-up, link, unlink, a removal on Facebook's side, and a share, all working.

### Phase 2: Meta Business setup (Jason; starts with Phase 1, the slow part)

- A Meta Business portfolio owning the app: https://business.facebook.com/settings/info (the app added under Accounts →
  Apps).
- **Business Verification:** https://business.facebook.com/settings/security
- Data Use Checkup and Data Protection answers when Meta asks.

Gate: "Verified". Phases 3 onward wait on it.

### Phase 3: businesses connect their Facebook Page

Meta side:
- A Facebook Login for Business configuration (https://developers.facebook.com/apps/APP_ID/business-login/configurations/)
  with `pages_show_list`, `pages_read_engagement`, `pages_read_user_content`, `business_management`;
  `FACEBOOK_LOGIN_CONFIG_ID` in `.env`.
- One **App Review** round (https://developers.facebook.com/apps/APP_ID/app-review/permissions/) for these and the
  members' optional `user_link`, `user_location`, `user_hometown`, `user_photos`.
- Webhooks (https://developers.facebook.com/apps/APP_ID/webhooks/) with `FACEBOOK_WEBHOOK_VERIFY_TOKEN`.

Our side:
- On a **claimed** listing, the business portal offers **Connect your Facebook Page**; the owner picks the Page(s).
- **Details:** hours, phone, website, description, cover photo and category arrive as suggestions; counted as the owner's
  own word in the identity checks (0164). Page changes arrive by webhook as new suggestions.
- **Page posts into the Traversence feed,** under the business's control: **all posts**, **only ones I approve**, or
  **only with a tag** (e.g. `#Traversence`); off until chosen. Community rules and moderation apply; Pulse holds anything
  flagged; staff can mute a source.
- **Viewers' control:** each item is labelled ("from Pine Cafe's Facebook"); Feed → Sources lets a viewer show
  Traversence only, or include businesses' Facebook and Instagram.
- **Members' optional extras** (after review): their profile link on their public profile (0058) if they choose, home
  place suggested from hometown or city, and chosen Facebook photos imported into their Gallery.
- Sales: 0086's "Facebook or social only" prospects get Connect your Page as the pitch.

Gate: App Review approved; one real business connected; Disconnect and deauthorize clean up.

### Phase 4: Page events

A connected Page's events flow into Events (0134) as an `event_sources` "Facebook Page", kept current by webhook, removed
when cancelled there. Its permission goes in Phase 3's review round where possible.

### Phase 5: Instagram

Business and creator Instagram accounts linked to a connected Page (`instagram_basic`): recent posts on the listing or
storefront with a link back (a short cache, no copies kept), and into the feed under Phase 3's same three choices.

### Phase 6: posting from Traversence (a paid feature)

`pages_manage_posts` and `instagram_content_publish`: a business posts once (an offer, an event, a Moment) and picks
Facebook and/or Instagram too. Inside a paid plan or add-on (commercial.md, 0141); the API itself costs us nothing.

### Phase 7: messages into Pulse (after decisions/0167)

`pages_messaging` and `instagram_manage_messages`: messages to a connected Page land in that business's inbox beside
email (0167 §2a), replies go back through Meta, inside Meta's 24-hour reply window.

### Phase 8: WhatsApp Business (only if a plan calls for it)

Its own WhatsApp Business account (https://business.facebook.com/wa/manage/home/), phone number ID and system-user token.
Meta charges per conversation, so only inside a paid plan ("no income, no expense").

### Separately: Share into Traversence

When the installable app is built, it registers as a phone **share target** (Web Share Target): a member shares their
own Facebook or Instagram post to Traversence and it opens as a Moment draft with its photos and caption to edit. The
member chooses each one; nothing is pulled without them. This is how members' own posts reach our feed, since Meta
allows no feed import.

## `.env`

```
# --- Meta: Sign in with Facebook (decisions/0090, 0168 Phase 1) ---
# App ID and secret: https://developers.facebook.com/apps/APP_ID/settings/basic/
# Redirect URI (https://traversence.com/api/auth/facebook.php):
#   https://developers.facebook.com/apps/APP_ID/fb-login/settings/
FACEBOOK_APP_ID=
FACEBOOK_APP_SECRET=
# Optional (default v21.0): https://developers.facebook.com/docs/graph-api/changelog/versions/
# FACEBOOK_GRAPH_VERSION=v21.0

# --- Meta: connected Pages (0168 Phase 3 onward; not built, leave commented) ---
# https://developers.facebook.com/apps/APP_ID/business-login/configurations/
# FACEBOOK_LOGIN_CONFIG_ID=
# Any long random string, also typed into https://developers.facebook.com/apps/APP_ID/webhooks/
# FACEBOOK_WEBHOOK_VERIFY_TOKEN=
# https://business.facebook.com/settings/info
# META_BUSINESS_ID=
# WhatsApp (Phase 8, paid plans only): https://business.facebook.com/wa/manage/home/
# WHATSAPP_PHONE_NUMBER_ID=
# WHATSAPP_ACCESS_TOKEN=
```

## Order

Phases 1 and 2 together (a small build for us, paperwork for Jason); then 3; then 4 and 5 in one review round; 6 when
Market 360's paid features need it; 7 after 0167; 8 only if a plan calls for it. Share into Traversence with the app.

## Consequences

- Members get Facebook sign-in, linking and a share that feels like ours, with no review wait.
- Businesses that live on Facebook get a real way in: their Page feeds their listing, events and (by their choice) our
  feed.
- Members' personal feeds never come in automatically; Meta forbids it, and the share target is the member's own way.
- Meta's reviews and Business Verification set the pace from Phase 3 on; a refused permission drops only its feature.
