<?php
declare(strict_types=1);
namespace BloodHub\Controllers\Admin;
use BloodHub\Core\{AdminGuard,Auth,Csrf,Flash};
use BloodHub\Services\EquipmentService;
final class EquipmentController
{
 public static function index():void{AdminGuard::enforce('laboratory_equipment.manage');$data=EquipmentService::catalogs();extract($data);$flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();$pageTitle='Equipamentos';require dirname(__DIR__,2).'/Views/admin/equipment/index.php';}
 public static function save():void{self::guard();try{$id=EquipmentService::saveEquipment($_POST);Auth::registerAudit('LABORATORY_EQUIPMENT_SAVED','laboratory_equipment',$id);Flash::set('success','Equipamento salvo com sucesso.');}catch(\Throwable$e){Flash::set('error',$e->getMessage());}self::back();}
 public static function assign():void{self::guard();try{$id=EquipmentService::assign($_POST);Auth::registerAudit('TEST_EQUIPMENT_ASSIGNMENT_SAVED','test_equipment_assignments',$id);Flash::set('success','Vínculo de equipamento salvo.');}catch(\Throwable$e){Flash::set('error',$e->getMessage());}self::back();}
 public static function toggle():void{self::guard();try{EquipmentService::toggleEquipment((int)($_POST['id']??0),($_POST['active']??'0')==='1');Flash::set('success','Status do equipamento atualizado.');}catch(\Throwable$e){Flash::set('error',$e->getMessage());}self::back();}
 public static function delete():void{self::guard();try{EquipmentService::deleteEquipment((int)($_POST['id']??0));Flash::set('success','Equipamento excluído definitivamente.');}catch(\Throwable$e){Flash::set('error',$e->getMessage());}self::back();}
 public static function toggleAssignment():void{self::guard();try{EquipmentService::toggleAssignment((int)($_POST['id']??0),($_POST['active']??'0')==='1');Flash::set('success','Status do vínculo atualizado.');}catch(\Throwable$e){Flash::set('error',$e->getMessage());}self::back();}
 public static function deleteAssignment():void{self::guard();try{EquipmentService::deleteAssignment((int)($_POST['id']??0));Flash::set('success','Vínculo excluído definitivamente.');}catch(\Throwable$e){Flash::set('error',$e->getMessage());}self::back();}
 private static function guard():void{AdminGuard::enforce('laboratory_equipment.manage');if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada.');self::back();}}
 private static function back():never{header('Location: /admin/equipment');exit;}
}
