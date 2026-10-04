<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="shortcut icon" href="/favicon.ico">
<title>BloodHub — Acesso</title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="login-body">
<main class="login-shell">
<section class="login-brand">
<header class="login-brand-header">
<img class="login-logo" src="/assets/images/bloodhub-logo-horizontal.png" alt="BloodHub — Sistema de Controle de Qualidade de Hemocomponentes">
</header>
<div class="login-brand-content">
<span class="login-brand-eyebrow">QUALIDADE QUE CONECTA</span>
<h2>Rastreabilidade.<br>Segurança.<br>Controle.</h2>
<p>Centralize amostras, resultados, validações, insumos e decisões em uma única plataforma.</p>
<ul>
<li>Rastreabilidade completa</li>
<li>Processos padronizados</li>
<li>Decisões mais seguras</li>
</ul>
</div>
</section>
<section class="login-card">
<div class="login-card-inner">
<div class="login-heading"><span class="eyebrow">ACESSO SEGURO</span><h2>Entrar no BloodHub</h2><p>Utilize suas credenciais para acessar o sistema.</p></div>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form method="post" action="/login" class="login-form">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
<label><span>E-mail</span><input type="email" name="email" required autocomplete="username" placeholder="nome@instituicao.org.br"></label>
<label><span>Senha</span><input type="password" name="password" required autocomplete="current-password" placeholder="••••••••"></label>
<button type="submit">Entrar</button>
</form>
</div>
</section>
</main>
</body>
</html>
