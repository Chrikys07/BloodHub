<?php
declare(strict_types=1);
namespace BloodHub\Services;

use DomainException;

final class WashedRedCellCalculator
{
    public static function decimal(mixed $value):?float
    {
        if($value===null||trim((string)$value)==='')return null;
        $normalized=str_replace(',','.',trim((string)$value));
        if(!preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/D',$normalized))throw new DomainException('Informe somente valores numéricos, usando ponto ou vírgula decimal.');
        $number=(float)$normalized;
        if(!is_finite($number))throw new DomainException('Valor numérico inválido.');
        return $number;
    }

    public static function calculateVolume(?float $weight,?float $tare,?float $density):?float
    {
        if($weight===null||$tare===null||$density===null)return null;
        if($density<=0)throw new DomainException('Densidade não configurada para CHL.');
        if($weight<=$tare)throw new DomainException('O peso deve ser maior que a tara da bolsa.');
        return ($weight-$tare)/$density;
    }

    public static function calculateRecovery(?float $initialHct,?float $initialVolume,?float $finalHct,?float $finalVolume):?float
    {
        if($initialHct===null||$initialVolume===null||$finalHct===null||$finalVolume===null||$initialHct==0||$initialVolume==0)return null;
        return (($finalHct*$finalVolume)/($initialHct*$initialVolume))*100;
    }

    public static function calculateHemoglobinPerUnit(?float $hemoglobin,?float $finalVolume):?float
    {return $hemoglobin===null||$finalVolume===null?null:($hemoglobin/100)*$finalVolume;}

    public static function calculateHemolysis(?float $freeHemoglobin,?float $hemoglobin,?float $finalHct):?float
    {return $freeHemoglobin===null||$hemoglobin===null||$finalHct===null||$hemoglobin==0?null:($freeHemoglobin/$hemoglobin)*(100-$finalHct);}

    public static function calculateResidualProtein(?float $standardAbsorbance,?float $proteinAbsorbance,?float $finalVolume):?float
    {return $standardAbsorbance===null||$standardAbsorbance==0||$proteinAbsorbance===null||$finalVolume===null?null:(($proteinAbsorbance/$standardAbsorbance)*50*0.00001)*$finalVolume;}
}
