<?php
declare(strict_types=1);

require __DIR__ . '/../src/App.php';

$app = createReportApp();

$report = $app->reportSummary('2030-01-01', '2030-01-31');
assertSame('2030-01-01', $report['from'], 'El límite desde cambió durante la validación.');
assertSame('2030-01-31', $report['to'], 'El límite hasta cambió durante la validación.');
assertSame(
    ['Reservas' => 2, 'Préstamos' => 2, 'Incidencias' => 2],
    $report['summary'],
    'El reporte no incluyó exactamente ambos límites.'
);

$singleDay = $app->reportSummary('2030-01-01', '2030-01-01');
assertSame(
    ['Reservas' => 1, 'Préstamos' => 1, 'Incidencias' => 1],
    $singleDay['summary'],
    'Un rango de un día no trató el límite como inclusivo.'
);

$leapDay = $app->reportSummary('2032-02-29', '2032-02-29');
assertSame(
    ['Reservas' => 0, 'Préstamos' => 0, 'Incidencias' => 0],
    $leapDay['summary'],
    'Una fecha bisiesta válida fue rechazada o produjo resultados incorrectos.'
);

foreach (['', '2030-1-01', '01-01-2030', '2030-02-30', '2030-00-10', ' 2030-01-01 '] as $invalidDate) {
    expectInvalidReport(
        static fn() => $app->reportSummary($invalidDate, '2030-01-31'),
        "Se aceptó fecha desde inválida: " . json_encode($invalidDate)
    );
    expectInvalidReport(
        static fn() => $app->reportSummary('2030-01-01', $invalidDate),
        "Se aceptó fecha hasta inválida: " . json_encode($invalidDate)
    );
}

foreach ([null, [], 20300101] as $invalidType) {
    expectInvalidReport(
        static fn() => $app->reportSummary($invalidType, '2030-01-31'),
        'Se aceptó un tipo no textual como fecha.'
    );
}

expectInvalidReport(
    static fn() => $app->reportSummary('2030-02-01', '2030-01-31'),
    'Se procesó un rango invertido.',
    'La fecha desde no puede ser posterior a la fecha hasta.'
);

echo "PASS: filtros de reportes\n";

function createReportApp(): App
{
    $app = App::connect(':memory:');
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
        VALUES ('Notebook','NB-1','Equipo',5,'operational')"
    );

    $reservation = $db->prepare(
        "INSERT INTO reservations (user_id,room_id,reservation_date,start_time,end_time,status)
        VALUES (1,1,?,'09:00','10:00',?)"
    );
    $reservation->execute(['2030-01-01', 'active']);
    $reservation->execute(['2030-01-31', 'active']);
    $reservation->execute(['2030-02-01', 'active']);
    $reservation->execute(['2030-01-15', 'cancelled']);

    $loan = $db->prepare(
        "INSERT INTO loans (supply_id,user_id,quantity,loaned_at) VALUES (1,1,1,?)"
    );
    $loan->execute(['2030-01-01 00:00:00']);
    $loan->execute(['2030-01-31 23:59:59']);
    $loan->execute(['2030-02-01 00:00:00']);

    $incident = $db->prepare(
        "INSERT INTO incidents (reporter_id,room_id,description,urgency,created_at)
        VALUES (1,1,'Fallo de prueba','low',?)"
    );
    $incident->execute(['2030-01-01 00:00:00']);
    $incident->execute(['2030-01-31 23:59:59']);
    $incident->execute(['2030-02-01 00:00:00']);
    return $app;
}

function expectInvalidReport(
    callable $operation,
    string $message,
    ?string $expectedMessage = null
): void {
    try {
        $operation();
    } catch (InvalidArgumentException $error) {
        if ($expectedMessage !== null) {
            assertSame($expectedMessage, $error->getMessage(), 'El rango invertido no mostró un mensaje claro.');
        }
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
