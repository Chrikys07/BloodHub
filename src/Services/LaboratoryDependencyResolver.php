<?php
declare(strict_types=1);
namespace BloodHub\Services;

final class LaboratoryDependencyResolver
{
    private const DEPENDENCIES=[
        'VOLUME'=>['gross_weight'],
        'HEMOGLOBIN_PER_UNIT'=>['hemoglobin','volume'],
        'HEMOLYSIS_DEGREE'=>['free_hemoglobin','hematocrit','hemoglobin'],
        'FREE_HEMOGLOBIN'=>['abs_370','abs_415','abs_510','abs_577','abs_600'],
        'PLATELETS_PER_UNIT'=>['platelet_method','platelet_count','volume'],
        'LEUKOCYTES_PER_UNIT'=>['leukocyte_method','leukocyte_count','volume'],
        'PLATELETS_PER_ML'=>['platelet_method','platelet_count'],
        'LEUKOCYTES_PER_ML'=>['leukocyte_method','leukocyte_count'],
        'REDBLOODCELLS_PER_ML'=>['red_cell_method','red_cell_count'],
        'FIBRINOGEN'=>['gross_weight','dilution','fibrinogen_mg_dl','volume'],
        'RECOVERY'=>['initial_weight_g','final_weight_g','initial_hematocrit_pct','final_hematocrit_pct'],
        'RESIDUAL_PROTEIN'=>['standard_absorbance','protein_absorbance','final_weight_g'],
    ];
    private const INPUT_DEPENDENCIES=['volume'=>['gross_weight'],'free_hemoglobin'=>['abs_370','abs_415','abs_510','abs_577','abs_600']];
    public static function resolve(array $testCodes):array{$fields=[];$visit=function(string $key)use(&$visit,&$fields):void{foreach(self::DEPENDENCIES[$key]??self::INPUT_DEPENDENCIES[$key]??[] as $dependency){$fields[$dependency]=true;$visit($dependency);}};foreach($testCodes as$code)$visit(strtoupper((string)$code));return array_keys($fields);}
    public static function has(array $fields,string $field):bool{return in_array($field,$fields,true);}
}
