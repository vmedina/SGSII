<?php
declare(strict_types=1);

require __DIR__ . '/../src/Security.php';

$security = file_get_contents(__DIR__ . '/../src/Security.php');
$entryPoint = file_get_contents(__DIR__ . '/../public/index.php');

assertContains('http_response_code(403)', $security, 'Las solicitudes inválidas no conservan el código 403.');
assertContains('http_response_code(500)', $security, 'Los errores internos no conservan el código 500.');
assertContains("header('Location: ?page=login')", $security, 'La falta de autenticación no redirige a login.');
assertContains('header(\'Location: ?page=\' . rawurlencode($page))', $security, 'La navegación no conserva la redirección por página.');
assertContains('flash(\'error\', user_error_message($error))', $entryPoint, 'Los errores POST no se conservan como flash.');
assertContains('go($page)', $entryPoint, 'Los errores POST no regresan a la página de origen.');

$_SESSION = [];
flash('error', 'Mensaje de prueba.');
assertSame(['error', 'Mensaje de prueba.'], pull_flash(), 'El flash no se recuperó correctamente.');
assertSame(null, pull_flash(), 'El flash recuperado no se consumió.');

assertSame(
    'Error controlado.',
    user_error_message(new InvalidArgumentException('Error controlado.')),
    'La validación no conserva su mensaje público.'
);
assertSame(
    'Ocurrió un error interno. Intente nuevamente.',
    user_error_message(new RuntimeException('detalle interno')),
    'El error interno no conserva su mensaje genérico.'
);
assertSame(
    'No se pudo guardar: hay datos duplicados o relacionados.',
    user_error_message(new PDOException('detalle SQL')),
    'El error PDO no conserva su mensaje genérico.'
);

echo "PASS: contrato HTTP de respuestas\n";

function assertContains(string $needle, string $content, string $message): void
{
    if (!str_contains($content, $needle)) {
        throw new RuntimeException("FAIL: {$message}");
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException("FAIL: {$message}");
    }
}