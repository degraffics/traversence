# Review: `ListingIdentity.php` against ADR 0164

**Scope.** This review covers one file, `sync-app/lib/ListingIdentity.php`. Callers such as `IntakeStager`, `AddBusiness`, `IdentityJobs`, `Identity` and `EngineCase` are not shown, so I judge only what this file does and what it passes to them. Section references are to decisions/0164.

---

## Path 1: Identity keys and the database guard (`writeKeys`, `ensureKeys`, `uniqueKey`)

**(1) Trigger.** All machine:
- `writeKeys` is called on create or edit (per its comment), by `ensureKeys` and by `recheckTick`.
- `ensureKeys` runs inside `candidateIds`, so a read path writes up to 3,000 rows per ZIP.
- `uniqueKey` runs at the end of a `recheckTick` that merged nothing.

**(2) Writes.**
- `entities.ident_phone`, `ident_name`, `ident_addr`, `ident_geo`, `ident_live` and `ident_at`.
- On a unique-key clash, `ident_live = NULL` and an `EngineCase::raise('identity', …)` step.
- `uniqueKey` rewrites `ident_live` on every row of `entities` and runs `ALTER TABLE entities ADD UNIQUE KEY uq_ident`.

**(3) Person review.** None. None of these are public fields.

**(4) Rule gaps.**
- **§3.1 database guard.** `uniqueKey` changes the schema of the live listings table from inside a scheduled tick, with no person and no release. 0164 says the key is "added once the recheck has cleared today's exact twins". It does not say the tick adds it