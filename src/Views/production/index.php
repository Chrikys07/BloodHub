<?php require dirname(__DIR__).'/layouts/admin_start.php';$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');$tab=($_GET['tab']??'daily')==='history'?'history':'daily';$months=[1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro']; ?>
<nav class="production-tabs" aria-label="Seções da produção">
 <a class="<?= $tab==='daily'?'active':'' ?>" href="/production?unit_id=<?=$unitId?>&date=<?=$h($date)?>">Registro diário</a>
 <a class="<?= $tab==='history'?'active':'' ?>" href="/production?tab=history&unit_id=<?=$unitId?>&month=<?=$month?>&year=<?=$year?>">Histórico mensal</a>
</nav>

<?php if($tab==='daily'): ?>
<section class="production-kpis">
 <article><span>Produção na data</span><strong><?=number_format($kpis['today'],0,',','.')?></strong></article>
 <article><span>Hemocomponentes informados</span><strong><?=$kpis['informed']?></strong></article>
 <article><span>Pendentes de informação</span><strong><?=$kpis['pending']?></strong></article>
 <article><span>Total do mês</span><strong><?=number_format($kpis['month'],0,',','.')?></strong></article>
</section>
<section class="content-card production-selector">
 <form method="get" action="/production">
  <label><span>Unidade de processamento</span><select name="unit_id" <?=$isAdmin||count($units)>1?'':'disabled'?>><?php foreach($units as$unit): ?><option value="<?=(int)$unit['id']?>" <?=(int)$unit['id']===$unitId?'selected':''?>><?=$h($unit['name'])?></option><?php endforeach; ?></select><?php if(!$isAdmin&&count($units)===1): ?><input type="hidden" name="unit_id" value="<?=$unitId?>"><?php endif; ?></label>
  <label><span>Data da produção *</span><input type="date" name="date" value="<?=$h($date)?>" max="<?=date('Y-m-d')?>" required></label>
  <button class="button button-secondary" type="submit">Carregar</button>
 </form>
</section>
<section class="content-card production-entry">
 <header><div><h2>Produção do dia</h2><p><?=date('d/m/Y',strtotime($date))?> · <?=count($components)?> hemocomponentes elegíveis ao CQ</p></div></header>
 <?php if(!$components): ?><div class="empty-state">Nenhum hemocomponente ativo e vinculado a teste de Controle de Qualidade.</div><?php else: ?>
 <form method="post" action="/production/save">
  <input type="hidden" name="_csrf" value="<?=$h($csrf)?>"><input type="hidden" name="unit_id" value="<?=$unitId?>"><input type="hidden" name="production_date" value="<?=$h($date)?>">
  <div class="production-list"><div class="production-list-head"><span>Hemocomponente</span><span>Quantidade produzida</span></div>
  <?php foreach($components as$c):$value=array_key_exists((int)$c['id'],$daily)?$daily[(int)$c['id']]:''; ?><label class="production-row"><span class="component-identity"><b><?=$h($c['code'])?></b><span><?=$h($c['name'])?></span></span><input aria-label="Quantidade de <?=$h($c['code'])?>" type="number" name="quantity[<?=(int)$c['id']?>]" value="<?=$value===''?'':(int)$value?>" min="0" step="1" inputmode="numeric" <?=$canSave?'':'disabled'?>></label><?php endforeach; ?></div>
  <?php if($canSave): ?><div class="production-actions"><button class="button button-primary" type="submit">Salvar produção</button></div><?php endif; ?>
 </form><?php endif; ?>
</section>
<?php else: ?>
<section class="content-card production-history-filters"><form method="get" action="/production"><input type="hidden" name="tab" value="history">
 <label><span>Unidade</span><select name="unit_id" <?=$isAdmin||count($units)>1?'':'disabled'?>><?php foreach($units as$unit): ?><option value="<?=(int)$unit['id']?>" <?=(int)$unit['id']===$unitId?'selected':''?>><?=$h($unit['name'])?></option><?php endforeach; ?></select><?php if(!$isAdmin&&count($units)===1): ?><input type="hidden" name="unit_id" value="<?=$unitId?>"><?php endif; ?></label>
 <label><span>Mês</span><select name="month"><?php foreach($months as$n=>$label): ?><option value="<?=$n?>" <?=$n===$month?'selected':''?>><?=$label?></option><?php endforeach; ?></select></label>
 <label><span>Ano</span><input type="number" name="year" min="2000" max="2100" value="<?=$year?>"></label>
 <label><span>Hemocomponente</span><select name="component_id"><option value="">Todos</option><?php foreach($components as$c): ?><option value="<?=(int)$c['id']?>" <?=(int)$componentId===(int)$c['id']?'selected':''?>><?=$h($c['code'].' — '.$c['name'])?></option><?php endforeach; ?></select></label>
 <button class="button button-primary">Filtrar</button>
</form></section>
<section class="production-summary-grid"><?php if(!$matrix): ?><article class="content-card empty-state">Nenhuma produção informada no período.</article><?php else:foreach($matrix as$row): ?><article class="content-card"><b><?=$h($row['code'])?></b><span><?=$h($row['name'])?></span><strong><?=number_format($row['total'],0,',','.')?></strong></article><?php endforeach;endif; ?></section>
<section class="content-card production-matrix-card"><header><div><h2>Matriz diária</h2><p><?=$h($months[$month])?> de <?=$year?> — zero é produção informada; travessão é não informado.</p></div></header><div class="production-matrix-scroll"><table class="production-matrix"><thead><tr><th>Hemocomponente</th><?php for($d=1;$d<=$days;$d++):?><th><?=str_pad((string)$d,2,'0',STR_PAD_LEFT)?></th><?php endfor;?><th>Total</th></tr></thead><tbody><?php foreach($matrix as$row):?><tr><th><b><?=$h($row['code'])?></b><small><?=$h($row['name'])?></small></th><?php for($d=1;$d<=$days;$d++):$v=$row['days'][$d]??null;?><td><a href="/production?unit_id=<?=$unitId?>&date=<?=sprintf('%04d-%02d-%02d',$year,$month,$d)?>"><?=$v===null?'—':number_format($v,0,',','.')?></a></td><?php endfor;?><td><strong><?=number_format($row['total'],0,',','.')?></strong></td></tr><?php endforeach;?></tbody></table></div></section>
<section class="content-card production-detail"><header><h2>Lançamentos do mês</h2></header><div class="table-wrap"><table><thead><tr><th>Data</th><th>Hemocomponente</th><th>Quantidade</th><th>Última atualização</th><th>Responsável</th><th></th></tr></thead><tbody><?php foreach($monthly as$row):?><tr><td><?=date('d/m/Y',strtotime($row['production_date']))?></td><td><b><?=$h($row['component_code'])?></b> <?=$h($row['component_name'])?></td><td><?=number_format((int)$row['quantity'],0,',','.')?></td><td><?=date('d/m/Y H:i',strtotime($row['updated_at']))?></td><td><?=$h($row['responsible_name']??'—')?></td><td><a class="table-action" href="/production?unit_id=<?=$unitId?>&date=<?=$h($row['production_date'])?>">Editar produção</a></td></tr><?php endforeach;?><?php if(!$monthly):?><tr><td colspan="6" class="empty-state">Nenhum lançamento encontrado.</td></tr><?php endif;?></tbody></table></div></section>
<?php endif; ?>
<?php require dirname(__DIR__).'/layouts/admin_end.php'; ?>
