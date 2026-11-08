# Site audit and phased releases

Decision 0145. One file per phase, each with what gets built, what Jason provides, when it's done and how it rolls
back.

| Phase | File | Changes the live site? |
|---|---|---|
| 1 | [Foundations: code checks, zip check, delivery log, scorecard, the decision register and the page tree](phase-1-foundations.md) | No |
| 2 | [Feature switches, test accounts, rendered checks, UI and UX on every page, the tool inventory, read-only tool checks](phase-2-switches-and-accounts.md) | Yes: switches and the test-account flag |
| 3 | [The staging copy of the site at `staging.trvgo.us`, and checks of every tool that changes something](phase-3-staging.md) | No (staging is separate) |
| 4 | [Journeys and the review](phase-4-journeys-and-review.md) | Yes: the Pulse "Site health" case |
| 5 | [Planned, phased releases](phase-5-releases.md) | Yes: the Releases page |
| 6 | [Deploy from git (optional)](phase-6-deploy-from-git.md) | Changes how files reach the server |

**One build.** All of it is built together from one punch list, [punch-list.md](punch-list.md), then rolled out in the
phase order.

## What Jason provides, all phases

| Phase | What you provide |
|---|---|
| 1 | Approval. `MIRROR_READ_TOKEN` (read-only access to the mirror). |
| 2 | Approval. The test accounts (table in the phase file). GitHub secrets `CHECK_ACCOUNTS` and `HEALTH_TOKEN`, with `HEALTH_TOKEN` also in the live `.env`. Confirmation that test payments run in sandbox. |
| 3 | Approval. Confirmation that Bluehost allows a subdomain of trvgo.us and another database, and where trvgo.us's DNS is managed. Staging's database password and viewing password in its `.env`. `staging.trvgo.us` added in Cloudflare Turnstile. |
| 4 | Approval. Your top 10 journeys. The measures (for example, steps too many). Decisions on the first proposals. |
| 5 | Approval. Who may publish. Rollout defaults and timing. Release windows. |
| 6 | The decision to switch and a date. Agreement that all file changes go through releases. |

## Files these phases create

- `punch-list.md`: everything Jason provides for the one build, in order
- `test-accounts.md`: the test accounts, their roles and what each covers (no passwords: those are only in the `CHECK_ACCOUNTS` secret)
- `rules.md`: the rule catalogue (Phase 1)
- `../decisions/index.md`: the decision register (Phase 1)
- `../routes.md`: the site page tree, generated and checked (Phase 1)
- `tools.md`: the tool inventory: every function and tool, who may use it, what should happen (Phase 2)
- `scorecard.md`: each page's health, live and staging (Phases 1–4)
- `journeys.md`: the journey catalogue (Phase 4)
- `page-map.md`: each page and the decisions that govern it (Phase 4)
- `proposals.md`: improvements awaiting approval (Phase 4)
- `../deliveries/`: every zip and release, and whether its files went live (Phases 1, 5)
