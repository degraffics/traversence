# Piece 2's tests (decisions/0179; handoffs/piece2-team.md §12)

Run on MariaDB or MySQL, with a root that holds `app/` (the mirror's `sync-app` with the release's `app/` laid over it) and
`website/` (the mirror's `sync-storage/js` at least), and a `.env` two folders above the root (`Database::envPath`:
four folders up from `app/lib/`) naming `DB_DATABASE=lv_main`.

- `roster.php` (P2-2, the Team): the schema and its seed each run twice; 33 roles, 9 working, 11 advocates, 13 on standby;
  an edited role never written over; `Access::canAs` against the rules for every level and department; advocates and the
  Founder never act; Edit with History; the status and its way back; the tool `config/team` (groups, filters, drawer,
  actions). 51 checks: `mariadb -e 'DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main' && php roster.php <root>/app`.
- `roster.cjs` with `router.php` (P2-2 rendered at 1280 and 390): groups closed until opened (phones too), one page for five
  group rows, the drawer and its words, ×, Esc and a click outside, Put on standby and Make active through the drawer's form,
  History in place, no sideways scroll, no page error. 40 checks: `node roster.cjs <root> [shots dir]`.
