<?php require dirname(__DIR__).'/layouts/admin_start.php'; ?>
<div class="reception-metrics" aria-label="Indicadores da fila">
    <div class="reception-metric"><span>Remessas aguardando</span><strong><?= (int)$counters['shipment_count'] ?></strong></div>
    <div class="reception-metric"><span>Amostras pendentes</span><strong><?= (int)$counters['sample_count'] ?></strong></div>
    <div class="reception-metric"><span>Caixas em trânsito</span><strong><?= (int)$counters['box_count'] ?></strong></div>
</div>

<section class="content-card reception-queue">
    <form class="reception-filters" method="get" action="/reception">
        <label><span>Nº da remessa</span><input type="search" name="shipment_code" maxlength="80" value="<?= htmlspecialchars($filters['shipment_code'],ENT_QUOTES,'UTF-8') ?>" placeholder="REM-..."></label>
        <label><span>Unidade / origem</span><select name="origin_unit_id"><option value="">Todas</option><?php foreach($origins as $origin): ?><option value="<?= (int)$origin['id'] ?>" <?= (int)$filters['origin_unit_id']===(int)$origin['id']?'selected':'' ?>><?= htmlspecialchars($origin['name'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></label>
        <label><span>Finalidade</span><select name="purpose"><option value="">Todas</option><?php foreach($purposes as $value=>$label): ?><option value="<?= htmlspecialchars($value,ENT_QUOTES,'UTF-8') ?>" <?= $filters['purpose']===$value?'selected':'' ?>><?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></label>
        <label><span>Data de envio</span><input type="date" name="sent_date" value="<?= htmlspecialchars($filters['sent_date'],ENT_QUOTES,'UTF-8') ?>"></label>
        <div class="reception-filter-actions"><button class="button" type="submit">Filtrar</button><a class="button button-secondary" href="/reception">Limpar filtros</a></div>
    </form>
    <div class="queue-heading"><div><h2>Fila de recebimento</h2><p>Ordem de chegada: remessas mais antigas primeiro.</p></div><span><?= count($shipments) ?> resultado(s)</span></div>
    <div class="table-wrap"><table class="reception-table"><thead><tr><th>Nº da remessa</th><th>Origem</th><th>Finalidade</th><th>Envio</th><th>Responsável</th><th>Amostras</th><th>Caixas</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach($shipments as $shipment): ?><tr>
        <td><strong><?= htmlspecialchars($shipment['shipment_code'],ENT_QUOTES,'UTF-8') ?></strong><?php if(!empty($shipment['client_name'])): ?><small class="cell-subtitle"><?= htmlspecialchars($shipment['client_name'],ENT_QUOTES,'UTF-8') ?></small><?php endif; ?></td>
        <td><?= htmlspecialchars($shipment['origin_name'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars(\BloodHub\Core\SamplePurpose::label($shipment['purpose']),ENT_QUOTES,'UTF-8') ?><?php if(!empty($shipment['pv_number'])): ?><small class="cell-subtitle"><?= htmlspecialchars($shipment['pv_number'].' / '.$shipment['validation_phase_name']) ?></small><?php endif; ?></td>
        <td><time datetime="<?= htmlspecialchars($shipment['sent_at'],ENT_QUOTES,'UTF-8') ?>"><?= date('d/m/Y H:i',strtotime($shipment['sent_at'])) ?></time></td>
        <td><?= htmlspecialchars($shipment['responsible_name'],ENT_QUOTES,'UTF-8') ?></td>
        <td><strong><?= (int)$shipment['sample_count'] ?> amostras</strong><small class="cell-subtitle"><?= (int)$shipment['received_count'] ?> recebidas · <?= (int)$shipment['awaiting_count'] ?> aguardando · <?= (int)$shipment['rejected_count'] ?> recusadas</small></td><td><?= (int)$shipment['box_count'] ?></td>
        <td><span class="status status-<?= htmlspecialchars($shipment['status'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars(\BloodHub\Core\StatusLabel::label($shipment['status']),ENT_QUOTES,'UTF-8') ?></span></td>
        <td><a class="button button-secondary button-compact" href="/reception/view?id=<?= (int)$shipment['id'] ?>">Conferir</a></td>
    </tr><?php endforeach; ?>
    <?php if(!$shipments): ?><tr><td colspan="9" class="empty">Nenhuma remessa corresponde aos filtros e aguarda recebimento.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php require dirname(__DIR__).'/layouts/admin_end.php'; ?>
