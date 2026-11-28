# Batch jobs (Data & Content, 2026-11-28; session manager 03:29, Jason via the CEO: "Use the batch api now")

Run from `/home/user` (paths are relative to it: `traversence/…`, `traverse-3way-sync/…`):

    python3 traversence/tools/batch/batch.py submit traversence/handoffs/batch-jobs/job1-kind-check.jsonl --max-usd 10
    python3 traversence/tools/batch/batch.py submit traversence/handoffs/batch-jobs/job2-intake-0164.jsonl --max-usd 30
    python3 traversence/tools/batch/batch.py fetch <batch_id> --out traversence/research/ai-results/<job>/

- **Job 1:** the kind check of 1A's 3,324 public listings. Haiku, 28 requests of 120 listings each, with public fields only (id, name, city, state, category, group, website), read through the public search API at 01:51 UTC. Estimated at about $0.07.
- **Job 2:** the intake paths read against decisions/0164, with live code from Sync 02:52 UTC (before the pause, #328). Opus, 8 requests, 0164 attached to each. Estimated at about $1.44.
- **Job 3:** the ADHS robots.txt and terms read. It isn't here: batch.py attaches only repo files, and this environment refuses the ADHS hosts, so A1's crawler (w14_terms) reads them.
- Results feed research/data-capture-review and F9's confirm pass.
