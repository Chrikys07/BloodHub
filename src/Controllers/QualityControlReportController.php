<?php
declare(strict_types=1);
namespace BloodHub\Controllers;

use BloodHub\Core\{AdminGuard,Auth,Permission};
use BloodHub\Services\QualityControlReportService as Report;

final class QualityControlReportController
{
    public static function index():void
    {
        AdminGuard::enforce(Report::PERMISSION);$filters=Report::getFilters($_GET);$page=max(1,(int)($_GET['page']??1));$report=Report::search($filters,$page);$summary=Report::getSummary($filters);$canViewNotifications=Permission::can('notifications.view');$userAuth=Auth::user();$flash=[];$pageTitle='Relatório Gerencial';$pageSubtitle='Consulte os resultados do Controle de Qualidade de Hemocomponentes por período, origem e hemocomponente.';require dirname(__DIR__).'/Views/reports/quality_control.php';
    }
    public static function detail():void
    {
        AdminGuard::enforce(Report::PERMISSION);$sample=Report::find((int)($_GET['id']??0));if(!$sample){http_response_code(404);echo'Amostra não encontrada ou fora do seu escopo.';return;}$canViewNotifications=Permission::can('notifications.view');$userAuth=Auth::user();$flash=[];$pageTitle='Detalhe do Controle de Qualidade';$pageSubtitle='Consulta somente leitura do resultado vigente e da especificação histórica.';require dirname(__DIR__).'/Views/reports/quality_control_detail.php';
    }
    public static function csv():void
    {
        AdminGuard::enforce(Report::PERMISSION);$f=Report::getFilters($_GET);$data=Report::search($f,1,true);$filename='relatorio-cq-'.$f['from'].'-'.$f['to'].'.csv';header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$filename.'"');$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");foreach($data['groups']as$group){fputcsv($out,[$group['component_code'].' - '.$group['component_name']],';');$head=['Recebimento','Nº da doação','Hemocomponente','Produção','Origem','Marca da bolsa','Código LCQH','Preservante'];foreach($group['columns']as$c){$head[]=$c['name'];$head[]=$c['name'].' - Status';}fputcsv($out,$head,';');foreach($group['samples']as$s){$row=[self::date($s['received_at'],true),$s['donation_number'],$s['component_code'],self::date($s['production_date']),$s['origin_name'],$s['bag_brand'],$s['lcqh_code'],$s['preservative_name']];foreach($group['columns']as$c){$r=$s['results'][(int)$c['id']]??null;$row[]=$r['display']??'';$row[]=$r?self::status($r['status']):'';}fputcsv($out,$row,';');}fputcsv($out,[],';');}fclose($out);Auth::registerAudit('quality_control_report.csv_exported','samples',null,null,['filters'=>$f,'records'=>$data['total']]);
    }
    public static function pdf():void
    {
        AdminGuard::enforce(Report::PERMISSION);$filters=Report::getFilters($_GET);$summary=Report::getSummary($filters);$testSummary=Report::getTestSummary($filters);$nonconformities=Report::getNonconformities($filters);Auth::registerAudit('quality_control_report.pdf_generated','samples',null,null,['filters'=>$filters,'records'=>$summary['records']]);require dirname(__DIR__).'/Views/reports/quality_control_pdf.php';
    }
    private static function date(?string$v,bool$time=false):string{return$v?date($time?'d/m/Y H:i':'d/m/Y',strtotime($v)):'';}
    private static function status(string$s):string{return match($s){'CONFORMING'=>'Conforme','NONCONFORMING'=>'Fora da especificação','NO_SPECIFICATION'=>'Sem especificação aplicável',default=>'Não avaliado'};}
}
