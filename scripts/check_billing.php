<?php
declare(strict_types=1);
use BloodHub\Core\Database;
use BloodHub\Services\{BillingService,FinalResultCatalog};
if(PHP_SAPI!=='cli')exit(1);spl_autoload_register(static function(string$c):void{$p='BloodHub\\';if(str_starts_with($c,$p)){$f=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($c,strlen($p))).'.php';if(is_file($f))require$f;}});
$pdo=Database::connection();$services=(int)$pdo->query('SELECT COUNT(*) FROM billing_services')->fetchColumn();if($services!==27)throw new RuntimeException("Esperados 27 serviços; encontrados $services.");
$matrix=BillingService::buildMatrix((int)date('Y'),(int)date('n'),false);if(count($matrix['rows'])!==(int)$pdo->query('SELECT COUNT(*) FROM billing_cost_centers WHERE active=1')->fetchColumn())throw new RuntimeException('A matriz não contém todos os centros ativos.');
foreach($matrix['rows']as$r)if(count($r['values'])!==count($matrix['services']))throw new RuntimeException('Matriz incompleta.');
$aux=(int)$pdo->query("SELECT COUNT(*) FROM tests WHERE is_final_result=0 AND id IN (SELECT test_id FROM billing_service_mappings WHERE active=1)")->fetchColumn();if($aux)throw new RuntimeException('Há mapping automático para teste auxiliar.');
$xml=BillingService::exportPdlab005((int)date('Y'),(int)date('n'));if(!str_contains($xml,'PDLAB005')||!str_contains($xml,'TOTAL'))throw new RuntimeException('Exportação inválida.');
$association=$pdo->query("SELECT m.id,t.id test_id,t.code,t.name,s.id service_id,s.service_code,s.name service_name,m.context_scope FROM billing_service_mappings m JOIN tests t ON t.id=m.test_id JOIN billing_services s ON s.id=m.billing_service_id WHERE t.code='BACTERIOLOGY' AND t.is_final_result=1 AND s.service_code='00561' AND m.active=1 AND FIND_IN_SET('transfusion_reaction',m.context_scope)>0")->fetchAll(PDO::FETCH_ASSOC);
if(count($association)!==1)throw new RuntimeException('A associação ativa BACTERIOLOGY + transfusion_reaction + 00561 deve ser única.');
$serviceId=(int)$association[0]['service_id'];$distribution=[];foreach($matrix['rows']as$row){$quantity=(int)$row['values'][$serviceId];if($quantity)$distribution[]=['center'=>$row['center']['cost_center_code'].' — '.$row['center']['name'],'quantity'=>$quantity];}
$rtPending=array_values(array_filter($matrix['unmapped'],static fn(array$row):bool=>$row['code']==='BACTERIOLOGY'&&$row['context_scope']==='transfusion_reaction'));
if($rtPending)throw new RuntimeException('Bacteriológico final de Reação Transfusional ainda aparece sem serviço associado.');
$csv=BillingService::exportCsv((int)date('Y'),(int)date('n'));if(!str_contains($csv,'00561 — Teste bacteriológico'))throw new RuntimeException('O CSV não contém o serviço 00561.');
echo "Faturamento: OK\nServiços: $services\nCentros: ".count($matrix['rows'])."\nResultados finais disponíveis: ".count(FinalResultCatalog::all())."\n";
echo 'Associação: test_id '.$association[0]['test_id'].' ('.$association[0]['code'].') -> '.$association[0]['service_code'].' '.$association[0]['service_name']." [Reação Transfusional]\n";
echo '00561 na competência: '.(int)$matrix['totals'][$serviceId]."\nDistribuição: ".json_encode($distribution,JSON_UNESCAPED_UNICODE)."\nPendência bacteriológica RT: não\n";
