<?php
declare(strict_types=1);
use BloodHub\Core\Database;
use BloodHub\Services\QcMonthlyClosureService as Closure;
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string$c):void{$p='BloodHub\\';if(!str_starts_with($c,$p))return;$f=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($c,strlen($p))).'.php';if(is_file($f))require$f;});
$pdo=Database::connection();$fail=[];$check=static function(bool$ok,string$msg)use(&$fail):void{if(!$ok)$fail[]=$msg;};$unitId=0;$closureId=0;$year=2098;$month=7;
try{
    foreach(['qc_monthly_closures','qc_monthly_closure_versions']as$t)$check((bool)$pdo->query("SHOW TABLES LIKE '$t'")->fetchColumn(),"Tabela $t ausente");
    foreach(['monthly_closure.view','monthly_closure.close','monthly_closure.reopen']as$p){$q=$pdo->prepare('SELECT COUNT(*) FROM permissions WHERE permission_key=? AND status="active"');$q->execute([$p]);$check((int)$q->fetchColumn()===1,"Permissão $p ausente");}
    $pdo->exec("INSERT INTO units(client_id,name,unit_type,code,status,created_at,updated_at) VALUES(NULL,'Teste Fechamento Mensal','processing','TEST-QCMC','active',NOW(),NOW())");$unitId=(int)$pdo->lastInsertId();
    $missingProduction=Closure::buildChecklist($unitId,$year,$month);$check(count($missingProduction['pending'])>0,'Cenário sem produção não gerou pendências');$missingPermission=Closure::canClose($missingProduction);$check($missingPermission['allowed'],'Produção não informada ocultou ou bloqueou o fechamento');$check($missingPermission['requires_justification'],'Produção não informada não exigiu justificativa');
    $period=new DateTimeImmutable(sprintf('%04d-%02d-01',$year,$month));$sources=[];foreach(\BloodHub\Services\SamplingScheduleService::buildMonthlySummary($unitId,$period->format('Y-m'),$period->modify('last day of this month'))as$r)$sources[(int)($r['source_id']?:$r['id'])]=true;
    $ins=$pdo->prepare('INSERT INTO production_records(unit_id,production_date,blood_component_id,quantity,created_at,updated_at) VALUES(:unit,:date,:component,0,NOW(),NOW())');foreach(array_keys($sources)as$component)$ins->execute(['unit'=>$unitId,'date'=>$period->format('Y-m-01'),'component'=>$component]);
    $list=$pdo->query("SELECT id FROM users WHERE status='active' ORDER BY id LIMIT 1")->fetchColumn();if(!$list)throw new RuntimeException('Nenhum usuário ativo para o teste.');$user=(int)$list;
    $before=Closure::buildChecklist($unitId,$year,$month);$check($before['blocking']===0,'Produção zero informada foi confundida com produção ausente');$check(Closure::canClose($before)['allowed'],'Período limpo não ficou apto');
    $v1=Closure::close($unitId,$year,$month,$user,'Fechamento automatizado de integração');$check($v1===1,'Primeira versão não é 1');$status=Closure::getPeriodStatus($unitId,$year,$month);$closureId=(int)$status['id'];$check($status['status']==='CLOSED','Status não ficou CLOSED');
    Closure::reopen($unitId,$year,$month,$user,'Ajuste automatizado de produção');$check(Closure::getPeriodStatus($unitId,$year,$month)['status']==='REOPENED','Status não ficou REOPENED');
    $pdo->prepare('UPDATE production_records SET quantity=100000 WHERE unit_id=:unit LIMIT 1')->execute(['unit'=>$unitId]);
    $withPending=Closure::buildChecklist($unitId,$year,$month);$check(count($withPending['pending'])>0,'Cenário com amostragem pendente não foi criado');$check(Closure::canClose($withPending)['allowed'],'Pendência justificável bloqueou o fechamento');$check(Closure::canClose($withPending)['requires_justification'],'Justificativa não foi exigida para pendências');
    $blankRejected=false;try{Closure::close($unitId,$year,$month,$user,'');}catch(DomainException$e){$blankRejected=true;}$check($blankRejected,'Fechamento com pendências aceitou justificativa vazia');$check(Closure::getPeriodStatus($unitId,$year,$month)['status']==='REOPENED','Tentativa sem justificativa alterou o status');
    $v2=Closure::close($unitId,$year,$month,$user,'Impossibilidade operacional de completar o envio');$check($v2===2,'Segundo fechamento não criou versão 2');$history=Closure::getHistory($closureId);$check(count($history)===2,'Histórico não preservou duas versões');$check(Closure::getVersion($closureId,1)!==null,'Snapshot da versão 1 não foi preservado');
}catch(Throwable$e){$fail[]=$e->getMessage();}
finally{if($unitId){$pdo->prepare("DELETE FROM audit_logs WHERE entity_type='qc_monthly_closures' AND entity_id=:id")->execute(['id'=>$closureId]);$pdo->prepare('DELETE FROM qc_monthly_closures WHERE unit_id=:unit AND year=:year AND month=:month')->execute(['unit'=>$unitId,'year'=>$year,'month'=>$month]);$pdo->prepare('DELETE FROM production_records WHERE unit_id=:unit')->execute(['unit'=>$unitId]);$pdo->prepare('DELETE FROM units WHERE id=:unit')->execute(['unit'=>$unitId]);}}
if($fail){fwrite(STDERR,"FALHA\n- ".implode("\n- ",$fail)."\n");exit(1);}fwrite(STDOUT,"OK: schema, RBAC, fechamento limpo, fechamento justificado, snapshot, reabertura e versionamento validados.\n");
