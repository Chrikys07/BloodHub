<?php
declare(strict_types=1);

require dirname(__DIR__).'/src/Core/Database.php';
require dirname(__DIR__).'/src/Services/ShelfLifeResolver.php';

use BloodHub\Core\Database;
use BloodHub\Services\ShelfLifeResolver;

$pdo=Database::connection();$fail=[];
foreach(['preservatives','blood_component_preservative_shelf_lives','bag_brands','samples'] as $table){$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:name');$q->execute(['name'=>$table]);if(!$q->fetchColumn())$fail[]="Tabela ausente: {$table}";}
foreach(['preservative_id_snapshot','shelf_life_configuration_id_snapshot','shelf_life_days_snapshot','expiration_date'] as $column){$q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='samples' AND COLUMN_NAME=:name");$q->execute(['name'=>$column]);if(!$q->fetchColumn())$fail[]="Coluna ausente: samples.{$column}";}
if(ShelfLifeResolver::isExpired('2026-09-06','2026-09-06'))$fail[]='A amostra foi considerada vencida no próprio dia da validade.';
if(!ShelfLifeResolver::isExpired('2026-09-05','2026-09-06'))$fail[]='A amostra anterior à data de recebimento não foi considerada vencida.';
$duplicates=$pdo->query('SELECT COUNT(*) FROM (SELECT blood_component_id,preservative_id,COUNT(*) n FROM blood_component_preservative_shelf_lives GROUP BY blood_component_id,preservative_id HAVING n>1) x')->fetchColumn();if($duplicates)$fail[]='Existem regras duplicadas.';
if($fail){fwrite(STDERR,implode(PHP_EOL,$fail).PHP_EOL);exit(1);}echo "Validade por preservante: estrutura e semântica de datas válidas.".PHP_EOL;
