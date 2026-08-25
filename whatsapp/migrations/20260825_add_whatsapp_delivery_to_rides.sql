ALTER TABLE rides
  ADD COLUMN whatsapp_sent TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN whatsapp_sent_at DATETIME NULL,
  ADD COLUMN whatsapp_message_id VARCHAR(128) NULL,
  ADD INDEX idx_rides_whatsapp_pending (whatsapp_sent, status, deleted);
