<?php
declare(strict_types=1);
namespace BloodHub\Controllers;

use BloodHub\Core\{AdminGuard,Auth,Flash,Permission,ProductionAccess};
use BloodHub\Services\SamplingScheduleService;

final class SamplingScheduleController
{
    public static function index():void
    {
        AdminGuard::enforce('sampling_schedule.view');$units=ProductionAccess::units();
        if(!$units){http_response_code(403);echo'Acesso negado: nenhuma unidade de processamento autorizada.';return;}
        $unitId=(int)($_GET['unit_id']??$units[0]['id']);if(!ProductionAccess::canUseUnit($unitId))$unitId=(int)$units[0]['id'];
        $month=(string)($_GET['month']??date('Y-m'));if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month))$month=date('Y-m');
        $weekday=isset($_GET['weekday'])?(int)$_GET['weekday']:(int)date('w');if($weekday<0||$weekday>6)$weekday=(int)date('w');
        $period=new \DateTimeImmutable($month.'-01');$rows=SamplingScheduleService::buildMonthlySummary($unitId,$month);foreach($rows as&$row)$row['suggestion']=SamplingScheduleService::buildDailySuggestion($row,$period,$weekday);unset($row);
        $totals=['produced'=>0,'required'=>0,'sent'=>0,'pending'=>0];foreach($rows as$r)foreach($totals as$k=>$_)$totals[$k]+=$r[$k];
        $endMonth=SamplingScheduleService::endOfMonthProjection($rows,$period);$unitName='';foreach($units as$u)if((int)$u['id']===$unitId)$unitName=$u['name'];
        $pageTitle='Cronograma de Envio de Amostras';$pageSubtitle='Acompanhe a produção e a quantidade de amostras que devem ser encaminhadas ao Controle de Qualidade.';$flash=Flash::pull();$userAuth=Auth::user();$isAdmin=Auth::isAdministrator()&&Permission::can('sampling_schedule.admin');
        require dirname(__DIR__).'/Views/sampling_schedule/index.php';
    }
}
