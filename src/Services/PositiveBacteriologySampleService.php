<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database};
use DomainException;
use PDO;

final class PositiveBacteriologySampleService
{
    public static function createFromConfirmedResult(int $sampleId,int $resultId):int
    {
        $pdo=Database::connection();
        $q=$pdo->prepare("SELECT s.*,u.name origin_name,c.name client_name FROM samples s LEFT JOIN units u ON u.id=s.origin_unit_id LEFT JOIN clients c ON c.id=s.client_id WHERE s.id=:id");
        $q->execute(['id'=>$sampleId]);$s=$q->fetch(PDO::FETCH_ASSOC);
        if(!$s)throw new DomainException('Amostra confirmada não encontrada.');
        $existing=$pdo->prepare('SELECT id FROM bacteriology_positive_samples WHERE bacteriology_result_id=:result OR sample_id=:sample ORDER BY id LIMIT 1');$existing->execute(['result'=>$resultId,'sample'=>$sampleId]);
        if($id=$existing->fetchColumn())return(int)$id;
        $sendDate=!empty($s['sent_at'])?substr((string)$s['sent_at'],0,10):null;
        $pdo->prepare("INSERT INTO bacteriology_positive_samples(bacteriology_result_id,sample_id,client_id,donation_number,send_date,collection_date,origin,client,created_by) VALUES(:result,:sample,:client_id,:donation,:send_date,:collection_date,:origin,:client,:user)")->execute(['result'=>$resultId,'sample'=>$sampleId,'client_id'=>$s['client_id']?:null,'donation'=>(string)($s['donation_number']?:$s['sample_code']),'send_date'=>$sendDate,'collection_date'=>$s['collection_date'],'origin'=>$s['origin_name'],'client'=>$s['client_name'],'user'=>self::user()]);
        $id=(int)$pdo->lastInsertId();
        Auth::registerAudit('bacteriology.positive_sample_created','bacteriology_positive_samples',$id,null,['sample_id'=>$sampleId,'result_id'=>$resultId,'status'=>'pending']);
        return$id;
    }

    public static function all(array $f=[],string $purpose='quality_control'):array
    {
        $w=['s.purpose=:purpose'];$p=['purpose'=>$purpose];
        if(!empty($f['from'])){$w[]='ps.send_date>=:from';$p['from']=$f['from'];}
        if(!empty($f['to'])){$w[]='ps.send_date<=:to';$p['to']=$f['to'];}
        if(!empty($f['donation'])){$w[]='ps.donation_number LIKE :donation';$p['donation']='%'.$f['donation'].'%';}
        if(!empty($f['positive_status'])){$w[]='ps.status=:status';$p['status']=$f['positive_status'];}
        $q=Database::connection()->prepare("SELECT ps.*,s.patient_name,COUNT(r.id) record_count FROM bacteriology_positive_samples ps JOIN samples s ON s.id=ps.sample_id LEFT JOIN bacteriology_positive_sample_records r ON r.positive_sample_id=ps.id WHERE ".implode(' AND ',$w).' GROUP BY ps.id ORDER BY ps.created_at DESC,ps.id DESC');$q->execute($p);return$q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int$id):array
    {
        $q=Database::connection()->prepare("SELECT ps.*,COALESCE(ps.client_id,s.client_id) client_id,s.patient_name,s.purpose,s.lcqh_code,bc.id source_component_id,bc.name source_component,br.stage source_result_stage,br.identified_bacteria source_bacteria_retest,COALESCE((SELECT ir.identified_bacteria FROM bacteriology_retests rt JOIN bacteriology_results ir ON ir.id=rt.initial_result_id WHERE rt.result_id=br.id LIMIT 1),br.identified_bacteria) source_bacteria_initial FROM bacteriology_positive_samples ps JOIN samples s ON s.id=ps.sample_id JOIN bacteriology_results br ON br.id=ps.bacteriology_result_id LEFT JOIN blood_components bc ON bc.id=s.blood_component_id WHERE ps.id=:id");$q->execute(['id'=>$id]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)throw new DomainException('Ocorrência positiva não encontrada.');
        $q=Database::connection()->prepare('SELECT r.*,COALESCE(bc.code,\'\') component_code,COALESCE(bc.name,r.hemocomponent) hemocomponent,COALESCE(u.name,r.storage_location) storage_location FROM bacteriology_positive_sample_records r LEFT JOIN blood_components bc ON bc.id=r.blood_component_id LEFT JOIN units u ON u.id=r.storage_unit_id WHERE r.positive_sample_id=:id ORDER BY r.position,r.id');$q->execute(['id'=>$id]);$row['records']=$q->fetchAll(PDO::FETCH_ASSOC);
        $tests=Database::connection()->prepare('SELECT t.*,u.name performed_by_name FROM bacteriology_positive_record_tests t LEFT JOIN users u ON u.id=t.performed_by WHERE t.positive_record_id=:record ORDER BY t.sequence,t.id');
        foreach($row['records'] as&$record){$tests->execute(['record'=>$record['id']]);$record['tests']=$tests->fetchAll(PDO::FETCH_ASSOC);$record['investigation_status']=self::recordStatus($record);}$row['context_type']=$row['purpose'];return$row;
    }

    public static function activeComponents():array{return Database::connection()->query("SELECT id,code,name FROM blood_components WHERE status='active' ORDER BY code,name")->fetchAll(PDO::FETCH_ASSOC);}
    public static function activeUnitsForClient(int$clientId):array{if(!$clientId)return[];$q=Database::connection()->prepare("SELECT id,name,unit_type FROM units WHERE client_id=:client AND status='active' ORDER BY name");$q->execute(['client'=>$clientId]);return$q->fetchAll(PDO::FETCH_ASSOC);}

    public static function relatedComponents(int$id):array
    {
        $positive=self::find($id);$q=Database::connection()->prepare("SELECT DISTINCT bc.name FROM samples s JOIN blood_components bc ON bc.id=s.blood_component_id WHERE s.donation_number=:donation ORDER BY bc.name");$q->execute(['donation'=>$positive['donation_number']]);return array_column($q->fetchAll(PDO::FETCH_ASSOC),'name');
    }

    public static function addRelatedComponent(int $positiveId,array $data):int
    {
        $pdo=Database::connection();$pdo->beginTransaction();
        try{$positive=self::lockedRtPositive($positiveId);$component=(int)($data['blood_component_id']??0);$situation=trim((string)($data['situation']??''));$storage=(int)($data['storage_unit_id']??0);$perform=($data['perform_bacteriology']??'no')==='yes';$notes=trim((string)($data['operational_notes']??''));
            if(!$component)throw new DomainException('Selecione o hemocomponente.');if(!in_array($situation,['in_stock','distributed','discarded'],true))throw new DomainException('Selecione uma situação válida.');if(mb_strlen($notes)>2000)throw new DomainException('A observação deve ter no máximo 2.000 caracteres.');
            $q=$pdo->prepare("SELECT code,name FROM blood_components WHERE id=:id AND status='active'");$q->execute(['id'=>$component]);$blood=$q->fetch(PDO::FETCH_ASSOC);if(!$blood)throw new DomainException('Hemocomponente inválido ou inativo.');
            $q=$pdo->prepare('SELECT id FROM bacteriology_positive_sample_records WHERE positive_sample_id=:parent AND blood_component_id=:component FOR UPDATE');$q->execute(['parent'=>$positiveId,'component'=>$component]);if($q->fetchColumn())throw new DomainException('Este hemocomponente já está relacionado à investigação.');
            $storageName=null;if($situation==='in_stock'){if(!$storage)throw new DomainException('Informe o local de armazenamento para componente em estoque.');$q=$pdo->prepare("SELECT name FROM units WHERE id=:id AND client_id=:client AND status='active'");$q->execute(['id'=>$storage,'client'=>(int)$positive['client_id']]);$storageName=$q->fetchColumn();if(!$storageName)throw new DomainException('Selecione um local ativo vinculado ao cliente da reação.');}else{$storage=null;}
            $q=$pdo->prepare('SELECT COALESCE(MAX(position),0)+1 FROM bacteriology_positive_sample_records WHERE positive_sample_id=:id FOR UPDATE');$q->execute(['id'=>$positiveId]);$position=max(2,(int)$q->fetchColumn());
            $pdo->prepare('INSERT INTO bacteriology_positive_sample_records(positive_sample_id,position,include_in_form,send_date,donation_number,blood_component_id,hemocomponent,collection_date,origin,situation,storage_unit_id,storage_location,reaction,perform_bacteriology,test_name,operational_notes) VALUES(:parent,:position,1,:send_date,:donation,:component,:name,:collection,:origin,:situation,:storage,:storage_name,\'yes\',:perform,:test_name,:notes)')->execute(['parent'=>$positiveId,'position'=>$position,'send_date'=>$positive['send_date'],'donation'=>$positive['donation_number'],'component'=>$component,'name'=>$blood['name'],'collection'=>$positive['collection_date'],'origin'=>$positive['origin'],'situation'=>$situation,'storage'=>$storage,'storage_name'=>$storageName,'perform'=>$perform?1:0,'test_name'=>$perform?'Bacteriológico':null,'notes'=>$notes?:null]);$id=(int)$pdo->lastInsertId();
            if($perform)$pdo->prepare("INSERT INTO bacteriology_positive_record_tests(positive_record_id,sequence,attempt_type,status) VALUES(:record,1,'initial','pending')")->execute(['record'=>$id]);
            $pdo->prepare("UPDATE bacteriology_positive_samples SET status='in_progress' WHERE id=:id AND status='pending'")->execute(['id'=>$positiveId]);
            Auth::registerAudit('TRANSFUSION_REACTION_RELATED_COMPONENT_ADDED','bacteriology_positive_sample_records',$id,null,['positive_sample_id'=>$positiveId,'sample_id'=>$positive['sample_id'],'donation_number'=>$positive['donation_number'],'blood_component_id'=>$component,'perform_bacteriology'=>$perform]);if($perform)Auth::registerAudit('TRANSFUSION_REACTION_RELATED_BACTERIOLOGY_STARTED','bacteriology_positive_record_tests',(int)$pdo->lastInsertId(),null,['positive_record_id'=>$id,'pool'=>false]);$pdo->commit();return$id;
        }catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    public static function updateRelatedComponent(int $recordId,array $data):int
    {
        $pdo=Database::connection();$pdo->beginTransaction();try{$record=self::lockedRtRecord($recordId);$situation=trim((string)($data['situation']??''));$storage=(int)($data['storage_unit_id']??0);$notes=trim((string)($data['operational_notes']??''));if(!in_array($situation,['in_stock','distributed','discarded'],true))throw new DomainException('Selecione uma situação válida.');if(mb_strlen($notes)>2000)throw new DomainException('A observação deve ter no máximo 2.000 caracteres.');$storageName=null;if($situation==='in_stock'){if(!$storage)throw new DomainException('Informe o local de armazenamento.');$q=$pdo->prepare("SELECT name FROM units WHERE id=:id AND client_id=:client AND status='active'");$q->execute(['id'=>$storage,'client'=>$record['client_id']]);$storageName=$q->fetchColumn();if(!$storageName)throw new DomainException('Local de armazenamento inválido.');}else $storage=null;$pdo->prepare('UPDATE bacteriology_positive_sample_records SET situation=:situation,storage_unit_id=:storage,storage_location=:name,operational_notes=:notes WHERE id=:id')->execute(['situation'=>$situation,'storage'=>$storage,'name'=>$storageName,'notes'=>$notes?:null,'id'=>$recordId]);Auth::registerAudit('TRANSFUSION_REACTION_RELATED_COMPONENT_UPDATED','bacteriology_positive_sample_records',$recordId,['situation'=>$record['situation'],'storage_unit_id'=>$record['storage_unit_id']],['situation'=>$situation,'storage_unit_id'=>$storage]);$pdo->commit();return(int)$record['positive_sample_id'];}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    public static function saveRelatedTest(int $testId,string $raw,?string $bacteria,?string $date,?string $notes):int
    {
        $result=self::normalizedResult($raw);$bacteria=trim((string)$bacteria)?:null;$notes=trim((string)$notes)?:null;if($result==='positive'&&!$bacteria)throw new DomainException('Informe a bactéria identificada.');$tested=self::testDate($date);$pdo=Database::connection();$pdo->beginTransaction();try{$q=$pdo->prepare("SELECT t.*,r.positive_sample_id FROM bacteriology_positive_record_tests t JOIN bacteriology_positive_sample_records r ON r.id=t.positive_record_id JOIN bacteriology_positive_samples ps ON ps.id=r.positive_sample_id JOIN samples s ON s.id=ps.sample_id WHERE t.id=:id AND s.purpose='transfusion_reaction' AND t.status='pending' FOR UPDATE");$q->execute(['id'=>$testId]);$test=$q->fetch(PDO::FETCH_ASSOC);if(!$test)throw new DomainException('Teste relacionado indisponível.');$pdo->prepare("UPDATE bacteriology_positive_record_tests SET status='completed',result=:result,identified_bacteria=:bacteria,tested_at=:tested,notes=:notes,performed_by=:user WHERE id=:id")->execute(['result'=>$result,'bacteria'=>$bacteria,'tested'=>$tested,'notes'=>$notes,'user'=>self::user(),'id'=>$testId]);$pdo->prepare('UPDATE bacteriology_positive_sample_records SET bacteriology_date=:date,bacteriology_result=:result,identified_bacteria_initial=:bacteria,identified_bacteria=:bacteria WHERE id=:id')->execute(['date'=>substr($tested,0,10),'result'=>$result,'bacteria'=>$bacteria,'id'=>$test['positive_record_id']]);if($result==='positive')$pdo->prepare("INSERT INTO bacteriology_positive_record_tests(positive_record_id,sequence,attempt_type,status) VALUES(:record,:sequence,'retest','pending')")->execute(['record'=>$test['positive_record_id'],'sequence'=>(int)$test['sequence']+1]);Auth::registerAudit('TRANSFUSION_REACTION_RELATED_BACTERIOLOGY_SAVED','bacteriology_positive_record_tests',$testId,null,['result'=>$result,'pool'=>false]);Auth::registerAudit('TRANSFUSION_REACTION_RELATED_BACTERIOLOGY_COMPLETED','bacteriology_positive_record_tests',$testId,null,['result'=>$result,'retest_required'=>$result==='positive']);$pdo->commit();return(int)$test['positive_sample_id'];}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    public static function saveRelatedRetest(int $testId,string $raw,?string $bacteria,?string $date,?string $notes):int
    {
        $result=self::normalizedResult($raw);$bacteria=trim((string)$bacteria)?:null;if($result==='positive'&&!$bacteria)throw new DomainException('Informe a bactéria identificada do reteste.');$tested=self::testDate($date);$pdo=Database::connection();$pdo->beginTransaction();try{$q=$pdo->prepare("SELECT t.*,r.positive_sample_id FROM bacteriology_positive_record_tests t JOIN bacteriology_positive_sample_records r ON r.id=t.positive_record_id JOIN bacteriology_positive_samples ps ON ps.id=r.positive_sample_id JOIN samples s ON s.id=ps.sample_id WHERE t.id=:id AND t.attempt_type='retest' AND t.status='pending' AND s.purpose='transfusion_reaction' FOR UPDATE");$q->execute(['id'=>$testId]);$test=$q->fetch(PDO::FETCH_ASSOC);if(!$test)throw new DomainException('Reteste relacionado indisponível.');$pdo->prepare("UPDATE bacteriology_positive_record_tests SET status='completed',result=:result,identified_bacteria=:bacteria,tested_at=:tested,notes=:notes,performed_by=:user WHERE id=:id")->execute(['result'=>$result,'bacteria'=>$bacteria,'tested'=>$tested,'notes'=>trim((string)$notes)?:null,'user'=>self::user(),'id'=>$testId]);$pdo->prepare('UPDATE bacteriology_positive_sample_records SET retest_result=:result,identified_bacteria_retest=:bacteria,identified_bacteria=:final WHERE id=:id')->execute(['result'=>$result,'bacteria'=>$bacteria,'final'=>$bacteria,'id'=>$test['positive_record_id']]);Auth::registerAudit('TRANSFUSION_REACTION_RELATED_BACTERIOLOGY_COMPLETED','bacteriology_positive_record_tests',$testId,null,['result'=>$result,'retest'=>true]);if($result==='positive')Auth::registerAudit('TRANSFUSION_REACTION_RELATED_POSITIVE_CONFIRMED','bacteriology_positive_sample_records',(int)$test['positive_record_id'],null,['test_id'=>$testId,'pool'=>false]);$pdo->commit();return(int)$test['positive_sample_id'];}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    private static function lockedRtPositive(int$id):array{$q=Database::connection()->prepare("SELECT ps.*,s.purpose FROM bacteriology_positive_samples ps JOIN samples s ON s.id=ps.sample_id WHERE ps.id=:id AND s.purpose='transfusion_reaction' FOR UPDATE");$q->execute(['id'=>$id]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)throw new DomainException('Investigação positiva de reação transfusional não encontrada.');return$row;}
    private static function lockedRtRecord(int$id):array{$q=Database::connection()->prepare("SELECT r.*,ps.client_id,ps.sample_id FROM bacteriology_positive_sample_records r JOIN bacteriology_positive_samples ps ON ps.id=r.positive_sample_id JOIN samples s ON s.id=ps.sample_id WHERE r.id=:id AND s.purpose='transfusion_reaction' FOR UPDATE");$q->execute(['id'=>$id]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)throw new DomainException('Componente relacionado não encontrado.');return$row;}
    private static function normalizedResult(string$result):string{$result=mb_strtolower(trim($result));if(!in_array($result,['negative','positive'],true))throw new DomainException('Selecione Negativo ou Positivo.');return$result;}
    private static function testDate(?string$date):string{$date=trim((string)$date);if($date==='')return date('Y-m-d H:i:s');$time=strtotime($date);if($time===false)throw new DomainException('Informe uma data de teste válida.');return date('Y-m-d H:i:s',$time);}
    private static function recordStatus(array$r):string{if(empty($r['perform_bacteriology']))return'no_test';$tests=$r['tests']??[];if(!$tests)return'waiting';$last=end($tests);if($last['status']==='pending')return$last['attempt_type']==='retest'?'retest':'waiting';if($last['attempt_type']==='retest')return$last['result']==='positive'?'positive_confirmed':'negative';return$last['result']==='negative'?'negative':'retest';}

    public static function save(int$id,array$data,bool$complete):void
    {
        $pdo=Database::connection();$pdo->beginTransaction();
        try{$current=self::find($id);if($current['status']==='completed')throw new DomainException('Esta ocorrência já foi concluída.');
            $records=self::normalizeRecords($data,$current);
            $conclusion=trim((string)($data['conclusion']??''));if(mb_strlen($conclusion)>5000)throw new DomainException('A conclusão deve ter no máximo 5.000 caracteres.');
            if($complete){if(!$records)throw new DomainException('Adicione pelo menos um registro válido.');if($conclusion==='')throw new DomainException('Preencha a conclusão.');self::validateForCompletion($records);}
            $status=$complete?'completed':($records||$conclusion!==''?'in_progress':'pending');
            $pdo->prepare("UPDATE bacteriology_positive_samples SET status=:status,conclusion=:conclusion,completed_by=:completed_by,completed_at=:completed_at WHERE id=:id")->execute(['status'=>$status,'conclusion'=>$conclusion?:null,'completed_by'=>$complete?self::user():null,'completed_at'=>$complete?date('Y-m-d H:i:s'):null,'id'=>$id]);
            $pdo->prepare('DELETE FROM bacteriology_positive_sample_records WHERE positive_sample_id=:id')->execute(['id'=>$id]);
            $ins=$pdo->prepare("INSERT INTO bacteriology_positive_sample_records(positive_sample_id,position,include_in_form,send_date,donation_number,blood_component_id,hemocomponent,collection_date,origin,situation,storage_unit_id,storage_location,reaction,test_name,bacteriology_date,bacteriology_result,retest_result,identified_bacteria_initial,identified_bacteria,identified_bacteria_retest) VALUES(:parent,:position,:include,:send_date,:donation,:component_id,:hemocomponent,:collection_date,:origin,:situation,:storage_id,:storage,:reaction,:test_name,:bacteriology_date,:bacteriology_result,:retest_result,:initial_bacteria,:legacy_bacteria,:retest_bacteria)");
            foreach($records as$i=>$r)$ins->execute(['parent'=>$id,'position'=>$i+1,'include'=>$r['include_in_form']?1:0,'send_date'=>$r['send_date'],'donation'=>$r['donation_number'],'component_id'=>$r['blood_component_id'],'hemocomponent'=>$r['hemocomponent'],'collection_date'=>$r['collection_date'],'origin'=>$r['origin'],'situation'=>$r['situation'],'storage_id'=>$r['storage_unit_id'],'storage'=>$r['storage_location'],'reaction'=>$r['reaction'],'test_name'=>$r['test_name'],'bacteriology_date'=>$r['bacteriology_date'],'bacteriology_result'=>$r['bacteriology_result'],'retest_result'=>$r['retest_result'],'initial_bacteria'=>$r['identified_bacteria_initial'],'legacy_bacteria'=>$r['identified_bacteria_retest']??$r['identified_bacteria_initial'],'retest_bacteria'=>$r['identified_bacteria_retest']]);
            Auth::registerAudit($complete?'bacteriology.positive_sample_completed':'bacteriology.positive_sample_saved','bacteriology_positive_samples',$id,['status'=>$current['status']],['status'=>$status,'record_count'=>count($records)]);$pdo->commit();
        }catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    private static function normalizeRecords(array$data,array$current):array
    {
        $keys=['send_date','donation_number','hemocomponent','collection_date','origin','situation','storage_location','reaction','test_name','bacteriology_date','bacteriology_result','retest_result','identified_bacteria_initial','identified_bacteria_retest'];$count=0;foreach($keys as$k)$count=max($count,count((array)($data[$k]??[])));$out=[];$pdo=Database::connection();
        for($i=0;$i<$count;$i++){$r=[];foreach($keys as$k){$v=trim((string)(($data[$k]??[])[$i]??''));$r[$k]=$v===''?null:$v;}$r['blood_component_id']=(int)(($data['blood_component_id']??[])[$i]??0)?:null;$r['storage_unit_id']=(int)(($data['storage_unit_id']??[])[$i]??0)?:null;if($r['blood_component_id']){$q=$pdo->prepare("SELECT name FROM blood_components WHERE id=:id AND status='active'");$q->execute(['id'=>$r['blood_component_id']]);$r['hemocomponent']=$q->fetchColumn()?:$r['hemocomponent'];}if($r['storage_unit_id']){$q=$pdo->prepare("SELECT name FROM units WHERE id=:id AND client_id=:client AND status='active'");$q->execute(['id'=>$r['storage_unit_id'],'client'=>(int)($current['client_id']??0)]);$r['storage_location']=$q->fetchColumn()?:null;if(!$r['storage_location'])throw new DomainException('Registro '.($i+1).': selecione uma unidade ativa vinculada ao cliente da ocorrência.');}$r['donation_number']=$r['donation_number']??$current['donation_number'];$r['send_date']=$r['send_date']??$current['send_date'];$r['collection_date']=$r['collection_date']??$current['collection_date'];$r['origin']=$r['origin']??$current['origin'];$r['include_in_form']=isset(($data['include_in_form']??[])[$i]);if($r['bacteriology_result']!=='positive'){$r['retest_result']=null;$r['identified_bacteria_initial']=null;$r['identified_bacteria_retest']=null;}elseif($r['retest_result']!=='positive'){$r['identified_bacteria_retest']=null;}$has=array_filter($r,fn($v)=>$v!==null&&$v!=='');if(!$has&&!$r['include_in_form'])continue;$out[]=$r;}
        return$out;
    }
    private static function validateForCompletion(array$records):void
    {
        foreach($records as$i=>$r){$number=$i+1;if($number===1){if(empty($r['identified_bacteria_initial']))throw new DomainException('Registro 1: informe a bactéria identificada.');continue;}
            foreach(['origin'=>'informe a origem.','situation'=>'informe a situação.','storage_location'=>'informe o local de armazenamento.','reaction'=>'informe a reação.']as$field=>$message)if(empty($r[$field]))throw new DomainException("Registro {$number}: {$message}");
            if(empty($r['bacteriology_result']))throw new DomainException("Registro {$number}: informe o resultado bacteriológico.");
            if($r['bacteriology_result']==='positive'&&empty($r['identified_bacteria_initial']))throw new DomainException("Registro {$number}: informe a bactéria identificada do resultado inicial.");
            if($r['bacteriology_result']==='positive'&&empty($r['retest_result']))throw new DomainException("Registro {$number}: informe o resultado do reteste.");
            if($r['bacteriology_result']==='positive'&&$r['retest_result']==='positive'&&empty($r['identified_bacteria_retest']))throw new DomainException("Registro {$number}: informe a bactéria identificada do reteste positivo.");
        }
    }
    private static function user():?int{return isset(Auth::user()['id'])?(int)Auth::user()['id']:null;}
}
