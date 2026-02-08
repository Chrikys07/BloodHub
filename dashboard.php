<?php
session_start();

if (empty($_SESSION['auth']) || $_SESSION['auth'] !== true) {
  header("Location: index.php");
  exit;
}

$user = $_SESSION['user'] ?? ["name" => "Usuário", "role" => "user", "username" => "user"];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>BLoodHub • Sistema</title>

  <link rel="stylesheet" href="/bloodHub/assets/css/styles.css?v=10" />
  <link rel="stylesheet" href="/bloodHub/assets/css/app.css?v=10" />
</head>

<body>
  <header class="app-topbar" role="banner">
    <div class="app-topbar-inner">
      <div class="app-brand" aria-label="BloodHub">
        <div class="app-brand-mark" aria-hidden="true">BH</div>
        <div class="app-brand-text">
          <div class="app-brand-title">BloodHub</div>
          <div class="app-brand-subtitle">Gestão de Estoque de Hemocomponentes</div>
        </div>
      </div>

      <div class="app-topbar-right">
        <div class="userchip" id="userChip" data-username="<?php echo htmlspecialchars($user["username"]); ?>">
          <div class="avatar" aria-hidden="true"><?php echo strtoupper(substr($user["name"],0,1)); ?></div>
          <div class="userchip-meta">
            <div class="userchip-name"><?php echo htmlspecialchars($user["name"]); ?></div>
            <div class="userchip-sub"><?php echo htmlspecialchars($user["role"]); ?> • <?php echo htmlspecialchars($user["username"]); ?></div>
          </div>
          <button class="iconbtn" id="btnUserMenu" type="button" aria-label="Abrir menu do usuário">▾</button>
        </div>

        <div class="usermenu" id="userMenu" hidden>
          <a class="usermenu-item" href="#" aria-disabled="true" onclick="return false;">Perfil (em breve)</a>
          <a class="usermenu-item danger" href="logout.php">Sair</a>
        </div>
      </div>
    </div>
  </header>

  <main class="shell" data-username="<?php echo htmlspecialchars($user["username"]); ?>">
    <!-- SIDEBAR -->
    <aside class="app-sidebar" aria-label="Menu principal">
      <div class="sidebar-section">
        <div class="sidebar-h">Navegação</div>
        <div class="sidebar-s">Sessão: <?php echo htmlspecialchars($user["username"]); ?></div>
      </div>

      <nav class="app-nav" role="navigation">
        <button class="nav-group" id="navEstoque" type="button" aria-expanded="true">
          <span class="nav-ico" aria-hidden="true">▦</span>
          <span class="nav-label">Estoque</span>
          <span class="nav-caret" aria-hidden="true">▾</span>
        </button>

        <div class="nav-sub open" id="subEstoque">
          <button type="button" class="nav-sub-item" data-route="producao">
            <span class="sub-dot" aria-hidden="true"></span>
            Produção
          </button>
          <!-- Mantidos para fases futuras (não visíveis/ativos nesta versão) -->
          <button type="button" class="nav-sub-item is-hidden" data-route="liberacao" disabled>Liberação</button>
          <button type="button" class="nav-sub-item is-hidden" data-route="transformacao" disabled>Transformação</button>
          <button type="button" class="nav-sub-item is-hidden" data-route="desprezo" disabled>Desprezo</button>
          <button type="button" class="nav-sub-item is-hidden" data-route="inventario" disabled>Inventário</button>
        </div>
      </nav>

      <div class="sidebar-section sidebar-foot">
        <div class="small-muted">© <?php echo date('Y'); ?> BloodHub</div>
        <div class="small-muted">Segurança & rastreabilidade</div>
      </div>
    </aside>

    <!-- CONTENT -->
    <section class="app-content" aria-label="Conteúdo">
      <div class="content-head">
        <div>
          <div class="content-title" id="viewTitle">Início</div>
          <div class="content-sub" id="viewSub">Selecione uma opção no menu para começar.</div>
        </div>
      </div>

      <!-- HOME (neutro) -->
      <div class="view" id="view-home">
        <div class="welcome-grid">
          <div class="panel">
            <div class="panel-head">
              <div class="panel-title">Bem-vindo(a)</div>
              <div class="panel-badge">Ambiente de testes</div>
            </div>
            <p class="muted">
              Esta versão prioriza o novo layout (login → navegação → Produção) e usa persistência <strong>local</strong> para simulação.
              Em etapas futuras, o mesmo fluxo será conectado ao banco na nuvem.
            </p>
            <div class="kpi-row">
              <div class="kpi">
                <div class="kpi-label">Módulo</div>
                <div class="kpi-value">Estoque</div>
              </div>
              <div class="kpi">
                <div class="kpi-label">Primeira entrega</div>
                <div class="kpi-value">Produção</div>
              </div>
              <div class="kpi">
                <div class="kpi-label">Persistência</div>
                <div class="kpi-value">Local (simulação)</div>
              </div>
            </div>
          </div>

          <div class="panel">
            <div class="panel-head">
              <div class="panel-title">Atalhos</div>
              <div class="panel-badge">Desktop first</div>
            </div>
            <div class="shortcut-row">
              <button class="btn" type="button" data-shortcut="producao">Abrir Produção</button>
              <button class="btn-secondary" type="button" disabled>Outros módulos (em breve)</button>
            </div>
          </div>
        </div>
      </div>

      <!-- PRODUÇÃO -->
      <div class="view" id="view-producao" hidden>
        <section class="panel">
          <div class="panel-head">
            <div>
              <div class="panel-title">Produção diária</div>
              <div class="muted" style="margin-top:4px;">Informe a data e clique em <strong>Buscar</strong>.</div>
            </div>
            <div class="panel-badge" id="prodBadge">—</div>
          </div>

          <div class="filterbar">
            <label class="field">
              <span>Data</span>
              <input class="input" type="date" id="prodDate" />
            </label>
            <button class="btn" id="btnProdBuscar" type="button">Buscar</button>
            <div class="grow"></div>
            <span class="pill" id="prodStatus" style="display:none;"></span>
          </div>

          <div class="divider"></div>

          <!-- Formulário só aparece após Buscar (e confirmação quando não existir) -->
          <div id="prodFormWrap" style="display:none;">
            <div class="table-wrap">
              <table class="tbl tbl-compact" aria-label="Produção de hemocomponentes">
                <thead>
                  <tr>
                    <th style="text-align:left;">Hemocomponente</th>
                    <th style="width:180px;">Quantidade</th>
                  </tr>
                </thead>
                <tbody id="prodTbody"></tbody>
              </table>
            </div>

            <div class="actions-row">
              <button class="btn" id="btnProdSalvar" type="button">Salvar</button>
              <button class="btn-secondary" id="btnProdLimpar" type="button">Limpar campos</button>
              <span class="muted" id="prodHint">Os dados ficam salvos apenas neste navegador (simulação).</span>
            </div>
          </div>
        </section>
      </div>

    </section>
  </main>

  <!-- Modal: confirmação de criação para data sem dados -->
  <div class="modal" id="confirmModal" hidden>
    <div class="modal-backdrop" data-close="1"></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
      <div class="modal-head">
        <div>
          <div class="modal-title" id="confirmTitle">Confirmação</div>
          <div class="modal-sub" id="confirmMsg">—</div>
        </div>
        <button class="iconbtn" type="button" data-close="1" aria-label="Fechar">✕</button>
      </div>
      <div class="modal-actions">
        <button class="btn" id="confirmYes" type="button">Sim</button>
        <button class="btn-secondary" id="confirmNo" type="button">Não</button>
      </div>
    </div>
  </div>

  <script src="/bloodHub/assets/js/app_shell.js?v=10"></script>
</body>
</html>
