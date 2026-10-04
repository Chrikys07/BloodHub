<?php
declare(strict_types=1);
use BloodHub\Core\Database;
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string $class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file;});
$pdo=Database::connection();$fail=[];
foreach(['preservatives','sample_weight_results','test_result_parameters','sample_tests'] as $table){$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');$q->execute(['table'=>$table]);if(!$q->fetchColumn())$fail[]="Tabela ausente: {$table}";}
foreach(['lcqh_code','preservative_id'] as $column){$q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='samples' AND COLUMN_NAME=:column");$q->execute(['column'=>$column]);if(!$q->fetchColumn())$fail[]="Coluna ausente: samples.{$column}";}
$codes=$pdo->query("SELECT code FROM tests WHERE code IN ('HEMATOCRIT','HEMOGLOBIN','HEMOGLOBIN_PER_UNIT','HEMOLYSIS_DEGREE','FREE_HEMOGLOBIN','VOLUME','PLATELET_COUNT','PLATELETS_PER_UNIT','LEUKOCYTE_COUNT','LEUKOCYTES_PER_UNIT','PH','SWIRLING')")->fetchAll(PDO::FETCH_COLUMN);foreach(['HEMATOCRIT','HEMOGLOBIN','HEMOGLOBIN_PER_UNIT','HEMOLYSIS_DEGREE','FREE_HEMOGLOBIN','VOLUME','PLATELET_COUNT','PLATELETS_PER_UNIT','LEUKOCYTE_COUNT','LEUKOCYTES_PER_UNIT','PH','SWIRLING'] as $code)if(!in_array($code,$codes,true))$fail[]="Teste ausente: {$code}";
foreach(['density_used','volume_ml'] as $column){$q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sample_weight_results' AND COLUMN_NAME=:column");$q->execute(['column'=>$column]);if(!$q->fetchColumn())$fail[]="Coluna ausente: sample_weight_results.{$column}";}
$bact=(int)$pdo->query("SELECT COUNT(*) FROM tests WHERE code='BACTERIOLOGY'")->fetchColumn();if($bact!==1)$fail[]='Teste estável BACTERIOLOGY ausente ou duplicado.';
$duplicates=(int)$pdo->query('SELECT COUNT(*) FROM (SELECT sample_id,test_id FROM sample_tests WHERE test_id IS NOT NULL GROUP BY sample_id,test_id HAVING COUNT(*)>1) duplicate_tests')->fetchColumn();if($duplicates)$fail[]="Há {$duplicates} vínculo(s) sample/test duplicado(s).";
$eligible=(int)$pdo->query("SELECT COUNT(*) FROM samples WHERE purpose='quality_control' AND status IN ('received','in_analysis','partial_results','completed')")->fetchColumn();
if($fail){foreach($fail as $message)fwrite(STDERR,"FALHA: {$message}\n");exit(1);}fwrite(STDOUT,"OK: infraestrutura de resultados íntegra; {$eligible} amostra(s) elegível(is).\n");
