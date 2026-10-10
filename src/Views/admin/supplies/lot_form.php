<?php require dirname(__DIR__,2).'/layouts/admin_start.php';$editing=!empty($lot['id']);$h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); ?>
<link rel="stylesheet" href="/assets/css/supplies.css">
<section class="content-card form-card">
<div class="section-heading"><div><span class="eyebrow">Insumo: <?= $h($supply['name']) ?></span><h2><?= $editing?'Editar lote':'Cadastrar lote' ?></h2><p>Informe validade, recebimento e a situação operacional do lote.</p></div><a class="button button-secondary" href="/admin/supplies/lots?supply_id=<?= (int)$supply['id'] ?>">Voltar</a></div>
<?php if($errors): ?><div class="alert alert-error"><ul><?php foreach($errors as $error): ?><li><?= $h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form class="admin-form" method="post" action="<?= $editing?'/admin/supplies/lots/edit':'/admin/supplies/lots/create' ?>">
<input type="hidden" name="_csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="supply_id" value="<?= (int)$supply['id'] ?>"><?php if($editing): ?><input type="hidden" name="id" value="<?= (int)$lot['id'] ?>"><?php endif; ?>
<div class="form-grid">
<label><span>Insumo</span><input value="<?= $h($supply['name']) ?>" disabled></label>
<label><span>Número do lote *</span><input name="lot_number" required maxlength="120" value="<?= $h($lot['lot_number']??'') ?>"></label>
<label><span>Data de validade *</span><input type="date" name="expiration_date" required value="<?= $h($lot['expiration_date']??'') ?>"></label>
<label><span>Data de recebimento</span><input type="date" name="received_at" value="<?= $h($lot['received_at']??'') ?>"></label>
<label><span>Quantidade inicial</span><input type="number" min="0" step="0.0001" name="quantity_initial" value="<?= $h($lot['quantity_initial']??'') ?>"></label>
<label><span>Quantidade disponível</span><input type="number" min="0" step="0.0001" name="quantity_available" value="<?= $h($lot['quantity_available']??'') ?>"><small class="field-help">No cadastro, se ficar vazia, será preenchida com a quantidade inicial.</small></label>
<label><span>Status *</span><select name="status" required><?php foreach(['active'=>'Ativo','inactive'=>'Inativo','exhausted'=>'Esgotado','blocked'=>'Bloqueado'] as $value=>$label): ?><option value="<?= $value ?>" <?= ($lot['status']??'active')===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
</div>
<fieldset class="operational-use-card" data-operational-use>
<legend>Uso operacional</legend>
<div class="operational-use-content">
<div class="operational-use-copy"><strong>Lote em uso</strong><p>Define este lote como o lote utilizado atualmente nos testes vinculados a este insumo.</p></div>
<label class="operational-switch" for="is-in-use"><input id="is-in-use" type="checkbox" name="is_in_use" value="1" <?= !empty($lot['is_in_use'])?'checked':'' ?>><span class="operational-switch-track" aria-hidden="true"></span><span class="operational-switch-state" data-off="Não está em uso" data-on="Em uso"></span></label>
</div>
<small class="operational-use-help">Ao ativar este lote, o lote anteriormente em uso para este insumo será desmarcado automaticamente.</small>
</fieldset>
<div class="form-actions"><button class="button" type="submit">Salvar Lote</button></div></form></section>
<?php require dirname(__DIR__,2).'/layouts/admin_end.php'; ?>
