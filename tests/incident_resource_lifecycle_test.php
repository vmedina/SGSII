<?php
declare(strict_types=1);

require __DIR__ . '/../src/App.php';

testHighIncidentBlocksAndRestoresRoom();
testLowIncidentBlocksOnlyWhileInProgress();
testOtherBlockingIncidentPreventsRestore();
testResolvingNonBlockingIncidentPreservesManualState();
testBlockingIncidentRejectsContradictoryManualState();
testMissingResourceIsRejected();

echo "PASS: ciclo de incidencias y recursos\n";

function testHighIncidentBlocksAndRestoresRoom(): void
{
    $app = createIncidentApp();
    $app->incident(1, incidentData('room', 1, 'high'));
    $incidentId = $app->id();

    assertSame(0, roomActive($app), 'Una incidencia alta no bloqueó la sala.');
    $available = $app->updateIncident($incidentId, ['status' => 'in_progress', 'admin_notes' => 'Revisión iniciada']);
    assertSame(false, $available, 'La sala quedó disponible mientras la incidencia alta seguía en proceso.');
    assertSame(0, roomActive($app), 'La sala se rehabilitó antes de resolver la incidencia alta.');

    $available = $app->updateIncident($incidentId, ['status' => 'resolved', 'admin_notes' => 'Resuelta']);
    assertSame(true, $available, 'La sala no se informó disponible al resolver el último bloqueo.');
    assertSame(1, roomActive($app), 'La sala no se rehabilitó al resolver el último bloqueo.');
    $incident = incidentRow($app, $incidentId);
    assertSame('resolved', $incident['status'], 'La incidencia no quedó resuelta.');
    assertTrue($incident['resolved_at'] !== null, 'La resolución no guardó fecha.');
}

function testLowIncidentBlocksOnlyWhileInProgress(): void
{
    $app = createIncidentApp();
    $app->incident(1, incidentData('room', 1, 'low'));
    $incidentId = $app->id();

    assertSame(1, roomActive($app), 'Una incidencia baja abierta bloqueó automáticamente la sala.');
    $app->updateIncident($incidentId, ['status' => 'in_progress', 'admin_notes' => 'Bloqueo preventivo']);
    assertSame(0, roomActive($app), 'La acción administrativa En proceso no bloqueó la sala.');
    $app->updateIncident($incidentId, ['status' => 'resolved', 'admin_notes' => 'Comprobada']);
    assertSame(1, roomActive($app), 'La sala no se rehabilitó al terminar el bloqueo manual.');
}

function testOtherBlockingIncidentPreventsRestore(): void
{
    $app = createIncidentApp();
    $app->incident(1, incidentData('supply', 1, 'high', 'Primer fallo grave'));
    $firstId = $app->id();
    $app->incident(1, incidentData('supply', 1, 'high', 'Segundo fallo grave'));
    $secondId = $app->id();

    assertSame('repair', supplyStatus($app), 'Las incidencias altas no bloquearon el insumo.');
    $available = $app->updateIncident($firstId, ['status' => 'resolved', 'admin_notes' => 'Primera resuelta']);
    assertSame(false, $available, 'Resolver una incidencia ignoró otro bloqueo abierto.');
    assertSame('repair', supplyStatus($app), 'El insumo se rehabilitó con otro bloqueo abierto.');

    $available = $app->updateIncident($secondId, ['status' => 'resolved', 'admin_notes' => 'Segunda resuelta']);
    assertSame(true, $available, 'El insumo no se informó disponible al resolver el último bloqueo.');
    assertSame('operational', supplyStatus($app), 'El insumo no se rehabilitó al resolver el último bloqueo.');
}

function testResolvingNonBlockingIncidentPreservesManualState(): void
{
    $app = createIncidentApp();
    $app->execute("UPDATE supplies SET status='out_of_service' WHERE id=1");
    $app->incident(1, incidentData('supply', 1, 'medium'));
    $incidentId = $app->id();

    $available = $app->updateIncident($incidentId, ['status' => 'resolved', 'admin_notes' => 'Registrada']);
    assertSame(false, $available, 'Se informó disponible un insumo fuera de servicio.');
    assertSame('out_of_service', supplyStatus($app), 'Resolver una incidencia no bloqueante borró un estado manual.');
}

function testBlockingIncidentRejectsContradictoryManualState(): void
{
    $app = createIncidentApp();
    $app->incident(1, incidentData('supply', 1, 'high'));
    expectInvalidIncident(
        static fn() => $app->saveSupply([
            'name' => 'Notebook',
            'asset_code' => 'NB-1',
            'category' => 'Equipo',
            'description' => '',
            'quantity' => 5,
            'status' => 'out_of_service',
        ], 1),
        'Se aceptó un estado de insumo contradictorio con una incidencia bloqueante.'
    );
    assertSame('repair', supplyStatus($app), 'El insumo dejó el estado administrado por la incidencia.');

    $app = createIncidentApp();
    $app->incident(1, incidentData('room', 1, 'high'));
    expectInvalidIncident(
        static fn() => $app->deactivate('rooms', 1),
        'Se aceptó una desactivación de sala indistinguible del bloqueo por incidencia.'
    );
}

function testMissingResourceIsRejected(): void
{
    foreach (['room', 'supply'] as $type) {
        $app = createIncidentApp();
        expectInvalidIncident(
            static fn() => $app->incident(1, incidentData($type, 999, 'high')),
            "Se aceptó una incidencia para {$type} inexistente."
        );
        assertSame(0, incidentCount($app), 'El recurso inexistente dejó una incidencia guardada.');
        assertTrue(!$app->db()->inTransaction(), 'El recurso inexistente dejó una transacción abierta.');
    }

    $app = createIncidentApp();
    expectInvalidIncident(
        static fn() => $app->updateIncident(999, ['status' => 'resolved', 'admin_notes' => '']),
        'Se actualizó una incidencia inexistente.'
    );
}

function createIncidentApp(): App
{
    $app = App::connect(':memory:');
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
        "INSERT INTO supplies (name, asset_code, category, quantity, status)
        VALUES ('Notebook', 'NB-1', 'Equipo', 5, 'operational')"
    );
    return $app;
}

function incidentData(string $type, int $resourceId, string $urgency, string $description = 'Fallo detectado'): array
{
    return [
        'resource_type' => $type,
        'resource_id' => $resourceId,
        'description' => $description,
        'urgency' => $urgency,
    ];
}

function incidentRow(App $app, int $id): array
{
    return $app->one('SELECT * FROM incidents WHERE id=?', [$id]) ?? [];
}

function incidentCount(App $app): int
{
    return (int)$app->one('SELECT COUNT(*) AS total FROM incidents')['total'];
}

function roomActive(App $app): int
{
    return (int)$app->one('SELECT active FROM rooms WHERE id=1')['active'];
}

function supplyStatus(App $app): string
{
    return (string)$app->one('SELECT status FROM supplies WHERE id=1')['status'];
}

function expectInvalidIncident(callable $operation, string $message): void
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
