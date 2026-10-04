<?php
require dirname(__DIR__).'/layouts/admin_start.php';
$h=fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
$s=$snapshot['sample'];
$r=$snapshot['recipient'];
?>
<?php if($preview):?><div class="preview-watermark">PRÉVIA - NÃO LIBERADO</div><?php endif?>
<section class="report-sheet content-card">
 <header>
  <div class="report-logo report-logo-bloodhub"><img src="/assets/images/bloodhub-logo-horizontal.png" alt="BloodHub"></div>
  <div class="report-title"><strong>LABORATÓRIO DE CONTROLE DE QUALIDADE</strong><h2>Laudo de Hemocomponente</h2></div>
  <div class="report-logo report-logo-colsan"><img src="/assets/images/colsan.png" alt="COLSAN" onerror="this.hidden=true;this.nextElementSibling.style.display='block'"><span>COLSAN</span></div>
 </header>
 <div class="report-meta">
  <span><small>Recebimento</small><?=$s['received_at']?date('d/m/Y H:i',strtotime($s['received_at'])):'—'?></span>
  <span><small>Emissão</small><?=$report&&$report['released_at']?date('d/m/Y H:i',strtotime($report['released_at'])):'Não liberado'?></span>
 </div>
 <h3>Dados do Destinatário</h3>
 <dl class="report-grid">
  <div><dt>Cliente</dt><dd><?=$h($r['client'])?></dd></div>
  <div><dt>Unidade</dt><dd><?=$h($r['unit']?:'—')?></dd></div>
  <div><dt>Endereço</dt><dd><?=$h($r['address']?:'Não informado')?></dd></div>
  <div><dt>Bairro</dt><dd><?=$h($r['district']?:'Não informado')?></dd></div>
  <div><dt>Cidade / UF</dt><dd><?=$h(trim(($r['city']??'').' / '.($r['state']??''),' /'))?></dd></div>
  <div><dt>CEP</dt><dd><?=$h($r['postal_code']?:'Não informado')?></dd></div>
  <div><dt>Contato</dt><dd><?=$h($r['contact']?:'Não informado')?></dd></div>
  <div><dt>Telefone / Ramal</dt><dd><?=$h(trim(($r['phone']??'').' / '.($r['extension']??''),' /')?:'Não informado')?></dd></div>
 </dl>
 <h3>Informações do Hemocomponente</h3>
 <dl class="report-grid">
  <div><dt>Hemocomponente</dt><dd><?=$h($s['component'])?></dd></div>
  <div><dt>Nº da bolsa / doação</dt><dd><?=$h($s['donation']?:'—')?></dd></div>
  <div><dt>Data da coleta / produção</dt><dd><?=$s['production_date']?date('d/m/Y',strtotime($s['production_date'])):'—'?></dd></div>
  <div><dt>Código LCQH</dt><dd><?=$h($s['lcqh_code']?:'—')?></dd></div>
 </dl>
 <h3>Resultados finais</h3>
 <div class="table-wrap"><table><thead><tr><th>Parâmetro</th><th>Resultado</th><th>Referência</th><th>Método / Equipamento</th></tr></thead><tbody>
 <?php foreach($snapshot['results']as$result):?><tr><td><?=$h($result['parameter'])?></td><td><strong><?=$h($result['result'])?></strong></td><td><?=$h($result['reference'])?></td><td><?=$h($result['method_equipment'])?></td></tr><?php endforeach?>
 </tbody></table></div>
 <h3>Observações</h3><p><?=$h($report['notes_snapshot']??'Sem observações.')?:'Sem observações.'?></p>
 <?php if($report):?><footer><strong>RESPONSÁVEL PELA LIBERAÇÃO</strong><p><?=$h($report['release_user_name_snapshot'])?><br><?=$h($report['professional_registration_snapshot'])?></p><small>Liberado eletronicamente em <?=date('d/m/Y H:i',strtotime($report['released_at']))?>.</small></footer><?php endif?>
</section>
<div class="report-page-actions"><a class="button button-secondary" href="/reports">Voltar</a><?php if($report&&$report['status']!=='CANCELLED'):?><a class="button" href="/reports/download?id=<?=$report['id']?>">Baixar PDF</a><?php endif?></div>
<?php if(!empty($versions)):?><section class="content-card version-history"><h2>Histórico de versões</h2><table><thead><tr><th>Versão</th><th>Status</th><th>Emitido em</th><th>Emitido por</th><th>Motivo</th><th></th></tr></thead><tbody><?php foreach($versions as$version):?><tr><td>v<?=$version['version']?></td><td><?=$h(\BloodHub\Services\LaboratoryReportService::LABELS[$version['status']])?></td><td><?=$version['released_at']?date('d/m/Y H:i',strtotime($version['released_at'])):'—'?></td><td><?=$h($version['release_user_name_snapshot'])?></td><td><?=$h($version['revision_reason']?:$version['cancellation_reason']?:'—')?></td><td><a href="/reports/download?id=<?=$version['id']?>">Download</a></td></tr><?php endforeach?></tbody></table></section><?php endif?>
<?php require dirname(__DIR__).'/layouts/admin_end.php';?>
