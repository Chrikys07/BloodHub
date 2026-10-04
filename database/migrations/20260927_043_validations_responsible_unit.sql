-- Unidade responsavel das validacoes. NULL e mantido apenas para registros legados.
ALTER TABLE validations
    ADD COLUMN IF NOT EXISTS unit_id BIGINT UNSIGNED NULL AFTER description;

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE validations ADD CONSTRAINT fk_validations_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE RESTRICT',
    'SELECT 1') FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='validations' AND CONSTRAINT_NAME='fk_validations_unit');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE validations ADD INDEX idx_validations_unit_status (unit_id, status)',
    'SELECT 1') FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='validations' AND INDEX_NAME='idx_validations_unit_status');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
