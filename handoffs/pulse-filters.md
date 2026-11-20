# Pulse filters: one button, saved views (Design and trips, 2026-11-20)

Built on Sync 18:34 UTC (5674a94) as `pulse-filter-panel.zip` (the name `pulse-filters.zip` was used before). Source:
`design/table-standard.md`, Jason's note of 2026-11-20 and its **Answers**. Build: `pulse-filters/build-pf.py` (reads the
mirror, writes `out/`; rerun it to rebuild on a newer sync), sources in `pulse-filters/src/`.

## What it does, each with its source
| Behaviour | Source |
|---|---|
| One button "Inbox · All (n) ▾" whose panel holds Your lists, Show, **Senders**, **Levels**, **Group by**, **Saved views** | note 1 (Senders, Group by, views); Levels by §4 "no filter controls in column headers" |
| Ticks apply at once, no Show/Cancel; the panel stays open so filters combine | note 1 "each tick applies at once" |
| Active filters as tags beside the button, each with ×; Clear all when there are two or more | note 1; CLAUDE.md "Filters" (only active ones, with ×) |
| The From and level funnels go; TvAsk.choose's "Show these …" box goes; sorting stays on the headers | note 1, §4 |
| Counts within the other filters; a sender or level with none greyed at 0 | note 2 |
| The panel opens under its button; ×, Esc, a click outside close it | note 3; CLAUDE.md "Everything that pops open closes" |
| Group by: 3 rows or fewer opens by itself; opened and closed groups remembered on the device; Open all / Close all beside the button | note 4; Jason's answer "3 rows or fewer" |
| Saved views kept with the account (`pulse_views`, records class), owner-only API, × with Undo | note 5; Jason's answer "With my account" |
| Words beside the bell, Your day, Quick actions, Settings at 768px and wider; icons only on a phone | note 6 |
| FAQ: "How do I work the Pulse stream?" updated in place (anchor kept) | CLAUDE.md "Keep the FAQ current" |

## Kept, moved, dropped (builder playbook §3)
- **Kept:** the lists (Inbox, Pinned, Follow up, Archived, folders), Show's views and their counts, the bell, Filter this
  list, sorting on the four headers (three taps), column resizing, paging and Show N a page, + New, the four header icons
  and their pop-ups, Group by Sender / Kind / Level / None, alarm group rows, every choice remembered on the device
  (`pb_view`, `pb_group`, `pb_table`, `pb_per`).
- **Moved:** Group ▾ → the panel's Group by; the From and level funnels → the panel's Senders and Levels.
- **Dropped:** only the centred "Show these senders / levels" box (note 1).
- **Changed:** choosing a list or a Show view no longer closes the panel (note 1: filters combine); `paintFilters` now
  paints the panel's ticks and the tags.
- New on the device: `pb_gopen` (Group by's open groups), `pb_aopen` (alarm groups opened or closed).

## Tests
- `tools/design-tests/pulse-filters.cjs` (65 checks at 1280 and 390, on the harness with 20 rows from 6 senders and 4
  levels): every new control pressed, ×/Esc/outside, remembered on reload, the saved view on a second device, Undo.
- notify-tests on a copy: admin-stream 31, member-pulse 4, pulse-act 35, all passing. They need the comms migrations,
  which are no longer in the mirror: take them from `comms-all.zip`, `comms-gaps.zip` and `notify-1.zip` in git history.
- `drawer-hash.cjs`'s default anchor moved from `#pb-gdd` (gone) to `#pb-fdd`.

## Open
- Alarm groups (0171 §8 rows) are remembered and follow Open all / Close all; whether they also open by themselves at 3
  or fewer is left for the session manager's read of Jason's answer (they don't in this build).
