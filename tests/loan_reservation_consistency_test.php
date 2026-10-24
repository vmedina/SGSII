<?php
declare(strict_types=1);

require __DIR__ . '/../src/App.php';

testReservationBlocksLoan();
testLoanBlocksReservationUntilReturn();
testQuantityBoundariesAndAtomicWrite();
testReservationPeakInsteadOfTotal();
testConcurrentLoans();

echo "PASS: consistencia entre préstamos y reservas\n";

function testReservationBlocksLoan(): void
{
    $app = createInventoryApp(2);
    insertReservation($app->db(), 2, '2030-01-10', '09:00', '10:00');

    expectInvalidArgument(
        static fn() => $app->loan(loanData(1)),
        'Una reserva que consume todo el stock no bloqueó el préstamo.'
    );
    assertSame(0, openLoanQuantity($app->db()), 'El préstamo rechazado modificó el inventario.');
}

function testLoanBlocksReservationUntilReturn(): void
{
    $app = createInventoryApp(2);
    $app->loan(loanData(2));
    $loanId = $app->id();

    assertTrue(
        !$app->available('supply', 1, '2030-01-10', '09:00', '10:00', 1),
        'Un préstamo abierto no bloqueó una reserva posterior.'
    );

    $app->returnLoan($loanId);

    assertTrue(
        $app->available('supply', 1, '2030-01-10', '09:00', '10:00', 2),
        'La devolución no liberó el stock para reservas.'
    );
}

function testQuantityBoundariesAndAtomicWrite(): void
{
    $app = createInventoryApp(2);
    $db = $app->db();
    insertReservation($db, 1, '2030-01-10', '09:00', '10:00');
    $db->sqliteCreateFunction(
        'transaction_active',
        static fn(): int => $db->inTransaction() ? 1 : 0
    );
    $db->exec(
        "CREATE TEMP TRIGGER loan_insert_transaction
        BEFORE INSERT ON loans
        WHEN transaction_active() = 0
        BEGIN
            SELECT RAISE(ABORT, 'loan insert outside transaction');
        END"
    );

    expectInvalidArgument(
        static fn() => $app->loan(loanData(0)),
        'La cantidad cero fue aceptada.'
    );

    $app->loan(loanData(1));
    assertSame(1, openLoanQuantity($db), 'No se aceptó la cantidad exacta disponible.');

    expectInvalidArgument(
        static fn() => $app->loan(loanData(1)),
        'Se aceptó una unidad por encima del límite comprometido.'
    );
    expectInvalidArgument(
        static fn() => $app->loan(loanData(3)),
        'Se aceptó una cantidad superior al stock total.'
    );

    assertSame(1, openLoanQuantity($db), 'Un rechazo dejó un préstamo parcial guardado.');
    assertTrue(!$db->inTransaction(), 'Un rechazo dejó una transacción abierta.');
}

function testReservationPeakInsteadOfTotal(): void
{
    $app = createInventoryApp(2);
    $db = $app->db();
    insertReservation($db, 1, '2030-01-10', '09:00', '10:00');
    insertReservation($db, 1, '2030-01-10', '10:00', '11:00');

    $app->loan(loanData(1));

    assertSame(
        1,
        openLoanQuantity($db),
        'Las reservas consecutivas se sumaron como si fueran simultáneas.'
    );
}

function testConcurrentLoans(): void
{
    $databasePath = sys_get_temp_dir() . '/sgsii-loan-concurrency-' . bin2hex(random_bytes(4)) . '.sqlite';
    $gatePath = $databasePath . '.gate';
    $db = new PDO('sqlite:' . $databasePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    seedInventory($db, 2);
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
        __DIR__ . '/fixtures/loan_worker.php',
        $databasePath,
        $gatePath,
    ];

    $workers = [startLoanWorker($command), startLoanWorker($command)];
    usleep(150000);
    touch($gatePath);

    try {
        $results = array_map('finishLoanWorker', $workers);
        sort($results);
        assertSame(
            ['CONFLICT', 'SUCCESS'],
            $results,
            'Dos préstamos concurrentes excedieron el stock o ambos fueron rechazados.'
        );

        $check = new PDO('sqlite:' . $databasePath);
        assertSame(2, openLoanQuantity($check), 'El stock comprometido concurrentemente es incorrecto.');
        $check = null;
    } finally {
        if (file_exists($gatePath)) unlink($gatePath);
        if (file_exists($databasePath)) unlink($databasePath);
    }
}

function createInventoryApp(int $stock): App
{
    $app = App::connect(':memory:');
    $db = $app->db();
    $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    seedInventory($db, $stock);
    return $app;
}

function seedInventory(PDO $db, int $stock): void
{
    $db->exec(
        "INSERT INTO users (username, first_name, last_name, password_hash, role)
        VALUES ('teacher', 'Ana', 'Docente', 'hash', 'user')"
    );
    $statement = $db->prepare(
        "INSERT INTO supplies (name, asset_code, category, quantity, status)
        VALUES ('Notebook', 'NB-1', 'Informática', ?, 'operational')"
    );
    $statement->execute([$stock]);
}

function insertReservation(PDO $db, int $quantity, string $date, string $start, string $end): void
{
    $statement = $db->prepare(
        'INSERT INTO reservations (user_id, supply_id, quantity, reservation_date, start_time, end_time)
        VALUES (1, 1, ?, ?, ?, ?)'
    );
    $statement->execute([$quantity, $date, $start, $end]);
}

function loanData(int $quantity): array
{
    return [
        'supply_id' => 1,
        'user_id' => 1,
        'quantity' => $quantity,
        'notes' => 'Prueba de inventario',
    ];
}

function openLoanQuantity(PDO $db): int
{
    return (int) $db->query(
        'SELECT COALESCE(SUM(quantity), 0) FROM loans WHERE returned_at IS NULL'
    )->fetchColumn();
}

function expectInvalidArgument(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException("FAIL: {$message}");
}

function startLoanWorker(array $command): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('No se pudo iniciar un proceso de préstamo.');
    }
    return [$process, $pipes];
}

function finishLoanWorker(array $worker): string
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
