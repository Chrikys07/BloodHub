<?php
session_start();

// Limpa variáveis de sessão
$_SESSION = [];

// Remove cookie "remember" (se você estiver usando)
setcookie("bloodhub_remember", "", time() - 3600, "/");

// Remove cookie de sessão (PHPSESSID) de forma compatível
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();

  // PHP antigo: setcookie(name, value, expires, path, domain, secure, httponly)
  setcookie(
    session_name(),
    "",
    time() - 42000,
    $params["path"] ?? "/",
    $params["domain"] ?? "",
    $params["secure"] ?? false,
    $params["httponly"] ?? true
  );
}

// Destrói a sessão
session_destroy();

// Redireciona pro login
header("Location: index.php");
exit;
