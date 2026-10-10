<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$outputDirectory = $argv[1] ?? dirname(__DIR__) . '/tmp/pdfs/shipment-report';
if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Não foi possível criar o diretório de saída.');
}

$scenarios = [
    '1-amostra-sem-caixa' => [1, false],
    '2-amostras-com-caixa' => [2, true],
    '5-amostras-sem-caixa' => [5, false],
    '5-amostras-com-caixa' => [5, true],
    '8-amostras-sem-caixa' => [8, false],
    '8-amostras-com-caixa' => [8, true],
];

foreach ($scenarios as $name => [$sampleCount, $withBox]) {
    $shipment = [
        'shipment_code' => 'REM-TESTE-' . $sampleCount,
        'sent_at' => '2026-10-09 10:30:00',
        'origin_name' => 'Unidade de Processamento',
        'client_name' => 'Cliente de Homologação',
        'destination_name' => 'LCQH Central',
        'responsible_name' => 'Responsável de Teste',
        'checked_by' => 'Conferente de Teste',
        'notes' => 'Cenário de validação visual da paginação.',
    ];
    $samples = [];
    for ($index = 1; $index <= $sampleCount; $index++) {
        $samples[] = [
            'component_code' => 'CH',
            'component_name' => 'Concentrado de Hemácias',
            'donation_number' => sprintf('DOACAO-%04d', $index),
            'production_date' => '2026-10-08',
            'bag_brand_name' => 'Marca de Bolsa | Ref. TESTE',
        ];
    }
    $boxes = $withBox ? [[
        'box_code' => 'CXT01',
        'received_seal' => 'LACRE-001',
        'sent_temperature' => 4.2,
        'received_temperature' => 4.8,
        'received_at' => '2026-10-09 12:15:00',
    ]] : [];
    $pageTitle = 'Relatório de teste - ' . $name;

    ob_start();
    require dirname(__DIR__) . '/src/Views/shipments/report.php';
    $html = ob_get_clean();
    $html = str_replace("<script>window.addEventListener('load',()=>setTimeout(()=>window.print(),150))</script>", '', $html);
    file_put_contents($outputDirectory . '/' . $name . '.html', $html);
}

fwrite(STDOUT, count($scenarios) . " relatórios de teste gerados em {$outputDirectory}\n");
