<?php
declare(strict_types=1);
spl_autoload_register(static function(string $class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file;});
use BloodHub\Services\FreshPlasmaResultCalculator;
$cases=[['platelets_per_ml','platelet_count',12,12000000.0],['platelets_per_ml','platelet_count','12,5',12500000.0],['leukocytes_per_ml','leukocyte_count',4,160.0],['red_cells_per_ml','red_cell_count',8,400000.0]];$failures=[];
foreach($cases as [$key,$countKey,$count,$expected]){$input=['platelet_method'=>'Neubauer','leukocyte_method'=>'Nageotte','red_cell_method'=>'Neubauer',$countKey=>$count];$actual=FreshPlasmaResultCalculator::calculate($input)[$key];if($actual!==$expected)$failures[]="{$key}: esperado {$expected}, obtido ".var_export($actual,true);}
$zero=FreshPlasmaResultCalculator::calculate(['platelet_method'=>'Neubauer','platelet_count'=>0]);if($zero['platelets_per_ml']!==0.0)$failures[]='Zero não foi preservado.';
$empty=FreshPlasmaResultCalculator::calculate([]);if($empty['platelets_per_ml']!==null||$empty['leukocytes_per_ml']!==null||$empty['red_cells_per_ml']!==null)$failures[]='Campo vazio não permaneceu nulo.';
if($failures){foreach($failures as $failure)fwrite(STDERR,"FALHA: {$failure}\n");exit(1);}echo "OK: cálculos de PF/PF24, decimal pt-BR, vazio e zero.\n";
