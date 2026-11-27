# The Storage tile's silent catch (draft, held)

`website/business/router.php:163–166`: the Storage used tile on `/business/` was dropped by `catch (Throwable) { return ''; }`
with nothing logged (Release #319's look, 2026-11-27). The build logs the error (`biz storage tile (listing N): …`) and keeps
the tile with its label, "—" and "Storage used: figures unavailable right now".

Order (the session manager and the CEO, 2026-11-27): after the check release, b2-backups-2, r2-lanes-3 and the pipeline
release; pulled ahead of r2-lanes-3 only if #320 withdraws on the tile. Still to do before sending: Design review (1280, 390),
the FAQ entry, the IT Manager lane's Work ticket in its migration (seeded once, by `what`), release.json.
Built on Sync: 2026-11-27 02:50 UTC.
