<?php
namespace BloodHub\Core;

final class AdminGuard
{
    public static function enforce(?string $permission = null): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }
        if ($permission !== null && !Permission::can($permission)) {
            http_response_code(403);
            echo 'Acesso negado.';
            exit;
        }
        if ($permission === null && !Auth::isAdministrator()) {
            http_response_code(403);
            echo 'Acesso negado.';
            exit;
        }
    }
}
