-- Ciclo de vida seguro para configurações históricas.
ALTER TABLE billing_service_mappings
  ADD COLUMN IF NOT EXISTS version INT UNSIGNED NOT NULL DEFAULT 1 AFTER active,
  ADD COLUMN IF NOT EXISTS supersedes_id BIGINT UNSIGNED NULL AFTER version;
ALTER TABLE billing_service_mappings DROP INDEX uk_billing_mapping,
  ADD UNIQUE KEY uk_billing_mapping_version(billing_service_id,test_id,blood_component_id,context_scope,version);

SET @fk_mapping_service := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='billing_service_mappings' AND CONSTRAINT_NAME='fk_billing_mapping_service');
SET @drop_mapping_service := IF(@fk_mapping_service=1,'ALTER TABLE billing_service_mappings DROP FOREIGN KEY fk_billing_mapping_service','SELECT 1');
PREPARE stmt FROM @drop_mapping_service; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE billing_service_mappings ADD CONSTRAINT fk_billing_mapping_service FOREIGN KEY(billing_service_id) REFERENCES billing_services(id) ON DELETE RESTRICT;

SET @fk_mapping_supersedes := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='billing_service_mappings' AND CONSTRAINT_NAME='fk_billing_mapping_supersedes');
SET @add_mapping_supersedes := IF(@fk_mapping_supersedes=0,'ALTER TABLE billing_service_mappings ADD CONSTRAINT fk_billing_mapping_supersedes FOREIGN KEY(supersedes_id) REFERENCES billing_service_mappings(id) ON DELETE RESTRICT','SELECT 1');
PREPARE stmt FROM @add_mapping_supersedes; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE INDEX IF NOT EXISTS idx_billing_mapping_version ON billing_service_mappings(supersedes_id,version);
