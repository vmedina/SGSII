<?php
declare(strict_types=1);

require __DIR__ . '/../src/App.php';
require __DIR__ . '/../src/Security.php';

$app = App::connect(':memory:');
$app->db()->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$app->db()->exec(
    "INSERT INTO users (username, first_name, last_name, password_hash, role) VALUES
    ('admin_test', 'Admin', 'Test', 'hash', 'admin'),
    ('user_test', 'User', 'Test', 'hash', 'user')"
);

$_SESSION = [
    'csrf' => 'known-token',
    'user' => ['id' => 1, 'role' => 'admin', 'name' => 'Antiguo'],
];
revalidate_session_user($app);
assertTrue(is_admin(), 'El administrador activo debe conservar su acceso.');
assertSame('Admin', current_user()['name'] ?? '', 'El nombre debe refrescarse desde la base.');

$app->execute("UPDATE users SET role='user' WHERE id=1");
revalidate_session_user($app);
assertTrue(!is_admin(), 'El rol degradado debe aplicarse en la siguiente petición.');
assertSame('user', current_user()['role'] ?? '', 'La sesión debe reflejar el rol vigente.');

$_SESSION['user'] = ['id' => 2, 'role' => 'user', 'name' => 'User'];
$app->execute('UPDATE users SET active=0 WHERE id=2');
revalidate_session_user($app);
assertTrue(current_user() === null, 'Un usuario desactivado debe perder la sesión.');
assertTrue(!isset($_SESSION['csrf']), 'Al invalidar la sesión también debe eliminarse CSRF.');

$_SESSION['user'] = ['id' => 999, 'role' => 'admin', 'name' => 'Inexistente'];
revalidate_session_user($app);
assertTrue(current_user() === null, 'Un usuario inexistente debe perder la sesión.');

assertAdminGate('admin', true);
assertAdminGate('user', false);
assertAdminGate('missing', false);

echo "PASS: revalidación de sesión y autorización\n";

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("FAIL: {$message}");
    }
}

function assertSame(string $expected, string $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            "FAIL: {$message} Esperado: {$expected}; obtenido: {$actual}"
        );
    }
}

function assertAdminGate(string $case, bool $accepted): void
{
    $process = proc_open(
        [PHP_BINARY, __DIR__ . '/fixtures/authorization_probe.php', $case],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) throw new RuntimeException('No se pudo probar la puerta ADMIN.');
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    $wasAccepted = $status === 0 && str_contains($output, 'ACCEPTED');
    assertTrue(
        $wasAccepted === $accepted,
        "La puerta ADMIN produjo un resultado incorrecto para {$case}: {$output}"
    );
}
