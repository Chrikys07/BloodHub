<?php
declare(strict_types=1);
use BloodHub\Core\Database;
use BloodHub\Services\CpafYieldClassificationService;
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string $class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file;});
$pdo=Database::connection();$failures=[];$pdo->beginTransaction();
try{
    $component=(int)$pdo->query("SELECT id FROM blood_components WHERE code='CPAF'")->fetchColumn();$test=(int)$pdo->query("SELECT id FROM tests WHERE code='PLATELETS_PER_UNIT'")->fetchColumn();$rule=$pdo->query("SELECT * FROM cpaf_yield_classification_rules WHERE blood_component_id={$component} AND active=1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if(!$component||!$test||!$rule)throw new RuntimeException('Configuracao CPAF inicial nao encontrada.');
    $createSample=static function(string $suffix,float $value)use($pdo,$component,$test):int{$pdo->prepare("INSERT INTO samples(sample_code,purpose,blood_component_id,status,received_at) VALUES(:code,'quality_control',:component,'partial_results',NOW())")->execute(['code'=>'TEST-CPAF-VERSION-'.$suffix.'-'.bin2hex(random_bytes(4)),'component'=>$component]);$sample=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO sample_tests(sample_id,test_id,status,started_at,completed_at) VALUES(:sample,:test,'completed',NOW(),NOW())")->execute(['sample'=>$sample,'test'=>$test]);$pdo->prepare('INSERT INTO test_results(sample_test_id,result_value_numeric) VALUES(:sample_test,:value)')->execute(['sample_test'=>(int)$pdo->lastInsertId(),'value'=>$value]);return $sample;};
    $oldSample=$createSample('OLD',5.5e11);$old=CpafYieldClassificationService::recalculate($oldSample,true);if(($old['code']??null)!=='CPAF_SIMPLE')$failures[]='Amostra antiga nao foi classificada como simples.';
    $pdo->prepare('UPDATE cpaf_yield_classification_rules SET active=0 WHERE blood_component_id=:component AND active=1')->execute(['component'=>$component]);
    $pdo->prepare("INSERT INTO cpaf_yield_classification_rules(supersedes_id,version_number,blood_component_id,simple_min_platelets,double_min_platelets,effective_from,source_name,active) VALUES(:old,:version,:component,400000000000,500000000000,CURDATE(),'Teste transacional de versionamento',1)")->execute(['old'=>$rule['id'],'version'=>(int)$rule['version_number']+1,'component'=>$component]);
    $newSample=$createSample('NEW',5.5e11);$new=CpafYieldClassificationService::recalculate($newSample);$oldAfter=CpafYieldClassificationService::state($oldSample);
    if(($new['code']??null)!=='CPAF_DOUBLE')$failures[]='Amostra nova nao usou a nova versao.';if(($oldAfter['code']??null)!=='CPAF_SIMPLE'||empty($oldAfter['finalized']))$failures[]='Snapshot historico foi alterado.';
}finally{$pdo->rollBack();}
if($failures){fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL);exit(1);}fwrite(STDOUT,"Versionamento CPAF: snapshot antigo preservado e nova amostra reclassificada.\n");
