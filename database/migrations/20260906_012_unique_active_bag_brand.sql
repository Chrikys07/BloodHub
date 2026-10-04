-- Mantém somente a referência ativa mais recente por marca antes de criar a barreira de unicidade.
UPDATE bag_brands older
JOIN bag_brands newer
  ON newer.active = 1
 AND LOWER(TRIM(newer.name)) = LOWER(TRIM(older.name))
 AND (newer.created_at > older.created_at OR (newer.created_at = older.created_at AND newer.id > older.id))
SET older.active = 0
WHERE older.active = 1;

ALTER TABLE bag_brands
    ADD COLUMN IF NOT EXISTS active_brand_key VARCHAR(180)
    GENERATED ALWAYS AS (CASE WHEN active = 1 THEN LOWER(TRIM(name)) ELSE NULL END) STORED;

SET @has_active_brand_unique := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bag_brands'
       AND INDEX_NAME = 'uk_bag_brands_one_active_name'
);
SET @sql := IF(@has_active_brand_unique = 0,
    'ALTER TABLE bag_brands ADD UNIQUE KEY uk_bag_brands_one_active_name (active_brand_key)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
