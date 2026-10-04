-- Fluxo bacteriologico: resultados individuais, pools de CP e retestes.
CREATE TABLE IF NOT EXISTS bacteriology_pools (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(80) NOT NULL,
 component_id BIGINT UNSIGNED NOT NULL,
 origin_unit_id BIGINT UNSIGNED NOT NULL,
 client_id BIGINT UNSIGNED NULL,
 start_date DATE NOT NULL,
 end_date DATE NOT NULL,
 result ENUM('negative','positive') NULL,
 status ENUM('open','completed_negative','completed_positive','cancelled') NOT NULL DEFAULT 'open',
 created_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 resulted_by BIGINT UNSIGNED NULL, resulted_at DATETIME NULL,
 cancelled_by BIGINT UNSIGNED NULL, cancelled_at DATETIME NULL, cancellation_reason VARCHAR(500) NULL,
 notes TEXT NULL,
 CONSTRAINT fk_bact_pool_component FOREIGN KEY(component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bact_pool_origin FOREIGN KEY(origin_unit_id) REFERENCES units(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bact_pool_client FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE SET NULL,
 CONSTRAINT fk_bact_pool_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_bact_pool_result_user FOREIGN KEY(resulted_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_bact_pool_cancel_user FOREIGN KEY(cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uk_bacteriology_pool_code(code), INDEX idx_bact_pool_status(status),
 INDEX idx_bact_pool_group(component_id,origin_unit_id,client_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bacteriology_pool_members (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, pool_id BIGINT UNSIGNED NOT NULL,
 sample_id BIGINT UNSIGNED NOT NULL, position TINYINT UNSIGNED NOT NULL,
 active_sample_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN active=1 THEN sample_id ELSE NULL END) STORED,
 active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 released_at DATETIME NULL,
 CONSTRAINT fk_bact_member_pool FOREIGN KEY(pool_id) REFERENCES bacteriology_pools(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bact_member_sample FOREIGN KEY(sample_id) REFERENCES samples(id) ON DELETE RESTRICT,
 CONSTRAINT chk_bact_member_position CHECK(position BETWEEN 1 AND 4),
 UNIQUE KEY uk_bact_pool_position(pool_id,position), UNIQUE KEY uk_bact_pool_sample(pool_id,sample_id),
 UNIQUE KEY uk_bact_active_sample(active_sample_id), INDEX idx_bact_member_sample(sample_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bacteriology_results (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sample_id BIGINT UNSIGNED NOT NULL,
 stage ENUM('individual_initial','pool_screening','post_pool_individual','retest') NOT NULL,
 source_type ENUM('individual','pool','retest') NOT NULL, pool_id BIGINT UNSIGNED NULL,
 result ENUM('negative','positive') NOT NULL, is_final TINYINT(1) NOT NULL DEFAULT 0,
 bacteriological_conformity ENUM('conforming','nonconforming','pending') NOT NULL DEFAULT 'pending',
 performed_by BIGINT UNSIGNED NULL, performed_at DATETIME NOT NULL,
 notes TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_bact_result_sample FOREIGN KEY(sample_id) REFERENCES samples(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bact_result_pool FOREIGN KEY(pool_id) REFERENCES bacteriology_pools(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bact_result_user FOREIGN KEY(performed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_bact_result_sample_final(sample_id,is_final), INDEX idx_bact_result_pool(pool_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bacteriology_retests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sample_id BIGINT UNSIGNED NOT NULL,
 initial_result_id BIGINT UNSIGNED NOT NULL, status ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
 result ENUM('negative','positive') NULL, result_id BIGINT UNSIGNED NULL,
 created_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 completed_by BIGINT UNSIGNED NULL, completed_at DATETIME NULL, notes TEXT NULL,
 CONSTRAINT fk_bact_retest_sample FOREIGN KEY(sample_id) REFERENCES samples(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bact_retest_initial FOREIGN KEY(initial_result_id) REFERENCES bacteriology_results(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bact_retest_result FOREIGN KEY(result_id) REFERENCES bacteriology_results(id) ON DELETE SET NULL,
 CONSTRAINT fk_bact_retest_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_bact_retest_completer FOREIGN KEY(completed_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uk_bact_retest_initial(initial_result_id), INDEX idx_bact_retest_status(status,sample_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions(permission_key,name,module,status) VALUES
('quality.bacteriology.view','Visualizar Bacteriologico','quality_results','active'),
('quality.bacteriology.edit','Registrar resultados bacteriologicos','quality_results','active'),
('quality.bacteriology.pool','Gerenciar pools bacteriologicos','quality_results','active')
ON DUPLICATE KEY UPDATE name=VALUES(name),module=VALUES(module),status='active';
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.permission_key IN ('quality.bacteriology.view','quality.bacteriology.edit','quality.bacteriology.pool')
WHERE r.slug IN ('administrador','lcqh');
