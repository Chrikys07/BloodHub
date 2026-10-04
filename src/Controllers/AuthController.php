<?php
namespace BloodHub\Controllers;

use BloodHub\Core\Auth;
use BloodHub\Core\Csrf;

final class AuthController
{
    public static function showLogin(): void
    {
        if (Auth::check()) { header('Location: /'); exit; }
        $csrf = Csrf::token();
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);
        require dirname(__DIR__) . '/Views/auth/login.php';
    }

    public static function login(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Sessão expirada. Atualize a página e tente novamente.';
            header('Location: /login'); exit;
        }
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$email || !$password || !Auth::attempt($email, $password)) {
            $_SESSION['flash_error'] = 'E-mail ou senha inválidos.';
            header('Location: /login'); exit;
        }
        header('Location: /'); exit;
    }

    public static function logout(): void
    {
        Auth::logout();
        header('Location: /login'); exit;
    }
}
