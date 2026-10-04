<?php
declare(strict_types=1);

namespace BloodHub\Services;

use BloodHub\Core\{Auth, Database, Permission, SamplePurpose, ShipmentAccess};
use PDO;
use BloodHub\Services\ShelfLifeResolver;

final class ReceptionService
{
    public static function shipment(int $id, bool $lock=false): ?array
    {
        [$scope,$params]=self::scopeSql('sh');
        $sql="SELECT sh.*,ou.name origin_name,du.name destination_name,c.name client_name,
                    responsible.name responsible_name,sender.name sent_by_name,
                    receiver.name received_by_name,rejector.name rejected_by_name,v.pv_number,v.name validation_name,vp.name validation_phase_name
              FROM sample_shipments sh
              JOIN units ou ON ou.id=sh.origin_unit_id
              JOIN units du ON du.id=sh.destination_unit_id
              LEFT JOIN clients c ON c.id=sh.client_id
              JOIN users responsible ON responsible.id=sh.responsible_user_id
              LEFT JOIN users sender ON sender.id=sh.sent_by
              LEFT JOIN users receiver ON receiver.id=sh.received_by
              LEFT JOIN users rejector ON rejector.id=sh.rejected_by
              LEFT JOIN validations v ON v.id=sh.validation_id
              LEFT JOIN validation_phases vp ON vp.id=sh.validation_phase_id
              WHERE sh.id=:id AND {$scope} LIMIT 1".($lock?' FOR UPDATE':'');
        $stmt=Database::connection()->prepare($sql);
        $stmt->execute(['id'=>$id]+$params);
        return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public static function queue(array $filters): array
    {
        [$scope, $params] = self::scopeSql('sh');
        $where = ["sh.status IN ('awaiting_receipt','partially_received')", $scope];

        if (($filters['origin_unit_id'] ?? 0) > 0) {
            $where[] = 'sh.origin_unit_id = :origin';
            $params['origin'] = (int)$filters['origin_unit_id'];
        }
        if (($filters['purpose'] ?? '') !== '' && SamplePurpose::isValid($filters['purpose'])) {
            $where[] = 'sh.purpose = :purpose';
            $params['purpose'] = $filters['purpose'];
        }
        if (($filters['sent_date'] ?? '') !== '') {
            $where[] = 'sh.sent_at >= :sent_start AND sh.sent_at < DATE_ADD(:sent_end, INTERVAL 1 DAY)';
            $params['sent_start'] = $filters['sent_date'].' 00:00:00';
            $params['sent_end'] = $filters['sent_date'].' 00:00:00';
        }
        if (($filters['shipment_code'] ?? '') !== '') {
            $where[] = 'sh.shipment_code LIKE :shipment_code';
            $params['shipment_code'] = '%'.$filters['shipment_code'].'%';
        }

        $sql = "SELECT sh.id, sh.shipment_code, sh.purpose, sh.sent_at, sh.status,v.pv_number,vp.name validation_phase_name,
                       ou.name origin_name, c.name client_name, u.name responsible_name,
                       (SELECT COUNT(*) FROM samples x WHERE x.sample_shipment_id=sh.id) sample_count,
                       (SELECT COUNT(*) FROM samples x WHERE x.sample_shipment_id=sh.id AND x.status='received') received_count,
                       (SELECT COUNT(*) FROM samples x WHERE x.sample_shipment_id=sh.id AND x.status='awaiting_receipt') awaiting_count,
                       (SELECT COUNT(*) FROM samples x WHERE x.sample_shipment_id=sh.id AND x.status='rejected') rejected_count,
                       (SELECT COUNT(*) FROM sample_shipment_thermal_boxes b WHERE b.shipment_id=sh.id) box_count
                FROM sample_shipments sh
                JOIN units ou ON ou.id=sh.origin_unit_id
                LEFT JOIN clients c ON c.id=sh.client_id
                JOIN users u ON u.id=sh.responsible_user_id
                LEFT JOIN validations v ON v.id=sh.validation_id
                LEFT JOIN validation_phases vp ON vp.id=sh.validation_phase_id
                WHERE ".implode(' AND ', $where).'
                ORDER BY sh.sent_at ASC, sh.id ASC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function counters(): array
    {
        [$scope, $params] = self::scopeSql('sh');
        $stmt = Database::connection()->prepare("SELECT COUNT(*) shipment_count,
            COALESCE(SUM((SELECT COUNT(*) FROM samples s WHERE s.sample_shipment_id=sh.id AND s.status='awaiting_receipt')),0) sample_count,
            COALESCE(SUM((SELECT COUNT(*) FROM sample_shipment_thermal_boxes b WHERE b.shipment_id=sh.id)),0) box_count
            FROM sample_shipments sh WHERE sh.status IN ('awaiting_receipt','partially_received') AND {$scope}");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['shipment_count'=>0,'sample_count'=>0,'box_count'=>0];
    }

    public static function origins(): array
    {
        [$scope, $params] = self::scopeSql('sh');
        $stmt = Database::connection()->prepare("SELECT DISTINCT u.id,u.name FROM sample_shipments sh JOIN units u ON u.id=sh.origin_unit_id WHERE sh.status IN ('awaiting_receipt','partially_received') AND {$scope} ORDER BY u.name");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function boxes(int $shipmentId): array
    {
        $stmt=Database::connection()->prepare("SELECT b.id,b.box_code,b.transport_temp_min_snapshot,b.transport_temp_max_snapshot,b.sent_temperature,b.received_temperature,b.received_at,b.received_by,u.name received_by_name,GROUP_CONCAT(tbc.blood_component_id ORDER BY bc.code) component_ids,GROUP_CONCAT(CONCAT(bc.code,' - ',bc.name) ORDER BY bc.code SEPARATOR '||') component_labels FROM sample_shipment_thermal_boxes b LEFT JOIN users u ON u.id=b.received_by LEFT JOIN sample_shipment_thermal_box_components tbc ON tbc.thermal_box_id=b.id LEFT JOIN blood_components bc ON bc.id=tbc.blood_component_id WHERE b.shipment_id=:id GROUP BY b.id ORDER BY b.id");
        $stmt->execute(['id'=>$shipmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function samples(int $shipmentId, bool $lock=false): array
    {
        $sql="SELECT s.*,bc.code component_code,bc.name component_name,CONCAT(bb.name,IF(bb.reference_number IS NULL OR bb.reference_number='','',CONCAT(' | Ref. ',bb.reference_number))) bag_brand_name,p.code preservative_code,p.name preservative_name,ru.name received_by_name,xu.name rejected_by_name FROM samples s LEFT JOIN blood_components bc ON bc.id=s.blood_component_id LEFT JOIN bag_brands bb ON bb.id=s.bag_brand_id LEFT JOIN preservatives p ON p.id=s.preservative_id_snapshot LEFT JOIN users ru ON ru.id=s.received_by LEFT JOIN users xu ON xu.id=s.rejected_by WHERE s.sample_shipment_id=:id ORDER BY s.id".($lock?' FOR UPDATE':'');
        $stmt=Database::connection()->prepare($sql);$stmt->execute(['id'=>$shipmentId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);foreach($rows as &$row)$row['is_expired']=ShelfLifeResolver::isExpired($row['expiration_date']??null);unset($row);return $rows;
    }

    public static function shipmentComponents(int $shipmentId): array
    {
        $s=Database::connection()->prepare("SELECT DISTINCT bc.id,bc.code,bc.name FROM samples x JOIN blood_components bc ON bc.id=x.blood_component_id WHERE x.sample_shipment_id=:id ORDER BY bc.code");
        $s->execute(['id'=>$shipmentId]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function calculateShipmentStatus(array $samples): string
    {
        $counts=array_count_values(array_column($samples,'status'));$awaiting=(int)($counts['awaiting_receipt']??0);$received=(int)($counts['received']??0);$rejected=(int)($counts['rejected']??0);
        if($awaiting===count($samples))return 'awaiting_receipt';
        if($awaiting>0&&($received+$rejected)>0)return 'partially_received';
        if($awaiting===0&&$received>0)return 'received';
        if($samples&&$rejected===count($samples))return 'rejected';
        throw new \RuntimeException('A remessa possui uma combinação de status inválida.');
    }

    private static function scopeSql(string $alias): array
    {
        if (Permission::can('samples.scope.global')) return ['1=1', []];
        $userId=(int)(Auth::user()['id']??0);
        return ["EXISTS (SELECT 1 FROM users ru LEFT JOIN user_units rux ON rux.user_id=ru.id AND rux.unit_id={$alias}.destination_unit_id WHERE ru.id=:reception_user AND (ru.primary_unit_id={$alias}.destination_unit_id OR rux.unit_id IS NOT NULL))", ['reception_user'=>$userId]];
    }
}
