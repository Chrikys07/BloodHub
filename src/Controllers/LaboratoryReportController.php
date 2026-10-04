<?php
declare(strict_types=1);
namespace BloodHub\Controllers;

use BloodHub\Core\{AdminGuard,Auth,Csrf,Flash,Permission};
use BloodHub\Services\LaboratoryReportService as Reports;

final class LaboratoryReportController
{
    public static function index():void{AdminGuard::enforce(Reports::VIEW);try{$rows=Reports::search($_GET);$catalogs=Reports::catalogs();$kpis=Reports::kpis($rows);$releaseUser=Reports::releaseUser();self::view('index',compact('rows','catalogs','kpis','releaseUser')+['labels'=>Reports::LABELS,'canManage'=>Permission::can(Reports::MANAGE),'canRevise'=>Permission::can(Reports::REVISE),'canCancel'=>Permission::can(Reports::CANCEL),'pageTitle'=>'Laudos','pageSubtitle'=>'Consulte, libere e disponibilize eletronicamente os laudos de hemocomponentes.']);}catch(\Throwable$e){http_response_code(403);echo htmlspecialchars($e->getMessage());}}
    public static function preview():void{AdminGuard::enforce(Reports::VIEW);try{$snapshot=Reports::preview((int)($_GET['sample_id']??0));$report=null;$preview=true;self::view('show',compact('snapshot','report','preview')+['pageTitle'=>'Prévia do laudo','pageSubtitle'=>'PRÉVIA - NÃO LIBERADO']);}catch(\Throwable$e){self::fail($e);}}
    public static function show():void{AdminGuard::enforce(Reports::VIEW);try{$report=Reports::find((int)($_GET['id']??0));$snapshot=['recipient'=>json_decode($report['recipient_snapshot_json'],true),'sample'=>json_decode($report['sample_snapshot_json'],true),'results'=>json_decode($report['results_snapshot_json'],true)];$preview=false;$versions=Reports::versions((int)$report['id']);self::view('show',compact('snapshot','report','preview','versions')+['pageTitle'=>'Laudo de Hemocomponente','pageSubtitle'=>'Versão '.$report['version'].' - '.Reports::LABELS[$report['status']]]);}catch(\Throwable$e){self::notFound();}}
    public static function release():void{AdminGuard::enforce(Reports::MANAGE);self::csrf();try{$id=Reports::release((int)($_POST['sample_id']??0),trim((string)($_POST['notes']??'')));Flash::set('success','Laudo liberado eletronicamente e PDF armazenado com sucesso.');header('Location: /reports/view?id='.$id);}catch(\Throwable$e){Flash::set('error',$e->getMessage());header('Location: /reports');}exit;}
    public static function revise():void{AdminGuard::enforce(Reports::REVISE);self::csrf();try{$previous=(int)($_POST['report_id']??0);$old=Reports::find($previous);$id=Reports::release((int)$old['sample_id'],trim((string)($_POST['notes']??'')),$previous,trim((string)($_POST['reason']??'')));Flash::set('success','Nova versão do laudo gerada. A versão anterior foi preservada.');header('Location: /reports/view?id='.$id);}catch(\Throwable$e){Flash::set('error',$e->getMessage());header('Location: /reports');}exit;}
    public static function cancel():void{AdminGuard::enforce(Reports::CANCEL);self::csrf();try{Reports::cancel((int)($_POST['report_id']??0),trim((string)($_POST['reason']??'')));Flash::set('success','Laudo cancelado. O histórico foi preservado.');}catch(\Throwable$e){Flash::set('error',$e->getMessage());}header('Location: /reports');exit;}
    public static function download():void{AdminGuard::enforce(Reports::VIEW);try{$r=Reports::find((int)($_GET['id']??0));$path=Reports::pdfPath($r);Auth::registerAudit('REPORT_DOWNLOADED','laboratory_reports',(int)$r['id'],null,['version'=>(int)$r['version']]);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.basename($path).'"');header('Content-Length: '.filesize($path));readfile($path);}catch(\Throwable$e){self::notFound();} }
    private static function csrf():void{if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada. Tente novamente.');header('Location: /reports');exit;}}
    private static function view(string$n,array$v):void{extract($v);$flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();require dirname(__DIR__).'/Views/laboratory_reports/'.$n.'.php';}
    private static function fail(\Throwable$e):void{Flash::set('error',$e->getMessage());header('Location: /reports');exit;}
    private static function notFound():void{http_response_code(404);echo'Laudo não encontrado.';}
}
