<?php
declare(strict_types=1);
namespace BloodHub\Core;

use PDO;

final class ProductionAccess
{
    public static function isGlobal():bool { return Auth::isAdministrator() && Permission::can('production.admin'); }

    public static function units():array
    {
        $pdo=Database::connection();
        if(self::isGlobal()) return $pdo->query("SELECT id,name FROM units WHERE status='active' AND unit_type='processing' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $q=$pdo->prepare("SELECT DISTINCT u.id,u.name FROM units u JOIN users usr ON usr.id=:user LEFT JOIN user_units uu ON uu.user_id=usr.id AND uu.unit_id=u.id WHERE u.status='active' AND u.unit_type='processing' AND (usr.primary_unit_id=u.id OR uu.unit_id IS NOT NULL) ORDER BY u.name");
        $q->execute(['user'=>(int)(Auth::user()['id']??0)]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function canUseUnit(int $unitId):bool
    {
        foreach(self::units() as $unit) if((int)$unit['id']===$unitId)return true;
        return false;
    }
}
