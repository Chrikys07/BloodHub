<?php require dirname(__DIR__).'/layouts/admin_start.php'; ?>
<div class="mc-page">
    <form class="mc-filters card" method="get">
        <label>Ano
            <input type="number" name="year" min="2000" max="2100" value="<?=$year?>">
        </label>
        <label>Mês
            <select name="month">
                <?php for ($m=1; $m<=12; $m++): ?>
                    <option value="<?=$m?>" <?=$month===$m?'selected':''?>><?=htmlspecialchars(\BloodHub\Services\QcMonthlyClosureService::monthName($m))?></option>
                <?php endfor; ?>
            </select>
        </label>
        <label>Unidade / Processamento
            <select name="unit_id">
                <option value="0">Todas as unidades</option>
                <?php foreach ($units as $u): ?>
                    <option value="<?=$u['id']?>" <?=$unitId===(int)$u['id']?'selected':''?>><?=htmlspecialchars($u['name'])?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Status
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (['OPEN'=>'Em aberto','READY'=>'Prontos','CLOSED'=>'Fechados','REOPENED'=>'Reabertos'] as $key=>$label): ?>
                    <option value="<?=$key?>" <?=$statusFilter===$key?'selected':''?>><?=$label?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="mc-btn mc-btn-primary mc-filter-submit">Aplicar filtros</button>
    </form>

    <?php if (!$unitId): ?>
        <section class="mc-kpis">
            <article><span>Processamentos</span><strong><?=$summary['processings']?></strong></article>
            <article class="ok"><span>Fechados</span><strong><?=$summary['closed']?></strong></article>
            <article class="warning"><span>Pendentes</span><strong><?=$summary['pending']?></strong></article>
            <article class="amber"><span>Reabertos</span><strong><?=$summary['reopened']?></strong></article>
        </section>
    <?php endif; ?>

    <section class="card mc-table-wrap">
        <table class="mc-table">
            <thead><tr><th>Processamento</th><th>Produção informada</th><th>Amostragem</th><th>Análises CQ</th><th>Pendências</th><th>Status</th><th>Ação</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): $status=$row['status']['status']; $pendingCount=count($row['pending']); ?>
                <tr>
                    <td><strong><?=htmlspecialchars($row['unit']['name'])?></strong></td>
                    <td><?=count(array_filter($row['rows'],fn($item)=>$item['production_informed']))?>/<?=count($row['rows'])?> componentes</td>
                    <td><?=$row['totals']['sent']?> / <?=$row['totals']['required']?></td>
                    <td><?=$row['totals']['qc_completed']?> concluídas<br><small><?=$row['totals']['qc_in_progress']?> em andamento</small></td>
                    <td>
                        <?=$pendingCount?>
                        <?php if ($pendingCount && $status!=='CLOSED'): ?><br><span class="mc-badge has-pending">Com pendências</span><?php endif; ?>
                        <?php if (!empty($row['status']['overdue'])): ?><br><span class="mc-overdue">Fechamento pendente</span><?php endif; ?>
                    </td>
                    <td><span class="mc-badge s-<?=strtolower($status)?>"><?=match($status){'READY'=>'Pronto para fechamento','CLOSED'=>'Fechado','REOPENED'=>'Reaberto',default=>'Em aberto'}?></span></td>
                    <td>
                        <a class="mc-btn mc-btn-secondary mc-open-unit" href="/monthly-closures/view?<?=http_build_query(['unit_id'=>$row['unit']['id'],'year'=>$year,'month'=>$month])?>">
                            Abrir unidade <span aria-hidden="true">→</span>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7" class="mc-empty">Nenhum processamento corresponde aos filtros.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
</div>
<?php require dirname(__DIR__).'/layouts/admin_end.php'; ?>
