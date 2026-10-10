<?php
declare(strict_types=1);
namespace BloodHub\Services;

final class DecimalNormalizer
{
    /** Accepts browser/SQL decimals (350.000) and pt-BR decimals (350,000). */
    public static function parse(mixed $raw): ?float
    {
        $value=trim((string)$raw);
        if($value==='')return null;
        if(str_contains($value,',')){
            if(substr_count($value,',')!==1)return null;
            $value=str_replace('.','',$value);
            $value=str_replace(',','.',$value);
        }
        if(!preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/D',$value))return null;
        $number=(float)$value;
        return is_finite($number)?$number:null;
    }
}
