<?php
require dirname(__DIR__).'/layouts/admin_start.php';
$group=[];
foreach($tests as $test)$group[$test['component_id']][]=$test;
$canEdit=\BloodHub\Core\Permission::can('validations.configure_tests')&&!in_array($validation['status'],['completed','cancelled'],true)&&($selectedPhase['status']??null)==='active';
?>
<div class="validation-plan-page">
<header class="validation-page-heading"><div><h2>Plano de testes</h2><p>Defina os hemocomponentes e os testes que farão parte desta validação.</p></div><a class="button button-secondary" href="/validations/view?id=<?= (int)$validation['id'] ?>">Voltar</a></header>
<nav class="validation-tabs" aria-label="Navegação da validação"><a href="/validations/view?id=<?= (int)$validation['id'] ?>">Visão geral</a><a class="active" aria-current="page" href="/validations/plan?id=<?= (int)$validation['id'] ?>">Plano de testes</a><a href="/validations/results?id=<?= (int)$validation['id'] ?>">Amostras e resultados</a></nav>
<form class="validation-plan-form" method="post" action="/validations/plan/save">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$validation['id'] ?>">
<section class="content-card validation-phase-card"><label><span>Etapa da validação</span><select name="phase_id" onchange="location='/validations/plan?id=<?= (int)$validation['id'] ?>&phase_id='+this.value" <?= count($phases)===1?'aria-readonly="true"':'' ?>><?php foreach($phases as$p): ?><option value="<?= (int)$p['id'] ?>" <?= $phase===(int)$p['id']?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?></select></label></section>
<div class="validation-component-grid">
<?php foreach($group as$componentId=>$rows): $selectedCount=count(array_filter($rows,fn($row)=>!empty($row['selected']))); ?>
<article class="validation-test-card <?= $selectedCount?'has-selection':'' ?>" data-component-card>
<header><span class="component-code"><?= htmlspecialchars($rows[0]['component_code']) ?></span><div><h3><?= htmlspecialchars($rows[0]['component_name']) ?></h3><small data-component-count><?= $selectedCount ?> teste<?= $selectedCount===1?'':'s' ?> selecionado<?= $selectedCount===1?'':'s' ?></small></div></header>
<div class="validation-test-grid">
<?php foreach($rows as$t): $selected=!empty($t['selected']);$required=$selected&&!empty($t['is_required']); ?>
<div class="validation-test-item <?= $selected?'is-selected':'' ?>" data-test-item>
<label class="test-choice"><input data-test-selector type="checkbox" name="tests[<?= (int)$componentId ?>][<?= (int)$t['test_id'] ?>][selected]" value="1" <?= $selected?'checked':'' ?> <?= $canEdit?'':'disabled' ?>><span><strong><?= htmlspecialchars($t['test_name']) ?></strong><?php if($t['unit']): ?><small><?= htmlspecialchars($t['unit']) ?></small><?php endif; ?></span></label>
<label class="required-choice"><input data-required type="checkbox" name="tests[<?= (int)$componentId ?>][<?= (int)$t['test_id'] ?>][required]" value="1" <?= $required?'checked':'' ?> <?= $selected&&$canEdit?'':'disabled' ?>><span>Obrigatório</span></label>
</div>
<?php endforeach; ?>
</div></article>
<?php endforeach; ?>
</div>
<footer class="validation-plan-actions"><span><strong data-total-count>0</strong> testes selecionados</span><div><a class="button button-secondary" href="/validations/view?id=<?= (int)$validation['id'] ?>">Voltar</a><?php if($canEdit): ?><button class="button button-primary">Salvar plano de testes</button><?php endif; ?></div></footer>
</form></div>
<script>
(()=>{const selectors=[...document.querySelectorAll('[data-test-selector]')],total=document.querySelector('[data-total-count]');function refresh(){let count=0;document.querySelectorAll('[data-component-card]').forEach(card=>{const selected=[...card.querySelectorAll('[data-test-selector]:checked')].length;count+=selected;card.classList.toggle('has-selection',selected>0);card.querySelector('[data-component-count]').textContent=`${selected} ${selected===1?'teste selecionado':'testes selecionados'}`});total.textContent=count}selectors.forEach(box=>box.addEventListener('change',()=>{const item=box.closest('[data-test-item]'),required=item.querySelector('[data-required]');required.disabled=!box.checked;if(!box.checked)required.checked=false;item.classList.toggle('is-selected',box.checked);refresh()}));refresh()})();
</script>
<?php require dirname(__DIR__).'/layouts/admin_end.php'; ?>
