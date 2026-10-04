<?php

$env = static fn (string $name, string $fallback): string => (($value = getenv($name)) !== false && $value !== '') ? $value : $fallback;

return [
    'host' => $env('DB_HOST', 'localhost'),
    'port' => $env('DB_PORT', '3306'),
    'database' => $env('DB_DATABASE', 'bloodhub'),
    'username' => $env('DB_USERNAME', 'root'),
    'password' => $env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
];
