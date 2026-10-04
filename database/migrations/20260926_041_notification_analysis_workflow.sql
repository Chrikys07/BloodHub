-- Evolucao segura do modulo de notificacoes: ciencia, analise, acoes e encerramento.
ALTER TABLE qc_notifications
  MODIFY status ENUM('PENDING_ACKNOWLEDGEMENT','ACKNOWLEDGED','IN_ANALYSIS','ANALYZED','IN_FOLLOW_UP','COMPLETED','CLOSED','CANCELLED')
  NOT NULL DEFAULT 'PENDING_ACKNOWLEDGEMENT';

CREATE TABLE IF NOT EXISTS qc_notification_analyses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 analysis_code VARCHAR(40) NULL UNIQUE,
 unit_id BIGINT UNSIGNED NOT NULL,
 client_id BIGINT UNSIGNED NULL,
 blood_component_id BIGINT UNSIGNED NULL,
 test_id BIGINT UNSIGNED NULL,
 period_start DATE NOT NULL,
 period_end DATE NOT NULL,
 status ENUM('IN_ANALYSIS','IN_FOLLOW_UP','COMPLETED') NOT NULL DEFAULT 'IN_ANALYSIS',
 analysis_text TEXT NULL,
 cause_classification VARCHAR(80) NULL,
 cause_other VARCHAR(255) NULL,
 no_action_required TINYINT(1) NOT NULL DEFAULT 0,
 no_action_justification TEXT NULL,
 conclusion_text TEXT NULL,
 started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 completed_by BIGINT UNSIGNED NULL,
 completed_at DATETIME NULL,
 CONSTRAINT fk_qcna_unit FOREIGN KEY(unit_id) REFERENCES units(id) ON DELETE RESTRICT,
 CONSTRAINT fk_qcna_client FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE SET NULL,
 CONSTRAINT fk_qcna_component FOREIGN KEY(blood_component_id) REFERENCES blood_components(id) ON DELETE SET NULL,
 CONSTRAINT fk_qcna_test FOREIGN KEY(test_id) REFERENCES tests(id) ON DELETE SET NULL,
 CONSTRAINT fk_qcna_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_qcna_updater FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_qcna_completer FOREIGN KEY(completed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_qcna_scope(unit_id,status,updated_at),
 INDEX idx_qcna_period(period_start,period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qc_notification_analysis_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 analysis_id BIGINT UNSIGNED NOT NULL,
 notification_id BIGINT UNSIGNED NOT NULL,
 added_by BIGINT UNSIGNED NULL,
 added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 removed_by BIGINT UNSIGNED NULL,
 removed_at DATETIME NULL,
 CONSTRAINT fk_qcnai_analysis FOREIGN KEY(analysis_id) REFERENCES qc_notification_analyses(id) ON DELETE CASCADE,
 CONSTRAINT fk_qcnai_notification FOREIGN KEY(notification_id) REFERENCES qc_notifications(id) ON DELETE RESTRICT,
 CONSTRAINT fk_qcnai_adder FOREIGN KEY(added_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_qcnai_remover FOREIGN KEY(removed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_qcnai_analysis_active(analysis_id,removed_at),
 INDEX idx_qcnai_notification_active(notification_id,removed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qc_notification_actions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 analysis_id BIGINT UNSIGNED NOT NULL,
 action_description TEXT NOT NULL,
 responsible_user_id BIGINT UNSIGNED NULL,
 responsible_name VARCHAR(180) NULL,
 due_date DATE NULL,
 status ENUM('PLANNED','IN_PROGRESS','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PLANNED',
 completed_at DATETIME NULL,
 completion_notes TEXT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_qcnaa_analysis FOREIGN KEY(analysis_id) REFERENCES qc_notification_analyses(id) ON DELETE CASCADE,
 CONSTRAINT fk_qcnaa_responsible FOREIGN KEY(responsible_user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_qcnaa_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_qcnaa_updater FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_qcnaa_analysis_status(analysis_id,status,due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qc_notification_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 notification_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(80) NOT NULL,
 description VARCHAR(500) NOT NULL,
 user_id BIGINT UNSIGNED NULL,
 metadata JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_qcne_notification FOREIGN KEY(notification_id) REFERENCES qc_notifications(id) ON DELETE CASCADE,
 CONSTRAINT fk_qcne_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_qcne_timeline(notification_id,created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qc_notification_analysis_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 analysis_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(80) NOT NULL,
 description VARCHAR(500) NOT NULL,
 user_id BIGINT UNSIGNED NULL,
 metadata JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_qcnae_analysis FOREIGN KEY(analysis_id) REFERENCES qc_notification_analyses(id) ON DELETE CASCADE,
 CONSTRAINT fk_qcnae_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_qcnae_timeline(analysis_id,created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions(permission_key,name,module,status) VALUES
 ('notifications.analyze','Criar e editar analises de notificacoes','notifications','active'),
 ('notifications.action_plan','Gerenciar planos de acao','notifications','active'),
 ('notifications.close','Encerrar analises de notificacoes','notifications','active'),
 ('notifications.manage','Gerenciar excepcionalmente notificacoes e analises','notifications','active')
ON DUPLICATE KEY UPDATE name=VALUES(name),module=VALUES(module),status='active';

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p
WHERE (r.slug='administrador' AND p.permission_key IN ('notifications.analyze','notifications.action_plan','notifications.close','notifications.manage'))
   OR (r.slug='processamento' AND p.permission_key IN ('notifications.analyze','notifications.action_plan','notifications.close'));
