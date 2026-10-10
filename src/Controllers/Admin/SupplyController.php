<?php
declare(strict_types=1);
namespace BloodHub\Controllers\Admin;

use BloodHub\Core\{AdminGuard, Auth, Csrf, Database, Flash, SupplyAvailability};
use DateTimeImmutable;
use PDO;
use PDOException;

final class SupplyController
{
    // O vínculo test_supplies e a validação de lote ativo, validade e saldo pertencem
    // ao futuro fluxo de execução de testes; este módulo mantém apenas o cadastro operacional.
    private const PERMISSION='admin.supplies.manage';
    private const LOT_STATUSES=['active','inactive','exhausted','blocked'];

    public static function index():void
    {
        AdminGuard::enforce(self::PERMISSION);
        $sql="SELECT s.*,COUNT(CASE WHEN sl.status='active' THEN 1 END) active_lots,
            MIN(CASE WHEN sl.status='active' THEN sl.expiration_date END) next_expiration,
            MIN(CASE WHEN sl.status='active' THEN DATEDIFF(sl.expiration_date,CURDATE()) END) worst_days
            FROM supplies s LEFT JOIN supply_lots sl ON sl.supply_id=s.id GROUP BY s.id ORDER BY s.name";
        $supplies=Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach($supplies as &$s)$s['validity']=SupplyAvailability::statusFromDays($s['worst_days']===null?null:(int)$s['worst_days']);
        unset($s);
        self::view('index',compact('supplies')+['pageTitle'=>'Insumos']);
    }
    public static function create():void{AdminGuard::enforce(self::PERMISSION);self::supplyForm(null,[]);}
    public static function store():void{AdminGuard::enforce(self::PERMISSION);self::saveSupply(null);}
    public static function edit():void{AdminGuard::enforce(self::PERMISSION);$s=self::supply(false);if(!$s){self::notFound('Insumo');return;}self::supplyForm($s,[]);}
    public static function update():void{AdminGuard::enforce(self::PERMISSION);$s=self::supply(true);if(!$s){self::notFound('Insumo');return;}self::saveSupply($s);}

    public static function lots():void
    {
        AdminGuard::enforce(self::PERMISSION);$s=self::supply(false,'supply_id');if(!$s){self::notFound('Insumo');return;}
        $q=Database::connection()->prepare('SELECT * FROM supply_lots WHERE supply_id=:id ORDER BY expiration_date,lot_number');$q->execute(['id'=>$s['id']]);$lots=$q->fetchAll(PDO::FETCH_ASSOC);
        $today=new DateTimeImmutable('today');foreach($lots as &$lot){$expiration=new DateTimeImmutable($lot['expiration_date']);$days=(int)$today->diff($expiration)->format('%r%a');$lot['validity']=SupplyAvailability::statusFromDays($days);}$supply=$s;unset($lot);
        self::view('lots',compact('supply','lots')+['pageTitle'=>'Lotes de Insumo']);
    }
    public static function createLot():void{AdminGuard::enforce(self::PERMISSION);$s=self::supply(false,'supply_id');if(!$s){self::notFound('Insumo');return;}self::lotForm(null,$s,[]);}
    public static function storeLot():void{AdminGuard::enforce(self::PERMISSION);self::saveLot(null);}
    public static function editLot():void{AdminGuard::enforce(self::PERMISSION);$lot=self::lot(false);if(!$lot){self::notFound('Lote');return;}$s=self::supplyById((int)$lot['supply_id']);self::lotForm($lot,$s,[]);}
    public static function updateLot():void{AdminGuard::enforce(self::PERMISSION);$lot=self::lot(true);if(!$lot){self::notFound('Lote');return;}self::saveLot($lot);}

    private static function saveSupply(?array $current):void
    {
        if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada. Tente novamente.');self::redirect($current?'/admin/supplies/edit?id='.(int)$current['id']:'/admin/supplies/create');}
        $data=['name'=>trim((string)($_POST['name']??'')),'manufacturer'=>trim((string)($_POST['manufacturer']??''))?:null,'internal_code'=>trim((string)($_POST['internal_code']??''))?:null,'unit_of_measure'=>trim((string)($_POST['unit_of_measure']??''))?:null,'status'=>(string)($_POST['status']??'')];
        $errors=[];if($data['name']==='')$errors[]='O nome é obrigatório.';if(mb_strlen($data['name'])>180)$errors[]='O nome deve ter no máximo 180 caracteres.';if($data['manufacturer']!==null&&mb_strlen($data['manufacturer'])>180)$errors[]='O fabricante deve ter no máximo 180 caracteres.';if($data['internal_code']!==null&&mb_strlen($data['internal_code'])>80)$errors[]='O código interno deve ter no máximo 80 caracteres.';if($data['unit_of_measure']!==null&&mb_strlen($data['unit_of_measure'])>40)$errors[]='A unidade de medida deve ter no máximo 40 caracteres.';if(!in_array($data['status'],['active','inactive'],true))$errors[]='Selecione um status válido.';
        if($errors){self::supplyForm(array_merge($current??[],$data),$errors);return;}$pdo=Database::connection();
        if($current){$pdo->prepare('UPDATE supplies SET name=:name,manufacturer=:manufacturer,internal_code=:internal_code,unit_of_measure=:unit_of_measure,status=:status WHERE id=:id')->execute($data+['id'=>$current['id']]);Auth::registerAudit('supply.update','supplies',(int)$current['id'],self::supplySnapshot($current),$data);Flash::set('success','Insumo atualizado com sucesso.');}
        else{$pdo->prepare('INSERT INTO supplies(name,manufacturer,internal_code,unit_of_measure,status) VALUES(:name,:manufacturer,:internal_code,:unit_of_measure,:status)')->execute($data);$id=(int)$pdo->lastInsertId();Auth::registerAudit('supply.create','supplies',$id,null,$data);Flash::set('success','Insumo cadastrado com sucesso.');}
        self::redirect('/admin/supplies');
    }

    private static function saveLot(?array $current):void
    {
        $supplyId=filter_var($_POST['supply_id']??null,FILTER_VALIDATE_INT);$supply=$supplyId?self::supplyById((int)$supplyId):null;
        if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada. Tente novamente.');self::redirect($current?'/admin/supplies/lots/edit?id='.(int)$current['id']:'/admin/supplies/lots/create?supply_id='.(int)$supplyId);}
        if(!$supply){self::notFound('Insumo');return;}
        $initial=trim((string)($_POST['quantity_initial']??''));$available=trim((string)($_POST['quantity_available']??''));if(!$current&&$initial!==''&&$available==='')$available=$initial;
        $data=['supply_id'=>(int)$supplyId,'lot_number'=>trim((string)($_POST['lot_number']??'')),'expiration_date'=>trim((string)($_POST['expiration_date']??'')),'received_at'=>trim((string)($_POST['received_at']??''))?:null,'quantity_initial'=>$initial===''?null:$initial,'quantity_available'=>$available===''?null:$available,'status'=>(string)($_POST['status']??''),'is_in_use'=>!empty($_POST['is_in_use'])?1:0];
        $errors=[];if($data['lot_number']==='')$errors[]='O número do lote é obrigatório.';if(mb_strlen($data['lot_number'])>120)$errors[]='O número do lote deve ter no máximo 120 caracteres.';if(!self::validDate($data['expiration_date']))$errors[]='Informe uma data de validade válida.';if($data['received_at']!==null&&!self::validDate($data['received_at']))$errors[]='Informe uma data de recebimento válida.';foreach(['quantity_initial'=>'quantidade inicial','quantity_available'=>'quantidade disponível'] as $key=>$label)if($data[$key]!==null&&(!is_numeric($data[$key])||(float)$data[$key]<0))$errors[]='A '.$label.' deve ser um número não negativo.';if(!in_array($data['status'],self::LOT_STATUSES,true))$errors[]='Selecione um status válido.';
        $pdo=Database::connection();$q=$pdo->prepare('SELECT id FROM supply_lots WHERE supply_id=:supply_id AND lot_number=:lot_number AND id<>:id LIMIT 1');$q->execute(['supply_id'=>$data['supply_id'],'lot_number'=>$data['lot_number'],'id'=>$current['id']??0]);if($data['lot_number']!==''&&$q->fetchColumn())$errors[]='Já existe um lote com este número para o insumo.';
        if($errors){self::lotForm(array_merge($current??[],$data),$supply,$errors);return;}
        try{$pdo->beginTransaction();$pdo->prepare('SELECT id FROM supplies WHERE id=:id FOR UPDATE')->execute(['id'=>$data['supply_id']]);if($data['is_in_use'])$pdo->prepare('UPDATE supply_lots SET is_in_use=0 WHERE supply_id=:supply_id AND id<>:id')->execute(['supply_id'=>$data['supply_id'],'id'=>$current['id']??0]);if($current){$pdo->prepare('UPDATE supply_lots SET supply_id=:supply_id,lot_number=:lot_number,expiration_date=:expiration_date,received_at=:received_at,quantity_initial=:quantity_initial,quantity_available=:quantity_available,status=:status,is_in_use=:is_in_use WHERE id=:id')->execute($data+['id'=>$current['id']]);Auth::registerAudit('supply_lot.update','supply_lots',(int)$current['id'],self::lotSnapshot($current),$data);Flash::set('success','Lote atualizado com sucesso.');}
        else{$pdo->prepare('INSERT INTO supply_lots(supply_id,lot_number,expiration_date,received_at,quantity_initial,quantity_available,status,is_in_use) VALUES(:supply_id,:lot_number,:expiration_date,:received_at,:quantity_initial,:quantity_available,:status,:is_in_use)')->execute($data);$id=(int)$pdo->lastInsertId();Auth::registerAudit('supply_lot.create','supply_lots',$id,null,$data);Flash::set('success','Lote cadastrado com sucesso.');}$pdo->commit();}
        catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if($e->getCode()==='23000'){self::lotForm(array_merge($current??[],$data),$supply,['Já existe um lote com este número para o insumo.']);return;}throw $e;}
        catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        self::redirect('/admin/supplies/lots?supply_id='.(int)$supplyId);
    }

    private static function validDate(string $date):bool{$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);return $d!==false&&$d->format('Y-m-d')===$date;}
    private static function supplyForm(?array $supply,array $errors):void{self::view('form',compact('supply','errors')+['pageTitle'=>!empty($supply['id'])?'Editar Insumo':'Novo Insumo']);}
    private static function lotForm(?array $lot,array $supply,array $errors):void{self::view('lot_form',compact('lot','supply','errors')+['pageTitle'=>!empty($lot['id'])?'Editar Lote':'Novo Lote']);}
    private static function supply(bool $post,string $key='id'):?array{$id=filter_var($post?($_POST[$key]??null):($_GET[$key]??null),FILTER_VALIDATE_INT);return $id?self::supplyById((int)$id):null;}
    private static function supplyById(int $id):?array{$q=Database::connection()->prepare('SELECT * FROM supplies WHERE id=:id');$q->execute(['id'=>$id]);return $q->fetch(PDO::FETCH_ASSOC)?:null;}
    private static function lot(bool $post):?array{$id=filter_var($post?($_POST['id']??null):($_GET['id']??null),FILTER_VALIDATE_INT);if(!$id)return null;$q=Database::connection()->prepare('SELECT * FROM supply_lots WHERE id=:id');$q->execute(['id'=>$id]);return $q->fetch(PDO::FETCH_ASSOC)?:null;}
    private static function supplySnapshot(array $s):array{return array_intersect_key($s,array_flip(['name','manufacturer','internal_code','unit_of_measure','status']));}
    private static function lotSnapshot(array $l):array{return array_intersect_key($l,array_flip(['supply_id','lot_number','expiration_date','received_at','quantity_initial','quantity_available','status','is_in_use']));}
    private static function view(string $name,array $vars):void{extract($vars);$flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();require dirname(__DIR__,2).'/Views/admin/supplies/'.$name.'.php';}
    private static function redirect(string $url):never{header('Location: '.$url);exit;}
    private static function notFound(string $type):void{http_response_code(404);echo $type.' não encontrado.';}
}
