<?php
declare(strict_types=1);
namespace BloodHub\Controllers;

use BloodHub\Core\{Auth, Configuration, Flash};

final class SettingsController
{
    public static function index(): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }
        $modules = Configuration::modules();
        if ($modules === []) {
            http_response_code(403);
            echo 'Acesso negado.';
            exit;
        }
        $pageTitle = 'Configuração';
        $userAuth = Auth::user();
        $flash = Flash::pull();
        require dirname(__DIR__) . '/Views/settings/index.php';
    }
}
