<?php
declare(strict_types=1);

require __DIR__ . '/../src/App.php';

$app = App::connect(':memory:');
$db = $app->db();
$db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));

$app->saveUser(userData('ana'));
$app->saveRoom(roomData('Laboratorio', 'L1', 0));
$app->saveSupply(supplyData('Notebook', 'NB-1', 0));
assertSame(1, rowCount($db, 'users'), 'No se creó el usuario válido.');
assertSame(1, rowCount($db, 'rooms'), 'No se creó la sala válida.');
assertSame(1, rowCount($db, 'supplies'), 'No se creó el insumo válido.');

expectInvalidCrud(static fn() => $app->saveUser(userData('ab')), 'Se aceptó un usuario demasiado corto.');
expectInvalidCrud(static fn() => $app->saveUser([...userData('beto'), 'first_name' => '']), 'Se aceptó un nombre vacío.');
expectInvalidCrud(static fn() => $app->saveUser([...userData('beto'), 'password' => '1234567']), 'Se aceptó una contraseña corta.');
expectInvalidCrud(static fn() => $app->saveRoom([...roomData('Sala 2', 'L2', 0), 'location' => '']), 'Se aceptó una ubicación vacía.');
expectInvalidCrud(static fn() => $app->saveRoom(roomData('Sala 2', 'L2', -1)), 'Se aceptó una cantidad de equipos negativa.');
expectInvalidCrud(static fn() => $app->saveRoom(roomData('Sala 2', 'L2', 1000)), 'Se superó el límite de equipos.');
expectInvalidCrud(static fn() => $app->saveSupply(supplyData('Mouse', 'MS-1', -1)), 'Se aceptó stock negativo.');
expectInvalidCrud(static fn() => $app->saveSupply(supplyData('Mouse', 'MS-1', 10000)), 'Se superó el límite de stock.');

expectDuplicate(static fn() => $app->saveUser(userData('ana')), 'Se aceptó un usuario duplicado.');
expectDuplicate(static fn() => $app->saveRoom(roomData('Laboratorio', 'L2', 1)), 'Se aceptó un nombre de sala duplicado.');
expectDuplicate(static fn() => $app->saveSupply(supplyData('Mouse', 'NB-1', 1)), 'Se aceptó un código de insumo duplicado.');
assertSame(1, rowCount($db, 'users'), 'Un rechazo de duplicado alteró usuarios.');
assertSame(1, rowCount($db, 'rooms'), 'Un rechazo de duplicado alteró salas.');
assertSame(1, rowCount($db, 'supplies'), 'Un rechazo de duplicado alteró insumos.');

$app->saveUser(userData('beto'), 999);
$app->saveRoom(roomData('Sala 2', 'L2', 1), 999);
expectInvalidCrud(
    static fn() => $app->saveSupply(supplyData('Mouse', 'MS-1', 1), 999),
    'Se actualizó un insumo inexistente.'
);
assertSame(1, rowCount($db, 'users'), 'Actualizar un ID de usuario inexistente creó una fila.');
assertSame(1, rowCount($db, 'rooms'), 'Actualizar un ID de sala inexistente creó una fila.');

$app->deactivate('users', 1);
$app->deactivate('rooms', 1);
$app->deactivate('supplies', 1);
assertSame(0, activeValue($db, 'users'), 'No se desactivó el usuario.');
assertSame(0, activeValue($db, 'rooms'), 'No se desactivó la sala.');
assertSame(0, activeValue($db, 'supplies'), 'No se desactivó el insumo.');
expectInvalidCrud(static fn() => $app->deactivate('invalid_table', 1), 'Se aceptó una entidad no permitida.');

echo "PASS: validación CRUD\n";

function userData(string $username): array
{
    return [
        'username' => $username,
        'first_name' => 'Nombre',
        'last_name' => 'Apellido',
        'subject' => '',
        'role' => 'user',
        'password' => '12345678',
    ];
}

function roomData(string $name, string $number, int $equipment): array
{
    return [
        'name' => $name,
        'room_number' => $number,
        'location' => 'Piso 1',
        'equipment_count' => $equipment,
    ];
}

function supplyData(string $name, string $code, int $quantity): array
{
    return [
        'name' => $name,
        'asset_code' => $code,
        'category' => 'Equipo',
        'description' => '',
        'quantity' => $quantity,
        'status' => 'operational',
    ];
}

function rowCount(PDO $db, string $table): int
{
    return (int)$db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}

function activeValue(PDO $db, string $table): int
{
    return (int)$db->query("SELECT active FROM {$table} WHERE id=1")->fetchColumn();
}

function expectInvalidCrud(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException("FAIL: {$message}");
}

function expectDuplicate(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (PDOException) {
        return;
    }
    throw new RuntimeException("FAIL: {$message}");
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
