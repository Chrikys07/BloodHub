<?php
declare(strict_types=1);

namespace BloodHub\Core;

use PDO;

final class ValidationAccess
{
    public static function isGlobal(): bool
    {
        return Auth::isAdministrator();
    }

    public static function units(): array
    {
        $pdo = Database::connection();
        if (self::isGlobal()) return $pdo->query("SELECT id,name FROM units WHERE status='active' AND unit_type='processing' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $userId = (int)(Auth::user()['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT DISTINCT u.id,u.name FROM units u JOIN users usr ON usr.id=:user LEFT JOIN user_units uu ON uu.user_id=usr.id AND uu.unit_id=u.id WHERE u.status='active' AND u.unit_type='processing' AND (usr.primary_unit_id=u.id OR uu.unit_id IS NOT NULL) ORDER BY u.name");
        $stmt->execute(['user'=>$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function canUseUnit(int $unitId): bool
    {
        if ($unitId <= 0) return false;
        foreach (self::units() as $unit) if ((int)$unit['id'] === $unitId) return true;
        return false;
    }

    public static function scopeSql(string $alias='v', string $parameter='validation_user'): array
    {
        if (self::isGlobal()) return ['1=1', []];
        $userId = (int)(Auth::user()['id'] ?? 0);
        return ["{$alias}.unit_id IS NOT NULL AND EXISTS (SELECT 1 FROM users vu LEFT JOIN user_units vux ON vux.user_id=vu.id AND vux.unit_id={$alias}.unit_id WHERE vu.id=:{$parameter} AND (vu.primary_unit_id={$alias}.unit_id OR vux.unit_id IS NOT NULL))", [$parameter=>$userId]];
    }
}
