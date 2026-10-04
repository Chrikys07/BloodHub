<?php
declare(strict_types=1);
spl_autoload_register(static function(string $class):void{$prefix='BloodHub\\';if(str_starts_with($class,$prefix))require dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';});
use BloodHub\Core\Database;
use BloodHub\Services\SpecificationEvaluator;

$pdo=Database::connection();$pdo->beginTransaction();$fail=0;
try{
 $suffix=bin2hex(random_bytes(4));$pdo->prepare("INSERT INTO blood_components(code,name,status) VALUES(:code,'Componente de teste','active')")->execute(['code'=>'HIST_'.$suffix]);$component=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO tests(code,name,result_type,status) VALUES(:code,'pH histórico','numeric','active')")->execute(['code'=>'PH_HIST_'.$suffix]);$test=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO test_blood_components(test_id,blood_component_id) VALUES(:t,:c)')->execute(['t'=>$test,'c'=>$component]);
 $pdo->prepare("INSERT INTO blood_component_test_specifications(version_number,blood_component_id,test_id,rule_type,min_value,effective_from,effective_to,active) VALUES(1,:c,:t,'GT',6.4,'2026-01-01','2027-12-31',1)")->execute(['c'=>$component,'t'=>$test]);$spec1=(int)$pdo->lastInsertId();
 $makeResult=function(string $code,string $recorded,string $status)use($pdo,$component,$test):int{$pdo->prepare("INSERT INTO samples(sample_code,purpose,blood_component_id,status) VALUES(:code,'quality_control',:c,:status)")->execute(['code'=>$code,'c'=>$component,'status'=>$status]);$sample=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO sample_tests(sample_id,test_id,status,completed_at) VALUES(:s,:t,'completed',:at)")->execute(['s'=>$sample,'t'=>$test,'at'=>$recorded]);$st=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO test_results(sample_test_id,result_value_numeric,recorded_at) VALUES(:st,6.45,:at)')->execute(['st'=>$st,'at'=>$recorded]);return (int)$pdo->lastInsertId();};
 $oldResult=$makeResult('HIST_OLD_'.$suffix,'2026-06-01 10:00:00','completed');$old=SpecificationEvaluator::persistForResult($oldResult);$pdo->prepare("INSERT INTO blood_component_test_specifications(supersedes_id,version_number,blood_component_id,test_id,rule_type,min_value,effective_from,active) VALUES(:old,2,:c,:t,'GT',6.5,'2028-01-01',1)")->execute(['old'=>$spec1,'c'=>$component,'t'=>$test]);$spec2=(int)$pdo->lastInsertId();$oldAgain=SpecificationEvaluator::persistForResult($oldResult);
 $newResult=$makeResult('HIST_NEW_'.$suffix,'2028-06-01 10:00:00','in_analysis');$new=SpecificationEvaluator::persistForResult($newResult);
 $checks=['resultado 2026 conforme'=>$old['status']==='CONFORMING','resultado 2026 mantém versão 1'=>(int)($oldAgain['specification_id']??$oldAgain['specification']['id']??0)===$spec1,'resultado 2028 não conforme'=>$new['status']==='NONCONFORMING','resultado 2028 usa versão 2'=>(int)($new['specification']['id']??0)===$spec2];foreach($checks as $label=>$ok){echo ($ok?'OK':'FALHA').' '.$label.PHP_EOL;if(!$ok)$fail++;}
}finally{$pdo->rollBack();}
exit($fail?1:0);
