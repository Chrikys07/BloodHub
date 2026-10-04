<?php
namespace BloodHub\Controllers;

use BloodHub\Core\Auth;
use BloodHub\Core\Permission;
use BloodHub\Services\GlobalDashboardService;

final class DashboardController
{
    public static function index(): void
    {
        if (!Auth::check()) { header('Location: /login'); exit; }
        self::authorize();
        $user = Auth::user();
        $pageTitle = 'Dashboard Global';
        $pageSubtitle = 'Desempenho e monitoramento do Controle de Qualidade de Hemocomponentes.';
        $filters=GlobalDashboardService::getFilters($_GET);
        $dashboard=GlobalDashboardService::dashboard($filters,max(1,(int)($_GET['page']??1)),10);
        require dirname(__DIR__) . '/Views/dashboard/index.php';
    }

    public static function report():void
    {
        if(!Auth::check()){header('Location: /login');exit;}self::authorize();
        $filters=GlobalDashboardService::getFilters($_GET);$dashboard=GlobalDashboardService::dashboard($filters,1,10000);
        require dirname(__DIR__).'/Views/dashboard/report.php';
    }

    private static function authorize():void
    {
        if(!Permission::can('dashboard.global.view')){http_response_code(403);echo'Acesso negado.';exit;}
    }
}
