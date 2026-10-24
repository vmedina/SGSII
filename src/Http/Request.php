<?php
declare(strict_types=1);

namespace Sgsii\Http;

final class Request
{
    /**
     * Agrupa los datos de una petición HTTP para que el resto de la aplicación
     * no tenga que leer directamente las variables globales de PHP.
     */
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
        // Copia método, consulta, formulario y sesión en un objeto inmutable
        // desde el punto de vista del consumidor de la petición.
        return new self(
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $_GET,
            $_POST,
            $_SESSION ?? []
        );
    }

    public function method(): string
    {
        // El constructor normaliza el método a mayúsculas.
        return $this->method;
    }

    public function isPost(): bool
    {
        // Se usa como atajo para las acciones que solo aceptan formularios.
        return $this->method === 'POST';
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        // Sin clave devuelve toda la consulta; con clave devuelve un valor o
        // el valor predeterminado cuando el parámetro no fue enviado.
        return $key === null ? $this->query : ($this->query[$key] ?? $default);
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        // Aplica la misma regla de acceso que query(), pero sobre $_POST.
        return $key === null ? $this->post : ($this->post[$key] ?? $default);
    }

    public function session(?string $key = null, mixed $default = null): mixed
    {
        // Permite consultar datos de sesión sin exponer el arreglo interno.
        return $key === null ? $this->session : ($this->session[$key] ?? $default);
    }
}
