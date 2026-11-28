# Review: `DataCleanup.php` against decisions/0164

**Scope.** This reviews `traverse-3way-sync/sync-app/lib/DataCleanup.php` only. Calls into `ListingIdentity`, `Landmarks`, `Suggestions`, `PlaceFinder`, `AiClient` and `SearchIndex` are taken at their names; their bodies aren't shown, so nothing below depends on them.

**A general note on the tribal-