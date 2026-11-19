#!/usr/bin/env python3
"""Get Local R2, part 3: the PO Box rule everywhere a listing offers Directions (Jason: "why would m2 serv core have
directions to it when it is only a mailbox?"; the session manager's two edges). One rule, ListingActions::isMailbox():
the listing's page, the map's card and the search's peek leave Directions out and say "Mailing address only"; on the
map its pin is shown as approximate ("pin shows the town"); the owner's portal says "Add a street address to show
Directions". Replayable: build-r2c.py <root> with app/lib/ListingActions.php, app/lib/UniversalSearch.php,
website/js/map-view.js, website/directory/index.php (after r2b), app/business/portal.php."""
import sys, os
R = sys.argv[1]
def patch(rel, pairs):
    p = os.path.join(R, rel); s = open(p, encoding='utf-8').read()
    for a, b in pairs:
        assert s.count(a) == 1, (rel, a[:90], s.count(a)); s = s.replace(a, b)
    open(p, 'w', encoding='utf-8').write(s)
patch('app/lib/ListingActions.php', [
 ("        if (!$private) {\n            $lat = $row['latitude'] ?? null; $lon = $row['longitude'] ?? null;",
  "        if (!$private && !self::isMailbox((string) ($row['address'] ?? ''))) {   // a mailbox has no way to drive to it (2026-11-19)\n            $lat = $row['latitude'] ?? null; $lon = $row['longitude'] ?? null;"),
 ("    public const ACTIONS = [",
  "    /** A mailbox, not a street: P.O. Box, PO BOX, POB, Box 12, PMB, private mail box. The one rule for Directions (the\n"
  "     *  listing's page, the map's card, the peek; Get Local's cards use the same pattern in its script). */\n"
  "    public const MAILBOX = '/^\\s*(p\\.?\\s*o\\.?\\s*box|post\\s+office\\s+box|pob\\b|box\\s+\\d|pmb\\b|private\\s+mail\\s*box)/i';\n"
  "    public static function isMailbox(string $address): bool { return trim($address) !== '' && (bool) preg_match(self::MAILBOX, $address); }\n\n"
  "    public const ACTIONS = ["),
])
patch('app/lib/UniversalSearch.php', [
 ("                'private_location' => $private, 'approx' => !$private && self::approx($pdo, (int) $e['id']),",
  "                'private_location' => $private, 'mailing_only' => !$private && (require_once __DIR__ . '/ListingActions.php') && ListingActions::isMailbox((string) ($m['address'] ?? '')),\n"
  "                'approx' => !$private && ((require_once __DIR__ . '/ListingActions.php') && ListingActions::isMailbox((string) ($m['address'] ?? '')) || self::approx($pdo, (int) $e['id'])),"),
 ("                'directions' => !$private && $e['latitude'] !== null ? 'https://www.google.com/maps/dir/?api=1&destination='",
  "                'directions' => !$private && $e['latitude'] !== null && !ListingActions::isMailbox((string) ($m['address'] ?? '')) ? 'https://www.google.com/maps/dir/?api=1&destination='"),
])
patch('website/js/map-view.js', [
 ("        + (l.address ? '<p>' + esc(l.address) + '</p>' : l.private_location ?",
  "        + (l.address ? '<p>' + esc(l.address) + (l.mailing_only ? '<br><span class=\"m\">Mailing address only: the pin shows the town, not a place to drive to.</span>' : '') + '</p>' : l.private_location ?"),
 ("      if (l.approx) fixPin(card, p, l);", "      if (l.approx && !l.mailing_only) fixPin(card, p, l);   // a mailbox's pin stays the town's"),
])
patch('website/directory/index.php', [
 ("icon: L.divIcon({ className: 'dr-pin' + (l.approx ? ' ap' : ''),", "icon: L.divIcon({ className: 'dr-pin' + (l.approx || (l.address && !hasStreet(l) && !l.private_location) ? ' ap' : ''),"),
 ("          + (l.approx ? '<p><i>Approximate: the middle of its ZIP area.</i>",
  "          + (l.address && !hasStreet(l) && !l.private_location ? '<p><i>Mailing address only: the pin shows the town.</i></p>' : l.approx ? '<p><i>Approximate: the middle of its ZIP area.</i>"),
])
patch('app/business/portal.php', [
 ("    if ($have) $h .= '<div class=\"bo-prof\">",
  "    if (isset($m['address']) && is_string($m['address']) && (require_once __DIR__ . '/../lib/ListingActions.php') && ListingActions::isMailbox($m['address']))   // the owner's way forward (2026-11-19)\n"
  "        $h .= '<p data-tv-status=\"info\" data-tv-label=\"Mailing address only:\">Your address reads as a mailbox, so visitors see no Directions and your pin shows the town. <a class=\"underline\" href=\"/listing/businessportal.php?id=' . $listingId . '\">Add a street address</a> to show Directions.</p>';\n"
  "    if ($have) $h .= '<div class=\"bo-prof\">"),
])
print('built')
