# Push polish (2026-11-19): one push per thing, a pace per person, our star, the site as an app

The Control Board session's record of `push-polish.zip`, built on Sync 2026-11-19 23:27 UTC (020ef77).

## Why

At 23:21 UTC, Chrome on Jason's Android phone flagged the push test as "Possible spam: Chrome detected possible spam from
traversence.com". It offered Unsubscribe, which would also stop Critical alarms reaching him. Jason then asked "Our icon?".

What the live code did:
- **It re-alerted every repeat.** `push-sw.js` set `renotify: true` whenever a push had a tag, so a second Test, or any
  repeat, buzzed again. Chrome's guidance counts repeated alerts from one site against it.
- **Alarms came one at a time.** Each message had its own tag (`'m' . $mid`), so a trip, its escalation and its recovery
  were three notifications. There was no pace per person.
- **The badge was the tile.** It was the 180×180 RGB `apple-touch-icon.png`, which has no transparency. Android draws a
  badge from its alpha only, so the status bar showed a solid square.
- **The site couldn't be installed.** There was no app manifest, so Android labels the site's pushes as Chrome's.

The test push's words and links match nothing Chrome's spam guidance warns about. The flag was most likely Chrome's
on-device check on a first push from a site that isn't installed and has no history yet. Installing it (the manifest) and
a real badge are the legitimate signals we can give.

## What changed

- **One notification per thing.** `Notify::deliverPush` tags a push by its thing: `'t' . sha1(user|thing)`, 12 characters.
  - An update or a recovery replaces the notification already there.
  - It alerts again (`renotify`) only when the level rises over the last message about that thing, or for Critical
    (alarm.escalated).
  - `WebPush::payload` carries `renotify`, false unless asked, and `push-sw.js` sets it only when asked.
  - The test push asks none, so a second Test updates quietly.
- **A pace per person.** The limit is `Notify::PUSH_PACE` = 3 pushes in `PUSH_WINDOW` = 5 minutes.
  - Past that, the rest fold into one summary: tag `summary`, "N new in your Pulse", the latest named, opening Pulse.
    Later ones update it quietly.
  - Critical always goes on its own.
  - The summary is reached only past quiet hours, message control (Stop these per kind) and staging's hold, just as a
    single push is.
  - Each folded message's route reads "In a summary push (N new in 5 minutes)", and it's still in Pulse and email.
- **The badge.** `image/push-badge.png` is 96×96: the North Star and its ring in white on clear, from
  `image/North-Star-logo.svg`.
- **The site as an app.** `manifest.json` has name Traversence, start and scope `/`, standalone, theme `#92400E` and
  background `#3E2B1F`.
  - Its icons are `image/app-icon-192.png`, `-512.png` and `-maskable-512.png`: the brand's North Star SVG on the tile's
    brown. The maskable one keeps the star in the safe zone.
  - It's linked, with `theme-color`, from `tv_favicon_links()`, on every traversence.com page head but not trvgo.us or a
    storefront.
- **FAQ.** A new entry, "My phone says 'Possible spam'…", and installing on Android in "How do I turn on push
  notifications?".

## The images

![The badge on a dark status bar, the 192 icon, and the maskable 512](push-polish/preview.png)

`push-polish/push-badge.png` (96×96, transparent), `push-polish/app-icon-192.png`, `push-polish/app-icon-maskable-512.png`.

## Tests

- `tools/notify-tests/push-pace.php`: 22 tests, on MariaDB, with WebPush stood in. It covers:
  - the tag by thing;
  - renotify only on a rise or Critical;
  - the pace and its summary;
  - Critical during the pace;
  - the routes;
  - the payload and the service worker;
  - the badge's alpha;
  - the manifest and its icons;
  - the head link (not on trvgo).
- `notify.php` (56), `push.php` (54) and `comms-engine.php` (74) still pass. comms-engine's `Operations` stand-in gained
  `KEYS`, which `EnvSettings::secrets()` has read since 0173 R2.

## For Jason, once it's live

Tap **Show** or **Allow** on the warning, never Unsubscribe. Then in Chrome, use **⋮ → Add to Home screen** (or **Install
app**), so pushes carry Traversence's name and icon.
