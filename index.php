<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
  if (function_exists('random_bytes')) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  } elseif (function_exists('openssl_random_pseudo_bytes')) {
    $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
  } else {
    // fallback (último caso)
    $_SESSION['csrf_token'] = bin2hex(md5(uniqid((string)mt_rand(), true), true));
  }
}

// Mensagem de erro (quando login falha)
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

// Se já estiver logado, manda pro dashboard
if (!empty($_SESSION['auth']) && $_SESSION['auth'] === true) {
  header("Location: dashboard.php");
  exit;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>BLoodHub • Login</title>
  <link rel="stylesheet" href="/bloodHub/assets/css/styles.css?v=1" />
  <script src="/bloodHub/assets/js/login.js?v=1" defer></script>
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

      <div class="topbar-right">
        <span class="pill">Ambiente • Local</span>
      </div>
    </div>
  </header>

  <main class="page">
    <section class="login-wrap" aria-label="Área de login">
      <div class="login-card">
        <div class="login-head">
          <h1>Acesso ao sistema</h1>
          <p>Entre com suas credenciais para continuar.</p>
        </div>

        <?php if (!empty($error)): ?>
          <div class="alert" role="alert">
            <strong>Ops:</strong> <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>

        <form class="form" method="POST" action="login.php" autocomplete="on" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

          <label class="field">
            <span>Usuário</span>
            <input
              type="text"
              name="username"
              id="username"
              placeholder="ex: cristiano"
              inputmode="text"
              autocomplete="username"
              required
              minlength="3"
            />
            <small class="hint">Use seu usuário institucional.</small>
          </label>

          <label class="field">
            <span>Senha</span>
            <div class="password-row">
              <input
                type="password"
                name="password"
                id="password"
                placeholder="••••••••"
                autocomplete="current-password"
                required
                minlength="6"
              />
              <button type="button" class="ghost" id="togglePass" aria-label="Mostrar senha">
                Mostrar
              </button>
            </div>
            <small class="hint">Nunca compartilhe sua senha.</small>
          </label>

          <div class="row">
            <label class="check">
              <input type="checkbox" name="remember" value="1" />
              <span>Lembrar-me</span>
            </label>

            <a class="link" href="#" onclick="return false;" aria-disabled="true" title="Placeholder">
              Esqueci minha senha
            </a>
          </div>

          <button class="btn" type="submit" id="btnLogin">
            Entrar
          </button>

          <div class="footer-note">
            <span>© <?php echo date('Y'); ?> BLoodHub</span>
            <span>•</span>
            <span>Segurança & rastreabilidade</span>
          </div>
        </form>
      </div>

      <div class="side-card" aria-label="Informações do sistema">
        <div class="side-title">Bem-vindo ao BLoodHub</div>
        <ul class="side-list">
          <li>Controle de estoque por ABO/RH</li>
          <li>Rastreabilidade e auditoria</li>
          <li>Perfis de acesso por unidade</li>
          <li>Relatórios e indicadores</li>
        </ul>
        <div class="side-tip">
          Dica: no servidor real, use HTTPS e política de senha forte.
        </div>
      </div>
    </section>
  </main>

  <script src="assets/js/login.js"></script>
</body>
</html>
