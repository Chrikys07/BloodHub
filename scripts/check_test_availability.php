<?php
declare(strict_types=1);

use BloodHub\Core\SupplyAvailability;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado pela linha de comando.\n");
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'BloodHub\\';
    if (!str_starts_with($class, $prefix)) return;
    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require $file;
});

$testId = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT);
if ($testId === false || $testId <= 0) {
    fwrite(STDERR, "Uso: php scripts/check_test_availability.php <id_do_teste>\n");
    exit(1);
}

try {
    $result = SupplyAvailability::checkTest((int) $testId);
    if ($result['test_name'] === null) {
        fwrite(STDERR, "Erro: teste não encontrado.\n");
        exit(1);
    }

    fwrite(STDOUT, 'Teste: ' . $result['test_name'] . PHP_EOL);
    fwrite(STDOUT, 'Status: ' . ($result['available'] ? 'DISPONÍVEL' : 'BLOQUEADO') . PHP_EOL);

    if ($result['blocking_reasons']) {
        fwrite(STDOUT, PHP_EOL . "Motivos:\n");
        foreach ($result['blocking_reasons'] as $reason) fwrite(STDOUT, '- ' . $reason . PHP_EOL);
    }

    fwrite(STDOUT, PHP_EOL . "Insumos:\n");
    if (!$result['supplies']) fwrite(STDOUT, "- Nenhum insumo configurado.\n");
    foreach ($result['supplies'] as $supply) {
        $lot = $supply['selected_lot'];
        fwrite(STDOUT, '- ' . $supply['supply_name'] . PHP_EOL);
        fwrite(STDOUT, '  Obrigatório: ' . ($supply['required'] ? 'Sim' : 'Não') . PHP_EOL);
        fwrite(STDOUT, '  Quantidade necessária: ' . ($supply['quantity_required'] ?? 'Não informada') . PHP_EOL);
        fwrite(STDOUT, '  Lote selecionado: ' . ($lot['lot_number'] ?? 'Nenhum') . PHP_EOL);
        fwrite(STDOUT, '  Validade: ' . ($lot ? date('d/m/Y', strtotime($lot['expiration_date'])) : '—') . PHP_EOL);
        fwrite(STDOUT, '  Disponível: ' . ($supply['available'] ? 'Sim' : 'Não') . PHP_EOL);
        if ($supply['reason'] !== null) fwrite(STDOUT, '  Motivo: ' . $supply['reason'] . PHP_EOL);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Erro: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
