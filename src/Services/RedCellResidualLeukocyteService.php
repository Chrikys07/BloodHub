<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database};
use DomainException;
use PDO;

final class RedCellResidualLeukocyteService
{
    /** Hemocomponentes que compartilham o fluxo de hemacias com leucocitos residuais. */
    public static function supports(string $code):bool{return in_array($code,['CHF','CHAF'],true);}

    public static function state(int $sampleId):array
    {
        $q=Database::connection()->prepare("SELECT t.code,tr.id result_id,tr.result_value_numeric FROM sample_tests st JOIN tests t ON t.id=st.test_id LEFT JOIN test_results tr ON tr.sample_test_id=st.id WHERE st.sample_id=:id AND t.code IN ('LEUKOCYTE_COUNT','LEUKOCYTES_PER_UNIT')");
        $q->execute(['id'=>$sampleId]);$state=['leukocyte_method'=>null,'leukocyte_count'=>null,'leukocytes_per_unit'=>null];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){if($row['code']==='LEUKOCYTE_COUNT'){$state['leukocyte_count']=$row['result_value_numeric'];if($row['result_id']){$p=Database::connection()->prepare("SELECT text_value FROM test_result_parameters WHERE test_result_id=:id AND parameter_code='LEUKOCYTE_METHOD'");$p->execute(['id'=>$row['result_id']]);$state['leukocyte_method']=$p->fetchColumn()?:null;}}elseif($row['code']==='LEUKOCYTES_PER_UNIT')$state['leukocytes_per_unit']=$row['result_value_numeric'];}
        return $state;
    }

    public static function save(int $sampleId,array $input):void
    {
        $method=trim((string)($input['leukocyte_method']??''));
        if($method!==''&&$method!==RedCellResidualLeukocyteCalculator::METHOD)throw new DomainException('Selecione o método Nageotte para leucócitos residuais.');
        $count=PlateletResultCalculator::decimal($input['leukocyte_count']??null);
        if($count===null){if($method!=='')self::savePendingMethod($sampleId,$method);return;}
        if($method==='')throw new DomainException('Selecione o método de leucócitos.');
        $volume=self::volume($sampleId);$perUnit=RedCellResidualLeukocyteCalculator::calculate($count,$volume);
        $result=TestResultService::saveNumeric($sampleId,TestResultService::CODES['leukocyte_count'],$count);
        self::saveMethod((int)$result['id'],$method);
        TestResultService::saveNumeric($sampleId,TestResultService::CODES['leukocytes_per_unit'],$perUnit);
        Auth::registerAudit('quality_red_cell_leukocytes.save','samples',$sampleId,null,['method'=>$method,'count'=>$count,'volume_ml'=>$volume,'leukocytes_per_unit'=>$perUnit]);
    }

    public static function recalculate(int $sampleId):?float
    {
        $q=Database::connection()->prepare('SELECT bc.code FROM samples s JOIN blood_components bc ON bc.id=s.blood_component_id WHERE s.id=:id');$q->execute(['id'=>$sampleId]);
        if(!self::supports((string)$q->fetchColumn()))return null;$state=self::state($sampleId);
        if($state['leukocyte_count']===null)return null;
        $value=RedCellResidualLeukocyteCalculator::calculate($state['leukocyte_count'],self::volume($sampleId));
        TestResultService::saveNumeric($sampleId,TestResultService::CODES['leukocytes_per_unit'],$value);return $value;
    }

    private static function volume(int $id):?float{$q=Database::connection()->prepare('SELECT volume_ml FROM sample_weight_results WHERE sample_id=:id');$q->execute(['id'=>$id]);$v=$q->fetchColumn();return $v===false||$v===null?null:(float)$v;}
    private static function savePendingMethod(int $sampleId,string $method):void{$test=TestResultService::configuredTest($sampleId,TestResultService::CODES['leukocyte_count']);if(!$test)return;$pdo=Database::connection();$q=$pdo->prepare('SELECT id FROM sample_tests WHERE sample_id=:sample AND test_id=:test');$q->execute(['sample'=>$sampleId,'test'=>$test['id']]);$st=(int)$q->fetchColumn();if(!$st){$pdo->prepare("INSERT INTO sample_tests(sample_id,test_id,status,started_at,executed_by) VALUES(:sample,:test,'in_progress',NOW(),:user)")->execute(['sample'=>$sampleId,'test'=>$test['id'],'user'=>Auth::user()['id']??null]);$st=(int)$pdo->lastInsertId();}$q=$pdo->prepare('SELECT id FROM test_results WHERE sample_test_id=:id ORDER BY id DESC LIMIT 1');$q->execute(['id'=>$st]);$r=(int)$q->fetchColumn();if(!$r){$pdo->prepare('INSERT INTO test_results(sample_test_id,recorded_by) VALUES(:st,:user)')->execute(['st'=>$st,'user'=>Auth::user()['id']??null]);$r=(int)$pdo->lastInsertId();}self::saveMethod($r,$method);TestResultService::refreshSampleStatus($sampleId);}
    private static function saveMethod(int $resultId,string $method):void{Database::connection()->prepare("INSERT INTO test_result_parameters(test_result_id,parameter_code,parameter_name,text_value) VALUES(:result,'LEUKOCYTE_METHOD','Método Leucócitos',:value) ON DUPLICATE KEY UPDATE parameter_name=VALUES(parameter_name),text_value=VALUES(text_value),numeric_value=NULL")->execute(['result'=>$resultId,'value'=>$method]);}
}
