<?php
declare(strict_types=1);
namespace BloodHub\Controllers\Admin;
use BloodHub\Core\{AdminGuard,Auth,Csrf,Database,Flash};
use PDO;

final class PreservativeController
{
    private const P='admin.preservatives.manage';
    public static function index():void{AdminGuard::enforce(self::P);self::view(Database::connection()->query('SELECT * FROM preservatives ORDER BY active DESC,name')->fetchAll(PDO::FETCH_ASSOC));}
    public static function save():void{AdminGuard::enforce(self::P);if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada.');self::redirect();}$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT)?:null;$code=strtoupper(trim((string)($_POST['code']??'')));$name=trim((string)($_POST['name']??''));$active=(string)($_POST['active']??'1');if($code===''||mb_strlen($code)>60||$name===''||mb_strlen($name)>180||!in_array($active,['0','1'],true)){Flash::set('error','Informe código, nome e status válidos.');self::redirect();}$pdo=Database::connection();$before=null;try{if($id){$q=$pdo->prepare('SELECT * FROM preservatives WHERE id=:id');$q->execute(['id'=>$id]);$before=$q->fetch(PDO::FETCH_ASSOC);if(!$before){Flash::set('error','Preservante não encontrado.');self::redirect();}$pdo->prepare('UPDATE preservatives SET code=:code,name=:name,active=:active WHERE id=:id')->execute(['code'=>$code,'name'=>$name,'active'=>$active,'id'=>$id]);}else{$pdo->prepare('INSERT INTO preservatives(code,name,active) VALUES(:code,:name,:active)')->execute(['code'=>$code,'name'=>$name,'active'=>$active]);$id=(int)$pdo->lastInsertId();}Auth::registerAudit($before?'preservative.update':'preservative.create','preservatives',(int)$id,$before?:null,['code'=>$code,'name'=>$name,'active'=>(int)$active]);Flash::set('success','Preservante salvo com sucesso.');}catch(\PDOException $e){if(($e->errorInfo[0]??'')==='23000')Flash::set('error','Já existe um preservante com este código ou nome.');else throw $e;}self::redirect();}
    private static function view(array $preservatives):void{$pageTitle='Preservantes';$pageSubtitle='Configure as soluções preservantes disponíveis para as amostras.';$flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();require dirname(__DIR__,2).'/Views/admin/preservatives/index.php';}
    private static function redirect():never{header('Location: /admin/preservatives');exit;}
}
