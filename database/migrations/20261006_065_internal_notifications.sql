CREATE TABLE IF NOT EXISTS user_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    event_key VARCHAR(80) NOT NULL,
    title VARCHAR(180) NOT NULL,
    description VARCHAR(500) NOT NULL,
    action_url VARCHAR(500) NULL,
    entity_type VARCHAR(80) NULL,
    entity_id BIGINT UNSIGNED NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_notifications_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_notifications_unread (user_id, read_at, created_at, id),
    INDEX idx_user_notifications_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE permissions
SET name='Visualizar relatório gerencial de CQ',
    description='Consulta e exportação dos resultados gerenciais de Controle de Qualidade.'
WHERE permission_key='reports.quality_control.view';
