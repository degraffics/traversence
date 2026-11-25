-- The stage of each open task already in the Work queue (Piece 2, handoffs/piece2-team.md §11 P2-1): a release named →
-- Implementation; someone has it and its goal is set → Building; the rest → Planning. Done and withdrawn tasks keep no stage.
-- Only rows with no stage yet: safe to run twice. No CREATE here (0162's note: a file whose tables all exist is cleared unrun).
UPDATE control_gauge_tasks SET ticket = 'work' WHERE ticket = '' OR ticket IS NULL;
UPDATE control_gauge_tasks SET stage = 'implementation', stage_at = UTC_TIMESTAMP() WHERE stage IS NULL AND kind = 'fix' AND bucket = 'task' AND undone_at IS NULL AND cleared_at IS NULL AND rel <> '';
UPDATE control_gauge_tasks SET stage = 'building', stage_at = UTC_TIMESTAMP() WHERE stage IS NULL AND kind = 'fix' AND bucket = 'task' AND undone_at IS NULL AND cleared_at IS NULL AND owner <> '' AND COALESCE(goal, '') <> '';
UPDATE control_gauge_tasks SET stage = 'planning', stage_at = UTC_TIMESTAMP() WHERE stage IS NULL AND kind = 'fix' AND bucket = 'task' AND undone_at IS NULL AND cleared_at IS NULL;
