-- Faturamento institucional PDLAB005 Rev.14: somente resultados finais.
CREATE TABLE IF NOT EXISTS billing_cost_centers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, cost_center_code VARCHAR(60) NOT NULL, name VARCHAR(180) NOT NULL,
 unit_id BIGINT UNSIGNED NULL, display_order INT NOT NULL DEFAULT 0, active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uk_billing_cost_center_code(cost_center_code), UNIQUE KEY uk_billing_cost_center_unit(unit_id),
 CONSTRAINT fk_billing_cost_center_unit FOREIGN KEY(unit_id) REFERENCES units(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_services (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, service_code VARCHAR(20) NOT NULL, name VARCHAR(180) NOT NULL,
 display_order INT NOT NULL DEFAULT 0, active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uk_billing_service_code(service_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_service_mappings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, billing_service_id BIGINT UNSIGNED NOT NULL, test_id BIGINT UNSIGNED NOT NULL,
 blood_component_id BIGINT UNSIGNED NULL, context_scope VARCHAR(255) NOT NULL DEFAULT 'quality_control', active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_billing_mapping_service FOREIGN KEY(billing_service_id) REFERENCES billing_services(id) ON DELETE CASCADE,
 CONSTRAINT fk_billing_mapping_test FOREIGN KEY(test_id) REFERENCES tests(id) ON DELETE RESTRICT,
 CONSTRAINT fk_billing_mapping_component FOREIGN KEY(blood_component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
 UNIQUE KEY uk_billing_mapping(billing_service_id,test_id,blood_component_id,context_scope), INDEX idx_billing_mapping_test(test_id,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_manual_entries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, year SMALLINT UNSIGNED NOT NULL, month TINYINT UNSIGNED NOT NULL,
 billing_cost_center_id BIGINT UNSIGNED NOT NULL, billing_service_id BIGINT UNSIGNED NOT NULL, quantity INT UNSIGNED NOT NULL,
 notes VARCHAR(1000) NULL, created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_billing_manual_center FOREIGN KEY(billing_cost_center_id) REFERENCES billing_cost_centers(id) ON DELETE RESTRICT,
 CONSTRAINT fk_billing_manual_service FOREIGN KEY(billing_service_id) REFERENCES billing_services(id) ON DELETE RESTRICT,
 CONSTRAINT fk_billing_manual_created FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_billing_manual_updated FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_billing_manual_period(year,month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_periods (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, year SMALLINT UNSIGNED NOT NULL, month TINYINT UNSIGNED NOT NULL,
 status ENUM('OPEN','REVIEWED','CLOSED','REOPENED') NOT NULL DEFAULT 'OPEN', snapshot_json LONGTEXT NULL, snapshot_version INT UNSIGNED NOT NULL DEFAULT 0,
 reviewed_by BIGINT UNSIGNED NULL, reviewed_at DATETIME NULL, closed_by BIGINT UNSIGNED NULL, closed_at DATETIME NULL,
 reopened_by BIGINT UNSIGNED NULL, reopened_at DATETIME NULL, reopen_reason VARCHAR(1000) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uk_billing_period(year,month), CONSTRAINT fk_billing_period_reviewed FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_billing_period_closed FOREIGN KEY(closed_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_billing_period_reopened FOREIGN KEY(reopened_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO billing_services(service_code,name,display_order) VALUES
('00547','Teste de hematócrito',10),('00548','Controle de qualidade da água deionizada',20),('00549','Contagem de plaquetas',30),
('00550','Contagem de leucócitos',40),('00551','Contagem de leucócitos (leuco-reduzidos)',50),('00552','Contagem de hemácias',60),
('00553','Dosagem de fator VIII',70),('00554','Dosagem de fibrinogênio',80),('00556','Dosagem de hemoglobina',90),
('00557','Inspeção de materiais',100),('00558','Proteínas residuais',110),('00559','Teste de pH',120),('00560','Swirling (teste visual)',130),
('00561','Teste bacteriológico',140),('00562','Teste de grau de hemólise',150),('00563','Volume do hemocomponente',160),
('00830','Coloração de GRAM',170),('00831','Identificação bacteriana - GRAM positivo',180),('00832','Identificação bacteriana - GRAM negativo',190),
('00833','Viabilidade celular - Pré-congelamento (automatizado)',200),('00834','Viabilidade celular - Pós-congelamento (automatizado)',210),
('00828','Viabilidade celular - Pré-congelamento (manual)',220),('00829','Viabilidade celular - Pós-congelamento (manual)',230),
('00841','PRP (Plasma Rico em Plaquetas)',240),('00842','LPA (Lisado de Plaquetas)',250),('00840','Contagem CD34+',260),('00843','Teste Automatizado',270)
ON DUPLICATE KEY UPDATE name=VALUES(name),display_order=VALUES(display_order);

-- O catálogo próprio nasce vinculado às units existentes; centros exclusivamente institucionais podem ser incluídos pela configuração.
INSERT INTO billing_cost_centers(cost_center_code,name,unit_id,display_order)
SELECT COALESCE(NULLIF(TRIM(code),''),CONCAT('UNIT-',id)),name,id,id FROM units
ON DUPLICATE KEY UPDATE name=VALUES(name),unit_id=VALUES(unit_id);

-- Apenas correspondências sem ambiguidade semântica/codificada. Demais resultados finais ficam visíveis como pendência.
INSERT INTO billing_service_mappings(billing_service_id,test_id,blood_component_id,context_scope,active)
SELECT s.id,t.id,NULL,'quality_control',1 FROM billing_services s JOIN tests t
 ON (s.service_code,t.code) IN (('00547','HEMATOCRIT'),('00549','PLATELETS_PER_UNIT'),('00549','PLATELETS_PER_ML'),
 ('00552','REDBLOODCELLS_PER_ML'),('00553','FACTOR_VIII'),('00554','FIBRINOGEN'),('00556','HEMOGLOBIN_PER_UNIT'),
 ('00558','RESIDUAL_PROTEIN'),('00559','PH'),('00560','SWIRLING'),('00561','BACTERIOLOGY'),('00562','HEMOLYSIS_DEGREE'),('00563','VOLUME'))
WHERE t.is_final_result=1 AND NOT EXISTS(SELECT 1 FROM billing_service_mappings m WHERE m.billing_service_id=s.id AND m.test_id=t.id AND m.blood_component_id IS NULL AND m.context_scope='quality_control');

INSERT INTO permissions(permission_key,name,module,status) VALUES
('billing.view','Visualizar faturamento','billing','active'),('billing.manual.manage','Gerenciar lançamentos complementares','billing','active'),
('billing.config.manage','Configurar faturamento','billing','active'),('billing.close','Fechar competência de faturamento','billing','active'),
('billing.reopen','Reabrir competência de faturamento','billing','active')
ON DUPLICATE KEY UPDATE name=VALUES(name),module=VALUES(module),status='active';

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.permission_key IN ('billing.view','billing.manual.manage','billing.config.manage','billing.close','billing.reopen') WHERE r.slug='administrador';
