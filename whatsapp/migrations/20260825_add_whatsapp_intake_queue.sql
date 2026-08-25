CREATE TABLE IF NOT EXISTS whatsapp_ride_intakes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  source_group_jid VARCHAR(128) NOT NULL,
  source_sender_jid VARCHAR(128) NOT NULL,
  source_message_id VARCHAR(128) NOT NULL,
  raw_text TEXT NOT NULL,
  intake_status ENUM('needs_details','awaiting_confirmation','confirmed','posted','failed','cancelled') NOT NULL DEFAULT 'needs_details',
  ride_type ENUM('offer','request') NULL,
  from_text VARCHAR(255) NULL,
  to_text VARCHAR(255) NULL,
  note VARCHAR(1000) NULL,
  phone VARCHAR(32) NULL,
  whatsapp VARCHAR(32) NULL,
  clarification_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_whatsapp_intake_message (source_message_id),
  KEY idx_whatsapp_intake_status (intake_status, updated_at)
);

CREATE TABLE whatsapp_bridge_settings (
  setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
INSERT INTO whatsapp_bridge_settings (setting_key, setting_value)
VALUES ('intake_mode', 'manual')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
