-- Fechamento mensal do Controle de Qualidade.
CREATE TABLE IF NOT EXISTS qc_monthly_closures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unit_id BIGINT UNSIGNED NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    status ENUM('OPEN','READY','CLOSED','REOPENED') NOT NULL DEFAULT 'OPEN',
    closed_by BIGINT UNSIGNED NULL,
    closed_at DATETIME NULL,
    closure_notes TEXT NULL,
    reopened_by BIGINT UNSIGNED NULL,
    reopened_at DATETIME NULL,
    reopen_reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_qc_monthly_closure_period (unit_id, year, month),
    KEY idx_qc_monthly_closure_status (status),
    KEY idx_qc_monthly_closure_period (year, month),
    CONSTRAINT fk_qcmc_unit FOREIGN KEY (unit_id) REFERENCES units(id),
    CONSTRAINT fk_qcmc_closed_by FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_qcmc_reopened_by FOREIGN KEY (reopened_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS qc_monthly_closure_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    closure_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    snapshot_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    snapshot_json LONGTEXT NOT NULL,
    closure_notes TEXT NULL,
    has_pending_items TINYINT(1) NOT NULL DEFAULT 0,
    closed_by BIGINT UNSIGNED NOT NULL,
    closed_at DATETIME NOT NULL,
    reopened_by BIGINT UNSIGNED NULL,
    reopened_at DATETIME NULL,
    reopen_reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_qcmcv_version (closure_id, version_number),
    KEY idx_qcmcv_closed_at (closed_at),
    CONSTRAINT fk_qcmcv_closure FOREIGN KEY (closure_id) REFERENCES qc_monthly_closures(id) ON DELETE CASCADE,
    CONSTRAINT fk_qcmcv_closed_by FOREIGN KEY (closed_by) REFERENCES users(id),
    CONSTRAINT fk_qcmcv_reopened_by FOREIGN KEY (reopened_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permissions(permission_key,name,description,module,status)
VALUES
('monthly_closure.view','Visualizar fechamento mensal de CQ','Consulta o checklist, snapshots e historico mensal.','monthly_closure','active'),
('monthly_closure.close','Fechar mes de CQ','Registra o aceite formal e uma nova versao do snapshot.','monthly_closure','active'),
('monthly_closure.reopen','Reabrir mes de CQ','Reabre um periodo fechado mediante motivo obrigatorio.','monthly_closure','active')
ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),module=VALUES(module),status='active';

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.permission_key='monthly_closure.view'
WHERE r.slug IN ('administrador','gestao','lcqh','processamento');

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.permission_key IN ('monthly_closure.close','monthly_closure.reopen')
WHERE r.slug='administrador';
