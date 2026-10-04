<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database};
use DomainException;
use PDO;

final class ClientScopeService
{
    public const TYPES=['all','internal','external'];

    public static function resolve(?string $type=null,mixed $clientId=null):array
    {
        $user=Auth::user()??[];
        $restricted=self::restrictedClientId($user);
        if($restricted){
            $client=self::client($restricted);
            if(!$client)throw new DomainException('Cliente autorizado não encontrado.');
            return ['type'=>$client['client_type'],'client_id'=>$restricted,'client'=>$client,'restricted'=>true];
        }
        $type=in_array($type,self::TYPES,true)?$type:'all';
        $id=filter_var($clientId,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:null;
        $client=$id?self::client((int)$id):null;
        if($id&&!$client)throw new DomainException('Cliente inválido ou fora do escopo autorizado.');
        if($client&&$type!=='all'&&$client['client_type']!==$type)throw new DomainException('O cliente não pertence ao tipo selecionado.');
        if($client)$type=$client['client_type'];
        return ['type'=>$type,'client_id'=>$id?(int)$id:null,'client'=>$client,'restricted'=>false];
    }

    public static function getVisibleClientsForUser(bool $includeInactive=false):array
    {
        $scope=self::resolve();$sql='SELECT id,name,client_type,status FROM clients';$params=[];$where=[];
        if($scope['restricted']){$where[]='id=:id';$params['id']=$scope['client_id'];}
        if(!$includeInactive)$where[]="status='active'";
        if($where)$sql.=' WHERE '.implode(' AND ',$where);
        $q=Database::connection()->prepare($sql.' ORDER BY name');$q->execute($params);return$q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getVisibleUnitsForUser():array{return UnitAccessService::visibleUnits();}
    public static function getInternalClients():array{return array_values(array_filter(self::getVisibleClientsForUser(),fn($c)=>$c['client_type']==='internal'));}
    public static function getExternalClients():array{return array_values(array_filter(self::getVisibleClientsForUser(),fn($c)=>$c['client_type']==='external'));}
    public static function canAccessClient(int $id):bool{try{$s=self::resolve('all',$id);return$s['client_id']===$id;}catch(\Throwable){return false;}}
    public static function matches(array$scope,?int$clientId,?string$clientType):bool
    {if($scope['client_id'])return$clientId===(int)$scope['client_id'];if($scope['type']==='all')return true;return$clientType===$scope['type'];}

    /** Returns a parameterized condition for an SQL expression resolving a client id. */
    public static function applyClientScope(string $clientExpression,array $scope,string $prefix='scope'):array
    {
        if($scope['client_id'])return ["$clientExpression = :{$prefix}_client",["{$prefix}_client"=>$scope['client_id']]];
        if(in_array($scope['type'],['internal','external'],true))return ["EXISTS(SELECT 1 FROM clients {$prefix}_c WHERE {$prefix}_c.id=$clientExpression AND {$prefix}_c.client_type=:{$prefix}_type)",["{$prefix}_type"=>$scope['type']]];
        return ['1=1',[]];
    }

    private static function restrictedClientId(array$user):?int
    {
        if(in_array($user['role_slug']??'',['administrador','lcqh','gestao'],true))return null;
        $id=(int)($user['client_id']??0);return$id?:null;
    }
    private static function client(int$id):?array{$q=Database::connection()->prepare('SELECT id,name,client_type,status FROM clients WHERE id=:id');$q->execute(['id'=>$id]);return$q->fetch(PDO::FETCH_ASSOC)?:null;}
}
