<?php
declare(strict_types=1);
use BloodHub\Services\Mailer;
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string$class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});
$recipient=trim((string)($argv[1]??''));
if(!filter_var($recipient,FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"Uso: php scripts/test_email.php destinatario@dominio.com.br\n");exit(2);}
$subject='BloodHub | Teste de envio';$text='Este é um teste de configuração de e-mail do BloodHub.';$html='<html><body><p>'.$text.'</p></body></html>';
try{(new Mailer())->send($recipient,$subject,$html,$text);fwrite(STDOUT,"E-mail de teste enviado com sucesso para {$recipient}.\n");exit(0);}catch(Throwable$e){fwrite(STDERR,"Falha no envio: {$e->getMessage()}\n");exit(1);}
