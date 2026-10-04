<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\Database;

final class SampleTestSynchronizer
{
    public const BACTERIOLOGY_CODE = 'BACTERIOLOGY';

    /** Garante, sem duplicar, os testes ativos configurados para amostras em fluxo. */
    public static function syncSample(int $sampleId): int
    {
        $sql="INSERT INTO sample_tests(sample_id,test_id,status)
              SELECT s.id,t.id,'pending'
                FROM samples s
                JOIN test_blood_components tbc ON tbc.blood_component_id=s.blood_component_id
                JOIN tests t ON t.id=tbc.test_id AND t.status='active'
               WHERE s.id=:sample AND s.purpose='quality_control'
                 AND s.status IN ('received','in_analysis','partial_results')
                 AND NOT EXISTS(SELECT 1 FROM sample_tests st WHERE st.sample_id=s.id AND st.test_id=t.id)";
        $q=Database::connection()->prepare($sql);$q->execute(['sample'=>$sampleId]);return $q->rowCount();
    }

    public static function syncOpenSamples(): int
    {
        $sql="INSERT INTO sample_tests(sample_id,test_id,status)
              SELECT s.id,t.id,'pending'
                FROM samples s
                JOIN test_blood_components tbc ON tbc.blood_component_id=s.blood_component_id
                JOIN tests t ON t.id=tbc.test_id AND t.status='active'
               WHERE s.purpose='quality_control' AND s.status IN ('received','in_analysis','partial_results')
                 AND NOT EXISTS(SELECT 1 FROM sample_tests st WHERE st.sample_id=s.id AND st.test_id=t.id)";
        return Database::connection()->exec($sql);
    }

    public static function syncTransfusionReaction(int $sampleId): int
    {
        $sql="INSERT INTO sample_tests(sample_id,test_id,status)
              SELECT s.id,t.id,'pending' FROM samples s
              JOIN tests t ON t.code='BACTERIOLOGY' AND t.status='active'
              WHERE s.id=:sample AND s.purpose='transfusion_reaction'
                AND s.status IN ('received','in_analysis','partial_results')
                AND NOT EXISTS(SELECT 1 FROM sample_tests st WHERE st.sample_id=s.id AND st.test_id=t.id)";
        $q=Database::connection()->prepare($sql);$q->execute(['sample'=>$sampleId]);return $q->rowCount();
    }
}
