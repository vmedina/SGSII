<?php
declare(strict_types=1);

require __DIR__ . '/../src/Services/ResourceService.php';
require __DIR__ . '/../src/Repositories/ResourceRepository.php';

use Sgsii\Repositories\ResourceRepository;
use Sgsii\Services\ResourceService;

$database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$database->exec("INSERT INTO users (username,first_name,last_name,password_hash,role) VALUES ('teacher','Ana','Docente','hash','user')");
$service = new ResourceService(
    $database,
    new ResourceRepository($database),
    new DateTimeImmutable('2030-01-01 08:00:00')
);

$service->saveRoom(['name' => 'Laboratorio', 'room_number' => 'L1', 'location' => 'Piso 1', 'equipment_count' => 20]);
$service->saveSupply([
    'name' => 'Notebook',
    'asset_code' => 'NB-1',
    'category' => 'Equipo',
    'description' => '',
    'quantity' => 5,
    'status' => 'operational',
]);
$service->saveRoom(['name' => 'Aula', 'room_number' => 'A1', 'location' => 'Piso 2', 'equipment_count' => 24], 1);
$service->saveSupply([
    'name' => 'Tablet',
    'asset_code' => 'TB-1',
    'category' => 'Equipo',
    'description' => 'Movil',
    'quantity' => 3,
    'status' => 'repair',
], 1);
$service->deactivateRoom(1);
$service->deactivateSupply(1);

assertSame('Aula', $database->query('SELECT name FROM rooms WHERE id=1')->fetchColumn(), 'ResourceService no actualizó la sala.');
assertSame(0, (int) $database->query('SELECT active FROM rooms WHERE id=1')->fetchColumn(), 'ResourceService no desactivó la sala.');
assertSame('Tablet', $database->query('SELECT name FROM supplies WHERE id=1')->fetchColumn(), 'ResourceService no actualizó el insumo.');
assertSame(0, (int) $database->query('SELECT active FROM supplies WHERE id=1')->fetchColumn(), 'ResourceService no desactivó el insumo.');

echo "PASS: resource service\n";

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) throw new RuntimeException("FAIL: {$message}");
}