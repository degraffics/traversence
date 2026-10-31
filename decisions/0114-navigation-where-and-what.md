# 0114: Navigation: one place for where, one for what

Status: Built (2026-10-23). Jason, 2026-10-23, on the phone menus: "the right and left navigation systems seem to be
competitive or redundant… Ui/Ux from a what... how... where... simple", and "Why does help center go to sign in."

## The rule

| | Answers | Holds |
|---|---|---|
| **The compass** (phones; the icons across the top bar on wider screens) | *Where can I go?* | Let's Explore, Get Local, Social, Marketplace, Trip planner · Sign in, or your Dashboard · Help Center · Set location, Our approach, Privacy, Terms. The same on every page. |
| **The ☰ / your photo** (phones; the rail on the left on wider screens) | *What can I do here?* | Only this part of the site's tools · Dashboard first and Switch account and Sign out at the foot, when signed in. |

- Nothing is listed in both. The phone tools list no longer has **Switch to**: moving between parts of the site is the
  compass's job.
- **Pages outside a section** (FAQ, Privacy, Terms, the 404, our own pages):
  - their tools are the help pages (Help Center, Our approach, Privacy, Terms), in the rail on wider screens;
  - on phones the ☰ holds only you, and isn't there when you're signed out.
- **The wider screens' ☰ dropdown** holds you, your location and help. Home and the sections are already the logo and
  the icons across the top.
- **Help Center** is the FAQ (`/faq.php`) for everyone. It used to open the Dashboard's help section, which asked
  visitors to sign in.

## Where

- `app/ui/header.php`: the compass menu, the dropdown, and the Trip planner icon.
- `app/ui/app-shell.php`: the phone tools list (no Switch to; a page without tools holds only you).
- `app/ui/section-rails.php`: the rail for pages outside a section is Help.
- `app/ui/page-shell.php`: marks those pages.
- `faq.md`: "Where did the big logo and icons at the top go?" describes the two corners.

## Addendum (2026-10-23): you at the top of the phone menu

Jason: "When signed in the top line of the menu needs to be the user switcher. Also collapse toggles within the menu."

- **Signed in, the right-hand menu's first line on phones is you:** your initial, your name, and the account you're
  acting as.
- **Tapping it opens the account switcher in place, under it,** inside the menu, and tapping again folds it away.
  - It's the same switcher as on wider screens (`context-switcher.php`), moved into the menu.
  - Choosing an account inside it doesn't close the menu first.
- **Each thing once:**
  - the top line drops what your name already says ("Acting as Traversence staff · Owner", not the staff ID again);
  - the switcher under it lists only the other accounts, not the one you're on, and not its own Current Account heading.
- "Switch account" is gone from the foot of the menu. Sign out stays there.
- The menu's headings (Things to do, Settings…) fold open and shut as before.
- Wider screens: unchanged (the switcher opens from the person icon).
- **Fixes (2026-10-23):**
  - the open phone menu sits above the page's own buttons (Shortcuts);
  - on account and admin pages your name keeps clear of your photo in the corner;
  - "Your member account · Not linked yet · how to link" opens the FAQ's steps, not the archived staff logins page;
  - with listing accounts, the switcher has **Business portal** (`/business/`) beside **Your listings**
    (`/user/accounts.php`).

## Addendum (2026-10-23): your listings open in the menu

Jason: "I dont want to leave menu to see my listings." **Your listings** in the account menu now folds open in place
(remembered): up to three of your other listings, each one tap to switch to, and **See all N listings**, a pop-up over
the page with every listing and a search box, also one tap to switch. All your accounts (`/user/accounts.php`, grouped
by Nexus) stays for the full view. One menu at a time: the tools list closes a page's section or filter drop-down, and
the other way round.
