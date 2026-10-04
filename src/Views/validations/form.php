<?php require dirname(__DIR__).'/layouts/admin_start.php';$editing=!empty($validation['id']); ?>
<section class="content-card validation-form-card">
<div class="section-heading"><div><h2><?= $editing?'Editar validação':'Dados da validação' ?></h2><p>Defina o protocolo, a unidade responsável e o período planejado.</p></div></div>
<?php if($errors): ?><div class="alert alert-error"><ul><?php foreach($errors as$e): ?><li><?= htmlspecialchars($e,ENT_QUOTES,'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="<?= $editing?'/validations/edit':'/validations/create' ?>"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><?php if($editing): ?><input type="hidden" name="id" value="<?= (int)$validation['id'] ?>"><?php endif; ?>
<div class="validation-form-grid">
<label class="validation-field-pv"><span>Número do PV *</span><input name="pv_number" maxlength="80" required value="<?= htmlspecialchars($validation['pv_number']??'') ?>"></label>
<label class="validation-field-name"><span>Nome da validação *</span><input name="name" maxlength="180" required value="<?= htmlspecialchars($validation['name']??'') ?>"></label>
<label class="validation-field-unit"><span>Unidade responsável *</span><select name="unit_id" required><option value="">Selecione</option><?php foreach($units as$unit): ?><option value="<?= (int)$unit['id'] ?>" <?= (int)($validation['unit_id']??0)===(int)$unit['id']?'selected':'' ?>><?= htmlspecialchars($unit['name']) ?></option><?php endforeach; ?></select></label>
<label class="validation-field-status"><span>Status *</span><select name="status"><?php foreach(['planned'=>'Planejada','in_progress'=>'Em andamento','cancelled'=>'Cancelada'] as$k=>$l): ?><option value="<?= $k ?>" <?= ($validation['status']??'planned')===$k?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label>
<label class="validation-date"><span>Data de início *</span><input type="date" name="start_date" required value="<?= htmlspecialchars($validation['start_date']??date('Y-m-d')) ?>"></label>
<label class="validation-date"><span>Previsão de término</span><input type="date" name="expected_end_date" value="<?= htmlspecialchars($validation['expected_end_date']??'') ?>"></label>
<label class="validation-date"><span>Término efetivo</span><input type="date" readonly disabled value="<?= htmlspecialchars($validation['actual_end_date']??'') ?>"><small>Preenchido automaticamente na conclusão.</small></label>
<label class="validation-description"><span>Descrição</span><textarea name="description" rows="4"><?= htmlspecialchars($validation['description']??'') ?></textarea></label>
</div><div class="form-actions"><a class="button button-secondary" href="<?= $editing?'/validations/view?id='.(int)$validation['id']:'/validations' ?>">Cancelar</a><button class="button button-primary">Salvar validação</button></div></form></section>
<?php require dirname(__DIR__).'/layouts/admin_end.php'; ?>
