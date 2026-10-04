<?php
declare(strict_types=1);
use BloodHub\Core\Database;
use BloodHub\Services\BacteriologyEligibility;
if(PHP_SAPI!=='cli')exit(1);
spl_autoload_register(static function(string$class):void{$p='BloodHub\\';if(str_starts_with($class,$p))require dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($p))).'.php';});
$pdo=Database::connection();
$analyzed=(int)$pdo->query("SELECT COUNT(*) FROM samples s JOIN test_blood_components x ON x.blood_component_id=s.blood_component_id JOIN tests t ON t.id=x.test_id AND t.code='BACTERIOLOGY' AND t.status='active' WHERE s.purpose='quality_control' AND s.status IN ('received','in_analysis','partial_results') AND NOT EXISTS(SELECT 1 FROM bacteriology_results br WHERE br.sample_id=s.id AND br.is_final=1)")->fetchColumn();
$created=BacteriologyEligibility::syncOpenSamples();
printf("Analisados: %d\nCriados: %d\nIgnorados: %d\nCorrigidos: 0\n",$analyzed,$created,max(0,$analyzed-$created));
