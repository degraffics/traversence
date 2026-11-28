# The order page's split fix (held for slot #8)

Gate 2's run (2026-11-27) logged "Cannot read properties of null (reading 'hasAttribute')" on Back-Office → Orders → an
order. Cause: the lots dialog's Split button (`admin/client-order.php:1173`) carried the save group's bare `data-split`,
so the save group's script (`:1878`) took it in, found no `[data-split-main]` and died at `:1882`. Whenever the lots
dialog renders (`ship_mode` single or multi, `:1119`; test copies carry it), Save ▾ lost its list and its confirm
question (the QuickBooks invoice and COGS warnings): live harm on money wording.

Fix: the lots button is `data-lots-split` (`:1173` and its handler `:2064`); the save script reads only
`.co-split[data-split]`. Every `data-split` in the live mirror (sync-storage, sync-app, sync-trvgo) is in this one
file; no other caller. No words change.

Built on Sync 2026-11-27 19:50 UTC: `python3 patch.py <root laid out as the mirror>`, anchors asserted; rebuild on the
newest sync before zipping. Test: `tools/order-tests/split-fix.cjs <base> <shipped order> <plain order>` on the order
harness: 16/16 at 1280 and 390 (no error, Save's confirm and ▾ list, the lots Split still splits into 3); the live copy
fails it at 390.
