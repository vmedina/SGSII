<?php
declare(strict_types=1);

require __DIR__ . '/../src/Repositories/IncidentRepository.php';

use Sgsii\Repositories\IncidentRepository;

$database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$database->exec(
    "INSERT INTO users (username,first_name,last_name,password_hash,role) VALUES
    ('ana','Ana','Docente','hash','user'),('bruno','Bruno','Docente','hash','user'),('admin','Ada','Admin','hash','admin')"
);
$database->exec("INSERT INTO rooms (name,room_number,location) VALUES ('Laboratorio','L1','Piso 1')");
$insert = $database->prepare(
    'INSERT INTO incidents (reporter_id,room_id,description,urgency,admin_notes) VALUES (?,?,?,?,?)'
);
$insert->execute([1, 1, 'Falla de Ana', 'high', 'Nota interna de Ana']);
$insert->execute([2, 1, 'Falla de Bruno', 'low', 'Nota interna de Bruno']);

$repository = new IncidentRepository($database);
$viewerRows = $repository->findForViewer(1, false);
assertSame(1, count($viewerRows), 'El docente recibió incidencias ajenas.');
assertTrue(!array_key_exists('admin_notes', $viewerRows[0]), 'El docente recibió notas administrativas.');
assertTrue(!array_key_exists('reporter_id', $viewerRows[0]), 'El docente recibió el reportante interno.');
assertSame('Laboratorio', $viewerRows[0]['resource_name'], 'El recurso no se resolvió.');
assertSame(1, (int) $viewerRows[0]['resource_available'], 'La disponibilidad del recurso no se calculó.');

$adminRows = $repository->findForViewer(3, true);
assertSame(2, count($adminRows), 'El administrador no recibió todas las incidencias.');
$adminNotes = array_column($adminRows, 'admin_notes');
sort($adminNotes);
assertSame(['Nota interna de Ana', 'Nota interna de Bruno'], $adminNotes, 'El administrador no recibió notas internas.');
assertSame('Ana', $adminRows[1]['first_name'], 'El listado administrativo no incluyó al reportante.');

echo "PASS: incident repository\n";

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("FAIL: {$message}");
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException("FAIL: {$message}");
    }
}