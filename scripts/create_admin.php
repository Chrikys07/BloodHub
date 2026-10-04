<?php
declare(strict_types=1);

use BloodHub\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script so pode ser executado pela linha de comando.\n");
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'BloodHub\\';
    $baseDir = dirname(__DIR__) . '/src/';
    if (!str_starts_with($class, $prefix)) return;

    $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require $file;
});

function prompt(string $label): string
{
    if (function_exists('readline')) {
        $value = readline($label);
        return $value === false ? '' : $value;
    }
    fwrite(STDOUT, $label);
    $value = fgets(STDIN);
    return $value === false ? '' : rtrim($value, "\r\n");
}

function promptPassword(): string
{
    if (PHP_OS_FAMILY === 'Windows' && function_exists('shell_exec')) {
        $command = 'powershell.exe -NoProfile -Command "$secure = Read-Host \"Senha\" -AsSecureString; '
            . '$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure); '
            . 'try { [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr) } '
            . 'finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }"';
        $password = shell_exec($command);
        if (is_string($password)) return rtrim($password, "\r\n");
    }

    if (PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec')) {
        $mode = shell_exec('stty -g 2>/dev/null');
        if (is_string($mode) && trim($mode) !== '') {
            fwrite(STDOUT, 'Senha: ');
            shell_exec('stty -echo');
            $password = fgets(STDIN);
            shell_exec('stty ' . escapeshellarg(trim($mode)));
            fwrite(STDOUT, PHP_EOL);
            return $password === false ? '' : rtrim($password, "\r\n");
        }
    }

    fwrite(STDERR, "Aviso: este terminal nao permite ocultar a digitacao da senha.\n");
    return prompt('Senha: ');
}

$options = getopt('', ['name:', 'email:', 'password:']);
$name = trim(is_string($options['name'] ?? null) ? $options['name'] : prompt('Nome: '));
$email = mb_strtolower(trim(is_string($options['email'] ?? null) ? $options['email'] : prompt('E-mail: ')));
$password = is_string($options['password'] ?? null) ? $options['password'] : promptPassword();

if ($name === '') {
    fwrite(STDERR, "Erro: o nome e obrigatorio.\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Erro: informe um e-mail valido.\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Erro: a senha deve ter no minimo 8 caracteres.\n");
    exit(1);
}

try {
    $pdo = Database::connection();

    $statement = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
    $statement->execute(['name' => 'Administrador']);
    $roleId = $statement->fetchColumn();
    if ($roleId === false) {
        throw new RuntimeException('O perfil "Administrador" nao foi encontrado. Importe database/schema.sql antes de continuar.');
    }

    $statement = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => $email]);
    if ($statement->fetchColumn() !== false) {
        throw new RuntimeException('Ja existe um usuario cadastrado com esse e-mail.');
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if ($passwordHash === false) throw new RuntimeException('Nao foi possivel gerar o hash da senha.');

    $statement = $pdo->prepare(
        'INSERT INTO users (role_id, name, email, password_hash, status)
         VALUES (:role_id, :name, :email, :password_hash, :status)'
    );
    $statement->execute([
        'role_id' => $roleId,
        'name' => $name,
        'email' => $email,
        'password_hash' => $passwordHash,
        'status' => 'active',
    ]);

    unset($password, $passwordHash);
    fwrite(STDOUT, "Administrador criado com sucesso para o e-mail {$email}.\n");
} catch (PDOException $exception) {
    $message = $exception->getCode() === '23000'
        ? 'Ja existe um usuario cadastrado com esse e-mail.'
        : 'Erro de banco de dados. Verifique a configuracao e tente novamente.';
    fwrite(STDERR, "Erro: {$message}\n");
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Erro: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
