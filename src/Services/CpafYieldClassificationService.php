<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database};
use PDO;

final class CpafYieldClassificationService
{
    public const INSUFFICIENT='INSUFFICIENT_YIELD';
    public const SIMPLE='CPAF_SIMPLE';
    public const DOUBLE='CPAF_DOUBLE';
    public const LABELS=[self::INSUFFICIENT=>'Rendimento insuficiente',self::SIMPLE=>'CPAF Simples',self::DOUBLE=>'CPAF Dupla'];

    public static function classify(float $value,float $simpleMin,float $doubleMin):array
    {
        $code=$value<$simpleMin?self::INSUFFICIENT:($value<$doubleMin?self::SIMPLE:self::DOUBLE);
        return ['code'=>$code,'label'=>self::LABELS[$code]];
    }

    public static function activeRule(int $componentId,?string $date=null):?array
    {
        $date=$date?:date('Y-m-d');$q=Database::connection()->prepare("SELECT * FROM cpaf_yield_classification_rules WHERE blood_component_id=:component AND active=1 AND (effective_from IS NULL OR effective_from<=:d1) AND (effective_to IS NULL OR effective_to>=:d2) ORDER BY effective_from DESC,id DESC LIMIT 1");$q->execute(['component'=>$componentId,'d1'=>$date,'d2'=>$date]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public static function recalculate(int $sampleId,bool $finalize=false):?array
    {
        $pdo=Database::connection();$q=$pdo->prepare("SELECT s.id,s.status,s.blood_component_id,bc.code,tr.id result_id,tr.result_value_numeric platelets_per_unit FROM samples s JOIN blood_components bc ON bc.id=s.blood_component_id LEFT JOIN sample_tests st ON st.sample_id=s.id AND st.test_id=(SELECT id FROM tests WHERE code='PLATELETS_PER_UNIT' LIMIT 1) LEFT JOIN test_results tr ON tr.sample_test_id=st.id WHERE s.id=:id ORDER BY tr.id DESC LIMIT 1");$q->execute(['id'=>$sampleId]);$sample=$q->fetch(PDO::FETCH_ASSOC);if(!$sample||$sample['code']!=='CPAF'||$sample['platelets_per_unit']===null)return null;
        $oldQ=$pdo->prepare('SELECT * FROM cpaf_yield_classifications WHERE sample_id=:id');$oldQ->execute(['id'=>$sampleId]);$old=$oldQ->fetch(PDO::FETCH_ASSOC);if($old&&($old['finalized_at']!==null||$sample['status']==='completed'))return self::present($old);
        $rule=self::activeRule((int)$sample['blood_component_id']);if(!$rule)return null;$value=(float)$sample['platelets_per_unit'];$classification=self::classify($value,(float)$rule['simple_min_platelets'],(float)$rule['double_min_platelets']);$snapshot=['rule_id'=>(int)$rule['id'],'version_number'=>(int)$rule['version_number'],'rule_kind'=>$rule['rule_kind'],'simple_min_platelets'=>$rule['simple_min_platelets'],'double_min_platelets'=>$rule['double_min_platelets'],'effective_from'=>$rule['effective_from'],'effective_to'=>$rule['effective_to'],'source_name'=>$rule['source_name'],'source_reference'=>$rule['source_reference'],'notes'=>$rule['notes'],'extension_config_json'=>$rule['extension_config_json']];
        $data=['sample'=>$sampleId,'result'=>$sample['result_id'],'rule'=>$rule['id'],'value'=>$value,'code'=>$classification['code'],'label'=>$classification['label'],'simple'=>$rule['simple_min_platelets'],'double'=>$rule['double_min_platelets'],'kind'=>$rule['rule_kind'],'from'=>$rule['effective_from'],'to'=>$rule['effective_to'],'source'=>$rule['source_name'],'reference'=>$rule['source_reference'],'notes'=>$rule['notes'],'snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'finalize'=>$finalize?date('Y-m-d H:i:s'):null,'user'=>$finalize?(Auth::user()['id']??null):null];
        $pdo->prepare('INSERT INTO cpaf_yield_classifications(sample_id,platelet_test_result_id,rule_id,platelets_per_unit,classification_code,classification_label,simple_min_snapshot,double_min_snapshot,rule_kind_snapshot,effective_from_snapshot,effective_to_snapshot,source_name_snapshot,source_reference_snapshot,notes_snapshot,rule_snapshot_json,classified_at,finalized_at,finalized_by) VALUES(:sample,:result,:rule,:value,:code,:label,:simple,:double,:kind,:from,:to,:source,:reference,:notes,:snapshot,NOW(),:finalize,:user) ON DUPLICATE KEY UPDATE platelet_test_result_id=VALUES(platelet_test_result_id),rule_id=VALUES(rule_id),platelets_per_unit=VALUES(platelets_per_unit),classification_code=VALUES(classification_code),classification_label=VALUES(classification_label),simple_min_snapshot=VALUES(simple_min_snapshot),double_min_snapshot=VALUES(double_min_snapshot),rule_kind_snapshot=VALUES(rule_kind_snapshot),effective_from_snapshot=VALUES(effective_from_snapshot),effective_to_snapshot=VALUES(effective_to_snapshot),source_name_snapshot=VALUES(source_name_snapshot),source_reference_snapshot=VALUES(source_reference_snapshot),notes_snapshot=VALUES(notes_snapshot),rule_snapshot_json=VALUES(rule_snapshot_json),classified_at=NOW(),finalized_at=VALUES(finalized_at),finalized_by=VALUES(finalized_by)')->execute($data);
        Auth::registerAudit($finalize?'CPAF_YIELD_CLASSIFICATION_FINALIZED':'CPAF_YIELD_CLASSIFICATION_RECALCULATED','samples',$sampleId,$old?:null,$data);return $classification+['platelets_per_unit'=>$value,'simple_min'=>(float)$rule['simple_min_platelets'],'double_min'=>(float)$rule['double_min_platelets'],'version_number'=>(int)$rule['version_number'],'finalized'=>$finalize];
    }

    public static function state(int $sampleId):?array
    {$q=Database::connection()->prepare('SELECT c.*,r.version_number FROM cpaf_yield_classifications c LEFT JOIN cpaf_yield_classification_rules r ON r.id=c.rule_id WHERE c.sample_id=:id');$q->execute(['id'=>$sampleId]);$row=$q->fetch(PDO::FETCH_ASSOC);return $row?self::present($row):null;}
    private static function present(array $row):array{return ['code'=>$row['classification_code'],'label'=>$row['classification_label'],'platelets_per_unit'=>(float)$row['platelets_per_unit'],'simple_min'=>(float)$row['simple_min_snapshot'],'double_min'=>(float)$row['double_min_snapshot'],'version_number'=>(int)($row['version_number']??1),'finalized'=>$row['finalized_at']!==null,'source'=>$row['source_name_snapshot']??null,'reference'=>$row['source_reference_snapshot']??null];}
}
