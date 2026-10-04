<?php
declare(strict_types=1);
use BloodHub\Services\{CryoprecipitateResultService,SpecificationEvaluator};
spl_autoload_register(static function(string $class):void{$prefix='BloodHub\\';if(str_starts_with($class,$prefix))require dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';});

$root=dirname(__DIR__);$fail=[];$assert=static function(bool $ok,string $message)use(&$fail):void{if(!$ok)$fail[]=$message;};
$migration=file_get_contents($root.'/database/migrations/20260915_031_crio_fibrinogen_inputs.sql');
$view=file_get_contents($root.'/src/Views/quality_control/index.php');
$renderer=file_get_contents($root.'/src/Views/quality_control/partials/cryoprecipitate_results.php');
$controller=file_get_contents($root.'/src/Controllers/QualityControlController.php');
$required=file_get_contents($root.'/src/Services/TestResultService.php');
$service=file_get_contents($root.'/src/Services/CryoprecipitateResultService.php');

$assert(abs((float)CryoprecipitateResultService::calculateFibrinogenPerUnit(300,2,25)-150)<1e-12,'Fórmula FC0538 incorreta.');
$assert(CryoprecipitateResultService::calculateFibrinogenPerUnit(null,2,25)===null,'Entrada incompleta não pode gerar resultado.');
$assert(CryoprecipitateResultService::calculateFibrinogenPerUnit(300,null,25)===null,'Diluição ausente não pode gerar resultado.');
$between=['rule_type'=>'BETWEEN','min_value'=>10,'max_value'=>40];$gt=['rule_type'=>'GT','min_value'=>150,'max_value'=>null];
foreach([[9.5,false],[10,true],[40,true],[40.1,false]] as [$value,$expected])$assert(SpecificationEvaluator::evaluateRule($between,$value)===$expected,"Volume {$value} avaliado incorretamente.");
foreach([[149,false],[150,false],[151,true]] as [$value,$expected])$assert(SpecificationEvaluator::evaluateRule($gt,$value)===$expected,"Fibrinogênio {$value} avaliado incorretamente.");
$assert(str_contains($migration,'dilution')&&str_contains($migration,'fibrinogen_mg_dl')&&str_contains($migration,'fibrinogen_mg_u'),'Persistência específica do CRIO ausente.');
$assert(str_contains($renderer,'[dilution]')&&str_contains($renderer,'[fibrinogen_mg_dl]')&&!str_contains($renderer,'name="<?= $n ?>[fibrinogen]"'),'Renderer ainda aceita mg/U manual.');
$assert(substr_count($renderer,'<input readonly')===2&&str_contains($renderer,'data-crio-fibrinogen-mg-u'),'Campos derivados não estão protegidos.');
$assert(str_contains($service,'(($mgDl*$dilution)/100)*$volumeMl')&&str_contains($service,"TestResultService::CODES['fibrinogen']"),'Materialização do resultado normativo ausente.');
$assert(str_contains($controller,'CryoprecipitateResultService::save')&&str_contains($controller,'CryoprecipitateResultService::recalculate'),'Backend não recalcula em salvar/concluir.');
$assert(str_contains($view,'((mgDl*dilution)/100)*volume')&&str_contains($view,'[data-crio-dilution]'),'Recálculo no navegador ausente.');
$assert(str_contains($required,"'CRIO_DILUTION'")&&str_contains($required,"'CRIO_FIBRINOGEN_MG_DL'"),'Requisitos de conclusão incompletos.');
if($fail){foreach($fail as $message)fwrite(STDERR,"FALHA: {$message}\n");exit(1);}fwrite(STDOUT,"OK: CRIO usa entradas mg/dL/diluição e resultado normativo calculado em mg/U.\n");
