<?php
declare(strict_types=1);

namespace BloodHub\Core;

use PDO;

final class SampleAccess
{
    public static function units(?string $unitType = null): array
    {
        $user = Auth::user();
        if (!$user) return [];
        $pdo = Database::connection();
        if (Permission::can('samples.scope.global')) {
            $sql = "SELECT id,name,client_id,unit_type FROM units WHERE status='active'";
            $params = [];
            if ($unitType !== null) { $sql .= ' AND unit_type=:unit_type'; $params['unit_type'] = $unitType; }
            $stmt = $pdo->prepare($sql.' ORDER BY name');
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        // Processing origins are operational units of the user's client. A user may
        // be attached to an agency in user_units while the compatible processing
        // unit is a sibling unit of that same client.
        $clientScope = $unitType === 'processing'
            ? ' OR (usr.client_id IS NOT NULL AND u.client_id=usr.client_id)'
            : '';
        $stmt = $pdo->prepare(
            "SELECT DISTINCT u.id,u.name,u.client_id,u.unit_type FROM units u
             LEFT JOIN user_units uu ON uu.unit_id=u.id AND uu.user_id=:user_id
             LEFT JOIN users usr ON usr.id=:user_id2
             WHERE u.status='active' AND (uu.user_id IS NOT NULL OR usr.primary_unit_id=u.id{$clientScope})".
             ($unitType !== null ? " AND u.unit_type=:unit_type" : '')."
             ORDER BY u.name"
        );
        $params = ['user_id'=>$user['id'], 'user_id2'=>$user['id']];
        if ($unitType !== null) $params['unit_type'] = $unitType;
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function canUseUnit(int $unitId): bool
    {
        foreach (self::units() as $unit) if ((int)$unit['id'] === $unitId) return true;
        return false;
    }

    public static function scopeSql(string $alias='s'): array
    {
        $user = Auth::user();
        if (Permission::can('samples.scope.global')) return ['1=1', []];
        return ["EXISTS (SELECT 1 FROM users su LEFT JOIN user_units uu ON uu.user_id=su.id AND uu.unit_id={$alias}.origin_unit_id WHERE su.id=:scope_user AND (su.primary_unit_id={$alias}.origin_unit_id OR uu.unit_id IS NOT NULL))", ['scope_user'=>(int)($user['id']??0)]];
    }

    public static function sample(int $id): ?array
    {
        [$scope,$params]=self::scopeSql('s');
        $sql="SELECT s.*,u.name unit_name,c.name client_name,bc.name component_name,
                    reg.name registered_by_name,sender.name sent_by_name,receiver.name received_by_name,rejecter.name rejected_by_name
              FROM samples s LEFT JOIN units u ON u.id=s.origin_unit_id LEFT JOIN clients c ON c.id=s.client_id
              LEFT JOIN blood_components bc ON bc.id=s.blood_component_id LEFT JOIN users reg ON reg.id=s.registered_by
              LEFT JOIN users sender ON sender.id=s.sent_by LEFT JOIN users receiver ON receiver.id=s.received_by
              LEFT JOIN users rejecter ON rejecter.id=s.rejected_by WHERE s.id=:id AND {$scope} LIMIT 1";
        $stmt=Database::connection()->prepare($sql); $stmt->execute(['id'=>$id]+$params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
