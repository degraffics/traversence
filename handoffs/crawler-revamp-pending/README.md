# Crawler revamp: built, tested, approved, not yet sent

Patches against the live copy of Sync 2026-11-13 19:58 UTC, one folder per release, to send in this order: `w6`, `w9`,
`w11`, `w12`. Jason approved them in chat ("merge all", 2026-11-13). How to send each: `handoffs/crawler-revamp.md` §8.
Each folder: `change.diff` (apply with `patch -p1` to a tree laid out as `app/`, `website/`), `release.json`. The FAQ
line of each: `python3 faq-lines.py <w> <path to app/ui/faq.md>`. Delete this folder once all four are live.
