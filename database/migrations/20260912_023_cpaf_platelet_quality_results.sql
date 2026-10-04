-- CPAF compartilha o fluxo analítico de CP, mantendo configuração própria por hemocomponente.
INSERT INTO blood_components(code,name,density,description,status)
SELECT 'CPAF','Concentrado de Plaquetas por Aférese',NULL,
       'Concentrado de plaquetas obtido por aférese.','active'
WHERE NOT EXISTS(SELECT 1 FROM blood_components WHERE code='CPAF');

-- Os campos disponíveis são os mesmos de CP. Especificações, densidade e taras não são copiadas.
INSERT IGNORE INTO test_blood_components(test_id,blood_component_id,is_required)
SELECT cp_tests.test_id,cpaf.id,cp_tests.is_required
  FROM test_blood_components cp_tests
  JOIN blood_components cp ON cp.id=cp_tests.blood_component_id AND cp.code='CP'
  JOIN blood_components cpaf ON cpaf.code='CPAF';
