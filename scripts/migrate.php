<?php
declare(strict_types=1);
use BloodHub\Core\Database;
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string $class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});
$files=array_slice($argv,1);if(!$files){fwrite(STDERR,"Uso: php scripts/migrate.php database/migrations/arquivo.sql\n");exit(1);}
$pdo=Database::connection();
foreach($files as $file){$real=realpath($file);$root=realpath(dirname(__DIR__).'/database/migrations');if(!$real||!$root||!str_starts_with($real,$root.DIRECTORY_SEPARATOR)){fwrite(STDERR,"Migration inválida: {$file}\n");exit(1);}$sql=file_get_contents($real);if($sql===false)exit(1);try{$pdo->exec($sql);fwrite(STDOUT,basename($real)." aplicada.\n");}catch(Throwable $e){fwrite(STDERR,"Erro em ".basename($real).": ".$e->getMessage()."\n");exit(1);}}
