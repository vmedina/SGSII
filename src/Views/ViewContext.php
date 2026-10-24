<?php
declare(strict_types=1);

namespace Sgsii\Views;

final class ViewContext
{
    public function __construct(private array $values = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function all(): array
    {
        return $this->values;
    }

    public function escape(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}