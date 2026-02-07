<?php
// gerar_hash.php
$senha = $_GET['s'] ?? 'TroqueIsso@123';
echo "<pre>";
echo "Senha: " . htmlspecialchars($senha) . "\n\n";
echo "Hash:\n" . password_hash($senha, PASSWORD_DEFAULT) . "\n";
echo "</pre>";
