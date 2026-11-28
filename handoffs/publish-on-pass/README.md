# Publish on pass (2026-11-28)

Ticket: "A release publishes the moment its check passes, not on the next 15-minute tick". Jason, 2026-11-28 15:55 UTC:
"Push to live should be done immediately, upon pass." It replaces the CEO's Tier 4 option (a) of 2026-11-27
(`handoffs/pipeline-tick/README.md`: beforeafter.yml doesn't ping, the ping never moves a verdict on).

## The cause

- `beforeafter.yml` committed the verdict and told nobody.
- `website/api/releases-pull.php` took builds only; verdicts waited for `releases_tick` (every 10 minutes).

## The fix

| Where | Change |
|---|---|
| `.github/workflows/beforeafter.yml` (docs repo, in main with this hand-off) | last step, Tell the site the verdict is in: the same call as `release-ping.yml`'s |
| `website/api/releases-pull.php` (release `publish-on-pass.zip`) | after taking builds, `BeforeAfter::advance` under the same one-call grant (`Releases::asPing`), each in its own try |
| `app/ui/faq.md` | the before-and-after entry: one sentence |

Never unchecked: `BeforeAfter::advance` is unchanged. It publishes only on a pass verdict read from main, and a step
that throws leaves the release waiting for the job.

## Proof

`php tools/piece3-tests/tickverdict.php <root>` (50 checks on MariaDB; `<root>` holds `app/` and `website/`, with `.env`
two folders above it). The real endpoint runs in its own process (`tickverdict-pull.php`).

## Way back

Roll back the release on https://traversence.com/admin/config/releases ; remove the workflow step by a PR.
