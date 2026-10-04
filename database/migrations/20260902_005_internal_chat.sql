-- Chat interno nativo. Seguro para reaplicacao: tabelas, permissoes e conversa geral sao idempotentes.
CREATE TABLE IF NOT EXISTS chat_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('general','private') NOT NULL,
    name VARCHAR(180) NULL,
    slug VARCHAR(80) NULL,
    private_key VARCHAR(80) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_chat_conversations_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_chat_conversations_slug (slug),
    UNIQUE KEY uq_chat_conversations_private_key (private_key),
    INDEX idx_chat_conversations_updated (updated_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_participants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_read_at DATETIME(6) NULL,
    CONSTRAINT fk_chat_participants_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_participants_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_chat_participant (conversation_id, user_id),
    INDEX idx_chat_participants_user (user_id, conversation_id)
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

ALTER TABLE chat_conversations MODIFY updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6);
ALTER TABLE chat_participants MODIFY last_read_at DATETIME(6) NULL;
ALTER TABLE chat_messages MODIFY created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6);

INSERT INTO permissions (permission_key, name, module, description, status) VALUES
('chat.view', 'Visualizar chat interno', 'chat', 'Acessar conversas das quais participa', 'active'),
('chat.send', 'Enviar mensagens no chat', 'chat', 'Enviar mensagens em conversas autorizadas', 'active'),
('chat.private', 'Iniciar conversa privada', 'chat', 'Iniciar conversa com outro usuario ativo', 'active')
ON DUPLICATE KEY UPDATE name=VALUES(name), module=VALUES(module), description=VALUES(description), status='active';

-- O chat operacional fica disponivel a todos os perfis ativos, sem depender do nome do perfil.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.status='active' AND p.permission_key IN ('chat.view','chat.send','chat.private');

INSERT INTO chat_conversations (type, name, slug, created_by)
VALUES ('general', 'Geral', 'general', NULL)
ON DUPLICATE KEY UPDATE name='Geral', type='general';

INSERT IGNORE INTO chat_participants (conversation_id, user_id, joined_at)
SELECT c.id, u.id, NOW() FROM chat_conversations c CROSS JOIN users u
WHERE c.slug='general' AND u.status='active';
