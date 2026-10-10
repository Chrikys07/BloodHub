<?php
$groups = [];
foreach ($samples as $sample) {
    $groups[strtoupper(trim($sample['component_code'] ?? 'OUTROS'))][] = $sample;
}

$columns = static function (string $code): array {
    $map = [
        'CH' => ['Controle LCQH', 'Bacteriológico', 'Peso', 'Ht (%)', 'Hb (g/dL)'],
        'CHF' => ['Controle LCQH', 'Bacteriológico', 'Peso', 'Ht (%)', 'Hb (g/dL)', 'Nº Leuc.'],
        'CHAF' => ['Controle LCQH', 'Bacteriológico', 'Peso', 'Ht (%)', 'Hb (g/dL)', 'Nº Leuc.'],
        'CHL' => ['Controle LCQH', 'Bacteriológico', 'Peso origem', 'Peso final', 'Ht Inicial (%)', 'Ht Final (%)', 'Hb (g/dL)', 'Abs padrão', 'Abs proteína'],
        'ST' => ['Controle LCQH', 'Bacteriológico', 'Peso', 'Ht (%)'],
        'STR' => ['Controle LCQH', 'Bacteriológico', 'Peso', 'Ht (%)'],
        'CP' => ['Controle LCQH', 'Peso', 'Método Plqs.', 'Nº Plqs.', 'Método Leuc.', 'Nº Leuc.', 'pH', 'Swirling'],
        'CPAF' => ['Controle LCQH', 'Bacteriológico', 'Peso inicial', 'Peso final', 'Método Plqs.', 'Nº Plqs.', 'Método Leuc.', 'Nº Leuc.', 'pH', 'Swirling'],
        'PFC' => ['Controle LCQH', 'Peso', 'Fator VIII'],
        'PFC24' => ['Controle LCQH', 'Peso', 'Fator VIII'],
        'PFC RESIDUAL' => ['Controle LCQH', 'Método Plqs.', 'Nº Plqs.', 'Método Leuc.', 'Nº Leuc.', 'Método Hemácias', 'Nº Hemácias'],
        'PFC24 RESIDUAL' => ['Controle LCQH', 'Método Plqs.', 'Nº Plqs.', 'Método Leuc.', 'Nº Leuc.', 'Método Hemácias', 'Nº Hemácias'],
        'CRIO' => ['Controle LCQH', 'Peso', 'Fibrinogênio (mg/dL)'],
    ];
    return $map[$code] ?? ['Controle LCQH'];
};

$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$temperature = static fn (mixed $value): string => $value === null ? '' : number_format((float) $value, 2, ',', '.') . ' °C';

/*
 * A paginação é explícita porque os navegadores Chromium não oferecem, no HTML
 * impresso, um contador confiável de "página X de Y". Três linhas físicas de
 * 4 cm cabem com segurança abaixo do cabeçalho completo em A4 paisagem.
 */
$pages = [];
foreach ($groups as $code => $items) {
    $componentColumns = array_values(array_unique(array_merge(['Controle LCQH', 'Bacteriológico'], $columns($code))));
    $componentName = $items[0]['component_name'] ?? '';
    $sampleChunks = array_chunk($items, 3);

    foreach ($sampleChunks as $index => $chunk) {
        $isLastSamplePage = $index === array_key_last($sampleChunks);
        $pages[] = [
            'kind' => 'samples',
            'code' => $code,
            'component_name' => $componentName,
            'columns' => $componentColumns,
            'samples' => $chunk,
            'show_manual' => $isLastSamplePage,
            'boxes' => [],
        ];
    }

    if ($boxes) {
        $lastPageIndex = array_key_last($pages);
        $lastRowCount = count($pages[$lastPageIndex]['samples']);
        $inlineBoxLimits = [1 => 7, 2 => 2, 3 => 0];
        if (count($boxes) <= ($inlineBoxLimits[$lastRowCount] ?? 0)) {
            $pages[$lastPageIndex]['boxes'] = $boxes;
        } else {
            foreach (array_chunk($boxes, 14) as $boxChunk) {
                $pages[] = [
                    'kind' => 'temperature',
                    'code' => $code,
                    'component_name' => $componentName,
                    'columns' => $componentColumns,
                    'samples' => [],
                    'show_manual' => false,
                    'boxes' => $boxChunk,
                ];
            }
        }
    }
}
$totalPages = count($pages);
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title><?= $h($pageTitle) ?></title>
<style>
@page{size:A4 landscape;margin:9mm}
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{font:8px Arial,sans-serif;color:#17212b;background:#e9edf2}
.print{position:fixed;right:8px;top:8px;z-index:5;padding:8px 14px}
.report-page{position:relative;width:279mm;height:192mm;margin:8mm auto;background:#fff;break-after:page;page-break-after:always;overflow:hidden}
.report-page:last-of-type{break-after:auto;page-break-after:auto}
.report-header{display:grid;grid-template-columns:5cm 1fr 5cm;align-items:center;height:1.8cm;border-bottom:2px solid #9f1734;margin-bottom:2mm;padding-bottom:2mm}
.report-header img{display:block;width:4.6cm;height:auto;max-height:1.5cm;object-fit:contain}
.title{text-align:center}
.title h1{font-size:13px;line-height:1.2;margin:0}
.title p{font-size:9px;margin:2px 0 0}
.meta{display:grid;grid-template-columns:repeat(4,1fr);gap:1mm;margin:2mm 0}
.meta div{border:1px solid #aaa;padding:1.2mm;height:7mm;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.component-title{font-size:10px;line-height:5mm;height:5mm;margin:1mm 0}
.samples{width:279mm;max-width:279mm;border-collapse:collapse;table-layout:fixed}
.samples th,.samples td{border:1px solid #666;text-align:center;vertical-align:middle;padding:1mm;overflow:hidden}
.samples thead{display:table-header-group}
.samples th{height:8mm;background:#eee;font-size:7px;overflow-wrap:anywhere}
.samples tbody tr{height:4cm;break-inside:avoid;page-break-inside:avoid}
.samples tbody td{height:4cm;min-height:4cm;max-height:4cm}
.cell-value{display:flex;align-items:center;justify-content:center;width:100%;height:calc(4cm - 2mm);overflow:hidden;overflow-wrap:anywhere}
.samples .label-column{width:5cm;min-width:5cm;max-width:5cm}
.manual{height:5mm;line-height:5mm;margin-top:1mm;break-inside:avoid;page-break-inside:avoid}
.temperature{display:grid;grid-template-columns:1fr 1fr;gap:4mm;margin-top:2mm;break-inside:avoid;page-break-inside:avoid}
.temp h3{font-size:9px;line-height:4mm;height:4mm;margin:0 0 1mm}
.temp table{width:100%;border-collapse:collapse;table-layout:fixed}
.temp th,.temp td{border:1px solid #777;padding:1.5mm;text-align:center;height:7mm}
.temp th{background:#eee}
.temp tbody tr{break-inside:avoid;page-break-inside:avoid}
.page-number{position:absolute;right:0;bottom:0;height:5mm;line-height:5mm;color:#59636e;font-size:8px}
@media print{
  body{background:#fff}
  .print{display:none}
  .report-page{margin:0;width:279mm;height:192mm}
}
</style>
</head>
<body>
<button class="print" onclick="window.print()">Imprimir / PDF</button>
<?php foreach ($pages as $pageIndex => $page): ?>
<section class="report-page">
  <header class="report-header">
    <img src="/assets/images/bloodhub-logo-horizontal.png" alt="BloodHub">
    <div class="title">
      <h1>RELATÓRIO DE REMESSA DE AMOSTRAS PARA CONTROLE DE QUALIDADE</h1>
      <p>Sistema de Controle de Qualidade de Hemocomponentes</p>
    </div>
    <div></div>
  </header>
  <div class="meta">
    <div><b>Nº da Remessa:</b> <?= $h($shipment['shipment_code']) ?></div>
    <div><b>Envio:</b> <?= $shipment['sent_at'] ? date('d/m/Y H:i', strtotime($shipment['sent_at'])) : '—' ?></div>
    <div><b>Origem:</b> <?= $h($shipment['origin_name']) ?></div>
    <div><b>Cliente:</b> <?= $h($shipment['client_name'] ?? '—') ?></div>
    <div><b>Destino:</b> <?= $h($shipment['destination_name']) ?></div>
    <div><b>Responsável:</b> <?= $h($shipment['responsible_name']) ?></div>
    <div><b>Conferido por:</b> <?= $h($shipment['checked_by']) ?></div>
    <div><b>Observações:</b> <?= $h($shipment['notes'] ?? '—') ?></div>
  </div>
  <h2 class="component-title"><?= $h($page['code'] . ' - ' . $page['component_name']) ?></h2>

  <?php if ($page['kind'] === 'samples'): ?>
  <table class="samples">
    <colgroup>
      <col style="width:2.5cm"><col style="width:1.8cm"><col style="width:1.9cm"><col style="width:1.5cm">
      <?php foreach ($page['columns'] as $column): ?><col class="<?= in_array($column, ['Controle LCQH', 'Bacteriológico'], true) ? 'label-column' : '' ?>"><?php endforeach; ?>
    </colgroup>
    <thead><tr>
      <th>Nº da Doação</th><th>Produção</th><th>Marca da Bolsa</th><th>Hemocomponente</th>
      <?php foreach ($page['columns'] as $column): ?><th class="<?= in_array($column, ['Controle LCQH', 'Bacteriológico'], true) ? 'label-column' : '' ?>"><?= $h($column) ?></th><?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($page['samples'] as $sample): ?><tr>
      <td><span class="cell-value"><?= $h($sample['donation_number']) ?></span></td>
      <td><span class="cell-value"><?= date('d/m/Y', strtotime($sample['production_date'])) ?></span></td>
      <td><span class="cell-value"><?= $h($sample['bag_brand_name'] ?? '—') ?></span></td>
      <td><span class="cell-value"><?= $h($page['code']) ?></span></td>
      <?php foreach ($page['columns'] as $column): ?><td class="<?= in_array($column, ['Controle LCQH', 'Bacteriológico'], true) ? 'label-column' : '' ?>"></td><?php endforeach; ?>
    </tr><?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($page['show_manual']): ?><div class="manual"><b>Método:</b> ( ) Sysmex &nbsp; ( ) Neubauer &nbsp; ( ) Nageotte &nbsp;&nbsp;&nbsp; <b>Swirling:</b> ( ) Presente &nbsp; ( ) Ausente</div><?php endif; ?>
  <?php endif; ?>

  <?php if ($page['boxes']): ?>
  <div class="temperature">
    <?php foreach ([['REGISTRO DA TEMPERATURA DE ENVIO', 'sent_temperature', false], ['REGISTRO DA TEMPERATURA DE RECEBIMENTO', 'received_temperature', true]] as [$title, $field, $received]): ?>
    <div class="temp"><h3><?= $title ?></h3><table>
      <thead><tr><th>Nº da Caixa</th><th>Lacre</th><th>Temperatura</th><th>Horário</th></tr></thead>
      <tbody><?php foreach ($page['boxes'] as $box): ?><tr>
        <td><?= $h($box['box_code'] ?? '') ?></td><td><?= $h($box['received_seal'] ?? '') ?></td><td><?= $h($temperature($box[$field] ?? null)) ?></td><td><?= $received && !empty($box['received_at']) ? date('H:i', strtotime($box['received_at'])) : '' ?></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <footer class="page-number">Página <?= $pageIndex + 1 ?> de <?= $totalPages ?></footer>
</section>
<?php endforeach; ?>
<script>window.addEventListener('load',()=>setTimeout(()=>window.print(),150))</script>
</body>
</html>
