-- W15 release 1 (Data & Content, 2026-11-27, decisions/0170 §2 and its 2026-11-27 note, 0181's W15 note, the CEO's Tier 4
-- review of W15, 2026-11-27): Winslow, a 1A town regions.md names, gets its own area under the Ancient Borderlands zone,
-- the zone of the Greater St. Johns Area (cluster-1a-st-johns). Today its ZIP 86047 is in no area, so search reads Winslow
-- as outside our areas ("We have no listings in Winslow" over its results) and offers no guide (gate 3, 0181 §1).
-- 0170 §2's cascade: an existing town first (none covers 86047), else a town in its zone. Named by 0042's slug formula.
-- The town (anchors) follows within the hour (Guides::syncTowns). Add-only, safe to run twice, its id named (MAX(id)+1:
-- staging's copied tables have AUTO_INCREMENT behind their rows). Undo: delete this one row while nothing points to it.
SET @g = (SELECT geo_hub_id FROM micro_clusters WHERE slug = 'cluster-1a-st-johns' LIMIT 1);
SET @n = (SELECT COALESCE(MAX(id), 0) + 1 FROM micro_clusters);
INSERT INTO micro_clusters (id, geo_hub_id, slug, name, primary_zip, associated_zips, center_lat, center_lon, source, confidence_score, created_at, updated_at)
  SELECT @n, @g, 'cluster-1a-winslow', 'Winslow', '86047', JSON_ARRAY('86047'), z.latitude, z.longitude, 'manual', 1.00, NOW(), NOW()
  FROM zip_coordinates z
  WHERE z.zip = '86047' AND @g IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM micro_clusters c WHERE c.slug = 'cluster-1a-winslow' OR c.primary_zip = '86047' OR JSON_CONTAINS(c.associated_zips, '"86047"'))
  LIMIT 1;
