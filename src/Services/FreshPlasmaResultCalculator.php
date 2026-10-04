<?php
declare(strict_types=1);
namespace BloodHub\Services;

use DomainException;

final class FreshPlasmaResultCalculator
{
    public const PLATELET_METHODS=['Neubauer'];
    public const LEUKOCYTE_METHODS=['Nageotte'];
    public const RED_CELL_METHODS=['Neubauer'];

    public static function calculate(array $input):array
    {
        self::assertMethod($input['platelet_count']??null,(string)($input['platelet_method']??''),self::PLATELET_METHODS);
        self::assertMethod($input['leukocyte_count']??null,(string)($input['leukocyte_method']??''),self::LEUKOCYTE_METHODS);
        self::assertMethod($input['red_cell_count']??null,(string)($input['red_cell_method']??''),self::RED_CELL_METHODS);
        return [
            'platelets_per_ml'=>self::calculatePlateletsPerMl($input['platelet_count']??null),
            'leukocytes_per_ml'=>self::calculateLeukocytesPerMl($input['leukocyte_count']??null),
            'red_cells_per_ml'=>self::calculateRedCellsPerMl($input['red_cell_count']??null),
        ];
    }

    public static function calculatePlateletsPerMl(mixed $count):?float
    {
        $value=PlateletResultCalculator::decimal($count);
        // FC0538: Nº Plaquetas × 5 × 10 × 20 × 1000.
        return $value===null?null:$value*5*10*20*1000;
    }

    public static function calculateLeukocytesPerMl(mixed $count):?float
    {
        $value=PlateletResultCalculator::decimal($count);
        // FC0538: (Nº Leucócitos × 1000 × 2) / 50.
        return $value===null?null:($value*1000*2)/50;
    }

    public static function calculateRedCellsPerMl(mixed $count):?float
    {
        $value=PlateletResultCalculator::decimal($count);
        // FC0538: Nº Hemácias × 5 × 10 × 1000.
        return $value===null?null:$value*5*10*1000;
    }

    private static function assertMethod(mixed $count,string $method,array $allowed):void
    {
        if(PlateletResultCalculator::decimal($count)!==null&&!in_array($method,$allowed,true))
            throw new DomainException('Selecione um método válido para a contagem informada.');
    }
}
