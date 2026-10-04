-- Primeira etapa dos resultados de Controle de Qualidade.
ALTER TABLE samples
    ADD COLUMN IF NOT EXISTS lcqh_code VARCHAR(80) NULL AFTER sample_code,
    ADD COLUMN IF NOT EXISTS preservative_id BIGINT UNSIGNED NULL AFTER bag_brand_id;

CREATE TABLE IF NOT EXISTS preservatives (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_preservatives_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fk_preservative_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='samples' AND CONSTRAINT_NAME='fk_samples_preservative');
SET @fk_preservative_sql = IF(@fk_preservative_exists=0, 'ALTER TABLE samples ADD CONSTRAINT fk_samples_preservative FOREIGN KEY (preservative_id) REFERENCES preservatives(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @fk_preservative_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE test_blood_components
    ADD COLUMN IF NOT EXISTS is_required TINYINT(1) NOT NULL DEFAULT 1 AFTER blood_component_id;

CREATE TABLE IF NOT EXISTS sample_weight_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sample_id BIGINT UNSIGNED NOT NULL,
    gross_weight DECIMAL(10,3) NOT NULL,
    bag_brand_tare_id BIGINT UNSIGNED NOT NULL,
    tare_weight_used DECIMAL(10,3) NOT NULL,
    net_weight DECIMAL(10,3) NOT NULL,
    recorded_by BIGINT UNSIGNED NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_swr_sample FOREIGN KEY (sample_id) REFERENCES samples(id) ON DELETE CASCADE,
    CONSTRAINT fk_swr_tare FOREIGN KEY (bag_brand_tare_id) REFERENCES bag_brand_tares(id) ON DELETE RESTRICT,
    CONSTRAINT fk_swr_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uk_swr_sample (sample_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_result_parameters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    test_result_id BIGINT UNSIGNED NOT NULL,
    parameter_code VARCHAR(80) NOT NULL,
    parameter_name VARCHAR(180) NOT NULL,
    numeric_value DECIMAL(20,8) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_trp_result FOREIGN KEY (test_result_id) REFERENCES test_results(id) ON DELETE CASCADE,
    UNIQUE KEY uk_trp_result_code (test_result_id, parameter_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Consolida códigos estáveis sem depender deles posteriormente por nome.
UPDATE tests SET code='HEMATOCRIT' WHERE LOWER(name) IN ('hematócrito','hematocrito') AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM tests WHERE code='HEMATOCRIT') stable WHERE stable.id<>tests.id);
UPDATE tests SET code='HEMOGLOBIN' WHERE LOWER(name)='hemoglobina' AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM tests WHERE code='HEMOGLOBIN') stable WHERE stable.id<>tests.id);
UPDATE tests SET code='HEMOLYSIS_DEGREE' WHERE LOWER(name) IN ('grau de hemólise','grau de hemolise') AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM tests WHERE code='HEMOLYSIS_DEGREE') stable WHERE stable.id<>tests.id);
UPDATE tests SET code='FREE_HEMOGLOBIN' WHERE LOWER(name)='hemoglobina livre' AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM tests WHERE code='FREE_HEMOGLOBIN') stable WHERE stable.id<>tests.id);

INSERT INTO tests(code,name,result_type,unit,allows_ad_hoc,status)
SELECT 'HEMATOCRIT','Hematócrito','numeric','%',0,'active' WHERE NOT EXISTS(SELECT 1 FROM tests WHERE code='HEMATOCRIT');
INSERT INTO tests(code,name,result_type,unit,allows_ad_hoc,status)
SELECT 'HEMOGLOBIN','Hemoglobina','numeric','g/dL',0,'active' WHERE NOT EXISTS(SELECT 1 FROM tests WHERE code='HEMOGLOBIN');
INSERT INTO tests(code,name,result_type,unit,allows_ad_hoc,status)
SELECT 'HEMOLYSIS_DEGREE','Grau de Hemólise','numeric','%',0,'active' WHERE NOT EXISTS(SELECT 1 FROM tests WHERE code='HEMOLYSIS_DEGREE');
INSERT INTO tests(code,name,result_type,unit,allows_ad_hoc,status)
SELECT 'FREE_HEMOGLOBIN','Hemoglobina Livre','numeric',NULL,0,'active' WHERE NOT EXISTS(SELECT 1 FROM tests WHERE code='FREE_HEMOGLOBIN');

INSERT IGNORE INTO test_blood_components(test_id,blood_component_id,is_required)
SELECT t.id,bc.id,IF(t.code='FREE_HEMOGLOBIN',0,1) FROM tests t CROSS JOIN blood_components bc
WHERE t.code IN ('HEMATOCRIT','HEMOGLOBIN','HEMOLYSIS_DEGREE','FREE_HEMOGLOBIN') AND bc.code='CH';

INSERT INTO permissions(permission_key,name,module,status) VALUES
('quality_results.view','Visualizar resultados de Controle de Qualidade','quality_results','active'),
('quality_results.edit','Editar resultados de Controle de Qualidade','quality_results','active'),
('quality_results.complete','Concluir análises de Controle de Qualidade','quality_results','active'),
('quality_results.hemolysis','Acessar ensaio de Grau de Hemólise','quality_results','active'),
('admin.preservatives.manage','Gerenciar preservantes','admin','active')
ON DUPLICATE KEY UPDATE name=VALUES(name),module=VALUES(module),status='active';
