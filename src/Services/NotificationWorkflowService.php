<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database,Permission};
use DomainException;
use PDO;

final class NotificationWorkflowService
{
    public const PENDING='PENDING_ACKNOWLEDGEMENT', ACKNOWLEDGED='ACKNOWLEDGED', IN_ANALYSIS='IN_ANALYSIS', IN_FOLLOW_UP='IN_FOLLOW_UP', COMPLETED='COMPLETED';
    public static function labels():array{return[self::PENDING=>'Pendente de ciência',self::ACKNOWLEDGED=>'Aguardando análise',self::IN_ANALYSIS=>'Em análise',self::IN_FOLLOW_UP=>'Em acompanhamento',self::COMPLETED=>'Concluída','CLOSED'=>'Concluída','CANCELLED'=>'Cancelada'];}
    public static function acknowledge(array $ids):int
    {
        self::requirePermission('notifications.acknowledge');$ids=array_values(array_unique(array_filter(array_map('intval',$ids))));if(!$ids)return 0;
        $pdo=Database::connection();$uid=(int)(Auth::user()['id']??0);$count=0;[$scope,$params]=QcNotificationService::scopeSql('n');$pdo->beginTransaction();
        try{foreach($ids as$id){$q=$pdo->prepare("SELECT n.id,n.status,n.origin_unit_id FROM qc_notifications n WHERE n.id=:id AND {$scope} FOR UPDATE");$q->execute(['id'=>$id]+$params);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row||$row['status']!==self::PENDING)continue;$pdo->prepare('UPDATE qc_notifications SET status=:status,acknowledged_at=NOW(),acknowledged_by=:user WHERE id=:id')->execute(['status'=>self::ACKNOWLEDGED,'user'=>$uid,'id'=>$id]);self::notificationEvent($pdo,$id,'NOTIFICATION_ACKNOWLEDGED','Ciência registrada',$uid,['unit_id'=>(int)$row['origin_unit_id'],'previous_status'=>self::PENDING,'new_status'=>self::ACKNOWLEDGED]);Auth::registerAudit('NOTIFICATION_ACKNOWLEDGED','qc_notifications',$id,['status'=>self::PENDING],['status'=>self::ACKNOWLEDGED,'unit_id'=>(int)$row['origin_unit_id']]);$count++;}$pdo->commit();return$count;}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }
    public static function notificationEvent(PDO $pdo,int $notificationId,string $type,string $description,?int $userId=null,array $metadata=[]):void{$pdo->prepare('INSERT INTO qc_notification_events(notification_id,event_type,description,user_id,metadata) VALUES(:notification,:type,:description,:user,:metadata)')->execute(['notification'=>$notificationId,'type'=>$type,'description'=>$description,'user'=>$userId,'metadata'=>$metadata?json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null]);}
    public static function analysisEvent(PDO $pdo,int $analysisId,string $type,string $description,?int $userId=null,array $metadata=[]):void{$pdo->prepare('INSERT INTO qc_notification_analysis_events(analysis_id,event_type,description,user_id,metadata) VALUES(:analysis,:type,:description,:user,:metadata)')->execute(['analysis'=>$analysisId,'type'=>$type,'description'=>$description,'user'=>$userId,'metadata'=>$metadata?json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null]);}
    public static function requirePermission(string $permission):void{if(!Permission::can($permission))throw new DomainException('Você não possui permissão para realizar esta ação.');}
}
