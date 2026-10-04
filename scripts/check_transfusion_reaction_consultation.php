<?php
declare(strict_types=1);

use BloodHub\Core\Database;
use BloodHub\Services\{LaboratoryReportService,TransfusionReactionConsultationService as Consultation,UnitAccessService};

if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string$class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});
$assert=static function(bool$ok,string$message):void{if(!$ok)throw new RuntimeException($message);};
$pdo=Database::connection();
$permission=(int)$pdo->query("SELECT COUNT(*) FROM permissions WHERE permission_key='transfusion_reaction_consultation.view' AND status='active'")->fetchColumn();
$assert($permission===1,'Permissão de consulta ausente.');
$roles=$pdo->query("SELECT r.slug,GROUP_CONCAT(p.permission_key) permissions FROM roles r LEFT JOIN role_permissions rp ON rp.role_id=r.id LEFT JOIN permissions p ON p.id=rp.permission_id WHERE r.slug IN ('administrador','lcqh','gestao','agencia-transfusional') GROUP BY r.id")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach(['administrador','lcqh','gestao','agencia-transfusional']as$role)$assert(str_contains((string)($roles[$role]??''),Consultation::PERMISSION),"Permissão ausente no perfil $role.");
$assert(!str_contains((string)$roles['agencia-transfusional'],'transfusion_reactions.manage'),'Cliente não pode manter permissão operacional de RT.');

$admin=$pdo->query("SELECT u.id,u.client_id,r.id role_id,r.slug role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='administrador' AND u.status='active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if(!$admin){$role=$pdo->query("SELECT id role_id,slug role_slug FROM roles WHERE slug='administrador'")->fetch(PDO::FETCH_ASSOC);$admin=['id'=>0,'client_id'=>null]+$role;}
$_SESSION['user']=$admin;$result=Consultation::search(['from'=>'2000-01-01','to'=>'2099-12-31']);
$assert(count($result['rows'])<=Consultation::PER_PAGE,'Paginação excedeu dez registros.');

$restricted=$pdo->query("SELECT u.id,u.client_id,r.id role_id,r.slug role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='agencia-transfusional' AND u.status='active' AND EXISTS(SELECT 1 FROM user_units uu WHERE uu.user_id=u.id) LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$temporary=false;
if(!$restricted){$candidate=$pdo->query("SELECT u.id,u.client_id FROM units u WHERE u.status='active' AND u.client_id IS NOT NULL ORDER BY u.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);$roleId=(int)$pdo->query("SELECT id FROM roles WHERE slug='agencia-transfusional'")->fetchColumn();if($candidate&&$roleId){$pdo->beginTransaction();$pdo->prepare("INSERT INTO users(role_id,client_id,primary_unit_id,name,email,password_hash,status) VALUES(:role,:client,:unit,'Teste Consulta RT',:email,:password,'active')")->execute(['role'=>$roleId,'client'=>$candidate['client_id'],'unit'=>$candidate['id'],'email'=>'consulta-rt-check-'.bin2hex(random_bytes(5)).'@invalid.local','password'=>password_hash(bin2hex(random_bytes(8)),PASSWORD_DEFAULT)]);$userId=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO user_units(user_id,unit_id) VALUES(:user,:unit)')->execute(['user'=>$userId,'unit'=>$candidate['id']]);$restricted=['id'=>$userId,'client_id'=>$candidate['client_id'],'role_id'=>$roleId,'role_slug'=>'agencia-transfusional'];$temporary=true;}}
if($restricted){$_SESSION['user']=$restricted;$visible=UnitAccessService::visibleUnits();$visibleIds=array_map(fn($u)=>(int)$u['id'],$visible);$assert($visibleIds!==[],'Usuário cliente deveria possuir unidade visível.');$foreign=$pdo->prepare('SELECT id FROM units WHERE status=\'active\' AND id NOT IN ('.implode(',',array_fill(0,count($visibleIds),'?')).') LIMIT 1');$foreign->execute($visibleIds);$foreignId=(int)$foreign->fetchColumn();if($foreignId){$blocked=false;try{Consultation::search(['from'=>'2000-01-01','to'=>'2099-12-31','unit_id'=>$foreignId]);}catch(DomainException){$blocked=true;}$assert($blocked,'Manipulação manual de unit_id não foi bloqueada.');}
    $own=Consultation::search(['from'=>'2000-01-01','to'=>'2099-12-31']);foreach($own['rows']as$row)$assert(in_array((int)$row['origin_unit_id'],$visibleIds,true),'Consulta retornou unidade fora do escopo.');
    $marks=implode(',',array_fill(0,count($visibleIds),'?'));$report=$pdo->prepare("SELECT id FROM laboratory_reports WHERE unit_id IS NOT NULL AND unit_id NOT IN ($marks) LIMIT 1");$report->execute($visibleIds);$reportId=(int)$report->fetchColumn();if($reportId){$blocked=false;try{LaboratoryReportService::find($reportId);}catch(DomainException){$blocked=true;}$assert($blocked,'Acesso direto ao laudo de outra unidade não foi bloqueado.');}
}
if($temporary&&$pdo->inTransaction())$pdo->rollBack();
fwrite(STDOUT,"OK: RBAC, paginação e escopo de unidade da Consulta RT validados com usuário cliente".($temporary?' temporário (rollback).':'.').PHP_EOL);
