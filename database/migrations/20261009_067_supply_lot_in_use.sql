-- Lote operacional em uso. Não altera nem consome quantidades.
ALTER TABLE supply_lots ADD COLUMN IF NOT EXISTS is_in_use TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
ALTER TABLE supply_lots ADD COLUMN IF NOT EXISTS in_use_unique TINYINT GENERATED ALWAYS AS (CASE WHEN is_in_use=1 THEN 1 ELSE NULL END) STORED;

SET @has_in_use_unique := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='supply_lots' AND INDEX_NAME='uk_supply_lot_in_use');
SET @add_in_use_unique := IF(@has_in_use_unique=0,'ALTER TABLE supply_lots ADD UNIQUE KEY uk_supply_lot_in_use(supply_id,in_use_unique)','SELECT 1');
PREPARE stmt FROM @add_in_use_unique; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_in_use_index := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='supply_lots' AND INDEX_NAME='idx_supply_lots_in_use');
SET @add_in_use_index := IF(@has_in_use_index=0,'ALTER TABLE supply_lots ADD INDEX idx_supply_lots_in_use(is_in_use)','SELECT 1');
PREPARE stmt FROM @add_in_use_index; EXECUTE stmt; DEALLOCATE PREPARE stmt;
