-- Dados eletrônicos do recebimento das caixas térmicas.
-- Migration incremental e compatível com registros existentes.
ALTER TABLE sample_shipment_thermal_boxes
    ADD COLUMN IF NOT EXISTS received_temperature DECIMAL(5,2) NULL AFTER box_code,
    ADD COLUMN IF NOT EXISTS received_at DATETIME NULL AFTER received_temperature,
    ADD COLUMN IF NOT EXISTS received_seal VARCHAR(120) NULL AFTER received_at,
    ADD COLUMN IF NOT EXISTS received_by BIGINT UNSIGNED NULL AFTER received_seal;

SET @has_box_receiver_fk := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sample_shipment_thermal_boxes'
      AND CONSTRAINT_NAME = 'fk_shipment_boxes_received_by'
);
SET @sql := IF(
    @has_box_receiver_fk = 0,
    'ALTER TABLE sample_shipment_thermal_boxes ADD CONSTRAINT fk_shipment_boxes_received_by FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

