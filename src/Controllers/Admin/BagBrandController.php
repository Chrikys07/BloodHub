<?php
namespace BloodHub\Controllers\Admin;

use BloodHub\Core\Auth;
use BloodHub\Core\AdminGuard;
use BloodHub\Core\Csrf;
use BloodHub\Core\Database;
use BloodHub\Core\Flash;
use PDO;

final class BagBrandController
{
    private const P='admin.bag_brands.manage';
    private const REFERENCE_LOCK='Esta referência já foi utilizada em amostras. Para alterar o número da referência, cadastre uma nova referência de bolsa.';
    private const TARE_LOCK='Esta tara já foi utilizada analiticamente e não pode ser alterada retroativamente. Cadastre um novo perfil.';

    public static function index():void
    {
        AdminGuard::enforce(self::P);
        $brands=Database::connection()->query('SELECT b.*,p.code preservative_code,p.name preservative_name,COUNT(t.id) tare_count,COALESCE(SUM(t.active=1),0) active_tare_count FROM bag_brands b LEFT JOIN preservatives p ON p.id=b.preservative_id LEFT JOIN bag_brand_tares t ON t.bag_brand_id=b.id GROUP BY b.id ORDER BY b.name,b.reference_number')->fetchAll();
        self::view('index',compact('brands')+['pageTitle'=>'Marcas de Bolsa']);
    }
    public static function create():void{AdminGuard::enforce(self::P);self::form(null,[]);}
    public static function store():void{AdminGuard::enforce(self::P);self::saveBrand(null);}
    public static function edit():void{AdminGuard::enforce(self::P);$b=self::requested(false);if(!$b){http_response_code(404);return;}self::form($b,[]);}
    public static function update():void{AdminGuard::enforce(self::P);$b=self::requested(true);if(!$b){http_response_code(404);return;}self::saveBrand($b);}

    public static function toggleStatus():void
    {
        AdminGuard::enforce(self::P);self::csrfOrBack();$brand=self::requested(true);
        if(!$brand){http_response_code(404);return;}
        $activate=(int)($_POST['activate']??-1);
        if(!in_array($activate,[0,1],true)){Flash::set('error','Status inválido.');self::redirect('/admin/bag-brands');}
        if($activate&&empty($brand['preservative_id'])){Flash::set('error','Configure o preservante antes de ativar esta referência.');self::redirect('/admin/bag-brands');}
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $family=self::lockBrandFamily($pdo,(string)$brand['name']);$selected=null;
            foreach($family as $row)if((int)$row['id']===(int)$brand['id'])$selected=$row;
            if(!$selected)throw new \RuntimeException('Referência de bolsa não encontrada.');
            if((int)$selected['active']===$activate){$pdo->commit();Flash::set('success',$activate?'A referência já está ativa.':'A referência já está inativa.');self::redirect('/admin/bag-brands');}
            $before=self::brandAudit($selected);$previous=[];
            if($activate){
                foreach($family as $row)if((int)$row['active']&&(int)$row['id']!==(int)$selected['id'])$previous[]=self::brandAudit($row);
                $pdo->prepare('UPDATE bag_brands SET active=0 WHERE LOWER(TRIM(name))=LOWER(TRIM(:name)) AND id<>:id AND active=1')->execute(['name'=>$selected['name'],'id'=>$selected['id']]);
            }
            $pdo->prepare('UPDATE bag_brands SET active=:active WHERE id=:id')->execute(['active'=>$activate,'id'=>$selected['id']]);
            $after=array_merge($before,['active'=>$activate]);
            Auth::registerAudit($activate?($previous?'bag_brand.active_replaced':'bag_brand.activate'):'bag_brand.deactivate','bag_brands',(int)$selected['id'],$previous?:$before,$previous?['previous_references'=>$previous,'new_reference'=>$after]:$after);
            $pdo->commit();Flash::set('success',$activate?($previous?'Referência ativada; a anterior foi inativada.':'Referência ativada.'):'Referência inativada.');
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();Flash::set('error',$e->getMessage());}
        self::redirect('/admin/bag-brands');
    }

    public static function storeTare():void{AdminGuard::enforce(self::P);self::saveTare(null);}
    public static function updateTare():void{AdminGuard::enforce(self::P);$tare=self::requestedTare();if(!$tare){http_response_code(404);return;}self::saveTare($tare);}
    public static function deactivateTare():void
    {
        AdminGuard::enforce(self::P); self::csrfOrBack(); $tare=self::requestedTare();
        if(!$tare){http_response_code(404);return;} if($tare['locked_at']){Flash::set('error',self::TARE_LOCK);self::back((int)$tare['bag_brand_id']);}
        $pdo=Database::connection();$pdo->beginTransaction();
        try{$lock=$pdo->prepare('SELECT id FROM bag_brands WHERE id=:id FOR UPDATE');$lock->execute(['id'=>$tare['bag_brand_id']]);$before=self::tareAudit($tare);$pdo->prepare('UPDATE bag_brand_tares SET active=0 WHERE id=:id')->execute(['id'=>$tare['id']]);Auth::registerAudit('bag_brand_tare.deactivate','bag_brand_tares',(int)$tare['id'],$before,array_merge($before,['active'=>0]));$pdo->commit();Flash::set('success','Perfil de tara inativado.');}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();Flash::set('error',$e->getMessage());}
        self::back((int)$tare['bag_brand_id']);
    }

    private static function saveBrand(?array $current):void
    {
        if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada.');self::redirect('/admin/bag-brands');}
        $name=trim((string)($_POST['name']??''));$reference=trim((string)($_POST['reference_number']??''));$preservativeId=(int)($_POST['preservative_id']??0);$active=$current?(int)$current['active']:0;$errors=[];
        if($name===''||mb_strlen($name)>180)$errors[]='Informe uma marca com até 180 caracteres.';
        if($reference===''||mb_strlen($reference)>120)$errors[]='Informe um número de referência com até 120 caracteres.';
        if(!$preservativeId)$errors[]='Selecione o preservante da referência.';
        else{$p=Database::connection()->prepare("SELECT 1 FROM preservatives WHERE id=:id AND (active=1 OR id=:current)");$p->execute(['id'=>$preservativeId,'current'=>(int)($current['preservative_id']??0)]);if(!$p->fetchColumn())$errors[]='Selecione um preservante ativo.';}
        if($current&&!empty($current['is_used'])&&$reference!==(string)$current['reference_number'])$errors[]=self::REFERENCE_LOCK;
        $data=array_merge($current??[],['name'=>$name,'reference_number'=>$reference,'preservative_id'=>$preservativeId,'active'=>$active]);if($errors){self::form($data,$errors);return;}
        $pdo=Database::connection();
        try{
            $pdo->beginTransaction();self::lockBrandFamily($pdo,$current?(string)$current['name']:$name);
            if($current&&mb_strtolower(trim((string)$current['name']))!==mb_strtolower($name))self::lockBrandFamily($pdo,$name);
            if($current){$pdo->prepare('UPDATE bag_brands SET name=:name,reference_number=:reference,preservative_id=:preservative WHERE id=:id')->execute(['name'=>$name,'reference'=>$reference,'preservative'=>$preservativeId,'id'=>$current['id']]);$id=(int)$current['id'];}
            else{$pdo->prepare('INSERT INTO bag_brands(name,reference_number,preservative_id,tare_weight,active) VALUES(:name,:reference,:preservative,NULL,0)')->execute(['name'=>$name,'reference'=>$reference,'preservative'=>$preservativeId]);$id=(int)$pdo->lastInsertId();}
            Auth::registerAudit($current?'bag_brand.update':'bag_brand.create','bag_brands',$id,$current,['name'=>$name,'reference_number'=>$reference,'preservative_id'=>$preservativeId,'active'=>$active]);$pdo->commit();
            Flash::set('success',$current?'Referência de bolsa salva.':'Referência criada como inativa. Ative-a na listagem quando estiver pronta para uso.');self::back($id);
        }catch(\PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if(($e->errorInfo[0]??'')==='23000'){self::form($data,['Já existe esta combinação de marca e número de referência.']);return;}throw $e;}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private static function saveTare(?array $current):void
    {
        self::csrfOrBack();$brandId=(int)($current['bag_brand_id']??($_POST['bag_brand_id']??0));$name=trim((string)($_POST['name']??''));$raw=str_replace(',','.',trim((string)($_POST['tare_weight']??'')));$active=(int)($_POST['active']??1);$componentIds=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['blood_component_ids']??[])))));$errors=[];
        if($name===''||mb_strlen($name)>180)$errors[]='Informe a identificação da tara com até 180 caracteres.';
        if(!preg_match('/^\d+(?:\.\d{1,3})?$/',$raw)||(float)$raw<=0||(float)$raw>=10000000)$errors[]='Informe uma tara maior que zero, com até 3 casas decimais.';
        if(!in_array($active,[0,1],true))$errors[]='Status inválido.';
        if(!$brandId)$errors[]='Referência de bolsa inválida.';
        if($current&&$current['locked_at'])$errors[]=self::TARE_LOCK;
        $pdo=Database::connection();
        if($componentIds){$marks=implode(',',array_fill(0,count($componentIds),'?'));$params=$componentIds;if($current){$q=$pdo->prepare("SELECT id FROM blood_components WHERE id IN ($marks) AND (status='active' OR id IN (SELECT blood_component_id FROM bag_brand_tare_components WHERE bag_brand_tare_id=?))");$params[]=(int)$current['id'];}else{$q=$pdo->prepare("SELECT id FROM blood_components WHERE id IN ($marks) AND status='active'");}$q->execute($params);$valid=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));if(count($valid)!==count($componentIds))$errors[]='Selecione somente hemocomponentes ativos ou vínculos históricos deste perfil.';}
        if($errors){Flash::set('error',implode(' ',$errors));self::back($brandId);}
        $pdo->beginTransaction();
        try{
            $lock=$pdo->prepare('SELECT id FROM bag_brands WHERE id=:id FOR UPDATE');$lock->execute(['id'=>$brandId]);if(!$lock->fetchColumn())throw new \RuntimeException('Referência de bolsa não encontrada.');
            if($active&&$componentIds){$marks=implode(',',array_fill(0,count($componentIds),'?'));$params=array_merge([$brandId,(int)($current['id']??0)],$componentIds);$conflict=$pdo->prepare("SELECT bc.code FROM bag_brand_tares t JOIN bag_brand_tare_components c ON c.bag_brand_tare_id=t.id JOIN blood_components bc ON bc.id=c.blood_component_id WHERE t.bag_brand_id=? AND t.active=1 AND t.id<>? AND c.blood_component_id IN ($marks) LIMIT 1");$conflict->execute($params);$code=$conflict->fetchColumn();if($code)throw new \DomainException("O hemocomponente {$code} já está vinculado a outra tara ativa desta referência.");}
            $before=$current?self::tareAudit($current):null;
            if($current){$pdo->prepare('UPDATE bag_brand_tares SET name=:name,tare_weight=:weight,active=:active WHERE id=:id')->execute(['name'=>$name,'weight'=>$raw,'active'=>$active,'id'=>$current['id']]);$tareId=(int)$current['id'];}else{$pdo->prepare('INSERT INTO bag_brand_tares(bag_brand_id,name,tare_weight,active) VALUES(:brand,:name,:weight,:active)')->execute(['brand'=>$brandId,'name'=>$name,'weight'=>$raw,'active'=>$active]);$tareId=(int)$pdo->lastInsertId();}
            $oldIds=$current?self::tareComponentIds($tareId):[];$pdo->prepare('DELETE FROM bag_brand_tare_components WHERE bag_brand_tare_id=:id')->execute(['id'=>$tareId]);$link=$pdo->prepare('INSERT INTO bag_brand_tare_components(bag_brand_tare_id,blood_component_id) VALUES(:tare,:component)');foreach($componentIds as $componentId)$link->execute(['tare'=>$tareId,'component'=>$componentId]);
            $after=['bag_brand_id'=>$brandId,'name'=>$name,'tare_weight'=>number_format((float)$raw,3,'.',''),'active'=>$active,'blood_component_ids'=>$componentIds];$action='bag_brand_tare.create';if($current)$action=(int)$current['active']!==$active?($active?'bag_brand_tare.activate':'bag_brand_tare.deactivate'):'bag_brand_tare.update';Auth::registerAudit($action,'bag_brand_tares',$tareId,$before,$after);
            $added=array_values(array_diff($componentIds,$oldIds));$removed=array_values(array_diff($oldIds,$componentIds));if($added||$removed)Auth::registerAudit('bag_brand_tare.components_changed','bag_brand_tares',$tareId,['blood_component_ids'=>$oldIds],['blood_component_ids'=>$componentIds,'added'=>$added,'removed'=>$removed]);
            $pdo->commit();Flash::set('success','Perfil de tara salvo com sucesso.');
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();Flash::set('error',$e->getMessage());}
        self::back($brandId);
    }

    private static function form(?array $brand,array $errors):void{$tares=[];$components=[];$configured=[];$preservatives=Database::connection()->query("SELECT id,code,name,active FROM preservatives WHERE active=1".($brand&&!empty($brand['preservative_id'])?" OR id=".(int)$brand['preservative_id']:"")." ORDER BY code,name")->fetchAll(PDO::FETCH_ASSOC);if($brand){$brandId=(int)$brand['id'];$tares=self::tares($brandId);foreach($tares as &$tare){$tare['component_ids']=self::tareComponentIds((int)$tare['id']);foreach($tare['component_ids'] as $id)if($tare['active'])$configured[$id]=true;}unset($tare);$s=Database::connection()->prepare("SELECT DISTINCT bc.id,bc.code,bc.name,bc.status FROM blood_components bc LEFT JOIN bag_brand_tare_components c ON c.blood_component_id=bc.id LEFT JOIN bag_brand_tares t ON t.id=c.bag_brand_tare_id AND t.bag_brand_id=:brand WHERE bc.status='active' OR t.id IS NOT NULL ORDER BY bc.code,bc.name");$s->execute(['brand'=>$brandId]);$components=$s->fetchAll();}self::view('form',compact('brand','errors','tares','components','configured','preservatives')+['pageTitle'=>$brand?'Editar Referência':'Nova Referência']);}
    private static function tares(int $brandId):array{$s=Database::connection()->prepare("SELECT t.*,GROUP_CONCAT(bc.code ORDER BY bc.code SEPARATOR ', ') component_codes FROM bag_brand_tares t LEFT JOIN bag_brand_tare_components c ON c.bag_brand_tare_id=t.id LEFT JOIN blood_components bc ON bc.id=c.blood_component_id WHERE t.bag_brand_id=:id GROUP BY t.id ORDER BY t.active DESC,t.name");$s->execute(['id'=>$brandId]);return$s->fetchAll(PDO::FETCH_ASSOC);}
    private static function tareComponentIds(int $tareId):array{$s=Database::connection()->prepare('SELECT blood_component_id FROM bag_brand_tare_components WHERE bag_brand_tare_id=:id ORDER BY blood_component_id');$s->execute(['id'=>$tareId]);return array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));}
    private static function requested(bool $post):?array{$id=(int)($post?($_POST['id']??0):($_GET['id']??0));$s=Database::connection()->prepare('SELECT b.*,EXISTS(SELECT 1 FROM samples s WHERE s.bag_brand_id=b.id) is_used FROM bag_brands b WHERE b.id=:id');$s->execute(['id'=>$id]);return$s->fetch(PDO::FETCH_ASSOC)?:null;}
    private static function requestedTare():?array{$id=(int)($_POST['tare_id']??0);$s=Database::connection()->prepare('SELECT * FROM bag_brand_tares WHERE id=:id');$s->execute(['id'=>$id]);return$s->fetch(PDO::FETCH_ASSOC)?:null;}
    private static function tareAudit(array $t):array{return ['bag_brand_id'=>(int)$t['bag_brand_id'],'name'=>$t['name'],'tare_weight'=>number_format((float)$t['tare_weight'],3,'.',''),'active'=>(int)$t['active'],'locked_at'=>$t['locked_at']??null,'blood_component_ids'=>isset($t['id'])?self::tareComponentIds((int)$t['id']):[]];}
    private static function brandAudit(array $b):array{return ['id'=>(int)$b['id'],'name'=>$b['name'],'reference_number'=>$b['reference_number'],'preservative_id'=>isset($b['preservative_id'])?(int)$b['preservative_id']:null,'active'=>(int)$b['active']];}
    private static function lockBrandFamily(PDO $pdo,string $name):array{$s=$pdo->prepare('SELECT id,name,reference_number,active,created_at FROM bag_brands WHERE LOWER(TRIM(name))=LOWER(TRIM(:name)) ORDER BY created_at DESC,id DESC FOR UPDATE');$s->execute(['name'=>$name]);return$s->fetchAll(PDO::FETCH_ASSOC);}
    private static function csrfOrBack():void{if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada.');self::redirect('/admin/bag-brands');}}
    private static function back(int $id):never{self::redirect('/admin/bag-brands/edit?id='.$id);}
    private static function view(string $name,array $values):void{extract($values);$flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();require dirname(__DIR__,2).'/Views/admin/bag_brands/'.$name.'.php';}
    private static function redirect(string $url):never{header('Location: '.$url);exit;}
}
