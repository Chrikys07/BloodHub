<?php
declare(strict_types=1);
namespace BloodHub\Services;

final class MeasurementFormatter
{
    private const SCIENTIFIC_TESTS=['PLATELETS_PER_UNIT','LEUKOCYTES_PER_UNIT','PLATELETS_PER_ML','LEUKOCYTES_PER_ML','REDBLOODCELLS_PER_ML'];

    public static function formatScientific(mixed $value,int $maxDecimals=2):string
    {
        if($value===null||$value===''||!is_numeric($value))return '';
        $number=(float)$value;
        if($number==0.0)return '0';
        $exponent=(int)floor(log10(abs($number)));
        $mantissa=$number/(10**$exponent);
        $mantissa=round($mantissa,max(0,$maxDecimals));
        if(abs($mantissa)>=10){$mantissa/=10;$exponent++;}
        $text=number_format($mantissa,max(0,$maxDecimals),',','');
        if($maxDecimals>0)$text=rtrim(rtrim($text,'0'),',');
        return $text.' × 10'.self::superscript($exponent);
    }

    public static function formatDecimalMeasurement(mixed $value,int $decimals):string
    {
        return $value===null||$value===''||!is_numeric($value)?'':number_format((float)$value,$decimals,',','.');
    }

    public static function formatTestValue(string $testCode,mixed $value):string
    {
        if(in_array($testCode,self::SCIENTIFIC_TESTS,true))return self::formatScientific($value);
        if($testCode==='VOLUME')return self::formatDecimalMeasurement($value,1);
        if($testCode==='PH')return self::formatDecimalMeasurement($value,2);
        if(in_array($testCode,['HEMOGLOBIN','FREE_HEMOGLOBIN','HEMOGLOBIN_PER_UNIT'],true))return self::formatDecimalMeasurement($value,1);
        if($testCode==='FACTOR_VIII')return self::formatDecimalMeasurement($value,2);
        if($testCode==='FIBRINOGEN'){
            $text=self::formatDecimalMeasurement($value,8);
            return rtrim(rtrim($text,'0'),',');
        }
        return $value===null?'':(string)$value;
    }

    public static function formatSpecification(string $testCode,array $evaluation):string
    {
        $type=(string)($evaluation['rule_type']??'');
        $labels=['GT'=>'>','GTE'=>'≥','LT'=>'<','LTE'=>'≤','EQUAL_NUMERIC'=>'='];
        $format=static fn($value)=>self::formatTestValue($testCode,$value);
        $value=match($type){
            'BETWEEN'=>$format($evaluation['expected_min']??null).' a '.$format($evaluation['expected_max']??null),
            'LT','LTE'=>$format($evaluation['expected_max']??null),
            'EQUAL_TEXT'=>(string)($evaluation['expected_text']??''),
            'BOOLEAN'=>self::humanBoolean((string)($evaluation['expected_text']??'')),
            default=>$format($evaluation['expected_min']??null),
        };
        $prefix=$type==='BETWEEN'?'':(($labels[$type]??'').' ');
        return trim($prefix.$value.' '.($evaluation['unit']??''));
    }

    private static function humanBoolean(string$value):string
    {$normalized=mb_strtolower(trim($value));return in_array($normalized,['1','true','sim','presente','positivo'],true)?'Sim':(in_array($normalized,['0','false','não','nao','ausente','negativo'],true)?'Não':$value);}

    public static function formatResult(string $testCode,mixed $value,?string $unit):string
    {
        return trim(self::formatTestValue($testCode,$value).' '.($unit??''));
    }

    private static function superscript(int $number):string
    {
        return strtr((string)$number,['-'=>'⁻','0'=>'⁰','1'=>'¹','2'=>'²','3'=>'³','4'=>'⁴','5'=>'⁵','6'=>'⁶','7'=>'⁷','8'=>'⁸','9'=>'⁹']);
    }
}
