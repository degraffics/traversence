# Card photos and the Parks strip: test

The finder (`app/lib/PlacePhotos.php`) and the strip's own functions (`website/discovery/index.php`: `dv_parks`,
`dv_park_cards`, `dv_card_photos`, `dv_min_miles`), on a real MySQL or MariaDB, with Commons stood in by fixed photos.

1. Make a tree: the live `app/lib/` copied in, then the `*.stub.php` files over their namesakes (`StockWikimedia.stub.php`
   to `app/lib/stock/StockWikimedia.php`, the rest to `app/lib/`), the release's `PlacePhotos.php`, and
   `website/discovery/index.php` with `test.php` beside it.
2. `CREATE DATABASE cp_test`, then the release's migration and `seed.sql`.
3. `php website/discovery/test.php`: 19 checks. These cover the finder's rules (name words, distance, size, licence, a
   private place never looked up), the W9 pause, the strip near the place (no Kansas), photos found before stock, credits
   and miles, the fallback when nothing is close, and no place set unchanged.
4. `php website/discovery/want.php` (copy it beside `test.php`; it adds a `page_cache` table): 8 checks. A listing opened without a photo is wanted once, only listings are wanted, the next run looks it up first and only it, it then leaves the list, and its photo shows.
