<?php
declare(strict_types=1);

require __DIR__ . '/../src/App.php';

$app = App::connect(':memory:', new DateTimeImmutable('2030-01-01 08:00:00'));
$db = $app->db();
$db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$db->exec(
    "INSERT INTO users (username,first_name,last_name,password_hash,role)
    VALUES ('teacher','Ana','Docente','hash','user')"
);
$db->exec(
    "INSERT INTO rooms (name,room_number,location,equipment_count)
    VALUES ('Laboratorio','L1','Piso 1',20)"
);
$db->exec(
    "INSERT INTO supplies (name,asset_code,category,quantity,status)
    VALUES ('Notebook','NB-1','Equipo',3,'operational')"
);

$app->createReservation(1, reservationInput('room'));
$app->createReservation(1, reservationInput('supply'));

$roomReservation = $app->one('SELECT room_id,supply_id,quantity FROM reservations WHERE id=1');
assertSame(1, (int)$roomReservation['room_id'], 'La reserva de sala no usó room_id.');
assertTrue($roomReservation['supply_id'] === null, 'La reserva de sala escribió supply_id.');
assertSame(1, (int)$roomReservation['quantity'], 'La reserva de sala no conservó cantidad uno.');

$supplyReservation = $app->one('SELECT room_id,supply_id,quantity FROM reservations WHERE id=2');
assertTrue($supplyReservation['room_id'] === null, 'La reserva de insumo escribió room_id.');
assertSame(1, (int)$supplyReservation['supply_id'], 'La reserva de insumo no usó supply_id.');
assertSame(2, (int)$supplyReservation['quantity'], 'La reserva de insumo perdió su cantidad.');

expectInvalidMapping(
    static fn() => $app->available('invalid', 1, '2030-01-02', '09:00', '10:00', 1),
    'Un tipo inválido llegó a formar un nombre de columna.'
);
expectInvalidMapping(
    static fn() => $app->createReservation(1, [...reservationInput('room'), 'resource_type' => 'rooms; DROP TABLE users;--']),
    'Una entrada externa pudo formar un nombre de tabla.'
);
assertSame(1, (int)$app->one('SELECT COUNT(*) AS total FROM users')['total'], 'El tipo manipulado alteró la base.');

echo "PASS: mapeo explícito de recursos\n";

function reservationInput(string $type): array
{
    return [
        'resource_type' => $type,
        'resource_id' => 1,
        'reservation_date' => '2030-01-02',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'quantity' => 2,
    ];
}

function expectInvalidMapping(callable $operation, string $message): void
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
