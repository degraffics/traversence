# Lexicon builders

Both read WordNet 3.1 (download the database files: `data.*`, `index.*`, `index.sense`, `*.exc`) and write PHP data
files the site reads. Nothing on the site reads WordNet itself.

## build_families.py (decisions/0064)

    python3 build_families.py /path/to/wordnet31 /path/to/site/api/lib/data/word-families.php

Each common noun's family (belongings, documents, pets, vehicles…) and irregular word forms, for the situation reasoner.

## build_concepts.py (decisions/0067)

    python3 build_concepts.py /path/to/wordnet31 categories.json /path/to/site/api/lib/data/concepts.php

Every English word mapped to the categories that serve it ("x-rays" → Diagnostic Imaging Centers). It reads
`category-terms.tsv` (the everyday words for each category; edit it freely) and `categories.json`, exported from the
live database in phpMyAdmin as JSON (Export → JSON) from this query:

```sql
SELECT c.name, COALESCE(p.name, '') AS parent,
       COALESCE((SELECT GROUP_CONCAT(si.label SEPARATOR ' | ') FROM search_index si
                 WHERE si.kind = 'listing' AND si.category_id = c.id), '') AS labels
FROM categories c LEFT JOIN categories p ON p.id = c.parent_id
WHERE c.is_active = 1;
```

With word embeddings (recommended, decisions/0067): `pip install "spacy>=3.7,<3.8"`, download the
`en_core_web_lg-3.7.1` wheel (about 590 MB, build machine only) from github.com/explosion/spacy-models/releases, unzip it, and point `CONCEPTS_VECTORS` at
the `en_core_web_lg/en_core_web_lg-3.7.1` folder inside:

    CONCEPTS_FLOOR=0.04 CONCEPTS_VECTORS=/path/to/en_core_web_lg-3.7.1 python3 build_concepts.py wordnet31 categories.json concepts.php

Rebuild when categories change. To try words without writing the file:

    CONCEPTS_TRY=x-ray,gym,mushroom python3 build_concepts.py /path/to/wordnet31 categories.json /dev/null

`CONCEPTS_FLOOR` (default 0.12; the shipped file used 0.04, with links under 0.08 offered as "maybe") is the loosest link kept.

## build_word_rank.py (decisions/0067, 2026-10-07)

    python3 build_word_rank.py /path/to/en_core_web_lg-3.7.1 /path/to/site/api/lib/data/word-rank.php

How common each English word is (the 100,000 commonest, from the order of the spaCy vectors). Search uses it to finish a
word still being typed ("new mus" → new music, new museum; never the genus Mus). Rebuild only with a new model.
