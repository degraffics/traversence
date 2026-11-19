# The design releases' tests (design/css-theme-brand.md)

- `edit-switch.php`: Release 3a's Edit page switch (`Blocks::editingOn`, `Blocks::zone`). Off: no outline, no Edit, the
  published content only, a draft not shown. On: outlined with Edit, the draft shown marked Draft. A cookie other than
  1 is off. 9 tests on MariaDB or MySQL, the PHP command line counting as an editor (`Blocks::can`): `php edit-switch.php
  <root>` (a root holding `app/`, with a `.env` four folders up from `app/lib/`).
- `hero-order.php`: 3b-1's Explore hero: the page's own photos (landmarks' Commons) shown before any stock photo; stock
  only with no real photo; the stock credit on the photo ("Photo: <who> on <source>") with its i and details. 6 tests on
  MariaDB or MySQL: `php hero-order.php <root>` (it makes `stock_images` and `stock_uses` in the test database).
