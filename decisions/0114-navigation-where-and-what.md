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
