<?php
declare(strict_types=1);

require __DIR__ . '/../src/Repositories/UserRepository.php';
require __DIR__ . '/../src/Repositories/ResourceRepository.php';

use Sgsii\Repositories\ResourceRepository;
use Sgsii\Repositories\UserRepository;

$database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$users = new UserRepository($database);
$resources = new ResourceRepository($database);

$users->create(['ana', 'Ana', 'Docente', '', 'hash', 'user']);
$users->update(1, ['ana-updated', 'Ana', 'Actualizada', 'Sistemas', 'user']);
$users->deactivate(1);
$resources->createRoom(['Laboratorio', 'L1', 'Piso 1', 20]);
$resources->updateRoom(1, ['Aula', 'A1', 'Piso 2', 24]);
$resources->deactivateRoom(1);
$resources->createSupply(['Notebook', 'NB-1', 'Equipo', '', 5, 'operational']);
$resources->updateSupply(1, ['Tablet', 'TB-1', 'Equipo', 'Movil', 3, 'repair']);
$resources->deactivateSupply(1);

assertSame('ana-updated', $database->query('SELECT username FROM users WHERE id=1')->fetchColumn(), 'No se actualizó el usuario.');
assertSame(0, (int) $database->query('SELECT active FROM users WHERE id=1')->fetchColumn(), 'No se desactivó el usuario.');
assertSame('Aula', $database->query('SELECT name FROM rooms WHERE id=1')->fetchColumn(), 'No se actualizó la sala.');
assertSame(0, (int) $database->query('SELECT active FROM rooms WHERE id=1')->fetchColumn(), 'No se desactivó la sala.');
assertSame('Tablet', $database->query('SELECT name FROM supplies WHERE id=1')->fetchColumn(), 'No se actualizó el insumo.');
assertSame(0, (int) $database->query('SELECT active FROM supplies WHERE id=1')->fetchColumn(), 'No se desactivó el insumo.');

echo "PASS: CRUD repositories\n";

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException("FAIL: {$message}");
    }
}