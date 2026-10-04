<?php
declare(strict_types=1);
use BloodHub\Core\Database;
use BloodHub\Services\BacteriologyService;
if(PHP_SAPI!=='cli')exit(1);
spl_autoload_register(static function(string$class):void{$p='BloodHub\\';if(str_starts_with($class,$p))require dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($p))).'.php';});
$pdo=Database::connection();$required=['bacteriology_results','bacteriology_pools','bacteriology_pool_members','bacteriology_retests'];foreach($required as$table){$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');$q->execute(['table'=>$table]);if(!(int)$q->fetchColumn())throw new RuntimeException("Tabela ausente: $table");}
$q=$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bacteriology_pool_members' AND INDEX_NAME='uk_bact_active_sample' AND NON_UNIQUE=0");if(!(int)$q->fetchColumn())throw new RuntimeException('Constraint de membro ativo ausente.');
$columns=['identified_bacteria','workflow_status'];foreach($columns as$column){$q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bacteriology_results' AND COLUMN_NAME=:column");$q->execute(['column'=>$column]);if(!(int)$q->fetchColumn())throw new RuntimeException("Coluna ausente: $column");}
$q=$pdo->query("SELECT COUNT(*) FROM blood_components bc JOIN test_blood_components x ON x.blood_component_id=bc.id JOIN tests t ON t.id=x.test_id AND t.code='BACTERIOLOGY' AND t.status='active'");$eligible=(int)$q->fetchColumn();
if(BacteriologyService::POOL_MAX_SIZE!==4||BacteriologyService::POOL_COMPONENT!=='CP')throw new RuntimeException('Constantes de pool inválidas.');
$service=file_get_contents(dirname(__DIR__).'/src/Services/BacteriologyService.php');foreach(['bulkCompleteIndividuals','bacteriology.bulk_completed',"\$result==='negative'","identified_bacteria_retest"]as$expected)if(!str_contains($service,$expected))throw new RuntimeException("Regra bacteriológica ausente: $expected");
echo "OK: fluxo bacteriológico íntegro; elegibilidade central possui $eligible vínculo(s), pools limitados a CP/4 e exclusividade ativa protegida no banco.\n";
