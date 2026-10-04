CREATE TABLE IF NOT EXISTS cryoprecipitate_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sample_id BIGINT UNSIGNED NOT NULL UNIQUE,
    gross_weight DECIMAL(10,3) NULL,
    tare_weight_used DECIMAL(10,3) NULL,
    density_used DECIMAL(8,4) NULL,
    volume_ml DECIMAL(12,4) NULL,
    dilution DECIMAL(12,4) NULL,
    fibrinogen_mg_dl DECIMAL(12,4) NULL,
    fibrinogen_mg_u DECIMAL(12,4) NULL,
    recorded_by BIGINT UNSIGNED NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_crio_result_sample FOREIGN KEY (sample_id) REFERENCES samples(id) ON DELETE CASCADE,
    CONSTRAINT fk_crio_result_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
