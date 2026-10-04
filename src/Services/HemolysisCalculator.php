<?php
declare(strict_types=1);
namespace BloodHub\Services;

use DomainException;

final class HemolysisCalculator
{
    public const FIELDS=['abs_370','abs_415','abs_510','abs_577','abs_600'];

    public static function decimal(mixed $value, bool $required=true): ?float
    {
        if ($value === null || trim((string)$value) === '') {
            if ($required) throw new DomainException('Todas as cinco absorbâncias são obrigatórias para calcular a Hemoglobina Livre.');
            return null;
        }
        $normalized=str_replace(',', '.', trim((string)$value));
        if (!preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/D', $normalized)) throw new DomainException('Absorbância inválida. Use somente números com ponto ou vírgula decimal.');
        $number=(float)$normalized;
        if (!is_finite($number)) throw new DomainException('Absorbância inválida.');
        return $number;
    }

    public static function calculate(array $values): array
    {
        $v=[];foreach(self::FIELDS as $field)$v[$field]=self::decimal($values[$field]??null);
        if($v['abs_415']<=0.9){$x=$v['abs_510']+($v['abs_370']-$v['abs_510'])*0.68;$y=$v['abs_415']-$x;$free=($y/113.22*16125)/1000;$branch='LOW_415';}
        else{$x=$v['abs_600']+($v['abs_510']-$v['abs_600'])*0.26;$y=$v['abs_577']-$x;$free=($y/13.64*16125)/1000;$branch='HIGH_415';}
        return $v+['x_value'=>$x,'y_value'=>$y,'free_hemoglobin_g_dl'=>$free,'formula_branch'=>$branch];
    }
}
