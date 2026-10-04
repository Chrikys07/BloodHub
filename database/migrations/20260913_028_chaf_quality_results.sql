-- CHAF: Concentrado de Hemácias por Aférese (código operacional estável).
-- A densidade pertence ao cadastro de CHAF e e lida em runtime pelo calculo de volume.
INSERT INTO blood_components(code,name,density,hemolysis_hematocrit_test_id,description,status)
SELECT 'CHAF','Concentrado de Hemácias por Aférese',1.0700,hct.id,
       'Concentrado de hemácias obtido por aférese.','active'
FROM tests hct
WHERE hct.code='HEMATOCRIT' AND NOT EXISTS(SELECT 1 FROM blood_components WHERE code='CHAF');

-- Matriz de ensaios de CH, com vinculos proprios pelo blood_component_id de CHAF.
INSERT IGNORE INTO test_blood_components(test_id,blood_component_id,is_required)
SELECT x.test_id,chaf.id,x.is_required FROM test_blood_components x
JOIN blood_components ch ON ch.id=x.blood_component_id AND ch.code='CH'
JOIN blood_components chaf ON chaf.code='CHAF';

-- Extensao residual identica a CHF; resultados derivados permanecem numericos.
INSERT IGNORE INTO test_blood_components(test_id,blood_component_id,is_required)
SELECT t.id,chaf.id,1 FROM tests t JOIN blood_components chaf ON chaf.code='CHAF'
WHERE t.code IN ('VOLUME','LEUKOCYTE_COUNT','LEUKOCYTES_PER_UNIT');

-- Somente elegibilidade pelo mecanismo bacteriologico central existente.
INSERT IGNORE INTO test_blood_components(test_id,blood_component_id,is_required)
SELECT t.id,chaf.id,1 FROM tests t JOIN blood_components chaf ON chaf.code='CHAF'
WHERE t.code='BACTERIOLOGY';

-- Taras e especificacoes nao sao copiadas de CH/CHF: devem ser configuradas para CHAF.
