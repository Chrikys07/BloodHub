<?php
declare(strict_types=1);

namespace BloodHub\Core;

final class StatusLabel
{
    private const LABELS = [
        'draft' => 'Em preparação',
        'registered' => 'Cadastrada',
        'sent' => 'Enviada',
        'awaiting_receipt' => 'Aguardando recebimento',
        'partially_received' => 'Recebimento parcial',
        'received' => 'Recebida',
        'rejected' => 'Recusada',
        'in_analysis' => 'Em análise',
        'partial_results' => 'Resultados parciais',
        'completed' => 'Concluída',
        'cancelled' => 'Cancelada',
        'blocked' => 'Bloqueada',
        'pending' => 'Pendente',
        'in_progress' => 'Em andamento',
    ];

    public static function label(?string $status): string
    {
        return self::LABELS[$status ?? ''] ?? 'Status não informado';
    }

    public static function sample(?string $status): string
    {
        return self::label($status);
    }

    public static function all(): array
    {
        return self::LABELS;
    }
}
