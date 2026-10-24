<?php
declare(strict_types=1);

require __DIR__ . '/../src/Services/IncidentService.php';

use Sgsii\Services\IncidentService;

$database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$database->exec("INSERT INTO users (username,first_name,last_name,password_hash,role) VALUES ('teacher','Ana','Docente','hash','user')");
$database->exec("INSERT INTO rooms (name,room_number,location) VALUES ('Laboratorio','L1','Piso 1')");
$service = new IncidentService($database);

$service->report(1, ['resource_type' => 'room', 'resource_id' => 1, 'description' => 'Falla grave', 'urgency' => 'high']);
assertSame(0, (int) $database->query('SELECT active FROM rooms WHERE id=1')->fetchColumn(), 'La incidencia alta no bloqueó la sala.');
$incidentId = (int) $database->lastInsertId();
$available = $service->update($incidentId, ['status' => 'resolved', 'admin_notes' => 'Resuelta']);
assertTrue($available, 'Resolver la última incidencia no rehabilitó el recurso.');
assertSame(1, (int) $database->query('SELECT active FROM rooms WHERE id=1')->fetchColumn(), 'La sala no fue rehabilitada.');

echo "PASS: incident service\n";

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException("FAIL: {$message}");
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) throw new RuntimeException("FAIL: {$message}");
}