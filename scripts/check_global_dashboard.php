<?php
declare(strict_types=1);
use BloodHub\Core\Database;
use BloodHub\Services\{GlobalDashboardService,SamplingScheduleService};
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string $class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});
$pdo=Database::connection();$admin=$pdo->query("SELECT u.id,u.role_id,r.slug role_slug,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='administrador' AND u.status='active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if(!$admin)throw new RuntimeException('Administrador ativo não encontrado para o teste de leitura.');$_SESSION['user']=$admin;
$filters=GlobalDashboardService::getFilters([]);if(!$filters['components'])throw new RuntimeException('Nenhum hemocomponente elegível.');
$dashboard=GlobalDashboardService::dashboard($filters,1,10);$required=['component','summary','series','testConformity','globalConformity','rows','attention'];foreach($required as$key)if(!array_key_exists($key,$dashboard))throw new RuntimeException("Estrutura ausente: {$key}");
if($dashboard['summary']['production_records']===0&&$dashboard['summary']['produced']!==null)throw new RuntimeException('Ausência de produção deve ser representada por null.');
if($dashboard['summary']['produced']===0&&$dashboard['summary']['sampling_percent']!==null)throw new RuntimeException('Produção zero não pode gerar divisão.');
foreach($dashboard['testConformity']as$test){if($test['evaluated']!==$test['conforming']+$test['nonconforming'])throw new RuntimeException('Denominador inconsistente em '.$test['name']);}
foreach($dashboard['rows']as$row){if($row['produced']===null&&$row['minimum']!==null)throw new RuntimeException('Mínimo calculado sem produção informada.');}
$expected=['CP'=>['BACTERIOLOGY','LEUKOCYTES_PER_UNIT','PH','PLATELETS_PER_UNIT','SWIRLING','VOLUME'],'PF'=>['LEUKOCYTES_PER_ML','PLATELETS_PER_ML','REDBLOODCELLS_PER_ML'],'CH'=>['BACTERIOLOGY','HEMATOCRIT','HEMOGLOBIN_PER_UNIT','HEMOLYSIS_DEGREE'],'CHL'=>['BACTERIOLOGY','HEMATOCRIT','HEMOGLOBIN_PER_UNIT','HEMOLYSIS_DEGREE','RECOVERY','RESIDUAL_PROTEIN','VOLUME'],'CRIO'=>['FIBRINOGEN','VOLUME']];
foreach($expected as$componentCode=>$codes){$q=$pdo->prepare('SELECT id FROM blood_components WHERE code=:code');$q->execute(['code'=>$componentCode]);$componentId=(int)$q->fetchColumn();$filters=GlobalDashboardService::getFilters(['blood_component_id'=>$componentId,'year'=>2026,'month'=>9]);$check=GlobalDashboardService::dashboard($filters,1,100);$actual=array_column($check['testCatalog'],'code');sort($actual);sort($codes);if($actual!==$codes)throw new RuntimeException("Catálogo final incorreto para {$componentCode}: ".implode(',',$actual));foreach($check['testConformity']as$t){if(!array_key_exists('target',$t)||!isset($t['target_status']))throw new RuntimeException('Metadados de meta ausentes em '.$t['name']);}}
$status=new ReflectionMethod(GlobalDashboardService::class,'targetStatus');foreach([[[97.0,95.0],'met'],[[94.0,95.0],'below'],[[87.0,null],'no_target'],[[null,95.0],'no_results']]as[$args,$want])if($status->invoke(null,...$args)!==$want)throw new RuntimeException('Semântica de meta incorreta para '.json_encode($args));
echo "Dashboard Global: OK\n";echo 'Hemocomponente: '.$dashboard['component']['code']."\n";echo 'Linhas agregadas: '.$dashboard['total']."\n";echo 'Testes dinâmicos: '.count($dashboard['testCatalog'])."\n";
