<?php
declare(strict_types=1);
use BloodHub\Core\Database;
use BloodHub\Services\IndicatorService;
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string$class):void{$p='BloodHub\\';if(!str_starts_with($class,$p))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($p))).'.php';if(is_file($file))require$file;});
function check(bool$condition,string$message):void{if(!$condition)throw new RuntimeException($message);echo"OK - $message\n";}
$pdo=Database::connection();$items=IndicatorService::all(true);check(count($items)===2,'seed idempotente contém os dois indicadores');
$byCode=[];foreach($items as$i)$byCode[$i['code']]=IndicatorService::getIndicator((int)$i['id']);
check(isset($byCode['HEMOCOMPONENTS_WITHIN_SPECIFICATIONS'],$byCode['PLATELET_MEAN_CONCENTRATION']),'codes institucionais cadastrados');
check(($byCode['PLATELET_MEAN_CONCENTRATION']['configured_test_code']??null)==='PLATELETS_PER_UNIT','Plaquetas/U resolvido pelo catálogo, sem usar input intermediário');
$target=IndicatorService::getTargetForPeriod((int)$byCode['HEMOCOMPONENTS_WITHIN_SPECIFICATIONS']['id'],2026,7);check($target!==null&&(float)$target['target_value']===90.0,'meta histórica vigente recuperada por período');
check(IndicatorService::compare(92.0,$target)&&!IndicatorService::compare(88.0,$target),'operador da meta aplicado ao resultado');
foreach($byCode as$code=>$indicator){$data=IndicatorService::dashboard($indicator,2026,9);check(count($data['months'])===12,"$code retorna série Jan-Dez");check(array_key_exists('value',$data['month']),"$code retorna resultado mensal");}
check((int)$pdo->query("SELECT COUNT(*) FROM permissions WHERE permission_key LIKE 'indicators.%'")->fetchColumn()===3,'três permissões RBAC cadastradas');
echo"Indicadores verificados com sucesso.\n";
