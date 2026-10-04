ALTER TABLE blood_component_test_specifications
    ADD COLUMN IF NOT EXISTS supersedes_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN IF NOT EXISTS version_number INT UNSIGNED NOT NULL DEFAULT 1 AFTER supersedes_id,
    ADD INDEX IF NOT EXISTS idx_qspec_family (blood_component_id,test_id,condition_type,preservative_id,effective_from,effective_to),
    ADD CONSTRAINT fk_qspec_supersedes FOREIGN KEY (supersedes_id) REFERENCES blood_component_test_specifications(id) ON DELETE SET NULL;

ALTER TABLE test_result_spec_evaluations
    ADD COLUMN IF NOT EXISTS condition_type VARCHAR(50) NULL AFTER unit,
    ADD COLUMN IF NOT EXISTS condition_value VARCHAR(255) NULL AFTER condition_type,
    ADD COLUMN IF NOT EXISTS preservative_id BIGINT UNSIGNED NULL AFTER condition_value;
