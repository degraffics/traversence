-- E.1, after St. Johns (Data & Content for the PM (8), 2026-11-27, 0181 C7 and gate 3, 0131 "St. Johns before live"):
-- the guide of Round Valley (Springerville & Eagar) (cluster:2) is written again from its research brief. Today it's a draft scoring
-- about 50 (Claude's confidence 62, no research brief, 12 checks), and every section is written, so a plain redraft adds
-- nothing (Guides.php:1097). The route the live code already has: draft_info.renew, the same field the guide check sets
-- (Guides.php:709), makes the guide job write Claude's own sections again with a fresh research brief (Guides.php:802
-- and :1088). A person's sections stay. It runs within the AI cap like any renewal. Data only, safe to run twice: only a
-- draft, never one marked kept, never one already waiting to be renewed. Nothing else changes. Appended as PHP's
-- json_encode writes it (no spaces), since the guide job finds renewals by LIKE '%"renew":[%' (Guides.php:802).
UPDATE place_guides
   SET draft_info = CONCAT(LEFT(TRIM(draft_info), CHAR_LENGTH(TRIM(draft_info)) - 1), ',"renew":["0181 gate 3: written again from its research brief (E.1, after St. Johns, the PM''s ticket, 2026-11-27)"]}')
 WHERE place_key = 'cluster:2' AND status = 'draft' AND draft_status = 'done'
   AND JSON_VALID(draft_info) AND TRIM(draft_info) LIKE '{%}' AND TRIM(draft_info) <> '{}'
   AND draft_info NOT LIKE '%"kept"%' AND draft_info NOT LIKE '%"renew"%';
