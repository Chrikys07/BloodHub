-- Pools rastreáveis de Fator VIII:C para PFC e PFC24.
INSERT INTO blood_components(code,name,density,transport_temperature_min,transport_temperature_max,description,status)
SELECT 'PFC24','Plasma Fresco Congelado de 24 horas',density,transport_temperature_min,transport_temperature_max,'Configuração inicial derivada do PFC; revise densidade, transporte e especificações antes do uso.','active'
FROM blood_components WHERE code='PFC'
  AND NOT EXISTS(SELECT 1 FROM blood_components WHERE code='PFC24') LIMIT 1;

INSERT INTO tests(code,name,result_type,unit,allows_ad_hoc,status)
SELECT 'FACTOR_VIII','Fator VIII:C','numeric','UI/mL',0,'active'
WHERE NOT EXISTS(SELECT 1 FROM tests WHERE code='FACTOR_VIII');
INSERT INTO tests(code,name,result_type,unit,allows_ad_hoc,status)
SELECT 'VOLUME','Volume','numeric','mL',0,'active'
WHERE NOT EXISTS(SELECT 1 FROM tests WHERE code='VOLUME');

INSERT IGNORE INTO test_blood_components(test_id,blood_component_id,is_required)
SELECT t.id,bc.id,1 FROM tests t CROSS JOIN blood_components bc
WHERE t.code IN ('VOLUME','FACTOR_VIII') AND bc.code IN ('PFC','PFC24');

-- PFC24 inicia com as regras versionadas equivalentes às de PFC, sem hardcode numérico.
-- Depois disso as duas famílias podem ser versionadas de forma independente pela administração.
INSERT INTO blood_component_test_specifications(blood_component_id,test_id,rule_type,min_value,max_value,expected_text,unit,preservative_id,condition_type,condition_value,effective_from,effective_to,source_name,source_reference,notes,sampling_requirement_notes,active,created_by,updated_by,version_number)
SELECT target.id,sp.test_id,sp.rule_type,sp.min_value,sp.max_value,sp.expected_text,sp.unit,sp.preservative_id,sp.condition_type,sp.condition_value,sp.effective_from,sp.effective_to,sp.source_name,sp.source_reference,CONCAT(COALESCE(sp.notes,''),' | Configuração inicial derivada de PFC para PFC24.'),sp.sampling_requirement_notes,sp.active,sp.created_by,sp.updated_by,sp.version_number
FROM blood_component_test_specifications sp
JOIN blood_components source ON source.id=sp.blood_component_id AND source.code='PFC'
JOIN tests t ON t.id=sp.test_id AND t.code IN ('VOLUME','FACTOR_VIII')
JOIN blood_components target ON target.code='PFC24'
WHERE NOT EXISTS(SELECT 1 FROM blood_component_test_specifications existing WHERE existing.blood_component_id=target.id AND existing.test_id=sp.test_id AND existing.version_number=sp.version_number AND existing.effective_from<=>sp.effective_from AND existing.condition_type<=>sp.condition_type AND existing.condition_value<=>sp.condition_value AND existing.preservative_id<=>sp.preservative_id);

CREATE TABLE IF NOT EXISTS factor_viii_pools (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(80) NOT NULL,
 blood_component_id BIGINT UNSIGNED NOT NULL,
 origin_unit_id BIGINT UNSIGNED NOT NULL,
 status ENUM('open','completed_conforming','completed_nonconforming','cancelled') NOT NULL DEFAULT 'open',
 result DECIMAL(20,8) NULL,
 result_unit VARCHAR(30) NOT NULL DEFAULT 'UI/mL',
 specification_id BIGINT UNSIGNED NULL,
 specification_snapshot JSON NULL,
 is_conforming TINYINT(1) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 result_saved_by BIGINT UNSIGNED NULL,
 result_saved_at DATETIME NULL,
 finalized_by BIGINT UNSIGNED NULL,
 finalized_at DATETIME NULL,
 cancelled_by BIGINT UNSIGNED NULL,
 cancelled_at DATETIME NULL,
 cancellation_reason VARCHAR(500) NULL,
 notes TEXT NULL,
 CONSTRAINT fk_fviii_pool_component FOREIGN KEY(blood_component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
 CONSTRAINT fk_fviii_pool_origin FOREIGN KEY(origin_unit_id) REFERENCES units(id) ON DELETE RESTRICT,
 CONSTRAINT fk_fviii_pool_spec FOREIGN KEY(specification_id) REFERENCES blood_component_test_specifications(id) ON DELETE SET NULL,
 CONSTRAINT fk_fviii_pool_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_fviii_pool_result_user FOREIGN KEY(result_saved_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_fviii_pool_finalizer FOREIGN KEY(finalized_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_fviii_pool_canceller FOREIGN KEY(cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uk_fviii_pool_code(code),
 INDEX idx_fviii_pool_status(status),
 INDEX idx_fviii_pool_component(blood_component_id),
 INDEX idx_fviii_pool_origin(origin_unit_id),
 INDEX idx_fviii_pool_group(blood_component_id,origin_unit_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS factor_viii_pool_samples (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 pool_id BIGINT UNSIGNED NOT NULL,
 sample_id BIGINT UNSIGNED NOT NULL,
 position TINYINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_fviii_member_pool FOREIGN KEY(pool_id) REFERENCES factor_viii_pools(id) ON DELETE RESTRICT,
 CONSTRAINT fk_fviii_member_sample FOREIGN KEY(sample_id) REFERENCES samples(id) ON DELETE RESTRICT,
 CONSTRAINT chk_fviii_member_position CHECK(position BETWEEN 1 AND 4),
 UNIQUE KEY uk_fviii_pool_position(pool_id,position),
 UNIQUE KEY uk_fviii_pool_sample(pool_id,sample_id),
 INDEX idx_fviii_member_sample(sample_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS factor_viii_sample_results (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sample_id BIGINT UNSIGNED NOT NULL,
 current_pool_id BIGINT UNSIGNED NULL,
 analysis_mode ENUM('pool','individual') NOT NULL DEFAULT 'pool',
 pool_result DECIMAL(20,8) NULL,
 pool_result_at DATETIME NULL,
 pool_result_by BIGINT UNSIGNED NULL,
 pool_specification_id BIGINT UNSIGNED NULL,
 pool_specification_snapshot JSON NULL,
 individual_required TINYINT(1) NOT NULL DEFAULT 0,
 individual_result DECIMAL(20,8) NULL,
 individual_result_at DATETIME NULL,
 individual_result_by BIGINT UNSIGNED NULL,
 individual_specification_id BIGINT UNSIGNED NULL,
 individual_specification_snapshot JSON NULL,
 effective_result DECIMAL(20,8) NULL,
 effective_source ENUM('pool','individual') NULL,
 test_result_id BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_fviii_sample_sample FOREIGN KEY(sample_id) REFERENCES samples(id) ON DELETE RESTRICT,
 CONSTRAINT fk_fviii_sample_pool FOREIGN KEY(current_pool_id) REFERENCES factor_viii_pools(id) ON DELETE SET NULL,
 CONSTRAINT fk_fviii_sample_pool_user FOREIGN KEY(pool_result_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_fviii_sample_individual_user FOREIGN KEY(individual_result_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_fviii_sample_pool_spec FOREIGN KEY(pool_specification_id) REFERENCES blood_component_test_specifications(id) ON DELETE SET NULL,
 CONSTRAINT fk_fviii_sample_individual_spec FOREIGN KEY(individual_specification_id) REFERENCES blood_component_test_specifications(id) ON DELETE SET NULL,
 CONSTRAINT fk_fviii_sample_test_result FOREIGN KEY(test_result_id) REFERENCES test_results(id) ON DELETE SET NULL,
 UNIQUE KEY uk_fviii_sample(sample_id),
 INDEX idx_fviii_sample_pool(current_pool_id),
 INDEX idx_fviii_sample_mode(analysis_mode,individual_required)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions(permission_key,name,module,status) VALUES
('quality_control.factor_viii_pool.view','Visualizar pools de Fator VIII','quality_results','active'),
('quality_control.factor_viii_pool.create','Criar pools de Fator VIII','quality_results','active'),
('quality_control.factor_viii_pool.result','Registrar resultados de pools de Fator VIII','quality_results','active'),
('quality_control.factor_viii_pool.cancel','Cancelar pools de Fator VIII','quality_results','active')
ON DUPLICATE KEY UPDATE name=VALUES(name),module=VALUES(module),status='active';
