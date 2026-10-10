<?php
declare(strict_types=1);
namespace BloodHub\Controllers\Admin;

use BloodHub\Core\{AdminGuard,Auth,Csrf,Flash};
use BloodHub\Services\LaboratoryReportService;

final class ReportConfigController
{
    private const PERMISSION = 'admin.reports_settings.manage';

    public static function index():void
    {
        AdminGuard::enforce(self::PERMISSION);
        $eligibility=LaboratoryReportService::eligibilitySettings();
        $flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();
        $pageTitle='Configuração de Laudos';
        $pageSubtitle='Defina os contextos institucionais que podem gerar laudos.';
        require dirname(__DIR__,2).'/Views/admin/reports/index.php';
    }
    public static function save():void
    {
        AdminGuard::enforce(self::PERMISSION);
        if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada. Tente novamente.');self::back();}
        LaboratoryReportService::setEligibilitySettings($_POST['eligibility']??[]);
        Flash::set('success','Elegibilidade de laudos atualizada.');self::back();
    }
    private static function back():never{header('Location: /admin/reports');exit;}
}
