<?php require dirname(__DIR__) . '/layouts/admin_start.php'; ?>
<section class="settings-intro">
    <div><span class="eyebrow">Administração do sistema</span><h2>Centralize a gestão do BloodHub</h2><p>Cadastros estruturais, acessos e parâmetros operacionais em um único ambiente.</p></div>
    <span class="settings-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="M19 12a7 7 0 0 1-.12 1.3l2 1.55-2 3.46-2.48-1a7 7 0 0 1-2.28 1.32L13.75 21h-4l-.37-2.37a7 7 0 0 1-2.28-1.32l-2.48 1-2-3.46 2-1.55a7 7 0 0 1 0-2.6l-2-1.55 2-3.46 2.48 1a7 7 0 0 1 2.28-1.32L9.75 3h4l.37 2.37a7 7 0 0 1 2.28 1.32l2.48-1 2 3.46-2 1.55A7 7 0 0 1 19 12Z"/></svg></span>
</section>
<?php
$modules = [
 ['Equipamentos','Cadastre equipamentos laboratoriais e configure vínculos vigentes com testes.','/admin/equipment','tests'],
 ['Laudos','Configure a elegibilidade institucional para emissão de laudos.','/admin/reports','tests'],
 ['Faturamento','Configure centros de custo, serviços institucionais e associações de resultados finais.','/admin/billing','tests'],
 ['Indicadores','Configure metadados, metas vigentes e responsáveis pelos indicadores institucionais.','/admin/indicators','tests'],
 ['Metas de Conformidade','Configure metas agregadas do Dashboard por hemocomponente e teste, com vigência histórica.','/admin/dashboard-targets','tests'],
 ['Regras do Cronograma','Configure dias de envio, amostragem e fontes de produção com vigência histórica.','/admin/sampling-schedule','tests'],
 ['Correções administrativas','Localize amostras, retifique dados e consulte a rastreabilidade completa.','/admin/corrections','corrections'],
 ['Usuários','Gerencie contas, vínculos institucionais, status e perfis de acesso.','/admin/users','users'],
 ['Perfis','Organize os papéis e responsabilidades disponíveis no sistema.','/admin/roles','roles'],
 ['Permissões','Defina com precisão o acesso de cada perfil aos módulos.','/admin/permissions','permissions'],
 ['Clientes','Mantenha os clientes internos e externos centralizados.','/admin/clients','clients'],
 ['Unidades','Configure unidades, tipos operacionais e vínculos com clientes.','/admin/units','units'],
 ['Hemocomponentes','Administre o catálogo utilizado nos fluxos de qualidade.','/admin/blood-components','blood'],
 ['Marcas de Bolsa','Padronize as marcas de bolsas utilizadas nas remessas.','/admin/bag-brands','bag'],
 ['Testes','Configure ensaios, resultados e insumos necessários.','/admin/tests','tests'],
 ['Insumos','Controle cadastros, lotes, quantidades e datas de validade.','/admin/supplies','supplies'],
 ['Preservantes','Configure códigos e soluções preservantes das referências de bolsa.','/admin/preservatives','tests'],
];
?>
<section class="settings-grid" aria-label="Módulos de configuração">
<?php foreach ($modules as [$title,$description,$url,$icon]): ?>
<a class="settings-card" href="<?= $url ?>">
    <span class="settings-card-icon icon-<?= $icon ?>" aria-hidden="true"><span></span></span>
    <div class="settings-card-copy"><h3><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p></div>
    <span class="settings-card-action">Gerenciar <svg viewBox="0 0 20 20"><path d="m7 4 6 6-6 6"/></svg></span>
</a>
<?php endforeach; ?>
</section>
<?php require dirname(__DIR__) . '/layouts/admin_end.php'; ?>
