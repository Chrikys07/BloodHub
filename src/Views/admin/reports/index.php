<?php require dirname(__DIR__,2).'/layouts/admin_start.php'; ?>
<section class="content-card eligibility-card"><div class="section-heading"><div><h2>Elegibilidade de laudos</h2><p>Defina quais tipos de amostra podem gerar laudos para clientes internos e externos.</p></div></div>
<form method="post" action="/admin/reports" class="eligibility-form"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>">
<?php
$groups=[
 'Clientes externos'=>[
  'external_quality_control'=>['Controle de Qualidade','Disponibiliza laudos de Controle de Qualidade para clientes externos.'],
  'external_transfusion_reaction'=>['Reação Transfusional','Disponibiliza laudos de Reação Transfusional para clientes externos.'],
 ],
 'Clientes internos'=>[
  'internal_quality_control'=>['Controle de Qualidade','Disponibiliza laudos de Controle de Qualidade para clientes internos.'],
  'internal_transfusion_reaction'=>['Reação Transfusional','Disponibiliza laudos de Reação Transfusional para clientes internos.'],
 ],
];
foreach($groups as$title=>$rules):?>
<div class="eligibility-group"><h3><?=$title?></h3><?php foreach($rules as$key=>[$label,$description]):$active=!empty($eligibility[$key]);?><label class="eligibility-row"><span><strong><?=$label?></strong><small><?=$description?></small></span><span class="switch"><input type="checkbox" name="eligibility[<?=$key?>]" value="1" <?=$active?'checked':''?>><i></i><b><?=$active?'Ativo':'Inativo'?></b></span></label><?php endforeach?></div>
<?php endforeach?>
<div class="form-actions"><button class="button">Salvar configuração</button></div></form></section>
<?php require dirname(__DIR__,2).'/layouts/admin_end.php'; ?>
