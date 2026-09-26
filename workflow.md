# Traversence: Documentation Governance & Operating Protocol

*Version: 2026-09-22*
*Governance tier: Meta — this document governs how the other governance documents get written and maintained. It doesn't describe the product; it describes the process. Adapted from the Traversence Master Workflow doc to reflect the file split now in use: `charter.md`, `prd.md`, `architecture.md`, `brand.md`, `commercial.md`, `routes.md`, this file, `apdex.md`, and the append-only `decisions/` log.*

## 1. Core Philosophy & Division of Labor

Traversence is built on a high-efficiency, low-token-usage workflow that prioritizes zero-extraction direct-to-operator economics, rigorous state control, and modular code execution.

| Actor / Tool | Responsibility & Role |
|---|---|
| Claude (The Precision Engine) | Pure code execution: clean PHP 8+, native PDO, Tailwind, via a lightweight kernel. Returns complete functional blocks. |
| Git (The Mechanical Memory) | Version control and state tracking — the objective source of truth for file modifications, diffs, commits, code history. |
| Gemini (The Intelligence Layer) | Administrative synthesis — turns raw terminal/Git output into changelogs and testing notes without taxing active coding chats. |
| You (The Director) | Governance and integration — holds the master project vision, orchestrates workflow handoffs, manages file syncs across deployment tiers. |

## 2. Multi-Tier File Management Strategy

- **OneDrive (Master Source of Truth):** project master documentation, brand identity guidelines, PRDs, architectural diagrams, archival backups. Kept external to active code execution to avoid unnecessary token consumption.
- **Git (Local Repository & Version Control):** all active codebases, version histories, database schema files, component scripts. Day-to-day dev tracking, diffs, clean staging.
- **FTP (Live Deployment Target):** pushes stable, tested code to the live server for traversence.com, via the three-way sync pipeline or manual staging.

## 3. Documentation Management & Maintenance Protocol

The governance-tier documents (`charter.md`, `prd.md`, `architecture.md`, `brand.md`, `commercial.md`, `routes.md`, this file, `decisions/*.md`) and `apdex.md` are maintained under strict lifecycle rules to prevent structural drift and documentation bloat.

- **Explicit-Request Only:** governance documents are never modified as a silent side-effect of code changes or architecture discussions; they require an explicit instruction naming the target document and scope.
- **Snippet-First Delivery:** substantive edits are first drafted and displayed inline as text diffs for review.
- **Gated Approval Gate:** no substantive change to governance rules, pricing tiers, or routing master maps is written to disk without an explicit affirmative response (`GOVERNANCE APPROVED`) from the project owner.
- **No-Duplication Rule:** information is owned by exactly one file (pricing lives only in `commercial.md`, routes live only in `routes.md`, brand tokens live only in `brand.md`), using cross-references instead of restating facts. This is the rule the old single-file model was breaking, and the reason for this split.
- **Version Stamps, Not Changelogs:** each document carries a single top-level version stamp (`*Version: YYYY-MM-DD-HHMM*`), overwritten in place.
- **Author/Contributor Tagging:** each governance document also carries an explicit `Author / Contributor` tag (e.g. Claude or Gemini), overwritten in place alongside the version stamp, so provenance stays clear across a multi-model workflow. Added per `GEMINI-PROTOCOLS.md`, Gemini's own operating rules for this project — its content-governance rules are the same ones in this file (explicit-request only, gated approval, no-duplication, plain-text standard), restated there for Gemini sessions specifically. A parallel `claude.md` is referenced elsewhere as Claude's equivalent kernel but hasn't been shared into this restructuring — if it adds rules beyond what's here, it stays the real authority for Claude sessions once available.
- **The ~150k Character Split Threshold:** any file approaching ~150,000 characters gets a structured split, moving older historical/narrative content into dated `daily-logs/` files, leaving clean pointers behind.
- **Plain-Text Standard:** all ongoing governance documentation stays plain-text `.md` — no `.docx` or `.pdf`. This restructuring converts the last remaining `.docx` governance files (Master Governance Document Model, Brand Identity guide, Bluehost migration blueprint, this workflow doc) into `.md`, closing that gap.

## 4. Session Conventions

Two conventions from `GEMINI-PROTOCOLS.md` worth carrying across both assistants rather than leaving Gemini-specific:

- **`[NOTE: ...]` anchoring:** when you drop a `[NOTE: ...]` marker into a chat, the assistant treats that block as the primary diagnostic focus for the rest of the turn, ahead of other chat history.
- **Full-file output:** when a governance file is updated, the assistant outputs the complete file, never a partial diff, so it can be dropped in as a straight replacement.

## 5. The Decision Log (`decisions/`)

New in this revision: architectural decisions that were previously buried inside prose (and re-explained, slightly differently, every time someone rewrote the architecture doc) now get one short, append-only file each in `decisions/`, numbered sequentially (`0001-`, `0002-`, ...). Format: Title, Status (Proposed / Accepted / Superseded), Context, Decision, Consequences. Once written, a decision file is never edited to reflect a change of mind — a changed decision gets a new numbered file that supersedes the old one, so the history of *why* stays intact instead of being silently overwritten.

## 6. Active Development Workflow (Task-Based)

To protect daily token limits and rate caps, development is segmented into isolated, single-task chats:

- **Start Fresh:** open a dedicated chat for a single feature or fix.
- **Lean Instructions:** rely on Claude's pre-loaded `claude.md` Executive Kernel (enforcing "Ship, Don't Narrate," security flags, complete structural code output).
- **Execute:** build, test locally, close the chat immediately on completion.

## 7. Automated Validation & Synchronization

- **App Directory Index (`apdex.md`) Maintenance:** any creation, deletion, or renaming of a project file requires an immediate matching line-item update to `apdex.md` in the same turn.
- **Three-Way Pipeline Self-Healing (TRAVERSE-3WAY-SYNC):** pre-flight integrity checks verify that critical config rules (e.g. `.htaccess` security blocks) and file indexes stay synchronized across OneDrive, GitHub, and live FTP.
- **Daily-Log Traceability:** every session's changes are recorded in a dated log (`daily-logs/session-log-YYYYMMDD-HHMM.md`).

## 8. The Automated Close-of-Day Loop

1. **Capture State Locally** — run `git diff --stat` for an exact inventory of everything touched.
2. **Synthesize via Gemini** — paste the diff stat into a zero-cost wrap-up chat: *"Here is today's git diff stat. Based on this, generate a concise 3-bullet changelog entry and a quick note on what needs to be tested next session."*
3. **Sync, Commit, and Close** — copy Gemini's output into the repo's changelog, commit via Git, push via FTP or the sync pipeline, archive any master documentation updates to OneDrive.
