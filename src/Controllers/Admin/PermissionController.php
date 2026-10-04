<?php
namespace BloodHub\Controllers\Admin;

use BloodHub\Core\AdminGuard;
use BloodHub\Core\Auth;
use BloodHub\Core\Csrf;
use BloodHub\Core\Database;
use BloodHub\Core\Flash;

final class PermissionController
{
    public static function index(): void
    {
        AdminGuard::enforce(); $pdo=Database::connection();
        $roles=$pdo->query('SELECT id,name FROM roles ORDER BY name')->fetchAll();
        $roleId=filter_input(INPUT_GET,'role_id',FILTER_VALIDATE_INT) ?: (int)($roles[0]['id']??0);
        $permissions=$pdo->query("SELECT id,module,name,permission_key FROM permissions WHERE status='active' ORDER BY module,name")->fetchAll();
        $grouped=[];foreach($permissions as $permission)$grouped[$permission['module']][]=$permission;
        $s=$pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id=:id');$s->execute(['id'=>$roleId]);$selected=array_map('intval',$s->fetchAll(\PDO::FETCH_COLUMN));
        $pageTitle='Permissões';$flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();require dirname(__DIR__,2).'/Views/admin/permissions/index.php';
    }
    public static function update(): void
    {
        AdminGuard::enforce();
        if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada. Tente novamente.');self::redirect('/admin/permissions');}
        $roleId=filter_var($_POST['role_id']??null,FILTER_VALIDATE_INT);$pdo=Database::connection();
        $s=$pdo->prepare('SELECT id FROM roles WHERE id=:id');$s->execute(['id'=>$roleId]);if(!$roleId||!$s->fetchColumn()){Flash::set('error','Perfil inválido.');self::redirect('/admin/permissions');}
        $ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['permissions']??[])))));
        if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$s=$pdo->prepare("SELECT id FROM permissions WHERE status='active' AND id IN ($marks)");$s->execute($ids);$ids=array_map('intval',$s->fetchAll(\PDO::FETCH_COLUMN));}
        $pdo->beginTransaction();try{$pdo->prepare('DELETE FROM role_permissions WHERE role_id=?')->execute([$roleId]);$insert=$pdo->prepare('INSERT INTO role_permissions(role_id,permission_id) VALUES(?,?)');foreach($ids as $id)$insert->execute([$roleId,$id]);$pdo->commit();}catch(\Throwable $e){$pdo->rollBack();throw $e;}
        Auth::registerAudit('role.permissions.update','roles',(int)$roleId);Flash::set('success','Permissões atualizadas com sucesso.');self::redirect('/admin/permissions?role_id='.$roleId);
    }
    private static function redirect(string $url):never{header('Location: '.$url);exit;}
}
