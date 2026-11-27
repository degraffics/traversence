# C.8 open now (open-now.zip): build, screens, seed

Built on Sync 2026-11-27 02:50 UTC and on C.4's `website/directory/index.php`. Rebuild on the newest sync once C.4,
place-list and r2-lanes-2 (faq.md) are live. Files: `app/lib/OpenNow.php` (new), `Nations.php` (allAt), `SearchIndex.php`,
`UniversalSearch.php`, `app/ui/header.php` (.tvs-isopen), `app/ui/faq.md`, `website/api/search_listings.php`,
`website/js/search-panel.js`, `website/directory/index.php`, `website/hub/index.php`.

Harness seed for `tools/search-tests/open-now.php` and `open-now.cjs`: listings 101 Owner Diner (owner hours, locked), 102
Crawler Cafe (ai_crawler hours, a crawler fact source), 103 OSM Grill (ai_crawler hours with an openstreetmap.org fact
source), 104 Staff Pizza (admin hours, locked), 105 Shut Owner Bar (owner hours, closed every day), 106 No Hours Taco; all
category 1 (Pizza) around Show Low, AZ; `nation_shapes` with a Navajo Nation box (35-37 N, 111.5-108.5 W) and a Hopi box
inside it (35.6-36.2 N, 111-110 W); `search_index_state.built_listing` set. Results: open-now.php 23/23, open-now.cjs
22/22 at 390 and 1280.
