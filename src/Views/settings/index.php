<?php require dirname(__DIR__) . '/layouts/admin_start.php'; ?>
<section class="settings-intro">
    <div><span class="eyebrow">Administração do sistema</span><h2>Centralize a gestão do BloodHub</h2><p>Cadastros estruturais, acessos e parâmetros operacionais em um único ambiente.</p></div>
    <span class="settings-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="M19 12a7 7 0 0 1-.12 1.3l2 1.55-2 3.46-2.48-1a7 7 0 0 1-2.28 1.32L13.75 21h-4l-.37-2.37a7 7 0 0 1-2.28-1.32l-2.48 1-2-3.46 2-1.55a7 7 0 0 1 0-2.6l-2-1.55 2-3.46 2.48 1a7 7 0 0 1 2.28-1.32L9.75 3h4l.37 2.37a7 7 0 0 1 2.28 1.32l2.48-1 2 3.46-2 1.55A7 7 0 0 1 19 12Z"/></svg></span>
</section>
<section class="settings-grid" aria-label="Módulos de configuração">
<?php foreach ($modules as $module): ?>
<a class="settings-card" href="<?= htmlspecialchars($module['url'], ENT_QUOTES, 'UTF-8') ?>">
    <span class="settings-card-icon icon-<?= htmlspecialchars($module['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"><span></span></span>
    <div class="settings-card-copy"><h3><?= htmlspecialchars($module['title'], ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars($module['description'], ENT_QUOTES, 'UTF-8') ?></p></div>
    <span class="settings-card-action">Gerenciar <svg viewBox="0 0 20 20"><path d="m7 4 6 6-6 6"/></svg></span>
</a>
<?php endforeach; ?>
</section>
<?php require dirname(__DIR__) . '/layouts/admin_end.php'; ?>
