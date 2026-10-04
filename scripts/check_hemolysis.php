<?php
declare(strict_types=1);
use BloodHub\Services\{HemolysisCalculator,HemolysisSpreadsheetReader};
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string $class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file;});
function assertNear(float $actual,float $expected,string $label):void{if(abs($actual-$expected)>1e-12)throw new RuntimeException("{$label}: {$actual} != {$expected}");}
$low=HemolysisCalculator::calculate(['abs_370'=>'0,4363','abs_415'=>'0,6901','abs_510'=>'0,1944','abs_577'=>'0,1663','abs_600'=>'0,1146']);
$high=HemolysisCalculator::calculate(['abs_370'=>'0.7018','abs_415'=>'1.2817','abs_510'=>'0.2919','abs_577'=>'0.2652','abs_600'=>'0.1755']);
assertNear($low['x_value'],0.358892,'LOW X');assertNear($low['y_value'],0.331208,'LOW Y');assertNear($low['free_hemoglobin_g_dl'],($low['y_value']/113.22*16125)/1000,'LOW Hb');
assertNear($high['x_value'],0.205764,'HIGH X');assertNear($high['y_value'],0.059436,'HIGH Y');assertNear($high['free_hemoglobin_g_dl'],($high['y_value']/13.64*16125)/1000,'HIGH Hb');
if($low['formula_branch']!=='LOW_415'||$high['formula_branch']!=='HIGH_415')throw new RuntimeException('Seleção de ramo incorreta.');
$tmp=tempnam(sys_get_temp_dir(),'bh-h-').'.xlsx';$zip=new ZipArchive();$zip->open($tmp,ZipArchive::CREATE);$zip->addFromString('xl/worksheets/sheet1.xml','<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Instrument</t></is></c></row><row r="7">'.cells(['No.','Sample Name','Abs(370nm)','Abs(415nm)','Abs(510nm)','Abs(577nm)','Abs(600nm)','Status']).'</row><row r="8">'.cells(['1','CH','','','','','','Unmeasured']).'</row><row r="9">'.cells(['2','01261200679','0,4363','0,6901','0,1944','0,1663','0,1146','Measured']).'</row></sheetData></worksheet>');$zip->close();$parsed=HemolysisSpreadsheetReader::read($tmp,'xlsx');unlink($tmp);if(count($parsed['rows'])!==1||$parsed['ignored']!==1||$parsed['rows'][0]['sample_name']!=='01261200679')throw new RuntimeException('Parser XLSX não respeitou cabeçalho dinâmico/grupo.');
echo "Hemólise: fórmulas LOW/HIGH, vírgula decimal e parser XLSX validados.\n";
function cells(array $values):string{$out='';foreach($values as $i=>$v){$col=chr(65+$i);$out.='<c r="'.$col.'1" t="inlineStr"><is><t>'.htmlspecialchars($v,ENT_XML1).'</t></is></c>';}return $out;}
