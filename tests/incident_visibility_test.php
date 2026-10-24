<?php
declare(strict_types=1);

require __DIR__ . '/../src/App.php';

$app = createVisibilityApp();
$app->incident(1, incidentInput('Incidencia privada de Ana', 2));
$anaIncidentId = $app->id();
$app->updateIncident($anaIncidentId, ['status' => 'open', 'admin_notes' => 'Nota interna sobre Ana']);
$app->incident(2, incidentInput('Incidencia privada de Bruno', 1));
$brunoIncidentId = $app->id();
$app->updateIncident($brunoIncidentId, ['status' => 'open', 'admin_notes' => 'Nota interna sobre Bruno']);

$anaRows = $app->incidentsFor(1, false);
assertSame([$anaIncidentId], rowIds($anaRows), 'Ana pudo ver incidencias de otra cuenta.');
assertSame('Incidencia privada de Ana', $anaRows[0]['description'], 'Ana no vio su propia incidencia.');
assertTrue(!array_key_exists('admin_notes', $anaRows[0]), 'Las notas administrativas se cargaron para un docente.');
assertTrue(!array_key_exists('reporter_id', $anaRows[0]), 'El listado docente expuso identificadores internos de reportantes.');

$brunoRows = $app->incidentsFor(2, false);
assertSame([$brunoIncidentId], rowIds($brunoRows), 'Bruno pudo ver incidencias de otra cuenta.');

$adminRows = $app->incidentsFor(3, true);
assertSame([$anaIncidentId, $brunoIncidentId], rowIds($adminRows), 'ADMIN no pudo ver todas las incidencias.');
assertSame(
    ['Nota interna sobre Ana', 'Nota interna sobre Bruno'],
    sortedColumn($adminRows, 'admin_notes'),
    'ADMIN no recibió las notas necesarias para gestionar incidencias.'
);

$forgedReporter = $app->one('SELECT reporter_id FROM incidents WHERE id=?', [$anaIncidentId]);
assertSame(1, (int)$forgedReporter['reporter_id'], 'El POST pudo sustituir al reportante autenticado.');

echo "PASS: privacidad de incidencias\n";

function createVisibilityApp(): App
{
    $app = App::connect(':memory:');
    $db = $app->db();
    $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    $db->exec(
        "INSERT INTO users (username,first_name,last_name,password_hash,role) VALUES
        ('ana','Ana','Docente','hash','user'),
        ('bruno','Bruno','Docente','hash','user'),
        ('admin','Ada','Admin','hash','admin')"
    );
    $db->exec(
        "INSERT INTO rooms (name,room_number,location,equipment_count)
        VALUES ('Laboratorio','L1','Piso 1',20)"
    );
    return $app;
}

function incidentInput(string $description, int $forgedReporterId): array
{
    return [
        'resource_type' => 'room',
        'resource_id' => 1,
        'description' => $description,
        'urgency' => 'low',
        'reporter_id' => $forgedReporterId,
    ];
}

function rowIds(array $rows): array
{
    $ids = array_map(static fn(array $row): int => (int)$row['id'], $rows);
    sort($ids);
    return $ids;
}

function sortedColumn(array $rows, string $column): array
{
    $values = array_column($rows, $column);
    sort($values);
    return $values;
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
