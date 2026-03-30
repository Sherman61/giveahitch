-- Migration based on the current production-style dump in `glitchahitchva.sql`.
-- That schema already includes the newer ride_ratings/users/messaging/notifications columns.
-- This file only adds the new tables that are still missing there.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS score_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  points_delta INT NOT NULL,
  reason_key VARCHAR(64) NOT NULL,
  reason_label VARCHAR(255) NOT NULL,
  details VARCHAR(1000) DEFAULT NULL,
  related_ride_id BIGINT UNSIGNED DEFAULT NULL,
  related_match_id BIGINT UNSIGNED DEFAULT NULL,
  related_rating_id BIGINT UNSIGNED DEFAULT NULL,
  actor_user_id BIGINT UNSIGNED DEFAULT NULL,
  metadata JSON DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_score_events_user_created (user_id, created_at),
  KEY idx_score_events_reason (reason_key),
  KEY idx_score_events_ride (related_ride_id),
  KEY idx_score_events_match (related_match_id),
  KEY idx_score_events_rating (related_rating_id),
  CONSTRAINT fk_score_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_score_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_score_events_ride FOREIGN KEY (related_ride_id) REFERENCES rides(id) ON DELETE SET NULL,
  CONSTRAINT fk_score_events_match FOREIGN KEY (related_match_id) REFERENCES ride_matches(id) ON DELETE SET NULL,
  CONSTRAINT fk_score_events_rating FOREIGN KEY (related_rating_id) REFERENCES ride_ratings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ride_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ride_id BIGINT UNSIGNED NOT NULL,
  reporter_user_id BIGINT UNSIGNED NOT NULL,
  reported_user_id BIGINT UNSIGNED DEFAULT NULL,
  reason_key VARCHAR(64) NOT NULL,
  details VARCHAR(1000) DEFAULT NULL,
  status ENUM('open','reviewing','closed','dismissed') NOT NULL DEFAULT 'open',
  admin_notes TEXT DEFAULT NULL,
  reviewed_by_user_id BIGINT UNSIGNED DEFAULT NULL,
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_ride_reports_ride (ride_id),
  KEY idx_ride_reports_reporter (reporter_user_id),
  KEY idx_ride_reports_status (status, created_at),
  KEY idx_ride_reports_reported_user (reported_user_id),
  CONSTRAINT fk_ride_reports_ride FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE CASCADE,
  CONSTRAINT fk_ride_reports_reporter FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ride_reports_reported_user FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ride_reports_reviewed_by FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_key VARCHAR(64) NOT NULL,
  severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
  actor_user_id BIGINT UNSIGNED DEFAULT NULL,
  target_user_id BIGINT UNSIGNED DEFAULT NULL,
  ip_address VARCHAR(64) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  details VARCHAR(1000) DEFAULT NULL,
  metadata JSON DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_security_events_created (created_at),
  KEY idx_security_events_key_created (event_key, created_at),
  KEY idx_security_events_severity_created (severity, created_at),
  KEY idx_security_events_actor (actor_user_id),
  KEY idx_security_events_target (target_user_id),
  CONSTRAINT fk_security_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_security_events_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

CREATE OR REPLACE VIEW rides_developer_view AS
SELECT
  r.id,
  r.user_id AS owner_user_id,
  r.type AS posting_type,
  CASE
    WHEN r.type = 'offer' THEN 'driver'
    WHEN r.type = 'request' THEN 'passenger'
    ELSE NULL
  END AS owner_role,
  r.from_text AS route_from_text,
  r.to_text AS route_to_text,
  r.ride_datetime AS trip_start_at,
  r.ride_end_datetime AS trip_end_at,
  CASE
    WHEN r.status = 'inprogress' THEN 'in_progress'
    ELSE r.status
  END AS ride_status,
  r.confirmed_match_id AS active_match_id,
  r.created_at,
  r.updated_at,
  r.deleted
FROM rides AS r;

CREATE OR REPLACE VIEW ride_matches_developer_view AS
SELECT
  rm.id AS match_id,
  rm.ride_id,
  r.user_id AS owner_user_id,
  r.type AS posting_type,
  CASE
    WHEN r.type = 'offer' THEN 'driver'
    WHEN r.type = 'request' THEN 'passenger'
    ELSE NULL
  END AS owner_role,
  CASE
    WHEN r.type = 'offer' THEN rm.passenger_user_id
    WHEN r.type = 'request' THEN rm.driver_user_id
    ELSE NULL
  END AS joiner_user_id,
  CASE
    WHEN r.type = 'offer' THEN 'passenger'
    WHEN r.type = 'request' THEN 'driver'
    ELSE NULL
  END AS joiner_role,
  rm.driver_user_id,
  rm.passenger_user_id,
  CASE
    WHEN rm.status = 'inprogress' THEN 'in_progress'
    ELSE rm.status
  END AS match_status,
  rm.confirmed_at,
  rm.created_at,
  rm.updated_at
FROM ride_matches AS rm
JOIN rides AS r ON r.id = rm.ride_id;
