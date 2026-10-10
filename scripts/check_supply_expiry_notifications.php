<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
spl_autoload_register(static function(string $class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});

use BloodHub\Services\SupplyExpiryNotificationService;

try{$result=SupplyExpiryNotificationService::run();fwrite(STDOUT,sprintf("Lotes verificados: %d; notificações criadas: %d; destinatários elegíveis: %d; faixas: %s dias e vencido.\n",$result['checked'],$result['created'],$result['recipients'],implode(', ',$result['thresholds'])));exit(0);}catch(Throwable$e){fwrite(STDERR,"Falha ao verificar validade dos insumos: {$e->getMessage()}\n");exit(1);}
