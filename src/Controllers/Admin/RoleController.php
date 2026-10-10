<?php
namespace BloodHub\Controllers\Admin;

use BloodHub\Core\AdminGuard;
use BloodHub\Core\Auth;
use BloodHub\Core\Csrf;
use BloodHub\Core\Database;
use BloodHub\Core\Flash;
use PDO;

final class RoleController
{
    private const PERMISSION = 'admin.roles.manage';
    public static function index(): void
    {
        AdminGuard::enforce(self::PERMISSION);
        $roles=Database::connection()->query('SELECT r.*,COUNT(u.id) user_count FROM roles r LEFT JOIN users u ON u.role_id=r.id GROUP BY r.id ORDER BY r.name')->fetchAll();
        self::view('index', ['roles'=>$roles,'pageTitle'=>'Perfis']);
    }
    public static function create(): void { AdminGuard::enforce(self::PERMISSION); self::form(null,[]); }
    public static function store(): void { AdminGuard::enforce(self::PERMISSION); self::save(null); }
    public static function edit(): void { AdminGuard::enforce(self::PERMISSION); $r=self::requested(); if(!$r){self::notFound();return;} self::form($r,[]); }
    public static function update(): void { AdminGuard::enforce(self::PERMISSION); $r=self::requested(); if(!$r){self::notFound();return;} self::save($r); }

    private static function save(?array $current): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) { Flash::set('error','Sessão expirada. Tente novamente.'); self::redirect($current?'/admin/roles/edit?id='.$current['id']:'/admin/roles/create'); }
        $name=trim((string)($_POST['name']??'')); $slug=trim((string)($_POST['slug']??''));
        if($slug==='') $slug=self::slugify($name); else $slug=self::slugify($slug);
        $data=['name'=>$name,'slug'=>$slug,'description'=>trim((string)($_POST['description']??'')) ?: null,'status'=>(string)($_POST['status']??'')];
        $errors=[];
        if($name==='') $errors[]='O nome é obrigatório.';
        if($slug==='') $errors[]='Informe um slug válido.';
        if(!in_array($data['status'],['active','inactive'],true)) $errors[]='Status inválido.';
        $s=Database::connection()->prepare('SELECT id FROM roles WHERE slug=:slug AND id<>:id'); $s->execute(['slug'=>$slug,'id'=>$current['id']??0]); if($s->fetchColumn())$errors[]='Este slug já está em uso.';
        if($errors){self::form(array_merge($current??[],$data),$errors);return;}
        $pdo=Database::connection();
        if($current){$data['id']=$current['id'];$pdo->prepare('UPDATE roles SET name=:name,slug=:slug,description=:description,status=:status WHERE id=:id')->execute($data);Auth::registerAudit('role.update','roles',(int)$current['id']);Flash::set('success','Perfil atualizado com sucesso.');}
        else{$pdo->prepare('INSERT INTO roles(name,slug,description,status) VALUES(:name,:slug,:description,:status)')->execute($data);$id=(int)$pdo->lastInsertId();Auth::registerAudit('role.create','roles',$id);Flash::set('success','Perfil criado com sucesso.');}
        self::redirect('/admin/roles');
    }
    private static function slugify(string $value): string { $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value) ?: $value; return trim(preg_replace('/[^a-z0-9]+/','-',mb_strtolower($ascii))??'', '-'); }
    private static function requested(): ?array{$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);if(!$id)return null;$s=Database::connection()->prepare('SELECT * FROM roles WHERE id=:id');$s->execute(['id'=>$id]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
    private static function form(?array $role,array $errors):void{self::view('form',compact('role','errors')+['pageTitle'=>$role?'Editar Perfil':'Novo Perfil']);}
    private static function view(string $name,array $vars):void{extract($vars);$flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();require dirname(__DIR__,2).'/Views/admin/roles/'.$name.'.php';}
    private static function redirect(string $url):never{header('Location: '.$url);exit;}
    private static function notFound():void{http_response_code(404);echo 'Perfil não encontrado.';}
}
