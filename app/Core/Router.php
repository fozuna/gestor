<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    private function __construct(private readonly Request $request)
    {
    }

    public static function fromGlobals(): self
    {
        return new self(Request::fromGlobals());
    }

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(): void
    {
        $method = $this->request->method();
        $path = $this->request->path();

        [$handler, $params] = $this->match($method, $path);
        if ($handler === null) {
            Response::html('404', 404);
            return;
        }

        $handler($this->request->withRouteParams($params));
    }

    private function match(string $method, string $path): array
    {
        $routes = $this->routes[$method] ?? [];
        if (isset($routes[$path])) {
            return [$routes[$path], []];
        }

        foreach ($routes as $routePath => $handler) {
            $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $routePath);
            if ($pattern === null || str_contains($routePath, '{') === false) {
                continue;
            }

            if (preg_match('#^' . $pattern . '$#', $path, $matches) !== 1) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            return [$handler, $params];
        }

        return [null, []];
    }
}

