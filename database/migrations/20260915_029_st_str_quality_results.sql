-- ST e STR: componentes operacionais estaveis do Controle de Qualidade.
-- A densidade e inicializada somente na criacao ou quando estiver ausente.
INSERT INTO blood_components(code,name,density,hemolysis_hematocrit_test_id,description,status)
SELECT 'ST','Sangue Total',1.0560,hct.id,'Sangue total para uso transfusional.','active'
FROM tests hct WHERE hct.code='HEMATOCRIT'
AND NOT EXISTS(SELECT 1 FROM blood_components WHERE code='ST');
UPDATE blood_components SET density=1.0560 WHERE code='ST' AND density IS NULL;

INSERT INTO blood_components(code,name,density,description,status)
SELECT 'STR','Sangue Total Reconstituído',1.0560,'Sangue total reconstituído.','active'
WHERE NOT EXISTS(SELECT 1 FROM blood_components WHERE code='STR');
UPDATE blood_components SET name='Sangue Total Reconstituído',description='Sangue total reconstituído.'
WHERE code='STR' AND name='Sangue Total Reconstituido';
UPDATE blood_components SET density=1.0560 WHERE code='STR' AND density IS NULL;

-- FC0538: (Hb livre / Hb) x (100 - Ht). Ht e dependencia tecnica de ST.
UPDATE blood_components bc JOIN tests hct ON hct.code='HEMATOCRIT'
SET bc.hemolysis_hematocrit_test_id=hct.id
WHERE bc.code='ST' AND bc.hemolysis_hematocrit_test_id IS NULL;

INSERT IGNORE INTO test_blood_components(test_id,blood_component_id,is_required)
SELECT t.id,bc.id,CASE WHEN t.code='FREE_HEMOGLOBIN' THEN 0 ELSE 1 END
FROM tests t JOIN blood_components bc ON bc.code='ST'
WHERE t.code IN ('VOLUME','HEMATOCRIT','HEMOGLOBIN','HEMOGLOBIN_PER_UNIT','HEMOLYSIS_DEGREE','FREE_HEMOGLOBIN','BACTERIOLOGY');

INSERT IGNORE INTO test_blood_components(test_id,blood_component_id,is_required)
SELECT t.id,bc.id,1 FROM tests t JOIN blood_components bc ON bc.code='STR'
WHERE t.code IN ('VOLUME','HEMATOCRIT','BACTERIOLOGY');

-- Regras iniciais de ST. Nunca substituem configuracao administrativa existente.
INSERT INTO blood_component_test_specifications(version_number,blood_component_id,test_id,rule_type,min_value,max_value,unit,effective_from,source_name,source_reference,notes,active)
SELECT 1,bc.id,t.id,'BETWEEN',405,495,'mL','2026-09-15','Anexo 6 do Anexo IV-B','Sangue Total','Volume.',1
FROM blood_components bc JOIN tests t ON t.code='VOLUME'
WHERE bc.code='ST' AND NOT EXISTS(SELECT 1 FROM blood_component_test_specifications s WHERE s.blood_component_id=bc.id AND s.test_id=t.id);
INSERT INTO blood_component_test_specifications(version_number,blood_component_id,test_id,rule_type,min_value,unit,effective_from,source_name,source_reference,notes,active)
SELECT 1,bc.id,t.id,'GTE',45,'g/U','2026-09-15','Anexo 6 do Anexo IV-B','Sangue Total','Teor de hemoglobina por unidade.',1
FROM blood_components bc JOIN tests t ON t.code='HEMOGLOBIN_PER_UNIT'
WHERE bc.code='ST' AND NOT EXISTS(SELECT 1 FROM blood_component_test_specifications s WHERE s.blood_component_id=bc.id AND s.test_id=t.id);
INSERT INTO blood_component_test_specifications(version_number,blood_component_id,test_id,rule_type,max_value,unit,effective_from,source_name,source_reference,notes,active)
SELECT 1,bc.id,t.id,'LT',0.8,'%','2026-09-15','Anexo 6 do Anexo IV-B','Sangue Total','Grau de hemolise.',1
FROM blood_components bc JOIN tests t ON t.code='HEMOLYSIS_DEGREE'
WHERE bc.code='ST' AND NOT EXISTS(SELECT 1 FROM blood_component_test_specifications s WHERE s.blood_component_id=bc.id AND s.test_id=t.id);
