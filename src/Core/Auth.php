<?php
namespace BloodHub\Core;

use PDO;

final class Auth
{
    public static function user(): ?array { return $_SESSION['user'] ?? null; }
    public static function check(): bool { return !empty($_SESSION['user']); }

    public static function attempt(string $email, string $password): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.professional_name, u.professional_council, u.professional_registration, u.email, u.photo_path, u.client_id, u.password_hash, u.status,
                    r.id AS role_id, r.name AS role_name, r.slug AS role_slug
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => mb_strtolower(trim($email))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) return false;
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        session_regenerate_id(true);
        self::registerAudit('auth.login', 'users', (int)$user['id']);
        return true;
    }

    public static function isAdministrator(): bool
    {
        $user = self::user();
        return $user !== null && (
            ($user['role_slug'] ?? null) === 'administrador'
            || mb_strtolower((string)($user['role_name'] ?? '')) === 'administrador'
        );
    }

    public static function logout(): void
    {
        if (self::check()) self::registerAudit('auth.logout', 'users', (int)$_SESSION['user']['id']);
        $_SESSION = [];
        session_destroy();
    }

    public static function refreshUser(): void
    {
        $id = (int)($_SESSION['user']['id'] ?? 0);
        if (!$id) return;
        $stmt = Database::connection()->prepare(
            'SELECT u.id, u.name, u.professional_name, u.professional_council, u.professional_registration, u.email, u.photo_path, u.client_id, u.status,
                    r.id AS role_id, r.name AS role_name, r.slug AS role_slug
             FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) $_SESSION['user'] = $user;
    }

    public static function registerAudit(string $action, ?string $entityType = null, ?int $entityId = null, ?array $before = null, ?array $after = null): void
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'INSERT INTO audit_logs
                 (user_id, action, entity_type, entity_id, before_data, after_data, ip_address, user_agent, created_at)
                 VALUES (:user_id, :action, :entity_type, :entity_id, :before_data, :after_data, :ip_address, :user_agent, NOW())'
            );
            $stmt->execute([
                'user_id' => $_SESSION['user']['id'] ?? null,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'before_data' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'after_data' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ]);
        } catch (\Throwable $e) {}
    }
}
