<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $get,
        private readonly array $post,
        private readonly array $routeParams = []
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = (string)parse_url($uri, PHP_URL_PATH);
        $path = $path === '' ? '/' : $path;

        return new self($method, $path, $_GET, $_POST);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $v = $this->get[$key] ?? $default;
        return is_string($v) ? $v : $default;
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $v = $this->post[$key] ?? $default;
        return is_string($v) ? $v : $default;
    }

    public function all(): array
    {
        return $this->post;
    }

    public function route(string $key, ?string $default = null): ?string
    {
        $value = $this->routeParams[$key] ?? $default;
        return is_scalar($value) ? (string)$value : $default;
    }

    public function withRouteParams(array $routeParams): self
    {
        return new self($this->method, $this->path, $this->get, $this->post, $routeParams);
    }
}

