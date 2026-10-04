<?php
declare(strict_types=1);
use BloodHub\Core\Database;
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string$class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});
$pdo=Database::connection();$fail=[];
foreach(['bacteriology_positive_samples','bacteriology_positive_sample_records','bacteriology_positive_record_tests']as$table){$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:name');$q->execute(['name'=>$table]);if(!(int)$q->fetchColumn())$fail[]="Tabela ausente: {$table}";}
foreach(['perform_bacteriology','operational_notes']as$column){$q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bacteriology_positive_sample_records' AND COLUMN_NAME=:name");$q->execute(['name'=>$column]);if(!(int)$q->fetchColumn())$fail[]="Coluna ausente: {$column}";}
$service=file_get_contents(dirname(__DIR__).'/src/Services/PositiveBacteriologySampleService.php');
foreach(["s.purpose='transfusion_reaction'","TRANSFUSION_REACTION_RELATED_COMPONENT_ADDED","TRANSFUSION_REACTION_RELATED_BACTERIOLOGY_STARTED","TRANSFUSION_REACTION_RELATED_POSITIVE_CONFIRMED","bacteriology_positive_record_tests"]as$expected)if(!str_contains($service,$expected))$fail[]="Regra ausente: {$expected}";
if(str_contains($service,'bacteriology_pools'))$fail[]='O serviço de componentes relacionados não pode operar pools.';
$routes=file_get_contents(dirname(__DIR__).'/public/index.php');foreach(['components/add','components/update','tests/save','retests/save']as$route)if(!str_contains($routes,$route))$fail[]="Rota ausente: {$route}";
$view=file_get_contents(dirname(__DIR__).'/src/Views/transfusion_reactions/related_components.php');foreach(['+ Adicionar componente relacionado','perform_bacteriology','SEM TESTE','POSITIVO CONFIRMADO']as$expected)if(!str_contains($view,$expected))$fail[]="Controle visual ausente: {$expected}";
$counts=file_get_contents(dirname(__DIR__).'/src/Services/BacteriologyService.php');if(!str_contains($counts,"s.purpose='quality_control' AND ps.status<>'completed'"))$fail[]='Badge do CQ ainda pode misturar positivos da RT.';
if($fail){fwrite(STDERR,implode(PHP_EOL,$fail).PHP_EOL);exit(1);}echo "OK: componentes RT usam a investigação positiva compartilhada, testes individuais históricos, sem pool e com filas separadas.\n";
