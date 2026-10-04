-- CHL confirmado pelo código operacional estável do FC0538.
INSERT INTO blood_components(code,name,density,hemolysis_hematocrit_test_id,description,status)
SELECT 'CHL','Concentrado de Hemácias Lavadas',ch.density,hct.id,'Concentrado de hemácias lavadas.','active'
FROM blood_components ch JOIN tests hct ON hct.code='HEMATOCRIT'
WHERE ch.code='CH' AND NOT EXISTS(SELECT 1 FROM blood_components WHERE code='CHL');

-- A mesma referência física aplicável às hemácias é explicitamente habilitada para CHL.
INSERT IGNORE INTO bag_brand_tare_components(bag_brand_tare_id,blood_component_id)
SELECT map.bag_brand_tare_id,chl.id FROM bag_brand_tare_components map
JOIN blood_components ch ON ch.id=map.blood_component_id AND ch.code='CH'
JOIN blood_components chl ON chl.code='CHL';

INSERT INTO tests(code,name,result_type,unit,allows_ad_hoc,status)
SELECT 'RECOVERY','Recuperação','numeric','%',0,'active' WHERE NOT EXISTS(SELECT 1 FROM tests WHERE code='RECOVERY');
INSERT INTO tests(code,name,result_type,unit,allows_ad_hoc,status)
SELECT 'RESIDUAL_PROTEIN','Proteína Residual','numeric','g/U',0,'active' WHERE NOT EXISTS(SELECT 1 FROM tests WHERE code='RESIDUAL_PROTEIN');

INSERT IGNORE INTO test_blood_components(test_id,blood_component_id,is_required)
SELECT t.id,chl.id,IF(t.code='FREE_HEMOGLOBIN',0,1) FROM tests t JOIN blood_components chl ON chl.code='CHL'
WHERE t.code IN ('HEMATOCRIT','HEMOGLOBIN','HEMOGLOBIN_PER_UNIT','HEMOLYSIS_DEGREE','FREE_HEMOGLOBIN','VOLUME','RECOVERY','RESIDUAL_PROTEIN');
-- Apenas elegibilidade central; o microbiológico permanece fora desta entrega.
INSERT IGNORE INTO test_blood_components(test_id,blood_component_id,is_required)
SELECT t.id,chl.id,0 FROM tests t JOIN blood_components chl ON chl.code='CHL' WHERE t.code='BACTERIOLOGY';

CREATE TABLE IF NOT EXISTS washed_red_cell_results (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sample_id BIGINT UNSIGNED NOT NULL,
 initial_weight_g DECIMAL(12,4) NULL, initial_volume_ml DECIMAL(14,6) NULL,
 final_weight_g DECIMAL(12,4) NULL, final_volume_ml DECIMAL(14,6) NULL,
 initial_hematocrit_pct DECIMAL(12,6) NULL, final_hematocrit_pct DECIMAL(12,6) NULL,
 recovery_pct DECIMAL(14,8) NULL, hemoglobin_g_dl DECIMAL(12,6) NULL,
 hemoglobin_per_unit_g DECIMAL(14,8) NULL, free_hemoglobin_g_dl DECIMAL(14,8) NULL,
 hemolysis_pct DECIMAL(14,8) NULL, standard_absorbance DECIMAL(16,8) NULL,
 protein_absorbance DECIMAL(16,8) NULL, residual_protein_g_u DECIMAL(16,10) NULL,
 bag_brand_tare_id BIGINT UNSIGNED NOT NULL, tare_weight_used DECIMAL(10,3) NOT NULL,
 density_used DECIMAL(10,6) NOT NULL, recorded_by BIGINT UNSIGNED NULL,
 recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_wrc_sample FOREIGN KEY(sample_id) REFERENCES samples(id) ON DELETE CASCADE,
 CONSTRAINT fk_wrc_tare FOREIGN KEY(bag_brand_tare_id) REFERENCES bag_brand_tares(id) ON DELETE RESTRICT,
 CONSTRAINT fk_wrc_user FOREIGN KEY(recorded_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uk_wrc_sample(sample_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Regras iniciais versionadas; o renderer não conhece limites normativos.
INSERT INTO blood_component_test_specifications(version_number,blood_component_id,test_id,rule_type,min_value,unit,effective_from,source_name,source_reference,notes,sampling_requirement_notes,active)
SELECT 1,chl.id,t.id,'GTE',40,'g/U','2026-09-13','Anexo vigente','Concentrado de Hemácias Lavadas','Teor de hemoglobina por unidade.','1% da produção ou 10 unidades/mês, o que for maior.',1 FROM blood_components chl JOIN tests t ON t.code='HEMOGLOBIN_PER_UNIT' WHERE chl.code='CHL' AND NOT EXISTS(SELECT 1 FROM blood_component_test_specifications s WHERE s.blood_component_id=chl.id AND s.test_id=t.id);
INSERT INTO blood_component_test_specifications(version_number,blood_component_id,test_id,rule_type,min_value,max_value,unit,effective_from,source_name,source_reference,notes,sampling_requirement_notes,active)
SELECT 1,chl.id,t.id,'BETWEEN',50,75,'%','2026-09-13','Anexo vigente','Concentrado de Hemácias Lavadas','Hematócrito final.','1% da produção ou 10 unidades/mês, o que for maior.',1 FROM blood_components chl JOIN tests t ON t.code='HEMATOCRIT' WHERE chl.code='CHL' AND NOT EXISTS(SELECT 1 FROM blood_component_test_specifications s WHERE s.blood_component_id=chl.id AND s.test_id=t.id);
INSERT INTO blood_component_test_specifications(version_number,blood_component_id,test_id,rule_type,max_value,unit,effective_from,source_name,source_reference,notes,sampling_requirement_notes,active)
SELECT 1,chl.id,t.id,'LT',0.8,'%','2026-09-13','Anexo vigente','Concentrado de Hemácias Lavadas','Grau de hemólise.','1% da produção ou 10 unidades/mês, o que for maior.',1 FROM blood_components chl JOIN tests t ON t.code='HEMOLYSIS_DEGREE' WHERE chl.code='CHL' AND NOT EXISTS(SELECT 1 FROM blood_component_test_specifications s WHERE s.blood_component_id=chl.id AND s.test_id=t.id);
INSERT INTO blood_component_test_specifications(version_number,blood_component_id,test_id,rule_type,min_value,unit,effective_from,source_name,source_reference,notes,sampling_requirement_notes,active)
SELECT 1,chl.id,t.id,'GT',80,'%','2026-09-13','Anexo vigente','Concentrado de Hemácias Lavadas','Recuperação.','1% da produção ou 10 unidades/mês, o que for maior.',1 FROM blood_components chl JOIN tests t ON t.code='RECOVERY' WHERE chl.code='CHL' AND NOT EXISTS(SELECT 1 FROM blood_component_test_specifications s WHERE s.blood_component_id=chl.id AND s.test_id=t.id);
INSERT INTO blood_component_test_specifications(version_number,blood_component_id,test_id,rule_type,max_value,unit,effective_from,source_name,source_reference,notes,sampling_requirement_notes,active)
SELECT 1,chl.id,t.id,'LT',0.5,'g/U','2026-09-13','Anexo vigente','Concentrado de Hemácias Lavadas','Proteína residual.','Todas as unidades produzidas.',1 FROM blood_components chl JOIN tests t ON t.code='RESIDUAL_PROTEIN' WHERE chl.code='CHL' AND NOT EXISTS(SELECT 1 FROM blood_component_test_specifications s WHERE s.blood_component_id=chl.id AND s.test_id=t.id);
