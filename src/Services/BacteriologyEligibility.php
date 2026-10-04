<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\Database;
use PDO;

final class BacteriologyEligibility
{
    public const TEST_CODE = 'BACTERIOLOGY';
    public const AUTOMATIC_POOL_COMPONENT_CODE = 'CP';

    public static function isEligible(int|array $sample): bool
    {
        $sampleId=is_array($sample)?(int)($sample['id']??0):(int)$sample;
        if($sampleId<=0)return false;
        $q=Database::connection()->prepare("SELECT EXISTS(SELECT 1 FROM samples s JOIN test_blood_components x ON x.blood_component_id=s.blood_component_id JOIN tests t ON t.id=x.test_id AND t.code=:code AND t.status='active' WHERE s.id=:sample AND s.purpose='quality_control')");
        $q->execute(['code'=>self::TEST_CODE,'sample'=>$sampleId]);
        return (bool)$q->fetchColumn();
    }

    public static function ensurePending(int $sampleId): int
    {
        $sql="INSERT INTO sample_tests(sample_id,test_id,status) SELECT s.id,t.id,'pending' FROM samples s JOIN test_blood_components x ON x.blood_component_id=s.blood_component_id JOIN tests t ON t.id=x.test_id AND t.code=:code AND t.status='active' WHERE s.id=:sample AND s.purpose='quality_control' AND s.status IN ('received','in_analysis','partial_results') AND NOT EXISTS(SELECT 1 FROM sample_tests st WHERE st.sample_id=s.id AND st.test_id=t.id)";
        $q=Database::connection()->prepare($sql);$q->execute(['code'=>self::TEST_CODE,'sample'=>$sampleId]);return $q->rowCount();
    }

    public static function syncOpenSamples(): int
    {
        $sql="INSERT INTO sample_tests(sample_id,test_id,status) SELECT s.id,t.id,'pending' FROM samples s JOIN test_blood_components x ON x.blood_component_id=s.blood_component_id JOIN tests t ON t.id=x.test_id AND t.code='BACTERIOLOGY' AND t.status='active' WHERE s.purpose='quality_control' AND s.status IN ('received','in_analysis','partial_results') AND NOT EXISTS(SELECT 1 FROM sample_tests st WHERE st.sample_id=s.id AND st.test_id=t.id)";
        return (int)Database::connection()->exec($sql);
    }

    public static function eligibleComponents(): array
    {
        return Database::connection()->query("SELECT DISTINCT bc.id,bc.code,bc.name FROM blood_components bc JOIN test_blood_components x ON x.blood_component_id=bc.id JOIN tests t ON t.id=x.test_id AND t.code='BACTERIOLOGY' AND t.status='active' ORDER BY bc.code")->fetchAll(PDO::FETCH_ASSOC);
    }
}
