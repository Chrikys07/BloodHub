-- Evolui marcas de bolsa para referências versionadas, preservando registros legados.
ALTER TABLE bag_brands
    ADD COLUMN IF NOT EXISTS reference_number VARCHAR(120) NULL AFTER name,
    ADD COLUMN IF NOT EXISTS tare_weight DECIMAL(10,3) NULL AFTER reference_number;

-- A estrutura original tornava o nome isoladamente único. Remove somente esse índice.
SET @name_unique_index := (
    SELECT INDEX_NAME
      FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'bag_brands'
       AND NON_UNIQUE = 0
       AND INDEX_NAME <> 'PRIMARY'
     GROUP BY INDEX_NAME
    HAVING COUNT(*) = 1 AND MAX(COLUMN_NAME) = 'name'
     LIMIT 1
);
SET @sql := IF(@name_unique_index IS NULL, 'SELECT 1', CONCAT('ALTER TABLE bag_brands DROP INDEX `', REPLACE(@name_unique_index, '`', '``'), '`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_brand_reference_unique := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bag_brands'
       AND INDEX_NAME = 'uk_bag_brands_name_reference'
);
SET @sql := IF(@has_brand_reference_unique = 0,
    'ALTER TABLE bag_brands ADD UNIQUE KEY uk_bag_brands_name_reference (name, reference_number)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
