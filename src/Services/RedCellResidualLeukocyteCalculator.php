<?php
declare(strict_types=1);
namespace BloodHub\Services;

use DomainException;

final class RedCellResidualLeukocyteCalculator
{
    public const METHOD='Nageotte';

    public static function calculate(mixed $count,mixed $volume):?float
    {
        $count=PlateletResultCalculator::decimal($count);
        if($count===null)return null;
        $volume=PlateletResultCalculator::decimal($volume);
        if($volume===null)throw new DomainException('Calcule o Volume antes de informar o número de leucócitos.');
        // FC0538, atualizarDerivadosCHF_: Nº Leuc./U = (Nº Leuc. × 10 × 1000 × Vol. mL) / 50.
        return ($count*10*1000*$volume)/50;
    }
}
