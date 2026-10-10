<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$source = file_get_contents(dirname(__DIR__) . '/src/Views/shipments/report.php');
$checks = [
    'logo oficial' => '/assets/images/bloodhub-logo-horizontal.png',
    'páginas explícitas' => 'foreach ($pages as $pageIndex => $page)',
    'cabeçalho completo por página' => '<header class="report-header">',
    'metadados por página' => '<div class="meta">',
    'numeração automática' => 'Página <?= $pageIndex + 1 ?> de <?= $totalPages ?>',
    'largura física mínima' => 'min-width:5cm',
    'largura física máxima' => 'max-width:5cm',
    'altura física' => 'height:4cm',
    'altura física mínima' => 'min-height:4cm',
    'linha indivisível' => 'page-break-inside:avoid',
    'cabeçalho das colunas' => '<thead><tr>',
    'temperatura de envio' => 'REGISTRO DA TEMPERATURA DE ENVIO',
    'temperatura de recebimento' => 'REGISTRO DA TEMPERATURA DE RECEBIMENTO',
    'condição sem caixas' => 'if ($boxes)',
    'abertura automática' => "setTimeout(()=>window.print(),150)",
];

foreach ($checks as $label => $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "FALHA: requisito ausente no relatório: {$label}\n");
        exit(1);
    }
}
foreach (['height:6cm', 'transform:scale', 'transform: scale'] as $forbidden) {
    if (str_contains($source, $forbidden)) {
        fwrite(STDERR, "FALHA: regra proibida encontrada no relatório: {$forbidden}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Estrutura física, paginação e regras condicionais do relatório: OK\n");
