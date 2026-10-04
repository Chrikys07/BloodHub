-- Classificacao versionada de rendimento do CPAF. Volume permanece uma especificacao independente.
CREATE TABLE IF NOT EXISTS cpaf_yield_classification_rules (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 supersedes_id BIGINT UNSIGNED NULL,
 version_number INT UNSIGNED NOT NULL DEFAULT 1,
 blood_component_id BIGINT UNSIGNED NOT NULL,
 simple_min_platelets DECIMAL(30,10) NOT NULL,
 double_min_platelets DECIMAL(30,10) NOT NULL,
 effective_from DATE NULL,
 effective_to DATE NULL,
 source_name VARCHAR(255) NULL,
 source_reference VARCHAR(500) NULL,
 notes TEXT NULL,
 rule_kind VARCHAR(80) NOT NULL DEFAULT 'PLATELETS_PER_UNIT_THRESHOLDS',
 extension_config_json JSON NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_cpaf_yield_component FOREIGN KEY (blood_component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
 CONSTRAINT fk_cpaf_yield_supersedes FOREIGN KEY (supersedes_id) REFERENCES cpaf_yield_classification_rules(id) ON DELETE SET NULL,
 CONSTRAINT fk_cpaf_yield_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_cpaf_yield_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_cpaf_yield_active (blood_component_id,active,effective_from,effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpaf_yield_classifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sample_id BIGINT UNSIGNED NOT NULL,
 platelet_test_result_id BIGINT UNSIGNED NULL,
 rule_id BIGINT UNSIGNED NULL,
 platelets_per_unit DECIMAL(30,10) NOT NULL,
 classification_code ENUM('INSUFFICIENT_YIELD','CPAF_SIMPLE','CPAF_DOUBLE') NOT NULL,
 classification_label VARCHAR(80) NOT NULL,
 simple_min_snapshot DECIMAL(30,10) NOT NULL,
 double_min_snapshot DECIMAL(30,10) NOT NULL,
 rule_kind_snapshot VARCHAR(80) NOT NULL,
 effective_from_snapshot DATE NULL,
 effective_to_snapshot DATE NULL,
 source_name_snapshot VARCHAR(255) NULL,
 source_reference_snapshot VARCHAR(500) NULL,
 notes_snapshot TEXT NULL,
 rule_snapshot_json JSON NOT NULL,
 classified_at DATETIME NOT NULL,
 finalized_at DATETIME NULL,
 finalized_by BIGINT UNSIGNED NULL,
 CONSTRAINT fk_cpaf_class_sample FOREIGN KEY (sample_id) REFERENCES samples(id) ON DELETE CASCADE,
 CONSTRAINT fk_cpaf_class_result FOREIGN KEY (platelet_test_result_id) REFERENCES test_results(id) ON DELETE SET NULL,
 CONSTRAINT fk_cpaf_class_rule FOREIGN KEY (rule_id) REFERENCES cpaf_yield_classification_rules(id) ON DELETE SET NULL,
 CONSTRAINT fk_cpaf_class_finalized_by FOREIGN KEY (finalized_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uk_cpaf_class_sample (sample_id),
 INDEX idx_cpaf_class_code (classification_code,finalized_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cpaf_yield_classification_rules
 (blood_component_id,version_number,simple_min_platelets,double_min_platelets,effective_from,source_name,source_reference,notes,active)
SELECT bc.id,1,300000000000,600000000000,'2026-09-12',
 'Portaria GM/MS aplicavel / regulamentacao hemoterapica vigente',
 'Classificacao de CPAF por rendimento de Plaquetas/U',
 'Valores iniciais vigentes. Volume nao integra esta classificacao.',1
FROM blood_components bc
WHERE bc.code='CPAF'
  AND NOT EXISTS (SELECT 1 FROM cpaf_yield_classification_rules r WHERE r.blood_component_id=bc.id);

-- Requisito de volume federal inicial, independente da classificacao simples/dupla.
INSERT INTO blood_component_test_specifications
 (blood_component_id,test_id,version_number,rule_type,min_value,unit,effective_from,source_name,source_reference,notes,active)
SELECT bc.id,t.id,1,'GTE',200,'mL','2026-09-12',
 'Portaria GM/MS aplicavel / regulamentacao hemoterapica vigente',
 'Especificacao de volume do CPAF',
 'Especificacao independente da classificacao por rendimento; nao exige 400 mL para CPAF dupla.',1
FROM blood_components bc JOIN tests t ON t.code='VOLUME'
WHERE bc.code='CPAF'
  AND NOT EXISTS (SELECT 1 FROM blood_component_test_specifications s WHERE s.blood_component_id=bc.id AND s.test_id=t.id);

-- Mantem a divergencia normativa de baixo rendimento no SpecificationEvaluator.
INSERT INTO blood_component_test_specifications
 (blood_component_id,test_id,version_number,rule_type,min_value,unit,effective_from,source_name,source_reference,notes,active)
SELECT bc.id,t.id,1,'GTE',300000000000,'/U','2026-09-12',
 'Portaria GM/MS aplicavel / regulamentacao hemoterapica vigente',
 'Rendimento minimo de plaquetas do CPAF',
 'A classificacao derivada nao substitui esta avaliacao de conformidade.',1
FROM blood_components bc JOIN tests t ON t.code='PLATELETS_PER_UNIT'
WHERE bc.code='CPAF'
  AND NOT EXISTS (SELECT 1 FROM blood_component_test_specifications s WHERE s.blood_component_id=bc.id AND s.test_id=t.id);
