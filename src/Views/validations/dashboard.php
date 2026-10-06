<?php
require dirname(__DIR__).'/layouts/admin_start.php';
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$validationLabels = ['planned'=>'Planejada','in_progress'=>'Em andamento','completed'=>'Concluída','cancelled'=>'Cancelada'];
$phaseLabels = ['active'=>'Em andamento','completed'=>'Concluída','cancelled'=>'Cancelada'];
$trendLabels = ['improved'=>'Melhora','stable'=>'Estável','regressed'=>'Regressão'];
$date = static fn($value) => $value ? date('d/m/Y', strtotime($value)) : '—';
$percent = static fn($value) => $value === null ? '—' : number_format((float) $value, 1, ',', '.').'%';
$variation = static function ($value): string {
    if ($value === null) return '—';
    return ($value > 0 ? '+' : '').number_format((float) $value, 1, ',', '.').' p.p.';
};
$chartPhases = array_values(array_filter($phases, static fn(array $phase): bool => $phase['global_conformity'] !== null));
$chartPoints = [];
foreach ($chartPhases as $index => $phase) {
    $x = count($chartPhases) === 1 ? 50 : 8 + ($index * 84 / (count($chartPhases) - 1));
    $y = 90 - ((float) $phase['global_conformity'] * .76);
    $chartPoints[] = ['x'=>$x,'y'=>$y,'phase'=>$phase];
}
?>
<div class="validation-dashboard">
 <section class="content-card validation-dashboard-head">
  <div><small><?=$e($validation['pv_number'])?></small><h2><?=$e($validation['name'])?></h2><p><?=$e($validation['unit_name']??'Unidade não definida')?> · <?=$date($validation['start_date'])?> a <?=$date($validation['actual_end_date']?:$validation['expected_end_date'])?></p></div>
  <div><span class="status status-<?=$e($validation['status'])?>"><?=$e($validationLabels[$validation['status']]??$validation['status'])?></span><a class="button button-secondary" href="/validations/view?id=<?=(int)$validation['id']?>">Voltar à validação</a></div>
 </section>

 <section class="validation-dashboard-kpis">
  <?php foreach ([['Etapas',$summary['phases']],['Etapas concluídas',$summary['completed_phases']],['Amostras',$summary['samples']],['Testes previstos',$summary['planned_tests']],['Resultados concluídos',$summary['completed_results']],['Conformidade global',$percent($summary['global_conformity'])]] as [$label,$value]): ?>
   <article><strong><?=$e($value)?></strong><span><?=$e($label)?></span></article>
  <?php endforeach ?>
 </section>

 <section class="content-card">
  <header class="validation-dashboard-section"><div><h3>Comparativo entre Etapas</h3><p>Rodadas da mesma validação, na ordem em que foram executadas.</p></div><?php if($bestTie):?><span class="validation-best-tie">Empate no melhor desempenho observado</span><?php endif?></header>
  <div class="validation-phase-comparison">
   <?php foreach ($phases as $phase): ?>
    <a class="validation-phase-summary <?=$phaseId===(int)$phase['id']?'active':''?> <?=$phase['best_performance']?'is-best':''?>" href="?id=<?=(int)$validation['id']?>&phase=<?=(int)$phase['id']?><?=$testId?'&test='.$testId:''?>">
     <header><div><small>Etapa <?=(int)$phase['sequence_order']?></small><strong><?=$e($phase['name'])?></strong></div><span class="status status-<?=$e($phase['status'])?>"><?=$e($phaseLabels[$phase['status']]??$phase['status'])?></span></header>
     <?php if ($phase['best_performance']): ?><b class="validation-best-label">Melhor desempenho observado</b><?php endif ?>
     <?php if (!empty($phase['description'])): ?><p class="validation-phase-condition"><b>Perfil/condição:</b> <?=$e($phase['description'])?></p><?php endif ?>
     <dl><div><dt>Amostras</dt><dd><?=(int)$phase['sample_count']?></dd></div><div><dt>Testes previstos</dt><dd><?=(int)$phase['planned_tests']?></dd></div><div><dt>Resultados concluídos</dt><dd><?=(int)$phase['completed_results']?> / <?=(int)$phase['expected_results']?></dd></div><div><dt>Conformidade</dt><dd><?=$percent($phase['global_conformity'])?></dd></div></dl>
     <footer><span><?=$date($phase['started_at']?:$phase['created_at'])?> a <?=$date($phase['completed_at'])?></span><?php if($phase['trend']):?><span class="validation-trend trend-<?=$e($phase['trend'])?>"><?=$e($trendLabels[$phase['trend']])?> · <?=$variation($phase['variation_pp'])?></span><?php endif?></footer>
    </a>
   <?php endforeach ?>
   <?php if (!$phases): ?><p class="validation-chart-empty">Nenhuma etapa disponível nesta validação.</p><?php endif ?>
  </div>
 </section>

 <section class="content-card">
  <header class="validation-dashboard-section"><div><h3>Evolução da Conformidade</h3><p>Percentual de resultados finais avaliáveis conformes. Estável significa variação de até ±<?=number_format($stableThreshold,1,',','.')?> p.p.; é um indicador analítico, não uma decisão de aprovação.</p></div></header>
  <?php if ($comparableCount >= 2): ?>
   <div class="validation-evolution-chart">
    <svg viewBox="0 0 100 100" role="img" aria-label="Evolução da conformidade entre etapas" preserveAspectRatio="none">
     <?php foreach ([0,25,50,75,100] as $tick): $y=90-$tick*.76; ?><line x1="8" y1="<?=$y?>" x2="92" y2="<?=$y?>" class="chart-grid"/><text x="1" y="<?=$y+1?>"><?=$tick?>%</text><?php endforeach ?>
     <polyline points="<?=implode(' ',array_map(static fn($point)=>$point['x'].','.$point['y'],$chartPoints))?>"/>
     <?php foreach ($chartPoints as $point): ?><circle cx="<?=$point['x']?>" cy="<?=$point['y']?>" r="1.8"><title><?=$e($point['phase']['name'])?>: <?=$percent($point['phase']['global_conformity'])?></title></circle><text class="chart-value" x="<?=$point['x']?>" y="<?=$point['y']-4?>" text-anchor="middle"><?=$percent($point['phase']['global_conformity'])?></text><text class="chart-label" x="<?=$point['x']?>" y="97" text-anchor="middle">Etapa <?=(int)$point['phase']['sequence_order']?></text><?php endforeach ?>
    </svg>
   </div>
  <?php else: ?><p class="validation-chart-empty">São necessárias pelo menos duas etapas com resultados avaliáveis para exibir a comparação.</p><?php endif ?>
 </section>

 <section class="content-card">
  <form class="validation-dashboard-filter" method="get"><input type="hidden" name="id" value="<?=(int)$validation['id']?>"><input type="hidden" name="phase" value="<?=$phaseId?>"><label><span>Teste</span><select name="test"><option value="">Todos os testes</option><?php foreach($tests as $test):?><option value="<?=(int)$test['id']?>" <?=$testId===(int)$test['id']?'selected':''?>><?=$e($test['name'])?></option><?php endforeach?></select></label><button class="button">Aplicar</button></form>
  <header class="validation-dashboard-section"><div><h3>Comparativo por Teste</h3><p>“Não avaliado” indica que o teste não fazia parte da etapa; “Sem resultado avaliável” não é tratado como zero nem como não conformidade.</p></div></header>
  <?php if ($comparison && $phases): ?>
   <div class="table-wrap validation-comparison-table"><table><thead><tr><th>Teste</th><?php foreach($phases as $phase):?><th class="<?=$phaseId===(int)$phase['id']?'is-selected':''?>">Etapa <?=(int)$phase['sequence_order']?><small><?=$e($phase['name'])?></small></th><?php endforeach?></tr></thead><tbody>
    <?php foreach ($comparison as $test): ?><tr><th><strong><?=$e($test['name'])?></strong><small><?=$e($test['code'])?></small></th><?php foreach($phases as $phase):$cell=$test['phases'][(int)$phase['id']];$result=$cell['result'];?><td class="<?=$phaseId===(int)$phase['id']?'is-selected':''?>"><?php if(!$cell['planned']):?><span class="validation-not-evaluated">Não avaliado</span><?php elseif(!$result||(int)$result['assessable_count']===0):?><span class="validation-not-evaluated">Sem resultado avaliável</span><?php else:?><strong class="validation-test-percent"><?=$percent($result['percent'])?></strong><small><?=(int)$result['assessable_count']?> avaliados · <?=(int)$result['conforming_count']?> conformes · <?=(int)$result['nonconforming_count']?> não conformes</small><?php if($test['result_type']==='numeric'&&$result['average_value']!==null):?><small>Média: <?=$e(number_format((float)$result['average_value'],2,',','.'))?><?=$test['unit']?' '.$e($test['unit']):''?></small><?php endif?><div class="validation-compliance-bar"><i style="width:<?=$e($result['percent'])?>%"></i></div><?php endif?></td><?php endforeach?></tr><?php endforeach ?>
   </tbody></table></div>
  <?php else: ?><p class="validation-chart-empty">Não há testes finais configurados para comparar.</p><?php endif ?>
 </section>
</div>
<?php require dirname(__DIR__).'/layouts/admin_end.php'; ?>
