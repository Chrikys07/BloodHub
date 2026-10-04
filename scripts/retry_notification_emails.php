<?php
declare(strict_types=1);
use BloodHub\Core\Database;
use BloodHub\Services\QcNotificationService;
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string$class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});
$apply=in_array('--apply',$argv,true);$pdo=Database::connection();
$rows=$pdo->query("SELECT l.id,l.notification_id,n.public_code,l.recipient,l.status,l.attempted_at,l.error_message FROM qc_notification_email_logs l JOIN qc_notifications n ON n.id=l.notification_id WHERE l.status IN ('PENDING','FAILED') ORDER BY l.attempted_at,l.id")->fetchAll(PDO::FETCH_ASSOC);
if(!$rows){fwrite(STDOUT,"Nenhum envio pendente ou falho.\n");exit(0);}
foreach($rows as$row){fwrite(STDOUT,sprintf("#%d | %s | %s | %s | %s | %s\n",$row['id'],$row['public_code'],$row['recipient'],$row['status'],$row['attempted_at'],$row['error_message']??''));}
if(!$apply){fwrite(STDOUT,"\nSomente listagem. Use --apply para reenviar.\n");exit(0);}
$ids=array_values(array_unique(array_map(static fn(array$row):int=>(int)$row['notification_id'],$rows)));$failed=0;
foreach($ids as$id){try{QcNotificationService::deliver($id);}catch(Throwable$e){$failed++;fwrite(STDERR,"Notificação #{$id}: {$e->getMessage()}\n");}}
$remaining=(int)$pdo->query("SELECT COUNT(*) FROM qc_notification_email_logs WHERE status IN ('PENDING','FAILED')")->fetchColumn();
fwrite(STDOUT,"Reenvio concluído. Pendentes/falhos restantes: {$remaining}.\n");exit(($failed||$remaining)?1:0);
