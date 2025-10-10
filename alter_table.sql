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

START TRANSACTION;

-- Add per-user contact privacy setting (1=match only, 2=logged-in, 3=public with active ride).
ALTER TABLE users
  ADD COLUMN contact_privacy TINYINT NOT NULL DEFAULT 1 AFTER whatsapp;

-- Ensure existing records default to the strictest option.
UPDATE users SET contact_privacy = 1 WHERE contact_privacy IS NULL;

COMMIT;

START TRANSACTION;

-- Allow members to control who can direct message them (1=everyone, 2=ride connections, 3=no one).
ALTER TABLE users
  ADD COLUMN message_privacy TINYINT NOT NULL DEFAULT 1 AFTER contact_privacy;

UPDATE users SET message_privacy = 1 WHERE message_privacy IS NULL;

-- Conversation metadata between two members (user_a_id < user_b_id enforced in code).
CREATE TABLE IF NOT EXISTS user_message_threads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_a_id BIGINT UNSIGNED NOT NULL,
  user_b_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_message_id BIGINT UNSIGNED DEFAULT NULL,
  last_message_at TIMESTAMP NULL DEFAULT NULL,
  user_a_unread INT NOT NULL DEFAULT 0,
  user_b_unread INT NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_message_pair (user_a_id, user_b_id),
  KEY idx_thread_last_message (last_message_at),
  KEY idx_thread_user_a (user_a_id),
  KEY idx_thread_user_b (user_b_id),
  CONSTRAINT fk_thread_user_a FOREIGN KEY (user_a_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_thread_user_b FOREIGN KEY (user_b_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual direct messages stored per thread.
CREATE TABLE IF NOT EXISTS user_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  thread_id BIGINT UNSIGNED NOT NULL,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP NULL DEFAULT NULL,
  KEY idx_message_thread (thread_id),
  KEY idx_message_sender (sender_user_id),
  KEY idx_message_created (created_at),
  CONSTRAINT fk_message_thread FOREIGN KEY (thread_id) REFERENCES user_message_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
