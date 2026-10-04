-- Recebimento parcial por amostra, temperaturas e componentes por caixa.
ALTER TABLE sample_shipments MODIFY status ENUM('draft','awaiting_receipt','partially_received','received','rejected','cancelled') NOT NULL DEFAULT 'draft';

ALTER TABLE sample_shipment_thermal_boxes
    ADD COLUMN IF NOT EXISTS sent_temperature DECIMAL(5,2) NULL AFTER box_code;

CREATE TABLE IF NOT EXISTS sample_shipment_thermal_box_components (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thermal_box_id BIGINT UNSIGNED NOT NULL,
    blood_component_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_thermal_box_components_box FOREIGN KEY (thermal_box_id) REFERENCES sample_shipment_thermal_boxes(id) ON DELETE CASCADE,
    CONSTRAINT fk_thermal_box_components_component FOREIGN KEY (blood_component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
    UNIQUE KEY uk_thermal_box_component (thermal_box_id,blood_component_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions(permission_key,name,module)
VALUES ('reception.edit_sample_data','Corrigir dados de amostras no recebimento','reception');
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.permission_key='reception.edit_sample_data'
WHERE r.slug='administrador';
