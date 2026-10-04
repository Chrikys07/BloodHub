<?php
declare(strict_types=1);
use BloodHub\Core\Database;
use BloodHub\Services\FactorViiiPoolService;
if(PHP_SAPI!=='cli')exit(1);
spl_autoload_register(static function(string $class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});
$pdo=Database::connection();$fail=static function(string $m):never{fwrite(STDERR,"FALHA: {$m}\n");exit(1);};
if(FactorViiiPoolService::FVIII_POOL_MAX_SIZE!==4)$fail('tamanho máximo não centralizado em 4');
foreach(['factor_viii_pools','factor_viii_pool_samples','factor_viii_sample_results'] as $table){$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');$q->execute(['table'=>$table]);if(!(int)$q->fetchColumn())$fail("tabela {$table} ausente");}
$q=$pdo->query("SELECT COUNT(*) FROM tests WHERE code='FACTOR_VIII' AND unit='UI/mL' AND status='active'");if(!(int)$q->fetchColumn())$fail('teste FACTOR_VIII ausente');
$q=$pdo->query("SELECT bc.code,COUNT(*) total FROM test_blood_components x JOIN tests t ON t.id=x.test_id AND t.code IN ('VOLUME','FACTOR_VIII') JOIN blood_components bc ON bc.id=x.blood_component_id AND bc.code IN ('PFC','PFC24') GROUP BY bc.code");$configured=[];foreach($q as $r)$configured[$r['code']]=(int)$r['total'];foreach(['PFC','PFC24'] as $code)if(($configured[$code]??0)!==2)$fail("Volume/FVIII não configurados para {$code}");
$q=$pdo->query("SELECT COUNT(*) FROM factor_viii_pool_samples m JOIN factor_viii_pools p ON p.id=m.pool_id GROUP BY m.sample_id HAVING SUM(p.status='open')>1 LIMIT 1");if($q->fetchColumn())$fail('sample em mais de um pool aberto');
$q=$pdo->query('SELECT COUNT(*) FROM factor_viii_pool_samples GROUP BY pool_id HAVING COUNT(*)>4 LIMIT 1');if($q->fetchColumn())$fail('pool acima de quatro unidades');
$q=$pdo->query('SELECT COUNT(*) FROM factor_viii_pool_samples m JOIN samples s ON s.id=m.sample_id JOIN factor_viii_pools p ON p.id=m.pool_id WHERE s.blood_component_id<>p.blood_component_id OR s.origin_unit_id<>p.origin_unit_id');if((int)$q->fetchColumn())$fail('pool histórico misto');
fwrite(STDOUT,"OK: modelagem, configuração, limite e integridade dos pools de Fator VIII.\n");
