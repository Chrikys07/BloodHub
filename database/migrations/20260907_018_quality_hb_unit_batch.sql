-- Hemoglobina por unidade: teste calculado com resultado histórico em test_results.
UPDATE tests SET code='HEMOGLOBIN_PER_UNIT'
 WHERE LOWER(name) IN ('hemoglobina por unidade','teor de hemoglobina','hb (g/u)')
   AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM tests WHERE code='HEMOGLOBIN_PER_UNIT') stable WHERE stable.id<>tests.id);

INSERT INTO tests(code,name,result_type,unit,allows_ad_hoc,status)
SELECT 'HEMOGLOBIN_PER_UNIT','Hemoglobina por unidade','numeric','g/U',0,'active'
 WHERE NOT EXISTS(SELECT 1 FROM tests WHERE code='HEMOGLOBIN_PER_UNIT');

INSERT IGNORE INTO test_blood_components(test_id,blood_component_id,is_required)
SELECT t.id,bc.id,1 FROM tests t CROSS JOIN blood_components bc
 WHERE t.code='HEMOGLOBIN_PER_UNIT' AND bc.code='CH';
