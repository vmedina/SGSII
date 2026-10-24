<?php
declare(strict_types=1);

require __DIR__ . '/../src/App.php';

testYesterdayTodayAndTomorrowWithControlledClock();
testFutureReservationCannotMoveToPast();
testHistoricalReservationCanOnlyBeCancelled();

echo "PASS: reglas de fecha de reservas\n";

function testYesterdayTodayAndTomorrowWithControlledClock(): void
{
    $previousTimezone = date_default_timezone_get();
    date_default_timezone_set('Pacific/Honolulu');
    try {
        $app = createDateApp(fixedClock());
        $db = $app->db();

        expectInvalidReservation(
            static fn() => $app->createReservation(1, reservationDateData('2030-05-14')),
            'El servidor aceptó una reserva con fecha de ayer.',
            'no puede ser anterior a hoy'
        );
        assertSame(0, reservationCount($db), 'El rechazo de ayer guardó una reserva.');
        assertTrue(!$db->inTransaction(), 'El rechazo de ayer dejó una transacción abierta.');

        $app->createReservation(1, reservationDateData('2030-05-15'));
        $todayId = $app->id();
        $app->createReservation(1, reservationDateData('2030-05-16'));
        $app->createReservation(1, reservationDateData('2030-05-15', '10:00', '11:00'), $todayId);

        assertSame(2, reservationCount($db), 'Hoy o mañana fueron rechazados incorrectamente.');
        $today = findReservation($db, $todayId);
        assertSame('2030-05-15', $today['reservation_date'], 'La edición válida cambió la fecha de hoy.');
        assertSame('10:00', $today['start_time'], 'No se permitió editar una reserva de hoy.');
    } finally {
        date_default_timezone_set($previousTimezone);
    }
}

function testFutureReservationCannotMoveToPast(): void
{
    $app = createDateApp(fixedClock());
    $db = $app->db();
    $app->createReservation(1, reservationDateData('2030-05-16'));
    $reservationId = $app->id();

    expectInvalidReservation(
        static fn() => $app->createReservation(
            1,
            reservationDateData('2030-05-14', '10:00', '11:00'),
            $reservationId
        ),
        'Una reserva futura fue movida a una fecha pasada.',
        'no puede ser anterior a hoy'
    );

    $reservation = findReservation($db, $reservationId);
    assertSame('2030-05-16', $reservation['reservation_date'], 'El rechazo alteró la fecha futura.');
    assertSame('09:00', $reservation['start_time'], 'El rechazo alteró el horario futuro.');
}

function testHistoricalReservationCanOnlyBeCancelled(): void
{
    $app = createDateApp(fixedClock());
    $db = $app->db();
    $db->exec(
        "INSERT INTO reservations (user_id, room_id, quantity, reservation_date, start_time, end_time)
        VALUES (1, 1, 1, '2030-05-14', '09:00', '10:00')"
    );

    expectInvalidReservation(
        static fn() => $app->createReservation(
            1,
            reservationDateData('2030-05-16', '10:00', '11:00'),
            1
        ),
        'Una reserva histórica fue reprogramada en lugar de conservar el historial.',
        'solo pueden cancelarse'
    );

    assertTrue(!$db->inTransaction(), 'La edición histórica dejó una transacción abierta.');
    $reservation = findReservation($db, 1);
    assertSame('2030-05-14', $reservation['reservation_date'], 'El rechazo reescribió la fecha histórica.');
    assertSame('active', $reservation['status'], 'El rechazo cambió el estado histórico.');

    $app->cancelReservation(1, 1, false);
    assertSame('cancelled', findReservation($db, 1)['status'], 'No se permitió cancelar la reserva histórica.');
}

function createDateApp(DateTimeImmutable $clock): App
{
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
    return $app;
}

function fixedClock(): DateTimeImmutable
{
    return new DateTimeImmutable(
        '2030-05-15 23:30:00',
        new DateTimeZone('Pacific/Kiritimati')
    );
}

function reservationDateData(string $date, string $start = '09:00', string $end = '10:00'): array
{
    return [
        'resource_type' => 'room',
        'resource_id' => 1,
        'reservation_date' => $date,
        'start_time' => $start,
        'end_time' => $end,
    ];
}

function reservationCount(PDO $db): int
{
    return (int)$db->query('SELECT COUNT(*) FROM reservations')->fetchColumn();
}

function findReservation(PDO $db, int $id): array
{
    $statement = $db->prepare('SELECT * FROM reservations WHERE id=?');
    $statement->execute([$id]);
    return $statement->fetch(PDO::FETCH_ASSOC);
}

function expectInvalidReservation(callable $operation, string $message, string $messageFragment): void
{
    try {
        $operation();
    } catch (InvalidArgumentException $error) {
        if (!str_contains($error->getMessage(), $messageFragment)) {
            throw new RuntimeException(
                "FAIL: mensaje inesperado: {$error->getMessage()}"
            );
        }
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
