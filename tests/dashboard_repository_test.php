<?php
declare(strict_types=1);

require __DIR__ . '/../src/Repositories/DashboardRepository.php';

use Sgsii\Repositories\DashboardRepository;

$database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$database->exec("INSERT INTO users (username,first_name,last_name,password_hash,role) VALUES ('teacher','Ana','Docente','hash','user')");
$database->exec("INSERT INTO rooms (name,room_number,location) VALUES ('Laboratorio','L1','Piso 1')");
$database->exec("INSERT INTO supplies (name,asset_code,category) VALUES ('Notebook','NB-1','Informática')");
$insert = $database->prepare(
    'INSERT INTO reservations (user_id,room_id,quantity,reservation_date,start_time,end_time) VALUES (1,1,1,?,?,?)'
);
$insert->execute(['2030-01-10', '11:00', '12:00']);
$insert = $database->prepare(
    'INSERT INTO reservations (user_id,supply_id,quantity,reservation_date,start_time,end_time) VALUES (1,1,2,?,?,?)'
);
$insert->execute(['2030-01-10', '09:00', '10:00']);
$insert->execute(['2030-01-11', '08:00', '09:00']);
$database->exec("UPDATE reservations SET status='cancelled' WHERE id=3");

$events = (new DashboardRepository($database))->findActiveReservationsForDate('2030-01-10');
assertSame(2, count($events), 'El dashboard devolvió reservas fuera de fecha o canceladas.');
assertSame('09:00', $events[0]['start_time'], 'El dashboard no ordenó por hora.');
assertSame('Insumo', $events[0]['resource_type'], 'El tipo de insumo no se calculó.');
assertSame('Notebook', $events[0]['resource_name'], 'El nombre del insumo no se resolvió.');
assertSame('Sala', $events[1]['resource_type'], 'El tipo de sala no se calculó.');
assertSame('Laboratorio', $events[1]['resource_name'], 'El nombre de la sala no se resolvió.');

echo "PASS: dashboard repository\n";

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException("FAIL: {$message}");
    }
}