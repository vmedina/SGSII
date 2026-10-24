<?php
declare(strict_types=1);

require __DIR__ . '/../src/App.php';

$app = App::connect(':memory:');
$db = $app->db();
$db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$insert = $db->prepare(
    "INSERT INTO users (username,first_name,last_name,password_hash,role,active)
    VALUES (?,?,?,?, 'user',?)"
);
$insert->execute(['active_user', 'Ana', 'Activa', password_hash('password123', PASSWORD_DEFAULT), 1]);
$insert->execute(['inactive_user', 'Ina', 'Inactiva', password_hash('password123', PASSWORD_DEFAULT), 0]);

$authenticated = $app->login('active_user', 'password123');
assertTrue($authenticated !== null, 'Las credenciales válidas fueron rechazadas.');
assertSame('active_user', $authenticated['username'], 'El login devolvió otra cuenta.');
assertTrue($app->login('active_user', 'incorrecta') === null, 'Una contraseña incorrecta fue aceptada.');
assertTrue($app->login("' OR 1=1 --", 'password123') === null, 'Una entrada SQL alteró el login.');
assertTrue($app->login('inactive_user', 'password123') === null, 'Un usuario inactivo pudo iniciar sesión.');
assertTrue($app->login('missing_user', 'password123') === null, 'Un usuario inexistente pudo iniciar sesión.');

expectInvalidAuthentication(
    static fn() => $app->changePassword(1, 'incorrecta', 'newpassword123'),
    'Se cambió la contraseña sin conocer la actual.'
);
expectInvalidAuthentication(
    static fn() => $app->changePassword(1, 'password123', 'short'),
    'Se aceptó una contraseña nueva menor a ocho caracteres.'
);
$app->changePassword(1, 'password123', 'newpassword123');
assertTrue($app->login('active_user', 'password123') === null, 'La contraseña anterior siguió siendo válida.');
assertTrue($app->login('active_user', 'newpassword123') !== null, 'La contraseña nueva no permitió iniciar sesión.');

echo "PASS: autenticación y cambio de contraseña\n";

function expectInvalidAuthentication(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException("FAIL: {$message}");
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException("FAIL: {$message}");
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            'FAIL: ' . $message . ' Esperado: ' . json_encode($expected)
            . '; obtenido: ' . json_encode($actual)
        );
    }
}
