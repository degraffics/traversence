# The phone menu (Design and trips, 2026-11-22)

Jason's screenshot of 2026-11-20 (`design-and-trips-2026-11-20.md` §2 item 3) and his pick on 2026-11-22 (option B, in
the pop-up reviewed by the session manager; mockups in `phone-menu/proof/pm-compare.png`). Built by
session_01CXLi5jNQCN3zFZ2Fh3qUNf on **Sync 14:20 UTC 2026-11-22 (25cb67e)** as `phone-menu.zip`: one file,
`app/ui/header.php` (`phone-menu/build.sh` replays `src/pm.patch`). Zip check clean. Carries #1256 (the layout probe's
false 5px gap, tool only) in the same PR.

| Behaviour | Source |
|---|---|
| Sign in has its own class (`.tvpm-sin`); the menu's column (`.tvpm-in`) no longer takes its outline, display and width, so Help Center no longer overlaps the last line and Sign in is full width | Defect (the session manager, 2026-11-22: "defects against the rules: fix without asking") |
| "Ai Assist" → "AI Assist" (the link, the coming-soon line and the file's header) | Defect: every other surface says AI |
| Words 1.35rem/700, icons 34px, a 96px logo, Help Center in sentence case | Jason, 2026-11-22: option B (decisions/0055's note) |
| Wider screens unchanged | the menu's CSS is the phone menu's only |

**Kept:** every link and button in the menu, Create account / Sign in for guests, Set location, the footer links, ×
and Esc. **Dropped:** nothing.

**Admin's test:** none (a visitor's menu). **Proof:** `tools/design-tests/phone-menu.cjs` 12/12 (390: opened by a tap
on the compass, no outline, Sign in 342px, AI Assist, Help Center 22px below the last line, option B's sizes, fits one
844px screen, × and Esc close it, no sideways scroll; 1280 unchanged). The account bar on a listing for Jason (an open
ask from 2026-11-20) stays open: it's his to look at.
