<?php
declare(strict_types=1);

namespace BloodHub\Controllers\Admin;

use BloodHub\Core\AdminGuard;
use BloodHub\Core\Auth;
use BloodHub\Core\Csrf;
use BloodHub\Core\Database;
use BloodHub\Core\Flash;
use PDO;
use PDOException;
use BloodHub\Services\{CpafYieldRuleVersionService,SpecificationVersionService};

final class BloodComponentController
{
    private const PERMISSION = 'admin.blood_components.manage';

    public static function index(): void
    {
        AdminGuard::enforce(self::PERMISSION);
        $bloodComponents = Database::connection()->query('SELECT * FROM blood_components ORDER BY name')->fetchAll();
        self::view('index', ['bloodComponents' => $bloodComponents, 'pageTitle' => 'Hemocomponentes']);
    }

    public static function create(): void { AdminGuard::enforce(self::PERMISSION); self::form(null, []); }
    public static function store(): void { AdminGuard::enforce(self::PERMISSION); self::save(null); }
    public static function edit(): void { AdminGuard::enforce(self::PERMISSION); $item=self::requested(); if(!$item){self::notFound();return;} self::form($item, []); }
    public static function update(): void { AdminGuard::enforce(self::PERMISSION); $item=self::requested(true); if(!$item){self::notFound();return;} self::save($item); }
    public static function saveShelfLife():void
    {
        AdminGuard::enforce(self::PERMISSION);if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada.');self::redirect('/admin/blood-components');}
        $componentId=(int)($_POST['blood_component_id']??0);$preservativeId=(int)($_POST['preservative_id']??0);$days=filter_var($_POST['shelf_life_days']??null,FILTER_VALIDATE_INT);$active=(int)($_POST['active']??1);$pdo=Database::connection();
        if(!$componentId||!$preservativeId||$days===false||$days<=0||!in_array($active,[0,1],true)){Flash::set('error','Informe preservante, validade maior que zero e status válidos.');self::redirect('/admin/blood-components/edit?id='.$componentId);}
        $q=$pdo->prepare('SELECT sl.*,p.code preservative_code FROM blood_component_preservative_shelf_lives sl JOIN preservatives p ON p.id=sl.preservative_id WHERE sl.blood_component_id=:c AND sl.preservative_id=:p');$q->execute(['c'=>$componentId,'p'=>$preservativeId]);$before=$q->fetch(PDO::FETCH_ASSOC);
        if(!$before){$q=$pdo->prepare("SELECT 1 FROM preservatives WHERE id=:id AND active=1");$q->execute(['id'=>$preservativeId]);if(!$q->fetchColumn()){Flash::set('error','Selecione um preservante ativo.');self::redirect('/admin/blood-components/edit?id='.$componentId);}}
        if($before){$pdo->prepare('UPDATE blood_component_preservative_shelf_lives SET shelf_life_days=:days,active=:active WHERE id=:id')->execute(['days'=>$days,'active'=>$active,'id'=>$before['id']]);$id=(int)$before['id'];}else{$pdo->prepare('INSERT INTO blood_component_preservative_shelf_lives(blood_component_id,preservative_id,shelf_life_days,active) VALUES(:c,:p,:days,:active)')->execute(['c'=>$componentId,'p'=>$preservativeId,'days'=>$days,'active'=>$active]);$id=(int)$pdo->lastInsertId();}
        $after=['blood_component_id'=>$componentId,'preservative_id'=>$preservativeId,'shelf_life_days'=>(int)$days,'active'=>$active];Auth::registerAudit($before?'shelf_life.update':'shelf_life.create','blood_component_preservative_shelf_lives',$id,$before?:null,$after);Flash::set('success','Validade por preservante salva.');self::redirect('/admin/blood-components/edit?id='.$componentId);
    }

    public static function saveSpecification():void
    {
        AdminGuard::enforce(self::PERMISSION);
        if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada.');self::redirect('/admin/blood-components');}
        try{$saved=SpecificationVersionService::save($_POST);Flash::set('success',$saved['message']);}
        catch(\DomainException $e){Flash::set('error',$e->getMessage());}
        catch(\Throwable $e){self::logSpecificationFailure($e,$_POST);Flash::set('error','Não foi possível salvar a especificação.');}
        self::redirect('/admin/blood-components/edit?id='.(int)($_POST['blood_component_id']??0));
    }

    private static function logSpecificationFailure(\Throwable $e,array $payload):void
    {
        unset($payload['_csrf']);
        foreach($payload as $key=>$value){if(is_string($value)&&mb_strlen($value)>500)$payload[$key]=mb_substr($value,0,500).'…';}
        $sqlState=$e instanceof PDOException?(string)($e->errorInfo[0]??$e->getCode()):(string)$e->getCode();
        error_log(sprintf('[BloodHub][quality_specification.save] exception=%s sqlstate=%s message=%s file=%s line=%d user_id=%s blood_component_id=%d payload=%s',$e::class,$sqlState,$e->getMessage(),$e->getFile(),$e->getLine(),(string)(Auth::user()['id']??'null'),(int)($payload['blood_component_id']??0),json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)));
    }
    public static function toggleSpecification():void{AdminGuard::enforce(self::PERMISSION);if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada.');self::redirect('/admin/blood-components');}$id=(int)($_POST['specification_id']??0);$c=(int)($_POST['blood_component_id']??0);SpecificationVersionService::toggle($id,$c);self::redirect('/admin/blood-components/edit?id='.$c);}

    public static function deleteSpecification():void
    {
        AdminGuard::enforce(self::PERMISSION);
        $componentId=(int)($_POST['blood_component_id']??0);
        if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada.');self::redirect('/admin/blood-components/edit?id='.$componentId);}
        try{SpecificationVersionService::delete((int)($_POST['specification_id']??0),$componentId);Flash::set('success','Especificação excluída definitivamente.');}
        catch(\DomainException $e){Flash::set('error',$e->getMessage());}
        catch(\Throwable $e){error_log('[BloodHub][quality_specification.delete] '.$e->getMessage());Flash::set('error','Não foi possível excluir a especificação.');}
        self::redirect('/admin/blood-components/edit?id='.$componentId);
    }

    public static function saveCpafYieldRule():void
    {
        AdminGuard::enforce(self::PERMISSION);$component=(int)($_POST['blood_component_id']??0);
        if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada.');self::redirect('/admin/blood-components/edit?id='.$component);}
        try{$saved=CpafYieldRuleVersionService::save($_POST);Flash::set('success',$saved['message']);}
        catch(\DomainException $e){Flash::set('error',$e->getMessage());}
        catch(\Throwable $e){error_log('[BloodHub][cpaf_yield_rule.save] '.$e->getMessage());Flash::set('error','Não foi possível salvar a classificação por rendimento.');}
        self::redirect('/admin/blood-components/edit?id='.$component);
    }

    public static function toggleCpafYieldRule():void
    {
        AdminGuard::enforce(self::PERMISSION);$component=(int)($_POST['blood_component_id']??0);
        if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada.');self::redirect('/admin/blood-components/edit?id='.$component);}
        try{$saved=CpafYieldRuleVersionService::setActive((int)($_POST['rule_id']??0),$component,(int)($_POST['active']??0)===1);Flash::set('success',$saved['message']);}
        catch(\DomainException $e){Flash::set('error',$e->getMessage());}
        catch(\Throwable $e){error_log('[BloodHub][cpaf_yield_rule.toggle] '.$e->getMessage());Flash::set('error','Não foi possível alterar o status da versão.');}
        self::redirect('/admin/blood-components/edit?id='.$component);
    }

    private static function save(?array $current): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('error', 'Sessão expirada. Tente novamente.');
            self::redirect($current ? '/admin/blood-components/edit?id='.(int)$current['id'] : '/admin/blood-components/create');
        }
        $data = [
            'code' => trim((string)($_POST['code'] ?? '')),
            'name' => trim((string)($_POST['name'] ?? '')),
            'density' => self::decimal($_POST['density'] ?? null),
            'transport_temperature_min' => self::decimal($_POST['transport_temperature_min'] ?? null),
            'transport_temperature_max' => self::decimal($_POST['transport_temperature_max'] ?? null),
            'description' => trim((string)($_POST['description'] ?? '')) ?: null,
            'status' => (string)($_POST['status'] ?? ''),
        ];
        $errors = [];
        if ($data['code'] === '') $errors[] = 'O código é obrigatório.';
        if (mb_strlen($data['code']) > 60) $errors[] = 'O código deve ter no máximo 60 caracteres.';
        if ($data['name'] === '') $errors[] = 'O nome é obrigatório.';
        if (mb_strlen($data['name']) > 180) $errors[] = 'O nome deve ter no máximo 180 caracteres.';
        if (!in_array($data['status'], ['active', 'inactive'], true)) $errors[] = 'Selecione um status válido.';

        if ($data['density'] === null || (float)$data['density'] <= 0) $errors[] = 'Informe uma densidade maior que zero.';
        if ($data['transport_temperature_min'] === null) $errors[] = 'Informe a temperatura mínima de transporte.';
        if ($data['transport_temperature_max'] === null) $errors[] = 'Informe a temperatura máxima de transporte.';
        if ($data['transport_temperature_min'] !== null && $data['transport_temperature_max'] !== null && (float)$data['transport_temperature_min'] > (float)$data['transport_temperature_max']) $errors[] = 'A temperatura mínima deve ser menor ou igual à temperatura máxima.';

        $pdo = Database::connection();
        if ($data['code'] !== '') {
            $unique = $pdo->prepare('SELECT id FROM blood_components WHERE code=:code AND id<>:id LIMIT 1');
            $unique->execute(['code' => $data['code'], 'id' => $current['id'] ?? 0]);
            if ($unique->fetchColumn()) $errors[] = 'Já existe um hemocomponente com este código.';
        }
        if ($errors) { self::form(array_merge($current ?? [], $data), $errors); return; }

        try {
            if ($current) {
                $data['id'] = $current['id'];
                $pdo->prepare('UPDATE blood_components SET code=:code,name=:name,density=:density,transport_temperature_min=:transport_temperature_min,transport_temperature_max=:transport_temperature_max,description=:description,status=:status WHERE id=:id')->execute($data);
                $after=$data; unset($after['id']);
                Auth::registerAudit('blood_component.update', 'blood_components', (int)$current['id'], $current, $after);
                Flash::set('success', 'Hemocomponente atualizado com sucesso.');
            } else {
                $pdo->prepare('INSERT INTO blood_components(code,name,density,transport_temperature_min,transport_temperature_max,description,status) VALUES(:code,:name,:density,:transport_temperature_min,:transport_temperature_max,:description,:status)')->execute($data);
                $id = (int)$pdo->lastInsertId();
                Auth::registerAudit('blood_component.create', 'blood_components', $id, null, $data);
                Flash::set('success', 'Hemocomponente cadastrado com sucesso.');
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { self::form(array_merge($current ?? [], $data), ['Já existe um hemocomponente com este código.']); return; }
            throw $e;
        }
        self::redirect('/admin/blood-components');
    }

    private static function form(?array $bloodComponent,array $errors):void{$shelfLives=[];$preservatives=[];$specifications=[];$componentTests=[];if($bloodComponent&&!empty($bloodComponent['id'])){$pdo=Database::connection();$q=$pdo->prepare('SELECT sl.*,p.code preservative_code,p.name preservative_name FROM blood_component_preservative_shelf_lives sl JOIN preservatives p ON p.id=sl.preservative_id WHERE sl.blood_component_id=:id ORDER BY sl.active DESC,p.code,p.name');$q->execute(['id'=>$bloodComponent['id']]);$shelfLives=$q->fetchAll(PDO::FETCH_ASSOC);$preservatives=$pdo->query("SELECT id,code,name FROM preservatives WHERE active=1 ORDER BY code,name")->fetchAll(PDO::FETCH_ASSOC);$q=$pdo->prepare('SELECT sp.*,t.name test_name,p.name preservative_name FROM blood_component_test_specifications sp JOIN tests t ON t.id=sp.test_id LEFT JOIN preservatives p ON p.id=sp.preservative_id WHERE sp.blood_component_id=:id ORDER BY sp.active DESC,t.name');$q->execute(['id'=>$bloodComponent['id']]);$specifications=$q->fetchAll(PDO::FETCH_ASSOC);foreach($specifications as &$specification)$specification['is_used']=SpecificationVersionService::isUsed((int)$specification['id'],$pdo)?1:0;unset($specification);$q=$pdo->prepare("SELECT t.id,t.name,t.unit FROM tests t JOIN test_blood_components x ON x.test_id=t.id WHERE x.blood_component_id=:id AND t.status='active' ORDER BY t.name");$q->execute(['id'=>$bloodComponent['id']]);$componentTests=$q->fetchAll(PDO::FETCH_ASSOC);}self::view('form',compact('bloodComponent','errors','shelfLives','preservatives','specifications','componentTests')+['pageTitle'=>$bloodComponent&&!empty($bloodComponent['id'])?'Editar Hemocomponente':'Novo Hemocomponente']);}
    private static function decimal(mixed $value): ?string { $raw=str_replace(',','.',trim((string)$value));if($raw===''||!preg_match('/^-?\d{1,3}(?:\.\d{1,4})?$/',$raw))return null;return number_format((float)$raw,4,'.',''); }
    private static function requested(bool $fromPost=false): ?array { $id=filter_var($fromPost ? ($_POST['id']??null) : ($_GET['id']??null), FILTER_VALIDATE_INT); if(!$id)return null; $s=Database::connection()->prepare('SELECT * FROM blood_components WHERE id=:id');$s->execute(['id'=>$id]);return $s->fetch(PDO::FETCH_ASSOC)?:null; }
    private static function view(string $name,array $vars):void { extract($vars);$flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();require dirname(__DIR__,2).'/Views/admin/blood_components/'.$name.'.php'; }
    private static function redirect(string $url):never { header('Location: '.$url);exit; }
    private static function notFound():void { http_response_code(404);echo 'Hemocomponente não encontrado.'; }
}
