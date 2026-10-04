-- CRIO: resultados manuais conforme o fluxo historico FC0538.
INSERT INTO blood_components(code,name,description,status)
SELECT 'CRIO','Crioprecipitado','Crioprecipitado.','active'
WHERE NOT EXISTS(SELECT 1 FROM blood_components WHERE code='CRIO');

INSERT INTO tests(code,name,result_type,unit,allows_ad_hoc,status)
SELECT 'FIBRINOGEN','Fibrinogênio','numeric','mg/U',0,'active'
WHERE NOT EXISTS(SELECT 1 FROM tests WHERE code='FIBRINOGEN');

INSERT IGNORE INTO test_blood_components(test_id,blood_component_id,is_required)
SELECT t.id,bc.id,1 FROM tests t JOIN blood_components bc ON bc.code='CRIO'
WHERE t.code IN ('VOLUME','FIBRINOGEN');

INSERT INTO blood_component_test_specifications(version_number,blood_component_id,test_id,rule_type,min_value,max_value,unit,effective_from,source_name,source_reference,notes,sampling_requirement_notes,active)
SELECT 1,bc.id,t.id,'BETWEEN',10,40,'mL','2026-09-15','Anexo 6 do Anexo IV-B','Crioprecipitado','Volume.','Todas as unidades produzidas.',1
FROM blood_components bc JOIN tests t ON t.code='VOLUME'
WHERE bc.code='CRIO' AND NOT EXISTS(SELECT 1 FROM blood_component_test_specifications s WHERE s.blood_component_id=bc.id AND s.test_id=t.id);

INSERT INTO blood_component_test_specifications(version_number,blood_component_id,test_id,rule_type,min_value,unit,effective_from,source_name,source_reference,notes,sampling_requirement_notes,active)
SELECT 1,bc.id,t.id,'GT',150,'mg/U','2026-09-15','Anexo 6 do Anexo IV-B','Crioprecipitado','Fibrinogênio.','1% da produção ou 4 unidades, o que for maior, nos meses em que houver produção, em unidades com até 30 dias de armazenamento.',1
FROM blood_components bc JOIN tests t ON t.code='FIBRINOGEN'
WHERE bc.code='CRIO' AND NOT EXISTS(SELECT 1 FROM blood_component_test_specifications s WHERE s.blood_component_id=bc.id AND s.test_id=t.id);
