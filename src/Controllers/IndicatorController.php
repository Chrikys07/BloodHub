<?php
declare(strict_types=1);
namespace BloodHub\Controllers;

use BloodHub\Core\{AdminGuard,Auth,Csrf,Database,Flash};
use BloodHub\Services\{ClientScopeService,IndicatorService};
use PDO;

final class IndicatorController
{
    public static function index():void{AdminGuard::enforce(IndicatorService::VIEW);self::view('index',['indicators'=>IndicatorService::all(),'pageTitle'=>'Indicadores','pageSubtitle'=>'Acompanhe desempenho, metas institucionais, análises e planos de ação.']);}
    public static function withinSpecifications():void{self::show('hemocomponents-within-specifications');}
    public static function plateletMean():void{self::show('platelet-mean-concentration');}
    private static function show(string$slug):void
    {
        AdminGuard::enforce(IndicatorService::VIEW);$indicator=IndicatorService::getIndicator($slug);
        if(!$indicator||!(int)$indicator['active']){http_response_code(404);echo'Indicador não encontrado.';return;}
        $year=self::year($_GET['year']??date('Y'));$month=self::month($_GET['month']??date('n'));
        try{$scope=ClientScopeService::resolve($_GET['client_type']??'all',$_GET['client_id']??null);$calculationError=null;}
        catch(\Throwable$e){$scope=ClientScopeService::resolve();$calculationError=$e->getMessage();}
        $clients=ClientScopeService::getVisibleClientsForUser(true);
        try{$dashboard=IndicatorService::dashboard($indicator,$year,$month,$scope);}
        catch(\Throwable$e){$calculationError=$e->getMessage();$empty=['value'=>null,'quantity'=>0,'rows'=>[],'target'=>null,'status'=>'no_data'];$dashboard=['months'=>array_fill(1,12,$empty),'month'=>$empty,'analysis'=>IndicatorService::getAnalysis((int)$indicator['id'],$year,$month,$scope),'pending'=>IndicatorService::getPendingActions((int)$indicator['id'],$year,$month,$scope),'can_manage'=>IndicatorService::canManageAnalysis((int)$indicator['id'])];}
        $users=Database::connection()->query("SELECT id,name FROM users WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        self::view('show',compact('indicator','dashboard','year','month','users','calculationError','scope','clients')+['pageTitle'=>$indicator['name'],'pageSubtitle'=>'Visão mensal e série histórica anual por escopo de cliente.']);
    }
    public static function saveAnalysis():void
    {
        AdminGuard::enforce(IndicatorService::VIEW);$slug=trim((string)($_POST['indicator_slug']??''));$indicator=IndicatorService::getIndicator($slug);$year=self::year($_POST['year']??0);$month=self::month($_POST['month']??0);
        try{$scope=ClientScopeService::resolve($_POST['client_type']??'all',$_POST['client_id']??null);}catch(\Throwable$e){Flash::set('error',$e->getMessage());$scope=ClientScopeService::resolve();}
        if(!$indicator||!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada ou indicador inválido.');self::redirect($slug,$year,$month,$scope);}
        try{IndicatorService::saveAnalysis((int)$indicator['id'],$year,$month,trim((string)($_POST['analysis_text']??'')),self::normalizeActions((array)($_POST['actions']??[])),$scope);Flash::set('success','Análise e plano de ação salvos.');}catch(\Throwable$e){Flash::set('error',$e->getMessage());}
        self::redirect($slug,$year,$month,$scope);
    }
    private static function normalizeActions(array$input):array{$out=[];foreach($input as$a)if(is_array($a))$out[]=$a;return$out;}
    private static function year(mixed$v):int{$y=(int)$v;return$y>=2000&&$y<=2100?$y:(int)date('Y');}
    private static function month(mixed$v):int{$m=(int)$v;return$m>=1&&$m<=12?$m:(int)date('n');}
    private static function redirect(string$slug,int$year,int$month,array$scope):never{header('Location: /indicators/'.rawurlencode($slug).'?'.http_build_query(['year'=>$year,'month'=>$month,'client_type'=>$scope['type'],'client_id'=>$scope['client_id']]));exit;}
    private static function view(string$name,array$vars):void{extract($vars);$flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();require dirname(__DIR__).'/Views/indicators/'.$name.'.php';}
}
