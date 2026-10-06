<?php
use BloodHub\Core\SamplePurpose;
require dirname(__DIR__,2).'/layouts/admin_start.php';
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<section class="billing-config-grid">
 <article class="card billing-panel billing-catalog-panel">
  <div class="billing-panel-heading"><div><h2>Centros de custo</h2><p>Cadastre e classifique os centros usados no faturamento.</p></div></div>
  <form method="post" action="/admin/billing/catalog" class="billing-form billing-center-form">
   <input type="hidden" name="_csrf" value="<?=$e($csrf)?>"><input type="hidden" name="type" value="center">
   <label>Código<input name="code" required></label><label>Nome<input name="name" required></label>
   <label>Unidade<select name="unit_id"><option value="">Sem vínculo</option><?php foreach($catalogs['units']as$u):?><option value="<?=(int)$u['id']?>"><?=$e($u['code'].' — '.$u['name'])?></option><?php endforeach?></select></label>
   <label>Cliente associado<select name="client_id"><option value="">Herdar da unidade / pendente</option><?php foreach($catalogs['clients']as$c):?><option value="<?=(int)$c['id']?>"><?=$e($c['name'].' — '.($c['client_type']==='internal'?'Interno':'Externo'))?></option><?php endforeach?></select></label>
   <label>Ordem<input type="number" name="display_order" value="0"></label><label class="billing-check"><input type="checkbox" name="active" value="1" checked><span>Ativo</span></label><button class="billing-btn-primary" type="submit">Salvar centro</button>
  </form>
  <div class="compact-list billing-catalog-list"><?php foreach($catalogs['centers']as$c):?><div><span><b><?=$e($c['cost_center_code'])?></b> <?=$e($c['name'])?></span><small><?=$c['unit_name']?'Vínculo: '.$e($c['unit_name']):'Somente faturamento'?></small></div><?php endforeach?></div>
 </article>
 <article class="card billing-panel billing-catalog-panel">
  <div class="billing-panel-heading"><div><h2>Serviços</h2><p>Organize os serviços disponíveis para associação.</p></div></div>
  <form method="post" action="/admin/billing/catalog" class="billing-form billing-service-form">
   <input type="hidden" name="_csrf" value="<?=$e($csrf)?>"><input type="hidden" name="type" value="service">
   <label>Código<input name="code" required></label><label>Nome<input name="name" required></label><label>Ordem<input type="number" name="display_order" value="0"></label><label class="billing-check"><input type="checkbox" name="active" value="1" checked><span>Ativo</span></label><button class="billing-btn-primary" type="submit">Salvar serviço</button>
  </form>
  <div class="compact-list billing-catalog-list"><?php foreach($catalogs['services']as$s):?><div><span><b><?=$e($s['service_code'])?></b> <?=$e($s['name'])?></span></div><?php endforeach?></div>
 </article>
</section>
<section class="card billing-panel billing-mapping-panel">
 <div class="billing-panel-heading"><div><h2>Associações de serviços</h2><p>Associe cada resultado final ao serviço institucional em que ele deve ser contabilizado.</p></div></div>
 <form method="post" action="/admin/billing/mapping" class="billing-form billing-mapping-form">
  <input type="hidden" name="_csrf" value="<?=$e($csrf)?>">
  <label>Serviço associado<select name="service_id" required><?php foreach($catalogs['services']as$s):?><option value="<?=(int)$s['id']?>"><?=$e($s['service_code'].' — '.$s['name'])?></option><?php endforeach?></select></label>
  <label>Resultado final<select name="test_id" required><?php foreach($catalogs['tests']as$t):?><option value="<?=(int)$t['id']?>"><?=$e(($t['code']?:'—').' — '.$t['name'])?></option><?php endforeach?></select></label>
  <label>Hemocomponente<select name="component_id"><option value="">Todos</option><?php foreach($catalogs['components']as$c):?><option value="<?=(int)$c['id']?>"><?=$e($c['code'].' — '.$c['name'])?></option><?php endforeach?></select></label>
  <fieldset class="billing-contexts"><legend>Contextos</legend><div><?php foreach(['quality_control'=>'Controle de Qualidade','validation'=>'Validação','single_assessment'=>'Avaliação','transfusion_reaction'=>'Reação Transfusional']as$k=>$v):?><label class="billing-check"><input type="checkbox" name="contexts[]" value="<?=$e($k)?>" <?=$k==='quality_control'?'checked':''?>><span><?=$e($v)?></span></label><?php endforeach?></div></fieldset>
  <label class="billing-check"><input type="checkbox" name="active" value="1" checked><span>Ativo</span></label><button class="billing-btn-primary" type="submit">Salvar associação</button>
 </form>
 <div class="table-wrap billing-table"><table><thead><tr><th>Serviço associado</th><th>Resultado final</th><th>Hemocomponente</th><th>Contexto</th><th>Ativo</th></tr></thead><tbody><?php foreach($mappings as$m):?><tr><td><?=$e($m['service_code'].' — '.$m['service_name'])?></td><td><?=$e($m['test_code'].' — '.$m['test_name'])?></td><td><?=$e($m['component_code']?:'Todos')?></td><td><?=$e(implode(', ',array_map([SamplePurpose::class,'label'],explode(',',(string)$m['context_scope']))))?></td><td><span class="status <?=$m['active']?'status-active':'status-inactive'?>"><?=$m['active']?'Sim':'Não'?></span></td></tr><?php endforeach?></tbody></table></div>
</section>
<section class="card billing-panel">
 <div class="billing-panel-heading"><div><h2>Classificação dos centros</h2><p>Confira os vínculos usados para classificar corretamente o faturamento.</p></div></div>
 <div class="table-wrap billing-table"><table><thead><tr><th>Centro</th><th>Unidade</th><th>Cliente associado</th><th>Tipo</th></tr></thead><tbody><?php foreach($catalogs['centers']as$c):?><tr><td><?=$e($c['cost_center_code'].' — '.$c['name'])?></td><td><?=$e($c['unit_name']?:'Sem unidade operacional')?></td><td><?=$e($c['client_name']?:'Centro de custo sem cliente associado')?></td><td><?=$c['client_type']==='internal'?'Interno':($c['client_type']==='external'?'Externo':'Pendente')?></td></tr><?php endforeach?></tbody></table></div>
</section>
<?php require dirname(__DIR__,2).'/layouts/admin_end.php'; ?>
