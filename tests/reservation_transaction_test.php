<?php
declare(strict_types=1);

require __DIR__ . '/../src/App.php';

testTransactionBoundaries();
testConcurrentReservation();

echo "PASS: atomicidad de reservas\n";

function testTransactionBoundaries(): void
{
    $app = App::connect(':memory:');
    $db = $app->db();
    $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    seedDatabase($db);
    $db->sqliteCreateFunction(
        'transaction_active',
        static fn(): int => $db->inTransaction() ? 1 : 0
    );
    $db->exec(
        "CREATE TEMP TRIGGER reservation_insert_transaction
        BEFORE INSERT ON reservations
        WHEN transaction_active() = 0
        BEGIN
            SELECT RAISE(ABORT, 'reservation insert outside transaction');
        END"
    );
    $db->exec(
        "CREATE TEMP TRIGGER reservation_update_transaction
        BEFORE UPDATE ON reservations
        WHEN transaction_active() = 0
        BEGIN
            SELECT RAISE(ABORT, 'reservation update outside transaction');
        END"
    );

    $first = reservationData('09:00', '10:00');
    $app->createReservation(1, $first);
    $firstId = $app->id();
    $app->createReservation(1, reservationData('10:00', '11:00'));
    $app->createReservation(1, $first, $firstId);

    try {
        $app->createReservation(1, reservationData('09:00', '10:00'));
        throw new RuntimeException('FAIL: una reserva superpuesta fue aceptada.');
    } catch (InvalidArgumentException) {
    }

    assertTrue(!$db->inTransaction(), 'Un conflicto dejó una transacción abierta.');
    assertSame(
        2,
        (int) $db->query("SELECT COUNT(*) FROM reservations WHERE status='active'")->fetchColumn(),
        'El rollback alteró las reservas válidas.'
    );
}

function testConcurrentReservation(): void
{
    $databasePath = sys_get_temp_dir() . '/sgsii-concurrency-' . bin2hex(random_bytes(4)) . '.sqlite';
    $gatePath = $databasePath . '.gate';
    $db = new PDO('sqlite:' . $databasePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    seedDatabase($db);
    $db = null;

    $command = [
        PHP_BINARY,
        '-n',
        '-d',
        'extension_dir=' . ini_get('extension_dir'),
        '-d',
        'extension=pdo_sqlite',
        '-d',
        'extension=sqlite3',
        __DIR__ . '/fixtures/reservation_worker.php',
        $databasePath,
        $gatePath,
    ];

    $workers = [startWorker($command), startWorker($command)];
    usleep(150000);
    touch($gatePath);

    try {
        $results = array_map('finishWorker', $workers);
        sort($results);
        assertSame(
            ['CONFLICT', 'SUCCESS'],
            $results,
            'Las solicitudes concurrentes no terminaron en un éxito y un conflicto.'
        );

        $check = new PDO('sqlite:' . $databasePath);
        assertSame(
            1,
            (int) $check->query("SELECT COUNT(*) FROM reservations WHERE status='active'")->fetchColumn(),
            'Se guardaron reservas concurrentes incompatibles.'
        );
        $check = null;
    } finally {
        if (file_exists($gatePath)) unlink($gatePath);
        if (file_exists($databasePath)) unlink($databasePath);
    }
}

function startWorker(array $command): array
{
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('No se pudo iniciar un proceso de reserva.');
    }
    return [$process, $pipes];
}

function finishWorker(array $worker): string
{
    [$process, $pipes] = $worker;
    $output = trim(stream_get_contents($pipes[1]));
    $error = trim(stream_get_contents($pipes[2]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0) {
        throw new RuntimeException("Proceso concurrente falló: {$output} {$error}");
    }
    return $output;
}

function seedDatabase(PDO $db): void
{
    $db->exec(
        "INSERT INTO users (username, first_name, last_name, password_hash, role)
        VALUES ('teacher', 'Ana', 'Docente', 'hash', 'user')"
    );
    $db->exec(
        "INSERT INTO rooms (name, room_number, location, equipment_count)
        VALUES ('Laboratorio', 'L1', 'Piso 1', 20)"
    );
}

function reservationData(string $start, string $end): array
{
    return [
        'resource_type' => 'room',
        'resource_id' => 1,
        'reservation_date' => '2030-01-10',
        'start_time' => $start,
        'end_time' => $end,
    ];
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
