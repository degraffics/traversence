# The design releases' tests (design/css-theme-brand.md)

- `edit-switch.php`: Release 3a's Edit page switch (`Blocks::editingOn`, `Blocks::zone`). Off: no outline, no Edit, the
  published content only, a draft not shown. On: outlined with Edit, the draft shown marked Draft. A cookie other than
  1 is off. 9 tests on MariaDB or MySQL, the PHP command line counting as an editor (`Blocks::can`): `php edit-switch.php
  <root>` (a root holding `app/`, with a `.env` four folders up from `app/lib/`).
