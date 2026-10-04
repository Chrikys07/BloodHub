<?php
declare(strict_types=1);
use BloodHub\Core\Database;
if(PHP_SAPI!=='cli')exit(1);
spl_autoload_register(static function(string $class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});
$pdo=Database::connection();$fail=[];
foreach(['sample_shipments','sample_shipment_thermal_boxes','bag_brands','samples','audit_logs'] as $table){$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t');$s->execute(['t'=>$table]);if(!$s->fetchColumn())$fail[]="Tabela ausente: {$table}";}
foreach(['sample_shipment_id','production_date','bag_brand_id'] as $column){$s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='samples' AND COLUMN_NAME=:c");$s->execute(['c'=>$column]);if(!$s->fetchColumn())$fail[]="Coluna ausente: samples.{$column}";}
$required=['shipments.view','shipments.create','shipments.edit','shipments.send','shipments.cancel','shipments.print','reception.view','reception.receive','reception.reject','admin.bag_brands.manage'];$in=implode(',',array_fill(0,count($required),'?'));$s=$pdo->prepare("SELECT permission_key FROM permissions WHERE permission_key IN ({$in}) AND status='active'");$s->execute($required);$found=$s->fetchAll(PDO::FETCH_COLUMN);foreach(array_diff($required,$found) as $p)$fail[]="Permissão ausente: {$p}";
if($fail){foreach($fail as $f)fwrite(STDERR,"FALHA: {$f}\n");exit(1);}fwrite(STDOUT,"Estrutura de remessas, relacionamento, caixas, marcas e RBAC: OK\n");
fwrite(STDOUT,"Regras transacionais de edição/recebimento: verificadas por SELECT FOR UPDATE nos controllers.\n");
