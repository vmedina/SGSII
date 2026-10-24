<?php
declare(strict_types=1);

require __DIR__ . '/../src/Http/Request.php';
require __DIR__ . '/../src/Http/Router.php';

use Sgsii\Http\Request;
use Sgsii\Http\Router;

$router = new Router();
$guestRequest = new Request('GET', [], [], []);
$userRequest = new Request('GET', [], [], ['user' => ['id' => 7, 'role' => 'user']]);
$adminRequest = new Request('GET', ['page' => 'reports'], [], ['user' => ['id' => 3, 'role' => 'admin']]);

assertSame('login', $router->page($guestRequest, null), 'El invitado no fue enviado a login.');
assertSame('dashboard', $router->page($userRequest, $userRequest->session('user')), 'El usuario no fue enviado al dashboard.');
assertSame('reports', $router->page($adminRequest, $adminRequest->session('user')), 'La página query no fue conservada.');
assertSame('custom', $router->page(new Request('GET', ['page' => 'custom'], [], []), null), 'La compatibilidad con page desconocida cambió.');

assertSame(11, count($router->pages()), 'Cambió el número de páginas públicas conocidas.');
assertSame(5, count($router->adminPages()), 'Cambió el número de páginas administrativas.');
assertTrue($router->isKnownPage('reservations'), 'No se reconoce una página existente.');
assertTrue(!$router->isKnownPage('custom'), 'Una página desconocida fue marcada como conocida.');
assertTrue($router->requiresLogin('dashboard'), 'Dashboard dejó de requerir login.');
assertTrue(!$router->requiresLogin('login'), 'Login requiere autenticación.');
assertTrue($router->requiresAdmin('reports'), 'Reports dejó de requerir admin.');
assertTrue(!$router->requiresAdmin('reservations'), 'Reservas requieren admin indebidamente.');

echo "PASS: router HTTP\n";

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