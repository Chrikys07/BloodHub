<?php
declare(strict_types=1);
use BloodHub\Services\CpafYieldClassificationService;
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string $class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file;});
$cases=[[2.9e11,'INSUFFICIENT_YIELD'],[3.0e11,'CPAF_SIMPLE'],[5.99e11,'CPAF_SIMPLE'],[6.0e11,'CPAF_DOUBLE'],[8.0e11,'CPAF_DOUBLE']];$failures=[];
foreach($cases as [$value,$expected]){$actual=CpafYieldClassificationService::classify($value,3.0e11,6.0e11)['code'];if($actual!==$expected)$failures[]="{$value}: esperado {$expected}, obtido {$actual}";}
if($failures){fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL);exit(1);}fwrite(STDOUT,"Classificacao CPAF: 5 limites validados.\n");
