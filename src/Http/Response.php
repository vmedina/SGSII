<?php
declare(strict_types=1);

namespace Sgsii\Http;

final class Response
{
    /**
     * Representa la respuesta HTTP antes de enviarla al navegador: contenido,
     * código de estado y encabezados.
     */
    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = []
    ) {
        if ($status < 100 || $status > 599) {
            throw new \InvalidArgumentException('Código HTTP inválido.');
        }
    }

    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        // Define el tipo de contenido HTML y conserva encabezados adicionales.
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8', ...$headers]);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        // Crea una respuesta sin cuerpo que indica al navegador una nueva URL.
        return new self('', $status, ['Location' => $location]);
    }

    public function body(): string
    {
        return $this->body;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): never
    {
        // Los encabezados solo pueden enviarse antes de producir el cuerpo.
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}", true);
            }
        }

        echo $this->body;
        exit;
    }
}
