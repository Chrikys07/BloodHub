<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\Database;
use PDO;

final class InternalNotificationService
{
    public static function notifyUnit(int $unitId,string $permission,string $eventKey,string $title,string $description,?string $url=null,?string $entityType=null,?int $entityId=null,?int $actorId=null):int
    {
        $pdo=Database::connection();
        $q=$pdo->prepare("SELECT DISTINCT u.id FROM users u JOIN roles r ON r.id=u.role_id AND r.status='active' JOIN role_permissions rp ON rp.role_id=r.id JOIN permissions p ON p.id=rp.permission_id AND p.permission_key=:permission AND p.status='active' LEFT JOIN user_units uu ON uu.user_id=u.id AND uu.unit_id=:unit WHERE u.status='active' AND (u.primary_unit_id=:unit2 OR uu.unit_id IS NOT NULL)");
        $q->execute(['permission'=>$permission,'unit'=>$unitId,'unit2'=>$unitId]);
        return self::notifyUsers(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)),$eventKey,$title,$description,$url,$entityType,$entityId,$actorId);
    }

    public static function notifyUsers(array $userIds,string $eventKey,string $title,string $description,?string $url=null,?string $entityType=null,?int $entityId=null,?int $actorId=null):int
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$userIds))));if(!$ids)return 0;
        $stmt=Database::connection()->prepare('INSERT INTO user_notifications(user_id,actor_user_id,event_key,title,description,action_url,entity_type,entity_id) VALUES(:user,:actor,:event,:title,:description,:url,:type,:entity)');$count=0;
        foreach($ids as$userId){if($actorId&&$userId===$actorId)continue;$stmt->execute(['user'=>$userId,'actor'=>$actorId?:null,'event'=>$eventKey,'title'=>mb_substr($title,0,180),'description'=>mb_substr($description,0,500),'url'=>$url,'type'=>$entityType,'entity'=>$entityId]);$count++;}return$count;
    }

    public static function recent(int $userId,int $limit=8):array
    {
        $limit=max(1,min(20,$limit));$q=Database::connection()->prepare("SELECT n.id,n.event_key,n.title,n.description,n.action_url,n.read_at,n.created_at,a.name actor_name FROM user_notifications n LEFT JOIN users a ON a.id=n.actor_user_id WHERE n.user_id=:user ORDER BY n.created_at DESC,n.id DESC LIMIT {$limit}");$q->execute(['user'=>$userId]);return$q->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function unreadCount(int $userId):int{$q=Database::connection()->prepare('SELECT COUNT(*) FROM user_notifications WHERE user_id=:user AND read_at IS NULL');$q->execute(['user'=>$userId]);return(int)$q->fetchColumn();}
    public static function markRead(int $userId,array $ids):int{$ids=array_values(array_unique(array_filter(array_map('intval',$ids))));if(!$ids)return 0;$marks=implode(',',array_fill(0,count($ids),'?'));$q=Database::connection()->prepare("UPDATE user_notifications SET read_at=COALESCE(read_at,NOW()) WHERE user_id=? AND id IN ({$marks})");$q->execute(array_merge([$userId],$ids));return$q->rowCount();}
}
