-- Associação institucional obrigatória e idempotente:
-- resultado final BACTERIOLOGY + transfusion_reaction -> 00561.

-- Mantém somente uma associação ativa equivalente caso uma carga anterior tenha
-- criado duplicatas (a chave original contém NULL e não as impede no MySQL).
DELETE duplicate_mapping
FROM billing_service_mappings duplicate_mapping
JOIN billing_service_mappings canonical_mapping
  ON canonical_mapping.billing_service_id = duplicate_mapping.billing_service_id
 AND canonical_mapping.test_id = duplicate_mapping.test_id
 AND canonical_mapping.blood_component_id IS NULL
 AND duplicate_mapping.blood_component_id IS NULL
 AND canonical_mapping.active = 1
 AND duplicate_mapping.active = 1
 AND FIND_IN_SET('transfusion_reaction', canonical_mapping.context_scope) > 0
 AND FIND_IN_SET('transfusion_reaction', duplicate_mapping.context_scope) > 0
 AND canonical_mapping.id < duplicate_mapping.id
JOIN billing_services service
  ON service.id = duplicate_mapping.billing_service_id
 AND service.service_code = '00561'
JOIN tests final_test
  ON final_test.id = duplicate_mapping.test_id
 AND final_test.code = 'BACTERIOLOGY'
 AND final_test.is_final_result = 1;

INSERT INTO billing_service_mappings
  (billing_service_id, test_id, blood_component_id, context_scope, active)
SELECT service.id, final_test.id, NULL, 'transfusion_reaction', 1
FROM billing_services service
JOIN tests final_test
  ON final_test.code = 'BACTERIOLOGY'
 AND final_test.status = 'active'
 AND final_test.is_final_result = 1
WHERE service.service_code = '00561'
  AND service.active = 1
  AND NOT EXISTS (
    SELECT 1
    FROM billing_service_mappings existing
    WHERE existing.billing_service_id = service.id
      AND existing.test_id = final_test.id
      AND existing.blood_component_id IS NULL
      AND existing.active = 1
      AND FIND_IN_SET('transfusion_reaction', existing.context_scope) > 0
  );
