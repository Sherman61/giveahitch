START TRANSACTION;

-- Ensure ride_ratings uses clear role naming so it is obvious who rated whom.
ALTER TABLE ride_ratings
  CHANGE COLUMN role rater_role ENUM('driver','passenger') NOT NULL AFTER rated_user_id;

-- Track the role of the person who received the rating for clarity in reports.
ALTER TABLE ride_ratings
  ADD COLUMN rated_role ENUM('driver','passenger') DEFAULT 'driver' NOT NULL AFTER rater_role;

-- Backfill the new rated_role column based on the existing rater role values.
UPDATE ride_ratings
  SET rated_role = CASE WHEN rater_role = 'driver' THEN 'passenger' ELSE 'driver' END
  WHERE rated_role IS NULL OR rated_role = '';

-- Import any legacy feedback rows into ride_ratings so all comments live in one table.
INSERT INTO ride_ratings (
    ride_id,
    match_id,
    rater_user_id,
    rated_user_id,
    rater_role,
    rated_role,
    stars,
    comment,
    created_at
)
SELECT
    rm.ride_id,
    f.ride_match_id,
    f.rater_user_id,
    f.ratee_user_id,
    CASE WHEN f.role = 'driver' THEN 'passenger' ELSE 'driver' END AS rater_role,
    f.role AS rated_role,
    f.rating,
    NULLIF(f.comment, ''),
    f.created_at
FROM feedback AS f
JOIN ride_matches AS rm ON rm.id = f.ride_match_id
LEFT JOIN ride_ratings AS existing
       ON existing.match_id = f.ride_match_id
      AND existing.rater_user_id = f.rater_user_id
WHERE existing.id IS NULL;

-- The legacy feedback table is no longer needed after migration.
DROP TABLE IF EXISTS feedback;

COMMIT;
