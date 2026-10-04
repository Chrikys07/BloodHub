<?php
declare(strict_types=1);
namespace BloodHub\Services;

use DomainException;

final class PlateletResultCalculator
{
    public const PLATELET_METHODS=['Sysmex','Neubauer'];
    public const LEUKOCYTE_METHODS=['Sysmex','Neubauer','Nageotte'];

    public static function decimal(mixed $value,bool $required=false):?float
    {
        if($value===null||trim((string)$value)===''){
            if($required)throw new DomainException('Informe um valor numérico.');
            return null;
        }
        $normalized=trim((string)$value);
        if(str_contains($normalized,','))$normalized=str_replace(['.',','],['','.'],$normalized);
        if(!is_numeric($normalized))throw new DomainException('O resultado deve ser numérico.');
        $number=(float)$normalized;
        if(!is_finite($number))throw new DomainException('Resultado numérico inválido.');
        return $number;
    }

    public static function calculate(array $input):array
    {
        $weight=self::decimal($input['gross_weight']??null);
        $tare=self::decimal($input['tare_weight']??null);
        $density=self::decimal($input['density']??null);
        $volume=self::decimal($input['volume_ml']??null);
        if($weight!==null){
            if($tare===null)throw new DomainException(BagTareResolver::NOT_CONFIGURED);
            if($density===null||$density<=0)throw new DomainException('Densidade não configurada ou inválida para este hemocomponente.');
            if($weight<=$tare)throw new DomainException('O peso bruto deve ser maior que a tara da bolsa.');
            $volume=($weight-$tare)/$density;
        }
        $platelets=self::decimal($input['platelet_count']??null);
        $leukocytes=self::decimal($input['leukocyte_count']??null);
        $plateletMethod=trim((string)($input['platelet_method']??''));
        $leukocyteMethod=trim((string)($input['leukocyte_method']??''));
        $plateletsPerUnit=null;$leukocytesPerUnit=null;
        if($platelets!==null){
            if($volume===null)throw new DomainException('Calcule o Volume antes de informar o número de plaquetas.');
            if(!in_array($plateletMethod,self::PLATELET_METHODS,true))throw new DomainException('Selecione um método de plaquetas com fórmula configurada.');
            $plateletsPerUnit=$plateletMethod==='Sysmex'?$platelets*5*1000000*$volume:$platelets*5*10*200*1000*$volume;
        }
        if($leukocytes!==null){
            if($volume===null)throw new DomainException('Calcule o Volume antes de informar o número de leucócitos.');
            if(!in_array($leukocyteMethod,self::LEUKOCYTE_METHODS,true))throw new DomainException('Selecione um método de leucócitos válido.');
            $leukocytesPerUnit=match($leukocyteMethod){
                'Sysmex'=>$leukocytes*1000000*$volume,
                'Neubauer'=>($leukocytes*10*20*1000*$volume)/4,
                'Nageotte'=>($leukocytes*10*1000*$volume)/50,
            };
        }
        return ['volume_ml'=>$volume,'platelet_count_per_unit'=>$plateletsPerUnit,'leukocyte_count_per_unit'=>$leukocytesPerUnit];
    }
}
