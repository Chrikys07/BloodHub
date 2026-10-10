<?php
$topbarUser = $userAuth ?? \BloodHub\Core\Auth::user() ?? [];
$avatarData = \BloodHub\Core\UserAvatar::data($topbarUser);
$name = $avatarData['name']; $initials = $avatarData['initials']; $photo = $avatarData['photo'];
?>
<a class="topbar-brand" href="/" aria-label="Ir para o dashboard"><span class="topbar-brand-mark"></span><span>Ambiente seguro</span></a>
<div class="topbar-actions">
    <?php if (\BloodHub\Core\Permission::can('chat.view')): ?>
    <a class="notification-button chat-topbar-button" href="/chat" aria-label="Abrir HubChat" title="HubChat">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z"/><path d="M8 10h.01M12 10h.01M16 10h.01"/></svg>
        <span class="chat-topbar-badge" id="chat-topbar-badge" hidden>0</span>
    </a>
    <?php endif; ?>
    <div class="internal-notifications">
    <button class="notification-button" id="notification-center-toggle" type="button" aria-label="Notificações" title="Notificações" aria-expanded="false">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
        <span class="notification-topbar-badge" id="notification-topbar-badge" hidden>0</span>
    </button>
    <section class="notification-center" id="notification-center" hidden aria-label="Notificações recentes"><header><div><strong>Notificações</strong><small id="notification-center-summary">Nenhuma não lida</small></div><button type="button" id="notification-mark-all">Marcar todas como lidas</button></header><div id="notification-center-list" class="notification-center-list"><p class="notification-center-empty">Carregando...</p></div><footer><a href="/internal-notifications">Ver todas</a><?php if (\BloodHub\Core\Permission::can('notifications.view')): ?> · <a href="/notifications">Ocorrências de CQ</a><?php endif; ?></footer></section>
    </div>
    <details class="user-menu">
        <summary class="user-trigger">
            <?php if ($photo !== ''): ?><img class="avatar avatar-image" src="<?= htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') ?>" alt="Foto de <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"><?php else: ?><span class="avatar" aria-hidden="true"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
            <span class="user-copy"><strong><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($topbarUser['role_name'] ?? 'Sem perfil', ENT_QUOTES, 'UTF-8') ?></small></span>
            <svg class="menu-chevron" viewBox="0 0 20 20" aria-hidden="true"><path d="m6 8 4 4 4-4"/></svg>
        </summary>
        <div class="user-dropdown">
            <div class="dropdown-caption"><strong><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></strong><span><?= htmlspecialchars($topbarUser['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></div>
            <a href="/profile"><svg viewBox="0 0 24 24"><path d="M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z"/></svg>Meu perfil</a>
            <a class="dropdown-logout" href="/logout"><svg viewBox="0 0 24 24"><path d="M10 17l5-5-5-5M15 12H3M21 19V5a2 2 0 0 0-2-2h-5"/></svg>Sair</a>
        </div>
    </details>
</div>
<?php if (\BloodHub\Core\Permission::can('chat.view')): ?><script src="/assets/js/chat-badge.js" defer></script><?php endif; ?>
<script>window.BloodHubCsrf=<?=json_encode(\BloodHub\Core\Csrf::token(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script><script src="/assets/js/internal-notifications.js" defer></script>
