<?php
declare(strict_types=1);

namespace BloodHub\Core;

final class SamplePurpose
{
    public const SHIPMENT_OPTIONS = [
        'quality_control' => 'Controle de Qualidade',
        'validation' => 'Validação',
        'single_assessment' => 'Avaliação avulsa',
        'transfusion_reaction' => 'Reação Transfusional',
    ];
    public const OPTIONS = self::SHIPMENT_OPTIONS + [
        'transfusion_reaction' => 'Reação Transfusional',
        'other' => 'Outro',
    ];

    public static function label(string $purpose): string
    {
        if ($purpose === '') {
            return 'Não informado';
        }

        return self::OPTIONS[$purpose] ?? $purpose;
    }

    public static function isValid(string $purpose): bool
    {
        return isset(self::SHIPMENT_OPTIONS[$purpose]);
    }
}
