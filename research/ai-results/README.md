# Batch results index (2026-11-28)

Drafts and findings for the team to review. None is applied without review; nothing ships from a batch.
Job files are in `handoffs/batch-jobs/`. Costs are the real figures `batch.py fetch` wrote (Batch API, half price, API credit).
"Rows" is a rough count, not verified: JSON lines for jobs 1-2; for the rest, list or table rows citing file:line, plus the files in diffs.

| Job | What | Folder | Answers | Rows | Real cost |
|---|---|---|---|---|---|
| 1 | Kind check, 1A's 3,324 listings (Sonnet) | `job1a-kind-check/` | 34 | ~3321 | $1.65 |
| 2 | Kind check, 595 other AZ listings (Sonnet) | `job2a-kind-check-statewide/` | 6 | ~595 | $0.30 |
| 3 | Intake paths vs 0164/0163/0066 (Opus) | `job3-intake-0164/` | 8 | ~0 cited | $1.76 |
| 3b | Same, second pass | `job3b-intake-0164-pass2/` | 8 | ~0 cited | $1.76 |
| 4 | Search audit | `job4-search-audit/` | 2 | ~2 cited | $0.94 |
| 4b | Search audit, third pass | `job4b-search-audit-pass3/` | 2 | ~1 cited | $0.94 |
| 5 | 150 search test queries | `job5-search-test-set/` | 3 | ~0 cited | $0.51 |
| 6 | FAQ/page promise check (Sonnet) | `job6-promise-check/` | 8 | ~0 cited | $1.30 |
| 7 | Hometown Theater kit draft | `job7-theater-kit/` | 1 | ~0 cited | $0.14 |
| 8 | Second independent search audit | `job8-search-audit-second/` | 2 | ~0 cited | $0.93 |
| 9 | Intake paths in depth, one per path | `job9-intake-in-depth/` | 11 | ~22 cited | $3.42 |
| 10 | Security review of public endpoints | `job10-security-endpoints/` | 5 | ~222 cited | $1.22 |
| 11 | Money paths vs 0177/0178 | `job11-money-paths/` | 7 | ~49 cited | $1.09 |
| 12 | Search speed (indexes mostly not shown) | `job12-search-speed/` | 3 | ~3 cited | $0.81 |
| 13 | Launch gates (0181 §1) | `job13-launch-gates/` | 1 | ~9 cited | $0.24 |
| 14 | Events E1/E2/P1/P2 vs 0134 | `job14-events/` | 5 | ~19 cited | $1.70 |
| 15 | 1A coverage by town (Sonnet) | `job15-1a-coverage/` | 1 | ~0 cited | $0.05 |
| 16 | Platform atlas, 18 areas (also in research/platform-atlas/) | `job16-platform-atlas/` | 18 | ~439 cited | $7.57 |
| 16b | Atlas README (two-page platform, gaps) | `job16b-atlas-readme/` | 1 | ~0 cited | $0.28 |
| 17 | Patch drafts: search-release-1, care-claim-kind | `job17-patch-drafts/` | 2 | ~1 cited, 2 diff files | $0.74 |
| 18 | The team to Jason's spec, by role group | `job18-team/` | 6 | ~0 cited | $2.41 |
| 19 | Decided-not-live items, patches | `job19-decided-not-live/` | 14 | ~0 cited, 6 diff files | $4.22 |
| 20 | Control Board items, patches | `job20-control-board/` | 6 | ~0 cited, 4 diff files | $2.58 |

| 18b | Team, second independent pass | `job18b-team-pass2/` | 6 | see file | $2.41 |
| 18c | Team, third pass on AI access | `job18c-team-pass3-ai-access/` | 6 | see file | $2.41 |
| 10b | Security, second pass | `job10b-security-pass2/` | 5 | see file | $1.18 |
| 11b | Money paths, second pass | `job11b-money-pass2/` | 7 | see file | $1.07 |
| 16c | Atlas, second pass (18 areas) | `job16c-atlas-pass2/` | 18 | see file | $7.56 |
| 16d | Atlas README, second pass | `job16d-atlas-readme-pass2/` | 1 | see file | $0.28 |
| 13b | Launch gates, second pass | `job13b-gates-pass2/` | 1 | see file | $0.21 |
| 21 | Reconcile: team, security, money, gates, and 18 atlas areas (merged atlas in research/platform-atlas/merged/) | `job21-reconcile/` | 22 | see file | $2.44 |
| 21b | Reconcile: atlas README | `job21b-reconcile-atlas-readme/` | 1 | see file | $0.12 |

**Total: $54.28** (wave 1 $36.58; wave 2 $17.70, independent second passes and reconciles).

Notes:
- Jobs 1-2 omit "our source" (not public). Job 12 can't see most search-table indexes: app/migrations isn't in the mirror.
- Job 19 doesn't cover open Work-queue parents (live database). Job 15's counts come from the public search at 03:50 UTC.
- Inputs for jobs 9-20 were line-numbered copies kept in a session scratch folder (not committed); jobs 3-8's are in `handoffs/batch-jobs/inputs-numbered/`.
- The platform atlas is in `research/platform-atlas/` (18 areas and its README).
