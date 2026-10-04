-- Classificacao gerencial explicita dos testes e metas versionadas do Dashboard.
ALTER TABLE tests
    ADD COLUMN IF NOT EXISTS is_final_result TINYINT(1) NOT NULL DEFAULT 1 AFTER allows_ad_hoc;

-- Inputs laboratoriais que existem apenas para produzir outro resultado final.
UPDATE tests SET is_final_result=0
WHERE code IN ('HEMOGLOBIN','FREE_HEMOGLOBIN','PLATELET_COUNT','LEUKOCYTE_COUNT');

-- Nomenclatura curta de exibicao; codigos e relacionamentos permanecem inalterados.
UPDATE tests SET name='Plaquetas/U' WHERE code='PLATELETS_PER_UNIT';
UPDATE tests SET name='Leucócitos/U' WHERE code='LEUKOCYTES_PER_UNIT';
UPDATE tests SET name='Hb/U' WHERE code='HEMOGLOBIN_PER_UNIT';
UPDATE tests SET name='Plaquetas/mL' WHERE code='PLATELETS_PER_ML';
UPDATE tests SET name='Leucócitos/mL' WHERE code='LEUKOCYTES_PER_ML';
UPDATE tests SET name='Hemácias/mL' WHERE code='REDBLOODCELLS_PER_ML';
UPDATE tests SET name='Fibrinogênio/U' WHERE code='FIBRINOGEN';

CREATE TABLE IF NOT EXISTS dashboard_conformity_targets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blood_component_id BIGINT UNSIGNED NOT NULL,
    test_id BIGINT UNSIGNED NOT NULL,
    minimum_percentage DECIMAL(5,2) NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_dashboard_target_component FOREIGN KEY(blood_component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
    CONSTRAINT fk_dashboard_target_test FOREIGN KEY(test_id) REFERENCES tests(id) ON DELETE RESTRICT,
    CONSTRAINT fk_dashboard_target_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT chk_dashboard_target_percentage CHECK(minimum_percentage BETWEEN 0 AND 100),
    UNIQUE KEY uk_dashboard_target_version(blood_component_id,test_id,effective_from),
    INDEX idx_dashboard_target_lookup(blood_component_id,test_id,active,effective_from,effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
