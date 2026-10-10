-- Separa a configuração administrativa de laudos das permissões operacionais.
INSERT INTO permissions (permission_key, name, module, description, status)
VALUES (
    'admin.reports_settings.manage',
    'Gerenciar laudos',
    'admin',
    'Gerenciar as configurações administrativas de laudos.',
    'active'
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    module = VALUES(module),
    description = VALUES(description),
    status = 'active';

-- Mantém o padrão do sistema: novas permissões administrativas são concedidas ao Administrador.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key = 'admin.reports_settings.manage'
WHERE r.slug = 'administrador' OR LOWER(r.name) = 'administrador';
