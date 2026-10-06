<?php $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
<aside class="sidebar">
    <div class="sidebar-brand"><img class="sidebar-logo" src="/assets/images/bloodhub-logo-horizontal.png" alt="BloodHub — Controle de Qualidade"></div>
    <nav class="sidebar-nav" aria-label="Navegação principal">
        <?php if (\BloodHub\Core\Permission::can('dashboard.global.view')): ?><a class="nav-item <?= $path === '/' ? 'active' : '' ?>" href="/">Dashboard Global</a><?php endif; ?>
        <?php if (\BloodHub\Core\Permission::can('reception.view')): ?><a class="nav-item <?= str_starts_with($path, '/reception') ? 'active' : '' ?>" href="/reception">Recebimento</a><?php endif; ?>
        <?php if (\BloodHub\Core\Permission::can('samples.view') || \BloodHub\Core\Permission::can('shipments.view')): ?><a class="nav-item <?= str_starts_with($path, '/samples') ? 'active' : '' ?>" href="/samples">Amostras</a><?php endif; ?>
        <?php if (\BloodHub\Core\Permission::can('production.view')): ?><a class="nav-item <?= str_starts_with($path, '/production') ? 'active' : '' ?>" href="/production">Produção</a><?php endif; ?>
        <?php if (\BloodHub\Core\Permission::can('sampling_schedule.view')): ?><a class="nav-item <?= str_starts_with($path, '/sampling-schedule') ? 'active' : '' ?>" href="/sampling-schedule"><span>Cronograma de Envio</span></a><?php endif; ?>
        <?php if (\BloodHub\Core\Permission::can('quality_results.view') || \BloodHub\Core\Permission::can('quality_control.results.manage')): ?><a class="nav-item <?= str_starts_with($path, '/quality-control') ? 'active' : '' ?>" href="/quality-control">Controle de Qualidade</a><?php endif; ?>
        <?php if (\BloodHub\Core\Permission::can('monthly_closure.view')): ?><a class="nav-item <?= str_starts_with($path, '/monthly-closures') ? 'active' : '' ?>" href="/monthly-closures">Fechamento Mensal</a><?php endif; ?>
        <?php if (\BloodHub\Core\Permission::can('validations.view')): ?><a class="nav-item <?= str_starts_with($path, '/validations') && $path !== '/validations/dashboard-select' ? 'active' : '' ?>" href="/validations">Validações</a><?php endif; ?>
        <?php if (\BloodHub\Core\Permission::can('validations.view')): ?><a class="nav-item <?= $path === '/validations/dashboard-select' ? 'active' : '' ?>" href="/validations/dashboard-select">Dashboard de Validações</a><?php endif; ?>
        <?php if (\BloodHub\Core\Permission::can('transfusion_reactions.view')): ?><a class="nav-item <?= str_starts_with($path, '/transfusion-reactions') ? 'active' : '' ?>" href="/transfusion-reactions">Reações Transfusionais</a><?php endif; ?>
        <?php if (\BloodHub\Core\Permission::can('transfusion_reaction_consultation.view')): ?><a class="nav-item <?= str_starts_with($path, '/transfusion-reaction-consultation') ? 'active' : '' ?>" href="/transfusion-reaction-consultation">Consulta de Reações Transfusionais</a><?php endif; ?>
        <?php if (\BloodHub\Core\Permission::can('reports.quality_control.view')): ?><a class="nav-item <?= str_starts_with($path, '/reports/quality-control') ? 'active' : '' ?>" href="/reports/quality-control">Relatórios</a><?php endif; ?>
        <?php if (\BloodHub\Core\Permission::can('indicators.view')): ?><a class="nav-item <?= str_starts_with($path, '/indicators') ? 'active' : '' ?>" href="/indicators">Indicadores</a><?php endif; ?>
        <?php if (\BloodHub\Core\Permission::can('billing.view')): ?><a class="nav-item <?= str_starts_with($path, '/billing') ? 'active' : '' ?>" href="/billing">Faturamento</a><?php endif; ?>
        <?php if (\BloodHub\Core\Permission::can('reports.release.view')): ?><a class="nav-item <?= $path === '/reports' || (str_starts_with($path, '/reports/') && !str_starts_with($path, '/reports/quality-control')) ? 'active' : '' ?>" href="/reports">Laudos</a><?php endif; ?>
        <?php if (\BloodHub\Core\Permission::can('notifications.view')): ?><a class="nav-item <?= str_starts_with($path, '/notifications') ? 'active' : '' ?>" href="/notifications">Notificações</a><?php endif; ?>
        <?php if (\BloodHub\Core\Auth::isAdministrator()): ?>
            <div class="nav-divider" aria-hidden="true"></div>
            <a class="nav-item nav-settings <?= str_starts_with($path, '/admin') ? 'active' : '' ?>" href="/admin">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-4v-.08A1.7 1.7 0 0 0 9 19.37a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.63 15 1.7 1.7 0 0 0 3.08 14H3v-4h.08A1.7 1.7 0 0 0 4.63 9a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.63 1.7 1.7 0 0 0 10 3.08V3h4v.08A1.7 1.7 0 0 0 15 4.63a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.37 9 1.7 1.7 0 0 0 20.92 10H21v4h-.08A1.7 1.7 0 0 0 19.4 15Z"/></svg>
                <span>Configuração</span>
            </a>
        <?php endif; ?>
    </nav>
</aside>
