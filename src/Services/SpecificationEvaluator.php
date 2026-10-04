<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\Database;
use PDO;

final class SpecificationEvaluator
{
    public const STATUSES=['CONFORMING','NONCONFORMING','NO_SPECIFICATION','NOT_EVALUATED'];

    public static function evaluate(int $sampleId,int $testId,mixed $result,?string $date=null):array
    {
        $pdo=Database::connection();$date=$date?:date('Y-m-d');
        $q=$pdo->prepare('SELECT s.blood_component_id,s.preservative_id,t.name test_name,t.code test_code,t.unit result_unit FROM samples s JOIN tests t ON t.id=:test WHERE s.id=:id');$q->execute(['id'=>$sampleId,'test'=>$testId]);$sample=$q->fetch(PDO::FETCH_ASSOC);
        if(!$sample)return ['status'=>'NOT_EVALUATED','test_id'=>$testId,'result_value'=>(string)$result,'conforms'=>false,'evaluated'=>false,'reason'=>'Amostra ou teste não encontrado.'];
        $q=$pdo->prepare("SELECT sp.* FROM blood_component_test_specifications sp WHERE sp.blood_component_id=:component AND sp.test_id=:test AND sp.active=1 AND (sp.effective_from IS NULL OR sp.effective_from<=:d1) AND (sp.effective_to IS NULL OR sp.effective_to>=:d2) ORDER BY (sp.condition_type IS NOT NULL) DESC,sp.effective_from DESC,sp.version_number DESC,sp.id DESC");
        $q->execute(['component'=>$sample['blood_component_id'],'test'=>$testId,'d1'=>$date,'d2'=>$date]);$unresolved=false;$chosen=null;
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $spec){$condition=$spec['condition_type'];if(!$condition){$chosen??=$spec;continue;}if($condition==='PRESERVATIVE'){$expected=(int)($spec['preservative_id']?:$spec['condition_value']);if($expected&&$expected===(int)$sample['preservative_id']){$chosen=$spec;break;}continue;}if($condition==='STORAGE_DAY'&&$spec['condition_value']==='LAST_DAY'){$chosen=$spec;break;}$unresolved=true;}
        if(!$chosen)return ['status'=>$unresolved?'NOT_EVALUATED':'NO_SPECIFICATION','test_id'=>$testId,'test_name'=>$sample['test_name'],'result_value'=>(string)$result,'result_display'=>MeasurementFormatter::formatResult((string)$sample['test_code'],$result,$sample['result_unit']),'result_unit'=>$sample['result_unit'],'specification_id'=>null,'conforms'=>false,'evaluated'=>!$unresolved,'reason'=>$unresolved?'Avaliação condicionada: condição ainda não resolvida automaticamente.':'Nenhuma especificação vigente.'];
        $status=self::evaluateRule($chosen,$result)?'CONFORMING':'NONCONFORMING';
        $snapshot=self::snapshotText($chosen);return ['status'=>$status,'test_id'=>$testId,'test_name'=>$sample['test_name'],'result_value'=>(string)$result,'result_display'=>MeasurementFormatter::formatResult((string)$sample['test_code'],$result,$sample['result_unit']),'result_unit'=>$sample['result_unit'],'specification_id'=>(int)$chosen['id'],'specification_version'=>(int)($chosen['version_number']??1),'rule'=>$chosen['rule_type'],'min_value'=>$chosen['min_value'],'max_value'=>$chosen['max_value'],'expected_text'=>$chosen['expected_text'],'reference_display'=>MeasurementFormatter::formatSpecification((string)$sample['test_code'],['rule_type'=>$chosen['rule_type'],'expected_min'=>$chosen['min_value'],'expected_max'=>$chosen['max_value'],'expected_text'=>$chosen['expected_text'],'unit'=>$chosen['unit'],'specification_snapshot_text'=>$snapshot]),'conforms'=>$status==='CONFORMING','evaluated'=>true,'reason'=>$status==='CONFORMING'?'Resultado conforme.':'Resultado fora da especificação.','specification'=>$chosen,'actual_value'=>(string)$result,'snapshot'=>$snapshot];
    }

    private static function evaluateWithSpecification(array $specification,mixed $result):array
    {
        return ['status'=>self::evaluateRule($specification,$result)?'CONFORMING':'NONCONFORMING','specification'=>$specification,'actual_value'=>(string)$result,'snapshot'=>self::snapshotText($specification)];
    }

    public static function evaluateRule(array $s,mixed $actual):bool
    {
        $type=$s['rule_type'];if(in_array($type,['EQUAL_TEXT','BOOLEAN'],true)){$a=self::normalize((string)$actual);$e=self::normalize((string)$s['expected_text']);return $type==='BOOLEAN'?self::boolean($a)===self::boolean($e):$a===$e;}
        if(is_string($actual)){$actual=trim($actual);if(str_contains($actual,','))$actual=str_replace(',','.',str_replace('.','',$actual));}
        if(!is_numeric($actual))return false;$v=(float)$actual;$min=$s['min_value']!==null?(float)$s['min_value']:null;$max=$s['max_value']!==null?(float)$s['max_value']:null;
        return match($type){'GT'=>$v>$min,'GTE'=>$v>=$min,'LT'=>$v<$max,'LTE'=>$v<=$max,'BETWEEN'=>$v>=$min&&$v<=$max,'EQUAL_NUMERIC'=>abs($v-(float)$min)<1e-10,default=>false};
    }

    public static function persistForResult(int $resultId,?string $date=null):array
    {
        $pdo=Database::connection();$q=$pdo->prepare('SELECT tr.*,st.sample_id,st.test_id,s.status sample_status FROM test_results tr JOIN sample_tests st ON st.id=tr.sample_test_id JOIN samples s ON s.id=st.sample_id WHERE tr.id=:id');$q->execute(['id'=>$resultId]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return ['status'=>'NOT_EVALUATED'];
        $old=$pdo->prepare('SELECT * FROM test_result_spec_evaluations WHERE test_result_id=:id');$old->execute(['id'=>$resultId]);$existing=$old->fetch(PDO::FETCH_ASSOC);if($existing&&$r['sample_status']==='completed')return ['status'=>$existing['conformity_status']]+$existing;
        $actual=$r['result_value_numeric']!==null?$r['result_value_numeric']:$r['result_value_text'];
        // A regulatory snapshot is immutable only after completion. While the
        // analysis is open, resolve specification, version and context on every save.
        $evaluationDate=$date?:substr((string)$r['recorded_at'],0,10);
        $e=self::evaluate((int)$r['sample_id'],(int)$r['test_id'],$actual,$evaluationDate);
        $s=$e['specification']??[];
        $pdo->prepare('INSERT INTO test_result_spec_evaluations(test_result_id,specification_id,rule_type,expected_min,expected_max,expected_text,unit,condition_type,condition_value,preservative_id,actual_value,conformity_status,evaluated_at,specification_snapshot_text) VALUES(:result,:spec,:rule,:min,:max,:text,:unit,:condition,:condition_value,:preservative,:actual,:status,NOW(),:snapshot) ON DUPLICATE KEY UPDATE specification_id=VALUES(specification_id),rule_type=VALUES(rule_type),expected_min=VALUES(expected_min),expected_max=VALUES(expected_max),expected_text=VALUES(expected_text),unit=VALUES(unit),condition_type=VALUES(condition_type),condition_value=VALUES(condition_value),preservative_id=VALUES(preservative_id),actual_value=VALUES(actual_value),conformity_status=VALUES(conformity_status),evaluated_at=NOW(),specification_snapshot_text=VALUES(specification_snapshot_text),acknowledged_by=NULL,acknowledged_at=NULL')->execute(['result'=>$resultId,'spec'=>$s['id']??null,'rule'=>$s['rule_type']??null,'min'=>$s['min_value']??null,'max'=>$s['max_value']??null,'text'=>$s['expected_text']??null,'unit'=>$s['unit']??null,'condition'=>$s['condition_type']??null,'condition_value'=>$s['condition_value']??null,'preservative'=>$s['preservative_id']??null,'actual'=>(string)$actual,'status'=>$e['status'],'snapshot'=>$e['snapshot']??($e['reason']??null)]);return $e;
    }

    public static function evaluateSampleResults(int $sampleId):array
    {
        WashedRedCellResultService::recalculate($sampleId);
        $q=Database::connection()->prepare("SELECT tr.id FROM test_results tr JOIN sample_tests st ON st.id=tr.sample_test_id JOIN tests t ON t.id=st.test_id WHERE st.sample_id=:id AND t.code<>'BACTERIOLOGY' AND (tr.result_value_numeric IS NOT NULL OR NULLIF(TRIM(tr.result_value_text),'') IS NOT NULL) ORDER BY st.test_id,tr.id");
        $q->execute(['id'=>$sampleId]);
        $evaluations=[];$divergences=[];
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id){
            $evaluation=self::persistForResult((int)$id);
            $evaluations[]=$evaluation;
            if(($evaluation['status']??null)==='NONCONFORMING')$divergences[]=$evaluation;
        }
        return ['evaluations'=>$evaluations,'divergences'=>$divergences];
    }
    public static function nonconforming(int $sampleId):array
    {$q=Database::connection()->prepare("SELECT e.*,e.actual_value result_value,t.unit result_unit,e.rule_type rule,e.expected_min min_value,e.expected_max max_value,t.id test_id,t.code test_code,t.name test_name,sp.version_number specification_version FROM test_result_spec_evaluations e JOIN test_results tr ON tr.id=e.test_result_id JOIN sample_tests st ON st.id=tr.sample_test_id JOIN tests t ON t.id=st.test_id LEFT JOIN blood_component_test_specifications sp ON sp.id=e.specification_id WHERE st.sample_id=:id AND t.code<>'BACTERIOLOGY' AND e.conformity_status='NONCONFORMING' ORDER BY t.name");$q->execute(['id'=>$sampleId]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);foreach($rows as &$row){$row['result_display']=MeasurementFormatter::formatResult((string)$row['test_code'],$row['result_value'],$row['result_unit']);$row['reference_display']=MeasurementFormatter::formatSpecification((string)$row['test_code'],$row);$row['conforms']=false;$row['evaluated']=true;$row['reason']='Resultado fora da especificação.';}unset($row);return $rows;}
    public static function acknowledge(int $sampleId,int $userId):void
    {Database::connection()->prepare("UPDATE test_result_spec_evaluations e JOIN test_results tr ON tr.id=e.test_result_id JOIN sample_tests st ON st.id=tr.sample_test_id SET e.acknowledged_by=:user,e.acknowledged_at=NOW() WHERE st.sample_id=:sample AND e.conformity_status='NONCONFORMING'")->execute(['user'=>$userId,'sample'=>$sampleId]);}
    public static function snapshotText(array $s):string{$labels=['GT'=>'Maior que','GTE'=>'Maior ou igual a','LT'=>'Menor que','LTE'=>'Menor ou igual a','BETWEEN'=>'Entre','EQUAL_NUMERIC'=>'Igual a','EQUAL_TEXT'=>'Texto igual a','BOOLEAN'=>'Resultado esperado'];$value=match($s['rule_type']){'BETWEEN'=>$s['min_value'].' a '.$s['max_value'],'LT','LTE'=>$s['max_value'],'BOOLEAN'=>self::boolean((string)$s['expected_text'])?'Sim':'Não','EQUAL_TEXT'=>$s['expected_text'],default=>$s['min_value']};return trim(($labels[$s['rule_type']]??$s['rule_type']).' '.$value.' '.($s['unit']??''));}
    private static function normalize(string $v):string{$v=mb_strtolower(trim($v));return strtr($v,['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);}
    private static function boolean(string $v):?bool{return in_array($v,['1','true','sim','positivo','presente'],true)?true:(in_array($v,['0','false','nao','negativo','ausente'],true)?false:null);}
}
