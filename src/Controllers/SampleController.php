<?php
declare(strict_types=1);

namespace BloodHub\Controllers;

use BloodHub\Core\SamplePurpose;

use BloodHub\Core\AdminGuard;
use BloodHub\Core\Auth;
use BloodHub\Core\Csrf;
use BloodHub\Core\Database;
use BloodHub\Core\Flash;
use BloodHub\Core\SampleAccess;
use BloodHub\Core\StatusLabel;
use PDO;
use PDOException;

final class SampleController
{
    private const PURPOSES=SamplePurpose::OPTIONS;

    public static function index():void
    {
        if (\BloodHub\Core\Permission::can('shipments.view')) { ShipmentController::index(); return; }
        AdminGuard::enforce('samples.view'); [$scope,$params]=SampleAccess::scopeSql('s');
        $where=[$scope];
        $filters=['q'=>trim((string)($_GET['q']??'')),'status'=>(string)($_GET['status']??''),'purpose'=>(string)($_GET['purpose']??''),'unit_id'=>(string)($_GET['unit_id']??''),'component_id'=>(string)($_GET['component_id']??'')];
        if($filters['q']!==''){$where[]='s.sample_code LIKE :q';$params['q']='%'.$filters['q'].'%';}
        if(isset(StatusLabel::all()[$filters['status']])){$where[]='s.status=:status';$params['status']=$filters['status'];}
        if(isset(self::PURPOSES[$filters['purpose']])){$where[]='s.purpose=:purpose';$params['purpose']=$filters['purpose'];}
        foreach(['unit_id'=>'origin_unit_id','component_id'=>'blood_component_id'] as $key=>$column){$id=filter_var($filters[$key],FILTER_VALIDATE_INT);if($id){$where[]="s.{$column}=:{$key}";$params[$key]=$id;}}
        $sql='SELECT s.*,u.name unit_name,bc.name component_name FROM samples s LEFT JOIN units u ON u.id=s.origin_unit_id LEFT JOIN blood_components bc ON bc.id=s.blood_component_id WHERE '.implode(' AND ',$where).' ORDER BY s.registered_at DESC';
        $stmt=Database::connection()->prepare($sql);$stmt->execute($params);
        self::view('index',['samples'=>$stmt->fetchAll(),'filters'=>$filters,'units'=>SampleAccess::units(),'components'=>self::components(),'purposes'=>self::PURPOSES,'statuses'=>StatusLabel::all(),'pageTitle'=>'Amostras']);
    }

    public static function create():void { AdminGuard::enforce('samples.create');self::form(null,[]); }
    public static function store():void { AdminGuard::enforce('samples.create');self::save(null); }
    public static function edit():void { AdminGuard::enforce('samples.edit');$sample=self::requested();if(!$sample){self::notFound();return;}if($sample['status']!=='registered'){Flash::set('error','A amostra não pode mais ser editada após o envio.');self::redirect('/samples/view?id='.(int)$sample['id']);}self::form($sample,[]); }
    public static function update():void { AdminGuard::enforce('samples.edit');$sample=self::requested(true);if(!$sample){self::notFound();return;}if($sample['status']!=='registered'){http_response_code(409);Flash::set('error','A amostra não pode mais ser editada após o envio.');self::redirect('/samples/view?id='.(int)$sample['id']);}self::save($sample); }
    public static function show():void { AdminGuard::enforce('samples.view');$sample=self::requested();if(!$sample){self::notFound();return;}self::view('show',['sample'=>$sample,'purposes'=>self::PURPOSES,'statuses'=>StatusLabel::all(),'pageTitle'=>'Detalhes da Amostra']); }

    public static function send():void
    {
        AdminGuard::enforce('samples.send');self::csrf('/samples');$sample=self::requested(true);if(!$sample){self::notFound();return;}
        $pdo=Database::connection();$pdo->beginTransaction();
        try{$lock=$pdo->prepare('SELECT status FROM samples WHERE id=:id FOR UPDATE');$lock->execute(['id'=>$sample['id']]);if($lock->fetchColumn()!=='registered')throw new \RuntimeException('Somente amostras cadastradas podem ser enviadas.');
            $pdo->prepare("UPDATE samples SET status='awaiting_receipt',sent_at=NOW(),sent_by=:user WHERE id=:id")->execute(['user'=>Auth::user()['id'],'id'=>$sample['id']]);
            Auth::registerAudit('sample.send','samples',(int)$sample['id'],['status'=>'registered'],['status'=>'awaiting_receipt','sent_by'=>(int)Auth::user()['id']]);$pdo->commit();Flash::set('success','Amostra enviada ao LCQH com sucesso.');
        }catch(\Throwable $e){$pdo->rollBack();Flash::set('error',$e->getMessage());}
        self::redirect('/samples/view?id='.(int)$sample['id']);
    }

    private static function save(?array $current):void
    {
        self::csrf($current?'/samples/edit?id='.(int)$current['id']:'/samples/create');
        $unitId=filter_var($_POST['origin_unit_id']??null,FILTER_VALIDATE_INT);$componentId=filter_var($_POST['blood_component_id']??null,FILTER_VALIDATE_INT);
        $data=['sample_code'=>trim((string)($_POST['sample_code']??'')),'purpose'=>(string)($_POST['purpose']??''),'origin_unit_id'=>$unitId?:null,'blood_component_id'=>$componentId?:null,'collection_date'=>trim((string)($_POST['collection_date']??''))?:null,'notes'=>trim((string)($_POST['notes']??''))?:null];$errors=[];
        if($data['sample_code']==='')$errors[]='A identificação da amostra é obrigatória.'; elseif(mb_strlen($data['sample_code'])>80)$errors[]='A identificação deve ter no máximo 80 caracteres.';
        if(!isset(self::PURPOSES[$data['purpose']]))$errors[]='Selecione uma finalidade válida.';
        if(!$unitId||!SampleAccess::canUseUnit((int)$unitId))$errors[]='Selecione uma unidade autorizada.';
        $pdo=Database::connection();$check=$pdo->prepare("SELECT 1 FROM blood_components WHERE id=:id AND status='active'");$check->execute(['id'=>$componentId?:0]);if(!$check->fetchColumn())$errors[]='Selecione um hemocomponente ativo.';
        if($data['collection_date']&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$data['collection_date']))$errors[]='Informe uma data de coleta válida.';
        if($errors){self::form(array_merge($current??[],$data),$errors);return;}
        $unit=$pdo->prepare('SELECT client_id FROM units WHERE id=:id');$unit->execute(['id'=>$unitId]);$clientId=$unit->fetchColumn()?:null;
        try{if($current){$sql='UPDATE samples SET sample_code=:sample_code,purpose=:purpose,origin_unit_id=:origin_unit_id,client_id=:client_id,blood_component_id=:blood_component_id,collection_date=:collection_date,notes=:notes WHERE id=:id AND status="registered"';$payload=$data+['client_id'=>$clientId,'id'=>$current['id']];$stmt=$pdo->prepare($sql);$stmt->execute($payload);$state=$pdo->prepare('SELECT status FROM samples WHERE id=:id');$state->execute(['id'=>$current['id']]);if($state->fetchColumn()!=='registered')throw new \RuntimeException('A amostra não está mais disponível para edição.');Auth::registerAudit('sample.update','samples',(int)$current['id'],self::auditData($current),self::auditData($payload));$id=(int)$current['id'];Flash::set('success','Amostra atualizada com sucesso.');}
            else{$payload=$data+['client_id'=>$clientId,'registered_by'=>Auth::user()['id']];$pdo->prepare('INSERT INTO samples(sample_code,purpose,origin_unit_id,client_id,blood_component_id,collection_date,notes,status,registered_by,registered_at) VALUES(:sample_code,:purpose,:origin_unit_id,:client_id,:blood_component_id,:collection_date,:notes,"registered",:registered_by,NOW())')->execute($payload);$id=(int)$pdo->lastInsertId();Auth::registerAudit('sample.create','samples',$id,null,self::auditData($payload)+['status'=>'registered']);Flash::set('success','Amostra cadastrada com sucesso.');}
        }catch(PDOException $e){if($e->getCode()==='23000'){self::form(array_merge($current??[],$data),['Já existe uma amostra com essa identificação.']);return;}throw $e;}
        self::redirect('/samples/view?id='.$id);
    }

    private static function auditData(array $d):array { return array_intersect_key($d,array_flip(['sample_code','purpose','origin_unit_id','client_id','blood_component_id','collection_date','notes','status','registered_by'])); }
    private static function form(?array $sample,array $errors):void { self::view('form',['sample'=>$sample,'errors'=>$errors,'units'=>SampleAccess::units(),'components'=>self::components(),'purposes'=>self::PURPOSES,'pageTitle'=>$sample?'Editar Amostra':'Nova Amostra']); }
    private static function components():array { return Database::connection()->query("SELECT id,code,name FROM blood_components WHERE status='active' ORDER BY name")->fetchAll(); }
    private static function requested(bool $post=false):?array {$id=filter_var($post?($_POST['id']??null):($_GET['id']??null),FILTER_VALIDATE_INT);return $id?SampleAccess::sample((int)$id):null;}
    private static function csrf(string $back):void {if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada. Tente novamente.');self::redirect($back);}}
    private static function view(string $name,array $vars):void {extract($vars);$flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();require dirname(__DIR__).'/Views/samples/'.$name.'.php';}
    private static function redirect(string $url):never {header('Location: '.$url);exit;}
    private static function notFound():void {http_response_code(404);echo 'Amostra não encontrada ou fora do seu escopo.';}
}
