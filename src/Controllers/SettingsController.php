<?php
declare(strict_types=1);
namespace BloodHub\Controllers;

use BloodHub\Core\{AdminGuard, Auth, Flash};

final class SettingsController
{
    public static function index(): void
    {
        AdminGuard::enforce();
        $pageTitle = 'Configuração';
        $userAuth = Auth::user();
        $flash = Flash::pull();
        require dirname(__DIR__) . '/Views/settings/index.php';
    }
}
