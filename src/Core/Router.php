<?php
namespace BloodHub\Core;

final class Router
{
    private array $routes = [];
    public function get(string $path, callable $handler): void { $this->routes['GET'][$path] = $handler; }
    public function post(string $path, callable $handler): void { $this->routes['POST'][$path] = $handler; }
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        if ($basePath && $basePath !== '/' && str_starts_with($uri, $basePath)) $uri = substr($uri, strlen($basePath));
        $uri = '/' . ltrim($uri, '/');
        $handler = $this->routes[$method][$uri] ?? null;
        if (!$handler) { http_response_code(404); echo 'Página não encontrada.'; return; }
        $handler();
    }
}
