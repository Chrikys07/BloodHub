<?php
declare(strict_types=1);

$env = static fn (string $name, string $fallback): string => (($value = getenv($name)) !== false && $value !== '') ? $value : $fallback;
$boolean = static fn (string $value): bool => filter_var($value, FILTER_VALIDATE_BOOL);

return [
    'url' => rtrim($env('APP_URL', 'http://localhost/Blood%20Hub/public'), '/'),
    'environment' => $env('APP_ENV', 'local'),
    'debug' => $boolean($env('APP_DEBUG', 'true')),
];
