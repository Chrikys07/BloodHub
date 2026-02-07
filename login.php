<?php
session_start();

// Segurança básica: só aceitar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: index.php");
  exit;
}

// Verifica CSRF
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
  $_SESSION['login_error'] = "Sessão inválida. Atualize a página e tente novamente.";
  header("Location: index.php");
  exit;
}

$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$remember = !empty($_POST['remember']);

// Rate-limit bem simples (por sessão)
if (!isset($_SESSION['login_tries'])) $_SESSION['login_tries'] = 0;
if (!isset($_SESSION['login_lock_until'])) $_SESSION['login_lock_until'] = 0;

$now = time();
if ($_SESSION['login_lock_until'] > $now) {
  $_SESSION['login_error'] = "Muitas tentativas. Aguarde alguns segundos e tente novamente.";
  header("Location: index.php");
  exit;
}

if (strlen($username) < 3 || strlen($password) < 6) {
  $_SESSION['login_error'] = "Preencha usuário e senha corretamente.";
  header("Location: index.php");
  exit;
}

/**
 * DEMO: usuários hardcoded.
 * Depois você troca por banco (MySQL) ou outro provider.
 * Dica: gere hashes com password_hash("SUA_SENHA", PASSWORD_DEFAULT)
 */
$users = [
  "admin" => [
    "name" => "Administrador",
    "password_hash" => '$2y$10$dpduQc5Ct4zYjsV8RBI9IuaGBFH3gRSwzNwMiKZH5bBMCq91CybQ2', // senha demo: Admin@123
    "role" => "admin",
  ],
  "cristiano" => [
    "name" => "Cristiano",
    "password_hash" => '$2y$10$ZVqYp8s7fD5oHfQ1pRzC0e4l8l7n9q1r5Zk8c2sJwqvH3p1o6B8mK', // senha demo: Blood@123
    "role" => "user",
  ],
];

$record = $users[strtolower($username)] ?? null;

if (!$record || !password_verify($password, $record["password_hash"])) {
  $_SESSION['login_tries'] += 1;

  if ($_SESSION['login_tries'] >= 5) {
    $_SESSION['login_lock_until'] = $now + 20; // 20s bloqueio
    $_SESSION['login_tries'] = 0;
  }

  $_SESSION['login_error'] = "Usuário ou senha inválidos.";
  header("Location: index.php");
  exit;
}

// Login OK
$_SESSION['auth'] = true;
$_SESSION['user'] = [
  "username" => strtolower($username),
  "name" => $record["name"],
  "role" => $record["role"],
  "logged_at" => date("Y-m-d H:i:s"),
];

// Regenera ID de sessão (evita session fixation)
session_regenerate_id(true);

// “Remember me” (demo): cookie simples.
// Em produção, o ideal é token no banco + rotação.
if ($remember) {
  setcookie("bloodhub_remember", $_SESSION['user']['username'], [
    "expires" => time() + (30 * 24 * 60 * 60),
    "path" => "/",
    "httponly" => true,
    "samesite" => "Lax",
    "secure" => false, // coloque true em HTTPS
  ]);
}

header("Location: dashboard.php");
exit;
