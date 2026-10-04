-- Preservantes, validade configurável e snapshot operacional das amostras.
ALTER TABLE preservatives
    ADD COLUMN IF NOT EXISTS code VARCHAR(60) NULL AFTER id;

CREATE UNIQUE INDEX IF NOT EXISTS uk_preservatives_code ON preservatives(code);

ALTER TABLE bag_brands
    ADD COLUMN IF NOT EXISTS preservative_id BIGINT UNSIGNED NULL AFTER reference_number;

SET @has_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='bag_brands' AND CONSTRAINT_NAME='fk_bag_brands_preservative');
SET @sql := IF(@has_fk=0,'ALTER TABLE bag_brands ADD CONSTRAINT fk_bag_brands_preservative FOREIGN KEY (preservative_id) REFERENCES preservatives(id) ON DELETE SET NULL','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS blood_component_preservative_shelf_lives (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blood_component_id BIGINT UNSIGNED NOT NULL,
    preservative_id BIGINT UNSIGNED NOT NULL,
    shelf_life_days INT UNSIGNED NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bcpsl_component FOREIGN KEY (blood_component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
    CONSTRAINT fk_bcpsl_preservative FOREIGN KEY (preservative_id) REFERENCES preservatives(id) ON DELETE RESTRICT,
    CONSTRAINT chk_bcpsl_days CHECK (shelf_life_days > 0),
    UNIQUE KEY uk_bcpsl_component_preservative (blood_component_id,preservative_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE samples
    ADD COLUMN IF NOT EXISTS preservative_id_snapshot BIGINT UNSIGNED NULL AFTER preservative_id,
    ADD COLUMN IF NOT EXISTS shelf_life_configuration_id_snapshot BIGINT UNSIGNED NULL AFTER preservative_id_snapshot,
    ADD COLUMN IF NOT EXISTS shelf_life_days_snapshot INT UNSIGNED NULL AFTER shelf_life_configuration_id_snapshot;

SET @has_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='samples' AND CONSTRAINT_NAME='fk_samples_preservative_snapshot');
SET @sql := IF(@has_fk=0,'ALTER TABLE samples ADD CONSTRAINT fk_samples_preservative_snapshot FOREIGN KEY (preservative_id_snapshot) REFERENCES preservatives(id) ON DELETE SET NULL','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='samples' AND CONSTRAINT_NAME='fk_samples_shelf_life_snapshot');
SET @sql := IF(@has_fk=0,'ALTER TABLE samples ADD CONSTRAINT fk_samples_shelf_life_snapshot FOREIGN KEY (shelf_life_configuration_id_snapshot) REFERENCES blood_component_preservative_shelf_lives(id) ON DELETE SET NULL','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
