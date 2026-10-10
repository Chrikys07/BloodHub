<?php
declare(strict_types=1);

namespace BloodHub\Core;

use DomainException;
use PDO;

/** Valida o lote operacional dos insumos, sem reservar ou consumir estoque. */
final class SupplyInUseValidator
{
    public static function validateTest(int $testId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT ts.supply_id, s.name supply_name, s.status supply_status,
                    sl.id lot_id, sl.lot_number, sl.expiration_date, sl.status lot_status
             FROM test_supplies ts
             JOIN supplies s ON s.id=ts.supply_id
             LEFT JOIN supply_lots sl ON sl.supply_id=ts.supply_id AND sl.is_in_use=1
             WHERE ts.test_id=:test AND ts.is_required=1
             ORDER BY s.name,sl.id"
        );
        $statement->execute(['test'=>$testId]);
        $grouped=[];
        foreach($statement->fetchAll(PDO::FETCH_ASSOC) as $row)$grouped[(int)$row['supply_id']][]=$row;
        $issues=[];$lots=[];
        foreach($grouped as $supplyId=>$rows){
            $name=(string)$rows[0]['supply_name'];$marked=array_values(array_filter($rows,static fn(array $r):bool=>$r['lot_id']!==null));
            if(count($marked)>1){$issues[]="O insumo '{$name}' possui mais de um lote marcado como 'Em uso'. Corrija o cadastro antes de continuar.";continue;}
            if(!$marked){$issues[]="O insumo '{$name}' não possui lote válido marcado como 'Em uso'.";continue;}
            $lot=$marked[0];
            if($lot['supply_status']!=='active'){$issues[]="O insumo '{$name}' está inativo.";continue;}
            if($lot['lot_status']!=='active'){$issues[]="O lote {$lot['lot_number']} do insumo '{$name}' está inativo, bloqueado ou esgotado.";continue;}
            if((string)$lot['expiration_date']<date('Y-m-d')){$issues[]="O lote {$lot['lot_number']} do insumo '{$name}' venceu em ".date('d/m/Y',strtotime((string)$lot['expiration_date'])).'.';continue;}
            $lots[]=['supply_id'=>$supplyId,'supply_name'=>$name,'lot_id'=>(int)$lot['lot_id'],'lot_number'=>(string)$lot['lot_number'],'expiration_date'=>(string)$lot['expiration_date']];
        }
        return ['valid'=>!$issues,'issues'=>$issues,'lots'=>$lots];
    }

    public static function assertTest(int $testId,string $action='salvar este resultado'):array
    {
        $result=self::validateTest($testId);
        if($result['issues'])throw new DomainException("Não é possível {$action}.\n- ".implode("\n- ",$result['issues']));
        return $result['lots'];
    }

    public static function validateSample(int $sampleId):array
    {
        $q=Database::connection()->prepare('SELECT DISTINCT test_id FROM sample_tests WHERE sample_id=:sample');$q->execute(['sample'=>$sampleId]);
        $issues=[];
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $testId)foreach(self::validateTest((int)$testId)['issues'] as $issue)$issues[$issue]=true;
        return array_keys($issues);
    }

    public static function assertSample(int $sampleId,string $action='concluir esta amostra'):void
    {
        $issues=self::validateSample($sampleId);
        if($issues)throw new DomainException("Não é possível {$action}.\n- ".implode("\n- ",$issues));
    }

    public static function captureResultLots(int $resultId,int $testId,array $lots=[]):void
    {
        $pdo=Database::connection();$exists=$pdo->prepare('SELECT 1 FROM test_result_supplies WHERE test_result_id=:result LIMIT 1');$exists->execute(['result'=>$resultId]);
        if($exists->fetchColumn())return;
        if(!$lots)$lots=self::assertTest($testId);
        $insert=$pdo->prepare('INSERT INTO test_result_supplies(test_result_id,supply_id,supply_lot_id,supply_name_snapshot,lot_number_snapshot,expiration_date_snapshot,quantity_used) VALUES(:result,:supply,:lot,:supply_name,:lot_number,:expiration,NULL)');
        foreach($lots as $lot)$insert->execute(['result'=>$resultId,'supply'=>$lot['supply_id'],'lot'=>$lot['lot_id'],'supply_name'=>$lot['supply_name'],'lot_number'=>$lot['lot_number'],'expiration'=>$lot['expiration_date']]);
    }
}
