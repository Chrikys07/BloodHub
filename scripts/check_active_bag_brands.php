<?php
declare(strict_types=1);

use BloodHub\Core\Database;
use BloodHub\Services\BagTareResolver;

if(PHP_SAPI!=='cli'){http_response_code(403);exit("Somente CLI.\n");}
spl_autoload_register(static function(string $class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file;});

$pdo=Database::connection();$fail=[];
$duplicates=$pdo->query("SELECT LOWER(TRIM(name)) name_key,preservative_id,COUNT(*) qty FROM bag_brands WHERE active=1 GROUP BY LOWER(TRIM(name)),preservative_id HAVING COUNT(*)>1")->fetchAll();
if($duplicates)$fail[]='Existem combinações Marca + Preservante ativas duplicadas: '.json_encode($duplicates,JSON_UNESCAPED_UNICODE);
$referenceUnique=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bag_brands' AND INDEX_NAME='uk_bag_brands_name_reference'")->fetchColumn();
if($referenceUnique)$fail[]='A referência ainda participa de um índice único.';
$column=$pdo->query("SELECT GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bag_brands' AND COLUMN_NAME='active_brand_key'")->fetchColumn();
if(!$column||stripos((string)$column,'preservative_id')===false)$fail[]='A chave ativa não representa Marca + Preservante.';
$historical=$pdo->query("SELECT s.bag_brand_id,s.blood_component_id FROM samples s JOIN bag_brands b ON b.id=s.bag_brand_id JOIN bag_brand_tares t ON t.bag_brand_id=b.id AND t.active=1 JOIN bag_brand_tare_components c ON c.bag_brand_tare_id=t.id AND c.blood_component_id=s.blood_component_id WHERE b.active=0 LIMIT 1")->fetch();
if($historical&&!BagTareResolver::resolve((int)$historical['bag_brand_id'],(int)$historical['blood_component_id']))$fail[]='O resolvedor não preservou a tara de um cadastro histórico.';
if($fail){foreach($fail as $message)fwrite(STDERR,"FALHA: {$message}\n");exit(1);}echo "OK: Marca + Preservante, referência informativa e resolução histórica validadas.\n";
