SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(160) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    module VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    client_type ENUM('internal','external') NOT NULL,
    document VARCHAR(40) NULL,
    email VARCHAR(180) NULL,
    phone VARCHAR(40) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_clients_type (client_type),
    INDEX idx_clients_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS units (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NULL,
    name VARCHAR(180) NOT NULL,
    unit_type ENUM('lcqh','processing','transfusion_agency','management','other') NOT NULL,
    code VARCHAR(60) NULL,
    email VARCHAR(255) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_units_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    INDEX idx_units_type (unit_type),
    INDEX idx_units_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NULL,
    client_id BIGINT UNSIGNED NULL,
    primary_unit_id BIGINT UNSIGNED NULL,
    name VARCHAR(180) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    photo_path VARCHAR(255) NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_primary_unit FOREIGN KEY (primary_unit_id) REFERENCES units(id) ON DELETE SET NULL,
    INDEX idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_units (
    user_id BIGINT UNSIGNED NOT NULL,
    unit_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, unit_id),
    CONSTRAINT fk_user_units_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_units_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blood_components (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(180) NOT NULL,
    density DECIMAL(8,4) NULL,
    transport_temperature_min DECIMAL(5,2) NULL,
    transport_temperature_max DECIMAL(5,2) NULL,
    description TEXT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NULL UNIQUE,
    name VARCHAR(180) NOT NULL,
    result_type ENUM('numeric','text','select','boolean','positive_negative') NOT NULL,
    unit VARCHAR(80) NULL,
    allows_ad_hoc TINYINT(1) NOT NULL DEFAULT 0,
    is_final_result TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dashboard_conformity_targets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blood_component_id BIGINT UNSIGNED NOT NULL,
    test_id BIGINT UNSIGNED NOT NULL,
    minimum_percentage DECIMAL(5,2) NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_dashboard_target_component FOREIGN KEY(blood_component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
    CONSTRAINT fk_dashboard_target_test FOREIGN KEY(test_id) REFERENCES tests(id) ON DELETE RESTRICT,
    CONSTRAINT fk_dashboard_target_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT chk_dashboard_target_percentage CHECK(minimum_percentage BETWEEN 0 AND 100),
    UNIQUE KEY uk_dashboard_target_version(blood_component_id,test_id,effective_from),
    INDEX idx_dashboard_target_lookup(blood_component_id,test_id,active,effective_from,effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_blood_components (
    test_id BIGINT UNSIGNED NOT NULL,
    blood_component_id BIGINT UNSIGNED NOT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (test_id, blood_component_id),
    CONSTRAINT fk_tbc_test FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
    CONSTRAINT fk_tbc_component FOREIGN KEY (blood_component_id) REFERENCES blood_components(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    manufacturer VARCHAR(180) NULL,
    internal_code VARCHAR(80) NULL,
    unit_of_measure VARCHAR(40) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supply_lots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supply_id BIGINT UNSIGNED NOT NULL,
    lot_number VARCHAR(120) NOT NULL,
    expiration_date DATE NOT NULL,
    received_at DATE NULL,
    quantity_initial DECIMAL(14,4) NULL,
    quantity_available DECIMAL(14,4) NULL,
    status ENUM('active','inactive','exhausted','blocked') NOT NULL DEFAULT 'active',
    is_in_use TINYINT(1) NOT NULL DEFAULT 0,
    in_use_unique TINYINT GENERATED ALWAYS AS (CASE WHEN is_in_use=1 THEN 1 ELSE NULL END) STORED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_supply_lots_supply FOREIGN KEY (supply_id) REFERENCES supplies(id) ON DELETE RESTRICT,
    UNIQUE KEY uk_supply_lot (supply_id, lot_number),
    UNIQUE KEY uk_supply_lot_in_use (supply_id, in_use_unique),
    INDEX idx_supply_lots_in_use (is_in_use),
    INDEX idx_supply_lots_expiration (expiration_date),
    INDEX idx_supply_lots_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_supplies (
    test_id BIGINT UNSIGNED NOT NULL,
    supply_id BIGINT UNSIGNED NOT NULL,
    quantity_required DECIMAL(14,4) NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (test_id, supply_id),
    CONSTRAINT fk_test_supplies_test FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
    CONSTRAINT fk_test_supplies_supply FOREIGN KEY (supply_id) REFERENCES supplies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bag_brands (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    reference_number VARCHAR(120) NULL,
    preservative_id BIGINT UNSIGNED NULL,
    tare_weight DECIMAL(10,3) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    active_brand_key VARCHAR(220) GENERATED ALWAYS AS (CASE WHEN active = 1 AND preservative_id IS NOT NULL THEN CONCAT(LOWER(TRIM(name)), '#', preservative_id) ELSE NULL END) STORED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_bag_brands_active_name_preservative (active_brand_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS preservatives (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(60) NULL UNIQUE,
    name VARCHAR(180) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS sample_shipments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_code VARCHAR(80) NOT NULL UNIQUE,
    purpose ENUM('quality_control','validation','single_assessment') NOT NULL DEFAULT 'quality_control',
    origin_unit_id BIGINT UNSIGNED NOT NULL, client_id BIGINT UNSIGNED NULL,
    destination_unit_id BIGINT UNSIGNED NOT NULL, responsible_user_id BIGINT UNSIGNED NOT NULL,
    checked_by VARCHAR(180) NOT NULL, notes TEXT NULL,
    status ENUM('draft','awaiting_receipt','partially_received','received','rejected','cancelled') NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sent_at DATETIME NULL, sent_by BIGINT UNSIGNED NULL, received_at DATETIME NULL, received_by BIGINT UNSIGNED NULL,
    rejected_at DATETIME NULL, rejected_by BIGINT UNSIGNED NULL, rejection_reason VARCHAR(500) NULL,
    cancelled_at DATETIME NULL, cancelled_by BIGINT UNSIGNED NULL, cancellation_reason VARCHAR(500) NULL,
    CONSTRAINT fk_shipments_origin FOREIGN KEY(origin_unit_id) REFERENCES units(id) ON DELETE RESTRICT,
    CONSTRAINT fk_shipments_client FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_shipments_destination FOREIGN KEY(destination_unit_id) REFERENCES units(id) ON DELETE RESTRICT,
    CONSTRAINT fk_shipments_responsible FOREIGN KEY(responsible_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_shipments_created_by FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_shipments_sent_by FOREIGN KEY(sent_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_shipments_received_by FOREIGN KEY(received_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_shipments_rejected_by FOREIGN KEY(rejected_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_shipments_cancelled_by FOREIGN KEY(cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_shipments_status_sent(status,sent_at), INDEX idx_shipments_origin(origin_unit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sample_shipment_thermal_boxes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, shipment_id BIGINT UNSIGNED NOT NULL,
    box_code CHAR(5) NOT NULL,
    transport_temp_min_snapshot DECIMAL(5,2) NULL,
    transport_temp_max_snapshot DECIMAL(5,2) NULL,
    sent_temperature DECIMAL(5,2) NULL,
    received_temperature DECIMAL(5,2) NULL,
    received_at DATETIME NULL,
    received_seal VARCHAR(120) NULL,
    received_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_shipment_boxes_shipment FOREIGN KEY(shipment_id) REFERENCES sample_shipments(id) ON DELETE CASCADE,
    CONSTRAINT fk_shipment_boxes_received_by FOREIGN KEY(received_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uk_shipment_box(shipment_id,box_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sample_shipment_thermal_box_components (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thermal_box_id BIGINT UNSIGNED NOT NULL,
    blood_component_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_thermal_box_components_box FOREIGN KEY (thermal_box_id) REFERENCES sample_shipment_thermal_boxes(id) ON DELETE CASCADE,
    CONSTRAINT fk_thermal_box_components_component FOREIGN KEY (blood_component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
    UNIQUE KEY uk_thermal_box_component (thermal_box_id,blood_component_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS samples (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sample_shipment_id BIGINT UNSIGNED NULL,
    sample_code VARCHAR(80) NOT NULL UNIQUE,
    lcqh_code VARCHAR(80) NULL,
    purpose ENUM('quality_control','validation','single_assessment','transfusion_reaction','other') NOT NULL,
    origin_unit_id BIGINT UNSIGNED NULL,
    client_id BIGINT UNSIGNED NULL,
    blood_component_id BIGINT UNSIGNED NULL,
    donation_number VARCHAR(120) NULL,
    patient_name VARCHAR(180) NULL,
    collection_date DATE NULL,
    production_date DATE NULL,
    bag_brand_id BIGINT UNSIGNED NULL,
    preservative_id BIGINT UNSIGNED NULL,
    preservative_id_snapshot BIGINT UNSIGNED NULL,
    shelf_life_configuration_id_snapshot BIGINT UNSIGNED NULL,
    shelf_life_days_snapshot INT UNSIGNED NULL,
    expiration_date DATE NULL,
    status ENUM('registered','sent','awaiting_receipt','received','rejected','in_analysis','partial_results','completed','cancelled') NOT NULL DEFAULT 'registered',
    registered_by BIGINT UNSIGNED NULL,
    received_by BIGINT UNSIGNED NULL,
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    sent_by BIGINT UNSIGNED NULL,
    received_at DATETIME NULL,
    rejection_reason VARCHAR(500) NULL,
    rejected_at DATETIME NULL,
    rejected_by BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    CONSTRAINT fk_samples_origin_unit FOREIGN KEY (origin_unit_id) REFERENCES units(id) ON DELETE SET NULL,
    CONSTRAINT fk_samples_shipment FOREIGN KEY (sample_shipment_id) REFERENCES sample_shipments(id) ON DELETE SET NULL,
    CONSTRAINT fk_samples_bag_brand FOREIGN KEY (bag_brand_id) REFERENCES bag_brands(id) ON DELETE SET NULL,
    CONSTRAINT fk_samples_preservative FOREIGN KEY (preservative_id) REFERENCES preservatives(id) ON DELETE SET NULL,
    CONSTRAINT fk_samples_preservative_snapshot FOREIGN KEY (preservative_id_snapshot) REFERENCES preservatives(id) ON DELETE SET NULL,
    CONSTRAINT fk_samples_shelf_life_snapshot FOREIGN KEY (shelf_life_configuration_id_snapshot) REFERENCES blood_component_preservative_shelf_lives(id) ON DELETE SET NULL,
    CONSTRAINT fk_samples_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_samples_component FOREIGN KEY (blood_component_id) REFERENCES blood_components(id) ON DELETE SET NULL,
    CONSTRAINT fk_samples_registered_by FOREIGN KEY (registered_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_samples_received_by FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_samples_sent_by FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_samples_rejected_by FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_samples_purpose (purpose),
    INDEX idx_samples_status (status),
    INDEX idx_samples_donation_number (donation_number),
    INDEX idx_samples_dashboard (purpose,blood_component_id,production_date,origin_unit_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sample_tests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sample_id BIGINT UNSIGNED NOT NULL,
    test_id BIGINT UNSIGNED NULL,
    ad_hoc_test_name VARCHAR(180) NULL,
    status ENUM('pending','in_progress','completed','blocked','cancelled') NOT NULL DEFAULT 'pending',
    blocked_reason VARCHAR(255) NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    executed_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sample_tests_sample FOREIGN KEY (sample_id) REFERENCES samples(id) ON DELETE CASCADE,
    CONSTRAINT fk_sample_tests_test FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE SET NULL,
    CONSTRAINT fk_sample_tests_user FOREIGN KEY (executed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_sample_tests_status (status),
    UNIQUE KEY uk_sample_tests_sample_test (sample_id,test_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sample_test_id BIGINT UNSIGNED NOT NULL,
    result_value_text TEXT NULL,
    result_value_numeric DECIMAL(20,8) NULL,
    conformity ENUM('conforming','nonconforming','not_applicable','pending') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    recorded_by BIGINT UNSIGNED NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_test_results_sample_test FOREIGN KEY (sample_test_id) REFERENCES sample_tests(id) ON DELETE CASCADE,
    CONSTRAINT fk_test_results_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_result_supplies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    test_result_id BIGINT UNSIGNED NOT NULL,
    supply_id BIGINT UNSIGNED NOT NULL,
    supply_lot_id BIGINT UNSIGNED NOT NULL,
    supply_name_snapshot VARCHAR(180) NOT NULL,
    lot_number_snapshot VARCHAR(120) NOT NULL,
    expiration_date_snapshot DATE NOT NULL,
    quantity_used DECIMAL(14,4) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_trs_result FOREIGN KEY (test_result_id) REFERENCES test_results(id) ON DELETE CASCADE,
    CONSTRAINT fk_trs_supply FOREIGN KEY (supply_id) REFERENCES supplies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_trs_lot FOREIGN KEY (supply_lot_id) REFERENCES supply_lots(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sample_weight_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sample_id BIGINT UNSIGNED NOT NULL UNIQUE,
    gross_weight DECIMAL(10,3) NOT NULL,
    bag_brand_tare_id BIGINT UNSIGNED NOT NULL,
    tare_weight_used DECIMAL(10,3) NOT NULL,
    net_weight DECIMAL(10,3) NOT NULL,
    density_used DECIMAL(8,4) NULL,
    volume_ml DECIMAL(12,4) NULL,
    recorded_by BIGINT UNSIGNED NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_swr_sample FOREIGN KEY (sample_id) REFERENCES samples(id) ON DELETE CASCADE,
    CONSTRAINT fk_swr_tare FOREIGN KEY (bag_brand_tare_id) REFERENCES bag_brand_tares(id) ON DELETE RESTRICT,
    CONSTRAINT fk_swr_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS test_result_parameters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    test_result_id BIGINT UNSIGNED NOT NULL,
    parameter_code VARCHAR(80) NOT NULL,
    parameter_name VARCHAR(180) NOT NULL,
    numeric_value DECIMAL(20,8) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_trp_result FOREIGN KEY (test_result_id) REFERENCES test_results(id) ON DELETE CASCADE,
    UNIQUE KEY uk_trp_result_code (test_result_id, parameter_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blood_component_test_specifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, supersedes_id BIGINT UNSIGNED NULL, version_number INT UNSIGNED NOT NULL DEFAULT 1, blood_component_id BIGINT UNSIGNED NOT NULL, test_id BIGINT UNSIGNED NOT NULL,
    rule_type ENUM('GT','GTE','LT','LTE','BETWEEN','EQUAL_NUMERIC','EQUAL_TEXT','BOOLEAN') NOT NULL,
    min_value DECIMAL(30,10) NULL, max_value DECIMAL(30,10) NULL, expected_text VARCHAR(255) NULL, unit VARCHAR(80) NULL,
    preservative_id BIGINT UNSIGNED NULL, condition_type ENUM('PRESERVATIVE','PROCESS_METHOD','STORAGE_DAY','LEUKOREDUCED','PATHOGEN_REDUCTION','PRE_STORAGE_LEUKOREDUCTION','POOL_TYPE') NULL, condition_value VARCHAR(255) NULL,
    effective_from DATE NULL, effective_to DATE NULL, source_name VARCHAR(255) NULL, source_reference VARCHAR(500) NULL, notes TEXT NULL, sampling_requirement_notes TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_qspec_supersedes FOREIGN KEY(supersedes_id) REFERENCES blood_component_test_specifications(id) ON DELETE SET NULL, CONSTRAINT fk_qspec_component FOREIGN KEY(blood_component_id) REFERENCES blood_components(id) ON DELETE CASCADE, CONSTRAINT fk_qspec_test FOREIGN KEY(test_id) REFERENCES tests(id) ON DELETE CASCADE,
    CONSTRAINT fk_qspec_preservative FOREIGN KEY(preservative_id) REFERENCES preservatives(id) ON DELETE RESTRICT, CONSTRAINT fk_qspec_created_by FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL, CONSTRAINT fk_qspec_updated_by FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_qspec_lookup(blood_component_id,test_id,active,effective_from,effective_to), INDEX idx_qspec_family(blood_component_id,test_id,condition_type,preservative_id,effective_from,effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_result_spec_evaluations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, test_result_id BIGINT UNSIGNED NOT NULL, specification_id BIGINT UNSIGNED NULL, rule_type VARCHAR(30) NULL,
    expected_min DECIMAL(30,10) NULL, expected_max DECIMAL(30,10) NULL, expected_text VARCHAR(255) NULL, unit VARCHAR(80) NULL, condition_type VARCHAR(50) NULL, condition_value VARCHAR(255) NULL, preservative_id BIGINT UNSIGNED NULL, actual_value VARCHAR(255) NULL,
    conformity_status ENUM('CONFORMING','NONCONFORMING','NO_SPECIFICATION','NOT_EVALUATED') NOT NULL, evaluated_at DATETIME NOT NULL, specification_snapshot_text TEXT NULL,
    acknowledged_by BIGINT UNSIGNED NULL, acknowledged_at DATETIME NULL,
    CONSTRAINT fk_qeval_result FOREIGN KEY(test_result_id) REFERENCES test_results(id) ON DELETE CASCADE, CONSTRAINT fk_qeval_spec FOREIGN KEY(specification_id) REFERENCES blood_component_test_specifications(id) ON DELETE SET NULL, CONSTRAINT fk_qeval_ack FOREIGN KEY(acknowledged_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uk_qeval_result(test_result_id), INDEX idx_qeval_status(conformity_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bacteriology_pools (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(80) NOT NULL UNIQUE,
 component_id BIGINT UNSIGNED NOT NULL, origin_unit_id BIGINT UNSIGNED NOT NULL, client_id BIGINT UNSIGNED NULL,
 start_date DATE NOT NULL, end_date DATE NOT NULL, result ENUM('negative','positive') NULL,
 status ENUM('open','completed_negative','completed_positive','cancelled') NOT NULL DEFAULT 'open',
 created_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 resulted_by BIGINT UNSIGNED NULL, resulted_at DATETIME NULL, cancelled_by BIGINT UNSIGNED NULL,
 cancelled_at DATETIME NULL, cancellation_reason VARCHAR(500) NULL, notes TEXT NULL,
 CONSTRAINT fk_bact_pool_component FOREIGN KEY(component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bact_pool_origin FOREIGN KEY(origin_unit_id) REFERENCES units(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bact_pool_client FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE SET NULL,
 CONSTRAINT fk_bact_pool_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_bact_pool_result_user FOREIGN KEY(resulted_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_bact_pool_cancel_user FOREIGN KEY(cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_bact_pool_status(status), INDEX idx_bact_pool_group(component_id,origin_unit_id,client_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bacteriology_pool_members (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, pool_id BIGINT UNSIGNED NOT NULL, sample_id BIGINT UNSIGNED NOT NULL,
 position TINYINT UNSIGNED NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1,
 active_sample_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN active=1 THEN sample_id ELSE NULL END) STORED,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, released_at DATETIME NULL,
 CONSTRAINT fk_bact_member_pool FOREIGN KEY(pool_id) REFERENCES bacteriology_pools(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bact_member_sample FOREIGN KEY(sample_id) REFERENCES samples(id) ON DELETE RESTRICT,
 CONSTRAINT chk_bact_member_position CHECK(position BETWEEN 1 AND 4),
 UNIQUE KEY uk_bact_pool_position(pool_id,position), UNIQUE KEY uk_bact_pool_sample(pool_id,sample_id),
 UNIQUE KEY uk_bact_active_sample(active_sample_id), INDEX idx_bact_member_sample(sample_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bacteriology_results (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sample_id BIGINT UNSIGNED NOT NULL,
 stage ENUM('individual_initial','pool_screening','post_pool_individual','retest') NOT NULL,
 source_type ENUM('individual','pool','retest') NOT NULL, pool_id BIGINT UNSIGNED NULL,
 result ENUM('negative','positive') NOT NULL, identified_bacteria VARCHAR(255) NULL, is_final TINYINT(1) NOT NULL DEFAULT 0,
 bacteriological_conformity ENUM('conforming','nonconforming','pending') NOT NULL DEFAULT 'pending', workflow_status ENUM('registered','retest_pending','identification_pending','completed') NOT NULL DEFAULT 'registered',
 performed_by BIGINT UNSIGNED NULL, performed_at DATETIME NOT NULL, notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_bact_result_sample FOREIGN KEY(sample_id) REFERENCES samples(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bact_result_pool FOREIGN KEY(pool_id) REFERENCES bacteriology_pools(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bact_result_user FOREIGN KEY(performed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_bact_result_sample_final(sample_id,is_final), INDEX idx_bact_result_pool(pool_id), INDEX idx_bact_result_workflow(workflow_status,stage,result)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bacteriology_retests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sample_id BIGINT UNSIGNED NOT NULL,
 initial_result_id BIGINT UNSIGNED NOT NULL, status ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
 result ENUM('negative','positive') NULL, result_id BIGINT UNSIGNED NULL,
 created_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 completed_by BIGINT UNSIGNED NULL, completed_at DATETIME NULL, notes TEXT NULL,
 CONSTRAINT fk_bact_retest_sample FOREIGN KEY(sample_id) REFERENCES samples(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bact_retest_initial FOREIGN KEY(initial_result_id) REFERENCES bacteriology_results(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bact_retest_result FOREIGN KEY(result_id) REFERENCES bacteriology_results(id) ON DELETE SET NULL,
 CONSTRAINT fk_bact_retest_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_bact_retest_completer FOREIGN KEY(completed_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uk_bact_retest_initial(initial_result_id), INDEX idx_bact_retest_status(status,sample_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bacteriology_positive_samples (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, bacteriology_result_id BIGINT UNSIGNED NOT NULL,
 sample_id BIGINT UNSIGNED NOT NULL, client_id BIGINT UNSIGNED NULL, donation_number VARCHAR(120) NOT NULL, send_date DATE NULL,
 collection_date DATE NULL, origin VARCHAR(180) NULL, client VARCHAR(180) NULL,
 status ENUM('pending','in_progress','completed') NOT NULL DEFAULT 'pending', conclusion TEXT NULL,
 created_by BIGINT UNSIGNED NULL, completed_by BIGINT UNSIGNED NULL, completed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_bact_positive_result FOREIGN KEY(bacteriology_result_id) REFERENCES bacteriology_results(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bact_positive_sample FOREIGN KEY(sample_id) REFERENCES samples(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bact_positive_client FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE SET NULL,
 CONSTRAINT fk_bact_positive_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_bact_positive_completer FOREIGN KEY(completed_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uk_bact_positive_result(bacteriology_result_id), KEY idx_bact_positive_filters(donation_number,status,send_date), KEY idx_bact_positive_client(client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bacteriology_positive_sample_records (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, positive_sample_id BIGINT UNSIGNED NOT NULL,
 position SMALLINT UNSIGNED NOT NULL, include_in_form TINYINT(1) NOT NULL DEFAULT 1,
 send_date DATE NULL, donation_number VARCHAR(120) NOT NULL, blood_component_id BIGINT UNSIGNED NULL, hemocomponent VARCHAR(180) NULL,
 collection_date DATE NULL, origin VARCHAR(180) NULL, situation VARCHAR(180) NULL, storage_unit_id BIGINT UNSIGNED NULL,
 storage_location VARCHAR(180) NULL, reaction VARCHAR(180) NULL, perform_bacteriology TINYINT(1) NOT NULL DEFAULT 1, test_name VARCHAR(180) NULL,
 bacteriology_date DATE NULL, bacteriology_result VARCHAR(120) NULL, retest_result ENUM('negative','positive') NULL, identified_bacteria_initial VARCHAR(255) NULL, identified_bacteria VARCHAR(255) NULL, identified_bacteria_retest VARCHAR(255) NULL, operational_notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_bact_positive_record_parent FOREIGN KEY(positive_sample_id) REFERENCES bacteriology_positive_samples(id) ON DELETE CASCADE,
 CONSTRAINT fk_bact_positive_record_component FOREIGN KEY(blood_component_id) REFERENCES blood_components(id) ON DELETE SET NULL,
 CONSTRAINT fk_bact_positive_record_storage FOREIGN KEY(storage_unit_id) REFERENCES units(id) ON DELETE SET NULL,
 UNIQUE KEY uk_bact_positive_position(positive_sample_id,position), KEY idx_bact_positive_record_component(blood_component_id), KEY idx_bact_positive_record_storage(storage_unit_id), KEY idx_bact_positive_record_retest(retest_result)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bacteriology_positive_record_tests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, positive_record_id BIGINT UNSIGNED NOT NULL,
 sequence SMALLINT UNSIGNED NOT NULL, attempt_type ENUM('initial','retest') NOT NULL DEFAULT 'initial',
 status ENUM('pending','completed') NOT NULL DEFAULT 'pending', result ENUM('negative','positive') NULL,
 identified_bacteria VARCHAR(255) NULL, tested_at DATETIME NULL, notes TEXT NULL, performed_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_bact_positive_test_record FOREIGN KEY(positive_record_id) REFERENCES bacteriology_positive_sample_records(id) ON DELETE CASCADE,
 CONSTRAINT fk_bact_positive_test_user FOREIGN KEY(performed_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uk_bact_positive_test_attempt(positive_record_id,sequence), KEY idx_bact_positive_test_status(positive_record_id,status,attempt_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(160) NOT NULL,
    entity_type VARCHAR(120) NULL,
    entity_id BIGINT UNSIGNED NULL,
    before_data JSON NULL,
    after_data JSON NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_logs_created_at (created_at),
    INDEX idx_audit_logs_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('general','group','private') NOT NULL,
    name VARCHAR(180) NULL,
    slug VARCHAR(80) NULL UNIQUE,
    private_key VARCHAR(80) NULL UNIQUE,
    unit_id BIGINT UNSIGNED NULL UNIQUE,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_chat_conversations_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_chat_conversations_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    INDEX idx_chat_conversations_updated (updated_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_participants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    participant_source ENUM('unit','external') NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_read_at DATETIME(6) NULL,
    archived_at DATETIME(6) NULL,
    CONSTRAINT fk_chat_participants_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_participants_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_chat_participant (conversation_id, user_id),
    INDEX idx_chat_participants_user (user_id, conversation_id),
    INDEX idx_chat_participants_archived (user_id, archived_at, conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    sender_id BIGINT UNSIGNED NOT NULL,
    message VARCHAR(4000) NOT NULL,
    related_type VARCHAR(80) NULL,
    related_id BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_chat_messages_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_chat_messages_timeline (conversation_id, created_at, id),
    INDEX idx_chat_messages_context (related_type, related_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (id, name, slug, description) VALUES
(1, 'Administrador', 'administrador', 'Acesso administrativo ao sistema'),
(2, 'LCQH', 'lcqh', 'Laboratório de Controle de Qualidade de Hemocomponentes'),
(3, 'Processamento', 'processamento', 'Unidades de processamento'),
(4, 'Agência Transfusional', 'agencia-transfusional', 'Cadastro e acompanhamento de reações transfusionais'),
(5, 'Gestão', 'gestao', 'Acesso consultivo ao Dashboard Global');

INSERT IGNORE INTO permissions (permission_key, name, module) VALUES
('dashboard.global.view', 'Visualizar Dashboard Global', 'dashboard'),
('dashboard.operational.view', 'Visualizar Dashboard Operacional', 'dashboard'),
('samples.create', 'Cadastrar amostras', 'samples'),
('samples.view', 'Visualizar amostras', 'samples'),
('samples.edit', 'Editar amostras cadastradas', 'samples'),
('samples.send', 'Enviar amostras ao LCQH', 'samples'),
('samples.scope.global', 'Acessar amostras de todas as unidades', 'samples'),
('shipments.view', 'Visualizar remessas', 'shipments'),
('shipments.create', 'Criar remessas', 'shipments'),
('shipments.edit', 'Editar remessas antes do recebimento', 'shipments'),
('shipments.send', 'Finalizar e enviar remessas', 'shipments'),
('shipments.cancel', 'Cancelar remessas', 'shipments'),
('shipments.print', 'Imprimir relatório de remessa', 'shipments'),
('reception.view', 'Visualizar fila de recebimento', 'reception'),
('reception.receive', 'Receber amostras', 'reception'),
('reception.reject', 'Recusar amostras', 'reception'),
('reception.edit_sample_data', 'Corrigir dados de amostras no recebimento', 'reception'),
('quality_control.results.manage', 'Gerenciar resultados de Controle de Qualidade', 'quality_control'),
('quality.bacteriology.view', 'Visualizar Bacteriológico', 'quality_results'),
('quality.bacteriology.edit', 'Registrar resultados bacteriológicos', 'quality_results'),
('quality.bacteriology.pool', 'Gerenciar pools bacteriológicos', 'quality_results'),
('validations.manage', 'Gerenciar validações', 'validations'),
('transfusion_reactions.manage', 'Gerenciar reações transfusionais', 'transfusion_reactions'),
('transfusion_reaction_consultation.view', 'Consultar reações transfusionais', 'transfusion_reaction_consultation'),
('notifications.manage', 'Gerenciar notificações', 'notifications'),
('admin.users.manage', 'Gerenciar usuários', 'admin'),
('admin.roles.manage', 'Gerenciar perfis', 'admin'),
('admin.permissions.manage', 'Gerenciar permissões', 'admin'),
('admin.clients.manage', 'Gerenciar clientes', 'admin'),
('admin.units.manage', 'Gerenciar unidades', 'admin'),
('admin.blood_components.manage', 'Gerenciar hemocomponentes', 'admin'),
('admin.bag_brands.manage', 'Gerenciar marcas de bolsa', 'admin'),
('admin.tests.manage', 'Gerenciar testes', 'admin'),
('admin.supplies.manage', 'Gerenciar insumos e lotes', 'admin');

INSERT IGNORE INTO permissions (permission_key, name, module) VALUES
('chat.view', 'Visualizar HubChat', 'chat'),
('chat.send', 'Enviar mensagens no HubChat', 'chat'),
('chat.private', 'Iniciar conversa privada', 'chat'),
('chat.group', 'Participar de grupos do HubChat', 'chat');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.status='active' AND p.permission_key IN ('chat.view','chat.send','chat.private','chat.group');

INSERT IGNORE INTO chat_conversations (type, name, slug) VALUES ('general','Geral','general');

SET FOREIGN_KEY_CHECKS = 1;
