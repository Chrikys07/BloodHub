<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database};
use DomainException;
use PDO;

/** Central unit-level authorization. Request parameters never expand this scope. */
final class UnitAccessService
{
    public static function isGlobal(?array $user=null):bool
    {
        $user ??= Auth::user() ?? [];
        return in_array($user['role_slug'] ?? '', ['administrador','lcqh','gestao'], true);
    }

    public static function visibleUnits(?int $clientId=null):array
    {
        $user=Auth::user()??[];$pdo=Database::connection();$params=[];
        $sql="SELECT DISTINCT u.id,u.code,u.name,u.client_id,u.unit_type,c.name client_name,c.client_type
              FROM units u LEFT JOIN clients c ON c.id=u.client_id";
        $where=["u.status='active'"];
        if(!self::isGlobal($user)){
            $sql.=' JOIN user_units uu ON uu.unit_id=u.id AND uu.user_id=:user_id';$params['user_id']=(int)($user['id']??0);
            if(!empty($user['client_id'])){$where[]='u.client_id=:authorized_client';$params['authorized_client']=(int)$user['client_id'];}
        }
        if($clientId){$where[]='u.client_id=:selected_client';$params['selected_client']=$clientId;}
        $q=$pdo->prepare($sql.' WHERE '.implode(' AND ',$where).' ORDER BY u.code,u.name');$q->execute($params);
        return$q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function canAccess(?int $unitId):bool
    {
        if(self::isGlobal())return true;
        if(!$unitId)return false;
        $q=Database::connection()->prepare("SELECT 1 FROM user_units uu JOIN units u ON u.id=uu.unit_id AND u.status='active' WHERE uu.user_id=:user AND uu.unit_id=:unit AND (:client=0 OR u.client_id=:client2) LIMIT 1");
        $client=(int)(Auth::user()['client_id']??0);$q->execute(['user'=>(int)(Auth::user()['id']??0),'unit'=>$unitId,'client'=>$client,'client2'=>$client]);return(bool)$q->fetchColumn();
    }

    public static function authorize(?int $unitId,string $message='Registro não encontrado.'):void
    {if(!self::canAccess($unitId))throw new DomainException($message);}

    /** SQL condition restricting a unit expression to the authenticated user's units. */
    public static function sqlScope(string $unitExpression,string $prefix='unit_scope'):array
    {
        $user=Auth::user()??[];if(self::isGlobal($user))return['1=1',[]];
        $condition="EXISTS(SELECT 1 FROM user_units {$prefix}_uu JOIN units {$prefix}_u ON {$prefix}_u.id={$prefix}_uu.unit_id AND {$prefix}_u.status='active' WHERE {$prefix}_uu.user_id=:{$prefix}_user AND {$prefix}_uu.unit_id=$unitExpression";
        $params=["{$prefix}_user"=>(int)($user['id']??0)];
        if(!empty($user['client_id'])){$condition.=" AND {$prefix}_u.client_id=:{$prefix}_client";$params["{$prefix}_client"]=(int)$user['client_id'];}
        return[$condition.')',$params];
    }
}
