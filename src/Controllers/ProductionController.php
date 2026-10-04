<?php
declare(strict_types=1);
namespace BloodHub\Controllers;

use BloodHub\Core\AdminGuard;
use BloodHub\Core\Auth;
use BloodHub\Core\Csrf;
use BloodHub\Core\Flash;
use BloodHub\Core\Permission;
use BloodHub\Core\ProductionAccess;
use BloodHub\Services\ProductionService;
use BloodHub\Services\QcMonthlyClosureService;

final class ProductionController
{
    public static function index():void
    {
        AdminGuard::enforce('production.view');$units=ProductionAccess::units();
        if(!$units){http_response_code(403);echo'Acesso negado: nenhuma unidade de processamento autorizada.';return;}
        $unitId=(int)($_GET['unit_id']??$units[0]['id']);if(!ProductionAccess::canUseUnit($unitId))$unitId=(int)$units[0]['id'];
        $date=self::validDate((string)($_GET['date']??date('Y-m-d')))?(string)($_GET['date']??date('Y-m-d')):date('Y-m-d');
        $month=max(1,min(12,(int)($_GET['month']??substr($date,5,2))));$year=(int)($_GET['year']??substr($date,0,4));if($year<2000||$year>2100)$year=(int)date('Y');
        $componentId=filter_var($_GET['component_id']??null,FILTER_VALIDATE_INT)?:null;$components=ProductionService::eligibleComponents();$daily=ProductionService::getDailyProduction($unitId,$date);
        $monthly=ProductionService::getMonthlyProduction($unitId,$year,$month,$componentId);$matrix=ProductionService::getProductionByComponent($unitId,$year,$month);if($componentId)$matrix=array_values(array_filter($matrix,fn($r)=>(int)$r['id']===$componentId));
        $monthStart=sprintf('%04d-%02d-01',$year,$month);$monthEnd=date('Y-m-t',strtotime($monthStart));
        self::view(['units'=>$units,'unitId'=>$unitId,'date'=>$date,'month'=>$month,'year'=>$year,'componentId'=>$componentId,'components'=>$components,'daily'=>$daily,'monthly'=>$monthly,'matrix'=>$matrix,'days'=>(int)date('t',strtotime($monthStart)),'canSave'=>Permission::can('production.create')||Permission::can('production.edit'),'isAdmin'=>ProductionAccess::isGlobal(),'kpis'=>['today'=>array_sum($daily),'informed'=>count($daily),'pending'=>max(0,count($components)-count($daily)),'month'=>ProductionService::getProductionTotal($unitId,$monthStart,$monthEnd)]]);
    }

    public static function save():void
    {
        AdminGuard::enforce('production.view');
        if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada. Tente novamente.');self::redirect('/production');}
        $unitId=filter_var($_POST['unit_id']??null,FILTER_VALIDATE_INT)?:0;$date=trim((string)($_POST['production_date']??''));$errors=[];
        if(!Permission::can('production.create')&&!Permission::can('production.edit'))$errors[]='Você não possui permissão para salvar produção.';
        if(!$unitId||!ProductionAccess::canUseUnit((int)$unitId))$errors[]='Unidade de processamento inválida ou fora do seu escopo.';
        if(!self::validDate($date))$errors[]='Informe uma data de produção válida.';elseif($date>date('Y-m-d'))$errors[]='Não é permitido registrar produção em data futura.';
        if(self::validDate($date)&&QcMonthlyClosureService::isClosed((int)$unitId,(int)substr($date,0,4),(int)substr($date,5,2)))$errors[]='O período está fechado. Solicite a reabertura antes de alterar a produção.';
        $eligible=[];foreach(ProductionService::eligibleComponents() as$c)$eligible[(int)$c['id']]=true;$posted=$_POST['quantity']??[];$quantities=[];
        foreach($eligible as$id=>$_){$raw=trim((string)($posted[$id]??''));if($raw===''){$quantities[$id]=null;continue;}if(!preg_match('/^\d+$/',$raw)){$errors[]='As quantidades devem ser números inteiros maiores ou iguais a zero.';break;}$quantities[$id]=(int)$raw;}
        foreach(array_keys((array)$posted) as$id)if(!isset($eligible[(int)$id])){$errors[]='Foi informado um hemocomponente inválido ou inativo.';break;}
        $back='/production?unit_id='.$unitId.'&date='.urlencode($date);
        if($errors){Flash::set('error',implode(' ',$errors));self::redirect($back);}
        try{ProductionService::saveDailyProduction((int)$unitId,$date,$quantities);Flash::set('success','Produção salva com sucesso.');}catch(\Throwable $e){Flash::set('error','Não foi possível salvar a produção. Nenhuma alteração foi aplicada.');}
        self::redirect($back);
    }

    private static function validDate(string $date):bool{$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);return$d&&$d->format('Y-m-d')===$date;}
    private static function view(array $vars):void{extract($vars);$flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();$pageTitle='Produção';$pageSubtitle='Registre e acompanhe diariamente a produção de hemocomponentes.';require dirname(__DIR__).'/Views/production/index.php';}
    private static function redirect(string$url):never{header('Location: '.$url);exit;}
}
