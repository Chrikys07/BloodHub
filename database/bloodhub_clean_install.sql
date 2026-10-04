-- BloodHub - instalação limpa para homologação
-- Gerado da estrutura integral do banco de desenvolvimento exclusivamente por consultas de leitura.
-- Destino: banco vazio em MySQL 8.0.46. Este arquivo não seleciona nem altera o banco bloodhub local.
-- Não contém CREATE DATABASE, USE, DROP, DELETE ou TRUNCATE.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET SESSION time_zone = '+00:00';
SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 1;

-- Estrutura em ordem topológica segura para todas as foreign keys.

-- Tabela `billing_services`
CREATE TABLE `billing_services` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `service_code` varchar(20) NOT NULL,
  `name` varchar(180) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_billing_service_code` (`service_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `clients`
CREATE TABLE `clients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(180) NOT NULL,
  `client_type` enum('internal','external') NOT NULL,
  `document` varchar(40) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `district` varchar(120) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `state` char(2) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `contact_name` varchar(180) DEFAULT NULL,
  `email` varchar(180) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `phone_extension` varchar(30) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_clients_type` (`client_type`),
  KEY `idx_clients_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `permissions`
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `permission_key` varchar(160) NOT NULL,
  `name` varchar(160) NOT NULL,
  `module` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_key` (`permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `preservatives`
CREATE TABLE `preservatives` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(60) DEFAULT NULL,
  `name` varchar(180) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_preservatives_name` (`name`),
  UNIQUE KEY `uk_preservatives_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `roles`
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `role_permissions`
CREATE TABLE `role_permissions` (
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_role_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `supplies`
CREATE TABLE `supplies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(180) NOT NULL,
  `manufacturer` varchar(180) DEFAULT NULL,
  `internal_code` varchar(80) DEFAULT NULL,
  `unit_of_measure` varchar(40) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `supply_lots`
CREATE TABLE `supply_lots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `supply_id` bigint(20) unsigned NOT NULL,
  `lot_number` varchar(120) NOT NULL,
  `expiration_date` date NOT NULL,
  `received_at` date DEFAULT NULL,
  `quantity_initial` decimal(14,4) DEFAULT NULL,
  `quantity_available` decimal(14,4) DEFAULT NULL,
  `status` enum('active','inactive','exhausted','blocked') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_supply_lot` (`supply_id`,`lot_number`),
  KEY `idx_supply_lots_expiration` (`expiration_date`),
  KEY `idx_supply_lots_status` (`status`),
  CONSTRAINT `fk_supply_lots_supply` FOREIGN KEY (`supply_id`) REFERENCES `supplies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `tests`
CREATE TABLE `tests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(80) DEFAULT NULL,
  `name` varchar(180) NOT NULL,
  `result_type` enum('numeric','text','select','boolean','positive_negative') NOT NULL,
  `unit` varchar(80) DEFAULT NULL,
  `method_name` varchar(180) DEFAULT NULL,
  `equipment_required` tinyint(1) NOT NULL DEFAULT 0,
  `allows_ad_hoc` tinyint(1) NOT NULL DEFAULT 0,
  `is_final_result` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `test_supplies`
CREATE TABLE `test_supplies` (
  `test_id` bigint(20) unsigned NOT NULL,
  `supply_id` bigint(20) unsigned NOT NULL,
  `quantity_required` decimal(14,4) DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`test_id`,`supply_id`),
  KEY `fk_test_supplies_supply` (`supply_id`),
  CONSTRAINT `fk_test_supplies_supply` FOREIGN KEY (`supply_id`) REFERENCES `supplies` (`id`),
  CONSTRAINT `fk_test_supplies_test` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `units`
CREATE TABLE `units` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(180) NOT NULL,
  `unit_type` enum('lcqh','processing','transfusion_agency','management','other') NOT NULL,
  `code` varchar(60) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `district` varchar(120) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `state` char(2) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `contact_name` varchar(180) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `phone_extension` varchar(30) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_units_client` (`client_id`),
  KEY `idx_units_type` (`unit_type`),
  CONSTRAINT `fk_units_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `users`
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `primary_unit_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(180) NOT NULL,
  `professional_name` varchar(180) DEFAULT NULL,
  `professional_council` varchar(40) DEFAULT NULL,
  `professional_registration` varchar(80) DEFAULT NULL,
  `email` varchar(180) NOT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_users_role` (`role_id`),
  KEY `fk_users_client` (`client_id`),
  KEY `fk_users_primary_unit` (`primary_unit_id`),
  KEY `idx_users_status` (`status`),
  CONSTRAINT `fk_users_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_primary_unit` FOREIGN KEY (`primary_unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `user_units`
CREATE TABLE `user_units` (
  `user_id` bigint(20) unsigned NOT NULL,
  `unit_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`unit_id`),
  KEY `fk_user_units_unit` (`unit_id`),
  CONSTRAINT `fk_user_units_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_units_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `validations`
CREATE TABLE `validations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pv_number` varchar(80) NOT NULL,
  `name` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `unit_id` bigint(20) unsigned DEFAULT NULL,
  `start_date` date NOT NULL,
  `expected_end_date` date DEFAULT NULL,
  `actual_end_date` date DEFAULT NULL,
  `final_conclusion` text DEFAULT NULL,
  `status` enum('planned','in_progress','completed','cancelled') NOT NULL DEFAULT 'planned',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_validations_pv` (`pv_number`),
  KEY `idx_validations_status_dates` (`status`,`start_date`),
  KEY `fk_validations_created_by` (`created_by`),
  KEY `fk_validations_updated_by` (`updated_by`),
  KEY `idx_validations_unit_status` (`unit_id`,`status`),
  CONSTRAINT `fk_validations_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_validations_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `fk_validations_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `validation_phases`
CREATE TABLE `validation_phases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `validation_id` bigint(20) unsigned NOT NULL,
  `name` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `sequence_order` int(10) unsigned NOT NULL DEFAULT 1,
  `status` enum('active','completed','cancelled','inactive') NOT NULL DEFAULT 'active',
  `outcome` enum('satisfactory','unsatisfactory','inconclusive') DEFAULT NULL,
  `conclusion` text DEFAULT NULL,
  `started_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_validation_phase_order` (`validation_id`,`sequence_order`),
  KEY `fk_vphase_user` (`created_by`),
  CONSTRAINT `fk_vphase_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vphase_validation` FOREIGN KEY (`validation_id`) REFERENCES `validations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `audit_logs`
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(160) NOT NULL,
  `entity_type` varchar(120) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `before_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_data`)),
  `after_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_data`)),
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_audit_logs_user` (`user_id`),
  KEY `idx_audit_logs_created_at` (`created_at`),
  KEY `idx_audit_logs_action` (`action`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `bag_brands`
CREATE TABLE `bag_brands` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(180) NOT NULL,
  `reference_number` varchar(120) DEFAULT NULL,
  `preservative_id` bigint(20) unsigned DEFAULT NULL,
  `tare_weight` decimal(10,3) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `active_brand_key` varchar(180) GENERATED ALWAYS AS (case when `active` = 1 then lcase(trim(`name`)) else NULL end) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bag_brands_name_reference` (`name`,`reference_number`),
  UNIQUE KEY `uk_bag_brands_one_active_name` (`active_brand_key`),
  KEY `fk_bag_brands_preservative` (`preservative_id`),
  CONSTRAINT `fk_bag_brands_preservative` FOREIGN KEY (`preservative_id`) REFERENCES `preservatives` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `bag_brand_tares`
CREATE TABLE `bag_brand_tares` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bag_brand_id` bigint(20) unsigned NOT NULL,
  `name` varchar(180) NOT NULL,
  `tare_weight` decimal(10,3) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `locked_at` datetime DEFAULT NULL COMMENT 'Preenchido quando um resultado analítico usar esta tara',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bag_brand_tares_brand_active` (`bag_brand_id`,`active`),
  CONSTRAINT `fk_bag_brand_tares_brand` FOREIGN KEY (`bag_brand_id`) REFERENCES `bag_brands` (`id`),
  CONSTRAINT `chk_bag_brand_tares_weight` CHECK (`tare_weight` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `billing_cost_centers`
CREATE TABLE `billing_cost_centers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cost_center_code` varchar(60) NOT NULL,
  `name` varchar(180) NOT NULL,
  `unit_id` bigint(20) unsigned DEFAULT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_billing_cost_center_code` (`cost_center_code`),
  UNIQUE KEY `uk_billing_cost_center_unit` (`unit_id`),
  KEY `fk_billing_cost_center_client` (`client_id`),
  CONSTRAINT `fk_billing_cost_center_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_billing_cost_center_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `billing_manual_entries`
CREATE TABLE `billing_manual_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `year` smallint(5) unsigned NOT NULL,
  `month` tinyint(3) unsigned NOT NULL,
  `billing_cost_center_id` bigint(20) unsigned NOT NULL,
  `billing_service_id` bigint(20) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_billing_manual_center` (`billing_cost_center_id`),
  KEY `fk_billing_manual_service` (`billing_service_id`),
  KEY `fk_billing_manual_created` (`created_by`),
  KEY `fk_billing_manual_updated` (`updated_by`),
  KEY `idx_billing_manual_period` (`year`,`month`),
  CONSTRAINT `fk_billing_manual_center` FOREIGN KEY (`billing_cost_center_id`) REFERENCES `billing_cost_centers` (`id`),
  CONSTRAINT `fk_billing_manual_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_billing_manual_service` FOREIGN KEY (`billing_service_id`) REFERENCES `billing_services` (`id`),
  CONSTRAINT `fk_billing_manual_updated` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `billing_periods`
CREATE TABLE `billing_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `year` smallint(5) unsigned NOT NULL,
  `month` tinyint(3) unsigned NOT NULL,
  `status` enum('OPEN','REVIEWED','CLOSED','REOPENED') NOT NULL DEFAULT 'OPEN',
  `snapshot_json` longtext DEFAULT NULL,
  `snapshot_version` int(10) unsigned NOT NULL DEFAULT 0,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `closed_by` bigint(20) unsigned DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `reopened_by` bigint(20) unsigned DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `reopen_reason` varchar(1000) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_billing_period` (`year`,`month`),
  KEY `fk_billing_period_reviewed` (`reviewed_by`),
  KEY `fk_billing_period_closed` (`closed_by`),
  KEY `fk_billing_period_reopened` (`reopened_by`),
  CONSTRAINT `fk_billing_period_closed` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_billing_period_reopened` FOREIGN KEY (`reopened_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_billing_period_reviewed` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `blood_components`
CREATE TABLE `blood_components` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(60) NOT NULL,
  `name` varchar(180) NOT NULL,
  `density` decimal(8,4) DEFAULT NULL,
  `hemolysis_hematocrit_test_id` bigint(20) unsigned DEFAULT NULL,
  `transport_temperature_min` decimal(5,2) DEFAULT NULL,
  `transport_temperature_max` decimal(5,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `fk_bc_hemolysis_hct_test` (`hemolysis_hematocrit_test_id`),
  CONSTRAINT `fk_bc_hemolysis_hct_test` FOREIGN KEY (`hemolysis_hematocrit_test_id`) REFERENCES `tests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `blood_component_preservative_shelf_lives`
CREATE TABLE `blood_component_preservative_shelf_lives` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `blood_component_id` bigint(20) unsigned NOT NULL,
  `preservative_id` bigint(20) unsigned NOT NULL,
  `shelf_life_days` int(10) unsigned NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bcpsl_component_preservative` (`blood_component_id`,`preservative_id`),
  KEY `fk_bcpsl_preservative` (`preservative_id`),
  CONSTRAINT `fk_bcpsl_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`),
  CONSTRAINT `fk_bcpsl_preservative` FOREIGN KEY (`preservative_id`) REFERENCES `preservatives` (`id`),
  CONSTRAINT `chk_bcpsl_days` CHECK (`shelf_life_days` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `blood_component_test_specifications`
CREATE TABLE `blood_component_test_specifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `supersedes_id` bigint(20) unsigned DEFAULT NULL,
  `version_number` int(10) unsigned NOT NULL DEFAULT 1,
  `blood_component_id` bigint(20) unsigned NOT NULL,
  `test_id` bigint(20) unsigned NOT NULL,
  `rule_type` enum('GT','GTE','LT','LTE','BETWEEN','EQUAL_NUMERIC','EQUAL_TEXT','BOOLEAN') NOT NULL,
  `min_value` decimal(30,10) DEFAULT NULL,
  `max_value` decimal(30,10) DEFAULT NULL,
  `expected_text` varchar(255) DEFAULT NULL,
  `unit` varchar(80) DEFAULT NULL,
  `preservative_id` bigint(20) unsigned DEFAULT NULL,
  `condition_type` enum('PRESERVATIVE','PROCESS_METHOD','STORAGE_DAY','LEUKOREDUCED','PATHOGEN_REDUCTION','PRE_STORAGE_LEUKOREDUCTION','POOL_TYPE') DEFAULT NULL,
  `condition_value` varchar(255) DEFAULT NULL,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `source_name` varchar(255) DEFAULT NULL,
  `source_reference` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `sampling_requirement_notes` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_qspec_test` (`test_id`),
  KEY `fk_qspec_preservative` (`preservative_id`),
  KEY `fk_qspec_created_by` (`created_by`),
  KEY `fk_qspec_updated_by` (`updated_by`),
  KEY `idx_qspec_lookup` (`blood_component_id`,`test_id`,`active`,`effective_from`,`effective_to`),
  KEY `idx_qspec_family` (`blood_component_id`,`test_id`,`condition_type`,`preservative_id`,`effective_from`,`effective_to`),
  KEY `fk_qspec_supersedes` (`supersedes_id`),
  CONSTRAINT `fk_qspec_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qspec_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qspec_preservative` FOREIGN KEY (`preservative_id`) REFERENCES `preservatives` (`id`),
  CONSTRAINT `fk_qspec_supersedes` FOREIGN KEY (`supersedes_id`) REFERENCES `blood_component_test_specifications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qspec_test` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qspec_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `chat_conversations`
CREATE TABLE `chat_conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('general','group','private') NOT NULL,
  `name` varchar(180) DEFAULT NULL,
  `slug` varchar(80) DEFAULT NULL,
  `private_key` varchar(80) DEFAULT NULL,
  `unit_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_conversations_slug` (`slug`),
  UNIQUE KEY `uq_chat_conversations_private_key` (`private_key`),
  UNIQUE KEY `uq_chat_conversations_unit` (`unit_id`),
  KEY `fk_chat_conversations_creator` (`created_by`),
  KEY `idx_chat_conversations_updated` (`updated_at`,`id`),
  CONSTRAINT `fk_chat_conversations_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_chat_conversations_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `chat_messages`
CREATE TABLE `chat_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `sender_id` bigint(20) unsigned NOT NULL,
  `message` varchar(4000) NOT NULL,
  `related_type` varchar(80) DEFAULT NULL,
  `related_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_chat_messages_sender` (`sender_id`),
  KEY `idx_chat_messages_timeline` (`conversation_id`,`created_at`,`id`),
  KEY `idx_chat_messages_context` (`related_type`,`related_id`),
  CONSTRAINT `fk_chat_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `chat_participants`
CREATE TABLE `chat_participants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `participant_source` enum('unit','external') DEFAULT NULL,
  `joined_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_read_at` datetime(6) DEFAULT NULL,
  `archived_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_participant` (`conversation_id`,`user_id`),
  KEY `idx_chat_participants_user` (`user_id`,`conversation_id`),
  KEY `idx_chat_participants_archived` (`user_id`,`archived_at`,`conversation_id`),
  CONSTRAINT `fk_chat_participants_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_participants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `cpaf_yield_classification_rules`
CREATE TABLE `cpaf_yield_classification_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `supersedes_id` bigint(20) unsigned DEFAULT NULL,
  `version_number` int(10) unsigned NOT NULL DEFAULT 1,
  `blood_component_id` bigint(20) unsigned NOT NULL,
  `simple_min_platelets` decimal(30,10) NOT NULL,
  `double_min_platelets` decimal(30,10) NOT NULL,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `source_name` varchar(255) DEFAULT NULL,
  `source_reference` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `rule_kind` varchar(80) NOT NULL DEFAULT 'PLATELETS_PER_UNIT_THRESHOLDS',
  `extension_config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extension_config_json`)),
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `active_component_guard` bigint(20) unsigned GENERATED ALWAYS AS (case when `active` = 1 then `blood_component_id` else NULL end) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cpaf_yield_component_version` (`blood_component_id`,`version_number`),
  UNIQUE KEY `uk_cpaf_yield_single_active` (`active_component_guard`),
  KEY `fk_cpaf_yield_supersedes` (`supersedes_id`),
  KEY `fk_cpaf_yield_created_by` (`created_by`),
  KEY `fk_cpaf_yield_updated_by` (`updated_by`),
  KEY `idx_cpaf_yield_active` (`blood_component_id`,`active`,`effective_from`,`effective_to`),
  CONSTRAINT `fk_cpaf_yield_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cpaf_yield_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cpaf_yield_supersedes` FOREIGN KEY (`supersedes_id`) REFERENCES `cpaf_yield_classification_rules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cpaf_yield_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `dashboard_conformity_targets`
CREATE TABLE `dashboard_conformity_targets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `blood_component_id` bigint(20) unsigned NOT NULL,
  `test_id` bigint(20) unsigned NOT NULL,
  `minimum_percentage` decimal(5,2) NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dashboard_target_version` (`blood_component_id`,`test_id`,`effective_from`),
  KEY `fk_dashboard_target_test` (`test_id`),
  KEY `fk_dashboard_target_user` (`created_by`),
  KEY `idx_dashboard_target_lookup` (`blood_component_id`,`test_id`,`active`,`effective_from`,`effective_to`),
  CONSTRAINT `fk_dashboard_target_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`),
  CONSTRAINT `fk_dashboard_target_test` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`),
  CONSTRAINT `fk_dashboard_target_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_dashboard_target_percentage` CHECK (`minimum_percentage` between 0 and 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `factor_viii_pools`
CREATE TABLE `factor_viii_pools` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(80) NOT NULL,
  `blood_component_id` bigint(20) unsigned NOT NULL,
  `origin_unit_id` bigint(20) unsigned NOT NULL,
  `status` enum('open','completed_conforming','completed_nonconforming','cancelled') NOT NULL DEFAULT 'open',
  `result` decimal(20,8) DEFAULT NULL,
  `result_unit` varchar(30) NOT NULL DEFAULT 'UI/mL',
  `specification_id` bigint(20) unsigned DEFAULT NULL,
  `specification_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specification_snapshot`)),
  `is_conforming` tinyint(1) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `result_saved_by` bigint(20) unsigned DEFAULT NULL,
  `result_saved_at` datetime DEFAULT NULL,
  `finalized_by` bigint(20) unsigned DEFAULT NULL,
  `finalized_at` datetime DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fviii_pool_code` (`code`),
  KEY `fk_fviii_pool_spec` (`specification_id`),
  KEY `fk_fviii_pool_creator` (`created_by`),
  KEY `fk_fviii_pool_result_user` (`result_saved_by`),
  KEY `fk_fviii_pool_finalizer` (`finalized_by`),
  KEY `fk_fviii_pool_canceller` (`cancelled_by`),
  KEY `idx_fviii_pool_status` (`status`),
  KEY `idx_fviii_pool_component` (`blood_component_id`),
  KEY `idx_fviii_pool_origin` (`origin_unit_id`),
  KEY `idx_fviii_pool_group` (`blood_component_id`,`origin_unit_id`,`status`),
  CONSTRAINT `fk_fviii_pool_canceller` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fviii_pool_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`),
  CONSTRAINT `fk_fviii_pool_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fviii_pool_finalizer` FOREIGN KEY (`finalized_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fviii_pool_origin` FOREIGN KEY (`origin_unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `fk_fviii_pool_result_user` FOREIGN KEY (`result_saved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fviii_pool_spec` FOREIGN KEY (`specification_id`) REFERENCES `blood_component_test_specifications` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `hemolysis_imports`
CREATE TABLE `hemolysis_imports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `original_filename` varchar(255) NOT NULL,
  `file_hash` char(64) NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL,
  `valid_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `matched_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `unmatched_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `duplicate_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `ignored_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `status` enum('previewed','applied','failed') NOT NULL DEFAULT 'previewed',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `applied_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_hemolysis_import_user` (`created_by`),
  KEY `ix_hemolysis_import_hash` (`file_hash`),
  KEY `ix_hemolysis_import_created` (`created_at`),
  CONSTRAINT `fk_hemolysis_import_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `indicators`
CREATE TABLE `indicators` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(100) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `name` varchar(180) NOT NULL,
  `objective` text DEFAULT NULL,
  `sector` varchar(120) DEFAULT NULL,
  `regional` varchar(120) DEFAULT NULL,
  `category` varchar(120) DEFAULT NULL,
  `subcategory` varchar(120) DEFAULT NULL,
  `periodicity` varchar(60) NOT NULL DEFAULT 'Mensal',
  `value_type` enum('percentage','scientific','number') NOT NULL DEFAULT 'number',
  `value_unit` varchar(80) DEFAULT NULL,
  `configured_test_id` bigint(20) unsigned DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_indicators_code` (`code`),
  UNIQUE KEY `uk_indicators_slug` (`slug`),
  KEY `fk_indicators_test` (`configured_test_id`),
  CONSTRAINT `fk_indicators_test` FOREIGN KEY (`configured_test_id`) REFERENCES `tests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `indicator_analyses`
CREATE TABLE `indicator_analyses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `indicator_id` bigint(20) unsigned NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `month` tinyint(3) unsigned NOT NULL,
  `client_scope_type` enum('all','internal','external') NOT NULL DEFAULT 'all',
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `analysis_text` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `scope_key` varchar(32) GENERATED ALWAYS AS (coalesce(cast(`client_id` as char charset utf8mb4),`client_scope_type`)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_indicator_analysis_scope` (`indicator_id`,`year`,`month`,`scope_key`),
  KEY `idx_indicator_analysis_period` (`indicator_id`,`year`,`month`),
  KEY `fk_indicator_analysis_creator` (`created_by`),
  KEY `fk_indicator_analysis_updater` (`updated_by`),
  KEY `fk_indicator_analysis_client` (`client_id`),
  CONSTRAINT `fk_indicator_analysis_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_indicator_analysis_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_indicator_analysis_indicator` FOREIGN KEY (`indicator_id`) REFERENCES `indicators` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_indicator_analysis_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_indicator_analysis_month` CHECK (`month` between 1 and 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `indicator_responsibles`
CREATE TABLE `indicator_responsibles` (
  `indicator_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`indicator_id`,`user_id`),
  KEY `idx_indicator_responsible_user` (`user_id`,`active`),
  CONSTRAINT `fk_indicator_responsible_indicator` FOREIGN KEY (`indicator_id`) REFERENCES `indicators` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_indicator_responsible_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `indicator_targets`
CREATE TABLE `indicator_targets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `indicator_id` bigint(20) unsigned NOT NULL,
  `target_operator` enum('GT','GTE','LT','LTE','EQ') NOT NULL DEFAULT 'GTE',
  `target_value` decimal(20,8) NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deletion_reason` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_indicator_target_version` (`indicator_id`,`effective_from`),
  KEY `idx_indicator_target_lookup` (`indicator_id`,`active`,`effective_from`,`effective_to`),
  KEY `fk_indicator_target_user` (`created_by`),
  KEY `fk_indicator_target_deleted_by` (`deleted_by`),
  KEY `idx_indicator_target_deleted` (`indicator_id`,`deleted_at`,`active`,`effective_from`),
  CONSTRAINT `fk_indicator_target_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_indicator_target_indicator` FOREIGN KEY (`indicator_id`) REFERENCES `indicators` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_indicator_target_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `laboratory_equipment`
CREATE TABLE `laboratory_equipment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(80) NOT NULL,
  `name` varchar(180) NOT NULL,
  `manufacturer` varchar(180) NOT NULL,
  `model` varchar(180) NOT NULL,
  `serial_number` varchar(120) DEFAULT NULL,
  `unit_id` bigint(20) unsigned DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_laboratory_equipment_code` (`code`),
  KEY `idx_equipment_active` (`active`,`effective_from`,`effective_to`),
  KEY `fk_laboratory_equipment_unit` (`unit_id`),
  CONSTRAINT `fk_laboratory_equipment_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `production_records`
CREATE TABLE `production_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint(20) unsigned NOT NULL,
  `production_date` date NOT NULL,
  `blood_component_id` bigint(20) unsigned NOT NULL,
  `quantity` int(11) NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_production_unit_date_component` (`unit_id`,`production_date`,`blood_component_id`),
  KEY `fk_production_created_by` (`created_by`),
  KEY `fk_production_updated_by` (`updated_by`),
  KEY `idx_production_period` (`unit_id`,`production_date`),
  KEY `idx_production_component_period` (`blood_component_id`,`production_date`),
  CONSTRAINT `fk_production_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`),
  CONSTRAINT `fk_production_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_production_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `fk_production_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_production_quantity` CHECK (`quantity` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `qc_monthly_closures`
CREATE TABLE `qc_monthly_closures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint(20) unsigned NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `month` tinyint(3) unsigned NOT NULL,
  `status` enum('OPEN','READY','CLOSED','REOPENED') NOT NULL DEFAULT 'OPEN',
  `closed_by` bigint(20) unsigned DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closure_notes` text DEFAULT NULL,
  `reopened_by` bigint(20) unsigned DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `reopen_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qc_monthly_closure_period` (`unit_id`,`year`,`month`),
  KEY `idx_qc_monthly_closure_status` (`status`),
  KEY `idx_qc_monthly_closure_period` (`year`,`month`),
  KEY `fk_qcmc_closed_by` (`closed_by`),
  KEY `fk_qcmc_reopened_by` (`reopened_by`),
  CONSTRAINT `fk_qcmc_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qcmc_reopened_by` FOREIGN KEY (`reopened_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qcmc_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `qc_monthly_closure_versions`
CREATE TABLE `qc_monthly_closure_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `closure_id` bigint(20) unsigned NOT NULL,
  `version_number` int(10) unsigned NOT NULL,
  `snapshot_version` smallint(5) unsigned NOT NULL DEFAULT 1,
  `snapshot_json` longtext NOT NULL,
  `closure_notes` text DEFAULT NULL,
  `has_pending_items` tinyint(1) NOT NULL DEFAULT 0,
  `closed_by` bigint(20) unsigned NOT NULL,
  `closed_at` datetime NOT NULL,
  `reopened_by` bigint(20) unsigned DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `reopen_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qcmcv_version` (`closure_id`,`version_number`),
  KEY `idx_qcmcv_closed_at` (`closed_at`),
  KEY `fk_qcmcv_closed_by` (`closed_by`),
  KEY `fk_qcmcv_reopened_by` (`reopened_by`),
  CONSTRAINT `fk_qcmcv_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_qcmcv_closure` FOREIGN KEY (`closure_id`) REFERENCES `qc_monthly_closures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qcmcv_reopened_by` FOREIGN KEY (`reopened_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `qc_notification_analyses`
CREATE TABLE `qc_notification_analyses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `analysis_code` varchar(40) DEFAULT NULL,
  `unit_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `blood_component_id` bigint(20) unsigned DEFAULT NULL,
  `test_id` bigint(20) unsigned DEFAULT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `status` enum('IN_ANALYSIS','IN_FOLLOW_UP','COMPLETED') NOT NULL DEFAULT 'IN_ANALYSIS',
  `analysis_text` text DEFAULT NULL,
  `cause_classification` varchar(80) DEFAULT NULL,
  `cause_other` varchar(255) DEFAULT NULL,
  `no_action_required` tinyint(1) NOT NULL DEFAULT 0,
  `no_action_justification` text DEFAULT NULL,
  `conclusion_text` text DEFAULT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_by` bigint(20) unsigned DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `analysis_code` (`analysis_code`),
  KEY `fk_qcna_client` (`client_id`),
  KEY `fk_qcna_component` (`blood_component_id`),
  KEY `fk_qcna_test` (`test_id`),
  KEY `fk_qcna_creator` (`created_by`),
  KEY `fk_qcna_updater` (`updated_by`),
  KEY `fk_qcna_completer` (`completed_by`),
  KEY `idx_qcna_scope` (`unit_id`,`status`,`updated_at`),
  KEY `idx_qcna_period` (`period_start`,`period_end`),
  CONSTRAINT `fk_qcna_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qcna_completer` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qcna_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qcna_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qcna_test` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qcna_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `fk_qcna_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `qc_notification_analysis_events`
CREATE TABLE `qc_notification_analysis_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `analysis_id` bigint(20) unsigned NOT NULL,
  `event_type` varchar(80) NOT NULL,
  `description` varchar(500) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_qcnae_user` (`user_id`),
  KEY `idx_qcnae_timeline` (`analysis_id`,`created_at`,`id`),
  CONSTRAINT `fk_qcnae_analysis` FOREIGN KEY (`analysis_id`) REFERENCES `qc_notification_analyses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qcnae_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `sample_shipments`
CREATE TABLE `sample_shipments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shipment_code` varchar(80) NOT NULL,
  `purpose` enum('quality_control','validation','single_assessment','transfusion_reaction') NOT NULL DEFAULT 'quality_control',
  `validation_id` bigint(20) unsigned DEFAULT NULL,
  `validation_phase_id` bigint(20) unsigned DEFAULT NULL,
  `origin_unit_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `destination_unit_id` bigint(20) unsigned NOT NULL,
  `responsible_user_id` bigint(20) unsigned NOT NULL,
  `checked_by` varchar(180) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('draft','awaiting_receipt','partially_received','received','rejected','cancelled') NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `sent_by` bigint(20) unsigned DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `received_by` bigint(20) unsigned DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejected_by` bigint(20) unsigned DEFAULT NULL,
  `rejection_reason` varchar(500) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancellation_reason` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipment_code` (`shipment_code`),
  KEY `fk_shipments_client` (`client_id`),
  KEY `fk_shipments_destination` (`destination_unit_id`),
  KEY `fk_shipments_responsible` (`responsible_user_id`),
  KEY `fk_shipments_created_by` (`created_by`),
  KEY `fk_shipments_sent_by` (`sent_by`),
  KEY `fk_shipments_received_by` (`received_by`),
  KEY `fk_shipments_rejected_by` (`rejected_by`),
  KEY `fk_shipments_cancelled_by` (`cancelled_by`),
  KEY `idx_shipments_status_sent` (`status`,`sent_at`),
  KEY `idx_shipments_origin` (`origin_unit_id`),
  KEY `fk_sh_validation` (`validation_id`),
  KEY `fk_sh_validation_phase` (`validation_phase_id`),
  CONSTRAINT `fk_sh_validation` FOREIGN KEY (`validation_id`) REFERENCES `validations` (`id`),
  CONSTRAINT `fk_sh_validation_phase` FOREIGN KEY (`validation_phase_id`) REFERENCES `validation_phases` (`id`),
  CONSTRAINT `fk_shipments_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_shipments_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_shipments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_shipments_destination` FOREIGN KEY (`destination_unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `fk_shipments_origin` FOREIGN KEY (`origin_unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `fk_shipments_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_shipments_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_shipments_responsible` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_shipments_sent_by` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `sample_shipment_thermal_boxes`
CREATE TABLE `sample_shipment_thermal_boxes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shipment_id` bigint(20) unsigned NOT NULL,
  `box_code` varchar(80) DEFAULT NULL,
  `transport_temp_min_snapshot` decimal(5,2) DEFAULT NULL,
  `transport_temp_max_snapshot` decimal(5,2) DEFAULT NULL,
  `sent_temperature` decimal(5,2) DEFAULT NULL,
  `received_temperature` decimal(5,2) DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `received_seal` varchar(120) DEFAULT NULL,
  `received_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_shipment_box` (`shipment_id`,`box_code`),
  KEY `fk_shipment_boxes_received_by` (`received_by`),
  CONSTRAINT `fk_shipment_boxes_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_shipment_boxes_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `sample_shipments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `sample_shipment_thermal_box_components`
CREATE TABLE `sample_shipment_thermal_box_components` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `thermal_box_id` bigint(20) unsigned NOT NULL,
  `blood_component_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_thermal_box_component` (`thermal_box_id`,`blood_component_id`),
  KEY `fk_thermal_box_components_component` (`blood_component_id`),
  CONSTRAINT `fk_thermal_box_components_box` FOREIGN KEY (`thermal_box_id`) REFERENCES `sample_shipment_thermal_boxes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_thermal_box_components_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `sampling_production_sources`
CREATE TABLE `sampling_production_sources` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `target_blood_component_id` bigint(20) unsigned NOT NULL,
  `source_blood_component_id` bigint(20) unsigned NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_sampling_source_source` (`source_blood_component_id`),
  KEY `idx_sampling_source_period` (`target_blood_component_id`,`active`,`effective_from`,`effective_to`),
  CONSTRAINT `fk_sampling_source_source` FOREIGN KEY (`source_blood_component_id`) REFERENCES `blood_components` (`id`),
  CONSTRAINT `fk_sampling_source_target` FOREIGN KEY (`target_blood_component_id`) REFERENCES `blood_components` (`id`),
  CONSTRAINT `chk_sampling_source_distinct` CHECK (`target_blood_component_id` <> `source_blood_component_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `sampling_rules`
CREATE TABLE `sampling_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `blood_component_id` bigint(20) unsigned NOT NULL,
  `percentage` decimal(7,4) NOT NULL DEFAULT 1.0000,
  `minimum_units` int(10) unsigned NOT NULL DEFAULT 10,
  `small_production_mode` enum('standard','actual_up_to_limit') NOT NULL DEFAULT 'standard',
  `small_production_limit` int(10) unsigned DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sampling_rule_period` (`blood_component_id`,`active`,`effective_from`,`effective_to`),
  CONSTRAINT `fk_sampling_rule_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`),
  CONSTRAINT `chk_sampling_rule_percentage` CHECK (`percentage` >= 0),
  CONSTRAINT `chk_sampling_rule_period` CHECK (`effective_to` is null or `effective_to` >= `effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `sampling_schedule_days`
CREATE TABLE `sampling_schedule_days` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `blood_component_id` bigint(20) unsigned NOT NULL,
  `weekday` tinyint(3) unsigned NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sampling_day_period` (`blood_component_id`,`weekday`,`active`,`effective_from`,`effective_to`),
  CONSTRAINT `fk_sampling_day_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`),
  CONSTRAINT `chk_sampling_weekday` CHECK (`weekday` between 0 and 6),
  CONSTRAINT `chk_sampling_day_period` CHECK (`effective_to` is null or `effective_to` >= `effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `system_settings`
CREATE TABLE `system_settings` (
  `setting_key` varchar(120) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`),
  KEY `fk_system_setting_user` (`updated_by`),
  CONSTRAINT `fk_system_setting_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `test_blood_components`
CREATE TABLE `test_blood_components` (
  `test_id` bigint(20) unsigned NOT NULL,
  `blood_component_id` bigint(20) unsigned NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`test_id`,`blood_component_id`),
  KEY `fk_tbc_component` (`blood_component_id`),
  CONSTRAINT `fk_tbc_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tbc_test` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `test_equipment_assignments`
CREATE TABLE `test_equipment_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `test_id` bigint(20) unsigned NOT NULL,
  `equipment_id` bigint(20) unsigned NOT NULL,
  `blood_component_id` bigint(20) unsigned DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_test_equipment_equipment` (`equipment_id`),
  KEY `fk_test_equipment_component` (`blood_component_id`),
  KEY `idx_test_equipment_lookup` (`test_id`,`blood_component_id`,`active`,`effective_from`,`effective_to`),
  CONSTRAINT `fk_test_equipment_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`),
  CONSTRAINT `fk_test_equipment_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `laboratory_equipment` (`id`),
  CONSTRAINT `fk_test_equipment_test` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `validation_blood_components`
CREATE TABLE `validation_blood_components` (
  `validation_id` bigint(20) unsigned NOT NULL,
  `blood_component_id` bigint(20) unsigned NOT NULL,
  `phase_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  UNIQUE KEY `uk_validation_component` (`validation_id`,`blood_component_id`,`phase_id`),
  KEY `fk_vbc_component` (`blood_component_id`),
  KEY `fk_vbc_phase` (`phase_id`),
  CONSTRAINT `fk_vbc_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`),
  CONSTRAINT `fk_vbc_phase` FOREIGN KEY (`phase_id`) REFERENCES `validation_phases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vbc_validation` FOREIGN KEY (`validation_id`) REFERENCES `validations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `validation_tests`
CREATE TABLE `validation_tests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `validation_id` bigint(20) unsigned NOT NULL,
  `phase_id` bigint(20) unsigned DEFAULT NULL,
  `blood_component_id` bigint(20) unsigned NOT NULL,
  `test_id` bigint(20) unsigned NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_validation_test` (`validation_id`,`phase_id`,`blood_component_id`,`test_id`),
  KEY `fk_vtest_phase` (`phase_id`),
  KEY `fk_vtest_component` (`blood_component_id`),
  KEY `fk_vtest_test` (`test_id`),
  CONSTRAINT `fk_vtest_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`),
  CONSTRAINT `fk_vtest_phase` FOREIGN KEY (`phase_id`) REFERENCES `validation_phases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vtest_test` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`),
  CONSTRAINT `fk_vtest_validation` FOREIGN KEY (`validation_id`) REFERENCES `validations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `bacteriology_pools`
CREATE TABLE `bacteriology_pools` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(80) NOT NULL,
  `component_id` bigint(20) unsigned NOT NULL,
  `origin_unit_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `result` enum('negative','positive') DEFAULT NULL,
  `status` enum('open','completed_negative','completed_positive','cancelled') NOT NULL DEFAULT 'open',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resulted_by` bigint(20) unsigned DEFAULT NULL,
  `resulted_at` datetime DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bacteriology_pool_code` (`code`),
  KEY `fk_bact_pool_origin` (`origin_unit_id`),
  KEY `fk_bact_pool_client` (`client_id`),
  KEY `fk_bact_pool_creator` (`created_by`),
  KEY `fk_bact_pool_result_user` (`resulted_by`),
  KEY `fk_bact_pool_cancel_user` (`cancelled_by`),
  KEY `idx_bact_pool_status` (`status`),
  KEY `idx_bact_pool_group` (`component_id`,`origin_unit_id`,`client_id`,`status`),
  CONSTRAINT `fk_bact_pool_cancel_user` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bact_pool_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bact_pool_component` FOREIGN KEY (`component_id`) REFERENCES `blood_components` (`id`),
  CONSTRAINT `fk_bact_pool_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bact_pool_origin` FOREIGN KEY (`origin_unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `fk_bact_pool_result_user` FOREIGN KEY (`resulted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `bag_brand_tare_components`
CREATE TABLE `bag_brand_tare_components` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bag_brand_tare_id` bigint(20) unsigned NOT NULL,
  `blood_component_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bbtc_tare_component` (`bag_brand_tare_id`,`blood_component_id`),
  KEY `idx_bbtc_component` (`blood_component_id`),
  CONSTRAINT `fk_bbtc_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`),
  CONSTRAINT `fk_bbtc_tare` FOREIGN KEY (`bag_brand_tare_id`) REFERENCES `bag_brand_tares` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `billing_service_mappings`
CREATE TABLE `billing_service_mappings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `billing_service_id` bigint(20) unsigned NOT NULL,
  `test_id` bigint(20) unsigned NOT NULL,
  `blood_component_id` bigint(20) unsigned DEFAULT NULL,
  `context_scope` varchar(255) NOT NULL DEFAULT 'quality_control',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_billing_mapping` (`billing_service_id`,`test_id`,`blood_component_id`,`context_scope`),
  KEY `fk_billing_mapping_component` (`blood_component_id`),
  KEY `idx_billing_mapping_test` (`test_id`,`active`),
  CONSTRAINT `fk_billing_mapping_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`),
  CONSTRAINT `fk_billing_mapping_service` FOREIGN KEY (`billing_service_id`) REFERENCES `billing_services` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_billing_mapping_test` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `indicator_actions`
CREATE TABLE `indicator_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `indicator_analysis_id` bigint(20) unsigned NOT NULL,
  `cause` text DEFAULT NULL,
  `what_text` text DEFAULT NULL,
  `why_text` text DEFAULT NULL,
  `how_text` text DEFAULT NULL,
  `who_text` varchar(255) DEFAULT NULL,
  `responsible_user_id` bigint(20) unsigned DEFAULT NULL,
  `when_text` varchar(255) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('planned','in_progress','completed','cancelled') NOT NULL DEFAULT 'planned',
  `completion_date` date DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_indicator_actions_analysis_status` (`indicator_analysis_id`,`status`),
  KEY `idx_indicator_actions_due` (`status`,`due_date`),
  KEY `fk_indicator_action_responsible` (`responsible_user_id`),
  KEY `fk_indicator_action_creator` (`created_by`),
  KEY `fk_indicator_action_updater` (`updated_by`),
  CONSTRAINT `fk_indicator_action_analysis` FOREIGN KEY (`indicator_analysis_id`) REFERENCES `indicator_analyses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_indicator_action_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_indicator_action_responsible` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_indicator_action_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `qc_notification_actions`
CREATE TABLE `qc_notification_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `analysis_id` bigint(20) unsigned NOT NULL,
  `action_description` text NOT NULL,
  `responsible_user_id` bigint(20) unsigned DEFAULT NULL,
  `responsible_name` varchar(180) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('PLANNED','IN_PROGRESS','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PLANNED',
  `completed_at` datetime DEFAULT NULL,
  `completion_notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_qcnaa_responsible` (`responsible_user_id`),
  KEY `fk_qcnaa_creator` (`created_by`),
  KEY `fk_qcnaa_updater` (`updated_by`),
  KEY `idx_qcnaa_analysis_status` (`analysis_id`,`status`,`due_date`),
  CONSTRAINT `fk_qcnaa_analysis` FOREIGN KEY (`analysis_id`) REFERENCES `qc_notification_analyses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qcnaa_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qcnaa_responsible` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qcnaa_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `samples`
CREATE TABLE `samples` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sample_shipment_id` bigint(20) unsigned DEFAULT NULL,
  `sample_code` varchar(80) NOT NULL,
  `lcqh_code` varchar(80) DEFAULT NULL,
  `purpose` enum('quality_control','validation','single_assessment','transfusion_reaction','other') NOT NULL,
  `validation_id` bigint(20) unsigned DEFAULT NULL,
  `validation_phase_id` bigint(20) unsigned DEFAULT NULL,
  `origin_unit_id` bigint(20) unsigned DEFAULT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `blood_component_id` bigint(20) unsigned DEFAULT NULL,
  `donation_number` varchar(120) DEFAULT NULL,
  `patient_name` varchar(180) DEFAULT NULL,
  `collection_date` date DEFAULT NULL,
  `production_date` date DEFAULT NULL,
  `bag_brand_id` bigint(20) unsigned DEFAULT NULL,
  `preservative_id` bigint(20) unsigned DEFAULT NULL,
  `preservative_id_snapshot` bigint(20) unsigned DEFAULT NULL,
  `shelf_life_configuration_id_snapshot` bigint(20) unsigned DEFAULT NULL,
  `shelf_life_days_snapshot` int(10) unsigned DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `status` enum('registered','sent','awaiting_receipt','received','rejected','in_analysis','partial_results','completed','cancelled') NOT NULL DEFAULT 'registered',
  `registered_by` bigint(20) unsigned DEFAULT NULL,
  `received_by` bigint(20) unsigned DEFAULT NULL,
  `registered_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `sent_by` bigint(20) unsigned DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(500) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejected_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` varchar(1000) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sample_code` (`sample_code`),
  KEY `fk_samples_origin_unit` (`origin_unit_id`),
  KEY `fk_samples_client` (`client_id`),
  KEY `fk_samples_component` (`blood_component_id`),
  KEY `fk_samples_registered_by` (`registered_by`),
  KEY `fk_samples_received_by` (`received_by`),
  KEY `idx_samples_purpose` (`purpose`),
  KEY `idx_samples_status` (`status`),
  KEY `idx_samples_donation_number` (`donation_number`),
  KEY `fk_samples_sent_by` (`sent_by`),
  KEY `fk_samples_rejected_by` (`rejected_by`),
  KEY `fk_samples_shipment` (`sample_shipment_id`),
  KEY `fk_samples_bag_brand` (`bag_brand_id`),
  KEY `fk_samples_preservative` (`preservative_id`),
  KEY `fk_samples_preservative_snapshot` (`preservative_id_snapshot`),
  KEY `fk_samples_shelf_life_snapshot` (`shelf_life_configuration_id_snapshot`),
  KEY `fk_samples_validation` (`validation_id`),
  KEY `fk_samples_validation_phase` (`validation_phase_id`),
  KEY `idx_samples_donation_component` (`donation_number`,`blood_component_id`),
  KEY `fk_samples_cancelled_by` (`cancelled_by`),
  KEY `idx_samples_dashboard` (`purpose`,`blood_component_id`,`production_date`,`origin_unit_id`,`status`),
  KEY `idx_samples_qc_report` (`purpose`,`received_at`,`origin_unit_id`,`blood_component_id`,`status`),
  KEY `idx_samples_lcqh_code` (`lcqh_code`),
  CONSTRAINT `fk_samples_bag_brand` FOREIGN KEY (`bag_brand_id`) REFERENCES `bag_brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_samples_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_samples_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_samples_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_samples_origin_unit` FOREIGN KEY (`origin_unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_samples_preservative` FOREIGN KEY (`preservative_id`) REFERENCES `preservatives` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_samples_preservative_snapshot` FOREIGN KEY (`preservative_id_snapshot`) REFERENCES `preservatives` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_samples_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_samples_registered_by` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_samples_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_samples_sent_by` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_samples_shelf_life_snapshot` FOREIGN KEY (`shelf_life_configuration_id_snapshot`) REFERENCES `blood_component_preservative_shelf_lives` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_samples_shipment` FOREIGN KEY (`sample_shipment_id`) REFERENCES `sample_shipments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_samples_validation` FOREIGN KEY (`validation_id`) REFERENCES `validations` (`id`),
  CONSTRAINT `fk_samples_validation_phase` FOREIGN KEY (`validation_phase_id`) REFERENCES `validation_phases` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `sample_tests`
CREATE TABLE `sample_tests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sample_id` bigint(20) unsigned NOT NULL,
  `test_id` bigint(20) unsigned DEFAULT NULL,
  `ad_hoc_test_name` varchar(180) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','blocked','cancelled') NOT NULL DEFAULT 'pending',
  `blocked_reason` varchar(255) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `executed_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sample_tests_sample_test` (`sample_id`,`test_id`),
  KEY `fk_sample_tests_test` (`test_id`),
  KEY `fk_sample_tests_user` (`executed_by`),
  KEY `idx_sample_tests_status` (`status`),
  CONSTRAINT `fk_sample_tests_sample` FOREIGN KEY (`sample_id`) REFERENCES `samples` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sample_tests_test` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sample_tests_user` FOREIGN KEY (`executed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `sample_weight_results`
CREATE TABLE `sample_weight_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sample_id` bigint(20) unsigned NOT NULL,
  `gross_weight` decimal(10,3) NOT NULL,
  `bag_brand_tare_id` bigint(20) unsigned NOT NULL,
  `tare_weight_used` decimal(10,3) NOT NULL,
  `net_weight` decimal(10,3) NOT NULL,
  `density_used` decimal(8,4) DEFAULT NULL,
  `volume_ml` decimal(12,4) DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_swr_sample` (`sample_id`),
  KEY `fk_swr_tare` (`bag_brand_tare_id`),
  KEY `fk_swr_user` (`recorded_by`),
  CONSTRAINT `fk_swr_sample` FOREIGN KEY (`sample_id`) REFERENCES `samples` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_swr_tare` FOREIGN KEY (`bag_brand_tare_id`) REFERENCES `bag_brand_tares` (`id`),
  CONSTRAINT `fk_swr_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `test_results`
CREATE TABLE `test_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sample_test_id` bigint(20) unsigned NOT NULL,
  `result_value_text` text DEFAULT NULL,
  `result_value_numeric` decimal(30,8) DEFAULT NULL,
  `conformity` enum('conforming','nonconforming','not_applicable','pending') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_test_results_sample_test` (`sample_test_id`),
  KEY `fk_test_results_user` (`recorded_by`),
  CONSTRAINT `fk_test_results_sample_test` FOREIGN KEY (`sample_test_id`) REFERENCES `sample_tests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_results_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `test_result_equipment`
CREATE TABLE `test_result_equipment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `test_result_id` bigint(20) unsigned NOT NULL,
  `equipment_id` bigint(20) unsigned NOT NULL,
  `equipment_name_snapshot` varchar(180) NOT NULL,
  `manufacturer_snapshot` varchar(180) NOT NULL,
  `model_snapshot` varchar(180) NOT NULL,
  `serial_snapshot` varchar(120) DEFAULT NULL,
  `method_snapshot` varchar(180) DEFAULT NULL,
  `used_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_test_result_equipment` (`test_result_id`),
  KEY `fk_result_equipment_equipment` (`equipment_id`),
  CONSTRAINT `fk_result_equipment_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `laboratory_equipment` (`id`),
  CONSTRAINT `fk_result_equipment_result` FOREIGN KEY (`test_result_id`) REFERENCES `test_results` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `test_result_parameters`
CREATE TABLE `test_result_parameters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `test_result_id` bigint(20) unsigned NOT NULL,
  `parameter_code` varchar(80) NOT NULL,
  `parameter_name` varchar(180) NOT NULL,
  `numeric_value` decimal(30,8) DEFAULT NULL,
  `text_value` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_trp_result_code` (`test_result_id`,`parameter_code`),
  CONSTRAINT `fk_trp_result` FOREIGN KEY (`test_result_id`) REFERENCES `test_results` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `test_result_spec_evaluations`
CREATE TABLE `test_result_spec_evaluations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `test_result_id` bigint(20) unsigned NOT NULL,
  `specification_id` bigint(20) unsigned DEFAULT NULL,
  `rule_type` varchar(30) DEFAULT NULL,
  `expected_min` decimal(30,10) DEFAULT NULL,
  `expected_max` decimal(30,10) DEFAULT NULL,
  `expected_text` varchar(255) DEFAULT NULL,
  `unit` varchar(80) DEFAULT NULL,
  `condition_type` varchar(50) DEFAULT NULL,
  `condition_value` varchar(255) DEFAULT NULL,
  `preservative_id` bigint(20) unsigned DEFAULT NULL,
  `actual_value` varchar(255) DEFAULT NULL,
  `conformity_status` enum('CONFORMING','NONCONFORMING','NO_SPECIFICATION','NOT_EVALUATED') NOT NULL,
  `evaluated_at` datetime NOT NULL,
  `specification_snapshot_text` text DEFAULT NULL,
  `acknowledged_by` bigint(20) unsigned DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_qeval_result` (`test_result_id`),
  KEY `fk_qeval_spec` (`specification_id`),
  KEY `fk_qeval_ack` (`acknowledged_by`),
  KEY `idx_qeval_status` (`conformity_status`),
  CONSTRAINT `fk_qeval_ack` FOREIGN KEY (`acknowledged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qeval_result` FOREIGN KEY (`test_result_id`) REFERENCES `test_results` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qeval_spec` FOREIGN KEY (`specification_id`) REFERENCES `blood_component_test_specifications` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `test_result_supplies`
CREATE TABLE `test_result_supplies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `test_result_id` bigint(20) unsigned NOT NULL,
  `supply_id` bigint(20) unsigned NOT NULL,
  `supply_lot_id` bigint(20) unsigned NOT NULL,
  `supply_name_snapshot` varchar(180) NOT NULL,
  `lot_number_snapshot` varchar(120) NOT NULL,
  `expiration_date_snapshot` date NOT NULL,
  `quantity_used` decimal(14,4) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_trs_result` (`test_result_id`),
  KEY `fk_trs_supply` (`supply_id`),
  KEY `fk_trs_lot` (`supply_lot_id`),
  CONSTRAINT `fk_trs_lot` FOREIGN KEY (`supply_lot_id`) REFERENCES `supply_lots` (`id`),
  CONSTRAINT `fk_trs_result` FOREIGN KEY (`test_result_id`) REFERENCES `test_results` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trs_supply` FOREIGN KEY (`supply_id`) REFERENCES `supplies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `washed_red_cell_results`
CREATE TABLE `washed_red_cell_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sample_id` bigint(20) unsigned NOT NULL,
  `initial_weight_g` decimal(12,4) DEFAULT NULL,
  `initial_volume_ml` decimal(14,6) DEFAULT NULL,
  `final_weight_g` decimal(12,4) DEFAULT NULL,
  `final_volume_ml` decimal(14,6) DEFAULT NULL,
  `initial_hematocrit_pct` decimal(12,6) DEFAULT NULL,
  `final_hematocrit_pct` decimal(12,6) DEFAULT NULL,
  `recovery_pct` decimal(14,8) DEFAULT NULL,
  `hemoglobin_g_dl` decimal(12,6) DEFAULT NULL,
  `hemoglobin_per_unit_g` decimal(14,8) DEFAULT NULL,
  `free_hemoglobin_g_dl` decimal(14,8) DEFAULT NULL,
  `hemolysis_pct` decimal(14,8) DEFAULT NULL,
  `standard_absorbance` decimal(16,8) DEFAULT NULL,
  `protein_absorbance` decimal(16,8) DEFAULT NULL,
  `residual_protein_g_u` decimal(16,10) DEFAULT NULL,
  `bag_brand_tare_id` bigint(20) unsigned NOT NULL,
  `tare_weight_used` decimal(10,3) NOT NULL,
  `density_used` decimal(10,6) NOT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_wrc_sample` (`sample_id`),
  KEY `fk_wrc_tare` (`bag_brand_tare_id`),
  KEY `fk_wrc_user` (`recorded_by`),
  CONSTRAINT `fk_wrc_sample` FOREIGN KEY (`sample_id`) REFERENCES `samples` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wrc_tare` FOREIGN KEY (`bag_brand_tare_id`) REFERENCES `bag_brand_tares` (`id`),
  CONSTRAINT `fk_wrc_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `administrative_corrections`
CREATE TABLE `administrative_corrections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `correction_type` enum('SAMPLE_IDENTIFICATION','SAMPLE_STATUS','LAB_RESULT','LAB_INPUT','BACTERIOLOGY_RESULT','POOL_RESULT','OTHER') NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL,
  `sample_id` bigint(20) unsigned NOT NULL,
  `parent_correction_id` bigint(20) unsigned DEFAULT NULL,
  `field_name` varchar(120) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `old_formatted_value` text DEFAULT NULL,
  `new_formatted_value` text DEFAULT NULL,
  `reason` text NOT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `corrected_by` bigint(20) unsigned DEFAULT NULL,
  `corrected_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_admin_correction_parent` (`parent_correction_id`),
  KEY `fk_admin_correction_user` (`corrected_by`),
  KEY `idx_admin_correction_sample` (`sample_id`,`corrected_at`,`id`),
  KEY `idx_admin_correction_entity` (`entity_type`,`entity_id`),
  KEY `idx_admin_correction_type` (`correction_type`,`corrected_at`),
  CONSTRAINT `fk_admin_correction_parent` FOREIGN KEY (`parent_correction_id`) REFERENCES `administrative_corrections` (`id`),
  CONSTRAINT `fk_admin_correction_sample` FOREIGN KEY (`sample_id`) REFERENCES `samples` (`id`),
  CONSTRAINT `fk_admin_correction_user` FOREIGN KEY (`corrected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `bacteriology_pool_members`
CREATE TABLE `bacteriology_pool_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pool_id` bigint(20) unsigned NOT NULL,
  `sample_id` bigint(20) unsigned NOT NULL,
  `position` tinyint(3) unsigned NOT NULL,
  `active_sample_id` bigint(20) unsigned GENERATED ALWAYS AS (case when `active` = 1 then `sample_id` else NULL end) STORED,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `released_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bact_pool_position` (`pool_id`,`position`),
  UNIQUE KEY `uk_bact_pool_sample` (`pool_id`,`sample_id`),
  UNIQUE KEY `uk_bact_active_sample` (`active_sample_id`),
  KEY `idx_bact_member_sample` (`sample_id`),
  CONSTRAINT `fk_bact_member_pool` FOREIGN KEY (`pool_id`) REFERENCES `bacteriology_pools` (`id`),
  CONSTRAINT `fk_bact_member_sample` FOREIGN KEY (`sample_id`) REFERENCES `samples` (`id`),
  CONSTRAINT `chk_bact_member_position` CHECK (`position` between 1 and 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `bacteriology_results`
CREATE TABLE `bacteriology_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sample_id` bigint(20) unsigned NOT NULL,
  `test_sequence` smallint(5) unsigned NOT NULL DEFAULT 1,
  `test_kind` enum('initial','additional','retest','pool') NOT NULL DEFAULT 'initial',
  `stage` enum('individual_initial','pool_screening','post_pool_individual','retest') NOT NULL,
  `source_type` enum('individual','pool','retest') NOT NULL,
  `pool_id` bigint(20) unsigned DEFAULT NULL,
  `result` enum('negative','positive') DEFAULT NULL,
  `identified_bacteria` varchar(255) DEFAULT NULL,
  `is_final` tinyint(1) NOT NULL DEFAULT 0,
  `bacteriological_conformity` enum('conforming','nonconforming','pending') NOT NULL DEFAULT 'pending',
  `workflow_status` enum('registered','retest_pending','identification_pending','completed') NOT NULL DEFAULT 'registered',
  `performed_by` bigint(20) unsigned DEFAULT NULL,
  `performed_at` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_bact_result_user` (`performed_by`),
  KEY `idx_bact_result_sample_final` (`sample_id`,`is_final`),
  KEY `idx_bact_result_pool` (`pool_id`),
  KEY `idx_bact_result_workflow` (`workflow_status`,`stage`,`result`),
  KEY `idx_bact_result_attempt` (`sample_id`,`test_sequence`,`test_kind`),
  CONSTRAINT `fk_bact_result_pool` FOREIGN KEY (`pool_id`) REFERENCES `bacteriology_pools` (`id`),
  CONSTRAINT `fk_bact_result_sample` FOREIGN KEY (`sample_id`) REFERENCES `samples` (`id`),
  CONSTRAINT `fk_bact_result_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `bacteriology_retests`
CREATE TABLE `bacteriology_retests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sample_id` bigint(20) unsigned NOT NULL,
  `initial_result_id` bigint(20) unsigned NOT NULL,
  `status` enum('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
  `result` enum('negative','positive') DEFAULT NULL,
  `result_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_by` bigint(20) unsigned DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bact_retest_initial` (`initial_result_id`),
  KEY `fk_bact_retest_sample` (`sample_id`),
  KEY `fk_bact_retest_result` (`result_id`),
  KEY `fk_bact_retest_creator` (`created_by`),
  KEY `fk_bact_retest_completer` (`completed_by`),
  KEY `idx_bact_retest_status` (`status`,`sample_id`),
  CONSTRAINT `fk_bact_retest_completer` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bact_retest_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bact_retest_initial` FOREIGN KEY (`initial_result_id`) REFERENCES `bacteriology_results` (`id`),
  CONSTRAINT `fk_bact_retest_result` FOREIGN KEY (`result_id`) REFERENCES `bacteriology_results` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bact_retest_sample` FOREIGN KEY (`sample_id`) REFERENCES `samples` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `cpaf_yield_classifications`
CREATE TABLE `cpaf_yield_classifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sample_id` bigint(20) unsigned NOT NULL,
  `platelet_test_result_id` bigint(20) unsigned DEFAULT NULL,
  `rule_id` bigint(20) unsigned DEFAULT NULL,
  `platelets_per_unit` decimal(30,10) NOT NULL,
  `classification_code` enum('INSUFFICIENT_YIELD','CPAF_SIMPLE','CPAF_DOUBLE') NOT NULL,
  `classification_label` varchar(80) NOT NULL,
  `simple_min_snapshot` decimal(30,10) NOT NULL,
  `double_min_snapshot` decimal(30,10) NOT NULL,
  `rule_kind_snapshot` varchar(80) NOT NULL,
  `effective_from_snapshot` date DEFAULT NULL,
  `effective_to_snapshot` date DEFAULT NULL,
  `source_name_snapshot` varchar(255) DEFAULT NULL,
  `source_reference_snapshot` varchar(500) DEFAULT NULL,
  `notes_snapshot` text DEFAULT NULL,
  `rule_snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`rule_snapshot_json`)),
  `classified_at` datetime NOT NULL,
  `finalized_at` datetime DEFAULT NULL,
  `finalized_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cpaf_class_sample` (`sample_id`),
  KEY `fk_cpaf_class_result` (`platelet_test_result_id`),
  KEY `fk_cpaf_class_rule` (`rule_id`),
  KEY `fk_cpaf_class_finalized_by` (`finalized_by`),
  KEY `idx_cpaf_class_code` (`classification_code`,`finalized_at`),
  CONSTRAINT `fk_cpaf_class_finalized_by` FOREIGN KEY (`finalized_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cpaf_class_result` FOREIGN KEY (`platelet_test_result_id`) REFERENCES `test_results` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cpaf_class_rule` FOREIGN KEY (`rule_id`) REFERENCES `cpaf_yield_classification_rules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cpaf_class_sample` FOREIGN KEY (`sample_id`) REFERENCES `samples` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `cryoprecipitate_results`
CREATE TABLE `cryoprecipitate_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sample_id` bigint(20) unsigned NOT NULL,
  `gross_weight` decimal(10,3) DEFAULT NULL,
  `tare_weight_used` decimal(10,3) DEFAULT NULL,
  `density_used` decimal(8,4) DEFAULT NULL,
  `volume_ml` decimal(12,4) DEFAULT NULL,
  `dilution` decimal(12,4) DEFAULT NULL,
  `fibrinogen_mg_dl` decimal(12,4) DEFAULT NULL,
  `fibrinogen_mg_u` decimal(12,4) DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sample_id` (`sample_id`),
  KEY `fk_crio_result_user` (`recorded_by`),
  CONSTRAINT `fk_crio_result_sample` FOREIGN KEY (`sample_id`) REFERENCES `samples` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_crio_result_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `factor_viii_pool_samples`
CREATE TABLE `factor_viii_pool_samples` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pool_id` bigint(20) unsigned NOT NULL,
  `sample_id` bigint(20) unsigned NOT NULL,
  `position` tinyint(3) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fviii_pool_position` (`pool_id`,`position`),
  UNIQUE KEY `uk_fviii_pool_sample` (`pool_id`,`sample_id`),
  KEY `idx_fviii_member_sample` (`sample_id`),
  CONSTRAINT `fk_fviii_member_pool` FOREIGN KEY (`pool_id`) REFERENCES `factor_viii_pools` (`id`),
  CONSTRAINT `fk_fviii_member_sample` FOREIGN KEY (`sample_id`) REFERENCES `samples` (`id`),
  CONSTRAINT `chk_fviii_member_position` CHECK (`position` between 1 and 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `factor_viii_sample_results`
CREATE TABLE `factor_viii_sample_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sample_id` bigint(20) unsigned NOT NULL,
  `current_pool_id` bigint(20) unsigned DEFAULT NULL,
  `analysis_mode` enum('pool','individual') NOT NULL DEFAULT 'pool',
  `pool_result` decimal(20,8) DEFAULT NULL,
  `pool_result_at` datetime DEFAULT NULL,
  `pool_result_by` bigint(20) unsigned DEFAULT NULL,
  `pool_specification_id` bigint(20) unsigned DEFAULT NULL,
  `pool_specification_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pool_specification_snapshot`)),
  `individual_required` tinyint(1) NOT NULL DEFAULT 0,
  `individual_result` decimal(20,8) DEFAULT NULL,
  `individual_result_at` datetime DEFAULT NULL,
  `individual_result_by` bigint(20) unsigned DEFAULT NULL,
  `individual_specification_id` bigint(20) unsigned DEFAULT NULL,
  `individual_specification_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`individual_specification_snapshot`)),
  `effective_result` decimal(20,8) DEFAULT NULL,
  `effective_source` enum('pool','individual') DEFAULT NULL,
  `test_result_id` bigint(20) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fviii_sample` (`sample_id`),
  KEY `fk_fviii_sample_pool_user` (`pool_result_by`),
  KEY `fk_fviii_sample_individual_user` (`individual_result_by`),
  KEY `fk_fviii_sample_pool_spec` (`pool_specification_id`),
  KEY `fk_fviii_sample_individual_spec` (`individual_specification_id`),
  KEY `fk_fviii_sample_test_result` (`test_result_id`),
  KEY `idx_fviii_sample_pool` (`current_pool_id`),
  KEY `idx_fviii_sample_mode` (`analysis_mode`,`individual_required`),
  CONSTRAINT `fk_fviii_sample_individual_spec` FOREIGN KEY (`individual_specification_id`) REFERENCES `blood_component_test_specifications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fviii_sample_individual_user` FOREIGN KEY (`individual_result_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fviii_sample_pool` FOREIGN KEY (`current_pool_id`) REFERENCES `factor_viii_pools` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fviii_sample_pool_spec` FOREIGN KEY (`pool_specification_id`) REFERENCES `blood_component_test_specifications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fviii_sample_pool_user` FOREIGN KEY (`pool_result_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fviii_sample_sample` FOREIGN KEY (`sample_id`) REFERENCES `samples` (`id`),
  CONSTRAINT `fk_fviii_sample_test_result` FOREIGN KEY (`test_result_id`) REFERENCES `test_results` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `laboratory_reports`
CREATE TABLE `laboratory_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `report_group_id` char(36) NOT NULL,
  `previous_report_id` bigint(20) unsigned DEFAULT NULL,
  `sample_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  `unit_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('PENDING_RELEASE','RELEASED','REVISED','CANCELLED') NOT NULL DEFAULT 'PENDING_RELEASE',
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `released_by` bigint(20) unsigned DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `release_user_name_snapshot` varchar(180) DEFAULT NULL,
  `professional_registration_snapshot` varchar(160) DEFAULT NULL,
  `recipient_snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recipient_snapshot_json`)),
  `sample_snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sample_snapshot_json`)),
  `results_snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`results_snapshot_json`)),
  `notes_snapshot` text DEFAULT NULL,
  `pdf_path` varchar(500) DEFAULT NULL,
  `source_fingerprint` char(64) DEFAULT NULL,
  `revision_reason` text DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_laboratory_report_version` (`report_group_id`,`version`),
  KEY `idx_report_sample` (`sample_id`,`status`),
  KEY `idx_report_client` (`client_id`,`status`,`released_at`),
  KEY `fk_report_previous` (`previous_report_id`),
  KEY `fk_report_unit` (`unit_id`),
  KEY `fk_report_releaser` (`released_by`),
  KEY `fk_report_canceller` (`cancelled_by`),
  CONSTRAINT `fk_report_canceller` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_report_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_report_previous` FOREIGN KEY (`previous_report_id`) REFERENCES `laboratory_reports` (`id`),
  CONSTRAINT `fk_report_releaser` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_report_sample` FOREIGN KEY (`sample_id`) REFERENCES `samples` (`id`),
  CONSTRAINT `fk_report_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `qc_notifications`
CREATE TABLE `qc_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_code` varchar(40) DEFAULT NULL,
  `sample_id` bigint(20) unsigned NOT NULL,
  `sample_test_id` bigint(20) unsigned NOT NULL,
  `result_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `origin_unit_id` bigint(20) unsigned NOT NULL,
  `blood_component_id` bigint(20) unsigned DEFAULT NULL,
  `test_id` bigint(20) unsigned DEFAULT NULL,
  `specification_id` bigint(20) unsigned DEFAULT NULL,
  `donation_number_snapshot` varchar(120) DEFAULT NULL,
  `lcqh_code_snapshot` varchar(80) DEFAULT NULL,
  `component_code_snapshot` varchar(60) DEFAULT NULL,
  `component_name_snapshot` varchar(180) NOT NULL,
  `client_name_snapshot` varchar(180) DEFAULT NULL,
  `origin_unit_name_snapshot` varchar(180) NOT NULL,
  `test_code_snapshot` varchar(80) DEFAULT NULL,
  `test_name_snapshot` varchar(180) NOT NULL,
  `result_value_snapshot` varchar(255) NOT NULL,
  `result_display_snapshot` varchar(255) NOT NULL,
  `result_unit_snapshot` varchar(80) DEFAULT NULL,
  `rule_snapshot` varchar(30) DEFAULT NULL,
  `min_value_snapshot` decimal(30,10) DEFAULT NULL,
  `max_value_snapshot` decimal(30,10) DEFAULT NULL,
  `expected_text_snapshot` varchar(255) DEFAULT NULL,
  `specification_unit_snapshot` varchar(80) DEFAULT NULL,
  `reference_display_snapshot` text DEFAULT NULL,
  `specification_version_snapshot` int(10) unsigned DEFAULT NULL,
  `specification_effective_from_snapshot` date DEFAULT NULL,
  `occurred_at` datetime NOT NULL,
  `status` enum('PENDING_ACKNOWLEDGEMENT','ACKNOWLEDGED','IN_ANALYSIS','ANALYZED','IN_FOLLOW_UP','COMPLETED','CLOSED','CANCELLED') NOT NULL DEFAULT 'PENDING_ACKNOWLEDGEMENT',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `acknowledged_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancellation_reason` varchar(500) DEFAULT NULL,
  `previous_result_snapshot` varchar(255) DEFAULT NULL,
  `corrected_result_snapshot` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_qcn_result` (`result_id`),
  UNIQUE KEY `public_code` (`public_code`),
  KEY `fk_qcn_sample_test` (`sample_test_id`),
  KEY `fk_qcn_client` (`client_id`),
  KEY `fk_qcn_component` (`blood_component_id`),
  KEY `fk_qcn_test` (`test_id`),
  KEY `fk_qcn_spec` (`specification_id`),
  KEY `fk_qcn_creator` (`created_by`),
  KEY `fk_qcn_ack` (`acknowledged_by`),
  KEY `fk_qcn_cancel` (`cancelled_by`),
  KEY `idx_qcn_scope` (`origin_unit_id`,`status`,`occurred_at`),
  KEY `idx_qcn_sample` (`sample_id`),
  CONSTRAINT `fk_qcn_ack` FOREIGN KEY (`acknowledged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qcn_cancel` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qcn_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qcn_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qcn_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qcn_result` FOREIGN KEY (`result_id`) REFERENCES `test_results` (`id`),
  CONSTRAINT `fk_qcn_sample` FOREIGN KEY (`sample_id`) REFERENCES `samples` (`id`),
  CONSTRAINT `fk_qcn_sample_test` FOREIGN KEY (`sample_test_id`) REFERENCES `sample_tests` (`id`),
  CONSTRAINT `fk_qcn_spec` FOREIGN KEY (`specification_id`) REFERENCES `blood_component_test_specifications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qcn_test` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qcn_unit` FOREIGN KEY (`origin_unit_id`) REFERENCES `units` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `qc_notification_analysis_items`
CREATE TABLE `qc_notification_analysis_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `analysis_id` bigint(20) unsigned NOT NULL,
  `notification_id` bigint(20) unsigned NOT NULL,
  `added_by` bigint(20) unsigned DEFAULT NULL,
  `added_at` datetime NOT NULL DEFAULT current_timestamp(),
  `removed_by` bigint(20) unsigned DEFAULT NULL,
  `removed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_qcnai_adder` (`added_by`),
  KEY `fk_qcnai_remover` (`removed_by`),
  KEY `idx_qcnai_analysis_active` (`analysis_id`,`removed_at`),
  KEY `idx_qcnai_notification_active` (`notification_id`,`removed_at`),
  CONSTRAINT `fk_qcnai_adder` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qcnai_analysis` FOREIGN KEY (`analysis_id`) REFERENCES `qc_notification_analyses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qcnai_notification` FOREIGN KEY (`notification_id`) REFERENCES `qc_notifications` (`id`),
  CONSTRAINT `fk_qcnai_remover` FOREIGN KEY (`removed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `qc_notification_email_logs`
CREATE TABLE `qc_notification_email_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` bigint(20) unsigned NOT NULL,
  `recipient` varchar(180) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('PENDING','SENT','FAILED') NOT NULL DEFAULT 'PENDING',
  `error_message` varchar(1000) DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_qcn_email_recipient` (`notification_id`,`recipient`),
  KEY `idx_qcn_email_retry` (`status`,`attempted_at`),
  CONSTRAINT `fk_qcn_email_notification` FOREIGN KEY (`notification_id`) REFERENCES `qc_notifications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `qc_notification_events`
CREATE TABLE `qc_notification_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` bigint(20) unsigned NOT NULL,
  `event_type` varchar(80) NOT NULL,
  `description` varchar(500) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_qcne_user` (`user_id`),
  KEY `idx_qcne_timeline` (`notification_id`,`created_at`,`id`),
  CONSTRAINT `fk_qcne_notification` FOREIGN KEY (`notification_id`) REFERENCES `qc_notifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qcne_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `bacteriology_positive_samples`
CREATE TABLE `bacteriology_positive_samples` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bacteriology_result_id` bigint(20) unsigned NOT NULL,
  `sample_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `donation_number` varchar(120) NOT NULL,
  `send_date` date DEFAULT NULL,
  `collection_date` date DEFAULT NULL,
  `origin` varchar(180) DEFAULT NULL,
  `client` varchar(180) DEFAULT NULL,
  `status` enum('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
  `conclusion` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `completed_by` bigint(20) unsigned DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bact_positive_result` (`bacteriology_result_id`),
  KEY `fk_bact_positive_sample` (`sample_id`),
  KEY `fk_bact_positive_creator` (`created_by`),
  KEY `fk_bact_positive_completer` (`completed_by`),
  KEY `idx_bact_positive_filters` (`donation_number`,`status`,`send_date`),
  KEY `idx_bact_positive_client` (`client_id`),
  CONSTRAINT `fk_bact_positive_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bact_positive_completer` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bact_positive_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bact_positive_result` FOREIGN KEY (`bacteriology_result_id`) REFERENCES `bacteriology_results` (`id`),
  CONSTRAINT `fk_bact_positive_sample` FOREIGN KEY (`sample_id`) REFERENCES `samples` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `bacteriology_positive_sample_records`
CREATE TABLE `bacteriology_positive_sample_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `positive_sample_id` bigint(20) unsigned NOT NULL,
  `position` smallint(5) unsigned NOT NULL,
  `include_in_form` tinyint(1) NOT NULL DEFAULT 1,
  `send_date` date DEFAULT NULL,
  `donation_number` varchar(120) NOT NULL,
  `blood_component_id` bigint(20) unsigned DEFAULT NULL,
  `hemocomponent` varchar(180) DEFAULT NULL,
  `collection_date` date DEFAULT NULL,
  `origin` varchar(180) DEFAULT NULL,
  `situation` varchar(180) DEFAULT NULL,
  `storage_unit_id` bigint(20) unsigned DEFAULT NULL,
  `storage_location` varchar(180) DEFAULT NULL,
  `reaction` varchar(180) DEFAULT NULL,
  `perform_bacteriology` tinyint(1) NOT NULL DEFAULT 1,
  `test_name` varchar(180) DEFAULT NULL,
  `bacteriology_date` date DEFAULT NULL,
  `bacteriology_result` varchar(120) DEFAULT NULL,
  `retest_result` enum('negative','positive') DEFAULT NULL,
  `identified_bacteria_initial` varchar(255) DEFAULT NULL,
  `identified_bacteria` varchar(255) DEFAULT NULL,
  `identified_bacteria_retest` varchar(255) DEFAULT NULL,
  `operational_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bact_positive_position` (`positive_sample_id`,`position`),
  KEY `idx_bact_positive_record_component` (`blood_component_id`),
  KEY `idx_bact_positive_record_storage` (`storage_unit_id`),
  KEY `idx_bact_positive_record_retest` (`retest_result`),
  CONSTRAINT `fk_bact_positive_record_component` FOREIGN KEY (`blood_component_id`) REFERENCES `blood_components` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bact_positive_record_parent` FOREIGN KEY (`positive_sample_id`) REFERENCES `bacteriology_positive_samples` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bact_positive_record_storage` FOREIGN KEY (`storage_unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela `bacteriology_positive_record_tests`
CREATE TABLE `bacteriology_positive_record_tests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `positive_record_id` bigint(20) unsigned NOT NULL,
  `sequence` smallint(5) unsigned NOT NULL,
  `attempt_type` enum('initial','retest') NOT NULL DEFAULT 'initial',
  `status` enum('pending','completed') NOT NULL DEFAULT 'pending',
  `result` enum('negative','positive') DEFAULT NULL,
  `identified_bacteria` varchar(255) DEFAULT NULL,
  `tested_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `performed_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bact_positive_test_attempt` (`positive_record_id`,`sequence`),
  KEY `fk_bact_positive_test_user` (`performed_by`),
  KEY `idx_bact_positive_test_status` (`positive_record_id`,`status`,`attempt_type`),
  CONSTRAINT `fk_bact_positive_test_record` FOREIGN KEY (`positive_record_id`) REFERENCES `bacteriology_positive_sample_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bact_positive_test_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dados mestres e configurações (nenhum dado operacional).
START TRANSACTION;

-- Dados preservados: `billing_services` (27 registro(s))
INSERT INTO `billing_services` (`id`, `service_code`, `name`, `display_order`, `active`, `created_at`, `updated_at`) VALUES
('1', '00547', 'Teste de hematócrito', '10', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('2', '00548', 'Controle de qualidade da água deionizada', '20', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('3', '00549', 'Contagem de plaquetas', '30', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('4', '00550', 'Contagem de leucócitos', '40', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('5', '00551', 'Contagem de leucócitos (leuco-reduzidos)', '50', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('6', '00552', 'Contagem de hemácias', '60', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('7', '00553', 'Dosagem de fator VIII', '70', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('8', '00554', 'Dosagem de fibrinogênio', '80', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('9', '00556', 'Dosagem de hemoglobina', '90', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('10', '00557', 'Inspeção de materiais', '100', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('11', '00558', 'Proteínas residuais', '110', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('12', '00559', 'Teste de pH', '120', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('13', '00560', 'Swirling (teste visual)', '130', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('14', '00561', 'Teste bacteriológico', '140', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('15', '00562', 'Teste de grau de hemólise', '150', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('16', '00563', 'Volume do hemocomponente', '160', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('17', '00830', 'Coloração de GRAM', '170', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('18', '00831', 'Identificação bacteriana - GRAM positivo', '180', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('19', '00832', 'Identificação bacteriana - GRAM negativo', '190', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('20', '00833', 'Viabilidade celular - Pré-congelamento (automatizado)', '200', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('21', '00834', 'Viabilidade celular - Pós-congelamento (automatizado)', '210', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('22', '00828', 'Viabilidade celular - Pré-congelamento (manual)', '220', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('23', '00829', 'Viabilidade celular - Pós-congelamento (manual)', '230', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('24', '00841', 'PRP (Plasma Rico em Plaquetas)', '240', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('25', '00842', 'LPA (Lisado de Plaquetas)', '250', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('26', '00840', 'Contagem CD34+', '260', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('27', '00843', 'Teste Automatizado', '270', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33');

-- Dados preservados: `clients` (1 registro(s))
INSERT INTO `clients` (`id`, `name`, `client_type`, `document`, `address`, `district`, `city`, `state`, `postal_code`, `contact_name`, `email`, `phone`, `phone_extension`, `status`, `created_at`, `updated_at`) VALUES
('1', 'Colsan Associação Beneficente de Coleta de Sangue', 'internal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2026-08-29 18:13:05', '2026-08-29 18:13:05');

-- Dados preservados: `permissions` (88 registro(s))
INSERT INTO `permissions` (`id`, `permission_key`, `name`, `module`, `description`, `status`, `created_at`) VALUES
('1', 'dashboard.global.view', 'Visualizar Dashboard Global', 'dashboard', NULL, 'active', '2026-08-29 09:21:00'),
('2', 'dashboard.operational.view', 'Visualizar Dashboard Operacional', 'dashboard', NULL, 'active', '2026-08-29 09:21:00'),
('3', 'samples.create', 'Cadastrar amostras', 'samples', NULL, 'active', '2026-08-29 09:21:00'),
('4', 'samples.view', 'Visualizar amostras', 'samples', NULL, 'active', '2026-08-29 09:21:00'),
('5', 'samples.receive', 'Receber amostras', 'reception', NULL, 'inactive', '2026-08-29 09:21:00'),
('6', 'samples.reject', 'Recusar amostras', 'reception', NULL, 'inactive', '2026-08-29 09:21:00'),
('7', 'quality_control.results.manage', 'Gerenciar resultados de Controle de Qualidade', 'quality_control', NULL, 'active', '2026-08-29 09:21:00'),
('8', 'validations.manage', 'Gerenciar validações', 'validations', NULL, 'active', '2026-08-29 09:21:00'),
('9', 'transfusion_reactions.manage', 'Gerenciar reações transfusionais', 'transfusion_reactions', NULL, 'active', '2026-08-29 09:21:00'),
('10', 'notifications.manage', 'Gerenciar excepcionalmente notificações e análises', 'notifications', NULL, 'active', '2026-08-29 09:21:00'),
('11', 'admin.users.manage', 'Gerenciar usuários', 'admin', NULL, 'active', '2026-08-29 09:21:00'),
('12', 'admin.roles.manage', 'Gerenciar perfis', 'admin', NULL, 'active', '2026-08-29 09:21:00'),
('13', 'admin.permissions.manage', 'Gerenciar permissões', 'admin', NULL, 'active', '2026-08-29 09:21:00'),
('14', 'admin.clients.manage', 'Gerenciar clientes', 'admin', NULL, 'active', '2026-08-29 09:21:00'),
('15', 'admin.tests.manage', 'Gerenciar testes', 'admin', NULL, 'active', '2026-08-29 09:21:00'),
('16', 'admin.supplies.manage', 'Gerenciar insumos e lotes', 'admin', NULL, 'active', '2026-08-29 09:21:00'),
('18', 'admin.units.manage', 'Gerenciar unidades', 'admin', NULL, 'active', '2026-08-29 18:02:32'),
('23', 'admin.blood_components.manage', 'Gerenciar hemocomponentes', 'admin', NULL, 'active', '2026-08-29 18:48:48'),
('38', 'samples.edit', 'Editar amostras cadastradas', 'samples', NULL, 'active', '2026-08-30 19:01:38'),
('39', 'samples.send', 'Enviar amostras ao LCQH', 'samples', NULL, 'active', '2026-08-30 19:01:38'),
('40', 'samples.scope.global', 'Acessar amostras de todas as unidades', 'samples', NULL, 'active', '2026-08-30 19:01:38'),
('41', 'reception.view', 'Visualizar fila de recebimento', 'reception', NULL, 'active', '2026-08-30 19:01:38'),
('42', 'reception.receive', 'Receber amostras', 'reception', NULL, 'active', '2026-08-30 19:01:38'),
('43', 'reception.reject', 'Recusar amostras', 'reception', NULL, 'active', '2026-08-30 19:01:38'),
('101', 'shipments.view', 'Visualizar remessas', 'shipments', NULL, 'active', '2026-09-01 21:19:19'),
('102', 'shipments.create', 'Criar remessas', 'shipments', NULL, 'active', '2026-09-01 21:19:19'),
('103', 'shipments.edit', 'Editar remessas antes do recebimento', 'shipments', NULL, 'active', '2026-09-01 21:19:19'),
('104', 'shipments.send', 'Finalizar e enviar remessas', 'shipments', NULL, 'active', '2026-09-01 21:19:19'),
('105', 'shipments.cancel', 'Cancelar remessas', 'shipments', NULL, 'active', '2026-09-01 21:19:19'),
('106', 'shipments.print', 'Imprimir relatório de remessa', 'shipments', NULL, 'active', '2026-09-01 21:19:19'),
('107', 'admin.bag_brands.manage', 'Gerenciar marcas de bolsa', 'admin', NULL, 'active', '2026-09-01 21:19:19'),
('168', 'chat.view', 'Visualizar HubChat', 'chat', 'Acessar conversas das quais participa', 'active', '2026-09-02 16:37:29'),
('169', 'chat.send', 'Enviar mensagens no HubChat', 'chat', 'Enviar mensagens em conversas autorizadas', 'active', '2026-09-02 16:37:29'),
('170', 'chat.private', 'Iniciar conversa privada', 'chat', 'Iniciar conversa com outro usuario ativo', 'active', '2026-09-02 16:37:29'),
('174', 'chat.group', 'Participar de grupos do HubChat', 'chat', 'Participar dos grupos das unidades as quais o usuario esta vinculado', 'active', '2026-09-02 18:10:06'),
('211', 'reception.edit_sample_data', 'Corrigir dados de amostras no recebimento', 'reception', NULL, 'active', '2026-09-05 19:16:53'),
('229', 'quality_results.view', 'Visualizar resultados de Controle de Qualidade', 'quality_results', NULL, 'active', '2026-09-06 10:36:16'),
('230', 'quality_results.edit', 'Editar resultados de Controle de Qualidade', 'quality_results', NULL, 'active', '2026-09-06 10:36:16'),
('231', 'quality_results.complete', 'Concluir análises de Controle de Qualidade', 'quality_results', NULL, 'active', '2026-09-06 10:36:16'),
('232', 'quality_results.hemolysis', 'Acessar ensaio de Grau de Hemólise', 'quality_results', NULL, 'active', '2026-09-06 10:36:16'),
('233', 'admin.preservatives.manage', 'Gerenciar preservantes', 'admin', NULL, 'active', '2026-09-06 10:36:16'),
('265', 'quality_results.hemolysis_replace', 'Substituir resultado não concluído de Grau de Hemólise', 'quality_results', NULL, 'active', '2026-09-06 22:40:42'),
('297', 'quality_control.factor_viii_pool.view', 'Visualizar pools de Fator VIII', 'quality_results', NULL, 'active', '2026-09-07 22:31:11'),
('298', 'quality_control.factor_viii_pool.create', 'Criar pools de Fator VIII', 'quality_results', NULL, 'active', '2026-09-07 22:31:11'),
('299', 'quality_control.factor_viii_pool.result', 'Registrar resultados de pools de Fator VIII', 'quality_results', NULL, 'active', '2026-09-07 22:31:11'),
('300', 'quality_control.factor_viii_pool.cancel', 'Cancelar pools de Fator VIII', 'quality_results', NULL, 'active', '2026-09-07 22:31:11'),
('341', 'quality.bacteriology.view', 'Visualizar Bacteriológico', 'quality_results', NULL, 'active', '2026-09-15 20:38:46'),
('342', 'quality.bacteriology.edit', 'Registrar resultados bacteriológicos', 'quality_results', NULL, 'active', '2026-09-15 20:38:46'),
('343', 'quality.bacteriology.pool', 'Gerenciar pools bacteriológicos', 'quality_results', NULL, 'active', '2026-09-15 20:38:46'),
('344', 'notifications.view', 'Visualizar notificações da própria unidade', 'notifications', NULL, 'active', '2026-09-19 23:31:26'),
('345', 'notifications.view_all', 'Visualizar notificações de todas as unidades', 'notifications', NULL, 'active', '2026-09-19 23:31:26'),
('346', 'notifications.acknowledge', 'Registrar ciência de notificações', 'notifications', NULL, 'active', '2026-09-19 23:31:26'),
('388', 'notifications.analyze', 'Criar e editar análises de notificações', 'notifications', NULL, 'active', '2026-09-26 22:54:07'),
('389', 'notifications.action_plan', 'Gerenciar planos de ação', 'notifications', NULL, 'active', '2026-09-26 22:54:07'),
('390', 'notifications.close', 'Encerrar análises de notificações', 'notifications', NULL, 'active', '2026-09-26 22:54:07'),
('438', 'validations.view', 'Visualizar validacoes', 'validations', NULL, 'active', '2026-09-27 11:13:15'),
('439', 'validations.create', 'Criar validacoes', 'validations', NULL, 'active', '2026-09-27 11:13:15'),
('440', 'validations.edit', 'Editar validacoes', 'validations', NULL, 'active', '2026-09-27 11:13:15'),
('441', 'validations.configure_tests', 'Configurar plano de testes', 'validations', NULL, 'active', '2026-09-27 11:13:15'),
('442', 'validations.enter_results', 'Registrar resultados de validacao', 'validations', NULL, 'active', '2026-09-27 11:13:15'),
('443', 'validations.complete', 'Concluir validacoes', 'validations', NULL, 'active', '2026-09-27 11:13:15'),
('445', 'admin_corrections.manage', 'Gerenciar correcoes administrativas', 'admin', NULL, 'active', '2026-09-27 16:57:32'),
('492', 'transfusion_reactions.view', 'Visualizar reacoes transfusionais', 'quality_results', NULL, 'active', '2026-10-02 12:18:54'),
('493', 'transfusion_reactions.edit', 'Registrar bacteriologico de reacoes transfusionais', 'quality_results', NULL, 'active', '2026-10-02 12:18:54'),
('494', 'production.view', 'Visualizar produção', 'production', NULL, 'active', '2026-10-02 14:44:15'),
('495', 'production.create', 'Registrar produção', 'production', NULL, 'active', '2026-10-02 14:44:15'),
('496', 'production.edit', 'Editar produção', 'production', NULL, 'active', '2026-10-02 14:44:15'),
('497', 'production.admin', 'Administrar produção', 'production', NULL, 'active', '2026-10-02 14:44:15'),
('506', 'sampling_schedule.view', 'Visualizar cronograma de envio', 'sampling_schedule', NULL, 'active', '2026-10-02 15:32:58'),
('507', 'sampling_schedule.admin', 'Administrar regras do cronograma', 'sampling_schedule', NULL, 'active', '2026-10-02 15:32:58'),
('508', 'reports.quality_control.view', 'Visualizar relat??rio gerencial de CQ', '', 'Consulta e exporta????o dos resultados vigentes de Controle de Qualidade.', 'active', '2026-10-02 18:53:51'),
('509', 'indicators.view', 'Visualizar indicadores', 'indicators', 'Consulta de indicadores, análises e planos de ação.', 'active', '2026-10-02 20:22:13'),
('510', 'indicators.analysis.manage', 'Gerenciar análises de indicadores', 'indicators', 'Edição operacional por responsáveis cadastrados.', 'active', '2026-10-02 20:22:13'),
('511', 'indicators.config.manage', 'Configurar indicadores', 'indicators', 'Metadados, metas, responsáveis e ativação.', 'active', '2026-10-02 20:22:13'),
('515', 'monthly_closure.view', 'Visualizar fechamento mensal de CQ', 'monthly_closure', 'Consulta o checklist, snapshots e historico mensal.', 'active', '2026-10-02 21:59:35'),
('516', 'monthly_closure.close', 'Fechar mes de CQ', 'monthly_closure', 'Registra o aceite formal e uma nova versao do snapshot.', 'active', '2026-10-02 21:59:35'),
('517', 'monthly_closure.reopen', 'Reabrir mes de CQ', 'monthly_closure', 'Reabre um periodo fechado mediante motivo obrigatorio.', 'active', '2026-10-02 21:59:35'),
('629', 'billing.view', 'Visualizar faturamento', 'billing', NULL, 'active', '2026-10-02 23:10:33'),
('630', 'billing.manual.manage', 'Gerenciar lançamentos complementares', 'billing', NULL, 'active', '2026-10-02 23:10:33'),
('631', 'billing.config.manage', 'Configurar faturamento', 'billing', NULL, 'active', '2026-10-02 23:10:33'),
('632', 'billing.close', 'Fechar competência de faturamento', 'billing', NULL, 'active', '2026-10-02 23:10:33'),
('633', 'billing.reopen', 'Reabrir competência de faturamento', 'billing', NULL, 'active', '2026-10-02 23:10:33'),
('635', 'reports.release.view', 'Visualizar laudos', 'reports', NULL, 'active', '2026-10-03 08:47:08'),
('636', 'reports.release.manage', 'Liberar laudos', 'reports', NULL, 'active', '2026-10-03 08:47:08'),
('637', 'reports.release.revise', 'Retificar laudos', 'reports', NULL, 'active', '2026-10-03 08:47:08'),
('638', 'reports.release.cancel', 'Cancelar laudos', 'reports', NULL, 'active', '2026-10-03 08:47:08'),
('639', 'laboratory_equipment.manage', 'Gerenciar equipamentos laboratoriais', 'admin', NULL, 'active', '2026-10-03 08:47:08'),
('645', 'transfusion_reaction_consultation.view', 'Consultar reações transfusionais', 'transfusion_reaction_consultation', NULL, 'active', '2026-10-03 10:53:26');

-- Dados preservados: `preservatives` (2 registro(s))
INSERT INTO `preservatives` (`id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES
('2', 'PRS001', 'CPDA-1', '1', '2026-09-06 11:53:29', '2026-09-13 21:51:32'),
('3', 'PRS002', 'SAG-M', '1', '2026-09-13 21:52:17', '2026-09-13 21:52:17');

-- Dados preservados: `roles` (5 registro(s))
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `status`, `created_at`, `updated_at`) VALUES
('1', 'Administrador', 'administrador', 'Acesso administrativo ao sistema', 'active', '2026-08-29 09:21:00', '2026-08-29 09:21:00'),
('2', 'LCQH', 'lcqh', 'Laboratório de Controle de Qualidade de Hemocomponentes', 'active', '2026-08-29 09:21:00', '2026-08-29 09:21:00'),
('3', 'Processamento', 'processamento', 'Unidades de processamento', 'active', '2026-08-29 09:21:00', '2026-08-29 09:21:00'),
('4', 'Agência Transfusional', 'agencia-transfusional', 'Cadastro e acompanhamento de reações transfusionais', 'active', '2026-08-29 09:21:00', '2026-08-29 09:21:00'),
('5', 'Gestão', 'gestao', 'Acesso consultivo ao Dashboard Global', 'active', '2026-08-29 09:21:00', '2026-08-29 09:21:00');

-- Dados preservados: `role_permissions` (184 registro(s))
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
('1', '1'),
('1', '2'),
('1', '3'),
('1', '4'),
('1', '7'),
('1', '8'),
('1', '9'),
('1', '10'),
('1', '11'),
('1', '12'),
('1', '13'),
('1', '14'),
('1', '15'),
('1', '16'),
('1', '18'),
('1', '23'),
('1', '38'),
('1', '39'),
('1', '40'),
('1', '41'),
('1', '42'),
('1', '43'),
('1', '101'),
('1', '102'),
('1', '103'),
('1', '104'),
('1', '105'),
('1', '106'),
('1', '107'),
('1', '168'),
('1', '169'),
('1', '170'),
('1', '174'),
('1', '211'),
('1', '229'),
('1', '230'),
('1', '231'),
('1', '232'),
('1', '233'),
('1', '265'),
('1', '297'),
('1', '298'),
('1', '299'),
('1', '300'),
('1', '341'),
('1', '342'),
('1', '343'),
('1', '344'),
('1', '345'),
('1', '346'),
('1', '388'),
('1', '389'),
('1', '390'),
('1', '438'),
('1', '439'),
('1', '440'),
('1', '441'),
('1', '442'),
('1', '443'),
('1', '445'),
('1', '492'),
('1', '493'),
('1', '494'),
('1', '495'),
('1', '496'),
('1', '497'),
('1', '506'),
('1', '507'),
('1', '508'),
('1', '509'),
('1', '510'),
('1', '511'),
('1', '515'),
('1', '516'),
('1', '517'),
('1', '629'),
('1', '630'),
('1', '631'),
('1', '632'),
('1', '633'),
('1', '635'),
('1', '636'),
('1', '637'),
('1', '638'),
('1', '639'),
('1', '645'),
('2', '1'),
('2', '2'),
('2', '4'),
('2', '7'),
('2', '8'),
('2', '9'),
('2', '40'),
('2', '41'),
('2', '42'),
('2', '43'),
('2', '101'),
('2', '106'),
('2', '168'),
('2', '169'),
('2', '170'),
('2', '174'),
('2', '229'),
('2', '230'),
('2', '231'),
('2', '232'),
('2', '297'),
('2', '298'),
('2', '299'),
('2', '300'),
('2', '341'),
('2', '342'),
('2', '343'),
('2', '344'),
('2', '345'),
('2', '438'),
('2', '439'),
('2', '440'),
('2', '441'),
('2', '442'),
('2', '443'),
('2', '492'),
('2', '493'),
('2', '508'),
('2', '509'),
('2', '510'),
('2', '515'),
('2', '635'),
('2', '636'),
('2', '637'),
('2', '638'),
('2', '639'),
('2', '645'),
('3', '1'),
('3', '2'),
('3', '3'),
('3', '4'),
('3', '38'),
('3', '39'),
('3', '101'),
('3', '102'),
('3', '103'),
('3', '104'),
('3', '105'),
('3', '106'),
('3', '168'),
('3', '169'),
('3', '170'),
('3', '174'),
('3', '344'),
('3', '346'),
('3', '388'),
('3', '389'),
('3', '390'),
('3', '494'),
('3', '495'),
('3', '496'),
('3', '506'),
('3', '515'),
('3', '635'),
('4', '1'),
('4', '4'),
('4', '101'),
('4', '102'),
('4', '103'),
('4', '104'),
('4', '105'),
('4', '106'),
('4', '168'),
('4', '169'),
('4', '170'),
('4', '174'),
('4', '635'),
('4', '645'),
('5', '1'),
('5', '168'),
('5', '169'),
('5', '170'),
('5', '174'),
('5', '508'),
('5', '509'),
('5', '515'),
('5', '635'),
('5', '645');

-- Dados preservados: `supplies` (1 registro(s))
INSERT INTO `supplies` (`id`, `name`, `manufacturer`, `internal_code`, `unit_of_measure`, `status`, `created_at`, `updated_at`) VALUES
('1', 'Fator VIII', 'Werfen', 'C1210', 'Frasco', 'active', '2026-08-29 19:19:22', '2026-08-29 19:19:22');

-- Dados preservados: `tests` (20 registro(s))
INSERT INTO `tests` (`id`, `code`, `name`, `result_type`, `unit`, `method_name`, `equipment_required`, `allows_ad_hoc`, `is_final_result`, `status`, `created_at`, `updated_at`) VALUES
('1', 'HEMATOCRIT', 'Hematócrito', 'numeric', '%', NULL, '0', '1', '1', 'active', '2026-08-29 18:58:46', '2026-09-06 10:37:27'),
('2', 'HEMOGLOBIN', 'Hemoglobina', 'numeric', 'g/dL', NULL, '0', '0', '0', 'active', '2026-09-06 10:37:27', '2026-10-02 17:59:25'),
('3', 'HEMOLYSIS_DEGREE', 'Grau de Hemólise', 'numeric', '%', NULL, '0', '0', '1', 'active', '2026-09-06 10:37:27', '2026-09-06 10:37:27'),
('4', 'FREE_HEMOGLOBIN', 'Hemoglobina Livre', 'numeric', NULL, NULL, '0', '0', '0', 'active', '2026-09-06 10:37:27', '2026-10-02 17:59:25'),
('5', 'BACTERIOLOGY', 'Bacteriológico', 'positive_negative', NULL, NULL, '0', '0', '1', 'active', '2026-09-06 12:57:34', '2026-09-06 12:57:34'),
('6', 'HEMOGLOBIN_PER_UNIT', 'Hb/U', 'numeric', 'g/U', NULL, '0', '0', '1', 'active', '2026-09-07 00:05:35', '2026-10-02 17:59:25'),
('7', 'VOLUME', 'Volume', 'numeric', 'mL', NULL, '0', '0', '1', 'active', '2026-09-07 09:54:26', '2026-09-07 09:54:26'),
('8', 'PLATELET_COUNT', 'Número de Plaquetas', 'numeric', NULL, NULL, '0', '0', '0', 'active', '2026-09-07 09:54:26', '2026-10-02 17:59:25'),
('9', 'PLATELETS_PER_UNIT', 'Plaquetas/U', 'numeric', '/U', NULL, '0', '0', '1', 'active', '2026-09-07 09:54:26', '2026-10-02 17:59:25'),
('10', 'LEUKOCYTE_COUNT', 'Número de Leucócitos', 'numeric', NULL, NULL, '0', '0', '0', 'active', '2026-09-07 09:54:26', '2026-10-02 17:59:25'),
('11', 'LEUKOCYTES_PER_UNIT', 'Leucócitos/U', 'numeric', '/U', NULL, '0', '0', '1', 'active', '2026-09-07 09:54:26', '2026-10-02 17:59:25'),
('12', 'PH', 'pH', 'numeric', NULL, NULL, '0', '0', '1', 'active', '2026-09-07 09:54:26', '2026-09-07 09:54:26'),
('13', 'SWIRLING', 'Swirling', 'select', NULL, NULL, '0', '0', '1', 'active', '2026-09-07 09:54:26', '2026-09-07 09:54:26'),
('18', 'FACTOR_VIII', 'Fator VIII:C', 'numeric', 'UI/mL', NULL, '0', '0', '1', 'active', '2026-09-07 22:31:10', '2026-09-07 22:31:10'),
('20', 'LEUKOCYTES_PER_ML', 'Leucócitos/mL', 'numeric', '/mL', NULL, '0', '1', '1', 'active', '2026-09-11 23:14:23', '2026-10-02 17:59:25'),
('21', 'PLATELETS_PER_ML', 'Plaquetas/mL', 'numeric', '/mL', NULL, '0', '1', '1', 'active', '2026-09-11 23:15:30', '2026-10-02 17:59:25'),
('22', 'REDBLOODCELLS_PER_ML', 'Hemácias/mL', 'numeric', '/mL', NULL, '0', '1', '1', 'active', '2026-09-11 23:17:57', '2026-10-02 17:59:25'),
('26', 'RECOVERY', 'Recuperação', 'numeric', '%', NULL, '0', '0', '1', 'active', '2026-09-13 11:04:45', '2026-09-13 11:04:45'),
('27', 'RESIDUAL_PROTEIN', 'Proteína Residual', 'numeric', 'g/U', NULL, '0', '0', '1', 'active', '2026-09-13 11:04:45', '2026-09-13 11:04:45'),
('30', 'FIBRINOGEN', 'Fibrinogênio/U', 'numeric', 'mg/U', NULL, '0', '0', '1', 'active', '2026-09-15 18:59:22', '2026-10-02 17:59:25');

-- Dados preservados: `units` (3 registro(s))
INSERT INTO `units` (`id`, `client_id`, `name`, `unit_type`, `code`, `address`, `district`, `city`, `state`, `postal_code`, `contact_name`, `email`, `phone`, `phone_extension`, `status`, `created_at`, `updated_at`) VALUES
('1', '1', 'São Bernardo', 'processing', '031052', NULL, NULL, NULL, NULL, NULL, NULL, 'cristiano.costa@colsan.org.br', NULL, NULL, 'active', '2026-08-29 18:29:48', '2026-09-20 00:21:47'),
('2', NULL, 'Laboratório de Controle de Qualidade de Hemocomponentes', 'lcqh', 'LCQH', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2026-09-01 21:47:48', '2026-09-01 21:47:48'),
('3', '1', 'Jundiaí', 'processing', '32078', NULL, NULL, NULL, NULL, NULL, NULL, 'cristiano.costa@colsan.org.br', NULL, NULL, 'active', '2026-09-01 22:01:23', '2026-09-20 00:08:05');

-- Dados preservados: `users` (1 registro(s))
INSERT INTO `users` (`id`, `role_id`, `client_id`, `primary_unit_id`, `name`, `professional_name`, `professional_council`, `professional_registration`, `email`, `photo_path`, `password_hash`, `status`, `last_login_at`, `created_at`, `updated_at`) VALUES
('1', '1', NULL, '2', 'Cristiano', 'Cristiano B. Costa', 'CRBio', '05477', 'cristiano.costa@colsan.org.br', '/uploads/avatars/user-1-d07c1b0ca986dd14e8fa0242.jpg', '$2y$10$A9TivlK2Cye0FlpZNG7iHuPkIuxyzL/iRR.zo1Dm8NcGLNc6RHO.K', 'active', NULL, '2026-08-29 17:21:17', '2026-10-03 09:45:34');

-- Dados preservados: `user_units` (1 registro(s))
INSERT INTO `user_units` (`user_id`, `unit_id`) VALUES
('1', '2');

-- Dados preservados: `bag_brands` (5 registro(s))
INSERT INTO `bag_brands` (`id`, `name`, `reference_number`, `preservative_id`, `tare_weight`, `active`, `created_at`, `updated_at`) VALUES
('1', 'Fresenius', NULL, NULL, NULL, '0', '2026-09-01 22:03:13', '2026-09-06 09:55:41'),
('2', 'JP', NULL, NULL, NULL, '1', '2026-09-01 22:03:21', '2026-09-01 22:03:21'),
('7', 'Fresenius', 'REF002FRSNS001', '2', NULL, '1', '2026-09-06 09:36:50', '2026-09-06 11:53:52'),
('9', 'Haemonetics', 'HMT0001', '2', NULL, '1', '2026-09-12 18:10:00', '2026-09-12 18:10:52'),
('10', 'Terumo', 'TRM001', '3', NULL, '1', '2026-09-13 22:02:40', '2026-09-13 22:05:07');

-- Dados preservados: `bag_brand_tares` (8 registro(s))
INSERT INTO `bag_brand_tares` (`id`, `bag_brand_id`, `name`, `tare_weight`, `active`, `locked_at`, `created_at`, `updated_at`) VALUES
('3', '1', 'Concentrado de Hemácias', '70.000', '1', '2026-09-06 23:48:59', '2026-09-06 09:34:52', '2026-09-06 23:48:59'),
('4', '7', 'Concentrado de Hemácias', '45.000', '1', '2026-09-06 12:41:14', '2026-09-06 09:37:20', '2026-09-06 12:41:14'),
('5', '7', 'Concentrado de Plaquetas', '28.000', '1', '2026-09-07 11:47:13', '2026-09-06 09:57:33', '2026-09-07 11:47:13'),
('6', '7', 'Plasma Fresco Congelado', '30.000', '1', '2026-09-07 23:33:30', '2026-09-06 09:58:06', '2026-09-07 23:33:30'),
('7', '9', 'Plaquetáferese', '45.000', '1', NULL, '2026-09-12 18:10:23', '2026-09-12 18:10:23'),
('8', '10', 'Concentrado de Hemácias por Aférese', '35.000', '1', NULL, '2026-09-13 22:07:33', '2026-09-13 22:07:33'),
('9', '7', 'Sangue Total', '45.000', '1', '2026-09-15 18:49:41', '2026-09-15 18:48:31', '2026-09-15 18:49:41'),
('10', '7', 'Crioprecipitado', '30.000', '1', NULL, '2026-09-15 19:05:07', '2026-09-15 19:05:07');

-- Dados preservados: `billing_cost_centers` (3 registro(s))
INSERT INTO `billing_cost_centers` (`id`, `cost_center_code`, `name`, `unit_id`, `client_id`, `display_order`, `active`, `created_at`, `updated_at`) VALUES
('1', '031052', 'São Bernardo', '1', '1', '1', '1', '2026-10-02 23:10:33', '2026-10-03 08:12:01'),
('2', 'LCQH', 'Laboratório de Controle de Qualidade de Hemocomponentes', '2', NULL, '2', '1', '2026-10-02 23:10:33', '2026-10-02 23:10:33'),
('3', '32078', 'Jundiaí', '3', '1', '3', '1', '2026-10-02 23:10:33', '2026-10-03 08:12:01');

-- Dados preservados: `blood_components` (13 registro(s))
INSERT INTO `blood_components` (`id`, `code`, `name`, `density`, `hemolysis_hematocrit_test_id`, `transport_temperature_min`, `transport_temperature_max`, `description`, `status`, `created_at`, `updated_at`) VALUES
('1', 'CH', 'Concentrado de Hemácias', '1.0700', '1', '1.00', '10.00', NULL, 'active', '2026-08-29 18:50:21', '2026-09-06 22:40:42'),
('3', 'CP', 'Concentrado de Plaquetas', '1.0390', '1', '20.00', '24.00', NULL, 'active', '2026-09-06 09:56:26', '2026-09-06 22:40:42'),
('4', 'PFC', 'Plasma Fresco Congelado', '1.0300', '1', '-80.00', '-20.00', NULL, 'active', '2026-09-06 09:56:45', '2026-09-06 22:40:42'),
('11', 'PFC24', 'Plasma Fresco Congelado de 24 horas', '1.0300', NULL, '-80.00', '-20.00', 'Configuração inicial derivada do PFC; revise densidade, transporte e especificações antes do uso.', 'active', '2026-09-07 22:34:53', '2026-09-07 22:34:53'),
('12', 'PF', 'Plasma Fresco', '1.0300', NULL, '-80.00', '-20.00', NULL, 'active', '2026-09-11 23:07:57', '2026-09-11 23:07:57'),
('13', 'PF24', 'Plasma Fresco 24 Horas', '1.0300', NULL, '-80.00', '-20.00', NULL, 'active', '2026-09-11 23:10:59', '2026-09-11 23:10:59'),
('14', 'CPAF', 'Concentrado de Plaquetas por Aférese', '1.0390', NULL, '20.00', '24.00', 'Concentrado de plaquetas obtido por aférese.', 'active', '2026-09-12 15:19:52', '2026-09-12 15:37:02'),
('17', 'CHF', 'Concentrado de Hemácias Desleucocitado', '1.0700', '1', '1.00', '10.00', 'Concentrado de hemácias filtrado/desleucocitado.', 'active', '2026-09-13 09:55:49', '2026-09-13 11:31:24'),
('19', 'CHL', 'Concentrado de Hemácias Lavadas', '1.0700', '1', '1.00', '10.00', 'Concentrado de hemácias lavadas.', 'active', '2026-09-13 11:04:45', '2026-09-13 11:32:04'),
('21', 'CHAF', 'Concentrado de Hemácias por Aférese', '1.0700', '1', '1.00', '10.00', 'Concentrado de hemácias obtido por aférese.', 'active', '2026-09-13 21:39:51', '2026-09-13 21:50:58'),
('22', 'ST', 'Sangue Total', '1.0560', '1', '1.00', '10.00', 'Sangue total para uso transfusional.', 'active', '2026-09-15 18:40:31', '2026-09-15 18:44:14'),
('23', 'STR', 'Sangue Total Reconstituído', '1.0560', NULL, '1.00', '10.00', 'Sangue total reconstituído.', 'active', '2026-09-15 18:40:31', '2026-09-15 18:44:40'),
('25', 'CRIO', 'Crioprecipitado', '1.0300', NULL, '-80.00', '-20.00', 'Crioprecipitado.', 'active', '2026-09-15 18:59:22', '2026-09-15 19:03:42');

-- Dados preservados: `blood_component_preservative_shelf_lives` (13 registro(s))
INSERT INTO `blood_component_preservative_shelf_lives` (`id`, `blood_component_id`, `preservative_id`, `shelf_life_days`, `active`, `created_at`, `updated_at`) VALUES
('2', '1', '2', '35', '1', '2026-09-06 11:54:26', '2026-09-06 11:54:26'),
('3', '3', '2', '5', '1', '2026-09-06 11:57:29', '2026-09-06 11:57:29'),
('4', '4', '2', '365', '1', '2026-09-07 22:47:48', '2026-09-07 22:47:48'),
('5', '11', '2', '365', '1', '2026-09-07 22:49:45', '2026-09-07 22:49:45'),
('6', '12', '2', '365', '1', '2026-09-11 23:08:22', '2026-09-11 23:08:22'),
('7', '13', '2', '365', '1', '2026-09-11 23:11:11', '2026-09-11 23:11:11'),
('8', '14', '2', '5', '1', '2026-09-12 18:13:09', '2026-09-12 18:13:09'),
('9', '17', '2', '35', '1', '2026-09-13 10:03:26', '2026-09-13 10:03:26'),
('10', '19', '2', '1', '1', '2026-09-13 11:31:54', '2026-09-13 11:31:54'),
('11', '21', '3', '42', '1', '2026-09-13 21:52:48', '2026-09-13 21:52:48'),
('12', '22', '2', '35', '1', '2026-09-15 18:43:31', '2026-09-15 18:43:31'),
('13', '23', '2', '1', '1', '2026-09-15 18:44:35', '2026-09-15 18:44:35'),
('14', '25', '2', '365', '1', '2026-09-15 19:03:19', '2026-09-15 19:03:19');

-- Dados preservados: `blood_component_test_specifications` (34 registro(s))
INSERT INTO `blood_component_test_specifications` (`id`, `supersedes_id`, `version_number`, `blood_component_id`, `test_id`, `rule_type`, `min_value`, `max_value`, `expected_text`, `unit`, `preservative_id`, `condition_type`, `condition_value`, `effective_from`, `effective_to`, `source_name`, `source_reference`, `notes`, `sampling_requirement_notes`, `active`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
('13', NULL, '1', '3', '7', 'BETWEEN', '40.0000000000', '70.0000000000', NULL, 'mL', NULL, NULL, NULL, '2026-09-07', NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '1', '1', '1', '2026-09-07 11:39:16', '2026-09-07 11:39:16'),
('14', NULL, '1', '3', '12', 'GT', '6.4000000000', NULL, NULL, NULL, NULL, 'STORAGE_DAY', 'LAST_DAY', '2026-09-07', NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '1', '1', '1', '2026-09-07 11:40:42', '2026-09-07 11:40:42'),
('15', NULL, '1', '3', '13', 'BOOLEAN', NULL, NULL, 'Sim', NULL, NULL, NULL, NULL, '2026-09-07', NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '1', '1', '1', '2026-09-07 11:41:33', '2026-09-07 11:41:33'),
('16', NULL, '1', '3', '9', 'GTE', '55000000000.0000000000', NULL, NULL, '/U', NULL, NULL, NULL, '2026-09-07', NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '1', '1', '1', '2026-09-07 11:42:56', '2026-09-20 00:41:19'),
('17', NULL, '1', '3', '11', 'LT', NULL, '200000000.0000000000', NULL, '/U', NULL, NULL, NULL, NULL, NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '1', '1', '1', '2026-09-07 11:45:17', '2026-09-07 11:45:17'),
('20', NULL, '1', '4', '18', 'GTE', '0.7000000000', NULL, NULL, 'UI/mL', NULL, NULL, NULL, '2026-09-07', NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '1', '1', '1', '2026-09-07 22:48:27', '2026-09-07 22:48:27'),
('21', NULL, '1', '4', '7', 'LTE', NULL, '150.0000000000', NULL, 'mL', NULL, NULL, NULL, '2026-09-07', NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '0', '1', '1', '2026-09-07 22:48:49', '2026-09-07 23:34:33'),
('22', NULL, '1', '11', '18', 'GTE', '0.7000000000', NULL, NULL, 'UI/mL', NULL, NULL, NULL, '2026-09-07', NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '1', '1', '1', '2026-09-07 22:50:07', '2026-09-07 22:50:07'),
('23', NULL, '1', '11', '7', 'GTE', '150.0000000000', NULL, NULL, 'UI/mL', NULL, NULL, NULL, '2026-09-07', NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '1', '1', '1', '2026-09-07 22:50:23', '2026-09-07 22:50:23'),
('24', NULL, '1', '4', '7', 'GTE', '150.0000000000', NULL, NULL, 'mL', NULL, NULL, NULL, NULL, '2026-09-07', 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '0', '1', '1', '2026-09-07 23:35:01', '2026-09-07 23:35:41'),
('25', NULL, '1', '4', '7', 'GTE', '150.0000000000', NULL, NULL, 'mL', NULL, NULL, NULL, '2026-09-07', NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '1', '1', '1', '2026-09-07 23:36:14', '2026-09-07 23:36:14'),
('27', NULL, '1', '14', '7', 'GTE', '200.0000000000', NULL, NULL, 'mL', NULL, NULL, NULL, '2026-09-12', NULL, 'Portaria GM/MS aplicavel / regulamentacao hemoterapica vigente', 'Especificacao de volume do CPAF', 'Especificacao independente da classificacao por rendimento; nao exige 400 mL para CPAF dupla.', NULL, '1', NULL, NULL, '2026-09-12 16:05:12', '2026-09-12 16:05:12'),
('33', NULL, '1', '14', '9', 'GTE', '280000000000.0000000000', NULL, NULL, '/U', NULL, NULL, NULL, '2026-09-12', NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '1', '1', '1', '2026-09-12 18:05:17', '2026-09-12 18:05:17'),
('34', NULL, '1', '14', '11', 'LT', NULL, '5000000.0000000000', NULL, '/U', NULL, NULL, NULL, '2026-09-12', NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '1', '1', '1', '2026-09-12 18:05:53', '2026-09-12 18:05:53'),
('35', NULL, '1', '14', '13', 'BOOLEAN', NULL, NULL, 'Sim', NULL, NULL, NULL, NULL, '2026-09-12', NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '1', '1', '1', '2026-09-12 18:06:36', '2026-09-12 18:06:36'),
('36', NULL, '1', '14', '12', 'GT', '6.4000000000', NULL, NULL, NULL, NULL, 'STORAGE_DAY', 'LAST_DAY', '2026-09-12', NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '1', '1', '1', '2026-09-12 18:07:37', '2026-09-12 18:07:37'),
('37', NULL, '1', '17', '6', 'GTE', '40.0000000000', NULL, NULL, 'g/U', NULL, NULL, NULL, '2026-09-13', NULL, 'Anexo 6 vigente', 'Concentrado de Hem?cias Desleucocitadas', 'Teor de hemoglobina por unidade.', NULL, '1', NULL, NULL, '2026-09-13 09:55:49', '2026-09-13 09:55:49'),
('38', NULL, '1', '17', '3', 'LT', NULL, '0.8000000000', NULL, '%', NULL, NULL, NULL, '2026-09-13', NULL, 'Anexo 6 vigente', 'Concentrado de Hem?cias Desleucocitadas', 'Grau de hem?lise.', NULL, '1', NULL, NULL, '2026-09-13 09:55:49', '2026-09-13 09:55:49'),
('39', NULL, '1', '17', '11', 'LT', NULL, '5000000.0000000000', NULL, '/U', NULL, NULL, NULL, '2026-09-13', NULL, 'Anexo 6 vigente', 'Concentrado de Hem?cias Desleucocitadas', 'Leuc?citos residuais por unidade.', NULL, '1', NULL, NULL, '2026-09-13 09:55:49', '2026-09-13 09:55:49'),
('42', NULL, '1', '19', '6', 'GTE', '40.0000000000', NULL, NULL, 'g/U', NULL, NULL, NULL, '2026-09-13', NULL, 'Anexo vigente', 'Concentrado de Hemácias Lavadas', 'Teor de hemoglobina por unidade.', '1% da produção ou 10 unidades/mês, o que for maior.', '1', NULL, NULL, '2026-09-13 11:04:46', '2026-09-13 11:04:46'),
('43', NULL, '1', '19', '1', 'BETWEEN', '50.0000000000', '75.0000000000', NULL, '%', NULL, NULL, NULL, '2026-09-13', NULL, 'Anexo vigente', 'Concentrado de Hemácias Lavadas', 'Hematócrito final.', '1% da produção ou 10 unidades/mês, o que for maior.', '1', NULL, NULL, '2026-09-13 11:04:46', '2026-09-13 11:04:46'),
('44', NULL, '1', '19', '3', 'LT', NULL, '0.8000000000', NULL, '%', NULL, NULL, NULL, '2026-09-13', NULL, 'Anexo vigente', 'Concentrado de Hemácias Lavadas', 'Grau de hemólise.', '1% da produção ou 10 unidades/mês, o que for maior.', '1', NULL, NULL, '2026-09-13 11:04:46', '2026-09-13 11:04:46'),
('45', NULL, '1', '19', '26', 'GT', '80.0000000000', NULL, NULL, '%', NULL, NULL, NULL, '2026-09-13', NULL, 'Anexo vigente', 'Concentrado de Hemácias Lavadas', 'Recuperação.', '1% da produção ou 10 unidades/mês, o que for maior.', '1', NULL, NULL, '2026-09-13 11:04:46', '2026-09-13 11:04:46'),
('46', NULL, '1', '19', '27', 'LT', NULL, '0.5000000000', NULL, 'g/U', NULL, NULL, NULL, '2026-09-13', NULL, 'Anexo vigente', 'Concentrado de Hemácias Lavadas', 'Proteína residual.', 'Todas as unidades produzidas.', '1', NULL, NULL, '2026-09-13 11:04:46', '2026-09-13 11:04:46'),
('49', NULL, '1', '21', '1', 'BETWEEN', '64.0000000000', '81.0000000000', NULL, '%', NULL, NULL, NULL, '2026-09-13', NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '1', '1', '1', '2026-09-13 21:53:47', '2026-09-13 21:53:47'),
('51', NULL, '1', '21', '3', 'LT', NULL, '0.8000000000', NULL, '%', NULL, 'STORAGE_DAY', 'LAST_DAY', '2026-09-13', NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '1', '1', '1', '2026-09-13 21:56:06', '2026-09-13 21:56:06'),
('52', NULL, '1', '21', '11', 'LT', NULL, '5000000.0000000000', NULL, '/U', NULL, NULL, NULL, '2026-09-13', NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '1', '1', '1', '2026-09-13 21:58:23', '2026-09-13 21:58:23'),
('53', NULL, '1', '21', '6', 'LT', NULL, '40.0000000000', NULL, 'g/U', NULL, NULL, NULL, '2026-09-13', NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '1', '1', '1', '2026-09-13 21:59:11', '2026-09-13 21:59:11'),
('54', NULL, '1', '22', '7', 'BETWEEN', '405.0000000000', '495.0000000000', NULL, 'mL', NULL, NULL, NULL, '2026-09-15', NULL, 'Anexo 6 do Anexo IV-B', 'Sangue Total', 'Volume.', NULL, '1', NULL, NULL, '2026-09-15 18:40:31', '2026-09-15 18:40:31'),
('55', NULL, '1', '22', '6', 'GTE', '45.0000000000', NULL, NULL, 'g/U', NULL, NULL, NULL, '2026-09-15', NULL, 'Anexo 6 do Anexo IV-B', 'Sangue Total', 'Teor de hemoglobina por unidade.', NULL, '1', NULL, NULL, '2026-09-15 18:40:31', '2026-09-15 18:40:31'),
('56', NULL, '1', '22', '3', 'LT', NULL, '0.8000000000', NULL, '%', NULL, NULL, NULL, '2026-09-15', NULL, 'Anexo 6 do Anexo IV-B', 'Sangue Total', 'Grau de hemolise.', NULL, '1', NULL, NULL, '2026-09-15 18:40:31', '2026-09-15 18:40:31'),
('59', NULL, '1', '23', '1', 'BETWEEN', '44.0000000000', '56.0000000000', NULL, '%', NULL, NULL, NULL, '2026-09-15', NULL, 'Portaria GM/MS nº 11.685, de 2 de julho de 2026', 'Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade', NULL, NULL, '1', '1', '1', '2026-09-15 18:45:16', '2026-09-15 18:45:16'),
('60', NULL, '1', '25', '7', 'BETWEEN', '10.0000000000', '40.0000000000', NULL, 'mL', NULL, NULL, NULL, '2026-09-15', NULL, 'Anexo 6 do Anexo IV-B', 'Crioprecipitado', 'Volume.', 'Todas as unidades produzidas.', '1', NULL, NULL, '2026-09-15 18:59:22', '2026-09-15 18:59:22'),
('61', NULL, '1', '25', '30', 'GT', '150.0000000000', NULL, NULL, 'mg/U', NULL, NULL, NULL, '2026-09-15', NULL, 'Anexo 6 do Anexo IV-B', 'Crioprecipitado', 'Fibrinogênio.', '1% da produção ou 4 unidades, o que for maior, nos meses em que houver produção, em unidades com até 30 dias de armazenamento.', '1', NULL, NULL, '2026-09-15 18:59:22', '2026-09-15 18:59:22');

-- Dados preservados: `cpaf_yield_classification_rules` (1 registro(s))
INSERT INTO `cpaf_yield_classification_rules` (`id`, `supersedes_id`, `version_number`, `blood_component_id`, `simple_min_platelets`, `double_min_platelets`, `effective_from`, `effective_to`, `source_name`, `source_reference`, `notes`, `rule_kind`, `extension_config_json`, `active`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
('1', NULL, '1', '14', '300000000000.0000000000', '600000000000.0000000000', '2026-09-12', NULL, 'Portaria GM/MS aplicavel / regulamentacao hemoterapica vigente', 'Classificacao de CPAF por rendimento de Plaquetas/U', 'Valores iniciais vigentes. Volume nao integra esta classificacao.', 'PLATELETS_PER_UNIT_THRESHOLDS', NULL, '1', NULL, NULL, '2026-09-12 16:05:12', '2026-09-12 16:05:12');

-- Dados preservados: `indicators` (2 registro(s))
INSERT INTO `indicators` (`id`, `code`, `slug`, `name`, `objective`, `sector`, `regional`, `category`, `subcategory`, `periodicity`, `value_type`, `value_unit`, `configured_test_id`, `active`, `created_at`, `updated_at`) VALUES
('1', 'HEMOCOMPONENTS_WITHIN_SPECIFICATIONS', 'hemocomponents-within-specifications', 'Hemocomponentes Dentro das Especificações', 'Monitorar a qualidade dos hemocomponentes produzidos na instituição.', 'LCQH', 'Laboratórios', 'Gerencial', 'Performance', 'Mensal', 'percentage', '%', NULL, '1', '2026-10-02 20:22:13', '2026-10-02 20:22:13'),
('2', 'PLATELET_MEAN_CONCENTRATION', 'platelet-mean-concentration', 'Concentração Média de Plaquetas', NULL, NULL, NULL, NULL, NULL, 'Mensal', 'scientific', '/U', '9', '1', '2026-10-02 20:22:13', '2026-10-02 20:22:13');

-- Dados preservados: `indicator_responsibles` (1 registro(s))
INSERT INTO `indicator_responsibles` (`indicator_id`, `user_id`, `active`, `created_at`) VALUES
('2', '1', '1', '2026-10-02 21:36:22');

-- Dados preservados: `indicator_targets` (2 registro(s))
INSERT INTO `indicator_targets` (`id`, `indicator_id`, `target_operator`, `target_value`, `effective_from`, `effective_to`, `active`, `created_by`, `created_at`, `deleted_at`, `deleted_by`, `deletion_reason`) VALUES
('1', '1', 'GTE', '90.00000000', '2026-01-01', NULL, '1', NULL, '2026-10-02 20:22:13', NULL, NULL, NULL),
('2', '2', 'GTE', '90.00000000', '2026-10-01', NULL, '1', '1', '2026-10-02 21:18:43', NULL, NULL, NULL);

-- Dados preservados: `sampling_production_sources` (2 registro(s))
INSERT INTO `sampling_production_sources` (`id`, `target_blood_component_id`, `source_blood_component_id`, `active`, `effective_from`, `effective_to`, `created_at`, `updated_at`) VALUES
('1', '12', '4', '1', '2026-01-01', NULL, '2026-10-02 15:32:58', '2026-10-02 15:32:58'),
('2', '13', '11', '1', '2026-01-01', NULL, '2026-10-02 15:32:58', '2026-10-02 15:32:58');

-- Dados preservados: `sampling_rules` (12 registro(s))
INSERT INTO `sampling_rules` (`id`, `blood_component_id`, `percentage`, `minimum_units`, `small_production_mode`, `small_production_limit`, `active`, `effective_from`, `effective_to`, `created_at`, `updated_at`) VALUES
('1', '1', '1.0000', '10', 'standard', NULL, '1', '2026-01-01', NULL, '2026-10-02 15:32:58', '2026-10-02 15:32:58'),
('2', '21', '1.0000', '10', 'actual_up_to_limit', '10', '1', '2026-01-01', NULL, '2026-10-02 15:32:58', '2026-10-02 15:32:58'),
('3', '17', '1.0000', '10', 'standard', NULL, '1', '2026-01-01', NULL, '2026-10-02 15:32:58', '2026-10-02 15:32:58'),
('4', '19', '1.0000', '10', 'actual_up_to_limit', '10', '1', '2026-01-01', NULL, '2026-10-02 15:32:58', '2026-10-02 15:32:58'),
('5', '3', '1.0000', '10', 'standard', NULL, '1', '2026-01-01', NULL, '2026-10-02 15:32:58', '2026-10-02 15:32:58'),
('6', '14', '1.0000', '10', 'standard', NULL, '1', '2026-01-01', NULL, '2026-10-02 15:32:58', '2026-10-02 15:32:58'),
('7', '25', '1.0000', '4', 'standard', NULL, '1', '2026-01-01', NULL, '2026-10-02 15:32:58', '2026-10-02 15:32:58'),
('8', '12', '1.0000', '4', 'standard', NULL, '1', '2026-01-01', NULL, '2026-10-02 15:32:58', '2026-10-02 15:32:58'),
('9', '13', '1.0000', '4', 'standard', NULL, '1', '2026-01-01', NULL, '2026-10-02 15:32:58', '2026-10-02 15:32:58'),
('10', '4', '1.0000', '4', 'standard', NULL, '1', '2026-01-01', NULL, '2026-10-02 15:32:58', '2026-10-02 15:32:58'),
('11', '11', '1.0000', '4', 'standard', NULL, '1', '2026-01-01', NULL, '2026-10-02 15:32:58', '2026-10-02 15:32:58'),
('12', '23', '1.0000', '10', 'actual_up_to_limit', '10', '1', '2026-01-01', NULL, '2026-10-02 15:32:58', '2026-10-02 15:32:58');

-- Dados preservados: `system_settings` (4 registro(s))
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_by`, `updated_at`) VALUES
('report_eligibility_external_quality_control', '1', NULL, '2026-10-03 10:29:52'),
('report_eligibility_external_transfusion_reaction', '1', NULL, '2026-10-03 10:29:52'),
('report_eligibility_internal_quality_control', '1', '1', '2026-10-03 09:46:57'),
('report_eligibility_internal_transfusion_reaction', '1', NULL, '2026-10-03 10:29:52');

-- Dados preservados: `test_blood_components` (71 registro(s))
INSERT INTO `test_blood_components` (`test_id`, `blood_component_id`, `is_required`) VALUES
('1', '1', '1'),
('1', '17', '1'),
('1', '19', '1'),
('1', '21', '1'),
('1', '22', '1'),
('1', '23', '1'),
('2', '1', '1'),
('2', '17', '1'),
('2', '19', '1'),
('2', '21', '1'),
('2', '22', '1'),
('3', '1', '1'),
('3', '17', '1'),
('3', '19', '1'),
('3', '21', '1'),
('3', '22', '1'),
('4', '1', '0'),
('4', '17', '0'),
('4', '19', '0'),
('4', '21', '0'),
('4', '22', '0'),
('5', '1', '0'),
('5', '3', '1'),
('5', '14', '1'),
('5', '17', '1'),
('5', '19', '0'),
('5', '21', '1'),
('5', '22', '1'),
('5', '23', '1'),
('6', '1', '1'),
('6', '17', '1'),
('6', '19', '1'),
('6', '21', '1'),
('6', '22', '1'),
('7', '3', '1'),
('7', '4', '1'),
('7', '11', '1'),
('7', '14', '1'),
('7', '17', '1'),
('7', '19', '1'),
('7', '21', '1'),
('7', '22', '1'),
('7', '23', '1'),
('7', '25', '1'),
('8', '3', '1'),
('8', '14', '1'),
('9', '3', '1'),
('9', '14', '1'),
('10', '3', '1'),
('10', '14', '1'),
('10', '17', '1'),
('10', '21', '1'),
('11', '3', '1'),
('11', '14', '1'),
('11', '17', '1'),
('11', '21', '1'),
('12', '3', '1'),
('12', '14', '1'),
('13', '3', '1'),
('13', '14', '1'),
('18', '4', '1'),
('18', '11', '1'),
('20', '12', '1'),
('20', '13', '1'),
('21', '12', '1'),
('21', '13', '1'),
('22', '12', '1'),
('22', '13', '1'),
('26', '19', '1'),
('27', '19', '1'),
('30', '25', '1');

-- Dados preservados: `bag_brand_tare_components` (14 registro(s))
INSERT INTO `bag_brand_tare_components` (`id`, `bag_brand_tare_id`, `blood_component_id`, `created_at`) VALUES
('4', '4', '1', '2026-09-06 09:37:20'),
('5', '3', '1', '2026-09-06 09:37:45'),
('6', '5', '3', '2026-09-06 09:57:33'),
('8', '6', '4', '2026-09-07 22:57:41'),
('9', '6', '11', '2026-09-07 22:57:41'),
('10', '7', '14', '2026-09-12 18:10:23'),
('11', '4', '17', '2026-09-13 09:57:38'),
('12', '3', '17', '2026-09-13 09:57:38'),
('14', '4', '19', '2026-09-13 11:04:45'),
('15', '3', '19', '2026-09-13 11:04:45'),
('18', '8', '21', '2026-09-13 22:07:33'),
('19', '9', '22', '2026-09-15 18:48:31'),
('20', '9', '23', '2026-09-15 18:48:31'),
('21', '10', '25', '2026-09-15 19:05:07');

-- Dados preservados: `billing_service_mappings` (14 registro(s))
INSERT INTO `billing_service_mappings` (`id`, `billing_service_id`, `test_id`, `blood_component_id`, `context_scope`, `active`, `created_at`, `updated_at`) VALUES
('1', '1', '1', NULL, 'quality_control', '1', '2026-10-02 23:12:16', '2026-10-02 23:12:16'),
('2', '15', '3', NULL, 'quality_control', '1', '2026-10-02 23:12:16', '2026-10-02 23:12:16'),
('3', '14', '5', NULL, 'quality_control', '1', '2026-10-02 23:12:16', '2026-10-02 23:12:16'),
('4', '9', '6', NULL, 'quality_control', '1', '2026-10-02 23:12:16', '2026-10-02 23:12:16'),
('5', '16', '7', NULL, 'quality_control', '1', '2026-10-02 23:12:16', '2026-10-02 23:12:16'),
('6', '3', '9', NULL, 'quality_control', '1', '2026-10-02 23:12:16', '2026-10-02 23:12:16'),
('7', '12', '12', NULL, 'quality_control', '1', '2026-10-02 23:12:16', '2026-10-02 23:12:16'),
('8', '13', '13', NULL, 'quality_control', '1', '2026-10-02 23:12:16', '2026-10-02 23:12:16'),
('9', '7', '18', NULL, 'quality_control', '1', '2026-10-02 23:12:16', '2026-10-02 23:12:16'),
('10', '3', '21', NULL, 'quality_control', '1', '2026-10-02 23:12:16', '2026-10-02 23:12:16'),
('11', '6', '22', NULL, 'quality_control', '1', '2026-10-02 23:12:16', '2026-10-02 23:12:16'),
('12', '11', '27', NULL, 'quality_control', '1', '2026-10-02 23:12:16', '2026-10-02 23:12:16'),
('13', '8', '30', NULL, 'quality_control', '1', '2026-10-02 23:12:16', '2026-10-02 23:12:16'),
('14', '14', '5', NULL, 'transfusion_reaction', '1', '2026-10-03 07:41:40', '2026-10-03 07:41:40');
COMMIT;

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;

-- Fim da instalação limpa.
