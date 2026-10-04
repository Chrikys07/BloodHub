-- Laudos eletrônicos e rastreabilidade de equipamentos. Idempotente para MySQL/MariaDB atuais.
ALTER TABLE users
 ADD COLUMN IF NOT EXISTS professional_name VARCHAR(180) NULL AFTER name,
 ADD COLUMN IF NOT EXISTS professional_council VARCHAR(40) NULL AFTER professional_name,
 ADD COLUMN IF NOT EXISTS professional_registration VARCHAR(80) NULL AFTER professional_council;

ALTER TABLE tests
 ADD COLUMN IF NOT EXISTS method_name VARCHAR(180) NULL AFTER unit,
 ADD COLUMN IF NOT EXISTS equipment_required TINYINT(1) NOT NULL DEFAULT 0 AFTER method_name;

ALTER TABLE clients
 ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL AFTER document,
 ADD COLUMN IF NOT EXISTS district VARCHAR(120) NULL AFTER address,
 ADD COLUMN IF NOT EXISTS city VARCHAR(120) NULL AFTER district,
 ADD COLUMN IF NOT EXISTS state CHAR(2) NULL AFTER city,
 ADD COLUMN IF NOT EXISTS postal_code VARCHAR(20) NULL AFTER state,
 ADD COLUMN IF NOT EXISTS contact_name VARCHAR(180) NULL AFTER postal_code,
 ADD COLUMN IF NOT EXISTS phone_extension VARCHAR(30) NULL AFTER phone;

ALTER TABLE units
 ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL AFTER code,
 ADD COLUMN IF NOT EXISTS district VARCHAR(120) NULL AFTER address,
 ADD COLUMN IF NOT EXISTS city VARCHAR(120) NULL AFTER district,
 ADD COLUMN IF NOT EXISTS state CHAR(2) NULL AFTER city,
 ADD COLUMN IF NOT EXISTS postal_code VARCHAR(20) NULL AFTER state,
 ADD COLUMN IF NOT EXISTS contact_name VARCHAR(180) NULL AFTER postal_code,
 ADD COLUMN IF NOT EXISTS phone VARCHAR(40) NULL AFTER email,
 ADD COLUMN IF NOT EXISTS phone_extension VARCHAR(30) NULL AFTER phone;

CREATE TABLE IF NOT EXISTS laboratory_equipment (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(80) NOT NULL, name VARCHAR(180) NOT NULL,
 manufacturer VARCHAR(180) NOT NULL, model VARCHAR(180) NOT NULL, serial_number VARCHAR(120) NULL,
 unit_id BIGINT UNSIGNED NULL, active TINYINT(1) NOT NULL DEFAULT 1,
 effective_from DATE NOT NULL, effective_to DATE NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uk_laboratory_equipment_code(code), INDEX idx_equipment_active(active,effective_from,effective_to),
 CONSTRAINT fk_laboratory_equipment_unit FOREIGN KEY(unit_id) REFERENCES units(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_equipment_assignments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, test_id BIGINT UNSIGNED NOT NULL, equipment_id BIGINT UNSIGNED NOT NULL,
 blood_component_id BIGINT UNSIGNED NULL, effective_from DATE NOT NULL, effective_to DATE NULL, active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_test_equipment_test FOREIGN KEY(test_id) REFERENCES tests(id) ON DELETE RESTRICT,
 CONSTRAINT fk_test_equipment_equipment FOREIGN KEY(equipment_id) REFERENCES laboratory_equipment(id) ON DELETE RESTRICT,
 CONSTRAINT fk_test_equipment_component FOREIGN KEY(blood_component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
 INDEX idx_test_equipment_lookup(test_id,blood_component_id,active,effective_from,effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_result_equipment (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, test_result_id BIGINT UNSIGNED NOT NULL, equipment_id BIGINT UNSIGNED NOT NULL,
 equipment_name_snapshot VARCHAR(180) NOT NULL, manufacturer_snapshot VARCHAR(180) NOT NULL,
 model_snapshot VARCHAR(180) NOT NULL, serial_snapshot VARCHAR(120) NULL, method_snapshot VARCHAR(180) NULL,
 used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uk_test_result_equipment(test_result_id),
 CONSTRAINT fk_result_equipment_result FOREIGN KEY(test_result_id) REFERENCES test_results(id) ON DELETE RESTRICT,
 CONSTRAINT fk_result_equipment_equipment FOREIGN KEY(equipment_id) REFERENCES laboratory_equipment(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS laboratory_reports (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, report_group_id CHAR(36) NOT NULL, previous_report_id BIGINT UNSIGNED NULL,
 sample_id BIGINT UNSIGNED NOT NULL, client_id BIGINT UNSIGNED NOT NULL, unit_id BIGINT UNSIGNED NULL,
 status ENUM('PENDING_RELEASE','RELEASED','REVISED','CANCELLED') NOT NULL DEFAULT 'PENDING_RELEASE', version INT UNSIGNED NOT NULL DEFAULT 1,
 released_by BIGINT UNSIGNED NULL, released_at DATETIME NULL, release_user_name_snapshot VARCHAR(180) NULL,
 professional_registration_snapshot VARCHAR(160) NULL, recipient_snapshot_json JSON NULL, sample_snapshot_json JSON NULL,
 results_snapshot_json JSON NULL, notes_snapshot TEXT NULL, pdf_path VARCHAR(500) NULL, source_fingerprint CHAR(64) NULL,
 revision_reason TEXT NULL, cancelled_by BIGINT UNSIGNED NULL, cancelled_at DATETIME NULL, cancellation_reason TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uk_laboratory_report_version(report_group_id,version), INDEX idx_report_sample(sample_id,status),
 INDEX idx_report_client(client_id,status,released_at),
 CONSTRAINT fk_report_previous FOREIGN KEY(previous_report_id) REFERENCES laboratory_reports(id) ON DELETE RESTRICT,
 CONSTRAINT fk_report_sample FOREIGN KEY(sample_id) REFERENCES samples(id) ON DELETE RESTRICT,
 CONSTRAINT fk_report_client FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE RESTRICT,
 CONSTRAINT fk_report_unit FOREIGN KEY(unit_id) REFERENCES units(id) ON DELETE RESTRICT,
 CONSTRAINT fk_report_releaser FOREIGN KEY(released_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_report_canceller FOREIGN KEY(cancelled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
 setting_key VARCHAR(120) PRIMARY KEY, setting_value TEXT NULL, updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_system_setting_user FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions(permission_key,name,module,status) VALUES
 ('reports.release.view','Visualizar laudos','reports','active'),
 ('reports.release.manage','Liberar laudos','reports','active'),
 ('reports.release.revise','Retificar laudos','reports','active'),
 ('reports.release.cancel','Cancelar laudos','reports','active'),
 ('laboratory_equipment.manage','Gerenciar equipamentos laboratoriais','admin','active')
ON DUPLICATE KEY UPDATE name=VALUES(name),module=VALUES(module),status='active';

INSERT IGNORE INTO role_permissions(role_id,permission_id)
 SELECT r.id,p.id FROM roles r JOIN permissions p ON p.permission_key IN
 ('reports.release.view','reports.release.manage','reports.release.revise','reports.release.cancel','laboratory_equipment.manage')
 WHERE r.slug IN ('administrador','lcqh');
INSERT IGNORE INTO role_permissions(role_id,permission_id)
 SELECT r.id,p.id FROM roles r JOIN permissions p ON p.permission_key='reports.release.view'
 WHERE r.slug IN ('gestao','processamento','agencia-transfusional');
