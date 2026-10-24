<?php
declare(strict_types=1);

require __DIR__ . '/../src/App.php';

testAdminCreatesReservationForTeacher();
testAdminTargetMustBeActiveTeacher();
testTeacherCannotForgeReservationOwner();
testEditPreservesOwnerUnlessAdminChangesIt();

echo "PASS: asignación de responsable de reserva\n";

function testAdminCreatesReservationForTeacher(): void
{
    $app = createAssignmentApp();
    $app->createReservation(4, assignmentData('09:00', '10:00', 1), null, true);

    assertSame(1, reservationOwner($app->db()), 'La reserva administrativa quedó asociada al administrador.');
    assertSame(
        1,
        (int)$app->db()->query('SELECT COUNT(*) FROM reservations WHERE user_id=1')->fetchColumn(),
        'La reserva no aparece entre las reservas del docente elegido.'
    );
}

function testAdminTargetMustBeActiveTeacher(): void
{
    foreach ([3, 4, 999] as $invalidTarget) {
        $app = createAssignmentApp();
        expectInvalidAssignment(
            static fn() => $app->createReservation(
                4,
                assignmentData('09:00', '10:00', $invalidTarget),
                null,
                true
            ),
            "Se aceptó el responsable inválido {$invalidTarget}."
        );
        assertSame(0, reservationCount($app->db()), 'El responsable inválido dejó una reserva guardada.');
        assertTrue(!$app->db()->inTransaction(), 'El responsable inválido dejó una transacción abierta.');
    }

    $app = createAssignmentApp();
    $data = assignmentData('09:00', '10:00', 1);
    unset($data['user_id']);
    expectInvalidAssignment(
        static fn() => $app->createReservation(4, $data, null, true),
        'El administrador creó una reserva sin seleccionar docente.'
    );
}

function testTeacherCannotForgeReservationOwner(): void
{
    $app = createAssignmentApp();
    $app->createReservation(1, assignmentData('09:00', '10:00', 2));

    assertSame(1, reservationOwner($app->db()), 'Un docente creó una reserva para otra cuenta manipulando user_id.');
}

function testEditPreservesOwnerUnlessAdminChangesIt(): void
{
    $app = createAssignmentApp();
    $db = $app->db();
    $app->createReservation(1, assignmentData('09:00', '10:00', 2));
    $reservationId = $app->id();

    $adminEdit = assignmentData('10:00', '11:00', 2);
    unset($adminEdit['user_id']);
    $app->createReservation(4, $adminEdit, $reservationId, true);
    assertSame(1, reservationOwner($db), 'Editar sin responsable cambió el propietario original.');

    $app->createReservation(4, assignmentData('11:00', '12:00', 2), $reservationId, true);
    assertSame(2, reservationOwner($db), 'El administrador no pudo cambiar explícitamente el responsable.');

    $app->createReservation(2, assignmentData('12:00', '13:00', 1), $reservationId);
    assertSame(2, reservationOwner($db), 'El docente cambió el propietario manipulando una edición.');
}

function createAssignmentApp(): App
{
    $clock = new DateTimeImmutable('2030-05-15 12:00:00', new DateTimeZone('UTC'));
    $app = App::connect(':memory:', $clock);
    $db = $app->db();
    $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    $db->exec(
        "INSERT INTO users (username, first_name, last_name, password_hash, role, active) VALUES
        ('teacher1', 'Ana', 'Docente', 'hash', 'user', 1),
        ('teacher2', 'Bruno', 'Docente', 'hash', 'user', 1),
        ('inactive', 'Celia', 'Inactiva', 'hash', 'user', 0),
        ('admin', 'Ada', 'Admin', 'hash', 'admin', 1)"
    );
    $db->exec(
        "INSERT INTO rooms (name, room_number, location, equipment_count)
        VALUES ('Laboratorio', 'L1', 'Piso 1', 20)"
    );
    return $app;
}

function assignmentData(string $start, string $end, int $userId): array
{
    return [
        'resource_type' => 'room',
        'resource_id' => 1,
        'reservation_date' => '2030-05-16',
        'start_time' => $start,
        'end_time' => $end,
        'user_id' => $userId,
    ];
}

function reservationOwner(PDO $db): int
{
    return (int)$db->query('SELECT user_id FROM reservations ORDER BY id LIMIT 1')->fetchColumn();
}

function reservationCount(PDO $db): int
{
    return (int)$db->query('SELECT COUNT(*) FROM reservations')->fetchColumn();
}

function expectInvalidAssignment(callable $operation, string $message): void
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
