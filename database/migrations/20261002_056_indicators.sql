-- Infraestrutura genérica de indicadores institucionais.
CREATE TABLE IF NOT EXISTS indicators (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    name VARCHAR(180) NOT NULL,
    objective TEXT NULL,
    sector VARCHAR(120) NULL,
    regional VARCHAR(120) NULL,
    category VARCHAR(120) NULL,
    subcategory VARCHAR(120) NULL,
    periodicity VARCHAR(60) NOT NULL DEFAULT 'Mensal',
    value_type ENUM('percentage','scientific','number') NOT NULL DEFAULT 'number',
    value_unit VARCHAR(80) NULL,
    configured_test_id BIGINT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_indicators_code(code),
    UNIQUE KEY uk_indicators_slug(slug),
    CONSTRAINT fk_indicators_test FOREIGN KEY(configured_test_id) REFERENCES tests(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS indicator_targets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    indicator_id BIGINT UNSIGNED NOT NULL,
    target_operator ENUM('GT','GTE','LT','LTE','EQ') NOT NULL DEFAULT 'GTE',
    target_value DECIMAL(20,8) NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_indicator_target_version(indicator_id,effective_from),
    KEY idx_indicator_target_lookup(indicator_id,active,effective_from,effective_to),
    CONSTRAINT fk_indicator_target_indicator FOREIGN KEY(indicator_id) REFERENCES indicators(id) ON DELETE CASCADE,
    CONSTRAINT fk_indicator_target_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS indicator_responsibles (
    indicator_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(indicator_id,user_id),
    KEY idx_indicator_responsible_user(user_id,active),
    CONSTRAINT fk_indicator_responsible_indicator FOREIGN KEY(indicator_id) REFERENCES indicators(id) ON DELETE CASCADE,
    CONSTRAINT fk_indicator_responsible_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS indicator_analyses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    indicator_id BIGINT UNSIGNED NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    analysis_text TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_indicator_analysis_period(indicator_id,year,month),
    KEY idx_indicator_analysis_period(indicator_id,year,month),
    CONSTRAINT fk_indicator_analysis_indicator FOREIGN KEY(indicator_id) REFERENCES indicators(id) ON DELETE CASCADE,
    CONSTRAINT fk_indicator_analysis_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_indicator_analysis_updater FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT chk_indicator_analysis_month CHECK(month BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS indicator_actions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    indicator_analysis_id BIGINT UNSIGNED NOT NULL,
    cause TEXT NULL,
    what_text TEXT NULL,
    why_text TEXT NULL,
    how_text TEXT NULL,
    who_text VARCHAR(255) NULL,
    responsible_user_id BIGINT UNSIGNED NULL,
    when_text VARCHAR(255) NULL,
    due_date DATE NULL,
    status ENUM('planned','in_progress','completed','cancelled') NOT NULL DEFAULT 'planned',
    completion_date DATE NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_indicator_actions_analysis_status(indicator_analysis_id,status),
    KEY idx_indicator_actions_due(status,due_date),
    CONSTRAINT fk_indicator_action_analysis FOREIGN KEY(indicator_analysis_id) REFERENCES indicator_analyses(id) ON DELETE CASCADE,
    CONSTRAINT fk_indicator_action_responsible FOREIGN KEY(responsible_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_indicator_action_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_indicator_action_updater FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO indicators(code,slug,name,objective,sector,regional,category,subcategory,periodicity,value_type,value_unit,active)
VALUES('HEMOCOMPONENTS_WITHIN_SPECIFICATIONS','hemocomponents-within-specifications','Hemocomponentes Dentro das Especificações','Monitorar a qualidade dos hemocomponentes produzidos na instituição.','LCQH','Laboratórios','Gerencial','Performance','Mensal','percentage','%',1)
ON DUPLICATE KEY UPDATE name=VALUES(name),updated_at=updated_at;

INSERT INTO indicators(code,slug,name,periodicity,value_type,value_unit,configured_test_id,active)
SELECT 'PLATELET_MEAN_CONCENTRATION','platelet-mean-concentration','Concentração Média de Plaquetas','Mensal','scientific','/U',t.id,1
FROM tests t WHERE t.code='PLATELETS_PER_UNIT' LIMIT 1
ON DUPLICATE KEY UPDATE name=VALUES(name),configured_test_id=COALESCE(indicators.configured_test_id,VALUES(configured_test_id)),updated_at=indicators.updated_at;

INSERT INTO indicator_targets(indicator_id,target_operator,target_value,effective_from,active,created_by)
SELECT i.id,'GTE',90.00000000,'2026-01-01',1,NULL FROM indicators i
WHERE i.code='HEMOCOMPONENTS_WITHIN_SPECIFICATIONS'
AND NOT EXISTS(SELECT 1 FROM indicator_targets it WHERE it.indicator_id=i.id);

INSERT INTO permissions(permission_key,name,module,description,status) VALUES
('indicators.view','Visualizar indicadores','indicators','Consulta de indicadores, análises e planos de ação.','active'),
('indicators.analysis.manage','Gerenciar análises de indicadores','indicators','Edição operacional por responsáveis cadastrados.','active'),
('indicators.config.manage','Configurar indicadores','indicators','Metadados, metas, responsáveis e ativação.','active')
ON DUPLICATE KEY UPDATE name=VALUES(name),module=VALUES(module),description=VALUES(description),status='active';

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.permission_key IN ('indicators.view','indicators.analysis.manage','indicators.config.manage')
WHERE r.slug='administrador';
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.permission_key='indicators.view'
WHERE r.slug='gestao';
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.permission_key IN ('indicators.view','indicators.analysis.manage')
WHERE r.slug='lcqh';
