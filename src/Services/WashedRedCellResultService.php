<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database};
use DomainException;
use PDO;

final class WashedRedCellResultService
{
    public const COMPONENT='CHL';
    public static function supports(string $code):bool{return strtoupper(trim($code))===self::COMPONENT;}

    public static function state(int $sampleId):array
    {
        $q=Database::connection()->prepare('SELECT * FROM washed_red_cell_results WHERE sample_id=:id');$q->execute(['id'=>$sampleId]);
        return $q->fetch(PDO::FETCH_ASSOC)?:array_fill_keys(['initial_weight_g','initial_volume_ml','final_weight_g','final_volume_ml','initial_hematocrit_pct','final_hematocrit_pct','recovery_pct','hemoglobin_g_dl','hemoglobin_per_unit_g','free_hemoglobin_g_dl','hemolysis_pct','standard_absorbance','protein_absorbance','residual_protein_g_u','tare_weight_used','density_used'],null);
    }

    public static function save(array $sample,array $values):array
    {
        $id=(int)$sample['id'];$tare=BagTareResolver::resolve((int)$sample['bag_brand_id'],(int)$sample['blood_component_id']);
        if(!$tare)throw new DomainException(BagTareResolver::NOT_CONFIGURED);
        $pdo=Database::connection();$q=$pdo->prepare('SELECT density FROM blood_components WHERE id=:id');$q->execute(['id'=>$sample['blood_component_id']]);$density=WashedRedCellCalculator::decimal($q->fetchColumn());
        $old=self::state($id);$value=static function(string $key)use($values,$old):?float{return array_key_exists($key,$values)?WashedRedCellCalculator::decimal($values[$key]):WashedRedCellCalculator::decimal($old[$key]??null);};
        $initialWeight=$value('initial_weight_g');$finalWeight=$value('final_weight_g');$initialHct=$value('initial_hematocrit_pct');$finalHct=$value('final_hematocrit_pct');$hb=$value('hemoglobin_g_dl');$standard=$value('standard_absorbance');$protein=$value('protein_absorbance');
        $initialVolume=WashedRedCellCalculator::calculateVolume($initialWeight,(float)$tare['tare_weight'],$density);$finalVolume=WashedRedCellCalculator::calculateVolume($finalWeight,(float)$tare['tare_weight'],$density);
        $recovery=WashedRedCellCalculator::calculateRecovery($initialHct,$initialVolume,$finalHct,$finalVolume);$hbUnit=WashedRedCellCalculator::calculateHemoglobinPerUnit($hb,$finalVolume);
        $free=self::numericResult($id,'FREE_HEMOGLOBIN');$hemolysis=WashedRedCellCalculator::calculateHemolysis($free,$hb,$finalHct);$residual=WashedRedCellCalculator::calculateResidualProtein($standard,$protein,$finalVolume);
        $data=['initial_weight_g'=>$initialWeight,'initial_volume_ml'=>$initialVolume,'final_weight_g'=>$finalWeight,'final_volume_ml'=>$finalVolume,'initial_hematocrit_pct'=>$initialHct,'final_hematocrit_pct'=>$finalHct,'recovery_pct'=>$recovery,'hemoglobin_g_dl'=>$hb,'hemoglobin_per_unit_g'=>$hbUnit,'free_hemoglobin_g_dl'=>$free,'hemolysis_pct'=>$hemolysis,'standard_absorbance'=>$standard,'protein_absorbance'=>$protein,'residual_protein_g_u'=>$residual,'tare_weight_used'=>(float)$tare['tare_weight'],'density_used'=>$density,'bag_brand_tare_id'=>(int)$tare['tare_id']];
        $sql='INSERT INTO washed_red_cell_results(sample_id,initial_weight_g,initial_volume_ml,final_weight_g,final_volume_ml,initial_hematocrit_pct,final_hematocrit_pct,recovery_pct,hemoglobin_g_dl,hemoglobin_per_unit_g,free_hemoglobin_g_dl,hemolysis_pct,standard_absorbance,protein_absorbance,residual_protein_g_u,bag_brand_tare_id,tare_weight_used,density_used,recorded_by) VALUES(:sample,:initial_weight_g,:initial_volume_ml,:final_weight_g,:final_volume_ml,:initial_hematocrit_pct,:final_hematocrit_pct,:recovery_pct,:hemoglobin_g_dl,:hemoglobin_per_unit_g,:free_hemoglobin_g_dl,:hemolysis_pct,:standard_absorbance,:protein_absorbance,:residual_protein_g_u,:bag_brand_tare_id,:tare_weight_used,:density_used,:user) ON DUPLICATE KEY UPDATE initial_weight_g=VALUES(initial_weight_g),initial_volume_ml=VALUES(initial_volume_ml),final_weight_g=VALUES(final_weight_g),final_volume_ml=VALUES(final_volume_ml),initial_hematocrit_pct=VALUES(initial_hematocrit_pct),final_hematocrit_pct=VALUES(final_hematocrit_pct),recovery_pct=VALUES(recovery_pct),hemoglobin_g_dl=VALUES(hemoglobin_g_dl),hemoglobin_per_unit_g=VALUES(hemoglobin_per_unit_g),free_hemoglobin_g_dl=VALUES(free_hemoglobin_g_dl),hemolysis_pct=VALUES(hemolysis_pct),standard_absorbance=VALUES(standard_absorbance),protein_absorbance=VALUES(protein_absorbance),residual_protein_g_u=VALUES(residual_protein_g_u),bag_brand_tare_id=VALUES(bag_brand_tare_id),tare_weight_used=VALUES(tare_weight_used),density_used=VALUES(density_used),recorded_by=VALUES(recorded_by),recorded_at=NOW()';
        $pdo->prepare($sql)->execute(['sample'=>$id,'user'=>Auth::user()['id']??null]+$data);
        foreach(['HEMATOCRIT'=>$finalHct,'HEMOGLOBIN'=>$hb,'VOLUME'=>$finalVolume,'HEMOGLOBIN_PER_UNIT'=>$hbUnit,'RECOVERY'=>$recovery,'RESIDUAL_PROTEIN'=>$residual] as $code=>$number)if($number!==null&&TestResultService::configuredTest($id,$code))TestResultService::saveNumeric($id,$code,$number);
        CalculatedTestService::recalculateHemolysis($id);self::refreshCalculatedSnapshot($id);$current=self::state($id);Auth::registerAudit('quality_chl_result.save','washed_red_cell_results',(int)$current['id'],isset($old['id'])?$old:null,$current);return $current;
    }

    public static function refreshCalculatedSnapshot(int $sampleId):void
    {
        $state=self::state($sampleId);if(empty($state['id']))return;$free=self::numericResult($sampleId,'FREE_HEMOGLOBIN');$hemolysis=WashedRedCellCalculator::calculateHemolysis($free,WashedRedCellCalculator::decimal($state['hemoglobin_g_dl']),WashedRedCellCalculator::decimal($state['final_hematocrit_pct']));
        Database::connection()->prepare('UPDATE washed_red_cell_results SET free_hemoglobin_g_dl=:free,hemolysis_pct=:hemolysis WHERE sample_id=:id')->execute(['free'=>$free,'hemolysis'=>$hemolysis,'id'=>$sampleId]);
    }
    public static function recalculate(int $sampleId):void
    {$q=Database::connection()->prepare("SELECT s.* FROM samples s JOIN blood_components bc ON bc.id=s.blood_component_id AND bc.code='CHL' WHERE s.id=:id");$q->execute(['id'=>$sampleId]);if($sample=$q->fetch(PDO::FETCH_ASSOC))self::save($sample,[]);}
    public static function completionRequirements(int $sampleId):array
    {
        $q=Database::connection()->prepare("SELECT s.lcqh_code,w.* FROM samples s JOIN blood_components bc ON bc.id=s.blood_component_id AND bc.code='CHL' LEFT JOIN washed_red_cell_results w ON w.sample_id=s.id WHERE s.id=:id");$q->execute(['id'=>$sampleId]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return[];
        $fields=['lcqh_code'=>['LCQH_CODE','Código LCQH'],'initial_weight_g'=>['CHL_INITIAL_WEIGHT','Peso origem'],'final_weight_g'=>['CHL_FINAL_WEIGHT','Peso final'],'initial_hematocrit_pct'=>['CHL_INITIAL_HCT','Ht inicial'],'standard_absorbance'=>['CHL_STANDARD_ABS','Abs padrão'],'protein_absorbance'=>['CHL_PROTEIN_ABS','Abs proteína']];$missing=[];foreach($fields as $field=>[$code,$name])if($r[$field]===null||trim((string)$r[$field])==='')$missing[]=['code'=>$code,'name'=>$name];return $missing;
    }
    private static function numericResult(int $sampleId,string $code):?float{$q=Database::connection()->prepare('SELECT tr.result_value_numeric FROM sample_tests st JOIN tests t ON t.id=st.test_id AND t.code=:code JOIN test_results tr ON tr.sample_test_id=st.id WHERE st.sample_id=:id ORDER BY tr.id DESC LIMIT 1');$q->execute(['code'=>$code,'id'=>$sampleId]);$v=$q->fetchColumn();return $v===false||$v===null?null:(float)$v;}
}
