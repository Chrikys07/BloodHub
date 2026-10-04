-- Perfis de tara por referência de bolsa. Incremental, idempotente e não destrutiva.
CREATE TABLE IF NOT EXISTS bag_brand_tares (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bag_brand_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    tare_weight DECIMAL(10,3) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    locked_at DATETIME NULL COMMENT 'Preenchido quando um resultado analítico usar esta tara',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bag_brand_tares_brand FOREIGN KEY (bag_brand_id) REFERENCES bag_brands(id) ON DELETE RESTRICT,
    CONSTRAINT chk_bag_brand_tares_weight CHECK (tare_weight > 0),
    INDEX idx_bag_brand_tares_brand_active (bag_brand_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bag_brand_tare_components (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bag_brand_tare_id BIGINT UNSIGNED NOT NULL,
    blood_component_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bbtc_tare FOREIGN KEY (bag_brand_tare_id) REFERENCES bag_brand_tares(id) ON DELETE CASCADE,
    CONSTRAINT fk_bbtc_component FOREIGN KEY (blood_component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
    UNIQUE KEY uk_bbtc_tare_component (bag_brand_tare_id, blood_component_id),
    INDEX idx_bbtc_component (blood_component_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada tara legada é copiada uma única vez. A coluna original permanece intacta.
INSERT INTO bag_brand_tares (bag_brand_id, name, tare_weight, active)
SELECT b.id, 'Tara principal', b.tare_weight, b.active
FROM bag_brands b
WHERE b.tare_weight IS NOT NULL AND b.tare_weight > 0
  AND NOT EXISTS (SELECT 1 FROM bag_brand_tares t WHERE t.bag_brand_id = b.id);
