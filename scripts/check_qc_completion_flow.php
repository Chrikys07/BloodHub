<?php
declare(strict_types=1);
use BloodHub\Core\Database;
use BloodHub\Services\SpecificationEvaluator;
if(PHP_SAPI!=='cli')exit(1);
spl_autoload_register(static function(string$class):void{$p='BloodHub\\';if(str_starts_with($class,$p))require dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($p))).'.php';});
$root=dirname(__DIR__);$fail=[];$check=static function(bool$ok,string$label)use(&$fail):void{fwrite($ok?STDOUT:STDERR,($ok?'OK  ':'FALHA  ').$label."\n");if(!$ok)$fail[]=$label;};
$controller=file_get_contents($root.'/src/Controllers/QualityControlController.php');$results=file_get_contents($root.'/src/Services/TestResultService.php');$view=file_get_contents($root.'/src/Views/quality_control/index.php');$notify=file_get_contents($root.'/src/Services/QcNotificationService.php');$hemolysis=file_get_contents($root.'/src/Services/HemolysisResultService.php');
$check(str_contains($controller,'previewSpecifications')&&str_contains($controller,'SpecificationEvaluator::nonconforming($id)'),'pré-avaliação usa o avaliador único');
$check(str_contains($view,"fetch('/quality-control/specifications/preview'")&&!str_contains($view,'Nenhum resultado fora da especificação.</span>'),'modal usa resposta atual e não exibe falso vazio');
$check(str_contains($results,"t.code<>'BACTERIOLOGY'")&&!str_contains($controller,"Bacteriológico pendente"),'CQ independente do bacteriológico');
$check(str_contains($notify,'SELECT email FROM units WHERE id=:unit UNION'),'e-mail institucional precede usuários autorizados');
$check(str_contains($hemolysis,'HEMOLYSIS_FREE_HB_COMPLETED')&&str_contains($hemolysis,"SET status='completed'"),'Hemoglobina Livre possui conclusão isolada e auditada');
$check(!SpecificationEvaluator::evaluateRule(['rule_type'=>'BETWEEN','min_value'=>50,'max_value'=>80],'81,03'),'decimal brasileiro fora da faixa');
$check(SpecificationEvaluator::evaluateRule(['rule_type'=>'GTE','min_value'=>5.5e10,'max_value'=>null],'5,5E10'),'notação científica brasileira normalizada');
$pdo=Database::connection();$q=$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='units' AND COLUMN_NAME='email' AND IS_NULLABLE='YES'");$check((int)$q->fetchColumn()===1,'units.email retrocompatível e anulável');
$q=$pdo->query("SELECT COUNT(*) FROM (SELECT result_id,COUNT(*) total FROM qc_notifications GROUP BY result_id HAVING total>1)x");$check((int)$q->fetchColumn()===0,'notificações idempotentes por resultado');
exit($fail?1:0);
