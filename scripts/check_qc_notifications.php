<?php
declare(strict_types=1);
use BloodHub\Core\Database;
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string$class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});
$pdo=Database::connection();$fail=[];$ok=[];$check=static function(bool$condition,string$label)use(&$ok,&$fail):void{if($condition)$ok[]=$label;else$fail[]=$label;};
$table=static function(string$name)use($pdo):bool{$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:name');$q->execute(['name'=>$name]);return(bool)$q->fetchColumn();};
foreach(['qc_notifications','qc_notification_email_logs']as$t)$check($table($t),"tabela {$t}");
$q=$pdo->query("SHOW INDEX FROM qc_notifications WHERE Key_name='uk_qcn_result'");$check((bool)$q->fetch(),'idempotência por result_id');
$q=$pdo->query("SELECT permission_key FROM permissions WHERE permission_key IN ('notifications.view','notifications.view_all','notifications.acknowledge')");$check($q->rowCount()===3,'permissões específicas');
$q=$pdo->query("SELECT COUNT(*) FROM roles r JOIN role_permissions rp ON rp.role_id=r.id JOIN permissions p ON p.id=rp.permission_id WHERE r.slug='processamento' AND p.permission_key='notifications.acknowledge'");$check((int)$q->fetchColumn()>0,'processamento pode registrar ciência');
$q=$pdo->query("SELECT COUNT(*) FROM roles r JOIN role_permissions rp ON rp.role_id=r.id JOIN permissions p ON p.id=rp.permission_id WHERE r.slug='lcqh' AND p.permission_key='notifications.acknowledge'");$check((int)$q->fetchColumn()===0,'LCQH não registra ciência');
$q=$pdo->query("SELECT COUNT(*) FROM qc_notifications n LEFT JOIN test_result_spec_evaluations e ON e.test_result_id=n.result_id WHERE e.conformity_status<>'NONCONFORMING'");$check((int)$q->fetchColumn()===0,'somente não conformidades notificadas');
$q=$pdo->query("SELECT COUNT(*) FROM (SELECT result_id,COUNT(*) c FROM qc_notifications GROUP BY result_id HAVING c>1) d");$check((int)$q->fetchColumn()===0,'sem notificações duplicadas');
foreach($ok as$item)fwrite(STDOUT,"OK  {$item}\n");foreach($fail as$item)fwrite(STDERR,"FALHA  {$item}\n");exit($fail?1:0);
