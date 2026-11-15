#!/usr/bin/env bash
# Public photos load (2026-11-15, after the stock door photos gave a 404): website/uploads/.htaccess must hand a photo that
# isn't on the server to media.php (decisions/0123, Store::uploadsRule). Daily from site-checks.yml.
#  1. A real stock photo from the home page loads: 200 and an image, after its redirects.
#  2. A real member photo (PHOTO_MEMBER, an /uploads/… address, when set) loads the same way.
#  3. For every kind in Store::KINDS (read from the mirror) and the old hero|journeys|profile names, a made-up address
#     reaches media.php (its own UTF-8 answer), not Apache's "Not Found" (iso-8859-1): the rule covers that kind.
# Exit 1 on any failure. SITE defaults to https://traversence.com; STORE is the mirror's Store.php.
set -u
SITE="${SITE:-https://traversence.com}"; STORE="${STORE:-mirror/sync-app/lib/Store.php}"; bad=0
loads() {   # $1 label, $2 address
  local r; r=$(curl -sL -o /dev/null -w '%{http_code} %{content_type}' --max-time 30 "$SITE$2")
  case "$r" in 200\ image/*) echo "ok   $1: $2 ($r)";; *) echo "::error::$1 doesn't load: $2 ($r)"; bad=1;; esac
}
stock=$(curl -s --max-time 30 "$SITE/" | grep -o '\(/uploads/\|/media\.php?p=\)system/[A-Za-z0-9_./-]*\.\(webp\|jpe\?g\|png\)' | head -1)
if [ -n "$stock" ]; then loads "Stock photo" "$stock"; else echo "::warning::No stock photo on the home page to check."; fi
if [ -n "${PHOTO_MEMBER:-}" ]; then loads "Member photo" "$PHOTO_MEMBER"; else echo "::notice::PHOTO_MEMBER isn't set: the member photo is checked by its kind's rule only."; fi
kinds=$(grep -o "KINDS = \[[^]]*\]" "$STORE" 2>/dev/null | grep -o "'[a-z]*'" | tr -d "'")
[ -n "$kinds" ] || kinds="members businesses guests orders system"
for k in $kinds; do probe+=("/uploads/$k/0123456789abcdef/check/photo-check.webp"); done
probe+=(/uploads/hero/2026/01/photo-check.webp /uploads/journeys/2026/01/photo-check.jpg /uploads/profile/2026/01/photo-check.png)
for u in "${probe[@]}"; do
  r=$(curl -s -o /dev/null -w '%{http_code} %{content_type}' --max-time 30 "$SITE$u")
  case "$r" in *iso-8859-1*|500*) echo "::error::The uploads rule misses $u ($r)"; bad=1;; *) echo "ok   rule: $u ($r)";; esac
done
exit $bad
