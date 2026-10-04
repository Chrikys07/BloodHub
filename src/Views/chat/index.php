<?php require dirname(__DIR__).'/layouts/admin_start.php'; ?>
<link rel="stylesheet" href="/assets/css/chat.css">
<section class="chat-app" id="chat-app" data-user-id="<?= (int)$userAuth['id'] ?>" data-csrf="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
  <aside class="chat-sidebar" aria-label="Conversas">
    <div class="chat-sidebar-head">
      <div><h2>Conversas</h2><p>Comunicação interna</p></div>
      <?php if(\BloodHub\Core\Permission::can('chat.private') || (\BloodHub\Core\Permission::can('chat.send') && \BloodHub\Core\Permission::can('chat.group'))): ?>
        <button class="chat-new-button" id="chat-new" type="button"><span aria-hidden="true">+</span> Nova conversa</button>
      <?php endif; ?>
    </div>
    <div class="chat-conversation-list" id="chat-conversations"><div class="chat-loading">Carregando conversas...</div></div>
    <div class="chat-users" id="chat-users" hidden>
      <div class="chat-users-head"><button id="chat-users-back" type="button" aria-label="Voltar para conversas">&larr;</button><strong>Nova conversa</strong></div>
      <div class="chat-new-tabs" role="tablist" aria-label="Tipo de conversa">
        <?php if(\BloodHub\Core\Permission::can('chat.private')): ?><button id="chat-tab-people" class="active" type="button" role="tab" aria-selected="true">Pessoas</button><?php endif; ?>
        <?php if(\BloodHub\Core\Permission::can('chat.send') && \BloodHub\Core\Permission::can('chat.group')): ?><button id="chat-tab-sectors" type="button" role="tab" aria-selected="false">Setores</button><?php endif; ?>
      </div>
      <div id="chat-users-list"><div class="chat-loading">Carregando...</div></div>
    </div>
  </aside>
  <div class="chat-panel" id="chat-panel">
    <div class="chat-empty" id="chat-empty"><span class="chat-empty-icon">&#128172;</span><h2>HubChat</h2><p>Comunicação interna</p><small>Selecione uma conversa para visualizar as mensagens.</small></div>
    <div class="chat-room" id="chat-room" hidden>
      <header class="chat-room-head"><button class="chat-mobile-back" id="chat-mobile-back" type="button" aria-label="Voltar para conversas">&larr; <span>Conversas</span></button><div id="chat-room-avatar"></div><div><h2 id="chat-room-title"></h2><button class="chat-participant-trigger" id="chat-room-subtitle" type="button"></button></div><div class="chat-participant-popover" id="chat-participant-popover" hidden></div></header>
      <div class="chat-history" id="chat-history"><button class="chat-older" id="chat-older" type="button" hidden>Carregar mensagens anteriores</button><div id="chat-messages"></div></div>
      <?php if(\BloodHub\Core\Permission::can('chat.send')): ?><form class="chat-compose" id="chat-form"><textarea id="chat-input" maxlength="4000" rows="1" placeholder="Escreva uma mensagem..." aria-label="Mensagem"></textarea><button type="submit" aria-label="Enviar mensagem"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="M22 2 11 13"/></svg></button></form><?php else: ?><div class="chat-compose-disabled">Você possui acesso somente para leitura.</div><?php endif; ?>
    </div>
  </div>
</section>
<div class="chat-toast" id="chat-toast" role="status" aria-live="polite" hidden></div>
<div class="chat-confirm-backdrop" id="chat-archive-confirm" hidden><div class="chat-confirm" role="dialog" aria-modal="true" aria-labelledby="chat-confirm-title"><h2 id="chat-confirm-title">Remover esta conversa da sua lista?</h2><p>O histórico será preservado. A conversa poderá reaparecer caso uma nova mensagem seja enviada ou você inicie uma conversa novamente com este usuário.</p><div><button id="chat-archive-cancel" type="button">Cancelar</button><button id="chat-archive-submit" class="danger" type="button">Remover da lista</button></div></div></div>
<script src="/assets/js/chat.js" defer></script>
<?php require dirname(__DIR__).'/layouts/admin_end.php'; ?>
