<?php
declare(strict_types=1);
namespace BloodHub\Core;

use PDO;

final class ShipmentAccess
{
    public static function scopeSql(string $alias='sh'): array
    {
        if (Permission::can('samples.scope.global')) return ['1=1', []];
        return ["EXISTS (SELECT 1 FROM users su LEFT JOIN user_units uu ON uu.user_id=su.id AND uu.unit_id={$alias}.origin_unit_id WHERE su.id=:scope_user AND (su.primary_unit_id={$alias}.origin_unit_id OR uu.unit_id IS NOT NULL))", ['scope_user'=>(int)(Auth::user()['id']??0)]];
    }

    public static function shipment(int $id, bool $lock=false): ?array
    {
        [$scope,$params]=self::scopeSql('sh');
        $sql="SELECT sh.*,ou.name origin_name,du.name destination_name,c.name client_name,
                    responsible.name responsible_name,sender.name sent_by_name,receiver.name received_by_name,
                    rejector.name rejected_by_name,canceller.name cancelled_by_name
              FROM sample_shipments sh
              JOIN units ou ON ou.id=sh.origin_unit_id JOIN units du ON du.id=sh.destination_unit_id
              LEFT JOIN clients c ON c.id=sh.client_id JOIN users responsible ON responsible.id=sh.responsible_user_id
              LEFT JOIN users sender ON sender.id=sh.sent_by LEFT JOIN users receiver ON receiver.id=sh.received_by
              LEFT JOIN users rejector ON rejector.id=sh.rejected_by LEFT JOIN users canceller ON canceller.id=sh.cancelled_by
              WHERE sh.id=:id AND {$scope} LIMIT 1".($lock?' FOR UPDATE':'');
        $stmt=Database::connection()->prepare($sql);$stmt->execute(['id'=>$id]+$params);
        return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public static function canMutate(array $shipment): bool
    {
        return in_array($shipment['status'],['draft','awaiting_receipt','partially_received'],true)
            && Permission::can('shipments.edit') && SampleAccess::canUseUnit((int)$shipment['origin_unit_id']);
    }
}
