-- Defesa no banco: uma versão numérica e, no máximo, uma versão ativa por CPAF.
ALTER TABLE cpaf_yield_classification_rules
  DROP FOREIGN KEY fk_cpaf_yield_component,
  ADD CONSTRAINT fk_cpaf_yield_component
    FOREIGN KEY (blood_component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
  ADD COLUMN active_component_guard BIGINT UNSIGNED
    GENERATED ALWAYS AS (CASE WHEN active = 1 THEN blood_component_id ELSE NULL END) STORED,
  ADD UNIQUE KEY uk_cpaf_yield_component_version (blood_component_id, version_number),
  ADD UNIQUE KEY uk_cpaf_yield_single_active (active_component_guard);
