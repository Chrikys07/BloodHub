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

  <link rel="stylesheet" href="/bloodHub/assets/css/styles.css?v=2" />
  <link rel="stylesheet" href="/bloodHub/assets/css/app.css?v=3" />
</head>

<body>
  <header class="topbar">
    <div class="topbar-inner">
      <div class="brand">
        <div class="brand-mark" aria-hidden="true">BH</div>
        <div class="brand-text">
          <div class="brand-title">BLoodHub</div>
          <div class="brand-subtitle">Gestão de Estoque de Hemocomponentes</div>
        </div>
      </div>

      <div class="topbar-right app-top-right">
        <span class="pill">
          <?php echo htmlspecialchars($user["name"]); ?> • <?php echo htmlspecialchars($user["role"]); ?>
        </span>
        <a class="pill pill-link" href="logout.php">Sair</a>
      </div>
    </div>
  </header>

  <main class="app">
    <!-- SIDEBAR -->
    <aside class="sidebar">
      <div class="sidebar-head">
        <div class="sidebar-title">Menu</div>
        <div class="sidebar-sub">Sessão: <?php echo htmlspecialchars($user["username"]); ?></div>
      </div>

      <nav class="nav">
        <!-- ESTOQUE (com submenu) -->
        <button class="nav-item active" id="navEstoque" data-view="estoque" aria-expanded="true">
          <span class="dot"></span>
          Estoque
          <span class="nav-caret" aria-hidden="true">▾</span>
        </button>

        <div class="nav-sub open" id="subEstoque">
          <button type="button" class="nav-sub-item active" data-subview="producao">Produção</button>
          <button type="button" class="nav-sub-item" data-subview="liberacao">Liberação</button>
          <button type="button" class="nav-sub-item" data-subview="transformacao">Transformação</button>
          <button type="button" class="nav-sub-item" data-subview="desprezo">Desprezo</button>
          <button type="button" class="nav-sub-item" data-subview="inventario">Inventário</button>
        </div>

        <!-- outros itens (placeholder) -->
        <button class="nav-item" data-view="dashboard" disabled title="Em breve">
          <span class="dot"></span>
          Dashboard
        </button>
        <button class="nav-item" data-view="relatorios" disabled title="Em breve">
          <span class="dot"></span>
          Relatórios
        </button>
        <button class="nav-item" data-view="config" disabled title="Em breve">
          <span class="dot"></span>
          Configurações
        </button>
      </nav>

      <div class="sidebar-foot">
        <div class="small-muted">© <?php echo date('Y'); ?> BLoodHub</div>
        <div class="small-muted">Segurança & rastreabilidade</div>
      </div>
    </aside>

    <!-- CONTENT -->
    <section class="content">
      <div class="content-head">
        <div>
          <div class="content-title" id="viewTitle">Estoque • Produção</div>
          <div class="content-sub" id="viewSub">Movimentações do estoque (layout FC0577)</div>
        </div>

        <!-- aqui a gente não usa mais os botões do estoque antigo -->
        <div class="head-actions" id="headActions" style="display:none;"></div>
      </div>

      <!-- VIEW: ESTOQUE -->
      <div class="view" id="view-estoque">
        <!-- PRODUÇÃO -->
        <section class="estoque-section" id="sec-producao">
          <h2 class="h2">Produção</h2>

          <div class="row-actions">
            <label class="field">
              <span>Data</span>
              <input type="date" id="dtProducao" />
            </label>
            <button class="btn" id="btnBuscarProd">Buscar</button>
            <span class="pill" id="statusProd" style="display:none;"></span>
          </div>

          <!-- OBS: o editor fica oculto até clicar em Buscar -->
          <div class="editor" id="editorProd" style="display:none;">
            <div class="toolbar">
              <button class="btn-secondary" id="btnAddProd">Inserir hemocomponente produzido</button>
              <button class="btn" id="btnSalvarProd">Finalizar inserção de dados</button>
            </div>
            <div class="divider"></div>
            <div class="list" id="listaProd"></div>
          </div>
        </section>

        <!-- LIBERAÇÃO -->
        <section class="estoque-section" id="sec-liberacao" style="display:none;">
          <h2 class="h2">Liberação</h2>

          <div class="row-actions">
            <label class="field">
              <span>Data</span>
              <input type="date" id="dtLiberacao" />
            </label>
            <button class="btn" id="btnBuscarLib">Buscar</button>
            <span class="pill" id="statusLib" style="display:none;"></span>
          </div>

          <div class="editor" id="editorLib" style="display:none;">
            <div class="toolbar">
              <button class="btn-secondary" id="btnAddLib">Inserir liberação</button>
              <button class="btn" id="btnSalvarLib">Finalizar inserção de dados</button>
            </div>
            <div class="divider"></div>
            <div class="list" id="listaLib"></div>
          </div>
        </section>

        <!-- TRANSFORMAÇÃO -->
        <section class="estoque-section" id="sec-transformacao" style="display:none;">
          <h2 class="h2">Transformação</h2>

          <div class="row-actions">
            <label class="field">
              <span>Data</span>
              <input type="date" id="dtTransf" />
            </label>
            <button class="btn" id="btnBuscarTransf">Buscar</button>
            <span class="pill" id="statusTransf" style="display:none;"></span>
          </div>

          <div class="editor" id="editorTransf" style="display:none;">
            <div class="toolbar">
              <button class="btn-secondary" id="btnAddTransf">Inserir transformação</button>
              <button class="btn" id="btnSalvarTransf">Finalizar inserção de dados</button>
            </div>
            <div class="divider"></div>
            <div class="list" id="listaTransf"></div>
          </div>
        </section>

        <!-- DESPREZO -->
        <section class="estoque-section" id="sec-desprezo" style="display:none;">
          <h2 class="h2">Desprezo</h2>

          <div class="row-actions">
            <label class="field">
              <span>Data</span>
              <input type="date" id="dtDesp" />
            </label>
            <button class="btn" id="btnBuscarDesp">Buscar</button>
            <span class="pill" id="statusDesp" style="display:none;"></span>
          </div>

          <div class="editor" id="editorDesp" style="display:none;">
            <div class="toolbar">
              <button class="btn-secondary" id="btnAddDesp">Inserir desprezo</button>
              <button class="btn" id="btnSalvarDesp">Finalizar inserção de dados</button>
            </div>
            <div class="divider"></div>
            <div class="list" id="listaDesp"></div>
          </div>
        </section>

        <!-- INVENTÁRIO -->
        <section class="estoque-section" id="sec-inventario" style="display:none;">
          <h2 class="h2">Inventário</h2>

          <div class="row-actions">
            <label class="field">
              <span>Data</span>
              <input type="date" id="dtInv" />
            </label>
            <button class="btn" id="btnBuscarInv">Buscar</button>
            <span class="pill" id="statusInv" style="display:none;"></span>
          </div>

          <div class="editor" id="editorInv" style="display:none;">
            <div class="toolbar">
              <button class="btn-secondary" id="btnAddInv">Inserir inventário</button>
              <button class="btn" id="btnSalvarInv">Finalizar inserção de dados</button>
            </div>
            <div class="divider"></div>
            <div class="list" id="listaInv"></div>
          </div>
        </section>

      </div>

      <!-- outras views (placeholder) -->
      <div class="view" id="view-dashboard" hidden></div>
      <div class="view" id="view-relatorios" hidden></div>
      <div class="view" id="view-config" hidden></div>

    </section>
  </main>

  <!-- IMPORTANTE:
       Removi o estoque.js antigo daqui, porque ele procura tabs/tabela que não existem mais nessa tela.
       Agora quem controla tudo é o estoque_modulo.js -->
  <script src="/bloodHub/assets/js/estoque_modulo.js?v=1"></script>
</body>
</html>
