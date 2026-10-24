<?php
declare(strict_types=1);

require __DIR__ . '/../src/Http/Response.php';

use Sgsii\Http\Response;

$html = Response::html('<h1>OK</h1>', 201, ['X-Test' => 'yes']);
assertSame('<h1>OK</h1>', $html->body(), 'La respuesta HTML no conservó el body.');
assertSame(201, $html->status(), 'La respuesta HTML no conservó el código.');
assertSame('text/html; charset=UTF-8', $html->headers()['Content-Type'], 'La respuesta HTML no declaró su contenido.');
assertSame('yes', $html->headers()['X-Test'], 'La respuesta HTML no conservó headers adicionales.');

$redirect = Response::redirect('?page=login');
assertSame('', $redirect->body(), 'La redirección no debe generar body.');
assertSame(302, $redirect->status(), 'La redirección no usó 302 por defecto.');
assertSame('?page=login', $redirect->headers()['Location'], 'La redirección no conservó el destino.');

expectInvalidStatus(99);
expectInvalidStatus(600);

echo "PASS: response HTTP\n";

function expectInvalidStatus(int $status): void
{
    try {
        new Response('', $status);
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException("FAIL: se aceptó el código HTTP {$status}.");
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException("FAIL: {$message}");
    }
}