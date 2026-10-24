<?php
declare(strict_types=1);

require __DIR__ . '/../src/Services/AuthenticationService.php';
require __DIR__ . '/../src/Repositories/UserRepository.php';

use Sgsii\Repositories\UserRepository;
use Sgsii\Services\AuthenticationService;

$database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$insert = $database->prepare(
    'INSERT INTO users (username,first_name,last_name,password_hash,role,active) VALUES (?,?,?,?,?,?)'
);
$hash = password_hash('password123', PASSWORD_DEFAULT);
$insert->execute(['active', 'Ana', 'Activa', $hash, 'user', 1]);
$insert->execute(['inactive', 'Ina', 'Inactiva', $hash, 'user', 0]);
$service = new AuthenticationService(new UserRepository($database));

$user = $service->login('active', 'password123');
assertSame('active', $user['username'], 'El servicio rechazó credenciales válidas.');
assertSame(null, $service->login('inactive', 'password123'), 'El servicio aceptó un usuario inactivo.');
assertSame(null, $service->login('active', 'incorrecta'), 'El servicio aceptó una contraseña inválida.');
assertSame('Ana', $service->activeSessionUser(1)['first_name'], 'No se revalidó el usuario activo.');
assertSame(null, $service->activeSessionUser(2), 'Se revalidó un usuario inactivo.');

$service->changePassword(1, 'password123', 'newpassword123');
assertTrue($service->login('active', 'newpassword123') !== null, 'No se guardó la nueva contraseña.');
expectInvalid(static fn() => $service->changePassword(1, 'wrong', 'another123'));
expectInvalid(static fn() => $service->changePassword(1, 'newpassword123', 'short'));

echo "PASS: authentication service\n";

function expectInvalid(callable $operation): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('FAIL: se aceptó una operación de autenticación inválida.');
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException("FAIL: {$message}");
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) throw new RuntimeException("FAIL: {$message}");
}