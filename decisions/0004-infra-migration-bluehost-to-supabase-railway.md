# ADR 0004: Phased, Need-Driven Infrastructure Migration (Bluehost → Railway / Supabase, as Features Require)

**Status:** Accepted — revised from the original all-at-once proposal; nothing below is a scheduled cutover, each phase triggers only when its feature is actually built

## Context

The original version of this ADR proposed a single, all-at-once cutover: Bluehost → Supabase (data/auth) + Railway (app hosting) + Cloudflare (DNS/SSL), justified by Bluehost's inability to support `pgvector` semantic search or a proper Redis-backed queue architecture (`architecture.md` §12). That framing assumed those features were being built now — they aren't yet. This is a shoestring-budget startup: Bluehost's current cost is already sunk, and its real capacity (confirmed via cPanel: 10.66 GB disk, 89,591 files, 2.04 GB bandwidth against nominally "unlimited" quotas) has real headroom today. Provisioning new infrastructure ahead of the features that actually need it spends budget for no immediate benefit.

## Decision

Migrate in phases, each triggered by an actual feature being built — never by a calendar date or a single big-bang cutover.

1. **Bluehost stays primary, indefinitely.** The current PHP/MySQL app and database stay on Bluehost for as long as it remains economically and performance-capable. This is a deliberate, firm cost constraint, not a placeholder — re-evaluate only when a specific performance ceiling is actually hit (e.g., file count approaching the 200,000-file planning ceiling in `commercial.md` §1, or a query/queue workload Bluehost genuinely can't serve).
2. **Railway, added only when the async queue/worker layer is actually built.** The Redis-backed queues in `architecture.md` §12 (chat-vectorization, ingestion-scrape, notification-auth, maintenance-sync) need long-running worker processes that PHP shared hosting's execution-time limits can't support. When the scraper-to-article pipeline (§25/§26) actually gets built, the queue/worker layer moves to Railway — and only that layer; the main app and database stay on Bluehost. **This trigger fired sooner than expected, 2026-09-24 (`decisions/0017`):** OCR-based document screening for claim/dispute evidence turned out to need real, CPU-heavy binary execution (Tesseract) that Bluehost hard-blocks via `disable_functions` regardless of resource limits — a second, independent, and earlier real need for exactly this layer, arriving ahead of the scraper pipeline that originally justified it. The principle is unchanged (Railway only for the async layer, only when something real needs it) — it's just a different feature that ended up needing it first.
3. **Supabase, added only when vector-embedding topic search is actually built.** MySQL/MariaDB (Bluehost's stack) has no solid native vector-index support. When `topic_id` embedding storage and similarity search (§26) actually gets built, that specific capability moves to Supabase (Postgres + `pgvector`) — not the rest of the data model. Until then, no Postgres migration is scheduled.
4. **Build the embedding layer behind a service abstraction now, regardless of where it eventually lives.** Whatever code calls into vector storage/similarity search should go through a clean internal interface from day one, so pointing that interface at Supabase/`pgvector` later doesn't require rewriting every caller across the codebase.
5. **DNS/SSL and email decoupling are deferred, not adopted yet.** The original proposal's Cloudflare DNS/SSL move and Zoho/Google Workspace + Resend/SendGrid email split remain reasonable ideas but aren't tied to a specific feature trigger the way the two migrations above are — revisit if and when Bluehost's bundled DNS/email becomes an actual constraint, not proactively.

## Open Gaps (carried over from the original proposal, still unaddressed)

- No explicit checklist step yet for auditing and migrating application-specific secrets (session encryption keys, third-party API tokens) when the queue/worker layer does move to Railway.
- No explicit scan step yet for hardcoded `http://` links in the legacy PHP codebase.
- Domain-transfer mechanics (if the DNS/SSL move ever happens) remain undocumented against a schedule.

## Consequences

No infrastructure spend or migration work happens ahead of the feature that needs it — Bluehost hosting cost stays flat until a real ceiling is hit. `architecture.md` §16 is updated to describe this phased model instead of the original one-shot plan. The original plan's "losses" if adopted wholesale (the cPanel/phpMyAdmin dashboard, Bluehost's automatic snapshot backups, cPanel's built-in cron UI) are deferred along with it, since nothing about the current Bluehost operation changes until phase 2 or 3 actually triggers.

**Security note, unchanged and still binding:** an earlier draft of this migration plan had a live Supabase account password written in plaintext. That credential must be rotated before any part of this ADR is acted on, and no credential should be stored in this file or any governance document going forward — secrets belong in environment variables or a secrets manager, never in prose.
