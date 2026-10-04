<?php
declare(strict_types=1);
use BloodHub\Core\Database;
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string$class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});
$pdo=Database::connection();$fail=[];
$table=$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='administrative_corrections'")->fetchColumn();if(!$table)$fail[]='Tabela administrative_corrections ausente.';
$columns=$pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='samples'")->fetchAll(PDO::FETCH_COLUMN);foreach(['cancelled_by','cancelled_at','cancellation_reason']as$c)if(!in_array($c,$columns,true))$fail[]="Coluna samples.$c ausente.";
$q=$pdo->query("SELECT r.slug FROM roles r JOIN role_permissions rp ON rp.role_id=r.id JOIN permissions p ON p.id=rp.permission_id WHERE p.permission_key='admin_corrections.manage'");$roles=$q->fetchAll(PDO::FETCH_COLUMN);if($roles!==['administrador'])$fail[]='Permissão de correção deve pertencer somente ao administrador: '.implode(',',$roles);
$files=['src/Services/AdministrativeCorrectionService.php','src/Controllers/Admin/CorrectionController.php','src/Views/admin/corrections/index.php','src/Views/admin/corrections/show.php'];foreach($files as$f){$text=file_get_contents(dirname(__DIR__).'/'.$f);if(preg_match('/DELETE\s+FROM\s+(samples|test_results)/i',$text))$fail[]="Exclusão física encontrada em $f.";}
if($fail){fwrite(STDERR,"FALHA\n- ".implode("\n- ",$fail)."\n");exit(1);}fwrite(STDOUT,"OK: schema, RBAC exclusivo, cancelamento lógico e ausência de DELETE físico verificados.\n");
