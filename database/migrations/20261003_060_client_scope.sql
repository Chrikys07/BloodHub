-- Segmentação estrutural de clientes, faturamento e análises de indicadores.
ALTER TABLE clients ADD COLUMN IF NOT EXISTS client_type ENUM('internal','external') NULL AFTER name;
ALTER TABLE billing_cost_centers ADD COLUMN IF NOT EXISTS client_id BIGINT UNSIGNED NULL AFTER unit_id;

SET @has_bcc_client_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='billing_cost_centers' AND CONSTRAINT_NAME='fk_billing_cost_center_client');
SET @sql := IF(@has_bcc_client_fk=0,'ALTER TABLE billing_cost_centers ADD CONSTRAINT fk_billing_cost_center_client FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE SET NULL','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Vínculos inequívocos são copiados; centros sem unidade permanecem pendentes para configuração administrativa.
UPDATE billing_cost_centers b JOIN units u ON u.id=b.unit_id SET b.client_id=u.client_id WHERE b.client_id IS NULL AND u.client_id IS NOT NULL;

ALTER TABLE indicator_analyses ADD COLUMN IF NOT EXISTS client_scope_type ENUM('all','internal','external') NOT NULL DEFAULT 'all' AFTER month;
ALTER TABLE indicator_analyses ADD COLUMN IF NOT EXISTS client_id BIGINT UNSIGNED NULL AFTER client_scope_type;
ALTER TABLE indicator_analyses DROP INDEX IF EXISTS uk_indicator_analysis_period;
ALTER TABLE indicator_analyses ADD COLUMN IF NOT EXISTS scope_key VARCHAR(32) GENERATED ALWAYS AS (COALESCE(CAST(client_id AS CHAR),client_scope_type)) STORED;
ALTER TABLE indicator_analyses ADD UNIQUE KEY IF NOT EXISTS uk_indicator_analysis_scope(indicator_id,year,month,scope_key);
SET @has_ia_client_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='indicator_analyses' AND CONSTRAINT_NAME='fk_indicator_analysis_client');
SET @sql := IF(@has_ia_client_fk=0,'ALTER TABLE indicator_analyses ADD CONSTRAINT fk_indicator_analysis_client FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE RESTRICT','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
