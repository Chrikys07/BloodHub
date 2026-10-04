<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database};
use DomainException;
use PDO;

final class LaboratoryResultService
{
    public static function save(array $sample,array $values,string $context):void
    {
        if(!in_array($context,['quality_control','validation'],true)||($sample['purpose']??'')!==$context)throw new DomainException('Contexto laboratorial inválido.');
        if(($sample['status']??'')==='completed')throw new DomainException('Amostra concluída está em modo somente leitura.');
        $id=(int)$sample['id'];self::saveIdentity($sample,$values);$code=strtoupper((string)$sample['component_code']);
        if(WashedRedCellResultService::supports($code)){WashedRedCellResultService::save($sample,$values);self::finish($id);return;}
        if(PlateletResultService::supports($code)){PlateletResultService::save($id,$values);self::finish($id);return;}
        if(in_array($code,['PF','PF24'],true)){FreshPlasmaResultService::save($id,$values);self::finish($id);return;}
        if($code==='CRIO'){CryoprecipitateResultService::save($sample,$values);self::finish($id);return;}
        if(array_key_exists('gross_weight',$values)&&trim((string)$values['gross_weight'])!=='')WeightVolumeService::save($sample,$values['gross_weight']);
        foreach(['hematocrit'=>TestResultService::CODES['hematocrit'],'hemoglobin'=>TestResultService::CODES['hemoglobin'],'factor_viii'=>TestResultService::CODES['factor_viii']] as$field=>$test)if(array_key_exists($field,$values)&&trim((string)$values[$field])!=='')TestResultService::saveNumeric($id,$test,$values[$field]);
        if(RedCellResidualLeukocyteService::supports($code))RedCellResidualLeukocyteService::save($id,$values);
        if(isset($values['bacteriology'])&&trim((string)$values['bacteriology'])!=='')TestResultService::saveText($id,'BACTERIOLOGY',mb_strtolower(trim((string)$values['bacteriology'])));
        if(isset($values['absorbances'])&&is_array($values['absorbances'])&&array_filter($values['absorbances'],static fn($v)=>trim((string)$v)!==''))HemolysisResultService::save($id,$values['absorbances']);
        TestResultService::recalculateHemoglobinPerUnit($id);CalculatedTestService::recalculateHemolysis($id);self::finish($id);
    }
    private static function saveIdentity(array $sample,array $values):void{$lcqh=array_key_exists('lcqh_code',$values)?trim((string)$values['lcqh_code']):(string)($sample['lcqh_code']??'');if(mb_strlen($lcqh)>80)throw new DomainException('Código LCQH deve ter no máximo 80 caracteres.');Database::connection()->prepare('UPDATE samples SET lcqh_code=:code WHERE id=:id')->execute(['code'=>$lcqh?:null,'id'=>$sample['id']]);}
    private static function finish(int $id):void{HemolysisResultService::synchronizeStage($id);SpecificationEvaluator::evaluateSampleResults($id);Database::connection()->prepare("UPDATE samples SET status='partial_results' WHERE id=:id AND status IN('received','in_analysis')")->execute(['id'=>$id]);Auth::registerAudit('LABORATORY_RESULTS_SAVED','samples',$id,null,['context'=>'validation']);}
}
