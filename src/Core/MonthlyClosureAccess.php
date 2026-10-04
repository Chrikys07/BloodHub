<?php
declare(strict_types=1);
namespace BloodHub\Core;

use PDO;

final class MonthlyClosureAccess
{
    public static function isGlobal(): bool
    {
        $role=(string)(Auth::user()['role_slug']??'');
        return Auth::isAdministrator() || in_array($role,['gestao','lcqh'],true) || Permission::can('samples.scope.global');
    }

    public static function units(): array
    {
        $pdo=Database::connection();
        if(self::isGlobal()) return $pdo->query("SELECT id,name,code FROM units WHERE status='active' AND unit_type='processing' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $q=$pdo->prepare("SELECT DISTINCT u.id,u.name,u.code FROM units u JOIN users usr ON usr.id=:user LEFT JOIN user_units uu ON uu.user_id=usr.id AND uu.unit_id=u.id WHERE u.status='active' AND u.unit_type='processing' AND (u.id=usr.primary_unit_id OR uu.unit_id IS NOT NULL) ORDER BY u.name");
        $q->execute(['user'=>(int)(Auth::user()['id']??0)]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function canUseUnit(int $unitId): bool
    {
        foreach(self::units() as $unit) if((int)$unit['id']===$unitId)return true;
        return false;
    }
}
