# Situations.php: SQL inventory, index match, and slow-query review

Draft for human review.

## Ground rules for this review

- **No index coverage.** None of the tables Situations.php touches appear in indexes.txt: `situations`, `situation_triggers`, `situation_needs`, `categories`, `search_index`, `entity_metadata`, `search_frames`, `search_outcomes` and `search_terms`. The only nearby entry is `entities` uq_ident (sync-app/lib/ListingIdentity.php:697), which this file doesn't query. So every match below is **index not shown**. Keys may already exist in app/migrations, which isn't in the mirror.
- **Hints from the code.** Some code behaviour suggests keys exist, but none of them are shown:
  - The INSERT…catch at Situations.php:494 hints at a unique key on `situation_triggers`.
  - The INSERT…catch at :1250 hints at one on `search_outcomes`.
  - The INSERT…catch at :1265 hints at one on `search_terms`.
- **Table sizes.** Table sizes and column types/collations aren't shown. The only size I can see is the SEED at :23–452, which holds roughly 90 situations and several hundred phrases. Admin edits can add to that, and how much is not shown.

## 1. Every SQL statement (numbered)

| # | Line | Table(s) | Lookup shape | Index needed | Shown? |
|---|---|---|---|---|---|
| S1 | 460 | situations | `LIMIT 1` probe | none | n/a |
| S2 | 471 | situations | `COUNT(*)` | none (small) | n/a |
| S3 | 473 | situations | INSERT | – | – |
| S4 | 474 | situation_triggers | INSERT | – | – |
| S5 | 475 | situation_needs | INSERT | – | – |
| S6 | 476 | situations | `slug = ?` (in seed loop) | slug | not shown |
| S7 | 485 | situations | UPDATE `id = ?` | PK | not shown |
| S8 | 487 | situation_triggers | DELETE `situation_id = ? [AND source]` | situation_id | not shown |
| S9 | 488 | situation_needs | DELETE `situation_id = ?` | situation_id | not shown |
| S10 | 558 | categories | `is_active = 1`, all names | (is_active, name) | not shown |
| S11 | 575 | situation_triggers ⋈ situations | all live phrases | situation_id | not shown |
| S12 | 612 | situations | `id IN (…) AND danger = 1` | PK | not shown |
| S13 | 613 | situations | `slug = ? AND status IN` | slug | not shown |
| S14 | 627 | situations | same as S13, in a loop | slug | not shown |
| S15 | 642 | situations | same as S13 | slug | not shown |
| S16 | 651 | situations | `id IN` | PK | not shown |
| S17 | 749 | situations | `id IN` | PK | not shown |
| S18 | 808 | situations | `id IN AND status IN` | PK | not shown |
| S19 | 810 | situation_needs | `situation_id IN … ORDER BY situation_id, pos` | (situation_id, pos) | not shown |
| S20 | 835 | situation_triggers | `LIMIT 1` probe | none | n/a |
| S21 | 850 | situations | `label = ? LIMIT 1` (loop, ≤4) | label | not shown |
| S22 | 854–855 | situations ⟕ situation_triggers | `LOWER(label) LIKE '%…%' OR slug LIKE '%…%' OR phrase LIKE '%…%'`, DISTINCT | none can help | not shown |
| S23 | 859 | situations | `ORDER BY label LIMIT 200` | label | not shown |
| S24 | 863 | situations | `id IN` | PK | not shown |
| S25 | 865 | situation_triggers | `situation_id IN … ORDER BY id` | situation_id | not shown |
| S26 | 866 | situation_needs | `situation_id IN … ORDER BY pos` | (situation_id, pos) | not shown |
| S27 | 883 | situations | `id = ?` | PK | not shown |
| S28 | 886 | situation_triggers | `situation_id = ? ORDER BY id` | situation_id | not shown |
| S29 | 888 | situation_needs | `situation_id = ? ORDER BY pos` | (situation_id, pos) | not shown |
| S30 | 893 | search_frames | `slug = ? ORDER BY id` | slug | not shown |
| S31 | 898 | situations | `id = ?` (2nd read of same row) | PK | not shown |
| S32 | 907 | situations | `id = ?` | PK | not shown |
| S33 | 910 | situation_triggers | `situation_id = ? AND phrase = ?` | (situation_id, phrase) | not shown |
| S34 | 912 / 913 | situation_triggers | INSERT | – | – |
| S35 | 915 | situation_triggers ⋈ situations | `phrase = ? AND situation_id <> ?` | phrase (leading) | not shown |
| S36 | 921 | situation_triggers | DELETE `situation_id = ? AND phrase = ?` | (situation_id, phrase) | not shown |
| S37 | 988 | situations | `id = ?` | PK | not shown |
| S38 | 1010 | situations | UPDATE `id = ?` | PK | not shown |
| S39 | 1015–1016 | situations | `slug = ?` in a while loop | slug | not shown |
| S40 | 1017 | situations | INSERT | – | – |
| S41 | 1021 | situation_triggers | DELETE `situation_id = ?` | situation_id | not shown |
| S42 | 1022 | situation_triggers | INSERT | – | – |
| S43 | 1024 | situation_needs | DELETE `situation_id = ?` | situation_id | not shown |
| S44 | 1025 | situation_needs | INSERT | – | – |
| S45 | 1029 | situations | `id = ?` | PK | not shown |
| S46 | 1031 | search_frames | DELETE `slug = ?` | slug | not shown |
| S47 | 1032 | search_frames | INSERT | – | – |
| S48 | 1036 | situations | UPDATE `id = ?` | PK | not shown |
| S49 | 1060–1061 | categories | `name LIKE ?`, once per pattern | name | not shown |
| S50 | 1077 | situation_needs | `situation_id IN … ORDER BY pos` | (situation_id, pos) | not shown |
| S51 | 1139–1142 | search_index | `kind IN` + OR(category_id IN / `terms LIKE '% w %'`…) + bbox, `ORDER BY here DESC, weight DESC LIMIT 400` | see below | not shown |
| S52 | 1149 | entity_metadata | `meta_key = 'phone' AND entity_id IN (≤500)` | (entity_id, meta_key) | not shown |
| S53 | 1203–1204 | situation_needs ⋈ situations | `status='live' AND requires<>'' AND k