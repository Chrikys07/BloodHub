<?php
declare(strict_types=1);
namespace BloodHub\Controllers\Admin;

use BloodHub\Core\{AdminGuard, Auth, Csrf, Database, Flash, SupplyAvailability};
use PDO;
use PDOException;
use Throwable;

final class TestController
{
    private const PERMISSION = 'admin.tests.manage';
    private const RESULT_TYPES = ['numeric','text','select','boolean','positive_negative'];

    public static function index(): void
    {
        AdminGuard::enforce(self::PERMISSION);
        $tests=Database::connection()->query('SELECT t.*,GROUP_CONCAT(bc.code ORDER BY bc.code SEPARATOR ", ") blood_component_codes FROM tests t LEFT JOIN test_blood_components tbc ON tbc.test_id=t.id LEFT JOIN blood_components bc ON bc.id=tbc.blood_component_id GROUP BY t.id ORDER BY t.name')->fetchAll(PDO::FETCH_ASSOC);
        self::view('index',['tests'=>$tests,'pageTitle'=>'Testes']);
    }
    public static function create():void{AdminGuard::enforce(self::PERMISSION);self::form(null,[],[]);}
    public static function store():void{AdminGuard::enforce(self::PERMISSION);self::save(null);}
    public static function edit():void{AdminGuard::enforce(self::PERMISSION);$test=self::requested(false);if(!$test){self::notFound();return;}self::form($test,[],self::componentIds((int)$test['id']));}
    public static function update():void{AdminGuard::enforce(self::PERMISSION);$test=self::requested(true);if(!$test){self::notFound();return;}self::save($test);}
    public static function createSupply():void{AdminGuard::enforce(self::PERMISSION);self::saveSupplyLink(false);}
    public static function updateSupply():void{AdminGuard::enforce(self::PERMISSION);self::saveSupplyLink(true);}
    public static function deleteSupply():void
    {
        AdminGuard::enforce(self::PERMISSION);$testId=self::positiveInt($_POST['test_id']??null);$supplyId=self::positiveInt($_POST['supply_id']??null);
        if(!$testId||!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada. Tente novamente.');self::redirect('/admin/tests');}
        $link=self::supplyLink($testId,$supplyId);if(!$link){Flash::set('error','Vínculo entre teste e insumo não encontrado.');self::redirect('/admin/tests/edit?id='.$testId);}
        Database::connection()->prepare('DELETE FROM test_supplies WHERE test_id=:test_id AND supply_id=:supply_id')->execute(['test_id'=>$testId,'supply_id'=>$supplyId]);
        Auth::registerAudit('test_supply.delete','test_supplies',$supplyId,self::linkSnapshot($link),null);
        Flash::set('success','Insumo removido da configuração do teste.');self::redirect('/admin/tests/edit?id='.$testId);
    }

    private static function saveSupplyLink(bool $editing):void
    {
        $testId=self::positiveInt($_POST['test_id']??null);$supplyId=self::positiveInt($_POST['supply_id']??null);
        if(!$testId||!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada. Tente novamente.');self::redirect('/admin/tests');}
        $redirect='/admin/tests/edit?id='.$testId;$test=self::testById($testId);if(!$test){self::notFound();return;}
        $quantity=trim((string)($_POST['quantity_required']??''));$required=(string)($_POST['is_required']??'');$errors=[];
        if(!$supplyId)$errors[]='Selecione um insumo válido.';
        if($quantity!==''&&(!is_numeric($quantity)||(float)$quantity<=0))$errors[]='A quantidade necessária deve ser maior que zero.';
        if(!in_array($required,['0','1'],true))$errors[]='Informe se o insumo é obrigatório.';
        $pdo=Database::connection();$supply=null;if($supplyId){$sql=$editing?'SELECT id FROM supplies WHERE id=:id':"SELECT id FROM supplies WHERE id=:id AND status='active'";$s=$pdo->prepare($sql);$s->execute(['id'=>$supplyId]);$supply=$s->fetchColumn();if(!$supply)$errors[]=$editing?'O insumo vinculado não existe.':'O insumo selecionado não existe ou está inativo.';}
        $current=$supplyId?self::supplyLink($testId,$supplyId):null;
        if($editing&&!$current)$errors[]='Vínculo entre teste e insumo não encontrado.';
        if(!$editing&&$current)$errors[]='Este insumo já está vinculado ao teste.';
        if($errors){Flash::set('error',implode(' ',$errors));self::redirect($redirect);}
        $data=['test_id'=>$testId,'supply_id'=>$supplyId,'quantity_required'=>$quantity===''?null:$quantity,'is_required'=>(int)$required];
        try{
            if($editing){$pdo->prepare('UPDATE test_supplies SET quantity_required=:quantity_required,is_required=:is_required WHERE test_id=:test_id AND supply_id=:supply_id')->execute($data);Auth::registerAudit('test_supply.update','test_supplies',$supplyId,self::linkSnapshot($current),$data);Flash::set('success','Configuração do insumo atualizada com sucesso.');}
            else{$pdo->prepare('INSERT INTO test_supplies(test_id,supply_id,quantity_required,is_required) VALUES(:test_id,:supply_id,:quantity_required,:is_required)')->execute($data);Auth::registerAudit('test_supply.create','test_supplies',$supplyId,null,$data);Flash::set('success','Insumo vinculado ao teste com sucesso.');}
        }catch(PDOException $e){if($e->getCode()==='23000'){Flash::set('error','Este insumo já está vinculado ao teste.');self::redirect($redirect);}throw $e;}
        self::redirect($redirect);
    }

    private static function save(?array $current):void
    {
        if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada. Tente novamente.');self::redirect($current?'/admin/tests/edit?id='.(int)$current['id']:'/admin/tests/create');}
        $data=['code'=>trim((string)($_POST['code']??''))?:null,'name'=>trim((string)($_POST['name']??'')),'result_type'=>(string)($_POST['result_type']??''),'unit'=>trim((string)($_POST['unit']??''))?:null,'method_name'=>trim((string)($_POST['method_name']??''))?:null,'equipment_required'=>(int)($_POST['equipment_required']??0),'allows_ad_hoc'=>(string)($_POST['allows_ad_hoc']??''),'is_final_result'=>(string)($_POST['is_final_result']??''),'status'=>(string)($_POST['status']??'')];
        $componentIds=self::postedComponentIds();$errors=[];
        if($data['name']==='')$errors[]='O nome do teste é obrigatório.';
        if(mb_strlen($data['name'])>180)$errors[]='O nome deve ter no máximo 180 caracteres.';
        if($data['code']!==null&&mb_strlen($data['code'])>80)$errors[]='O código deve ter no máximo 80 caracteres.';
        if($data['unit']!==null&&mb_strlen($data['unit'])>80)$errors[]='A unidade deve ter no máximo 80 caracteres.';
        if(!in_array($data['result_type'],self::RESULT_TYPES,true))$errors[]='Selecione um tipo de resultado válido.';
        if(!in_array($data['allows_ad_hoc'],['0','1'],true))$errors[]='Informe se o teste permite uso avulso.';
        if(!in_array($data['is_final_result'],['0','1'],true))$errors[]='Informe se o teste é um resultado final gerencial.';
        if(!in_array($data['status'],['active','inactive'],true))$errors[]='Selecione um status válido.';
        $pdo=Database::connection();
        if($data['code']!==null){$s=$pdo->prepare('SELECT id FROM tests WHERE code=:code AND id<>:id LIMIT 1');$s->execute(['code'=>$data['code'],'id'=>$current['id']??0]);if($s->fetchColumn())$errors[]='Já existe um teste com este código.';}
        if($componentIds){
            $marks=implode(',',array_fill(0,count($componentIds),'?'));
            $allowedIds=$current?self::componentIds((int)$current['id']):[];
            $params=array_merge($componentIds,$allowedIds);
            $linkedMarks=$allowedIds?implode(',',array_fill(0,count($allowedIds),'?')):'NULL';
            $s=$pdo->prepare("SELECT id FROM blood_components WHERE id IN ($marks) AND (status='active' OR id IN ($linkedMarks))");
            $s->execute($params);
            $valid=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));
            sort($valid);
            if($valid!==$componentIds)$errors[]='Um ou mais hemocomponentes selecionados são inválidos ou inativos.';
        }
        if($errors){self::form(array_merge($current??[],$data),$errors,$componentIds);return;}
        $data['allows_ad_hoc']=(int)$data['allows_ad_hoc'];
        $data['is_final_result']=(int)$data['is_final_result'];
        $before=$current?self::snapshot($current,self::componentIds((int)$current['id'])):null;
        try{
            $pdo->beginTransaction();
            if($current){$pdo->prepare('UPDATE tests SET code=:code,name=:name,result_type=:result_type,unit=:unit,method_name=:method_name,equipment_required=:equipment_required,allows_ad_hoc=:allows_ad_hoc,is_final_result=:is_final_result,status=:status WHERE id=:id')->execute($data+['id'=>(int)$current['id']]);$testId=(int)$current['id'];}
            else{$pdo->prepare('INSERT INTO tests(code,name,result_type,unit,method_name,equipment_required,allows_ad_hoc,is_final_result,status) VALUES(:code,:name,:result_type,:unit,:method_name,:equipment_required,:allows_ad_hoc,:is_final_result,:status)')->execute($data);$testId=(int)$pdo->lastInsertId();}
            $pdo->prepare('DELETE FROM test_blood_components WHERE test_id=:test_id')->execute(['test_id'=>$testId]);
            $link=$pdo->prepare('INSERT INTO test_blood_components(test_id,blood_component_id) VALUES(:test_id,:blood_component_id)');foreach($componentIds as $id)$link->execute(['test_id'=>$testId,'blood_component_id'=>$id]);
            Auth::registerAudit($current?'test.update':'test.create','tests',$testId,$before,self::snapshot($data,$componentIds));
            $pdo->commit();
        }catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if($e->getCode()==='23000'){self::form(array_merge($current??[],$data),['Já existe um teste com este código.'],$componentIds);return;}throw $e;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        Flash::set('success',$current?'Teste atualizado com sucesso.':'Teste cadastrado com sucesso.');self::redirect('/admin/tests');
    }
    private static function form(?array $test,array $errors,array $selectedComponentIds):void
    {
        $pdo=Database::connection();
        if(!empty($test['id'])){
            $query=$pdo->prepare("SELECT bc.id,bc.code,bc.name,bc.status
                FROM blood_components bc
                WHERE bc.status='active'
                   OR EXISTS(SELECT 1 FROM test_blood_components tbc WHERE tbc.blood_component_id=bc.id AND tbc.test_id=:test_id)
                ORDER BY bc.status DESC,bc.code,bc.name");
            $query->execute(['test_id'=>(int)$test['id']]);
            $bloodComponents=$query->fetchAll(PDO::FETCH_ASSOC);
        }else{
            $bloodComponents=$pdo->query("SELECT id,code,name,status FROM blood_components WHERE status='active' ORDER BY code,name")->fetchAll(PDO::FETCH_ASSOC);
        }
        $testSupplies=[];$availableSupplies=[];
        if(!empty($test['id'])){
            $availability=SupplyAvailability::checkTest((int)$test['id']);$testSupplies=$availability['supplies'];
            $q=$pdo->prepare("SELECT id,name,unit_of_measure FROM supplies s WHERE status='active' AND NOT EXISTS(SELECT 1 FROM test_supplies ts WHERE ts.supply_id=s.id AND ts.test_id=:test_id) ORDER BY name");$q->execute(['test_id'=>(int)$test['id']]);$availableSupplies=$q->fetchAll(PDO::FETCH_ASSOC);
        }
        self::view('form',compact('test','errors','bloodComponents','selectedComponentIds','testSupplies','availableSupplies')+['pageTitle'=>!empty($test['id'])?'Editar Teste':'Novo Teste']);
    }
    private static function postedComponentIds():array{$values=is_array($_POST['blood_component_ids']??null)?$_POST['blood_component_ids']:[];$ids=array_values(array_unique(array_filter(array_map(static fn($id):int=>filter_var($id,FILTER_VALIDATE_INT)?:0,$values))));sort($ids);return $ids;}
    private static function componentIds(int $testId):array{$s=Database::connection()->prepare('SELECT blood_component_id FROM test_blood_components WHERE test_id=:test_id ORDER BY blood_component_id');$s->execute(['test_id'=>$testId]);return array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));}
    private static function snapshot(array $test,array $ids):array{return ['code'=>$test['code']??null,'name'=>$test['name'],'result_type'=>$test['result_type'],'unit'=>$test['unit']??null,'allows_ad_hoc'=>(int)$test['allows_ad_hoc'],'is_final_result'=>(int)($test['is_final_result']??1),'status'=>$test['status'],'blood_component_ids'=>$ids];}
    private static function requested(bool $post):?array{$id=filter_var($post?($_POST['id']??null):($_GET['id']??null),FILTER_VALIDATE_INT);if(!$id)return null;$s=Database::connection()->prepare('SELECT * FROM tests WHERE id=:id');$s->execute(['id'=>$id]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
    private static function testById(int $id):?array{$s=Database::connection()->prepare('SELECT * FROM tests WHERE id=:id');$s->execute(['id'=>$id]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
    private static function positiveInt(mixed $value):int{$id=filter_var($value,FILTER_VALIDATE_INT);return $id&&$id>0?(int)$id:0;}
    private static function supplyLink(int $testId,int $supplyId):?array{$s=Database::connection()->prepare('SELECT test_id,supply_id,quantity_required,is_required FROM test_supplies WHERE test_id=:test_id AND supply_id=:supply_id');$s->execute(['test_id'=>$testId,'supply_id'=>$supplyId]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
    private static function linkSnapshot(array $link):array{return ['test_id'=>(int)$link['test_id'],'supply_id'=>(int)$link['supply_id'],'quantity_required'=>$link['quantity_required'],'is_required'=>(int)$link['is_required']];}
    private static function view(string $name,array $vars):void{extract($vars);$flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();require dirname(__DIR__,2).'/Views/admin/tests/'.$name.'.php';}
    private static function redirect(string $url):never{header('Location: '.$url);exit;}
    private static function notFound():void{http_response_code(404);echo 'Teste não encontrado.';}
}
