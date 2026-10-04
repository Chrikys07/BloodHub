<?php
declare(strict_types=1);

namespace BloodHub\Core;

use DateTimeImmutable;
use PDO;

/**
 * Consulta a disponibilidade de insumos sem reservar ou consumir estoque.
 */
final class SupplyAvailability
{
    public static function checkSupply(int $supplyId, float $quantityRequired = 0.0): array
    {
        $quantityRequired = max(0.0, $quantityRequired);
        $statement = Database::connection()->prepare(
            'SELECT s.id supply_id, s.name supply_name, s.status supply_status,
                    sl.id lot_id, sl.lot_number, sl.expiration_date,
                    sl.quantity_available, sl.status lot_status
             FROM supplies s
             LEFT JOIN supply_lots sl ON sl.supply_id = s.id
             WHERE s.id = :supply_id
             ORDER BY sl.expiration_date, sl.lot_number, sl.id'
        );
        $statement->execute(['supply_id' => $supplyId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return self::supplyResult($supplyId, null, $quantityRequired, false, null, 'Insumo não encontrado.');
        }

        $supplyName = (string) $rows[0]['supply_name'];
        if ($rows[0]['supply_status'] !== 'active') {
            return self::supplyResult($supplyId, $supplyName, $quantityRequired, false, null, 'Insumo inativo.');
        }

        $lots = [];
        foreach ($rows as $row) {
            if ($row['lot_id'] === null) continue;
            $days = self::daysToExpiration((string) $row['expiration_date']);
            $lots[] = [
                'lot_id' => (int) $row['lot_id'],
                'lot_number' => (string) $row['lot_number'],
                'expiration_date' => (string) $row['expiration_date'],
                'quantity_available' => $row['quantity_available'] === null ? null : (float) $row['quantity_available'],
                'status' => (string) $row['lot_status'],
                'days_to_expiration' => $days,
                'validity' => self::statusFromDays($days),
            ];
        }

        if (!$lots) {
            return self::supplyResult($supplyId, $supplyName, $quantityRequired, false, null, 'Insumo sem lote cadastrado.', $lots);
        }

        $activeLots = array_values(array_filter($lots, static fn(array $lot): bool => $lot['status'] === 'active'));
        if (!$activeLots) {
            return self::supplyResult($supplyId, $supplyName, $quantityRequired, false, null, 'Lote bloqueado ou inativo.', $lots);
        }

        $validLots = array_values(array_filter($activeLots, static fn(array $lot): bool => $lot['days_to_expiration'] >= 0));
        if (!$validLots) {
            return self::supplyResult($supplyId, $supplyName, $quantityRequired, false, null, 'Todos os lotes disponíveis estão vencidos.', $lots);
        }

        $eligibleLots = array_values(array_filter(
            $validLots,
            static fn(array $lot): bool => $quantityRequired <= 0.0
                || ($lot['quantity_available'] !== null && $lot['quantity_available'] >= $quantityRequired)
        ));
        if (!$eligibleLots) {
            return self::supplyResult($supplyId, $supplyName, $quantityRequired, false, null, 'Quantidade disponível insuficiente.', $lots);
        }

        // A consulta já ordena por validade: o primeiro elegível é o lote FEFO.
        return self::supplyResult($supplyId, $supplyName, $quantityRequired, true, $eligibleLots[0], null, $lots);
    }

    public static function checkTest(int $testId): array
    {
        $testStatement = Database::connection()->prepare('SELECT id, name FROM tests WHERE id = :test_id LIMIT 1');
        $testStatement->execute(['test_id' => $testId]);
        $test = $testStatement->fetch(PDO::FETCH_ASSOC);
        if (!$test) {
            return [
                'test_id' => $testId,
                'test_name' => null,
                'available' => false,
                'blocking_reasons' => ['Teste não encontrado.'],
                'supplies' => [],
            ];
        }

        $statement = Database::connection()->prepare(
            'SELECT ts.supply_id, ts.quantity_required, ts.is_required,
                    s.name supply_name, s.unit_of_measure
             FROM test_supplies ts
             INNER JOIN supplies s ON s.id = ts.supply_id
             WHERE ts.test_id = :test_id
             ORDER BY s.name, s.id'
        );
        $statement->execute(['test_id' => $testId]);

        $supplies = [];
        $blockingReasons = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $link) {
            $required = (int) $link['is_required'] === 1;
            $quantity = $link['quantity_required'] === null ? 0.0 : (float) $link['quantity_required'];
            $check = self::checkSupply((int) $link['supply_id'], $quantity);
            $reason = $check['reason'];

            if ($required && !$check['available'] && $reason !== null) {
                $blockingReasons[] = $link['supply_name'] . ': ' . self::requiredReason($reason);
            }

            $supplies[] = [
                'supply_id' => (int) $link['supply_id'],
                'supply_name' => (string) $link['supply_name'],
                'name' => (string) $link['supply_name'], // compatibilidade com a tela administrativa atual
                'required' => $required,
                'is_required' => (int) $link['is_required'],
                'quantity_required' => $link['quantity_required'] === null ? null : $quantity,
                'unit_of_measure' => $link['unit_of_measure'],
                'available' => $check['available'],
                'selected_lot' => $check['selected_lot'],
                'reason' => $reason,
                'best_status' => $check['best_status'],
            ];
        }

        return [
            'test_id' => (int) $test['id'],
            'test_name' => (string) $test['name'],
            'available' => !$blockingReasons,
            'blocking_reasons' => $blockingReasons,
            'supplies' => $supplies,
        ];
    }

    public static function checkTests(array $testIds): array
    {
        $results = [];
        foreach (array_values(array_unique($testIds, SORT_REGULAR)) as $testId) {
            $id = filter_var($testId, FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) continue;
            $results[(int) $id] = self::checkTest((int) $id);
        }
        return $results;
    }

    public static function statusFromDays(?int $days): array
    {
        if ($days === null) return ['key' => 'none', 'label' => 'SEM LOTE'];
        if ($days < 0) return ['key' => 'expired', 'label' => 'VENCIDO'];
        if ($days <= 7) return ['key' => 'critical', 'label' => 'CRÍTICO'];
        if ($days <= 30) return ['key' => 'warning', 'label' => 'ATENÇÃO'];
        return ['key' => 'valid', 'label' => 'VÁLIDO'];
    }

    private static function supplyResult(
        int $supplyId,
        ?string $supplyName,
        float $quantityRequired,
        bool $available,
        ?array $selectedLot,
        ?string $reason,
        array $lots = []
    ): array {
        $activeLots = array_values(array_filter($lots, static fn(array $lot): bool => $lot['status'] === 'active'));
        $validLots = array_values(array_filter($activeLots, static fn(array $lot): bool => $lot['days_to_expiration'] >= 0));
        $nearestLot = $activeLots[0] ?? null;

        return [
            'supply_id' => $supplyId,
            'supply_name' => $supplyName,
            'quantity_required' => $quantityRequired,
            'available' => $available,
            'selected_lot' => $selectedLot === null ? null : array_intersect_key($selectedLot, array_flip([
                'lot_id', 'lot_number', 'expiration_date', 'quantity_available', 'validity',
            ])),
            'reason' => $reason,
            'has_active_lot' => (bool) $activeLots,
            'has_valid_lot' => (bool) $validLots,
            'best_status' => $nearestLot['validity'] ?? self::statusFromDays(null),
            'active_lots' => $activeLots,
            'nearest_expiration' => $nearestLot['expiration_date'] ?? null,
        ];
    }

    private static function daysToExpiration(string $expirationDate): int
    {
        $today = new DateTimeImmutable('today');
        return (int) $today->diff(new DateTimeImmutable($expirationDate))->format('%r%a');
    }

    private static function requiredReason(string $reason): string
    {
        return match ($reason) {
            'Insumo sem lote cadastrado.' => 'Insumo obrigatório sem lote cadastrado.',
            default => $reason,
        };
    }
}
