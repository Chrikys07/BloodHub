-- Elegibilidade por codigo estavel e backfill idempotente de pendencias antigas.
-- CPAF segue elegivel individualmente; o pool automatico permanece restrito a CP no servico.
INSERT IGNORE INTO test_blood_components(test_id,blood_component_id,is_required)
SELECT t.id,bc.id,1 FROM tests t JOIN blood_components bc ON bc.code IN ('CP','CPAF')
WHERE t.code='BACTERIOLOGY' AND t.status='active';

INSERT INTO sample_tests(sample_id,test_id,status)
SELECT s.id,t.id,'pending' FROM samples s
JOIN blood_components bc ON bc.id=s.blood_component_id AND bc.code IN ('CP','CPAF')
JOIN test_blood_components x ON x.blood_component_id=bc.id
JOIN tests t ON t.id=x.test_id AND t.code='BACTERIOLOGY' AND t.status='active'
WHERE s.purpose='quality_control' AND s.status IN ('received','in_analysis','partial_results')
AND NOT EXISTS(SELECT 1 FROM sample_tests st WHERE st.sample_id=s.id AND st.test_id=t.id)
AND NOT EXISTS(SELECT 1 FROM bacteriology_results br WHERE br.sample_id=s.id AND br.is_final=1);
