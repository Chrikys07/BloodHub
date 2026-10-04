-- Metadados incrementais do envio e da decisao de recebimento central.
ALTER TABLE samples
    ADD COLUMN IF NOT EXISTS sent_at DATETIME NULL AFTER registered_at,
    ADD COLUMN IF NOT EXISTS sent_by BIGINT UNSIGNED NULL AFTER sent_at,
    ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(500) NULL AFTER received_at,
    ADD COLUMN IF NOT EXISTS rejected_at DATETIME NULL AFTER rejection_reason,
    ADD COLUMN IF NOT EXISTS rejected_by BIGINT UNSIGNED NULL AFTER rejected_at;

SET @has_sent_by_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='samples' AND CONSTRAINT_NAME='fk_samples_sent_by');
SET @sql := IF(@has_sent_by_fk=0, 'ALTER TABLE samples ADD CONSTRAINT fk_samples_sent_by FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_rejected_by_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='samples' AND CONSTRAINT_NAME='fk_samples_rejected_by');
SET @sql := IF(@has_rejected_by_fk=0, 'ALTER TABLE samples ADD CONSTRAINT fk_samples_rejected_by FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

