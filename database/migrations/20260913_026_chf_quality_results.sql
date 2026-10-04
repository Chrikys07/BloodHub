-- CHF confirmado no FC0538 pelo código operacional "CHF" (aba CHFiltrado).
INSERT INTO blood_components(code,name,density,hemolysis_hematocrit_test_id,description,status)
SELECT 'CHF','Concentrado de Hemácias Desleucocitado',ch.density,hct.id,
       'Concentrado de hemácias filtrado/desleucocitado.','active'
FROM blood_components ch JOIN tests hct ON hct.code='HEMATOCRIT'
WHERE ch.code='CH' AND NOT EXISTS(SELECT 1 FROM blood_components WHERE code='CHF');

-- As referências já habilitadas para CH passam a apontar explicitamente também para CHF.
INSERT IGNORE INTO bag_brand_tare_components(bag_brand_tare_id,blood_component_id)
SELECT map.bag_brand_tare_id,chf.id FROM bag_brand_tare_components map
JOIN blood_components ch ON ch.id=map.blood_component_id AND ch.code='CH'
JOIN blood_components chf ON chf.code='CHF';

-- Reutiliza os testes de CH e acrescenta os três registros próprios do fluxo residual.
INSERT IGNORE INTO test_blood_components(test_id,blood_component_id,is_required)
SELECT x.test_id,chf.id,x.is_required FROM test_blood_components x
JOIN blood_components ch ON ch.id=x.blood_component_id AND ch.code='CH'
JOIN blood_components chf ON chf.code='CHF';
INSERT IGNORE INTO test_blood_components(test_id,blood_component_id,is_required)
SELECT t.id,chf.id,1 FROM tests t JOIN blood_components chf ON chf.code='CHF'
WHERE t.code IN ('VOLUME','LEUKOCYTE_COUNT','LEUKOCYTES_PER_UNIT');
-- Mantém a elegibilidade global, sem implementar um fluxo bacteriológico novo.
INSERT IGNORE INTO test_blood_components(test_id,blood_component_id,is_required)
SELECT t.id,chf.id,1 FROM tests t JOIN blood_components chf ON chf.code='CHF' WHERE t.code='BACTERIOLOGY';

-- Valores iniciais do Anexo 6; o evaluator continua lendo exclusivamente a tabela versionada.
INSERT INTO blood_component_test_specifications(version_number,blood_component_id,test_id,rule_type,min_value,unit,effective_from,source_name,source_reference,notes,active)
SELECT 1,chf.id,t.id,'GTE',40,'g/U','2026-09-13','Anexo 6 vigente','Concentrado de Hemácias Desleucocitadas','Teor de hemoglobina por unidade.',1
FROM blood_components chf JOIN tests t ON t.code='HEMOGLOBIN_PER_UNIT' WHERE chf.code='CHF' AND NOT EXISTS(SELECT 1 FROM blood_component_test_specifications s WHERE s.blood_component_id=chf.id AND s.test_id=t.id);
INSERT INTO blood_component_test_specifications(version_number,blood_component_id,test_id,rule_type,max_value,unit,effective_from,source_name,source_reference,notes,active)
SELECT 1,chf.id,t.id,'LT',0.8,'%','2026-09-13','Anexo 6 vigente','Concentrado de Hemácias Desleucocitadas','Grau de hemólise.',1
FROM blood_components chf JOIN tests t ON t.code='HEMOLYSIS_DEGREE' WHERE chf.code='CHF' AND NOT EXISTS(SELECT 1 FROM blood_component_test_specifications s WHERE s.blood_component_id=chf.id AND s.test_id=t.id);
INSERT INTO blood_component_test_specifications(version_number,blood_component_id,test_id,rule_type,max_value,unit,effective_from,source_name,source_reference,notes,active)
SELECT 1,chf.id,t.id,'LT',5000000,'/U','2026-09-13','Anexo 6 vigente','Concentrado de Hemácias Desleucocitadas','Leucócitos residuais por unidade.',1
FROM blood_components chf JOIN tests t ON t.code='LEUKOCYTES_PER_UNIT' WHERE chf.code='CHF' AND NOT EXISTS(SELECT 1 FROM blood_component_test_specifications s WHERE s.blood_component_id=chf.id AND s.test_id=t.id);
