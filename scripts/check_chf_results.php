<?php
declare(strict_types=1);
use BloodHub\Services\RedCellResidualLeukocyteCalculator;
spl_autoload_register(static function(string $class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file;});
$volume=280.25;$count=3.75;$expected=($count*10*1000*$volume)/50;
$actual=RedCellResidualLeukocyteCalculator::calculate($count,$volume);
if(abs((float)$actual-$expected)>1e-9){fwrite(STDERR,"FALHA: fórmula CHF/Nageotte divergente.\n");exit(1);}
if(RedCellResidualLeukocyteCalculator::calculate(null,$volume)!==null){fwrite(STDERR,"FALHA: parcial não preservado.\n");exit(1);}
fwrite(STDOUT,"OK: fórmula CHF do FC0538 e salvamento parcial conferidos.\n");
