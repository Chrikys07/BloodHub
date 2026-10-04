<?php
require dirname(__DIR__).'/layouts/admin_start.php';
$status=$checklist['status']['status'];
$isOpen=in_array($status,['OPEN','READY','REOPENED'],true);
$pendingCount=count($checklist['pending']);
?>
<div class="mc-page">
    <div class="mc-toolbar">
        <a class="mc-btn mc-btn-secondary" href="/monthly-closures?year=<?=$year?>&month=<?=$month?>">← Voltar</a>
        <div class="mc-toolbar-summary">
            <div><strong><?=htmlspecialchars($checklist['unit']['name'])?></strong><span><?=$checklist['period_label']?></span></div>
            <span class="mc-badge s-<?=strtolower($status)?>"><?=match($status){'READY'=>'Pronto para fechamento','CLOSED'=>'Fechado','REOPENED'=>'Reaberto',default=>'Em aberto'}?></span>
            <?php if ($pendingCount && $status!=='CLOSED'): ?><span class="mc-badge has-pending"><?=$pendingCount?> pendência<?=$pendingCount===1?'':'s'?></span><?php endif; ?>
        </div>
        <div class="mc-toolbar-action">
            <?php if ($isOpen && $canClose): ?>
                <button type="button" class="mc-btn mc-btn-primary" data-modal="close-modal">Fechar mês</button>
            <?php elseif ($status==='CLOSED' && $canReopen): ?>
                <button type="button" class="mc-btn mc-btn-warning" data-modal="reopen-modal">Reabrir mês</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($status==='CLOSED'): ?>
        <section class="mc-closed-summary" aria-label="Dados do fechamento">
            <span class="mc-badge s-closed">FECHADO</span>
            <div><span>Fechado por</span><strong><?=htmlspecialchars($checklist['status']['closed_by_name']??'—')?></strong></div>
            <div><span>Em</span><strong><?=$checklist['status']['closed_at']?date('d/m/Y H:i',strtotime($checklist['status']['closed_at'])):'—'?></strong></div>
        </section>
    <?php endif; ?>

    <section class="mc-kpis six">
        <article><span>Produção</span><strong><?=$checklist['totals']['production']?></strong></article>
        <article><span>Amostras exigidas</span><strong><?=$checklist['totals']['required']?></strong></article>
        <article><span>Amostras enviadas</span><strong><?=$checklist['totals']['sent']?></strong></article>
        <article class="ok"><span>CQ concluído</span><strong><?=$checklist['totals']['qc_completed']?></strong></article>
        <article class="warning"><span>CQ em andamento</span><strong><?=$checklist['totals']['qc_in_progress']?></strong></article>
        <article class="danger"><span>NC abertas</span><strong><?=$checklist['totals']['open_notifications']?></strong></article>
    </section>

    <section class="card">
        <div class="mc-section-title"><div><h2>Checklist do período</h2><p>Produção e amostragem conforme regras efetivas do mês.</p></div></div>
        <div class="mc-table-wrap"><table class="mc-table"><thead><tr><th>Hemocomponente</th><th>Produzido</th><th>Exigido</th><th>Enviado</th><th>Pendente</th><th>CQ concluído</th><th>Situação da amostragem</th></tr></thead><tbody>
        <?php foreach ($checklist['rows'] as $row): ?><tr><td><strong><?=htmlspecialchars($row['code'])?></strong><br><small><?=htmlspecialchars($row['name'])?></small></td><td><?=$row['produced']===null?'<span class="mc-missing">Não informada</span>':$row['produced']?></td><td><?=$row['required']?></td><td><?=$row['sent']?></td><td><?=$row['pending']?></td><td><?=$row['qc_completed']?></td><td><span class="mc-badge <?=$row['sampling_status']==='MET'?'s-ready':'has-pending'?>"><?=$row['sampling_status']==='MET'?'ATENDIDA':'ABAIXO DO MÍNIMO'?></span><?php if ($row['pending']): ?><small class="mc-block">Faltam <?=$row['pending']?> unidades.</small><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>

    <section class="mc-grid">
        <article class="card"><div class="mc-section-title"><div><h2>Pendências do período</h2><p>Bloqueantes exigem correção; justificáveis permitem aceite formal motivado.</p></div></div><?php if ($checklist['pending']): ?><ul class="mc-pending-list"><?php foreach ($checklist['pending'] as $pending): ?><li><span class="mc-badge <?=$pending['severity']==='BLOCKING'?'is-blocking':'has-pending'?>"><?=$pending['severity']==='BLOCKING'?'Bloqueante':'Justificável'?></span><div><strong><?=htmlspecialchars($pending['category'])?></strong><p><?=htmlspecialchars($pending['message'])?></p></div></li><?php endforeach; ?></ul><?php else: ?><div class="mc-success">Nenhuma pendência relevante. Período pronto para fechamento.</div><?php endif; ?></article>
        <article class="card"><h2>Acompanhamento e correções</h2><dl class="mc-details"><div><dt>Recebidas</dt><dd><?=$checklist['samples']['received']?></dd></div><div><dt>Em análise</dt><dd><?=$checklist['samples']['in_analysis']?></dd></div><div><dt>Resultados parciais</dt><dd><?=$checklist['samples']['partial_results']?></dd></div><div><dt>Concluídas</dt><dd><?=$checklist['samples']['completed']?></dd></div><div><dt>Notificações abertas</dt><dd><?=$checklist['notifications']['open']?></dd></div><div><dt>Em acompanhamento</dt><dd><?=$checklist['notifications']['in_follow_up']?></dd></div><div><dt>Resultados retificados</dt><dd><?=$checklist['corrections']['results_rectified']?></dd></div><div><dt>Amostras corrigidas</dt><dd><?=$checklist['corrections']['samples_corrected']?></dd></div></dl></article>
    </section>

    <section class="card mc-actions"><div><h2>Aceite formal</h2><p>O fechamento preserva o estado do mês sem interromper notificações, bacteriologia ou correções administrativas.</p></div></section>

    <?php if ($history): ?><section class="card"><h2>Histórico de fechamentos</h2><div class="mc-table-wrap"><table class="mc-table"><thead><tr><th>Versão</th><th>Fechado em</th><th>Por</th><th>Reaberto em</th><th>Motivo</th><th>Comprovante</th></tr></thead><tbody><?php foreach ($history as $item): ?><tr><td>Versão <?=$item['version_number']?></td><td><?=date('d/m/Y H:i',strtotime($item['closed_at']))?></td><td><?=htmlspecialchars($item['closed_by_name']??'—')?></td><td><?=$item['reopened_at']?date('d/m/Y H:i',strtotime($item['reopened_at'])):'—'?></td><td><?=htmlspecialchars($item['reopen_reason']??'—')?></td><td><a class="mc-btn mc-btn-secondary" target="_blank" href="/monthly-closures/pdf?closure_id=<?=$checklist['status']['id']?>&version=<?=$item['version_number']?>">Gerar comprovante PDF</a></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
</div>

<dialog id="close-modal" class="bh-modal">
    <form method="post" action="/monthly-closures/close">
        <input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="unit_id" value="<?=$unitId?>"><input type="hidden" name="year" value="<?=$year?>"><input type="hidden" name="month" value="<?=$month?>">
        <header><div><h2><?=$pendingCount?'Fechar mês com pendências':'Fechar mês'?></h2><p><?=htmlspecialchars($checklist['unit']['name'])?> · <?=$checklist['period_label']?></p></div><button type="button" data-close aria-label="Fechar modal">×</button></header>
        <dl class="mc-modal-summary"><div><dt>Unidade</dt><dd><?=htmlspecialchars($checklist['unit']['name'])?></dd></div><div><dt>Mês/Ano</dt><dd><?=$checklist['period_label']?></dd></div><div><dt>Produção informada</dt><dd><?=$checklist['totals']['production']?></dd></div><div><dt>Amostragem</dt><dd><?=$checklist['totals']['sent']?> / <?=$checklist['totals']['required']?></dd></div><div><dt>CQ concluído</dt><dd><?=$checklist['totals']['qc_completed']?></dd></div></dl>
        <?php if ($pendingCount): ?><div class="mc-modal-warning"><strong>Este período possui pendências.</strong><ul><?php foreach ($checklist['pending'] as $pending): ?><li><strong><?=htmlspecialchars($pending['category'])?>:</strong> <?=htmlspecialchars($pending['message'])?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <label><?=$pendingCount?'Justificativa para fechamento com pendências *':'Observação do fechamento'?><textarea name="closure_notes" rows="5" <?=$pendingCount?'required minlength="5" aria-required="true"':''?>></textarea></label>
        <footer><button type="button" class="mc-btn mc-btn-secondary" data-close>Cancelar</button><button type="submit" class="mc-btn mc-btn-primary">Confirmar fechamento</button></footer>
    </form>
</dialog>

<dialog id="reopen-modal" class="bh-modal">
    <form method="post" action="/monthly-closures/reopen">
        <input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="unit_id" value="<?=$unitId?>"><input type="hidden" name="year" value="<?=$year?>"><input type="hidden" name="month" value="<?=$month?>">
        <header><div><h2>Reabrir mês</h2><p><?=htmlspecialchars($checklist['unit']['name'])?> · <?=$checklist['period_label']?></p></div><button type="button" data-close aria-label="Fechar modal">×</button></header>
        <label>Motivo da reabertura *<textarea name="reopen_reason" rows="5" required minlength="5" aria-required="true"></textarea></label>
        <footer><button type="button" class="mc-btn mc-btn-secondary" data-close>Cancelar</button><button type="submit" class="mc-btn mc-btn-warning">Reabrir mês</button></footer>
    </form>
</dialog>
<script src="/assets/js/monthly-closures.js"></script>
<?php require dirname(__DIR__).'/layouts/admin_end.php'; ?>
