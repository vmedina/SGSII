<?php
declare(strict_types=1);

require __DIR__ . '/../src/App.php';

testStockReductionIsRejectedWithoutMutation();
testNonOperationalStatusIsRejected();
testDeactivationIsRejected();
testReleasedCommitmentsAllowChanges();
testSupplyUpdatesAreAtomic();
testConcurrentLoanAndStockReduction();

echo "PASS: invariantes de stock y estado de insumos\n";

function testStockReductionIsRejectedWithoutMutation(): void
{
    $app = createSupplyApp(5);
    $db = $app->db();
    insertOpenLoan($db, 2);
    insertSupplyReservation($db, 2, '2030-01-10', '09:00', '10:00');

    expectInvalidSupplyChange(
        static fn() => $app->saveSupply(supplyData(3, 'operational', 'Nombre alterado'), 1),
        'Se redujo el stock por debajo de las cuatro unidades comprometidas.'
    );

    $supply = findSupply($db);
    assertSame(5, (int)$supply['quantity'], 'El rechazo modificó la cantidad.');
    assertSame('Notebook', $supply['name'], 'El rechazo modificó otros datos del insumo.');

    $app->saveSupply(supplyData(4), 1);
    assertSame(4, (int)findSupply($db)['quantity'], 'Se rechazó el límite exacto comprometido.');
}

function testNonOperationalStatusIsRejected(): void
{
    foreach (['repair', 'out_of_service'] as $status) {
        $app = createSupplyApp(2);
        $db = $app->db();
        insertSupplyReservation($db, 1, '2030-01-10', '09:00', '10:00');

        expectInvalidSupplyChange(
            static fn() => $app->saveSupply(supplyData(2, $status), 1),
            "Se aceptó el estado {$status} con una reserva activa."
        );

        assertSame('operational', findSupply($db)['status'], 'El rechazo cambió el estado operativo.');
    }
}

function testDeactivationIsRejected(): void
{
    $app = createSupplyApp(2);
    $db = $app->db();
    insertOpenLoan($db, 1);

    expectInvalidSupplyChange(
        static fn() => $app->deactivate('supplies', 1),
        'Se desactivó un insumo con un préstamo abierto.'
    );

    assertSame(1, (int)findSupply($db)['active'], 'El rechazo dejó el insumo inactivo.');
}

function testReleasedCommitmentsAllowChanges(): void
{
    $app = createSupplyApp(2);
    $db = $app->db();
    insertOpenLoan($db, 2);
    insertSupplyReservation($db, 2, '2030-01-10', '09:00', '10:00');
    $db->exec("UPDATE loans SET returned_at=CURRENT_TIMESTAMP");
    $db->exec("UPDATE reservations SET status='cancelled'");
    insertSupplyReservation($db, 10, '2020-01-10', '09:00', '10:00');

    $app->saveSupply(supplyData(0, 'repair'), 1);
    $app->deactivate('supplies', 1);

    $supply = findSupply($db);
    assertSame(0, (int)$supply['quantity'], 'No se permitió reducir stock sin compromisos.');
    assertSame('repair', $supply['status'], 'No se permitió cambiar el estado sin compromisos.');
    assertSame(0, (int)$supply['active'], 'No se permitió desactivar sin compromisos.');
}

function testSupplyUpdatesAreAtomic(): void
{
    $app = createSupplyApp(1);
    $db = $app->db();
    $db->sqliteCreateFunction(
        'transaction_active',
        static fn(): int => $db->inTransaction() ? 1 : 0
    );
    $db->exec(
        "CREATE TEMP TRIGGER supply_update_transaction
        BEFORE UPDATE ON supplies
        WHEN transaction_active() = 0
        BEGIN
            SELECT RAISE(ABORT, 'supply update outside transaction');
        END"
    );

    $app->saveSupply(supplyData(2), 1);
    assertSame(2, (int)findSupply($db)['quantity'], 'La actualización atómica válida no se guardó.');

    $db->exec(
        "CREATE TEMP TRIGGER reject_supply_name
        BEFORE UPDATE ON supplies
        WHEN NEW.name = 'Provocar rollback'
        BEGIN
            SELECT RAISE(ABORT, 'forced rollback');
        END"
    );
    try {
        $app->saveSupply(supplyData(3, 'operational', 'Provocar rollback'), 1);
        throw new RuntimeException('FAIL: no se propagó el error forzado del UPDATE.');
    } catch (PDOException) {
    }

    assertTrue(!$db->inTransaction(), 'El error dejó una transacción abierta.');
    $supply = findSupply($db);
    assertSame(2, (int)$supply['quantity'], 'El rollback dejó una actualización parcial.');
    assertSame('Notebook', $supply['name'], 'El rollback modificó el nombre.');

    $app->deactivate('supplies', 1);
    assertSame(0, (int)findSupply($db)['active'], 'La desactivación no se ejecutó atómicamente.');
}

function testConcurrentLoanAndStockReduction(): void
{
    $databasePath = sys_get_temp_dir() . '/sgsii-supply-concurrency-' . bin2hex(random_bytes(4)) . '.sqlite';
    $gatePath = $databasePath . '.gate';
    $db = new PDO('sqlite:' . $databasePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    seedSupply($db, 2);
    $db = null;

    $baseCommand = [
        PHP_BINARY,
        '-n',
        '-d',
        'extension_dir=' . ini_get('extension_dir'),
        '-d',
        'extension=pdo_sqlite',
        '-d',
        'extension=sqlite3',
        __DIR__ . '/fixtures/supply_worker.php',
        $databasePath,
        $gatePath,
    ];
    $loanCommand = [...$baseCommand, 'loan'];
    $updateCommand = [...$baseCommand, 'update'];
    $workers = [startSupplyWorker($loanCommand), startSupplyWorker($updateCommand)];
    usleep(150000);
    touch($gatePath);

    try {
        $results = array_map('finishSupplyWorker', $workers);
        sort($results);
        assertSame(
            ['CONFLICT', 'SUCCESS'],
            $results,
            'El préstamo y la reducción concurrentes no respetaron una única operación válida.'
        );

        $check = new PDO('sqlite:' . $databasePath);
        $stock = (int)$check->query('SELECT quantity FROM supplies WHERE id=1')->fetchColumn();
        $loaned = (int)$check->query(
            'SELECT COALESCE(SUM(quantity), 0) FROM loans WHERE returned_at IS NULL'
        )->fetchColumn();
        assertTrue($stock >= $loaned, 'La carrera dejó más unidades prestadas que stock.');
        $check = null;
    } finally {
        if (file_exists($gatePath)) unlink($gatePath);
        if (file_exists($databasePath)) unlink($databasePath);
    }
}

function createSupplyApp(int $stock): App
{
    $app = App::connect(':memory:');
    $db = $app->db();
    $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    seedSupply($db, $stock);
    return $app;
}

function seedSupply(PDO $db, int $stock): void
{
    $db->exec(
        "INSERT INTO users (username, first_name, last_name, password_hash, role)
        VALUES ('teacher', 'Ana', 'Docente', 'hash', 'user')"
    );
    $statement = $db->prepare(
        "INSERT INTO supplies (name, asset_code, category, description, quantity, status)
        VALUES ('Notebook', 'NB-1', 'Informática', 'Equipo', ?, 'operational')"
    );
    $statement->execute([$stock]);
}

function insertOpenLoan(PDO $db, int $quantity): void
{
    $statement = $db->prepare(
        "INSERT INTO loans (supply_id, user_id, quantity, notes) VALUES (1, 1, ?, 'Prueba')"
    );
    $statement->execute([$quantity]);
}

function insertSupplyReservation(PDO $db, int $quantity, string $date, string $start, string $end): void
{
    $statement = $db->prepare(
        'INSERT INTO reservations (user_id, supply_id, quantity, reservation_date, start_time, end_time)
        VALUES (1, 1, ?, ?, ?, ?)'
    );
    $statement->execute([$quantity, $date, $start, $end]);
}

function supplyData(int $quantity, string $status = 'operational', string $name = 'Notebook'): array
{
    return [
        'name' => $name,
        'asset_code' => 'NB-1',
        'category' => 'Informática',
        'description' => 'Equipo',
        'quantity' => $quantity,
        'status' => $status,
    ];
}

function findSupply(PDO $db): array
{
    return $db->query('SELECT * FROM supplies WHERE id=1')->fetch(PDO::FETCH_ASSOC);
}

function expectInvalidSupplyChange(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException("FAIL: {$message}");
}

function startSupplyWorker(array $command): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('No se pudo iniciar un proceso de inventario.');
    }
    return [$process, $pipes];
}

function finishSupplyWorker(array $worker): string
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
