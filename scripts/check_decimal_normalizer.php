<?php
declare(strict_types=1);
use BloodHub\Services\DecimalNormalizer;
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/src/Services/DecimalNormalizer.php';
$cases=['350,0'=>350.0,'350.000'=>350.0,'1.234,500'=>1234.5,'1234.500'=>1234.5,'0,125'=>0.125];
foreach($cases as $raw=>$expected){$actual=DecimalNormalizer::parse($raw);if($actual===null||abs($actual-$expected)>0.000001){fwrite(STDERR,"FALHA: {$raw} resultou em ".var_export($actual,true)."\n");exit(1);}}
foreach(['1,2,3','12.3.4','peso'] as $raw)if(DecimalNormalizer::parse($raw)!==null){fwrite(STDERR,"FALHA: valor inválido aceito: {$raw}\n");exit(1);}
fwrite(STDOUT,"Normalização decimal de peso (SQL/HTML e pt-BR): OK\n");
