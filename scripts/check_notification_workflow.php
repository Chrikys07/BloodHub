<?php
declare(strict_types=1);
use BloodHub\Core\Database;
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string $class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});
$pdo=Database::connection();
$tables=['qc_notification_analyses','qc_notification_analysis_items','qc_notification_actions','qc_notification_events','qc_notification_analysis_events'];
foreach($tables as$table){if(!$pdo->query('SHOW TABLES LIKE '.$pdo->quote($table))->fetchColumn())throw new RuntimeException("Tabela ausente: {$table}");}
$duplicates=(int)$pdo->query("SELECT COUNT(*) FROM (SELECT i.notification_id FROM qc_notification_analysis_items i JOIN qc_notification_analyses a ON a.id=i.analysis_id WHERE i.removed_at IS NULL AND a.status != 'COMPLETED' GROUP BY i.notification_id HAVING COUNT(*)>1) duplicated")->fetchColumn();
$invalidGroups=(int)$pdo->query('SELECT COUNT(*) FROM qc_notification_analysis_items i JOIN qc_notification_analyses a ON a.id=i.analysis_id JOIN qc_notifications n ON n.id=i.notification_id WHERE i.removed_at IS NULL AND (a.unit_id!=n.origin_unit_id OR NOT(a.blood_component_id<=>n.blood_component_id))')->fetchColumn();
$testColumn=$pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='qc_notification_analyses' AND COLUMN_NAME='test_id'")->fetchColumn();
$permissionCount=(int)$pdo->query("SELECT COUNT(*) FROM permissions WHERE permission_key IN ('notifications.view','notifications.acknowledge','notifications.analyze','notifications.action_plan','notifications.close','notifications.manage') AND status='active'")->fetchColumn();
if($duplicates||$invalidGroups||$permissionCount!==6||$testColumn!=='YES')throw new RuntimeException("Integridade inválida: duplicadas={$duplicates}; agrupamentos={$invalidGroups}; permissoes={$permissionCount}; test_id_nullable={$testColumn}");
fwrite(STDOUT,"Workflow de notificações: estrutura, agrupamentos ativos e permissões OK.\n");
