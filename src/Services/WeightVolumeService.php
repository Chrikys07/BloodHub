<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database};
use DomainException;
use PDO;

final class WeightVolumeService
{
    public static function save(array $sample,mixed $rawGross):array
    {
        $gross=self::decimal($rawGross);
        if($gross===null||$gross<=0)throw new DomainException('Peso bruto deve ser numérico e maior que zero.');
        if(empty($sample['bag_brand_id']))throw new DomainException('Referência de bolsa não vinculada à amostra.');
        $tare=BagTareResolver::resolve((int)$sample['bag_brand_id'],(int)$sample['blood_component_id']);
        if(!$tare)throw new DomainException(BagTareResolver::NOT_CONFIGURED);
        $pdo=Database::connection();$q=$pdo->prepare('SELECT density FROM blood_components WHERE id=:id');$q->execute(['id'=>$sample['blood_component_id']]);$density=$q->fetchColumn();
        if($density===false||$density===null||(float)$density<=0)throw new DomainException('Densidade não configurada.');
        $net=$gross-(float)$tare['tare_weight'];if($net<=0)throw new DomainException('O peso bruto deve ser maior que a tara da bolsa.');$volume=$net/(float)$density;
        $old=$pdo->prepare('SELECT * FROM sample_weight_results WHERE sample_id=:id');$old->execute(['id'=>$sample['id']]);$before=$old->fetch(PDO::FETCH_ASSOC);
        $pdo->prepare('INSERT INTO sample_weight_results(sample_id,gross_weight,bag_brand_tare_id,tare_weight_used,net_weight,density_used,volume_ml,recorded_by) VALUES(:sample,:gross,:tare_id,:tare,:net,:density,:volume,:user) ON DUPLICATE KEY UPDATE gross_weight=VALUES(gross_weight),bag_brand_tare_id=VALUES(bag_brand_tare_id),tare_weight_used=VALUES(tare_weight_used),net_weight=VALUES(net_weight),density_used=VALUES(density_used),volume_ml=VALUES(volume_ml),recorded_by=VALUES(recorded_by),recorded_at=NOW()')->execute(['sample'=>$sample['id'],'gross'=>$gross,'tare_id'=>$tare['tare_id'],'tare'=>$tare['tare_weight'],'net'=>$net,'density'=>$density,'volume'=>$volume,'user'=>Auth::user()['id']??null]);
        $pdo->prepare('UPDATE bag_brand_tares SET locked_at=COALESCE(locked_at,NOW()) WHERE id=:id')->execute(['id'=>$tare['tare_id']]);
        if(TestResultService::configuredTest((int)$sample['id'],TestResultService::CODES['volume']))TestResultService::saveNumeric((int)$sample['id'],TestResultService::CODES['volume'],$volume);
        $after=['gross_weight'=>$gross,'bag_brand_tare_id'=>(int)$tare['tare_id'],'tare_weight_used'=>(float)$tare['tare_weight'],'net_weight'=>$net,'density_used'=>(float)$density,'volume_ml'=>$volume];
        Auth::registerAudit('laboratory_weight.save','samples',(int)$sample['id'],$before?:null,$after);return $after;
    }
    private static function decimal(mixed $raw):?float{$v=trim((string)$raw);if($v==='')return null;$v=str_replace(',','.',str_replace('.','',$v));return is_numeric($v)&&is_finite((float)$v)?(float)$v:null;}
}
