<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database};
use DomainException;
use PDO;

final class HemolysisResultService
{
    private const PARAM_NAMES=['abs_370'=>'Abs 370','abs_415'=>'Abs 415','abs_510'=>'Abs 510','abs_577'=>'Abs 577','abs_600'=>'Abs 600','x_value'=>'X','y_value'=>'Y','formula_branch'=>'Ramo da fórmula','calculated_at'=>'Calculado em','calculated_by'=>'Calculado por','method'=>'Método','import_id'=>'Importação'];

    public static function state(int $sampleId):array
    {
        $q=Database::connection()->prepare("SELECT st.id sample_test_id,st.status,tr.id result_id,tr.result_value_numeric,p.parameter_code,p.numeric_value,p.text_value FROM sample_tests st JOIN tests t ON t.id=st.test_id AND t.code='FREE_HEMOGLOBIN' LEFT JOIN test_results tr ON tr.sample_test_id=st.id LEFT JOIN test_result_parameters p ON p.test_result_id=tr.id WHERE st.sample_id=:id ORDER BY tr.id DESC");$q->execute(['id'=>$sampleId]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);$out=['sample_test_id'=>null,'result_id'=>null,'status'=>'pending','free_hemoglobin_g_dl'=>null,'parameters'=>[]];
        foreach($rows as $r){if($out['result_id']!==null&&(int)$r['result_id']!==$out['result_id'])continue;$out['sample_test_id']=(int)$r['sample_test_id'];$out['status']=$r['status'];if($r['result_id']!==null){$out['result_id']=(int)$r['result_id'];$out['free_hemoglobin_g_dl']=$r['result_value_numeric']===null?null:(float)$r['result_value_numeric'];if($r['parameter_code'])$out['parameters'][$r['parameter_code']]=$r['numeric_value']!==null?(float)$r['numeric_value']:$r['text_value'];}}
        return $out;
    }

    public static function save(int $sampleId,array $input,string $method='MANUAL',?int $importId=null,bool $replace=false):array
    {
        $pdo=Database::connection();$test=TestResultService::configuredTest($sampleId,TestResultService::CODES['free_hemoglobin']);if(!$test)throw new DomainException('Hemoglobina Livre não está configurada para este hemocomponente.');
        $values=[];$filled=0;foreach(HemolysisCalculator::FIELDS as $f){$values[$f]=HemolysisCalculator::decimal($input[$f]??null,false);if($values[$f]!==null)$filled++;}
        $current=self::state($sampleId);if($current['status']==='completed'&&$current['result_id'])throw new DomainException('Esta amostra já possui resultado concluído e não pode ser sobrescrito.');
        if($current['result_id']&&$method==='IMPORT'&&!$replace)throw new DomainException('Esta amostra já possui resultado. Confirme explicitamente a substituição.');
        $calc=$filled===5?HemolysisCalculator::calculate($values):null;$user=(int)(Auth::user()['id']??0);
        $st=$pdo->prepare('SELECT id FROM sample_tests WHERE sample_id=:sample AND test_id=:test FOR UPDATE');$st->execute(['sample'=>$sampleId,'test'=>$test['id']]);$sampleTestId=(int)$st->fetchColumn();if(!$sampleTestId){$pdo->prepare("INSERT INTO sample_tests(sample_id,test_id,status,started_at,executed_by) VALUES(:sample,:test,'in_progress',NOW(),:user)")->execute(['sample'=>$sampleId,'test'=>$test['id'],'user'=>$user?:null]);$sampleTestId=(int)$pdo->lastInsertId();}
        $old=$pdo->prepare('SELECT * FROM test_results WHERE sample_test_id=:id ORDER BY id DESC LIMIT 1 FOR UPDATE');$old->execute(['id'=>$sampleTestId]);$before=$old->fetch(PDO::FETCH_ASSOC);
        if($before){$resultId=(int)$before['id'];$pdo->prepare('UPDATE test_results SET result_value_numeric=:value,recorded_by=:user,recorded_at=NOW() WHERE id=:id')->execute(['value'=>$calc['free_hemoglobin_g_dl']??null,'user'=>$user?:null,'id'=>$resultId]);}
        else{$pdo->prepare('INSERT INTO test_results(sample_test_id,result_value_numeric,recorded_by) VALUES(:st,:value,:user)')->execute(['st'=>$sampleTestId,'value'=>$calc['free_hemoglobin_g_dl']??null,'user'=>$user?:null]);$resultId=(int)$pdo->lastInsertId();}
        $params=$values;if($calc)$params=array_merge($params,['x_value'=>$calc['x_value'],'y_value'=>$calc['y_value'],'formula_branch'=>$calc['formula_branch'],'calculated_at'=>date('Y-m-d H:i:s'),'calculated_by'=>(string)$user]);$params['method']=$method;if($importId)$params['import_id']=(string)$importId;
        $up=$pdo->prepare('INSERT INTO test_result_parameters(test_result_id,parameter_code,parameter_name,numeric_value,text_value) VALUES(:result,:code,:name,:numeric,:text) ON DUPLICATE KEY UPDATE parameter_name=VALUES(parameter_name),numeric_value=VALUES(numeric_value),text_value=VALUES(text_value),updated_at=NOW()');foreach($params as $code=>$value){$numeric=is_float($value)||is_int($value);$up->execute(['result'=>$resultId,'code'=>$code,'name'=>self::PARAM_NAMES[$code]??$code,'numeric'=>$numeric?$value:null,'text'=>$value===null||$numeric?null:(string)$value]);}
        $stageStatus='in_progress';$pdo->prepare("UPDATE sample_tests SET status=:status,started_at=COALESCE(started_at,NOW()),completed_at=NULL,executed_by=:user WHERE id=:id")->execute(['status'=>$stageStatus,'user'=>$user?:null,'id'=>$sampleTestId]);TestResultService::refreshSampleStatus($sampleId);
        Auth::registerAudit($method==='IMPORT'?'HEMOLYSIS_IMPORT_APPLY':($before?'HEMOLYSIS_RESULT_UPDATE':'HEMOLYSIS_MANUAL_SAVE'),'test_results',$resultId,$before?:null,['sample_id'=>$sampleId,'method'=>$method,'values'=>$params,'free_hemoglobin_g_dl'=>$calc['free_hemoglobin_g_dl']??null]);
        return ['result_id'=>$resultId,'complete_absorbances'=>$filled===5,'calculation'=>$calc];
    }

    public static function complete(int $sampleId):array
    {
        $state=self::state($sampleId);$missing=[];foreach(HemolysisCalculator::FIELDS as $f)if(!array_key_exists($f,$state['parameters'])||$state['parameters'][$f]===null){$missing[]='as cinco absorbâncias';break;}if(!self::validNumeric($state['free_hemoglobin_g_dl']))$missing[]='Hemoglobina Livre';if($missing)return $missing;
        $pdo=Database::connection();$pdo->prepare("UPDATE sample_tests SET status='completed',started_at=COALESCE(started_at,NOW()),completed_at=NOW(),executed_by=:user WHERE id=:id")->execute(['user'=>Auth::user()['id']??null,'id'=>$state['sample_test_id']]);CalculatedTestService::recalculateHemolysis($sampleId);TestResultService::refreshSampleStatus($sampleId);Auth::registerAudit('HEMOLYSIS_FREE_HB_COMPLETED','sample_tests',(int)$state['sample_test_id'],['status'=>$state['status']],['status'=>'completed','sample_id'=>$sampleId,'free_hemoglobin_g_dl'=>$state['free_hemoglobin_g_dl']]);return [];
    }

    public static function synchronizeStage(int $sampleId):string
    {
        $state=self::state($sampleId);if(!$state['sample_test_id'])return 'pending';$filled=0;foreach(HemolysisCalculator::FIELDS as $field)if(array_key_exists($field,$state['parameters'])&&$state['parameters'][$field]!==null)$filled++;$status=$filled===0?'pending':(($filled===5&&self::validNumeric($state['free_hemoglobin_g_dl']))?'completed':'in_progress');if($state['status']!==$status){Database::connection()->prepare("UPDATE sample_tests SET status=:new_status,started_at=CASE WHEN :pending_status='pending' THEN started_at ELSE COALESCE(started_at,NOW()) END,completed_at=CASE WHEN :completed_status='completed' THEN COALESCE(completed_at,NOW()) ELSE NULL END WHERE id=:id")->execute(['new_status'=>$status,'pending_status'=>$status,'completed_status'=>$status,'id'=>$state['sample_test_id']]);Auth::registerAudit('HEMOLYSIS_STATUS_SYNC','sample_tests',$state['sample_test_id'],['status'=>$state['status']],['status'=>$status,'sample_id'=>$sampleId]);}return $status;
    }

    private static function validNumeric(mixed $value):bool{return $value!==null&&$value!==''&&is_numeric($value)&&is_finite((float)$value);}
}
