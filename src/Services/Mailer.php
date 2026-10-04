<?php
declare(strict_types=1);
namespace BloodHub\Services;

use RuntimeException;

final class Mailer
{
    private array $config;
    /** @var resource|null */
    private $socket = null;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? self::configuration();
    }

    public static function configuration(): array
    {
        $file = dirname(__DIR__, 2).'/config/mail.php';
        $local = is_file($file) ? (array) require $file : [];
        $value = static function (string $environment, string $key, mixed $default = '') use ($local): mixed {
            $fromEnvironment = getenv($environment);
            return $fromEnvironment !== false && $fromEnvironment !== '' ? $fromEnvironment : ($local[$key] ?? $default);
        };
        return [
            'host' => trim((string) $value('MAIL_HOST', 'host')),
            'port' => (int) $value('MAIL_PORT', 'port', 587),
            'username' => (string) $value('MAIL_USERNAME', 'username'),
            'password' => (string) $value('MAIL_PASSWORD', 'password'),
            'encryption' => strtolower(trim((string) $value('MAIL_ENCRYPTION', 'encryption', 'tls'))),
            'from_address' => trim((string) $value('MAIL_FROM_ADDRESS', 'from_address')),
            'from_name' => trim((string) $value('MAIL_FROM_NAME', 'from_name', 'BloodHub')),
        ];
    }

    public function send(string $recipient, string $subject, string $html, string $text): void
    {
        $this->validate($recipient);
        $host = $this->config['host'];
        $encryption = $this->config['encryption'];
        $target = ($encryption === 'ssl' ? 'ssl://' : '').$host.':'.$this->config['port'];
        $errorNumber = 0;
        $errorMessage = '';
        $this->socket = @stream_socket_client($target, $errorNumber, $errorMessage, 15, STREAM_CLIENT_CONNECT);
        if (!is_resource($this->socket)) {
            throw new RuntimeException("SMTP connection failed ({$errorNumber}): ".($errorMessage ?: 'connection refused'));
        }
        stream_set_timeout($this->socket, 15);
        try {
            $this->expect([220]);
            $this->command('EHLO '.self::hostname(), [250]);
            if ($encryption === 'tls') {
                $this->command('STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP TLS negotiation failed.');
                }
                $this->command('EHLO '.self::hostname(), [250]);
            }
            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode($this->config['username']), [334], true);
            $this->command(base64_encode($this->config['password']), [235], true, 'SMTP authentication failed.');
            $this->command('MAIL FROM:<'.$this->config['from_address'].'>', [250]);
            $this->command('RCPT TO:<'.$recipient.'>', [250, 251]);
            $this->command('DATA', [354]);
            $message = $this->message($recipient, $subject, $html, $text);
            fwrite($this->socket, preg_replace('/(?m)^\./', '..', $message)."\r\n.\r\n");
            $this->expect([250]);
            $this->command('QUIT', [221]);
        } finally {
            if (is_resource($this->socket)) fclose($this->socket);
            $this->socket = null;
        }
    }

    private function validate(string $recipient): void
    {
        foreach (['host', 'username', 'password', 'from_address'] as $key) {
            if (trim((string) ($this->config[$key] ?? '')) === '') {
                throw new RuntimeException('Configuração SMTP não disponível. Missing '.strtoupper('MAIL_'.$key));
            }
        }
        if (($this->config['port'] ?? 0) < 1 || !in_array($this->config['encryption'], ['tls', 'ssl', 'none'], true)) {
            throw new RuntimeException('Configuração SMTP não disponível. Invalid MAIL_PORT or MAIL_ENCRYPTION');
        }
        if (!filter_var($this->config['from_address'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid sender address.');
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid recipient: '.$recipient);
    }

    private function message(string $recipient, string $subject, string $html, string $text): string
    {
        $boundary = 'bloodhub_'.bin2hex(random_bytes(12));
        $name = str_replace(["\r", "\n"], '', $this->config['from_name'] ?: 'BloodHub');
        $encodedName = '=?UTF-8?B?'.base64_encode($name).'?=';
        $encodedSubject = '=?UTF-8?B?'.base64_encode(str_replace(["\r", "\n"], '', $subject)).'?=';
        $headers = [
            'Date: '.date(DATE_RFC2822), 'From: '.$encodedName.' <'.$this->config['from_address'].'>',
            'To: <'.$recipient.'>', 'Subject: '.$encodedSubject, 'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="'.$boundary.'"',
        ];
        return implode("\r\n", $headers)."\r\n\r\n".
            '--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".
            chunk_split(base64_encode($text))."\r\n--".$boundary."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".
            chunk_split(base64_encode($html))."\r\n--".$boundary.'--';
    }

    private function command(string $command, array $expected, bool $sensitive = false, ?string $customError = null): void
    {
        fwrite($this->socket, $command."\r\n");
        $this->expect($expected, $customError, $sensitive ? '[credentials hidden]' : $command);
    }

    private function expect(array $expected, ?string $customError = null, string $context = ''): void
    {
        $response = '';
        do {
            $line = fgets($this->socket, 515);
            if ($line === false) {
                $meta = stream_get_meta_data($this->socket);
                throw new RuntimeException(($meta['timed_out'] ?? false) ? 'SMTP connection timed out.' : 'SMTP connection closed unexpectedly.');
            }
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            $detail = trim(preg_replace('/[\r\n]+/', ' ', $response));
            throw new RuntimeException($customError ? $customError.' '.$detail : 'SMTP error'.($context ? " after {$context}" : '').": {$detail}");
        }
    }

    private static function hostname(): string
    {
        return preg_replace('/[^a-z0-9.-]/i', '', gethostname() ?: 'localhost') ?: 'localhost';
    }
}
