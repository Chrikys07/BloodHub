-- Exclusão lógica auditável de metas gerenciais dos indicadores.
ALTER TABLE indicator_targets
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER created_at,
    ADD COLUMN IF NOT EXISTS deleted_by BIGINT UNSIGNED NULL AFTER deleted_at,
    ADD COLUMN IF NOT EXISTS deletion_reason VARCHAR(1000) NULL AFTER deleted_by;

SET @has_fk_indicator_target_deleted_by := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'indicator_targets'
      AND CONSTRAINT_NAME = 'fk_indicator_target_deleted_by'
);
SET @sql_indicator_target_deleted_by := IF(
    @has_fk_indicator_target_deleted_by = 0,
    'ALTER TABLE indicator_targets ADD CONSTRAINT fk_indicator_target_deleted_by FOREIGN KEY(deleted_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_indicator_target_deleted_by FROM @sql_indicator_target_deleted_by;
EXECUTE stmt_indicator_target_deleted_by;
DEALLOCATE PREPARE stmt_indicator_target_deleted_by;

CREATE INDEX IF NOT EXISTS idx_indicator_target_deleted ON indicator_targets(indicator_id, deleted_at, active, effective_from);
