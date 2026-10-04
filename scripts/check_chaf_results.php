<?php
declare(strict_types=1);

spl_autoload_register(static function(string $class):void{
    $prefix='BloodHub\\';
    if(!str_starts_with($class,$prefix))return;
    $file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($file))require $file;
});

use BloodHub\Services\RedCellResidualLeukocyteCalculator as Calculator;
use BloodHub\Services\RedCellResidualLeukocyteService as Service;

$assert=static function(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);};
$assert(Service::supports('CHF'),'CHF deve continuar suportado.');
$assert(Service::supports('CHAF'),'CHAF deve usar o renderer residual.');
$assert(!Service::supports('CH'),'CH nao deve ganhar o bloco residual.');
$assert(!Service::supports('CHL'),'CHL nao deve ganhar o bloco residual.');
$assert(abs((float)Calculator::calculate('1,25',250)-62500)<1e-9,'Calculo Nageotte pt-BR invalido.');
$assert(abs((float)Calculator::calculate('1.25',250)-62500)<1e-9,'Calculo Nageotte decimal invalido.');

echo "CHAF: renderer compartilhado e calculo residual validados.\n";
