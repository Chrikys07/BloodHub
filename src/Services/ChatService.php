<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\Database;
use PDO;
use RuntimeException;

final class ChatService
{
    private PDO $pdo;
    private bool $unitGroupsSynced = false;
    public function __construct() { $this->pdo = Database::connection(); }

    public function syncUnitGroupParticipants(): void
    {
        if ($this->unitGroupsSynced) return;
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec("INSERT INTO chat_participants(conversation_id,user_id,participant_source,joined_at)
                SELECT c.id,u.id,'unit',NOW()
                FROM chat_conversations c
                JOIN units un ON un.id=c.unit_id AND un.status='active'
                JOIN user_units uu ON uu.unit_id=un.id
                JOIN users u ON u.id=uu.user_id AND u.status='active'
                JOIN roles r ON r.id=u.role_id AND r.status='active'
                WHERE c.type='group'
                  AND EXISTS (SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id AND p.permission_key='chat.view' AND p.status='active' WHERE rp.role_id=r.id)
                  AND EXISTS (SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id AND p.permission_key='chat.group' AND p.status='active' WHERE rp.role_id=r.id)
                ON DUPLICATE KEY UPDATE participant_source=IF(participant_source='external','external','unit'),archived_at=NULL");
            $this->pdo->exec("DELETE cp FROM chat_participants cp
                JOIN chat_conversations c ON c.id=cp.conversation_id AND c.type='group'
                LEFT JOIN units un ON un.id=c.unit_id AND un.status='active'
                LEFT JOIN user_units uu ON uu.unit_id=c.unit_id AND uu.user_id=cp.user_id
                LEFT JOIN users u ON u.id=cp.user_id AND u.status='active'
                WHERE cp.participant_source='unit' AND (un.id IS NULL OR uu.user_id IS NULL OR u.id IS NULL
                   OR NOT EXISTS (
                       SELECT 1 FROM role_permissions rp
                       JOIN permissions p ON p.id=rp.permission_id
                       JOIN roles r ON r.id=rp.role_id
                       WHERE rp.role_id=u.role_id AND r.status='active'
                         AND p.permission_key IN ('chat.view','chat.group') AND p.status='active'
                         GROUP BY rp.role_id HAVING COUNT(DISTINCT p.permission_key)=2
                   ))");
            $this->pdo->commit();
            $this->unitGroupsSynced = true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function ensureGeneralForUser(int $userId): int
    {
        $this->pdo->prepare("INSERT INTO chat_conversations(type,name,slug,created_by) VALUES('general','Geral','general',NULL) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),name='Geral'")->execute();
        $id = (int)$this->pdo->lastInsertId();
        if (!$id) { $id = (int)$this->pdo->query("SELECT id FROM chat_conversations WHERE slug='general' LIMIT 1")->fetchColumn(); }
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO chat_participants(conversation_id,user_id,joined_at) SELECT :conversation_id,id,NOW() FROM users WHERE id=:user_id AND status='active'");
        $stmt->execute(['conversation_id'=>$id,'user_id'=>$userId]);
        return $id;
    }

    public function conversations(int $userId): array
    {
        $this->ensureGeneralForUser($userId);
        $this->syncUnitGroupParticipants();
        $sql = "SELECT c.id,c.type,c.name,c.unit_id,c.updated_at,
                  ou.id other_user_id,ou.name other_user_name,ou.photo_path other_photo_path,r.name other_role_name,
                  (SELECT COUNT(*) FROM chat_participants members JOIN users member_user ON member_user.id=members.user_id AND member_user.status='active' WHERE members.conversation_id=c.id) participant_count,
                  (SELECT m.message FROM chat_messages m WHERE m.conversation_id=c.id AND m.deleted_at IS NULL ORDER BY m.created_at DESC,m.id DESC LIMIT 1) last_message,
                  (SELECT m.created_at FROM chat_messages m WHERE m.conversation_id=c.id AND m.deleted_at IS NULL ORDER BY m.created_at DESC,m.id DESC LIMIT 1) last_message_at,
                  (SELECT COUNT(*) FROM chat_messages m WHERE m.conversation_id=c.id AND m.deleted_at IS NULL AND m.sender_id<>:uid_unread AND (cp.last_read_at IS NULL OR m.created_at>cp.last_read_at)) unread_count
                FROM chat_participants cp
                JOIN chat_conversations c ON c.id=cp.conversation_id
                LEFT JOIN chat_participants op ON c.type='private' AND op.conversation_id=c.id AND op.user_id<>:uid_other
                LEFT JOIN users ou ON ou.id=op.user_id LEFT JOIN roles r ON r.id=ou.role_id
                WHERE cp.user_id=:uid_where AND cp.archived_at IS NULL
                ORDER BY COALESCE((SELECT MAX(mm.created_at) FROM chat_messages mm WHERE mm.conversation_id=c.id AND mm.deleted_at IS NULL),c.updated_at) DESC,c.id DESC";
        $stmt=$this->pdo->prepare($sql); $stmt->execute(['uid_unread'=>$userId,'uid_other'=>$userId,'uid_where'=>$userId]);
        return $stmt->fetchAll();
    }

    public function activeUsers(int $userId): array
    {
        $stmt=$this->pdo->prepare("SELECT u.id,u.name,u.photo_path,r.name role_name FROM users u LEFT JOIN roles r ON r.id=u.role_id WHERE u.status='active' AND u.id<>:id ORDER BY u.name");
        $stmt->execute(['id'=>$userId]); return $stmt->fetchAll();
    }

    public function activeSectors(): array
    {
        $sql="SELECT u.id,u.name,u.unit_type,COUNT(DISTINCT usr.id) user_count
              FROM units u JOIN user_units uu ON uu.unit_id=u.id
              JOIN users usr ON usr.id=uu.user_id AND usr.status='active'
              JOIN roles r ON r.id=usr.role_id AND r.status='active'
              WHERE u.status='active'
                AND EXISTS (SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id AND p.permission_key='chat.view' AND p.status='active' WHERE rp.role_id=r.id)
                AND EXISTS (SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id AND p.permission_key='chat.group' AND p.status='active' WHERE rp.role_id=r.id)
              GROUP BY u.id,u.name,u.unit_type ORDER BY u.unit_type,u.name";
        return array_map(function(array $unit):array{$unit['display_name']=$this->unitGroupName($unit);return $unit;},$this->pdo->query($sql)->fetchAll());
    }

    public function unitConversation(int $userId,int $unitId): int
    {
        $unit=$this->pdo->prepare("SELECT u.id,u.name,u.unit_type FROM units u WHERE u.id=:id AND u.status='active' AND EXISTS (SELECT 1 FROM user_units uu JOIN users usr ON usr.id=uu.user_id AND usr.status='active' JOIN roles r ON r.id=usr.role_id AND r.status='active' WHERE uu.unit_id=u.id AND EXISTS (SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id AND p.permission_key='chat.view' AND p.status='active' WHERE rp.role_id=r.id) AND EXISTS (SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id AND p.permission_key='chat.group' AND p.status='active' WHERE rp.role_id=r.id))");
        $unit->execute(['id'=>$unitId]);$row=$unit->fetch();if(!$row)throw new RuntimeException('O setor selecionado nao esta disponivel.');
        $this->pdo->beginTransaction();
        try{
            $stmt=$this->pdo->prepare("INSERT INTO chat_conversations(type,name,unit_id,created_by) VALUES('group',:name,:unit_id,:created_by) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),type='group',name=VALUES(name)");
            $stmt->execute(['name'=>$this->unitGroupName($row),'unit_id'=>$unitId,'created_by'=>$userId]);$id=(int)$this->pdo->lastInsertId();
            $stmt=$this->pdo->prepare("INSERT INTO chat_participants(conversation_id,user_id,participant_source,joined_at,archived_at) VALUES(:conversation_id,:user_id,'external',NOW(),NULL) ON DUPLICATE KEY UPDATE archived_at=NULL");
            $stmt->execute(['conversation_id'=>$id,'user_id'=>$userId]);$this->pdo->commit();$this->unitGroupsSynced=false;$this->syncUnitGroupParticipants();return $id;
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function participants(int $conversationId,int $userId): array
    {
        $conversation=$this->assertAccess($conversationId,$userId);if($conversation['type']!=='group')throw new RuntimeException('A lista de participantes esta disponivel apenas para grupos.');
        $stmt=$this->pdo->prepare("SELECT u.id,u.name,u.photo_path FROM chat_participants cp JOIN users u ON u.id=cp.user_id AND u.status='active' WHERE cp.conversation_id=:id ORDER BY u.name");
        $stmt->execute(['id'=>$conversationId]);return $stmt->fetchAll();
    }

    public function messages(int $conversationId, int $userId, ?int $beforeId=null, int $limit=50): array
    {
        $this->assertAccess($conversationId,$userId);
        $limit=max(1,min(50,$limit)); $params=['conversation_id'=>$conversationId];
        $before=''; if($beforeId){$before=' AND m.id<:before_id';$params['before_id']=$beforeId;}
        $sql="SELECT m.id,m.conversation_id,m.sender_id,m.message,m.created_at,u.name sender_name,u.photo_path sender_photo_path
              FROM chat_messages m JOIN users u ON u.id=m.sender_id
              WHERE m.conversation_id=:conversation_id AND m.deleted_at IS NULL{$before}
              ORDER BY m.created_at DESC,m.id DESC LIMIT {$limit}";
        $stmt=$this->pdo->prepare($sql);$stmt->execute($params);return array_reverse($stmt->fetchAll());
    }

    public function send(int $conversationId,int $userId,string $message): array
    {
        $this->assertAccess($conversationId,$userId);
        $message=trim($message);$length=mb_strlen($message);
        if($length<1||$length>4000)throw new RuntimeException('A mensagem deve ter entre 1 e 4000 caracteres.');
        $this->pdo->beginTransaction();
        try{
            $stmt=$this->pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_id,message,created_at) VALUES(:conversation_id,:sender_id,:message,NOW(6))');
            $stmt->execute(['conversation_id'=>$conversationId,'sender_id'=>$userId,'message'=>$message]);$id=(int)$this->pdo->lastInsertId();
            $this->pdo->prepare('UPDATE chat_conversations SET updated_at=NOW(6) WHERE id=:id')->execute(['id'=>$conversationId]);
            $this->pdo->prepare('UPDATE chat_participants SET last_read_at=NOW(6) WHERE conversation_id=:conversation_id AND user_id=:user_id')->execute(['conversation_id'=>$conversationId,'user_id'=>$userId]);
            $this->pdo->prepare("UPDATE chat_participants cp JOIN chat_conversations c ON c.id=cp.conversation_id SET cp.archived_at=NULL WHERE cp.conversation_id=:conversation_id AND c.type='private'")->execute(['conversation_id'=>$conversationId]);
            $this->pdo->commit();
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        return $this->messageById($id,$userId);
    }

    public function markRead(int $conversationId,int $userId): void
    {
        $this->assertAccess($conversationId,$userId);
        $stmt=$this->pdo->prepare('UPDATE chat_participants SET last_read_at=NOW(6) WHERE conversation_id=:conversation_id AND user_id=:user_id');
        $stmt->execute(['conversation_id'=>$conversationId,'user_id'=>$userId]);
    }

    public function unreadCount(int $userId): int
    {
        $this->ensureGeneralForUser($userId);
        $this->syncUnitGroupParticipants();
        $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM chat_messages m JOIN chat_participants cp ON cp.conversation_id=m.conversation_id AND cp.user_id=:user_id WHERE cp.archived_at IS NULL AND m.deleted_at IS NULL AND m.sender_id<>:sender_id AND (cp.last_read_at IS NULL OR m.created_at>cp.last_read_at)');
        $stmt->execute(['user_id'=>$userId,'sender_id'=>$userId]);return (int)$stmt->fetchColumn();
    }

    public function privateConversation(int $userId,int $otherUserId): int
    {
        if($otherUserId===$userId)throw new RuntimeException('Selecione outro usuario.');
        $stmt=$this->pdo->prepare("SELECT 1 FROM users WHERE id=:id AND status='active'");$stmt->execute(['id'=>$otherUserId]);
        if(!$stmt->fetchColumn())throw new RuntimeException('O usuario selecionado nao esta disponivel.');
        [$a,$b]=$userId<$otherUserId?[$userId,$otherUserId]:[$otherUserId,$userId];$key=$a.':'.$b;
        $this->pdo->beginTransaction();
        try{
            $stmt=$this->pdo->prepare("INSERT INTO chat_conversations(type,name,private_key,created_by) VALUES('private',NULL,:private_key,:created_by) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
            $stmt->execute(['private_key'=>$key,'created_by'=>$userId]);$id=(int)$this->pdo->lastInsertId();
            $participant=$this->pdo->prepare('INSERT IGNORE INTO chat_participants(conversation_id,user_id,joined_at) VALUES(:conversation_id,:user_id,NOW())');
            foreach([$userId,$otherUserId] as $participantId)$participant->execute(['conversation_id'=>$id,'user_id'=>$participantId]);
            $this->pdo->prepare('UPDATE chat_participants SET archived_at=NULL WHERE conversation_id=:conversation_id AND user_id=:user_id')->execute(['conversation_id'=>$id,'user_id'=>$userId]);
            $this->pdo->commit();return $id;
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function archivePrivate(int $conversationId,int $userId): void
    {
        $conversation=$this->assertAccess($conversationId,$userId);if($conversation['type']!=='private')throw new RuntimeException('Somente conversas privadas podem ser removidas da lista.');
        $stmt=$this->pdo->prepare('UPDATE chat_participants SET archived_at=NOW(6) WHERE conversation_id=:conversation_id AND user_id=:user_id');
        $stmt->execute(['conversation_id'=>$conversationId,'user_id'=>$userId]);
    }

    public function assertAccess(int $conversationId,int $userId): array
    {
        $this->syncUnitGroupParticipants();
        $stmt=$this->pdo->prepare("SELECT c.id,c.type,c.name FROM chat_conversations c
            JOIN chat_participants cp ON cp.conversation_id=c.id
            WHERE c.id=:conversation_id AND cp.user_id=:user_id
              AND (c.type<>'group' OR EXISTS (
                  SELECT 1 FROM users gu JOIN roles gr ON gr.id=gu.role_id AND gr.status='active'
                  JOIN role_permissions rp ON rp.role_id=gr.id JOIN permissions p ON p.id=rp.permission_id
                  WHERE gu.id=:group_user_id AND gu.status='active' AND p.permission_key='chat.group' AND p.status='active'
              ))
              LIMIT 1");
        $accessParams=['conversation_id'=>$conversationId,'user_id'=>$userId,'group_user_id'=>$userId];
        $stmt->execute($accessParams);$row=$stmt->fetch();
        if(!$row){
            $type=$this->pdo->prepare("SELECT type FROM chat_conversations WHERE id=:id AND slug='general'");$type->execute(['id'=>$conversationId]);
            if($type->fetchColumn()==='general'){$this->ensureGeneralForUser($userId);$stmt->execute($accessParams);$row=$stmt->fetch();}
        }
        if(!$row)throw new RuntimeException('Conversa nao encontrada ou acesso negado.');return $row;
    }

    private function messageById(int $id,int $userId): array
    {
        $stmt=$this->pdo->prepare('SELECT m.id,m.conversation_id,m.sender_id,m.message,m.created_at,u.name sender_name,u.photo_path sender_photo_path FROM chat_messages m JOIN users u ON u.id=m.sender_id WHERE m.id=:id AND m.sender_id=:user_id');
        $stmt->execute(['id'=>$id,'user_id'=>$userId]);return $stmt->fetch() ?: [];
    }

    private function unitGroupName(array $unit): string
    {
        $name=trim((string)$unit['name']);
        if($unit['unit_type']==='lcqh')return 'LCQH';
        if($unit['unit_type']==='processing')return str_starts_with(mb_strtolower($name),'processamento ')?$name:'Processamento '.$name;
        return $name;
    }
}
