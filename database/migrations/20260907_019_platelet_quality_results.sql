-- Resultados configuráveis do Concentrado de Plaquetas (CP).
ALTER TABLE test_results MODIFY COLUMN result_value_numeric DECIMAL(30,8) NULL;
ALTER TABLE test_result_parameters MODIFY COLUMN numeric_value DECIMAL(30,8) NULL;

INSERT INTO tests(code,name,result_type,unit,allows_ad_hoc,status)
SELECT 'VOLUME','Volume','numeric','mL',0,'active' WHERE NOT EXISTS(SELECT 1 FROM tests WHERE code='VOLUME');
INSERT INTO tests(code,name,result_type,unit,allows_ad_hoc,status)
SELECT 'PLATELET_COUNT','Número de Plaquetas','numeric',NULL,0,'active' WHERE NOT EXISTS(SELECT 1 FROM tests WHERE code='PLATELET_COUNT');
INSERT INTO tests(code,name,result_type,unit,allows_ad_hoc,status)
SELECT 'PLATELETS_PER_UNIT','Plaquetas por unidade','numeric','/U',0,'active' WHERE NOT EXISTS(SELECT 1 FROM tests WHERE code='PLATELETS_PER_UNIT');
INSERT INTO tests(code,name,result_type,unit,allows_ad_hoc,status)
SELECT 'LEUKOCYTE_COUNT','Número de Leucócitos','numeric',NULL,0,'active' WHERE NOT EXISTS(SELECT 1 FROM tests WHERE code='LEUKOCYTE_COUNT');
INSERT INTO tests(code,name,result_type,unit,allows_ad_hoc,status)
SELECT 'LEUKOCYTES_PER_UNIT','Leucócitos por unidade','numeric','/U',0,'active' WHERE NOT EXISTS(SELECT 1 FROM tests WHERE code='LEUKOCYTES_PER_UNIT');
INSERT INTO tests(code,name,result_type,unit,allows_ad_hoc,status)
SELECT 'PH','pH','numeric',NULL,0,'active' WHERE NOT EXISTS(SELECT 1 FROM tests WHERE code='PH');
INSERT INTO tests(code,name,result_type,unit,allows_ad_hoc,status)
SELECT 'SWIRLING','Swirling','select',NULL,0,'active' WHERE NOT EXISTS(SELECT 1 FROM tests WHERE code='SWIRLING');

INSERT IGNORE INTO test_blood_components(test_id,blood_component_id,is_required)
SELECT t.id,bc.id,1 FROM tests t CROSS JOIN blood_components bc
 WHERE bc.code='CP' AND t.code IN ('VOLUME','PLATELET_COUNT','PLATELETS_PER_UNIT','LEUKOCYTE_COUNT','LEUKOCYTES_PER_UNIT','PH','SWIRLING');
