-- Remessas em lote FC088. Incremental, preserva todas as amostras legadas.
CREATE TABLE IF NOT EXISTS bag_brands (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sample_shipments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_code VARCHAR(80) NOT NULL UNIQUE,
    purpose ENUM('quality_control') NOT NULL DEFAULT 'quality_control',
    origin_unit_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NULL,
    destination_unit_id BIGINT UNSIGNED NOT NULL,
    responsible_user_id BIGINT UNSIGNED NOT NULL,
    checked_by VARCHAR(180) NOT NULL,
    notes TEXT NULL,
    status ENUM('draft','awaiting_receipt','received','rejected','cancelled') NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    sent_by BIGINT UNSIGNED NULL,
    received_at DATETIME NULL,
    received_by BIGINT UNSIGNED NULL,
    rejected_at DATETIME NULL,
    rejected_by BIGINT UNSIGNED NULL,
    rejection_reason VARCHAR(500) NULL,
    cancelled_at DATETIME NULL,
    cancelled_by BIGINT UNSIGNED NULL,
    cancellation_reason VARCHAR(500) NULL,
    CONSTRAINT fk_shipments_origin FOREIGN KEY (origin_unit_id) REFERENCES units(id) ON DELETE RESTRICT,
    CONSTRAINT fk_shipments_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_shipments_destination FOREIGN KEY (destination_unit_id) REFERENCES units(id) ON DELETE RESTRICT,
    CONSTRAINT fk_shipments_responsible FOREIGN KEY (responsible_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_shipments_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_shipments_sent_by FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_shipments_received_by FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_shipments_rejected_by FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_shipments_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_shipments_status_sent (status, sent_at),
    INDEX idx_shipments_origin (origin_unit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sample_shipment_thermal_boxes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_id BIGINT UNSIGNED NOT NULL,
    box_code CHAR(5) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_shipment_boxes_shipment FOREIGN KEY (shipment_id) REFERENCES sample_shipments(id) ON DELETE CASCADE,
    UNIQUE KEY uk_shipment_box (shipment_id, box_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE samples
    ADD COLUMN IF NOT EXISTS sample_shipment_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN IF NOT EXISTS production_date DATE NULL AFTER collection_date,
    ADD COLUMN IF NOT EXISTS bag_brand_id BIGINT UNSIGNED NULL AFTER production_date;

SET @has_shipment_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='samples' AND CONSTRAINT_NAME='fk_samples_shipment');
SET @sql := IF(@has_shipment_fk=0, 'ALTER TABLE samples ADD CONSTRAINT fk_samples_shipment FOREIGN KEY (sample_shipment_id) REFERENCES sample_shipments(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_brand_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='samples' AND CONSTRAINT_NAME='fk_samples_bag_brand');
SET @sql := IF(@has_brand_fk=0, 'ALTER TABLE samples ADD CONSTRAINT fk_samples_bag_brand FOREIGN KEY (bag_brand_id) REFERENCES bag_brands(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO permissions(permission_key,name,module,status) VALUES
('shipments.view','Visualizar remessas','shipments','active'),
('shipments.create','Criar remessas','shipments','active'),
('shipments.edit','Editar remessas antes do recebimento','shipments','active'),
('shipments.send','Finalizar e enviar remessas','shipments','active'),
('shipments.cancel','Cancelar remessas','shipments','active'),
('shipments.print','Imprimir relatório de remessa','shipments','active'),
('admin.bag_brands.manage','Gerenciar marcas de bolsa','admin','active')
ON DUPLICATE KEY UPDATE name=VALUES(name),module=VALUES(module),status='active';
