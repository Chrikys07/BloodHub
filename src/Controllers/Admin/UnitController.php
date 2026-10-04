<?php
declare(strict_types=1);

namespace BloodHub\Controllers\Admin;

use BloodHub\Core\AdminGuard;
use BloodHub\Core\Auth;
use BloodHub\Core\Csrf;
use BloodHub\Core\Database;
use BloodHub\Core\Flash;
use PDO;

final class UnitController
{
    private const PERMISSION = 'admin.units.manage';
    private const TYPES = ['lcqh','processing','transfusion_agency','management','other'];

    public static function index(): void
    {
        AdminGuard::enforce(self::PERMISSION);
        $units=Database::connection()->query('SELECT u.*,c.name client_name FROM units u LEFT JOIN clients c ON c.id=u.client_id ORDER BY u.name')->fetchAll();
        self::view('index',['units'=>$units,'pageTitle'=>'Unidades']);
    }
    public static function create():void { AdminGuard::enforce(self::PERMISSION);self::form(null,[]); }
    public static function store():void { AdminGuard::enforce(self::PERMISSION);self::save(null); }
    public static function edit():void { AdminGuard::enforce(self::PERMISSION);$unit=self::requested();if(!$unit){self::notFound();return;}self::form($unit,[]); }
    public static function update():void { AdminGuard::enforce(self::PERMISSION);$unit=self::requested();if(!$unit){self::notFound();return;}self::save($unit); }

    private static function save(?array $current):void
    {
        if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada. Tente novamente.');self::redirect($current?'/admin/units/edit?id='.(int)$current['id']:'/admin/units/create');}
        $clientId=filter_var($_POST['client_id']??null,FILTER_VALIDATE_INT)?:null;
        $email=mb_strtolower(trim((string)($_POST['email']??'')));
        $data=['client_id'=>$clientId,'name'=>trim((string)($_POST['name']??'')),'unit_type'=>(string)($_POST['unit_type']??''),'code'=>trim((string)($_POST['code']??''))?:null,'email'=>$email!==''?$email:null,'status'=>(string)($_POST['status']??'')];
        $errors=[];
        if($data['name']==='')$errors[]='O nome é obrigatório.';
        if(!$current&&$data['email']===null)$errors[]='O e-mail da unidade é obrigatório para novas unidades.';
        if($data['email']!==null&&!filter_var($data['email'],FILTER_VALIDATE_EMAIL))$errors[]='Informe um e-mail da unidade válido.';
        if(!in_array($data['unit_type'],self::TYPES,true))$errors[]='Selecione um tipo de unidade válido.';
        if(!in_array($data['status'],['active','inactive'],true))$errors[]='Selecione um status válido.';
        if($clientId){$s=Database::connection()->prepare('SELECT 1 FROM clients WHERE id=:id');$s->execute(['id'=>$clientId]);if(!$s->fetchColumn())$errors[]='O cliente selecionado não existe.';}
        if($errors){self::form(array_merge($current??[],$data),$errors);return;}
        $pdo=Database::connection();
        if($current){$data['id']=$current['id'];$pdo->prepare('UPDATE units SET client_id=:client_id,name=:name,unit_type=:unit_type,code=:code,email=:email,status=:status WHERE id=:id')->execute($data);$after=$data;unset($after['id']);Auth::registerAudit('unit.update','units',(int)$current['id'],$current,$after);if(($current['email']??null)!==$data['email'])Auth::registerAudit('UNIT_EMAIL_CHANGED','units',(int)$current['id'],['email'=>$current['email']??null],['email'=>$data['email']]);Flash::set('success','Unidade atualizada com sucesso.');}
        else{$pdo->prepare('INSERT INTO units(client_id,name,unit_type,code,email,status) VALUES(:client_id,:name,:unit_type,:code,:email,:status)')->execute($data);$id=(int)$pdo->lastInsertId();Auth::registerAudit('unit.create','units',$id,null,$data);Flash::set('success','Unidade criada com sucesso.');}
        self::redirect('/admin/units');
    }
    private static function form(?array $unit,array $errors):void{$clients=Database::connection()->query("SELECT id,name,status FROM clients ORDER BY status='active' DESC,name")->fetchAll();self::view('form',compact('unit','errors','clients')+['pageTitle'=>$unit?'Editar Unidade':'Nova Unidade']);}
    private static function requested():?array{$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);if(!$id)return null;$s=Database::connection()->prepare('SELECT * FROM units WHERE id=:id');$s->execute(['id'=>$id]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
    private static function view(string $name,array $vars):void{extract($vars);$flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();require dirname(__DIR__,2).'/Views/admin/units/'.$name.'.php';}
    private static function redirect(string $url):never{header('Location: '.$url);exit;}
    private static function notFound():void{http_response_code(404);echo 'Unidade não encontrada.';}
}
