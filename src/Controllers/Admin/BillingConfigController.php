<?php
declare(strict_types=1);
namespace BloodHub\Controllers\Admin;
use BloodHub\Core\{AdminGuard,Csrf,Flash,Auth};
use BloodHub\Services\BillingService as Billing;

final class BillingConfigController
{
 public static function index():void{AdminGuard::enforce(Billing::CONFIG);$catalogs=Billing::catalogs();$pdo=\BloodHub\Core\Database::connection();$mappings=$pdo->query("SELECT m.*,s.service_code,s.name service_name,t.code test_code,t.name test_name,bc.code component_code FROM billing_service_mappings m JOIN billing_services s ON s.id=m.billing_service_id JOIN tests t ON t.id=m.test_id LEFT JOIN blood_components bc ON bc.id=m.blood_component_id ORDER BY m.active DESC,s.display_order,t.name,m.version DESC")->fetchAll(\PDO::FETCH_ASSOC);$pageTitle='Configuração — Faturamento';$pageSubtitle='Gerencie centros, serviços e o vínculo entre resultados finais e serviços institucionais.';$flash=Flash::pull();$csrf=Csrf::token();require dirname(__DIR__,2).'/Views/admin/billing/index.php';}
 public static function mapping():void{self::guard();try{Billing::saveMapping(['id'=>(int)($_POST['id']??0),'service_id'=>(int)($_POST['service_id']??0),'test_id'=>(int)($_POST['test_id']??0),'component_id'=>$_POST['component_id']??'','contexts'=>$_POST['contexts']??[],'active'=>$_POST['active']??null]);Flash::set('success','Associação de serviço salva.');}catch(\Throwable$e){Flash::set('error',$e->getMessage());}self::back();}
 public static function catalog():void{self::guard();try{Billing::saveCatalog((string)($_POST['type']??''),$_POST);Flash::set('success','Cadastro salvo.');}catch(\Throwable$e){Flash::set('error',$e->getMessage());}self::back();}
 public static function toggle():void{self::guard();try{Billing::toggleConfig((string)($_POST['type']??''),(int)($_POST['id']??0),($_POST['active']??'0')==='1');Flash::set('success','Status atualizado.');}catch(\Throwable$e){Flash::set('error',$e->getMessage());}self::back();}
 public static function delete():void{self::guard();try{Billing::deleteConfig((string)($_POST['type']??''),(int)($_POST['id']??0));Flash::set('success','Registro excluído definitivamente.');}catch(\Throwable$e){Flash::set('error',$e->getMessage());}self::back();}
 private static function guard():void{AdminGuard::enforce(Billing::CONFIG);if(!Csrf::validate($_POST['_csrf']??null))throw new \RuntimeException('Sessão expirada.');}
 private static function back():never{header('Location: /admin/billing');exit;}
}
