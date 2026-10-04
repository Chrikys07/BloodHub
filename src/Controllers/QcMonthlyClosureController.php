<?php
declare(strict_types=1);
namespace BloodHub\Controllers;

use BloodHub\Core\{AdminGuard,Auth,Csrf,Flash,MonthlyClosureAccess,Permission};
use BloodHub\Services\QcMonthlyClosureService as Closure;

final class QcMonthlyClosureController
{
    public static function index():void
    {
        AdminGuard::enforce(Closure::VIEW);[$year,$month]=self::period($_GET);$units=MonthlyClosureAccess::units();if(!$units){http_response_code(403);echo'Acesso negado: nenhuma unidade de processamento autorizada.';return;}$requested=(int)($_GET['unit_id']??0);$unitId=$requested&&MonthlyClosureAccess::canUseUnit($requested)?$requested:0;$statusFilter=in_array($_GET['status']??'', ['OPEN','READY','CLOSED','REOPENED'],true)?$_GET['status']:'';
        $rows=[];foreach($units as$unit){if($unitId&&(int)$unit['id']!==$unitId)continue;$check=Closure::buildChecklist((int)$unit['id'],$year,$month);if($statusFilter&&$check['status']['status']!==$statusFilter)continue;$rows[]=$check;}
        $summary=['processings'=>count($rows),'closed'=>0,'pending'=>0,'reopened'=>0];foreach($rows as$r){$s=$r['status']['status'];if($s==='CLOSED')$summary['closed']++;elseif($s==='REOPENED'){$summary['reopened']++;$summary['pending']++;}else$summary['pending']++;}
        self::view('index',compact('year','month','units','unitId','statusFilter','rows','summary')+['canClose'=>Permission::can(Closure::CLOSE),'canReopen'=>Permission::can(Closure::REOPEN),'pageTitle'=>'Fechamento Mensal do CQ','pageSubtitle'=>'Confira as informações do período e registre formalmente o encerramento mensal do Controle de Qualidade.']);
    }

    public static function show():void
    {
        AdminGuard::enforce(Closure::VIEW);[$year,$month]=self::period($_GET);$unitId=(int)($_GET['unit_id']??0);self::assertUnit($unitId);$checklist=Closure::buildChecklist($unitId,$year,$month);$history=$checklist['status']['id']?Closure::getHistory((int)$checklist['status']['id']):[];$can=Closure::canClose($checklist);self::view('show',compact('year','month','unitId','checklist','history','can')+['canClose'=>Permission::can(Closure::CLOSE),'canReopen'=>Permission::can(Closure::REOPEN),'pageTitle'=>'Fechamento Mensal do CQ','pageSubtitle'=>'Confira as informações do período e registre formalmente o encerramento mensal do Controle de Qualidade.']);
    }

    public static function close():void
    {
        AdminGuard::enforce(Closure::CLOSE);self::csrf();[$year,$month]=self::period($_POST);$unitId=(int)($_POST['unit_id']??0);self::assertUnit($unitId);try{$version=Closure::close($unitId,$year,$month,(int)Auth::user()['id'],trim((string)($_POST['closure_notes']??'')));Flash::set('success','Mês fechado com sucesso. Snapshot versão '.$version.' preservado.');}catch(\Throwable$e){Flash::set('error',$e->getMessage());}self::redirect($unitId,$year,$month);
    }

    public static function reopen():void
    {
        AdminGuard::enforce(Closure::REOPEN);self::csrf();[$year,$month]=self::period($_POST);$unitId=(int)($_POST['unit_id']??0);self::assertUnit($unitId);try{Closure::reopen($unitId,$year,$month,(int)Auth::user()['id'],trim((string)($_POST['reopen_reason']??'')));Flash::set('success','Mês reaberto. O snapshot anterior permanece no histórico.');}catch(\Throwable$e){Flash::set('error',$e->getMessage());}self::redirect($unitId,$year,$month);
    }

    public static function pdf():void
    {
        AdminGuard::enforce(Closure::VIEW);$closureId=(int)($_GET['closure_id']??0);$version=(int)($_GET['version']??0);$record=Closure::getVersion($closureId,$version);if(!$record||!MonthlyClosureAccess::canUseUnit((int)$record['unit_id'])){http_response_code(404);echo'Versão de fechamento não encontrada.';return;}Auth::registerAudit('QC_MONTHLY_CLOSURE_PDF_GENERATED','qc_monthly_closure_versions',(int)$record['id'],null,['closure_id'=>$closureId,'version'=>$version]);require dirname(__DIR__).'/Views/monthly_closures/pdf.php';
    }

    private static function period(array$input):array{$year=(int)($input['year']??date('Y'));$month=(int)($input['month']??date('n'));if($year<2000||$year>2100)$year=(int)date('Y');if($month<1||$month>12)$month=(int)date('n');return[$year,$month];}
    private static function assertUnit(int$id):void{if(!$id||!MonthlyClosureAccess::canUseUnit($id)){http_response_code(403);echo'Unidade fora do seu escopo.';exit;}}
    private static function csrf():void{if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada. Tente novamente.');header('Location: /monthly-closures');exit;}}
    private static function redirect(int$unit,int$year,int$month):never{header('Location: /monthly-closures/view?'.http_build_query(['unit_id'=>$unit,'year'=>$year,'month'=>$month]));exit;}
    private static function view(string$name,array$vars):void{extract($vars);$flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();require dirname(__DIR__).'/Views/monthly_closures/'.$name.'.php';}
}
