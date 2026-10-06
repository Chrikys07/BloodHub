<?php
require dirname(__DIR__).'/layouts/admin_start.php';
$e=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
$labels=['planned'=>'Planejada','in_progress'=>'Em andamento','completed'=>'Concluída','cancelled'=>'Cancelada'];
$date=static fn($value)=>$value?date('d/m/Y',strtotime($value)):'—';
?>
<div class="validation-dashboard-selection">
<?php if($validations): ?>
<section class="validation-selection-list" aria-label="Validações disponíveis">
<?php foreach($validations as$validation):$components=array_filter(explode('||',(string)($validation['component_names']??''))); ?>
<article class="validation-selection-card">
 <div><small><?=$e($validation['pv_number'])?></small><h3><?=$e($validation['name'])?></h3><p><?=$e($validation['unit_name']??'Unidade não definida')?></p></div>
 <div><small>Hemocomponente</small><div class="validation-selection-components"><?php foreach($components as$component):?><span><?=$e($component)?></span><?php endforeach?><?php if(!$components):?><p>Não configurado</p><?php endif?></div></div>
 <div><small>Responsável</small><p><?=$e($validation['responsible_name']??'Não informado')?></p></div>
 <div><small>Período</small><p><?=$date($validation['start_date'])?> a <?=$date($validation['actual_end_date']?:$validation['expected_end_date'])?></p><span class="status status-<?=$e($validation['status'])?>"><?=$e($labels[$validation['status']]??$validation['status'])?></span></div>
 <a class="button button-primary" href="/validations/dashboard?id=<?=(int)$validation['id']?>">Abrir dashboard</a>
</article>
<?php endforeach?>
</section>
<?php else: ?>
<section class="content-card validation-selection-empty"><strong>Nenhuma validação disponível.</strong><p>Não há validações dentro do seu escopo de acesso.</p></section>
<?php endif?>
</div>
<?php require dirname(__DIR__).'/layouts/admin_end.php'; ?>
