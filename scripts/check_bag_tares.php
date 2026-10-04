<?php
declare(strict_types=1);
use BloodHub\Core\Database;
use BloodHub\Services\BagTareResolver;
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string $class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});
$pdo=Database::connection();$failures=[];
$duplicates=$pdo->query('SELECT t.bag_brand_id,c.blood_component_id,COUNT(*) qty FROM bag_brand_tares t JOIN bag_brand_tare_components c ON c.bag_brand_tare_id=t.id WHERE t.active=1 GROUP BY t.bag_brand_id,c.blood_component_id HAVING COUNT(*)>1')->fetchAll();
if($duplicates)$failures[]='Há vínculos ativos ambíguos no banco.';
if((int)$pdo->query('SELECT COUNT(*) FROM bag_brand_tares WHERE tare_weight<=0')->fetchColumn())$failures[]='Há perfis com tara inválida.';
$links=$pdo->query('SELECT t.bag_brand_id,c.blood_component_id,t.tare_weight FROM bag_brand_tares t JOIN bag_brand_tare_components c ON c.bag_brand_tare_id=t.id JOIN bag_brands b ON b.id=t.bag_brand_id WHERE t.active=1 AND b.active=1')->fetchAll(PDO::FETCH_ASSOC);
foreach($links as $link){$resolved=BagTareResolver::resolve((int)$link['bag_brand_id'],(int)$link['blood_component_id']);if(!$resolved||number_format($resolved['tare_weight'],3)!==number_format((float)$link['tare_weight'],3)){$failures[]='Resolver retornou valor divergente.';break;}}
if($failures){foreach($failures as $failure)fwrite(STDERR,"FALHA: {$failure}\n");exit(1);}fwrite(STDOUT,'OK: estrutura sem ambiguidades; '.count($links)." vínculo(s) resolvido(s).\n");
