<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database};
use DateTimeImmutable;
use DomainException;
use PDO;

final class QcMonthlyClosureService
{
    public const VIEW='monthly_closure.view';
    public const CLOSE='monthly_closure.close';
    public const REOPEN='monthly_closure.reopen';
    private const MONTHS=[1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];

    public static function monthName(int $month):string{return self::MONTHS[$month]??'';}

    public static function getPeriodStatus(int $unitId,int $year,int $month):array
    {
        $q=Database::connection()->prepare('SELECT c.*,u.name unit_name,cb.name closed_by_name,rb.name reopened_by_name FROM qc_monthly_closures c JOIN units u ON u.id=c.unit_id LEFT JOIN users cb ON cb.id=c.closed_by LEFT JOIN users rb ON rb.id=c.reopened_by WHERE c.unit_id=:unit AND c.year=:year AND c.month=:month');
        $q->execute(['unit'=>$unitId,'year'=>$year,'month'=>$month]);$row=$q->fetch(PDO::FETCH_ASSOC);
        return $row?:['id'=>null,'unit_id'=>$unitId,'year'=>$year,'month'=>$month,'status'=>'OPEN','closed_by'=>null,'closed_at'=>null,'closure_notes'=>null,'reopened_by'=>null,'reopened_at'=>null,'reopen_reason'=>null];
    }

    public static function isClosed(int $unitId,int $year,int $month):bool
    {
        return self::getPeriodStatus($unitId,$year,$month)['status']==='CLOSED';
    }

    public static function isSamplePeriodClosed(int $sampleId):bool
    {
        $q=Database::connection()->prepare("SELECT c.status FROM samples s LEFT JOIN qc_monthly_closures c ON c.unit_id=s.origin_unit_id AND c.year=YEAR(s.production_date) AND c.month=MONTH(s.production_date) WHERE s.id=:id AND s.purpose='quality_control'");
        $q->execute(['id'=>$sampleId]);return$q->fetchColumn()==='CLOSED';
    }

    public static function buildChecklist(int $unitId,int $year,int $month):array
    {
        self::validatePeriod($year,$month);$pdo=Database::connection();$period=new DateTimeImmutable(sprintf('%04d-%02d-01',$year,$month));$from=$period->format('Y-m-01');$to=$period->format('Y-m-t');
        $unitQ=$pdo->prepare("SELECT id,name,code FROM units WHERE id=:id AND unit_type='processing'");$unitQ->execute(['id'=>$unitId]);$unit=$unitQ->fetch(PDO::FETCH_ASSOC);if(!$unit)throw new DomainException('Unidade de processamento inválida.');
        $sampling=SamplingScheduleService::buildMonthlySummary($unitId,$period->format('Y-m'),$period->modify('last day of this month'));
        $prodQ=$pdo->prepare('SELECT blood_component_id,COUNT(*) records,SUM(quantity) total FROM production_records WHERE unit_id=:unit AND production_date BETWEEN :from AND :to GROUP BY blood_component_id');$prodQ->execute(['unit'=>$unitId,'from'=>$from,'to'=>$to]);$prod=[];foreach($prodQ->fetchAll(PDO::FETCH_ASSOC)as$r)$prod[(int)$r['blood_component_id']]=$r;
        $samples=self::sampleCounts($unitId,$from,$to);$analytical=self::analyticalPending($unitId,$from,$to);$notifications=self::notificationCounts($unitId,$from,$to);$corrections=self::correctionCounts($unitId,$from,$to);
        $rows=[];$pending=[];$totals=['production'=>0,'required'=>0,'sent'=>0,'qc_completed'=>$samples['completed'],'qc_in_progress'=>$samples['in_progress']+$samples['received']+$samples['partial_results'],'open_notifications'=>$notifications['open']];
        foreach($sampling as$r){$id=(int)$r['id'];$source=(int)($r['source_id']?:$id);$informed=isset($prod[$source]);$produced=$informed?(int)$prod[$source]['total']:null;$required=$produced===null?0:SamplingScheduleService::calculateRequired($produced,$r['rule']);$sent=(int)$r['sent'];$missing=max(0,$required-$sent);$totals['production']+=$produced??0;$totals['required']+=$required;$totals['sent']+=$sent;$rule=$r['rule'];
            $rows[]=['id'=>$id,'code'=>$r['code'],'name'=>$r['name'],'production_source_id'=>$source,'produced'=>$produced,'production_informed'=>$informed,'required'=>$required,'sent'=>$sent,'pending'=>$missing,'qc_completed'=>(int)($samples['completed_by_component'][$id]??0),'sampling_status'=>$missing?'BELOW_MINIMUM':'MET','rule'=>$rule?['id'=>(int)$rule['id'],'percentage'=>(float)$rule['percentage'],'minimum_units'=>(int)$rule['minimum_units'],'small_production_mode'=>$rule['small_production_mode'],'small_production_limit'=>$rule['small_production_limit']===null?null:(int)$rule['small_production_limit'],'effective_from'=>$rule['effective_from'],'effective_to'=>$rule['effective_to']]:null];
            if(!$informed)$pending[]=['category'=>'Produção não informada','severity'=>'BLOCKING','message'=>$r['code'].' - produção necessária não informada.'];
            if($missing)$pending[]=['category'=>'Amostragem abaixo do mínimo','severity'=>'JUSTIFIABLE','message'=>$r['code'].' - faltam '.$missing.' unidade(s).'];
        }
        if($analytical['non_bacteriology_incomplete']>0)$pending[]=['category'=>'Resultados incompletos','severity'=>'JUSTIFIABLE','message'=>$analytical['non_bacteriology_incomplete'].' teste(s) obrigatório(s) sem conclusão.'];
        if($totals['qc_in_progress']>0)$pending[]=['category'=>'Análises em andamento','severity'=>'JUSTIFIABLE','message'=>$totals['qc_in_progress'].' amostra(s) de CQ ainda não concluída(s).'];
        if($analytical['bacteriology_pending']>0)$pending[]=['category'=>'Bacteriológicos pendentes','severity'=>'JUSTIFIABLE','message'=>$analytical['bacteriology_pending'].' análise(s) bacteriológica(s) pendente(s).'];
        if($notifications['open']>0)$pending[]=['category'=>'Notificações abertas','severity'=>'JUSTIFIABLE','message'=>$notifications['open'].' notificação(ões) em acompanhamento.'];
        $blocking=count(array_filter($pending,fn($p)=>$p['severity']==='BLOCKING'));$status=self::getPeriodStatus($unitId,$year,$month);if(!$status['id']&&$period->format('Y-m')<date('Y-m'))$status['overdue']=true;
        $suggested=$pending||$period->format('Y-m')>=date('Y-m')?'OPEN':'READY';if($status['status']==='OPEN'&&!$pending&&$period->format('Y-m')<date('Y-m'))$status['status']='READY';
        return compact('unit','year','month','from','to','rows','totals','pending','blocking','samples','analytical','notifications','corrections','status','suggested')+['period_label'=>self::monthName($month).'/'.$year];
    }

    public static function canClose(array $checklist):array
    {
        // Pendências nunca escondem nem bloqueiam o fechamento. Elas mudam o
        // aceite para um fechamento excepcional, com justificativa obrigatória.
        return['allowed'=>true,'requires_justification'=>count($checklist['pending'])>0,'reason'=>null];
    }

    public static function close(int $unitId,int $year,int $month,int $userId,string $notes):int
    {
        $pdo=Database::connection();$pdo->beginTransaction();
        try{$check=self::buildChecklist($unitId,$year,$month);$permission=self::canClose($check);if(!$permission['allowed'])throw new DomainException($permission['reason']);if($permission['requires_justification']&&mb_strlen(trim($notes))<5)throw new DomainException('Informe a justificativa para o fechamento com pendências.');
            $upsert=$pdo->prepare("INSERT INTO qc_monthly_closures(unit_id,year,month,status,created_at,updated_at) VALUES(:unit,:year,:month,'OPEN',NOW(),NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),updated_at=updated_at");$upsert->execute(['unit'=>$unitId,'year'=>$year,'month'=>$month]);$id=(int)$pdo->lastInsertId();
            $lock=$pdo->prepare('SELECT * FROM qc_monthly_closures WHERE id=:id FOR UPDATE');$lock->execute(['id'=>$id]);$current=$lock->fetch(PDO::FETCH_ASSOC);if(($current['status']??'')==='CLOSED')throw new DomainException('Este período já está fechado.');
            $v=$pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM qc_monthly_closure_versions WHERE closure_id=:id');$v->execute(['id'=>$id]);$version=(int)$v->fetchColumn();$snapshot=self::createSnapshot($check,$version);
            $ins=$pdo->prepare('INSERT INTO qc_monthly_closure_versions(closure_id,version_number,snapshot_version,snapshot_json,closure_notes,has_pending_items,closed_by,closed_at) VALUES(:closure,:version,1,:snapshot,:notes,:pending,:user,NOW())');$ins->execute(['closure'=>$id,'version'=>$version,'snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'notes'=>trim($notes)?:null,'pending'=>count($check['pending'])?1:0,'user'=>$userId]);
            $upd=$pdo->prepare("UPDATE qc_monthly_closures SET status='CLOSED',closed_by=:user,closed_at=NOW(),closure_notes=:notes,reopened_by=NULL,reopened_at=NULL,reopen_reason=NULL WHERE id=:id");$upd->execute(['user'=>$userId,'notes'=>trim($notes)?:null,'id'=>$id]);
            if($version===1)self::audit($pdo,'QC_MONTHLY_CLOSURE_CREATED','qc_monthly_closures',$id,$userId,['unit_id'=>$unitId,'year'=>$year,'month'=>$month]);self::audit($pdo,'QC_MONTHLY_CLOSED','qc_monthly_closures',$id,$userId,['unit_id'=>$unitId,'year'=>$year,'month'=>$month,'version'=>$version,'pending_items'=>count($check['pending'])]);if($check['pending'])self::audit($pdo,'QC_MONTHLY_CLOSED_WITH_PENDING_ITEMS','qc_monthly_closures',$id,$userId,['version'=>$version,'justification'=>$notes]);$pdo->commit();return$version;
        }catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    public static function reopen(int $unitId,int $year,int $month,int $userId,string $reason):void
    {
        if(mb_strlen(trim($reason))<5)throw new DomainException('Informe o motivo da reabertura.');$pdo=Database::connection();$pdo->beginTransaction();
        try{$q=$pdo->prepare('SELECT * FROM qc_monthly_closures WHERE unit_id=:unit AND year=:year AND month=:month FOR UPDATE');$q->execute(['unit'=>$unitId,'year'=>$year,'month'=>$month]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row||$row['status']!=='CLOSED')throw new DomainException('Somente um período fechado pode ser reaberto.');
            $pdo->prepare("UPDATE qc_monthly_closures SET status='REOPENED',reopened_by=:user,reopened_at=NOW(),reopen_reason=:reason WHERE id=:id")->execute(['user'=>$userId,'reason'=>trim($reason),'id'=>$row['id']]);$pdo->prepare('UPDATE qc_monthly_closure_versions SET reopened_by=:user,reopened_at=NOW(),reopen_reason=:reason WHERE closure_id=:id AND version_number=(SELECT v FROM (SELECT MAX(version_number) v FROM qc_monthly_closure_versions WHERE closure_id=:id2) x)')->execute(['user'=>$userId,'reason'=>trim($reason),'id'=>$row['id'],'id2'=>$row['id']]);self::audit($pdo,'QC_MONTHLY_REOPENED','qc_monthly_closures',(int)$row['id'],$userId,['reason'=>trim($reason)]);$pdo->commit();
        }catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    public static function createSnapshot(array $checklist,int $version):array
    {
        return['schema_version'=>1,'closure_version'=>$version,'captured_at'=>date(DATE_ATOM),'period'=>['unit_id'=>(int)$checklist['unit']['id'],'unit_name'=>$checklist['unit']['name'],'year'=>$checklist['year'],'month'=>$checklist['month'],'label'=>$checklist['period_label'],'date_basis'=>'samples.production_date'],'summary'=>$checklist['totals'],'components'=>$checklist['rows'],'sample_statuses'=>$checklist['samples'],'analytical_pending'=>$checklist['analytical'],'notifications'=>$checklist['notifications'],'administrative_corrections'=>$checklist['corrections'],'pending_items'=>$checklist['pending'],'sampling_rules_note'=>'Regras efetivas retornadas por SamplingScheduleService no período.'];
    }

    public static function getHistory(int $closureId):array
    {$q=Database::connection()->prepare('SELECT v.*,cb.name closed_by_name,rb.name reopened_by_name FROM qc_monthly_closure_versions v LEFT JOIN users cb ON cb.id=v.closed_by LEFT JOIN users rb ON rb.id=v.reopened_by WHERE v.closure_id=:id ORDER BY v.version_number DESC');$q->execute(['id'=>$closureId]);return$q->fetchAll(PDO::FETCH_ASSOC);}
    public static function getVersion(int $closureId,int $version):?array
    {$q=Database::connection()->prepare('SELECT v.*,c.unit_id,c.year,c.month,u.name unit_name,cb.name closed_by_name FROM qc_monthly_closure_versions v JOIN qc_monthly_closures c ON c.id=v.closure_id JOIN units u ON u.id=c.unit_id JOIN users cb ON cb.id=v.closed_by WHERE v.closure_id=:id AND v.version_number=:version');$q->execute(['id'=>$closureId,'version'=>$version]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return null;$r['snapshot']=json_decode($r['snapshot_json'],true,512,JSON_THROW_ON_ERROR);return$r;}

    private static function sampleCounts(int$unit,string$from,string$to):array
    {$q=Database::connection()->prepare("SELECT status,blood_component_id,COUNT(*) total FROM samples WHERE origin_unit_id=:unit AND purpose='quality_control' AND status<>'cancelled' AND production_date BETWEEN :from AND :to GROUP BY status,blood_component_id");$q->execute(['unit'=>$unit,'from'=>$from,'to'=>$to]);$out=['received'=>0,'in_analysis'=>0,'partial_results'=>0,'completed'=>0,'in_progress'=>0,'completed_by_component'=>[],'total'=>0];foreach($q->fetchAll(PDO::FETCH_ASSOC)as$r){$n=(int)$r['total'];$out['total']+=$n;$status=$r['status'];if(isset($out[$status]))$out[$status]+=$n;if($status==='completed')$out['completed_by_component'][(int)$r['blood_component_id']]=($out['completed_by_component'][(int)$r['blood_component_id']]??0)+$n;}return$out;}
    private static function analyticalPending(int$unit,string$from,string$to):array
    {$q=Database::connection()->prepare("SELECT SUM(CASE WHEN (UPPER(COALESCE(t.code,''))='BACTERIOLOGY' OR t.name LIKE '%bacteriol%') AND st.status<>'completed' THEN 1 ELSE 0 END) bacteriology_pending,SUM(CASE WHEN COALESCE(tbc.is_required,1)=1 AND NOT (UPPER(COALESCE(t.code,''))='BACTERIOLOGY' OR t.name LIKE '%bacteriol%') AND st.status<>'completed' THEN 1 ELSE 0 END) non_bacteriology_incomplete FROM samples s JOIN sample_tests st ON st.sample_id=s.id LEFT JOIN tests t ON t.id=st.test_id LEFT JOIN test_blood_components tbc ON tbc.test_id=st.test_id AND tbc.blood_component_id=s.blood_component_id WHERE s.origin_unit_id=:unit AND s.purpose='quality_control' AND s.status<>'cancelled' AND st.status<>'cancelled' AND s.production_date BETWEEN :from AND :to");$q->execute(['unit'=>$unit,'from'=>$from,'to'=>$to]);$r=$q->fetch(PDO::FETCH_ASSOC)?:[];return['bacteriology_pending'=>(int)($r['bacteriology_pending']??0),'non_bacteriology_incomplete'=>(int)($r['non_bacteriology_incomplete']??0)];}
    private static function notificationCounts(int$unit,string$from,string$to):array
    {$q=Database::connection()->prepare("SELECT n.status,COUNT(*) total FROM qc_notifications n JOIN samples s ON s.id=n.sample_id WHERE n.origin_unit_id=:unit AND s.production_date BETWEEN :from AND :to AND n.status<>'CANCELLED' GROUP BY n.status");$q->execute(['unit'=>$unit,'from'=>$from,'to'=>$to]);$out=['open'=>0,'in_analysis'=>0,'in_follow_up'=>0,'completed'=>0,'by_status'=>[]];foreach($q->fetchAll(PDO::FETCH_ASSOC)as$r){$s=$r['status'];$n=(int)$r['total'];$out['by_status'][$s]=$n;if(in_array($s,['COMPLETED','CLOSED'],true))$out['completed']+=$n;else{$out['open']+=$n;if(in_array($s,['IN_ANALYSIS','ANALYZED'],true))$out['in_analysis']+=$n;if($s==='IN_FOLLOW_UP')$out['in_follow_up']+=$n;}}return$out;}
    private static function correctionCounts(int$unit,string$from,string$to):array
    {$q=Database::connection()->prepare("SELECT SUM(ac.correction_type IN ('LAB_RESULT','LAB_INPUT','BACTERIOLOGY_RESULT','POOL_RESULT')) results_rectified,SUM(ac.correction_type IN ('SAMPLE_IDENTIFICATION','SAMPLE_STATUS')) samples_corrected FROM administrative_corrections ac JOIN samples s ON s.id=ac.sample_id WHERE s.origin_unit_id=:unit AND s.production_date BETWEEN :from AND :to");$q->execute(['unit'=>$unit,'from'=>$from,'to'=>$to]);$r=$q->fetch(PDO::FETCH_ASSOC)?:[];return['results_rectified'=>(int)($r['results_rectified']??0),'samples_corrected'=>(int)($r['samples_corrected']??0)];}
    private static function validatePeriod(int$year,int$month):void{if($year<2000||$year>2100||$month<1||$month>12)throw new DomainException('Período inválido.');}
    private static function audit(PDO$pdo,string$action,string$type,int$id,int$user,array$after):void{$q=$pdo->prepare('INSERT INTO audit_logs(user_id,action,entity_type,entity_id,after_data,ip_address,user_agent,created_at) VALUES(:user,:action,:type,:id,:data,:ip,:agent,NOW())');$q->execute(['user'=>$user,'action'=>$action,'type'=>$type,'id'=>$id,'data'=>json_encode($after,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'ip'=>$_SERVER['REMOTE_ADDR']??null,'agent'=>substr($_SERVER['HTTP_USER_AGENT']??'',0,500)]);}
}
