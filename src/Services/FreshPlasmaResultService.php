<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database};
use DomainException;
use PDO;

final class FreshPlasmaResultService
{
    private const GROUPS=[
        ['test'=>'PLATELETS_PER_ML','method'=>'platelet_method','count'=>'platelet_count','method_code'=>'PLATELET_METHOD','method_name'=>'Método Plaquetas','count_code'=>'PLATELET_COUNT','count_name'=>'Nº Plaquetas','result'=>'platelets_per_ml'],
        ['test'=>'LEUKOCYTES_PER_ML','method'=>'leukocyte_method','count'=>'leukocyte_count','method_code'=>'LEUKOCYTE_METHOD','method_name'=>'Método Leucócitos','count_code'=>'LEUKOCYTE_COUNT','count_name'=>'Nº Leucócitos','result'=>'leukocytes_per_ml'],
        ['test'=>'REDBLOODCELLS_PER_ML','method'=>'red_cell_method','count'=>'red_cell_count','method_code'=>'RED_CELL_METHOD','method_name'=>'Método Hemácias','count_code'=>'RED_CELL_COUNT','count_name'=>'Nº Hemácias','result'=>'red_cells_per_ml'],
    ];

    public static function save(int $sampleId,array $input):array
    {
        self::assertFreshPlasma($sampleId);$calculated=FreshPlasmaResultCalculator::calculate($input);
        foreach(self::GROUPS as $g){$method=trim((string)($input[$g['method']]??''));$count=PlateletResultCalculator::decimal($input[$g['count']]??null);if($method===''&&$count===null)continue;$resultId=self::resultId($sampleId,$g['test'],$calculated[$g['result']]);self::parameter($resultId,$g['method_code'],$g['method_name'],null,$method?:null);self::parameter($resultId,$g['count_code'],$g['count_name'],$count,null);}
        TestResultService::refreshSampleStatus($sampleId);Auth::registerAudit('quality_fresh_plasma_results.save','samples',$sampleId,null,$calculated);return $calculated;
    }

    public static function state(int $sampleId):array
    {
        $state=['platelet_method'=>null,'platelet_count'=>null,'platelets_per_ml'=>null,'leukocyte_method'=>null,'leukocyte_count'=>null,'leukocytes_per_ml'=>null,'red_cell_method'=>null,'red_cell_count'=>null,'red_cells_per_ml'=>null];
        $q=Database::connection()->prepare("SELECT t.code,tr.result_value_numeric,p.parameter_code,p.numeric_value,p.text_value FROM sample_tests st JOIN tests t ON t.id=st.test_id LEFT JOIN test_results tr ON tr.sample_test_id=st.id LEFT JOIN test_result_parameters p ON p.test_result_id=tr.id WHERE st.sample_id=:id AND t.code IN ('PLATELETS_PER_ML','LEUKOCYTES_PER_ML','REDBLOODCELLS_PER_ML')");$q->execute(['id'=>$sampleId]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$resultKey=match($row['code']){'PLATELETS_PER_ML'=>'platelets_per_ml','LEUKOCYTES_PER_ML'=>'leukocytes_per_ml',default=>'red_cells_per_ml'};$state[$resultKey]=$row['result_value_numeric'];$parameterKey=match($row['parameter_code']??''){'PLATELET_METHOD'=>'platelet_method','PLATELET_COUNT'=>'platelet_count','LEUKOCYTE_METHOD'=>'leukocyte_method','LEUKOCYTE_COUNT'=>'leukocyte_count','RED_CELL_METHOD'=>'red_cell_method','RED_CELL_COUNT'=>'red_cell_count',default=>null};if($parameterKey)$state[$parameterKey]=$row['text_value']??$row['numeric_value'];}return $state;
    }

    private static function resultId(int $sampleId,string $testCode,?float $value):int
    {
        $test=TestResultService::configuredTest($sampleId,$testCode);if(!$test)throw new DomainException('O teste '.$testCode.' não está configurado para este hemocomponente.');$pdo=Database::connection();$q=$pdo->prepare('SELECT id FROM sample_tests WHERE sample_id=:sample AND test_id=:test');$q->execute(['sample'=>$sampleId,'test'=>$test['id']]);$st=(int)$q->fetchColumn();if(!$st){$pdo->prepare("INSERT INTO sample_tests(sample_id,test_id,status,started_at,executed_by) VALUES(:sample,:test,'in_progress',NOW(),:user)")->execute(['sample'=>$sampleId,'test'=>$test['id'],'user'=>Auth::user()['id']??null]);$st=(int)$pdo->lastInsertId();}$q=$pdo->prepare('SELECT id FROM test_results WHERE sample_test_id=:id ORDER BY id DESC LIMIT 1');$q->execute(['id'=>$st]);$result=(int)$q->fetchColumn();if($result)$pdo->prepare('UPDATE test_results SET result_value_numeric=:value,recorded_by=:user,recorded_at=NOW() WHERE id=:id')->execute(['value'=>$value,'user'=>Auth::user()['id']??null,'id'=>$result]);else{$pdo->prepare('INSERT INTO test_results(sample_test_id,result_value_numeric,recorded_by) VALUES(:st,:value,:user)')->execute(['st'=>$st,'value'=>$value,'user'=>Auth::user()['id']??null]);$result=(int)$pdo->lastInsertId();}$status=$value===null?'in_progress':'completed';$pdo->prepare("UPDATE sample_tests SET status=:status,started_at=COALESCE(started_at,NOW()),completed_at=CASE WHEN :done=1 THEN COALESCE(completed_at,NOW()) ELSE NULL END,executed_by=:user WHERE id=:id")->execute(['status'=>$status,'done'=>$value===null?0:1,'user'=>Auth::user()['id']??null,'id'=>$st]);if($value!==null)SpecificationEvaluator::persistForResult($result);return $result;
    }

    private static function parameter(int $result,string $code,string $name,?float $numeric,?string $text):void
    {Database::connection()->prepare('INSERT INTO test_result_parameters(test_result_id,parameter_code,parameter_name,numeric_value,text_value) VALUES(:result,:code,:name,:numeric,:text) ON DUPLICATE KEY UPDATE parameter_name=VALUES(parameter_name),numeric_value=VALUES(numeric_value),text_value=VALUES(text_value),updated_at=NOW()')->execute(compact('result','code','name','numeric','text'));}
    private static function assertFreshPlasma(int $sampleId):void{$q=Database::connection()->prepare('SELECT bc.code FROM samples s JOIN blood_components bc ON bc.id=s.blood_component_id WHERE s.id=:id');$q->execute(['id'=>$sampleId]);if(!in_array($q->fetchColumn(),['PF','PF24'],true))throw new DomainException('Os resultados informados não pertencem a uma amostra PF/PF24.');}
}
