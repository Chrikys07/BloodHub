<?php
declare(strict_types=1);

spl_autoload_register(static function(string $class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file;});

use BloodHub\Services\MeasurementFormatter;

$cases=[
    [46600000000,'4,66 × 10¹⁰'],
    [55000000000,'5,5 × 10¹⁰'],
    [102000000000,'1,02 × 10¹¹'],
    [5290000,'5,29 × 10⁶'],
    [10000000,'1 × 10⁷'],
    [0,'0'],
    [null,''],
    [-5290000,'-5,29 × 10⁶'],
];
$failures=[];
foreach($cases as [$value,$expected])if(($actual=MeasurementFormatter::formatScientific($value))!==$expected)$failures[]="{$value}: esperado '{$expected}', obtido '{$actual}'";
if(MeasurementFormatter::formatDecimalMeasurement(39.4610221,1)!=='39,5')$failures[]='Volume 39.4610221 não foi exibido como 39,5.';
if(MeasurementFormatter::formatDecimalMeasurement(40,1)!=='40,0')$failures[]='Volume inteiro não manteve uma casa decimal.';
$volumeSpec=['rule_type'=>'BETWEEN','expected_min'=>'40.0000000000','expected_max'=>'70.0000000000','unit'=>'mL'];
if(MeasurementFormatter::formatSpecification('VOLUME',$volumeSpec)!=='40,0 a 70,0 mL')$failures[]='Faixa de volume formatada incorretamente.';
$plateletSpec=['rule_type'=>'GTE','expected_min'=>'55000000000.0000000000','expected_max'=>null,'unit'=>'/U'];
if(MeasurementFormatter::formatSpecification('PLATELETS_PER_UNIT',$plateletSpec)!=='≥ 5,5 × 10¹⁰ /U')$failures[]='Especificação científica formatada incorretamente.';
if($failures){foreach($failures as $failure)fwrite(STDERR,"FALHA: {$failure}\n");exit(1);}echo "OK: formatação científica, volume e especificações.\n";
