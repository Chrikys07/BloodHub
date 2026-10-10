<?php
declare(strict_types=1);

use BloodHub\Core\{Auth,Database,ShipmentAccess};

if (PHP_SAPI !== 'cli') exit(1);
spl_autoload_register(static function (string $class): void {
    $prefix='BloodHub\\';
    if (!str_starts_with($class,$prefix)) return;
    $file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if (is_file($file)) require $file;
});

$pdo=Database::connection();
$shipmentId=(int)($argv[1]??24);
$shipment=$pdo->prepare('SELECT * FROM sample_shipments WHERE id=:id');
$shipment->execute(['id'=>$shipmentId]);
$shipment=$shipment->fetch(PDO::FETCH_ASSOC);
if (!$shipment) {
    fwrite(STDERR,"FALHA: remessa {$shipmentId} não existe.\n");
    exit(1);
}

$userByEmail=static function (string $email) use ($pdo): array {
    $query=$pdo->prepare('SELECT u.id,u.name,u.email,u.client_id,u.role_id,r.name role_name,r.slug role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.email=:email LIMIT 1');
    $query->execute(['email'=>$email]);
    return $query->fetch(PDO::FETCH_ASSOC)?:[];
};
$canView=static function (array $user,int $id) use ($pdo): bool {
    $_SESSION['user']=$user;
    [$scope,$params]=ShipmentAccess::scopeSql('sh');
    $query=$pdo->prepare("SELECT 1 FROM sample_shipments sh WHERE sh.id=:id AND {$scope}");
    $query->execute(['id'=>$id]+$params);
    return (bool)$query->fetchColumn();
};
$failures=[];
$assert=static function (bool $condition,string $message) use (&$failures): void {
    if (!$condition) $failures[]=$message;
};

$sara=$userByEmail('sara.costa@colsan.org.br');
$admin=$userByEmail('cristiano.costa@colsan.org.br');
$lcqh=$userByEmail('gestao.processos@colsan.org.br');
$otherProcessing=$userByEmail('thaina.sacramento@colsan.org.br');
$assert($sara!==[]&&$canView($sara,$shipmentId),'Sara/Processamento não acessou a remessa enviada.');
$assert($canView($sara,$shipmentId),'Sara não manteve acesso em uma segunda consulta.');
$assert($admin!==[]&&$canView($admin,$shipmentId),'Administrador não acessou a remessa.');
$assert($lcqh!==[]&&$canView($lcqh,$shipmentId),'LCQH destinatário não acessou a remessa.');
$assert($otherProcessing!==[]&&!$canView($otherProcessing,$shipmentId),'Processamento sem vínculo direto e que não criou/enviou a remessa obteve acesso.');
$unrelated=['id'=>999999,'client_id'=>999999,'role_id'=>$sara['role_id'],'role_name'=>$sara['role_name'],'role_slug'=>'processamento'];
$assert(!$canView($unrelated,$shipmentId),'Usuário sem relação com a remessa obteve acesso.');
$_SESSION['user']=$sara;
[$scopeSql]=ShipmentAccess::scopeSql('sh');
$assert(stripos($scopeSql,'sh.status')===false,'O status da remessa está alterando o escopo de visualização.');
$_GET['sent']='1';
$withSent=$canView($sara,$shipmentId);
unset($_GET['sent']);
$assert($withSent,'sent=1 alterou indevidamente o escopo da remessa.');

if ($failures) {
    foreach ($failures as $failure) fwrite(STDERR,"FALHA: {$failure}\n");
    exit(1);
}
fwrite(STDOUT,"Escopo da remessa {$shipmentId}: Sara, reabertura, administrador, LCQH, bloqueios e sent=1 OK.\n");
