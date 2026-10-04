<?php
declare(strict_types=1);

namespace BloodHub\Services;

use BloodHub\Core\Auth;
use BloodHub\Core\Database;
use DateTimeImmutable;
use PDO;

final class ShelfLifeResolver
{
    public static function resolve(int $bloodComponentId, int $preservativeId, bool $lock=false): array
    {
        $sql = "SELECT sl.id configuration_id,sl.shelf_life_days,sl.active,
                       bc.code component_code,bc.name component_name,
                       p.code preservative_code,p.name preservative_name
                  FROM blood_component_preservative_shelf_lives sl
                  JOIN blood_components bc ON bc.id=sl.blood_component_id
                  JOIN preservatives p ON p.id=sl.preservative_id
                 WHERE sl.blood_component_id=:component AND sl.preservative_id=:preservative
                   AND sl.active=1 LIMIT 1".($lock?' FOR UPDATE':'');
        $stmt=Database::connection()->prepare($sql);
        $stmt->execute(['component'=>$bloodComponentId,'preservative'=>$preservativeId]);
        $rule=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$rule) throw new \DomainException('Não existe validade ativa configurada para este hemocomponente e preservante.');
        $rule['configuration_id']=(int)$rule['configuration_id'];
        $rule['shelf_life_days']=(int)$rule['shelf_life_days'];
        return $rule;
    }

    public static function forBagBrand(int $bloodComponentId, int $bagBrandId, string $productionDate, bool $lock=false): array
    {
        $stmt=Database::connection()->prepare('SELECT b.preservative_id,p.code preservative_code,p.name preservative_name FROM bag_brands b LEFT JOIN preservatives p ON p.id=b.preservative_id WHERE b.id=:id'.($lock?' FOR UPDATE':''));
        $stmt->execute(['id'=>$bagBrandId]);$brand=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$brand) throw new \DomainException('Referência de bolsa não encontrada.');
        if(empty($brand['preservative_id'])) throw new \DomainException('A referência da bolsa não possui preservante configurado.');
        $rule=self::resolve($bloodComponentId,(int)$brand['preservative_id'],$lock);
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$productionDate);
        if(!$date||$date->format('Y-m-d')!==$productionDate) throw new \DomainException('Data de produção inválida.');
        return $rule+[
            'preservative_id'=>(int)$brand['preservative_id'],
            'expiration_date'=>$date->modify('+'.$rule['shelf_life_days'].' days')->format('Y-m-d'),
        ];
    }

    public static function isExpired(?string $expirationDate, ?string $receivingDate=null): bool
    {
        if(!$expirationDate) return false;
        $receivingDate=$receivingDate?:date('Y-m-d');
        return $receivingDate>$expirationDate;
    }

    public static function snapshotShipment(int $shipmentId):void
    {
        $pdo=Database::connection();$q=$pdo->prepare("SELECT id,blood_component_id,bag_brand_id,production_date,preservative_id_snapshot,shelf_life_days_snapshot,expiration_date FROM samples WHERE sample_shipment_id=:id AND status IN ('registered','awaiting_receipt') FOR UPDATE");$q->execute(['id'=>$shipmentId]);
        $update=$pdo->prepare('UPDATE samples SET preservative_id_snapshot=:preservative,shelf_life_configuration_id_snapshot=:configuration,shelf_life_days_snapshot=:days,expiration_date=:expiration WHERE id=:id');
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $sample){$rule=self::forBagBrand((int)$sample['blood_component_id'],(int)$sample['bag_brand_id'],(string)$sample['production_date'],true);$after=['preservative'=>(int)$rule['preservative_id'],'configuration'=>(int)$rule['configuration_id'],'days'=>(int)$rule['shelf_life_days'],'expiration'=>$rule['expiration_date'],'id'=>(int)$sample['id']];$update->execute($after);if($sample['preservative_id_snapshot']!=$after['preservative']||$sample['shelf_life_days_snapshot']!=$after['days']||$sample['expiration_date']!=$after['expiration'])Auth::registerAudit('sample.shelf_life_snapshot','samples',(int)$sample['id'],['preservative_id_snapshot'=>$sample['preservative_id_snapshot'],'shelf_life_days_snapshot'=>$sample['shelf_life_days_snapshot'],'expiration_date'=>$sample['expiration_date']],['preservative_id_snapshot'=>$after['preservative'],'shelf_life_configuration_id_snapshot'=>$after['configuration'],'shelf_life_days_snapshot'=>$after['days'],'expiration_date'=>$after['expiration']]);}
    }
}
