<?php
declare(strict_types=1);
namespace BloodHub\Services\Indicators;

interface IndicatorCalculator
{
    /** @return array{months:array<int,array>,month:array} */
    public function calculate(int $year,int $month,array $scope=[]):array;
}
