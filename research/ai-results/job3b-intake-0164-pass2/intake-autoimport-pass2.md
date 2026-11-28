# Review of `AutoImport.php` against decisions/0164

**Scope.** This review covers only `sync-app/lib/crawler/AutoImport.php`. The file hands its writes to `IntakeStager::approve()`, `IntakeStager::merge()`, `IntakeStager::reject()`, `Identity::adopt()`, `Aliases::add()` and `Nexus::create()/link()`. Those were not attached, so I have not assumed they enforce anything. Where a rule depends on them, I say so.

---

## Path 1: The pipeline pass, `advance()`

**(1) Trigger.** A machine runs it as a scheduled pass over `crawler_raw_index` rows with `status='staged'`, oldest `advanced_at` first, up to 150 (maximum 500) at a time.

**(2) What it writes.**
- It refreshes `raw_payload` with `IntakeStager::freshMatch()` and `suggestFor()`.
- It then sends each row down one of three branches:
  - **"covered"** (Path 4);
  - **"obvious"** merge through `fillGaps()` (Path 3);
  - **eligible** publish through `tryImport()` (Path 2).
- Otherwise it writes the updated payload back.

**(3) Human review before public.** None.

**(4) Rule breaks.**
- **§3.1, "one identity check, on every path".** The match is read fresh, which is good. But the decision is made from `obvious()`/`coveredBy()`/`relation()` in this file, not from `ListingIdentity::resolve()`. That leaves two match rule-sets: the ones here and whatever `resolve()` uses.
- **R9 / §1.1, "failed crawls … a Pulse item".** Any throwable is only `error_log`'d. A row that fails every pass is never surfaced to anyone.

**(5) Smallest fix.**
- Route the branch decision through `ListingIdentity::resolve()` and treat `obvious()`/`coveredBy()` as its callers' adapters.
- Count exceptions per row in the payload. After N, raise a step in the engine's Pulse case (X3).

---

## Path 2: Automatic publish, `tryImport()` → `IntakeStager::approve(…, null, note)`

**(1) Trigger.** Machine: from `advance()`, or any caller passing a staged row, while `AUTO_IMPORT` is not `off`.

**(2) What it writes.**
- A new `entities` listing built from `input($