-- Habilita Bacteriológico para CH no catálogo compartilhado sem alterar o workflow do CQ.
INSERT IGNORE INTO test_blood_components(test_id,blood_component_id,is_required)
SELECT t.id,bc.id,0 FROM tests t CROSS JOIN blood_components bc
WHERE t.code='BACTERIOLOGY' AND bc.code='CH';
