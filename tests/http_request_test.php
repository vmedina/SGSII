<?php
declare(strict_types=1);

require __DIR__ . '/../src/Http/Request.php';

use Sgsii\Http\Request;

$request = new Request(
    'post',
    ['page' => 'reservations'],
    ['action' => 'reservation'],
    ['user' => ['id' => 7]]
);

assertSame('POST', $request->method(), 'El método HTTP no se normalizó.');
assertTrue($request->isPost(), 'La petición POST no fue identificada.');
assertSame('reservations', $request->query('page'), 'La query no se leyó por clave.');
assertSame('fallback', $request->query('missing', 'fallback'), 'El valor por defecto de query falló.');
assertSame(['action' => 'reservation'], $request->post(), 'El payload POST completo no se conservó.');
assertSame('reservation', $request->post('action'), 'El payload POST no se leyó por clave.');
assertSame(['id' => 7], $request->session('user'), 'La sesión no se leyó por clave.');
assertSame([], $request->session('missing', []), 'El valor por defecto de sesión falló.');

$_SERVER['REQUEST_METHOD'] = 'get';
$_GET = ['page' => 'login'];
$_POST = [];
$_SESSION = [];
$globalRequest = Request::fromGlobals();
assertSame('GET', $globalRequest->method(), 'fromGlobals no tomó el método HTTP.');
assertSame('login', $globalRequest->query('page'), 'fromGlobals no tomó la query.');
assertTrue(!$globalRequest->isPost(), 'Una petición GET fue identificada como POST.');

echo "PASS: request HTTP\n";

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("FAIL: {$message}");
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException("FAIL: {$message}");
    }
}