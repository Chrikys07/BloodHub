<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database};
use DomainException;
use PDO;

final class AdministrativeCorrectionService
{
    private const CALCULATED=['VOLUME','HEMOGLOBIN_PER_UNIT','HEMOLYSIS_DEGREE','FREE_HEMOGLOBIN','PLATELETS_PER_UNIT','LEUKOCYTES_PER_UNIT','PLATELETS_PER_ML','LEUKOCYTES_PER_ML','REDBLOODCELLS_PER_ML','RECOVERY','RESIDUAL_PROTEIN','FIBRINOGEN'];

    public static function correctIdentification(int $sampleId,array $input,string $reason):void
    {
        self::reason($reason);$pdo=Database::connection();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT * FROM samples WHERE id=:id FOR UPDATE');$q->execute(['id'=>$sampleId]);$sample=$q->fetch(PDO::FETCH_ASSOC);if(!$sample)throw new DomainException('Amostra não encontrada.');
            $next=['donation_number'=>trim((string)($input['donation_number']??$sample['donation_number'])),'blood_component_id'=>(int)($input['blood_component_id']??$sample['blood_component_id']),'origin_unit_id'=>(int)($input['origin_unit_id']??$sample['origin_unit_id']),'lcqh_code'=>trim((string)($input['lcqh_code']??$sample['lcqh_code'])),'patient_name'=>trim((string)($input['patient_name']??$sample['patient_name']))];
            if($sample['purpose']==='transfusion_reaction'&&($next['patient_name']===''||mb_strlen($next['patient_name'])>255))throw new DomainException('Informe o nome do paciente.');
            if($next['donation_number']===''||$next['blood_component_id']<1)throw new DomainException('Número da doação e hemocomponente são obrigatórios.');
            SampleUniquenessService::assertRowsAvailable([['id'=>$sampleId,'donation_number'=>$next['donation_number'],'blood_component_id'=>$next['blood_component_id']]],true);
            if($next['blood_component_id']!==(int)$sample['blood_component_id']&&self::hasResults($sampleId))throw new DomainException('Esta amostra possui resultados registrados. A alteração do hemocomponente pode invalidar os dados laboratoriais existentes. Cancele a amostra e execute a correção apropriada.');
            $changed=[];foreach($next as$field=>$value){$old=$sample[$field]??null;if((string)$old===(string)$value)continue;$changed[$field]=[$old,$value];}
            if(!$changed)throw new DomainException('Informe ao menos uma alteração.');
            $pdo->prepare('UPDATE samples SET donation_number=:donation,blood_component_id=:component,origin_unit_id=:origin,lcqh_code=:lcqh,patient_name=:patient WHERE id=:id')->execute(['donation'=>$next['donation_number'],'component'=>$next['blood_component_id'],'origin'=>$next['origin_unit_id']?:null,'lcqh'=>$next['lcqh_code']?:null,'patient'=>$next['patient_name']?:null,'id'=>$sampleId]);
            foreach($changed as$field=>[$old,$new])self::record('SAMPLE_IDENTIFICATION','samples',$sampleId,$sampleId,$field,$old,$new,$reason,['dependencies'=>self::dependencies($sampleId)]);
            Auth::registerAudit('SAMPLE_ADMIN_CORRECTED','samples',$sampleId,array_intersect_key($sample,$changed),array_map(fn($v)=>$v[1],$changed));$pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function cancelSample(int $sampleId,string $reason):void
    {
        self::reason($reason);$pdo=Database::connection();$pdo->beginTransaction();
        try{$q=$pdo->prepare('SELECT id,status FROM samples WHERE id=:id FOR UPDATE');$q->execute(['id'=>$sampleId]);$s=$q->fetch(PDO::FETCH_ASSOC);if(!$s)throw new DomainException('Amostra não encontrada.');if($s['status']==='cancelled')throw new DomainException('Amostra já cancelada.');
            $metadata=['dependencies'=>self::dependencies($sampleId),'results_preserved'=>self::hasResults($sampleId)];
            $pdo->prepare("UPDATE samples SET status='cancelled',cancelled_by=:user,cancelled_at=NOW(),cancellation_reason=:reason WHERE id=:id")->execute(['user'=>Auth::user()['id']??null,'reason'=>$reason,'id'=>$sampleId]);
            self::record('SAMPLE_STATUS','samples',$sampleId,$sampleId,'status',$s['status'],'cancelled',$reason,$metadata);self::notificationEvents($sampleId,'SAMPLE_ADMIN_CANCELLED','Amostra cancelada administrativamente.',$metadata);
            Auth::registerAudit('SAMPLE_ADMIN_CANCELLED','samples',$sampleId,['status'=>$s['status']],['status'=>'cancelled','reason'=>$reason]);$pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function correctResult(int $sampleId,int $resultId,mixed $value,string $reason):void
    {
        self::reason($reason);$pdo=Database::connection();$pdo->beginTransaction();
        try{$q=$pdo->prepare('SELECT tr.*,st.sample_id,st.test_id,t.code,t.name,t.unit,t.result_type,s.purpose FROM test_results tr JOIN sample_tests st ON st.id=tr.sample_test_id JOIN tests t ON t.id=st.test_id JOIN samples s ON s.id=st.sample_id WHERE tr.id=:result AND st.sample_id=:sample FOR UPDATE');$q->execute(['result'=>$resultId,'sample'=>$sampleId]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new DomainException('Resultado não encontrado.');if(in_array(strtoupper((string)$r['code']),self::CALCULATED,true))throw new DomainException('Resultado calculado. Corrija os dados de origem para recalcular este valor.');
            $before=self::resultSnapshot($sampleId);$old=$r['result_value_numeric']!==null?$r['result_value_numeric']:$r['result_value_text'];
            if($r['result_type']==='numeric')TestResultService::saveNumeric($sampleId,(string)$r['code'],$value);else TestResultService::saveText($sampleId,(string)$r['code'],$value);
            self::recalculate($sampleId,(string)$r['code']);$after=self::resultSnapshot($sampleId);$new=$after[$resultId]['value']??$value;
            $parent=self::record('LAB_RESULT','test_results',$resultId,$sampleId,(string)$r['code'],$old,$new,$reason,['test_name'=>$r['name'],'context'=>$r['purpose']]);
            $recalculated=[];foreach($after as$id=>$row){if($id===$resultId||!isset($before[$id])||(string)$before[$id]['value']===(string)$row['value'])continue;$derivedTransition=self::reevaluateHistoricalSpecification((int)$id);$recalculated[]=['result_id'=>$id,'test'=>$row['name'],'old'=>$before[$id]['value'],'new'=>$row['value'],'conformity'=>$derivedTransition];self::record('LAB_INPUT','test_results',(int)$id,$sampleId,$row['code'],$before[$id]['value'],$row['value'],$reason,['automatic_recalculation'=>true,'conformity_transition'=>$derivedTransition],$parent);self::notificationCorrection($sampleId,(int)$id,(string)$r['purpose'],$before[$id]['value'],$row['value'],$derivedTransition,['source_result_id'=>$resultId]);}
            $transition=self::reevaluateHistoricalSpecification($resultId);$metadata=['recalculated'=>$recalculated,'conformity_transition'=>$transition];$pdo->prepare('UPDATE administrative_corrections SET metadata_json=:metadata WHERE id=:id')->execute(['metadata'=>json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'id'=>$parent]);self::notificationCorrection($sampleId,$resultId,(string)$r['purpose'],$old,$new,$transition,$metadata);
            Auth::registerAudit('RESULT_ADMIN_CORRECTED','test_results',$resultId,['value'=>$old],['value'=>$new,'reason'=>$reason,'recalculated'=>$recalculated]);$pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function correctBacteriology(int $sampleId,int $resultId,string $value,string $reason):void
    {
        self::reason($reason);if(!in_array($value,['negative','positive'],true))throw new DomainException('Resultado bacteriológico inválido.');$pdo=Database::connection();$pdo->beginTransaction();
        try{$q=$pdo->prepare('SELECT * FROM bacteriology_results WHERE id=:id AND sample_id=:sample FOR UPDATE');$q->execute(['id'=>$resultId,'sample'=>$sampleId]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new DomainException('Resultado bacteriológico não encontrado.');if($r['result']===$value)throw new DomainException('O novo resultado é igual ao atual.');
            $deps=self::dependencies($sampleId);$pdo->prepare("UPDATE bacteriology_results SET result=:result,bacteriological_conformity=:conformity,updated_at=NOW() WHERE id=:id")->execute(['result'=>$value,'conformity'=>$value==='negative'?'conforming':'nonconforming','id'=>$resultId]);
            self::record('BACTERIOLOGY_RESULT','bacteriology_results',$resultId,$sampleId,'result',$r['result'],$value,$reason,['workflow_preserved'=>true,'dependencies'=>$deps]);
            Auth::registerAudit('BACTERIOLOGY_ADMIN_CORRECTED','bacteriology_results',$resultId,['result'=>$r['result']],['result'=>$value,'reason'=>$reason]);$pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function correctParameter(int $sampleId,int $parameterId,mixed $value,string $reason):void
    {
        self::reason($reason);$pdo=Database::connection();$pdo->beginTransaction();
        try{$q=$pdo->prepare('SELECT p.*,tr.id result_id FROM test_result_parameters p JOIN test_results tr ON tr.id=p.test_result_id JOIN sample_tests st ON st.id=tr.sample_test_id WHERE p.id=:id AND st.sample_id=:sample FOR UPDATE');$q->execute(['id'=>$parameterId,'sample'=>$sampleId]);$p=$q->fetch(PDO::FETCH_ASSOC);if(!$p||!str_starts_with((string)$p['parameter_code'],'abs_'))throw new DomainException('Dado primário não editável por este fluxo.');$number=self::number($value);$before=self::resultSnapshot($sampleId);$old=$p['numeric_value'];$pdo->prepare('UPDATE test_result_parameters SET numeric_value=:value,updated_at=NOW() WHERE id=:id')->execute(['value'=>$number,'id'=>$parameterId]);
            $state=HemolysisResultService::state($sampleId);$values=$state['parameters'];$values[$p['parameter_code']]=$number;$calc=HemolysisCalculator::calculate($values);$pdo->prepare('UPDATE test_results SET result_value_numeric=:value,recorded_by=:user,recorded_at=NOW() WHERE id=:id')->execute(['value'=>$calc['free_hemoglobin_g_dl'],'user'=>Auth::user()['id']??null,'id'=>$p['result_id']]);CalculatedTestService::recalculateHemolysis($sampleId);$after=self::resultSnapshot($sampleId);
            $parent=self::record('LAB_INPUT','test_result_parameters',$parameterId,$sampleId,(string)$p['parameter_code'],$old,$number,$reason,['warning'=>'Absorbância retificada']);foreach($after as$id=>$row)if(isset($before[$id])&&(string)$before[$id]['value']!==(string)$row['value'])self::record('LAB_INPUT','test_results',(int)$id,$sampleId,$row['code'],$before[$id]['value'],$row['value'],$reason,['automatic_recalculation'=>true],$parent);
            Auth::registerAudit('LAB_INPUT_ADMIN_CORRECTED','test_result_parameters',$parameterId,['value'=>$old],['value'=>$number,'reason'=>$reason]);$pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private static function recalculate(int $sampleId,string $code):void
    {if(in_array($code,['HEMOGLOBIN','HEMATOCRIT'],true)){TestResultService::recalculateHemoglobinPerUnit($sampleId);CalculatedTestService::recalculateHemolysis($sampleId);}if(in_array($code,['PLATELET_COUNT','LEUKOCYTE_COUNT','REDBLOODCELL_COUNT'],true))PlateletResultService::recalculate($sampleId);}
    private static function reevaluateHistoricalSpecification(int $resultId):array
    {$pdo=Database::connection();$q=$pdo->prepare('SELECT e.*,tr.result_value_numeric,tr.result_value_text FROM test_result_spec_evaluations e JOIN test_results tr ON tr.id=e.test_result_id WHERE e.test_result_id=:id FOR UPDATE');$q->execute(['id'=>$resultId]);$e=$q->fetch(PDO::FETCH_ASSOC);if(!$e)return ['before'=>'NOT_EVALUATED','after'=>'NOT_EVALUATED'];$actual=$e['result_value_numeric']!==null?$e['result_value_numeric']:$e['result_value_text'];$spec=['rule_type'=>$e['rule_type'],'min_value'=>$e['expected_min'],'max_value'=>$e['expected_max'],'expected_text'=>$e['expected_text']];$status=$e['rule_type']?(SpecificationEvaluator::evaluateRule($spec,$actual)?'CONFORMING':'NONCONFORMING'):'NO_SPECIFICATION';$pdo->prepare('UPDATE test_result_spec_evaluations SET actual_value=:actual,conformity_status=:status,evaluated_at=NOW() WHERE test_result_id=:id')->execute(['actual'=>(string)$actual,'status'=>$status,'id'=>$resultId]);return ['before'=>$e['conformity_status'],'after'=>$status,'specification_id'=>$e['specification_id'],'snapshot'=>$e['specification_snapshot_text']];}
    private static function notificationCorrection(int $sampleId,int $resultId,string $purpose,mixed $old,mixed $new,array $transition,array $metadata):void
    {$pdo=Database::connection();$q=$pdo->prepare('SELECT id FROM qc_notifications WHERE result_id=:id');$q->execute(['id'=>$resultId]);$notification=(int)$q->fetchColumn();if($notification){$pdo->prepare('UPDATE qc_notifications SET previous_result_snapshot=COALESCE(previous_result_snapshot,result_value_snapshot),corrected_result_snapshot=:new WHERE id=:id')->execute(['new'=>(string)$new,'id'=>$notification]);self::notificationEvent($notification,'RESULT_ADMIN_CORRECTED','Resultado retificado administrativamente.',['old'=>$old,'new'=>$new]+$metadata);}elseif($purpose==='quality_control'&&($transition['after']??'')==='NONCONFORMING'){QcNotificationService::createForResult($resultId);}}
    private static function notificationEvents(int $sampleId,string $type,string $description,array $metadata):void{$q=Database::connection()->prepare('SELECT id FROM qc_notifications WHERE sample_id=:id');$q->execute(['id'=>$sampleId]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as$id)self::notificationEvent((int)$id,$type,$description,$metadata);}
    private static function notificationEvent(int $id,string $type,string $description,array $metadata):void{Database::connection()->prepare('INSERT INTO qc_notification_events(notification_id,event_type,description,user_id,metadata) VALUES(:id,:type,:description,:user,:metadata)')->execute(['id'=>$id,'type'=>$type,'description'=>$description,'user'=>Auth::user()['id']??null,'metadata'=>json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);}
    private static function resultSnapshot(int $sampleId):array{$q=Database::connection()->prepare('SELECT tr.id,t.code,t.name,COALESCE(CAST(tr.result_value_numeric AS CHAR),tr.result_value_text) value FROM test_results tr JOIN sample_tests st ON st.id=tr.sample_test_id JOIN tests t ON t.id=st.test_id WHERE st.sample_id=:id');$q->execute(['id'=>$sampleId]);$out=[];foreach($q->fetchAll(PDO::FETCH_ASSOC)as$r)$out[(int)$r['id']]=$r;return$out;}
    public static function dependencies(int $sampleId):array{$pdo=Database::connection();$queries=['results'=>'SELECT COUNT(*) FROM test_results tr JOIN sample_tests st ON st.id=tr.sample_test_id WHERE st.sample_id=?','bacteriology'=>'SELECT COUNT(*) FROM bacteriology_results WHERE sample_id=?','bacteriology_pools'=>'SELECT COUNT(*) FROM bacteriology_pool_members WHERE sample_id=?','factor_viii_pools'=>'SELECT COUNT(*) FROM factor_viii_pool_samples WHERE sample_id=?','notifications'=>'SELECT COUNT(*) FROM qc_notifications WHERE sample_id=?'];$out=[];foreach($queries as$key=>$sql){try{$q=$pdo->prepare($sql);$q->execute([$sampleId]);$out[$key]=(int)$q->fetchColumn();}catch(\Throwable){$out[$key]=0;}}return$out;}
    private static function hasResults(int $sampleId):bool{return (self::dependencies($sampleId)['results']??0)>0||(self::dependencies($sampleId)['bacteriology']??0)>0;}
    private static function record(string $type,string $entity,int $entityId,int $sampleId,string $field,mixed $old,mixed $new,string $reason,array $metadata=[],?int $parent=null):int{$pdo=Database::connection();$q=$pdo->prepare('INSERT INTO administrative_corrections(correction_type,entity_type,entity_id,sample_id,parent_correction_id,field_name,old_value,new_value,old_formatted_value,new_formatted_value,reason,metadata_json,corrected_by) VALUES(:type,:entity,:entity_id,:sample,:parent,:field,:old,:new,:oldf,:newf,:reason,:metadata,:user)');$q->execute(['type'=>$type,'entity'=>$entity,'entity_id'=>$entityId,'sample'=>$sampleId,'parent'=>$parent,'field'=>$field,'old'=>$old===null?null:(string)$old,'new'=>$new===null?null:(string)$new,'oldf'=>$old===null?null:(string)$old,'newf'=>$new===null?null:(string)$new,'reason'=>$reason,'metadata'=>$metadata?json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,'user'=>Auth::user()['id']??null]);return(int)$pdo->lastInsertId();}
    private static function reason(string $reason):void{if(mb_strlen(trim($reason))<3)throw new DomainException('Informe o motivo da correção.');}
    private static function number(mixed $value):float{$v=str_replace(',','.',trim((string)$value));if(!is_numeric($v))throw new DomainException('Informe um valor numérico válido.');return(float)$v;}
}
