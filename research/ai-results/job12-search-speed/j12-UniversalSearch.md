# Search speed review: `UniversalSearch.php` against `indexes.txt`

*Draft for human review. Every claim cites `UniversalSearch.php` line numbers unless another file is named.*

## Scope and limits

- **The file is truncated.** The scratch copy ends at line 3294, and statements after that point are not shown.
- **Most indexes are not shown.** `indexes.txt` lists no index on `search_index`, `categories`, `zip_coordinates`, `entity_metadata`, `micro_clusters`, `landmarks`, `cluster_subgroups`, `geo_hubs`, `hubs`, `place_stories`, `journeys`, `situation_needs` or `search_meanings`. The only index on a table this file queries is `entities` `uq_ident (ident_live)` (`ListingIdentity.php:697`), and no query here uses `ident_live`. So every finding below is **"index not shown"** unless stated otherwise.
- **SQL built in other files is not shown and not reviewed.** This includes:
  - `SearchText::CI`, `SearchText::where`, `SearchText::catNameSql`
  - `SearchIndex::whereSql/find/rows`, `CategoryAlso::sqlFor`, `Landmarks::visibleSql`, `PlaceScope::resolve`
  - `Community::*`, `Experiences::search`, `Situations::*`, `Concepts::*`, `Lexicon::*`, `Topics::*`, `Heritage::*`, `SourceIndex::search`, `HelpFind::near`
- **A collation caveat applies to many findings.** `SearchText::CI` is appended after columns (e.g. 498, 585, 596). Its definition is not shown. If it is a `COLLATE …` that differs from the column's own collation, MySQL cannot use an index on that column for `=` or `LIKE`. This affects S1, S5, S6, S7, S11, S12, S14, S15, S17, S20, S46, S48 and S49. Check this first.
- **The one size figure in the code is a comment.** Line 967 says `zip_coordinates` holds about 41,000 rows. Lines 966–967 say `city_key` has an index added by `2026-11-22_zip_city_index.sql`. That migration is not in the mirror, so the index is not shown