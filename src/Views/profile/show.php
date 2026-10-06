<?php require dirname(__DIR__) . '/layouts/admin_start.php';
$name=trim((string)($profile['name'] ?? 'Usuário')); $parts=preg_split('/\s+/u',$name,-1,PREG_SPLIT_NO_EMPTY)?:['U'];
$initials=mb_strtoupper(mb_substr($parts[0],0,1).(count($parts)>1?mb_substr($parts[count($parts)-1],0,1):''));
?>
<section class="profile-layout">
    <article class="profile-card profile-identity">
        <div class="profile-avatar-wrap">
            <?php if (!empty($profile['photo_path'])): ?><img class="profile-avatar" src="<?= htmlspecialchars($profile['photo_path'],ENT_QUOTES,'UTF-8') ?>" alt="Foto de <?= htmlspecialchars($name,ENT_QUOTES,'UTF-8') ?>"><?php else: ?><div class="profile-avatar profile-avatar-fallback"><?= htmlspecialchars($initials,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
            <span class="profile-status" title="Usuário ativo"></span>
        </div>
        <h2><?= htmlspecialchars($name,ENT_QUOTES,'UTF-8') ?></h2>
        <p><?= htmlspecialchars($userAuth['role_name']??'Sem perfil',ENT_QUOTES,'UTF-8') ?></p>
        <span class="profile-badge">Conta <?= ($profile['status']??'')==='active'?'ativa':'restrita' ?></span>
    </article>
    <article class="profile-card profile-details">
        <div class="section-heading"><div><h2>Informações pessoais</h2><p>Seus dados básicos de identificação no sistema.</p></div></div>
        <dl class="profile-data"><div><dt>Nome completo</dt><dd><?= htmlspecialchars($name,ENT_QUOTES,'UTF-8') ?></dd></div><div><dt>E-mail</dt><dd><?= htmlspecialchars($profile['email']??'',ENT_QUOTES,'UTF-8') ?></dd></div><div><dt>Perfil de acesso</dt><dd><?= htmlspecialchars($userAuth['role_name']??'Sem perfil',ENT_QUOTES,'UTF-8') ?></dd></div><div><dt>Status</dt><dd><?= ($profile['status']??'')==='active'?'Ativo':'Restrito' ?></dd></div></dl>
        <div class="profile-photo-section">
            <div><h3>Foto de perfil</h3><p>JPG, PNG ou WebP, com no máximo 2 MB. Prefira uma imagem quadrada.</p></div>
            <form class="photo-form" method="post" action="/profile" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
                <input type="hidden" name="MAX_FILE_SIZE" value="2097152">
                <label class="file-picker"><input id="profile-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp"><span id="profile-photo-label">Escolher foto</span></label>
                <button class="button" type="submit">Atualizar foto</button>
                <?php if (!empty($profile['photo_path'])): ?><button class="button button-ghost-danger" type="submit" name="remove_photo" value="1">Remover</button><?php endif; ?>
            </form>
        </div>
    </article>
    <article class="profile-card profile-password-card">
        <div class="section-heading"><div><h2>Alterar senha</h2><p>Confirme sua senha atual e escolha uma nova senha com pelo menos 8 caracteres.</p></div></div>
        <form class="profile-password-form" method="post" action="/profile">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
            <input type="hidden" name="action" value="change_password">
            <label><span>Senha atual</span><input type="password" name="current_password" required autocomplete="current-password"></label>
            <label><span>Nova senha</span><input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label>
            <label><span>Confirmar nova senha</span><input type="password" name="new_password_confirmation" required minlength="8" autocomplete="new-password"></label>
            <div class="profile-password-actions"><button class="button" type="submit">Alterar senha</button></div>
        </form>
    </article>
</section>
<script>document.querySelector('#profile-photo')?.addEventListener('change',function(){document.querySelector('#profile-photo-label').textContent=this.files[0]?.name||'Escolher foto'});</script>
<?php require dirname(__DIR__) . '/layouts/admin_end.php'; ?>
