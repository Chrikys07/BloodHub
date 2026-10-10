-- Altera a identidade operacional de marca de bolsa para Marca + Preservante.
-- Não altera, funde, inativa ou exclui registros existentes.

CREATE OR REPLACE VIEW bag_brand_active_duplicates AS
SELECT LOWER(TRIM(b.name)) AS normalized_name,
       b.preservative_id,
       COUNT(*) AS duplicate_count,
       GROUP_CONCAT(b.id ORDER BY b.id) AS bag_brand_ids
  FROM bag_brands b
 WHERE b.active = 1
 GROUP BY LOWER(TRIM(b.name)), b.preservative_id
HAVING COUNT(*) > 1;

SET @has_old_reference_unique := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bag_brands'
       AND INDEX_NAME='uk_bag_brands_name_reference'
);
SET @sql := IF(@has_old_reference_unique > 0,
    'ALTER TABLE bag_brands DROP INDEX uk_bag_brands_name_reference', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_old_active_unique := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bag_brands'
       AND INDEX_NAME='uk_bag_brands_one_active_name'
);
SET @sql := IF(@has_old_active_unique > 0,
    'ALTER TABLE bag_brands DROP INDEX uk_bag_brands_one_active_name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE bag_brands
    DROP COLUMN active_brand_key,
    ADD COLUMN active_brand_key VARCHAR(220)
      GENERATED ALWAYS AS (
        CASE WHEN active=1 AND preservative_id IS NOT NULL
             THEN CONCAT(LOWER(TRIM(name)), '#', preservative_id)
             ELSE NULL END
      ) STORED;

SET @active_duplicates := (SELECT COUNT(*) FROM bag_brand_active_duplicates);
SET @sql := IF(@active_duplicates = 0,
    'ALTER TABLE bag_brands ADD UNIQUE KEY uk_bag_brands_active_name_preservative (active_brand_key)',
    'SELECT ''Índice único adiado: consulte bag_brand_active_duplicates para saneamento manual.'' AS warning');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

