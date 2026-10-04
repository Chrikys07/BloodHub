<?php
namespace BloodHub\Core;

final class Permission
{
    public static function can(string $permissionKey): bool
    {
        $user = Auth::user();
        if (!$user || empty($user['role_id'])) return false;
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM role_permissions rp
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :role_id
               AND p.permission_key = :permission_key
               AND p.status = "active"
             LIMIT 1'
        );
        $stmt->execute(['role_id' => $user['role_id'], 'permission_key' => $permissionKey]);
        return (bool)$stmt->fetchColumn();
    }
}
