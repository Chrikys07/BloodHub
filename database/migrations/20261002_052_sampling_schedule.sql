CREATE TABLE IF NOT EXISTS sampling_rules (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 blood_component_id BIGINT UNSIGNED NOT NULL,
 percentage DECIMAL(7,4) NOT NULL DEFAULT 1.0000,
 minimum_units INT UNSIGNED NOT NULL DEFAULT 10,
 small_production_mode ENUM('standard','actual_up_to_limit') NOT NULL DEFAULT 'standard',
 small_production_limit INT UNSIGNED NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 effective_from DATE NOT NULL,
 effective_to DATE NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_sampling_rule_component FOREIGN KEY(blood_component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
 CONSTRAINT chk_sampling_rule_percentage CHECK(percentage >= 0),
 CONSTRAINT chk_sampling_rule_period CHECK(effective_to IS NULL OR effective_to >= effective_from),
 INDEX idx_sampling_rule_period(blood_component_id,active,effective_from,effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sampling_schedule_days (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 blood_component_id BIGINT UNSIGNED NOT NULL,
 weekday TINYINT UNSIGNED NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 effective_from DATE NOT NULL,
 effective_to DATE NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_sampling_day_component FOREIGN KEY(blood_component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
 CONSTRAINT chk_sampling_weekday CHECK(weekday BETWEEN 0 AND 6),
 CONSTRAINT chk_sampling_day_period CHECK(effective_to IS NULL OR effective_to >= effective_from),
 INDEX idx_sampling_day_period(blood_component_id,weekday,active,effective_from,effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sampling_production_sources (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 target_blood_component_id BIGINT UNSIGNED NOT NULL,
 source_blood_component_id BIGINT UNSIGNED NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 effective_from DATE NOT NULL,
 effective_to DATE NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_sampling_source_target FOREIGN KEY(target_blood_component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
 CONSTRAINT fk_sampling_source_source FOREIGN KEY(source_blood_component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
 CONSTRAINT chk_sampling_source_distinct CHECK(target_blood_component_id <> source_blood_component_id),
 INDEX idx_sampling_source_period(target_blood_component_id,active,effective_from,effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions(permission_key,name,module,status) VALUES
 ('sampling_schedule.view','Visualizar cronograma de envio','sampling_schedule','active'),
 ('sampling_schedule.admin','Administrar regras do cronograma','sampling_schedule','active')
ON DUPLICATE KEY UPDATE name=VALUES(name),module=VALUES(module),status='active';

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p
WHERE (r.slug='administrador' OR LOWER(r.name)='administrador')
AND p.permission_key IN('sampling_schedule.view','sampling_schedule.admin');
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p
WHERE (r.slug='processamento' OR LOWER(r.name)='processamento')
AND p.permission_key='sampling_schedule.view';

INSERT INTO sampling_rules(blood_component_id,percentage,minimum_units,small_production_mode,small_production_limit,effective_from)
SELECT bc.id,1.0000,CASE WHEN bc.code IN('PFC','PFC24','PF','PF24','CRIO') THEN 4 ELSE 10 END,
 CASE WHEN bc.code IN('CHL','STR','CHAF') THEN 'actual_up_to_limit' ELSE 'standard' END,
 CASE WHEN bc.code IN('CHL','STR','CHAF') THEN 10 ELSE NULL END,'2026-01-01'
FROM blood_components bc
WHERE bc.code IN('CH','CHF','CP','PFC','PFC24','PF','PF24','STR','CHL','CPAF','CHAF','CRIO')
AND NOT EXISTS(SELECT 1 FROM sampling_rules sr WHERE sr.blood_component_id=bc.id);

INSERT INTO sampling_schedule_days(blood_component_id,weekday,effective_from)
SELECT bc.id,d.weekday,'2026-01-01' FROM blood_components bc JOIN (
 SELECT 'CH' code,1 weekday UNION ALL SELECT 'CHF',1 UNION ALL SELECT 'PF',1 UNION ALL SELECT 'PF24',1 UNION ALL SELECT 'STR',1 UNION ALL SELECT 'CHL',1 UNION ALL SELECT 'CPAF',1 UNION ALL SELECT 'CHAF',1
 UNION ALL SELECT 'CP',2 UNION ALL SELECT 'PF',2 UNION ALL SELECT 'PF24',2 UNION ALL SELECT 'STR',2 UNION ALL SELECT 'CHL',2 UNION ALL SELECT 'CPAF',2 UNION ALL SELECT 'CHAF',2
 UNION ALL SELECT 'CP',3 UNION ALL SELECT 'CHF',3 UNION ALL SELECT 'PF',3 UNION ALL SELECT 'PF24',3 UNION ALL SELECT 'STR',3 UNION ALL SELECT 'CHL',3 UNION ALL SELECT 'CPAF',3 UNION ALL SELECT 'CHAF',3
 UNION ALL SELECT 'CH',4 UNION ALL SELECT 'PF',4 UNION ALL SELECT 'PF24',4 UNION ALL SELECT 'STR',4 UNION ALL SELECT 'CHL',4 UNION ALL SELECT 'CPAF',4 UNION ALL SELECT 'CHAF',4
 UNION ALL SELECT 'PFC',5 UNION ALL SELECT 'PFC24',5 UNION ALL SELECT 'CRIO',5 UNION ALL SELECT 'STR',5 UNION ALL SELECT 'CHL',5 UNION ALL SELECT 'CPAF',5 UNION ALL SELECT 'CHAF',5
) d ON d.code=bc.code
WHERE NOT EXISTS(SELECT 1 FROM sampling_schedule_days ssd WHERE ssd.blood_component_id=bc.id AND ssd.weekday=d.weekday);

INSERT INTO sampling_production_sources(target_blood_component_id,source_blood_component_id,effective_from)
SELECT target.id,source.id,'2026-01-01' FROM blood_components target JOIN blood_components source
 ON (target.code='PF' AND source.code='PFC') OR (target.code='PF24' AND source.code='PFC24')
WHERE NOT EXISTS(SELECT 1 FROM sampling_production_sources sps WHERE sps.target_blood_component_id=target.id);
