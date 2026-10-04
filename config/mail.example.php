<?php
declare(strict_types=1);

// Copie para config/mail.php somente quando o servidor não fornecer variáveis
// de ambiente. Nunca versione config/mail.php com credenciais reais.
return [
    'host' => '',
    'port' => 587,
    'username' => '',
    'password' => '',
    'encryption' => 'tls', // tls (STARTTLS), ssl ou none
    'from_address' => '',
    'from_name' => 'BloodHub',
];
