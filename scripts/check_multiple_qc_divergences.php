<?php
declare(strict_types=1);

use BloodHub\Core\Database;
use BloodHub\Services\{QcNotificationService,SpecificationEvaluator,TestResultService};

if(PHP_SAPI!=='cli')exit(1);
spl_autoload_register(static function(string $class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file;});

$pdo=Database::connection();$failures=[];$check=static function(bool $ok,string $label)use(&$failures):void{fwrite($ok?STDOUT:STDERR,($ok?'OK  ':'FALHA  ').$label.PHP_EOL);if(!$ok)$failures[]=$label;};
$pdo->beginTransaction();
try{
    $suffix=bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO blood_components(code,name,status) VALUES(:code,'Componente regressivo multi-NC','active')")->execute(['code'=>'MULTINC_'.$suffix]);$component=(int)$pdo->lastInsertId();
    $tests=[];foreach(['PLATELETS_PER_UNIT','LEUKOCYTES_PER_UNIT','PH'] as $code){$q=$pdo->prepare('SELECT id FROM tests WHERE code=:code');$q->execute(['code'=>$code]);$tests[$code]=(int)$q->fetchColumn();$pdo->prepare('INSERT INTO test_blood_components(test_id,blood_component_id,is_required) VALUES(:test,:component,1)')->execute(['test'=>$tests[$code],'component'=>$component]);}
    $specs=[['PLATELETS_PER_UNIT','GTE',5.5e10,null],['LEUKOCYTES_PER_UNIT','LT',null,2e8],['PH','GT',6.4,null]];
    foreach($specs as [$code,$rule,$min,$max]){$pdo->prepare('INSERT INTO blood_component_test_specifications(blood_component_id,test_id,rule_type,min_value,max_value,unit,effective_from,active) VALUES(:component,:test,:rule,:min,:max,:unit,CURDATE(),1)')->execute(['component'=>$component,'test'=>$tests[$code],'rule'=>$rule,'min'=>$min,'max'=>$max,'unit'=>in_array($code,['PLATELETS_PER_UNIT','LEUKOCYTES_PER_UNIT'],true)?'/U':null]);}
    $origin=(int)$pdo->query("SELECT id FROM units WHERE status='active' ORDER BY id LIMIT 1")->fetchColumn();
    $pdo->prepare("INSERT INTO samples(sample_code,purpose,blood_component_id,origin_unit_id,status,received_at) VALUES(:code,'quality_control',:component,:origin,'in_analysis',NOW())")->execute(['code'=>'MULTINC-'.$suffix,'component'=>$component,'origin'=>$origin]);$sample=(int)$pdo->lastInsertId();
    $save=static function(string $code,float $value)use($sample):void{TestResultService::saveNumeric($sample,$code,$value);};
    $save('PLATELETS_PER_UNIT',3.58e10);$save('LEUKOCYTES_PER_UNIT',1.19e9);$save('PH',6.8);
    $evaluation=SpecificationEvaluator::evaluateSampleResults($sample);$outside=SpecificationEvaluator::nonconforming($sample);
    $check(count($evaluation['evaluations'])===3,'avaliador percorre os três testes');
    $check(count($evaluation['divergences'])===2&&count($outside)===2,'save preserva duas divergências independentes');
    $check(array_column($outside,'test_id')===[$tests['LEUKOCYTES_PER_UNIT'],$tests['PLATELETS_PER_UNIT']]||count(array_intersect(array_column($outside,'test_id'),[$tests['PLATELETS_PER_UNIT'],$tests['LEUKOCYTES_PER_UNIT']]))===2,'coleção contém Plaquetas/U e Leucócitos/U');
    $save('PH',6.2);$evaluation=SpecificationEvaluator::evaluateSampleResults($sample);$check(count($evaluation['divergences'])===3&&count(SpecificationEvaluator::nonconforming($sample))===3,'save preserva três divergências');
    $save('PLATELETS_PER_UNIT',5.8e10);SpecificationEvaluator::evaluateSampleResults($sample);$remaining=SpecificationEvaluator::nonconforming($sample);$check(count($remaining)===2&&!in_array($tests['PLATELETS_PER_UNIT'],array_column($remaining,'test_id'),true),'correção remove somente a divergência corrigida');
    $save('LEUKOCYTES_PER_UNIT',1e8);$save('PH',6.8);SpecificationEvaluator::evaluateSampleResults($sample);$check(SpecificationEvaluator::nonconforming($sample)===[],'todos conformes removem o alerta');
    $save('PLATELETS_PER_UNIT',3.58e10);$save('LEUKOCYTES_PER_UNIT',1.19e9);$save('PH',6.2);SpecificationEvaluator::evaluateSampleResults($sample);$pdo->prepare("UPDATE samples SET status='completed' WHERE id=:id")->execute(['id'=>$sample]);$notificationIds=QcNotificationService::createForSample($sample);$check(count($notificationIds)===3,'conclusão cria uma notificação por teste divergente');$check(QcNotificationService::createForSample($sample)===[],'notificações são idempotentes por result_id');
    $q=$pdo->prepare('SELECT COUNT(DISTINCT test_id) FROM qc_notifications WHERE sample_id=:sample');$q->execute(['sample'=>$sample]);$check((int)$q->fetchColumn()===3,'notificações preservam três test_id distintos');
}finally{$pdo->rollBack();}
exit($failures?1:0);
