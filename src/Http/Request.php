<?php
declare(strict_types=1);

namespace Sgsii\Http;

final class Request
{
    public function __construct(
        private string $method,
        private array $query,
        private array $post,
        private array $session
    ) {
        $this->method = strtoupper($method);
    }

    public static function fromGlobals(): self
    {
        return new self(
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $_GET,
            $_POST,
            $_SESSION ?? []
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->query : ($this->query[$key] ?? $default);
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->post : ($this->post[$key] ?? $default);
    }

    public function session(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->session : ($this->session[$key] ?? $default);
    }
}