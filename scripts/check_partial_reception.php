<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/database.php';
spl_autoload_register(function(string $class):void{$prefix='BloodHub\\';if(str_starts_with($class,$prefix))require dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';});

use BloodHub\Services\ReceptionService;

$cases=[
    'awaiting_receipt'=>array_fill(0,5,['status'=>'awaiting_receipt']),
    'partially_received'=>[['status'=>'received'],['status'=>'received'],['status'=>'rejected'],['status'=>'awaiting_receipt'],['status'=>'awaiting_receipt']],
    'received'=>[['status'=>'received'],['status'=>'rejected'],['status'=>'received']],
    'rejected'=>array_fill(0,5,['status'=>'rejected']),
];
foreach($cases as $expected=>$samples){$actual=ReceptionService::calculateShipmentStatus($samples);if($actual!==$expected){fwrite(STDERR,"Falha: esperado {$expected}, obtido {$actual}.\n");exit(1);}echo "OK: {$expected}\n";}
echo "Regras centrais do recebimento parcial: OK\n";
