# Traversence Vault extension (Chrome and Edge)

Stage 1 of decisions/0156 §9: for staff, loaded in developer mode. It fills Vault logins only on their exact site
(scheme, host and port), offers to save or update a login after signing in, and holds the vault key in the browser's
memory only (`chrome.storage.session`) for up to 12 hours, 30 minutes unused, until the browser closes, or until the
vault is locked.

- **Install:** unzip, open `chrome://extensions` (Edge: `edge://extensions`), turn on Developer mode, Load unpacked, pick
  the `vault` folder. Its ID is fixed by `key` in `manifest.json`: `leflbllgieloanpllabjmbjimemcjicj` (the Vault page and
  `/api/vault-ext.php` expect it; `VAULT_EXTENSION_ID` in `.env` overrides both, e.g. for a Web Store ID later).
- **Connect:** System Configuration → Vault → Browser extension → Connect this browser's extension.
- **Files:** `background.js` (the key, opening sealed items, the API with its `X-Vault-Token`), `content.js` (the list
  under a sign-in box and the save offer, in closed shadow roots), `popup.*` (status, Fill for this tab, Disconnect).
- The site side: `app/lib/Vault.php` (`ext*`), `website/api/vault-ext.php` (outside `/admin/`, which opens only for a signed-in
  staff login; the extension uses only its token), `website/js/tv-vault.js` (`extOn`). The emailed lock link is
  `website/api/vault-lock.php`.
- Bump `version` in `manifest.json` with each change, and send the new zip.
