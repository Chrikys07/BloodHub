<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\Database;
use DomainException;
use PDO;

final class SampleUniquenessService
{
    public static function find(string $donationNumber,int $bloodComponentId,int $excludeSampleId=0,bool $lock=false):?array
    {
        $donationNumber=trim($donationNumber);
        if($donationNumber===''||$bloodComponentId<1)return null;
        $pdo=Database::connection();
        $sql="SELECT s.id,s.purpose,s.status,s.registered_at,bc.name component_name,u.name origin_name
              FROM samples s
              JOIN blood_components bc ON bc.id=s.blood_component_id
              LEFT JOIN units u ON u.id=s.origin_unit_id
              WHERE LOWER(TRIM(s.donation_number))=LOWER(:donation)
                AND s.blood_component_id=:component
                AND (:exclude=0 OR s.id<>:exclude2)
              ORDER BY s.id LIMIT 1".($lock?' FOR UPDATE':'');
        $query=$pdo->prepare($sql);$query->execute(['donation'=>$donationNumber,'component'=>$bloodComponentId,'exclude'=>$excludeSampleId,'exclude2'=>$excludeSampleId]);
        return $query->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public static function validateRows(array $rows,bool $lock=false):array
    {
        $errors=[];$seen=[];
        foreach($rows as$i=>$row){
            $donation=trim((string)($row['donation_number']??''));$component=(int)($row['blood_component_id']??0);$sampleId=(int)($row['id']??0);
            if($donation===''||!$component)continue;
            $key=mb_strtolower($donation).'|'.$component;
            if(isset($seen[$key])){$errors[]='Linhas '.$seen[$key].' e '.($i+1).': o mesmo número de doação e hemocomponente foi informado mais de uma vez.';continue;}
            $seen[$key]=$i+1;$existing=self::find($donation,$component,$sampleId,$lock);
            if($existing)$errors[]=self::message($existing);
        }
        return array_values(array_unique($errors));
    }

    public static function assertRowsAvailable(array $rows,bool $lock=true):void
    {
        $errors=self::validateRows($rows,$lock);if($errors)throw new DomainException(implode(' ', $errors));
    }

    public static function message(array $existing):string
    {
        return 'Esta doação já possui uma amostra cadastrada para '.($existing['component_name']??'o hemocomponente selecionado').'. Não é permitido cadastrar o mesmo número de doação para o mesmo hemocomponente mais de uma vez.';
    }
}
