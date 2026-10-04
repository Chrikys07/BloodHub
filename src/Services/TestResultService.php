<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database};
use DomainException;
use PDO;

final class TestResultService
{
    public const CODES=['hematocrit'=>'HEMATOCRIT','hemoglobin'=>'HEMOGLOBIN','hemoglobin_per_unit'=>'HEMOGLOBIN_PER_UNIT','hemolysis'=>'HEMOLYSIS_DEGREE','free_hemoglobin'=>'FREE_HEMOGLOBIN','volume'=>'VOLUME','fibrinogen'=>'FIBRINOGEN','factor_viii'=>'FACTOR_VIII','platelet_count'=>'PLATELET_COUNT','platelets_per_unit'=>'PLATELETS_PER_UNIT','leukocyte_count'=>'LEUKOCYTE_COUNT','leukocytes_per_unit'=>'LEUKOCYTES_PER_UNIT','ph'=>'PH','swirling'=>'SWIRLING'];

    public static function saveNumeric(int $sampleId,string $testCode,mixed $value):array
    {
        if($value===''||$value===null)return [];
        if(is_string($value)){$value=trim($value);if(str_contains($value,','))$value=str_replace(',','.',str_replace('.','',$value));}
        if(!is_numeric($value))throw new DomainException('O resultado deve ser numérico.');
        $number=(float)$value;if(!is_finite($number))throw new DomainException('Resultado numérico inválido.');
        $pdo=Database::connection();$own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
        try{
            $test=self::configuredTest($sampleId,$testCode);
            if(!$test)throw new DomainException('O teste não está ativo e configurado para este hemocomponente.');
            if($test['result_type']!=='numeric')throw new DomainException('O teste configurado não aceita resultado numérico.');
            if($testCode===self::CODES['hemolysis'])throw new DomainException('Grau de Hemólise é calculado e não aceita digitação manual.');
            $st=$pdo->prepare('SELECT id,status FROM sample_tests WHERE sample_id=:sample AND test_id=:test ORDER BY id LIMIT 1 FOR UPDATE');$st->execute(['sample'=>$sampleId,'test'=>$test['id']]);$sampleTest=$st->fetch(PDO::FETCH_ASSOC);
            if(!$sampleTest){$pdo->prepare("INSERT INTO sample_tests(sample_id,test_id,status,started_at,executed_by) VALUES(:sample,:test,'in_progress',NOW(),:user)")->execute(['sample'=>$sampleId,'test'=>$test['id'],'user'=>Auth::user()['id']??null]);$sampleTest=['id'=>(int)$pdo->lastInsertId()];}
            $old=$pdo->prepare('SELECT * FROM test_results WHERE sample_test_id=:id ORDER BY id DESC LIMIT 1');$old->execute(['id'=>$sampleTest['id']]);$before=$old->fetch(PDO::FETCH_ASSOC);
            if($before){$pdo->prepare('UPDATE test_results SET result_value_numeric=:value,result_value_text=NULL,recorded_by=:user,recorded_at=NOW() WHERE id=:id')->execute(['value'=>$number,'user'=>Auth::user()['id']??null,'id'=>$before['id']]);$resultId=(int)$before['id'];}
            else{$pdo->prepare('INSERT INTO test_results(sample_test_id,result_value_numeric,recorded_by) VALUES(:sample_test,:value,:user)')->execute(['sample_test'=>$sampleTest['id'],'value'=>$number,'user'=>Auth::user()['id']??null]);$resultId=(int)$pdo->lastInsertId();}
            $pdo->prepare("UPDATE sample_tests SET status='completed',started_at=COALESCE(started_at,NOW()),completed_at=NOW(),executed_by=:user WHERE id=:id")->execute(['user'=>Auth::user()['id']??null,'id'=>$sampleTest['id']]);
            self::refreshSampleStatus($sampleId);
            Auth::registerAudit('quality_result.save','test_results',$resultId,$before?:null,['test_code'=>$testCode,'numeric_value'=>$number]);
            if(in_array($testCode,[self::CODES['hematocrit'],self::CODES['hemoglobin']],true))CalculatedTestService::recalculateHemolysis($sampleId);
            SpecificationEvaluator::persistForResult($resultId);EquipmentService::attach($resultId,filter_var($_POST['equipment_id'][$testCode]??null,FILTER_VALIDATE_INT)?:null);if($own)$pdo->commit();return ['id'=>$resultId,'value'=>$number];
        }catch(\Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function configuredTest(int $sampleId,string $code):?array
    {$s=Database::connection()->prepare("SELECT t.* FROM samples s JOIN test_blood_components tbc ON tbc.blood_component_id=s.blood_component_id JOIN tests t ON t.id=tbc.test_id WHERE s.id=:sample AND t.code=:code AND t.status='active' LIMIT 1");$s->execute(['sample'=>$sampleId,'code'=>$code]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}

    public static function saveText(int $sampleId,string $testCode,mixed $value):array
    {
        if($value===null||trim((string)$value)==='')return [];
        $text=trim((string)$value);$pdo=Database::connection();$own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
        try{$test=self::configuredTest($sampleId,$testCode);if(!$test)throw new DomainException('O teste não está ativo e configurado para este hemocomponente.');if(!in_array($test['result_type'],['text','select','boolean','positive_negative'],true))throw new DomainException('O teste configurado não aceita resultado textual.');
            $st=$pdo->prepare('SELECT id FROM sample_tests WHERE sample_id=:sample AND test_id=:test ORDER BY id LIMIT 1 FOR UPDATE');$st->execute(['sample'=>$sampleId,'test'=>$test['id']]);$sampleTestId=(int)$st->fetchColumn();if(!$sampleTestId){$pdo->prepare("INSERT INTO sample_tests(sample_id,test_id,status,started_at,executed_by) VALUES(:sample,:test,'in_progress',NOW(),:user)")->execute(['sample'=>$sampleId,'test'=>$test['id'],'user'=>Auth::user()['id']??null]);$sampleTestId=(int)$pdo->lastInsertId();}
            $old=$pdo->prepare('SELECT * FROM test_results WHERE sample_test_id=:id ORDER BY id DESC LIMIT 1');$old->execute(['id'=>$sampleTestId]);$before=$old->fetch(PDO::FETCH_ASSOC);if($before){$pdo->prepare('UPDATE test_results SET result_value_text=:value,result_value_numeric=NULL,recorded_by=:user,recorded_at=NOW() WHERE id=:id')->execute(['value'=>$text,'user'=>Auth::user()['id']??null,'id'=>$before['id']]);$resultId=(int)$before['id'];}else{$pdo->prepare('INSERT INTO test_results(sample_test_id,result_value_text,recorded_by) VALUES(:sample_test,:value,:user)')->execute(['sample_test'=>$sampleTestId,'value'=>$text,'user'=>Auth::user()['id']??null]);$resultId=(int)$pdo->lastInsertId();}
            $pdo->prepare("UPDATE sample_tests SET status='completed',started_at=COALESCE(started_at,NOW()),completed_at=NOW(),executed_by=:user WHERE id=:id")->execute(['user'=>Auth::user()['id']??null,'id'=>$sampleTestId]);self::refreshSampleStatus($sampleId);SpecificationEvaluator::persistForResult($resultId);EquipmentService::attach($resultId,filter_var($_POST['equipment_id'][$testCode]??null,FILTER_VALIDATE_INT)?:null);Auth::registerAudit('quality_result.save','test_results',$resultId,$before?:null,['test_code'=>$testCode,'text_value'=>$text]);if($own)$pdo->commit();return ['id'=>$resultId,'value'=>$text];
        }catch(\Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function missingRequired(int $sampleId):array
    {return array_column(self::pendingRequirements($sampleId),'name');}

    /** Recalcula e persiste o snapshot de Hb por unidade usando o volume sem arredondamento. */
    public static function recalculateHemoglobinPerUnit(int $sampleId):?float
    {
        if(!self::configuredTest($sampleId,self::CODES['hemoglobin_per_unit']))return null;
        $q=Database::connection()->prepare("SELECT wr.volume_ml,MAX(CASE WHEN t.code='HEMOGLOBIN' THEN tr.result_value_numeric END) hemoglobin FROM samples s LEFT JOIN sample_weight_results wr ON wr.sample_id=s.id LEFT JOIN sample_tests st ON st.sample_id=s.id LEFT JOIN tests t ON t.id=st.test_id LEFT JOIN test_results tr ON tr.sample_test_id=st.id WHERE s.id=:id GROUP BY s.id,wr.volume_ml");
        $q->execute(['id'=>$sampleId]);$values=$q->fetch(PDO::FETCH_ASSOC);
        if(!$values||$values['volume_ml']===null||$values['hemoglobin']===null){RedCellResidualLeukocyteService::recalculate($sampleId);return null;}
        $result=((float)$values['hemoglobin']/100)*(float)$values['volume_ml'];
        self::saveNumeric($sampleId,self::CODES['hemoglobin_per_unit'],$result);
        RedCellResidualLeukocyteService::recalculate($sampleId);
        return $result;
    }

    public static function pendingRequirements(int $sampleId):array
    {
        $pdo=Database::connection();
        $s=$pdo->prepare("SELECT t.code,t.name FROM samples s JOIN test_blood_components tbc ON tbc.blood_component_id=s.blood_component_id AND tbc.is_required=1 JOIN tests t ON t.id=tbc.test_id AND t.status='active' LEFT JOIN sample_tests st ON st.sample_id=s.id AND st.test_id=t.id AND st.status='completed' LEFT JOIN test_results tr ON tr.sample_test_id=st.id WHERE s.id=:id AND t.code<>'BACTERIOLOGY' AND (tr.id IS NULL OR (tr.result_value_numeric IS NULL AND NULLIF(TRIM(tr.result_value_text),'') IS NULL)) ORDER BY t.name");
        $s->execute(['id'=>$sampleId]);$missing=$s->fetchAll(PDO::FETCH_ASSOC);
        $identity=$pdo->prepare('SELECT bc.code,NULLIF(TRIM(s.lcqh_code),\'\') lcqh_code FROM samples s JOIN blood_components bc ON bc.id=s.blood_component_id WHERE s.id=:id');
        $identity->execute(['id'=>$sampleId]);$sample=$identity->fetch(PDO::FETCH_ASSOC);
        if($sample&&in_array($sample['code'],['ST','STR','CRIO'],true)&&$sample['lcqh_code']===null)array_unshift($missing,['code'=>'LCQH_CODE','name'=>'Código LCQH']);
        if($sample&&$sample['code']==='CRIO'){$crio=$pdo->prepare('SELECT gross_weight,volume_ml,dilution,fibrinogen_mg_dl,fibrinogen_mg_u FROM cryoprecipitate_results WHERE sample_id=:id');$crio->execute(['id'=>$sampleId]);$crio=$crio->fetch(PDO::FETCH_ASSOC)?:[];if(($crio['gross_weight']??null)===null)$missing[]=['code'=>'GROSS_WEIGHT','name'=>'Peso bruto'];if(($crio['volume_ml']??null)===null&&!in_array(self::CODES['volume'],array_column($missing,'code'),true))$missing[]=['code'=>self::CODES['volume'],'name'=>'Volume'];if(($crio['dilution']??null)===null)$missing[]=['code'=>'CRIO_DILUTION','name'=>'Diluição'];if(($crio['fibrinogen_mg_dl']??null)===null)$missing[]=['code'=>'CRIO_FIBRINOGEN_MG_DL','name'=>'Fibrinogênio (mg/dL)'];if(($crio['fibrinogen_mg_u']??null)===null&&!in_array(self::CODES['fibrinogen'],array_column($missing,'code'),true))$missing[]=['code'=>self::CODES['fibrinogen'],'name'=>'Fibrinogênio (mg/U)'];}
        $equipment=$pdo->prepare("SELECT t.code,t.name FROM sample_tests st JOIN tests t ON t.id=st.test_id AND t.equipment_required=1 JOIN test_results tr ON tr.id=(SELECT MAX(x.id) FROM test_results x WHERE x.sample_test_id=st.id) LEFT JOIN test_result_equipment re ON re.test_result_id=tr.id WHERE st.sample_id=:id AND re.id IS NULL");$equipment->execute(['id'=>$sampleId]);foreach($equipment->fetchAll(PDO::FETCH_ASSOC)as$e)$missing[]=['code'=>'EQUIPMENT_'.$e['code'],'name'=>'Equipamento utilizado em '.$e['name']];
        return array_merge($missing,WashedRedCellResultService::completionRequirements($sampleId));
    }

    public static function complete(int $sampleId):array
    {$pdo=Database::connection();$own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();try{SampleTestSynchronizer::syncSample($sampleId);self::recalculateHemoglobinPerUnit($sampleId);PlateletResultService::recalculate($sampleId);HemolysisResultService::synchronizeStage($sampleId);CalculatedTestService::recalculateHemolysis($sampleId);SpecificationEvaluator::evaluateSampleResults($sampleId);$outside=SpecificationEvaluator::nonconforming($sampleId);if($outside&&empty($_POST['specification_acknowledged'])){if($own)$pdo->commit();return [['code'=>'SPECIFICATION_ACK','name'=>'ciência dos resultados fora da especificação']];}if($outside){SpecificationEvaluator::acknowledge($sampleId,(int)(Auth::user()['id']??0));foreach($outside as$item)Auth::registerAudit('QC_NONCONFORMITY_DETECTED','test_results',(int)$item['test_result_id'],null,['sample_id'=>$sampleId,'test_id'=>(int)$item['test_id'],'specification_id'=>$item['specification_id']??null,'result_value'=>$item['result_value']]);}$missing=self::pendingRequirements($sampleId);if($missing){if($own)$pdo->commit();return $missing;}CpafYieldClassificationService::recalculate($sampleId,true);$pdo->prepare("UPDATE samples SET status='completed' WHERE id=:id AND status IN ('received','in_analysis','partial_results')")->execute(['id'=>$sampleId]);QcNotificationService::createForSample($sampleId);Auth::registerAudit('QC_RESULT_COMPLETED','samples',$sampleId,null,['status'=>'completed']);if($own){$pdo->commit();QcNotificationService::deliverPendingForSample($sampleId);}return [];}catch(\Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $e;}}

    public static function refreshSampleStatus(int $sampleId):void
    {$missing=self::missingRequired($sampleId);$count=Database::connection()->prepare('SELECT COUNT(*) FROM sample_tests st JOIN test_results tr ON tr.sample_test_id=st.id WHERE st.sample_id=:id');$count->execute(['id'=>$sampleId]);$status=((int)$count->fetchColumn()>0)?'partial_results':'in_analysis';if(!$missing)$status='partial_results';Database::connection()->prepare("UPDATE samples SET status=:status WHERE id=:id AND status<>'completed'")->execute(['status'=>$status,'id'=>$sampleId]);}
}
