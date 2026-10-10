<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\Database;
use DateTimeImmutable;
use PDO;

final class SupplyExpiryNotificationService
{
    public const DEFAULT_THRESHOLDS=[30,15,7];

    public static function run(?DateTimeImmutable $today=null):array
    {
        $today=$today??new DateTimeImmutable('today');$thresholds=self::thresholds();$max=max($thresholds);
        $q=Database::connection()->prepare("SELECT l.id,l.supply_id,l.lot_number,l.expiration_date,l.is_in_use,s.name supply_name FROM supply_lots l JOIN supplies s ON s.id=l.supply_id WHERE l.status='active' AND s.status='active' AND l.expiration_date<=DATE_ADD(:today,INTERVAL {$max} DAY) ORDER BY l.expiration_date,l.id");
        $q->execute(['today'=>$today->format('Y-m-d')]);$recipients=array_values(array_unique(array_merge(InternalNotificationService::usersByRoles(['lcqh'],'quality_results.view'),InternalNotificationService::usersByRoles(['administrador'],'admin.supplies.manage'))));$created=0;$checked=0;
        foreach($q->fetchAll(PDO::FETCH_ASSOC)as$lot){$checked++;$expiry=new DateTimeImmutable($lot['expiration_date']);$days=(int)$today->diff($expiry)->format('%r%a');$bucket=self::bucket($days,$thresholds);if($bucket===null)continue;
            $expired=$days<0;$inUse=(bool)$lot['is_in_use'];$critical=$inUse;$title=$expired&&$inUse?'Lote vencido em uso':($inUse?'Lote em uso próximo do vencimento':($expired?'Insumo vencido':'Insumo próximo do vencimento'));
            $description=sprintf('O lote %s do insumo %s %s em %s%s.',$lot['lot_number'],$lot['supply_name'],$expired?'venceu':'vence',$expiry->format('d/m/Y'),$expired&&$inUse?' e permanece marcado como Em uso':'');
            $key=($expired?'supply_expired':'supply_expiry').':lot_'.$lot['id'].':'.$bucket;$created+=InternalNotificationService::notifyUsers($recipients,$key,$title,$description,null,'supply_lot',(int)$lot['id'],null,$critical?'critical':'warning');
        }
        return['checked'=>$checked,'created'=>$created,'recipients'=>count($recipients),'thresholds'=>$thresholds];
    }

    public static function thresholds():array
    {
        $raw=getenv('SUPPLY_EXPIRY_NOTIFICATION_DAYS');$values=$raw===false?self::DEFAULT_THRESHOLDS:array_map('intval',explode(',',$raw));$values=array_values(array_unique(array_filter($values,static fn(int$v)=>$v>0&&$v<=365)));rsort($values);return$values?:self::DEFAULT_THRESHOLDS;
    }

    private static function bucket(int $days,array $thresholds):?string
    {if($days<0)return'expired';foreach(array_reverse($thresholds)as$threshold)if($days<=$threshold)return$threshold.'d';return null;}
}
