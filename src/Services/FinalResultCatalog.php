<?php
declare(strict_types=1);
namespace BloodHub\Services;
use BloodHub\Core\Database;
use PDO;

/** Fonte canônica compartilhável da classificação gerencial já usada por Dashboard e Relatório. */
final class FinalResultCatalog
{
    public static function all(bool $activeOnly=true):array
    {
        $sql="SELECT id,code,name,unit,status FROM tests WHERE is_final_result=1".($activeOnly?" AND status='active'":'')." ORDER BY name";
        return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function isFinal(int $testId):bool
    {
        $q=Database::connection()->prepare("SELECT 1 FROM tests WHERE id=:id AND status='active' AND is_final_result=1");$q->execute(['id'=>$testId]);return(bool)$q->fetchColumn();
    }
}
