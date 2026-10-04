<?php
declare(strict_types=1);

use BloodHub\Core\Database;
use BloodHub\Services\ChatService;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
spl_autoload_register(static function(string $class):void {
    $prefix='BloodHub\\'; if(!str_starts_with($class,$prefix))return;
    $file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($file))require $file;
});

try {
    $pdo=Database::connection();
    $user=$pdo->query("SELECT u.id,u.name,u.email,u.photo_path,u.status,r.id role_id,r.name role_name,r.slug role_slug FROM users u LEFT JOIN roles r ON r.id=u.role_id WHERE u.status='active' ORDER BY u.id LIMIT 1")->fetch();
    if(!$user)throw new RuntimeException('Nenhum usuario ativo para o teste.');
    $service=new ChatService();
    $conversations=$service->conversations((int)$user['id']);
    $general=array_values(array_filter($conversations,static fn(array $c):bool=>$c['type']==='general'));
    if(count($general)!==1)throw new RuntimeException('A conversa Geral nao esta unica ou acessivel.');
    $service->messages((int)$general[0]['id'],(int)$user['id']);
    $service->unreadCount((int)$user['id']);
    $_SESSION['user']=$user;
    $pageTitle='HubChat';$pageSubtitle='Comunicação interna';$userAuth=$user;$flash=[];$csrf='test-token';
    ob_start();require dirname(__DIR__).'/src/Views/chat/index.php';$html=(string)ob_get_clean();
    foreach(['HubChat','Comunicação interna','id="chat-app"','class="chat-sidebar"','id="chat-conversations"','id="chat-new"','id="chat-users"','id="chat-users-list"','id="chat-messages"','/assets/css/chat.css','/assets/js/chat.js'] as $marker)if(!str_contains($html,$marker))throw new RuntimeException('Renderizacao incompleta: '.$marker);
    foreach($service->activeUsers((int)$user['id']) as $availableUser){
        if((int)$availableUser['id']===(int)$user['id'])throw new RuntimeException('O usuario atual apareceu na lista de usuarios ativos.');
    }
    $duplicatePrivate=(int)$pdo->query("SELECT COUNT(*) FROM (SELECT private_key FROM chat_conversations WHERE type='private' GROUP BY private_key HAVING COUNT(*)>1) duplicates")->fetchColumn();
    if($duplicatePrivate!==0)throw new RuntimeException('Existem conversas privadas duplicadas.');
    $duplicateGroups=(int)$pdo->query("SELECT COUNT(*) FROM (SELECT unit_id FROM chat_conversations WHERE type='group' GROUP BY unit_id HAVING COUNT(*)>1) duplicates")->fetchColumn();
    if($duplicateGroups!==0)throw new RuntimeException('Existem grupos de unidade duplicados.');
    $invalidNaturalParticipants=(int)$pdo->query("SELECT COUNT(*) FROM chat_participants cp JOIN chat_conversations c ON c.id=cp.conversation_id AND c.type='group' LEFT JOIN user_units uu ON uu.user_id=cp.user_id AND uu.unit_id=c.unit_id LEFT JOIN users u ON u.id=cp.user_id AND u.status='active' WHERE cp.participant_source='unit' AND (uu.user_id IS NULL OR u.id IS NULL)")->fetchColumn();
    if($invalidNaturalParticipants!==0)throw new RuntimeException('Existem participantes naturais sem vinculo ativo com a unidade do grupo.');
    $groupSummary=$pdo->query("SELECT c.name,COUNT(cp.id) participant_count FROM chat_conversations c LEFT JOIN chat_participants cp ON cp.conversation_id=c.id WHERE c.type='group' GROUP BY c.id,c.name ORDER BY c.name")->fetchAll();
    $forbidden=$pdo->query("SELECT c.id conversation_id,u.id user_id FROM chat_conversations c CROSS JOIN users u LEFT JOIN chat_participants cp ON cp.conversation_id=c.id AND cp.user_id=u.id WHERE c.type='group' AND u.status='active' AND cp.id IS NULL LIMIT 1")->fetch();
    if($forbidden){
        try{$service->assertAccess((int)$forbidden['conversation_id'],(int)$forbidden['user_id']);throw new RuntimeException('Um usuario externo conseguiu acessar grupo de outra unidade.');}
        catch(RuntimeException $e){if($e->getMessage()!=='Conversa nao encontrada ou acesso negado.')throw $e;}
    }
    echo "OK: HubChat, Geral, grupos, acesso, consultas e renderizacao validados para o usuario #{$user['id']}.\n";
    echo 'Mensagens existentes: '.(int)$pdo->query('SELECT COUNT(*) FROM chat_messages')->fetchColumn()." (nenhum dado de teste foi criado).\n";
    echo "Conversas privadas duplicadas: 0.\n";
    echo "Grupos de unidade duplicados: 0.\n";
    echo "Acesso sem participacao a grupo: bloqueado.\n";
    foreach($groupSummary as $group)echo "Grupo {$group['name']}: {$group['participant_count']} participante(s).\n";
} catch(Throwable $e) { fwrite(STDERR,'Falha: '.$e->getMessage().PHP_EOL);exit(1); }
