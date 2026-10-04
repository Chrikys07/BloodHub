CREATE TABLE IF NOT EXISTS qc_notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 public_code VARCHAR(40) NULL UNIQUE,
 sample_id BIGINT UNSIGNED NOT NULL,
 sample_test_id BIGINT UNSIGNED NOT NULL,
 result_id BIGINT UNSIGNED NOT NULL,
 client_id BIGINT UNSIGNED NULL,
 origin_unit_id BIGINT UNSIGNED NOT NULL,
 blood_component_id BIGINT UNSIGNED NULL,
 test_id BIGINT UNSIGNED NULL,
 specification_id BIGINT UNSIGNED NULL,
 donation_number_snapshot VARCHAR(120) NULL,
 lcqh_code_snapshot VARCHAR(80) NULL,
 component_code_snapshot VARCHAR(60) NULL,
 component_name_snapshot VARCHAR(180) NOT NULL,
 client_name_snapshot VARCHAR(180) NULL,
 origin_unit_name_snapshot VARCHAR(180) NOT NULL,
 test_code_snapshot VARCHAR(80) NULL,
 test_name_snapshot VARCHAR(180) NOT NULL,
 result_value_snapshot VARCHAR(255) NOT NULL,
 result_display_snapshot VARCHAR(255) NOT NULL,
 result_unit_snapshot VARCHAR(80) NULL,
 rule_snapshot VARCHAR(30) NULL,
 min_value_snapshot DECIMAL(30,10) NULL,
 max_value_snapshot DECIMAL(30,10) NULL,
 expected_text_snapshot VARCHAR(255) NULL,
 specification_unit_snapshot VARCHAR(80) NULL,
 reference_display_snapshot TEXT NULL,
 specification_version_snapshot INT UNSIGNED NULL,
 specification_effective_from_snapshot DATE NULL,
 occurred_at DATETIME NOT NULL,
 status ENUM('PENDING_ACKNOWLEDGEMENT','ACKNOWLEDGED','IN_ANALYSIS','ANALYZED','CLOSED','CANCELLED') NOT NULL DEFAULT 'PENDING_ACKNOWLEDGEMENT',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 created_by BIGINT UNSIGNED NULL,
 acknowledged_at DATETIME NULL,
 acknowledged_by BIGINT UNSIGNED NULL,
 cancelled_at DATETIME NULL,
 cancelled_by BIGINT UNSIGNED NULL,
 cancellation_reason VARCHAR(500) NULL,
 previous_result_snapshot VARCHAR(255) NULL,
 corrected_result_snapshot VARCHAR(255) NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_qcn_sample FOREIGN KEY(sample_id) REFERENCES samples(id) ON DELETE RESTRICT,
 CONSTRAINT fk_qcn_sample_test FOREIGN KEY(sample_test_id) REFERENCES sample_tests(id) ON DELETE RESTRICT,
 CONSTRAINT fk_qcn_result FOREIGN KEY(result_id) REFERENCES test_results(id) ON DELETE RESTRICT,
 CONSTRAINT fk_qcn_client FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE SET NULL,
 CONSTRAINT fk_qcn_unit FOREIGN KEY(origin_unit_id) REFERENCES units(id) ON DELETE RESTRICT,
 CONSTRAINT fk_qcn_component FOREIGN KEY(blood_component_id) REFERENCES blood_components(id) ON DELETE SET NULL,
 CONSTRAINT fk_qcn_test FOREIGN KEY(test_id) REFERENCES tests(id) ON DELETE SET NULL,
 CONSTRAINT fk_qcn_spec FOREIGN KEY(specification_id) REFERENCES blood_component_test_specifications(id) ON DELETE SET NULL,
 CONSTRAINT fk_qcn_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_qcn_ack FOREIGN KEY(acknowledged_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_qcn_cancel FOREIGN KEY(cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uk_qcn_result(result_id),
 INDEX idx_qcn_scope(origin_unit_id,status,occurred_at),
 INDEX idx_qcn_sample(sample_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qc_notification_email_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 notification_id BIGINT UNSIGNED NOT NULL,
 recipient VARCHAR(180) NOT NULL,
 attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 status ENUM('PENDING','SENT','FAILED') NOT NULL DEFAULT 'PENDING',
 error_message VARCHAR(1000) NULL,
 sent_at DATETIME NULL,
 CONSTRAINT fk_qcn_email_notification FOREIGN KEY(notification_id) REFERENCES qc_notifications(id) ON DELETE CASCADE,
 UNIQUE KEY uk_qcn_email_recipient(notification_id,recipient),
 INDEX idx_qcn_email_retry(status,attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions(permission_key,name,module,status) VALUES
 ('notifications.view','Visualizar notificações da própria unidade','notifications','active'),
 ('notifications.view_all','Visualizar notificações de todas as unidades','notifications','active'),
 ('notifications.acknowledge','Registrar ciência de notificações','notifications','active')
ON DUPLICATE KEY UPDATE name=VALUES(name),module=VALUES(module),status='active';

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p
WHERE (r.slug='administrador' AND p.permission_key IN ('notifications.view','notifications.view_all'))
   OR (r.slug='lcqh' AND p.permission_key IN ('notifications.view','notifications.view_all'))
   OR (r.slug='processamento' AND p.permission_key IN ('notifications.view','notifications.acknowledge'));
