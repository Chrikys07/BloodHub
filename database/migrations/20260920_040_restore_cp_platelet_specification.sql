-- Corrige a vigência terminal criada acidentalmente para Plaquetas/U do CP.
-- A regra e seus valores permanecem intactos; somente o fim de vigência espúrio
-- é removido quando não existe versão sucessora.
UPDATE blood_component_test_specifications sp
JOIN blood_components bc ON bc.id=sp.blood_component_id AND bc.code='CP'
JOIN tests t ON t.id=sp.test_id AND t.code='PLATELETS_PER_UNIT'
SET sp.effective_to=NULL
WHERE sp.active=1
  AND sp.effective_to=sp.effective_from
  AND NOT EXISTS (
      SELECT 1
      FROM blood_component_test_specifications successor
      WHERE successor.supersedes_id=sp.id
  );
