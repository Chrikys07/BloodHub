-- HubChat: grupos automaticos por unidade, sem alterar conversas existentes.
ALTER TABLE chat_conversations
    MODIFY type ENUM('general','group','private') NOT NULL,
    ADD COLUMN IF NOT EXISTS unit_id BIGINT UNSIGNED NULL AFTER private_key,
    ADD UNIQUE INDEX IF NOT EXISTS uq_chat_conversations_unit (unit_id);

SET @hubchat_fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chat_conversations'
      AND CONSTRAINT_NAME = 'fk_chat_conversations_unit'
);
SET @hubchat_fk_sql = IF(
    @hubchat_fk_exists = 0,
    'ALTER TABLE chat_conversations ADD CONSTRAINT fk_chat_conversations_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE hubchat_fk_statement FROM @hubchat_fk_sql;
EXECUTE hubchat_fk_statement;
DEALLOCATE PREPARE hubchat_fk_statement;

INSERT INTO permissions (permission_key, name, module, description, status)
VALUES ('chat.group', 'Participar de grupos do HubChat', 'chat', 'Participar dos grupos das unidades as quais o usuario esta vinculado', 'active')
ON DUPLICATE KEY UPDATE name=VALUES(name), module=VALUES(module), description=VALUES(description), status='active';

UPDATE permissions SET name='Visualizar HubChat' WHERE permission_key='chat.view';
UPDATE permissions SET name='Enviar mensagens no HubChat' WHERE permission_key='chat.send';

-- Materializa em user_units os vinculos de unidade principal ja existentes.
INSERT IGNORE INTO user_units (user_id, unit_id)
SELECT id, primary_unit_id FROM users WHERE primary_unit_id IS NOT NULL;

-- Disponibiliza a capacidade sem depender do nome do perfil. A unidade ainda limita o acesso.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.status='active' AND p.permission_key='chat.group';
