<?php
declare(strict_types=1);

require __DIR__ . '/../src/App.php';
require __DIR__ . '/../src/Security.php';

$app = App::connect(':memory:');
$db = $app->db();
$db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$password = password_hash('password123', PASSWORD_DEFAULT);
$statement = $db->prepare(
    "INSERT INTO users (username,first_name,last_name,password_hash,role) VALUES (?,?,?,?, 'user')"
);
$statement->execute(['teacher', 'Ana', 'Docente', $password]);

assertTrue($app->login("teacher' OR 1=1 --", 'password123') === null, 'SQL injection alteró el login.');

$sqlPayload = "Laboratorio'); DROP TABLE users; --";
$app->saveRoom([
    'name' => $sqlPayload,
    'room_number' => 'L1',
    'location' => 'Piso 1',
    'equipment_count' => 1,
]);
assertSame($sqlPayload, $app->one('SELECT name FROM rooms WHERE id=1')['name'], 'El texto SQL no se guardó como dato literal.');
assertSame(1, (int)$app->one('SELECT COUNT(*) AS total FROM users')['total'], 'Una entrada SQL modificó la tabla de usuarios.');

$xssPayload = '<img src=x onerror=alert(1)>';
$app->incident(1, [
    'resource_type' => 'room',
    'resource_id' => 1,
    'description' => $xssPayload,
    'urgency' => 'low',
]);
assertSame($xssPayload, $app->one('SELECT description FROM incidents WHERE id=1')['description'], 'El contenido XSS no se preservó como dato.');
$encoded = e($xssPayload);
assertTrue(!str_contains($encoded, '<img'), 'La salida permitió una etiqueta HTML ejecutable.');
assertSame('&lt;img src=x onerror=alert(1)&gt;', $encoded, 'La salida XSS no quedó completamente codificada.');

echo "PASS: entradas SQL y XSS\n";

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
