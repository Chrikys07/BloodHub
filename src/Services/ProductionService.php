<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\Auth;
use BloodHub\Core\Database;
use DomainException;
use PDO;

final class ProductionService
{
    public static function eligibleComponents():array
    {
        return Database::connection()->query("SELECT DISTINCT bc.id,bc.code,bc.name FROM blood_components bc JOIN test_blood_components tbc ON tbc.blood_component_id=bc.id JOIN tests t ON t.id=tbc.test_id WHERE bc.status='active' AND t.status='active' ORDER BY bc.code,bc.name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getDailyProduction(int $unitId,string $date):array
    {
        $q=Database::connection()->prepare('SELECT blood_component_id,quantity FROM production_records WHERE unit_id=:unit AND production_date=:date');
        $q->execute(['unit'=>$unitId,'date'=>$date]);
        $out=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$out[(int)$row['blood_component_id']]=(int)$row['quantity'];return$out;
    }

    public static function saveDailyProduction(int $unitId,string $date,array $quantities):void
    {
        $pdo=Database::connection();$userId=(int)(Auth::user()['id']??0);$pdo->beginTransaction();
        try{
            $existing=$pdo->prepare('SELECT id,quantity FROM production_records WHERE unit_id=:unit AND production_date=:date AND blood_component_id=:component FOR UPDATE');
            $insert=$pdo->prepare('INSERT INTO production_records(unit_id,production_date,blood_component_id,quantity,created_by,updated_by) VALUES(:unit,:date,:component,:quantity,:user,:user2)');
            $update=$pdo->prepare('UPDATE production_records SET quantity=:quantity,updated_by=:user WHERE id=:id');
            $delete=$pdo->prepare('DELETE FROM production_records WHERE id=:id');
            foreach($quantities as $componentId=>$quantity){
                $existing->execute(['unit'=>$unitId,'date'=>$date,'component'=>$componentId]);$row=$existing->fetch(PDO::FETCH_ASSOC);
                if($quantity===null){if($row){$delete->execute(['id'=>$row['id']]);self::audit('PRODUCTION_UPDATED',(int)$row['id'],$unitId,$date,(int)$componentId,(int)$row['quantity'],null);}continue;}
                if($row){if((int)$row['quantity']===$quantity)continue;$update->execute(['quantity'=>$quantity,'user'=>$userId,'id'=>$row['id']]);self::audit('PRODUCTION_UPDATED',(int)$row['id'],$unitId,$date,(int)$componentId,(int)$row['quantity'],$quantity);}
                else{$insert->execute(['unit'=>$unitId,'date'=>$date,'component'=>$componentId,'quantity'=>$quantity,'user'=>$userId,'user2'=>$userId]);$id=(int)$pdo->lastInsertId();self::audit('PRODUCTION_CREATED',$id,$unitId,$date,(int)$componentId,null,$quantity);}
            }
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    public static function getMonthlyProduction(int $unitId,int $year,int $month,?int $componentId=null):array
    {
        $from=sprintf('%04d-%02d-01',$year,$month);$to=date('Y-m-d',strtotime($from.' +1 month'));
        $sql='SELECT pr.*,bc.code component_code,bc.name component_name,u.name responsible_name FROM production_records pr JOIN blood_components bc ON bc.id=pr.blood_component_id LEFT JOIN users u ON u.id=pr.updated_by WHERE pr.unit_id=:unit AND pr.production_date>=:from AND pr.production_date<:to';$p=['unit'=>$unitId,'from'=>$from,'to'=>$to];
        if($componentId){$sql.=' AND pr.blood_component_id=:component';$p['component']=$componentId;}$sql.=' ORDER BY pr.production_date DESC,bc.code';$q=Database::connection()->prepare($sql);$q->execute($p);return$q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getProductionByComponent(int $unitId,int $year,int $month):array
    {
        $rows=self::getMonthlyProduction($unitId,$year,$month);$out=[];foreach($rows as$r){$id=(int)$r['blood_component_id'];if(!isset($out[$id]))$out[$id]=['id'=>$id,'code'=>$r['component_code'],'name'=>$r['component_name'],'total'=>0,'days'=>[]];$out[$id]['total']+=(int)$r['quantity'];$out[$id]['days'][(int)substr($r['production_date'],8,2)]=(int)$r['quantity'];}return array_values($out);
    }

    public static function getProductionTotal(int $unitId,string $from,string $to):int
    { $q=Database::connection()->prepare('SELECT COALESCE(SUM(quantity),0) FROM production_records WHERE unit_id=:unit AND production_date BETWEEN :from AND :to');$q->execute(['unit'=>$unitId,'from'=>$from,'to'=>$to]);return(int)$q->fetchColumn(); }

    private static function audit(string $action,int $id,int $unit,string $date,int $component,?int $old,?int $new):void
    { $base=['unit_id'=>$unit,'production_date'=>$date,'blood_component_id'=>$component];Auth::registerAudit($action,'production_records',$id,$old===null?null:$base+['quantity'=>$old],$new===null?$base+['quantity'=>null]:$base+['quantity'=>$new]); }
}
