CREATE TABLE IF NOT EXISTS administrative_corrections (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 correction_type ENUM('SAMPLE_IDENTIFICATION','SAMPLE_STATUS','LAB_RESULT','LAB_INPUT','BACTERIOLOGY_RESULT','POOL_RESULT','OTHER') NOT NULL,
 entity_type VARCHAR(80) NOT NULL,
 entity_id BIGINT UNSIGNED NOT NULL,
 sample_id BIGINT UNSIGNED NOT NULL,
 parent_correction_id BIGINT UNSIGNED NULL,
 field_name VARCHAR(120) NOT NULL,
 old_value TEXT NULL,
 new_value TEXT NULL,
 old_formatted_value TEXT NULL,
 new_formatted_value TEXT NULL,
 reason TEXT NOT NULL,
 metadata_json JSON NULL,
 corrected_by BIGINT UNSIGNED NULL,
 corrected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_admin_correction_sample FOREIGN KEY(sample_id) REFERENCES samples(id) ON DELETE RESTRICT,
 CONSTRAINT fk_admin_correction_parent FOREIGN KEY(parent_correction_id) REFERENCES administrative_corrections(id) ON DELETE RESTRICT,
 CONSTRAINT fk_admin_correction_user FOREIGN KEY(corrected_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_admin_correction_sample(sample_id,corrected_at,id),
 INDEX idx_admin_correction_entity(entity_type,entity_id),
 INDEX idx_admin_correction_type(correction_type,corrected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE samples
 ADD COLUMN IF NOT EXISTS cancelled_by BIGINT UNSIGNED NULL AFTER rejected_by,
 ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL AFTER cancelled_by,
 ADD COLUMN IF NOT EXISTS cancellation_reason VARCHAR(1000) NULL AFTER cancelled_at;

SET @has_fk=(SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='samples' AND CONSTRAINT_NAME='fk_samples_cancelled_by');
SET @sql=IF(@has_fk=0,'ALTER TABLE samples ADD CONSTRAINT fk_samples_cancelled_by FOREIGN KEY(cancelled_by) REFERENCES users(id) ON DELETE SET NULL','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO permissions(permission_key,name,module,status) VALUES
 ('admin_corrections.manage','Gerenciar correcoes administrativas','admin','active')
ON DUPLICATE KEY UPDATE name=VALUES(name),module=VALUES(module),status='active';

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.permission_key='admin_corrections.manage'
WHERE r.slug='administrador' OR LOWER(r.name)='administrador';
