<?php
namespace BloodHub\Services;

use BloodHub\Core\Database;
use DomainException;
use PDO;

final class BagTareResolver
{
    public const NOT_CONFIGURED = 'Tara não configurada para esta referência e hemocomponente.';
    public const AMBIGUOUS = 'Existe mais de uma tara configurada para este hemocomponente e referência de bolsa.';

    /** Retorna null quando não há configuração e lança DomainException se ela for ambígua. */
    public static function resolve(int $bagBrandId, int $bloodComponentId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.id tare_id,t.name tare_name,t.tare_weight,t.bag_brand_id,c.blood_component_id
               FROM bag_brand_tares t
               JOIN bag_brand_tare_components c ON c.bag_brand_tare_id=t.id
              WHERE t.bag_brand_id=:brand AND c.blood_component_id=:component
                AND t.active=1
              ORDER BY t.id LIMIT 2'
        );
        $stmt->execute(['brand'=>$bagBrandId, 'component'=>$bloodComponentId]);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows)>1) throw new DomainException(self::AMBIGUOUS);
        if (!$rows) return null;
        $row=$rows[0];
        return ['tare_id'=>(int)$row['tare_id'],'tare_name'=>$row['tare_name'],'tare_weight'=>(float)$row['tare_weight'],'bag_brand_id'=>(int)$row['bag_brand_id'],'blood_component_id'=>(int)$row['blood_component_id']];
    }
}
