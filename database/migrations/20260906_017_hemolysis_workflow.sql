-- Fluxo completo e rastreável do Grau de Hemólise.
ALTER TABLE test_result_parameters
    ADD COLUMN IF NOT EXISTS text_value VARCHAR(100) NULL AFTER numeric_value;

ALTER TABLE blood_components ADD COLUMN IF NOT EXISTS hemolysis_hematocrit_test_id BIGINT UNSIGNED NULL AFTER density;
SET @fk_hct_exists=(SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='blood_components' AND CONSTRAINT_NAME='fk_bc_hemolysis_hct_test');
SET @fk_hct_sql=IF(@fk_hct_exists=0,'ALTER TABLE blood_components ADD CONSTRAINT fk_bc_hemolysis_hct_test FOREIGN KEY (hemolysis_hematocrit_test_id) REFERENCES tests(id) ON DELETE SET NULL','SELECT 1');
PREPARE stmt FROM @fk_hct_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
UPDATE blood_components SET hemolysis_hematocrit_test_id=(SELECT id FROM tests WHERE code='HEMATOCRIT' LIMIT 1) WHERE hemolysis_hematocrit_test_id IS NULL;

CREATE TABLE IF NOT EXISTS hemolysis_imports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_filename VARCHAR(255) NOT NULL,
    file_hash CHAR(64) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    valid_rows INT UNSIGNED NOT NULL DEFAULT 0,
    matched_rows INT UNSIGNED NOT NULL DEFAULT 0,
    unmatched_rows INT UNSIGNED NOT NULL DEFAULT 0,
    duplicate_rows INT UNSIGNED NOT NULL DEFAULT 0,
    ignored_rows INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('previewed','applied','failed') NOT NULL DEFAULT 'previewed',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    applied_at DATETIME NULL,
    CONSTRAINT fk_hemolysis_import_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    KEY ix_hemolysis_import_hash (file_hash),
    KEY ix_hemolysis_import_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions(permission_key,name,module,status) VALUES
('quality_results.hemolysis_replace','Substituir resultado não concluído de Grau de Hemólise','quality_results','active')
ON DUPLICATE KEY UPDATE name=VALUES(name),module=VALUES(module),status='active';
