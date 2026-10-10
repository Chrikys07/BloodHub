<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$directory = $argv[1] ?? dirname(__DIR__) . '/tmp/pdfs/shipment-report';
$expectations = [
    '1-amostra-sem-caixa' => ['samples' => 1, 'pages' => 1, 'boxes' => false],
    '2-amostras-com-caixa' => ['samples' => 2, 'pages' => 1, 'boxes' => true],
    '5-amostras-sem-caixa' => ['samples' => 5, 'pages' => 2, 'boxes' => false],
    '5-amostras-com-caixa' => ['samples' => 5, 'pages' => 2, 'boxes' => true],
    '8-amostras-sem-caixa' => ['samples' => 8, 'pages' => 3, 'boxes' => false],
    '8-amostras-com-caixa' => ['samples' => 8, 'pages' => 3, 'boxes' => true],
];

$fail = static function (string $message): never {
    fwrite(STDERR, "FALHA: {$message}\n");
    exit(1);
};

foreach ($expectations as $name => $expected) {
    $path = $directory . '/' . $name . '.html';
    $html = is_file($path) ? file_get_contents($path) : false;
    if ($html === false) {
        $fail("fixture ausente: {$name}");
    }

    $pageCount = substr_count($html, '<section class="report-page">');
    if ($pageCount !== $expected['pages']) {
        $fail("{$name}: esperado {$expected['pages']} página(s), obtido {$pageCount}");
    }
    foreach (['<header class="report-header">', '<div class="meta">', 'class="component-title"', 'class="page-number"'] as $repeated) {
        if (substr_count($html, $repeated) !== $pageCount) {
            $fail("{$name}: elemento não repetido em todas as páginas: {$repeated}");
        }
    }
    for ($page = 1; $page <= $pageCount; $page++) {
        if (!str_contains($html, "Página {$page} de {$pageCount}")) {
            $fail("{$name}: numeração ausente para a página {$page}");
        }
    }
    if (substr_count($html, '<td><span class="cell-value">DOACAO-') !== $expected['samples']) {
        $fail("{$name}: quantidade de linhas de amostra divergente");
    }
    $hasTemperature = str_contains($html, 'REGISTRO DA TEMPERATURA DE ENVIO');
    if ($hasTemperature !== $expected['boxes']) {
        $fail("{$name}: condição de exibição das temperaturas divergente");
    }
    if (substr_count($html, '>Controle LCQH</th>') !== substr_count($html, '<table class="samples">')
        || substr_count($html, '>Bacteriológico</th>') !== substr_count($html, '<table class="samples">')) {
        $fail("{$name}: cabeçalhos das colunas de etiqueta não acompanham todas as tabelas");
    }
}

fwrite(STDOUT, count($expectations) . " cenários estruturais do relatório: OK\n");
