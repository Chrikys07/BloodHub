<?php
declare(strict_types=1);

use BloodHub\Controllers\ShipmentController;
use BloodHub\Core\Database;
use BloodHub\Services\InternalNotificationService;

if (PHP_SAPI !== 'cli') exit(1);
spl_autoload_register(static function (string $class): void {
    $prefix='BloodHub\\';
    if (!str_starts_with($class,$prefix)) return;
    $file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if (is_file($file)) require $file;
});

$pdo=Database::connection();
$fail=[];
$columns=$pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_notifications'")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('priority',$columns,true)) $fail[]='Migration 20261009_069: coluna priority ausente.';
$unique=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_notifications' AND INDEX_NAME='uk_user_notifications_event' AND NON_UNIQUE=0")->fetchColumn();
if (!$unique) $fail[]='Migration 20261009_069: índice único de deduplicação ausente.';

$shipment=$pdo->query("SELECT id,destination_unit_id FROM sample_shipments WHERE status IN ('awaiting_receipt','partially_received','received') ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$shipment) $fail[]='Nenhuma remessa enviada disponível para validar destinatários.';
if (!$fail) {
    $method=new ReflectionMethod(ShipmentController::class,'shipmentNotificationRecipients');
    $recipients=$method->invoke(null,(int)$shipment['destination_unit_id']);
    $marks=implode(',',array_fill(0,count($recipients),'?'));
    $q=$pdo->prepare("SELECT r.slug,COUNT(DISTINCT u.id) total FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id IN ($marks) GROUP BY r.slug");
    $q->execute($recipients);$roles=$q->fetchAll(PDO::FETCH_KEY_PAIR);
    if (empty($roles['administrador'])) $fail[]='Nenhum Administrador ativo foi selecionado.';
    if (empty($roles['lcqh'])) $fail[]='Nenhum LCQH contextual foi selecionado.';

    $pdo->beginTransaction();
    try {
        $event='shipment_sent:test_'.bin2hex(random_bytes(6));
        $first=InternalNotificationService::notifyUsers($recipients,$event,'Teste transacional','Validação sem persistência.',null,'sample_shipment',(int)$shipment['id']);
        $second=InternalNotificationService::notifyUsers($recipients,$event,'Teste transacional','Validação sem persistência.',null,'sample_shipment',(int)$shipment['id']);
        if ($first!==count($recipients)) $fail[]='A primeira criação não alcançou todos os destinatários.';
        if ($second!==0) $fail[]='A deduplicação não impediu a segunda criação.';
    } finally {$pdo->rollBack();}
}

if ($fail) {foreach ($fail as $message) fwrite(STDERR,"FALHA: $message\n");exit(1);}
fwrite(STDOUT,"Notificação de remessa: schema, Admin, LCQH contextual e deduplicação OK.\n");
