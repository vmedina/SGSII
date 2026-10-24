<?php
declare(strict_types=1);

require __DIR__ . '/../src/Security.php';

$validation = new InvalidArgumentException('El campo es inválido.');
assertSame(
    'El campo es inválido.',
    user_error_message($validation),
    'Los errores de validación deben conservar un mensaje útil.'
);

$secret = 'SELECT password_hash FROM users C:\secret\database.sqlite';
$unexpected = new RuntimeException($secret, 77);
$publicMessage = user_error_message($unexpected);
assertSame(
    'Ocurrió un error interno. Intente nuevamente.',
    $publicMessage,
    'Los errores internos deben mostrar un mensaje genérico.'
);
assertNotContains($secret, $publicMessage, 'El mensaje público filtró información interna.');

$pdoMessage = user_error_message(new PDOException('SQLSTATE[HY000] tabla secreta'));
assertNotContains('SQLSTATE', $pdoMessage, 'Un error PDO filtró la consulta o el driver.');

$logPath = sys_get_temp_dir() . '/sgsii-error-' . bin2hex(random_bytes(4)) . '.log';
try {
    log_internal_error($unexpected, $logPath);
    $log = file_get_contents($logPath);
    assertContains('RuntimeException', $log, 'El log no identifica el tipo de error.');
    assertContains('code=77', $log, 'El log no conserva el código de error.');
    assertNotContains($secret, $log, 'El log almacenó el mensaje potencialmente sensible.');
    assertNotContains(__DIR__, $log, 'El log almacenó una ruta completa.');
} finally {
    if (file_exists($logPath)) unlink($logPath);
}

echo "PASS: manejo seguro de errores\n";

function assertSame(string $expected, string $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            "FAIL: {$message} Esperado: {$expected}; obtenido: {$actual}"
        );
    }
}

function assertContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException("FAIL: {$message}");
    }
}

function assertNotContains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException("FAIL: {$message}");
    }
}
