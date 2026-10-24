<?php
declare(strict_types=1);

require __DIR__ . '/../src/Repositories/ReportRepository.php';

use Sgsii\Repositories\ReportRepository;

$database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$database->exec("INSERT INTO users (username,first_name,last_name,password_hash,role) VALUES ('teacher','Ana','Docente','hash','user')");
$database->exec("INSERT INTO rooms (name,room_number,location) VALUES ('Laboratorio','L1','Piso 1')");
$database->exec("INSERT INTO supplies (name,asset_code,category) VALUES ('Notebook','NB-1','Equipo')");
$database->exec("INSERT INTO reservations (user_id,room_id,reservation_date,start_time,end_time) VALUES (1,1,'2030-01-01','09:00','10:00')");
$database->exec("INSERT INTO reservations (user_id,room_id,reservation_date,start_time,end_time) VALUES (1,1,'2030-01-31','09:00','10:00')");
$database->exec("INSERT INTO reservations (user_id,room_id,reservation_date,start_time,end_time,status) VALUES (1,1,'2030-01-15','09:00','10:00','cancelled')");
$database->exec("INSERT INTO loans (supply_id,user_id,quantity,loaned_at) VALUES (1,1,1,'2030-01-31 23:59:59')");
$database->exec("INSERT INTO incidents (reporter_id,room_id,description,urgency,created_at) VALUES (1,1,'Fallo','low','2030-01-01 00:00:00')");

$summary = (new ReportRepository($database))->summary('2030-01-01', '2030-01-31');
assertSame(['Reservas' => 2, 'Préstamos' => 1, 'Incidencias' => 1], $summary, 'El repositorio no contó correctamente los límites inclusivos.');

echo "PASS: report repository\n";

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException("FAIL: {$message}");
    }
}