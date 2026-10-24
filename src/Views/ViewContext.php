<?php
declare(strict_types=1);

namespace Sgsii\Views;

final class ViewContext
{
    /**
     * Proporciona a una vista sus datos y una salida HTML segura.
     */
    public function __construct(private array $values = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        // Devuelve el valor solicitado o un valor alternativo si no existe.
        return $this->values[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        // array_key_exists distingue una clave existente cuyo valor sea null.
        return array_key_exists($key, $this->values);
    }

    public function all(): array
    {
        // Útil cuando una vista necesita recorrer todos los datos recibidos.
        return $this->values;
    }

    public function escape(mixed $value): string
    {
        // Escapa comillas y caracteres especiales para evitar HTML no confiable.
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
