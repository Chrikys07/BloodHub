-- Índice composto para as agregações do Dashboard Global.
SET @has_idx=(SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='samples' AND INDEX_NAME='idx_samples_dashboard');
SET @sql=IF(@has_idx=0,'ALTER TABLE samples ADD INDEX idx_samples_dashboard(purpose,blood_component_id,production_date,origin_unit_id,status)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
