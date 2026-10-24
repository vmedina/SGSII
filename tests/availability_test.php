<?php
declare(strict_types=1);

require __DIR__ . '/../src/App.php';

testRoomAvailabilityMatchesReservationRules();
testSupplyAvailabilityUsesStockAndLoans();
testMaintenanceAndInactiveStates();
testAvailabilityInputValidation();

echo "PASS: consulta real de disponibilidad\n";

function testRoomAvailabilityMatchesReservationRules(): void
{
    $app = createAvailabilityApp();
    $db = $app->db();

    $available = $app->checkAvailability(availabilityData('room', 1));
    assertSame(true, $available['available'], 'La sala libre no figura disponible.');
    assertSame('available', $available['status'], 'La sala libre tiene un estado incorrecto.');
    assertSame('Laboratorio', $available['resource_name'], 'El resultado no identifica la sala.');

    $app->createReservation(1, reservationDataFromAvailability('room', 1));
    $reserved = $app->checkAvailability(availabilityData('room', 1));
    assertSame(false, $reserved['available'], 'La sala reservada figura disponible.');
    assertSame('reserved', $reserved['status'], 'La sala ocupada no se identifica como reservada.');
    assertSame(
        $app->available('room', 1, '2030-05-16', '09:00', '10:00', 1),
        $reserved['available'],
        'La consulta y la validación interna de reserva no coinciden.'
    );

    $consecutive = $app->checkAvailability(
        availabilityData('room', 1, '2030-05-16', '10:00', '11:00')
    );
    assertSame(true, $consecutive['available'], 'Una reserva consecutiva figura ocupada.');

    $db->exec("UPDATE reservations SET status='cancelled'");
    $released = $app->checkAvailability(availabilityData('room', 1));
    assertSame(true, $released['available'], 'La reserva cancelada continúa bloqueando la consulta.');
}

function testSupplyAvailabilityUsesStockAndLoans(): void
{
    $app = createAvailabilityApp();
    $db = $app->db();

    $available = $app->checkAvailability(availabilityData('supply', 1, quantity: 2));
    assertSame(true, $available['available'], 'El insumo con stock suficiente figura ocupado.');
    assertSame('available', $available['status'], 'El stock suficiente tiene un estado incorrecto.');

    $db->exec(
        "INSERT INTO loans (supply_id, user_id, quantity, notes)
        VALUES (1, 1, 1, 'Préstamo abierto')"
    );
    $db->exec(
        "INSERT INTO reservations (user_id, supply_id, quantity, reservation_date, start_time, end_time)
        VALUES (1, 1, 1, '2030-05-16', '09:00', '10:00')"
    );

    $exact = $app->checkAvailability(availabilityData('supply', 1, quantity: 1));
    assertSame(true, $exact['available'], 'El límite exacto de stock fue rechazado.');

    $insufficient = $app->checkAvailability(availabilityData('supply', 1, quantity: 2));
    assertSame(false, $insufficient['available'], 'Se informó stock suficiente por encima del límite.');
    assertSame('insufficient_stock', $insufficient['status'], 'No se distinguió el stock insuficiente.');
    assertSame(
        $app->available('supply', 1, '2030-05-16', '09:00', '10:00', 2),
        $insufficient['available'],
        'La consulta de stock y la validación interna no coinciden.'
    );

    $db->exec("UPDATE loans SET returned_at=CURRENT_TIMESTAMP");
    $afterReturn = $app->checkAvailability(availabilityData('supply', 1, quantity: 2));
    assertSame(true, $afterReturn['available'], 'La devolución no liberó stock en la consulta.');
}

function testMaintenanceAndInactiveStates(): void
{
    $app = createAvailabilityApp();
    $db = $app->db();

    $repair = $app->checkAvailability(availabilityData('supply', 2));
    assertSame(false, $repair['available'], 'Un insumo en reparación figura disponible.');
    assertSame('maintenance', $repair['status'], 'No se distinguió el mantenimiento.');
    assertSame('En mantenimiento', $repair['title'], 'El texto de mantenimiento es ambiguo.');

    $outOfService = $app->checkAvailability(availabilityData('supply', 3));
    assertSame(false, $outOfService['available'], 'Un insumo fuera de servicio figura disponible.');
    assertSame('maintenance', $outOfService['status'], 'Fuera de servicio no usa estado no disponible.');
    assertSame('Fuera de servicio', $outOfService['title'], 'No se informa fuera de servicio con texto.');

    $db->exec('UPDATE rooms SET active=0 WHERE id=1');
    $inactive = $app->checkAvailability(availabilityData('room', 1));
    assertSame(false, $inactive['available'], 'Una sala inactiva figura disponible.');
    assertSame('maintenance', $inactive['status'], 'La sala inactiva no se identifica como no disponible.');
}

function testAvailabilityInputValidation(): void
{
    $app = createAvailabilityApp();

    expectInvalidAvailability(
        static fn() => $app->checkAvailability(
            availabilityData('room', 1, start: '10:00', end: '09:00')
        ),
        'Se aceptó un rango invertido.'
    );
    expectInvalidAvailability(
        static fn() => $app->checkAvailability(
            availabilityData('supply', 1, quantity: 0)
        ),
        'Se aceptó cantidad cero para un insumo.'
    );
    expectInvalidAvailability(
        static fn() => $app->checkAvailability(
            availabilityData('room', 1, date: '2030-05-14')
        ),
        'La consulta aceptó una fecha pasada que no podría reservarse.'
    );
    expectInvalidAvailability(
        static fn() => $app->checkAvailability(
            availabilityData('room', 999)
        ),
        'La consulta aceptó un recurso inexistente.'
    );
}

function createAvailabilityApp(): App
{
    $clock = new DateTimeImmutable('2030-05-15 12:00:00', new DateTimeZone('UTC'));
    $app = App::connect(':memory:', $clock);
    $db = $app->db();
    $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    $db->exec(
        "INSERT INTO users (username, first_name, last_name, password_hash, role)
        VALUES ('teacher', 'Ana', 'Docente', 'hash', 'user')"
    );
    $db->exec(
        "INSERT INTO rooms (name, room_number, location, equipment_count)
        VALUES ('Laboratorio', 'L1', 'Piso 1', 20)"
    );
    $db->exec(
        "INSERT INTO supplies (name, asset_code, category, quantity, status) VALUES
        ('Notebook', 'NB-1', 'Informática', 3, 'operational'),
        ('Proyector', 'PR-1', 'Audiovisual', 1, 'repair'),
        ('Impresora', 'IM-1', 'Periféricos', 1, 'out_of_service')"
    );
    return $app;
}

function availabilityData(
    string $type,
    int $id,
    string $date = '2030-05-16',
    string $start = '09:00',
    string $end = '10:00',
    int $quantity = 1
): array {
    return [
        'resource_type' => $type,
        'resource_id' => $id,
        'reservation_date' => $date,
        'start_time' => $start,
        'end_time' => $end,
        'quantity' => $quantity,
    ];
}

function reservationDataFromAvailability(string $type, int $id): array
{
    return availabilityData($type, $id);
}

function expectInvalidAvailability(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
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
