<?php
declare(strict_types=1);

use BloodHub\Core\Database;
use BloodHub\Services\SimplePdf;

if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string $class):void{
    $prefix='BloodHub\\';
    if(!str_starts_with($class,$prefix))return;
    $file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($file))require $file;
});

$assert=static function(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);};
$root=dirname(__DIR__);
foreach(['bloodhub-logo-horizontal.png','colsan.png']as$file){
    $path=$root.'/public/assets/images/'.$file;
    $assert(is_file($path),'Asset ausente: '.$file);
    $dimensions=getimagesize($path);
    $assert($dimensions!==false&&$dimensions[0]>0&&$dimensions[1]>0,'Asset inválido: '.$file);
}

$rows=[
 ['parameter'=>'Leucócitos/U','result'=>'1,2 x 10³','reference'=>'Até 5,0 x 10³ por unidade','method_equipment'=>'Contagem automatizada'],
 ['parameter'=>'pH','result'=>'7,1','reference'=>'Maior ou igual a 6,4','method_equipment'=>'Potenciometria'],
 ['parameter'=>'Plaquetas/U','result'=>'6,5 x 10¹⁰','reference'=>'Maior ou igual a 5,5 x 10¹⁰','method_equipment'=>'Contagem automatizada'],
 ['parameter'=>'Swirling','result'=>'Presente','reference'=>'Presente','method_equipment'=>'Inspeção visual'],
 ['parameter'=>'Volume','result'=>'250 mL','reference'=>'200 a 300 mL','method_equipment'=>'Gravimetria'],
 ['parameter'=>'Bacteriológico','result'=>'Negativo','reference'=>'Resultado bacteriológico final','method_equipment'=>'Método configurável'],
];
$pdf=new SimplePdf('Validação de laudo');
$pdf->heading('LAUDO DE HEMOCOMPONENTE - VERSÃO 1');
$pdf->results($rows);
$binary=$pdf->output();
$assert(str_starts_with($binary,'%PDF-1.4'),'Cabeçalho PDF inválido.');
$assert(str_ends_with($binary,'%%EOF'),'PDF incompleto.');
$assert(str_contains($binary,'/ImBloodHub Do'),'Logo BloodHub não foi incorporado.');
$assert(str_contains($binary,'/ImColsan Do'),'Logo COLSAN não foi incorporado.');
$footer=iconv('UTF-8','windows-1252','BloodHub - Laudo eletrônico | Página 1 de 1');
$assert($footer!==false&&str_contains($binary,$footer),'Rodapé não está codificado em WinAnsi/Windows-1252.');
$bacteriologyLine=iconv('UTF-8','windows-1252','bacteriológico final');
$assert(str_contains($binary,'(Resultado) Tj')&&$bacteriologyLine!==false&&str_contains($binary,$bacteriologyLine),'Quebra multilinha de bacteriologia ausente.');

$keys=['report_eligibility_external_quality_control','report_eligibility_external_transfusion_reaction','report_eligibility_internal_quality_control','report_eligibility_internal_transfusion_reaction'];
$placeholders=implode(',',array_fill(0,count($keys),'?'));
$query=Database::connection()->prepare("SELECT setting_key,setting_value FROM system_settings WHERE setting_key IN ($placeholders)");
$query->execute($keys);
$settings=$query->fetchAll(PDO::FETCH_KEY_PAIR);
$assert(count($settings)===4,'As quatro regras de elegibilidade precisam estar persistidas.');
foreach($keys as$key)$assert(in_array((string)$settings[$key],['0','1'],true),'Valor inválido para '.$key);

fwrite(STDOUT,"OK: logos locais, tabela dinâmica, encoding e quatro regras persistidas.\n");
