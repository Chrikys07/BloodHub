<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database};
use DomainException;
use PDO;

final class CryoprecipitateResultService
{
    public static function calculateFibrinogenPerUnit(?float $mgDl,?float $dilution,?float $volumeMl):?float
    {
        if($mgDl===null||$dilution===null||$volumeMl===null)return null;
        return (($mgDl*$dilution)/100)*$volumeMl;
    }

    public static function save(array $sample,array $values):array
    {
        $id=(int)$sample['id'];$current=self::state($id);
        $gross=self::value($values,'gross_weight',$current['gross_weight']??null,'Peso bruto');
        $dilution=self::value($values,'dilution',$current['dilution']??null,'Diluição');
        $mgDl=self::value($values,'fibrinogen_mg_dl',$current['fibrinogen_mg_dl']??null,'Fibrinogênio (mg/dL)');
        if($gross!==null&&$gross<=0)throw new DomainException('Peso bruto deve ser maior que zero.');
        if($dilution!==null&&$dilution<=0)throw new DomainException('Diluição deve ser maior que zero.');
        if($mgDl!==null&&$mgDl<=0)throw new DomainException('Fibrinogênio (mg/dL) deve ser maior que zero.');

        $tare=null;$density=null;$volume=null;$warning=null;
        if($gross!==null){
            if(empty($sample['bag_brand_id']))$warning='Marca de bolsa não vinculada; não foi possível calcular o volume.';
            else try{$tare=BagTareResolver::resolve((int)$sample['bag_brand_id'],(int)$sample['blood_component_id']);}catch(DomainException $e){$warning=$e->getMessage();}
            $q=Database::connection()->prepare('SELECT density FROM blood_components WHERE id=:id');$q->execute(['id'=>$sample['blood_component_id']]);$densityRaw=$q->fetchColumn();
            if($densityRaw!==false&&$densityRaw!==null&&(float)$densityRaw>0)$density=(float)$densityRaw;elseif($warning===null)$warning='Densidade não configurada para o hemocomponente CRIO.';
            if($tare&&$density!==null){if($gross<=(float)$tare['tare_weight'])throw new DomainException('O peso bruto deve ser maior que a tara da bolsa.');$volume=($gross-(float)$tare['tare_weight'])/$density;self::saveWeight($sample,$gross,$tare,$density,$volume);}
        }
        $fib=self::calculateFibrinogenPerUnit($mgDl,$dilution,$volume);$pdo=Database::connection();
        $pdo->prepare('INSERT INTO cryoprecipitate_results(sample_id,gross_weight,tare_weight_used,density_used,volume_ml,dilution,fibrinogen_mg_dl,fibrinogen_mg_u,recorded_by) VALUES(:sample,:gross,:tare,:density,:volume,:dilution,:mg_dl,:mg_u,:user) ON DUPLICATE KEY UPDATE gross_weight=VALUES(gross_weight),tare_weight_used=VALUES(tare_weight_used),density_used=VALUES(density_used),volume_ml=VALUES(volume_ml),dilution=VALUES(dilution),fibrinogen_mg_dl=VALUES(fibrinogen_mg_dl),fibrinogen_mg_u=VALUES(fibrinogen_mg_u),recorded_by=VALUES(recorded_by),recorded_at=NOW()')->execute(['sample'=>$id,'gross'=>$gross,'tare'=>$tare['tare_weight']??null,'density'=>$density,'volume'=>$volume,'dilution'=>$dilution,'mg_dl'=>$mgDl,'mg_u'=>$fib,'user'=>Auth::user()['id']??null]);
        if($fib!==null)TestResultService::saveNumeric($id,TestResultService::CODES['fibrinogen'],$fib);else self::clearCalculatedTest($id,TestResultService::CODES['fibrinogen']);
        if($volume===null){self::clearCalculatedTest($id,TestResultService::CODES['volume']);$pdo->prepare('DELETE FROM sample_weight_results WHERE sample_id=:id')->execute(['id'=>$id]);}
        return compact('gross','dilution','mgDl','volume','fib','warning');
    }

    public static function recalculate(int $sampleId):void
    {
        $pdo=Database::connection();$q=$pdo->prepare('SELECT s.* FROM samples s JOIN blood_components bc ON bc.id=s.blood_component_id AND bc.code=\'CRIO\' WHERE s.id=:id AND s.status<>\'completed\'');$q->execute(['id'=>$sampleId]);$sample=$q->fetch(PDO::FETCH_ASSOC);if(!$sample)return;
        $state=self::state($sampleId);self::save($sample,['gross_weight'=>$state['gross_weight']??'','dilution'=>$state['dilution']??'','fibrinogen_mg_dl'=>$state['fibrinogen_mg_dl']??'']);
    }

    public static function state(int $sampleId):array
    {$q=Database::connection()->prepare('SELECT * FROM cryoprecipitate_results WHERE sample_id=:id');$q->execute(['id'=>$sampleId]);return $q->fetch(PDO::FETCH_ASSOC)?:[];}

    private static function value(array $values,string $key,mixed $fallback,string $label):?float
    {if(!array_key_exists($key,$values))return $fallback===null?null:(float)$fallback;$raw=trim((string)$values[$key]);if($raw==='')return null;return self::decimal($raw,$label);}
    private static function decimal(string $raw,string $label):float
    {$v=str_replace(',','.',trim($raw));if(!preg_match('/^(?:\d+(?:\.\d*)?|\.\d+)$/',$v))throw new DomainException("{$label} deve ser numérico.");$n=(float)$v;if(!is_finite($n))throw new DomainException("{$label} inválido.");return $n;}
    private static function saveWeight(array $sample,float $gross,array $tare,float $density,float $volume):void
    {$pdo=Database::connection();$net=$gross-(float)$tare['tare_weight'];$pdo->prepare('INSERT INTO sample_weight_results(sample_id,gross_weight,bag_brand_tare_id,tare_weight_used,net_weight,density_used,volume_ml,recorded_by) VALUES(:sample,:gross,:tare_id,:tare,:net,:density,:volume,:user) ON DUPLICATE KEY UPDATE gross_weight=VALUES(gross_weight),bag_brand_tare_id=VALUES(bag_brand_tare_id),tare_weight_used=VALUES(tare_weight_used),net_weight=VALUES(net_weight),density_used=VALUES(density_used),volume_ml=VALUES(volume_ml),recorded_by=VALUES(recorded_by),recorded_at=NOW()')->execute(['sample'=>$sample['id'],'gross'=>$gross,'tare_id'=>$tare['tare_id'],'tare'=>$tare['tare_weight'],'net'=>$net,'density'=>$density,'volume'=>$volume,'user'=>Auth::user()['id']??null]);$pdo->prepare('UPDATE bag_brand_tares SET locked_at=COALESCE(locked_at,NOW()) WHERE id=:id')->execute(['id'=>$tare['tare_id']]);if(TestResultService::configuredTest((int)$sample['id'],TestResultService::CODES['volume']))TestResultService::saveNumeric((int)$sample['id'],TestResultService::CODES['volume'],$volume);}
    private static function clearCalculatedTest(int $sampleId,string $code):void
    {Database::connection()->prepare('DELETE tr FROM test_results tr JOIN sample_tests st ON st.id=tr.sample_test_id JOIN tests t ON t.id=st.test_id WHERE st.sample_id=:sample AND t.code=:code')->execute(['sample'=>$sampleId,'code'=>$code]);}
}
