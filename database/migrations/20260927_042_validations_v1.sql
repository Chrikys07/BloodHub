-- Validacoes v1: protocolos, fases, plano e vinculo das amostras.
CREATE TABLE IF NOT EXISTS validations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 pv_number VARCHAR(80) NOT NULL, name VARCHAR(180) NOT NULL, description TEXT NULL,
 start_date DATE NOT NULL, expected_end_date DATE NULL, actual_end_date DATE NULL,
 status ENUM('planned','in_progress','completed','cancelled') NOT NULL DEFAULT 'planned',
 created_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_by BIGINT UNSIGNED NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uk_validations_pv (pv_number), INDEX idx_validations_status_dates(status,start_date),
 CONSTRAINT fk_validations_created_by FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_validations_updated_by FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS validation_phases (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, validation_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(180) NOT NULL, description TEXT NULL, sequence_order INT UNSIGNED NOT NULL DEFAULT 1,
 status ENUM('active','completed','cancelled','inactive') NOT NULL DEFAULT 'active',
 started_at DATETIME NULL, completed_at DATETIME NULL, created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uk_validation_phase_order(validation_id,sequence_order),
 CONSTRAINT fk_vphase_validation FOREIGN KEY(validation_id) REFERENCES validations(id) ON DELETE CASCADE,
 CONSTRAINT fk_vphase_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS validation_blood_components (
 validation_id BIGINT UNSIGNED NOT NULL, blood_component_id BIGINT UNSIGNED NOT NULL, phase_id BIGINT UNSIGNED NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uk_validation_component(validation_id,blood_component_id,phase_id),
 CONSTRAINT fk_vbc_validation FOREIGN KEY(validation_id) REFERENCES validations(id) ON DELETE CASCADE,
 CONSTRAINT fk_vbc_component FOREIGN KEY(blood_component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
 CONSTRAINT fk_vbc_phase FOREIGN KEY(phase_id) REFERENCES validation_phases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS validation_tests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, validation_id BIGINT UNSIGNED NOT NULL, phase_id BIGINT UNSIGNED NULL,
 blood_component_id BIGINT UNSIGNED NOT NULL, test_id BIGINT UNSIGNED NOT NULL,
 is_required TINYINT(1) NOT NULL DEFAULT 1, status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uk_validation_test(validation_id,phase_id,blood_component_id,test_id),
 CONSTRAINT fk_vtest_validation FOREIGN KEY(validation_id) REFERENCES validations(id) ON DELETE CASCADE,
 CONSTRAINT fk_vtest_phase FOREIGN KEY(phase_id) REFERENCES validation_phases(id) ON DELETE CASCADE,
 CONSTRAINT fk_vtest_component FOREIGN KEY(blood_component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
 CONSTRAINT fk_vtest_test FOREIGN KEY(test_id) REFERENCES tests(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sample_shipments ADD COLUMN IF NOT EXISTS validation_id BIGINT UNSIGNED NULL AFTER purpose,
 ADD COLUMN IF NOT EXISTS validation_phase_id BIGINT UNSIGNED NULL AFTER validation_id;
ALTER TABLE samples ADD COLUMN IF NOT EXISTS validation_id BIGINT UNSIGNED NULL AFTER purpose,
 ADD COLUMN IF NOT EXISTS validation_phase_id BIGINT UNSIGNED NULL AFTER validation_id;

SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE sample_shipments ADD CONSTRAINT fk_sh_validation FOREIGN KEY(validation_id) REFERENCES validations(id) ON DELETE RESTRICT','SELECT 1') FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sample_shipments' AND CONSTRAINT_NAME='fk_sh_validation'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE sample_shipments ADD CONSTRAINT fk_sh_validation_phase FOREIGN KEY(validation_phase_id) REFERENCES validation_phases(id) ON DELETE RESTRICT','SELECT 1') FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sample_shipments' AND CONSTRAINT_NAME='fk_sh_validation_phase'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE samples ADD CONSTRAINT fk_samples_validation FOREIGN KEY(validation_id) REFERENCES validations(id) ON DELETE RESTRICT','SELECT 1') FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='samples' AND CONSTRAINT_NAME='fk_samples_validation'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE samples ADD CONSTRAINT fk_samples_validation_phase FOREIGN KEY(validation_phase_id) REFERENCES validation_phases(id) ON DELETE RESTRICT','SELECT 1') FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='samples' AND CONSTRAINT_NAME='fk_samples_validation_phase'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO permissions(permission_key,name,module,status) VALUES
('validations.view','Visualizar validacoes','validations','active'),('validations.create','Criar validacoes','validations','active'),
('validations.edit','Editar validacoes','validations','active'),('validations.configure_tests','Configurar plano de testes','validations','active'),
('validations.enter_results','Registrar resultados de validacao','validations','active'),('validations.complete','Concluir validacoes','validations','active')
ON DUPLICATE KEY UPDATE name=VALUES(name),module=VALUES(module),status='active';
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.slug IN('administrator','admin') AND p.module='validations';
