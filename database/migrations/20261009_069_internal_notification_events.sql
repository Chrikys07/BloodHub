ALTER TABLE user_notifications ADD COLUMN IF NOT EXISTS priority ENUM('info','warning','critical') NOT NULL DEFAULT 'info' AFTER entity_id;
ALTER TABLE user_notifications MODIFY event_key VARCHAR(160) NOT NULL;

SET @has_event_key := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_notifications' AND INDEX_NAME='uk_user_notifications_event');
DELETE newer FROM user_notifications newer JOIN user_notifications older ON older.user_id=newer.user_id AND older.event_key=newer.event_key AND older.id<newer.id WHERE @has_event_key=0;
SET @add_event_key := IF(@has_event_key=0,'ALTER TABLE user_notifications ADD UNIQUE KEY uk_user_notifications_event(user_id,event_key)','SELECT 1');
PREPARE stmt FROM @add_event_key; EXECUTE stmt; DEALLOCATE PREPARE stmt;
