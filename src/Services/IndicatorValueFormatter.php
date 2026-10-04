<?php
declare(strict_types=1);
namespace BloodHub\Services;

final class IndicatorValueFormatter
{
    public static function value(array$i,mixed$v):string
    {if($v===null||$v===''||!is_numeric($v))return'—';return match($i['value_type']){'percentage'=>number_format((float)$v,1,',','.').'%', 'scientific'=>MeasurementFormatter::formatScientific($v).' '.($i['value_unit']??''),default=>number_format((float)$v,2,',','.').' '.($i['value_unit']??'')};}
    public static function target(array$i,?array$t):string
    {if(!$t)return'Não configurada';$op=['GT'=>'>','GTE'=>'≥','LT'=>'<','LTE'=>'≤','EQ'=>'='][$t['target_operator']]??'';return trim($op.' '.self::value($i,$t['target_value']));}
}
